<?php
/** @var array $products */
/** @var array $filters */
/** @var string $blurb */
$filters = $filters ?? [];
?>
<h1>玫瑰</h1>
<p class="lede">先看株型、花色、香味、花期、耐熱。其餘資料在每一株的頁面。</p>
<form class="filters" method="get" action="/roses">
  <label class="f">株型
    <select name="class">
      <option value="">全部</option>
      <?php foreach (categories() as $cat): ?>
        <option value="<?= h($cat['code']) ?>" <?= ($filters['class'] ?? '') === $cat['code'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="f">花色
    <select name="color">
      <option value="">全部</option>
      <?php foreach (['白','乳白','粉','紅','深紅','橘','杏','黃','紫','綠','複色','條紋'] as $c): ?>
        <option value="<?= h($c) ?>" <?= ($filters['color'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="f">香味
    <select name="fragrance">
      <option value="">全部</option>
      <?php foreach (['無','淡香','中香','強香'] as $c): ?>
        <option value="<?= h($c) ?>" <?= ($filters['fragrance'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="f">花期
    <select name="bloom">
      <option value="">全部</option>
      <?php foreach (['四季開花','反覆開','一季開'] as $c): ?>
        <option value="<?= h($c) ?>" <?= ($filters['bloom'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="f">耐熱
    <select name="heat">
      <option value="">全部</option>
      <option value="1" <?= ($filters['heat'] ?? '') === '1' ? 'selected' : '' ?>>賣家標示耐熱</option>
    </select>
  </label>
  <button class="btn" type="submit">篩選</button>
</form>
<?php if (!empty($blurb)): ?>
  <p class="note"><?= h($blurb) ?></p>
<?php endif; ?>
<?php if (!$products): ?>
  <p class="note">這個條件目前沒有上架的玫瑰。</p>
<?php endif; ?>
<div class="grid">
  <?php foreach ($products as $p): ?>
    <article class="card">
      <a class="hit" href="/product/<?= (int) $p['id'] ?>">
        <div class="photo" <?php if (!empty($p['photo'])): ?>style="background-image:url('/<?= h(ltrim($p['photo'], '/')) ?>')"<?php endif; ?>>
          <?php if (empty($p['photo'])) { include __DIR__ . '/icon.php'; } ?>
        </div>
        <div class="pad">
          <h2 class="name"><?= h($p['name']) ?></h2>
          <?php $credit = product_credit($p); ?>
          <div class="meta"><?= h($credit !== '' ? $credit : ($p['vendor_name'] . ' · ' . category_name((string) ($p['class_main'] ?: $p['category_code'])))) ?></div>
          <div class="price"><?= money((int) $p['price_twd']) ?></div>
        </div>
      </a>
    </article>
  <?php endforeach; ?>
</div>
