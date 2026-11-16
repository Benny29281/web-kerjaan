<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0);
    try {
        if (($_POST['action'] ?? '')==='delete') query('DELETE FROM categories WHERE id=?',[$id]);
        else {
            $name = trim((string)($_POST['name'] ?? '')); $color = (string)($_POST['color'] ?? '#6366f1');
            if ($name==='' || mb_strlen($name)>80) throw new InvalidArgumentException('Nama proyek wajib diisi, maksimal 80 karakter.');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/',$color)) throw new InvalidArgumentException('Warna tidak valid.');
            if ($id) query('UPDATE categories SET name=?,color=? WHERE id=?',[$name,$color,$id]);
            else query('INSERT INTO categories (name,color) VALUES (?,?)',[$name,$color]);
        }
        flash('Proyek berhasil diperbarui.'); redirect('admin/categories.php');
    } catch (InvalidArgumentException $e) { $error=$e->getMessage(); }
    catch (PDOException $e) { if ($e->getCode()==='23000') $error='Nama proyek sudah digunakan.'; else throw $e; }
}
page_start('Proyek & kategori','categories');
?>
<section class="page-heading"><div><div class="eyebrow">RAPIKAN RUANG KERJA</div><h1>Proyek & kategori<span class="accent">.</span></h1><p>Kelompokkan pekerjaan agar lebih mudah ditemukan.</p></div></section><?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<div class="categories-layout"><form class="panel detail-body category-create" method="post"><?= csrf_field() ?><h2>Proyek baru</h2><label>Nama proyek<input name="name" required maxlength="80" placeholder="Contoh: Pengembangan ERP"></label><label>Warna<input type="color" name="color" value="#6366f1"></label><button class="btn btn-primary">+ Tambah proyek</button></form><div class="panel category-list"><?php $cats = categories(); foreach ($cats as $category): ?><div class="category-item"><form method="post" class="category-edit"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$category['id'] ?>"><input type="color" name="color" aria-label="Warna proyek" value="<?= e($category['color']) ?>"><input name="name" aria-label="Nama proyek" maxlength="80" required value="<?= e($category['name']) ?>"><button class="btn btn-small">Simpan</button></form><form method="post" data-confirm="Hapus proyek ini? Catatannya tetap tersimpan tanpa proyek."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>"><button class="link danger">Hapus</button></form></div><?php endforeach; if (!$cats): ?><div class="empty compact"><p>Belum ada proyek. Tambahkan proyek pertama di sini.</p></div><?php endif; ?></div></div>
<?php page_end(); ?>
