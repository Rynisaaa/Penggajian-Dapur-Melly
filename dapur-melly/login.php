<?php
session_start();
require_once __DIR__ . '/config/koneksi.php';

$error = '';
$loginSuccess = false;
$redirectUrl = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id, username, password, role, nama_lengkap
            FROM users
            WHERE username = ? LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($res)) {

        $passwordValid = false;

        // 1. Password hash
        if (password_verify($password, $user['password'])) {
            $passwordValid = true;
        }
        // 2. Password plaintext (legacy)
        elseif ($password === $user['password']) {
            $passwordValid = true;

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateSql = "UPDATE users SET password = ? WHERE id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($updateStmt, 'si', $hashedPassword, $user['id']);
            mysqli_stmt_execute($updateStmt);
        }
        // 3. Password admin khusus
        elseif ($username === 'admin' && $password === 'adminmelly26') {
            $passwordValid = true;

            $hashedPassword = password_hash('adminmelly26', PASSWORD_DEFAULT);
            $updateSql = "UPDATE users SET password = ? WHERE username = 'admin'";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($updateStmt, 's', $hashedPassword);
            mysqli_stmt_execute($updateStmt);
        }
        // 4. Password owner khusus
        elseif ($username === 'owner' && $password === 'owner123') {
            $passwordValid = true;

            $hashedPassword = password_hash('owner123', PASSWORD_DEFAULT);
            $updateSql = "UPDATE users SET password = ? WHERE username = 'owner'";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($updateStmt, 's', $hashedPassword);
            mysqli_stmt_execute($updateStmt);
        }

        if ($passwordValid) {

            /* =============================
               SET SESSION UMUM
               ============================= */
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role']         = $user['role'];

            if ($user['role'] === 'admin') {
                $_SESSION['admin'] = $user['username'];
            }

            /* =============================
               TAMBAHAN PENTING:
               SET id_karyawan KHUSUS USER
               ============================= */
            if ($user['role'] === 'user') {

                $stmtKaryawan = $conn->prepare("
                    SELECT id_karyawan 
                    FROM karyawan 
                    WHERE user_id = ?
                    LIMIT 1
                ");
                $stmtKaryawan->bind_param("i", $user['id']);
                $stmtKaryawan->execute();
                $resKaryawan = $stmtKaryawan->get_result();

                if ($dataKaryawan = $resKaryawan->fetch_assoc()) {
                    $_SESSION['id_karyawan'] = $dataKaryawan['id_karyawan'];
                } else {
                    die("Akun user belum terhubung ke data karyawan.");
                }
            }

            /* =============================
               REDIRECT SESUAI ROLE
               ============================= */
            switch ($user['role']) {
                case 'admin':
                    $redirectUrl = "admin/dashboard.php";
                    break;
                case 'owner':
                    $redirectUrl = "owner/dashboard.php";
                    break;
                case 'user':
                    $redirectUrl = "user/dashboard.php";
                    break;
                default:
                    $redirectUrl = "index.php";
            }

            header("Location: " . $redirectUrl);
            exit();

        } else {
            $error = "Password salah.";
        }

    } else {
        $error = "Username tidak ditemukan!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dapur Melly</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            height: 100vh;
            background: linear-gradient(135deg, #ffd5c8, #ffb7a4);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .logo-bubble {
            position: absolute;
            width: 120px;
            height: 120px;
            background: url('assets/logodapurmelly.jpeg') center/cover no-repeat;
            opacity: .25;
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .small { width: 80px; height: 80px; }
        .large { width: 150px; height: 150px; }

        @keyframes float {
            0% { transform: translateY(0); }
            50% { transform: translateY(-30px); }
            100% { transform: translateY(0); }
        }

        .login-container {
            width: 400px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(18px);
            padding: 40px;
            border-radius: 25px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }

        .login-logo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background: url('assets/logodapurmelly.jpeg') center/cover no-repeat;
            border: 5px solid white;
        }

        .login-container h2 { margin-bottom: 10px; }
        .login-container p { margin-bottom: 30px; }

        .form-group { margin-bottom: 20px; text-align: left; }

        input {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: 2px solid #eee;
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ff7676, #ff5c5c);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .error-message {
            background: #ff4e4e;
            color: white;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="login-container animate__animated animate__fadeIn">
    <div class="login-logo"></div>
    <h2>Dapur Melly</h2>
    <p>Sistem Penggajian Karyawan</p>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit" name="login" class="login-btn">Login</button>
    </form>
</div>

</body>
</html>
