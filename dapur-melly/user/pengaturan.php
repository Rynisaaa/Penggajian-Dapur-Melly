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
 * Cek semua kemungkinan session variable
 */
$user_id = null;
$possible_user_ids = ['id', 'user_id', 'userid', 'user'];

foreach ($possible_user_ids as $key) {
    if (isset($_SESSION[$key])) {
        $user_id = $_SESSION[$key];
        break;
    }
}

// Debug: Lihat semua session variables
error_log("DEBUG PENGATURAN: Session variables available:");
foreach ($_SESSION as $key => $value) {
    error_log("  $key => $value");
}

if (!$user_id) {
    die("<div style='padding: 20px; text-align: center;'><h3>Session tidak valid</h3><p>Silakan login kembali.</p></div>");
}

$nama_user = $_SESSION['nama_lengkap'] ?? 'User';
$role_user = $_SESSION['role'] ?? 'user';

/**
 * AMBIL DATA USER DAN KARYAWAN
 */
$query_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
if (!$query_user) {
    die("<div style='padding: 20px; text-align: center;'><h3>Error Query: " . mysqli_error($conn) . "</h3></div>");
}

$user_count = mysqli_num_rows($query_user);
error_log("DEBUG: Found $user_count users with id = $user_id");

if ($user_count == 0) {
    // Coba cari berdasarkan username jika id tidak cocok
    if (isset($_SESSION['username'])) {
        $username = $_SESSION['username'];
        $query_user_by_username = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
        if ($query_user_by_username && mysqli_num_rows($query_user_by_username) > 0) {
            $data_user = mysqli_fetch_assoc($query_user_by_username);
            $user_id = $data_user['id'];
            $_SESSION['id'] = $user_id;
            error_log("DEBUG: Found user by username. New user_id: $user_id");
            
            // Update query dengan user_id yang benar
            $query_user = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
            $user_count = mysqli_num_rows($query_user);
        }
    }
}

if ($user_count == 0) {
    echo "<div style='padding: 20px; text-align: center; max-width: 600px; margin: 50px auto; background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);'>";
    echo "<h3 style='color: #ff5f9e; margin-bottom: 20px;'><i class='fas fa-user-slash'></i> Data User Tidak Ditemukan</h3>";
    echo "<p style='margin-bottom: 20px;'>User ID dalam session: <strong>$user_id</strong></p>";
    echo "<p style='margin-bottom: 20px;'>Username dalam session: <strong>" . ($_SESSION['username'] ?? 'Tidak ada') . "</strong></p>";
    echo "<p style='margin-bottom: 30px;'>Silakan logout dan login kembali dengan akun yang valid.</p>";
    echo "<a href='../logout.php' class='btn' style='background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: white; padding: 10px 30px; border-radius: 10px; text-decoration: none; font-weight: bold;'>";
    echo "<i class='fas fa-sign-out-alt'></i> Logout & Login Kembali";
    echo "</a>";
    echo "</div>";
    exit;
}

$data_user = mysqli_fetch_assoc($query_user);

// Update nama_user dari database jika ada perbedaan
if (empty($nama_user) || $nama_user == 'User') {
    $nama_user = $data_user['nama_lengkap'] ?? 'User';
    $_SESSION['nama_lengkap'] = $nama_user;
}

$query_karyawan = mysqli_query($conn, "SELECT * FROM karyawan WHERE user_id = '$user_id'");
$data_karyawan = mysqli_fetch_assoc($query_karyawan);

// Inisialisasi variabel
$email = $data_user['email'] ?? '';
$foto_profil = $data_user['foto_profil'] ?? 'default.png';
$no_telp = $data_karyawan['no_telp'] ?? '';
$alamat = $data_karyawan['alamat'] ?? '';
$posisi = $data_karyawan['posisi'] ?? 'Belum diatur';
$tgl_masuk = $data_karyawan['tgl_masuk'] ?? 'Belum diatur';
if ($tgl_masuk != 'Belum diatur' && $tgl_masuk != '0000-00-00') {
    $tgl_masuk = date('d F Y', strtotime($tgl_masuk));
}

// Pesan sukses/error
$message = '';
$message_type = ''; // success, danger, warning

/**
 * PROSES UPDATE PROFIL
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profil'])) {
        // Update data profil
        $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp']);
        $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
        
        // Update tabel users
        $update_user = mysqli_query($conn, "UPDATE users SET nama_lengkap = '$nama_lengkap', email = '$email' WHERE id = '$user_id'");
        
        // Update tabel karyawan
        // Cek apakah data karyawan sudah ada
        if ($data_karyawan) {
            $update_karyawan = mysqli_query($conn, "UPDATE karyawan SET no_telp = '$no_telp', alamat = '$alamat' WHERE user_id = '$user_id'");
        } else {
            // Jika belum ada, insert baru
            $update_karyawan = mysqli_query($conn, "INSERT INTO karyawan (user_id, no_telp, alamat) VALUES ('$user_id', '$no_telp', '$alamat')");
        }
        
        if ($update_user) {
            $message = "Profil berhasil diperbarui!";
            $message_type = "success";
            $_SESSION['nama_lengkap'] = $nama_lengkap;
            $nama_user = $nama_lengkap;
        } else {
            $message = "Gagal memperbarui profil: " . mysqli_error($conn);
            $message_type = "danger";
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
        
        // Validasi ekstensi file
        if (!in_array($file_ext, $allowed_extensions)) {
            $message = "Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.";
            $message_type = "danger";
        } 
        // Validasi ukuran file (max 2MB)
        elseif ($file_size > 2097152) {
            $message = "Ukuran file terlalu besar. Maksimal 2MB.";
            $message_type = "danger";
        } else {
            // Generate nama file unik
            $new_file_name = "profile_" . $user_id . "_" . time() . "." . $file_ext;
            $upload_path = "../assets/img/profile/" . $new_file_name;
            
            // Pastikan folder ada
            if (!file_exists("../assets/img/profile/")) {
                mkdir("../assets/img/profile/", 0777, true);
            }
            
            // Hapus foto lama jika bukan default
            if ($foto_profil != 'default.png' && file_exists("../assets/" . $foto_profil)) {
                unlink("../assets/" . $foto_profil);
            } elseif ($foto_profil != 'default.png' && file_exists("../assets/img/profile/" . $foto_profil)) {
                unlink("../assets/img/profile/" . $foto_profil);
            }
            
            // Upload file baru
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Update database
                $update_foto = mysqli_query($conn, "UPDATE users SET foto_profil = '$new_file_name' WHERE id = '$user_id'");
                if ($update_foto) {
                    $message = "Foto profil berhasil diperbarui!";
                    $message_type = "success";
                    $foto_profil = $new_file_name;
                } else {
                    $message = "Gagal menyimpan informasi foto ke database.";
                    $message_type = "danger";
                }
            } else {
                $message = "Gagal mengupload foto.";
                $message_type = "danger";
            }
        }
    }
    
    /**
     * PROSES GANTI PASSWORD
     */
    if (isset($_POST['ganti_password'])) {
        $password_lama = $_POST['password_lama'];
        $password_baru = $_POST['password_baru'];
        $konfirmasi_password = $_POST['konfirmasi_password'];
        
        // Verifikasi password lama
        $check_password = mysqli_query($conn, "SELECT password FROM users WHERE id = '$user_id'");
        $user_data = mysqli_fetch_assoc($check_password);
        
        // Cek apakah password di database plain text atau hash
        $password_match = false;
        if (isset($user_data['password'])) {
            // Cek jika password di-hash
            if (password_verify($password_lama, $user_data['password'])) {
                $password_match = true;
            }
            // Cek jika password plain text (untuk backward compatibility)
            elseif ($password_lama == $user_data['password']) {
                $password_match = true;
            }
        }
        
        if ($password_match) {
            // Validasi password baru
            if (strlen($password_baru) < 6) {
                $message = "Password baru minimal 6 karakter.";
                $message_type = "danger";
            } elseif ($password_baru != $konfirmasi_password) {
                $message = "Konfirmasi password tidak cocok.";
                $message_type = "danger";
            } else {
                // Hash password baru
                $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $update_password = mysqli_query($conn, "UPDATE users SET password = '$password_hash' WHERE id = '$user_id'");
                
                if ($update_password) {
                    $message = "Password berhasil diubah!";
                    $message_type = "success";
                } else {
                    $message = "Gagal mengubah password: " . mysqli_error($conn);
                    $message_type = "danger";
                }
            }
        } else {
            $message = "Password lama salah.";
            $message_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan Akun - Dapur Melly</title>

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

/* ===================== */
/* CARDS */
/* ===================== */
.card-custom {
    border: none;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    background: white;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
    margin-bottom: 30px;
}

.card-custom:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(255, 154, 158, 0.2);
}

.card-header-custom {
    background: var(--pink-gradient);
    color: white;
    border-radius: 20px 20px 0 0 !important;
    padding: 20px 25px;
    border-bottom: none;
}

.card-header-custom h3 {
    margin: 0;
    font-weight: 600;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* ===================== */
/* PROFILE PHOTO */
/* ===================== */
.profile-photo-container {
    text-align: center;
    padding: 30px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    margin-bottom: 25px;
}

.profile-photo {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 6px solid white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    transition: transform 0.3s ease;
}

.profile-photo:hover {
    transform: scale(1.05);
}

/* ===================== */
/* FORM STYLES */
/* ===================== */
.form-label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
}

.form-control-custom {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 12px 18px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control-custom:focus {
    border-color: #ff9a9e;
    box-shadow: 0 0 0 0.25rem rgba(255, 154, 158, 0.25);
}

/* ===================== */
/* BUTTONS */
/* ===================== */
.btn-custom {
    background: var(--pink-gradient);
    border: none;
    color: white;
    font-weight: 600;
    padding: 12px 30px;
    border-radius: 12px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-custom:hover {
    background: linear-gradient(135deg, #ff8a9e 0%, #fec0ef 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 154, 158, 0.3);
}

.btn-custom-secondary {
    background: #6c757d;
    border: none;
    color: white;
    font-weight: 600;
    padding: 12px 30px;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.btn-custom-secondary:hover {
    background: #5a6268;
    color: white;
    transform: translateY(-2px);
}

/* ===================== */
/* READ-ONLY INFO */
/* ===================== */
.readonly-info {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px dashed #dee2e6;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--text-light);
}

.info-value {
    color: var(--text-dark);
    font-weight: 500;
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
    
    .profile-photo {
        width: 120px;
        height: 120px;
    }
}

@media (max-width: 576px) {
    .card-custom {
        border-radius: 15px;
    }
    
    .btn-custom {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .form-control-custom {
        padding: 10px 15px;
    }
}
</style>
</head>

<body>

<!-- INCLUDE SIDEBAR -->
<?php include '../includes/sidebar.php'; ?>

<!-- DEBUG INFO PANEL (Bisa diaktifkan jika perlu) -->
<?php if (false): // Set true untuk debug ?>
<div style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 12px; z-index: 9999;">
    <strong>Debug Info:</strong><br>
    User ID: <?= $user_id ?><br>
    Nama: <?= htmlspecialchars($nama_user) ?><br>
    Email: <?= htmlspecialchars($email) ?><br>
    Username: <?= htmlspecialchars($data_user['username'] ?? '') ?>
</div>
<?php endif; ?>

<!-- MAIN CONTENT -->
<div class="main-content">

<!-- PAGE HEADER -->
<div class="page-header">
    <h1><i class="fas fa-user-cog"></i> Pengaturan Akun</h1>
    <p>Kelola informasi profil, foto, dan keamanan akun Anda</p>
</div>

<!-- ALERT MESSAGE -->
<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?> alert-custom alert-dismissible fade show" role="alert">
    <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
    <div>
        <strong><?= $message_type == 'success' ? 'Berhasil!' : 'Perhatian!' ?></strong> <?= $message ?>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- COLUMN KIRI: FOTO PROFIL DAN INFO -->
    <div class="col-lg-4 col-md-5 mb-4">
        <div class="card-custom">
            <div class="card-body">
                <!-- FOTO PROFIL -->
                <div class="profile-photo-container">
                    <?php 
                    // Cek foto di beberapa lokasi yang mungkin
                    $foto_paths = [
                        "../assets/img/profile/" . $foto_profil,
                        "../assets/" . $foto_profil,
                        "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=ff9a9e&color=fff&size=150"
                    ];
                    
                    $foto_ditemukan = false;
                    foreach ($foto_paths as $path) {
                        if (strpos($path, 'http') === 0 || file_exists($path)) {
                            $foto_ditemukan = true;
                            ?>
                            <img src="<?= $path ?>" class="profile-photo" alt="Foto Profil" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=ff9a9e&color=fff&size=150'">
                            <?php
                            break;
                        }
                    }
                    
                    if (!$foto_ditemukan): ?>
                        <div class="profile-photo" style="background: var(--pink-gradient); display: flex; align-items: center; justify-content: center; color: white; font-size: 60px; font-weight: bold;">
                            <?= strtoupper(substr($nama_user, 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <h4 class="mt-3"><?= htmlspecialchars($nama_user) ?></h4>
                    <p class="text-muted"><?= ucfirst($role_user) ?> • Dapur Melly</p>
                </div>
                
                <!-- FORM UPLOAD FOTO -->
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Ganti Foto Profil</label>
                        <input type="file" class="form-control form-control-custom" name="foto_profil" accept="image/*">
                        <div class="form-text">Format: JPG, PNG, GIF. Maksimal 2MB</div>
                    </div>
                    <button type="submit" name="upload_foto" class="btn btn-custom w-100">
                        <i class="fas fa-upload"></i> Upload Foto
                    </button>
                </form>
            </div>
        </div>
        
        <!-- INFO READ-ONLY -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3><i class="fas fa-info-circle"></i> Informasi Karyawan</h3>
            </div>
            <div class="card-body">
                <div class="readonly-info">
                    <div class="info-item">
                        <span class="info-label">ID Karyawan</span>
                        <span class="info-value">#<?= $data_karyawan['id_karyawan'] ?? '-' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Posisi</span>
                        <span class="info-value"><?= htmlspecialchars($posisi) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tanggal Masuk</span>
                        <span class="info-value"><?= $tgl_masuk ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            <span class="badge bg-success">Aktif</span>
                        </span>
                    </div>
                </div>
                <p class="text-muted small">
                    <i class="fas fa-lock"></i> Informasi ini hanya dapat diubah oleh admin
                </p>
            </div>
        </div>
    </div>
    
    <!-- COLUMN KANAN: FORM PROFIL DAN PASSWORD -->
    <div class="col-lg-8 col-md-7">
        <!-- FORM UPDATE PROFIL -->
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <h3><i class="fas fa-user-edit"></i> Informasi Profil</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-custom" name="nama_lengkap" 
                                   value="<?= htmlspecialchars($nama_user) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-custom" name="email" 
                                   value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="tel" class="form-control form-control-custom" name="no_telp" 
                                   value="<?= htmlspecialchars($no_telp) ?>" placeholder="Contoh: 081234567890">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alamat</label>
                            <input type="text" class="form-control form-control-custom" name="alamat" 
                                   value="<?= htmlspecialchars($alamat) ?>" placeholder="Alamat lengkap">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control form-control-custom" 
                                   value="<?= htmlspecialchars($data_user['username'] ?? '') ?>" readonly>
                            <div class="form-text">Username tidak dapat diubah untuk menjaga konsistensi sistem</div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" name="update_profil" class="btn btn-custom">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <button type="reset" class="btn btn-custom-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- FORM GANTI PASSWORD -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3><i class="fas fa-lock"></i> Keamanan Akun</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="formPassword">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-custom" 
                                       name="password_lama" id="password_lama" required>
                                <button class="btn btn-outline-secondary" type="button" 
                                        onclick="togglePassword('password_lama')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-custom" 
                                       name="password_baru" id="password_baru" required>
                                <button class="btn btn-outline-secondary" type="button" 
                                        onclick="togglePassword('password_baru')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimal 6 karakter</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-custom" 
                                       name="konfirmasi_password" id="konfirmasi_password" required>
                                <button class="btn btn-outline-secondary" type="button" 
                                        onclick="togglePassword('konfirmasi_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kekuatan Password</label>
                            <div class="password-strength mt-2">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" id="passwordStrength" 
                                         role="progressbar" style="width: 0%"></div>
                                </div>
                                <small id="passwordFeedback" class="form-text"></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info alert-custom">
                        <i class="fas fa-lightbulb"></i>
                        <div>
                            <strong>Tips keamanan:</strong> Gunakan kombinasi huruf besar, kecil, angka, dan simbol untuk password yang kuat.
                        </div>
                    </div>
                    
                    <button type="submit" name="ganti_password" class="btn btn-custom">
                        <i class="fas fa-key"></i> Ganti Password
                    </button>
                </form>
            </div>
        </div>
        
        <!-- INFO SISTEM -->
        <div class="card-custom mt-4">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded" style="background: #f8f9fa;">
                            <i class="fas fa-calendar-alt fa-2x mb-3" style="color: #ff9a9e;"></i>
                            <h5>Terdaftar Sejak</h5>
                            <p class="mb-0">
                                <?= date('d F Y', strtotime($data_user['created_at'] ?? date('Y-m-d'))) ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded" style="background: #f8f9fa;">
                            <i class="fas fa-clock fa-2x mb-3" style="color: #ff9a9e;"></i>
                            <h5>Login Terakhir</h5>
                            <p class="mb-0">
                                <?= $data_user['last_login'] ? date('d F Y H:i', strtotime($data_user['last_login'])) : 'Belum pernah' ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded" style="background: #f8f9fa;">
                            <i class="fas fa-shield-alt fa-2x mb-3" style="color: #ff9a9e;"></i>
                            <h5>Status Akun</h5>
                            <p class="mb-0">
                                <span class="badge bg-success">Aman</span>
                            </p>
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

// Fungsi check password strength
document.getElementById('password_baru').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    const feedback = document.getElementById('passwordFeedback');
    
    let strength = 0;
    let feedbackText = '';
    
    if (password.length >= 8) strength += 25;
    if (/[a-z]/.test(password)) strength += 25;
    if (/[A-Z]/.test(password)) strength += 25;
    if (/[0-9]/.test(password)) strength += 25;
    
    // Update progress bar
    strengthBar.style.width = strength + '%';
    
    // Update color and text based on strength
    if (strength < 50) {
        strengthBar.className = 'progress-bar bg-danger';
        feedbackText = 'Password lemah';
    } else if (strength < 75) {
        strengthBar.className = 'progress-bar bg-warning';
        feedbackText = 'Password cukup';
    } else {
        strengthBar.className = 'progress-bar bg-success';
        feedbackText = 'Password kuat';
    }
    
    feedback.textContent = feedbackText;
});

// Validasi form password
document.getElementById('formPassword').addEventListener('submit', function(e) {
    const passwordBaru = document.getElementById('password_baru').value;
    const konfirmasi = document.getElementById('konfirmasi_password').value;
    
    if (passwordBaru !== konfirmasi) {
        e.preventDefault();
        Swal.fire({
            title: 'Password Tidak Cocok',
            text: 'Password baru dan konfirmasi password tidak cocok.',
            icon: 'error',
            confirmButtonColor: '#ff9a9e'
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

// Confirm before submitting password change
document.querySelector('button[name="ganti_password"]').addEventListener('click', function(e) {
    const form = document.getElementById('formPassword');
    const passwordLama = document.getElementById('password_lama').value;
    
    if (!passwordLama) {
        e.preventDefault();
        Swal.fire({
            title: 'Password Lama Diperlukan',
            text: 'Silakan masukkan password lama Anda.',
            icon: 'warning',
            confirmButtonColor: '#ff9a9e'
        });
    }
});
</script>
</body>
</html>