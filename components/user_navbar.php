<?php
/**
 * Komponen Navbar Pengguna (Murid/Guru/Tendik) — SMAN 10 Pontianak
 * Digunakan di semua halaman panel user.
 */

// Auto-detect halaman aktif jika tidak ditentukan secara manual
if (!isset($active_nav)) {
    $current_script = basename($_SERVER['PHP_SELF'] ?? '');
    $active_nav = match ($current_script) {
        'dashboard.php' => 'dashboard',
        'riwayat.php'   => 'riwayat',
        'profil.php'    => 'profil',
        default         => ''
    };
}

$u_nama = $_SESSION['nama'] ?? 'Pengguna';
$u_role = $_SESSION['role'] ?? 'murid';
$u_initial = strtoupper(substr(trim($u_nama), 0, 1));
?>

<!-- ══ NAVBAR USER ══ -->
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
    <a href="dashboard.php" class="nav-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">Katalog Barang</a>
    <a href="riwayat.php"   class="nav-link <?= $active_nav === 'riwayat' ? 'active' : '' ?>">Peminjaman Saya</a>
    <a href="profil.php"    class="nav-link <?= $active_nav === 'profil' ? 'active' : '' ?>">Profil</a>
  </div>

  <!-- User info + logout -->
  <div class="nav-user">
    <a href="profil.php" class="nav-avatar" title="Profil Saya"><?= $u_initial ?></a>
    <div>
      <div class="nav-user-name"><?= htmlspecialchars($u_nama) ?></div>
      <div class="nav-user-role"><?= ucfirst($u_role) ?></div>
    </div>
    <a href="../auth/logout.php" class="nav-logout"><i class="bi bi-box-arrow-right"></i> Keluar</a>
  </div>

  <!-- Hamburger -->
  <button class="nav-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Menu">
    <i class="bi bi-list" id="hamburgerIcon"></i>
  </button>

  <!-- Mobile dropdown -->
  <div class="nav-mobile-menu" id="mobileMenu">
    <div class="mobile-user-info" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:6px;">
      <div class="mobile-avatar" style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:13px;"><?= $u_initial ?></div>
      <div>
        <div style="font-size:13px;font-weight:700;color:white;"><?= htmlspecialchars($u_nama) ?></div>
        <div style="font-size:11px;color:rgba(255,255,255,.5);"><?= ucfirst($u_role) ?></div>
      </div>
    </div>
    <a href="dashboard.php" class="nav-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">Katalog Barang</a>
    <a href="riwayat.php"   class="nav-link <?= $active_nav === 'riwayat' ? 'active' : '' ?>">Peminjaman Saya</a>
    <a href="profil.php"    class="nav-link <?= $active_nav === 'profil' ? 'active' : '' ?>">Profil Saya</a>
    <a href="../auth/logout.php" class="nav-link" style="color:#FCA5A5;">Keluar</a>
  </div>
</nav>

<script>
  if (typeof toggleMobileMenu !== 'function') {
    function toggleMobileMenu() {
      const menu = document.getElementById('mobileMenu');
      if (menu) {
        menu.classList.toggle('open');
      }
    }
  }
</script>
