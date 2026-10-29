<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - purchase orders.
 *
 * What the store asks a supplier for: the lines of a purchase invoice, written
 * before the supplier's bill exists, with the day the goods are expected
 * instead of a due date. Like a quote it moves no money, no stock and no tax,
 * and lives in a table of its own (erp_purchase_orders, 10.58) so that no
 * report has to learn to leave it out. It is typed in the invoice editor on
 * the purchase side, takes a number of its own series when first saved, is
 * printed (includes/erp/templates/purchase_order.html) and e-mailed to the
 * supplier.
 *
 * Status: open (editable), sent (it went to the supplier, still editable),
 * partial and received (read back from the purchase invoices made from it),
 * cancelled. Stock and cost change only with the purchase invoice, as they
 * always have (includes/erp/stock.php).
 *
 * Every line carries a key of its own (source_line, up to 16 letters and
 * digits) that stays with it while the order is edited; the purchase
 * invoice lines made from the order name it, which is how the order knows
 * what has come.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * Whether the 10.58 table is there.
 *
 * @return bool
 */
function erp_purchase_orders_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_purchase_orders', 'line_data');
    }

    return $ready;
}

/**
 * The series purchase orders are numbered in: config.php may name one
 * (ERP_PURCHASE_ORDER_SERIES); otherwise the invoice series with a P after
 * it. S is the delivery notes' (erp_waybill_series()), and two documents
 * printed with the same number are the one thing a numbering must not do.
 *
 * @return string
 */
function erp_purchase_order_series()
{
    if (defined('ERP_PURCHASE_ORDER_SERIES') && (trim((string) ERP_PURCHASE_ORDER_SERIES) !== '')) {
        return trim((string) ERP_PURCHASE_ORDER_SERIES);
    }

    return (defined('ERP_DEFAULT_SERIES') ? trim((string) ERP_DEFAULT_SERIES) : 'PGF') . 'P';
}

/**
 * @return array  status => [label, badge colour]
 */
function erp_purchase_order_statuses()
{
    return array(
        'open' => array(lang('Open'), 'primary'),
        'sent' => array(lang('Sent to the supplier'), 'info'),
        'partial' => array(lang('Partly received'), 'warning'),
        'received' => array(lang('Received in full'), 'success'),
        'cancelled' => array(lang('Cancelled'), 'inverse'),
    );
}

/**
 * One purchase order with its account's live details.
 *
 * @param int $order_id
 * @return array|null
 */
function erp_purchase_order($order_id)
{
    if (!erp_purchase_orders_ready() || ((int) $order_id <= 0)) {
        return null;
    }

    $invoice_email = waf_table_has_column('erp_accounts', 'invoice_email') ? 'a.invoice_email' : "''";

    $order = db_item("SELECT o.*, a.title AS account_title, a.email AS account_email, " . $invoice_email . " AS invoice_email,
            a.kind AS account_kind
        FROM erp_purchase_orders o
        LEFT JOIN erp_accounts a ON a.id = o.account_id
        WHERE o.id = '" . (int) $order_id . "' LIMIT 1");

    return is_array($order) ? $order : null;
}

/**
 * Whether the order's lines can still be changed: while it is open or sent,
 * before anything has come in against it.
 *
 * @param array $order
 * @return bool
 */
function erp_purchase_order_editable($order)
{
    return in_array((string) $order['status'], array('open', 'sent'), true);
}

/**
 * The lines as stored, shaped like erp_invoice_items rows with their key in
 * source_line.
 *
 * @param array $order
 * @return array
 */
function erp_purchase_order_lines($order)
{
    $lines = json_decode((string) ($order['line_data'] ?? ''), true);

    return is_array($lines) ? $lines : array();
}

/**
 * The lines for the editor, with the product names the editor shows.
 *
 * @param array $order
 * @return array
 */
function erp_purchase_order_editor_rows($order)
{
    $lines = erp_purchase_order_lines($order);
    $ids = array();

    foreach ($lines as $line) {
        if ((int) ($line['product_id'] ?? 0) > 0) {
            $ids[] = (int) $line['product_id'];
        }
    }

    $names = array();
    if (!empty($ids)) {
        foreach ((array) db_items("SELECT id, COALESCE(NULLIF(short_description, ''), name) AS product_name FROM products WHERE id IN (" . implode(', ', array_unique($ids)) . ")") as $row) {
            $names[(int) $row['id']] = (string) $row['product_name'];
        }
    }

    foreach ($lines as $index => $line) {
        $lines[$index]['product_name'] = $names[(int) ($line['product_id'] ?? 0)] ?? '';
    }

    return $lines;
}

/**
 * Give every line a key: the one it came with when that is usable and not
 * already taken by an earlier line, otherwise a new one. A key the order
 * already used is never handed to another line, so an invoice line that
 * names it cannot be read as a different line.
 *
 * @param array    $lines      Each may carry source_line
 * @param callable $new_key    Returns a fresh key; random by default
 * @return array  The lines, each with source_line set
 */
function erp_purchase_order_line_keys($lines, $new_key = null)
{
    if (!is_callable($new_key)) {
        $new_key = function () {
            try {
                return bin2hex(random_bytes(4));
            } catch (Exception $e) {
                return substr(md5(uniqid('', true) . mt_rand()), 0, 8);
            }
        };
    }

    $taken = array();
    $keyed = array();

    foreach (array_values((array) $lines) as $line) {
        $key = erp_source_line_key($line['source_line'] ?? '');

        while (($key === '') || isset($taken[strtolower($key)])) {
            $key = erp_source_line_key(call_user_func($new_key));
        }

        $taken[strtolower($key)] = true;
        $line['source_line'] = $key;
        $keyed[] = $line;
    }

    return $keyed;
}

/**
 * Save a new purchase order, which takes its number, or rewrite one that can
 * still be changed (erp_purchase_order_editable()).
 *
 * @param array $data      What erp_invoice_form_read() returns; due_date is
 *                         the day the goods are expected ('' for none)
 * @param int   $order_id  0 for a new order
 * @param int   $user_id
 * @return array ['success' => bool, 'order_id' => int, 'full_number' => string, 'error' => string, 'field' => string]
 */
function erp_purchase_order_save($data, $order_id, $user_id)
{
    $fail = function ($message, $field = '_error') {
        return array('success' => false, 'order_id' => 0, 'full_number' => '', 'error' => $message, 'field' => $field);
    };

    if (!erp_purchase_orders_ready()) {
        return $fail(lang('Purchase orders come with the software update; run the update to use them.'));
    }

    $data['direction'] = 'purchase';
    $built_header = erp_manual_header_build($data);

    if ($built_header['error'] !== '') {
        return $fail($built_header['error'], $built_header['field']);
    }

    $header = $built_header['header'];
    $built = erp_manual_lines_build($data['lines'] ?? array());

    if ($built['error'] !== '') {
        return $fail($built['error'], $built['field']);
    }

    if (empty($built['lines'])) {
        return $fail(lang('Enter at least one line.'), 'lines[0][description]');
    }

    // erp_manual_header_build() fills an empty date with the account's
    // payment term; an order has no due date, only the day the goods are
    // expected, which may be left open.
    $expected_date = (string) ($data['due_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected_date)) {
        $expected_date = '0000-00-00';
    }

    if (($expected_date !== '0000-00-00') && ($expected_date < $header['issue_date'])) {
        return $fail(lang('The goods cannot be expected before the order\'s date.'), 'due_date');
    }

    $totals = $built['totals'];
    $base = erp_base_currency();
    $grand_total_base = ($header['currency'] === $base)
        ? $totals['grand_total']
        : (($header['exchange_rate'] > 0) ? erp_to_base($totals['grand_total'], $header['exchange_rate']) : 0);

    // The keys the lines came with (the form's hidden source_line) are kept;
    // a new line is given one.
    $lines = array();
    foreach (array_values($built['lines']) as $index => $line) {
        $line['line_no'] = $index + 1;
        $line['source_line'] = (string) ($line['source_line'] ?? '');
        $lines[] = $line;
    }

    $lines = erp_purchase_order_line_keys($lines);

    $set = "
            account_id = '" . (int) $header['account_id'] . "',
            issue_date = '" . escape($header['issue_date']) . "',
            expected_date = '" . escape($expected_date) . "',
            currency = '" . escape($header['currency']) . "',
            exchange_rate = '" . escape(number_format((float) $header['exchange_rate'], 6, '.', '')) . "',
            subtotal = '" . (int) $totals['subtotal'] . "',
            discount_total = '" . (int) $totals['discount_total'] . "',
            tax_total = '" . (int) $totals['tax_total'] . "',
            tax2_total = '" . (int) $totals['tax2_total'] . "',
            withholding_total = '" . (int) $totals['withholding_total'] . "',
            grand_total = '" . (int) $totals['grand_total'] . "',
            grand_total_base = '" . (int) $grand_total_base . "',
            line_data = '" . escape(json_encode($lines, JSON_UNESCAPED_UNICODE)) . "',
            notes = '" . escape($header['notes']) . "',
            updated_at = UNIX_TIMESTAMP()";

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    if ((int) $order_id > 0) {
        $existing = db_item("SELECT id, status, full_number FROM erp_purchase_orders WHERE id = '" . (int) $order_id . "' LIMIT 1 FOR UPDATE");

        if (!is_array($existing)) {
            erp_tx_rollback();
            return $fail(lang('The purchase order could not be found.'));
        }

        if (!erp_purchase_order_editable($existing)) {
            erp_tx_rollback();
            return $fail(lang('Only an open or sent purchase order can be changed.'));
        }

        if (erp_query("UPDATE erp_purchase_orders SET " . $set . " WHERE id = '" . (int) $order_id . "'") === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }

        if (!erp_tx_commit()) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }

        return array('success' => true, 'order_id' => (int) $order_id, 'full_number' => (string) $existing['full_number'], 'error' => '', 'field' => '');
    }

    $series = erp_purchase_order_series();
    $numbered = erp_next_number($series, 'purchase_order', (int) substr($header['issue_date'], 0, 4));

    if (empty($numbered['success'])) {
        erp_tx_rollback();
        return $fail((string) $numbered['error']);
    }

    $ok = erp_query("INSERT INTO erp_purchase_orders SET
            series = '" . escape($series) . "',
            number = '" . (int) $numbered['number'] . "',
            issue_year = '" . (int) $numbered['year'] . "',
            full_number = '" . escape((string) $numbered['full']) . "',
            status = 'open',
            created_by = '" . (int) $user_id . "',
            created_at = UNIX_TIMESTAMP()," . $set);

    if ($ok === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    $new_id = (int) mysqli_insert_id(db::$con);

    // Announced inside the writer's transaction, the way quotes are: the
    // queue row and the audit line roll back with the order they announce.
    erp_event_purchase_order($new_id, 'erp.purchase_order.created');

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'order_id' => $new_id, 'full_number' => (string) $numbered['full'], 'error' => '', 'field' => '');
}

/**
 * Why the order cannot be moved to a status, or '' when it can: sent (it went
 * to the supplier), cancelled, or open again. The screen's buttons and the
 * API ask the same question before they move it.
 *
 * @param array  $order  From erp_purchase_order()
 * @param string $to     sent | cancelled | open
 * @return string
 */
function erp_purchase_order_status_refusal($order, $to)
{
    $status = (string) $order['status'];

    switch ((string) $to) {
        case 'sent':
            return ($status === 'cancelled') ? lang('A cancelled purchase order is not sent. Open it again first.') : '';

        case 'cancelled':
            if ($status === 'received') {
                return lang('Everything on the purchase order has come in; there is nothing left to cancel.');
            }

            return ($status === 'cancelled') ? lang('The purchase order is already cancelled.') : '';

        case 'open':
            return ($status !== 'cancelled') ? lang('Only a cancelled purchase order is opened again.') : '';
    }

    return lang('The purchase order cannot be moved to that status.');
}

/**
 * Mark the order as gone to the supplier, by hand (it went by phone, or on
 * paper) or after the e-mail. The first time is kept: sending it again does
 * not move sent_at.
 *
 * @param int $order_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_purchase_order_mark_sent($order_id)
{
    $order = erp_purchase_order($order_id);

    if ($order === null) {
        return array('success' => false, 'error' => lang('The purchase order could not be found.'));
    }

    if (($refusal = erp_purchase_order_status_refusal($order, 'sent')) !== '') {
        return array('success' => false, 'error' => $refusal);
    }

    $first = ((int) $order['sent_at'] === 0);

    db("UPDATE erp_purchase_orders SET
            status = IF(status = 'open', 'sent', status),
            sent_at = IF(sent_at = 0, UNIX_TIMESTAMP(), sent_at),
            updated_at = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $order['id'] . "'");

    if ($first) {
        erp_event_purchase_order((int) $order['id'], 'erp.purchase_order.sent');
    }

    return array('success' => true, 'error' => '');
}

/**
 * Cancel the order, or open a cancelled one again.
 *
 * A cancelled order keeps its number and its lines; whatever already came in
 * against it stays where it is, on its purchase invoices. Opened again it
 * goes back to the status its history gives it.
 *
 * @param int    $order_id
 * @param string $to  cancelled | open
 * @return array ['success' => bool, 'error' => string]
 */
function erp_purchase_order_set_status($order_id, $to)
{
    $order = erp_purchase_order($order_id);

    if ($order === null) {
        return array('success' => false, 'error' => lang('The purchase order could not be found.'));
    }

    if (($refusal = erp_purchase_order_status_refusal($order, $to)) !== '') {
        return array('success' => false, 'error' => $refusal);
    }

    if ($to === 'cancelled') {
        db("UPDATE erp_purchase_orders SET status = 'cancelled', updated_at = UNIX_TIMESTAMP() WHERE id = '" . (int) $order['id'] . "'");
        erp_event_purchase_order((int) $order['id'], 'erp.purchase_order.cancelled');

        return array('success' => true, 'error' => '');
    }

    if ($to === 'open') {
        db("UPDATE erp_purchase_orders SET status = IF(sent_at > 0, 'sent', 'open'), updated_at = UNIX_TIMESTAMP() WHERE id = '" . (int) $order['id'] . "'");

        if (function_exists('erp_purchase_order_refresh')) {
            erp_purchase_order_refresh((int) $order['id']);
        }

        erp_event_purchase_order((int) $order['id'], 'erp.purchase_order.reopened');

        return array('success' => true, 'error' => '');
    }

    return array('success' => false, 'error' => lang('The purchase order cannot be moved to that status.'));
}

/**
 * The order as the purchase order template reads a document.
 *
 * @param array $order  From erp_purchase_order()
 * @return array  erp_invoice_document_data()'s $source
 */
function erp_purchase_order_document_source($order)
{
    $account = db_item("SELECT * FROM erp_accounts WHERE id = '" . (int) $order['account_id'] . "' LIMIT 1");
    $account = is_array($account) ? $account : array();

    $row = array(
        'id' => (int) $order['id'],
        'full_number' => (string) $order['full_number'],
        'issue_date' => (string) $order['issue_date'],
        'due_date' => (string) $order['expected_date'],
        'doc_type' => 'purchase_order',
        'status' => 'issued',
        'direction' => 'purchase',
        'order_id' => 0,
        'order_number' => '',
        'parent_invoice_id' => 0,
        'currency' => (string) $order['currency'],
        'exchange_rate' => (float) $order['exchange_rate'],
        'exchange_rate_date' => (string) $order['issue_date'],
        'grand_total_base' => (int) $order['grand_total_base'],
        'is_internet_sale' => 0,
        'payment_method' => '',
        'payment_date' => '0000-00-00',
        'shipment_date' => '0000-00-00',
        'carrier_title' => '',
        'carrier_vkn' => '',
        'web_address' => '',
        'notes' => (string) $order['notes'],
        'supplier_invoice_no' => '',
        'supplier_invoice_date' => '0000-00-00',
        'subtotal' => (int) $order['subtotal'],
        'discount_total' => (int) $order['discount_total'],
        'shipping_total' => 0,
        'surcharge_total' => 0,
        'gift_card_total' => 0,
        'tax_total' => (int) $order['tax_total'],
        'tax2_total' => (int) $order['tax2_total'],
        'withholding_total' => (int) $order['withholding_total'],
        'grand_total' => (int) $order['grand_total'],
        // No copy of the account is kept on an order: it is printed with the
        // supplier as it reads today.
        'account_title' => '',
        'live_title' => (string) ($account['title'] ?? ''),
        'live_is_person' => (int) ($account['is_person'] ?? 0),
        'live_tax_number' => (string) ($account['tax_number'] ?? ''),
        'live_tax_office' => (string) ($account['tax_office'] ?? ''),
        'live_address' => (string) ($account['address'] ?? ''),
        'live_district' => (string) ($account['district'] ?? ''),
        'live_city' => (string) ($account['city'] ?? ''),
        'live_state' => (string) ($account['state'] ?? ''),
        'live_country_code' => (string) ($account['country_code'] ?? ''),
        'live_postcode' => (string) ($account['postcode'] ?? ''),
        'live_email' => (string) ($account['email'] ?? ''),
        'live_phone' => (string) ($account['phone'] ?? ''),
    );

    return array(
        'invoice' => $row,
        'items' => erp_purchase_order_lines($order),
        'title' => lang('PURCHASE ORDER'),
        'label' => array(
            'invoice_no' => lang('Order No'),
            'invoice_date' => lang('Order Date'),
            'due_date' => lang('Expected delivery'),
            'bill_to' => lang('SUPPLIER'),
        ),
    );
}

/**
 * The built-in purchase order template, as shipped.
 *
 * @return string
 */
function erp_purchase_order_template()
{
    $path = PG_FUNCTIONS_DIR . '/includes/erp/templates/purchase_order.html';
    $contents = is_file($path) ? @file_get_contents($path) : false;

    return ($contents === false) ? '' : $contents;
}

/**
 * The order as a complete HTML document.
 *
 * @param int $order_id
 * @return string|false
 */
function erp_purchase_order_html($order_id)
{
    $order = erp_purchase_order($order_id);

    if ($order === null) {
        return false;
    }

    $data = erp_invoice_document_data(0, erp_purchase_order_document_source($order));

    return ($data === false) ? false : erp_template_render(erp_purchase_order_template(), $data);
}

/**
 * The order's file name, without the extension.
 *
 * @param array $order
 * @return string
 */
function erp_purchase_order_file_name($order)
{
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $order['full_number']);

    return (trim((string) $name, '_') !== '') ? $name : ('purchase_order_' . (int) $order['id']);
}

/**
 * What a new e-mail of the order starts with.
 *
 * @param array $order
 * @return array ['to', 'subject', 'message']
 */
function erp_purchase_order_mail_defaults($order)
{
    $to = trim((string) ($order['invoice_email'] ?? ''));
    $expected = ((string) $order['expected_date'] > '0000-00-00')
        ? lang(array('string' => 'We would like to receive it by {var:1}.', 'vars' => prepare_form_data_for_output((string) $order['expected_date'], 'date', false)))
        : '';

    return array(
        'to' => ($to !== '') ? $to : trim((string) ($order['account_email'] ?? '')),
        'subject' => ORGANIZATION_NAME . ' - ' . lang('Purchase order') . ' ' . (string) $order['full_number'],
        'message' => trim(lang('Please find our purchase order attached and confirm it by replying to this e-mail.') . ' ' . $expected),
    );
}

/**
 * E-mail the order, with its PDF, record it with the order and mark the
 * order sent.
 *
 * @param int    $order_id
 * @param string $to
 * @param string $subject
 * @param string $message
 * @param int    $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_purchase_order_mail_send($order_id, $to, $subject, $message, $user_id)
{
    $mail_id = 0;
    $fail = function ($message) use (&$mail_id) {
        if ($mail_id > 0) {
            erp_mail_settle($mail_id, false, '', $message);
        }
        return array('success' => false, 'error' => $message);
    };

    $order = erp_purchase_order($order_id);

    if ($order === null) {
        return $fail(lang('The purchase order could not be found.'));
    }

    if (($refusal = erp_purchase_order_status_refusal($order, 'sent')) !== '') {
        return $fail($refusal);
    }

    if (!function_exists('erp_mail_ready') || !erp_mail_ready()) {
        return $fail(lang('Sending documents by e-mail comes with the software update; run the update to use it.'));
    }

    $addresses = erp_mail_addresses($to);

    if ($addresses['error'] !== '') {
        return $fail($addresses['error']);
    }

    $defaults = erp_purchase_order_mail_defaults($order);
    $subject = (trim((string) $subject) !== '') ? mb_substr(trim((string) $subject), 0, 255) : $defaults['subject'];
    $message = (trim((string) $message) !== '') ? mb_substr(trim((string) $message), 0, 5000) : $defaults['message'];
    $sender = function_exists('erp_reconciliation_sender') ? erp_reconciliation_sender() : '';

    if ($sender === '') {
        return $fail(lang('The store has no e-mail address to send from. Set one under Settings.'));
    }

    $mail_id = erp_mail_record('purchase_order', (int) $order['id'], 'manual', implode(', ', $addresses['addresses']), $subject, $message, $user_id);

    $html = erp_purchase_order_html((int) $order['id']);
    $pdf = ($html !== false) ? erp_invoice_pdf($html) : false;

    if ($pdf === false) {
        return $fail(lang('The PDF library is not installed.'));
    }

    $file = erp_purchase_order_file_name($order) . '.pdf';
    $sent = email(array(
        'to' => $addresses['addresses'],
        'from_name' => ORGANIZATION_NAME,
        'from_email_address' => $sender,
        'reply_to' => $sender,
        'subject' => $subject,
        'format' => 'html',
        'body' => erp_purchase_order_mail_html($order, $message, $sender),
        'type' => 'system',
        'attachments' => array(
            array('name' => $file, 'content' => $pdf, 'type' => 'application/pdf'),
        ),
    ));

    if (!$sent) {
        $error = pg_mail_failure_sentence(lang('The e-mail could not be sent.'));
        erp_mail_settle($mail_id, false, $file, $error);
        return array('success' => false, 'error' => $error);
    }

    erp_mail_settle($mail_id, true, $file, '');
    erp_purchase_order_mark_sent((int) $order['id']);

    log_activity(lang(array(
        'string' => 'erp purchase order {var:1} was e-mailed to {var:2}',
        'vars' => array((string) $order['full_number'], implode(', ', $addresses['addresses'])),
    )), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('success' => true, 'error' => '');
}

/**
 * The e-mail itself: the covering text and the order's figures.
 *
 * @param array  $order
 * @param string $message
 * @param string $sender
 * @return string
 */
function erp_purchase_order_mail_html($order, $message, $sender)
{
    $paragraph = 'font-size: 14px; margin: 0 0 12px 0;';
    $cell = 'font-size: 14px; padding: 4px 16px 4px 0; vertical-align: top;';
    $title = trim((string) ($order['account_title'] ?? ''));

    $rows = array(
        array(lang('Purchase order'), (string) $order['full_number']),
        array(lang('Date'), prepare_form_data_for_output((string) $order['issue_date'], 'date', false)),
    );

    if ((string) $order['expected_date'] > '0000-00-00') {
        $rows[] = array(lang('Expected delivery'), prepare_form_data_for_output((string) $order['expected_date'], 'date', false));
    }

    $rows[] = array(lang('Total'), erp_money_out_currency((int) $order['grand_total'], (string) $order['currency']));

    $output_rows = '';
    foreach ($rows as $row) {
        $output_rows .= '<tr><td style="' . $cell . ' color: #666666;">' . h($row[0]) . '</td><td style="' . $cell . ' font-weight: bold;">' . h($row[1]) . '</td></tr>';
    }

    return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>' . h(lang('Purchase order')) . '</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #222222; background: #ffffff; margin: 0; padding: 20px;">
    <div style="max-width: 640px; margin: 0 auto;">
        <h2 style="font-size: 18px; margin: 0 0 16px 0;">' . h(ORGANIZATION_NAME) . ' &middot; ' . h(lang('Purchase order')) . ' ' . h((string) $order['full_number']) . '</h2>
        ' . (($title !== '') ? '<p style="' . $paragraph . '">' . h(lang(array('string' => 'Dear {var:1},', 'vars' => $title))) . '</p>' : '') . '
        <p style="' . $paragraph . '">' . nl2br(h(trim((string) $message))) . '</p>
        <table style="border-collapse: collapse; margin: 8px 0 16px 0;">' . $output_rows . '</table>
        <p style="' . $paragraph . '">' . h(lang('If you have a question about this order, simply reply to this e-mail.')) . '</p>
        <p style="' . $paragraph . '">' . h(ORGANIZATION_NAME) . (($sender !== '') ? '<br>' . h($sender) : '') . '</p>
    </div>
</body>
</html>';
}

/**
 * The purchase orders for the list, newest first.
 *
 * @param array $filters  status (a key of erp_purchase_order_statuses(), 'waiting' for
 *                        open, sent and partial, or ''), search, account_id, limit
 * @return array
 */
function erp_purchase_order_rows($filters = array())
{
    if (!erp_purchase_orders_ready()) {
        return array();
    }

    $where = array('1 = 1');
    $status = (string) ($filters['status'] ?? '');

    if ($status === 'waiting') {
        $where[] = "o.status IN ('open', 'sent', 'partial')";
    } elseif (array_key_exists($status, erp_purchase_order_statuses())) {
        $where[] = "o.status = '" . escape($status) . "'";
    }

    if ((int) ($filters['account_id'] ?? 0) > 0) {
        $where[] = "o.account_id = '" . (int) $filters['account_id'] . "'";
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $like = "'%" . escape(addcslashes($search, '%_\\')) . "%'";
        $where[] = "(o.full_number LIKE " . $like . " OR a.title LIKE " . $like . ")";
    }

    return (array) db_items("SELECT o.id, o.full_number, o.account_id, o.issue_date, o.expected_date, o.currency, o.grand_total,
            o.status, o.sent_at, a.title AS account_title
        FROM erp_purchase_orders o
        LEFT JOIN erp_accounts a ON a.id = o.account_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY o.id DESC
        LIMIT " . max(1, min(1000, (int) ($filters['limit'] ?? 300))));
}

/**
 * How many orders are still waiting for goods: open, sent or partly
 * received.
 *
 * @param int $account_id  Only this supplier's orders; 0 for all
 * @return int
 */
function erp_purchase_order_waiting_count($account_id = 0)
{
    if (!erp_purchase_orders_ready()) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM erp_purchase_orders WHERE status IN ('open', 'sent', 'partial')"
        . (((int) $account_id > 0) ? " AND account_id = '" . (int) $account_id . "'" : ''));
}

/**
 * How much the store asks a supplier for, for a product at or below its
 * minimum: back up to the minimum, and at least one.
 *
 * @param int|float $minimum
 * @param int|float $current  The count on hand; may be below zero
 * @return int
 */
function erp_purchase_order_suggested_quantity($minimum, $current)
{
    return max(1, (int) ceil((float) $minimum - (float) $current));
}

/**
 * Editor rows for a new order from products (the minimum stock screen): the
 * suggested quantity, the last purchase price and the product's own tax
 * rate, or the store's default. The operator picks the supplier and checks
 * the figures before saving.
 *
 * @param array $product_ids
 * @return array  Rows shaped like erp_purchase_order_editor_rows()
 */
function erp_purchase_order_rows_for_products($product_ids)
{
    $ids = array();
    foreach ((array) $product_ids as $id) {
        // Each entry is one number: an array inside the list
        // (?products[0][]=x) cast to 1 and put product 1 on the order.
        if (is_scalar($id) && ((int) $id > 0)) {
            $ids[(int) $id] = (int) $id;
        }
    }

    if (empty($ids)) {
        return array();
    }

    $ids = array_slice($ids, 0, ERP_MANUAL_MAX_LINES);
    $minimums = (function_exists('erp_stock_minimums_ready') && erp_stock_minimums_ready())
        ? "COALESCE((SELECT m.min_quantity FROM erp_stock_minimums m WHERE m.product_id = p.id), 0)"
        : "0";

    $found = array();
    foreach ((array) db_items("SELECT p.*, " . $minimums . " AS min_quantity FROM products p WHERE p.id IN (" . implode(', ', $ids) . ")") as $row) {
        $found[(int) $row['id']] = $row;
    }

    $rows = array();
    foreach ($ids as $id) {
        if (!isset($found[$id])) {
            continue;
        }

        $product = erp_product_for_line($found[$id]);
        $current = ((int) ($found[$id]['inventory'] ?? 0) === 1) ? (int) $found[$id]['inventory_quantity'] : 0;

        $rows[] = array(
            'product_id' => $id,
            'product_name' => (string) $product['name'],
            'description' => (string) $product['name'],
            'quantity' => erp_purchase_order_suggested_quantity((int) $found[$id]['min_quantity'], $current),
            'unit_code' => 'C62',
            'unit_price' => is_array($product['cost']) ? (int) $product['cost']['last'] : 0,
            'tax_rate' => (float) $product['tax_rate'],
            'source_line' => '',
        );
    }

    return $rows;
}

/**
 * The order's lines against what has come: per line what was ordered, what
 * the issued purchase invoices brought, what is on drafts made from the
 * order, and what a new draft would ask for.
 *
 * @param array $lines     erp_purchase_order_lines()
 * @param array $received  key => quantity, from erp_purchase_order_quantities()
 * @param array $drafted   key => quantity, the same
 * @return array  One row per line, in order: source_line, line_no,
 *                description, product_id, ordered, received, drafted,
 *                remaining (not received and not on a draft), open (not
 *                received)
 */
function erp_purchase_order_progress($lines, $received = array(), $drafted = array())
{
    $rows = array();

    foreach (array_values((array) $lines) as $index => $line) {
        $key = strtolower(erp_source_line_key($line['source_line'] ?? ''));
        $ordered = round((float) ($line['quantity'] ?? 0), 4);
        $in = ($key !== '') ? round((float) ($received[$key] ?? 0), 4) : 0.0;
        $on_drafts = ($key !== '') ? round((float) ($drafted[$key] ?? 0), 4) : 0.0;

        $rows[] = array(
            'source_line' => $key,
            'line_no' => (int) ($line['line_no'] ?? ($index + 1)),
            'description' => (string) ($line['description'] ?? ''),
            'product_id' => (int) ($line['product_id'] ?? 0),
            'ordered' => $ordered,
            'received' => $in,
            'drafted' => $on_drafts,
            'remaining' => max(0.0, round($ordered - $in - $on_drafts, 4)),
            'open' => max(0.0, round($ordered - $in, 4)),
        );
    }

    return $rows;
}

/**
 * Where the goods stand: 'none' before anything has come, 'partial' once
 * something has, 'received' when every line has come in full (more than was
 * ordered counts as in full).
 *
 * @param array $progress  erp_purchase_order_progress()
 * @return string  none | partial | received
 */
function erp_purchase_order_receipt_state($progress)
{
    $counted = 0;
    $any = false;
    $all = true;

    foreach ((array) $progress as $row) {
        if ((float) $row['ordered'] <= 0) {
            continue;
        }

        $counted++;

        if ((float) $row['received'] > 0) {
            $any = true;
        }
        if ((float) $row['received'] < (float) $row['ordered']) {
            $all = false;
        }
    }

    if ($counted === 0) {
        return 'none';
    }

    return $all ? 'received' : ($any ? 'partial' : 'none');
}

/**
 * The quantities the purchase invoices made from the order carry, per order
 * line. Received: issued purchase invoices (paid or not); a cancelled one
 * counts for nothing and a return is not looked at. On drafts: the drafts
 * made from the order, not yet issued.
 *
 * @param int $order_id
 * @return array ['received' => key => quantity, 'drafted' => key => quantity]
 */
function erp_purchase_order_quantities($order_id)
{
    $quantities = array('received' => array(), 'drafted' => array());

    if (!erp_purchase_orders_ready() || !erp_invoice_lines_have_source() || ((int) $order_id <= 0)) {
        return $quantities;
    }

    $rows = (array) db_items("SELECT it.source_line, IF(i.status = 'draft', 1, 0) AS is_draft, SUM(it.quantity) AS quantity
        FROM erp_invoices i
        INNER JOIN erp_invoice_items it ON it.invoice_id = i.id
        WHERE i.purchase_order_id = '" . (int) $order_id . "'
            AND i.direction = 'purchase'
            AND i.doc_type = 'invoice'
            AND i.status <> 'cancelled'
            AND it.source_line <> ''
        GROUP BY it.source_line, is_draft");

    foreach ($rows as $row) {
        $key = strtolower((string) $row['source_line']);
        $bucket = ((int) $row['is_draft'] === 1) ? 'drafted' : 'received';
        $quantities[$bucket][$key] = round(($quantities[$bucket][$key] ?? 0) + (float) $row['quantity'], 4);
    }

    return $quantities;
}

/**
 * The order's lines with what has come against each of them.
 *
 * @param array $order  From erp_purchase_order()
 * @return array  erp_purchase_order_progress()
 */
function erp_purchase_order_order_progress($order)
{
    $quantities = erp_purchase_order_quantities((int) $order['id']);

    return erp_purchase_order_progress(erp_purchase_order_lines($order), $quantities['received'], $quantities['drafted']);
}

/**
 * The purchase invoices and drafts made from the order, newest first.
 *
 * @param int $order_id
 * @return array
 */
function erp_purchase_order_invoices($order_id)
{
    if (!erp_purchase_orders_ready() || ((int) $order_id <= 0)) {
        return array();
    }

    return (array) db_items("SELECT id, full_number, status, issue_date, supplier_invoice_no, currency, grand_total
        FROM erp_invoices
        WHERE purchase_order_id = '" . (int) $order_id . "' AND direction = 'purchase' AND doc_type = 'invoice'
        ORDER BY id DESC
        LIMIT 200");
}

/**
 * Read the order's status again from its purchase invoices: received in
 * full, partly, or nothing yet (open, or sent once it went). A cancelled
 * order stays cancelled. Announces erp.purchase_order.received the moment
 * the last of the goods comes in.
 *
 * @param int $order_id
 * @return string  The status now, '' when there is no such order
 */
function erp_purchase_order_refresh($order_id)
{
    $order = erp_purchase_order($order_id);

    if ($order === null) {
        return '';
    }

    $status = (string) $order['status'];

    if ($status === 'cancelled') {
        return $status;
    }

    $state = erp_purchase_order_receipt_state(erp_purchase_order_order_progress($order));
    $to = ($state === 'none') ? (((int) $order['sent_at'] > 0) ? 'sent' : 'open') : $state;

    // The quantities came or went even when the status stays: a client that
    // follows changes by updated_at reads the order again.
    if ($to === $status) {
        db("UPDATE erp_purchase_orders SET updated_at = UNIX_TIMESTAMP() WHERE id = '" . (int) $order['id'] . "'");
        return $status;
    }

    // Guarded on the status read above: of two requests that both see the
    // last goods come in, one moves the order and announces it.
    db("UPDATE erp_purchase_orders SET status = '" . escape($to) . "', updated_at = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $order['id'] . "' AND status = '" . escape($status) . "'");

    if (($to === 'received') && (mysqli_affected_rows(db::$con) > 0)) {
        erp_event_purchase_order((int) $order['id'], 'erp.purchase_order.received');
    }

    return $to;
}

/**
 * After a purchase invoice was issued or cancelled (erp_event_invoice()):
 * the order it was made from, if any, reads its status again.
 *
 * @param int $invoice_id
 * @return void
 */
function erp_purchase_order_refresh_for_invoice($invoice_id)
{
    if (!erp_purchase_orders_ready()) {
        return;
    }

    $order_id = (int) db_value("SELECT purchase_order_id FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");

    if ($order_id > 0) {
        erp_purchase_order_refresh($order_id);
    }
}

/**
 * Why no purchase invoice draft can be made from the order now, or '' when
 * one can: a cancelled order, one that has come in full, or one whose every
 * line is already on an invoice or a draft.
 *
 * @param array $order     From erp_purchase_order()
 * @param array $progress  erp_purchase_order_order_progress()
 * @return string
 */
function erp_purchase_order_invoice_refusal($order, $progress)
{
    if ((string) $order['status'] === 'cancelled') {
        return lang('A cancelled purchase order is not invoiced. Open it again first.');
    }

    if ((string) $order['status'] === 'received') {
        return lang('Everything on the purchase order has come in.');
    }

    foreach ((array) $progress as $row) {
        if ((float) $row['remaining'] > 0) {
            return '';
        }
    }

    return lang('Every line of the purchase order is already on a purchase invoice or a draft.');
}

/**
 * Make a purchase invoice draft from the order, for what has not come yet
 * and is not already on a draft: the order's prices, today's date, the
 * account's payment term. Repeatable, one draft per delivery. Stock and cost
 * move when the draft is issued, as with any purchase invoice.
 *
 * @param int $order_id
 * @param int $user_id
 * @return array ['success' => bool, 'invoice_id' => int, 'error' => string]
 */
function erp_purchase_order_to_invoice($order_id, $user_id)
{
    $fail = function ($message) {
        return array('success' => false, 'invoice_id' => 0, 'error' => $message);
    };

    $order = erp_purchase_order($order_id);

    if ($order === null) {
        return $fail(lang('The purchase order could not be found.'));
    }

    $progress = erp_purchase_order_order_progress($order);

    if (($refusal = erp_purchase_order_invoice_refusal($order, $progress)) !== '') {
        return $fail($refusal);
    }

    $lines = array();

    foreach (array_values(erp_purchase_order_lines($order)) as $index => $line) {
        $remaining = (float) ($progress[$index]['remaining'] ?? 0);

        if ($remaining <= 0) {
            continue;
        }

        $lines[] = array(
            'product_id' => (int) ($line['product_id'] ?? 0),
            'description' => (string) ($line['description'] ?? ''),
            'quantity' => $remaining,
            'unit_code' => (string) ($line['unit_code'] ?? 'C62'),
            'unit_price' => (int) ($line['unit_price'] ?? 0),
            'discount_rate' => (float) ($line['discount_rate'] ?? 0),
            'tax_rate' => (float) ($line['tax_rate'] ?? 0),
            'tax2_rate' => (float) ($line['tax2_rate'] ?? 0),
            'withholding_code' => (string) ($line['withholding_code'] ?? ''),
            'withholding_rate' => (float) ($line['withholding_rate'] ?? 0),
            'source_line' => (string) ($line['source_line'] ?? ''),
        );
    }

    $data = array(
        'direction' => 'purchase',
        'account_id' => (int) $order['account_id'],
        'issue_date' => date('Y-m-d'),
        'due_date' => '',
        'currency' => (string) $order['currency'],
        'supplier_invoice_no' => '',
        'supplier_invoice_date' => '',
        'series' => defined('ERP_DEFAULT_SERIES') ? ERP_DEFAULT_SERIES : 'PGF',
        'created_by' => (int) $user_id,
        'lines' => $lines,
        'purchase_order_id' => (int) $order['id'],
    );

    // A foreign-currency bill at today's recorded rate; without one the
    // draft is saved with none and asks for it when issued.
    if (strtoupper((string) $order['currency']) !== erp_base_currency()) {
        $recorded = erp_fx_rate_for((string) $order['currency'], date('Y-m-d'));
        $data['exchange_rate'] = is_array($recorded) ? $recorded['rate'] : 0;
        $data['exchange_rate_date'] = is_array($recorded) ? $recorded['rate_date'] : date('Y-m-d');
        $data['exchange_rate_source'] = is_array($recorded) ? $recorded['source'] : '';
    }

    $reference = lang(array('string' => 'Purchase order {var:1}', 'vars' => (string) $order['full_number']));
    $data['notes'] = trim(trim((string) $order['notes']) . "\n" . $reference);

    $saved = erp_invoice_draft_save($data, 0);

    if (empty($saved['success'])) {
        return $fail((string) $saved['error']);
    }

    // What is on drafts is part of the order as it is read; a client that
    // follows changes by updated_at sees it.
    db("UPDATE erp_purchase_orders SET updated_at = UNIX_TIMESTAMP() WHERE id = '" . (int) $order['id'] . "'");

    return array('success' => true, 'invoice_id' => (int) $saved['invoice_id'], 'error' => '');
}

/**
 * How many orders still waiting for goods are past the day they were
 * expected.
 *
 * @return int
 */
function erp_purchase_order_late_count()
{
    if (!erp_purchase_orders_ready()) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM erp_purchase_orders
        WHERE status IN ('open', 'sent', 'partial') AND expected_date > '0000-00-00' AND expected_date < '" . escape(date('Y-m-d')) . "'");
}
