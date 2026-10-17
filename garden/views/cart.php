<h1>購物車</h1>
<?php if (!$groups): ?>
  <div class="empty">
    <h2>購物車是空的</h2>
    <p>挑好玫瑰再回來結帳。</p>
    <p><a class="btn" href="<?= h(url('/roses')) ?>">去看玫瑰</a></p>
  </div>
<?php endif; ?>
<?php foreach ($groups as $group): $q = $group['quote']; $v = $group['vendor']; ?>
  <h2><?= h($v['name']) ?></h2>
  <div class="card pad">
    <?php foreach ($group['lines'] as $line): ?>
      <form method="post" action="<?= h(url('/cart/update')) ?>" class="cart-line">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="product_id" value="<?= (int) $line['id'] ?>">
        <div>
          <strong><?= h(public_product_name($line)) ?></strong>
          <?php if (!empty($line['excluded'])): ?><span class="badge">不計入免運</span><?php endif; ?>
          <?php if (is_preorder($line)): ?><div class="meta"><?= h(preorder_spec()) ?> · <?= h(ship_month_text($line)) ?></div><?php endif; ?>
          <?php $credit = product_credit($line); if ($credit !== ''): ?><div class="meta"><?= h($credit) ?><?php if (!empty($line['origin_product_id'])): ?>，由原店出貨<?php endif; ?></div><?php endif; ?>
          <div class="meta"><?= money((int) $line['price_twd']) ?></div>
        </div>
        <div>
          <input class="qty" type="number" name="qty" min="0" value="<?= (int) $line['qty'] ?>">
          <button class="btn" type="submit">更新</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
  <div class="sum-box" style="margin-top:12px">
    <div class="sum-row"><span>商品合計</span><strong><?= money((int) $q['goods']) ?></strong></div>
    <div class="sum-row"><span>計入免運的金額</span><strong><?= money((int) $q['eligible']) ?></strong></div>
    <?php if ($q['excluded']): ?>
      <p class="warn">以下商品不計入免運門檻：</p>
      <ul>
        <?php foreach ($q['excluded'] as $ex): ?>
          <li><?= h($ex['name']) ?> <?= money((int) $ex['line_total']) ?> <span class="badge">不計入免運</span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($q['fee_set'] === null): ?>
      <p>這家店還沒有設定運費。請先跟店家確認，再結帳。</p>
    <?php elseif ($q['free']): ?>
      <div class="sum-row"><span>運費</span><strong><?= money(0) ?>（已達免運）</strong></div>
    <?php else: ?>
      <div class="sum-row"><span>運費</span><strong><?= money((int) $q['charge']) ?></strong></div>
      <?php if ($q['need'] !== null): ?>
        <p>計入免運的商品再買 <?= money((int) $q['need']) ?> 即免運。標成不計入免運的商品不會算進去。</p>
      <?php elseif ($q['threshold'] === null): ?>
        <p>這家店有設定運費，還沒有設定免運門檻。</p>
      <?php endif; ?>
    <?php endif; ?>
    <div class="sum-row"><span>本店應付</span><strong><?= money((int) $q['goods'] + (int) ($q['charge'] ?? 0)) ?></strong></div>
  </div>
<?php endforeach; ?>
<?php if ($groups): ?>
  <p style="margin-top:18px"><a class="btn" href="<?= h(url('/checkout')) ?>">前往結帳</a></p>
<?php endif; ?>
