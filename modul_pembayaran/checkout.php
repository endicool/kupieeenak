<?php
// 1. Ambil koneksi database dan template UI
// Sesuaikan path arah folder karena checkout.php berada di dalam /modul_pembayaran
require_once '../config/koneksi.php';
include_once '../layouts/header.php';
include_once '../layouts/menu.php';

$message = '';
$message_type = '';

// SIMULASI USER LOGGED IN (Karena sistem login belum digabung, kita asumsikan user_id = 1)
$user_id_kasir = 1; 

// ==========================================
// PROSES AKSI 1: PROSES PEMBAYARAN (CHECKOUT)
// ==========================================
if (isset($_POST['bayar_pesanan'])) {
    $order_id = $_POST['order_id'];
    $total_amount = $_POST['total_amount'];
    $amount_paid = $_POST['amount_paid'];

    if ($amount_paid < $total_amount) {
        $message = "Uang yang dibayarkan kurang!";
        $message_type = "danger";
    } else {
        $change_amount = $amount_paid - $total_amount;

        try {
            // Update status order menjadi 'paid', isi nominal bayar dan kembalian
            $stmt = $koneksi->prepare("UPDATE orders SET 
                                        status = 'paid', 
                                        amount_paid = :amount_paid, 
                                        change_amount = :change_amount,
                                        user_id = :user_id
                                       WHERE id = :id");
            $stmt->execute([
                ':amount_paid' => $amount_paid,
                ':change_amount' => $change_amount,
                ':user_id' => $user_id_kasir,
                ':id' => $order_id
            ]);

            $message = "Pembayaran Berhasil! Kembalian: Rp " . number_format($change_amount, 0, ',', '.');
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Gagal memproses pembayaran: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// ==========================================
// PROSES AKSI 2: TAMBAH PESANAN DI TEMPAT
// ==========================================
if (isset($_POST['tambah_item'])) {
    $order_id = $_POST['order_id'];
    $menu_id = $_POST['menu_id'];
    $qty = (int)$_POST['qty'];

    if ($qty > 0) {
        try {
            // Ambil harga menu terkini
            $stmtMenu = $koneksi->prepare("SELECT price FROM menus WHERE id = :id");
            $stmtMenu->execute([':id' => $menu_id]);
            $menu = $stmtMenu->fetch();

            if ($menu) {
                $subtotal = $menu['price'] * $qty;

                // Cek apakah menu ini sudah ada di detail order sebelumnya
                $stmtCheck = $koneksi->prepare("SELECT id, qty FROM order_details WHERE order_id = :order_id AND menu_id = :menu_id");
                $stmtCheck->execute([':order_id' => $order_id, ':menu_id' => $menu_id]);
                $existingItem = $stmtCheck->fetch();

                if ($existingItem) {
                    // Jika sudah ada, update qty dan subtotalnya
                    $new_qty = $existingItem['qty'] + $qty;
                    $new_subtotal = $menu['price'] * $new_qty;
                    
                    $stmtUpdateDetail = $koneksi->prepare("UPDATE order_details SET qty = :qty, subtotal = :subtotal WHERE id = :id");
                    $stmtUpdateDetail->execute([':qty' => $new_qty, ':subtotal' => $new_subtotal, ':id' => $existingItem['id']]);
                } else {
                    // Jika belum ada, insert baru
                    $stmtInsertDetail = $koneksi->prepare("INSERT INTO order_details (order_id, menu_id, qty, subtotal) VALUES (:order_id, :menu_id, :qty, :subtotal)");
                    $stmtInsertDetail->execute([':order_id' => $order_id, ':menu_id' => $menu_id, ':qty' => $qty, ':subtotal' => $subtotal]);
                }

                // Hitung ulang Total Amount di tabel orders
                $stmtTotal = $koneksi->prepare("SELECT SUM(subtotal) as total FROM order_details WHERE order_id = :order_id");
                $stmtTotal->execute([':order_id' => $order_id]);
                $totalData = $stmtTotal->fetch();

                $stmtUpdateOrder = $koneksi->prepare("UPDATE orders SET total_amount = :total WHERE id = :order_id");
                $stmtUpdateOrder->execute([':total' => $totalData['total'], ':order_id' => $order_id]);

                $message = "Item berhasil ditambahkan ke pesanan!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Gagal menambah item: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// ==========================================
// AMBIL DATA UNTUK INTERFACE
// ==========================================
// 1. Ambil semua meja yang statusnya 'open' (Belum Bayar)
$orders_open = $koneksi->query("SELECT o.*, u.name as kasir_nama 
                                FROM orders o 
                                JOIN users u ON o.user_id = u.id 
                                WHERE o.status = 'open' 
                                ORDER BY o.created_at DESC")->fetchAll();

// 2. Ambil semua data menu aktif untuk dropdown tambah pesanan
$menus = $koneksi->query("SELECT * FROM menus WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// 3. Jika kasir memilih salah satu meja/order untuk diproses
$selected_order = null;
$order_details = [];
if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    
    // Detail Order Utama
    $stmt = $koneksi->prepare("SELECT * FROM orders WHERE id = :id AND status = 'open'");
    $stmt->execute([':id' => $order_id]);
    $selected_order = $stmt->fetch();

    if ($selected_order) {
        // Detail Item Makanan/Minuman di dalam order tersebut
        $stmtDetail = $koneksi->prepare("SELECT od.*, m.name as menu_name, m.price as menu_price 
                                         FROM order_details od 
                                         JOIN menus m ON od.menu_id = m.id 
                                         WHERE od.order_id = :order_id");
        $stmtDetail->execute([':order_id' => $order_id]);
        $order_details = $stmtDetail->fetchAll();
    }
}
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">💰 Modul Kasir: Pembayaran & Meja</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type; ?> alert-dismissible fade show" role="alert">
                    <?= $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">🪑 Daftar Meja Aktif (Belum Bayar)</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($orders_open)): ?>
                        <div class="p-3 text-center text-muted">Tidak ada transaksi/meja yang aktif.</div>
                    <?php else: ?>
                        <?php foreach ($orders_open as $row): ?>
                            <a href="checkout.php?order_id=<?= $row['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= (isset($_GET['order_id']) && $_GET['order_id'] == $row['id']) ? 'active' : ''; ?>">
                                <div>
                                    <strong class="d-block">Meja: <?= htmlspecialchars($row['table_number']); ?></strong>
                                    <small class="<?= (isset($_GET['order_id']) && $_GET['order_id'] == $row['id']) ? 'text-white-50' : 'text-muted'; ?>">Dibuat: <?= date('H:i', strtotime($row['created_at'])); ?></small>
                                </div>
                                <span class="badge bg-warning text-dark rounded-pill fw-bold">Rp <?= number_format($row['total_amount'], 0, ',', '.'); ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <?php if ($selected_order): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">📋 Rincian Meja <?= htmlspecialchars($selected_order['table_number']); ?></h5>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalTambahPesanan">+ Tambah Menu</button>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Menu</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Harga</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_details as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['menu_name']); ?></td>
                                        <td class="text-center"><?= $item['qty']; ?></td>
                                        <td class="text-end">Rp <?= number_format($item['menu_price'], 0, ',', '.'); ?></td>
                                        <td class="text-end">Rp <?= number_format($item['subtotal'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-secondary fw-bold">
                                    <td colspan="3" class="text-end">TOTAL YANG HARUS DIBAYAR:</td>
                                    <td class="text-end text-danger fs-5">Rp <?= number_format($selected_order['total_amount'], 0, ',', '.'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark fw-bold">
                        🧮 Proses Pembayaran
                    </div>
                    <div class="card-body">
                        <form action="checkout.php" method="POST" onsubmit="return validasiBayar();">
                            <input type="hidden" name="order_id" value="<?= $selected_order['id']; ?>">
                            <input type="hidden" id="total_amount" name="total_amount" value="<?= $selected_order['total_amount']; ?>">
                            
                            <div class="mb-3">
                                <label for="amount_paid" class="form-label fw-bold">Uang Tunai Pelanggan (Rp)</label>
                                <input type="number" class="form-control form-control-lg text-end fw-bold text-success" id="amount_paid" name="amount_paid" placeholder="Contoh: 50000" min="<?= $selected_order['total_amount']; ?>" required autocomplete="off">
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-muted">Kembalian</label>
                                <div class="form-control form-control-lg bg-light text-end fw-bold text-muted fs-4" id="kembalian_label">Rp 0</div>
                            </div>

                            <button type="submit" name="bayar_pesanan" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm">Selesaikan Transaksi & Cetak</button>
                        </form>
                    </div>
                </div>

                <div class="modal fade" id="modalTambahPesanan" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="checkout.php?order_id=<?= $selected_order['id']; ?>" method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Tambah Menu ke Meja <?= htmlspecialchars($selected_order['table_number']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="order_id" value="<?= $selected_order['id']; ?>">
                                    <div class="mb-3">
                                        <label for="menu_id" class="form-label">Pilih Menu</label>
                                        <select class="form-select" id="menu_id" name="menu_id" required>
                                            <option value="">-- Pilih Makanan / Minuman --</option>
                                            <?php foreach ($menus as $m): ?>
                                                <option value="<?= $m['id']; ?>"><?= htmlspecialchars($m['name']); ?> (Rp <?= number_format($m['price'], 0, ',', '.'); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="qty" class="form-label">Jumlah (Qty)</label>
                                        <input type="number" class="form-control" id="qty" name="qty" value="1" min="1" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" name="tambah_item" class="btn btn-success">Masukkan Order</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="h-100 d-flex flex-column justify-content-center align-items-center border rounded bg-white p-5 text-muted shadow-sm">
                    <span class="fs-1">☕</span>
                    <h5 class="mt-3">Silakan Pilih Meja di Sebelah Kiri</h5>
                    <p class="small text-center text-wrap">Klik salah satu nomor meja aktif untuk melakukan rincian pesanan, menambah item, atau melakukan transaksi pembayaran.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    const amountPaidInput = document.getElementById('amount_paid');
    const totalAmount = parseFloat(document.getElementById('total_amount')?.value || 0);
    const kembalianLabel = document.getElementById('kembalian_label');

    if (amountPaidInput) {
        amountPaidInput.addEventListener('input', function() {
            const bayar = parseFloat(this.value) || 0;
            const kembalian = bayar - totalAmount;

            if (kembalian >= 0) {
                kembalianLabel.innerText = "Rp " + kembalian.toLocaleString('id-ID');
                kembalianLabel.classList.remove('text-muted', 'text-danger');
                kembalianLabel.classList.add('text-primary');
            } else {
                kembalianLabel.innerText = "Uang Kurang!";
                kembalianLabel.classList.remove('text-muted', 'text-primary');
                kembalianLabel.classList.add('text-danger');
            }
        });
    }

    function validasiBayar() {
        const bayar = parseFloat(amountPaidInput.value) || 0;
        if (bayar < totalAmount) {
            alert('Uang yang dimasukkan kurang dari total tagihan!');
            return false;
        }
        return true;
    }
</script>

<?php 
include_once '../layouts/footer.php'; 
?>