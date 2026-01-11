<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Ambil data dari form
$id = $_POST['id'] ?? '';
$nama = mysqli_real_escape_string($conn, $_POST['nama_lengkap'] ?? '');
$username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
$role = mysqli_real_escape_string($conn, $_POST['role'] ?? '');
$password = $_POST['password'] ?? '';

// Data karyawan (jika role adalah user)
$posisi = mysqli_real_escape_string($conn, $_POST['posisi'] ?? '');
$no_telp = mysqli_real_escape_string($conn, $_POST['no_telp'] ?? '');
$gaji_pokok = $_POST['gaji_pokok'] ?? 0;
$tgl_gajian_rutin = $_POST['tgl_gajian_rutin'] ?? null;

$success = false;
$error_message = '';

// VALIDASI INPUT
if (empty($nama)) {
    $error_message = "Nama lengkap wajib diisi!";
} elseif (empty($username)) {
    $error_message = "Username wajib diisi!";
} elseif (empty($role)) {
    $error_message = "Role wajib dipilih!";
} elseif (empty($id) && empty($password)) {
    $error_message = "Password wajib diisi untuk user baru!";
} elseif (empty($id) && strlen($password) < 6) {
    $error_message = "Password minimal 6 karakter!";
}

// Jika ada error, langsung redirect
if (!empty($error_message)) {
    $_SESSION['error'] = $error_message;
    header("Location: manajemen_user.php");
    exit;
}

// PROSES SIMPAN DATA
mysqli_begin_transaction($conn);

try {
    if ($id) {
        // ========== UPDATE USER YANG SUDAH ADA ==========
        
        // 1. Cek apakah username sudah digunakan oleh user lain
        $checkUsername = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id != '$id'");
        if (mysqli_num_rows($checkUsername) > 0) {
            throw new Exception("Username '$username' sudah digunakan oleh user lain!");
        }
        
        // 2. Update data user
        if (!empty($password)) {
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET 
                    nama_lengkap = '$nama',
                    username = '$username',
                    password = '$pass_hash',
                    role = '$role'
                    WHERE id = '$id'";
        } else {
            $sql = "UPDATE users SET 
                    nama_lengkap = '$nama',
                    username = '$username',
                    role = '$role'
                    WHERE id = '$id'";
        }
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Gagal update user: " . mysqli_error($conn));
        }
        
        // 3. Update/Insert data karyawan berdasarkan role
        if ($role == 'user') {
            // Cek apakah sudah ada data karyawan
            $checkKaryawan = mysqli_query($conn, "SELECT id_karyawan FROM karyawan WHERE user_id = '$id'");
            
            if (mysqli_num_rows($checkKaryawan) > 0) {
                // Update data karyawan yang sudah ada
                $karyawan = mysqli_fetch_assoc($checkKaryawan);
                $id_karyawan = $karyawan['id_karyawan'];
                
                $updateKaryawan = "UPDATE karyawan SET
                    posisi = '$posisi',
                    no_telp = '$no_telp',
                    gaji_pokok = '$gaji_pokok',
                    tgl_gajian_rutin = " . ($tgl_gajian_rutin ? "'$tgl_gajian_rutin'" : "NULL") . "
                    WHERE id_karyawan = '$id_karyawan'";
                
                if (!mysqli_query($conn, $updateKaryawan)) {
                    throw new Exception("Gagal update data karyawan: " . mysqli_error($conn));
                }
            } else {
                // Insert data karyawan baru
                $insertKaryawan = "INSERT INTO karyawan 
                    (user_id, posisi, no_telp, gaji_pokok, tgl_gajian_rutin, tgl_masuk)
                    VALUES ('$id', '$posisi', '$no_telp', '$gaji_pokok', 
                    " . ($tgl_gajian_rutin ? "'$tgl_gajian_rutin'" : "NULL") . ",
                    CURDATE())";
                
                if (!mysqli_query($conn, $insertKaryawan)) {
                    throw new Exception("Gagal tambah data karyawan: " . mysqli_error($conn));
                }
            }
        } else {
            // Jika role diubah dari 'user' ke 'admin' atau 'owner', hapus data karyawan
            mysqli_query($conn, "DELETE FROM karyawan WHERE user_id = '$id'");
        }
        
        $success = true;
        $message = "User berhasil diperbarui!";
        
    } else {
        // ========== INSERT USER BARU ==========
        
        // 1. Cek apakah username sudah ada
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
        if (mysqli_num_rows($check) > 0) {
            throw new Exception("Username '$username' sudah terdaftar!");
        }
        
        // 2. Hash password
        $pass_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // 3. INSERT USER - VERSI SEDERHANA & AMAN
        // Hanya insert ke kolom yang pasti ada
        $sql = "INSERT INTO users 
                (nama_lengkap, username, password, role)
                VALUES ('$nama', '$username', '$pass_hash', '$role')";
        
        // Jika ingin tambah created_at (jika kolom ada dan mau diisi)
        // $sql = "INSERT INTO users 
        //         (nama_lengkap, username, password, role, created_at)
        //         VALUES ('$nama', '$username', '$pass_hash', '$role', NOW())";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception("Gagal tambah user: " . mysqli_error($conn));
        }
        
        $new_user_id = mysqli_insert_id($conn);
        
        // 4. Jika role adalah 'user', buat data karyawan
        if ($role == 'user') {
            $insertKaryawan = "INSERT INTO karyawan 
                (user_id, posisi, no_telp, gaji_pokok, tgl_gajian_rutin, tgl_masuk)
                VALUES ('$new_user_id', '$posisi', '$no_telp', '$gaji_pokok', 
                " . ($tgl_gajian_rutin ? "'$tgl_gajian_rutin'" : "NULL") . ",
                CURDATE())";
            
            if (!mysqli_query($conn, $insertKaryawan)) {
                throw new Exception("Gagal tambah data karyawan: " . mysqli_error($conn));
            }
        }
        
        $success = true;
        $message = "User baru berhasil ditambahkan!";
    }
    
    mysqli_commit($conn);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $error_message = $e->getMessage();
}

// SET SESSION MESSAGE
if ($success) {
    $_SESSION['success'] = $message;
} else {
    $_SESSION['error'] = $error_message ?: "Terjadi kesalahan yang tidak diketahui!";
}

header("Location: manajemen_user.php");
exit;
?>