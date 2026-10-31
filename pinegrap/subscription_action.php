<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What a signed-in customer does with one of their subscriptions from the
 * my_account widget's subscriptions section: pause, resume, cancel at the
 * end of the paid period, or open the payment service's card page
 * (pg_subscription_customer_action(), includes/fn/subscriptions_account.php).
 *
 * POST only, with the form token. Another customer's subscription is
 * answered as not found. The customer is sent back to send_to (a path on
 * this site, the My Account page without one) with a notice for the
 * my_account widget, or to the payment service's https page (303).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    output_error(h(lang('Method not allowed.')), 405);
}

validate_token_field();

pg_request_single_values(array('id', 'action', 'send_to'));

$subscription_user_id = (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ID')) ? (int) USER_ID : 0;

if ($subscription_user_id <= 0) {
    output_error(h(lang('You must be logged in to view this page.')), 403);
}

$subscription_account_url = (string) get_page_type_url('my account');
$subscription_send_to = pg_safe_redirect_path($_POST['send_to'] ?? '', ($subscription_account_url !== '') ? $subscription_account_url : '');

$subscription_result = pg_subscription_customer_action(
    $subscription_user_id,
    (int) ($_POST['id'] ?? 0),
    (string) ($_POST['action'] ?? ''),
    URL_SCHEME . HOSTNAME_SETTING . $subscription_send_to
);

if ($subscription_result['result'] === 'redirect') {
    header('Location: ' . $subscription_result['url'], true, 303);
    exit;
}

include_once('liveform.class.php');
$subscription_notices = new liveform('my_account');

switch ($subscription_result['result']) {
    case 'done':
        $subscription_messages = array(
            'pause'  => lang('Your subscription has been paused.'),
            'resume' => lang('Your subscription has been resumed.'),
            'cancel' => lang('Your subscription has been cancelled. No further charges will be made.'),
        );
        $subscription_notices->add_notice(h($subscription_messages[(string) $_POST['action']]));
        break;

    case 'failed':
        $subscription_notices->add_error(h(lang('The payment service\'s card page could not be opened. Please try again later.')));
        break;

    case 'refused':
        $subscription_notices->add_error(h(lang('This cannot be done with the subscription as it is now.')));
        break;

    default:
        $subscription_notices->add_error(h(lang('The subscription could not be found.')));
}

go($subscription_send_to);
