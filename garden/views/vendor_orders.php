<h1>收款確認</h1>
<p class="lede">客人填了末五碼之後，核對入帳再按確認。</p>
<?php if (!$orders): ?><p class="note">還沒有訂單。</p><?php endif; ?>
<?php foreach ($orders as $order): ?>
  <div class="card pad" style="margin-bottom:12px">
    <p><strong>訂單 <?= (int) $order['id'] ?></strong> · <?= h($order['pay_status'] !== '' ? $order['pay_status'] : '待填末五碼') ?></p>
    <p><?= h((string) ($order['summary'] ?? '')) ?></p>
    <p>應付 <?= money((int) $order['total_twd']) ?> · 末五碼 <?= h($order['pay_last5'] !== '' ? $order['pay_last5'] : '尚未填') ?></p>
    <p class="meta"><?= h($order['buyer_name']) ?> <?= h($order['buyer_phone']) ?></p>
    <?php if ($order['pay_status'] !== '已收款' && $order['pay_last5'] !== ''): ?>
      <form method="post" action="<?= h(url('/vendor/orders')) ?>">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
        <button class="btn" type="submit">確認收款</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
