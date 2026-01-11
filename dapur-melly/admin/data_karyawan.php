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

/* DATA KARYAWAN + TANGGAL GAJIAN + SMART SORTING - DIREVISI */
$q = mysqli_query($conn, "
    SELECT 
        k.*,
        u.nama_lengkap,
        u.username,
        
        -- Hitung hari sampai tanggal gajian berikutnya dengan FIX
        CASE 
            WHEN k.tgl_gajian_rutin IS NOT NULL AND k.tgl_gajian_rutin > 0 THEN 
                CASE
                    -- Jika tanggal gajian >= tanggal hari ini (masih di bulan ini)
                    WHEN k.tgl_gajian_rutin >= DAY(CURDATE()) THEN 
                        k.tgl_gajian_rutin - DAY(CURDATE())
                    ELSE
                        -- Jika tanggal gajian sudah lewat bulan ini, hitung untuk bulan depan
                        (DAY(LAST_DAY(CURDATE())) - DAY(CURDATE())) + k.tgl_gajian_rutin
                END
            ELSE 999
        END as days_until_gajian,
        
        -- Rekap absen 30 hari
        SUM(CASE WHEN p.status='masuk' THEN 1 ELSE 0 END) AS masuk,
        SUM(CASE WHEN p.status='izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN p.status='sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN p.status='alpa' THEN 1 ELSE 0 END) AS alpa

    FROM karyawan k
    JOIN users u ON k.user_id = u.id
    LEFT JOIN presensi p 
        ON p.id_karyawan = k.id_karyawan
        AND p.tanggal >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)

    GROUP BY k.id_karyawan
    -- SMART SORTING: urutkan berdasarkan tanggal gajian terdekat
    ORDER BY 
        -- Pertama: Karyawan dengan tgl_gajian_rutin NULL atau 0 diakhir
        CASE 
            WHEN k.tgl_gajian_rutin IS NULL OR k.tgl_gajian_rutin = 0 THEN 1
            ELSE 0
        END,
        -- Kedua: Urutkan berdasarkan days_until_gajian (terdekat dulu)
        days_until_gajian ASC,
        -- Ketiga: Jika tanggal gajian sama, urutkan berdasarkan nama
        u.nama_lengkap ASC
");

/* USER UNTUK DROPDOWN TAMBAH */
$userQ = mysqli_query($conn, "
    SELECT id, nama_lengkap, username
    FROM users 
    WHERE role='user' AND id NOT IN (SELECT user_id FROM karyawan WHERE user_id IS NOT NULL)
    ORDER BY nama_lengkap ASC
");

// HITUNG STATISTIK
$total_karyawan = mysqli_num_rows($q);
mysqli_data_seek($q, 0); // Reset pointer

// Hitung statistik gajian secara terpisah untuk akurasi
$gajian_hari_ini = 0;
$gajian_minggu_ini = 0;

// Simpan data untuk ditampilkan nanti
$karyawan_data = [];
while($d = mysqli_fetch_assoc($q)) {
    $karyawan_data[] = $d;
    
    // Hitung statistik gajian
    if (!empty($d['tgl_gajian_rutin']) && $d['tgl_gajian_rutin'] > 0) {
        $today = date('j'); // Tanggal hari ini (1-31)
        
        if ($d['tgl_gajian_rutin'] == $today) {
            $gajian_hari_ini++;
            $gajian_minggu_ini++;
        } elseif ($d['tgl_gajian_rutin'] > $today && $d['tgl_gajian_rutin'] <= $today + 7) {
            $gajian_minggu_ini++;
        } elseif ($d['tgl_gajian_rutin'] < $today) {
            // Jika tanggal gajian sudah lewat, cek apakah dalam 7 hari ke depan di bulan depan
            $days_in_month = date('t'); // Jumlah hari dalam bulan ini
            $days_remaining = $days_in_month - $today;
            $next_month_gajian = $d['tgl_gajian_rutin'] + $days_remaining;
            
            if ($next_month_gajian <= 7) {
                $gajian_minggu_ini++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data Karyawan - Dapur Melly</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<style>
:root{
    --pink:#ff7eb3;
    --pink-soft:#ffe3f0;
    --shadow:0 10px 30px rgba(0,0,0,.08);
}
body{
    margin:0;
    font-family:Poppins;
    background:linear-gradient(135deg,var(--pink-soft),#fff);
}
.main{margin-left:80px;padding:40px}
h1{color:#ff5f9e; margin-bottom:20px;}

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

/* STATS BAR */
.stats-bar {
    display:flex;
    gap:15px;
    margin-bottom:20px;
    flex-wrap:wrap;
}
.stat-box {
    background:white;
    padding:15px 20px;
    border-radius:15px;
    box-shadow:var(--shadow);
    flex:1;
    min-width:150px;
    text-align:center;
    border-left:5px solid var(--pink);
}
.stat-box .number {
    font-size:24px;
    font-weight:bold;
    color:var(--pink);
    display:block;
}
.stat-box .label {
    font-size:12px;
    color:#666;
    margin-top:5px;
}

.btn{
    background:var(--pink);
    color:#fff;
    border:none;
    padding:10px 18px;
    border-radius:20px;
    cursor:pointer;
    margin-bottom:20px;
    transition:0.3s;
}
.btn:hover {
    transform:translateY(-2px);
    box-shadow:0 5px 15px rgba(255,126,179,0.3);
}
table{
    width:100%;
    background:#fff;
    border-radius:20px;
    box-shadow:var(--shadow);
    border-collapse:collapse;
}
thead{background:var(--pink);color:#fff}
th,td{padding:12px; border-bottom:1px solid #eee;}

.badge{padding:4px 10px;border-radius:12px;font-size:12px; display:inline-block;}
.masuk{background:#4caf50;color:#fff}
.izin{background:#ffb300;color:#fff}
.sakit{background:#03a9f4;color:#fff}
.alpa{background:#f44336;color:#fff}

/* BADGE TANGGAL GAJIAN */
.gajian-badge{
    padding:4px 8px;
    border-radius:8px;
    font-size:11px;
    font-weight:500;
    margin-top:5px;
    display:inline-block;
}
.gajian-today{background:#4CAF50; color:white; animation:pulse 2s infinite;}
.gajian-upcoming{background:#2196F3; color:white;}
.gajian-past{background:#FF9800; color:white;}
.gajian-na{background:#9E9E9E; color:white;}

@keyframes pulse {
    0% { opacity:1; }
    50% { opacity:0.7; }
    100% { opacity:1; }
}

/* ACTION BUTTONS */
.action-btn {
    background:none;
    border:none;
    cursor:pointer;
    font-size:16px;
    margin-right:8px;
    padding:5px;
    border-radius:5px;
    transition:0.2s;
}
.action-btn.edit{
    background:#4caf50;
    color:white;
}
.action-btn.edit:hover{
    background:#45a049;
}
.action-btn.delete{
    background:#f44336;
    color:white;
}
.action-btn.delete:hover{
    background:#d32f2f;
}

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
    max-height:90vh;
    overflow-y:auto;
    box-shadow:0 10px 30px rgba(0,0,0,0.3);
}
.modal input,.modal select{
    width:100%;
    padding:10px;
    margin-bottom:12px;
    border-radius:10px;
    border:1px solid #ddd;
    font-family:Poppins;
    transition:0.3s;
}
.modal input:focus,.modal select:focus{
    outline:none;
    border-color:var(--pink);
    box-shadow:0 0 0 3px rgba(255,126,179,0.1);
}
.modal label{
    display:block;
    margin-bottom:5px;
    font-weight:500;
    color:#555;
}
.modal .form-group {
    margin-bottom:15px;
}

/* EMPTY STATE */
.empty-state {
    text-align:center;
    padding:40px 20px;
    color:#666;
}
.empty-state i {
    font-size:48px;
    color:var(--pink);
    margin-bottom:15px;
    opacity:0.5;
}
</style>
</head>

<body>

<div class="main">
<h1>👩‍🍳 Data Karyawan</h1>

<?= $notification ?>

<!-- STATS BAR -->
<div class="stats-bar">
    <div class="stat-box">
        <span class="number"><?= $total_karyawan ?></span>
        <span class="label">Total Karyawan</span>
    </div>
    <div class="stat-box">
        <span class="number" id="gajian-today-count"><?= $gajian_hari_ini ?></span>
        <span class="label">Gajian Hari Ini</span>
    </div>
    <div class="stat-box">
        <span class="number" id="gajian-week-count"><?= $gajian_minggu_ini ?></span>
        <span class="label">Gajian 7 Hari</span>
    </div>
</div>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <button class="btn" onclick="openTambah()">
        <i class="fas fa-user-plus"></i> + Tambah Karyawan
    </button>
    
    <div style="display:flex; gap:10px;">
        <button class="btn" style="background:#6c757d;" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak
        </button>
        <button class="btn" style="background:#28a745;" onclick="exportToExcel()">
            <i class="fas fa-file-excel"></i> Excel
        </button>
    </div>
</div>

<?php if ($total_karyawan == 0): ?>
<div class="empty-state">
    <i class="fas fa-users"></i>
    <h3>Belum ada data karyawan</h3>
    <p>Klik tombol "Tambah Karyawan" untuk menambahkan data</p>
</div>
<?php else: ?>

<table id="tabel">
<thead>
<tr>
    <th>Nama</th>
    <th>Posisi & Gaji</th>
    <th>Tanggal Gajian</th>
    <th>No Telp</th>
    <th>Rekap Absen (30hr)</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php 
foreach($karyawan_data as $d): 
    // LOGIKA TANGGAL GAJIAN YANG LEBIH AKURAT
    $tanggalGajian = '';
    $gajianClass = 'gajian-na';
    $gajianText = 'Belum diatur';
    
    if (!empty($d['tgl_gajian_rutin']) && $d['tgl_gajian_rutin'] > 0) {
        $today = date('j');
        $days_until = $d['days_until_gajian'];
        
        $tanggalGajian = 'Tiap tanggal <strong>' . $d['tgl_gajian_rutin'] . '</strong>';
        
        if ($days_until == 0) {
            $gajianClass = 'gajian-today';
            $gajianText = 'Gajian Hari Ini!';
        } elseif ($days_until == 1) {
            $gajianClass = 'gajian-upcoming';
            $gajianText = 'Besok Gajian';
        } elseif ($days_until > 1 && $days_until <= 7) {
            $gajianClass = 'gajian-upcoming';
            $gajianText = $days_until . ' hari lagi';
        } elseif ($days_until > 7 && $days_until < 999) {
            $gajianClass = 'gajian-upcoming';
            $gajianText = $days_until . ' hari lagi';
        } elseif ($days_until == 999) {
            $gajianClass = 'gajian-na';
            $gajianText = 'Belum diatur';
        }
    }
?>
<tr>
    <td>
        <strong><?= htmlspecialchars($d['nama_lengkap']) ?></strong><br>
        <small style="color:#666; font-size:11px;">
            <i class="fas fa-user"></i> <?= $d['username'] ?> |
            <i class="fas fa-calendar"></i> Masuk: <?= !empty($d['tgl_masuk']) ? date('d/m/Y', strtotime($d['tgl_masuk'])) : '-' ?>
        </small>
    </td>
    <td>
        <strong><?= !empty($d['posisi']) ? $d['posisi'] : '-' ?></strong><br>
        <span style="color:#ff5f9e; font-weight:bold;">
            Rp <?= number_format($d['gaji_pokok'], 0, ',', '.') ?>
        </span><br>
        <small style="color:#666; font-size:11px;">
            <i class="fas fa-history"></i> <?= $d['lama_bekerja'] ?? 0 ?> bulan
        </small>
    </td>
    <td>
        <?php if(!empty($d['tgl_gajian_rutin']) && $d['tgl_gajian_rutin'] > 0): ?>
            <div><?= $tanggalGajian ?></div>
            <span class="gajian-badge <?= $gajianClass ?>">
                <i class="fas fa-calendar-check"></i> <?= $gajianText ?>
            </span>
        <?php else: ?>
            <span class="gajian-badge gajian-na">
                <i class="fas fa-question-circle"></i> Belum diatur
            </span>
        <?php endif; ?>
    </td>
    <td>
        <?php if(!empty($d['no_telp'])): ?>
            <a href="https://wa.me/<?= $d['no_telp'] ?>" target="_blank" 
               style="color:#25D366; text-decoration:none;" title="Kirim WhatsApp">
                <i class="fab fa-whatsapp"></i> <?= $d['no_telp'] ?>
            </a>
        <?php else: ?>
            <span style="color:#999;">-</span>
        <?php endif; ?>
    </td>
    <td>
        <div style="display:flex; gap:5px; flex-wrap:wrap;">
            <span class="badge masuk" title="Masuk">M <?= $d['masuk'] ?: 0 ?></span>
            <span class="badge izin" title="Izin">I <?= $d['izin'] ?: 0 ?></span>
            <span class="badge sakit" title="Sakit">S <?= $d['sakit'] ?: 0 ?></span>
            <span class="badge alpa" title="Alpa">A <?= $d['alpa'] ?: 0 ?></span>
        </div>
        <?php 
        $total_hadir = ($d['masuk'] ?: 0) + ($d['izin'] ?: 0) + ($d['sakit'] ?: 0);
        $total_absensi = $total_hadir + ($d['alpa'] ?: 0);
        $persentase = $total_absensi > 0 ? ($total_hadir / $total_absensi * 100) : 0;
        ?>
        <small style="color:#666; font-size:10px; margin-top:3px; display:block;">
            Kehadiran: <?= number_format($persentase, 1) ?>%
        </small>
    </td>
    <td>
        <button class="action-btn edit" onclick='editKaryawan(<?= json_encode($d) ?>)' title="Edit">
            <i class="fa fa-edit"></i>
        </button>
        <a class="action-btn delete"
           href="hapus_karyawan.php?id=<?= $d['id_karyawan'] ?>"
           onclick="return confirm('Hapus karyawan \"<?= addslashes($d['nama_lengkap']) ?>\"? Semua data penggajian dan presensi juga akan terhapus.')"
           title="Hapus">
            <i class="fa fa-trash"></i>
        </a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<!-- FOOTER INFO -->
<div style="margin-top:20px; padding:15px; background:#f8f9fa; border-radius:10px; font-size:14px;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <i class="fas fa-info-circle" style="color:#ff5f9e;"></i>
            <strong><?= $total_karyawan ?> karyawan</strong> | 
            <span style="color:#4CAF50;"><i class="fas fa-calendar-check"></i> <?= $gajian_hari_ini ?> gajian hari ini</span> | 
            <span style="color:#2196F3;"><i class="fas fa-calendar-week"></i> <?= $gajian_minggu_ini ?> gajian dalam 7 hari</span>
        </div>
        <div style="color:#666; font-size:12px;">
            <i class="fas fa-sort-amount-up-alt"></i> Diurutkan berdasarkan tanggal gajian terdekat
        </div>
    </div>
</div>

<?php endif; ?>

</div>

<!-- MODAL -->
<div class="modal" id="modal">
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h3 style="color:#ff5f9e; margin:0;" id="modalTitle">Tambah Karyawan</h3>
    <button onclick="modal.style.display='none'" style="background:none; border:none; font-size:20px; color:#999; cursor:pointer;">
        &times;
    </button>
</div>

<form method="POST" action="save_karyawan.php" id="karyawanForm">
<input type="hidden" name="id_karyawan" id="id_karyawan">
<input type="hidden" name="user_id" id="user_id_hidden">

<div class="form-group">
    <label>Nama Karyawan *</label>
    <select name="user_id" id="user_id" required onchange="updateNamaFromUser()">
        <option value="">-- Pilih User --</option>
        <?php 
        $userData = [];
        while($u = mysqli_fetch_assoc($userQ)): 
            $userData[] = $u;
        ?>
            <option value="<?= $u['id'] ?>" data-nama="<?= htmlspecialchars($u['nama_lengkap']) ?>">
                <?= $u['nama_lengkap'] ?> (<?= $u['username'] ?>)
            </option>
        <?php endwhile; 
        
        if (empty($userData)): ?>
            <option value="" disabled>Semua user sudah menjadi karyawan</option>
        <?php endif; ?>
    </select>
</div>

<div class="form-group">
    <label>Nama Lengkap (otomatis terisi)</label>
    <input name="nama_lengkap" id="nama_lengkap" readonly style="background:#f5f5f5;">
</div>

<div class="form-group">
    <label>Posisi/Jabatan *</label>
    <input name="posisi" id="posisi" required placeholder="Contoh: Baker, Delivery">
</div>

<div class="form-group">
    <label>No Telepon (WhatsApp)</label>
    <input name="no_telp" id="no_telp" placeholder="628xxxxxxxxxx">
</div>

<div class="form-group">
    <label>Gaji Pokok *</label>
    <input type="number" name="gaji_pokok" id="gaji_pokok" required min="0" value="0">
</div>

<div class="form-group">
    <label>Tanggal Gajian</label>
    <select name="tgl_gajian_rutin" id="tgl_gajian_rutin" style="width:100%;">
        <option value="">-- Pilih Tanggal --</option>
        <?php for($i=1; $i<=31; $i++): ?>
            <option value="<?= $i ?>">Tanggal <?= $i ?></option>
        <?php endfor; ?>
    </select>
    <small style="color:#666; font-size:11px; display:block; margin-top:5px;">
        <i class="fas fa-info-circle"></i> Pilih tanggal gajian setiap bulan
    </small>
</div>

<div class="form-group">
    <label>Lama Bekerja (bulan)</label>
    <input type="number" name="lama_bekerja" id="lama_bekerja" min="0" value="0">
</div>

<button type="submit" class="btn" style="width:100%;">
    <i class="fas fa-save"></i> Simpan Data Karyawan
</button>
</form>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    if(<?= $total_karyawan ?> > 0) {
        new DataTable('#tabel', {
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50],
            language: {
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ hingga _END_ dari _TOTAL_ data",
                infoEmpty: "Menampilkan 0 hingga 0 dari 0 data",
                infoFiltered: "(disaring dari _MAX_ total data)",
                zeroRecords: "Tidak ada data yang cocok",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "→",
                    previous: "←"
                }
            },
            order: [[2, 'asc']], // Default sort by tanggal gajian
            responsive: true
        });
    }
});

const modal = document.getElementById('modal');
const modalTitle = document.getElementById('modalTitle');
const karyawanForm = document.getElementById('karyawanForm');

function openTambah(){
    modal.style.display='flex';
    modalTitle.textContent = 'Tambah Karyawan';
    karyawanForm.reset();
    document.getElementById('id_karyawan').value='';
    document.getElementById('user_id').disabled = false;
    document.getElementById('user_id_hidden').value = '';
    document.getElementById('nama_lengkap').value = '';
    document.getElementById('user_id').focus();
}

function editKaryawan(d){
    modal.style.display='flex';
    modalTitle.textContent = 'Edit Karyawan - ' + d.nama_lengkap;
    karyawanForm.reset();
    
    // Isi data
    document.getElementById('id_karyawan').value = d.id_karyawan || '';
    document.getElementById('user_id_hidden').value = d.user_id || '';
    document.getElementById('nama_lengkap').value = d.nama_lengkap || '';
    document.getElementById('posisi').value = d.posisi || '';
    document.getElementById('no_telp').value = d.no_telp || '';
    document.getElementById('gaji_pokok').value = d.gaji_pokok || '0';
    document.getElementById('tgl_gajian_rutin').value = d.tgl_gajian_rutin || '';
    document.getElementById('lama_bekerja').value = d.lama_bekerja || '0';
    
    // Nonaktifkan select user untuk edit
    document.getElementById('user_id').disabled = true;
    
    // Tampilkan user yang dipilih
    const userSelect = document.getElementById('user_id');
    for(let i = 0; i < userSelect.options.length; i++){
        if(userSelect.options[i].value == d.user_id){
            userSelect.options[i].selected = true;
            break;
        }
    }
}

function updateNamaFromUser(){
    const userSelect = document.getElementById('user_id');
    const selectedOption = userSelect.options[userSelect.selectedIndex];
    const namaLengkap = selectedOption.getAttribute('data-nama');
    
    if(namaLengkap){
        document.getElementById('nama_lengkap').value = namaLengkap;
        document.getElementById('user_id_hidden').value = userSelect.value;
    }
}

modal.onclick = function(e){
    if(e.target == modal){
        modal.style.display='none';
    }
}

// Form validation
karyawanForm.addEventListener('submit', function(e) {
    const posisi = document.getElementById('posisi').value.trim();
    const gaji = document.getElementById('gaji_pokok').value;
    
    if(!posisi) {
        e.preventDefault();
        alert('Posisi/Jabatan wajib diisi!');
        document.getElementById('posisi').focus();
        return false;
    }
    
    if(!gaji || parseFloat(gaji) < 0) {
        e.preventDefault();
        alert('Gaji pokok harus diisi dan minimal 0!');
        document.getElementById('gaji_pokok').focus();
        return false;
    }
    
    return true;
});

// Export to Excel function
function exportToExcel() {
    // Simple export - could be enhanced with library
    const table = document.getElementById('tabel');
    const html = table.outerHTML;
    const blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'data-karyawan-dapur-melly.xls';
    a.click();
    URL.revokeObjectURL(url);
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