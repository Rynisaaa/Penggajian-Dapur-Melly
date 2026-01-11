<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$id_karyawan   = $_POST['id_karyawan'] ?? '';
$user_id       = $_POST['user_id'] ?? $_POST['user_id_hidden'] ?? '';
$nama          = mysqli_real_escape_string($conn,$_POST['nama_lengkap'] ?? '');
$posisi        = mysqli_real_escape_string($conn,$_POST['posisi'] ?? '');
$no_telp       = mysqli_real_escape_string($conn,$_POST['no_telp'] ?? '');
$gaji_pokok    = $_POST['gaji_pokok'] ?? 0;
$lama_bekerja  = $_POST['lama_bekerja'] ?? 0;
$tgl_gajian_rutin = $_POST['tgl_gajian_rutin'] ?? null;
$tgl_gajian    = $_POST['tgl_gajian'] ?? null;

// Validasi input
if (empty($user_id)) {
    $_SESSION['error'] = "User harus dipilih!";
    header("Location: data_karyawan.php");
    exit;
}

if (empty($posisi)) {
    $_SESSION['error'] = "Posisi harus diisi!";
    header("Location: data_karyawan.php");
    exit;
}

// Update nama user jika berubah
if (!empty($nama)) {
    mysqli_query($conn, "UPDATE users SET nama_lengkap = '$nama' WHERE id = '$user_id'");
}

mysqli_begin_transaction($conn);

try {
    /* TAMBAH KARYAWAN BARU */
    if (empty($id_karyawan)) {
        // Cek apakah user sudah menjadi karyawan
        $cekUser = mysqli_query($conn, "SELECT id_karyawan FROM karyawan WHERE user_id = '$user_id'");
        if (mysqli_num_rows($cekUser) > 0) {
            throw new Exception("User ini sudah terdaftar sebagai karyawan!");
        }

        $query = "INSERT INTO karyawan
                (user_id, posisi, no_telp, gaji_pokok, lama_bekerja, 
                 tgl_gajian_rutin, tgl_gajian, tgl_masuk)
                VALUES
                ('$user_id','$posisi','$no_telp','$gaji_pokok','$lama_bekerja',
                 " . ($tgl_gajian_rutin ? "'$tgl_gajian_rutin'" : "NULL") . ",
                 " . ($tgl_gajian ? "'$tgl_gajian'" : "NULL") . ",
                 CURDATE())";
        
        if (!mysqli_query($conn, $query)) {
            throw new Exception("Gagal menambah karyawan: " . mysqli_error($conn));
        }
        
        $_SESSION['success'] = "Karyawan berhasil ditambahkan!";
    }
    /* EDIT KARYAWAN */
    else {
        $query = "UPDATE karyawan SET
                posisi = '$posisi',
                no_telp = '$no_telp',
                gaji_pokok = '$gaji_pokok',
                lama_bekerja = '$lama_bekerja',
                tgl_gajian_rutin = " . ($tgl_gajian_rutin ? "'$tgl_gajian_rutin'" : "NULL") . ",
                tgl_gajian = " . ($tgl_gajian ? "'$tgl_gajian'" : "NULL") . "
                WHERE id_karyawan = '$id_karyawan'";
        
        if (!mysqli_query($conn, $query)) {
            throw new Exception("Gagal mengupdate karyawan: " . mysqli_error($conn));
        }
        
        $_SESSION['success'] = "Data karyawan berhasil diperbarui!";
    }
    
    mysqli_commit($conn);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
}

header("Location: data_karyawan.php");
exit;
?>