<?php
require_once "../config/auth_user.php";
require_once "../config/koneksi.php";

$id_pengguna = $_SESSION['id_pengguna'];
$nama_user   = $_SESSION['nama'] ?? 'Pengguna';
$role_user   = $_SESSION['role'] ?? 'murid';

$msg_success = '';
$msg_error   = '';

/* ══════════════════════════════════════
   POST: UPDATE PROFIL
══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_profil'])) {
        $nama     = trim(mysqli_real_escape_string($conn, $_POST['nama']     ?? ''));
        $email    = trim(mysqli_real_escape_string($conn, $_POST['email']    ?? ''));
        $username = trim(mysqli_real_escape_string($conn, $_POST['username'] ?? ''));

        if (empty($nama) || empty($username)) {
            $msg_error = "Nama dan username wajib diisi.";
        } else {
            /* Cek duplikasi username */
            $dup = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id_pengguna FROM pengguna
                 WHERE username='$username' AND id_pengguna!='$id_pengguna'"));
            if ($dup) {
                $msg_error = "Username '$username' sudah digunakan oleh pengguna lain.";
            } else {
                mysqli_query($conn,
                    "UPDATE pengguna
                     SET nama='$nama', email='$email', username='$username'
                     WHERE id_pengguna='$id_pengguna'");
                $_SESSION['nama'] = $nama;
                $nama_user        = $nama;
                $msg_success      = "Profil berhasil diperbarui.";
            }
        }
    }

    elseif (isset($_POST['ganti_password'])) {
        $pw_lama = $_POST['pw_lama'] ?? '';
        $pw_baru = $_POST['pw_baru'] ?? '';
        $pw_conf = $_POST['pw_conf'] ?? '';

        $pg = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT password FROM pengguna WHERE id_pengguna='$id_pengguna'"));

        if (!password_verify($pw_lama, $pg['password'])) {
            $msg_error = "Password lama tidak sesuai.";
        } elseif (strlen($pw_baru) < 6) {
            $msg_error = "Password baru minimal 6 karakter.";
        } elseif ($pw_baru !== $pw_conf) {
            $msg_error = "Konfirmasi password baru tidak cocok.";
        } else {
            $hashed = mysqli_real_escape_string($conn, password_hash($pw_baru, PASSWORD_DEFAULT));
            mysqli_query($conn,
                "UPDATE pengguna SET password='$hashed' WHERE id_pengguna='$id_pengguna'");
            $msg_success = "Password berhasil diperbarui.";
        }
    }
}

/* ── Ambil data user terbaru ── */
$user = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM pengguna WHERE id_pengguna='$id_pengguna'"));

/* ── Statistik ── */
$stat_total     = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman WHERE id_pengguna='$id_pengguna'"))['t'];
$stat_aktif     = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman WHERE id_pengguna='$id_pengguna' AND status IN ('menunggu','dipinjam','menunggu_kembali')"))['t'];
$stat_selesai   = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman WHERE id_pengguna='$id_pengguna' AND status='dikembalikan'"))['t'];
$stat_dipinjam  = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as t FROM peminjaman WHERE id_pengguna='$id_pengguna' AND status='dipinjam'"))['t'];

$role_labels = ['murid'=>'Murid','guru'=>'Guru','tendik'=>'Tenaga Kependidikan','admin'=>'Administrator'];
$role_label  = $role_labels[$user['role']] ?? ucfirst($user['role']);
$initials    = strtoupper(substr($user['nama'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil Saya — Inventaris SARPRAS</title>
  <meta name="description" content="Kelola profil dan pengaturan akun Anda">
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
    .mobile-user-info{display:flex;align-items:center;gap:10px;padding:12px 4px 14px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:6px;}
    .mobile-avatar{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:white;flex-shrink:0;}
    .nav-mobile-menu .nav-link{padding:12px 10px;border-radius:10px;font-size:14px;border-bottom:1px solid rgba(255,255,255,.06);}
    .nav-mobile-menu .nav-link:last-child{border-bottom:none;}

    /* ── PAGE ── */
    .page-wrapper{max-width:980px;margin:0 auto;padding:32px 24px 60px;flex:1;}

    /* ── FLASH ── */
    .flash{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:22px;display:flex;align-items:center;gap:9px;animation:fadeSlide .3s ease;}
    @keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
    .flash-success{background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;}
    .flash-error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;}

    /* ── LAYOUT 2 COL ── */
    .profil-layout{display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;}

    /* ── PROFILE CARD (sidebar) ── */
    .profile-card{background:var(--card);border:1.5px solid var(--border);border-radius:18px;box-shadow:var(--shadow);overflow:hidden;position:sticky;top:86px;}
    .profile-card-hero{background:linear-gradient(135deg,var(--blue-deep) 0%,var(--blue-dark) 100%);padding:32px 24px 24px;text-align:center;position:relative;overflow:hidden;}
    .profile-card-hero::after{content:'';position:absolute;right:-30px;top:-30px;width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none;}
    .profile-card-hero::before{content:'';position:absolute;left:-20px;bottom:-20px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.04);pointer-events:none;}
    .profile-avatar-big{width:80px;height:80px;border-radius:20px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-size:32px;font-weight:900;color:white;margin:0 auto 14px;border:3px solid rgba(255,255,255,.2);position:relative;z-index:1;}
    .profile-name{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:white;margin-bottom:4px;position:relative;z-index:1;}
    .profile-role-badge{display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,.15);color:rgba(255,255,255,.85);border-radius:20px;padding:4px 12px;font-size:11px;font-weight:700;position:relative;z-index:1;}
    .profile-card-body{padding:20px 22px;}
    .profile-info-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);}
    .profile-info-row:last-child{border-bottom:none;padding-bottom:0;}
    .profile-info-icon{width:34px;height:34px;border-radius:9px;background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--blue-dark);flex-shrink:0;}
    .profile-info-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}
    .profile-info-value{font-size:13px;font-weight:600;color:var(--text);word-break:break-all;}

    /* ── STATS ── */
    .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:16px 22px 20px;}
    .stat-box{background:var(--bg);border:1.5px solid var(--border);border-radius:12px;padding:12px;text-align:center;}
    .stat-box-num{font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:900;color:var(--blue-dark);line-height:1;}
    .stat-box-lbl{font-size:10px;color:var(--muted);margin-top:4px;font-weight:600;}

    /* ── EDIT FORMS (main area) ── */
    .form-card{background:var(--card);border:1.5px solid var(--border);border-radius:18px;box-shadow:var(--shadow);overflow:hidden;margin-bottom:22px;}
    .form-card-header{padding:18px 24px;border-bottom:2px solid var(--border);display:flex;align-items:center;gap:10px;}
    .form-card-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
    .form-card-icon-blue{background:#EFF6FF;color:var(--blue-dark);}
    .form-card-icon-green{background:#F0FDF4;color:var(--green);}
    .form-card-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--text);}
    .form-card-sub{font-size:12px;color:var(--muted);margin-top:1px;}
    .form-card-body{padding:24px;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:16px;}
    .form-row.single{grid-template-columns:1fr;}
    .form-group{margin-bottom:16px;}
    .form-group:last-child{margin-bottom:0;}
    .form-label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:.4px;}
    .form-label span{color:#DC2626;}
    .form-control{width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
    .form-control:focus{border-color:var(--blue);background:white;box-shadow:0 0 0 3px rgba(74,144,196,.14);}
    .form-control.readonly{background:#F8FAFC;color:var(--muted);cursor:not-allowed;}
    .form-hint{font-size:11px;color:var(--muted);margin-top:5px;}
    .pw-wrap{position:relative;}
    .pw-wrap .form-control{padding-right:42px;}
    .pw-toggle{position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);font-size:17px;transition:color .2s;padding:2px;}
    .pw-toggle:hover{color:var(--blue-dark);}
    .btn-submit{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:var(--blue-dark);color:white;border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;cursor:pointer;transition:all .2s;box-shadow:0 4px 16px rgba(43,111,168,.22);}
    .btn-submit:hover{background:var(--blue-deep);box-shadow:0 6px 20px rgba(43,111,168,.32);}
    .btn-submit-green{background:var(--green);box-shadow:0 4px 16px rgba(61,155,74,.22);}
    .btn-submit-green:hover{background:#2E7A38;box-shadow:0 6px 20px rgba(61,155,74,.32);}
    .pw-strength{height:4px;border-radius:2px;margin-top:8px;background:#E5E7EB;overflow:hidden;}
    .pw-strength-bar{height:100%;border-radius:2px;transition:all .3s;width:0%;}
    .pw-strength-text{font-size:10px;font-weight:700;margin-top:4px;}

    /* ── RESPONSIVE ── */
    @media(max-width:900px){
      .profil-layout{grid-template-columns:1fr;}
      .profile-card{position:static;}
    }
    @media(max-width:768px){
      .navbar{position:relative;}.nav-links,.nav-user{display:none;}
      .nav-hamburger{display:flex;align-items:center;justify-content:center;}
      .page-wrapper{padding:20px 14px 80px;}
      .form-row{grid-template-columns:1fr;}
      .stats-grid{grid-template-columns:repeat(3,1fr);}
    }
    @media(max-width:480px){
      .stats-grid{grid-template-columns:repeat(3,1fr);}
      .form-card-header{flex-direction:column;align-items:flex-start;}
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
    <a href="riwayat.php"   class="nav-link">Peminjaman Saya</a>
    <a href="profil.php"    class="nav-link active">Profil</a>
  </div>

  <div class="nav-user">
    <a href="profil.php" class="nav-avatar" title="Profil"><?= $initials ?></a>
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
      <div class="mobile-avatar"><?= $initials ?></div>
      <div>
        <div style="font-size:13px;font-weight:700;color:white;"><?= htmlspecialchars($nama_user) ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,.5);"><?= ucfirst($role_user) ?></div>
      </div>
    </div>
    <a href="dashboard.php" class="nav-link">Katalog Barang</a>
    <a href="riwayat.php"   class="nav-link">Peminjaman Saya</a>
    <a href="profil.php"    class="nav-link active">Profil Saya</a>
    <a href="../auth/logout.php" class="nav-link" style="color:#FCA5A5;">Keluar</a>
  </div>
</nav>

<div class="page-wrapper">

  <?php if ($msg_success): ?>
  <div class="flash flash-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($msg_success) ?></div>
  <?php endif; ?>
  <?php if ($msg_error): ?>
  <div class="flash flash-error"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($msg_error) ?></div>
  <?php endif; ?>

  <div class="profil-layout">

    <!-- ═══════ SIDEBAR: Profile Card ═══════ -->
    <aside>
      <div class="profile-card">
        <div class="profile-card-hero">
          <div class="profile-avatar-big"><?= $initials ?></div>
          <div class="profile-name"><?= htmlspecialchars($user['nama']) ?></div>
          <span class="profile-role-badge"><i class="bi bi-person-badge"></i> <?= $role_label ?></span>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
          <div class="stat-box">
            <div class="stat-box-num"><?= $stat_total ?></div>
            <div class="stat-box-lbl">Total</div>
          </div>
          <div class="stat-box">
            <div class="stat-box-num" style="color:#2563EB;"><?= $stat_dipinjam ?></div>
            <div class="stat-box-lbl">Dipinjam</div>
          </div>
          <div class="stat-box">
            <div class="stat-box-num" style="color:var(--green);"><?= $stat_selesai ?></div>
            <div class="stat-box-lbl">Selesai</div>
          </div>
        </div>

        <div class="profile-card-body">
          <div class="profile-info-row">
            <div class="profile-info-icon"><i class="bi bi-person-vcard"></i></div>
            <div>
              <div class="profile-info-label">ID Pengguna</div>
              <div class="profile-info-value"><?= htmlspecialchars($user['id_pengguna']) ?></div>
            </div>
          </div>
          <div class="profile-info-row">
            <div class="profile-info-icon"><i class="bi bi-at"></i></div>
            <div>
              <div class="profile-info-label">Username</div>
              <div class="profile-info-value"><?= htmlspecialchars($user['username'] ?? '—') ?></div>
            </div>
          </div>
          <div class="profile-info-row">
            <div class="profile-info-icon"><i class="bi bi-envelope"></i></div>
            <div>
              <div class="profile-info-label">Email</div>
              <div class="profile-info-value"><?= htmlspecialchars($user['email'] ?? '—') ?></div>
            </div>
          </div>
          <div class="profile-info-row">
            <div class="profile-info-icon"><i class="bi bi-shield-check"></i></div>
            <div>
              <div class="profile-info-label">Status Akun</div>
              <div class="profile-info-value" style="color:var(--green);">
                <i class="bi bi-check-circle-fill" style="font-size:12px;"></i>
                <?= ucfirst($user['status']) ?>
              </div>
            </div>
          </div>
          <div style="margin-top:16px;">
            <a href="riwayat.php" style="display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;background:var(--bg);border:1.5px solid var(--border);border-radius:10px;color:var(--blue-dark);font-size:13px;font-weight:700;text-decoration:none;transition:all .2s;">
              <i class="bi bi-clock-history"></i> Lihat Riwayat Peminjaman
            </a>
          </div>
        </div>
      </div>
    </aside>

    <!-- ═══════ MAIN: Edit Forms ═══════ -->
    <main>

      <!-- Edit Profil -->
      <div class="form-card">
        <div class="form-card-header">
          <div class="form-card-icon form-card-icon-blue"><i class="bi bi-pencil-square"></i></div>
          <div>
            <div class="form-card-title">Edit Informasi Profil</div>
            <div class="form-card-sub">Perbarui nama, email, dan username Anda</div>
          </div>
        </div>
        <div class="form-card-body">
          <form method="POST" id="formProfil">
            <div class="form-row">
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Nama Lengkap <span>*</span></label>
                <input type="text" name="nama" class="form-control" required
                       value="<?= htmlspecialchars($user['nama']) ?>" placeholder="Nama lengkap Anda">
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Username <span>*</span></label>
                <input type="text" name="username" class="form-control" required
                       value="<?= htmlspecialchars($user['username'] ?? '') ?>" placeholder="username unik Anda">
              </div>
            </div>
            <div class="form-group" style="margin-top:16px;">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control"
                     value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="email@contoh.com">
              <div class="form-hint">Opsional sebagai kelengkapan data kontak.</div>
            </div>
            <div class="form-group">
              <label class="form-label">ID Pengguna</label>
              <input type="text" class="form-control readonly" value="<?= htmlspecialchars($user['id_pengguna']) ?>" readonly>
              <div class="form-hint">ID pengguna tidak dapat diubah.</div>
            </div>
            <button type="submit" name="update_profil" class="btn-submit">
              <i class="bi bi-check-lg"></i> Simpan Perubahan
            </button>
          </form>
        </div>
      </div>

      <!-- Ganti Password -->
      <div class="form-card">
        <div class="form-card-header">
          <div class="form-card-icon form-card-icon-green"><i class="bi bi-lock"></i></div>
          <div>
            <div class="form-card-title">Ganti Password</div>
            <div class="form-card-sub">Pastikan gunakan password yang kuat dan mudah diingat</div>
          </div>
        </div>
        <div class="form-card-body">
          <form method="POST" id="formPassword" onsubmit="return validatePassword()">
            <div class="form-group">
              <label class="form-label">Password Lama <span>*</span></label>
              <div class="pw-wrap">
                <input type="password" name="pw_lama" id="pwLama" class="form-control" required placeholder="Masukkan password saat ini">
                <button type="button" class="pw-toggle" onclick="togglePw('pwLama',this)"><i class="bi bi-eye"></i></button>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Password Baru <span>*</span></label>
                <div class="pw-wrap">
                  <input type="password" name="pw_baru" id="pwBaru" class="form-control" required
                         placeholder="Min. 6 karakter" oninput="checkStrength(this.value)">
                  <button type="button" class="pw-toggle" onclick="togglePw('pwBaru',this)"><i class="bi bi-eye"></i></button>
                </div>
                <div class="pw-strength"><div class="pw-strength-bar" id="strengthBar"></div></div>
                <div class="pw-strength-text" id="strengthText"></div>
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Konfirmasi Password <span>*</span></label>
                <div class="pw-wrap">
                  <input type="password" name="pw_conf" id="pwConf" class="form-control" required
                         placeholder="Ulangi password baru">
                  <button type="button" class="pw-toggle" onclick="togglePw('pwConf',this)"><i class="bi bi-eye"></i></button>
                </div>
                <div id="matchHint" class="form-hint" style="margin-top:6px;"></div>
              </div>
            </div>
            <div id="pwError" style="background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;border-radius:8px;padding:10px 12px;font-size:12px;margin-top:14px;display:none;"></div>
            <div style="margin-top:18px;">
              <button type="submit" name="ganti_password" class="btn-submit btn-submit-green">
                <i class="bi bi-shield-check"></i> Perbarui Password
              </button>
            </div>
          </form>
        </div>
      </div>

    </main>
  </div>

</div>

<footer style="background:var(--blue-deep);color:rgba(255,255,255,.6);text-align:center;padding:20px;font-size:12px;">
  &copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak
</footer>

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

  /* Toggle password visibility */
  function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'bi bi-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'bi bi-eye';
    }
  }

  /* Password strength */
  function checkStrength(val) {
    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
      [0,  '0%',   '#EF4444', ''],
      [1,  '25%',  '#EF4444', 'Sangat Lemah'],
      [2,  '50%',  '#F59E0B', 'Cukup'],
      [3,  '70%',  '#3B82F6', 'Baik'],
      [4,  '85%',  '#10B981', 'Kuat'],
      [5,  '100%', '#059669', 'Sangat Kuat'],
    ];
    const [, w, c, label] = levels[Math.min(score, 5)];
    bar.style.width = w;
    bar.style.background = c;
    text.textContent = label;
    text.style.color = c;

    /* match hint */
    const conf = document.getElementById('pwConf').value;
    checkMatch(val, conf);
  }

  function checkMatch(baru, conf) {
    const hint = document.getElementById('matchHint');
    if (!conf) { hint.textContent = ''; return; }
    if (baru === conf) {
      hint.innerHTML = '<span style="color:#16A34A;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Password cocok</span>';
    } else {
      hint.innerHTML = '<span style="color:#DC2626;font-weight:700;"><i class="bi bi-x-circle-fill"></i> Password tidak cocok</span>';
    }
  }
  document.getElementById('pwConf').addEventListener('input', function() {
    checkMatch(document.getElementById('pwBaru').value, this.value);
  });

  /* Validate password form */
  function validatePassword() {
    const baru = document.getElementById('pwBaru').value;
    const conf = document.getElementById('pwConf').value;
    const err  = document.getElementById('pwError');
    if (baru.length < 6) {
      err.textContent = 'Password baru minimal 6 karakter.';
      err.style.display = 'block'; return false;
    }
    if (baru !== conf) {
      err.textContent = 'Konfirmasi password tidak cocok dengan password baru.';
      err.style.display = 'block'; return false;
    }
    err.style.display = 'none'; return true;
  }

  /* ── BFCache Fix: paksa reload jika halaman dari back-forward cache ── */
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) window.location.reload();
  });
</script>
</body>
</html>