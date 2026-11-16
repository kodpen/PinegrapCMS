<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Turn on" button of the Visitors section "Clicks and scrolling"
 * (includes/visitors/clicks.php): a form post that turns the switch on and
 * goes back to the section. Only for whoever may change Features, the right
 * the Features save asks (validate_area_access($user, 'manager')), with the
 * form's token. The setting is written by pg_page_clicks_switch_save()
 * (includes/fn/page_clicks.php), never here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'manager');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/visitors.php');
    exit;
}

validate_token_field();

if (pg_page_clicks_switch_save(true)) {
    log_activity(lang('clicks and scrolling counting was turned on'), $_SESSION['sessionusername']);
}

// Back to the section with the period the button was pressed in: only the
// characters of the period's own query (p, from, to, compare).
$back = (isset($_POST['back']) && is_string($_POST['back']) && preg_match('/^[a-z0-9_=&%\-]{0,120}$/i', $_POST['back'])) ? $_POST['back'] : '';

header('Location: ' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/visitors.php' . (($back !== '') ? '?' . $back : '') . '#clicks');
exit;
