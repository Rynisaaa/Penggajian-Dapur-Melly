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
if (!$query_karyawan || mysqli_num_rows($query_karyawan) == 0) {
    die("<div style='padding: 20px; text-align: center;'><h3>Data karyawan tidak ditemukan</h3><p>Hubungi admin untuk melengkapi data karyawan Anda.</p></div>");
}
$data_karyawan = mysqli_fetch_assoc($query_karyawan);

$id_karyawan = $data_karyawan['id_karyawan'] ?? 0;
$posisi = $data_karyawan['posisi'] ?? 'Belum ditentukan';

/**
 * TENTUKAN JAM MASUK BERDASARKAN POSISI
 */
$jam_batas_masuk = '08:00'; // Default
if (strtolower($posisi) === 'baker') {
    $jam_batas_masuk = '07:00';
} elseif (strtolower($posisi) === 'dekor') {
    $jam_batas_masuk = '08:00';
}

/**
 * PROSES ABSEN MASUK
 */
$success_message = '';
$error_message = '';
$sudah_absen_hari_ini = false;
$jam_absen_hari_ini = '';

// Cek apakah sudah absen hari ini
$today = date('Y-m-d');
$query_absen_hari_ini = mysqli_query($conn, "
    SELECT * FROM presensi 
    WHERE id_karyawan = '$id_karyawan' 
    AND tanggal = '$today' 
    AND status = 'masuk'
    LIMIT 1
");

if ($query_absen_hari_ini && mysqli_num_rows($query_absen_hari_ini) > 0) {
    $sudah_absen_hari_ini = true;
    $data_absen = mysqli_fetch_assoc($query_absen_hari_ini);
    if (!empty($data_absen['jam_masuk']) && $data_absen['jam_masuk'] != '0000-00-00 00:00:00') {
        $jam_absen_hari_ini = date('H:i', strtotime($data_absen['jam_masuk']));
    }
}

// Proses absen jika form dikirim
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['absen_masuk']) && !$sudah_absen_hari_ini) {
    $waktu_sekarang = date('Y-m-d H:i:s');
    $jam_sekarang = date('H:i');
    
    // Hitung keterlambatan
    $keterlambatan = '';
    $status_tepat_waktu = true;
    
    $jam_batas = strtotime($jam_batas_masuk);
    $jam_aktual = strtotime($jam_sekarang);
    
    if ($jam_aktual > $jam_batas) {
        $status_tepat_waktu = false;
        $selisih_detik = $jam_aktual - $jam_batas;
        $jam_telat = floor($selisih_detik / 3600);
        $menit_telat = floor(($selisih_detik % 3600) / 60);
        
        if ($jam_telat > 0) {
            $keterlambatan = "Terlambat $jam_telat jam";
            if ($menit_telat > 0) {
                $keterlambatan .= " $menit_telat menit";
            }
        } else {
            $keterlambatan = "Terlambat $menit_telat menit";
        }
    } else {
        $keterlambatan = 'Tepat Waktu';
    }
    
    // Simpan ke database
    $tanggal = date('Y-m-d');
    $status = 'masuk';
    
    $query_insert = mysqli_query($conn, "
        INSERT INTO presensi (id_karyawan, tanggal, status, jam_masuk, keterangan_lembur) 
        VALUES ('$id_karyawan', '$tanggal', '$status', '$waktu_sekarang', '$keterlambatan')
    ");
    
    if ($query_insert) {
        $success_message = "Absen berhasil! Jam masuk Anda: $jam_sekarang. Status: $keterlambatan";
        
        // Update status sudah absen
        $sudah_absen_hari_ini = true;
        $jam_absen_hari_ini = $jam_sekarang;
        
        // Catat login history
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $device_info = "Absen Masuk via Dashboard - Posisi: $posisi";
        mysqli_query($conn, "
            INSERT INTO login_history (user_id, ip_address, device_info) 
            VALUES ('$user_id', '$ip_address', '$device_info')
        ");
    } else {
        $error_message = "Gagal melakukan absen. Error: " . mysqli_error($conn);
    }
}

/**
 * AMBIL RIWAYAT ABSEN 7 HARI TERAKHIR
 */
$riwayat_absen = [];
$query_riwayat = mysqli_query($conn, "
    SELECT tanggal, jam_masuk, status, keterangan_lembur
    FROM presensi 
    WHERE id_karyawan = '$id_karyawan'
    AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY tanggal DESC, jam_masuk DESC
");

if ($query_riwayat) {
    while ($row = mysqli_fetch_assoc($query_riwayat)) {
        $riwayat_absen[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Absen Karyawan - Dapur Melly</title>

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
    --pink-dark: #ff5f9e;
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
    margin-bottom: 30px;
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

/* ===================== */
/* INFO KARYAWAN CARD */
/* ===================== */
.info-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: var(--card-shadow);
    margin-bottom: 30px;
    border-left: 5px solid #ff9a9e;
}

.info-card h5 {
    color: #ff5f9e;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.info-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #eee;
}

.info-label {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-dark);
}

/* ===================== */
/* JAM DIGITAL */
/* ===================== */
.jam-digital-container {
    text-align: center;
    margin: 30px 0;
    padding: 30px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 20px;
    color: white;
    box-shadow: 0 15px 35px rgba(102, 126, 234, 0.2);
}

.jam-label {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 10px;
}

.jam-digital {
    font-size: 4rem;
    font-weight: 900;
    font-family: 'Courier New', monospace;
    text-shadow: 0 4px 8px rgba(0,0,0,0.3);
    letter-spacing: 2px;
}

.tanggal-digital {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-top: 10px;
}

/* ===================== */
/* TOMBOL ABSEN */
/* ===================== */
.absen-section {
    text-align: center;
    margin: 40px 0;
}

.absen-button {
    background: var(--orange-gradient);
    color: white;
    border: none;
    padding: 25px 50px;
    font-size: 1.8rem;
    font-weight: 800;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(255, 95, 158, 0.3);
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
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
    transform: translateY(-8px) scale(1.03);
    box-shadow: 0 25px 50px rgba(255, 95, 158, 0.4);
    color: white;
}

.absen-button:active {
    transform: translateY(-4px) scale(1.01);
}

.absen-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none !important;
}

.absen-info {
    margin-top: 20px;
    color: var(--text-light);
    font-size: 1rem;
    max-width: 600px;
    margin: 20px auto 0;
    padding: 15px;
    background: rgba(255, 154, 158, 0.1);
    border-radius: 12px;
}

/* ===================== */
/* ALERT SUDAH ABSEN */
/* ===================== */
.alert-sudah-absen {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    padding: 25px;
    border-radius: 20px;
    text-align: center;
    box-shadow: var(--card-shadow);
    margin: 30px auto;
    max-width: 600px;
}

.alert-sudah-absen h4 {
    margin-bottom: 15px;
    font-weight: 700;
}

/* ===================== */
/* RIWAYAT ABSEN */
/* ===================== */
.riwayat-card {
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
    margin-top: 40px;
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

.badge-tepat-waktu {
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
/* ALERTS */
/* ===================== */
.alert-custom {
    border-radius: 15px;
    border: none;
    padding: 15px 20px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.alert-custom i {
    font-size: 1.5rem;
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
    
    .jam-digital {
        font-size: 3rem;
    }
    
    .absen-button {
        padding: 20px 30px;
        font-size: 1.4rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .jam-digital {
        font-size: 2.5rem;
    }
    
    .absen-button {
        padding: 18px 25px;
        font-size: 1.2rem;
        gap: 10px;
    }
    
    .riwayat-table th,
    .riwayat-table td {
        padding: 10px 15px;
        font-size: 0.85rem;
    }
}

/* Animation for clock */
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.pulse {
    animation: pulse 1s infinite;
}
</style>
</head>

<body>

<!-- INCLUDE SIDEBAR -->
<?php include '../includes/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="main-content">

<!-- HEADER -->
<div class="page-header">
    <h1><i class="fas fa-fingerprint"></i> Absen Karyawan</h1>
    <p>Lakukan absen masuk untuk mencatat kehadiran Anda</p>
</div>

<!-- INFO KARYAWAN -->
<div class="info-card">
    <h5><i class="fas fa-user-circle"></i> Informasi Karyawan</h5>
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label"><i class="fas fa-user"></i> Nama</div>
            <div class="info-value"><?= htmlspecialchars($nama_user) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-id-card"></i> ID Karyawan</div>
            <div class="info-value">#<?= $id_karyawan ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-briefcase"></i> Posisi</div>
            <div class="info-value"><?= htmlspecialchars($posisi) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-clock"></i> Batas Jam Masuk</div>
            <div class="info-value"><?= $jam_batas_masuk ?></div>
        </div>
    </div>
</div>

<!-- JAM DIGITAL -->
<div class="jam-digital-container">
    <div class="jam-label">Waktu Sekarang</div>
    <div class="jam-digital" id="jamDigital"><?= date('H:i:s') ?></div>
    <div class="tanggal-digital" id="tanggalDigital"><?= date('d F Y') ?></div>
</div>

<!-- ALERT SUCCESS/ERROR -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i>
    <div>
        <strong>Berhasil!</strong> <?= $success_message ?>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>Error!</strong> <?= $error_message ?>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- ABSEN SECTION -->
<div class="absen-section">
    <?php if ($sudah_absen_hari_ini): ?>
        <div class="alert-sudah-absen">
            <h4><i class="fas fa-check-circle"></i> Anda Sudah Absen Hari Ini</h4>
            <p style="font-size: 1.2rem;">Jam absen masuk: <strong><?= $jam_absen_hari_ini ?></strong></p>
            <p style="margin-top: 10px; opacity: 0.9;">Absen selanjutnya dapat dilakukan besok.</p>
        </div>
    <?php else: ?>
        <form method="POST" id="formAbsen">
            <button type="submit" name="absen_masuk" class="absen-button" id="btnAbsen">
                <i class="fas fa-fingerprint"></i>
                <span>ABSEN MASUK SEKARANG</span>
            </button>
            <div class="absen-info">
                <i class="fas fa-info-circle"></i> Pastikan Anda sudah berada di lokasi kerja sebelum melakukan absen.
                Batas jam masuk untuk posisi <?= htmlspecialchars($posisi) ?> adalah pukul <?= $jam_batas_masuk ?>.
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- RIWAYAT ABSEN 7 HARI TERAKHIR -->
<div class="riwayat-card">
    <div class="riwayat-header">
        <h3><i class="fas fa-history"></i> Riwayat Absen 7 Hari Terakhir</h3>
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
                            <th>Jam Masuk</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat_absen as $absen): 
                            $is_today = $absen['tanggal'] == $today;
                            $jam_masuk = !empty($absen['jam_masuk']) && $absen['jam_masuk'] != '0000-00-00 00:00:00' 
                                ? date('H:i', strtotime($absen['jam_masuk'])) 
                                : '-';
                            $is_terlambat = !empty($absen['keterangan_lembur']) && 
                                           strpos(strtolower($absen['keterangan_lembur']), 'terlambat') !== false;
                        ?>
                        <tr <?= $is_today ? 'style="background-color: #fff0f5;"' : '' ?>>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($absen['tanggal'])) ?></strong>
                                <?php if ($is_today): ?>
                                    <span class="badge bg-danger ms-2">Hari Ini</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($jam_masuk != '-'): ?>
                                    <span class="text-dark fw-bold">
                                        <i class="fas fa-clock"></i> <?= $jam_masuk ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status <?= $is_terlambat ? 'badge-terlambat' : 'badge-tepat-waktu' ?>">
                                    <i class="fas <?= $is_terlambat ? 'fa-exclamation-triangle' : 'fa-check' ?>"></i>
                                    <?= $is_terlambat ? 'Terlambat' : 'Tepat Waktu' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($absen['keterangan_lembur'])): ?>
                                    <span class="<?= $is_terlambat ? 'text-warning fw-bold' : 'text-success' ?>">
                                        <i class="fas <?= $is_terlambat ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i> 
                                        <?= htmlspecialchars($absen['keterangan_lembur']) ?>
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
        <?php endif; ?>
    </div>
</div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Update jam digital real-time
function updateJamDigital() {
    const now = new Date();
    const jam = now.getHours().toString().padStart(2, '0');
    const menit = now.getMinutes().toString().padStart(2, '0');
    const detik = now.getSeconds().toString().padStart(2, '0');
    
    const jamElement = document.getElementById('jamDigital');
    const tanggalElement = document.getElementById('tanggalDigital');
    
    if (jamElement) {
        jamElement.textContent = `${jam}:${menit}:${detik}`;
        
        // Efek berkedip pada detik
        if (parseInt(detik) % 2 === 0) {
            jamElement.classList.add('pulse');
        } else {
            jamElement.classList.remove('pulse');
        }
    }
    
    if (tanggalElement) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        tanggalElement.textContent = now.toLocaleDateString('id-ID', options);
    }
}

// Update jam setiap detik
setInterval(updateJamDigital, 1000);
updateJamDigital();

// Konfirmasi sebelum absen
document.getElementById('formAbsen')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const now = new Date();
    const jam = now.getHours().toString().padStart(2, '0');
    const menit = now.getMinutes().toString().padStart(2, '0');
    const waktuSekarang = `${jam}:${menit}`;
    
    const posisi = "<?= addslashes($posisi) ?>";
    const jamBatas = "<?= $jam_batas_masuk ?>";
    
    // Cek jika di luar jam kerja (misal: malam hari)
    const jamSekarang = now.getHours();
    if (jamSekarang < 4 || jamSekarang >= 22) {
        Swal.fire({
            title: 'Absen di Luar Jam Kerja?',
            html: `Saat ini pukul <strong>${waktuSekarang}</strong><br>
                   Jam kerja normal adalah 07:00-17:00<br>
                   <strong>Apakah Anda yakin ingin absen sekarang?</strong>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff5f9e',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check"></i> Ya, Absen Sekarang',
            cancelButtonText: '<i class="fas fa-times"></i> Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                e.target.submit();
            }
        });
    } else {
        // Untuk jam normal, langsung submit
        e.target.submit();
    }
});

// Auto-hide alert setelah 5 detik
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        setTimeout(() => bsAlert.close(), 5000);
    });
}, 5000);

// Animasi tombol absen
const btnAbsen = document.getElementById('btnAbsen');
if (btnAbsen) {
    btnAbsen.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px) scale(1.03)';
    });
    
    btnAbsen.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
}

// Tampilkan notifikasi jika ada pesan sukses
<?php if ($success_message): ?>
Swal.fire({
    title: 'Absen Berhasil!',
    html: '<?= addslashes($success_message) ?>',
    icon: 'success',
    confirmButtonColor: '#ff5f9e',
    confirmButtonText: 'OK'
});
<?php endif; ?>
</script>
</body>
</html>