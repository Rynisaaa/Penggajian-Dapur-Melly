<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_karyawan = $_POST['id_karyawan'];
    $bulan = $_POST['bulan'];
    $tahun = $_POST['tahun'];
    $tunjangan = $_POST['tunjangan'] ?: 0;
    $potongan = $_POST['potongan'] ?: 0;
    
    // Ambil gaji pokok
    $q = mysqli_query($conn, "SELECT gaji_pokok FROM karyawan WHERE id_karyawan = '$id_karyawan'");
    $karyawan = mysqli_fetch_assoc($q);
    $gaji_pokok = $karyawan['gaji_pokok'];
    
    $gaji_bersih = ($gaji_pokok + $tunjangan) - $potongan;
    
    mysqli_query($conn, "
        INSERT INTO penggajian 
        (id_karyawan, bulan, tahun, tunjangan, potongan, gaji_bersih, status_bayar)
        VALUES 
        ('$id_karyawan', '$bulan', '$tahun', '$tunjangan', '$potongan', '$gaji_bersih', 'belum')
    ");
    
    $_SESSION['success'] = "Data penggajian berhasil ditambahkan";
    header("Location: penggajian.php");
    exit;
}

// Ambil data karyawan dengan info tanggal gajian
$karyawan = mysqli_query($conn, "
    SELECT 
        k.id_karyawan, 
        u.nama_lengkap, 
        k.gaji_pokok,
        k.tgl_gajian,
        k.tgl_gajian_rutin,
        CASE 
            WHEN k.tgl_gajian IS NOT NULL THEN 
                DATE_FORMAT(k.tgl_gajian, '%d %M %Y')
            WHEN k.tgl_gajian_rutin IS NOT NULL THEN 
                CONCAT('Tgl ', k.tgl_gajian_rutin, ' setiap bulan')
            ELSE 'Belum diatur'
        END as info_tanggal_gajian
    FROM karyawan k 
    JOIN users u ON k.user_id = u.id
    ORDER BY u.nama_lengkap
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Tambah Penggajian</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
body{
    margin:0;
    font-family:Poppins;
    background:linear-gradient(135deg,#ffe3f0,#fff);
}
.main{
    margin-left:80px;
    padding:40px;
    max-width:600px;
}
h1{color:#ff5f9e}
.form-group{
    margin-bottom:20px;
}
label{
    display:block;
    margin-bottom:5px;
    color:#555;
}
input,select{
    width:100%;
    padding:10px;
    border-radius:10px;
    border:1px solid #ddd;
    font-family:Poppins;
}
.btn{
    background:#ff7eb3;
    color:#fff;
    border:none;
    padding:10px 20px;
    border-radius:10px;
    cursor:pointer;
    margin-right:10px;
    text-decoration:none;
    display:inline-block;
}
.btn-secondary{
    background:#777;
}
.form-container{
    background:#fff;
    padding:30px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.1);
    margin-top:20px;
}
.option-info{
    font-size:12px;
    color:#666;
    margin-top:2px;
}
</style>
</head>
<body>
<div class="main">
<h1>➕ Tambah Data Penggajian</h1>
<a href="penggajian.php" class="btn btn-secondary">← Kembali</a>

<div class="form-container">
<form method="POST">
    
    <div class="form-group">
        <label>Karyawan</label>
        <select name="id_karyawan" required>
            <option value="">Pilih Karyawan</option>
            <?php while($k = mysqli_fetch_assoc($karyawan)): ?>
            <option value="<?= $k['id_karyawan'] ?>">
                <?= htmlspecialchars($k['nama_lengkap']) ?> 
                (Gaji: Rp <?= number_format($k['gaji_pokok']) ?>)
                <div class="option-info">
                    <i class="fas fa-calendar"></i> <?= $k['info_tanggal_gajian'] ?>
                </div>
            </option>
            <?php endwhile; ?>
        </select>
    </div>
    
    <div style="display:flex; gap:15px;">
        <div class="form-group" style="flex:1">
            <label>Bulan</label>
            <select name="bulan" required>
                <?php 
                $months = [
                    '01'=>'Januari','02'=>'Februari','03'=>'Maret',
                    '04'=>'April','05'=>'Mei','06'=>'Juni',
                    '07'=>'Juli','08'=>'Agustus','09'=>'September',
                    '10'=>'Oktober','11'=>'November','12'=>'Desember'
                ];
                foreach($months as $num => $name): ?>
                <option value="<?= $num ?>" <?= $num == date('m') ? 'selected' : '' ?>>
                    <?= $name ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="flex:1">
            <label>Tahun</label>
            <select name="tahun" required>
                <?php for($y = date('Y'); $y >= date('Y')-2; $y--): ?>
                <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>>
                    <?= $y ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
    
    <div style="display:flex; gap:15px;">
        <div class="form-group" style="flex:1">
            <label>Tunjangan (Rp)</label>
            <input type="number" name="tunjangan" value="0" min="0">
        </div>
        
        <div class="form-group" style="flex:1">
            <label>Potongan (Rp)</label>
            <input type="number" name="potongan" value="0" min="0">
        </div>
    </div>
    
    <button type="submit" class="btn">Simpan Data</button>
</form>
</div>

</div>
</body>
</html>