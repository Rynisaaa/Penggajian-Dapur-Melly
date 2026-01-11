<?php
session_start();

/**
 * VALIDASI SESSION ADMIN
 */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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
 * TENTUKAN ADMIN_ID YANG BENAR
 */
if (!isset($_SESSION['id'])) {
    // Ambil admin pertama dari database
    $admin_query = mysqli_query($conn, "SELECT * FROM users WHERE role = 'admin' LIMIT 1");
    if ($admin_query && mysqli_num_rows($admin_query) > 0) {
        $admin = mysqli_fetch_assoc($admin_query);
        $_SESSION['id'] = $admin['id'];
    } else {
        header("Location: ../login.php");
        exit;
    }
}

$admin_id = $_SESSION['id'];
$nama_user = $_SESSION['nama_lengkap'] ?? 'Admin';

/**
 * AMBIL DATA ADMIN
 */
$query_admin = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($query_admin, "i", $admin_id);
mysqli_stmt_execute($query_admin);
$admin_result = mysqli_stmt_get_result($query_admin);
$data_admin = mysqli_fetch_assoc($admin_result);

if (!$data_admin) {
    header("Location: ../login.php");
    exit;
}

// Update nama_user dari database jika perlu
if (empty($nama_user) || $nama_user == 'Admin') {
    $nama_user = $data_admin['nama_lengkap'] ?? 'Admin';
    $_SESSION['nama_lengkap'] = $nama_user;
}

/**
 * INISIALISASI VARIABEL
 */
$success_message = '';
$error_message = '';
$foto_profil = $data_admin['foto_profil'] ?? 'default.png';
$email = $data_admin['email'] ?? '';
$username = $data_admin['username'] ?? '';

/**
 * CEK DAN BUAT TABEL SISTEM PARAMETER JIKA BELUM ADA
 */
$check_param_table = mysqli_query($conn, "SHOW TABLES LIKE 'system_parameters'");
if (mysqli_num_rows($check_param_table) == 0) {
    mysqli_query($conn, "CREATE TABLE system_parameters (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        param_key VARCHAR(50) NOT NULL UNIQUE,
        param_value TEXT,
        param_label VARCHAR(100),
        param_type VARCHAR(20) DEFAULT 'text',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default parameters
    $default_params = [
        ['uang_makan', '20000', 'Uang Makan per Hari', 'number'],
        ['potongan_terlambat', '5000', 'Potongan Terlambat per Kejadian', 'number'],
        ['jam_masuk_baker', '07:00', 'Jam Masuk Baker', 'time'],
        ['jam_masuk_umum', '08:00', 'Jam Masuk Umum', 'time'],
        ['target_warning_percent', '80', 'Persentase Warning Target', 'number']
    ];
    
    foreach ($default_params as $param) {
        $stmt = mysqli_prepare($conn, "INSERT INTO system_parameters (param_key, param_value, param_label, param_type) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $param[0], $param[1], $param[2], $param[3]);
        mysqli_stmt_execute($stmt);
    }
}

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
 * AMBIL RIWAYAT LOGIN ADMIN
 */
$login_history = [];
$query_history = mysqli_query($conn, "
    SELECT * FROM login_history 
    WHERE user_id = '$admin_id' 
    ORDER BY login_time DESC 
    LIMIT 10
");

if ($query_history) {
    while ($row = mysqli_fetch_assoc($query_history)) {
        $login_history[] = $row;
    }
}

/**
 * AMBIL PESAN SISTEM
 */
$system_messages = [];
$query_messages = mysqli_query($conn, "
    SELECT * FROM pesan_sistem 
    WHERE tujuan_role = 'admin' OR sender_id = '$admin_id'
    ORDER BY created_at DESC 
    LIMIT 10
");

if ($query_messages) {
    while ($row = mysqli_fetch_assoc($query_messages)) {
        $system_messages[] = $row;
    }
}

/**
 * PROSES UPDATE PROFIL
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        
        // Update tabel users
        $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, email = ?, username = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssi", $nama_lengkap, $email, $username, $admin_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['nama_lengkap'] = $nama_lengkap;
            $success_message = "Profil berhasil diperbarui!";
        } else {
            $error_message = "Gagal memperbarui profil: " . mysqli_error($conn);
        }
    }
    
    /**
     * PROSES UPLOAD FOTO PROFIL
     */
    if (isset($_POST['upload_foto']) && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $file_name = $_FILES['foto_profil']['name'];
        $file_tmp = $_FILES['foto_profil']['tmp_name'];
        $file_size = $_FILES['foto_profil']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $error_message = "Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.";
        } elseif ($file_size > 2097152) {
            $error_message = "Ukuran file terlalu besar. Maksimal 2MB.";
        } else {
            $new_file_name = "admin_profile_" . $admin_id . "_" . time() . "." . $file_ext;
            $upload_path = "../assets/img/profile/" . $new_file_name;
            
            if (!file_exists("../assets/img/profile/")) {
                mkdir("../assets/img/profile/", 0777, true);
            }
            
            if ($foto_profil != 'default.png' && file_exists("../assets/img/profile/" . $foto_profil)) {
                unlink("../assets/img/profile/" . $foto_profil);
            }
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $stmt = mysqli_prepare($conn, "UPDATE users SET foto_profil = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $new_file_name, $admin_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $foto_profil = $new_file_name;
                    $success_message = "Foto profil berhasil diperbarui!";
                } else {
                    $error_message = "Gagal menyimpan informasi foto ke database.";
                }
            } else {
                $error_message = "Gagal mengupload foto.";
            }
        }
    }
    
    /**
     * PROSES GANTI PASSWORD
     */
    if (isset($_POST['change_password'])) {
        $password_lama = $_POST['password_lama'];
        $password_baru = $_POST['password_baru'];
        $konfirmasi_password = $_POST['konfirmasi_password'];
        
        // Verifikasi password lama
        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $admin_id);
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
                mysqli_stmt_bind_param($stmt, "si", $password_hash, $admin_id);
                
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
    
    /**
     * PROSES UPDATE PARAMETER SISTEM
     */
    if (isset($_POST['update_parameters'])) {
        foreach ($_POST['param'] as $key => $value) {
            $value = mysqli_real_escape_string($conn, $value);
            $stmt = mysqli_prepare($conn, "UPDATE system_parameters SET param_value = ? WHERE param_key = ?");
            mysqli_stmt_bind_param($stmt, "ss", $value, $key);
            mysqli_stmt_execute($stmt);
        }
        $success_message = "Parameter sistem berhasil diperbarui!";
    }
    
    /**
     * PROSES UPDATE TARGET PENDAPATAN
     */
    if (isset($_POST['update_target'])) {
        $bulan = mysqli_real_escape_string($conn, $_POST['bulan']);
        $tahun = mysqli_real_escape_string($conn, $_POST['tahun']);
        $target_amount = str_replace(['.', ','], '', $_POST['target_amount']);
        
        // Cek apakah data sudah ada
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM target_pendapatan WHERE bulan = ? AND tahun = ?");
        mysqli_stmt_bind_param($check_stmt, "ss", $bulan, $tahun);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Update existing
            $stmt = mysqli_prepare($conn, "UPDATE target_pendapatan SET target_amount = ? WHERE bulan = ? AND tahun = ?");
            mysqli_stmt_bind_param($stmt, "dss", $target_amount, $bulan, $tahun);
        } else {
            // Insert new
            $stmt = mysqli_prepare($conn, "INSERT INTO target_pendapatan (bulan, tahun, target_amount) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssd", $bulan, $tahun, $target_amount);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Target pendapatan berhasil diperbarui!";
        } else {
            $error_message = "Gagal memperbarui target: " . mysqli_error($conn);
        }
    }
    
    /**
     * PROSES KIRIM PESAN
     */
    if (isset($_POST['send_message'])) {
        $message = mysqli_real_escape_string($conn, $_POST['message']);
        $sender_name = $data_admin['nama_lengkap'];
        
        $stmt = mysqli_prepare($conn, "INSERT INTO pesan_sistem (sender_id, sender_name, message, tujuan_role) VALUES (?, ?, ?, 'owner')");
        mysqli_stmt_bind_param($stmt, "iss", $admin_id, $sender_name, $message);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Pesan berhasil dikirim ke Owner!";
        } else {
            $error_message = "Gagal mengirim pesan: " . mysqli_error($conn);
        }
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
    <title>Pengaturan Admin - Dapur Melly</title>
    
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
        font-weight: 500;
        padding: 15px 25px;
        border-radius: 10px 10px 0 0;
        transition: all 0.3s ease;
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
    .card-custom {
        background: var(--white);
        border: none;
        border-radius: 15px;
        box-shadow: var(--card-shadow);
        margin-bottom: 25px;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .card-custom:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(255, 154, 158, 0.2);
    }
    
    .card-header-custom {
        background: var(--pink-gradient);
        border-bottom: none;
        padding: 20px;
        color: white;
        border-radius: 15px 15px 0 0 !important;
    }
    
    .card-header-custom h5 {
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
    /* FORMS */
    /* ===================== */
    .form-label {
        color: var(--text-dark);
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .form-control, .form-select {
        border: 2px solid #e9ecef;
        color: var(--text-dark);
        padding: 12px 15px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--pink-primary);
        color: var(--text-dark);
        box-shadow: 0 0 0 0.25rem rgba(255, 95, 158, 0.25);
    }
    
    .form-text {
        color: var(--text-muted);
        font-size: 0.9rem;
    }
    
    .input-group-text {
        background-color: #f8f9fa;
        border: 2px solid #e9ecef;
        color: var(--text-muted);
    }
    
    /* ===================== */
    /* BUTTONS */
    /* ===================== */
    .btn-custom {
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
    
    .btn-custom:hover {
        background: linear-gradient(135deg, #ff8a9e 0%, #fec0ef 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(255, 154, 158, 0.3);
    }
    
    .btn-outline-custom {
        background: transparent;
        border: 2px solid var(--pink-primary);
        color: var(--pink-primary);
        font-weight: 600;
        padding: 10px 20px;
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    
    .btn-outline-custom:hover {
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
    .table-custom {
        background: transparent;
        color: var(--text-dark);
    }
    
    .table-custom th {
        background: var(--pink-soft);
        border-color: var(--border-color);
        color: var(--pink-primary);
        font-weight: 600;
        padding: 15px;
    }
    
    .table-custom td {
        border-color: var(--border-color);
        vertical-align: middle;
        padding: 12px 15px;
    }
    
    .table-custom tbody tr:hover {
        background: var(--pink-soft);
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
    /* PARAMETER GRID */
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
        transition: all 0.3s ease;
    }
    
    .parameter-card:hover {
        border-color: var(--pink-primary);
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(255, 95, 158, 0.1);
    }
    
    .param-label {
        font-weight: 600;
        color: var(--pink-primary);
        margin-bottom: 10px;
        display: block;
    }
    
    /* ===================== */
    /* ALERTS */
    /* ===================== */
    .alert-custom {
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
    .badge-pink {
        background: var(--pink-gradient);
        color: white;
    }
    
    /* ===================== */
    /* PROGRESS BARS */
    /* ===================== */
    .progress {
        height: 8px;
        border-radius: 4px;
    }
    
    .progress-bar {
        background: var(--pink-gradient);
        border-radius: 4px;
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
        
        .card-custom {
            border-radius: 10px;
        }
    }
    
    @media (max-width: 576px) {
        .card-body {
            padding: 20px;
        }
        
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
    <h1><i class="fas fa-cogs"></i> Pengaturan Sistem - Admin</h1>
    <p>Kelola profil, keamanan, dan parameter sistem Dapur Melly</p>
</div>

<!-- ALERT MESSAGES -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2 text-success"></i>
    <span class="text-success"><?= htmlspecialchars($success_message) ?></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
    <span class="text-danger"><?= htmlspecialchars($error_message) ?></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- TABS NAVIGATION -->
<ul class="nav nav-tabs" id="settingsTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
            <i class="fas fa-user-circle"></i> Profil Admin
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
            <i class="fas fa-shield-alt"></i> Keamanan
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="parameters-tab" data-bs-toggle="tab" data-bs-target="#parameters" type="button" role="tab">
            <i class="fas fa-sliders-h"></i> Parameter Sistem
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="targets-tab" data-bs-toggle="tab" data-bs-target="#targets" type="button" role="tab">
            <i class="fas fa-bullseye"></i> Target Pendapatan
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="messages-tab" data-bs-toggle="tab" data-bs-target="#messages" type="button" role="tab">
            <i class="fas fa-comments"></i> Komunikasi
        </button>
    </li>
</ul>

<!-- TAB CONTENT -->
<div class="tab-content" id="settingsTabContent">
    
    <!-- TAB 1: PROFIL ADMIN -->
    <div class="tab-pane fade show active" id="profile" role="tabpanel">
        <div class="row fade-in">
            <div class="col-lg-4">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-camera"></i> Foto Profil</h5>
                    </div>
                    <div class="card-body">
                        <div class="profile-photo-container">
                            <?php 
                            $foto_path = "../assets/img/profile/" . $foto_profil;
                            if (!file_exists($foto_path) || $foto_profil == 'default.png') {
                                $foto_path = "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=ff5f9e&color=fff&size=150";
                            }
                            ?>
                            <img src="<?= $foto_path ?>" class="profile-photo" alt="Foto Profil" 
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=ff5f9e&color=fff&size=150'">
                            <h5 class="mt-3 text-dark"><?= htmlspecialchars($nama_user) ?></h5>
                            <p class="text-muted">Admin • Dapur Melly</p>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Ganti Foto Profil</label>
                                <input type="file" class="form-control" name="foto_profil" accept="image/*">
                                <div class="form-text">Format: JPG, PNG, GIF. Maksimal 2MB</div>
                            </div>
                            <button type="submit" name="upload_foto" class="btn btn-custom w-100">
                                <i class="fas fa-upload"></i> Upload Foto
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-edit"></i> Edit Profil Admin</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_lengkap" 
                                           value="<?= htmlspecialchars($data_admin['nama_lengkap'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="username" 
                                           value="<?= htmlspecialchars($username) ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?= htmlspecialchars($email) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="Administrator" readonly style="background-color: #f8f9fa;">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tanggal Bergabung</label>
                                    <input type="text" class="form-control" 
                                           value="<?= date('d F Y', strtotime($data_admin['created_at'] ?? date('Y-m-d'))) ?>" readonly style="background-color: #f8f9fa;">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Login Terakhir</label>
                                    <input type="text" class="form-control" 
                                           value="<?= !empty($data_admin['last_login']) ? date('d F Y H:i', strtotime($data_admin['last_login'])) : 'Belum pernah' ?>" readonly style="background-color: #f8f9fa;">
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3 mt-4">
                                <button type="submit" name="update_profile" class="btn btn-custom">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                                <button type="reset" class="btn btn-outline-custom">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- LOGIN HISTORY -->
                <div class="card-custom mt-4 fade-in">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-history"></i> Riwayat Login Terakhir</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Tanggal & Waktu</th>
                                        <th>IP Address</th>
                                        <th>Perangkat</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($login_history)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="fas fa-info-circle"></i> Belum ada riwayat login
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($login_history as $log): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($log['login_time'])) ?></td>
                                            <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($log['device_info'] ?? '-') ?></td>
                                            <td><span class="badge bg-success">Berhasil</span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TAB 2: KEAMANAN -->
    <div class="tab-pane fade" id="security" role="tabpanel">
        <div class="row fade-in">
            <div class="col-lg-6">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-key"></i> Ganti Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="passwordForm">
                            <div class="mb-3">
                                <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password_lama" id="password_lama" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_lama')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password_baru" id="password_baru" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_baru')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimal 8 karakter</div>
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
                            
                            <div class="alert alert-info alert-custom">
                                <i class="fas fa-info-circle text-info"></i>
                                <div>
                                    <strong>Tips keamanan:</strong> Gunakan kombinasi huruf besar, kecil, angka, dan simbol untuk password yang kuat.
                                </div>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-custom">
                                <i class="fas fa-key"></i> Ganti Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-shield-alt"></i> Keamanan Akun</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="text-pink">Status Akun</h6>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success p-3">
                                    <i class="fas fa-check fa-lg text-white"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1 text-dark">Aman</h5>
                                    <p class="text-muted mb-0">Akun Anda terlindungi dengan baik</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="text-pink">Tips Keamanan</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Gunakan password yang kuat dan unik</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Jangan berbagi kredensial login</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Selalu logout setelah menggunakan perangkat bersama</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Perbarui password secara berkala</li>
                                <li><i class="fas fa-check text-success me-2"></i> Pantau riwayat login secara rutin</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning alert-custom">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            <div>
                                <strong>Penting:</strong> Pastikan Anda adalah satu-satunya yang memiliki akses ke akun admin ini.
                                Segera ubah password jika Anda mencurigai adanya aktivitas yang tidak biasa.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TAB 3: PARAMETER SISTEM -->
    <div class="tab-pane fade" id="parameters" role="tabpanel">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <h5><i class="fas fa-sliders-h"></i> Parameter Sistem Gaji dan Absensi</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="parameter-grid">
                        <?php foreach ($system_params as $key => $param): ?>
                        <div class="parameter-card">
                            <label class="param-label"><?= htmlspecialchars($param['param_label']) ?></label>
                            <?php if ($param['param_type'] == 'number'): ?>
                                <input type="number" class="form-control" 
                                       name="param[<?= htmlspecialchars($key) ?>]" 
                                       value="<?= htmlspecialchars($param['param_value']) ?>"
                                       step="any">
                            <?php elseif ($param['param_type'] == 'time'): ?>
                                <input type="time" class="form-control" 
                                       name="param[<?= htmlspecialchars($key) ?>]" 
                                       value="<?= htmlspecialchars($param['param_value']) ?>">
                            <?php else: ?>
                                <input type="text" class="form-control" 
                                       name="param[<?= htmlspecialchars($key) ?>]" 
                                       value="<?= htmlspecialchars($param['param_value']) ?>">
                            <?php endif; ?>
                            <small class="text-muted">Key: <?= htmlspecialchars($key) ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" name="update_parameters" class="btn btn-custom">
                            <i class="fas fa-save"></i> Simpan Parameter
                        </button>
                        <button type="button" class="btn btn-outline-custom" onclick="resetParameters()">
                            <i class="fas fa-undo"></i> Reset ke Default
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card-custom fade-in">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-info-circle"></i> Informasi Parameter</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <strong class="text-pink">Uang Makan:</strong> 
                                <p class="text-muted mb-0">Jumlah uang makan per hari masuk yang diberikan kepada karyawan</p>
                            </li>
                            <li class="mb-3">
                                <strong class="text-pink">Potongan Terlambat:</strong>
                                <p class="text-muted mb-0">Jumlah potongan untuk setiap keterlambatan karyawan</p>
                            </li>
                            <li class="mb-3">
                                <strong class="text-pink">Jam Masuk Baker:</strong>
                                <p class="text-muted mb-0">Batas jam masuk untuk posisi Baker</p>
                            </li>
                            <li class="mb-3">
                                <strong class="text-pink">Jam Masuk Umum:</strong>
                                <p class="text-muted mb-0">Batas jam masuk untuk posisi selain Baker</p>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card-custom fade-in">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-exclamation-triangle"></i> Perhatian</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle text-warning"></i>
                            <div>
                                <strong>Perubahan parameter dapat mempengaruhi:</strong>
                                <ul class="mt-2">
                                    <li>Perhitungan gaji karyawan</li>
                                    <li>Penentuan keterlambatan absensi</li>
                                    <li>Progress pencapaian target</li>
                                </ul>
                            </div>
                        </div>
                        <p class="text-muted">
                            Pastikan perubahan parameter telah dikonsultasikan dengan Owner sebelum disimpan.
                            Perubahan akan berlaku untuk seluruh karyawan dan mempengaruhi perhitungan selanjutnya.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TAB 4: TARGET PENDAPATAN -->
    <div class="tab-pane fade" id="targets" role="tabpanel">
        <div class="row fade-in">
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-bullseye"></i> Atur Target Pendapatan</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="targetForm">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Bulan</label>
                                    <select class="form-select" name="bulan" required>
                                        <option value="">Pilih Bulan</option>
                                        <?php for ($i = 1; $i <= 12; $i++): 
                                            $month_num = str_pad($i, 2, '0', STR_PAD_LEFT);
                                            $month_name = date('F', mktime(0, 0, 0, $i, 1));
                                        ?>
                                        <option value="<?= $month_num ?>"><?= $month_name ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tahun</label>
                                    <input type="number" class="form-control" name="tahun" 
                                           value="<?= $current_year ?>" min="2024" max="2030" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Target (Rp)</label>
                                    <input type="text" class="form-control" name="target_amount" 
                                           placeholder="10.000.000" required>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_target" class="btn btn-custom">
                                <i class="fas fa-save"></i> Simpan Target
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- TARGET TABLE -->
                <div class="card-custom mt-4">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-table"></i> Daftar Target <?= $current_year ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom">
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
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="fas fa-info-circle"></i> Belum ada target yang ditetapkan
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                                                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                        ?>
                                        <?php foreach ($target_pendapatan as $target): ?>
                                        <tr>
                                            <td><?= $months[($target['bulan'] ?? 1) - 1] ?></td>
                                            <td><?= htmlspecialchars($target['tahun'] ?? $current_year) ?></td>
                                            <td><strong class="text-pink">Rp <?= number_format($target['target_amount'] ?? 0, 0, ',', '.') ?></strong></td>
                                            <td>
                                                <span class="badge badge-pink">Tersimpan</span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-chart-pie"></i> Statistik Target</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="display-6 text-pink"><?= count($target_pendapatan) ?></div>
                            <p class="text-muted">Target yang Telah Ditentukan</p>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="text-pink mb-3">Distribusi Target</h6>
                            <?php 
                            $total_target = array_sum(array_column($target_pendapatan, 'target_amount'));
                            $avg_target = count($target_pendapatan) > 0 ? $total_target / count($target_pendapatan) : 0;
                            ?>
                            <p><strong class="text-dark">Total Target:</strong> <span class="text-pink">Rp <?= number_format($total_target, 0, ',', '.') ?></span></p>
                            <p><strong class="text-dark">Rata-rata per Bulan:</strong> <span class="text-pink">Rp <?= number_format($avg_target, 0, ',', '.') ?></span></p>
                        </div>
                        
                        <div class="alert alert-info alert-custom">
                            <i class="fas fa-lightbulb text-info"></i>
                            <div>
                                <strong>Tips:</strong> Atur target yang realistis berdasarkan data historis pendapatan.
                                Target yang terlalu tinggi dapat demotivasi, sedangkan yang terlalu rendah tidak menantang.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TAB 5: KOMUNIKASI -->
    <div class="tab-pane fade" id="messages" role="tabpanel">
        <div class="row fade-in">
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-paper-plane"></i> Kirim Pesan ke Owner</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="messageForm">
                            <div class="mb-3">
                                <label class="form-label">Pesan</label>
                                <textarea class="form-control" name="message" rows="6" 
                                          placeholder="Tulis pesan penting untuk Owner disini..." required></textarea>
                            </div>
                            
                            <button type="submit" name="send_message" class="btn btn-custom">
                                <i class="fas fa-paper-plane"></i> Kirim Pesan
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- MESSAGE HISTORY -->
                <div class="card-custom mt-4">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-history"></i> Riwayat Pesan</h5>
                    </div>
                    <div class="card-body">
                        <div class="message-list">
                            <?php if (empty($system_messages)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-comment-slash fa-3x mb-3"></i>
                                    <p>Belum ada riwayat pesan</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($system_messages as $msg): ?>
                                <div class="message-item">
                                    <div class="message-meta">
                                        <strong class="text-pink"><?= htmlspecialchars($msg['sender_name']) ?></strong>
                                        <span><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></span>
                                    </div>
                                    <div class="message-content">
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    </div>
                                    <?php if ($msg['tujuan_role']): ?>
                                    <small class="text-muted">Untuk: <?= ucfirst($msg['tujuan_role']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-users"></i> Kontak Penting</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="text-pink mb-3">Owner Dapur Melly</h6>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle bg-pink p-3">
                                    <i class="fas fa-crown fa-lg text-white"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-dark">Owner</h6>
                                    <p class="text-muted mb-0">Pemilik Dapur Melly</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info alert-custom">
                            <i class="fas fa-info-circle text-info"></i>
                            <div>
                                <strong>Informasi:</strong> Pesan yang dikirim akan tersimpan di sistem dan dapat dilihat oleh Owner saat login.
                                Gunakan fitur ini untuk komunikasi formal yang perlu dicatat.
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="text-pink mb-3">Tips Komunikasi</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Gunakan bahasa yang jelas dan profesional</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Sertakan informasi yang lengkap</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Tandai jika bersifat mendesak</li>
                                <li><i class="fas fa-check text-success me-2"></i> Periksa kembali sebelum mengirim</li>
                            </ul>
                        </div>
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

// Format number input for target amount
document.querySelector('input[name="target_amount"]')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^\d]/g, '');
    if (value) {
        e.target.value = parseInt(value).toLocaleString('id-ID');
    }
});

// Reset parameters to default
function resetParameters() {
    Swal.fire({
        title: 'Reset Parameter?',
        text: 'Semua parameter akan dikembalikan ke nilai default. Lanjutkan?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff5f9e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Reset!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // You can implement AJAX call to reset parameters here
            window.location.href = 'pengaturan.php?action=reset_parameters';
        }
    });
}

// Form validation
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

// Auto-hide alert after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        setTimeout(() => bsAlert.close(), 5000);
    });
}, 5000);

// SweetAlert2 for form submissions
document.addEventListener('DOMContentLoaded', function() {
    // Profile form success
    <?php if ($success_message): ?>
    Swal.fire({
        title: 'Berhasil!',
        text: '<?= addslashes($success_message) ?>',
        icon: 'success',
        confirmButtonColor: '#ff5f9e'
    });
    <?php endif; ?>
    
    // Profile form error
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

// Add animation to cards when tab changes
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