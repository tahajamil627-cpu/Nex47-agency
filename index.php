<?php
/**
 * NEX - 47 CATALYS'S - Master All-in-One Engine & Router
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost');
        $user = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root');
        $pass = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: ''));
        $name = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'nex47_db');
        $port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: '3306');

        $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
        if ($url) {
            $p = parse_url($url);
            if ($p) {
                $host = $p['host'] ?? $host;
                $user = $p['user'] ?? $user;
                $pass = $p['pass'] ?? $pass;
                $port = $p['port'] ?? $port;
                $name = isset($p['path']) ? ltrim($p['path'], '/') : $name;
            }
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3
        ];
        return new PDO($dsn, $user, $pass, $opts);
    } catch (Exception $e) {
        return null; // Fail-safe: Never throw 500 error
    }
}

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($rawUri, PHP_URL_PATH);
$uri = rtrim($uri, '/');
if (empty($uri)) $uri = '/';

// 1. ADMIN LOGOUT
if ($uri === '/admin/logout' || $uri === '/admin/logout.php' || (isset($_GET['admin']) && $_GET['admin'] === 'logout')) {
    $_SESSION = [];
    session_destroy();
    header('Location: /?admin=1&logout=1');
    exit;
}

// 2. CHECK FOR ADMIN ROUTES
$isAdminRoute = ($uri === '/admin' || $uri === '/admin/login' || $uri === '/admin/login.php' || $uri === '/admin/index.php' || strpos($uri, '/admin') === 0 || isset($_GET['admin']));

if ($isAdminRoute) {
    $loginError = '';
    $logoutMsg = isset($_GET['logout']) ? 'You have been successfully logged out.' : '';

    // Handle Login Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['admin_pass_key'] ?? $_POST['password'] ?? '');
        $valid = false;

        try {
            $pdo = getDBConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `email` = :e OR `name` = :n LIMIT 1");
                $stmt->execute([':e' => $email, ':n' => $email]);
                $adm = $stmt->fetch();
                if ($adm && password_verify($password, $adm['password'])) {
                    $valid = true;
                }
            }
        } catch (Exception $e) {}

        // Master Fallback Key
        if (!$valid && (($email === 'admin@nex47.com' || $email === 'admin') && $password === 'samsparrow')) {
            $valid = true;
        }

        if ($valid) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email'] = 'admin@nex47.com';
            $_SESSION['admin_name'] = 'NEX-47 SuperAdmin';
            header('Location: /?admin=dashboard');
            exit;
        } else {
            $loginError = 'Access Denied: Incorrect password or username.';
        }
    }

    // If Authenticated -> Renders CRM Dashboard directly!
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        // [Built-in CRM Dashboard renders here]
    } else {
        // [Built-in Admin Login Gate renders here]
    }
}
?>
