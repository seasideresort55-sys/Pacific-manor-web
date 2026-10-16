<h1>購物車</h1>
<?php if (!$groups): ?>
  <p class="note">購物車是空的。<a href="/roses">去看玫瑰</a></p>
<?php endif; ?>
<?php foreach ($groups as $group): $q = $group['quote']; $v = $group['vendor']; ?>
  <h2><?= h($v['name']) ?></h2>
  <div class="card pad">
    <?php foreach ($group['lines'] as $line): ?>
      <form method="post" action="/cart/update" class="cart-line">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="product_id" value="<?= (int) $line['id'] ?>">
        <div>
          <strong><?= h($line['name']) ?></strong>
          <?php if (!empty($line['excluded'])): ?><span class="badge">不計入免運</span><?php endif; ?>
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
      <p>這家店還沒有設定運費。結帳前請先跟店家確認，平台不會代填。</p>
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
  <p style="margin-top:18px"><a class="btn" href="/checkout">前往結帳</a></p>
<?php endif; ?>
