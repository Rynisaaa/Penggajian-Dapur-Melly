<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

include '../includes/sidebar.php';

// TAMPILKAN NOTIFIKASI
$notification = '';
if (isset($_SESSION['success'])) {
    $notification = '<div class="notification success">
        <span>' . $_SESSION['success'] . '</span>
        <button class="close-notif" onclick="this.parentElement.remove()">&times;</button>
    </div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $notification = '<div class="notification error">
        <span>' . $_SESSION['error'] . '</span>
        <button class="close-notif" onclick="this.parentElement.remove()">&times;</button>
    </div>';
    unset($_SESSION['error']);
}

/* AMBIL DATA USER + KARYAWAN */
$q = mysqli_query($conn, "
    SELECT 
        u.id, 
        u.nama_lengkap, 
        u.username, 
        u.role,
        k.id_karyawan,
        k.posisi,
        k.no_telp,
        k.tgl_gajian_rutin,
        k.gaji_pokok
    FROM users u
    LEFT JOIN karyawan k ON u.id = k.user_id
    ORDER BY u.nama_lengkap ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen User - Dapur Melly</title>

<!-- FONT -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<!-- ICON -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- DATATABLE -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<style>
:root{
    --pink:#ff7eb3;
    --pink-soft:#ffe3f0;
    --white:#fff;
    --shadow:0 10px 30px rgba(0,0,0,.08);
}

body{
    margin:0;
    font-family:Poppins;
    background:linear-gradient(135deg,var(--pink-soft),#fff);
}

.main{
    margin-left:80px;
    padding:40px;
}

h1{
    color:#ff5f9e;
    margin-bottom:20px;
}

/* NOTIFICATION */
.notification {
    padding:15px 20px;
    margin-bottom:20px;
    border-radius:10px;
    font-weight:500;
    display:flex;
    align-items:center;
    justify-content:space-between;
    animation:slideIn 0.3s ease;
}
.notification.success {
    background:#d4edda;
    color:#155724;
    border:1px solid #c3e6cb;
}
.notification.error {
    background:#f8d7da;
    color:#721c24;
    border:1px solid #f5c6cb;
}
.notification .close-notif {
    background:none;
    border:none;
    font-size:20px;
    cursor:pointer;
    color:inherit;
    padding:0;
    width:24px;
    height:24px;
    display:flex;
    align-items:center;
    justify-content:center;
}
@keyframes slideIn {
    from { transform:translateY(-20px); opacity:0; }
    to { transform:translateY(0); opacity:1; }
}

/* BUTTON */
.btn{
    background:var(--pink);
    color:#fff;
    border:none;
    padding:10px 18px;
    border-radius:20px;
    cursor:pointer;
    transition:.3s;
    margin-bottom:20px;
}
.btn:hover{transform:scale(1.05)}

/* TABLE */
table{
    width:100%;
    background:#fff;
    border-radius:20px;
    overflow:hidden;
    box-shadow:var(--shadow);
}
thead{
    background:var(--pink);
    color:#fff;
}
th,td{
    padding:12px;
}

/* ROLE BADGE */
.role{
    padding:5px 12px;
    border-radius:14px;
    font-size:12px;
    color:#fff;
    display:inline-block;
}
.admin{background:#ff5f9e}
.owner{background:#ff9800}
.user{background:#7ecbff}

/* POSISI BADGE */
.posisi-badge{
    background:#6c757d;
    color:white;
    padding:3px 8px;
    border-radius:8px;
    font-size:11px;
    display:inline-block;
    margin-top:5px;
}

/* TANGGAL GAJIAN BADGE */
.tanggal-badge{
    background:#9c27b0;
    color:white;
    padding:3px 8px;
    border-radius:8px;
    font-size:11px;
    display:inline-block;
    margin-top:3px;
}

/* ACTION */
.action-btn{
    background:none;
    border:none;
    cursor:pointer;
    font-size:16px;
    margin-right:5px;
}
.edit{color:#4caf50}
.delete{color:#f44336}

/* MODAL */
.modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.6);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:999;
}
.modal form{
    background:#fff;
    padding:30px;
    width:450px;
    max-width:90%;
    border-radius:20px;
    animation:fade .4s ease;
    max-height:90vh;
    overflow-y:auto;
}
@keyframes fade{
    from{opacity:0;transform:scale(.9)}
    to{opacity:1}
}
.modal h3{
    color:#ff5f9e;
    margin-top:0;
    margin-bottom:20px;
}
.modal label{
    display:block;
    margin-bottom:5px;
    font-weight:500;
    color:#555;
}
.modal input, .modal select{
    width:100%;
    padding:10px;
    margin-bottom:15px;
    border-radius:10px;
    border:1px solid #ccc;
    font-family:Poppins;
}
.modal input:focus, .modal select:focus{
    outline:none;
    border-color:var(--pink);
}
.modal .form-section{
    margin-bottom:20px;
    padding-bottom:15px;
    border-bottom:1px solid #eee;
}
.modal .form-section-title{
    color:#ff5f9e;
    font-size:14px;
    font-weight:600;
    margin-bottom:10px;
    display:flex;
    align-items:center;
    gap:8px;
}
</style>
</head>

<body>

<div class="main">
<h1>👤 Manajemen User</h1>

<?= $notification ?>

<button class="btn" onclick="openTambah()">
    <i class="fas fa-user-plus"></i> Tambah User Baru
</button>

<table id="tabel">
<thead>
<tr>
    <th>Nama Lengkap</th>
    <th>Username</th>
    <th>Role & Posisi</th>
    <th>Info Karyawan</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php while($d=mysqli_fetch_assoc($q)): 
    $karyawan_info = '';
    $tanggal_info = '';
    
    if ($d['id_karyawan']) {
        $karyawan_info = '<div style="font-size:12px; color:#666;">
            <i class="fas fa-id-card"></i> ID: ' . $d['id_karyawan'] . '
            ' . ($d['posisi'] ? '<br><i class="fas fa-briefcase"></i> ' . $d['posisi'] : '') . '
            ' . ($d['no_telp'] ? '<br><i class="fas fa-phone"></i> ' . $d['no_telp'] : '') . '
            ' . ($d['gaji_pokok'] ? '<br><i class="fas fa-money-bill"></i> Rp ' . number_format($d['gaji_pokok'], 0, ',', '.') : '') . '
        </div>';
        
        if ($d['tgl_gajian_rutin']) {
            $tanggal_info = '<br><span class="tanggal-badge">
                <i class="fas fa-calendar-day"></i> Gajian tgl ' . $d['tgl_gajian_rutin'] . '
            </span>';
        }
    }
?>
<tr>
    <td>
        <strong><?= htmlspecialchars($d['nama_lengkap']) ?></strong>
    </td>
    <td><?= htmlspecialchars($d['username']) ?></td>
    <td>
        <span class="role <?= $d['role'] ?>">
            <?= strtoupper($d['role']) ?>
        </span>
        <?php if($d['posisi']): ?>
        <div class="posisi-badge">
            <i class="fas fa-briefcase"></i> <?= $d['posisi'] ?>
        </div>
        <?php endif; ?>
        <?= $tanggal_info ?>
    </td>
    <td>
        <?= $karyawan_info ?>
    </td>
    <td>
        <button class="action-btn edit" onclick='editUser(<?= json_encode($d) ?>)' title="Edit">
            <i class="fa fa-edit"></i>
        </button>
        <a class="action-btn delete"
           href="hapus_user.php?id=<?= $d['id'] ?>"
           onclick="return confirm('Hapus user \"<?= addslashes($d['nama_lengkap']) ?>\"?')"
           title="Hapus">
            <i class="fa fa-trash"></i>
        </a>
    </td>
</tr>
<?php endwhile ?>
</tbody>
</table>
</div>

<!-- MODAL TAMBAH/EDIT USER -->
<div class="modal" id="modal">
<form method="POST" action="save_user.php" onsubmit="return validateForm()">
<input type="hidden" name="id" id="uid">

<div class="form-section">
    <div class="form-section-title">
        <i class="fas fa-user"></i> Data Akun
    </div>
    
    <label for="nama_lengkap">Nama Lengkap *</label>
    <input type="text" name="nama_lengkap" id="nama_lengkap" required>
    
    <label for="username">Username *</label>
    <input type="text" name="username" id="username" required>
    
    <label for="password" id="password_label">Password *</label>
    <input type="password" name="password" id="password">
    <small id="password_hint" style="color:#666; font-size:12px;">Minimal 6 karakter</small>
    
    <label for="role">Role *</label>
    <select name="role" id="role" required onchange="toggleKaryawanFields()">
        <option value="">-- Pilih Role --</option>
        <option value="admin">Admin</option>
        <option value="owner">Owner</option>
        <option value="user">User/Karyawan</option>
    </select>
</div>

<div class="form-section" id="karyawan_section" style="display:none;">
    <div class="form-section-title">
        <i class="fas fa-briefcase"></i> Data Karyawan
    </div>
    
    <label for="posisi">Posisi/Jabatan</label>
    <input type="text" name="posisi" id="posisi">
    
    <label for="no_telp">Nomor Telepon (WhatsApp)</label>
    <input type="text" name="no_telp" id="no_telp" placeholder="Contoh: 081234567890">
    
    <label for="gaji_pokok">Gaji Pokok</label>
    <input type="number" name="gaji_pokok" id="gaji_pokok" min="0" value="0">
    
    <!-- TANGGAL GAJIAN - VERSI SEDERHANA -->
    <label for="tgl_gajian_rutin">
        <i class="fas fa-calendar-day"></i> Tanggal Gajian
    </label>
    <select name="tgl_gajian_rutin" id="tgl_gajian_rutin" style="width:150px;">
        <option value="">-- Pilih Tanggal --</option>
        <?php for($i=1; $i<=31; $i++): ?>
            <option value="<?= $i ?>">Tanggal <?= $i ?></option>
        <?php endfor; ?>
    </select>
    <small style="color:#666; font-size:12px; display:block; margin-top:5px;">
        <i class="fas fa-info-circle"></i> Pilih tanggal gajian setiap bulan
    </small>
</div>

<button type="submit" class="btn" style="width:100%;">
    <i class="fas fa-save"></i> Simpan Data
</button>
</form>
</div>

<script>
$(document).ready(function() {
    new DataTable('#tabel', {
        pageLength: 10,
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ hingga _END_ dari _TOTAL_ data",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "→",
                previous: "←"
            }
        }
    });
});

const modal = document.getElementById('modal');

function openTambah(){
    modal.style.display='flex';
    document.querySelector('#modal form').reset();
    document.getElementById('uid').value = '';
    document.getElementById('password_label').innerHTML = 'Password *';
    document.getElementById('password_hint').style.display = 'block';
    document.getElementById('password').required = true;
    document.getElementById('karyawan_section').style.display = 'none';
}

function editUser(userData){
    modal.style.display='flex';
    document.querySelector('#modal form').reset();
    
    // Isi data user
    document.getElementById('uid').value = userData.id;
    document.getElementById('nama_lengkap').value = userData.nama_lengkap;
    document.getElementById('username').value = userData.username;
    document.getElementById('role').value = userData.role;
    
    // Password opsional untuk edit
    document.getElementById('password_label').innerHTML = 'Password (kosongkan jika tidak diubah)';
    document.getElementById('password_hint').style.display = 'none';
    document.getElementById('password').required = false;
    
    // Tampilkan section karyawan jika role user/karyawan
    toggleKaryawanFields();
    
    // Jika ada data karyawan, isi form
    if(userData.id_karyawan){
        document.getElementById('posisi').value = userData.posisi || '';
        document.getElementById('no_telp').value = userData.no_telp || '';
        document.getElementById('gaji_pokok').value = userData.gaji_pokok || '0';
        document.getElementById('tgl_gajian_rutin').value = userData.tgl_gajian_rutin || '';
    } else {
        // Kosongkan jika bukan karyawan
        document.getElementById('posisi').value = '';
        document.getElementById('no_telp').value = '';
        document.getElementById('gaji_pokok').value = '0';
        document.getElementById('tgl_gajian_rutin').value = '';
    }
}

function toggleKaryawanFields(){
    const role = document.getElementById('role').value;
    const karyawanSection = document.getElementById('karyawan_section');
    
    if(role === 'user'){
        karyawanSection.style.display = 'block';
    }else{
        karyawanSection.style.display = 'none';
    }
}

function validateForm(){
    const password = document.getElementById('password').value;
    const uid = document.getElementById('uid').value;
    
    // Validasi password untuk user baru
    if(!uid && password.length < 6){
        alert('Password minimal 6 karakter untuk user baru!');
        return false;
    }
    
    return true;
}

modal.onclick = function(e){
    if(e.target == modal){
        modal.style.display='none';
    }
}

// Auto-hide notifikasi
setTimeout(() => {
    document.querySelectorAll('.notification').forEach(notification => {
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 500);
        }, 5000);
    });
}, 1000);
</script>

</body>
</html>