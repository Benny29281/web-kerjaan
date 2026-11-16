<?php
require __DIR__ . '/../app/bootstrap.php';
[$logs, $count, $page, $filters] = filtered_logs();
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$stats = array_column(query("SELECT status, COUNT(*) AS total FROM logs WHERE visibility='public' AND log_date BETWEEN ? AND ? GROUP BY status", [$weekStart,$weekEnd])->fetchAll(), 'total', 'status');
$ongoing = query("SELECT l.*,c.name AS category_name FROM logs l LEFT JOIN categories c ON c.id=l.category_id WHERE l.visibility='public' AND l.status='in_progress' ORDER BY l.updated_at DESC,l.id DESC LIMIT 6")->fetchAll();
$ongoingCount = (int)query("SELECT COUNT(*) FROM logs WHERE visibility='public' AND status='in_progress'")->fetchColumn();
page_start('Jurnal publik', 'public');
?>
<section class="page-heading"><div><div class="eyebrow">SETIAP LANGKAH, TERCATAT.</div><h1>Jurnal kerja<span class="accent">.</span></h1><p>Ruang untuk berbagi pekerjaan, proses, dan progres saya.</p></div><div class="heading-date"><span>HARI INI</span><strong><?= e(date_label(date('Y-m-d'))) ?></strong></div></section>
<section class="intro-banner"><div class="intro-avatar">BD</div><div><div class="intro-label">DI BALIK CATATAN INI</div><h2><?= e(config()['owner']['name']) ?> <span>· <?= e(config()['owner']['role']) ?></span></h2><p>Selamat datang! Ini yang sedang saya kerjakan dan selesaikan.</p></div><div class="banner-decoration" aria-hidden="true">✳</div></section>
<div class="section-line"><h2>Sekilas minggu ini</h2><span><?= e(date_label($weekStart)) ?> – <?= e(date_label($weekEnd)) ?></span></div>
<section class="stats-grid" aria-label="Ringkasan minggu ini">
<?php foreach (['done'=>['Tugas selesai','✓','Satu langkah lebih maju'], 'in_progress'=>['Sedang dikerjakan','↗','Terus bergerak maju'], 'blocked'=>['Terkendala','!','Butuh tindak lanjut']] as $status => [$label,$symbol,$hint]): ?>
<article class="stat panel <?= e($status) ?>"><div class="stat-top"><span><?= e($label) ?></span><span class="stat-icon"><?= e($symbol) ?></span></div><strong><?= (int)($stats[$status] ?? 0) ?><small> tugas</small></strong><p><?= e($hint) ?></p></article>
<?php endforeach; ?>
</section>
<section class="ongoing-section"><div class="section-line"><h2><span class="live-dot"></span> Sedang dikerjakan <span class="count-pill"><?= $ongoingCount ?></span></h2><span>Fokus saat ini</span></div>
<?php if (!$ongoing): ?><div class="empty compact panel"><span>↗</span><p>Belum ada pekerjaan aktif. Progres berikutnya akan tampil di sini.</p></div><?php else: ?><div class="ongoing-grid"><?php foreach ($ongoing as $log): ?><a class="ongoing-card panel" href="<?= e(url('log.php?id=' . $log['id'])) ?>"><div class="card-meta"><span class="category"><?= e($log['category_name'] ?: 'Tanpa proyek') ?></span><span>↗</span></div><h3><?= e($log['title']) ?></h3><div class="progress-line"><div class="progress-track"><div class="progress-fill" style="width:<?= (int)$log['progress'] ?>%"></div></div><span><?= (int)$log['progress'] ?>%</span></div></a><?php endforeach; ?></div><?php endif; ?>
<?php if ($ongoingCount > 6): ?><a class="more-link" href="?status=in_progress">Lihat semua <?= $ongoingCount ?> pekerjaan aktif →</a><?php endif; ?></section>
<section><div class="section-line timeline-title"><h2>Jejak pekerjaan</h2><span><?= $count ?> catatan<?= array_filter($filters) ? ' sesuai filter' : ' publik' ?></span></div>
<?php render_filters($filters); ?>
<?php if (!$logs): ?><div class="empty panel"><div class="empty-icon">▤</div><h3><?= array_filter($filters) ? 'Catatan tidak ditemukan' : 'Cerita kerja dimulai di sini' ?></h3><p><?= array_filter($filters) ? 'Coba kata kunci atau rentang tanggal lain.' : 'Catatan yang dipublikasikan akan muncul di timeline ini.' ?></p><?php if (is_admin()): ?><a class="btn btn-primary" href="<?= e(url('admin/form.php')) ?>">+ Tulis catatan pertama</a><?php endif; ?></div><?php else: ?>
<div class="timeline"><?php $lastDate = null; foreach ($logs as $log): if ($lastDate !== $log['log_date']): if ($lastDate !== null) echo '</div></div>'; $lastDate = $log['log_date']; ?><div class="timeline-group"><div class="timeline-date"><span class="timeline-node"></span><strong><?= $lastDate === date('Y-m-d') ? 'Hari ini' : e(date_label($lastDate)) ?></strong><?php if ($lastDate === date('Y-m-d')): ?><small><?= e(date_label($lastDate)) ?></small><?php endif; ?></div><div class="timeline-cards"><?php endif; render_card($log); endforeach; ?></div></div></div>
<?php endif; pagination($count,$page); ?></section>
<?php page_end(); ?>
