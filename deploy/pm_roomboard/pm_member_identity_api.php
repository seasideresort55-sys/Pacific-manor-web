<?php
/**
 * 會員身分 API（正式 qlo_pm_member）。
 * GET：狀態 + CSRF。POST：Apple／Google／手機 登入與綁定。
 *
 * 建議上傳到 /booking/pm_roomboard/pm_member_identity_api.php
 */

require_once __DIR__ . '/pm_member_identity_lib.php';
require_once __DIR__ . '/pm_member_identity_hooks.php';
require_once __DIR__ . '/pm_smsgo_identity.php';

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    pm_identity_dispatch_request();
}

function pm_identity_dispatch_request()
{
    pm_identity_session_start();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET' || $method === 'HEAD' || $method === 'OPTIONS') {
        pm_identity_json(pm_identity_status_payload());
        return;
    }
    if ($method !== 'POST') {
        pm_identity_json(['ok' => false, 'error' => '請用正確方式送出。'], 405);
        return;
    }

    $payload = pm_identity_request_payload();
    $action = (string) ($payload['action'] ?? '');
    $serverOk = pm_identity_server_token_ok();
    if (!$serverOk && !pm_identity_csrf_ok(pm_identity_request_csrf($payload))) {
        pm_identity_json(['ok' => false, 'error' => pm_identity_msg('csrf')], 403);
        return;
    }

    try {
        $result = pm_identity_handle_action($action, $payload, $serverOk);
        $http = !empty($result['ok']) ? 200 : 400;
        if (($result['error'] ?? '') === pm_identity_msg('csrf')) {
            $http = 403;
        }
        pm_identity_json($result, $http);
    } catch (Throwable $e) {
        pm_identity_json(['ok' => false, 'error' => '系統暫時發生錯誤，請稍後再試。'], 500);
    }
}

function pm_identity_handle_action($action, array $payload, $serverOk = false)
{
    switch ($action) {
        case 'status':
        case 'identity_status':
            return pm_identity_status_payload();

        case 'logout':
            pm_identity_logout_user();
            return ['ok' => true, 'message' => '已登出。', 'logged_in' => false, 'member' => null];

        case 'request_phone_login_code':
        case 'request_sms_login_code':
        case 'request_phone_code':
            return pm_identity_action_request_phone($payload);

        case 'verify_phone_login_code':
        case 'verify_sms_login_code':
        case 'verify_phone_code':
            return pm_identity_action_verify_phone($payload);

        case 'oauth_login':
        case 'social_login':
            return pm_identity_action_oauth($payload, false);

        case 'oauth_bind':
        case 'bind_google':
        case 'bind_apple':
            $payload['provider'] = $payload['provider'] ?? ($action === 'bind_apple' ? 'apple' : ($action === 'bind_google' ? 'google' : ''));
            return pm_identity_action_oauth($payload, true);

        case 'bind_phone':
            $payload['confirm_bind'] = 1;
            $payload['phone_verified'] = !empty($_SESSION['pm_identity_verified_phone']);
            if (empty($payload['phone']) && !empty($_SESSION['pm_identity_verified_phone'])) {
                $payload['phone'] = $_SESSION['pm_identity_verified_phone'];
            }
            return pm_identity_finish_phone($payload);

        case 'sms_verified_upsert':
            if (!$serverOk) {
                return ['ok' => false, 'error' => '請先重新登入會員'];
            }
            return pm_identity_server_phone_upsert($payload);

        case 'phone_status':
            return pm_phone_host_dispatch(['action' => 'phone_status']);

        default:
            return ['ok' => false, 'error' => pm_identity_msg('unknown_action'), 'unavailable' => true];
    }
}

function pm_identity_action_request_phone(array $payload)
{
    $issued = pm_identity_otp_issue($payload['phone'] ?? '');
    if (empty($issued['ok'])) {
        return $issued;
    }
    $sent = pm_smsgo_identity_send($issued['phone'], $issued['code']);
    if (empty($sent['ok'])) {
        unset($_SESSION['pm_identity_otp']);
        return ['ok' => false, 'error' => $sent['error'] ?? pm_identity_msg('sms_unavailable')];
    }
    return ['ok' => true, 'message' => pm_identity_msg('otp_sent')];
}

function pm_identity_action_verify_phone(array $payload)
{
    $phone = $payload['phone'] ?? '';
    $code = $payload['code'] ?? '';
    if (!pm_identity_otp_check($phone, $code)) {
        return ['ok' => false, 'error' => pm_identity_msg('otp_bad')];
    }
    $payload['phone'] = pm_identity_normalize_phone($phone);
    $payload['phone_verified'] = true;
    return pm_identity_finish_phone($payload);
}

function pm_identity_finish_phone(array $payload)
{
    $result = pm_identity_run_phone([
        'phone' => $payload['phone'] ?? '',
        'phone_verified' => !empty($payload['phone_verified']),
        'confirm_bind' => !empty($payload['confirm_bind']),
        'name' => $payload['name'] ?? '',
        'logged_in_user_id' => pm_identity_logged_in_user_id(),
    ]);
    return pm_identity_decorate($result);
}

function pm_identity_action_oauth(array $payload, $forceBind)
{
    $provider = strtolower((string) ($payload['provider'] ?? ''));
    if (!in_array($provider, ['google', 'apple'], true)) {
        return ['ok' => false, 'error' => '請選擇 Google 或 Apple。'];
    }
    $profile = null;
    $token = $payload['credential'] ?? $payload['id_token'] ?? $payload['token'] ?? '';
    if ($provider === 'google') {
        $nonce = $_SESSION['pm_identity_google_nonce'] ?? null;
        $profile = pm_identity_verify_google_id_token($token, $nonce);
    } else {
        $profile = pm_identity_verify_apple_id_token($token);
    }
    if (!$profile) {
        return ['ok' => false, 'error' => pm_identity_msg('bad_token')];
    }
    if ($forceBind && !pm_identity_logged_in_user_id()) {
        return ['ok' => false, 'error' => pm_identity_msg('need_login')];
    }
    $confirm = !empty($payload['confirm_bind']);
    $result = pm_identity_run_oauth([
        'provider' => $provider,
        'sub' => $profile['sub'],
        'email' => $profile['email'],
        'name' => $profile['name'] ?? ($payload['name'] ?? ''),
        'confirm_bind' => $confirm,
        'logged_in_user_id' => pm_identity_logged_in_user_id(),
    ]);
    return pm_identity_decorate($result);
}

function pm_identity_server_phone_upsert(array $payload)
{
    $phone = pm_identity_normalize_phone($payload['phone'] ?? $payload['identifier'] ?? '');
    if (!$phone) {
        return ['ok' => false, 'error' => pm_identity_msg('bad_phone')];
    }
    $result = pm_identity_run_phone([
        'phone' => $phone,
        'phone_verified' => true,
        'confirm_bind' => true,
        'name' => $payload['name'] ?? '',
        'logged_in_user_id' => 0,
    ]);
    $decorated = pm_identity_decorate($result);
    if (!empty($decorated['ok']) && !empty($decorated['member'])) {
        $decorated['member']['id'] = (string) $decorated['member']['user_id'];
        $decorated['member']['member_id'] = (string) $decorated['member']['user_id'];
    }
    return $decorated;
}

function pm_identity_decorate(array $result)
{
    if (!empty($result['ok']) && !empty($result['user_id']) && pm_identity_bootstrap_qlo()) {
        $row = pm_identity_load_member_by_id((int) $result['user_id']);
        if ($row) {
            $result['member'] = pm_identity_public_member($row);
            $result['logged_in'] = true;
        }
    }
    return $result;
}
