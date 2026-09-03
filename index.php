<?php
/**
 * NEX - 47 CATALYS'S - Cloud Router & Web Portal Entrypoint
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

$requestedPath = __DIR__ . $uri;

// 1. Direct PHP File Request (e.g. /admin/login.php, /api/submit_lead.php, /setup.php)
if ($uri !== '/' && file_exists($requestedPath) && !is_dir($requestedPath)) {
    if (pathinfo($requestedPath, PATHINFO_EXTENSION) === 'php') {
        require $requestedPath;
        exit;
    }
    // Return false for static assets (images, css, js) so web server serves them directly
    return false;
}

// 2. Directory Request (e.g. /admin or /admin/)
if (is_dir($requestedPath) && $uri !== '/') {
    $indexPhp = rtrim($requestedPath, '/') . '/index.php';
    if (file_exists($indexPhp)) {
        require $indexPhp;
        exit;
    }
    $indexHtml = rtrim($requestedPath, '/') . '/index.html';
    if (file_exists($indexHtml)) {
        include $indexHtml;
        exit;
    }
}

// 3. Root Request (/)
if ($uri === '/' || $uri === '' || $uri === '/index.html' || $uri === '/index.php') {
    include __DIR__ . '/index.html';
    exit;
}

// 4. Fallback if requested directly
if (file_exists($requestedPath)) {
    require $requestedPath;
    exit;
}

// Default Fallback
include __DIR__ . '/index.html';
