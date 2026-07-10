<?php
session_start();
require_once "../config/koneksi.php";
require_once "../config/auth_admin.php";

$msg_success = '';
$msg_error   = '';

/* ══════════════════════════════════════════
   POST: CATAT PENGEMBALIAN
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['catat_pengembalian'])) {
        $id_pm        = (int)$_POST['id_peminjaman'];
        $tgl_kembali  = $_POST['tgl_kembali'];
        $kondisi      = in_array($_POST['kondisi_kembali'],['baik','rusak_ringan','rusak_berat'])
                        ? $_POST['kondisi_kembali'] : 'baik';

        if (empty($tgl_kembali)) {
            $msg_error = "Tanggal pengembalian wajib diisi.";
        } else {
            $pm = mysqli_fetch_assoc(mysqli_query($conn, "
                SELECT pm.*, pg.nama AS nama_peminjam
                FROM peminjaman pm
                JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
                WHERE pm.id_peminjaman=$id_pm AND pm.status IN ('dipinjam','menunggu_kembali')
            "));

            if (!$pm) {
                $msg_error = "Data peminjaman tidak ditemukan atau sudah dikembalikan.";
            } else {
                // Update peminjaman: status, tgl_kembali_aktual, waktu_kembali, kondisi_kembali
                $now_waktu_kembali = date('H:i:s');
                mysqli_query($conn, "
                    UPDATE peminjaman
                    SET status='dikembalikan',
                        tgl_kembali_aktual='$tgl_kembali',
                        waktu_kembali='$now_waktu_kembali',
                        kondisi_kembali='$kondisi'
                    WHERE id_peminjaman=$id_pm
                ");

                // Kembalikan jumlah barang dari detail_peminjaman
                $det = mysqli_query($conn,
                    "SELECT dp.id_barang, dp.jumlah
                     FROM detail_peminjaman dp
                     WHERE dp.id_peminjaman=$id_pm");

                // Ambil detail barang yang dipinjam
                $det_rows = [];
                while ($d = mysqli_fetch_assoc($det)) {
                    $det_rows[] = $d;
                }

                // Kembalikan jumlah barang ke stok (sinkronkan laik/tidak laik)
                foreach ($det_rows as $d) {
                    if ($kondisi === 'rusak_berat') {
                        // Rusak Berat -> Tidak Laik
                        mysqli_query($conn, "
                            UPDATE barang
                            SET jumlah = jumlah + {$d['jumlah']},
                                jumlah_tidak_laik = jumlah_tidak_laik + {$d['jumlah']}
                            WHERE id_barang = {$d['id_barang']}
                        ");
                    } else {
                        // Baik & Rusak Ringan -> Laik
                        mysqli_query($conn, "
                            UPDATE barang
                            SET jumlah = jumlah + {$d['jumlah']},
                                jumlah_laik = jumlah_laik + {$d['jumlah']}
                            WHERE id_barang = {$d['id_barang']}
                        ");
                    }
                }

                header("Location: pengembalian.php?success=".urlencode("Pengembalian berhasil dicatat."));
                exit;
            }
        }
    }
}

if (isset($_GET['success'])) $msg_success = htmlspecialchars($_GET['success']);

/* ── Hitung badge status ── */
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengguna WHERE status='pending'"))['t'];
$pm_menunggu = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM peminjaman WHERE status='menunggu'"))['t'];

/* ── Pre-fill dari link "Catat Kembali" di peminjaman.php ── */
$prefill_id = isset($_GET['catat']) ? (int)$_GET['catat'] : 0;
$prefill_pm = null;
if ($prefill_id) {
    $prefill_pm = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT pm.*,
               pg.nama AS nama_peminjam,
               pg.role AS role_peminjam,
               GROUP_CONCAT(b.nama_barang ORDER BY b.nama_barang SEPARATOR ', ') AS nama_barang_list,
               GROUP_CONCAT(dp.jumlah     ORDER BY b.nama_barang SEPARATOR ', ') AS jumlah_list
        FROM peminjaman pm
        LEFT JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
        LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
        LEFT JOIN barang b ON dp.id_barang=b.id_barang
        WHERE pm.id_peminjaman=$prefill_id AND pm.status='dipinjam'
        GROUP BY pm.id_peminjaman
    "));
}

/* ── Filter & Search riwayat ── */
$filter_kondisi  = isset($_GET['kondisi']) && in_array($_GET['kondisi'],['baik','rusak_ringan','rusak_berat']) ? $_GET['kondisi'] : '';
$search          = isset($_GET['q']) ? trim(mysqli_real_escape_string($conn, $_GET['q'])) : '';
$filter_tgl_dari = $_GET['dari'] ?? '';
$filter_tgl_ke   = $_GET['ke']   ?? '';

$valid_per_page = [5,10,20,25,50,100];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'],$valid_per_page) ? (int)$_GET['per_page'] : 5;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where = "WHERE pm.status='dikembalikan'";
if ($search)          $where .= " AND (pg.nama LIKE '%$search%' OR b.nama_barang LIKE '%$search%' OR pm.kelas LIKE '%$search%')";
if ($filter_kondisi)  $where .= " AND pm.kondisi_kembali='$filter_kondisi'";
if ($filter_tgl_dari) $where .= " AND pm.tgl_kembali_aktual >= '".mysqli_real_escape_string($conn,$filter_tgl_dari)."'";
if ($filter_tgl_ke)   $where .= " AND pm.tgl_kembali_aktual <= '".mysqli_real_escape_string($conn,$filter_tgl_ke)."'";

$total = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT pm.id_peminjaman) as t
    FROM peminjaman pm
    JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
    LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
    LEFT JOIN barang b ON dp.id_barang=b.id_barang
    $where"))['t'];

$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$kembali_q = mysqli_query($conn, "
    SELECT pm.*,
           pg.nama AS nama_peminjam,
           pg.role AS role_peminjam,
           GROUP_CONCAT(b.nama_barang ORDER BY b.nama_barang SEPARATOR ', ') AS nama_barang_list,
           GROUP_CONCAT(dp.jumlah     ORDER BY b.nama_barang SEPARATOR ', ') AS jumlah_list
    FROM peminjaman pm
    LEFT JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
    LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
    LEFT JOIN barang b ON dp.id_barang=b.id_barang
    $where
    GROUP BY pm.id_peminjaman
    ORDER BY pm.tgl_kembali_aktual DESC, pm.id_peminjaman DESC
    LIMIT $per_page OFFSET $offset
");

/* ── Daftar yang masih dipinjam atau menunggu verifikasi kembali ── */
$aktif_q = mysqli_query($conn, "
    SELECT pm.*,
           pg.nama AS nama_peminjam,
           pg.role AS role_peminjam,
           GROUP_CONCAT(b.nama_barang ORDER BY b.nama_barang SEPARATOR ', ') AS nama_barang_list,
           GROUP_CONCAT(dp.jumlah     ORDER BY b.nama_barang SEPARATOR ', ') AS jumlah_list
    FROM peminjaman pm
    LEFT JOIN pengguna pg ON pm.id_pengguna=pg.id_pengguna
    LEFT JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
    LEFT JOIN barang b ON dp.id_barang=b.id_barang
    WHERE pm.status IN ('dipinjam','menunggu_kembali')
    GROUP BY pm.id_peminjaman
    ORDER BY FIELD(pm.status,'menunggu_kembali','dipinjam') ASC, pm.tanggal_pinjam ASC
");
$aktif_list = [];
while ($av = mysqli_fetch_assoc($aktif_q)) $aktif_list[] = $av;

/* ── Stats ── */
$total_aktif       = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as t FROM peminjaman WHERE status IN ('dipinjam','menunggu_kembali')"))['t'];
$total_kembali_all = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as t FROM peminjaman WHERE status='dikembalikan'"))['t'];
$menunggu_verif    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as t FROM peminjaman WHERE status='menunggu_kembali'"))['t'];
$terlambat_count   = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman
     WHERE status IN ('dipinjam','menunggu_kembali') AND waktu_selesai IS NOT NULL
       AND CONCAT(tanggal_pinjam,' ',waktu_selesai) < NOW()"))['t'];

$kondisi_cfg = [
    'baik'         => ['Baik',         'badge-success'],
    'rusak_ringan' => ['Rusak Ringan',  'badge-warning'],
    'rusak_berat'  => ['Rusak Berat',  'badge-danger'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengembalian — Inventaris SARPRAS</title>
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

    /* AKTIF SECTION */
    .aktif-section{background:var(--card);border:1.5px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:24px;box-shadow:var(--shadow);}
    .aktif-section-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;margin-bottom:14px;}
    .aktif-count-badge{background:var(--blue-dark);color:white;border-radius:20px;padding:2px 9px;font-size:11px;font-weight:800;}
    .terlambat-badge{background:#DC2626;color:white;border-radius:20px;padding:2px 9px;font-size:11px;font-weight:800;}
    .aktif-item{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border);gap:14px;flex-wrap:wrap;}
    .aktif-item:last-child{border-bottom:none;padding-bottom:0;}
    .aktif-item.overdue{background:#FEF2F2;border-radius:10px;padding:10px 12px;margin:2px -12px;}
    .aktif-item-info{flex:1;min-width:0;}
    .aktif-item-name{font-weight:700;font-size:14px;}
    .aktif-item-meta{font-size:12px;color:var(--muted);margin-top:3px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
    .overdue-tag{color:#DC2626;font-weight:700;}
    .aktif-empty{text-align:center;padding:24px;color:var(--muted);font-size:13px;}
    .aktif-empty i{font-size:28px;display:block;margin-bottom:8px;color:#B0C8E0;}
    /* TOOLBAR */
    .toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
    .search-wrap-inner{position:relative;flex:1;min-width:180px;}
    .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:14px;pointer-events:none;}
    .search-input{width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border);border-right:none;border-radius:9px 0 0 9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);outline:none;}
    .search-input:focus{border-color:var(--blue);}
    .btn-search{padding:10px 16px;background:var(--blue-dark);color:white;border:none;border-radius:0 9px 9px 0;cursor:pointer;font-size:14px;}
    .filter-select,.filter-date{padding:10px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--card);outline:none;}
    /* CARD & TABLE */
    .card{background:var(--card);border-radius:14px;border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;}
    .card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;}
    .card-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;color:var(--text);display:flex;align-items:center;gap:8px;}
    .card-title i{color:var(--green);}
    .table-wrap{overflow-x:auto;}
    .inv-table{width:100%;border-collapse:collapse;font-size:13.5px;}
    .inv-table thead th{background:#F4F8FD;padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.5px;text-transform:uppercase;border-bottom:2px solid var(--border);white-space:nowrap;}
    .inv-table tbody tr{border-bottom:1px solid var(--border);transition:background .12s;}
    .inv-table tbody tr:last-child{border-bottom:none;}
    .inv-table tbody tr:hover{background:#F4F8FD;}
    .inv-table td{padding:12px 14px;color:var(--text);vertical-align:middle;}
    /* BADGES */
    .badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
    .badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;opacity:.7;}
    .badge-success{background:#F0FDF4;color:#15803D;}
    .badge-warning{background:#FFFBEB;color:#D97706;}
    .badge-danger{background:#FEF2F2;color:#DC2626;}
    .badge-muted{background:#F1F5F9;color:#64748B;}
    /* BUTTONS */
    .btn{display:inline-flex;align-items:center;gap:5px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .2s;font-family:'DM Sans',sans-serif;}
    .btn-sm{padding:6px 10px;font-size:12px;border-radius:7px;}
    .btn-xs{padding:4px 8px;font-size:11px;border-radius:6px;}
    .btn-primary{background:var(--blue-dark);color:white;}
    .btn-primary:hover{background:var(--blue-deep);}
    .btn-secondary{background:var(--card);color:var(--text);border:1px solid var(--border);}
    .btn-secondary:hover{background:var(--bg);}
    .btn-success{background:#F0FDF4;color:#16A34A;border:1px solid #BBF7D0;}
    .btn-success:hover{background:#16A34A;color:white;}
    /* PAGINATION */
    .table-footer{display:flex;align-items:center;justify-content:space-between;padding:13px 20px;border-top:1px solid var(--border);font-size:13px;color:var(--muted);flex-wrap:wrap;gap:10px;}
    .pag-btns{display:flex;align-items:center;gap:6px;}
    .pag-btn{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;background:var(--card);color:var(--text);text-decoration:none;transition:all .2s;}
    .pag-btn:hover:not(.disabled):not(.active){background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.active{background:var(--blue-dark);color:white;border-color:var(--blue-dark);}
    .pag-btn.disabled{opacity:.35;cursor:not-allowed;pointer-events:none;}
    .pag-btn-text{padding:0 12px;width:auto;}
    /* EMPTY */
    .empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
    .empty-state i{font-size:40px;display:block;margin-bottom:12px;color:#B0C8E0;}
    .empty-state h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px;}
    /* MOBILE */
    .mobile-list{display:none;}
    .mobile-item{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:10px;box-shadow:var(--shadow);}
    .mobile-item-meta{display:flex;flex-wrap:wrap;gap:7px;align-items:center;margin-top:6px;}
    /* MODAL */
    .modal-backdrop{display:none;position:fixed;inset:0;z-index:500;background:rgba(27,63,110,.35);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
    .modal-backdrop.open{display:flex;}
    .modal-box{background:var(--card);border-radius:18px;padding:32px;width:100%;max-width:500px;box-shadow:var(--shadow-lg);position:relative;z-index:501;animation:modalIn .22s cubic-bezier(.4,0,.2,1);max-height:92vh;overflow-y:auto;}
    @keyframes modalIn{from{transform:scale(.94) translateY(10px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
    .modal-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--green);margin-bottom:6px;}
    .modal-sub{font-size:13px;color:var(--muted);margin-bottom:16px;}
    .modal-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:8px;background:var(--bg);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px;transition:all .2s;}
    .modal-close:hover{background:#FECACA;color:#DC2626;}
    .modal-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;border-radius:8px;padding:9px 12px;font-size:12px;margin-bottom:14px;display:none;}
    .modal-error.show{display:block;}
    .pinjam-summary{background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:18px;}
    .pinjam-summary-title{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;}
    .pinjam-summary-row{display:flex;align-items:flex-start;gap:8px;margin-bottom:6px;font-size:13px;}
    .pinjam-summary-row:last-child{margin-bottom:0;}
    .pinjam-summary-row i{color:var(--blue);width:16px;text-align:center;flex-shrink:0;margin-top:2px;}
    .pinjam-summary-row span{color:var(--muted);flex-shrink:0;}
    .pinjam-summary-row strong{color:var(--text);}
    .overdue-box{background:#FEF2F2;border:1.5px solid #FECACA;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#DC2626;display:flex;align-items:flex-start;gap:8px;line-height:1.5;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
    .form-group{margin-bottom:14px;}
    .form-label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;}
    .form-label span{color:#DC2626;}
    .form-control{width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
    .form-control:focus{border-color:var(--blue);background:white;box-shadow:0 0 0 3px rgba(74,144,196,.14);}
    .btn-modal-submit{width:100%;padding:12px;background:var(--green);color:white;border:none;border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 20px rgba(61,155,74,.28);transition:all .2s;margin-top:4px;}
    .btn-modal-submit:hover{background:#2E7A38;}
    .btn-modal-cancel{width:100%;padding:11px;background:var(--bg);color:var(--muted);border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;margin-top:8px;}
    .btn-modal-cancel:hover{background:#E5E7EB;}

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


    /* Desktop: date-filter-row transparan, isinya tetap tampil inline seperti aslinya */
    .date-filter-row{display:contents;}
    .date-filter-group{display:contents;}
    .date-filter-label{display:inline-block;font-size:12px;font-weight:600;color:var(--muted);margin-right:4px;}

    @media(max-width:768px){
      .navbar{position:relative;}.nav-links{display:none;}
      .nav-hamburger{display:flex;align-items:center;justify-content:center;}.nav-brand{margin-right:0;}
      .page-wrapper{padding:20px 14px 80px;}.page-title{font-size:20px;}
      .page-header{flex-direction:column;align-items:stretch;gap:12px;}.page-header .btn{justify-content:center;}

      /* Toolbar: kolom */
      .toolbar{flex-direction:column;gap:8px;}

      /* Search: full width */
      #searchFormPengembalian{width:100%;}
      .search-wrap-inner{width:100%;min-width:unset;}
      .filter-select{width:100%;}

      /* Filter tanggal: DARI & SAMPAI berdampingan satu baris */
      .date-filter-row{display:flex;gap:8px;width:100%;}
      .date-filter-group{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px;}
      .date-filter-group .date-filter-label{font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);}
      .date-filter-group .filter-date{width:100%;box-sizing:border-box;}

      .table-wrap{display:none;}.mobile-list{display:block;}
      .card-header{padding:14px 16px;}
      .table-footer{flex-direction:column;align-items:flex-start;gap:10px;padding:12px 16px;}
      .pag-btns{width:100%;justify-content:center;}
      .modal-box{margin:12px;padding:22px 18px;border-radius:16px;max-width:100%;}
      .form-row{grid-template-columns:1fr;gap:0;}
      .aktif-item{flex-direction:column;align-items:flex-start;gap:8px;}
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
    <a href="peminjaman.php"   class="nav-link">
      Peminjaman
      <?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?>
    </a>
    <a href="pengembalian.php" class="nav-link active">Pengembalian</a>
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
  <a href="peminjaman.php"     class="nav-link">
    Peminjaman
    <?php if ($pm_menunggu > 0): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?>
  </a>
  <a href="pengembalian.php"   class="nav-link active">Pengembalian</a>
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
      <div class="page-title">Pengembalian</div>
      <div class="page-sub">Catat pengembalian dan lihat riwayat peminjaman</div>
    </div>
    <?php if (!empty($aktif_list)): ?>
    <button class="btn btn-primary" onclick="openReturnModal(null)">
      <i class="bi bi-arrow-return-left"></i> Catat Pengembalian
    </button>
    <?php endif; ?>
  </div>



  <!-- Aktif Section -->
  <div class="aktif-section">
    <div class="aktif-section-title">
      <i class="bi bi-box-arrow-up-right" style="color:var(--blue-dark);"></i>
      Sedang Dipinjam &amp; Pengajuan Kembali
      <span class="aktif-count-badge"><?= count($aktif_list) ?></span>
      <?php if ($menunggu_verif > 0): ?>
        <span style="background:#E67E22;color:white;border-radius:20px;padding:2px 9px;font-size:11px;font-weight:800;"><i class="bi bi-envelope-fill"></i> <?= $menunggu_verif ?> Menunggu Verifikasi</span>
      <?php endif; ?>
      <?php if ($terlambat_count > 0): ?>
        <span class="terlambat-badge"><i class="bi bi-exclamation-triangle-fill"></i> <?= $terlambat_count ?> Terlambat</span>
      <?php endif; ?>
    </div>

    <?php if (empty($aktif_list)): ?>
    <div class="aktif-empty"><i class="bi bi-inbox"></i>Tidak ada sarana yang sedang dipinjam.</div>
    <?php else: foreach ($aktif_list as $av):
      $is_overdue        = ($av['waktu_selesai'] && time() > strtotime($av['tanggal_pinjam'].' '.$av['waktu_selesai']));
      $is_user_requested = ($av['status'] === 'menunggu_kembali');
      $pm_data = json_encode([
        'id'           => $av['id_peminjaman'],
        'nama'         => $av['nama_peminjam'],
        'barang'       => $av['nama_barang_list'],
        'jumlah'       => $av['jumlah_list'],
        'tgl_pinjam'   => $av['tanggal_pinjam'],
        'waktu_mulai'  => $av['waktu_mulai'],
        'waktu_selesai'=> $av['waktu_selesai'],
        'kelas'        => $av['kelas'],
        'tujuan'       => $av['tujuan'],
        'is_overdue'   => $is_overdue,
      ]);
    ?>
    <div class="aktif-item <?= $is_overdue?'overdue':'' ?>" <?= $is_user_requested?'style="background:#FFF8F0;border:1.5px solid #FDBA74;border-radius:10px;padding:10px 12px;margin:2px -12px;"':'' ?>>
      <div class="aktif-item-info">
        <div class="aktif-item-name">
          <?= htmlspecialchars($av['nama_barang_list']??'') ?>
          <?php if ($is_user_requested): ?>
            <span style="display:inline-flex;align-items:center;gap:4px;background:#E67E22;color:white;border-radius:12px;padding:2px 8px;font-size:10px;font-weight:800;margin-left:6px;"><i class="bi bi-arrow-return-left" style="font-size:9px;"></i> Pengajuan Kembali</span>
          <?php endif; ?>
        </div>
        <div class="aktif-item-meta">
          <span><i class="bi bi-person" style="font-size:11px;"></i> <?= htmlspecialchars($av['nama_peminjam']) ?></span>
          <?php if ($av['kelas']): ?><span>— <strong><?= htmlspecialchars($av['kelas']) ?></strong></span><?php endif; ?>
          <span><i class="bi bi-calendar3" style="font-size:11px;"></i> <?= $av['tanggal_pinjam']?date('d M Y',strtotime($av['tanggal_pinjam'])):'—' ?></span>
          <?php if ($av['waktu_mulai']&&$av['waktu_selesai']): ?>
            <span><i class="bi bi-clock" style="font-size:11px;"></i> <?= substr($av['waktu_mulai'],0,5) ?>–<?= substr($av['waktu_selesai'],0,5) ?></span>
          <?php endif; ?>
          <?php if ($is_overdue): ?><span class="overdue-tag"><i class="bi bi-exclamation-triangle-fill"></i> TERLAMBAT!</span><?php endif; ?>
          <?php if ($is_user_requested && $av['tanggal_kembali']): ?>
            <span style="color:#E67E22;font-weight:700;"><i class="bi bi-calendar-check" style="font-size:11px;"></i> Diajukan: <?= date('d M Y',strtotime($av['tanggal_kembali'])) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <button class="btn btn-sm btn-success" onclick="openReturnModal(<?= htmlspecialchars($pm_data) ?>)">
        <i class="bi bi-<?= $is_user_requested ? 'check-circle' : 'arrow-return-left' ?>"></i>
        <?= $is_user_requested ? 'Verifikasi & Konfirmasi' : 'Catat Kembali' ?>
      </button>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Filter Riwayat -->
  <div class="toolbar">
    <form method="GET" id="searchFormPengembalian" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;align-items:center;">
      <div class="search-wrap-inner" style="min-width:200px;">
        <i class="bi bi-search search-icon"></i>
        <input type="text" name="q" id="searchInputPengembalian" class="search-input" placeholder="Cari nama, barang, kelas..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-search" style="position:absolute;right:0;top:0;bottom:0;border-radius:0 9px 9px 0;"><i class="bi bi-search"></i></button>
      </div>
      <select name="kondisi" class="filter-select" onchange="this.form.submit()">
        <option value="">Semua Kondisi</option>
        <option value="baik"         <?= $filter_kondisi==='baik'?'selected':'' ?>>Baik</option>
        <option value="rusak_ringan" <?= $filter_kondisi==='rusak_ringan'?'selected':'' ?>>Rusak Ringan</option>
        <option value="rusak_berat"  <?= $filter_kondisi==='rusak_berat'?'selected':'' ?>>Rusak Berat</option>
      </select>
      <div class="date-filter-row">
        <div class="date-filter-group">
          <span class="date-filter-label">Dari</span>
          <input type="date" name="dari" class="filter-date" title="Dari tanggal" value="<?= htmlspecialchars($filter_tgl_dari) ?>" onchange="this.form.submit()">
        </div>
        <div class="date-filter-group">
          <span class="date-filter-label">Sampai</span>
          <input type="date" name="ke" class="filter-date" title="Sampai tanggal" value="<?= htmlspecialchars($filter_tgl_ke) ?>" onchange="this.form.submit()">
        </div>
      </div>
    </form>
    <?php if ($search||$filter_kondisi||$filter_tgl_dari||$filter_tgl_ke): ?>
    <a href="pengembalian.php" class="btn btn-secondary btn-sm"><i class="bi bi-x"></i> Reset</a>
    <?php endif; ?>
    <form method="GET" style="display:flex;align-items:center;">
      <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
      <?php if ($filter_kondisi): ?><input type="hidden" name="kondisi" value="<?= $filter_kondisi ?>"><?php endif; ?>
      <?php if ($filter_tgl_dari): ?><input type="hidden" name="dari" value="<?= htmlspecialchars($filter_tgl_dari) ?>"><?php endif; ?>
      <?php if ($filter_tgl_ke): ?><input type="hidden" name="ke" value="<?= htmlspecialchars($filter_tgl_ke) ?>"><?php endif; ?>
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

  <!-- Riwayat -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-clock-history"></i> Riwayat Pengembalian</div>
      <span style="font-size:12px;color:var(--muted);"><?= number_format($total) ?> data</span>
    </div>

    <div class="table-wrap">
      <table class="inv-table">
        <thead>
          <tr>
            <th style="width:44px;">No</th>
            <th>Peminjam</th>
            <th>Sarana</th>
            <th>Tgl Pinjam</th>
            <th>Tgl Kembali</th>
            <th style="width:120px;">Kondisi</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no = $offset+1; $has = false;
          while ($km = mysqli_fetch_assoc($kembali_q)):
            $has = true;
            $kc  = $kondisi_cfg[$km['kondisi_kembali']] ?? ['—','badge-muted'];
          ?>
          <tr>
            <td style="color:var(--muted);font-size:12px;"><?= $no++ ?></td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($km['nama_peminjam']) ?></div>
              <div style="font-size:11px;color:var(--muted);">
                <?= ucfirst($km['role_peminjam']) ?>
                <?php if ($km['kelas']): ?>&bull; <strong style="color:var(--blue-dark);"><?= htmlspecialchars($km['kelas']) ?></strong><?php endif; ?>
              </div>
            </td>
            <td>
              <?php
              $b_arr = explode(', ', $km['nama_barang_list']??'');
              $j_arr = explode(', ', $km['jumlah_list']??'');
              foreach ($b_arr as $i => $nb): ?>
                <div style="font-size:13px;"><span style="font-weight:600;"><?= htmlspecialchars($nb) ?></span><span style="color:var(--muted);"> ×<?= $j_arr[$i]??1 ?></span></div>
              <?php endforeach; ?>
              <?php if ($km['tujuan']): ?><div style="font-size:11px;color:var(--muted);font-style:italic;"><?= htmlspecialchars(mb_strimwidth($km['tujuan'],0,50,'...')) ?></div><?php endif; ?>
            </td>
            <td style="font-size:13px;">
              <div style="font-weight:600;"><?= $km['tanggal_pinjam']?date('d M Y',strtotime($km['tanggal_pinjam'])):'—' ?></div>
              <?php if ($km['waktu_mulai']&&$km['waktu_selesai']): ?><div style="font-size:11px;color:var(--muted);"><?= substr($km['waktu_mulai'],0,5) ?>–<?= substr($km['waktu_selesai'],0,5) ?></div><?php endif; ?>
            </td>
            <td style="font-size:13px;">
              <div style="font-weight:600;"><?= $km['tgl_kembali_aktual']?date('d M Y',strtotime($km['tgl_kembali_aktual'])):'—' ?></div>
              <?php if ($km['waktu_kembali']): ?><div style="font-size:11px;color:var(--muted);"><i class="bi bi-clock"></i> Kembali: <?= substr($km['waktu_kembali'],0,5) ?></div><?php endif; ?>
            </td>
            <td><span class="badge <?= $kc[1] ?>"><?= $kc[0] ?></span></td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$has): ?>
          <tr><td colspan="6"><div class="empty-state"><i class="bi bi-clock-history"></i><h3><?= $search?'Tidak ditemukan':'Belum ada riwayat' ?></h3><p><?= $search?'Tidak ada data yang cocok.':'Riwayat pengembalian akan muncul di sini.' ?></p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile List -->
    <div class="mobile-list">
      <?php
      $km_q2 = mysqli_query($conn,"
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
          ORDER BY pm.tgl_kembali_aktual DESC, pm.id_peminjaman DESC LIMIT $per_page OFFSET $offset");
      while ($km2 = mysqli_fetch_assoc($km_q2)):
        $kc2 = $kondisi_cfg[$km2['kondisi_kembali']] ?? ['—','badge-muted'];
      ?>
      <div class="mobile-item">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
          <div>
            <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($km2['nama_barang_list']??'') ?></div>
            <div style="font-size:12px;color:var(--blue-dark);margin-top:2px;"><?= htmlspecialchars($km2['nama_peminjam']) ?><?php if ($km2['kelas']): ?> — <strong><?= htmlspecialchars($km2['kelas']) ?></strong><?php endif; ?></div>
          </div>
          <span class="badge <?= $kc2[1] ?>"><?= $kc2[0] ?></span>
        </div>
        <div class="mobile-item-meta">
          <span style="font-size:12px;color:var(--muted);"><i class="bi bi-calendar3"></i> Pinjam: <?= $km2['tanggal_pinjam']?date('d M Y',strtotime($km2['tanggal_pinjam'])):'—' ?></span>
          <?php if ($km2['tgl_kembali_aktual']): ?>
            <span style="font-size:12px;color:#16A34A;font-weight:600;"><i class="bi bi-check-circle"></i> Kembali: <?= date('d M Y',strtotime($km2['tgl_kembali_aktual'])) ?></span>
            <?php if ($km2['waktu_kembali']): ?><span style="font-size:12px;color:#16A34A;"><i class="bi bi-clock"></i> <?= substr($km2['waktu_kembali'],0,5) ?></span><?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <?php if ($total > 0): ?>
    <div class="table-footer">
      <span>Menampilkan <?= $offset+1 ?>–<?= min($offset+$per_page,$total) ?> dari <?= number_format($total) ?> data</span>
      <div class="pag-btns">
        <?php $bu='?'.($search?'q='.urlencode($search).'&':'').($filter_kondisi?'kondisi='.$filter_kondisi.'&':'').($filter_tgl_dari?'dari='.$filter_tgl_dari.'&':'').($filter_tgl_ke?'ke='.$filter_tgl_ke.'&':'').($per_page!=5?'per_page='.$per_page.'&':''); ?>
        <a href="<?= $bu ?>page=<?= $page-1 ?>" class="pag-btn pag-btn-text <?= $page<=1?'disabled':'' ?>"><i class="bi bi-chevron-left"></i> Prev</a>
        <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?><a href="<?= $bu ?>page=<?= $i ?>" class="pag-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
        <a href="<?= $bu ?>page=<?= $page+1 ?>" class="pag-btn pag-btn-text <?= $page>=$total_pages?'disabled':'' ?>">Next <i class="bi bi-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<footer style="background:var(--blue-deep);color:rgba(255,255,255,.6);text-align:center;padding:20px;font-size:12px;">
  &copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak
</footer>


<!-- MODAL CATAT PENGEMBALIAN -->
<div class="modal-backdrop" id="modalReturn" onclick="handleBackdropClick(event,'modalReturn')">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('modalReturn')"><i class="bi bi-x-lg"></i></button>
    <div class="modal-title">Catat Pengembalian</div>
    <div class="modal-sub">Konfirmasi dan catat pengembalian sarana.</div>
    <div class="modal-error" id="returnError"></div>

    <!-- Summary -->
    <div class="pinjam-summary" id="pinjamSummary">
      <div class="pinjam-summary-title">Detail Peminjaman</div>
      <div class="pinjam-summary-row"><i class="bi bi-person"></i><span>Peminjam:</span>&nbsp;<strong id="sumPeminjam">—</strong></div>
      <div class="pinjam-summary-row"><i class="bi bi-box-seam"></i><span>Sarana:</span>&nbsp;<strong id="sumBarang">—</strong></div>
      <div class="pinjam-summary-row"><i class="bi bi-calendar3"></i><span>Tgl Pinjam:</span>&nbsp;<strong id="sumTgl">—</strong></div>
      <div class="pinjam-summary-row" id="sumWaktuRow" style="display:none;"><i class="bi bi-clock"></i><span>Waktu:</span>&nbsp;<strong id="sumWaktu">—</strong></div>
      <div class="pinjam-summary-row"><i class="bi bi-chat-text"></i><span>Tujuan:</span>&nbsp;<strong id="sumTujuan">—</strong></div>
    </div>

    <div class="overdue-box" id="overdueBox" style="display:none;">
      <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;"></i>
      <div><strong>Pengembalian Terlambat!</strong><br>Sarana ini melewati batas waktu yang ditentukan.</div>
    </div>

    <form method="POST" id="formReturn" onsubmit="return validateReturn()">
      <input type="hidden" name="id_peminjaman" id="returnId">

      <!-- Selector (muncul kalau dibuka tanpa prefill) -->
      <div class="form-group" id="pilihPeminjamanGroup" style="display:none;">
        <label class="form-label">Pilih Peminjaman <span>*</span></label>
        <select id="returnSelectPm" class="form-control" onchange="onSelectPm(this)">
          <option value="">— Pilih Peminjaman Aktif —</option>
          <?php foreach ($aktif_list as $av2):
            $is_od2 = ($av2['waktu_selesai'] && time()>strtotime($av2['tanggal_pinjam'].' '.$av2['waktu_selesai']));
            $od2 = json_encode([
              'id'         => $av2['id_peminjaman'],
              'nama'       => $av2['nama_peminjam'],
              'barang'     => $av2['nama_barang_list'],
              'jumlah'     => $av2['jumlah_list'],
              'tgl_pinjam' => $av2['tanggal_pinjam'],
              'waktu_mulai'=> $av2['waktu_mulai'],
              'waktu_selesai'=> $av2['waktu_selesai'],
              'kelas'      => $av2['kelas'],
              'tujuan'     => $av2['tujuan'],
              'is_overdue' => $is_od2,
            ]);
          ?>
          <option value="<?= $av2['id_peminjaman'] ?>" data-pm="<?= htmlspecialchars($od2) ?>"
                  <?= $is_od2 ? 'style="color:#DC2626;"' : '' ?>>
            <?= htmlspecialchars($av2['nama_peminjam']) ?> — <?= htmlspecialchars($av2['nama_barang_list']??'') ?>
            (<?= $av2['tanggal_pinjam']?date('d/m/Y',strtotime($av2['tanggal_pinjam'])):'—' ?>)
            <?= $is_od2?' <i class="bi bi-exclamation-triangle-fill"></i> Terlambat':'' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Tanggal Kembali <span>*</span></label>
          <input type="date" name="tgl_kembali" id="returnTgl" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Kondisi Sarana <span>*</span></label>
          <select name="kondisi_kembali" id="returnKondisi" class="form-control">
            <option value="baik">Baik</option>
            <option value="rusak_ringan">Rusak Ringan</option>
            <option value="rusak_berat">Rusak Berat</option>
          </select>
        </div>
      </div>

      <button type="submit" name="catat_pengembalian" class="btn-modal-submit">
        <i class="bi bi-check-circle"></i> Konfirmasi Pengembalian
      </button>
      <button type="button" class="btn-modal-cancel" onclick="closeModal('modalReturn')">Batal</button>
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
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  function handleBackdropClick(e, id) { if (e.target===document.getElementById(id)) closeModal(id); }
  document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal('modalReturn'); });

  function openReturnModal(data) {
    document.getElementById('returnError').classList.remove('show');
    const pilih = document.getElementById('pilihPeminjamanGroup');
    const sel   = document.getElementById('returnSelectPm');
    if (data) {
      pilih.style.display = 'none';
      document.getElementById('returnId').value = data.id;
      fillSummary(data);
    } else {
      pilih.style.display = '';
      sel.value = '';
      document.getElementById('returnId').value = '';
      clearSummary();
    }
    openModal('modalReturn');
  }

  function onSelectPm(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) { clearSummary(); document.getElementById('returnId').value=''; return; }
    const data = JSON.parse(opt.dataset.pm);
    document.getElementById('returnId').value = data.id;
    fillSummary(data);
  }

  function fillSummary(data) {
    document.getElementById('sumPeminjam').textContent = data.nama + (data.kelas ? ` (${data.kelas})` : '');
    document.getElementById('sumBarang').textContent   = data.barang || '—';
    document.getElementById('sumTgl').textContent      = data.tgl_pinjam ? formatTgl(data.tgl_pinjam) : '—';
    document.getElementById('sumTujuan').textContent   = data.tujuan || '—';
    const wRow = document.getElementById('sumWaktuRow');
    if (data.waktu_mulai && data.waktu_selesai) {
      document.getElementById('sumWaktu').textContent = data.waktu_mulai.substr(0,5)+' – '+data.waktu_selesai.substr(0,5);
      wRow.style.display = '';
    } else { wRow.style.display = 'none'; }
    document.getElementById('overdueBox').style.display = data.is_overdue ? '' : 'none';
  }

  function clearSummary() {
    ['sumPeminjam','sumBarang','sumTgl','sumTujuan'].forEach(id => document.getElementById(id).textContent='—');
    document.getElementById('sumWaktuRow').style.display = 'none';
    document.getElementById('overdueBox').style.display  = 'none';
  }

  function formatTgl(t) {
    const d = new Date(t);
    const bl = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    return d.getDate()+' '+bl[d.getMonth()]+' '+d.getFullYear();
  }

  function validateReturn() {
    const err = document.getElementById('returnError');
    const id  = document.getElementById('returnId').value;
    const tgl = document.getElementById('returnTgl').value;
    if (!id)  { err.textContent='Pilih peminjaman.'; err.classList.add('show'); return false; }
    if (!tgl) { err.textContent='Tanggal kembali wajib diisi.'; err.classList.add('show'); return false; }
    err.classList.remove('show'); return true;
  }

  initTableControls('searchFormPengembalian','searchInputPengembalian');

  /* Auto-open jika ada ?catat= */
  <?php if ($prefill_pm): ?>
  window.addEventListener('DOMContentLoaded', () => {
    openReturnModal(<?= json_encode([
      'id'           => $prefill_pm['id_peminjaman'],
      'nama'         => $prefill_pm['nama_peminjam'],
      'barang'       => $prefill_pm['nama_barang_list'],
      'jumlah'       => $prefill_pm['jumlah_list'],
      'tgl_pinjam'   => $prefill_pm['tanggal_pinjam'],
      'waktu_mulai'  => $prefill_pm['waktu_mulai'],
      'waktu_selesai'=> $prefill_pm['waktu_selesai'],
      'kelas'        => $prefill_pm['kelas'],
      'tujuan'       => $prefill_pm['tujuan'],
      'is_overdue'   => (bool)($prefill_pm['waktu_selesai'] && time() > strtotime($prefill_pm['tanggal_pinjam'].' '.$prefill_pm['waktu_selesai'])),
    ]) ?>);
  });
  <?php endif; ?>

  /* ── BFCache Fix: paksa reload jika halaman dari back-forward cache ── */
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
  });
</script>
</body>
</html>