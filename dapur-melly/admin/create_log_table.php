<?php
require '../config/koneksi.php';

$sql = "CREATE TABLE IF NOT EXISTS log_whatsapp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_penggajian INT NOT NULL,
    no_telp VARCHAR(20) NOT NULL,
    pesan TEXT,
    status VARCHAR(10),
    response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_penggajian) REFERENCES penggajian(id) ON DELETE CASCADE
)";

if (mysqli_query($conn, $sql)) {
    echo "Tabel log_whatsapp berhasil dibuat!";
} else {
    echo "Error: " . mysqli_error($conn);
}