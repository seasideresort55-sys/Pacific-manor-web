<?php
/**
 * Local PHP built-in server router. Not used on live LiteSpeed.
 */
declare(strict_types=1);
$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;
if ($path === '/pm_member_social.php') {
    require __DIR__ . '/local/pm_member_social.php';
    return true;
}
if ($path === '/pm_member_client.js') {
    header('Content-Type: text/javascript; charset=utf-8');
    readfile(__DIR__ . '/pm_member_client_v18.js');
    return true;
}
if ($path !== '/' && is_file($file)) {
    return false;
}
if ($path === '/' || $path === '') {
    require __DIR__ . '/pm_member_portal_v17.php';
    return true;
}
return false;
