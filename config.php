<?php
/**
 * NEX - 47 CATALYS'S - Database Connection & Configuration
 * Supports Local XAMPP & Cloud Deployment (Railway, Render, Heroku)
 */

if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Check for Cloud / Railway Database Environment Variables
$db_host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost');
$db_user = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root');
$db_pass = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: ''));
$db_name = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'nex47_db');
$db_port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: '3306');

// Check if a full MYSQL_URL or DATABASE_URL is provided (e.g. mysql://user:pass@host:port/db)
$databaseUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
if ($databaseUrl) {
    $urlParts = parse_url($databaseUrl);
    if ($urlParts) {
        $db_host = $urlParts['host'] ?? $db_host;
        $db_user = $urlParts['user'] ?? $db_user;
        $db_pass = $urlParts['pass'] ?? $db_pass;
        $db_port = $urlParts['port'] ?? $db_port;
        $db_name = isset($urlParts['path']) ? ltrim($urlParts['path'], '/') : $db_name;
    }
}

define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);
define('DB_PORT', $db_port);

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        // Attempt connecting directly to database
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $ex) {
            // If database doesn't exist yet on local XAMPP, create it
            $rootDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
            $pdo = new PDO($rootDsn, DB_USER, DB_PASS, $options);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
        }
        
        // Ensure Leads Table Exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `leads` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `client_name` VARCHAR(255) NOT NULL,
            `client_phone` VARCHAR(50) NOT NULL,
            `brand_name` VARCHAR(255) DEFAULT NULL,
            `service_type` VARCHAR(150) NOT NULL,
            `budget` VARCHAR(100) DEFAULT '$100 - $300',
            `message` TEXT DEFAULT NULL,
            `lead_source` VARCHAR(50) DEFAULT 'Website Form',
            `status` VARCHAR(50) DEFAULT 'new',
            `notes` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure Admins Table Exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) UNIQUE NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` VARCHAR(50) DEFAULT 'superadmin',
            `last_login` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure default admin user is seeded (samsparrow)
        $adminCheck = $pdo->query("SELECT COUNT(*) FROM `admins` WHERE `email` = 'admin@nex47.com'")->fetchColumn();
        if ($adminCheck == 0) {
            $defaultHash = password_hash('samsparrow', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO `admins` (`name`, `email`, `password`, `role`) VALUES (?, ?, ?, ?)");
            $stmt->execute(['NEX-47 SuperAdmin', 'admin@nex47.com', $defaultHash, 'superadmin']);
        }

        return $pdo;
    } catch (PDOException $e) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }
}
