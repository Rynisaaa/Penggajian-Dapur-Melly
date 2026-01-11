<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

include '../includes/sidebar.php'; // INI YANG PERLU DITAMBAHKAN

$query = "SELECT u.id, u.nama_lengkap, u.username, u.foto_profil, 
                 k.posisi, k.no_telp, k.gaji_pokok, k.tgl_masuk,
                 k.id_karyawan
          FROM users u
          LEFT JOIN karyawan k ON u.id = k.user_id
          WHERE u.role = 'user'
          ORDER BY u.nama_lengkap";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Karyawan - Owner | Dapur Melly</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --pink: #ff7eb3;
            --pink-soft: #ffe3f0;
            --peach: #ffb199;
            --dark: #333;
            --light: #fff;
            --gray: #f5f5f5;
            --shadow: 0 8px 25px rgba(0,0,0,0.08);
            --shadow-light: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--pink-soft), #fff);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* ===================== */
        /* MAIN CONTENT */
        /* ===================== */
        .main-content {
            margin-left: 20px;
            padding: 40px;
            transition: margin-left 0.35s ease;
            min-height: 100vh;
        }
        
        .sidebar-wrapper:hover ~ .main-content,
        .sidebar-hover-zone:hover ~ .main-content {
            margin-left: 280px;
        }
        
        /* ===================== */
        /* HEADER */
        /* ===================== */
        .dashboard-header {
            background: linear-gradient(135deg, rgba(255,126,179,0.1), rgba(255,177,153,0.1));
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            border-left: 5px solid var(--pink);
        }
        
        .dashboard-header h1 {
            color: #ff5f9e;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dashboard-header p {
            color: #666;
            margin: 5px 0;
            font-size: 15px;
        }
        
        .owner-badge {
            background: #6c757d;
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        /* ===================== */
        /* DATA CONTAINER */
        /* ===================== */
        .data-container {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-top: 20px;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .table-header h2 {
            color: #333;
            font-size: 24px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-add {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: not-allowed;
            opacity: 0.7;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        /* ===================== */
        /* READ-ONLY NOTICE */
        /* ===================== */
        .read-only-notice {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 15px;
            margin-bottom: 25px;
            border-left: 4px solid #6c757d;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
        }
        
        .read-only-notice i {
            color: #6c757d;
            font-size: 18px;
        }
        
        /* ===================== */
        /* TABLE STYLES */
        /* ===================== */
        .table-responsive {
            overflow-x: auto;
            border-radius: 15px;
            box-shadow: var(--shadow-light);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 1000px;
        }
        
        thead {
            background: linear-gradient(135deg, var(--pink), var(--peach));
        }
        
        th {
            color: white;
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border: none;
        }
        
        th:first-child {
            border-radius: 10px 0 0 0;
        }
        
        th:last-child {
            border-radius: 0 10px 0 0;
        }
        
        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }
        
        tbody tr:hover {
            background: #f9f9f9;
        }
        
        tbody tr:nth-child(even) {
            background: #fafafa;
        }
        
        tbody tr:nth-child(even):hover {
            background: #f5f5f5;
        }
        
        td {
            padding: 16px 15px;
            color: #555;
            vertical-align: middle;
            font-size: 14px;
        }
        
        /* ===================== */
        /* PROFILE IMAGE */
        /* ===================== */
        .profile-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--pink-soft);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        /* ===================== */
        /* POSISI BADGE */
        /* ===================== */
        .posisi-badge {
            background: rgba(255, 126, 179, 0.1);
            color: var(--pink);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-block;
        }
        
        /* ===================== */
        /* ACTION BUTTONS */
        /* ===================== */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-view {
            background: #28a745;
            color: white;
        }
        
        .btn-view:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }
        
        .btn-edit, .btn-delete {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
            opacity: 0.6;
            position: relative;
        }
        
        .btn-edit::after, .btn-delete::after {
            content: "Owner Only View";
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #495057;
            color: white;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11px;
            opacity: 0;
            transition: opacity 0.3s;
            white-space: nowrap;
            pointer-events: none;
        }
        
        .btn-edit:hover::after, .btn-delete:hover::after {
            opacity: 1;
        }
        
        /* ===================== */
        /* NO DATA */
        /* ===================== */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-data i {
            font-size: 60px;
            margin-bottom: 20px;
            display: block;
            opacity: 0.3;
        }
        
        .no-data h3 {
            color: #666;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        /* ===================== */
        /* SIMPLE NOTICE */
        /* ===================== */
        .simple-notice {
            background: rgba(108,117,125,0.05);
            padding: 15px 20px;
            border-radius: 15px;
            margin-top: 30px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            border: 1px dashed rgba(108,117,125,0.2);
        }
        
        .simple-notice i {
            margin-right: 8px;
            color: #6c757d;
        }
        
        /* ===================== */
        /* RESPONSIVE */
        /* ===================== */
        @media (max-width: 768px) {
            .main-content {
                padding: 25px;
                margin-left: 0;
            }
            
            .sidebar-wrapper:hover ~ .main-content,
            .sidebar-hover-zone:hover ~ .main-content {
                margin-left: 280px;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .btn-add {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100px;
            }
            
            .action-buttons .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar akan otomatis muncul dari include sidebar.php -->

<div class="main-content">
    <!-- HEADER -->
    <div class="dashboard-header">
        <h1>
            <i class="fas fa-users"></i> Data Karyawan
            <span class="owner-badge">View Only</span>
        </h1>
        <p>Halo, <b><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></b>! Data karyawan Dapur Melly.</p>
        <p><i class="fas fa-eye"></i> Akses hanya untuk melihat. Untuk edit data, hubungi Admin.</p>
    </div>
    
    <!-- DATA CONTAINER -->
    <div class="data-container">
        <div class="table-header">
            <h2><i class="fas fa-list"></i> Daftar Karyawan</h2>
            <button class="btn-add" disabled>
                <i class="fas fa-plus"></i> Tambah Karyawan
            </button>
        </div>
        
        <div class="read-only-notice">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Anda login sebagai Owner</strong> - Hanya dapat melihat data karyawan.
                Fitur tambah, edit, dan hapus tidak tersedia.
            </div>
        </div>
        
        <?php if(mysqli_num_rows($result) > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Foto</th>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Posisi</th>
                        <th>No. Telepon</th>
                        <th>Gaji Pokok</th>
                        <th>Tanggal Masuk</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <img src="../uploads/profil/<?= htmlspecialchars($row['foto_profil'] ?? 'default.png') ?>" 
                                 class="profile-img" 
                                 alt="<?= htmlspecialchars($row['nama_lengkap']) ?>"
                                 onerror="this.src='../assets/default-profile.png'">
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong>
                            <?php if(isset($row['id_karyawan'])): ?>
                            <br><small style="color: #888; font-size: 12px;">ID: <?= $row['id_karyawan'] ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td>
                            <span class="posisi-badge">
                                <?= htmlspecialchars($row['posisi'] ?? 'Belum ditentukan') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['no_telp'] ?? '-') ?></td>
                        <td><strong>Rp <?= number_format($row['gaji_pokok'] ?? 0, 0, ',', '.') ?></strong></td>
                        <td><?= $row['tgl_masuk'] ? date('d/m/Y', strtotime($row['tgl_masuk'])) : 'Belum ada' ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-view" onclick="viewDetail(<?= $row['id'] ?>)">
                                    <i class="fas fa-eye"></i> Lihat
                                </button>
                                <button class="btn btn-edit" disabled>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-delete" disabled>
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="no-data">
            <i class="fas fa-users-slash"></i>
            <h3>Tidak ada data karyawan</h3>
            <p>Belum ada karyawan yang terdaftar dalam sistem.</p>
        </div>
        <?php endif; ?>
        
        <div class="simple-notice">
            <i class="fas fa-info-circle"></i> 
            Menampilkan <?= mysqli_num_rows($result) ?> karyawan • Terakhir update: <?= date('d/m/Y H:i') ?>
        </div>
    </div>
</div>

<script>
// Fungsi untuk melihat detail karyawan
function viewDetail(id) {
    // Bisa diganti dengan modal atau redirect ke halaman detail view-only
    alert('📋 Detail Karyawan (ID: ' + id + ')\n\n' +
          'Sebagai Owner, Anda hanya dapat melihat data.\n' +
          'Untuk modifikasi data, hubungi Administrator.');
}

// Animasi untuk tabel rows
document.addEventListener('DOMContentLoaded', function() {
    const tableRows = document.querySelectorAll('tbody tr');
    
    tableRows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            row.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateX(0)';
        }, 50 * index);
    });
});

// Tooltip untuk tombol disabled
document.querySelectorAll('.btn-edit, .btn-delete').forEach(button => {
    button.addEventListener('mouseenter', function() {
        const rect = this.getBoundingClientRect();
        const tooltip = this.querySelector('.tooltip') || document.createElement('span');
        
        if (!this.querySelector('.tooltip')) {
            tooltip.className = 'tooltip';
            tooltip.textContent = 'Tidak tersedia untuk Owner';
            tooltip.style.cssText = `
                position: absolute;
                top: -30px;
                left: 50%;
                transform: translateX(-50%);
                background: #495057;
                color: white;
                padding: 5px 10px;
                border-radius: 6px;
                font-size: 11px;
                white-space: nowrap;
                z-index: 1000;
            `;
            this.style.position = 'relative';
            this.appendChild(tooltip);
        }
    });
    
    button.addEventListener('mouseleave', function() {
        const tooltip = this.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    });
});

// Prevent form submission jika ada form
document.addEventListener('submit', function(e) {
    if (e.target.tagName === 'FORM') {
        e.preventDefault();
        alert('🛑 Akses Ditolak\n\nAnda login sebagai Owner. Hanya dapat melihat data.');
    }
});
</script>

</body>
</html>