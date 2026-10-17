<h1>可代賣商品</h1>
<p class="lede">其他店開放代賣的商品。按下「我要代賣」就立刻上架，不用等審核。名稱、照片、說明、售價跟著原店，原店一改這裡也改。庫存跟原店同一份，賣出一棵到處都少一棵。</p>
<?php if (!empty($message)): ?><p class="note"><?= h($message) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
<?php foreach ($products as $p): ?>
  <article class="card" style="margin-bottom:14px">
    <div class="photo" style="aspect-ratio:16/9;<?= !empty($p['photo']) ? "background-image:url('" . h(media_url($p['photo'])) . "')" : '' ?>">
      <?php if (empty($p['photo'])) { include __DIR__ . '/icon.php'; } ?>
    </div>
    <div class="pad">
      <h2 class="name"><?= h($p['name']) ?></h2>
      <div class="meta"><?= h($p['vendor_name']) ?>種植</div>
      <div class="price"><?= money((int) $p['price_twd']) ?></div>
      <p class="meta">庫存 <?= (int) $p['stock'] ?> 棵<?= (int) $p['stock'] <= 0 ? '，目前完售' : '' ?></p>
      <?php if (!empty($p['description'])): ?><p><?= nl2br(h($p['description'])) ?></p><?php endif; ?>
      <?php if (!empty($p['mine_id'])): ?>
        <button class="btn big block" type="button" disabled>已在賣</button>
        <p class="meta"><a href="<?= h(url('/product/' . (int) $p['mine_id'])) ?>">看商品頁</a></p>
      <?php else: ?>
        <form method="post" action="<?= h(url('/vendor/consign')) ?>">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="origin_product_id" value="<?= (int) $p['id'] ?>">
          <button class="btn big block" type="submit">我要代賣</button>
        </form>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
<?php if (!$products): ?><p class="note">目前沒有其他店開放代賣的商品。</p><?php endif; ?>
