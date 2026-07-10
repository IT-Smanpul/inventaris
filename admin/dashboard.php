<?php
session_start();
require_once "../config/koneksi.php";
require_once "../config/auth_admin.php";

/* ══════════════════════════════════════════
   AJAX: Data chart tahunan (dipanggil dari JS)
══════════════════════════════════════════ */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'chart_tahunan') {
    header('Content-Type: application/json');
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
    $tahun_sekarang = (int)date('Y');
    if ($tahun < 2020 || $tahun > $tahun_sekarang) $tahun = $tahun_sekarang;

    $nama_bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    $labels = $pinjam = $kembali = [];
    for ($m = 1; $m <= 12; $m++) {
        $bln = sprintf('%04d-%02d', $tahun, $m);
        $labels[] = $nama_bulan[$m - 1] . ' ' . $tahun;
        $cp = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) as t FROM peminjaman WHERE DATE_FORMAT(tanggal_pinjam,'%Y-%m')='$bln'"))['t'];
        $ck = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) as t FROM peminjaman
             WHERE status='dikembalikan'
             AND DATE_FORMAT(tgl_kembali_aktual,'%Y-%m')='$bln'"))['t'];
        $pinjam[]  = (int)$cp;
        $kembali[] = (int)$ck;
    }
    echo json_encode(['labels' => $labels, 'pinjam' => $pinjam, 'kembali' => $kembali]);
    exit;
}

/* ══════════════════════════════════════════
   STATISTIK UTAMA
══════════════════════════════════════════ */

// Total barang, ruangan, pengguna
$total_barang   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM barang"))['t'];
$total_ruangan  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM ruangan"))['t'];
$total_pengguna = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengguna WHERE status='aktif'"))['t'];

// jumlah barang
$jumlah = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(jumlah),0) as t FROM barang"))['t'];

// Peminjaman
$pm_menunggu     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM peminjaman WHERE status='menunggu'"))['t'];
$pm_dipinjam     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM peminjaman WHERE status='dipinjam'"))['t'];
$pm_dikembalikan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM peminjaman WHERE status='dikembalikan'"))['t'];
$pm_terlambat    = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman
     WHERE status='dipinjam' AND waktu_selesai IS NOT NULL
       AND CONCAT(tanggal_pinjam,' ',waktu_selesai) < NOW()"))['t'];

// Kondisi barang: laik vs tidak laik (akumulasi semua unit)
$laik_row         = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(jumlah_laik),0) AS total_laik,
            COALESCE(SUM(jumlah_tidak_laik),0) AS total_tidak_laik
     FROM barang"));
$total_laik       = (int)$laik_row['total_laik'];
$total_tidak_laik = (int)$laik_row['total_tidak_laik'];

// Pengguna pending
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengguna WHERE status='pending'"))['t'];

// Barang bisa dipinjam vs tidak
$bisa_pinjam    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM barang WHERE bisa_dipinjam=1"))['t'];
$tidak_pinjam   = $total_barang - $bisa_pinjam;

/* ── Peminjaman 7 hari terakhir (chart) ── */
$chart_labels = [];
$chart_pinjam = [];
$chart_kembali= [];
for ($i = 6; $i >= 0; $i--) {
    $tgl   = date('Y-m-d', strtotime("-$i days"));
    $label = date('d/m', strtotime("-$i days"));
    $chart_labels[] = $label;

    $cp = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as t FROM peminjaman WHERE DATE(tanggal_pinjam)='$tgl'"))['t'];
    $ck = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as t FROM peminjaman WHERE DATE(tgl_kembali_aktual)='$tgl'"))['t'];
    $chart_pinjam[]  = (int)$cp;
    $chart_kembali[] = (int)$ck;
}

/* ── Peminjaman & Pengembalian per bulan (per tahun) ── */
$tahun_sekarang = (int)date('Y');
$chart_bulanan_labels  = [];
$chart_bulanan_pinjam  = [];
$chart_bulanan_kembali = [];
for ($m = 1; $m <= 12; $m++) {
    $bln = sprintf('%04d-%02d', $tahun_sekarang, $m);
    $lbl = date('M', mktime(0,0,0,$m,1)) . ' ' . $tahun_sekarang;
    $chart_bulanan_labels[] = $lbl;
    $cp = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as t FROM peminjaman WHERE DATE_FORMAT(tanggal_pinjam,'%Y-%m')='$bln'"))['t'];
    $ck = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as t FROM peminjaman
         WHERE status='dikembalikan'
         AND DATE_FORMAT(tgl_kembali_aktual,'%Y-%m')='$bln'"))['t'];
    $chart_bulanan_pinjam[]  = (int)$cp;
    $chart_bulanan_kembali[] = (int)$ck;
}

/* ── Aktivitas terbaru (peminjaman) ── */
$aktivitas_q = mysqli_query($conn, "
    SELECT pm.*, pg.nama AS nama_peminjam, pg.role AS role_peminjam,
           GROUP_CONCAT(b.nama_barang ORDER BY b.nama_barang SEPARATOR ', ') AS nama_barang_list
    FROM peminjaman pm
    JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
    LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
    LEFT JOIN barang b ON dp.id_barang=b.id_barang
    GROUP BY pm.id_peminjaman
    ORDER BY pm.id_peminjaman DESC
    LIMIT 8
");
$aktivitas_list = [];
while ($av = mysqli_fetch_assoc($aktivitas_q)) $aktivitas_list[] = $av;

/* ── Barang jumlah rendah (≤ 3) ── */
$jumlah_rendah_q = mysqli_query($conn, "
    SELECT b.*, r.nama_ruangan
    FROM barang b
    LEFT JOIN ruangan r ON b.id_ruangan=r.id_ruangan
    WHERE b.jumlah <= 3
    ORDER BY b.jumlah ASC
    LIMIT 6
");
$jumlah_rendah = [];
while ($sv = mysqli_fetch_assoc($jumlah_rendah_q)) $jumlah_rendah[] = $sv;

/* ── Ruangan terbanyak barang (top 6) ── */
$top_ruangan_q = mysqli_query($conn, "
    SELECT r.nama_ruangan, COUNT(b.id_barang) as jml, COALESCE(SUM(b.jumlah),0) as jumlah
    FROM ruangan r
    LEFT JOIN barang b ON r.id_ruangan=b.id_ruangan
    GROUP BY r.id_ruangan, r.nama_ruangan
    ORDER BY jml DESC
    LIMIT 6
");
$top_ruangan = [];
while ($tr = mysqli_fetch_assoc($top_ruangan_q)) $top_ruangan[] = $tr;
$max_jml = $top_ruangan ? max(array_column($top_ruangan, 'jml')) : 1;

/* ── Peminjam terbanyak (top 5) ── */
$top_peminjam_q = mysqli_query($conn, "
    SELECT pg.nama, pg.role, COUNT(pm.id_peminjaman) as jml
    FROM peminjaman pm
    JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
    GROUP BY pm.id_pengguna
    ORDER BY jml DESC
    LIMIT 5
");
$top_peminjam = [];
while ($tp = mysqli_fetch_assoc($top_peminjam_q)) $top_peminjam[] = $tp;

/* ── Pengembalian terlambat ── */
$terlambat_q = mysqli_query($conn, "
    SELECT pm.*, pg.nama AS nama_peminjam,
           GROUP_CONCAT(b.nama_barang SEPARATOR ', ') AS nama_barang_list,
           TIMESTAMPDIFF(HOUR, CONCAT(pm.tanggal_pinjam,' ',pm.waktu_selesai), NOW()) AS jam_terlambat
    FROM peminjaman pm
    JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
    LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
    LEFT JOIN barang b ON dp.id_barang=b.id_barang
    WHERE pm.status='dipinjam' AND pm.waktu_selesai IS NOT NULL
      AND CONCAT(pm.tanggal_pinjam,' ',pm.waktu_selesai) < NOW()
    GROUP BY pm.id_peminjaman
    ORDER BY jam_terlambat DESC
    LIMIT 5
");
$terlambat_list = [];
while ($tl = mysqli_fetch_assoc($terlambat_q)) $terlambat_list[] = $tl;

$nama_admin = $_SESSION['nama'] ?? 'Admin';
$role_admin = $_SESSION['role'] ?? 'admin';
$today = date('l, d F Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Inventaris SARPRAS</title>
  <link rel="icon" href="../assets/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=DM+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --blue:      #4A90C4;
      --blue-dark: #2B6FA8;
      --blue-deep: #1B3F6E;
      --green:     #3D9B4A;
      --green-light:#6DC05A;
      --yellow:    #F5C518;
      --orange:    #F07C1B;
      --red:       #E03B3B;
      --bg:        #F0F7FF;
      --card:      #FFFFFF;
      --text:      #1B2D45;
      --muted:     #6B7C93;
      --border:    #D0E4F5;
      --shadow:    0 2px 14px rgba(27,63,110,.09);
      --shadow-md: 0 4px 20px rgba(27,63,110,.12);
      --shadow-lg: 0 8px 32px rgba(27,63,110,.15);
    }

    html { height: 100%; scroll-behavior: smooth; }
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
      position: sticky; top: 0; z-index: 200;
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
      display: block;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 13px; font-weight: 800; color: white; line-height: 1.2;
    }
    .nav-brand-text span { font-size: 10px; color: rgba(255,255,255,.5); }

    .nav-links { display: flex; align-items: center; gap: 2px; flex: 1; }
    .nav-link {
      padding: 8px 13px; border-radius: 8px;
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

    /* Hamburger */
    .nav-hamburger {
      display: none; margin-left: auto;
      background: none; border: none; cursor: pointer;
      color: white; font-size: 22px; padding: 6px; border-radius: 8px;
    }
    .nav-hamburger:hover { background: rgba(255,255,255,.1); }

    .nav-mobile-menu {
      display: none; position: fixed;
      top: 62px; left: 0; right: 0;
      background: var(--blue-deep);
      box-shadow: 0 8px 24px rgba(27,63,110,.3);
      z-index: 199; flex-direction: column;
      padding: 10px 16px 20px;
      border-top: 1px solid rgba(255,255,255,.1);
      max-height: calc(100vh - 62px); overflow-y: auto;
    }
    .nav-mobile-menu.open { display: flex; }
    .nav-mobile-menu .nav-link {
      padding: 13px 14px; border-radius: 10px; font-size: 14px;
      border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .nav-mobile-menu .nav-link:last-child { border-bottom: none; }
    .nav-mobile-menu .nav-link.logout { margin-left: 0; margin-top: 6px; }

    /* ══ PAGE ══ */
    .page-wrapper {
      width: 100%; margin: 0 auto;
      padding: 28px 32px 60px; flex: 1;
    }

    /* ══ PAGE HEADER ══ */
    .page-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      margin-bottom: 24px; gap: 16px; flex-wrap: wrap;
    }
    .page-header-left {}
    .page-greeting {
      font-size: 12px; color: var(--muted); font-weight: 500;
      text-transform: uppercase; letter-spacing: .8px; margin-bottom: 4px;
    }
    .page-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 26px; font-weight: 900; color: var(--text); line-height: 1.1;
    }
    .page-date { font-size: 13px; color: var(--muted); margin-top: 4px; }

    /* Alert banners */
    .alert-banner {
      display: flex; align-items: center; gap: 12px;
      padding: 12px 18px; border-radius: 12px;
      font-size: 13px; font-weight: 600; margin-bottom: 20px;
      cursor: default;
    }
    .alert-warning { background: #FFFBEB; border: 1.5px solid #FDE68A; color: #92400E; }
    .alert-danger  { background: #FEF2F2; border: 1.5px solid #FCA5A5; color: #B91C1C; }
    .alert-banner i { font-size: 18px; flex-shrink: 0; }
    .alert-banner a { color: inherit; font-weight: 700; text-decoration: underline; margin-left: 4px; }

    /* ══ STAT CARDS GRID ══ */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }
    .stat-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px;
      box-shadow: var(--shadow);
      transition: all .22s;
      position: relative; overflow: hidden;
      text-decoration: none; color: inherit;
      display: block;
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
      border-color: var(--blue);
    }
    .stat-card-accent {
      position: absolute; top: 0; left: 0; right: 0;
      height: 3px; border-radius: 16px 16px 0 0;
    }
    .stat-card-icon {
      width: 44px; height: 44px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; margin-bottom: 14px;
    }
    .stat-card-num {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 30px; font-weight: 900; color: var(--text); line-height: 1;
    }
    .stat-card-label { font-size: 12px; color: var(--muted); margin-top: 5px; font-weight: 500; }
    .stat-card-change {
      display: inline-flex; align-items: center; gap: 4px;
      font-size: 11px; font-weight: 700; margin-top: 8px;
      padding: 3px 8px; border-radius: 20px;
    }

    /* ══ SECTION HEADER ══ */
    .section-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 16px; font-weight: 800; color: var(--text);
      margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
    }
    .section-title i { color: var(--blue); }
    .section-title a {
      margin-left: auto; font-size: 12px; font-weight: 700;
      color: var(--blue-dark); text-decoration: none;
    }
    .section-title a:hover { color: var(--blue-deep); text-decoration: underline; }

    /* ══ GRID LAYOUT ══ */
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    .grid-3 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }

    /* ══ CARD ══ */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      box-shadow: var(--shadow);
      overflow: hidden;
    }
    .card-body { padding: 20px; }
    .card-header-bar {
      padding: 14px 20px; border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .card-header-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px; font-weight: 800; color: var(--text);
      display: flex; align-items: center; gap: 7px;
    }
    .card-header-title i { color: var(--blue); }
    .card-header-sub { font-size: 12px; color: var(--muted); }

    /* ══ CHART WRAPPER ══ */
    .chart-wrap { position: relative; height: 230px; transition: opacity .2s; }
    .chart-wrap-sm { position: relative; height: 180px; }

    /* Year select */
    .year-select {
      appearance: none;
      background: var(--bg) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 8px center;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      padding: 5px 28px 5px 11px;
      font-size: 12px; font-weight: 700;
      color: var(--text);
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: border-color .2s;
    }
    .year-select:hover, .year-select:focus { border-color: var(--blue); outline: none; }

    /* Chart inline legend */
    .chart-legend-inline {
      display: flex; align-items: center; gap: 5px;
      font-size: 11.5px; font-weight: 600; color: var(--muted);
    }
    .chart-legend-dot {
      width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ══ ACTIVITY LIST ══ */
    .activity-list { }
    .activity-item {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 12px 0; border-bottom: 1px solid var(--border);
    }
    .activity-item:last-child { border-bottom: none; }
    .activity-dot {
      width: 36px; height: 36px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 15px; flex-shrink: 0;
    }
    .activity-content { flex: 1; min-width: 0; }
    .activity-title { font-size: 13px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .activity-sub { font-size: 11px; color: var(--muted); margin-top: 2px; }
    .activity-time { font-size: 11px; color: var(--muted); flex-shrink: 0; }

    /* ══ BADGES ══ */
    .badge {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 9px; border-radius: 20px;
      font-size: 10.5px; font-weight: 700; white-space: nowrap;
    }
    .badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; opacity:.7; }
    .badge-success { background: #F0FDF4; color: #15803D; }
    .badge-info    { background: #EFF6FF; color: #2563EB; }
    .badge-warning { background: #FFFBEB; color: #D97706; }
    .badge-danger  { background: #FEF2F2; color: #DC2626; }
    .badge-muted   { background: #F1F5F9; color: #64748B; }
    .badge-orange  { background: #FFF7ED; color: #EA580C; }

    /* ══ jumlah RENDAH ══ */
    .jumlah-item {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 0; border-bottom: 1px solid var(--border);
    }
    .jumlah-item:last-child { border-bottom: none; }
    .jumlah-num {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 20px; font-weight: 900;
      width: 40px; text-align: center; flex-shrink: 0;
    }
    .jumlah-0  { color: #DC2626; }
    .jumlah-1  { color: #DC2626; }
    .jumlah-2  { color: #EA580C; }
    .jumlah-3  { color: #D97706; }
    .jumlah-info { flex: 1; min-width: 0; }
    .jumlah-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .jumlah-room { font-size: 11px; color: var(--muted); margin-top: 2px; }

    /* ══ PROGRESS BAR (top ruangan) ══ */
    .room-bar-item { margin-bottom: 14px; }
    .room-bar-item:last-child { margin-bottom: 0; }
    .room-bar-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px; }
    .room-bar-name { font-size: 12.5px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }
    .room-bar-count { font-size: 12px; font-weight: 700; color: var(--blue-dark); flex-shrink: 0; }
    .room-bar-track { height: 7px; background: var(--border); border-radius: 10px; overflow: hidden; }
    .room-bar-fill { height: 100%; border-radius: 10px; transition: width .8s cubic-bezier(.4,0,.2,1); background: linear-gradient(90deg, var(--blue) 0%, var(--blue-dark) 100%); }

    /* ══ KONDISI DONUT ══ */
    .kondisi-legend { display: flex; flex-direction: column; gap: 8px; margin-top: 14px; }
    .legend-item { display: flex; align-items: center; gap: 8px; font-size: 12px; }
    .legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .legend-label { flex: 1; color: var(--text); font-weight: 500; }
    .legend-val { font-weight: 800; color: var(--text); }

    /* ══ TERLAMBAT LIST ══ */
    .terlambat-item {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 10px 0; border-bottom: 1px solid #FECACA;
    }
    .terlambat-item:last-child { border-bottom: none; }
    .terlambat-icon { width: 32px; height: 32px; border-radius: 9px; background: #FEF2F2; display: flex; align-items: center; justify-content: center; color: #DC2626; font-size: 14px; flex-shrink: 0; }
    .terlambat-info { flex: 1; min-width: 0; }
    .terlambat-name { font-size: 12.5px; font-weight: 600; color: var(--text); }
    .terlambat-barang { font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
    .terlambat-dur { font-size: 11px; font-weight: 700; color: #DC2626; flex-shrink: 0; }

    /* ══ PEMINJAMAN STATUS SUMMARY ══ */
    .pm-status-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
    .pm-status-card {
      border-radius: 14px; padding: 16px;
      display: flex; align-items: center; gap: 12px;
    }
    .pm-status-card.menunggu  { background: #FFFBEB; border: 1.5px solid #FDE68A; }
    .pm-status-card.dipinjam  { background: #EFF6FF; border: 1.5px solid #BFDBFE; }
    .pm-status-card.kembali   { background: #F0FDF4; border: 1.5px solid #BBF7D0; }
    .pm-icon { font-size: 24px; width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .pm-num { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 28px; font-weight: 900; line-height: 1; }
    .pm-lbl { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 500; }

    /* ══ EMPTY ══ */
    .empty-mini { text-align: center; padding: 28px 16px; color: var(--muted); font-size: 13px; }
    .empty-mini i { font-size: 28px; display: block; margin-bottom: 8px; color: #C8DCEE; }

    /* ══ FOOTER ══ */
    footer { background: var(--blue-deep); color: rgba(255,255,255,.55); text-align: center; padding: 20px; font-size: 12px; }

    /* ══ RESPONSIVE ══ */
    @media (max-width: 1024px) {
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .grid-3 { grid-template-columns: 1fr; }
      .quick-actions { grid-template-columns: repeat(4, 1fr); }
    }

    @media (max-width: 768px) {
      .navbar { position: relative; }
      .nav-links { display: none; }
      .nav-hamburger { display: flex; align-items: center; justify-content: center; }
      .nav-brand { margin-right: 0; }

      .page-wrapper { padding: 16px 12px 70px; }
      .page-title { font-size: 20px; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
      .stat-card-num { font-size: 22px; }
      .stat-card { padding: 14px; }
      .stat-card-icon { width: 38px; height: 38px; font-size: 17px; margin-bottom: 10px; }
      .grid-2 { grid-template-columns: 1fr; }
      .grid-3 { grid-template-columns: 1fr; }
      .quick-actions { grid-template-columns: repeat(2, 1fr); gap: 10px; }

      /* pm-status-row: tetap horizontal, tapi lebih kompak */
      .pm-status-row { grid-template-columns: repeat(3, 1fr); gap: 8px; }
      .pm-status-card { padding: 12px 8px; gap: 8px; flex-direction: column; align-items: flex-start; }
      .pm-icon { width: 34px; height: 34px; font-size: 16px; border-radius: 8px; }
      .pm-num { font-size: 22px; }
      .pm-lbl { font-size: 10px; }

      /* Chart tahunan: tinggi lebih kecil, label dirotate */
      .chart-wrap { height: 200px; }
      .chart-wrap-sm { height: 160px; }

      /* Chart tahunan full width - beri height proporsional */
      .card .chart-wrap[style*="280px"] { height: 220px !important; }

      .card-body { padding: 14px; }
      .card-header-bar { padding: 11px 14px; }

      /* Year selector dan legend di chart header: wrap ke bawah */
      .card-header-bar > div:last-child {
        flex-wrap: wrap; gap: 6px;
      }
      .chart-legend-inline { font-size: 11px; }
      .year-select { font-size: 11px; padding: 4px 22px 4px 8px; }

      /* Alert banners */
      .alert-banner { padding: 10px 14px; font-size: 12px; }

      /* Activity & jumlah section */
      .activity-item { gap: 8px; }
      .activity-dot { width: 32px; height: 32px; font-size: 13px; }
    }

    @media (max-width: 480px) {
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
      .stat-card { padding: 12px; }
      .stat-card-icon { width: 34px; height: 34px; font-size: 15px; margin-bottom: 8px; }
      .stat-card-num { font-size: 20px; }

      /* pm-status-row tetap 3 kolom di hp kecil juga, tapi lebih ringkas */
      .pm-status-row { grid-template-columns: repeat(3, 1fr); gap: 6px; }
      .pm-status-card { padding: 10px 6px; gap: 6px; }
      .pm-icon { width: 28px; height: 28px; font-size: 13px; }
      .pm-num { font-size: 18px; }
      .pm-lbl { font-size: 9px; line-height: 1.3; }

      /* Chart height di hp kecil */
      .chart-wrap { height: 180px; }
      .card .chart-wrap[style*="280px"] { height: 200px !important; }

      /* Grid kondisi barang: donut + legend stack vertical */
      .card-body[style*="display:flex"] { flex-direction: column; align-items: center; }
    }

    /* ══ OVERVIEW HEADER ══ */
    .overview-header {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 16px;
    }
    .overview-tag {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 6px 14px 6px 10px;
      background: linear-gradient(135deg, var(--blue-deep) 0%, var(--blue-dark) 100%);
      border-radius: 10px;
      flex-shrink: 0;
      box-shadow: 0 2px 8px rgba(27,63,110,.18);
    }
    .overview-tag i {
      font-size: 13px;
      color: rgba(255,255,255,.85);
    }
    .overview-tag span {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 12.5px;
      font-weight: 700;
      color: white;
      letter-spacing: .3px;
      text-transform: uppercase;
    }
    .overview-line {
      flex: 1;
      height: 1.5px;
      background: linear-gradient(90deg, var(--border) 0%, transparent 100%);
      border-radius: 10px;
    }
    .overview-hint {
      font-size: 11.5px;
      color: var(--muted);
      font-weight: 500;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .overview-hint i {
      font-size: 12px;
      color: var(--blue);
    }

    /* ══ ANIMATE IN ══ */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .stats-grid .stat-card { animation: fadeUp .4s ease both; }
    .stats-grid .stat-card:nth-child(1) { animation-delay: .05s; }
    .stats-grid .stat-card:nth-child(2) { animation-delay: .10s; }
    .stats-grid .stat-card:nth-child(3) { animation-delay: .15s; }
    .stats-grid .stat-card:nth-child(4) { animation-delay: .20s; }
    .stats-grid .stat-card:nth-child(5) { animation-delay: .25s; }
    .stats-grid .stat-card:nth-child(6) { animation-delay: .30s; }
    .stats-grid .stat-card:nth-child(7) { animation-delay: .35s; }
    .stats-grid .stat-card:nth-child(8) { animation-delay: .40s; }
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

  <!-- Desktop -->
  <div class="nav-links">
    <a href="dashboard.php"    class="nav-link active">Dashboard</a>
    <a href="ruangan.php"      class="nav-link">Prasarana</a>
    <a href="barang.php"       class="nav-link">Sarana</a>
    <a href="pengguna.php"     class="nav-link">
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
  <a href="dashboard.php"      class="nav-link active">Dashboard</a>
  <a href="ruangan.php"        class="nav-link">Prasarana</a>
  <a href="barang.php"         class="nav-link">Sarana</a>
  <a href="pengguna.php"       class="nav-link">
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


<!-- ══ PAGE ══ -->
<div class="page-wrapper">

  <!-- ── HEADER ── -->
  <div class="page-header">
    <div class="page-header-left">
      <div class="page-greeting">Selamat datang kembali</div>
      <div class="page-title"><?= htmlspecialchars($nama_admin) ?></div>
      <div class="page-date"><i class="bi bi-calendar3" style="font-size:11px;"></i> <?= $today ?></div>
    </div>
  </div>

  <!-- ── ALERT BANNERS ── -->
  <?php if ($pm_terlambat > 0): ?>
  <div class="alert-banner alert-danger">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
      <strong><?= $pm_terlambat ?> peminjaman terlambat dikembalikan!</strong>
      Segera <a href="pengembalian.php">cek halaman pengembalian</a> dan hubungi peminjam.
    </div>
  </div>
  <?php endif; ?>

  <?php if ($pm_menunggu > 0): ?>
  <div class="alert-banner alert-warning">
    <i class="bi bi-hourglass-split"></i>
    <div>
      <strong><?= $pm_menunggu ?> peminjaman menunggu persetujuan.</strong>
      <a href="peminjaman.php?status=menunggu">Lihat dan setujui</a> sekarang.
    </div>
  </div>
  <?php endif; ?>

  <?php if ($pending_count > 0): ?>
  <div class="alert-banner alert-warning">
    <i class="bi bi-person-fill-exclamation"></i>
    <div>
      <strong><?= $pending_count ?> akun pengguna menunggu persetujuan.</strong>
      <a href="pengguna.php">Kelola pengguna</a> untuk menyetujui atau menolak.
    </div>
  </div>
  <?php endif; ?>

  <!-- ── OVERVIEW SECTION ── -->
  <div class="overview-header">
    <div class="overview-tag">
      <i class="bi bi-grid-1x2-fill"></i>
      <span>Overview</span>
    </div>
    <div class="overview-line"></div>
    <div class="overview-hint">
      <i class="bi bi-info-circle"></i>
      Ringkasan data inventaris
    </div>
  </div>

  <!-- ── STAT CARDS ── -->
  <div class="stats-grid">

    <!-- Barang -->
    <a href="barang.php" class="stat-card" style="text-decoration:none;">
      <div class="stat-card-accent" style="background:linear-gradient(90deg,#4A90C4,#2B6FA8);"></div>
      <div class="stat-card-icon" style="background:#EFF6FF;"><i class="bi bi-box-seam" style="color:#2563EB;"></i></div>
      <div class="stat-card-num"><?= number_format($total_barang) ?></div>
      <div class="stat-card-label">Total Jenis Barang Terdaftar</div>
      <div class="stat-card-change" style="background:#EFF6FF;color:#2563EB;">
        <i class="bi bi-layers" style="font-size:10px;"></i> <?= number_format($jumlah) ?> unit
      </div>
    </a>

    <!-- Ruangan -->
    <a href="ruangan.php" class="stat-card" style="text-decoration:none;">
      <div class="stat-card-accent" style="background:linear-gradient(90deg,#3D9B4A,#6DC05A);"></div>
      <div class="stat-card-icon" style="background:#F0FDF4;"><i class="bi bi-building" style="color:#16A34A;"></i></div>
      <div class="stat-card-num"><?= number_format($total_ruangan) ?></div>
      <div class="stat-card-label">Total Ruangan Terdaftar</div>
    </a>

    <!-- Pengguna -->
    <a href="pengguna.php" class="stat-card" style="text-decoration:none;">
      <div class="stat-card-accent" style="background:linear-gradient(90deg,#F5C518,#F07C1B);"></div>
      <div class="stat-card-icon" style="background:#FFF8E1;"><i class="bi bi-people" style="color:#D97706;"></i></div>
      <div class="stat-card-num"><?= number_format($total_pengguna) ?></div>
      <div class="stat-card-label">Total Pengguna Aktif</div>
      <?php if ($pending_count > 0): ?>
      <div class="stat-card-change" style="background:#FEF2F2;color:#DC2626;">
        <i class="bi bi-clock" style="font-size:10px;"></i> <?= $pending_count ?> pending
      </div>
      <?php else: ?>
      <div class="stat-card-change" style="background:#F0FDF4;color:#16A34A;">
        <i class="bi bi-check-circle" style="font-size:10px;"></i> Semua aktif
      </div>
      <?php endif; ?>
    </a>

    <!-- Dipinjam -->
    <a href="peminjaman.php?status=dipinjam" class="stat-card" style="text-decoration:none;">
      <div class="stat-card-accent" style="background:linear-gradient(90deg,#E03B3B,#F07C1B);"></div>
      <div class="stat-card-icon" style="background:#FEF2F2;"><i class="bi bi-box-arrow-up-right" style="color:#DC2626;"></i></div>
      <div class="stat-card-num"><?= number_format($pm_dipinjam) ?></div>
      <div class="stat-card-label">Barang Sedang Dipinjam</div>
      <?php if ($pm_terlambat > 0): ?>
      <div class="stat-card-change" style="background:#FEF2F2;color:#DC2626;">
        <i class="bi bi-exclamation-triangle" style="font-size:10px;"></i> <?= $pm_terlambat ?> Peminjaman Terlambat Dikembalikan
      </div>
      <?php else: ?>
      <div class="stat-card-change" style="background:#F0FDF4;color:#16A34A;">
        <i class="bi bi-check-circle" style="font-size:10px;"></i> Peminjaman Tidak Terlambat Dikembalikan
      </div>
      <?php endif; ?>
    </a>

  </div>

  <!-- ── PEMINJAMAN SECTION LABEL ── -->
  <div class="overview-header">
    <div class="overview-tag" style="background:linear-gradient(135deg,#D97706 0%,#F07C1B 100%);">
      <i class="bi bi-arrow-left-right"></i>
      <span>Peminjaman</span>
    </div>
    <div class="overview-line"></div>
    <div class="overview-hint">
      <i class="bi bi-info-circle"></i>
      Status peminjaman barang
    </div>
  </div>

  <!-- ── PEMINJAMAN STATUS ROW ── -->
  <div class="pm-status-row">
    <div class="pm-status-card menunggu">
      <div class="pm-icon" style="background:#FEF3C7;"><i class="bi bi-hourglass-split" style="color:#D97706;font-size:20px;"></i></div>
      <div>
        <div class="pm-num" style="color:#D97706;"><?= $pm_menunggu ?></div>
        <div class="pm-lbl">Peminjaman Menunggu Persetujuan</div>
      </div>
    </div>
    <div class="pm-status-card dipinjam">
      <div class="pm-icon" style="background:#DBEAFE;"><i class="bi bi-clock" style="color:#2563EB;font-size:20px;"></i></div>
      <div>
        <div class="pm-num" style="color:#2563EB;"><?= $pm_dipinjam ?></div>
        <div class="pm-lbl">Peminjaman Sedang Dipinjam</div>
      </div>
    </div>
    <div class="pm-status-card kembali">
      <div class="pm-icon" style="background:#DCFCE7;"><i class="bi bi-arrow-return-left" style="color:#16A34A;font-size:20px;"></i></div>
      <div>
        <div class="pm-num" style="color:#16A34A;"><?= $pm_dikembalikan ?></div>
        <div class="pm-lbl">Peminjaman Sudah Dikembalikan</div>
      </div>
    </div>
  </div>

  <!-- ── CHART TAHUNAN FULL WIDTH ── -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header-bar" style="flex-wrap:wrap;gap:8px;">
      <div class="card-header-title"><i class="bi bi-bar-chart"></i> Peminjaman dan Pengembalian Tahunan</div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <div class="chart-legend-inline">
          <span class="chart-legend-dot" style="background:#2B6FA8;"></span><span>Peminjaman</span>
          <span class="chart-legend-dot" style="background:#3D9B4A;margin-left:10px;"></span><span>Pengembalian</span>
        </div>
        <select id="tahunSelector" class="year-select" onchange="updateChartBulanan(this.value)">
          <?php
            $tahun_awal = 2024;
            $tahun_akhir = (int)date('Y');
            for ($y = $tahun_akhir; $y >= $tahun_awal; $y--): ?>
            <option value="<?= $y ?>" <?= $y == $tahun_sekarang ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
    <div class="card-body">
      <div id="chartBulananLoading" style="display:none;text-align:center;padding:40px 0;color:var(--muted);font-size:13px;">
        <i class="bi bi-arrow-clockwise" style="animation:spin .8s linear infinite;display:inline-block;"></i> Memuat data...
      </div>
      <div class="chart-wrap" style="height:280px;position:relative;">
        <canvas id="chartBulanan"></canvas>
      </div>
    </div>
  </div>

  <!-- ── GRID: KONDISI + TOP RUANGAN ── -->
  <div class="grid-2">

    <!-- Kondisi Barang: Laik vs Tidak Laik -->
    <div class="card">
      <div class="card-header-bar">
        <div class="card-header-title"><i class="bi bi-pie-chart"></i> Kondisi Barang</div>
        <a href="barang.php" style="font-size:12px;color:var(--blue-dark);text-decoration:none;font-weight:700;">Lihat semua →</a>
      </div>
      <div class="card-body" style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
        <!-- Donut chart dengan angka total di tengah -->
        <div style="width:140px;height:140px;flex-shrink:0;position:relative;">
          <canvas id="chartKondisi"></canvas>
          <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
            <span style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:900;color:var(--text);line-height:1;"><?= number_format($total_laik + $total_tidak_laik) ?></span>
            <span style="font-size:10px;color:var(--muted);margin-top:2px;">unit</span>
          </div>
        </div>
        <!-- Legend + progress bar -->
        <div class="kondisi-legend" style="flex:1;min-width:140px;">
          <div class="legend-item">
            <span class="legend-dot" style="background:#22C55E;"></span>
            <span class="legend-label">Laik Pakai</span>
            <span class="legend-val" style="color:#16A34A;"><?= number_format($total_laik) ?></span>
          </div>
          <div class="legend-item">
            <span class="legend-dot" style="background:#EF4444;"></span>
            <span class="legend-label">Tidak Laik</span>
            <span class="legend-val" style="color:#DC2626;"><?= number_format($total_tidak_laik) ?></span>
          </div>
          <div class="legend-item" style="border-top:1px solid var(--border);padding-top:8px;margin-top:4px;">
            <span class="legend-dot" style="background:var(--text);"></span>
            <span class="legend-label" style="font-weight:700;">Total Unit</span>
            <span class="legend-val"><?= number_format($total_laik + $total_tidak_laik) ?></span>
          </div>
          <?php if (($total_laik + $total_tidak_laik) > 0):
            $pct_laik = round($total_laik / ($total_laik + $total_tidak_laik) * 100);
          ?>
          <div style="margin-top:12px;">
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--muted);margin-bottom:4px;">
              <span style="color:#16A34A;font-weight:700;">Laik <?= $pct_laik ?>%</span>
              <span style="color:#DC2626;font-weight:700;">Tdk Laik <?= 100-$pct_laik ?>%</span>
            </div>
            <div style="height:8px;background:#FEE2E2;border-radius:10px;overflow:hidden;">
              <div style="height:100%;width:<?= $pct_laik ?>%;background:linear-gradient(90deg,#22C55E,#16A34A);border-radius:10px;transition:width .8s cubic-bezier(.4,0,.2,1);"></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Barang Per Ruangan -->
    <div class="card">
      <div class="card-header-bar">
        <div class="card-header-title"><i class="bi bi-building"></i> Jumlah Barang Tiap Ruangan</div>
        <a href="barang.php" style="font-size:12px;color:var(--blue-dark);text-decoration:none;font-weight:700;">Lihat semua →</a>
      </div>
      <div class="card-body">
        <?php if (empty($top_ruangan)): ?>
        <div class="empty-mini"><i class="bi bi-building"></i>Belum ada data ruangan</div>
        <?php else: foreach ($top_ruangan as $tr): ?>
        <div class="room-bar-item">
          <div class="room-bar-header">
            <span class="room-bar-name"><?= htmlspecialchars($tr['nama_ruangan']) ?></span>
            <span class="room-bar-count"><?= $tr['jml'] ?> barang</span>
          </div>
          <div class="room-bar-track">
            <div class="room-bar-fill"
                 style="width:<?= $max_jml > 0 ? round($tr['jml']/$max_jml*100) : 0 ?>%;">
            </div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div>

  <!-- ── GRID: AKTIVITAS + jumlah RENDAH + TOP PEMINJAM ── -->
  <div class="grid-2">

    <!-- Aktivitas Terbaru -->
    <div class="card">
      <div class="card-header-bar">
        <div class="card-header-title"><i class="bi bi-activity"></i> Aktivitas Peminjaman Terbaru</div>
        <a href="peminjaman.php" style="font-size:12px;color:var(--blue-dark);text-decoration:none;font-weight:700;">Lihat semua →</a>
      </div>
      <div class="card-body">
        <?php if (empty($aktivitas_list)): ?>
        <div class="empty-mini"><i class="bi bi-inbox"></i>Belum ada aktivitas peminjaman</div>
        <?php else:
          $status_icon = ['menunggu'=>['bi-hourglass-split','#FFF8E1','#D97706'],'dipinjam'=>['bi-box-arrow-up-right','#EFF6FF','#2563EB'],'dikembalikan'=>['bi-arrow-return-left','#F0FDF4','#16A34A']];
          foreach ($aktivitas_list as $ak):
            $si = $status_icon[$ak['status']] ?? ['bi-circle','#F1F5F9','#64748B'];
        ?>
        <div class="activity-item">
          <div class="activity-dot" style="background:<?= $si[1] ?>;color:<?= $si[2] ?>;">
            <i class="bi <?= $si[0] ?>"></i>
          </div>
          <div class="activity-content">
            <div class="activity-title"><?= htmlspecialchars($ak['nama_peminjam']) ?> — <?= htmlspecialchars(mb_strimwidth($ak['nama_barang_list']??'—',0,40,'...')) ?></div>
            <div class="activity-sub">
              <?= ucfirst($ak['status']) ?>
              <?php if ($ak['kelas']): ?> &bull; <?= htmlspecialchars($ak['kelas']) ?><?php endif; ?>
              &bull; <?= $ak['tanggal_pinjam'] ? date('d M Y', strtotime($ak['tanggal_pinjam'])) : '—' ?>
            </div>
          </div>
          <span class="badge <?= $ak['status']==='menunggu'?'badge-warning':($ak['status']==='dipinjam'?'badge-info':'badge-success') ?>">
            <?= ucfirst($ak['status']) ?>
          </span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- KOLOM KANAN: jumlah Rendah + Top Peminjam -->
    <div style="display:flex;flex-direction:column;gap:20px;">

      <!-- jumlah Rendah -->
      <div class="card">
        <div class="card-header-bar">
          <div class="card-header-title" style="color:#DC2626;"><i class="bi bi-exclamation-triangle" style="color:#DC2626;"></i> Barang Hampir Habis</div>
          <a href="barang.php" style="font-size:12px;color:var(--blue-dark);text-decoration:none;font-weight:700;">Detail →</a>
        </div>
        <div class="card-body">
          <?php if (empty($jumlah_rendah)): ?>
          <div class="empty-mini"><i class="bi bi-check-circle" style="color:#16A34A;"></i>Semua Barang aman</div>
          <?php else: foreach ($jumlah_rendah as $sv): ?>
          <div class="jumlah-item">
            <div class="jumlah-num jumlah-<?= min($sv['jumlah'], 3) ?>"><?= $sv['jumlah'] ?></div>
            <div class="jumlah-info">
              <div class="jumlah-name"><?= htmlspecialchars($sv['nama_barang']) ?></div>
              <div class="jumlah-room"><i class="bi bi-building" style="font-size:10px;"></i> <?= htmlspecialchars($sv['nama_ruangan'] ?? '—') ?></div>
            </div>
            <?php if ($sv['jumlah'] === 0): ?>
              <span class="badge badge-danger">Habis</span>
            <?php else: ?>
              <span class="badge badge-warning">Sisa <?= $sv['jumlah'] ?></span>
            <?php endif; ?>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── TERLAMBAT SECTION ── -->
  <?php if (!empty($terlambat_list)): ?>
  <div class="card" style="border-color:#FECACA;margin-bottom:20px;">
    <div class="card-header-bar" style="background:#FEF2F2;border-bottom-color:#FECACA;">
      <div class="card-header-title" style="color:#DC2626;">
        <i class="bi bi-alarm" style="color:#DC2626;"></i> Pengembalian Terlambat
      </div>
      <a href="pengembalian.php" style="font-size:12px;color:#DC2626;text-decoration:none;font-weight:700;">Catat Kembali →</a>
    </div>
    <div class="card-body">
      <?php foreach ($terlambat_list as $tl):
        $jam = (int)$tl['jam_terlambat'];
        $hari = floor($jam / 24);
        $sisa_jam = $jam % 24;
        $dur_txt = $hari > 0 ? "$hari hari $sisa_jam jam" : "$jam jam";
      ?>
      <div class="terlambat-item">
        <div class="terlambat-icon"><i class="bi bi-alarm-fill"></i></div>
        <div class="terlambat-info">
          <div class="terlambat-name"><?= htmlspecialchars($tl['nama_peminjam']) ?><?php if ($tl['kelas']): ?> — <strong><?= htmlspecialchars($tl['kelas']) ?></strong><?php endif; ?></div>
          <div class="terlambat-barang"><?= htmlspecialchars($tl['nama_barang_list']??'') ?></div>
        </div>
        <div class="terlambat-dur">+<?= $dur_txt ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /page-wrapper -->

<footer>
  &copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak
</footer>


<script>
  /* ══ HAMBURGER ══ */
  function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('hamburgerIcon');
    const open = menu.classList.toggle('open');
    icon.className = open ? 'bi bi-x-lg' : 'bi bi-list';
    document.body.style.overflow = open ? 'hidden' : '';
  }
  document.addEventListener('click', e => {
    const menu = document.getElementById('mobileMenu');
    const btn  = document.getElementById('hamburgerBtn');
    if (menu.classList.contains('open') && !menu.contains(e.target) && !btn.contains(e.target)) {
      menu.classList.remove('open');
      document.getElementById('hamburgerIcon').className = 'bi bi-list';
      document.body.style.overflow = '';
    }
  });

  /* ══ CHART DEFAULTS ══ */
  Chart.defaults.font.family = "'DM Sans', sans-serif";
  Chart.defaults.color = '#6B7C93';
  Chart.defaults.plugins.legend.display = false;

  /* ── Chart Harian (7 hari) ── */
  new Chart(document.getElementById('chartHarian'), {
    type: 'line',
    data: {
      labels: <?= json_encode($chart_labels) ?>,
      datasets: [
        {
          label: 'Peminjaman',
          data: <?= json_encode($chart_pinjam) ?>,
          borderColor: '#2B6FA8',
          backgroundColor: 'rgba(43,111,168,.12)',
          fill: true,
          tension: .4,
          pointBackgroundColor: '#2B6FA8',
          pointRadius: 4,
          pointHoverRadius: 6,
          borderWidth: 2.5,
        },
        {
          label: 'Pengembalian',
          data: <?= json_encode($chart_kembali) ?>,
          borderColor: '#3D9B4A',
          backgroundColor: 'rgba(61,155,74,.08)',
          fill: true,
          tension: .4,
          pointBackgroundColor: '#3D9B4A',
          pointRadius: 4,
          pointHoverRadius: 6,
          borderWidth: 2.5,
          borderDash: [5, 3],
        },
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true, position: 'top',
          labels: { boxWidth: 12, padding: 16, font: { size: 12, weight: '600' } }
        },
        tooltip: {
          backgroundColor: '#1B2D45',
          padding: 10, cornerRadius: 10,
          titleFont: { size: 12, weight: '700' },
          bodyFont: { size: 11 },
        }
      },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: {
          beginAtZero: true,
          ticks: { stepSize: 1, font: { size: 11 } },
          grid: { color: 'rgba(208,228,245,.5)' }
        }
      }
    }
  });

  /* ── Chart Bulanan (per tahun, peminjaman + pengembalian) ── */
  const bulananInitLabels  = <?= json_encode($chart_bulanan_labels) ?>;
  const bulananInitPinjam  = <?= json_encode($chart_bulanan_pinjam) ?>;
  const bulananInitKembali = <?= json_encode($chart_bulanan_kembali) ?>;

  const chartBulanan = new Chart(document.getElementById('chartBulanan'), {
    type: 'bar',
    data: {
      labels: bulananInitLabels,
      datasets: [
        {
          label: 'Peminjaman',
          data: bulananInitPinjam,
          backgroundColor: (ctx) => {
            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 220);
            g.addColorStop(0, 'rgba(43,111,168,.85)');
            g.addColorStop(1, 'rgba(74,144,196,.35)');
            return g;
          },
          borderRadius: 6,
          borderSkipped: false,
          hoverBackgroundColor: '#2B6FA8',
          borderWidth: 0,
        },
        {
          label: 'Pengembalian',
          data: bulananInitKembali,
          backgroundColor: (ctx) => {
            const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 220);
            g.addColorStop(0, 'rgba(61,155,74,.8)');
            g.addColorStop(1, 'rgba(109,192,90,.3)');
            return g;
          },
          borderRadius: 6,
          borderSkipped: false,
          hoverBackgroundColor: '#3D9B4A',
          borderWidth: 0,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          backgroundColor: '#1B2D45',
          padding: 12, cornerRadius: 10,
          titleFont: { size: 12, weight: '700' },
          bodyFont: { size: 11 },
          callbacks: {
            title: (items) => items[0].label,
            label: (ctx) => ` ${ctx.dataset.label}: ${ctx.raw}`
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            font: { size: 10 },
            maxRotation: 45,
            minRotation: 0,
            callback: function(val, idx) {
              // Di layar kecil, tampilkan label lebih pendek
              const label = this.getLabelForValue(val);
              if (window.innerWidth < 480) {
                // Tampilkan setiap 2 bulan
                return idx % 2 === 0 ? label.split(' ')[0] : '';
              }
              if (window.innerWidth < 768) {
                return label.split(' ')[0]; // Hanya nama bulan, tanpa tahun
              }
              return label;
            }
          }
        },
        y: {
          beginAtZero: true,
          ticks: { stepSize: 1, font: { size: 11 } },
          grid: { color: 'rgba(208,228,245,.5)' }
        }
      }
    }
  });

  function updateChartBulanan(tahun) {
    const loading = document.getElementById('chartBulananLoading');
    const wrap = document.querySelector('.chart-wrap[style*="280px"], .chart-wrap[style*="position:relative"]');
    loading.style.display = 'block';
    if (wrap) wrap.style.opacity = '.4';

    fetch(`dashboard.php?ajax=chart_tahunan&tahun=${tahun}`)
      .then(r => r.json())
      .then(data => {
        chartBulanan.data.labels = data.labels;
        chartBulanan.data.datasets[0].data = data.pinjam;
        chartBulanan.data.datasets[1].data = data.kembali;
        chartBulanan.update('active');
      })
      .catch(() => {})
      .finally(() => {
        loading.style.display = 'none';
        if (wrap) wrap.style.opacity = '1';
      });
  }

  // Re-render chart on resize untuk label mobile
  let resizeTimer;
  window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
      chartBulanan.update();
    }, 200);
  });
  new Chart(document.getElementById('chartKondisi'), {
    type: 'doughnut',
    data: {
      labels: ['Laik Pakai', 'Tidak Laik'],
      datasets: [{
        data: [<?= $total_laik ?>, <?= $total_tidak_laik ?>],
        backgroundColor: ['#22C55E', '#EF4444'],
        hoverBackgroundColor: ['#16A34A', '#DC2626'],
        borderWidth: 3,
        borderColor: '#ffffff',
        hoverOffset: 8,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '70%',
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1B2D45',
          padding: 10, cornerRadius: 8,
          callbacks: {
            label: ctx => {
              const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
              const pct   = total > 0 ? Math.round(ctx.raw / total * 100) : 0;
              return ` ${ctx.label}: ${ctx.raw.toLocaleString()} unit (${pct}%)`;
            }
          }
        }
      }
    }
  });

  /* ── BFCache Fix: paksa reload jika halaman dari back-forward cache ── */
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
  });
</script>

</body>
</html>