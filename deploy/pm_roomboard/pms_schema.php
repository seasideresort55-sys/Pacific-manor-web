<?php
/**
 * 加法 schema（不覆蓋線上 pms_schema()）。Email 不 UNIQUE。
 * 勿改名成 pm_member_social_lib.php。
 */
require_once __DIR__ . '/pm_member_identity_hooks.php';

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    $result = pm_identity_schema_relax_email();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
