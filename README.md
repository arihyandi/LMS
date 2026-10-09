# 📚 LMS Sekolah

Aplikasi **Learning Management System (LMS)** berbasis website untuk sekolah. Dibuat dengan
**PHP native + SQLite** sehingga mudah dipasang di XAMPP, Laragon, atau shared hosting
tanpa perlu membuat database secara manual.

## Fitur

| Peran | Fitur |
|-------|-------|
| **Admin** | Kelola pengguna (tambah, impor massal CSV, reset password, ubah peran, hapus), lihat semua kelas |
| **Guru** | Buat kelas + kode kelas, materi (teks, video YouTube, lampiran file), tugas dengan tenggat & penilaian + umpan balik, kuis pilihan ganda (dinilai otomatis, batas waktu), pengumuman, kelola anggota, rekap nilai (cetak / unduh CSV untuk Excel) |
| **Siswa** | Daftar akun, gabung kelas dengan kode, baca materi & tandai selesai (progress bar), kumpulkan tugas (teks/file), kerjakan kuis dengan timer & lihat pembahasan, lihat nilai |

Tampilan responsif (bisa dibuka di HP) dan berbahasa Indonesia.

## Cara Menjalankan

### Opsi 1 — Server bawaan PHP (paling cepat untuk mencoba)
Butuh PHP 8.1 atau lebih baru dengan ekstensi `pdo_sqlite`.
```bash
cd LMS
php -S localhost:8000 router.php
```
Buka <http://localhost:8000>.

### Opsi 2 — XAMPP / Laragon
1. Salin folder `LMS` ke `C:\xampp\htdocs\` (atau `C:\laragon\www\`).
2. Jalankan Apache.
3. Buka <http://localhost/LMS>.

### Opsi 3 — Hosting
Unggah semua file ke `public_html`. Pastikan folder `storage/` dapat ditulis (permission 775).
File `.htaccess` sudah disertakan untuk memblokir akses langsung ke database dan file unggahan.

## Login Pertama

Database dibuat otomatis saat aplikasi pertama kali dibuka.

| Username | Password |
|----------|----------|
| `admin`  | `admin123` |

Anda **wajib mengganti password** saat login pertama. Selanjutnya:
1. Admin membuat akun guru di menu **Pengguna**.
2. Guru membuat kelas, lalu membagikan **kode kelas** kepada siswa.
3. Siswa mendaftar di halaman *Daftar*, lalu memasukkan kode kelas di dashboard.

### Impor siswa massal
Di menu **Pengguna → Impor Massal**, tempel data dengan format satu baris per siswa:
```
Budi Santoso;budi;rahasia123;siswa
Siti Aminah;siti;rahasia123;siswa
```
Kolom bisa langsung disalin dari Excel (pisahkan dengan titik koma). Siswa wajib mengganti password saat login pertama.

## Pengaturan

Ubah `config.php` untuk:
- `app_name` — nama aplikasi/sekolah
- `max_upload_mb` — batas ukuran file unggahan (sesuaikan juga `upload_max_filesize` dan `post_max_size` di `php.ini`)
- `allowed_ext` — tipe file yang boleh diunggah
- `allow_register` — `false` jika hanya admin yang boleh membuat akun

## Struktur Folder

```
LMS/
├── config.php            # Pengaturan aplikasi
├── index.php             # Halaman awal (redirect)
├── login.php / register.php / logout.php / profile.php
├── dashboard.php         # Dashboard sesuai peran
├── courses.php           # Daftar kelas, gabung/keluar/hapus kelas
├── course.php            # Detail kelas (tab materi, tugas, kuis, pengumuman, anggota, nilai)
├── course_form.php       # Buat/edit kelas
├── lesson.php / lesson_form.php          # Materi
├── assignment.php / assignment_form.php  # Tugas & penilaian
├── quiz.php / quiz_form.php              # Kuis & bank soal
├── admin_users.php       # Manajemen pengguna (admin)
├── download.php          # Unduh file dengan pengecekan hak akses
├── export_grades.php     # Ekspor nilai ke CSV
├── includes/             # Koneksi DB, fungsi bantu, otentikasi, template
├── assets/               # CSS & JavaScript
└── storage/              # Database SQLite & file unggahan (tidak bisa diakses langsung)
```

## Keamanan

- Password di-hash (`password_hash`), wajib ganti password default
- Proteksi CSRF di semua form, query database memakai *prepared statement*
- Semua output di-escape (anti-XSS), Content-Security-Policy aktif
- Pembatasan percobaan login, regenerasi sesi saat login
- File unggahan disimpan dengan nama acak di luar jangkauan browser, divalidasi ekstensi & ukurannya, dan hanya bisa diunduh oleh anggota kelas yang berhak
- Ekspor CSV dilindungi dari *formula injection* Excel

## Backup

Cukup salin folder `storage/` (berisi `lms.sqlite` dan folder `uploads/`).
