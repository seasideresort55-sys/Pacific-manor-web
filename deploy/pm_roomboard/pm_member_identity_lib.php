<?php
/**
 * 太平洋莊園會員身分：user_id = id_member；Apple／Google 只認 sub；手機 E.164。
 * Email／Apple Relay 僅輔助聯絡，不當作 find-or-create 唯一鍵。
 *
 * 可在測試模式（PM_IDENTITY_TEST）單獨載入純函式，不必連 QloApps。
 */

if (!defined('PM_IDENTITY_LIB')) {
    define('PM_IDENTITY_LIB', '2026-09-17');
}

function pm_identity_messages()
{
    return [
        'google_conflict' => '這個 Google 帳號已綁定其他會員，請先用該方式登入，或聯絡客服。',
        'apple_conflict' => '這個 Apple 帳號已綁定其他會員，請先用該方式登入，或聯絡客服。',
        'phone_conflict' => '這個手機號碼已綁定其他會員，請先用該方式登入，或聯絡客服。',
        'confirm_bind_google' => '您已有會員帳號，是否綁定 Google？綁定後可用 Google 登入同一個會員。',
        'confirm_bind_apple' => '您已有會員帳號，是否綁定 Apple？綁定後可用 Apple 登入同一個會員。',
        'confirm_bind_phone' => '您已有會員帳號，是否綁定這個手機？綁定後可用簡訊登入同一個會員。',
        'need_confirm' => '請先確認是否要綁定到目前的會員帳號。系統不會在未確認時合併帳號。',
        'bad_phone' => '請輸入台灣手機號碼，例如 09 開頭的十位數字。',
        'bad_sub' => '登入服務沒有回傳有效的帳號識別碼，請改用其他方式或稍後再試。',
        'bad_token' => '無法驗證登入憑證，請重新登入。',
        'need_consent' => '請先勾選同意《會員資料使用說明》。',
        'need_login' => '請先登入會員，再綁定登入方式。',
        'otp_first' => '請先取得驗證碼。',
        'otp_bad' => '驗證碼不正確或已過期，請重新取得。',
        'otp_sent' => '簡訊驗證碼已送出。',
        'bound' => '已綁定到同一個會員帳號。',
        'logged_in' => '已登入。',
        'created' => '已建立會員。',
        'email_aux_only' => 'Email 僅作為輔助聯絡，不能用來建立或合併會員。請用手機簡訊、Google 或 Apple 登入。',
        'apple_relay_ok' => '可用 Apple 登入；若開啟隱藏信箱，我們會以手機聯絡您。',
        'sms_unavailable' => '簡訊服務目前無法送出，請稍後再試。',
        'unknown_action' => '無法辨識這個操作。',
        'csrf' => '頁面驗證已失效，請重新整理後再試。',
        'install_ok' => '會員身分欄位已就緒。',
        'not_installed' => '會員身分欄位尚未安裝，請先執行 pm_member_identity_install.php。',
    ];
}

function pm_identity_msg($key)
{
    $all = pm_identity_messages();
    return $all[$key] ?? '操作沒有完成，請稍後再試。';
}

function pm_identity_provider_label($provider)
{
    $map = ['google' => 'Google', 'apple' => 'Apple', 'phone' => '手機'];
    return $map[$provider] ?? $provider;
}

function pm_identity_sub_column($provider)
{
    if ($provider === 'google') {
        return 'google_sub';
    }
    if ($provider === 'apple') {
        return 'apple_sub';
    }
    return null;
}

function pm_identity_normalize_phone($input)
{
    $raw = trim((string) $input);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\+8869\d{8}$/', $raw)) {
        return $raw;
    }
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === null || $digits === '') {
        return null;
    }
    if (preg_match('/^8869\d{8}$/', $digits)) {
        return '+' . $digits;
    }
    if (preg_match('/^09\d{8}$/', $digits)) {
        return '+886' . substr($digits, 1);
    }
    return null;
}

function pm_identity_phone_local($e164)
{
    if (!is_string($e164) || !preg_match('/^\+8869\d{8}$/', $e164)) {
        return null;
    }
    return '0' . substr($e164, 4);
}

function pm_identity_is_apple_relay($email)
{
    $email = strtolower(trim((string) $email));
    return $email !== '' && substr($email, -strlen('@privaterelay.appleid.com')) === '@privaterelay.appleid.com';
}

function pm_identity_normalize_email($input)
{
    $email = strtolower(trim((string) $input));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return $email;
}

function pm_identity_null_if_empty($value)
{
    if ($value === null) {
        return null;
    }
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

/**
 * 純決策：OAuth 只依 sub；禁止用 email 找或建。
 * $store 提供 findBySub / findById。
 */
function pm_identity_decide_oauth(array $storeMembers, array $input)
{
    $provider = $input['provider'] ?? '';
    $sub = pm_identity_null_if_empty($input['sub'] ?? null);
    $loggedInId = isset($input['logged_in_user_id']) ? (int) $input['logged_in_user_id'] : 0;
    $confirm = !empty($input['confirm_bind']);
    $col = pm_identity_sub_column($provider);

    if (!$col || !$sub) {
        return pm_identity_fail('bad_sub');
    }

    $owner = pm_identity_find_member($storeMembers, $col, $sub);

    if ($loggedInId > 0) {
        $me = pm_identity_find_member($storeMembers, 'user_id', $loggedInId);
        if (!$me) {
            return pm_identity_fail('need_login');
        }
        if ($owner && (int) $owner['user_id'] !== $loggedInId) {
            return pm_identity_fail($provider . '_conflict');
        }
        if ($owner && (int) $owner['user_id'] === $loggedInId) {
            return pm_identity_ok('logged_in', $loggedInId, ['bound' => true, 'created' => false]);
        }
        if (!$confirm) {
            return [
                'ok' => false,
                'error' => pm_identity_msg('confirm_bind_' . $provider),
                'next_step' => 'confirm_bind',
                'prompt' => '您已有會員帳號，是否綁定 ' . pm_identity_provider_label($provider) . '？',
                'user_id' => $loggedInId,
            ];
        }
        return pm_identity_ok('bound', $loggedInId, [
            'action' => 'bind',
            'column' => $col,
            'value' => $sub,
            'aux_email' => pm_identity_normalize_email($input['email'] ?? '') ?: null,
        ]);
    }

    if ($owner) {
        return pm_identity_ok('logged_in', (int) $owner['user_id'], ['created' => false, 'bound' => true]);
    }

    return pm_identity_ok('created', 0, [
        'action' => 'create',
        'column' => $col,
        'value' => $sub,
        'aux_email' => pm_identity_normalize_email($input['email'] ?? '') ?: null,
        'name' => trim((string) ($input['name'] ?? '')) ?: '會員',
        'created' => true,
    ]);
}

/**
 * 手機：驗證後才找／建。已登入則提示綁定，不靜默合併。
 */
function pm_identity_decide_phone(array $storeMembers, array $input)
{
    $phone = pm_identity_normalize_phone($input['phone'] ?? '');
    $loggedInId = isset($input['logged_in_user_id']) ? (int) $input['logged_in_user_id'] : 0;
    $confirm = !empty($input['confirm_bind']);
    $verified = !empty($input['phone_verified']);

    if (!$phone) {
        return pm_identity_fail('bad_phone');
    }
    if (!$verified) {
        return pm_identity_fail('otp_first');
    }

    $owner = pm_identity_find_member($storeMembers, 'phone', $phone);

    if ($loggedInId > 0) {
        if ($owner && (int) $owner['user_id'] !== $loggedInId) {
            return pm_identity_fail('phone_conflict');
        }
        if ($owner && (int) $owner['user_id'] === $loggedInId) {
            return pm_identity_ok('logged_in', $loggedInId, ['bound' => true, 'created' => false]);
        }
        if (!$confirm) {
            return [
                'ok' => false,
                'error' => pm_identity_msg('confirm_bind_phone'),
                'next_step' => 'confirm_bind',
                'prompt' => '您已有會員帳號，是否綁定手機？',
                'user_id' => $loggedInId,
            ];
        }
        return pm_identity_ok('bound', $loggedInId, [
            'action' => 'bind',
            'column' => 'phone',
            'value' => $phone,
        ]);
    }

    if ($owner) {
        return pm_identity_ok('logged_in', (int) $owner['user_id'], ['created' => false, 'bound' => true]);
    }

    return pm_identity_ok('created', 0, [
        'action' => 'create',
        'column' => 'phone',
        'value' => $phone,
        'name' => trim((string) ($input['name'] ?? '')) ?: '會員',
        'created' => true,
    ]);
}

function pm_identity_find_member(array $members, $column, $value)
{
    if ($value === null || $value === '') {
        return null;
    }
    foreach ($members as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($column === 'user_id' && (int) ($row['user_id'] ?? $row['id_member'] ?? 0) === (int) $value) {
            return $row;
        }
        if (isset($row[$column]) && (string) $row[$column] === (string) $value) {
            return $row;
        }
    }
    return null;
}

function pm_identity_ok($messageKey, $userId, array $extra = [])
{
    return array_merge([
        'ok' => true,
        'error' => null,
        'next_step' => null,
        'message' => pm_identity_msg($messageKey),
        'user_id' => $userId,
        'id_member' => $userId,
    ], $extra);
}

function pm_identity_fail($messageKey)
{
    return [
        'ok' => false,
        'error' => pm_identity_msg($messageKey),
        'next_step' => $messageKey === 'need_confirm' || strpos($messageKey, 'confirm_bind') === 0 ? 'confirm_bind' : null,
        'user_id' => null,
    ];
}

function pm_identity_public_member(array $row)
{
    $userId = (int) ($row['user_id'] ?? $row['id_member'] ?? 0);
    $phone = pm_identity_null_if_empty($row['phone'] ?? null);
    $email = pm_identity_null_if_empty($row['email'] ?? null);
    $contact = 'oauth_only';
    if ($phone) {
        $contact = 'phone';
    } elseif ($email) {
        $contact = 'email';
    }
    return [
        'user_id' => $userId,
        'id_member' => $userId,
        'name' => $row['name'] ?? '會員',
        'phone' => $phone,
        'email' => $email,
        'email_is_apple_relay' => pm_identity_is_apple_relay($email ?: ''),
        'apple_bound' => pm_identity_null_if_empty($row['apple_sub'] ?? null) !== null,
        'google_bound' => pm_identity_null_if_empty($row['google_sub'] ?? null) !== null,
        'phone_bound' => $phone !== null,
        'contact_priority' => $contact,
        'member_level' => $row['member_level'] ?? 'trial',
        'qualification_state' => $row['qualification_state'] ?? 'pending',
    ];
}

function pm_identity_private_dir()
{
    $candidates = [
        '/home/tdwhhyfe/pm_member_private',
        dirname(__DIR__, 2) . '/pm_member_private',
        dirname(__DIR__) . '/pm_member_private',
        __DIR__ . '/../pm_member_private',
    ];
    foreach ($candidates as $dir) {
        if (is_dir($dir)) {
            return $dir;
        }
    }
    return '/home/tdwhhyfe/pm_member_private';
}

function pm_identity_read_secret($basename, $envKeys = [])
{
    foreach ((array) $envKeys as $key) {
        $value = getenv($key);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
        if (!empty($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
            return trim((string) $_SERVER[$key]);
        }
    }
    $path = rtrim(pm_identity_private_dir(), '/') . '/' . $basename;
    if (is_readable($path)) {
        $value = trim((string) file_get_contents($path));
        return $value !== '' ? $value : '';
    }
    return '';
}

function pm_identity_google_client_id()
{
    return pm_identity_read_secret('google-client-id.txt', ['GOOGLE_CLIENT_ID', 'PM_GOOGLE_CLIENT_ID']);
}

function pm_identity_apple_client_id()
{
    return pm_identity_read_secret('apple-service-id.txt', ['APPLE_CLIENT_ID', 'APPLE_SERVICE_ID', 'PM_APPLE_CLIENT_ID']);
}

function pm_identity_server_token()
{
    return pm_identity_read_secret('member-api-token.txt', ['MEMBER_API_TOKEN', 'PM_MEMBER_API_TOKEN']);
}

function pm_identity_install_key()
{
    return pm_identity_read_secret('identity-install.key', ['PM_IDENTITY_INSTALL_KEY']);
}

function pm_identity_session_start()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent()) {
        @session_start();
        return;
    }
    if (PHP_SAPI !== 'cli') {
        session_start();
    } else {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
    }
}

function pm_identity_csrf_token()
{
    pm_identity_session_start();
    if (empty($_SESSION['pm_identity_csrf']) || !is_string($_SESSION['pm_identity_csrf'])) {
        $_SESSION['pm_identity_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pm_identity_csrf'];
}

function pm_identity_csrf_ok($given)
{
    pm_identity_session_start();
    $expected = $_SESSION['pm_identity_csrf'] ?? '';
    return is_string($given) && is_string($expected) && $expected !== '' && hash_equals($expected, $given);
}

function pm_identity_logged_in_user_id()
{
    pm_identity_session_start();
    foreach (['pm_identity_user_id', 'pm_id_member', 'pm_member_id', 'id_member'] as $key) {
        if (!empty($_SESSION[$key]) && (int) $_SESSION[$key] > 0) {
            return (int) $_SESSION[$key];
        }
    }
    if (!empty($_SESSION['pm_member']['id_member'])) {
        return (int) $_SESSION['pm_member']['id_member'];
    }
    return 0;
}

function pm_identity_login_user($userId)
{
    $userId = (int) $userId;
    if ($userId < 1) {
        return;
    }
    pm_identity_session_start();
    $_SESSION['pm_identity_user_id'] = $userId;
    $_SESSION['pm_id_member'] = $userId;
    $_SESSION['pm_member_id'] = $userId;
    $_SESSION['id_member'] = $userId;
    if (!isset($_SESSION['pm_member']) || !is_array($_SESSION['pm_member'])) {
        $_SESSION['pm_member'] = [];
    }
    $_SESSION['pm_member']['id_member'] = $userId;
    $_SESSION['pm_member']['user_id'] = $userId;
}

function pm_identity_logout_user()
{
    pm_identity_session_start();
    foreach (['pm_identity_user_id', 'pm_id_member', 'pm_member_id', 'id_member'] as $key) {
        unset($_SESSION[$key]);
    }
    unset($_SESSION['pm_member']);
}

function pm_identity_request_payload()
{
    $json = [];
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '' && isset($_SERVER['CONTENT_TYPE']) && stripos((string) $_SERVER['CONTENT_TYPE'], 'json') !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }
    return array_merge($_GET, $_POST, $json);
}

function pm_identity_request_csrf($payload)
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $name => $value) {
        if (strtolower((string) $name) === 'x-pm-csrf') {
            return (string) $value;
        }
    }
    if (!empty($_SERVER['HTTP_X_PM_CSRF'])) {
        return (string) $_SERVER['HTTP_X_PM_CSRF'];
    }
    return (string) ($payload['csrf'] ?? $payload['csrf_token'] ?? '');
}

function pm_identity_server_token_ok()
{
    $expected = pm_identity_server_token();
    if ($expected === '') {
        return false;
    }
    $given = '';
    if (!empty($_SERVER['HTTP_X_PM_SERVER_TOKEN'])) {
        $given = (string) $_SERVER['HTTP_X_PM_SERVER_TOKEN'];
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(.+)/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $given = $m[1];
    }
    return $given !== '' && hash_equals($expected, $given);
}

function pm_identity_json($data, $http = 200)
{
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    $data['csrf_token'] = pm_identity_csrf_token();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    return $data;
}

function pm_identity_table_name()
{
    $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'qlo_';
    return $prefix . 'pm_member';
}

function pm_identity_bootstrap_qlo()
{
    if (defined('PM_IDENTITY_TEST') && PM_IDENTITY_TEST) {
        return false;
    }
    if (class_exists('Db') && defined('_DB_PREFIX_')) {
        return true;
    }
    $roots = [
        dirname(__DIR__),
        dirname(__DIR__, 1),
        dirname(__DIR__, 2),
        '/home/tdwhhyfe/public_html/booking',
    ];
    foreach ($roots as $root) {
        $config = rtrim($root, '/') . '/config/config.inc.php';
        if (is_readable($config)) {
            require_once $config;
            return class_exists('Db');
        }
    }
    return class_exists('Db');
}

function pm_identity_db_query($sql)
{
    if (class_exists('Db')) {
        return Db::getInstance()->executeS($sql);
    }
    throw new RuntimeException('會員資料庫尚未連線');
}

function pm_identity_db_exec($sql)
{
    if (class_exists('Db')) {
        return Db::getInstance()->execute($sql);
    }
    throw new RuntimeException('會員資料庫尚未連線');
}

function pm_identity_db_value($sql)
{
    if (class_exists('Db')) {
        return Db::getInstance()->getValue($sql);
    }
    throw new RuntimeException('會員資料庫尚未連線');
}

function pm_identity_sql_str($value)
{
    if ($value === null) {
        return 'NULL';
    }
    $value = (string) $value;
    if (function_exists('pSQL')) {
        return "'" . pSQL($value) . "'";
    }
    if (class_exists('Db') && method_exists(Db::getInstance(), 'escape')) {
        return "'" . Db::getInstance()->escape($value) . "'";
    }
    return "'" . addslashes($value) . "'";
}

function pm_identity_reset_column_cache()
{
    unset($GLOBALS['pm_identity_column_cache']);
}

function pm_identity_table_columns()
{
    if (isset($GLOBALS['pm_identity_column_cache']) && is_array($GLOBALS['pm_identity_column_cache'])) {
        return $GLOBALS['pm_identity_column_cache'];
    }
    $table = pm_identity_table_name();
    $rows = pm_identity_db_query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    $cache = [];
    foreach ($rows ?: [] as $row) {
        $field = $row['Field'] ?? $row['field'] ?? null;
        if ($field) {
            $cache[$field] = $row;
        }
    }
    $GLOBALS['pm_identity_column_cache'] = $cache;
    return $cache;
}

function pm_identity_has_column($name)
{
    $cols = pm_identity_table_columns();
    return isset($cols[$name]);
}

function pm_identity_load_member_by($column, $value)
{
    $value = pm_identity_null_if_empty($value);
    if ($value === null || !pm_identity_has_column($column)) {
        return null;
    }
    $table = str_replace('`', '', pm_identity_table_name());
    $sql = 'SELECT * FROM `' . $table . '` WHERE `' . str_replace('`', '', $column) . '` = ' . pm_identity_sql_str($value) . ' LIMIT 1';
    $rows = pm_identity_db_query($sql);
    if (!$rows || !isset($rows[0])) {
        return null;
    }
    return pm_identity_normalize_row($rows[0]);
}

function pm_identity_load_member_by_id($id)
{
    $id = (int) $id;
    if ($id < 1) {
        return null;
    }
    $table = str_replace('`', '', pm_identity_table_name());
    $rows = pm_identity_db_query('SELECT * FROM `' . $table . '` WHERE `id_member` = ' . $id . ' LIMIT 1');
    if (!$rows || !isset($rows[0])) {
        return null;
    }
    return pm_identity_normalize_row($rows[0]);
}

function pm_identity_normalize_row(array $row)
{
    $row['user_id'] = (int) ($row['id_member'] ?? $row['user_id'] ?? 0);
    $row['id_member'] = $row['user_id'];
    foreach (['apple_sub', 'google_sub', 'phone', 'email'] as $key) {
        if (isset($row[$key])) {
            $row[$key] = pm_identity_null_if_empty($row[$key]);
        }
    }
    return $row;
}

function pm_identity_members_for_decision($candidates)
{
    $out = [];
    foreach ($candidates as $row) {
        if (is_array($row)) {
            $out[] = pm_identity_normalize_row($row);
        }
    }
    return $out;
}

function pm_identity_apply_decision(array $decision)
{
    if (empty($decision['ok'])) {
        return $decision;
    }
    $action = $decision['action'] ?? null;
    if ($action === 'create') {
        $userId = pm_identity_insert_member($decision);
        if (!$userId) {
            return pm_identity_fail('not_installed');
        }
        pm_identity_login_user($userId);
        $decision['user_id'] = $userId;
        $decision['id_member'] = $userId;
        $decision['created'] = true;
        return $decision;
    }
    if ($action === 'bind') {
        $userId = (int) $decision['user_id'];
        if (!pm_identity_update_member($userId, [
            $decision['column'] => $decision['value'],
            'email' => $decision['aux_email'] ?? null,
        ])) {
            return ['ok' => false, 'error' => '無法寫入綁定資料，請聯絡客服。', 'next_step' => null];
        }
        pm_identity_login_user($userId);
        return $decision;
    }
    if (!empty($decision['user_id'])) {
        pm_identity_login_user((int) $decision['user_id']);
    }
    return $decision;
}

function pm_identity_insert_member(array $decision)
{
    $cols = pm_identity_table_columns();
    if (!$cols || !isset($cols['id_member'])) {
        return 0;
    }
    $now = date('Y-m-d H:i:s');
    $email = $decision['aux_email'] ?? null;
    $name = $decision['name'] ?? '會員';
    $row = [];
    $wanted = [
        'name' => $name,
        'firstname' => $name,
        'email' => $email,
        'phone' => ($decision['column'] ?? '') === 'phone' ? $decision['value'] : null,
        'apple_sub' => ($decision['column'] ?? '') === 'apple_sub' ? $decision['value'] : null,
        'google_sub' => ($decision['column'] ?? '') === 'google_sub' ? $decision['value'] : null,
        'member_level' => 'trial',
        'qualification_state' => 'pending',
        'line_user_id' => '',
        'passwd' => '',
        'password' => '',
        'date_add' => $now,
        'date_upd' => $now,
        'created_at' => $now,
        'updated_at' => $now,
        'active' => 1,
        'deleted' => 0,
    ];
    foreach ($wanted as $key => $value) {
        if (!isset($cols[$key])) {
            continue;
        }
        $nullOk = stripos((string) ($cols[$key]['Null'] ?? ''), 'yes') !== false;
        if ($value === null && !$nullOk) {
            $row[$key] = '';
        } else {
            $row[$key] = $value;
        }
    }
    $parts = [];
    foreach ($row as $key => $value) {
        $parts[] = '`' . str_replace('`', '', $key) . '` = ' . ($value === null ? 'NULL' : pm_identity_sql_str($value));
    }
    $table = str_replace('`', '', pm_identity_table_name());
    pm_identity_db_exec('INSERT INTO `' . $table . '` SET ' . implode(', ', $parts));
    $id = 0;
    if (class_exists('Db')) {
        $id = (int) Db::getInstance()->Insert_ID();
    }
    return $id;
}

function pm_identity_update_member($userId, array $fields)
{
    $userId = (int) $userId;
    $cols = pm_identity_table_columns();
    $sets = [];
    foreach ($fields as $key => $value) {
        if (!isset($cols[$key])) {
            continue;
        }
        if ($key === 'email' && $value === null) {
            continue;
        }
        if ($value === null) {
            $sets[] = '`' . $key . '` = NULL';
        } else {
            $sets[] = '`' . $key . '` = ' . pm_identity_sql_str($value);
        }
    }
    if (isset($cols['date_upd'])) {
        $sets[] = '`date_upd` = ' . pm_identity_sql_str(date('Y-m-d H:i:s'));
    }
    if (!$sets) {
        return true;
    }
    $table = str_replace('`', '', pm_identity_table_name());
    return (bool) pm_identity_db_exec('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE `id_member` = ' . $userId . ' LIMIT 1');
}

function pm_identity_snapshot_for($decisionInput)
{
    $rows = [];
    $logged = (int) ($decisionInput['logged_in_user_id'] ?? 0);
    if ($logged > 0) {
        $me = pm_identity_load_member_by_id($logged);
        if ($me) {
            $rows[] = $me;
        }
    }
    if (!empty($decisionInput['sub']) && ($col = pm_identity_sub_column($decisionInput['provider'] ?? ''))) {
        $found = pm_identity_load_member_by($col, $decisionInput['sub']);
        if ($found) {
            $rows[] = $found;
        }
    }
    if (!empty($decisionInput['phone'])) {
        $phone = pm_identity_normalize_phone($decisionInput['phone']);
        $found = $phone ? pm_identity_load_member_by('phone', $phone) : null;
        if ($found) {
            $rows[] = $found;
        }
    }
    return $rows;
}

function pm_identity_run_oauth(array $input)
{
    $input['logged_in_user_id'] = $input['logged_in_user_id'] ?? pm_identity_logged_in_user_id();
    $members = pm_identity_snapshot_for($input);
    $decision = pm_identity_decide_oauth($members, $input);
    if (empty($decision['ok'])) {
        return $decision;
    }
    return pm_identity_apply_decision($decision);
}

function pm_identity_run_phone(array $input)
{
    $input['logged_in_user_id'] = $input['logged_in_user_id'] ?? pm_identity_logged_in_user_id();
    $members = pm_identity_snapshot_for($input);
    $decision = pm_identity_decide_phone($members, $input);
    if (empty($decision['ok'])) {
        return $decision;
    }
    return pm_identity_apply_decision($decision);
}

function pm_identity_otp_issue($phone)
{
    $phone = pm_identity_normalize_phone($phone);
    if (!$phone) {
        return pm_identity_fail('bad_phone');
    }
    pm_identity_session_start();
    $now = time();
    $pending = $_SESSION['pm_identity_otp'] ?? null;
    if (is_array($pending) && ($pending['phone'] ?? '') === $phone && ($pending['ready_at'] ?? 0) > $now) {
        return ['ok' => false, 'error' => '請稍候再重新送出簡訊。', 'retry_after' => $pending['ready_at'] - $now];
    }
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $salt = bin2hex(random_bytes(8));
    $_SESSION['pm_identity_otp'] = [
        'phone' => $phone,
        'hash' => hash('sha256', $salt . $code),
        'salt' => $salt,
        'expires' => $now + 600,
        'ready_at' => $now + 60,
        'tries' => 0,
    ];
    return ['ok' => true, 'phone' => $phone, 'code' => $code, 'message' => pm_identity_msg('otp_sent')];
}

function pm_identity_otp_check($phone, $code)
{
    $phone = pm_identity_normalize_phone($phone);
    pm_identity_session_start();
    $pending = $_SESSION['pm_identity_otp'] ?? null;
    if (!$phone || !is_array($pending) || ($pending['phone'] ?? '') !== $phone) {
        return false;
    }
    if (time() > (int) $pending['expires']) {
        unset($_SESSION['pm_identity_otp']);
        return false;
    }
    $pending['tries'] = (int) ($pending['tries'] ?? 0) + 1;
    $_SESSION['pm_identity_otp'] = $pending;
    if ($pending['tries'] > 8) {
        unset($_SESSION['pm_identity_otp']);
        return false;
    }
    $ok = hash_equals($pending['hash'], hash('sha256', $pending['salt'] . trim((string) $code)));
    if ($ok) {
        unset($_SESSION['pm_identity_otp']);
        $_SESSION['pm_identity_verified_phone'] = $phone;
    }
    return $ok;
}

function pm_identity_b64url_decode($data)
{
    $data = strtr((string) $data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($data);
}

function pm_identity_jwt_payload($jwt)
{
    $parts = explode('.', (string) $jwt);
    if (count($parts) < 2) {
        return null;
    }
    $payload = json_decode(pm_identity_b64url_decode($parts[1]), true);
    return is_array($payload) ? $payload : null;
}

function pm_identity_http_get($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300 && is_string($body)) {
            return $body;
        }
        return null;
    }
    $body = @file_get_contents($url);
    return is_string($body) ? $body : null;
}

function pm_identity_http_post($url, array $fields)
{
    $body = http_build_query($fields);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, is_string($resp) ? $resp : ''];
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 15,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    return [200, is_string($resp) ? $resp : ''];
}

function pm_identity_verify_google_id_token($jwt, $nonce = null)
{
    $clientId = pm_identity_google_client_id();
    if ($clientId === '' || !$jwt) {
        return null;
    }
    $body = pm_identity_http_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($jwt));
    $data = $body ? json_decode($body, true) : null;
    if (!is_array($data) || empty($data['sub'])) {
        return null;
    }
    $aud = $data['aud'] ?? '';
    if ($aud !== $clientId) {
        return null;
    }
    $iss = $data['iss'] ?? '';
    if (!in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)) {
        return null;
    }
    if ($nonce && !empty($data['nonce']) && !hash_equals((string) $nonce, (string) $data['nonce'])) {
        return null;
    }
    return [
        'provider' => 'google',
        'sub' => (string) $data['sub'],
        'email' => pm_identity_normalize_email($data['email'] ?? '') ?: null,
        'name' => trim((string) ($data['name'] ?? '')) ?: null,
    ];
}

function pm_identity_verify_apple_id_token($jwt)
{
    $clientId = pm_identity_apple_client_id();
    if ($clientId === '' || !$jwt) {
        return null;
    }
    $parts = explode('.', (string) $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    $header = json_decode(pm_identity_b64url_decode($parts[0]), true);
    $payload = json_decode(pm_identity_b64url_decode($parts[1]), true);
    if (!is_array($header) || !is_array($payload) || empty($payload['sub'])) {
        return null;
    }
    if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
        return null;
    }
    $aud = $payload['aud'] ?? '';
    if (is_array($aud)) {
        if (!in_array($clientId, $aud, true)) {
            return null;
        }
    } elseif ($aud !== $clientId) {
        return null;
    }
    if (!empty($payload['exp']) && time() >= (int) $payload['exp']) {
        return null;
    }
    $jwksBody = pm_identity_http_get('https://appleid.apple.com/auth/keys');
    $jwks = $jwksBody ? json_decode($jwksBody, true) : null;
    if (!is_array($jwks) || empty($jwks['keys'])) {
        return null;
    }
    $kid = $header['kid'] ?? '';
    $key = null;
    foreach ($jwks['keys'] as $item) {
        if (($item['kid'] ?? '') === $kid) {
            $key = $item;
            break;
        }
    }
    if (!$key || !function_exists('openssl_verify')) {
        return null;
    }
    $n = pm_identity_b64url_decode($key['n'] ?? '');
    $e = pm_identity_b64url_decode($key['e'] ?? '');
    if (!$n || !$e) {
        return null;
    }
    $pem = pm_identity_rsa_pem($n, $e);
    $sig = pm_identity_b64url_decode($parts[2]);
    $ok = openssl_verify($parts[0] . '.' . $parts[1], $sig, $pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        return null;
    }
    return [
        'provider' => 'apple',
        'sub' => (string) $payload['sub'],
        'email' => pm_identity_normalize_email($payload['email'] ?? '') ?: null,
        'name' => null,
    ];
}

function pm_identity_rsa_pem($n, $e)
{
    $encode = function ($bin) {
        if (ord($bin[0]) > 0x7f) {
            $bin = "\x00" . $bin;
        }
        return pack('Ca*a*', 0x02, pm_identity_der_len(strlen($bin)), $bin);
    };
    $seq = $encode($n) . $encode($e);
    $rsa = pack('Ca*a*', 0x30, pm_identity_der_len(strlen($seq)), $seq);
    $bits = chr(0x00) . $rsa;
    $bitstr = pack('Ca*a*', 0x03, pm_identity_der_len(strlen($bits)), $bits);
    $algoid = hex2bin('300d06092a864886f70d0101010500');
    $spki = pack('Ca*a*', 0x30, pm_identity_der_len(strlen($algoid . $bitstr)), $algoid . $bitstr);
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function pm_identity_der_len($len)
{
    if ($len < 128) {
        return chr($len);
    }
    $out = ltrim(pack('N', $len), "\x00");
    return chr(0x80 | strlen($out)) . $out;
}

function pm_identity_index_list()
{
    $table = str_replace('`', '', pm_identity_table_name());
    $rows = pm_identity_db_query('SHOW INDEX FROM `' . $table . '`');
    $out = [];
    foreach ($rows ?: [] as $row) {
        $name = $row['Key_name'] ?? '';
        $col = $row['Column_name'] ?? '';
        $unique = ((int) ($row['Non_unique'] ?? 1)) === 0;
        if ($name && $col) {
            $out[] = ['name' => $name, 'column' => $col, 'unique' => $unique];
        }
    }
    return $out;
}

function pm_identity_has_unique($column)
{
    foreach (pm_identity_index_list() as $idx) {
        if ($idx['column'] === $column && $idx['unique'] && $idx['name'] !== 'PRIMARY') {
            return $idx['name'];
        }
    }
    return null;
}

function pm_identity_has_index($nameOrColumn, $unique = null)
{
    foreach (pm_identity_index_list() as $idx) {
        if ($idx['name'] !== $nameOrColumn && $idx['column'] !== $nameOrColumn) {
            continue;
        }
        if ($unique === null || $idx['unique'] === $unique) {
            return $idx['name'];
        }
    }
    return null;
}

function pm_identity_migrate()
{
    if (!pm_identity_bootstrap_qlo()) {
        return ['ok' => false, 'error' => '找不到 QloApps 資料庫連線（config.inc.php）。'];
    }
    $table = str_replace('`', '', pm_identity_table_name());
    $exists = pm_identity_db_value("SHOW TABLES LIKE '" . addslashes($table) . "'");
    if (!$exists) {
        return ['ok' => false, 'error' => '找不到資料表 ' . $table . '，請確認這是正式 qlo_pm_member。'];
    }
    $log = [];
    $cols = pm_identity_table_columns();
    if (!isset($cols['apple_sub'])) {
        pm_identity_db_exec('ALTER TABLE `' . $table . '` ADD COLUMN `apple_sub` VARCHAR(255) NULL DEFAULT NULL');
        $log[] = 'added apple_sub';
        pm_identity_reset_column_cache();
    }
    if (!pm_identity_has_column('google_sub')) {
        pm_identity_db_exec('ALTER TABLE `' . $table . '` ADD COLUMN `google_sub` VARCHAR(255) NULL DEFAULT NULL');
        $log[] = 'added google_sub';
        pm_identity_reset_column_cache();
    }
    if (!pm_identity_has_column('phone')) {
        pm_identity_db_exec('ALTER TABLE `' . $table . '` ADD COLUMN `phone` VARCHAR(32) NULL DEFAULT NULL');
        $log[] = 'added phone';
        pm_identity_reset_column_cache();
    }
    pm_identity_db_exec('UPDATE `' . $table . '` SET `apple_sub` = NULL WHERE `apple_sub` IS NOT NULL AND TRIM(`apple_sub`) = \'\'');
    pm_identity_db_exec('UPDATE `' . $table . '` SET `google_sub` = NULL WHERE `google_sub` IS NOT NULL AND TRIM(`google_sub`) = \'\'');
    if (pm_identity_has_column('phone')) {
        pm_identity_db_exec('UPDATE `' . $table . '` SET `phone` = NULL WHERE `phone` IS NOT NULL AND TRIM(`phone`) = \'\'');
        $dupPhone = pm_identity_db_query('SELECT `phone`, COUNT(*) c FROM `' . $table . '` WHERE `phone` IS NOT NULL GROUP BY `phone` HAVING c > 1');
        if ($dupPhone) {
            return ['ok' => false, 'error' => '電話有重複值，無法加上 UNIQUE。請先人工處理後再跑一次安裝。', 'duplicates' => $dupPhone, 'log' => $log];
        }
    }

    foreach (['apple_sub' => 'uniq_pm_member_apple_sub', 'google_sub' => 'uniq_pm_member_google_sub', 'phone' => 'uniq_pm_member_phone'] as $col => $idx) {
        if (!pm_identity_has_column($col)) {
            continue;
        }
        if (!pm_identity_has_unique($col)) {
            pm_identity_db_exec('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `' . $idx . '` (`' . $col . '`)');
            $log[] = 'unique ' . $col;
        }
    }

    if (pm_identity_has_column('email')) {
        $emailUnique = pm_identity_has_unique('email');
        if ($emailUnique) {
            pm_identity_db_exec('ALTER TABLE `' . $table . '` DROP INDEX `' . str_replace('`', '', $emailUnique) . '`');
            $log[] = 'dropped unique on email (' . $emailUnique . ')';
        }
        if (!pm_identity_has_index('email', false) && !pm_identity_has_index('idx_pm_member_email')) {
            pm_identity_db_exec('ALTER TABLE `' . $table . '` ADD INDEX `idx_pm_member_email` (`email`)');
            $log[] = 'index email';
        }
    }

    return [
        'ok' => true,
        'message' => pm_identity_msg('install_ok'),
        'table' => $table,
        'user_id_column' => 'id_member',
        'log' => $log,
        'columns' => array_keys(pm_identity_table_columns()),
    ];
}

function pm_identity_status_payload()
{
    $installed = false;
    $member = null;
    $userId = pm_identity_logged_in_user_id();
    try {
        if (pm_identity_bootstrap_qlo() && pm_identity_has_column('apple_sub') && pm_identity_has_column('google_sub')) {
            $installed = true;
            if ($userId) {
                $row = pm_identity_load_member_by_id($userId);
                if ($row) {
                    $member = pm_identity_public_member($row);
                }
            }
        }
    } catch (Throwable $e) {
        $installed = false;
    }
    return [
        'ok' => true,
        'installed' => $installed,
        'logged_in' => $member !== null,
        'member' => $member,
        'providers' => [
            'google' => pm_identity_google_client_id() !== '',
            'apple' => pm_identity_apple_client_id() !== '',
            'sms' => true,
        ],
        'identity' => [
            'user_id_column' => 'id_member',
            'lookup' => ['apple_sub', 'google_sub', 'phone'],
            'email_is_identity' => false,
        ],
    ];
}
