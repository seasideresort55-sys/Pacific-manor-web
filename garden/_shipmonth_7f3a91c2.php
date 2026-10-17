<?php
declare(strict_types=1);

/**
 * 把東海岸太平洋莊園的新苗預購預計出貨改成 2027年1月。
 * 網址要帶對的 token。先備份 garden.sqlite，再改，最後印筆數。
 * 已經是這個月份的列不重寫，可以再跑。
 * mode=status 只印筆數，不備份、不改資料庫。
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

function ship_finish(PDOStatement $stmt): void
{
    $stmt->closeCursor();
}

function ship_pragma(PDO $pdo, string $sql): void
{
    $stmt = $pdo->query($sql);
    if ($stmt instanceof PDOStatement) {
        $stmt->fetchAll();
        ship_finish($stmt);
    }
}

function ship_one(PDO $pdo, string $sql, array $args = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $value = $stmt->fetchColumn();
    ship_finish($stmt);
    return $value;
}

function ship_run(PDO $pdo, string $sql, array $args = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $changed = $stmt->rowCount();
    ship_finish($stmt);
    return $changed;
}

function ship_pdo(string $dbPath): PDO
{
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    ship_pragma($pdo, 'PRAGMA busy_timeout = 5000');
    return $pdo;
}

function ship_vendor_id(PDO $pdo): int
{
    $vendorId = ship_one($pdo, 'SELECT id FROM vendors WHERE name = ?', [VENDOR_NAME]);
    if ($vendorId === false) {
        ship_out(500, '找不到店家');
    }
    return (int) $vendorId;
}

$token = ship_request('token');
if ($token === '' || !hash_equals(SHIP_TOKEN, $token)) {
    ship_out(403, 'forbidden');
}

$mode = ship_request('mode');
if ($mode === 'cleanup') {
    if (!unlink(__FILE__)) {
        ship_out(500, '無法刪除腳本');
    }
    ship_out(200, 'removed');
}
if ($mode !== '' && $mode !== 'apply' && $mode !== 'status') {
    ship_out(400, '未知的 mode');
}

$dbPath = ship_db_path();
if (!is_file($dbPath)) {
    ship_out(500, '找不到資料庫，已停下');
}

try {
    $pdo = ship_pdo($dbPath);
    $vendorId = ship_vendor_id($pdo);
    $preorders = (int) ship_one($pdo, "SELECT COUNT(*) FROM products WHERE status = '新苗預購' AND vendor_id = ?", [$vendorId]);
    $already = (int) ship_one($pdo, "SELECT COUNT(*) FROM products WHERE status = '新苗預購' AND vendor_id = ? AND ship_month = ?", [$vendorId, SHIP_MONTH]);
    $pending = (int) ship_one($pdo, "SELECT COUNT(*) FROM products WHERE status = '新苗預購' AND vendor_id = ? AND COALESCE(ship_month, '') != ?", [$vendorId, SHIP_MONTH]);
    $mirrorsPending = (int) ship_one(
        $pdo,
        "SELECT COUNT(*) FROM products WHERE origin_product_id IN (SELECT id FROM products WHERE status = '新苗預購' AND vendor_id = ?) AND COALESCE(ship_month, '') != ?",
        [$vendorId, SHIP_MONTH]
    );
} catch (Throwable $e) {
    ship_out(500, '讀取失敗，已停下');
}

if ($mode === 'status') {
    ship_out(200, "mode status\nvendor " . VENDOR_NAME . "\npreorders $preorders\nalready $already\npending $pending\nmirrors_pending $mirrorsPending");
}

$stamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Taipei')))->format('YmdHis');
$backupDir = ship_backup_parent() . '/garden_backup_shipmonth_' . $stamp;
$suffix = 0;
while (is_dir($backupDir)) {
    $suffix++;
    $backupDir = ship_backup_parent() . '/garden_backup_shipmonth_' . $stamp . '_' . $suffix;
}
if (!mkdir($backupDir, 0700, true)) {
    ship_out(500, '無法建立備份目錄，已停下');
}
$backupFile = $backupDir . '/garden.sqlite';

try {
    ship_pragma($pdo, 'PRAGMA wal_checkpoint(TRUNCATE)');
    $vacuum = $pdo->query('VACUUM INTO ' . $pdo->quote($backupFile));
    if ($vacuum instanceof PDOStatement) {
        $vacuum->fetchAll();
        ship_finish($vacuum);
    }
} catch (Throwable $e) {
    ship_out(500, '備份失敗，已停下');
}
if (!is_file($backupFile) || filesize($backupFile) <= 0) {
    ship_out(500, '備份檔是空的，已停下');
}
if ($preorders === 0) {
    ship_out(500, "preorders 0\nbackup $backupFile");
}

try {
    $pdo->beginTransaction();
    $updated = ship_run(
        $pdo,
        "UPDATE products SET ship_month = ? WHERE status = '新苗預購' AND vendor_id = ? AND COALESCE(ship_month, '') != ?",
        [SHIP_MONTH, $vendorId, SHIP_MONTH]
    );
    $mirrors = ship_run(
        $pdo,
        "UPDATE products SET ship_month = ? WHERE origin_product_id IN (SELECT id FROM products WHERE status = '新苗預購' AND vendor_id = ?) AND COALESCE(ship_month, '') != ?",
        [SHIP_MONTH, $vendorId, SHIP_MONTH]
    );
    $pdo->commit();
    $preorders = (int) ship_one($pdo, "SELECT COUNT(*) FROM products WHERE status = '新苗預購' AND vendor_id = ? AND ship_month = ?", [$vendorId, SHIP_MONTH]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ship_out(500, "更新失敗，已停下\nbackup $backupFile");
}

ship_out(200, "backup $backupFile\npreorders $preorders\nupdated $updated\nmirrors $mirrors");
