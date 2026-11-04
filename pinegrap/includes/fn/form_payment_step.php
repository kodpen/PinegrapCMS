<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The payment step of a designed form on the site. A form with a fee
 * (includes/fn/form_payments.php) gets a last step: the amount, the payment
 * choice (the hosted card driver, the redirect drivers switched on and, when
 * the form and the store allow it, offline payment) and the card driver's
 * browser part (pg_payment_box()). On submit custom_form.php saves the
 * record unpaid, writes its fee order and charges it here; the completion of
 * the order (pg_order_complete()) marks the record paid. A 3-D Secure or
 * hosted page brings the visitor back to custom_form.php?mode=<return mode>;
 * the service's own message completes the order through
 * includes/payments/callback.php as for any order.
 *
 * A payment that is refused keeps the record unpaid and its order in the
 * session; the form's page then shows only the payment, and a retry charges
 * the same order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The payment choices of a form. Pure.
 *
 * @param bool  $hosted_card    a card driver takes the card in its own fields
 * @param array $driver_methods the redirect drivers' methods switched on
 * @param bool  $offline_on     the store takes offline payments
 * @param mixed $form_offline   the form allows offline payment
 * @return array methods: the card, the redirect drivers, offline payment
 */
function pg_form_payment_methods_decide($hosted_card, $driver_methods, $offline_on, $form_offline)
{
    $methods = $hosted_card ? array('Credit/Debit Card') : array();

    foreach ((array) $driver_methods as $method) {
        if (is_string($method) && ($method !== '') && !in_array($method, array('Credit/Debit Card', 'Offline Payment'), true)
            && !in_array($method, $methods, true)) {
            $methods[] = $method;
        }
    }

    if ($offline_on && !empty($form_offline)) {
        $methods[] = 'Offline Payment';
    }

    return $methods;
}

/**
 * The payment choices of a form on this site, method => label.
 *
 * @param mixed $form_offline the form (or its order) allows offline payment
 * @return array
 */
function pg_form_payment_methods($form_offline)
{
    $card_on = defined('ECOMMERCE_CREDIT_DEBIT_CARD') && (ECOMMERCE_CREDIT_DEBIT_CARD == true);
    $offline_on = defined('ECOMMERCE_OFFLINE_PAYMENT') && (ECOMMERCE_OFFLINE_PAYMENT == true);
    $labels = array();

    foreach (pg_form_payment_methods_decide($card_on && (pg_payment_hosted_card_driver() !== ''),
        array_keys(pg_payment_checkout_driver_methods(false)), $offline_on, $form_offline) as $method) {
        $labels[$method] = pg_payment_method_label($method);
    }

    return $labels;
}

/**
 * The payment step's markup. Pure.
 *
 * $view: amount (HTML of the amount line), methods (method => label),
 * chosen (the posted method), box (the card driver's box, printed only when
 * the card is a choice), error (the driver's reason, text), offline_note
 * (the line under the offline choice, text; the form's when not given).
 *
 * One choice is posted as a hidden value; several are radios. The card box
 * follows the choice itself (assets/js/payment_hosted.js).
 *
 * @param array $view
 * @return string
 */
function pg_form_payment_step_html($view)
{
    $methods = (isset($view['methods']) && is_array($view['methods'])) ? $view['methods'] : array();
    $chosen = isset($view['chosen']) ? (string) $view['chosen'] : '';
    $html = '<div class="pg-cf-payment mb-3" data-pg-cf-payment>'
          . '<p class="mb-2"><span class="text-body-secondary">' . h(lang('Amount')) . ':</span> <strong>' . (string) ($view['amount'] ?? '') . '</strong></p>';

    if (isset($view['error']) && (string) $view['error'] !== '') {
        $html .= '<div class="alert alert-danger" role="alert">' . h((string) $view['error']) . '</div>';
    }

    if ($methods === array()) {
        return $html . '<div class="alert alert-warning" role="alert">' . h(lang('Payment is not available right now. Please try again later.')) . '</div></div>';
    }

    if (count($methods) === 1) {
        $html .= '<input type="hidden" name="payment_method" value="' . h((string) key($methods)) . '">'
               . '<p class="mb-2">' . h(lang('Payment Method')) . ': ' . h((string) reset($methods)) . '</p>';
    } else {
        $html .= '<fieldset class="mb-3"><legend class="form-label fs-6">' . h(lang('Payment Method')) . '</legend>';
        $n = 0;

        foreach ($methods as $method => $label) {
            $id = 'pg-cf-payment-method-' . (++$n);
            $html .= '<div class="form-check">'
                   . '<input class="form-check-input" type="radio" name="payment_method" id="' . $id . '" value="' . h((string) $method) . '" required'
                   . ((((string) $method === $chosen) || (($chosen === '') && ($n === 1))) ? ' checked' : '') . '>'
                   . '<label class="form-check-label" for="' . $id . '">' . h((string) $label) . '</label></div>';
        }

        $html .= '</fieldset>';
    }

    if (isset($methods['Credit/Debit Card'])) {
        $html .= (string) ($view['box'] ?? '');
    }

    if (isset($methods['Offline Payment'])) {
        $html .= '<p class="small text-body-secondary" data-pg-cf-offline>'
               . h(isset($view['offline_note']) ? (string) $view['offline_note'] : lang('With offline payment your submission is saved and you pay as the site tells you.')) . '</p>';
    }

    return $html . '</div>';
}

/**
 * Puts the payment step into a form's tree: before the submit button (and a
 * CAPTCHA right before it), or at the end of the form when there is none.
 * In a stepped form (pg_cf_apply_steps()) whose stepped container holds the
 * submit, a step break goes first, so the payment is the last step with the
 * submit. Pure.
 *
 * @param array  $tree  the form's tree (by reference)
 * @param string $html  pg_form_payment_step_html()
 * @param string $title the payment step's title
 * @return bool
 */
function pg_cf_insert_payment_step(&$tree, $html, $title)
{
    if (!is_array($tree)) {
        return false;
    }

    $node = array('type' => 'content', 'children' => array(), 'props' => array('contentType' => 'custom_html', 'html' => (string) $html));
    $path = _pg_cf_submit_path($tree);

    if ($path === null) {
        $tree['children'] = isset($tree['children']) && is_array($tree['children']) ? $tree['children'] : array();
        $tree['children'][] = $node;

        return true;
    }

    // The first container on the path that holds step breaks takes the
    // break; otherwise the submit's own container takes the step.
    $holder = &$tree;
    $depth = count($path) - 1;

    for ($i = 0; $i < count($path); $i++) {
        if (pg_cf_step_groups($holder['children']) !== null) {
            $depth = $i;
            break;
        }

        if ($i < count($path) - 1) {
            $holder = &$holder['children'][$path[$i]];
        }
    }

    $stepped = (pg_cf_step_groups($holder['children']) !== null);
    $at = $path[$depth];

    while (!$stepped && ($at > 0) && (($holder['children'][$at - 1]['props']['_bindings']['section'] ?? '') === 'captcha')) {
        $at--;
    }

    $insert = $stepped
        ? array(array('type' => 'semantic', 'children' => array(), 'props' => array(
            'tag' => 'div', 'cssClass' => 'h5', 'text' => (string) $title, '_bindings' => array('section' => 'form_step'))), $node)
        : array($node);
    array_splice($holder['children'], $at, 0, $insert);

    return true;
}

// The child indexes from $node down to its first submit button, or null.
function _pg_cf_submit_path($node)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children'])) {
        return null;
    }

    foreach (array_values($node['children']) as $i => $child) {
        if (_pg_cf_is_submit($child)) {
            return array($i);
        }

        $below = _pg_cf_submit_path($child);

        if ($below !== null) {
            return array_merge(array($i), $below);
        }
    }

    return null;
}

// Is $node a submit button (a button or input of type submit, or a button
// component set to submit)?
function _pg_cf_is_submit($node)
{
    $props = (is_array($node) && isset($node['props']) && is_array($node['props'])) ? $node['props'] : array();

    if (($props['btnType'] ?? '') === 'submit') {
        return true;
    }

    if (!in_array($props['tag'] ?? '', array('button', 'input'), true)) {
        return false;
    }

    foreach ((array) ($props['_attrs'] ?? array()) as $attr) {
        if (is_array($attr) && (($attr['name'] ?? '') === 'type') && (($attr['value'] ?? '') === 'submit')) {
            return true;
        }
    }

    return false;
}

/**
 * The UPDATE for db_q() that writes on a fee order still incomplete the
 * payment method, the payment's id and its figures, which its completion
 * (pg_order_complete_from_payment()) reads back. Pure.
 *
 * @param int    $order_id
 * @param string $method
 * @param string $transaction_id '' for offline payment
 * @param int    $total          minor units
 * @return array|null sql, params
 */
function pg_form_payment_settle_query($order_id, $method, $transaction_id, $total)
{
    if ((int) $order_id <= 0) {
        return null;
    }

    return array(
        'sql'    => "UPDATE orders SET payment_method = ?, transaction_id = ?, subtotal = ?, total = ? WHERE id = ? AND status = 'incomplete'",
        'params' => array((string) $method, (string) $transaction_id, (string) (int) $total, (string) (int) $total, (string) (int) $order_id),
    );
}

/**
 * What a driver's answer to authorize() or complete() does to a fee order.
 * Pure.
 *
 * @param array $answer pg_payment_answer()
 * @return array outcome: complete (status), redirect (html, redirect_url)
 *               or failed (error)
 */
function pg_form_payment_answer_outcome($answer)
{
    $status = (string) ($answer['status'] ?? '');

    if (!empty($answer['success']) && in_array($status, array('paid', 'authorized', 'pending'), true)) {
        return array('outcome' => 'complete', 'status' => $status);
    }

    if (!empty($answer['success']) && ($status === 'redirect')) {
        return array('outcome' => 'redirect', 'html' => (string) ($answer['html'] ?? ''), 'redirect_url' => (string) ($answer['redirect_url'] ?? ''));
    }

    $error = trim((string) ($answer['error'] ?? ''));

    return array('outcome' => 'failed', 'error' => ($error !== '') ? $error : lang('The payment was not accepted. Please try again.'));
}

/**
 * The billing contact of a fee order from the record's contact. Pure.
 *
 * @param array|null $contact the contacts row, or null
 * @param string     $email   the submitter's address
 * @param int        $user_id
 * @return array see pg_form_payment_order_columns()
 */
function pg_form_payment_contact($contact, $email, $user_id)
{
    $contact = is_array($contact) ? $contact : array();
    $get = function ($key) use ($contact) {
        return isset($contact[$key]) && is_scalar($contact[$key]) ? (string) $contact[$key] : '';
    };

    return array(
        'contact_id'    => (int) ($contact['id'] ?? 0),
        'user_id'       => (int) $user_id,
        'first_name'    => $get('first_name'),
        'last_name'     => $get('last_name'),
        'company'       => $get('company'),
        'email_address' => ($get('email_address') !== '') ? $get('email_address') : (string) $email,
        'phone_number'  => ($get('business_phone') !== '') ? $get('business_phone') : (($get('mobile_phone') !== '') ? $get('mobile_phone') : $get('home_phone')),
        'address_1'     => $get('business_address_1'),
        'address_2'     => $get('business_address_2'),
        'city'          => $get('business_city'),
        'state'         => $get('business_state'),
        'zip_code'      => $get('business_zip_code'),
        'country'       => $get('business_country'),
    );
}

/* ---------------------------------------------------------------------------
   The form's setting and the session's open payment
   --------------------------------------------------------------------------- */

/**
 * A form's payment setting with its fee product's price, or null for a form
 * without a fee (or an installation before the fee's columns).
 *
 * @param int $page_id
 * @return array|null pg_form_fee() settings
 */
function pg_form_payment_settings($page_id)
{
    if (((int) $page_id <= 0) || !pg_form_payment_ready()) {
        return null;
    }

    $settings = pg_form_fee_settings_decode(db_value("SELECT payment FROM custom_form_pages WHERE page_id = '" . (int) $page_id . "' LIMIT 1"));

    if ($settings['product_id'] <= 0) {
        return null;
    }

    $settings['product_price'] = (int) db_value("SELECT price FROM products WHERE id = '" . (int) $settings['product_id'] . "' LIMIT 1");

    // A product priced 0 asks for no payment (pg_form_fee()).
    return (($settings['amount_source'] === 'product') && ($settings['product_price'] <= 0)) ? null : $settings;
}

// An amount in the site's currency, for the payment step.
function _pg_form_payment_money($minor)
{
    return pg_format_money((int) $minor / 100, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '');
}

// The amount line of a form: the product's price, or the visitor's figure
// with its bounds.
function _pg_form_payment_amount_html($settings)
{
    if ($settings['amount_source'] === 'product') {
        return _pg_form_payment_money($settings['product_price']);
    }

    $text = h(lang('The amount you enter in the form'));

    if ($settings['amount_min'] > 0) {
        $text .= ' · ' . h(lang('at least')) . ' ' . _pg_form_payment_money($settings['amount_min']);
    }

    if ($settings['amount_max'] > 0) {
        $text .= ' · ' . h(lang('at most')) . ' ' . _pg_form_payment_money($settings['amount_max']);
    }

    return $text;
}

// The card driver's box for an amount, '' when the card is no choice.
function _pg_form_payment_box($methods, $order, $amount)
{
    $key = isset($methods['Credit/Debit Card']) ? pg_payment_hosted_card_driver() : '';

    if ($key === '') {
        return '';
    }

    $order = is_array($order) ? $order : array();
    $order['recurring'] = false;

    return pg_payment_box($key, pg_payment_call($key, 'prepare', array($order, max(100, (int) $amount))));
}

/**
 * The payment this session left open on a form: record_id, order_id,
 * reference_code, send_to, driver, state, error. Null when there is none.
 *
 * @param int $page_id
 * @return array|null
 */
function pg_form_payment_pending($page_id)
{
    $pending = $_SESSION['software']['form_payment'][(int) $page_id] ?? null;

    return (is_array($pending) && ((int) ($pending['order_id'] ?? 0) > 0)) ? $pending : null;
}

function pg_form_payment_pending_set($page_id, array $pending)
{
    $_SESSION['software']['form_payment'][(int) $page_id] = $pending;
}

function pg_form_payment_pending_forget($page_id)
{
    unset($_SESSION['software']['form_payment'][(int) $page_id]);
}

/* ---------------------------------------------------------------------------
   The site: the step in the form, and the payment alone
   --------------------------------------------------------------------------- */

/**
 * What the custom_form renderer draws for a form with a fee: '' (no fee),
 * the payment step to put into the form, or, while this session has the
 * form's payment open, the payment alone (replace) or the confirmation
 * message once the order was paid elsewhere (the service's message).
 *
 * @param int         $page_id
 * @param object|null $liveform the form's liveform
 * @return array kind ('' | step | replace), html
 */
function pg_form_payment_render($page_id, $liveform)
{
    $settings = pg_form_payment_settings($page_id);
    $pending = pg_form_payment_pending($page_id);

    if ($pending !== null) {
        $order = db_item("SELECT * FROM orders WHERE id = '" . (int) $pending['order_id'] . "'");

        if (is_array($order) && ($order['status'] === 'incomplete')) {
            return array('kind' => 'replace', 'html' => pg_form_payment_retry_html($page_id, $pending, $order));
        }

        pg_form_payment_pending_forget($page_id);

        if (is_array($order)) {
            $message = db_value("SELECT confirmation_message FROM custom_form_pages WHERE page_id = '" . (int) $page_id . "' LIMIT 1");

            return array('kind' => 'replace', 'html' => '<div class="confirmation_message alert alert-success">' . pg_cf_confirmation_message_or_default((string) $message) . '</div>');
        }
    }

    if ($settings === null) {
        return array('kind' => '', 'html' => '');
    }

    $methods = pg_form_payment_methods($settings['offline']);
    $amount = ($settings['amount_source'] === 'product') ? $settings['product_price'] : $settings['amount_min'];

    return array('kind' => 'step', 'html' => pg_form_payment_step_html(array(
        'amount'  => _pg_form_payment_amount_html($settings),
        'methods' => $methods,
        'chosen'  => $liveform ? (string) $liveform->get_field_value('payment_method') : '',
        'box'     => _pg_form_payment_box($methods, array(), $amount),
        'error'   => '',
    )));
}

/**
 * The payment alone, for a record saved unpaid: the order's amount, the
 * choices, the driver's reason the last attempt was refused (said once),
 * a Pay button and a way to start a new submission instead.
 *
 * @param int   $page_id
 * @param array $pending pg_form_payment_pending()
 * @param array $order   the orders row
 * @return string
 */
function pg_form_payment_retry_html($page_id, array $pending, array $order)
{
    $action = h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/custom_form.php');
    $methods = pg_form_payment_methods($order['offline_payment_allowed'] ?? 0);
    $error = (string) ($pending['error'] ?? '');

    if ($error !== '') {
        $pending['error'] = '';
        pg_form_payment_pending_set($page_id, $pending);
    }

    $hidden = get_token_field() . '<input type="hidden" name="page_id" value="' . (int) $page_id . '">';
    $html = '<div class="pg-cf-payment-retry">'
          . '<p>' . h(lang('Your submission is saved. Complete the payment to send it.')) . '</p>';

    // A payment the service is still confirming (the visitor came back from
    // its page before its message arrived) is not offered blindly again.
    if ((string) ($order['transaction_id'] ?? '') !== '') {
        $html .= '<div class="alert alert-info" role="status">' . h(lang('If you have just paid, the payment may take a moment to be confirmed. Reload this page before paying again.')) . '</div>';
    }

    $html .= '<form action="' . $action . '" method="post" class="mb-2">' . $hidden
           . '<input type="hidden" name="pg_form_pay" value="1">'
           . pg_form_payment_step_html(array(
               'amount'  => _pg_form_payment_money($order['total']),
               'methods' => $methods,
               'chosen'  => '',
               'box'     => _pg_form_payment_box($methods, $order, (int) $order['total']),
               'error'   => $error,
           ))
           . (($methods !== array()) ? '<button type="submit" class="btn btn-primary">' . h(lang('Pay')) . '</button>' : '')
           . '</form>'
           . '<form action="' . $action . '" method="post">' . $hidden
           . '<input type="hidden" name="pg_form_pay_discard" value="1">'
           . '<button type="submit" class="btn btn-link px-0">' . h(lang('Start a new submission')) . '</button>'
           . '</form></div>';

    return $html;
}

/* ---------------------------------------------------------------------------
   custom_form.php: the check, the charge and the answers
   --------------------------------------------------------------------------- */

/**
 * The fee a submission owes, checked before anything is stored: a refused
 * amount marks its field, a choice the form does not offer marks the
 * payment. Null for a form without a fee.
 *
 * @param object $liveform
 * @param int    $page_id
 * @param array  $fields  the form's fields (id, name)
 * @param array  $hidden  the fields a Shown-when rule hid, by id
 * @return array|null fee (pg_form_fee()), method
 */
function pg_form_payment_check($liveform, $page_id, $fields, $hidden)
{
    $settings = pg_form_payment_settings($page_id);

    if ($settings === null) {
        return null;
    }

    $values = array();
    $ids = array();

    foreach ((array) $fields as $field) {
        if (!isset($hidden[$field['id']])) {
            $values[$field['name']] = $liveform->get_field_value($field['id']);
            $ids[$field['name']] = $field['id'];
        }
    }

    $fee = pg_form_fee($settings, $values);

    if ($fee === null) {
        return null;
    }

    if (!$fee['ok']) {
        $messages = array(
            'below_min' => lang('The amount is below the minimum.'),
            'above_max' => lang('The amount is above the maximum.'),
        );
        $liveform->mark_error($ids[$settings['amount_field']] ?? 'payment_method', $messages[$fee['error']] ?? lang('Please enter an amount.'));
    }

    $method = (string) $liveform->get_field_value('payment_method');

    if (!isset(pg_form_payment_methods($settings['offline'])[$method])) {
        $liveform->mark_error('payment_method', lang('Please choose a payment method.'));
    }

    return array('fee' => $fee, 'method' => $method);
}

/**
 * Writes the fee order of a record just saved and charges it. Returns only
 * when the order is completed; a payment page or a refusal ends the request.
 *
 * @param int    $page_id
 * @param int    $record_id
 * @param string $reference_code
 * @param array  $check      pg_form_payment_check()
 * @param int    $contact_id
 * @param string $email      the submitter's address
 * @param int    $user_id
 * @param string $send_to    the form's address
 */
function pg_form_payment_start($page_id, $record_id, $reference_code, $check, $contact_id, $email, $user_id, $send_to)
{
    $contact = ((int) $contact_id > 0) ? db_item("SELECT * FROM contacts WHERE id = '" . (int) $contact_id . "'") : null;
    $order_id = pg_form_payment_order_write($record_id, $check['fee'], pg_form_payment_contact($contact, $email, $user_id));

    if ($order_id <= 0) {
        log_activity('The fee order of submitted form ' . $reference_code . ' could not be written.');
        output_error(lang('The payment could not be started. Please try again later.'));
    }

    $pending = array('record_id' => (int) $record_id, 'order_id' => $order_id, 'reference_code' => (string) $reference_code,
        'send_to' => (string) $send_to, 'driver' => '', 'state' => array(), 'error' => '');
    pg_form_payment_pending_set($page_id, $pending);

    pg_form_payment_respond($page_id, pg_form_payment_charge($page_id, $check['method'], isset($_POST['payment_token']) && is_string($_POST['payment_token']) ? $_POST['payment_token'] : ''), false);
}

/**
 * Charges the session's open fee order with the chosen method: offline
 * payment completes it unpaid; a driver's authorize() completes it, sends
 * the visitor to a payment page or is refused. Keeps the driver's state in
 * the session for the return.
 *
 * @param int    $page_id
 * @param string $method
 * @param string $token  the card service's token
 * @return array pg_form_payment_answer_outcome()
 */
function pg_form_payment_charge($page_id, $method, $token)
{
    $pending = pg_form_payment_pending($page_id);
    $order = ($pending !== null) ? db_item("SELECT * FROM orders WHERE id = '" . (int) $pending['order_id'] . "'") : null;

    if (!is_array($order)) {
        return array('outcome' => 'failed', 'error' => lang('The payment could not be found.'));
    }

    if ($order['status'] !== 'incomplete') {
        return array('outcome' => 'complete', 'status' => 'paid');
    }

    if (!isset(pg_form_payment_methods($order['offline_payment_allowed'])[(string) $method])) {
        return array('outcome' => 'failed', 'error' => lang('Please choose a payment method.'));
    }

    $total = (int) $order['total'];

    if ($method === 'Offline Payment') {
        return pg_form_payment_finish($order, $method, '', 'paid', array());
    }

    $key = pg_payment_order_driver((string) $method, (string) ECOMMERCE_PAYMENT_GATEWAY);
    $return_mode = pg_payment_return_mode($key);
    $result = db_q(pg_order_lines_sql(), array((string) $order['id'])) or output_error('Query failed.');
    $lines = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $lines[] = $row;
    }

    $answer = pg_payment_call($key, 'authorize', array($order, $total, array(
        'token'         => (string) $token,
        'recurring'     => false,
        'subtotal'      => $total,
        'discount'      => 0,
        'charged_lines' => $lines,
        'charges'       => array('shipping' => 0, 'surcharge' => 0),
        'lines'         => $lines,
        'ship_to'       => array(),
        'customer_id'   => (int) $order['user_id'],
        'ip'            => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'basket_id'     => (string) (int) $page_id,
        'return_url'    => ($return_mode !== '')
            ? URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/custom_form.php?mode=' . $return_mode . '&page_id=' . (int) $page_id
            : '',
    )));

    $pending['driver'] = $key;
    $pending['state'] = $answer['state'];
    pg_form_payment_pending_set($page_id, $pending);

    return pg_form_payment_answered($order, $method, $answer);
}

/**
 * The visitor is back from a driver's 3-D Secure or hosted page
 * (custom_form.php?mode=<return mode>&page_id=): the driver finishes the
 * payment, and the visitor goes to the form's confirmation, or back to the
 * payment with the driver's reason.
 *
 * @param int $page_id
 */
function pg_form_payment_return($page_id)
{
    $pending = pg_form_payment_pending($page_id);
    $key = ($pending !== null) ? (string) $pending['driver'] : '';
    $order = ($pending !== null) ? db_item("SELECT * FROM orders WHERE id = '" . (int) $pending['order_id'] . "'") : null;

    if (!is_array($order) || ($key === '') || (($_GET['mode'] ?? '') !== pg_payment_return_mode($key))) {
        output_error(lang('The payment could not be found.'));
    }

    if ($order['status'] !== 'incomplete') {
        pg_form_payment_respond($page_id, array('outcome' => 'complete', 'status' => 'paid'), true);
    }

    $answer = pg_payment_call($key, 'complete', array($order, (int) $order['total'], $_POST, $pending['state']));
    $pending['state'] = $answer['state'];
    pg_form_payment_pending_set($page_id, $pending);

    pg_form_payment_respond($page_id, pg_form_payment_answered($order, (string) $order['payment_method'], $answer), true);
}

/**
 * A retry from the payment alone, or "Start a new submission".
 *
 * @param int $page_id
 */
function pg_form_payment_retry($page_id)
{
    $pending = pg_form_payment_pending($page_id);

    if (($pending === null) || isset($_POST['pg_form_pay_discard'])) {
        $send_to = ($pending !== null) ? $pending['send_to'] : '';
        pg_form_payment_pending_forget($page_id);
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($send_to, PATH . get_page_name($page_id)));
        exit();
    }

    pg_form_payment_respond($page_id, pg_form_payment_charge($page_id,
        (isset($_POST['payment_method']) && is_string($_POST['payment_method'])) ? $_POST['payment_method'] : '',
        (isset($_POST['payment_token']) && is_string($_POST['payment_token'])) ? $_POST['payment_token'] : ''), true);
}

// A driver's answer: its log lines written, the order settled and completed
// or kept for the service's page.
function pg_form_payment_answered($order, $method, $answer)
{
    foreach ($answer['log'] as $line) {
        log_activity($line);
    }

    $outcome = pg_form_payment_answer_outcome($answer);

    if ($outcome['outcome'] === 'complete') {
        $paid = is_int($answer['amount']) ? array('amount' => $answer['amount'], 'installments' => $answer['installments']) : array();

        return pg_form_payment_finish($order, $method, (string) $answer['transaction_id'], $outcome['status'], $paid);
    }

    // The service's own message completes the order when the visitor never
    // comes back: it finds the order by the payment id kept here.
    if ($outcome['outcome'] === 'redirect') {
        $query = pg_form_payment_settle_query($order['id'], $method, (string) $answer['transaction_id'], (int) $order['total']);
        db_q($query['sql'], $query['params']) or output_error('Query failed.');
    }

    return $outcome;
}

// Settles and completes the fee order (pg_order_complete_from_payment()):
// the record is marked paid when the order is, an offline order stays
// unpaid. A completion refused after the service took the money is logged
// for the operator; the visitor is not asked to pay again.
function pg_form_payment_finish($order, $method, $transaction_id, $status, $paid)
{
    $query = pg_form_payment_settle_query($order['id'], $method, $transaction_id, (int) $order['total']);
    db_q($query['sql'], $query['params']) or output_error('Query failed.');

    $result = pg_order_complete_from_payment((int) $order['id'], $status, $paid);

    if ($result['status'] === 'refused') {
        log_activity('The fee order ' . (int) $order['id'] . ' of a submitted form was paid but not completed: ' . $result['reason'], 'SYSTEM');
    }

    return array('outcome' => 'complete', 'status' => $status);
}

/**
 * Ends the request for an outcome: a payment page is shown, a refusal goes
 * back to the form's page (the payment alone, with the reason), a completed
 * payment forgets the open payment and, with $confirm, goes to the form's
 * confirmation; without it the caller continues to it.
 *
 * @param int   $page_id
 * @param array $outcome
 * @param bool  $confirm
 */
function pg_form_payment_respond($page_id, $outcome, $confirm)
{
    $pending = pg_form_payment_pending($page_id);
    $send_to = ($pending !== null) ? $pending['send_to'] : '';

    if ($outcome['outcome'] === 'complete') {
        pg_form_payment_pending_forget($page_id);

        if (!$confirm) {
            return;
        }

        header('Location: ' . pg_form_payment_confirmation_url($page_id, $send_to, ($pending !== null) ? $pending['reference_code'] : ''));
        exit();
    }

    if ($outcome['outcome'] === 'redirect') {
        if ($outcome['html'] !== '') {
            echo $outcome['html'];
        } else {
            header('Location: ' . $outcome['redirect_url']);
        }

        exit();
    }

    if ($pending !== null) {
        $pending['error'] = $outcome['error'];
        pg_form_payment_pending_set($page_id, $pending);
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($send_to, PATH . get_page_name($page_id)));
    exit();
}

/**
 * Where a paid form sends the visitor, as custom_form.php does after a
 * submission: the form's page with its confirmation message, or the next
 * page, given the record's reference code when it shows the answers.
 *
 * @param int    $page_id
 * @param string $send_to
 * @param string $reference_code
 * @return string
 */
function pg_form_payment_confirmation_url($page_id, $send_to, $reference_code)
{
    $row = db_item("SELECT confirmation_type, confirmation_page_id FROM custom_form_pages WHERE page_id = '" . (int) $page_id . "' LIMIT 1");
    $row = is_array($row) ? $row : array('confirmation_type' => 'message', 'confirmation_page_id' => 0);

    if (pg_cf_confirmation_shows_message($row['confirmation_type'], $row['confirmation_page_id'])) {
        return build_url(array(
            'url' => pg_safe_redirect_path($send_to, PATH . get_page_name($page_id)),
            'scheme' => URL_SCHEME,
            'hostname' => HOSTNAME,
            'parameters' => array((int) $page_id . '_confirmation' => 'true')));
    }

    $next = (int) $row['confirmation_page_id'];
    $type = (string) db_value("SELECT page_type FROM page WHERE page_id = '$next'");
    $answers = in_array($type, array('custom form confirmation', 'form item view'), true) || pg_sw_page_widget($next, 'form_item_view');

    return URL_SCHEME . HOSTNAME . PATH . get_page_name($next) . ($answers ? '?r=' . urlencode((string) $reference_code) : '');
}
