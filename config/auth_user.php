<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cegah browser menyimpan cache halaman terproteksi
// Ini memastikan tombol Back browser tidak menampilkan halaman setelah logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// Cek session: harus login dan bukan admin
if (!isset($_SESSION['id_pengguna']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Admin tidak boleh akses halaman user
if ($_SESSION['role'] === 'admin') {
    header("Location: ../admin/dashboard.php");
    exit;
}

// Akun harus berstatus aktif
if (($_SESSION['status'] ?? '') !== 'aktif') {
    session_destroy();
    header("Location: ../auth/login.php?msg=" . urlencode("Akun Anda belum diaktifkan."));
    exit;
}
