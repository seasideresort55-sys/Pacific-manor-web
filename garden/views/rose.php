<?php
$v = $variety;
$facts = [
    '中文名' => public_value($v['name_zh'] ?? ''),
    '英文名' => public_value($v['name_orig'] ?? ''),
    '育種者' => public_value($v['breeder'] ?? ''),
    '株型' => is_blank_value($v['class_main'] ?? '') ? PENDING : category_name((string) $v['class_main']),
    '花色' => public_value($v['color_group'] ?? ''),
    '花徑' => is_blank_value($v['flower_dia_cm'] ?? '') ? PENDING : trim((string) $v['flower_dia_cm']) . ' 公分',
    '香味' => public_value($v['fragrance'] ?? ''),
    '花期' => public_value(trim(($v['bloom_habit'] ?? '') . ((($v['bloom_months'] ?? '') !== '' && !is_blank_value($v['bloom_months'] ?? '')) ? ' ' . $v['bloom_months'] . '月' : ''))),
    '耐熱' => public_value(($v['heat_tw'] ?? '') !== '' && !is_blank_value($v['heat_tw'] ?? '') ? $v['heat_tw'] : ((($v['heat_flag'] ?? '') === '1') ? '標示耐熱（未分級）' : '')),
    '台灣種植經驗' => PENDING,
];
$faqs = [
    ['q' => '這株的花色是什麼？', 'a' => $facts['花色']],
    ['q' => '花徑大概幾公分？', 'a' => $facts['花徑']],
    ['q' => '在台灣怎麼種？', 'a' => $facts['台灣種植經驗']],
];
$offer = $offers[0] ?? null;
$canonical = 'https://' . garden_config()['host'] . url('/rose/' . rose_slug($v));
$productLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $facts['中文名'],
    'description' => $facts['株型'] . '，花色 ' . $facts['花色'],
    'brand' => ['@type' => 'Brand', 'name' => $facts['育種者']],
    'url' => $canonical,
];
if ($offer) {
    $productLd['offers'] = [
        '@type' => 'Offer',
        'priceCurrency' => 'TWD',
        'price' => (string) (int) $offer['price_twd'],
        'availability' => 'https://schema.org/InStock',
        'url' => 'https://' . garden_config()['host'] . url('/product/' . (int) $offer['id']),
    ];
}
$faqLd = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static function (array $faq): array {
        return [
            '@type' => 'Question',
            'name' => $faq['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
        ];
    }, $faqs),
];
?>
<?php
$headExtra = '<link rel="canonical" href="' . h($canonical) . '">'
    . '<script type="application/ld+json">' . json_encode($productLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>'
    . '<script type="application/ld+json">' . json_encode($faqLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
?>
<h1><?= h($facts['中文名']) ?></h1>
<p class="lede"><?= h($facts['英文名']) ?></p>
<table class="specs">
  <?php foreach ($facts as $label => $value): if ($label === '中文名') continue; ?>
    <tr><th><?= h($label) ?></th><td><?= h($value) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php if ($offers): ?>
  <h2>現在可買</h2>
  <div class="grid">
    <?php foreach ($offers as $p): include __DIR__ . '/card.php'; endforeach; ?>
  </div>
<?php else: ?>
  <p class="note">這一株目前沒有上架的苗。</p>
<?php endif; ?>
<h2>常見問答</h2>
<?php foreach ($faqs as $faq): ?>
  <h3><?= h($faq['q']) ?></h3>
  <p><?= h($faq['a']) ?></p>
<?php endforeach; ?>
