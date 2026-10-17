<?php
$facts = card_facts($p);
$photo = $p['photo'] ?? '';
?>
<article class="card">
  <a class="hit" href="<?= h(url('/product/' . (int) $p['id'])) ?>">
    <div class="photo" <?php if ($photo): ?>style="background-image:url('<?= h(media_url($photo)) ?>')"<?php endif; ?>>
      <?php if (!$photo) { include __DIR__ . '/icon.php'; } ?>
    </div>
    <div class="pad">
      <h2 class="name"><?= h(public_product_name($p)) ?></h2>
      <?php if ($facts): ?>
        <div class="meta"><?php
          $bits = [];
          foreach ($facts as $label => $value) {
              $bits[] = $label . ' ' . $value;
          }
          echo h(implode(' · ', $bits));
        ?></div>
      <?php endif; ?>
      <?php if (is_preorder($p)): ?>
        <div class="meta"><?= h(preorder_spec()) ?> · <?= h(ship_month_text($p)) ?></div>
      <?php endif; ?>
      <?php $credit = product_credit($p); ?>
      <div class="meta"><?= h($credit !== '' ? $credit : (string) $p['vendor_name']) ?></div>
      <div class="price"><?= money((int) $p['price_twd']) ?></div>
    </div>
  </a>
</article>
