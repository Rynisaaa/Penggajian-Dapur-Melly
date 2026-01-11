<?php
require '../config/koneksi.php';

$id     = $_POST['id'];
$nama   = $_POST['nama'];
$jual   = $_POST['jual'];

$foto = $_POST['foto_lama'];

if (!empty($_FILES['foto']['name'])) {
    $foto = time().'_'.$_FILES['foto']['name'];
    move_uploaded_file($_FILES['foto']['tmp_name'], "../assets/$foto");
}

$stmt = mysqli_prepare($conn,
    "UPDATE produk_unggulan 
     SET nama_produk=?, jumlah_terjual=?, foto=? 
     WHERE id=?"
);
mysqli_stmt_bind_param($stmt,'sisi',$nama,$jual,$foto,$id);
mysqli_stmt_execute($stmt);

header("Location: dashboard.php");
exit;
