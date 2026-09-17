<?php
/**
 * Google 登入 API：只以 google_sub 找或建會員。Email 只當輔助欄位。
 * 覆蓋線上「Google 登入尚未啟用」的同名檔（部署時替換 pm_google_api.php）。
 */

require_once __DIR__ . '/pm_member_identity_lib.php';
require_once __DIR__ . '/pm_member_identity_api.php';

pm_identity_session_start();
$payload = pm_identity_request_payload();
$action = (string) ($payload['action'] ?? '');

if ($action === 'prepare' || $action === '') {
    $clientId = pm_identity_google_client_id();
    if ($clientId === '') {
        pm_identity_json(['ok' => false, 'error' => 'Google 登入尚未啟用。請在主機設定 Google Client ID。'], 503);
        exit;
    }
    $_SESSION['pm_identity_google_nonce'] = bin2hex(random_bytes(16));
    pm_identity_json([
        'ok' => true,
        'client_id' => $clientId,
        'nonce' => $_SESSION['pm_identity_google_nonce'],
        'csrf' => pm_identity_csrf_token(),
        'logged_in' => pm_identity_logged_in_user_id() > 0,
        'bind_prompt' => pm_identity_logged_in_user_id() > 0 ? pm_identity_msg('confirm_bind_google') : null,
    ]);
    exit;
}

if ($action === 'login' || $action === 'bind') {
    if (!pm_identity_csrf_ok((string) ($payload['csrf'] ?? ''))) {
        pm_identity_json(['ok' => false, 'error' => pm_identity_msg('csrf')], 403);
        exit;
    }
    $payload['provider'] = 'google';
    $payload['confirm_bind'] = !empty($payload['confirm_bind']) || $action === 'bind';
    $result = pm_identity_action_oauth($payload, $action === 'bind');
    pm_identity_json(pm_identity_decorate($result), !empty($result['ok']) ? 200 : 400);
    exit;
}

pm_identity_json(['ok' => false, 'error' => pm_identity_msg('unknown_action')], 400);
