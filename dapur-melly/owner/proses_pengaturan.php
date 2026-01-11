<?php
session_start();
require '../config/koneksi.php';

// Check if user is owner
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak!']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle database backup
if ($action === 'backup_database') {
    backupDatabase();
    exit;
}

// Handle file download
if (isset($_GET['download'])) {
    downloadBackup($_GET['download']);
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'update_profile':
            updateProfile();
            break;
        case 'change_password':
            changePassword();
            break;
        case 'send_message':
            sendMessage();
            break;
        case 'set_target':
            setTarget();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
    }
}

// FUNCTION: Update Profile
function updateProfile() {
    global $conn;
    
    $user_id = $_POST['user_id'];
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $whatsapp = mysqli_real_escape_string($conn, $_POST['whatsapp'] ?? '');
    
    // Check if username already exists (excluding current user)
    $check_query = mysqli_query($conn, 
        "SELECT id FROM users WHERE username = '$username' AND id != '$user_id'"
    );
    
    if (mysqli_num_rows($check_query) > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Username sudah digunakan oleh user lain'
        ]);
        return;
    }
    
    // Update user profile
    $query = "UPDATE users SET 
              nama_lengkap = '$nama_lengkap',
              username = '$username',
              email = '$email',
              updated_at = NOW()
              WHERE id = '$user_id'";
    
    if (mysqli_query($conn, $query)) {
        // Update WhatsApp number in karyawan table
        if (!empty($whatsapp)) {
            $check_karyawan = mysqli_query($conn, 
                "SELECT user_id FROM karyawan WHERE user_id = '$user_id'"
            );
            
            if (mysqli_num_rows($check_karyawan) > 0) {
                mysqli_query($conn, 
                    "UPDATE karyawan SET no_telp = '$whatsapp' WHERE user_id = '$user_id'"
                );
            } else {
                mysqli_query($conn, 
                    "INSERT INTO karyawan (user_id, no_telp, created_at) 
                     VALUES ('$user_id', '$whatsapp', NOW())"
                );
            }
        }
        
        // Update session
        $_SESSION['nama_lengkap'] = $nama_lengkap;
        $_SESSION['username'] = $username;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Profil berhasil diperbarui'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal memperbarui profil: ' . mysqli_error($conn)
        ]);
    }
}

// FUNCTION: Change Password
function changePassword() {
    global $conn;
    
    $user_id = $_POST['user_id'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    
    // Verify current password
    $user_query = mysqli_query($conn, 
        "SELECT password FROM users WHERE id = '$user_id'"
    );
    $user = mysqli_fetch_assoc($user_query);
    
    // For testing: if password is plain text (not hashed)
    if ($user['password'] === $current_password) {
        // Password is plain text (for testing)
        $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET 
                  password = '$new_password_hashed',
                  updated_at = NOW()
                  WHERE id = '$user_id'";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode([
                'success' => true, 
                'message' => 'Password berhasil diubah'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Gagal mengubah password: ' . mysqli_error($conn)
            ]);
        }
    } else {
        // Try password_verify if password is hashed
        if (password_verify($current_password, $user['password'])) {
            $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            
            $query = "UPDATE users SET 
                      password = '$new_password_hashed',
                      updated_at = NOW()
                      WHERE id = '$user_id'";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Password berhasil diubah'
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Gagal mengubah password: ' . mysqli_error($conn)
                ]);
            }
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Password saat ini salah'
            ]);
        }
    }
}

// FUNCTION: Send Message to Admin
function sendMessage() {
    global $conn;
    
    $sender_id = $_POST['sender_id'];
    $sender_name = mysqli_real_escape_string($conn, $_POST['sender_name']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    // Create message table if not exists
    $create_table = "
        CREATE TABLE IF NOT EXISTS pesan_sistem (
            id INT PRIMARY KEY AUTO_INCREMENT,
            sender_id INT NOT NULL,
            sender_name VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            tujuan_role VARCHAR(20) DEFAULT 'admin',
            tujuan_user_id INT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tujuan (tujuan_role, tujuan_user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    mysqli_query($conn, $create_table);
    
    // Get all admin IDs
    $admin_query = mysqli_query($conn, 
        "SELECT id FROM users WHERE role = 'admin'"
    );
    
    $success_count = 0;
    while ($admin = mysqli_fetch_assoc($admin_query)) {
        $admin_id = $admin['id'];
        
        $insert_query = "
            INSERT INTO pesan_sistem (sender_id, sender_name, message, tujuan_role, tujuan_user_id)
            VALUES ('$sender_id', '$sender_name', '$message', 'admin', '$admin_id')
        ";
        
        if (mysqli_query($conn, $insert_query)) {
            $success_count++;
        }
    }
    
    if ($success_count > 0) {
        echo json_encode([
            'success' => true, 
            'message' => "Pesan berhasil dikirim ke $success_count admin"
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal mengirim pesan'
        ]);
    }
}

// FUNCTION: Set Target Pendapatan
function setTarget() {
    global $conn;
    
    $bulan = $_POST['bulan'];
    $tahun = $_POST['tahun'];
    $target_amount = str_replace(['.', ','], '', $_POST['target_amount']);
    
    // Create target table if not exists
    $create_table = "
        CREATE TABLE IF NOT EXISTS target_pendapatan (
            id INT PRIMARY KEY AUTO_INCREMENT,
            bulan VARCHAR(2) NOT NULL,
            tahun VARCHAR(4) NOT NULL,
            target_amount DECIMAL(15,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_bulan_tahun (bulan, tahun)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    mysqli_query($conn, $create_table);
    
    // Insert or update target
    $query = "
        INSERT INTO target_pendapatan (bulan, tahun, target_amount)
        VALUES ('$bulan', '$tahun', '$target_amount')
        ON DUPLICATE KEY UPDATE
        target_amount = '$target_amount',
        updated_at = NOW()
    ";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Target pendapatan berhasil disimpan'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal menyimpan target: ' . mysqli_error($conn)
        ]);
    }
}

// FUNCTION: Backup Database
function backupDatabase() {
    $db_host = 'localhost';
    $db_user = 'root'; // Change according to your configuration
    $db_pass = '';     // Change according to your configuration
    $db_name = 'dapur_melly';
    
    $backup_file = 'backup/db_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_dir = dirname(__DIR__) . '/backup/';
    
    // Create backup directory if not exists
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $command = "mysqldump --host=$db_host --user=$db_user --password=$db_pass $db_name > " . $backup_dir . basename($backup_file);
    
    // Execute backup command
    system($command, $output);
    
    if ($output === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Backup database berhasil dibuat',
            'filename' => basename($backup_file)
        ]);
    } else {
        // Alternative method using PHP
        $tables = array();
        $result = mysqli_query($GLOBALS['conn'], 'SHOW TABLES');
        
        while($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }
        
        $return = '';
        
        foreach($tables as $table) {
            $result = mysqli_query($GLOBALS['conn'], 'SELECT * FROM ' . $table);
            $num_fields = mysqli_num_fields($result);
            
            $return .= 'DROP TABLE IF EXISTS ' . $table . ';';
            $row2 = mysqli_fetch_row(mysqli_query($GLOBALS['conn'], 'SHOW CREATE TABLE ' . $table));
            $return .= "\n\n" . $row2[1] . ";\n\n";
            
            for ($i = 0; $i < $num_fields; $i++) {
                while($row = mysqli_fetch_row($result)) {
                    $return .= 'INSERT INTO ' . $table . ' VALUES(';
                    for($j = 0; $j < $num_fields; $j++) {
                        $row[$j] = addslashes($row[$j]);
                        $row[$j] = str_replace("\n", "\\n", $row[$j]);
                        if (isset($row[$j])) {
                            $return .= '"' . $row[$j] . '"';
                        } else {
                            $return .= '""';
                        }
                        if ($j < ($num_fields - 1)) {
                            $return .= ',';
                        }
                    }
                    $return .= ");\n";
                }
            }
            $return .= "\n\n\n";
        }
        
        // Save file
        $handle = fopen($backup_dir . basename($backup_file), 'w+');
        fwrite($handle, $return);
        fclose($handle);
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup database berhasil dibuat (PHP method)',
            'filename' => basename($backup_file)
        ]);
    }
}

// FUNCTION: Download Backup
function downloadBackup($filename) {
    $filepath = dirname(__DIR__) . '/backup/' . $filename;
    
    if (file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        echo 'File backup tidak ditemukan';
    }
}

// FUNCTION: Create Login History Table
function createLoginHistoryTable() {
    global $conn;
    
    $create_table = "
        CREATE TABLE IF NOT EXISTS login_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            device_info TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_login (user_id, login_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    mysqli_query($conn, $create_table);
}

// Initialize necessary tables
createLoginHistoryTable();
?>