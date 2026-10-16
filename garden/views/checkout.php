<h1>結帳</h1>
<?php foreach ($groups as $group): $q = $group['quote']; ?>
  <div class="sum-box" style="margin-bottom:16px">
    <h2><?= h($group['vendor']['name']) ?></h2>
    <?php foreach ($group['lines'] as $line): ?>
      <div class="sum-row">
        <span><?= h($line['name']) ?> × <?= (int) $line['qty'] ?><?php if (!empty($line['excluded'])): ?> <span class="badge">不計入免運</span><?php endif; ?></span>
        <span><?= money((int) $line['line_total']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="sum-row"><span>計入免運</span><strong><?= money((int) $q['eligible']) ?></strong></div>
    <?php if ($q['need'] !== null): ?>
      <p>再買 <?= money((int) $q['need']) ?>（只算計入免運的商品）即免運。</p>
    <?php endif; ?>
    <div class="sum-row"><span>運費</span><strong><?= $q['charge'] === null ? '店家尚未設定' : money((int) $q['charge']) ?></strong></div>
  </div>
<?php endforeach; ?>
<form method="post" action="/checkout">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <label class="f">收件人<input type="text" name="name" required placeholder="姓名"></label>
  <label class="f">電話<input type="text" name="phone" required placeholder="手機"></label>
  <label class="f">地址<input type="text" name="address" required placeholder="地址"></label>
  <p><button class="btn big" type="submit">送出訂單</button></p>
</form>
