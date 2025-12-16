<?php
// config.php - Konfigurasi Database SI-KLEPON
// Kelurahan Kalampangan

// ====================================================================
// KONFIGURASI UMUM
// ====================================================================

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'si_klepon');

// Konfigurasi Aplikasi
define('BASE_URL', 'http://localhost/SI-KLEPON/');
define('SITE_NAME', 'Kelurahan Kalampangan');

// Konfigurasi Upload
// UPLOAD_DIR akan menunjuk ke D:\xampp\htdocs\SI-KLEPON Rev\uploads/ (berdasarkan lokasi config.php)
define('UPLOAD_DIR', __DIR__ . '/uploads/'); 
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Timezone
date_default_timezone_set('Asia/Jakarta');

// ====================================================================
// CORE CLASSES: Database & Session
// ====================================================================

// Koneksi Database menggunakan PDO
class Database {
    private static $connection = null;
    
    public static function connect() {
        if (self::$connection === null) {
            try {
                self::$connection = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                // Hentikan eksekusi jika koneksi gagal
                die("Koneksi database gagal: " . $e->getMessage());
            }
        }
        return self::$connection;
    }
    
    // Metode untuk menjalankan query
    public static function query($sql, $params = []) {
        // Baris 54 di file ini (sekarang)
        $stmt = self::connect()->prepare($sql); 
        $stmt->execute($params);
        return $stmt;
    }
}

// Session Management
class Session {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key) {
        self::start();
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        self::start();
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-42000, '/');
        }
        session_destroy();
    }
    
    public static function isLoggedIn() {
        return self::has('user_id') || self::has('admin_id');
    }
    
    public static function isAdmin() {
        // Diasumsikan 'is_admin' di set saat login
        return self::has('admin_id') && self::get('admin_role') !== null;
    }
    
    public static function isUser() {
        return self::has('user_id') && self::get('admin_role') === null;
    }
    
    public static function getUserId() {
        return self::get('user_id');
    }
    
    public static function getAdminId() {
        return self::get('admin_id');
    }
    
    public static function getUserName() {
        if (self::isAdmin()) {
            return self::get('admin_name'); // Mengambil dari session 'admin_name'
        } else {
            return self::get('user_name'); // Mengambil dari session 'user_name'
        }
    }
    
    public static function getRole() {
        if (self::isAdmin()) {
            return self::get('admin_role', 'admin');
        } else {
            return 'user';
        }
    }
}

// ====================================================================
// HELPER FUNCTIONS
// ====================================================================

function redirect($url) {
    // Memastikan path redirect dari root (BASE_URL)
    header("Location: " . BASE_URL . $url);
    exit;
}

function sanitize($data) {
    // Menghilangkan spasi, tag HTML, dan mengubah karakter khusus menjadi entitas HTML
    return htmlspecialchars(strip_tags(trim($data)));
}

function uploadFile($file, $directory) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $uploadDir = UPLOAD_DIR . $directory . '/';
    
    // Pastikan direktori upload ada
    if (!is_dir($uploadDir)) {
        // Cek izin (opsional, tergantung environment)
        mkdir($uploadDir, 0755, true); 
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    // Membuat nama file unik
    $filename = uniqid() . '_' . time() . '.' . $extension; 
    $filepath = $uploadDir . $filename;
    
    // Validasi ukuran file
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Mengembalikan path relatif dari UPLOAD_DIR
        return $directory . '/' . $filename;
    }
    
    return false;
}

function formatTanggal($date, $format = 'd F Y') {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $bulan[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    
    // Jika format mencakup waktu (H:i), tambahkan waktu
    if (str_contains($format, 'H:i')) {
        $time = date('H:i', $timestamp);
        return "$day $month $year $time";
    }

    return "$day $month $year";
}

function generateNomorPengajuan() {
    return 'PGJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function generateNomorPengaduan() {
    return 'ADU-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Fungsi Notifikasi (asumsi ada tabel 'notifikasi' di DB)
function sendNotification($userId, $judul, $pesan, $tipe = 'info', $link = '') {
    $sql = "INSERT INTO notifikasi (user_id, judul, pesan, tipe, link) VALUES (?, ?, ?, ?, ?)";
    Database::query($sql, [$userId, $judul, $pesan, $tipe, $link]);
}
?>