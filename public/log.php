<?php
require __DIR__ . '/../app/bootstrap.php';
$log = find_log((int)($_GET['id'] ?? 0), is_admin());
if (!$log) {
    http_response_code(404); page_start('Catatan tidak ditemukan');
    echo '<div class="empty panel"><h1>Catatan tidak ditemukan</h1><p>Catatan ini tidak tersedia untuk dilihat.</p><a class="btn" href="' . e(url()) . '">Kembali ke jurnal</a></div>'; page_end(); exit;
}
$history = query('SELECT * FROM log_history WHERE log_id=? ORDER BY changed_at DESC,id DESC', [(int)$log['id']])->fetchAll();
page_start('Detail catatan', is_admin() ? 'admin' : 'public');
?>
<a class="back-link" href="<?= e(url(is_admin() ? 'admin/' : '')) ?>">← Kembali ke jurnal</a><div class="detail-heading"><div class="card-meta"><?= badge($log['status']) ?><span class="category"><?= e($log['category_name'] ?: 'Tanpa proyek') ?></span><?php if ($log['visibility']==='private'): ?><span class="private">Privat</span><?php endif; ?></div><h1><?= e($log['title']) ?></h1><p class="muted"><?= e(date_label($log['log_date'])) ?> · <?= e(config()['owner']['name']) ?></p></div>
<div class="detail-grid"><article class="panel detail-body"><h2>Catatan pekerjaan</h2><div class="prose"><?= nl2br(e($log['description'] ?: 'Belum ada deskripsi tambahan.')) ?></div><?php if ($log['status']==='blocked'): ?><div class="blocker"><strong>Kendala</strong><p><?= nl2br(e($log['blocker_note'])) ?></p></div><?php endif; ?><?php if ($log['links']): ?><h2>Tautan terkait</h2><ul class="links-list"><?php foreach (preg_split('/\R/',$log['links']) as $link): if (trim($link)==='') continue; ?><li><a href="<?= e(trim($link)) ?>" target="_blank" rel="noopener noreferrer"><?= e(trim($link)) ?> ↗</a></li><?php endforeach; ?></ul><?php endif; ?></article><aside><div class="panel detail-body"><h2>Progres pekerjaan</h2><div class="big-progress"><?= (int)$log['progress'] ?><small>%</small></div><div class="progress-track"><div class="progress-fill <?= e($log['status']) ?>" style="width:<?= (int)$log['progress'] ?>%"></div></div><?php if (is_admin()): ?><a class="btn full-width" href="<?= e(url('admin/form.php?id=' . $log['id'])) ?>">Edit catatan ↗</a><?php endif; ?></div><div class="panel detail-body history"><h2>Riwayat progres</h2><?php foreach ($history as $item): ?><div class="history-item"><?= badge($item['new_status']) ?><strong><?= (int)$item['progress'] ?>%</strong><small><?= e(date_label($item['changed_at'])) ?> · <?= e(date('H:i',strtotime($item['changed_at']))) ?></small></div><?php endforeach; ?></div></aside></div>
<?php page_end(); ?>
