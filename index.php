<?php
/**
 * NEX - 47 CATALYS'S - Web Portal Entrypoint
 */

// If requested URL is the root, serve index.html
if (file_exists(__DIR__ . '/index.html')) {
    include __DIR__ . '/index.html';
    exit;
}
?>
