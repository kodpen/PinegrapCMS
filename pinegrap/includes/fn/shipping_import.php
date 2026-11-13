<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shipping in bulk: "mark shipped" on the selected orders (edit_orders.php)
 * and the tracking-number CSV upload (import_shipments.php). Reading and
 * checking the file, the list an address is sent and the request a mark
 * sends are pure; the writes go through update_order(), the order screen's
 * own save, so a parcel entered in bulk is recorded, announced and mailed the
 * same way as one typed in by hand.
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
 * The column separator of a CSV line: of the comma, the semicolon (Excel
 * saves with semicolons in a Turkish locale) and the tab, the one the line
 * holds most outside quoted cells, else a comma (pg_csv_separator(), the
 * other importers' rule).
 *
 * @param  string $line the file's first line
 * @return string
 */
function pg_shipping_import_delimiter($line)
{
    return pg_csv_separator((string) $line);
}

/**
 * The single-byte encoding a cell that is not UTF-8 is read as: Windows-1254
 * where mbstring knows it, ISO-8859-9 otherwise (the two differ only in the
 * 0x80-0x9F range).
 *
 * @return string
 */
function pg_shipping_import_fallback_encoding()
{
    static $encoding = null;

    if ($encoding === null) {
        $known = array_map('strtolower', mb_list_encodings());
        $encoding = in_array('windows-1254', $known, true) ? 'Windows-1254' : 'ISO-8859-9';
    }

    return $encoding;
}

/**
 * The rows of a tracking-number CSV: order_number, number, carrier (the
 * carrier may be left out). A first row whose first cell is "order_number" is
 * the header and is skipped; blank lines are skipped; cells are trimmed and
 * lose the formula guard an export put on them (pg_csv_formula_unguard()). A
 * UTF-8 byte order mark at the start is stepped over before the first cell is
 * read, so a quoted first heading loses its quotes like any other. A cell
 * that is not UTF-8 is read as Excel's Turkish encoding (Windows-1254), so
 * 'Yurtiçi Kargo' saved by Excel in a Turkish locale names the carrier. A
 * row's line is the file line it starts on (a quoted cell may hold a line
 * break), so the result list can point at it.
 *
 * @param  resource $handle an open stream at the start of its first line
 *                          (pg_csv_open() gives one with LF line breaks)
 * @param  int      $limit  the most rows read; reading stops after it
 * @return array 'rows' => list of line, order_number, number, carrier;
 *               'more' => true when the file had rows past the limit
 */
function pg_shipping_import_read($handle, $limit)
{
    $out = array('rows' => array(), 'more' => false);

    $start = (int) ftell($handle);

    if (fread($handle, 3) !== "\xEF\xBB\xBF") {
        fseek($handle, $start);
    }

    $delimiter = pg_csv_read_separator($handle);

    $next_line = 1;

    while (($cells = fgetcsv($handle, 10000, $delimiter, '"', '\\')) !== false) {

        $line = $next_line;
        $next_line += pg_csv_row_lines($cells);

        $cells = array_map(function ($cell) {
            $cell = (string) $cell;

            if (!mb_check_encoding($cell, 'UTF-8')) {
                $cell = mb_convert_encoding($cell, 'UTF-8', pg_shipping_import_fallback_encoding());
            }

            // A cell an order export guarded ('-…) reads back as it was.
            return pg_csv_formula_unguard(trim($cell));
        }, (array) $cells);

        if (($line === 1) && (strtolower($cells[0]) === 'order_number')) {
            continue;
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
 * The rows of an uploaded tracking-number file (pg_shipping_import_read()),
 * opened with pg_csv_open() as the other importers open theirs: a file whose
 * line breaks are a lone CR reads line by line, and the byte order mark is
 * stepped over.
 *
 * @param  string $path  the uploaded file
 * @param  int    $limit the most rows read
 * @return array|null as pg_shipping_import_read(); null when the file cannot
 *                    be opened
 */
function pg_shipping_import_read_file($path, $limit)
{
    $handle = pg_csv_open($path);

    if (!$handle) {
        return null;
    }

    $read = pg_shipping_import_read($handle, $limit);

    fclose($handle);

    return $read;
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

    // orders.order_number is a number column: MySQL compares '10045-1' or
    // '10045abc' by its numeric prefix and would find order 10045, and a
    // UTF-16 file's cells would read as one-digit order numbers.
    if (!preg_match('/^[0-9]{1,18}$/', $row['order_number'])) {
        return lang('An order number is made of digits only.');
    }

    if ($row['number'] === '') {
        return lang('The tracking number is missing.');
    }

    if (mb_strlen($row['number']) > 100) {
        return lang('A tracking number cannot be longer than 100 characters.');
    }

    $refusal = pg_tracking_number_refusal($row['number']);

    if ($refusal !== '') {
        return $refusal;
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
 * sends: the customer is told ('shipped' true), the ship date is today, the
 * delivery date goes back as it is (update_order() writes the two together
 * and one sent alone blanks the other), and every line is shipped in full, so
 * the Unshipped filter, the shipping report and the dashboard read the order
 * as shipped. No tracking numbers: the address's list stays as it is, and a
 * mark without a number is no parcel, so the order does not count as shipped
 * for the customer's cancel gate (_order_has_shipped()).
 *
 * @param  array  $recipient ship_tos row: id, ship_date, delivery_date
 * @param  array  $items     the address's order_items rows: id, quantity
 * @param  string $today     Y-m-d
 * @return array
 */
function pg_shipping_bulk_mark_recipient($recipient, $items, $today)
{
    $request = array(
        'id'            => (int) $recipient['id'],
        'shipped'       => true,
        'ship_date'     => (string) $today,
        'delivery_date' => (string) $recipient['delivery_date'],
        'items'         => array(),
    );

    foreach ((array) $items as $item) {
        $request['items'][] = array(
            'id'               => (int) $item['id'],
            'shipped_quantity' => (int) $item['quantity'],
        );
    }

    return $request;
}

/**
 * Whether any of the lines still has something to ship.
 *
 * @param  array $items order_items rows: quantity, shipped_quantity
 * @return bool
 */
function pg_shipping_bulk_mark_left($items)
{
    foreach ((array) $items as $item) {
        if ((int) $item['quantity'] > (int) $item['shipped_quantity']) {
            return true;
        }
    }

    return false;
}

/**
 * Why the bulk mark leaves an order alone, or '' to mark it: a cancelled
 * order, a cart that was never ordered, an order with nothing to ship to, one
 * that already has a tracking number (its customer was told when the number
 * went in), and one whose lines are all shipped already (marked before, or
 * shipped on the order screen); a second mark would mail the customer again.
 *
 * @param  string $status        orders.status
 * @param  int    $address_count the order's ship_tos rows
 * @param  bool   $has_parcel    _order_has_shipped()
 * @param  bool   $left          pg_shipping_bulk_mark_left() over its lines
 * @return string '', 'cancelled', 'incomplete', 'no_address', 'shipped' or
 *                'shipped_in_full'
 */
function pg_shipping_bulk_mark_skip($status, $address_count, $has_parcel, $left)
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

    if (!$left) {
        return 'shipped_in_full';
    }

    return '';
}

/**
 * Reads the uploaded file, writes each order's numbers and returns the result
 * list as HTML.
 *
 * @param  string $path      the uploaded file
 * @param  bool   $notify    whether customers get the shipping e-mail
 * @param  int    $max_rows
 * @return string
 */
function pg_shipping_import_run($path, $notify, $max_rows)
{
    $read = pg_shipping_import_read_file($path, $max_rows);

    if ($read === null) {
        return '<div class="alert theme-danger">' . h(lang('The file could not be read.')) . '</div>';
    }

    // line => array(class, message); rows are listed in the file's order.
    $results = array();

    // order_number => its valid rows, so an order is written once with all
    // of its new numbers.
    $orders = array();

    foreach ($read['rows'] as $row) {

        $error = pg_shipping_import_row_error($row);

        if ($error !== '') {
            $results[$row['line']] = array('danger', $error);
            continue;
        }

        $orders[$row['order_number']][] = $row;
    }

    require_once(PG_FUNCTIONS_DIR . '/update_order.php');

    $counts = array('added' => 0, 'present' => 0, 'failed' => 0);

    foreach ($orders as $order_number => $rows) {

        $outcome = pg_shipping_import_order($order_number, $rows, $notify);

        foreach ($rows as $row) {
            $results[$row['line']] = $outcome[$row['line']];
        }
    }

    foreach ($results as $result) {
        if ($result[0] === 'success' || $result[0] === 'warning') {
            $counts['added']++;
        } elseif ($result[0] === 'secondary') {
            $counts['present']++;
        } else {
            $counts['failed']++;
        }
    }

    if ($counts['added'] > 0) {
        log_activity(lang(array(
            'string' => '{var:1} tracking number(s) were imported from a CSV file',
            'vars'   => array(pg_format_number($counts['added'], 0)),
        )));
    }

    $output_rows = '';

    foreach ($read['rows'] as $row) {

        $result = $results[$row['line']];

        $output_rows .= '
            <tr>
                <td class="text-end">' . h($row['line']) . '</td>
                <td>' . h($row['order_number']) . '</td>
                <td>' . h($row['number']) . '</td>
                <td>' . h($row['carrier']) . '</td>
                <td><span class="badge theme-' . $result[0] . '">' . h($result[1]) . '</span></td>
            </tr>';
    }

    $output = '
        <div class="alert theme-info">
            ' . h(lang(array(
                'string' => 'Added: {var:1}. Already on the order: {var:2}. Not imported: {var:3}.',
                'vars'   => array(pg_format_number($counts['added'], 0), pg_format_number($counts['present'], 0), pg_format_number($counts['failed'], 0)),
            ))) . '
        </div>';

    if ($read['more']) {
        $output .= '
        <div class="alert theme-warning">' . h(lang(array(
            'string' => 'Only the first {var:1} rows were read. Please upload the rest in another file.',
            'vars'   => array(pg_format_number($max_rows, 0)),
        ))) . '</div>';
    }

    if ($output_rows === '') {
        return $output . '<div class="alert theme-warning">' . h(lang('The file has no rows.')) . '</div>';
    }

    return $output . '
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th class="text-end">' . h(lang('Row')) . '</th>
                        <th>' . h(lang('Order Number')) . '</th>
                        <th>' . h(lang('Tracking Number')) . '</th>
                        <th>' . h(lang('Shipping carrier')) . '</th>
                        <th>' . h(lang('Result')) . '</th>
                    </tr>
                </thead>
                <tbody>' . $output_rows . '
                </tbody>
            </table>
        </div>';
}

/**
 * Adds one order's rows to its address.
 *
 * @param  string $order_number
 * @param  array  $rows   the order's valid rows
 * @param  bool   $notify
 * @return array line => array(badge class, message)
 */
function pg_shipping_import_order($order_number, $rows, $notify)
{
    $refuse = function ($message) use ($rows) {
        $out = array();
        foreach ($rows as $row) {
            $out[$row['line']] = array('danger', $message);
        }
        return $out;
    };

    $order = db_item("SELECT id, status FROM orders WHERE order_number = '" . e($order_number) . "' LIMIT 1");

    if (!$order) {
        return $refuse(lang('No order has this order number.'));
    }

    if ($order['status'] === 'cancelled') {
        return $refuse(lang('This order was cancelled, so a shipment cannot be recorded against it.'));
    }

    if ($order['status'] === 'incomplete') {
        return $refuse(lang('This order was never completed.'));
    }

    $recipients = db_items("SELECT id, ship_date, delivery_date FROM ship_tos WHERE order_id = '" . (int) $order['id'] . "' ORDER BY id");

    if (count($recipients) === 0) {
        return $refuse(lang('This order has no shipping address, so there is nothing to ship.'));
    }

    if (count($recipients) > 1) {
        return $refuse(lang('This order ships to more than one address. Enter its tracking numbers on the order screen.'));
    }

    $recipient = $recipients[0];

    $existing = array();

    foreach (pg_shipment_rows($recipient['id']) as $parcel) {
        if (trim($parcel['number']) !== '') {
            $existing[] = $parcel['number'];
        }
    }

    $entries = pg_shipping_import_entries($existing, $rows);

    $out = array();

    foreach ($entries['status'] as $line => $status) {
        $out[$line] = ($status === 'add')
            ? array('success', lang('Added to the order'))
            : array('secondary', lang('Already on the order'));
    }

    if (!in_array('add', $entries['status'], true)) {
        return $out;
    }

    $request = array(
        'id'               => (int) $recipient['id'],
        'shipped'          => (bool) $notify,
        'tracking_numbers' => $entries['entries'],
        'items'            => array(),
    );

    // An address without a ship date gets today's; the delivery date goes
    // back as it is, because update_order() writes the two together.
    if (in_array((string) $recipient['ship_date'], array('', '0000-00-00'), true)) {
        $request['ship_date'] = date('Y-m-d');
        $request['delivery_date'] = (string) $recipient['delivery_date'];
    }

    $outcome = update_order(array('source' => 'csv', 'order' => array(
        'id'         => (int) $order['id'],
        'recipients' => array($request),
    )));

    if (!is_array($outcome) || (($outcome['status'] ?? '') !== 'success')) {
        return $refuse(lang('The tracking numbers could not be saved.'));
    }

    // A number of a shape its carrier does not use was saved with a warning,
    // the same one the order screen shows beside it.
    $warned = array();

    foreach (pg_shipment_rows($recipient['id']) as $parcel) {
        if (!empty($parcel['format_warning'])) {
            $warned[mb_strtolower($parcel['number'])] = true;
        }
    }

    foreach ($rows as $row) {
        if ($out[$row['line']][0] === 'success' && isset($warned[mb_strtolower($row['number'])])) {
            $out[$row['line']] = array('warning', lang('Added to the order') . ' ' . pg_shipment_warning_text(''));
        }
    }

    return $out;
}
