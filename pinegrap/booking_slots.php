<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The free days and times of the booking widget, and the visitor's hold on
 * a time (includes/fn/widgets_booking.php). Anonymous; the body is JSON, so
 * another site's plain form cannot post here.
 *
 *   {"action": "month", "service": id, "staff": id|0, "month": "YYYY-MM"}
 *        -> {"ok": true, "days": ["YYYY-MM-DD", …]}
 *   {"action": "day", "service": id, "staff": id|0, "day": "YYYY-MM-DD"}
 *        -> {"ok": true, "times": [{"start": unix, "staff": id, "label": "09:00"}, …]}
 *   {"action": "hold", "service": id, "staff": id, "start": unix}
 *        -> {"ok": true, "hold": {"until": unix, "label": "…"}}
 *        or {"ok": false, "error": "taken", "message": …}
 *        with "visitor": {name, email, phone} of a signed-in account
 *   {"action": "confirm", "name", "email", "phone", "notes", "consent"?}
 *        -> {"ok": true, "status": "confirmed"|"requested", "message": …}
 *        or {"ok": false, "error": "invalid", "fields": {field: sentence}}
 *        or {"ok": false, "error": "expired"|"taken", "message": …}
 *        or {"ok": true, "status": "pay", "pay": {"html": …}} for a service
 *        with a deposit (includes/fn/booking_deposit_pay.php)
 *   {"action": "payment"}
 *        -> {"ok": true, "state": "open"|"done"|"expired"|"none", …}
 *   {"action": "release"}
 *        -> {"ok": true}
 *
 * The deposit's payment itself is a form post here (pg_booking_pay=1 with
 * the form token), and a payment service's page comes back here with
 * ?mode=<return mode>; both end on the widget's page.
 *
 * Staff 0 is "anyone": the days of every staff member who takes the
 * service, and each time with the freest of them. A session holds one slot
 * at a time; a new hold or a release gives the previous one back. A request
 * refused before it is read is {"error": <code>, "message": <sentence>}
 * with its status: method 405, request 400, rate 429.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

pg_error_throws(true);

/**
 * Sends one JSON answer and stops.
 *
 * @param array $data
 * @param int   $status
 */
function booking_slots_answer($data, $status = 200)
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex');
    }

    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

// Back from a payment service's 3-D Secure or hosted page for a deposit:
// the service's request carries no form token.
if (isset($_GET['mode']) && is_string($_GET['mode']) && ($_GET['mode'] !== 'paypal_express_checkout_return') && pg_payment_is_return_mode($_GET['mode'])) {
    pg_booking_pay_return();
}

// The deposit's payment, posted from the widget's payment form.
if (isset($_POST['pg_booking_pay'])) {
    validate_token_field();

    if (pg_booking_throttled('hold')) {
        output_error(lang('Too many requests from your address. Please wait a moment and try again.'));
    }

    pg_booking_pay_retry(time());
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    booking_slots_answer(array('error' => 'method', 'message' => lang('Method not allowed.')), 405);
}

// The JSON actions carry no form token: a hold, a confirmation or a release
// from a page of another site would act on the visitor's booking once a
// checkout has re-issued the session cookie as SameSite=None
// (pg_request_cross_site()).
if (pg_request_cross_site($_SERVER)) {
    booking_slots_answer(array('error' => 'request', 'message' => lang('Invalid request.')), 403);
}

$request = json_decode((string) file_get_contents('php://input', false, null, 0, 16384), true);
$action = (is_array($request) && isset($request['action']) && is_string($request['action'])) ? $request['action'] : '';

if (!in_array($action, array('month', 'day', 'hold', 'confirm', 'release', 'payment'), true)) {
    booking_slots_answer(array('error' => 'request', 'message' => lang('Invalid request.')), 400);
}

$throttled = pg_booking_throttled(in_array($action, array('hold', 'confirm', 'release', 'payment'), true) ? 'hold' : 'read');

if ($throttled) {
    header('Retry-After: ' . $throttled);
    booking_slots_answer(array('error' => 'rate', 'message' => lang('Too many requests from your address. Please wait a moment and try again.')), 429);
}

try {
    $now = time();

    if ($action === 'release') {
        pg_booking_release_session_hold($now);
        booking_slots_answer(array('ok' => true));
    }

    if ($action === 'payment') {
        booking_slots_answer(pg_booking_pay_state_request());
    }

    if ($action === 'confirm') {
        booking_slots_answer(pg_booking_confirm_request($request, $now));
    }

    $answer = pg_booking_slots_request($request, $now);

    if ($answer === null) {
        booking_slots_answer(array('error' => 'request', 'message' => lang('Invalid request.')), 400);
    }

    booking_slots_answer($answer);
} catch (Throwable $error) {
    booking_slots_answer(array('ok' => false, 'error' => 'failed', 'message' => lang('Something went wrong. Please try again.')));
}
