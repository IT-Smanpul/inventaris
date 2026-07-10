<?php
session_start();
require_once "../config/koneksi.php";
require_once "../config/auth_admin.php";

$msg_success = '';
$msg_error   = '';

/* ══════════════════════════════════════════
   POST: TAMBAH / EDIT / HAPUS / RESET PASSWORD / IMPORT
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ── TAMBAH ── */
    if (isset($_POST['tambah_pengguna'])) {
        $id       = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna']));
        $nama     = trim(mysqli_real_escape_string($conn, $_POST['nama']));
        $email    = trim(mysqli_real_escape_string($conn, $_POST['email']));
        $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
        $password = trim(mysqli_real_escape_string($conn, $_POST['password']));
        $role     = in_array($_POST['role'], ['admin','murid','guru','tendik']) ? $_POST['role'] : 'murid';

        if (empty($id)) {
            $msg_error = "ID Pengguna tidak boleh kosong.";
        } elseif (empty($nama) || empty($username) || empty($password)) {
            $msg_error = "Nama, username, dan password tidak boleh kosong.";
        } else {
            $cek_id = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COUNT(*) as t FROM pengguna WHERE id_pengguna='$id'"));
            if ($cek_id['t'] > 0) {
                $msg_error = "ID Pengguna \"$id\" sudah digunakan.";
            } else {
                $cek = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COUNT(*) as t FROM pengguna WHERE username='$username'"));
                if ($cek['t'] > 0) {
                    $msg_error = "Username \"$username\" sudah digunakan.";
                } else {
                    // Hash password sebelum disimpan
                    $hashed_add = password_hash($password, PASSWORD_BCRYPT);
                    $esc_hashed = mysqli_real_escape_string($conn, $hashed_add);
                    mysqli_query($conn, "
                        INSERT INTO pengguna (id_pengguna, nama, email, username, password, role, status)
                        VALUES ('$id', '$nama', '$email', '$username', '$esc_hashed', '$role', 'aktif')
                    ");
                    header("Location: pengguna.php?success=".urlencode("Pengguna \"$nama\" berhasil ditambahkan."));
                    exit;
                }
            }
        }
    }

    /* ── EDIT ── */
    if (isset($_POST['edit_pengguna'])) {
        $my_id    = $_SESSION['id_pengguna'];
        $id_lama  = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna_lama']));
        $id_baru  = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna']));
        $nama     = trim(mysqli_real_escape_string($conn, $_POST['nama']));
        $email    = trim(mysqli_real_escape_string($conn, $_POST['email']));
        $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
        $role     = in_array($_POST['role'], ['admin','murid','guru','tendik']) ? $_POST['role'] : 'murid';

        // Ambil data target pengguna yang akan diedit
        $target_pg = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT role FROM pengguna WHERE id_pengguna='$id_lama'"));

        if (empty($nama) || empty($username)) {
            $msg_error = "Nama dan username tidak boleh kosong.";
        } elseif ($id_lama === $my_id && $role !== 'admin') {
            // Proteksi: admin tidak bisa menurunkan role akunnya sendiri
            $msg_error = "Anda tidak dapat mengubah role akun Anda sendiri.";
        } elseif ($id_lama !== $my_id && isset($target_pg['role']) && $target_pg['role'] === 'admin') {
            // Cek status — admin nonaktif BOLEH diedit (untuk proses pergantian)
            $target_status = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT status FROM pengguna WHERE id_pengguna='$id_lama'"));
            if (!$target_status || $target_status['status'] === 'aktif') {
                $msg_error = "Akun admin aktif tidak dapat diedit. Nonaktifkan terlebih dahulu.";
            }
        } else {
            $cek = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COUNT(*) as t FROM pengguna WHERE username='$username' AND id_pengguna!='$id_lama'"));
            if ($cek['t'] > 0) {
                $msg_error = "Username \"$username\" sudah digunakan pengguna lain.";
            } else {
                mysqli_query($conn, "
                    UPDATE pengguna
                    SET id_pengguna='$id_baru', nama='$nama', email='$email',
                        username='$username', role='$role'
                    WHERE id_pengguna='$id_lama'
                ");
                // Perbarui session jika admin mengedit akunnya sendiri
                if ($id_lama === $my_id) {
                    $_SESSION['nama'] = $nama;
                    $_SESSION['id_pengguna'] = $id_baru;
                }
                header("Location: pengguna.php?success=".urlencode("Data pengguna berhasil diperbarui."));
                exit;
            }
        }
    }

    /* ── HAPUS ── */
    if (isset($_POST['hapus_pengguna'])) {
        $my_id = $_SESSION['id_pengguna'];
        $id    = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna']));

        // Proteksi: tidak bisa hapus akun sendiri
        if ($id === $my_id) {
            $msg_error = "Anda tidak dapat menghapus akun Anda sendiri.";
        } else {
            // Proteksi: tidak bisa hapus akun admin lain
            $target_pg = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT role FROM pengguna WHERE id_pengguna='$id'"));
            if ($target_pg && $target_pg['role'] === 'admin') {
                $msg_error = "Akun admin tidak dapat dihapus melalui halaman ini.";
            } else {
                // Cek apakah pengguna punya peminjaman aktif
                $cek = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COUNT(*) as t FROM peminjaman WHERE id_pengguna='$id' AND status IN ('menunggu','dipinjam')"));
                if ($cek['t'] > 0) {
                    $msg_error = "Pengguna tidak bisa dihapus karena masih memiliki peminjaman aktif.";
                } else {
                    // Sudah lolos cek aktif — aman hapus semua riwayat peminjaman pengguna ini
                    // 1. Ambil SEMUA id_peminjaman milik pengguna (tidak filter status)
                    $pm_ids_res = mysqli_query($conn,
                        "SELECT id_peminjaman FROM peminjaman WHERE id_pengguna='$id'");
                    while ($pm_row = mysqli_fetch_assoc($pm_ids_res)) {
                        $pm_id = mysqli_real_escape_string($conn, $pm_row['id_peminjaman']);
                        // Hapus detail_peminjaman dulu (child)
                        mysqli_query($conn, "DELETE FROM detail_peminjaman WHERE id_peminjaman='$pm_id'");
                    }
                    // 2. Hapus semua peminjaman milik pengguna
                    mysqli_query($conn, "DELETE FROM peminjaman WHERE id_pengguna='$id'");
                    // 3. Hapus pengguna
                    mysqli_query($conn, "DELETE FROM pengguna WHERE id_pengguna='$id'");
                    header("Location: pengguna.php?success=".urlencode("Pengguna berhasil dihapus."));
                    exit;
                }
            }
        }
    }

    /* ── RESET PASSWORD ── */
    if (isset($_POST['reset_password'])) {
        $my_id        = $_SESSION['id_pengguna'];
        $id           = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna']));
        $new_password = trim($_POST['new_password']);

        // Proteksi: tidak bisa reset password akun admin AKTIF lain
        $target_pg = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT role, status FROM pengguna WHERE id_pengguna='$id'"));
        if ($target_pg && $target_pg['role'] === 'admin' && $id !== $my_id && $target_pg['status'] === 'aktif') {
            $msg_error = "Password akun admin aktif tidak dapat direset. Nonaktifkan terlebih dahulu.";
        } elseif (empty($new_password)) {
            $msg_error = "Password baru tidak boleh kosong.";
        } elseif (strlen($new_password) < 6) {
            $msg_error = "Password minimal 6 karakter.";
        } else {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $esc_h  = mysqli_real_escape_string($conn, $hashed);
            mysqli_query($conn, "UPDATE pengguna SET password='$esc_h' WHERE id_pengguna='$id'");
            header("Location: pengguna.php?success=".urlencode("Password berhasil direset."));
            exit;
        }
    }

    /* ── IMPORT EXCEL ── */
    if (isset($_POST['import_csv'])) {        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $msg_error = "Gagal upload file. Pastikan file Excel dipilih dengan benar.";
        } else {
            $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx','xls','csv'])) {
                $msg_error = "Format file harus Excel (.xlsx / .xls) atau CSV.";
            } else {
                $rows   = [];
                $tmpPath = $_FILES['csv_file']['tmp_name'];

                if ($ext === 'csv') {
                    // Baca CSV biasa
                    $handle = fopen($tmpPath, 'r');
                    $r = 0;
                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        $r++;
                        if ($r === 1) continue; // skip header
                        $rows[] = $row;
                    }
                    fclose($handle);
                } else {
                    // Baca Excel dengan PhpSpreadsheet
                    $autoloads = [
                        __DIR__ . '/../vendor/autoload.php',
                        __DIR__ . '/../../vendor/autoload.php',
                    ];
                    $loaded = false;
                    foreach ($autoloads as $al) {
                        if (file_exists($al)) { require_once $al; $loaded = true; break; }
                    }
                    if (!$loaded) {
                        $msg_error = "PhpSpreadsheet belum terinstall. Jalankan: <code>composer require phpoffice/phpspreadsheet</code> di root project, atau gunakan format CSV.";
                    } else {
                        try {
                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
                            $sheet       = $spreadsheet->getActiveSheet();
                            $firstRow    = true;
                            foreach ($sheet->getRowIterator() as $sheetRow) {
                                if ($firstRow) { $firstRow = false; continue; }
                                $cells = [];
                                foreach ($sheetRow->getCellIterator() as $cell) {
                                    $cells[] = (string)$cell->getValue();
                                }
                                if (count($cells) >= 5) $rows[] = $cells;
                            }
                        } catch (\Exception $e) {
                            $msg_error = "Gagal membaca file Excel: " . htmlspecialchars($e->getMessage());
                        }
                    }
                }

                if (empty($msg_error) && !empty($rows)) {
                    $ok     = 0;
                    $skip   = 0;
                    $errors = [];
                    $rowNum = 1;
                    foreach ($rows as $data) {
                        $rowNum++;
                        $id_p  = trim(mysqli_real_escape_string($conn, $data[0] ?? ''));
                        $nama  = trim(mysqli_real_escape_string($conn, $data[1] ?? ''));
                        $email = trim(mysqli_real_escape_string($conn, $data[2] ?? ''));
                        $uname = trim(mysqli_real_escape_string($conn, $data[3] ?? ''));
                        $pass  = trim(mysqli_real_escape_string($conn, $data[4] ?? ''));
                        $role  = isset($data[5]) && in_array(trim($data[5]), ['admin','murid','guru','tendik'])
                                 ? trim($data[5]) : 'murid';

                        if (empty($id_p)) { $skip++; $errors[] = "Baris $rowNum: ID Pengguna kosong."; continue; }
                        if (empty($nama) || empty($uname) || empty($pass)) { $skip++; continue; }

                        $cek_id = mysqli_fetch_assoc(mysqli_query($conn,
                            "SELECT COUNT(*) as t FROM pengguna WHERE id_pengguna='$id_p'"));
                        if ($cek_id['t'] > 0) { $skip++; $errors[] = "Baris $rowNum: ID '$id_p' sudah ada."; continue; }

                        $cek = mysqli_fetch_assoc(mysqli_query($conn,
                            "SELECT COUNT(*) as t FROM pengguna WHERE username='$uname'"));
                        if ($cek['t'] > 0) { $skip++; $errors[] = "Baris $rowNum: username '$uname' sudah ada."; continue; }

                        mysqli_query($conn, "
                            INSERT INTO pengguna (id_pengguna, nama, email, username, password, role)
                            VALUES ('$id_p', '$nama', '$email', '$uname', '$pass', '$role')
                        ");
                        $ok++;
                    }

                    $pesan = "$ok pengguna berhasil diimport" . ($skip ? ", $skip baris dilewati." : ".");
                    if (!empty($errors)) {
                        $msg_error = implode('<br>', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '<br>...dan lainnya.' : '');
                    }
                    if ($ok > 0) {
                        header("Location: pengguna.php?success=".urlencode($pesan));
                        exit;
                    } else {
                        if (empty($msg_error)) $msg_error = "Tidak ada data yang berhasil diimport. Periksa format file.";
                    }
                } elseif (empty($msg_error)) {
                    $msg_error = "File kosong atau tidak ada data selain header.";
                }
            }
        }
    }

    /* ── APPROVE PENDAFTAR ── */
    if (isset($_POST['approve_pengguna'])) {
        $id = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna']));
        mysqli_query($conn, "UPDATE pengguna SET status='aktif' WHERE id_pengguna='$id'");
        header("Location: pengguna.php?success=".urlencode("Akun berhasil diaktifkan."));
        exit;
    }

    /* ── NONAKTIFKAN ADMIN ── */
    if (isset($_POST['nonaktifkan_admin'])) {
        $my_id  = $_SESSION['id_pengguna'];
        $id     = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna']));
        $konfirm = trim($_POST['konfirm_nama'] ?? '');

        if ($id === $my_id) {
            $msg_error = "Anda tidak dapat menonaktifkan akun Anda sendiri.";
        } else {
            $target = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT nama, role, status FROM pengguna WHERE id_pengguna='$id'"));
            if (!$target || $target['role'] !== 'admin') {
                $msg_error = "Akun tidak ditemukan atau bukan admin.";
            } elseif ($target['status'] !== 'aktif') {
                $msg_error = "Akun ini sudah tidak aktif.";
            } elseif (strtolower(trim($konfirm)) !== strtolower(trim($target['nama']))) {
                $msg_error = "Konfirmasi nama tidak cocok. Ketik nama admin dengan benar.";
            } else {
                mysqli_query($conn, "UPDATE pengguna SET status='nonaktif' WHERE id_pengguna='$id'");
                header("Location: pengguna.php?success=".urlencode("Admin \"".$target['nama']."\" berhasil dinonaktifkan."));
                exit;
            }
        }
    }

    /* ── AKTIFKAN KEMBALI ADMIN ── */
    if (isset($_POST['aktifkan_admin'])) {
        $id = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna']));
        $target = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT nama, role FROM pengguna WHERE id_pengguna='$id'"));
        if ($target && $target['role'] === 'admin') {
            mysqli_query($conn, "UPDATE pengguna SET status='aktif' WHERE id_pengguna='$id'");
            header("Location: pengguna.php?success=".urlencode("Admin \"".$target['nama']."\" berhasil diaktifkan kembali."));
            exit;
        }
    }

    /* ── NONAKTIFKAN PENGGUNA (murid/guru/tendik) ── */
    if (isset($_POST['nonaktifkan_pengguna'])) {
        $id = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna']));
        $target = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT nama, role, status FROM pengguna WHERE id_pengguna='$id'"));
        if (!$target || $target['role'] === 'admin') {
            $msg_error = "Akun tidak ditemukan atau tidak dapat dinonaktifkan.";
        } elseif ($target['status'] === 'nonaktif') {
            $msg_error = "Akun sudah nonaktif.";
        } else {
            mysqli_query($conn, "UPDATE pengguna SET status='nonaktif' WHERE id_pengguna='$id'");
            header("Location: pengguna.php?success=".urlencode("Pengguna \"".$target['nama']."\" berhasil dinonaktifkan."));
            exit;
        }
    }

    /* ── AKTIFKAN KEMBALI PENGGUNA (murid/guru/tendik) ── */
    if (isset($_POST['aktifkan_pengguna'])) {
        $id = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna']));
        $target = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT nama, role FROM pengguna WHERE id_pengguna='$id'"));
        if ($target && $target['role'] !== 'admin') {
            mysqli_query($conn, "UPDATE pengguna SET status='aktif' WHERE id_pengguna='$id'");
            header("Location: pengguna.php?success=".urlencode("Pengguna \"".$target['nama']."\" berhasil diaktifkan."));
            exit;
        }
    }

    /* ── REJECT / TOLAK PENDAFTAR ── */
    if (isset($_POST['reject_pengguna'])) {
        $id = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna']));
        mysqli_query($conn, "DELETE FROM pengguna WHERE id_pengguna='$id' AND status='pending'");
        header("Location: pengguna.php?success=".urlencode("Pendaftaran ditolak dan dihapus."));
        exit;
    }

    /* ── BULK NONAKTIFKAN ── */
    if (isset($_POST['bulk_nonaktifkan'])) {
        $my_id = $_SESSION['id_pengguna'];
        $ids_raw = $_POST['bulk_ids'] ?? [];
        $ok = 0; $skip = 0;
        foreach ($ids_raw as $bid) {
            $bid = trim(mysqli_real_escape_string($conn, $bid));
            if ($bid === $my_id) { $skip++; continue; }
            $tgt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role, status FROM pengguna WHERE id_pengguna='$bid'"));
            if (!$tgt || $tgt['role'] === 'admin' || $tgt['status'] === 'nonaktif') { $skip++; continue; }
            mysqli_query($conn, "UPDATE pengguna SET status='nonaktif' WHERE id_pengguna='$bid'");
            $ok++;
        }
        $msg = "$ok pengguna berhasil dinonaktifkan" . ($skip ? ", $skip dilewati." : ".");
        header("Location: pengguna.php?success=".urlencode($msg));
        exit;
    }

    /* ── BULK AKTIFKAN ── */
    if (isset($_POST['bulk_aktifkan'])) {
        $my_id = $_SESSION['id_pengguna'];
        $ids_raw = $_POST['bulk_ids'] ?? [];
        $ok = 0; $skip = 0;
        foreach ($ids_raw as $bid) {
            $bid = trim(mysqli_real_escape_string($conn, $bid));
            if ($bid === $my_id) { $skip++; continue; }
            $tgt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role, status FROM pengguna WHERE id_pengguna='$bid'"));
            if (!$tgt || $tgt['role'] === 'admin' || $tgt['status'] === 'aktif') { $skip++; continue; }
            mysqli_query($conn, "UPDATE pengguna SET status='aktif' WHERE id_pengguna='$bid'");
            $ok++;
        }
        $msg = "$ok pengguna berhasil diaktifkan" . ($skip ? ", $skip dilewati." : ".");
        header("Location: pengguna.php?success=".urlencode($msg));
        exit;
    }

    /* ── BULK HAPUS ── */
    if (isset($_POST['bulk_hapus'])) {
        $my_id = $_SESSION['id_pengguna'];
        $ids_raw = $_POST['bulk_ids'] ?? [];
        $ok = 0; $skip = 0;
        foreach ($ids_raw as $bid) {
            $bid = trim(mysqli_real_escape_string($conn, $bid));
            if ($bid === $my_id) { $skip++; continue; }
            $tgt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM pengguna WHERE id_pengguna='$bid'"));
            if (!$tgt || $tgt['role'] === 'admin') { $skip++; continue; }
            // Cek pinjaman aktif
            $cek = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COUNT(*) as t FROM peminjaman WHERE id_pengguna='$bid' AND status IN ('menunggu','dipinjam')"));
            if ($cek['t'] > 0) { $skip++; continue; }
            // Hapus riwayat peminjaman
            $pm_ids_res = mysqli_query($conn, "SELECT id_peminjaman FROM peminjaman WHERE id_pengguna='$bid'");
            while ($pm_row = mysqli_fetch_assoc($pm_ids_res)) {
                $pm_id = mysqli_real_escape_string($conn, $pm_row['id_peminjaman']);
                mysqli_query($conn, "DELETE FROM detail_peminjaman WHERE id_peminjaman='$pm_id'");
            }
            mysqli_query($conn, "DELETE FROM peminjaman WHERE id_pengguna='$bid'");
            mysqli_query($conn, "DELETE FROM pengguna WHERE id_pengguna='$bid'");
            $ok++;
        }
        $msg = "$ok pengguna berhasil dihapus" . ($skip ? ", $skip dilewati (admin/pinjaman aktif/akun sendiri)." : ".");
        header("Location: pengguna.php?success=".urlencode($msg));
        exit;
    }
}

if (isset($_GET['success'])) $msg_success = htmlspecialchars($_GET['success']);

/* ── Search & Filter ── */
$search    = isset($_GET['q'])    ? trim(mysqli_real_escape_string($conn, $_GET['q']))    : '';
$filter_role = isset($_GET['role']) && in_array($_GET['role'], ['admin','murid','guru','tendik'])
               ? $_GET['role'] : '';

/* ── Pagination ── */
$valid_per_page = [5,10,20,25,50,100];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'],$valid_per_page) ? (int)$_GET['per_page'] : 5;
$page     = max(1, (int)($_GET['page'] ?? 1));

/* ── WHERE ── */
$where = "WHERE 1=1";
if ($search)      $where .= " AND (p.nama LIKE '%$search%' OR p.username LIKE '%$search%' OR p.email LIKE '%$search%')";
if ($filter_role) $where .= " AND p.role='$filter_role'";

/* ── Count ── */
$total       = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM pengguna p $where"))['t'];
$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

/* ── Fetch pengguna + jumlah peminjaman aktif ── */
$pengguna_q = mysqli_query($conn, "
    SELECT p.*,
           (SELECT COUNT(*) FROM peminjaman pm
            WHERE pm.id_pengguna = p.id_pengguna
              AND pm.status IN ('menunggu','dipinjam')) AS pinjam_aktif
    FROM pengguna p
    $where
    ORDER BY p.nama ASC
    LIMIT $per_page OFFSET $offset
");

$role_cfg = [
    'admin'  => ['Admin',   'badge-danger'],
    'murid'  => ['Murid',   'badge-info'],
    'guru'   => ['Guru',    'badge-success'],
    'tendik' => ['Tendik',  'badge-warning'],
];

/* ── Hitung badge status ── */
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengguna WHERE status='pending'"))['t'];
$pm_menunggu = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM peminjaman WHERE status='menunggu'"))['t'];

$nama_admin = $_SESSION['nama'] ?? 'Admin';

/* ── Hitung per role untuk stats ── */
$stats = [];
foreach (['admin','murid','guru','tendik'] as $r) {
    $stats[$r] = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as t FROM pengguna WHERE role='$r'"))['t'];
}
$stats['total'] = array_sum($stats);

/* ── Hitung akun pending (menunggu persetujuan) ── */
$pending_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM pengguna WHERE status='pending'"))['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengguna — Inventaris SARPRAS</title>
  <link rel="icon" href="../assets/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --blue:      #4A90C4;
      --blue-dark: #2B6FA8;
      --blue-deep: #1B3F6E;
      --green:     #3D9B4A;
      --yellow:    #F5C518;
      --bg:        #F0F7FF;
      --card:      #FFFFFF;
      --text:      #1B2D45;
      --muted:     #6B7C93;
      --border:    #D0E4F5;
      --shadow:    0 2px 14px rgba(27,63,110,.09);
      --shadow-lg: 0 8px 32px rgba(27,63,110,.15);
    }

    html { height: 100%; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg); color: var(--text);
      min-height: 100vh; display: flex; flex-direction: column;
    }

    /* ── NAVBAR ── */
    .navbar {
      position: sticky; top: 0; z-index: 100;
      background: var(--blue-deep); display: flex; align-items: center;
      padding: 0 28px; height: 62px;
      box-shadow: 0 2px 12px rgba(27,63,110,.25);
    }
    .nav-brand { display: flex; align-items: center; gap: 11px; text-decoration: none; flex-shrink: 0; margin-right: 36px; }
    .nav-brand img { width: 38px; height: 38px; object-fit: contain; }
    .nav-brand-text strong { display: block; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 800; color: white; line-height: 1.2; }
    .nav-brand-text span { font-size: 10px; color: rgba(255,255,255,.5); }
    .nav-links { display: flex; align-items: center; gap: 2px; flex: 1; }
    .nav-link { padding: 8px 13px; border-radius: 8px; color: rgba(255,255,255,.65); text-decoration: none; font-size: 13px; font-weight: 500; transition: all .2s; white-space: nowrap; }
    .nav-link:hover { color: white; background: rgba(255,255,255,.1); }
    .nav-link.active { color: white; font-weight: 700; border-bottom: 2px solid var(--yellow); border-radius: 0; padding-bottom: 6px; }
    .nav-link.logout { margin-left: auto; color: rgba(255,255,255,.5); }
    .nav-link.logout:hover { color: #FCA5A5; background: rgba(239,68,68,.15); }

    .nav-badge {
      display: inline-flex; align-items: center; justify-content: center;
      background: var(--red); color: white;
      width: 17px; height: 17px; border-radius: 50%;
      font-size: 10px; font-weight: 800;
      margin-left: 4px; vertical-align: middle;
    }
    .nav-hamburger { display: none; margin-left: auto; background: none; border: none; cursor: pointer; color: white; font-size: 22px; padding: 6px; border-radius: 8px; }
    .nav-hamburger:hover { background: rgba(255,255,255,.1); }
    .nav-mobile-menu { display: none; position: fixed; top: 62px; left: 0; right: 0; background: var(--blue-deep); box-shadow: 0 8px 24px rgba(27,63,110,.3); z-index: 199; flex-direction: column; padding: 10px 16px 20px; border-top: 1px solid rgba(255,255,255,.1); max-height: calc(100vh - 62px); overflow-y: auto; }
    .nav-mobile-menu.open { display: flex; }
    .nav-mobile-menu .nav-link { padding: 13px 14px; border-radius: 10px; font-size: 14px; border-bottom: 1px solid rgba(255,255,255,.06); }
    .nav-mobile-menu .nav-link:last-child { border-bottom: none; }
    .nav-mobile-menu .nav-link.logout { margin-left: 0; margin-top: 6px; }

    /* ── PAGE ── */
    .page-wrapper { max-width:1040px;margin:0 auto;padding:32px 24px 60px;flex:1; }

    /* ── FLASH ── */
    .flash { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 9px; }
    .flash-success { background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; }
    .flash-error   { background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; }

    /* ── HEADER ── */
    .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
    .page-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); }
    .page-sub { font-size: 13px; color: var(--muted); margin-top: 3px; }
    .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }

    /* ── STATS PILLS ── */
    .stats-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
    .mobile-role-dropdown { display: none; }
    .stat-pill {
      display: inline-flex; align-items: center; gap: 8px;
      background: var(--card); border: 1.5px solid var(--border);
      border-radius: 30px; padding: 7px 16px;
      font-size: 12px; font-weight: 700; color: var(--text);
      cursor: pointer; text-decoration: none; transition: all .2s;
    }
    .stat-pill:hover { border-color: var(--blue); background: #EFF6FF; }
    .stat-pill.active { border-color: var(--blue-dark); background: var(--blue-dark); color: white; }
    .stat-pill.active .pill-dot { background: white !important; }
    .pill-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .stat-pill-count { font-size: 11px; opacity: .7; margin-left: 2px; }

    /* ── TOOLBAR ── */
    .toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
    .search-wrap-inner { position: relative; flex: 1; min-width: 200px; }
    .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9CA3AF; font-size: 14px; pointer-events: none; }
    .search-input { width: 100%; padding: 10px 14px 10px 38px; border: 1.5px solid var(--border); border-right: none; border-radius: 9px 0 0 9px; font-size: 13px; font-family: 'DM Sans', sans-serif; color: var(--text); background: var(--card); outline: none; transition: border-color .2s; }
    .search-input:focus { border-color: var(--blue); }
    .btn-search { padding: 10px 16px; background: var(--blue-dark); color: white; border: none; border-radius: 0 9px 9px 0; cursor: pointer; font-size: 14px; transition: background .2s; }
    .btn-search:hover { background: var(--blue-deep); }

    /* ── CARD ── */
    .card { background: var(--card); border-radius: 14px; border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden; }
    .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .card-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 15px; color: var(--text); display: flex; align-items: center; gap: 8px; }
    .card-title i { color: var(--blue); }

    /* ── TABLE ── */
    .table-wrap { overflow-x: auto; }
    .inv-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .inv-table thead th { background: #F4F8FD; padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; color: var(--muted); letter-spacing: .5px; text-transform: uppercase; border-bottom: 2px solid var(--border); white-space: nowrap; }
    .inv-table tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
    .inv-table tbody tr:last-child { border-bottom: none; }
    .inv-table tbody tr:hover { background: #F4F8FD; }
    .inv-table td { padding: 13px 16px; color: var(--text); vertical-align: middle; }

    /* ── AVATAR ── */
    .avatar { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 800; flex-shrink: 0; }
    .avatar-admin  { background: #FEE2E2; color: #DC2626; }
    .avatar-murid  { background: #EFF6FF; color: #2563EB; }
    .avatar-guru   { background: #F0FDF4; color: #16A34A; }
    .avatar-tendik { background: #FFFBEB; color: #D97706; }

    /* ── BADGES ── */
    .badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; opacity:.7; }
    .badge-success { background: #F0FDF4; color: #15803D; }
    .badge-info    { background: #EFF6FF; color: #2563EB; }
    .badge-warning { background: #FFFBEB; color: #D97706; }
    .badge-danger  { background: #FEF2F2; color: #DC2626; }
    .badge-muted   { background: #F1F5F9; color: #64748B; }
    .badge-orange  { background: #FFF7ED; color: #EA580C; }

    /* ── PINJAM AKTIF ── */
    .pinjam-aktif-badge {
      display: inline-flex; align-items: center; gap: 5px;
      background: #FFF7ED; color: #EA580C;
      border: 1px solid #FED7AA;
      padding: 3px 10px; border-radius: 20px;
      font-size: 11px; font-weight: 700;
    }
    .pinjam-aktif-badge.none {
      background: #F1F5F9; color: #94A3B8;
      border-color: #E2E8F0;
    }

    /* ── BUTTONS ── */
    .btn { display: inline-flex; align-items: center; gap: 5px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all .2s; font-family: 'DM Sans', sans-serif; }
    .btn-sm { padding: 6px 10px; font-size: 12px; border-radius: 7px; }
    .btn-primary { background: var(--blue-dark); color: white; box-shadow: 0 4px 14px rgba(43,111,168,.25); }
    .btn-primary:hover { background: var(--blue-deep); }
    .btn-secondary { background: var(--card); color: var(--text); border: 1px solid var(--border); }
    .btn-secondary:hover { background: var(--bg); }
    .btn-danger { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
    .btn-danger:hover { background: #DC2626; color: white; }
    .btn-warning { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }
    .btn-warning:hover { background: #D97706; color: white; }
    .btn-success { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }
    .btn-success:hover { background: #16A34A; color: white; }

    /* ── TABLE FOOTER / PAGINATION ── */
    .table-footer { display: flex; align-items: center; justify-content: space-between; padding: 13px 20px; border-top: 1px solid var(--border); font-size: 13px; color: var(--muted); flex-wrap: wrap; gap: 10px; }
    .pag-btns { display: flex; align-items: center; gap: 6px; }
    .pag-btn { width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border: 1.5px solid var(--border); border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; background: var(--card); color: var(--text); text-decoration: none; transition: all .2s; }
    .pag-btn:hover:not(.disabled):not(.active) { background: var(--blue-dark); color: white; border-color: var(--blue-dark); }
    .pag-btn.active { background: var(--blue-dark); color: white; border-color: var(--blue-dark); }
    .pag-btn.disabled { opacity: .35; cursor: not-allowed; pointer-events: none; }
    .pag-btn-text { padding: 0 12px; width: auto; }

    /* ── EMPTY STATE ── */
    .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
    .empty-state i { font-size: 40px; display: block; margin-bottom: 12px; color: #B0C8E0; }
    .empty-state h3 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 6px; }
    .empty-state p { font-size: 13px; }

    /* ── MOBILE CARD LIST ── */
    .mobile-list { display: none; }
    .mobile-item { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; margin-bottom: 10px; box-shadow: var(--shadow); }
    .mobile-item-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
    .mobile-item-actions { display: flex; gap: 6px; flex-shrink: 0; }
    .mobile-item-meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

    /* ══ MODAL ══ */
    .modal-backdrop { display: none; position: fixed; inset: 0; z-index: 500; background: rgba(27,63,110,.35); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
    .modal-backdrop.open { display: flex; }
    .modal-box { background: var(--card); border-radius: 18px; padding: 32px; width: 100%; max-width: 500px; box-shadow: var(--shadow-lg); position: relative; z-index: 501; animation: modalIn .22s cubic-bezier(.4,0,.2,1); max-height: 90vh; overflow-y: auto; }
    .modal-box-sm { max-width: 420px; }
    @keyframes modalIn { from{transform:scale(.94) translateY(10px);opacity:0} to{transform:scale(1) translateY(0);opacity:1} }
    .modal-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 18px; font-weight: 800; color: var(--text); margin-bottom: 6px; }
    .modal-sub { font-size: 13px; color: var(--muted); margin-bottom: 22px; }
    .modal-close { position: absolute; top: 14px; right: 14px; width: 32px; height: 32px; border-radius: 8px; background: var(--bg); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; }
    .modal-close:hover { background: #FECACA; color: #DC2626; }
    .modal-error { background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; border-radius: 8px; padding: 9px 12px; font-size: 12px; margin-bottom: 14px; display: none; }
    .modal-error.show { display: block; }

    /* form fields */
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
    .form-group { margin-bottom: 14px; }
    .form-label { display: block; font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
    .form-control { width: 100%; padding: 11px 13px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'DM Sans', sans-serif; color: var(--text); background: var(--bg); outline: none; transition: all .2s; }
    .form-control:focus { border-color: var(--blue); background: white; box-shadow: 0 0 0 3px rgba(74,144,196,.14); }

    .btn-modal-submit { width: 100%; padding: 12px; background: var(--blue-dark); color: white; border: none; border-radius: 10px; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 6px 20px rgba(43,111,168,.28); transition: all .2s; margin-top: 4px; }
    .btn-modal-submit:hover { background: var(--blue-deep); }
    .btn-modal-danger { width: 100%; padding: 12px; background: #DC2626; color: white; border: none; border-radius: 10px; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all .2s; margin-top: 10px; }
    .btn-modal-danger:hover { background: #B91C1C; }
    .btn-modal-warning { width: 100%; padding: 12px; background: #D97706; color: white; border: none; border-radius: 10px; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all .2s; margin-top: 4px; }
    .btn-modal-warning:hover { background: #B45309; }
    .btn-modal-cancel { width: 100%; padding: 11px; background: var(--bg); color: var(--muted); border: 1.5px solid var(--border); border-radius: 10px; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all .2s; margin-top: 8px; }
    .btn-modal-cancel:hover { background: #E5E7EB; }

    /* ── IMPORT AREA ── */
    .upload-area { border: 2px dashed var(--border); border-radius: 12px; background: var(--bg); padding: 24px; text-align: center; cursor: pointer; transition: all .2s; position: relative; margin-bottom: 14px; }
    .upload-area:hover { border-color: var(--blue); background: #EFF6FF; }
    .upload-area input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .upload-area-icon { font-size: 32px; color: var(--blue); opacity: .6; margin-bottom: 8px; }
    .upload-area-text { font-size: 12px; color: var(--muted); line-height: 1.6; }
    .upload-area-text strong { color: var(--blue-dark); display: block; font-size: 13px; margin-bottom: 2px; }
    .upload-area-filename { font-size: 12px; color: var(--blue-dark); font-weight: 600; margin-top: 8px; display: none; }

    .csv-template-hint { background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 10px 14px; font-size: 11px; color: #166534; margin-bottom: 14px; line-height: 1.7; }
    .csv-template-hint code { background: #DCFCE7; padding: 1px 5px; border-radius: 4px; font-size: 11px; }

    /* ── Show Entries ── */
    .show-entries-wrap { display: flex; align-items: center; gap: 8px; }
    .show-entries-label { font-size: 13px; color: var(--muted); font-weight: 500; }
    .show-entries-select {
      padding: 7px 28px 7px 10px; border: 1.5px solid var(--border);
      border-radius: 8px; font-size: 13px; font-family: 'DM Sans', sans-serif;
      color: var(--text); background: var(--card);
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 8px center;
      cursor: pointer; outline: none; transition: border-color .2s;
    }
    .show-entries-select:hover, .show-entries-select:focus { border-color: var(--blue); }

    /* ── BULK ACTION BAR ── */
    .bulk-bar {
      display: none; align-items: center; gap: 10px; flex-wrap: wrap;
      background: var(--blue-deep); color: white;
      border-radius: 12px; padding: 10px 16px; margin-bottom: 14px;
      box-shadow: 0 4px 20px rgba(27,63,110,.25);
      animation: slideDown .2s cubic-bezier(.4,0,.2,1);
    }
    .bulk-bar.show { display: flex; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
    .bulk-count {
      font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; font-weight: 800;
      background: rgba(255,255,255,.18); border-radius: 20px; padding: 3px 12px;
    }
    .bulk-label { font-size: 13px; font-weight: 500; flex: 1; }
    .btn-bulk-danger {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700;
      cursor: pointer; border: none; font-family: 'DM Sans', sans-serif;
      background: #DC2626; color: white; transition: background .2s;
    }
    .btn-bulk-danger:hover { background: #B91C1C; }
    .btn-bulk-success {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700;
      cursor: pointer; border: none; font-family: 'DM Sans', sans-serif;
      background: #16A34A; color: white; transition: background .2s;
    }
    .btn-bulk-success:hover { background: #15803D; }
    .btn-bulk-warning {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700;
      cursor: pointer; border: none; font-family: 'DM Sans', sans-serif;
      background: #D97706; color: white; transition: background .2s;
    }
    .btn-bulk-warning:hover { background: #B45309; }
    .btn-bulk-cancel {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
      cursor: pointer; border: none; font-family: 'DM Sans', sans-serif;
      background: rgba(255,255,255,.12); color: rgba(255,255,255,.85); transition: background .2s;
    }
    .btn-bulk-cancel:hover { background: rgba(255,255,255,.22); }

    /* Checkbox styling */
    .cb-wrap { display: flex; align-items: center; justify-content: center; }
    .row-cb {
      width: 17px; height: 17px; border-radius: 5px; cursor: pointer;
      accent-color: var(--blue-dark);
    }
    .mobile-cb-wrap { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .mobile-cb-label { font-size: 11px; color: var(--muted); }

    /* password strength */
    .pass-strength { margin-top: 6px; }
    .pass-bar { height: 4px; border-radius: 2px; background: var(--border); overflow: hidden; margin-bottom: 4px; }
    .pass-bar-fill { height: 100%; border-radius: 2px; transition: all .3s; width: 0; }
    .pass-hint { font-size: 11px; color: var(--muted); }

    /* ── PENDING SECTION ── */
    .pending-section {
      background: #FFF8E1; border: 1.5px solid #FFE082;
      border-radius: 14px; padding: 18px 20px; margin-bottom: 20px;
    }
    .pending-section-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px; font-weight: 800; color: #92400E;
      display: flex; align-items: center; gap: 8px; margin-bottom: 12px;
    }
    .pending-count-badge {
      background: #F59E0B; color: white;
      border-radius: 20px; padding: 2px 9px;
      font-size: 11px; font-weight: 800;
    }
    .pending-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 10px 0; border-bottom: 1px solid #FFE082; gap: 12px; flex-wrap: wrap;
    }
    .pending-item:last-child { border-bottom: none; padding-bottom: 0; }
    .pending-item-info { display: flex; align-items: center; gap: 10px; }
    .pending-item-meta { font-size: 11px; color: #B45309; margin-top: 2px; }
    .pending-item-actions { display: flex; gap: 6px; flex-shrink: 0; }

    /* ── RESPONSIVE ── */
    @media (max-width: 768px) {
      .navbar { position: relative; }
      .nav-links { display: none; }
      .nav-hamburger { display: flex; align-items: center; justify-content: center; }
      .nav-brand { margin-right: 0; }
      .page-wrapper { padding: 20px 14px 80px; }
      .page-title { font-size: 20px; }
      .page-header { flex-direction: column; align-items: stretch; gap: 12px; }
      .header-actions { flex-direction: column; }
      .header-actions .btn { justify-content: center; }

      /* Stats pills: sembunyikan di mobile, ganti dengan dropdown */
      .stats-row { display: none; }
      .mobile-role-dropdown { display: block; margin-bottom: 16px; }
      .mobile-role-dropdown select { width: 100%; padding: 10px 36px 10px 14px; border: 1.5px solid var(--border); border-radius: 9px; font-size: 13px; font-family: 'DM Sans', sans-serif; color: var(--text); background: var(--card); outline: none; cursor: pointer; transition: border-color .2s; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }
      .mobile-role-dropdown select:focus { border-color: var(--blue); }

      /* Toolbar: search full width, show entries di kanan */
      .toolbar { flex-direction: column; gap: 8px; }
      #searchFormPengguna { width: 100% !important; flex: unset !important; min-width: unset !important; }
      .search-wrap-inner { width: 100% !important; min-width: unset !important; }
      .toolbar > form:last-child { align-self: flex-end; }
      .show-entries-wrap { width: auto; justify-content: flex-end; }

      .table-wrap { display: none; }
      .mobile-list { display: block; }
      .card-header { padding: 14px 16px; }
      .table-footer { flex-direction: column; align-items: flex-start; gap: 10px; padding: 12px 16px; }
      .pag-btns { width: 100%; justify-content: center; }
      .modal-box { margin: 12px; padding: 22px 18px; border-radius: 16px; max-width: 100%; }
      .form-row { grid-template-columns: 1fr; gap: 0; }
    }
  </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar">
  <a href="dashboard.php" class="nav-brand">
    <img src="../assets/logo.png" alt="Logo">
    <div class="nav-brand-text">
      <strong>Inventaris SARPRAS</strong>
      <span>SMAN 10 Pontianak</span>
    </div>
  </a>

  <!-- Desktop -->
  <div class="nav-links">
    <a href="dashboard.php"    class="nav-link">Dashboard</a>
    <a href="ruangan.php"      class="nav-link">Prasarana</a>
    <a href="barang.php"       class="nav-link">Sarana</a>
    <a href="pengguna.php"     class="nav-link active">
      Pengguna
      <?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?>
    </a>
    <a href="peminjaman.php"   class="nav-link">
      Peminjaman
      <?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?>
    </a>
    <a href="pengembalian.php" class="nav-link">Pengembalian</a>
    <a href="../auth/logout.php" class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>

  <!-- Hamburger -->
  <button class="nav-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Menu">
    <i class="bi bi-list" id="hamburgerIcon"></i>
  </button>
</nav>

<!-- Mobile Menu -->
<div class="nav-mobile-menu" id="mobileMenu">
  <a href="dashboard.php"      class="nav-link">Dashboard</a>
  <a href="ruangan.php"        class="nav-link">Prasarana</a>
  <a href="barang.php"         class="nav-link">Sarana</a>
  <a href="pengguna.php"       class="nav-link active">
    Pengguna
    <?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?>
  </a>
  <a href="peminjaman.php"     class="nav-link">
    Peminjaman
    <?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?>
  </a>
  <a href="pengembalian.php"   class="nav-link">Pengembalian</a>
  <a href="../auth/logout.php" class="nav-link logout">Logout</a>
</div>

<div class="page-wrapper">

  <?php if ($msg_success): ?>
  <div class="flash flash-success"><i class="bi bi-check-circle-fill"></i> <?= $msg_success ?></div>
  <?php endif; ?>
  <?php if ($msg_error): ?>
  <div class="flash flash-error"><i class="bi bi-exclamation-circle-fill"></i> <?= $msg_error ?></div>
  <?php endif; ?>

  <!-- Header -->
  <div class="page-header">
    <div>
      <div class="page-title">Pengguna</div>
      <div class="page-sub">Kelola akun pengguna sistem inventaris SARPRAS</div>
    </div>
    <div class="header-actions">
      <button class="btn btn-secondary" onclick="openModal('modalImport')">
        <i class="bi bi-file-earmark-excel"></i> Import Excel
      </button>
      <button class="btn btn-primary" onclick="openAddModal()">
        <i class="bi bi-plus-lg"></i> Tambah Pengguna
      </button>
    </div>
  </div>

  <!-- Stats Pills / Filter Role -->
  <div class="stats-row">
    <?php
    $base_q = '?' . ($search ? 'q='.urlencode($search).'&' : '');
    $role_labels = [''=>['Semua','#4A90C4'], 'admin'=>['Admin','#DC2626'], 'murid'=>['Murid','#2563EB'], 'guru'=>['Guru','#16A34A'], 'tendik'=>['Tendik','#D97706']];
    $role_counts = ['' => $stats['total'], 'admin'=>$stats['admin'], 'murid'=>$stats['murid'], 'guru'=>$stats['guru'], 'tendik'=>$stats['tendik']];
    foreach ($role_labels as $rk => $rv):
      $is_active = ($filter_role === $rk);
      $href = $base_q . ($rk ? 'role='.$rk : '');
    ?>
    <a href="<?= $href ?>" class="stat-pill <?= $is_active ? 'active' : '' ?>">
      <span class="pill-dot" style="background:<?= $rv[1] ?>;"></span>
      <?= $rv[0] ?>
      <span class="stat-pill-count">(<?= $role_counts[$rk] ?>)</span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Mobile: dropdown filter role (hanya tampil di mobile) -->
  <div class="mobile-role-dropdown">
    <select onchange="window.location.href=this.value">
      <option value="<?= '?' . ($search ? 'q='.urlencode($search) : '') ?>" <?= $filter_role===''?'selected':'' ?>>
        ● Semua (<?= $stats['total'] ?>)
      </option>
      <option value="<?= '?' . ($search?'q='.urlencode($search).'&':'') ?>role=admin" <?= $filter_role==='admin'?'selected':'' ?>>
        ● Admin (<?= $stats['admin'] ?>)
      </option>
      <option value="<?= '?' . ($search?'q='.urlencode($search).'&':'') ?>role=murid" <?= $filter_role==='murid'?'selected':'' ?>>
        ● Murid (<?= $stats['murid'] ?>)
      </option>
      <option value="<?= '?' . ($search?'q='.urlencode($search).'&':'') ?>role=guru" <?= $filter_role==='guru'?'selected':'' ?>>
        ● Guru (<?= $stats['guru'] ?>)
      </option>
      <option value="<?= '?' . ($search?'q='.urlencode($search).'&':'') ?>role=tendik" <?= $filter_role==='tendik'?'selected':'' ?>>
        ● Tendik (<?= $stats['tendik'] ?>)
      </option>
    </select>
  </div>

  <!-- ══ PENDING SECTION ══ -->
  <?php if ($pending_count > 0): ?>
  <div class="pending-section">
    <div class="pending-section-title">
      <i class="bi bi-hourglass-split"></i>
      Menunggu Persetujuan
      <span class="pending-count-badge"><?= $pending_count ?></span>
    </div>
    <?php
    $pq = mysqli_query($conn, "SELECT * FROM pengguna WHERE status='pending' ORDER BY id_pengguna ASC");
    while ($pend = mysqli_fetch_assoc($pq)):
      $rc_pend = $role_cfg[$pend['role']] ?? [ucfirst($pend['role']), 'badge-muted'];
      $inisial_pend = strtoupper(substr($pend['nama'], 0, 1));
    ?>
    <div class="pending-item">
      <div class="pending-item-info">
        <div class="avatar avatar-<?= $pend['role'] ?>"><?= $inisial_pend ?></div>
        <div>
          <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($pend['nama']) ?></div>
          <div class="pending-item-meta">
            <?= htmlspecialchars($pend['id_pengguna']) ?> &bull;
            @<?= htmlspecialchars($pend['username']) ?> &bull;
            <?= htmlspecialchars($pend['email'] ?: '—') ?> &bull;
            <span class="badge <?= $rc_pend[1] ?>"><?= $rc_pend[0] ?></span>
          </div>
        </div>
      </div>
      <div class="pending-item-actions">
        <form method="POST" style="display:inline;">
          <input type="hidden" name="id_pengguna" value="<?= htmlspecialchars($pend['id_pengguna']) ?>">
          <button type="submit" name="approve_pengguna" class="btn btn-sm btn-success">
            <i class="bi bi-check-lg"></i> Setujui
          </button>
        </form>
        <form method="POST" style="display:inline;"
              onsubmit="return confirm('Tolak dan hapus pendaftaran <?= htmlspecialchars(addslashes($pend['nama'])) ?>?')">
          <input type="hidden" name="id_pengguna" value="<?= htmlspecialchars($pend['id_pengguna']) ?>">
          <button type="submit" name="reject_pengguna" class="btn btn-sm btn-danger">
            <i class="bi bi-x-lg"></i> Tolak
          </button>
        </form>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
  <?php endif; ?>

  <!-- Toolbar: search -->
  <div class="toolbar">
    <form method="GET" id="searchFormPengguna" style="display:flex;gap:0;flex:1;min-width:200px;">
      <?php if ($filter_role): ?>
        <input type="hidden" name="role" value="<?= $filter_role ?>">
      <?php endif; ?>
      <div class="search-wrap-inner">
        <i class="bi bi-search search-icon"></i>
        <input type="text" name="q" id="searchInputPengguna" class="search-input"
               placeholder="Cari nama, username, atau email..."
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <button type="submit" class="btn-search"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($search || $filter_role): ?>
    <a href="pengguna.php" class="btn btn-secondary btn-sm">
      <i class="bi bi-x"></i> Reset Filter
    </a>
    <?php endif; ?>
    <form method="GET" style="display:flex;align-items:center;">
      <?php if ($filter_role): ?><input type="hidden" name="role" value="<?= $filter_role ?>"><?php endif; ?>
      <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
      <div class="show-entries-wrap">
        <label class="show-entries-label">Tampilkan</label>
        <select class="show-entries-select" name="per_page" onchange="this.form.submit()">
          <option value="5" <?= $per_page==5?'selected':'' ?>>5</option>
          <option value="10" <?= $per_page==10?'selected':'' ?>>10</option>
          <option value="20" <?= $per_page==20?'selected':'' ?>>20</option>
          <option value="25" <?= $per_page==25?'selected':'' ?>>25</option>
          <option value="50" <?= $per_page==50?'selected':'' ?>>50</option>
          <option value="100" <?= $per_page==100?'selected':'' ?>>100</option>
        </select>
        <span class="show-entries-label">entri</span>
      </div>
    </form>
  </div>

  <!-- ══ BULK ACTION BAR ══ -->
  <div class="bulk-bar" id="bulkBar">
    <span class="bulk-count" id="bulkCount">0</span>
    <span class="bulk-label">pengguna dipilih</span>
    <button class="btn-bulk-warning" id="btnBulkNonaktif" onclick="openBulkNonaktifModal()" style="display:none;">
      <i class="bi bi-person-slash"></i> Nonaktifkan
    </button>
    <button class="btn-bulk-success" id="btnBulkAktif" onclick="openBulkAktifModal()" style="display:none;">
      <i class="bi bi-person-check"></i> Aktifkan
    </button>
    <button class="btn-bulk-danger" id="btnBulkHapus" onclick="openBulkHapusModal()">
      <i class="bi bi-trash"></i> Hapus
    </button>
    <button class="btn-bulk-cancel" onclick="clearAllChecks()">
      <i class="bi bi-x"></i> Batal
    </button>
  </div>

  <!-- TABEL PENGGUNA -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i class="bi bi-people"></i> Daftar Pengguna
      </div>
      <span style="font-size:12px;color:var(--muted);"><?= number_format($total) ?> pengguna</span>
    </div>

    <div class="table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th style="width:40px;text-align:center;">
              <div class="cb-wrap">
                <input type="checkbox" class="row-cb" id="checkAll" title="Pilih semua" onchange="toggleAll(this)">
              </div>
            </th>
            <th style="width:46px;">No</th>
            <th>Pengguna</th>
            <th>Username</th>
            <th>Email</th>
            <th style="width:100px;">Role</th>
            <th style="width:110px;text-align:center;">Status</th>
            <th style="width:120px;text-align:center;">Pinjaman Aktif</th>
            <th style="width:160px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no  = $offset + 1;
          $has = false;
          $my_id_session = $_SESSION['id_pengguna'];
          while ($p = mysqli_fetch_assoc($pengguna_q)):
            $has = true;
            $rc  = $role_cfg[$p['role']] ?? [ucfirst($p['role']), 'badge-muted'];
            $inisial = strtoupper(substr($p['nama'], 0, 1));
            $is_self_row  = ($p['id_pengguna'] === $my_id_session);
            $is_other_admin = (!$is_self_row && $p['role'] === 'admin');
            $p_js = htmlspecialchars(json_encode([
              'id'       => $p['id_pengguna'],
              'nama'     => $p['nama'],
              'email'    => $p['email'],
              'username' => $p['username'],
              'role'     => $p['role'],
            ]), ENT_QUOTES);
            $is_admin_nonaktif = ($p['role'] === 'admin' && $p['status'] === 'nonaktif');
          ?>
          <tr>
            <td style="text-align:center;">
              <?php if (!$is_self_row && $p['role'] !== 'admin'): ?>
              <div class="cb-wrap">
                <input type="checkbox" class="row-cb bulk-cb"
                       value="<?= htmlspecialchars($p['id_pengguna']) ?>"
                       data-nama="<?= htmlspecialchars($p['nama']) ?>"
                       data-status="<?= htmlspecialchars($p['status'] ?? 'aktif') ?>"
                       onchange="updateBulkBar()">
              </div>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted);font-size:13px;"><?= $no++ ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="avatar avatar-<?= $p['role'] ?>"><?= $inisial ?></div>
                <div>
                  <div style="font-weight:600;"><?= htmlspecialchars($p['nama']) ?></div>
                  <div style="font-size:11px;color:var(--muted);margin-top:1px;"><?= htmlspecialchars($p['id_pengguna']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-size:13px;font-family:monospace;color:var(--blue-dark);">
              <?= htmlspecialchars($p['username']) ?>
            </td>
            <td style="font-size:13px;color:var(--muted);"><?= htmlspecialchars($p['email'] ?: '—') ?></td>
            <td><span class="badge <?= $rc[1] ?>"><?= $rc[0] ?></span></td>
            <td style="text-align:center;">
              <?php
                $st = $p['status'] ?? 'aktif';
                if ($st === 'aktif') echo '<span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Aktif</span>';
                elseif ($st === 'nonaktif') echo '<span class="badge badge-muted" style="background:#FEF2F2;color:#DC2626;"><i class="bi bi-slash-circle-fill"></i> Nonaktif</span>';
                else echo '<span class="badge badge-warning">Pending</span>';
              ?>
            </td>
            <td style="text-align:center;">
              <?php if ($p['pinjam_aktif'] > 0): ?>
                <span class="pinjam-aktif-badge">
                  <i class="bi bi-box-arrow-up-right" style="font-size:10px;"></i>
                  <?= $p['pinjam_aktif'] ?> aktif
                </span>
              <?php else: ?>
                <span class="pinjam-aktif-badge none">Tidak ada</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <?php if ($is_other_admin && !$is_admin_nonaktif): ?>
                <!-- Admin aktif lain: hanya tampilkan tombol nonaktifkan -->
                <button class="btn btn-sm btn-danger" title="Nonaktifkan admin ini"
                        onclick="openNonaktifModal('<?= htmlspecialchars(addslashes($p['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p['nama'])) ?>')">
                  <i class="bi bi-person-slash"></i> Nonaktifkan
                </button>
              <?php elseif ($is_admin_nonaktif): ?>
                <!-- Admin nonaktif: tampilkan edit, reset password, aktifkan kembali -->
                <button class="btn btn-sm btn-secondary" title="Edit" onclick="openEditModal(<?= $p_js ?>)" style="margin-right:2px;">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-warning" title="Reset Password"
                        onclick="openResetModal('<?= htmlspecialchars(addslashes($p['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p['nama'])) ?>')"
                        style="margin-right:2px;">
                  <i class="bi bi-key"></i>
                </button>
                <button class="btn btn-sm btn-success" title="Aktifkan Kembali"
                        onclick="openAktifkanModal('<?= htmlspecialchars(addslashes($p['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p['nama'])) ?>')">
                  <i class="bi bi-person-check"></i>
                </button>
              <?php elseif (!$is_self_row): ?>
                <!-- Bukan admin: tampilkan edit, reset, nonaktifkan/aktifkan -->
                <button class="btn btn-sm btn-secondary" title="Edit" onclick="openEditModal(<?= $p_js ?>)" style="margin-right:2px;">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-warning" title="Reset Password"
                        onclick="openResetModal('<?= htmlspecialchars(addslashes($p['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p['nama'])) ?>')"
                        style="margin-right:2px;">
                  <i class="bi bi-key"></i>
                </button>
                <?php if (($p['status'] ?? 'aktif') === 'nonaktif'): ?>
                <button class="btn btn-sm btn-success" title="Aktifkan kembali"
                        onclick="openAktifkanPenggunaModal('<?= htmlspecialchars(addslashes($p['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p['nama'])) ?>')"
                        style="margin-right:2px;">
                  <i class="bi bi-person-check"></i> Aktifkan
                </button>
                <?php else: ?>
                <button class="btn btn-sm btn-warning" title="Nonaktifkan"
                        onclick="openNonaktifkanPenggunaModal('<?= htmlspecialchars(addslashes($p['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p['nama'])) ?>')"
                        style="margin-right:2px;">
                  <i class="bi bi-person-slash"></i>
                </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-danger" title="Hapus"
                        onclick="openDeleteModal('<?= htmlspecialchars(addslashes($p['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p['nama'])) ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              <?php else: ?>
                <!-- Akun sendiri -->
                <button class="btn btn-sm btn-secondary" title="Edit" onclick="openEditModal(<?= $p_js ?>)" style="margin-right:2px;">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-warning" title="Reset Password"
                        onclick="openResetModal('<?= htmlspecialchars(addslashes($p['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p['nama'])) ?>')">
                  <i class="bi bi-key"></i>
                </button>
                <span style="font-size:11px;color:var(--blue-dark);display:inline-flex;align-items:center;gap:4px;margin-left:4px;"
                      title="Ini akun Anda yang sedang aktif">
                  <i class="bi bi-person-fill-check"></i> Akun Anda
                </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>

          <?php if (!$has): ?>
          <tr>
            <td colspan="9">
              <div class="empty-state">
                <i class="bi bi-people"></i>
                <h3><?= $search ? 'Tidak ditemukan' : 'Belum ada pengguna' ?></h3>
                <p>
                  <?= $search
                    ? "Tidak ada pengguna yang cocok dengan \"".htmlspecialchars($search)."\""
                    : "Belum ada data pengguna di sistem." ?>
                </p>
              </div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- ══ MOBILE CARD LIST ══ -->
    <div class="mobile-list" id="mobileList">
      <?php
      $pengguna_q2 = mysqli_query($conn, "
          SELECT p.*,
                 (SELECT COUNT(*) FROM peminjaman pm
                  WHERE pm.id_pengguna = p.id_pengguna
                    AND pm.status IN ('menunggu','dipinjam')) AS pinjam_aktif
          FROM pengguna p
          $where
          ORDER BY p.nama ASC
          LIMIT $per_page OFFSET $offset
      ");
      while ($p2 = mysqli_fetch_assoc($pengguna_q2)):
        $rc2 = $role_cfg[$p2['role']] ?? [ucfirst($p2['role']), 'badge-muted'];
        $inisial2 = strtoupper(substr($p2['nama'], 0, 1));
        $is_self_row2    = ($p2['id_pengguna'] === $my_id_session);
        $is_other_admin2 = (!$is_self_row2 && $p2['role'] === 'admin');
        $p2_js = htmlspecialchars(json_encode([
          'id'       => $p2['id_pengguna'],
          'nama'     => $p2['nama'],
          'email'    => $p2['email'],
          'username' => $p2['username'],
          'role'     => $p2['role'],
        ]), ENT_QUOTES);
      ?>
      <div class="mobile-item">
        <?php if (!$is_self_row2 && $p2['role'] !== 'admin'): ?>
        <div class="mobile-cb-wrap">
          <input type="checkbox" class="row-cb bulk-cb"
                 value="<?= htmlspecialchars($p2['id_pengguna']) ?>"
                 data-nama="<?= htmlspecialchars($p2['nama']) ?>"
                 data-status="<?= htmlspecialchars($p2['status'] ?? 'aktif') ?>"
                 onchange="updateBulkBar()">
          <span class="mobile-cb-label">Pilih untuk aksi massal</span>
        </div>
        <?php endif; ?>
        <div class="mobile-item-header">
          <div style="display:flex;align-items:center;gap:10px;">
            <div class="avatar avatar-<?= $p2['role'] ?>"><?= $inisial2 ?></div>
            <div>
              <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($p2['nama']) ?></div>
              <div style="font-size:11px;color:var(--muted);">@<?= htmlspecialchars($p2['username']) ?></div>
            </div>
          </div>
          <div class="mobile-item-actions">
            <?php if ($is_other_admin2): ?>
              <span style="font-size:11px;color:var(--muted);display:inline-flex;align-items:center;gap:4px;"
                    title="Akun admin tidak dapat diubah">
                <i class="bi bi-shield-lock-fill"></i>
              </span>
            <?php else: ?>
              <button class="btn btn-sm btn-secondary" title="Edit" onclick="openEditModal(<?= $p2_js ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-sm btn-warning" title="Reset Password"
                      onclick="openResetModal('<?= htmlspecialchars(addslashes($p2['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p2['nama'])) ?>')">
                <i class="bi bi-key"></i>
              </button>
              <?php if (!$is_self_row2): ?>
              <?php if (($p2['status'] ?? 'aktif') === 'nonaktif'): ?>
              <button class="btn btn-sm btn-success" title="Aktifkan kembali"
                      onclick="openAktifkanPenggunaModal('<?= htmlspecialchars(addslashes($p2['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p2['nama'])) ?>')">
                <i class="bi bi-person-check"></i>
              </button>
              <?php else: ?>
              <button class="btn btn-sm btn-warning" title="Nonaktifkan"
                      onclick="openNonaktifkanPenggunaModal('<?= htmlspecialchars(addslashes($p2['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p2['nama'])) ?>')">
                <i class="bi bi-person-slash"></i>
              </button>
              <?php endif; ?>
              <button class="btn btn-sm btn-danger" title="Hapus"
                      onclick="openDeleteModal('<?= htmlspecialchars(addslashes($p2['id_pengguna'])) ?>','<?= htmlspecialchars(addslashes($p2['nama'])) ?>')">
                <i class="bi bi-trash"></i>
              </button>
              <?php else: ?>
              <span style="font-size:11px;color:var(--blue-dark);display:inline-flex;align-items:center;gap:4px;">
                <i class="bi bi-person-fill-check"></i>
              </span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="mobile-item-meta">
          <span class="badge <?= $rc2[1] ?>"><?= $rc2[0] ?></span>
          <?php if ($p2['email']): ?>
            <span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($p2['email']) ?></span>
          <?php endif; ?>
          <?php if ($p2['pinjam_aktif'] > 0): ?>
            <span class="pinjam-aktif-badge"><i class="bi bi-box-arrow-up-right" style="font-size:10px;"></i> <?= $p2['pinjam_aktif'] ?> aktif</span>
          <?php else: ?>
            <span class="pinjam-aktif-badge none">Tidak ada pinjaman</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total > 0): ?>
    <div class="table-footer">
      <span>Menampilkan <?= $offset+1 ?>–<?= min($offset+$per_page,$total) ?> dari <?= number_format($total) ?> pengguna</span>
      <div class="pag-btns">
        <?php
        $base_url = '?' .
          ($search      ? 'q='.urlencode($search).'&'      : '') .
          ($filter_role ? 'role='.$filter_role.'&'         : '') .
          ($per_page!=5 ? 'per_page='.$per_page.'&' : '');
        ?>
        <a href="<?= $base_url ?>page=<?= $page-1 ?>"
           class="pag-btn pag-btn-text <?= $page<=1 ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-left"></i> Previous
        </a>
        <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
          <a href="<?= $base_url ?>page=<?= $i ?>"
             class="pag-btn <?= $i==$page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $base_url ?>page=<?= $page+1 ?>"
           class="pag-btn pag-btn-text <?= $page>=$total_pages ? 'disabled' : '' ?>">
          Next <i class="bi bi-chevron-right"></i>
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /page-wrapper -->

<footer style="background:var(--blue-deep);color:rgba(255,255,255,.6);text-align:center;padding:20px;font-size:12px;">
  &copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak
</footer>


<!-- ══ MODAL TAMBAH PENGGUNA ══ -->
<div class="modal-backdrop" id="modalAdd" onclick="handleBackdropClick(event,'modalAdd')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalAdd')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Tambah Pengguna</div>
    <div class="modal-sub">Isi data pengguna baru.</div>
    <div class="modal-error" id="addError"></div>
    <form method="POST" onsubmit="return validateForm('addNama','addUsername','addPassword','addError')">
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">ID Pengguna <span style="color:#DC2626;">*</span></label>
          <input type="text" name="id_pengguna" class="form-control" placeholder="Contoh: 12345 atau NIS" required>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Role <span style="color:#DC2626;">*</span></label>
          <select name="role" class="form-control">
            <option value="murid">Murid</option>
            <option value="guru">Guru</option>
            <option value="tendik">Tendik</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Nama Lengkap <span style="color:#DC2626;">*</span></label>
        <input type="text" name="nama" id="addNama" class="form-control" placeholder="Nama lengkap pengguna" autocomplete="off">
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" placeholder="email@example.com">
      </div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Username <span style="color:#DC2626;">*</span></label>
          <input type="text" name="username" id="addUsername" class="form-control" placeholder="Username unik" autocomplete="off">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Password <span style="color:#DC2626;">*</span></label>
          <input type="text" name="password" id="addPassword" class="form-control" placeholder="Min. 6 karakter"
                 oninput="checkStrength(this,'addBar','addHint')">
        </div>
      </div>
      <div class="pass-strength" style="margin-top:8px;">
        <div class="pass-bar"><div class="pass-bar-fill" id="addBar"></div></div>
        <div class="pass-hint" id="addHint"></div>
      </div>
      <button type="submit" name="tambah_pengguna" class="btn-modal-submit" style="margin-top:16px;">
        <i class="bi bi-person-plus"></i> Tambah Pengguna
      </button>
    </form>
  </div>
</div>


<!-- ══ MODAL EDIT PENGGUNA ══ -->
<div class="modal-backdrop" id="modalEdit" onclick="handleBackdropClick(event,'modalEdit')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalEdit')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Edit Pengguna</div>
    <div class="modal-sub">Ubah data pengguna (password tidak berubah).</div>
    <div class="modal-error" id="editError"></div>
    <form method="POST" onsubmit="return validateEditForm('editNama','editUsername','editError')">
      <input type="hidden" name="id_pengguna_lama" id="editIdLama">
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">ID Pengguna</label>
          <input type="text" name="id_pengguna" id="editId" class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Role <span style="color:#DC2626;">*</span></label>
          <select name="role" id="editRole" class="form-control">
            <option value="murid">Murid</option>
            <option value="guru">Guru</option>
            <option value="tendik">Tendik</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Nama Lengkap <span style="color:#DC2626;">*</span></label>
        <input type="text" name="nama" id="editNama" class="form-control">
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" id="editEmail" class="form-control" placeholder="email@example.com">
      </div>
      <div class="form-group">
        <label class="form-label">Username <span style="color:#DC2626;">*</span></label>
        <input type="text" name="username" id="editUsername" class="form-control">
      </div>
      <button type="submit" name="edit_pengguna" class="btn-modal-submit">
        <i class="bi bi-check-circle"></i> Simpan Perubahan
      </button>
    </form>
  </div>
</div>


<!-- ══ MODAL RESET PASSWORD ══ -->
<div class="modal-backdrop" id="modalReset" onclick="handleBackdropClick(event,'modalReset')">
  <div class="modal-box modal-box-sm">
    <button class="modal-close" onclick="closeModal('modalReset')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title" style="color:#D97706;"><i class="bi bi-key-fill"></i> Reset Password</div>
    <div class="modal-sub">Reset password untuk pengguna <strong id="resetNama"></strong>.</div>
    <div class="modal-error" id="resetError"></div>
    <form method="POST" onsubmit="return validateReset('resetPassword','resetError')">
      <input type="hidden" name="id_pengguna" id="resetId">
      <div class="form-group">
        <label class="form-label">Password Baru <span style="color:#DC2626;">*</span></label>
        <input type="text" name="new_password" id="resetPassword" class="form-control"
               placeholder="Min. 6 karakter"
               oninput="checkStrength(this,'resetBar','resetHint')">
        <div class="pass-strength" style="margin-top:8px;">
          <div class="pass-bar"><div class="pass-bar-fill" id="resetBar"></div></div>
          <div class="pass-hint" id="resetHint"></div>
        </div>
      </div>
      <button type="submit" name="reset_password" class="btn-modal-warning">
        <i class="bi bi-key"></i> Reset Password
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalReset')">Batal</button>
    </form>
  </div>
</div>


<!-- ══ MODAL HAPUS PENGGUNA ══ -->
<div class="modal-backdrop" id="modalDelete" onclick="handleBackdropClick(event,'modalDelete')">
  <div class="modal-box modal-box-sm">
    <button class="modal-close" onclick="closeModal('modalDelete')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title" style="color:#DC2626;">Hapus Pengguna</div>
    <div class="modal-sub">
      Apakah kamu yakin ingin menghapus pengguna <strong id="deleteNama"></strong>?
      <br>Pengguna dengan peminjaman aktif tidak dapat dihapus.
    </div>
    <form method="POST">
      <input type="hidden" name="id_pengguna" id="deleteId">
      <button type="submit" name="hapus_pengguna" class="btn-modal-danger">
        <i class="bi bi-trash"></i> Ya, Hapus
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalDelete')">Batal</button>
    </form>
  </div>
</div>


<!-- ══ MODAL NONAKTIFKAN ADMIN ══ -->
<div class="modal-backdrop" id="modalNonaktif" onclick="handleBackdropClick(event,'modalNonaktif')">
  <div class="modal-box modal-box-sm">
    <button class="modal-close" onclick="closeModal('modalNonaktif')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title" style="color:#DC2626;"><i class="bi bi-person-slash"></i> Nonaktifkan Admin</div>
    <div class="modal-sub">Aksi ini akan mencabut akses login untuk admin <strong id="nonaktifNama"></strong>.</div>

    <!-- Warning box -->
    <div style="background:#FFF8E1;border:1.5px solid #FFE082;border-radius:10px;padding:14px 16px;margin-bottom:16px;">
      <div style="font-size:12px;font-weight:700;color:#92400E;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-exclamation-triangle-fill"></i> Perhatian
      </div>
      <ul style="font-size:12px;color:#78350F;margin:0;padding-left:16px;line-height:1.8;">
        <li>Admin tidak akan bisa login setelah dinonaktifkan.</li>
        <li>Data &amp; riwayat tetap tersimpan.</li>
        <li>Setelah nonaktif, akun bisa diedit untuk pergantian orang baru.</li>
        <li>Admin lain dapat mengaktifkan kembali kapan saja.</li>
      </ul>
    </div>

    <form method="POST" onsubmit="return validateNonaktif()">
      <input type="hidden" name="id_pengguna" id="nonaktifId">
      <div class="form-group">
        <label class="form-label">Konfirmasi — Ketik nama admin: <strong id="nonaktifNamaLabel" style="color:#DC2626;"></strong></label>
        <input type="text" name="konfirm_nama" id="nonaktifKonfirm" class="form-control"
               placeholder="Ketik nama admin untuk konfirmasi" autocomplete="off">
        <div style="font-size:11px;color:var(--muted);margin-top:4px;">Penulisan tidak case-sensitive.</div>
      </div>
      <div class="modal-error" id="nonaktifError"></div>
      <button type="submit" name="nonaktifkan_admin" class="btn-modal-danger" style="margin-top:4px;">
        <i class="bi bi-person-slash"></i> Ya, Nonaktifkan
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalNonaktif')">Batal</button>
    </form>
  </div>
</div>


<!-- ══ MODAL AKTIFKAN KEMBALI ADMIN ══ -->
<div class="modal-backdrop" id="modalAktifkan" onclick="handleBackdropClick(event,'modalAktifkan')">
  <div class="modal-box modal-box-sm">
    <button class="modal-close" onclick="closeModal('modalAktifkan')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title" style="color:#16A34A;"><i class="bi bi-person-check"></i> Aktifkan Kembali</div>
    <div class="modal-sub">Aktifkan akun admin <strong id="aktifkanNama"></strong> sehingga bisa login kembali.</div>
    <form method="POST">
      <input type="hidden" name="id_pengguna" id="aktifkanId">
      <button type="submit" name="aktifkan_admin" class="btn-modal-submit" style="background:#16A34A;box-shadow:0 6px 20px rgba(22,163,74,.28);">
        <i class="bi bi-person-check"></i> Ya, Aktifkan Kembali
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalAktifkan')">Batal</button>
    </form>
  </div>
</div>


<!-- ══ MODAL BULK NONAKTIFKAN ══ -->
<!-- ══ MODAL BULK AKTIFKAN ══ -->
<div class="modal-backdrop" id="modalBulkAktif" onclick="handleBackdropClick(event,'modalBulkAktif')">
  <div class="modal-box modal-box-sm">
    <button class="modal-close" onclick="closeModal('modalBulkAktif')"><i class="bi bi-x-lg"></i></button>
    <div style="text-align:center;margin-bottom:18px;">
      <div style="width:52px;height:52px;border-radius:14px;background:#F0FDF4;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="bi bi-person-check" style="font-size:24px;color:#16A34A;"></i>
      </div>
      <div class="modal-title">Aktifkan Pengguna</div>
      <div class="modal-sub">Aktifkan <strong id="bulkAktifCount">0</strong> pengguna terpilih?
        <br><small style="color:#6B7C93;">Admin dan akun Anda sendiri akan dilewati otomatis.</small>
      </div>
    </div>
    <form method="POST" id="formBulkAktif">
      <div id="bulkAktifInputs"></div>
      <button type="submit" name="bulk_aktifkan" class="btn-modal-submit">
        <i class="bi bi-person-check"></i> Ya, Aktifkan
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalBulkAktif')">Batal</button>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="modalBulkNonaktif" onclick="handleBackdropClick(event,'modalBulkNonaktif')">
  <div class="modal-box modal-box-sm">
    <button class="modal-close" onclick="closeModal('modalBulkNonaktif')"><i class="bi bi-x-lg"></i></button>
    <div style="text-align:center;margin-bottom:18px;">
      <div style="width:52px;height:52px;border-radius:14px;background:#FFFBEB;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="bi bi-person-slash" style="font-size:24px;color:#D97706;"></i>
      </div>
      <div class="modal-title">Nonaktifkan Pengguna</div>
      <div class="modal-sub">Nonaktifkan <strong id="bulkNonaktifCount">0</strong> pengguna terpilih?
        <br><small style="color:#6B7C93;">Admin dan akun Anda sendiri akan dilewati otomatis.</small>
      </div>
    </div>
    <form method="POST" id="formBulkNonaktif">
      <div id="bulkNonaktifInputs"></div>
      <button type="submit" name="bulk_nonaktifkan" class="btn-modal-warning">
        <i class="bi bi-person-slash"></i> Ya, Nonaktifkan
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalBulkNonaktif')">Batal</button>
    </form>
  </div>
</div>

<!-- ══ MODAL BULK HAPUS ══ -->
<div class="modal-backdrop" id="modalBulkHapus" onclick="handleBackdropClick(event,'modalBulkHapus')">
  <div class="modal-box modal-box-sm">
    <button class="modal-close" onclick="closeModal('modalBulkHapus')"><i class="bi bi-x-lg"></i></button>
    <div style="text-align:center;margin-bottom:18px;">
      <div style="width:52px;height:52px;border-radius:14px;background:#FEF2F2;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="bi bi-trash" style="font-size:24px;color:#DC2626;"></i>
      </div>
      <div class="modal-title" style="color:#DC2626;">Hapus Pengguna</div>
      <div class="modal-sub">Hapus permanen <strong id="bulkHapusCount">0</strong> pengguna terpilih?
        <br><small style="color:#6B7C93;">Admin, akun sendiri, dan yang punya pinjaman aktif dilewati.</small>
      </div>
    </div>
    <div style="background:#FEF2F2;border:1.5px solid #FECACA;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:#DC2626;">
      <i class="bi bi-exclamation-triangle-fill"></i> Tindakan ini <strong>tidak dapat dibatalkan</strong>. Semua data riwayat peminjaman pengguna yang terhapus juga akan ikut dihapus.
    </div>
    <form method="POST" id="formBulkHapus">
      <div id="bulkHapusInputs"></div>
      <button type="submit" name="bulk_hapus" class="btn-modal-danger">
        <i class="bi bi-trash"></i> Ya, Hapus Permanen
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalBulkHapus')">Batal</button>
    </form>
  </div>
</div>


<!-- ══ MODAL IMPORT CSV ══ -->
<div class="modal-backdrop" id="modalImport" onclick="handleBackdropClick(event,'modalImport')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalImport')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Import Pengguna via Excel</div>
    <div class="modal-sub">Upload file Excel (.xlsx) untuk menambahkan banyak pengguna sekaligus.</div>

    <div class="csv-template-hint">
      <i class="bi bi-exclamation-triangle-fill"></i> <strong>ID Pengguna wajib diisi</strong> — Role valid: <code>admin</code> <code>murid</code> <code>guru</code> <code>tendik</code>
      <br>
      <a href="pengguna_template.xlsx" download
         style="color:var(--blue-dark);font-weight:700;font-size:11px;display:inline-flex;align-items:center;gap:4px;margin-top:6px;">
        <i class="bi bi-download"></i> Download Template Excel
      </a>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <div class="upload-area" id="importUploadArea">
        <input type="file" name="csv_file" id="csvFileInput" accept=".xlsx,.xls,.csv"
               onchange="showFileName(this)">
        <div class="upload-area-icon"><i class="bi bi-file-earmark-excel" style="color:#16A34A;"></i></div>
        <div class="upload-area-text">
          <strong>Klik untuk pilih file Excel</strong>
          Format: .xlsx / .xls / .csv — maks 5MB
        </div>
        <div class="upload-area-filename" id="csvFileName"></div>
      </div>
      <button type="submit" name="import_csv" class="btn-modal-submit">
        <i class="bi bi-upload"></i> Import Excel
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalImport')">Batal</button>
    </form>
  </div>
</div>


<script>

/* ── Live Search dengan fetch (fokus tidak hilang) ── */
function initTableControls(formId, searchInputId) {
  const input = document.getElementById(searchInputId);
  if (!input) return;
  
  let timer;
  let lastQuery = input.value;
  
  input.addEventListener('input', function() {
    const q = this.value;
    clearTimeout(timer);
    timer = setTimeout(function() {
      if (q === lastQuery) return;
      lastQuery = q;
      
      // Update URL
      const url = new URL(window.location.href);
      url.searchParams.set('q', q);
      url.searchParams.set('page', '1');
      window.history.replaceState({}, '', url.toString());
      
      // Fetch dan update tabel
      fetchTableData(url.toString(), searchInputId);
    }, 350);
  });
}

function fetchTableData(url, focusInputId) {
  // Tambahkan marker agar PHP tahu ini AJAX request
  const fetchUrl = url + (url.includes('?') ? '&' : '?') + '_ajax_table=1';
  
  // Show loading state
  const tableWrap = document.querySelector('.table-wrap');
  const mobileList = document.querySelector('.mobile-list');
  const tableFooter = document.querySelector('.table-footer');
  const cardHeader = document.querySelector('.card-header span[style*="color:var(--muted)"]');
  
  if (tableWrap) tableWrap.style.opacity = '0.5';
  if (mobileList) mobileList.style.opacity = '0.5';

  fetch(url)
    .then(r => r.text())
    .then(html => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      
      // Update tbody
      const newTbody = doc.querySelector('.inv-table tbody');
      const curTbody = document.querySelector('.inv-table tbody');
      if (newTbody && curTbody) curTbody.innerHTML = newTbody.innerHTML;
      
      // Update mobile list
      const newMobile = doc.querySelector('.mobile-list');
      const curMobile = document.querySelector('.mobile-list');
      if (newMobile && curMobile) curMobile.innerHTML = newMobile.innerHTML;
      
      // Update pagination
      const newFooter = doc.querySelector('.table-footer');
      const curFooter = document.querySelector('.table-footer');
      if (newFooter && curFooter) curFooter.innerHTML = newFooter.innerHTML;
      
      // Update counter
      const newCounter = doc.querySelector('.card-header span[style*="color:var(--muted)"]');
      const curCounter = document.querySelector('.card-header span[style*="color:var(--muted)"]');
      if (newCounter && curCounter) curCounter.innerHTML = newCounter.innerHTML;
      
      if (tableWrap) tableWrap.style.opacity = '1';
      if (mobileList) mobileList.style.opacity = '1';
      
      // Jaga fokus tetap di input
      const input = document.getElementById(focusInputId);
      if (input) {
        const len = input.value.length;
        input.focus();
        input.setSelectionRange(len, len);
      }
    })
    .catch(() => {
      if (tableWrap) tableWrap.style.opacity = '1';
      if (mobileList) mobileList.style.opacity = '1';
    });
}
  /* ── Hamburger ── */
  function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('hamburgerIcon');
    const isOpen = menu.classList.toggle('open');
    icon.className = isOpen ? 'bi bi-x-lg' : 'bi bi-list';
  }
  document.addEventListener('click', function(e) {
    const menu = document.getElementById('mobileMenu');
    const btn  = document.getElementById('hamburgerBtn');
    if (menu.classList.contains('open') && !menu.contains(e.target) && !btn.contains(e.target)) {
      menu.classList.remove('open');
      document.getElementById('hamburgerIcon').className = 'bi bi-list';
    }
  });

  /* ── Modal helpers ── */
  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    const errMap = {modalAdd:'addError',modalEdit:'editError',modalReset:'resetError'};
    if (errMap[id]) document.getElementById(errMap[id]).classList.remove('show');
  }
  function handleBackdropClick(e, id) {
    if (e.target === document.getElementById(id)) closeModal(id);
  }
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
      ['modalAdd','modalEdit','modalReset','modalDelete','modalImport','modalNonaktif','modalAktifkan',
       'modalBulkNonaktif','modalBulkAktif','modalBulkHapus','modalNonaktifPengguna','modalAktifkanPengguna'].forEach(id => closeModal(id));
  });

  function openAddModal() {
    openModal('modalAdd');
    setTimeout(() => document.getElementById('addNama').focus(), 80);
  }

  function openEditModal(data) {
    document.getElementById('editIdLama').value  = data.id;
    document.getElementById('editId').value      = data.id;
    document.getElementById('editNama').value    = data.nama;
    document.getElementById('editEmail').value   = data.email || '';
    document.getElementById('editUsername').value= data.username;
    document.getElementById('editRole').value    = data.role;
    openModal('modalEdit');
    setTimeout(() => document.getElementById('editNama').focus(), 80);
  }

  function openResetModal(id, nama) {
    document.getElementById('resetId').value            = id;
    document.getElementById('resetNama').textContent    = nama;
    document.getElementById('resetPassword').value      = '';
    document.getElementById('resetBar').style.width     = '0';
    document.getElementById('resetBar').style.background= '';
    document.getElementById('resetHint').textContent    = '';
    openModal('modalReset');
    setTimeout(() => document.getElementById('resetPassword').focus(), 80);
  }

  function openDeleteModal(id, nama) {
    document.getElementById('deleteId').value         = id;
    document.getElementById('deleteNama').textContent = nama;
    openModal('modalDelete');
  }

  function openNonaktifModal(id, nama) {
    document.getElementById('nonaktifId').value             = id;
    document.getElementById('nonaktifNama').textContent     = nama;
    document.getElementById('nonaktifNamaLabel').textContent= nama;
    document.getElementById('nonaktifKonfirm').value        = '';
    const err = document.getElementById('nonaktifError');
    err.classList.remove('show'); err.textContent = '';
    openModal('modalNonaktif');
    setTimeout(() => document.getElementById('nonaktifKonfirm').focus(), 80);
  }

  function openAktifkanModal(id, nama) {
    document.getElementById('aktifkanId').value          = id;
    document.getElementById('aktifkanNama').textContent  = nama;
    openModal('modalAktifkan');
  }

  function validateNonaktif() {
    const val = document.getElementById('nonaktifKonfirm').value.trim();
    const err = document.getElementById('nonaktifError');
    if (!val) {
      err.textContent = 'Ketik nama admin untuk konfirmasi.';
      err.classList.add('show');
      return false;
    }
    err.classList.remove('show');
    return true;
  }

  /* ── Validation ── */
  function validateForm(namaId, usernameId, passwordId, errId) {
    const nama     = document.getElementById(namaId).value.trim();
    const username = document.getElementById(usernameId).value.trim();
    const password = document.getElementById(passwordId).value.trim();
    const err      = document.getElementById(errId);
    if (!nama)     { err.textContent='Nama lengkap tidak boleh kosong.'; err.classList.add('show'); return false; }
    if (!username) { err.textContent='Username tidak boleh kosong.'; err.classList.add('show'); return false; }
    if (!password) { err.textContent='Password tidak boleh kosong.'; err.classList.add('show'); return false; }
    if (password.length < 6) { err.textContent='Password minimal 6 karakter.'; err.classList.add('show'); return false; }
    err.classList.remove('show'); return true;
  }
  function validateEditForm(namaId, usernameId, errId) {
    const nama     = document.getElementById(namaId).value.trim();
    const username = document.getElementById(usernameId).value.trim();
    const err      = document.getElementById(errId);
    if (!nama)     { err.textContent='Nama lengkap tidak boleh kosong.'; err.classList.add('show'); return false; }
    if (!username) { err.textContent='Username tidak boleh kosong.'; err.classList.add('show'); return false; }
    err.classList.remove('show'); return true;
  }
  function validateReset(passId, errId) {
    const pass = document.getElementById(passId).value.trim();
    const err  = document.getElementById(errId);
    if (!pass)          { err.textContent='Password baru tidak boleh kosong.'; err.classList.add('show'); return false; }
    if (pass.length < 6){ err.textContent='Password minimal 6 karakter.'; err.classList.add('show'); return false; }
    err.classList.remove('show'); return true;
  }

  /* ── Password strength ── */
  function checkStrength(input, barId, hintId) {
    const val  = input.value;
    const bar  = document.getElementById(barId);
    const hint = document.getElementById(hintId);
    let score  = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
      { w:'0%',   color:'',         text:'' },
      { w:'20%',  color:'#DC2626',  text:'Sangat lemah' },
      { w:'40%',  color:'#EA580C',  text:'Lemah' },
      { w:'60%',  color:'#D97706',  text:'Sedang' },
      { w:'80%',  color:'#16A34A',  text:'Kuat' },
      { w:'100%', color:'#15803D',  text:'Sangat kuat' },
    ];
    const lv = levels[Math.min(score, 5)];
    bar.style.width      = val.length ? lv.w : '0';
    bar.style.background = lv.color;
    hint.textContent     = val.length ? lv.text : '';
    hint.style.color     = lv.color;
  }

  initTableControls('searchFormPengguna','searchInputPengguna');

  /* ── CSV filename display ── */
  function showFileName(input) {
    const el = document.getElementById('csvFileName');
    if (input.files && input.files[0]) {
      el.innerHTML = '<i class="bi bi-file-earmark-excel-fill" style="color:#107C41;"></i> ' + input.files[0].name;
      el.style.display = 'block';
    } else {
      el.style.display = 'none';
    }
  }

  /* ── BFCache Fix: paksa reload jika halaman dari back-forward cache ── */
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
  });
  function openNonaktifkanPenggunaModal(id, nama) {
    document.getElementById('nonaktifPenggunaId').value         = id;
    document.getElementById('nonaktifPenggunaNama').textContent = nama;
    openModal('modalNonaktifPengguna');
  }

  function openAktifkanPenggunaModal(id, nama) {
    document.getElementById('aktifkanPenggunaId').value         = id;
    document.getElementById('aktifkanPenggunaNama').textContent = nama;
    openModal('modalAktifkanPengguna');
  }

  /* ── BULK ACTION ── */
  // Deduplikasi berdasarkan value: desktop table + mobile list keduanya
  // render checkbox dengan class yang sama untuk user yang sama,
  // sehingga harus difilter agar setiap ID hanya dihitung sekali.
  function getChecked() {
    const seen = new Set();
    return Array.from(document.querySelectorAll('.bulk-cb:checked')).filter(cb => {
      if (seen.has(cb.value)) return false;
      seen.add(cb.value);
      return true;
    });
  }

  // Kembalikan semua checkbox yang TERLIHAT (offsetParent !== null)
  // agar select-all tidak menghitung duplikat hidden dari mobile/desktop
  function getVisibleCbs() {
    return Array.from(document.querySelectorAll('.bulk-cb')).filter(cb => cb.offsetParent !== null);
  }

  function updateBulkBar() {
    const checked = getChecked(); // unique by value
    const bar = document.getElementById('bulkBar');
    const countEl = document.getElementById('bulkCount');
    countEl.textContent = checked.length;
    if (checked.length > 0) {
      bar.classList.add('show');
    } else {
      bar.classList.remove('show');
    }
    // Sync select-all berdasarkan checkbox yang visible saja
    const visibleCbs = getVisibleCbs();
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
      checkAll.indeterminate = (checked.length > 0 && checked.length < visibleCbs.length);
      checkAll.checked = (visibleCbs.length > 0 && checked.length === visibleCbs.length);
    }

    // Tentukan tombol yang tampil berdasarkan status pengguna yang dicentang
    const btnNonaktif = document.getElementById('btnBulkNonaktif');
    const btnAktif    = document.getElementById('btnBulkAktif');

    if (checked.length > 0) {
      const statuses = checked.map(cb => cb.getAttribute('data-status') || 'aktif');
      const allAktif    = statuses.every(s => s === 'aktif');
      const allNonaktif = statuses.every(s => s === 'nonaktif');

      if (allAktif) {
        // Semua aktif → tampilkan Nonaktifkan, sembunyikan Aktifkan
        btnNonaktif.style.display = '';
        btnAktif.style.display    = 'none';
      } else if (allNonaktif) {
        // Semua nonaktif → tampilkan Aktifkan, sembunyikan Nonaktifkan
        btnNonaktif.style.display = 'none';
        btnAktif.style.display    = '';
      } else {
        // Campuran → sembunyikan keduanya, hanya tampilkan Hapus
        btnNonaktif.style.display = 'none';
        btnAktif.style.display    = 'none';
      }
    } else {
      btnNonaktif.style.display = 'none';
      btnAktif.style.display    = 'none';
    }
  }

  // Toggle hanya checkbox yang visible (desktop atau mobile, bukan keduanya)
  function toggleAll(masterCb) {
    getVisibleCbs().forEach(cb => cb.checked = masterCb.checked);
    updateBulkBar();
  }

  function clearAllChecks() {
    document.querySelectorAll('.bulk-cb').forEach(cb => cb.checked = false);
    const checkAll = document.getElementById('checkAll');
    if (checkAll) { checkAll.checked = false; checkAll.indeterminate = false; }
    document.getElementById('bulkBar').classList.remove('show');
  }

  function buildHiddenInputs(containerId) {
    const checked = getChecked();
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    checked.forEach(cb => {
      const inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'bulk_ids[]'; inp.value = cb.value;
      container.appendChild(inp);
    });
    return checked.length;
  }

  function openBulkAktifModal() {
    const n = buildHiddenInputs('bulkAktifInputs');
    document.getElementById('bulkAktifCount').textContent = n;
    openModal('modalBulkAktif');
  }

  function openBulkNonaktifModal() {
    const n = buildHiddenInputs('bulkNonaktifInputs');
    document.getElementById('bulkNonaktifCount').textContent = n;
    openModal('modalBulkNonaktif');
  }

  function openBulkHapusModal() {
    const n = buildHiddenInputs('bulkHapusInputs');
    document.getElementById('bulkHapusCount').textContent = n;
    openModal('modalBulkHapus');
  }
</script>

<!-- ══ MODAL NONAKTIFKAN PENGGUNA ══ -->
<div class="modal-backdrop" id="modalNonaktifPengguna" onclick="handleBackdropClick(event,'modalNonaktifPengguna')">
  <div class="modal-box modal-box-sm">
    <button class="modal-close" onclick="closeModal('modalNonaktifPengguna')"><i class="bi bi-x-lg"></i></button>
    <div style="text-align:center;margin-bottom:18px;">
      <div style="width:52px;height:52px;border-radius:14px;background:#FEF2F2;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="bi bi-person-slash" style="font-size:24px;color:#DC2626;"></i>
      </div>
      <div class="modal-title">Nonaktifkan Pengguna</div>
      <div class="modal-sub">Pengguna <strong id="nonaktifPenggunaNama"></strong> tidak akan bisa login sampai diaktifkan kembali.</div>
    </div>
    <form method="POST">
      <input type="hidden" name="id_pengguna" id="nonaktifPenggunaId">
      <button type="submit" name="nonaktifkan_pengguna" class="btn-modal-danger">
        <i class="bi bi-person-slash"></i> Ya, Nonaktifkan
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalNonaktifPengguna')">Batal</button>
    </form>
  </div>
</div>

<!-- ══ MODAL AKTIFKAN KEMBALI PENGGUNA ══ -->
<div class="modal-backdrop" id="modalAktifkanPengguna" onclick="handleBackdropClick(event,'modalAktifkanPengguna')">
  <div class="modal-box modal-box-sm">
    <button class="modal-close" onclick="closeModal('modalAktifkanPengguna')"><i class="bi bi-x-lg"></i></button>
    <div style="text-align:center;margin-bottom:18px;">
      <div style="width:52px;height:52px;border-radius:14px;background:#F0FDF4;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
        <i class="bi bi-person-check" style="font-size:24px;color:#16A34A;"></i>
      </div>
      <div class="modal-title">Aktifkan Pengguna</div>
      <div class="modal-sub">Pengguna <strong id="aktifkanPenggunaNama"></strong> akan dapat login kembali.</div>
    </div>
    <form method="POST">
      <input type="hidden" name="id_pengguna" id="aktifkanPenggunaId">
      <button type="submit" name="aktifkan_pengguna" class="btn-modal-submit">
        <i class="bi bi-person-check"></i> Ya, Aktifkan Kembali
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalAktifkanPengguna')">Batal</button>
    </form>
  </div>
</div>

</body>
</html>