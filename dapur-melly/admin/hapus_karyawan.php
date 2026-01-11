<?php
require '../config/koneksi.php';
$id = $_GET['id'];
mysqli_query($conn,"DELETE FROM karyawan WHERE id_karyawan='$id'");
header("Location: data_karyawan.php");
