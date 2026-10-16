<?php
$title = $title ?? '園藝商城';
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> · 園藝商城</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="top">
  <div class="wrap">
    <a class="brand" href="/">園藝商城</a>
    <nav class="nav">
      <a href="/roses">玫瑰</a>
      <a href="/cart">購物車<?php if (!empty($cartCount)): ?>（<?= (int) $cartCount ?>）<?php endif; ?></a>
      <?php if (!empty($user) && $user['role'] === 'vendor'): ?>
        <a href="/vendor">店家</a>
      <?php elseif (!empty($user) && $user['role'] === 'admin'): ?>
        <a href="/admin/roses">玫瑰庫</a>
      <?php else: ?>
        <a href="/login">登入</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
  <div class="wrap">
    <?= $body ?>
  </div>
</main>
<p class="foot wrap">運費與免運由各店自己設定。平台不代填。</p>
</body>
</html>
