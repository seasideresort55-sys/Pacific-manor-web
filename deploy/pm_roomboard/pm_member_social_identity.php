<?php
/**
 * 身分綁定用的社群開關。不要覆蓋線上 pm_member_social.php（LINE 仍走原檔）。
 * 入口 JS（pm_member_identity.js）會讀這支，決定 Google／Apple 按鈕。
 */
require_once __DIR__ . '/pm_member_identity_lib.php';

pm_identity_session_start();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    pm_identity_json([
        'ok' => true,
        'providers' => [
            'google' => pm_identity_google_client_id() !== '',
            'apple' => pm_identity_apple_client_id() !== '',
            'line' => true,
        ],
        'pages' => [
            'google' => 'pm_google_login.php',
            'apple' => 'pm_apple_login.php',
            'bind' => 'pm_member_bind.php',
        ],
        'relay_note' => pm_identity_msg('apple_relay_ok'),
    ]);
    exit;
}

$payload = pm_identity_request_payload();
$provider = strtolower((string) ($payload['provider'] ?? ''));
if ($provider === 'google') {
    $id = pm_identity_google_client_id();
    if ($id === '') {
        pm_identity_json(['ok' => false, 'error' => 'Google 登入尚未啟用。'], 503);
        exit;
    }
    pm_identity_json(['ok' => true, 'url' => 'pm_google_login.php', 'mode' => 'page']);
    exit;
}
if ($provider === 'apple') {
    $id = pm_identity_apple_client_id();
    if ($id === '') {
        pm_identity_json(['ok' => false, 'error' => 'Apple 登入尚未啟用。'], 503);
        exit;
    }
    $redirect = pm_identity_read_secret('apple-redirect-uri.txt', ['APPLE_REDIRECT_URI']);
    if ($redirect === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $host = $_SERVER['HTTP_HOST'] ?? 'seasideresort.com.tw';
        $redirect = ($https ? 'https://' : 'http://') . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/pm_apple_callback.php';
    }
    $_SESSION['pm_identity_apple_nonce'] = bin2hex(random_bytes(16));
    $url = 'https://appleid.apple.com/auth/authorize?' . http_build_query([
        'client_id' => $id,
        'redirect_uri' => $redirect,
        'response_type' => 'code id_token',
        'response_mode' => 'form_post',
        'scope' => 'name email',
        'nonce' => $_SESSION['pm_identity_apple_nonce'],
    ]);
    pm_identity_json(['ok' => true, 'url' => $url]);
    exit;
}
pm_identity_json(['ok' => false, 'error' => '請改從會員入口使用 LINE，或聯絡客服。']);
