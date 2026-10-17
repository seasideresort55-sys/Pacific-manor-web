<?php
declare(strict_types=1);

const GARDEN_MODE = 'mock';
const PENDING = '待補';

function garden_root(): string
{
    return dirname(__DIR__);
}

function garden_config(): array
{
    static $cfg = null;
    if (is_array($cfg)) {
        return $cfg;
    }
    $cfg = [
        'db' => garden_root() . '/data/garden.sqlite',
        'payment' => 'mock',
        'base_path' => '/garden',
        'phone' => '0937667917',
        'line' => '@a0937667917',
        'host' => 'seasideresort.com.tw',
    ];
    $path = getenv('GARDEN_CONFIG');
    if ($path === false || $path === '') {
        $path = '/home/irihvsuu/garden_private/config.php';
    }
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            foreach (['payment', 'base_path', 'phone', 'line', 'host'] as $key) {
                if (isset($loaded[$key]) && is_string($loaded[$key]) && $loaded[$key] !== '') {
                    $cfg[$key] = $loaded[$key];
                }
            }
            $dbKey = '';
            if (isset($loaded['db']) && is_string($loaded['db']) && $loaded['db'] !== '') {
                $dbKey = $loaded['db'];
            } elseif (isset($loaded['database']) && is_string($loaded['database']) && $loaded['database'] !== '') {
                $dbKey = $loaded['database'];
            }
            if ($dbKey !== '') {
                $cfg['db'] = $dbKey;
            }
        }
    }
    $envDb = getenv('GARDEN_DB');
    if ($envDb !== false && $envDb !== '') {
        $cfg['db'] = $envDb;
    }
    $cfg['payment'] = 'mock';
    return $cfg;
}

function db_path(): string
{
    return garden_config()['db'];
}

function base_path(): string
{
    $base = garden_config()['base_path'];
    $base = '/' . trim($base, '/');
    return $base === '/' ? '' : $base;
}

function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    $base = base_path();
    if ($path === '/') {
        return $base === '' ? '/' : $base . '/';
    }
    return $base . $path;
}

function media_url(string $photo): string
{
    return url(ltrim($photo, '/'));
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $path = db_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    ensure_schema($pdo);
    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int $n): string
{
    return 'NT$' . number_format($n);
}

function round10(float $n): int
{
    return (int) (round($n / 10) * 10);
}

function redact(string $text): string
{
    $text = preg_replace('/https?:\/\/\S+/u', '', $text) ?? $text;
    $text = preg_replace('/冬冬玫瑰園|冬冬玫瑰網|冬冬|dongdongs\.com|dongdongs/iu', '', $text) ?? $text;
    $text = preg_replace('/尚實園藝|尚實|shop2000\.com\.tw/iu', '', $text) ?? $text;
    $text = preg_replace('/玫瑰天地|四季有花玫瑰園|四季有花/u', '', $text) ?? $text;
    $text = preg_replace('/[ \t]{2,}/u', ' ', $text) ?? $text;
    return trim($text);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('請重新整理頁面後再送出。');
    }
}

function redirect(string $to): never
{
    if (!preg_match('#^https?://#i', $to)) {
        $base = base_path();
        if ($base !== '' && !str_starts_with($to, $base . '/') && $to !== $base && $to !== $base . '/') {
            $to = url($to);
        }
    }
    header('Location: ' . $to, true, 303);
    exit;
}

function safe_return_path(string $to): string
{
    $base = base_path();
    $to = trim($to);
    if ($to === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $to) || str_contains($to, '\\') || str_starts_with($to, '//')) {
        return url('/');
    }
    if ($base !== '' && $to !== $base && !str_starts_with($to, $base . '/')) {
        return url('/');
    }
    return $to;
}

function is_blank_value(?string $value): bool
{
    $value = trim((string) $value);
    return $value === '' || in_array($value, ['待補', '待確認', '未填', '未知', '無資料', 'N/A', 'n/a', '-'], true);
}

function public_value(?string $value): string
{
    return is_blank_value($value) ? PENDING : trim((string) $value);
}

function categories(): array
{
    return [
        ['code' => 'HT', 'name' => '大輪直立', 'group' => '玫瑰', 'blurb' => ''],
        ['code' => 'FL', 'name' => '中輪豐花', 'group' => '玫瑰', 'blurb' => ''],
        ['code' => 'MIN', 'name' => '迷你', 'group' => '玫瑰', 'blurb' => ''],
        ['code' => 'SHR', 'name' => '灌木', 'group' => '玫瑰', 'blurb' => '英國玫瑰以育種者辨認，歸在灌木。'],
        ['code' => 'GC', 'name' => '地被', 'group' => '玫瑰', 'blurb' => ''],
        ['code' => 'CL', 'name' => '小型藤本', 'group' => '玫瑰', 'blurb' => '枝條較短，適合拱門與柱子，一般約可爬 2–3 公尺。'],
        ['code' => 'RA', 'name' => '蔓性藤本', 'group' => '玫瑰', 'blurb' => '枝條長而軟，適合籬笆與棚架整片披覆。'],
        ['code' => 'OGR', 'name' => '古典玫瑰', 'group' => '玫瑰', 'blurb' => ''],
        ['code' => 'SP', 'name' => '原種', 'group' => '玫瑰', 'blurb' => ''],
        ['code' => 'RS', 'name' => '砧木與繁殖材料', 'group' => '砧木與繁殖材料', 'blurb' => ''],
    ];
}

function category_name(string $code): string
{
    foreach (categories() as $cat) {
        if ($cat['code'] === $code) {
            return $cat['name'];
        }
    }
    return $code === '' ? PENDING : $code;
}

function public_category_label(string $code): string
{
    if (is_blank_value($code)) {
        return '';
    }
    $name = category_name($code);
    return is_blank_value($name) ? '' : $name;
}

function category_blurb(string $code): string
{
    foreach (categories() as $cat) {
        if ($cat['code'] === $code) {
            return $cat['blurb'];
        }
    }
    return '';
}

function ogr_classes(): array
{
    return ['Tea', 'China', 'Bourbon', 'Noisette', 'Damask', 'Gallica', 'Alba', 'Centifolia', 'Moss', 'Portland', 'Hybrid Perpetual'];
}

function ensure_schema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS vendors (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  login TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL DEFAULT '',
  shipping_fee_twd INTEGER,
  free_shipping_min_twd INTEGER,
  created_at TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY,
  vendor_id INTEGER NOT NULL,
  sku TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  price_twd INTEGER NOT NULL DEFAULT 0,
  stock INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL,
  category_code TEXT NOT NULL DEFAULT '',
  listing_kind TEXT NOT NULL DEFAULT 'shop',
  rose_offer_id INTEGER,
  stock_gate TEXT NOT NULL DEFAULT 'released',
  vendor_edited INTEGER NOT NULL DEFAULT 0,
  photo TEXT NOT NULL DEFAULT '',
  specs_json TEXT NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id)
);
CREATE TABLE IF NOT EXISTS rose_varieties (
  id INTEGER PRIMARY KEY,
  variety_key TEXT NOT NULL UNIQUE,
  name_zh TEXT NOT NULL,
  name_orig TEXT NOT NULL DEFAULT '待補',
  aliases TEXT NOT NULL DEFAULT '待補',
  reg_name TEXT NOT NULL DEFAULT '待補',
  class_main TEXT NOT NULL DEFAULT '待補',
  class_intl TEXT NOT NULL DEFAULT '待補',
  ogr_class TEXT NOT NULL DEFAULT '待補',
  series TEXT NOT NULL DEFAULT '待補',
  breeder TEXT NOT NULL DEFAULT '待補',
  breeder_country TEXT NOT NULL DEFAULT '待補',
  year TEXT NOT NULL DEFAULT '待補',
  color_group TEXT NOT NULL DEFAULT '待補',
  color_desc TEXT NOT NULL DEFAULT '待補',
  fragrance TEXT NOT NULL DEFAULT '待補',
  scent_type TEXT NOT NULL DEFAULT '待補',
  flower_dia_cm TEXT NOT NULL DEFAULT '待補',
  flower_size TEXT NOT NULL DEFAULT '待補',
  petals TEXT NOT NULL DEFAULT '待補',
  doubleness TEXT NOT NULL DEFAULT '待補',
  form TEXT NOT NULL DEFAULT '待補',
  inflorescence TEXT NOT NULL DEFAULT '待補',
  bloom_habit TEXT NOT NULL DEFAULT '待補',
  bloom_months TEXT NOT NULL DEFAULT '待補',
  height_cm TEXT NOT NULL DEFAULT '待補',
  width_cm TEXT NOT NULL DEFAULT '待補',
  growth_habit TEXT NOT NULL DEFAULT '待補',
  disease_bs TEXT NOT NULL DEFAULT '待補',
  disease_pm TEXT NOT NULL DEFAULT '待補',
  heat_tw TEXT NOT NULL DEFAULT '待補',
  heat_flag TEXT NOT NULL DEFAULT '待補',
  shade TEXT NOT NULL DEFAULT '待補',
  usda_zone TEXT NOT NULL DEFAULT '待補',
  uses TEXT NOT NULL DEFAULT '待補',
  thorns TEXT NOT NULL DEFAULT '待補',
  awards TEXT NOT NULL DEFAULT '待補',
  pvr TEXT NOT NULL DEFAULT '待補',
  seedling_types TEXT NOT NULL DEFAULT '待補',
  description TEXT NOT NULL DEFAULT '',
  listed INTEGER NOT NULL DEFAULT 0,
  multiplier_a REAL,
  retail_divisor REAL,
  sources_json TEXT NOT NULL DEFAULT '{}',
  updated_at TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS rose_offers (
  id INTEGER PRIMARY KEY,
  variety_id INTEGER NOT NULL,
  supplier_sku TEXT NOT NULL UNIQUE,
  list_name TEXT NOT NULL,
  seedling TEXT NOT NULL DEFAULT '待補',
  cost_twd INTEGER,
  platform_stock INTEGER NOT NULL DEFAULT 0,
  offer_state TEXT NOT NULL DEFAULT '有貨',
  auto_preorder INTEGER NOT NULL DEFAULT 0,
  eta TEXT NOT NULL DEFAULT '',
  stock_note TEXT NOT NULL DEFAULT '',
  photo TEXT NOT NULL DEFAULT '',
  menu_label TEXT NOT NULL DEFAULT '',
  instock_at_import INTEGER NOT NULL DEFAULT 0,
  consigned_product_id INTEGER,
  FOREIGN KEY (variety_id) REFERENCES rose_varieties(id)
);
CREATE TABLE IF NOT EXISTS shipping_exclusions (
  id INTEGER PRIMARY KEY,
  vendor_id INTEGER NOT NULL,
  scope TEXT NOT NULL,
  product_id INTEGER,
  category_code TEXT,
  created_at TEXT NOT NULL,
  UNIQUE (vendor_id, scope, product_id, category_code),
  FOREIGN KEY (vendor_id) REFERENCES vendors(id)
);
CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY,
  vendor_id INTEGER NOT NULL,
  total_twd INTEGER NOT NULL,
  shipping_twd INTEGER,
  eligible_twd INTEGER NOT NULL,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS order_lines (
  id INTEGER PRIMARY KEY,
  order_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  origin_product_id INTEGER NOT NULL,
  seller_vendor_id INTEGER NOT NULL,
  origin_vendor_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  qty INTEGER NOT NULL,
  price_twd INTEGER NOT NULL,
  line_twd INTEGER NOT NULL,
  origin_share_twd INTEGER NOT NULL,
  seller_share_twd INTEGER NOT NULL,
  platform_share_twd INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY,
  login TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  name TEXT NOT NULL
);
SQL);
    $defaults = [
        'multiplier_a' => '1.15',
        'retail_divisor' => '0.7',
        'round_to' => '10',
        'min_batch' => '1',
        'consign_stock_default' => '5',
    ];
    $ins = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }
    $cols = array_column($pdo->query('PRAGMA table_info(rose_varieties)')->fetchAll(), 'name');
    if (!in_array('consign_stock', $cols, true)) {
        $pdo->exec('ALTER TABLE rose_varieties ADD COLUMN consign_stock INTEGER');
    }
    $pcols = array_column($pdo->query('PRAGMA table_info(products)')->fetchAll(), 'name');
    if (!in_array('consign_open', $pcols, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN consign_open INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('origin_product_id', $pcols, true)) {
        $pdo->exec('ALTER TABLE products ADD COLUMN origin_product_id INTEGER');
    }
    garden_add_column($pdo, 'products', 'ship_month', 'ship_month TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'products', 'legacy_id', 'legacy_id INTEGER');
    garden_add_column($pdo, 'vendors', 'bank_name', 'bank_name TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'vendors', 'bank_code', 'bank_code TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'vendors', 'account_no', 'account_no TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'vendors', 'account_name', 'account_name TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'vendors', 'pay_days', 'pay_days INTEGER');
    garden_add_column($pdo, 'vendors', 'legacy_id', 'legacy_id INTEGER');
    garden_add_column($pdo, 'orders', 'buyer_name', 'buyer_name TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'orders', 'buyer_phone', 'buyer_phone TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'orders', 'buyer_address', 'buyer_address TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'orders', 'pay_method', 'pay_method TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'orders', 'pay_last5', 'pay_last5 TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'orders', 'pay_deadline', 'pay_deadline TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'orders', 'pay_status', 'pay_status TEXT NOT NULL DEFAULT \'\'');
    garden_add_column($pdo, 'orders', 'legacy_id', 'legacy_id INTEGER');
    garden_add_column($pdo, 'rose_varieties', 'legacy_id', 'legacy_id INTEGER');
    $extra = [
        'vendors' => [
            'region' => "region TEXT NOT NULL DEFAULT ''",
            'phone' => "phone TEXT NOT NULL DEFAULT ''",
            'description' => "description TEXT NOT NULL DEFAULT ''",
            'is_demo' => 'is_demo INTEGER NOT NULL DEFAULT 0',
            'shop_status' => "shop_status TEXT NOT NULL DEFAULT ''",
            'approval_status' => "approval_status TEXT NOT NULL DEFAULT ''",
            'is_operator' => 'is_operator INTEGER NOT NULL DEFAULT 0',
            'enabled' => 'enabled INTEGER NOT NULL DEFAULT 1',
            'services' => "services TEXT NOT NULL DEFAULT ''",
            'default_service' => "default_service TEXT NOT NULL DEFAULT ''",
            'cod' => 'cod INTEGER NOT NULL DEFAULT 0',
            'fees_json' => "fees_json TEXT NOT NULL DEFAULT ''",
            'fee_tiers' => "fee_tiers TEXT NOT NULL DEFAULT ''",
            'extra_item_fee' => 'extra_item_fee INTEGER NOT NULL DEFAULT 0',
            'pickup_address' => "pickup_address TEXT NOT NULL DEFAULT ''",
            'delivery_regions' => "delivery_regions TEXT NOT NULL DEFAULT ''",
            'bank_branch' => "bank_branch TEXT NOT NULL DEFAULT ''",
            'sender_name' => "sender_name TEXT NOT NULL DEFAULT ''",
            'sender_phone' => "sender_phone TEXT NOT NULL DEFAULT ''",
            'sender_address' => "sender_address TEXT NOT NULL DEFAULT ''",
            'notify_email' => "notify_email TEXT NOT NULL DEFAULT ''",
            'line_user_id' => "line_user_id TEXT NOT NULL DEFAULT ''",
            'role' => "role TEXT NOT NULL DEFAULT ''",
        ],
        'products' => [
            'legacy_status' => "legacy_status TEXT NOT NULL DEFAULT ''",
            'original_price' => 'original_price INTEGER',
            'plant_id' => 'plant_id INTEGER',
            'height_cm' => 'height_cm REAL',
            'pot_diameter' => 'pot_diameter REAL',
            'pot_height' => 'pot_height REAL',
            'package_length' => 'package_length REAL',
            'package_width' => 'package_width REAL',
            'package_height' => 'package_height REAL',
            'package_weight' => 'package_weight REAL',
            'shipping_class' => "shipping_class TEXT NOT NULL DEFAULT ''",
            'photos' => "photos TEXT NOT NULL DEFAULT '[]'",
            'services' => "services TEXT NOT NULL DEFAULT ''",
            'updated_at' => "updated_at TEXT NOT NULL DEFAULT ''",
            'hidden' => 'hidden INTEGER NOT NULL DEFAULT 0',
            'rose_legacy_id' => 'rose_legacy_id INTEGER',
            'cost_price' => 'cost_price INTEGER',
            'price_unset' => 'price_unset INTEGER NOT NULL DEFAULT 0',
            'stock_unset' => 'stock_unset INTEGER NOT NULL DEFAULT 0',
        ],
        'rose_varieties' => [
            'brand' => "brand TEXT NOT NULL DEFAULT ''",
            'subcategory' => "subcategory TEXT NOT NULL DEFAULT ''",
            'traits' => "traits TEXT NOT NULL DEFAULT ''",
            'plant_id' => 'plant_id INTEGER',
            'licensed_page' => "licensed_page TEXT NOT NULL DEFAULT ''",
            'licensed_image_url' => "licensed_image_url TEXT NOT NULL DEFAULT ''",
            'licensed_author' => "licensed_author TEXT NOT NULL DEFAULT ''",
            'licensed_license' => "licensed_license TEXT NOT NULL DEFAULT ''",
            'licensed_needs_review' => 'licensed_needs_review INTEGER NOT NULL DEFAULT 0',
            'licensed_file' => "licensed_file TEXT NOT NULL DEFAULT ''",
        ],
        'orders' => [
            'number' => "number TEXT NOT NULL DEFAULT ''",
            'parent_legacy_id' => 'parent_legacy_id INTEGER',
            'service' => "service TEXT NOT NULL DEFAULT ''",
            'quote_deadline' => 'quote_deadline INTEGER',
            'quote_date' => "quote_date TEXT NOT NULL DEFAULT ''",
            'quote_notes' => "quote_notes TEXT NOT NULL DEFAULT ''",
            'updated_at' => "updated_at TEXT NOT NULL DEFAULT ''",
            'payment_deadline_unix' => 'payment_deadline_unix INTEGER',
            'cancel_reason' => "cancel_reason TEXT NOT NULL DEFAULT ''",
            'cancel_kind' => "cancel_kind TEXT NOT NULL DEFAULT ''",
            'transfer_at' => "transfer_at TEXT NOT NULL DEFAULT ''",
            'transfer_reported_at' => "transfer_reported_at TEXT NOT NULL DEFAULT ''",
            'stock_released' => 'stock_released INTEGER NOT NULL DEFAULT 0',
            'deadline_reminded' => 'deadline_reminded INTEGER NOT NULL DEFAULT 0',
            'legacy_status' => "legacy_status TEXT NOT NULL DEFAULT ''",
        ],
        'order_lines' => [
            'legacy_id' => 'legacy_id INTEGER',
            'plant_id' => 'plant_id INTEGER',
            'height_cm' => 'height_cm REAL',
            'pot_diameter' => 'pot_diameter REAL',
            'shipping_class' => "shipping_class TEXT NOT NULL DEFAULT ''",
        ],
    ];
    foreach ($extra as $table => $cols) {
        foreach ($cols as $definition) {
            $name = strtok($definition, ' ');
            garden_add_column($pdo, $table, $name, $definition);
        }
    }
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY,
  legacy_id INTEGER,
  username TEXT NOT NULL,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL,
  vendor_id INTEGER
);
CREATE TABLE IF NOT EXISTS plant_master (
  plant_id INTEGER PRIMARY KEY,
  legacy_id INTEGER,
  name TEXT NOT NULL,
  aliases TEXT NOT NULL DEFAULT '',
  english_name TEXT NOT NULL DEFAULT '',
  scientific_name TEXT NOT NULL DEFAULT '',
  category TEXT NOT NULL,
  subcategory TEXT NOT NULL DEFAULT '',
  sunlight TEXT NOT NULL DEFAULT '',
  watering TEXT NOT NULL DEFAULT '',
  environment TEXT NOT NULL DEFAULT '',
  care TEXT NOT NULL DEFAULT '',
  mature_size TEXT NOT NULL DEFAULT '',
  flowering INTEGER,
  flower_color TEXT NOT NULL DEFAULT '',
  flower_season TEXT NOT NULL DEFAULT '',
  pot_suitable INTEGER,
  garden_suitable INTEGER,
  shipping_class TEXT NOT NULL,
  slug TEXT NOT NULL,
  tags TEXT NOT NULL DEFAULT '',
  reference_image TEXT NOT NULL DEFAULT '',
  data_status TEXT NOT NULL DEFAULT '',
  popularity_rank INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS rose_reference (
  id INTEGER PRIMARY KEY,
  legacy_id INTEGER,
  variety_id INTEGER NOT NULL,
  shop_name TEXT NOT NULL,
  page_url TEXT NOT NULL DEFAULT '',
  image_url TEXT NOT NULL DEFAULT '',
  local_file TEXT NOT NULL DEFAULT '',
  price_note TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS media_asset (
  name TEXT PRIMARY KEY,
  legacy_id TEXT,
  vendor_id INTEGER NOT NULL,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS parent_order (
  id INTEGER PRIMARY KEY,
  legacy_id INTEGER,
  number TEXT NOT NULL,
  access_hash TEXT NOT NULL,
  request_key TEXT NOT NULL,
  recipient TEXT NOT NULL,
  phone TEXT NOT NULL,
  address TEXT NOT NULL,
  store_chain TEXT NOT NULL DEFAULT '',
  store_id TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS shipment (
  id INTEGER PRIMARY KEY,
  legacy_id INTEGER,
  vendor_order_id INTEGER NOT NULL,
  carrier TEXT NOT NULL,
  tracking_number TEXT NOT NULL,
  mode TEXT NOT NULL,
  status TEXT NOT NULL,
  created_at TEXT NOT NULL,
  delivered_at TEXT
);
SQL);
}

function garden_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $cols = array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(), 'name');
    if (!in_array($column, $cols, true)) {
        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $definition);
    }
}

function consign_stock_default(): int
{
    $n = (int) setting('consign_stock_default');
    return $n > 0 ? $n : 5;
}

function consign_stock_for(array $variety): int
{
    if (array_key_exists('consign_stock', $variety) && $variety['consign_stock'] !== null && $variety['consign_stock'] !== '') {
        return max(0, (int) $variety['consign_stock']);
    }
    return consign_stock_default();
}

function apply_consign_stock(PDO $pdo, ?int $varietyId, bool $clearOverrides, bool $onlyUnset = false): void
{
    if ($clearOverrides) {
        if ($varietyId) {
            $pdo->prepare('UPDATE rose_varieties SET consign_stock = NULL WHERE id = ?')->execute([$varietyId]);
        } else {
            $pdo->exec('UPDATE rose_varieties SET consign_stock = NULL');
        }
    }
    $default = consign_stock_default();
    $sql = <<<'SQL'
UPDATE products
SET stock = COALESCE(
  (SELECT rv.consign_stock FROM rose_offers ro JOIN rose_varieties rv ON rv.id = ro.variety_id WHERE ro.id = products.rose_offer_id),
  ?
)
WHERE listing_kind = 'rose'
  AND vendor_id = 1
  AND rose_offer_id IN (
    SELECT ro.id FROM rose_offers ro
    JOIN rose_varieties rv ON rv.id = ro.variety_id
    WHERE ro.instock_at_import = 1
SQL;
    $args = [$default];
    if ($varietyId) {
        $sql .= ' AND rv.id = ?';
        $args[] = $varietyId;
    }
    if ($onlyUnset) {
        $sql .= ' AND rv.consign_stock IS NULL';
    }
    $sql .= ')';
    $pdo->prepare($sql)->execute($args);
    $note = '冬冬目錄目前可購買；頁面未公布棵數，可售棵數依平台庫存設定';
    $noteSql = "UPDATE rose_offers SET stock_note = ? WHERE instock_at_import = 1 AND supplier_sku NOT LIKE 'lot-%'";
    $noteArgs = [$note];
    if ($varietyId) {
        $noteSql .= ' AND variety_id = ?';
        $noteArgs[] = $varietyId;
    }
    $pdo->prepare($noteSql)->execute($noteArgs);
}

function consign_split(int $lineTwd): array
{
    $origin = (int) round($lineTwd * 0.7);
    if ($origin > $lineTwd) {
        $origin = $lineTwd;
    }
    if ($origin < 0) {
        $origin = 0;
    }
    return ['origin' => $origin, 'seller' => $lineTwd - $origin, 'platform' => 0];
}

function origin_product_id_of(array $product): int
{
    $oid = $product['origin_product_id'] ?? null;
    if ($oid === null || $oid === '' || (int) $oid === 0) {
        return (int) $product['id'];
    }
    return (int) $oid;
}

function product_credit(array $product): string
{
    $seller = (string) ($product['vendor_name'] ?? '');
    if (!empty($product['origin_product_id'])) {
        $grower = (string) ($product['origin_vendor_name'] ?? '');
        return $grower . '種植・' . $seller . '販售';
    }
    if ($seller === '') {
        return '';
    }
    return $seller . '種植・' . $seller . '販售';
}

function sync_consign_mirrors(PDO $pdo, int $originId): void
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$originId]);
    $origin = $stmt->fetch();
    if (!$origin || !empty($origin['origin_product_id'])) {
        return;
    }
    $open = (int) $origin['consign_open'] === 1
        && $origin['status'] === '上架'
        && $origin['stock_gate'] === 'released';
    $pdo->prepare(<<<'SQL'
UPDATE products
SET name = ?, description = ?, price_twd = ?, photo = ?, specs_json = ?, category_code = ?, stock = ?, stock_gate = ?, ship_month = ?
WHERE origin_product_id = ?
SQL)->execute([
        $origin['name'],
        $origin['description'],
        (int) $origin['price_twd'],
        $origin['photo'],
        $origin['specs_json'],
        $origin['category_code'],
        (int) $origin['stock'],
        $open ? 'released' : 'held',
        (string) ($origin['ship_month'] ?? ''),
        $originId,
    ]);
}

function consignable_products(int $sellerVendorId): array
{
    $stmt = db()->prepare(<<<'SQL'
SELECT p.*, v.name AS vendor_name,
       (SELECT m.id FROM products m WHERE m.origin_product_id = p.id AND m.vendor_id = ? LIMIT 1) AS mine_id
FROM products p
JOIN vendors v ON v.id = p.vendor_id
WHERE p.consign_open = 1
  AND (p.origin_product_id IS NULL OR p.origin_product_id = 0)
  AND p.vendor_id != ?
  AND p.status = '上架'
ORDER BY v.name, p.name
SQL);
    $stmt->execute([$sellerVendorId, $sellerVendorId]);
    return $stmt->fetchAll();
}

function vendor_consign_take(int $sellerVendorId, int $originProductId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$originProductId]);
    $origin = $stmt->fetch();
    if (!$origin || (int) $origin['consign_open'] !== 1 || !empty($origin['origin_product_id'])) {
        return ['ok' => false, 'error' => '這株沒有開放代賣。'];
    }
    if ((int) $origin['vendor_id'] === $sellerVendorId) {
        return ['ok' => false, 'error' => '這是自己的商品。'];
    }
    if ($origin['status'] !== '上架') {
        return ['ok' => false, 'error' => '原店目前沒有上架。'];
    }
    $existing = $pdo->prepare('SELECT id FROM products WHERE vendor_id = ? AND origin_product_id = ?');
    $existing->execute([$sellerVendorId, $originProductId]);
    $already = $existing->fetchColumn();
    if ($already) {
        return ['ok' => true, 'product_id' => (int) $already, 'already' => true];
    }
    $sku = 'CONSIGN-' . $sellerVendorId . '-' . $originProductId;
    $gate = $origin['stock_gate'] === 'released' ? 'released' : 'held';
    $pdo->beginTransaction();
    try {
        $pdo->prepare(<<<'SQL'
INSERT INTO products
(vendor_id, sku, name, description, price_twd, stock, status, category_code, listing_kind, rose_offer_id, stock_gate, vendor_edited, photo, specs_json, created_at, consign_open, origin_product_id)
VALUES (?,?,?,?,?,?, '上架', ?, 'consign', NULL, ?, 0, ?, ?, ?, 0, ?)
SQL)->execute([
            $sellerVendorId,
            $sku,
            $origin['name'],
            $origin['description'],
            (int) $origin['price_twd'],
            (int) $origin['stock'],
            (string) $origin['category_code'],
            $gate,
            $origin['photo'],
            $origin['specs_json'],
            gmdate('c'),
            $originProductId,
        ]);
        $pid = (int) $pdo->lastInsertId();
        $pdo->commit();
        sync_consign_mirrors($pdo, $originProductId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        $again = $pdo->prepare('SELECT id FROM products WHERE vendor_id = ? AND origin_product_id = ?');
        $again->execute([$sellerVendorId, $originProductId]);
        $found = $again->fetchColumn();
        if ($found) {
            return ['ok' => true, 'product_id' => (int) $found, 'already' => true];
        }
        return ['ok' => false, 'error' => '上架沒有完成，請再試一次。'];
    }
    return ['ok' => true, 'product_id' => $pid, 'already' => false];
}

function vendor_report(int $vendorId): array
{
    $stmt = db()->prepare(<<<'SQL'
SELECT ol.*, sv.name AS seller_name, ov.name AS origin_name, o.shipping_twd, o.vendor_id AS ship_vendor_id
FROM order_lines ol
JOIN orders o ON o.id = ol.order_id
JOIN vendors sv ON sv.id = ol.seller_vendor_id
JOIN vendors ov ON ov.id = ol.origin_vendor_id
WHERE ol.seller_vendor_id = ? OR ol.origin_vendor_id = ?
ORDER BY ol.id DESC
SQL);
    $stmt->execute([$vendorId, $vendorId]);
    $direct = [];
    $selling = [];
    $sourced = [];
    foreach ($stmt->fetchAll() as $row) {
        $seller = (int) $row['seller_vendor_id'];
        $origin = (int) $row['origin_vendor_id'];
        if ($seller === $vendorId && $origin === $vendorId) {
            $direct[] = $row;
        } elseif ($seller === $vendorId && $origin !== $vendorId) {
            $selling[] = $row;
        } elseif ($origin === $vendorId && $seller !== $vendorId) {
            $sourced[] = $row;
        }
    }
    $ship = db()->prepare('SELECT COALESCE(SUM(shipping_twd), 0) FROM orders WHERE vendor_id = ? AND shipping_twd IS NOT NULL');
    $ship->execute([$vendorId]);
    return [
        'direct' => $direct,
        'selling' => $selling,
        'sourced' => $sourced,
        'shipping_twd' => (int) $ship->fetchColumn(),
    ];
}

function setting(string $key): string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? '' : (string) $v;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_admin(): array
{
    $u = current_user();
    if (!$u || ($u['role'] ?? '') !== 'admin') {
        redirect('/login');
    }
    return $u;
}

function require_vendor(): array
{
    $u = current_user();
    if (!$u || ($u['role'] ?? '') !== 'vendor') {
        redirect('/login');
    }
    return $u;
}

function attempt_login(string $login, string $password): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE login = ?');
    $stmt->execute([$login]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['user'] = ['role' => 'admin', 'id' => (int) $admin['id'], 'name' => $admin['name']];
        return true;
    }
    $stmt = $pdo->prepare('SELECT * FROM vendors WHERE login = ?');
    $stmt->execute([$login]);
    $vendor = $stmt->fetch();
    if ($vendor && password_verify($password, $vendor['password_hash'])) {
        $_SESSION['user'] = [
            'role' => 'vendor',
            'id' => (int) $vendor['id'],
            'name' => $vendor['name'],
            'vendor_id' => (int) $vendor['id'],
        ];
        return true;
    }
    return false;
}

function offer_prices(array $offer, array $variety): array
{
    $cost = $offer['cost_twd'];
    if ($cost === null || $cost === '') {
        return ['cost' => null, 'vendor' => null, 'retail' => null];
    }
    $cost = (int) $cost;
    $a = $variety['multiplier_a'] !== null && $variety['multiplier_a'] !== ''
        ? (float) $variety['multiplier_a']
        : (float) setting('multiplier_a');
    $div = $variety['retail_divisor'] !== null && $variety['retail_divisor'] !== ''
        ? (float) $variety['retail_divisor']
        : (float) setting('retail_divisor');
    if ($div <= 0) {
        $div = 0.7;
    }
    return [
        'cost' => $cost,
        'vendor' => round10($cost * $a),
        'retail' => round10($cost / $div),
    ];
}

function public_specs(array $variety, array $offer): array
{
    $heat = $variety['heat_tw'];
    if ($heat === PENDING && ($variety['heat_flag'] ?? '') === '1') {
        $heat = '標示耐熱（未分級）';
    }
    $class = $variety['class_main'];
    $classLabel = $class === PENDING ? PENDING : category_name($class);
    if ($class === 'OGR' && ($variety['ogr_class'] ?? PENDING) !== PENDING) {
        $classLabel .= '・' . $variety['ogr_class'];
    }
    return [
        '株型' => $classLabel,
        '花色' => $variety['color_group'],
        '花色描述' => $variety['color_desc'],
        '香味' => $variety['fragrance'],
        '香型' => $variety['scent_type'],
        '花期' => trim(($variety['bloom_habit'] !== PENDING ? $variety['bloom_habit'] : '') . (($variety['bloom_months'] ?? PENDING) !== PENDING ? ' ' . $variety['bloom_months'] . '月' : '')) ?: PENDING,
        '耐熱' => $heat,
        '苗型' => $offer['seedling'] !== '' ? $offer['seedling'] : ($variety['seedling_types'] ?: PENDING),
        '花徑' => $variety['flower_dia_cm'] === PENDING ? PENDING : $variety['flower_dia_cm'] . ' 公分',
        '花朵大小' => $variety['flower_size'],
        '花瓣數' => $variety['petals'],
        '重瓣度' => $variety['doubleness'],
        '花型' => $variety['form'],
        '開法' => $variety['inflorescence'],
        '株高' => $variety['height_cm'] === PENDING ? PENDING : $variety['height_cm'] . ' 公分',
        '株寬' => $variety['width_cm'] === PENDING ? PENDING : $variety['width_cm'] . ' 公分',
        '育種者' => $variety['breeder'],
        '國家' => $variety['breeder_country'],
        '年份' => $variety['year'],
        '登錄名' => $variety['reg_name'],
        '黑斑病' => $variety['disease_bs'],
        '白粉病' => $variety['disease_pm'],
    ];
}

function visible_public_specs(array $variety, array $offer): array
{
    $out = [];
    foreach (public_specs($variety, $offer) as $label => $value) {
        $value = trim((string) $value);
        if (is_blank_value($value)) {
            continue;
        }
        $out[$label] = $value;
    }
    return $out;
}

function vendor_editable_products(int $vendorId): array
{
    $stmt = db()->prepare(<<<'SQL'
SELECT * FROM products
WHERE vendor_id = ?
  AND (
    status = '新苗預購'
    OR id IN (SELECT id FROM products WHERE vendor_id = ? ORDER BY id DESC LIMIT 40)
  )
ORDER BY CASE WHEN status = '新苗預購' THEN 0 ELSE 1 END, id DESC
SQL);
    $stmt->execute([$vendorId, $vendorId]);
    return $stmt->fetchAll();
}

function catalog(array $filters): array
{
    $sql = <<<'SQL'
SELECT p.*, v.name AS vendor_name, ov.name AS origin_vendor_name,
       rv.class_main, rv.color_group, rv.fragrance, rv.flower_dia_cm,
       rv.bloom_habit, rv.bloom_months, rv.heat_tw, rv.heat_flag, rv.breeder, rv.name_zh, rv.variety_key
FROM products p
JOIN vendors v ON v.id = p.vendor_id
LEFT JOIN products op ON op.id = p.origin_product_id
LEFT JOIN vendors ov ON ov.id = op.vendor_id
LEFT JOIN rose_offers ro ON ro.id = p.rose_offer_id
LEFT JOIN rose_varieties rv ON rv.id = ro.variety_id OR (ro.id IS NULL AND rv.legacy_id = p.rose_legacy_id)
WHERE p.status IN ('上架', '新苗預購') AND p.stock_gate = 'released' AND p.stock > 0
  AND COALESCE(v.enabled, 1) = 1 AND COALESCE(v.shop_status, '') != 'hidden' AND COALESCE(p.hidden, 0) = 0
SQL;
    $args = [];
    if (($filters['class'] ?? '') !== '') {
        $sql .= ' AND rv.class_main = ?';
        $args[] = $filters['class'];
    }
    if (($filters['color'] ?? '') !== '') {
        $sql .= ' AND rv.color_group = ?';
        $args[] = $filters['color'];
    }
    if (($filters['fragrance'] ?? '') !== '') {
        $sql .= ' AND rv.fragrance = ?';
        $args[] = $filters['fragrance'];
    }
    if (($filters['bloom'] ?? '') !== '') {
        $sql .= ' AND rv.bloom_habit = ?';
        $args[] = $filters['bloom'];
    }
    if (($filters['heat'] ?? '') === '1') {
        $sql .= " AND (rv.heat_flag = '1' OR (rv.heat_tw != '待補' AND rv.heat_tw != '待確認' AND rv.heat_tw != '未填' AND CAST(rv.heat_tw AS INTEGER) >= 4))";
    }
    if (($filters['vendor'] ?? '') !== '') {
        $sql .= ' AND v.name = ?';
        $args[] = $filters['vendor'];
    }
    if (($filters['roses_only'] ?? '') === '1') {
        $sql .= " AND p.listing_kind = 'rose'";
    }
    $sql .= ' ORDER BY p.id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function product_by_id(int $id): ?array
{
    $stmt = db()->prepare(<<<'SQL'
SELECT p.id, p.vendor_id, p.sku, p.name, p.description, p.price_twd, p.stock, p.status,
       p.category_code, p.listing_kind, p.rose_offer_id, p.stock_gate, p.vendor_edited,
       p.photo, p.specs_json, p.ship_month, p.created_at, p.consign_open, p.origin_product_id,
       p.rose_legacy_id, p.hidden, p.legacy_status,
       v.name AS vendor_name, v.enabled AS vendor_enabled, v.shop_status AS vendor_shop_status, ov.name AS origin_vendor_name,
       ro.seedling AS offer_seedling, ro.variety_id, ro.platform_stock, ro.offer_state, ro.photo AS offer_photo,
       rv.name_zh, rv.name_orig, rv.aliases, rv.reg_name, rv.class_main, rv.class_intl, rv.ogr_class,
       rv.series, rv.breeder, rv.breeder_country, rv.year, rv.color_group, rv.color_desc, rv.fragrance,
       rv.scent_type, rv.flower_dia_cm, rv.flower_size, rv.petals, rv.doubleness, rv.form, rv.inflorescence,
       rv.bloom_habit, rv.bloom_months, rv.height_cm, rv.width_cm, rv.growth_habit, rv.disease_bs, rv.disease_pm,
       rv.heat_tw, rv.heat_flag, rv.shade, rv.usda_zone, rv.uses, rv.thorns, rv.awards, rv.pvr, rv.seedling_types,
       rv.description AS variety_description, rv.listed
FROM products p
JOIN vendors v ON v.id = p.vendor_id
LEFT JOIN products op ON op.id = p.origin_product_id
LEFT JOIN vendors ov ON ov.id = op.vendor_id
LEFT JOIN rose_offers ro ON ro.id = p.rose_offer_id
LEFT JOIN rose_varieties rv ON rv.id = ro.variety_id OR (ro.id IS NULL AND rv.legacy_id = p.rose_legacy_id)
WHERE p.id = ?
SQL);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if (empty($row['photo']) && !empty($row['offer_photo'])) {
        $row['photo'] = $row['offer_photo'];
    }
    return $row;
}

function cart_items(): array
{
    return $_SESSION['cart'] ?? [];
}

function shared_stock_left(array $product, int $exceptProductId): int
{
    $originId = origin_product_id_of($product);
    $origin = $originId === (int) $product['id'] ? $product : product_by_id($originId);
    $stock = $origin ? (int) $origin['stock'] : 0;
    foreach (cart_items() as $pid => $qty) {
        if ((int) $pid === $exceptProductId) {
            continue;
        }
        $other = product_by_id((int) $pid);
        if ($other && origin_product_id_of($other) === $originId) {
            $stock -= (int) $qty;
        }
    }
    return $stock;
}

function cart_add(int $productId, int $qty): ?string
{
    $p = product_by_id($productId);
    if (!$p || !in_array($p['status'], ['上架', '新苗預購'], true) || $p['stock_gate'] !== 'released' || (int) ($p['vendor_enabled'] ?? 1) !== 1 || ($p['vendor_shop_status'] ?? '') === 'hidden' || (int) ($p['hidden'] ?? 0) === 1) {
        return '這株目前不能購買。';
    }
    $qty = max(1, $qty);
    $cart = cart_items();
    $have = (int) ($cart[$productId] ?? 0);
    $left = shared_stock_left($p, $productId);
    if ($have + $qty > $left) {
        return '庫存只剩 ' . max(0, $left) . ' 棵。';
    }
    $cart[$productId] = $have + $qty;
    $_SESSION['cart'] = $cart;
    return null;
}

function cart_set(int $productId, int $qty): void
{
    $cart = cart_items();
    if ($qty <= 0) {
        unset($cart[$productId]);
        $_SESSION['cart'] = $cart;
        return;
    }
    $p = product_by_id($productId);
    if ($p) {
        $left = shared_stock_left($p, $productId);
        $cart[$productId] = min($qty, max(0, $left));
    }
    $_SESSION['cart'] = $cart;
}

function is_excluded(int $vendorId, array $product): bool
{
    $stmt = db()->prepare(<<<'SQL'
SELECT 1 FROM shipping_exclusions
WHERE vendor_id = ?
  AND (
    (scope = 'product' AND product_id = ?)
    OR (scope = 'category' AND category_code = ? AND category_code != '')
  )
LIMIT 1
SQL);
    $stmt->execute([$vendorId, (int) $product['id'], (string) ($product['category_code'] ?? '')]);
    return (bool) $stmt->fetchColumn();
}

function cart_groups(): array
{
    $groups = [];
    foreach (cart_items() as $pid => $qty) {
        $p = product_by_id((int) $pid);
        if (!$p) {
            continue;
        }
        $originId = origin_product_id_of($p);
        $origin = $originId === (int) $p['id'] ? $p : product_by_id($originId);
        if (!$origin) {
            $origin = $p;
        }
        $vid = (int) $origin['vendor_id'];
        if (!isset($groups[$vid])) {
            $v = db()->prepare('SELECT * FROM vendors WHERE id = ?');
            $v->execute([$vid]);
            $groups[$vid] = ['vendor' => $v->fetch(), 'lines' => []];
        }
        $p['qty'] = (int) $qty;
        $p['excluded'] = is_excluded($vid, $origin);
        $p['line_total'] = (int) $p['price_twd'] * (int) $qty;
        $groups[$vid]['lines'][] = $p;
    }
    foreach ($groups as $vid => $group) {
        $groups[$vid]['quote'] = shipping_quote($group['vendor'], $group['lines']);
    }
    return $groups;
}

function shipping_quote(array $vendor, array $lines): array
{
    $eligible = 0;
    $excludedTotal = 0;
    $excluded = [];
    $goods = 0;
    foreach ($lines as $line) {
        $goods += (int) $line['line_total'];
        if (!empty($line['excluded'])) {
            $excludedTotal += (int) $line['line_total'];
            $excluded[] = $line;
        } else {
            $eligible += (int) $line['line_total'];
        }
    }
    $fee = $vendor['shipping_fee_twd'];
    $threshold = $vendor['free_shipping_min_twd'];
    $fee = $fee === null || $fee === '' ? null : (int) $fee;
    $threshold = $threshold === null || $threshold === '' ? null : (int) $threshold;
    $free = false;
    $need = null;
    $charge = $fee;
    if ($fee !== null && $threshold !== null && $threshold > 0) {
        if ($eligible >= $threshold) {
            $free = true;
            $charge = 0;
        } else {
            $need = $threshold - $eligible;
        }
    }
    return [
        'goods' => $goods,
        'eligible' => $eligible,
        'excluded_total' => $excludedTotal,
        'excluded' => $excluded,
        'fee_set' => $fee,
        'threshold' => $threshold,
        'charge' => $charge,
        'free' => $free,
        'need' => $need,
    ];
}

function is_preorder(array $product): bool
{
    return ($product['status'] ?? '') === '新苗預購';
}

function preorder_spec(): string
{
    return '3.5 吋扦插苗';
}

function public_product_name(array $product): string
{
    $name = trim((string) ($product['name'] ?? ''));
    if (is_preorder($product)) {
        $name = preg_replace('/[（(][^）)]*(?:小盆|中盆|大盆)[^）)]*[）)]/u', '', $name) ?? $name;
        $name = str_replace(['小盆', '中盆', '大盆'], '', $name);
        $name = trim(preg_replace('/\s{2,}/u', ' ', $name) ?? $name);
    }
    return $name;
}

function ship_month_text(array $product): string
{
    if (!is_preorder($product)) {
        return '';
    }
    $month = trim((string) ($product['ship_month'] ?? ''));
    return '預計出貨 ' . public_value($month);
}

function card_facts(array $product): array
{
    $facts = [];
    $class = (string) ($product['class_main'] ?? '');
    if (!is_blank_value($class)) {
        $facts['株型'] = category_name($class);
    }
    if (!is_blank_value($product['color_group'] ?? null)) {
        $facts['花色'] = trim((string) $product['color_group']);
    }
    if (!is_blank_value($product['flower_dia_cm'] ?? null)) {
        $facts['花徑'] = trim((string) $product['flower_dia_cm']) . ' 公分';
    }
    if (!is_blank_value($product['fragrance'] ?? null)) {
        $facts['香味'] = trim((string) $product['fragrance']);
    }
    return $facts;
}

function filter_values(string $column): array
{
    $allowed = ['class_main' => 'class_main', 'color_group' => 'color_group', 'fragrance' => 'fragrance', 'bloom_habit' => 'bloom_habit'];
    if (!isset($allowed[$column])) {
        return [];
    }
    $col = $allowed[$column];
    $rows = db()->query('SELECT DISTINCT ' . $col . ' AS v FROM rose_varieties ORDER BY v')->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        if (!is_blank_value($row['v'] ?? null)) {
            $out[] = (string) $row['v'];
        }
    }
    return $out;
}

function featured_roses(int $limit = 8): array
{
    $out = [];
    foreach (catalog(['roses_only' => '1']) as $product) {
        $out[] = $product;
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

function vendor_by_name(string $name): ?array
{
    $stmt = db()->prepare("SELECT * FROM vendors WHERE name = ? AND COALESCE(enabled, 1) = 1 AND COALESCE(shop_status, '') != 'hidden'");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function rose_by_slug(string $slug): ?array
{
    $slug = rawurldecode($slug);
    $stmt = db()->prepare('SELECT * FROM rose_varieties WHERE variety_key = ? OR name_zh = ?');
    $stmt->execute([$slug, $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function rose_slug(array $variety): string
{
    $key = trim((string) ($variety['variety_key'] ?? ''));
    if ($key === '') {
        $key = trim((string) ($variety['name_zh'] ?? ''));
    }
    return rawurlencode($key);
}

function rose_products(int $varietyId): array
{
    $stmt = db()->prepare(<<<'SQL'
SELECT p.*, v.name AS vendor_name, ov.name AS origin_vendor_name
FROM products p
JOIN rose_offers ro ON ro.id = p.rose_offer_id
JOIN vendors v ON v.id = p.vendor_id
LEFT JOIN products op ON op.id = p.origin_product_id
LEFT JOIN vendors ov ON ov.id = op.vendor_id
WHERE ro.variety_id = ? AND p.status = '上架' AND p.stock_gate = 'released' AND p.stock > 0
ORDER BY p.price_twd
SQL);
    $stmt->execute([$varietyId]);
    return $stmt->fetchAll();
}

function pay_deadline_text(?int $days): string
{
    if ($days === null || $days <= 0) {
        return '';
    }
    $when = new DateTimeImmutable('now', new DateTimeZone('Asia/Taipei'));
    return $when->modify('+' . $days . ' days')->format('Y-m-d');
}

function order_bundle(int $orderId): ?array
{
    $stmt = db()->prepare(<<<'SQL'
SELECT o.*, v.name AS vendor_name, v.bank_name, v.bank_code, v.account_no, v.account_name, v.pay_days
FROM orders o
JOIN vendors v ON v.id = o.vendor_id
WHERE o.id = ?
SQL);
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $lines = db()->prepare('SELECT l.*, p.status AS product_status, p.ship_month FROM order_lines l LEFT JOIN products p ON p.id = l.product_id WHERE l.order_id = ? ORDER BY l.id');
    $lines->execute([$orderId]);
    $order['lines'] = $lines->fetchAll();
    return $order;
}

function place_orders(array $buyer = []): array
{
    $pdo = db();
    $ids = [];
    $pdo->beginTransaction();
    try {
        foreach (cart_groups() as $group) {
            $quote = $group['quote'];
            $ship = $quote['charge'];
            $total = $quote['goods'] + (int) ($ship ?? 0);
            $vendorId = (int) $group['vendor']['id'];
            $last5 = preg_replace('/\D+/', '', (string) ($buyer['last5'][$vendorId] ?? $buyer['last5'] ?? '')) ?? '';
            if (strlen($last5) > 5) {
                $last5 = substr($last5, -5);
            }
            if (strlen($last5) !== 5) {
                $last5 = '';
            }
            $days = $group['vendor']['pay_days'];
            $days = $days === null || $days === '' ? null : (int) $days;
            $deadline = pay_deadline_text($days);
            $status = $last5 === '' ? '待填末五碼' : '待店家確認';
            $stmt = $pdo->prepare(<<<'SQL'
INSERT INTO orders
(vendor_id, total_twd, shipping_twd, eligible_twd, created_at, buyer_name, buyer_phone, buyer_address, pay_method, pay_last5, pay_deadline, pay_status)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
SQL);
            $stmt->execute([
                $vendorId,
                $total,
                $ship,
                $quote['eligible'],
                gmdate('c'),
                trim((string) ($buyer['name'] ?? '')),
                trim((string) ($buyer['phone'] ?? '')),
                trim((string) ($buyer['address'] ?? '')),
                'bank',
                $last5,
                $deadline,
                $status,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $ids[] = $orderId;
            $lineIns = $pdo->prepare(<<<'SQL'
INSERT INTO order_lines
(order_id, product_id, origin_product_id, seller_vendor_id, origin_vendor_id, name, qty, price_twd, line_twd, origin_share_twd, seller_share_twd, platform_share_twd, created_at)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
SQL);
            $need = [];
            foreach ($group['lines'] as $line) {
                $originId = origin_product_id_of($line);
                $isMirror = $originId !== (int) $line['id'];
                $lineTwd = (int) $line['line_total'];
                $split = $isMirror ? consign_split($lineTwd) : ['origin' => $lineTwd, 'seller' => 0, 'platform' => 0];
                $lineIns->execute([
                    $orderId,
                    (int) $line['id'],
                    $originId,
                    (int) $line['vendor_id'],
                    (int) $group['vendor']['id'],
                    (string) $line['name'],
                    (int) $line['qty'],
                    (int) $line['price_twd'],
                    $lineTwd,
                    $split['origin'],
                    $split['seller'],
                    0,
                    gmdate('c'),
                ]);
                $need[$originId] = ($need[$originId] ?? 0) + (int) $line['qty'];
            }
            $upd = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND (origin_product_id IS NULL OR origin_product_id = 0) AND stock >= ?');
            foreach ($need as $originId => $qty) {
                $upd->execute([$qty, $originId, $qty]);
                if ($upd->rowCount() !== 1) {
                    throw new RuntimeException('庫存不足');
                }
                sync_consign_mirrors($pdo, (int) $originId);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $_SESSION['cart'] = [];
    return $ids;
}

function vendor_may_take(array $variety, array $offer, int $qty): ?string
{
    if ((int) $variety['listed'] !== 1) {
        return '這株還沒開放，先不要上架。';
    }
    $state = (string) $offer['offer_state'];
    if ($state === '停止') {
        return '這株已停止供應。';
    }
    if ($state === '完售') {
        return '這株已經完售。';
    }
    $min = max(1, (int) setting('min_batch'));
    if ($qty < $min) {
        return '最少要 ' . $min . ' 棵。';
    }
    if ((int) $offer['platform_stock'] < $qty) {
        return '平台庫存不夠，現在只剩 ' . (int) $offer['platform_stock'] . ' 棵。';
    }
    if ($state === '扦插預購' && (int) $offer['platform_stock'] <= 0 && (int) $offer['auto_preorder'] !== 1) {
        return '預購還沒開放。';
    }
    return null;
}

function vendor_take(int $vendorId, int $offerId, int $qty): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<'SQL'
SELECT ro.id AS offer_id, ro.supplier_sku, ro.list_name, ro.seedling, ro.cost_twd, ro.platform_stock,
       ro.offer_state, ro.auto_preorder, ro.eta, ro.photo, ro.variety_id,
       rv.name_zh, rv.class_main, rv.description, rv.listed, rv.multiplier_a, rv.retail_divisor,
       rv.color_group, rv.color_desc, rv.fragrance, rv.scent_type, rv.bloom_habit, rv.bloom_months,
       rv.heat_tw, rv.heat_flag, rv.flower_dia_cm, rv.flower_size, rv.petals, rv.doubleness, rv.form,
       rv.inflorescence, rv.height_cm, rv.width_cm, rv.breeder, rv.breeder_country, rv.year, rv.reg_name,
       rv.disease_bs, rv.disease_pm, rv.ogr_class, rv.seedling_types
FROM rose_offers ro
JOIN rose_varieties rv ON rv.id = ro.variety_id
WHERE ro.id = ?
SQL);
    $stmt->execute([$offerId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => '找不到這株苗。'];
    }
    $variety = $row;
    $offer = $row;
    $offerId = (int) $row['offer_id'];
    $block = vendor_may_take($variety, $offer, $qty);
    if ($block) {
        return ['ok' => false, 'error' => $block];
    }
    $prices = offer_prices($offer, $variety);
    $pdo->beginTransaction();
    try {
        $existing = $pdo->prepare('SELECT * FROM products WHERE vendor_id = ? AND rose_offer_id = ?');
        $existing->execute([$vendorId, $offerId]);
        $product = $existing->fetch();
        $specs = public_specs($variety, $offer);
        $photo = (string) $offer['photo'];
        $desc = redact((string) $variety['description']);
        $name = $variety['name_zh'];
        if ($offer['seedling'] !== '' && $offer['seedling'] !== PENDING && $offer['seedling'] !== '自根') {
            $name .= '（' . $offer['seedling'] . '）';
        }
        if ($product) {
            if ((int) $product['vendor_edited'] === 1) {
                $name = $product['name'];
                $desc = $product['description'];
            }
            $price = (int) $product['price_twd'] > 0 ? (int) $product['price_twd'] : (int) ($prices['retail'] ?? 0);
            $upd = $pdo->prepare('UPDATE products SET stock = stock + ?, photo = ?, specs_json = ?, price_twd = ? WHERE id = ?');
            $upd->execute([$qty, $photo !== '' ? $photo : $product['photo'], json_encode($specs, JSON_UNESCAPED_UNICODE), $price, (int) $product['id']]);
            $pid = (int) $product['id'];
        } else {
            $sku = 'ROSE-' . $vendorId . '-' . $offer['supplier_sku'];
            $ins = $pdo->prepare(<<<'SQL'
INSERT INTO products
(vendor_id, sku, name, description, price_twd, stock, status, category_code, listing_kind, rose_offer_id, stock_gate, vendor_edited, photo, specs_json, created_at)
VALUES (?,?,?,?,?,?, '草稿', ?, 'rose', ?, 'platform_pending', 0, ?, ?, ?)
SQL);
            $ins->execute([
                $vendorId,
                $sku,
                $name,
                $desc,
                (int) ($prices['retail'] ?? 0),
                $qty,
                (string) $variety['class_main'],
                $offerId,
                $photo,
                json_encode($specs, JSON_UNESCAPED_UNICODE),
                gmdate('c'),
            ]);
            $pid = (int) $pdo->lastInsertId();
        }
        $stock = $pdo->prepare('UPDATE rose_offers SET platform_stock = platform_stock - ? WHERE id = ? AND platform_stock >= ?');
        $stock->execute([$qty, $offerId, $qty]);
        if ($stock->rowCount() !== 1) {
            throw new RuntimeException('庫存不夠');
        }
        $left = $pdo->prepare('SELECT platform_stock, auto_preorder, offer_state FROM rose_offers WHERE id = ?');
        $left->execute([$offerId]);
        $now = $left->fetch();
        if ($now && (int) $now['platform_stock'] === 0 && (int) $now['auto_preorder'] === 1 && $now['offer_state'] === '有貨') {
            $pdo->prepare("UPDATE rose_offers SET offer_state = '扦插預購' WHERE id = ?")->execute([$offerId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => '上架沒有完成，請再試一次。'];
    }
    return ['ok' => true, 'product_id' => $pid];
}

function render(string $view, array $data = []): void
{
    $data['user'] = current_user();
    $data['cartCount'] = array_sum(cart_items());
    extract($data, EXTR_SKIP);
    ob_start();
    include garden_root() . '/views/' . $view . '.php';
    $body = ob_get_clean();
    include garden_root() . '/views/layout.php';
}

function field_columns(): array
{
    return [
        'reg_name', 'class_main', 'class_intl', 'ogr_class', 'series', 'breeder', 'breeder_country', 'year',
        'color_group', 'color_desc', 'fragrance', 'scent_type', 'flower_dia_cm', 'flower_size', 'petals',
        'doubleness', 'form', 'inflorescence', 'bloom_habit', 'bloom_months', 'height_cm', 'width_cm',
        'growth_habit', 'disease_bs', 'disease_pm', 'heat_tw', 'heat_flag', 'shade', 'usda_zone', 'uses',
        'thorns', 'awards', 'pvr', 'seedling_types',
    ];
}
