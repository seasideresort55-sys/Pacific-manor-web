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
$blob = '';
foreach ([$views, garden_root() . '/src', garden_root() . '/assets'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $f) {
        if ($f->isFile() && preg_match('/\.(php|css|js)$/', $f->getFilename())) {
            $blob .= file_get_contents($f->getPathname());
        }
    }
}
$blob .= file_get_contents(garden_root() . '/index.php');
check(!preg_match('/測試|展示|示範/u', $blob), '介面文案沒有測試、展示或示範');

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
check(public_value('待確認') === '待補' && public_value('未填') === '待補', '待確認與未填在前台看成待補');
check(preorder_spec() === '3.5 吋扦插苗', '新苗預購規格是 3.5 吋扦插苗');
check(!in_array('待確認', filter_values('color_group'), true) && !in_array('未填', filter_values('fragrance'), true), '篩選沒有待確認或未填');
check(str_starts_with(url('/roses'), '/garden/'), '前台網址在 /garden 底下', url('/roses'));
$quoteHome = shipping_quote(['shipping_fee_twd' => 200, 'free_shipping_min_twd' => 2000], [['line_total' => 500, 'excluded' => false, 'name' => '甲']]);
check($quoteHome['charge'] === 200 && $quoteHome['need'] === 1500, '太平洋莊園運費 200，差 1500 才免運');
$originRow = $pdo->query("SELECT id, name, price_twd, consign_open FROM products WHERE sku = 'SHOP-3-02'")->fetch();
$originId = (int) $originRow['id'];
$pdo->prepare('UPDATE products SET consign_open = 1 WHERE id = ?')->execute([$originId]);
$took = vendor_consign_take(2, $originId);
$mirror = $pdo->query('SELECT m.name, m.price_twd, m.stock, m.description, m.status, m.stock_gate, o.stock AS origin_stock, o.name AS origin_name, o.price_twd AS origin_price FROM products m JOIN products o ON o.id = m.origin_product_id WHERE m.vendor_id = 2 AND m.origin_product_id = ' . $originId)->fetch();
check($took['ok'] && $mirror && $mirror['status'] === '上架' && $mirror['stock_gate'] === 'released' && $mirror['name'] === $mirror['origin_name'] && (int) $mirror['price_twd'] === (int) $mirror['origin_price'] && (int) $mirror['stock'] === (int) $mirror['origin_stock'], '代賣立刻上架並繼承原店');
$pdo->prepare("UPDATE products SET name = '左手香改名', price_twd = 80 WHERE id = ?")->execute([$originId]);
sync_consign_mirrors($pdo, $originId);
$copied = $pdo->query('SELECT name, price_twd FROM products WHERE origin_product_id = ' . $originId)->fetch();
check($copied && $copied['name'] === '左手香改名' && (int) $copied['price_twd'] === 80, '原店一改，代賣店跟著改');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['cart'] = [];
$originLeft = (int) $pdo->query('SELECT stock FROM products WHERE id = ' . $originId)->fetchColumn();
$mirrorId = (int) $took['product_id'];
$addAll = cart_add($originId, $originLeft);
$over = cart_add($mirrorId, 1);
check($addAll === null && $over !== null, '同一份庫存不能從兩家一起超賣');
$_SESSION['cart'] = [$mirrorId => $originLeft + 5];
$before = (int) $pdo->query('SELECT stock FROM products WHERE id = ' . $originId)->fetchColumn();
$threw = false;
try {
    place_orders(['name' => '甲', 'phone' => '1', 'address' => '址']);
} catch (Throwable $e) {
    $threw = true;
}
$after = (int) $pdo->query('SELECT stock FROM products WHERE id = ' . $originId)->fetchColumn();
check($threw && $after === $before, '結帳超賣會整筆退回，庫存不變');
$_SESSION['cart'] = [];
$pdo->prepare('DELETE FROM products WHERE origin_product_id = ?')->execute([$originId]);
$pdo->prepare('UPDATE products SET name = ?, price_twd = ?, consign_open = 0 WHERE id = ?')->execute([$originRow['name'], (int) $originRow['price_twd'], $originId]);
check((int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 0, '資料庫沒有範例訂單');
$chiffon = (string) $pdo->query("SELECT photo FROM products WHERE sku = 'ROSE-1-1357'")->fetchColumn();
check($chiffon === '', '薄紗沒有別人的照片', 'photo=' . $chiffon);
$shopDirty = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE sku LIKE 'SHOP-%' AND (photo != '' OR specs_json != '{}')")->fetchColumn();
check($shopDirty === 0, '店家自有商品沒有被寫成玫瑰規格', 'hits=' . $shopDirty);
$preDirty = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = '新苗預購' AND (specs_json != '{}' OR photo != '')")->fetchColumn();
check($preDirty === 0, '新苗預購沒有別株玫瑰的規格或照片', 'hits=' . $preDirty);
$tmp = sys_get_temp_dir() . '/garden-v13-' . getmypid() . '.sqlite';
@unlink($tmp);
$fix = new PDO('sqlite:' . $tmp);
$fix->exec('CREATE TABLE vendors (id INTEGER PRIMARY KEY, name TEXT, login TEXT, password_hash TEXT, created_at TEXT, extra TEXT)');
$fix->exec("INSERT INTO vendors VALUES (1, '舊店', 'old', 'hash', '2020-01-01', '多出來')");
$fix->exec('CREATE TABLE plant_master (id INTEGER PRIMARY KEY, note TEXT)');
$fix->exec("INSERT INTO plant_master VALUES (1, '苗')");
unset($fix);
$beforeHash = hash_file('sha256', $tmp);
$dry = [];
exec('php ' . escapeshellarg(garden_root() . '/migrate/v13_convert.php') . ' --dry-run --db=' . escapeshellarg($tmp), $dry, $dryCode);
$dryText = implode("\n", $dry);
check($dryCode === 2 && str_contains($dryText, 'UNMAPPED vendors.extra') && str_contains($dryText, 'TABLE plant_master') && hash_file('sha256', $tmp) === $beforeHash, 'v13 dry-run 只列出欄位，不改檔案');
$applyOut = [];
exec('php ' . escapeshellarg(garden_root() . '/migrate/v13_convert.php') . ' --apply --db=' . escapeshellarg($tmp), $applyOut, $applyCode);
check($applyCode === 2 && hash_file('sha256', $tmp) === $beforeHash, '對不到欄位就停下，不備份也不改');
$mapFile = sys_get_temp_dir() . '/garden-v13-map-' . getmypid() . '.php';
file_put_contents($mapFile, <<<'PHP'
<?php
return [
  'vendors' => [
    'id' => ['to' => 'vendors.legacy_id'],
    'name' => ['to' => 'vendors.name'],
    'login' => ['to' => 'vendors.login'],
    'password_hash' => ['to' => 'vendors.password_hash'],
    'created_at' => ['to' => 'vendors.created_at'],
    'extra' => ['skip' => '來源多出來的欄位，先標明不使用'],
  ],
  'plant_master' => [
    'id' => ['skip' => '尚未決定要落到哪一張 v14 表'],
    'note' => ['skip' => '尚未決定要落到哪一張 v14 表'],
  ],
];
PHP);
$apply2 = [];
exec('php ' . escapeshellarg(garden_root() . '/migrate/v13_convert.php') . ' --apply --db=' . escapeshellarg($tmp) . ' --map=' . escapeshellarg($mapFile), $apply2, $apply2Code);
$checkDb = new PDO('sqlite:' . $tmp);
$checkDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$legacy = (int) $checkDb->query('SELECT COUNT(*) FROM legacy_vendors')->fetchColumn();
$copiedVendor = $checkDb->query('SELECT name, legacy_id FROM vendors WHERE legacy_id = 1')->fetch();
exec('php ' . escapeshellarg(garden_root() . '/migrate/v13_convert.php') . ' --apply --db=' . escapeshellarg($tmp) . ' --map=' . escapeshellarg($mapFile), $apply3, $apply3Code);
$again = (int) $checkDb->query('SELECT COUNT(*) FROM vendors WHERE legacy_id = 1')->fetchColumn();
check($apply2Code === 0 && $legacy === 1 && $copiedVendor && $copiedVendor['name'] === '舊店' && $apply3Code === 0 && $again === 1, '對應齊了才改名 legacy_ 並可重跑');
@unlink($tmp);
@unlink($mapFile);
foreach (glob($tmp . '.bak-*') ?: [] as $bak) {
    @unlink($bak);
}
$customer = '';
foreach (['home.php', 'shop.php', 'card.php', 'product.php', 'cart.php', 'checkout.php', 'done.php', 'rules.php', 'about.php', 'contact.php', 'rose.php', 'layout.php'] as $view) {
    $customer .= file_get_contents(garden_root() . '/views/' . $view);
}
check(!preg_match('/抽成|再細一點|選一種植物|待確認|未填/u', $customer), '客人頁面沒有抽成或內部用語');

if ($fail > 0) {
    fwrite(STDOUT, "FAILED $fail\n");
    exit(1);
}
fwrite(STDOUT, "ALL PASSED\n");
