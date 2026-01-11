<?php
session_start();
require 'config/koneksi.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $koneksi->prepare(
    "SELECT id, username, password, role, nama_lengkap 
     FROM users 
     WHERE username = ?"
);

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    /*
    =====================================================
    MODE TESTING (PASSWORD TEKS BIASA)
    =====================================================
    JIKA DATABASE MASIH PLAINTEXT
    */
    if ($password === $user['password']) {

        /*
        =====================================================
        MODE PRODUKSI (AKTIFKAN NANTI)
        =====================================================
        if (password_verify($password, $user['password'])) {
        */

        $_SESSION['id_user'] = $user['id'];
        $_SESSION['nama']    = $user['nama_lengkap'];
        $_SESSION['role']    = $user['role'];

        if ($user['role'] === 'admin') {
            header("Location: admin/dashboard.php");
        } elseif ($user['role'] === 'owner') {
            header("Location: owner/dashboard.php");
        } elseif ($user['role'] === 'user') {
            header("Location: user/dashboard.php");
        } else {
            header("Location: index.php");
        }
        exit;
    }
}

echo "<script>
    alert('Username atau password salah!');
    window.location='index.php';
</script>";
