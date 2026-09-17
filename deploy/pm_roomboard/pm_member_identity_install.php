<?php
/**
 * 一次性、可重複執行的 qlo_pm_member 身分欄位安裝。
 * 只做加法：加 apple_sub／google_sub、UNIQUE NULL、拿掉 email UNIQUE（保留欄位與普通索引）。
 *
 * 網頁：需 pm_member_private/identity-install.key 或 ?key=
 * CLI：php pm_member_identity_install.php
 */

require_once __DIR__ . '/pm_member_identity_lib.php';
require_once __DIR__ . '/pm_member_social_lib.php';

$isCli = PHP_SAPI === 'cli';
$keyOk = false;
$expected = pm_identity_install_key();
if ($isCli) {
    $keyOk = true;
} elseif ($expected !== '') {
    $given = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
    $keyOk = $given !== '' && hash_equals($expected, $given);
} else {
    $keyOk = false;
}

$result = null;
if ($keyOk && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || $isCli) {
    $result = pms_schema();
} elseif ($keyOk && isset($_GET['run'])) {
    $result = pms_schema();
}

if ($isCli) {
    if (!$result) {
        $result = pms_schema();
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 1);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>會員身分欄位安裝｜太平洋莊園</title>
<style>
body{font:20px/1.6 system-ui,sans-serif;background:#F6F1E7;color:#1C3D4C;margin:0;padding:24px}
main{max-width:720px;margin:0 auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 10px 30px #1C3D4C14}
h1{font-size:28px}code{font-size:15px;background:#eef4f2;padding:2px 6px;border-radius:6px}
button,.btn{display:inline-block;font:inherit;background:#2E5E73;color:#fff;border:0;border-radius:10px;padding:12px 18px;text-decoration:none}
.err{background:#fdeaea;padding:12px;border-radius:10px}.ok{background:#e8f7ed;padding:12px;border-radius:10px}
</style>
</head>
<body>
<main>
<h1>會員身分欄位安裝</h1>
<p>這支程式只改正式表 <code>qlo_pm_member</code>：主鍵仍是 <code>id_member</code>（= user_id）。<strong>Email 不再 UNIQUE</strong>（規格禁止）。不會另建會員庫。</p>
<?php if (!$keyOk): ?>
<p class="err">請先在主機 <code>/home/tdwhhyfe/pm_member_private/identity-install.key</code> 放一把安裝金鑰，再用 <code>?key=</code> 開啟，或用 SSH 執行 CLI。</p>
<?php elseif (!$result): ?>
<p>將檢查並加入 <code>apple_sub</code>、<code>google_sub</code>、手機 UNIQUE（允許多筆空值），並把 Email 的 UNIQUE 改成普通索引。</p>
<p><a class="btn" href="?run=1&amp;key=<?php echo htmlspecialchars((string) ($_GET['key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">執行安裝</a></p>
<?php elseif (!empty($result['ok'])): ?>
<p class="ok"><?php echo htmlspecialchars($result['message'] ?? '完成', ENT_QUOTES, 'UTF-8'); ?></p>
<p>資料表：<code><?php echo htmlspecialchars($result['table'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></p>
<p>紀錄：<?php echo htmlspecialchars(implode('；', $result['log'] ?? ['無變更']), ENT_QUOTES, 'UTF-8'); ?></p>
<p><a href="pm_member_portal_v17.php#socialLogin">回登入頁</a></p>
<?php else: ?>
<p class="err"><?php echo htmlspecialchars($result['error'] ?? '安裝失敗', ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
</main>
</body>
</html>
