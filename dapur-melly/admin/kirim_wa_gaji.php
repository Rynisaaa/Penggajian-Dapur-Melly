<?php
session_start();
require '../config/koneksi.php';

// Cek apakah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    $_SESSION['error'] = "ID tidak valid";
    header("Location: penggajian.php");
    exit;
}

// Ambil data penggajian
$q = mysqli_query($conn, "
    SELECT 
        p.*,
        k.gaji_pokok,
        k.no_telp,
        u.nama_lengkap
    FROM penggajian p
    JOIN karyawan k ON p.id_karyawan = k.id_karyawan
    JOIN users u ON k.user_id = u.id
    WHERE p.id='$id'
");

if (mysqli_num_rows($q) == 0) {
    $_SESSION['error'] = "Data penggajian tidak ditemukan";
    header("Location: penggajian.php");
    exit;
}

$d = mysqli_fetch_assoc($q);

// Hitung gaji bersih
$bersih = ($d['gaji_pokok'] + $d['tunjangan']) - $d['potongan'];

// Konversi angka bulan ke nama bulan
$bulanIndonesia = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$nama_bulan = $bulanIndonesia[str_pad($d['bulan'], 2, '0', STR_PAD_LEFT)] ?? 'Bulan ' . $d['bulan'];

// Format nomor telepon
$no_telp = trim($d['no_telp']);
$original_no = $no_telp;

// Bersihkan karakter non-numerik
$no_telp = preg_replace('/[^0-9]/', '', $no_telp);

// Format ke 62xxxxxxxxxxx
if (strlen($no_telp) > 0) {
    if (substr($no_telp, 0, 1) === '0') {
        $no_telp = '62' . substr($no_telp, 1);
    } elseif (substr($no_telp, 0, 2) !== '62') {
        $no_telp = '62' . $no_telp;
    }
}

// Validasi nomor
if (strlen($no_telp) < 10) {
    $_SESSION['error'] = "Nomor WhatsApp tidak valid: $original_no";
    header("Location: penggajian.php");
    exit;
}

// Buat pesan WhatsApp
$pesan = 
"*SLIP GAJI - DAPUR MELLY*

👤 *Nama:* {$d['nama_lengkap']}
📅 *Periode:* {$nama_bulan} {$d['tahun']}

💵 *RINCIAN GAJI:*
─────────────────
• Gaji Pokok: Rp " . number_format($d['gaji_pokok'], 0, ',', '.') . "
• Tunjangan:  Rp " . number_format($d['tunjangan'], 0, ',', '.') . "
• Potongan:   Rp " . number_format($d['potongan'], 0, ',', '.') . "
─────────────────
💰 *TOTAL: Rp " . number_format($bersih, 0, ',', '.') . "*

✅ *Status:* LUNAS
📆 *Tanggal:* " . date('d/m/Y') . "

_*Dapur Melly*_
_Bakery & Catering_";

// Token API Fonnte BARU
$token = "MFipr6soTB2SQQ3Y1vdC";

// Data untuk dikirim ke Fonnte API
$data = [
    'target' => $no_telp,
    'message' => $pesan,
    'countryCode' => '62'
];

// Kirim ke API Fonnte
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.fonnte.com/send');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: $token"
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug info
$response_data = json_decode($response, true);

// Cek respon
if ($http_code == 200) {
    if (isset($response_data['status']) && $response_data['status'] == true) {
        $_SESSION['success'] = "✅ Slip gaji berhasil dikirim ke WhatsApp {$d['nama_lengkap']}";
        
        // Update status jadi lunas jika belum
        if ($d['status_bayar'] == 'belum') {
            mysqli_query($conn, "
                UPDATE penggajian 
                SET status_bayar='lunas', tgl_bayar_aktual=NOW()
                WHERE id='$id'
            ");
        }
    } else {
        $error_msg = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        $_SESSION['error'] = "❌ Gagal mengirim: $error_msg";
        
        // Log error
        error_log("Fonnte Error: " . $response);
    }
} else {
    $_SESSION['error'] = "❌ Error HTTP $http_code: " . ($error ?: 'No response');
}

// Redirect kembali
header("Location: penggajian.php");
exit;