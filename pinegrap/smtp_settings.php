<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name Pinegrap.
 * The final LiveSite update (2019) has been integrated into Pinegrap.
 * LiveSite remains available as a separate downloadable legacy version.
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

// The screen was a manager's, as the category is.
validate_area_access($user, 'manager');

if (!defined('PG_SETTINGS_MENU')) {
    define('PG_SETTINGS_MENU', true);
}

include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

// SMTP Settings is the E-mail sending category of Site Settings now. The
// browser keeps an old address's #anchor (#mail_domain_card) across the
// redirect, and the category's cards still carry it.
header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_url('mail_sending'));
exit;
