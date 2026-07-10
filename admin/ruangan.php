<?php
session_start();
require_once "../config/koneksi.php";
require_once "../config/auth_admin.php";

$msg_success = '';
$msg_error   = '';

/* ── Helper: simpan foto upload ── */
function simpanFoto($file_input, $id_ruangan) {
    if (empty($_FILES[$file_input]['name'])) return null;
    $f   = $_FILES[$file_input];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) return false;
    if ($f['size'] > 3 * 1024 * 1024) return false;
    $dir = __DIR__ . '/../assets/ruangan/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $nama_file = 'room_' . $id_ruangan . '_' . time() . '.' . $ext;
    if (move_uploaded_file($f['tmp_name'], $dir . $nama_file)) return $nama_file;
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* TAMBAH */
    if (isset($_POST['tambah_ruangan'])) {
        $nama               = trim(mysqli_real_escape_string($conn, $_POST['nama_ruangan']));
        $keterangan         = trim(mysqli_real_escape_string($conn, $_POST['keterangan'] ?? ''));
        $panjang            = isset($_POST['panjang']) && is_numeric($_POST['panjang']) ? (float)$_POST['panjang'] : null;
        $lebar              = isset($_POST['lebar'])   && is_numeric($_POST['lebar'])   ? (float)$_POST['lebar']   : null;
        $persen_rusak       = isset($_POST['persentase_kerusakan']) && is_numeric($_POST['persentase_kerusakan'])
                              ? min(100, max(0, (float)$_POST['persentase_kerusakan'])) : 0;


        if (empty($nama)) {
            $msg_error = "Nama prasarana tidak boleh kosong.";
        } else {
            $cek = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COUNT(*) as t FROM ruangan WHERE nama_ruangan='$nama'"));
            if ($cek['t'] > 0) {
                $msg_error = "Ruangan \"$nama\" sudah ada.";
            } else {
                $p_sql = $panjang !== null ? $panjang : 'NULL';
                $l_sql = $lebar   !== null ? $lebar   : 'NULL';
                $ket_sql = $keterangan ? "'$keterangan'" : 'NULL';
                mysqli_query($conn, "
                    INSERT INTO ruangan (nama_ruangan, keterangan, panjang, lebar, persentase_kerusakan)
                    VALUES ('$nama', $ket_sql, $p_sql, $l_sql, $persen_rusak)
                ");
                $new_id = mysqli_insert_id($conn);
                $foto_file = simpanFoto('foto_ruangan', $new_id);
                if ($foto_file === false) {
                    $msg_error = "Format foto tidak valid atau ukuran melebihi 3MB.";
                    mysqli_query($conn, "DELETE FROM ruangan WHERE id_ruangan=$new_id");
                } else {
                    if ($foto_file) {
                        $fo = mysqli_real_escape_string($conn, $foto_file);
                        mysqli_query($conn, "UPDATE ruangan SET foto='$fo' WHERE id_ruangan=$new_id");
                    }
                    header("Location: ruangan.php?success=".urlencode("Ruangan \"$nama\" berhasil ditambahkan."));
                    exit;
                }
            }
        }
    }

    /* EDIT */
    if (isset($_POST['edit_ruangan'])) {
        $id                 = (int)$_POST['id_ruangan'];
        $nama               = trim(mysqli_real_escape_string($conn, $_POST['nama_ruangan']));
        $keterangan         = trim(mysqli_real_escape_string($conn, $_POST['keterangan'] ?? ''));
        $panjang            = isset($_POST['panjang']) && is_numeric($_POST['panjang']) ? (float)$_POST['panjang'] : null;
        $lebar              = isset($_POST['lebar'])   && is_numeric($_POST['lebar'])   ? (float)$_POST['lebar']   : null;
        $persen_rusak       = isset($_POST['persentase_kerusakan']) && is_numeric($_POST['persentase_kerusakan'])
                              ? min(100, max(0, (float)$_POST['persentase_kerusakan'])) : 0;

        if (empty($nama)) {
            $msg_error = "Nama prasarana tidak boleh kosong.";
        } else {
            $cek = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COUNT(*) as t FROM ruangan WHERE nama_ruangan='$nama' AND id_ruangan!=$id"));
            if ($cek['t'] > 0) {
                $msg_error = "Nama ruangan \"$nama\" sudah digunakan.";
            } else {
                $old = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT foto FROM ruangan WHERE id_ruangan=$id"));
                $foto_lama = $old['foto'] ?? null;

                if (isset($_POST['hapus_foto']) && $foto_lama) {
                    $pl = __DIR__ . '/../assets/ruangan/' . $foto_lama;
                    if (file_exists($pl)) unlink($pl);
                    $foto_lama = null;
                }

                $foto_baru = simpanFoto('foto_ruangan', $id);
                if ($foto_baru === false) {
                    $msg_error = "Format foto tidak valid atau ukuran melebihi 3MB.";
                } else {
                    if ($foto_baru) {
                        if ($foto_lama) {
                            $pl2 = __DIR__ . '/../assets/ruangan/' . $foto_lama;
                            if (file_exists($pl2)) unlink($pl2);
                        }
                        $foto_lama = $foto_baru;
                    }
                    $foto_sql = $foto_lama
                        ? "foto='".mysqli_real_escape_string($conn,$foto_lama)."'"
                        : "foto=NULL";
                    $p_sql   = $panjang !== null ? $panjang : 'NULL';
                    $l_sql   = $lebar   !== null ? $lebar   : 'NULL';
                    $ket_sql = $keterangan ? "'$keterangan'" : 'NULL';
                    mysqli_query($conn, "
                        UPDATE ruangan
                        SET nama_ruangan='$nama', keterangan=$ket_sql, $foto_sql,
                            panjang=$p_sql, lebar=$l_sql,
                            persentase_kerusakan=$persen_rusak
                        WHERE id_ruangan=$id
                    ");
                    header("Location: ruangan.php?success=".urlencode("Prasarana berhasil diperbarui."));
                    exit;
                }
            }
        }
    }

    /* HAPUS */
    if (isset($_POST['hapus_ruangan'])) {
        $id  = (int)$_POST['id_ruangan'];
        $cek = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) as t FROM barang WHERE id_ruangan=$id"));
        if ($cek['t'] > 0) {
            $msg_error = "Ruangan tidak bisa dihapus karena masih memiliki barang.";
        } else {
            $old = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT foto FROM ruangan WHERE id_ruangan=$id"));
            if (!empty($old['foto'])) {
                $path = __DIR__ . '/../assets/ruangan/' . $old['foto'];
                if (file_exists($path)) unlink($path);
            }
            mysqli_query($conn, "DELETE FROM ruangan WHERE id_ruangan=$id");
            header("Location: ruangan.php?success=".urlencode("Prasarana berhasil dihapus."));
            exit;
        }
    }

    /* IMPORT EXCEL RUANGAN */
    if (isset($_POST['import_ruangan_excel'])) {
        if (!isset($_FILES['excel_ruangan']) || $_FILES['excel_ruangan']['error'] !== UPLOAD_ERR_OK) {
            $msg_error = "Gagal upload file. Pastikan file Excel dipilih dengan benar.";
        } else {
            $ext = strtolower(pathinfo($_FILES['excel_ruangan']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx','xls','csv'])) {
                $msg_error = "Format file harus Excel (.xlsx / .xls) atau CSV.";
            } else {
                $rows    = [];
                $tmpPath = $_FILES['excel_ruangan']['tmp_name'];

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
                                foreach ($sheetRow->getCellIterator('A', 'F') as $cell) {
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
                        // Kolom: [0]nama_ruangan [1]keterangan [2]panjang [3]lebar [4]persentase_kerusakan
                        $nama_r  = trim(mysqli_real_escape_string($conn, $data[0] ?? ''));
                        $ket     = trim(mysqli_real_escape_string($conn, $data[1] ?? ''));
                        $panjang = isset($data[2]) && is_numeric($data[2]) ? (float)$data[2] : null;
                        $lebar   = isset($data[3]) && is_numeric($data[3]) ? (float)$data[3] : null;
                        $persen  = isset($data[4]) && is_numeric($data[4]) ? min(100, max(0, (float)$data[4])) : 0;
                        if (empty($nama_r)) { $skip++; $errors[] = "Baris $rowNum: Nama ruangan kosong."; continue; }

                        // Cek nama duplikat
                        $cek_n = mysqli_fetch_assoc(mysqli_query($conn,
                            "SELECT COUNT(*) as t FROM ruangan WHERE nama_ruangan='$nama_r'"));
                        if ($cek_n['t'] > 0) { $skip++; $errors[] = "Baris $rowNum: Nama '$nama_r' sudah ada."; continue; }

                        $ket_sql  = $ket     ? "'$ket'"     : 'NULL';
                        $p_sql    = $panjang !== null ? $panjang : 'NULL';
                        $l_sql    = $lebar   !== null ? $lebar   : 'NULL';

                        mysqli_query($conn, "
                            INSERT INTO ruangan (nama_ruangan, keterangan, panjang, lebar, persentase_kerusakan)
                            VALUES ('$nama_r', $ket_sql, $p_sql, $l_sql, $persen)
                        ");
                        $ok++;
                    }

                    $pesan = "$ok prasarana berhasil diimport" . ($skip ? ", $skip baris dilewati." : ".");
                    if (!empty($errors)) {
                        $msg_error = implode('<br>', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '<br>...dan lainnya.' : '');
                    }
                    if ($ok > 0) {
                        header("Location: ruangan.php?success=".urlencode($pesan));
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

/* ── Search & Pagination ── */
$search = isset($_GET['q']) ? trim(mysqli_real_escape_string($conn, $_GET['q'])) : '';
$filter_kerusakan = isset($_GET['kerusakan']) && in_array($_GET['kerusakan'],
    ['baik','ringan','sedang','berat']) ? $_GET['kerusakan'] : '';

$valid_per_page = [5,10,20,25,50,100];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'],$valid_per_page) ? (int)$_GET['per_page'] : 5;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where = "WHERE 1=1";
if ($search)                        $where .= " AND r.nama_ruangan LIKE '%$search%'";
if ($filter_kerusakan === 'baik')   $where .= " AND r.persentase_kerusakan = 0";
if ($filter_kerusakan === 'ringan') $where .= " AND r.persentase_kerusakan BETWEEN 1 AND 30";
if ($filter_kerusakan === 'sedang') $where .= " AND r.persentase_kerusakan BETWEEN 31 AND 45";
if ($filter_kerusakan === 'berat')  $where .= " AND r.persentase_kerusakan > 45";

$total       = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM ruangan r $where"))['t'];
$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$ruangan_q = mysqli_query($conn, "
    SELECT r.id_ruangan, r.nama_ruangan, r.keterangan, r.foto,
           r.panjang, r.lebar, r.persentase_kerusakan,
           COUNT(b.id_barang) as jml_barang,
           COALESCE(SUM(b.jumlah),0) as total_jumlah,
           COALESCE(SUM(b.jumlah_laik),0) as total_laik,
           COALESCE(SUM(b.jumlah_tidak_laik),0) as total_tidak_laik
    FROM ruangan r
    LEFT JOIN barang b ON r.id_ruangan = b.id_ruangan
    $where
    GROUP BY r.id_ruangan, r.nama_ruangan, r.keterangan, r.foto,
             r.panjang, r.lebar, r.persentase_kerusakan
    ORDER BY r.nama_ruangan ASC
    LIMIT $per_page OFFSET $offset
");
$ruangan_list = [];
while ($r = mysqli_fetch_assoc($ruangan_q)) $ruangan_list[] = $r;

$nama_admin    = $_SESSION['nama'] ?? 'Admin';
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
  <title>Prasarana - Inventaris SARPRAS</title>
  <link rel="icon" href="../assets/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue:#4A90C4;--blue-dark:#2B6FA8;--blue-deep:#1B3F6E;
      --green:#3D9B4A;--yellow:#F5C518;
      --bg:#F0F7FF;--card:#FFFFFF;--text:#1B2D45;--muted:#6B7C93;
      --border:#D0E4F5;--shadow:0 2px 14px rgba(27,63,110,.09);
      --shadow-lg:0 8px 32px rgba(27,63,110,.15);
    }
    html{height:100%}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;}

    /* NAVBAR */
    .navbar{position:sticky;top:0;z-index:100;background:var(--blue-deep);display:flex;align-items:center;padding:0 28px;height:62px;box-shadow:0 2px 12px rgba(27,63,110,.25);}
    .nav-brand{display:flex;align-items:center;gap:11px;text-decoration:none;flex-shrink:0;margin-right:36px;}
    .nav-brand img{width:38px;height:38px;object-fit:contain;}
    .nav-brand-text strong{display:block;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:800;color:white;line-height:1.2;}
    .nav-brand-text span{font-size:10px;color:rgba(255,255,255,.5);}
    .nav-links{display:flex;align-items:center;gap:2px;flex:1;}
    .nav-link{padding:8px 14px;border-radius:8px;color:rgba(255,255,255,.65);text-decoration:none;font-size:13.5px;font-weight:500;transition:all .2s;white-space:nowrap;}
    .nav-link:hover{color:white;background:rgba(255,255,255,.1);}
    .nav-link.active{color:white;font-weight:700;border-bottom:2px solid var(--yellow);border-radius:0;padding-bottom:6px;}
    .nav-link.logout{margin-left:auto;color:rgba(255,255,255,.5);}
    .nav-link.logout:hover{color:#FCA5A5;background:rgba(239,68,68,.15);}
    .nav-badge{display:inline-flex;align-items:center;justify-content:center;background:#DC2626;color:white;width:17px;height:17px;border-radius:50%;font-size:10px;font-weight:800;margin-left:4px;vertical-align:middle;}
    .nav-hamburger{display:none;margin-left:auto;background:none;border:none;cursor:pointer;color:white;font-size:22px;padding:6px;border-radius:8px;}
    .nav-hamburger:hover{background:rgba(255,255,255,.1);}
    .nav-mobile-menu{display:none;position:fixed;top:62px;left:0;right:0;background:var(--blue-deep);box-shadow:0 8px 24px rgba(27,63,110,.25);z-index:99;flex-direction:column;padding:10px 16px 20px;border-top:1px solid rgba(255,255,255,.08);max-height:calc(100vh - 62px);overflow-y:auto;}
    .nav-mobile-menu.open{display:flex;}
    .nav-mobile-menu .nav-link{padding:13px 14px;border-radius:10px;font-size:14px;border-bottom:1px solid rgba(255,255,255,.06);}
    .nav-mobile-menu .nav-link:last-child{border-bottom:none;}
    .nav-mobile-menu .nav-link.logout{margin-left:0;margin-top:6px;}

    /* PAGE */
    .page-wrapper{max-width:1100px;margin:0 auto;padding:32px 24px 60px;flex:1;}
    .flash{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:9px;}
    .flash-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;}
    .flash-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;}
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px;flex-wrap:wrap;}
    .page-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:900;color:var(--text);}
    .page-sub{font-size:13px;color:var(--muted);margin-top:3px;}

    /* TOOLBAR */
    .toolbar{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
    .search-wrap-inner{position:relative;flex:1;min-width:200px;}
    .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:14px;pointer-events:none;}
    .search-input{width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border);border-right:none;border-radius:9px 0 0 9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);outline:none;}
    .search-input:focus{border-color:var(--blue);}
    .btn-search{padding:10px 16px;background:var(--blue-dark);color:white;border:none;border-radius:0 9px 9px 0;cursor:pointer;font-size:14px;height:42px;}
    .filter-group{display:flex;flex-direction:column;gap:3px;}
    .filter-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
    .filter-select{padding:10px 30px 10px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);height:42px;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;cursor:pointer;outline:none;}
    .filter-select:focus{border-color:var(--blue);}
    .show-entries-wrap{display:flex;align-items:center;gap:8px;}
    .show-entries-label{font-size:13px;color:var(--muted);font-weight:500;}
    .show-entries-select{padding:7px 28px 7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;cursor:pointer;outline:none;}
    .show-entries-select:focus{border-color:var(--blue);}

    /* CARD & TABLE */
    .card{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;}
    .card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .card-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;color:var(--text);display:flex;align-items:center;gap:8px;}
    .card-title i{color:var(--blue);}
    .table-wrap{overflow-x:auto;}
    .inv-table{width:100%;border-collapse:collapse;font-size:13px;}
    .inv-table thead th{background:#F4F8FD;padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.5px;text-transform:uppercase;border-bottom:2px solid var(--border);white-space:nowrap;cursor:pointer;user-select:none;}
    .inv-table thead th:last-child{cursor:default;}
    .inv-table thead th:hover:not(:last-child){color:var(--blue-dark);}
    .inv-table thead th.sorted{color:var(--blue-dark);}
    .inv-table thead th .sort-icon{margin-left:4px;font-size:10px;opacity:.5;}
    .inv-table thead th.sorted .sort-icon{opacity:1;}
    .inv-table tbody tr{border-bottom:1px solid var(--border);transition:background .12s;cursor:pointer;}
    .inv-table tbody tr:last-child{border-bottom:none;}
    .inv-table tbody tr:hover{background:#F4F8FD;}
    .inv-table td{padding:12px 14px;color:var(--text);vertical-align:middle;}
    .td-room{display:flex;align-items:center;gap:10px;}
    .td-thumb{width:38px;height:38px;border-radius:9px;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:17px;}
    .td-thumb img{width:100%;height:100%;object-fit:cover;}
    .td-name{font-weight:700;font-size:13px;}
    .td-keterangan{font-size:11px;color:var(--muted);margin-top:2px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

    /* BADGES */
    .badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
    .badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;opacity:.7;}
    .badge-success{background:#F0FDF4;color:#15803D;}
    .badge-info{background:#EFF6FF;color:#2563EB;}
    .badge-warning{background:#FFFBEB;color:#D97706;}
    .badge-danger{background:#FEF2F2;color:#DC2626;}
    .badge-muted{background:#F1F5F9;color:#64748B;}
    .badge-orange{background:#FFF7ED;color:#EA580C;}

    /* Progress bar kerusakan */
    .rusak-bar-wrap{display:flex;align-items:center;gap:7px;}
    .rusak-bar-track{flex:1;height:6px;background:var(--border);border-radius:10px;overflow:hidden;min-width:50px;}
    .rusak-bar-fill{height:100%;border-radius:10px;transition:width .6s;}
    .rusak-pct{font-size:11px;font-weight:700;flex-shrink:0;min-width:32px;text-align:right;}
    .rusak-0   {color:#16A34A;}
    .rusak-low {color:#D97706;}
    .rusak-mid {color:#EA580C;}
    .rusak-high{color:#DC2626;}

    /* BUTTONS */
    .btn{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:'DM Sans',sans-serif;}
    .btn-sm{padding:6px 10px;font-size:12px;border-radius:7px;}
    .btn-primary{background:var(--blue-dark);color:white;box-shadow:0 4px 14px rgba(43,111,168,.25);}
    .btn-primary:hover{background:var(--blue-deep);}
    .btn-secondary{background:var(--card);color:var(--text);border:1px solid var(--border);}
    .btn-secondary:hover{background:var(--bg);}
    .btn-danger{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;}
    .btn-danger:hover{background:#DC2626;color:white;}

    /* PAGINATION */
    .table-footer{display:flex;align-items:center;justify-content:space-between;padding:13px 20px;border-top:1px solid var(--border);font-size:13px;color:var(--muted);flex-wrap:wrap;gap:10px;}
    .pag-btns{display:flex;align-items:center;gap:6px;}
    .pag-btn{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;background:var(--card);color:var(--text);text-decoration:none;transition:all .2s;}
    .pag-btn:hover:not(.disabled):not(.active){background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.active{background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.disabled{opacity:.35;cursor:not-allowed;pointer-events:none;}
    .pag-btn-text{padding:0 12px;width:auto;}

    .empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
    .empty-state i{font-size:48px;display:block;margin-bottom:14px;color:#B0C8E0;}
    .empty-state h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:700;color:var(--text);margin-bottom:6px;}

    /* MOBILE CARD LIST */
    .mobile-room-list{display:none;}
    .mobile-room-item{background:var(--card);border:1px solid var(--border);border-radius:12px;margin-bottom:10px;box-shadow:var(--shadow);overflow:hidden;cursor:pointer;transition:box-shadow .15s;}
    .mobile-room-item:hover{box-shadow:var(--shadow-lg);}
    .mobile-room-item-inner{display:flex;align-items:center;gap:12px;padding:12px 14px;}
    .mobile-room-thumb{width:44px;height:44px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:20px;overflow:hidden;}
    .mobile-room-thumb img{width:100%;height:100%;object-fit:cover;}
    .mobile-room-info{flex:1;min-width:0;}
    .mobile-room-name{font-weight:700;font-size:14px;color:var(--text);}
    .mobile-room-meta{display:flex;flex-wrap:wrap;gap:6px;margin-top:5px;align-items:center;}
    .mobile-room-pill{display:inline-flex;align-items:center;gap:4px;background:var(--bg);border:1px solid var(--border);border-radius:20px;padding:2px 9px;font-size:11px;color:var(--muted);font-weight:600;}
    .mobile-room-actions{display:flex;gap:6px;padding:10px 14px;border-top:1px solid var(--border);justify-content:flex-end;}

    /* MODAL */
    .modal-backdrop{display:none;position:fixed;inset:0;z-index:500;background:rgba(27,63,110,.35);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
    .modal-backdrop.open{display:flex;}
    .modal-box{background:var(--card);border-radius:18px;padding:28px 32px;width:100%;max-width:520px;box-shadow:var(--shadow-lg);position:relative;z-index:501;animation:modalIn .22s cubic-bezier(.4,0,.2,1);max-height:92vh;overflow-y:auto;}
    @keyframes modalIn{from{transform:scale(.94) translateY(10px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
    .modal-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--text);margin-bottom:6px;}
    .modal-sub{font-size:13px;color:var(--muted);margin-bottom:20px;}
    .modal-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:8px;background:var(--bg);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px;transition:all .2s;}
    .modal-close:hover{background:#FECACA;color:#DC2626;}
    .modal-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;border-radius:8px;padding:9px 12px;font-size:12px;margin-bottom:14px;display:none;}
    .modal-error.show{display:block;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
    .form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px;}
    .form-group{margin-bottom:14px;}
    .form-label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;}
    .form-control{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
    .form-control:focus{border-color:var(--blue);background:white;box-shadow:0 0 0 3px rgba(74,144,196,.14);}
    .upload-area{border:2px dashed var(--border);border-radius:12px;background:var(--bg);padding:14px;text-align:center;cursor:pointer;transition:all .2s;margin-bottom:14px;position:relative;}
    .upload-area:hover{border-color:var(--blue);background:#EFF6FF;}
    .upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
    .upload-area-icon{font-size:22px;color:var(--blue);opacity:.6;margin-bottom:4px;}
    .upload-area-text{font-size:11px;color:var(--muted);}
    .upload-area-text strong{color:var(--blue-dark);display:block;}

    /* UPLOAD AREA IMPORT */
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
    .foto-preview-wrap{position:relative;margin-bottom:14px;}
    .foto-preview-wrap img{width:100%;height:110px;object-fit:cover;border-radius:10px;border:1.5px solid var(--border);display:block;}
    .btn-remove-foto{position:absolute;top:8px;right:8px;background:rgba(220,38,38,.85);color:white;border:none;border-radius:7px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:4px;}
    .btn-remove-foto:hover{background:#B91C1C;}
    .btn-modal-submit{width:100%;padding:11px;background:var(--blue-dark);color:white;border:none;border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 20px rgba(43,111,168,.28);transition:all .2s;margin-top:4px;}
    .btn-modal-submit:hover{background:var(--blue-deep);}
    .btn-modal-danger{width:100%;padding:11px;background:#DC2626;color:white;border:none;border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:10px;}
    .btn-modal-danger:hover{background:#B91C1C;}
    .btn-modal-cancel{width:100%;padding:10px;background:var(--bg);color:var(--muted);border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:8px;}
    .btn-modal-cancel:hover{background:#E5E7EB;}

    footer{background:var(--blue-deep);color:rgba(255,255,255,.6);text-align:center;padding:20px;font-size:12px;}

    @media(max-width:768px){
      .navbar{position:relative;}.nav-links{display:none;}
      .nav-hamburger{display:flex;align-items:center;justify-content:center;}.nav-brand{margin-right:0;}
      .page-wrapper{padding:16px 12px 80px;}.page-title{font-size:20px;}
      .page-header{flex-direction:column;align-items:stretch;gap:10px;}.page-header .btn{justify-content:center;padding:12px;}

      /* Toolbar mobile: search full width, lalu filter+entri satu baris */
      .toolbar{flex-direction:column;gap:8px;}
      #searchFormRuangan{width:100%;flex:unset;}
      .search-wrap-inner{width:100%;min-width:unset;}

      /* Filter kerusakan (kiri) + show entries (kanan) dalam satu baris */
      #filterFormRuangan{display:flex !important;flex-wrap:nowrap;align-items:flex-end;gap:8px;width:100%;}
      .filter-group{flex:1;min-width:0;}
      .filter-group .filter-label{display:none;} /* sembunyikan label agar lebih compact */
      .filter-select{width:100%;}
      #filterFormRuangan>[style*="margin-left:auto"]{margin-left:0 !important;flex-shrink:0;}

      .table-wrap{display:none;}.mobile-room-list{display:block;}
      .card{margin:0;border-radius:12px;}
      .table-footer{flex-direction:column;align-items:flex-start;gap:10px;padding:12px 16px;}
      .pag-btns{width:100%;justify-content:center;}
      .card-header{padding:13px 14px;}
      .modal-box{margin:10px;padding:20px 16px;border-radius:16px;max-width:100%;}
      .form-row,.form-row-3{grid-template-columns:1fr;gap:0;}
    }
  </style>
</head>
<body>

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
    <a href="ruangan.php"      class="nav-link active">Prasarana</a>
    <a href="barang.php"       class="nav-link">Sarana</a>
    <a href="pengguna.php"     class="nav-link">
      Pengguna<?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?>
    </a>
    <a href="peminjaman.php"   class="nav-link">
      Peminjaman<?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?>
    </a>
    <a href="pengembalian.php" class="nav-link">Pengembalian</a>
    <a href="../auth/logout.php" class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>
  <button class="nav-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()"><i class="bi bi-list" id="hamburgerIcon"></i></button>
</nav>
<div class="nav-mobile-menu" id="mobileMenu">
  <a href="dashboard.php"      class="nav-link">Dashboard</a>
  <a href="ruangan.php"        class="nav-link active">Prasarana</a>
  <a href="barang.php"         class="nav-link">Sarana</a>
  <a href="pengguna.php"       class="nav-link">Pengguna<?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?></a>
  <a href="peminjaman.php"     class="nav-link">Peminjaman<?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?></a>
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

  <div class="page-header">
    <div>
      <div class="page-title">Data Prasarana</div>
      <div class="page-sub"><?= $total ?> ruangan terdaftar</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <button class="btn btn-secondary" onclick="openImportModal()">
        <i class="bi bi-file-earmark-excel"></i> Import Excel
      </button>
      <button class="btn btn-primary" onclick="openAddModal()">
        <i class="bi bi-plus-lg"></i> Tambah Prasarana
      </button>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <form method="GET" id="searchFormRuangan" style="display:flex;gap:0;flex:1;min-width:200px;">
      <?php if ($filter_kerusakan): ?><input type="hidden" name="kerusakan" value="<?= $filter_kerusakan ?>"><?php endif; ?>
      <?php if ($per_page!=5): ?><input type="hidden" name="per_page" value="<?= $per_page ?>"><?php endif; ?>
      <div class="search-wrap-inner">
        <i class="bi bi-search search-icon"></i>
        <input type="text" name="q" id="searchInputRuangan" class="search-input"
               placeholder="Cari nama prasarana..."
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <button type="submit" class="btn-search"><i class="bi bi-search"></i></button>
    </form>

    <form method="GET" id="filterFormRuangan" style="display:contents;">
      <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
      <?php if ($per_page!=5): ?><input type="hidden" name="per_page" value="<?= $per_page ?>"><?php endif; ?>
      <div class="filter-group">
        <span class="filter-label">Kategori Kerusakan</span>
        <select name="kerusakan" class="filter-select" onchange="this.form.submit()">
          <option value="">Semua</option>
          <option value="baik"   <?= $filter_kerusakan==='baik'  ?'selected':'' ?>>Baik (0%)</option>
          <option value="ringan" <?= $filter_kerusakan==='ringan'?'selected':'' ?>>Rusak Ringan (1–30%)</option>
          <option value="sedang" <?= $filter_kerusakan==='sedang'?'selected':'' ?>>Rusak Sedang (31–45%)</option>
          <option value="berat"  <?= $filter_kerusakan==='berat' ?'selected':'' ?>>Rusak Berat (46–100%)</option>
        </select>
      </div>
      <?php if ($search || $filter_kerusakan): ?>
      <a href="ruangan.php" class="btn btn-secondary btn-sm" style="align-self:flex-end;height:42px;"><i class="bi bi-x"></i> Reset</a>
      <?php endif; ?>
      <div style="margin-left:auto;display:flex;align-items:center;gap:6px;align-self:flex-end;">
        <span class="show-entries-label">Tampilkan</span>
        <select class="show-entries-select" name="per_page" onchange="this.form.submit()">
          <option value="5"   <?= $per_page==5?'selected':'' ?>>5</option>
          <option value="10"  <?= $per_page==10?'selected':'' ?>>10</option>
          <option value="20"  <?= $per_page==20?'selected':'' ?>>20</option>
          <option value="25"  <?= $per_page==25?'selected':'' ?>>25</option>
          <option value="50"  <?= $per_page==50?'selected':'' ?>>50</option>
          <option value="100" <?= $per_page==100?'selected':'' ?>>100</option>
        </select>
        <span class="show-entries-label">entri</span>
      </div>
    </form>
  </div>



  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-building"></i> Daftar Prasarana</div>
      <span style="font-size:12px;color:var(--muted);"><?= number_format($total) ?> prasarana</span>
    </div>

    <!-- Desktop Table -->
    <div class="table-wrap">
      <table class="inv-table" id="ruanganTable">
        <thead>
          <tr>
            <th style="width:44px;">No</th>
            <th onclick="sortTable(1)" id="th1">Nama Prasarana <span class="sort-icon bi bi-chevron-expand"></span></th>
            <th onclick="sortTable(2)" id="th2" style="width:200px;">Keterangan <span class="sort-icon bi bi-chevron-expand"></span></th>
            <th onclick="sortTable(3)" id="th3" style="width:90px;text-align:center;">P (m) <span class="sort-icon bi bi-chevron-expand"></span></th>
            <th onclick="sortTable(4)" id="th4" style="width:90px;text-align:center;">L (m) <span class="sort-icon bi bi-chevron-expand"></span></th>
            <th onclick="sortTable(5)" id="th5" style="width:160px;">Tingkat Kerusakan <span class="sort-icon bi bi-chevron-expand"></span></th>

            <th onclick="sortTable(7)" id="th7" style="width:100px;text-align:center;">Jenis Brg <span class="sort-icon bi bi-chevron-expand"></span></th>
            <th style="width:110px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ruangan_list as $i => $room):
            $nm = strtolower($room['nama_ruangan']);
            if      (str_contains($nm,'perpustakaan'))                        { $ikon='<i class="bi bi-book"></i>'; $grad='135deg,#FFF8E1,#FFE082'; }
            elseif  (str_contains($nm,'laborator'))                           { $ikon='<i class="bi bi-activity"></i>'; $grad='135deg,#E8F5E9,#A5D6A7'; }
            elseif  (str_contains($nm,'olahraga')||str_contains($nm,'gym'))   { $ikon='<i class="bi bi-dribbble"></i>'; $grad='135deg,#E3F2FD,#90CAF9'; }
            elseif  (str_contains($nm,'guru'))                                { $ikon='<i class="bi bi-people"></i>'; $grad='135deg,#F3E5F5,#CE93D8'; }
            elseif  (str_contains($nm,'kepala'))                              { $ikon='<i class="bi bi-bank"></i>'; $grad='135deg,#FBE9E7,#FFAB91'; }
            elseif  (str_contains($nm,'tata usaha')||str_contains($nm,'tu'))  { $ikon='<i class="bi bi-clipboard-data"></i>'; $grad='135deg,#E8EAF6,#9FA8DA'; }
            elseif  (str_contains($nm,'komputer'))                            { $ikon='<i class="bi bi-pc-display"></i>'; $grad='135deg,#E0F7FA,#80DEEA'; }
            elseif  (str_contains($nm,'aula'))                                { $ikon='<i class="bi bi-mic"></i>'; $grad='135deg,#FCE4EC,#F48FB1'; }
            elseif  (str_contains($nm,'kantin'))                              { $ikon='<i class="bi bi-cup-hot"></i>'; $grad='135deg,#FFF3E0,#FFCC80'; }
            elseif  (str_contains($nm,'mushola')||str_contains($nm,'masjid')) { $ikon='<i class="bi bi-moon-stars"></i>'; $grad='135deg,#E8F5E9,#81C784'; }
            elseif  (str_contains($nm,'waka')||str_contains($nm,'wakil'))     { $ikon='<i class="bi bi-building"></i>'; $grad='135deg,#E3F2FD,#64B5F6'; }
            else                                                               { $ikon='<i class="bi bi-house-door"></i>'; $grad='135deg,#EAF3FC,#D0E8F8'; }
            $no = ($page - 1) * $per_page + $i + 1;
            $persen = (float)($room['persentase_kerusakan'] ?? 0);
            if ($persen == 0)       { $rusak_cls='rusak-0';   $bar_color='#16A34A'; }
            elseif ($persen <= 30)  { $rusak_cls='rusak-low'; $bar_color='#D97706'; }
            elseif ($persen <= 45)  { $rusak_cls='rusak-mid'; $bar_color='#EA580C'; }
            else                    { $rusak_cls='rusak-high';$bar_color='#DC2626'; }

            // Build JS data for edit modal
            $room_js = htmlspecialchars(json_encode([
              'id'         => $room['id_ruangan'],
              'nama'       => $room['nama_ruangan'],
              'keterangan' => $room['keterangan'] ?? '',
              'foto'       => $room['foto'] ?? '',
              'panjang'    => $room['panjang'] ?? '',
              'lebar'      => $room['lebar'] ?? '',
              'persentase' => $room['persentase_kerusakan'] ?? 0,
            ]), ENT_QUOTES);
          ?>
          <tr onclick="window.location='barang.php?ruangan=<?= $room['id_ruangan'] ?>&nama=<?= urlencode($room['nama_ruangan']) ?>'">
            <td style="color:var(--muted);font-size:12px;"><?= $no ?></td>
            <td>
              <div class="td-room">
                <div class="td-thumb" style="background:linear-gradient(<?= $grad ?>);">
                  <?php if (!empty($room['foto'])): ?>
                    <img src="../assets/ruangan/<?= htmlspecialchars($room['foto']) ?>" alt="">
                  <?php else: ?>
                    <?= $ikon ?>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($room['nama_ruangan']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <div style="font-size:12px;color:var(--muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= $room['keterangan'] ? htmlspecialchars($room['keterangan']) : '<span style="color:#C0CCE0;">—</span>' ?>
              </div>
            </td>
            <td style="text-align:center;font-weight:600;font-size:13px;"><?= $room['panjang'] ?: '<span style="color:var(--muted);">—</span>' ?></td>
            <td style="text-align:center;font-weight:600;font-size:13px;"><?= $room['lebar']   ?: '<span style="color:var(--muted);">—</span>' ?></td>
            <td>
              <div class="rusak-bar-wrap">
                <div class="rusak-bar-track">
                  <div class="rusak-bar-fill" style="width:<?= $persen ?>%;background:<?= $bar_color ?>;"></div>
                </div>
                <span class="rusak-pct <?= $rusak_cls ?>"><?= number_format($persen, 0) ?>%</span>
              </div>
            </td>

            <td style="text-align:center;">
              <span class="badge badge-info"><?= $room['jml_barang'] ?> jenis</span>
            </td>
            <td style="text-align:center;" onclick="event.stopPropagation();">
              <button class="btn btn-sm btn-secondary" title="Edit" style="margin-right:4px;"
                      onclick="openEditModal(<?= $room_js ?>)">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-sm btn-danger" title="Hapus"
                      onclick="openDeleteModal(<?= $room['id_ruangan'] ?>, '<?= htmlspecialchars(addslashes($room['nama_ruangan'])) ?>')">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($ruangan_list)): ?>
          <tr><td colspan="7">
            <div class="empty-state">
              <i class="bi bi-<?= ($search || $filter_kerusakan) ? 'search' : 'building' ?>"></i>
              <h3><?= ($search || $filter_kerusakan) ? 'Tidak ditemukan' : 'Belum ada prasarana' ?></h3>
              <p><?php
                if ($search && $filter_kerusakan)
                  echo 'Tidak ada prasarana cocok dengan pencarian &ldquo;'.htmlspecialchars($search).'&rdquo; pada kategori tersebut.';
                elseif ($search)
                  echo 'Tidak ada prasarana yang cocok dengan &ldquo;'.htmlspecialchars($search).'&rdquo;.';
                elseif ($filter_kerusakan)
                  echo 'Tidak ada prasarana dengan kategori kerusakan ini.';
                else
                  echo 'Mulai dengan menambahkan prasarana pertama.';
              ?></p>
            </div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile Card List -->
    <div class="mobile-room-list">
      <?php foreach ($ruangan_list as $room):
        $nm2 = strtolower($room['nama_ruangan']);
        if      (str_contains($nm2,'perpustakaan'))                         { $ikon2='<i class="bi bi-book"></i>'; $grad2='135deg,#FFF8E1,#FFE082'; }
        elseif  (str_contains($nm2,'laborator'))                            { $ikon2='<i class="bi bi-activity"></i>'; $grad2='135deg,#E8F5E9,#A5D6A7'; }
        elseif  (str_contains($nm2,'olahraga')||str_contains($nm2,'gym'))   { $ikon2='<i class="bi bi-dribbble"></i>'; $grad2='135deg,#E3F2FD,#90CAF9'; }
        elseif  (str_contains($nm2,'guru'))                                 { $ikon2='<i class="bi bi-people"></i>'; $grad2='135deg,#F3E5F5,#CE93D8'; }
        elseif  (str_contains($nm2,'kepala'))                               { $ikon2='<i class="bi bi-bank"></i>'; $grad2='135deg,#FBE9E7,#FFAB91'; }
        elseif  (str_contains($nm2,'tata usaha')||str_contains($nm2,'tu'))  { $ikon2='<i class="bi bi-clipboard-data"></i>'; $grad2='135deg,#E8EAF6,#9FA8DA'; }
        elseif  (str_contains($nm2,'komputer'))                             { $ikon2='<i class="bi bi-pc-display"></i>'; $grad2='135deg,#E0F7FA,#80DEEA'; }
        elseif  (str_contains($nm2,'aula'))                                 { $ikon2='<i class="bi bi-mic"></i>'; $grad2='135deg,#FCE4EC,#F48FB1'; }
        elseif  (str_contains($nm2,'kantin'))                               { $ikon2='<i class="bi bi-cup-hot"></i>'; $grad2='135deg,#FFF3E0,#FFCC80'; }
        elseif  (str_contains($nm2,'mushola')||str_contains($nm2,'masjid')) { $ikon2='<i class="bi bi-moon-stars"></i>'; $grad2='135deg,#E8F5E9,#81C784'; }
        elseif  (str_contains($nm2,'waka')||str_contains($nm2,'wakil'))     { $ikon2='<i class="bi bi-building"></i>'; $grad2='135deg,#E3F2FD,#64B5F6'; }
        else                                                                { $ikon2='<i class="bi bi-house-door"></i>'; $grad2='135deg,#EAF3FC,#D0E8F8'; }
        $persen2 = (float)($room['persentase_kerusakan'] ?? 0);
        if ($persen2 == 0)        { $rc2='rusak-0';   $bc2='#16A34A'; }
        elseif ($persen2 <= 30)   { $rc2='rusak-low'; $bc2='#D97706'; }
        elseif ($persen2 <= 45)   { $rc2='rusak-mid'; $bc2='#EA580C'; }
        else                      { $rc2='rusak-high';$bc2='#DC2626'; }
        $room_js2 = htmlspecialchars(json_encode([
          'id'         => $room['id_ruangan'],
          'nama'       => $room['nama_ruangan'],
          'keterangan' => $room['keterangan'] ?? '',
          'foto'       => $room['foto'] ?? '',
          'panjang'    => $room['panjang'] ?? '',
          'lebar'      => $room['lebar'] ?? '',
          'persentase' => $room['persentase_kerusakan'] ?? 0,
        ]), ENT_QUOTES);
      ?>
      <div class="mobile-room-item"
           onclick="window.location='barang.php?ruangan=<?= $room['id_ruangan'] ?>&nama=<?= urlencode($room['nama_ruangan']) ?>'">
        <div class="mobile-room-item-inner">
          <div class="mobile-room-thumb" style="background:linear-gradient(<?= $grad2 ?>);">
            <?php if (!empty($room['foto'])): ?>
              <img src="../assets/ruangan/<?= htmlspecialchars($room['foto']) ?>" alt="">
            <?php else: ?>
              <?= $ikon2 ?>
            <?php endif; ?>
          </div>
          <div class="mobile-room-info">
            <div class="mobile-room-name"><?= htmlspecialchars($room['nama_ruangan']) ?></div>
            <div class="mobile-room-meta">

              <span class="badge badge-info"><?= $room['jml_barang'] ?> jenis</span>
              <?php if ($persen2 > 0): ?>
                <span class="mobile-room-pill" style="color:<?= $bc2 ?>;"><i class="bi bi-tools" style="font-size:10px;"></i> <?= number_format($persen2,0) ?>% rusak</span>
              <?php endif; ?>
              <?php if ($room['panjang'] && $room['lebar']): ?>
                <span class="mobile-room-pill"><i class="bi bi-rulers" style="font-size:10px;"></i> <?= $room['panjang'] ?>×<?= $room['lebar'] ?>m</span>
              <?php endif; ?>
            </div>
            <?php if ($room['keterangan']): ?>
            <div style="font-size:11px;color:var(--muted);margin-top:4px;"><?= htmlspecialchars(mb_strimwidth($room['keterangan'],0,60,'...')) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="mobile-room-actions" onclick="event.stopPropagation();">
          <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?= $room_js2 ?>)">
            <i class="bi bi-pencil"></i> Edit
          </button>
          <button class="btn btn-sm btn-danger"
                  onclick="openDeleteModal(<?= $room['id_ruangan'] ?>, '<?= htmlspecialchars(addslashes($room['nama_ruangan'])) ?>')">
            <i class="bi bi-trash"></i> Hapus
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total > 0): ?>
    <div class="table-footer">
      <span>Menampilkan <?= ($page-1)*$per_page+1 ?>–<?= min($page*$per_page,$total) ?> dari <?= number_format($total) ?> prasarana</span>
      <div class="pag-btns">
        <?php $bu = '?'.($search?'q='.urlencode($search).'&':'').($filter_kerusakan?'kerusakan='.$filter_kerusakan.'&':'').($per_page!=5?'per_page='.$per_page.'&':''); ?>
        <a href="<?= $bu ?>page=<?= $page-1 ?>" class="pag-btn pag-btn-text <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i> Previous</a>
        <?php for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
          <a href="<?= $bu ?>page=<?= $i ?>" class="pag-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $bu ?>page=<?= $page+1 ?>" class="pag-btn pag-btn-text <?= $page>=$total_pages?'disabled':'' ?>">Next <i class="bi bi-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- /card -->

</div><!-- /page-wrapper -->

<footer>&copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak</footer>


<!-- MODAL TAMBAH -->
<div class="modal-backdrop" id="modalAdd" onclick="handleBackdropClick(event,'modalAdd')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalAdd')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Tambah Prasarana</div>
    <div class="modal-sub">Isi data prasarana sesuai format inventaris.</div>
    <div class="modal-error" id="addError"></div>
    <form method="POST" enctype="multipart/form-data" onsubmit="return validateInput('inputAdd','addError')">
      <div class="form-group">
        <label class="form-label">Nama Prasarana <span style="color:#DC2626;">*</span></label>
        <input type="text" name="nama_ruangan" id="inputAdd" class="form-control" placeholder="Contoh: Kelas XII IPA 1" autocomplete="off">
      </div>
      <div class="form-group">
        <label class="form-label">Keterangan</label>
        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Gedung A Lantai 2">
      </div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Panjang (m)</label>
          <input type="number" name="panjang" class="form-control" placeholder="9" min="0" step="0.1">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Lebar (m)</label>
          <input type="number" name="lebar" class="form-control" placeholder="7" min="0" step="0.1">
        </div>
      </div>
      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Tingkat Kerusakan (%)</label>
        <input type="number" name="persentase_kerusakan" class="form-control" value="0" min="0" max="100" step="0.01" placeholder="0">
      </div>
      <div style="margin-top:14px;" id="addFotoPreview" style="display:none;" class="foto-preview-wrap">
        <img id="addFotoImg" src="" alt="Preview">
        <button type="button" class="btn-remove-foto" onclick="removeFotoAdd()"><i class="bi bi-x"></i> Hapus</button>
      </div>
      <div class="upload-area" id="addUploadArea" style="margin-top:14px;">
        <input type="file" name="foto_ruangan" id="addFotoInput" accept="image/jpeg,image/png,image/webp"
               onchange="previewFoto(this,'addFotoImg','addFotoPreview','addUploadArea')">
        <div class="upload-area-icon"><i class="bi bi-image"></i></div>
        <div class="upload-area-text"><strong>Klik untuk upload foto</strong>JPG, PNG, WebP — maks 3MB (opsional)</div>
      </div>
      <button type="submit" name="tambah_ruangan" class="btn-modal-submit">
        <i class="bi bi-plus-circle"></i> Tambah Prasarana
      </button>
    </form>
  </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-backdrop" id="modalEdit" onclick="handleBackdropClick(event,'modalEdit')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalEdit')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Edit Prasarana</div>
    <div class="modal-sub">Ubah data prasarana.</div>
    <div class="modal-error" id="editError"></div>
    <form method="POST" enctype="multipart/form-data" onsubmit="return validateInput('inputEdit','editError')">
      <input type="hidden" name="id_ruangan" id="editId">
      <input type="hidden" name="hapus_foto" id="editHapusFoto" value="">
      <div class="form-group">
        <label class="form-label">Nama Prasarana <span style="color:#DC2626;">*</span></label>
        <input type="text" name="nama_ruangan" id="inputEdit" class="form-control" placeholder="Nama prasarana">
      </div>
      <div class="form-group">
        <label class="form-label">Keterangan</label>
        <input type="text" name="keterangan" id="editKeterangan" class="form-control" placeholder="Contoh: Gedung A Lantai 2">
      </div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Panjang (m)</label>
          <input type="number" name="panjang" id="editPanjang" class="form-control" placeholder="9" min="0" step="0.1">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Lebar (m)</label>
          <input type="number" name="lebar" id="editLebar" class="form-control" placeholder="7" min="0" step="0.1">
        </div>
      </div>
      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Tingkat Kerusakan (%)</label>
        <input type="number" name="persentase_kerusakan" id="editPersen" class="form-control" min="0" max="100" step="0.01">
      </div>
      <div style="margin-top:14px;" id="editFotoLamaWrap" style="display:none;" class="foto-preview-wrap">
        <img id="editFotoLamaImg" src="" alt="Foto saat ini">
        <button type="button" class="btn-remove-foto" onclick="hapusFotoLama()"><i class="bi bi-x"></i> Hapus Foto</button>
      </div>
      <div id="editFotoBaruWrap" style="display:none;" class="foto-preview-wrap">
        <img id="editFotoBaruImg" src="" alt="Preview baru">
        <button type="button" class="btn-remove-foto" onclick="removeFotoEdit()"><i class="bi bi-x"></i> Batal</button>
      </div>
      <div class="upload-area" id="editUploadArea" style="margin-top:14px;">
        <input type="file" name="foto_ruangan" id="editFotoInput" accept="image/jpeg,image/png,image/webp"
               onchange="previewFoto(this,'editFotoBaruImg','editFotoBaruWrap','editUploadArea')">
        <div class="upload-area-icon"><i class="bi bi-image"></i></div>
        <div class="upload-area-text"><strong>Klik untuk ganti foto</strong>JPG, PNG, WebP — maks 3MB</div>
      </div>
      <button type="submit" name="edit_ruangan" class="btn-modal-submit">
        <i class="bi bi-check-circle"></i> Simpan Perubahan
      </button>
    </form>
  </div>
</div>

<!-- MODAL HAPUS -->
<div class="modal-backdrop" id="modalDelete" onclick="handleBackdropClick(event,'modalDelete')">
  <div class="modal-box" style="max-width:400px;">
    <button class="modal-close" onclick="closeModal('modalDelete')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title" style="color:#DC2626;">Hapus Prasarana</div>
    <div class="modal-sub">Apakah kamu yakin ingin menghapus prasarana ini <strong id="deleteNama"></strong>?<br>Prasarana yang masih memiliki barang tidak dapat dihapus.</div>
    <form method="POST">
      <input type="hidden" name="id_ruangan" id="deleteId">
      <button type="submit" name="hapus_ruangan" class="btn-modal-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalDelete')">Batal</button>
    </form>
  </div>
</div>

<script>
  function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('hamburgerIcon');
    const open = menu.classList.toggle('open');
    icon.className = open ? 'bi bi-x-lg' : 'bi bi-list';
  }
  document.addEventListener('click', e => {
    const menu = document.getElementById('mobileMenu');
    const btn  = document.getElementById('hamburgerBtn');
    if (menu.classList.contains('open') && !menu.contains(e.target) && !btn.contains(e.target)) {
      menu.classList.remove('open');
      document.getElementById('hamburgerIcon').className = 'bi bi-list';
    }
  });

  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    const errMap = { modalAdd:'addError', modalEdit:'editError' };
    if (errMap[id]) document.getElementById(errMap[id]).classList.remove('show');
  }
  function handleBackdropClick(e, id) { if (e.target === document.getElementById(id)) closeModal(id); }
  document.addEventListener('keydown', e => { if(e.key==='Escape') ['modalAdd','modalEdit','modalDelete'].forEach(closeModal); });

  function openAddModal() {
    removeFotoAdd();
    document.getElementById('inputAdd').value = '';
    openModal('modalAdd');
    setTimeout(() => document.getElementById('inputAdd').focus(), 80);
  }

  function openEditModal(data) {
    document.getElementById('editId').value            = data.id;
    document.getElementById('inputEdit').value         = data.nama;
    document.getElementById('editKeterangan').value    = data.keterangan || '';
    document.getElementById('editPanjang').value       = data.panjang || '';
    document.getElementById('editLebar').value         = data.lebar || '';
    document.getElementById('editPersen').value        = data.persentase || 0;

    document.getElementById('editHapusFoto').value     = '';
    document.getElementById('editFotoInput').value     = '';
    document.getElementById('editFotoBaruWrap').style.display = 'none';
    document.getElementById('editFotoBaruImg').src     = '';
    const lamaWrap   = document.getElementById('editFotoLamaWrap');
    const uploadArea = document.getElementById('editUploadArea');
    if (data.foto) {
      document.getElementById('editFotoLamaImg').src = '../assets/ruangan/' + data.foto;
      lamaWrap.style.display   = '';
      uploadArea.style.display = 'none';
      delete lamaWrap.dataset.hidden;
    } else {
      lamaWrap.style.display   = 'none';
      uploadArea.style.display = '';
    }
    openModal('modalEdit');
    setTimeout(() => document.getElementById('inputEdit').focus(), 80);
  }

  function openDeleteModal(id, nama) {
    document.getElementById('deleteId').value         = id;
    document.getElementById('deleteNama').textContent = nama;
    openModal('modalDelete');
  }

  function validateInput(inputId, errId) {
    const val = document.getElementById(inputId).value.trim();
    const err = document.getElementById(errId);
    if (!val) { err.textContent='Nama prasarana tidak boleh kosong.'; err.classList.add('show'); return false; }
    err.classList.remove('show'); return true;
  }

  function previewFoto(input, imgId, wrapId, uploadAreaId) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById(imgId).src                  = e.target.result;
      document.getElementById(wrapId).style.display       = '';
      document.getElementById(uploadAreaId).style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }

  function removeFotoAdd() {
    document.getElementById('addFotoInput').value           = '';
    document.getElementById('addFotoPreview').style.display = 'none';
    document.getElementById('addFotoImg').src               = '';
    document.getElementById('addUploadArea').style.display  = '';
  }

  function removeFotoEdit() {
    document.getElementById('editFotoInput').value            = '';
    document.getElementById('editFotoBaruWrap').style.display = 'none';
    document.getElementById('editFotoBaruImg').src            = '';
    const lamaWrap = document.getElementById('editFotoLamaWrap');
    if (lamaWrap.dataset.hidden !== '1') {
      document.getElementById('editUploadArea').style.display = '';
    }
  }

  function hapusFotoLama() {
    document.getElementById('editHapusFoto').value             = '1';
    document.getElementById('editFotoLamaWrap').style.display  = 'none';
    document.getElementById('editFotoLamaWrap').dataset.hidden = '1';
    document.getElementById('editUploadArea').style.display    = '';
  }

  /* Sort table */
  let sortCol = -1, sortAsc = true;
  function sortTable(col) {
    const table = document.getElementById('ruanganTable');
    const tbody = table.querySelector('tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr'));
    const ths   = table.querySelectorAll('thead th');
    sortAsc = (sortCol === col) ? !sortAsc : true;
    sortCol = col;
    ths.forEach((th, i) => {
      th.classList.toggle('sorted', i === col);
      const icon = th.querySelector('.sort-icon');
      if (icon) icon.className = 'sort-icon bi ' + (i !== col ? 'bi-chevron-expand' : (sortAsc ? 'bi-chevron-up' : 'bi-chevron-down'));
    });
    rows.sort((a, b) => {
      const aVal = a.cells[col]?.textContent.trim() ?? '';
      const bVal = b.cells[col]?.textContent.trim() ?? '';
      const aNum = parseFloat(aVal);
      const bNum = parseFloat(bVal);
      const cmp  = (!isNaN(aNum) && !isNaN(bNum)) ? aNum - bNum : aVal.localeCompare(bVal, 'id');
      return sortAsc ? cmp : -cmp;
    });
    rows.forEach(r => tbody.appendChild(r));
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
  const mobileList = document.querySelector('.mobile-room-list');
  if (tableWrap) tableWrap.style.opacity = '0.5';
  if (mobileList) mobileList.style.opacity = '0.5';
  fetch(url).then(r => r.text()).then(html => {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const newTbody = doc.querySelector('.inv-table tbody');
    const curTbody = document.querySelector('.inv-table tbody');
    if (newTbody && curTbody) curTbody.innerHTML = newTbody.innerHTML;
    const newMobile = doc.querySelector('.mobile-room-list');
    const curMobile = document.querySelector('.mobile-room-list');
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
initTableControls('searchFormRuangan', 'searchInputRuangan');

  /* ── BFCache Fix: paksa reload jika halaman dari back-forward cache ── */
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
  });

  function openImportModal() {
    document.getElementById('modalImportRuangan').classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function showImportFile(input, nameId) {
    const el = document.getElementById(nameId);
    if (input.files.length) { el.textContent = input.files[0].name; el.style.display = 'block'; }
  }
</script>

<!-- ══ MODAL IMPORT EXCEL RUANGAN ══ -->
<div class="modal-backdrop" id="modalImportRuangan" onclick="if(event.target===this){closeModal('modalImportRuangan')}">
  <div class="modal-box" style="max-width:580px;">
    <button class="modal-close" onclick="closeModal('modalImportRuangan')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title"><i class="bi bi-file-earmark-excel" style="color:#16A34A;"></i> Import Prasarana via Excel</div>
    <div class="modal-sub" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <span>Upload file Excel (.xlsx/.xls) atau CSV untuk menambahkan banyak prasarana sekaligus.</span>
      <a href="ruangan_template.xlsx" download
         style="display:inline-flex;align-items:center;gap:5px;color:#16A34A;font-weight:700;font-size:12px;text-decoration:none;background:#F0FDF4;border:1px solid #BBF7D0;padding:5px 12px;border-radius:8px;white-space:nowrap;">
        <i class="bi bi-download"></i> Download Template
      </a>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
      <div class="upload-area-xl">
        <input type="file" name="excel_ruangan" id="excelRuanganInput" accept=".xlsx,.xls,.csv"
               onchange="showImportFile(this,'excelRuanganName')">
        <div class="upload-area-xl-icon"><i class="bi bi-file-earmark-excel"></i></div>
        <div class="upload-area-xl-text">
          <strong>Klik untuk pilih file Excel</strong>
          Format: .xlsx / .xls / .csv — maks 5MB
        </div>
        <div class="upload-filename" id="excelRuanganName"></div>
      </div>
      <button type="submit" name="import_ruangan_excel" class="btn-modal-submit">
        <i class="bi bi-upload"></i> Import Prasarana
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalImportRuangan')">Batal</button>
    </form>
  </div>
</div>

</body>
</html>