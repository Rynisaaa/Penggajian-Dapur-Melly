<?php
require '../config/koneksi.php';

// Test query
$sql = "INSERT INTO users 
        (nama_lengkap, username, password, role, created_at)
        VALUES ('Test', 'testuser', '123456', 'user', NOW())";

echo "SQL: " . $sql . "<br><br>";

if (mysqli_query($conn, $sql)) {
    echo "✅ Berhasil insert!";
} else {
    echo "❌ Error: " . mysqli_error($conn) . "<br>";
    
    // Cek kolom
    echo "<br>Struktur tabel users:<br>";
    $result = mysqli_query($conn, "DESCRIBE users");
    while($row = mysqli_fetch_assoc($result)) {
        echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . "<br>";
    }
}
?>