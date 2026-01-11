<?php
session_start();
require '../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'absen_masuk') {
    $id_karyawan = intval($_POST['id_karyawan']);
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    
    // Cek apakah sudah absen hari ini
    $check = mysqli_query($conn, "SELECT id FROM presensi WHERE id_karyawan = $id_karyawan AND tanggal = '$today'");
    
    if ($check && mysqli_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'Anda sudah absen hari ini']);
    } else {
        // Tentukan status (tepat waktu atau terlambat)
        $jam_sekarang = date('H:i');
        $status = ($jam_sekarang <= '09:00') ? 'masuk' : 'terlambat';
        
        $query = "INSERT INTO presensi (id_karyawan, tanggal, jam_masuk, status) 
                 VALUES ($id_karyawan, '$today', '$now', '$status')";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'time' => date('H:i', strtotime($now)), 'status' => $status]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>