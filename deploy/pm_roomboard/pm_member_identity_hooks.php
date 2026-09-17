<?php
/**
 * 身分補丁（可安全加到線上）。不宣告 pms_schema / pms_member / qlo_pm_social_identity。
 *
 * 請保留線上 12KB 的 pm_member_social_lib.php（Google／LINE／Apple JWT、.p8）。
 * 只在該檔 require 本 hooks，並依 PATCH_LIVE_SOCIAL_LIB.txt 改幾處字串。
 */

require_once __DIR__ . '/pm_member_identity_lib.php';

if (!defined('PM_MEMBER_IDENTITY_HOOKS')) {
    define('PM_MEMBER_IDENTITY_HOOKS', '2026-09-17-hooks');
}

/** 給 pms_schema()：拿掉 Email UNIQUE，禁止再丟 Unique email migration required。 */
function pm_identity_schema_relax_email()
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
    return $result;
}

function pm_identity_empty_email_allowed()
{
    return true;
}

/**
 * 線上 OAuth 驗證完 sub 之後改呼叫這裡（不要用 Email 當唯一鍵）。
 */
function pm_identity_after_oauth_profile($provider, $subject, $email = '', $name = '', array $opts = [])
{
    $members = [];
    if (defined('PM_IDENTITY_TEST') && PM_IDENTITY_TEST) {
        $members = $GLOBALS['pm_identity_test_members'] ?? [];
    } else {
        $members = pm_identity_snapshot_for([
            'provider' => $provider,
            'sub' => $subject,
            'email' => $email,
            'logged_in_user_id' => $opts['logged_in_user_id'] ?? pm_identity_logged_in_user_id(),
        ]);
        if (function_exists('qlo_pm_social_identity')) {
            $id = (int) qlo_pm_social_identity($provider, $subject);
            if ($id > 0) {
                $row = pm_identity_load_member_by_id($id);
                if ($row) {
                    $members[] = $row;
                }
            }
        }
    }
    $decision = pm_identity_decide_oauth($members, [
        'provider' => strtolower((string) $provider),
        'sub' => $subject,
        'email' => $email,
        'name' => $name,
        'confirm_bind' => !empty($opts['confirm_bind']),
        'logged_in_user_id' => $opts['logged_in_user_id'] ?? (defined('PM_IDENTITY_TEST') && PM_IDENTITY_TEST ? 0 : pm_identity_logged_in_user_id()),
    ]);
    if (defined('PM_IDENTITY_TEST') && PM_IDENTITY_TEST) {
        return $decision;
    }
    if (empty($decision['ok'])) {
        return $decision;
    }
    return pm_identity_apply_decision($decision);
}

function pm_identity_phone_login_guest($phone, $verified, array $opts = [])
{
    $input = [
        'phone' => $phone,
        'phone_verified' => !empty($verified),
        'confirm_bind' => !empty($opts['confirm_bind']),
        'name' => $opts['name'] ?? '',
        'logged_in_user_id' => $opts['logged_in_user_id'] ?? (defined('PM_IDENTITY_TEST') && PM_IDENTITY_TEST ? 0 : pm_identity_logged_in_user_id()),
    ];
    if (defined('PM_IDENTITY_TEST') && PM_IDENTITY_TEST) {
        return pm_identity_decide_phone($GLOBALS['pm_identity_test_members'] ?? [], $input);
    }
    return pm_identity_run_phone($input);
}

function pm_identity_conflict_zh($provider)
{
    $key = $provider === 'phone' ? 'phone_conflict' : $provider . '_conflict';
    return pm_identity_msg($key);
}
