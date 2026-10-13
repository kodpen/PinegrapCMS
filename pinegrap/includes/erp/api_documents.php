<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - writing invoices and quotes through the external API.
 *
 * An invoice is written the way the panel writes one: saved as a draft that
 * takes no number (invoice_manual.php), then issued, cancelled, returned,
 * e-mailed or set to repeat with the same functions the invoice screens call,
 * so an application cannot do to the books anything an operator could not,
 * and everything it does announces itself the same way. Quotes go through
 * quotes.php likewise; a quote turned into an invoice is an ordinary draft
 * from then on.
 *
 * Every check the screen or the domain function makes is made here first,
 * before the first write, which is where X-Dry-Run stops
 * (includes/api/dry_run.php). A rehearsed issue that answers 200 is refused
 * for real only by what the write itself finds, such as a number another
 * request took in the meantime.
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
   Shared: the lines and the header of a typed document
   --------------------------------------------------------------------------- */

/**
 * A number sent on a line: a JSON number, or a string holding one written
 * with a point.
 *
 * @param mixed $value
 * @return float|null  Null for anything else
 */
function erp_api_line_number($value)
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }

    if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
        return (float) trim($value);
    }

    return null;
}

/**
 * The lines of an invoice or a quote as sent, checked and shaped the way
 * erp_manual_lines_build() reads them.
 *
 * Each line has to be an object carrying only the fields the route names,
 * with its figures as numbers and its product one the store has. A line tied
 * to a product takes the product's name and tax rate where the caller left
 * them out - the rate the panel's line editor would fill in, the account's
 * zone rate for a product without its own. The figures themselves are worked
 * out by the ERP, which refuses what it would refuse from the screen.
 *
 * @param mixed $raw         The lines parameter
 * @param int   $account_id  The account the document is for
 * @return array
 */
function erp_api_lines_in($raw, $account_id)
{
    $known = array('product_id', 'description', 'quantity', 'unit', 'unit_price', 'discount_rate', 'tax_rate', 'tax2_rate', 'withholding_code', 'withholding_rate');
    $lines = array();
    $product_ids = array();

    foreach (array_values((array) $raw) as $index => $line) {
        $field = function ($name) use ($index) {
            return 'lines[' . $index . '].' . $name;
        };

        if (!is_array($line) || (!empty($line) && (array_keys($line) === range(0, count($line) - 1)))) {
            api_fail_validation(lang('Each line must be an object.'), 'lines[' . $index . ']');
        }

        foreach (array_keys($line) as $key) {
            if (!in_array((string) $key, $known, true)) {
                api_fail_validation(lang(array('string' => 'A line does not take a field called {var:1}.', 'vars' => mb_substr((string) $key, 0, 60))), $field(mb_substr((string) $key, 0, 60)));
            }
        }

        $given = function ($name) use ($line) {
            return array_key_exists($name, $line) && ($line[$name] !== null) && ($line[$name] !== '');
        };

        $number = function ($name, $min, $max) use ($line, $field, $given) {
            if (!$given($name)) {
                return null;
            }

            $value = erp_api_line_number($line[$name]);

            if ($value === null) {
                api_fail_validation(lang(array('string' => '{var:1} must be a number.', 'vars' => $field($name))), $field($name));
            }

            if (($value < $min) || ($value > $max)) {
                api_fail_validation(lang(array('string' => '{var:1} must be between {var:2} and {var:3}.', 'vars' => array($field($name), $min, $max))), $field($name));
            }

            return $value;
        };

        $text = function ($name, $max) use ($line, $field, $given) {
            if (!$given($name)) {
                return '';
            }

            if (!is_string($line[$name]) && !is_int($line[$name])) {
                api_fail_validation(lang(array('string' => '{var:1} must be text.', 'vars' => $field($name))), $field($name));
            }

            $value = trim((string) $line[$name]);

            if (mb_strlen($value) > $max) {
                api_fail_validation(lang(array('string' => '{var:1} is longer than the {var:2} characters accepted.', 'vars' => array($field($name), $max))), $field($name));
            }

            return $value;
        };

        $product_id = 0;

        if ($given('product_id')) {
            if (is_bool($line['product_id']) || !preg_match('/^\d+$/', (string) $line['product_id'])) {
                api_fail_validation(lang(array('string' => '{var:1} must be a whole number.', 'vars' => $field('product_id'))), $field('product_id'));
            }

            $product_id = (int) $line['product_id'];
        }

        // Minor units, never a decimal: the same rule as every money field of
        // the API, because 19.99 sent where 1999 was meant is a price a
        // hundred times too small.
        if (!$given('unit_price')) {
            api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => $field('unit_price'))), $field('unit_price'));
        }

        $price = $line['unit_price'];

        if (is_bool($price) || !is_numeric($price) || (strpos((string) $price, '.') !== false) || (strpos((string) $price, ',') !== false)) {
            api_fail_validation(lang(array('string' => '{var:1} must be a whole number of minor units, with no decimal separator.', 'vars' => $field('unit_price'))), $field('unit_price'));
        }

        $tax2_rate = $number('tax2_rate', 0, 100);

        if (($tax2_rate !== null) && ($tax2_rate > 0) && !erp_tax2_enabled()) {
            api_fail_validation(lang('The store names no second tax, so a line cannot carry one.'), $field('tax2_rate'));
        }

        $quantity = $number('quantity', 0, 999999999);

        $lines[$index] = array(
            'product_id' => $product_id,
            'description' => $text('description', 255),
            'quantity' => ($quantity === null) ? 1.0 : $quantity,
            'unit_code' => strtoupper($text('unit', 10)),
            'unit_price' => (int) $price,
            'offer_id' => 0,
            'offer_discount_rate' => 0.0,
            'discount_rate' => (float) $number('discount_rate', 0, 100),
            'tax_rate' => $number('tax_rate', 0, 100),
            'tax2_rate' => (float) $tax2_rate,
            'withholding_code' => $text('withholding_code', 10),
            'withholding_rate' => (float) $number('withholding_rate', 0, 100),
        );

        if ($product_id > 0) {
            $product_ids[$product_id] = true;
        }
    }

    $products = array();

    if (!empty($product_ids)) {
        foreach ((array) db_items("SELECT " . erp_product_columns('p') . " FROM products p WHERE p.id IN (" . implode(',', array_keys($product_ids)) . ")") as $row) {
            $products[(int) $row['id']] = $row;
        }
    }

    $out = array();

    foreach ($lines as $index => $line) {
        $field = 'lines[' . $index . '].';

        if ($line['product_id'] > 0) {
            if (!isset($products[$line['product_id']])) {
                api_fail_validation(lang('That product could not be found.'), $field . 'product_id');
            }

            if (($line['description'] === '') || ($line['tax_rate'] === null)) {
                $product = erp_product_for_line($products[$line['product_id']], $account_id);

                if ($line['description'] === '') {
                    $line['description'] = mb_substr((string) $product['name'], 0, 255);
                }

                if ($line['tax_rate'] === null) {
                    $line['tax_rate'] = (float) $product['tax_rate'];
                }
            }
        }

        if ($line['tax_rate'] === null) {
            api_fail_validation(lang(array('string' => '{var:1} is required.', 'vars' => $field . 'tax_rate')), $field . 'tax_rate');
        }

        $line['tax_rate'] = (float) $line['tax_rate'];
        $out[] = $line;
    }

    return $out;
}

/**
 * The API's name for a field the ERP refused: a line field in the
 * lines[0][unit_code] shape the panel form posts becomes lines[0].unit.
 *
 * @param string $field
 * @param array  $names  ERP name => API name, for the header
 * @return string|null
 */
function erp_api_document_field($field, $names = array())
{
    $field = (string) $field;

    if (($field === '') || ($field === '_error')) {
        return null;
    }

    if (preg_match('/^lines\[(\d+)\]\[([a-z0-9_]+)\]$/', $field, $match)) {
        $line_names = array('unit_code' => 'unit', 'offer_discount_rate' => 'discount_rate');

        return 'lines[' . $match[1] . '].' . ($line_names[$match[2]] ?? $match[2]);
    }

    return $names[$field] ?? $field;
}

/**
 * The dates, the currency and the rate of a draft or a quote, from what was
 * sent over what the document holds now.
 *
 * The rate is 1 in the store currency. In another one it is the rate sent,
 * marked as the API's; otherwise the rate recorded for the date, or none
 * yet, in which case issuing looks again. A document whose currency, rate
 * and date were left alone keeps the rate it has.
 *
 * @param array  $params      The route's parameters
 * @param array  $kept        The stored document, empty for a new one
 * @param string $due_param   The parameter carrying the second date
 * @param string $due_column  The column the second date is kept in
 * @return array issue_date, due_date, currency, exchange_rate, exchange_rate_date, exchange_rate_source
 */
function erp_api_document_header($params, $kept, $due_param, $due_column)
{
    $sent = function ($name) use ($params) {
        return array_key_exists($name, $params);
    };

    $base = erp_base_currency();
    $currency = $sent('currency') ? strtoupper(trim((string) $params['currency'])) : strtoupper(trim((string) ($kept['currency'] ?? '')));
    $currency = ($currency !== '') ? $currency : $base;

    // The save would refuse a currency the ERP does not allow; said with
    // the field it came in.
    if (($currency !== $base) && (!erp_fx_enabled() || !erp_fx_currency_allowed($currency))) {
        api_fail_validation(lang('That currency is not enabled for the ERP.'), 'currency');
    }

    if ($sent('issue_date')) {
        $issue_date = erp_api_day($params['issue_date']);
    } else {
        $issue_date = ((string) ($kept['issue_date'] ?? '') > '0000-00-00') ? (string) $kept['issue_date'] : date('Y-m-d');
    }

    $due_date = '';

    if ($sent($due_param)) {
        $due_date = ((string) $params[$due_param] === '') ? '' : erp_api_day($params[$due_param]);
    } elseif ((string) ($kept[$due_column] ?? '') > '0000-00-00') {
        $due_date = (string) $kept[$due_column];
    }

    $rate = array('rate' => 1.0, 'date' => $issue_date, 'source' => 'base');

    if ($currency !== $base) {
        $touched = empty($kept) || $sent('currency') || $sent('exchange_rate') || $sent('issue_date');

        if (!$touched) {
            $rate = array(
                'rate' => (float) ($kept['exchange_rate'] ?? 0),
                'date' => (string) ($kept['exchange_rate_date'] ?? $issue_date),
                'source' => (string) ($kept['exchange_rate_source'] ?? ''),
            );
        } elseif ((float) ($params['exchange_rate'] ?? 0) > 0) {
            $rate = array('rate' => (float) $params['exchange_rate'], 'date' => $issue_date, 'source' => 'api');
        } else {
            $recorded = erp_fx_rate_for($currency, $issue_date);
            $rate = is_array($recorded)
                ? array('rate' => (float) $recorded['rate'], 'date' => (string) $recorded['rate_date'], 'source' => (string) $recorded['source'])
                : array('rate' => 0.0, 'date' => $issue_date, 'source' => '');
        }
    }

    return array(
        'issue_date' => $issue_date,
        'due_date' => $due_date,
        'currency' => $currency,
        'exchange_rate' => $rate['rate'],
        'exchange_rate_date' => $rate['date'],
        'exchange_rate_source' => $rate['source'],
    );
}

/**
 * Check a typed document the way the save will, and answer 422 with the
 * field where it would refuse.
 *
 * @param array $data   What erp_invoice_draft_save() or erp_quote_save() takes
 * @param array $names  ERP field name => API name
 * @return array  erp_manual_lines_build()'s answer
 */
function erp_api_document_check($data, $names = array())
{
    $header = erp_manual_header_build($data);

    if ($header['error'] !== '') {
        api_fail_validation($header['error'], erp_api_document_field($header['field'], $names));
    }

    $built = erp_manual_lines_build($data['lines'] ?? array());

    if ($built['error'] !== '') {
        api_fail_validation($built['error'], erp_api_document_field($built['field'], $names));
    }

    if (empty($built['lines'])) {
        api_fail_validation(lang('Enter at least one line.'), 'lines');
    }

    return $built;
}

/* ---------------------------------------------------------------------------
   Invoices
   --------------------------------------------------------------------------- */

/**
 * Answer with one invoice, a draft included.
 *
 * @param int $invoice_id
 * @param int $status
 */
function erp_api_invoice_answer($invoice_id, $status = 200)
{
    $row = erp_api_invoice_row((int) $invoice_id, true);
    $lines = erp_api_invoice_lines(array((int) $row['id']));

    api_ok(erp_api_invoice_present($row, $lines[(int) $row['id']] ?? array()), $status);
}

/**
 * The invoice row a write is about, or a 404. Drafts are found.
 *
 * @param int $invoice_id
 * @return array  erp_invoices row
 */
function erp_api_invoice_for_write($invoice_id)
{
    $row = db_item("SELECT * FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");

    if (!is_array($row)) {
        api_fail_not_found(lang('Invoice'));
    }

    return $row;
}

/**
 * A draft as erp_invoice_draft_save() takes it, from what was sent over the
 * draft as it is kept.
 *
 * @param array $params  The route's parameters
 * @param array $kept    The draft's row, empty for a new one
 * @return array
 */
function erp_api_invoice_draft_in($params, $kept)
{
    $owner = erp_api_owner();

    $sent = function ($name) use ($params) {
        return array_key_exists($name, $params);
    };

    $direction = $sent('direction') ? (string) $params['direction'] : (string) ($kept['direction'] ?? 'sales');
    $direction = ($direction === 'purchase') ? 'purchase' : 'sales';

    // The supplier's own number and date belong to a purchase bill; on a
    // sale they would be dropped, so they are refused instead.
    if ($direction !== 'purchase') {
        foreach (array('supplier_invoice_no', 'supplier_invoice_date') as $name) {
            if ($sent($name) && ((string) $params[$name] !== '')) {
                api_fail_validation(lang(array('string' => '{var:1} is only taken on a purchase invoice.', 'vars' => $name)), $name);
            }
        }
    }

    $account_id = $sent('account_id') ? (int) $params['account_id'] : (int) ($kept['account_id'] ?? 0);

    if (!is_array(erp_account($account_id))) {
        api_fail_validation(lang('Choose an account.'), 'account_id');
    }

    $header = erp_api_document_header($params, $kept, 'due_date', 'due_date');

    if ($sent('lines')) {
        $lines = erp_api_lines_in($params['lines'], $account_id);
    } else {
        $lines = empty($kept) ? array() : (array) db_items("SELECT * FROM erp_invoice_items
            WHERE invoice_id = '" . (int) $kept['id'] . "' ORDER BY line_no ASC, id ASC");
    }

    if ($sent('supplier_invoice_date')) {
        $supplier_date = ((string) $params['supplier_invoice_date'] === '') ? '' : erp_api_day($params['supplier_invoice_date']);
    } else {
        $supplier_date = ((string) ($kept['supplier_invoice_date'] ?? '') > '0000-00-00') ? (string) $kept['supplier_invoice_date'] : '';
    }

    return array_merge($header, array(
        'direction' => $direction,
        'account_id' => $account_id,
        'supplier_invoice_no' => $sent('supplier_invoice_no') ? (string) $params['supplier_invoice_no'] : (string) ($kept['supplier_invoice_no'] ?? ''),
        'supplier_invoice_date' => $supplier_date,
        'notes' => $sent('notes') ? (string) $params['notes'] : (string) ($kept['notes'] ?? ''),
        'lines' => $lines,
        'created_by' => (int) $owner['id'],
    ));
}

function erp_api_invoices_create($params)
{
    $data = erp_api_invoice_draft_in($params, array());
    $built = erp_api_document_check($data);

    api_dry_run_stop('created', 'erp_invoice', array(
        'status' => 'draft',
        'direction' => $data['direction'],
        'account_id' => $data['account_id'],
        'currency' => $data['currency'],
        'lines' => count($built['lines']),
        'grand_total' => (int) $built['totals']['grand_total'],
    ));

    $saved = erp_invoice_draft_save($data, 0);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error'], erp_api_document_field((string) ($saved['field'] ?? '')));
    }

    erp_api_log(lang(array(
        'string' => 'ERP invoice draft #{var:1} was written through the API.',
        'vars' => (int) $saved['invoice_id'],
    )));

    erp_api_invoice_answer((int) $saved['invoice_id'], 201);
}

function erp_api_invoices_update($params)
{
    $kept = erp_api_invoice_for_write((int) $params['id']);

    if ((string) $kept['status'] !== 'draft') {
        api_fail(409, 'invoice_not_draft', lang('Only a draft can be edited. An issued invoice is corrected with a return.'));
    }

    $data = erp_api_invoice_draft_in($params, $kept);
    $built = erp_api_document_check($data);

    api_dry_run_stop('updated', 'erp_invoice', array(
        'id' => (int) $kept['id'],
        'lines' => count($built['lines']),
        'grand_total' => (int) $built['totals']['grand_total'],
    ));

    $saved = erp_invoice_draft_save($data, (int) $kept['id']);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error'], erp_api_document_field((string) ($saved['field'] ?? '')));
    }

    erp_api_log(lang(array(
        'string' => 'ERP invoice draft #{var:1} was changed through the API.',
        'vars' => (int) $kept['id'],
    )));

    erp_api_invoice_answer((int) $kept['id']);
}

function erp_api_invoices_delete($params)
{
    $kept = erp_api_invoice_for_write((int) $params['id']);

    if ((string) $kept['status'] !== 'draft') {
        api_fail(409, 'invoice_not_draft', lang('Only a draft can be deleted. An issued invoice is cancelled instead.'));
    }

    api_dry_run_stop('deleted', 'erp_invoice', array('id' => (int) $kept['id']));

    $deleted = erp_invoice_draft_delete((int) $kept['id']);

    if (empty($deleted['success'])) {
        api_fail_validation((string) $deleted['error'], 'id');
    }

    erp_api_log(lang(array(
        'string' => 'ERP invoice draft #{var:1} was deleted through the API.',
        'vars' => (int) $kept['id'],
    )));

    api_ok(array('id' => (int) $kept['id'], 'deleted' => true));
}

function erp_api_invoices_issue($params)
{
    $invoice = erp_api_invoice_for_write((int) $params['id']);

    if ((string) $invoice['status'] !== 'draft') {
        api_fail(409, 'invoice_not_draft', lang('Only a draft can be issued.'));
    }

    // What erp_invoice_issue() refuses, asked in the same order before the
    // number is taken, so a rehearsal meets the same refusal as the call.
    $account = erp_account((int) $invoice['account_id']);

    if (!is_array($account)) {
        api_fail_validation(lang('Choose an account.'), 'account_id');
    }

    if ((string) $account['status'] !== 'active') {
        api_fail_validation(lang('That account is passive. Make it active before invoicing it.'), 'account_id');
    }

    $edoc_missing = erp_edoc_invoice_party_missing($invoice);

    if (!empty($edoc_missing['fields'])) {
        api_fail_validation(lang(array(
            'string' => '{var:1} will not take an invoice until these are on the account card: {var:2}. Fill the card in and issue it again — no number has been used.',
            'vars' => array($edoc_missing['label'], implode(', ', $edoc_missing['fields'])),
        )), 'account_id');
    }

    $rows = (array) db_items("SELECT * FROM erp_invoice_items WHERE invoice_id = '" . (int) $invoice['id'] . "' ORDER BY line_no ASC, id ASC");
    $built = erp_manual_lines_build($rows);

    if ($built['error'] !== '') {
        api_fail_validation($built['error'], erp_api_document_field($built['field']));
    }

    if (empty($built['lines'])) {
        api_fail_validation(lang('Enter at least one line.'), 'lines');
    }

    if (((int) ($built['totals']['tax2_total'] ?? 0) > 0) && (erp_edoc_active() !== '')) {
        api_fail_validation(lang(array('string' => 'The {var:1} on the lines cannot go on an e-document. Take it off, or switch the e-document provider off first.', 'vars' => erp_tax2_name())), 'lines');
    }

    if (($refusal = erp_lock_refusal((string) $invoice['issue_date'])) !== '') {
        api_fail(422, 'period_locked', $refusal, 'issue_date');
    }

    $base = erp_base_currency();
    $currency = strtoupper(trim((string) $invoice['currency']));
    $rate = ($currency === $base) ? 1.0 : (float) $invoice['exchange_rate'];

    if (($currency !== $base) && ($rate <= 0)) {
        $recorded = erp_fx_rate_for($currency, (string) $invoice['issue_date']);

        if (!is_array($recorded)) {
            api_fail_validation(lang(array(
                'string' => 'No exchange rate is recorded for {var:1} on {var:2}. Set exchange_rate on the draft, or run Update Exchange Rates in the panel.',
                'vars' => array($currency, (string) $invoice['issue_date']),
            )), 'exchange_rate');
        }

        $rate = (float) $recorded['rate'];
    }

    $grand_total_base = ($currency === $base) ? (int) $built['totals']['grand_total'] : erp_to_base((int) $built['totals']['grand_total'], $rate);

    if (((string) $invoice['direction'] === 'sales') && function_exists('erp_credit_refusal')
        && (($refusal = erp_credit_refusal($account, (int) $grand_total_base)) !== '')) {
        api_fail(422, 'credit_limit', $refusal, 'account_id');
    }

    api_dry_run_stop('issued', 'erp_invoice', array(
        'id' => (int) $invoice['id'],
        'issue_date' => (string) $invoice['issue_date'],
        'currency' => $currency,
        'grand_total' => (int) $built['totals']['grand_total'],
    ));

    $issued = erp_invoice_issue((int) $invoice['id'], erp_api_owner()['id']);

    if (empty($issued['success'])) {
        api_fail_validation((string) $issued['error']);
    }

    erp_api_log(lang(array(
        'string' => 'ERP invoice {var:1} was issued through the API.',
        'vars' => (string) $issued['full_number'],
    )));

    erp_api_invoice_answer((int) $invoice['id']);
}

function erp_api_invoices_cancel($params)
{
    $invoice = erp_api_invoice_for_write((int) $params['id']);

    if ((string) $invoice['status'] === 'draft') {
        api_fail(409, 'invoice_is_draft', lang('A draft is not cancelled: it has no number and nothing was posted. Delete it instead.'));
    }

    if ((string) $invoice['status'] === 'cancelled') {
        api_fail(409, 'invoice_cancelled', lang('That invoice has been cancelled.'));
    }

    if (($refusal = erp_lock_refusal((string) $invoice['issue_date'])) !== '') {
        api_fail(422, 'period_locked', $refusal, 'id');
    }

    if (erp_invoice_receipt_count((int) $invoice['id']) > 0) {
        api_fail(409, 'invoice_has_payments', lang('Money has been allocated to this invoice, so it cannot be cancelled. Undo the settlement first.'));
    }

    if (!empty(erp_invoice_returns((int) $invoice['id']))) {
        api_fail(409, 'invoice_has_returns', lang('There is a return against this invoice, so it cannot be cancelled.'));
    }

    // A document the e-document provider holds is taken back there first,
    // which is a driver's call and the operator's confirmation on the
    // invoice screen. The API does not reach the provider: it refuses.
    $external_id = trim((string) ($invoice['edoc_external_id'] ?? ''));
    $edoc_status = (string) ($invoice['edoc_status'] ?? 'none');

    if (($external_id !== '') || !in_array($edoc_status, array('', 'none', 'error', 'rejected'), true)) {
        $provider = trim((string) ($invoice['edoc_provider'] ?? ''));
        $info = ($provider !== '') ? erp_edoc_info($provider) : null;

        api_fail(409, 'invoice_at_provider', lang(array(
            'string' => '{var:1} has this document, so it is taken back from the invoice screen in the panel, or corrected with a return.',
            'vars' => is_array($info) ? (string) $info['label'] : lang('The e-document provider'),
        )));
    }

    api_dry_run_stop('cancelled', 'erp_invoice', array('id' => (int) $invoice['id'], 'number' => (string) $invoice['full_number']));

    $cancelled = erp_invoice_cancel((int) $invoice['id'], erp_api_owner()['id']);

    if (empty($cancelled['success'])) {
        api_fail_validation((string) $cancelled['error'], 'id');
    }

    erp_api_log(lang(array(
        'string' => 'ERP invoice {var:1} was cancelled through the API.',
        'vars' => (string) $invoice['full_number'],
    )));

    erp_api_invoice_answer((int) $invoice['id']);
}

function erp_api_invoices_return($params)
{
    $invoice = erp_api_invoice_for_write((int) $params['id']);

    if ((string) $invoice['status'] === 'draft') {
        api_fail(409, 'invoice_is_draft', lang('A draft is not a document yet; issue it first.'));
    }

    if ((string) $invoice['status'] === 'cancelled') {
        api_fail(409, 'invoice_cancelled', lang('That invoice has been cancelled.'));
    }

    if ((string) $invoice['doc_type'] !== 'invoice') {
        api_fail_validation(lang('Only an invoice can be returned.'), 'id');
    }

    $all = !empty($params['all']);
    $sent_lines = array_key_exists('lines', $params) && !empty($params['lines']);

    if ($all && $sent_lines) {
        api_fail_validation(lang('Send either lines or all true, not both.'), 'lines');
    }

    if (!$all && !$sent_lines) {
        api_fail_validation(lang('Send the lines that come back, or all true.'), 'lines');
    }

    $returnable = erp_returnable_lines((int) $invoice['id']);
    $quantities = array();

    if ($all) {
        foreach ($returnable as $line) {
            if ((float) $line['remaining_qty'] > 0.00001) {
                $quantities[(int) $line['id']] = (float) $line['remaining_qty'];
            }
        }

        if (empty($quantities)) {
            api_fail_validation(lang('Everything on this invoice has already been returned.'), 'all');
        }
    } else {
        $on_invoice = array();

        foreach ($returnable as $line) {
            $on_invoice[(int) $line['id']] = true;
        }

        foreach (array_values((array) $params['lines']) as $index => $line) {
            $field = 'lines[' . $index . ']';

            if (!is_array($line) || !array_key_exists('line_id', $line) || !array_key_exists('quantity', $line)) {
                api_fail_validation(lang('Each line must be an object of {line_id, quantity}.'), $field);
            }

            foreach (array_keys($line) as $key) {
                if (!in_array((string) $key, array('line_id', 'quantity'), true)) {
                    api_fail_validation(lang(array('string' => 'A line does not take a field called {var:1}.', 'vars' => mb_substr((string) $key, 0, 60))), $field . '.' . mb_substr((string) $key, 0, 60));
                }
            }

            if (is_bool($line['line_id']) || !preg_match('/^\d+$/', (string) $line['line_id'])) {
                api_fail_validation(lang(array('string' => '{var:1} must be a whole number.', 'vars' => $field . '.line_id')), $field . '.line_id');
            }

            $line_id = (int) $line['line_id'];

            if (!isset($on_invoice[$line_id])) {
                api_fail_validation(lang('That line is not on this invoice.'), $field . '.line_id');
            }

            if (isset($quantities[$line_id])) {
                api_fail_validation(lang(array('string' => '{var:1} names the same line twice.', 'vars' => 'lines')), $field . '.line_id');
            }

            $quantity = erp_api_line_number($line['quantity']);

            if (($quantity === null) || ($quantity <= 0)) {
                api_fail_validation(lang(array('string' => '{var:1} must be a number greater than zero.', 'vars' => $field . '.quantity')), $field . '.quantity');
            }

            $quantities[$line_id] = $quantity;
        }
    }

    $built = erp_return_build($returnable, $quantities);

    if ($built['error'] !== '') {
        api_fail_validation($built['error'], 'lines');
    }

    $issue_date = isset($params['issue_date']) ? erp_api_day($params['issue_date']) : date('Y-m-d');

    if (($refusal = erp_lock_refusal($issue_date)) !== '') {
        api_fail(422, 'period_locked', $refusal, 'issue_date');
    }

    api_dry_run_stop('created', 'erp_invoice_return', array(
        'invoice_id' => (int) $invoice['id'],
        'issue_date' => $issue_date,
        'lines' => count($built['lines']),
        'full' => (bool) $built['is_full'],
        'grand_total' => $built['is_full'] ? (int) $invoice['grand_total'] : (int) $built['totals']['grand_total'],
    ));

    $returned = erp_invoice_return(array(
        'invoice_id' => (int) $invoice['id'],
        'quantities' => $quantities,
        'issue_date' => $issue_date,
        'created_by' => erp_api_owner()['id'],
    ));

    if (empty($returned['success'])) {
        api_fail_validation((string) $returned['error']);
    }

    erp_api_log(lang(array(
        'string' => 'ERP return {var:1} was raised against invoice {var:2} through the API.',
        'vars' => array((string) $returned['full_number'], (string) $invoice['full_number']),
    )));

    erp_api_invoice_answer((int) $returned['invoice_id'], 201);
}

function erp_api_invoices_email($params)
{
    $invoice = erp_mail_invoice((int) $params['id']);

    if ($invoice === null) {
        api_fail_not_found(lang('Invoice'));
    }

    if (!erp_mail_ready()) {
        api_fail(503, 'service_unavailable', lang('Sending invoices by e-mail comes with the software update; run the update to use it.'));
    }

    if (($refusal = erp_mail_invoice_refusal($invoice)) !== '') {
        api_fail(409, 'invoice_not_sendable', $refusal);
    }

    $to = trim((string) ($params['to'] ?? ''));
    $to = ($to !== '') ? $to : erp_mail_invoice_recipient($invoice);
    $addresses = erp_mail_addresses($to);

    if ($addresses['error'] !== '') {
        api_fail_validation($addresses['error'], 'to');
    }

    if (erp_reconciliation_sender() === '') {
        api_fail_validation(lang('The store has no e-mail address to send from. Set one under Settings.'));
    }

    $official = erp_mail_invoice_official($invoice);

    api_dry_run_stop('sent', 'erp_invoice_email', array(
        'invoice_id' => (int) $invoice['id'],
        'to' => $addresses['addresses'],
        'official' => $official,
    ));

    $sent = erp_mail_invoice_send((int) $invoice['id'], implode(', ', $addresses['addresses']),
        (string) ($params['subject'] ?? ''), (string) ($params['message'] ?? ''), erp_api_owner()['id']);

    if (empty($sent['success'])) {
        api_fail(503, 'service_unavailable', (string) $sent['error']);
    }

    erp_api_log(lang(array(
        'string' => 'ERP invoice {var:1} was e-mailed to {var:2} through the API.',
        'vars' => array((string) $invoice['full_number'], implode(', ', $addresses['addresses'])),
    )));

    api_ok(array(
        'invoice_id' => (int) $invoice['id'],
        'to' => $addresses['addresses'],
        'official' => (bool) $sent['official'],
    ));
}

function erp_api_orders_invoice($params)
{
    $order = db_item("SELECT * FROM orders WHERE id = '" . (int) $params['order_id'] . "' LIMIT 1");

    if (!is_array($order)) {
        api_fail_not_found(lang('Order'));
    }

    if (erp_invoice_for_order((int) $order['id']) > 0) {
        api_fail(409, 'order_already_invoiced', lang('This order has already been invoiced.'));
    }

    erp_api_order_invoice_check($order, 'order_id');

    api_dry_run_stop('created', 'erp_invoice', array('order_id' => (int) $order['id'], 'issue_date' => date('Y-m-d')));

    $made = erp_invoice_from_order((int) $order['id'], array('created_by' => erp_api_owner()['id']));

    if (empty($made['success'])) {
        api_fail_validation((string) $made['error'], 'order_id');
    }

    erp_api_log(lang(array(
        'string' => 'ERP invoice {var:1} was issued from order #{var:2} through the API.',
        'vars' => array((string) $made['full_number'], (int) $order['id']),
    )));

    erp_api_invoice_answer((int) $made['invoice_id'], 201);
}

/**
 * What erp_invoice_from_order() refuses before it writes, asked first so a
 * rehearsal meets it too, and answered 422 at $field. The account itself is
 * only opened by the call, from the customer record, which has to be there.
 *
 * @param array       $order  The orders row
 * @param string|null $field  The parameter the order came in, null when the
 *                            route named something else (a delivery note)
 * @return void
 */
function erp_api_order_invoice_check($order, $field = null)
{
    if (!in_array((string) $order['status'], erp_order_billable_statuses(), true)) {
        api_fail_validation(lang('An order is invoiced once it is complete.'), $field);
    }

    $items = (array) db_items("SELECT order_items.*, products.short_description,
            products.tax_rate AS product_tax_rate
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = '" . (int) $order['id'] . "' AND order_items.saved_for_later = 0
        ORDER BY order_items.id ASC");

    if (empty($items)) {
        api_fail_validation(lang('This order has no items.'), $field);
    }

    if (((int) ($order['erp_account_id'] ?? 0) <= 0)
        && ((int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . (int) $order['contact_id'] . "' AND contact_id > 0 LIMIT 1") <= 0)
        && (((int) $order['contact_id'] <= 0) || (erp_account_data_from_contact((int) $order['contact_id']) === null))) {
        api_fail_validation(((int) $order['contact_id'] > 0)
            ? lang('The contact this order belongs to no longer exists, so there is no account to bill.')
            : lang('This order is not linked to a contact, so there is no account to bill.'), $field);
    }

    $built = erp_order_lines($order, $items);
    $expected = (int) $order['total'] + (int) ($order['gift_card_discount'] ?? 0);

    if ((int) $built['totals']['grand_total'] !== $expected) {
        api_fail_validation(lang(array(
            'string' => 'The invoice total ({var:1}) does not match the order total ({var:2}). The invoice was not created.',
            'vars' => array(erp_money_out($built['totals']['grand_total']), erp_money_out($expected)),
        )), $field);
    }

    if (($refusal = erp_lock_refusal(date('Y-m-d'))) !== '') {
        api_fail(422, 'period_locked', $refusal, $field);
    }
}

/* ---------------------------------------------------------------------------
   Repeating invoices
   --------------------------------------------------------------------------- */

/**
 * A 503 for a store that has not run the update that brings the table
 * (4.93).
 */
function erp_api_invoice_recurrences_ready()
{
    if (!erp_invoice_recurring_ready()) {
        api_fail(503, 'service_unavailable', lang('Repeating invoices come with the software update; run the update to use them.'));
    }
}

function erp_api_invoice_recurrence_select()
{
    return "SELECT r.id, r.source_invoice_id, r.account_id, r.every_months, r.day_of_month, r.next_date, r.end_date,
            r.mode, r.grand_total, r.currency, r.occurrences, r.last_invoice_id, r.last_error, r.status,
            r.stopped_at, r.created_at, r.updated_at,
            a.title AS account_title, s.full_number AS source_number, l.full_number AS last_number
        FROM erp_invoice_recurrences r
        LEFT JOIN erp_accounts a ON a.id = r.account_id
        LEFT JOIN erp_invoices s ON s.id = r.source_invoice_id
        LEFT JOIN erp_invoices l ON l.id = r.last_invoice_id";
}

function erp_api_invoice_recurrence_present($row)
{
    return array(
        'id' => (int) $row['id'],
        'source_invoice_id' => (int) $row['source_invoice_id'],
        'source_number' => (string) ($row['source_number'] ?? ''),
        'account_id' => (int) $row['account_id'],
        'account_title' => (string) ($row['account_title'] ?? ''),
        'every_months' => (int) $row['every_months'],
        'day_of_month' => (int) $row['day_of_month'],
        'next_date' => erp_api_date($row['next_date']),
        'end_date' => erp_api_date($row['end_date']),
        'mode' => (string) $row['mode'],
        'currency' => (string) $row['currency'],
        'grand_total' => api_money($row['grand_total']),
        'occurrences' => (int) $row['occurrences'],
        'last_invoice_id' => (int) $row['last_invoice_id'],
        'last_number' => (string) ($row['last_number'] ?? ''),
        'last_error' => (string) $row['last_error'],
        'status' => (string) $row['status'],
        'stopped_at' => api_time($row['stopped_at']),
        'created_at' => api_time($row['created_at']),
        'updated_at' => api_time($row['updated_at']),
    );
}

// What erp_api_invoice_recurrence_present() returns.
function erp_api_invoice_recurrence_schema()
{
    return array(
        'id' => 'integer',
        'source_invoice_id' => 'integer',
        'source_number' => 'string',
        'account_id' => 'integer',
        'account_title' => 'string',
        'every_months' => 'integer',
        'day_of_month' => 'integer',
        'next_date' => 'string?',
        'end_date' => 'string?',
        'mode' => 'string',
        'currency' => 'string',
        'grand_total' => 'integer',
        'occurrences' => 'integer',
        'last_invoice_id' => 'integer',
        'last_number' => 'string',
        'last_error' => 'string',
        'status' => 'string',
        'stopped_at' => 'string?',
        'created_at' => 'string?',
        'updated_at' => 'string?',
    );
}

function erp_api_invoice_recurrence_answer($id, $status = 200)
{
    $row = db_item(erp_api_invoice_recurrence_select() . " WHERE r.id = '" . (int) $id . "' LIMIT 1");

    if (!is_array($row)) {
        api_fail_not_found(lang('Repeating invoice'));
    }

    api_ok(erp_api_invoice_recurrence_present($row), $status);
}

function erp_api_invoice_recurrences_list($params)
{
    erp_api_invoice_recurrences_ready();

    $where = array();

    if (isset($params['status'])) {
        $where[] = "r.status = '" . escape($params['status']) . "'";
    }

    if (isset($params['account_id'])) {
        $where[] = "r.account_id = '" . (int) $params['account_id'] . "'";
    }

    $page = erp_api_page(erp_api_invoice_recurrence_select(), $where, '', 'r.id', $params, "FROM erp_invoice_recurrences r");

    $out = array();

    foreach ($page['rows'] as $row) {
        $out[] = erp_api_invoice_recurrence_present($row);
    }

    api_ok_list($out, $page['limit'], $page['next_cursor'], $page['total']);
}

function erp_api_invoices_recurrence($params)
{
    erp_api_invoice_recurrences_ready();

    $invoice = erp_api_invoice_for_write((int) $params['id']);

    if (($refusal = erp_invoice_recurring_refusal($invoice)) !== '') {
        api_fail_validation($refusal, 'id');
    }

    if (erp_invoice_recurrence_of((int) $invoice['id']) !== null) {
        api_fail(409, 'invoice_already_repeats', lang('This invoice already repeats. Stop it first to start it again differently.'));
    }

    $every = (int) $params['every_months'];

    if (!array_key_exists($every, erp_expense_recurring_periods())) {
        api_fail_validation(lang(array('string' => '{var:1} must be one of: {var:2}', 'vars' => array('every_months', implode(', ', array_keys(erp_expense_recurring_periods()))))), 'every_months');
    }

    $first = erp_api_day($params['first_date']);

    if ($first <= (string) $invoice['issue_date']) {
        api_fail_validation(lang('The next invoice has to be dated after this one.'), 'first_date');
    }

    $end = isset($params['end_date']) ? erp_api_day($params['end_date']) : '';

    if (($end !== '') && ($end < $first)) {
        api_fail_validation(lang('The end date must come after the first date.'), 'end_date');
    }

    $mode = ((string) ($params['mode'] ?? 'draft') === 'issue') ? 'issue' : 'draft';

    api_dry_run_stop('created', 'erp_invoice_recurrence', array(
        'invoice_id' => (int) $invoice['id'],
        'every_months' => $every,
        'first_date' => $first,
        'end_date' => ($end !== '') ? $end : null,
        'mode' => $mode,
    ));

    $started = erp_invoice_recurrence_create((int) $invoice['id'], array(
        'every_months' => $every,
        'first_date' => $first,
        'end_date' => $end,
        'mode' => $mode,
    ), erp_api_owner()['id']);

    if (empty($started['success'])) {
        api_fail_validation((string) $started['error'], erp_api_document_field((string) $started['field'],
            array('recur_every' => 'every_months', 'recur_first' => 'first_date', 'recur_end' => 'end_date')));
    }

    erp_api_log(lang(array(
        'string' => 'ERP invoice {var:1} was set to repeat through the API.',
        'vars' => (string) $invoice['full_number'],
    )));

    erp_api_invoice_recurrence_answer((int) $started['id'], 201);
}

function erp_api_invoice_recurrences_stop($params)
{
    erp_api_invoice_recurrences_ready();

    $recurrence = erp_invoice_recurrence((int) $params['id']);

    if ($recurrence === null) {
        api_fail_not_found(lang('Repeating invoice'));
    }

    if ((string) $recurrence['status'] !== 'active') {
        api_fail(409, 'recurrence_stopped', lang('That repeating invoice has already been stopped.'));
    }

    api_dry_run_stop('stopped', 'erp_invoice_recurrence', array('id' => (int) $recurrence['id']));

    // Another request may have stopped it in between; either way it is
    // stopped now, which is what was asked.
    erp_invoice_recurrence_stop((int) $recurrence['id'], erp_api_owner()['id']);

    erp_api_log(lang(array(
        'string' => 'Repeating ERP invoice #{var:1} was stopped through the API.',
        'vars' => (int) $recurrence['id'],
    )));

    erp_api_invoice_recurrence_answer((int) $recurrence['id']);
}

/* ---------------------------------------------------------------------------
   Quotes
   --------------------------------------------------------------------------- */

/**
 * The quote a call is about, or a 404.
 *
 * @param int $quote_id
 * @return array  From erp_quote()
 */
function erp_api_quote_for_write($quote_id)
{
    erp_api_quotes_ready();

    $quote = erp_quote((int) $quote_id);

    if ($quote === null) {
        api_fail_not_found(lang('Sales quote'));
    }

    return $quote;
}

function erp_api_quote_answer($quote_id, $status = 200)
{
    $quote = erp_api_quote_for_write($quote_id);
    $lines = erp_api_quote_lines(array($quote));

    api_ok(erp_api_quote_present($quote, $lines[(int) $quote['id']] ?? array(), erp_signature('quote', (int) $quote['id'])), $status);
}

/**
 * A quote as erp_quote_save() takes it - the shape the panel's form reads -
 * from what was sent over the quote as it is kept.
 *
 * @param array $params
 * @param array $quote  From erp_quote(), empty for a new quote
 * @return array
 */
function erp_api_quote_in($params, $quote)
{
    $owner = erp_api_owner();
    $kept = array();

    if (!empty($quote)) {
        $form = json_decode((string) ($quote['form_data'] ?? ''), true);
        $kept = array_merge(is_array($form) ? $form : array(), array(
            'account_id' => (int) $quote['account_id'],
            'issue_date' => (string) $quote['issue_date'],
            'valid_until' => (string) $quote['valid_until'],
            'currency' => (string) $quote['currency'],
            'exchange_rate' => (float) $quote['exchange_rate'],
            'notes' => (string) $quote['notes'],
        ));
    }

    $sent = function ($name) use ($params) {
        return array_key_exists($name, $params);
    };

    $account_id = $sent('account_id') ? (int) $params['account_id'] : (int) ($kept['account_id'] ?? 0);

    if (!is_array(erp_account($account_id))) {
        api_fail_validation(lang('Choose an account.'), 'account_id');
    }

    $header = erp_api_document_header($params, $kept, 'valid_until', 'valid_until');

    if ($sent('lines')) {
        $lines = erp_api_lines_in($params['lines'], $account_id);
    } else {
        $lines = (isset($kept['lines']) && is_array($kept['lines'])) ? $kept['lines'] : erp_quote_lines($quote);
    }

    if (($header['due_date'] !== '') && ($header['due_date'] < $header['issue_date'])) {
        api_fail_validation(lang('The quote cannot run out before its date.'), 'valid_until');
    }

    return array_merge($header, array(
        'direction' => 'sales',
        'account_id' => $account_id,
        'supplier_invoice_no' => '',
        'supplier_invoice_date' => '',
        'notes' => $sent('notes') ? (string) $params['notes'] : (string) ($kept['notes'] ?? ''),
        'lines' => $lines,
        'created_by' => (int) $owner['id'],
    ));
}

function erp_api_quotes_document($params)
{
    $quote = erp_api_quote_for_write((int) $params['id']);

    $html = erp_quote_html((int) $quote['id']);

    if ($html === false) {
        api_fail_not_found(lang('Sales quote'));
    }

    $pdf = erp_invoice_pdf($html);

    if ($pdf === false) {
        api_fail(503, 'service_unavailable', lang('The PDF library is not installed.'));
    }

    api_ok(erp_api_document_present(erp_quote_file_name($quote) . '.pdf', 'application/pdf', $pdf));
}

function erp_api_quotes_create($params)
{
    erp_api_quotes_ready();

    $data = erp_api_quote_in($params, array());
    $built = erp_api_document_check($data, array('due_date' => 'valid_until'));

    api_dry_run_stop('created', 'erp_quote', array(
        'account_id' => $data['account_id'],
        'currency' => $data['currency'],
        'lines' => count($built['lines']),
        'grand_total' => (int) $built['totals']['grand_total'],
    ));

    $saved = erp_quote_save($data, 0, erp_api_owner()['id']);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error'], erp_api_document_field((string) $saved['field'], array('due_date' => 'valid_until')));
    }

    erp_api_log(lang(array(
        'string' => 'ERP quote {var:1} was written through the API.',
        'vars' => (string) $saved['full_number'],
    )));

    erp_api_quote_answer((int) $saved['quote_id'], 201);
}

function erp_api_quotes_update($params)
{
    $quote = erp_api_quote_for_write((int) $params['id']);

    if ((string) $quote['status'] !== 'open') {
        api_fail(409, 'quote_not_open', lang('Only an open quote can be changed. Open it again first, or write a new one.'));
    }

    $data = erp_api_quote_in($params, $quote);
    $built = erp_api_document_check($data, array('due_date' => 'valid_until'));

    api_dry_run_stop('updated', 'erp_quote', array(
        'id' => (int) $quote['id'],
        'lines' => count($built['lines']),
        'grand_total' => (int) $built['totals']['grand_total'],
    ));

    $saved = erp_quote_save($data, (int) $quote['id'], erp_api_owner()['id']);

    if (empty($saved['success'])) {
        api_fail_validation((string) $saved['error'], erp_api_document_field((string) $saved['field'], array('due_date' => 'valid_until')));
    }

    erp_api_log(lang(array(
        'string' => 'ERP quote {var:1} was changed through the API.',
        'vars' => (string) $quote['full_number'],
    )));

    erp_api_quote_answer((int) $quote['id']);
}

function erp_api_quotes_status($params)
{
    $quote = erp_api_quote_for_write((int) $params['id']);
    $to = (string) $params['status'];

    if (($refusal = erp_quote_status_refusal($quote, $to)) !== '') {
        api_fail(409, 'quote_status_not_allowed', $refusal);
    }

    api_dry_run_stop('updated', 'erp_quote', array('id' => (int) $quote['id'], 'from' => erp_quote_state($quote), 'to' => $to));

    $moved = erp_quote_set_status((int) $quote['id'], $to, erp_api_owner()['id']);

    if (empty($moved['success'])) {
        api_fail(409, 'quote_status_not_allowed', (string) $moved['error']);
    }

    erp_api_log(lang(array(
        'string' => 'ERP quote {var:1} was marked {var:2} through the API.',
        'vars' => array((string) $quote['full_number'], $to),
    )));

    erp_api_quote_answer((int) $quote['id']);
}

function erp_api_quotes_invoice($params)
{
    $quote = erp_api_quote_for_write((int) $params['id']);
    $state = erp_quote_state($quote);

    if (!in_array($state, array('open', 'expired', 'accepted'), true)) {
        api_fail(409, 'quote_not_invoiceable', ($state === 'invoiced')
            ? lang('The quote has already become an invoice.')
            : lang('A rejected or cancelled quote is not invoiced. Open it again first.'));
    }

    if (!is_array(json_decode((string) $quote['form_data'], true))) {
        api_fail_validation(lang('The quote\'s lines could not be read.'), 'id');
    }

    if (!is_array(erp_account((int) $quote['account_id']))) {
        api_fail_validation(lang('Choose an account.'), 'id');
    }

    api_dry_run_stop('created', 'erp_invoice', array('status' => 'draft', 'quote_id' => (int) $quote['id'], 'issue_date' => date('Y-m-d')));

    $made = erp_quote_to_invoice((int) $quote['id'], erp_api_owner()['id']);

    if (empty($made['success'])) {
        api_fail_validation((string) $made['error'], 'id');
    }

    erp_api_log(lang(array(
        'string' => 'ERP quote {var:1} was turned into invoice draft #{var:2} through the API.',
        'vars' => array((string) $quote['full_number'], (int) $made['invoice_id']),
    )));

    erp_api_invoice_answer((int) $made['invoice_id'], 201);
}

function erp_api_quotes_email($params)
{
    $quote = erp_api_quote_for_write((int) $params['id']);

    if (!erp_mail_ready()) {
        api_fail(503, 'service_unavailable', lang('Sending documents by e-mail comes with the software update; run the update to use it.'));
    }

    if (in_array(erp_quote_state($quote), array('cancelled', 'rejected'), true)) {
        api_fail(409, 'quote_not_sendable', lang('A rejected or cancelled quote is not sent. Open it again first.'));
    }

    $to = trim((string) ($params['to'] ?? ''));
    $to = ($to !== '') ? $to : erp_quote_mail_defaults($quote)['to'];
    $addresses = erp_mail_addresses($to);

    if ($addresses['error'] !== '') {
        api_fail_validation($addresses['error'], 'to');
    }

    if (erp_reconciliation_sender() === '') {
        api_fail_validation(lang('The store has no e-mail address to send from. Set one under Settings.'));
    }

    api_dry_run_stop('sent', 'erp_quote_email', array('quote_id' => (int) $quote['id'], 'to' => $addresses['addresses']));

    $sent = erp_quote_mail_send((int) $quote['id'], implode(', ', $addresses['addresses']),
        (string) ($params['subject'] ?? ''), (string) ($params['message'] ?? ''), erp_api_owner()['id']);

    if (empty($sent['success'])) {
        api_fail(503, 'service_unavailable', (string) $sent['error']);
    }

    erp_api_log(lang(array(
        'string' => 'ERP quote {var:1} was e-mailed to {var:2} through the API.',
        'vars' => array((string) $quote['full_number'], implode(', ', $addresses['addresses'])),
    )));

    api_ok(array('quote_id' => (int) $quote['id'], 'to' => $addresses['addresses']));
}
