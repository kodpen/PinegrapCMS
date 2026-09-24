<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - delivery notes (sevk irsaliyesi).
 *
 * A delivery note records that goods left: which account they went to, where,
 * when, with which carrier and driver, and what was on the vehicle. It carries
 * no money. It is written once, numbered in its own series, and afterwards it
 * is either turned into an invoice or cancelled; the goods on it are what the
 * invoice then bills.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Loaded from includes/erp/bootstrap.php, never on its own.
//
// Only the internal document. An e-delivery note (e-İrsaliye) would need an
// endpoint that turns a shipment document into a tax-authority document, and
// the Paraşüt API does not offer one; the erp_waybills table carries no e-doc
// columns for that reason and nothing here pretends otherwise.
if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/** The most lines one delivery note may carry. */
if (!defined('ERP_WAYBILL_MAX_LINES')) {
    define('ERP_WAYBILL_MAX_LINES', 200);
}

/**
 * The series delivery notes are numbered in.
 *
 * A note cannot share the invoice series: the number printed on a document is
 * what identifies it. The site may name a series in config.php
 * (ERP_WAYBILL_SERIES); otherwise it is the invoice series with an S after it,
 * the way returns take an I.
 *
 * @return string
 */
function erp_waybill_series()
{
    if (defined('ERP_WAYBILL_SERIES') && (trim((string) ERP_WAYBILL_SERIES) !== '')) {
        return trim((string) ERP_WAYBILL_SERIES);
    }

    return (defined('ERP_DEFAULT_SERIES') ? trim((string) ERP_DEFAULT_SERIES) : 'PGF') . 'S';
}

/**
 * One delivery note with the live account title and, when it has been
 * invoiced, the invoice's number and status.
 *
 * @param int $waybill_id
 * @return array|null
 */
function erp_waybill($waybill_id)
{
    $waybill_id = (int) $waybill_id;

    if ($waybill_id <= 0) {
        return null;
    }

    $row = db_item("SELECT w.*, a.title AS account_title, o.order_number,
            i.full_number AS invoice_number, i.status AS invoice_status
        FROM erp_waybills w
        LEFT JOIN erp_accounts a ON a.id = w.account_id
        LEFT JOIN orders o ON o.id = w.order_id
        LEFT JOIN erp_invoices i ON i.id = w.invoice_id
        WHERE w.id = '" . $waybill_id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The lines of a delivery note, with the live product name beside the
 * description that was written.
 *
 * @param int $waybill_id
 * @return array
 */
function erp_waybill_lines($waybill_id)
{
    return (array) db_items("SELECT l.*, p.name AS product_name
        FROM erp_waybill_items l
        LEFT JOIN products p ON p.id = l.product_id
        WHERE l.waybill_id = '" . (int) $waybill_id . "'
        ORDER BY l.line_no ASC, l.id ASC");
}

/**
 * Where a delivery note stands with its invoice.
 *
 * A note whose invoice was cancelled, or whose draft was thrown away, is back
 * to being uninvoiced: the goods still have to be billed.
 *
 * @param array $waybill  Row from erp_waybill()
 * @return string  'none' | 'draft' | 'invoiced'
 */
function erp_waybill_invoice_state($waybill)
{
    if (((int) ($waybill['invoice_id'] ?? 0) <= 0) || !isset($waybill['invoice_status']) || ($waybill['invoice_status'] === null)) {
        return 'none';
    }

    $status = (string) $waybill['invoice_status'];

    if ($status === 'cancelled') {
        return 'none';
    }

    return ($status === 'draft') ? 'draft' : 'invoiced';
}

/**
 * The unit codes offered on a line: the invoice editor's list, so a line
 * carried over to the invoice keeps its unit.
 *
 * @return array  code => label
 */
function erp_waybill_unit_codes()
{
    return function_exists('erp_invoice_form_unit_codes') ? erp_invoice_form_unit_codes() : array('C62' => lang('piece'));
}

/**
 * The unit as a person reads it: "piece" for C62. A code the list does not
 * know is shown as it is, so nothing is hidden.
 *
 * @param string $code
 * @return string
 */
function erp_waybill_unit_label($code)
{
    $code = (string) $code;
    $codes = erp_waybill_unit_codes();

    return isset($codes[$code]) ? (string) $codes[$code] : $code;
}

/**
 * Work out the lines of a delivery note from what was typed.
 *
 * Pure: reads nothing and writes nothing. A row with nothing in it is skipped;
 * a row with something in it has to say what and how much.
 *
 * @param array $lines_in  Each: description, quantity, and optionally
 *                         product_id, unit_code
 * @return array ['lines' => array, 'error' => string]
 */
function erp_waybill_lines_build($lines_in)
{
    $lines_in = array_values((array) $lines_in);

    if (count($lines_in) > ERP_WAYBILL_MAX_LINES) {
        return array('lines' => array(), 'error' => lang(array('string' => 'A delivery note can carry at most {var:1} lines.', 'vars' => ERP_WAYBILL_MAX_LINES)));
    }

    $lines = array();
    $row_no = 0;

    foreach ($lines_in as $line) {
        $row_no++;

        $description = mb_substr(trim((string) ($line['description'] ?? '')), 0, 255);
        $quantity_raw = trim((string) ($line['quantity'] ?? ''));
        // Digits and separators only; the separators are read the way the
        // store's country writes them (erp_quantity_in()).
        $quantity_valid = (preg_match('/^[0-9][0-9., ]*$/', $quantity_raw) === 1);
        $quantity = $quantity_valid ? erp_quantity_in($quantity_raw) : 0.0;
        $product_id = (int) ($line['product_id'] ?? 0);

        $unit_code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($line['unit_code'] ?? '')));
        $unit_code = ($unit_code === '') ? 'C62' : substr($unit_code, 0, 10);

        if (($description === '') && ($quantity_raw === '') && ($product_id === 0)) {
            continue;
        }

        if ($description === '') {
            return array('lines' => array(), 'error' => lang(array('string' => 'Line {var:1} needs a description.', 'vars' => $row_no)));
        }
        if (($quantity <= 0) || !$quantity_valid) {
            return array('lines' => array(), 'error' => lang(array('string' => 'Line {var:1} needs a quantity greater than zero.', 'vars' => $row_no)));
        }

        $lines[] = array(
            'product_id' => max(0, $product_id),
            'description' => $description,
            'quantity' => round($quantity, 4),
            'unit_code' => $unit_code,
        );
    }

    return array('lines' => $lines, 'error' => '');
}

/**
 * Read and check the header of a delivery note.
 *
 * @param array $data  account_id, order_id, issue_date (Y-m-d), ship_date
 *                     (Y-m-d, empty = issue date), ship_time (HH:MM, may be
 *                     empty), carrier_title, carrier_vkn, plate, driver_name,
 *                     driver_tckn, ship_to_title, ship_to_address,
 *                     ship_to_district, ship_to_city, ship_to_state,
 *                     ship_to_country, notes
 * @return array ['header' => array, 'error' => string]
 */
function erp_waybill_header_build($data)
{
    $fail = function ($message) {
        return array('header' => array(), 'error' => $message);
    };

    $account_id = (int) ($data['account_id'] ?? 0);

    if (($account_id <= 0) || !is_array(erp_account($account_id))) {
        return $fail(lang('Choose an account.'));
    }

    $is_date = function ($value) {
        $value = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }
        $parts = explode('-', $value);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    };

    $issue_date = trim((string) ($data['issue_date'] ?? ''));
    if ($issue_date === '') {
        $issue_date = date('Y-m-d');
    }
    if (!$is_date($issue_date)) {
        return $fail(lang('Please enter a valid date.'));
    }

    $ship_date = trim((string) ($data['ship_date'] ?? ''));
    if ($ship_date === '') {
        $ship_date = $issue_date;
    }
    if (!$is_date($ship_date)) {
        return $fail(lang('Please enter a valid shipment date.'));
    }

    // The hour the goods left, as the printed note states it. Optional.
    $ship_time = trim((string) ($data['ship_time'] ?? ''));
    if ($ship_time !== '') {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $ship_time, $m) !== 1 || ((int) $m[1] > 23) || ((int) $m[2] > 59)) {
            return $fail(lang('Please enter the shipment time as HH:MM.'));
        }
        $ship_time = sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
    } else {
        $ship_time = '00:00:00';
    }

    // The carrier and the driver are held to the store's country's rules:
    // in Turkey a VKN/TCKN and an eleven-digit TCKN (e-İrsaliye asks for
    // both); elsewhere what was written - a carrier's tax id, a driver's
    // licence - within the column.
    if (erp_account_country('') === 'TR') {
        $carrier_vkn = substr(preg_replace('/\D/', '', (string) ($data['carrier_vkn'] ?? '')), 0, 11);
        if (($carrier_vkn !== '') && !in_array(strlen($carrier_vkn), array(10, 11), true)) {
            return $fail(lang('The carrier tax number has to be 10 or 11 digits.'));
        }

        $driver_tckn = substr(preg_replace('/\D/', '', (string) ($data['driver_tckn'] ?? '')), 0, 11);
        if (($driver_tckn !== '') && (strlen($driver_tckn) !== 11)) {
            return $fail(lang('The driver ID number has to be 11 digits.'));
        }
    } else {
        $carrier = erp_tax_number_check((string) ($data['carrier_vkn'] ?? ''));
        if ($carrier['error'] !== '') {
            return $fail(lang('The carrier tax number:') . ' ' . $carrier['error']);
        }
        $carrier_vkn = $carrier['value'];
        $driver_tckn = mb_substr(trim((string) ($data['driver_tckn'] ?? '')), 0, 32);
    }

    $country = strtoupper(trim((string) ($data['ship_to_country'] ?? '')));
    if ($country === '') {
        $country = erp_default_country_code();
    }
    if (strlen($country) !== 2) {
        // A name rather than a code: the store's country list knows it.
        $code = db_value("SELECT code FROM countries WHERE name = '" . escape($country) . "' LIMIT 1");
        $country = (is_string($code) && (strlen($code) === 2)) ? strtoupper($code) : erp_default_country_code();
    }

    $text = function ($key, $length) use ($data) {
        return mb_substr(trim((string) ($data[$key] ?? '')), 0, $length);
    };

    return array('header' => array(
        'account_id' => $account_id,
        'order_id' => max(0, (int) ($data['order_id'] ?? 0)),
        'invoice_id' => max(0, (int) ($data['invoice_id'] ?? 0)),
        'issue_date' => $issue_date,
        'ship_date' => $ship_date,
        'ship_time' => $ship_time,
        'carrier_title' => $text('carrier_title', 255),
        'carrier_vkn' => $carrier_vkn,
        'plate' => strtoupper($text('plate', 20)),
        'driver_name' => $text('driver_name', 100),
        'driver_tckn' => $driver_tckn,
        'ship_to_title' => $text('ship_to_title', 255),
        'ship_to_address' => $text('ship_to_address', 255),
        'ship_to_district' => $text('ship_to_district', 100),
        'ship_to_city' => $text('ship_to_city', 100),
        // A Turkish address has no state: the province is the city.
        'ship_to_state' => ($country === 'TR') ? '' : $text('ship_to_state', 100),
        'ship_to_country' => $country,
        'notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 5000),
    ), 'error' => '');
}

/**
 * The delivery notes already written for an order, cancelled ones left out.
 *
 * @param int $order_id
 * @return array  Rows: id, full_number, status
 */
function erp_waybills_for_order($order_id)
{
    $order_id = (int) $order_id;

    if ($order_id <= 0) {
        return array();
    }

    return (array) db_items("SELECT id, full_number, status FROM erp_waybills
        WHERE order_id = '" . $order_id . "' AND status <> 'cancelled'
        ORDER BY id ASC");
}

/**
 * Write a delivery note: number, header and lines in one transaction.
 *
 * An order takes one note. A second one for the same order is refused rather
 * than numbered, so a shipment is never on paper twice; cancel the first one
 * to write it again.
 *
 * The note is tied to its invoice from the start when there is one: the one
 * named in $data (a note written from an invoice), otherwise the invoice the
 * order already carries. The usual order of things is invoice first, note
 * second, and the operator should not have to link the two by hand. Once
 * written, what the note knows is written back where it was missing (see
 * erp_waybill_write_back()); 'written_back' says what.
 *
 * @param array $data  See erp_waybill_header_build(), plus lines (see
 *                     erp_waybill_lines_build()), invoice_id and created_by
 * @return array ['success' => bool, 'waybill_id' => int, 'full_number' => string, 'error' => string, 'written_back' => array]
 */
function erp_waybill_create($data)
{
    $fail = function ($message) {
        return array('success' => false, 'waybill_id' => 0, 'full_number' => '', 'error' => $message, 'written_back' => array());
    };

    $built_header = erp_waybill_header_build($data);
    if ($built_header['error'] !== '') {
        return $fail($built_header['error']);
    }
    $header = $built_header['header'];

    $built = erp_waybill_lines_build($data['lines'] ?? array());
    if ($built['error'] !== '') {
        return $fail($built['error']);
    }
    if (empty($built['lines'])) {
        return $fail(lang('Enter at least one line.'));
    }

    if (($header['order_id'] > 0) && erp_waybills_for_order($header['order_id'])) {
        return $fail(lang('This order already has a delivery note. Cancel it first to write another.'));
    }

    // The invoice the note belongs to: named, or the order's. A named invoice
    // that is gone or cancelled is not linked; a note is still written.
    $invoice_id = (int) $header['invoice_id'];
    if (($invoice_id <= 0) && ($header['order_id'] > 0) && function_exists('erp_invoice_for_order')) {
        $invoice_id = (int) erp_invoice_for_order($header['order_id']);
    }
    if ($invoice_id > 0) {
        $invoice_status = (string) db_value("SELECT status FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");
        if (($invoice_status === '') || ($invoice_status === 'cancelled')) {
            $invoice_id = 0;
        }
    }

    $series = erp_waybill_series();
    $issue_year = (int) substr($header['issue_date'], 0, 4);
    $now = time();

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $numbered = erp_next_number($series, 'waybill', $issue_year);

    if (!$numbered['success']) {
        erp_tx_rollback();
        return $fail($numbered['error']);
    }

    $ok = erp_query("INSERT INTO erp_waybills SET
            series = '" . escape($series) . "',
            number = '" . (int) $numbered['number'] . "',
            issue_year = '" . (int) $numbered['year'] . "',
            full_number = '" . escape($numbered['full']) . "',
            account_id = '" . (int) $header['account_id'] . "',
            order_id = '" . (int) $header['order_id'] . "',
            invoice_id = '" . $invoice_id . "',
            issue_date = '" . escape($header['issue_date']) . "',
            ship_date = '" . escape($header['ship_date']) . "',
            ship_time = '" . escape($header['ship_time']) . "',
            carrier_title = '" . escape($header['carrier_title']) . "',
            carrier_vkn = '" . escape($header['carrier_vkn']) . "',
            plate = '" . escape($header['plate']) . "',
            driver_name = '" . escape($header['driver_name']) . "',
            driver_tckn = '" . escape($header['driver_tckn']) . "',
            ship_to_title = '" . escape($header['ship_to_title']) . "',
            ship_to_address = '" . escape($header['ship_to_address']) . "',
            ship_to_district = '" . escape($header['ship_to_district']) . "',
            ship_to_city = '" . escape($header['ship_to_city']) . "',
            " . (waf_table_has_column('erp_waybills', 'ship_to_state') ? "ship_to_state = '" . escape($header['ship_to_state']) . "'," : '') . "
            ship_to_country = '" . escape($header['ship_to_country']) . "',
            status = 'issued',
            notes = '" . escape($header['notes']) . "',
            created_by = '" . (int) ($data['created_by'] ?? 0) . "',
            created_at = '" . $now . "',
            updated_at = '" . $now . "'");

    if ($ok === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    $waybill_id = (int) mysqli_insert_id(db::$con);
    $line_no = 0;

    foreach ($built['lines'] as $line) {
        $line_no++;

        $ok = erp_query("INSERT INTO erp_waybill_items SET
                waybill_id = '" . $waybill_id . "',
                line_no = '" . $line_no . "',
                product_id = '" . (int) $line['product_id'] . "',
                description = '" . escape($line['description']) . "',
                quantity = '" . number_format((float) $line['quantity'], 4, '.', '') . "',
                unit_code = '" . escape($line['unit_code']) . "'");

        if ($ok === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(($error !== '') ? $error : lang('The delivery note could not be saved.'));
    }

    $written_back = erp_waybill_write_back($waybill_id);

    erp_event_waybill($waybill_id);

    return array('success' => true, 'waybill_id' => $waybill_id, 'full_number' => $numbered['full'], 'error' => '', 'written_back' => $written_back);
}

/**
 * Carry what a delivery note knows to the records that were still missing
 * it, so the shipment is written once.
 *
 * The order's recipients get the ship date when they have none yet - the
 * order screen shows it as the day the goods left. The invoice gets the
 * shipment date and the carrier when its own are empty - the VUK 509 fields
 * an e-archive document has to state. Nothing that already says something is
 * overwritten: a date the order screen recorded, a carrier the invoice
 * already named, stand.
 *
 * @param int $waybill_id
 * @return array  Keys present for what was written: 'order_ship_date',
 *                'invoice_shipment_date', 'invoice_carrier'
 */
function erp_waybill_write_back($waybill_id)
{
    $waybill = erp_waybill((int) $waybill_id);
    $written = array();

    if (!is_array($waybill) || ((string) $waybill['status'] === 'cancelled')) {
        return $written;
    }

    $ship_date = (string) $waybill['ship_date'];
    $has_ship_date = (($ship_date !== '') && ($ship_date !== '0000-00-00'));

    if ($has_ship_date && ((int) $waybill['order_id'] > 0)) {
        if (erp_query("UPDATE ship_tos SET ship_date = '" . escape($ship_date) . "'
            WHERE order_id = '" . (int) $waybill['order_id'] . "' AND (ship_date IS NULL OR ship_date = '0000-00-00')") !== false
            && (mysqli_affected_rows(db::$con) > 0)) {
            $written['order_ship_date'] = $ship_date;
        }
    }

    if ((int) $waybill['invoice_id'] > 0) {
        $invoice = db_item("SELECT id, status, shipment_date, carrier_title, carrier_vkn FROM erp_invoices WHERE id = '" . (int) $waybill['invoice_id'] . "' LIMIT 1");

        if (is_array($invoice) && ((string) $invoice['status'] !== 'cancelled')) {
            $sets = array();

            if ($has_ship_date && (((string) $invoice['shipment_date'] === '') || ((string) $invoice['shipment_date'] === '0000-00-00'))) {
                $sets[] = "shipment_date = '" . escape($ship_date) . "'";
                $written['invoice_shipment_date'] = $ship_date;
            }
            if ((trim((string) $invoice['carrier_title']) === '') && (trim((string) $waybill['carrier_title']) !== '')) {
                $sets[] = "carrier_title = '" . escape(mb_substr((string) $waybill['carrier_title'], 0, 255)) . "'";
                $sets[] = "carrier_vkn = '" . escape(mb_substr((string) $waybill['carrier_vkn'], 0, 32)) . "'";
                $written['invoice_carrier'] = (string) $waybill['carrier_title'];
            }

            if ($sets !== array()) {
                erp_query("UPDATE erp_invoices SET " . implode(', ', $sets) . " WHERE id = '" . (int) $invoice['id'] . "'");
            }
        }
    }

    return $written;
}

/**
 * What a delivery note for an invoice would say, before anything is written.
 *
 * An invoice written from an order hands over to the order's reading, with
 * the invoice attached. A typed invoice gives its own lines and its account's
 * address; the carrier and the shipment date are what the invoice already
 * states, when it states them.
 *
 * @param int $invoice_id
 * @param int $created_by
 * @return array ['success' => bool, 'data' => array, 'error' => string]
 */
function erp_waybill_data_from_invoice($invoice_id, $created_by = 0)
{
    $invoice_id = (int) $invoice_id;

    $fail = function ($message) {
        return array('success' => false, 'data' => array(), 'error' => $message);
    };

    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return $fail(lang('The invoice could not be found.'));
    }
    if ((string) $invoice['status'] === 'cancelled') {
        return $fail(lang('A cancelled invoice cannot be shipped against.'));
    }
    if ((string) $invoice['status'] === 'draft') {
        return $fail(lang('Issue the invoice first; a draft has no number for the delivery note to name.'));
    }
    if ((string) ($invoice['direction'] ?? 'sales') !== 'sales') {
        return $fail(lang('A delivery note is written for a sale; a purchase bill has none.'));
    }

    if ((int) $invoice['order_id'] > 0) {
        $from_order = erp_waybill_data_from_order((int) $invoice['order_id'], $created_by);

        if ($from_order['success']) {
            $from_order['data']['invoice_id'] = $invoice_id;

            // The invoice knows the carrier when the order's shipping method
            // does not name one.
            if ((trim((string) $from_order['data']['carrier_title']) === '') && (trim((string) $invoice['carrier_title']) !== '')) {
                $from_order['data']['carrier_title'] = (string) $invoice['carrier_title'];
                $from_order['data']['carrier_vkn'] = (string) $invoice['carrier_vkn'];
            }
        }

        return $from_order;
    }

    $items = (array) db_items("SELECT product_id, description, quantity, unit_code
        FROM erp_invoice_items WHERE invoice_id = '" . $invoice_id . "' ORDER BY line_no ASC, id ASC");

    if (empty($items)) {
        return $fail(lang('This invoice has no lines.'));
    }

    $lines = array();
    foreach ($items as $item) {
        $lines[] = array(
            'product_id' => (int) $item['product_id'],
            'description' => (string) $item['description'],
            'quantity' => (float) $item['quantity'],
            'unit_code' => (string) $item['unit_code'],
        );
    }

    $account = erp_account((int) $invoice['account_id']);
    $shipment_date = (string) ($invoice['shipment_date'] ?? '');

    return array('success' => true, 'error' => '', 'data' => array(
        'account_id' => (int) $invoice['account_id'],
        'order_id' => 0,
        'invoice_id' => $invoice_id,
        'issue_date' => date('Y-m-d'),
        'ship_date' => (($shipment_date !== '') && ($shipment_date !== '0000-00-00')) ? $shipment_date : date('Y-m-d'),
        'ship_time' => '',
        'carrier_title' => (string) ($invoice['carrier_title'] ?? ''),
        'carrier_vkn' => (string) ($invoice['carrier_vkn'] ?? ''),
        'plate' => '',
        'driver_name' => '',
        'driver_tckn' => '',
        'ship_to_title' => is_array($account) ? (string) $account['title'] : '',
        'ship_to_address' => is_array($account) ? (string) $account['address'] : '',
        'ship_to_district' => is_array($account) ? (string) $account['district'] : '',
        'ship_to_city' => is_array($account) ? (string) $account['city'] : '',
        'ship_to_state' => is_array($account) ? (string) ($account['state'] ?? '') : '',
        'ship_to_country' => is_array($account) ? (string) $account['country_code'] : '',
        'notes' => '',
        'lines' => $lines,
    ));
}

/**
 * The delivery notes written against an invoice, cancelled ones left out.
 *
 * @param int $invoice_id
 * @return array  Rows: id, full_number, status
 */
function erp_waybills_for_invoice($invoice_id)
{
    return (array) db_items("SELECT id, full_number, status FROM erp_waybills
        WHERE invoice_id = '" . (int) $invoice_id . "' AND status <> 'cancelled' ORDER BY id ASC");
}

/**
 * What a delivery note for an order would say, before anything is written.
 *
 * The account is the order's; the goods are its items; the address is the
 * first recipient's; the carrier and the ship date are what the order screen
 * recorded when the goods left (today when nothing has left yet, which the
 * operator sees on the form and can correct).
 *
 * @param int $order_id
 * @param int $created_by  Who opens an account for the contact if it has none
 * @return array ['success' => bool, 'data' => array, 'error' => string]
 */
function erp_waybill_data_from_order($order_id, $created_by = 0)
{
    $order_id = (int) $order_id;

    $fail = function ($message) {
        return array('success' => false, 'data' => array(), 'error' => $message);
    };

    $order = db_item("SELECT * FROM orders WHERE id = '" . $order_id . "' LIMIT 1");
    if (!is_array($order)) {
        return $fail(lang('Order not found.'));
    }

    $items = (array) db_items("SELECT order_items.product_id, order_items.product_name, order_items.quantity,
            products.short_description
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = '" . $order_id . "' AND order_items.saved_for_later = 0
        ORDER BY order_items.id ASC");

    if (empty($items)) {
        return $fail(lang('This order has no items.'));
    }

    $account_id = (int) ($order['erp_account_id'] ?? 0);
    if ($account_id <= 0) {
        $account_id = erp_account_for_contact((int) $order['contact_id'], (int) $created_by, $order);
    }
    if ($account_id <= 0) {
        return $fail(((int) $order['contact_id'] > 0)
            ? lang('The contact this order belongs to no longer exists, so there is no account to bill.')
            : lang('This order is not linked to a contact, so there is no account to bill.'));
    }

    $lines = array();
    foreach ($items as $item) {
        $lines[] = array(
            'product_id' => (int) $item['product_id'],
            'description' => (string) ($item['short_description'] ?: $item['product_name']),
            'quantity' => max(1, (int) $item['quantity']),
            'unit_code' => 'C62',
        );
    }

    // The first recipient. An order with several recipients is one shipment
    // to one address in the eyes of this note; the others are typed by hand.
    $recipient = db_item("SELECT * FROM ship_tos WHERE order_id = '" . $order_id . "' ORDER BY id ASC LIMIT 1");
    $ship_to = array('title' => '', 'address' => '', 'district' => '', 'city' => '', 'state' => '', 'country' => '');

    if (is_array($recipient)) {
        $who = trim((string) ($recipient['company'] ?? ''));
        if ($who === '') {
            $who = trim(trim((string) ($recipient['first_name'] ?? '')) . ' ' . trim((string) ($recipient['last_name'] ?? '')));
        }
        if ($who === '') {
            $who = trim((string) ($recipient['ship_to_name'] ?? ''));
        }
        $address = trim(trim((string) ($recipient['address_1'] ?? '')) . ' ' . trim((string) ($recipient['address_2'] ?? '')));
        $zip = trim((string) ($recipient['zip_code'] ?? ''));

        // The checkout's city and state are read by the recipient's
        // country, the way the account card is filled from a contact.
        $country = trim((string) ($recipient['country'] ?? ''));
        $place = erp_address_from_checkout((string) ($recipient['city'] ?? ''), (string) ($recipient['state'] ?? ''), $country);
        $ship_to = array(
            'title' => $who,
            'address' => trim($address . (($zip !== '') ? ' ' . $zip : '')),
            'district' => $place['district'],
            'city' => $place['city'],
            'state' => $place['state'],
            'country' => $country,
        );
    }

    // A recipient with no address is not a place to deliver to: a counter
    // sale's recipient row says "myself" and nothing else. The account's card
    // stands in, name and address both.
    if (($ship_to['title'] === '') || ($ship_to['address'] === '')) {
        $account = erp_account($account_id);
        if (is_array($account)) {
            if (($ship_to['title'] === '') || ($ship_to['address'] === '')) {
                $ship_to['title'] = (string) $account['title'];
            }
            if ($ship_to['address'] === '') {
                $ship_to['address'] = (string) $account['address'];
                $ship_to['district'] = (string) $account['district'];
                $ship_to['city'] = (string) $account['city'];
                $ship_to['state'] = (string) ($account['state'] ?? '');
                $ship_to['country'] = (string) $account['country_code'];
            }
        }
    }

    $shipment = erp_order_shipment($order_id);
    $ship_date = ($shipment['shipment_date'] !== '0000-00-00') ? $shipment['shipment_date'] : date('Y-m-d');

    return array('success' => true, 'error' => '', 'data' => array(
        'account_id' => $account_id,
        'order_id' => $order_id,
        'invoice_id' => 0,
        'issue_date' => date('Y-m-d'),
        'ship_date' => $ship_date,
        'ship_time' => '',
        'carrier_title' => $shipment['carrier_title'],
        'carrier_vkn' => $shipment['carrier_vkn'],
        'plate' => '',
        'driver_name' => '',
        'driver_tckn' => '',
        'ship_to_title' => $ship_to['title'],
        'ship_to_address' => $ship_to['address'],
        'ship_to_district' => $ship_to['district'],
        'ship_to_city' => $ship_to['city'],
        'ship_to_state' => $ship_to['state'],
        'ship_to_country' => $ship_to['country'],
        'notes' => '',
        'lines' => $lines,
    ));
}

/**
 * Cancel a delivery note.
 *
 * The row stays and keeps its number; the series is not reopened. A note that
 * has been invoiced is not cancelled - the invoice is the document that says
 * what was sold, and it is the one to reverse.
 *
 * @param int $waybill_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_waybill_cancel($waybill_id)
{
    $waybill = erp_waybill($waybill_id);

    if (!is_array($waybill)) {
        return array('success' => false, 'error' => lang('The delivery note could not be found.'));
    }
    if ((string) $waybill['status'] === 'cancelled') {
        return array('success' => false, 'error' => lang('This delivery note is already cancelled.'));
    }
    if (erp_waybill_invoice_state($waybill) !== 'none') {
        return array('success' => false, 'error' => lang('This delivery note has been invoiced. Cancel or return the invoice instead.'));
    }

    if (erp_query("UPDATE erp_waybills SET status = 'cancelled', updated_at = '" . time() . "' WHERE id = '" . (int) $waybill['id'] . "'") === false) {
        return array('success' => false, 'error' => erp_db_error());
    }

    return array('success' => true, 'error' => '');
}

/**
 * Turn a delivery note into an invoice.
 *
 * A note written for an order is billed the way the order is billed: through
 * the order bridge, at the order's own figures, so the invoice and the order
 * come to the same money. If the order was invoiced already, the note is
 * simply tied to that invoice. A note typed by hand carries quantities and no
 * prices, so it becomes a draft invoice with the lines carried over and the
 * catalogue prices filled in where a product is known; the operator finishes
 * the prices and issues it from the draft editor.
 *
 * @param int $waybill_id
 * @param int $created_by
 * @return array ['success' => bool, 'invoice_id' => int, 'draft' => bool, 'error' => string]
 */
function erp_waybill_invoice($waybill_id, $created_by = 0)
{
    $fail = function ($message) {
        return array('success' => false, 'invoice_id' => 0, 'draft' => false, 'error' => $message);
    };

    $waybill = erp_waybill($waybill_id);

    if (!is_array($waybill)) {
        return $fail(lang('The delivery note could not be found.'));
    }
    if ((string) $waybill['status'] === 'cancelled') {
        return $fail(lang('A cancelled delivery note cannot be invoiced.'));
    }
    if (erp_waybill_invoice_state($waybill) !== 'none') {
        return $fail(lang('This delivery note has been invoiced already.'));
    }

    $waybill_id = (int) $waybill['id'];
    $order_id = (int) $waybill['order_id'];

    if ($order_id > 0) {
        $invoice_id = erp_invoice_for_order($order_id);

        if ($invoice_id <= 0) {
            $result = erp_invoice_from_order($order_id, array('created_by' => (int) $created_by));

            if (!$result['success']) {
                return $fail($result['error']);
            }

            $invoice_id = (int) $result['invoice_id'];
        }

        if (erp_query("UPDATE erp_waybills SET invoice_id = '" . $invoice_id . "', updated_at = '" . time() . "' WHERE id = '" . $waybill_id . "'") === false) {
            return $fail(erp_db_error());
        }

        return array('success' => true, 'invoice_id' => $invoice_id, 'draft' => false, 'error' => '');
    }

    $lines = array();

    foreach (erp_waybill_lines($waybill_id) as $line) {
        $product = ((int) $line['product_id'] > 0)
            ? db_item("SELECT price, tax_rate FROM products WHERE id = '" . (int) $line['product_id'] . "' LIMIT 1")
            : null;

        $lines[] = array(
            'product_id' => (int) $line['product_id'],
            'description' => (string) $line['description'],
            'quantity' => (float) $line['quantity'],
            'unit_code' => (string) $line['unit_code'],
            'unit_price' => is_array($product) ? (int) $product['price'] : 0,
            'tax_rate' => (is_array($product) && ($product['tax_rate'] !== null)) ? (float) $product['tax_rate'] : 0,
            'discount_rate' => 0,
        );
    }

    $result = erp_invoice_draft_save(array(
        'direction' => 'sales',
        'account_id' => (int) $waybill['account_id'],
        'currency' => erp_base_currency(),
        'issue_date' => date('Y-m-d'),
        'due_date' => '',
        'notes' => lang(array('string' => 'Delivery note {var:1}', 'vars' => $waybill['full_number'])),
        'lines' => $lines,
        'created_by' => (int) $created_by,
    ));

    if (!$result['success']) {
        return $fail($result['error']);
    }

    if (erp_query("UPDATE erp_waybills SET invoice_id = '" . (int) $result['invoice_id'] . "', updated_at = '" . time() . "' WHERE id = '" . $waybill_id . "'") === false) {
        return $fail(erp_db_error());
    }

    return array('success' => true, 'invoice_id' => (int) $result['invoice_id'], 'draft' => true, 'error' => '');
}

/**
 * Forget the invoice a delivery note pointed at, when that invoice ceases to
 * exist. Called by the draft delete; a cancelled invoice keeps the link and
 * the state function reads through it.
 *
 * @param int $invoice_id
 * @return void
 */
function erp_waybill_unlink_invoice($invoice_id)
{
    $invoice_id = (int) $invoice_id;

    if ($invoice_id > 0) {
        erp_query("UPDATE erp_waybills SET invoice_id = 0, updated_at = '" . time() . "' WHERE invoice_id = '" . $invoice_id . "'");
    }
}

/**
 * The captions the printed delivery note uses, in the site language.
 *
 * @return array
 */
function erp_waybill_document_labels()
{
    return array(
        'waybill_no' => lang('Delivery Note No'),
        'waybill_date' => lang('Date'),
        'ship_date' => lang('Shipment Date'),
        'ship_time' => lang('Shipment Time'),
        'order_no' => lang('Order No'),
        'order' => lang('Order'),
        'invoice_no' => lang('Invoice No'),
        // The seller's number is named by the store's country, the
        // customer's by theirs (set per note).
        'tax_id' => erp_tax_id_label(),
        'account_tax_id' => erp_tax_id_label(),
        'tax_office' => lang('Tax Office'),
        'bill_to' => lang('Customer'),
        'ship_to' => lang('Delivered to'),
        'description' => lang('Description'),
        'quantity' => lang('Quantity'),
        'unit' => lang('Unit'),
        'no_lines' => lang('No lines'),
        'carrier' => lang('Carrier'),
        'carrier_tax_id' => (erp_account_country('') === 'TR') ? lang('Carrier VKN') : lang('Carrier tax number'),
        'plate' => lang('Plate'),
        'driver' => lang('Driver'),
        'driver_id' => lang('Driver ID No'),
        'note' => lang('Note'),
        'received_by' => lang('Received by (name, date, signature)'),
        'delivered_by' => lang('Delivered by (name, signature)'),
        'generated_at' => lang('Generated'),
        'cancelled' => lang('CANCELLED'),
    );
}

/**
 * Everything the delivery note template prints, keyed the way the template
 * names it: seller, account, waybill, ship_to, lines, label.
 *
 * @param int $waybill_id
 * @return array|false
 */
function erp_waybill_document_data($waybill_id)
{
    $waybill = erp_waybill($waybill_id);

    if (!is_array($waybill)) {
        return false;
    }

    $constant = function ($name) {
        return defined($name) ? trim((string) constant($name)) : '';
    };

    $logo = erp_document_logo();

    $seller = array(
        'title' => $constant('ORGANIZATION_NAME'),
        'address_1' => $constant('ORGANIZATION_ADDRESS_1'),
        'address_2' => $constant('ORGANIZATION_ADDRESS_2'),
        'city' => $constant('ORGANIZATION_CITY'),
        'state' => $constant('ORGANIZATION_STATE'),
        'zip_code' => $constant('ORGANIZATION_ZIP_CODE'),
        'country' => $constant('ORGANIZATION_COUNTRY'),
        'vkn' => $constant('ERP_SELLER_VKN'),
        'tax_office' => $constant('ERP_SELLER_TAX_OFFICE'),
        'web_address' => $constant('ERP_WEB_ADDRESS'),
        'email' => $constant('ECOMMERCE_EMAIL_ADDRESS'),
        'logo_url' => $logo['url'],
        'logo_data_uri' => $logo['data_uri'],
    );
    $seller['locality'] = erp_seller_locality($seller);

    $account_row = erp_account((int) $waybill['account_id']);
    $account = array(
        'title' => is_array($account_row) ? (string) $account_row['title'] : (string) $waybill['account_title'],
        'tax_number' => is_array($account_row) ? (string) $account_row['tax_number'] : '',
        'tax_office' => is_array($account_row) ? (string) $account_row['tax_office'] : '',
        'address' => is_array($account_row) ? (string) $account_row['address'] : '',
        'district' => is_array($account_row) ? (string) $account_row['district'] : '',
        'city' => is_array($account_row) ? (string) $account_row['city'] : '',
        'state' => is_array($account_row) ? (string) ($account_row['state'] ?? '') : '',
        'country' => is_array($account_row) ? (string) $account_row['country_code'] : '',
        'postcode' => is_array($account_row) ? (string) $account_row['postcode'] : '',
    );
    $account['locality'] = erp_address_locality($account, $account['country']);

    $order_number = '';
    if ((int) $waybill['order_id'] > 0) {
        $order_number = ((string) $waybill['order_number'] !== '') ? (string) $waybill['order_number'] : ('#' . (int) $waybill['order_id']);
    }

    $ship_time = (string) $waybill['ship_time'];
    $ship_time = ($ship_time !== '' && $ship_time !== '00:00:00') ? substr($ship_time, 0, 5) : '';

    $invoice_state = erp_waybill_invoice_state($waybill);

    $document = array(
        'id' => (int) $waybill['id'],
        'full_number' => (string) $waybill['full_number'],
        'title' => lang('DELIVERY NOTE'),
        'issue_date' => erp_document_date($waybill['issue_date']),
        'ship_date' => erp_document_date($waybill['ship_date']),
        'ship_time' => $ship_time,
        'order_number' => $order_number,
        'invoice_number' => ($invoice_state !== 'none') ? (string) $waybill['invoice_number'] : '',
        'carrier_title' => (string) $waybill['carrier_title'],
        'carrier_vkn' => (string) $waybill['carrier_vkn'],
        'plate' => (string) $waybill['plate'],
        'driver_name' => (string) $waybill['driver_name'],
        'driver_tckn' => (string) $waybill['driver_tckn'],
        'has_transport' => (trim((string) $waybill['carrier_title'] . $waybill['plate'] . $waybill['driver_name']) !== ''),
        'notes' => (string) ($waybill['notes'] ?? ''),
        'is_cancelled' => ((string) $waybill['status'] === 'cancelled'),
    );

    $ship_to = array(
        'title' => (string) $waybill['ship_to_title'],
        'address' => (string) $waybill['ship_to_address'],
        'district' => (string) $waybill['ship_to_district'],
        'city' => (string) $waybill['ship_to_city'],
        'state' => (string) ($waybill['ship_to_state'] ?? ''),
        'country' => (string) $waybill['ship_to_country'],
    );
    $ship_to['locality'] = erp_address_locality($ship_to, $ship_to['country']);

    $lines = array();

    foreach (erp_waybill_lines((int) $waybill['id']) as $row) {
        $lines[] = array(
            'no' => (int) $row['line_no'],
            'description' => (string) $row['description'],
            'quantity' => erp_quantity_text($row['quantity']),
            'unit' => erp_waybill_unit_label($row['unit_code']),
        );
    }

    return array(
        'seller' => $seller,
        'account' => $account,
        'waybill' => $document,
        'ship_to' => $ship_to,
        'lines' => $lines,
        'label' => array_merge(erp_waybill_document_labels(), array('account_tax_id' => erp_tax_id_label((string) ($account['country'] ?? '')))),
        'language' => defined('SOFTWARE_LANGUAGE') ? (string) SOFTWARE_LANGUAGE : 'en',
        'paper' => erp_paper_size(),
        'generated_at' => (string) prepare_form_data_for_output(date('Y-m-d H:i:s'), 'date and time', false),
    );
}

/**
 * The built-in delivery note template, as shipped.
 *
 * @return string
 */
function erp_waybill_default_template()
{
    $path = PG_FUNCTIONS_DIR . '/includes/erp/templates/waybill_default.html';
    $contents = is_file($path) ? @file_get_contents($path) : false;

    return ($contents === false) ? '' : $contents;
}

/**
 * The template the notes are printed from: the one saved on the ERP settings
 * screen when there is one (4.60), otherwise the built-in file.
 *
 * @return string
 */
function erp_waybill_template()
{
    if (function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_waybill_template')) {
        $saved = db_value("SELECT erp_waybill_template FROM config LIMIT 1");

        if (is_string($saved) && (trim($saved) !== '')) {
            return $saved;
        }
    }

    return erp_waybill_default_template();
}

/**
 * The delivery note as a complete HTML document.
 *
 * @param int $waybill_id
 * @return string|false  false when the note does not exist
 */
function erp_waybill_html($waybill_id, $template = null)
{
    $data = erp_waybill_document_data($waybill_id);

    if ($data === false) {
        return false;
    }

    return erp_template_render(($template === null) ? erp_waybill_template() : (string) $template, $data);
}
