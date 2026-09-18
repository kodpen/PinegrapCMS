<?php
/**
 * PineGrap - Customer-facing (and per-row admin) order cancellation endpoint.
 *
 * Visitor (or admin) submits a POST here from:
 *   - the order_view widget's "Siparişi İptal Et" form (customer self-service)
 *   - the admin view_orders.php per-row "İptal" button (admin context)
 *
 * Endpoint responsibilities:
 *   1. CSRF token validation (validate_token_field).
 *   2. Ownership / role gate — a customer can cancel only their own orders;
 *      an operator (USER_MANAGE_ECOMMERCE, i.e. anyone validate_ecommerce_access
 *      lets into the orders screens) can cancel any order.
 *   3. Delegate to process_order_cancellation() — that helper handles the
 *      status / shipment gates, the DB update, the email notification, and
 *      the optional Iyzipay auto-refund. Keeping that logic in one place
 *      means the bulk-cancel path in edit_orders.php shares the same code.
 *
 * Site settings (data/config.php defines):
 *   ECOMMERCE_ORDER_CANCEL_ALLOWED         — bool, must be true for customer
 *                                            self-service. Operators are NOT
 *                                            blocked by this flag (they
 *                                            always have escape-hatch access).
 *   ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED   — bool, default true. Blocks the
 *                                            CUSTOMER once a tracking code is
 *                                            recorded. Operators are never
 *                                            blocked by it.
 *   ECOMMERCE_CANCEL_EMAIL_NOTIFICATION    — bool, default true. Sends a
 *                                            cancellation confirmation email
 *                                            to the billing address.
 *   ECOMMERCE_ORDER_CANCEL_AUTO_REFUND     — bool, default false. Opt-in.
 *                                            Calls Iyzipay Cancel API and
 *                                            records refund_status.
 *
 * Request: POST order_id=N [cancellation_reason=text] token=csrf [send_to=path]
 * Response: 302 redirect back to send_to (or PATH) with ?cancelled=1|already|shipped|err
 */

include('init.php');

validate_token_field();

if (!defined('ECOMMERCE') || ECOMMERCE !== true) {
    output_error(lang('E-commerce is not enabled on this site.'));
}

$order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
if ($order_id <= 0) {
    output_error(lang('Order id is missing.'));
}

// ── Identify the caller ─────────────────────────────────────────────────
// "Operator" means anyone the backend already trusts with the orders screens,
// NOT just role 0. USER_MANAGE_ECOMMERCE carries exactly the predicate
// validate_ecommerce_access() uses (role < 3, or a contributor with the
// manage_ecommerce flag) and is only defined for logged-in users.
//
// Matching that rule matters: view_orders.php admits Managers and Designers,
// so gating on role === 0 meant a Manager's per-row cancel button fell into
// the CUSTOMER branch and was rejected as "not the order owner" — while the
// bulk "Cancel Selected" button on the same screen worked for them, because
// edit_orders.php passes is_admin unconditionally. Same screen, same user,
// two different answers.
$is_admin        = defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE === true;
$current_user_id = defined('USER_ID') ? (int) USER_ID : 0;

// Customers need the site-wide cancel-allowed flag. Operators bypass it (they
// already have full ecommerce access; the flag is for self-service UX).
if (!$is_admin) {
    $cancel_allowed = defined('ECOMMERCE_ORDER_CANCEL_ALLOWED') && ECOMMERCE_ORDER_CANCEL_ALLOWED === true;
    if (!$cancel_allowed) {
        output_error(lang('Order cancellation is not enabled on this site.'));
    }
}

// Ownership lookup — required for the customer path. Operators skip.
if (!$is_admin) {
    $owner_id = (int) db_value(
        "SELECT user_id FROM orders WHERE id = '" . e($order_id) . "' LIMIT 1"
    );
    if ($current_user_id <= 0 || $owner_id !== $current_user_id) {
        log_activity('Access denied — non-owner attempted to cancel order #' . $order_id);
        output_error(lang('You are not allowed to cancel this order.'));
    }
}

$reason = isset($_POST['cancellation_reason']) ? (string) $_POST['cancellation_reason'] : '';

$result = process_order_cancellation($order_id, $reason, $is_admin, $current_user_id);

// ── Redirect resolution ────────────────────────────────────────────────
// send_to is the page the order_view widget renders on (or the admin
// view_orders.php for the admin per-row case); the widget reads ?cancelled=*
// and renders the matching flash. PATH-only fallback when send_to is
// missing/junk.
$back = pg_safe_redirect_path($_POST['send_to'] ?? '', PATH);

$flag = 'err';
switch ($result['status']) {
    case 'success':   $flag = '1';       break;
    case 'already':   $flag = 'already'; break;
    case 'shipped':   $flag = 'shipped'; break;
    case 'not_found': $flag = 'notfound'; break;
}

// ── Backend flash ──────────────────────────────────────────────────────
// The customer-facing order_view widget reads the ?cancelled=* query flag,
// but the admin list screen renders liveform notices instead. When the POST
// came from view_orders.php (hidden admin_context field) push the outcome
// onto that screen's liveform so the operator gets real feedback rather than
// a silent page reload.
if (isset($_POST['admin_context']) && $_POST['admin_context'] === 'view_orders') {
    $liveform_view_orders = new liveform('view_orders');

    switch ($result['status']) {
        case 'success':
            $liveform_view_orders->add_notice(
                lang(array('string' => 'Order #{var:1} has been cancelled.', 'vars' => $order_id))
            );
            if ($result['refund_status'] === 'refunded') {
                $liveform_view_orders->add_notice(lang('The payment has been refunded.'));
            } elseif ($result['refund_status'] === 'manual_required' || $result['refund_status'] === 'failed') {
                $liveform_view_orders->add_notice(lang('The payment was NOT refunded automatically. Process the refund in your payment provider dashboard.'));
            }
            break;

        case 'already':
            $liveform_view_orders->mark_error('_error',lang('Order is already cancelled.'));
            break;

        case 'shipped':
            $liveform_view_orders->mark_error('_error',lang('This order has already shipped and can no longer be cancelled.'));
            break;

        default:
            $liveform_view_orders->mark_error('_error',lang('The order could not be cancelled.'));
            break;
    }
}

$sep = (strpos($back, '?') === false) ? '?' : '&';
header('Location: ' . URL_SCHEME . HOSTNAME . $back . $sep . 'cancelled=' . $flag);
exit();
