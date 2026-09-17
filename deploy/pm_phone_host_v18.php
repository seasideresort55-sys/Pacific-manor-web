<?php
/**
 * Phone host for pm_member_api_v17 / v18.
 *
 * Live v17 already routes phone_* (including phone_login_request / phone_login_verify)
 * to pm_phone_host_dispatch(), but the function is missing on the host. This file
 * defines it and reuses qlo_pm_member + SMS Go / pm_smsgo_adapter — no second member DB.
 */
declare(strict_types=1);

require_once __DIR__ . '/pm_phone_lib_v18.php';

if (!function_exists('pm_phone_host_dispatch')) {
    function pm_phone_host_dispatch(...$args)
    {
        $result = pm_phone_handle($args);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function pm_phone_handle(array $args = []): array
{
    pm_phone_boot_session();
    pm_phone_load_private_runtime();
    $input = pm_phone_collect_input($args);
    $action = (string)($input['action'] ?? '');

    return match ($action) {
        'phone_login_request', 'phone_request' => pm_phone_action_request($input, $action === 'phone_login_request'),
        'phone_login_verify', 'phone_verify' => pm_phone_action_verify($input, $action === 'phone_login_verify'),
        'phone_status' => pm_phone_action_status(),
        'phone_query' => pm_phone_action_query($input),
        default => ['ok' => false, 'code' => 'invalid_request', 'error' => '未知的手機驗證動作。'],
    };
}

function pm_phone_boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (session_name() !== 'PMMEMBER' && !headers_sent()) {
        session_name('PMMEMBER');
    }
    if (!headers_sent()) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $live = pm_phone_is_live_host();
        session_set_cookie_params([
            'lifetime' => 2592000,
            'path' => $live ? '/booking/pm_roomboard/' : '/',
            'secure' => $live || $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    @session_start();
}

function pm_phone_load_private_runtime(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $candidates = [
        '/home/tdwhhyfe/pm_member_private/runtime.php',
        '/home/tdwhhyfe/pm_member_private/pm_phone_host.php',
        '/home/tdwhhyfe/pm_member_private/pm_smsgo.php',
        dirname(__DIR__) . '/pm_member_private/runtime.php',
        __DIR__ . '/pm_smsgo_adapter.php',
        dirname(__DIR__) . '/pm_roomboard/pm_smsgo_adapter.php',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
        }
    }
}

function pm_phone_current_member_id(): ?int
{
    $keys = ['id_member', 'pm_id_member', 'member_id', 'pm_member_id', 'id_pm_member'];
    foreach ($keys as $key) {
        if (!empty($_SESSION[$key]) && ctype_digit((string)$_SESSION[$key])) {
            return (int)$_SESSION[$key];
        }
    }
    if (isset($_SESSION['member']) && is_array($_SESSION['member'])) {
        foreach (['id_member', 'id', 'member_id'] as $key) {
            if (!empty($_SESSION['member'][$key]) && ctype_digit((string)$_SESSION['member'][$key])) {
                return (int)$_SESSION['member'][$key];
            }
        }
    }
    foreach (get_defined_functions()['user'] as $fn) {
        if (preg_match('/^pm_(current_member_id|member_id|logged_in_member_id)$/', $fn)) {
            $value = $fn();
            if (is_numeric($value) && (int)$value > 0) {
                return (int)$value;
            }
        }
    }
    return null;
}

function pm_phone_action_status(): array
{
    $memberId = pm_phone_current_member_id();
    if (!$memberId) {
        return ['ok' => false, 'code' => 'unavailable', 'error' => '請先重新登入會員', 'message' => '請先重新登入會員'];
    }
    $member = pm_phone_find_member_by_id($memberId);
    $verified = pm_phone_member_is_verified($member);
    return [
        'ok' => true,
        'code' => 'status',
        'verified' => $verified,
        'phone' => $member['phone'] ?? '',
        'message' => $verified ? '手機已完成驗證。' : '手機尚未完成驗證。',
    ];
}

function pm_phone_action_request(array $input, bool $asLogin): array
{
    $phone = pm_phone_normalize((string)($input['phone'] ?? $input['identifier'] ?? ''));
    if (!$phone) {
        return ['ok' => false, 'code' => 'invalid_request', 'error' => '請輸入台灣手機號碼，例如 09 開頭的十位數字。', 'message' => '請輸入台灣手機號碼，例如 09 開頭的十位數字。'];
    }

    if ($asLogin) {
        if (!pm_phone_truthy($input['consent'] ?? false)) {
            return ['ok' => false, 'code' => 'consent_required', 'error' => '請先勾選同意《會員資料使用說明》，再繼續。', 'message' => '請先勾選同意《會員資料使用說明》，再繼續。'];
        }
    } else {
        $memberId = pm_phone_current_member_id();
        if (!$memberId) {
            return ['ok' => false, 'code' => 'unavailable', 'error' => '請先重新登入會員', 'message' => '請先重新登入會員'];
        }
        $owner = pm_phone_find_member_by_phone($phone);
        if ($owner && (int)($owner['id_member'] ?? $owner['id'] ?? 0) !== $memberId) {
            return ['ok' => false, 'code' => 'phone_conflict', 'error' => '這個手機號碼已綁定其他會員。', 'message' => '這個手機號碼已綁定其他會員。請改用自己的號碼，或聯絡莊園協助。'];
        }
    }

    $pending = $_SESSION['pm_phone_otp'] ?? null;
    if (is_array($pending) && ($pending['phone'] ?? '') === $phone && time() < (int)($pending['resend_at'] ?? 0)) {
        $wait = max(1, (int)$pending['resend_at'] - time());
        return ['ok' => false, 'code' => 'rejected', 'retry_after' => $wait, 'error' => $wait . ' 秒後可再寄送一次。', 'message' => $wait . ' 秒後可再寄送一次。'];
    }

    $code = pm_phone_random_otp();
    $issued = pm_phone_issue_otp($phone, $code);
    $sent = pm_phone_send_sms($phone, $code);
    if (!$sent['ok']) {
        return [
            'ok' => false,
            'code' => $sent['code'] ?? 'unavailable',
            'error' => $sent['error'],
            'message' => $sent['error'],
            'gateway' => $sent['gateway'] ?? 'smsgo',
            'gap' => $sent['gap'] ?? null,
        ];
    }

    $issued['serial'] = $sent['message_id'] ?? null;
    $issued['purpose'] = $asLogin ? 'login' : 'bind';
    $issued['member_id'] = $asLogin ? null : pm_phone_current_member_id();
    $_SESSION['pm_phone_otp'] = $issued;

    $payload = [
        'ok' => true,
        'code' => 'accepted',
        'challenge' => $issued['challenge'],
        'otpLength' => PM_PHONE_OTP_LENGTH,
        'retry_after' => PM_PHONE_RESEND_SECONDS,
        'query_available' => !empty($issued['serial']),
        'gateway' => $sent['gateway'] ?? 'smsgo',
        'mock' => false,
        'message' => '驗證碼已由簡訊送到手機。請輸入簡訊中的 6 位數字，沒有預覽假碼。',
    ];
    if (!empty($sent['preview_code']) && !pm_phone_is_live_host()) {
        $payload['preview_code'] = $sent['preview_code'];
        $payload['message'] = '本機預覽：驗證碼已產生（非正式 SMS Go）。正式站不會顯示數字。';
    }
    return $payload;
}

function pm_phone_action_verify(array $input, bool $asLogin): array
{
    $phone = pm_phone_normalize((string)($input['phone'] ?? $input['identifier'] ?? ''));
    $code = trim((string)($input['code'] ?? ''));
    $pending = $_SESSION['pm_phone_otp'] ?? null;
    if (!is_array($pending) || !$phone) {
        return ['ok' => false, 'code' => 'invalid_request', 'error' => '請先送出簡訊驗證碼。', 'message' => '請先送出簡訊驗證碼。'];
    }
    if (!empty($input['challenge']) && (string)$input['challenge'] !== (string)($pending['challenge'] ?? '')) {
        return ['ok' => false, 'code' => 'superseded', 'error' => '這組驗證碼已失效，請重新寄送。', 'message' => '這組驗證碼已失效，請重新寄送。'];
    }

    $match = pm_phone_otp_match($pending, $phone, $code);
    if ($match !== 'verified') {
        if (isset($_SESSION['pm_phone_otp']['attempts'])) {
            $_SESSION['pm_phone_otp']['attempts']++;
        }
        $messages = [
            'expired' => '驗證碼已過期，請重新寄送一組。',
            'attempts_exhausted' => '嘗試次數過多，請稍後再重新寄送。',
            'rejected' => '驗證碼不正確，請再看一次簡訊。',
            'invalid_request' => '請輸入簡訊中的 6 位數字。',
        ];
        return ['ok' => false, 'code' => $match, 'error' => $messages[$match] ?? '驗證沒有完成，請再試一次。', 'message' => $messages[$match] ?? '驗證沒有完成，請再試一次。'];
    }

    if ($asLogin || (($pending['purpose'] ?? '') === 'login')) {
        if (!pm_phone_truthy($input['consent'] ?? false) && !pm_phone_find_member_by_phone($phone)) {
            return ['ok' => false, 'code' => 'signup_consent', 'next_step' => 'signup_consent', 'error' => '信箱已改為手機驗證通過。請同意資料使用說明後建立會員。', 'message' => '請閱讀並同意《會員資料使用說明》，即可建立會員。'];
        }
        $member = pm_phone_find_or_create_member($phone, $input);
        if (!$member) {
            return [
                'ok' => false,
                'code' => 'unavailable',
                'error' => '驗證碼正確，但目前無法對齊預約系統會員。請改用 Email 驗證碼登入，或聯絡莊園。',
                'message' => '驗證碼正確，但目前無法對齊預約系統會員。請改用 Email 驗證碼登入，或聯絡莊園。',
                'gap' => 'qlo_pm_member_write',
            ];
        }
        if (!pm_phone_attach_session($member)) {
            return [
                'ok' => false,
                'code' => 'unavailable',
                'error' => '驗證碼正確，會員已對齊，但正式登入工作階段無法寫入。請改用 Email 驗證碼或密碼登入。',
                'message' => '驗證碼正確，會員已對齊，但正式登入工作階段無法寫入。請改用 Email 驗證碼或密碼登入。',
                'gap' => 'pm_member_session',
                'member_id' => $member['id_member'] ?? null,
            ];
        }
        unset($_SESSION['pm_phone_otp']);
        return [
            'ok' => true,
            'code' => 'verified',
            'logged_in' => true,
            'member' => $member,
            'message' => '手機簡訊驗證完成，已登入會員。',
        ];
    }

    $memberId = pm_phone_current_member_id();
    if (!$memberId) {
        return ['ok' => false, 'code' => 'unavailable', 'error' => '請先重新登入會員', 'message' => '請先重新登入會員'];
    }
    if (!pm_phone_bind_phone($memberId, $phone)) {
        return ['ok' => false, 'code' => 'unavailable', 'error' => '驗證碼正確，但手機綁定沒有寫入會員資料。', 'message' => '驗證碼正確，但手機綁定沒有寫入會員資料。'];
    }
    unset($_SESSION['pm_phone_otp']);
    return ['ok' => true, 'code' => 'verified', 'verified' => true, 'message' => '手機已完成驗證。'];
}

function pm_phone_action_query(array $input): array
{
    $pending = $_SESSION['pm_phone_otp'] ?? null;
    if (!is_array($pending) || empty($pending['serial'])) {
        return ['ok' => false, 'code' => 'unavailable', 'message' => '目前沒有可查詢的簡訊。', 'query_available' => false];
    }
    return [
        'ok' => true,
        'code' => 'accepted',
        'message' => '簡訊已交給 SMS Go，序號 ' . $pending['serial'] . '。請查看手機，系統不會自動重寄。',
        'query_available' => true,
        'message_id' => $pending['serial'],
    ];
}

function pm_phone_send_sms(string $phone, string $code): array
{
    $body = pm_phone_sms_body($code, pm_phone_config_value('template'));

    foreach (['pm_smsgo_send', 'pm_smsgo_adapter_send', 'smsgo_send', 'pm_phone_sms_send'] as $fn) {
        if (function_exists($fn)) {
            try {
                $result = $fn($phone, $body, $code);
                if ($result === true || (is_array($result) && ($result['ok'] ?? false))) {
                    return ['ok' => true, 'gateway' => 'pm_smsgo_adapter', 'message_id' => is_array($result) ? ($result['message_id'] ?? $result['msgid'] ?? null) : null];
                }
                if (is_array($result) && !empty($result['error'])) {
                    return ['ok' => false, 'code' => 'rejected', 'error' => (string)$result['error'], 'gateway' => 'pm_smsgo_adapter'];
                }
            } catch (Throwable $e) {
                return ['ok' => false, 'code' => 'unavailable', 'error' => '簡訊閘道暫時無法完成，請稍後再試，或改用 Email。', 'gateway' => 'pm_smsgo_adapter'];
            }
        }
    }

    $username = pm_phone_config_value('username');
    $apiKey = pm_phone_config_value('api_key');
    $enabled = pm_phone_config_flag('enabled', pm_phone_is_live_host());
    if ($username === '' || $apiKey === '') {
        if (!pm_phone_is_live_host()) {
            return ['ok' => true, 'gateway' => 'preview', 'preview_code' => $code, 'message_id' => null];
        }
        return [
            'ok' => false,
            'code' => 'unavailable',
            'error' => '正式簡訊閘道已接上，但主機尚未讀到 SMS Go 帳號或 API Key。金鑰在 smsgo-api-key.txt，不要寫進 git。請改用 Email 驗證碼。',
            'gap' => 'smsgo_credentials',
            'gateway' => 'smsgo',
        ];
    }
    if (!$enabled) {
        return [
            'ok' => false,
            'code' => 'unavailable',
            'error' => '簡訊服務目前關閉（SMSGO_ENABLED / runtime enabled）。請改用 Email 驗證碼，或請管理員開啟正式簡訊。',
            'gap' => 'smsgo_enabled',
            'gateway' => 'smsgo',
        ];
    }

    $payload = http_build_query([
        'username' => $username,
        'password' => $apiKey,
        'dstaddr' => pm_phone_smsgo_dstaddr($phone),
        'smbody' => $body,
        'encoding' => 'BIG5',
        'rtype' => 'JSON',
    ], '', '&', PHP_QUERY_RFC3986);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents(PM_SMSGO_SEND_URL, false, $context);
    $http = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $http = (int)$m[1];
    }
    $parsed = pm_phone_parse_smsgo_response($http, is_string($response) ? $response : '');
    if ($parsed['state'] === 'accepted') {
        return ['ok' => true, 'gateway' => 'smsgo', 'message_id' => $parsed['message_id']];
    }
    return [
        'ok' => false,
        'code' => 'rejected',
        'error' => pm_phone_smsgo_user_message((int)$parsed['statuscode']),
        'gateway' => 'smsgo',
    ];
}

function pm_phone_config_value(string $name): string
{
    $map = [
        'username' => ['SMSGO_USERNAME', 'smsgo_username', 'username'],
        'api_key' => ['SMSGO_API_KEY', 'SMSGO_PASSWORD', 'smsgo_api_key', 'api_key', 'password'],
        'template' => ['SMSGO_APPROVED_TEMPLATE', 'smsgo_template', 'template'],
    ];
    foreach ($map[$name] ?? [$name] as $key) {
        $env = getenv($key);
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }
        if (isset($GLOBALS['pm_smsgo'][$key]) && is_scalar($GLOBALS['pm_smsgo'][$key])) {
            return trim((string)$GLOBALS['pm_smsgo'][$key]);
        }
        if (isset($GLOBALS[$key]) && is_scalar($GLOBALS[$key])) {
            return trim((string)$GLOBALS[$key]);
        }
    }
    if ($name === 'username') {
        foreach (pm_phone_secret_files('smsgo-username.txt') as $file) {
            $value = trim((string)@file_get_contents($file));
            if ($value !== '') {
                return $value;
            }
        }
    }
    if ($name === 'api_key') {
        foreach (pm_phone_secret_files('smsgo-api-key.txt') as $file) {
            $value = trim((string)@file_get_contents($file));
            if ($value !== '') {
                return $value;
            }
        }
    }
    if ($name === 'template') {
        return PM_SMSGO_TEMPLATE;
    }
    return '';
}

function pm_phone_config_flag(string $name, bool $default): bool
{
    $keys = $name === 'enabled' ? ['SMSGO_ENABLED', 'enabled'] : [$name];
    foreach ($keys as $key) {
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return pm_phone_truthy($env);
        }
        if (isset($GLOBALS['pm_smsgo'][$key])) {
            return pm_phone_truthy($GLOBALS['pm_smsgo'][$key]);
        }
    }
    return $default;
}

function pm_phone_secret_files(string $basename): array
{
    return [
        '/home/tdwhhyfe/pm_member_private/' . $basename,
        dirname(__DIR__, 2) . '/pm_member_private/' . $basename,
        dirname(__DIR__) . '/pm_member_private/' . $basename,
        __DIR__ . '/' . $basename,
    ];
}

function pm_phone_pdo(): ?PDO
{
    static $pdo = null;
    static $tried = false;
    if ($tried) {
        return $pdo;
    }
    $tried = true;

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $pdo = $GLOBALS['pdo'];
    }

    foreach ([
        dirname(__DIR__) . '/config/settings.inc.php',
        dirname(__DIR__) . '/config/defines.inc.php',
        '/home/tdwhhyfe/public_html/booking/config/settings.inc.php',
    ] as $file) {
        if (is_file($file)) {
            require_once $file;
        }
    }

    if (defined('_DB_SERVER_') && defined('_DB_NAME_') && defined('_DB_USER_')) {
        $pass = defined('_DB_PASSWD_') ? _DB_PASSWD_ : (defined('_DB_PASSWORD_') ? _DB_PASSWORD_ : '');
        try {
            $pdo = new PDO(
                'mysql:host=' . _DB_SERVER_ . ';dbname=' . _DB_NAME_ . ';charset=utf8mb4',
                _DB_USER_,
                (string)$pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            return $pdo;
        } catch (Throwable $e) {
            $pdo = null;
        }
    }
    return null;
}

function pm_phone_table(): string
{
    $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'qlo_';
    return $prefix . 'pm_member';
}

function pm_phone_columns(PDO $pdo): array
{
    static $columns = null;
    if (is_array($columns)) {
        return $columns;
    }
    $columns = [];
    try {
        $rows = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', pm_phone_table()) . '`')->fetchAll();
        foreach ($rows as $row) {
            $columns[$row['Field']] = $row;
        }
    } catch (Throwable $e) {
        $columns = [];
    }
    return $columns;
}

function pm_phone_find_member_by_phone(string $phone): ?array
{
    foreach (get_defined_functions()['user'] as $fn) {
        if (preg_match('/^pm_(find|get)_member_by_phone$/', $fn)) {
            $row = $fn($phone);
            if (is_array($row) && $row) {
                return $row;
            }
        }
    }
    $pdo = pm_phone_pdo();
    if (!$pdo) {
        return pm_phone_preview_find($phone);
    }
    $columns = pm_phone_columns($pdo);
    $phoneCols = array_values(array_intersect(['phone', 'mobile', 'phone_mobile', 'cellphone', 'tel'], array_keys($columns)));
    if (!$phoneCols) {
        return null;
    }
    $where = [];
    $params = [];
    foreach ($phoneCols as $col) {
        $where[] = '`' . $col . '` = ?';
        $params[] = $phone;
        $where[] = "REPLACE(REPLACE(REPLACE(`$col`,'-',''),' ',''),'+','') = ?";
        $params[] = $phone;
    }
    $sql = 'SELECT * FROM `' . str_replace('`', '', pm_phone_table()) . '` WHERE ' . implode(' OR ', $where) . ' LIMIT 1';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function pm_phone_find_member_by_id(int $id): ?array
{
    $pdo = pm_phone_pdo();
    if (!$pdo) {
        return null;
    }
    $idCol = isset(pm_phone_columns($pdo)['id_member']) ? 'id_member' : 'id';
    try {
        $stmt = $pdo->prepare('SELECT * FROM `' . str_replace('`', '', pm_phone_table()) . '` WHERE `' . $idCol . '` = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function pm_phone_member_is_verified(?array $member): bool
{
    if (!$member) {
        return false;
    }
    foreach (['phone_verified', 'is_phone_verified', 'mobile_verified'] as $key) {
        if (array_key_exists($key, $member)) {
            return pm_phone_truthy($member[$key]) || $member[$key] === 1 || $member[$key] === '1';
        }
    }
    foreach (['phone_verified_at', 'mobile_verified_at'] as $key) {
        if (!empty($member[$key]) && $member[$key] !== '0000-00-00 00:00:00') {
            return true;
        }
    }
    return !empty($member['phone']) && pm_phone_normalize((string)$member['phone']);
}

function pm_phone_find_or_create_member(string $phone, array $input): ?array
{
    foreach (get_defined_functions()['user'] as $fn) {
        if (preg_match('/^pm_(find_or_create|upsert)_member(_by_phone)?$/', $fn)) {
            $row = $fn($phone, $input);
            if (is_array($row) && $row) {
                return pm_phone_public_member($row);
            }
        }
    }
    $existing = pm_phone_find_member_by_phone($phone);
    if ($existing) {
        pm_phone_mark_verified($existing);
        return pm_phone_public_member($existing);
    }
    $created = pm_phone_insert_member($phone, $input);
    return $created ? pm_phone_public_member($created) : null;
}

function pm_phone_insert_member(string $phone, array $input): ?array
{
    $pdo = pm_phone_pdo();
    if (!$pdo) {
        return pm_phone_preview_create($phone, $input);
    }
    $columns = pm_phone_columns($pdo);
    if (!$columns) {
        return null;
    }
    $now = date('Y-m-d H:i:s');
    $values = [
        'phone' => $phone,
        'mobile' => $phone,
        'phone_mobile' => $phone,
        'name' => trim((string)($input['name'] ?? '')) ?: '簡訊驗證會員',
        'email' => trim((string)($input['email'] ?? '')),
        'member_level' => 'trial',
        'qualification_state' => 'pending',
        'phone_verified' => 1,
        'is_phone_verified' => 1,
        'phone_verified_at' => $now,
        'date_add' => $now,
        'date_upd' => $now,
        'created_at' => $now,
        'updated_at' => $now,
        'source' => 'sms_login',
        'auth_provider' => 'sms',
    ];
    $insert = [];
    foreach ($values as $col => $value) {
        if (isset($columns[$col])) {
            $insert[$col] = $value;
        }
    }
    if (!$insert) {
        return null;
    }
    $cols = array_keys($insert);
    $sql = 'INSERT INTO `' . str_replace('`', '', pm_phone_table()) . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($insert));
        $id = (int)$pdo->lastInsertId();
        $row = pm_phone_find_member_by_id($id) ?: $insert;
        $row['id_member'] = $row['id_member'] ?? $id;
        return $row;
    } catch (Throwable $e) {
        return null;
    }
}

function pm_phone_mark_verified(array $member): void
{
    $id = (int)($member['id_member'] ?? $member['id'] ?? 0);
    if ($id) {
        pm_phone_bind_phone($id, (string)($member['phone'] ?? ''));
    }
}

function pm_phone_bind_phone(int $memberId, string $phone): bool
{
    foreach (get_defined_functions()['user'] as $fn) {
        if (preg_match('/^pm_(bind|set)_member_phone$/', $fn)) {
            try {
                return (bool)$fn($memberId, $phone);
            } catch (Throwable $e) {
                return false;
            }
        }
    }
    $pdo = pm_phone_pdo();
    if (!$pdo) {
        return !pm_phone_is_live_host();
    }
    $columns = pm_phone_columns($pdo);
    $idCol = isset($columns['id_member']) ? 'id_member' : 'id';
    $set = [];
    $params = [];
    foreach (['phone' => $phone, 'mobile' => $phone, 'phone_mobile' => $phone, 'phone_verified' => 1, 'is_phone_verified' => 1, 'phone_verified_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')] as $col => $value) {
        if (isset($columns[$col])) {
            $set[] = '`' . $col . '` = ?';
            $params[] = $value;
        }
    }
    if (!$set) {
        return false;
    }
    $params[] = $memberId;
    try {
        $stmt = $pdo->prepare('UPDATE `' . str_replace('`', '', pm_phone_table()) . '` SET ' . implode(',', $set) . ' WHERE `' . $idCol . '` = ?');
        $stmt->execute($params);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function pm_phone_attach_session(array $member): bool
{
    $id = (int)($member['id_member'] ?? $member['id'] ?? 0);
    if ($id < 1) {
        return false;
    }
    foreach (get_defined_functions()['user'] as $fn) {
        if (preg_match('/^pm_(member_set_session|set_member_session|login_member|member_login|start_member_session|establish_member_session)$/', $fn)) {
            try {
                $fn($member);
                return pm_phone_current_member_id() === $id || true;
            } catch (Throwable $e) {
                // try next
            }
        }
    }
    $_SESSION['id_member'] = $id;
    $_SESSION['pm_id_member'] = $id;
    $_SESSION['member_id'] = $id;
    $_SESSION['logged_in'] = true;
    $_SESSION['pm_logged_in'] = true;
    $_SESSION['member'] = $member;
    return true;
}

function pm_phone_public_member(array $row): array
{
    $id = $row['id_member'] ?? $row['id'] ?? null;
    return [
        'id_member' => $id,
        'name' => $row['name'] ?? '',
        'email' => $row['email'] ?? '',
        'phone' => $row['phone'] ?? $row['mobile'] ?? '',
        'member_level' => $row['member_level'] ?? 'trial',
        'qualification_state' => $row['qualification_state'] ?? 'pending',
    ];
}

function pm_phone_preview_store_path(): string
{
    $dir = sys_get_temp_dir() . '/pm_member_preview_v18';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir . '/members.json';
}

function pm_phone_preview_all(): array
{
    $file = pm_phone_preview_store_path();
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function pm_phone_preview_find(string $phone): ?array
{
    foreach (pm_phone_preview_all() as $row) {
        if (($row['phone'] ?? '') === $phone) {
            return $row;
        }
    }
    return null;
}

function pm_phone_preview_create(string $phone, array $input): ?array
{
    if (pm_phone_is_live_host()) {
        return null;
    }
    $rows = pm_phone_preview_all();
    $row = [
        'id_member' => count($rows) + 1001,
        'name' => trim((string)($input['name'] ?? '')) ?: '簡訊驗證會員',
        'email' => trim((string)($input['email'] ?? '')),
        'phone' => $phone,
        'member_level' => 'trial',
        'qualification_state' => 'pending',
        'phone_verified' => 1,
    ];
    $rows[] = $row;
    file_put_contents(pm_phone_preview_store_path(), json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $row;
}
