<?php
session_start();
require_once '../config/database.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Ambil id_karyawan
$query_karyawan = "SELECT k.id_karyawan, k.posisi 
                   FROM karyawan k 
                   WHERE k.user_id = ?";
$stmt_karyawan = $conn->prepare($query_karyawan);
$stmt_karyawan->bind_param("i", $user_id);
$stmt_karyawan->execute();
$result_karyawan = $stmt_karyawan->get_result();

if ($result_karyawan->num_rows == 0) {
    die("Data karyawan tidak ditemukan!");
}

$karyawan = $result_karyawan->fetch_assoc();
$id_karyawan = $karyawan['id_karyawan'];
$posisi = $karyawan['posisi'];

// Waktu sekarang
$tanggal_sekarang = date('Y-m-d');
$datetime_sekarang = date('Y-m-d H:i:s');

// Cek apakah sudah absen hari ini
$query_cek = "SELECT id FROM presensi 
              WHERE id_karyawan = ? AND tanggal = ?";
$stmt_cek = $conn->prepare($query_cek);
$stmt_cek->bind_param("is", $id_karyawan, $tanggal_sekarang);
$stmt_cek->execute();
$result_cek = $stmt_cek->get_result();

if ($result_cek->num_rows > 0) {
    // Update absen yang sudah ada
    $presensi = $result_cek->fetch_assoc();
    $query = "UPDATE presensi 
              SET jam_masuk = ?, 
                  status = 'masuk'
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $datetime_sekarang, $presensi['id']);
} else {
    // Insert baru
    $query = "INSERT INTO presensi 
              (id_karyawan, tanggal, jam_masuk, status) 
              VALUES (?, ?, ?, 'masuk')";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $id_karyawan, $tanggal_sekarang, $datetime_sekarang);
}

// Eksekusi
if ($stmt->execute()) {
    // Catat di login_history
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $device_info = "Absen Masuk - Posisi: " . $posisi;
    
    $query_history = "INSERT INTO login_history 
                      (user_id, login_time, ip_address, device_info) 
                      VALUES (?, ?, ?, ?)";
    $stmt_history = $conn->prepare($query_history);
    $stmt_history->bind_param("isss", $user_id, $datetime_sekarang, $ip_address, $device_info);
    $stmt_history->execute();
    
    $_SESSION['success_message'] = "✅ Absen berhasil! Jam: " . date('H:i', strtotime($datetime_sekarang));
} else {
    $_SESSION['error_message'] = "❌ Gagal absen: " . $conn->error;
}

$stmt->close();
$conn->close();

header('Location: dashboard.php');
exit();
?>