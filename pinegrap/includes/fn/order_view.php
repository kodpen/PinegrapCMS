<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel order screen's card rows (view_order.php): a "label: value" row
 * only when there is a value, the stored address type and country code as
 * the panel names them, the district only when it differs from the city,
 * the requested arrival date as the customer chose it
 * at the checkout, the text of the contact link, the printed order's
 * shipping card (print_order.php), the totals rows of the customer's printed
 * invoice (order_invoice_print.php), and the shipping input the screen and
 * update_order() check (ship and delivery dates, shipped quantity).
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
 * The district of an order address as it is printed before the city, or ''
 * when it only repeats the city: marketplace imports store the district in
 * both ship_tos.city and ship_tos.district. Compared the Turkish way
 * (pg_list_search_fold(): I, İ, ı and i are one letter), ignoring case and
 * spaces. Not escaped: the text goes through h() where it is printed.
 *
 * @param mixed $district ship_tos.district (null when the column is missing)
 * @param mixed $city ship_tos.city
 * @return string
 */
function pg_order_view_district($district, $city)
{
    $district = is_scalar($district) ? trim((string) $district) : '';

    if ($district === '') {
        return '';
    }

    $city = is_scalar($city) ? (string) $city : '';
    $fold = function ($text) {
        return preg_replace('/\s+/u', '', pg_list_search_fold($text));
    };

    return $fold($district) === $fold($city) ? '' : $district;
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

/**
 * The printed order's shipping card (print_order.php): a recipient's rows
 * under the "Shipping Information" heading, in the print view's section
 * look. A row without a value is left out; a card with no value at all
 * prints nothing.
 *
 * @param array $rows label (plain text) => value markup (escaped text or
 *                    markup the caller built), in print order
 * @return string
 */
function pg_print_order_shipping_card(array $rows)
{
    $rows_html = '';

    foreach ($rows as $label => $value_html) {
        $rows_html .= pg_order_view_row(h((string) $label), $value_html);
    }

    if ($rows_html === '') {
        return '';
    }

    return '<div class="print-order-shipping-card" style="margin-bottom:.5em;font-size:.9em">
                <div class="ord-section-title">' . h(lang('Shipping Information')) . '</div>
                ' . $rows_html . '
            </div>';
}

/**
 * A recipient's tracking numbers as the printed order lists them: every
 * number, one a line, its carrier in brackets when known. Paper takes no
 * link, so unlike the order screen a number is printed whether or not its
 * carrier has a tracking page. Escaped.
 *
 * @param array $parcels rows of pg_shipment_view(): number, carrier_name
 * @return string
 */
function pg_print_order_tracking_text(array $parcels)
{
    $lines = array();

    foreach ($parcels as $parcel) {
        $number = isset($parcel['number']) ? trim((string) $parcel['number']) : '';

        if ($number === '') {
            continue;
        }

        $carrier = isset($parcel['carrier_name']) ? trim((string) $parcel['carrier_name']) : '';
        $lines[] = h($number) . (($carrier !== '') ? ' (' . h($carrier) . ')' : '');
    }

    return implode('<br />', $lines);
}

/**
 * Whether the signed-in account owns the order, for the customer's order
 * history endpoints and the view order screen. A visitor who is not signed in
 * finds no account row (null), and a guest order belongs to account 0: a
 * loose comparison takes "0" and null as equal, which handed every guest order
 * to every visitor. Account 0 owns nothing.
 *
 * @param mixed $order_user_id the order's user_id column
 * @param mixed $user_id       the signed-in account's id, null when none
 * @return bool
 */
function pg_order_view_owner_matches($order_user_id, $user_id)
{
    if (!is_scalar($order_user_id) || !is_scalar($user_id)) {
        return false;
    }

    $user_id = (int) $user_id;

    return ($user_id > 0) && ((int) $order_user_id === $user_id);
}

/**
 * The heading cell of the shipped quantity column on the customer's view
 * order screen, the word in the visitor's language.
 *
 * @return string
 */
function pg_order_view_shipped_heading_cell()
{
    return '<th style="text-align: left">(' . h(lang('Shipped')) . ')</th>';
}

/**
 * A ship or delivery date typed on the order screen, as the column stores it.
 * Day, month and a four-digit year split by '.', '/', '-' or ',' (the dotted
 * form is how a date is written in Turkey), in the site's order of day and
 * month, or an ISO date. A two-digit year, a day that does not exist or any
 * other text is refused rather than saved as year 0026 or 0000-00-00.
 *
 * @param mixed $text        the posted field
 * @param bool  $month_first DATE_FORMAT is 'month_day'
 * @return array ok => bool, date => 'Y-m-d', or '' for an empty field
 */
function pg_order_ship_date_parse($text, $month_first)
{
    $text = is_scalar($text) ? trim((string) $text) : '';

    if ($text === '') {
        return array('ok' => true, 'date' => '');
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $parts) === 1) {
        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $day = (int) $parts[3];
    } elseif (preg_match('/^(\d{1,2})[.\/,-](\d{1,2})[.\/,-](\d{4})$/', $text, $parts) === 1) {
        $year = (int) $parts[3];
        $month = (int) ($month_first ? $parts[1] : $parts[2]);
        $day = (int) ($month_first ? $parts[2] : $parts[1]);
    } else {
        return array('ok' => false, 'date' => '');
    }

    if (($year < 1000) || !checkdate($month, $day, $year)) {
        return array('ok' => false, 'date' => '');
    }

    return array('ok' => true, 'date' => sprintf('%04d-%02d-%02d', $year, $month, $day));
}

/**
 * Whether a delivery date does not come before the ship date. Either one
 * empty (or the column's 0000-00-00) has nothing to compare.
 *
 * @param string $ship_date     Y-m-d
 * @param string $delivery_date Y-m-d
 * @return bool
 */
function pg_order_ship_dates_in_order($ship_date, $delivery_date)
{
    $ship_date = (string) $ship_date;
    $delivery_date = (string) $delivery_date;

    if (in_array($ship_date, array('', '0000-00-00'), true) || in_array($delivery_date, array('', '0000-00-00'), true)) {
        return true;
    }

    return strcmp($delivery_date, $ship_date) >= 0;
}

/**
 * A line's shipped quantity as update_order() writes it. An empty value
 * hides the quantity (show 0); otherwise a whole number from 0 to the
 * ordered quantity. A negative, decimal or larger value is refused: the
 * unsigned column would turn -2 into 0 and 1.5 into 2 without a word.
 *
 * @param mixed $value   the posted value
 * @param mixed $ordered order_items.quantity
 * @return array ok => bool, show => 0|1, quantity => int
 */
function pg_order_shipped_quantity($value, $ordered)
{
    $value = is_scalar($value) ? trim((string) $value) : '';

    if ($value === '') {
        return array('ok' => true, 'show' => 0, 'quantity' => 0);
    }

    if ((preg_match('/^\d{1,9}$/', $value) !== 1) || ((int) $value > max(0, (int) $ordered))) {
        return array('ok' => false, 'show' => 0, 'quantity' => 0);
    }

    return array('ok' => true, 'show' => 1, 'quantity' => (int) $value);
}

/**
 * The first reason update_order() refuses a shipping save, or '' when the
 * save may go ahead. Every recipient and line is checked before anything is
 * written: a refusal found halfway through used to leave the dates and the
 * tracking numbers of the save stored, and the next save no longer saw those
 * numbers as new, so the shipment was never announced.
 *
 * @param array $recipients the request's recipients (id, ship_date,
 *                          delivery_date, items with id and shipped_quantity)
 * @param mixed $order_id   the order the save is for
 * @param array $ship_tos   ship_tos rows by id (order_id)
 * @param array $items      order_items rows by id (recipient_id, quantity)
 * @return string
 */
function pg_order_update_request_error(array $recipients, $order_id, array $ship_tos, array $items)
{
    foreach ($recipients as $recipient_request) {

        $recipient_request = is_array($recipient_request) ? $recipient_request : array();
        $recipient_id = (isset($recipient_request['id']) && is_scalar($recipient_request['id'])) ? (string) $recipient_request['id'] : '';

        if (($recipient_id === '') || !isset($ship_tos[$recipient_id])) {
            return 'The recipient could not be found.';
        }

        if ((string) $ship_tos[$recipient_id]['order_id'] !== (string) $order_id) {
            return 'The recipient does not belong to that order.';
        }

        // Each date is a real day or empty: anything else would be stored
        // as 0000-00-00 and wipe the saved date.
        if (isset($recipient_request['ship_date']) || isset($recipient_request['delivery_date'])) {

            foreach (array('ship_date', 'delivery_date') as $date_key) {
                $date_value = isset($recipient_request[$date_key]) ? $recipient_request[$date_key] : '';

                if (!is_scalar($date_value)
                    || (!in_array((string) $date_value, array('', '0000-00-00'), true) && (pg_shipment_date((string) $date_value) !== (string) $date_value))
                ) {
                    return lang('The ship or delivery date is not a valid date.');
                }
            }
        }

        if (!isset($recipient_request['items']) || !is_array($recipient_request['items'])) {
            continue;
        }

        foreach ($recipient_request['items'] as $item_request) {

            $item_request = is_array($item_request) ? $item_request : array();
            $item_id = (isset($item_request['id']) && is_scalar($item_request['id'])) ? (string) $item_request['id'] : '';

            if (($item_id === '') || !isset($items[$item_id])) {
                return 'The item could not be found (id: ' . $item_id . ').';
            }

            if ((string) $items[$item_id]['recipient_id'] !== $recipient_id) {
                return 'The item (id: ' . $item_id . ') does not belong to that recipient (id: ' . $recipient_id . ').';
            }

            if (!isset($item_request['shipped_quantity'])) {
                continue;
            }

            if (!pg_order_shipped_quantity($item_request['shipped_quantity'], $items[$item_id]['quantity'])['ok']) {
                return lang(array('string' => 'The shipped quantity must be a whole number from 0 to {var:1}.', 'vars' => array((int) $items[$item_id]['quantity'])));
            }
        }
    }

    return '';
}

/**
 * The totals rows of the customer's printed invoice (order_invoice_print.php),
 * in print order: every figure that makes up orders.total, the total last.
 * A gift card lowers the total and a bank's instalment difference raises it,
 * neither with an order line of its own; without their rows the printed
 * figures did not add up to the total under them. A zero figure has no row,
 * the subtotal and the total always have one.
 *
 * @param array $order orders row (minor units); a missing column counts as 0
 * @return array list of ['key', 'amount' (minor units, not negative),
 *                       'negative' (taken off), 'grand' (the total)]
 */
function pg_order_print_totals(array $order)
{
    $figures = array(
        'subtotal' => false,
        'discount' => true,
        'tax' => false,
        'shipping' => false,
        'surcharge' => false,
        'gift_card_discount' => true,
        'installment_charges' => false,
    );

    $rows = array();

    foreach ($figures as $column => $negative) {
        $amount = (int) ($order[$column] ?? 0);

        if (($amount <= 0) && ($column !== 'subtotal')) {
            continue;
        }

        $rows[] = array(
            'key' => ($column === 'gift_card_discount') ? 'gift_card' : $column,
            'amount' => $amount,
            'negative' => $negative,
            'grand' => false,
        );
    }

    $rows[] = array('key' => 'total', 'amount' => (int) ($order['total'] ?? 0), 'negative' => false, 'grand' => true);

    return $rows;
}
