<?php
// Konfigurasi aplikasi LMS
return [
    'app_name'      => 'LMS Sekolah',
    'timezone'      => 'Asia/Jakarta',
    // Lokasi database SQLite (dibuat otomatis saat pertama kali dijalankan)
    'db_path'       => __DIR__ . '/storage/lms.sqlite',
    // Folder penyimpanan file unggahan (tidak bisa diakses langsung dari browser)
    'upload_dir'    => __DIR__ . '/storage/uploads',
    'max_upload_mb' => 20,
    'allowed_ext'   => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt',
                        'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar', 'mp3', 'mp4'],
    // Izinkan siswa mendaftar sendiri dari halaman registrasi
    'allow_register' => true,
];
