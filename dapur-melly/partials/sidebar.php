<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
.sidebar {
    position: fixed;
    left: -200px;
    top: 0;
    width: 200px;
    height: 100%;
    background: #4b2e1e;
    color: white;
    padding: 20px;
    transition: 0.4s;
    z-index: 1000;
}

.sidebar:hover {
    left: 0;
}

.sidebar a {
    color: white;
    display: block;
    margin: 15px 0;
    text-decoration: none;
}
</style>

<div class="sidebar">
    <p>Hello, <?= htmlspecialchars($_SESSION['nama_lengkap']); ?></p>
    <small><?= $_SESSION['role']; ?></small>
    <hr>

    <a href="#"><i class="fa fa-home"></i> Dashboard</a>
    <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>
