<?php
declare(strict_types=1);
session_start();
require dirname(__DIR__) . '/src/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/') {
    $products = catalog([]);
    $products = array_slice($products, 0, 8);
    render('home', ['title' => '首頁', 'products' => $products]);
    exit;
}

if ($path === '/roses') {
    $filters = [
        'class' => (string) ($_GET['class'] ?? ''),
        'color' => (string) ($_GET['color'] ?? ''),
        'fragrance' => (string) ($_GET['fragrance'] ?? ''),
        'bloom' => (string) ($_GET['bloom'] ?? ''),
        'heat' => (string) ($_GET['heat'] ?? ''),
    ];
    render('shop', [
        'title' => '玫瑰',
        'products' => catalog($filters),
        'filters' => $filters,
        'blurb' => category_blurb($filters['class']),
    ]);
    exit;
}

if (preg_match('#^/product/(\d+)$#', $path, $m)) {
    $product = product_by_id((int) $m[1]);
    if (!$product || ($product['status'] !== '上架' && ($current = current_user()) && ($current['role'] ?? '') !== 'admin' && (int) ($current['vendor_id'] ?? 0) !== (int) $product['vendor_id'])) {
        if (!$product || $product['status'] !== '上架') {
            http_response_code(404);
            render('home', ['title' => '找不到', 'products' => []]);
            exit;
        }
    }
    if ($product['status'] !== '上架' && !current_user()) {
        http_response_code(404);
        exit('找不到這個商品。');
    }
    render('product', ['title' => $product['name'], 'product' => $product]);
    exit;
}

if ($path === '/cart' && $method === 'GET') {
    render('cart', ['title' => '購物車', 'groups' => cart_groups()]);
    exit;
}
if ($path === '/cart/add' && $method === 'POST') {
    csrf_check();
    $err = cart_add((int) ($_POST['product_id'] ?? 0), (int) ($_POST['qty'] ?? 1));
    if ($err) {
        $_SESSION['flash'] = $err;
    }
    redirect('/cart');
}
if ($path === '/cart/update' && $method === 'POST') {
    csrf_check();
    cart_set((int) ($_POST['product_id'] ?? 0), (int) ($_POST['qty'] ?? 0));
    redirect('/cart');
}
if ($path === '/checkout' && $method === 'GET') {
    $groups = cart_groups();
    if (!$groups) {
        redirect('/cart');
    }
    render('checkout', ['title' => '結帳', 'groups' => $groups]);
    exit;
}
if ($path === '/checkout' && $method === 'POST') {
    csrf_check();
    try {
        $ids = place_orders();
    } catch (Throwable $e) {
        $_SESSION['flash'] = '訂單沒有送出，請再看一次庫存。';
        redirect('/cart');
    }
    render('done', ['title' => '訂單已送出', 'ids' => $ids]);
    exit;
}

if ($path === '/login' && $method === 'GET') {
    render('login', ['title' => '登入', 'error' => $_SESSION['login_error'] ?? '']);
    unset($_SESSION['login_error']);
    exit;
}
if ($path === '/login' && $method === 'POST') {
    csrf_check();
    if (attempt_login(trim((string) ($_POST['login'] ?? '')), (string) ($_POST['password'] ?? ''))) {
        $u = current_user();
        redirect($u['role'] === 'admin' ? '/admin/roses' : '/vendor');
    }
    $_SESSION['login_error'] = '帳號或密碼不對。';
    redirect('/login');
}
if ($path === '/logout' && $method === 'POST') {
    csrf_check();
    $_SESSION = [];
    redirect('/');
}

if ($path === '/admin/roses' && $method === 'GET') {
    require_admin();
    $q = trim((string) ($_GET['q'] ?? ''));
    $listed = (string) ($_GET['listed'] ?? '');
    $sql = <<<'SQL'
SELECT ro.id AS offer_id, ro.supplier_sku, ro.seedling, ro.cost_twd, ro.platform_stock, ro.offer_state,
       rv.name_zh, rv.class_main, rv.listed
FROM rose_offers ro
JOIN rose_varieties rv ON rv.id = ro.variety_id
WHERE 1=1
SQL;
    $args = [];
    if ($q !== '') {
        $sql .= ' AND (rv.name_zh LIKE ? OR rv.name_orig LIKE ? OR rv.breeder LIKE ?)';
        $args[] = '%' . $q . '%';
        $args[] = '%' . $q . '%';
        $args[] = '%' . $q . '%';
    }
    if ($listed === '1' || $listed === '0') {
        $sql .= ' AND rv.listed = ?';
        $args[] = (int) $listed;
    }
    $sql .= ' ORDER BY rv.listed DESC, ro.instock_at_import DESC, rv.name_zh LIMIT 80';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $counts = [
        'varieties' => (int) db()->query('SELECT COUNT(*) FROM rose_varieties')->fetchColumn(),
        'listed' => (int) db()->query('SELECT COUNT(*) FROM rose_varieties WHERE listed = 1')->fetchColumn(),
        'offers' => (int) db()->query('SELECT COUNT(*) FROM rose_offers')->fetchColumn(),
    ];
    render('admin_roses', [
        'title' => '玫瑰庫',
        'rows' => $stmt->fetchAll(),
        'q' => $q,
        'listed' => $listed,
        'counts' => $counts,
        'settings' => [
            'multiplier_a' => setting('multiplier_a'),
            'retail_divisor' => setting('retail_divisor'),
            'consign_stock_default' => setting('consign_stock_default'),
        ],
    ]);
    exit;
}
if ($path === '/admin/settings' && $method === 'POST') {
    require_admin();
    csrf_check();
    $a = (string) ($_POST['multiplier_a'] ?? '');
    $d = (string) ($_POST['retail_divisor'] ?? '');
    if (is_numeric($a) && (float) $a > 0) {
        db()->prepare('UPDATE settings SET value = ? WHERE key = ?')->execute([$a, 'multiplier_a']);
    }
    if (is_numeric($d) && (float) $d > 0) {
        db()->prepare('UPDATE settings SET value = ? WHERE key = ?')->execute([$d, 'retail_divisor']);
    }
    redirect('/admin/roses');
}
if ($path === '/admin/consign-stock' && $method === 'POST') {
    require_admin();
    csrf_check();
    $n = (int) ($_POST['consign_stock_default'] ?? 0);
    if ($n > 0) {
        db()->prepare('UPDATE settings SET value = ? WHERE key = ?')->execute([(string) $n, 'consign_stock_default']);
    }
    $apply = (string) ($_POST['apply'] ?? '');
    if ($apply === 'missing') {
        apply_consign_stock(db(), null, false, true);
    } elseif ($apply === 'all') {
        apply_consign_stock(db(), null, true, false);
    }
    redirect('/admin/roses');
}
if (preg_match('#^/admin/rose/(\d+)$#', $path, $m) && $method === 'GET') {
    require_admin();
    $stmt = db()->prepare(<<<'SQL'
SELECT ro.id AS offer_id, ro.*, rv.*
FROM rose_offers ro JOIN rose_varieties rv ON rv.id = ro.variety_id
WHERE ro.id = ?
SQL);
    $stmt->execute([(int) $m[1]]);
    $offer = $stmt->fetch();
    if (!$offer) {
        redirect('/admin/roses');
    }
    $offer['offer_id'] = (int) $m[1];
    render('admin_rose', ['title' => $offer['name_zh'], 'offer' => $offer]);
    exit;
}
if (preg_match('#^/admin/rose/(\d+)$#', $path, $m) && $method === 'POST') {
    require_admin();
    csrf_check();
    $id = (int) $m[1];
    $pdo = db();
    $offer = $pdo->prepare('SELECT variety_id FROM rose_offers WHERE id = ?');
    $offer->execute([$id]);
    $varietyId = (int) $offer->fetchColumn();
    if ($varietyId) {
        $consign = trim((string) ($_POST['consign_stock'] ?? ''));
        $pdo->prepare('UPDATE rose_varieties SET listed = ?, multiplier_a = ?, retail_divisor = ?, consign_stock = ?, updated_at = ? WHERE id = ?')
            ->execute([
                isset($_POST['listed']) ? 1 : 0,
                ($_POST['multiplier_a'] ?? '') === '' ? null : (float) $_POST['multiplier_a'],
                ($_POST['retail_divisor'] ?? '') === '' ? null : (float) $_POST['retail_divisor'],
                $consign === '' ? null : max(0, (int) $consign),
                gmdate('c'),
                $varietyId,
            ]);
        apply_consign_stock($pdo, $varietyId, false);
        $state = (string) ($_POST['offer_state'] ?? '有貨');
        if (!in_array($state, ['有貨', '扦插預購', '完售', '停止'], true)) {
            $state = '有貨';
        }
        $stock = max(0, (int) ($_POST['platform_stock'] ?? 0));
        if ($stock === 0 && isset($_POST['auto_preorder']) && $state === '有貨') {
            $state = '扦插預購';
        }
        $pdo->prepare('UPDATE rose_offers SET offer_state = ?, platform_stock = ?, cost_twd = ?, eta = ?, auto_preorder = ? WHERE id = ?')
            ->execute([
                $state,
                $stock,
                ($_POST['cost_twd'] ?? '') === '' ? null : (int) $_POST['cost_twd'],
                trim((string) ($_POST['eta'] ?? '')),
                isset($_POST['auto_preorder']) ? 1 : 0,
                $id,
            ]);
    }
    redirect('/admin/rose/' . $id);
}

if ($path === '/vendor' && $method === 'GET') {
    $u = require_vendor();
    $v = db()->prepare('SELECT * FROM vendors WHERE id = ?');
    $v->execute([(int) $u['vendor_id']]);
    render('vendor_home', ['title' => '店家', 'vendor' => $v->fetch()]);
    exit;
}
if ($path === '/vendor/pick' && $method === 'GET') {
    require_vendor();
    $class = (string) ($_GET['class'] ?? '');
    $color = (string) ($_GET['color'] ?? '');
    $sql = <<<'SQL'
SELECT ro.id AS offer_id, ro.seedling, ro.cost_twd, ro.platform_stock, ro.offer_state, ro.photo, ro.auto_preorder,
       rv.name_zh, rv.class_main, rv.color_group, rv.fragrance, rv.listed, rv.multiplier_a, rv.retail_divisor, rv.description
FROM rose_offers ro
JOIN rose_varieties rv ON rv.id = ro.variety_id
WHERE rv.listed = 1
SQL;
    $args = [];
    if ($class !== '') {
        $sql .= ' AND rv.class_main = ?';
        $args[] = $class;
    }
    if ($color !== '') {
        $sql .= ' AND rv.color_group = ?';
        $args[] = $color;
    }
    $sql .= ' ORDER BY ro.platform_stock DESC, rv.name_zh LIMIT 60';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    render('vendor_pick', [
        'title' => '選苗上架',
        'offers' => $stmt->fetchAll(),
        'class' => $class,
        'color' => $color,
        'message' => $_SESSION['pick_ok'] ?? '',
        'error' => $_SESSION['pick_err'] ?? '',
    ]);
    unset($_SESSION['pick_ok'], $_SESSION['pick_err']);
    exit;
}
if ($path === '/vendor/pick' && $method === 'POST') {
    $u = require_vendor();
    csrf_check();
    $res = vendor_take((int) $u['vendor_id'], (int) ($_POST['offer_id'] ?? 0), max(1, (int) ($_POST['qty'] ?? 1)));
    if ($res['ok']) {
        $_SESSION['pick_ok'] = '已建成草稿。平台放行之後才會出現在前台。';
    } else {
        $_SESSION['pick_err'] = $res['error'];
    }
    redirect('/vendor/pick');
}
if ($path === '/vendor/products' && $method === 'GET') {
    $u = require_vendor();
    $stmt = db()->prepare('SELECT * FROM products WHERE vendor_id = ? ORDER BY id DESC LIMIT 40');
    $stmt->execute([(int) $u['vendor_id']]);
    $products = $stmt->fetchAll();
    foreach ($products as &$p) {
        $p['excluded'] = is_excluded((int) $u['vendor_id'], $p);
    }
    render('vendor_products', ['title' => '我的商品', 'products' => $products]);
    exit;
}
if ($path === '/vendor/products' && $method === 'POST') {
    $u = require_vendor();
    csrf_check();
    $pid = (int) ($_POST['product_id'] ?? 0);
    $own = db()->prepare('SELECT * FROM products WHERE id = ? AND vendor_id = ?');
    $own->execute([$pid, (int) $u['vendor_id']]);
    $p = $own->fetch();
    if ($p && empty($p['origin_product_id'])) {
        db()->prepare('UPDATE products SET name = ?, description = ?, price_twd = ?, vendor_edited = 1, consign_open = ? WHERE id = ?')
            ->execute([
                trim((string) ($_POST['name'] ?? $p['name'])),
                redact(trim((string) ($_POST['description'] ?? ''))),
                max(0, (int) ($_POST['price_twd'] ?? 0)),
                isset($_POST['consign_open']) ? 1 : 0,
                $pid,
            ]);
        sync_consign_mirrors(db(), $pid);
        if (isset($_POST['exclude_ship'])) {
            db()->prepare('INSERT OR IGNORE INTO shipping_exclusions (vendor_id, scope, product_id, category_code, created_at) VALUES (?,?,?,?,?)')
                ->execute([(int) $u['vendor_id'], 'product', $pid, '', gmdate('c')]);
        } else {
            db()->prepare("DELETE FROM shipping_exclusions WHERE vendor_id = ? AND scope = 'product' AND product_id = ?")
                ->execute([(int) $u['vendor_id'], $pid]);
        }
    }
    redirect('/vendor/products');
}
if ($path === '/vendor/consign' && $method === 'GET') {
    $u = require_vendor();
    render('vendor_consign', [
        'title' => '可代賣商品',
        'products' => consignable_products((int) $u['vendor_id']),
        'message' => $_SESSION['consign_ok'] ?? '',
        'error' => $_SESSION['consign_err'] ?? '',
    ]);
    unset($_SESSION['consign_ok'], $_SESSION['consign_err']);
    exit;
}
if ($path === '/vendor/consign' && $method === 'POST') {
    $u = require_vendor();
    csrf_check();
    $res = vendor_consign_take((int) $u['vendor_id'], (int) ($_POST['origin_product_id'] ?? 0));
    if ($res['ok'] && !empty($res['already'])) {
        $_SESSION['consign_ok'] = '這株已經在你的店裡。';
    } elseif ($res['ok']) {
        $_SESSION['consign_ok'] = '已上架，前台現在就買得到。';
    } else {
        $_SESSION['consign_err'] = $res['error'];
    }
    redirect('/vendor/consign');
}
if ($path === '/vendor/report' && $method === 'GET') {
    $u = require_vendor();
    render('vendor_report', [
        'title' => '銷售分潤',
        'report' => vendor_report((int) $u['vendor_id']),
    ]);
    exit;
}
if ($path === '/vendor/shipping' && $method === 'GET') {
    $u = require_vendor();
    $v = db()->prepare('SELECT * FROM vendors WHERE id = ?');
    $v->execute([(int) $u['vendor_id']]);
    $cats = db()->prepare("SELECT category_code FROM shipping_exclusions WHERE vendor_id = ? AND scope = 'category'");
    $cats->execute([(int) $u['vendor_id']]);
    render('vendor_shipping', [
        'title' => '運費',
        'vendor' => $v->fetch(),
        'excludedCats' => array_column($cats->fetchAll(), 'category_code'),
        'saved' => isset($_GET['saved']),
    ]);
    exit;
}
if ($path === '/vendor/shipping' && $method === 'POST') {
    $u = require_vendor();
    csrf_check();
    $fee = ($_POST['shipping_fee_twd'] ?? '') === '' ? null : max(0, (int) $_POST['shipping_fee_twd']);
    $min = ($_POST['free_shipping_min_twd'] ?? '') === '' ? null : max(0, (int) $_POST['free_shipping_min_twd']);
    db()->prepare('UPDATE vendors SET shipping_fee_twd = ?, free_shipping_min_twd = ? WHERE id = ?')
        ->execute([$fee, $min, (int) $u['vendor_id']]);
    db()->prepare("DELETE FROM shipping_exclusions WHERE vendor_id = ? AND scope = 'category'")
        ->execute([(int) $u['vendor_id']]);
    $ins = db()->prepare('INSERT INTO shipping_exclusions (vendor_id, scope, product_id, category_code, created_at) VALUES (?,?,?,?,?)');
    foreach ((array) ($_POST['exclude_cat'] ?? []) as $code) {
        $code = (string) $code;
        if (category_name($code) === $code && $code !== 'HT') {
            continue;
        }
        $known = false;
        foreach (categories() as $cat) {
            if ($cat['code'] === $code) {
                $known = true;
            }
        }
        if ($known) {
            $ins->execute([(int) $u['vendor_id'], 'category', null, $code, gmdate('c')]);
        }
    }
    redirect('/vendor/shipping?saved=1');
}

http_response_code(404);
echo '找不到這個頁面。';
