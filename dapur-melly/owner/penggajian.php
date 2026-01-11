<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

include '../includes/sidebar.php';

$bulan  = $_GET['bulan'] ?? date('m');
$tahun  = $_GET['tahun'] ?? date('Y');
$status = $_GET['status'] ?? '';

// HARI INI untuk perbandingan
$today = date('Y-m-d');

// Query dengan logika sorting berdasarkan tanggal gajian terdekat
$where = "WHERE p.bulan = '$bulan' AND p.tahun = '$tahun'";
if ($status != '') {
    $where .= " AND p.status_bayar = '$status'";
}

$q = mysqli_query($conn, "
    SELECT 
        p.id,
        p.bulan,
        p.tahun,
        p.tunjangan,
        p.potongan,
        p.status_bayar,
        p.tgl_bayar_aktual,
        k.gaji_pokok,
        k.no_telp,
        u.nama_lengkap,
        k.tgl_gajian_rutin,
        -- Hitung selisih hari antara tanggal gajian dan hari ini
        CASE 
            WHEN k.tgl_gajian_rutin IS NOT NULL THEN 
                DATEDIFF(
                    STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(CURDATE()), '-', k.tgl_gajian_rutin), '%Y-%m-%d'),
                    CURDATE()
                )
            ELSE 999
        END as days_diff
    FROM penggajian p
    JOIN karyawan k ON p.id_karyawan = k.id_karyawan
    JOIN users u ON k.user_id = u.id
    $where
    ORDER BY 
        CASE 
            WHEN days_diff < 0 THEN days_diff + 365  -- Jika sudah lewat, urutkan berdasarkan tahun depan
            ELSE days_diff 
        END ASC,
        u.nama_lengkap ASC
");

// Untuk menampilkan semua data jika bulan ini kosong
$count = mysqli_num_rows($q);
if ($count == 0) {
    $q = mysqli_query($conn, "
        SELECT 
            p.id,
            p.bulan,
            p.tahun,
            p.tunjangan,
            p.potongan,
            p.status_bayar,
            p.tgl_bayar_aktual,
            k.gaji_pokok,
            k.no_telp,
            u.nama_lengkap,
            k.tgl_gajian_rutin,
            -- Hitung selisih hari antara tanggal gajian dan hari ini
            CASE 
                WHEN k.tgl_gajian_rutin IS NOT NULL THEN 
                    DATEDIFF(
                        STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(CURDATE()), '-', k.tgl_gajian_rutin), '%Y-%m-%d'),
                        CURDATE()
                    )
                ELSE 999
            END as days_diff
        FROM penggajian p
        JOIN karyawan k ON p.id_karyawan = k.id_karyawan
        JOIN users u ON k.user_id = u.id
        ORDER BY 
            CASE 
                WHEN days_diff < 0 THEN days_diff + 365
                ELSE days_diff 
            END ASC,
            p.tahun DESC, 
            p.bulan DESC, 
            u.nama_lengkap ASC
    ");
}

// Hitung statistik keuangan
$statsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_karyawan,
        SUM(CASE WHEN status_bayar = 'lunas' THEN 1 ELSE 0 END) as total_lunas,
        SUM(CASE WHEN status_bayar = 'belum' THEN 1 ELSE 0 END) as total_belum,
        SUM(gaji_bersih) as total_gaji_bersih,
        SUM(gaji_bersih * CASE WHEN status_bayar = 'lunas' THEN 1 ELSE 0 END) as total_gaji_dibayar
    FROM penggajian 
    WHERE bulan = '$bulan' AND tahun = '$tahun'
");
$stats = mysqli_fetch_assoc($statsQuery);

// Array nama bulan
$months = [
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
<title>Penggajian - Owner View - Dapur Melly</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --pink: #ff7eb3;
    --pink-soft: #ffe3f0;
    --green: #4CAF50;
    --blue: #2196F3;
    --orange: #FF9800;
    --red: #f44336;
    --shadow: 0 12px 30px rgba(0,0,0,.08);
}

body {
    margin:0;
    font-family:Poppins;
    background:linear-gradient(135deg,var(--pink-soft),#fff);
}
.main {
    margin-left:80px;
    padding:40px;
}
h1 {
    color:#ff5f9e;
    margin-bottom:5px;
}
.header-info {
    color:#777;
    margin-bottom:30px;
    font-size:14px;
}

/* STATS CARDS */
.stats-container {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
    gap:15px;
    margin-bottom:30px;
}
.stat-card {
    background:white;
    padding:20px;
    border-radius:15px;
    box-shadow:var(--shadow);
    border-left:5px solid;
}
.stat-card.total { border-left-color:var(--pink); }
.stat-card.lunas { border-left-color:var(--green); }
.stat-card.belum { border-left-color:var(--red); }
.stat-card.dibayar { border-left-color:var(--blue); }
.stat-card h3 {
    margin:0 0 10px 0;
    font-size:14px;
    color:#666;
    display:flex;
    align-items:center;
    gap:8px;
}
.stat-card .value {
    font-size:24px;
    font-weight:600;
    margin:0;
}
.stat-card.total .value { color:var(--pink); }
.stat-card.lunas .value { color:var(--green); }
.stat-card.belum .value { color:var(--red); }
.stat-card.dibayar .value { color:var(--blue); }

.filter {
    display:flex;
    gap:15px;
    margin-bottom:20px;
    flex-wrap:wrap;
    align-items:center;
    background:white;
    padding:20px;
    border-radius:15px;
    box-shadow:var(--shadow);
}
select, button {
    padding:10px 16px;
    border-radius:10px;
    border:2px solid var(--pink-soft);
    font-family:Poppins;
    font-size:14px;
    background:white;
}
.btn {
    background:var(--pink);
    color:#fff;
    border:none;
    cursor:pointer;
    transition:0.3s;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-weight:500;
}
.btn:hover {
    background:#ff5f9e;
    transform:translateY(-2px);
}
table {
    width:100%;
    background:#fff;
    border-radius:20px;
    box-shadow:0 10px 30px rgba(0,0,0,.1);
    overflow:hidden;
    border-collapse:collapse;
}
th, td {
    padding:12px 15px;
    text-align:left;
    border-bottom:1px solid #eee;
}
thead {
    background:linear-gradient(135deg,var(--pink),#ff5f9e);
    color:#fff;
}
tbody tr:hover {
    background-color:#fff5f9;
}
.badge {
    padding:6px 12px;
    border-radius:15px;
    font-size:12px;
    font-weight:500;
    display:inline-flex;
    align-items:center;
    gap:5px;
}
.lunas {
    background:rgba(76, 175, 80, 0.15);
    color:#2e7d32;
}
.belum {
    background:rgba(244, 67, 54, 0.15);
    color:#c62828;
}
.gajian-indicator {
    padding:4px 10px;
    border-radius:8px;
    font-size:11px;
    font-weight:500;
    margin-top:5px;
    display:inline-block;
}
.gajian-today {
    background:var(--green);
    color:white;
    animation:pulse 2s infinite;
}
.gajian-upcoming {
    background:var(--blue);
    color:white;
}
.gajian-past {
    background:var(--orange);
    color:white;
}
@keyframes pulse {
    0% { opacity:1; }
    50% { opacity:0.7; }
    100% { opacity:1; }
}
.no-data {
    text-align:center;
    padding:40px;
    color:#666;
}
.no-data i {
    font-size:48px;
    margin-bottom:15px;
    color:#ddd;
}
.summary-footer {
    margin-top:20px;
    color:#666;
    font-size:14px;
    padding:15px;
    background:#f8f9fa;
    border-radius:10px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
}
.action-buttons {
    display:flex;
    gap:10px;
    margin-top:20px;
    flex-wrap:wrap;
}
.btn-pdf {
    background:var(--red);
}
.btn-pdf:hover {
    background:#d32f2f;
}
.btn-print {
    background:#6c757d;
}
.btn-print:hover {
    background:#545b62;
}
</style>
</head>
<body>
<div class="main">
    <h1>💰 Penggajian Karyawan - Owner View</h1>
    <div class="header-info">
        <i class="fas fa-eye"></i> Mode Read-Only - Hanya untuk monitoring dan analisis
    </div>

    <!-- STATISTICS CARDS -->
    <div class="stats-container">
        <div class="stat-card total">
            <h3><i class="fas fa-users"></i> Total Karyawan</h3>
            <p class="value"><?= $stats['total_karyawan'] ?? 0 ?> orang</p>
            <small><?= $months[$bulan] ?> <?= $tahun ?></small>
        </div>
        
        <div class="stat-card lunas">
            <h3><i class="fas fa-check-circle"></i> Sudah Dibayar</h3>
            <p class="value"><?= $stats['total_lunas'] ?? 0 ?> karyawan</p>
            <small><?= $stats['total_karyawan'] > 0 ? round(($stats['total_lunas'] / $stats['total_karyawan']) * 100, 1) : 0 ?>% dari total</small>
        </div>
        
        <div class="stat-card belum">
            <h3><i class="fas fa-clock"></i> Belum Dibayar</h3>
            <p class="value"><?= $stats['total_belum'] ?? 0 ?> karyawan</p>
            <small>Perlu tindak lanjut</small>
        </div>
        
        <div class="stat-card dibayar">
            <h3><i class="fas fa-wallet"></i> Total Pengeluaran</h3>
            <p class="value">Rp <?= number_format($stats['total_gaji_dibayar'] ?? 0, 0, ',', '.') ?></p>
            <small>Gaji yang sudah dibayarkan</small>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter">
        <div style="display:flex; gap:10px; align-items:center;">
            <select name="tahun">
                <?php 
                $currentYear = date('Y');
                for($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= ($tahun == $y) ? 'selected' : '' ?>>
                        Tahun <?= $y ?>
                    </option>
                <?php endfor ?>
            </select>
            
            <select name="bulan">
                <?php foreach($months as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $bulan == $num ? 'selected' : '' ?>>
                        <?= $name ?>
                    </option>
                <?php endforeach ?>
            </select>

            <select name="status">
                <option value="">Semua Status</option>
                <option value="belum" <?= $status == 'belum' ? 'selected' : '' ?>>Belum Dibayar</option>
                <option value="lunas" <?= $status == 'lunas' ? 'selected' : '' ?>>Sudah Dibayar</option>
            </select>
        </div>

        <button type="submit" class="btn">
            <i class="fas fa-filter"></i> Filter Data
        </button>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="action-buttons">
        <button class="btn btn-pdf" onclick="generatePDF()">
            <i class="fas fa-file-pdf"></i> Export PDF
        </button>
        <button class="btn btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
    </div>

    <!-- DATA TABLE -->
    <?php if (mysqli_num_rows($q) == 0): ?>
    <div class="no-data">
        <i class="fas fa-database"></i>
        <h3>Tidak ada data penggajian</h3>
        <p>Belum ada data penggajian untuk periode yang dipilih</p>
    </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Nama Karyawan</th>
                <th>Tanggal Gajian</th>
                <th>Gaji Pokok</th>
                <th>Tunjangan</th>
                <th>Potongan</th>
                <th>Gaji Bersih</th>
                <th>Periode</th>
                <th>Status Pembayaran</th>
                <th>Tanggal Bayar</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalGajiPokok = 0;
            $totalTunjangan = 0;
            $totalPotongan = 0;
            $totalGajiBersih = 0;
            
            while($d = mysqli_fetch_assoc($q)):
                $totalGajiPokok += $d['gaji_pokok'];
                $totalTunjangan += $d['tunjangan'];
                $totalPotongan += $d['potongan'];
                $bersih = ($d['gaji_pokok'] + $d['tunjangan']) - $d['potongan'];
                $totalGajiBersih += $bersih;
                $monthName = $months[str_pad($d['bulan'], 2, '0', STR_PAD_LEFT)] ?? 'Bulan ' . $d['bulan'];
                
                // LOGIKA TANGGAL GAJIAN
                $tanggalGajian = '';
                $sisaHari = '';
                
                if (!empty($d['tgl_gajian_rutin'])) {
                    $tanggalGajian = 'Tiap tanggal <strong>' . $d['tgl_gajian_rutin'] . '</strong>';
                    
                    // Hitung selisih hari (gunakan days_diff dari query)
                    $daysDiff = $d['days_diff'];
                    
                    if ($daysDiff == 0) {
                        $sisaHari = '<span class="gajian-indicator gajian-today"><i class="fas fa-calendar-check"></i> Gajian Hari Ini!</span>';
                    } elseif ($daysDiff == 1) {
                        $sisaHari = '<span class="gajian-indicator gajian-upcoming"><i class="fas fa-clock"></i> Besok Gajian</span>';
                    } elseif ($daysDiff > 1 && $daysDiff <= 7) {
                        $sisaHari = '<span class="gajian-indicator gajian-upcoming"><i class="fas fa-hourglass-half"></i> ' . $daysDiff . ' hari lagi</span>';
                    } elseif ($daysDiff > 7) {
                        $sisaHari = '<span class="gajian-indicator gajian-upcoming">' . $daysDiff . ' hari lagi</span>';
                    } elseif ($daysDiff < 0) {
                        $sisaHari = '<span class="gajian-indicator gajian-past"><i class="fas fa-history"></i> Lewat ' . abs($daysDiff) . ' hari</span>';
                    }
                } else {
                    $tanggalGajian = '<span style="color:#999; font-size:12px;">Belum diatur</span>';
                    $sisaHari = '<span class="gajian-indicator" style="background:#9E9E9E;color:white;">Belum diatur</span>';
                }
            ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($d['nama_lengkap']) ?></strong><br>
                    <small style="color:#666; font-size:11px;"><i class="fas fa-phone"></i> <?= $d['no_telp'] ?></small>
                </td>
                
                <!-- KOLOM TANGGAL GAJIAN -->
                <td>
                    <div style="font-weight:500;"><?= $tanggalGajian ?></div>
                    <?= $sisaHari ?>
                </td>
                
                <td>Rp <?= number_format($d['gaji_pokok'], 0, ',', '.') ?></td>
                <td>Rp <?= number_format($d['tunjangan'], 0, ',', '.') ?></td>
                <td>Rp <?= number_format($d['potongan'], 0, ',', '.') ?></td>
                <td><b style="color:#ff5f9e;">Rp <?= number_format($bersih, 0, ',', '.') ?></b></td>
                <td>
                    <span style="font-weight:500;"><?= $monthName . ' ' . $d['tahun'] ?></span>
                </td>
                <td>
                    <?php 
                    $statusText = ucfirst($d['status_bayar']);
                    $statusClass = ($d['status_bayar'] == 'lunas') ? 'lunas' : 'belum';
                    ?>
                    <span class="badge <?= $statusClass ?>">
                        <?php if($d['status_bayar'] == 'lunas'): ?>
                            <i class="fas fa-check-circle"></i> <?= $statusText ?>
                        <?php else: ?>
                            <i class="fas fa-clock"></i> <?= $statusText ?>
                        <?php endif; ?>
                    </span>
                </td>
                <td>
                    <?php if ($d['tgl_bayar_aktual']): ?>
                        <span style="color:#4CAF50; font-weight:500;">
                            <i class="fas fa-calendar-check"></i> <?= date('d/m/Y', strtotime($d['tgl_bayar_aktual'])) ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#f44336; font-style:italic;">
                            <i class="fas fa-calendar-times"></i> Belum dibayar
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            
            <!-- TOTAL ROW -->
            <tr style="background:#f8f9fa; font-weight:bold;">
                <td colspan="2"><i class="fas fa-calculator"></i> TOTAL</td>
                <td>Rp <?= number_format($totalGajiPokok, 0, ',', '.') ?></td>
                <td>Rp <?= number_format($totalTunjangan, 0, ',', '.') ?></td>
                <td>Rp <?= number_format($totalPotongan, 0, ',', '.') ?></td>
                <td style="color:#ff5f9e; font-size:16px;">Rp <?= number_format($totalGajiBersih, 0, ',', '.') ?></td>
                <td colspan="3">
                    <span style="color:#666; font-size:13px;">
                        <i class="fas fa-info-circle"></i> <?= $count ?> data penggajian
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- SUMMARY FOOTER -->
    <?php if (mysqli_num_rows($q) > 0): ?>
    <div class="summary-footer">
        <div>
            <strong>Ringkasan <?= $months[$bulan] ?> <?= $tahun ?></strong><br>
            <small style="color:#666;">
                <i class="fas fa-money-bill-wave"></i> Total Pengeluaran: <strong>Rp <?= number_format($totalGajiBersih, 0, ',', '.') ?></strong> | 
                <i class="fas fa-users"></i> Karyawan: <?= $count ?> orang | 
                <i class="fas fa-check-circle" style="color:#4CAF50;"></i> Sudah dibayar: <?= $stats['total_lunas'] ?? 0 ?> | 
                <i class="fas fa-clock" style="color:#f44336;"></i> Belum dibayar: <?= $stats['total_belum'] ?? 0 ?>
            </small>
        </div>
        <div style="text-align:right;">
            <small style="color:#888;">
                <i class="fas fa-calendar-alt"></i> <?= date('d F Y H:i') ?><br>
                <i class="fas fa-user-tie"></i> Owner View
            </small>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Generate PDF Report
function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    
    // Add title
    doc.setFontSize(20);
    doc.setTextColor(255, 95, 158);
    doc.text('LAPORAN PENGAJIAN KARYAWAN', 105, 20, null, null, 'center');
    
    doc.setFontSize(12);
    doc.setTextColor(100, 100, 100);
    doc.text('Dapur Melly - Owner Report', 105, 28, null, null, 'center');
    doc.text('Periode: <?= $months[$bulan] ?> <?= $tahun ?>', 105, 34, null, null, 'center');
    doc.text('Dicetak: <?= date("d/m/Y H:i:s") ?>', 105, 40, null, null, 'center');
    
    // Add line separator
    doc.setDrawColor(255, 95, 158);
    doc.setLineWidth(0.5);
    doc.line(20, 45, 190, 45);
    
    let yPos = 55;
    
    // Summary Section
    doc.setFontSize(16);
    doc.setTextColor(255, 95, 158);
    doc.text('Ringkasan Penggajian', 20, yPos);
    
    yPos += 10;
    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    
    // Summary table
    const summaryData = [
        ['Total Karyawan', '<?= $stats['total_karyawan'] ?? 0 ?> orang'],
        ['Sudah Dibayar', '<?= $stats['total_lunas'] ?? 0 ?> orang (<?= $stats['total_karyawan'] > 0 ? round(($stats['total_lunas'] / $stats['total_karyawan']) * 100, 1) : 0 ?>%)'],
        ['Belum Dibayar', '<?= $stats['total_belum'] ?? 0 ?> orang'],
        ['Total Pengeluaran', 'Rp <?= number_format($stats['total_gaji_dibayar'] ?? 0, 0, ",", ".") ?>']
    ];
    
    summaryData.forEach((row, index) => {
        doc.text(row[0], 25, yPos);
        doc.text(row[1], 175, yPos, null, null, 'right');
        yPos += 8;
    });
    
    yPos += 10;
    
    // Detail Penggajian
    doc.setFontSize(16);
    doc.setTextColor(255, 95, 158);
    doc.text('Detail Penggajian Karyawan', 20, yPos);
    
    yPos += 10;
    
    // Table header
    doc.setFillColor(255, 95, 158);
    doc.rect(20, yPos, 170, 8, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(10);
    doc.text('Nama', 25, yPos + 6);
    doc.text('Gaji Pokok', 90, yPos + 6, null, null, 'right');
    doc.text('Tunjangan', 120, yPos + 6, null, null, 'right');
    doc.text('Potongan', 145, yPos + 6, null, null, 'right');
    doc.text('Bersih', 175, yPos + 6, null, null, 'right');
    
    yPos += 10;
    
    // Table rows (limit untuk PDF)
    doc.setTextColor(0, 0, 0);
    <?php 
    mysqli_data_seek($q, 0);
    $rowCount = 0;
    while($d = mysqli_fetch_assoc($q)): 
        if($rowCount >= 20) break; // Limit rows untuk PDF
        $bersih = ($d['gaji_pokok'] + $d['tunjangan']) - $d['potongan'];
    ?>
    doc.text('<?= addslashes(substr($d['nama_lengkap'], 0, 20)) ?>', 25, yPos + 6);
    doc.text('Rp <?= number_format($d['gaji_pokok'], 0, ",", ".") ?>', 90, yPos + 6, null, null, 'right');
    doc.text('Rp <?= number_format($d['tunjangan'], 0, ",", ".") ?>', 120, yPos + 6, null, null, 'right');
    doc.text('Rp <?= number_format($d['potongan'], 0, ",", ".") ?>', 145, yPos + 6, null, null, 'right');
    doc.text('Rp <?= number_format($bersih, 0, ",", ".") ?>', 175, yPos + 6, null, null, 'right');
    yPos += 8;
    
    // Check page break
    if(yPos > 250) {
        doc.addPage();
        yPos = 20;
    }
    <?php 
    $rowCount++;
    endwhile; 
    ?>
    
    yPos += 10;
    
    // Total row
    doc.setDrawColor(0, 0, 0);
    doc.setLineWidth(0.2);
    doc.line(20, yPos, 190, yPos);
    yPos += 5;
    
    doc.setFontSize(11);
    doc.setFont(undefined, 'bold');
    doc.text('TOTAL', 25, yPos);
    doc.text('Rp <?= number_format($totalGajiPokok, 0, ",", ".") ?>', 90, yPos, null, null, 'right');
    doc.text('Rp <?= number_format($totalTunjangan, 0, ",", ".") ?>', 120, yPos, null, null, 'right');
    doc.text('Rp <?= number_format($totalPotongan, 0, ",", ".") ?>', 145, yPos, null, null, 'right');
    doc.text('Rp <?= number_format($totalGajiBersih, 0, ",", ".") ?>', 175, yPos, null, null, 'right');
    
    yPos += 15;
    
    // Footer
    doc.setFontSize(8);
    doc.setTextColor(150, 150, 150);
    doc.text('Laporan ini dibuat otomatis untuk keperluan monitoring Owner', 105, yPos, null, null, 'center');
    yPos += 5;
    doc.text('© <?= date("Y") ?> Dapur Melly - All Rights Reserved', 105, yPos, null, null, 'center');
    
    // Save PDF
    doc.save('Laporan_Penggajian_<?= $months[$bulan] ?>_<?= $tahun ?>.pdf');
}

// Include jsPDF library
if (typeof window.jspdf === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
    script.onload = function() {
        console.log('jsPDF loaded successfully');
    };
    document.head.appendChild(script);
}
</script>

<!-- Load jsPDF Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

</body>
</html>