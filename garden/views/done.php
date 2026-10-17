<h1>訂單已送出</h1>
<p class="lede">請用銀行轉帳或 ATM 匯到下面的帳號，並留下末五碼。店家確認後才算收款。</p>
<?php foreach ($ids as $id): $order = order_bundle((int) $id); if (!$order) continue; ?>
  <div class="sum-box" style="margin-bottom:16px">
    <h2>訂單 <?= (int) $order['id'] ?> · <?= h($order['vendor_name']) ?></h2>
    <?php foreach ($order['lines'] as $line): ?>
      <?php $shown = $line; $shown['status'] = $line['product_status'] ?? ''; ?>
      <div class="sum-row">
        <span>
          <?= h(public_product_name($shown)) ?> × <?= (int) $line['qty'] ?>
          <?php if (is_preorder($shown)): ?><span class="meta"><?= h(preorder_spec()) ?> · <?= h(ship_month_text($shown)) ?></span><?php endif; ?>
        </span>
        <span><?= money((int) $line['line_twd']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="sum-row"><span>運費</span><strong><?= $order['shipping_twd'] === null ? '店家尚未設定' : money((int) $order['shipping_twd']) ?></strong></div>
    <div class="sum-row"><span>應付</span><strong><?= money((int) $order['total_twd']) ?></strong></div>
    <?php if (trim((string) $order['account_no']) === ''): ?>
      <p class="warn">這家店還沒有填轉帳帳號，請先聯絡店家。</p>
    <?php else: ?>
      <p>銀行 <?= h($order['bank_name']) ?> <?= h($order['bank_code']) ?></p>
      <p>戶名 <?= h($order['account_name']) ?></p>
      <p>帳號 <?= h($order['account_no']) ?></p>
    <?php endif; ?>
    <p>期限 <?= $order['pay_deadline'] !== '' ? h($order['pay_deadline']) : '店家尚未設定' ?></p>
    <p>收款狀態 <?= h($order['pay_status'] !== '' ? $order['pay_status'] : '待填末五碼') ?></p>
    <?php if ($order['pay_status'] !== '已收款'): ?>
      <form method="post" action="<?= h(url('/order/last5')) ?>">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
        <label class="f">末五碼
          <input type="text" name="last5" inputmode="numeric" maxlength="5" value="<?= h($order['pay_last5']) ?>" required>
        </label>
        <p><button class="btn" type="submit">送出末五碼</button></p>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<p><a class="btn" href="<?= h(url('/roses')) ?>">繼續看玫瑰</a></p>
