<?php
require_once "../config/auth_user.php";
require_once "../config/koneksi.php";

$id_pengguna = $_SESSION['id_pengguna'];

/* Hanya terima POST dengan tombol ajukan_kembali */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (!isset($_POST['ajukan_kembali']) && !isset($_POST['batal_pengembalian']))) {
    header("Location: riwayat.php"); exit;
}

$id_pm           = (int)($_POST['id_peminjaman'] ?? 0);
$catatan_kembali = trim(mysqli_real_escape_string($conn, $_POST['catatan_kembali'] ?? ''));

if (!$id_pm) {
    header("Location: riwayat.php?error=".urlencode("Data peminjaman tidak valid.")); exit;
}

/* ── Verifikasi kepemilikan & status harus 'dipinjam' ── */
$pm = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT id_peminjaman, status, id_pengguna
     FROM peminjaman
     WHERE id_peminjaman=$id_pm
       AND id_pengguna='$id_pengguna'
       AND status='dipinjam'"));

if (!$pm) {
    header("Location: riwayat.php?error=".urlencode("Peminjaman tidak ditemukan atau tidak dapat diajukan pengembaliannya.")); exit;
}

/* ── Simpan pengajuan: update status → menunggu_kembali ── */
$today = date('Y-m-d');

/* Simpan catatan user di field catatan (append) jika ada */
if ($catatan_kembali) {
    mysqli_query($conn,
        "UPDATE peminjaman
         SET status          = 'menunggu_kembali',
             tanggal_kembali = '$today',
             catatan         = CONCAT(IFNULL(catatan,''),
                               IF(catatan IS NULL OR catatan='', '', ' | '),
                               'Catatan Kembali: $catatan_kembali')
         WHERE id_peminjaman = $id_pm");
} else {
    mysqli_query($conn,
        "UPDATE peminjaman
         SET status          = 'menunggu_kembali',
             tanggal_kembali = '$today'
         WHERE id_peminjaman = $id_pm");
}

/* Stok BELUM dikembalikan — stok dikembalikan saat admin konfirmasi */

header("Location: riwayat.php?success=".urlencode(
    "Pengajuan pengembalian berhasil dikirim. Admin akan memverifikasi kondisi barang dan mengkonfirmasi pengembalian."));
exit;

/* ── POST: BATALKAN PENGAJUAN PENGEMBALIAN ── */
if (isset($_POST['batal_pengembalian'])) {

    $id_pm = (int)($_POST['id_peminjaman'] ?? 0);

    if (!$id_pm) {
        header("Location: riwayat.php?error=".urlencode("Data peminjaman tidak valid.")); exit;
    }

    /* Verifikasi kepemilikan & status harus 'menunggu_kembali' */
    $cek = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_peminjaman FROM peminjaman
         WHERE id_peminjaman=$id_pm
           AND id_pengguna='$id_pengguna'
           AND status='menunggu_kembali'"));

    if (!$cek) {
        header("Location: riwayat.php?error=".urlencode("Pengajuan pengembalian tidak ditemukan atau sudah diproses admin.")); exit;
    }

    /* Kembalikan status ke 'dipinjam' & hapus tanggal_kembali */
    mysqli_query($conn,
        "UPDATE peminjaman
         SET status          = 'dipinjam',
             tanggal_kembali = NULL
         WHERE id_peminjaman = $id_pm
           AND id_pengguna   = '$id_pengguna'
           AND status        = 'menunggu_kembali'");

    header("Location: riwayat.php?success=".urlencode("Pengajuan pengembalian berhasil dibatalkan. Status peminjaman kembali aktif."));
    exit;
}
