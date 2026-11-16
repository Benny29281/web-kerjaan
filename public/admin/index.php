<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();
[$logs,$count,$page,$filters] = filtered_logs(true);
page_start('Kelola catatan','admin');
?>
<section class="page-heading"><div><div class="eyebrow">RUANG KERJA BENNY</div><h1>Setiap progres berarti<span class="accent">.</span></h1><p>Catat pekerjaan, perbarui progres, dan bagikan hasilnya.</p></div><a class="btn btn-primary" href="<?= e(url('admin/form.php')) ?>">+ Catatan baru</a></section><div class="section-line"><h2>Semua catatan <span class="count-pill"><?= $count ?></span></h2><div><a class="btn btn-small" href="<?= e(url('admin/password.php')) ?>">Ubah kata sandi</a> <a class="btn btn-small" href="<?= e(url('admin/export.php') . '?' . http_build_query($_GET)) ?>">↓ Ekspor CSV</a></div></div>
<?php render_filters($filters,true); ?>
<div class="admin-list"><?php foreach ($logs as $log): ?><div class="admin-date"><?= e(date_label($log['log_date'])) ?></div><?php render_card($log,true); endforeach; if (!$logs): ?><div class="empty panel"><div class="empty-icon">✎</div><h2>Mulai dari satu catatan</h2><p>Catat pekerjaan hari ini dan lihat progresnya bertumbuh.</p><a class="btn btn-primary" href="<?= e(url('admin/form.php')) ?>">+ Catatan baru</a></div><?php endif; ?></div>
<?php pagination($count,$page); page_end(); ?>
