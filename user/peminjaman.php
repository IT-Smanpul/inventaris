<?php
require_once "../config/auth_user.php";
require_once "../config/koneksi.php";

$id_pengguna = $_SESSION['id_pengguna'];

/*POST: AJUKAN PEMINJAMAN*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajukan_peminjaman'])) {

    $id_barang_arr = $_POST['id_barang'] ?? [];
    $jumlah_arr    = $_POST['jumlah'] ?? [];
    $tujuan        = trim(mysqli_real_escape_string($conn, $_POST['tujuan']));
    $kelas         = trim(mysqli_real_escape_string($conn, $_POST['kelas'] ?? ''));
    $tgl_pinjam    = trim(mysqli_real_escape_string($conn, $_POST['tgl_pinjam'] ?? ''));

    $redirect_base = "dashboard.php";

    if (empty($id_barang_arr) || empty($tujuan) || empty($tgl_pinjam)) {
        header("Location: $redirect_base?error=".urlencode("Barang, tujuan, dan tanggal wajib diisi."));
        exit;
    }

    $role_user = $_SESSION['role'] ?? 'murid';
    $items_to_insert = [];

    /* Validasi semua barang di keranjang */
    foreach ($id_barang_arr as $index => $id_b) {
        $id_barang = (int)$id_b;
        $jumlah    = max(1, (int)($jumlah_arr[$index] ?? 1));

        /* Cek barang */
        $brg = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT nama_barang, jumlah, bisa_dipinjam, pinjam_murid, pinjam_guru, pinjam_tendik FROM barang WHERE id_barang=$id_barang"));

        if (!$brg || !$brg['bisa_dipinjam']) {
            $nm = $brg ? $brg['nama_barang'] : 'Barang';
            header("Location: $redirect_base?error=".urlencode("$nm tidak tersedia untuk dipinjam."));
            exit;
        }

        /* Cek izin role */
        $boleh = ($role_user === 'murid'  && $brg['pinjam_murid'])
              || ($role_user === 'guru'   && $brg['pinjam_guru'])
              || ($role_user === 'tendik' && $brg['pinjam_tendik'])
              || $role_user === 'admin';
        if (!$boleh) {
            header("Location: $redirect_base?error=".urlencode("Anda tidak memiliki izin meminjam '{$brg['nama_barang']}'."));
            exit;
        }

        if ($brg['jumlah'] < $jumlah) {
            header("Location: $redirect_base?error=".urlencode("Jumlah '{$brg['nama_barang']}' tidak mencukupi. Tersedia: {$brg['jumlah']} unit."));
            exit;
        }

        /* Cek apakah pengguna sudah ada peminjaman aktif untuk barang yang sama */
        $cek_aktif = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COUNT(*) as t
            FROM peminjaman pm
            JOIN detail_peminjaman dp ON pm.id_peminjaman=dp.id_peminjaman
            WHERE pm.id_pengguna='$id_pengguna'
              AND dp.id_barang=$id_barang
              AND pm.status IN ('menunggu','dipinjam')
        "));
        if ($cek_aktif['t'] > 0) {
            header("Location: $redirect_base?error=".urlencode("Kamu sudah memiliki peminjaman aktif untuk '{$brg['nama_barang']}'."));
            exit;
        }

        $items_to_insert[] = [
            'id_barang' => $id_barang,
            'jumlah'    => $jumlah
        ];
    }

    /* Insert peminjaman dengan status 'menunggu' */
    $today = date('Y-m-d');

    mysqli_query($conn, "
        INSERT INTO peminjaman
          (id_pengguna, tujuan, kelas, tanggal_pengajuan, tanggal_pinjam, status)
        VALUES
          ('$id_pengguna','$tujuan','$kelas','$today','$tgl_pinjam','menunggu')
    ");
    $new_id = mysqli_insert_id($conn);

    foreach ($items_to_insert as $item) {
        $ib = $item['id_barang'];
        $jm = $item['jumlah'];
        mysqli_query($conn, "
            INSERT INTO detail_peminjaman (id_peminjaman, id_barang, jumlah)
            VALUES ($new_id, $ib, $jm)
        ");
    }

    /* CATATAN: jumlah TIDAK dikurangi di sini.
       jumlah dikurangi saat admin menyetujui (status 'menunggu' → 'dipinjam').
       Lihat admin/peminjaman.php handler 'setujui_peminjaman'. */

    header("Location: riwayat.php?success=".urlencode("Pengajuan peminjaman berhasil dikirim. Menunggu persetujuan admin."));
    exit;
}

/* POST: BATALKAN PEMINJAMAN (status menunggu) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batal_peminjaman'])) {

    $id_pm = (int)($_POST['id_peminjaman'] ?? 0);

    if (!$id_pm) {
        header("Location: riwayat.php?error=".urlencode("Data peminjaman tidak valid.")); exit;
    }

    /* Verifikasi kepemilikan & status harus 'menunggu' */
    $pm = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id_peminjaman FROM peminjaman
         WHERE id_peminjaman=$id_pm
           AND id_pengguna='$id_pengguna'
           AND status='menunggu'"));

    if (!$pm) {
        header("Location: riwayat.php?error=".urlencode("Peminjaman tidak ditemukan atau sudah tidak bisa dibatalkan.")); exit;
    }

    /* Hapus detail dulu (FK), lalu peminjaman */
    mysqli_query($conn, "DELETE FROM detail_peminjaman WHERE id_peminjaman=$id_pm");
    mysqli_query($conn, "DELETE FROM peminjaman WHERE id_peminjaman=$id_pm AND id_pengguna='$id_pengguna' AND status='menunggu'");

    header("Location: riwayat.php?success=".urlencode("Pengajuan peminjaman berhasil dibatalkan."));
    exit;
}

/* Jika akses langsung GET (bukan POST), redirect ke dashboard */
header("Location: dashboard.php");
exit;