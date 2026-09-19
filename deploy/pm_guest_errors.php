<?php
/**
 * Guest-safe API / OTP error copy.
 *
 * Operators: never echo host paths, key filenames, or index/setup hints
 * to the browser. Live leaks come from pm_smsgo_identity.php (SMS key path)
 * and pm_member_email_code.php (email unique-index). Setup + product
 * conflict live in OPERATOR_OTP.md only. Do not overwrite pm_smsgo_adapter.php.
 */
declare(strict_types=1);

const PM_GUEST_OTP_UNAVAILABLE = '暫時無法寄送驗證碼，請稍後再試或改用其他方式';
const PM_GUEST_SERVICE_UNAVAILABLE = '會員服務暫時無法使用，請稍後再試。';

function pm_guest_is_leaky_error(string $msg): bool
{
    if ($msg === '') {
        return false;
    }
    $patterns = [
        '/pm_member_private/i',
        '/\/home\/[^\s"\'<>]+/',
        '/smsgo-api-key/i',
        '/SMSGO_[A-Z0-9_]+/',
        '/MEMBER_API_[A-Z0-9_]+/',
        '/簡訊金鑰/',
        '/唯一索引/',
        '/尚未準備完成/',
        '/Call to undefined/i',
        '/undefined function/i',
        '/runtime\.php/i',
        '/API Key/i',
        '/adapter/i',
        '/QloApps|QoApps/i',
        '/允許清單/',
        '/NCC 署名/',
        '/請管理員/',
        '/尚未初始化/',
        '/尚未設定/',
        '/qlo_pm_member/i',
        '/enabled\s*=/i',
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $msg)) {
            return true;
        }
    }
    return (bool)preg_match('/(?:^|[\s"\'=(])(?:\/[A-Za-z0-9._-]+){2,}\.(?:php|txt|js|inc)\b/i', $msg);
}

function pm_guest_is_safe_user_error(string $msg): bool
{
    $needles = [
        '請輸入有效',
        '請先勾選',
        '請先寄送',
        '請先送出',
        '請先同意',
        '驗證碼不正確',
        '驗證碼已過期',
        '驗證碼已失效',
        '秒後可再寄送',
        '這個手機號碼已綁定',
        '頁面驗證已失效',
        '首次建立會員',
        '請閱讀並同意',
        '嘗試次數過多',
    ];
    foreach ($needles as $needle) {
        if (str_contains($msg, $needle)) {
            return true;
        }
    }
    return false;
}

function pm_guest_safe_error(?string $raw, string $fallback = PM_GUEST_OTP_UNAVAILABLE): string
{
    $msg = trim((string)$raw);
    if ($msg === '') {
        return $fallback;
    }
    if (pm_guest_is_safe_user_error($msg) && !pm_guest_is_leaky_error($msg)) {
        return $msg;
    }
    if (pm_guest_is_leaky_error($msg)) {
        return $fallback;
    }
    return $msg;
}

/**
 * Sanitize a JSON payload (string or array) before it reaches the browser.
 * Strips leaky error/message fields and operator-only keys.
 */
function pm_guest_sanitize_payload(array $payload): array
{
    foreach (['error', 'message'] as $key) {
        if (isset($payload[$key]) && is_string($payload[$key])) {
            $payload[$key] = pm_guest_safe_error($payload[$key]);
        }
    }
    unset($payload['gap'], $payload['missing'], $payload['blockedGates'], $payload['requiredEnv']);
    if (isset($payload['preview_code']) && function_exists('pm_phone_is_live_host') && pm_phone_is_live_host()) {
        unset($payload['preview_code']);
    }
    return $payload;
}

function pm_guest_sanitize_json_output(string $raw): string
{
    $trim = trim($raw);
    if ($trim === '') {
        return $raw;
    }
    $decoded = json_decode($trim, true);
    if (!is_array($decoded)) {
        if (pm_guest_is_leaky_error($trim)) {
            return json_encode(['ok' => false, 'error' => PM_GUEST_OTP_UNAVAILABLE], JSON_UNESCAPED_UNICODE);
        }
        return $raw;
    }
    return json_encode(pm_guest_sanitize_payload($decoded), JSON_UNESCAPED_UNICODE);
}
