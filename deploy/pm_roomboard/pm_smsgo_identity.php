<?php
/**
 * 身分綁定用 SMS Go 發送（不覆蓋線上 pm_smsgo_adapter.php）。
 * 對齊 sendsms.aspx：username + API Key、核准模板 {code}。
 */

require_once __DIR__ . '/pm_member_identity_lib.php';

define('PM_SMSGO_SEND_URL', 'https://www.smsgo.com.tw/sms_gw/sendsms.aspx');
define('PM_SMSGO_QUERY_URL', 'https://www.smsgo.com.tw/sms_gw/query.aspx');
define('PM_SMSGO_TEMPLATE', '太平洋莊園手機驗證碼：{code}，10分鐘內有效，請勿提供他人。');

function pm_smsgo_identity_config()
{
    $username = pm_identity_read_secret('smsgo-username.txt', ['SMSGO_USERNAME']);
    $apiKey = pm_identity_read_secret('smsgo-api-key.txt', ['SMSGO_API_KEY', 'SMSGO_PASSWORD']);
    $template = pm_identity_read_secret('smsgo-template.txt', ['SMSGO_APPROVED_TEMPLATE']);
    if ($template === '') {
        $template = PM_SMSGO_TEMPLATE;
    }
    $enabledEnv = getenv('SMSGO_ENABLED');
    $enabled = $enabledEnv === false || $enabledEnv === ''
        ? ($username !== '' && $apiKey !== '')
        : in_array(strtolower((string) $enabledEnv), ['1', 'true', 'yes'], true);
    return [
        'username' => $username,
        'api_key' => $apiKey,
        'template' => $template,
        'configured' => $username !== '' && $apiKey !== '',
        'enabled' => $enabled,
    ];
}

function pm_smsgo_identity_user_message($statuscode)
{
    $map = [
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
        -23 => '簡訊缺少 NCC 署名。請補上核准模板署名。',
    ];
    return $map[(int) $statuscode] ?? '簡訊閘道暫時無法完成，請稍後再試。';
}

function pm_smsgo_identity_parse($http, $body)
{
    if ((int) $http !== 200 || strlen($body) > 32768) {
        return ['state' => 'unknown', 'statuscode' => -10, 'message_id' => null];
    }
    $node = null;
    $text = trim($body);
    if ($text !== '' && $text[0] === '{') {
        $parsed = json_decode($text, true);
        if (is_array($parsed)) {
            $node = isset($parsed['result']) && is_array($parsed['result']) ? $parsed['result'] : $parsed;
        }
    }
    if (!$node) {
        $fields = [];
        foreach (preg_split('/[\r\n&]+/', $text) as $line) {
            $i = strpos($line, '=');
            if ($i < 1) {
                continue;
            }
            $fields[strtolower(trim(substr($line, 0, $i)))] = trim(substr($line, $i + 1));
        }
        $node = $fields ?: null;
    }
    if (!$node) {
        return ['state' => 'unknown', 'statuscode' => -10, 'message_id' => null];
    }
    $code = (string) ($node['statuscode'] ?? '');
    $id = trim((string) ($node['msgid'] ?? ''));
    $messageId = preg_match('/^[0-9]{1,96}$/', $id) ? $id : null;
    if ($code === '0' && $messageId) {
        return ['state' => 'accepted', 'statuscode' => 0, 'message_id' => $messageId];
    }
    return ['state' => 'rejected', 'statuscode' => (int) $code, 'message_id' => null];
}

function pm_smsgo_identity_send($e164, $code)
{
    $e164 = pm_identity_normalize_phone($e164);
    if (!$e164 || !preg_match('/^\d{6}$/', (string) $code)) {
        return ['ok' => false, 'error' => pm_identity_msg('bad_phone')];
    }
    $config = pm_smsgo_identity_config();
    if (!$config['configured']) {
        return ['ok' => false, 'error' => '簡訊金鑰尚未設定。請把 SMS Go 帳號與 API Key 放在主機 pm_member_private，不要寫進網站檔案。'];
    }
    if (!$config['enabled']) {
        return ['ok' => false, 'error' => '簡訊發送已關閉（SMSGO_ENABLED）。'];
    }
    if (substr_count($config['template'], '{code}') !== 1) {
        return ['ok' => false, 'error' => '簡訊模板必須恰好包含一個 {code}。'];
    }
    $smbody = str_replace('{code}', $code, $config['template']);
    if (function_exists('mb_strlen') && mb_strlen($smbody, 'UTF-8') > 70) {
        return ['ok' => false, 'error' => '簡訊內容超過 70 字。'];
    }
    $dstaddr = pm_identity_phone_local($e164);
    list($http, $body) = pm_identity_http_post(PM_SMSGO_SEND_URL, [
        'username' => $config['username'],
        'password' => $config['api_key'],
        'dstaddr' => $dstaddr,
        'smbody' => $smbody,
        'encoding' => 'UTF-8',
        'rtype' => 'JSON',
    ]);
    $parsed = pm_smsgo_identity_parse($http, $body);
    if ($parsed['state'] === 'accepted') {
        return ['ok' => true, 'message_id' => $parsed['message_id']];
    }
    return ['ok' => false, 'error' => pm_smsgo_identity_user_message($parsed['statuscode'])];
}

/**
 * 若線上 v17 需要 pm_phone_host_dispatch() 而 adapter 沒載入，提供最小實作。
 * 已登入會員的手機綁定改走身分 API；此函式避免原頁面白字 PHP 錯誤。
 */
if (!function_exists('pm_phone_host_dispatch')) {
    function pm_phone_host_dispatch($input = null)
    {
        if (!is_array($input)) {
            $input = pm_identity_request_payload();
        }
        $action = preg_replace('/^phone_/', '', (string) ($input['action'] ?? ''));
        if ($action === 'status') {
            $userId = pm_identity_logged_in_user_id();
            $row = $userId && pm_identity_bootstrap_qlo() ? pm_identity_load_member_by_id($userId) : null;
            $verified = $row && !empty($row['phone']);
            return ['ok' => true, 'code' => 'status', 'verified' => $verified, 'message' => $verified ? '手機已完成驗證。' : '手機尚未完成驗證。'];
        }
        return ['ok' => false, 'code' => 'unavailable', 'error' => '請改用會員身分綁定頁完成手機驗證。', 'message' => '請改用會員身分綁定頁完成手機驗證。'];
    }
}
