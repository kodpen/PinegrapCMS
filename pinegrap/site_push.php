<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor's door of visitor notifications (includes/fn/site_push.php).
 *
 * action=subscribe: the "Notify me" block posts the browser's endpoint, its
 * keys (p256dh, auth) and the page's language; the answer carries the
 * subscription's secret, given once. action=stop: the secret; the
 * subscription is removed. action=message: the site's worker, woken by an
 * empty push, posts its secret and gets its waiting message (title, body,
 * url), or none.
 * action=order with the secret and an order_id makes that browser wait for
 * the order's shipped and delivered moments: only the order this session has
 * just completed or one of the signed-in account's own.
 *
 * Anonymous by design, so there is no form token (a cached page has none):
 * posts from other sites are refused by pg_consent_answer_same_origin(), and
 * each address is held to its own rate (pg_site_push_throttled()). While the
 * "Visitor notifications" switch is off, or the 11.0.227 step has not run,
 * every action is 404 and nothing is written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
$action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
$kind = ($action === 'message') ? 'read' : 'write';
$enabled = ($method === 'POST') && pg_consent_answer_same_origin($_SERVER) && pg_site_push_enabled();

$refusal = pg_site_push_door_refusal($method, $_SERVER, $enabled, $enabled && pg_site_push_throttled($kind));
$status = 200;
$reply = array();

if ($refusal) {
    list($status, $error) = $refusal;
    $reply = array('error' => $error);

    if ($status === 405) {
        header('Allow: POST');
    }
} elseif (pg_post_body_discarded()) {
    $status = 413;
    $reply = array('error' => 'size');
} elseif ($action === 'subscribe') {
    $checked = pg_site_push_subscription_check($_POST);

    if (!is_array($checked)) {
        $status = 400;
        $reply = array('error' => $checked);
    } else {
        $secret = pg_site_push_subscription_save($checked, pg_site_push_user_id());

        if ($secret === false) {
            $status = 500;
            $reply = array('error' => 'save');
        } else {
            $reply = array('secret' => $secret);
        }
    }
} elseif ($action === 'stop') {
    $reply = array('removed' => pg_site_push_subscription_remove($_POST['secret'] ?? '') ? 1 : 0);
} elseif ($action === 'order') {
    // The order page's "Notify me on this device": the browser of the secret
    // waits for the order's shipped and delivered moments.
    $watched = pg_site_push_order_watch($_POST['secret'] ?? '', (int) ($_POST['order_id'] ?? 0));

    if (is_int($watched)) {
        $reply = array('watching' => 1);
    } else {
        $status = ($watched === 'off') ? 404 : 400;
        $reply = array('error' => $watched);
    }
} elseif ($action === 'message') {
    $message = pg_site_push_waiting_message($_POST['secret'] ?? '');
    $reply = array('message' => $message);
} else {
    $status = 400;
    $reply = array('error' => 'action');
}

http_response_code($status);
echo json_encode($reply);
