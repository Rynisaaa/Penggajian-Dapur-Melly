<?php
@session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'owner'])) {
    header("Location: ../index.php");
    exit;
}

// SET BULAN DAN TAHUN
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$action = $_POST['action'] ?? '';

// JIKA ADMIN UPDATE DATA
if ($_SESSION['role'] === 'admin' && $action === 'update_laporan') {
    $pendapatan = $_POST['pendapatan'] ?? 0;
    $pendapatan = str_replace(['.', ','], '', $pendapatan);
    
    mysqli_query($conn, "
        INSERT INTO laporan_bulanan (bulan, tahun, pendapatan, updated_by, updated_at)
        VALUES ('$bulan', '$tahun', '$pendapatan', '{$_SESSION['id']}', NOW())
        ON DUPLICATE KEY UPDATE 
        pendapatan = '$pendapatan',
        updated_by = '{$_SESSION['id']}',
        updated_at = NOW()
    ");
    
    $_SESSION['success'] = "Laporan berhasil diperbarui!";
    header("Location: laporan.php?bulan=$bulan&tahun=$tahun");
    exit;
}

// QUERY DATA LAPORAN
// 1. TOTAL KARYAWAN
$total_karyawan = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT COUNT(*) as total FROM karyawan"))['total'];

// 2. KEHADIRAN HARI INI
$hari_ini = date('Y-m-d');
$hadir_hari_ini_result = mysqli_query($conn, 
    "SELECT COUNT(DISTINCT id_karyawan) as hadir 
     FROM presensi 
     WHERE tanggal = '$hari_ini' AND status = 'masuk'");
$hadir_hari_ini = 0;
if ($hadir_hari_ini_result) {
    $hadir_data = mysqli_fetch_assoc($hadir_hari_ini_result);
    $hadir_hari_ini = $hadir_data['hadir'] ?? 0;
}

// 3. KEHADIRAN BULAN INI
$kehadiran_bulan_result = mysqli_query($conn, "
    SELECT 
        COUNT(CASE WHEN status = 'masuk' THEN 1 END) as masuk,
        COUNT(CASE WHEN status = 'izin' THEN 1 END) as izin,
        COUNT(CASE WHEN status = 'sakit' THEN 1 END) as sakit,
        COUNT(CASE WHEN status = 'alpa' THEN 1 END) as alpa
    FROM presensi 
    WHERE MONTH(tanggal) = '$bulan' AND YEAR(tanggal) = '$tahun'
");
$kehadiran_bulan = ['masuk' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
if ($kehadiran_bulan_result) {
    $kehadiran_bulan = mysqli_fetch_assoc($kehadiran_bulan_result) ?? $kehadiran_bulan;
}
$total_masuk = $kehadiran_bulan['masuk'] ?? 0;
$total_izin = $kehadiran_bulan['izin'] ?? 0;
$total_sakit = $kehadiran_bulan['sakit'] ?? 0;
$total_alpa = $kehadiran_bulan['alpa'] ?? 0;

// 4. TOTAL GAJI BULAN INI
$total_gaji_result = mysqli_query($conn, "
    SELECT SUM(gaji_bersih) as total 
    FROM penggajian 
    WHERE bulan = '$bulan' AND tahun = '$tahun' AND status_bayar = 'lunas'
");
$total_gaji = 0;
if ($total_gaji_result) {
    $gaji_data = mysqli_fetch_assoc($total_gaji_result);
    $total_gaji = $gaji_data['total'] ?? 0;
}

// 5. TOTAL POTONGAN GAJI BULAN INI
$total_potongan_result = mysqli_query($conn, "
    SELECT SUM(potongan) as total 
    FROM penggajian 
    WHERE bulan = '$bulan' AND tahun = '$tahun'
");
$total_potongan = 0;
if ($total_potongan_result) {
    $potongan_data = mysqli_fetch_assoc($total_potongan_result);
    $total_potongan = $potongan_data['total'] ?? 0;
}

// 6. PENDAPATAN BULAN INI (DARI INPUT MANUAL)
$pendapatan_data_result = mysqli_query($conn, "
    SELECT pendapatan FROM laporan_bulanan 
    WHERE bulan = '$bulan' AND tahun = '$tahun'
");
$pendapatan = 0;
if ($pendapatan_data_result) {
    $pendapatan_data = mysqli_fetch_assoc($pendapatan_data_result);
    $pendapatan = $pendapatan_data['pendapatan'] ?? 0;
}

// 7. GAJI BULAN LALU (untuk perbandingan)
$bulan_lalu = ($bulan == '01') ? '12' : str_pad($bulan - 1, 2, '0', STR_PAD_LEFT);
$tahun_lalu = ($bulan == '01') ? $tahun - 1 : $tahun;

$total_gaji_lalu_result = mysqli_query($conn, "
    SELECT SUM(gaji_bersih) as total 
    FROM penggajian 
    WHERE bulan = '$bulan_lalu' AND tahun = '$tahun_lalu' AND status_bayar = 'lunas'
");
$total_gaji_lalu = 0;
if ($total_gaji_lalu_result) {
    $gaji_lalu_data = mysqli_fetch_assoc($total_gaji_lalu_result);
    $total_gaji_lalu = $gaji_lalu_data['total'] ?? 0;
}

// 8. PRODUK UNGGULAN
$produk_unggulan = mysqli_query($conn, "
    SELECT * FROM produk_unggulan 
    ORDER BY jumlah_terjual DESC
    LIMIT 10
");

// 9. HITUNG EFEKTIVITAS KARYAWAN (PERBAIKAN: id_karyawan bukan id_karyaman)
$efektivitas = mysqli_query($conn, "
    SELECT 
        u.nama_lengkap,
        k.posisi,
        COUNT(CASE WHEN p.status = 'masuk' THEN 1 END) as masuk,
        COUNT(CASE WHEN p.status = 'izin' THEN 1 END) as izin,
        COUNT(CASE WHEN p.status = 'sakit' THEN 1 END) as sakit,
        COUNT(CASE WHEN p.status = 'alpa' THEN 1 END) as alpa,
        COALESCE(pg.gaji_bersih, 0) as gaji_bersih,
        COALESCE(pg.potongan, 0) as potongan
    FROM karyawan k
    JOIN users u ON k.user_id = u.id
    LEFT JOIN presensi p ON k.id_karyawan = p.id_karyawan 
        AND MONTH(p.tanggal) = '$bulan' 
        AND YEAR(p.tanggal) = '$tahun'
    LEFT JOIN penggajian pg ON k.id_karyawan = pg.id_karyawan 
        AND pg.bulan = '$bulan' 
        AND pg.tahun = '$tahun'
    GROUP BY k.id_karyawan
    ORDER BY masuk DESC
");

// ARRAY NAMA BULAN
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Komprehensif - Dapur Melly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <style>
        :root {
            --primary-pink: #ff7eb3;
            --secondary-pink: #ff5f9e;
            --light-pink: #ffe3f0;
            --dark-pink: #d81b60;
            --success: #4CAF50;
            --warning: #FF9800;
            --danger: #f44336;
            --info: #2196F3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #ffe3f0 0%, #fff 100%);
            min-height: 100vh;
            color: #333;
            transition: margin-left 0.3s ease;
        }

        .main {
            margin-left: 15px;
            padding: 25px;
            transition: all 0.3s;
        }

        /* HEADER */
        .main-header {
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--secondary-pink) 100%);
            padding: 25px 30px;
            border-radius: 20px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 10px 25px rgba(255, 126, 179, 0.3);
            position: relative;
            overflow: hidden;
        }

        .main-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }

        .main-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .main-header .subtitle {
            font-size: 15px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        /* FILTER SECTION */
        .filter-section {
            background: white;
            padding: 20px 25px;
            border-radius: 18px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(255, 126, 179, 0.1);
            border: 1px solid var(--light-pink);
        }

        .filter-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-form select {
            padding: 10px 18px;
            border-radius: 12px;
            border: 2px solid var(--light-pink);
            font-family: 'Poppins';
            font-size: 14px;
            transition: all 0.3s;
            background: white;
            color: #555;
            min-width: 140px;
            cursor: pointer;
        }

        .filter-form select:focus {
            border-color: var(--primary-pink);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 126, 179, 0.15);
        }

        .filter-form button {
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--secondary-pink) 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 126, 179, 0.4);
        }

        /* ACTION BUTTONS */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .action-btn {
            background: linear-gradient(135deg, var(--success) 0%, #45a049 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.4);
        }

        .action-btn.pdf {
            background: linear-gradient(135deg, var(--danger) 0%, #d32f2f 100%);
        }

        .action-btn.pdf:hover {
            box-shadow: 0 8px 20px rgba(244, 67, 54, 0.4);
        }

        .action-btn.excel {
            background: linear-gradient(135deg, var(--success) 0%, #45a049 100%);
        }

        /* CARD STYLES */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(255, 126, 179, 0.1);
            border: 1px solid var(--light-pink);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-pink);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-pink), var(--secondary-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .card-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark-pink);
            margin: 10px 0;
        }

        .card-change {
            font-size: 14px;
            color: #666;
        }

        .card-change.positive {
            color: var(--success);
        }

        .card-change.negative {
            color: var(--danger);
        }

        /* TABLE STYLES */
        .table-section {
            background: white;
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(255, 126, 179, 0.1);
            border: 1px solid var(--light-pink);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--primary-pink);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .data-table th {
            background: linear-gradient(135deg, var(--primary-pink) 0%, var(--secondary-pink) 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .data-table tr:hover {
            background: #fff5f9;
        }

        /* STATUS BADGES */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .badge-danger {
            background: rgba(244, 67, 54, 0.15);
            color: #c62828;
            animation: pulse-danger 2s infinite;
        }

        .badge-warning {
            background: rgba(255, 152, 0, 0.15);
            color: #f57f17;
            animation: pulse-warning 2s infinite;
        }

        .badge-success {
            background: rgba(76, 175, 80, 0.15);
            color: #2e7d32;
        }

        .badge-info {
            background: rgba(33, 150, 243, 0.15);
            color: #1565c0;
        }

        @keyframes pulse-danger {
            0% { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(244, 67, 54, 0); }
            100% { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0); }
        }

        @keyframes pulse-warning {
            0% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(255, 152, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0); }
        }

        /* SWIPER SLIDER */
        .swiper-container {
            background: white;
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(255, 126, 179, 0.1);
            border: 1px solid var(--light-pink);
        }

        .swiper {
            width: 100%;
            height: 320px;
            margin-top: 20px;
        }

        .swiper-slide {
            position: relative;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .swiper-slide:hover {
            transform: translateY(-5px) scale(1.02);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .swiper-slide:hover .product-image {
            transform: scale(1.1);
        }

        .product-info {
            padding: 15px;
            text-align: center;
        }

        .product-name {
            font-weight: 600;
            color: var(--dark-pink);
            margin-bottom: 5px;
        }

        .product-sales {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .best-seller-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(45deg, #ff9800, #ff5722);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 10;
            animation: blink 1.5s infinite;
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* LEADERBOARD */
        .leaderboard-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .leaderboard-card {
            background: white;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(255, 126, 179, 0.1);
            border: 1px solid var(--light-pink);
        }

        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }

        .leaderboard-item:hover {
            background: #fff5f9;
        }

        .leaderboard-item:last-child {
            border-bottom: none;
        }

        .rank {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-pink), var(--secondary-pink));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .karyawan-info {
            flex: 1;
        }

        .karyawan-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .karyawan-position {
            font-size: 12px;
            color: #666;
        }

        .karyawan-stats {
            text-align: right;
        }

        .stat-value {
            font-weight: 600;
            color: var(--dark-pink);
            font-size: 14px;
        }

        .stat-label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
        }

        /* PRINT STYLES */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .print-section, .print-section * {
                visibility: visible;
            }
            
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
                padding: 20px;
            }
            
            .no-print {
                display: none !important;
            }
            
            .data-table {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .data-table th {
                background: #f5f5f5 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
            }
            
            .print-header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 3px solid #000;
            }
            
            .print-title {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            
            .print-subtitle {
                font-size: 16px;
                margin-bottom: 5px;
            }
        }

        /* NOTIFICATION */
        .notification {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideIn 0.3s ease;
            border: 1px solid transparent;
        }

        .notification.success {
            background: rgba(76, 175, 80, 0.1);
            color: #2e7d32;
            border-color: #c8e6c9;
        }

        .notification.error {
            background: rgba(244, 67, 54, 0.1);
            color: #c62828;
            border-color: #ffcdd2;
        }

        .notification .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes slideIn {
            from {
                transform: translateX(-20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* PENDAPATAN FORM */
        .pendapatan-form {
            background: white;
            padding: 25px;
            border-radius: 18px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(255, 126, 179, 0.1);
            border: 1px solid var(--light-pink);
        }

        .pendapatan-form .input-group {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .pendapatan-form input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid var(--light-pink);
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Poppins';
            transition: all 0.3s;
            background: #f9f9f9;
        }

        .pendapatan-form input:focus {
            border-color: var(--primary-pink);
            background: white;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 126, 179, 0.15);
        }

        .pendapatan-form button {
            background: linear-gradient(135deg, var(--success) 0%, #45a049 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pendapatan-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
        }

        .disabled-input {
            background: #f5f5f5;
            cursor: not-allowed;
            color: #999;
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 15px;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .leaderboard-container {
                grid-template-columns: 1fr;
            }
            
            .swiper {
                height: 280px;
            }
        }
    </style>
</head>
<body>
    <!-- INCLUDE SIDEBAR DARI FOLDER INCLUDES -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="main">
        <!-- PRINT HEADER (Hidden by default) -->
        <div class="print-header" style="display: none;">
            <div class="print-title">LAPORAN PENGGAJIAN DAPUR MELLY</div>
            <div class="print-subtitle">Periode: <?= $nama_bulan[$bulan] ?> <?= $tahun ?></div>
            <div class="print-subtitle">Dicetak pada: <?= date('d/m/Y H:i:s') ?></div>
        </div>

        <!-- MAIN HEADER -->
        <div class="main-header no-print">
            <h1><i class="fas fa-chart-line"></i> Laporan Komprehensif</h1>
            <div class="subtitle">Analisis detail kehadiran, penggajian, dan produktivitas karyawan</div>
        </div>

        <!-- NOTIFICATION -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="notification success no-print">
            <span><i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?></span>
            <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>

        <!-- FILTER SECTION -->
        <div class="filter-section no-print">
            <form class="filter-form" method="GET">
                <select name="tahun">
                    <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>>
                        Tahun <?= $y ?>
                    </option>
                    <?php endfor; ?>
                </select>
                
                <select name="bulan">
                    <?php foreach($nama_bulan as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $bulan == $num ? 'selected' : '' ?>>
                        <?= $name ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit">
                    <i class="fas fa-filter"></i> Filter Laporan
                </button>
            </form>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="action-buttons no-print">
            <button class="action-btn excel" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Ekspor ke Excel
            </button>
            <button class="action-btn pdf" onclick="printPDF()">
                <i class="fas fa-file-pdf"></i> Cetak PDF
            </button>
        </div>

        <!-- DASHBOARD CARDS -->
        <div class="dashboard-cards no-print">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-title">Total Karyawan</div>
                </div>
                <div class="card-value"><?= $total_karyawan ?></div>
                <div class="card-change">Orang aktif</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="card-title">Kehadiran Bulan Ini</div>
                </div>
                <div class="card-value"><?= $total_masuk ?></div>
                <div class="card-change">
                    <?= $total_izin ?> Izin, <?= $total_sakit ?> Sakit, <?= $total_alpa ?> Alpa
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="card-title">Pengeluaran Gaji</div>
                </div>
                <div class="card-value">Rp <?= number_format($total_gaji, 0, ',', '.') ?></div>
                <?php 
                $persentase_gaji = 0;
                if ($total_gaji_lalu > 0) {
                    $persentase_gaji = (($total_gaji - $total_gaji_lalu) / $total_gaji_lalu) * 100;
                }
                ?>
                <div class="card-change <?= $persentase_gaji >= 0 ? 'positive' : 'negative' ?>">
                    <?= $persentase_gaji >= 0 ? '↑' : '↓' ?> <?= number_format(abs($persentase_gaji), 1) ?>% dari bulan lalu
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="card-title">Total Potongan</div>
                </div>
                <div class="card-value">Rp <?= number_format($total_potongan, 0, ',', '.') ?></div>
                <div class="card-change">Kedisiplinan karyawan</div>
            </div>
        </div>

        <!-- 1. LAPORAN KEHADIRAN DETAIL -->
        <div class="table-section print-section">
            <div class="section-title">
                <i class="fas fa-calendar-alt"></i>
                Laporan Kehadiran Detail - <?= $nama_bulan[$bulan] ?> <?= $tahun ?>
            </div>
            
            <table class="data-table" id="kehadiranTable">
                <thead>
                    <tr>
                        <th>Nama Karyawan</th>
                        <th>Jabatan</th>
                        <th>Masuk</th>
                        <th>Izin</th>
                        <th>Sakit</th>
                        <th>Alpa</th>
                        <th>Persentase</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Reset pointer untuk query efektivitas
                    if ($efektivitas) {
                        mysqli_data_seek($efektivitas, 0);
                        while($row = mysqli_fetch_assoc($efektivitas)): 
                            $total_hari = max(($row['masuk'] + $row['izin'] + $row['sakit'] + $row['alpa']), 1);
                            $persentase = ($row['masuk'] / $total_hari) * 100;
                            
                            // Tentukan status
                            $status_class = 'badge-success';
                            $status_text = 'Baik';
                            
                            if ($row['alpa'] > 3) {
                                $status_class = 'badge-danger';
                                $status_text = 'Perhatian';
                            } elseif ($row['izin'] > 5) {
                                $status_class = 'badge-warning';
                                $status_text = 'Peringatan';
                            }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($row['posisi']) ?></td>
                        <td><?= $row['masuk'] ?></td>
                        <td><span class="<?= $row['izin'] > 5 ? 'badge-warning' : '' ?>"><?= $row['izin'] ?></span></td>
                        <td><?= $row['sakit'] ?></td>
                        <td><span class="<?= $row['alpa'] > 3 ? 'badge-danger' : '' ?>"><?= $row['alpa'] ?></span></td>
                        <td><?= number_format($persentase, 1) ?>%</td>
                        <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                    </tr>
                    <?php endwhile; 
                    } else { ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #666; padding: 20px;">
                            Tidak ada data kehadiran untuk bulan ini
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- 2. ANALISIS PENGGAJIAN & EFISIENSI -->
        <div class="table-section print-section">
            <div class="section-title">
                <i class="fas fa-chart-line"></i>
                Analisis Penggajian & Efisiensi
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Total Gaji</th>
                        <th>Total Potongan</th>
                        <th>Gaji Bersih</th>
                        <th>% Perubahan</th>
                        <th>Kinerja</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= $nama_bulan[$bulan] ?> <?= $tahun ?></td>
                        <td>Rp <?= number_format($total_gaji + $total_potongan, 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($total_potongan, 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($total_gaji, 0, ',', '.') ?></td>
                        <td>
                            <?php if ($persentase_gaji > 0): ?>
                            <span class="status-badge badge-danger">↑ <?= number_format($persentase_gaji, 1) ?>%</span>
                            <?php elseif ($persentase_gaji < 0): ?>
                            <span class="status-badge badge-success">↓ <?= number_format(abs($persentase_gaji), 1) ?>%</span>
                            <?php else: ?>
                            <span class="status-badge badge-info">0%</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $efficiency = 0;
                            if ($total_karyawan > 0) {
                                $efficiency = ($total_masuk / ($total_karyawan * 20)) * 100; // 20 hari kerja diasumsikan
                            }
                            if ($efficiency >= 90) {
                                echo '<span class="status-badge badge-success">Sangat Baik</span>';
                            } elseif ($efficiency >= 80) {
                                echo '<span class="status-badge badge-info">Baik</span>';
                            } elseif ($efficiency >= 70) {
                                echo '<span class="status-badge badge-warning">Cukup</span>';
                            } elseif ($efficiency > 0) {
                                echo '<span class="status-badge badge-danger">Perlu Perbaikan</span>';
                            } else {
                                echo '<span class="status-badge">Belum Ada Data</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?= $nama_bulan[$bulan_lalu] ?? 'Bulan Lalu' ?> <?= $tahun_lalu ?></td>
                        <td>Rp <?= number_format($total_gaji_lalu, 0, ',', '.') ?></td>
                        <td>-</td>
                        <td>Rp <?= number_format($total_gaji_lalu, 0, ',', '.') ?></td>
                        <td>-</td>
                        <td>-</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 3. GALLERY PRODUK UNGGULAN -->
        <div class="swiper-container no-print">
            <div class="section-title">
                <i class="fas fa-star"></i>
                Gallery Produk Unggulan
            </div>
            
            <div class="swiper mySwiper">
                <div class="swiper-wrapper">
                    <?php 
                    $max_sales = 0;
                    $produk_data = [];
                    
                    if ($produk_unggulan) {
                        while($produk = mysqli_fetch_assoc($produk_unggulan)) {
                            $produk_data[] = $produk;
                            if ($produk['jumlah_terjual'] > $max_sales) {
                                $max_sales = $produk['jumlah_terjual'];
                            }
                        }
                        
                        foreach($produk_data as $produk):
                            $is_best_seller = ($produk['jumlah_terjual'] == $max_sales && $max_sales > 0);
                    ?>
                    <div class="swiper-slide">
                        <?php if($is_best_seller): ?>
                        <div class="best-seller-badge">BEST SELLER</div>
                        <?php endif; ?>
                        
                        <img src="../assets/<?= $produk['foto'] ?>" 
                             alt="<?= htmlspecialchars($produk['nama_produk']) ?>" 
                             class="product-image"
                             onerror="this.src='https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&h=225&fit=crop'">
                        
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($produk['nama_produk']) ?></div>
                            <div class="product-sales">
                                <i class="fas fa-chart-line"></i> 
                                <?= $produk['jumlah_terjual'] ?> terjual
                                <?php if($produk['posisi'] <= 3): ?>
                                <br><small>Peringkat #<?= $produk['posisi'] ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; 
                    } else { ?>
                    <div class="swiper-slide">
                        <div class="product-info" style="display: flex; align-items: center; justify-content: center; height: 100%;">
                            <div style="text-align: center;">
                                <i class="fas fa-box-open" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                                <div>Tidak ada data produk</div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </div>

        <!-- 4. LEADERBOARD KARYAWAN -->
        <div class="leaderboard-container no-print">
            <!-- KARYAWAN TELADAN -->
            <div class="leaderboard-card">
                <div class="section-title">
                    <i class="fas fa-trophy"></i>
                    Karyawan Teladan
                </div>
                
                <?php 
                $top_karyawan = mysqli_query($conn, "
                    SELECT 
                        u.nama_lengkap,
                        k.posisi,
                        COUNT(CASE WHEN p.status = 'masuk' THEN 1 END) as masuk,
                        COUNT(CASE WHEN p.status = 'alpa' THEN 1 END) as alpa
                    FROM karyawan k
                    JOIN users u ON k.user_id = u.id
                    LEFT JOIN presensi p ON k.id_karyawan = p.id_karyawan 
                        AND MONTH(p.tanggal) = '$bulan' 
                        AND YEAR(p.tanggal) = '$tahun'
                    GROUP BY k.id_karyawan
                    ORDER BY masuk DESC, alpa ASC
                    LIMIT 5
                ");
                
                $rank = 1;
                if ($top_karyawan && mysqli_num_rows($top_karyawan) > 0):
                    while($row = mysqli_fetch_assoc($top_karyawan)):
                ?>
                <div class="leaderboard-item">
                    <div class="rank"><?= $rank ?></div>
                    <div class="karyawan-info">
                        <div class="karyawan-name"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                        <div class="karyawan-position"><?= htmlspecialchars($row['posisi']) ?></div>
                    </div>
                    <div class="karyawan-stats">
                        <div class="stat-value"><?= $row['masuk'] ?> hari</div>
                        <div class="stat-label"><?= $row['alpa'] ?> alpa</div>
                    </div>
                </div>
                <?php 
                    $rank++;
                    endwhile;
                else: 
                ?>
                <div style="text-align: center; color: #666; padding: 20px;">
                    Tidak ada data karyawan teladan
                </div>
                <?php endif; ?>
            </div>

            <!-- KARYAWAN EVALUASI -->
            <div class="leaderboard-card">
                <div class="section-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Karyawan Evaluasi
                </div>
                
                <?php 
                $bottom_karyawan = mysqli_query($conn, "
                    SELECT 
                        u.nama_lengkap,
                        k.posisi,
                        COUNT(CASE WHEN p.status = 'alpa' THEN 1 END) as alpa,
                        COUNT(CASE WHEN p.status = 'izin' THEN 1 END) as izin
                    FROM karyawan k
                    JOIN users u ON k.user_id = u.id
                    LEFT JOIN presensi p ON k.id_karyawan = p.id_karyawan 
                        AND MONTH(p.tanggal) = '$bulan' 
                        AND YEAR(p.tanggal) = '$tahun'
                    GROUP BY k.id_karyawan
                    HAVING COUNT(CASE WHEN p.status = 'alpa' THEN 1 END) > 0 
                        OR COUNT(CASE WHEN p.status = 'izin' THEN 1 END) > 3
                    ORDER BY alpa DESC, izin DESC
                    LIMIT 5
                ");
                
                $rank = 1;
                if ($bottom_karyawan && mysqli_num_rows($bottom_karyawan) > 0):
                    while($row = mysqli_fetch_assoc($bottom_karyawan)):
                ?>
                <div class="leaderboard-item">
                    <div class="rank" style="background: linear-gradient(135deg, #f44336, #d32f2f);"><?= $rank ?></div>
                    <div class="karyawan-info">
                        <div class="karyawan-name"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                        <div class="karyawan-position"><?= htmlspecialchars($row['posisi']) ?></div>
                    </div>
                    <div class="karyawan-stats">
                        <div class="stat-value" style="color: #f44336;"><?= $row['alpa'] ?> alpa</div>
                        <div class="stat-label"><?= $row['izin'] ?> izin</div>
                    </div>
                </div>
                <?php 
                    $rank++;
                    endwhile;
                else: 
                ?>
                <div style="text-align: center; color: #666; padding: 20px;">
                    Semua karyawan hadir dengan baik
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 5. PENDAPATAN FORM -->
        <div class="pendapatan-form no-print">
            <div class="section-title">
                <i class="fas fa-wallet"></i>
                Input Pendapatan Bulanan
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_laporan">
                
                <div class="input-group">
                    <input type="text" 
                           name="pendapatan" 
                           value="<?= number_format($pendapatan, 0, ',', '.') ?>" 
                           placeholder="Masukkan total pendapatan bulan ini"
                           <?= $_SESSION['role'] === 'owner' ? 'class="disabled-input" readonly' : '' ?>
                           required>
                    
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <button type="submit">
                        <i class="fas fa-save"></i> Simpan Pendapatan
                    </button>
                    <?php else: ?>
                    <button type="button" class="disabled-input" style="background: #ccc; cursor: not-allowed;">
                        <i class="fas fa-lock"></i> Hanya Admin
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#kehadiranTable').DataTable({
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'action-btn excel'
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'action-btn pdf'
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'action-btn'
                }
            ],
            pageLength: 10,
            language: {
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ hingga _END_ dari _TOTAL_ data",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "→",
                    previous: "←"
                }
            }
        });
        
        // Initialize Swiper
        var swiper = new Swiper(".mySwiper", {
            slidesPerView: 3,
            spaceBetween: 20,
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            breakpoints: {
                320: {
                    slidesPerView: 1,
                    spaceBetween: 10
                },
                768: {
                    slidesPerView: 2,
                    spaceBetween: 15
                },
                1024: {
                    slidesPerView: 3,
                    spaceBetween: 20
                }
            }
        });
        
        // Format input pendapatan
        const pendapatanInput = document.querySelector('input[name="pendapatan"]');
        if (pendapatanInput && !pendapatanInput.classList.contains('disabled-input')) {
            pendapatanInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^\d]/g, '');
                if (value) {
                    value = parseInt(value).toLocaleString('id-ID');
                    e.target.value = value;
                }
            });
            
            // Format nilai awal
            let initialValue = pendapatanInput.value.replace(/[^\d]/g, '');
            if (initialValue) {
                pendapatanInput.value = parseInt(initialValue).toLocaleString('id-ID');
            }
        }
    });

    // Export to Excel function
    function exportToExcel() {
        $('#kehadiranTable').DataTable().button('.buttons-excel').trigger();
    }

    // Print PDF function
    function printPDF() {
        // Show print header
        $('.print-header').show();
        
        // Hide non-print elements
        $('.no-print').hide();
        
        // Add print class to body
        $('body').addClass('printing');
        
        // Print the page
        window.print();
        
        // Restore after printing
        setTimeout(function() {
            $('.print-header').hide();
            $('.no-print').show();
            $('body').removeClass('printing');
        }, 1000);
    }

    // Auto-hide notification
    setTimeout(() => {
        const notification = document.querySelector('.notification');
        if (notification) {
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }, 5000);
        }
    }, 1000);
    </script>
</body>
</html>