<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - an invoice typed in by hand.
 *
 * The order bridge raises an invoice from what the shop sold; this raises one
 * from what an operator types - a sale outside the shop, or a supplier's
 * invoice - in the base currency or in one the ERP allows.
 *
 * A typed invoice has two states. A draft is the header and its lines saved
 * so the operator can come back to them: no document number is taken and
 * nothing is posted to the account. Issuing turns the draft into a document:
 * the number, the recomputed totals and the movement on the account land in
 * one transaction, the same way the bridge does it. An issued invoice is not
 * edited afterwards.
 *
 * The figures are worked out here once, through the money helpers, and the
 * PHP is the authority: whatever a screen shows while the lines are being
 * typed is a preview of what this file will compute.
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
 * The pseudo-series a draft is filed under.
 *
 * uniq_number(direction, series, number, issue_year) would let only one
 * blank-numbered row exist, so a draft carries this series, issue year 0 and
 * its own id as the number. No real series may be given this name; issuing
 * refuses to spend a number from it.
 */
if (!defined('ERP_DRAFT_SERIES')) {
    define('ERP_DRAFT_SERIES', '_D');
}

/** The most lines one typed invoice may carry. */
if (!defined('ERP_MANUAL_MAX_LINES')) {
    define('ERP_MANUAL_MAX_LINES', 200);
}

/**
 * Work out the lines and the totals of a typed invoice.
 *
 * Pure: reads nothing and writes nothing, so a screen can show the result
 * before anything is saved and the same call is made again when a draft is
 * issued. Rows with nothing typed in them are skipped rather than refused.
 *
 * Per line two discounts may apply, in this order: the store's campaign on
 * the product (offer_discount_rate, a rate on the line total) and the
 * discount the operator typed (discount_rate, a rate on what the campaign
 * left). discount_amount is their sum, so every reader of the amount - the
 * document, the return, the export - sees one discount. The VAT is worked
 * out on what is left after both. The document totals follow the contract
 * the order bridge writes: subtotal is the sum of the line totals before any
 * discount, discount_total the sum of the discounts, tax_total the sum of the
 * VAT, and grand_total = subtotal - discount_total + tax_total.
 *
 * A refused line says which field on which row is wrong ('field', in the
 * lines[n][name] shape the form posts), so the screen can mark it.
 *
 * @param array $lines_in  Each: description, quantity, unit_price (kurus),
 *                         tax_rate, and optionally product_id, unit_code,
 *                         discount_rate, offer_id, offer_discount_rate
 * @return array ['lines' => array, 'totals' => array, 'error' => string, 'field' => string]
 */
function erp_manual_lines_build($lines_in)
{
    $fail = function ($message, $field = '_error') {
        return array('lines' => array(), 'totals' => array(), 'error' => $message, 'field' => $field);
    };

    $lines_in = array_values((array) $lines_in);

    if (count($lines_in) > ERP_MANUAL_MAX_LINES) {
        return $fail(lang(array('string' => 'An invoice can carry at most {var:1} lines.', 'vars' => ERP_MANUAL_MAX_LINES)));
    }

    $lines = array();
    $subtotal = 0;
    $discount_total = 0;
    $tax_total = 0;
    $row_no = 0;

    foreach ($lines_in as $index => $line) {
        $row_no++;

        // The posted name of a field on this row, for the screen to mark.
        $field = function ($name) use ($index) {
            return 'lines[' . $index . '][' . $name . ']';
        };

        $description = mb_substr(trim((string) ($line['description'] ?? '')), 0, 255);
        $quantity = (float) ($line['quantity'] ?? 0);
        $unit_price = (int) ($line['unit_price'] ?? 0);
        $tax_rate = (float) ($line['tax_rate'] ?? 0);
        $discount_rate = (float) ($line['discount_rate'] ?? 0);
        $offer_rate = (float) ($line['offer_discount_rate'] ?? 0);
        $offer_id = (int) ($line['offer_id'] ?? 0);
        $product_id = (int) ($line['product_id'] ?? 0);

        $unit_code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($line['unit_code'] ?? '')));
        $unit_code = ($unit_code === '') ? 'C62' : substr($unit_code, 0, 10);

        // A row with nothing typed in it is an empty row, not an error.
        if (($description === '') && ($quantity == 0) && ($unit_price === 0) && ($product_id === 0)) {
            continue;
        }

        if ($description === '') {
            return $fail(lang(array('string' => 'Line {var:1} needs a description.', 'vars' => $row_no)), $field('description'));
        }
        if ($quantity <= 0) {
            return $fail(lang(array('string' => 'Line {var:1} needs a quantity greater than zero.', 'vars' => $row_no)), $field('quantity'));
        }
        if ($unit_price < 0) {
            return $fail(lang(array('string' => 'Line {var:1}: the unit price cannot be negative.', 'vars' => $row_no)), $field('unit_price'));
        }
        if (($tax_rate < 0) || ($tax_rate > 100)) {
            return $fail(lang(array('string' => 'Line {var:1}: the VAT rate must be between 0 and 100.', 'vars' => $row_no)), $field('tax_rate'));
        }
        if (($discount_rate < 0) || ($discount_rate > 100)) {
            return $fail(lang(array('string' => 'Line {var:1}: the discount must be between 0 and 100.', 'vars' => $row_no)), $field('discount_rate'));
        }
        if (($offer_rate < 0) || ($offer_rate > 100)) {
            return $fail(lang(array('string' => 'Line {var:1}: the campaign discount must be between 0 and 100.', 'vars' => $row_no)), $field('offer_discount_rate'));
        }

        // A campaign rate without a campaign is a typed discount in the wrong
        // box; a campaign without a rate is nothing.
        if ($offer_rate <= 0) {
            $offer_id = 0;
        }

        $line_total = erp_line_total($unit_price, $quantity);
        $offer_amount = ($offer_rate > 0) ? erp_apply_rate($line_total, $offer_rate) : 0;
        $typed_amount = ($discount_rate > 0) ? erp_apply_rate($line_total - $offer_amount, $discount_rate) : 0;
        $discount_amount = $offer_amount + $typed_amount;
        $line_tax = erp_apply_rate($line_total - $discount_amount, $tax_rate);

        $lines[] = array(
            'product_id' => max(0, $product_id),
            'description' => $description,
            'quantity' => $quantity,
            'unit_code' => $unit_code,
            'unit_price' => $unit_price,
            'offer_id' => max(0, $offer_id),
            'offer_discount_rate' => $offer_rate,
            'offer_discount_amount' => $offer_amount,
            'discount_rate' => $discount_rate,
            'discount_amount' => $discount_amount,
            'tax_rate' => $tax_rate,
            'tax_total' => $line_tax,
            'line_total' => $line_total,
        );

        $subtotal += $line_total;
        $discount_total += $discount_amount;
        $tax_total += $line_tax;
    }

    return array(
        'lines' => $lines,
        'totals' => array(
            'subtotal' => $subtotal,
            'discount_total' => $discount_total,
            'tax_total' => $tax_total,
            'grand_total' => $subtotal - $discount_total + $tax_total,
        ),
        'error' => '',
        'field' => '',
    );
}

/**
 * Whether the line table carries the campaign columns (4.58).
 *
 * @return bool
 */
function erp_invoice_lines_have_offers()
{
    static $has = null;

    if ($has === null) {
        $has = function_exists('waf_table_has_column') && waf_table_has_column('erp_invoice_items', 'offer_discount_rate');
    }

    return $has;
}

/**
 * Read and check the header of a typed invoice.
 *
 * Shared by the draft save and the issue step so the two cannot drift apart
 * on what an acceptable account or currency is.
 *
 * @param array $data  See erp_invoice_draft_save()
 * @return array ['header' => array, 'error' => string]
 */
function erp_manual_header_build($data)
{
    $fail = function ($message, $field = '_error') {
        return array('header' => array(), 'error' => $message, 'field' => $field);
    };

    $direction = (($data['direction'] ?? 'sales') === 'purchase') ? 'purchase' : 'sales';
    $account_id = (int) ($data['account_id'] ?? 0);

    if (($account_id <= 0) || !is_array(erp_account($account_id))) {
        return $fail(lang('Choose an account.'), 'account_id');
    }

    $base = erp_base_currency();
    $currency = strtoupper(trim((string) ($data['currency'] ?? $base)));

    if (!erp_fx_currency_allowed($currency)) {
        return $fail(lang('That currency is not enabled for the ERP.'), 'currency');
    }

    $exchange_rate = ($currency === $base) ? 1.0 : (float) ($data['exchange_rate'] ?? 0);

    if ($exchange_rate < 0) {
        return $fail(lang('Enter an exchange rate greater than zero.'), 'exchange_rate');
    }

    $today = date('Y-m-d');
    $issue_date = (string) ($data['issue_date'] ?? $today);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issue_date)) {
        $issue_date = $today;
    }

    // No due date given: the account's payment term, or the store's default,
    // counted from the issue date.
    $due_date = (string) ($data['due_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
        $due_date = erp_account_due_date($account_id, $issue_date);
    }

    $supplier_invoice_date = (string) ($data['supplier_invoice_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $supplier_invoice_date)) {
        $supplier_invoice_date = '0000-00-00';
    }

    return array(
        'header' => array(
            'direction' => $direction,
            'account_id' => $account_id,
            'currency' => $currency,
            'exchange_rate' => $exchange_rate,
            'exchange_rate_date' => (string) ($data['exchange_rate_date'] ?? $issue_date),
            'exchange_rate_source' => ($currency === $base) ? 'base' : (string) ($data['exchange_rate_source'] ?? ''),
            'issue_date' => $issue_date,
            'due_date' => $due_date,
            // The supplier's own number and date on a purchase bill; a sales
            // invoice has neither.
            'supplier_invoice_no' => ($direction === 'purchase') ? mb_substr(trim((string) ($data['supplier_invoice_no'] ?? '')), 0, 32) : '',
            'supplier_invoice_date' => ($direction === 'purchase') ? $supplier_invoice_date : '0000-00-00',
            'notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 5000),
            'series' => trim((string) ($data['series'] ?? (defined('ERP_DEFAULT_SERIES') ? ERP_DEFAULT_SERIES : 'PGF'))),
            'created_by' => (int) ($data['created_by'] ?? 0),
        ),
        'error' => '',
        'field' => '',
    );
}

/**
 * Save a typed invoice as a draft, or rewrite an existing draft.
 *
 * A draft takes no number and posts nothing: it is the header and the lines,
 * kept so the operator can come back. The lines are rewritten whole on every
 * save - a draft has no returns hanging on its lines, so nothing refers to
 * them by id.
 *
 * A foreign-currency draft may be saved without a rate; the rate is
 * required when the draft is issued.
 *
 * @param array $data        direction ('sales'|'purchase'), account_id,
 *                           currency, exchange_rate, exchange_rate_date,
 *                           exchange_rate_source, issue_date, due_date,
 *                           supplier_invoice_no, supplier_invoice_date, notes,
 *                           series, lines (see erp_manual_lines_build()),
 *                           created_by
 * @param int   $invoice_id  0 to create, otherwise the draft to rewrite
 * @return array ['success' => bool, 'invoice_id' => int, 'error' => string]
 */
function erp_invoice_draft_save($data, $invoice_id = 0)
{
    $fail = function ($message, $field = '_error') {
        return array('success' => false, 'invoice_id' => 0, 'error' => $message, 'field' => $field);
    };

    $invoice_id = (int) $invoice_id;

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

    $totals = $built['totals'];
    $base = erp_base_currency();
    $grand_total_base = ($header['currency'] === $base)
        ? $totals['grand_total']
        : (($header['exchange_rate'] > 0) ? erp_to_base($totals['grand_total'], $header['exchange_rate']) : 0);

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $header_sql = "
            direction = '" . $header['direction'] . "',
            account_id = '" . $header['account_id'] . "',
            issue_date = '" . escape($header['issue_date']) . "',
            due_date = '" . escape($header['due_date']) . "',
            supplier_invoice_no = '" . escape($header['supplier_invoice_no']) . "',
            supplier_invoice_date = '" . escape($header['supplier_invoice_date']) . "',
            currency = '" . escape($header['currency']) . "',
            exchange_rate = '" . escape(number_format($header['exchange_rate'], 6, '.', '')) . "',
            exchange_rate_date = '" . escape($header['exchange_rate_date']) . "',
            exchange_rate_source = '" . escape($header['exchange_rate_source']) . "',
            subtotal = '" . (int) $totals['subtotal'] . "',
            discount_total = '" . (int) $totals['discount_total'] . "',
            shipping_total = 0,
            surcharge_total = 0,
            gift_card_total = 0,
            tax_total = '" . (int) $totals['tax_total'] . "',
            grand_total = '" . (int) $totals['grand_total'] . "',
            grand_total_base = '" . (int) $grand_total_base . "',
            notes = '" . escape($header['notes']) . "',
            updated_at = '" . time() . "'";

    if ($invoice_id > 0) {

        $existing = db_item("SELECT id, status FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1 FOR UPDATE");

        if (!is_array($existing)) {
            erp_tx_rollback();
            return $fail(lang('The invoice could not be found.'));
        }
        if ((string) $existing['status'] !== 'draft') {
            erp_tx_rollback();
            return $fail(lang('Only a draft can be edited. An issued invoice is corrected with a return.'));
        }

        $ok = erp_query("UPDATE erp_invoices SET " . $header_sql . " WHERE id = '" . $invoice_id . "'");

        if (($ok === false) || (erp_query("DELETE FROM erp_invoice_items WHERE invoice_id = '" . $invoice_id . "'") === false)) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }

    } else {

        // The number is set to the row's own id right after the insert. A
        // second draft inserted in between waits on the unique key and
        // re-checks once this transaction has committed, so the placeholder
        // never collides for longer than the two statements below.
        $ok = erp_query("INSERT INTO erp_invoices SET
            doc_type = 'invoice',
            invoice_type = 'SATIS',
            series = '" . escape(ERP_DRAFT_SERIES) . "',
            number = 0,
            issue_year = 0,
            full_number = '',
            order_id = 0,
            status = 'draft',
            is_internet_sale = 0,
            created_by = '" . $header['created_by'] . "',
            created_at = '" . time() . "'," . $header_sql);

        if ($ok === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }

        $invoice_id = (int) mysqli_insert_id(db::$con);

        if (erp_query("UPDATE erp_invoices SET number = '" . $invoice_id . "' WHERE id = '" . $invoice_id . "'") === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    $line_no = 0;

    foreach ($built['lines'] as $line) {
        $line_no++;

        $ok = erp_query("INSERT INTO erp_invoice_items SET
                invoice_id = '" . $invoice_id . "',
                line_no = '" . $line_no . "',
                product_id = '" . (int) $line['product_id'] . "',
                description = '" . escape($line['description']) . "',
                quantity = '" . number_format($line['quantity'], 4, '.', '') . "',
                unit_code = '" . escape($line['unit_code']) . "',
                unit_price = '" . (int) $line['unit_price'] . "',"
                . (erp_invoice_lines_have_offers() ? "
                offer_id = '" . (int) $line['offer_id'] . "',
                offer_discount_rate = '" . escape(number_format($line['offer_discount_rate'], 3, '.', '')) . "'," : "") . "
                discount_rate = '" . escape(number_format($line['discount_rate'], 3, '.', '')) . "',
                discount_amount = '" . (int) $line['discount_amount'] . "',
                tax_rate = '" . escape(number_format($line['tax_rate'], 3, '.', '')) . "',
                tax_total = '" . (int) $line['tax_total'] . "',
                line_total = '" . (int) $line['line_total'] . "'");

        if ($ok === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    // The account as it reads now, so the draft shows what will be printed;
    // issuing copies it once more.
    if (!erp_invoice_snapshot_account($invoice_id, $header['account_id'])) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(($error !== '') ? $error : lang('Choose an account.'));
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'invoice_id' => $invoice_id, 'error' => '');
}

/**
 * Turn a draft into an issued invoice.
 *
 * Takes the next number of the series, rewrites the header with totals
 * recomputed from the stored lines, posts the movement on the account and
 * refreshes its balance - all in one transaction, so a failed step leaves the
 * draft as it was and the series untouched. The header totals are never
 * trusted: the lines are the document, the totals are derived from them.
 *
 * Refused when the row is not a draft, the account is passive, there are no
 * lines, or a foreign-currency document has no rate and none is recorded for
 * its issue date.
 *
 * @param int $invoice_id
 * @param int $created_by
 * @return array ['success' => bool, 'invoice_id' => int, 'full_number' => string, 'error' => string]
 */
function erp_invoice_issue($invoice_id, $created_by = 0)
{
    $invoice_id = (int) $invoice_id;
    $created_by = (int) $created_by;

    $fail = function ($message) use ($invoice_id) {
        return array('success' => false, 'invoice_id' => $invoice_id, 'full_number' => '', 'error' => $message);
    };

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1 FOR UPDATE");

    if (!is_array($invoice)) {
        erp_tx_rollback();
        return $fail(lang('The invoice could not be found.'));
    }

    if ((string) $invoice['status'] !== 'draft') {
        erp_tx_rollback();
        return $fail(lang('Only a draft can be issued.'));
    }

    $account = erp_account((int) $invoice['account_id']);

    if (!is_array($account)) {
        erp_tx_rollback();
        return $fail(lang('Choose an account.'));
    }
    if ((string) $account['status'] !== 'active') {
        erp_tx_rollback();
        return $fail(lang('That account is passive. Make it active before invoicing it.'));
    }

    $rows = (array) db_items("SELECT * FROM erp_invoice_items WHERE invoice_id = '" . $invoice_id . "' ORDER BY line_no ASC, id ASC");
    $built = erp_manual_lines_build($rows);

    if ($built['error'] !== '') {
        erp_tx_rollback();
        return $fail($built['error']);
    }
    if (empty($built['lines'])) {
        erp_tx_rollback();
        return $fail(lang('Enter at least one line.'));
    }

    $totals = $built['totals'];

    $direction = ((string) $invoice['direction'] === 'purchase') ? 'purchase' : 'sales';
    $issue_date = (string) $invoice['issue_date'];
    $issue_year = (int) substr($issue_date, 0, 4);
    $series = trim((string) (defined('ERP_DEFAULT_SERIES') ? ERP_DEFAULT_SERIES : 'PGF'));

    if (($series === '') || ($series === ERP_DRAFT_SERIES)) {
        erp_tx_rollback();
        return $fail(lang('No invoice series is set.'));
    }

    // The rate: the one on the draft, or the recorded rate of the issue date
    // when the draft was saved without one.
    $base = erp_base_currency();
    $currency = strtoupper(trim((string) $invoice['currency']));
    $exchange_rate = ($currency === $base) ? 1.0 : (float) $invoice['exchange_rate'];
    $rate_date = ($currency === $base) ? $issue_date : (string) $invoice['exchange_rate_date'];
    $rate_source = ($currency === $base) ? 'base' : (string) $invoice['exchange_rate_source'];

    if (($currency !== $base) && ($exchange_rate <= 0)) {
        $recorded = erp_fx_rate_for($currency, $issue_date);

        if (!is_array($recorded)) {
            erp_tx_rollback();
            return $fail(lang(array(
                'string' => 'No exchange rate is recorded for {var:1} on {var:2}. Run Update Exchange Rates or enter the rate.',
                'vars' => array($currency, prepare_form_data_for_output($issue_date, 'date')),
            )));
        }

        $exchange_rate = (float) $recorded['rate'];
        $rate_date = (string) $recorded['rate_date'];
        $rate_source = (string) $recorded['source'];
    }

    $grand_total_base = ($currency === $base) ? $totals['grand_total'] : erp_to_base($totals['grand_total'], $exchange_rate);

    $numbered = erp_next_number($series, ($direction === 'sales') ? 'sales_invoice' : 'purchase_invoice', $issue_year);

    if (!$numbered['success']) {
        erp_tx_rollback();
        return $fail($numbered['error']);
    }

    $ok = erp_query("UPDATE erp_invoices SET
            series = '" . escape($series) . "',
            number = '" . (int) $numbered['number'] . "',
            issue_year = '" . $issue_year . "',
            full_number = '" . escape($numbered['full']) . "',
            exchange_rate = '" . escape(number_format($exchange_rate, 6, '.', '')) . "',
            exchange_rate_date = '" . escape($rate_date) . "',
            exchange_rate_source = '" . escape($rate_source) . "',
            subtotal = '" . (int) $totals['subtotal'] . "',
            discount_total = '" . (int) $totals['discount_total'] . "',
            tax_total = '" . (int) $totals['tax_total'] . "',
            grand_total = '" . (int) $totals['grand_total'] . "',
            grand_total_base = '" . (int) $grand_total_base . "',
            status = 'issued',
            updated_at = '" . time() . "'
        WHERE id = '" . $invoice_id . "' AND status = 'draft'");

    if (($ok === false) || (mysqli_affected_rows(db::$con) !== 1)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(($error !== '') ? $error : lang('Only a draft can be issued.'));
    }

    // The counterparty as it reads at the moment of issue; the document
    // prints this copy, however the card is edited afterwards.
    if (!erp_invoice_snapshot_account($invoice_id, (int) $invoice['account_id'])) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    // The stored per-line figures are rewritten from the same computation
    // the totals came from, so a line never disagrees with its document.
    foreach ($built['lines'] as $index => $line) {
        if (!isset($rows[$index])) {
            continue;
        }

        $ok = erp_query("UPDATE erp_invoice_items SET
                discount_amount = '" . (int) $line['discount_amount'] . "',
                tax_total = '" . (int) $line['tax_total'] . "',
                line_total = '" . (int) $line['line_total'] . "'
            WHERE id = '" . (int) $rows[$index]['id'] . "'");

        if ($ok === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    // A sale is owed to the shop; a purchase is owed by it.
    $posted = erp_account_post(array(
        'account_id' => (int) $invoice['account_id'],
        'doc_date' => $issue_date,
        'kind' => 'invoice',
        'direction' => ($direction === 'sales') ? 'debit' : 'credit',
        'amount' => (int) $totals['grand_total'],
        'currency' => $currency,
        'exchange_rate' => $exchange_rate,
        'exchange_rate_date' => $rate_date,
        'exchange_rate_source' => $rate_source,
        'amount_base' => (int) $grand_total_base,
        'doc_type' => 'invoice',
        'doc_id' => $invoice_id,
        'description' => $numbered['full'],
        'created_by' => $created_by,
    ));

    if (($posted === false) || !erp_account_refresh_balance((int) $invoice['account_id'])) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    erp_event_invoice($invoice_id, 'erp.invoice.created');

    return array('success' => true, 'invoice_id' => $invoice_id, 'full_number' => $numbered['full'], 'error' => '');
}

/**
 * Delete a draft.
 *
 * A draft is deleted, not cancelled: it never had a number or a movement, so
 * there is nothing to reverse and nothing a register would miss.
 *
 * @param int $invoice_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_invoice_draft_delete($invoice_id)
{
    $invoice_id = (int) $invoice_id;

    $fail = function ($message) {
        return array('success' => false, 'error' => $message);
    };

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $invoice = db_item("SELECT id, status FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1 FOR UPDATE");

    if (!is_array($invoice)) {
        erp_tx_rollback();
        return $fail(lang('The invoice could not be found.'));
    }

    if ((string) $invoice['status'] !== 'draft') {
        erp_tx_rollback();
        return $fail(lang('Only a draft can be deleted. An issued invoice is cancelled instead.'));
    }

    if ((erp_query("DELETE FROM erp_invoice_items WHERE invoice_id = '" . $invoice_id . "'") === false)
        || (erp_query("DELETE FROM erp_invoices WHERE id = '" . $invoice_id . "' AND status = 'draft'") === false)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    // A delivery note that was waiting on this draft is waiting again.
    if (function_exists('erp_waybill_unlink_invoice')) {
        erp_waybill_unlink_invoice($invoice_id);
    }

    return array('success' => true, 'error' => '');
}

/**
 * Raise an invoice from typed lines in one call.
 *
 * The draft save and the issue step in a single transaction, for callers that
 * have the whole document in hand and want no draft left behind on failure.
 * A foreign-currency document needs a rate above zero.
 *
 * @param array $data  See erp_invoice_draft_save(); lines each carry
 *                     description, quantity, unit_price in kurus, tax_rate
 * @return array ['success' => bool, 'invoice_id' => int, 'full_number' => string, 'error' => string]
 */
function erp_invoice_create_manual($data)
{
    $fail = function ($message, $field = '_error') {
        return array('success' => false, 'invoice_id' => 0, 'full_number' => '', 'error' => $message, 'field' => $field);
    };

    $base = erp_base_currency();
    $currency = strtoupper(trim((string) ($data['currency'] ?? $base)));

    if (($currency !== $base) && ((float) ($data['exchange_rate'] ?? 0) <= 0)) {
        return $fail(lang('Enter an exchange rate greater than zero.'), 'exchange_rate');
    }

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $saved = erp_invoice_draft_save($data, 0);

    if (!$saved['success']) {
        erp_tx_rollback();
        return $fail($saved['error'], $saved['field'] ?? '_error');
    }

    $issued = erp_invoice_issue($saved['invoice_id'], (int) ($data['created_by'] ?? 0));

    if (!$issued['success']) {
        erp_tx_rollback();
        return $fail($issued['error']);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'invoice_id' => (int) $saved['invoice_id'], 'full_number' => $issued['full_number'], 'error' => '');
}
