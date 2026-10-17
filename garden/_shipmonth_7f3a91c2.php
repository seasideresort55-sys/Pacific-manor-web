<?php
declare(strict_types=1);

/**
 * 一次性：把東海岸太平洋莊園的新苗預購預計出貨改成 2027年1月。
 * 網址要帶對的 token。先備份 garden.sqlite，再改，最後印筆數。
 * mode=cleanup 只刪掉這支腳本，不改資料庫。
 */

const SHIP_TOKEN = 'ff58b2c78122a90fa859c8bdd83653b0';
const SHIP_MONTH = '2027年1月';
const VENDOR_NAME = '東海岸太平洋莊園';
const LIVE_DB = '/home/irihvsuu/garden_private/garden.sqlite';

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
}

function ship_out(int $status, string $message): never
{
    if (PHP_SAPI !== 'cli') {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    echo $message;
    if (!str_ends_with($message, "\n")) {
        echo "\n";
    }
    exit($status === 200 ? 0 : $status);
}

function ship_request(string $key): string
{
    $value = (string) ($_GET[$key] ?? '');
    if (PHP_SAPI === 'cli') {
        $env = getenv('GARDEN_SHIP_' . strtoupper($key));
        if (is_string($env) && $env !== '') {
            return $env;
        }
    }
    return $value;
}

function ship_db_path(): string
{
    if (PHP_SAPI === 'cli') {
        $env = getenv('GARDEN_SHIP_DB');
        if (is_string($env) && $env !== '') {
            return $env;
        }
    }
    return LIVE_DB;
}

function ship_backup_parent(): string
{
    if (PHP_SAPI === 'cli' && getenv('GARDEN_SHIP_DB')) {
        return sys_get_temp_dir();
    }
    return '/home/irihvsuu';
}

$token = ship_request('token');
if ($token === '' || !hash_equals(SHIP_TOKEN, $token)) {
    ship_out(403, 'forbidden');
}

if (ship_request('mode') === 'cleanup') {
    if (!unlink(__FILE__)) {
        ship_out(500, '無法刪除腳本');
    }
    ship_out(200, 'removed');
}

$dbPath = ship_db_path();
if (!is_file($dbPath)) {
    ship_out(500, '找不到資料庫，已停下');
}

$stamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Taipei')))->format('YmdHis');
$backupDir = ship_backup_parent() . '/garden_backup_shipmonth_' . $stamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) {
    ship_out(500, '無法建立備份目錄，已停下');
}
$backupFile = $backupDir . '/garden.sqlite';

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    $pdo->exec('VACUUM INTO ' . $pdo->quote($backupFile));
} catch (Throwable $e) {
    ship_out(500, '備份失敗，已停下');
}
if (!is_file($backupFile) || filesize($backupFile) <= 0) {
    ship_out(500, '備份檔是空的，已停下');
}

try {
    $vendor = $pdo->prepare('SELECT id FROM vendors WHERE name = ?');
    $vendor->execute([VENDOR_NAME]);
    $vendorId = $vendor->fetchColumn();
    if ($vendorId === false) {
        ship_out(500, "找不到店家\nbackup $backupFile");
    }
    $vendorId = (int) $vendorId;
    $count = $pdo->prepare("SELECT COUNT(*) FROM products WHERE status = '新苗預購' AND vendor_id = ?");
    $count->execute([$vendorId]);
    $matched = (int) $count->fetchColumn();
    if ($matched === 0) {
        ship_out(500, "preorders 0\nbackup $backupFile");
    }
    $pdo->beginTransaction();
    $update = $pdo->prepare("UPDATE products SET ship_month = ? WHERE status = '新苗預購' AND vendor_id = ?");
    $update->execute([SHIP_MONTH, $vendorId]);
    $mirror = $pdo->prepare("UPDATE products SET ship_month = ? WHERE origin_product_id IN (SELECT id FROM products WHERE status = '新苗預購' AND vendor_id = ?)");
    $mirror->execute([SHIP_MONTH, $vendorId]);
    $mirrors = $mirror->rowCount();
    $pdo->commit();
    $after = $pdo->prepare("SELECT COUNT(*) FROM products WHERE status = '新苗預購' AND vendor_id = ? AND ship_month = ?");
    $after->execute([$vendorId, SHIP_MONTH]);
    $preorders = (int) $after->fetchColumn();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ship_out(500, "更新失敗，已停下\nbackup $backupFile");
}

ship_out(200, "backup $backupFile\npreorders $preorders\nmirrors $mirrors");
