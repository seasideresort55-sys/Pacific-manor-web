<?php
declare(strict_types=1);

/**
 * 先看 v13 資料庫長什麼樣子，再轉換。
 * dry-run 只讀、不改檔案。
 * --apply 在每一個來源欄都有對應之後才會備份並寫入。對不到就停下。
 *
 * php migrate/v13_convert.php --dry-run --db=/home/irihvsuu/garden_private/garden.sqlite
 * php migrate/v13_convert.php --apply --db=/home/irihvsuu/garden_private/garden.sqlite
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "請在命令列執行。\n");
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

function v13_args(array $argv): array
{
    $mode = 'dry-run';
    $db = '';
    $map = dirname(__FILE__) . '/v13_map.php';
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $mode = 'dry-run';
        } elseif ($arg === '--apply') {
            $mode = 'apply';
        } elseif (str_starts_with($arg, '--db=')) {
            $db = substr($arg, 5);
        } elseif (str_starts_with($arg, '--map=')) {
            $map = substr($arg, 6);
        } else {
            fwrite(STDERR, "不認識的參數 $arg\n");
            exit(1);
        }
    }
    if ($db === '') {
        $db = '/home/irihvsuu/garden_private/garden.sqlite';
    }
    return [$mode, $db, $map];
}

function v13_schema(PDO $pdo): array
{
    $tables = [];
    $names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll();
    foreach ($names as $row) {
        $name = (string) $row['name'];
        $cols = $pdo->query('PRAGMA table_info(' . str_replace('"', '', $name) . ')')->fetchAll();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', $name) . '"')->fetchColumn();
        $tables[$name] = ['rows' => $count, 'columns' => $cols];
    }
    return $tables;
}

function v13_load_map(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "找不到對應檔 $path\n");
        exit(1);
    }
    $map = require $path;
    return is_array($map) ? $map : [];
}

function v13_is_converted_shape(array $schema): bool
{
    foreach (array_keys($schema) as $name) {
        if (str_starts_with($name, 'legacy_')) {
            return true;
        }
    }
    return false;
}

function v13_needs_map(string $table, array $schema): bool
{
    if (str_starts_with($table, 'legacy_') || str_starts_with($table, 'sqlite_')) {
        return false;
    }
    if (isset($schema['legacy_' . $table])) {
        return false;
    }
    $v14 = [
        'vendors', 'products', 'rose_varieties', 'rose_offers', 'shipping_exclusions', 'settings',
        'orders', 'order_lines', 'admins', 'users', 'plant_master', 'rose_reference', 'media_asset',
        'parent_order', 'shipment',
    ];
    if (v13_is_converted_shape($schema) && in_array($table, $v14, true)) {
        return false;
    }
    return true;
}

function v13_unmapped(array $schema, array $map): array
{
    $missing = [];
    foreach ($schema as $table => $info) {
        if (!v13_needs_map($table, $schema)) {
            continue;
        }
        foreach ($info['columns'] as $col) {
            $name = (string) $col['name'];
            $rule = $map[$table][$name] ?? null;
            if (!is_array($rule) || (!isset($rule['to']) && !isset($rule['skip']))) {
                $missing[] = $table . '.' . $name;
            }
        }
    }
    return $missing;
}

function v13_targets_missing_legacy(array $map): array
{
    $need = [];
    foreach ($map as $table => $cols) {
        if (!is_array($cols)) {
            continue;
        }
        $targets = [];
        foreach ($cols as $rule) {
            if (!is_array($rule) || !isset($rule['to'])) {
                continue;
            }
            $tos = $rule['to'];
            $tos = is_array($tos) ? $tos : [$tos];
            foreach ($tos as $to) {
                $to = (string) $to;
                if (!str_contains($to, '.')) {
                    continue;
                }
                [$target] = explode('.', $to, 2);
                $targets[$target] = true;
            }
        }
        foreach (array_keys($targets) as $target) {
            $has = false;
            foreach ($cols as $rule) {
                $tos = is_array($rule) ? ($rule['to'] ?? null) : null;
            $tos = is_array($tos) ? $tos : [$tos];
            if (in_array($target . '.legacy_id', $tos, true)) {
                    $has = true;
                }
            }
            if (!$has) {
                $need[] = $table . ' → ' . $target . '.legacy_id';
            }
        }
    }
    return $need;
}

function v13_print_schema(array $schema): void
{
    fwrite(STDOUT, 'tables ' . count($schema) . "\n");
    foreach ($schema as $table => $info) {
        fwrite(STDOUT, 'TABLE ' . $table . ' rows=' . $info['rows'] . "\n");
        foreach ($info['columns'] as $col) {
            $pk = (int) $col['pk'] === 1 ? ' pk' : '';
            $nn = (int) $col['notnull'] === 1 ? ' notnull' : '';
            $type = (string) $col['type'];
            fwrite(STDOUT, '  ' . $col['name'] . ' ' . $type . $pk . $nn . "\n");
        }
    }
}

function v13_quote(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

function v13_apply(PDO $pdo, array $schema, array $map): void
{
    $markers = ['vendor_product', 'plant_master', 'parent_order', 'rose_variety', 'legacy_vendor_product', 'legacy_plant_master'];
    $found = 0;
    foreach ($markers as $name) {
        if (isset($schema[$name])) {
            $found++;
        }
    }
    if ($found === 0) {
        fwrite(STDERR, "這不是 v13 資料庫（沒有 vendor_product、plant_master、parent_order、rose_variety），沒有改動。\n");
        exit(2);
    }
    $pdo->beginTransaction();
    try {
        foreach (array_keys($map) as $table) {
            if (!isset($schema[$table])) {
                continue;
            }
            $legacy = 'legacy_' . $table;
            if (isset($schema[$legacy])) {
                fwrite(STDOUT, "keep $legacy\n");
                continue;
            }
            $pdo->exec('ALTER TABLE ' . v13_quote($table) . ' RENAME TO ' . v13_quote($legacy));
            fwrite(STDOUT, "rename $table $legacy\n");
        }
        ensure_schema($pdo);
        $copied = v13_copy($pdo, $map);
        $named = $pdo->query("SELECT id FROM vendors WHERE name = '太平洋莊園' AND legacy_id IS NULL")->fetch();
        if ($named) {
            $pdo->prepare('UPDATE vendors SET shipping_fee_twd = 200, free_shipping_min_twd = 2000 WHERE id = ?')
                ->execute([(int) $named['id']]);
            fwrite(STDOUT, "shipping 太平洋莊園 200 2000\n");
        } else {
            fwrite(STDOUT, "shipping 找不到名稱剛好是太平洋莊園的店，沒有改運費\n");
        }
        v13_finish($pdo);
        v13_report($pdo);
        $pdo->commit();
        fwrite(STDOUT, 'copied ' . $copied . "\n");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, '轉換沒有寫入：' . $e->getMessage() . "\n");
        exit(2);
    }
}

function v13_copy(PDO $pdo, array $map): int
{
    $n = 0;
    $sourceRank = [
        'vendors' => 1,
        'vendor_settings' => 2,
        'users' => 3,
        'rose_variety' => 4,
        'plant_master' => 5,
        'vendor_product' => 6,
        'media_asset' => 7,
        'rose_reference' => 8,
        'parent_order' => 9,
        'vendor_order' => 10,
        'order_item' => 11,
        'shipment' => 12,
    ];
    $jobs = [];
    foreach ($map as $source => $cols) {
        if (!is_array($cols)) {
            continue;
        }
        $groups = [];
        foreach ($cols as $srcCol => $rule) {
            if (!is_array($rule) || !isset($rule['to'])) {
                continue;
            }
            $targets = $rule['to'];
            $targets = is_array($targets) ? $targets : [$targets];
            foreach ($targets as $to) {
                [$target, $field] = explode('.', (string) $to, 2);
                $groups[$target][$field] = (string) $srcCol;
            }
        }
        foreach ($groups as $target => $fields) {
            $jobs[] = [
                'source' => (string) $source,
                'target' => $target,
                'fields' => $fields,
                'rank' => $sourceRank[(string) $source] ?? 50,
            ];
        }
    }
    usort($jobs, static fn(array $a, array $b): int => $a['rank'] <=> $b['rank']);
    foreach ($jobs as $job) {
        $legacy = 'legacy_' . $job['source'];
        $rows = $pdo->query('SELECT * FROM ' . v13_quote($legacy))->fetchAll();
        $fields = array_keys($job['fields']);
        $sql = 'INSERT INTO ' . v13_quote($job['target']) . ' (' . implode(',', array_map('v13_quote', $fields)) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $ins = $pdo->prepare($sql);
        $srcLegacy = $job['fields']['legacy_id'];
        $seen = $pdo->prepare('SELECT 1 FROM ' . v13_quote($job['target']) . ' WHERE legacy_id = ?');
        $setFields = array_values(array_filter($fields, static fn(string $field): bool => $field !== 'legacy_id'));
        $upd = null;
        if ($setFields !== []) {
            $sets = [];
            foreach ($setFields as $field) {
                $sets[] = v13_quote($field) . ' = ?';
            }
            $upd = $pdo->prepare('UPDATE ' . v13_quote($job['target']) . ' SET ' . implode(',', $sets) . ' WHERE legacy_id = ?');
        }
        foreach ($rows as $row) {
            $legacyId = $row[$srcLegacy] ?? null;
            $seen->execute([$legacyId]);
            if ($seen->fetchColumn()) {
                if ($upd) {
                    $args = [];
                    foreach ($setFields as $field) {
                        $args[] = $row[$job['fields'][$field]] ?? null;
                    }
                    $args[] = $legacyId;
                    $upd->execute($args);
                }
                continue;
            }
            $args = [];
            foreach ($fields as $field) {
                $args[] = $row[$job['fields'][$field]] ?? null;
            }
            $ins->execute($args);
            $n++;
        }
    }
    return $n;
}

function v13_report(PDO $pdo): void
{
    $pairs = [
        ['legacy_vendors', 'vendors', '店家'],
        ['legacy_vendor_product', 'products', '商品'],
        ['legacy_users', 'users', '登入'],
        ['legacy_vendor_settings', 'vendors', '店家設定列'],
        ['legacy_rose_variety', 'rose_varieties', '玫瑰品種'],
        ['legacy_rose_reference', 'rose_reference', '玫瑰引用'],
        ['legacy_plant_master', 'plant_master', '植物主檔'],
        ['legacy_media_asset', 'media_asset', '媒體'],
        ['legacy_parent_order', 'parent_order', '母訂單'],
        ['legacy_vendor_order', 'orders', '店家訂單'],
        ['legacy_order_item', 'order_lines', '訂單明細'],
        ['legacy_shipment', 'shipment', '出貨'],
    ];
    fwrite(STDOUT, "COUNTS\n");
    foreach ($pairs as [$from, $to, $label]) {
        $tables = array_column($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(), 'name');
        if (!in_array($from, $tables, true) || !in_array($to, $tables, true)) {
            continue;
        }
        $a = (int) $pdo->query('SELECT COUNT(*) FROM ' . v13_quote($from))->fetchColumn();
        $b = (int) $pdo->query('SELECT COUNT(*) FROM ' . v13_quote($to))->fetchColumn();
        fwrite(STDOUT, "COUNT $label $from=$a $to=$b\n");
    }
    if (in_array('legacy_vendor_product', array_column($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(), 'name'), true)) {
        $sql = <<<'SQL'
SELECT v.legacy_id, v.name, v.enabled, v.login,
       (SELECT COUNT(*) FROM legacy_vendor_product lp WHERE lp.vendor_id = v.legacy_id) AS before_n,
       (SELECT COUNT(*) FROM products p WHERE p.vendor_id = v.id) AS after_n
FROM vendors v
WHERE v.legacy_id IS NOT NULL
ORDER BY v.legacy_id
SQL;
        foreach ($pdo->query($sql) as $row) {
            fwrite(STDOUT, 'VENDOR ' . $row['legacy_id'] . ' ' . $row['name'] . ' enabled=' . $row['enabled'] . ' login=' . $row['login'] . ' before=' . $row['before_n'] . ' after=' . $row['after_n'] . "\n");
        }
        $status = $pdo->query("SELECT legacy_status, status, COUNT(*) AS c FROM products GROUP BY legacy_status, status ORDER BY legacy_status, status");
        foreach ($status as $row) {
            fwrite(STDOUT, 'STATUS ' . $row['legacy_status'] . ' -> ' . $row['status'] . ' ' . $row['c'] . "\n");
        }
        $sample = $pdo->query("SELECT price_twd, photo FROM products WHERE legacy_id = 135")->fetch();
        $vendor = $pdo->query("SELECT shipping_fee_twd, free_shipping_min_twd, pay_days, default_service, shop_status, enabled FROM vendors WHERE legacy_id = 1")->fetch();
        if ($sample) {
            fwrite(STDOUT, 'PRICE 大遊行 v13_cents=36000 v13_display=NT$360 converted=' . (int) $sample['price_twd'] . ' photo=' . $sample['photo'] . "\n");
        }
        if ($vendor) {
            fwrite(STDOUT, 'SHIP vendor1 service=' . $vendor['default_service'] . ' shipping_fee_twd=' . $vendor['shipping_fee_twd'] . ' free=' . $vendor['free_shipping_min_twd'] . ' pay_days=' . $vendor['pay_days'] . "\n");
        }
        $hidden = $pdo->query("SELECT name, shop_status, enabled FROM vendors WHERE legacy_id = 8")->fetch();
        if ($hidden) {
            fwrite(STDOUT, 'SHOP8 ' . $hidden['name'] . ' shop_status=' . $hidden['shop_status'] . ' enabled=' . $hidden['enabled'] . "\n");
        }
    }
}

function v13_cents_to_nt(mixed $cents): ?int
{
    if ($cents === null || $cents === '') {
        return null;
    }
    return intdiv((int) $cents, 100);
}

function v13_divide_money_json(string $json): string
{
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $json;
    }
    $out = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $inner = [];
            foreach ($value as $cls => $cents) {
                $inner[$cls] = v13_cents_to_nt($cents);
            }
            $out[$key] = $inner;
        } elseif (is_numeric($value)) {
            $out[$key] = v13_cents_to_nt($value);
        } else {
            $out[$key] = $value;
        }
    }
    $encoded = json_encode($out, JSON_UNESCAPED_UNICODE);
    return $encoded === false ? $json : $encoded;
}

function v13_checkout_fee_cents(string $defaultService, string $feesJson, string $tiersJson): ?int
{
    $service = trim($defaultService) !== '' ? trim($defaultService) : 'HCT';
    $fees = json_decode($feesJson, true);
    $tiers = json_decode($tiersJson, true);
    if (!is_array($fees)) {
        $fees = [];
    }
    if (!is_array($tiers)) {
        $tiers = [];
    }
    $tier = $tiers[$service] ?? null;
    if (is_array($tier) && $tier !== []) {
        $vals = [];
        foreach ($tier as $cents) {
            if ($cents === null || $cents === '') {
                continue;
            }
            $vals[] = (int) $cents;
        }
        $uniq = array_values(array_unique($vals));
        if (count($uniq) === 1) {
            return $uniq[0];
        }
    }
    if (array_key_exists($service, $fees) && $fees[$service] !== null && $fees[$service] !== '') {
        return (int) $fees[$service];
    }
    return null;
}

function v13_finish(PDO $pdo): void
{
    $tables = array_column($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(), 'name');
    if (in_array('legacy_users', $tables, true)) {
        $upd = $pdo->prepare('UPDATE vendors SET login = ?, password_hash = ?, role = ? WHERE legacy_id = ?');
        foreach ($pdo->query('SELECT username, password_hash, role, vendor_id FROM legacy_users') as $row) {
            if ($row['vendor_id'] === null || $row['vendor_id'] === '') {
                continue;
            }
            $upd->execute([$row['username'], $row['password_hash'], $row['role'], $row['vendor_id']]);
        }
    }
    if (in_array('products', $tables, true)) {
        $pdo->exec("UPDATE products SET status = '草稿' WHERE legacy_status = 'draft'");
        $pdo->exec("UPDATE products SET status = '新苗預購' WHERE legacy_status = 'sale' AND (name LIKE '%新苗預購%' OR description LIKE '%新苗預購%')");
        $pdo->exec("UPDATE products SET status = '上架' WHERE legacy_status = 'sale' AND status = 'sale'");
        $pdo->exec("UPDATE products SET listing_kind = 'rose' WHERE rose_legacy_id IS NOT NULL AND rose_legacy_id != 0");
    }
    if (in_array('vendors', $tables, true)) {
        $pdo->exec('UPDATE vendors SET enabled = CASE WHEN is_demo = 1 THEN 0 ELSE 1 END WHERE legacy_id IS NOT NULL');
    }
    if (in_array('legacy_vendor_product', $tables, true)) {
        $pdo->exec('UPDATE products SET price_twd = (SELECT price / 100 FROM legacy_vendor_product lp WHERE lp.id = products.legacy_id) WHERE legacy_id IS NOT NULL');
        $pdo->exec('UPDATE products SET original_price = (SELECT CASE WHEN lp.original_price IS NULL THEN NULL ELSE lp.original_price / 100 END FROM legacy_vendor_product lp WHERE lp.id = products.legacy_id) WHERE legacy_id IS NOT NULL');
        $pdo->exec('UPDATE products SET cost_price = (SELECT CASE WHEN lp.cost_price IS NULL THEN NULL ELSE lp.cost_price / 100 END FROM legacy_vendor_product lp WHERE lp.id = products.legacy_id) WHERE legacy_id IS NOT NULL');
        $photo = $pdo->prepare('UPDATE products SET photo = ? WHERE id = ?');
        $photoRows = $pdo->query("SELECT id, photos FROM products WHERE legacy_id IS NOT NULL")->fetchAll();
        foreach ($photoRows as $row) {
            $list = json_decode((string) $row['photos'], true);
            $name = is_array($list) && isset($list[0]) ? (string) $list[0] : '';
            if (preg_match('/^[a-f0-9]{32}\\.webp$/', $name) === 1) {
                $photo->execute(['media.php?name=' . $name, (int) $row['id']]);
            }
        }
    }
    if (in_array('legacy_vendor_settings', $tables, true)) {
        $upd = $pdo->prepare('UPDATE vendors SET fees_json = ?, fee_tiers = ?, extra_item_fee = ?, free_shipping_min_twd = ?, shipping_fee_twd = ? WHERE legacy_id = ?');
        foreach ($pdo->query('SELECT vendor_id, default_service, fees, fee_tiers, extra_item_fee, free_shipping_min FROM legacy_vendor_settings') as $row) {
            $cents = v13_checkout_fee_cents((string) $row['default_service'], (string) $row['fees'], (string) $row['fee_tiers']);
            $upd->execute([
                v13_divide_money_json((string) $row['fees']),
                v13_divide_money_json((string) $row['fee_tiers']),
                v13_cents_to_nt($row['extra_item_fee']) ?? 0,
                v13_cents_to_nt($row['free_shipping_min']) ?? 0,
                $cents === null ? null : v13_cents_to_nt($cents),
                $row['vendor_id'],
            ]);
        }
    }
    if (in_array('legacy_vendor_order', $tables, true)) {
        $pdo->exec('UPDATE orders SET eligible_twd = (SELECT subtotal / 100 FROM legacy_vendor_order o WHERE o.id = orders.legacy_id), shipping_twd = (SELECT shipping_fee / 100 FROM legacy_vendor_order o WHERE o.id = orders.legacy_id), total_twd = (SELECT total / 100 FROM legacy_vendor_order o WHERE o.id = orders.legacy_id) WHERE legacy_id IS NOT NULL');
    }
    if (in_array('legacy_order_item', $tables, true)) {
        $pdo->exec('UPDATE order_lines SET price_twd = (SELECT price / 100 FROM legacy_order_item i WHERE i.id = order_lines.legacy_id) WHERE legacy_id IS NOT NULL');
    }
    if (in_array('legacy_rose_variety', $tables, true)) {
        $pdo->exec("UPDATE rose_varieties SET breeder = brand WHERE legacy_id IS NOT NULL AND brand != '' AND brand NOT IN ('待補', '待確認', '未填') AND (breeder IS NULL OR breeder = '' OR breeder IN ('待補', '待確認', '未填'))");
    }
}

[$mode, $db, $mapPath] = v13_args($argv);
if (!is_file($db)) {
    fwrite(STDERR, "找不到資料庫 $db\n");
    exit(1);
}

if ($mode === 'dry-run') {
    $pdo = new PDO('sqlite:file:' . $db . '?mode=ro', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 1);
    $before = filemtime($db);
    $schema = v13_schema($pdo);
    $map = v13_load_map($mapPath);
    v13_print_schema($schema);
    $missing = v13_unmapped($schema, $map);
    foreach ($missing as $item) {
        fwrite(STDOUT, "UNMAPPED $item\n");
    }
    $need = v13_targets_missing_legacy($map);
    foreach ($need as $item) {
        fwrite(STDOUT, "NEED $item\n");
    }
    clearstatcache(true, $db);
    if (filemtime($db) !== $before) {
        fwrite(STDERR, "dry-run 改到檔案了，這不該發生。\n");
        exit(1);
    }
    fwrite(STDOUT, $missing === [] ? "DRY-RUN MAP COMPLETE\n" : 'DRY-RUN STOP unmapped=' . count($missing) . "\n");
    exit($missing === [] ? 0 : 2);
}

$map = v13_load_map($mapPath);
$read = new PDO('sqlite:' . $db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$schema = v13_schema($read);
$missing = v13_unmapped($schema, $map);
$need = v13_targets_missing_legacy($map);
if ($missing !== [] || $need !== []) {
    v13_print_schema($schema);
    foreach ($missing as $item) {
        fwrite(STDOUT, "UNMAPPED $item\n");
    }
    foreach ($need as $item) {
        fwrite(STDOUT, "NEED $item\n");
    }
    fwrite(STDERR, "還有對不到的欄位，沒有備份，也沒有改資料庫。\n");
    exit(2);
}
unset($read);
$bak = $db . '.bak-' . gmdate('YmdHis');
if (!copy($db, $bak)) {
    fwrite(STDERR, "備份失敗\n");
    exit(1);
}
fwrite(STDOUT, "backup $bak\n");
$pdo = new PDO('sqlite:' . $db, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = OFF');
v13_apply($pdo, $schema, $map);
fwrite(STDOUT, "APPLY DONE\n");
