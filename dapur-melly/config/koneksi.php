<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "dapur_melly";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// =====================
// SET TIMEZONE KE ASIA/JAKARTA (PENTING!)
// =====================
date_default_timezone_set('Asia/Jakarta');

// Set timezone untuk MySQL juga
mysqli_query($conn, "SET time_zone = '+07:00'");

mysqli_set_charset($conn, "utf8");
?>