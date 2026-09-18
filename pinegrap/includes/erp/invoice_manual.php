<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - an invoice typed in by hand.
 *
 * The order bridge raises an invoice from what the shop sold; this raises one
 * from what an operator types - a sale outside the shop, or a supplier's
 * invoice - in the base currency or in one the ERP allows. The figures are
 * worked out here once, through the money helpers, and land in one
 * transaction with the number, the lines and the movement on the account,
 * the same way the bridge does it.
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
 * Raise an invoice from typed lines.
 *
 * Every amount in $data is in the document currency. A foreign-currency
 * document needs a rate above zero; the base-currency total is computed once
 * from the grand total, so the document and its ledger movement agree to the
 * kurus.
 *
 * @param array $data  direction ('sales'|'purchase'), account_id, currency,
 *                     exchange_rate, exchange_rate_date, exchange_rate_source,
 *                     issue_date, due_date, series, lines (each: description,
 *                     quantity, unit_price in kurus, tax_rate), created_by
 * @return array ['success' => bool, 'invoice_id' => int, 'full_number' => string, 'error' => string]
 */
function erp_invoice_create_manual($data)
{
    $fail = function ($message) {
        return array('success' => false, 'invoice_id' => 0, 'full_number' => '', 'error' => $message);
    };

    $direction = (($data['direction'] ?? 'sales') === 'purchase') ? 'purchase' : 'sales';
    $account_id = (int) ($data['account_id'] ?? 0);

    if (($account_id <= 0) || !is_array(erp_account($account_id))) {
        return $fail(lang('Choose an account.'));
    }

    $base = erp_base_currency();
    $currency = strtoupper(trim((string) ($data['currency'] ?? $base)));

    if (!erp_fx_currency_allowed($currency)) {
        return $fail(lang('That currency is not enabled for the ERP.'));
    }

    $exchange_rate = ($currency === $base) ? 1.0 : (float) ($data['exchange_rate'] ?? 0);

    if ($exchange_rate <= 0) {
        return $fail(lang('Enter an exchange rate greater than zero.'));
    }

    $lines = array();
    $subtotal = 0;
    $tax_total = 0;

    foreach ((array) ($data['lines'] ?? array()) as $line) {
        $description = trim((string) ($line['description'] ?? ''));
        $quantity = (float) ($line['quantity'] ?? 0);
        $unit_price = (int) ($line['unit_price'] ?? 0);
        $tax_rate = (float) ($line['tax_rate'] ?? 0);

        // A row with nothing typed in it is an empty row, not an error.
        if (($description === '') && ($quantity == 0) && ($unit_price === 0)) {
            continue;
        }

        if ($description === '') {
            return $fail(lang('Every line needs a description.'));
        }
        if ($quantity <= 0) {
            return $fail(lang('Every line needs a quantity greater than zero.'));
        }
        if (($unit_price < 0) || ($tax_rate < 0) || ($tax_rate > 100)) {
            return $fail(lang('Check the unit price and the VAT rate on the lines.'));
        }

        $line_total = erp_line_total($unit_price, $quantity);
        $line_tax = erp_apply_rate($line_total, $tax_rate);

        $lines[] = array(
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'tax_rate' => $tax_rate,
            'tax_total' => $line_tax,
            'line_total' => $line_total,
        );

        $subtotal += $line_total;
        $tax_total += $line_tax;
    }

    if (empty($lines)) {
        return $fail(lang('Enter at least one line.'));
    }

    $grand_total = $subtotal + $tax_total;
    $grand_total_base = ($currency === $base) ? $grand_total : erp_to_base($grand_total, $exchange_rate);

    $issue_date = (string) ($data['issue_date'] ?? date('Y-m-d'));
    $due_date = (string) ($data['due_date'] ?? $issue_date);
    $issue_year = (int) substr($issue_date, 0, 4);
    $created_by = (int) ($data['created_by'] ?? 0);
    $series = trim((string) ($data['series'] ?? (defined('ERP_DEFAULT_SERIES') ? ERP_DEFAULT_SERIES : 'PGF')));

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $numbered = erp_next_number($series, ($direction === 'sales') ? 'sales_invoice' : 'purchase_invoice', $issue_year);

    if (!$numbered['success']) {
        erp_tx_rollback();
        return $fail($numbered['error']);
    }

    $ok = erp_query("INSERT INTO erp_invoices SET
            direction = '" . $direction . "',
            doc_type = 'invoice',
            invoice_type = 'SATIS',
            series = '" . escape($series) . "',
            number = '" . (int) $numbered['number'] . "',
            issue_year = '" . $issue_year . "',
            full_number = '" . escape($numbered['full']) . "',
            account_id = '" . $account_id . "',
            order_id = 0,
            issue_date = '" . escape($issue_date) . "',
            due_date = '" . escape($due_date) . "',
            currency = '" . escape($currency) . "',
            exchange_rate = '" . escape(number_format($exchange_rate, 6, '.', '')) . "',
            exchange_rate_date = '" . escape((string) ($data['exchange_rate_date'] ?? $issue_date)) . "',
            exchange_rate_source = '" . escape(($currency === $base) ? 'base' : (string) ($data['exchange_rate_source'] ?? '')) . "',
            subtotal = '" . $subtotal . "',
            discount_total = 0,
            shipping_total = 0,
            surcharge_total = 0,
            gift_card_total = 0,
            tax_total = '" . $tax_total . "',
            grand_total = '" . $grand_total . "',
            grand_total_base = '" . $grand_total_base . "',
            status = 'issued',
            is_internet_sale = 0,
            created_by = '" . $created_by . "',
            created_at = '" . time() . "',
            updated_at = '" . time() . "'");

    if ($ok === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    $invoice_id = (int) mysqli_insert_id(db::$con);
    $line_no = 0;

    foreach ($lines as $line) {
        $line_no++;

        $ok = erp_query("INSERT INTO erp_invoice_items SET
                invoice_id = '" . $invoice_id . "',
                line_no = '" . $line_no . "',
                product_id = 0,
                description = '" . escape($line['description']) . "',
                quantity = '" . number_format($line['quantity'], 4, '.', '') . "',
                unit_code = 'C62',
                unit_price = '" . (int) $line['unit_price'] . "',
                discount_amount = 0,
                tax_rate = '" . escape(number_format($line['tax_rate'], 3, '.', '')) . "',
                tax_total = '" . (int) $line['tax_total'] . "',
                line_total = '" . (int) $line['line_total'] . "'");

        if ($ok === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    // A sale is owed to the shop; a purchase is owed by it.
    $posted = erp_account_post(array(
        'account_id' => $account_id,
        'doc_date' => $issue_date,
        'kind' => 'invoice',
        'direction' => ($direction === 'sales') ? 'debit' : 'credit',
        'amount' => $grand_total,
        'currency' => $currency,
        'exchange_rate' => $exchange_rate,
        'exchange_rate_date' => (string) ($data['exchange_rate_date'] ?? $issue_date),
        'exchange_rate_source' => ($currency === $base) ? 'base' : (string) ($data['exchange_rate_source'] ?? ''),
        'amount_base' => $grand_total_base,
        'doc_type' => 'invoice',
        'doc_id' => $invoice_id,
        'description' => $numbered['full'],
        'created_by' => $created_by,
    ));

    if (($posted === false) || !erp_account_refresh_balance($account_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'invoice_id' => $invoice_id, 'full_number' => $numbered['full'], 'error' => '');
}
