<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Ambil data karyawan
$karyawanQuery = mysqli_query($conn, "SELECT * FROM karyawan WHERE user_id = $user_id");
$karyawan = mysqli_fetch_assoc($karyawanQuery);
$id_karyawan = $karyawan['id_karyawan'];

// Ambil data gaji
$gajiQuery = mysqli_query($conn, "
    SELECT * FROM penggajian 
    WHERE id_karyawan = $id_karyawan 
    AND bulan = '$bulan' 
    AND tahun = '$tahun'
");
$gaji = mysqli_fetch_assoc($gajiQuery);

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Slip Gaji - Dapur Melly</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .slip { border: 2px solid #333; padding: 20px; max-width: 500px; margin: auto; }
        .header { text-align: center; margin-bottom: 20px; }
        .detail { margin: 10px 0; }
        .total { font-weight: bold; font-size: 18px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="slip">
        <div class="header">
            <h2>SLIP GAJI KARYAWAN</h2>
            <p>Dapur Melly</p>
            <p>Periode: <?= date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)) ?></p>
        </div>
        
        <div class="detail">
            <p>Nama: <?= htmlspecialchars($_SESSION['nama_lengkap']) ?></p>
            <p>ID Karyawan: <?= $karyawan['id_karyawan'] ?></p>
            <p>Jabatan: <?= $karyawan['posisi'] ?></p>
        </div>
        
        <hr>
        
        <div class="detail">
            <p>Gaji Pokok: Rp <?= number_format($karyawan['qaji_pokok'], 0, ',', '.') ?></p>
            <?php if($gaji): ?>
                <p>Tunjangan: Rp <?= number_format($gaji['tunjangan'], 0, ',', '.') ?></p>
                <p>Potongan: Rp <?= number_format($gaji['potongan'], 0, ',', '.') ?></p>
                <p class="total">Total Gaji: Rp <?= number_format($gaji['gaji_bersih'], 0, ',', '.') ?></p>
            <?php else: ?>
                <p class="total">Estimasi Gaji: Rp <?= number_format($karyawan['qaji_pokok'], 0, ',', '.') ?></p>
            <?php endif; ?>
        </div>
        
        <hr>
        
        <div style="text-align: center; margin-top: 30px;">
            <p>Dicetak pada: <?= date('d F Y H:i:s') ?></p>
            <button onclick="window.print()">Cetak Slip</button>
        </div>
    </div>
    
    <script>
        // Auto print saat halaman terbuka
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>