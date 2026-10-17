<h1>選苗上架</h1>
<p class="lede">選一株，數量填好，就會建成你的商品。名稱、說明、規格、照片會自動帶入。</p>
<?php if (!empty($message)): ?><p class="note"><?= h($message) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
<div class="chips">
  <a class="chip <?= ($class ?? '') === '' ? 'on' : '' ?>" href="<?= h(url('/vendor/pick')) ?>">全部可選</a>
  <?php foreach (categories() as $cat): ?>
    <a class="chip <?= ($class ?? '') === $cat['code'] ? 'on' : '' ?>" href="<?= h(url('/vendor/pick?class=' . rawurlencode($cat['code']))) ?>"><?= h($cat['name']) ?></a>
  <?php endforeach; ?>
</div>
<div class="chips">
  <?php foreach (['白','粉','紅','橘','黃','紫','複色'] as $c): ?>
    <a class="chip <?= ($color ?? '') === $c ? 'on' : '' ?>" href="<?= h(url('/vendor/pick?class=' . rawurlencode((string) ($class ?? '')) . '&color=' . rawurlencode($c))) ?>"><?= h($c) ?></a>
  <?php endforeach; ?>
</div>
<div class="grid">
  <?php foreach ($offers as $offer):
    $prices = offer_prices($offer, $offer);
    $block = vendor_may_take($offer, $offer, 1);
  ?>
    <article class="card">
      <div class="photo" <?php if (!empty($offer['photo'])): ?>style="background-image:url('<?= h(media_url($offer['photo'])) ?>')"<?php endif; ?>>
        <?php if (empty($offer['photo'])) { include __DIR__ . '/icon.php'; } ?>
      </div>
      <div class="pad">
        <h2 class="name"><?= h($offer['name_zh']) ?></h2>
        <div class="meta"><?= h(category_name((string) $offer['class_main'])) ?> · <?= h($offer['color_group']) ?> · <?= h($offer['fragrance']) ?></div>
        <div class="meta">苗型 <?= h($offer['seedling']) ?> · 平台還有 <?= (int) $offer['platform_stock'] ?> 棵</div>
        <div class="price"><?= $prices['retail'] === null ? '售價待補' : money((int) $prices['retail']) ?></div>
        <?php if ($block): ?>
          <p class="warn"><?= h($block) ?></p>
          <button class="btn big block" type="button" disabled>不能上架</button>
        <?php else: ?>
          <form method="post" action="<?= h(url('/vendor/pick')) ?>">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="offer_id" value="<?= (int) $offer['offer_id'] ?>">
            <label class="f">數量
              <input type="number" name="qty" min="1" max="<?= (int) $offer['platform_stock'] ?>" value="1">
            </label>
            <button class="btn big block" type="submit">上架</button>
          </form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php if (!$offers): ?>
  <p class="note">現在沒有符合的苗。請平台先開放品種、填好庫存。</p>
<?php endif; ?>
