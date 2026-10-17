<?php
$specs = [];
if (!empty($product['rose_offer_id'])) {
    $specs = visible_public_specs($product, ['seedling' => (string) ($product['offer_seedling'] ?? '')]);
}
$photo = trim((string) ($product['photo'] ?? ''));
$categoryLabel = public_category_label((string) ($product['category_code'] ?? ''));
?>
<div class="layout-2">
  <div class="card product-shot">
    <?php if ($photo !== ''): ?>
      <img src="<?= h(media_url($photo)) ?>" alt="<?= h(public_product_name($product)) ?>">
    <?php else: ?>
      <div class="photo"><?php include __DIR__ . '/icon.php'; ?></div>
    <?php endif; ?>
  </div>
  <div>
    <h1><?= h(public_product_name($product)) ?></h1>
    <p class="lede"><?= h(product_credit($product)) ?></p>
    <?php if ($categoryLabel !== ''): ?><p class="meta"><?= h($categoryLabel) ?></p><?php endif; ?>
    <?php $blurb = category_blurb((string) ($product['class_main'] ?? '')); ?>
    <?php if ($blurb): ?><p class="note"><?= h($blurb) ?></p><?php endif; ?>
    <?php if (is_preorder($product)): ?>
      <p class="meta"><?= h(preorder_spec()) ?> · <?= h(ship_month_text($product)) ?></p>
    <?php endif; ?>
    <?php $facts = card_facts($product); if ($facts): ?>
      <p class="meta"><?php
        $bits = [];
        foreach ($facts as $label => $value) { $bits[] = $label . ' ' . $value; }
        echo h(implode(' · ', $bits));
      ?></p>
    <?php endif; ?>
    <p class="price"><?= money((int) $product['price_twd']) ?></p>
    <p class="meta">庫存 <?= (int) $product['stock'] ?> 棵</p>
    <?php if (in_array(($product['status'] ?? ''), ['上架', '新苗預購'], true) && ($product['stock_gate'] ?? '') === 'released' && (int) $product['stock'] > 0): ?>
      <form method="post" action="<?= h(url('/cart/add')) ?>">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="return" value="<?= h(url('/product/' . (int) $product['id'])) ?>">
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <label class="f">數量
          <input class="qty" type="number" name="qty" min="1" max="<?= (int) $product['stock'] ?>" value="1">
        </label>
        <p><button class="btn" type="submit">放入購物車</button></p>
      </form>
    <?php else: ?>
      <p class="warn">目前不能購買。</p>
    <?php endif; ?>
    <?php if (!empty($product['description'])): ?>
      <p><?= nl2br(h(redact((string) $product['description']))) ?></p>
    <?php endif; ?>
  </div>
</div>
<?php if ($specs): ?>
  <h2>品種資料</h2>
  <table class="specs">
    <?php foreach ($specs as $label => $value): ?>
      <tr>
        <th><?= h($label) ?></th>
        <td><?= h((string) $value) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
