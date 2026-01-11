<?php
session_start();
require_once '../config/koneksi.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

/* SIMPAN / UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama   = $_POST['nama'];
    $jual   = $_POST['jual'];
    $posisi = $_POST['posisi'];

    $fotoName = $_FILES['foto']['name'];
    $tmp      = $_FILES['foto']['tmp_name'];

    if ($fotoName) {
        $fotoBaru = time() . '_' . $fotoName;
        move_uploaded_file($tmp, "../assets/produk/$fotoBaru");
    } else {
        $fotoBaru = $_POST['foto_lama'];
    }

    $cek = mysqli_query($conn, "SELECT id FROM produk_unggulan WHERE posisi='$posisi'");
    if (mysqli_num_rows($cek)) {
        mysqli_query($conn,"
            UPDATE produk_unggulan
            SET nama_produk='$nama', foto='$fotoBaru', jumlah_terjual='$jual'
            WHERE posisi='$posisi'
        ");
    } else {
        mysqli_query($conn,"
            INSERT INTO produk_unggulan(nama_produk,foto,jumlah_terjual,posisi)
            VALUES('$nama','$fotoBaru','$jual','$posisi')
        ");
    }

    header("Location: produk_unggulan.php");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Kelola Produk Unggulan</title>
<style>
body{font-family:Poppins;padding:30px}
.card{max-width:500px;margin:auto;background:#fff;padding:25px;border-radius:16px}
input,select,button{width:100%;padding:10px;margin-top:10px}
button{background:#ff7eb3;color:#fff;border:none;border-radius:10px}
</style>
</head>

<body>
<div class="card">
<h2>🏆 Produk Unggulan</h2>

<form method="POST" enctype="multipart/form-data">
<input name="nama" placeholder="Nama Produk" required>
<input type="number" name="jual" placeholder="Jumlah Terjual">
<select name="posisi" required>
    <option value="">-- Posisi --</option>
    <option value="1">Juara 1 (Tengah)</option>
    <option value="2">Juara 2 (Kiri)</option>
    <option value="3">Juara 3 (Kanan)</option>
</select>
<input type="file" name="foto">
<input type="hidden" name="foto_lama">
<button>Simpan</button>
</form>

</div>
</body>
</html>
