<?php
function config(): array
{
    static $config;
    return $config ??= require (is_file(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/config.example.php');
}
function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function base_path(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = dirname($script);
    if (basename($dir) === 'admin') $dir = dirname($dir);
    return $dir === '/' || $dir === '.' ? '' : implode('/', array_map(fn($segment) => rawurlencode(rawurldecode($segment)), explode('/', rtrim($dir, '/'))));
}
function url(string $path = ''): string { return base_path() . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path), true, 303); exit; }
function flash(string $message): void { $_SESSION['flash'] = $message; }
function statuses(): array { return ['planned' => 'Direncanakan', 'in_progress' => 'Dikerjakan', 'done' => 'Selesai', 'blocked' => 'Terkendala']; }
function badge(string $status): string { return '<span class="badge ' . e($status) . '"><span class="dot"></span>' . e(statuses()[$status] ?? $status) . '</span>'; }
function date_label(string $date): string
{
    $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $t = strtotime($date);
    return date('j', $t) . ' ' . $months[(int)date('n', $t)-1] . ' ' . date('Y', $t);
}
function valid_date(string $date): bool { $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date); return $d && $d->format('Y-m-d') === $date; }
function categories(): array { return query('SELECT * FROM categories ORDER BY name')->fetchAll(); }
function find_log(int $id, bool $admin = false): array|false
{
    return query('SELECT l.*, c.name AS category_name, c.color AS category_color FROM logs l LEFT JOIN categories c ON c.id = l.category_id WHERE l.id = ?' . ($admin ? ' AND l.user_id = ?' : " AND l.visibility = 'public'"), $admin ? [$id, (int)$_SESSION['user_id']] : [$id])->fetch();
}
function validated_log(array $input): array
{
    $data = [];
    foreach (['title','description','log_date','status','blocker_note','links','visibility'] as $key) $data[$key] = trim((string)($input[$key] ?? ''));
    if ($data['title'] === '' || mb_strlen($data['title']) > 120) throw new InvalidArgumentException('Judul wajib diisi, maksimal 120 karakter.');
    if (!valid_date($data['log_date'])) throw new InvalidArgumentException('Tanggal catatan tidak valid.');
    if (!array_key_exists($data['status'], statuses())) throw new InvalidArgumentException('Status tidak valid.');
    if (!in_array($data['visibility'], ['public','private'], true)) throw new InvalidArgumentException('Visibilitas tidak valid.');
    $progress = filter_var($input['progress'] ?? null, FILTER_VALIDATE_INT);
    if ($progress === false || $progress === null || $progress < 0 || $progress > 100) throw new InvalidArgumentException('Progres harus antara 0 dan 100.');
    $data['progress'] = $data['status'] === 'done' ? 100 : $progress;
    if ($data['status'] === 'blocked' && $data['blocker_note'] === '') throw new InvalidArgumentException('Tuliskan kendala sebelum memilih status terkendala.');
    foreach (['description','blocker_note','links'] as $key) if (strlen($data[$key]) > 60000) throw new InvalidArgumentException('Teks terlalu panjang. Maksimal 60.000 byte per kolom.');
    foreach (preg_split('/\R/', $data['links']) as $link) {
        if ($link !== '' && (!filter_var(trim($link), FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url(trim($link), PHP_URL_SCHEME)), ['http','https'], true))) throw new InvalidArgumentException('Tautan harus berupa URL http atau https yang valid, satu per baris.');
    }
    $data['category_id'] = empty($input['category_id']) ? null : filter_var($input['category_id'], FILTER_VALIDATE_INT);
    if ($data['category_id'] !== null && (!$data['category_id'] || !query('SELECT id FROM categories WHERE id = ?', [$data['category_id']])->fetch())) throw new InvalidArgumentException('Kategori tidak ditemukan.');
    return $data;
}
function save_log(array $data, ?int $id = null): int
{
    db()->beginTransaction();
    try {
        $old = $id ? query('SELECT * FROM logs WHERE id = ? AND user_id = ? FOR UPDATE', [$id, (int)$_SESSION['user_id']])->fetch() : false;
        if ($id && !$old) throw new InvalidArgumentException('Catatan tidak ditemukan.');
        // Explicit column mapping keeps parameter order independent of caller array order.
        $fields = ['title','description','log_date','status','blocker_note','links','visibility','progress','category_id'];
        $params = array_map(fn($key) => $data[$key], $fields);
        if ($id) {
            query('UPDATE logs SET title=?,description=?,log_date=?,status=?,blocker_note=?,links=?,visibility=?,progress=?,category_id=? WHERE id=? AND user_id=?', [...$params, $id, (int)$_SESSION['user_id']]);
        } else {
            query('INSERT INTO logs (title,description,log_date,status,blocker_note,links,visibility,progress,category_id,user_id) VALUES (?,?,?,?,?,?,?,?,?,?)', [...$params, (int)$_SESSION['user_id']]);
            $id = (int)db()->lastInsertId();
        }
        if (!$old || $old['status'] !== $data['status'] || (int)$old['progress'] !== $data['progress']) query('INSERT INTO log_history (log_id,old_status,new_status,progress) VALUES (?,?,?,?)', [$id, $old['status'] ?? null, $data['status'], $data['progress']]);
        db()->commit(); return $id;
    } catch (Throwable $error) { db()->rollBack(); throw $error; }
}
function filtered_logs(bool $admin = false, bool $all = false): array
{
    $where = [$admin ? 'l.user_id = :owner' : "l.visibility = 'public'"];
    $params = $admin ? ['owner' => (int)$_SESSION['user_id']] : [];
    $filters = [];
    foreach (['status','category','from','to','q'] as $key) $filters[$key] = trim((string)($_GET[$key] ?? ''));
    if (isset(statuses()[$filters['status']])) { $where[] = 'l.status = :status'; $params['status'] = $filters['status']; }
    if (ctype_digit($filters['category'])) { $where[] = 'l.category_id = :category'; $params['category'] = (int)$filters['category']; }
    foreach (['from' => '>=', 'to' => '<='] as $key => $op) if (valid_date($filters[$key])) { $where[] = 'l.log_date ' . $op . ' :' . $key; $params[$key] = $filters[$key]; }
    if ($filters['q'] !== '') { $where[] = '(l.title LIKE :q1 OR l.description LIKE :q2)'; $params['q1'] = $params['q2'] = '%' . mb_substr($filters['q'], 0, 200) . '%'; }
    $sql = ' FROM logs l LEFT JOIN categories c ON c.id=l.category_id WHERE ' . implode(' AND ', $where);
    $count = (int)query('SELECT COUNT(*)' . $sql, $params)->fetchColumn();
    $page = max(1, min(max(1, (int)ceil($count/50)), (int)($_GET['page'] ?? 1)));
    $limit = $all ? '' : ' LIMIT 50 OFFSET :offset';
    $rows = query('SELECT l.*,c.name AS category_name,c.color AS category_color' . $sql . ' ORDER BY l.log_date DESC,l.id DESC' . $limit, $all ? $params : [...$params, 'offset' => ($page-1)*50])->fetchAll();
    return [$rows, $count, $page, $filters];
}
function render_filters(array $filters, bool $admin = false): void
{
    echo '<form class="filters panel" method="get"><label class="search"><span>⌕</span><input name="q" placeholder="Cari judul atau deskripsi…" aria-label="Cari catatan" value="' . e($filters['q']) . '"></label><div class="filter-fields"><select name="status" aria-label="Filter status"><option value="">Semua status</option>';
    foreach (statuses() as $key => $label) echo '<option value="' . $key . '"' . ($filters['status'] === $key ? ' selected' : '') . '>' . e($label) . '</option>';
    echo '</select><select name="category" aria-label="Filter proyek"><option value="">Semua proyek</option>';
    foreach (categories() as $category) echo '<option value="' . (int)$category['id'] . '"' . ($filters['category'] === (string)$category['id'] ? ' selected' : '') . '>' . e($category['name']) . '</option>';
    echo '</select><label class="date-filter">Dari<input type="date" name="from" aria-label="Dari tanggal" value="' . e($filters['from']) . '"></label><label class="date-filter">Sampai<input type="date" name="to" aria-label="Sampai tanggal" value="' . e($filters['to']) . '"></label><button class="btn btn-primary" type="submit">Terapkan</button><a class="reset" href="' . e(url($admin ? 'admin/' : '')) . '">Reset</a></div></form>';
}
function pagination(int $count, int $page): void
{
    if ($count <= 50) return;
    echo '<nav class="pagination" aria-label="Paginasi">';
    foreach ([$page-1 => '← Sebelumnya', $page+1 => 'Berikutnya →'] as $target => $label) if ($target >= 1 && $target <= ceil($count/50)) echo '<a class="btn" href="?' . e(http_build_query([...$_GET, 'page' => $target])) . '">' . e($label) . '</a>';
    echo '<span>Halaman ' . $page . ' / ' . (int)ceil($count/50) . '</span></nav>';
}
function render_card(array $log, bool $admin = false): void
{
    echo '<article class="log-card panel"><div class="card-main"><div class="card-meta">' . badge($log['status']) . '<span class="category">' . e($log['category_name'] ?: 'Tanpa proyek') . '</span>' . ($admin && $log['visibility'] === 'private' ? '<span class="private">♧ Privat</span>' : '') . '</div><h3><a href="' . e(url('log.php?id=' . (int)$log['id'])) . '">' . e($log['title']) . '</a></h3><p class="excerpt">' . e(mb_strimwidth($log['description'] ?? '', 0, 200, '…')) . '</p>';
    if ($log['status'] === 'blocked') echo '<p class="blocker">Kendala: ' . e(mb_strimwidth($log['blocker_note'], 0, 180, '…')) . '</p>';
    echo '<div class="progress-line"><div class="progress-track"><div class="progress-fill ' . e($log['status']) . '" style="width:' . (int)$log['progress'] . '%"></div></div><span>' . (int)$log['progress'] . '%</span></div></div>';
    if ($admin) {
        echo '<div class="card-admin"><form class="quick-form" method="post" action="' . e(url('admin/quick_status.php')) . '">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$log['id'] . '"><select name="status" aria-label="Ubah status">';
        foreach (statuses() as $key => $label) echo '<option value="' . e($key) . '"' . ($log['status'] === $key ? ' selected' : '') . '>' . e($label) . '</option>';
        echo '</select><input class="quick-progress" type="number" name="progress" min="0" max="100" value="' . (int)$log['progress'] . '" aria-label="Progres persen"><input name="blocker_note" placeholder="Catatan kendala" value="' . e($log['blocker_note']) . '" aria-label="Catatan kendala"><button class="btn" type="submit">Simpan</button></form><div class="row-actions"><a href="' . e(url('admin/form.php?id=' . (int)$log['id'])) . '">Edit catatan ↗</a><form method="post" action="' . e(url('admin/delete.php')) . '" data-confirm="Hapus catatan ini beserta riwayatnya?">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$log['id'] . '"><button class="link danger">Hapus</button></form></div></div>';
    } else echo '<a class="card-arrow" aria-label="Detail ' . e($log['title']) . '" href="' . e(url('log.php?id=' . (int)$log['id'])) . '">↗</a>';
    echo '</article>';
}
function page_start(string $title, string $active = ''): void
{
    $owner = config()['owner'];
    echo '<!doctype html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="Jurnal kerja Benny Dwiyanto — catatan progres dan aktivitas pekerjaan."><title>' . e($title) . ' · Worklog</title><link rel="icon" href="' . e(url('assets/favicon.svg')) . '" type="image/svg+xml"><link rel="stylesheet" href="' . e(url('assets/app.css')) . '"><script src="' . e(url('assets/app.js')) . '" defer></script></head><body><aside class="sidebar"><a class="brand" href="' . e(url()) . '"><span class="brand-icon">w<span>●</span></span>worklog<span class="brand-period">.</span></a><div class="workspace-label">RUANG KERJA PERSONAL</div><nav class="main-nav"><a class="' . ($active === 'public' ? 'active' : '') . '" href="' . e(url()) . '"><span>▦</span> Jurnal publik</a>';
    if (is_admin()) echo '<a class="' . ($active === 'admin' ? 'active' : '') . '" href="' . e(url('admin/')) . '"><span>▤</span> Kelola catatan</a><a class="' . ($active === 'categories' ? 'active' : '') . '" href="' . e(url('admin/categories.php')) . '"><span>◇</span> Proyek & kategori</a>';
    echo '</nav><div class="sidebar-note"><span class="signal"></span> Terhubung secara lokal<p>Catatan kecil hari ini.<br>Progres besar esok hari.</p></div><div class="profile"><div class="avatar">BD</div><div><strong>' . e($owner['name']) . '</strong><small>' . e($owner['role']) . ' · ' . e($owner['employee_id']) . '</small></div></div></aside><div class="shell"><header class="topbar"><span>Workspace <span class="slash">/</span> <strong>' . e($title) . '</strong></span><div class="top-actions"><span class="local-pill"><span class="signal"></span> Jaringan lokal</span>';
    if (is_admin()) echo '<form method="post" action="' . e(url('logout.php')) . '">' . csrf_field() . '<button class="btn btn-small">Keluar ↗</button></form>';
    else echo '<a class="btn btn-small" href="' . e(url('login.php')) . '">Masuk pemilik ↗</a>';
    echo '</div></header><main>';
    if (isset($_SESSION['flash'])) { echo '<div class="notice" role="status">' . e($_SESSION['flash']) . '</div>'; unset($_SESSION['flash']); }
}
function page_end(): void { echo '</main><footer>© ' . date('Y') . ' ' . e(config()['owner']['name']) . '<span>Dibuat untuk mencatat setiap langkah.</span></footer></div></body></html>'; }
