# PRD: Web Jurnal Kerja (Work Log)

| | |
|---|---|
| **Versi** | 2.0 (PHP + MySQL + Laragon, jaringan lokal) |
| **Tanggal** | 16 November 2026 |
| **Pemilik** | Benny Dwiyanto |
| **Jabatan** | IT Developer |
| **ID Karyawan** | ipt2026006 |
| **Status** | Siap dikerjakan |

---

## 1. Latar Belakang

Pekerjaan harian di kantor sering tidak terdokumentasi. Atasan dan rekan kerja tidak selalu tahu apa yang sedang dikerjakan, dan pemilik pekerjaan sulit mengingat apa saja yang sudah selesai saat membuat laporan atau evaluasi.

Produk ini adalah web jurnal kerja pribadi yang berjalan di server lokal (Laragon) dan bisa dilihat oleh siapa saja yang terhubung ke WiFi/jaringan kantor yang sama.

## 2. Tujuan

1. Mencatat pekerjaan harian dengan cepat (kurang dari 1 menit per catatan).
2. Memberi visibilitas kepada atasan dan rekan kerja tentang apa yang sedang dikerjakan.
3. Menjadi bahan rekap untuk laporan mingguan/bulanan.

**Di luar cakupan (v1):** akses dari internet, multi-user penuh, komentar, notifikasi, aplikasi mobile native.

## 3. Pengguna

| Peran | Deskripsi | Hak akses |
|---|---|---|
| **Pemilik (Benny Dwiyanto)** | Pembuat dan pengelola catatan | Login; tambah, ubah, hapus catatan |
| **Penonton (bos, teman)** | Siapa pun di jaringan yang sama | Hanya melihat, tanpa login |

## 4. Lingkungan dan Teknologi

| Komponen | Pilihan |
|---|---|
| Server lokal | **Laragon** (Apache + PHP 8.x + MySQL) |
| Bahasa backend | **PHP native** (struktur sederhana, tanpa framework) |
| Database | **MySQL**, dikelola lewat HeidiSQL (bawaan Laragon) atau phpMyAdmin |
| Akses database | **PDO + prepared statement** (wajib, anti SQL injection) |
| Frontend | HTML + **Tailwind CSS** (CDN untuk awal; unduh file lokal jika jaringan kantor tanpa internet) + sedikit JavaScript |
| Version control | **Git + GitHub** (hanya untuk menyimpan kode, bukan hosting) |
| Akses penonton | `http://<IP-komputer>/worklog` lewat WiFi/LAN yang sama |

## 5. Kebutuhan Fungsional

### 5.1 Halaman Publik

| ID | Fitur | Prioritas |
|---|---|---|
| F-01 | **Ringkasan**: jumlah tugas selesai minggu ini, sedang dikerjakan, terkendala | Must |
| F-02 | **Bagian "Sedang Dikerjakan"** di bagian atas | Must |
| F-03 | **Timeline catatan** terbaru di atas, dikelompokkan per tanggal | Must |
| F-04 | **Filter** status, kategori/proyek, rentang tanggal | Must |
| F-05 | **Pencarian** pada judul dan deskripsi | Should |
| F-06 | **Halaman detail** catatan (deskripsi, tautan, riwayat status) | Should |
| F-07 | Tampilan responsif (nyaman di HP) | Must |

### 5.2 Halaman Admin

| ID | Fitur | Prioritas |
|---|---|---|
| F-10 | **Login/logout** pemilik | Must |
| F-11 | **Tambah catatan** (form singkat) | Must |
| F-12 | **Edit dan hapus** catatan | Must |
| F-13 | **Ubah status dan progres cepat** dari daftar | Must |
| F-14 | **Visibilitas** per catatan: publik / privat | Should |
| F-15 | **Kelola kategori/proyek** | Should |
| F-16 | **Ekspor rekap** ke CSV | Could |

### 5.3 Aturan Bisnis

- Status `done` otomatis mengisi progres 100%.
- Status `blocked` wajib mengisi `blocker_note`.
- Catatan `private` tidak boleh muncul di halaman publik, pencarian, maupun halaman detail publik.
- Setiap perubahan status dicatat di tabel riwayat.
- Tanggal default form adalah hari ini.

## 6. Skema Database (MySQL)

Nama database: `worklog`. Charset `utf8mb4`.

```sql
CREATE DATABASE worklog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE worklog;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id   VARCHAR(30)  NOT NULL UNIQUE,      -- contoh: ipt2026006
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NULL,
  password_hash VARCHAR(255) NOT NULL,             -- hasil password_hash()
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL UNIQUE,
  color      VARCHAR(7)  NOT NULL DEFAULT '#6366f1',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE logs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  category_id   INT UNSIGNED NULL,
  title         VARCHAR(120) NOT NULL,
  description   TEXT NULL,
  log_date      DATE NOT NULL,
  status        ENUM('planned','in_progress','done','blocked') NOT NULL DEFAULT 'planned',
  progress      TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- 0-100
  blocker_note  TEXT NULL,
  links         TEXT NULL,                             -- satu URL per baris
  visibility    ENUM('public','private') NOT NULL DEFAULT 'public',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_date (log_date),
  INDEX idx_status (status),
  INDEX idx_visibility (visibility)
);

CREATE TABLE log_history (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_id      INT UNSIGNED NOT NULL,
  old_status  VARCHAR(20) NULL,
  new_status  VARCHAR(20) NOT NULL,
  progress    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  changed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (log_id) REFERENCES logs(id) ON DELETE CASCADE
);
```

**Membuat akun pemilik** (jalankan sekali dari skrip PHP `setup.php`, lalu hapus file tersebut):

```php
<?php
$hash = password_hash('GANTI_PASSWORD_KUAT', PASSWORD_DEFAULT);
// INSERT INTO users (employee_id, name, password_hash) VALUES ('ipt2026006', 'Benny Dwiyanto', :hash)
```

## 7. Struktur Folder

```
worklog/
├── public/                  # satu-satunya folder yang diakses browser
│   ├── index.php            # dashboard publik
│   ├── log.php              # detail catatan (?id=)
│   ├── login.php
│   ├── logout.php
│   ├── assets/              # css, js, gambar
│   └── admin/
│       ├── index.php        # daftar catatan + aksi cepat
│       ├── form.php         # tambah / edit
│       ├── save.php         # proses simpan (POST)
│       ├── delete.php       # proses hapus (POST)
│       ├── quick_status.php # ubah status/progres cepat (POST)
│       ├── categories.php
│       └── export.php       # CSV
├── app/
│   ├── config.php           # kredensial DB (jangan di-commit)
│   ├── db.php               # koneksi PDO
│   ├── auth.php             # cek login, session, CSRF
│   └── functions.php        # helper (escape, format tanggal, dll.)
├── database/
│   └── schema.sql
├── .gitignore               # abaikan app/config.php
└── README.md
```

> Opsional: arahkan document root virtual host Laragon ke folder `public/` agar folder `app/` tidak bisa diakses langsung dari browser.

## 8. Rute dan Halaman

| URL | Akses | Fungsi |
|---|---|---|
| `/` | Publik | Ringkasan, sedang dikerjakan, timeline, filter, pencarian |
| `/log.php?id=` | Publik | Detail catatan (hanya yang `public`) |
| `/login.php` | Publik | Form login pemilik |
| `/admin/` | Login | Daftar semua catatan + ubah status cepat |
| `/admin/form.php` | Login | Tambah/edit catatan |
| `/admin/categories.php` | Login | Kelola kategori |
| `/admin/export.php` | Login | Unduh CSV |

## 9. Query Penting (acuan)

```sql
-- Ringkasan minggu ini (publik)
SELECT status, COUNT(*) AS total
FROM logs
WHERE visibility = 'public'
  AND YEARWEEK(log_date, 1) = YEARWEEK(CURDATE(), 1)
GROUP BY status;

-- Sedang dikerjakan (publik)
SELECT l.*, c.name AS category_name
FROM logs l LEFT JOIN categories c ON c.id = l.category_id
WHERE l.visibility = 'public' AND l.status = 'in_progress'
ORDER BY l.updated_at DESC;

-- Timeline dengan filter (gunakan placeholder PDO, jangan konkatenasi string)
SELECT l.*, c.name AS category_name
FROM logs l LEFT JOIN categories c ON c.id = l.category_id
WHERE l.visibility = 'public'
  AND (:status IS NULL OR l.status = :status)
  AND (:category IS NULL OR l.category_id = :category)
  AND l.log_date BETWEEN :from AND :to
  AND (:q IS NULL OR l.title LIKE :q OR l.description LIKE :q)
ORDER BY l.log_date DESC, l.id DESC
LIMIT 50 OFFSET :offset;
```

## 10. Kebutuhan Non-Fungsional

| Aspek | Target |
|---|---|
| **Performa** | Halaman termuat < 1 detik di jaringan lokal; paginasi 50 catatan per halaman |
| **Keamanan** | Lihat Bagian 11 |
| **Kompatibilitas** | Chrome, Safari, Edge, Firefox terbaru; layar 360px ke atas |
| **Ketersediaan** | Aktif selama komputer server menyala dan terhubung ke jaringan |
| **Backup** | Ekspor database (`mysqldump`/HeidiSQL) minimal mingguan; kode di GitHub |

## 11. Keamanan (Wajib)

- **Semua query memakai PDO prepared statement.** Tidak ada input yang digabung langsung ke SQL.
- **Escape semua output** dengan `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')` untuk mencegah XSS.
- **Password** disimpan dengan `password_hash()` dan dicek dengan `password_verify()`.
- **Session login**: `session_regenerate_id(true)` setelah login; cookie `HttpOnly` dan `SameSite=Lax`.
- **CSRF token** pada semua form POST (tambah, edit, hapus, ubah status).
- **Hapus dan ubah data hanya lewat POST**, bukan GET.
- **Batasi percobaan login** (misalnya 5 kali gagal, tunggu 5 menit).
- **Validasi input di server**: panjang judul, nilai status dari daftar yang diizinkan, progres 0-100, format URL.
- **User MySQL khusus aplikasi** (hak akses hanya ke database `worklog`); jangan pakai `root` di kode.
- **Ganti password root MySQL Laragon** (default kosong).
- **Jangan di-commit ke GitHub**: `app/config.php` dan file backup SQL.
- **Jangan buka ke internet**: tidak memakai fitur Share (ngrok/cloudflared) dan tidak melakukan port-forward.
- Halaman publik tidak boleh menampilkan catatan `private` di bagian mana pun, termasuk detail dan pencarian.

## 12. Cara Menjalankan dan Membagikan (Laragon)

1. Letakkan project di `C:\laragon\www\worklog`.
2. Jalankan Laragon, klik **Start All** (Apache + MySQL).
3. Buka HeidiSQL, jalankan `database/schema.sql`.
4. Buat user MySQL khusus aplikasi dan isi `app/config.php`.
5. Jalankan `setup.php` sekali untuk membuat akun pemilik, lalu hapus file itu.
6. Cek IP komputer: buka CMD, ketik `ipconfig`, lihat **IPv4 Address** (contoh `192.168.1.25`).
7. Izinkan **Apache** di Windows Defender Firewall untuk jaringan **Private** (port 80).
8. Bagikan ke rekan: `http://192.168.1.25/worklog` (atau `/worklog/public` bila document root belum diatur).
9. Agar selalu aktif: Preferences Laragon → aktifkan *Run Laragon when Windows starts* dan *Start all automatically*.

**Catatan:**
- `worklog.test` (alamat otomatis Laragon) hanya jalan di komputer Anda; orang lain harus memakai alamat IP.
- Minta IT kantor memberi **IP statis** agar link tidak berubah.
- Jika memakai phpMyAdmin, batasi hanya dari `localhost`.
- Beri tahu atau minta persetujuan IT kantor karena server dijalankan di jaringan kantor.

## 13. Rencana Pengerjaan

| Tahap | Pekerjaan | Estimasi |
|---|---|---|
| **1. Fondasi** | Setup Laragon, repo Git, skema DB, koneksi PDO, login/logout | 1 hari |
| **2. CRUD admin** | Form tambah/edit, daftar, hapus, ubah status cepat, riwayat status | 2 hari |
| **3. Halaman publik** | Ringkasan, sedang dikerjakan, timeline, filter, responsif | 2 hari |
| **4. Tambahan** | Pencarian, detail, kategori, visibilitas, ekspor CSV | 1-2 hari |
| **5. Pengamanan dan rilis** | Cek checklist keamanan, firewall, IP statis, uji dari HP | 1 hari |

## 14. Kriteria Selesai (MVP)

- [ ] Pemilik bisa login dan menambah, mengubah, menghapus catatan.
- [ ] Status dan progres bisa diubah cepat dari daftar.
- [ ] Halaman publik menampilkan ringkasan, "Sedang Dikerjakan", dan timeline.
- [ ] Filter status, kategori, dan tanggal berfungsi.
- [ ] Catatan privat tidak terlihat di halaman publik.
- [ ] Halaman terbuka dari HP rekan di WiFi yang sama lewat alamat IP.
- [ ] Semua item keamanan di Bagian 11 terpenuhi.
- [ ] Backup database pertama sudah dibuat.

## 15. Metrik Keberhasilan

- Anda mencatat minimal 4 hari per minggu selama 1 bulan pertama.
- Atasan atau rekan membuka halaman minimal 1 kali per minggu.
- Waktu menyusun laporan mingguan berkurang menjadi di bawah 10 menit.

## 16. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Komputer server mati atau IP berubah | Web tidak bisa diakses | IP statis, auto-start Laragon, pakai PC kantor yang menyala terus |
| Malas mencatat karena form ribet | Data tidak terisi | Hanya judul dan status yang wajib; ubah status cepat dari daftar |
| Informasi sensitif terlihat orang lain | Pelanggaran kebijakan kantor | Visibilitas privat per catatan; cek kebijakan kantor |
| Data hilang | Riwayat kerja hilang | Backup database mingguan; ekspor CSV |
| Celah keamanan (SQL injection/XSS) | Data bocor atau rusak | Ikuti Bagian 11 tanpa pengecualian |

## 17. Pengembangan Berikutnya (Opsional)

- Rekan kerja ikut menambah catatan (multi-user dengan peran).
- Komentar atau reaksi dari atasan.
- Lampiran file/screenshot.
- Laporan mingguan otomatis (PDF) dan notifikasi.
- Akses di luar kantor lewat VPN atau hosting (butuh perubahan keamanan).
