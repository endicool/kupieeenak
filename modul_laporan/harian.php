<?php
// Hubungkan ke database
require_once '../config/koneksi.php';

// Ambil tanggal hari ini (Format: YYYY-MM-DD)
$hari_ini = date('Y-m-d');

try {
    // 1. Hitung Total Pendapatan Hari Ini (Hanya transaksi yang sudah 'paid')
    $stmtOmset = $koneksi->prepare("SELECT SUM(total_amount) AS total FROM orders WHERE DATE(created_at) = ? AND status = 'paid'");
    $stmtOmset->execute([$hari_ini]);
    $pendapatan = $stmtOmset->fetch()['total'] ?? 0;

    // 2. Hitung Total Pengeluaran Hari Ini
    $stmtPengeluaran = $koneksi->prepare("SELECT SUM(amount) AS total FROM expenses WHERE expense_date = ?");
    $stmtPengeluaran->execute([$hari_ini]);
    $pengeluaran = $stmtPengeluaran->fetch()['total'] ?? 0;

    // 3. Hitung Jumlah Transaksi Hari Ini (Baik yang open maupun paid)
    $stmtSelesai = $koneksi->prepare("SELECT COUNT(id) AS total FROM orders WHERE DATE(created_at) = ? AND status = 'paid'");
    $stmtSelesai->execute([$hari_ini]);
    $transaksi_selesai = $stmtSelesai->fetch()['total'] ?? 0;

    $stmtPending = $koneksi->prepare("SELECT COUNT(id) AS total FROM orders WHERE DATE(created_at) = ? AND status = 'open'");
    $stmtPending->execute([$hari_ini]);
    $transaksi_pending = $stmtPending->fetch()['total'] ?? 0;

    // 4. Ambil Semua Riwayat Transaksi Hari Ini untuk Tabel
    $stmtList = $koneksi->prepare("SELECT o.*, u.name AS kasir_name FROM orders o JOIN users u ON o.user_id = u.id WHERE DATE(o.created_at) = ? ORDER BY o.created_at DESC");
    $stmtList->execute([$hari_ini]);
    $orders_hari_ini = $stmtList->fetchAll();

} catch (PDOException $e) {
    die("Gagal memuat laporan harian: " . $e->getMessage());
}
?>

<?php include '../layouts/header.php'; ?>
<?php include '../layouts/menu.php'; ?>

<main class="container">
    <div class="row mb-4">
        <div class="col">
            <h2 class="fw-bold">📊 Laporan & Tutup Kasir Harian</h2>
            <p class="text-muted">Rekapitulasi keuangan pada tanggal: <strong><?= date('d F Y', strtotime($hari_ini)); ?></strong></p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Total Pendapatan (Paid)</h6>
                    <h3 class="fw-bold">Rp <?= number_format($pendapatan, 0, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Total Pengeluaran</h6>
                    <h3 class="fw-bold">Rp <?= number_format($pengeluaran, 0, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Transaksi Selesai</h6>
                    <h3 class="fw-bold"><?= $transaksi_selesai; ?> Nota</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title text-dark-50">Meja Masih Open (Belum Bayar)</h6>
                    <h3 class="fw-bold"><?= $transaksi_pending; ?> Meja</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white fw-bold">
            📋 Aktivitas Transaksi Hari Ini
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>No. Meja</th>
                            <th>Total Tagihan</th>
                            <th>Status</th>
                            <th>Kasir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders_hari_ini)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada aktivitas transaksi hari ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders_hari_ini as $row): ?>
                                <tr>
                                    <td><?= date('H:i:s', strtotime($row['created_at'])); ?> WIB</td>
                                    <td class="fw-bold">Meja <?= $row['table_number']; ?></td>
                                    <td>Rp <?= number_format($row['total_amount'], 0, ',', '.'); ?></td>
                                    <td>
                                        <?php if ($row['status'] == 'paid'): ?>
                                            <span class="badge bg-success">Lunas / Paid</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Masih Makan (Open)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $row['kasir_name']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../layouts/footer.php'; ?>