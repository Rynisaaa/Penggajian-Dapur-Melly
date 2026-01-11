<?php
session_start();
require '../config/koneksi.php';

if ($_SESSION['role'] !== 'admin') {
    exit;
}

$id = $_POST['id'];
$nama = $_POST['nama'];
$terjual = $_POST['terjual'];

if (!empty($_FILES['foto']['name'])) {
    $file = $_FILES['foto']['name'];
    $tmp = $_FILES['foto']['tmp_name'];
    move_uploaded_file($tmp, "../assets/produk/" . $file);

    $stmt = $conn->prepare(
        "UPDATE produk_unggulan SET nama=?, terjual=?, foto=? WHERE id=?"
    );
    $stmt->bind_param("sisi", $nama, $terjual, $file, $id);
} else {
    $stmt = $conn->prepare(
        "UPDATE produk_unggulan SET nama=?, terjual=? WHERE id=?"
    );
    $stmt->bind_param("sii", $nama, $terjual, $id);
}

$stmt->execute();
header("Location: dashboard.php");
exit;
