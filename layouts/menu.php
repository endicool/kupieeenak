<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="/kupiee_enak/index.php">☕ Kupiee Enak</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/kupiee_enak/index.php">Dashboard</a>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Master Data</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/kupiee_enak/modul_master/menu.php">Data Menu</a></li>
                        <li><a class="dropdown-item" href="/kupiee_enak/modul_master/user.php">Data Kasir</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/kupiee_enak/modul_pemesanan/order.php">Pesan (Buka Meja)</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="/kupiee_enak/modul_pembayaran/checkout.php">Pembayaran</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/kupiee_enak/modul_pengeluaran/expense.php">Pengeluaran</a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Laporan</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/kupiee_enak/modul_laporan/harian.php">Laporan Harian</a></li>
                        <li><a class="dropdown-item" href="/kupiee_enak/modul_laporan/bulanan.php">Laporan Bulanan</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>