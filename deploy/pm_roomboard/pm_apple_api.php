<?php
/**
 * Apple 登入 API：只以 apple_sub 找或建會員。Hide My Email 不擋註冊。
 */

require_once __DIR__ . '/pm_member_identity_lib.php';
require_once __DIR__ . '/pm_member_identity_api.php';

pm_identity_session_start();
$payload = pm_identity_request_payload();
$action = (string) ($payload['action'] ?? '');
$clientId = pm_identity_apple_client_id();

if ($action === 'prepare' || $action === '') {
    if ($clientId === '') {
        pm_identity_json(['ok' => false, 'error' => 'Apple 登入尚未啟用。請在主機設定 Apple Service ID。'], 503);
        exit;
    }
    $_SESSION['pm_identity_apple_nonce'] = bin2hex(random_bytes(16));
    $redirect = pm_identity_apple_redirect_uri();
    $url = 'https://appleid.apple.com/auth/authorize?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'response_type' => 'code id_token',
        'response_mode' => 'form_post',
        'scope' => 'name email',
        'nonce' => $_SESSION['pm_identity_apple_nonce'],
    ]);
    pm_identity_json([
        'ok' => true,
        'client_id' => $clientId,
        'url' => $url,
        'redirect_uri' => $redirect,
        'nonce' => $_SESSION['pm_identity_apple_nonce'],
        'csrf' => pm_identity_csrf_token(),
        'logged_in' => pm_identity_logged_in_user_id() > 0,
        'bind_prompt' => pm_identity_logged_in_user_id() > 0 ? pm_identity_msg('confirm_bind_apple') : null,
        'relay_note' => pm_identity_msg('apple_relay_ok'),
    ]);
    exit;
}

if ($action === 'login' || $action === 'bind' || $action === 'callback') {
    if (!pm_identity_csrf_ok((string) ($payload['csrf'] ?? $_POST['state'] ?? ''))) {
        // Apple form_post 用 state 帶 CSRF
        if (empty($payload['id_token']) && empty($_POST['id_token'])) {
            pm_identity_json(['ok' => false, 'error' => pm_identity_msg('csrf')], 403);
            exit;
        }
    }
    $payload['provider'] = 'apple';
    $payload['id_token'] = $payload['id_token'] ?? ($_POST['id_token'] ?? '');
    $payload['confirm_bind'] = !empty($payload['confirm_bind']) || $action === 'bind';
    $result = pm_identity_action_oauth($payload, $action === 'bind');
    pm_identity_json(pm_identity_decorate($result), !empty($result['ok']) ? 200 : 400);
    exit;
}

pm_identity_json(['ok' => false, 'error' => pm_identity_msg('unknown_action')], 400);

function pm_identity_apple_redirect_uri()
{
    $fromEnv = pm_identity_read_secret('apple-redirect-uri.txt', ['APPLE_REDIRECT_URI']);
    if ($fromEnv !== '') {
        return $fromEnv;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $host = $_SERVER['HTTP_HOST'] ?? 'seasideresort.com.tw';
    return ($https ? 'https://' : 'http://') . $host . dirname($_SERVER['SCRIPT_NAME'] ?? '') . '/pm_apple_callback.php';
}
