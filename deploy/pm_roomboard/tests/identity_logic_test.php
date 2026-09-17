<?php
/**
 * 身分決策單元測試（不連線上庫、不另建正式會員庫）。
 * 執行：php deploy/pm_roomboard/tests/identity_logic_test.php
 */
define('PM_IDENTITY_TEST', true);
require_once dirname(__DIR__) . '/pm_member_identity_lib.php';
require_once dirname(__DIR__) . '/pm_member_identity_hooks.php';

$failed = 0;
$passed = 0;

function expect($cond, $label)
{
    global $failed, $passed;
    if ($cond) {
        $passed++;
        echo "ok  $label\n";
        return;
    }
    $failed++;
    echo "FAIL $label\n";
}

// 手機正規化 → E.164
expect(pm_identity_normalize_phone('0986770718') === '+886986770718', '09 轉 E.164');
expect(pm_identity_normalize_phone('+886986770718') === '+886986770718', '已是 E.164');
expect(pm_identity_normalize_phone('886986770718') === '+886986770718', '886 轉 E.164');
expect(pm_identity_normalize_phone('123') === null, '無效電話');
expect(pm_identity_phone_local('+886986770718') === '0986770718', 'E.164 轉 09');

// Apple Relay 可當輔助信箱，不是身分
expect(pm_identity_is_apple_relay('remote.chang@privaterelay.appleid.com'), '辨識 Relay');
expect(!pm_identity_is_apple_relay('guest@example.com'), '一般信箱不是 Relay');

$members = [
    [
        'user_id' => 10086,
        'id_member' => 10086,
        'apple_sub' => 'A12345',
        'google_sub' => null,
        'phone' => null,
        'email' => 'old@privaterelay.appleid.com',
        'name' => '王先生',
    ],
    [
        'user_id' => 20001,
        'id_member' => 20001,
        'apple_sub' => null,
        'google_sub' => 'G999',
        'phone' => '+886900000001',
        'email' => 'same@example.com',
        'name' => '另一人',
    ],
];
$GLOBALS['pm_identity_test_members'] = $members;

// A. Apple：有 sub 就回同一 user_id
$r = pm_identity_decide_oauth($members, ['provider' => 'apple', 'sub' => 'A12345', 'email' => 'changed@privaterelay.appleid.com']);
expect(!empty($r['ok']) && (int) $r['user_id'] === 10086 && empty($r['created']), '同一 Apple sub 回 10086');

// Email 已在別的會員：綁定提示，不是硬擋、也不是靜默合併／新建
$r = pm_identity_decide_oauth($members, ['provider' => 'apple', 'sub' => 'BRAND_NEW_SUB', 'email' => 'old@privaterelay.appleid.com']);
expect(empty($r['ok']) && ($r['next_step'] ?? '') === 'confirm_bind' && strpos($r['error'], '請先用原本方式登入') !== false, 'Email 已存在改走綁定提示');

$r = pm_identity_decide_oauth($members, ['provider' => 'google', 'sub' => 'NEWG', 'email' => 'same@example.com']);
expect(empty($r['ok']) && ($r['next_step'] ?? '') === 'confirm_bind', 'Google 不靠 Email 合併，改提示綁定');

// Hide My Email 不擋
$r = pm_identity_decide_oauth($members, ['provider' => 'apple', 'sub' => 'RELAY1', 'email' => 'hideme@privaterelay.appleid.com']);
expect(!empty($r['ok']) && ($r['action'] ?? '') === 'create' && $r['aux_email'] === 'hideme@privaterelay.appleid.com', 'Relay 可寫入輔助 Email');

$r = pm_identity_decide_oauth($members, ['provider' => 'apple', 'sub' => 'NOEMAIL', 'email' => '']);
expect(!empty($r['ok']) && ($r['action'] ?? '') === 'create', '沒有 Email 也可建 Apple 會員');

// 已登入綁定：要確認，不可靜默
$r = pm_identity_decide_oauth($members, [
    'provider' => 'google',
    'sub' => 'NEW_GOOGLE',
    'logged_in_user_id' => 10086,
    'confirm_bind' => false,
]);
expect(empty($r['ok']) && ($r['next_step'] ?? '') === 'confirm_bind', '已登入綁 Google 先提示');

$r = pm_identity_decide_oauth($members, [
    'provider' => 'google',
    'sub' => 'NEW_GOOGLE',
    'logged_in_user_id' => 10086,
    'confirm_bind' => true,
]);
expect(!empty($r['ok']) && ($r['action'] ?? '') === 'bind' && (int) $r['user_id'] === 10086, '確認後綁同一 user_id');

// 衝突：sub 已在別人身上
$r = pm_identity_decide_oauth($members, [
    'provider' => 'google',
    'sub' => 'G999',
    'logged_in_user_id' => 10086,
    'confirm_bind' => true,
]);
expect(empty($r['ok']) && strpos($r['error'], 'Google') !== false && strpos($r['error'], '其他會員') !== false, 'Google 衝突白話');

$r = pm_identity_decide_oauth($members, [
    'provider' => 'apple',
    'sub' => 'A12345',
    'logged_in_user_id' => 20001,
    'confirm_bind' => true,
]);
expect(empty($r['ok']) && strpos($r['error'], 'Apple') !== false, 'Apple 衝突白話');

// 手機：未驗證不可登入
$r = pm_identity_decide_phone($members, ['phone' => '0900000001', 'phone_verified' => false]);
expect(empty($r['ok']), '未驗證手機不可登入');

$r = pm_identity_decide_phone($members, ['phone' => '0900000001', 'phone_verified' => true]);
expect(!empty($r['ok']) && (int) $r['user_id'] === 20001, '驗證後用手機登入同一帳');

$r = pm_identity_decide_phone($members, ['phone' => '0986770718', 'phone_verified' => true]);
expect(!empty($r['ok']) && ($r['action'] ?? '') === 'create', '新手機驗證後新建');

$r = pm_identity_decide_phone($members, [
    'phone' => '0900000001',
    'phone_verified' => true,
    'logged_in_user_id' => 10086,
    'confirm_bind' => true,
]);
expect(empty($r['ok']) && strpos($r['error'], '手機') !== false, '手機衝突白話');

$r = pm_identity_decide_phone($members, [
    'phone' => '0911111111',
    'phone_verified' => true,
    'logged_in_user_id' => 10086,
    'confirm_bind' => false,
]);
expect(empty($r['ok']) && ($r['next_step'] ?? '') === 'confirm_bind', '已登入綁手機先提示');

$pub = pm_identity_public_member($members[0]);
expect($pub['user_id'] === 10086 && $pub['email_is_apple_relay'] && $pub['contact_priority'] === 'email', '公開欄位：Relay 為輔助聯絡');

$pub2 = pm_identity_public_member($members[1]);
expect($pub2['contact_priority'] === 'phone', '有手機則主要聯絡為手機');

expect(strpos(pm_identity_msg('google_conflict'), '請先用該方式登入') !== false, '衝突文案無英文代碼');
expect(strpos(pm_identity_msg('email_aux_only'), 'Email') !== false, 'Email 不當主鍵說明');

expect(pm_identity_empty_email_allowed() === true, 'hooks 允許空 Email');
$schema = pm_identity_schema_relax_email();
expect(!empty($schema['ok']) && empty($schema['email_unique_required']), 'schema hook 不要求 unique email');
expect(strpos(json_encode($schema, JSON_UNESCAPED_UNICODE), 'Unique email migration required') === false, '不再出現 Unique email migration required');

$emptyMail = pm_identity_after_oauth_profile('apple', 'NOMAIL2', '', '會員');
expect(!empty($emptyMail['ok']) && ($emptyMail['action'] ?? '') === 'create', 'hooks 無 Email 可建（Hide My Email）');
expect(strpos(json_encode($emptyMail, JSON_UNESCAPED_UNICODE), '登入服務未提供 Email') === false, '不再擋沒有 Email');

$bindHint = pm_identity_after_oauth_profile('google', 'GX', 'same@example.com');
expect(empty($bindHint['ok']) && ($bindHint['next_step'] ?? '') === 'confirm_bind', 'hooks Email 衝突走綁定提示');

$phoneGuest = pm_identity_phone_login_guest('0986770718', true);
expect(!empty($phoneGuest['ok']) && ($phoneGuest['action'] ?? '') === 'create', '訪客手機驗證後可建檔登入');

$phoneExist = pm_identity_phone_login_guest('0900000001', true);
expect(!empty($phoneExist['ok']) && (int) $phoneExist['user_id'] === 20001, '訪客手機驗證後登入同一 user_id');

expect(pm_identity_conflict_zh('google') === pm_identity_msg('google_conflict'), 'Google 衝突白話');
expect(pm_identity_conflict_zh('phone') === pm_identity_msg('phone_conflict'), '手機衝突白話');
expect(function_exists('pms_schema') === false && function_exists('pms_member') === false, 'hooks 不宣告線上 pms_* 以免覆蓋');

echo "\n$passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
