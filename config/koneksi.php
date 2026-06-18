<?php
// Konfigurasi Database
$host     = 'localhost';    // Server database (biasanya localhost)
$dbname   = 'kupiee_enak';  // Nama database sesuai dengan SQL sebelumnya
$username = 'root';         // Username default XAMPP/MAMP/Laragon
$password = '';             // Password default (kosongkan jika menggunakan XAMPP)

try {
    // Membuat instance koneksi PDO
    $koneksi = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Mengatur mode error agar melempar Exception (memudahkan tim melacak error)
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Mengatur mode fetch default ke Associative Array
    $koneksi->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Menangkap error jika database belum aktif atau kredensial salah
    die("Koneksi Database Gagal: " . $e->getMessage());
}
?>