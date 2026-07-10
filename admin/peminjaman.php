<?php
session_start();
require_once "../config/koneksi.php";
require_once "../config/auth_admin.php";

$msg_success = '';
$msg_error   = '';


/* ══════════════════════════════════════════
   POST HANDLER
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ── TAMBAH ── */
    if (isset($_POST['tambah_peminjaman'])) {
        $id_pengguna   = trim(mysqli_real_escape_string($conn, $_POST['id_pengguna'] ?? ''));
        $id_barang_arr = $_POST['id_barang'] ?? [];
        $jumlah_arr    = $_POST['jumlah'] ?? [];
        $tujuan        = trim(mysqli_real_escape_string($conn, $_POST['tujuan']));
        $kelas         = trim(mysqli_real_escape_string($conn, $_POST['kelas'] ?? ''));
        $tgl_pinjam    = $_POST['tgl_pinjam'];

        if (empty($id_pengguna) || empty($id_barang_arr) || empty($tujuan) || empty($tgl_pinjam)) {
            $msg_error = "Peminjam, barang, tujuan, dan tanggal wajib diisi.";
        } else {
            $items_to_insert = [];
            $all_ok = true;
            $durasi_menit = null;

            $pg_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM pengguna WHERE id_pengguna='$id_pengguna'"));
            $role_peminjam = $pg_info['role'] ?? 'murid';
            $kolom_durasi = in_array($role_peminjam, ['murid','guru','tendik']) ? "durasi_$role_peminjam AS durasi_role" : "NULL AS durasi_role";

            foreach ($id_barang_arr as $index => $id_b) {
                $id_barang = (int)$id_b;
                $jumlah    = max(1, (int)($jumlah_arr[$index] ?? 1));

                $brg = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT nama_barang, jumlah, jumlah_laik, bisa_dipinjam, $kolom_durasi FROM barang WHERE id_barang=$id_barang"));

                if (!$brg || !$brg['bisa_dipinjam']) {
                    $msg_error = "Sebagian barang tidak tersedia untuk dipinjam.";
                    $all_ok = false; break;
                } elseif ($brg['jumlah_laik'] < $jumlah) {
                    $msg_error = "Jumlah layak pakai '{$brg['nama_barang']}' tidak mencukupi (tersedia: {$brg['jumlah_laik']}).";
                    $all_ok = false; break;
                }

                if (!is_null($brg['durasi_role'])) {
                    $durasi_menit = is_null($durasi_menit) ? (int)$brg['durasi_role'] : min($durasi_menit, (int)$brg['durasi_role']);
                }

                $items_to_insert[] = [
                    'id_barang' => $id_barang,
                    'jumlah'    => $jumlah
                ];
            }

            if ($all_ok) {
                $today    = date('Y-m-d');
                $now_time = date('H:i:s');
                
                if (!is_null($durasi_menit) && $durasi_menit > 0) {
                    $waktu_selesai_sql = "'".date('H:i:s', strtotime("+$durasi_menit minutes", strtotime($now_time)))."'";
                } else {
                    $waktu_selesai_sql = 'NULL';
                }

                mysqli_query($conn, "
                    INSERT INTO peminjaman
                      (id_pengguna, tujuan, kelas, tanggal_pengajuan, tanggal_pinjam, waktu_mulai, waktu_selesai, status)
                    VALUES
                      ('$id_pengguna','$tujuan','$kelas','$today','$tgl_pinjam','$now_time',$waktu_selesai_sql,'dipinjam')
                ");
                $new_id = mysqli_insert_id($conn);

                foreach ($items_to_insert as $item) {
                    $ib = $item['id_barang'];
                    $jm = $item['jumlah'];
                    mysqli_query($conn, "
                        INSERT INTO detail_peminjaman (id_peminjaman, id_barang, jumlah)
                        VALUES ($new_id, $ib, $jm)
                    ");
                    mysqli_query($conn, "UPDATE barang SET jumlah=jumlah-$jm, jumlah_laik=jumlah_laik-$jm WHERE id_barang=$ib");
                }
                
                header("Location: peminjaman.php?success=".urlencode("Peminjaman berhasil dicatat."));
                exit;
            }
        }
    }

    /* ── SETUJUI ── */
    if (isset($_POST['setujui_peminjaman'])) {
        $id_pm = (int)$_POST['id_peminjaman'];

        /* Ambil data peminjaman + role peminjam */
        $pm_info = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT pm.id_pengguna, pg.role AS role_peminjam
             FROM peminjaman pm
             LEFT JOIN pengguna pg ON pm.id_pengguna = pg.id_pengguna
             WHERE pm.id_peminjaman = $id_pm"));
        $role_peminjam = $pm_info['role_peminjam'] ?? 'murid';

        /* Ambil detail barang + durasi per role */
        $kolom_durasi = in_array($role_peminjam, ['murid','guru','tendik'])
                        ? "b.durasi_$role_peminjam AS durasi_role"
                        : "NULL AS durasi_role";
        $det = mysqli_query($conn,
            "SELECT dp.id_barang, dp.jumlah AS jumlah_pinjam, b.jumlah_laik AS stok, $kolom_durasi
             FROM detail_peminjaman dp
             JOIN barang b ON dp.id_barang = b.id_barang
             WHERE dp.id_peminjaman = $id_pm");

        $ok = true; $rows = []; $durasi_menit = null;
        while ($d = mysqli_fetch_assoc($det)) {
            if ($d['stok'] < $d['jumlah_pinjam']) { $ok = false; break; }
            /* Ambil durasi terpendek jika ada banyak barang (paling ketat) */
            if (!is_null($d['durasi_role'])) {
                $durasi_menit = is_null($durasi_menit)
                    ? (int)$d['durasi_role']
                    : min($durasi_menit, (int)$d['durasi_role']);
            }
            $rows[] = $d;
        }

        if (!$ok) {
            $msg_error = "Jumlah tidak mencukupi untuk menyetujui peminjaman ini.";
        } else {
            $now_time_setujui = date('H:i:s');

            /* Hitung waktu_selesai jika ada batas durasi */
            if (!is_null($durasi_menit) && $durasi_menit > 0) {
                $waktu_selesai_sql = "'".date('H:i:s', strtotime("+$durasi_menit minutes", strtotime($now_time_setujui)))."'";
            } else {
                $waktu_selesai_sql = 'NULL';
            }

            mysqli_query($conn,
                "UPDATE peminjaman
                 SET status        = 'dipinjam',
                     waktu_mulai   = '$now_time_setujui',
                     waktu_selesai = $waktu_selesai_sql
                 WHERE id_peminjaman = $id_pm");

            foreach ($rows as $d)
                mysqli_query($conn, "UPDATE barang SET jumlah=jumlah-{$d['jumlah_pinjam']}, jumlah_laik=jumlah_laik-{$d['jumlah_pinjam']} WHERE id_barang={$d['id_barang']}");

            $msg_ok = "Peminjaman disetujui.";
            if (!is_null($durasi_menit))
                $msg_ok .= " Batas waktu: " . date('H:i', strtotime("+$durasi_menit minutes", strtotime($now_time_setujui))) . " ({$durasi_menit} menit).";

            header("Location: peminjaman.php?success=".urlencode($msg_ok));
            exit;
        }
    }

    /* ── HAPUS ── */
    if (isset($_POST['hapus_peminjaman'])) {
        $id_pm = (int)$_POST['id_peminjaman'];
        $pm    = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT status FROM peminjaman WHERE id_peminjaman=$id_pm"));

        if ($pm && $pm['status'] === 'dipinjam') {
            $det = mysqli_query($conn,
                "SELECT id_barang, jumlah FROM detail_peminjaman WHERE id_peminjaman=$id_pm");
            while ($d = mysqli_fetch_assoc($det))
                mysqli_query($conn, "UPDATE barang SET jumlah=jumlah+{$d['jumlah']}, jumlah_laik=jumlah_laik+{$d['jumlah']} WHERE id_barang={$d['id_barang']}");
        }
        mysqli_query($conn, "DELETE FROM detail_peminjaman WHERE id_peminjaman=$id_pm");
        mysqli_query($conn, "DELETE FROM peminjaman WHERE id_peminjaman=$id_pm");
        header("Location: peminjaman.php?success=".urlencode("Data peminjaman dihapus."));
        exit;
    }
}

if (isset($_GET['success'])) $msg_success = htmlspecialchars($_GET['success']);

/* ── Filter & Search ── */
$filter_status = isset($_GET['status']) && in_array($_GET['status'],
    ['menunggu','dipinjam','dikembalikan']) ? $_GET['status'] : '';
$search = isset($_GET['q']) ? trim(mysqli_real_escape_string($conn, $_GET['q'])) : '';
$filter_tgl_dari = isset($_GET['dari']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari']) ? $_GET['dari'] : '';
$filter_tgl_ke   = isset($_GET['ke'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['ke'])   ? $_GET['ke']   : '';

$where = "WHERE 1=1";
if ($filter_status)  $where .= " AND pm.status='$filter_status'";
if ($filter_tgl_dari) $where .= " AND pm.tanggal_pinjam >= '$filter_tgl_dari'";
if ($filter_tgl_ke)   $where .= " AND pm.tanggal_pinjam <= '$filter_tgl_ke'";
if ($search)         $where .= " AND (pg.nama LIKE '%$search%'
                                     OR b.nama_barang LIKE '%$search%'
                                     OR pm.tujuan LIKE '%$search%' OR pm.kelas LIKE '%$search%')";

/* ── Pagination ── */
$valid_per_page = [5,10,20,25,50,100];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'],$valid_per_page) ? (int)$_GET['per_page'] : 5;
$page     = max(1, (int)($_GET['page'] ?? 1));

$total = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT pm.id_peminjaman) as t
    FROM peminjaman pm
    LEFT JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
    LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
    LEFT JOIN barang b ON dp.id_barang=b.id_barang
    $where"))['t'];

$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$pinjam_q = mysqli_query($conn, "
    SELECT pm.*,
           COALESCE(pg.nama) AS nama_peminjam,
           COALESCE(pg.role, 'tamu') AS role_peminjam,
           GROUP_CONCAT(b.nama_barang ORDER BY b.nama_barang SEPARATOR ', ') AS nama_barang_list,
           GROUP_CONCAT(dp.jumlah     ORDER BY b.nama_barang SEPARATOR ', ') AS jumlah_list
    FROM peminjaman pm
    LEFT JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
    LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
    LEFT JOIN barang b ON dp.id_barang=b.id_barang
    $where
    GROUP BY pm.id_peminjaman
    ORDER BY pm.id_peminjaman DESC
    LIMIT $per_page OFFSET $offset
");

/* ── Stats ── */
$stats_pm = [];
foreach (['menunggu','dipinjam','dikembalikan'] as $s)
    $stats_pm[$s] = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as t FROM peminjaman WHERE status='$s'"))['t'];
$stats_pm['total'] = array_sum($stats_pm);

/* ── Hitung badge status ── */
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengguna WHERE status='pending'"))['t'];
$pm_menunggu = $stats_pm['menunggu'];

/* ── Data untuk form ── */
$barang_list = [];
$bq = mysqli_query($conn,
    "SELECT id_barang, nama_barang, kode_barang, jumlah FROM barang WHERE bisa_dipinjam=1 AND jumlah>0 ORDER BY nama_barang");
while ($bv = mysqli_fetch_assoc($bq)) $barang_list[] = $bv;

$pengguna_list = [];
$pq2 = mysqli_query($conn,
    "SELECT id_pengguna, nama, role FROM pengguna WHERE status='aktif' ORDER BY nama");
while ($pv = mysqli_fetch_assoc($pq2)) $pengguna_list[] = $pv;

$status_cfg = [
    'menunggu'     => ['Menunggu',     'badge-warning'],
    'dipinjam'     => ['Dipinjam',     'badge-info'],
    'dikembalikan' => ['Dikembalikan', 'badge-success'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Peminjaman — Inventaris SARPRAS</title>
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
    .nav-badge{display:inline-flex;align-items:center;justify-content:center;background:var(--red);color:white;width:17px;height:17px;border-radius:50%;font-size:10px;font-weight:800;margin-left:4px;vertical-align:middle;}
    .nav-hamburger{display:none;margin-left:auto;background:none;border:none;cursor:pointer;color:white;font-size:22px;padding:6px;border-radius:8px;}
    .nav-hamburger:hover{background:rgba(255,255,255,.1);}
    .nav-mobile-menu{display:none;position:fixed;top:62px;left:0;right:0;background:var(--blue-deep);box-shadow:0 8px 24px rgba(27,63,110,.3);z-index:199;flex-direction:column;padding:10px 16px 20px;border-top:1px solid rgba(255,255,255,.1);max-height:calc(100vh - 62px);overflow-y:auto;}
    .nav-mobile-menu.open{display:flex;}
    .nav-mobile-menu .nav-link{padding:13px 14px;border-radius:10px;font-size:14px;border-bottom:1px solid rgba(255,255,255,.06);}
    .nav-mobile-menu .nav-link:last-child{border-bottom:none;}
    .nav-mobile-menu .nav-link.logout{margin-left:0;margin-top:6px;}
    .page-wrapper{max-width:1040px;margin:0 auto;padding:32px 24px 60px;flex:1;}
    .flash{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:9px;}
    .flash-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;}
    .flash-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;}
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;gap:16px;flex-wrap:wrap;}
    .page-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:900;color:var(--text);}
    .page-sub{font-size:13px;color:var(--muted);margin-top:3px;}
    .stats-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
    .stat-pill{display:inline-flex;align-items:center;gap:8px;background:var(--card);border:1.5px solid var(--border);border-radius:30px;padding:7px 16px;font-size:12px;font-weight:700;color:var(--text);text-decoration:none;transition:all .2s;}
    .stat-pill:hover{border-color:var(--blue);background:#EFF6FF;}
    .stat-pill.active{border-color:var(--blue-dark);background:var(--blue-dark);color:white;}
    .stat-pill.active .pill-dot{background:white!important;}
    .pill-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
    .toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
    .search-wrap-inner{position:relative;flex:1;min-width:200px;}
    .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:14px;pointer-events:none;}
    .search-input{width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border);border-right:none;border-radius:9px 0 0 9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);outline:none;transition:border-color .2s;}
    .search-input:focus{border-color:var(--blue);}
    .btn-search{padding:10px 16px;background:var(--blue-dark);color:white;border:none;border-radius:0 9px 9px 0;cursor:pointer;font-size:14px;}
    .btn-search:hover{background:var(--blue-deep);}

    /* ── Filter tanggal ── */
    .filter-date {
      padding: 10px 12px; border: 1.5px solid var(--border);
      border-radius: 9px; font-size: 13px; font-family: 'DM Sans', sans-serif;
      color: var(--text); background: var(--card);
      cursor: pointer; outline: none; transition: border-color .2s;
    }
    .filter-date:hover, .filter-date:focus { border-color: var(--blue); }
    .filter-date-wrap {
      display: flex; align-items: center; gap: 6px;
      background: var(--card); border: 1.5px solid var(--border);
      border-radius: 9px; padding: 0 12px 0 0; overflow: hidden;
    }
    .filter-date-wrap .filter-date {
      border: none; border-radius: 0; padding-right: 0;
      background: transparent;
    }
    .filter-date-wrap .filter-date:focus { box-shadow: none; }
    .filter-date-label {
      font-size: 11px; font-weight: 700; color: var(--muted);
      text-transform: uppercase; letter-spacing: .4px; white-space: nowrap;
      padding-left: 12px;
    }

    .card{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;}
    .card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .card-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;color:var(--text);display:flex;align-items:center;gap:8px;}
    .card-title i{color:var(--blue);}
    .table-wrap{overflow-x:auto;}
    .inv-table{width:100%;border-collapse:collapse;font-size:13.5px;}
    .inv-table thead th{background:#F4F8FD;padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.5px;text-transform:uppercase;border-bottom:2px solid var(--border);white-space:nowrap;}
    .inv-table tbody tr{border-bottom:1px solid var(--border);transition:background .12s;}
    .inv-table tbody tr:last-child{border-bottom:none;}
    .inv-table tbody tr:hover{background:#F4F8FD;}
    .inv-table td{padding:12px 14px;color:var(--text);vertical-align:middle;}
    .badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
    .badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;opacity:.7;}
    .badge-success{background:#F0FDF4;color:#15803D;}
    .badge-info{background:#EFF6FF;color:#2563EB;}
    .badge-warning{background:#FFFBEB;color:#D97706;}
    .badge-danger{background:#FEF2F2;color:#DC2626;}
    .badge-muted{background:#F1F5F9;color:#64748B;}
    .time-info{font-size:11px;color:var(--muted);margin-top:3px;}
    .time-overdue{color:#DC2626!important;font-weight:700;}
    .btn{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:'DM Sans',sans-serif;}
    .btn-sm{padding:6px 10px;font-size:12px;border-radius:7px;}
    .btn-xs{padding:4px 8px;font-size:11px;border-radius:6px;}
    .btn-primary{background:var(--blue-dark);color:white;box-shadow:0 4px 14px rgba(43,111,168,.25);}
    .btn-primary:hover{background:var(--blue-deep);}
    .btn-secondary{background:var(--card);color:var(--text);border:1px solid var(--border);}
    .btn-secondary:hover{background:var(--bg);}
    .btn-danger{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;}
    .btn-danger:hover{background:#DC2626;color:white;}
    .btn-success{background:#F0FDF4;color:#16A34A;border:1px solid #BBF7D0;}
    .btn-success:hover{background:#16A34A;color:white;}
    .btn-warning{background:#FFFBEB;color:#D97706;border:1px solid #FDE68A;}
    .btn-warning:hover{background:#D97706;color:white;}
    .table-footer{display:flex;align-items:center;justify-content:space-between;padding:13px 20px;border-top:1px solid var(--border);font-size:13px;color:var(--muted);flex-wrap:wrap;gap:10px;}
    .pag-btns{display:flex;align-items:center;gap:6px;}
    .pag-btn{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;background:var(--card);color:var(--text);text-decoration:none;transition:all .2s;}
    .pag-btn:hover:not(.disabled):not(.active){background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.active{background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.disabled{opacity:.35;cursor:not-allowed;pointer-events:none;}
    .pag-btn-text{padding:0 12px;width:auto;}
    .empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
    .empty-state i{font-size:40px;display:block;margin-bottom:12px;color:#B0C8E0;}
    .empty-state h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px;}
    .mobile-list{display:none;}
    .mobile-item{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:10px;box-shadow:var(--shadow);}
    .mobile-item-header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px;}
    .mobile-item-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;}
    .mobile-item-meta{display:flex;flex-wrap:wrap;gap:7px;align-items:center;}
    .modal-backdrop{display:none;position:fixed;inset:0;z-index:500;background:rgba(27,63,110,.35);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
    .modal-backdrop.open{display:flex;}
    .modal-box{background:var(--card);border-radius:18px;padding:32px;width:100%;max-width:520px;box-shadow:var(--shadow-lg);position:relative;z-index:501;animation:modalIn .22s cubic-bezier(.4,0,.2,1);max-height:92vh;overflow-y:auto;}
    .modal-box-sm{max-width:420px;}
    @keyframes modalIn{from{transform:scale(.94) translateY(10px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
    .modal-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--text);margin-bottom:6px;}
    .modal-sub{font-size:13px;color:var(--muted);margin-bottom:22px;}
    .modal-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:8px;background:var(--bg);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px;transition:all .2s;}
    .modal-close:hover{background:#FECACA;color:#DC2626;}
    .modal-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;border-radius:8px;padding:9px 12px;font-size:12px;margin-bottom:14px;display:none;}
    .modal-error.show{display:block;}
    .modal-info{background:#FFF8E1;border:1px solid #FFE082;color:#92400E;border-radius:8px;padding:9px 12px;font-size:12px;margin-bottom:14px;line-height:1.6;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
    .form-group{margin-bottom:14px;}
    .form-label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;}
    .form-label span{color:#DC2626;}
    .form-control{width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
    .form-control:focus{border-color:var(--blue);background:white;box-shadow:0 0 0 3px rgba(74,144,196,.14);}
    .form-hint{font-size:11px;color:var(--muted);margin-top:5px;}
    .duration-display{background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;margin-top:8px;display:none;}
    .duration-display.show{display:block;}
    .duration-display.over{background:#FEF2F2;border-color:#FECACA;color:#DC2626;}
    .duration-display.ok{background:#F0FDF4;border-color:#BBF7D0;color:#166534;}
    .btn-modal-submit{width:100%;padding:12px;background:var(--blue-dark);color:white;border:none;border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 20px rgba(43,111,168,.28);transition:all .2s;margin-top:4px;}
    .btn-modal-submit:hover{background:var(--blue-deep);}
    .btn-modal-danger{width:100%;padding:12px;background:#DC2626;color:white;border:none;border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:10px;}
    .btn-modal-danger:hover{background:#B91C1C;}
    .btn-modal-cancel{width:100%;padding:11px;background:var(--bg);color:var(--muted);border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:8px;}
    .btn-modal-cancel:hover{background:#E5E7EB;}

    /* ── Autocomplete Peminjam ── */
    .ac-wrap{position:relative;}
    .ac-input-row{display:flex;align-items:center;gap:0;position:relative;}
    .ac-input{width:100%;padding:11px 38px 11px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
    .ac-input:focus{border-color:var(--blue);background:white;box-shadow:0 0 0 3px rgba(74,144,196,.14);}
    .ac-clear{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;padding:3px;border-radius:4px;display:none;line-height:1;}
    .ac-clear:hover{color:#DC2626;}
    .ac-clear.show{display:flex;align-items:center;justify-content:center;}
    .ac-dropdown{position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--card);border:1.5px solid var(--border);border-radius:10px;box-shadow:0 8px 24px rgba(27,63,110,.14);z-index:600;max-height:220px;overflow-y:auto;display:none;}
    .ac-dropdown.open{display:block;}
    .ac-item{padding:10px 14px;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:1px solid var(--border);transition:background .12s;}
    .ac-item:last-child{border-bottom:none;}
    .ac-item:hover{background:#EFF6FF;}
    .ac-item strong{color:var(--blue-dark);}
    .ac-role{font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:#EFF6FF;color:var(--blue-dark);flex-shrink:0;}
    .ac-role.murid{background:#FFF8E1;color:#92400E;}
    .ac-role.guru{background:#F0FDF4;color:#15803D;}
    .ac-role.tendik{background:#F5F3FF;color:#7C3AED;}
    .ac-selected-tag{display:none;align-items:center;gap:5px;background:#EFF6FF;border:1.5px solid var(--blue);border-radius:8px;padding:5px 10px;font-size:12px;font-weight:700;color:var(--blue-dark);margin-top:6px;}
    .ac-selected-tag.show{display:inline-flex;}
    .ac-manual-tag{display:none;align-items:center;gap:5px;background:#F5F3FF;border:1.5px solid #A78BFA;border-radius:8px;padding:5px 10px;font-size:12px;font-weight:700;color:#7C3AED;margin-top:6px;}
    .ac-manual-tag.show{display:inline-flex;}
    .ac-empty{padding:14px;text-align:center;font-size:12px;color:var(--muted);}

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

    @media(max-width:768px){
      .navbar{position:relative;}.nav-links{display:none;}
      .nav-hamburger{display:flex;align-items:center;justify-content:center;}.nav-brand{margin-right:0;}
      .page-wrapper{padding:20px 14px 80px;}.page-title{font-size:20px;}
      .page-header{flex-direction:column;align-items:stretch;gap:12px;}.page-header .btn{justify-content:center;}

      /* Stat pills: sama rata (flex:1) sehingga 2+2 per baris sama lebar */
      .stats-row{flex-wrap:wrap;gap:8px;}
      .stat-pill{flex:1 1 calc(50% - 4px);justify-content:center;min-width:0;}

      /* Toolbar */
      .toolbar{flex-direction:column;gap:8px;}

      /* Search full width */
      #searchFormPeminjaman{width:100%;flex:unset;}
      .search-wrap-inner{width:100%;min-width:unset;}

      /* Filter tanggal: DARI dan SAMPAI sejajar satu baris */
      #filterTanggalForm{flex-wrap:nowrap !important;align-items:flex-end;gap:8px;width:100%;}
      #filterTanggalForm > div{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px;}
      #filterTanggalForm .filter-date{width:100%;box-sizing:border-box;}
      #filterTanggalForm .filter-date-label{font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);}

      /* Show entries */
      .show-entries-wrap{flex-shrink:0;}

      .table-wrap{display:none;}.mobile-list{display:block;}
      .card-header{padding:14px 16px;}
      .table-footer{flex-direction:column;align-items:flex-start;gap:10px;padding:12px 16px;}
      .pag-btns{width:100%;justify-content:center;}
      .modal-box{margin:12px;padding:22px 18px;border-radius:16px;max-width:100%;}
      .form-row{grid-template-columns:1fr;gap:0;}
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

  <!-- Desktop -->
  <div class="nav-links">
    <a href="dashboard.php"    class="nav-link">Dashboard</a>
    <a href="ruangan.php"      class="nav-link">Prasarana</a>
    <a href="barang.php"       class="nav-link">Sarana</a>
    <a href="pengguna.php"     class="nav-link">
      Pengguna
      <?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?>
    </a>
    <a href="peminjaman.php"   class="nav-link active">
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
  <a href="pengguna.php"       class="nav-link">
    Pengguna
    <?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?>
  </a>
  <a href="peminjaman.php"     class="nav-link active">
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

  <div class="page-header">
    <div>
      <div class="page-title">Peminjaman</div>
      <div class="page-sub">Catat dan kelola peminjaman sarana inventaris SARPRAS</div>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">
      <i class="bi bi-plus-lg"></i> Catat Peminjaman
    </button>
  </div>

  <!-- Stats Pills -->
  <div class="stats-row">
    <?php
    $pill_cfg = [
      ''             => ['Semua',        '#4A90C4', $stats_pm['total']],
      'menunggu'     => ['Menunggu',     '#D97706', $stats_pm['menunggu']],
      'dipinjam'     => ['Dipinjam',     '#2563EB', $stats_pm['dipinjam']],
      'dikembalikan' => ['Dikembalikan', '#16A34A', $stats_pm['dikembalikan']],
    ];
    $base_q = '?' . ($search ? 'q='.urlencode($search).'&' : '') . ($filter_tgl_dari ? 'dari='.$filter_tgl_dari.'&' : '') . ($filter_tgl_ke ? 'ke='.$filter_tgl_ke.'&' : '');
    foreach ($pill_cfg as $sk => $sv):
    ?>
    <a href="<?= $base_q.($sk?'status='.$sk:'') ?>" class="stat-pill <?= $filter_status===$sk?'active':'' ?>">
      <span class="pill-dot" style="background:<?= $sv[1] ?>;"></span>
      <?= $sv[0] ?> <span style="opacity:.65;">(<?= $sv[2] ?>)</span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <form method="GET" id="searchFormPeminjaman" style="display:flex;gap:0;flex:1;min-width:200px;">
      <?php if ($filter_status): ?><input type="hidden" name="status" value="<?= $filter_status ?>"><?php endif; ?>
      <?php if ($filter_tgl_dari): ?><input type="hidden" name="dari" value="<?= $filter_tgl_dari ?>"><?php endif; ?>
      <?php if ($filter_tgl_ke): ?><input type="hidden" name="ke" value="<?= $filter_tgl_ke ?>"><?php endif; ?>
      <div class="search-wrap-inner">
        <i class="bi bi-search search-icon"></i>
        <input type="text" name="q" id="searchInputPeminjaman" class="search-input" placeholder="Cari nama, barang, tujuan, kelas..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <button type="submit" class="btn-search"><i class="bi bi-search"></i></button>
    </form>
    <form method="GET" id="filterTanggalForm" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
      <?php if ($filter_status): ?><input type="hidden" name="status" value="<?= $filter_status ?>"><?php endif; ?>
      <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
      <?php if ($per_page!=10): ?><input type="hidden" name="per_page" value="<?= $per_page ?>"><?php endif; ?>
      <div style="display:flex;align-items:center;gap:6px;">
        <span class="filter-date-label">Dari</span>
        <input type="date" name="dari" class="filter-date" value="<?= htmlspecialchars($filter_tgl_dari) ?>"
               title="Tanggal mulai" onchange="this.form.submit()">
      </div>
      <div style="display:flex;align-items:center;gap:6px;">
        <span class="filter-date-label">Sampai</span>
        <input type="date" name="ke" class="filter-date" value="<?= htmlspecialchars($filter_tgl_ke) ?>"
               title="Tanggal selesai" onchange="this.form.submit()">
      </div>
    </form>
    <?php if ($search || $filter_status || $filter_tgl_dari || $filter_tgl_ke): ?>
    <a href="peminjaman.php" class="btn btn-secondary btn-sm"><i class="bi bi-x"></i> Reset</a>
    <?php endif; ?>
    <form method="GET" style="display:flex;align-items:center;">
      <?php if ($filter_status): ?><input type="hidden" name="status" value="<?= $filter_status ?>"><?php endif; ?>
      <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
      <?php if ($filter_tgl_dari): ?><input type="hidden" name="dari" value="<?= $filter_tgl_dari ?>"><?php endif; ?>
      <?php if ($filter_tgl_ke): ?><input type="hidden" name="ke" value="<?= $filter_tgl_ke ?>"><?php endif; ?>
      <div class="show-entries-wrap">
        <label class="show-entries-label">Tampilkan</label>
        <select class="show-entries-select" name="per_page" onchange="this.form.submit()">
          <option value="5"   <?= $per_page==5  ?'selected':'' ?>>5</option>
          <option value="10"  <?= $per_page==10 ?'selected':'' ?>>10</option>
          <option value="20"  <?= $per_page==20 ?'selected':'' ?>>20</option>
          <option value="25"  <?= $per_page==25 ?'selected':'' ?>>25</option>
          <option value="50"  <?= $per_page==50 ?'selected':'' ?>>50</option>
          <option value="100" <?= $per_page==100?'selected':'' ?>>100</option>
        </select>
        <span class="show-entries-label">entri</span>
      </div>
    </form>
  </div>

  <!-- TABEL -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-clipboard-check"></i> Daftar Peminjaman</div>
      <span style="font-size:12px;color:var(--muted);"><?= number_format($total) ?> transaksi</span>
    </div>

    <div class="table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th style="width:44px;">No</th>
            <th>Peminjam</th>
            <th>Sarana</th>
            <th>Tujuan</th>
            <th>Tgl Pinjam</th>
            <th style="width:120px;">Status</th>
            <th style="width:130px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no = $offset + 1; $has = false;
          while ($pm = mysqli_fetch_assoc($pinjam_q)):
            $has = true;
            $sc  = $status_cfg[$pm['status']] ?? [ucfirst($pm['status']),'badge-muted'];
            $is_overdue = ($pm['status']==='dipinjam' && $pm['waktu_selesai'] && $pm['tanggal_pinjam'] &&
                           time() > strtotime($pm['tanggal_pinjam'].' '.$pm['waktu_selesai']));
          ?>
          <tr>
            <td style="color:var(--muted);font-size:12px;"><?= $no++ ?></td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($pm['nama_peminjam']) ?></div>
              <div style="font-size:11px;color:var(--muted);">
                <?= ucfirst($pm['role_peminjam']) ?>
                <?php if ($pm['kelas']): ?>&bull; <strong style="color:var(--blue-dark);"><?= htmlspecialchars($pm['kelas']) ?></strong><?php endif; ?>
              </div>
            </td>
            <td>
              <?php
              $b_arr = explode(', ', $pm['nama_barang_list']??'');
              $j_arr = explode(', ', $pm['jumlah_list']??'');
              foreach ($b_arr as $i => $nb): ?>
                <div style="font-size:13px;"><span style="font-weight:600;"><?= htmlspecialchars($nb) ?></span><span style="color:var(--muted);"> ×<?= $j_arr[$i]??1 ?></span></div>
              <?php endforeach; ?>
            </td>
            <td style="font-size:13px;max-width:160px;">
              <?= htmlspecialchars($pm['tujuan']??'') ?>
              <?php if ($pm['catatan']): ?><div style="font-size:11px;color:var(--muted);font-style:italic;"><?= htmlspecialchars($pm['catatan']) ?></div><?php endif; ?>
            </td>
            <td>
              <div style="font-weight:600;font-size:13px;"><?= $pm['tanggal_pinjam'] ? date('d M Y',strtotime($pm['tanggal_pinjam'])) : '—' ?></div>
              <?php if ($pm['waktu_mulai']): ?>
              <div class="time-info">
                <i class="bi bi-clock"></i> Mulai: <?= substr($pm['waktu_mulai'],0,5) ?>
                <?php if ($is_overdue): ?> <i class="bi bi-exclamation-triangle-fill" style="color:#DC2626;"></i><?php endif; ?>
              </div>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $sc[1] ?>"><?= $sc[0] ?></span>
              <?php if ($is_overdue && $pm['status']==='dipinjam'): ?>
                <div style="margin-top:4px;"><span class="badge badge-danger" style="font-size:10px;">Terlambat</span></div>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                <?php if ($pm['status']==='menunggu'): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="id_peminjaman" value="<?= $pm['id_peminjaman'] ?>">
                    <button type="submit" name="setujui_peminjaman" class="btn btn-xs btn-success" title="Setujui"><i class="bi bi-check-lg"></i></button>
                  </form>
                <?php endif; ?>
                <?php if ($pm['status']==='dipinjam'): ?>
                  <a href="pengembalian.php?catat=<?= $pm['id_peminjaman'] ?>" class="btn btn-xs btn-warning"><i class="bi bi-arrow-return-left"></i> Kembali</a>
                <?php endif; ?>
                <button class="btn btn-xs btn-danger" onclick="openHapusModal(<?= $pm['id_peminjaman'] ?>,'<?= htmlspecialchars(addslashes($pm['nama_peminjam'])) ?>')"><i class="bi bi-trash"></i></button>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$has): ?>
          <tr><td colspan="7"><div class="empty-state"><i class="bi bi-clipboard-x"></i><h3><?= $search?'Tidak ditemukan':'Belum ada peminjaman' ?></h3><p><?= $search?'Tidak ada data yang cocok.':'Belum ada data peminjaman tercatat.' ?></p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile List -->
    <div class="mobile-list">
      <?php
      $pinjam_q2 = mysqli_query($conn, "
          SELECT pm.*,
                 pg.nama AS nama_peminjam,
                 pg.role AS role_peminjam,
                 GROUP_CONCAT(b.nama_barang ORDER BY b.nama_barang SEPARATOR ', ') AS nama_barang_list,
                 GROUP_CONCAT(dp.jumlah ORDER BY b.nama_barang SEPARATOR ', ') AS jumlah_list
          FROM peminjaman pm
          LEFT JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
          LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
          LEFT JOIN barang b ON dp.id_barang=b.id_barang
          $where GROUP BY pm.id_peminjaman
          ORDER BY pm.id_peminjaman DESC
          LIMIT $per_page OFFSET $offset");
      while ($pm2 = mysqli_fetch_assoc($pinjam_q2)):
        $sc2 = $status_cfg[$pm2['status']] ?? [ucfirst($pm2['status']),'badge-muted'];
        $is_ov2  = ($pm2['status']==='dipinjam' && $pm2['waktu_selesai'] && time()>strtotime($pm2['tanggal_pinjam'].' '.$pm2['waktu_selesai']));
      ?>
      <div class="mobile-item">
        <div class="mobile-item-header">
          <div>
            <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($pm2['nama_barang_list']??'') ?></div>
            <div style="font-size:12px;color:var(--blue-dark);margin-top:2px;"><?= htmlspecialchars($pm2['nama_peminjam']) ?><?php if ($pm2['kelas']): ?> — <strong><?= htmlspecialchars($pm2['kelas']) ?></strong><?php endif; ?></div>
          </div>
          <span class="badge <?= $sc2[1] ?>"><?= $sc2[0] ?></span>
        </div>
        <div class="mobile-item-meta">
          <span style="font-size:12px;color:var(--muted);"><i class="bi bi-calendar3"></i> <?= $pm2['tanggal_pinjam']?date('d M Y',strtotime($pm2['tanggal_pinjam'])):'—' ?></span>
          <?php if ($pm2['waktu_mulai']): ?><span style="font-size:12px;color:var(--muted);"><i class="bi bi-clock"></i> Mulai: <?= substr($pm2['waktu_mulai'],0,5) ?></span><?php endif; ?>
        </div>
        <div style="font-size:12px;color:var(--muted);margin-top:6px;"><?= htmlspecialchars($pm2['tujuan']??'') ?></div>
        <div class="mobile-item-actions">
          <?php if ($pm2['status']==='menunggu'): ?>
            <form method="POST" style="display:inline;"><input type="hidden" name="id_peminjaman" value="<?= $pm2['id_peminjaman'] ?>"><button type="submit" name="setujui_peminjaman" class="btn btn-xs btn-success"><i class="bi bi-check-lg"></i> Setujui</button></form>
          <?php endif; ?>
          <?php if ($pm2['status']==='dipinjam'): ?>
            <a href="pengembalian.php?catat=<?= $pm2['id_peminjaman'] ?>" class="btn btn-xs btn-warning"><i class="bi bi-arrow-return-left"></i> Catat Kembali</a>
          <?php endif; ?>
          <button class="btn btn-xs btn-danger" onclick="openHapusModal(<?= $pm2['id_peminjaman'] ?>,'<?= htmlspecialchars(addslashes($pm2['nama_peminjam'])) ?>')"><i class="bi bi-trash"></i></button>
        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <?php if ($total > 0): ?>
    <div class="table-footer">
      <span>Menampilkan <?= $offset+1 ?>–<?= min($offset+$per_page,$total) ?> dari <?= number_format($total) ?> data</span>
      <div class="pag-btns">
        <?php $bu = '?'.($filter_status?'status='.$filter_status.'&':'').($search?'q='.urlencode($search).'&':'').($filter_tgl_dari?'dari='.$filter_tgl_dari.'&':'').($filter_tgl_ke?'ke='.$filter_tgl_ke.'&':'').($per_page!=5?'per_page='.$per_page.'&':''); ?>
        <a href="<?= $bu ?>page=<?= $page-1 ?>" class="pag-btn pag-btn-text <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i> Prev</a>
        <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
          <a href="<?= $bu ?>page=<?= $i ?>" class="pag-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $bu ?>page=<?= $page+1 ?>" class="pag-btn pag-btn-text <?= $page>=$total_pages?'disabled':'' ?>">Next <i class="bi bi-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<footer style="background:var(--blue-deep);color:rgba(255,255,255,.6);text-align:center;padding:20px;font-size:12px;">
  &copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak
</footer>


<!-- MODAL CATAT PEMINJAMAN -->
<div class="modal-backdrop" id="modalAdd" onclick="handleBackdropClick(event,'modalAdd')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalAdd')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Catat Peminjaman</div>
    <div class="modal-sub">Isi data peminjaman sarana inventaris.</div>
    <div class="modal-error" id="addError"></div>
    <form method="POST" onsubmit="return validatePeminjaman()">
      <div style="display:flex;gap:14px;margin-bottom:14px;">
        <div class="form-group" style="margin-bottom:0;flex:1;min-width:0;">
          <label class="form-label">Peminjam <span>*</span></label>
          <input type="hidden" name="id_pengguna" id="addPeminjamId">
          <div class="ac-wrap">
            <div class="ac-input-row">
              <input type="text" id="addPeminjamText" class="ac-input"
                     placeholder="Ketik nama pengguna..." autocomplete="off">
              <button type="button" class="ac-clear" id="acClearBtn" onclick="clearPeminjam()"
                      title="Hapus pilihan"><i class="bi bi-x"></i></button>
            </div>
            <div class="ac-dropdown" id="acDropdown"></div>
            <div class="ac-selected-tag" id="acSelectedTag">
              <i class="bi bi-person-check"></i><span id="acSelectedName"></span>
            </div>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0;flex:1;min-width:0;" id="kelasGroup">
          <label class="form-label">Kelas</label>
          <input type="text" name="kelas" id="addKelas" class="form-control" placeholder="Contoh: XII IPA 1">
        </div>
      </div>
      <div class="form-group" style="margin-top:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
          <label class="form-label" style="margin-bottom:0;">Sarana yang Dipinjam <span>*</span></label>
          <button type="button" class="btn btn-xs btn-outline" style="padding:4px 8px;font-size:11px;" onclick="addBarangRow()"><i class="bi bi-plus-lg"></i> Tambah Barang</button>
        </div>
        <div id="barangContainerAdmin">
          <div class="barang-row" style="display:flex;gap:10px;margin-bottom:10px;align-items:flex-start;">
            <div style="flex:1;">
              <select name="id_barang[]" class="form-control barang-select" onchange="onBarangChangeAdmin(this)" required>
                <option value="">— Pilih Barang —</option>
                <?php foreach ($barang_list as $bv): ?>
                <option value="<?= $bv['id_barang'] ?>" data-jumlah="<?= $bv['jumlah'] ?>">
                  <?= htmlspecialchars($bv['nama_barang']) ?><?= $bv['kode_barang']?' ['.$bv['kode_barang'].']':'' ?> — Sisa: <?= $bv['jumlah'] ?>
                </option>
                <?php endforeach; ?>
              </select>
              <div class="form-hint barang-hint"></div>
            </div>
            <div style="width:80px;">
              <input type="number" name="jumlah[]" class="form-control" value="1" min="1" required>
            </div>
            <button type="button" class="btn btn-danger btn-remove-row" style="padding:10px 12px;border-radius:10px;display:none;" onclick="removeBarangRow(this)" title="Hapus"><i class="bi bi-trash"></i></button>
          </div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Tanggal Pinjam <span>*</span></label>
          <input type="date" name="tgl_pinjam" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>
      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Tujuan Peminjaman <span>*</span></label>
        <textarea name="tujuan" id="addTujuan" class="form-control" rows="2"
                  placeholder="Contoh: Presentasi pelajaran Biologi kelas XII IPA 1" style="resize:vertical;"></textarea>
      </div>
      <button type="submit" name="tambah_peminjaman" class="btn-modal-submit">
        <i class="bi bi-clipboard-plus"></i> Catat Peminjaman
      </button>
    </form>
  </div>
</div>

<!-- MODAL HAPUS -->
<div class="modal-backdrop" id="modalHapus" onclick="handleBackdropClick(event,'modalHapus')">
  <div class="modal-box modal-box-sm">
    <button class="modal-close" onclick="closeModal('modalHapus')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title" style="color:#DC2626;">Hapus Peminjaman</div>
    <div class="modal-sub">Hapus data peminjaman oleh <strong id="hapusPeminjam"></strong>?<br>Jika status masih <em>dipinjam</em>, jumlah akan dikembalikan otomatis.</div>
    <form method="POST">
      <input type="hidden" name="id_peminjaman" id="hapusId">
      <button type="submit" name="hapus_peminjaman" class="btn-modal-danger"><i class="bi bi-trash"></i> Ya, Hapus</button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalHapus')">Batal</button>
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
    if (id === 'modalAdd') document.getElementById('addError').classList.remove('show');
  }
  function handleBackdropClick(e, id) { if (e.target === document.getElementById(id)) closeModal(id); }
  document.addEventListener('keydown', e => { if (e.key==='Escape') ['modalAdd','modalHapus'].forEach(closeModal); });

  function openAddModal() {
    openModal('modalAdd');
    setTimeout(() => document.getElementById('addPeminjamText').focus(), 80);
  }
  function openHapusModal(id, nama) {
    document.getElementById('hapusId').value = id;
    document.getElementById('hapusPeminjam').textContent = nama;
    openModal('modalHapus');
  }

  function onBarangChangeAdmin(sel) {
    const opt    = sel.options[sel.selectedIndex];
    const jumlah = parseInt(opt.dataset.jumlah) || 0;
    const hint   = sel.parentElement.querySelector('.barang-hint');
    if (hint) {
      hint.textContent = sel.value ? `Tersedia: ${jumlah} unit` : '';
    }
  }

  function addBarangRow() {
    const container = document.getElementById('barangContainerAdmin');
    const firstRow = container.querySelector('.barang-row');
    const newRow = firstRow.cloneNode(true);
    
    // Reset values
    newRow.querySelector('select').value = '';
    newRow.querySelector('.barang-hint').textContent = '';
    newRow.querySelector('input[type="number"]').value = '1';
    newRow.querySelector('.btn-remove-row').style.display = 'block';
    
    container.appendChild(newRow);
    
    // Update first row delete button visibility
    const rows = container.querySelectorAll('.barang-row');
    if (rows.length > 1) {
      rows[0].querySelector('.btn-remove-row').style.display = 'block';
    }
  }

  function removeBarangRow(btn) {
    const container = document.getElementById('barangContainerAdmin');
    const rows = container.querySelectorAll('.barang-row');
    if (rows.length > 1) {
      btn.closest('.barang-row').remove();
    }
    // If only 1 row left, hide its delete button
    const updatedRows = container.querySelectorAll('.barang-row');
    if (updatedRows.length === 1) {
      updatedRows[0].querySelector('.btn-remove-row').style.display = 'none';
    }
  }

  /* ══ AUTOCOMPLETE PEMINJAM ══ */
  const penggunaData = <?= json_encode(array_map(fn($p) => [
    'id'   => $p['id_pengguna'],
    'nama' => $p['nama'],
    'role' => $p['role'],
  ], $pengguna_list)) ?>;

  let acSelectedRole = '';

  function initAutocomplete() {
    const input   = document.getElementById('addPeminjamText');
    const dropdown= document.getElementById('acDropdown');

    input.addEventListener('input', function() {
      const q = this.value.trim();
      // Reset pilihan saat user mengetik ulang
      document.getElementById('addPeminjamId').value = '';
      acSelectedRole = '';
      document.getElementById('acSelectedTag').classList.remove('show');
      document.getElementById('acClearBtn').classList.toggle('show', q.length > 0);
      updateKelasVisibility('');
      if (!q) { closeAcDropdown(); return; }
      const matches = penggunaData.filter(p => p.nama.toLowerCase().includes(q.toLowerCase()));
      renderAcDropdown(matches, q);
    });

    input.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeAcDropdown();
    });

    document.addEventListener('click', function(e) {
      if (!input.closest('.ac-wrap').contains(e.target)) closeAcDropdown();
    });
  }

  function renderAcDropdown(matches, q) {
    const dd = document.getElementById('acDropdown');
    if (!matches.length) {
      dd.innerHTML = '<div class="ac-empty"><i class="bi bi-person-x"></i> Pengguna tidak ditemukan</div>';
      dd.classList.add('open'); return;
    }
    const roleLabel = {murid:'Murid', guru:'Guru', tendik:'Tendik', admin:'Admin'};
    dd.innerHTML = matches.slice(0, 8).map(p => {
      const hl = p.nama.replace(new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'), 'gi'),
                                m => `<strong>${m}</strong>`);
      return `<div class="ac-item" onmousedown="selectPengguna('${p.id}','${p.nama.replace(/'/g,"\\'")}','${p.role}')">
                <span>${hl}</span>
                <span class="ac-role ${p.role}">${roleLabel[p.role]||p.role}</span>
              </div>`;
    }).join('');
    dd.classList.add('open');
  }

  function selectPengguna(id, nama, role) {
    document.getElementById('addPeminjamText').value  = nama;
    document.getElementById('addPeminjamId').value    = id;
    document.getElementById('acSelectedName').textContent = nama;
    document.getElementById('acSelectedTag').classList.add('show');
    document.getElementById('acClearBtn').classList.add('show');
    acSelectedRole = role;
    updateKelasVisibility(role);
    closeAcDropdown();
  }

  function clearPeminjam() {
    document.getElementById('addPeminjamText').value = '';
    document.getElementById('addPeminjamId').value   = '';
    document.getElementById('acSelectedTag').classList.remove('show');
    document.getElementById('acClearBtn').classList.remove('show');
    acSelectedRole = '';
    updateKelasVisibility('');
    closeAcDropdown();
    document.getElementById('addPeminjamText').focus();
  }

  function closeAcDropdown() {
    document.getElementById('acDropdown').classList.remove('open');
  }

  function updateKelasVisibility(role) {
    // Tampil jika: belum ada pilihan (kosong) ATAU yang dipilih adalah murid
    // Sembunyikan jika: dipilih pengguna dengan role selain murid
    const hide = role && role !== 'murid';
    const kelasGroup = document.getElementById('kelasGroup');
    kelasGroup.style.display = hide ? 'none' : '';
    if (hide) document.getElementById('addKelas').value = '';
  }

  function validatePeminjaman() {
    const err = document.getElementById('addError');
    const txt = document.getElementById('addPeminjamText').value.trim();
    const pid = document.getElementById('addPeminjamId').value;
    const tj  = document.getElementById('addTujuan').value.trim();
    
    // Cek barang
    const barangSelects = document.querySelectorAll('.barang-select');
    let hasBarang = false;
    let allBarangFilled = true;
    barangSelects.forEach(sel => {
      if (sel.value) hasBarang = true;
      else allBarangFilled = false;
    });

    if (!txt) { showErr(err, 'Nama peminjam wajib diisi.'); return false; }
    if (!pid) { showErr(err, 'Pilih peminjam dari daftar pengguna terdaftar.'); return false; }
    if (!hasBarang || !allBarangFilled) { showErr(err, 'Pilih sarana yang dipinjam pada semua baris.'); return false; }
    if (!tj)  { showErr(err, 'Tujuan peminjaman wajib diisi.'); return false; }
    err.classList.remove('show'); return true;
  }

  initAutocomplete();
  initTableControls('searchFormPeminjaman','searchInputPeminjaman');

  function showErr(el, msg) { el.textContent=msg; el.classList.add('show'); el.scrollIntoView({behavior:'smooth',block:'nearest'}); }

  /* ── BFCache Fix: paksa reload jika halaman dari back-forward cache ── */
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
  });
</script>
</body>
</html>