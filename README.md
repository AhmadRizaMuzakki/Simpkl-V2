# SIMPKL Robotics

Sistem Informasi Monitoring Praktik Kerja Lapangan (PKL) untuk lingkungan akademik Ilmu Komputer Universitas Djuanda. Aplikasi ini berjalan sebagai PHP native di Laragon, dengan MySQL sebagai basis data dan unggahan foto disimpan di server lokal.

## Fitur

- Dashboard monitoring untuk admin dan pembimbing: peserta aktif, kehadiran hari ini, training, dan grafik 7 hari.
- Absensi masuk dan pulang untuk peserta. Keterlambatan dihitung jika jam masuk lewat pukul 08:00.
- Laporan training harian beserta peran peserta dan unggahan dokumentasi.
- Daftar peserta, detail kehadiran, dan rekap training.
- Penilaian sikap mingguan: disiplin, tanggung jawab, komunikasi, kerja sama, dan inisiatif.
- Galeri dokumentasi untuk pembimbing.

Peran yang tersedia: `ADMIN`, `PEMBIMBING`, dan `PESERTA`.

## Menjalankan

1. Nyalakan Apache dan MySQL di Laragon.
2. Sesuaikan koneksi di `config/config.php` bila user atau password MySQL berbeda. Bawaan Laragon adalah user `root` tanpa password.
3. Buka `http://localhost/simpkl/setup.php` sekali. Halaman itu membuat database `simpkl`, tabel, dan akun demo.
4. Masuk lewat `http://localhost/simpkl/login.php`.

Akun demo:

| Peran | Email | Password |
| --- | --- | --- |
| Admin | alhafidz.almagribi409@gmail.com | admin123 |
| Pembimbing | pembimbing@unida.ac.id | pembimbing123 |
| Peserta | peserta1@unida.ac.id | peserta123 |

`peserta2@unida.ac.id` dan `peserta3@unida.ac.id` memakai password `peserta123`.

Foto training disimpan di `uploads/dokumentasi`. Ukuran maksimal 5 MB, format JPG, PNG, WEBP, atau GIF.

## Struktur

- `config/config.php` — nama aplikasi, zona waktu, dan koneksi database.
- `database/schema.sql` — struktur tabel.
- `includes/` — koneksi, autentikasi, dan layout dashboard.
- `assets/` — CSS dan JavaScript tampilan SB Admin.
- Halaman utama: `dashboard.php`, `peserta.php`, `training.php`, `absensi.php`, `penilaian.php`, `dokumentasi.php`.

## Pengembang

Aulia Alhafidz  
Program Studi Ilmu Komputer  
Universitas Djuanda
