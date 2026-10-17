<?php
declare(strict_types=1);

$name = basename((string) ($_GET['name'] ?? ''));
if (preg_match('/^[a-f0-9]{32}\.webp$/', $name) !== 1) {
    http_response_code(404);
    exit;
}

$dirs = [
    __DIR__ . '/data/media',
    __DIR__ . '/data/uploads',
];
$config = getenv('GARDEN_CONFIG');
if ($config === false || $config === '') {
    $config = '/home/irihvsuu/garden_private/config.php';
}
if (is_file($config)) {
    $loaded = require $config;
    $dbPath = '';
    if (is_array($loaded)) {
        if (isset($loaded['db']) && is_string($loaded['db']) && $loaded['db'] !== '') {
            $dbPath = $loaded['db'];
        } elseif (isset($loaded['database']) && is_string($loaded['database']) && $loaded['database'] !== '') {
            $dbPath = $loaded['database'];
        }
    }
    if ($dbPath !== '') {
        $dirs[] = dirname($dbPath) . '/media';
    }
}
$dirs[] = dirname(__DIR__, 2) . '/garden_private/media';

$thumb = isset($_GET['thumb']) && (string) $_GET['thumb'] !== '' && (string) $_GET['thumb'] !== '0';
foreach ($dirs as $dir) {
    $candidates = $thumb ? [$dir . '/thumbs/' . $name, $dir . '/' . $name] : [$dir . '/' . $name];
    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        header('Content-Type: image/webp');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }
}
http_response_code(404);
exit;
