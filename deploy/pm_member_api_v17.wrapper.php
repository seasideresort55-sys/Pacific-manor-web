<?php
/**
 * Optional live wrapper: sanitize guest-facing JSON from existing v17 API.
 *
 * Operator steps (do not tell guests):
 * 1. On the host, rename the current pm_member_api_v17.php to pm_member_api_v17.core.php
 * 2. Upload this file as pm_member_api_v17.php
 * 3. Also upload pm_guest_errors.php next to it
 *
 * This does not invent SMS keys or database indexes. OTP still needs host SMS
 * credentials and the member email unique index — see OPERATOR_OTP.md.
 */
declare(strict_types=1);

require_once __DIR__ . '/pm_guest_errors.php';

$core = __DIR__ . '/pm_member_api_v17.core.php';
if (!is_file($core)) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => false, 'error' => PM_GUEST_SERVICE_UNAVAILABLE], JSON_UNESCAPED_UNICODE);
    exit;
}

ob_start();
require $core;
echo pm_guest_sanitize_json_output((string)ob_get_clean());
