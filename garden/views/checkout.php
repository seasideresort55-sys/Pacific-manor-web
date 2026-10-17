<h1>結帳</h1>
<p class="lede">下一步：核對下面的訂單，填寫收件資料。送出後會看到各店的轉帳帳號與期限。</p>
<?php foreach ($groups as $group): $q = $group['quote']; $v = $group['vendor']; ?>
  <div class="sum-box" style="margin-bottom:16px">
    <h2><?= h($v['name']) ?></h2>
    <?php foreach ($group['lines'] as $line): ?>
      <div class="sum-row">
        <span>
          <?= h(public_product_name($line)) ?> × <?= (int) $line['qty'] ?>
          <?php $credit = product_credit($line); if ($credit !== ''): ?>（<?= h($credit) ?>）<?php endif; ?>
          <?php if (is_preorder($line)): ?><span class="meta"><?= h(preorder_spec()) ?> · <?= h(ship_month_text($line)) ?></span><?php endif; ?>
          <?php if (!empty($line['excluded'])): ?> <span class="badge">不計入免運</span><?php endif; ?>
        </span>
        <span><?= money((int) $line['line_total']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="sum-row"><span>商品合計</span><strong><?= money((int) $q['goods']) ?></strong></div>
    <div class="sum-row"><span>計入免運</span><strong><?= money((int) $q['eligible']) ?></strong></div>
    <?php if ($q['need'] !== null): ?>
      <p>再買 <?= money((int) $q['need']) ?>（只算計入免運的商品）即免運。</p>
    <?php elseif (!empty($q['free'])): ?>
      <p>這家店這筆已達免運。</p>
    <?php endif; ?>
    <div class="sum-row"><span>運費</span><strong><?= $q['charge'] === null ? '店家尚未設定' : money((int) $q['charge']) ?></strong></div>
    <div class="sum-row"><span>本店應付</span><strong><?= money((int) $q['goods'] + (int) ($q['charge'] ?? 0)) ?></strong></div>
    <p>付款方式：銀行轉帳／ATM。帳號與期限在送出訂單後顯示。</p>
    <?php if (trim((string) ($v['account_no'] ?? '')) === ''): ?>
      <p>這家店還沒有填轉帳帳號。送出後若仍是空的，請先聯絡店家。</p>
    <?php endif; ?>
    <label class="f">轉帳末五碼（可稍後補）
      <input type="text" name="last5[<?= (int) $v['id'] ?>]" inputmode="numeric" maxlength="5" pattern="[0-9]{5}" form="checkout-form" placeholder="5 位數字">
    </label>
  </div>
<?php endforeach; ?>
<form id="checkout-form" method="post" action="<?= h(url('/checkout')) ?>">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <label class="f">收件人<input type="text" name="name" required placeholder="姓名"></label>
  <label class="f">電話<input type="text" name="phone" required placeholder="手機"></label>
  <label class="f">地址<input type="text" name="address" required placeholder="地址"></label>
  <p><button class="btn big" type="submit">送出訂單</button></p>
</form>
