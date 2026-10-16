<?php
declare(strict_types=1);
/**
 * Checks the review database. Not included in garden-v14.zip.
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$fail = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $fail;
    if ($ok) {
        fwrite(STDOUT, "OK  $label" . ($detail !== '' ? "  $detail" : '') . "\n");
        return;
    }
    $fail++;
    fwrite(STDOUT, "FAIL  $label" . ($detail !== '' ? "  $detail" : '') . "\n");
}

$pdo = db();
$pre = $pdo->query("SELECT id, sku, name, price_twd, stock, status, vendor_id FROM products WHERE status = '新苗預購' ORDER BY id")->fetchAll();
check(count($pre) === 47, '47 新苗預購', 'count=' . count($pre));
check((int) $pdo->query('SELECT COUNT(*) FROM products WHERE vendor_id = 2')->fetchColumn() === 3, 'vendor 2 products stay');
check((int) $pdo->query('SELECT COUNT(*) FROM products WHERE vendor_id = 3')->fetchColumn() === 2, 'vendor 3 products stay');
$v1 = $pdo->query('SELECT shipping_fee_twd, free_shipping_min_twd FROM vendors WHERE id = 1')->fetch();
check((int) $v1['shipping_fee_twd'] === 200 && (int) $v1['free_shipping_min_twd'] === 2000, '太平洋莊園運費 200 / 2000');
$v2 = $pdo->query('SELECT shipping_fee_twd, free_shipping_min_twd FROM vendors WHERE id = 2')->fetch();
check($v2['shipping_fee_twd'] === null && $v2['free_shipping_min_twd'] === null, '山風花圃運費未代填');
$v3 = $pdo->query('SELECT shipping_fee_twd, free_shipping_min_twd FROM vendors WHERE id = 3')->fetch();
check((int) $v3['shipping_fee_twd'] === 180 && (int) $v3['free_shipping_min_twd'] === 1500, '河岸苗園維持自己的運費');

$roseN = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE vendor_id = 1 AND listing_kind = 'rose' AND status = '上架'")->fetchColumn();
check($roseN >= 280 && $roseN <= 340, '代賣上架數量接近在售數', 'count=' . $roseN);

$badPrice = $pdo->query("SELECT COUNT(*) FROM products WHERE listing_kind = 'rose' AND price_twd % 10 != 0")->fetchColumn();
check((int) $badPrice === 0, '玫瑰售價取整到 10');

$sample = $pdo->query("SELECT p.price_twd, ro.cost_twd FROM products p JOIN rose_offers ro ON ro.id = p.rose_offer_id WHERE p.listing_kind = 'rose' AND ro.instock_at_import = 1 LIMIT 1")->fetch();
if ($sample) {
    $expect = round10(((int) $sample['cost_twd']) / 0.7);
    check((int) $sample['price_twd'] === $expect, '售價＝成本÷0.7 取整到 10', $sample['cost_twd'] . ' -> ' . $sample['price_twd']);
}

$leak = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE description LIKE '%冬冬%' OR description LIKE '%dongdongs%' OR description LIKE '%尚實%' OR name LIKE '%冬冬%'")->fetchColumn();
check($leak === 0, '前台商品文字沒有供應商名稱', 'hits=' . $leak);

$photoLeak = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE photo LIKE '%http%' OR specs_json LIKE '%dongdongs%' OR specs_json LIKE '%http%'")->fetchColumn();
check($photoLeak === 0, '商品照片與規格沒有供應商網址', 'hits=' . $photoLeak);

$listedDefault = (int) $pdo->query('SELECT COUNT(*) FROM rose_varieties WHERE listed = 0')->fetchColumn();
check($listedDefault > 0, '仍有未開放的品種', 'unlisted=' . $listedDefault);

$treeCat = (int) $pdo->query("SELECT COUNT(*) FROM rose_varieties WHERE class_main = 'STD'")->fetchColumn();
check($treeCat === 0, '樹玫瑰不是分類');

// shipping math
$vendor = ['shipping_fee_twd' => 200, 'free_shipping_min_twd' => 2000];
$lines = [
    ['line_total' => 520, 'excluded' => false, 'name' => '甲'],
    ['line_total' => 400, 'excluded' => true, 'name' => '乙'],
];
$q = shipping_quote($vendor, $lines);
check($q['charge'] === 200 && $q['eligible'] === 520 && $q['need'] === 1480 && count($q['excluded']) === 1, '免運只算未排除商品，運費 200');

// pick block
$block = vendor_may_take(['listed' => 0], ['offer_state' => '有貨', 'platform_stock' => 3, 'auto_preorder' => 0], 1);
check($block !== null, '未開放不能上架');
$block2 = vendor_may_take(['listed' => 1], ['offer_state' => '有貨', 'platform_stock' => 0, 'auto_preorder' => 0], 1);
check($block2 !== null, '沒庫存不能上架');

$views = garden_root() . '/views';
$pub = garden_root() . '/public';
$blob = '';
foreach ([$views, $pub, garden_root() . '/src'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $f) {
        if ($f->isFile() && preg_match('/\.(php|css|js)$/', $f->getFilename())) {
            $blob .= file_get_contents($f->getPathname());
        }
    }
}
check(!preg_match('/測試|展示/u', $blob), '介面文案沒有測試或展示');

if ($fail > 0) {
    fwrite(STDOUT, "FAILED $fail\n");
    exit(1);
}
fwrite(STDOUT, "ALL PASSED\n");
