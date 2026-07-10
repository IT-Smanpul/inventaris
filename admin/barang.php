<?php
session_start();
require_once "../config/koneksi.php";
require_once "../config/auth_admin.php";
/** @var mysqli $conn  —  defined in koneksi.php */

/* ── Filter ruangan dari ruangan.php ── */
$id_ruangan   = isset($_GET['ruangan']) ? (int)$_GET['ruangan'] : 0;
$nama_ruangan = '';

if ($id_ruangan) {
  $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_ruangan FROM ruangan WHERE id_ruangan=$id_ruangan"));
  $nama_ruangan = $r['nama_ruangan'] ?? '';
}
if (empty($nama_ruangan) && isset($_GET['nama'])) {
  $nama_ruangan = htmlspecialchars($_GET['nama']);
}

/* ── Daftar ruangan untuk dropdown ── */
$ruangan_all = [];
$rq = mysqli_query($conn, "SELECT id_ruangan, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC");
while ($rv = mysqli_fetch_assoc($rq)) $ruangan_all[] = $rv;

/* POST HANDLER*/
$msg_success = '';
$msg_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ── Helper upload foto ── */
    function uploadFoto($file_input, $existing = '') {
        if (empty($file_input['name'])) return $existing;
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($file_input['type'], $allowed)) return $existing;
        $ext  = pathinfo($file_input['name'], PATHINFO_EXTENSION);
        $name = 'barang_' . time() . '_' . rand(100,999) . '.' . strtolower($ext);
        $dest = __DIR__ . '/../assets/foto_barang/' . $name;
        if (move_uploaded_file($file_input['tmp_name'], $dest)) {
          if ($existing && file_exists(__DIR__ . '/../assets/foto_barang/' . $existing)) {
            unlink(__DIR__ . '/../assets/foto_barang/' . $existing);
          }
          return $name;
        }
        return $existing;
    }
    
    /* ── TAMBAH ── */
    if (isset($_POST['tambah_barang'])) {
        $kode            = trim(mysqli_real_escape_string($conn, $_POST['kode_barang']));
        $nama            = trim(mysqli_real_escape_string($conn, $_POST['nama_barang']));
        $id_r            = (int)$_POST['id_ruangan_barang'];
        $jumlah_laik     = max(0, (int)$_POST['jumlah_laik']);
        $jumlah_tdk_laik = max(0, (int)$_POST['jumlah_tidak_laik']);
        $jumlah          = $jumlah_laik + $jumlah_tdk_laik;
        $deskripsi       = trim(mysqli_real_escape_string($conn, $_POST['deskripsi'] ?? ''));
        $spesifikasi     = trim(mysqli_real_escape_string($conn, $_POST['spesifikasi'] ?? ''));
        $sumber_dana     = trim(mysqli_real_escape_string($conn, $_POST['sumber_dana'] ?? ''));
        $tgl_beli        = trim($_POST['tanggal_pembelian'] ?? '');
        $bisa_dipinjam   = isset($_POST['bisa_dipinjam']) ? 1 : 0;
        $pinjam_murid   = isset($_POST['pinjam_murid'])   ? 1 : 0;
        $pinjam_guru    = isset($_POST['pinjam_guru'])    ? 1 : 0;
        $pinjam_tendik  = isset($_POST['pinjam_tendik'])  ? 1 : 0;
        $durasi_murid   = ($bisa_dipinjam && $pinjam_murid  && !empty($_POST['durasi_murid']))  ? max(1,(int)$_POST['durasi_murid'])  : 'NULL';
        $durasi_guru    = ($bisa_dipinjam && $pinjam_guru   && !empty($_POST['durasi_guru']))   ? max(1,(int)$_POST['durasi_guru'])   : 'NULL';
        $durasi_tendik  = ($bisa_dipinjam && $pinjam_tendik && !empty($_POST['durasi_tendik'])) ? max(1,(int)$_POST['durasi_tendik']) : 'NULL';
        $foto_file       = isset($_FILES['foto']) ? $_FILES['foto'] : [];

        if (empty($nama)) {
            $msg_error = "Nama sarana tidak boleh kosong.";
        } elseif (!$id_r) {
            $msg_error = "Letak / Prasarana wajib dipilih.";
        } elseif ($kode && mysqli_fetch_assoc(mysqli_query($conn, "SELECT id_barang FROM barang WHERE kode_barang='$kode' LIMIT 1"))) {
            $msg_error = "Kode sarana &ldquo;$kode&rdquo; sudah digunakan. Gunakan kode yang berbeda.";
        } else {
            $foto_name   = !empty($foto_file['name']) ? uploadFoto($foto_file) : '';
            $id_r_sql    = $id_r ? $id_r : 'NULL';
            $kode_sql    = $kode        ? "'$kode'"        : 'NULL';
            $spek_sql    = $spesifikasi ? "'$spesifikasi'" : 'NULL';
            $desk_sql    = $deskripsi   ? "'$deskripsi'"   : 'NULL';
            $foto_sql    = $foto_name   ? "'$foto_name'"   : 'NULL';
            $sdana_sql   = $sumber_dana ? "'$sumber_dana'" : 'NULL';
            $tglbeli_sql = $tgl_beli    ? "'$tgl_beli'"    : 'NULL';
            mysqli_query($conn, "
                INSERT INTO barang
                  (kode_barang, nama_barang, id_ruangan,
                   jumlah, jumlah_laik, jumlah_tidak_laik,
                   deskripsi, spesifikasi, bisa_dipinjam,
                   pinjam_murid, pinjam_guru, pinjam_tendik,
                   durasi_murid, durasi_guru, durasi_tendik,
                   foto, sumber_dana, tanggal_pembelian)
                VALUES
                  ($kode_sql,'$nama',$id_r_sql,
                   $jumlah,$jumlah_laik,$jumlah_tdk_laik,
                   $desk_sql,$spek_sql,$bisa_dipinjam,
                   $pinjam_murid,$pinjam_guru,$pinjam_tendik,
                   $durasi_murid,$durasi_guru,$durasi_tendik,
                   $foto_sql,$sdana_sql,$tglbeli_sql)
            ");
            $back = "barang.php" . ($id_ruangan ? "?ruangan=$id_ruangan&nama=".urlencode($nama_ruangan)."&" : '?');
            header("Location: {$back}success=".urlencode("Barang \"$nama\" berhasil ditambahkan."));
            exit;
        }
    }

    /* ── EDIT ── */
    if (isset($_POST['edit_barang'])) {
        $id_b            = (int)$_POST['id_barang'];
        $kode            = trim(mysqli_real_escape_string($conn, $_POST['kode_barang']));
        $nama            = trim(mysqli_real_escape_string($conn, $_POST['nama_barang']));
        $id_r            = (int)$_POST['id_ruangan_barang'];
        $jumlah_laik     = max(0, (int)$_POST['jumlah_laik']);
        $jumlah_tdk_laik = max(0, (int)$_POST['jumlah_tidak_laik']);
        $jumlah          = $jumlah_laik + $jumlah_tdk_laik;
        $deskripsi       = trim(mysqli_real_escape_string($conn, $_POST['deskripsi'] ?? ''));
        $spesifikasi     = trim(mysqli_real_escape_string($conn, $_POST['spesifikasi'] ?? ''));
        $sumber_dana     = trim(mysqli_real_escape_string($conn, $_POST['sumber_dana'] ?? ''));
        $tgl_beli        = trim($_POST['tanggal_pembelian'] ?? '');
        $bisa_dipinjam   = isset($_POST['bisa_dipinjam']) ? 1 : 0;
        $pinjam_murid   = isset($_POST['pinjam_murid'])   ? 1 : 0;
        $pinjam_guru    = isset($_POST['pinjam_guru'])    ? 1 : 0;
        $pinjam_tendik  = isset($_POST['pinjam_tendik'])  ? 1 : 0;
        $durasi_murid   = ($bisa_dipinjam && $pinjam_murid  && !empty($_POST['durasi_murid']))  ? max(1,(int)$_POST['durasi_murid'])  : 'NULL';
        $durasi_guru    = ($bisa_dipinjam && $pinjam_guru   && !empty($_POST['durasi_guru']))   ? max(1,(int)$_POST['durasi_guru'])   : 'NULL';
        $durasi_tendik  = ($bisa_dipinjam && $pinjam_tendik && !empty($_POST['durasi_tendik'])) ? max(1,(int)$_POST['durasi_tendik']) : 'NULL';
        $foto_file       = isset($_FILES['foto']) ? $_FILES['foto'] : [];
        $foto_existing   = trim(mysqli_real_escape_string($conn, $_POST['foto_existing'] ?? ''));

        if (empty($nama)) {
            $msg_error = "Nama sarana tidak boleh kosong.";
        } elseif (!$id_r) {
            $msg_error = "Letak / Prasarana wajib dipilih.";
        } elseif ($kode && mysqli_fetch_assoc(mysqli_query($conn, "SELECT id_barang FROM barang WHERE kode_barang='$kode' AND id_barang != $id_b LIMIT 1"))) {
            $msg_error = "Kode sarana &ldquo;$kode&rdquo; sudah digunakan oleh sarana lain. Gunakan kode yang berbeda.";
        } else {
            $foto_name   = !empty($foto_file['name']) ? uploadFoto($foto_file, $foto_existing) : $foto_existing;
            $id_r_sql    = $id_r ? $id_r : 'NULL';
            $kode_sql    = $kode        ? "'$kode'"        : 'NULL';
            $spek_sql    = $spesifikasi ? "'$spesifikasi'" : 'NULL';
            $desk_sql    = $deskripsi   ? "'$deskripsi'"   : 'NULL';
            $foto_sql    = $foto_name   ? "'$foto_name'"   : 'NULL';
            $sdana_sql   = $sumber_dana ? "'$sumber_dana'" : 'NULL';
            $tglbeli_sql = $tgl_beli    ? "'$tgl_beli'"    : 'NULL';
            mysqli_query($conn, "
                UPDATE barang
                SET kode_barang=$kode_sql, nama_barang='$nama',
                    id_ruangan=$id_r_sql,
                    jumlah=$jumlah, jumlah_laik=$jumlah_laik,
                    jumlah_tidak_laik=$jumlah_tdk_laik,
                    deskripsi=$desk_sql, spesifikasi=$spek_sql,
                    bisa_dipinjam=$bisa_dipinjam,
                    pinjam_murid=$pinjam_murid, pinjam_guru=$pinjam_guru,
                    pinjam_tendik=$pinjam_tendik,
                    durasi_murid=$durasi_murid, durasi_guru=$durasi_guru,
                    durasi_tendik=$durasi_tendik,
                    foto=$foto_sql, sumber_dana=$sdana_sql,
                    tanggal_pembelian=$tglbeli_sql
                WHERE id_barang=$id_b
            ");
            $back = "barang.php" . ($id_ruangan ? "?ruangan=$id_ruangan&nama=".urlencode($nama_ruangan)."&" : '?');
            header("Location: {$back}success=".urlencode("Barang berhasil diperbarui."));
            exit;
        }
    }

    /* ── HAPUS ── */
    if (isset($_POST['hapus_barang'])) {
        $id_b = (int)$_POST['id_barang'];
        $cek_aktif = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) as t
             FROM detail_peminjaman dp
             JOIN peminjaman pm ON dp.id_peminjaman = pm.id_peminjaman
             WHERE dp.id_barang = $id_b
               AND pm.status IN ('menunggu','dipinjam')"));
        if ($cek_aktif['t'] > 0) {
            $msg_error = "Sarana tidak bisa dihapus karena masih ada peminjaman aktif yang menggunakan barang ini.";
        } else {
            $pm_ids_res = mysqli_query($conn,
                "SELECT DISTINCT dp.id_peminjaman
                 FROM detail_peminjaman dp
                 JOIN peminjaman pm ON dp.id_peminjaman = pm.id_peminjaman
                 WHERE dp.id_barang = $id_b
                   AND pm.status NOT IN ('menunggu','dipinjam')");
            $pm_ids = [];
            while ($row = mysqli_fetch_assoc($pm_ids_res)) {
                $pm_ids[] = (int)$row['id_peminjaman'];
            }
            mysqli_query($conn, "DELETE FROM detail_peminjaman WHERE id_barang = $id_b");

            if (!empty($pm_ids)) {
                $pm_ids_str = implode(',', $pm_ids);
                mysqli_query($conn,
                    "DELETE FROM peminjaman
                     WHERE id_peminjaman IN ($pm_ids_str)
                       AND id_peminjaman NOT IN (
                           SELECT id_peminjaman FROM detail_peminjaman
                       )");
            }
            mysqli_query($conn, "DELETE FROM barang WHERE id_barang=$id_b");
            $back = "barang.php" . ($id_ruangan ? "?ruangan=$id_ruangan&nama=".urlencode($nama_ruangan)."&" : '?');
            header("Location: {$back}success=".urlencode("Barang berhasil dihapus."));
            exit;
        }
    }

    /* ── IMPORT EXCEL BARANG ── */
    if (isset($_POST['import_barang_excel'])) {
        if (!isset($_FILES['excel_barang']) || $_FILES['excel_barang']['error'] !== UPLOAD_ERR_OK) {
            $msg_error = "Gagal upload file. Pastikan file Excel dipilih dengan benar.";
        } else {
            $ext = strtolower(pathinfo($_FILES['excel_barang']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx','xls','csv'])) {
                $msg_error = "Format file harus Excel (.xlsx / .xls) atau CSV.";
            } else {
                $rows    = [];
                $tmpPath = $_FILES['excel_barang']['tmp_name'];

                if ($ext === 'csv') {
                    $handle = fopen($tmpPath, 'r');
                    $r = 0;
                    while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                        $r++;
                        if ($r === 1) continue;
                        $rows[] = $row;
                    }
                    fclose($handle);
                } else {
                    $autoloads = [
                        __DIR__ . '/../vendor/autoload.php',
                        __DIR__ . '/../../vendor/autoload.php',
                    ];
                    $loaded = false;
                    foreach ($autoloads as $al) {
                        if (file_exists($al)) { require_once $al; $loaded = true; break; }
                    }
                    if (!$loaded) {
                        $msg_error = "PhpSpreadsheet belum terinstall. Jalankan: <code>composer require phpoffice/phpspreadsheet</code> atau gunakan format CSV.";
                    } else {
                        try {
                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
                            $sheet       = $spreadsheet->getActiveSheet();
                            $firstRow    = true;
                            foreach ($sheet->getRowIterator() as $sheetRow) {
                                if ($firstRow) { $firstRow = false; continue; }
                                $cells = [];
                                foreach ($sheetRow->getCellIterator('A', 'K') as $cell) {
                                    $cells[] = trim((string)$cell->getValue());
                                }
                                if (count(array_filter($cells)) > 0) $rows[] = $cells;
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
                        $kode       = trim(mysqli_real_escape_string($conn, $data[0] ?? ''));
                        $nama       = trim(mysqli_real_escape_string($conn, $data[1] ?? ''));
                        $nama_r     = trim(mysqli_real_escape_string($conn, $data[2] ?? ''));
                        $laik       = max(0, (int)($data[4] ?? 0));
                        $tdk_laik   = max(0, (int)($data[5] ?? 0));
                        $jumlah     = $laik + $tdk_laik;
                        $bisa_pinjam= in_array(strtolower(trim($data[6] ?? 'ya')), ['ya','yes','1','true']) ? 1 : 0;
                        $sumber_dana= trim(mysqli_real_escape_string($conn, $data[7] ?? ''));
                        $tgl_beli   = trim($data[8] ?? '');
                        $deskripsi  = trim(mysqli_real_escape_string($conn, $data[9] ?? ''));
                        $spesifikasi= trim(mysqli_real_escape_string($conn, $data[10] ?? ''));

                        if (empty($nama)) { $skip++; $errors[] = "Baris $rowNum: Nama barang kosong."; continue; }
                        if (empty($nama_r)) { $skip++; $errors[] = "Baris $rowNum: Data ruangan kosong."; continue; }

                        // Cek kode duplikat
                        if ($kode) {
                            $cek_kode = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id_barang FROM barang WHERE kode_barang='$kode' LIMIT 1"));
                            if ($cek_kode) { $skip++; $errors[] = "Baris $rowNum: Kode '$kode' sudah ada."; continue; }
                        }

                        // Resolve nama_ruangan -> id_ruangan
                        $id_r_sql = 'NULL';
                        if ($nama_r) {
                            $r_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id_ruangan FROM ruangan WHERE nama_ruangan='$nama_r' LIMIT 1"));
                            if ($r_row) $id_r_sql = (int)$r_row['id_ruangan'];
                        }

                        $kode_sql  = $kode        ? "'$kode'"         : 'NULL';
                        $spek_sql  = $spesifikasi ? "'$spesifikasi'"  : 'NULL';
                        $desk_sql  = $deskripsi   ? "'$deskripsi'"    : 'NULL';
                        $sdana_sql = $sumber_dana ? "'$sumber_dana'"  : 'NULL';
                        $tgl_sql   = ($tgl_beli && strtotime($tgl_beli)) ? "'" . date('Y-m-d', strtotime($tgl_beli)) . "'" : 'NULL';

                        mysqli_query($conn, "
                            INSERT INTO barang
                              (kode_barang, nama_barang, id_ruangan,
                               jumlah, jumlah_laik, jumlah_tidak_laik,
                               deskripsi, spesifikasi, bisa_dipinjam,
                               sumber_dana, tanggal_pembelian)
                            VALUES
                              ($kode_sql,'$nama',$id_r_sql,
                               $jumlah,$laik,$tdk_laik,
                               $desk_sql,$spek_sql,$bisa_pinjam,
                               $sdana_sql,$tgl_sql)
                        ");
                        $ok++;
                    }

                    $pesan = "$ok barang berhasil diimport" . ($skip ? ", $skip baris dilewati." : ".");
                    if (!empty($errors)) {
                        $msg_error = implode('<br>', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '<br>...dan lainnya.' : '');
                    }
                    if ($ok > 0) {
                        $back = "barang.php" . ($id_ruangan ? "?ruangan=$id_ruangan&nama=".urlencode($nama_ruangan)."&" : '?');
                        header("Location: {$back}success=".urlencode($pesan));
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
}

if (isset($_GET['success'])) $msg_success = htmlspecialchars($_GET['success']);

/* ── Search & Filter ── */
$search             = isset($_GET['q'])                  ? trim(mysqli_real_escape_string($conn, $_GET['q'])) : '';
$filter_ruangan     = isset($_GET['filter_ruangan'])     ? (int)$_GET['filter_ruangan']  : 0;
$filter_laik        = isset($_GET['filter_laik']) && in_array($_GET['filter_laik'],['laik','tidak_laik'])
                      ? $_GET['filter_laik'] : '';

/* ── Pagination ── */
$valid_per_page = [5,10,20,25,50,100];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'],$valid_per_page)
            ? (int)$_GET['per_page'] : 5;
$page = max(1, (int)($_GET['page'] ?? 1));

/* ── WHERE ── */
$where = "WHERE 1=1";
if ($id_ruangan)          $where .= " AND b.id_ruangan=$id_ruangan";
elseif ($filter_ruangan)  $where .= " AND b.id_ruangan=$filter_ruangan";
if ($filter_laik === 'laik')       $where .= " AND b.jumlah_laik > 0";
if ($filter_laik === 'tidak_laik') $where .= " AND b.jumlah_tidak_laik > 0";
if ($search)              $where .= " AND (b.nama_barang LIKE '%$search%' OR b.kode_barang LIKE '%$search%')";

/* ── Count ── */
$total       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM barang b $where"))['t'];
$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

/* ── Fetch ── */
$barang_q = mysqli_query($conn, "
    SELECT b.*, r.nama_ruangan
    FROM barang b
    LEFT JOIN ruangan r ON b.id_ruangan = r.id_ruangan
    $where
    ORDER BY b.nama_barang ASC
    LIMIT $per_page OFFSET $offset
");

/* ── Ringkasan total laik/tidak laik untuk header ── */
$sum_q = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(jumlah_laik),0) as total_laik,
            COALESCE(SUM(jumlah_tidak_laik),0) as total_tidak_laik
     FROM barang b $where"));
$total_laik      = (int)$sum_q['total_laik'];
$total_tidak_laik= (int)$sum_q['total_tidak_laik'];

$pending_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM pengguna WHERE status='pending'"))['t'];
$pm_menunggu   = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman WHERE status='menunggu'"))['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $nama_ruangan ? htmlspecialchars($nama_ruangan) : 'Semua Sarana' ?> — Inventaris SARPRAS</title>
  <link rel="icon" href="../assets/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{
      --blue:#4A90C4;--blue-dark:#2B6FA8;--blue-deep:#1B3F6E;
      --green:#3D9B4A;--yellow:#F5C518;
      --bg:#F0F7FF;--card:#FFFFFF;--text:#1B2D45;--muted:#6B7C93;
      --border:#D0E4F5;
      --shadow:0 2px 14px rgba(27,63,110,.09);
      --shadow-lg:0 8px 32px rgba(27,63,110,.15);
    }
    html,body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;}

    /* ── NAVBAR ── */
    .navbar{position:sticky;top:0;z-index:100;background:var(--blue-deep);display:flex;align-items:center;padding:0 28px;height:62px;box-shadow:0 2px 12px rgba(27,63,110,.25);}
    .nav-brand{display:flex;align-items:center;gap:11px;text-decoration:none;flex-shrink:0;margin-right:36px;}
    .nav-brand img{width:38px;height:38px;object-fit:contain;}
    .nav-brand-text strong{display:block;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:800;color:white;line-height:1.2;}
    .nav-brand-text span{font-size:10px;color:rgba(255,255,255,.5);}
    .nav-links{display:flex;align-items:center;gap:2px;flex:1;}
    .nav-link{padding:8px 13px;border-radius:8px;color:rgba(255,255,255,.65);text-decoration:none;font-size:13px;font-weight:500;transition:all .2s;white-space:nowrap;}
    .nav-link:hover{color:white;background:rgba(255,255,255,.1);}
    .nav-link.active{color:white;font-weight:700;border-bottom:2px solid var(--yellow);border-radius:0;padding-bottom:6px;}
    .nav-link.logout{margin-left:auto;color:rgba(255,255,255,.5);}
    .nav-link.logout:hover{color:#FCA5A5;background:rgba(239,68,68,.15);}
    .nav-badge{display:inline-flex;align-items:center;justify-content:center;background:#DC2626;color:white;width:17px;height:17px;border-radius:50%;font-size:10px;font-weight:800;margin-left:4px;vertical-align:middle;}
    .nav-hamburger{display:none;margin-left:auto;background:none;border:none;cursor:pointer;color:white;font-size:22px;padding:6px;border-radius:8px;}
    .nav-hamburger:hover{background:rgba(255,255,255,.1);}
    .nav-mobile-menu{display:none;position:fixed;top:62px;left:0;right:0;background:var(--blue-deep);box-shadow:0 8px 24px rgba(27,63,110,.3);z-index:99;flex-direction:column;padding:8px 16px 16px;border-top:1px solid rgba(255,255,255,.08);}
    .nav-mobile-menu.open{display:flex;}
    .nav-mobile-menu .nav-link{padding:13px 12px;border-radius:10px;font-size:14px;border-bottom:1px solid rgba(255,255,255,.06);}
    .nav-mobile-menu .nav-link:last-child{border-bottom:none;}
    .nav-mobile-menu .nav-link.logout{margin-left:0;margin-top:4px;}

    /* ── PAGE ── */
    .page-wrapper{max-width:1100px;margin:0 auto;padding:32px 24px 60px;flex:1;}
    .flash{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:9px;}
    .flash-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;}
    .flash-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;}
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;gap:16px;flex-wrap:wrap;}
    .back-link{display:inline-flex;align-items:center;gap:6px;color:var(--blue-dark);font-size:13px;font-weight:700;text-decoration:none;margin-bottom:8px;}
    .back-link:hover{color:var(--blue-deep);}
    .page-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:900;color:var(--text);}
    .page-sub{font-size:13px;color:var(--muted);margin-top:3px;}

    /* ── SUMMARY PILLS ── */
    .summary-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
    .summary-pill{display:flex;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--border);border-radius:12px;padding:10px 16px;flex:1;min-width:140px;transition:box-shadow .2s;}
    .summary-pill:hover{box-shadow:var(--shadow);}
    .summary-pill-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
    .summary-pill-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:20px;font-weight:900;line-height:1;}
    .summary-pill-lbl{font-size:11px;color:var(--muted);margin-top:2px;}
    .pill-laik   {background:#F0FDF4;} .pill-laik-icon{background:#DCFCE7;color:#16A34A;}
    .pill-tdk    {background:#FEF2F2;} .pill-tdk-icon {background:#FEE2E2;color:#DC2626;}
    .pill-total  {background:#EFF6FF;} .pill-total-icon{background:#DBEAFE;color:#2563EB;}

    /* ── TOOLBAR ── */
    .toolbar{display:flex;flex-direction:column;gap:8px;margin-bottom:20px;}
    .toolbar-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .search-wrap-inner{position:relative;flex:1;min-width:0;}
    .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:14px;pointer-events:none;}
    .search-input{width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border);border-right:none;border-radius:9px 0 0 9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);outline:none;transition:border-color .2s;}
    .search-input:focus{border-color:var(--blue);}
    .btn-search{padding:10px 16px;background:var(--blue-dark);color:white;border:none;border-radius:0 9px 9px 0;cursor:pointer;font-size:14px;height:42px;}
    .btn-search:hover{background:var(--blue-deep);}
    .filter-group{display:flex;flex-direction:column;gap:3px;flex-shrink:0;}
    .filter-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding-left:3px;}
    .filter-select{padding:0 30px 0 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);height:42px;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;cursor:pointer;outline:none;}
    .filter-select:focus{border-color:var(--blue);}
    .show-entries-inline{display:flex;align-items:flex-end;gap:6px;margin-left:auto;flex-shrink:0;}
    .show-entries-inline .sel{padding:0 28px 0 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);height:42px;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;outline:none;}
    .show-entries-inline .lbl{font-size:13px;color:var(--muted);font-weight:500;line-height:42px;}

    /* ── CARD & TABLE ── */
    .card{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;}
    .card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .card-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;color:var(--text);display:flex;align-items:center;gap:8px;}
    .card-title i{color:var(--blue);}
    .table-wrap{overflow-x:auto;}
    .inv-table{width:100%;border-collapse:collapse;font-size:13px;}
    .inv-table thead th{background:#F4F8FD;padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.5px;text-transform:uppercase;border-bottom:2px solid var(--border);white-space:nowrap;}
    .inv-table thead th.right{text-align:right;}
    .inv-table thead th.center{text-align:center;}
    .inv-table tbody tr{border-bottom:1px solid var(--border);transition:background .12s;}
    .inv-table tbody tr:last-child{border-bottom:none;}
    .inv-table tbody tr:hover{background:#F4F8FD;}
    .inv-table td{padding:12px 14px;color:var(--text);vertical-align:middle;}
    .inv-table td.right{text-align:right;}
    .inv-table td.center{text-align:center;}

    /* jumlah laik/tidak laik inline */
    .qty-cell{display:flex;flex-direction:column;gap:4px;min-width:100px;}
    .qty-row{display:flex;align-items:center;justify-content:space-between;gap:8px;}
    .qty-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
    .qty-lbl{font-size:11px;color:var(--muted);flex:1;}
    .qty-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:800;}
    .qty-total{font-size:11px;color:var(--muted);border-top:1px solid var(--border);padding-top:3px;margin-top:1px;}
    .qty-total strong{color:var(--text);}

    /* ── BUTTONS ── */
    .btn{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:'DM Sans',sans-serif;}
    .btn-sm{padding:6px 10px;font-size:12px;border-radius:7px;}
    .btn-primary{background:var(--blue-dark);color:white;box-shadow:0 4px 14px rgba(43,111,168,.25);}
    .btn-primary:hover{background:var(--blue-deep);}
    .btn-secondary{background:var(--card);color:var(--text);border:1px solid var(--border);}
    .btn-secondary:hover{background:var(--bg);}
    .btn-danger{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;}
    .btn-danger:hover{background:#DC2626;color:white;}

    /* ── PAGINATION ── */
    .table-footer{display:flex;align-items:center;justify-content:space-between;padding:13px 20px;border-top:1px solid var(--border);font-size:13px;color:var(--muted);flex-wrap:wrap;gap:10px;}
    .pag-btns{display:flex;align-items:center;gap:6px;}
    .pag-btn{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;background:var(--card);color:var(--text);text-decoration:none;transition:all .2s;}
    .pag-btn:hover:not(.disabled):not(.active){background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.active{background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.disabled{opacity:.35;cursor:not-allowed;pointer-events:none;}
    .pag-btn-text{padding:0 12px;width:auto;}

    /* ── EMPTY ── */
    .empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
    .empty-state i{font-size:40px;display:block;margin-bottom:12px;color:#B0C8E0;}
    .empty-state h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px;}

    /* ── MOBILE CARD LIST ── */
    .mobile-list{display:none;}
    .mobile-item{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:10px;box-shadow:var(--shadow);}
    .mobile-item-header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px;}
    .mobile-item-actions{display:flex;gap:6px;flex-shrink:0;}
    .mobile-item-meta{display:flex;flex-wrap:wrap;gap:7px;align-items:center;}
    .mobile-qty-row{display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;}
    .mobile-qty-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;}
    .chip-laik{background:#F0FDF4;color:#16A34A;}
    .chip-tdk{background:#FEF2F2;color:#DC2626;}
    .chip-total{background:#EFF6FF;color:#2563EB;}

    /* ── MODAL ── */
    .modal-backdrop{display:none;position:fixed;inset:0;z-index:500;background:rgba(27,63,110,.35);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
    .modal-backdrop.open{display:flex;}
    .modal-box{background:var(--card);border-radius:18px;padding:28px 32px;width:100%;max-width:540px;box-shadow:var(--shadow-lg);position:relative;z-index:501;animation:modalIn .22s cubic-bezier(.4,0,.2,1);max-height:92vh;overflow-y:auto;}
    @keyframes modalIn{from{transform:scale(.94) translateY(10px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
    .modal-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--text);margin-bottom:6px;}
    .modal-sub{font-size:13px;color:var(--muted);margin-bottom:20px;}
    .modal-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:8px;background:var(--bg);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px;transition:all .2s;}
    .modal-close:hover{background:#FECACA;color:#DC2626;}
    .modal-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;border-radius:8px;padding:9px 12px;font-size:12px;margin-bottom:14px;display:none;}
    .modal-error.show{display:block;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;}
    .form-group{margin-bottom:12px;}
    .form-label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;}
    .form-control{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
    .form-control:focus{border-color:var(--blue);background:white;box-shadow:0 0 0 3px rgba(74,144,196,.14);}
    .form-hint{font-size:11px;color:var(--muted);margin-top:4px;}
    .form-divider{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;margin:14px 0 10px;display:flex;align-items:center;gap:8px;}
    .form-divider::after{content:'';flex:1;height:1px;background:var(--border);}
    .qty-input-group{background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:12px;}
    .qty-input-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .qty-total-preview{display:flex;align-items:center;justify-content:space-between;padding:8px 0 0;border-top:1px dashed var(--border);margin-top:10px;font-size:13px;color:var(--muted);}
    .qty-total-preview strong{font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:900;color:var(--text);}
    .form-control-check{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;background:var(--bg);cursor:pointer;}
    .form-control-check input{width:16px;height:16px;cursor:pointer;accent-color:var(--blue-dark);}
    .form-control-check span{font-size:13px;color:var(--text);font-weight:500;}
    .btn-modal-submit{width:100%;padding:11px;background:var(--blue-dark);color:white;border:none;border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 20px rgba(43,111,168,.28);transition:all .2s;margin-top:6px;}
    .btn-modal-submit:hover{background:var(--blue-deep);}
    .btn-modal-danger{width:100%;padding:11px;background:#DC2626;color:white;border:none;border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:10px;}
    .btn-modal-danger:hover{background:#B91C1C;}
    .btn-modal-cancel{width:100%;padding:10px;background:var(--bg);color:var(--muted);border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:8px;}
    .btn-modal-cancel:hover{background:#E5E7EB;}

    /* ── UPLOAD AREA (import) ── */
    .upload-area-xl{border:2px dashed var(--border);border-radius:12px;background:var(--bg);padding:28px;text-align:center;cursor:pointer;transition:all .2s;position:relative;margin-bottom:14px;}
    .upload-area-xl:hover{border-color:var(--blue);background:#EFF6FF;}
    .upload-area-xl input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
    .upload-area-xl-icon{font-size:36px;color:#16A34A;opacity:.7;margin-bottom:8px;}
    .upload-area-xl-text strong{color:var(--blue-dark);display:block;font-size:13px;margin-bottom:3px;}
    .upload-area-xl-text{font-size:12px;color:var(--muted);line-height:1.6;}
    .upload-filename{font-size:12px;color:var(--blue-dark);font-weight:600;margin-top:8px;display:none;}
    .import-hint{background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:12px 14px;font-size:11.5px;color:#166534;margin-bottom:14px;line-height:1.9;}
    .import-hint code{background:#DCFCE7;padding:1px 6px;border-radius:4px;font-size:11px;}
    .import-hint strong{color:#15803D;}
    .col-table{width:100%;border-collapse:collapse;margin-top:8px;}
    .col-table th,.col-table td{padding:5px 8px;text-align:left;border-bottom:1px solid #BBF7D0;font-size:11px;}
    .col-table th{font-weight:700;color:#166534;background:#DCFCE7;}
    .col-table td{color:#374151;}

    footer{background:var(--blue-deep);color:rgba(255,255,255,.55);text-align:center;padding:20px;font-size:12px;}

    @media(max-width:768px){
      .navbar{position:relative;}.nav-links{display:none;}
      .nav-hamburger{display:flex;align-items:center;justify-content:center;}.nav-brand{margin-right:0;}
      .page-wrapper{padding:16px 12px 80px;}.page-title{font-size:20px;}
      .page-header{flex-direction:column;align-items:stretch;gap:10px;}.page-header .btn{justify-content:center;padding:12px;}
      .toolbar-row{flex-wrap:wrap;}
      .filter-group{flex:1;min-width:calc(50% - 4px);}.filter-group .filter-select{width:100%;}
      .table-wrap{display:none;}.mobile-list{display:block;}
      .card-header{padding:13px 14px;}
      .table-footer{flex-direction:column;align-items:flex-start;gap:10px;padding:12px 14px;}
      .pag-btns{width:100%;justify-content:center;}
      .modal-box{margin:10px;padding:20px 16px;border-radius:16px;max-width:100%;}
      .form-row,.qty-input-row{grid-template-columns:1fr;gap:0;}
      .summary-pill{min-width:120px;}
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <a href="dashboard.php" class="nav-brand">
    <img src="../assets/logo.png" alt="Logo">
    <div class="nav-brand-text">
      <strong>Inventaris SARPRAS</strong>
      <span>SMAN 10 Pontianak</span>
    </div>
  </a>
  <div class="nav-links">
    <a href="dashboard.php"    class="nav-link">Dashboard</a>
    <a href="ruangan.php"      class="nav-link">Prasarana</a>
    <a href="barang.php"       class="nav-link active">Sarana</a>
    <a href="pengguna.php"     class="nav-link">Pengguna<?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?></a>
    <a href="peminjaman.php"   class="nav-link">Peminjaman<?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?></a>
    <a href="pengembalian.php" class="nav-link">Pengembalian</a>
    <a href="../auth/logout.php" class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>
  <button class="nav-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()"><i class="bi bi-list" id="hamburgerIcon"></i></button>
</nav>
<div class="nav-mobile-menu" id="mobileMenu">
  <a href="dashboard.php"    class="nav-link">Dashboard</a>
  <a href="ruangan.php"      class="nav-link">Prasarana</a>
  <a href="barang.php"       class="nav-link active">Sarana</a>
  <a href="pengguna.php"     class="nav-link">Pengguna<?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?></a>
  <a href="peminjaman.php"   class="nav-link">Peminjaman<?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?></a>
  <a href="pengembalian.php" class="nav-link">Pengembalian</a>
  <a href="../auth/logout.php" class="nav-link logout">Logout</a>
</div>

<div class="page-wrapper">

  <?php if ($msg_success): ?><div class="flash flash-success"><i class="bi bi-check-circle-fill"></i> <?= $msg_success ?></div><?php endif; ?>
  <?php if ($msg_error):   ?><div class="flash flash-error"><i class="bi bi-exclamation-circle-fill"></i> <?= $msg_error ?></div><?php endif; ?>

  <div class="page-header">
    <div>
      <?php if ($id_ruangan): ?>
      <a href="ruangan.php" class="back-link"><i class="bi bi-arrow-left"></i> Kembali ke Prasarana</a>
      <?php endif; ?>
      <div class="page-title"><?= $nama_ruangan ? htmlspecialchars($nama_ruangan) : 'Semua Sarana' ?></div>
      <div class="page-sub">
        <?= $id_ruangan
          ? 'Menampilkan sarana di prasarana <strong>'.htmlspecialchars($nama_ruangan).'</strong>'
          : 'Daftar seluruh inventaris sarana SARPRAS' ?>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <button class="btn btn-secondary" onclick="openModal('modalImportBarang')">
        <i class="bi bi-file-earmark-excel"></i> Import Excel
      </button>
      <button class="btn btn-primary" onclick="openAddModal()">
        <i class="bi bi-plus-lg"></i> Tambah Sarana
      </button>
    </div>
  </div>

  <!-- Summary Pills: laik / tidak laik / total dari filter aktif -->
  <div class="summary-row">
    <div class="summary-pill pill-laik">
      <div class="summary-pill-icon pill-laik-icon"><i class="bi bi-check-circle-fill"></i></div>
      <div>
        <div class="summary-pill-val" style="color:#16A34A;"><?= number_format($total_laik) ?></div>
        <div class="summary-pill-lbl">Unit Laik</div>
      </div>
    </div>
    <div class="summary-pill pill-tdk">
      <div class="summary-pill-icon pill-tdk-icon"><i class="bi bi-x-circle-fill"></i></div>
      <div>
        <div class="summary-pill-val" style="color:#DC2626;"><?= number_format($total_tidak_laik) ?></div>
        <div class="summary-pill-lbl">Unit Tidak Laik</div>
      </div>
    </div>
    <div class="summary-pill pill-total">
      <div class="summary-pill-icon pill-total-icon"><i class="bi bi-layers-fill"></i></div>
      <div>
        <div class="summary-pill-val" style="color:#2563EB;"><?= number_format($total_laik + $total_tidak_laik) ?></div>
        <div class="summary-pill-lbl">Total Unit</div>
      </div>
    </div>
    <div class="summary-pill" style="background:var(--card);">
      <div class="summary-pill-icon" style="background:#EEF4FB;color:var(--blue-dark);"><i class="bi bi-box-seam"></i></div>
      <div>
        <div class="summary-pill-val" style="color:var(--text);"><?= number_format($total) ?></div>
        <div class="summary-pill-lbl">Jenis Sarana</div>
      </div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="toolbar-row">
      <form method="GET" id="searchFormBarang" style="display:flex;gap:0;flex:1;min-width:200px;">
        <?php if ($id_ruangan): ?>
          <input type="hidden" name="ruangan" value="<?= $id_ruangan ?>">
          <input type="hidden" name="nama" value="<?= htmlspecialchars($nama_ruangan) ?>">
        <?php endif; ?>
        <?php if ($filter_ruangan && !$id_ruangan): ?><input type="hidden" name="filter_ruangan" value="<?= $filter_ruangan ?>"><?php endif; ?>
        <?php if ($filter_laik): ?><input type="hidden" name="filter_laik" value="<?= $filter_laik ?>"><?php endif; ?>
        <?php if ($per_page!=5): ?><input type="hidden" name="per_page" value="<?= $per_page ?>"><?php endif; ?>
        <div class="search-wrap-inner">
          <i class="bi bi-search search-icon"></i>
          <input type="text" name="q" id="searchInputBarang" class="search-input"
                 placeholder="Cari nama atau kode sarana..."
                 value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-search"><i class="bi bi-search"></i></button>
      </form>
    </div>

    <?php if (!$id_ruangan): ?>
    <div class="toolbar-row">
      <form method="GET" id="filterFormBarang" style="display:contents;">
        <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
        <?php if ($per_page!=5): ?><input type="hidden" name="per_page" value="<?= $per_page ?>"><?php endif; ?>

        <div class="filter-group">
          <span class="filter-label">Prasarana</span>
          <select name="filter_ruangan" class="filter-select" onchange="this.form.submit()">
            <option value="">Semua Prasarana</option>
            <?php foreach ($ruangan_all as $rv): ?>
            <option value="<?= $rv['id_ruangan'] ?>" <?= $filter_ruangan==$rv['id_ruangan']?'selected':'' ?>>
              <?= htmlspecialchars($rv['nama_ruangan']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <span class="filter-label">Kondisi</span>
          <select name="filter_laik" class="filter-select" onchange="this.form.submit()">
            <option value="">Semua</option>
            <option value="laik"       <?= $filter_laik==='laik'?'selected':'' ?>>Ada yang Laik</option>
            <option value="tidak_laik" <?= $filter_laik==='tidak_laik'?'selected':'' ?>>Ada yang Tidak Laik</option>
          </select>
        </div>

        <?php if ($search || $filter_ruangan || $filter_laik): ?>
        <a href="?<?= $id_ruangan ? 'ruangan='.$id_ruangan.'&nama='.urlencode($nama_ruangan) : '' ?>"
           class="btn btn-secondary btn-sm" style="align-self:flex-end;height:42px;">
          <i class="bi bi-x"></i> Reset
        </a>
        <?php endif; ?>

        <div class="show-entries-inline">
          <span class="lbl">Tampilkan</span>
          <select class="sel" name="per_page" onchange="this.form.submit()">
            <option value="5"   <?= $per_page==5?'selected':'' ?>>5</option>
            <option value="10"  <?= $per_page==10?'selected':'' ?>>10</option>
            <option value="20"  <?= $per_page==20?'selected':'' ?>>20</option>
            <option value="25"  <?= $per_page==25?'selected':'' ?>>25</option>
            <option value="50"  <?= $per_page==50?'selected':'' ?>>50</option>
            <option value="100" <?= $per_page==100?'selected':'' ?>>100</option>
          </select>
          <span class="lbl">entri</span>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- TABEL -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-box-seam"></i>
        <?= $nama_ruangan ? htmlspecialchars($nama_ruangan) : 'Inventaris Sarana' ?>
      </div>
      <span style="font-size:12px;color:var(--muted);"><?= number_format($total) ?> Jenis Sarana</span>
    </div>

    <div class="table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th style="width:38px;">No</th>
            <th style="width:50px;">Foto</th>
            <th>Nama Sarana</th>
            <th style="width:115px;">Sumber &amp; Tanggal</th>
            <?php if (!$id_ruangan): ?><th style="width:110px;">Prasarana</th><?php endif; ?>
            <th class="center" style="width:52px;">Laik</th>
            <th class="center" style="width:62px;">Tidak Laik</th>
            <th class="center" style="width:52px;">Total</th>
            <th class="center" style="width:72px;">Dipinjam</th>
            <th class="center" style="width:82px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no = $offset + 1; $has = false;
          while ($b = mysqli_fetch_assoc($barang_q)):
            $has  = true;
            $laik = (int)($b['jumlah_laik']       ?? 0);
            $tdk  = (int)($b['jumlah_tidak_laik']  ?? 0);
            $tot  = $laik + $tdk;
            $b_js = htmlspecialchars(json_encode([
              'id'                => $b['id_barang'],
              'kode'              => $b['kode_barang'],
              'nama'              => $b['nama_barang'],
              'id_ruangan'        => $b['id_ruangan'],
              'deskripsi'         => $b['deskripsi']   ?? '',
              'spesifikasi'       => $b['spesifikasi']  ?? '',
              'jumlah_laik'       => $laik,
              'jumlah_tidak_laik' => $tdk,
              'bisa_dipinjam'     => $b['bisa_dipinjam'],
              'pinjam_murid'      => $b['pinjam_murid']  ?? 1,
              'pinjam_guru'       => $b['pinjam_guru']   ?? 1,
              'pinjam_tendik'     => $b['pinjam_tendik'] ?? 1,
              'durasi_murid'      => $b['durasi_murid']  ?? '',
              'durasi_guru'       => $b['durasi_guru']   ?? '',
              'durasi_tendik'     => $b['durasi_tendik'] ?? '',
              'foto'              => $b['foto'] ?? '',
              'sumber_dana'       => $b['sumber_dana'] ?? '',
              'tanggal_pembelian' => $b['tanggal_pembelian'] ?? '',
            ]), ENT_QUOTES);
          ?>
          <tr>
            <td style="color:var(--muted);font-size:12px;"><?= $no++ ?></td>
            <td>
              <?php if (!empty($b['foto'])): ?>
                <img src="../assets/foto_barang/<?= htmlspecialchars($b['foto']) ?>" alt="Foto"
                     style="width:46px;height:46px;object-fit:cover;border-radius:8px;border:1.5px solid var(--border);display:block;">
              <?php else: ?>
                <div style="width:46px;height:46px;border-radius:8px;background:#EEF4FB;border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--blue-dark);"><i class="bi bi-box-seam"></i></div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($b['nama_barang']) ?></div>
              <?php if ($b['kode_barang']): ?>
                <span class="code-tag"><?= htmlspecialchars($b['kode_barang']) ?></span>
              <?php endif; ?>
              <?php if (!empty($b['spesifikasi'])): ?>
                <div style="font-size:11px;color:var(--muted);margin-top:2px;"><?= htmlspecialchars(mb_strimwidth($b['spesifikasi'],0,50,'…')) ?></div>
              <?php endif; ?>
            </td>
            <!-- Sumber Dana + Tgl -->
            <td style="font-size:12px;">
              <?php if (!empty($b['sumber_dana'])): ?>
                <span class="badge" style="background:#EFF6FF;color:#2563EB;font-size:11px;"><i class="bi bi-bank" style="margin:0;"></i> <?= htmlspecialchars($b['sumber_dana']) ?></span>
              <?php endif; ?>
              <?php if (!empty($b['tanggal_pembelian'])): ?>
                <div style="color:var(--muted);margin-top:3px;"><?= date('d/m/Y', strtotime($b['tanggal_pembelian'])) ?></div>
              <?php elseif (empty($b['sumber_dana'])): ?>
                <span style="color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
            <?php if (!$id_ruangan): ?>
            <td style="font-size:12px;"><?= htmlspecialchars($b['nama_ruangan'] ?? '—') ?></td>
            <?php endif; ?>
            <td class="center">
              <span style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:14px;color:<?= $laik > 0 ? '#16A34A' : 'var(--muted)' ?>;"><?= $laik ?></span>
            </td>
            <td class="center">
              <span style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:14px;color:<?= $tdk > 0 ? '#DC2626' : 'var(--muted)' ?>;"><?= $tdk ?></span>
            </td>
            <td class="center">
              <span style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:14px;color:var(--text);"><?= $tot ?></span>
            </td>
            <td class="center">
              <?php if ($b['bisa_dipinjam']): ?>
                <div style="display:flex;flex-direction:column;gap:3px;align-items:center;">
                  <?php if ($b['pinjam_murid']  ?? 1): ?><span class="badge badge-green"  style="font-size:10px;">Murid</span><?php endif; ?>
                  <?php if ($b['pinjam_guru']   ?? 1): ?><span class="badge badge-green"  style="font-size:10px;">Guru</span><?php endif; ?>
                  <?php if ($b['pinjam_tendik'] ?? 1): ?><span class="badge badge-green"  style="font-size:10px;">Tendik</span><?php endif; ?>
                </div>
              <?php else: ?>
                <span class="badge badge-gray">Tidak</span>
              <?php endif; ?>
            </td>
            <td class="center">
              <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?= $b_js ?>)" style="margin-right:4px;"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= $b['id_barang'] ?>,'<?= htmlspecialchars(addslashes($b['nama_barang'])) ?>')"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$has): ?>
          <tr><td colspan="<?= $id_ruangan ? 10 : 11 ?>">
            <div class="empty-state">
              <i class="bi bi-inbox"></i>
              <h3><?= $search ? 'Tidak ditemukan' : 'Belum ada sarana' ?></h3>
              <p><?= $search ? 'Tidak ada sarana yang cocok dengan "'.htmlspecialchars($search).'"' : ($id_ruangan ? 'Prasarana ini belum memiliki sarana.' : 'Belum ada data sarana.') ?></p>
            </div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile Card List -->
    <div class="mobile-list">
      <?php
      $q2 = mysqli_query($conn,"SELECT b.*,r.nama_ruangan FROM barang b LEFT JOIN ruangan r ON b.id_ruangan=r.id_ruangan $where ORDER BY b.nama_barang ASC LIMIT $per_page OFFSET $offset");
      while ($b2 = mysqli_fetch_assoc($q2)):
        $laik2 = (int)($b2['jumlah_laik'] ?? 0);
        $tdk2  = (int)($b2['jumlah_tidak_laik'] ?? 0);
        $tot2  = $laik2 + $tdk2;
        $b2_js = htmlspecialchars(json_encode([
          'id'              => $b2['id_barang'],
          'kode'            => $b2['kode_barang'],
          'nama'            => $b2['nama_barang'],
          'id_ruangan'      => $b2['id_ruangan'],
          'deskripsi'       => $b2['deskripsi']   ?? '',
          'spesifikasi'     => $b2['spesifikasi']  ?? '',
          'jumlah_laik'     => $laik2,
          'jumlah_tidak_laik' => $tdk2,
          'bisa_dipinjam'   => $b2['bisa_dipinjam'],
        ]), ENT_QUOTES);
      ?>
      <div class="mobile-item">
        <div class="mobile-item-header">
          <div>
            <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($b2['nama_barang']) ?></div>
            <?php if ($b2['kode_barang']): ?><span class="code-tag"><?= htmlspecialchars($b2['kode_barang']) ?></span><?php endif; ?>
          </div>
          <div class="mobile-item-actions">
            <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?= $b2_js ?>)"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= $b2['id_barang'] ?>,'<?= htmlspecialchars(addslashes($b2['nama_barang'])) ?>')"><i class="bi bi-trash"></i></button>
          </div>
        </div>
        <div class="mobile-item-meta">
          <?php if (!$id_ruangan && $b2['nama_ruangan']): ?>
            <span style="font-size:11px;color:var(--muted);"><i class="bi bi-building"></i> <?= htmlspecialchars($b2['nama_ruangan']) ?></span>
          <?php endif; ?>
        </div>
        <div class="mobile-qty-row">
          <span class="mobile-qty-chip chip-laik"><i class="bi bi-check-circle" style="font-size:11px;"></i> Laik: <?= $laik2 ?></span>
          <span class="mobile-qty-chip chip-tdk"><i class="bi bi-x-circle" style="font-size:11px;"></i> Tdk Laik: <?= $tdk2 ?></span>
          <span class="mobile-qty-chip chip-total"><i class="bi bi-layers" style="font-size:11px;"></i> Total: <?= $tot2 ?></span>
        </div>
        <?php if (!empty($b2['spesifikasi'])): ?>
          <div style="font-size:11px;color:var(--muted);margin-top:6px;font-style:italic;"><?= htmlspecialchars(mb_strimwidth($b2['spesifikasi'],0,70,'…')) ?></div>
        <?php endif; ?>
      </div>
      <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total > 0): ?>
    <div class="table-footer">
      <span>Menampilkan <?= $offset+1 ?>–<?= min($offset+$per_page,$total) ?> dari <?= number_format($total) ?> sarana</span>
      <div class="pag-btns">
        <?php
        $bu = '?' . ($id_ruangan ? "ruangan=$id_ruangan&nama=".urlencode($nama_ruangan)."&" : '')
            . ($filter_ruangan && !$id_ruangan ? "filter_ruangan=$filter_ruangan&" : '')
            . ($filter_laik ? "filter_laik=$filter_laik&" : '')
            . ($search ? "q=".urlencode($search)."&" : '')
            . ($per_page!=5 ? "per_page=$per_page&" : '');
        ?>
        <a href="<?= $bu ?>page=<?= $page-1 ?>" class="pag-btn pag-btn-text <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i> Prev</a>
        <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?><a href="<?= $bu ?>page=<?= $i ?>" class="pag-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
        <a href="<?= $bu ?>page=<?= $page+1 ?>" class="pag-btn pag-btn-text <?= $page>=$total_pages?'disabled':'' ?>">Next <i class="bi bi-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>
<footer>&copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak</footer>


<!-- MODAL TAMBAH -->
<div class="modal-backdrop" id="modalAdd" onclick="handleBackdropClick(event,'modalAdd')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalAdd')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Tambah Sarana</div>
    <div class="modal-sub">Isi data sarana sesuai format inventaris SARPRAS.</div>
    <div class="modal-error" id="addError"></div>
    <form method="POST" enctype="multipart/form-data" onsubmit="return validateBarang('addNama','addError','addRuangan')">
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Kode Sarana</label>
          <input type="text" name="kode_barang" class="form-control" placeholder="INV-001">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Nama Sarana <span style="color:#DC2626;">*</span></label>
          <input type="text" name="nama_barang" id="addNama" class="form-control" placeholder="Nama sarana" autocomplete="off">
        </div>
      </div>
      <div class="form-row" style="margin-top:12px;">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Letak / Prasarana <span style="color:#DC2626;">*</span></label>
          <select name="id_ruangan_barang" id="addRuangan" class="form-control" required>
            <option value="">— Pilih Ruangan —</option>
            <?php foreach ($ruangan_all as $rv): ?>
            <option value="<?= $rv['id_ruangan'] ?>" <?= $id_ruangan==$rv['id_ruangan']?'selected':'' ?>>
              <?= htmlspecialchars($rv['nama_ruangan']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-divider" style="margin-top:14px;">Jumlah</div>
      <div class="qty-input-group">
        <div class="qty-input-row">
          <div>
            <label class="form-label" style="color:#16A34A;">● Jumlah Laik</label>
            <input type="number" name="jumlah_laik" id="addLaik" class="form-control" value="0" min="0"
                   oninput="updateTotalPreview('addLaik','addTdkLaik','addTotalPreview')"
                   style="border-color:#BBF7D0;">
          </div>
          <div>
            <label class="form-label" style="color:#DC2626;">● Jumlah Tidak Laik</label>
            <input type="number" name="jumlah_tidak_laik" id="addTdkLaik" class="form-control" value="0" min="0"
                   oninput="updateTotalPreview('addLaik','addTdkLaik','addTotalPreview')"
                   style="border-color:#FECACA;">
          </div>
        </div>
        <div class="qty-total-preview">
          <span>Total unit</span>
          <strong id="addTotalPreview">0</strong>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Spesifikasi</label>
        <input type="text" name="spesifikasi" class="form-control" placeholder="Merk, ukuran, dll. (opsional)">
      </div>

      <div class="form-divider">Informasi Pengadaan</div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Sumber Dana</label>
          <input type="text" name="sumber_dana" class="form-control" placeholder="Contoh: BOS, Dana Sekolah">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Tanggal Pembelian</label>
          <input type="date" name="tanggal_pembelian" class="form-control">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Foto Barang</label>
        <input type="file" name="foto" id="addFotoInput" class="form-control" accept="image/*"
               onchange="previewFoto(this,'addFotoPreview')" style="padding:6px;">
        <div id="addFotoPreview" style="margin-top:8px;display:none;">
          <img id="addFotoImg" src="" alt="Preview" style="max-width:100%;max-height:140px;border-radius:8px;border:1.5px solid var(--border);object-fit:cover;">
        </div>
        <div class="form-hint">Format: JPG, PNG, GIF, WEBP. Maks 2MB. (Opsional)</div>
      </div>

      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Deskripsi / Catatan</label>
          <textarea name="deskripsi" class="form-control" rows="2" style="resize:vertical;" placeholder="Catatan (opsional)"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Bisa Dipinjam</label>
          <label class="form-control-check" style="height:42px;">
            <input type="checkbox" name="bisa_dipinjam" id="addDipinjam" value="1" checked onchange="toggleAddRoleBoxes(this)">
            <span>Aktifkan Peminjaman</span>
          </label>
        </div>
      </div>
      <div id="addRoleBoxes">
        <div class="form-divider">Izin Peminjam</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
          <label class="form-control-check" style="flex:1;min-width:120px;">
            <input type="checkbox" name="pinjam_murid" id="addPinjamMurid" value="1" checked onchange="syncAddCheckAll()">
            <span>Murid</span>
          </label>
          <label class="form-control-check" style="flex:1;min-width:120px;">
            <input type="checkbox" name="pinjam_guru" id="addPinjamGuru" value="1" checked onchange="syncAddCheckAll()">
            <span>Guru</span>
          </label>
          <label class="form-control-check" style="flex:1;min-width:120px;">
            <input type="checkbox" name="pinjam_tendik" id="addPinjamTendik" value="1" checked onchange="syncAddCheckAll()">
            <span>Tendik / Staff</span>
          </label>
        </div>
        <div style="margin-bottom:10px;">
          <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);cursor:pointer;">
            <input type="checkbox" id="addCheckAll" checked onchange="toggleAddAll(this)">
            Centang Semua
          </label>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin:14px 0 10px;">
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;">Batas Waktu Peminjaman (dalam menit)</div>
          <div style="font-weight:400;font-size:11px;color:var(--muted);text-transform:uppercase;">(kosongkan jika tidak ada batas)</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;">
          <div>
            <label class="form-label" id="addLabelDurasiMurid" style="color:#2563EB;"><i class="bi bi-stopwatch"></i> Murid</label>
            <input type="number" name="durasi_murid" id="addDurasiMurid" class="form-control" min="1" placeholder="cth: 270">
          </div>
          <div>
            <label class="form-label" id="addLabelDurasiGuru" style="color:#16A34A;"><i class="bi bi-stopwatch"></i> Guru</label>
            <input type="number" name="durasi_guru" id="addDurasiGuru" class="form-control" min="1" placeholder="cth: 480">
          </div>
          <div>
            <label class="form-label" id="addLabelDurasiTendik" style="color:#D97706;"><i class="bi bi-stopwatch"></i> Tendik</label>
            <input type="number" name="durasi_tendik" id="addDurasiTendik" class="form-control" min="1" placeholder="cth: 480">
          </div>
        </div>
      </div>
      <button type="submit" name="tambah_barang" class="btn-modal-submit"><i class="bi bi-plus-circle"></i> Tambah Sarana</button>
    </form>
  </div>
</div>


<!-- MODAL EDIT -->
<div class="modal-backdrop" id="modalEdit" onclick="handleBackdropClick(event,'modalEdit')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalEdit')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Edit Sarana</div>
    <div class="modal-sub">Ubah data sarana inventaris.</div>
    <div class="modal-error" id="editError"></div>
    <form method="POST" enctype="multipart/form-data" onsubmit="return validateBarang('editNama','editError','editRuangan')">
      <input type="hidden" name="id_barang" id="editId">
      <input type="hidden" name="foto_existing" id="editFotoExisting">
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Kode Sarana</label>
          <input type="text" name="kode_barang" id="editKode" class="form-control" placeholder="INV-001">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Nama Sarana <span style="color:#DC2626;">*</span></label>
          <input type="text" name="nama_barang" id="editNama" class="form-control" placeholder="Nama sarana">
        </div>
      </div>
      <div class="form-row" style="margin-top:12px;">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Letak / Prasarana <span style="color:#DC2626;">*</span></label>
          <select name="id_ruangan_barang" id="editRuangan" class="form-control" required>
            <option value="">— Pilih Ruangan —</option>
            <?php foreach ($ruangan_all as $rv): ?>
            <option value="<?= $rv['id_ruangan'] ?>"><?= htmlspecialchars($rv['nama_ruangan']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-divider" style="margin-top:14px;">Jumlah</div>
      <div class="qty-input-group">
        <div class="qty-input-row">
          <div>
            <label class="form-label" style="color:#16A34A;">● Jumlah Laik</label>
            <input type="number" name="jumlah_laik" id="editLaik" class="form-control" min="0"
                   oninput="updateTotalPreview('editLaik','editTdkLaik','editTotalPreview')"
                   style="border-color:#BBF7D0;">
          </div>
          <div>
            <label class="form-label" style="color:#DC2626;">● Jumlah Tidak Laik</label>
            <input type="number" name="jumlah_tidak_laik" id="editTdkLaik" class="form-control" min="0"
                   oninput="updateTotalPreview('editLaik','editTdkLaik','editTotalPreview')"
                   style="border-color:#FECACA;">
          </div>
        </div>
        <div class="qty-total-preview">
          <span>Total unit</span>
          <strong id="editTotalPreview">0</strong>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Spesifikasi</label>
        <input type="text" name="spesifikasi" id="editSpesifikasi" class="form-control" placeholder="Merk, ukuran, dll. (opsional)">
      </div>

      <div class="form-divider">Informasi Pengadaan</div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Sumber Dana</label>
          <input type="text" name="sumber_dana" id="editSumberDana" class="form-control" placeholder="Contoh: BOS, Dana Sekolah">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Tanggal Pembelian</label>
          <input type="date" name="tanggal_pembelian" id="editTglBeli" class="form-control">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Foto Barang</label>
        <div id="editFotoPreviewWrap" style="margin-bottom:8px;display:none;">
          <img id="editFotoImg" src="" alt="Foto" style="max-width:100%;max-height:140px;border-radius:8px;border:1.5px solid var(--border);object-fit:cover;">
          <div style="font-size:11px;color:var(--muted);margin-top:4px;">Foto saat ini. Upload baru untuk mengganti.</div>
        </div>
        <input type="file" name="foto" id="editFotoInput" class="form-control" accept="image/*"
               onchange="previewFoto(this,'editFotoNewPreview')" style="padding:6px;">
        <div id="editFotoNewPreview" style="margin-top:8px;display:none;">
          <img id="editFotoNewImg" src="" alt="Preview Baru" style="max-width:100%;max-height:140px;border-radius:8px;border:1.5px solid var(--border);object-fit:cover;">
        </div>
        <div class="form-hint">Format: JPG, PNG, GIF, WEBP. Biarkan kosong untuk tidak mengubah foto.</div>
      </div>

      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Deskripsi / Catatan</label>
          <textarea name="deskripsi" id="editDeskripsi" class="form-control" rows="2" style="resize:vertical;"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Bisa Dipinjam</label>
          <label class="form-control-check" style="height:42px;">
            <input type="checkbox" name="bisa_dipinjam" id="editDipinjam" value="1" onchange="toggleEditRoleBoxes(this)">
            <span>Aktifkan Peminjaman</span>
          </label>
        </div>
      </div>
      <div id="editRoleBoxes">
        <div class="form-divider">Izin Peminjam</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
          <label class="form-control-check" style="flex:1;min-width:120px;">
            <input type="checkbox" name="pinjam_murid" id="editPinjamMurid" value="1" onchange="syncEditCheckAll()">
            <span>Murid</span>
          </label>
          <label class="form-control-check" style="flex:1;min-width:120px;">
            <input type="checkbox" name="pinjam_guru" id="editPinjamGuru" value="1" onchange="syncEditCheckAll()">
            <span>Guru</span>
          </label>
          <label class="form-control-check" style="flex:1;min-width:120px;">
            <input type="checkbox" name="pinjam_tendik" id="editPinjamTendik" value="1" onchange="syncEditCheckAll()">
            <span>Tendik / Staff</span>
          </label>
        </div>
        <div style="margin-bottom:10px;">
          <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);cursor:pointer;">
            <input type="checkbox" id="editCheckAll" onchange="toggleEditAll(this)">
            Centang Semua
          </label>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin:14px 0 10px;">
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;">Batas Waktu Peminjaman (dalam menit)</div>
          <div style="font-weight:400;font-size:11px;color:var(--muted);text-transform:uppercase;">(kosongkan jika tidak ada batas)</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;">
          <div>
            <label class="form-label" style="color:#2563EB;"><i class="bi bi-stopwatch"></i> Murid</label>
            <input type="number" name="durasi_murid" id="editDurasiMurid" class="form-control" min="1" placeholder="cth: 270">
          </div>
          <div>
            <label class="form-label" style="color:#16A34A;"><i class="bi bi-stopwatch"></i> Guru</label>
            <input type="number" name="durasi_guru" id="editDurasiGuru" class="form-control" min="1" placeholder="cth: 480">
          </div>
          <div>
            <label class="form-label" style="color:#D97706;"><i class="bi bi-stopwatch"></i> Tendik</label>
            <input type="number" name="durasi_tendik" id="editDurasiTendik" class="form-control" min="1" placeholder="cth: 480">
          </div>
        </div>
      </div>
      <button type="submit" name="edit_barang" class="btn-modal-submit"><i class="bi bi-check-circle"></i> Simpan Perubahan</button>
    </form>
  </div>
</div>


<!-- MODAL HAPUS -->
<div class="modal-backdrop" id="modalDelete" onclick="handleBackdropClick(event,'modalDelete')">
  <div class="modal-box" style="max-width:420px;">
    <button class="modal-close" onclick="closeModal('modalDelete')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title" style="color:#DC2626;">Hapus Sarana</div>
    <div class="modal-sub">Apakah kamu yakin ingin menghapus <strong id="deleteNama"></strong>?<br>Tindakan ini tidak dapat dibatalkan.</div>
    <form method="POST">
      <input type="hidden" name="id_barang" id="deleteId">
      <button type="submit" name="hapus_barang" class="btn-modal-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalDelete')">Batal</button>
    </form>
  </div>
</div>


<script>
  function toggleMobileMenu(){const m=document.getElementById('mobileMenu'),i=document.getElementById('hamburgerIcon'),o=m.classList.toggle('open');i.className=o?'bi bi-x-lg':'bi bi-list';}
  document.addEventListener('click',e=>{const m=document.getElementById('mobileMenu'),b=document.getElementById('hamburgerBtn');if(m.classList.contains('open')&&!m.contains(e.target)&&!b.contains(e.target)){m.classList.remove('open');document.getElementById('hamburgerIcon').className='bi bi-list';}});

  function openModal(id){
    document.getElementById(id).classList.add('open');
    document.body.style.overflow='hidden';
  }
  function closeModal(id){
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow='';
    const e=document.getElementById(id==='modalAdd'?'addError':'editError');
    if(e)e.classList.remove('show');
  }
  function handleBackdropClick(e,id){if(e.target===document.getElementById(id))closeModal(id);}
  document.addEventListener('keydown',e=>{if(e.key==='Escape')['modalAdd','modalEdit','modalDelete'].forEach(closeModal);});

  function openAddModal(){openModal('modalAdd');setTimeout(()=>document.getElementById('addNama').focus(),80);}

  /* ── Role checkbox helpers ── */
  function toggleAddRoleBoxes(cb){
    document.getElementById('addRoleBoxes').style.display = cb.checked ? '' : 'none';
  }
  function toggleEditRoleBoxes(cb){
    document.getElementById('editRoleBoxes').style.display = cb.checked ? '' : 'none';
  }
  function toggleAddAll(cb){
    ['addPinjamMurid','addPinjamGuru','addPinjamTendik'].forEach(id=>{
      document.getElementById(id).checked = cb.checked;
    });
  }
  function toggleEditAll(cb){
    ['editPinjamMurid','editPinjamGuru','editPinjamTendik'].forEach(id=>{
      document.getElementById(id).checked = cb.checked;
    });
  }
  function syncEditCheckAll(){
    const all = ['editPinjamMurid','editPinjamGuru','editPinjamTendik'].every(id=>document.getElementById(id).checked);
    document.getElementById('editCheckAll').checked = all;
  }
  // Sync "centang semua" edit when individual boxes change
  ['editPinjamMurid','editPinjamGuru','editPinjamTendik'].forEach(id=>{
    document.addEventListener('change', e=>{ if(e.target.id===id) syncEditCheckAll(); });
  });
  // Sync "centang semua" add
  function syncAddCheckAll(){
    const all = ['addPinjamMurid','addPinjamGuru','addPinjamTendik'].every(id=>document.getElementById(id).checked);
    document.getElementById('addCheckAll').checked = all;
  }
  ['addPinjamMurid','addPinjamGuru','addPinjamTendik'].forEach(id=>{
    document.addEventListener('change', e=>{ if(e.target.id===id) syncAddCheckAll(); });
  });

  function openEditModal(data){
    document.getElementById('editId').value              = data.id;
    document.getElementById('editKode').value            = data.kode||'';
    document.getElementById('editNama').value            = data.nama||'';
    document.getElementById('editRuangan').value         = data.id_ruangan||'';
    document.getElementById('editLaik').value            = data.jumlah_laik||0;
    document.getElementById('editTdkLaik').value         = data.jumlah_tidak_laik||0;
    document.getElementById('editDeskripsi').value       = data.deskripsi||'';
    document.getElementById('editSpesifikasi').value     = data.spesifikasi||'';
    const aktif = !!parseInt(data.bisa_dipinjam);
    document.getElementById('editDipinjam').checked      = aktif;
    document.getElementById('editRoleBoxes').style.display = aktif ? '' : 'none';
    document.getElementById('editPinjamMurid').checked   = !!parseInt(data.pinjam_murid  ?? 1);
    document.getElementById('editPinjamGuru').checked    = !!parseInt(data.pinjam_guru   ?? 1);
    document.getElementById('editPinjamTendik').checked  = !!parseInt(data.pinjam_tendik ?? 1);
    syncEditCheckAll();
    document.getElementById('editDurasiMurid').value     = data.durasi_murid  || '';
    document.getElementById('editDurasiGuru').value      = data.durasi_guru   || '';
    document.getElementById('editDurasiTendik').value    = data.durasi_tendik || '';
    document.getElementById('editSumberDana').value      = data.sumber_dana||'';
    document.getElementById('editTglBeli').value         = data.tanggal_pembelian||'';
    document.getElementById('editFotoExisting').value    = data.foto||'';
    // Reset file input
    document.getElementById('editFotoInput').value = '';
    document.getElementById('editFotoNewPreview').style.display = 'none';
    // Tampilkan foto existing
    const wrap = document.getElementById('editFotoPreviewWrap');
    const img  = document.getElementById('editFotoImg');
    if (data.foto) {
      img.src = '../assets/foto_barang/' + data.foto;
      wrap.style.display = 'block';
    } else {
      wrap.style.display = 'none';
    }
    updateTotalPreview('editLaik','editTdkLaik','editTotalPreview');
    openModal('modalEdit');
    setTimeout(()=>document.getElementById('editNama').focus(),80);
  }

  function openDeleteModal(id,nama){
    document.getElementById('deleteId').value=id;
    document.getElementById('deleteNama').textContent=nama;
    openModal('modalDelete');
  }

  function validateBarang(inputId, errId, ruanganId) {
    const v = document.getElementById(inputId).value.trim();
    const e = document.getElementById(errId);
    if (!v) {
      e.textContent = 'Nama sarana tidak boleh kosong.';
      e.classList.add('show');
      return false;
    }
    if (ruanganId) {
      const r = document.getElementById(ruanganId);
      if (r && !r.value) {
        e.textContent = 'Letak / Prasarana wajib dipilih.';
        e.classList.add('show');
        e.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return false;
      }
    }
    e.classList.remove('show');
    return true;
  }

  function updateTotalPreview(laikId,tdkId,previewId){
    const l=parseInt(document.getElementById(laikId).value)||0;
    const t=parseInt(document.getElementById(tdkId).value)||0;
    document.getElementById(previewId).textContent=l+t;
  }

  function previewFoto(input, wrapId) {
    const wrap = document.getElementById(wrapId);
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = e => {
        // Cari img di dalam wrap
        const imgs = wrap.getElementsByTagName('img');
        if (imgs.length) imgs[0].src = e.target.result;
        wrap.style.display = 'block';
      };
      reader.readAsDataURL(input.files[0]);
    } else {
      wrap.style.display = 'none';
    }
  }

  /* Live search */
  function initTableControls(formId, searchInputId) {
  const input = document.getElementById(searchInputId);
  if (!input) return;
  let timer, lastQuery = input.value;
  input.addEventListener('input', function() {
    const q = this.value;
    clearTimeout(timer);
    timer = setTimeout(function() {
      if (q === lastQuery) return;
      lastQuery = q;
      const url = new URL(window.location.href);
      url.searchParams.set('q', q);
      url.searchParams.set('page', '1');
      window.history.replaceState({}, '', url.toString());
      fetchTableData(url.toString(), searchInputId);
    }, 350);
  });
}

function fetchTableData(url, focusInputId) {
  const tableWrap = document.querySelector('.table-wrap');
  const mobileList = document.querySelector('.mobile-list');
  if (tableWrap) tableWrap.style.opacity = '0.5';
  if (mobileList) mobileList.style.opacity = '0.5';
  fetch(url).then(r => r.text()).then(html => {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const newTbody = doc.querySelector('.inv-table tbody');
    const curTbody = document.querySelector('.inv-table tbody');
    if (newTbody && curTbody) curTbody.innerHTML = newTbody.innerHTML;
    const newMobile = doc.querySelector('.mobile-list');
    const curMobile = document.querySelector('.mobile-list');
    if (newMobile && curMobile) curMobile.innerHTML = newMobile.innerHTML;
    const newFooter = doc.querySelector('.table-footer');
    const curFooter = document.querySelector('.table-footer');
    if (newFooter && curFooter) curFooter.innerHTML = newFooter.innerHTML;
    const newCounter = doc.querySelector('.card-header span[style*="color:var(--muted)"]');
    const curCounter = document.querySelector('.card-header span[style*="color:var(--muted)"]');
    if (newCounter && curCounter) curCounter.innerHTML = newCounter.innerHTML;
    if (tableWrap) tableWrap.style.opacity = '1';
    if (mobileList) mobileList.style.opacity = '1';
    const input = document.getElementById(focusInputId);
    if (input) { const len = input.value.length; input.focus(); input.setSelectionRange(len, len); }
  }).catch(() => {
    if (tableWrap) tableWrap.style.opacity = '1';
    if (mobileList) mobileList.style.opacity = '1';
  });
}
initTableControls('searchFormBarang', 'searchInputBarang');

  /* ── BFCache Fix: paksa reload jika halaman dari back-forward cache ── */
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
  });


  function showImportFile(input, nameId) {
    const nameEl = document.getElementById(nameId);
    if (input.files.length) { nameEl.textContent = input.files[0].name; nameEl.style.display = 'block'; }
  }
</script>

<!-- ══ MODAL IMPORT EXCEL BARANG ══ -->
<div class="modal-backdrop" id="modalImportBarang" onclick="handleBackdropClick(event,'modalImportBarang')">
  <div class="modal-box" style="max-width:580px;">
    <button class="modal-close" onclick="closeModal('modalImportBarang')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title"><i class="bi bi-file-earmark-excel" style="color:#16A34A;"></i> Import Sarana via Excel</div>
    <div class="modal-sub" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <span>Upload file Excel (.xlsx/.xls) atau CSV untuk menambahkan banyak sarana sekaligus.</span>
      <a href="barang_template.xlsx" download
         style="display:inline-flex;align-items:center;gap:5px;color:#16A34A;font-weight:700;font-size:12px;text-decoration:none;background:#F0FDF4;border:1px solid #BBF7D0;padding:5px 12px;border-radius:8px;white-space:nowrap;">
        <i class="bi bi-download"></i> Download Template
      </a>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <div class="upload-area-xl" id="importBarangUpload">
        <input type="file" name="excel_barang" id="excelBarangInput" accept=".xlsx,.xls,.csv"
               onchange="showImportFile(this,'excelBarangName')">
        <div class="upload-area-xl-icon"><i class="bi bi-file-earmark-excel"></i></div>
        <div class="upload-area-xl-text">
          <strong>Klik untuk pilih file Excel</strong>
          Format: .xlsx / .xls / .csv — maks 5MB
        </div>
        <div class="upload-filename" id="excelBarangName"></div>
      </div>
      <button type="submit" name="import_barang_excel" class="btn-modal-submit">
        <i class="bi bi-upload"></i> Import Sarana
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalImportBarang')">Batal</button>
    </form>
  </div>
</div>

</body>
</html>