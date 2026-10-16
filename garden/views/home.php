<h1>找一株想種的玫瑰</h1>
<p class="lede">分類照國際株型。樹玫瑰是苗型（自根、低接、高幹），不是一個分類。</p>
<div class="chips">
  <?php foreach (categories() as $cat): ?>
    <a class="chip" href="/roses?class=<?= h($cat['code']) ?>"><?= h($cat['name']) ?></a>
  <?php endforeach; ?>
</div>
<div class="grid">
  <?php foreach ($products as $p): ?>
    <article class="card">
      <a class="hit" href="/product/<?= (int) $p['id'] ?>">
        <div class="photo" <?php if (!empty($p['photo'])): ?>style="background-image:url('/<?= h(ltrim($p['photo'], '/')) ?>')"<?php endif; ?>>
          <?php if (empty($p['photo'])) { include __DIR__ . '/icon.php'; } ?>
        </div>
        <div class="pad">
          <h2 class="name"><?= h($p['name']) ?></h2>
          <div class="meta"><?= h($p['vendor_name']) ?></div>
          <div class="price"><?= money((int) $p['price_twd']) ?></div>
        </div>
      </a>
    </article>
  <?php endforeach; ?>
</div>
