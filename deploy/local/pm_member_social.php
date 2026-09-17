<?php
/**
 * Local preview only. Do NOT upload over live pm_member_social.php.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (str_contains(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), 'seasideresort.com')) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => '這是本機預覽檔，不可覆蓋正式 OAuth。'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $provider = strtolower((string)($_POST['provider'] ?? ''));
    if ($provider === 'apple') {
        echo json_encode([
            'ok' => false,
            'error' => '本機預覽：Apple 鈕已可點。正式站會導向 appleid.apple.com，只用 apple_sub 認人（不把 Email 當主鍵）。請在主機放 apple-service-id.txt。',
            'identity' => 'apple_sub',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => '本機預覽不開啟正式 Google／LINE。請用手機簡訊或 Email。'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok' => true, 'providers' => ['google' => true, 'apple' => true, 'line' => true]], JSON_UNESCAPED_UNICODE);
