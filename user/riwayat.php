<?php
require_once "../config/auth_user.php";
require_once "../config/koneksi.php";

$id_pengguna = $_SESSION['id_pengguna'];
$nama_user   = $_SESSION['nama'] ?? 'Pengguna';
$role_user   = $_SESSION['role'] ?? 'murid';

/* ── Filter ── */
$valid_statuses = ['menunggu','dipinjam','menunggu_kembali','dikembalikan'];
$filter_status = isset($_GET['status']) && in_array($_GET['status'], $valid_statuses) ? $_GET['status'] : '';
$search = isset($_GET['q']) ? trim(mysqli_real_escape_string($conn, $_GET['q'])) : '';

/* ── Stats ── */
$stats = [];
foreach ($valid_statuses as $s) {
    $stats[$s] = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as t FROM peminjaman WHERE id_pengguna='$id_pengguna' AND status='$s'"))['t'];
}
$stats['total'] = array_sum($stats);

/* ── Peminjaman terlambat milik user ini ── */
$terlambat_count = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman
     WHERE id_pengguna='$id_pengguna'
       AND status='dipinjam'
       AND waktu_selesai IS NOT NULL
       AND CONCAT(tanggal_pinjam,' ',waktu_selesai) < NOW()"))['t'];

/* ── Pagination ── */
$valid_per_page = [5, 10, 20, 25, 50];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $valid_per_page) ? (int)$_GET['per_page'] : 5;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where = "WHERE pm.id_pengguna='$id_pengguna'";
if ($filter_status) $where .= " AND pm.status='$filter_status'";
if ($search)        $where .= " AND (b.nama_barang LIKE '%$search%' OR pm.tujuan LIKE '%$search%')";

$total = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(DISTINCT pm.id_peminjaman) as t
     FROM peminjaman pm
     LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
     LEFT JOIN barang b ON dp.id_barang=b.id_barang
     $where"))['t'];

$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$riwayat_q = mysqli_query($conn,
    "SELECT pm.*,
            GROUP_CONCAT(b.nama_barang ORDER BY b.nama_barang SEPARATOR ', ') AS nama_barang_list,
            GROUP_CONCAT(dp.jumlah     ORDER BY b.nama_barang SEPARATOR ', ') AS jumlah_list
     FROM peminjaman pm
     LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
     LEFT JOIN barang b ON dp.id_barang=b.id_barang
     $where
     GROUP BY pm.id_peminjaman
     ORDER BY pm.tanggal_pengajuan DESC, pm.id_peminjaman DESC
     LIMIT $per_page OFFSET $offset");

$rows = [];
while ($r = mysqli_fetch_assoc($riwayat_q)) $rows[] = $r;

$status_cfg = [
    'menunggu'         => ['Menunggu Persetujuan', 'badge-warning',  'bi-hourglass-split'],
    'dipinjam'         => ['Sedang Dipinjam',       'badge-info',     'bi-box-arrow-up-right'],
    'menunggu_kembali' => ['Menunggu Verifikasi',   'badge-orange',   'bi-arrow-return-left'],
    'dikembalikan'     => ['Sudah Dikembalikan',    'badge-success',  'bi-check-circle'],
];
$kondisi_cfg = [
    'baik'         => ['Baik',         'badge-success'],
    'rusak_ringan' => ['Rusak Ringan', 'badge-warning'],
    'rusak_berat'  => ['Rusak Berat',  'badge-danger'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Peminjaman Saya — Inventaris SARPRAS</title>
  <meta name="description" content="Riwayat dan status semua peminjaman barang inventaris Anda">
  <link rel="icon" href="../assets/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{
      --blue:#4A90C4;--blue-dark:#2B6FA8;--blue-deep:#1B3F6E;
      --green:#3D9B4A;--yellow:#F5C518;--orange:#E67E22;
      --bg:#F0F7FF;--card:#FFFFFF;--text:#1B2D45;--muted:#6B7C93;
      --border:#D0E4F5;--shadow:0 2px 14px rgba(27,63,110,.09);
      --shadow-lg:0 8px 32px rgba(27,63,110,.15);
    }
    html{height:100%}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;}

    /* ── NAVBAR ── */
    .navbar{position:sticky;top:0;z-index:100;background:var(--blue-deep);display:flex;align-items:center;padding:0 28px;height:62px;box-shadow:0 2px 12px rgba(27,63,110,.25);}
    .nav-brand{display:flex;align-items:center;gap:11px;text-decoration:none;flex-shrink:0;margin-right:auto;}
    .nav-brand img{width:38px;height:38px;object-fit:contain;}
    .nav-brand-text strong{display:block;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:800;color:white;line-height:1.2;}
    .nav-brand-text span{font-size:10px;color:rgba(255,255,255,.5);}
    .nav-links{display:flex;align-items:center;gap:2px;margin-left:24px;}
    .nav-link{padding:8px 14px;border-radius:8px;color:rgba(255,255,255,.65);text-decoration:none;font-size:13.5px;font-weight:500;transition:all .2s;white-space:nowrap;}
    .nav-link:hover{color:white;background:rgba(255,255,255,.1);}
    .nav-link.active{color:white;font-weight:700;border-bottom:2px solid var(--yellow);border-radius:0;padding-bottom:6px;}
    .nav-user{display:flex;align-items:center;gap:10px;margin-left:16px;}
    .nav-avatar{width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;color:white;flex-shrink:0;text-decoration:none;transition:background .2s;}
    .nav-avatar:hover{background:rgba(255,255,255,.25);}
    .nav-user-name{font-size:13px;font-weight:600;color:white;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .nav-user-role{font-size:10px;color:rgba(255,255,255,.5);}
    .nav-logout{padding:7px 14px;border-radius:8px;color:rgba(255,255,255,.5);text-decoration:none;font-size:13px;font-weight:500;transition:all .2s;white-space:nowrap;border:1px solid rgba(255,255,255,.15);}
    .nav-logout:hover{color:#FCA5A5;background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.3);}
    .nav-hamburger{display:none;margin-left:16px;background:none;border:none;cursor:pointer;color:white;font-size:22px;padding:6px;border-radius:8px;}
    .nav-mobile-menu{display:none;position:absolute;top:62px;left:0;right:0;background:var(--blue-deep);box-shadow:0 8px 24px rgba(27,63,110,.25);z-index:99;flex-direction:column;padding:8px 16px 16px;border-top:1px solid rgba(255,255,255,.08);}
    .nav-mobile-menu.open{display:flex;}
    .mobile-user-info{display:flex;align-items:center;gap:10px;padding:12px 4px 14px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:6px;}
    .mobile-avatar{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:white;flex-shrink:0;}
    .nav-mobile-menu .nav-link{padding:12px 10px;border-radius:10px;font-size:14px;border-bottom:1px solid rgba(255,255,255,.06);}
    .nav-mobile-menu .nav-link:last-child{border-bottom:none;}

    /* ── PAGE ── */
    .page-wrapper{max-width:1060px;margin:0 auto;padding:32px 24px 60px;flex:1;}

    /* ── FLASH ── */
    .flash{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:9px;animation:fadeSlide .3s ease;}
    @keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
    .flash-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;}
    .flash-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;}

    /* ── ALERT BANNER ── */
    .alert-banner{display:flex;align-items:center;gap:12px;padding:14px 18px;border-radius:12px;font-size:13px;font-weight:600;margin-bottom:20px;animation:fadeSlide .35s ease;}
    .alert-danger{background:#FEF2F2;border:1.5px solid #FECACA;color:#991B1B;}
    .alert-danger i.alert-icon{font-size:18px;color:#DC2626;flex-shrink:0;}
    .alert-danger a{color:#DC2626;font-weight:700;text-decoration:underline;margin-left:4px;}

    /* ── HEADER ── */
    .page-header{margin-bottom:24px;}
    .page-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:900;color:var(--text);}
    .page-sub{font-size:13px;color:var(--muted);margin-top:3px;}

    /* ── INFO BANNER ── */
    .info-banner{background:linear-gradient(135deg,#FFF8E1,#FFFDE7);border:1.5px solid #FFE082;border-radius:14px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:flex-start;gap:14px;}
    .info-banner > i{font-size:22px;color:#F59E0B;flex-shrink:0;margin-top:2px;}
    .info-banner-text{font-size:13px;color:#78350F;line-height:1.7;}
    .info-banner-text strong{display:block;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;color:#92400E;margin-bottom:2px;font-size:14px;}

    /* ── STAT PILLS ── */
    .stat-pills{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px;}
    .stat-pill{display:flex;align-items:center;gap:9px;padding:10px 16px;background:var(--card);border:1.5px solid var(--border);border-radius:12px;text-decoration:none;transition:all .2s;cursor:pointer;}
    .stat-pill:hover{border-color:var(--blue);box-shadow:var(--shadow);transform:translateY(-1px);}
    .stat-pill.active{background:var(--blue-dark);border-color:var(--blue-dark);}
    .stat-pill.active .sp-label,.stat-pill.active .sp-num{color:white;}
    .sp-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
    .sp-dot-all{background:#9CA3AF;}
    .sp-dot-menunggu{background:#D97706;}
    .sp-dot-dipinjam{background:#2563EB;}
    .sp-dot-menunggu_kembali{background:#E67E22;}
    .sp-dot-dikembalikan{background:#16A34A;}
    .sp-label{font-size:12px;font-weight:600;color:var(--muted);}
    .sp-num{font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;color:var(--text);margin-left:auto;min-width:20px;text-align:right;}

    /* ── TOOLBAR ── */
    .toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
    .search-wrap{position:relative;display:flex;flex:1;min-width:200px;}
    .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:14px;pointer-events:none;}
    .search-input{width:100%;padding:10px 36px 10px 38px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);outline:none;transition:border-color .2s;}
    .search-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(74,144,196,.1);}
    .search-clear{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;padding:3px;border-radius:4px;display:none;line-height:1;z-index:1;}
    .search-clear:hover{color:#DC2626;}
    .search-clear.show{display:flex;align-items:center;justify-content:center;}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:'DM Sans',sans-serif;}
    .btn-primary{background:var(--blue-dark);color:white;}
    .btn-primary:hover{background:var(--blue-deep);}
    .btn-outline{background:transparent;color:var(--muted);border:1.5px solid var(--border);}
    .btn-outline:hover{background:var(--bg);color:var(--text);}
    /* ── Show Entries ── */
    .show-entries-wrap{display:flex;align-items:center;gap:8px;}
    .show-entries-label{font-size:13px;color:var(--muted);font-weight:500;white-space:nowrap;}
    .show-entries-select{padding:7px 28px 7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;cursor:pointer;outline:none;transition:border-color .2s;}
    .show-entries-select:hover,.show-entries-select:focus{border-color:var(--blue);}

    /* ── CARD ── */
    .card{background:var(--card);border:1.5px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;}
    .card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .card-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;}

    /* ── TABLE ── */
    .table-wrap{overflow-x:auto;}
    .inv-table{width:100%;border-collapse:collapse;font-size:13px;}
    .inv-table thead th{background:#F4F8FD;padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.5px;text-transform:uppercase;border-bottom:2px solid var(--border);white-space:nowrap;}
    .inv-table tbody tr{border-bottom:1px solid var(--border);transition:background .12s;}
    .inv-table tbody tr:last-child{border-bottom:none;}
    .inv-table tbody tr:hover{background:#F4F8FD;}
    .inv-table td{padding:14px 16px;color:var(--text);vertical-align:middle;}

    /* ── BADGES ── */
    .badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
    .badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;opacity:.7;}
    .badge-success{background:#F0FDF4;color:#15803D;}
    .badge-info{background:#EFF6FF;color:#2563EB;}
    .badge-warning{background:#FFFBEB;color:#D97706;}
    .badge-orange{background:#FFF3E0;color:#C05621;}
    .badge-danger{background:#FEF2F2;color:#DC2626;}
    .badge-muted{background:#F1F5F9;color:#64748B;}

    /* ── BTN RETURN ── */
    .btn-return{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:1px solid #FDBA74;text-decoration:none;transition:all .2s;background:#FFF3E0;color:#C05621;}
    .btn-return:hover{background:var(--orange);color:white;border-color:var(--orange);}

    /* ── BTN CANCEL ── */
    .btn-cancel{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:1px solid #FECACA;text-decoration:none;transition:all .2s;background:#FEF2F2;color:#DC2626;}
    .btn-cancel:hover{background:#DC2626;color:white;border-color:#DC2626;}

    /* ── MODAL DANGER ── */
    .btn-modal-danger{width:100%;padding:13px;background:#DC2626;color:white;border:none;border-radius:11px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 20px rgba(220,38,38,.3);transition:all .2s;margin-top:4px;}
    .btn-modal-danger:hover{background:#B91C1C;box-shadow:0 8px 24px rgba(220,38,38,.4);}
    .danger-box{background:#FEF2F2;border:1.5px solid #FECACA;border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:#7F1D1D;display:flex;align-items:flex-start;gap:9px;line-height:1.6;}
    .danger-box i{color:#DC2626;flex-shrink:0;font-size:16px;margin-top:1px;}

    /* ── PAGINATION ── */
    .table-footer{display:flex;align-items:center;justify-content:space-between;padding:13px 20px;border-top:1px solid var(--border);font-size:13px;color:var(--muted);flex-wrap:wrap;gap:10px;}
    .pag-btns{display:flex;align-items:center;gap:6px;}
    .pag-btn{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;background:var(--card);color:var(--text);text-decoration:none;transition:all .2s;}
    .pag-btn:hover:not(.disabled):not(.active){background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.active{background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.disabled{opacity:.35;cursor:not-allowed;pointer-events:none;}
    .pag-btn-w{padding:0 12px;width:auto;}

    /* ── EMPTY ── */
    .empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
    .empty-state i{font-size:44px;display:block;margin-bottom:14px;color:#B0C8E0;}
    .empty-state h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:700;color:var(--text);margin-bottom:6px;}

    /* ── MOBILE CARDS ── */
    .mobile-list{display:none;}
    .mobile-item{background:var(--card);border:1.5px solid var(--border);border-radius:13px;padding:16px;margin-bottom:10px;box-shadow:var(--shadow);transition:box-shadow .2s;}
    .mobile-item:hover{box-shadow:var(--shadow-lg);}
    .mi-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px;}
    .mi-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;color:var(--text);line-height:1.3;}
    .mi-sub{font-size:11px;color:var(--muted);margin-top:3px;}
    .mi-meta{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}
    .mi-meta span{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:4px;background:var(--bg);padding:3px 8px;border-radius:6px;}
    .mi-actions{display:flex;gap:8px;}
    .pending-note{font-size:11px;color:var(--muted);font-style:italic;text-align:center;padding:8px;background:#FFF3E0;border-radius:8px;margin-top:4px;}

    /* ── MODAL ── */
    .modal-backdrop{display:none;position:fixed;inset:0;z-index:500;background:rgba(27,63,110,.4);backdrop-filter:blur(5px);align-items:center;justify-content:center;padding:16px;}
    .modal-backdrop.open{display:flex;}
    .modal-box{background:var(--card);border-radius:20px;padding:32px;width:100%;max-width:490px;box-shadow:var(--shadow-lg);position:relative;animation:modalIn .22s cubic-bezier(.4,0,.2,1);max-height:92vh;overflow-y:auto;}
    @keyframes modalIn{from{transform:scale(.93) translateY(12px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
    .modal-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:19px;font-weight:800;color:var(--text);margin-bottom:6px;}
    .modal-sub{font-size:13px;color:var(--muted);margin-bottom:20px;line-height:1.6;}
    .modal-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:8px;background:var(--bg);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px;transition:all .2s;}
    .modal-close:hover{background:#FECACA;color:#DC2626;}
    .info-card{background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:18px;}
    .info-card-title{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;}
    .info-row{display:flex;align-items:flex-start;gap:8px;margin-bottom:7px;font-size:13px;}
    .info-row:last-child{margin-bottom:0;}
    .info-row i{color:var(--blue);width:16px;flex-shrink:0;margin-top:2px;}
    .info-row span{color:var(--muted);flex-shrink:0;}
    .info-row strong{color:var(--text);}
    .warn-box{background:#FFF8E1;border:1.5px solid #FFE082;border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:#78350F;display:flex;align-items:flex-start;gap:9px;line-height:1.6;}
    .warn-box i{color:#F59E0B;flex-shrink:0;font-size:16px;margin-top:1px;}
    .form-group{margin-bottom:14px;}
    .form-label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;}
    .form-control{width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
    .form-control:focus{border-color:var(--blue);background:white;box-shadow:0 0 0 3px rgba(74,144,196,.14);}
    .btn-modal-submit{width:100%;padding:13px;background:var(--orange);color:white;border:none;border-radius:11px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 20px rgba(230,126,34,.3);transition:all .2s;margin-top:4px;}
    .btn-modal-submit:hover{background:#D35400;box-shadow:0 8px 24px rgba(230,126,34,.4);}
    .btn-modal-cancel{width:100%;padding:11px;background:var(--bg);color:var(--muted);border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:8px;}
    .btn-modal-cancel:hover{background:#E5E7EB;color:var(--text);}

    /* ── MOBILE STATUS DROPDOWN ── */
    .stat-pills-mobile{display:none;}
    .mobile-filter-row{display:flex;align-items:center;gap:8px;}
    .stat-pills-mobile select{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);outline:none;cursor:pointer;transition:border-color .2s;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
    .stat-pills-mobile select:focus{border-color:var(--blue);}

    /* ── RESPONSIVE ── */
    @media(max-width:960px){.stat-pills{gap:8px;}}
    @media(max-width:768px){
      .navbar{position:relative;}.nav-links,.nav-user{display:none;}
      .nav-hamburger{display:flex;align-items:center;justify-content:center;}
      .page-wrapper{padding:20px 14px 80px;}
      .table-wrap{display:none;}.mobile-list{display:block;}
      .stat-pills{display:none;}
      .mobile-filter-row{display:flex;gap:8px;align-items:center;width:100%;}
      .stat-pills-mobile{display:flex;flex:1;min-width:0;}
      .stat-pills-mobile select{width:100%;}
      .toolbar{flex-direction:column;align-items:stretch;}
      .search-wrap{min-width:unset;width:100%;}
      .show-entries-wrap{flex-shrink:0;}
      .btn{justify-content:center;}
      .table-footer{flex-direction:column;align-items:flex-start;gap:10px;padding:12px 16px;}
      .pag-btns{width:100%;justify-content:center;}
      .modal-box{padding:22px 18px;border-radius:16px;}
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

  <div class="nav-links">
    <a href="dashboard.php" class="nav-link">Katalog Barang</a>
    <a href="riwayat.php"   class="nav-link active">Peminjaman Saya</a>
    <a href="profil.php"    class="nav-link">Profil</a>
  </div>

  <div class="nav-user">
    <a href="profil.php" class="nav-avatar" title="Profil Saya"><?= strtoupper(substr($nama_user,0,1)) ?></a>
    <div>
      <div class="nav-user-name"><?= htmlspecialchars($nama_user) ?></div>
      <div class="nav-user-role"><?= ucfirst($role_user) ?></div>
    </div>
    <a href="../auth/logout.php" class="nav-logout"><i class="bi bi-box-arrow-right"></i> Keluar</a>
  </div>

  <button class="nav-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Menu">
    <i class="bi bi-list" id="hamburgerIcon"></i>
  </button>

  <div class="nav-mobile-menu" id="mobileMenu">
    <div class="mobile-user-info">
      <div class="mobile-avatar"><?= strtoupper(substr($nama_user,0,1)) ?></div>
      <div>
        <div style="font-size:13px;font-weight:700;color:white;"><?= htmlspecialchars($nama_user) ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,.5);"><?= ucfirst($role_user) ?></div>
      </div>
    </div>
    <a href="dashboard.php" class="nav-link">Katalog Barang</a>
    <a href="riwayat.php"   class="nav-link active">Peminjaman Saya</a>
    <a href="profil.php"    class="nav-link">Profil Saya</a>
    <a href="../auth/logout.php" class="nav-link" style="color:#FCA5A5;">Keluar</a>
  </div>
</nav>

<div class="page-wrapper">

  <?php if (isset($_GET['success'])): ?>
  <div class="flash flash-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['error'])): ?>
  <div class="flash flash-error"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>

  <div class="page-header">
    <h1 class="page-title">Peminjaman Saya</h1>
    <div class="page-sub">Riwayat dan status semua peminjaman barang Anda</div>
  </div>

  <!-- Notif peminjaman terlambat -->
  <?php if ($terlambat_count > 0): ?>
  <div class="alert-banner alert-danger">
    <i class="bi bi-exclamation-triangle-fill alert-icon"></i>
    <div>
      <strong><?= $terlambat_count ?> peminjaman kamu sudah melewati batas waktu!</strong>
      Segera kembalikan barang yang dipinjam.
      <a href="riwayat.php?status=dipinjam">Lihat peminjaman →</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Stat Pills -->
  <div class="stat-pills">
    <a href="riwayat.php<?= $search?'?q='.urlencode($search):'' ?>" class="stat-pill <?= !$filter_status?'active':'' ?>">
      <span class="sp-dot sp-dot-all"></span>
      <span class="sp-label">Semua</span>
      <span class="sp-num"><?= $stats['total'] ?></span>
    </a>
    <a href="riwayat.php?status=menunggu<?= $search?'&q='.urlencode($search):'' ?>" class="stat-pill <?= $filter_status==='menunggu'?'active':'' ?>">
      <span class="sp-dot sp-dot-menunggu"></span>
      <span class="sp-label">Menunggu</span>
      <span class="sp-num"><?= $stats['menunggu'] ?></span>
    </a>
    <a href="riwayat.php?status=dipinjam<?= $search?'&q='.urlencode($search):'' ?>" class="stat-pill <?= $filter_status==='dipinjam'?'active':'' ?>">
      <span class="sp-dot sp-dot-dipinjam"></span>
      <span class="sp-label">Dipinjam</span>
      <span class="sp-num"><?= $stats['dipinjam'] ?></span>
    </a>
    <a href="riwayat.php?status=menunggu_kembali<?= $search?'&q='.urlencode($search):'' ?>" class="stat-pill <?= $filter_status==='menunggu_kembali'?'active':'' ?>">
      <span class="sp-dot sp-dot-menunggu_kembali"></span>
      <span class="sp-label">Menunggu Verifikasi</span>
      <span class="sp-num"><?= $stats['menunggu_kembali'] ?></span>
    </a>
    <a href="riwayat.php?status=dikembalikan<?= $search?'&q='.urlencode($search):'' ?>" class="stat-pill <?= $filter_status==='dikembalikan'?'active':'' ?>">
      <span class="sp-dot sp-dot-dikembalikan"></span>
      <span class="sp-label">Selesai</span>
      <span class="sp-num"><?= $stats['dikembalikan'] ?></span>
    </a>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <!-- Live Search -->
    <div class="search-wrap" style="flex:1;min-width:200px;position:relative;">
      <i class="bi bi-search search-icon"></i>
      <input type="text" id="searchInput" class="search-input" placeholder="Cari barang atau tujuan..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
      <button type="button" class="search-clear <?= $search ? 'show' : '' ?>" id="searchClearBtn" onclick="clearSearch()" title="Hapus pencarian"><i class="bi bi-x"></i></button>
    </div>

    <!-- Filter row: dropdown status (mobile) + show entries (selalu) -->
    <div class="mobile-filter-row">
      <div class="stat-pills-mobile">
        <select onchange="window.location.href=this.value">
          <option value="riwayat.php<?= $search?'?q='.urlencode($search):'' ?>" <?= !$filter_status?'selected':'' ?>>Semua (<?= $stats['total'] ?>)</option>
          <option value="riwayat.php?status=menunggu<?= $search?'&amp;q='.urlencode($search):'' ?>" <?= $filter_status==='menunggu'?'selected':'' ?>>Menunggu (<?= $stats['menunggu'] ?>)</option>
          <option value="riwayat.php?status=dipinjam<?= $search?'&amp;q='.urlencode($search):'' ?>" <?= $filter_status==='dipinjam'?'selected':'' ?>>Dipinjam (<?= $stats['dipinjam'] ?>)</option>
          <option value="riwayat.php?status=menunggu_kembali<?= $search?'&amp;q='.urlencode($search):'' ?>" <?= $filter_status==='menunggu_kembali'?'selected':'' ?>>Menunggu Verifikasi (<?= $stats['menunggu_kembali'] ?>)</option>
          <option value="riwayat.php?status=dikembalikan<?= $search?'&amp;q='.urlencode($search):'' ?>" <?= $filter_status==='dikembalikan'?'selected':'' ?>>Selesai (<?= $stats['dikembalikan'] ?>)</option>
        </select>
      </div>
      <form method="GET" id="perPageForm" style="display:flex;align-items:center;">
        <?php if ($filter_status): ?><input type="hidden" name="status" value="<?= $filter_status ?>"><?php endif; ?>
        <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
        <div class="show-entries-wrap">
          <span class="show-entries-label">Tampilkan</span>
          <select class="show-entries-select" name="per_page" onchange="this.form.submit()">
            <option value="5"  <?= $per_page==5  ?'selected':'' ?>>5</option>
            <option value="10" <?= $per_page==10 ?'selected':'' ?>>10</option>
            <option value="20" <?= $per_page==20 ?'selected':'' ?>>20</option>
            <option value="25" <?= $per_page==25 ?'selected':'' ?>>25</option>
            <option value="50" <?= $per_page==50 ?'selected':'' ?>>50</option>
          </select>
          <span class="show-entries-label">entri</span>
        </div>
      </form>
    </div>

    <?php if ($search || $filter_status): ?>
    <a href="riwayat.php" class="btn btn-outline"><i class="bi bi-x"></i> Reset</a>
    <?php endif; ?>
    <a href="dashboard.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Pinjam Barang</a>
  </div>



  <!-- Card -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-clock-history" style="color:var(--blue);"></i> Riwayat Peminjaman</div>
      <span style="font-size:12px;color:var(--muted);"><?= number_format($total) ?> data</span>
    </div>

    <!-- Desktop Table -->
    <div class="table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th>Barang</th>
            <th>Tgl Pengajuan</th>
            <th>Tgl Pinjam</th>
            <th>Status</th>
            <th>Tgl Kembali</th>
            <th style="width:140px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
          <tr><td colspan="6">
            <div class="empty-state">
              <i class="bi bi-inbox"></i>
              <h3><?= $search||$filter_status ? 'Tidak ditemukan' : 'Belum ada peminjaman' ?></h3>
              <p><?= $search ? 'Tidak ada data yang cocok dengan pencarian Anda.' : ($filter_status ? 'Tidak ada peminjaman dengan status ini.' : 'Anda belum mengajukan peminjaman barang.') ?></p>
            </div>
          </td></tr>
          <?php else: ?>
          <?php foreach ($rows as $r):
            [$slabel, $sbadge] = array_slice($status_cfg[$r['status']] ?? ['—','badge-muted',''], 0, 2);
          ?>
          <tr>
            <td>
              <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($r['nama_barang_list'] ?? '—') ?></div>
              <?php if ($r['kelas']): ?><div style="font-size:11px;color:var(--blue-dark);font-weight:600;margin-top:2px;"><?= htmlspecialchars($r['kelas']) ?></div><?php endif; ?>
              <?php if ($r['tujuan']): ?><div style="font-size:11px;color:var(--muted);margin-top:2px;"><?= htmlspecialchars(mb_strimwidth($r['tujuan'],0,50,'...')) ?></div><?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--muted);"><?= $r['tanggal_pengajuan'] ? date('d M Y',strtotime($r['tanggal_pengajuan'])) : '—' ?></td>
            <td style="font-size:13px;">
              <?php if ($r['tanggal_pinjam']): ?>
                <div style="font-weight:600;"><?= date('d M Y',strtotime($r['tanggal_pinjam'])) ?></div>
                <?php if ($r['waktu_mulai']): ?><div style="font-size:11px;color:var(--muted);"><i class="bi bi-clock"></i> Mulai: <?= substr($r['waktu_mulai'],0,5) ?></div><?php endif; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><span class="badge <?= $sbadge ?>"><?= $slabel ?></span></td>
            <td style="font-size:12px;">
              <?php if ($r['tanggal_kembali'] && $r['status']==='menunggu_kembali'): ?>
                <div style="color:var(--orange);font-weight:600;">Diajukan <?= date('d M Y',strtotime($r['tanggal_kembali'])) ?></div>
              <?php endif; ?>
              <?php if ($r['tgl_kembali_aktual']): ?>
                <div style="color:#16A34A;font-weight:600;"><?= date('d M Y',strtotime($r['tgl_kembali_aktual'])) ?></div>
                <?php if (!empty($r['waktu_kembali'])): ?><div style="font-size:11px;color:#16A34A;"><i class="bi bi-clock"></i> Kembali: <?= substr($r['waktu_kembali'],0,5) ?></div><?php endif; ?>
                <?php if (isset($kondisi_cfg[$r['kondisi_kembali']])): ?>
                  <span class="badge <?= $kondisi_cfg[$r['kondisi_kembali']][1] ?>" style="margin-top:4px;"><?= $kondisi_cfg[$r['kondisi_kembali']][0] ?></span>
                <?php endif; ?>
              <?php elseif (!$r['tanggal_kembali']): ?>—<?php endif; ?>
            </td>
            <td>
              <?php if ($r['status'] === 'dipinjam'): ?>
                <button class="btn-return" onclick="openReturnModal(<?= htmlspecialchars(json_encode([
                  'id'     => $r['id_peminjaman'],
                  'barang' => $r['nama_barang_list'],
                  'tgl'    => $r['tanggal_pinjam'],
                ])) ?>)">
                  <i class="bi bi-arrow-return-left"></i> Ajukan Kembali
                </button>
              <?php elseif ($r['status'] === 'menunggu_kembali'): ?>
                <div style="display:flex;flex-direction:column;gap:5px;">
                  <span style="font-size:11px;color:var(--orange);font-weight:600;"><i class="bi bi-hourglass-split"></i> Menunggu admin</span>
                  <button class="btn-cancel" onclick="openCancelReturnModal(<?= htmlspecialchars(json_encode([
                    'id'     => $r['id_peminjaman'],
                    'barang' => $r['nama_barang_list'],
                  ])) ?>)">
                    <i class="bi bi-x-circle"></i> Batalkan
                  </button>
                </div>
              <?php elseif ($r['status'] === 'menunggu'): ?>
                <button class="btn-cancel" onclick="openCancelModal(<?= htmlspecialchars(json_encode([
                  'id'     => $r['id_peminjaman'],
                  'barang' => $r['nama_barang_list'],
                ])) ?>)">
                  <i class="bi bi-x-circle"></i> Batalkan
                </button>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile Card List -->
    <div class="mobile-list">
      <?php if (empty($rows)): ?>
      <div class="empty-state"><i class="bi bi-inbox"></i><h3>Belum ada data</h3><p>Tidak ada peminjaman untuk ditampilkan.</p></div>
      <?php else: ?>
      <?php foreach ($rows as $r):
        [$slabel, $sbadge] = array_slice($status_cfg[$r['status']] ?? ['—','badge-muted',''], 0, 2);
      ?>
      <div class="mobile-item">
        <div class="mi-head">
          <div>
            <div class="mi-title"><?= htmlspecialchars($r['nama_barang_list'] ?? '—') ?></div>
            <?php if ($r['kelas']): ?><div class="mi-sub" style="color:var(--blue-dark);font-weight:700;"><?= htmlspecialchars($r['kelas']) ?></div><?php endif; ?>
            <?php if ($r['tujuan']): ?><div class="mi-sub"><?= htmlspecialchars(mb_strimwidth($r['tujuan'],0,60,'...')) ?></div><?php endif; ?>
          </div>
          <span class="badge <?= $sbadge ?>"><?= $slabel ?></span>
        </div>
        <div class="mi-meta">
          <?php if ($r['tanggal_pengajuan']): ?><span><i class="bi bi-calendar-plus"></i><?= date('d M Y',strtotime($r['tanggal_pengajuan'])) ?></span><?php endif; ?>
          <?php if ($r['tanggal_pinjam']): ?><span><i class="bi bi-calendar3"></i>Pinjam: <?= date('d M Y',strtotime($r['tanggal_pinjam'])) ?></span><?php endif; ?>
          <?php if ($r['waktu_mulai']): ?><span><i class="bi bi-clock"></i>Mulai: <?= substr($r['waktu_mulai'],0,5) ?></span><?php endif; ?>
          <?php if ($r['tgl_kembali_aktual']): ?><span style="color:#16A34A;font-weight:600;"><i class="bi bi-check-circle"></i>Kembali: <?= date('d M Y',strtotime($r['tgl_kembali_aktual'])) ?></span><?php endif; ?>
          <?php if (!empty($r['waktu_kembali'])): ?><span style="color:#16A34A;"><i class="bi bi-clock"></i><?= substr($r['waktu_kembali'],0,5) ?></span><?php endif; ?>
          <?php if (isset($kondisi_cfg[$r['kondisi_kembali']])): ?><span class="badge <?= $kondisi_cfg[$r['kondisi_kembali']][1] ?>"><?= $kondisi_cfg[$r['kondisi_kembali']][0] ?></span><?php endif; ?>
        </div>
        <?php if ($r['status'] === 'dipinjam'): ?>
        <div class="mi-actions">
          <button class="btn-return" style="flex:1;justify-content:center;" onclick="openReturnModal(<?= htmlspecialchars(json_encode([
            'id'     => $r['id_peminjaman'],
            'barang' => $r['nama_barang_list'],
            'tgl'    => $r['tanggal_pinjam'],
          ])) ?>)">
            <i class="bi bi-arrow-return-left"></i> Ajukan Pengembalian
          </button>
        </div>
        <?php elseif ($r['status'] === 'menunggu_kembali'): ?>
        <div class="pending-note"><i class="bi bi-hourglass-split"></i> Pengajuan dikirim — menunggu verifikasi admin</div>
        <div class="mi-actions" style="margin-top:8px;">
          <button class="btn-cancel" style="flex:1;justify-content:center;" onclick="openCancelReturnModal(<?= htmlspecialchars(json_encode([
            'id'     => $r['id_peminjaman'],
            'barang' => $r['nama_barang_list'],
          ])) ?>)">
            <i class="bi bi-x-circle"></i> Batalkan Pengajuan Kembali
          </button>
        </div>
        <?php elseif ($r['status'] === 'menunggu'): ?>
        <div class="mi-actions">
          <button class="btn-cancel" style="flex:1;justify-content:center;" onclick="openCancelModal(<?= htmlspecialchars(json_encode([
            'id'     => $r['id_peminjaman'],
            'barang' => $r['nama_barang_list'],
          ])) ?>)">
            <i class="bi bi-x-circle"></i> Batalkan Peminjaman
          </button>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($total > 0): ?>
    <div class="table-footer">
      <span>Menampilkan <?= $offset+1 ?>–<?= min($offset+$per_page,$total) ?> dari <?= number_format($total) ?> data</span>
      <div class="pag-btns">
        <?php $bu='?'.($filter_status?'status='.$filter_status.'&':'').($search?'q='.urlencode($search).'&':'').($per_page!=10?'per_page='.$per_page.'&':''); ?>
        <a href="<?= $bu ?>page=<?= $page-1 ?>" class="pag-btn pag-btn-w <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i></a>
        <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
          <a href="<?= $bu ?>page=<?= $i ?>" class="pag-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $bu ?>page=<?= $page+1 ?>" class="pag-btn pag-btn-w <?= $page>=$total_pages?'disabled':'' ?>"><i class="bi bi-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<footer style="background:var(--blue-deep);color:rgba(255,255,255,.6);text-align:center;padding:20px;font-size:12px;">
  &copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak
</footer>

<!-- ── MODAL AJUKAN PENGEMBALIAN ── -->
<div class="modal-backdrop" id="modalReturn" onclick="handleBackdropClick(event,'modalReturn')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalReturn')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Ajukan Pengembalian</div>
    <div class="modal-sub">Konfirmasi bahwa barang sudah dikembalikan ke ruangan. Admin akan memverifikasi kondisi fisik sebelum mengkonfirmasi.</div>

    <div class="info-card">
      <div class="info-card-title">Detail Peminjaman</div>
      <div class="info-row"><i class="bi bi-box-seam"></i><span>Barang:</span>&nbsp;<strong id="retBarang">—</strong></div>
      <div class="info-row"><i class="bi bi-calendar3"></i><span>Tgl Pinjam:</span>&nbsp;<strong id="retTgl">—</strong></div>
    </div>

    <div class="warn-box">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <div>Pastikan barang sudah benar-benar dikembalikan ke ruangan sebelum mengajukan. Admin akan mengecek kondisi barang secara langsung.</div>
    </div>

    <form method="POST" action="pengembalian_user.php">
      <input type="hidden" name="id_peminjaman" id="returnId">
      <div class="form-group">
        <label class="form-label">Catatan (opsional)</label>
        <textarea name="catatan_kembali" class="form-control" rows="2"
                  placeholder="Contoh: Sudah dikembalikan ke Lab Bahasa, kondisi baik."
                  style="resize:vertical;"></textarea>
      </div>
      <button type="submit" name="ajukan_kembali" class="btn-modal-submit">
        <i class="bi bi-arrow-return-left"></i> Kirim Pengajuan Pengembalian
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalReturn')">Batal</button>
    </form>
  </div>
</div>

<!-- ── MODAL BATALKAN PEMINJAMAN ── -->
<div class="modal-backdrop" id="modalCancelBorrow" onclick="handleBackdropClick(event,'modalCancelBorrow')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalCancelBorrow')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Batalkan Peminjaman?</div>
    <div class="modal-sub">Pengajuan peminjaman yang belum disetujui admin akan dihapus secara permanen.</div>

    <div class="info-card">
      <div class="info-card-title">Detail Peminjaman</div>
      <div class="info-row"><i class="bi bi-box-seam"></i><span>Barang:</span>&nbsp;<strong id="cancelBorrowBarang">—</strong></div>
    </div>

    <div class="danger-box">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <div>Tindakan ini <strong>tidak dapat dibatalkan</strong>. Pengajuan peminjaman akan dihapus dan Anda perlu mengajukan kembali jika masih membutuhkan barang tersebut.</div>
    </div>

    <form method="POST" action="peminjaman.php">
      <input type="hidden" name="id_peminjaman" id="cancelBorrowId">
      <button type="submit" name="batal_peminjaman" class="btn-modal-danger">
        <i class="bi bi-x-circle-fill"></i> Ya, Batalkan Peminjaman
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalCancelBorrow')">Tidak, Kembali</button>
    </form>
  </div>
</div>

<!-- ── MODAL BATALKAN PENGAJUAN PENGEMBALIAN ── -->
<div class="modal-backdrop" id="modalCancelReturn" onclick="handleBackdropClick(event,'modalCancelReturn')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalCancelReturn')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Batalkan Pengajuan Pengembalian?</div>
    <div class="modal-sub">Pengajuan pengembalian yang belum diverifikasi admin akan dibatalkan dan status peminjaman kembali aktif.</div>

    <div class="info-card">
      <div class="info-card-title">Detail Peminjaman</div>
      <div class="info-row"><i class="bi bi-box-seam"></i><span>Barang:</span>&nbsp;<strong id="cancelReturnBarang">—</strong></div>
    </div>

    <div class="warn-box">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <div>Status peminjaman akan dikembalikan menjadi <strong>Sedang Dipinjam</strong>. Anda bisa mengajukan pengembalian kembali kapan saja.</div>
    </div>

    <form method="POST" action="pengembalian_user.php">
      <input type="hidden" name="id_peminjaman" id="cancelReturnId">
      <button type="submit" name="batal_pengembalian" class="btn-modal-danger">
        <i class="bi bi-arrow-counterclockwise"></i> Ya, Batalkan Pengajuan
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalCancelReturn')">Tidak, Kembali</button>
    </form>
  </div>
</div>

<script>
  /* Mobile Menu */
  function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('hamburgerIcon');
    const open = menu.classList.toggle('open');
    icon.className = open ? 'bi bi-x-lg' : 'bi bi-list';
  }
  document.addEventListener('click', e => {
    const menu = document.getElementById('mobileMenu');
    const btn  = document.getElementById('hamburgerBtn');
    if (menu && menu.classList.contains('open') && !menu.contains(e.target) && !btn.contains(e.target)) {
      menu.classList.remove('open');
      document.getElementById('hamburgerIcon').className = 'bi bi-list';
    }
  });

  /* Live Search AJAX (fokus tidak hilang) */
  (function() {
    const input    = document.getElementById('searchInput');
    const clearBtn = document.getElementById('searchClearBtn');
    if (!input) return;

    let timer;
    let lastQ = input.value;

    function fetchTable(q) {
      const url = new URL(window.location.href);
      if (q) url.searchParams.set('q', q);
      else    url.searchParams.delete('q');
      url.searchParams.set('page', '1');
      window.history.replaceState({}, '', url.toString());

      const tableWrap  = document.querySelector('.table-wrap');
      const mobileList = document.querySelector('.mobile-list');
      if (tableWrap)  tableWrap.style.opacity  = '0.5';
      if (mobileList) mobileList.style.opacity = '0.5';

      fetch(url.toString())
        .then(r => r.text())
        .then(html => {
          const doc = new DOMParser().parseFromString(html, 'text/html');

          const newTbody = doc.querySelector('.inv-table tbody');
          const curTbody = document.querySelector('.inv-table tbody');
          if (newTbody && curTbody) curTbody.innerHTML = newTbody.innerHTML;

          const newMob = doc.querySelector('.mobile-list');
          const curMob = document.querySelector('.mobile-list');
          if (newMob && curMob) curMob.innerHTML = newMob.innerHTML;

          const newFoot = doc.querySelector('.table-footer');
          const curFoot = document.querySelector('.table-footer');
          if (newFoot && curFoot) curFoot.innerHTML = newFoot.innerHTML;

          if (tableWrap)  tableWrap.style.opacity  = '1';
          if (mobileList) mobileList.style.opacity = '1';

          const inp = document.getElementById('searchInput');
          if (inp) { inp.focus(); inp.setSelectionRange(inp.value.length, inp.value.length); }
        })
        .catch(() => {
          if (tableWrap)  tableWrap.style.opacity  = '1';
          if (mobileList) mobileList.style.opacity = '1';
        });
    }

    input.addEventListener('input', function() {
      const q = this.value.trim();
      clearBtn.classList.toggle('show', q.length > 0);
      clearTimeout(timer);
      timer = setTimeout(() => {
        if (q === lastQ) return;
        lastQ = q;
        fetchTable(q);
      }, 350);
    });

    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(timer);
        lastQ = this.value.trim();
        fetchTable(lastQ);
      }
    });
  })();

  function clearSearch() {
    const input = document.getElementById('searchInput');
    if (input) { input.value = ''; input.dispatchEvent(new Event('input')); }
    document.getElementById('searchClearBtn').classList.remove('show');
  }

  /* Modal */
  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  function handleBackdropClick(e, id) { if (e.target === document.getElementById(id)) closeModal(id); }
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeModal('modalReturn');
      closeModal('modalCancelBorrow');
      closeModal('modalCancelReturn');
    }
  });

  function openReturnModal(data) {
    document.getElementById('returnId').value        = data.id;
    document.getElementById('retBarang').textContent = data.barang || '—';
    document.getElementById('retTgl').textContent    = data.tgl ? fmtDate(data.tgl) : '—';
    openModal('modalReturn');
  }

  function openCancelModal(data) {
    document.getElementById('cancelBorrowId').value          = data.id;
    document.getElementById('cancelBorrowBarang').textContent = data.barang || '—';
    openModal('modalCancelBorrow');
  }

  function openCancelReturnModal(data) {
    document.getElementById('cancelReturnId').value          = data.id;
    document.getElementById('cancelReturnBarang').textContent = data.barang || '—';
    openModal('modalCancelReturn');
  }
  function fmtDate(s) {
    const mn = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    const d = new Date(s + 'T00:00:00');
    return d.getDate() + ' ' + mn[d.getMonth()] + ' ' + d.getFullYear();
  }

  /* ── BFCache Fix: paksa reload jika halaman dari back-forward cache ── */
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
  });
</script>
</body>
</html>