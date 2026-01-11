<?php
session_start();
require_once __DIR__ . '/config/koneksi.php';

$error = '';
$loginSuccess = false;
$redirectUrl = '';
$namaUser = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, username, password, role, nama_lengkap
         FROM users WHERE username=? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($res)) {
        
        $passwordValid = false;
        
        // Method 1: Check hashed password
        if (password_verify($password, $user['password'])) {
            $passwordValid = true;
        }
        // Method 2: Check plaintext (for backward compatibility)
        elseif ($password === $user['password']) {
            $passwordValid = true;
            
            // Auto-update to hash for future logins
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE users SET password = '$hashedPassword' WHERE id = {$user['id']}");
        }

        if ($passwordValid) {
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role']         = $user['role'];

            // For compatibility with old system
            $_SESSION['nama'] = $user['nama_lengkap'];
            $_SESSION['id_user'] = $user['id'];

            $loginSuccess = true;
            $namaUser = $user['nama_lengkap'];

            // SET REDIRECT URL FOR ALL ROLES
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

        } else {
            $error = "Password salah";
        }

    } else {
        $error = "Username tidak ditemukan";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dapur Melly - Login</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    margin: 0;
    height: 100vh;
    background: linear-gradient(135deg, #ffd5c8, #ffb7a4);
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: 'Poppins', sans-serif;
    overflow: hidden;
}

/* BUBBLE BACKGROUND */
.logo-bubble {
    position: absolute;
    width: 120px;
    height: 120px;
    background: url('assets/logodapurmelly.jpeg') center/cover no-repeat;
    opacity: .25;
    border-radius: 50%;
    animation: float 6s ease-in-out infinite;
}

.small {
    width: 80px;
    height: 80px;
}

.large {
    width: 150px;
    height: 150px;
}

@keyframes float {
    0% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-30px) rotate(5deg);
    }
    100% {
        transform: translateY(0) rotate(0deg);
    }
}

/* CARDS */
.card {
    width: 400px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(15px);
    padding: 40px;
    border-radius: 25px;
    text-align: center;
    z-index: 10;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.4);
}

/* ONBOARDING PAGE */
.onboarding-logo {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: url('assets/logodapurmelly.jpeg') center/cover no-repeat;
    margin: 0 auto 25px;
    border: 6px solid white;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
}

#onboarding h2 {
    color: #4b2e1e;
    font-size: 32px;
    margin-bottom: 10px;
    font-weight: 700;
}

#onboarding p {
    color: #666;
    margin-bottom: 35px;
    font-size: 17px;
    line-height: 1.5;
}

/* LOGIN PAGE */
.login-logo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: url('assets/logodapurmelly.jpeg') center/cover no-repeat;
    margin: 0 auto 20px;
    border: 5px solid white;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

#loginBox h3 {
    color: #4b2e1e;
    font-size: 26px;
    margin-bottom: 8px;
    font-weight: 700;
}

#loginBox > p {
    color: #777;
    margin-bottom: 30px;
    font-size: 15px;
}

/* FORM ELEMENTS */
.input-with-icon {
    position: relative;
    margin-bottom: 20px;
}

.input-with-icon i {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #ff7676;
    font-size: 18px;
}

input {
    width: 100%;
    padding: 16px 20px 16px 50px;
    border: 2px solid #e6e6e6;
    border-radius: 12px;
    font-size: 16px;
    font-family: 'Poppins', sans-serif;
    background: rgba(255, 255, 255, 0.95);
    transition: all 0.3s;
    color: #333;
}

input:focus {
    outline: none;
    border-color: #ff7676;
    box-shadow: 0 0 0 4px rgba(255, 118, 118, 0.15);
    background: white;
}

input::placeholder {
    color: #999;
}

button {
    width: 100%;
    padding: 17px;
    background: linear-gradient(135deg, #ff7676, #ff5c5c);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 17px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-family: 'Poppins', sans-serif;
    margin-top: 10px;
    letter-spacing: 0.5px;
}

button:hover {
    background: linear-gradient(135deg, #ff5c5c, #ff4242);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 118, 118, 0.3);
}

button:active {
    transform: translateY(0);
}

button i {
    margin-right: 10px;
}

/* ERROR MESSAGE */
.error {
    background: linear-gradient(135deg, #ff4e4e, #ff3030);
    color: white;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    animation: shake 0.5s;
}

@keyframes shake {
    0%, 100% {
        transform: translateX(0);
    }
    10%, 30%, 50%, 70%, 90% {
        transform: translateX(-5px);
    }
    20%, 40%, 60%, 80% {
        transform: translateX(5px);
    }
}

/* HIDDEN CLASS */
.hidden {
    display: none;
}

/* SUCCESS OVERLAY */
.success-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.success-box {
    background: white;
    padding: 50px;
    border-radius: 25px;
    text-align: center;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
    max-width: 500px;
    width: 90%;
    animation: popIn 0.5s ease-out;
}

@keyframes popIn {
    0% {
        opacity: 0;
        transform: scale(0.8);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

.success-box h2 {
    color: #4b2e1e;
    margin-bottom: 15px;
    font-size: 34px;
}

.success-box h3 {
    color: #ff7676;
    margin-bottom: 15px;
    font-size: 26px;
    font-weight: 600;
}

.success-box p {
    color: #666;
    margin-bottom: 10px;
    font-size: 16px;
}

.success-box .progress-container {
    width: 100%;
    height: 6px;
    background: #eee;
    border-radius: 5px;
    margin-top: 25px;
    overflow: hidden;
}

.success-box .progress-bar {
    width: 0%;
    height: 100%;
    background: linear-gradient(135deg, #ff7676, #ff5c5c);
    border-radius: 5px;
    transition: width 2s ease-out;
}

/* RESPONSIVE DESIGN */
@media (max-width: 480px) {
    .card {
        width: 90%;
        padding: 30px 25px;
    }
    
    .onboarding-logo {
        width: 120px;
        height: 120px;
    }
    
    .login-logo {
        width: 90px;
        height: 90px;
    }
    
    #onboarding h2 {
        font-size: 28px;
    }
    
    #loginBox h3 {
        font-size: 24px;
    }
    
    .success-box {
        padding: 40px 25px;
    }
    
    .success-box h2 {
        font-size: 28px;
    }
    
    .success-box h3 {
        font-size: 22px;
    }
}
</style>
</head>

<body>

<!-- BUBBLE BACKGROUND -->
<div class="logo-bubble small" style="left:10%;top:10%"></div>
<div class="logo-bubble large" style="left:45%;top:5%"></div>
<div class="logo-bubble" style="left:80%;top:15%"></div>
<div class="logo-bubble small" style="left:15%;top:75%"></div>
<div class="logo-bubble large" style="left:70%;top:65%"></div>

<!-- ONBOARDING SCREEN -->
<div id="onboarding" class="card">
    <div class="onboarding-logo"></div>
    <h2 class="animate__animated animate__fadeIn animate__delay-1s">Dapur Melly</h2>
    <p class="animate__animated animate__fadeIn animate__delay-2s">
        Sistem Penggajian Karyawan
    </p>
    <button class="animate__animated animate__fadeInUp animate__delay-3s"
            onclick="showLogin()">
        <i class="fas fa-sign-in-alt"></i> Masuk ke Sistem
    </button>
</div>

<!-- LOGIN FORM -->
<div id="loginBox" class="card hidden">
    <div class="login-logo"></div>
    <h3>Login</h3>
    <p>Masukkan kredensial Anda</p>

    <?php if ($error): ?>
        <div class="error animate__animated animate__shakeX">
            <i class="fas fa-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
        <div class="input-with-icon">
            <i class="fas fa-user"></i>
            <input type="text" 
                   name="username" 
                   id="username"
                   placeholder="Username" 
                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                   required 
                   autocomplete="username">
        </div>
        
        <div class="input-with-icon">
            <i class="fas fa-lock"></i>
            <input type="password" 
                   name="password" 
                   id="password"
                   placeholder="Password" 
                   required 
                   autocomplete="current-password">
        </div>
        
        <button type="submit" id="loginButton">
            <i class="fas fa-sign-in-alt"></i> Login
        </button>
    </form>
</div>

<?php if ($loginSuccess): ?>
<!-- SUCCESS MESSAGE -->
<div class="success-overlay">
    <div class="success-box">
        <h2>🎉 Selamat Datang!</h2>
        <h3><?= htmlspecialchars($namaUser) ?></h3>
        <p>Login berhasil sebagai <strong><?= htmlspecialchars($_SESSION['role']) ?></strong></p>
        <p style="color:#888; font-size:14px; margin-top: 20px;">Mengarahkan ke dashboard...</p>
        <div class="progress-container">
            <div class="progress-bar" id="progressBar"></div>
        </div>
    </div>
</div>

<script>
// Animate progress bar
setTimeout(() => {
    document.getElementById('progressBar').style.width = '100%';
}, 100);

// Redirect after 2 seconds
setTimeout(() => {
    window.location.href = "<?= $redirectUrl ?>";
}, 2000);
</script>
<?php endif; ?>

<script>
// Show login form
function showLogin() {
    document.getElementById('onboarding').style.display = 'none';
    document.getElementById('loginBox').classList.remove('hidden');
    
    // Auto focus on username field
    setTimeout(() => {
        document.getElementById('username').focus();
    }, 300);
}

// Form submission enhancement
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');
    
    if (loginForm) {
        // Handle Enter key in password field
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loginButton.click();
            }
        });
        
        // Form submit handler
        loginForm.addEventListener('submit', function() {
            loginButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            loginButton.disabled = true;
        });
    }
    
    // If there's an error, show login form directly
    <?php if ($error): ?>
        showLogin();
        document.getElementById('password').focus();
    <?php endif; ?>
});

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>

</body>
</html>
