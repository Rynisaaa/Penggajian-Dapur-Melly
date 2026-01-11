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

/**
 * TENTUKAN USER_ID YANG BENAR
 */
$user_id = null;
$possible_user_ids = ['id', 'user_id', 'userid', 'user'];

foreach ($possible_user_ids as $key) {
    if (isset($_SESSION[$key])) {
        $user_id = $_SESSION[$key];
        break;
    }
}

if (!$user_id) {
    header("Location: ../login.php");
    exit;
}

$nama_user = $_SESSION['nama_lengkap'] ?? 'Karyawan';
$role_user = $_SESSION['role'] ?? 'user';

/**
 * AMBIL DATA USER DAN KARYAWAN
 */
$query_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
if (!$query_user || mysqli_num_rows($query_user) == 0) {
    header("Location: ../login.php");
    exit;
}
$data_user = mysqli_fetch_assoc($query_user);

// Update nama_user dari database jika perlu
if (empty($nama_user) || $nama_user == 'Karyawan') {
    $nama_user = $data_user['nama_lengkap'] ?? 'Karyawan';
    $_SESSION['nama_lengkap'] = $nama_user;
}

// Ambil data karyawan
$query_karyawan = mysqli_query($conn, "SELECT * FROM karyawan WHERE user_id = '$user_id'");
$data_karyawan = mysqli_fetch_assoc($query_karyawan);
$id_karyawan = $data_karyawan['id_karyawan'] ?? 0;
$gaji_pokok = $data_karyawan['gaji_pokok'] ?? 0;
$posisi = $data_karyawan['posisi'] ?? 'Belum ditentukan';

// Tanggal sekarang
$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');

/**
 * HITUNG STATISTIK
 */

// 1. JUMLAH HARI MASUK BULAN INI
$query_kehadiran = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM presensi 
    WHERE id_karyawan = '$id_karyawan' 
    AND MONTH(tanggal) = '$current_month' 
    AND YEAR(tanggal) = '$current_year'
    AND status = 'masuk'
");
$data_kehadiran = mysqli_fetch_assoc($query_kehadiran);
$total_kehadiran = $data_kehadiran['total'] ?? 0;

// 2. STATUS GAJI BULAN INI
$query_gaji = mysqli_query($conn, "
    SELECT status_bayar, gaji_bersih 
    FROM penggajian 
    WHERE id_karyawan = '$id_karyawan' 
    AND bulan = '$current_month' 
    AND tahun = '$current_year'
    LIMIT 1
");
$data_gaji = mysqli_fetch_assoc($query_gaji);
$status_gaji = $data_gaji['status_bayar'] ?? 'belum';
$gaji_bersih = $data_gaji['gaji_bersih'] ?? 0;

// 3. ESTIMASI GAJI BULAN INI
$uang_makan = $total_kehadiran * 20000; // Rp20.000 per hari masuk

// Hitung potongan terlambat bulan ini
$query_terlambat = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM presensi 
    WHERE id_karyawan = '$id_karyawan' 
    AND MONTH(tanggal) = '$current_month' 
    AND YEAR(tanggal) = '$current_year'
    AND (keterangan_lembur LIKE '%Terlambat%' OR keterangan_lembur LIKE '%terlambat%')
");
$data_terlambat = mysqli_fetch_assoc($query_terlambat);
$potongan_terlambat = ($data_terlambat['total'] ?? 0) * 5000; // Rp5.000 per keterlambatan

$estimasi_gaji = $gaji_pokok + $uang_makan - $potongan_terlambat;

/**
 * AMBIL RIWAYAT ABSENSI (5 TERAKHIR)
 */
$riwayat_absen = [];
$query_riwayat = mysqli_query($conn, "
    SELECT tanggal, status, keterangan_lembur,
           CASE 
               WHEN jam_masuk IS NOT NULL AND jam_masuk != '00:00:00' THEN jam_masuk
               ELSE jam_mulai_lembur 
           END as jam_absensi
    FROM presensi 
    WHERE id_karyawan = '$id_karyawan'
    ORDER BY tanggal DESC, jam_absensi DESC
    LIMIT 5
");

if ($query_riwayat) {
    while ($row = mysqli_fetch_assoc($query_riwayat)) {
        $riwayat_absen[] = $row;
    }
}

/**
 * FUNGSI FORMAT CURRENCY
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

// Format nilai mata uang
$estimasi_gaji_formatted = formatCurrency($estimasi_gaji);
$gaji_pokok_formatted = formatCurrency($gaji_pokok);
$gaji_bersih_formatted = formatCurrency($gaji_bersih);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Karyawan - Dapur Melly</title>

<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<!-- SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
/* ===================== */
/* VARIABLES & RESET */
/* ===================== */
:root {
    --pink-light: #ff9a9e;
    --pink-dark: #fecfef;
    --pink-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
    --orange-gradient: linear-gradient(135deg, #ffb199 0%, #ff5f9e 100%);
    --soft-pink: #fff0f5;
    --card-shadow: 0 10px 30px rgba(255, 154, 158, 0.15);
    --text-dark: #333;
    --text-light: #666;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f9f7fe;
    background-image: linear-gradient(120deg, #fdfbfb 0%, #ebedee 100%);
    min-height: 100vh;
    color: var(--text-dark);
    overflow-x: hidden;
}

/* ===================== */
/* MAIN CONTENT */
/* ===================== */
.main-content {
    margin-left: 20px;
    padding: 30px;
    min-height: 100vh;
    transition: margin-left 0.35s ease;
}

/* ===================== */
/* HEADER */
/* ===================== */
.page-header {
    margin-bottom: 40px;
}

.page-header h1 {
    color: #ff5f9e;
    font-weight: 700;
    font-size: 2.5rem;
    margin-bottom: 10px;
    position: relative;
    display: inline-block;
}

.page-header h1:after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 0;
    width: 80px;
    height: 4px;
    background: var(--pink-gradient);
    border-radius: 2px;
}

.page-header p {
    color: var(--text-light);
    font-size: 1.1rem;
    margin-top: 20px;
}

.welcome-text {
    background: linear-gradient(135deg, #fff0f5, #fff);
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    border-left: 5px solid #ff9a9e;
    margin-bottom: 30px;
}

.welcome-text h2 {
    color: #ff5f9e;
    font-weight: 600;
    margin-bottom: 10px;
}

.welcome-text p {
    color: #666;
    margin-bottom: 5px;
}

/* ===================== */
/* STATISTIC CARDS */
/* ===================== */
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: var(--card-shadow);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 100%;
    border: none;
    position: relative;
    overflow: hidden;
}

.stat-card:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: var(--pink-gradient);
}

.stat-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(255, 154, 158, 0.2);
}

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    font-size: 30px;
    color: white;
}

.stat-icon-1 { background: linear-gradient(135deg, #4CAF50, #45a049); }
.stat-icon-2 { background: linear-gradient(135deg, #2196F3, #1976D2); }
.stat-icon-3 { background: linear-gradient(135deg, #FF9800, #FF5722); }

.stat-value {
    font-size: 2.2rem;
    font-weight: 800;
    margin: 10px 0;
    color: var(--text-dark);
}

.stat-label {
    color: var(--text-light);
    font-size: 1rem;
    font-weight: 500;
    margin-bottom: 5px;
}

.stat-subtext {
    font-size: 0.9rem;
    color: #999;
    margin-top: 10px;
}

.status-badge {
    display: inline-block;
    padding: 6px 15px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    margin-top: 10px;
}

.status-lunas {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
}

.status-belum {
    background: linear-gradient(135deg, #FF9800, #FF5722);
    color: white;
}

/* ===================== */
/* ABSENSI BUTTON */
/* ===================== */
.absen-section {
    margin: 50px 0;
    text-align: center;
}

.absen-button {
    background: var(--orange-gradient);
    color: white;
    border: none;
    padding: 25px 50px;
    font-size: 1.5rem;
    font-weight: 700;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(255, 95, 158, 0.3);
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    overflow: hidden;
}

.absen-button:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: 0.5s;
}

.absen-button:hover:before {
    left: 100%;
}

.absen-button:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 20px 40px rgba(255, 95, 158, 0.4);
    color: white;
}

.absen-button:active {
    transform: translateY(-2px) scale(1.02);
}

.absen-info {
    margin-top: 20px;
    color: var(--text-light);
    font-size: 0.95rem;
}

/* ===================== */
/* RIWAYAT TABLE */
/* ===================== */
.riwayat-card {
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
    margin-top: 30px;
}

.riwayat-header {
    background: var(--pink-gradient);
    color: white;
    padding: 20px 25px;
    border-bottom: none;
}

.riwayat-header h3 {
    margin: 0;
    font-weight: 600;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.riwayat-table {
    margin: 0;
    width: 100%;
    border-collapse: collapse;
}

.riwayat-table th {
    background: #f8f9fa;
    color: #666;
    font-weight: 600;
    padding: 15px 20px;
    text-align: left;
    border-bottom: 2px solid #eee;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.riwayat-table td {
    padding: 15px 20px;
    border-bottom: 1px solid #f5f5f5;
    vertical-align: middle;
}

.riwayat-table tr:last-child td {
    border-bottom: none;
}

.riwayat-table tr:hover {
    background-color: #f9f9f9;
}

.riwayat-table .badge-status {
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-masuk {
    background: rgba(76, 175, 80, 0.15);
    color: #2E7D32;
    border: 1px solid rgba(76, 175, 80, 0.3);
}

.badge-terlambat {
    background: rgba(255, 152, 0, 0.15);
    color: #EF6C00;
    border: 1px solid rgba(255, 152, 0, 0.3);
}

/* ===================== */
/* RESPONSIVE */
/* ===================== */
@media (max-width: 768px) {
    .main-content {
        padding: 20px;
        margin-left: 0;
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .absen-button {
        padding: 20px 30px;
        font-size: 1.2rem;
    }
    
    .stat-value {
        font-size: 1.8rem;
    }
}

@media (max-width: 576px) {
    .welcome-text {
        padding: 20px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .riwayat-table {
        font-size: 0.9rem;
    }
    
    .riwayat-table th,
    .riwayat-table td {
        padding: 10px 15px;
    }
}
</style>
</head>

<body>

<!-- INCLUDE SIDEBAR -->
<?php include '../includes/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="main-content">

<!-- HEADER DAN SAMBUTAN -->
<div class="page-header">
    <h1><i class="fas fa-gauge-high"></i> Dashboard Karyawan</h1>
    <p>Selamat datang di sistem Dapur Melly - Kelola aktivitas kerja Anda</p>
</div>

<!-- WELCOME MESSAGE -->
<div class="welcome-text">
    <h2><i class="fas fa-hand-wave"></i> Selamat Datang, <?= htmlspecialchars($nama_user) ?>!</h2>
    <p><i class="fas fa-user-tag"></i> <strong>Posisi:</strong> <?= htmlspecialchars($posisi) ?></p>
    <p><i class="fas fa-calendar-day"></i> <strong>Tanggal:</strong> <?= date('d F Y') ?></p>
    <p><i class="fas fa-clock"></i> <strong>Waktu:</strong> <span id="currentTime"><?= date('H:i:s') ?></span></p>
</div>

<!-- STATISTIK CARDS -->
<div class="row g-4 mb-5">
    <!-- CARD 1: KEHADIRAN -->
    <div class="col-lg-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon stat-icon-1">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-label">Kehadiran Bulan Ini</div>
            <div class="stat-value"><?= $total_kehadiran ?> Hari</div>
            <div class="stat-subtext">
                <?php if ($total_kehadiran > 0): ?>
                    <i class="fas fa-check-circle text-success"></i> Terhitung dari <?= date('F Y') ?>
                <?php else: ?>
                    <i class="fas fa-info-circle text-warning"></i> Belum ada kehadiran bulan ini
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- CARD 2: STATUS GAJI -->
    <div class="col-lg-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon stat-icon-2">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-label">Status Gaji <?= date('F') ?></div>
            <div class="stat-value">
                <?php if ($status_gaji == 'lunas'): ?>
                    <?= $gaji_bersih_formatted ?>
                <?php else: ?>
                    <?= formatCurrency($gaji_pokok) ?>
                <?php endif; ?>
            </div>
            <div class="status-badge <?= $status_gaji == 'lunas' ? 'status-lunas' : 'status-belum' ?>">
                <?php if ($status_gaji == 'lunas'): ?>
                    <i class="fas fa-check-circle"></i> LUNAS
                <?php else: ?>
                    <i class="fas fa-clock"></i> BELUM
                <?php endif; ?>
            </div>
            <div class="stat-subtext">
                <?= $status_gaji == 'lunas' ? 'Sudah dibayar' : 'Menunggu pembayaran' ?>
            </div>
        </div>
    </div>
    
    <!-- CARD 3: ESTIMASI GAJI -->
    <div class="col-lg-4 col-md-12">
        <div class="stat-card">
            <div class="stat-icon stat-icon-3">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-label">Estimasi Gaji <?= date('F') ?></div>
            <div class="stat-value"><?= $estimasi_gaji_formatted ?></div>
            <div class="stat-subtext">
                <small>
                    <i class="fas fa-money-bill-wave"></i> Pokok: <?= $gaji_pokok_formatted ?><br>
                    <i class="fas fa-utensils"></i> Makan: +<?= formatCurrency($uang_makan) ?><br>
                    <i class="fas fa-clock"></i> Potongan: -<?= formatCurrency($potongan_terlambat) ?>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- TOMBOL ABSEN UTAMA -->
<div class="absen-section">
    <a href="absen.php" class="absen-button">
        <i class="fas fa-fingerprint"></i> KLIK UNTUK ABSEN SEKARANG
    </a>
    <div class="absen-info">
        <i class="fas fa-info-circle"></i> Pastikan Anda sudah berada di lokasi kerja sebelum melakukan absen
    </div>
</div>

<!-- RIWAYAT ABSENSI -->
<div class="riwayat-card">
    <div class="riwayat-header">
        <h3><i class="fas fa-history"></i> Riwayat Absensi Terakhir</h3>
    </div>
    <div class="card-body">
        <?php if (empty($riwayat_absen)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Belum ada riwayat absensi</h5>
                <p class="text-muted">Mulai lakukan absen untuk melihat riwayat di sini</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="riwayat-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat_absen as $absen): 
                            $is_today = $absen['tanggal'] == $today;
                            $is_late = !empty($absen['keterangan_lembur']) && 
                                      (strpos(strtolower($absen['keterangan_lembur']), 'terlambat') !== false);
                        ?>
                        <tr <?= $is_today ? 'style="background-color: #fff0f5;"' : '' ?>>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($absen['tanggal'])) ?></strong>
                                <?php if ($is_today): ?>
                                    <span class="badge bg-danger ms-2">Hari Ini</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status <?= $is_late ? 'badge-terlambat' : 'badge-masuk' ?>">
                                    <i class="fas <?= $is_late ? 'fa-exclamation-triangle' : 'fa-check' ?>"></i>
                                    <?= ucfirst($absen['status']) ?>
                                    <?= $is_late ? ' (Terlambat)' : '' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($absen['keterangan_lembur'])): ?>
                                    <span class="text-warning">
                                        <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($absen['keterangan_lembur']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($absen['jam_absensi']) && $absen['jam_absensi'] != '00:00:00'): ?>
                                    <span class="text-dark fw-bold">
                                        <i class="fas fa-clock"></i> <?= date('H:i', strtotime($absen['jam_absensi'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-3">
                <a href="absen.php?tab=riwayat" class="btn btn-sm" style="background: var(--pink-gradient); color: white;">
                    <i class="fas fa-list"></i> Lihat Semua Riwayat
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- INFORMASI TAMBAHAN -->
<div class="row mt-4">
    <div class="col-md-6 mb-4">
        <div class="stat-card">
            <div class="d-flex align-items-center mb-3">
                <div class="stat-icon stat-icon-1" style="width: 50px; height: 50px; font-size: 20px;">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="ms-3">
                    <h5 class="mb-1">Informasi Karyawan</h5>
                    <p class="text-muted mb-0">Data personal dan pekerjaan</p>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <small class="text-muted d-block">ID Karyawan</small>
                    <strong>#<?= $id_karyawan ?></strong>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Posisi</small>
                    <strong><?= htmlspecialchars($posisi) ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="stat-card">
            <div class="d-flex align-items-center mb-3">
                <div class="stat-icon stat-icon-2" style="width: 50px; height: 50px; font-size: 20px;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="ms-3">
                    <h5 class="mb-1">Periode Kerja</h5>
                    <p class="text-muted mb-0">Informasi bulan berjalan</p>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <small class="text-muted d-block">Bulan</small>
                    <strong><?= date('F Y') ?></strong>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Hari Kerja</small>
                    <strong><?= date('t') ?> hari</strong>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Update waktu real-time
function updateCurrentTime() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const timeElement = document.getElementById('currentTime');
    
    if (timeElement) {
        timeElement.textContent = `${hours}:${minutes}:${seconds}`;
        
        // Efek berkedip pada detik
        if (parseInt(seconds) % 2 === 0) {
            timeElement.style.color = '#ff5f9e';
        } else {
            timeElement.style.color = '#333';
        }
    }
}

// Update waktu setiap detik
setInterval(updateCurrentTime, 1000);
updateCurrentTime();

// Auto-refresh dashboard setiap 60 detik
setTimeout(() => {
    // Bukan reload penuh, hanya tampilkan notifikasi
    const notificationDiv = document.createElement('div');
    notificationDiv.innerHTML = `
        <div style="position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 15px 25px; border-radius: 10px; z-index: 10000; box-shadow: 0 5px 20px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; animation: slideIn 0.5s ease;">
            <i class="fas fa-sync-alt" style="font-size: 20px;"></i>
            <div>
                <strong>Dashboard diperbarui</strong>
                <div style="font-size: 13px; opacity: 0.9;">Data terbaru telah dimuat</div>
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
}, 60000); // Setiap 60 detik

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

// Konfirmasi sebelum absen
document.querySelector('.absen-button').addEventListener('click', function(e) {
    const now = new Date();
    const hours = now.getHours();
    
    // Jika di luar jam kerja (contoh: 17:00 - 07:00), tampilkan konfirmasi
    if (hours >= 17 || hours < 7) {
        e.preventDefault();
        Swal.fire({
            title: 'Absen di Luar Jam Kerja?',
            html: `Saat ini pukul ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}<br>
                   <strong>Apakah Anda yakin ingin absen?</strong>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff9a9e',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check"></i> Ya, Lanjutkan',
            cancelButtonText: '<i class="fas fa-times"></i> Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'absen.php';
            }
        });
    }
});
</script>
</body>
</html>