<?php
session_start();
require_once '../config/koneksi.php';

// ===================== PROSES CRUD =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- TAMBAH MENU ----
    if ($action === 'tambah') {
        $name      = trim($_POST['name']);
        $category  = $_POST['category'];
        $price     = $_POST['price'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $koneksi->prepare("INSERT INTO menus (name, category, price, is_active) 
                                    VALUES (:name, :category, :price, :is_active)");
        $stmt->execute([
            ':name'      => $name,
            ':category'  => $category,
            ':price'     => $price,
            ':is_active' => $is_active
        ]);
        $_SESSION['success'] = "Menu '$name' berhasil ditambahkan.";
        header("Location: menu.php");
        exit;
    }

    // ---- EDIT MENU ----
    if ($action === 'edit') {
        $id        = $_POST['id'];
        $name      = trim($_POST['name']);
        $category  = $_POST['category'];
        $price     = $_POST['price'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $koneksi->prepare("UPDATE menus 
                                    SET name=:name, category=:category, price=:price, is_active=:is_active 
                                    WHERE id=:id");
        $stmt->execute([
            ':name'      => $name,
            ':category'  => $category,
            ':price'     => $price,
            ':is_active' => $is_active,
            ':id'        => $id
        ]);
        $_SESSION['success'] = "Menu '$name' berhasil diperbarui.";
        header("Location: menu.php");
        exit;
    }

    // ---- HAPUS MENU ----
    if ($action === 'hapus') {
        $id = $_POST['id'];

        // Cek apakah menu sudah pernah dipesan (relasi ke order_details)
        $cek = $koneksi->prepare("SELECT COUNT(*) FROM order_details WHERE menu_id = :id");
        $cek->execute([':id' => $id]);

        if ($cek->fetchColumn() > 0) {
            // Jika sudah ada riwayat transaksi, non-aktifkan saja (soft delete)
            $stmt = $koneksi->prepare("UPDATE menus SET is_active = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success'] = "Menu memiliki riwayat transaksi, sehingga otomatis dinonaktifkan (bukan dihapus permanen).";
        } else {
            $stmt = $koneksi->prepare("DELETE FROM menus WHERE id=:id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success'] = "Menu berhasil dihapus.";
        }
        header("Location: menu.php");
        exit;
    }
}

// ===================== FILTER & SEARCH =====================
$filter_category = $_GET['category'] ?? '';
$search          = $_GET['search'] ?? '';

$sql    = "SELECT * FROM menus WHERE 1=1";
$params = [];

if ($filter_category !== '') {
    $sql .= " AND category = :category";
    $params[':category'] = $filter_category;
}
if ($search !== '') {
    $sql .= " AND name LIKE :search";
    $params[':search'] = "%$search%";
}
$sql .= " ORDER BY category ASC, name ASC";

$stmt = $koneksi->prepare($sql);
$stmt->execute($params);
$menus = $stmt->fetchAll();

include '../layouts/header.php';
include '../layouts/menu.php';
?>

<main class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">🍽️ Data Menu Makanan & Minuman</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
            + Tambah Menu
        </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Filter & Search -->
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="category" class="form-select" onchange="this.form.submit()">
                <option value="">Semua Kategori</option>
                <option value="makanan" <?= $filter_category === 'makanan' ? 'selected' : '' ?>>Makanan</option>
                <option value="minuman" <?= $filter_category === 'minuman' ? 'selected' : '' ?>>Minuman</option>
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Cari nama menu..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-outline-secondary" type="submit">Cari</button>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Nama Menu</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($menus) > 0): ?>
                            <?php foreach ($menus as $i => $menu): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($menu['name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $menu['category'] === 'makanan' ? 'success' : 'info' ?>">
                                        <?= ucfirst($menu['category']) ?>
                                    </span>
                                </td>
                                <td>Rp <?= number_format($menu['price'], 0, ',', '.') ?></td>
                                <td>
                                    <?php if ($menu['is_active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-warning"
                                        data-bs-toggle="modal" data-bs-target="#modalEdit"
                                        data-id="<?= $menu['id'] ?>"
                                        data-name="<?= htmlspecialchars($menu['name']) ?>"
                                        data-category="<?= $menu['category'] ?>"
                                        data-price="<?= $menu['price'] ?>"
                                        data-active="<?= $menu['is_active'] ?>">
                                        Edit
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus menu ini?');">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= $menu['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted">Belum ada data menu.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- ===================== MODAL TAMBAH ===================== -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="tambah">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Menu Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Menu</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kategori</label>
                    <select name="category" class="form-select" required>
                        <option value="makanan">Makanan</option>
                        <option value="minuman">Minuman</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Harga (Rp)</label>
                    <input type="number" name="price" class="form-control" min="0" step="100" required>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" id="activeTambah" checked>
                    <label class="form-check-label" for="activeTambah">Aktif (tampil di halaman pemesanan)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== MODAL EDIT ===================== -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <h5 class="modal-title">Edit Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Menu</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kategori</label>
                    <select name="category" id="edit_category" class="form-select" required>
                        <option value="makanan">Makanan</option>
                        <option value="minuman">Minuman</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Harga (Rp)</label>
                    <input type="number" name="price" id="edit_price" class="form-control" min="0" step="100" required>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="edit_active" class="form-check-input">
                    <label class="form-check-label" for="edit_active">Aktif (tampil di halaman pemesanan)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-warning">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('modalEdit').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_id').value = btn.getAttribute('data-id');
    document.getElementById('edit_name').value = btn.getAttribute('data-name');
    document.getElementById('edit_category').value = btn.getAttribute('data-category');
    document.getElementById('edit_price').value = btn.getAttribute('data-price');
    document.getElementById('edit_active').checked = btn.getAttribute('data-active') === '1';
});
</script>

<?php include '../layouts/footer.php'; ?>
