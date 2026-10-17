<?php
declare(strict_types=1);
/**
 * Build a v13-shaped baseline, then apply migrate/v14.php.
 * Not included in garden-v14.zip.
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
ensure_schema($pdo);

$exists = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
if ($exists > 0 && getenv('GARDEN_RESET') !== '1') {
    fwrite(STDOUT, "database already has products; set GARDEN_RESET=1 to rebuild\n");
    exit(0);
}
if (getenv('GARDEN_RESET') === '1') {
    foreach (['order_lines', 'orders', 'shipping_exclusions', 'products', 'rose_offers', 'rose_varieties', 'vendors', 'admins'] as $t) {
        $pdo->exec('DELETE FROM ' . $t);
    }
}

$now = gmdate('c');
$insV = $pdo->prepare('INSERT INTO vendors (id, name, login, password_hash, shipping_fee_twd, free_shipping_min_twd, created_at) VALUES (?,?,?,?,?,?,?)');
$insV->execute([1, '太平洋莊園', 'taiping', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), null, null, $now]);
$insV->execute([2, '山風花圃', 'shanfeng', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), null, null, $now]);
$insV->execute([3, '河岸苗園', 'hean', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 180, 1500, $now]);
$pdo->prepare('INSERT INTO admins (login, password_hash, name) VALUES (?,?,?)')
    ->execute(['admin', password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), '平台管理員']);

$preorders = [
    '藍雪花', '茉莉', '梔子花', '桂花', '茶花', '杜鵑', '繡球', '九重葛', '紫薇', '流蘇',
    '黃槐', '阿勃勒', '羊蹄甲', '楓香', '青楓', '烏桕', '樟樹', '台灣欒樹', '苦楝', '小葉欖仁',
    '羅漢松', '龍柏', '真柏', '黑松', '五葉松', '南洋杉', '福木', '春不老', '月橘', '胡椒木',
    '金露花', '仙丹花', '龍船花', '馬櫻丹', '雪茄花', '日日春', '長春花', '繁星花', '天使花', '迷迭香',
    '薰衣草', '薄荷', '羅勒', '左手香', '艾草', '香茅', '甜菊',
];
if (count($preorders) !== 47) {
    throw new RuntimeException('expected 47 preorders, got ' . count($preorders));
}
$insP = $pdo->prepare('INSERT INTO products (vendor_id, sku, name, description, price_twd, stock, status, category_code, listing_kind, stock_gate, photo, specs_json, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ($preorders as $i => $name) {
    $n = $i + 1;
    $insP->execute([
        1,
        sprintf('PRE-%03d', $n),
        $name . ' 新苗預購',
        '季節新苗，開放預購後出貨。',
        120 + ($n % 5) * 10,
        20,
        '新苗預購',
        '',
        'preorder',
        'released',
        '',
        '{}',
        $now,
    ]);
}
$others = [
    [2, 'SHOP-2-01', '甜羅勒盆栽', 80, 12],
    [2, 'SHOP-2-02', '薄荷盆栽', 70, 15],
    [2, 'SHOP-2-03', '迷迭香盆栽', 90, 10],
    [3, 'SHOP-3-01', '檸檬香茅', 60, 18],
    [3, 'SHOP-3-02', '左手香', 50, 20],
];
foreach ($others as $row) {
    $insP->execute([$row[0], $row[1], $row[2], '店家自有商品。', $row[3], $row[4], '上架', '', 'shop', 'released', '', '{}', $now]);
}

fwrite(STDOUT, "v13 baseline ready\n");
require dirname(__DIR__) . '/migrate/v14.php';
garden_migrate_v14();
$top = $pdo->query("SELECT id FROM products WHERE vendor_id = 1 AND listing_kind = 'rose' AND status = '上架' ORDER BY price_twd DESC LIMIT 1")->fetch();
if ($top) {
    $pdo->prepare('INSERT OR IGNORE INTO shipping_exclusions (vendor_id, scope, product_id, category_code, created_at) VALUES (?,?,?,?,?)')
        ->execute([1, 'product', (int) $top['id'], '', gmdate('c')]);
    fwrite(STDOUT, "vendor1_exclusion_product " . (int) $top['id'] . "\n");
}
