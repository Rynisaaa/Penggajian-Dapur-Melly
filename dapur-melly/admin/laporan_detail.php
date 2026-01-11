<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'owner')) {
    header("Location: ../index.php");
    exit;
}

// Ambil parameter bulan dan tahun
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Query data pendapatan bulan ini
$query = mysqli_query($conn, "
    SELECT * 
    FROM laporan_bulanan 
    WHERE bulan = '$bulan' AND tahun = '$tahun'
    LIMIT 1
");

$data = mysqli_fetch_assoc($query);

include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Pendapatan <?= $bulan ?>/<?= $tahun ?> - Dapur Melly</title>
    <style>
        /* Tambahkan styling sesuai kebutuhan */
        .detail-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .back-btn {
            background: #ff7eb3;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="main">
        <button class="back-btn" onclick="history.back()">← Kembali</button>
        
        <div class="detail-container">
            <h1>Detail Pendapatan Bulan <?= $bulan ?>/<?= $tahun ?></h1>
            
            <?php if ($data): ?>
                <p><strong>Pendapatan:</strong> Rp <?= number_format($data['pendapatan'], 0, ',', '.') ?></p>
                <p><strong>Terakhir Update:</strong> <?= $data['updated_at'] ?></p>
                <!-- Tambahkan detail lain sesuai kebutuhan -->
            <?php else: ?>
                <p>Data pendapatan untuk bulan ini belum tersedia.</p>
            <?php endif; ?>
            
            <!-- Tambahkan grafik atau breakdown pendapatan di sini -->
        </div>
    </div>
</body>
</html>