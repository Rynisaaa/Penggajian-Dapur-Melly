<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

include '../includes/sidebar.php';

$users = mysqli_query($conn,"
    SELECT * FROM users
    ORDER BY nama_lengkap ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data User</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<style>
body{
    margin:0;
    font-family:Poppins;
    background:linear-gradient(135deg,#ffe3f0,#fff);
}
.main{margin-left:80px;padding:40px}
h1{color:#ff5f9e}

.btn{
    background:#ff7eb3;
    color:#fff;
    border:none;
    padding:10px 18px;
    border-radius:20px;
    cursor:pointer;
}

table{
    width:100%;
    background:#fff;
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}
thead{background:#ff7eb3;color:#fff}
th,td{padding:12px}

.modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.6);
    display:none;
    justify-content:center;
    align-items:center;
}
.modal form{
    background:#fff;
    padding:30px;
    width:380px;
    border-radius:20px;
}
.modal input, .modal select{
    width:100%;
    padding:8px;
    margin-bottom:10px;
}
</style>
</head>

<body>

<div class="main">
<h1>👤 Data User</h1>
<button class="btn" onclick="openTambah()">+ Tambah User</button>

<br><br>

<table id="tabel">
<thead>
<tr>
    <th>Nama Lengkap</th>
    <th>Username</th>
    <th>Role</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php while($u=mysqli_fetch_assoc($users)): ?>
<tr>
    <td><?= htmlspecialchars($u['nama_lengkap']) ?></td>
    <td><?= $u['username'] ?></td>
    <td><?= strtoupper($u['role']) ?></td>
    <td>
        <button onclick='editUser(<?= json_encode($u) ?>)'><i class="fa fa-edit"></i></button>
        <a href="hapus_user.php?id=<?= $u['id'] ?>" onclick="return confirm('Hapus user?')">
            <i class="fa fa-trash"></i>
        </a>
    </td>
</tr>
<?php endwhile ?>
</tbody>
</table>
</div>

<!-- MODAL -->
<div class="modal" id="modal">
<form method="POST" action="save_user.php">
<input type="hidden" name="id" id="uid">

<label>Nama Lengkap</label>
<input name="nama_lengkap" id="nama" required>

<label>Username</label>
<input name="username" id="username" required>

<label>Password</label>
<input type="password" name="password" id="password">

<label>Role</label>
<select name="role" id="role" required>
    <option value="user">User</option>
    <option value="admin">Admin</option>
    <option value="owner">Owner</option>
</select>

<button class="btn">Simpan</button>
</form>
</div>

<script>
new DataTable('#tabel');

const modal=document.getElementById('modal');

function openTambah(){
    modal.style.display='flex';
    document.querySelector('form').reset();
    uid.value='';
}

function editUser(d){
    modal.style.display='flex';
    uid.value=d.id;
    nama.value=d.nama_lengkap;
    username.value=d.username;
    role.value=d.role;
}

modal.onclick=e=>{if(e.target==modal)modal.style.display='none'}
</script>

</body>
</html>
