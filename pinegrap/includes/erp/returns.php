<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - putting a sale back: credit notes and cancellations.
 *
 * An issued invoice is never edited. It has already been handed to somebody, and
 * a document that says something different from the copy in their hands is worse
 * than a wrong document. There are two honest ways back:
 *
 *   a return (iade)  a document of its own, against the original, for the goods
 *                    that actually came back. It can be partial, it carries its
 *                    own number, and it credits the account.
 *
 *   a cancellation   the invoice should never have existed. Allowed only while
 *                    nothing has been hung on it - no money allocated to it, no
 *                    return against it - and it reverses its own ledger entry
 *                    rather than deleting it.
 *
 * A full return reproduces the parent's stored figures verbatim instead of
 * working them out again. The parent's tax was tied back to the order's header
 * and can sit a kurus away from what the lines alone produce; recomputing here
 * would leave that kurus behind on the account forever.
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
 * Returns raised against an invoice, cancelled ones left out.
 *
 * @param int $invoice_id
 * @return array
 */
function erp_invoice_returns($invoice_id)
{
    return (array) db_items("SELECT id, full_number, issue_date, grand_total, status
        FROM erp_invoices
        WHERE parent_invoice_id = '" . (int) $invoice_id . "'
          AND doc_type = 'return'
          AND status <> 'cancelled'
        ORDER BY issue_date ASC, id ASC");
}

/**
 * How much of an invoice has been given back.
 *
 * @param int $invoice_id
 * @return int  Kurus
 */
function erp_invoice_returned_total($invoice_id)
{
    return (int) db_value("SELECT COALESCE(SUM(grand_total), 0) FROM erp_invoices
        WHERE parent_invoice_id = '" . (int) $invoice_id . "'
          AND doc_type = 'return'
          AND status <> 'cancelled'");
}

/**
 * The parent's lines with what is still returnable on each.
 *
 * Besides the quantity, each line carries the tax already credited back on it
 * (returned_tax), so a further return can take no more than what is left. A
 * return line has no link to its parent line, so, as when a return is
 * cancelled, the lines are matched on product.
 *
 * @param int $invoice_id
 * @return array
 */
function erp_returnable_lines($invoice_id)
{
    $invoice_id = (int) $invoice_id;

    $lines = (array) db_items("SELECT i.*,
            (SELECT COALESCE(SUM(r.tax_total), 0)
                FROM erp_invoice_items r
                INNER JOIN erp_invoices d ON r.invoice_id = d.id
                WHERE d.parent_invoice_id = '" . $invoice_id . "'
                  AND d.doc_type = 'return'
                  AND d.status <> 'cancelled'
                  AND r.product_id = i.product_id) AS returned_tax
        FROM erp_invoice_items i
        WHERE i.invoice_id = '" . $invoice_id . "' ORDER BY i.line_no ASC, i.id ASC");

    foreach ($lines as $index => $line) {
        $lines[$index]['remaining_qty'] = (float) $line['quantity'] - (float) $line['returned_qty'];
    }

    return $lines;
}

/**
 * Build the lines of a return from the quantities somebody chose.
 *
 * @param array $parent_lines  from erp_returnable_lines()
 * @param array $quantities    line id => quantity
 * @return array ['lines' => array, 'totals' => array, 'is_full' => bool, 'error' => string]
 */
function erp_return_build($parent_lines, $quantities)
{
    $lines = array();
    $is_full = true;

    foreach ($parent_lines as $parent) {
        $line_id = (int) $parent['id'];
        $wanted = isset($quantities[$line_id]) ? (float) $quantities[$line_id] : 0;
        $sold = (float) $parent['quantity'];
        $remaining = (float) $parent['remaining_qty'];

        // A quantity is a count of things that came back, so it cannot exceed
        // what went out and has not already come back.
        if ($wanted > ($remaining + 0.00001)) {
            return array('lines' => array(), 'totals' => array(), 'is_full' => false,
                'error' => lang('You cannot return more than was sold.'));
        }

        if ($wanted <= 0) {
            $is_full = false;
            continue;
        }

        if (($wanted < ($sold - 0.00001)) || ((float) $parent['returned_qty'] > 0.00001)) {
            $is_full = false;
        }

        $share = ($sold > 0) ? ($wanted / $sold) : 0;

        $line_total = (int) round((int) $parent['unit_price'] * $wanted);
        $discount = (int) round((int) $parent['discount_amount'] * $share);
        $tax = erp_apply_rate($line_total - $discount, (float) $parent['tax_rate']);

        // The parent line's tax is one rounded figure, and the pieces given
        // back cannot add up to more than it: two halves of 361 kurus both
        // round to 181. Each piece is taken at the rate, capped by what is
        // still on the line, and the piece that empties the line takes exactly
        // what is left - the same tie-back erp_order_lines does against the
        // order's header, so that the returns of a line sum to its tax.
        if (isset($parent['returned_tax'])) {
            $tax_left = max(0, (int) $parent['tax_total'] - (int) $parent['returned_tax']);
            $tax = ($wanted >= ($remaining - 0.00001)) ? $tax_left : min($tax, $tax_left);
        }

        $lines[] = array(
            'parent_line_id' => $line_id,
            'product_id' => (int) $parent['product_id'],
            'description' => $parent['description'],
            'quantity' => $wanted,
            'unit_code' => $parent['unit_code'],
            'unit_price' => (int) $parent['unit_price'],
            'discount_amount' => $discount,
            'tax_rate' => (float) $parent['tax_rate'],
            'tax_total' => $tax,
            'line_total' => $line_total,
        );
    }

    if (empty($lines)) {
        return array('lines' => array(), 'totals' => array(), 'is_full' => false,
            'error' => lang('Choose at least one line to return.'));
    }

    $subtotal = 0;
    $discount_total = 0;
    $tax_total = 0;

    foreach ($lines as $line) {
        $subtotal += $line['line_total'];
        $discount_total += $line['discount_amount'];
        $tax_total += $line['tax_total'];
    }

    return array(
        'lines' => $lines,
        'totals' => array(
            'subtotal' => $subtotal,
            'discount_total' => $discount_total,
            'tax_total' => $tax_total,
            'grand_total' => $subtotal - $discount_total + $tax_total,
        ),
        'is_full' => $is_full,
        'error' => '',
    );
}

/**
 * Raise a return against an invoice.
 *
 * Everything lands in one transaction: the number, the document, its lines, the
 * credit on the account and the returned quantities on the parent. A return that
 * credited the account without marking the goods returned would let the same
 * goods come back twice.
 *
 * @param array $data  invoice_id, quantities (line id => qty), issue_date, created_by
 * @return array ['success' => bool, 'invoice_id' => int, 'full_number' => string, 'error' => string]
 */
function erp_invoice_return($data)
{
    $parent_id = (int) ($data['invoice_id'] ?? 0);

    $fail = function ($message) {
        return array('success' => false, 'invoice_id' => 0, 'full_number' => '', 'error' => $message);
    };

    $parent = db_item("SELECT * FROM erp_invoices WHERE id = '" . $parent_id . "' LIMIT 1");

    if (!is_array($parent)) {
        return $fail(lang('The invoice could not be found.'));
    }

    if ((string) $parent['doc_type'] !== 'invoice') {
        return $fail(lang('Only an invoice can be returned.'));
    }

    if ((string) $parent['status'] === 'cancelled') {
        return $fail(lang('That invoice has been cancelled.'));
    }

    $parent_lines = erp_returnable_lines($parent_id);
    $built = erp_return_build($parent_lines, (array) ($data['quantities'] ?? array()));

    if ($built['error'] !== '') {
        return $fail($built['error']);
    }

    // Nothing has come back before and everything is coming back now, so the
    // return has to cancel the invoice to the kurus - which means copying its
    // figures rather than deriving them a second time.
    if ($built['is_full']) {
        $built['totals'] = array(
            'subtotal' => (int) $parent['subtotal'],
            'discount_total' => (int) $parent['discount_total'],
            'tax_total' => (int) $parent['tax_total'],
            'grand_total' => (int) $parent['grand_total'],
        );
    }

    $account_id = (int) $parent['account_id'];
    $issue_date = (string) ($data['issue_date'] ?? date('Y-m-d'));
    $issue_year = (int) substr($issue_date, 0, 4);
    $created_by = (int) ($data['created_by'] ?? 0);

    // A return cannot share the invoice series. The number printed on a document
    // is what identifies it, and erp_invoices enforces that per series - so a
    // return numbered 1 in the invoice series would be the same document number
    // as invoice 1. Returns therefore run on a series of their own, the invoice
    // series with an I after it unless the site names one.
    $series = (defined('ERP_RETURN_SERIES') && (trim(ERP_RETURN_SERIES) !== ''))
        ? trim(ERP_RETURN_SERIES)
        : ((string) $parent['series'] . 'I');

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $numbered = erp_next_number($series, 'sales_return', $issue_year);

    if (!$numbered['success']) {
        erp_tx_rollback();
        return $fail($numbered['error']);
    }

    $totals = $built['totals'];

    $ok = erp_query("INSERT INTO erp_invoices SET
            direction = '" . escape($parent['direction']) . "',
            doc_type = 'return',
            invoice_type = 'IADE',
            series = '" . escape($series) . "',
            number = '" . (int) $numbered['number'] . "',
            issue_year = '" . $issue_year . "',
            full_number = '" . escape($numbered['full']) . "',
            account_id = '" . $account_id . "',
            order_id = '" . (int) $parent['order_id'] . "',
            parent_invoice_id = '" . $parent_id . "',
            issue_date = '" . escape($issue_date) . "',
            due_date = '" . escape($issue_date) . "',
            currency = '" . escape($parent['currency']) . "',
            subtotal = '" . (int) $totals['subtotal'] . "',
            discount_total = '" . (int) $totals['discount_total'] . "',
            tax_total = '" . (int) $totals['tax_total'] . "',
            grand_total = '" . (int) $totals['grand_total'] . "',
            grand_total_try = '" . (int) $totals['grand_total'] . "',
            status = 'issued',
            is_internet_sale = '" . (int) $parent['is_internet_sale'] . "',
            payment_method = '" . escape($parent['payment_method'] ?? '') . "',
            payment_date = '" . escape($parent['payment_date'] ?? '0000-00-00') . "',
            shipment_date = '" . escape($parent['shipment_date'] ?? '0000-00-00') . "',
            carrier_title = '" . escape($parent['carrier_title'] ?? '') . "',
            carrier_vkn = '" . escape($parent['carrier_vkn'] ?? '') . "',
            web_address = '" . escape($parent['web_address']) . "',
            created_by = '" . $created_by . "',
            created_at = '" . time() . "',
            updated_at = '" . time() . "'");

    if ($ok === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    $return_id = (int) mysqli_insert_id(db::$con);
    $line_no = 0;

    foreach ($built['lines'] as $line) {
        $line_no++;

        $ok = erp_query("INSERT INTO erp_invoice_items SET
                invoice_id = '" . $return_id . "',
                line_no = '" . $line_no . "',
                product_id = '" . (int) $line['product_id'] . "',
                description = '" . escape($line['description']) . "',
                quantity = '" . number_format($line['quantity'], 4, '.', '') . "',
                unit_code = '" . escape($line['unit_code']) . "',
                unit_price = '" . (int) $line['unit_price'] . "',
                discount_amount = '" . (int) $line['discount_amount'] . "',
                tax_rate = '" . escape((string) $line['tax_rate']) . "',
                tax_total = '" . (int) $line['tax_total'] . "',
                line_total = '" . (int) $line['line_total'] . "'");

        if ($ok === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }

        // The parent remembers what has come back, so the same goods cannot be
        // returned twice across two documents.
        $ok = erp_query("UPDATE erp_invoice_items
            SET returned_qty = returned_qty + " . number_format($line['quantity'], 4, '.', '') . "
            WHERE id = '" . (int) $line['parent_line_id'] . "'");

        if ($ok === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    // A sale coming back credits the customer; a purchase coming back debits the
    // supplier. The direction of the money follows the direction of the goods.
    $posted = erp_account_post(array(
        'account_id' => $account_id,
        'doc_date' => $issue_date,
        'kind' => 'return',
        'direction' => ((string) $parent['direction'] === 'sales') ? 'credit' : 'debit',
        'amount' => (int) $totals['grand_total'],
        'currency' => 'TRY',
        'doc_type' => 'return',
        'doc_id' => $return_id,
        'description' => $numbered['full'],
        'created_by' => $created_by,
    ));

    if (($posted === false) || !erp_account_refresh_balance($account_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    // The parent now asks for less, so money already allocated to it may
    // cover it: its status is measured against the total less returns.
    if (!erp_invoice_refresh_paid($parent_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'invoice_id' => $return_id, 'full_number' => $numbered['full'], 'error' => '');
}

/**
 * Cancel a document that should never have been raised.
 *
 * Refused as soon as anything hangs on it. Money allocated to an invoice, or a
 * return raised against it, both mean somebody has already acted on the
 * document; unpicking that quietly is how figures stop agreeing with the paper.
 *
 * The number is not released. A gap in the series that can be explained beats a
 * number that appears twice.
 *
 * @param int $invoice_id
 * @param int $created_by
 * @return array ['success' => bool, 'error' => string]
 */
function erp_invoice_cancel($invoice_id, $created_by = 0)
{
    $invoice_id = (int) $invoice_id;

    $fail = function ($message) {
        return array('success' => false, 'error' => $message);
    };

    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return $fail(lang('The invoice could not be found.'));
    }

    if ((string) $invoice['status'] === 'cancelled') {
        return $fail(lang('That invoice has been cancelled.'));
    }

    if ((int) db_value("SELECT COUNT(*) FROM erp_settlements WHERE invoice_id = '" . $invoice_id . "'") > 0) {
        return $fail(lang('Money has been allocated to this invoice, so it cannot be cancelled. Undo the settlement first.'));
    }

    if (!empty(erp_invoice_returns($invoice_id))) {
        return $fail(lang('There is a return against this invoice, so it cannot be cancelled.'));
    }

    $account_id = (int) $invoice['account_id'];
    $is_return = ((string) $invoice['doc_type'] === 'return');

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    // The entry is reversed, never deleted: the ledger says what happened, and
    // "this was raised and then taken back" is what happened.
    $posted = erp_account_post(array(
        'account_id' => $account_id,
        'doc_date' => date('Y-m-d'),
        'kind' => 'adjustment',
        'direction' => (((string) $invoice['direction'] === 'sales') xor $is_return) ? 'credit' : 'debit',
        'amount' => (int) $invoice['grand_total'],
        'currency' => 'TRY',
        'doc_type' => 'cancel',
        'doc_id' => $invoice_id,
        'description' => lang(array(
            'string' => '{var:1} cancelled',
            'vars' => $invoice['full_number'],
        )),
        'created_by' => (int) $created_by,
    ));

    if ($posted === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (erp_query("UPDATE erp_invoices SET status = 'cancelled', updated_at = '" . time() . "'
        WHERE id = '" . $invoice_id . "'") === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    // A cancelled return gives the goods back to the parent, so the parent can
    // be returned again.
    if ($is_return) {
        foreach ((array) db_items("SELECT product_id, quantity FROM erp_invoice_items
            WHERE invoice_id = '" . $invoice_id . "'") as $line) {

            if (erp_query("UPDATE erp_invoice_items
                SET returned_qty = GREATEST(0, returned_qty - " . number_format((float) $line['quantity'], 4, '.', '') . ")
                WHERE invoice_id = '" . (int) $invoice['parent_invoice_id'] . "'
                  AND product_id = '" . (int) $line['product_id'] . "'
                LIMIT 1") === false) {
                $error = erp_db_error();
                erp_tx_rollback();
                return $fail($error);
            }
        }

        // The parent asks for its full amount again, so a status that the
        // return had allowed to reach paid has to be worked out afresh.
        if (!erp_invoice_refresh_paid((int) $invoice['parent_invoice_id'])) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    // The order is free to be invoiced again.
    if (!$is_return && ((int) $invoice['order_id'] > 0)) {
        if (erp_query("UPDATE orders SET erp_invoice_id = 0 WHERE id = '" . (int) $invoice['order_id'] . "'") === false) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    if (!erp_account_refresh_balance($account_id) || !erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'error' => '');
}
