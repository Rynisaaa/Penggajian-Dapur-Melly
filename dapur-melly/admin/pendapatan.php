<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Handle delete action
if ($action === 'delete' && $id > 0) {
    mysqli_query($conn, "DELETE FROM pendapatan_harian WHERE id = '$id'");
    $_SESSION['success'] = "Data pendapatan harian berhasil dihapus!";
    header("Location: pendapatan.php?tanggal=$tanggal&bulan=$bulan&tahun=$tahun");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $bulan = date('m', strtotime($tanggal));
    $tahun = date('Y', strtotime($tanggal));
    $offline = str_replace(['.', ','], '', $_POST['offline'] ?? 0);
    $online = str_replace(['.', ','], '', $_POST['online'] ?? 0);
    $total_harian = $offline + $online; // Auto calculate
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');
    $user_id = $_SESSION['id'] ?? 0;
    
    if ($action === 'add' || $action === 'edit') {
        if ($action === 'add') {
            $query = "INSERT INTO pendapatan_harian (tanggal, bulan, tahun, offline, online, total_harian, keterangan, created_by) 
                      VALUES ('$tanggal', '$bulan', '$tahun', '$offline', '$online', '$total_harian', '$keterangan', '$user_id')";
        } else {
            $query = "UPDATE pendapatan_harian 
                      SET tanggal = '$tanggal', bulan = '$bulan', tahun = '$tahun', 
                          offline = '$offline', online = '$online', total_harian = '$total_harian', 
                          keterangan = '$keterangan'
                      WHERE id = '$id'";
        }
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = $action === 'add' 
                ? "Data pendapatan harian berhasil ditambahkan!" 
                : "Data pendapatan harian berhasil diperbarui!";
            
            // Update summary in laporan_bulanan
            updateLaporanBulanan($conn, $bulan, $tahun, $user_id);
        } else {
            $_SESSION['error'] = "Terjadi kesalahan: " . mysqli_error($conn);
        }
        
        header("Location: pendapatan.php?tanggal=$tanggal&bulan=$bulan&tahun=$tahun");
        exit;
    }
}

// Function to update summary in laporan_bulanan
function updateLaporanBulanan($conn, $bulan, $tahun, $user_id) {
    $totalQuery = mysqli_query($conn, "
        SELECT SUM(total_harian) as total 
        FROM pendapatan_harian 
        WHERE bulan = '$bulan' AND tahun = '$tahun'
    ");
    
    $totalData = mysqli_fetch_assoc($totalQuery);
    $totalPendapatan = $totalData['total'] ?? 0;
    
    mysqli_query($conn, "
        INSERT INTO laporan_bulanan (bulan, tahun, pendapatan, updated_by, updated_at)
        VALUES ('$bulan', '$tahun', '$totalPendapatan', '$user_id', NOW())
        ON DUPLICATE KEY UPDATE 
        pendapatan = '$totalPendapatan',
        updated_by = '$user_id',
        updated_at = NOW()
    ");
}

// Get data for selected date
$pendapatanHarian = mysqli_query($conn, "
    SELECT ph.*, u.nama_lengkap as created_by_name
    FROM pendapatan_harian ph
    LEFT JOIN users u ON ph.created_by = u.id
    WHERE ph.bulan = '$bulan' AND ph.tahun = '$tahun'
    ORDER BY ph.tanggal DESC
");

// Calculate monthly totals
$monthlyQuery = mysqli_query($conn, "
    SELECT 
        SUM(offline) as total_offline,
        SUM(online) as total_online,
        SUM(total_harian) as total_harian
    FROM pendapatan_harian 
    WHERE bulan = '$bulan' AND tahun = '$tahun'
");

$monthlyTotals = mysqli_fetch_assoc($monthlyQuery);
$totalOffline = $monthlyTotals['total_offline'] ?? 0;
$totalOnline = $monthlyTotals['total_online'] ?? 0;
$totalPendapatanBulan = $monthlyTotals['total_harian'] ?? 0;

// Get total gaji bulan ini
$totalGajiQuery = mysqli_query($conn, "
    SELECT SUM(gaji_bersih) as total 
    FROM penggajian 
    WHERE bulan = '$bulan' AND tahun = '$tahun' AND status_bayar = 'lunas'
");
$totalGajiData = mysqli_fetch_assoc($totalGajiQuery);
$totalGajiBulan = $totalGajiData['total'] ?? 0;

// Calculate laba bersih
$labaBersih = $totalPendapatanBulan - $totalGajiBulan;

// Get years for filter
$yearsQuery = mysqli_query($conn, "
    SELECT DISTINCT tahun 
    FROM pendapatan_harian 
    ORDER BY tahun DESC
");

// Get data for chart (last 7 days)
$chartQuery = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(tanggal, '%d/%m') as tanggal_label,
        SUM(offline) as offline,
        SUM(online) as online
    FROM pendapatan_harian 
    WHERE tanggal >= DATE_SUB('$tahun-$bulan-01', INTERVAL 7 DAY)
    GROUP BY tanggal
    ORDER BY tanggal DESC
    LIMIT 7
");

$chartLabels = [];
$chartOfflineData = [];
$chartOnlineData = [];

while ($row = mysqli_fetch_assoc($chartQuery)) {
    $chartLabels[] = $row['tanggal_label'];
    $chartOfflineData[] = $row['offline'];
    $chartOnlineData[] = $row['online'];
}
$chartLabels = array_reverse($chartLabels);
$chartOfflineData = array_reverse($chartOfflineData);
$chartOnlineData = array_reverse($chartOnlineData);

// Array nama bulan
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Pendapatan Harian - Dapur Melly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --pink: #ff7eb3;
            --pink-soft: #ffe3f0;
            --peach: #ffb199;
            --green: #4CAF50;
            --blue: #2196F3;
            --orange: #FF9800;
            --red: #f44336;
            --shadow: 0 12px 30px rgba(0,0,0,.08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--pink-soft), #fff);
        }

        .main {
            margin-left: 80px;
            padding: 40px;
        }

        /* HEADER */
        .header h1 { 
            color: #ff5f9e; 
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .header p { 
            color: #777;
            margin-bottom: 20px;
        }

        /* NOTIFICATION */
        .notification {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.3s ease;
        }
        .notification.success {
            background: rgba(76, 175, 80, 0.1);
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .notification.error {
            background: rgba(244, 67, 54, 0.1);
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .notification .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
        }
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* BIG PROFIT CARD */
        .profit-card {
            background: linear-gradient(135deg, var(--green), #388E3C);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .profit-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .profit-card h2 {
            margin: 0 0 10px 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .profit-card .profit-value {
            font-size: 42px;
            font-weight: 700;
            margin: 0 0 10px 0;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .profit-card .profit-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }

        /* SUMMARY CARDS */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow);
            border-left: 5px solid;
        }
        .summary-card.total { border-left-color: var(--pink); }
        .summary-card.offline { border-left-color: var(--blue); }
        .summary-card.online { border-left-color: var(--orange); }
        .summary-card.gaji { border-left-color: var(--red); }
        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #666;
        }
        .summary-card .value {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }
        .summary-card.total .value { color: var(--pink); }
        .summary-card.offline .value { color: var(--blue); }
        .summary-card.online .value { color: var(--orange); }
        .summary-card.gaji .value { color: var(--red); }

        /* DAILY INPUT FORM */
        .daily-form-container {
            background: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        .daily-form-container h3 {
            color: #ff5f9e;
            margin: 0 0 25px 0;
            font-size: 20px;
        }
        .daily-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--pink-soft);
            border-radius: 10px;
            font-family: 'Poppins';
            font-size: 14px;
            transition: border 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--pink);
            outline: none;
        }
        .total-display {
            grid-column: 1 / -1;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin-top: 10px;
            border: 2px dashed #ddd;
        }
        .total-display h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }
        .total-display .total-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--pink);
        }
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-family: 'Poppins';
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .btn-primary {
            background: var(--pink);
            color: white;
        }
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }
        .btn-success {
            background: var(--green);
            color: white;
        }

        /* FILTER SECTION */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filter-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-form select {
            padding: 8px 15px;
            border-radius: 8px;
            border: 2px solid var(--pink-soft);
            font-family: 'Poppins';
            font-size: 14px;
            min-width: 120px;
        }
        .filter-form button {
            background: var(--pink);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .add-btn {
            background: var(--green);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* DATA TABLE */
        .data-table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        .data-table-container h3 {
            color: #ff5f9e;
            margin: 0 0 20px 0;
            font-size: 20px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th {
            background: linear-gradient(135deg, var(--pink), var(--peach));
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
            background: #fff9fb;
        }
        .day-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            background: #f0f0f0;
            color: #666;
        }
        .day-badge.weekend {
            background: #ffe3f0;
            color: #ff5f9e;
        }
        .income-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            color: white;
        }
        .income-badge.offline {
            background: var(--blue);
        }
        .income-badge.online {
            background: var(--orange);
        }
        .income-badge.total {
            background: var(--pink);
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-edit {
            background: var(--blue);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .btn-delete {
            background: var(--red);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* CHART SECTION */
        .chart-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        .chart-section h3 {
            color: #ff5f9e;
            margin: 0 0 20px 0;
            font-size: 20px;
        }
        .chart-container {
            height: 300px;
            position: relative;
        }

        /* MONTHLY SUMMARY */
        .monthly-summary {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        .monthly-summary h3 {
            color: #ff5f9e;
            margin: 0 0 20px 0;
            font-size: 20px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .summary-item {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: #f9f9f9;
        }
        .summary-item h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }
        .summary-item .value {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        .summary-item.offline .value { color: var(--blue); }
        .summary-item.online .value { color: var(--orange); }
        .summary-item.total .value { color: var(--pink); }

        /* NO DATA MESSAGE */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 20px;
            }
            .daily-form {
                grid-template-columns: 1fr;
            }
            .profit-card .profit-value {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>

<div class="main">
    <!-- HEADER -->
    <div class="header">
        <h1>💰 Manajemen Pendapatan Harian</h1>
        <p>Input dan kelola pendapatan harian - <?= $nama_bulan[$bulan] ?> <?= $tahun ?></p>
    </div>

    <!-- NOTIFICATION -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="notification success">
        <span><i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?></span>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="notification error">
        <span><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?></span>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- BIG PROFIT CARD -->
    <div class="profit-card">
        <h2><i class="fas fa-chart-line"></i> Laba Bersih Bulan Ini</h2>
        <p class="profit-value">Rp <?= number_format($labaBersih, 0, ',', '.') ?></p>
        <p class="profit-subtitle">Total Pendapatan: Rp <?= number_format($totalPendapatanBulan, 0, ',', '.') ?> - Total Gaji: Rp <?= number_format($totalGajiBulan, 0, ',', '.') ?></p>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-cards">
        <div class="summary-card total">
            <h3><i class="fas fa-money-bill-wave"></i> Total Pendapatan</h3>
            <p class="value">Rp <?= number_format($totalPendapatanBulan, 0, ',', '.') ?></p>
            <small><?= $nama_bulan[$bulan] ?> <?= $tahun ?></small>
        </div>
        
        <div class="summary-card offline">
            <h3><i class="fas fa-store"></i> Offline</h3>
            <p class="value">Rp <?= number_format($totalOffline, 0, ',', '.') ?></p>
            <small>Penjualan di toko</small>
        </div>
        
        <div class="summary-card online">
            <h3><i class="fas fa-globe"></i> Online</h3>
            <p class="value">Rp <?= number_format($totalOnline, 0, ',', '.') ?></p>
            <small>Marketplace & Instagram</small>
        </div>
        
        <div class="summary-card gaji">
            <h3><i class="fas fa-wallet"></i> Pengeluaran Gaji</h3>
            <p class="value">Rp <?= number_format($totalGajiBulan, 0, ',', '.') ?></p>
            <small>Berdasarkan data penggajian</small>
        </div>
    </div>

    <!-- DAILY INPUT FORM -->
    <div class="daily-form-container">
        <h3><i class="fas fa-calendar-day"></i> Input Pendapatan Harian</h3>
        <form id="dailyForm" method="POST" class="daily-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="id" id="formId" value="0">
            
            <div class="form-group">
                <label for="tanggal"><i class="fas fa-calendar"></i> Tanggal</label>
                <input type="date" id="tanggal" name="tanggal" value="<?= $tanggal ?>" required>
            </div>
            
            <div class="form-group">
                <label for="offline"><i class="fas fa-store"></i> Pendapatan Offline</label>
                <input type="text" id="offline" name="offline" placeholder="Contoh: 5.000.000" required>
            </div>
            
            <div class="form-group">
                <label for="online"><i class="fas fa-globe"></i> Pendapatan Online</label>
                <input type="text" id="online" name="online" placeholder="Contoh: 3.000.000" required>
            </div>
            
            <div class="form-group">
                <label for="keterangan"><i class="fas fa-sticky-note"></i> Keterangan (Opsional)</label>
                <textarea id="keterangan" name="keterangan" rows="3" placeholder="Catatan khusus hari ini..."></textarea>
            </div>
            
            <div class="total-display">
                <h4><i class="fas fa-calculator"></i> Total Harian (Otomatis)</h4>
                <p class="total-value" id="totalDisplay">Rp 0</p>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                    <i class="fas fa-redo"></i> Reset
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Simpan Pendapatan Harian
                </button>
            </div>
        </form>
    </div>

    <!-- MONTHLY SUMMARY -->
    <div class="monthly-summary">
        <h3><i class="fas fa-chart-bar"></i> Rekapitulasi Bulanan</h3>
        <div class="summary-grid">
            <div class="summary-item offline">
                <h4>Total Offline</h4>
                <p class="value">Rp <?= number_format($totalOffline, 0, ',', '.') ?></p>
            </div>
            <div class="summary-item online">
                <h4>Total Online</h4>
                <p class="value">Rp <?= number_format($totalOnline, 0, ',', '.') ?></p>
            </div>
            <div class="summary-item total">
                <h4>Total Pendapatan</h4>
                <p class="value">Rp <?= number_format($totalPendapatanBulan, 0, ',', '.') ?></p>
            </div>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <form class="filter-form" method="GET">
            <select name="tahun">
                <?php while($year = mysqli_fetch_assoc($yearsQuery)): ?>
                <option value="<?= $year['tahun'] ?>" <?= $tahun == $year['tahun'] ? 'selected' : '' ?>>
                    Tahun <?= $year['tahun'] ?>
                </option>
                <?php endwhile; ?>
            </select>
            
            <select name="bulan">
                <?php foreach($nama_bulan as $num => $name): ?>
                <option value="<?= $num ?>" <?= $bulan == $num ? 'selected' : '' ?>>
                    <?= $name ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit">
                <i class="fas fa-filter"></i> Filter Bulan
            </button>
        </form>
        
        <button class="add-btn" onclick="resetForm()">
            <i class="fas fa-plus"></i> Input Baru
        </button>
    </div>

    <!-- DATA TABLE -->
    <div class="data-table-container">
        <h3><i class="fas fa-history"></i> Riwayat Pendapatan Harian</h3>
        
        <?php if (mysqli_num_rows($pendapatanHarian) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Offline</th>
                    <th>Online</th>
                    <th>Total Harian</th>
                    <th>Keterangan</th>
                    <th>Ditambahkan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($pendapatanHarian)): 
                    $dayOfWeek = date('N', strtotime($row['tanggal']));
                    $isWeekend = ($dayOfWeek >= 6);
                ?>
                <tr>
                    <td>
                        <div class="day-badge <?= $isWeekend ? 'weekend' : '' ?>">
                            <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                            <br><small><?= $isWeekend ? 'Weekend' : 'Weekday' ?></small>
                        </div>
                    </td>
                    <td>
                        <span class="income-badge offline">
                            Rp <?= number_format($row['offline'], 0, ',', '.') ?>
                        </span>
                    </td>
                    <td>
                        <span class="income-badge online">
                            Rp <?= number_format($row['online'], 0, ',', '.') ?>
                        </span>
                    </td>
                    <td>
                        <span class="income-badge total">
                            Rp <?= number_format($row['total_harian'], 0, ',', '.') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($row['keterangan'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['created_by_name'] ?? 'System') ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-edit" onclick="editDailyData(
                                <?= $row['id'] ?>,
                                '<?= $row['tanggal'] ?>',
                                '<?= $row['offline'] ?>',
                                '<?= $row['online'] ?>',
                                `<?= addslashes($row['keterangan']) ?>`
                            )">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-delete" onclick="deleteData(<?= $row['id'] ?>)">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">
            <i class="fas fa-chart-bar"></i>
            <h3>Belum ada data pendapatan harian</h3>
            <p>Mulai dengan menginput data pendapatan harian di form di atas</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- CHART SECTION -->
    <div class="chart-section">
        <h3><i class="fas fa-chart-bar"></i> Tren 7 Hari Terakhir (Offline vs Online)</h3>
        <div class="chart-container">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>
</div>

<script>
// Format number input
function formatNumberInput(value) {
    if (!value) return '0';
    return parseInt(value).toLocaleString('id-ID');
}

function parseNumberInput(value) {
    if (!value) return 0;
    return parseInt(value.replace(/[^\d]/g, ''));
}

// Calculate and display total
function calculateTotal() {
    const offline = parseNumberInput(document.getElementById('offline').value);
    const online = parseNumberInput(document.getElementById('online').value);
    const total = offline + online;
    document.getElementById('totalDisplay').textContent = 'Rp ' + formatNumberInput(total);
    return total;
}

// Auto format input and calculate total
document.getElementById('offline').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^\d]/g, '');
    if (value) {
        e.target.value = parseInt(value).toLocaleString('id-ID');
    }
    calculateTotal();
});

document.getElementById('online').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^\d]/g, '');
    if (value) {
        e.target.value = parseInt(value).toLocaleString('id-ID');
    }
    calculateTotal();
});

// Reset form
function resetForm() {
    document.getElementById('dailyForm').reset();
    document.getElementById('formId').value = '0';
    document.getElementById('tanggal').value = '<?= date('Y-m-d') ?>';
    document.getElementById('totalDisplay').textContent = 'Rp 0';
    
    // Change submit button text
    const submitBtn = document.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-save"></i> Simpan Pendapatan Harian';
}

// Edit daily data
function editDailyData(id, tanggal, offline, online, keterangan) {
    document.getElementById('formId').value = id;
    document.getElementById('tanggal').value = tanggal;
    document.getElementById('offline').value = formatNumberInput(offline);
    document.getElementById('online').value = formatNumberInput(online);
    document.getElementById('keterangan').value = keterangan;
    
    // Calculate and display total
    calculateTotal();
    
    // Change submit button text
    const submitBtn = document.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Pendapatan';
    
    // Scroll to form
    document.querySelector('.daily-form-container').scrollIntoView({ behavior: 'smooth' });
}

// Delete data
function deleteData(id) {
    if (confirm('Apakah Anda yakin ingin menghapus data pendapatan ini?')) {
        window.location.href = `pendapatan.php?action=delete&id=${id}&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>`;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
    
    // Daily Chart
    const ctx = document.getElementById('dailyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Offline',
                    data: <?= json_encode($chartOfflineData) ?>,
                    backgroundColor: 'rgba(33, 150, 243, 0.8)',
                    borderColor: 'rgb(33, 150, 243)',
                    borderWidth: 1
                },
                {
                    label: 'Online',
                    data: <?= json_encode($chartOnlineData) ?>,
                    backgroundColor: 'rgba(255, 152, 0, 0.8)',
                    borderColor: 'rgb(255, 152, 0)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
    
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
});
</script>

</body>
</html>