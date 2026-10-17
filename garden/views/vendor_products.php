<h1>我的商品</h1>
<p class="lede">共 <?= (int) ($total ?? count($products)) ?> 筆。新苗預購全部列在這裡，預計出貨月份可以改。其餘只列最新 40 筆。自己的商品可以改名稱、說明、售價，也可以開放給其他店代賣。代賣來的商品跟著原店，這裡不能改。從玫瑰庫選來的苗，要等平台放行才會出現在前台。</p>
<?php foreach ($products as $p): ?>
  <?php $mirror = !empty($p['origin_product_id']); ?>
  <?php if ($mirror): ?>
    <div class="card pad" style="margin-bottom:12px">
      <p class="meta">代賣自其他店。名稱、照片、說明、售價、庫存跟原店同一份。</p>
      <p><strong><?= h($p['name']) ?></strong></p>
      <p><?= nl2br(h($p['description'])) ?></p>
      <p class="price"><?= money((int) $p['price_twd']) ?></p>
      <p class="meta">庫存 <?= (int) $p['stock'] ?> 棵 · <?= h($p['status']) ?> / <?= $p['stock_gate'] === 'released' ? '已可賣' : '原店已關閉代賣' ?></p>
    </div>
  <?php else: ?>
    <form method="post" action="<?= h(url('/vendor/products')) ?>" class="card pad" style="margin-bottom:12px">
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
      <?php if (($p['status'] ?? '') === '新苗預購'): ?>
        <label class="f">預計出貨月份<input type="text" name="ship_month" value="<?= h((string) ($p['ship_month'] ?? '')) ?>" placeholder="例如 2027年1月"></label>
      <?php endif; ?>
      <label class="f"><input type="checkbox" name="consign_open" value="1" <?= !empty($p['consign_open']) ? 'checked' : '' ?>> 開放給其他店代賣</label>
      <label class="f"><input type="checkbox" name="exclude_ship" value="1" <?= !empty($p['excluded']) ? 'checked' : '' ?>> 這項不計入免運</label>
      <p><button class="btn" type="submit">儲存</button></p>
    </form>
  <?php endif; ?>
<?php endforeach; ?>
<?php if (!$products): ?><p class="note">還沒有商品。</p><?php endif; ?>
