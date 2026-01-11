<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

// Set bulan dan tahun (default bulan dan tahun saat ini)
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Get data pendapatan harian bulan ini
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

// Calculate percentage for pie chart
$totalAll = $totalOffline + $totalOnline;
$percentageOffline = $totalAll > 0 ? round(($totalOffline / $totalAll) * 100, 1) : 0;
$percentageOnline = $totalAll > 0 ? round(($totalOnline / $totalAll) * 100, 1) : 0;

// Get data for monthly trend chart (last 12 months)
$trendQuery = mysqli_query($conn, "
    SELECT 
        CONCAT(
            CASE bulan 
                WHEN '01' THEN 'Jan' WHEN '02' THEN 'Feb' WHEN '03' THEN 'Mar'
                WHEN '04' THEN 'Apr' WHEN '05' THEN 'Mei' WHEN '06' THEN 'Jun'
                WHEN '07' THEN 'Jul' WHEN '08' THEN 'Agu' WHEN '09' THEN 'Sep'
                WHEN '10' THEN 'Okt' WHEN '11' THEN 'Nov' WHEN '12' THEN 'Des'
            END, ' ', tahun
        ) as periode,
        SUM(total_harian) as total_pendapatan,
        SUM(offline) as total_offline,
        SUM(online) as total_online
    FROM pendapatan_harian 
    WHERE CONCAT(tahun, '-', bulan, '-01') >= DATE_SUB(CONCAT('$tahun', '-', '$bulan', '-01'), INTERVAL 11 MONTH)
    GROUP BY tahun, bulan
    ORDER BY tahun ASC, bulan ASC
");

$trendLabels = [];
$trendData = [];
$trendOfflineData = [];
$trendOnlineData = [];

while ($row = mysqli_fetch_assoc($trendQuery)) {
    $trendLabels[] = $row['periode'];
    $trendData[] = $row['total_pendapatan'];
    $trendOfflineData[] = $row['total_offline'];
    $trendOnlineData[] = $row['total_online'];
}

// Get years for filter
$yearsQuery = mysqli_query($conn, "
    SELECT DISTINCT tahun 
    FROM pendapatan_harian 
    ORDER BY tahun DESC
");

// Get data for daily comparison chart (7 days)
$dailyChartQuery = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(tanggal, '%d/%m') as tanggal_label,
        SUM(offline) as offline,
        SUM(online) as online
    FROM pendapatan_harian 
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY tanggal
    ORDER BY tanggal ASC
    LIMIT 7
");

$dailyLabels = [];
$dailyOfflineData = [];
$dailyOnlineData = [];

while ($row = mysqli_fetch_assoc($dailyChartQuery)) {
    $dailyLabels[] = $row['tanggal_label'];
    $dailyOfflineData[] = $row['offline'];
    $dailyOnlineData[] = $row['online'];
}

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
    <title>Analisis Pendapatan - Dapur Melly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        :root {
            --pink: #ff7eb3;
            --pink-soft: #ffe3f0;
            --peach: #ffb199;
            --green: #4CAF50;
            --blue: #2196F3;
            --orange: #FF9800;
            --red: #f44336;
            --purple: #9C27B0;
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

        /* ACTION BUTTONS */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .action-btn {
            padding: 12px 25px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-family: 'Poppins';
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .action-btn.pdf {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
        }
        .action-btn.filter {
            background: linear-gradient(135deg, var(--blue), #1976D2);
            color: white;
        }
        .action-btn.excel {
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
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

        /* FILTER SECTION */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        .filter-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-form select {
            padding: 10px 15px;
            border-radius: 8px;
            border: 2px solid var(--pink-soft);
            font-family: 'Poppins';
            font-size: 14px;
            min-width: 140px;
            background: white;
        }
        .filter-form button {
            background: var(--pink);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* CHART CONTAINER */
        .chart-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }
        .chart-box h3 {
            color: #ff5f9e;
            margin: 0 0 20px 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .chart-box .chart {
            height: 300px;
            position: relative;
        }

        /* PERCENTAGE INFO */
        .percentage-info {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 10px;
        }
        .percentage-item {
            text-align: center;
        }
        .percentage-item h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #666;
        }
        .percentage-item .value {
            font-size: 24px;
            font-weight: 600;
        }
        .percentage-item.offline .value { color: var(--blue); }
        .percentage-item.online .value { color: var(--orange); }

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
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                padding: 20px;
            }
            .chart-container {
                grid-template-columns: 1fr;
            }
            .profit-card .profit-value {
                font-size: 32px;
            }
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="main">
    <!-- HEADER -->
    <div class="header">
        <h1>📊 Analisis Pendapatan - Owner View</h1>
        <p>Dashboard analisis keuangan mendalam - <?= $nama_bulan[$bulan] ?> <?= $tahun ?></p>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="action-buttons no-print">
        <button class="action-btn pdf" onclick="generatePDF()">
            <i class="fas fa-file-pdf"></i> Cetak Laporan Keuangan
        </button>
        <a href="pendapatan.php?bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>" class="action-btn filter">
            <i class="fas fa-calendar"></i> Bulan Ini
        </a>
        <button class="action-btn excel" onclick="exportToExcel()">
            <i class="fas fa-file-excel"></i> Export Excel
        </button>
    </div>

    <!-- BIG PROFIT CARD -->
    <div class="profit-card">
        <h2><i class="fas fa-chart-line"></i> Laba Bersih Bulan <?= $nama_bulan[$bulan] ?> <?= $tahun ?></h2>
        <p class="profit-value">Rp <?= number_format($labaBersih, 0, ',', '.') ?></p>
        <p class="profit-subtitle">
            Pendapatan: Rp <?= number_format($totalPendapatanBulan, 0, ',', '.') ?> | 
            Gaji: Rp <?= number_format($totalGajiBulan, 0, ',', '.') ?> | 
            Margin: <?= $totalPendapatanBulan > 0 ? round(($labaBersih / $totalPendapatanBulan) * 100, 1) : 0 ?>%
        </p>
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
            <small><?= $percentageOffline ?>% dari total</small>
        </div>
        
        <div class="summary-card online">
            <h3><i class="fas fa-globe"></i> Online</h3>
            <p class="value">Rp <?= number_format($totalOnline, 0, ',', '.') ?></p>
            <small><?= $percentageOnline ?>% dari total</small>
        </div>
        
        <div class="summary-card gaji">
            <h3><i class="fas fa-wallet"></i> Pengeluaran Gaji</h3>
            <p class="value">Rp <?= number_format($totalGajiBulan, 0, ',', '.') ?></p>
            <small><?= $totalPendapatanBulan > 0 ? round(($totalGajiBulan / $totalPendapatanBulan) * 100, 1) : 0 ?>% dari pendapatan</small>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section no-print">
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
                <i class="fas fa-filter"></i> Analisis Bulan
            </button>
        </form>
    </div>

    <!-- CHART CONTAINER -->
    <div class="chart-container">
        <!-- PIE CHART: Persentase Pendapatan -->
        <div class="chart-box">
            <h3><i class="fas fa-chart-pie"></i> Distribusi Pendapatan Bulan Ini</h3>
            <div class="chart">
                <canvas id="pieChart"></canvas>
            </div>
            <div class="percentage-info">
                <div class="percentage-item offline">
                    <h4>Offline</h4>
                    <p class="value"><?= $percentageOffline ?>%</p>
                    <small>Rp <?= number_format($totalOffline, 0, ',', '.') ?></small>
                </div>
                <div class="percentage-item online">
                    <h4>Online</h4>
                    <p class="value"><?= $percentageOnline ?>%</p>
                    <small>Rp <?= number_format($totalOnline, 0, ',', '.') ?></small>
                </div>
            </div>
        </div>

        <!-- LINE CHART: Tren 12 Bulan -->
        <div class="chart-box">
            <h3><i class="fas fa-chart-line"></i> Tren Pendapatan 12 Bulan</h3>
            <div class="chart">
                <canvas id="lineChart"></canvas>
            </div>
        </div>
    </div>

    <!-- BAR CHART: Perbandingan Harian -->
    <div class="chart-box" style="margin-bottom: 30px;">
        <h3><i class="fas fa-chart-bar"></i> Perbandingan Harian (7 Hari Terakhir)</h3>
        <div class="chart">
            <canvas id="barChart"></canvas>
        </div>
    </div>

    <!-- MONTHLY SUMMARY -->
    <div class="monthly-summary">
        <h3><i class="fas fa-chart-bar"></i> Rekapitulasi Bulan <?= $nama_bulan[$bulan] ?> <?= $tahun ?></h3>
        <div class="summary-grid">
            <div class="summary-item offline">
                <h4>Total Offline</h4>
                <p class="value">Rp <?= number_format($totalOffline, 0, ',', '.') ?></p>
                <small><?= $percentageOffline ?>% dari total</small>
            </div>
            <div class="summary-item online">
                <h4>Total Online</h4>
                <p class="value">Rp <?= number_format($totalOnline, 0, ',', '.') ?></p>
                <small><?= $percentageOnline ?>% dari total</small>
            </div>
            <div class="summary-item total">
                <h4>Total Pendapatan</h4>
                <p class="value">Rp <?= number_format($totalPendapatanBulan, 0, ',', '.') ?></p>
                <small>Kotor</small>
            </div>
            <div class="summary-item" style="background: #ffebee; border-left: 5px solid #f44336;">
                <h4>Pengeluaran Gaji</h4>
                <p class="value" style="color: #f44336;">Rp <?= number_format($totalGajiBulan, 0, ',', '.') ?></p>
                <small>Operasional</small>
            </div>
        </div>
    </div>

    <!-- DATA TABLE -->
    <div class="data-table-container">
        <h3><i class="fas fa-history"></i> Detail Pendapatan Harian - <?= $nama_bulan[$bulan] ?> <?= $tahun ?></h3>
        
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
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">
            <i class="fas fa-chart-bar"></i>
            <h3>Belum ada data pendapatan harian</h3>
            <p>Tidak ada data pendapatan untuk <?= $nama_bulan[$bulan] ?> <?= $tahun ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- PRINT SECTION (Hidden) -->
    <div id="printSection" style="display: none;">
        <div class="print-section">
            <!-- PDF Header -->
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 3px solid #ff5f9e; padding-bottom: 20px;">
                <h1 style="color: #ff5f9e; margin: 0;">LAPORAN KEUANGAN BULANAN</h1>
                <h2 style="color: #666; margin: 10px 0;">Dapur Melly</h2>
                <p style="color: #999; margin: 0;">Periode: <?= $nama_bulan[$bulan] ?> <?= $tahun ?></p>
                <p style="color: #999; margin: 0;">Dicetak: <?= date('d/m/Y H:i:s') ?></p>
            </div>

            <!-- Summary Section -->
            <div style="margin-bottom: 30px;">
                <h3 style="color: #ff5f9e; border-bottom: 2px solid #eee; padding-bottom: 10px;">Ringkasan Keuangan</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Total Pendapatan Kotor</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: right; font-weight: bold; color: #ff5f9e;">
                            Rp <?= number_format($totalPendapatanBulan, 0, ',', '.') ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Total Pengeluaran Gaji</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: right; font-weight: bold; color: #f44336;">
                            Rp <?= number_format($totalGajiBulan, 0, ',', '.') ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold; background: #f9f9f9;">LABA BERSIH</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: right; font-weight: bold; background: #f9f9f9; color: #4CAF50;">
                            Rp <?= number_format($labaBersih, 0, ',', '.') ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Detail Pendapatan -->
            <div style="margin-bottom: 30px;">
                <h3 style="color: #ff5f9e; border-bottom: 2px solid #eee; padding-bottom: 10px;">Detail Pendapatan Harian</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #ff5f9e; color: white;">
                            <th style="padding: 10px; border: 1px solid #ddd;">Tanggal</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Offline</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Online</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Total Harian</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($pendapatanHarian, 0);
                        $counter = 0;
                        while($row = mysqli_fetch_assoc($pendapatanHarian)): 
                            $counter++;
                        ?>
                        <tr style="background: <?= $counter % 2 == 0 ? '#f9f9f9' : 'white' ?>;">
                            <td style="padding: 8px; border: 1px solid #ddd;"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">Rp <?= number_format($row['offline'], 0, ',', '.') ?></td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">Rp <?= number_format($row['online'], 0, ',', '.') ?></td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold;">Rp <?= number_format($row['total_harian'], 0, ',', '.') ?></td>
                            <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($row['keterangan'] ?: '-') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Breakdown -->
            <div style="margin-bottom: 30px;">
                <h3 style="color: #ff5f9e; border-bottom: 2px solid #eee; padding-bottom: 10px;">Analisis Persentase</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd; width: 50%;">
                            <strong>Offline:</strong> <?= $percentageOffline ?>% (Rp <?= number_format($totalOffline, 0, ',', '.') ?>)
                        </td>
                        <td style="padding: 10px; border: 1px solid #ddd; width: 50%;">
                            <strong>Online:</strong> <?= $percentageOnline ?>% (Rp <?= number_format($totalOnline, 0, ',', '.') ?>)
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Footer -->
            <div style="text-align: center; margin-top: 50px; padding-top: 20px; border-top: 2px solid #eee;">
                <p style="color: #999;">Laporan ini dibuat otomatis oleh sistem Dapur Melly</p>
                <p style="color: #999;">© <?= date('Y') ?> Dapur Melly - All Rights Reserved</p>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    // PIE CHART: Persentase Pendapatan
    const pieCtx = document.getElementById('pieChart').getContext('2d');
    new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: ['Offline (<?= $percentageOffline ?>%)', 'Online (<?= $percentageOnline ?>%)'],
            datasets: [{
                data: [<?= $totalOffline ?>, <?= $totalOnline ?>],
                backgroundColor: ['#2196F3', '#FF9800'],
                borderColor: ['#1976D2', '#F57C00'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: Rp ${value.toLocaleString('id-ID')} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // LINE CHART: Tren 12 Bulan
    const lineCtx = document.getElementById('lineChart').getContext('2d');
    new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [
                {
                    label: 'Total Pendapatan',
                    data: <?= json_encode($trendData) ?>,
                    borderColor: '#ff7eb3',
                    backgroundColor: 'rgba(255, 126, 179, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Pendapatan Offline',
                    data: <?= json_encode($trendOfflineData) ?>,
                    borderColor: '#2196F3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                },
                {
                    label: 'Pendapatan Online',
                    data: <?= json_encode($trendOnlineData) ?>,
                    borderColor: '#FF9800',
                    backgroundColor: 'rgba(255, 152, 0, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: Rp ${context.parsed.y.toLocaleString('id-ID')}`;
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

    // BAR CHART: Perbandingan 7 Hari
    const barCtx = document.getElementById('barChart').getContext('2d');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($dailyLabels) ?>,
            datasets: [
                {
                    label: 'Offline',
                    data: <?= json_encode($dailyOfflineData) ?>,
                    backgroundColor: 'rgba(33, 150, 243, 0.8)',
                    borderColor: 'rgb(33, 150, 243)',
                    borderWidth: 1
                },
                {
                    label: 'Online',
                    data: <?= json_encode($dailyOnlineData) ?>,
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
});

// Generate PDF Report
function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    
    // Add title
    doc.setFontSize(20);
    doc.setTextColor(255, 95, 158); // Pink color
    doc.text('LAPORAN KEUANGAN BULANAN', 105, 20, null, null, 'center');
    
    doc.setFontSize(12);
    doc.setTextColor(100, 100, 100);
    doc.text('Dapur Melly', 105, 28, null, null, 'center');
    doc.text('Periode: <?= $nama_bulan[$bulan] ?> <?= $tahun ?>', 105, 34, null, null, 'center');
    doc.text('Dicetak: <?= date("d/m/Y H:i:s") ?>', 105, 40, null, null, 'center');
    
    // Add line separator
    doc.setDrawColor(255, 95, 158);
    doc.setLineWidth(0.5);
    doc.line(20, 45, 190, 45);
    
    let yPos = 55;
    
    // Summary Section
    doc.setFontSize(16);
    doc.setTextColor(255, 95, 158);
    doc.text('Ringkasan Keuangan', 20, yPos);
    
    yPos += 10;
    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    
    // Summary table
    const summaryData = [
        ['Total Pendapatan Kotor', 'Rp <?= number_format($totalPendapatanBulan, 0, ",", ".") ?>'],
        ['Total Pengeluaran Gaji', 'Rp <?= number_format($totalGajiBulan, 0, ",", ".") ?>'],
        ['LABA BERSIH', 'Rp <?= number_format($labaBersih, 0, ",", ".") ?>']
    ];
    
    summaryData.forEach((row, index) => {
        doc.setFillColor(index === 2 ? 249 : 255, index === 2 ? 249 : 255, index === 2 ? 249 : 255);
        doc.rect(20, yPos, 170, 8, 'F');
        doc.text(row[0], 25, yPos + 6);
        doc.text(row[1], 175, yPos + 6, null, null, 'right');
        yPos += 10;
    });
    
    yPos += 5;
    
    // Detail Pendapatan Harian
    doc.setFontSize(16);
    doc.setTextColor(255, 95, 158);
    doc.text('Detail Pendapatan Harian', 20, yPos);
    
    yPos += 10;
    
    // Table header
    doc.setFillColor(255, 95, 158);
    doc.rect(20, yPos, 170, 8, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(10);
    doc.text('Tanggal', 25, yPos + 6);
    doc.text('Offline', 60, yPos + 6);
    doc.text('Online', 95, yPos + 6);
    doc.text('Total', 130, yPos + 6);
    doc.text('Keterangan', 165, yPos + 6);
    
    yPos += 10;
    
    // Table rows
    doc.setTextColor(0, 0, 0);
    <?php 
    mysqli_data_seek($pendapatanHarian, 0);
    $rowCount = 0;
    while($row = mysqli_fetch_assoc($pendapatanHarian)): 
        $rowCount++;
        if($rowCount > 15) break; // Limit rows for PDF
    ?>
    doc.setFillColor(<?= $rowCount % 2 == 0 ? 249 : 255 ?>, <?= $rowCount % 2 == 0 ? 249 : 255 ?>, <?= $rowCount % 2 == 0 ? 249 : 255 ?>);
    doc.rect(20, yPos, 170, 8, 'F');
    doc.text('<?= date("d/m/Y", strtotime($row["tanggal"])) ?>', 25, yPos + 6);
    doc.text('Rp <?= number_format($row["offline"], 0, ",", ".") ?>', 60, yPos + 6);
    doc.text('Rp <?= number_format($row["online"], 0, ",", ".") ?>', 95, yPos + 6);
    doc.text('Rp <?= number_format($row["total_harian"], 0, ",", ".") ?>', 130, yPos + 6);
    doc.text('<?= addslashes(substr($row["keterangan"] ?: "-", 0, 15)) ?>...', 165, yPos + 6);
    yPos += 10;
    
    // Check page break
    if(yPos > 250) {
        doc.addPage();
        yPos = 20;
    }
    <?php endwhile; ?>
    
    yPos += 10;
    
    // Analysis Section
    doc.setFontSize(16);
    doc.setTextColor(255, 95, 158);
    doc.text('Analisis Persentase', 20, yPos);
    
    yPos += 10;
    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    
    doc.text(`Offline: <?= $percentageOffline ?>% (Rp <?= number_format($totalOffline, 0, ",", ".") ?>)`, 25, yPos);
    yPos += 8;
    doc.text(`Online: <?= $percentageOnline ?>% (Rp <?= number_format($totalOnline, 0, ",", ".") ?>)`, 25, yPos);
    
    yPos += 15;
    
    // Footer
    doc.setFontSize(8);
    doc.setTextColor(150, 150, 150);
    doc.text('Laporan ini dibuat otomatis oleh sistem Dapur Melly', 105, yPos, null, null, 'center');
    yPos += 5;
    doc.text('© <?= date("Y") ?> Dapur Melly - All Rights Reserved', 105, yPos, null, null, 'center');
    
    // Save PDF
    doc.save('Laporan_Keuangan_<?= $nama_bulan[$bulan] ?>_<?= $tahun ?>.pdf');
}

// Export to Excel (simplified version)
function exportToExcel() {
    // Create table data
    let table = '<table border="1">';
    table += '<tr><th colspan="5">Laporan Pendapatan <?= $nama_bulan[$bulan] ?> <?= $tahun ?></th></tr>';
    table += '<tr><th>Tanggal</th><th>Offline</th><th>Online</th><th>Total</th><th>Keterangan</th></tr>';
    
    <?php 
    mysqli_data_seek($pendapatanHarian, 0);
    while($row = mysqli_fetch_assoc($pendapatanHarian)): 
    ?>
    table += '<tr>';
    table += '<td><?= date("d/m/Y", strtotime($row["tanggal"])) ?></td>';
    table += '<td><?= number_format($row["offline"], 0, ",", ".") ?></td>';
    table += '<td><?= number_format($row["online"], 0, ",", ".") ?></td>';
    table += '<td><?= number_format($row["total_harian"], 0, ",", ".") ?></td>';
    table += '<td><?= htmlspecialchars($row["keterangan"] ?: "-") ?></td>';
    table += '</tr>';
    <?php endwhile; ?>
    
    table += '<tr><td colspan="2"><strong>Total Offline: Rp <?= number_format($totalOffline, 0, ",", ".") ?></strong></td>';
    table += '<td colspan="3"><strong>Total Online: Rp <?= number_format($totalOnline, 0, ",", ".") ?></strong></td></tr>';
    table += '<tr><td colspan="5"><strong>Total Pendapatan: Rp <?= number_format($totalPendapatanBulan, 0, ",", ".") ?></strong></td></tr>';
    table += '<tr><td colspan="5"><strong>Total Gaji: Rp <?= number_format($totalGajiBulan, 0, ",", ".") ?></strong></td></tr>';
    table += '<tr><td colspan="5"><strong>Laba Bersih: Rp <?= number_format($labaBersih, 0, ",", ".") ?></strong></td></tr>';
    table += '</table>';
    
    // Create blob and download
    const blob = new Blob([table], { type: 'application/vnd.ms-excel' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'Laporan_Pendapatan_<?= $nama_bulan[$bulan] ?>_<?= $tahun ?>.xls';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Print function
function printReport() {
    const printContent = document.getElementById('printSection').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}
</script>

</body>
</html>