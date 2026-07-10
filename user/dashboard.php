<?php
require_once "../config/auth_user.php";
require_once "../config/koneksi.php";

$id_pengguna = $_SESSION['id_pengguna'];
$nama_user   = $_SESSION['nama'] ?? 'Pengguna';
$role_user   = $_SESSION['role'] ?? 'murid';

/* ── Statistik peminjaman saya ── */
$pinjam_aktif  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman WHERE id_pengguna='$id_pengguna' AND status IN ('menunggu','dipinjam')"))['t'];
$total_pinjam  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman WHERE id_pengguna='$id_pengguna'"))['t'];
$menunggu_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman WHERE id_pengguna='$id_pengguna' AND status='menunggu'"))['t'];

/* ── Peminjaman terlambat milik user ini ── */
$terlambat_count = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman
     WHERE id_pengguna='$id_pengguna'
       AND status='dipinjam'
       AND waktu_selesai IS NOT NULL
       AND CONCAT(tanggal_pinjam,' ',waktu_selesai) < NOW()"))['t'];

/* ── Filter & Search barang ── */
$search      = isset($_GET['q'])       ? trim(mysqli_real_escape_string($conn, $_GET['q'])) : '';
$id_ruangan  = isset($_GET['ruangan']) ? (int)$_GET['ruangan'] : 0;

$where = "WHERE b.bisa_dipinjam=1 AND b.jumlah_laik>0";
/* Filter berdasarkan role pengguna */
if ($role_user === 'murid')  $where .= " AND b.pinjam_murid=1";
elseif ($role_user === 'guru')   $where .= " AND b.pinjam_guru=1";
elseif ($role_user === 'tendik') $where .= " AND b.pinjam_tendik=1";
if ($search)     $where .= " AND (b.nama_barang LIKE '%$search%' OR b.kode_barang LIKE '%$search%')";
if ($id_ruangan) $where .= " AND b.id_ruangan=$id_ruangan";

/* ── Pagination ── */
$valid_per_page = [6, 12, 24, 48];
$per_page    = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $valid_per_page) ? (int)$_GET['per_page'] : 6;
$page        = max(1, (int)($_GET['page'] ?? 1));
$total_barang = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM barang b $where"))['t'];
$total_pages = max(1, ceil($total_barang / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$barang_q = mysqli_query($conn, "
    SELECT b.*, r.nama_ruangan
    FROM barang b
    LEFT JOIN ruangan r ON b.id_ruangan=r.id_ruangan
    $where
    ORDER BY b.nama_barang ASC
    LIMIT $per_page OFFSET $offset
");

/* ── Daftar ruangan untuk filter ── */
$ruangan_list = [];
$rq = mysqli_query($conn, "SELECT id_ruangan, nama_ruangan FROM ruangan ORDER BY nama_ruangan");
while ($rv = mysqli_fetch_assoc($rq)) $ruangan_list[] = $rv;


?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Inventaris SARPRAS</title>
  <link rel="icon" href="../assets/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{
      --blue:#4A90C4;--blue-dark:#2B6FA8;--blue-deep:#1B3F6E;
      --green:#3D9B4A;--yellow:#F5C518;
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
    .nav-mobile-menu .nav-link{padding:13px 12px;border-radius:10px;font-size:14px;border-bottom:1px solid rgba(255,255,255,.06);}
    .nav-mobile-menu .nav-link:last-child{border-bottom:none;}

    /* ── PAGE ── */
    .page-wrapper{max-width:1060px;margin:0 auto;padding:32px 24px 60px;flex:1;}

    /* ── WELCOME BANNER ── */
    .welcome-banner{
      background:linear-gradient(135deg,var(--blue-deep) 0%,var(--blue-dark) 100%);
      border-radius:16px;padding:24px 28px;margin-bottom:24px;
      display:flex;align-items:center;justify-content:space-between;gap:16px;
      box-shadow:0 8px 28px rgba(27,63,110,.25);position:relative;overflow:hidden;
    }
    .welcome-banner::after{
      content:'';position:absolute;right:-40px;top:-40px;
      width:200px;height:200px;border-radius:50%;
      background:rgba(255,255,255,.05);pointer-events:none;
    }
    .welcome-text h2{font-family:'Plus Jakarta Sans',sans-serif;font-size:20px;font-weight:800;color:white;margin-bottom:4px;}
    .welcome-text p{font-size:13px;color:rgba(255,255,255,.65);}
    .welcome-stats{display:flex;gap:20px;flex-shrink:0;}
    .w-stat{text-align:center;}
    .w-stat-num{font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:900;color:white;line-height:1;}
    .w-stat-lbl{font-size:10px;color:rgba(255,255,255,.55);margin-top:3px;}

    /* ── NOTIF BANNER ── */
    .notif-banner{background:#FFF8E1;border:1.5px solid #FFE082;border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;}
    .notif-banner-text{font-size:13px;color:#92400E;display:flex;align-items:center;gap:8px;}
    .notif-banner-text i{font-size:16px;color:#F59E0B;}
    .notif-banner a{font-size:12px;font-weight:700;color:var(--blue-dark);text-decoration:none;white-space:nowrap;}
    .notif-banner a:hover{text-decoration:underline;}

    /* ── TOOLBAR ── */
    .toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
    .search-wrap{position:relative;flex:1;min-width:220px;}
    .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:14px;pointer-events:none;}
    .search-clear{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;padding:3px;border-radius:4px;display:none;line-height:1;}
    .search-clear:hover{color:#DC2626;}
    .search-clear.show{display:flex;align-items:center;justify-content:center;}
    .search-input{width:100%;padding:10px 36px 10px 38px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);outline:none;transition:border-color .2s;}
    .search-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(74,144,196,.1);}
    .filter-select{padding:10px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);outline:none;cursor:pointer;transition:border-color .2s;}
    .filter-select:hover,.filter-select:focus{border-color:var(--blue);}
    /* ── Show Entries ── */
    .show-entries-wrap{display:flex;align-items:center;gap:8px;}
    .show-entries-label{font-size:13px;color:var(--muted);font-weight:500;white-space:nowrap;}
    .show-entries-select{padding:7px 28px 7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;cursor:pointer;outline:none;transition:border-color .2s;}
    .show-entries-select:hover,.show-entries-select:focus{border-color:var(--blue);}

    .section-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--text);margin-bottom:4px;}
    .section-sub{font-size:13px;color:var(--muted);margin-bottom:20px;}

    /* ── BARANG GRID ── */
    .barang-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}
    .barang-card{background:var(--card);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow);transition:all .22s;display:flex;flex-direction:column;}
    .barang-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);border-color:var(--blue);}
    .barang-card-thumb{height:110px;background:linear-gradient(135deg,#EAF3FC,#D0E8F8);display:flex;align-items:center;justify-content:center;font-size:44px;position:relative;}
    .barang-card-jumlah{position:absolute;top:8px;right:8px;background:white;border:1px solid var(--border);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;color:var(--blue-dark);}
    .barang-card-body{padding:14px;flex:1;display:flex;flex-direction:column;gap:8px;}
    .barang-card-name{font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;color:var(--text);line-height:1.3;}
    .barang-card-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
    .barang-card-ruangan{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:4px;}
    .barang-card-ruangan i{font-size:11px;}
    .barang-card-footer{padding:12px 14px;border-top:1px solid var(--border);margin-top:auto;}
    .btn-pinjam{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:10px;background:var(--blue-dark);color:white;border:none;border-radius:9px;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;text-decoration:none;}
    .btn-pinjam:hover{background:var(--blue-deep);}
    .btn-pinjam.disabled{background:#CBD5E1;cursor:not-allowed;}

    /* ── BADGE ── */
    .badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
    .badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;opacity:.7;}
    .badge-success{background:#F0FDF4;color:#15803D;}
    .badge-info{background:#EFF6FF;color:#2563EB;}
    .badge-warning{background:#FFFBEB;color:#D97706;}
    .badge-danger{background:#FEF2F2;color:#DC2626;}

    /* ── PAGINATION ── */
    .pagination{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:28px;}
    .pag-btn{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;background:var(--card);color:var(--text);text-decoration:none;transition:all .2s;}
    .pag-btn:hover:not(.disabled):not(.active){background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.active{background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.disabled{opacity:.35;cursor:not-allowed;pointer-events:none;}
    .pag-btn-wide{padding:0 14px;width:auto;}

    /* ── EMPTY ── */
    .empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
    .empty-state i{font-size:48px;display:block;margin-bottom:14px;color:#B0C8E0;}
    .empty-state h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:700;color:var(--text);margin-bottom:6px;}

    /* ── BUTTONS ── */
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:'DM Sans',sans-serif;}
    .btn-primary{background:var(--blue-dark);color:white;}
    .btn-primary:hover{background:var(--blue-deep);}
    .btn-outline{background:transparent;color:var(--blue-dark);border:1.5px solid var(--blue-dark);}
    .btn-outline:hover{background:var(--blue-dark);color:white;}

    /* ── MODAL ── */
    .modal-backdrop{display:none;position:fixed;inset:0;z-index:500;background:rgba(27,63,110,.35);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
    .modal-backdrop.open{display:flex;}
    .modal-box{background:var(--card);border-radius:18px;padding:32px;width:100%;max-width:480px;box-shadow:var(--shadow-lg);position:relative;z-index:501;animation:modalIn .22s cubic-bezier(.4,0,.2,1);max-height:92vh;overflow-y:auto;}
    @keyframes modalIn{from{transform:scale(.94) translateY(10px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
    .modal-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--text);margin-bottom:6px;}
    .modal-sub{font-size:13px;color:var(--muted);margin-bottom:20px;}
    .modal-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:8px;background:var(--bg);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px;transition:all .2s;}
    .modal-close:hover{background:#FECACA;color:#DC2626;}
    .modal-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;border-radius:8px;padding:9px 12px;font-size:12px;margin-bottom:14px;display:none;}
    .modal-error.show{display:block;}
    .barang-summary{background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:18px;display:flex;align-items:center;gap:14px;}
    .barang-summary-icon{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#EAF3FC,#D0E8F8);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;}
    .barang-summary-name{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;color:var(--text);}
    .barang-summary-meta{font-size:12px;color:var(--muted);margin-top:3px;}
    .form-group{margin-bottom:14px;}
    .form-label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;}
    .form-label span{color:#DC2626;}
    .form-control{width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
    .form-control:focus{border-color:var(--blue);background:white;box-shadow:0 0 0 3px rgba(74,144,196,.14);}
    .form-hint{font-size:11px;color:var(--muted);margin-top:5px;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
    .infokus-note{background:#FFF8E1;border:1px solid #FFE082;border-radius:8px;padding:10px 12px;font-size:12px;color:#92400E;margin-bottom:14px;display:none;line-height:1.6;}
    .infokus-note.show{display:block;}
    .duration-display{background:var(--bg);border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:12px;margin-top:8px;display:none;}
    .duration-display.show{display:block;}
    .duration-display.over{background:#FEF2F2;border-color:#FECACA;color:#DC2626;}
    .duration-display.ok{background:#F0FDF4;border-color:#BBF7D0;color:#166534;}
    .btn-modal-submit{width:100%;padding:12px;background:var(--blue-dark);color:white;border:none;border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 20px rgba(43,111,168,.28);transition:all .2s;margin-top:4px;}
    .btn-modal-submit:hover{background:var(--blue-deep);}
    .btn-modal-cancel{width:100%;padding:11px;background:var(--bg);color:var(--muted);border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:8px;}
    .btn-modal-cancel:hover{background:#E5E7EB;}

    /* ── FLASH ── */
    .flash{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:9px;}
    .flash-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;}
    .flash-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;}

    /* ── ALERT BANNER ── */
    .alert-banner{display:flex;align-items:center;gap:12px;padding:14px 18px;border-radius:12px;font-size:13px;font-weight:600;margin-bottom:20px;animation:fadeSlide .35s ease;}
    @keyframes fadeSlide{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
    .alert-danger{background:#FEF2F2;border:1.5px solid #FECACA;color:#991B1B;}
    .alert-danger i{font-size:18px;color:#DC2626;flex-shrink:0;}
    .alert-danger a{color:#DC2626;font-weight:700;text-decoration:underline;margin-left:4px;}

    /* ── CART UI ── */
    .floating-cart{position:fixed;bottom:30px;right:30px;background:var(--blue-dark);color:white;width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 8px 32px rgba(43,111,168,.4);cursor:pointer;z-index:90;transition:all .3s cubic-bezier(.4,0,.2,1);border:none;outline:none;}
    .floating-cart:hover{transform:translateY(-5px) scale(1.05);background:var(--blue-deep);}
    .floating-cart .cart-badge{position:absolute;top:-2px;right:-2px;background:#DC2626;color:white;font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:800;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg);transition:all .2s;}
    .cart-item-row{display:flex;align-items:center;gap:12px;padding:12px;border:1.5px solid var(--border);border-radius:10px;margin-bottom:10px;background:white;}
    .cart-item-icon{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#EAF3FC,#D0E8F8);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
    .cart-item-info{flex:1;min-width:0;}
    .cart-item-name{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .cart-item-meta{font-size:11px;color:var(--muted);margin-top:2px;}
    .cart-item-qty{display:flex;align-items:center;gap:8px;background:var(--bg);padding:4px;border-radius:8px;}
    .cart-qty-btn{width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:white;border:1px solid var(--border);border-radius:6px;cursor:pointer;color:var(--text);font-size:14px;}
    .cart-qty-btn:hover{background:var(--border);}
    .cart-qty-input{width:36px;text-align:center;font-size:13px;font-weight:700;border:none;background:transparent;outline:none;}
    .cart-item-del{width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:#FEF2F2;color:#DC2626;border:none;border-radius:8px;cursor:pointer;transition:all .2s;}
    .cart-item-del:hover{background:#FECACA;}

    /* ── RESPONSIVE ── */
    @media(max-width:960px){.barang-grid{grid-template-columns:repeat(2,1fr);}}
    @media(max-width:768px){
      .navbar{position:relative;}.nav-links,.nav-user{display:none;}
      .nav-hamburger{display:flex;align-items:center;justify-content:center;}
      .page-wrapper{padding:20px 14px 80px;}
      .welcome-banner{flex-direction:column;align-items:flex-start;}
      .welcome-stats{width:100%;justify-content:space-around;}
      .toolbar{flex-direction:column;}.search-wrap{width:100%;}
      .filter-select{flex:1;min-width:0;}
      #filterForm{flex:1;min-width:0;}
      #perPageForm{flex-shrink:0;}
      .toolbar>form{display:flex;}
      .toolbar-filter-row{display:flex;flex-direction:row;gap:8px;width:100%;align-items:center;}
      .show-entries-wrap{justify-content:flex-start;}
      .barang-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
      .modal-box{margin:12px;padding:22px 18px;border-radius:16px;max-width:100%;}
      .form-row{grid-template-columns:1fr;gap:0;}
    }
    @media(max-width:420px){
      .barang-grid{grid-template-columns:1fr;}
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

  <!-- Desktop links -->
  <div class="nav-links">
    <a href="dashboard.php"  class="nav-link active">Katalog Barang</a>
    <a href="riwayat.php"    class="nav-link">Peminjaman Saya</a>
    <a href="profil.php"     class="nav-link">Profil</a>
  </div>

  <!-- User info + logout -->
  <div class="nav-user">
    <a href="profil.php" class="nav-avatar" title="Profil Saya"><?= strtoupper(substr($nama_user,0,1)) ?></a>
    <div>
      <div class="nav-user-name"><?= htmlspecialchars($nama_user) ?></div>
      <div class="nav-user-role"><?= ucfirst($role_user) ?></div>
    </div>
    <a href="../auth/logout.php" class="nav-logout"><i class="bi bi-box-arrow-right"></i> Keluar</a>
  </div>

  <!-- Hamburger -->
  <button class="nav-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Menu">
    <i class="bi bi-list" id="hamburgerIcon"></i>
  </button>

  <!-- Mobile dropdown -->
  <div class="nav-mobile-menu" id="mobileMenu">
    <a href="dashboard.php" class="nav-link active">Katalog Barang</a>
    <a href="riwayat.php"   class="nav-link">Peminjaman Saya</a>
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

  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div class="welcome-text">
      <h2>Halo, <?= htmlspecialchars(explode(' ', $nama_user)[0]) ?>!</h2>
    </div>
    <div class="welcome-stats">
      <div class="w-stat">
        <div class="w-stat-num"><?= $pinjam_aktif ?></div>
        <div class="w-stat-lbl">Sedang Dipinjam</div>
      </div>
      <div class="w-stat" style="border-left:1px solid rgba(255,255,255,.15);padding-left:20px;">
        <div class="w-stat-num"><?= $menunggu_count ?></div>
        <div class="w-stat-lbl">Menunggu Persetujuan</div>
      </div>
      <div class="w-stat" style="border-left:1px solid rgba(255,255,255,.15);padding-left:20px;">
        <div class="w-stat-num"><?= $total_pinjam ?></div>
        <div class="w-stat-lbl">Total Pinjaman</div>
      </div>
    </div>
  </div>

  <!-- Notif peminjaman terlambat -->
  <?php if ($terlambat_count > 0): ?>
  <div class="alert-banner alert-danger">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
      <strong><?= $terlambat_count ?> peminjaman kamu sudah melewati batas waktu!</strong>
      Segera kembalikan barang yang dipinjam.
      <a href="riwayat.php?status=dipinjam">Lihat peminjaman →</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Notif jika ada pengajuan menunggu -->
  <?php if ($menunggu_count > 0): ?>
  <div class="notif-banner">
    <div class="notif-banner-text">
      <i class="bi bi-hourglass-split"></i>
      Kamu memiliki <strong><?= $menunggu_count ?> pengajuan</strong> yang sedang menunggu persetujuan admin.
    </div>
    <a href="riwayat.php?status=menunggu">Lihat Status →</a>
  </div>
  <?php endif; ?>

  <!-- Section title + toolbar -->
  <div class="section-title">Katalog Barang</div>
  <div class="section-sub">Barang yang tersedia untuk dipinjam</div>

  <div class="toolbar">
    <!-- Live Search -->
    <div class="search-wrap" style="flex:1;min-width:220px;">
      <i class="bi bi-search search-icon"></i>
      <input type="text" id="searchInput" class="search-input" placeholder="Cari nama atau kode barang..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
      <button type="button" class="search-clear <?= $search ? 'show' : '' ?>" id="searchClearBtn" onclick="clearSearch()" title="Hapus pencarian"><i class="bi bi-x"></i></button>
    </div>

    <!-- Filter ruangan + Show Entries (satu baris di mobile) -->
    <div class="toolbar-filter-row">
      <form method="GET" id="filterForm" style="display:flex;flex:1;min-width:0;">
        <input type="hidden" name="q" id="filterFormQ" value="<?= htmlspecialchars($search) ?>">
        <?php if ($per_page != 12): ?><input type="hidden" name="per_page" value="<?= $per_page ?>"><?php endif; ?>
        <select name="ruangan" class="filter-select" style="width:100%;" onchange="this.form.submit()">
          <option value="">Semua Ruangan</option>
          <?php foreach ($ruangan_list as $rv): ?>
          <option value="<?= $rv['id_ruangan'] ?>" <?= $id_ruangan==$rv['id_ruangan']?'selected':'' ?>>
            <?= htmlspecialchars($rv['nama_ruangan']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>

      <form method="GET" id="perPageForm" style="display:flex;align-items:center;flex-shrink:0;">
        <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
        <?php if ($id_ruangan): ?><input type="hidden" name="ruangan" value="<?= $id_ruangan ?>"><?php endif; ?>
        <div class="show-entries-wrap">
          <span class="show-entries-label">Tampilkan</span>
          <select class="show-entries-select" name="per_page" onchange="this.form.submit()">
            <option value="6"  <?= $per_page==6  ?'selected':'' ?>>6</option>
            <option value="12" <?= $per_page==12 ?'selected':'' ?>>12</option>
            <option value="24" <?= $per_page==24 ?'selected':'' ?>>24</option>
            <option value="48" <?= $per_page==48 ?'selected':'' ?>>48</option>
          </select>
          <span class="show-entries-label">item</span>
        </div>
      </form>
    </div>

    <?php if ($search || $id_ruangan): ?>
    <a href="dashboard.php" class="btn btn-outline btn-sm" style="font-size:12px;padding:8px 12px;">
      <i class="bi bi-x"></i> Reset
    </a>
    <?php endif; ?>
  </div>

  <!-- Barang Grid -->
  <div id="barangContainer">
  <?php if ($total_barang == 0): ?>
  <div class="empty-state">
    <i class="bi bi-inbox"></i>
    <?php if ($search && $id_ruangan): ?>
      <h3>Tidak ada barang</h3>
      <p>Tidak ada barang yang cocok dengan "<strong><?= htmlspecialchars($search) ?></strong>" di ruangan ini.</p>
    <?php elseif ($search): ?>
      <h3>Tidak ada barang</h3>
      <p>Tidak ada barang yang cocok dengan "<strong><?= htmlspecialchars($search) ?></strong>".</p>
    <?php elseif ($id_ruangan): ?>
      <h3>Belum ada barang</h3>
      <p>Tidak ada barang yang tersedia untuk dipinjam di ruangan ini.</p>
    <?php else: ?>
      <h3>Belum ada barang</h3>
      <p>Belum ada barang yang tersedia untuk dipinjam saat ini.</p>
    <?php endif; ?>
    <?php if ($search || $id_ruangan): ?>
      <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:5px;margin-top:12px;padding:6px 12px;background:var(--blue-dark);color:white;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;transition:all .2s;width:auto;">
        <i style="font-size:11px;"></i> Lihat Semua Barang
      </a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="barang-grid">
    <?php while ($b = mysqli_fetch_assoc($barang_q)):
      $nm  = strtolower($b['nama_barang']);
      if      (str_contains($nm,'infokus')||str_contains($nm,'proyektor')) $ikon='<i class="bi bi-projector"></i>';
      elseif  (str_contains($nm,'laptop')||str_contains($nm,'komputer'))   $ikon='<i class="bi bi-laptop"></i>';
      elseif  (str_contains($nm,'buku'))  $ikon='<i class="bi bi-book"></i>';
      elseif  (str_contains($nm,'kursi')) $ikon='<i class="bi bi-egg"></i>'; // Bootstrap doesn't have chair, using generic or bounding-box. Let's just use grid or box. Actually bi-hdd for electronic, let's use bi-box for generic.
      elseif  (str_contains($nm,'meja'))  $ikon='<i class="bi bi-table"></i>';
      elseif  (str_contains($nm,'papan')) $ikon='<i class="bi bi-clipboard"></i>';
      else                                 $ikon='<i class="bi bi-box-seam"></i>';
      $is_inf = str_contains($nm,'infokus') || str_contains($nm,'proyektor') || str_contains($nm,'projector');
      $b_js = htmlspecialchars(json_encode([
        'id'                => $b['id_barang'],
        'nama'              => $b['nama_barang'],
        'kode'              => $b['kode_barang'],
        'jumlah'            => $b['jumlah_laik'],
        'ruangan'           => $b['nama_ruangan'] ?? '—',
        'is_infokus'        => $is_inf,
        'foto'              => $b['foto'] ?? '',
        'spesifikasi'       => $b['spesifikasi'] ?? '',
        'sumber_dana'       => $b['sumber_dana'] ?? '',
        'tanggal_pembelian' => $b['tanggal_pembelian'] ?? '',
      ]), ENT_QUOTES);
    ?>
    <div class="barang-card">
      <div class="barang-card-thumb">
        <?php if (!empty($b['foto'])): ?>
          <img src="../assets/foto_barang/<?= htmlspecialchars($b['foto']) ?>" alt="Foto <?= htmlspecialchars($b['nama_barang']) ?>"
               style="width:100%;height:100%;object-fit:cover;display:block;">
        <?php else: ?>
          <?= $ikon ?>
        <?php endif; ?>
        <div class="barang-card-jumlah"><?= $b['jumlah_laik'] ?> unit</div>
      </div>
      <div class="barang-card-body">
        <div class="barang-card-name"><?= htmlspecialchars($b['nama_barang']) ?></div>
        <?php if ($b['nama_ruangan']): ?>
        <div class="barang-card-ruangan"><i class="bi bi-building"></i> <?= htmlspecialchars($b['nama_ruangan']) ?></div>
        <?php endif; ?>
        <?php if (!empty($b['spesifikasi'])): ?>
        <div style="font-size:11px;color:var(--muted);line-height:1.4;"><?= htmlspecialchars(mb_strimwidth($b['spesifikasi'],0,60,'…')) ?></div>
        <?php endif; ?>
        <?php if (!empty($b['sumber_dana']) || !empty($b['tanggal_pembelian'])): ?>
        <div class="barang-card-meta" style="margin-top:2px;">
          <?php if (!empty($b['sumber_dana'])): ?>
            <span class="badge badge-info" style="font-size:10px;"><i class="bi bi-bank" style="margin:0;"></i> <?= htmlspecialchars($b['sumber_dana']) ?></span>
          <?php endif; ?>
          <?php if (!empty($b['tanggal_pembelian'])): ?>
            <span class="badge badge-success" style="font-size:10px;"><i class="bi bi-calendar3" style="margin:0;"></i> <?= date('Y', strtotime($b['tanggal_pembelian'])) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="barang-card-footer" style="display:flex;gap:8px;">
        <button class="btn-pinjam" style="flex:1;" onclick="pinjamLangsung(<?= $b_js ?>)">
          <i class="bi bi-lightning-charge"></i> Pinjam Langsung
        </button>
        <button class="btn-pinjam" style="flex:0 0 44px;background:var(--bg);color:var(--blue-dark);border:1.5px solid var(--border);" onclick="addToCart(<?= $b_js ?>)" title="Tambah ke Keranjang">
          <i class="bi bi-cart-plus" style="font-size:16px;"></i>
        </button>
      </div>
    </div>
    <?php endwhile; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php $bu = '?'.($search?'q='.urlencode($search).'&':'').($id_ruangan?'ruangan='.$id_ruangan.'&':'').($per_page!=12?'per_page='.$per_page.'&':''); ?>
    <a href="<?= $bu ?>page=<?= $page-1 ?>" class="pag-btn pag-btn-wide <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i></a>
    <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
      <a href="<?= $bu ?>page=<?= $i ?>" class="pag-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <a href="<?= $bu ?>page=<?= $page+1 ?>" class="pag-btn pag-btn-wide <?= $page>=$total_pages?'disabled':'' ?>"><i class="bi bi-chevron-right"></i></a>
  </div>
  <?php endif; ?>
  <?php endif; ?>
  </div><!-- /barangContainer -->

</div>

<footer style="background:var(--blue-deep);color:rgba(255,255,255,.6);text-align:center;padding:20px;font-size:12px;">
  &copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak
</footer>

<!-- Floating Cart Button -->
<button class="floating-cart" id="floatingCartBtn" onclick="openCartModal()" style="display:none;">
  <i class="bi bi-cart3"></i>
  <span class="cart-badge" id="cartBadge">0</span>
</button>


<!-- ══ MODAL AJUKAN PEMINJAMAN (CART / CHECKOUT) ══ -->
<div class="modal-backdrop" id="modalPinjam" onclick="handleBackdropClick(event,'modalPinjam')">
  <div class="modal-box" style="max-width:560px;">
    <button class="modal-close" onclick="closeModal('modalPinjam')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Checkout Peminjaman</div>
    <div class="modal-sub">Periksa kembali barang yang akan dipinjam dan lengkapi tujuan.</div>
    <div class="modal-error" id="pinjamError"></div>

    <form method="POST" action="peminjaman.php" id="formPeminjaman" onsubmit="return validateForm()">
      <!-- Daftar Barang -->
      <div style="margin-bottom:20px;">
        <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:10px;">Daftar Barang</div>
        <div id="cartItemsContainer" style="max-height:240px;overflow-y:auto;padding-right:4px;">
          <!-- Diisi via JS -->
        </div>
      </div>

      <div class="form-row">
        <?php if ($role_user === 'murid'): ?>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Kelas <span>*</span></label>
          <input type="text" name="kelas" class="form-control" placeholder="Contoh: XII A" value="<?= htmlspecialchars($_SESSION['kelas'] ?? '') ?>" required>
        </div>
        <?php else: ?>
        <input type="hidden" name="kelas" value="">
        <?php endif; ?>
        
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Tanggal Pinjam <span>*</span></label>
          <input type="date" name="tgl_pinjam" class="form-control" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Tujuan Peminjaman <span>*</span></label>
        <textarea name="tujuan" id="inputTujuan" class="form-control" rows="2"
                  placeholder="Contoh: Presentasi pelajaran Biologi di ruang multimedia" style="resize:vertical;" required></textarea>
      </div>

      <button type="submit" name="ajukan_peminjaman" class="btn-modal-submit" id="btnSubmitCart">
        <i class="bi bi-send"></i> Ajukan Peminjaman
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalPinjam')">Batal</button>
    </form>
  </div>
</div>


<script>
  let isInfokus = false;

  /* ── Mobile Menu ── */
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

  /* ── Live Search AJAX (fokus tidak hilang) ── */
  (function() {
    const input    = document.getElementById('searchInput');
    const clearBtn = document.getElementById('searchClearBtn');
    if (!input) return;

    let timer;
    let lastQ = input.value;

    function fetchGrid(q) {
      const url = new URL(window.location.href);
      if (q) url.searchParams.set('q', q);
      else    url.searchParams.delete('q');
      url.searchParams.delete('page');
      window.history.replaceState({}, '', url.toString());

      // Opacity loading
      const grid = document.querySelector('.barang-grid') || document.querySelector('.empty-state');
      const pag  = document.querySelector('.pagination');
      if (grid) grid.style.opacity = '0.5';

      fetch(url.toString())
        .then(r => r.text())
        .then(html => {
          const doc = new DOMParser().parseFromString(html, 'text/html');

          // Ganti grid barang
          const newGrid  = doc.querySelector('.barang-grid');
          const newEmpty = doc.querySelector('.empty-state');
          const curGrid  = document.querySelector('.barang-grid');
          const curEmpty = document.querySelector('.empty-state');
          const wrapper  = document.getElementById('barangContainer');

          if (wrapper) {
            const newWrapper = doc.getElementById('barangContainer');
            if (newWrapper) wrapper.innerHTML = newWrapper.innerHTML;
          }

          // Ganti pagination
          const newPag = doc.querySelector('.pagination');
          const curPag = document.querySelector('.pagination');
          if (newPag && curPag)  curPag.outerHTML  = newPag.outerHTML;
          else if (curPag && !newPag) curPag.remove();

          // Kembalikan fokus
          const inp = document.getElementById('searchInput');
          if (inp) { inp.focus(); inp.setSelectionRange(inp.value.length, inp.value.length); }
        })
        .catch(() => {
          if (grid) grid.style.opacity = '1';
        });
    }

    input.addEventListener('input', function() {
      const q = this.value.trim();
      clearBtn.classList.toggle('show', q.length > 0);
      clearTimeout(timer);
      timer = setTimeout(() => {
        if (q === lastQ) return;
        lastQ = q;
        fetchGrid(q);
      }, 350);
    });

    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(timer);
        lastQ = this.value.trim();
        fetchGrid(lastQ);
      }
    });
  })();

  function clearSearch() {
    const input = document.getElementById('searchInput');
    if (input) { input.value = ''; input.dispatchEvent(new Event('input')); }
    document.getElementById('searchClearBtn').classList.remove('show');
  }

  /* ── Modal ── */
  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.getElementById('pinjamError').classList.remove('show');
  }
  function handleBackdropClick(e, id) {
    if (e.target === document.getElementById(id)) closeModal(id);
  }
  document.addEventListener('keydown', e => { if (e.key==='Escape') closeModal('modalPinjam'); });

  /* ── CART LOGIC ── */
  let cart = [];

  function initCart() {
    try {
      const stored = localStorage.getItem('sarpas_cart');
      if (stored) cart = JSON.parse(stored);
    } catch (e) { cart = []; }
    updateCartUI();
  }

  function saveCart() {
    localStorage.setItem('sarpas_cart', JSON.stringify(cart));
    updateCartUI();
  }

  function updateCartUI() {
    const btn = document.getElementById('floatingCartBtn');
    const badge = document.getElementById('cartBadge');
    if (cart.length > 0) {
      btn.style.display = 'flex';
      badge.textContent = cart.length;
    } else {
      btn.style.display = 'none';
    }
  }

  function getIconHtml(data) {
    if (data.foto) return `<img src="../assets/foto_barang/${data.foto}" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
    const nm = data.nama.toLowerCase();
    let ikon = '<i class="bi bi-box-seam"></i>';
    if (nm.includes('infokus') || nm.includes('proyektor')) ikon = '<i class="bi bi-projector"></i>';
    else if (nm.includes('laptop') || nm.includes('komputer')) ikon = '<i class="bi bi-laptop"></i>';
    else if (nm.includes('buku')) ikon = '<i class="bi bi-book"></i>';
    else if (nm.includes('kursi')) ikon = '<i class="bi bi-egg"></i>';
    else if (nm.includes('meja')) ikon = '<i class="bi bi-table"></i>';
    else if (nm.includes('papan')) ikon = '<i class="bi bi-clipboard"></i>';
    return ikon;
  }

  function addToCart(data) {
    const existing = cart.find(i => i.id === data.id);
    if (existing) {
      if (existing.qty < data.jumlah) existing.qty++;
    } else {
      cart.push({ ...data, qty: 1 });
    }
    saveCart();
    
    // Animate cart button
    const btn = document.getElementById('floatingCartBtn');
    btn.style.transform = 'scale(1.2)';
    setTimeout(() => btn.style.transform = '', 200);
  }

  function pinjamLangsung(data) {
    // Override cart temporarily or just set a temporary checkout state
    // We'll clear the cart and put just this item
    cart = [{ ...data, qty: 1 }];
    saveCart();
    openCartModal();
  }

  function openCartModal() {
    renderCartItems();
    openModal('modalPinjam');
  }

  function renderCartItems() {
    const container = document.getElementById('cartItemsContainer');
    const btnSubmit = document.getElementById('btnSubmitCart');
    
    if (cart.length === 0) {
      container.innerHTML = '<div class="empty-state" style="padding:20px;"><i class="bi bi-cart-x"></i><h3 style="font-size:14px;">Keranjang Kosong</h3></div>';
      btnSubmit.disabled = true;
      btnSubmit.style.opacity = '0.5';
      return;
    }

    btnSubmit.disabled = false;
    btnSubmit.style.opacity = '1';

    let html = '';
    cart.forEach((item, index) => {
      html += `
        <div class="cart-item-row">
          <input type="hidden" name="id_barang[]" value="${item.id}">
          <input type="hidden" name="jumlah[]" id="inputQty_${index}" value="${item.qty}">
          <div class="cart-item-icon">${getIconHtml(item)}</div>
          <div class="cart-item-info">
            <div class="cart-item-name" title="${item.nama}">${item.nama}</div>
            <div class="cart-item-meta">Tersedia: ${item.jumlah} unit</div>
          </div>
          <div class="cart-item-qty">
            <button type="button" class="cart-qty-btn" onclick="changeQty(${index}, -1, ${item.jumlah})"><i class="bi bi-dash"></i></button>
            <input type="text" class="cart-qty-input" value="${item.qty}" readonly>
            <button type="button" class="cart-qty-btn" onclick="changeQty(${index}, 1, ${item.jumlah})"><i class="bi bi-plus"></i></button>
          </div>
          <button type="button" class="cart-item-del" onclick="removeFromCart(${index})" title="Hapus"><i class="bi bi-trash"></i></button>
        </div>
      `;
    });
    container.innerHTML = html;
  }

  function changeQty(index, delta, max) {
    const item = cart[index];
    let newQty = item.qty + delta;
    if (newQty < 1) newQty = 1;
    if (newQty > max) newQty = max;
    item.qty = newQty;
    saveCart();
    renderCartItems();
  }

  function removeFromCart(index) {
    cart.splice(index, 1);
    saveCart();
    if (cart.length === 0) {
      closeModal('modalPinjam');
    } else {
      renderCartItems();
    }
  }

  // Clear cart after successful submit (will be handled via PHP redirect, but just in case we need it)
  // We'll clear it on load if there's a success param in URL
  if (window.location.href.includes('success=')) {
    localStorage.removeItem('sarpas_cart');
    cart = [];
    updateCartUI();
  }

  // Initialize
  initCart();

  /* ── Validasi ── */
  function validateForm() {
    const err    = document.getElementById('pinjamError');
    const tujuan = document.getElementById('inputTujuan').value.trim();
    if (cart.length === 0) {
      err.textContent = 'Keranjang kosong.';
      err.classList.add('show'); return false;
    }
    if (!tujuan) {
      err.textContent = 'Tujuan peminjaman wajib diisi.';
      err.classList.add('show'); return false;
    }
    
    // Clear cart upon submission so next time they visit it's empty
    // But what if validation fails on server? We keep it for now.
    // The success redirect clears it.
    
    err.classList.remove('show'); return true;
  }

  /* ── BFCache Fix: paksa reload jika halaman dari back-forward cache ── */
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
  });
</script>

</body>
</html>