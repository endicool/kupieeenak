<?php
require_once '../config/koneksi.php';

if(isset($_POST['simpan'])){

    $user_id = $_POST['user_id'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $expense_date = $_POST['expense_date'];

    $sql = "INSERT INTO expenses(user_id, description, amount, expense_date)
            VALUES(?,?,?,?)";

    $stmt = $koneksi->prepare($sql);
    $stmt->execute([
        $user_id,
        $description,
        $amount,
        $expense_date
    ]);

    header("Location: expense.php");
    exit;
}

$users = $koneksi->query("
    SELECT id,name
    FROM users
    ORDER BY name
")->fetchAll();

$data = $koneksi->query("
    SELECT e.*, u.name
    FROM expenses e
    JOIN users u ON e.user_id = u.id
    ORDER BY e.id DESC
")->fetchAll();

$totalHariIni = $koneksi->query("
    SELECT COALESCE(SUM(amount),0) as total
    FROM expenses
    WHERE expense_date = CURDATE()
")->fetch();
?>

<?php include '../layouts/header.php'; ?>
<?php include '../layouts/menu.php'; ?>

<main class="container">

<div class="row">

    <div class="col-md-4">

        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white">
                Input Pengeluaran
            </div>

            <div class="card-body">

                <form method="POST">

                    <div class="mb-3">
                        <label>Petugas</label>
                        <select name="user_id" class="form-control" required>
                            <option value="">Pilih Petugas</option>

                            <?php foreach($users as $u){ ?>
                                <option value="<?= $u['id'] ?>">
                                    <?= $u['name'] ?>
                                </option>
                            <?php } ?>

                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Keterangan</label>
                        <textarea
                            name="description"
                            class="form-control"
                            required></textarea>
                    </div>

                    <div class="mb-3">
                        <label>Nominal</label>
                        <input
                            type="number"
                            name="amount"
                            class="form-control"
                            required>
                    </div>

                    <div class="mb-3">
                        <label>Tanggal</label>
                        <input
                            type="date"
                            name="expense_date"
                            class="form-control"
                            value="<?= date('Y-m-d') ?>"
                            required>
                    </div>

                    <button
                        type="submit"
                        name="simpan"
                        class="btn btn-danger w-100">
                        Simpan Pengeluaran
                    </button>

                </form>

            </div>
        </div>

        <div class="card mt-3 border-danger">
            <div class="card-body text-center">
                <h6>Total Pengeluaran Hari Ini</h6>

                <h3 class="text-danger">
                    Rp <?= number_format($totalHariIni['total'],0,',','.') ?>
                </h3>
            </div>
        </div>

    </div>

    <div class="col-md-8">

        <div class="card shadow-sm">

            <div class="card-header bg-dark text-white">
                Data Pengeluaran
            </div>

            <div class="card-body">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Petugas</th>
                            <th>Keterangan</th>
                            <th>Nominal</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php
                    $no = 1;
                    foreach($data as $d){
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= $d['expense_date'] ?></td>
                            <td><?= $d['name'] ?></td>
                            <td><?= $d['description'] ?></td>
                            <td>
                                Rp <?= number_format($d['amount'],0,',','.') ?>
                            </td>
                        </tr>
                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</main>

<?php include '../layouts/footer.php'; ?>