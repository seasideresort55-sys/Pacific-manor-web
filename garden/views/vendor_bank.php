<h1>收款帳號</h1>
<p class="lede">客人結帳後會看到這些資料。期限留空就不會代填天數。</p>
<?php if (!empty($saved)): ?><p class="note">已儲存。</p><?php endif; ?>
<form method="post" action="<?= h(url('/vendor/bank')) ?>" class="card pad">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <label class="f">銀行<input type="text" name="bank_name" value="<?= h($vendor['bank_name'] ?? '') ?>"></label>
  <label class="f">銀行代碼<input type="text" name="bank_code" value="<?= h($vendor['bank_code'] ?? '') ?>"></label>
  <label class="f">帳號<input type="text" name="account_no" value="<?= h($vendor['account_no'] ?? '') ?>"></label>
  <label class="f">戶名<input type="text" name="account_name" value="<?= h($vendor['account_name'] ?? '') ?>"></label>
  <label class="f">匯款天數<input type="number" name="pay_days" min="1" value="<?= isset($vendor['pay_days']) && $vendor['pay_days'] !== null && $vendor['pay_days'] !== '' ? (int) $vendor['pay_days'] : '' ?>" placeholder="留空表示尚未設定"></label>
  <p><button class="btn" type="submit">儲存</button></p>
</form>
