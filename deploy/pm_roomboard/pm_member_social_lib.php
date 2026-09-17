<?php
/**
 * 線上 drop-in：覆蓋 /booking/pm_roomboard/pm_member_social_lib.php
 *
 * 既有 qlo_pm_social_identity(provider, subject) 仍先用 sub 找 id_member。
 * 修正相對規格的缺口：
 *  - pms_schema 不再要求 Email UNIQUE（改拿掉 UNIQUE、留普通索引）
 *  - pms_member 允許沒有 Email／Apple Hide My Email 只靠 sub 建檔
 *  - Email 已在別的會員 → 綁定提示，不是硬擋、也不是靜默合併
 */

require_once __DIR__ . '/pm_member_identity_lib.php';

if (!defined('PM_MEMBER_SOCIAL_LIB')) {
    define('PM_MEMBER_SOCIAL_LIB', '2026-09-17-identity');
}

/**
 * provider + subject（OAuth sub）→ id_member（= user_id）。先查對照表，再查欄位。
 */
function qlo_pm_social_identity($provider, $subject)
{
    return pm_identity_lookup_social_id($provider, $subject);
}

function pms_social_identity($provider, $subject)
{
    return qlo_pm_social_identity($provider, $subject);
}

/**
 * 線上 schema 安裝。禁止再丟 Unique email migration required。
 */
function pms_schema($opts = [])
{
    if (defined('PM_IDENTITY_TEST') && PM_IDENTITY_TEST) {
        return [
            'ok' => true,
            'email_unique_required' => false,
            'user_id_column' => 'id_member',
            'message' => 'Email 不可當 UNIQUE。id_member = user_id。',
        ];
    }
    $result = pm_identity_migrate();
    $result['email_unique_required'] = false;
    if (!empty($result['ok'])) {
        unset($result['error']);
    }
    return $result;
}

/**
 * 會員建立／查找／綁定。
 *
 * 用法：
 *   pms_member('create', ['sub'=>'…','provider'=>'apple','email'=>null])
 *   pms_member('find', ['provider'=>'google','subject'=>'…'])
 *   pms_member('bind', ['provider'=>'google','sub'=>'…','confirm_bind'=>1])
 *   pms_member('phone_login', ['phone'=>'09…','phone_verified'=>true])
 *   pms_member($email, $name) 舊簽名：email 可空
 */
function pms_member($actionOrEmail = null, $payloadOrName = null, $extra = [])
{
    if (is_string($actionOrEmail) && in_array($actionOrEmail, ['create', 'find', 'bind', 'login', 'phone_login', 'phone_create', 'schema'], true)) {
        $action = $actionOrEmail;
        $payload = is_array($payloadOrName) ? $payloadOrName : [];
    } else {
        $action = 'create';
        $payload = array_merge(is_array($extra) ? $extra : [], [
            'email' => $actionOrEmail,
            'name' => $payloadOrName,
        ]);
    }

    if ($action === 'schema') {
        return pms_schema($payload);
    }
    if ($action === 'find') {
        $provider = $payload['provider'] ?? '';
        $subject = $payload['subject'] ?? $payload['sub'] ?? '';
        $id = qlo_pm_social_identity($provider, $subject);
        return $id > 0
            ? ['ok' => true, 'id_member' => $id, 'user_id' => $id]
            : ['ok' => false, 'id_member' => 0, 'user_id' => 0];
    }
    if ($action === 'phone_login' || $action === 'phone_create') {
        return pms_member_phone($payload);
    }
    if ($action === 'bind' || $action === 'login' || $action === 'create') {
        return pms_member_oauth($payload, $action === 'bind');
    }
    return ['ok' => false, 'error' => '無法辨識這個操作。'];
}

function pms_member_test_store()
{
    return isset($GLOBALS['pm_identity_test_members']) && is_array($GLOBALS['pm_identity_test_members'])
        ? $GLOBALS['pm_identity_test_members']
        : [];
}

function pms_member_oauth(array $payload, $forceBind = false)
{
    $provider = strtolower((string) ($payload['provider'] ?? $payload['network'] ?? ''));
    $sub = $payload['sub'] ?? $payload['subject'] ?? $payload['openid'] ?? '';
    $email = $payload['email'] ?? '';
    $name = $payload['name'] ?? $payload['firstname'] ?? '會員';

    if ($sub === '' || $sub === null) {
        return ['ok' => false, 'error' => pm_identity_msg('bad_sub')];
    }
    // 空 Email／Hide My Email：允許只靠 sub 建立，不再丟「登入服務未提供 Email」
    $email = pm_identity_normalize_email($email);

    $loggedIn = (int) ($payload['logged_in_user_id'] ?? $payload['id_member'] ?? 0);
    if ($loggedIn < 1) {
        $loggedIn = pm_identity_logged_in_user_id();
    }
    if ($forceBind && $loggedIn < 1) {
        return ['ok' => false, 'error' => pm_identity_msg('need_login'), 'next_step' => 'login_then_bind'];
    }

    $input = [
        'provider' => $provider,
        'sub' => $sub,
        'email' => $email,
        'name' => $name,
        'confirm_bind' => !empty($payload['confirm_bind']),
        'logged_in_user_id' => $loggedIn,
    ];
    if (defined('PM_IDENTITY_TEST') && PM_IDENTITY_TEST) {
        return pms_member_decorate(pm_identity_decide_oauth(pms_member_test_store(), $input));
    }
    return pms_member_decorate(pm_identity_run_oauth($input));
}

function pms_member_phone(array $payload)
{
    $input = [
        'phone' => $payload['phone'] ?? '',
        'phone_verified' => !empty($payload['phone_verified']),
        'confirm_bind' => !empty($payload['confirm_bind']),
        'name' => $payload['name'] ?? '',
        'logged_in_user_id' => $payload['logged_in_user_id'] ?? pm_identity_logged_in_user_id(),
    ];
    if (defined('PM_IDENTITY_TEST') && PM_IDENTITY_TEST) {
        return pms_member_decorate(pm_identity_decide_phone(pms_member_test_store(), $input));
    }
    return pms_member_decorate(pm_identity_run_phone($input));
}

function pms_member_decorate(array $result)
{
    if (!empty($result['user_id'])) {
        $result['id_member'] = (int) $result['user_id'];
        $result['user_id'] = (int) $result['user_id'];
    }
    if (($result['next_step'] ?? '') === 'confirm_bind' && empty($result['prompt'])) {
        $result['prompt'] = $result['error'] ?? pm_identity_msg('need_confirm');
    }
    return $result;
}

function pms_member_conflict_message($provider)
{
    $key = $provider === 'phone' ? 'phone_conflict' : $provider . '_conflict';
    return pm_identity_msg($key);
}

function pms_member_allow_empty_email()
{
    return true;
}

function pms_schema_requires_unique_email()
{
    return false;
}
