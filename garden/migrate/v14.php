<?php
declare(strict_types=1);

/**
 * v14 migration: backup garden.sqlite, then only add or update.
 * Does not delete rows. Does not change 新苗預購 listings.
 * Does not change other vendors' products. Does not prefill their shipping.
 */

if (!function_exists('garden_migrate_v14')) {
    function garden_migrate_v14(): void
    {
        $path = db_path();
        if (is_file($path)) {
            $bak = $path . '.bak-' . gmdate('YmdHis');
            if (!copy($path, $bak)) {
                throw new RuntimeException('backup failed');
            }
            fwrite(STDOUT, "backup $bak\n");
        }
        $pdo = db();
        ensure_schema($pdo);
        $jsonPath = garden_root() . '/data/rose-library.json';
        if (!is_file($jsonPath)) {
            throw new RuntimeException('missing data/rose-library.json');
        }
        $lib = json_decode((string) file_get_contents($jsonPath), true);
        if (!is_array($lib) || !isset($lib['varieties'])) {
            throw new RuntimeException('rose library json is not readable');
        }
        $now = gmdate('c');
        $pdo->beginTransaction();
        $insertedV = 0;
        $updatedV = 0;
        $offers = 0;
        $consigned = 0;
        foreach ($lib['varieties'] as $var) {
            $key = (string) $var['key'];
            $fields = $var['fields'] ?? [];
            $find = $pdo->prepare('SELECT * FROM rose_varieties WHERE variety_key = ?');
            $find->execute([$key]);
            $existing = $find->fetch();
            $values = variety_values($var, $fields);
            $hasInstock = false;
            foreach ($var['offers'] ?? [] as $offer) {
                if (!empty($offer['instock'])) {
                    $hasInstock = true;
                }
            }
            if (!$existing) {
                $cols = array_keys($values);
                $sql = 'INSERT INTO rose_varieties (' . implode(',', $cols) . ', listed, updated_at) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ', ?, ?)';
                $pdo->prepare($sql)->execute([...array_values($values), $hasInstock ? 1 : 0, $now]);
                $varietyId = (int) $pdo->lastInsertId();
                $insertedV++;
            } else {
                $varietyId = (int) $existing['id'];
                $sets = [];
                $args = [];
                foreach ($values as $col => $val) {
                    if ($col === 'sources_json') {
                        continue;
                    }
                    $old = (string) ($existing[$col] ?? '');
                    if (($old === '' || $old === PENDING) && $val !== PENDING && $val !== '') {
                        $sets[] = $col . ' = ?';
                        $args[] = $val;
                    }
                }
                $newSources = (string) ($values['sources_json'] ?? '{}');
                if ($newSources !== '' && $newSources !== (string) ($existing['sources_json'] ?? '')) {
                    $sets[] = 'sources_json = ?';
                    $args[] = $newSources;
                }
                foreach (['breeder', 'year', 'flower_size', 'doubleness'] as $col) {
                    $new = (string) ($values[$col] ?? '');
                    $old = (string) ($existing[$col] ?? '');
                    if ($new === '' || $new === PENDING || $new === $old || $old === '' || $old === PENDING) {
                        continue;
                    }
                    $ok = false;
                    if ($col === 'breeder') {
                        $a = function_exists('mb_strtolower') ? mb_strtolower($old) : strtolower($old);
                        $b = function_exists('mb_strtolower') ? mb_strtolower($new) : strtolower($new);
                        $ok = str_contains($b, $a) || str_contains($a, $b);
                    } elseif ($col === 'year' && preg_match('/^(19|20)\d{2}$/', $old) && preg_match('/^(19|20)\d{2}$/', $new)) {
                        $ok = abs((int) $old - (int) $new) <= 2;
                    } elseif ($col === 'flower_size' || $col === 'doubleness') {
                        $ok = true;
                    }
                    if ($ok) {
                        $sets[] = $col . ' = ?';
                        $args[] = $new;
                    }
                }
                if ($hasInstock && (int) $existing['listed'] === 0) {
                    $sets[] = 'listed = 1';
                }
                if ($sets) {
                    $sets[] = 'updated_at = ?';
                    $args[] = $now;
                    $args[] = $varietyId;
                    $pdo->prepare('UPDATE rose_varieties SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
                    $updatedV++;
                }
            }
            foreach ($var['offers'] ?? [] as $offer) {
                $sku = (string) $offer['supplier_sku'];
                $have = $pdo->prepare('SELECT * FROM rose_offers WHERE supplier_sku = ?');
                $have->execute([$sku]);
                $row = $have->fetch();
                if (!$row) {
                    $pdo->prepare(<<<'SQL'
INSERT INTO rose_offers
(variety_id, supplier_sku, list_name, seedling, cost_twd, platform_stock, offer_state, stock_note, photo, menu_label, instock_at_import)
VALUES (?,?,?,?,?,?,?,?,?,?,?)
SQL)->execute([
                        $varietyId,
                        $sku,
                        (string) ($offer['list_name'] ?? $var['name_zh']),
                        (string) ($offer['seedling'] ?? PENDING),
                        $offer['cost'] ?? null,
                        (int) ($offer['stock'] ?? 0),
                        '有貨',
                        (string) ($offer['stock_note'] ?? ''),
                        (string) ($offer['photo'] ?? ''),
                        (string) ($offer['menu'] ?? ''),
                        1,
                    ]);
                    $offerId = (int) $pdo->lastInsertId();
                    $offers++;
                    $row = [
                        'id' => $offerId,
                        'consigned_product_id' => null,
                        'platform_stock' => (int) ($offer['stock'] ?? 0),
                        'cost_twd' => $offer['cost'] ?? null,
                        'seedling' => (string) ($offer['seedling'] ?? PENDING),
                        'photo' => (string) ($offer['photo'] ?? ''),
                        'supplier_sku' => $sku,
                    ];
                } else {
                    $offerId = (int) $row['id'];
                    if (($row['photo'] ?? '') === '' && !empty($offer['photo'])) {
                        $pdo->prepare('UPDATE rose_offers SET photo = ? WHERE id = ?')->execute([$offer['photo'], $offerId]);
                        $row['photo'] = $offer['photo'];
                    }
                    if (($row['cost_twd'] === null || $row['cost_twd'] === '') && isset($offer['cost'])) {
                        $pdo->prepare('UPDATE rose_offers SET cost_twd = ? WHERE id = ?')->execute([(int) $offer['cost'], $offerId]);
                        $row['cost_twd'] = (int) $offer['cost'];
                    }
                }
                if (empty($row['consigned_product_id'])) {
                    $consigned += consign_offer($pdo, $varietyId, $row, $values, $now) ? 1 : 0;
                }
            }
        }
        add_platform_lots($pdo, $now);
        $pdo->prepare('UPDATE vendors SET shipping_fee_twd = 200, free_shipping_min_twd = 2000 WHERE id = 1')
            ->execute();
        sync_existing_consign_stock($pdo);
        refresh_consigned_specs($pdo);
        $pdo->commit();
        $pre = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = '新苗預購'")->fetchColumn();
        fwrite(STDOUT, "varieties_inserted $insertedV\nvarieties_updated $updatedV\noffers_inserted $offers\nconsigned $consigned\npreorder_kept $pre\n");
    }

    function variety_values(array $var, array $fields): array
    {
        $get = static function (string $key) use ($fields): string {
            $v = $fields[$key]['v'] ?? PENDING;
            $v = trim((string) $v);
            return $v === '' ? PENDING : $v;
        };
        $sources = [];
        foreach ($fields as $key => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            if (($meta['v'] ?? PENDING) !== PENDING && ($meta['src'] ?? '') !== '') {
                $sources[$key] = ['src' => $meta['src'], 'url' => $meta['url'] ?? ''];
            }
        }
        return [
            'variety_key' => (string) $var['key'],
            'name_zh' => (string) $var['name_zh'],
            'name_orig' => (string) ($var['name_orig'] ?: PENDING),
            'aliases' => (string) ($var['aliases'] ?: PENDING),
            'reg_name' => $get('reg_name'),
            'class_main' => $get('class_main'),
            'class_intl' => $get('class_intl'),
            'ogr_class' => $get('ogr_class'),
            'series' => $get('series'),
            'breeder' => $get('breeder'),
            'breeder_country' => $get('breeder_country'),
            'year' => $get('year'),
            'color_group' => $get('color_group'),
            'color_desc' => $get('color_desc'),
            'fragrance' => $get('fragrance'),
            'scent_type' => $get('scent_type'),
            'flower_dia_cm' => $get('flower_dia_cm'),
            'flower_size' => $get('flower_size'),
            'petals' => $get('petals'),
            'doubleness' => $get('doubleness'),
            'form' => $get('form'),
            'inflorescence' => $get('inflorescence'),
            'bloom_habit' => $get('bloom_habit'),
            'bloom_months' => $get('bloom_months'),
            'height_cm' => $get('height_cm'),
            'width_cm' => $get('width_cm'),
            'growth_habit' => $get('growth_habit'),
            'disease_bs' => $get('disease_bs'),
            'disease_pm' => $get('disease_pm'),
            'heat_tw' => $get('heat_tw'),
            'heat_flag' => $get('heat_flag'),
            'shade' => $get('shade'),
            'usda_zone' => $get('usda_zone'),
            'uses' => $get('uses'),
            'thorns' => $get('thorns'),
            'awards' => $get('awards'),
            'pvr' => $get('pvr'),
            'seedling_types' => $get('seedling_types'),
            'description' => redact((string) ($var['description'] ?? '')),
            'sources_json' => json_encode($sources, JSON_UNESCAPED_UNICODE),
        ];
    }

    function consign_offer(PDO $pdo, int $varietyId, array $offer, array $values, string $now): bool
    {
        $vendorId = 1;
        $sku = 'ROSE-' . $vendorId . '-' . $offer['supplier_sku'];
        $have = $pdo->prepare('SELECT id FROM products WHERE sku = ?');
        $have->execute([$sku]);
        $existingId = $have->fetchColumn();
        if ($existingId) {
            $pdo->prepare('UPDATE rose_offers SET consigned_product_id = ? WHERE id = ? AND consigned_product_id IS NULL')
                ->execute([(int) $existingId, (int) $offer['id']]);
            return false;
        }
        $variety = $pdo->prepare('SELECT * FROM rose_varieties WHERE id = ?');
        $variety->execute([$varietyId]);
        $variety = $variety->fetch();
        if (!$variety) {
            return false;
        }
        $cost = $offer['cost_twd'];
        if ($cost === null || $cost === '') {
            return false;
        }
        $div = (float) (setting('retail_divisor') ?: 0.7);
        $price = round10(((int) $cost) / $div);
        $placeholder = (int) $offer['platform_stock'];
        if ($placeholder < 1) {
            return false;
        }
        $qty = consign_stock_for($variety);
        $name = $variety['name_zh'];
        $seedling = (string) $offer['seedling'];
        if ($seedling !== '' && $seedling !== PENDING && $seedling !== '自根') {
            $name .= '（' . $seedling . '）';
        }
        $specs = public_specs($variety, ['seedling' => $seedling]);
        $pdo->prepare(<<<'SQL'
INSERT INTO products
(vendor_id, sku, name, description, price_twd, stock, status, category_code, listing_kind, rose_offer_id, stock_gate, vendor_edited, photo, specs_json, created_at)
VALUES (?,?,?,?,?,?, '上架', ?, 'rose', ?, 'released', 0, ?, ?, ?)
SQL)->execute([
            $vendorId,
            $sku,
            $name,
            (string) $variety['description'],
            $price,
            $qty,
            (string) $variety['class_main'],
            (int) $offer['id'],
            (string) ($offer['photo'] ?? ''),
            json_encode($specs, JSON_UNESCAPED_UNICODE),
            $now,
        ]);
        $pid = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE rose_offers SET platform_stock = platform_stock - ?, consigned_product_id = ?, stock_note = ? WHERE id = ? AND platform_stock >= ?')
            ->execute([
                $placeholder,
                $pid,
                '冬冬目錄目前可購買；頁面未公布棵數，可售棵數依平台庫存設定',
                (int) $offer['id'],
                $placeholder,
            ]);
        return true;
    }

    function sync_existing_consign_stock(PDO $pdo): void
    {
        $default = consign_stock_default();
        $note = '冬冬目錄目前可購買；頁面未公布棵數，可售棵數依平台庫存設定';
        $pdo->prepare(<<<'SQL'
UPDATE products
SET stock = ?
WHERE listing_kind = 'rose'
  AND vendor_id = 1
  AND stock = 1
  AND rose_offer_id IN (
    SELECT id FROM rose_offers WHERE stock_note LIKE '%平台庫存記 1%'
  )
SQL)->execute([$default]);
        $pdo->prepare("UPDATE rose_offers SET stock_note = ? WHERE stock_note LIKE '%平台庫存記 1%'")
            ->execute([$note]);
    }

    function refresh_consigned_specs(PDO $pdo): void
    {
        $rows = $pdo->query(<<<'SQL'
SELECT ro.seedling, ro.photo AS offer_photo, rv.*, p.id AS product_id
FROM products p
JOIN rose_offers ro ON ro.id = p.rose_offer_id
JOIN rose_varieties rv ON rv.id = ro.variety_id
WHERE p.listing_kind = 'rose' AND p.vendor_edited = 0
SQL)->fetchAll();
        $upd = $pdo->prepare("UPDATE products SET specs_json = ?, photo = CASE WHEN photo = '' AND ? <> '' THEN ? ELSE photo END WHERE id = ?");
        foreach ($rows as $row) {
            $specs = public_specs($row, ['seedling' => $row['seedling']]);
            $photo = (string) ($row['offer_photo'] ?? '');
            $upd->execute([json_encode($specs, JSON_UNESCAPED_UNICODE), $photo, $photo, (int) $row['product_id']]);
        }
    }

    function add_platform_lots(PDO $pdo, string $now): void
    {
        $already = (int) $pdo->query("SELECT COUNT(*) FROM rose_offers WHERE supplier_sku LIKE 'lot-%'")->fetchColumn();
        if ($already >= 8) {
            return;
        }
        $listPath = garden_root() . '/data/dd-list.json';
        if (!is_file($listPath)) {
            return;
        }
        $list = json_decode((string) file_get_contents($listPath), true);
        if (!is_array($list)) {
            return;
        }
        $made = $already;
        foreach ($list as $name => $rec) {
            if ($made >= 8) {
                break;
            }
            if (!is_array($rec) || !isset($rec[2])) {
                continue;
            }
            $key = preg_replace('/\s+/u', '', (string) preg_replace('/[（(](?:大接|接)[)）]/u', '', (string) $name));
            $v = $pdo->prepare('SELECT * FROM rose_varieties WHERE variety_key = ?');
            $v->execute([$key]);
            $variety = $v->fetch();
            if (!$variety) {
                continue;
            }
            $sku = 'lot-' . substr(sha1($key), 0, 10);
            $have = $pdo->prepare('SELECT id FROM rose_offers WHERE supplier_sku = ?');
            $have->execute([$sku]);
            if ($have->fetchColumn()) {
                continue;
            }
            $busy = $pdo->prepare('SELECT COUNT(*) FROM rose_offers WHERE variety_id = ? AND instock_at_import = 1');
            $busy->execute([(int) $variety['id']]);
            if ((int) $busy->fetchColumn() > 0) {
                continue;
            }
            $photo = '';
            $remote = (string) ($rec[1] ?? '');
            if ($remote !== '') {
                $url = 'https://www.dongdongs.com/' . ltrim($remote, '/');
                $bin = @file_get_contents($url);
                if (is_string($bin) && strlen($bin) > 400) {
                    $file = garden_root() . '/public/media/roses/' . $sku . '.jpg';
                    file_put_contents($file, $bin);
                    $photo = 'media/roses/' . $sku . '.jpg';
                }
            }
            $pdo->prepare('UPDATE rose_varieties SET listed = 1, updated_at = ? WHERE id = ?')->execute([$now, (int) $variety['id']]);
            $pdo->prepare(<<<'SQL'
INSERT INTO rose_offers
(variety_id, supplier_sku, list_name, seedling, cost_twd, platform_stock, offer_state, stock_note, photo, menu_label, instock_at_import)
VALUES (?,?,?,?,?,3,'有貨',?,?, '', 0)
SQL)->execute([
                (int) $variety['id'],
                $sku,
                (string) $name,
                '自根',
                (int) $rec[2],
                '平台自有庫存，不是供應商公布的棵數',
                $photo,
            ]);
            $made++;
        }
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require dirname(__DIR__) . '/src/bootstrap.php';
    garden_migrate_v14();
}
