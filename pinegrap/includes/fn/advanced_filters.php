<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Advanced Filters panel of the list screens (`.advanced-filter-bar`):
 * its head (icon, "Filters", the count of filters in use, Close), its foot
 * ("Clear" and "Update"), the count itself and the address "Clear" opens.
 *
 * The panel is a column of three parts: the head and the foot keep their
 * place and only the body between them scrolls, so nothing floats over the
 * fields. "Clear" sends the screen's own filter fields with their empty
 * values, the same request an emptied form would send: no new parameter.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * The number of filters in use: a field counts when the state holds a value
 * for it that differs from its empty value. A field missing from the state
 * is not in use, so a filter never touched does not count.
 *
 * @param array $state  The screen's kept filter values (its session array).
 * @param array $empty  Field name => the value that means "no filter".
 * @return int
 */
function pg_advanced_filters_count(array $state, array $empty)
{
    $count = 0;

    foreach ($empty as $name => $neutral) {
        if (!array_key_exists($name, $state) || !is_scalar($state[$name])) {
            continue;
        }

        if (trim((string) $state[$name]) !== (string) $neutral) {
            $count++;
        }
    }

    return $count;
}

/**
 * The address of "Clear": the screen's form address with every filter field
 * at its empty value and the form's own submit value, so the screen stores
 * them as it stores any submitted filter. Extra pairs (a screen's view, such
 * as Contacts' filter) are kept.
 *
 * @param string $action  The form's action, such as 'view_orders.php'.
 * @param array  $empty   Field name => the value that means "no filter".
 * @param array  $keep    Extra name => value pairs sent with it.
 * @return string  An unescaped URL; the caller escapes it with h().
 */
function pg_advanced_filters_clear_url($action, array $empty, array $keep = array())
{
    $query = array();

    foreach ($keep as $name => $value) {
        $query[$name] = (string) $value;
    }

    foreach ($empty as $name => $neutral) {
        $query[$name] = (string) $neutral;
    }

    $query['submit_data'] = 'Update';

    return $action . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

/**
 * The panel's head, 48 px: the funnel icon, "Filters", the count of filters
 * in use as a count pill when there is one, and Close as an icon button (the
 * link that turns the panel off).
 *
 * @param string $close_url    The address that turns the panel off.
 * @param string $close_label  The Close link's name ("Remove Advanced Filters").
 * @param int    $count        Filters in use; 0 draws no pill.
 * @return string
 */
function pg_advanced_filters_head($close_url, $close_label, $count)
{
    $count = (int) $count;

    $pill = '';

    if ($count > 0) {
        $in_use = lang(array('string' => '{var:1} filters in use', 'vars' => array($count)));
        $pill = '<span class="pg-count" title="' . h($in_use) . '"><span class="visually-hidden">' . h($in_use) . '</span><span aria-hidden="true">' . $count . '</span></span>';
    }

    return '<div class="advanced-filter-head">'
        . '<p class="advanced-filter-title"><i class="bi bi-funnel" aria-hidden="true"></i>' . h(lang('Filters')) . $pill . '</p>'
        . '<a class="pg-icon-button pg-icon-button-sm" href="' . h($close_url) . '" title="' . h($close_label) . '" aria-label="' . h($close_label) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></a>'
        . '</div>';
}

/**
 * The panel's foot, fixed under the body: "Clear" (quiet, at the left) and
 * "Update" (fill, last). Update keeps today's name and value, so the screens
 * read the submit as before.
 *
 * @param string $clear_url  The address from pg_advanced_filters_clear_url();
 *                           '' draws no Clear.
 * @return string
 */
function pg_advanced_filters_foot($clear_url = '')
{
    $clear = '';

    // A screen with no list of its filter fields yet draws Update alone.
    if ($clear_url !== '') {
        $clear = '<a class="pg-button pg-button-quiet" href="' . h($clear_url) . '">' . h(lang('Clear')) . '</a>';
    }

    return '<div class="advanced-filter-foot">'
        . $clear
        . '<button type="submit" name="submit_data" value="Update" class="pg-button pg-button-fill"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . h(lang('Update')) . '</button>'
        . '</div>';
}

/**
 * All Orders' advanced filter fields and the value of each that means "no
 * filter": '' for a text box, 'any' for a picklist whose '' is a filter of
 * its own (payment method None), Order Date for the date type. The period
 * (start_* / stop_*) is the bar's period navigator's and is not listed.
 *
 * @param bool $affiliate      The affiliate program is on (Affiliate Code).
 * @param bool $multicurrency  Multi-currency is on (Currency).
 * @param bool $shipping       Shipping is on (Order Date / Ship Date).
 * @return array  Field name => empty value, in the panel's order.
 */
function pg_order_advanced_filters_empty($affiliate, $multicurrency, $shipping)
{
    $empty = array(
        'shipping_status' => 'any',
        'order_number' => '',
        'transaction_id' => '',
        'authorization_code' => '',
        'special_offer_code' => '',
        'referral_source_code' => '',
        'reference_code' => '',
        'tracking_code' => '',
        'http_referer' => '',
    );

    if ($multicurrency) {
        $empty['currency_code'] = '';
    }

    $empty += array(
        'ip_address' => '',
        'product_name' => '',
        'payment_method' => 'any',
        'card_type' => '',
        'cardholder' => '',
        'card_number' => '',
    );

    if ($affiliate) {
        $empty['affiliate_code'] = '';
    }

    foreach (array('custom_field_1', 'custom_field_2', 'billing_salutation', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_zip_code', 'billing_country', 'billing_phone_number', 'billing_fax_number', 'billing_email_address') as $name) {
        $empty[$name] = '';
    }

    $empty['opt_in_status'] = 'any';
    $empty['po_number'] = '';
    $empty['tax_status'] = 'any';

    foreach (array('shipping_salutation', 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_zip_code', 'shipping_country') as $name) {
        $empty[$name] = '';
    }

    $empty['address_type'] = 'any';
    $empty['shipping_phone_number'] = '';
    $empty['ship_to_name'] = '';
    $empty['arrival_date_code'] = '';
    $empty['shipping_method_code'] = '';

    if ($shipping) {
        $empty['date_type'] = 'order_date';
    }

    return $empty;
}
