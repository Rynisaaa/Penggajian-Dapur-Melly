download_laporan.php:
<?php
session_start();

// Cek login admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$file = $_GET['file'] ?? '';
if (empty($file)) {
    die('File tidak ditemukan');
}

$filepath = __DIR__ . '/../laporan/' . basename($file);

if (!file_exists($filepath)) {
    die('File tidak ditemukan');
}

// Set headers untuk download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
header('Content-Length: ' . filesize($filepath));

// Output file
readfile($filepath);

// Optional: Hapus file setelah didownload
// unlink($filepath);
exit;