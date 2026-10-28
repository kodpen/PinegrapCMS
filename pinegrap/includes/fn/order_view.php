<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel order screen's card rows (view_order.php): a "label: value" row
 * only when there is a value, the stored address type and country code as
 * the panel names them, the requested arrival date as the customer chose it
 * at the checkout, and the text of the contact link.
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
 * One row of an order card: the label, a colon, the value on the right.
 * Nothing when the value is empty: a row with no value says nothing and only
 * makes the card longer.
 *
 * @param string $label      the label text, ready to print
 * @param string $value_html the value: escaped text, or markup the caller built
 * @param bool   $wide       an address line: the label in its own column, the
 *                           value emphasised, as the other address rows
 * @return string
 */
function pg_order_view_row($label, $value_html, $wide = false)
{
    $value_html = trim((string) $value_html);

    if ($value_html === '') {
        return '';
    }

    if ($wide) {
        return '<div class="row">
                                        <span class="translateable col-auto fg-secondary">' . $label . ':</span>
                                        <span class="col offset-1" style="font-size:110%;font-weight:500">' . $value_html . '</span>
                                    </div>';
    }

    return '<div class="row">
                                        <span class="translateable col fg-secondary">' . $label . ':</span>
                                        <span class="col text-end">' . $value_html . '</span>
                                    </div>';
}

/**
 * The stored address type as the panel names it. The address forms post
 * residential or business; a value from an import is shown as it was stored.
 * Escaped.
 *
 * @param mixed $stored ship_tos.address_type
 * @return string '' for none
 */
function pg_order_view_address_type($stored)
{
    $stored = is_scalar($stored) ? trim((string) $stored) : '';

    switch (strtolower($stored)) {
        case '':
            return '';
        case 'residential':
            return h(lang('Residential'));
        case 'business':
            return h(lang('Business'));
    }

    return h(ucwords($stored));
}

/**
 * A stored country as the panel names it: a two-letter code through
 * pg_geo_country_name(), anything else (an older order that kept the name)
 * as it is. Not escaped: the name goes through h() where it is printed.
 *
 * @param mixed $stored orders.billing_country or ship_tos.country
 * @return string '' for none
 */
function pg_order_view_country($stored)
{
    $stored = is_scalar($stored) ? trim((string) $stored) : '';

    if (!preg_match('/^[A-Za-z]{2}$/', $stored)) {
        return $stored;
    }

    return pg_geo_country_name($stored);
}

/**
 * The distinct arrival date codes the order's recipients chose, for one
 * name lookup (pg_order_view_arrival_names()).
 *
 * @param array $ship_tos rows with arrival_date_code
 * @return array
 */
function pg_order_view_arrival_codes(array $ship_tos)
{
    $codes = array();

    foreach ($ship_tos as $ship_to) {
        $code = (is_array($ship_to) && isset($ship_to['arrival_date_code']) && is_scalar($ship_to['arrival_date_code']))
            ? trim((string) $ship_to['arrival_date_code'])
            : '';

        if (($code !== '') && !in_array($code, $codes, true)) {
            $codes[] = $code;
        }
    }

    return $codes;
}

/**
 * The names of the arrival dates with these codes, code => name, from one
 * query: the name is what the checkout showed the customer. A code that two
 * rows carry takes the enabled one, else the newest. A code whose row was
 * deleted is left out, so the screen falls back to the code.
 *
 * @param array $codes pg_order_view_arrival_codes()
 * @return array
 */
function pg_order_view_arrival_names(array $codes)
{
    $names = array();

    if (count($codes) === 0) {
        return $names;
    }

    $result = db_q(
        "SELECT code, name
         FROM arrival_dates
         WHERE code IN (" . implode(', ', array_fill(0, count($codes), '?')) . ")
         ORDER BY (status = 'enabled') DESC, id DESC",
        array_values($codes)
    );

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (!isset($names[$row['code']])) {
                $names[$row['code']] = (string) $row['name'];
            }
        }
    }

    return $names;
}

/**
 * The requested arrival date as the order screen prints it: the date the
 * customer asked for, then the arrival date's name in brackets; the name
 * alone when the choice carries no date (a ship-at-once choice); the stored
 * code when its arrival date row is gone. Escaped.
 *
 * @param string $date_text the date as the screen prints it, '' for none
 * @param mixed  $code      ship_tos.arrival_date_code
 * @param array  $names     code => name, pg_order_view_arrival_names()
 * @return string
 */
function pg_order_view_arrival_text($date_text, $code, array $names)
{
    $date_text = trim((string) $date_text);
    $code = is_scalar($code) ? trim((string) $code) : '';
    $name = (($code !== '') && isset($names[$code])) ? trim((string) $names[$code]) : '';

    if ($name === '') {
        $name = $code;
    }

    if ($name === '') {
        return h($date_text);
    }

    if ($date_text === '') {
        return h($name);
    }

    return h($date_text) . ' (' . h($name) . ')';
}

/**
 * The text of the contact link on the order screen: the contact's name,
 * else the company, else the e-mail address, else the id marked as one, so
 * a contact card with none of them does not read as a bare number. Escaped.
 *
 * @param mixed $contact_id
 * @param mixed $first_name
 * @param mixed $last_name
 * @param mixed $company
 * @param mixed $email_address
 * @return string
 */
function pg_order_view_contact_text($contact_id, $first_name, $last_name, $company, $email_address)
{
    $text = pg_contact_display_name($first_name, $last_name);

    foreach (array($company, $email_address) as $fallback) {
        if ($text !== '') {
            break;
        }

        $text = is_scalar($fallback) ? trim((string) $fallback) : '';
    }

    if ($text === '') {
        $text = '#' . (int) $contact_id;
    }

    return h($text);
}
