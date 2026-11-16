# Worklog — Jurnal Kerja Benny

Aplikasi PHP native + MySQL untuk jurnal kerja di jaringan kantor, sesuai `prd (6).md`. Dashboard publik tanpa login; pemilik dapat mengelola catatan, status/progres, visibilitas, proyek, riwayat, dan ekspor CSV.

## Menjalankan di Laragon

1. Klik **Start All** di Laragon. PHP 8.1+ dengan `pdo_mysql` dan `mbstring`, serta MySQL 8 diperlukan.
2. Buka terminal di folder proyek. Jalankan setup sekali (hanya lewat CLI):

   ```powershell
   '' | & 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' scripts/setup.php
   ```

   Setup memakai akun administrasi MySQL lokal (`root` tanpa password sebagai default Laragon) **hanya untuk instalasi**. Setup menolak menimpa database atau user yang sudah ada. Aplikasi memakai user `worklog_app` dengan hak SELECT, INSERT, UPDATE, DELETE saja. Kredensial acak disimpan di `app/config.php`, diabaikan Git.

   Jika MySQL sudah memiliki password, gunakan PowerShell berikut agar password tidak muncul di riwayat perintah:

   ```powershell
   $securePassword = Read-Host 'Password administrator MySQL' -AsSecureString
   $dbCredential = [System.Net.NetworkCredential]::new('', $securePassword)
   @{admin_user='root'; admin_password=$dbCredential.Password} | ConvertTo-Json -Compress | & 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' scripts/setup.php
   Remove-Variable securePassword,dbCredential
   ```

3. Buka **http://localhost/web%20kerjaan/public/**. Bila memakai virtual host, arahkan DocumentRoot ke `C:/laragon/www/web kerjaan/public`.
4. Buka `storage/initial-access.txt` secara lokal untuk ID dan kata sandi awal. Masuk melalui tombol **Masuk pemilik**. Ganti kata sandi dari panel admin; file akses awal otomatis dihapus setelah berhasil.
5. Buat catatan pertama. Tidak ada data pekerjaan palsu yang dimasukkan saat setup.

Jika konfigurasi database sudah ada, impor `database/schema.sql` ke database `worklog`, buat user MySQL khusus, salin `app/config.example.php` menjadi `app/config.php`, lalu sesuaikan koneksi. Untuk membuat akun, gunakan `password_hash()` di CLI, lalu INSERT ke tabel users dengan prepared statement. Jangan memakai root untuk koneksi aplikasi.

## Akses dari HP / komputer kantor

- Cari IPv4 komputer dengan `ipconfig`.
- Dari WiFi/LAN yang sama: `http://IP-KOMPUTER/web%20kerjaan/public/`.
- `worklog.test` hanya dapat dipakai di komputer yang memiliki konfigurasi hosts tersebut.
- Izinkan Apache pada profil **Private** Windows Firewall sesuai kebijakan IT. Minta IP tetap dan persetujuan pengelola jaringan kantor. Firewall tidak diubah otomatis oleh aplikasi.
- Jangan mengaktifkan ngrok/cloudflared, port forwarding, atau hosting internet.
- Arahkan DocumentRoot ke `public/` untuk konfigurasi produksi lokal yang disarankan. `.htaccess` pada root dan folder internal juga menolak akses langsung ke kode internal, cadangan, kredensial, dan Git. Pastikan Apache mengizinkan override. Jangan memakai PHP development server dengan root folder proyek; gunakan `-t public`.
- Atur password root MySQL melalui HeidiSQL jika masih kosong. Setup tidak mengubah password administrasi milik instalasi lain.

## Backup dan pemulihan

```powershell
& 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' scripts/backup.php
```

Cadangan lengkap dibuat di `database/backups/*.backup.sql`, termasuk akun dan riwayat. Jalankan minimal mingguan dan salin ke penyimpanan aman di luar PC. Cadangan serta konfigurasi tidak masuk Git. Untuk pemulihan: pilih database worklog di HeidiSQL lalu jalankan file backup; proses pemulihan mengganti tabel yang ada. CSV hanya rekap catatan, bukan backup lengkap. Jangan membagikan CSV berisi catatan privat kepada penonton.

## Implementasi

- UI berbahasa Indonesia, CSS lokal tanpa ketergantungan internet; menggantikan Tailwind CDN agar tetap berjalan di LAN tanpa internet.
- Ringkasan dihitung untuk minggu berjalan (Senin–Minggu, Asia/Jakarta). Bagian fokus menampilkan pekerjaan aktif dari semua tanggal; enam terbaru dan tautan ke seluruh hasil.
- Filter judul/deskripsi, status, proyek, tanggal, dan paginasi 50 catatan. Filter juga berlaku pada ekspor CSV admin.
- Progres selesai dipaksa 100%; status terkendala memerlukan alasan. Riwayat merekam status serta progres, disimpan dalam transaksi.
- Semua mutasi HTTP menggunakan POST + CSRF; session HttpOnly/SameSite=Lax; query PDO native prepared statement; output HTML di-escape; URL dibatasi HTTP(S). CSV melindungi sel dari formula spreadsheet.
- Login dibatasi lima kegagalan per IP dalam jendela lima menit, tersimpan di MySQL sehingga sesi baru tidak mereset pembatasan. Pengguna di IP yang sama berbagi batas percobaan.
- Kategori yang dihapus tidak menghapus catatan. Catatan privat hanya terlihat di admin dan detail setelah login.

## Pengujian

```powershell
Get-ChildItem app,public,scripts,tests -Filter *.php -Recurse | ForEach-Object { & 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' -l $_.FullName }
& 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' tests/integration.php
& 'C:/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe' tests/http.php
```

Tes membuat akun dan catatan sementara milik pengujian, lalu membersihkannya dalam blok `finally`: validasi, aturan status, riwayat, filter, pembatasan visibilitas, login, CSRF, CRUD, ekspor, dan akses file internal. Jalankan tes di lingkungan pengembangan. Pemeriksaan dari HP di jaringan kantor tetap perlu dilakukan langsung di jaringan tersebut.
