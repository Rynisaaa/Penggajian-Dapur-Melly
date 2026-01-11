<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'dapur_melly';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// SET TIMEZONE untuk koneksi MySQL
$conn->query("SET time_zone = '+07:00'");

// SET TIMEZONE PHP
date_default_timezone_set('Asia/Jakarta');

$conn->set_charset("utf8mb4");

// Optional: untuk debugging
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
?>