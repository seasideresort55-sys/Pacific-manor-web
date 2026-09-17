<?php
/**
 * 身分 API 別名入口。不宣告 pms_member()，以免撞線上 social_lib。
 */
require_once __DIR__ . '/pm_member_identity_api.php';

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    pm_identity_dispatch_request();
}
