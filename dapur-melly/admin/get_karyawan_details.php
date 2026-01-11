<?php
require '../config/koneksi.php';

$id_karyawan = $_GET['id'] ?? 0;

if (!$id_karyawan) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

$query = mysqli_query($conn, "
    SELECT 
        posisi, 
        no_telp, 
        gaji_pokok, 
        tgl_gajian_rutin,
        lama_bekerja,
        alamat
    FROM karyawan 
    WHERE id_karyawan = '$id_karyawan'
");

if (mysqli_num_rows($query) > 0) {
    $data = mysqli_fetch_assoc($query);
    echo json_encode([
        'success' => true,
        'posisi' => $data['posisi'] ?? '',
        'no_telp' => $data['no_telp'] ?? '',
        'gaji_pokok' => $data['gaji_pokok'] ?? '0',
        'tgl_gajian_rutin' => $data['tgl_gajian_rutin'] ?? '',
        'lama_bekerja' => $data['lama_bekerja'] ?? '0',
        'alamat' => $data['alamat'] ?? ''
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
}
?>