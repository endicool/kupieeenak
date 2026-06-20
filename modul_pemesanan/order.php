<?php
// 1. Hubungkan ke database (Menggunakan config bawaan tim)
require_once '../config/koneksi.php'; 

// Variabel untuk menampung pesan notifikasi
$message = "";
$message_type = "";

// Menggunakan ID user = 1 sesuai data dummy yang sudah kamu masukkan di phpMyAdmin
$current_user_id = 1; 

// --- PROSES 1: MEMBUAT ORDER BARU (BUKA MEJA) ---
if (isset($_POST['buka_meja'])) {
    $table_number = htmlspecialchars($_POST['table_number']);
    
    if (!empty($table_number)) {
        try {
            // Mengubah $pdo menjadi $koneksi sesuai file konfigurasi
            $stmt = $koneksi->prepare("INSERT INTO orders (table_number, user_id, status) VALUES (?, ?, 'open')");
            $stmt->execute([$table_number, $current_user_id]);
            
            $message = "Meja $table_number berhasil dibuka! Silakan tambah pesanan.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Gagal membuka meja: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// --- PROSES 2: MENAMBAH ITEM PESANAN (ORDER DETAIL) ---
if (isset($_POST['tambah_item'])) {
    $order_id = $_POST['order_id'];
    $menu_id = $_POST['menu_id'];
    $qty = intval($_POST['qty']);

    if ($qty > 0 && !empty($order_id) && !empty($menu_id)) {
        try {
            // Mengubah $pdo menjadi $koneksi
            $stmtMenu = $koneksi->prepare("SELECT price FROM menus WHERE id = ?");
            $stmtMenu->execute([$menu_id]);
            $menu = $stmtMenu->fetch();

            if ($menu) {
                $price = $menu['price'];
                $subtotal = $price * $qty;

                // Masukkan ke tabel order_details
                $stmtDetail = $koneksi->prepare("INSERT INTO order_details (order_id, menu_id, qty, subtotal) VALUES (?, ?, ?, ?)");
                $stmtDetail->execute([$order_id, $menu_id, $qty, $subtotal]);

                // Update total_amount di tabel orders utama
                $stmtUpdateOrder = $koneksi->prepare("UPDATE orders SET total_amount = total_amount + ? WHERE id = ?");
                $stmtUpdateOrder->execute([$subtotal, $order_id]);

                $message = "Item berhasil ditambahkan ke pesanan.";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Gagal menambah item: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// --- QUERY DATA UNTUK DITAMPILKAN DI HALAMAN ---
// Mengubah $pdo menjadi $koneksi
$menus = $koneksi->query("SELECT * FROM menus WHERE is_active = 1 ORDER BY category, name")->fetchAll();

$active_orders = $koneksi->query("SELECT o.*, u.name as kasir_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status = 'open' ORDER BY o.created_at DESC")->fetchAll();
?>

<?php include '../layouts/header.php'; ?>
<?php include '../layouts/menu.php'; ?>

<main class="container">
    <div class="row mb-4">
        <div class="col">
            <h2 class="fw-bold">☕ Modul Pemesanan (Buka Meja)</h2>
            <p class="text-muted">Kelola meja aktif dan input hidangan pelanggan di sini.</p>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type; ?> alert-dismissible fade show" role="alert">
            <?= $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold">
                    📌 Buka Meja Baru
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="table_number" class="form-label">Nomor / Nama Meja</label>
                            <input type="text" class="form-control" id="table_number" name="table_number" placeholder="Contoh: 05, 12A, VIP" required>
                        </div>
                        <button type="submit" name="buka_meja" class="btn btn-primary w-100">Buka Meja & Mulai</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold">
                    🛒 Input Pesanan Menu
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="order_id" class="form-label">Pilih Meja Aktif</label>
                                <select class="form-select" id="order_id" name="order_id" required>
                                    <option value="">-- Pilih Meja --</option>
                                    <?php foreach ($active_orders as $order): ?>
                                        <option value="<?= $order['id']; ?>">Meja <?= $order['table_number']; ?> (Rp <?= number_format($order['total_amount'], 0, ',', '.'); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="menu_id" class="form-label">Pilih Menu Makanan/Minuman</label>
                                <select class="form-select" id="menu_id" name="menu_id" required>
                                    <option value="">-- Pilih Menu --</option>
                                    <?php foreach ($menus as $menu): ?>
                                        <option value="<?= $menu['id']; ?>">[<?= ucfirst($menu['category']); ?>] <?= $menu['name']; ?> - Rp <?= number_format($menu['price'], 0, ',', '.'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="qty" class="form-label">Jumlah (Qty)</label>
                                <input type="number" class="form-control" id="qty" name="qty" min="1" value="1" required>
                            </div>
                        </div>
                        <button type="submit" name="tambah_item" class="btn btn-success mt-3 w-100">Tambah ke Pesanan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">
                    📋 Daftar Meja yang Sedang Makan (Status: Open)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>No. Meja</th>
                                    <th>Waktu Masuk</th>
                                    <th>Detail Item Pesanan</th>
                                    <th>Total Sementara</th>
                                    <th>Kasir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($active_orders)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Tidak ada meja aktif saat ini. Silakan buka meja baru!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($active_orders as $order): ?>
                                        <tr>
                                            <td class="fw-bold text-primary fs-5">Meja <?= $order['table_number']; ?></td>
                                            <td><?= date('d M Y, H:i', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <ul class="list-unstyled mb-0">
                                                    <?php
                                                    // Mengubah $pdo menjadi $koneksi di detail item
                                                    $stmtItems = $koneksi->prepare("SELECT od.*, m.name FROM order_details od JOIN menus m ON od.menu_id = m.id WHERE od.order_id = ?");
                                                    $stmtItems->execute([$order['id']]);
                                                    $items = $stmtItems->fetchAll();
                                                    
                                                    if(empty($items)) {
                                                        echo "<span class='text-muted italic'>Belum ada pesanan</span>";
                                                    } else {
                                                        foreach ($items as $item) {
                                                            echo "<li>• {$item['name']} <strong>(x{$item['qty']})</strong></li>";
                                                        }
                                                    }
                                                    ?>
                                                </ul>
                                            </td>
                                            <td class="fw-bold text-success">Rp <?= number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                            <td><span class="badge bg-secondary"><?= $order['kasir_name']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../layouts/footer.php'; ?>