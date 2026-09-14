<?php
session_start();
require_once "../config/koneksi.php";
require_once "../config/auth_admin.php";
/** @var mysqli $conn  —  defined in koneksi.php */

/* ── Daftar ruangan untuk filter dropdown ── */
$ruangan_all = [];
$rq = mysqli_query($conn, "SELECT id_ruangan, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC");
while ($rv = mysqli_fetch_assoc($rq)) $ruangan_all[] = $rv;

/* POST HANDLER */
$msg_success = '';
$msg_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ── UPDATE STOK (tambah / kurangi) ── */
    if (isset($_POST['update_stok'])) {
        $id_b      = (int)$_POST['id_barang'];
        $aksi      = $_POST['aksi_stok'] ?? '';
        $jumlah    = max(1, (int)$_POST['jumlah_stok']);
        $keterangan = trim(mysqli_real_escape_string($conn, $_POST['keterangan_stok'] ?? ''));

        // Ambil data barang saat ini
        $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM barang WHERE id_barang=$id_b AND kategori='Barang Habis Pakai' LIMIT 1"));
        if (!$cur) {
            $msg_error = "Barang tidak ditemukan atau bukan kategori Habis Pakai.";
        } elseif ($aksi === 'tambah') {
            $new_laik = (int)$cur['jumlah_laik'] + $jumlah;
            $new_total = $new_laik + (int)$cur['jumlah_tidak_laik'];
            mysqli_query($conn, "UPDATE barang SET jumlah_laik=$new_laik, jumlah=$new_total WHERE id_barang=$id_b");

            // Catat riwayat
            $ket_sql = $keterangan ? "'$keterangan'" : 'NULL';
            mysqli_query($conn, "INSERT INTO riwayat_stok (id_barang, jenis, jumlah, stok_sebelum, stok_sesudah, keterangan, tanggal)
                VALUES ($id_b, 'masuk', $jumlah, {$cur['jumlah_laik']}, $new_laik, $ket_sql, NOW())");

            $back = "stok_habis_pakai.php?success=" . urlencode("Berhasil menambah $jumlah unit stok \"" . $cur['nama_barang'] . "\".");
            header("Location: $back");
            exit;
        } elseif ($aksi === 'kurangi') {
            if ($jumlah > (int)$cur['jumlah_laik']) {
                $msg_error = "Jumlah pengurangan ($jumlah) melebihi stok laik saat ini (" . $cur['jumlah_laik'] . ").";
            } else {
                $new_laik = (int)$cur['jumlah_laik'] - $jumlah;
                $new_total = $new_laik + (int)$cur['jumlah_tidak_laik'];
                mysqli_query($conn, "UPDATE barang SET jumlah_laik=$new_laik, jumlah=$new_total WHERE id_barang=$id_b");

                $ket_sql = $keterangan ? "'$keterangan'" : 'NULL';
                mysqli_query($conn, "INSERT INTO riwayat_stok (id_barang, jenis, jumlah, stok_sebelum, stok_sesudah, keterangan, tanggal)
                    VALUES ($id_b, 'keluar', $jumlah, {$cur['jumlah_laik']}, $new_laik, $ket_sql, NOW())");

                $back = "stok_habis_pakai.php?success=" . urlencode("Berhasil mengurangi $jumlah unit stok \"" . $cur['nama_barang'] . "\".");
                header("Location: $back");
                exit;
            }
        } else {
            $msg_error = "Aksi stok tidak valid.";
        }
    }
}

if (isset($_GET['success'])) $msg_success = htmlspecialchars($_GET['success']);

/* ── Search & Filter ── */
$search         = isset($_GET['q']) ? trim(mysqli_real_escape_string($conn, $_GET['q'])) : '';
$filter_ruangan = isset($_GET['filter_ruangan']) ? (int)$_GET['filter_ruangan'] : 0;
$filter_stok    = isset($_GET['filter_stok']) && in_array($_GET['filter_stok'], ['habis','menipis','tersedia'])
                  ? $_GET['filter_stok'] : '';

/* ── Pagination ── */
$valid_per_page = [5,10,20,25,50,100];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $valid_per_page)
            ? (int)$_GET['per_page'] : 10;
$page = max(1, (int)($_GET['page'] ?? 1));

/* ── WHERE ── */
$where = "WHERE b.kategori='Barang Habis Pakai'";
if ($filter_ruangan)      $where .= " AND b.id_ruangan=$filter_ruangan";
if ($search)              $where .= " AND (b.nama_barang LIKE '%$search%' OR b.kode_barang LIKE '%$search%')";
if ($filter_stok === 'habis')    $where .= " AND b.jumlah_laik = 0";
if ($filter_stok === 'menipis')  $where .= " AND b.jumlah_laik > 0 AND b.jumlah_laik <= 5";
if ($filter_stok === 'tersedia') $where .= " AND b.jumlah_laik > 5";

/* ── Count ── */
$total       = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM barang b $where"))['t'];
$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

/* ── Fetch ── */
$barang_q = mysqli_query($conn, "
    SELECT b.*, r.nama_ruangan
    FROM barang b
    LEFT JOIN ruangan r ON b.id_ruangan = r.id_ruangan
    $where
    ORDER BY b.jumlah_laik ASC, b.nama_barang ASC
    LIMIT $per_page OFFSET $offset
");

/* ── Summary stats ── */
$sum_all = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total_jenis,
            COALESCE(SUM(jumlah_laik),0) as total_laik,
            COALESCE(SUM(jumlah_tidak_laik),0) as total_tdk_laik,
            COALESCE(SUM(jumlah),0) as total_unit
     FROM barang WHERE kategori='Barang Habis Pakai'"));
$stat_jenis     = (int)$sum_all['total_jenis'];
$stat_laik      = (int)$sum_all['total_laik'];
$stat_tdk_laik  = (int)$sum_all['total_tdk_laik'];
$stat_total     = (int)$sum_all['total_unit'];

$stat_habis    = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM barang WHERE kategori='Barang Habis Pakai' AND jumlah_laik=0"))['t'];
$stat_menipis  = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM barang WHERE kategori='Barang Habis Pakai' AND jumlah_laik>0 AND jumlah_laik<=5"))['t'];

/* ── Riwayat stok terbaru ── */
$cek_tabel_riwayat = mysqli_query($conn, "SHOW TABLES LIKE 'riwayat_stok'");
$riwayat_exists = mysqli_num_rows($cek_tabel_riwayat) > 0;
$riwayat_list = [];
if ($riwayat_exists) {
    $riwayat_q = mysqli_query($conn, "
        SELECT rs.*, b.nama_barang
        FROM riwayat_stok rs
        JOIN barang b ON rs.id_barang = b.id_barang
        ORDER BY rs.tanggal DESC
        LIMIT 10
    ");
    while ($rw = mysqli_fetch_assoc($riwayat_q)) $riwayat_list[] = $rw;
}

$pending_count = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM pengguna WHERE status='pending'"))['t'];
$pm_menunggu   = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman WHERE status='menunggu'"))['t'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stok Habis Pakai — Inventaris SARPRAS</title>
  <link rel="icon" href="../assets/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{
      --blue:#4A90C4;--blue-dark:#2B6FA8;--blue-deep:#1B3F6E;
      --green:#3D9B4A;--yellow:#F5C518;
      --amber:#D97706;--amber-light:#FFFBEB;--amber-border:#FDE68A;
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
    .page-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:900;color:var(--text);display:flex;align-items:center;gap:10px;}
    .page-sub{font-size:13px;color:var(--muted);margin-top:3px;}

    /* ── SUMMARY PILLS ── */
    .summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:20px;}
    .summary-pill{display:flex;align-items:center;gap:10px;background:var(--card);border:1.5px solid var(--border);border-radius:12px;padding:12px 16px;transition:box-shadow .2s;}
    .summary-pill:hover{box-shadow:var(--shadow);}
    .summary-pill-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
    .summary-pill-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:900;line-height:1;}
    .summary-pill-lbl{font-size:11px;color:var(--muted);margin-top:2px;}

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
    .card-title i{color:var(--amber);}
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

    /* ── STOCK BAR ── */
    .stok-bar-wrap{width:100%;min-width:100px;}
    .stok-bar-info{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:4px;}
    .stok-bar-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:900;}
    .stok-bar-lbl{font-size:10px;color:var(--muted);}
    .stok-bar{height:8px;background:#E5E7EB;border-radius:99px;overflow:hidden;position:relative;}
    .stok-bar-fill{height:100%;border-radius:99px;transition:width .4s ease;}

    /* ── STATUS BADGE ── */
    .status-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
    .status-tersedia{background:#F0FDF4;color:#16A34A;border:1px solid #BBF7D0;}
    .status-menipis{background:#FFFBEB;color:#D97706;border:1px solid #FDE68A;}
    .status-habis{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;}

    /* ── BUTTONS ── */
    .btn{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:'DM Sans',sans-serif;}
    .btn-sm{padding:6px 10px;font-size:12px;border-radius:7px;}
    .btn-primary{background:var(--blue-dark);color:white;box-shadow:0 4px 14px rgba(43,111,168,.25);}
    .btn-primary:hover{background:var(--blue-deep);}
    .btn-secondary{background:var(--card);color:var(--text);border:1px solid var(--border);}
    .btn-secondary:hover{background:var(--bg);}
    .btn-amber{background:var(--amber);color:white;box-shadow:0 4px 14px rgba(217,119,6,.25);}
    .btn-amber:hover{background:#B45309;}
    .btn-success{background:#16A34A;color:white;}
    .btn-success:hover{background:#15803D;}
    .btn-danger-outline{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;}
    .btn-danger-outline:hover{background:#DC2626;color:white;}

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
    .empty-state i{font-size:40px;display:block;margin-bottom:12px;color:#FDE68A;}
    .empty-state h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px;}

    /* ── MOBILE CARD LIST ── */
    .mobile-list{display:none;}
    .mobile-item{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:10px;box-shadow:var(--shadow);}
    .mobile-item-header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px;}
    .mobile-item-actions{display:flex;gap:6px;flex-shrink:0;}
    .mobile-stok-row{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;align-items:center;}
    .mobile-stok-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;}

    /* ── MODAL ── */
    .modal-backdrop{display:none;position:fixed;inset:0;z-index:500;background:rgba(27,63,110,.35);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
    .modal-backdrop.open{display:flex;}
    .modal-box{background:var(--card);border-radius:18px;padding:28px 32px;width:100%;max-width:480px;box-shadow:var(--shadow-lg);position:relative;z-index:501;animation:modalIn .22s cubic-bezier(.4,0,.2,1);max-height:92vh;overflow-y:auto;}
    @keyframes modalIn{from{transform:scale(.94) translateY(10px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
    .modal-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--text);margin-bottom:6px;}
    .modal-sub{font-size:13px;color:var(--muted);margin-bottom:20px;}
    .modal-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:8px;background:var(--bg);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px;transition:all .2s;}
    .modal-close:hover{background:#FECACA;color:#DC2626;}
    .modal-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;border-radius:8px;padding:9px 12px;font-size:12px;margin-bottom:14px;display:none;}
    .modal-error.show{display:block;}
    .form-group{margin-bottom:14px;}
    .form-label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;}
    .form-control{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
    .form-control:focus{border-color:var(--blue);background:white;box-shadow:0 0 0 3px rgba(74,144,196,.14);}
    .form-hint{font-size:11px;color:var(--muted);margin-top:4px;}
    .btn-modal-submit{width:100%;padding:11px;border:none;border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:6px;}
    .btn-modal-cancel{width:100%;padding:10px;background:var(--bg);color:var(--muted);border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:8px;}
    .btn-modal-cancel:hover{background:#E5E7EB;}

    /* ── Aksi toggle ── */
    .aksi-tabs{display:flex;border:1.5px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:16px;}
    .aksi-tab{flex:1;padding:10px;text-align:center;font-size:13px;font-weight:700;cursor:pointer;transition:all .15s;background:var(--bg);color:var(--muted);border:none;font-family:'DM Sans',sans-serif;}
    .aksi-tab:first-child{border-right:1.5px solid var(--border);}
    .aksi-tab.active-tambah{background:#F0FDF4;color:#16A34A;}
    .aksi-tab.active-kurangi{background:#FEF2F2;color:#DC2626;}

    /* ── Stok info in modal ── */
    .stok-info-box{background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:16px;display:flex;align-items:center;gap:14px;}
    .stok-info-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
    .stok-info-name{font-weight:700;font-size:14px;margin-bottom:2px;}
    .stok-info-detail{font-size:12px;color:var(--muted);}
    .stok-info-detail strong{color:var(--text);}

    /* ── Riwayat Section ── */
    .riwayat-card{margin-top:24px;}
    .riwayat-item{display:flex;align-items:flex-start;gap:12px;padding:12px 20px;border-bottom:1px solid var(--border);}
    .riwayat-item:last-child{border-bottom:none;}
    .riwayat-dot{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;margin-top:2px;}
    .riwayat-dot-masuk{background:#F0FDF4;color:#16A34A;}
    .riwayat-dot-keluar{background:#FEF2F2;color:#DC2626;}
    .riwayat-text{flex:1;}
    .riwayat-text-main{font-size:13px;font-weight:600;}
    .riwayat-text-sub{font-size:11px;color:var(--muted);margin-top:2px;}
    .riwayat-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;white-space:nowrap;}

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
      .summary-row{grid-template-columns:repeat(2,1fr);}
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
    <a href="dashboard.php"         class="nav-link">Dashboard</a>
    <a href="ruangan.php"           class="nav-link">Prasarana</a>
    <a href="barang.php"            class="nav-link">Sarana</a>
    <a href="stok_habis_pakai.php"  class="nav-link active">Stok Habis Pakai</a>
    <a href="pengguna.php"          class="nav-link">Pengguna<?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?></a>
    <a href="peminjaman.php"        class="nav-link">Peminjaman<?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?></a>
    <a href="pengembalian.php"      class="nav-link">Pengembalian</a>
    <a href="../auth/logout.php"    class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>
  <button class="nav-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()"><i class="bi bi-list" id="hamburgerIcon"></i></button>
</nav>
<div class="nav-mobile-menu" id="mobileMenu">
  <a href="dashboard.php"         class="nav-link">Dashboard</a>
  <a href="ruangan.php"           class="nav-link">Prasarana</a>
  <a href="barang.php"            class="nav-link">Sarana</a>
  <a href="stok_habis_pakai.php"  class="nav-link active">Stok Habis Pakai</a>
  <a href="pengguna.php"          class="nav-link">Pengguna<?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?></a>
  <a href="peminjaman.php"        class="nav-link">Peminjaman<?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?></a>
  <a href="pengembalian.php"      class="nav-link">Pengembalian</a>
  <a href="../auth/logout.php"    class="nav-link logout">Logout</a>
</div>

<div class="page-wrapper">

  <?php if ($msg_success): ?><div class="flash flash-success"><i class="bi bi-check-circle-fill"></i> <?= $msg_success ?></div><?php endif; ?>
  <?php if ($msg_error):   ?><div class="flash flash-error"><i class="bi bi-exclamation-circle-fill"></i> <?= $msg_error ?></div><?php endif; ?>

  <div class="page-header">
    <div>
      <a href="barang.php" class="back-link"><i class="bi bi-arrow-left"></i> Kembali ke Sarana</a>
      <div class="page-title">
        <i class="bi bi-arrow-repeat" style="color:var(--amber);"></i>
        Stok Barang Habis Pakai
      </div>
      <div class="page-sub">Kelola dan pantau stok barang habis pakai (ATK, bahan lab, dll.)</div>
    </div>
  </div>

  <!-- Summary Pills -->
  <div class="summary-row">
    <div class="summary-pill">
      <div class="summary-pill-icon" style="background:#EEF2FF;color:#6366F1;"><i class="bi bi-box-seam"></i></div>
      <div>
        <div class="summary-pill-val" style="color:#6366F1;"><?= number_format($stat_jenis) ?></div>
        <div class="summary-pill-lbl">Jenis Barang</div>
      </div>
    </div>
    <div class="summary-pill" style="background:#F0FDF4;">
      <div class="summary-pill-icon" style="background:#DCFCE7;color:#16A34A;"><i class="bi bi-check-circle-fill"></i></div>
      <div>
        <div class="summary-pill-val" style="color:#16A34A;"><?= number_format($stat_laik) ?></div>
        <div class="summary-pill-lbl">Total Stok Tersedia</div>
      </div>
    </div>
    <div class="summary-pill" style="background:var(--amber-light);">
      <div class="summary-pill-icon" style="background:#FDE68A;color:#D97706;"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div>
        <div class="summary-pill-val" style="color:#D97706;"><?= number_format($stat_menipis) ?></div>
        <div class="summary-pill-lbl">Stok Menipis (≤5)</div>
      </div>
    </div>
    <div class="summary-pill" style="background:#FEF2F2;">
      <div class="summary-pill-icon" style="background:#FEE2E2;color:#DC2626;"><i class="bi bi-x-circle-fill"></i></div>
      <div>
        <div class="summary-pill-val" style="color:#DC2626;"><?= number_format($stat_habis) ?></div>
        <div class="summary-pill-lbl">Stok Habis</div>
      </div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="toolbar-row">
      <form method="GET" id="searchFormStok" style="display:flex;gap:0;flex:1;min-width:200px;">
        <?php if ($filter_ruangan): ?><input type="hidden" name="filter_ruangan" value="<?= $filter_ruangan ?>"><?php endif; ?>
        <?php if ($filter_stok): ?><input type="hidden" name="filter_stok" value="<?= $filter_stok ?>"><?php endif; ?>
        <?php if ($per_page!=10): ?><input type="hidden" name="per_page" value="<?= $per_page ?>"><?php endif; ?>
        <div class="search-wrap-inner">
          <i class="bi bi-search search-icon"></i>
          <input type="text" name="q" id="searchInputStok" value="<?= htmlspecialchars($search) ?>"
                 class="search-input" placeholder="Cari nama atau kode barang...">
        </div>
        <button type="submit" class="btn-search"><i class="bi bi-search"></i></button>
      </form>

      <div class="filter-group">
        <span class="filter-label">Prasarana</span>
        <select class="filter-select" onchange="applyFilter('filter_ruangan',this.value)">
          <option value="">Semua</option>
          <?php foreach ($ruangan_all as $rv): ?>
          <option value="<?= $rv['id_ruangan'] ?>" <?= $filter_ruangan==$rv['id_ruangan']?'selected':'' ?>><?= htmlspecialchars($rv['nama_ruangan']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <span class="filter-label">Status Stok</span>
        <select class="filter-select" onchange="applyFilter('filter_stok',this.value)">
          <option value="">Semua</option>
          <option value="tersedia" <?= $filter_stok==='tersedia'?'selected':'' ?>>Tersedia (>5)</option>
          <option value="menipis" <?= $filter_stok==='menipis'?'selected':'' ?>>Menipis (≤5)</option>
          <option value="habis" <?= $filter_stok==='habis'?'selected':'' ?>>Habis (0)</option>
        </select>
      </div>
      <div class="show-entries-inline">
        <span class="lbl">Tampil</span>
        <select class="sel" onchange="applyFilter('per_page',this.value)">
          <?php foreach ($valid_per_page as $vp): ?>
          <option value="<?= $vp ?>" <?= $per_page==$vp?'selected':'' ?>><?= $vp ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- Table Card -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i class="bi bi-arrow-repeat"></i> Daftar Barang Habis Pakai
      </div>
      <span style="font-size:12px;color:var(--muted);"><?= number_format($total) ?> Barang</span>
    </div>

    <div class="table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th style="width:38px;">No</th>
            <th>Nama Barang</th>
            <th style="width:130px;">Prasarana</th>
            <th class="center" style="width:170px;">Stok Tersedia</th>
            <th class="center" style="width:100px;">Status</th>
            <th class="center" style="width:100px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no = $offset + 1; $has = false;
          while ($b = mysqli_fetch_assoc($barang_q)):
            $has  = true;
            $laik = (int)($b['jumlah_laik'] ?? 0);
            $tdk  = (int)($b['jumlah_tidak_laik'] ?? 0);
            $tot  = $laik + $tdk;
            // Determine status
            if ($laik === 0) { $status = 'habis'; $status_label = 'Habis'; $status_icon = 'bi-x-circle'; }
            elseif ($laik <= 5) { $status = 'menipis'; $status_label = 'Menipis'; $status_icon = 'bi-exclamation-triangle'; }
            else { $status = 'tersedia'; $status_label = 'Tersedia'; $status_icon = 'bi-check-circle'; }
            // Bar percentage
            $bar_max = max($tot, 1);
            $bar_pct = round(($laik / $bar_max) * 100);
            $bar_color = $status === 'habis' ? '#DC2626' : ($status === 'menipis' ? '#D97706' : '#16A34A');
          ?>
          <tr>
            <td style="color:var(--muted);font-size:12px;"><?= $no++ ?></td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($b['nama_barang']) ?></div>
              <?php if ($b['kode_barang']): ?>
                <span style="display:inline-block;background:#EFF6FF;color:#2563EB;font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;margin-top:2px;"><?= htmlspecialchars($b['kode_barang']) ?></span>
              <?php endif; ?>
              <?php if (!empty($b['spesifikasi'])): ?>
                <div style="font-size:11px;color:var(--muted);margin-top:2px;"><?= htmlspecialchars(mb_strimwidth($b['spesifikasi'],0,40,'…')) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;"><?= htmlspecialchars($b['nama_ruangan'] ?? '—') ?></td>
            <td>
              <div class="stok-bar-wrap">
                <div class="stok-bar-info">
                  <span class="stok-bar-val" style="color:<?= $bar_color ?>;"><?= $laik ?></span>
                  <span class="stok-bar-lbl">/ <?= $tot ?> unit</span>
                </div>
                <div class="stok-bar">
                  <div class="stok-bar-fill" style="width:<?= $bar_pct ?>%;background:<?= $bar_color ?>;"></div>
                </div>
              </div>
            </td>
            <td class="center">
              <span class="status-badge status-<?= $status ?>">
                <i class="bi <?= $status_icon ?>" style="font-size:11px;"></i> <?= $status_label ?>
              </span>
            </td>
            <td class="center">
              <button class="btn btn-sm btn-amber" onclick="openStokModal(<?= $b['id_barang'] ?>,'<?= htmlspecialchars(addslashes($b['nama_barang'])) ?>',<?= $laik ?>,<?= $tdk ?>,<?= $tot ?>)" title="Update Stok">
                <i class="bi bi-pencil-square"></i> Stok
              </button>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$has): ?>
          <tr><td colspan="6">
            <div class="empty-state">
              <i class="bi bi-inbox"></i>
              <h3><?= $search ? 'Tidak ditemukan' : 'Belum ada barang habis pakai' ?></h3>
              <p><?= $search ? 'Tidak ada barang yang cocok dengan "'.htmlspecialchars($search).'"' : 'Tambahkan barang dengan kategori "Barang Habis Pakai" di halaman Sarana.' ?></p>
              <?php if (!$search): ?>
                <a href="barang.php" class="btn btn-primary" style="margin-top:12px;"><i class="bi bi-plus-lg"></i> Tambah Sarana</a>
              <?php endif; ?>
            </div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile Card List -->
    <div class="mobile-list">
      <?php
      $q2 = mysqli_query($conn,"SELECT b.*,r.nama_ruangan FROM barang b LEFT JOIN ruangan r ON b.id_ruangan=r.id_ruangan $where ORDER BY b.jumlah_laik ASC, b.nama_barang ASC LIMIT $per_page OFFSET $offset");
      while ($b2 = mysqli_fetch_assoc($q2)):
        $laik2 = (int)($b2['jumlah_laik'] ?? 0);
        $tdk2  = (int)($b2['jumlah_tidak_laik'] ?? 0);
        $tot2  = $laik2 + $tdk2;
        if ($laik2 === 0) { $st2 = 'habis'; $sl2 = 'Habis'; }
        elseif ($laik2 <= 5) { $st2 = 'menipis'; $sl2 = 'Menipis'; }
        else { $st2 = 'tersedia'; $sl2 = 'Tersedia'; }
        $bar2_pct = $tot2 > 0 ? round(($laik2 / $tot2) * 100) : 0;
        $bar2_color = $st2 === 'habis' ? '#DC2626' : ($st2 === 'menipis' ? '#D97706' : '#16A34A');
      ?>
      <div class="mobile-item">
        <div class="mobile-item-header">
          <div>
            <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($b2['nama_barang']) ?></div>
            <?php if ($b2['kode_barang']): ?><span style="display:inline-block;background:#EFF6FF;color:#2563EB;font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;"><?= htmlspecialchars($b2['kode_barang']) ?></span><?php endif; ?>
            <?php if ($b2['nama_ruangan']): ?><div style="font-size:11px;color:var(--muted);margin-top:3px;"><i class="bi bi-building"></i> <?= htmlspecialchars($b2['nama_ruangan']) ?></div><?php endif; ?>
          </div>
          <div class="mobile-item-actions">
            <button class="btn btn-sm btn-amber" onclick="openStokModal(<?= $b2['id_barang'] ?>,'<?= htmlspecialchars(addslashes($b2['nama_barang'])) ?>',<?= $laik2 ?>,<?= $tdk2 ?>,<?= $tot2 ?>)">
              <i class="bi bi-pencil-square"></i> Stok
            </button>
          </div>
        </div>
        <div class="stok-bar-wrap" style="margin-bottom:8px;">
          <div class="stok-bar-info">
            <span class="stok-bar-val" style="color:<?= $bar2_color ?>;font-size:14px;"><?= $laik2 ?> tersedia</span>
            <span class="stok-bar-lbl">/ <?= $tot2 ?> unit</span>
          </div>
          <div class="stok-bar">
            <div class="stok-bar-fill" style="width:<?= $bar2_pct ?>%;background:<?= $bar2_color ?>;"></div>
          </div>
        </div>
        <span class="status-badge status-<?= $st2 ?>" style="font-size:10px;">
          <?= $sl2 ?>
        </span>
      </div>
      <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total > 0): ?>
    <div class="table-footer">
      <span>Menampilkan <?= $offset+1 ?>–<?= min($offset+$per_page,$total) ?> dari <?= number_format($total) ?> barang</span>
      <div class="pag-btns">
        <?php
        $bu = '?' . ($filter_ruangan ? "filter_ruangan=$filter_ruangan&" : '')
            . ($filter_stok ? "filter_stok=$filter_stok&" : '')
            . ($search ? "q=".urlencode($search)."&" : '')
            . ($per_page!=10 ? "per_page=$per_page&" : '');
        ?>
        <a href="<?= $bu ?>page=<?= $page-1 ?>" class="pag-btn pag-btn-text <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i> Prev</a>
        <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?><a href="<?= $bu ?>page=<?= $i ?>" class="pag-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
        <a href="<?= $bu ?>page=<?= $page+1 ?>" class="pag-btn pag-btn-text <?= $page>=$total_pages?'disabled':'' ?>">Next <i class="bi bi-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Riwayat Stok Terbaru -->
  <?php if ($riwayat_exists && !empty($riwayat_list)): ?>
  <div class="card riwayat-card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-clock-history" style="color:var(--blue);"></i> Riwayat Stok Terbaru</div>
      <span style="font-size:12px;color:var(--muted);">10 terakhir</span>
    </div>
    <?php foreach ($riwayat_list as $rw): ?>
    <div class="riwayat-item">
      <div class="riwayat-dot <?= $rw['jenis']==='masuk' ? 'riwayat-dot-masuk' : 'riwayat-dot-keluar' ?>">
        <i class="bi <?= $rw['jenis']==='masuk' ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle' ?>"></i>
      </div>
      <div class="riwayat-text">
        <div class="riwayat-text-main"><?= htmlspecialchars($rw['nama_barang']) ?></div>
        <div class="riwayat-text-sub">
          <?= date('d/m/Y H:i', strtotime($rw['tanggal'])) ?>
          <?php if (!empty($rw['keterangan'])): ?> — <?= htmlspecialchars($rw['keterangan']) ?><?php endif; ?>
        </div>
      </div>
      <div class="riwayat-val" style="color:<?= $rw['jenis']==='masuk' ? '#16A34A' : '#DC2626' ?>;">
        <?= $rw['jenis']==='masuk' ? '+' : '-' ?><?= $rw['jumlah'] ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
<footer>&copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak</footer>


<!-- MODAL UPDATE STOK -->
<div class="modal-backdrop" id="modalStok" onclick="handleBackdropClick(event,'modalStok')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalStok')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title"><i class="bi bi-pencil-square" style="color:var(--amber);"></i> Update Stok</div>
    <div class="modal-sub">Tambah atau kurangi stok barang habis pakai.</div>
    <div class="modal-error" id="stokError"></div>

    <!-- Info barang -->
    <div class="stok-info-box">
      <div class="stok-info-icon" style="background:var(--amber-light);color:var(--amber);"><i class="bi bi-box-seam"></i></div>
      <div>
        <div class="stok-info-name" id="stokNamaBarang">—</div>
        <div class="stok-info-detail">
          Stok saat ini: <strong id="stokCurrentLaik">0</strong> tersedia
          <span style="color:var(--muted);"> / <span id="stokCurrentTotal">0</span> total</span>
        </div>
      </div>
    </div>

    <form method="POST" onsubmit="return validateStok()">
      <input type="hidden" name="id_barang" id="stokIdBarang">
      <input type="hidden" name="aksi_stok" id="stokAksi" value="tambah">

      <!-- Toggle Tambah / Kurangi -->
      <div class="aksi-tabs">
        <button type="button" class="aksi-tab active-tambah" id="tabTambah" onclick="setAksi('tambah')">
          <i class="bi bi-plus-circle"></i> Tambah Stok
        </button>
        <button type="button" class="aksi-tab" id="tabKurangi" onclick="setAksi('kurangi')">
          <i class="bi bi-dash-circle"></i> Kurangi Stok
        </button>
      </div>

      <div class="form-group">
        <label class="form-label">Jumlah <span style="color:#DC2626;">*</span></label>
        <input type="number" name="jumlah_stok" id="stokJumlah" class="form-control" min="1" value="1" required
               oninput="previewStokResult()">
        <div class="form-hint" id="stokHint">Stok setelah ditambah: <strong id="stokPreview">—</strong></div>
      </div>

      <div class="form-group">
        <label class="form-label">Keterangan</label>
        <textarea name="keterangan_stok" class="form-control" rows="2" style="resize:vertical;" placeholder="Contoh: Pengadaan ATK semester 1, Terpakai untuk kegiatan, dll. (opsional)"></textarea>
      </div>

      <button type="submit" name="update_stok" class="btn-modal-submit" id="stokSubmitBtn"
              style="background:#16A34A;color:white;box-shadow:0 6px 20px rgba(22,163,74,.28);">
        <i class="bi bi-plus-circle"></i> Tambah Stok
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalStok')">Batal</button>
    </form>
  </div>
</div>


<script>
  /* ── Nav ── */
  function toggleMobileMenu(){const m=document.getElementById('mobileMenu'),i=document.getElementById('hamburgerIcon'),o=m.classList.toggle('open');i.className=o?'bi bi-x-lg':'bi bi-list';}
  document.addEventListener('click',e=>{const m=document.getElementById('mobileMenu'),b=document.getElementById('hamburgerBtn');if(m.classList.contains('open')&&!m.contains(e.target)&&!b.contains(e.target)){m.classList.remove('open');document.getElementById('hamburgerIcon').className='bi bi-list';}});

  /* ── Modal ── */
  function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
  function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';const e=document.getElementById('stokError');if(e)e.classList.remove('show');}
  function handleBackdropClick(e,id){if(e.target===document.getElementById(id))closeModal(id);}
  document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal('modalStok');});

  /* ── Stok Modal Data ── */
  let currentLaik = 0, currentTdk = 0, currentTotal = 0;

  function openStokModal(id, nama, laik, tdk, total) {
    currentLaik = laik; currentTdk = tdk; currentTotal = total;
    document.getElementById('stokIdBarang').value = id;
    document.getElementById('stokNamaBarang').textContent = nama;
    document.getElementById('stokCurrentLaik').textContent = laik;
    document.getElementById('stokCurrentTotal').textContent = total;
    document.getElementById('stokJumlah').value = 1;
    setAksi('tambah');
    previewStokResult();
    openModal('modalStok');
    setTimeout(()=>document.getElementById('stokJumlah').focus(),80);
  }

  function setAksi(aksi) {
    document.getElementById('stokAksi').value = aksi;
    const tabT = document.getElementById('tabTambah');
    const tabK = document.getElementById('tabKurangi');
    const btn  = document.getElementById('stokSubmitBtn');

    if (aksi === 'tambah') {
      tabT.className = 'aksi-tab active-tambah';
      tabK.className = 'aksi-tab';
      btn.style.background = '#16A34A';
      btn.style.boxShadow = '0 6px 20px rgba(22,163,74,.28)';
      btn.innerHTML = '<i class="bi bi-plus-circle"></i> Tambah Stok';
    } else {
      tabT.className = 'aksi-tab';
      tabK.className = 'aksi-tab active-kurangi';
      btn.style.background = '#DC2626';
      btn.style.boxShadow = '0 6px 20px rgba(220,38,38,.28)';
      btn.innerHTML = '<i class="bi bi-dash-circle"></i> Kurangi Stok';
    }
    previewStokResult();
  }

  function previewStokResult() {
    const aksi = document.getElementById('stokAksi').value;
    const jumlah = parseInt(document.getElementById('stokJumlah').value) || 0;
    const hint = document.getElementById('stokHint');
    const preview = document.getElementById('stokPreview');

    let result;
    if (aksi === 'tambah') {
      result = currentLaik + jumlah;
      hint.innerHTML = 'Stok setelah ditambah: <strong id="stokPreview" style="color:#16A34A;">' + result + '</strong>';
    } else {
      result = currentLaik - jumlah;
      if (result < 0) {
        hint.innerHTML = 'Stok setelah dikurangi: <strong id="stokPreview" style="color:#DC2626;">Melebihi stok!</strong>';
      } else {
        hint.innerHTML = 'Stok setelah dikurangi: <strong id="stokPreview" style="color:#D97706;">' + result + '</strong>';
      }
    }
  }

  function validateStok() {
    const aksi = document.getElementById('stokAksi').value;
    const jumlah = parseInt(document.getElementById('stokJumlah').value) || 0;
    const err = document.getElementById('stokError');

    if (jumlah < 1) {
      err.textContent = 'Jumlah minimal 1.';
      err.classList.add('show');
      return false;
    }
    if (aksi === 'kurangi' && jumlah > currentLaik) {
      err.textContent = 'Jumlah pengurangan (' + jumlah + ') melebihi stok tersedia (' + currentLaik + ').';
      err.classList.add('show');
      return false;
    }
    err.classList.remove('show');
    return true;
  }

  /* ── Filters ── */
  function applyFilter(key, val) {
    const url = new URL(window.location.href);
    if (val) url.searchParams.set(key, val);
    else url.searchParams.delete(key);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
  }

  /* ── BFCache Fix ── */
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
  });
</script>

</body>
</html>
