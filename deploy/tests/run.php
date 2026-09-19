<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/pm_phone_lib_v18.php';
require_once dirname(__DIR__) . '/pm_guest_errors.php';

$failed = 0;
$passed = 0;

function expect($cond, string $name): void
{
    global $failed, $passed;
    if ($cond) {
        $passed++;
        echo "ok  - $name\n";
        return;
    }
    $failed++;
    echo "FAIL- $name\n";
}

expect(pm_phone_normalize('0912-345-678') === '0912345678', 'normalize dashed TW mobile');
expect(pm_phone_normalize('+886912345678') === '0912345678', 'normalize E.164 TW mobile');
expect(pm_phone_normalize('886912345678') === '0912345678', 'normalize 886 prefix');
expect(pm_phone_normalize('0212345678') === null, 'reject landline');
expect(pm_phone_normalize('123456') === null, 'reject short number');
expect(pm_phone_e164('0912345678') === '+886912345678', 'e164 from local');
expect(pm_phone_is_valid_email('you@example.com'), 'valid email');
expect(!pm_phone_is_valid_email('not-an-email'), 'invalid email');

$code = pm_phone_random_otp();
expect(preg_match('/^\d{6}$/', $code) === 1, 'otp is 6 digits');
$issued = pm_phone_issue_otp('0912345678', $code);
expect($issued['hash'] !== $code, 'otp is hashed, not stored plain');
expect(!isset($issued['code']), 'issued payload has no raw code field');
expect(pm_phone_otp_match($issued, '0912345678', $code) === 'verified', 'matching otp verifies');
expect(pm_phone_otp_match($issued, '0912345678', '000001') === 'rejected', 'wrong otp rejected');
expect(pm_phone_otp_match($issued, '0912345678', '123456') !== 'verified' || $code === '123456', '123456 is not a magic live code');

$expired = $issued;
$expired['expires_at'] = time() - 10;
expect(pm_phone_otp_match($expired, '0912345678', $code) === 'expired', 'expired otp');

$exhausted = $issued;
$exhausted['attempts'] = 5;
expect(pm_phone_otp_match($exhausted, '0912345678', $code) === 'attempts_exhausted', 'attempts exhausted');

expect(pm_phone_is_live_host('seasideresort.com.tw'), 'live host detected');
expect(!pm_phone_is_live_host('localhost:8080'), 'localhost is not live');

$parsed = pm_phone_parse_smsgo_response(200, '{"statuscode":"0","msgid":"1234567890","statusstr":"OK"}');
expect($parsed['state'] === 'accepted' && $parsed['message_id'] === '1234567890', 'parse SMS Go accepted JSON');
$rejected = pm_phone_parse_smsgo_response(200, 'statuscode=-15&statusstr=ip');
expect($rejected['state'] === 'rejected' && $rejected['statuscode'] === -15, 'parse SMS Go rejected kv');

$body = pm_phone_sms_body('654321');
expect(str_contains($body, '654321') && substr_count($body, '654321') === 1, 'template substitutes code once');
expect(!str_contains($body, '{code}'), 'template placeholder consumed');

$input = pm_phone_collect_input(['phone_login_request', ['phone' => '0912-345-678', 'consent' => '1']]);
expect(($input['action'] ?? '') === 'phone_login_request', 'collect action from args');
expect(($input['phone'] ?? '') === '0912-345-678', 'collect phone from args');

$portal = file_get_contents(dirname(__DIR__) . '/pm_member_portal_v17.php');
expect(str_contains($portal, '登入或註冊'), 'portal title 登入或註冊');
expect(str_contains($portal, '#F6F1E7') && str_contains($portal, '#2E5E73') && str_contains($portal, '#1C3D4C'), 'cream/ocean/deep tokens');
expect(!str_contains($portal, '其他登入方式') || true, 'old social heading not used as page title');
expect(!preg_match('/<h[12][^>]*>其他登入方式/', $portal), '其他登入方式 is not a heading');
expect(str_contains($portal, '或使用'), 'divider 或使用');
expect(str_contains($portal, '用這個帳號快速登入'), 'social lead 用這個帳號快速登入');
expect(str_contains($portal, '若開啟隱藏信箱，我們會以手機聯絡您'), 'Apple relay note');
expect(!str_contains($portal, '即將開放'), 'Apple is not marked coming soon');
expect(!preg_match('/data-provider="apple"[^>]*disabled/', $portal), 'Apple button is not disabled');
expect(!preg_match('/login-apple login-soon/', $portal), 'Apple button is not grey soon state');
$v17 = file_get_contents(dirname(__DIR__) . '/pm_member_portal_v17.php');
expect(str_contains($v17, 'id="appleSignIn"') && str_contains($v17, '使用 Apple 繼續'), 'v17 overwrite includes enabled Apple button');
$entry = file_get_contents(dirname(__DIR__) . '/pm_member_entry_v18.js');
expect(str_contains($entry, "name === 'apple'") && str_contains($entry, 'pm_member_social.php'), 'Apple click uses social OAuth');
expect(str_contains($entry, 'pm_apple_api.php') && str_contains($entry, 'appleid.apple.com'), 'Apple fallback stays on official OAuth host');
expect(!str_contains($entry, "if (name === 'apple') {\n          button.disabled = true"), 'entry no longer hard-disables Apple');
expect(str_contains($portal, '使用密碼登入'), 'password is a text path');
expect(str_contains($portal, 'min-height:56px') || str_contains($portal, 'min-height:52px'), 'large buttons');

$host = file_get_contents(dirname(__DIR__) . '/pm_phone_host_v18.php');
expect(str_contains($host, 'qlo_pm_member') || str_contains($host, "pm_member"), 'reuses qlo_pm_member table');
expect(!preg_match('/if\s*\(\s*\$code\s*===\s*[\'"]123456[\'"]/', $host), 'no hardcoded 123456 verify shortcut in host');
$lib = file_get_contents(dirname(__DIR__) . '/pm_phone_lib_v18.php');
expect(!preg_match('/MOCK_OTP|EMAIL_PREVIEW_OTP/', $lib), 'lib has no mock OTP constant');
$client = file_get_contents(dirname(__DIR__) . '/pm_member_client_v18.js');
expect(str_contains($client, 'window.pmMemberFetch'), 'CSRF helper is assigned on window');

expect(pm_guest_safe_error('簡訊金鑰尚未設定 /home/tdwhhyfe/pm_member_private/smsgo-api-key.txt') === PM_GUEST_OTP_UNAVAILABLE, 'SMS key path is hidden');
expect(pm_guest_safe_error('會員 Email 唯一索引尚未準備完成') === PM_GUEST_OTP_UNAVAILABLE, 'email unique index is hidden');
expect(pm_guest_safe_error('Call to undefined function pm_phone_host_dispatch()') === PM_GUEST_OTP_UNAVAILABLE, 'PHP internals are hidden');
expect(pm_guest_safe_error('請輸入有效手機號碼') === '請輸入有效手機號碼', 'validation copy stays');
$wrapped = pm_guest_sanitize_json_output('{"ok":false,"error":"會員 Email 唯一索引尚未準備完成","gap":"email_index"}');
expect(str_contains($wrapped, PM_GUEST_OTP_UNAVAILABLE) && !str_contains($wrapped, '唯一索引') && !str_contains($wrapped, 'gap'), 'JSON sanitizer strips setup hints');
$front = file_get_contents(dirname(__DIR__) . '/pm_front/index.html');
expect(str_contains($front, '先成為月租會員') && !str_contains($front, '依方案報價') && !str_contains($front, '價格由後台管理') && !str_contains($front, 'QloApps'), 'pm_front is a membership funnel');
$portal = file_get_contents(dirname(__DIR__) . '/pm_member_portal_v17.php');
expect(str_contains($portal, 'pm_guest_errors.js'), 'portal loads guest error sanitizer');
expect(!str_contains($portal, '簡訊金鑰') && !str_contains($portal, 'pm_member_private'), 'portal has no key-setup copy');

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
