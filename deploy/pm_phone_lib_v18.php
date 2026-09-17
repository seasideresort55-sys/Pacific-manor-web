<?php
/**
 * Pure helpers for Pacific Manor member phone login (v18).
 * No I/O. Safe to unit-test. Live path never accepts a fixed preview OTP.
 */
declare(strict_types=1);

const PM_PHONE_OTP_TTL = 600;
const PM_PHONE_RESEND_SECONDS = 60;
const PM_PHONE_OTP_LENGTH = 6;
const PM_PHONE_MAX_ATTEMPTS = 5;
const PM_SMSGO_SEND_URL = 'https://www.smsgo.com.tw/sms_gw/sendsms.aspx';
const PM_SMSGO_TEMPLATE = '太平洋莊園手機驗證碼：{code}，10分鐘內有效，請勿提供他人。';

function pm_phone_is_live_host(?string $host = null): bool
{
    $host = strtolower($host ?? (string)($_SERVER['HTTP_HOST'] ?? ''));
    return $host !== '' && (str_contains($host, 'seasideresort.com.tw') || str_contains($host, 'seasideresort.com'));
}

function pm_phone_normalize(string $input): ?string
{
    $digits = preg_replace('/\D+/', '', $input) ?? '';
    if (str_starts_with($digits, '886') && strlen($digits) >= 12) {
        $digits = '0' . substr($digits, 3, 9);
    }
    if (preg_match('/^09\d{8}$/', $digits)) {
        return $digits;
    }
    return null;
}

function pm_phone_e164(string $local): ?string
{
    $local = pm_phone_normalize($local) ?? '';
    return $local === '' ? null : '+886' . substr($local, 1);
}

function pm_phone_is_valid_email(string $input): bool
{
    return (bool)filter_var(trim($input), FILTER_VALIDATE_EMAIL);
}

function pm_phone_random_otp(int $length = PM_PHONE_OTP_LENGTH): string
{
    $max = (10 ** $length) - 1;
    return str_pad((string)random_int(0, $max), $length, '0', STR_PAD_LEFT);
}

function pm_phone_hash_otp(string $code, string $salt): string
{
    return hash_hmac('sha256', $code, $salt);
}

function pm_phone_new_challenge(): string
{
    return bin2hex(random_bytes(16));
}

function pm_phone_issue_otp(string $phone, string $code, ?string $challenge = null): array
{
    $salt = bin2hex(random_bytes(16));
    return [
        'phone' => $phone,
        'challenge' => $challenge ?: pm_phone_new_challenge(),
        'hash' => pm_phone_hash_otp($code, $salt),
        'salt' => $salt,
        'expires_at' => time() + PM_PHONE_OTP_TTL,
        'resend_at' => time() + PM_PHONE_RESEND_SECONDS,
        'attempts' => 0,
        'gateway' => 'smsgo',
    ];
}

function pm_phone_otp_match(array $pending, string $phone, string $code): string
{
    if (($pending['phone'] ?? '') !== $phone) {
        return 'invalid_request';
    }
    if (time() > (int)($pending['expires_at'] ?? 0)) {
        return 'expired';
    }
    if ((int)($pending['attempts'] ?? 0) >= PM_PHONE_MAX_ATTEMPTS) {
        return 'attempts_exhausted';
    }
    $code = trim($code);
    if (!preg_match('/^\d{' . PM_PHONE_OTP_LENGTH . '}$/', $code)) {
        return 'invalid_request';
    }
    $expected = (string)($pending['hash'] ?? '');
    $salt = (string)($pending['salt'] ?? '');
    if ($expected === '' || $salt === '' || !hash_equals($expected, pm_phone_hash_otp($code, $salt))) {
        return 'rejected';
    }
    return 'verified';
}

function pm_phone_collect_input(array $args = []): array
{
    $json = [];
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }

    $fromArgs = [];
    foreach ($args as $arg) {
        if (is_string($arg) && $arg !== '') {
            if (!isset($fromArgs['action']) && (str_starts_with($arg, 'phone_') || in_array($arg, ['status', 'login', 'logout'], true))) {
                $fromArgs['action'] = $arg;
            }
        } elseif (is_array($arg)) {
            $fromArgs = array_merge($fromArgs, $arg);
        } elseif (is_object($arg)) {
            $fromArgs = array_merge($fromArgs, get_object_vars($arg));
        }
    }

    $input = array_merge($_GET, $_POST, $json, $fromArgs);
    if (!isset($input['action']) || $input['action'] === '') {
        $input['action'] = (string)($input['op'] ?? '');
    }
    return $input;
}

function pm_phone_truthy($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $value = strtolower(trim((string)$value));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function pm_phone_smsgo_dstaddr(string $local): string
{
    return $local;
}

function pm_phone_sms_body(string $code, ?string $template = null): string
{
    $template = $template ?: PM_SMSGO_TEMPLATE;
    if (substr_count($template, '{code}') !== 1) {
        $template = PM_SMSGO_TEMPLATE;
    }
    return str_replace('{code}', $code, $template);
}

function pm_phone_parse_smsgo_response(int $http, string $body): array
{
    $unknown = ['state' => 'unknown', 'message_id' => null, 'statuscode' => -10, 'statusstr' => ''];
    if ($http !== 200 || strlen($body) > 32768) {
        return $unknown;
    }
    $node = null;
    $text = trim($body);
    if (str_starts_with($text, '{')) {
        $parsed = json_decode($text, true);
        if (is_array($parsed)) {
            $node = (isset($parsed['result']) && is_array($parsed['result'])) ? $parsed['result'] : $parsed;
        }
    }
    if (!is_array($node)) {
        $fields = [];
        foreach (preg_split('/[\r\n&]+/', $text) ?: [] as $line) {
            $index = strpos($line, '=');
            if ($index === false || $index < 1) {
                continue;
            }
            $fields[strtolower(trim(substr($line, 0, $index)))] = trim(substr($line, $index + 1));
        }
        if ($fields) {
            $node = $fields;
        }
    }
    if (!is_array($node)) {
        return $unknown + ['statusstr' => substr($text, 0, 160)];
    }
    $code = (string)($node['statuscode'] ?? '');
    $id = trim((string)($node['msgid'] ?? ''));
    $messageId = preg_match('/^[0-9]{1,96}$/', $id) ? $id : null;
    $statuscode = is_numeric($code) ? (int)$code : -10;
    $statusstr = trim((string)($node['statusstr'] ?? ''));
    if ($code === '0' && $messageId) {
        return ['state' => 'accepted', 'message_id' => $messageId, 'statuscode' => 0, 'statusstr' => $statusstr];
    }
    $rejected = [-1, -2, -3, -5, -8, -9, -10, -11, -12, -13, -14, -15, -16, -17, -18, -19, -20, -21, -22, -23, -24, -25, -30];
    if (in_array($statuscode, $rejected, true)) {
        return ['state' => 'rejected', 'message_id' => null, 'statuscode' => $statuscode, 'statusstr' => $statusstr];
    }
    return ['state' => 'unknown', 'message_id' => $messageId, 'statuscode' => $statuscode, 'statusstr' => $statusstr];
}

function pm_phone_smsgo_user_message(int $statuscode): string
{
    return match ($statuscode) {
        0 => '成功',
        -1 => '簡訊閘道參數格式不正確。',
        -2 => 'SMS Go 帳號、API Key 或來源 IP 驗證失敗。',
        -3 => '尚未設定 SMS Go 帳號或 API Key。',
        -5 => '手機號碼格式不被簡訊閘道接受。',
        -8 => 'SMS Go 點數不足，請先加值。',
        -10 => '簡訊發送失敗。',
        -15 => '此伺服器 IP 尚未加入 SMS Go 允許清單。',
        -16 => 'SMS Go 尚未開通 API。',
        -21 => '已達發送上限，請稍後再試。',
        -23 => '簡訊缺少 NCC 署名。',
        default => '簡訊服務這次沒有完成，請稍後再試，或改用 Email。',
    };
}
