<?php
/**
 * 給現有 pm_member_api_v17.php 加 4 行即可把身分 API 接上同一支 URL：
 *
 *   if (is_file(__DIR__ . '/pm_member_identity_bridge.php')) {
 *       require_once __DIR__ . '/pm_member_identity_bridge.php';
 *       if (function_exists('pm_identity_bridge_dispatch') && pm_identity_bridge_dispatch()) { return; }
 *   }
 *
 * 未加入這段時，請改打 pm_member_identity_api.php。
 */

require_once __DIR__ . '/pm_member_identity_lib.php';
require_once __DIR__ . '/pm_smsgo_identity.php';
require_once __DIR__ . '/pm_member_identity_api.php';

function pm_identity_bridge_actions()
{
    return [
        'request_phone_login_code', 'request_sms_login_code', 'request_phone_code',
        'verify_phone_login_code', 'verify_sms_login_code', 'verify_phone_code',
        'oauth_login', 'oauth_bind', 'social_login',
        'bind_google', 'bind_apple', 'bind_phone',
        'sms_verified_upsert', 'identity_status',
    ];
}

function pm_identity_bridge_dispatch()
{
    $payload = pm_identity_request_payload();
    $action = (string) ($payload['action'] ?? '');
    if (!in_array($action, pm_identity_bridge_actions(), true)) {
        return false;
    }
    $serverOk = pm_identity_server_token_ok();
    $csrf = pm_identity_request_csrf($payload);
    if (!$serverOk && $csrf !== '' && !pm_identity_csrf_ok($csrf)) {
        // 與 v17 同一頁的 CSRF 可能不同 session key；若 v17 已驗證過，允許繼續。
        $v17ok = !empty($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $csrf);
        if (!$v17ok) {
            pm_identity_json(['ok' => false, 'error' => pm_identity_msg('csrf')], 403);
            return true;
        }
    }
    try {
        $result = pm_identity_handle_action($action, $payload, $serverOk);
        pm_identity_json($result, !empty($result['ok']) ? 200 : 400);
    } catch (Throwable $e) {
        pm_identity_json(['ok' => false, 'error' => '系統暫時發生錯誤，請稍後再試。'], 500);
    }
    return true;
}
