<h1>登入</h1>
<?php if (!empty($error)): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
<form method="post" action="<?= h(url('/login')) ?>" class="card pad" style="max-width:420px">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <label class="f">帳號<input type="text" name="login" autocomplete="username" required></label>
  <label class="f">密碼<input type="password" name="password" autocomplete="current-password" required></label>
  <p><button class="btn big block" type="submit">登入</button></p>
</form>
