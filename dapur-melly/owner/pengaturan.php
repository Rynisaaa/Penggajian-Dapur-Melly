<?php
session_start();

/**
 * VALIDASI SESSION OWNER
 */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
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
 * TENTUKAN OWNER_ID YANG BENAR
 */
if (!isset($_SESSION['id'])) {
    // Ambil owner dari database
    $owner_query = mysqli_query($conn, "SELECT * FROM users WHERE role = 'owner' LIMIT 1");
    if ($owner_query && mysqli_num_rows($owner_query) > 0) {
        $owner_data = mysqli_fetch_assoc($owner_query);
        $_SESSION['id'] = $owner_data['id'];
        $_SESSION['nama_lengkap'] = $owner_data['nama_lengkap'];
    } else {
        header("Location: ../login.php");
        exit;
    }
}

$owner_id = $_SESSION['id'];
$nama_owner = $_SESSION['nama_lengkap'] ?? 'Owner';

/**
 * AMBIL DATA OWNER DENGAN PREPARED STATEMENT
 */
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $owner_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data_owner = mysqli_fetch_assoc($result);

if (!$data_owner) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

// Update nama owner dari database jika perlu
if (empty($nama_owner) || $nama_owner == 'Owner') {
    $nama_owner = $data_owner['nama_lengkap'] ?? 'Owner';
    $_SESSION['nama_lengkap'] = $nama_owner;
}

/**
 * INISIALISASI VARIABEL
 */
$success_message = '';
$error_message = '';
$foto_profil = $data_owner['foto_profil'] ?? 'default.png';
$email = $data_owner['email'] ?? '';
$username = $data_owner['username'] ?? '';

/**
 * AMBIL PARAMETER SISTEM
 */
$system_params = [];
$query_params = mysqli_query($conn, "SELECT * FROM system_parameters ORDER BY param_label");
if ($query_params) {
    while ($row = mysqli_fetch_assoc($query_params)) {
        $system_params[$row['param_key']] = $row;
    }
}

/**
 * AMBIL TARGET PENDAPATAN
 */
$current_year = date('Y');
$target_pendapatan = [];
$query_target = mysqli_query($conn, "
    SELECT * FROM target_pendapatan 
    WHERE tahun = '$current_year' 
    ORDER BY bulan ASC
");

if ($query_target) {
    while ($row = mysqli_fetch_assoc($query_target)) {
        $target_pendapatan[] = $row;
    }
}

/**
 * AMBIL SEMUA RIWAYAT LOGIN (UNTUK MONITORING)
 */
$all_login_history = [];
$query_all_history = mysqli_query($conn, "
    SELECT lh.*, u.nama_lengkap, u.role 
    FROM login_history lh
    JOIN users u ON lh.user_id = u.id
    ORDER BY lh.login_time DESC 
    LIMIT 20
");

if ($query_all_history) {
    while ($row = mysqli_fetch_assoc($query_all_history)) {
        $all_login_history[] = $row;
    }
}

/**
 * AMBIL PESAN DARI ADMIN
 */
$admin_messages = [];
$query_messages = mysqli_query($conn, "
    SELECT * FROM pesan_sistem 
    WHERE tujuan_role = 'owner' OR sender_name LIKE '%Admin%'
    ORDER BY created_at DESC 
    LIMIT 10
");

if ($query_messages) {
    while ($row = mysqli_fetch_assoc($query_messages)) {
        $admin_messages[] = $row;
    }
}

/**
 * AMBIL DATA UNTUK DOWNLOAD/EXPORT
 */
// Data users
$users_data = [];
$query_users = mysqli_query($conn, "SELECT id, username, nama_lengkap, role, email, last_login, created_at FROM users");
if ($query_users) {
    while ($row = mysqli_fetch_assoc($query_users)) {
        $users_data[] = $row;
    }
}

// Data karyawan
$karyawan_data = [];
$query_karyawan = mysqli_query($conn, "
    SELECT k.*, u.nama_lengkap, u.username 
    FROM karyawan k 
    LEFT JOIN users u ON k.user_id = u.id
");
if ($query_karyawan) {
    while ($row = mysqli_fetch_assoc($query_karyawan)) {
        $karyawan_data[] = $row;
    }
}

// Data presensi bulan ini
$current_month = date('m');
$presensi_data = [];
$query_presensi = mysqli_query($conn, "
    SELECT p.*, k.id_karyawan, u.nama_lengkap
    FROM presensi p
    JOIN karyawan k ON p.id_karyawan = k.id_karyawan
    JOIN users u ON k.user_id = u.id
    WHERE MONTH(p.tanggal) = '$current_month'
    ORDER BY p.tanggal DESC
");
if ($query_presensi) {
    while ($row = mysqli_fetch_assoc($query_presensi)) {
        $presensi_data[] = $row;
    }
}

/**
 * PROSES DOWNLOAD DATA
 */
if (isset($_GET['download']) && $_GET['download'] == 'all_data') {
    // Set headers for file download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="dapur_melly_data_' . date('Y-m-d') . '.xls"');
    
    // Start HTML output for Excel
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    echo 'th { background-color: #ff5f9e; color: white; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h1>Data Dapur Melly - ' . date('d F Y') . '</h1>';
    
    // Users Data
    echo '<h2>Data Users</h2>';
    echo '<table>';
    echo '<tr><th>ID</th><th>Username</th><th>Nama Lengkap</th><th>Role</th><th>Email</th><th>Login Terakhir</th><th>Tanggal Daftar</th></tr>';
    foreach ($users_data as $user) {
        echo '<tr>';
        echo '<td>' . $user['id'] . '</td>';
        echo '<td>' . $user['username'] . '</td>';
        echo '<td>' . $user['nama_lengkap'] . '</td>';
        echo '<td>' . $user['role'] . '</td>';
        echo '<td>' . $user['email'] . '</td>';
        echo '<td>' . ($user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-') . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($user['created_at'])) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // Karyawan Data
    echo '<h2>Data Karyawan</h2>';
    echo '<table>';
    echo '<tr><th>ID Karyawan</th><th>Nama</th><th>Username</th><th>Posisi</th><th>Gaji Pokok</th><th>No. Telp</th><th>Tanggal Masuk</th></tr>';
    foreach ($karyawan_data as $karyawan) {
        echo '<tr>';
        echo '<td>' . $karyawan['id_karyawan'] . '</td>';
        echo '<td>' . $karyawan['nama_lengkap'] . '</td>';
        echo '<td>' . $karyawan['username'] . '</td>';
        echo '<td>' . $karyawan['posisi'] . '</td>';
        echo '<td>Rp ' . number_format($karyawan['gaji_pokok'], 0, ',', '.') . '</td>';
        echo '<td>' . $karyawan['no_telp'] . '</td>';
        echo '<td>' . ($karyawan['tgl_masuk'] ? date('d/m/Y', strtotime($karyawan['tgl_masuk'])) : '-') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // Presensi Data
    echo '<h2>Data Presensi Bulan ' . date('F Y') . '</h2>';
    echo '<table>';
    echo '<tr><th>ID Karyawan</th><th>Nama</th><th>Tanggal</th><th>Status</th><th>Jam Masuk</th><th>Keterangan</th></tr>';
    foreach ($presensi_data as $presensi) {
        echo '<tr>';
        echo '<td>' . $presensi['id_karyawan'] . '</td>';
        echo '<td>' . $presensi['nama_lengkap'] . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($presensi['tanggal'])) . '</td>';
        echo '<td>' . $presensi['status'] . '</td>';
        echo '<td>' . ($presensi['jam_masuk'] ? date('H:i', strtotime($presensi['jam_masuk'])) : '-') . '</td>';
        echo '<td>' . $presensi['keterangan_lembur'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    echo '</body></html>';
    exit;
}

/**
 * PROSES GANTI PASSWORD (Satu-satunya edit yang bisa dilakukan Owner)
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    // Verifikasi password lama
    $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $owner_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($result);
    
    $password_match = false;
    if (isset($user_data['password'])) {
        if (password_verify($password_lama, $user_data['password'])) {
            $password_match = true;
        } elseif ($password_lama == $user_data['password']) {
            $password_match = true;
        }
    }
    
    if ($password_match) {
        if (strlen($password_baru) < 8) {
            $error_message = "Password baru minimal 8 karakter.";
        } elseif ($password_baru != $konfirmasi_password) {
            $error_message = "Konfirmasi password tidak cocok.";
        } else {
            $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $password_hash, $owner_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Password berhasil diubah!";
            } else {
                $error_message = "Gagal mengubah password: " . mysqli_error($conn);
            }
        }
    } else {
        $error_message = "Password lama salah.";
    }
}

// Include sidebar
include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Owner - Dapur Melly</title>
    
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
        --pink-primary: #ff5f9e;
        --pink-light: #ff9a9e;
        --pink-soft: #ffeff3;
        --pink-gradient: linear-gradient(135deg, var(--pink-primary), var(--pink-light));
        --white: #ffffff;
        --light-bg: #f9f7fe;
        --light-card: #ffffff;
        --border-color: #e9ecef;
        --text-dark: #333333;
        --text-muted: #666666;
        --shadow: 0 4px 20px rgba(255, 95, 158, 0.1);
        --card-shadow: 0 10px 30px rgba(255, 154, 158, 0.15);
    }
    
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: var(--light-bg);
        background-image: linear-gradient(120deg, #fdfbfb 0%, #ebedee 100%);
        color: var(--text-dark);
        min-height: 100vh;
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
        color: var(--pink-primary);
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
        color: var(--text-muted);
        font-size: 1.1rem;
        margin-top: 20px;
    }
    
    .owner-badge {
        display: inline-block;
        background: var(--pink-gradient);
        color: white;
        padding: 8px 20px;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 600;
        margin-left: 15px;
        box-shadow: 0 4px 10px rgba(255, 95, 158, 0.2);
    }
    
    /* ===================== */
    /* TABS */
    /* ===================== */
    .nav-tabs {
        border-bottom: 2px solid var(--pink-soft);
        margin-bottom: 30px;
    }
    
    .nav-tabs .nav-link {
        background: transparent;
        border: none;
        color: var(--text-muted);
        font-weight: 600;
        padding: 15px 25px;
        border-radius: 10px 10px 0 0;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .nav-tabs .nav-link:hover {
        color: var(--pink-primary);
        background: var(--pink-soft);
    }
    
    .nav-tabs .nav-link.active {
        color: var(--pink-primary);
        background: var(--white);
        border-bottom: 3px solid var(--pink-primary);
        box-shadow: 0 -2px 10px rgba(255, 95, 158, 0.1);
    }
    
    .tab-content {
        padding: 30px 0;
    }
    
    /* ===================== */
    /* CARDS */
    /* ===================== */
    .card-owner {
        background: var(--white);
        border: none;
        border-radius: 15px;
        box-shadow: var(--card-shadow);
        margin-bottom: 25px;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .card-owner:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(255, 154, 158, 0.2);
    }
    
    .card-header-owner {
        background: var(--pink-gradient);
        border-bottom: none;
        padding: 20px;
        color: white;
        border-radius: 15px 15px 0 0 !important;
    }
    
    .card-header-owner h5 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        color: white;
    }
    
    .card-body {
        padding: 25px;
    }
    
    /* ===================== */
    /* FORMS (READ-ONLY) */
    /* ===================== */
    .form-label {
        color: var(--text-dark);
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .form-control, .form-select {
        border: 2px solid var(--border-color);
        color: var(--text-dark);
        padding: 12px 15px;
        border-radius: 10px;
        transition: all 0.3s ease;
        background-color: #f8f9fa;
    }
    
    .form-control:read-only, .form-select:read-only {
        background-color: #f8f9fa;
        cursor: not-allowed;
    }
    
    .form-text {
        color: var(--text-muted);
        font-size: 0.9rem;
    }
    
    .input-group-text {
        background-color: #f8f9fa;
        border: 2px solid var(--border-color);
        color: var(--text-muted);
    }
    
    /* ===================== */
    /* BUTTONS */
    /* ===================== */
    .btn-owner {
        background: var(--pink-gradient);
        border: none;
        color: white;
        font-weight: 600;
        padding: 12px 25px;
        border-radius: 10px;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-owner:hover {
        background: linear-gradient(135deg, #ff8a9e 0%, #fec0ef 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(255, 154, 158, 0.3);
    }
    
    .btn-download {
        background: linear-gradient(135deg, #28a745, #20c997);
        border: none;
        color: white;
        font-weight: 600;
        padding: 12px 25px;
        border-radius: 10px;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-download:hover {
        background: linear-gradient(135deg, #218838, #1e9e8a);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
    }
    
    .btn-outline-owner {
        background: transparent;
        border: 2px solid var(--pink-primary);
        color: var(--pink-primary);
        font-weight: 600;
        padding: 10px 20px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    
    .btn-outline-owner:hover {
        background: var(--pink-primary);
        color: white;
        transform: translateY(-2px);
    }
    
    /* ===================== */
    /* PROFILE PHOTO */
    /* ===================== */
    .profile-photo-container {
        text-align: center;
        padding: 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 15px;
        margin-bottom: 20px;
    }
    
    .profile-photo {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        margin-bottom: 15px;
        transition: transform 0.3s ease;
    }
    
    .profile-photo:hover {
        transform: scale(1.05);
    }
    
    /* ===================== */
    /* TABLES */
    /* ===================== */
    .table-owner {
        background: transparent;
        color: var(--text-dark);
        border-radius: 10px;
        overflow: hidden;
    }
    
    .table-owner th {
        background: var(--pink-soft);
        border: none;
        color: var(--pink-primary);
        font-weight: 700;
        padding: 15px;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }
    
    .table-owner td {
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
        padding: 12px 15px;
        font-weight: 500;
    }
    
    .table-owner tbody tr {
        transition: all 0.3s ease;
    }
    
    .table-owner tbody tr:hover {
        background: var(--pink-soft);
        transform: translateX(5px);
    }
    
    /* ===================== */
    /* MESSAGES */
    /* ===================== */
    .message-list {
        max-height: 400px;
        overflow-y: auto;
        padding-right: 10px;
    }
    
    .message-item {
        background: var(--pink-soft);
        border-left: 4px solid var(--pink-primary);
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .message-item:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(255, 95, 158, 0.1);
    }
    
    .message-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-bottom: 8px;
    }
    
    .message-content {
        color: var(--text-dark);
        line-height: 1.5;
    }
    
    /* ===================== */
    /* PARAMETER GRID (READ-ONLY) */
    /* ===================== */
    .parameter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .parameter-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px solid var(--border-color);
        border-radius: 10px;
        padding: 20px;
    }
    
    .param-label {
        font-weight: 600;
        color: var(--pink-primary);
        margin-bottom: 10px;
        display: block;
    }
    
    .param-value {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-top: 5px;
    }
    
    /* ===================== */
    /* ALERTS */
    /* ===================== */
    .alert-owner {
        background: var(--pink-soft);
        border: 1px solid var(--pink-primary);
        color: var(--text-dark);
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 20px;
    }
    
    /* ===================== */
    /* BADGES */
    /* ===================== */
    .badge-owner {
        background: var(--pink-gradient);
        color: white;
        padding: 8px 18px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.9rem;
    }
    
    .badge-admin {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        color: white;
    }
    
    .badge-user {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
    }
    
    /* ===================== */
    /* STATISTICS */
    /* ===================== */
    .stats-container {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 15px;
        padding: 25px;
        border: 2px solid var(--border-color);
        text-align: center;
    }
    
    .stat-number {
        font-size: 3rem;
        font-weight: 800;
        color: var(--pink-primary);
        line-height: 1;
    }
    
    .stat-label {
        font-size: 1rem;
        color: var(--text-muted);
        font-weight: 600;
        margin-top: 10px;
    }
    
    /* ===================== */
    /* DOWNLOAD SECTION */
    /* ===================== */
    .download-section {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 15px;
        padding: 25px;
        border: 2px dashed var(--pink-primary);
        text-align: center;
        margin-bottom: 25px;
    }
    
    .download-icon {
        font-size: 4rem;
        color: var(--pink-primary);
        margin-bottom: 20px;
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
        
        .nav-tabs .nav-link {
            padding: 10px 15px;
            font-size: 0.9rem;
        }
        
        .parameter-grid {
            grid-template-columns: 1fr;
        }
        
        .card-body {
            padding: 20px;
        }
    }
    
    @media (max-width: 576px) {
        .profile-photo {
            width: 120px;
            height: 120px;
        }
        
        .nav-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
        }
        
        .nav-tabs .nav-link {
            white-space: nowrap;
        }
    }
    
    /* ===================== */
    /* UTILITY CLASSES */
    /* ===================== */
    .text-pink {
        color: var(--pink-primary) !important;
    }
    
    .bg-pink {
        background: var(--pink-gradient) !important;
    }
    
    .border-pink {
        border-color: var(--pink-primary) !important;
    }
    
    /* ===================== */
    /* ANIMATIONS */
    /* ===================== */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .fade-in {
        animation: fadeIn 0.5s ease;
    }
    </style>
</head>
<body>

<!-- INCLUDE SIDEBAR -->
<?php include '../includes/sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="main-content">

<!-- PAGE HEADER -->
<div class="page-header">
    <h1><i class="fas fa-eye"></i> Dashboard Monitoring 
        <span class="owner-badge"><i class="fas fa-user-tie"></i> Owner - Read Only</span>
    </h1>
    <p>Pantau data dan aktivitas sistem Dapur Melly (Mode Baca Saja)</p>
</div>

<!-- ALERT MESSAGES -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-owner alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2 text-success"></i>
    <span><?= htmlspecialchars($success_message) ?></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-owner alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
    <span><?= htmlspecialchars($error_message) ?></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- TABS NAVIGATION -->
<ul class="nav nav-tabs" id="ownerTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="monitoring-tab" data-bs-toggle="tab" data-bs-target="#monitoring" type="button" role="tab">
            <i class="fas fa-chart-line"></i> Monitoring Sistem
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
            <i class="fas fa-user-circle"></i> Profil Owner
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="targets-tab" data-bs-toggle="tab" data-bs-target="#targets" type="button" role="tab">
            <i class="fas fa-bullseye"></i> Target Bisnis
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="policies-tab" data-bs-toggle="tab" data-bs-target="#policies" type="button" role="tab">
            <i class="fas fa-gavel"></i> Kebijakan
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="download-tab" data-bs-toggle="tab" data-bs-target="#download" type="button" role="tab">
            <i class="fas fa-download"></i> Download Data
        </button>
    </li>
</ul>

<!-- TAB CONTENT -->
<div class="tab-content" id="ownerTabContent">
    
    <!-- TAB 1: MONITORING SISTEM -->
    <div class="tab-pane fade show active" id="monitoring" role="tabpanel">
        <div class="row fade-in">
            <div class="col-lg-12">
                <div class="card-owner">
                    <div class="card-header-owner">
                        <h5><i class="fas fa-history"></i> Riwayat Login Sistem (20 Terakhir)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-owner">
                                <thead>
                                    <tr>
                                        <th>Tanggal & Waktu</th>
                                        <th>Nama Pengguna</th>
                                        <th>Role</th>
                                        <th>IP Address</th>
                                        <th>Perangkat</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($all_login_history)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="fas fa-history fa-3x mb-3"></i>
                                                <h5>Belum ada riwayat login</h5>
                                                <p>Tidak ada aktivitas login yang tercatat</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($all_login_history as $log): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($log['login_time'])) ?></td>
                                            <td><strong><?= htmlspecialchars($log['nama_lengkap']) ?></strong></td>
                                            <td>
                                                <?php if ($log['role'] == 'owner'): ?>
                                                    <span class="badge badge-owner">Owner</span>
                                                <?php elseif ($log['role'] == 'admin'): ?>
                                                    <span class="badge badge-admin">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge badge-user">User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($log['device_info'] ?? '-') ?></td>
                                            <td><span class="badge bg-success">Berhasil</span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-info alert-owner mt-4">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            <div>
                                <strong>Informasi Monitoring:</strong> Anda dapat memantau siapa saja yang mengakses sistem Dapur Melly.
                                Mode Owner hanya memiliki akses baca untuk menjaga keamanan sistem.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SYSTEM STATS -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="stats-container">
                    <div class="stat-number"><?= count($users_data) ?></div>
                    <div class="stat-label">Total Pengguna</div>
                    <div class="mt-3">
                        <small class="text-muted">Owner: <?= count(array_filter($users_data, fn($u) => $u['role'] == 'owner')) ?></small><br>
                        <small class="text-muted">Admin: <?= count(array_filter($users_data, fn($u) => $u['role'] == 'admin')) ?></small><br>
                        <small class="text-muted">User: <?= count(array_filter($users_data, fn($u) => $u['role'] == 'user')) ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stats-container">
                    <div class="stat-number"><?= count($karyawan_data) ?></div>
                    <div class="stat-label">Total Karyawan</div>
                    <div class="mt-3">
                        <small class="text-muted">Aktif: <?= count($karyawan_data) ?></small><br>
                        <small class="text-muted">Total Gaji: Rp <?= number_format(array_sum(array_column($karyawan_data, 'gaji_pokok')), 0, ',', '.') ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stats-container">
                    <div class="stat-number"><?= count($presensi_data) ?></div>
                    <div class="stat-label">Presensi Bulan Ini</div>
                    <div class="mt-3">
                        <small class="text-muted">Bulan: <?= date('F Y') ?></small><br>
                        <small class="text-muted">Tanggal: <?= date('d/m/Y') ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ADMIN MESSAGES -->
        <div class="card-owner mt-4">
            <div class="card-header-owner">
                <h5><i class="fas fa-comments"></i> Pesan Terbaru dari Admin</h5>
            </div>
            <div class="card-body">
                <?php if (empty($admin_messages)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-comment-slash fa-3x mb-3"></i>
                        <h5>Belum ada pesan</h5>
                        <p>Tidak ada pesan dari Admin saat ini</p>
                    </div>
                <?php else: ?>
                    <div class="message-list">
                        <?php foreach ($admin_messages as $msg): ?>
                        <div class="message-item">
                            <div class="message-meta">
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($msg['sender_name']) ?></span>
                                <span><i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></span>
                            </div>
                            <div class="message-content">
                                <i class="fas fa-quote-left text-pink me-2"></i>
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                <i class="fas fa-quote-right text-pink ms-2"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- TAB 2: PROFIL OWNER -->
    <div class="tab-pane fade" id="profile" role="tabpanel">
        <div class="row fade-in">
            <div class="col-lg-4">
                <div class="card-owner">
                    <div class="card-header-owner">
                        <h5><i class="fas fa-user-circle"></i> Profil Owner</h5>
                    </div>
                    <div class="card-body">
                        <div class="profile-photo-container">
                            <?php 
                            $foto_path = "../assets/img/profile/" . $foto_profil;
                            if (!file_exists($foto_path) || $foto_profil == 'default.png') {
                                $foto_path = "https://ui-avatars.com/api/?name=" . urlencode($nama_owner) . "&background=ff5f9e&color=fff&size=150";
                            }
                            ?>
                            <img src="<?= $foto_path ?>" class="profile-photo" alt="Foto Profil Owner" 
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($nama_owner) ?>&background=ff5f9e&color=fff&size=150'">
                            <h4 class="mt-3 text-dark"><?= htmlspecialchars($nama_owner) ?></h4>
                            <p class="text-muted mb-2">Owner • Dapur Melly</p>
                            <p class="text-pink"><i class="fas fa-star"></i> Pemilik Perusahaan</p>
                        </div>
                        
                        <div class="alert alert-info alert-owner mt-4">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            <div>
                                <strong>Mode Read Only:</strong> Data profil hanya dapat dilihat, tidak dapat diubah melalui panel Owner.
                                Hubungi Admin untuk perubahan profil.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card-owner">
                    <div class="card-header-owner">
                        <h5><i class="fas fa-info-circle"></i> Informasi Profil (Read Only)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" 
                                       value="<?= htmlspecialchars($data_owner['nama_lengkap'] ?? '') ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" 
                                       value="<?= htmlspecialchars($username) ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" 
                                       value="<?= htmlspecialchars($email) ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="Owner" readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Bergabung</label>
                                <input type="text" class="form-control" 
                                       value="<?= date('d F Y', strtotime($data_owner['created_at'] ?? date('Y-m-d'))) ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Login Terakhir</label>
                                <input type="text" class="form-control" 
                                       value="<?= !empty($data_owner['last_login']) ? date('d F Y H:i', strtotime($data_owner['last_login'])) : 'Belum pernah' ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- GANTI PASSWORD (SATU-SATUNYA YANG BISA DIEDIT) -->
                <div class="card-owner mt-4">
                    <div class="card-header-owner">
                        <h5><i class="fas fa-key"></i> Ganti Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="passwordForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="password_lama" id="password_lama" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_lama')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="password_baru" id="password_baru" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_baru')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Minimal 8 karakter</div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="konfirmasi_password" id="konfirmasi_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('konfirmasi_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-owner">
                                <i class="fas fa-key"></i> Ganti Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TAB 3: TARGET BISNIS -->
    <div class="tab-pane fade" id="targets" role="tabpanel">
        <div class="row fade-in">
            <div class="col-lg-12">
                <div class="card-owner">
                    <div class="card-header-owner">
                        <h5><i class="fas fa-bullseye"></i> Target Pendapatan <?= $current_year ?> (Read Only)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-owner">
                                <thead>
                                    <tr>
                                        <th>Bulan</th>
                                        <th>Tahun</th>
                                        <th>Target (Rp)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($target_pendapatan)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-5">
                                                <i class="fas fa-bullseye fa-3x mb-3"></i>
                                                <h5>Belum ada target yang ditetapkan</h5>
                                                <p>Hubungi Admin untuk menetapkan target pendapatan</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $months = [
                                            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                                            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                                            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                                            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                                        ];
                                        $total_target = 0;
                                        ?>
                                        <?php foreach ($target_pendapatan as $target): 
                                            $total_target += $target['target_amount'];
                                        ?>
                                        <tr>
                                            <td><strong><?= $months[$target['bulan']] ?? 'Bulan ' . $target['bulan'] ?></strong></td>
                                            <td><?= htmlspecialchars($target['tahun'] ?? $current_year) ?></td>
                                            <td><strong class="text-pink">Rp <?= number_format($target['target_amount'] ?? 0, 0, ',', '.') ?></strong></td>
                                            <td>
                                                <span class="badge badge-owner">
                                                    <i class="fas fa-eye"></i> Lihat Saja
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <?php if (!empty($target_pendapatan)): ?>
                                <tfoot>
                                    <tr>
                                        <td colspan="2"><strong>Total Target Tahun <?= $current_year ?></strong></td>
                                        <td><strong class="text-pink">Rp <?= number_format($total_target, 0, ',', '.') ?></strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                        
                        <div class="alert alert-info alert-owner mt-4">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            <div>
                                <strong>Informasi:</strong> Target pendapatan ditetapkan oleh Admin. Mode Owner hanya dapat melihat target, 
                                tidak dapat mengubahnya. Hubungi Admin untuk perubahan target.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- STATISTIK TARGET -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="stats-container">
                    <div class="stat-number"><?= count($target_pendapatan) ?></div>
                    <div class="stat-label">Target yang Telah Ditentukan</div>
                    <div class="mt-3">
                        <small class="text-muted">Sisa bulan: <?= 12 - count($target_pendapatan) ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stats-container">
                    <div class="stat-number">Rp <?= number_format($total_target ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-label">Total Target Tahunan</div>
                    <div class="mt-3">
                        <small class="text-muted">Rata-rata: Rp <?= count($target_pendapatan) > 0 ? number_format($total_target / count($target_pendapatan), 0, ',', '.') : 0 ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stats-container">
                    <div class="stat-number"><?= $current_year ?></div>
                    <div class="stat-label">Tahun Berjalan</div>
                    <div class="mt-3">
                        <small class="text-muted">Bulan: <?= date('F') ?></small><br>
                        <small class="text-muted">Tanggal: <?= date('d/m/Y') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TAB 4: KEBIJAKAN PERUSAHAAN -->
    <div class="tab-pane fade" id="policies" role="tabpanel">
        <div class="card-owner fade-in">
            <div class="card-header-owner">
                <h5><i class="fas fa-gavel"></i> Parameter Sistem & Kebijakan Perusahaan (Read Only)</h5>
            </div>
            <div class="card-body">
                <div class="parameter-grid">
                    <?php foreach ($system_params as $key => $param): ?>
                    <div class="parameter-card">
                        <label class="param-label">
                            <i class="fas fa-sliders-h me-2"></i>
                            <?= htmlspecialchars($param['param_label']) ?>
                        </label>
                        <div class="param-value">
                            <?php if ($param['param_type'] == 'number'): ?>
                                Rp <?= number_format($param['param_value'], 0, ',', '.') ?>
                            <?php else: ?>
                                <?= htmlspecialchars($param['param_value']) ?>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted mt-2 d-block">Kode: <?= htmlspecialchars($key) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="alert alert-info alert-owner mt-4">
                    <i class="fas fa-info-circle text-info me-2"></i>
                    <div>
                        <strong>Mode Read Only:</strong> Parameter sistem ditetapkan oleh Admin. 
                        Anda hanya dapat melihat kebijakan yang berlaku, tidak dapat mengubahnya.
                        Hubungi Admin untuk perubahan parameter.
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            <div>
                                <strong>Parameter ini mempengaruhi:</strong>
                                <ul class="mt-2 mb-0">
                                    <li>Perhitungan gaji karyawan</li>
                                    <li>Penentuan keterlambatan</li>
                                    <li>Jam kerja yang berlaku</li>
                                    <li>Monitoring bisnis</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <div>
                                <strong>Status:</strong> Semua parameter telah ditetapkan oleh Admin.
                                Sistem berjalan sesuai dengan kebijakan yang berlaku.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TAB 5: DOWNLOAD DATA -->
    <div class="tab-pane fade" id="download" role="tabpanel">
        <div class="download-section fade-in">
            <div class="download-icon">
                <i class="fas fa-file-excel"></i>
            </div>
            <h3 class="text-pink">Download Data Lengkap</h3>
            <p class="text-muted">Unduh seluruh data sistem Dapur Melly dalam format Excel untuk keperluan backup, analisis, atau laporan.</p>
            
            <div class="row mt-4">
                <div class="col-md-4 mb-3">
                    <div class="stats-container">
                        <div class="stat-number"><?= count($users_data) ?></div>
                        <div class="stat-label">Data Users</div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="stats-container">
                        <div class="stat-number"><?= count($karyawan_data) ?></div>
                        <div class="stat-label">Data Karyawan</div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="stats-container">
                        <div class="stat-number"><?= count($presensi_data) ?></div>
                        <div class="stat-label">Data Presensi</div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <a href="?download=all_data" class="btn btn-download btn-lg">
                    <i class="fas fa-download"></i> Download Semua Data (Excel)
                </a>
                <p class="text-muted mt-3">File akan berisi: Data Users, Data Karyawan, dan Data Presensi bulan ini.</p>
            </div>
        </div>
        
        <div class="card-owner mt-4">
            <div class="card-header-owner">
                <h5><i class="fas fa-info-circle"></i> Informasi Download</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-pink mb-3"><i class="fas fa-check-circle"></i> Data yang Tersedia</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-user text-success me-2"></i> Data semua pengguna (Owner, Admin, User)</li>
                            <li class="mb-2"><i class="fas fa-users text-success me-2"></i> Data karyawan lengkap dengan gaji</li>
                            <li class="mb-2"><i class="fas fa-calendar-check text-success me-2"></i> Data presensi bulan berjalan</li>
                            <li><i class="fas fa-history text-success me-2"></i> Data login history</li>
                        </ul>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-pink mb-3"><i class="fas fa-shield-alt"></i> Keamanan Data</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-lock text-info me-2"></i> Hanya Owner yang dapat mengunduh data</li>
                            <li class="mb-2"><i class="fas fa-clock text-info me-2"></i> Data diperbarui real-time</li>
                            <li class="mb-2"><i class="fas fa-file-excel text-info me-2"></i> Format Excel aman dan terstruktur</li>
                            <li><i class="fas fa-download text-info me-2"></i> Tidak ada batasan download</li>
                        </ul>
                    </div>
                </div>
                
                <div class="alert alert-warning alert-owner mt-4">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    <div>
                        <strong>Perhatian:</strong> Data yang diunduh bersifat rahasia. 
                        Simpan file dengan aman dan jangan bagikan dengan pihak yang tidak berwenang.
                    </div>
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
// Fungsi toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        button.classList.remove('fa-eye');
        button.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        button.classList.remove('fa-eye-slash');
        button.classList.add('fa-eye');
    }
}

// Form validation for password
document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
    const newPass = document.getElementById('password_baru').value;
    const confirmPass = document.getElementById('konfirmasi_password').value;
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        Swal.fire({
            title: 'Password Tidak Cocok',
            text: 'Password baru dan konfirmasi password tidak cocok.',
            icon: 'error',
            confirmButtonColor: '#ff5f9e'
        });
    } else if (newPass.length < 8) {
        e.preventDefault();
        Swal.fire({
            title: 'Password Terlalu Pendek',
            text: 'Password baru minimal 8 karakter.',
            icon: 'warning',
            confirmButtonColor: '#ff5f9e'
        });
    }
});

// Confirm before downloading
document.querySelector('a[href*="download=all_data"]')?.addEventListener('click', function(e) {
    e.preventDefault();
    const downloadUrl = this.href;
    
    Swal.fire({
        title: 'Download Data Lengkap?',
        html: 'Anda akan mengunduh seluruh data sistem Dapur Melly.<br><br><strong>Data yang akan diunduh:</strong><br>• Data Users (<?= count($users_data) ?> record)<br>• Data Karyawan (<?= count($karyawan_data) ?> record)<br>• Data Presensi (<?= count($presensi_data) ?> record)',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-download"></i> Ya, Download!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Menyiapkan Data...',
                text: 'Sedang memproses data untuk download',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Redirect to download
            window.location.href = downloadUrl;
        }
    });
});

// Auto-hide alert after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        setTimeout(() => bsAlert.close(), 5000);
    });
}, 5000);

// SweetAlert2 notifications
document.addEventListener('DOMContentLoaded', function() {
    // Success message
    <?php if ($success_message): ?>
    Swal.fire({
        title: 'Berhasil!',
        text: '<?= addslashes($success_message) ?>',
        icon: 'success',
        confirmButtonColor: '#ff5f9e',
        timer: 3000,
        timerProgressBar: true
    });
    <?php endif; ?>
    
    // Error message
    <?php if ($error_message): ?>
    Swal.fire({
        title: 'Terjadi Kesalahan',
        text: '<?= addslashes($error_message) ?>',
        icon: 'error',
        confirmButtonColor: '#ff5f9e'
    });
    <?php endif; ?>
});

// Initialize Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Add animation when tab changes
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function (event) {
        const activePane = document.querySelector(event.target.getAttribute('data-bs-target'));
        const fadeElements = activePane.querySelectorAll('.fade-in');
        
        fadeElements.forEach((element, index) => {
            element.style.animationDelay = `${index * 0.1}s`;
        });
    });
});
</script>
</body>
</html>