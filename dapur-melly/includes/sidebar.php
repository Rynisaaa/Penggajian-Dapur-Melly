<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nama = $_SESSION['nama_lengkap'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
?>

<!-- FONT AWESOME -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

<style>
/* SIDEBAR WRAPPER */
.sidebar-wrapper {
    position: fixed;
    top: 0;
    left: -220px;
    width: 260px;
    height: 100vh;
    background: linear-gradient(180deg, #1f1f1f, #2a2a2a);
    color: #fff;
    transition: all 0.35s ease;
    z-index: 999;
    box-shadow: 4px 0 15px rgba(0,0,0,0.3);
}

/* HOVER ZONE */
.sidebar-hover-zone {
    position: fixed;
    top: 0;
    left: 0;
    width: 15px;
    height: 100vh;
    z-index: 998;
}

/* SHOW ON HOVER */
.sidebar-hover-zone:hover + .sidebar-wrapper,
.sidebar-wrapper:hover {
    left: 0;
}

/* HEADER */
.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar-header h3 {
    margin: 0;
    font-size: 16px;
}
.sidebar-header span {
    font-size: 12px;
    color: #ffb7a4;
}

/* MENU */
.sidebar-menu {
    padding: 15px 0;
}
.sidebar-menu a {
    display: flex;
    align-items: center;
    padding: 12px 22px;
    color: #ddd;
    text-decoration: none;
    font-size: 14px;
    transition: 0.25s;
}
.sidebar-menu a i {
    width: 22px;
    margin-right: 12px;
}
.sidebar-menu a:hover {
    background: rgba(255,255,255,0.08);
    color: #ffb7a4;
}

/* LOGOUT */
.sidebar-menu .logout {
    color: #ff7676;
}

/* NEW MENU HIGHLIGHT */
.new-menu-highlight {
    position: relative;
}

@keyframes pulse {
    0% { opacity: 0.8; }
    50% { opacity: 1; }
    100% { opacity: 0.8; }
}
</style>

<!-- AREA PEMICU HOVER -->
<div class="sidebar-hover-zone"></div>

<!-- SIDEBAR -->
<div class="sidebar-wrapper">
    <div class="sidebar-header">
        <h3>Hello, <?= htmlspecialchars($nama) ?></h3>
        <span><?= ucfirst($role) ?></span>
    </div>

    <div class="sidebar-menu">

        <!-- ADMIN -->
        <?php if ($role === 'admin'): ?>
            <a href="dashboard.php">
                <i class="fa-solid fa-gauge"></i> Dashboard
            </a>

            <a href="manajemen_user.php">
                <i class="fa-solid fa-user-gear"></i> Manajemen User
            </a>

            <a href="data_karyawan.php">
                <i class="fa-solid fa-users"></i> Data Karyawan
            </a>

            <!-- MENU BARU: MANAJEMEN PENDAPATAN -->
            <a href="pendapatan.php" class="new-menu-highlight">
                <i class="fa-solid fa-money-bill-trend-up"></i> Manajemen Pendapatan
            </a>

            <a href="laporan.php">
                <i class="fa-solid fa-file-lines"></i> Laporan
            </a>

            <a href="penggajian.php">
                <i class="fa-solid fa-money-check-dollar"></i> Penggajian
            </a>

            <a href="pengaturan.php">
                <i class="fa-solid fa-gear"></i> Pengaturan
            </a>

            <a class="logout" href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>

        <!-- OWNER -->
        <?php elseif ($role === 'owner'): ?>
            <a href="dashboard.php">
                <i class="fa-solid fa-gauge"></i> Dashboard
            </a>

            <a href="data_karyawan.php">
                <i class="fa-solid fa-users"></i> Data Karyawan
            </a>

            <!-- MENU BARU: ANALISIS PENDAPATAN -->
            <a href="pendapatan.php" class="new-menu-highlight">
                <i class="fa-solid fa-chart-pie"></i> Analisis Pendapatan
            </a>

            <a href="laporan.php">
                <i class="fa-solid fa-file-lines"></i> Laporan
            </a>

            <a href="penggajian.php">
                <i class="fa-solid fa-money-check-dollar"></i> Penggajian
            </a>

            <!-- MENU BARU: PENGATURAN OWNER -->
            <a href="pengaturan.php" class="new-menu-highlight">
                <i class="fa-solid fa-gear"></i> Pengaturan Owner
            </a>

            <a class="logout" href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>

        <!-- USER -->
        <?php else: ?>
            <a href="dashboard.php">
                <i class="fa-solid fa-gauge"></i> Dashboard
            </a>

            <a href="absen.php">
                <i class="fa-solid fa-calendar-check"></i> Absen
            </a>

            <a href="penggajian.php">
                <i class="fa-solid fa-calendar-days"></i> Penggajian
            </a>

            <a href="pengaturan.php">
                <i class="fa-solid fa-gear"></i> Pengaturan
            </a>

            <a class="logout" href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        <?php endif; ?>

    </div>
</div>