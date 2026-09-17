<?php
/**
 * Apple form_post 回呼：驗證 id_token 的 sub，找或建／綁定同一 user_id。
 */
require_once __DIR__ . '/pm_member_identity_lib.php';
require_once __DIR__ . '/pm_member_identity_api.php';

pm_identity_session_start();
$token = (string) ($_POST['id_token'] ?? '');
$user = (string) ($_POST['user'] ?? '');
$name = '';
if ($user !== '') {
    $decoded = json_decode($user, true);
    if (is_array($decoded)) {
        $name = trim(($decoded['name']['lastName'] ?? '') . ($decoded['name']['firstName'] ?? ''));
    }
}

$confirm = !empty($_POST['confirm_ok']);
$result = ['ok' => false, 'error' => pm_identity_msg('bad_token')];
if ($token !== '') {
    $loggedIn = pm_identity_logged_in_user_id() > 0;
    $result = pm_identity_action_oauth([
        'provider' => 'apple',
        'id_token' => $token,
        'name' => $name,
        'confirm_bind' => $confirm || !$loggedIn,
    ], $loggedIn);
}

$ok = !empty($result['ok']);
$needBind = ($result['next_step'] ?? '') === 'confirm_bind';
if ($ok) {
    header('Location: pm_member_center_v17.php');
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Apple 登入結果｜太平洋莊園</title>
<style>
body{font:20px/1.65 system-ui;background:#F6F1E7;color:#1C3D4C;margin:0;padding:24px}
main{max-width:460px;margin:4vh auto;background:#fff;border-radius:20px;padding:28px}
.err{color:#922e26}
button{font:inherit;min-height:48px;width:100%;border:0;border-radius:12px;background:#2E5E73;color:#fff}
</style>
</head>
<body>
<main>
<h1>Apple 登入</h1>
<p class="<?php echo $needBind ? '' : 'err'; ?>"><?php echo htmlspecialchars($result['error'] ?? '登入沒有完成', ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($needBind): ?>
<form method="post" action="pm_apple_callback.php">
<input type="hidden" name="id_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
<p>您已有會員帳號，是否綁定 Apple？系統不會在未確認時合併帳號。</p>
<p><label><input type="checkbox" name="confirm_ok" value="1" required> 是，綁定到目前這個會員</label></p>
<button type="submit">確認綁定</button>
</form>
<?php else: ?>
<p><a href="pm_apple_login.php">返回再試</a></p>
<?php endif; ?>
</main>
</body>
</html>
