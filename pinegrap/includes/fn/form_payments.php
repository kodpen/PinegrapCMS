<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The fee of a designed form (custom_form widget). A form's payment setting
 * names a fee product; the amount is that product's price or a figure the
 * visitor typed into a numeric field, held between a minimum and a maximum.
 * The fee is charged as a one-line order of the fee product, so receipts,
 * refunds, reports and invoices come from the order.
 *
 * Amounts are integer minor units, as products.price and orders.total.
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
 * A form's payment setting in its one shape: product_id (0 = no fee),
 * amount_source ('product' or 'field'), amount_field (the field's name),
 * amount_min and amount_max (minor units, 0 = no bound; a minimum over the
 * maximum is swapped), offline (offline payment allowed, 0 or 1). Unknown
 * keys are dropped.
 *
 * @param  mixed $raw
 * @return array
 */
function pg_form_fee_settings_clean($raw)
{
    $raw = is_array($raw) ? $raw : array();
    $int = function ($key) use ($raw) {
        $value = isset($raw[$key]) && is_scalar($raw[$key]) ? (int) $raw[$key] : 0;
        return max(0, $value);
    };

    $min = $int('amount_min');
    $max = $int('amount_max');

    if ($min > 0 && $max > 0 && $min > $max) {
        list($min, $max) = array($max, $min);
    }

    $source = isset($raw['amount_source']) ? (string) $raw['amount_source'] : '';
    $offline = isset($raw['offline']) ? $raw['offline'] : 0;

    return array(
        'product_id'    => $int('product_id'),
        'amount_source' => ($source === 'field') ? 'field' : 'product',
        'amount_field'  => isset($raw['amount_field']) && is_scalar($raw['amount_field'])
                               ? mb_substr(trim((string) $raw['amount_field']), 0, 100) : '',
        'amount_min'    => $min,
        'amount_max'    => $max,
        'offline'       => (!empty($offline) && $offline !== '0') ? 1 : 0,
    );
}

/**
 * The fee one submission of a form owes.
 *
 * $settings is a payment setting (pg_form_fee_settings_clean()) plus
 * product_price, the fee product's price in minor units, which the caller
 * reads. $values are the submitted answers by field name.
 *
 * null: the form asks for no payment (no fee product, or a product priced
 * 0). Otherwise array('ok', 'amount', 'product_id', 'offline', 'error'),
 * where a refused amount has ok false and error 'no_amount' (nothing
 * positive could be read), 'below_min' or 'above_max'. A figure outside the
 * bounds is refused, never moved to the bound: the visitor sees what is
 * charged.
 *
 * @param  mixed $settings
 * @param  array $values
 * @return array|null
 */
function pg_form_fee($settings, $values)
{
    if (!is_array($settings)) {
        return null;
    }

    $clean = pg_form_fee_settings_clean($settings);

    if ($clean['product_id'] <= 0) {
        return null;
    }

    $fee = array(
        'ok'         => false,
        'amount'     => 0,
        'product_id' => $clean['product_id'],
        'offline'    => $clean['offline'],
        'error'      => '',
    );

    if ($clean['amount_source'] === 'product') {
        $price = isset($settings['product_price']) ? (int) $settings['product_price'] : 0;

        if ($price <= 0) {
            return null;
        }

        $fee['ok'] = true;
        $fee['amount'] = $price;

        return $fee;
    }

    $values = is_array($values) ? $values : array();
    $raw = ($clean['amount_field'] !== '' && isset($values[$clean['amount_field']]))
        ? $values[$clean['amount_field']] : '';

    if (is_array($raw)) {
        $raw = (count($raw) === 1) ? reset($raw) : '';
    }

    $amount = is_scalar($raw) ? (int) round(pg_parse_amount((string) $raw) * 100) : 0;

    if ($amount <= 0) {
        $fee['error'] = 'no_amount';
    } elseif ($clean['amount_min'] > 0 && $amount < $clean['amount_min']) {
        $fee['error'] = 'below_min';
    } elseif ($clean['amount_max'] > 0 && $amount > $clean['amount_max']) {
        $fee['error'] = 'above_max';
    } else {
        $fee['ok'] = true;
        $fee['amount'] = $amount;
    }

    return $fee;
}

/**
 * Whether this installation stores the fee (2026.11.0, 11.0.158):
 * custom_form_pages.payment, forms.order_id and forms.paid_at. Code that
 * lands before the upgrade reads no fee and writes none.
 *
 * @return bool
 */
function pg_form_payment_ready()
{
    return pg_schema_has('custom_form_pages', 'payment')
        && pg_schema_has('forms', 'order_id')
        && pg_schema_has('forms', 'paid_at');
}

/**
 * custom_form_pages.payment as the setting: NULL, '' or JSON that does not
 * read is a form without a fee (product_id 0).
 *
 * @param  mixed $stored
 * @return array
 */
function pg_form_fee_settings_decode($stored)
{
    $raw = (is_string($stored) && $stored !== '') ? json_decode($stored, true) : null;

    return pg_form_fee_settings_clean(is_array($raw) ? $raw : array());
}

/**
 * The setting as custom_form_pages.payment stores it: '' for a form without
 * a fee product, so a switched-off fee leaves no stale amounts behind.
 *
 * @param  mixed $raw
 * @return string
 */
function pg_form_fee_settings_encode($raw)
{
    $clean = pg_form_fee_settings_clean($raw);

    return ($clean['product_id'] > 0) ? (string) json_encode($clean) : '';
}

/**
 * The columns of a record's fee order and its one line. Pure.
 *
 * $fee is an accepted pg_form_fee() answer, $contact the record's contact
 * as the form's contact fields gave it (contact_id, user_id, first_name,
 * last_name, company, email_address, phone_number, address_1, address_2,
 * city, state, zip_code, country; any may be missing), $product_name the fee
 * product's name, $currency the site's currency code, $offline_on whether
 * the store takes offline payments at all.
 *
 * The amount is the order's total as the visitor saw it: one line, quantity
 * 1, no tax or shipping added on top. The order is written incomplete; its
 * payment completes it through pg_order_complete().
 *
 * @param  array  $fee
 * @param  array  $contact
 * @param  string $product_name
 * @param  string $currency
 * @param  bool   $offline_on
 * @return array|null order (column => value), item (column => value); null
 *                    for a refused or empty fee
 */
function pg_form_payment_order_columns($fee, $contact, $product_name, $currency, $offline_on)
{
    if (!is_array($fee) || empty($fee['ok']) || (int) ($fee['amount'] ?? 0) <= 0 || (int) ($fee['product_id'] ?? 0) <= 0) {
        return null;
    }

    $contact = is_array($contact) ? $contact : array();
    $text = function ($key, $length) use ($contact) {
        return isset($contact[$key]) && is_scalar($contact[$key]) ? mb_substr(trim((string) $contact[$key]), 0, $length) : '';
    };
    $amount = (int) $fee['amount'];

    $order = array(
        'status'                  => 'incomplete',
        'user_id'                 => max(0, (int) ($contact['user_id'] ?? 0)),
        'contact_id'              => max(0, (int) ($contact['contact_id'] ?? 0)),
        'currency_code'           => (string) $currency,
        'billing_first_name'      => $text('first_name', 50),
        'billing_last_name'       => $text('last_name', 50),
        'billing_company'         => $text('company', 50),
        'billing_address_1'       => $text('address_1', 50),
        'billing_address_2'       => $text('address_2', 50),
        'billing_city'            => $text('city', 50),
        'billing_state'           => $text('state', 50),
        'billing_zip_code'        => $text('zip_code', 50),
        'billing_country'         => $text('country', 50),
        'billing_phone_number'    => $text('phone_number', 50),
        'billing_email_address'   => $text('email_address', 100),
        'subtotal'                => $amount,
        'total'                   => $amount,
        'offline_payment_allowed' => ($offline_on && !empty($fee['offline'])) ? 1 : 0,
    );

    $item = array(
        'product_id'   => (int) $fee['product_id'],
        'product_name' => mb_substr((string) $product_name, 0, 255),
        'quantity'     => 1,
        'price'        => $amount,
        'tax'          => 0,
        'tax_total'    => 0,
    );

    return array('order' => $order, 'item' => $item);
}

/**
 * Writes the fee order of a submitted record and points the record at it
 * (forms.order_id), modelled on pg_subscription_renewal_order_write(): an
 * incomplete order with one order_items line of the fee product, billing
 * from the record's contact fields, orders.notes untouched. The payment
 * (or the order screen's offline path) completes it, and the completion
 * marks the record paid (pg_form_payment_mark_paid()).
 *
 * A record that already points at an order keeps it: a retried payment
 * charges the same order.
 *
 * @param  int   $record_id forms.id
 * @param  array $fee       an accepted pg_form_fee() answer
 * @param  array $contact   see pg_form_payment_order_columns()
 * @return int the order's id, 0 when none was written
 */
function pg_form_payment_order_write($record_id, $fee, $contact)
{
    $record_id = (int) $record_id;

    if ($record_id <= 0 || !pg_form_payment_ready()) {
        return 0;
    }

    $existing = (int) db_value("SELECT order_id FROM forms WHERE id = '$record_id' LIMIT 1");

    if ($existing > 0) {
        return $existing;
    }

    $product_name = (string) db_value("SELECT name FROM products WHERE id = '" . (int) ($fee['product_id'] ?? 0) . "' LIMIT 1");
    $columns = pg_form_payment_order_columns($fee, $contact, $product_name, BASE_CURRENCY_CODE, ECOMMERCE_OFFLINE_PAYMENT == true);

    if ($columns === null) {
        return 0;
    }

    $order = $columns['order'];
    $order['reference_code'] = generate_order_reference_code();
    $order['last_modified_timestamp'] = time();

    if (pg_schema_has('orders', 'type')) {
        $order['type'] = 'online';
    }

    if (!db_q("INSERT INTO orders (" . implode(', ', array_keys($order)) . ")
        VALUES (" . implode(', ', array_fill(0, count($order), '?')) . ")", array_values($order))) {
        return 0;
    }

    $order_id = (int) mysqli_insert_id(db::$con);
    $item = array('order_id' => $order_id, 'ship_to_id' => 0) + $columns['item'];

    db_q("INSERT INTO order_items (" . implode(', ', array_keys($item)) . ")
        VALUES (" . implode(', ', array_fill(0, count($item), '?')) . ")", array_values($item)) or output_error('Query failed.');

    // Only a record that still points at no order takes this one.
    db_q("UPDATE forms SET order_id = ? WHERE id = ? AND order_id = 0", array($order_id, $record_id)) or output_error('Query failed.');

    return $order_id;
}

/**
 * The statement that marks the records of a completed order paid. Pure.
 * paid_at is written once: a second completion message for the same order
 * finds no record still at 0 and changes nothing.
 *
 * @param  int $order_id
 * @param  int $now
 * @return array|null sql, params; null for no order
 */
function pg_form_payment_mark_paid_query($order_id, $now)
{
    $order_id = (int) $order_id;

    if ($order_id <= 0) {
        return null;
    }

    return array(
        'sql'    => 'UPDATE forms SET paid_at = ? WHERE order_id = ? AND paid_at = 0',
        'params' => array(max(1, (int) $now), $order_id),
    );
}

/**
 * Marks the record a completed order paid for, called by pg_order_complete()
 * for every order it completes; an order no record points at changes
 * nothing.
 *
 * @param  int      $order_id
 * @param  int|null $now
 * @return bool whether a record was marked by this call
 */
function pg_form_payment_mark_paid($order_id, $now = null)
{
    if (!pg_form_payment_ready()) {
        return false;
    }

    $query = pg_form_payment_mark_paid_query($order_id, ($now === null) ? time() : $now);

    if ($query === null) {
        return false;
    }

    db_q($query['sql'], $query['params']) or output_error('Query failed.');

    return mysqli_affected_rows(db::$con) > 0;
}
