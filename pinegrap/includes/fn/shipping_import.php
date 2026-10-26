<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shipping in bulk: the pure half of "mark shipped" on the selected orders
 * (edit_orders.php) and of the tracking-number CSV upload
 * (import_shipments.php). Reading and checking the file, the list an address
 * is sent and the request a mark sends live here; the writes go through
 * update_order(), the order screen's own save, so a parcel entered in bulk is
 * recorded, announced and mailed the same way as one typed in by hand.
 *
 * Loaded by the two screens, not by functions.php.
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
 * The column separator of a CSV line: a semicolon when the line has more of
 * them than commas (Excel saves with semicolons in a Turkish locale), a tab
 * likewise, else a comma.
 *
 * @param  string $line the file's first line
 * @return string
 */
function pg_shipping_import_delimiter($line)
{
    $line = (string) $line;

    $counts = array(
        ','  => substr_count($line, ','),
        ';'  => substr_count($line, ';'),
        "\t" => substr_count($line, "\t"),
    );

    arsort($counts);

    $delimiter = key($counts);

    return (current($counts) > 0) ? $delimiter : ',';
}

/**
 * The rows of a tracking-number CSV: order_number, number, carrier (the
 * carrier may be left out). A first row whose first cell is "order_number" is
 * the header and is skipped; blank lines are skipped; cells are trimmed and a
 * byte order mark is removed. A row's line is the file's line, so the result
 * list can point at it.
 *
 * @param  resource $handle an open stream at its start
 * @param  int      $limit  the most rows read; reading stops after it
 * @return array 'rows' => list of line, order_number, number, carrier;
 *               'more' => true when the file had rows past the limit
 */
function pg_shipping_import_read($handle, $limit)
{
    $out = array('rows' => array(), 'more' => false);

    $first = fgets($handle);

    if ($first === false) {
        return $out;
    }

    $delimiter = pg_shipping_import_delimiter($first);

    rewind($handle);

    $line = 0;

    while (($cells = fgetcsv($handle, 10000, $delimiter, '"', '\\')) !== false) {

        $line++;

        $cells = array_map(function ($cell) {
            return trim((string) $cell);
        }, (array) $cells);

        if ($line === 1) {
            $cells[0] = trim(preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]));

            if (strtolower($cells[0]) === 'order_number') {
                continue;
            }
        }

        if (implode('', $cells) === '') {
            continue;
        }

        if (count($out['rows']) >= $limit) {
            $out['more'] = true;
            break;
        }

        $out['rows'][] = array(
            'line'         => $line,
            'order_number' => $cells[0],
            'number'       => isset($cells[1]) ? $cells[1] : '',
            'carrier'      => isset($cells[2]) ? $cells[2] : '',
        );
    }

    return $out;
}

/**
 * Why a row cannot be imported, or '' when it can. A number of a shape its
 * carrier does not use is not refused: like the order screen, the row is
 * saved with a format warning.
 *
 * @param  array  $row from pg_shipping_import_read()
 * @return string
 */
function pg_shipping_import_row_error($row)
{
    if ($row['order_number'] === '') {
        return lang('The order number is missing.');
    }

    if ($row['number'] === '') {
        return lang('The tracking number is missing.');
    }

    if (mb_strlen($row['number']) > 100) {
        return lang('A tracking number cannot be longer than 100 characters.');
    }

    if (($row['carrier'] !== '') && (pg_shipping_method_carrier($row['carrier']) === '')) {
        return lang('The carrier is not one the store knows.');
    }

    return '';
}

/**
 * The tracking_numbers list update_order() is sent for one address: the
 * numbers it has, then the rows' new ones. update_order() replaces the
 * address's list with what it is sent, so the numbers it has go back as they
 * are (plain, so each keeps its own carrier); a new one names its carrier
 * when the row gave one. A number the address has, or one an earlier row
 * added, is not added again (compared without regard to case, as the
 * shipment rows' unique key does).
 *
 * @param  string[] $existing the address's numbers
 * @param  array    $rows     the order's valid rows
 * @return array 'entries' => the list; 'status' => line => 'add' | 'present'
 */
function pg_shipping_import_entries($existing, $rows)
{
    $out = array('entries' => array(), 'status' => array());

    $seen = array();

    foreach ((array) $existing as $number) {
        $out['entries'][] = $number;
        $seen[mb_strtolower($number)] = true;
    }

    foreach ($rows as $row) {

        $key = mb_strtolower($row['number']);

        if (isset($seen[$key])) {
            $out['status'][$row['line']] = 'present';
            continue;
        }

        $seen[$key] = true;

        $out['entries'][] = ($row['carrier'] !== '')
            ? array('number' => $row['number'], 'carrier' => $row['carrier'])
            : $row['number'];

        $out['status'][$row['line']] = 'add';
    }

    return $out;
}

/**
 * One address's part of the update_order() request a bulk "mark shipped"
 * sends: the customer is told ('shipped' true), the ship date is today, and
 * the delivery date goes back as it is, because update_order() writes the two
 * together and one sent alone blanks the other. No tracking numbers: the
 * address's list stays as it is, and a mark without a number is no parcel, so
 * the order does not count as shipped for the cancel gate
 * (_order_has_shipped()).
 *
 * @param  array  $recipient ship_tos row: id, ship_date, delivery_date
 * @param  string $today     Y-m-d
 * @return array
 */
function pg_shipping_bulk_mark_recipient($recipient, $today)
{
    return array(
        'id'            => (int) $recipient['id'],
        'shipped'       => true,
        'ship_date'     => (string) $today,
        'delivery_date' => (string) $recipient['delivery_date'],
        'items'         => array(),
    );
}

/**
 * Why the bulk mark leaves an order alone, or '' to mark it: a cancelled
 * order, a cart that was never ordered, an order with nothing to ship to, and
 * one that already has a tracking number (its customer was told when the
 * number went in; a second mail would announce the same parcel again).
 *
 * @param  string $status        orders.status
 * @param  int    $address_count the order's ship_tos rows
 * @param  bool   $has_parcel    _order_has_shipped()
 * @return string '', 'cancelled', 'incomplete', 'no_address' or 'shipped'
 */
function pg_shipping_bulk_mark_skip($status, $address_count, $has_parcel)
{
    if ($status === 'cancelled') {
        return 'cancelled';
    }

    if ($status === 'incomplete') {
        return 'incomplete';
    }

    if ((int) $address_count <= 0) {
        return 'no_address';
    }

    if ($has_parcel) {
        return 'shipped';
    }

    return '';
}
