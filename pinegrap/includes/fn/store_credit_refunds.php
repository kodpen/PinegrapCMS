<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Refunds given as store credit (2026.11.0).
 *
 * A received return (pg_return_refund(), method 'credit') and a cancelled
 * order that still owes money (the order screen's refund dialog) may give the
 * refund as a card of kind 'refund' bound to the order's customer, with an
 * optional bonus percentage on top (the config_text row credit_refund_bonus,
 * '' = none). The refund itself is written as every refund is: the refunded
 * amount and an order_refunds row hold the money given back, without the
 * bonus; the card id is the refund's reference.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Highest bonus percentage the setting keeps. */
define('PG_CREDIT_REFUND_BONUS_MAX', 100);

/**
 * The bonus percentage typed in Site Settings, as it is stored: a plain
 * number with a dot, at most two decimals, capped at 100; '' for empty, zero,
 * negative or not a number (no bonus). Read as pg_parse_amount() reads
 * amounts.
 *
 * @param mixed $raw
 * @return string
 */
function pg_credit_refund_parse_bonus($raw)
{
    if (!is_scalar($raw) || (trim((string) $raw) === '')) {
        return '';
    }

    $percent = round(pg_parse_amount($raw), 2);

    if ($percent <= 0) {
        return '';
    }

    $percent = min((float) PG_CREDIT_REFUND_BONUS_MAX, $percent);

    return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
}

/**
 * The bonus percentage of the site, 0 when none is set.
 *
 * @return float
 */
function pg_credit_refund_bonus()
{
    $stored = pg_config_text('credit_refund_bonus');

    return is_numeric($stored) ? max(0.0, min((float) PG_CREDIT_REFUND_BONUS_MAX, (float) $stored)) : 0.0;
}

/**
 * What the credit card holds for a refund: the refund and the bonus on it,
 * rounded with round() at the smallest unit. A bonus of 0, empty or negative
 * adds nothing; it is capped like the setting.
 *
 * @param int   $amount  the refund in minor units
 * @param mixed $percent the bonus percentage
 * @return int minor units
 */
function pg_credit_refund_card_amount($amount, $percent)
{
    $amount = (int) $amount;

    if ($amount <= 0) {
        return 0;
    }

    $percent = is_numeric($percent) ? min((float) PG_CREDIT_REFUND_BONUS_MAX, (float) $percent) : 0.0;

    if ($percent <= 0) {
        return $amount;
    }

    return $amount + (int) round($amount * $percent / 100);
}

/**
 * The refund reference a cancelled order keeps for a refund given as store
 * credit (orders.refund_reference has no method beside it).
 *
 * @param int $card_id
 * @return string
 */
function pg_credit_refund_reference($card_id)
{
    return 'store_credit:' . (int) $card_id;
}

/**
 * The card a refund reference names, 0 when it names none (a typed one).
 *
 * @param string $reference
 * @return int
 */
function pg_credit_refund_card_id($reference)
{
    return (preg_match('/^store_credit:([1-9][0-9]*)$/', (string) $reference, $match) === 1) ? (int) $match[1] : 0;
}

/**
 * Whether an order's refund may be given as store credit: the credit columns
 * exist and the order belongs to a customer account that still exists.
 *
 * @param int $user_id orders.user_id
 * @return bool
 */
function pg_credit_refund_possible($user_id)
{
    if (((int) $user_id <= 0) || !pg_credit_ready()) {
        return false;
    }

    return (int) db_value("SELECT COUNT(*) FROM user WHERE user_id = '" . (int) $user_id . "'") > 0;
}

/**
 * Issues the card of a refund: kind 'refund', never expiring, the site's
 * bonus on top.
 *
 * @param int    $user_id   the order's customer
 * @param int    $amount    the refund in minor units
 * @param string $reason    what the card is for (kept on the card)
 * @param int    $issued_by the staff member
 * @return array card_id (0 when nothing was written), credit (minor units)
 */
function pg_credit_refund_issue($user_id, $amount, $reason, $issued_by)
{
    $credit = pg_credit_refund_card_amount($amount, pg_credit_refund_bonus());

    if ($credit <= 0) {
        return array('card_id' => 0, 'credit' => 0);
    }

    $card_id = (int) pg_credit_issue((int) $user_id, $credit, 'refund', $reason, 0, (int) $issued_by);

    return array('card_id' => $card_id, 'credit' => ($card_id > 0) ? $credit : 0);
}

/**
 * The body of the mail that tells a customer the refund of a cancelled order
 * was given as store credit.
 *
 * @param array $data customer, order_number, amount, credit (plain text),
 *                    from_name
 * @return string HTML
 */
function pg_credit_refund_cancel_mail_body($data)
{
    $customer = isset($data['customer']) ? (string) $data['customer'] : '';
    $paragraph = function ($html) {
        return '<p style="margin:0 0 12px;">' . $html . '</p>';
    };

    return '<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;background:#f7f7f7;padding:20px;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;padding:24px;border-radius:6px;border:1px solid #e5e5e5;">'
        . '<h2 style="margin:0 0 16px;font-size:18px;">' . h(lang('Your refund was made')) . '</h2>'
        . $paragraph(h(lang('Hello')) . (($customer !== '') ? ' ' . h($customer) : '') . ',')
        . $paragraph(h(lang(array(
            'string' => 'The refund of {var:2} for your cancelled order #{var:1} was added to your account as store credit of {var:3}. It is used at checkout when you are signed in.',
            'vars'   => array(
                isset($data['order_number']) ? (string) $data['order_number'] : '',
                isset($data['amount']) ? (string) $data['amount'] : '',
                isset($data['credit']) ? (string) $data['credit'] : '')))))
        . '<p style="margin:16px 0 0;color:#666;font-size:12px;">' . h(isset($data['from_name']) ? (string) $data['from_name'] : '') . '</p>'
        . '</div></body></html>';
}

/**
 * Gives what a cancelled order still owes (pg_order_cancel_refund_owed(): the
 * payment money left and the gift card part) as store credit, and marks the
 * order's refund as processed with the card as its reference.
 *
 * The refund status moves first, guarded by the states that owe money, so a
 * second click or a second person finds nothing to give; when no card can be
 * written it goes back. The payment money given is added to the refunded
 * amount with an order_refunds row of type 'credit' (transaction_id the card
 * id), as a refund of the payment would be; the gift card part has no
 * refunded amount to add to.
 *
 * @param int $order_id
 * @param int $staff_id the staff member
 * @return array ok, error, card_id, amount, credit, mailed
 */
function pg_credit_refund_cancelled_order($order_id, $staff_id)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'card_id' => 0, 'amount' => 0, 'credit' => 0, 'mailed' => false);
    };

    $order = db_item("SELECT * FROM orders WHERE id = '" . (int) $order_id . "'");

    if (!$order || !array_key_exists('refund_status', $order)) {
        return $fail(lang('Order not found.'));
    }

    if (((string) $order['status'] !== 'cancelled') || !in_array((string) $order['refund_status'], array('manual_required', 'failed', 'pending'), true)) {
        return $fail(lang('This order is not waiting for a refund.'));
    }

    if (!pg_credit_refund_possible((int) $order['user_id'])) {
        return $fail(lang('This order has no customer account to give store credit to.'));
    }

    $owed = pg_order_cancel_refund_owed($order);
    $amount = (int) $owed['total'];

    if ($amount <= 0) {
        return $fail(lang('The refund amount must be greater than 0.'));
    }

    $now = time();

    db("UPDATE orders SET
            refund_status = 'refunded',
            refunded_at   = '" . $now . "',
            " . pg_order_touch_sql() . "
        WHERE (id = '" . (int) $order['id'] . "') AND (refund_status IN ('manual_required', 'failed', 'pending'))");

    if (mysqli_affected_rows(db::$con) < 1) {
        return $fail(lang('This order is not waiting for a refund.'));
    }

    $order_number = pg_order_log_number($order);
    $issued = pg_credit_refund_issue((int) $order['user_id'], $amount,
        'Refund of cancelled order #' . $order_number, (int) $staff_id);

    if ($issued['card_id'] <= 0) {
        db("UPDATE orders SET refund_status = '" . e((string) $order['refund_status']) . "', refunded_at = '" . (int) $order['refunded_at'] . "'
            WHERE (id = '" . (int) $order['id'] . "') AND (refund_status = 'refunded') AND (refunded_at = '" . $now . "')");

        return $fail(lang('The store credit could not be written.'));
    }

    db("UPDATE orders SET refund_reference = '" . e(pg_credit_refund_reference($issued['card_id'])) . "'
        WHERE id = '" . (int) $order['id'] . "'");

    $payment = min($amount, (int) $owed['payment']);

    if ($payment > 0) {
        db("UPDATE orders SET refunded_amount = COALESCE(refunded_amount, 0) + " . $payment . " WHERE id = '" . (int) $order['id'] . "'");
    }

    $has_notes = pg_schema_has('order_refunds', 'notes');

    db("INSERT INTO order_refunds (order_id, amount_cents, refund_type, transaction_id" . ($has_notes ? ', notes' : '') . ", created_at)
        VALUES ('" . (int) $order['id'] . "', '" . $amount . "', 'credit', '" . (int) $issued['card_id'] . "'"
        . ($has_notes ? ", '" . e('Store credit #' . (int) $issued['card_id']) . "'" : '') . ", NOW())");

    log_activity(lang(array(
        'string' => 'The refund of {var:1} for order #{var:2} was given as store credit of {var:3}.',
        'vars'   => array(pg_order_log_money($amount), $order_number, pg_order_log_money($issued['credit'])))));

    return array(
        'ok'      => true,
        'error'   => '',
        'card_id' => $issued['card_id'],
        'amount'  => $amount,
        'credit'  => $issued['credit'],
        'mailed'  => pg_credit_refund_cancel_mail($order, $amount, $issued['credit']),
    );
}

/**
 * Tells the customer of a cancelled order that its refund became store
 * credit, at the billing address, through the mail queue.
 *
 * @param array $order  orders row
 * @param int   $amount the refund in minor units
 * @param int   $credit the card in minor units
 * @return bool sent or queued
 */
function pg_credit_refund_cancel_mail(array $order, $amount, $credit)
{
    $to = trim((string) (isset($order['billing_email_address']) ? $order['billing_email_address'] : ''));

    if (($to === '') || (strpos($to, '@') === false) || !defined('EMAIL_ADDRESS') || (EMAIL_ADDRESS === '')) {
        return false;
    }

    $customer = trim(trim((string) $order['billing_first_name']) . ' ' . trim((string) $order['billing_last_name']));
    $from_name = (defined('ORGANIZATION_NAME') && (ORGANIZATION_NAME !== ''))
        ? ORGANIZATION_NAME
        : (defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : 'Pinegrap');
    $order_number = pg_order_log_number($order);

    return (bool) email(array(
        'to'                 => $to,
        'to_name'            => $customer,
        'from_name'          => $from_name,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang(array('string' => 'Your refund for order #{var:1}', 'vars' => array($order_number))),
        'format'             => 'html',
        'body'               => pg_credit_refund_cancel_mail_body(array(
            'customer'     => $customer,
            'order_number' => $order_number,
            'amount'       => pg_order_log_money($amount),
            'credit'       => pg_order_log_money($credit),
            'from_name'    => $from_name,
        )),
        'type'               => 'system',
        'queue'              => true,
    ));
}
