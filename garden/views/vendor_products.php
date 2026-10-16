<h1>我的商品</h1>
<p class="lede">從玫瑰庫選來的苗，要等平台放行才會出現在前台。你改過的名稱和說明不會被蓋掉。</p>
<?php foreach ($products as $p): ?>
  <form method="post" action="/vendor/products" class="card pad" style="margin-bottom:12px">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
    <label class="f">名稱<input type="text" name="name" value="<?= h($p['name']) ?>"></label>
    <label class="f">說明<textarea name="description" rows="3"><?= h($p['description']) ?></textarea></label>
    <div class="filters">
      <label class="f">售價<input type="number" name="price_twd" value="<?= (int) $p['price_twd'] ?>"></label>
      <label class="f">狀態
        <input type="text" value="<?= h($p['status']) ?> / <?= $p['stock_gate'] === 'released' ? '已可賣' : '等平台放行' ?>" disabled>
      </label>
    </div>
    <p class="meta">庫存 <?= (int) $p['stock'] ?> 棵</p>
    <label class="f"><input type="checkbox" name="exclude_ship" value="1" <?= !empty($p['excluded']) ? 'checked' : '' ?>> 這項不計入免運</label>
    <p><button class="btn" type="submit">儲存</button></p>
  </form>
<?php endforeach; ?>
<?php if (!$products): ?><p class="note">還沒有商品。</p><?php endif; ?>
