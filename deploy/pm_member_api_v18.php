<?php
/**
 * Member API v18 — phone-first login host + existing v17 actions.
 *
 * Upload next to pm_member_api_v17.php. This file:
 * 1. Defines pm_phone_host_dispatch() before v17 runs (live v17 already calls it).
 * 2. Requires v17 so Email / password / apply / status stay authoritative.
 * 3. Falls back to a local preview router when v17 is not on disk.
 */
declare(strict_types=1);

require_once __DIR__ . '/pm_phone_host_v18.php';
require_once __DIR__ . '/pm_guest_errors.php';

$v17 = __DIR__ . '/pm_member_api_v17.php';
if (is_file($v17) && realpath($v17) !== realpath(__FILE__)) {
    ob_start();
    require $v17;
    echo pm_guest_sanitize_json_output((string)ob_get_clean());
    return;
}

pm_phone_boot_session();

if (!isset($_SESSION['pm_csrf']) || !is_string($_SESSION['pm_csrf']) || strlen($_SESSION['pm_csrf']) < 32) {
    $_SESSION['pm_csrf'] = bin2hex(random_bytes(32));
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET' || $method === 'HEAD') {
    pm_api_v18_json([
        'ok' => true,
        'csrf_token' => $_SESSION['pm_csrf'],
        'installed' => true,
        'logged_in' => (bool)pm_phone_current_member_id(),
        'member' => pm_phone_current_member_id() ? ['id_member' => pm_phone_current_member_id()] : null,
        'applications' => [],
        'rooms' => ['15坪海景一大床房', '16坪海景雙大床房', '17坪三床海景房'],
        'api' => 'pm_member_api_v18_preview',
        'phone_login' => true,
    ]);
}

$csrfHeader = (string)($_SERVER['HTTP_X_PM_CSRF'] ?? '');
if (!hash_equals((string)$_SESSION['pm_csrf'], $csrfHeader)) {
    http_response_code(403);
    pm_api_v18_json(['ok' => false, 'error' => '頁面驗證已失效，請重新整理後再試']);
}

$input = pm_phone_collect_input();
$action = (string)($input['action'] ?? '');

if (str_starts_with($action, 'phone_')) {
    pm_phone_host_dispatch($action, $input);
}

if ($action === 'status') {
    $id = pm_phone_current_member_id();
    $member = $id ? (pm_phone_find_member_by_id($id) ?: ($_SESSION['member'] ?? ['id_member' => $id])) : null;
    pm_api_v18_json([
        'ok' => true,
        'csrf_token' => $_SESSION['pm_csrf'],
        'installed' => true,
        'logged_in' => (bool)$id,
        'member' => $member ? pm_phone_public_member($member) : null,
        'applications' => [],
        'rooms' => ['15坪海景一大床房', '16坪海景雙大床房', '17坪三床海景房'],
    ]);
}

if ($action === 'logout') {
    $_SESSION = [];
    pm_api_v18_json(['ok' => true, 'message' => '已登出']);
}

if ($action === 'request_email_code') {
    $email = strtolower(trim((string)($input['email'] ?? '')));
    if (!pm_phone_is_valid_email($email)) {
        pm_api_v18_json(['ok' => false, 'error' => '請輸入有效電子郵件']);
    }
    $code = pm_phone_random_otp();
    $_SESSION['pm_email_otp'] = pm_phone_issue_otp($email, $code);
    $_SESSION['pm_email_otp']['phone'] = $email;
    $payload = ['ok' => true, 'message' => '驗證信已送出。本機預覽會顯示驗證碼；正式站走 v17 寄信。', 'retry_after' => 60];
    if (!pm_phone_is_live_host()) {
        $payload['preview_code'] = $code;
    }
    pm_api_v18_json($payload);
}

if ($action === 'verify_email_code') {
    $email = strtolower(trim((string)($input['email'] ?? $_SESSION['pm_email_otp']['phone'] ?? '')));
    $pending = $_SESSION['pm_email_otp'] ?? null;
    if (!is_array($pending)) {
        pm_api_v18_json(['ok' => false, 'error' => '請先寄送驗證碼。']);
    }
    $match = pm_phone_otp_match($pending, $email, (string)($input['code'] ?? ''));
    if ($match !== 'verified') {
        pm_api_v18_json(['ok' => false, 'error' => '驗證碼不正確或已過期。']);
    }
    if (!pm_phone_truthy($input['consent'] ?? false)) {
        pm_api_v18_json(['ok' => false, 'next_step' => 'signup_consent', 'error' => '首次建立會員請先同意資料使用說明']);
    }
    $member = [
        'id_member' => 2001,
        'name' => strtok($email, '@') ?: '會員',
        'email' => $email,
        'phone' => '',
        'member_level' => 'trial',
        'qualification_state' => 'pending',
    ];
    pm_phone_attach_session($member);
    unset($_SESSION['pm_email_otp']);
    pm_api_v18_json(['ok' => true, 'message' => '電子郵件驗證完成。', 'member' => $member]);
}

if ($action === 'login') {
    pm_api_v18_json(['ok' => false, 'error' => '本機預覽請用手機簡訊或電子郵件驗證碼。']);
}

pm_api_v18_json(['ok' => false, 'error' => '未知操作']);

function pm_api_v18_json(array $payload): never
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode(pm_guest_sanitize_payload($payload), JSON_UNESCAPED_UNICODE);
    exit;
}
