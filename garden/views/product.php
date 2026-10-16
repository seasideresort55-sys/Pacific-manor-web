<?php
$specs = json_decode((string) ($product['specs_json'] ?? '{}'), true) ?: [];
if (!$specs && !empty($product['class_main'])) {
    $specs = public_specs($product, ['seedling' => $product['offer_seedling'] ?? PENDING]);
}
$photo = $product['photo'] ?? '';
?>
<div class="layout-2">
  <div class="card">
    <div class="photo" style="aspect-ratio:4/3;<?= $photo ? "background-image:url('/" . h(ltrim($photo, '/')) . "')" : '' ?>">
      <?php if (!$photo) { include __DIR__ . '/icon.php'; } ?>
    </div>
  </div>
  <div>
    <h1><?= h($product['name']) ?></h1>
    <p class="lede"><?= h($product['vendor_name']) ?> · <?= h(category_name((string) $product['category_code'])) ?></p>
    <?php $blurb = category_blurb((string) ($product['class_main'] ?? '')); ?>
    <?php if ($blurb): ?><p class="note"><?= h($blurb) ?></p><?php endif; ?>
    <p class="price"><?= money((int) $product['price_twd']) ?></p>
    <p class="meta">庫存 <?= (int) $product['stock'] ?> 棵</p>
    <?php if (($product['status'] ?? '') === '上架' && (int) $product['stock'] > 0): ?>
      <form method="post" action="/cart/add">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
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
    <?php foreach ($specs as $label => $value): if ($label === '花色描述' && ($value === PENDING || $value === '')) continue; ?>
      <tr>
        <th><?= h($label) ?></th>
        <td><?= h((string) ($value === '' ? PENDING : $value)) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
