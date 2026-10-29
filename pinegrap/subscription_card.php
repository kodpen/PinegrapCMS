<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The page behind the card update link a failed subscription charge mails
 * (pg_subscription_card_url(), includes/fn/subscription_renewal.php). Opening
 * the link changes nothing: mail scanners and link previews open links on
 * their own. The page shows a button, and only its POST, with the form token
 * and the same signed values, asks the payment service for its card page
 * (the driver's update_card()) and sends the customer there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A personal answer: never kept by a cache, never indexed, and the signed
// address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$pg_sc_post = (isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] === 'POST'));

if ($pg_sc_post) {
    validate_token_field();
}

$pg_sc_source = $pg_sc_post ? $_POST : $_GET;
$pg_sc_id = (isset($pg_sc_source['s']) && is_string($pg_sc_source['s'])) ? (int) $pg_sc_source['s'] : 0;
$pg_sc_row = (($pg_sc_id > 0) && pg_subscriptions_ready())
    ? db_item("SELECT * FROM subscriptions WHERE id = '" . e($pg_sc_id) . "'")
    : null;
$pg_sc_state = pg_subscription_card_link_state($pg_sc_source, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '', $pg_sc_row ?: null, time());

if ($pg_sc_post && ($pg_sc_state === 'open')) {
    $pg_sc_answer = pg_payment_call((string) $pg_sc_row['driver'], 'update_card', array(pg_subscription_driver_row($pg_sc_row)));
    $pg_sc_url = (string) ($pg_sc_answer['redirect_url'] ?? '');

    if (!empty($pg_sc_answer['success']) && (preg_match('~^https://~i', $pg_sc_url) === 1)) {
        header('Location: ' . $pg_sc_url, true, 303);
        exit;
    }

    $pg_sc_state = 'failed';
}

switch ($pg_sc_state) {
    case 'open':
        $pg_sc_message = lang('Press the button below to enter a new card for your subscription on the payment service\'s page.');
        break;

    case 'failed':
        $pg_sc_message = lang('The payment service\'s card page could not be opened. Please try again later.');
        break;

    case 'expired':
        $pg_sc_message = lang('This link has expired. Please contact us to update your card.');
        break;

    case 'closed':
        $pg_sc_message = lang('This subscription no longer runs, so its card cannot be changed.');
        break;

    default:
        $pg_sc_message = lang('This link cannot be used. Open the whole link from the e-mail.');
}

$pg_sc_content = '<p>' . h($pg_sc_message) . '</p>';

if (($pg_sc_state === 'open') || ($pg_sc_state === 'failed')) {
    $pg_sc_content .=
        '<form method="post" action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/subscription_card.php') . '" class="mt-5">'
        . get_token_field()
        . '<input type="hidden" name="s" value="' . h((string) $pg_sc_source['s']) . '"/>'
        . '<input type="hidden" name="t" value="' . h((string) $pg_sc_source['t']) . '"/>'
        . '<input type="hidden" name="k" value="' . h((string) $pg_sc_source['k']) . '"/>'
        . '<button type="submit" class="btn btn-solid theme-primary">' . h(lang('Update my card')) . '</button>'
        . '</form>';
}

print output_header_secure(array('title' => lang('Update your card')))
    . pg_secure_card('bi-credit-card', lang('Update your card'), $pg_sc_content)
    . output_footer_secure();
