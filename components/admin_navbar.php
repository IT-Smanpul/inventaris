<?php
/**
 * Komponen Navbar Admin — SMAN 10 Pontianak
 * Digunakan di semua halaman panel admin.
 */

// Pastikan koneksi DB tersedia untuk query badge count jika belum di-set
if (!isset($conn)) {
    require_once __DIR__ . "/../config/koneksi.php";
}

// Auto-detect halaman aktif jika tidak ditentukan secara manual
if (!isset($active_nav)) {
    $current_script = basename($_SERVER['PHP_SELF'] ?? '');
    $active_nav = match ($current_script) {
        'dashboard.php'        => 'dashboard',
        'ruangan.php'          => 'ruangan',
        'barang.php'           => 'barang',
        'stok_habis_pakai.php' => 'stok_habis_pakai',
        'pengguna.php'         => 'pengguna',
        'peminjaman.php'       => 'peminjaman',
        'pengembalian.php'     => 'pengembalian',
        'pengaduan.php'        => 'pengaduan',
        default                => ''
    };
}

// Ambil badge count jika belum tersedia
if (!isset($pending_count) && isset($conn)) {
    $pending_count = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengguna WHERE status='pending'"))['t'] ?? 0);
}
if (!isset($pm_menunggu) && isset($conn)) {
    $pm_menunggu = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM peminjaman WHERE status='menunggu'"))['t'] ?? 0);
}
if (!isset($aduan_menunggu) && isset($conn)) {
    $aduan_menunggu = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM pengaduan WHERE status='menunggu'"))['t'] ?? 0);
}
?>

<!-- ══ NAVBAR ADMIN ══ -->
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
    <a href="dashboard.php"        class="nav-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="ruangan.php"          class="nav-link <?= $active_nav === 'ruangan' ? 'active' : '' ?>">Prasarana</a>
    <a href="barang.php"           class="nav-link <?= $active_nav === 'barang' ? 'active' : '' ?>">Sarana</a>
    <a href="stok_habis_pakai.php" class="nav-link <?= $active_nav === 'stok_habis_pakai' ? 'active' : '' ?>">Stok Habis Pakai</a>
    <a href="pengguna.php"         class="nav-link <?= $active_nav === 'pengguna' ? 'active' : '' ?>">
      Pengguna
      <?php if (!empty($pending_count)): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?>
    </a>
    <a href="peminjaman.php"       class="nav-link <?= $active_nav === 'peminjaman' ? 'active' : '' ?>">
      Peminjaman
      <?php if (!empty($pm_menunggu)): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?>
    </a>
    <a href="pengembalian.php"     class="nav-link <?= $active_nav === 'pengembalian' ? 'active' : '' ?>">Pengembalian</a>
    <a href="pengaduan.php"        class="nav-link <?= $active_nav === 'pengaduan' ? 'active' : '' ?>">
      Pengaduan
      <?php if (!empty($aduan_menunggu)): ?><span class="nav-badge"><?= $aduan_menunggu ?></span><?php endif; ?>
    </a>
    <a href="../auth/logout.php"   class="nav-link logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>

  <!-- Hamburger Toggle -->
  <button class="nav-hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Menu">
    <i class="bi bi-list" id="hamburgerIcon"></i>
  </button>
</nav>

<!-- Mobile Menu -->
<div class="nav-mobile-menu" id="mobileMenu">
  <a href="dashboard.php"        class="nav-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
  <a href="ruangan.php"          class="nav-link <?= $active_nav === 'ruangan' ? 'active' : '' ?>">Prasarana</a>
  <a href="barang.php"           class="nav-link <?= $active_nav === 'barang' ? 'active' : '' ?>">Sarana</a>
  <a href="stok_habis_pakai.php" class="nav-link <?= $active_nav === 'stok_habis_pakai' ? 'active' : '' ?>">Stok Habis Pakai</a>
  <a href="pengguna.php"         class="nav-link <?= $active_nav === 'pengguna' ? 'active' : '' ?>">
    Pengguna
    <?php if (!empty($pending_count)): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?>
  </a>
  <a href="peminjaman.php"       class="nav-link <?= $active_nav === 'peminjaman' ? 'active' : '' ?>">
    Peminjaman
    <?php if (!empty($pm_menunggu)): ?><span class="nav-badge"><?= $pm_menunggu ?></span><?php endif; ?>
  </a>
  <a href="pengembalian.php"     class="nav-link <?= $active_nav === 'pengembalian' ? 'active' : '' ?>">Pengembalian</a>
  <a href="pengaduan.php"        class="nav-link <?= $active_nav === 'pengaduan' ? 'active' : '' ?>">
    Pengaduan
    <?php if (!empty($aduan_menunggu)): ?><span class="nav-badge"><?= $aduan_menunggu ?></span><?php endif; ?>
  </a>
  <a href="../auth/logout.php"   class="nav-link logout">Logout</a>
</div>

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
