<?php
/**
 * Plugin Name: Pacific Manor legacy member page redirects
 * Description: Send retired WordPress page_id entry points to working member / booking URLs instead of the homepage.
 * Version: 1.0.0
 *
 * Drop into wp-content/mu-plugins/ or wp-content/plugins/ and activate.
 * Do not expose setup notes on guest pages.
 */
if (!defined('ABSPATH')) {
    exit;
}

function pm_legacy_member_redirect_target(): ?string
{
    $pageId = 0;
    if (isset($_GET['page_id']) && ctype_digit((string)$_GET['page_id'])) {
        $pageId = (int)$_GET['page_id'];
    } elseif (function_exists('is_page') && is_page()) {
        $pageId = (int)get_queried_object_id();
    }
    $map = [
        11240 => '/booking/pm_roomboard/pm_member_portal_v17.php',
        2107 => '/booking/pm_front/',
        901 => '/booking/pm_roomboard/pm_member_center_v17.php',
    ];
    return $map[$pageId] ?? null;
}

$pmLegacyRedirect = static function () {
    $target = pm_legacy_member_redirect_target();
    if (!$target) {
        return;
    }
    if (function_exists('wp_safe_redirect') && function_exists('home_url')) {
        wp_safe_redirect(home_url($target), 301);
        exit;
    }
    header('Location: ' . $target, true, 301);
    exit;
};

add_action('init', $pmLegacyRedirect, 0);
add_action('template_redirect', $pmLegacyRedirect, 0);
