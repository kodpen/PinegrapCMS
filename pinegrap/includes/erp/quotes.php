<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - quotes.
 *
 * A priced offer to a customer: the lines of an invoice, with a date it runs
 * to instead of a due date. It moves no money, no stock and no tax, and lives
 * in its own table (erp_quotes, 4.92) so that no report has to learn to leave
 * it out. It is typed in the invoice editor, takes a number of its own series
 * when first saved, is printed with the invoice template and e-mailed like an
 * invoice. Once the customer says yes it becomes an invoice draft in one step,
 * at the prices quoted; the draft is issued the usual way.
 *
 * Status: open (editable; shown as expired once its date has passed),
 * accepted, rejected, cancelled, invoiced. An invoiced quote whose draft was
 * deleted reads as accepted again and can be invoiced once more.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

if (!defined('ERP_QUOTE_VALID_DAYS')) {
    define('ERP_QUOTE_VALID_DAYS', 30);
}

/**
 * Whether the 4.92 table is there.
 *
 * @return bool
 */
function erp_quotes_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_quotes', 'line_data');
    }

    return $ready;
}

/**
 * The series quotes are numbered in: config.php may name one
 * (ERP_QUOTE_SERIES); otherwise the invoice series with a T after it, the
 * way delivery notes take an S.
 *
 * @return string
 */
function erp_quote_series()
{
    if (defined('ERP_QUOTE_SERIES') && (trim((string) ERP_QUOTE_SERIES) !== '')) {
        return trim((string) ERP_QUOTE_SERIES);
    }

    return (defined('ERP_DEFAULT_SERIES') ? trim((string) ERP_DEFAULT_SERIES) : 'PGF') . 'T';
}

/**
 * @return array  state => [label, badge colour]
 */
function erp_quote_statuses()
{
    return array(
        'open' => array(lang('Open'), 'primary'),
        'expired' => array(lang('Expired'), 'secondary'),
        'accepted' => array(lang('Accepted'), 'success'),
        'rejected' => array(lang('Rejected'), 'danger'),
        'invoiced' => array(lang('Invoiced'), 'info'),
        'cancelled' => array(lang('Cancelled'), 'dark'),
    );
}

/**
 * One quote with its account's live details.
 *
 * @param int $quote_id
 * @return array|null
 */
function erp_quote($quote_id)
{
    if (!erp_quotes_ready() || ((int) $quote_id <= 0)) {
        return null;
    }

    $invoice_email = waf_table_has_column('erp_accounts', 'invoice_email') ? 'a.invoice_email' : "''";

    $quote = db_item("SELECT q.*, a.title AS account_title, a.email AS account_email, " . $invoice_email . " AS invoice_email,
            i.status AS invoice_status, i.full_number AS invoice_number
        FROM erp_quotes q
        LEFT JOIN erp_accounts a ON a.id = q.account_id
        LEFT JOIN erp_invoices i ON i.id = q.invoice_id
        WHERE q.id = '" . (int) $quote_id . "' LIMIT 1");

    return is_array($quote) ? $quote : null;
}

/**
 * What the quote is now: its status, except that an open quote past its date
 * is expired and an invoiced one whose draft is gone is accepted again.
 *
 * @param array $quote
 * @return string  A key of erp_quote_statuses()
 */
function erp_quote_state($quote)
{
    $status = (string) $quote['status'];

    if (($status === 'invoiced') && ((string) ($quote['invoice_status'] ?? '') === '')) {
        return 'accepted';
    }

    if (($status === 'open') && ((string) $quote['valid_until'] > '0000-00-00') && ((string) $quote['valid_until'] < date('Y-m-d'))) {
        return 'expired';
    }

    return $status;
}

/**
 * The lines as stored, shaped like erp_invoice_items rows.
 *
 * @param array $quote
 * @return array
 */
function erp_quote_lines($quote)
{
    $lines = json_decode((string) ($quote['line_data'] ?? ''), true);

    return is_array($lines) ? $lines : array();
}

/**
 * The lines for the editor, with the product names the editor shows.
 *
 * @param array $quote
 * @return array
 */
function erp_quote_editor_rows($quote)
{
    $lines = erp_quote_lines($quote);
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
 * Save a new quote, which takes its number, or rewrite an open one.
 *
 * @param array $data      What erp_invoice_form_read() returns; due_date is
 *                         the date the quote runs to
 * @param int   $quote_id  0 for a new quote
 * @param int   $user_id
 * @return array ['success' => bool, 'quote_id' => int, 'full_number' => string, 'error' => string, 'field' => string]
 */
function erp_quote_save($data, $quote_id, $user_id)
{
    $fail = function ($message, $field = '_error') {
        return array('success' => false, 'quote_id' => 0, 'full_number' => '', 'error' => $message, 'field' => $field);
    };

    if (!erp_quotes_ready()) {
        return $fail(lang('Quotes come with the software update; run the update to use them.'));
    }

    $data['direction'] = 'sales';
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

    $valid_until = (string) ($data['due_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valid_until)) {
        $valid_until = date('Y-m-d', strtotime($header['issue_date'] . ' +' . (int) ERP_QUOTE_VALID_DAYS . ' days'));
    }

    if ($valid_until < $header['issue_date']) {
        return $fail(lang('The quote cannot run out before its date.'), 'due_date');
    }

    $totals = $built['totals'];
    $base = erp_base_currency();
    $grand_total_base = ($header['currency'] === $base)
        ? $totals['grand_total']
        : (($header['exchange_rate'] > 0) ? erp_to_base($totals['grand_total'], $header['exchange_rate']) : 0);

    $lines = array();
    foreach (array_values($built['lines']) as $index => $line) {
        $line['line_no'] = $index + 1;
        $lines[] = $line;
    }

    $data['due_date'] = $valid_until;
    $data['created_by'] = (int) $user_id;

    $set = "
            account_id = '" . (int) $header['account_id'] . "',
            issue_date = '" . escape($header['issue_date']) . "',
            valid_until = '" . escape($valid_until) . "',
            currency = '" . escape($header['currency']) . "',
            exchange_rate = '" . escape(number_format((float) $header['exchange_rate'], 6, '.', '')) . "',
            subtotal = '" . (int) $totals['subtotal'] . "',
            discount_total = '" . (int) $totals['discount_total'] . "',
            tax_total = '" . (int) $totals['tax_total'] . "',
            tax2_total = '" . (int) $totals['tax2_total'] . "',
            withholding_total = '" . (int) $totals['withholding_total'] . "',
            grand_total = '" . (int) $totals['grand_total'] . "',
            grand_total_base = '" . (int) $grand_total_base . "',
            form_data = '" . escape(json_encode($data, JSON_UNESCAPED_UNICODE)) . "',
            line_data = '" . escape(json_encode($lines, JSON_UNESCAPED_UNICODE)) . "',
            notes = '" . escape($header['notes']) . "',
            updated_at = UNIX_TIMESTAMP()";

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    if ((int) $quote_id > 0) {
        $existing = db_item("SELECT id, status, full_number FROM erp_quotes WHERE id = '" . (int) $quote_id . "' LIMIT 1 FOR UPDATE");

        if (!is_array($existing)) {
            erp_tx_rollback();
            return $fail(lang('The quote could not be found.'));
        }

        if ((string) $existing['status'] !== 'open') {
            erp_tx_rollback();
            return $fail(lang('Only an open quote can be changed. Open it again first, or write a new one.'));
        }

        if (erp_query("UPDATE erp_quotes SET " . $set . " WHERE id = '" . (int) $quote_id . "'") === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }

        if (!erp_tx_commit()) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }

        return array('success' => true, 'quote_id' => (int) $quote_id, 'full_number' => (string) $existing['full_number'], 'error' => '', 'field' => '');
    }

    $numbered = erp_next_number(erp_quote_series(), 'proforma', (int) substr($header['issue_date'], 0, 4));

    if (empty($numbered['success'])) {
        erp_tx_rollback();
        return $fail((string) $numbered['error']);
    }

    $ok = erp_query("INSERT INTO erp_quotes SET
            series = '" . escape(erp_quote_series()) . "',
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

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'quote_id' => $new_id, 'full_number' => (string) $numbered['full'], 'error' => '', 'field' => '');
}

/**
 * Move a quote to another status by hand.
 *
 * @param int    $quote_id
 * @param string $to       open | accepted | rejected | cancelled
 * @param int    $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_quote_set_status($quote_id, $to, $user_id)
{
    $quote = erp_quote($quote_id);

    if ($quote === null) {
        return array('success' => false, 'error' => lang('The quote could not be found.'));
    }

    $state = erp_quote_state($quote);
    $allowed = array(
        'open' => array('accepted', 'rejected', 'cancelled'),
        'expired' => array('open', 'accepted', 'rejected', 'cancelled'),
        'accepted' => array('open', 'rejected', 'cancelled'),
        'rejected' => array('open'),
        'cancelled' => array('open'),
        'invoiced' => array(),
    );

    if (!in_array((string) $to, $allowed[$state] ?? array(), true)) {
        return array('success' => false, 'error' => ($state === 'invoiced')
            ? lang('The quote has become an invoice; the invoice is where it goes on from here.')
            : lang('The quote cannot be moved to that status.'));
    }

    // Opening an expired quote again gives it the usual run from today.
    $valid_sql = '';
    if (($to === 'open') && ((string) $quote['valid_until'] < date('Y-m-d'))) {
        $valid_sql = ", valid_until = '" . escape(date('Y-m-d', strtotime('+' . (int) ERP_QUOTE_VALID_DAYS . ' days'))) . "'";
    }

    db("UPDATE erp_quotes SET status = '" . escape($to) . "', invoice_id = IF('" . escape($to) . "' = 'open', 0, invoice_id),
            decided_by = '" . (($to === 'open') ? 0 : (int) $user_id) . "', decided_at = " . (($to === 'open') ? '0' : 'UNIX_TIMESTAMP()') . $valid_sql . ",
            updated_at = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $quote['id'] . "'");

    return array('success' => true, 'error' => '');
}

/**
 * Turn the quote into an invoice draft at the prices quoted, dated today.
 *
 * A quote in another currency leaves the draft without a rate, so the rate
 * of the day the invoice is issued is used.
 *
 * @param int $quote_id
 * @param int $user_id
 * @return array ['success' => bool, 'invoice_id' => int, 'error' => string]
 */
function erp_quote_to_invoice($quote_id, $user_id)
{
    $quote = erp_quote($quote_id);

    if ($quote === null) {
        return array('success' => false, 'invoice_id' => 0, 'error' => lang('The quote could not be found.'));
    }

    $state = erp_quote_state($quote);

    if (!in_array($state, array('open', 'expired', 'accepted'), true)) {
        return array('success' => false, 'invoice_id' => 0, 'error' => ($state === 'invoiced')
            ? lang('The quote has already become an invoice.')
            : lang('A rejected or cancelled quote is not invoiced. Open it again first.'));
    }

    $data = json_decode((string) $quote['form_data'], true);

    if (!is_array($data)) {
        return array('success' => false, 'invoice_id' => 0, 'error' => lang('The quote\'s lines could not be read.'));
    }

    $base = erp_base_currency();
    $data['direction'] = 'sales';
    $data['account_id'] = (int) $quote['account_id'];
    $data['issue_date'] = date('Y-m-d');
    $data['due_date'] = '';
    $data['series'] = defined('ERP_DEFAULT_SERIES') ? ERP_DEFAULT_SERIES : 'PGF';
    $data['created_by'] = (int) $user_id;
    $data['lines'] = erp_quote_lines($quote);

    if (strtoupper((string) $quote['currency']) !== $base) {
        $recorded = erp_fx_rate_for((string) $quote['currency'], date('Y-m-d'));
        $data['exchange_rate'] = is_array($recorded) ? $recorded['rate'] : 0;
        $data['exchange_rate_date'] = is_array($recorded) ? $recorded['rate_date'] : date('Y-m-d');
        $data['exchange_rate_source'] = is_array($recorded) ? $recorded['source'] : '';
    }

    $reference = lang(array('string' => 'Quote {var:1}', 'vars' => (string) $quote['full_number']));
    $data['notes'] = trim(trim((string) ($data['notes'] ?? '')) . "\n" . $reference);

    $saved = erp_invoice_draft_save($data, 0);

    if (empty($saved['success'])) {
        return array('success' => false, 'invoice_id' => 0, 'error' => (string) $saved['error']);
    }

    db("UPDATE erp_quotes SET status = 'invoiced', invoice_id = '" . (int) $saved['invoice_id'] . "',
            decided_by = IF(decided_at > 0, decided_by, '" . (int) $user_id . "'),
            decided_at = IF(decided_at > 0, decided_at, UNIX_TIMESTAMP()),
            updated_at = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $quote['id'] . "'");

    return array('success' => true, 'invoice_id' => (int) $saved['invoice_id'], 'error' => '');
}

/**
 * The quote as the invoice template reads a document.
 *
 * @param array $quote  From erp_quote()
 * @return array  erp_invoice_document_data()'s $source
 */
function erp_quote_document_source($quote)
{
    $account = db_item("SELECT * FROM erp_accounts WHERE id = '" . (int) $quote['account_id'] . "' LIMIT 1");
    $account = is_array($account) ? $account : array();

    $row = array(
        'id' => (int) $quote['id'],
        'full_number' => (string) $quote['full_number'],
        'issue_date' => (string) $quote['issue_date'],
        'due_date' => (string) $quote['valid_until'],
        'doc_type' => 'proforma',
        'status' => 'issued',
        'direction' => 'sales',
        'order_id' => 0,
        'order_number' => '',
        'parent_invoice_id' => 0,
        'currency' => (string) $quote['currency'],
        'exchange_rate' => (float) $quote['exchange_rate'],
        'exchange_rate_date' => (string) $quote['issue_date'],
        'grand_total_base' => (int) $quote['grand_total_base'],
        'is_internet_sale' => 0,
        'payment_method' => '',
        'payment_date' => '0000-00-00',
        'shipment_date' => '0000-00-00',
        'carrier_title' => '',
        'carrier_vkn' => '',
        'web_address' => '',
        'notes' => (string) $quote['notes'],
        'supplier_invoice_no' => '',
        'supplier_invoice_date' => '0000-00-00',
        'subtotal' => (int) $quote['subtotal'],
        'discount_total' => (int) $quote['discount_total'],
        'shipping_total' => 0,
        'surcharge_total' => 0,
        'gift_card_total' => 0,
        'tax_total' => (int) $quote['tax_total'],
        'tax2_total' => (int) $quote['tax2_total'],
        'withholding_total' => (int) $quote['withholding_total'],
        'grand_total' => (int) $quote['grand_total'],
        // No copy of the account is kept on a quote: it is printed with the
        // account as it reads today.
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
        'items' => erp_quote_lines($quote),
        'title' => lang('QUOTE'),
        'label' => array(
            'invoice_no' => lang('Quote No'),
            'invoice_date' => lang('Quote Date'),
            'due_date' => lang('Valid until'),
        ),
    );
}

/**
 * The quote as a complete HTML document, in the store's invoice template.
 *
 * @param int $quote_id
 * @return string|false
 */
function erp_quote_html($quote_id)
{
    $quote = erp_quote($quote_id);

    if ($quote === null) {
        return false;
    }

    $data = erp_invoice_document_data(0, erp_quote_document_source($quote));

    return ($data === false) ? false : erp_template_render(erp_invoice_template(), $data);
}

/**
 * The quote's file name, without the extension.
 *
 * @param array $quote
 * @return string
 */
function erp_quote_file_name($quote)
{
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $quote['full_number']);

    return (trim((string) $name, '_') !== '') ? $name : ('quote_' . (int) $quote['id']);
}

/**
 * What a new e-mail of the quote starts with.
 *
 * @param array $quote
 * @return array ['to', 'subject', 'message']
 */
function erp_quote_mail_defaults($quote)
{
    $to = trim((string) ($quote['invoice_email'] ?? ''));

    return array(
        'to' => ($to !== '') ? $to : trim((string) ($quote['account_email'] ?? '')),
        'subject' => ORGANIZATION_NAME . ' - ' . lang('Sales quote') . ' ' . (string) $quote['full_number'],
        'message' => lang(array('string' => 'Please find our quote attached. It is valid until {var:1}.', 'vars' => prepare_form_data_for_output((string) $quote['valid_until'], 'date', false))),
    );
}

/**
 * E-mail the quote, with its PDF, and record it with the quote.
 *
 * @param int    $quote_id
 * @param string $to
 * @param string $subject
 * @param string $message
 * @param int    $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_quote_mail_send($quote_id, $to, $subject, $message, $user_id)
{
    $mail_id = 0;
    $fail = function ($message) use (&$mail_id) {
        if ($mail_id > 0) {
            erp_mail_settle($mail_id, false, '', $message);
        }
        return array('success' => false, 'error' => $message);
    };

    $quote = erp_quote($quote_id);

    if ($quote === null) {
        return $fail(lang('The quote could not be found.'));
    }

    if (in_array(erp_quote_state($quote), array('cancelled', 'rejected'), true)) {
        return $fail(lang('A rejected or cancelled quote is not sent. Open it again first.'));
    }

    if (!function_exists('erp_mail_ready') || !erp_mail_ready()) {
        return $fail(lang('Sending documents by e-mail comes with the software update; run the update to use it.'));
    }

    $addresses = erp_mail_addresses($to);

    if ($addresses['error'] !== '') {
        return $fail($addresses['error']);
    }

    $defaults = erp_quote_mail_defaults($quote);
    $subject = (trim((string) $subject) !== '') ? mb_substr(trim((string) $subject), 0, 255) : $defaults['subject'];
    $message = (trim((string) $message) !== '') ? mb_substr(trim((string) $message), 0, 5000) : $defaults['message'];
    $sender = function_exists('erp_reconciliation_sender') ? erp_reconciliation_sender() : '';

    if ($sender === '') {
        return $fail(lang('The store has no e-mail address to send from. Set one under Settings.'));
    }

    $mail_id = erp_mail_record('quote', (int) $quote['id'], 'manual', implode(', ', $addresses['addresses']), $subject, $message, $user_id);

    $html = erp_quote_html((int) $quote['id']);
    $pdf = ($html !== false) ? erp_invoice_pdf($html) : false;

    if ($pdf === false) {
        return $fail(lang('The PDF library is not installed.'));
    }

    $file = erp_quote_file_name($quote) . '.pdf';
    $sent = email(array(
        'to' => $addresses['addresses'],
        'from_name' => ORGANIZATION_NAME,
        'from_email_address' => $sender,
        'reply_to' => $sender,
        'subject' => $subject,
        'format' => 'html',
        'body' => erp_quote_mail_html($quote, $message, $sender),
        'type' => 'system',
        'attachments' => array(
            array('name' => $file, 'content' => $pdf, 'type' => 'application/pdf'),
        ),
    ));

    if (!$sent) {
        erp_mail_settle($mail_id, false, $file, lang('The e-mail could not be sent. The details are in the activity log.'));
        return array('success' => false, 'error' => lang('The e-mail could not be sent. The details are in the activity log.'));
    }

    erp_mail_settle($mail_id, true, $file, '');

    log_activity(lang(array(
        'string' => 'erp quote {var:1} was e-mailed to {var:2}',
        'vars' => array((string) $quote['full_number'], implode(', ', $addresses['addresses'])),
    )), (string) ($_SESSION['sessionusername'] ?? ''));

    return array('success' => true, 'error' => '');
}

/**
 * The e-mail itself: the covering text and the quote's figures.
 *
 * @param array  $quote
 * @param string $message
 * @param string $sender
 * @return string
 */
function erp_quote_mail_html($quote, $message, $sender)
{
    $paragraph = 'font-size: 14px; margin: 0 0 12px 0;';
    $cell = 'font-size: 14px; padding: 4px 16px 4px 0; vertical-align: top;';
    $title = trim((string) ($quote['account_title'] ?? ''));

    $rows = array(
        array(lang('Sales quote'), (string) $quote['full_number']),
        array(lang('Date'), prepare_form_data_for_output((string) $quote['issue_date'], 'date', false)),
        array(lang('Valid until'), prepare_form_data_for_output((string) $quote['valid_until'], 'date', false)),
        array(lang('Total'), erp_money_out_currency((int) $quote['grand_total'], (string) $quote['currency'])),
    );

    $output_rows = '';
    foreach ($rows as $row) {
        $output_rows .= '<tr><td style="' . $cell . ' color: #666666;">' . h($row[0]) . '</td><td style="' . $cell . ' font-weight: bold;">' . h($row[1]) . '</td></tr>';
    }

    return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>' . h(lang('Sales quote')) . '</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #222222; background: #ffffff; margin: 0; padding: 20px;">
    <div style="max-width: 640px; margin: 0 auto;">
        <h2 style="font-size: 18px; margin: 0 0 16px 0;">' . h(ORGANIZATION_NAME) . ' &middot; ' . h(lang('Sales quote')) . ' ' . h((string) $quote['full_number']) . '</h2>
        ' . (($title !== '') ? '<p style="' . $paragraph . '">' . h(lang(array('string' => 'Dear {var:1},', 'vars' => $title))) . '</p>' : '') . '
        <p style="' . $paragraph . '">' . nl2br(h(trim((string) $message))) . '</p>
        <table style="border-collapse: collapse; margin: 8px 0 16px 0;">' . $output_rows . '</table>
        <p style="' . $paragraph . '">' . h(lang('If you have a question about this quote, simply reply to this e-mail.')) . '</p>
        <p style="' . $paragraph . '">' . h(ORGANIZATION_NAME) . (($sender !== '') ? '<br>' . h($sender) : '') . '</p>
    </div>
</body>
</html>';
}

/**
 * The quotes for the list, newest first.
 *
 * @param array $filters  state (a key of erp_quote_statuses(), or ''), search, account_id, limit
 * @return array
 */
function erp_quote_rows($filters = array())
{
    if (!erp_quotes_ready()) {
        return array();
    }

    $today = escape(date('Y-m-d'));
    $where = array('1 = 1');
    $state = (string) ($filters['state'] ?? '');

    if ($state === 'open') {
        $where[] = "q.status = 'open' AND (q.valid_until = '0000-00-00' OR q.valid_until >= '" . $today . "')";
    } elseif ($state === 'expired') {
        $where[] = "q.status = 'open' AND q.valid_until > '0000-00-00' AND q.valid_until < '" . $today . "'";
    } elseif ($state === 'accepted') {
        $where[] = "(q.status = 'accepted' OR (q.status = 'invoiced' AND i.id IS NULL))";
    } elseif ($state === 'invoiced') {
        $where[] = "q.status = 'invoiced' AND i.id IS NOT NULL";
    } elseif (in_array($state, array('rejected', 'cancelled'), true)) {
        $where[] = "q.status = '" . escape($state) . "'";
    }

    if ((int) ($filters['account_id'] ?? 0) > 0) {
        $where[] = "q.account_id = '" . (int) $filters['account_id'] . "'";
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $like = "'%" . escape(addcslashes($search, '%_\\')) . "%'";
        $where[] = "(q.full_number LIKE " . $like . " OR a.title LIKE " . $like . ")";
    }

    return (array) db_items("SELECT q.id, q.full_number, q.account_id, q.issue_date, q.valid_until, q.currency, q.grand_total,
            q.status, q.invoice_id, a.title AS account_title,
            i.status AS invoice_status, i.full_number AS invoice_number
        FROM erp_quotes q
        LEFT JOIN erp_accounts a ON a.id = q.account_id
        LEFT JOIN erp_invoices i ON i.id = q.invoice_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY q.id DESC
        LIMIT " . max(1, min(1000, (int) ($filters['limit'] ?? 300))));
}

/**
 * How many quotes are open and still running.
 *
 * @return int
 */
function erp_quote_open_count()
{
    if (!erp_quotes_ready()) {
        return 0;
    }

    return (int) db_value("SELECT COUNT(*) FROM erp_quotes WHERE status = 'open' AND (valid_until = '0000-00-00' OR valid_until >= '" . escape(date('Y-m-d')) . "')");
}
