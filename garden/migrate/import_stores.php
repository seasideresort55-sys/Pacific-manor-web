<?php
declare(strict_types=1);

/**
 * 把河岸苗園、山風花圃寫進現有資料庫。
 * 先在 migrate/stores.json 填 login 與 password_hash，再執行。
 * 密碼不要寫進這個程式。欄位空著就停下。
 *
 * php migrate/import_stores.php --db=/home/irihvsuu/garden_private/garden.sqlite
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "請在命令列執行。\n");
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

$db = '';
$file = dirname(__FILE__) . '/stores.json';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $db = substr($arg, 5);
    } elseif (str_starts_with($arg, '--file=')) {
        $file = substr($arg, 7);
    }
}
if ($db !== '') {
    putenv('GARDEN_DB=' . $db);
}

$raw = json_decode((string) file_get_contents($file), true);
if (!is_array($raw) || !isset($raw['stores']) || !is_array($raw['stores'])) {
    fwrite(STDERR, "匯入檔不是店家清單。\n");
    exit(1);
}

$pdo = db();
$inserted = 0;
foreach ($raw['stores'] as $store) {
    $name = trim((string) ($store['name'] ?? ''));
    $login = trim((string) ($store['login'] ?? ''));
    $hash = trim((string) ($store['password_hash'] ?? ''));
    if ($name === '' || $login === '' || $hash === '' || !str_starts_with($hash, '$')) {
        fwrite(STDERR, $name . " 還沒有登入名稱或密碼雜湊，沒有寫入。\n");
        exit(1);
    }
    $exists = $pdo->prepare('SELECT id FROM vendors WHERE name = ? OR login = ?');
    $exists->execute([$name, $login]);
    if ($exists->fetchColumn()) {
        fwrite(STDOUT, "已有 $name\n");
        continue;
    }
    $fee = $store['shipping_fee_twd'];
    $min = $store['free_shipping_min_twd'];
    $fee = $fee === null || $fee === '' ? null : (int) $fee;
    $min = $min === null || $min === '' ? null : (int) $min;
    $pdo->prepare('INSERT INTO vendors (name, login, password_hash, shipping_fee_twd, free_shipping_min_twd, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$name, $login, $hash, $fee, $min, gmdate('c')]);
    fwrite(STDOUT, "新增 $name\n");
    $inserted++;
}
fwrite(STDOUT, "inserted $inserted\n");
