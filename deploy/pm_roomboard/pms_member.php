<?php
/**
 * 線上會員建立／綁定入口（/booking/pm_roomboard/pms_member.php）。
 * 只靠 Apple／Google sub 或已驗證手機；Email 可空。
 */
require_once __DIR__ . '/pm_member_social_lib.php';
require_once __DIR__ . '/pm_member_identity_api.php';

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    pm_identity_dispatch_request();
}
