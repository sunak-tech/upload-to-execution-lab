<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';

header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'");
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Method Not Allowed\n";
    exit;
}

$requested = (string) ($_GET['file'] ?? '');
if (preg_match(STORED_FILE_PATTERN, $requested) !== 1) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Bad Request: invalid image identifier format.\n";
    exit;
}

$fullPath = STORAGE_DIR . '/' . $requested;
if (!is_file($fullPath) || is_link($fullPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Not Found\n";
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($fullPath);
$allowedMimes = ['image/png', 'image/jpeg', 'image/gif'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden: stored content failed MIME verification.\n";
    exit;
}

$size = @filesize($fullPath);
http_response_code(200);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $requested . '"');
if ($size !== false) {
    header('Content-Length: ' . (string) $size);
}

if ($method === 'GET') {
    readfile($fullPath);
}
