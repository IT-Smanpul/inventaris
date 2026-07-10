<?php
require_once "config/koneksi.php";

$total_barang   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM barang"))['t'];
$total_ruangan  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM ruangan"))['t'];
$total_pengguna = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengguna"))['t'];

if ($total_barang   == 0) $total_barang   = '0';
if ($total_ruangan  == 0) $total_ruangan  = '0';
if ($total_pengguna == 0) $total_pengguna = '0';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistem Inventaris SARPRAS — SMAN 10 Pontianak</title>
  <link rel="icon" href="assets/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
      /* Warna dari logo SMAN 10 */
      --blue:        #4A90C4;   /* biru muda logo */
      --blue-dark:   #2B6FA8;   /* biru tua ring */
      --blue-deep:   #1B3F6E;   /* navy */
      --green:       #3D9B4A;   /* hijau daun */
      --green-light: #6DC05A;
      --yellow:      #F5C518;   /* kuning angka 10 */
      --white:       #FFFFFF;
      --bg-light:    #F0F7FF;   /* biru sangat muda */
      --text:        #1B2D45;
      --muted:       #6B7C93;
    }

    html { scroll-behavior: smooth; }
    body {
      font-family: 'DM Sans', sans-serif;
      color: var(--text);
      overflow-x: hidden;
      background: var(--white);
    }

    /* ══════════ NAVBAR ══════════ */
    .navbar {
      position: fixed; top: 0; left: 0; right: 0; z-index: 200;
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 52px;
      background: rgba(255,255,255,.97);
      backdrop-filter: blur(14px);
      border-bottom: 1px solid rgba(74,144,196,.15);
      transition: box-shadow .3s;
    }
    .navbar.scrolled { box-shadow: 0 4px 24px rgba(27,63,110,.12); }

    .nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
    .nav-logo { width: 42px; height: 42px; }
    .nav-logo img { width: 100%; height: 100%; object-fit: contain; }

    .nav-brand-text strong {
      display: block; font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px; font-weight: 800; color: var(--blue-deep);
    }
    .nav-brand-text span { font-size: 11px; color: var(--muted); }

    .nav-links { display: flex; gap: 32px; list-style: none; }
    .nav-links a {
      text-decoration: none; font-size: 13px; font-weight: 600;
      color: var(--muted); transition: color .2s; letter-spacing: .3px;
    }
    .nav-links a:hover { color: var(--blue-dark); }

    .btn-nav-login {
      background: var(--blue-dark); color: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 700; font-size: 13px;
      padding: 9px 24px; border-radius: 9px;
      text-decoration: none;
      display: flex; align-items: center; gap: 6px;
      transition: all .2s;
      box-shadow: 0 4px 14px rgba(43,111,168,.3);
    }
    .btn-nav-login:hover { background: var(--blue-deep); transform: translateY(-1px); }

    .nav-hamburger {
      display: none; flex-direction: column; gap: 5px;
      cursor: pointer; padding: 6px; background: none; border: none;
    }
    .nav-hamburger span {
      display: block; width: 22px; height: 2px;
      background: var(--text); border-radius: 2px;
    }

    .mobile-nav {
      display: none; position: fixed;
      top: 70px; left: 0; right: 0;
      background: white; border-bottom: 1px solid #dde8f0;
      padding: 16px 24px 24px;
      flex-direction: column; gap: 4px;
      z-index: 199; box-shadow: 0 8px 24px rgba(0,0,0,.08);
    }
    .mobile-nav.open { display: flex; }
    .mobile-nav a {
      padding: 12px 16px; border-radius: 8px; text-decoration: none;
      font-size: 15px; font-weight: 500; color: var(--text); transition: background .15s;
    }
    .mobile-nav a:hover { background: var(--bg-light); }
    .mobile-nav .btn-nav-login { margin-top: 8px; justify-content: center; }

    /* ══════════ HERO ══════════ */
    .hero {
      min-height: 100vh;
      display: flex; align-items: stretch;
      padding-top: 70px;
      position: relative;
      overflow: hidden;
      background: var(--white);
    }

    /* ── LEFT CONTENT SIDE ── */
    .hero-left {
      flex: 1;
      display: flex; flex-direction: column; justify-content: center;
      padding: 60px 56px 60px 64px;
      position: relative; z-index: 2;
    }

    /* Big blob behind left text */
    .hero-left::before {
      content: '';
      position: absolute;
      top: -80px; left: -120px;
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(74,144,196,.12) 0%, transparent 70%);
      pointer-events: none;
    }

    .hero-eyebrow {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(74,144,196,.12); color: var(--blue-dark);
      padding: 5px 14px; border-radius: 20px;
      font-size: 11px; font-weight: 700; letter-spacing: 1px;
      text-transform: uppercase; margin-bottom: 22px;
    }
    .hero-eyebrow::before {
      content: ''; width: 7px; height: 7px;
      background: var(--blue); border-radius: 50%;
    }

    .hero-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 52px; font-weight: 900;
      line-height: 1.08; color: var(--text);
      margin-bottom: 18px;
    }
    .hero-title .accent-blue   { color: var(--blue-dark); }
    .hero-title .accent-green  { color: var(--green); }

    .hero-desc {
      font-size: 15px; color: var(--muted);
      line-height: 1.8; max-width: 440px; margin-bottom: 36px;
    }

    /* CTA Buttons */
    .hero-cta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 52px; }

    .btn-primary-cta {
      background: var(--blue-dark); color: white;
      font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 14px;
      padding: 13px 30px; border-radius: 10px; text-decoration: none;
      display: inline-flex; align-items: center; gap: 8px;
      transition: all .25s;
      box-shadow: 0 8px 24px rgba(43,111,168,.35);
    }
    .btn-primary-cta:hover { background: var(--blue-deep); transform: translateY(-2px); box-shadow: 0 12px 32px rgba(43,111,168,.45); }

    .btn-secondary-cta {
      color: var(--green); font-weight: 700; font-size: 14px;
      text-decoration: none; display: inline-flex; align-items: center; gap: 7px;
      padding: 13px 22px; border-radius: 10px;
      border: 2px solid var(--green);
      transition: all .2s;
    }
    .btn-secondary-cta:hover { background: var(--green); color: white; }

    /* Stats */
    .hero-stats {
      display: flex; gap: 0;
      border-top: 1px solid #dde8f5;
      padding-top: 28px;
    }
    .stat-col { flex: 1; padding-right: 24px; }
    .stat-col + .stat-col { padding-left: 24px; border-left: 1px solid #dde8f5; }

    .stat-num {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 30px; font-weight: 900; color: var(--text);
    }
    .stat-lbl { font-size: 12px; color: var(--muted); margin-top: 3px; }
    .stat-dot {
      display: inline-block; width: 8px; height: 8px;
      border-radius: 50%; margin-right: 4px; vertical-align: middle;
    }

    /* ── RIGHT IMAGE SIDE ── */
    .hero-right {
      width: 48%;
      position: relative;
      flex-shrink: 0;
      overflow: hidden;
    }

    /* The organic blob shape that clips the photo */
    .hero-right-blob {
      position: absolute; inset: 0;
      clip-path: ellipse(90% 100% at 100% 50%);
      background: var(--blue);
      z-index: 0;
    }

    /* Inner colored blob — matches reference wavy shape */
    .blob-shape {
      position: absolute; inset: 0; z-index: 1;
      clip-path: path('M 60 0 C 10 0, 0 80, 0 180 C 0 320, 30 400, 0 520 C -20 620, 0 700, 0 800 L 700 800 L 700 0 Z');
      background: linear-gradient(160deg, var(--blue) 0%, var(--blue-dark) 60%, var(--blue-deep) 100%);
    }

    /* Actual school photo */
    .school-photo {
      position: absolute;
      top: 0; right: 0; bottom: 0;
      width: 88%;
      z-index: 2;
    }
    .school-photo img {
      width: 100%; height: 100%;
      object-fit: cover; object-position: center 30%;
      display: block;
    }

    /* Photo overlay gradient on the left edge — blends into white */
    .photo-fade {
      position: absolute;
      top: 0; left: 0; bottom: 0;
      width: 200px;
      background: linear-gradient(to right, white 0%, transparent 100%);
      z-index: 3;
    }

    /* Floating info chips on the photo */
    .photo-chip {
      position: absolute; z-index: 4;
      background: rgba(255,255,255,.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      padding: 10px 16px;
      display: flex; align-items: center; gap: 10px;
      box-shadow: 0 8px 28px rgba(27,63,110,.15);
      font-size: 12px; font-weight: 700; color: var(--text);
      white-space: nowrap;
    }
    .chip-icon {
      width: 32px; height: 32px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; flex-shrink: 0;
    }
    .chip-1 { top: 80px; left: 40px; animation: floatY 4s ease-in-out infinite; }
    .chip-2 { bottom: 100px; left: 30px; animation: floatY 5s ease-in-out infinite reverse; }
    .chip-3 { bottom: 52px; right: 28px; animation: floatY 6s ease-in-out infinite; }

    @keyframes floatY {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-8px); }
    }

    /* Yellow accent circle */
    .yellow-circle {
      position: absolute;
      width: 120px; height: 120px;
      border-radius: 50%;
      background: var(--yellow);
      opacity: .15;
      top: 40px; right: 40px;
      z-index: 4;
    }
    .green-circle {
      position: absolute;
      width: 80px; height: 80px;
      border-radius: 50%;
      background: var(--green);
      opacity: .2;
      bottom: 80px; right: 80px;
      z-index: 4;
    }

    /* ══════════ RESPONSIVE ══════════ */
    @media (max-width: 960px) {
      .nav-links, .btn-nav-login { display: none; }
      .nav-hamburger { display: flex; }
      .navbar { padding: 14px 20px; }

      .hero { flex-direction: column; min-height: auto; padding-top: 70px; }
      .hero-left { padding: 40px 24px 32px; }
      .hero-title { font-size: 36px; }
      .hero-right {
        width: 100%; height: 280px; flex-shrink: 0;
        order: -1; /* photo on top on mobile */
      }
      .hero-right-blob { clip-path: ellipse(100% 80% at 50% 0%); }
      .blob-shape { clip-path: none; }
      .school-photo { width: 100%; }
      .photo-fade {
        left: auto; right: auto; top: auto;
        bottom: 0; width: 100%; height: 80px;
        background: linear-gradient(to bottom, transparent, white);
      }
      .chip-1, .chip-2, .chip-3 { display: none; }
      .yellow-circle, .green-circle { display: none; }
      .hero-stats { gap: 0; }
      .stat-num { font-size: 24px; }
    }

    @media (max-width: 520px) {
      .hero-title { font-size: 30px; }
      .hero-stats { flex-direction: column; gap: 16px; }
      .stat-col + .stat-col { border-left: none; padding-left: 0; border-top: 1px solid #dde8f5; padding-top: 16px; }
      .hero-cta { flex-direction: column; align-items: stretch; }
      .btn-primary-cta, .btn-secondary-cta { justify-content: center; }
    }
  </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="navbar" id="navbar">
  <a href="index.php" class="nav-brand">
    <div class="nav-logo">
      <img src="assets/logo.png" alt="Logo SMAN 10">
    </div>
    <div class="nav-brand-text">
      <strong>SMA Negeri 10 Pontianak</strong>
    </div>
  </a>
</nav>


<!-- ══ HERO ══ -->
<section class="hero">

  <!-- ── LEFT TEXT ── -->
  <div class="hero-left">

    <h1 class="hero-title">
      SISTEM<br>
      <span class="accent-blue">INVENTARIS</span><br>
      <span class="accent-green">SARANA PRASARANA</span>
    </h1>

    <p class="hero-desc">
      Platform digital pengelolaan aset dan fasilitas SMA Negeri 10 Pontianak.
      Catat, pantau, dan kelola inventaris sekolah secara efisien dalam satu sistem terpadu.
    </p>

    <div class="hero-cta">
      <a href="auth/login.php" class="btn-primary-cta">
        <i class="bi bi-box-arrow-in-right"></i> Masuk ke Sistem
      </a>
    </div>

    <!-- Stats dari database -->
    <div class="hero-stats">
      <div class="stat-col">
        <div style="margin-bottom:6px;">
          <span class="stat-dot" style="background:var(--blue);"></span>
        </div>
        <div class="stat-num"><?= $total_barang ?></div>
        <div class="stat-lbl">Total Barang</div>
      </div>
      <div class="stat-col">
        <div style="margin-bottom:6px;">
          <span class="stat-dot" style="background:var(--green);"></span>
        </div>
        <div class="stat-num"><?= $total_ruangan ?></div>
        <div class="stat-lbl">Ruangan</div>
      </div>
      <div class="stat-col">
        <div style="margin-bottom:6px;">
          <span class="stat-dot" style="background:var(--yellow);"></span>
        </div>
        <div class="stat-num"><?= $total_pengguna ?></div>
        <div class="stat-lbl">Pengguna</div>
      </div>
    </div>

  </div>


  <!-- ── RIGHT PHOTO ── -->
  <div class="hero-right">

    <!-- Blue wavy background shape -->
    <div class="hero-right-blob"></div>
    <div class="blob-shape"></div>

    <!-- Decorative circles -->
    <div class="yellow-circle"></div>
    <div class="green-circle"></div>

    <!-- School photo -->
    <div class="school-photo">
      <img src="assets/sekolah.jpeg" alt="SMA Negeri 10 Pontianak">
    </div>

    <!-- Fade from photo into white on left edge -->
    <div class="photo-fade"></div>
  </div>

</section>

<style>
  @media (max-width: 860px) {
    #tentang { padding: 48px 24px !important; }
    #tentang > div > div:last-of-type { grid-template-columns: 1fr 1fr !important; }
  }
  @media (max-width: 540px) {
    #tentang > div > div:last-of-type { grid-template-columns: 1fr !important; }
  }
</style>

<!-- Footer minimal -->
<footer style="background:var(--blue-deep);color:rgba(255,255,255,.6);text-align:center;padding:20px;font-size:12px;">
  &copy; <?= date('Y') ?> Sistem Inventaris SARPRAS — SMA Negeri 10 Pontianak
</footer>

<script>
window.addEventListener('scroll', () => {
  document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 20);
});
function toggleNav() {
  document.getElementById('mobileNav').classList.toggle('open');
}
document.addEventListener('click', e => {
  const nav = document.getElementById('mobileNav');
  const btn = document.getElementById('hamburger');
  if (nav && btn && !nav.contains(e.target) && !btn.contains(e.target)) {
    nav.classList.remove('open');
  }
});
</script>

</body>
</html>