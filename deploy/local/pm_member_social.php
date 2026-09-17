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
    echo json_encode(['ok' => false, 'error' => '本機預覽不開啟正式 Google／LINE。請用手機簡訊或 Email。'], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok' => true, 'providers' => ['google' => true, 'apple' => false, 'line' => true]], JSON_UNESCAPED_UNICODE);
