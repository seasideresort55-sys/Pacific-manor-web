<?php
/**
 * 線上 Google 輔助函式 drop-in。改走 sub，不再因沒有 Email 擋註冊。
 */
require_once __DIR__ . '/pm_member_social_lib.php';

function pm_google_find_or_create_member(array $profile, array $opts = [])
{
    $sub = $profile['sub'] ?? $profile['subject'] ?? '';
    if ($sub === '') {
        return ['ok' => false, 'error' => pm_identity_msg('bad_sub')];
    }
    return pms_member('create', [
        'provider' => 'google',
        'sub' => $sub,
        'email' => $profile['email'] ?? '',
        'name' => $profile['name'] ?? '會員',
        'confirm_bind' => !empty($opts['confirm_bind']),
        'logged_in_user_id' => $opts['logged_in_user_id'] ?? 0,
    ]);
}
