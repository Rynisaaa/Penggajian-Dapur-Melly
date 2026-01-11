<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

include '../includes/sidebar.php';

/* HITUNG DATA REAL-TIME */
$today = date('Y-m-d');
$bulanIni = date('m');
$tahunIni = date('Y');

// 1. Total Pengeluaran Gaji Bulan Ini
$totalGajiQuery = mysqli_query($conn, "
    SELECT 
        COALESCE(SUM(
            CASE 
                WHEN pg.id IS NOT NULL THEN pg.gaji_bersih
                ELSE k.gaji_pokok
            END
        ), 0) as total_gaji
    FROM karyawan k
    LEFT JOIN penggajian pg ON k.id_karyawan = pg.id_karyawan 
        AND pg.bulan = '$bulanIni' AND pg.tahun = '$tahunIni'
");
$totalGajiResult = mysqli_fetch_assoc($totalGajiQuery);
$totalGaji = $totalGajiResult['total_gaji'] ?: 0;

// Format total gaji
if ($totalGaji >= 1000000000) {
    $totalGajiFormatted = 'Rp ' . number_format($totalGaji / 1000000000, 1, ',', '.') . ' M';
} elseif ($totalGaji >= 1000000) {
    $totalGajiFormatted = 'Rp ' . number_format($totalGaji / 1000000, 1, ',', '.') . ' JT';
} elseif ($totalGaji >= 1000) {
    $totalGajiFormatted = 'Rp ' . number_format($totalGaji / 1000, 1, ',', '.') . ' RB';
} else {
    $totalGajiFormatted = 'Rp ' . number_format($totalGaji, 0, ',', '.');
}

// 2. Persentase Kehadiran Bulan Ini
$totalHariKerja = date('t');
$totalKaryawan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM karyawan"))['total'];

$kehadiranBulanIni = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM presensi 
    WHERE MONTH(tanggal) = '$bulanIni' 
    AND YEAR(tanggal) = '$tahunIni' 
    AND status = 'masuk'
"))['total'];

$maxKehadiran = $totalKaryawan * $totalHariKerja;
$persentaseKehadiran = $maxKehadiran > 0 ? round(($kehadiranBulanIni / $maxKehadiran) * 100, 1) : 0;

// 3. Status Gaji (Lunas/Belum)
$gajiLunas = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM penggajian 
    WHERE bulan = '$bulanIni' 
    AND tahun = '$tahunIni' 
    AND (status_bayar = 'lunas' OR status = 'Lunas')
"))['total'];

$totalGajiData = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM penggajian 
    WHERE bulan = '$bulanIni' AND tahun = '$tahunIni'
"))['total'];

$statusGaji = $totalGajiData > 0 ? round(($gajiLunas / $totalGajiData) * 100, 0) : 0;

// 4. Nama Karyawan Teladan
$karyawanTeladan = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT u.nama_lengkap, COUNT(p.id) as total_hadir
    FROM presensi p
    JOIN karyawan k ON p.id_karyawan = k.id_karyawan
    JOIN users u ON k.user_id = u.id
    WHERE MONTH(p.tanggal) = '$bulanIni' 
    AND YEAR(p.tanggal) = '$tahunIni' 
    AND p.status = 'masuk'
    GROUP BY p.id_karyawan
    ORDER BY total_hadir DESC
    LIMIT 1
"));

$namaKaryawanTeladan = $karyawanTeladan ? $karyawanTeladan['nama_lengkap'] : 'Belum ada data';
$totalHadirTeladan = $karyawanTeladan ? $karyawanTeladan['total_hadir'] : 0;

/* GRAFIK KEHADIRAN 7 HARI TERAKHIR */
$absenLabels = [];
$absenMasuk = [];
$absenIzin = [];
$absenSakit = [];
$absenAlpa = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $absenLabels[] = date('d/m', strtotime($date));
    
    $queryMasuk = mysqli_query($conn, "SELECT COUNT(*) as total FROM presensi WHERE tanggal = '$date' AND status = 'masuk'");
    $masuk = mysqli_fetch_assoc($queryMasuk)['total'];
    $absenMasuk[] = $masuk;
    
    $queryIzin = mysqli_query($conn, "SELECT COUNT(*) as total FROM presensi WHERE tanggal = '$date' AND status = 'izin'");
    $izin = mysqli_fetch_assoc($queryIzin)['total'];
    $absenIzin[] = $izin;
    
    $querySakit = mysqli_query($conn, "SELECT COUNT(*) as total FROM presensi WHERE tanggal = '$date' AND status = 'sakit'");
    $sakit = mysqli_fetch_assoc($querySakit)['total'];
    $absenSakit[] = $sakit;
    
    $queryAlpa = mysqli_query($conn, "SELECT COUNT(*) as total FROM presensi WHERE tanggal = '$date' AND status = 'alpa'");
    $alpa = mysqli_fetch_assoc($queryAlpa)['total'];
    $absenAlpa[] = $alpa;
}

/* KARYAWAN AKAN GAJIAN */
$karyawanAkanGajian = mysqli_query($conn, "
    SELECT 
        u.nama_lengkap,
        k.tgl_gajian,
        k.tgl_gajian_rutin,
        k.gaji_pokok,
        DATEDIFF(
            COALESCE(
                k.tgl_gajian,
                CONCAT('$tahunIni-', '$bulanIni-', k.tgl_gajian_rutin)
            ), 
            CURDATE()
        ) as hari_menuju_gajian
    FROM karyawan k
    JOIN users u ON k.user_id = u.id
    WHERE k.tgl_gajian IS NOT NULL OR k.tgl_gajian_rutin IS NOT NULL
    ORDER BY 
        CASE 
            WHEN k.tgl_gajian IS NOT NULL THEN k.tgl_gajian
            ELSE CONCAT('$tahunIni-', '$bulanIni-', k.tgl_gajian_rutin)
        END ASC
    LIMIT 5
");

/* PRODUK UNGGULAN */
$produk = mysqli_query($conn,"SELECT * FROM produk_unggulan ORDER BY posisi ASC LIMIT 3");
$totalProduk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk_unggulan"))['total'];

$produkLabels = [];
$produkData = [];
while($p = mysqli_fetch_assoc($produk)) {
    $produkLabels[] = $p['nama_produk'];
    $produkData[] = $p['jumlah_terjual'];
}
mysqli_data_seek($produk, 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Owner - Dapur Melly</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
    --pink:#ff7eb3;
    --pink-soft:#ffe3f0;
    --peach:#ffb199;
    --white:#fff;
    --shadow:0 12px 30px rgba(0,0,0,.08);
}

*{box-sizing:border-box}

body{
    margin:0;
    font-family:Poppins;
    background:linear-gradient(135deg,var(--pink-soft),#fff);
    overflow-x:hidden;
}

/* SIDEBAR ADJUSTMENT */
.main{
    margin-left:20px;
    padding:40px;
    transition:margin-left 0.35s ease;
    min-height:100vh;
}

.sidebar-wrapper:hover ~ .main,
.sidebar-hover-zone:hover ~ .main{
    margin-left:280px;
}

/* HEADER */
.header{
    background:linear-gradient(135deg, rgba(255,126,179,0.1), rgba(255,177,153,0.1));
    padding:25px 30px;
    border-radius:20px;
    margin-bottom:30px;
    border-left:5px solid var(--pink);
}
.header h1{
    color:#ff5f9e;
    margin:0 0 10px 0;
    display:flex;
    align-items:center;
    gap:10px;
}
.header p{
    color:#666;
    margin:5px 0;
    font-size:15px;
}

/* OWNER BADGE */
.owner-badge{
    background:#6c757d;
    color:white;
    padding:6px 15px;
    border-radius:20px;
    font-size:13px;
    font-weight:600;
    display:inline-block;
}

/* ===================== */
/* STAT CARD */
/* ===================== */
.stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-top:30px;
}
.stat-card{
    background:linear-gradient(135deg,var(--pink),var(--peach));
    color:#fff;
    padding:25px;
    border-radius:20px;
    box-shadow:var(--shadow);
    transition:.3s;
    position:relative;
    overflow:hidden;
}
.stat-card::before{
    content:'';
    position:absolute;
    top:0;
    left:0;
    right:0;
    height:4px;
    background:rgba(255,255,255,0.3);
}
.stat-card:hover{
    transform:translateY(-6px);
    box-shadow:0 15px 35px rgba(255,126,179,0.3);
}
.stat-card i{
    font-size:28px;
    margin-bottom:15px;
    opacity:0.9;
}
.stat-card h4{
    margin:5px 0;
    font-size:15px;
    opacity:0.9;
    font-weight:400;
}
.stat-card h2{
    margin:10px 0 0 0;
    font-size:30px;
    font-weight:700;
}
.stat-card .subtext{
    font-size:12px;
    opacity:0.8;
    margin-top:8px;
    font-weight:300;
}

/* ===================== */
/* GAJI AKAN DATANG */
/* ===================== */
.upcoming-salary{
    margin-top:60px;
}
.upcoming-salary h3{
    color:#ff5f9e;
    margin-bottom:20px;
    display:flex;
    align-items:center;
    gap:10px;
}
.salary-list{
    background:#fff;
    border-radius:20px;
    padding:25px;
    box-shadow:var(--shadow);
    margin-top:10px;
}
.salary-item{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:18px 0;
    border-bottom:1px solid #f0f0f0;
    transition:0.3s;
}
.salary-item:hover{
    background:#f9f9f9;
    padding-left:10px;
    padding-right:10px;
    margin:0 -10px;
    border-radius:10px;
}
.salary-item:last-child{
    border-bottom:none;
}
.salary-info h4{
    margin:0;
    color:#333;
    font-size:16px;
    font-weight:600;
}
.salary-info p{
    margin:6px 0 0 0;
    color:#666;
    font-size:14px;
}
.salary-date{
    background:#ffe3f0;
    padding:8px 16px;
    border-radius:15px;
    font-weight:600;
    color:#ff5f9e;
    font-size:14px;
    min-width:120px;
    text-align:center;
}
.salary-date.soon{
    background:#fff3cd;
    color:#856404;
}
.salary-date.today{
    background:#d4edda;
    color:#155724;
}

/* ===================== */
/* PODIUM PRODUK */
/* ===================== */
.products{
    margin-top:60px;
    background:#fff;
    border-radius:25px;
    padding:30px;
    box-shadow:var(--shadow);
}
.products h3{
    color:#ff5f9e;
    margin-top:0;
    display:flex;
    align-items:center;
    gap:10px;
}
.podium{
    display:flex;
    justify-content:center;
    align-items:flex-end;
    gap:30px;
    margin-top:30px;
}

.card{
    background:#fff;
    width:240px;
    border-radius:20px;
    padding:20px;
    text-align:center;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    transition:.35s;
    position:relative;
    border:2px solid #f0f0f0;
}

.card:hover{
    transform:translateY(-18px) scale(1.07);
    box-shadow:0 20px 40px rgba(0,0,0,.15);
    border-color:var(--pink);
}

/* PAKSA POSISI PODIUM */
.rank-1{
    order:2;
    transform:translateY(-55px) scale(1.12);
    border:4px solid var(--pink);
    z-index:2;
}
.rank-2{order:1}
.rank-3{order:3}

.card img{
    width:100%;
    height:160px;
    object-fit:cover;
    border-radius:14px;
    margin-bottom:15px;
    border:3px solid #fff;
    box-shadow:0 5px 15px rgba(0,0,0,.1);
}

.badge{
    position:absolute;
    top:-14px;
    left:50%;
    transform:translateX(-50%);
    background:var(--pink);
    color:#fff;
    padding:8px 20px;
    border-radius:20px;
    font-size:14px;
    font-weight:600;
    box-shadow:var(--shadow);
    min-width:60px;
}

.card h4{
    margin:10px 0 5px 0;
    color:#333;
    font-size:18px;
    font-weight:600;
}
.card p{
    margin:5px 0;
    color:#666;
    font-size:14px;
}

/* ===================== */
/* CHART */
/* ===================== */
.chart-area{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:30px;
    margin-top:60px;
}
.chart-box{
    background:#fff;
    padding:30px;
    border-radius:22px;
    box-shadow:var(--shadow);
}
.chart-box h3{
    color:#ff5f9e;
    margin-top:0;
    margin-bottom:25px;
    display:flex;
    align-items:center;
    gap:10px;
}

/* SIMPLE NOTICE */
.simple-notice{
    background:rgba(108,117,125,0.05);
    padding:15px 20px;
    border-radius:15px;
    margin-top:30px;
    text-align:center;
    color:#6c757d;
    font-size:14px;
    border:1px dashed rgba(108,117,125,0.2);
}
.simple-notice i{
    margin-right:8px;
    color:#6c757d;
}

/* RESPONSIVE */
@media (max-width:1100px){
    .chart-area{
        grid-template-columns:1fr;
    }
}
@media (max-width:768px){
    .podium{
        flex-direction:column;
        align-items:center;
        gap:40px;
    }
    .rank-1,.rank-2,.rank-3{
        order:0;
        transform:none !important;
        width:100%;
        max-width:300px;
    }
    .card:hover{
        transform:translateY(-8px) scale(1.02);
    }
    .main{
        padding:25px;
    }
}
</style>
</head>

<body>

<!-- Sidebar auto-hide (from includes/sidebar.php) -->

<div class="main">

<div class="header">
    <h1>
        <i class="fas fa-crown"></i> Dashboard Owner
        <span class="owner-badge">View Only</span>
    </h1>
    <p>Halo, <b><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></b>! Anda login sebagai Owner.</p>
    <p><i class="fas fa-eye"></i> Akses hanya untuk melihat data. Untuk edit data, hubungi Admin.</p>
</div>

<!-- 4 CARD STATISTIK -->
<div class="stats">
    <div class="stat-card">
        <i class="fa fa-wallet"></i>
        <h4>Total Pengeluaran Gaji</h4>
        <h2><?= $totalGajiFormatted ?></h2>
        <div class="subtext">Bulan <?= date('F Y') ?></div>
    </div>
    
    <div class="stat-card">
        <i class="fa fa-chart-line"></i>
        <h4>Persentase Kehadiran</h4>
        <h2><?= $persentaseKehadiran ?>%</h2>
        <div class="subtext">Bulan <?= date('F Y') ?></div>
    </div>
    
    <div class="stat-card">
        <i class="fa fa-money-check"></i>
        <h4>Status Gaji</h4>
        <h2><?= $statusGaji ?>% Lunas</h2>
        <div class="subtext"><?= $gajiLunas ?> dari <?= $totalGajiData ?> karyawan</div>
    </div>
    
    <div class="stat-card">
        <i class="fa fa-crown"></i>
        <h4>Karyawan Teladan</h4>
        <h2 style="font-size:24px;"><?= htmlspecialchars(mb_strimwidth($namaKaryawanTeladan, 0, 20, '...')) ?></h2>
        <div class="subtext"><?= $totalHadirTeladan ?> hari hadir bulan ini</div>
    </div>
</div>

<!-- GRAFIK KEHADIRAN -->
<div class="chart-area">
    <div class="chart-box">
        <h3><i class="fas fa-chart-line"></i> Grafik Kehadiran 7 Hari</h3>
        <canvas id="absenChart"></canvas>
        <div class="simple-notice">
            <i class="fas fa-info-circle"></i> Data kehadiran real-time dari database
        </div>
    </div>
    <div class="chart-box">
        <h3><i class="fas fa-star"></i> Grafik Produk Unggulan</h3>
        <canvas id="produkChart"></canvas>
        <div class="simple-notice">
            <i class="fas fa-info-circle"></i> Total <?= $totalProduk ?> produk unggulan
        </div>
    </div>
</div>

<!-- KARYAWAN AKAN GAJIAN -->
<div class="upcoming-salary">
    <h3><i class="fas fa-calendar-day"></i> Karyawan Akan Gajian</h3>
    <div class="salary-list">
        <?php if(mysqli_num_rows($karyawanAkanGajian) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($karyawanAkanGajian)): 
                $hariMenuju = $row['hari_menuju_gajian'];
                $tglGajian = $row['tgl_gajian'] ?: date('Y-m-d', strtotime($tahunIni . '-' . $bulanIni . '-' . $row['tgl_gajian_rutin']));
                $dateClass = '';
                
                if ($hariMenuju == 0) {
                    $dateClass = 'today';
                    $statusText = 'Gajian Hari Ini';
                } elseif ($hariMenuju <= 3) {
                    $dateClass = 'soon';
                    $statusText = $hariMenuju . ' hari lagi';
                } else {
                    $statusText = $hariMenuju . ' hari lagi';
                }
            ?>
            <div class="salary-item">
                <div class="salary-info">
                    <h4><?= htmlspecialchars($row['nama_lengkap']) ?></h4>
                    <p>Gaji: Rp <?= number_format($row['gaji_pokok'], 0, ',', '.') ?></p>
                </div>
                <div class="salary-date <?= $dateClass ?>">
                    <?= date('d M', strtotime($tglGajian)) ?><br>
                    <small><?= $statusText ?></small>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding:30px; color:#999;">
                <i class="fa fa-calendar" style="font-size:48px; margin-bottom:15px; display:block; opacity:0.3;"></i>
                <p>Tidak ada jadwal gajian mendatang</p>
            </div>
        <?php endif; ?>
        <div class="simple-notice" style="margin-top:20px;">
            <i class="fas fa-clock"></i> Update otomatis setiap hari
        </div>
    </div>
</div>

<!-- PRODUK UNGGULAN -->
<div class="products">
    <h3><i class="fas fa-trophy"></i> Produk Unggulan</h3>
    <div class="podium">
    <?php 
    $counter = 0;
    while($p = mysqli_fetch_assoc($produk)): 
        $counter++;
        if($counter <= 3):
    ?>
    <div class="card rank-<?= $p['posisi'] ?>">
        <div class="badge">Peringkat #<?= $p['posisi'] ?></div>
        <img src="../assets/<?= $p['foto'] ?>" alt="<?= htmlspecialchars($p['nama_produk']) ?>" 
             onerror="this.src='../assets/default-product.jpg'">
        <h4><?= htmlspecialchars($p['nama_produk']) ?></h4>
        <p><i class="fas fa-shopping-cart"></i> Terjual: <?= number_format($p['jumlah_terjual'], 0, ',', '.') ?></p>
    </div>
    <?php 
        endif;
    endwhile; 
    ?>
    </div>
    <div class="simple-notice">
        <i class="fas fa-info-circle"></i> Hanya dapat melihat. Untuk edit produk, hubungi Admin.
    </div>
</div>

<!-- SIMPLE NOTICE -->
<div class="simple-notice" style="margin-top:40px;">
    <i class="fas fa-user-shield"></i> 
    <strong>Akses Owner:</strong> Hanya dapat melihat data. Untuk perubahan data, silakan hubungi Administrator sistem.
</div>

</div>

<script>
// Grafik Kehadiran 7 Hari
new Chart(document.getElementById('absenChart'),{
    type:'line',
    data:{
        labels:<?= json_encode($absenLabels) ?>,
        datasets:[
            {
                label:'Masuk',
                data:<?= json_encode($absenMasuk) ?>,
                borderColor:'#ff7eb3',
                backgroundColor:'rgba(255,126,179,0.1)',
                fill:true,
                tension:0.4,
                borderWidth:3
            },
            {
                label:'Izin',
                data:<?= json_encode($absenIzin) ?>,
                borderColor:'#ffd36e',
                backgroundColor:'rgba(255,211,110,0.1)',
                fill:true,
                tension:0.4,
                borderWidth:2
            },
            {
                label:'Sakit',
                data:<?= json_encode($absenSakit) ?>,
                borderColor:'#7ecbff',
                backgroundColor:'rgba(126,203,255,0.1)',
                fill:true,
                tension:0.4,
                borderWidth:2
            },
            {
                label:'Alpa',
                data:<?= json_encode($absenAlpa) ?>,
                borderColor:'#ff9a9a',
                backgroundColor:'rgba(255,154,154,0.1)',
                fill:true,
                tension:0.4,
                borderWidth:2
            }
        ]
    },
    options:{
        responsive:true,
        plugins:{
            legend:{
                position:'top',
            }
        },
        scales:{
            y:{
                beginAtZero:true,
                title:{
                    display:true,
                    text:'Jumlah Karyawan'
                }
            }
        }
    }
});

// Grafik Produk Unggulan
new Chart(document.getElementById('produkChart'),{
    type:'bar',
    data:{
        labels:<?= json_encode($produkLabels) ?>,
        datasets:[{
            label:'Jumlah Terjual',
            data:<?= json_encode($produkData) ?>,
            backgroundColor:['#ff7eb3','#ffd36e','#7ecbff'],
            borderRadius:10,
            borderWidth:1,
            borderColor:'rgba(0,0,0,0.1)'
        }]
    },
    options:{
        plugins:{
            legend:{display:false}
        },
        scales:{
            y:{
                beginAtZero:true,
                title:{
                    display:true,
                    text:'Jumlah Terjual'
                }
            }
        }
    }
});

// Animasi untuk card statistik
document.addEventListener('DOMContentLoaded', function() {
    // Animasi stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 * (index + 1));
    });

    // Animasi podium cards
    const podiumCards = document.querySelectorAll('.card');
    podiumCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px) scale(0.95)';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0) scale(1)';
            if(card.classList.contains('rank-1')) {
                card.style.transform = 'translateY(-55px) scale(1.12)';
            }
        }, 300 + (200 * index));
    });
});

// Prevent any accidental clicks on interactive elements
document.addEventListener('click', function(e) {
    if(e.target.closest('.stat-card') || e.target.closest('.card')) {
        console.log('Owner dashboard - View only mode');
        // Bisa ditambahkan tooltip atau notifikasi sederhana
        // tapi tidak ada alert yang mengganggu
    }
});
</script>

</body>
</html>