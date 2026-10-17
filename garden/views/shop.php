<?php
$filters = $filters ?? [];
$store = $store ?? null;
$colors = filter_values('color_group');
$scents = filter_values('fragrance');
$blooms = filter_values('bloom_habit');
?>
<?php if ($store): ?>
  <h1><?= h($store['name']) ?></h1>
  <p class="lede">這一家店現在上架的商品。運費依這家店的後台。</p>
<?php else: ?>
  <h1>玫瑰</h1>
  <p class="lede">用株型、花色、香味、花期來找。沒有填好的條件不會出現在選單裡。</p>
<?php endif; ?>
<?php if (!$store): ?>
<form class="filters" method="get" action="<?= h(url('/roses')) ?>">
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
      <?php foreach ($colors as $c): ?>
        <option value="<?= h($c) ?>" <?= ($filters['color'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="f">香味
    <select name="fragrance">
      <option value="">全部</option>
      <?php foreach ($scents as $c): ?>
        <option value="<?= h($c) ?>" <?= ($filters['fragrance'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="f">花期
    <select name="bloom">
      <option value="">全部</option>
      <?php foreach ($blooms as $c): ?>
        <option value="<?= h($c) ?>" <?= ($filters['bloom'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="f">耐熱
    <select name="heat">
      <option value="">全部</option>
      <option value="1" <?= ($filters['heat'] ?? '') === '1' ? 'selected' : '' ?>>有標示耐熱</option>
    </select>
  </label>
  <button class="btn" type="submit">篩選</button>
</form>
<?php endif; ?>
<?php if (!empty($blurb)): ?>
  <p class="note"><?= h($blurb) ?></p>
<?php endif; ?>
<?php if (!$products): ?>
  <p class="note">這個條件目前沒有可買的玫瑰。</p>
<?php endif; ?>
<div class="grid">
  <?php foreach ($products as $p): ?>
    <?php include __DIR__ . '/card.php'; ?>
  <?php endforeach; ?>
</div>
