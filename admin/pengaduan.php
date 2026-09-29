<?php
session_start();
require_once "../config/koneksi.php";
require_once "../config/auth_admin.php";
/** @var mysqli $conn */

$msg_success = '';
$msg_error   = '';

/* ══════════════════════════════════════════
   1. POST HANDLER: UPDATE STATUS & TANGGAPAN
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // UPDATE STATUS & TANGGAPAN
    if (isset($_POST['update_pengaduan'])) {
        $id_pengaduan = (int)($_POST['id_pengaduan'] ?? 0);
        $status       = trim($_POST['status'] ?? '');
        $tanggapan    = trim($_POST['tanggapan'] ?? '');

        $valid_status = ['menunggu', 'diproses', 'selesai', 'ditolak'];
        if ($id_pengaduan <= 0 || !in_array($status, $valid_status)) {
            $msg_error = "Data pengaduan atau status tidak valid.";
        } else {
            $esc_tanggapan = $tanggapan !== '' ? "'" . mysqli_real_escape_string($conn, $tanggapan) . "'" : "NULL";
            $sql = "UPDATE pengaduan SET status = '$status', tanggapan = $esc_tanggapan WHERE id_pengaduan = $id_pengaduan";
            if (mysqli_query($conn, $sql)) {
                $tiket = 'PND-' . str_pad($id_pengaduan, 4, '0', STR_PAD_LEFT);
                header("Location: pengaduan.php?success=" . urlencode("Pengaduan $tiket berhasil diperbarui menjadi status \"" . ucfirst($status) . "\"."));
                exit;
            } else {
                $msg_error = "Gagal memperbarui pengaduan: " . mysqli_error($conn);
            }
        }
    }

    // HAPUS PENGADUAN
    if (isset($_POST['hapus_pengaduan'])) {
        $id_pengaduan = (int)($_POST['id_pengaduan'] ?? 0);
        if ($id_pengaduan > 0) {
            // Cek file foto
            $q_foto = mysqli_query($conn, "SELECT foto FROM pengaduan WHERE id_pengaduan = $id_pengaduan LIMIT 1");
            if ($r_f = mysqli_fetch_assoc($q_foto)) {
                if (!empty($r_f['foto'])) {
                    $path_foto = __DIR__ . '/../assets/pengaduan/' . $r_f['foto'];
                    if (file_exists($path_foto)) {
                        @unlink($path_foto);
                    }
                }
            }

            if (mysqli_query($conn, "DELETE FROM pengaduan WHERE id_pengaduan = $id_pengaduan")) {
                header("Location: pengaduan.php?success=" . urlencode("Data pengaduan berhasil dihapus."));
                exit;
            } else {
                $msg_error = "Gagal menghapus pengaduan: " . mysqli_error($conn);
            }
        }
    }
}

if (isset($_GET['success'])) {
    $msg_success = htmlspecialchars($_GET['success']);
}

/* ══════════════════════════════════════════
   2. STATISTIK PENGADUAN
══════════════════════════════════════════ */
$st_total    = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan"))['t'] ?? 0);
$st_menunggu = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan WHERE status='menunggu'"))['t'] ?? 0);
$st_diproses = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan WHERE status='diproses'"))['t'] ?? 0);
$st_selesai  = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan WHERE status='selesai'"))['t'] ?? 0);
$st_ditolak  = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan WHERE status='ditolak'"))['t'] ?? 0);

/* Badges for other navbar links */
$pending_count = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengguna WHERE status='pending'"))['t'] ?? 0);
$pm_menunggu   = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM peminjaman WHERE status='menunggu'"))['t'] ?? 0);

/* ══════════════════════════════════════════
   3. FILTER & PENCARIAN
══════════════════════════════════════════ */
$filter_status = $_GET['status'] ?? '';
$cari          = trim($_GET['q'] ?? '');
$limit         = isset($_GET['limit']) ? max(10, min(100, (int)$_GET['limit'])) : 20;
$page          = max(1, (int)($_GET['p'] ?? 1));
$offset        = ($page - 1) * $limit;

$where = "WHERE 1=1";
if (in_array($filter_status, ['menunggu', 'diproses', 'selesai', 'ditolak'])) {
    $where .= " AND p.status = '$filter_status'";
}
if (!empty($cari)) {
    $esc = mysqli_real_escape_string($conn, $cari);
    $where .= " AND (p.nama_prasarana LIKE '%$esc%' OR p.nama_pelapor LIKE '%$esc%' OR p.kontak_pelapor LIKE '%$esc%' OR p.deskripsi LIKE '%$esc%' OR p.id_pengaduan LIKE '%$esc%')";
}

// Total count for pagination
$cnt_q = mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan p LEFT JOIN ruangan r ON p.id_ruangan = r.id_ruangan $where");
$total_rows = (int)(mysqli_fetch_assoc($cnt_q)['t'] ?? 0);
$total_pages = max(1, ceil($total_rows / $limit));

// Fetch records
$query_aduan = "
    SELECT p.*, r.nama_ruangan, r.keterangan AS lokasi_ruangan
    FROM pengaduan p
    LEFT JOIN ruangan r ON p.id_ruangan = r.id_ruangan
    $where
    ORDER BY 
      CASE WHEN p.status = 'menunggu' THEN 1 WHEN p.status = 'diproses' THEN 2 ELSE 3 END,
      p.tanggal_pengaduan DESC
    LIMIT $limit OFFSET $offset
";
$res_aduan = mysqli_query($conn, $query_aduan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Pengaduan Sarpras — SMAN 10 Pontianak</title>
  <link rel="icon" href="../assets/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    :root {
      --blue:        #4A90C4;
      --blue-dark:   #2B6FA8;
      --blue-deep:   #1B3F6E;
      --green:       #3D9B4A;
      --green-light: #DCFCE7;
      --yellow:      #F5C518;
      --amber:       #D97706;
      --amber-light: #FFFBEB;
      --red:         #DC2626;
      --red-light:   #FEF2F2;
      --bg:          #F0F7FF;
      --card:        #FFFFFF;
      --border:      #D0E4F5;
      --text:        #1B2D45;
      --muted:       #6B7C93;
      --shadow:      0 2px 14px rgba(27,63,110,.09);
      --shadow-md:   0 4px 20px rgba(27,63,110,.12);
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ══ NAVBAR ══ */
    .navbar {
      position: sticky; top: 0; z-index: 100;
      background: var(--blue-deep);
      display: flex; align-items: center;
      padding: 0 28px; height: 62px;
      box-shadow: 0 2px 16px rgba(27,63,110,.3);
    }
    .nav-brand {
      display: flex; align-items: center; gap: 11px;
      text-decoration: none; flex-shrink: 0; margin-right: 32px;
    }
    .nav-brand img { width: 38px; height: 38px; object-fit: contain; }
    .nav-brand-text strong {
      display: block; font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 13px; font-weight: 800; color: white; line-height: 1.2;
    }
    .nav-brand-text span { font-size: 10px; color: rgba(255,255,255,.5); }
    .nav-links { display: flex; align-items: center; gap: 2px; flex: 1; overflow-x: auto; }
    .nav-link {
      padding: 8px 12px; border-radius: 8px;
      color: rgba(255,255,255,.65); text-decoration: none;
      font-size: 13px; font-weight: 500; transition: all .2s; white-space: nowrap;
    }
    .nav-link:hover { color: white; background: rgba(255,255,255,.1); }
    .nav-link.active {
      color: white; font-weight: 700;
      border-bottom: 2px solid var(--yellow);
      border-radius: 0; padding-bottom: 6px;
    }
    .nav-link.logout { margin-left: auto; color: rgba(255,255,255,.5); }
    .nav-link.logout:hover { color: #FCA5A5; background: rgba(239,68,68,.15); }
    .nav-badge {
      display: inline-flex; align-items: center; justify-content: center;
      background: var(--red); color: white;
      width: 17px; height: 17px; border-radius: 50%;
      font-size: 10px; font-weight: 800;
      margin-left: 4px; vertical-align: middle;
    }
    .nav-hamburger {
      display: none; margin-left: auto;
      background: none; border: none; cursor: pointer;
      color: white; font-size: 22px; padding: 6px; border-radius: 8px;
    }
    .nav-mobile-menu {
      display: none; position: fixed; top: 62px; left: 0; right: 0;
      background: var(--blue-deep); box-shadow: 0 8px 24px rgba(27,63,110,.3);
      z-index: 99; flex-direction: column; padding: 8px 16px 16px;
      border-top: 1px solid rgba(255,255,255,.08);
    }
    .nav-mobile-menu.open { display: flex; }
    .nav-mobile-menu .nav-link {
      padding: 12px 14px; border-radius: 10px; font-size: 14px;
      border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .nav-mobile-menu .nav-link.logout { margin-left: 0; margin-top: 4px; }

    /* ══ PAGE LAYOUT ══ */
    .page-wrapper {
      max-width: 1240px; margin: 0 auto;
      padding: 28px 24px 60px; flex: 1; width: 100%;
    }
    .page-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      margin-bottom: 22px; gap: 16px; flex-wrap: wrap;
    }
    .page-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 22px; font-weight: 900; color: var(--text);
      display: flex; align-items: center; gap: 10px;
    }
    .page-sub { font-size: 13px; color: var(--muted); margin-top: 3px; }

    /* ── FLASH ── */
    .flash {
      padding: 12px 16px; border-radius: 10px; font-size: 13px;
      margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
    }
    .flash-success { background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; }
    .flash-error   { background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; }

    /* ── STATS ROW ── */
    .stats-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
      gap: 12px; margin-bottom: 22px;
    }
    .stat-card {
      background: var(--card); border: 1.5px solid var(--border);
      border-radius: 12px; padding: 14px 16px;
      display: flex; align-items: center; gap: 12px;
      transition: all .2s;
    }
    .stat-card:hover { box-shadow: var(--shadow); transform: translateY(-1px); }
    .stat-icon {
      width: 42px; height: 42px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 19px; flex-shrink: 0;
    }
    .stat-val {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 22px; font-weight: 900; line-height: 1.1;
    }
    .stat-lbl { font-size: 11.5px; color: var(--muted); margin-top: 2px; }

    /* ── TOOLBAR ── */
    .toolbar {
      background: var(--card); border: 1.5px solid var(--border);
      border-radius: 12px; padding: 14px 16px; margin-bottom: 20px;
      display: flex; align-items: center; justify-content: space-between;
      gap: 12px; flex-wrap: wrap;
    }
    .filter-tabs { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .ftab-btn {
      padding: 6px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600;
      text-decoration: none; color: var(--muted); background: #F1F5F9;
      transition: all .15s; display: inline-flex; align-items: center; gap: 5px;
    }
    .ftab-btn:hover { background: #E2E8F0; color: var(--text); }
    .ftab-btn.active {
      background: var(--blue-deep); color: #fff;
    }
    .ftab-btn.active.ftab-menunggu { background: var(--amber); }
    .ftab-btn.active.ftab-diproses { background: var(--blue); }
    .ftab-btn.active.ftab-selesai  { background: var(--green); }
    .ftab-btn.active.ftab-ditolak  { background: var(--red); }

    .search-box {
      display: flex; align-items: center; gap: 6px; flex: 1; max-width: 380px;
    }
    .search-input {
      flex: 1; padding: 8px 12px; border: 1.5px solid var(--border);
      border-radius: 8px; font-size: 13px; font-family: 'DM Sans', sans-serif;
      outline: none; transition: border-color .2s;
    }
    .search-input:focus { border-color: var(--blue); }
    .btn-search {
      padding: 8px 14px; background: var(--blue-dark); color: #fff;
      border: none; border-radius: 8px; font-size: 13px; font-weight: 600;
      cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
    }

    /* ── CARD & TABLE ── */
    .card {
      background: var(--card); border: 1.5px solid var(--border);
      border-radius: 14px; box-shadow: var(--shadow);
      overflow: hidden;
    }
    .table-wrap { overflow-x: auto; }
    .table-custom {
      width: 100%; border-collapse: collapse; font-size: 13px;
    }
    .table-custom th {
      background: #F4F8FD; padding: 12px 14px; text-align: left;
      font-size: 11px; font-weight: 800; color: var(--muted);
      letter-spacing: .5px; text-transform: uppercase;
      border-bottom: 2px solid var(--border); white-space: nowrap;
    }
    .table-custom td {
      padding: 14px; border-bottom: 1px solid var(--border);
      vertical-align: top;
    }
    .table-custom tr:last-child td { border-bottom: none; }
    .table-custom tr:hover td { background: #FAFDFE; }

    /* Badges */
    .badge-status {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700;
      white-space: nowrap;
    }
    .badge-menunggu { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }
    .badge-diproses { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
    .badge-selesai  { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }
    .badge-ditolak  { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }

    .tiket-badge {
      display: inline-block; font-size: 10.5px; font-weight: 800;
      background: #EFF6FF; color: #2563EB; padding: 2px 7px;
      border-radius: 5px; margin-bottom: 4px;
    }

    /* Thumb */
    .img-thumb {
      width: 68px; height: 52px; object-fit: cover; border-radius: 6px;
      border: 1.5px solid var(--border); cursor: pointer; transition: transform .2s;
    }
    .img-thumb:hover { transform: scale(1.06); }

    /* Action Buttons */
    .btn-action-tanggapi {
      background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE;
      padding: 6px 12px; border-radius: 7px; font-size: 12px; font-weight: 700;
      cursor: pointer; display: inline-flex; align-items: center; gap: 5px;
      transition: all .15s; white-space: nowrap; text-decoration: none;
    }
    .btn-action-tanggapi:hover { background: #2563EB; color: #fff; }

    .btn-action-wa {
      background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0;
      padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700;
      text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-action-wa:hover { background: #16A34A; color: #fff; }

    .btn-action-del {
      background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA;
      padding: 6px 10px; border-radius: 7px; font-size: 12px; font-weight: 700;
      cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
      transition: all .15s;
    }
    .btn-action-del:hover { background: #DC2626; color: #fff; }

    /* ── MODAL ── */
    .modal-overlay {
      display: none; position: fixed; inset: 0; z-index: 1000;
      background: rgba(15,23,42,.6); backdrop-filter: blur(3px);
      align-items: center; justify-content: center; padding: 16px;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: #fff; border-radius: 16px; width: 100%; max-width: 520px;
      box-shadow: 0 20px 45px rgba(0,0,0,.2); overflow: hidden;
      animation: modalPop .2s ease-out;
    }
    @keyframes modalPop {
      0% { transform: scale(.95); opacity: 0; }
      100% { transform: scale(1); opacity: 1; }
    }
    .modal-header {
      padding: 16px 20px; background: #F8FAFD; border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .modal-title {
      font-family: 'Plus Jakarta Sans', sans-serif; font-size: 15px; font-weight: 800;
      color: var(--text); display: flex; align-items: center; gap: 8px;
    }
    .modal-close {
      background: none; border: none; font-size: 20px; color: var(--muted);
      cursor: pointer; padding: 4px; border-radius: 6px;
    }
    .modal-close:hover { background: #E2E8F0; }
    .modal-body { padding: 20px; max-height: 80vh; overflow-y: auto; }
    .modal-footer {
      padding: 14px 20px; background: #F8FAFD; border-top: 1px solid var(--border);
      display: flex; justify-content: flex-end; gap: 10px;
    }
    .form-group { margin-bottom: 16px; }
    .form-label {
      display: block; font-size: 12px; font-weight: 700; color: var(--text);
      margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px;
    }
    .form-control {
      width: 100%; padding: 9px 12px; border: 1.5px solid var(--border);
      border-radius: 9px; font-size: 13px; font-family: 'DM Sans', sans-serif;
      outline: none; transition: border-color .2s;
    }
    .form-control:focus { border-color: var(--blue); }
    .btn-secondary {
      padding: 9px 16px; background: #E2E8F0; color: var(--text);
      border: none; border-radius: 8px; font-size: 13px; font-weight: 700;
      cursor: pointer;
    }
    .btn-primary {
      padding: 9px 18px; background: var(--blue-dark); color: #fff;
      border: none; border-radius: 8px; font-size: 13px; font-weight: 700;
      cursor: pointer;
    }
    .btn-primary:hover { background: var(--blue-deep); }

    /* Quick reply buttons */
    .quick-replies { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
    .qr-btn {
      font-size: 11px; background: #F1F5F9; border: 1px solid var(--border);
      border-radius: 6px; padding: 3px 8px; cursor: pointer; color: var(--muted);
      transition: all .15s;
    }
    .qr-btn:hover { background: #E0E7FF; color: var(--blue-deep); }

    /* Lightbox */
    .lightbox {
      display: none; position: fixed; inset: 0; z-index: 1100;
      background: rgba(0,0,0,.85); backdrop-filter: blur(4px);
      align-items: center; justify-content: center; padding: 20px;
    }
    .lightbox.open { display: flex; }
    .lightbox img { max-width: 90vw; max-height: 85vh; border-radius: 10px; }
    .lightbox-close {
      position: absolute; top: 20px; right: 20px;
      background: rgba(255,255,255,.2); color: white;
      border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 20px;
      cursor: pointer; display: flex; align-items: center; justify-content: center;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 768px) {
      .navbar { padding: 0 16px; }
      .nav-links { display: none; }
      .nav-hamburger { display: block; }
      .page-wrapper { padding: 20px 14px 40px; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
      .toolbar { flex-direction: column; align-items: stretch; }
      .search-box { max-width: 100%; }
    }
  </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
  <a href="dashboard.php" class="nav-brand">
    <img src="../assets/logo.png" alt="Logo">
    <div class="nav-brand-text">
      <strong>Inventaris SARPRAS</strong>
      <span>SMAN 10 Pontianak</span>
    </div>
  </a>

  <!-- Desktop Menu -->
  <div class="nav-links">
    <a href="dashboard.php"        class="nav-link">Dashboard</a>
    <a href="ruangan.php"          class="nav-link">Prasarana</a>
    <a href="barang.php"           class="nav-link">Sarana</a>
    <a href="pengguna.php"         class="nav-link">Pengguna<?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?></a>
    <a href="peminjaman.php"       class="nav-link">Peminjaman<?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?></a>
    <a href="pengembalian.php"     class="nav-link">Pengembalian</a>
    <a href="stok_habis_pakai.php" class="nav-link">Stok Habis Pakai</a>
    <a href="pengaduan.php"        class="nav-link active">
      Pengaduan
      <?php if ($st_menunggu > 0): ?><span class="nav-badge"><?= $st_menunggu ?></span><?php endif; ?>
    </a>
    <a href="../auth/logout.php"   class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>

  <!-- Hamburger -->
  <button class="nav-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()"><i class="bi bi-list" id="hamburgerIcon"></i></button>
</nav>

<!-- Mobile Menu -->
<div class="nav-mobile-menu" id="mobileMenu">
  <a href="dashboard.php"        class="nav-link">Dashboard</a>
  <a href="ruangan.php"          class="nav-link">Prasarana</a>
  <a href="barang.php"           class="nav-link">Sarana</a>
  <a href="pengguna.php"         class="nav-link">Pengguna<?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?></a>
  <a href="peminjaman.php"       class="nav-link">Peminjaman<?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?></a>
  <a href="pengembalian.php"     class="nav-link">Pengembalian</a>
  <a href="stok_habis_pakai.php" class="nav-link">Stok Habis Pakai</a>
  <a href="pengaduan.php"        class="nav-link active">Pengaduan<?php if ($st_menunggu > 0): ?><span class="nav-badge"><?= $st_menunggu ?></span><?php endif; ?></a>
  <a href="../auth/logout.php"   class="nav-link logout">Logout</a>
</div>

<!-- ══ PAGE CONTENT ══ -->
<div class="page-wrapper">

  <?php if ($msg_success): ?><div class="flash flash-success"><i class="bi bi-check-circle-fill"></i> <?= $msg_success ?></div><?php endif; ?>
  <?php if ($msg_error):   ?><div class="flash flash-error"><i class="bi bi-exclamation-circle-fill"></i> <?= $msg_error ?></div><?php endif; ?>

  <div class="page-header">
    <div>
      <div class="page-title">
        <i class="bi bi-megaphone-fill" style="color:var(--amber);"></i>
        Kelola Pengaduan Sarana & Prasarana
      </div>
      <div class="page-sub">Pantau laporan kerusakan dari warga sekolah, perbarui status penanganan, dan berikan tanggapan tim Sarpras.</div>
    </div>
    <div>
      <a href="../pengaduan.php" target="_blank" class="btn-action-tanggapi" style="padding:8px 14px;">
        <i class="bi bi-box-arrow-up-right"></i> Buka Halaman Pengaduan Publik
      </a>
    </div>
  </div>

  <!-- ── STATS ROW ── -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#EEF2FF;color:#4F46E5;"><i class="bi bi-inbox-fill"></i></div>
      <div>
        <div class="stat-val"><?= number_format($st_total) ?></div>
        <div class="stat-lbl">Total Laporan</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFFBEB;color:#D97706;"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="stat-val" style="color:#D97706;"><?= number_format($st_menunggu) ?></div>
        <div class="stat-lbl">Menunggu Verifikasi</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#EFF6FF;color:#2563EB;"><i class="bi bi-tools"></i></div>
      <div>
        <div class="stat-val" style="color:#2563EB;"><?= number_format($st_diproses) ?></div>
        <div class="stat-lbl">Sedang Diproses</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#F0FDF4;color:#16A34A;"><i class="bi bi-check-circle-fill"></i></div>
      <div>
        <div class="stat-val" style="color:#16A34A;"><?= number_format($st_selesai) ?></div>
        <div class="stat-lbl">Selesai</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FEF2F2;color:#DC2626;"><i class="bi bi-x-circle-fill"></i></div>
      <div>
        <div class="stat-val" style="color:#DC2626;"><?= number_format($st_ditolak) ?></div>
        <div class="stat-lbl">Ditolak</div>
      </div>
    </div>
  </div>

  <!-- ── TOOLBAR ── -->
  <div class="toolbar">
    <div class="filter-tabs">
      <a href="pengaduan.php<?= !empty($cari) ? '?q='.urlencode($cari) : '' ?>" class="ftab-btn <?= empty($filter_status) ? 'active' : '' ?>">
        Semua (<?= $st_total ?>)
      </a>
      <a href="pengaduan.php?status=menunggu<?= !empty($cari) ? '&q='.urlencode($cari) : '' ?>" class="ftab-btn ftab-menunggu <?= $filter_status === 'menunggu' ? 'active' : '' ?>">
        Menunggu (<?= $st_menunggu ?>)
      </a>
      <a href="pengaduan.php?status=diproses<?= !empty($cari) ? '&q='.urlencode($cari) : '' ?>" class="ftab-btn ftab-diproses <?= $filter_status === 'diproses' ? 'active' : '' ?>">
        Diproses (<?= $st_diproses ?>)
      </a>
      <a href="pengaduan.php?status=selesai<?= !empty($cari) ? '&q='.urlencode($cari) : '' ?>" class="ftab-btn ftab-selesai <?= $filter_status === 'selesai' ? 'active' : '' ?>">
        Selesai (<?= $st_selesai ?>)
      </a>
      <a href="pengaduan.php?status=ditolak<?= !empty($cari) ? '&q='.urlencode($cari) : '' ?>" class="ftab-btn ftab-ditolak <?= $filter_status === 'ditolak' ? 'active' : '' ?>">
        Ditolak (<?= $st_ditolak ?>)
      </a>
    </div>

    <form action="pengaduan.php" method="GET" class="search-box">
      <?php if (!empty($filter_status)): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
      <input type="text" name="q" value="<?= htmlspecialchars($cari) ?>" class="search-input" placeholder="Cari tiket, fasilitas, pelapor...">
      <button type="submit" class="btn-search"><i class="bi bi-search"></i> Cari</button>
    </form>
  </div>

  <!-- ── DATA TABLE ── -->
  <div class="card">
    <div class="table-wrap">
      <table class="table-custom">
        <thead>
          <tr>
            <th style="width:110px;">Tiket / Tgl</th>
            <th style="width:180px;">Prasarana / Ruangan</th>
            <th style="width:80px;">Foto</th>
            <th>Deskripsi Keluhan</th>
            <th style="width:160px;">Pelapor & Kontak</th>
            <th style="width:120px;">Status</th>
            <th style="width:180px;">Tanggapan Sarpras</th>
            <th style="width:120px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($res_aduan) === 0): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:48px 16px;color:var(--muted);">
              <i class="bi bi-clipboard-x" style="font-size:38px;color:#CBD5E1;display:block;margin-bottom:8px;"></i>
              <div style="font-weight:700;font-size:14px;color:var(--text);">Tidak ada data pengaduan</div>
              <div style="font-size:12px;">Belum ada laporan yang sesuai dengan filter atau kata kunci pencarian.</div>
            </td>
          </tr>
          <?php else: ?>
            <?php while ($row = mysqli_fetch_assoc($res_aduan)):
              $tiket_code = 'PND-' . str_pad($row['id_pengaduan'], 4, '0', STR_PAD_LEFT);
              $b_class = 'badge-menunggu';
              $b_text  = 'Menunggu';
              $b_icon  = 'bi-hourglass-split';
              if ($row['status'] === 'diproses') {
                  $b_class = 'badge-diproses'; $b_text = 'Diproses'; $b_icon = 'bi-gear-fill';
              } elseif ($row['status'] === 'selesai') {
                  $b_class = 'badge-selesai'; $b_text = 'Selesai'; $b_icon = 'bi-check-circle-fill';
              } elseif ($row['status'] === 'ditolak') {
                  $b_class = 'badge-ditolak'; $b_text = 'Ditolak'; $b_icon = 'bi-x-circle-fill';
              }

              // WhatsApp Link Format
              $wa_link = '';
              if (!empty($row['kontak_pelapor'])) {
                  $clean_no = preg_replace('/[^0-9]/', '', $row['kontak_pelapor']);
                  if (str_starts_with($clean_no, '0')) {
                      $clean_no = '62' . substr($clean_no, 1);
                  }
                  if (strlen($clean_no) >= 9) {
                      $wa_link = "https://wa.me/{$clean_no}?text=" . urlencode("Halo {$row['nama_pelapor']}, kami dari Tim SARPRAS SMAN 10 Pontianak terkait laporan pengaduan [{$tiket_code}] fasilitas \"{$row['nama_prasarana']}\"...");
                  }
              }
            ?>
            <tr>
              <!-- Tiket & Waktu -->
              <td>
                <span class="tiket-badge"><?= $tiket_code ?></span>
                <div style="font-size:11px;color:var(--muted);white-space:nowrap;">
                  <i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($row['tanggal_pengaduan'])) ?>
                </div>
                <div style="font-size:10.5px;color:var(--muted);">
                  <?= date('H:i', strtotime($row['tanggal_pengaduan'])) ?> WIB
                </div>
              </td>

              <!-- Prasarana -->
              <td>
                <strong style="color:var(--text);font-size:13.5px;display:block;">
                  <?= htmlspecialchars($row['nama_prasarana']) ?>
                </strong>
                <?php if (!empty($row['lokasi_ruangan'])): ?>
                  <span style="font-size:11px;color:var(--muted);display:inline-flex;align-items:center;gap:3px;margin-top:2px;">
                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($row['lokasi_ruangan']) ?>
                  </span>
                <?php endif; ?>
              </td>

              <!-- Foto -->
              <td>
                <?php if (!empty($row['foto']) && file_exists(__DIR__ . '/../assets/pengaduan/' . $row['foto'])): ?>
                  <img src="../assets/pengaduan/<?= htmlspecialchars($row['foto']) ?>"
                       alt="Bukti" class="img-thumb"
                       onclick="openLightbox(this.src)" title="Klik untuk perbesar">
                <?php else: ?>
                  <span style="font-size:11px;color:var(--muted);font-style:italic;">Tanpa foto</span>
                <?php endif; ?>
              </td>

              <!-- Deskripsi -->
              <td>
                <div style="max-height:80px;overflow-y:auto;line-height:1.5;font-size:12.5px;color:#334155;word-break:break-word;">
                  <?= nl2br(htmlspecialchars($row['deskripsi'])) ?>
                </div>
              </td>

              <!-- Pelapor & Kontak -->
              <td>
                <div style="font-weight:700;font-size:12.5px;color:var(--text);">
                  <?= htmlspecialchars($row['nama_pelapor']) ?>
                </div>
                <?php if (!empty($row['kontak_pelapor'])): ?>
                  <div style="margin-top:4px;">
                    <?php if ($wa_link): ?>
                      <a href="<?= $wa_link ?>" target="_blank" class="btn-action-wa" title="Hubungi via WhatsApp">
                        <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($row['kontak_pelapor']) ?>
                      </a>
                    <?php else: ?>
                      <span style="font-size:11.5px;color:var(--muted);"><i class="bi bi-telephone"></i> <?= htmlspecialchars($row['kontak_pelapor']) ?></span>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <span style="font-size:11px;color:var(--muted);font-style:italic;">Tidak ada kontak</span>
                <?php endif; ?>
              </td>

              <!-- Status -->
              <td>
                <span class="badge-status <?= $b_class ?>">
                  <i class="bi <?= $b_icon ?>"></i> <?= $b_text ?>
                </span>
              </td>

              <!-- Tanggapan -->
              <td>
                <?php if (!empty($row['tanggapan'])): ?>
                  <div style="font-size:12px;color:#1E293B;background:#F8FAFD;border-left:3px solid var(--blue);padding:6px 10px;border-radius:0 6px 6px 0;line-height:1.4;">
                    <?= nl2br(htmlspecialchars($row['tanggapan'])) ?>
                  </div>
                <?php else: ?>
                  <span style="font-size:11px;color:var(--muted);font-style:italic;">Belum ada tanggapan</span>
                <?php endif; ?>
              </td>

              <!-- Aksi -->
              <td style="text-align:center;">
                <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                  <button type="button" class="btn-action-tanggapi"
                          onclick='openModalTanggapi(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                          title="Tanggapi & Ubah Status">
                    <i class="bi bi-pencil-square"></i> Tanggapi
                  </button>
                  <form action="pengaduan.php" method="POST" onsubmit="return confirm('Hapus pengaduan tiket <?= $tiket_code ?> ini secara permanen?')">
                    <input type="hidden" name="hapus_pengaduan" value="1">
                    <input type="hidden" name="id_pengaduan" value="<?= $row['id_pengaduan'] ?>">
                    <button type="submit" class="btn-action-del" title="Hapus Pengaduan">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;font-size:12.5px;">
      <div style="color:var(--muted);">
        Menampilkan halaman <strong><?= $page ?></strong> dari <strong><?= $total_pages ?></strong> (Total <?= $total_rows ?> laporan)
      </div>
      <div style="display:flex;gap:5px;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a href="pengaduan.php?p=<?= $i ?><?= !empty($filter_status)?'&status='.$filter_status:'' ?><?= !empty($cari)?'&q='.urlencode($cari):'' ?>"
             style="padding:5px 11px;border-radius:6px;border:1px solid var(--border);text-decoration:none;font-weight:700;<?= $i === $page ? 'background:var(--blue-deep);color:#fff;' : 'background:#fff;color:var(--text);' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- ══ MODAL TANGGAPI & UBAH STATUS ══ -->
<div class="modal-overlay" id="modalTanggapi">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">
        <i class="bi bi-reply-all-fill" style="color:var(--blue);"></i>
        Tanggapi Pengaduan <span id="mTiket" style="color:var(--blue-dark);margin-left:4px;"></span>
      </div>
      <button type="button" class="modal-close" onclick="closeModalTanggapi()">&times;</button>
    </div>

    <form action="pengaduan.php" method="POST">
      <input type="hidden" name="update_pengaduan" value="1">
      <input type="hidden" name="id_pengaduan" id="mIdPengaduan" value="">

      <div class="modal-body">
        <div style="background:#F8FAFD;border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:16px;">
          <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;">Prasarana / Fasilitas</div>
          <div style="font-weight:800;font-size:14px;color:var(--text);" id="mPrasarana">—</div>
          <div style="font-size:12px;color:#475569;margin-top:6px;line-height:1.4;" id="mDeskripsi">—</div>
        </div>

        <div class="form-group">
          <label class="form-label">Status Tindak Lanjut</label>
          <select name="status" id="mStatus" class="form-control" required>
            <option value="menunggu">Menunggu Verifikasi</option>
            <option value="diproses">Sedang Diproses (Teknisi/Pengerjaan)</option>
            <option value="selesai">Selesai (Sudah Diperbaiki)</option>
            <option value="ditolak">Ditolak (Tidak Memenuhi Syarat / Dibatalkan)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Tanggapan / Catatan Tim Sarpras</label>
          <textarea name="tanggapan" id="mTanggapan" class="form-control" rows="4"
                    placeholder="Tuliskan keterangan perbaikan, jadwal penanganan teknisi, atau alasan jika ditolak..."></textarea>
          
          <!-- Template balasan cepat -->
          <div style="margin-top:8px;">
            <div style="font-size:11px;color:var(--muted);font-weight:700;margin-bottom:4px;">Template Cepat:</div>
            <div class="quick-replies">
              <button type="button" class="qr-btn" onclick="setQuickReply('Laporan telah kami terima dan verifikasi. Teknisi SARPRAS dijadwalkan memeriksa lokasi secepatnya.', 'diproses')">
                &#9654; Jadwalkan Teknisi
              </button>
              <button type="button" class="qr-btn" onclick="setQuickReply('Perbaikan fasilitas telah selesai dilaksanakan oleh tim SARPRAS. Terima kasih atas laporannya!', 'selesai')">
                &#9654; Perbaikan Selesai
              </button>
              <button type="button" class="qr-btn" onclick="setQuickReply('Fasilitas sedang menunggu pengadaan suku cadang pengganti. Pengerjaan akan dilanjutkan setelah barang tersedia.', 'diproses')">
                &#9654; Tunggu Sparepart
              </button>
              <button type="button" class="qr-btn" onclick="setQuickReply('Mohon maaf, laporan pengaduan tidak dapat diproses karena prasarana yang dilaporkan tidak berada di bawah wewenang sekolah / informasi kurang lengkap.', 'ditolak')">
                &#9654; Tolak Aduan
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeModalTanggapi()">Batal</button>
        <button type="submit" class="btn-primary">
          <i class="bi bi-check2-circle"></i> Simpan Tanggapan & Status
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══ LIGHTBOX MODAL ══ -->
<div class="lightbox" id="lightboxModal" onclick="closeLightbox(event)">
  <button type="button" class="lightbox-close" onclick="closeLightboxDirect()">&times;</button>
  <img src="" alt="Bukti Pengaduan" id="lightboxImg">
</div>

<script>
  function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    menu.classList.toggle('open');
  }

  function openModalTanggapi(row) {
    const tiket = 'PND-' + String(row.id_pengaduan).padStart(4, '0');
    document.getElementById('mTiket').innerText = tiket;
    document.getElementById('mIdPengaduan').value = row.id_pengaduan;
    document.getElementById('mPrasarana').innerText = row.nama_prasarana + (row.lokasi_ruangan ? ' (' + row.lokasi_ruangan + ')' : '');
    document.getElementById('mDeskripsi').innerText = row.deskripsi;
    document.getElementById('mStatus').value = row.status;
    document.getElementById('mTanggapan').value = row.tanggapan || '';
    document.getElementById('modalTanggapi').classList.add('open');
  }

  function closeModalTanggapi() {
    document.getElementById('modalTanggapi').classList.remove('open');
  }

  function setQuickReply(text, status) {
    document.getElementById('mTanggapan').value = text;
    if (status) {
      document.getElementById('mStatus').value = status;
    }
  }

  function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxModal').classList.add('open');
  }

  function closeLightbox(e) {
    if (e.target.id === 'lightboxModal') {
      closeLightboxDirect();
    }
  }

  function closeLightboxDirect() {
    document.getElementById('lightboxModal').classList.remove('open');
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeModalTanggapi();
      closeLightboxDirect();
    }
  });
</script>

</body>
</html>
