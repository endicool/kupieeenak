<?php
session_start();
require_once '../config/koneksi.php';

// ===================== PROSES CRUD =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- TAMBAH USER ----
    if ($action === 'tambah') {
        $name     = trim($_POST['name']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role     = $_POST['role'];

        // Cek username sudah dipakai atau belum
        $cek = $koneksi->prepare("SELECT id FROM users WHERE username = :username");
        $cek->execute([':username' => $username]);

        if ($cek->rowCount() > 0) {
            $_SESSION['error'] = "Username '$username' sudah digunakan, silakan pilih username lain.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $koneksi->prepare("INSERT INTO users (name, username, password, role) 
                                        VALUES (:name, :username, :password, :role)");
            $stmt->execute([
                ':name'     => $name,
                ':username' => $username,
                ':password' => $hash,
                ':role'     => $role
            ]);
            $_SESSION['success'] = "User '$name' berhasil ditambahkan.";
        }
        header("Location: user.php");
        exit;
    }

    // ---- EDIT USER ----
    if ($action === 'edit') {
        $id       = $_POST['id'];
        $name     = trim($_POST['name']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role     = $_POST['role'];

        // Cek username dipakai oleh user lain
        $cek = $koneksi->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
        $cek->execute([':username' => $username, ':id' => $id]);

        if ($cek->rowCount() > 0) {
            $_SESSION['error'] = "Username '$username' sudah digunakan oleh user lain.";
        } else {
            if (!empty($password)) {
                // Password baru diisi -> update termasuk password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $koneksi->prepare("UPDATE users 
                                            SET name=:name, username=:username, password=:password, role=:role 
                                            WHERE id=:id");
                $stmt->execute([
                    ':name'     => $name,
                    ':username' => $username,
                    ':password' => $hash,
                    ':role'     => $role,
                    ':id'       => $id
                ]);
            } else {
                // Password dikosongkan -> tidak diubah
                $stmt = $koneksi->prepare("UPDATE users 
                                            SET name=:name, username=:username, role=:role 
                                            WHERE id=:id");
                $stmt->execute([
                    ':name'     => $name,
                    ':username' => $username,
                    ':role'     => $role,
                    ':id'       => $id
                ]);
            }
            $_SESSION['success'] = "User '$name' berhasil diperbarui.";
        }
        header("Location: user.php");
        exit;
    }

    // ---- HAPUS USER ----
    if ($action === 'hapus') {
        $id = $_POST['id'];

        // Cegah hapus user yang sudah punya riwayat transaksi (relasi ke orders)
        $cek = $koneksi->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :id");
        $cek->execute([':id' => $id]);

        if ($cek->fetchColumn() > 0) {
            $_SESSION['error'] = "User tidak bisa dihapus karena memiliki riwayat transaksi.";
        } else {
            $stmt = $koneksi->prepare("DELETE FROM users WHERE id=:id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success'] = "User berhasil dihapus.";
        }
        header("Location: user.php");
        exit;
    }
}

// ===================== FILTER & SEARCH =====================
$search      = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? '';

$sql    = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (name LIKE :search OR username LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($filter_role !== '') {
    $sql .= " AND role = :role";
    $params[':role'] = $filter_role;
}
$sql .= " ORDER BY role ASC, name ASC";

$stmt = $koneksi->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include '../layouts/header.php';
include '../layouts/menu.php';
?>

<main class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">👤 Data Kasir & Admin</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
            + Tambah User
        </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Filter & Search -->
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="role" class="form-select" onchange="this.form.submit()">
                <option value="">Semua Role</option>
                <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="kasir" <?= $filter_role === 'kasir' ? 'selected' : '' ?>>Kasir</option>
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Cari nama / username..." value="<?= htmlspecialchars($search) ?>">
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
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Dibuat</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $i => $u): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                        <?= ucfirst($u['role']) ?>
                                    </span>
                                </td>
                                <td><?= date('d-m-Y', strtotime($u['created_at'])) ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-warning"
                                        data-bs-toggle="modal" data-bs-target="#modalEdit"
                                        data-id="<?= $u['id'] ?>"
                                        data-name="<?= htmlspecialchars($u['name']) ?>"
                                        data-username="<?= htmlspecialchars($u['username']) ?>"
                                        data-role="<?= $u['role'] ?>">
                                        Edit
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user ini?');">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted">Belum ada data user.</td></tr>
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
                <h5 class="modal-title">Tambah User Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="kasir">Kasir</option>
                        <option value="admin">Admin</option>
                    </select>
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
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="edit_username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password" class="form-control" minlength="6" placeholder="Kosongkan jika tidak diubah">
                    <small class="text-muted">Biarkan kosong jika tidak ingin mengubah password.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" id="edit_role" class="form-select" required>
                        <option value="kasir">Kasir</option>
                        <option value="admin">Admin</option>
                    </select>
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
    document.getElementById('edit_username').value = btn.getAttribute('data-username');
    document.getElementById('edit_role').value = btn.getAttribute('data-role');
});
</script>

<?php include '../layouts/footer.php'; ?>