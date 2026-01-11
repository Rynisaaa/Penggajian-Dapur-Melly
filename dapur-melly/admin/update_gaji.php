<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    $_SESSION['error'] = "ID tidak valid";
    header("Location: penggajian.php");
    exit;
}

// Update status gaji
$result = mysqli_query($conn, "
    UPDATE penggajian 
    SET status_bayar='lunas', tgl_bayar_aktual=NOW()
    WHERE id='$id'
");

if ($result) {
    $_SESSION['success'] = "Status berhasil diubah menjadi LUNAS";
} else {
    $_SESSION['error'] = "Gagal mengubah status";
}

header("Location: penggajian.php");
exit;