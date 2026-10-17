<section class="hero">
  <div>
    <h1>挑一株玫瑰</h1>
    <p class="lede">先看株型、花色、花徑和香味。每一株都有自己的網址。</p>
    <p><a class="btn big" href="<?= h(url('/roses')) ?>">看全部玫瑰</a></p>
  </div>
</section>
<h2>精選</h2>
<div class="grid">
  <?php foreach ($products as $p): ?>
    <?php include __DIR__ . '/card.php'; ?>
  <?php endforeach; ?>
</div>
<?php if (!$products): ?><p class="note">現在沒有可買的玫瑰。</p><?php endif; ?>
<?php if (!empty($preorders)): ?>
  <h2>新苗預購</h2>
  <p class="lede">規格都是 <?= h(preorder_spec()) ?>。每株標預計出貨月份。</p>
  <div class="grid">
    <?php foreach ($preorders as $p): ?>
      <?php include __DIR__ . '/card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
