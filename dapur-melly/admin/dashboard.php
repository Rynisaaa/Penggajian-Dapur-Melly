<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

include '../includes/sidebar.php';

/* AMBIL PRODUK UNGGULAN */
$produk = mysqli_query($conn,"SELECT * FROM produk_unggulan ORDER BY posisi ASC");

/* HITUNG DATA REAL-TIME */
// Total Karyawan
$karyawanQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM karyawan");
$totalKaryawan = mysqli_fetch_assoc($karyawanQuery)['total'];

// Karyawan Masuk Hari Ini (berdasarkan presensi tanggal hari ini)
$today = date('Y-m-d');
$masukHariIniQuery = mysqli_query($conn, "
    SELECT COUNT(DISTINCT p.id_karyawan) as total 
    FROM presensi p 
    WHERE p.tanggal = '$today' AND p.status = 'masuk'
");
$masukHariIni = mysqli_fetch_assoc($masukHariIniQuery)['total'];

// Total Gaji Bulan Ini (gaji pokok semua karyawan untuk bulan ini)
$bulanIni = date('m');
$tahunIni = date('Y');
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

// Format total gaji ke dalam satuan
if ($totalGaji >= 1000000000) {
    $totalGajiFormatted = 'Rp ' . number_format($totalGaji / 1000000000, 1, ',', '.') . ' M';
} elseif ($totalGaji >= 1000000) {
    $totalGajiFormatted = 'Rp ' . number_format($totalGaji / 1000000, 1, ',', '.') . ' JT';
} elseif ($totalGaji >= 1000) {
    $totalGajiFormatted = 'Rp ' . number_format($totalGaji / 1000, 1, ',', '.') . ' RB';
} else {
    $totalGajiFormatted = 'Rp ' . number_format($totalGaji, 0, ',', '.');
}

// PRODUK UNGGULAN
$produkUnggulanQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk_unggulan");
$totalProduk = mysqli_fetch_assoc($produkUnggulanQuery)['total'];

// ===== TAMBAHAN BARU: TOTAL PENDAPATAN BULAN INI =====
$pendapatanBulanIniQuery = mysqli_query($conn, "
    SELECT pendapatan 
    FROM laporan_bulanan 
    WHERE bulan = '$bulanIni' AND tahun = '$tahunIni'
    LIMIT 1
");

$pendapatanBulanIni = 0;
if ($row = mysqli_fetch_assoc($pendapatanBulanIniQuery)) {
    $pendapatanBulanIni = $row['pendapatan'];
}

// Format total pendapatan ke dalam satuan
if ($pendapatanBulanIni >= 1000000000) {
    $pendapatanFormatted = 'Rp ' . number_format($pendapatanBulanIni / 1000000000, 1, ',', '.') . ' M';
} elseif ($pendapatanBulanIni >= 1000000) {
    $pendapatanFormatted = 'Rp ' . number_format($pendapatanBulanIni / 1000000, 1, ',', '.') . ' JT';
} elseif ($pendapatanBulanIni >= 1000) {
    $pendapatanFormatted = 'Rp ' . number_format($pendapatanBulanIni / 1000, 1, ',', '.') . ' RB';
} else {
    $pendapatanFormatted = 'Rp ' . number_format($pendapatanBulanIni, 0, ',', '.');
}
// ===== END TAMBAHAN =====

// Data untuk chart absen (7 hari terakhir)
$absenLabels = [];
$absenMasuk = [];
$absenIzin = [];
$absenSakit = [];
$absenAlpa = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $absenLabels[] = date('d/m', strtotime($date));
    
    // Query untuk setiap status
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

// Data untuk donat chart (total 30 hari)
$startDate30 = date('Y-m-d', strtotime('-30 days'));
$queryTotalMasuk = mysqli_query($conn, "SELECT COUNT(*) as total FROM presensi WHERE tanggal >= '$startDate30' AND status = 'masuk'");
$totalMasuk30 = mysqli_fetch_assoc($queryTotalMasuk)['total'];

$queryTotalIzin = mysqli_query($conn, "SELECT COUNT(*) as total FROM presensi WHERE tanggal >= '$startDate30' AND status = 'izin'");
$totalIzin30 = mysqli_fetch_assoc($queryTotalIzin)['total'];

$queryTotalSakit = mysqli_query($conn, "SELECT COUNT(*) as total FROM presensi WHERE tanggal >= '$startDate30' AND status = 'sakit'");
$totalSakit30 = mysqli_fetch_assoc($queryTotalSakit)['total'];

$queryTotalAlpa = mysqli_query($conn, "SELECT COUNT(*) as total FROM presensi WHERE tanggal >= '$startDate30' AND status = 'alpa'");
$totalAlpa30 = mysqli_fetch_assoc($queryTotalAlpa)['total'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Admin - Dapur Melly</title>

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
}

.main{
    margin-left:80px;
    padding:40px;
}

/* HEADER */
.header h1{color:#ff5f9e;margin:0}
.header p{color:#777}

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
    cursor:pointer;
    text-decoration:none;
    display:block;
}
.stat-card:hover{
    transform:translateY(-6px);
    box-shadow:0 15px 35px rgba(255,126,179,0.3);
}
.stat-card i{
    font-size:26px;
    margin-bottom:10px;
}
.stat-card h4{
    margin:5px 0;
    font-size:15px;
    opacity:0.9;
}
.stat-card h2{
    margin:10px 0 0 0;
    font-size:28px;
}
.stat-card .subtext{
    font-size:11px;
    opacity:0.8;
    margin-top:5px;
}

/* ===================== */
/* PODIUM */
/* ===================== */
.products{margin-top:60px}
.podium{
    display:flex;
    justify-content:center;
    align-items:flex-end;
    gap:30px;
}

.card{
    background:#fff;
    width:220px;
    border-radius:20px;
    padding:15px;
    text-align:center;
    box-shadow:0 10px 30px rgba(0,0,0,.1);
    transition:.35s;
    position:relative;
}

.card:hover{
    transform:translateY(-18px) scale(1.07);
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
    height:140px;
    object-fit:cover;
    border-radius:14px;
}

.badge{
    position:absolute;
    top:-14px;
    left:50%;
    transform:translateX(-50%);
    background:var(--pink);
    color:#fff;
    padding:6px 16px;
    border-radius:20px;
    font-size:13px;
    box-shadow:var(--shadow);
}

button{
    margin-top:10px;
    background:var(--pink);
    border:none;
    padding:8px 18px;
    color:#fff;
    border-radius:20px;
    cursor:pointer;
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
.chart-box h3{color:#ff5f9e}

/* ===================== */
/* MODAL */
/* ===================== */
.modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.6);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:9999;
}
.modal form{
    background:#fff;
    padding:30px;
    border-radius:20px;
    width:320px;
}
.modal input{
    width:100%;
    padding:8px;
    margin-bottom:10px;
}
</style>
</head>

<body>

<div class="main">

<div class="header">
    <h1>Dashboard Admin</h1>
    <p>Selamat datang, <b><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></b> 🌸</p>
</div>

<!-- STAT -->
<div class="stats">
    <a href="data_karyawan.php" class="stat-card">
        <i class="fa fa-users"></i>
        <h4>Total Karyawan</h4>
        <h2><?= $totalKaryawan ?></h2>
        <div class="subtext">Klik untuk lihat detail</div>
    </a>
    
    <a href="presensi.php?tanggal=<?= date('Y-m-d') ?>" class="stat-card">
        <i class="fa fa-calendar-check"></i>
        <h4>Masuk Hari Ini</h4>
        <h2><?= $masukHariIni ?></h2>
        <div class="subtext">Klik untuk lihat presensi</div>
    </a>
    
    <a href="penggajian.php?bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>" class="stat-card">
        <i class="fa fa-wallet"></i>
        <h4>Total Gaji Bulan Ini</h4>
        <h2><?= $totalGajiFormatted ?></h2>
        <div class="subtext">Klik untuk lihat penggajian</div>
    </a>
    
    <a href="pendapatan.php" class="stat-card">
        <i class="fa fa-money-bill"></i>
        <h4>Pendapatan Bulan Ini</h4>
        <h2><?= $pendapatanFormatted ?></h2>
        <div class="subtext">Klik untuk lihat laporan</div>
    </a>
</div>

<!-- PODIUM -->
<div class="products" id="produk-section">
<h3 style="color:#ff5f9e">🏆 Produk Unggulan</h3>

<div class="podium">
<?php 
mysqli_data_seek($produk, 0); // Reset pointer
while($p = mysqli_fetch_assoc($produk)): 
?>
<div class="card rank-<?= $p['posisi'] ?>">
    <div class="badge">#<?= $p['posisi'] ?></div>
    <img src="../assets/<?= $p['foto'] ?>">
    <h4><?= htmlspecialchars($p['nama_produk']) ?></h4>
    <p>Terjual: <?= $p['jumlah_terjual'] ?></p>
    <button onclick="editProduk(
        '<?= $p['id'] ?>',
        '<?= htmlspecialchars($p['nama_produk'],ENT_QUOTES) ?>',
        '<?= $p['jumlah_terjual'] ?>',
        '<?= $p['foto'] ?>'
    )">Edit</button>
</div>
<?php endwhile ?>
</div>
</div>

<!-- CHART -->
<div class="chart-area">
    <div class="chart-box">
        <h3>📊 Grafik Produk</h3>
        <canvas id="produkChart"></canvas>
    </div>
    <div class="chart-box">
        <h3>🕒 Rekap Absen (7 Hari)</h3>
        <canvas id="absenChart"></canvas>
    </div>
</div>

</div>

<!-- MODAL -->
<div class="modal" id="modal">
<form method="POST" action="save_produk.php" enctype="multipart/form-data">
<input type="hidden" name="id" id="pid">
<input type="hidden" name="foto_lama" id="foto_lama">

<label>Nama Produk</label>
<input name="nama" id="pnama" required>

<label>Jumlah Terjual</label>
<input name="jual" id="pjual" type="number" required>

<label>Foto</label>
<input type="file" name="foto">

<button>Simpan</button>
</form>
</div>

<script>
function editProduk(id,nama,jual,foto){
    pid.value=id;
    pnama.value=nama;
    pjual.value=jual;
    foto_lama.value=foto;
    modal.style.display='flex';
}
modal.onclick=e=>{if(e.target==modal)modal.style.display='none'}

// Produk Chart
new Chart(document.getElementById('produkChart'),{
    type:'bar',
    data:{
        labels:[
            <?php 
            mysqli_data_seek($produk, 0);
            while($p = mysqli_fetch_assoc($produk)): 
                echo "'" . addslashes($p['nama_produk']) . "',";
            endwhile;
            ?>
        ],
        datasets:[{
            label:'Jumlah Terjual',
            data:[
                <?php 
                mysqli_data_seek($produk, 0);
                while($p = mysqli_fetch_assoc($produk)): 
                    echo $p['jumlah_terjual'] . ",";
                endwhile;
                ?>
            ],
            backgroundColor:['#ff7eb3','#ffd36e','#7ecbff'],
            borderRadius:10
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

// Absen Chart (Line Chart untuk 7 hari)
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
                tension:0.4
            },
            {
                label:'Izin',
                data:<?= json_encode($absenIzin) ?>,
                borderColor:'#ffd36e',
                backgroundColor:'rgba(255,211,110,0.1)',
                fill:true,
                tension:0.4
            },
            {
                label:'Sakit',
                data:<?= json_encode($absenSakit) ?>,
                borderColor:'#7ecbff',
                backgroundColor:'rgba(126,203,255,0.1)',
                fill:true,
                tension:0.4
            },
            {
                label:'Alpa',
                data:<?= json_encode($absenAlpa) ?>,
                borderColor:'#ff9a9a',
                backgroundColor:'rgba(255,154,154,0.1)',
                fill:true,
                tension:0.4
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

// Donut Chart untuk total 30 hari (opsional, bisa ditambahkan di elemen baru)
const absenDonutChart = new Chart(document.getElementById('absenDonutChart'),{
    type:'doughnut',
    data:{
        labels:['Masuk','Izin','Sakit','Alpa'],
        datasets:[{
            data:[<?= $totalMasuk30 ?>, <?= $totalIzin30 ?>, <?= $totalSakit30 ?>, <?= $totalAlpa30 ?>],
            backgroundColor:['#ff7eb3','#ffd36e','#7ecbff','#ff9a9a']
        }]
    },
    options:{
        cutout:'65%',
        plugins:{
            legend:{position:'bottom'}
        }
    }
});

// Tambah chart donut jika diperlukan (tambahkan elemen canvas dengan id absenDonutChart)
</script>

</body>
</html>