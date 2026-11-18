<?php
/**
 * File Download Handler
 *  - ?export=<filename>  : DB export from ../exports (deleted after download)
 *  - ?file=<path>        : any file under /var/www/html
 */
$deleteAfter = false;

if (isset($_GET['export'])) {
    $name = basename($_GET['export']);
    if (!preg_match('/^backup-[A-Za-z0-9._-]+\.sql\.gz$/', $name)) {
        http_response_code(400);
        echo 'Invalid export name';
        exit;
    }
    $realFile = realpath(__DIR__ . '/../exports/' . $name);
    $baseDir  = realpath(__DIR__ . '/../exports');
    $deleteAfter = true;
} else {
    $realFile = realpath($_GET['file'] ?? '');
    $baseDir  = realpath('/var/www/html/');
}

if (!$realFile || strpos($realFile, $baseDir . '/') !== 0 || !is_file($realFile)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$filename = basename($realFile);
$mime = $deleteAfter ? 'application/gzip' : (mime_content_type($realFile) ?: 'application/octet-stream');

set_time_limit(0);
while (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($realFile));
header('Cache-Control: no-cache');

readfile($realFile);

if ($deleteAfter) @unlink($realFile);
