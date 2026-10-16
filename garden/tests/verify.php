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
$shop2 = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE sku IN ('SHOP-2-01','SHOP-2-02','SHOP-2-03')")->fetchColumn();
check($shop2 === 3, 'vendor 2 original products stay', 'count=' . $shop2);
$shop3 = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE sku IN ('SHOP-3-01','SHOP-3-02')")->fetchColumn();
check($shop3 === 2, 'vendor 3 original products stay', 'count=' . $shop3);
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

$defaultStock = (int) $pdo->query("SELECT value FROM settings WHERE key = 'consign_stock_default'")->fetchColumn();
check($defaultStock === 5, '代賣庫存預設 5', 'value=' . $defaultStock);
$stillOne = (int) $pdo->query("SELECT COUNT(*) FROM products p JOIN rose_offers ro ON ro.id = p.rose_offer_id WHERE p.vendor_id = 1 AND p.listing_kind = 'rose' AND ro.instock_at_import = 1 AND p.stock = 1 AND ro.stock_note LIKE '%平台庫存記 1%'")->fetchColumn();
check($stillOne === 0, '代賣不再以 1 棵為未公布棵數的占位');
$stock5 = (int) $pdo->query("SELECT COUNT(*) FROM products p JOIN rose_offers ro ON ro.id = p.rose_offer_id WHERE p.vendor_id = 1 AND p.listing_kind = 'rose' AND ro.instock_at_import = 1 AND p.stock = 5")->fetchColumn();
check($stock5 >= 280, '代賣可售棵數用平台設定', 'count=' . $stock5);
$preStock = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = '新苗預購' AND stock = 20")->fetchColumn();
check($preStock === 47, '新苗預購庫存維持 20', 'count=' . $preStock);
$note = (string) $pdo->query("SELECT stock_note FROM rose_offers WHERE instock_at_import = 1 AND supplier_sku NOT LIKE 'lot-%' LIMIT 1")->fetchColumn();
check(str_contains($note, '未公布棵數') && str_contains($note, '平台庫存設定'), '保留冬冬可買、棵數未公布的註記');

$split = consign_split(100);
check($split['origin'] === 70 && $split['seller'] === 30 && $split['platform'] === 0, '代賣售價 100 拆成原店 70、代賣店 30、平台 0');
$openN = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE sku IN ('SHOP-3-01','SHOP-3-02') AND consign_open = 1 AND origin_product_id IS NULL")->fetchColumn();
check($openN === 2, '河岸開放左手香與檸檬香茅代賣');
$mirror = $pdo->query("SELECT m.id, m.name, m.price_twd, m.stock, m.description, m.status, m.stock_gate, m.listing_kind, o.stock AS origin_stock, o.name AS origin_name, o.price_twd AS origin_price, o.description AS origin_desc FROM products m JOIN products o ON o.id = m.origin_product_id WHERE m.sku LIKE 'CONSIGN-2-%' AND o.sku = 'SHOP-3-02'")->fetch();
check($mirror && $mirror['name'] === $mirror['origin_name'] && (int) $mirror['price_twd'] === (int) $mirror['origin_price'] && $mirror['description'] === $mirror['origin_desc'], '代賣商品繼承原店名稱售價說明');
check($mirror && (int) $mirror['stock'] === (int) $mirror['origin_stock'] && (int) $mirror['stock'] === 18, '左手香共用庫存，賣出 2 棵後兩邊都是 18', 'stock=' . (int) ($mirror['stock'] ?? -1));
check($mirror && $mirror['status'] === '上架' && $mirror['stock_gate'] === 'released' && $mirror['listing_kind'] === 'consign', '代賣立刻上架、不用審核');
$line = $pdo->query("SELECT * FROM order_lines WHERE seller_vendor_id = 2 AND origin_vendor_id = 3 AND name = '左手香'")->fetch();
check($line && (int) $line['qty'] === 2 && (int) $line['line_twd'] === 100 && (int) $line['origin_share_twd'] === 70 && (int) $line['seller_share_twd'] === 30 && (int) $line['platform_share_twd'] === 0, '帳上記到平台 0、原店 70、代賣店 30');
$order = $line ? $pdo->query('SELECT vendor_id, shipping_twd FROM orders WHERE id = ' . (int) $line['order_id'])->fetch() : null;
check($order && (int) $order['vendor_id'] === 3 && (int) $order['shipping_twd'] === 180, '代賣出貨與運費歸原店');
$pdo->beginTransaction();
$pdo->prepare("UPDATE products SET name = '左手香改名', price_twd = 80, description = '原店新說明' WHERE sku = 'SHOP-3-02'")->execute();
$originId = (int) $pdo->query("SELECT id FROM products WHERE sku = 'SHOP-3-02'")->fetchColumn();
sync_consign_mirrors($pdo, $originId);
$copied = $pdo->prepare('SELECT name, price_twd, description, stock FROM products WHERE origin_product_id = ?');
$copied->execute([$originId]);
$copiedRow = $copied->fetch();
check($copiedRow && $copiedRow['name'] === '左手香改名' && (int) $copiedRow['price_twd'] === 80 && $copiedRow['description'] === '原店新說明', '原店一改，代賣店跟著改');
$pdo->rollBack();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['cart'] = [];
$originLeft = (int) $pdo->query("SELECT id, stock FROM products WHERE sku = 'SHOP-3-02'")->fetch()['stock'];
$mirrorId = (int) $mirror['id'];
$originPid = (int) $pdo->query("SELECT id FROM products WHERE sku = 'SHOP-3-02'")->fetchColumn();
$addAll = cart_add($originPid, $originLeft);
$over = cart_add($mirrorId, 1);
check($addAll === null && $over !== null, '同一份庫存不能從兩家一起超賣');
$_SESSION['cart'] = [$mirrorId => $originLeft + 5];
$before = (int) $pdo->query("SELECT stock FROM products WHERE sku = 'SHOP-3-02'")->fetchColumn();
$threw = false;
try {
    place_orders();
} catch (Throwable $e) {
    $threw = true;
}
$after = (int) $pdo->query("SELECT stock FROM products WHERE sku = 'SHOP-3-02'")->fetchColumn();
check($threw && $after === $before, '結帳超賣會整筆退回，庫存不變');
$_SESSION['cart'] = [];
$chiffon = (string) $pdo->query("SELECT photo FROM products WHERE sku = 'ROSE-1-1357'")->fetchColumn();
check($chiffon === '', '薄紗沒有別人的照片', 'photo=' . $chiffon);
$shopDirty = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE sku LIKE 'SHOP-%' AND (photo != '' OR specs_json != '{}')")->fetchColumn();
check($shopDirty === 0, '店家自有商品沒有被寫成玫瑰規格', 'hits=' . $shopDirty);

if ($fail > 0) {
    fwrite(STDOUT, "FAILED $fail\n");
    exit(1);
}
fwrite(STDOUT, "ALL PASSED\n");
