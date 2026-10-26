<?php
require_once __DIR__ . '/incl/init.php';

$slug = (string)($_GET['module'] ?? '');
$file = (string)($_GET['file'] ?? '');
$asset = cms_module_manager()->resolvePublicScript($slug, $file);

if ($asset === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    exit;
}

$path = (string)$asset['path'];
$mtime = (int)$asset['mtime'];

header('Content-Type: ' . (string)$asset['mime']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=2592000, immutable');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Content-Length: ' . (string)filesize($path));
readfile($path);
exit;
