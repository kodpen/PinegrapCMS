<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - purchase orders through the external API.
 *
 * Read with what has come against each line, and written the way the
 * purchase order screens write them (includes/erp/purchase_orders.php): the
 * same save, the same status moves, the same purchase invoice draft for what
 * is still to come and the same e-mail, so an application cannot do to an
 * order anything an operator could not, and everything it does announces
 * itself the same way.
 *
 * Every check the domain function makes is made here first, before the
 * first write, which is where X-Dry-Run stops (includes/api/dry_run.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Reading
   --------------------------------------------------------------------------- */

/**
 * A 503 for a store that has not run the update that brings the purchase
 * order table (10.58).
 */
function erp_api_purchase_orders_ready()
{
    if (!erp_purchase_orders_ready()) {
        api_fail(503, 'service_unavailable', lang('Purchase orders come with the software update; run the update to use them.'));
    }
}

/**
 * The order rows with the columns the presenter reads.
 *
 * @return string  SELECT ... FROM ... JOIN ..., for erp_api_page()
 */
function erp_api_purchase_order_select()
{
    return "SELECT o.id, o.full_number, o.account_id, o.issue_date, o.expected_date, o.currency, o.exchange_rate,
            o.subtotal, o.discount_total, o.tax_total, o.tax2_total, o.withholding_total, o.grand_total, o.grand_total_base,
            o.status, o.line_data, o.notes, o.sent_at, o.created_at, o.updated_at, o.updated_at AS _sort,
            a.title AS account_title
        FROM erp_purchase_orders o
        LEFT JOIN erp_accounts a ON a.id = o.account_id";
}

/**
 * What a page of orders needs beside its rows, each read in one query: the
 * quantities the purchase invoices made from them carry per line (the
 * reading of erp_purchase_order_quantities()), those invoices, and the
 * product codes on their lines.
 *
 * @param array $rows  Order rows carrying line_data
 * @return array ['quantities' => order_id => [received, drafted], 'invoices' => order_id => rows, 'skus' => product_id => code]
 */
function erp_api_purchase_order_context($rows)
{
    $context = array('quantities' => array(), 'invoices' => array(), 'skus' => array());
    $ids = array();
    $product_ids = array();

    foreach ((array) $rows as $row) {
        $ids[(int) $row['id']] = (int) $row['id'];

        foreach (erp_purchase_order_lines($row) as $line) {
            if ((int) ($line['product_id'] ?? 0) > 0) {
                $product_ids[(int) $line['product_id']] = (int) $line['product_id'];
            }
        }
    }

    if (empty($ids)) {
        return $context;
    }

    $in = implode(',', $ids);

    if (erp_invoice_lines_have_source()) {
        foreach ((array) db_items("SELECT i.purchase_order_id, it.source_line, IF(i.status = 'draft', 1, 0) AS is_draft, SUM(it.quantity) AS quantity
            FROM erp_invoices i
            INNER JOIN erp_invoice_items it ON it.invoice_id = i.id
            WHERE i.purchase_order_id IN (" . $in . ")
                AND i.direction = 'purchase'
                AND i.doc_type = 'invoice'
                AND i.status <> 'cancelled'
                AND it.source_line <> ''
            GROUP BY i.purchase_order_id, it.source_line, is_draft") as $row) {
            $order_id = (int) $row['purchase_order_id'];
            $key = strtolower((string) $row['source_line']);
            $bucket = ((int) $row['is_draft'] === 1) ? 'drafted' : 'received';
            $context['quantities'][$order_id][$bucket][$key] = round(($context['quantities'][$order_id][$bucket][$key] ?? 0) + (float) $row['quantity'], 4);
        }
    }

    foreach ((array) db_items("SELECT id, purchase_order_id, full_number, status, issue_date, supplier_invoice_no, currency, grand_total
        FROM erp_invoices
        WHERE purchase_order_id IN (" . $in . ") AND direction = 'purchase' AND doc_type = 'invoice'
        ORDER BY id ASC") as $row) {
        $context['invoices'][(int) $row['purchase_order_id']][] = $row;
    }

    if (!empty($product_ids)) {
        foreach ((array) db_items("SELECT id, name FROM products WHERE id IN (" . implode(',', $product_ids) . ")") as $product) {
            $context['skus'][(int) $product['id']] = (string) $product['name'];
        }
    }

    return $context;
}

/**
 * One order line with what has come against it. The lines are kept on the
 * order as they were worked out, shaped like erp_invoice_items rows with
 * their key in source_line.
 *
 * @param array  $line
 * @param int    $position  The line's place, for a line saved without line_no
 * @param array  $progress  The line's row of erp_purchase_order_progress()
 * @param string $sku
 * @return array
 */
function erp_api_purchase_order_line_present($line, $position, $progress, $sku)
{
    return array(
        'key' => (string) ($line['source_line'] ?? ''),
        'line_no' => (int) ($line['line_no'] ?? $position),
        'product_id' => (int) ($line['product_id'] ?? 0),
        'sku' => (string) $sku,
        'description' => (string) ($line['description'] ?? ''),
        'quantity' => (float) ($line['quantity'] ?? 0),
        'unit' => (string) ($line['unit_code'] ?? ''),
        'unit_price' => api_money($line['unit_price'] ?? 0),
        'discount_rate' => (float) ($line['discount_rate'] ?? 0),
        'discount_amount' => api_money($line['discount_amount'] ?? 0),
        'tax_rate' => (float) ($line['tax_rate'] ?? 0),
        'tax_total' => api_money($line['tax_total'] ?? 0),
        'tax2_rate' => (float) ($line['tax2_rate'] ?? 0),
        'tax2_amount' => api_money($line['tax2_amount'] ?? 0),
        'withholding_rate' => (float) ($line['withholding_rate'] ?? 0),
        'withholding_code' => (string) ($line['withholding_code'] ?? ''),
        'withholding_amount' => api_money($line['withholding_amount'] ?? 0),
        'line_total' => api_money($line['line_total'] ?? 0),
        'received_quantity' => (float) ($progress['received'] ?? 0),
        'drafted_quantity' => (float) ($progress['drafted'] ?? 0),
        'outstanding_quantity' => (float) ($progress['open'] ?? 0),
    );
}

/**
 * A purchase invoice or draft made from the order, as the order lists it.
 *
 * @param array $invoice  erp_invoices row
 * @return array
 */
function erp_api_purchase_order_invoice_present($invoice)
{
    return array(
        'id' => (int) $invoice['id'],
        'number' => (string) $invoice['full_number'],
        'status' => (string) $invoice['status'],
        'issue_date' => erp_api_date($invoice['issue_date']),
        'supplier_invoice_no' => (string) $invoice['supplier_invoice_no'],
        'currency' => (string) $invoice['currency'],
        'grand_total' => api_money($invoice['grand_total']),
    );
}

/**
 * @param array $row      The order row
 * @param array $context  erp_api_purchase_order_context()
 * @return array
 */
function erp_api_purchase_order_present($row, $context)
{
    $order_id = (int) $row['id'];
    $lines = array_values(erp_purchase_order_lines($row));
    $quantities = $context['quantities'][$order_id] ?? array();
    $progress = erp_purchase_order_progress($lines, $quantities['received'] ?? array(), $quantities['drafted'] ?? array());
    $status = (string) $row['status'];
    $expected = erp_api_date($row['expected_date']);

    $out_lines = array();

    foreach ($lines as $index => $line) {
        $out_lines[] = erp_api_purchase_order_line_present($line, $index + 1, $progress[$index] ?? array(),
            $context['skus'][(int) ($line['product_id'] ?? 0)] ?? '');
    }

    $invoices = array_map('erp_api_purchase_order_invoice_present', $context['invoices'][$order_id] ?? array());

    return array(
        'id' => $order_id,
        'number' => (string) $row['full_number'],
        'status' => $status,
        'account_id' => (int) $row['account_id'],
        'account_title' => (string) ($row['account_title'] ?? ''),
        'issue_date' => erp_api_date($row['issue_date']),
        'expected_date' => $expected,
        // The dashboard's reading (erp_purchase_order_late_count()).
        'late' => in_array($status, array('open', 'sent', 'partial'), true) && ($expected !== null) && ($expected < date('Y-m-d')),
        'currency' => (string) $row['currency'],
        'exchange_rate' => (float) $row['exchange_rate'],
        'totals' => array(
            'subtotal' => api_money($row['subtotal']),
            'discount' => api_money($row['discount_total']),
            'tax' => api_money($row['tax_total']),
            'tax2' => api_money($row['tax2_total']),
            'withholding' => api_money($row['withholding_total']),
            'grand_total' => api_money($row['grand_total']),
            'grand_total_base' => api_money($row['grand_total_base']),
        ),
        'notes' => (string) $row['notes'],
        'lines' => $out_lines,
        'invoices' => $invoices,
        'sent_at' => api_time($row['sent_at']),
        'created_at' => api_time($row['created_at']),
        'updated_at' => api_time($row['updated_at']),
    );
}

// What erp_api_purchase_order_present() returns, declared for the OpenAPI document.
function erp_api_purchase_order_schema()
{
    return array(
        'id' => 'integer',
        'number' => 'string',
        'status' => 'string',
        'account_id' => 'integer',
        'account_title' => 'string',
        'issue_date' => 'string?',
        'expected_date' => 'string?',
        'late' => 'boolean',
        'currency' => 'string',
        'exchange_rate' => 'number',
        'totals' => array(
            'subtotal' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'tax2' => 'integer',
            'withholding' => 'integer',
            'grand_total' => 'integer',
            'grand_total_base' => 'integer',
        ),
        'notes' => 'string',
        'lines' => array(array(
            'key' => 'string',
            'line_no' => 'integer',
            'product_id' => 'integer',
            'sku' => 'string',
            'description' => 'string',
            'quantity' => 'number',
            'unit' => 'string',
            'unit_price' => 'integer',
            'discount_rate' => 'number',
            'discount_amount' => 'integer',
            'tax_rate' => 'number',
            'tax_total' => 'integer',
            'tax2_rate' => 'number',
            'tax2_amount' => 'integer',
            'withholding_rate' => 'number',
            'withholding_code' => 'string',
            'withholding_amount' => 'integer',
            'line_total' => 'integer',
            'received_quantity' => 'number',
            'drafted_quantity' => 'number',
            'outstanding_quantity' => 'number',
        )),
        'invoices' => array(array(
            'id' => 'integer',
            'number' => 'string',
            'status' => 'string',
            'issue_date' => 'string?',
            'supplier_invoice_no' => 'string',
            'currency' => 'string',
            'grand_total' => 'integer',
        )),
        'sent_at' => 'string?',
        'created_at' => 'string?',
        'updated_at' => 'string?',
    );
}

function erp_api_purchase_orders_list($params)
{
    erp_api_purchase_orders_ready();

    $where = array();
    $waiting = "o.status IN ('open', 'sent', 'partial')";
    $late = $waiting . " AND o.expected_date > '0000-00-00' AND o.expected_date < '" . escape(date('Y-m-d')) . "'";

    $status = (string) ($params['status'] ?? '');

    if ($status === 'waiting') {
        $where[] = $waiting;
    } elseif ($status !== '') {
        $where[] = "o.status = '" . escape($status) . "'";
    }

    if (isset($params['late'])) {
        $where[] = $params['late'] ? "(" . $late . ")" : "NOT (" . $late . ")";
    }

    if (isset($params['account_id'])) {
        $where[] = "o.account_id = '" . (int) $params['account_id'] . "'";
    }

    if (isset($params['number']) && ($params['number'] !== '')) {
        $where[] = "o.full_number = '" . escape($params['number']) . "'";
    }

    if (isset($params['issued_from'])) {
        $where[] = "o.issue_date >= '" . escape(erp_api_day($params['issued_from'])) . "'";
    }

    if (isset($params['issued_to'])) {
        $where[] = "o.issue_date <= '" . escape(erp_api_day($params['issued_to'])) . "'";
    }

    if (isset($params['updated_since'])) {
        $where[] = "o.updated_at >= '" . (int) $params['updated_since'] . "'";
    }

    $page = erp_api_page(erp_api_purchase_order_select(), $where, 'o.updated_at', 'o.id', $params, "FROM erp_purchase_orders o");
    $context = erp_api_purchase_order_context($page['rows']);

    $out = array();

    foreach ($page['rows'] as $row) {
        $out[] = erp_api_purchase_order_present($row, $context);
    }

    api_ok_list($out, $page['limit'], $page['next_cursor'], $page['total']);
}

function erp_api_purchase_orders_get($params)
{
    $order = erp_api_purchase_order_for_write((int) $params['id']);

    api_ok(erp_api_purchase_order_present($order, erp_api_purchase_order_context(array($order))));
}

function erp_api_purchase_orders_document($params)
{
    $order = erp_api_purchase_order_for_write((int) $params['id']);

    $html = erp_purchase_order_html((int) $order['id']);

    if ($html === false) {
        api_fail_not_found(lang('Purchase order'));
    }

    $pdf = erp_invoice_pdf($html);

    if ($pdf === false) {
        api_fail(503, 'service_unavailable', lang('The PDF library is not installed.'));
    }

    api_ok(erp_api_document_present(erp_purchase_order_file_name($order) . '.pdf', 'application/pdf', $pdf));
}

/* ---------------------------------------------------------------------------
   Writing
   --------------------------------------------------------------------------- */

/**
 * The order a call is about, or a 404.
 *
 * @param int $order_id
 * @return array  From erp_purchase_order()
 */
function erp_api_purchase_order_for_write($order_id)
{
    erp_api_purchase_orders_ready();

    $order = erp_purchase_order((int) $order_id);

    if ($order === null) {
        api_fail_not_found(lang('Purchase order'));
    }

    return $order;
}

function erp_api_purchase_order_answer($order_id, $status = 200)
{
    $order = erp_api_purchase_order_for_write($order_id);

    api_ok(erp_api_purchase_order_present($order, erp_api_purchase_order_context(array($order))), $status);
}

/**
 * An order as erp_purchase_order_save() takes it - the shape the panel's form
 * reads - from what was sent over the order as it is kept. The day the goods
 * are expected travels as due_date, the way the form sends it.
 *
 * @param array $params
 * @param array $order  From erp_purchase_order(), empty for a new order
 * @return array
 */
function erp_api_purchase_order_in($params, $order)
{
    $owner = erp_api_owner();
    $kept = array();

    if (!empty($order)) {
        $kept = array(
            'account_id' => (int) $order['account_id'],
            'issue_date' => (string) $order['issue_date'],
            'expected_date' => (string) $order['expected_date'],
            'currency' => (string) $order['currency'],
            'exchange_rate' => (float) $order['exchange_rate'],
            'notes' => (string) $order['notes'],
        );
    }

    $sent = function ($name) use ($params) {
        return array_key_exists($name, $params);
    };

    $account_id = $sent('account_id') ? (int) $params['account_id'] : (int) ($kept['account_id'] ?? 0);

    if (!is_array(erp_account($account_id))) {
        api_fail_validation(lang('Choose an account.'), 'account_id');
    }

    $header = erp_api_document_header($params, $kept, 'expected_date', 'expected_date');

    // A line sent back with its key stays the line it was, so the drafts made
    // from the order keep counting against it. A new order gives the keys.
    $lines = $sent('lines') ? erp_api_lines_in($params['lines'], $account_id, empty($order) ? '' : 'key') : erp_purchase_order_lines($order);

    if (($header['due_date'] !== '') && ($header['due_date'] < $header['issue_date'])) {
        api_fail_validation(lang('The goods cannot be expected before the order\'s date.'), 'expected_date');
    }

    return array_merge($header, array(
        'direction' => 'purchase',
        'account_id' => $account_id,
        'supplier_invoice_no' => '',
        'supplier_invoice_date' => '',
        'notes' => $sent('notes') ? (string) $params['notes'] : (string) ($kept['notes'] ?? ''),
        'lines' => $lines,
        'created_by' => (int) $owner['id'],
    ));
}

function erp_api_purchase_orders_create($params)
{
    erp_api_purchase_orders_ready();

    $data = erp_api_purchase_order_in($params, array());
    $built = erp_api_document_check($data, array('due_date' => 'expected_date'));

    api_dry_run_stop('created', 'erp_purchase_order', array(
        'account_id' => $data['account_id'],
        'currency' => $data['currency'],
        'lines' => count($built['lines']),
        'grand_total' => (int) $built['totals']['grand_total'],
    ));

    $saved = erp_purchase_order_save($data, 0, erp_api_owner()['id']);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error'], erp_api_document_field((string) $saved['field'], array('due_date' => 'expected_date')));
    }

    erp_api_log(lang(array(
        'string' => 'ERP purchase order {var:1} was written through the API.',
        'vars' => (string) $saved['full_number'],
    )));

    erp_api_purchase_order_answer((int) $saved['order_id'], 201);
}

function erp_api_purchase_orders_update($params)
{
    $order = erp_api_purchase_order_for_write((int) $params['id']);

    if (!erp_purchase_order_editable($order)) {
        api_fail(409, 'purchase_order_not_editable', lang('Only an open or sent purchase order can be changed.'));
    }

    $data = erp_api_purchase_order_in($params, $order);
    $built = erp_api_document_check($data, array('due_date' => 'expected_date'));

    api_dry_run_stop('updated', 'erp_purchase_order', array(
        'id' => (int) $order['id'],
        'lines' => count($built['lines']),
        'grand_total' => (int) $built['totals']['grand_total'],
    ));

    $saved = erp_purchase_order_save($data, (int) $order['id'], erp_api_owner()['id']);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error'], erp_api_document_field((string) $saved['field'], array('due_date' => 'expected_date')));
    }

    erp_api_log(lang(array(
        'string' => 'ERP purchase order {var:1} was changed through the API.',
        'vars' => (string) $order['full_number'],
    )));

    erp_api_purchase_order_answer((int) $order['id']);
}

function erp_api_purchase_orders_status($params)
{
    $order = erp_api_purchase_order_for_write((int) $params['id']);
    $to = (string) $params['status'];

    if (($refusal = erp_purchase_order_status_refusal($order, $to)) !== '') {
        api_fail(409, 'purchase_order_status_not_allowed', $refusal);
    }

    api_dry_run_stop('updated', 'erp_purchase_order', array('id' => (int) $order['id'], 'from' => (string) $order['status'], 'to' => $to));

    $moved = ($to === 'sent') ? erp_purchase_order_mark_sent((int) $order['id']) : erp_purchase_order_set_status((int) $order['id'], $to);

    if (empty($moved['success'])) {
        api_fail(409, 'purchase_order_status_not_allowed', (string) $moved['error']);
    }

    erp_api_log(lang(array(
        'string' => 'ERP purchase order {var:1} was marked {var:2} through the API.',
        'vars' => array((string) $order['full_number'], $to),
    )));

    erp_api_purchase_order_answer((int) $order['id']);
}

function erp_api_purchase_orders_invoice($params)
{
    $order = erp_api_purchase_order_for_write((int) $params['id']);
    $progress = erp_purchase_order_order_progress($order);

    if (($refusal = erp_purchase_order_invoice_refusal($order, $progress)) !== '') {
        api_fail(409, 'purchase_order_not_invoiceable', $refusal);
    }

    if (!is_array(erp_account((int) $order['account_id']))) {
        api_fail_validation(lang('Choose an account.'), 'id');
    }

    $lines = 0;

    foreach ($progress as $row) {
        $lines += ((float) $row['remaining'] > 0) ? 1 : 0;
    }

    api_dry_run_stop('created', 'erp_invoice', array(
        'status' => 'draft',
        'direction' => 'purchase',
        'purchase_order_id' => (int) $order['id'],
        'issue_date' => date('Y-m-d'),
        'lines' => $lines,
    ));

    $made = erp_purchase_order_to_invoice((int) $order['id'], erp_api_owner()['id']);

    if (empty($made['success'])) {
        api_fail_validation((string) $made['error'], 'id');
    }

    erp_api_log(lang(array(
        'string' => 'ERP purchase order {var:1} was turned into purchase invoice draft #{var:2} through the API.',
        'vars' => array((string) $order['full_number'], (int) $made['invoice_id']),
    )));

    erp_api_invoice_answer((int) $made['invoice_id'], 201);
}

function erp_api_purchase_orders_email($params)
{
    $order = erp_api_purchase_order_for_write((int) $params['id']);

    if (!erp_mail_ready()) {
        api_fail(503, 'service_unavailable', lang('Sending documents by e-mail comes with the software update; run the update to use it.'));
    }

    if (($refusal = erp_purchase_order_status_refusal($order, 'sent')) !== '') {
        api_fail(409, 'purchase_order_not_sendable', $refusal);
    }

    $to = trim((string) ($params['to'] ?? ''));
    $to = ($to !== '') ? $to : erp_purchase_order_mail_defaults($order)['to'];
    $addresses = erp_mail_addresses($to);

    if ($addresses['error'] !== '') {
        api_fail_validation($addresses['error'], 'to');
    }

    if (erp_reconciliation_sender() === '') {
        api_fail_validation(lang('The store has no e-mail address to send from. Set one under Settings.'));
    }

    api_dry_run_stop('sent', 'erp_purchase_order_email', array('purchase_order_id' => (int) $order['id'], 'to' => $addresses['addresses']));

    $sent = erp_purchase_order_mail_send((int) $order['id'], implode(', ', $addresses['addresses']),
        (string) ($params['subject'] ?? ''), (string) ($params['message'] ?? ''), erp_api_owner()['id']);

    if (empty($sent['success'])) {
        api_fail(503, 'service_unavailable', (string) $sent['error']);
    }

    erp_api_log(lang(array(
        'string' => 'ERP purchase order {var:1} was e-mailed to {var:2} through the API.',
        'vars' => array((string) $order['full_number'], implode(', ', $addresses['addresses'])),
    )));

    api_ok(array('purchase_order_id' => (int) $order['id'], 'to' => $addresses['addresses']));
}
