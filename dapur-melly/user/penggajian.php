<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * VALIDASI SESSION USER
 */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit;
}

/**
 * KONEKSI DATABASE
 */
include '../config/koneksi.php';

// =====================
// SET TIMEZONE KE ASIA/JAKARTA
// =====================
date_default_timezone_set('Asia/Jakarta');

// Debug: Cek session variables
error_log("DEBUG: Session Role: " . ($_SESSION['role'] ?? 'TIDAK ADA'));
error_log("DEBUG: Session ID: " . ($_SESSION['id'] ?? 'TIDAK ADA'));
error_log("DEBUG: Session User ID: " . ($_SESSION['user_id'] ?? 'TIDAK ADA'));
error_log("DEBUG: Session Username: " . ($_SESSION['username'] ?? 'TIDAK ADA'));

/**
 * TENTUKAN USER_ID YANG BENAR
 * Dari database Anda, tabel 'users' memiliki kolom 'id' (bukan 'user_id')
 * Tabel 'karyawan' memiliki foreign key 'user_id' yang merujuk ke 'users.id'
 */
if (isset($_SESSION['id'])) {
    $user_id = $_SESSION['id'];
} elseif (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    die("<div style='padding: 20px; text-align: center;'><h3>Session tidak valid</h3><p>Silakan login kembali.</p></div>");
}

$nama_user = $_SESSION['nama_lengkap'] ?? 'User';
$role_user = $_SESSION['role'] ?? 'user';

/**
 * AMBIL DATA KARYAWAN
 * Debug query terlebih dahulu
 */
error_log("DEBUG: Mencari karyawan dengan user_id = $user_id");

$query_karyawan = mysqli_query($conn, "SELECT * FROM karyawan WHERE user_id = '$user_id'");
if (!$query_karyawan) {
    die("<div style='padding: 20px; text-align: center;'><h3>Error Query: " . mysqli_error($conn) . "</h3></div>");
}

$jumlah_karyawan = mysqli_num_rows($query_karyawan);
error_log("DEBUG: Jumlah karyawan ditemukan: $jumlah_karyawan");

if ($jumlah_karyawan == 0) {
    // Cek apakah user ada di tabel users
    $query_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
    $user_exists = mysqli_num_rows($query_user);
    
    if ($user_exists > 0) {
        // User ada tapi belum punya data karyawan
        echo "<div style='padding: 20px; text-align: center;'>";
        echo "<h3>Data karyawan belum lengkap</h3>";
        echo "<p>Akun Anda terdaftar sebagai user, tetapi data karyawan belum dibuat oleh admin.</p>";
        echo "<p>Hubungi admin untuk melengkapi data karyawan Anda.</p>";
        echo "<br>";
        echo "<p><small>User ID: $user_id</small></p>";
        echo "<p><small>Nama: $nama_user</small></p>";
        echo "</div>";
        exit;
    } else {
        die("<div style='padding: 20px; text-align: center;'><h3>User tidak ditemukan</h3><p>Silakan login kembali.</p></div>");
    }
}

$data_karyawan = mysqli_fetch_assoc($query_karyawan);

$id_karyawan = $data_karyawan['id_karyawan'];
$gaji_pokok = $data_karyawan['gaji_pokok'] ?? 0;
$posisi_karyawan = $data_karyawan['posisi'] ?? 'Belum diatur';

// Debug data yang ditemukan
error_log("DEBUG: ID Karyawan: $id_karyawan");
error_log("DEBUG: Gaji Pokok: $gaji_pokok");
error_log("DEBUG: Posisi: $posisi_karyawan");

/**
 * LOGIKA HITUNG ESTIMASI (REAL-TIME)
 */
$bulan_ini = date('m');
$tahun_ini = date('Y');
$today = date('Y-m-d');

// Hitung Kehadiran
$q_hadir = mysqli_query($conn, "SELECT COUNT(*) as total FROM presensi WHERE id_karyawan = '$id_karyawan' AND MONTH(tanggal) = '$bulan_ini' AND YEAR(tanggal) = '$tahun_ini' AND status = 'masuk'");
$total_hadir = mysqli_fetch_assoc($q_hadir)['total'] ?? 0;

// Hitung Potongan Terlambat
$q_telat = mysqli_query($conn, "SELECT COUNT(*) as total FROM presensi WHERE id_karyawan = '$id_karyawan' AND MONTH(tanggal) = '$bulan_ini' AND YEAR(tanggal) = '$tahun_ini' AND (keterangan_lembur LIKE '%Terlambat%' OR keterangan_lembur LIKE '%terlambat%')");
$total_telat = mysqli_fetch_assoc($q_telat)['total'] ?? 0;

$uang_makan = $total_hadir * 20000;
$potongan_terlambat = $total_telat * 5000;
$estimasi_gaji = $gaji_pokok + $uang_makan - $potongan_terlambat;

/**
 * FORMAT CURRENCY FUNCTION
 */
function formatCurrency($amount) {
    if ($amount >= 1000000000) {
        return 'Rp ' . number_format($amount / 1000000000, 1, ',', '.') . ' M';
    } elseif ($amount >= 1000000) {
        return 'Rp ' . number_format($amount / 1000000, 1, ',', '.') . ' JT';
    } elseif ($amount >= 1000) {
        return 'Rp ' . number_format($amount / 1000, 1, ',', '.') . ' RB';
    } else {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

$estimasi_formatted = formatCurrency($estimasi_gaji);
$gaji_pokok_formatted = formatCurrency($gaji_pokok);
$uang_makan_formatted = formatCurrency($uang_makan);
$potongan_terlambat_formatted = formatCurrency($potongan_terlambat);

/**
 * AMBIL RIWAYAT GAJI YANG SUDAH LUNAS
 */
$riwayat_gaji = [];
$riwayat_query = mysqli_query($conn, "
    SELECT * 
    FROM penggajian 
    WHERE id_karyawan = '$id_karyawan' 
    AND status_bayar = 'lunas'
    ORDER BY tahun DESC, bulan DESC
");

if ($riwayat_query) {
    while ($row = mysqli_fetch_assoc($riwayat_query)) {
        $riwayat_gaji[] = $row;
    }
}

/**
 * ARRAY NAMA BULAN
 */
$months = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

/**
 * COUNTDOWN GAJIAN
 */
$daysLeft = 0;
if (!empty($data_karyawan['tgl_gajian']) && $data_karyawan['tgl_gajian'] != '0000-00-00') {
    try {
        $tglGajian = new DateTime($data_karyawan['tgl_gajian']);
        $todayObj = new DateTime();
        
        if ($tglGajian < $todayObj) {
            $tglGajian->modify('+1 month');
        }
        $interval = $todayObj->diff($tglGajian);
        $daysLeft = $interval->days;
    } catch (Exception $e) {
        // Default tanggal 25
        $tglGajianDefault = new DateTime(date('Y-m-25'));
        $todayObj = new DateTime();
        if ($tglGajianDefault < $todayObj) {
            $tglGajianDefault->modify('+1 month');
        }
        $interval = $todayObj->diff($tglGajianDefault);
        $daysLeft = $interval->days;
    }
} else {
    // Default tanggal 25
    $tglGajianDefault = new DateTime(date('Y-m-25'));
    $todayObj = new DateTime();
    if ($tglGajianDefault < $todayObj) {
        $tglGajianDefault->modify('+1 month');
    }
    $interval = $todayObj->diff($tglGajianDefault);
    $daysLeft = $interval->days;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Penggajian Saya - Dapur Melly</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<!-- Bootstrap CSS for Modal -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
/* ===================== */
/* VARIABLES & RESET */
/* ===================== */
:root {
    --pink: #ff7eb3;
    --pink-dark: #ff5f9e;
    --pink-soft: #ffe3f0;
    --peach: #ffb199;
    --white: #fff;
    --shadow: 0 12px 30px rgba(0,0,0,.08);
    --gradient-pink: linear-gradient(135deg, var(--pink), var(--peach));
    --gradient-success: linear-gradient(135deg, #4CAF50, #45a049);
    --gradient-warning: linear-gradient(135deg, #ff9800, #ff5722);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, var(--pink-soft), #fff);
    min-height: 100vh;
    color: #333;
    overflow-x: hidden;
}

/* ===================== */
/* MAIN CONTENT */
/* ===================== */
.main {
    margin-left: 20px;
    padding: 40px;
    min-height: 100vh;
    transition: margin-left 0.35s ease;
}

/* ===================== */
/* HEADER */
/* ===================== */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.header-left h1 {
    color: var(--pink-dark);
    margin: 0 0 5px 0;
    font-size: 28px;
}

.header-left p {
    color: #777;
    margin: 0;
}

/* BADGES */
.countdown-badge {
    background: var(--gradient-pink);
    color: white;
    padding: 8px 20px;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 600;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ===================== */
/* TOP ESTIMASI CARD */
/* ===================== */
.top-estimasi-card {
    background: var(--gradient-pink);
    color: white;
    padding: 30px;
    border-radius: 25px;
    box-shadow: var(--shadow);
    margin-bottom: 40px;
    position: relative;
    overflow: hidden;
    text-align: center;
}

.top-estimasi-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
    background-size: 20px 20px;
    animation: sparkle 4s linear infinite;
}

@keyframes sparkle {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.estimasi-icon {
    font-size: 60px;
    margin-bottom: 20px;
    animation: bounce 2s infinite;
    position: relative;
    z-index: 2;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.estimasi-title {
    font-size: 20px;
    margin-bottom: 10px;
    opacity: 0.9;
    position: relative;
    z-index: 2;
}

.estimasi-amount {
    font-size: 48px;
    font-weight: 900;
    margin: 20px 0;
    text-shadow: 0 4px 8px rgba(0,0,0,0.3);
    position: relative;
    z-index: 2;
    animation: glow 2s ease-in-out infinite alternate;
}

@keyframes glow {
    from { text-shadow: 0 4px 8px rgba(0,0,0,0.3); }
    to { text-shadow: 0 0 20px rgba(255,255,255,0.8), 0 0 30px rgba(255,255,255,0.6); }
}

.estimasi-detail {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 30px;
    position: relative;
    z-index: 2;
}

.detail-item {
    background: rgba(255,255,255,0.2);
    padding: 20px;
    border-radius: 15px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.3);
    transition: all 0.3s;
}

.detail-item:hover {
    transform: translateY(-5px);
    background: rgba(255,255,255,0.3);
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}

.detail-item .label {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-item .value {
    font-size: 24px;
    font-weight: 700;
}

.detail-item .subtext {
    font-size: 12px;
    opacity: 0.8;
    margin-top: 5px;
}

/* ===================== */
/* RIWAYAT GAJI SECTION */
/* ===================== */
.riwayat-gaji-section {
    background: var(--white);
    padding: 30px;
    border-radius: 22px;
    box-shadow: var(--shadow);
    margin-top: 40px;
}

.riwayat-gaji-section h3 {
    color: var(--pink-dark);
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 20px;
}

.riwayat-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 14px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.riwayat-table th {
    background: var(--gradient-pink);
    color: white;
    padding: 16px;
    text-align: left;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    position: sticky;
    top: 0;
}

.riwayat-table td {
    padding: 16px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
    transition: all 0.2s;
}

.riwayat-table tr {
    transition: all 0.3s;
}

.riwayat-table tr:not(:first-child):hover {
    background: var(--pink-soft);
    transform: scale(1.002);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.riwayat-table .status-lunas {
    color: #4CAF50;
    font-weight: 600;
    background: rgba(76, 175, 80, 0.1);
    padding: 8px 16px;
    border-radius: 25px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(76, 175, 80, 0.3);
}

/* Tombol Lihat Slip */
.btn-lihat-slip {
    background: var(--gradient-pink);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-lihat-slip:hover {
    background: var(--pink-dark);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255,126,179,0.3);
    color: white;
    text-decoration: none;
}

/* ===================== */
/* MODAL SLIP GAJI */
/* ===================== */
.modal-slip-header {
    background: var(--gradient-pink);
    color: white;
    border-radius: 15px 15px 0 0;
}

.modal-slip-body {
    padding: 30px;
}

.slip-logo {
    text-align: center;
    margin-bottom: 25px;
}

.slip-logo h4 {
    color: var(--pink-dark);
    font-weight: 700;
    margin: 10px 0 5px;
}

.slip-logo p {
    color: #666;
    font-size: 14px;
}

.rincian-gaji {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 12px;
    margin: 20px 0;
}

.rincian-item {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px dashed #ddd;
}

.rincian-item:last-child {
    border-bottom: none;
}

.rincian-item.total {
    font-weight: 700;
    font-size: 18px;
    color: var(--pink-dark);
    border-top: 2px solid var(--pink);
    padding-top: 15px;
    margin-top: 10px;
}

.slip-footer {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 0 0 15px 15px;
    text-align: center;
    border-top: 1px solid #eee;
}

.status-badge {
    display: inline-block;
    background: var(--gradient-success);
    color: white;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 16px;
}

/* ===================== */
/* RESPONSIVE */
/* ===================== */
@media (max-width: 1024px) {
    .estimasi-detail {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .main {
        padding: 25px;
        margin-left: 0;
    }
    
    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .estimasi-amount {
        font-size: 36px;
    }
    
    .estimasi-detail {
        grid-template-columns: 1fr;
    }
    
    .riwayat-table {
        display: block;
        overflow-x: auto;
        font-size: 13px;
    }
}

@media (max-width: 480px) {
    .main {
        padding: 20px;
    }
    
    .estimasi-amount {
        font-size: 28px;
    }
    
    .detail-item .value {
        font-size: 20px;
    }
    
    .header-left h1 {
        font-size: 24px;
    }
    
    .modal-slip-body {
        padding: 20px;
    }
}

/* DEBUG PANEL */
.debug-panel {
    position: fixed;
    bottom: 10px;
    right: 10px;
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 10px;
    border-radius: 5px;
    font-size: 12px;
    z-index: 9999;
    max-width: 300px;
    display: none; /* Set to 'block' to show debug info */
}
</style>
</head>

<body>

<!-- INCLUDE SIDEBAR -->
<?php include '../includes/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="main">

<!-- DEBUG INFO (Dapat diaktifkan dengan mengubah display: none ke block) -->
<div class="debug-panel" style="display: none;">
    <strong>Debug Info:</strong><br>
    User ID: <?= $user_id ?><br>
    Nama: <?= $nama_user ?><br>
    Role: <?= $role_user ?><br>
    ID Karyawan: <?= $id_karyawan ?><br>
    Gaji Pokok: Rp <?= number_format($gaji_pokok, 0, ',', '.') ?>
</div>

<div class="header">
    <div class="header-left">
        <h1>💰 Riwayat & Estimasi Gaji</h1>
        <p>Lihat estimasi gaji bulan ini dan riwayat pembayaran gaji Anda</p>
    </div>
    
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <?php if($daysLeft > 0): ?>
        <div class="countdown-badge">
            <i class="fas fa-calendar-alt"></i>
            Gajian dalam <strong><?= $daysLeft ?></strong> hari lagi
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- INFO KARYAWAN -->
<div style="margin-bottom: 30px; padding: 20px; background: white; border-radius: 20px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div>
            <h4 style="color: var(--pink-dark); margin-bottom: 10px;">
                <i class="fas fa-user-circle"></i> Informasi Karyawan
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <div style="font-size: 13px; color: #666; margin-bottom: 3px;">Nama</div>
                    <div style="font-weight: 700; font-size: 16px;"><?= htmlspecialchars($nama_user) ?></div>
                </div>
                <div>
                    <div style="font-size: 13px; color: #666; margin-bottom: 3px;">ID Karyawan</div>
                    <div style="font-weight: 700; font-size: 16px; color: var(--pink-dark);">#<?= $id_karyawan ?></div>
                </div>
                <div>
                    <div style="font-size: 13px; color: #666; margin-bottom: 3px;">Posisi</div>
                    <div style="font-weight: 700; font-size: 16px;"><?= htmlspecialchars($posisi_karyawan) ?></div>
                </div>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 13px; color: #666; margin-bottom: 3px;">Tanggal</div>
            <div style="font-weight: 700; font-size: 16px; color: var(--pink-dark);">
                <?= date('d F Y') ?>
            </div>
            <div style="font-size: 12px; color: #999;">
                Periode: <?= $months[$bulan_ini] ?? $bulan_ini ?> <?= $tahun_ini ?>
            </div>
        </div>
    </div>
</div>

<!-- TOP ESTIMASI CARD -->
<div class="top-estimasi-card">
    <div class="estimasi-icon">
        <i class="fas fa-chart-line"></i>
    </div>
    <h3 class="estimasi-title">Estimasi Gaji Diterima (Bulan Ini)</h3>
    <div class="estimasi-amount"><?= $estimasi_formatted ?></div>
    
    <div class="estimasi-detail">
        <div class="detail-item">
            <div class="label"><i class="fas fa-money-bill-wave"></i> Gaji Pokok</div>
            <div class="value"><?= $gaji_pokok_formatted ?></div>
            <div class="subtext">Berdasarkan kontrak</div>
        </div>
        
        <div class="detail-item">
            <div class="label"><i class="fas fa-utensils"></i> Uang Makan</div>
            <div class="value">+ <?= $uang_makan_formatted ?></div>
            <div class="subtext"><?= $total_hadir ?> hari x Rp20.000</div>
        </div>
        
        <div class="detail-item">
            <div class="label"><i class="fas fa-clock"></i> Potongan Terlambat</div>
            <div class="value">- <?= $potongan_terlambat_formatted ?></div>
            <div class="subtext"><?= $total_telat ?> x Rp5.000</div>
        </div>
        
        <div class="detail-item" style="background: rgba(255,255,255,0.3); border: 2px solid rgba(255,255,255,0.5);">
            <div class="label"><i class="fas fa-calculator"></i> Total Estimasi</div>
            <div class="value" style="font-size: 28px; color: #fff;"><?= $estimasi_formatted ?></div>
            <div class="subtext">Per <?= date('F Y') ?></div>
        </div>
    </div>
    
    <div style="margin-top: 30px; font-size: 13px; opacity: 0.8; position: relative; z-index: 2;">
        <i class="fas fa-info-circle"></i> Estimasi ini akan terus update berdasarkan presensi harian
    </div>
</div>

<!-- RIWAYAT GAJI SECTION -->
<div class="riwayat-gaji-section">
    <h3><i class="fas fa-history"></i> Riwayat Gaji Diterima</h3>
    
    <?php if(empty($riwayat_gaji)): ?>
        <div style="text-align: center; padding: 60px 20px; color: #666;">
            <i class="fas fa-inbox" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
            <h4 style="color: #999; margin-bottom: 10px;">Belum ada riwayat gaji</h4>
            <p>Riwayat gaji akan muncul setelah pembayaran gaji Anda dilunaskan oleh admin.</p>
        </div>
    <?php else: ?>
        <table class="riwayat-table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Gaji Pokok</th>
                    <th>Tunjangan</th>
                    <th>Potongan</th>
                    <th>Total Diterima</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($riwayat_gaji as $gaji): 
                    $bulan_nama = $months[str_pad($gaji['bulan'], 2, '0', STR_PAD_LEFT)] ?? 'Bulan ' . $gaji['bulan'];
                    $total_diterima = $gaji['gaji_bersih'];
                ?>
                <tr>
                    <td>
                        <strong><?= $bulan_nama ?> <?= $gaji['tahun'] ?></strong>
                        <?php if($gaji['tgl_bayar_aktual']): ?>
                        <br><small style="color: #666;">Dibayar: <?= date('d/m/Y', strtotime($gaji['tgl_bayar_aktual'])) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>Rp <?= number_format($gaji['gaji_pokok'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($gaji['tunjangan'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($gaji['potongan'], 0, ',', '.') ?></td>
                    <td><strong style="color: var(--pink-dark);">Rp <?= number_format($total_diterima, 0, ',', '.') ?></strong></td>
                    <td>
                        <span class="status-lunas">
                            <i class="fas fa-check-circle"></i> Lunas
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn-lihat-slip" data-bs-toggle="modal" data-bs-target="#slipModal<?= $gaji['id'] ?>">
                            <i class="fas fa-eye"></i> Lihat Slip
                        </button>
                    </td>
                </tr>
                
                <!-- Modal untuk Slip Gaji -->
                <div class="modal fade" id="slipModal<?= $gaji['id'] ?>" tabindex="-1" aria-labelledby="slipModalLabel<?= $gaji['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header modal-slip-header">
                                <h5 class="modal-title" id="slipModalLabel<?= $gaji['id'] ?>">
                                    <i class="fas fa-file-invoice"></i> Slip Gaji
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-slip-body">
                                <!-- Header Slip -->
                                <div class="slip-logo">
                                    <div style="width: 80px; height: 80px; background: var(--gradient-pink); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 30px; margin-bottom: 15px;">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                    <h4>Dapur Melly</h4>
                                    <p>Jl. Contoh No. 123, Jakarta<br>Telp: (021) 123-4567</p>
                                </div>
                                
                                <!-- Info Karyawan -->
                                <div style="display: flex; justify-content: space-between; margin-bottom: 30px; flex-wrap: wrap; gap: 20px; padding: 20px; background: #f5f5f5; border-radius: 12px;">
                                    <div>
                                        <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Nama Karyawan</div>
                                        <div style="font-size: 18px; font-weight: 700; color: #333;"><?= htmlspecialchars($nama_user) ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: #666; margin-bottom: 5px;">ID Karyawan</div>
                                        <div style="font-size: 18px; font-weight: 700; color: #333;">#<?= $id_karyawan ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Posisi</div>
                                        <div style="font-size: 18px; font-weight: 700; color: #333;"><?= htmlspecialchars($posisi_karyawan) ?></div>
                                    </div>
                                </div>
                                
                                <!-- Info Periode -->
                                <div style="text-align: center; margin-bottom: 30px; padding: 15px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 10px;">
                                    <div style="font-size: 14px; opacity: 0.9;">Periode Gaji</div>
                                    <div style="font-size: 24px; font-weight: 900;"><?= $bulan_nama ?> <?= $gaji['tahun'] ?></div>
                                    <?php if($gaji['tgl_bayar_aktual']): ?>
                                    <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">
                                        Dibayar pada: <?= date('d F Y', strtotime($gaji['tgl_bayar_aktual'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Rincian Gaji -->
                                <div class="rincian-gaji">
                                    <h5 style="color: var(--pink-dark); margin-bottom: 20px; text-align: center;">
                                        <i class="fas fa-list-alt"></i> Rincian Gaji
                                    </h5>
                                    
                                    <div class="rincian-item">
                                        <div>Gaji Pokok</div>
                                        <div>Rp <?= number_format($gaji['gaji_pokok'], 0, ',', '.') ?></div>
                                    </div>
                                    
                                    <div class="rincian-item">
                                        <div>Tunjangan</div>
                                        <div>Rp <?= number_format($gaji['tunjangan'], 0, ',', '.') ?></div>
                                    </div>
                                    
                                    <div class="rincian-item">
                                        <div>Potongan</div>
                                        <div>Rp <?= number_format($gaji['potongan'], 0, ',', '.') ?></div>
                                    </div>
                                    
                                    <div class="rincian-item" style="border-top: 2px solid #ddd; padding-top: 15px; margin-top: 10px;">
                                        <div>Subtotal</div>
                                        <div>Rp <?= number_format($gaji['gaji_pokok'] + $gaji['tunjangan'] - $gaji['potongan'], 0, ',', '.') ?></div>
                                    </div>
                                    
                                    <div class="rincian-item total">
                                        <div>TOTAL DITERIMA</div>
                                        <div>Rp <?= number_format($total_diterima, 0, ',', '.') ?></div>
                                    </div>
                                </div>
                                
                                <!-- Keterangan -->
                                <div style="margin-top: 25px; padding: 15px; background: #e8f4fd; border-radius: 10px; border-left: 4px solid #2196F3;">
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <i class="fas fa-info-circle" style="color: #2196F3; font-size: 20px; margin-top: 2px;"></i>
                                        <div>
                                            <div style="font-weight: 600; color: #333;">Informasi:</div>
                                            <div style="font-size: 14px; color: #666; margin-top: 5px;">
                                                Slip gaji ini adalah bukti pembayaran resmi dari Dapur Melly. Simpan dengan baik untuk keperluan administrasi.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="slip-footer">
                                <span class="status-badge">
                                    <i class="fas fa-check-circle"></i> LUNAS
                                </span>
                                <div style="margin-top: 15px; color: #666; font-size: 12px;">
                                    Dicetak pada: <?= date('d F Y H:i') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; text-align: center; color: #666; font-size: 14px;">
            <i class="fas fa-info-circle" style="color: var(--pink);"></i> 
            Menampilkan <?= count($riwayat_gaji) ?> riwayat gaji yang sudah dilunaskan
        </div>
    <?php endif; ?>
</div>

<!-- INFO TAMBAHAN -->
<div style="margin-top: 40px; padding: 25px; background: white; border-radius: 20px; box-shadow: var(--shadow);">
    <h4 style="color: var(--pink-dark); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-question-circle"></i> Informasi Penting
    </h4>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div style="padding: 20px; background: #f9f9f9; border-radius: 12px; border-left: 4px solid var(--pink);">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                <div style="width: 40px; height: 40px; background: var(--gradient-pink); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fas fa-calculator"></i>
                </div>
                <div>
                    <div style="font-weight: 700; color: #333;">Perhitungan Estimasi</div>
                    <div style="font-size: 13px; color: #666;">Estimasi dihitung secara real-time berdasarkan presensi Anda</div>
                </div>
            </div>
            <ul style="font-size: 14px; color: #666; padding-left: 20px;">
                <li>Uang Makan: Rp20.000 per hari masuk</li>
                <li>Potongan Terlambat: Rp5.000 per kejadian</li>
                <li>Gaji pokok sesuai kontrak kerja</li>
            </ul>
        </div>
        
        <div style="padding: 20px; background: #f9f9f9; border-radius: 12px; border-left: 4px solid #4CAF50;">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #4CAF50, #45a049); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div>
                    <div style="font-weight: 700; color: #333;">Jadwal Pembayaran</div>
                    <div style="font-size: 13px; color: #666;">Gaji dibayarkan sesuai tanggal yang ditentukan</div>
                </div>
            </div>
            <ul style="font-size: 14px; color: #666; padding-left: 20px;">
                <li>Tanggal gajian Anda: 
                    <?php if(!empty($data_karyawan['tgl_gajian_rutin'])): ?>
                        Setiap tanggal <strong><?= $data_karyawan['tgl_gajian_rutin'] ?></strong>
                    <?php else: ?>
                        <span style="color: #999;">Belum diatur</span>
                    <?php endif; ?>
                </li>
                <li>Gaji akan masuk maksimal H+1 setelah tanggal gajian</li>
                <li>Hubungi admin jika ada keterlambatan > 2 hari</li>
            </ul>
        </div>
    </div>
</div>

</div>

<!-- Bootstrap JS untuk Modal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Auto-refresh estimasi setiap 5 menit
setTimeout(() => {
    const now = new Date();
    const hours = now.getHours();
    
    // Refresh hanya pada jam kerja (8-17)
    if (hours >= 8 && hours < 17) {
        // Bukan reload penuh, hanya tampilkan notifikasi
        const notificationDiv = document.createElement('div');
        notificationDiv.innerHTML = `
            <div style="position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 15px 25px; border-radius: 10px; z-index: 10000; box-shadow: 0 5px 20px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; animation: slideIn 0.5s ease;">
                <i class="fas fa-sync-alt" style="font-size: 20px;"></i>
                <div>
                    <strong>Estimasi gaji telah diperbarui</strong>
                    <div style="font-size: 13px; opacity: 0.9;">Berdasarkan presensi terkini</div>
                </div>
                <button onclick="location.reload()" style="background: white; color: #4CAF50; border: none; padding: 5px 15px; border-radius: 5px; font-weight: 600; cursor: pointer; margin-left: 10px;">
                    Refresh
                </button>
            </div>
        `;
        document.body.appendChild(notificationDiv);
        
        // Hapus notifikasi setelah 8 detik
        setTimeout(() => {
            notificationDiv.style.opacity = '0';
            setTimeout(() => notificationDiv.remove(), 500);
        }, 8000);
    }
}, 5 * 60 * 1000); // Setiap 5 menit

// Tambahkan CSS untuk animasi slideIn
const style = document.createElement('style');
style.textContent = `
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
`;
document.head.appendChild(style);

// Fungsi untuk print slip gaji
function printSlip(id) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Slip Gaji - Dapur Melly</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #ff5f9e; }
                .info { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 8px; }
                .rincian { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .rincian th, .rincian td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
                .rincian th { background: #ff7eb3; color: white; }
                .total { font-weight: bold; font-size: 18px; color: #ff5f9e; }
                .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="logo">Dapur Melly</div>
                <div>Jl. Contoh No. 123, Jakarta</div>
                <div>Telp: (021) 123-4567</div>
            </div>
            
            <div class="info">
                <strong>Nama:</strong> <?= htmlspecialchars($nama_user) ?><br>
                <strong>ID Karyawan:</strong> #<?= $id_karyawan ?><br>
                <strong>Posisi:</strong> <?= htmlspecialchars($posisi_karyawan) ?>
            </div>
            
            <div style="text-align: center; margin: 20px 0;">
                <h3>SLIP GAJI - PERIODE [BULAN] [TAHUN]</h3>
            </div>
            
            <table class="rincian">
                <tr>
                    <th>Keterangan</th>
                    <th>Jumlah</th>
                </tr>
                <tr>
                    <td>Gaji Pokok</td>
                    <td>Rp [NOMINAL]</td>
                </tr>
                <tr>
                    <td>Tunjangan</td>
                    <td>Rp [NOMINAL]</td>
                </tr>
                <tr>
                    <td>Potongan</td>
                    <td>Rp [NOMINAL]</td>
                </tr>
                <tr class="total">
                    <td>TOTAL DITERIMA</td>
                    <td>Rp [TOTAL]</td>
                </tr>
            </table>
            
            <div class="footer">
                Dicetak pada: <?= date('d F Y H:i') ?><br>
                Slip ini adalah bukti pembayaran resmi dari Dapur Melly
            </div>
            
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()">Cetak Slip</button>
                <button onclick="window.close()">Tutup</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Konfirmasi sebelum print
function confirmPrint(id, bulan, tahun) {
    Swal.fire({
        title: 'Cetak Slip Gaji?',
        html: `Anda akan mencetak slip gaji untuk periode:<br><strong>${bulan} ${tahun}</strong>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ff7eb3',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-print"></i> Ya, Cetak',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            printSlip(id);
        }
    });
}
</script>
</body>
</html>