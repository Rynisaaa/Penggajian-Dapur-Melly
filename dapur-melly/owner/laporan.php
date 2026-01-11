<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

include '../includes/sidebar.php';

// Ambil data laporan bulanan dari database
$tahunIni = date('Y');
$laporanQuery = mysqli_query($conn, "
    SELECT 
        lb.id,
        lb.bulan,
        lb.tahun,
        lb.pendapatan,
        DATE_FORMAT(lb.updated_at, '%d %b %Y %H:%i') as last_updated,
        u.nama_lengkap as updated_by_name
    FROM laporan_bulanan lb
    LEFT JOIN users u ON lb.updated_by = u.id
    WHERE lb.tahun = '$tahunIni'
    ORDER BY lb.tahun DESC, lb.bulan DESC
    LIMIT 6
");

// Hitung total pengeluaran gaji tahun ini
$totalGajiTahunIni = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(gaji_bersih) as total 
    FROM penggajian 
    WHERE tahun = '$tahunIni'
"))['total'] ?: 0;

// Hitung total pendapatan tahun ini
$totalPendapatanTahunIni = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(pendapatan) as total 
    FROM laporan_bulanan 
    WHERE tahun = '$tahunIni'
"))['total'] ?: 0;

// Data untuk chart laporan bulanan
$bulanLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$pendapatanData = array_fill(0, 12, 0);
$gajiData = array_fill(0, 12, 0);

// Ambil data pendapatan per bulan
$pendapatanPerBulan = mysqli_query($conn, "
    SELECT bulan, SUM(pendapatan) as total
    FROM laporan_bulanan
    WHERE tahun = '$tahunIni'
    GROUP BY bulan
    ORDER BY bulan
");

while ($row = mysqli_fetch_assoc($pendapatanPerBulan)) {
    $index = $row['bulan'] - 1;
    $pendapatanData[$index] = (float)$row['total'];
}

// Ambil data gaji per bulan
$gajiPerBulan = mysqli_query($conn, "
    SELECT bulan, SUM(gaji_bersih) as total
    FROM penggajian
    WHERE tahun = '$tahunIni'
    GROUP BY bulan
    ORDER BY bulan
");

while ($row = mysqli_fetch_assoc($gajiPerBulan)) {
    $index = $row['bulan'] - 1;
    $gajiData[$index] = (float)$row['total'];
}

// Fungsi untuk generate laporan PDF (sederhana - bisa dikembangkan)
function generatePDFReport($conn, $tahun) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
            .header h1 { color: #ff5f9e; margin: 0; }
            .header p { color: #666; margin: 5px 0; }
            .stats { display: flex; justify-content: space-between; margin: 30px 0; }
            .stat-card { background: #f5f5f5; padding: 20px; border-radius: 10px; text-align: center; flex: 1; margin: 0 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #ff7eb3; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            .footer { margin-top: 50px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Laporan Keuangan Dapur Melly</h1>
            <p>Periode: Tahun $tahun</p>
            <p>Tanggal Cetak: " . date('d F Y H:i') . "</p>
        </div>
        
        <div class='stats'>
            <div class='stat-card'>
                <h3>Total Pendapatan</h3>
                <h2>Rp " . number_format($totalPendapatanTahunIni, 0, ',', '.') . "</h2>
            </div>
            <div class='stat-card'>
                <h3>Total Pengeluaran Gaji</h3>
                <h2>Rp " . number_format($totalGajiTahunIni, 0, ',', '.') . "</h2>
            </div>
            <div class='stat-card'>
                <h3>Laba Bersih</h3>
                <h2>Rp " . number_format($totalPendapatanTahunIni - $totalGajiTahunIni, 0, ',', '.') . "</h2>
            </div>
        </div>
        
        <h3>Laporan Bulanan $tahun</h3>
        <table>
            <tr>
                <th>Bulan</th>
                <th>Tahun</th>
                <th>Pendapatan</th>
                <th>Terakhir Update</th>
            </tr>";
    
    $reportData = mysqli_query($conn, "
        SELECT bulan, tahun, pendapatan, DATE_FORMAT(updated_at, '%d %b %Y') as last_updated
        FROM laporan_bulanan 
        WHERE tahun = '$tahun'
        ORDER BY bulan ASC
    ");
    
    while($row = mysqli_fetch_assoc($reportData)) {
        $namaBulan = date('F', mktime(0, 0, 0, $row['bulan'], 1));
        $html .= "<tr>
            <td>$namaBulan</td>
            <td>{$row['tahun']}</td>
            <td>Rp " . number_format($row['pendapatan'], 0, ',', '.') . "</td>
            <td>{$row['last_updated']}</td>
        </tr>";
    }
    
    $html .= "
        </table>
        <div class='footer'>
            <p>Laporan ini dihasilkan secara otomatis oleh Sistem Penggajian Dapur Melly</p>
            <p>© " . date('Y') . " Dapur Melly - Hak Cipta Dilindungi</p>
        </div>
    </body>
    </html>";
    
    return $html;
}

// Handle download request
if (isset($_GET['download']) && $_GET['download'] == 'pdf') {
    // Set header untuk download PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="laporan_dapur_melly_' . date('Y-m-d') . '.pdf"');
    
    // Generate HTML content
    $htmlContent = generatePDFReport($conn, $tahunIni);
    
    // Untuk sementara kita output HTML, nanti bisa diganti dengan library PDF seperti Dompdf
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            // Konversi HTML ke PDF menggunakan jsPDF (client-side)
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Convert HTML to PDF
            doc.html(document.body, {
                callback: function(doc) {
                    doc.save('laporan_dapur_melly_" . date('Y-m-d') . ".pdf');
                },
                x: 10,
                y: 10,
                width: 190,
                windowWidth: 800
            });
        });
    </script>";
    
    // Include jsPDF library
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>';
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>';
    
    // Output HTML content
    echo $htmlContent;
    exit;
}

// Handle download Excel
if (isset($_GET['download']) && $_GET['download'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_dapur_melly_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>
        <tr>
            <th colspan='4' style='background:#ff7eb3;color:white;padding:20px;font-size:18px;'>
                Laporan Keuangan Dapur Melly - Tahun $tahunIni
            </th>
        </tr>
        <tr>
            <td colspan='4'>Tanggal Export: " . date('d F Y H:i') . "</td>
        </tr>
        <tr>
            <th>Bulan</th>
            <th>Tahun</th>
            <th>Pendapatan</th>
            <th>Terakhir Update</th>
        </tr>";
    
    $excelData = mysqli_query($conn, "
        SELECT bulan, tahun, pendapatan, DATE_FORMAT(updated_at, '%d %b %Y') as last_updated
        FROM laporan_bulanan 
        WHERE tahun = '$tahunIni'
        ORDER BY bulan ASC
    ");
    
    while($row = mysqli_fetch_assoc($excelData)) {
        $namaBulan = date('F', mktime(0, 0, 0, $row['bulan'], 1));
        echo "<tr>
            <td>$namaBulan</td>
            <td>{$row['tahun']}</td>
            <td>Rp " . number_format($row['pendapatan'], 0, ',', '.') . "</td>
            <td>{$row['last_updated']}</td>
        </tr>";
    }
    
    echo "<tr>
        <td colspan='2'><strong>Total Pendapatan Tahun Ini</strong></td>
        <td colspan='2'><strong>Rp " . number_format($totalPendapatanTahunIni, 0, ',', '.') . "</strong></td>
    </tr>
    <tr>
        <td colspan='2'><strong>Total Pengeluaran Gaji</strong></td>
        <td colspan='2'><strong>Rp " . number_format($totalGajiTahunIni, 0, ',', '.') . "</strong></td>
    </tr>
    <tr>
        <td colspan='2'><strong>Laba Bersih</strong></td>
        <td colspan='2'><strong>Rp " . number_format($totalPendapatanTahunIni - $totalGajiTahunIni, 0, ',', '.') . "</strong></td>
    </tr>
    </table>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Owner | Dapur Melly</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --pink: #ff7eb3;
            --pink-soft: #ffe3f0;
            --peach: #ffb199;
            --dark: #333;
            --light: #fff;
            --gray: #f5f5f5;
            --shadow: 0 8px 25px rgba(0,0,0,0.08);
            --shadow-light: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--pink-soft), #fff);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* ===================== */
        /* MAIN CONTENT */
        /* ===================== */
        .main-content {
            margin-left: 20px;
            padding: 40px;
            transition: margin-left 0.35s ease;
            min-height: 100vh;
        }
        
        .sidebar-wrapper:hover ~ .main-content,
        .sidebar-hover-zone:hover ~ .main-content {
            margin-left: 280px;
        }
        
        /* ===================== */
        /* HEADER */
        /* ===================== */
        .dashboard-header {
            background: linear-gradient(135deg, rgba(255,126,179,0.1), rgba(255,177,153,0.1));
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            border-left: 5px solid var(--pink);
        }
        
        .dashboard-header h1 {
            color: #ff5f9e;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dashboard-header p {
            color: #666;
            margin: 5px 0;
            font-size: 15px;
        }
        
        .owner-badge {
            background: #6c757d;
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        /* ===================== */
        /* STATS CARDS */
        /* ===================== */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--pink), var(--peach));
            color: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            transition: .3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(255,255,255,0.3);
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 35px rgba(255,126,179,0.3);
        }
        
        .stat-card i {
            font-size: 28px;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .stat-card h4 {
            margin: 5px 0;
            font-size: 15px;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .stat-card h2 {
            margin: 10px 0 0 0;
            font-size: 30px;
            font-weight: 700;
        }
        
        /* ===================== */
        /* CHART AREA */
        /* ===================== */
        .chart-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        @media (max-width: 1100px) {
            .chart-area {
                grid-template-columns: 1fr;
            }
        }
        
        .chart-box {
            background: white;
            padding: 30px;
            border-radius: 22px;
            box-shadow: var(--shadow);
        }
        
        .chart-box h3 {
            color: #ff5f9e;
            margin-top: 0;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* ===================== */
        /* REPORT TABLE */
        /* ===================== */
        .report-table-container {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
        }
        
        .report-table-container h3 {
            color: #ff5f9e;
            margin-top: 0;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 15px;
            box-shadow: var(--shadow-light);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 800px;
        }
        
        thead {
            background: linear-gradient(135deg, var(--pink), var(--peach));
        }
        
        th {
            color: white;
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border: none;
        }
        
        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }
        
        tbody tr:hover {
            background: #f9f9f9;
        }
        
        tbody tr:nth-child(even) {
            background: #fafafa;
        }
        
        td {
            padding: 16px 15px;
            color: #555;
            vertical-align: middle;
            font-size: 14px;
        }
        
        .bulan-badge {
            background: rgba(255, 126, 179, 0.1);
            color: var(--pink);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
        }
        
        /* ===================== */
        /* ACTION BUTTONS */
        /* ===================== */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            text-decoration: none;
        }
        
        .action-btn.disabled {
            background: #ccc !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }
        
        .action-btn.pdf {
            background: #ff4757;
            color: white;
        }
        
        .action-btn.pdf:hover {
            background: #ff2e43;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 71, 87, 0.3);
        }
        
        .action-btn.excel {
            background: #2ed573;
            color: white;
        }
        
        .action-btn.excel:hover {
            background: #25c464;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(46, 213, 115, 0.3);
        }
        
        .action-btn.print {
            background: #3742fa;
            color: white;
        }
        
        .action-btn.print:hover {
            background: #2f38e1;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(55, 66, 250, 0.3);
        }
        
        /* ===================== */
        /* PRINT STYLES */
        /* ===================== */
        @media print {
            body {
                background: white !important;
            }
            
            .sidebar-wrapper,
            .sidebar-hover-zone,
            .action-buttons .print,
            .dashboard-header .owner-badge,
            .info-box {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 20px !important;
            }
            
            .stat-card,
            .chart-box,
            .report-table-container {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
            }
            
            .dashboard-header {
                background: none !important;
                border-left: none !important;
            }
        }
        
        /* ===================== */
        /* INFO BOX */
        /* ===================== */
        .info-box {
            background: linear-gradient(135deg, rgba(108,117,125,0.05), rgba(73,80,87,0.05));
            padding: 25px;
            border-radius: 20px;
            margin-top: 40px;
            border-left: 5px solid #6c757d;
            color: #495057;
        }
        
        .info-box h4 {
            color: #495057;
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box p {
            margin: 10px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* ===================== */
        /* DOWNLOAD MODAL */
        /* ===================== */
        .download-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .download-modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        
        .download-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }
        
        .download-option {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .download-option:hover {
            border-color: var(--pink);
            background: rgba(255,126,179,0.05);
        }
        
        .download-option i {
            font-size: 24px;
            color: var(--pink);
        }
        
        /* ===================== */
        /* RESPONSIVE */
        /* ===================== */
        @media (max-width: 768px) {
            .main-content {
                padding: 25px;
                margin-left: 0;
            }
            
            .sidebar-wrapper:hover ~ .main-content,
            .sidebar-hover-zone:hover ~ .main-content {
                margin-left: 280px;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar auto-hide (from includes/sidebar.php) -->

<!-- Download Modal -->
<div class="download-modal" id="downloadModal">
    <div class="download-modal-content">
        <h3 style="color: #ff5f9e; margin-top: 0;"><i class="fas fa-download"></i> Unduh Laporan</h3>
        <p>Pilih format file yang ingin diunduh:</p>
        
        <div class="download-options">
            <a href="?download=excel" class="download-option" onclick="showDownloading()">
                <i class="fas fa-file-excel"></i>
                <div>
                    <strong>Excel (.xls)</strong>
                    <small>Format spreadsheet untuk analisis data</small>
                </div>
            </a>
            
            <a href="?download=pdf" class="download-option" onclick="showDownloading()">
                <i class="fas fa-file-pdf"></i>
                <div>
                    <strong>PDF (.pdf)</strong>
                    <small>Format dokumen untuk presentasi dan print</small>
                </div>
            </a>
            
            <div class="download-option" onclick="printReport()">
                <i class="fas fa-print"></i>
                <div>
                    <strong>Print Langsung</strong>
                    <small>Cetak laporan ke printer</small>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 25px; text-align: center;">
            <button onclick="closeDownloadModal()" style="background: #6c757d; color: white; border: none; padding: 10px 25px; border-radius: 10px; cursor: pointer;">
                <i class="fas fa-times"></i> Batal
            </button>
        </div>
    </div>
</div>

<div class="main-content">
    <!-- HEADER -->
    <div class="dashboard-header">
        <h1>
            <i class="fas fa-chart-bar"></i> Laporan Keuangan
            <span class="owner-badge">Owner Access</span>
        </h1>
        <p>Halo, <b><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></b>! Laporan keuangan Dapur Melly.</p>
        <p><i class="fas fa-download"></i> Anda dapat mengunduh laporan dalam format Excel atau PDF.</p>
    </div>
    
    <!-- STATS CARDS -->
    <div class="stats">
        <div class="stat-card">
            <i class="fas fa-money-bill-wave"></i>
            <h4>Total Pendapatan <?= $tahunIni ?></h4>
            <h2>Rp <?= number_format($totalPendapatanTahunIni, 0, ',', '.') ?></h2>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-wallet"></i>
            <h4>Total Pengeluaran Gaji <?= $tahunIni ?></h4>
            <h2>Rp <?= number_format($totalGajiTahunIni, 0, ',', '.') ?></h2>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-calculator"></i>
            <h4>Laba Bersih <?= $tahunIni ?></h4>
            <h2>Rp <?= number_format($totalPendapatanTahunIni - $totalGajiTahunIni, 0, ',', '.') ?></h2>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-chart-line"></i>
            <h4>Rata-rata per Bulan</h4>
            <h2>Rp <?= number_format($totalPendapatanTahunIni / 12, 0, ',', '.') ?></h2>
        </div>
    </div>
    
    <!-- CHARTS -->
    <div class="chart-area">
        <div class="chart-box">
            <h3><i class="fas fa-chart-line"></i> Grafik Pendapatan vs Pengeluaran</h3>
            <canvas id="pendapatanChart"></canvas>
        </div>
        
        <div class="chart-box">
            <h3><i class="fas fa-chart-pie"></i> Rasio Pendapatan vs Gaji</h3>
            <canvas id="rasioChart"></canvas>
        </div>
    </div>
    
    <!-- REPORT TABLE -->
    <div class="report-table-container">
        <h3><i class="fas fa-table"></i> Laporan Bulanan <?= $tahunIni ?></h3>
        
        <?php if(mysqli_num_rows($laporanQuery) > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th>Tahun</th>
                        <th>Pendapatan</th>
                        <th>Terakhir Update</th>
                        <th>Diupdate Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($laporanQuery)): 
                        $namaBulan = date('F', mktime(0, 0, 0, $row['bulan'], 1));
                    ?>
                    <tr>
                        <td>
                            <span class="bulan-badge"><?= $namaBulan ?></span>
                        </td>
                        <td><?= $row['tahun'] ?></td>
                        <td><strong>Rp <?= number_format($row['pendapatan'], 0, ',', '.') ?></strong></td>
                        <td><?= $row['last_updated'] ?></td>
                        <td><?= htmlspecialchars($row['updated_by_name'] ?? 'System') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #999;">
            <i class="fas fa-file-excel" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.3;"></i>
            <h3 style="color: #666; margin-bottom: 10px;">Belum ada laporan bulanan</h3>
            <p>Data laporan akan muncul setelah diinput oleh Administrator.</p>
        </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="javascript:void(0)" class="action-btn pdf" onclick="openDownloadModal()">
                <i class="fas fa-download"></i> Unduh Laporan
            </a>
            <a href="javascript:void(0)" class="action-btn excel" onclick="openDownloadModal()">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="javascript:void(0)" class="action-btn print" onclick="printReport()">
                <i class="fas fa-print"></i> Print
            </a>
        </div>
    </div>
    
    <!-- INFO BOX -->
    <div class="info-box">
        <h4><i class="fas fa-info-circle"></i> Informasi Laporan</h4>
        <p><strong>Periode Laporan:</strong> Tahun <?= $tahunIni ?></p>
        <p><strong>Total Data:</strong> <?= mysqli_num_rows($laporanQuery) ?> bulan</p>
        <p><strong>Fitur Download:</strong> Tersedia format Excel dan PDF</p>
        <p><strong>Catatan:</strong> Laporan ini bersifat read-only untuk data, namun owner dapat mengunduh laporan.</p>
    </div>
</div>

<script>
// Grafik Pendapatan vs Pengeluaran
const pendapatanCtx = document.getElementById('pendapatanChart').getContext('2d');
new Chart(pendapatanCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($bulanLabels) ?>,
        datasets: [
            {
                label: 'Pendapatan',
                data: <?= json_encode($pendapatanData) ?>,
                borderColor: '#2ed573',
                backgroundColor: 'rgba(46, 213, 115, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Pengeluaran Gaji',
                data: <?= json_encode($gajiData) ?>,
                borderColor: '#ff4757',
                backgroundColor: 'rgba(255, 71, 87, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        if (value >= 1000000) {
                            return 'Rp ' + (value / 1000000).toFixed(1) + ' JT';
                        } else if (value >= 1000) {
                            return 'Rp ' + (value / 1000).toFixed(1) + ' RB';
                        }
                        return 'Rp ' + value;
                    }
                }
            }
        }
    }
});

// Grafik Rasio
const rasioCtx = document.getElementById('rasioChart').getContext('2d');
new Chart(rasioCtx, {
    type: 'doughnut',
    data: {
        labels: ['Pendapatan', 'Pengeluaran Gaji', 'Laba Bersih'],
        datasets: [{
            data: [
                <?= $totalPendapatanTahunIni ?>,
                <?= $totalGajiTahunIni ?>,
                <?= $totalPendapatanTahunIni - $totalGajiTahunIni ?>
            ],
            backgroundColor: [
                '#2ed573',
                '#ff4757',
                '#3742fa'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.raw;
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = Math.round((value / total) * 100);
                        
                        if (label) {
                            label += ': ';
                        }
                        if (value >= 1000000) {
                            label += 'Rp ' + (value / 1000000).toFixed(1) + ' JT';
                        } else if (value >= 1000) {
                            label += 'Rp ' + (value / 1000).toFixed(1) + ' RB';
                        } else {
                            label += 'Rp ' + value;
                        }
                        label += ' (' + percentage + '%)';
                        return label;
                    }
                }
            }
        }
    }
});

// Download Modal Functions
function openDownloadModal() {
    document.getElementById('downloadModal').style.display = 'flex';
}

function closeDownloadModal() {
    document.getElementById('downloadModal').style.display = 'none';
}

function showDownloading() {
    alert('⏳ Mengunduh laporan...\n\nFile akan segera diunduh. Jika tidak otomatis, periksa folder download Anda.');
    closeDownloadModal();
}

function printReport() {
    window.print();
    closeDownloadModal();
}

// Close modal when clicking outside
document.getElementById('downloadModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDownloadModal();
    }
});

// Animasi untuk stat cards
document.addEventListener('DOMContentLoaded', function() {
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 * (index + 1));
    });
});

// Auto-refresh data setiap 5 menit
setTimeout(() => {
    console.log('Auto-refresh laporan...');
}, 300000);
</script>

</body>
</html>