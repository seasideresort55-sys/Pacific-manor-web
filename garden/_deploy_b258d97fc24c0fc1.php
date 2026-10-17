<?php
declare(strict_types=1);

/**
 * 一次性部署。網址要帶對的 token。
 * 依序：備份公開網站與私人目錄、解壓（不覆蓋 garden/data）、執行 v13_convert --apply、只印筆數、刪掉 zip 與自己。
 * 任一步失敗就停下，不繼續。
 */

const DEPLOY_TOKEN = '27fea771f41f1f87675e2c6463db30a1';

ob_start();
ini_set('display_errors', '0');

function deploy_fail(int $status, string $message): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo $message;
    if (!str_ends_with($message, "\n")) {
        echo "\n";
    }
    exit;
}

function deploy_disabled(string $fn): bool
{
    $raw = (string) ini_get('disable_functions');
    $list = array_map('trim', explode(',', $raw));
    return in_array($fn, $list, true);
}

function deploy_copy_tree(string $src, string $dst): void
{
    if (is_link($src)) {
        deploy_fail(500, '備份遇到連結，已停下。');
    }
    if (is_dir($src)) {
        if (!is_dir($dst) && !mkdir($dst, 0755)) {
            deploy_fail(500, '無法建立備份目錄，已停下。');
        }
        $items = scandir($src);
        if ($items === false) {
            deploy_fail(500, '無法讀取備份來源，已停下。');
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            deploy_copy_tree($src . '/' . $item, $dst . '/' . $item);
        }
        return;
    }
    if (!is_file($src) || !copy($src, $dst)) {
        deploy_fail(500, '複製備份失敗，已停下。');
    }
}

function deploy_rm_tree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            deploy_fail(500, '無法刪除暫存，已停下。');
        }
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        deploy_fail(500, '無法讀取暫存目錄，已停下。');
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        deploy_rm_tree($path . '/' . $item);
    }
    if (!rmdir($path)) {
        deploy_fail(500, '無法刪除暫存目錄，已停下。');
    }
}

/** @return string|null relative path under garden/; null means a directory entry to skip */
function deploy_entry_rel(string $name): ?string
{
    $name = str_replace('\\', '/', $name);
    if ($name === '' || str_contains($name, "\0")) {
        deploy_fail(500, 'zip 路徑不合法，已停下。');
    }
    if (str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) === 1) {
        deploy_fail(500, 'zip 路徑不合法，已停下。');
    }
    if ($name === 'garden' || $name === 'garden/') {
        return null;
    }
    if (!str_starts_with($name, 'garden/')) {
        deploy_fail(500, 'zip 最外層不是 garden/，已停下。');
    }
    if (str_ends_with($name, '/')) {
        return null;
    }
    return substr($name, strlen('garden/'));
}

function deploy_skip(string $rel): bool
{
    if ($rel === 'data' || str_starts_with($rel, 'data/')) {
        return true;
    }
    return preg_match('#(^|/)_deploy_[A-Za-z0-9]+\.php$#', $rel) === 1;
}

function deploy_put(string $garden, string $rel, string $bytes): void
{
    if (deploy_skip($rel)) {
        return;
    }
    $dest = $garden . '/' . $rel;
    $parent = dirname($dest);
    if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
        deploy_fail(500, '無法建立目錄，已停下。');
    }
    if (file_put_contents($dest, $bytes) === false) {
        deploy_fail(500, '無法寫入解壓檔，已停下。');
    }
}

function deploy_extract_ziparchive(string $zipPath, string $garden): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        deploy_fail(500, '無法開啟 zip，已停下。');
    }
    $saw = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name)) {
            $zip->close();
            deploy_fail(500, '無法讀取 zip 清單，已停下。');
        }
        $rel = deploy_entry_rel($name);
        if ($rel === null || deploy_skip($rel)) {
            continue;
        }
        $bytes = $zip->getFromIndex($i);
        if (!is_string($bytes)) {
            $zip->close();
            deploy_fail(500, '無法讀出 zip 內容，已停下。');
        }
        deploy_put($garden, $rel, $bytes);
        $saw = true;
    }
    $zip->close();
    if (!$saw) {
        deploy_fail(500, 'zip 沒有可解壓的程式檔，已停下。');
    }
}

function deploy_unzip_bin(): string
{
    foreach (['/usr/bin/unzip', '/bin/unzip', 'unzip'] as $bin) {
        if ($bin === 'unzip' || is_executable($bin)) {
            return $bin;
        }
    }
    deploy_fail(500, '無法解壓，已停下。');
}

function deploy_extract_unzip(string $zipPath, string $garden, string $home): void
{
    $unzip = deploy_unzip_bin();
    $list = [];
    $code = 0;
    exec(escapeshellarg($unzip) . ' -Z1 ' . escapeshellarg($zipPath), $list, $code);
    if ($code !== 0) {
        deploy_fail(500, '無法讀取 zip 清單，已停下。');
    }
    foreach ($list as $name) {
        $name = trim($name);
        if ($name === '') {
            continue;
        }
        deploy_entry_rel($name);
    }
    $tmp = $home . '/garden_unpack_' . bin2hex(random_bytes(4));
    if (str_starts_with($tmp, $home . '/public_html/') || file_exists($tmp) || !mkdir($tmp, 0755)) {
        deploy_fail(500, '無法建立暫存目錄，已停下。');
    }
    $out = [];
    $code = 0;
    exec(escapeshellarg($unzip) . ' -q ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($tmp), $out, $code);
    if ($code !== 0) {
        deploy_rm_tree($tmp);
        deploy_fail(500, '解壓失敗，已停下。');
    }
    $root = realpath($tmp);
    if ($root === false || !is_dir($root . '/garden')) {
        deploy_rm_tree($tmp);
        deploy_fail(500, 'zip 最外層不是 garden/，已停下。');
    }
    $saw = false;
    $prefix = $root . '/garden/';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/garden', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isLink()) {
            deploy_rm_tree($tmp);
            deploy_fail(500, 'zip 內有連結，已停下。');
        }
        if (!$file->isFile()) {
            continue;
        }
        $real = $file->getRealPath();
        if ($real === false || !str_starts_with($real, $prefix)) {
            deploy_rm_tree($tmp);
            deploy_fail(500, '解壓路徑超出暫存目錄，已停下。');
        }
        $rel = substr($real, strlen($prefix));
        if (deploy_skip($rel)) {
            continue;
        }
        $bytes = file_get_contents($real);
        if (!is_string($bytes)) {
            deploy_rm_tree($tmp);
            deploy_fail(500, '無法讀出解壓檔，已停下。');
        }
        deploy_put($garden, $rel, $bytes);
        $saw = true;
    }
    deploy_rm_tree($tmp);
    if (!$saw) {
        deploy_fail(500, 'zip 沒有可解壓的程式檔，已停下。');
    }
}

function deploy_php_cli(): string
{
    $candidates = [];
    if (PHP_BINARY !== '' && !str_contains(PHP_BINARY, 'fpm') && !str_contains(PHP_BINARY, 'cgi')) {
        $candidates[] = PHP_BINARY;
    }
    if (defined('PHP_BINDIR')) {
        $candidates[] = PHP_BINDIR . '/php';
    }
    $candidates[] = '/usr/bin/php';
    foreach ($candidates as $bin) {
        if (is_executable($bin)) {
            return $bin;
        }
    }
    deploy_fail(500, '找不到命令列 PHP，已停下。');
}

function deploy_convert(string $garden, string $db): string
{
    $script = $garden . '/migrate/v13_convert.php';
    if (!is_file($script)) {
        deploy_fail(500, '找不到轉換程式，已停下。zip 與腳本都還在。');
    }
    $cmd = [deploy_php_cli(), $script, '--apply', '--db=' . $db];
    $desc = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $pipes = [];
    $proc = proc_open($cmd, $desc, $pipes, $garden);
    if (!is_resource($proc)) {
        deploy_fail(500, '無法執行轉換，已停下。zip 與腳本都還在。');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    if ($exit !== 0 || !is_string($stdout)) {
        $err = trim((string) $stderr);
        if (strlen($err) > 1500) {
            $err = substr($err, 0, 1500);
        }
        deploy_fail(500, "轉換失敗，已停下。zip 與腳本都還在。\n" . $err);
    }
    $keep = [];
    foreach (preg_split("/\r\n|\n|\r/", $stdout) ?: [] as $line) {
        if (str_starts_with($line, 'COUNT ') || str_starts_with($line, 'VENDOR ')) {
            $keep[] = $line;
        }
    }
    if ($keep === []) {
        deploy_fail(500, '轉換沒有印出筆數，已停下。zip 與腳本都還在。');
    }
    return implode("\n", $keep) . "\n";
}

if (PHP_SAPI === 'cli') {
    deploy_fail(403, '請用網頁開啟。');
}

$given = $_GET['token'] ?? '';
if (!is_string($given) || !hash_equals(DEPLOY_TOKEN, $given)) {
    deploy_fail(403, 'token 不對。');
}

set_time_limit(0);
ignore_user_abort(true);

$home = getenv('GARDEN_DEPLOY_HOME');
if (!is_string($home) || $home === '' || str_contains($home, "\0")) {
    $home = '/home/irihvsuu';
}
$home = rtrim($home, '/');
if ($home === '' || !str_starts_with($home, '/')) {
    deploy_fail(500, '家目錄不對，已停下。');
}

$garden = $home . '/public_html/garden';
$private = $home . '/garden_private';
$zip = $home . '/garden-v14.1.zip';
$db = $private . '/garden.sqlite';
$public = $home . '/public_html';

if (!is_dir($garden) || !is_dir($private) || !is_file($zip) || !is_file($db)) {
    deploy_fail(500, '找不到網站目錄、私人目錄、資料庫或 zip，已停下。');
}
if (!function_exists('proc_open') || deploy_disabled('proc_open')) {
    deploy_fail(500, '這台 PHP 不能執行轉換程式，已停下。');
}
$canZip = class_exists('ZipArchive');
$canUnzip = function_exists('exec') && !deploy_disabled('exec');
if (!$canZip && !$canUnzip) {
    deploy_fail(500, '無法解壓，已停下。');
}

$lockPath = $home . '/garden_deploy.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    deploy_fail(500, '已有部署在跑，已停下。');
}

$stamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Taipei')))->format('YmdHis');
$backup = $home . '/garden_backup_' . $stamp;
if (str_starts_with($backup, $public . '/') || file_exists($backup) || !mkdir($backup, 0755)) {
    deploy_fail(500, '無法建立備份目錄，已停下。');
}

deploy_copy_tree($garden, $backup . '/garden');
deploy_copy_tree($private, $backup . '/garden_private');

if (!is_dir($backup . '/garden') || !is_file($backup . '/garden_private/garden.sqlite')) {
    deploy_fail(500, '備份不完整，已停下。');
}

if ($canZip) {
    deploy_extract_ziparchive($zip, $garden);
} else {
    deploy_extract_unzip($zip, $garden, $home);
}

if (!is_file($garden . '/index.php') || !is_file($garden . '/migrate/v13_convert.php')) {
    deploy_fail(500, '解壓後缺少程式，已停下。zip 與腳本都還在。');
}

$counts = deploy_convert($garden, $db);

if (!unlink($zip)) {
    deploy_fail(500, '無法刪除 zip，已停下。轉換已經寫入，腳本還在。');
}
if (!unlink(__FILE__)) {
    deploy_fail(500, '無法刪除腳本，已停下。轉換已經寫入，zip 已刪。');
}
flock($lock, LOCK_UN);
fclose($lock);
@unlink($lockPath);

while (ob_get_level() > 0) {
    ob_end_clean();
}
http_response_code(200);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo $counts;
