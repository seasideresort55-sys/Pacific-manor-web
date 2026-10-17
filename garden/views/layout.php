<?php
$title = $title ?? '太平洋莊園';
$toast = $_SESSION['toast'] ?? '';
unset($_SESSION['toast']);
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · 太平洋莊園</title>
  <link rel="stylesheet" href="<?= h(url('/assets/app.css')) ?>">
  <?= $headExtra ?? '' ?>
</head>
<body>
<header class="top">
  <div class="wrap">
    <a class="brand" href="<?= h(url('/')) ?>">太平洋莊園</a>
    <nav class="nav">
      <a href="<?= h(url('/roses')) ?>">玫瑰</a>
      <a href="<?= h(url('/rules')) ?>">購物說明</a>
      <a href="<?= h(url('/cart')) ?>">購物車<?php if (!empty($cartCount)): ?>（<?= (int) $cartCount ?>）<?php endif; ?></a>
      <?php if (!empty($user) && $user['role'] === 'vendor'): ?>
        <a href="<?= h(url('/vendor')) ?>">店家</a>
      <?php elseif (!empty($user) && $user['role'] === 'admin'): ?>
        <a href="<?= h(url('/admin/roses')) ?>">玫瑰庫</a>
      <?php else: ?>
        <a href="<?= h(url('/login')) ?>">登入</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
  <div class="wrap">
    <?php if ($flash !== ''): ?><p class="warn"><?= h($flash) ?></p><?php endif; ?>
    <?= $body ?>
  </div>
</main>
<footer class="foot">
  <div class="wrap foot-links">
    <a href="<?= h(url('/about')) ?>">關於我們</a>
    <a href="<?= h(url('/contact')) ?>">客服電話與 LINE</a>
    <a href="<?= h(url('/rules')) ?>">運費與退換貨</a>
  </div>
</footer>
<?php if ($toast !== ''): ?>
  <div class="toast" id="garden-toast"><?= h($toast) ?></div>
  <script>
    setTimeout(function () {
      var el = document.getElementById('garden-toast');
      if (!el) return;
      el.classList.add('out');
      setTimeout(function () { el.remove(); }, 400);
    }, 2200);
  </script>
<?php endif; ?>
</body>
</html>
