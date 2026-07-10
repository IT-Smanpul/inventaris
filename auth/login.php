<?php
session_start();
include "../config/koneksi.php";

$error    = '';
$success  = '';
$tab      = isset($_POST['tab']) ? $_POST['tab'] : (isset($_GET['tab']) ? $_GET['tab'] : 'login');
$sel_role = isset($_POST['role']) ? $_POST['role'] : 'murid';

/* ═══ PROSES DAFTAR ═══ */
if (isset($_POST['daftar'])) {
    $tab         = 'daftar';
    $id_pengguna = trim($_POST['id_pengguna']);
    $nama        = trim($_POST['nama']);
    $email       = trim($_POST['email']);
    $username    = trim($_POST['reg_username']);
    $password    = trim($_POST['reg_password']);
    $confirm     = trim($_POST['reg_confirm']);
    $role        = $_POST['role'] ?? 'murid';
    $label_id    = ($role === 'murid') ? 'NIS' : 'NIP';

    if (empty($id_pengguna) || empty($nama) || empty($username) || empty($password)) {
        $error = "Semua field wajib diisi termasuk $label_id.";
    } elseif (!ctype_digit($id_pengguna)) {
        $error = "$label_id hanya boleh berisi angka.";
    } elseif ($password !== $confirm) {
        $error = "Password dan konfirmasi password tidak cocok.";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } else {
        $esc_id = mysqli_real_escape_string($conn, $id_pengguna);
        $esc_un = mysqli_real_escape_string($conn, $username);
        $cek_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengguna WHERE id_pengguna='$esc_id'"));
        $cek_un = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengguna WHERE username='$esc_un'"));

        if ($cek_id['t'] > 0) {
            $error = "$label_id sudah terdaftar di sistem.";
        } elseif ($cek_un['t'] > 0) {
            $error = "Username sudah digunakan. Pilih yang lain.";
        } else {
            // Hash password sebelum disimpan
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $esc_hash = mysqli_real_escape_string($conn, $hashed);
            mysqli_query($conn, "INSERT INTO pengguna (id_pengguna, nama, email, username, password, role, status) VALUES (
                '$esc_id',
                '".mysqli_real_escape_string($conn,$nama)."',
                '".mysqli_real_escape_string($conn,$email)."',
                '$esc_un',
                '$esc_hash',
                '".mysqli_real_escape_string($conn,$role)."',
                'pending'
            )");
            $success  = "Pendaftaran berhasil! Akun kamu sedang menunggu persetujuan admin.";
            $tab      = 'login';
            $sel_role = 'murid';
        }
    }
}

/* ═══ PROSES LOGIN ═══ */
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Cari user berdasarkan username saja dulu
    $query = mysqli_query($conn,
        "SELECT * FROM pengguna
         WHERE username='".mysqli_real_escape_string($conn,$username)."'"
    );
    $data = mysqli_fetch_assoc($query);

    if ($data) {
        // Cek status akun
        $status = $data['status'] ?? 'aktif';
        if ($status === 'pending') {
            $error = "Akun kamu masih menunggu persetujuan admin. Hubungi admin sekolah untuk aktivasi.";
            $tab   = 'login';
        } elseif ($status === 'nonaktif') {
            $error = "Akun kamu telah dinonaktifkan. Hubungi admin sekolah.";
            $tab   = 'login';
        } else {
            // Verifikasi password: dukung hash baru (password_hash) dan plain text lama
            $pass_ok = false;
            if (password_verify($password, $data['password'])) {
                // Password sudah di-hash — cocok
                $pass_ok = true;
            } elseif ($data['password'] === $password) {
                // Password lama masih plain text — cocok, upgrade ke hash sekarang
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $esc_h  = mysqli_real_escape_string($conn, $hashed);
                $esc_id = mysqli_real_escape_string($conn, $data['id_pengguna']);
                mysqli_query($conn, "UPDATE pengguna SET password='$esc_h' WHERE id_pengguna='$esc_id'");
                $pass_ok = true;
            }

            if ($pass_ok) {
                $_SESSION['id_pengguna'] = $data['id_pengguna'];
                $_SESSION['nama']        = $data['nama'];
                $_SESSION['role']        = $data['role'];
                $_SESSION['status']      = $data['status'];  // ← tambahan ini
                header("Location: " . ($data['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php'));
                exit;
            } else {
                $error = "Username atau password salah. Silakan coba lagi.";
                $tab   = 'login';
            }
        }
    } else {
        $error = "Username atau password salah. Silakan coba lagi.";
        $tab   = 'login';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Inventaris SARPRAS SMAN 10</title>
  <link rel="icon" href="../assets/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --blue:      #4A90C4;
      --blue-dark: #2B6FA8;
      --blue-deep: #1B3F6E;
      --green:     #3D9B4A;
      --yellow:    #F5C518;
      --bg:        #F0F7FF;
      --text:      #1B2D45;
      --muted:     #6B7C93;
      --border:    #C8DDEF;
    }

    html, body { height: 100%; }
    body {
      font-family: 'DM Sans', sans-serif;
      min-height: 100vh; display: flex;
      background: var(--bg); color: var(--text);
    }

    /* ═══ LEFT PANEL ═══ */
    .panel-left {
      flex: 1; position: relative; overflow: hidden;
      display: flex; flex-direction: column; justify-content: flex-end;
    }
    .panel-left-photo { position: absolute; inset: 0; }
    .panel-left-photo img { width:100%; height:100%; object-fit:cover; object-position:center 20%; }
    .panel-left-overlay {
      position: absolute; inset: 0;
      background: linear-gradient(to bottom,
        rgba(27,63,110,.45) 0%, rgba(27,63,110,.2) 35%,
        rgba(27,63,110,.75) 68%, rgba(27,63,110,.95) 100%);
    }
    .panel-left-content { position: relative; z-index: 2; padding: 48px 52px; }

    .panel-school-brand {
      position: absolute; top: 36px; left: 40px; z-index: 3;
      display: flex; align-items: center; gap: 12px;
    }
    .panel-school-brand img {
      width: 52px; height: 52px; object-fit: contain;
      filter: drop-shadow(0 2px 8px rgba(0,0,0,.3));
    }
    .panel-school-brand-text strong {
      display: block; font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px; font-weight: 800; color: white;
      text-shadow: 0 1px 6px rgba(0,0,0,.4);
    }
    .panel-school-brand-text span { font-size: 11px; color: rgba(255,255,255,.7); }

    .panel-eyebrow {
      display: inline-flex; align-items: center; gap: 7px;
      background: var(--yellow); color: var(--blue-deep);
      padding: 5px 14px; border-radius: 20px;
      font-size: 11px; font-weight: 800; letter-spacing: .8px;
      text-transform: uppercase; margin-bottom: 16px;
    }
    .panel-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 36px; font-weight: 900; color: white;
      line-height: 1.15; margin-bottom: 12px;
      text-shadow: 0 2px 12px rgba(0,0,0,.3);
    }
    .panel-title .accent { color: var(--yellow); }
    .panel-desc {
      font-size: 14px; color: rgba(255,255,255,.65);
      line-height: 1.75; max-width: 380px; margin-bottom: 32px;
    }
    .panel-features { display: flex; flex-direction: column; gap: 12px; }
    .feat-item {
      display: flex; align-items: center; gap: 12px;
      color: rgba(255,255,255,.8); font-size: 13px; font-weight: 500;
    }
    .feat-icon {
      width: 34px; height: 34px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; flex-shrink: 0;
    }
    .feat-icon.blue   { background: rgba(74,144,196,.3);  color: #93C5FD; }
    .feat-icon.green  { background: rgba(61,155,74,.3);   color: #86EFAC; }
    .feat-icon.yellow { background: rgba(245,197,24,.25); color: var(--yellow); }

    /* ═══ RIGHT PANEL ═══ */
    .panel-right {
      width: 500px; flex-shrink: 0;
      background: white;
      display: flex; flex-direction: column;
      align-items: center; padding: 36px 44px 28px;
      overflow-y: auto;
    }

    .form-logo { width: 56px; height: 56px; margin-bottom: 6px; }
    .form-logo img { width: 100%; height: 100%; object-fit: contain; }
    .form-school-name {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 13px; font-weight: 700; color: var(--blue-deep);
      text-align: center; margin-bottom: 2px;
    }
    .form-school-sub { font-size: 11px; color: var(--muted); text-align: center; margin-bottom: 20px; }

    /* ── TABS ── */
    .tab-bar {
      display: flex; width: 100%;
      background: var(--bg); border-radius: 12px;
      padding: 4px; gap: 4px; margin-bottom: 22px;
    }
    .tab-btn {
      flex: 1; padding: 9px 0; border: none; background: none; cursor: pointer;
      border-radius: 9px; font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 13px; font-weight: 700; color: var(--muted);
      transition: all .2s; display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .tab-btn.active {
      background: white; color: var(--blue-deep);
      box-shadow: 0 2px 10px rgba(27,63,110,.12);
    }

    /* ── PANELS ── */
    .form-panel { display: none; width: 100%; }
    .form-panel.active { display: block; }

    .form-heading {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 19px; font-weight: 800; color: var(--text); margin-bottom: 3px;
    }
    .form-subheading { font-size: 13px; color: var(--muted); margin-bottom: 18px; }

    /* ── ALERTS ── */
    .alert {
      width: 100%; padding: 11px 14px; border-radius: 10px;
      font-size: 13px; display: flex; align-items: flex-start; gap: 9px;
      margin-bottom: 14px; border: 1px solid transparent;
    }
    .alert-error   { background: #FEF2F2; border-color: #FECACA; color: #DC2626; }
    .alert-success { background: #F0FDF4; border-color: #BBF7D0; color: #166534; }
    .alert-warning { background: #FFF8E1; border-color: #FFE082; color: #92400E; }

    /* ── INPUTS ── */
    .form-group { margin-bottom: 13px; }
    .form-label {
      display: block; font-size: 12px; font-weight: 700;
      color: var(--text); margin-bottom: 5px;
    }
    .input-wrap { position: relative; }
    .input-wrap .inp-icon {
      position: absolute; left: 13px; top: 50%;
      transform: translateY(-50%);
      color: #9CA3AF; font-size: 15px; pointer-events: none;
    }
    .input-wrap input {
      width: 100%; padding: 10px 14px 10px 38px;
      border: 1.5px solid var(--border); border-radius: 10px;
      font-size: 14px; font-family: 'DM Sans', sans-serif;
      color: var(--text); background: #FAFBFE;
      outline: none; transition: all .2s;
    }
    .input-wrap input:focus {
      border-color: var(--blue); background: white;
      box-shadow: 0 0 0 3px rgba(74,144,196,.14);
    }
    .input-wrap .btn-eye {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      color: #9CA3AF; font-size: 15px; padding: 4px;
    }

    /* NIS/NIP badge inside input */
    .id-badge {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      background: var(--blue-deep); color: white;
      font-size: 10px; font-weight: 800; letter-spacing: .5px;
      padding: 3px 8px; border-radius: 6px;
      pointer-events: none; transition: transform .15s;
    }

    /* 2-col grid */
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }

    /* Role cards */
    .role-grid {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 8px; margin-bottom: 13px;
    }
    .role-card {
      border: 2px solid var(--border); border-radius: 10px;
      padding: 10px 6px; text-align: center; cursor: pointer;
      transition: all .2s; position: relative;
    }
    .role-card input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .role-card.selected { border-color: var(--blue); background: #EFF6FF; }
    .role-card-icon { font-size: 20px; margin-bottom: 4px; display: block; }
    .role-card-label { font-size: 12px; font-weight: 700; color: var(--text); }
    .role-card-sub { font-size: 10px; color: var(--muted); margin-top: 1px; }

    /* Submit button */
    .btn-submit {
      width: 100%; padding: 12px; border: none; border-radius: 10px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px; font-weight: 800; cursor: pointer;
      transition: all .2s; margin-top: 4px;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-submit-blue {
      background: var(--blue-dark); color: white;
      box-shadow: 0 6px 20px rgba(43,111,168,.28);
    }
    .btn-submit-blue:hover { background: var(--blue-deep); transform: translateY(-1px); }
    .btn-submit-green {
      background: var(--green); color: white;
      box-shadow: 0 6px 20px rgba(61,155,74,.28);
    }
    .btn-submit-green:hover { background: #2e7a38; transform: translateY(-1px); }

    .form-footer { margin-top: 18px; text-align: center; font-size: 11px; color: var(--muted); }
    .form-link { color: var(--blue-dark); font-weight: 700; text-decoration: none; }
    .form-link:hover { text-decoration: underline; }

    /* Info note */
    .info-note {
      background: #EFF6FF; border: 1px solid #BFDBFE;
      border-radius: 9px; padding: 10px 13px;
      font-size: 12px; color: #1D4ED8;
      display: flex; gap: 8px; align-items: flex-start; margin-bottom: 12px;
    }

    /* ═══ RESPONSIVE ═══ */
    @media (max-width: 800px) {
      body { flex-direction: column; }
      .panel-left { height: 200px; flex: none; }
      .panel-left-content { padding: 16px 20px 22px; }
      .panel-title { font-size: 20px; }
      .panel-desc, .panel-features { display: none; }
      .panel-school-brand { top: 16px; left: 16px; }
      .panel-school-brand img { width: 36px; height: 36px; }
      .panel-school-brand-text strong { font-size: 13px; }
      .panel-right {
        width: 100%; flex: 1;
        padding: 22px 20px 32px;
        border-radius: 20px 20px 0 0;
        margin-top: -20px; position: relative; z-index: 5;
        box-shadow: 0 -6px 28px rgba(0,0,0,.1);
        justify-content: flex-start; overflow-y: auto;
      }
      .form-logo, .form-school-name, .form-school-sub { display: none; }
    }
    @media (max-width: 480px) {
      .form-grid-2 { grid-template-columns: 1fr; }
      .panel-left { height: 180px; }
      .panel-right { padding: 18px 16px 32px; }
    }
  </style>
</head>
<body>

<!-- ═══ LEFT PANEL ═══ -->
<div class="panel-left">
  <div class="panel-left-photo">
    <img src="../assets/sekolah.jpeg" alt="SMAN 10 Pontianak">
  </div>
  <div class="panel-left-overlay"></div>

  <div class="panel-school-brand">
    <img src="../assets/logo.png" alt="Logo SMAN 10">
    <div class="panel-school-brand-text">
      <strong>SMA Negeri 10 Pontianak</strong>
    </div>
  </div>

  <div class="panel-left-content">
    <h1 class="panel-title">
      Sistem Digital<br>
      <span class="accent">Sarana &amp; Prasarana</span>
    </h1>
    <p class="panel-desc">
      Kelola seluruh aset, ruangan, dan peminjaman fasilitas sekolah dalam satu platform terintegrasi.
    </p>
    </div>
  </div>
</div>


<!-- ═══ RIGHT PANEL ═══ -->
<div class="panel-right">

  <div class="form-logo"><img src="../assets/logo.png" alt="Logo"></div>
  <div class="form-school-name">SMA Negeri 10 Pontianak</div>
  <div class="form-school-sub">Sistem Inventaris Sarana &amp; Prasarana</div>

  <!-- Alert -->
  <?php if ($error): ?>
  <div class="alert alert-error">
    <i class="bi bi-exclamation-circle-fill" style="flex-shrink:0;margin-top:1px;"></i>
    <span><?= htmlspecialchars($error) ?></span>
  </div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="alert alert-warning">
    <i class="bi bi-hourglass-split" style="flex-shrink:0;margin-top:1px;"></i>
    <span><?= htmlspecialchars($success) ?></span>
  </div>
  <?php endif; ?>

  <!-- Tab bar -->
  <div class="tab-bar">
    <button class="tab-btn <?= $tab==='login'  ? 'active' : '' ?>" onclick="switchTab('login')">
      <i class="bi bi-box-arrow-in-right"></i> Masuk
    </button>
    <button class="tab-btn <?= $tab==='daftar' ? 'active' : '' ?>" onclick="switchTab('daftar')">
      <i class="bi bi-person-plus"></i> Daftar Akun
    </button>
  </div>


  <!-- ─── LOGIN ─── -->
  <div class="form-panel <?= $tab==='login' ? 'active' : '' ?>" id="panel-login">
    <div class="form-heading">Selamat Datang</div>
    <div class="form-subheading">Masuk ke akun Anda untuk melanjutkan</div>

    <form method="POST">
      <input type="hidden" name="tab" value="login">

      <div class="form-group">
        <label class="form-label">Username</label>
        <div class="input-wrap">
          <i class="bi bi-person inp-icon"></i>
          <input type="text" name="username" placeholder="Masukkan username" required
            value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-wrap">
          <i class="bi bi-lock inp-icon"></i>
          <input type="password" name="password" id="passLogin" placeholder="Masukkan password" required>
          <button type="button" class="btn-eye" onclick="togglePass('passLogin','eyeLogin')">
            <i class="bi bi-eye" id="eyeLogin"></i>
          </button>
        </div>
      </div>

      <button type="submit" name="login" class="btn-submit btn-submit-blue">
        <i class="bi bi-box-arrow-in-right"></i> Masuk ke Sistem
      </button>
    </form>

    <div style="text-align:center;font-size:13px;color:var(--muted);margin-top:16px;">
      Belum punya akun?
      <a href="#" class="form-link" onclick="switchTab('daftar');return false;">Daftar sekarang</a>
    </div>
  </div>

  <!-- ─── DAFTAR ─── -->
  <div class="form-panel <?= $tab==='daftar' ? 'active' : '' ?>" id="panel-daftar">
    <div class="form-heading">Buat Akun Baru</div>
    <div class="form-subheading">Pilih peran dan lengkapi data diri Anda</div>

    <form method="POST">
      <input type="hidden" name="tab" value="daftar">

      <!-- Role selector -->
      <div class="form-label" style="margin-bottom:7px;">Daftar sebagai</div>
      <div class="role-grid">

        <label class="role-card <?= $sel_role==='murid'  ? 'selected' : '' ?>" data-role="murid">
          <input type="radio" name="role" value="murid" <?= $sel_role==='murid'  ? 'checked' : '' ?>>
          <div class="role-card-label">Murid</div>
          <div class="role-card-sub">Siswa aktif</div>
        </label>

        <label class="role-card <?= $sel_role==='guru'   ? 'selected' : '' ?>" data-role="guru">
          <input type="radio" name="role" value="guru"   <?= $sel_role==='guru'   ? 'checked' : '' ?>>
          <div class="role-card-label">Guru</div>
          <div class="role-card-sub">Tenaga pengajar</div>
        </label>

        <label class="role-card <?= $sel_role==='tendik' ? 'selected' : '' ?>" data-role="tendik">
          <input type="radio" name="role" value="tendik" <?= $sel_role==='tendik' ? 'checked' : '' ?>>
          <div class="role-card-label">Staff</div>
          <div class="role-card-sub">Tenaga kependidikan</div>
        </label>

      </div>

      <!-- Nama + NIS/NIP -->
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <div class="input-wrap">
            <i class="bi bi-person-badge inp-icon"></i>
            <input type="text" name="nama" placeholder="Nama lengkap" required
              value="<?= isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '' ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" id="labelNomorId">
            <?= $sel_role==='murid' ? 'NIS (Nomor Induk Siswa)' : 'NIP/NUPTK' ?>
          </label>
          <div class="input-wrap">
            <i class="bi bi-credit-card-2-front inp-icon"></i>
            <input type="text" name="id_pengguna" id="inputNomorId"
              placeholder="<?= $sel_role==='murid' ? 'Masukkan NIS' : 'Masukkan NIP/NUPTK' ?>"
              inputmode="numeric" pattern="[0-9]*" required
              value="<?= isset($_POST['id_pengguna']) ? htmlspecialchars($_POST['id_pengguna']) : '' ?>">
            <span class="id-badge" id="badgeNomorId">
              <?= $sel_role==='murid' ? 'NIS' : 'NIP' ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Email + Username -->
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Email</label>
          <div class="input-wrap">
            <i class="bi bi-envelope inp-icon"></i>
            <input type="email" name="email" placeholder="Email aktif"
              value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Username</label>
          <div class="input-wrap">
            <i class="bi bi-at inp-icon"></i>
            <input type="text" name="reg_username" placeholder="Username unik" required
              value="<?= isset($_POST['reg_username']) ? htmlspecialchars($_POST['reg_username']) : '' ?>">
          </div>
        </div>
      </div>

      <!-- Password + Konfirmasi -->
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-wrap">
            <i class="bi bi-lock inp-icon"></i>
            <input type="password" name="reg_password" id="passReg" placeholder="Min. 6 karakter" required>
            <button type="button" class="btn-eye" onclick="togglePass('passReg','eyeReg')">
              <i class="bi bi-eye" id="eyeReg"></i>
            </button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Konfirmasi</label>
          <div class="input-wrap">
            <i class="bi bi-lock-fill inp-icon"></i>
            <input type="password" name="reg_confirm" id="passConfirm" placeholder="Ulangi password" required>
            <button type="button" class="btn-eye" onclick="togglePass('passConfirm','eyeConfirm')">
              <i class="bi bi-eye" id="eyeConfirm"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Info -->
      <div class="info-note">
        <i class="bi bi-info-circle-fill" style="flex-shrink:0;margin-top:1px;"></i>
        <span>Akun baru memerlukan verifikasi admin sebelum dapat mengakses sistem peminjaman.</span>
      </div>

      <button type="submit" name="daftar" class="btn-submit btn-submit-green">
        <i class="bi bi-person-check"></i> Buat Akun
      </button>
    </form>

    <div style="text-align:center;font-size:13px;color:var(--muted);margin-top:14px;">
      Sudah punya akun?
      <a href="#" class="form-link" onclick="switchTab('login');return false;">Masuk di sini</a>
    </div>
  </div>


  <div class="form-footer">
    &copy; <?= date('Y') ?> Inventaris SARPRAS — SMAN 10 Pontianak
  </div>

</div><!-- /panel-right -->


<script>
  /* Tab switching */
  function switchTab(tab) {
    ['login','daftar'].forEach(t => {
      document.getElementById('panel-' + t).classList.toggle('active', t === tab);
    });
    document.querySelectorAll('.tab-btn').forEach((btn, i) => {
      btn.classList.toggle('active',
        (i === 0 && tab === 'login') || (i === 1 && tab === 'daftar'));
    });
  }

  /* Toggle password visibility */
  function togglePass(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    inp.type       = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
  }

  /* Dynamic NIS/NIP label based on selected role */
  const nisMap = {
    murid:  { label: 'NIS (Nomor Induk Siswa)',   ph: 'Masukkan NIS', badge: 'NIS' },
    guru:   { label: 'NIP/NUPTK', ph: 'Masukkan NIP/NUPTK', badge: 'NIP' },
    tendik: { label: 'NIP/NUPTK', ph: 'Masukkan NIP/NUPTK', badge: 'NIP' },
  };

  function updateNisNip(role) {
    const cfg   = nisMap[role] || nisMap.murid;
    document.getElementById('labelNomorId').textContent = cfg.label;
    document.getElementById('inputNomorId').placeholder = cfg.ph;
    const badge = document.getElementById('badgeNomorId');
    badge.textContent = cfg.badge;
    badge.style.transform = 'translateY(-50%) scale(1.2)';
    setTimeout(() => badge.style.transform = 'translateY(-50%) scale(1)', 180);
  }

  document.querySelectorAll('.role-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      updateNisNip(card.dataset.role);
    });
  });

  /* Init on load */
  const initCard = document.querySelector('.role-card.selected');
  if (initCard) updateNisNip(initCard.dataset.role);
</script>

</body>
</html>