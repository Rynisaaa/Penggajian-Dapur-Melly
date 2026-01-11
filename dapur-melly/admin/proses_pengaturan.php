<?php
session_start();
require '../config/koneksi.php';

header('Content-Type: application/json');

/* =========================
   CEK LOGIN ADMIN
========================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update_profile':
        updateProfile($conn);
        break;

    case 'change_password':
        changePassword($conn);
        break;

    case 'send_message':
        sendMessage($conn);
        break;

    case 'set_target':
        setTarget($conn);
        break;

    case 'download_laporan':
        downloadLaporan($conn);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
}

/* =========================
   UPDATE PROFILE (FIXED)
========================= */
function updateProfile($conn) {

    // 🔐 AMAN: ambil user_id dari session
    $user_id      = $_SESSION['user_id'];
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $whatsapp     = trim($_POST['whatsapp'] ?? '');

    if (!$nama_lengkap || !$username) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        return;
    }

    // Cek username unik (kecuali milik sendiri)
    $cek = mysqli_prepare($conn,
        "SELECT id FROM users WHERE username = ? AND id != ?"
    );
    mysqli_stmt_bind_param($cek, "si", $username, $user_id);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);

    if (mysqli_stmt_num_rows($cek) > 0) {
        echo json_encode(['success' => false, 'message' => 'Username sudah digunakan']);
        return;
    }

    // Update tabel users
    $upd = mysqli_prepare($conn,
        "UPDATE users 
         SET nama_lengkap = ?, username = ?, email = ?
         WHERE id = ?"
    );
    mysqli_stmt_bind_param($upd, "sssi",
        $nama_lengkap,
        $username,
        $email,
        $user_id
    );

    if (!mysqli_stmt_execute($upd)) {
        echo json_encode(['success' => false, 'message' => 'Gagal update profil']);
        return;
    }

    // Update session
    $_SESSION['nama'] = $nama_lengkap;
    $_SESSION['username'] = $username;

    // Update / insert ke tabel karyawan
    $cekKar = mysqli_query($conn,
        "SELECT id_karyawan FROM karyawan WHERE user_id = '$user_id'"
    );

    if (mysqli_num_rows($cekKar) > 0) {
        $updKar = mysqli_prepare($conn,
            "UPDATE karyawan SET no_telp = ? WHERE user_id = ?"
        );
        mysqli_stmt_bind_param($updKar, "si", $whatsapp, $user_id);
        mysqli_stmt_execute($updKar);
    } else if ($whatsapp !== '') {
        $gaji = 0;
        $posisi = 'Admin';

        $insKar = mysqli_prepare($conn,
            "INSERT INTO karyawan (user_id, no_telp, gaji_pokok, posisi)
             VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($insKar, "isds",
            $user_id,
            $whatsapp,
            $gaji,
            $posisi
        );
        mysqli_stmt_execute($insKar);
    }

    echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui']);
}

/* =========================
   CHANGE PASSWORD
========================= */
function changePassword($conn) {
    $user_id = $_SESSION['user_id'];
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';

    if (!$current || !$new) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        return;
    }

    $q = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
    mysqli_stmt_bind_param($q, "i", $user_id);
    mysqli_stmt_execute($q);
    mysqli_stmt_bind_result($q, $hash);
    mysqli_stmt_fetch($q);
    mysqli_stmt_close($q);

    if (!password_verify($current, $hash)) {
        echo json_encode(['success' => false, 'message' => 'Password saat ini salah']);
        return;
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $upd = mysqli_prepare($conn,
        "UPDATE users SET password = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($upd, "si", $newHash, $user_id);
    mysqli_stmt_execute($upd);

    echo json_encode(['success' => true, 'message' => 'Password berhasil diubah']);
}

/* =========================
   SEND MESSAGE
========================= */
function sendMessage($conn) {
    $sid  = $_SESSION['user_id'];
    $name = $_SESSION['nama'];
    $msg  = trim($_POST['message'] ?? '');
    $role = $_POST['tujuan_role'] ?? 'owner';

    if (!$msg) {
        echo json_encode(['success' => false, 'message' => 'Pesan kosong']);
        return;
    }

    $ins = mysqli_prepare($conn,
        "INSERT INTO pesan_sistem (sender_id, sender_name, message, tujuan_role)
         VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($ins, "isss", $sid, $name, $msg, $role);
    mysqli_stmt_execute($ins);

    echo json_encode(['success' => true, 'message' => 'Pesan terkirim']);
}

/* =========================
   SET TARGET
========================= */
function setTarget($conn) {
    $bulan  = $_POST['bulan'] ?? '';
    $tahun  = $_POST['tahun'] ?? '';
    $amount = preg_replace('/[^0-9]/', '', $_POST['target_amount'] ?? '');

    if (!$bulan || !$tahun || !$amount) {
        echo json_encode(['success' => false, 'message' => 'Data target tidak lengkap']);
        return;
    }

    $cek = mysqli_prepare($conn,
        "SELECT id FROM target_pendapatan WHERE bulan = ? AND tahun = ?"
    );
    mysqli_stmt_bind_param($cek, "ss", $bulan, $tahun);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);

    if (mysqli_stmt_num_rows($cek) > 0) {
        $upd = mysqli_prepare($conn,
            "UPDATE target_pendapatan 
             SET target_amount = ? 
             WHERE bulan = ? AND tahun = ?"
        );
        mysqli_stmt_bind_param($upd, "dss", $amount, $bulan, $tahun);
        mysqli_stmt_execute($upd);
        echo json_encode(['success' => true, 'message' => 'Target diperbarui']);
    } else {
        $ins = mysqli_prepare($conn,
            "INSERT INTO target_pendapatan (bulan, tahun, target_amount)
             VALUES (?, ?, ?)"
        );
        mysqli_stmt_bind_param($ins, "ssd", $bulan, $tahun, $amount);
        mysqli_stmt_execute($ins);
        echo json_encode(['success' => true, 'message' => 'Target disimpan']);
    }
}
