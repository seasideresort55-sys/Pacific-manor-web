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
    $v14 = ['vendors', 'products', 'rose_varieties', 'rose_offers', 'shipping_exclusions', 'settings', 'orders', 'order_lines', 'admins'];
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
            $to = (string) $rule['to'];
            if (!str_contains($to, '.')) {
                continue;
            }
            [$target] = explode('.', $to, 2);
            $targets[$target] = true;
        }
        foreach (array_keys($targets) as $target) {
            $has = false;
            foreach ($cols as $rule) {
                if (is_array($rule) && ($rule['to'] ?? '') === $target . '.legacy_id') {
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
        $named = $pdo->query("SELECT id FROM vendors WHERE name = '太平洋莊園'")->fetch();
        if ($named) {
            $pdo->prepare('UPDATE vendors SET shipping_fee_twd = 200, free_shipping_min_twd = 2000 WHERE id = ?')
                ->execute([(int) $named['id']]);
            fwrite(STDOUT, "shipping 太平洋莊園 200 2000\n");
        } else {
            fwrite(STDOUT, "shipping 找不到名稱剛好是太平洋莊園的店，沒有改運費\n");
        }
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
    $rank = ['vendors' => 1, 'admins' => 2, 'rose_varieties' => 3, 'products' => 4, 'orders' => 5];
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
            [$target, $field] = explode('.', (string) $rule['to'], 2);
            $groups[$target][$field] = (string) $srcCol;
        }
        foreach ($groups as $target => $fields) {
            $jobs[] = ['source' => (string) $source, 'target' => $target, 'fields' => $fields, 'rank' => $rank[$target] ?? 50];
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
        foreach ($rows as $row) {
            $legacyId = $row[$srcLegacy] ?? null;
            $seen->execute([$legacyId]);
            if ($seen->fetchColumn()) {
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
