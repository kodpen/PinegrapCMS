<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The figures of the Order Report (view_order_report.php).
 *
 * orders.total is written when the order is placed: subtotal - discount +
 * tax + shipping - gift card + card surcharge + installment charge. The
 * report carries every one of those figures, so a row's columns add up to
 * its Total. A cart (an incomplete order) has no such figures yet and is
 * reported from its active lines, as the orders list shows its cart total.
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
 * The money figures of a report row, in column order.
 *
 * @return array figure names
 */
function pg_order_report_figure_names()
{
    return array('subtotal', 'discount', 'tax', 'shipping', 'gift_card_discount', 'surcharge', 'installment_charges', 'total');
}

/**
 * One order's row of the report, in minor units. Pure.
 *
 * @param array      $row  the orders row (id, order_number, order_date,
 *                         status and the figure columns)
 * @param array|null $cart an incomplete order's active lines summed
 *                         (subtotal, tax, shipping), null when it has none
 * @return array id, order_number, order_date and the figures as int
 */
function pg_order_report_order($row, $cart = null)
{
    $order = array(
        'id' => $row['id'] ?? 0,
        'order_number' => $row['order_number'] ?? '',
        'order_date' => $row['order_date'] ?? 0,
    );

    if (($row['status'] ?? '') !== 'incomplete') {
        foreach (pg_order_report_figure_names() as $name) {
            $order[$name] = (int) round((float) ($row[$name] ?? 0));
        }

        return $order;
    }

    foreach (pg_order_report_figure_names() as $name) {
        $order[$name] = 0;
    }

    if ($cart === null) {
        return $order;
    }

    // A discount is written on a cart only when it was sent to pay; the
    // gift card and the charges are settled when the order is placed.
    $order['subtotal'] = (int) round((float) ($cart['subtotal'] ?? 0));
    $order['discount'] = (int) round((float) ($row['discount'] ?? 0));
    $order['tax'] = (int) round((float) ($cart['tax'] ?? 0));
    $order['shipping'] = (int) round((float) ($cart['shipping'] ?? 0));
    $order['total'] = $order['subtotal'] - $order['discount'] + $order['tax'] + $order['shipping'];

    return $order;
}

/**
 * The carts' figures by order id: subtotal and tax from their active lines,
 * shipping from their recipients' shipping_cost. The cost is what the
 * shipping step charged: the method's and the zone's base rates and the
 * shipping offer's discount are in it, while order_items.shipping holds
 * only a line's own rate per unit, rounded. A cart with no active line is
 * left out. Pure.
 *
 * @param array $lines      rows of id, subtotal, tax (the active lines summed)
 * @param array $recipients rows of id, shipping (the recipients summed)
 * @return array id => subtotal, tax, shipping
 */
function pg_order_report_carts(array $lines, array $recipients)
{
    $shipping = array();

    foreach ($recipients as $row) {
        $shipping[(string) ($row['id'] ?? '')] = $row['shipping'] ?? 0;
    }

    $carts = array();

    foreach ($lines as $row) {
        $id = (string) ($row['id'] ?? '');
        $carts[$id] = array(
            'subtotal' => $row['subtotal'] ?? 0,
            'tax' => $row['tax'] ?? 0,
            'shipping' => $shipping[$id] ?? 0,
        );
    }

    return $carts;
}

/**
 * An empty group of the report: its name, its order count and its figures.
 * Pure.
 *
 * @param string $name the group's name
 * @return array
 */
function pg_order_report_bucket($name)
{
    $bucket = array('name' => $name, 'count' => 0);

    foreach (pg_order_report_figure_names() as $figure) {
        $bucket[$figure] = 0;
    }

    return $bucket;
}

/**
 * A group with one more order counted in. Pure.
 *
 * @param array $bucket pg_order_report_bucket()
 * @param array $order  pg_order_report_order()
 * @return array
 */
function pg_order_report_add($bucket, $order)
{
    $bucket['count']++;

    foreach (pg_order_report_figure_names() as $figure) {
        $bucket[$figure] += $order[$figure];
    }

    return $bucket;
}

/**
 * The charge columns the report shows: the gift card, the card surcharge
 * and the installment charge, each only when its grand total is not zero.
 * Pure.
 *
 * @param array $grand the grand total bucket
 * @return array figure names, in column order
 */
function pg_order_report_charge_columns($grand)
{
    $columns = array();

    foreach (array('gift_card_discount', 'surcharge', 'installment_charges') as $figure) {
        if ((int) ($grand[$figure] ?? 0) !== 0) {
            $columns[] = $figure;
        }
    }

    return $columns;
}

/**
 * Whether a row opens a new group: always the first row ($previous null),
 * whatever its name, so an empty first value still gets its group; after
 * that a name that differs from the previous one, without case. Pure.
 *
 * @param string      $name     the row's group value
 * @param string|null $previous the previous row's value, null on the first row
 * @return bool
 */
function pg_order_report_new_group($name, $previous)
{
    if ($previous === null) {
        return true;
    }

    return mb_strtolower((string) $name) !== mb_strtolower((string) $previous);
}

/**
 * The filters a new report starts with: carts and cancelled orders left
 * out, so the report sums only placed sales. Pure.
 *
 * @return array field, operator and value of each filter
 */
function pg_order_report_default_filters()
{
    return array(
        array('field' => 'order_status', 'operator' => 'is not equal to', 'value' => 'incomplete'),
        array('field' => 'order_status', 'operator' => 'is not equal to', 'value' => 'cancelled'),
    );
}

/**
 * The SQL a filter field compares, or '' for an empty or unknown field: such
 * a filter adds nothing to the report, so a report saved with one still
 * opens. A field of the order's lines reads order_items, one of its
 * recipients ship_tos. $from and $to bound the orders an order date is read
 * over in the site's own time (pg_site_time_sql()).
 *
 * @param mixed    $field the filter's field
 * @param int|null $from
 * @param int|null $to
 * @return string
 */
function pg_order_report_filter_column($field, $from = null, $to = null)
{
    if (!is_string($field) || ($field === '')) {
        return '';
    }

    $columns = array(
        'order_number', 'transaction_id', 'authorization_code', 'special_offer_code', 'referral_source_code',
        'reference_code', 'tracking_code', 'http_referer', 'currency_code',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'payment_method', 'card_type', 'cardholder', 'card_number', 'custom_field_1', 'custom_field_2',
        'billing_salutation', 'billing_first_name', 'billing_last_name', 'billing_company',
        'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_zip_code',
        'billing_country', 'billing_phone_number', 'billing_fax_number', 'billing_email_address', 'po_number',
    );

    if (in_array($field, $columns, true)) {
        return 'orders.' . $field;
    }

    $recipient_columns = array(
        'shipping_salutation' => 'salutation', 'shipping_first_name' => 'first_name',
        'shipping_last_name' => 'last_name', 'shipping_company' => 'company',
        'shipping_address_1' => 'address_1', 'shipping_address_2' => 'address_2',
        'shipping_city' => 'city', 'shipping_state' => 'state', 'shipping_zip_code' => 'zip_code',
        'shipping_country' => 'country', 'shipping_address_type' => 'address_type',
        'shipping_phone_number' => 'phone_number', 'ship_to_name' => 'ship_to_name',
        'arrival_date_code' => 'arrival_date_code', 'shipping_method_code' => 'shipping_method_code',
    );

    if (isset($recipient_columns[$field])) {
        return 'ship_tos.' . $recipient_columns[$field];
    }

    switch ($field) {
        case 'order_date':
            return pg_site_time_sql('orders.order_date', '%Y-%m-%d', $from, $to);

        case 'order_status':
            return 'orders.status';

        case 'opt_in_status':
            return 'orders.opt_in';

        case 'tax_status':
            return 'orders.tax_exempt';

        case 'ip_address':
            return 'CASE WHEN (INET_NTOA(orders.ip_address) = "0.0.0.0") THEN "" ELSE INET_NTOA(orders.ip_address) END';

        case 'product_name':
            return 'order_items.product_name';
    }

    return '';
}

/**
 * A filter's condition on its column (pg_order_report_filter_column()). A
 * column of the order's lines or recipients is read through the joined row,
 * so a comparison keeps an order when one of its lines or recipients
 * matches. "Does not contain" and "is not equal to" keep it only when none
 * matches: row by row, the order's other product passed and an order
 * holding the excluded product was listed. An order with no line or
 * recipient is read as one empty row, as the join reads it.
 *
 * @param string $column    the filter's column
 * @param string $operator  the filter's operator
 * @param string $operand_2 the value it is compared with
 * @return string
 */
function pg_order_report_filter_sql($column, $operator, $operand_2)
{
    $negations = array('does not contain' => 'contains', 'is not equal to' => 'is equal to');

    foreach (array('order_items', 'ship_tos') as $table) {
        if ((strpos($column, $table . '.') === 0) && isset($negations[$operator])) {
            return "NOT EXISTS (SELECT 1 FROM orders AS filter_order LEFT JOIN " . $table . " ON " . $table . ".order_id = filter_order.id"
                . " WHERE (filter_order.id = orders.id) AND (" . prepare_sql_operation($negations[$operator], $column, $operand_2) . "))";
        }
    }

    return prepare_sql_operation($operator, $column, $operand_2);
}

/**
 * Whether a "day(s) ago" count can be stored: a whole number from 0 up to
 * what the INT UNSIGNED column holds; empty means today. Pure.
 *
 * @param mixed $text the submitted count
 * @return bool
 */
function pg_order_report_days_ago_valid($text)
{
    if (!is_scalar($text)) {
        return false;
    }

    $text = trim((string) $text);

    if ($text === '') {
        return true;
    }

    return ctype_digit($text) && (strlen($text) <= 10) && ((float) $text <= 4294967295);
}
