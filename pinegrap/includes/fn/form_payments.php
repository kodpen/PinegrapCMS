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
