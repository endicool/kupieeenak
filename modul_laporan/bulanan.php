<?php
require_once '../config/koneksi.php';

// Menentukan bulan dan tahun aktif (default bulan & tahun saat ini jika user belum memfilter)
$bulan_pilihan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun_pilihan = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

try {
    // 1. Pendapatan bulanan (Paid saja)
    $stmtOmset = $koneksi->prepare("SELECT SUM(total_amount) AS total FROM orders WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND status = 'paid'");
    $stmtOmset->execute([$bulan_pilihan, $tahun_pilihan]);
    $total_pendapatan = $stmtOmset->fetch()['total'] ?? 0;

    // 2. Pengeluaran bulanan
    $stmtExpense = $koneksi->prepare("SELECT SUM(amount) AS total FROM expenses WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ?");
    $stmtExpense->execute([$bulan_pilihan, $tahun_pilihan]);
    $total_pengeluaran = $stmtExpense->fetch()['total'] ?? 0;

    // Calculate Net Income
    $laba_bersih = $total_pendapatan - $total_pengeluaran;

    // 3. Grafik/Rekap Pendapatan per Tanggal pada bulan tersebut
    $stmtGrafik = $koneksi->prepare("SELECT DATE(created_at) AS tanggal, SUM(total_amount) AS total_harian, COUNT(id) AS jumlah_nota FROM orders WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND status = 'paid' GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC");
    $stmtGrafik->execute([$bulan_pilihan, $tahun_pilihan]);
    $rekap_harian = $stmtGrafik->fetchAll();

} catch (PDOException $e) {
    die("Gagal memuat laporan bulanan: " . $e->getMessage());
}
?>

<?php include '../layouts/header.php'; ?>
<?php include '../layouts/menu.php'; ?>

<main class="container">
    <div class="row mb-4">
        <div class="col">
            <h2 class="fw-bold">📅 Filter & Rekap Keuangan Bulanan</h2>
            <p class="text-muted">Analisis performa bisnis bulanan Kupiee Enak.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="bulan" class="form-label fw-bold">Pilih Bulan</label>
                    <select class="form-select" id="bulan" name="bulan">
                        <?php
                        for ($m = 1; $m <= 12; $m++) {
                            $monthValue = str_pad($m, 2, '0', STR_PAD_LEFT);
                            $monthName = date('F', mktime(0, 0, 0, $m, 1));
                            $selected = ($monthValue == $bulan_pilihan) ? 'selected' : '';
                            echo "<option value='$monthValue' $selected>$monthName</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="tahun" class="form-label fw-bold">Pilih Tahun</label>
                    <select class="form-select" id="tahun" name="tahun">
                        <?php
                        $tahun_sekarang = date('Y');
                        for ($y = $tahun_sekarang - 3; $y <= $tahun_sekarang; $y++) {
                            $selected = ($y == $tahun_pilihan) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">🔍 Filter Laporan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-start border-success border-4 shadow-sm">
                <div class="card-body">
                    <span class="text-muted small text-uppercase fw-bold">Total Pemasukan</span>
                    <h3 class="text-success fw-bold mt-1">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-danger border-4 shadow-sm">
                <div class="card-body">
                    <span class="text-muted small text-uppercase fw-bold">Total Pengeluaran</span>
                    <h3 class="text-danger fw-bold mt-1">Rp <?= number_format($total_pengeluaran, 0, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-primary border-4 shadow-sm">
                <div class="card-body">
                    <span class="text-muted small text-uppercase fw-bold">Estimasi Keuntungan Bersih</span>
                    <h3 class="text-primary fw-bold mt-1">Rp <?= number_format($laba_bersih, 0, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white fw-bold">
            📈 Rincian Omset per Tanggal
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Jumlah Transaksi Sukses</th>
                            <th>Total Omset Harian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rekap_harian)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Tidak ada data penjualan pada bulan ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rekap_harian as $rekap): ?>
                                <tr>
                                    <td class="fw-bold"><?= date('d M Y', strtotime($rekap['tanggal'])); ?></td>
                                    <td><?= $rekap['jumlah_nota']; ?> Transaksi</td>
                                    <td class="text-success fw-bold">Rp <?= number_format($rekap['total_harian'], 0, ',', '.'); ?></td>
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