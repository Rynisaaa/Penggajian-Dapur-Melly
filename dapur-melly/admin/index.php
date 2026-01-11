<?php
session_start();
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
}
?>

<h1>Dashboard Admin</h1>
<p>Selamat datang, <?= $_SESSION['nama']; ?></p>
