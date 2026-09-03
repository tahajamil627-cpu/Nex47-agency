<?php
/**
 * NEX - 47 CATALYS'S - Master Cloud Router & Application Controller
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);
$cleanUri = trim($uri, '/');

// 1. Explicit Admin Routes
if ($cleanUri === 'admin/login.php' || $cleanUri === 'admin/login') {
    require_once __DIR__ . '/admin/login.php';
    exit;
}

if ($cleanUri === 'admin/logout.php' || $cleanUri === 'admin/logout') {
    require_once __DIR__ . '/admin/logout.php';
    exit;
}

if ($cleanUri === 'admin' || $cleanUri === 'admin/' || $cleanUri === 'admin/index.php') {
    require_once __DIR__ . '/admin/index.php';
    exit;
}

// 2. Explicit API Routes
if (strpos($cleanUri, 'api/') === 0) {
    $apiFile = __DIR__ . '/' . $cleanUri;
    if (file_exists($apiFile)) {
        require_once $apiFile;
        exit;
    }
}

// 3. Database Setup Script Route
if ($cleanUri === 'setup.php' || $cleanUri === 'setup') {
    require_once __DIR__ . '/setup.php';
    exit;
}

// 4. Static Files (CSS, JS, Images, Logo)
if (!empty($cleanUri) && file_exists(__DIR__ . '/' . $cleanUri) && !is_dir(__DIR__ . '/' . $cleanUri)) {
    return false; // Tells PHP server to serve the physical file directly
}

// 5. Default Route: Serve Main Agency Landing Page
include __DIR__ . '/index.html';
exit;
