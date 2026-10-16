<h1>運費</h1>
<p class="lede">自己填。平台不會先幫你填運費或免運門檻。也可以指定哪些商品、哪個分類不計入免運。</p>
<?php if (!empty($saved)): ?><p class="note">已儲存。</p><?php endif; ?>
<form method="post" action="/vendor/shipping" class="card pad">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <label class="f">運費（元）
    <input type="number" name="shipping_fee_twd" min="0" placeholder="例如 200" value="<?= h((string) ($vendor['shipping_fee_twd'] ?? '')) ?>">
  </label>
  <label class="f">免運門檻（元，計入的商品滿這個金額才免運）
    <input type="number" name="free_shipping_min_twd" min="0" placeholder="例如 2000" value="<?= h((string) ($vendor['free_shipping_min_twd'] ?? '')) ?>">
  </label>
  <h2>不計入免運的分類</h2>
  <?php foreach (categories() as $cat): ?>
    <label class="f"><input type="checkbox" name="exclude_cat[]" value="<?= h($cat['code']) ?>" <?= in_array($cat['code'], $excludedCats, true) ? 'checked' : '' ?>> <?= h($cat['name']) ?></label>
  <?php endforeach; ?>
  <p><button class="btn big block" type="submit">儲存運費</button></p>
</form>
