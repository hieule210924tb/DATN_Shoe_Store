<?php
/**
 * Cấu hình kết nối Database - WinK Shoe Store
 * Sử dụng PDO với Prepared Statements
 */

// Thông tin kết nối database
define('DB_HOST', 'localhost');
define('DB_NAME', 'wink_shoe_store');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Tạo kết nối PDO
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Trong production nên log lỗi thay vì hiển thị
            die("Lỗi kết nối database: " . $e->getMessage());
        }
    }
    
    return $pdo;
}
