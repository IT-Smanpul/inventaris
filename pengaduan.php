<?php
session_start();
require_once "config/koneksi.php";

/* ══════════════════════════════════════════
   1. AUTO-SETUP TABEL & DIREKTORI UPLOAD
══════════════════════════════════════════ */
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `pengaduan` (
  `id_pengaduan` int NOT NULL AUTO_INCREMENT,
  `nama_prasarana` varchar(150) NOT NULL,
  `id_ruangan` int DEFAULT NULL,
  `nama_pelapor` varchar(100) DEFAULT NULL,
  `kontak_pelapor` varchar(50) DEFAULT NULL,
  `deskripsi` text NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `status` enum('menunggu','diproses','selesai','ditolak') NOT NULL DEFAULT 'menunggu',
  `tanggapan` text DEFAULT NULL,
  `tanggal_pengaduan` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pengaduan`),
  KEY `id_ruangan` (`id_ruangan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$upload_dir = __DIR__ . '/assets/pengaduan/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

/* ══════════════════════════════════════════
   2. DATA PRASARANA (RUANGAN) UNTUK FORM
══════════════════════════════════════════ */
$ruangan_list = [];
$rq = mysqli_query($conn, "SELECT id_ruangan, nama_ruangan, keterangan AS lokasi FROM ruangan ORDER BY nama_ruangan ASC");
if ($rq) {
    while ($r = mysqli_fetch_assoc($rq)) {
        $ruangan_list[] = $r;
    }
}

/* ══════════════════════════════════════════
   3. HANDLER SUBMIT PENGADUAN
══════════════════════════════════════════ */
$msg_success = '';
$msg_error   = '';
$new_tiket   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['kirim_pengaduan']) || isset($_POST['action_pengaduan']))) {
    $pilihan_prasarana = trim($_POST['pilihan_prasarana'] ?? '');
    $nama_prasarana_custom = trim($_POST['nama_prasarana_custom'] ?? '');
    $nama_pelapor     = trim($_POST['nama_pelapor'] ?? '');
    $is_anonim        = isset($_POST['is_anonim']) ? 1 : 0;
    $kontak_pelapor   = trim($_POST['kontak_pelapor'] ?? '');
    $deskripsi        = trim($_POST['deskripsi'] ?? '');

    // Resolusi nama prasarana dan id_ruangan
    $id_ruangan = 'NULL';
    $nama_prasarana = '';

    if ($pilihan_prasarana === 'lainnya') {
        $nama_prasarana = $nama_prasarana_custom;
    } elseif (is_numeric($pilihan_prasarana) && (int)$pilihan_prasarana > 0) {
        $id_r = (int)$pilihan_prasarana;
        $q_cek = mysqli_query($conn, "SELECT nama_ruangan FROM ruangan WHERE id_ruangan = $id_r LIMIT 1");
        if ($r_cek = mysqli_fetch_assoc($q_cek)) {
            $nama_prasarana = $r_cek['nama_ruangan'];
            $id_ruangan = $id_r;
        }
    }

    // Nama pelapor
    if ($is_anonim || empty($nama_pelapor)) {
        $nama_pelapor = 'Anonim';
    }

    // Validasi
    if (empty($nama_prasarana)) {
        $msg_error = 'Nama prasarana wajib dipilih atau diisi.';
    } elseif (empty($deskripsi)) {
        $msg_error = 'Deskripsi keluhan wajib diisi.';
    } else {
        // Upload foto (opsional / direkomendasikan)
        $nama_foto = null;
        if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['foto'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed_ext)) {
                $msg_error = 'Format foto tidak valid. Gunakan format JPG, PNG, atau WEBP.';
            } elseif ($f['size'] > 5 * 1024 * 1024) {
                $msg_error = 'Ukuran foto maksimal 5 MB.';
            } else {
                $nama_foto = 'aduan_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
                if (!move_uploaded_file($f['tmp_name'], $upload_dir . $nama_foto)) {
                    $nama_foto = null;
                    $msg_error = 'Gagal mengunggah foto. Silakan coba lagi.';
                }
            }
        }

        if (empty($msg_error)) {
            $esc_prasarana = mysqli_real_escape_string($conn, $nama_prasarana);
            $esc_pelapor   = mysqli_real_escape_string($conn, $nama_pelapor);
            $esc_kontak    = mysqli_real_escape_string($conn, $kontak_pelapor);
            $esc_deskripsi = mysqli_real_escape_string($conn, $deskripsi);
            $esc_foto      = $nama_foto ? "'" . mysqli_real_escape_string($conn, $nama_foto) . "'" : 'NULL';

            $insert_sql = "INSERT INTO pengaduan 
                (nama_prasarana, id_ruangan, nama_pelapor, kontak_pelapor, deskripsi, foto, status, tanggal_pengaduan)
                VALUES 
                ('$esc_prasarana', $id_ruangan, '$esc_pelapor', '$esc_kontak', '$esc_deskripsi', $esc_foto, 'menunggu', NOW())";

            if (mysqli_query($conn, $insert_sql)) {
                $new_id = mysqli_insert_id($conn);
                $tiket = 'PND-' . str_pad($new_id, 4, '0', STR_PAD_LEFT);
                header("Location: pengaduan.php?tab=tracking&success=1&tiket=" . urlencode($tiket) . "&prasarana=" . urlencode($nama_prasarana) . "#tiket-" . urlencode($tiket));
                exit;
            } else {
                $msg_error = 'Gagal menyimpan pengaduan: ' . mysqli_error($conn);
            }
        }
    }
}

if (isset($_GET['success']) && isset($_GET['tiket'])) {
    $new_tiket = htmlspecialchars($_GET['tiket']);
    $sukses_prasarana = htmlspecialchars($_GET['prasarana'] ?? '');
    $msg_success = "Pengaduan untuk prasarana <strong>\"$sukses_prasarana\"</strong> berhasil dikirim dengan Nomor Tiket <strong>$new_tiket</strong>. Tim SARPRAS akan segera menindaklanjutinya.";
}

/* ══════════════════════════════════════════
   4. STATISTIK PENGADUAN PUBLIK
══════════════════════════════════════════ */
$stat_total    = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan"))['t'];
$stat_menunggu = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan WHERE status='menunggu'"))['t'];
$stat_proses   = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan WHERE status='diproses'"))['t'];
$stat_selesai  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan WHERE status='selesai'"))['t'];

/* ══════════════════════════════════════════
   5. DAFTAR PENGADUAN PUBLIK (TRACKING)
══════════════════════════════════════════ */
$tab = $_GET['tab'] ?? 'form';
$filter_status = $_GET['status'] ?? '';
$cari_aduan    = trim($_GET['q'] ?? '');

$where_aduan = "WHERE 1=1";
if (in_array($filter_status, ['menunggu', 'diproses', 'selesai', 'ditolak'])) {
    $where_aduan .= " AND p.status = '$filter_status'";
}
if (!empty($cari_aduan)) {
    $esc_q = mysqli_real_escape_string($conn, $cari_aduan);
    $where_aduan .= " AND (p.nama_prasarana LIKE '%$esc_q%' OR p.deskripsi LIKE '%$esc_q%' OR p.id_pengaduan LIKE '%$esc_q%')";
}

$pengaduan_list = [];
$aduan_q = mysqli_query($conn, "
    SELECT p.*, r.keterangan AS lokasi 
    FROM pengaduan p
    LEFT JOIN ruangan r ON p.id_ruangan = r.id_ruangan
    $where_aduan
    ORDER BY p.tanggal_pengaduan DESC
    LIMIT 30
");
if ($aduan_q) {
    while ($row = mysqli_fetch_assoc($aduan_q)) {
        $pengaduan_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Layanan Pengaduan Sarpras — SMAN 10 Pontianak</title>
  <link rel="icon" href="assets/logo.png">
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
      --shadow:      0 4px 20px rgba(27,63,110,.08);
      --shadow-lg:   0 12px 32px rgba(27,63,110,.14);
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ══════════ NAVBAR ══════════ */
    .navbar {
      position: sticky; top: 0; z-index: 100;
      background: var(--blue-deep);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 32px; height: 68px;
      box-shadow: 0 2px 14px rgba(27,63,110,.25);
    }
    .nav-brand {
      display: flex; align-items: center; gap: 12px;
      text-decoration: none;
    }
    .nav-brand img { width: 40px; height: 40px; object-fit: contain; }
    .nav-brand-text strong {
      display: block; font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px; font-weight: 800; color: #FFFFFF; line-height: 1.2;
    }
    .nav-brand-text span {
      font-size: 11px; color: rgba(255,255,255,.65);
    }
    .nav-actions {
      display: flex; align-items: center; gap: 12px;
    }
    .btn-nav-back {
      color: rgba(255,255,255,.85);
      text-decoration: none; font-size: 13px; font-weight: 600;
      padding: 8px 14px; border-radius: 8px;
      display: inline-flex; align-items: center; gap: 6px;
      transition: all .2s;
    }
    .btn-nav-back:hover {
      background: rgba(255,255,255,.1);
      color: #FFFFFF;
    }
    .btn-nav-login {
      background: var(--blue); color: #FFFFFF;
      text-decoration: none; font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 13px; font-weight: 700;
      padding: 8px 18px; border-radius: 8px;
      display: inline-flex; align-items: center; gap: 6px;
      transition: all .2s;
      box-shadow: 0 2px 10px rgba(74,144,196,.3);
    }
    .btn-nav-login:hover {
      background: #397fae;
      transform: translateY(-1px);
    }

    /* ══════════ PAGE WRAPPER ══════════ */
    .page-container {
      max-width: 980px;
      width: 100%;
      margin: 0 auto;
      padding: 32px 20px 60px;
      flex: 1;
    }

    /* ── HEADER BANNER ── */
    .hero-banner {
      background: linear-gradient(135deg, var(--blue-deep) 0%, var(--blue-dark) 100%);
      border-radius: 20px;
      padding: 36px 36px 32px;
      color: #FFFFFF;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden;
      box-shadow: var(--shadow-lg);
    }
    .hero-banner::after {
      content: '';
      position: absolute; right: -40px; top: -40px;
      width: 240px; height: 240px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(255,255,255,.12) 0%, transparent 70%);
      pointer-events: none;
    }
    .hero-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,.15);
      backdrop-filter: blur(8px);
      padding: 5px 12px; border-radius: 20px;
      font-size: 11px; font-weight: 700;
      letter-spacing: .5px; text-transform: uppercase;
      margin-bottom: 12px;
      color: #FEE685;
    }
    .hero-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 26px; font-weight: 900;
      line-height: 1.25; margin-bottom: 8px;
    }
    .hero-desc {
      font-size: 14px; color: rgba(255,255,255,.8);
      max-width: 640px; line-height: 1.55;
    }

    /* ── STATS ROW ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-bottom: 24px;
    }
    .stat-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px 18px;
      box-shadow: var(--shadow);
      display: flex; align-items: center; gap: 12px;
    }
    .stat-icon {
      width: 40px; height: 40px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; flex-shrink: 0;
    }
    .stat-val {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 20px; font-weight: 800; color: var(--text);
      line-height: 1.1;
    }
    .stat-lbl {
      font-size: 11px; color: var(--muted); margin-top: 2px;
    }

    /* ── TABS NAV ── */
    .tabs-nav {
      display: flex;
      background: #E2EEFA;
      padding: 6px; border-radius: 14px;
      gap: 6px; margin-bottom: 24px;
      border: 1px solid var(--border);
    }
    .tab-btn {
      flex: 1;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 11px 16px; border-radius: 10px;
      border: none; background: transparent;
      color: var(--muted);
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 13px; font-weight: 700;
      cursor: pointer; transition: all .2s;
      text-decoration: none;
    }
    .tab-btn:hover {
      color: var(--text); background: rgba(255,255,255,.6);
    }
    .tab-btn.active {
      background: #FFFFFF;
      color: var(--blue-deep);
      box-shadow: 0 2px 10px rgba(27,63,110,.12);
    }

    /* ── FORM CARD ── */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      box-shadow: var(--shadow);
      overflow: hidden;
      margin-bottom: 24px;
    }
    .card-header {
      padding: 18px 24px;
      border-bottom: 1px solid var(--border);
      background: #FAFDFE;
      display: flex; align-items: center; justify-content: space-between;
    }
    .card-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 16px; font-weight: 800; color: var(--text);
      display: flex; align-items: center; gap: 8px;
    }
    .card-title i { color: var(--blue); font-size: 18px; }
    .card-body { padding: 26px 24px; }

    /* ── FORM ELEMENTS ── */
    .form-group {
      margin-bottom: 20px;
    }
    .form-label {
      display: flex; align-items: center; justify-content: space-between;
      font-size: 12px; font-weight: 700;
      color: var(--text); text-transform: uppercase; letter-spacing: .5px;
      margin-bottom: 7px;
    }
    .form-label .req { color: var(--red); margin-left: 3px; }
    .form-label .hint {
      font-size: 11px; font-weight: 500; color: var(--muted);
      text-transform: none; letter-spacing: 0;
    }
    .form-control {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px; color: var(--text);
      background: #FFFFFF;
      outline: none;
      transition: all .2s;
    }
    .form-control:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 3.5px rgba(74,144,196,.18);
    }
    select.form-control {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7C93' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
      cursor: pointer;
    }

    /* Prasarana custom input slide */
    .custom-prasarana-wrap {
      display: none;
      margin-top: 10px;
      animation: slideDown .2s ease;
    }
    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-6px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ── UPLOAD DROPZONE ── */
    .dropzone {
      border: 2px dashed var(--border);
      border-radius: 12px;
      padding: 24px 16px;
      text-align: center;
      background: #FBFDFE;
      cursor: pointer;
      transition: all .2s;
      position: relative;
    }
    .dropzone:hover, .dropzone.dragover {
      border-color: var(--blue);
      background: #F0F7FF;
    }
    .dropzone-icon {
      width: 48px; height: 48px; border-radius: 50%;
      background: #EFF6FF; color: var(--blue-dark);
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 22px; margin-bottom: 10px;
    }
    .dropzone-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 13px; font-weight: 700; color: var(--text); margin-bottom: 4px;
    }
    .dropzone-sub {
      font-size: 11px; color: var(--muted);
    }
    .file-input-hidden {
      position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }

    /* Preview Foto */
    .preview-box {
      display: none;
      margin-top: 14px;
      background: #FFFFFF;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 10px 14px;
      align-items: center;
      gap: 12px;
    }
    .preview-img {
      width: 60px; height: 60px; border-radius: 8px;
      object-fit: cover; border: 1px solid var(--border);
      flex-shrink: 0;
    }
    .preview-info { flex: 1; min-width: 0; }
    .preview-name {
      font-size: 12px; font-weight: 700; color: var(--text);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .preview-size { font-size: 11px; color: var(--muted); }
    .btn-remove-file {
      background: #FEE2E2; color: var(--red);
      border: none; border-radius: 8px;
      width: 30px; height: 30px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: background .15s;
    }
    .btn-remove-file:hover { background: #FECACA; }

    /* Anonim Toggle */
    .anonim-box {
      background: #F8FAFD;
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 10px 14px;
      display: flex; align-items: center; justify-content: space-between;
      margin-top: 8px;
    }
    .anonim-label {
      font-size: 12px; font-weight: 600; color: var(--text);
      display: flex; align-items: center; gap: 8px;
      cursor: pointer;
    }

    /* Submit Button */
    .btn-submit {
      width: 100%;
      padding: 14px 20px;
      background: linear-gradient(135deg, var(--blue-dark) 0%, var(--blue-deep) 100%);
      color: #FFFFFF;
      border: none; border-radius: 12px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px; font-weight: 800;
      cursor: pointer; transition: all .2s;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      box-shadow: 0 4px 16px rgba(27,63,110,.25);
    }
    .btn-submit:hover {
      background: linear-gradient(135deg, #235d8e 0%, #153258 100%);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(27,63,110,.35);
    }

    /* ── ALERTS ── */
    .alert {
      padding: 14px 18px; border-radius: 12px;
      font-size: 13px; line-height: 1.5;
      display: flex; align-items: flex-start; gap: 10px;
      margin-bottom: 20px;
    }
    .alert-success {
      background: #F0FDF4; border: 1.5px solid #BBF7D0; color: #166534;
    }
    .alert-danger {
      background: #FEF2F2; border: 1.5px solid #FECACA; color: #991B1B;
    }
    .alert i { font-size: 18px; flex-shrink: 0; margin-top: 1px; }

    /* ── RIWAYAT LIST ITEM ── */
    .aduan-item {
      padding: 18px 24px;
      border-bottom: 1px solid var(--border);
      transition: background .15s;
    }
    .aduan-item:last-child { border-bottom: none; }
    .aduan-item:hover { background: #FAFDFE; }
    .aduan-item.is-new {
      background: #F0FDF4;
      border-left: 4px solid var(--green);
      animation: pulseHighlight 2s ease-in-out;
    }
    @keyframes pulseHighlight {
      0% { background: #DCFCE7; }
      100% { background: #F0FDF4; }
    }
    .spin { display: inline-block; animation: spin 1s linear infinite; }
    @keyframes spin { 100% { transform: rotate(360deg); } }
    .aduan-item-header {
      display: flex; align-items: flex-start; justify-content: space-between;
      gap: 12px; margin-bottom: 8px; flex-wrap: wrap;
    }
    .aduan-prasarana {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 15px; font-weight: 800; color: var(--text);
      display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .aduan-tiket {
      display: inline-block;
      font-size: 10px; font-weight: 800;
      background: #EFF6FF; color: #2563EB;
      padding: 2px 7px; border-radius: 5px;
    }
    .aduan-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 4px 10px; border-radius: 20px;
      font-size: 11px; font-weight: 700;
      white-space: nowrap;
    }
    .badge-menunggu {
      background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A;
    }
    .badge-diproses {
      background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE;
    }
    .badge-selesai {
      background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0;
    }
    .badge-ditolak {
      background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA;
    }
    .aduan-meta {
      font-size: 12px; color: var(--muted); margin-bottom: 10px;
      display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    }
    .aduan-meta span { display: inline-flex; align-items: center; gap: 5px; }
    .aduan-desc {
      font-size: 13.5px; color: #2D3748; line-height: 1.6;
      margin-bottom: 12px;
    }
    .aduan-foto-wrap {
      margin-top: 10px; margin-bottom: 10px;
    }
    .aduan-foto-thumb {
      width: 110px; height: 80px; object-fit: cover;
      border-radius: 8px; border: 1.5px solid var(--border);
      cursor: pointer; transition: transform .2s, box-shadow .2s;
    }
    .aduan-foto-thumb:hover {
      transform: scale(1.04); box-shadow: var(--shadow);
    }
    .tanggapan-box {
      margin-top: 12px;
      background: #F8FAFD;
      border-left: 3.5px solid var(--blue);
      border-radius: 0 8px 8px 0;
      padding: 10px 14px;
      font-size: 12.5px;
    }
    .tanggapan-title {
      font-weight: 700; color: var(--blue-deep); margin-bottom: 3px;
      display: flex; align-items: center; gap: 6px; font-size: 12px;
    }

    /* ── LIGHTBOX MODAL ── */
    .lightbox {
      display: none; position: fixed; inset: 0; z-index: 999;
      background: rgba(0,0,0,.85); backdrop-filter: blur(4px);
      align-items: center; justify-content: center; padding: 20px;
    }
    .lightbox.open { display: flex; }
    .lightbox img {
      max-width: 90vw; max-height: 85vh; border-radius: 12px;
      box-shadow: 0 10px 40px rgba(0,0,0,.5);
    }
    .lightbox-close {
      position: absolute; top: 20px; right: 20px;
      background: rgba(255,255,255,.2); color: white;
      border: none; border-radius: 50%;
      width: 40px; height: 40px; font-size: 20px;
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      transition: background .2s;
    }
    .lightbox-close:hover { background: rgba(255,255,255,.35); }

    /* ── FOOTER ── */
    footer {
      background: var(--blue-deep);
      color: rgba(255,255,255,.6);
      text-align: center;
      padding: 20px;
      font-size: 12px;
      margin-top: auto;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 768px) {
      .navbar { padding: 0 16px; height: 62px; }
      .page-container { padding: 20px 14px 40px; }
      .hero-banner { padding: 24px 20px; }
      .hero-title { font-size: 22px; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
      .stat-card { padding: 10px 12px; }
      .card-body { padding: 18px 16px; }
      .aduan-item { padding: 14px 16px; }
    }
  </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
  <a href="index.php" class="nav-brand">
    <img src="assets/logo.png" alt="Logo SMAN 10">
    <div class="nav-brand-text">
      <strong>Inventaris SARPRAS</strong>
      <span>SMA Negeri 10 Pontianak</span>
    </div>
  </a>
  <div class="nav-actions">
    <a href="index.php" class="btn-nav-back">
      <i class="bi bi-arrow-left"></i> Beranda
    </a>
    <a href="auth/login.php" class="btn-nav-login">
      <i class="bi bi-box-arrow-in-right"></i> Masuk
    </a>
  </div>
</nav>

<!-- ══ MAIN CONTAINER ══ -->
<div class="page-container">

  <!-- ── HERO BANNER ── -->
  <div class="hero-banner">
    <div class="hero-badge">
      <i class="bi bi-shield-check"></i> Layanan Terbuka & Publik
    </div>
    <h1 class="hero-title">Pengaduan Kerusakan & Keluhan Sarpras</h1>
    <p class="hero-desc">
      Laporkan kerusakan fasilitas, sarana, atau prasarana di lingkungan SMA Negeri 10 Pontianak agar dapat segera diperbaiki oleh tim Sarpras.
    </p>
  </div>

  <!-- ── STATS ROW ── -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#EEF2FF;color:#4F46E5;"><i class="bi bi-inbox-fill"></i></div>
      <div>
        <div class="stat-val"><?= number_format($stat_total) ?></div>
        <div class="stat-lbl">Total Laporan</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#FFFBEB;color:#D97706;"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="stat-val"><?= number_format($stat_menunggu) ?></div>
        <div class="stat-lbl">Menunggu</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#EFF6FF;color:#2563EB;"><i class="bi bi-tools"></i></div>
      <div>
        <div class="stat-val"><?= number_format($stat_proses) ?></div>
        <div class="stat-lbl">Diproses</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#F0FDF4;color:#16A34A;"><i class="bi bi-check-circle-fill"></i></div>
      <div>
        <div class="stat-val"><?= number_format($stat_selesai) ?></div>
        <div class="stat-lbl">Selesai</div>
      </div>
    </div>
  </div>

  <!-- ── TABS ── -->
  <div class="tabs-nav">
    <a href="pengaduan.php?tab=form" class="tab-btn <?= $tab !== 'tracking' ? 'active' : '' ?>">
      <i class="bi bi-pencil-square"></i> Buat Pengaduan Baru
    </a>
    <a href="pengaduan.php?tab=tracking" class="tab-btn <?= $tab === 'tracking' ? 'active' : '' ?>">
      <i class="bi bi-card-checklist"></i> Pantau Status Laporan (<?= $stat_total ?>)
    </a>
  </div>

  <!-- ── FLASH NOTIFICATIONS ── -->
  <?php if (!empty($msg_success)): ?>
  <div class="alert alert-success" id="sukses">
    <i class="bi bi-check-circle-fill"></i>
    <div>
      <div><?= $msg_success ?></div>
      <div style="margin-top:6px;font-size:12px;">
        Simpan kode tiket ini untuk memantau perkembangan perbaikan pada tab <strong>Pantau Status Laporan</strong>.
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($msg_error)): ?>
  <div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div><?= htmlspecialchars($msg_error) ?></div>
  </div>
  <?php endif; ?>


  <?php if ($tab !== 'tracking'): ?>
  <!-- ══════════════════════════════════════════
       TAB 1: FORM PENGADUAN
  ══════════════════════════════════════════ -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i class="bi bi-megaphone-fill"></i> Formulir Pengaduan Sarana & Prasarana
      </div>
      <span style="font-size:12px;color:var(--muted);"><span style="color:var(--red);">*</span> Wajib diisi</span>
    </div>

    <div class="card-body">
      <form action="pengaduan.php" method="POST" enctype="multipart/form-data" id="formPengaduan" onsubmit="return validateForm()">
        <input type="hidden" name="action_pengaduan" value="1">

        <!-- 1. Nama Prasarana -->
        <div class="form-group">
          <label class="form-label" for="pilihanPrasarana">
            <span>Nama Prasarana / Ruangan <span class="req">*</span></span>
            <span class="hint">Pilih dari daftar ruangan atau ketik nama lokasi</span>
          </label>
          <select name="pilihan_prasarana" id="pilihanPrasarana" class="form-control" required onchange="handlePrasaranaChange(this.value)">
            <option value="">-- Pilih Prasarana / Ruangan --</option>
            <?php foreach ($ruangan_list as $r): ?>
            <option value="<?= $r['id_ruangan'] ?>">
              <?= htmlspecialchars($r['nama_ruangan']) ?><?= !empty($r['lokasi']) ? ' (' . htmlspecialchars($r['lokasi']) . ')' : '' ?>
            </option>
            <?php endforeach; ?>
            <option value="lainnya">&#9998; Prasarana / Lokasi Lainnya (Tulis Manual)...</option>
          </select>

          <!-- Input Manual Jika Memilih Lainnya -->
          <div class="custom-prasarana-wrap" id="wrapPrasaranaCustom">
            <input type="text" name="nama_prasarana_custom" id="namaPrasaranaCustom" class="form-control"
                   placeholder="Tuliskan nama prasarana / lokasi fasilitas (contoh: Toilet Siswa Lt. 2, Lapangan Basket, Pintu Gerbang)...">
          </div>
        </div>

        <!-- 2. Foto Dokumentasi / Bukti -->
        <div class="form-group">
          <label class="form-label" for="fotoInput">
            <span>Foto Dokumentasi / Bukti Kerusakan <span class="req">*</span></span>
            <span class="hint">Ambil foto atau pilih dari galeri (JPG, PNG, WEBP maks 5MB)</span>
          </label>

          <div class="dropzone" id="dropzone" onclick="document.getElementById('fotoInput').click()">
            <input type="file" name="foto" id="fotoInput" class="file-input-hidden" accept="image/*" capture="environment" onchange="handleFileSelect(this)" required>
            <div class="dropzone-icon"><i class="bi bi-camera-fill"></i></div>
            <div class="dropzone-title">Klik atau Seret Foto Kerusakan ke Sini</div>
            <div class="dropzone-sub">Mendukung kamera langsung dari ponsel atau berkas gambar galeri</div>
          </div>

          <!-- Preview box -->
          <div class="preview-box" id="previewBox">
            <img src="" alt="Pratinjau" class="preview-img" id="previewImg">
            <div class="preview-info">
              <div class="preview-name" id="previewName">—</div>
              <div class="preview-size" id="previewSize">—</div>
            </div>
            <button type="button" class="btn-remove-file" onclick="removeFile()" title="Hapus foto">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
        </div>

        <!-- 3. Deskripsi Keluhan -->
        <div class="form-group">
          <label class="form-label" for="deskripsi">
            <span>Deskripsi Keluhan / Kerusakan <span class="req">*</span></span>
            <span class="hint">Jelaskan kondisi kerusakan secara detail</span>
          </label>
          <textarea name="deskripsi" id="deskripsi" class="form-control" rows="4" required
                    placeholder="Contoh: Lampu utama ruangan mati sejak kemarin, saklar longgar dan mengeluarkan percikan api saat dinyalakan. Mohon bantuan perbaikan."></textarea>
        </div>

        <!-- 4. Identitas Pelapor (Opsional) -->
        <div style="background:#F8FAFD;border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:22px;">
          <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-person-badge-fill" style="color:var(--blue);"></i> Identitas Pelapor <span style="font-size:11px;font-weight:500;color:var(--muted);">(Opsional)</span>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;">Nama Pelapor</label>
              <input type="text" name="nama_pelapor" id="namaPelapor" class="form-control" style="margin-top:4px;"
                     placeholder="Nama Anda (Siswa / Guru / Warga Sekolah)"
                     value="<?= htmlspecialchars($_SESSION['nama'] ?? '') ?>">
            </div>
            <div>
              <label style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;">No. WhatsApp / Kontak</label>
              <input type="text" name="kontak_pelapor" id="kontakPelapor" class="form-control" style="margin-top:4px;"
                     placeholder="Contoh: 081234567890">
            </div>
          </div>

          <div class="anonim-box">
            <label class="anonim-label" for="isAnonim">
              <input type="checkbox" name="is_anonim" id="isAnonim" value="1" onchange="toggleAnonim(this.checked)">
              <span>Kirim laporan secara <strong>Anonim</strong> (Nama tidak akan dipublikasikan)</span>
            </label>
            <i class="bi bi-incognito" style="font-size:18px;color:var(--muted);"></i>
          </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" name="kirim_pengaduan" class="btn-submit" id="btnSubmit">
          <i class="bi bi-send-fill"></i> Kirim Pengaduan Sekarang
        </button>

      </form>
    </div>
  </div>

  <?php else: ?>
  <!-- ══════════════════════════════════════════
       TAB 2: TRACKING & RIWAYAT PENGADUAN
  ══════════════════════════════════════════ -->
  <div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:12px;">
      <div class="card-title">
        <i class="bi bi-clock-history"></i> Daftar Status Laporan Pengaduan
      </div>

      <!-- Filter status -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="pengaduan.php?tab=tracking" class="tab-btn" style="padding:5px 12px;font-size:11px;border:1px solid var(--border);border-radius:20px;<?= empty($filter_status)?'background:var(--blue-deep);color:#fff;':'' ?>">Semua</a>
        <a href="pengaduan.php?tab=tracking&status=menunggu" class="tab-btn" style="padding:5px 12px;font-size:11px;border:1px solid var(--border);border-radius:20px;<?= $filter_status==='menunggu'?'background:var(--amber);color:#fff;':'' ?>">Menunggu</a>
        <a href="pengaduan.php?tab=tracking&status=diproses" class="tab-btn" style="padding:5px 12px;font-size:11px;border:1px solid var(--border);border-radius:20px;<?= $filter_status==='diproses'?'background:var(--blue);color:#fff;':'' ?>">Diproses</a>
        <a href="pengaduan.php?tab=tracking&status=selesai" class="tab-btn" style="padding:5px 12px;font-size:11px;border:1px solid var(--border);border-radius:20px;<?= $filter_status==='selesai'?'background:var(--green);color:#fff;':'' ?>">Selesai</a>
      </div>
    </div>

    <!-- Search bar -->
    <div style="padding:14px 24px;border-bottom:1px solid var(--border);background:#FBFDFE;">
      <form action="pengaduan.php" method="GET" style="display:flex;gap:8px;">
        <input type="hidden" name="tab" value="tracking">
        <?php if ($filter_status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
        <input type="text" name="q" value="<?= htmlspecialchars($cari_aduan) ?>" class="form-control" style="padding:9px 12px;font-size:13px;"
               placeholder="Cari nomor tiket, nama prasarana, atau kata kunci keluhan...">
        <button type="submit" class="btn-submit" style="width:auto;padding:9px 18px;font-size:13px;white-space:nowrap;">
          <i class="bi bi-search"></i> Cari
        </button>
      </form>
    </div>

    <!-- Daftar Aduan -->
    <div>
      <?php if (empty($pengaduan_list)): ?>
      <div style="padding:48px 20px;text-align:center;color:var(--muted);">
        <i class="bi bi-clipboard-x" style="font-size:42px;color:#CBD5E1;display:block;margin-bottom:10px;"></i>
        <div style="font-weight:700;font-size:15px;color:var(--text);margin-bottom:4px;">Tidak Ada Laporan Ditemukan</div>
        <div style="font-size:13px;">Belum ada laporan pengaduan yang sesuai dengan filter atau kata kunci pencarian.</div>
      </div>
      <?php else: ?>
        <?php foreach ($pengaduan_list as $aduan):
          $badge_class = 'badge-menunggu';
          $status_text = 'Menunggu Verifikasi';
          $icon_status = 'bi-hourglass-split';

          if ($aduan['status'] === 'diproses') {
              $badge_class = 'badge-diproses';
              $status_text = 'Sedang Diproses';
              $icon_status = 'bi-gear-fill';
          } elseif ($aduan['status'] === 'selesai') {
              $badge_class = 'badge-selesai';
              $status_text = 'Selesai Diperbaiki';
              $icon_status = 'bi-check-circle-fill';
          } elseif ($aduan['status'] === 'ditolak') {
              $badge_class = 'badge-ditolak';
              $status_text = 'Ditolak';
              $icon_status = 'bi-x-circle-fill';
          }

          $tiket_id = 'PND-' . str_pad($aduan['id_pengaduan'], 4, '0', STR_PAD_LEFT);
        ?>
        <?php
          $is_new_item = (!empty($new_tiket) && $new_tiket === $tiket_id);
        ?>
        <div class="aduan-item <?= $is_new_item ? 'is-new' : '' ?>" id="tiket-<?= $tiket_id ?>">
          <div class="aduan-item-header">
            <div class="aduan-prasarana">
              <span class="aduan-tiket"><?= $tiket_id ?></span>
              <span><?= htmlspecialchars($aduan['nama_prasarana']) ?></span>
              <?php if ($is_new_item): ?>
                <span style="font-size:10.5px;font-weight:800;background:var(--green);color:#fff;padding:2px 8px;border-radius:4px;display:inline-flex;align-items:center;gap:4px;">
                  <i class="bi bi-stars"></i> Laporan Baru Anda
                </span>
              <?php endif; ?>
              <?php if (!empty($aduan['lokasi'])): ?>
                <span style="font-size:11px;font-weight:500;color:var(--muted);background:#F1F5F9;padding:2px 8px;border-radius:4px;">
                  <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($aduan['lokasi']) ?>
                </span>
              <?php endif; ?>
            </div>
            <span class="aduan-badge <?= $badge_class ?>">
              <i class="bi <?= $icon_status ?>"></i> <?= $status_text ?>
            </span>
          </div>

          <div class="aduan-meta">
            <span><i class="bi bi-calendar3"></i> <?= date('d M Y, H:i', strtotime($aduan['tanggal_pengaduan'])) ?> WIB</span>
            <span><i class="bi bi-person"></i> Pelapor: <strong><?= htmlspecialchars($aduan['nama_pelapor']) ?></strong></span>
          </div>

          <div class="aduan-desc">
            <?= nl2br(htmlspecialchars($aduan['deskripsi'])) ?>
          </div>

          <?php if (!empty($aduan['foto']) && file_exists(__DIR__ . '/assets/pengaduan/' . $aduan['foto'])): ?>
          <div class="aduan-foto-wrap">
            <img src="assets/pengaduan/<?= htmlspecialchars($aduan['foto']) ?>"
                 alt="Foto Pengaduan"
                 class="aduan-foto-thumb"
                 onclick="openLightbox(this.src)"
                 title="Klik untuk memperbesar foto">
          </div>
          <?php endif; ?>

          <?php if (!empty($aduan['tanggapan'])): ?>
          <div class="tanggapan-box">
            <div class="tanggapan-title">
              <i class="bi bi-chat-dots-fill"></i> Tanggapan Tim Sarpras:
            </div>
            <div><?= nl2br(htmlspecialchars($aduan['tanggapan'])) ?></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ══ LIGHTBOX MODAL ══ -->
<div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
  <button class="lightbox-close" onclick="closeLightboxDirect()"><i class="bi bi-x-lg"></i></button>
  <img src="" id="lightboxImg" alt="Foto Diperbesar">
</div>

<!-- ══ FOOTER ══ -->
<footer>
  &copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak. Layanan Pengaduan Terbuka.
</footer>

<script>
  /* Prasarana custom dropdown toggle */
  function handlePrasaranaChange(val) {
    const wrap = document.getElementById('wrapPrasaranaCustom');
    const input = document.getElementById('namaPrasaranaCustom');
    if (val === 'lainnya') {
      wrap.style.display = 'block';
      input.required = true;
      setTimeout(() => input.focus(), 60);
    } else {
      wrap.style.display = 'none';
      input.required = false;
      input.value = '';
    }
  }

  /* File upload and live preview */
  function handleFileSelect(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    // Check size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
      alert('Ukuran file foto melebihi batas maksimal 5 MB.');
      removeFile();
      return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('previewImg').src = e.target.result;
      document.getElementById('previewName').textContent = file.name;
      document.getElementById('previewSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
      document.getElementById('previewBox').style.display = 'flex';
      document.getElementById('dropzone').style.borderColor = 'var(--blue)';
    };
    reader.readAsDataURL(file);
  }

  function removeFile() {
    const input = document.getElementById('fotoInput');
    input.value = '';
    document.getElementById('previewBox').style.display = 'none';
    document.getElementById('previewImg').src = '';
    document.getElementById('dropzone').style.borderColor = 'var(--border)';
  }

  /* Anonim checkbox toggle */
  function toggleAnonim(checked) {
    const namaInput = document.getElementById('namaPelapor');
    if (checked) {
      namaInput.dataset.original = namaInput.value;
      namaInput.value = 'Anonim';
      namaInput.readOnly = true;
      namaInput.style.background = '#F1F5F9';
    } else {
      namaInput.value = namaInput.dataset.original || '';
      namaInput.readOnly = false;
      namaInput.style.background = '#FFFFFF';
    }
  }

  /* Drag and drop effects */
  const dropzone = document.getElementById('dropzone');
  if (dropzone) {
    ['dragenter', 'dragover'].forEach(eventName => {
      dropzone.addEventListener(eventName, e => {
        e.preventDefault();
        dropzone.classList.add('dragover');
      }, false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
      dropzone.addEventListener(eventName, e => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
      }, false);
    });
  }

  /* Lightbox */
  function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeLightboxDirect() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
  }
  function closeLightbox(e) {
    if (e.target.id === 'lightbox') {
      closeLightboxDirect();
    }
  }
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeLightboxDirect();
  });

  /* Validate form before submit */
  function validateForm() {
    const sel = document.getElementById('pilihanPrasarana').value;
    const custom = document.getElementById('namaPrasaranaCustom').value.trim();
    if (!sel) {
      alert('Silakan pilih prasarana atau ruangan yang ingin dilaporkan.');
      return false;
    }
    if (sel === 'lainnya' && !custom) {
      alert('Silakan tuliskan nama prasarana / lokasi fasilitas.');
      document.getElementById('namaPrasaranaCustom').focus();
      return false;
    }
    const foto = document.getElementById('fotoInput');
    if (!foto.files || foto.files.length === 0) {
      alert('Foto bukti kerusakan wajib dilampirkan.');
      return false;
    }
    const desc = document.getElementById('deskripsi').value.trim();
    if (!desc) {
      alert('Deskripsi keluhan wajib diisi.');
      document.getElementById('deskripsi').focus();
      return false;
    }

    const btn = document.getElementById('btnSubmit');
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.75';
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Mengirim Laporan...';
    return true;
  }
</script>

</body>
</html>
