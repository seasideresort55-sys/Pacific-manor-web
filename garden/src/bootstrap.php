<?php
declare(strict_types=1);

const GARDEN_MODE = 'mock';
const PENDING = '待補';

function garden_root(): string
{
    return dirname(__DIR__);
}

function db_path(): string
{
    $env = getenv('GARDEN_DB');
    return $env !== false && $env !== '' ? $env : garden_root() . '/data/garden.sqlite';
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
    header('Location: ' . $to);
    exit;
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
  password_hash TEXT NOT NULL,
  shipping_fee_twd INTEGER,
  free_shipping_min_twd INTEGER,
  created_at TEXT NOT NULL
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
  listing_kind TEXT NOT NULL,
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
  updated_at TEXT NOT NULL
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
    ];
    $ins = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }
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
        $heat = '賣家標示耐熱（未分級）';
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

function catalog(array $filters): array
{
    $sql = <<<'SQL'
SELECT p.*, v.name AS vendor_name, rv.class_main, rv.color_group, rv.fragrance,
       rv.bloom_habit, rv.bloom_months, rv.heat_tw, rv.heat_flag, rv.breeder, rv.name_zh
FROM products p
JOIN vendors v ON v.id = p.vendor_id
LEFT JOIN rose_offers ro ON ro.id = p.rose_offer_id
LEFT JOIN rose_varieties rv ON rv.id = ro.variety_id
WHERE p.status = '上架' AND p.stock_gate = 'released' AND p.stock > 0
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
        $sql .= " AND (rv.heat_flag = '1' OR (rv.heat_tw != '待補' AND CAST(rv.heat_tw AS INTEGER) >= 4))";
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
       p.photo, p.specs_json, p.created_at,
       v.name AS vendor_name,
       ro.seedling AS offer_seedling, ro.variety_id, ro.platform_stock, ro.offer_state, ro.photo AS offer_photo,
       rv.name_zh, rv.name_orig, rv.aliases, rv.reg_name, rv.class_main, rv.class_intl, rv.ogr_class,
       rv.series, rv.breeder, rv.breeder_country, rv.year, rv.color_group, rv.color_desc, rv.fragrance,
       rv.scent_type, rv.flower_dia_cm, rv.flower_size, rv.petals, rv.doubleness, rv.form, rv.inflorescence,
       rv.bloom_habit, rv.bloom_months, rv.height_cm, rv.width_cm, rv.growth_habit, rv.disease_bs, rv.disease_pm,
       rv.heat_tw, rv.heat_flag, rv.shade, rv.usda_zone, rv.uses, rv.thorns, rv.awards, rv.pvr, rv.seedling_types,
       rv.description AS variety_description, rv.listed
FROM products p
JOIN vendors v ON v.id = p.vendor_id
LEFT JOIN rose_offers ro ON ro.id = p.rose_offer_id
LEFT JOIN rose_varieties rv ON rv.id = ro.variety_id
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

function cart_add(int $productId, int $qty): ?string
{
    $p = product_by_id($productId);
    if (!$p || $p['status'] !== '上架' || $p['stock_gate'] !== 'released') {
        return '這株目前不能購買。';
    }
    $qty = max(1, $qty);
    $cart = cart_items();
    $have = (int) ($cart[$productId] ?? 0);
    if ($have + $qty > (int) $p['stock']) {
        return '庫存只剩 ' . (int) $p['stock'] . ' 棵。';
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
    } else {
        $p = product_by_id($productId);
        if ($p) {
            $cart[$productId] = min($qty, (int) $p['stock']);
        }
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
        $vid = (int) $p['vendor_id'];
        if (!isset($groups[$vid])) {
            $v = db()->prepare('SELECT * FROM vendors WHERE id = ?');
            $v->execute([$vid]);
            $groups[$vid] = ['vendor' => $v->fetch(), 'lines' => []];
        }
        $p['qty'] = (int) $qty;
        $p['excluded'] = is_excluded($vid, $p);
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

function place_orders(): array
{
    $pdo = db();
    $ids = [];
    $pdo->beginTransaction();
    try {
        foreach (cart_groups() as $group) {
            $quote = $group['quote'];
            $ship = $quote['charge'];
            $total = $quote['goods'] + (int) ($ship ?? 0);
            $stmt = $pdo->prepare('INSERT INTO orders (vendor_id, total_twd, shipping_twd, eligible_twd, created_at) VALUES (?,?,?,?,?)');
            $stmt->execute([
                (int) $group['vendor']['id'],
                $total,
                $ship,
                $quote['eligible'],
                gmdate('c'),
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $ids[] = $orderId;
            $upd = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($group['lines'] as $line) {
                $upd->execute([(int) $line['qty'], (int) $line['id'], (int) $line['qty']]);
                if ($upd->rowCount() !== 1) {
                    throw new RuntimeException('庫存不足');
                }
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
