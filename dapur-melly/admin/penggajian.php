<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

include '../includes/sidebar.php';

// Tampilkan notifikasi
$notification = '';
if (isset($_SESSION['success'])) {
    $notification = '<div class="notification success">
        <span>' . $_SESSION['success'] . '</span>
        <button class="close" onclick="this.parentElement.remove()">&times;</button>
    </div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $notification = '<div class="notification error">
        <span>' . $_SESSION['error'] . '</span>
        <button class="close" onclick="this.parentElement.remove()">&times;</button>
    </div>';
    unset($_SESSION['error']);
}

$bulan  = $_GET['bulan'] ?? date('m');
$tahun  = $_GET['tahun'] ?? date('Y');
$status = $_GET['status'] ?? '';

// HARI INI untuk perbandingan
$today = date('Y-m-d');

// PROSES TAMBAH PENGGAJIAN OTOMATIS JIKA DIKLIK TOMBOL
if (isset($_GET['auto_generate']) && $_GET['auto_generate'] == 'true') {
    // Cek apakah sudah ada penggajian untuk bulan ini
    $checkQuery = mysqli_query($conn, "
        SELECT COUNT(*) as total 
        FROM penggajian 
        WHERE bulan = '$bulan' AND tahun = '$tahun'
    ");
    $checkResult = mysqli_fetch_assoc($checkQuery);
    
    if ($checkResult['total'] > 0) {
        $_SESSION['error'] = "Penggajian untuk $bulan/$tahun sudah ada!";
        header("Location: penggajian.php?bulan=$bulan&tahun=$tahun");
        exit;
    }
    
    // Ambil semua karyawan yang belum memiliki penggajian bulan ini
    $karyawanQuery = mysqli_query($conn, "
        SELECT k.*, u.nama_lengkap 
        FROM karyawan k 
        JOIN users u ON k.user_id = u.id 
        WHERE k.id_karyawan NOT IN (
            SELECT id_karyawan 
            FROM penggajian 
            WHERE bulan = '$bulan' AND tahun = '$tahun'
        )
    ");
    
    $successCount = 0;
    $errorCount = 0;
    
    while ($karyawan = mysqli_fetch_assoc($karyawanQuery)) {
        $id_karyawan = $karyawan['id_karyawan'];
        $gaji_pokok = $karyawan['gaji_pokok'];
        
        // Insert penggajian otomatis
        $insertQuery = mysqli_query($conn, "
            INSERT INTO penggajian (
                id_karyawan, 
                bulan, 
                tahun, 
                tunjangan, 
                potongan, 
                gaji_bersih, 
                status_bayar, 
                status
            ) VALUES (
                '$id_karyawan',
                '$bulan',
                '$tahun',
                '0',
                '0',
                '$gaji_pokok',
                'belum',
                'Belum'
            )
        ");
        
        if ($insertQuery) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    if ($successCount > 0) {
        $_SESSION['success'] = "Berhasil membuat $successCount data penggajian otomatis untuk $bulan/$tahun";
    }
    if ($errorCount > 0) {
        $_SESSION['error'] = "Ada $errorCount data gagal dibuat";
    }
    
    header("Location: penggajian.php?bulan=$bulan&tahun=$tahun");
    exit;
}

// Query dengan logika sorting berdasarkan tanggal gajian terdekat
$where = "WHERE p.bulan = '$bulan' AND p.tahun = '$tahun'";
if ($status != '') {
    $where .= " AND p.status_bayar = '$status'";
}

$q = mysqli_query($conn, "
    SELECT 
        p.id,
        p.bulan,
        p.tahun,
        p.tunjangan,
        p.potongan,
        p.status_bayar,
        p.tgl_bayar_aktual,
        k.gaji_pokok,
        k.no_telp,
        u.nama_lengkap,
        k.tgl_gajian_rutin,
        -- Hitung selisih hari antara tanggal gajian dan hari ini
        CASE 
            WHEN k.tgl_gajian_rutin IS NOT NULL THEN 
                DATEDIFF(
                    STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(CURDATE()), '-', k.tgl_gajian_rutin), '%Y-%m-%d'),
                    CURDATE()
                )
            ELSE 999
        END as days_diff
    FROM penggajian p
    JOIN karyawan k ON p.id_karyawan = k.id_karyawan
    JOIN users u ON k.user_id = u.id
    $where
    ORDER BY 
        CASE 
            WHEN days_diff < 0 THEN days_diff + 365  -- Jika sudah lewat, urutkan berdasarkan tahun depan
            ELSE days_diff 
        END ASC,
        u.nama_lengkap ASC
");

// Untuk menampilkan semua data jika bulan ini kosong
$count = mysqli_num_rows($q);
if ($count == 0) {
    $q = mysqli_query($conn, "
        SELECT 
            p.id,
            p.bulan,
            p.tahun,
            p.tunjangan,
            p.potongan,
            p.status_bayar,
            p.tgl_bayar_aktual,
            k.gaji_pokok,
            k.no_telp,
            u.nama_lengkap,
            k.tgl_gajian_rutin,
            -- Hitung selisih hari antara tanggal gajian dan hari ini
            CASE 
                WHEN k.tgl_gajian_rutin IS NOT NULL THEN 
                    DATEDIFF(
                        STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(CURDATE()), '-', k.tgl_gajian_rutin), '%Y-%m-%d'),
                        CURDATE()
                    )
                ELSE 999
            END as days_diff
        FROM penggajian p
        JOIN karyawan k ON p.id_karyawan = k.id_karyawan
        JOIN users u ON k.user_id = u.id
        ORDER BY 
            CASE 
                WHEN days_diff < 0 THEN days_diff + 365
                ELSE days_diff 
            END ASC,
            p.tahun DESC, 
            p.bulan DESC, 
            u.nama_lengkap ASC
    ");
}

// Cek apakah ada karyawan yang belum memiliki penggajian bulan ini
$karyawanBelumGajiQuery = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM karyawan k 
    WHERE k.id_karyawan NOT IN (
        SELECT id_karyawan 
        FROM penggajian 
        WHERE bulan = '$bulan' AND tahun = '$tahun'
    )
");
$karyawanBelumGaji = mysqli_fetch_assoc($karyawanBelumGajiQuery);

// Array nama bulan
$months = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Penggajian - Dapur Melly</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{
    margin:0;
    font-family:Poppins;
    background:linear-gradient(135deg,#ffe3f0,#fff);
}
.main{
    margin-left:80px;
    padding:40px;
}
h1{color:#ff5f9e}
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
.notification .close {
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
.filter{
    display:flex;
    gap:15px;
    margin-bottom:20px;
    flex-wrap:wrap;
    align-items:center;
}
select,button{
    padding:8px 14px;
    border-radius:20px;
    border:1px solid #ccc;
    font-family:Poppins;
}
.btn{
    background:#ff7eb3;
    color:#fff;
    border:none;
    cursor:pointer;
    transition:0.3s;
    text-decoration:none;
    display:inline-block;
    text-align:center;
}
.btn:hover {
    background:#ff5f9e;
    transform:translateY(-2px);
}
.btn-small {
    padding:5px 10px;
    font-size:12px;
    margin-right:5px;
}
.btn-test {
    background:#28a745;
    margin-left:10px;
}
.btn-test:hover {
    background:#218838;
}
.btn-wa-active {
    background:#25D366;
    opacity:1;
    cursor:pointer;
}
.btn-wa-inactive {
    background:#cccccc;
    opacity:0.4;
    cursor:not-allowed;
}
.btn-auto-generate {
    background:#6f42c1;
}
.btn-auto-generate:hover {
    background:#5a32a3;
}
.btn-tambah-satu {
    background:#17a2b8;
}
.btn-tambah-satu:hover {
    background:#138496;
}
table{
    width:100%;
    background:#fff;
    border-radius:20px;
    box-shadow:0 10px 30px rgba(0,0,0,.1);
    overflow:hidden;
    border-collapse:collapse;
}
th,td{
    padding:12px;
    text-align:left;
    border-bottom:1px solid #eee;
}
thead{
    background:#ff7eb3;
    color:#fff;
}
tbody tr:hover {
    background-color:#fff5f9;
}
.badge{
    padding:4px 10px;
    border-radius:12px;
    font-size:12px;
    display:inline-block;
}
.lunas{background:#4caf50;color:#fff}
.belum{background:#f44336;color:#fff}
.info-message {
    background:#fff3cd;
    border:1px solid #ffeaa7;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
    color:#856404;
}
.auto-generate-message {
    background:#d1ecf1;
    border:1px solid #bee5eb;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
    color:#0c5460;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
}
.loading {
    display:inline-block;
    animation:spin 1s linear infinite;
}
@keyframes spin {
    0% { transform:rotate(0deg); }
    100% { transform:rotate(360deg); }
}
.gajian-indicator {
    padding:3px 8px;
    border-radius:8px;
    font-size:11px;
    font-weight:500;
    margin-top:5px;
    display:inline-block;
}
.gajian-today {
    background:#4CAF50;
    color:white;
    animation:pulse 2s infinite;
}
.gajian-upcoming {
    background:#2196F3;
    color:white;
}
.gajian-past {
    background:#FF9800;
    color:white;
}
@keyframes pulse {
    0% { opacity:1; }
    50% { opacity:0.7; }
    100% { opacity:1; }
}
.tanggal-info {
    font-size:12px;
    color:#666;
    margin-top:3px;
}
</style>
</head>
<body>
<div class="main">
<h1>💰 Penggajian Karyawan</h1>

<?= $notification ?>

<!-- NOTIFIKASI AUTO GENERATE -->
<?php if ($karyawanBelumGaji['total'] > 0 && $count == 0): ?>
<div class="auto-generate-message">
    <div>
        <i class="fas fa-robot"></i> 
        <strong>Ada <?= $karyawanBelumGaji['total'] ?> karyawan yang belum memiliki penggajian untuk <?= $months[$bulan] ?> <?= $tahun ?></strong>
        <br>
        <small style="color:#666;">
            Klik tombol "Buat Otomatis" untuk membuat penggajian semua karyawan sekaligus
        </small>
    </div>
    <div style="margin-top:10px;">
        <a href="penggajian.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&auto_generate=true" 
           class="btn btn-auto-generate"
           onclick="return confirm('Buat penggajian otomatis untuk <?= $karyawanBelumGaji['total'] ?> karyawan?\\n\\nTunjangan dan potongan akan diisi 0 secara default.\\nAnda bisa edit manual nanti.')">
            <i class="fas fa-magic"></i> Buat Otomatis
        </a>
        <a href="tambah_penggajian.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" 
           class="btn btn-tambah-satu">
            <i class="fas fa-user-plus"></i> Tambah Manual
        </a>
    </div>
</div>
<?php elseif ($karyawanBelumGaji['total'] > 0 && $count > 0): ?>
<div class="info-message">
    <i class="fas fa-info-circle"></i> 
    Masih ada <?= $karyawanBelumGaji['total'] ?> karyawan yang belum memiliki penggajian untuk <?= $months[$bulan] ?> <?= $tahun ?>
    <a href="penggajian.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&auto_generate=true" 
       style="color:#6f42c1; margin-left:10px; font-weight:bold;"
       onclick="return confirm('Buat penggajian untuk <?= $karyawanBelumGaji['total'] ?> karyawan yang tersisa?')">
        <i class="fas fa-magic"></i> Tambahkan Otomatis
    </a>
</div>
<?php endif; ?>

<form class="filter" method="GET">
    <div style="display:flex; gap:10px; align-items:center;">
        <select name="tahun">
            <?php 
            $currentYear = date('Y');
            for($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                <option value="<?= $y ?>" <?= ($tahun == $y) ? 'selected' : '' ?>>
                    Tahun <?= $y ?>
                </option>
            <?php endfor ?>
        </select>
        
        <select name="bulan">
            <?php foreach($months as $num => $name): ?>
                <option value="<?= $num ?>" <?= $bulan == $num ? 'selected' : '' ?>>
                    <?= $name ?>
                </option>
            <?php endforeach ?>
        </select>

        <select name="status">
            <option value="">Semua Status</option>
            <option value="belum" <?= $status == 'belum' ? 'selected' : '' ?>>Belum</option>
            <option value="lunas" <?= $status == 'lunas' ? 'selected' : '' ?>>Lunas</option>
        </select>
    </div>

    <button type="submit" class="btn">Filter</button>
    <a href="tambah_penggajian.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn">
        <i class="fas fa-user-plus"></i> Tambah Manual
    </a>
</form>

<?php if (mysqli_num_rows($q) == 0 && ($bulan != '' || $status != '')): ?>
<div class="info-message">
    <i class="fas fa-info-circle"></i> Tidak ada data penggajian untuk periode yang dipilih.
    <a href="penggajian.php" style="color:#ff5f9e; margin-left:10px;">Tampilkan Semua Data</a>
</div>
<?php endif; ?>

<table>
<thead>
<tr>
    <th>Nama</th>
    <th>Tanggal Gajian</th>
    <th>Gaji Pokok</th>
    <th>Tunjangan</th>
    <th>Potongan</th>
    <th>Gaji Bersih</th>
    <th>Periode</th>
    <th>Status</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php 
$totalRows = 0;
while($d = mysqli_fetch_assoc($q)):
    $totalRows++;
    $bersih = ($d['gaji_pokok'] + $d['tunjangan']) - $d['potongan'];
    $monthName = $months[str_pad($d['bulan'], 2, '0', STR_PAD_LEFT)] ?? 'Bulan ' . $d['bulan'];
    
    // LOGIKA TANGGAL GAJIAN
    $tanggalGajian = '';
    $sisaHari = '';
    $isToday = false;
    
    if (!empty($d['tgl_gajian_rutin'])) {
        $tanggalGajian = 'Tiap tanggal <strong>' . $d['tgl_gajian_rutin'] . '</strong>';
        
        // Hitung selisih hari (gunakan days_diff dari query)
        $daysDiff = $d['days_diff'];
        
        if ($daysDiff == 0) {
            $sisaHari = '<span class="gajian-indicator gajian-today"><i class="fas fa-calendar-check"></i> Gajian Hari Ini!</span>';
            $isToday = true;
        } elseif ($daysDiff == 1) {
            $sisaHari = '<span class="gajian-indicator gajian-upcoming"><i class="fas fa-clock"></i> Besok Gajian</span>';
        } elseif ($daysDiff > 1 && $daysDiff <= 7) {
            $sisaHari = '<span class="gajian-indicator gajian-upcoming"><i class="fas fa-hourglass-half"></i> ' . $daysDiff . ' hari lagi</span>';
        } elseif ($daysDiff > 7) {
            $sisaHari = '<span class="gajian-indicator gajian-upcoming">' . $daysDiff . ' hari lagi</span>';
        } elseif ($daysDiff < 0) {
            $sisaHari = '<span class="gajian-indicator gajian-past"><i class="fas fa-history"></i> Lewat ' . abs($daysDiff) . ' hari</span>';
        }
    } else {
        $tanggalGajian = '<span style="color:#999; font-size:12px;">Belum diatur</span>';
        $sisaHari = '<span class="gajian-indicator" style="background:#9E9E9E;color:white;">Belum diatur</span>';
    }
?>
<tr>
    <td>
        <strong><?= htmlspecialchars($d['nama_lengkap']) ?></strong><br>
        <small style="color:#666; font-size:11px;"><i class="fas fa-phone"></i> <?= $d['no_telp'] ?></small>
    </td>
    
    <!-- KOLOM TANGGAL GAJIAN -->
    <td>
        <div style="font-weight:500;"><?= $tanggalGajian ?></div>
        <?= $sisaHari ?>
    </td>
    
    <td>Rp <?= number_format($d['gaji_pokok'], 0, ',', '.') ?></td>
    <td>Rp <?= number_format($d['tunjangan'], 0, ',', '.') ?></td>
    <td>Rp <?= number_format($d['potongan'], 0, ',', '.') ?></td>
    <td><b style="color:#ff5f9e;">Rp <?= number_format($bersih, 0, ',', '.') ?></b></td>
    <td>
        <span style="font-weight:500;"><?= $monthName . ' ' . $d['tahun'] ?></span>
    </td>
    <td>
        <?php 
        $statusText = ucfirst($d['status_bayar']);
        $statusClass = ($d['status_bayar'] == 'lunas') ? 'lunas' : 'belum';
        ?>
        <span class="badge <?= $statusClass ?>">
            <?php if($d['status_bayar'] == 'lunas'): ?>
                <i class="fas fa-check-circle"></i> <?= $statusText ?>
            <?php else: ?>
                <i class="fas fa-clock"></i> <?= $statusText ?>
            <?php endif; ?>
        </span>
        <?php if ($d['tgl_bayar_aktual']): ?>
            <br><small style="color:#666;"><i class="fas fa-calendar-day"></i> <?= date('d/m/Y', strtotime($d['tgl_bayar_aktual'])) ?></small>
        <?php endif; ?>
    </td>
    <td>
        <?php if($d['status_bayar'] == 'belum'): ?>
            <a class="btn btn-small" href="update_gaji.php?id=<?= $d['id'] ?>" 
               onclick="return confirm('Yakin ubah status menjadi LUNAS?')"
               style="background:#4caf50;">
                <i class="fas fa-check"></i> Set Lunas
            </a>
            <div style="margin-top:5px;">
            <?php if($isToday): ?>
                <!-- Tombol WA aktif -->
                <a class="btn btn-small btn-wa-active" href="kirim_wa_gaji.php?id=<?= $d['id'] ?>"
                   onclick="return confirmKirimWA(event, '<?= htmlspecialchars(addslashes($d['nama_lengkap'])) ?>')">
                    <i class="fab fa-whatsapp"></i> Kirim WA
                </a>
            <?php else: ?>
                <!-- Tombol WA non-aktif -->
                <span class="btn btn-small btn-wa-inactive" 
                      title="Tombol aktif hanya pada tanggal gajian">
                    <i class="fab fa-whatsapp"></i> Kirim WA
                </span>
                <?php if(!empty($d['tgl_gajian_rutin'])): ?>
                <small style="display:block; color:#999; font-size:10px; margin-top:3px;">
                    Aktif tiap tanggal <?= $d['tgl_gajian_rutin'] ?>
                </small>
                <?php endif; ?>
            <?php endif; ?>
            </div>
        <?php else: ?>
            <span style="color:#4caf50; font-weight:bold;">
                <i class="fas fa-check-circle"></i> Lunas
            </span>
            <br>
            <a class="btn btn-small" href="kirim_wa_gaji.php?id=<?= $d['id'] ?>" 
               style="background:#6c757d; margin-top:5px;"
               onclick="return confirm('Kirim ulang slip gaji?')">
                <i class="fab fa-whatsapp"></i> Kirim Ulang
            </a>
        <?php endif ?>
    </td>
</tr>
<?php endwhile; ?>
<?php if ($totalRows == 0 && (!$bulan || $bulan == date('m')) && $karyawanBelumGaji['total'] == 0): ?>
<tr>
    <td colspan="9" style="text-align:center; padding:30px; color:#666;">
        <i class="fas fa-database" style="font-size:48px; margin-bottom:10px; display:block; color:#ff7eb3;"></i>
        <strong>Belum ada data penggajian.</strong><br>
        <a href="tambah_penggajian.php" style="color:#ff5f9e; text-decoration:none; font-weight:bold;">
            <i class="fas fa-plus-circle"></i> Tambahkan data penggajian baru
        </a>
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>

<?php if ($totalRows > 0): ?>
<div style="margin-top:20px; color:#666; font-size:14px; padding:15px; background:#f8f9fa; border-radius:10px;">
    <i class="fas fa-info-circle" style="color:#ff5f9e;"></i> 
    <strong>Menampilkan <?= $totalRows ?> data penggajian</strong> 
    <span style="color:#ff5f9e; margin-left:15px;">
        <i class="fas fa-sort-amount-up-alt"></i> Diurutkan berdasarkan tanggal gajian terdekat
    </span>
    <span style="float:right; color:#888; font-size:12px;">
        <i class="fas fa-calendar-alt"></i> <?= date('d F Y') ?>
    </span>
</div>
<?php endif; ?>
</div>

<script>
function confirmKirimWA(e, nama) {
    e.preventDefault();
    const waBtn = e.target.closest('.btn-wa-active');
    const href = waBtn.href;
    
    if (confirm(`Kirim slip gaji via WhatsApp ke ${nama}?`)) {
        const originalHTML = waBtn.innerHTML;
        waBtn.innerHTML = '<i class="fas fa-spinner fa-spin loading"></i> Mengirim...';
        waBtn.disabled = true;
        waBtn.style.opacity = '0.7';
        
        setTimeout(() => {
            window.location.href = href;
        }, 800);
        
        setTimeout(() => {
            waBtn.innerHTML = originalHTML;
            waBtn.disabled = false;
            waBtn.style.opacity = '1';
        }, 4000);
    }
    return false;
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