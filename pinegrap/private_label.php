<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Private Label Branding is a card of Site Settings › Appearance; this
 * address, linked and bookmarked for years, sends an administrator there.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();

// The card is the administrator's alone, as this screen was.
validate_area_access($user, 'administrator');

if (!defined('PG_SETTINGS_MENU')) {
    define('PG_SETTINGS_MENU', true);
}

include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_url('appearance') . '#pgset-private-label');
exit;
