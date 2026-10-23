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
 * Besides the quantity, each line carries the amount, discount and taxes
 * already credited back on it (returned_line_total, returned_discount,
 * returned_tax...), so a further return can take no more than what is left
 * and the piece that empties the line takes exactly the rest. A
 * return line names the parent line it was taken from (parent_line_id); lines
 * written before that link existed carry 0 and are matched on product instead,
 * which is exact as long as the parent has one line per product.
 *
 * @param int $invoice_id
 * @return array
 */
function erp_returnable_lines($invoice_id)
{
    $invoice_id = (int) $invoice_id;

    // What earlier returns already gave back of each line, matched the way
    // the quantities are.
    $given_back = function ($column) use ($invoice_id) {
        return "(SELECT COALESCE(SUM(r." . $column . "), 0)
                FROM erp_invoice_items r
                INNER JOIN erp_invoices d ON r.invoice_id = d.id
                WHERE d.parent_invoice_id = '" . $invoice_id . "'
                  AND d.doc_type = 'return'
                  AND d.status <> 'cancelled'
                  AND (r.parent_line_id = i.id
                       OR (r.parent_line_id = 0 AND r.product_id = i.product_id)))";
    };

    $lines = (array) db_items("SELECT i.*,
            " . $given_back('line_total') . " AS returned_line_total,
            " . $given_back('discount_amount') . " AS returned_discount,
            " . $given_back('tax_total') . " AS returned_tax"
            . (erp_invoice_lines_have_withholding() ? ",
            " . $given_back('withholding_amount') . " AS returned_withholding" : "")
            . (erp_invoice_lines_have_tax2() ? ",
            " . $given_back('tax2_amount') . " AS returned_tax2" : "") . "
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
 * Each piece takes its share of the line's amount and discount, rounded on
 * its own and capped by what earlier returns left of them; the piece that
 * empties a line takes exactly what is left, so the pieces of a line add up
 * to the line however they were rounded.
 *
 * The lines given back in part take their taxes the way a document does
 * (erp_rate_vat()): the VAT per group of one VAT rate and one withholding
 * rate of the return, on the group's sum, the withholding over the groups
 * rounded once, the second tax per its own rate - each capped by what is
 * still on its line. The piece that empties a line whose earlier returns are
 * known takes exactly what is left on it, so the returns of a line add up to
 * the line. An order's lines (erp_order_refund_lines()) carry nothing given
 * back yet, so a line given back whole takes the tax the order charged on it.
 *
 * A line below zero (a credit, from a product priced below zero) has its
 * figures below zero, and so are its pieces and what is left of it: each is
 * held between zero and what is left, on the side of zero the line is on
 * (erp_return_within()). A return that would then give back more than is left
 * of the sale, or less than nothing, is refused (erp_return_credit_refusal()).
 *
 * @param array $parent_lines  from erp_returnable_lines()
 * @param array $quantities    line id => quantity
 * @return array ['lines' => array, 'totals' => array, 'is_full' => bool, 'error' => string]
 */
function erp_return_build($parent_lines, $quantities)
{
    $lines = array();
    // What erp_rate_vat() takes for the lines given back in part, and what is
    // left on each line, under the line's key in $lines.
    $taxed = array();
    $left = array();
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

        // Each piece is rounded on its own: two halves of 10.01 off both
        // round to 5.01, and two halves of a 2.5 x 3.33 line (8.33) both to
        // 4.16. Capped by what earlier returns left, and taken whole by the
        // piece that empties the line, the pieces sum to the line's figures.
        $empties = ($wanted >= ($remaining - 0.00001));

        if (isset($parent['returned_line_total'])) {
            $line_left = erp_return_within((int) $parent['line_total'] - (int) $parent['returned_line_total'], $parent['line_total']);
            $line_total = $empties ? $line_left : erp_return_within($line_total, $line_left);
        }

        if (isset($parent['returned_discount'])) {
            $discount_left = erp_return_within((int) $parent['discount_amount'] - (int) $parent['returned_discount'], $parent['discount_amount']);
            $discount = $empties ? $discount_left : erp_return_within($discount, $discount_left);
        }

        $discount = erp_return_within($discount, $line_total);
        $tax2_rate = (float) ($parent['tax2_rate'] ?? 0);
        $withholding_rate = (float) ($parent['withholding_rate'] ?? 0);

        // The parent line's taxes are rounded figures, and the pieces given
        // back cannot add up to more than them: two halves of 361 kurus both
        // round to 181. Each piece is capped by what is still on the line,
        // and the piece that empties the line takes exactly what is left -
        // the same tie-back erp_order_lines does against the order's header,
        // so that the returns of a line sum to its taxes. tax_total holds the
        // second tax too; the withholding is a share of the VAT alone.
        $known = isset($parent['returned_tax']);
        $left[count($lines)] = array(
            'empties' => $known && $empties,
            'tax' => $known ? erp_return_within((int) $parent['tax_total'] - (int) $parent['returned_tax'], $parent['tax_total']) : null,
            'tax2' => (($tax2_rate > 0) && isset($parent['returned_tax2']))
                ? erp_return_within((int) ($parent['tax2_amount'] ?? 0) - (int) $parent['returned_tax2'], $parent['tax2_amount'] ?? 0) : null,
            'withholding' => (($withholding_rate > 0) && isset($parent['returned_withholding']))
                ? erp_return_within((int) ($parent['withholding_amount'] ?? 0) - (int) $parent['returned_withholding'], $parent['withholding_amount'] ?? 0) : null,
        );

        if (!$left[count($lines)]['empties']) {
            $taxed[count($lines)] = array(
                'net' => $line_total - $discount,
                'tax_rate' => (float) $parent['tax_rate'],
                'withholding_rate' => $withholding_rate,
                'tax2_rate' => $tax2_rate,
            );
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
            'tax_total' => 0,
            'tax2_rate' => $tax2_rate,
            'tax2_amount' => 0,
            'withholding_code' => (string) ($parent['withholding_code'] ?? ''),
            'vat_exemption_code' => (string) ($parent['vat_exemption_code'] ?? ''),
            'withholding_rate' => $withholding_rate,
            'withholding_amount' => 0,
            'line_total' => $line_total,
        );
    }

    if (empty($lines)) {
        return array('lines' => array(), 'totals' => array(), 'is_full' => false,
            'error' => lang('Choose at least one line to return.'));
    }

    $taxes = erp_rate_vat($taxed);

    foreach ($lines as $index => $line) {
        $cap = $left[$index];

        if ($cap['empties']) {
            $tax2 = ($cap['tax2'] !== null) ? $cap['tax2'] : 0;
            $tax = $cap['tax'];
            $withholding = ($cap['withholding'] !== null) ? $cap['withholding']
                : (($line['withholding_rate'] > 0) ? erp_apply_rate(erp_return_within($tax - $tax2, $tax), $line['withholding_rate']) : 0);
        } else {
            $tax2 = $taxes['lines'][$index]['tax2'];
            if ($cap['tax2'] !== null) {
                $tax2 = erp_return_within($tax2, $cap['tax2']);
            }

            $tax = $taxes['lines'][$index]['vat'] + $tax2;
            if ($cap['tax'] !== null) {
                $tax = erp_return_within($tax, $cap['tax']);
            }

            // Never more than the VAT this piece gives back.
            $withholding = erp_return_within($taxes['lines'][$index]['withholding'], erp_return_within($tax - $tax2, $tax));
            if ($cap['withholding'] !== null) {
                $withholding = erp_return_within($withholding, $cap['withholding']);
            }
        }

        $lines[$index]['tax_total'] = $tax;
        $lines[$index]['tax2_amount'] = erp_return_within($tax2, $tax);
        $lines[$index]['withholding_amount'] = $withholding;
    }

    $subtotal = 0;
    $discount_total = 0;
    $tax_total = 0;
    $tax2_total = 0;
    $withholding_total = 0;

    foreach ($lines as $line) {
        $subtotal += $line['line_total'];
        $discount_total += $line['discount_amount'];
        $tax_total += $line['tax_total'];
        $tax2_total += $line['tax2_amount'];
        $withholding_total += $line['withholding_amount'];
    }

    return array(
        'lines' => $lines,
        'totals' => array(
            'subtotal' => $subtotal,
            'discount_total' => $discount_total,
            'tax_total' => $tax_total,
            'tax2_total' => $tax2_total,
            'withholding_total' => $withholding_total,
            'grand_total' => $subtotal - $discount_total + $tax_total - $withholding_total,
        ),
        'is_full' => $is_full,
        'error' => erp_return_credit_refusal($parent_lines, $lines),
    );
}

/**
 * A figure held between zero and a limit, on whichever side of zero the limit
 * is: never above a limit of zero or more, never below one under zero, never
 * across zero. What is left of a line and the pieces given back of it are
 * held so, a credit line's below zero like the line itself.
 *
 * @param int $figure  Kurus
 * @param int $limit   Kurus
 * @return int  Kurus
 */
function erp_return_within($figure, $limit)
{
    $figure = (int) $figure;
    $limit = (int) $limit;

    return ($limit < 0) ? min(0, max($figure, $limit)) : max(0, min($figure, $limit));
}

/**
 * Why a return of a sale with a line below zero cannot be written as chosen;
 * '' when it can.
 *
 * Every piece is held to what is left of its line, so a return of lines at
 * zero or more stays within the sale on its own. A credit line (from a
 * product priced below zero) takes the sale below the sum of its other lines:
 * the goods without it gave back more than is left of the sale (100.00 and
 * -30.00 at 20%, invoiced at 84.00, gave back 120.00), and the credit
 * without the goods less than nothing, which no ledger takes
 * (erp_account_post() refuses an amount below zero). So while a credit line
 * has something left, what a return gives back in all, and its net and its
 * VAT at each VAT rate, lie between zero and what is left of them.
 *
 * @param array $parent_lines  from erp_returnable_lines()
 * @param array $lines         the return's lines, from erp_return_build()
 * @return string
 */
function erp_return_credit_refusal($parent_lines, $lines)
{
    $credit = null;

    foreach ($parent_lines as $parent) {
        if (((int) $parent['line_total'] < 0) && ((float) $parent['remaining_qty'] > 0.00001)) {
            $credit = $parent;
            break;
        }
    }

    if ($credit === null) {
        return '';
    }

    // Net and VAT per rate, and the whole: what is left of the sale, and
    // what the return gives back.
    $left = array('total' => 0, 'rates' => array());
    $back = array('total' => 0, 'rates' => array());

    foreach ($parent_lines as $parent) {
        $rate = sprintf('%.3F', (float) $parent['tax_rate']);
        $net = (int) $parent['line_total'] - (int) ($parent['returned_line_total'] ?? 0)
            - ((int) $parent['discount_amount'] - (int) ($parent['returned_discount'] ?? 0));
        $tax = (int) $parent['tax_total'] - (int) ($parent['returned_tax'] ?? 0);
        $withholding = (int) ($parent['withholding_amount'] ?? 0) - (int) ($parent['returned_withholding'] ?? 0);

        $left['rates'][$rate]['net'] = ($left['rates'][$rate]['net'] ?? 0) + $net;
        $left['rates'][$rate]['tax'] = ($left['rates'][$rate]['tax'] ?? 0) + $tax;
        $left['total'] += $net + $tax - $withholding;
    }

    foreach ($lines as $line) {
        $rate = sprintf('%.3F', (float) $line['tax_rate']);
        $net = (int) $line['line_total'] - (int) $line['discount_amount'];

        $back['rates'][$rate]['net'] = ($back['rates'][$rate]['net'] ?? 0) + $net;
        $back['rates'][$rate]['tax'] = ($back['rates'][$rate]['tax'] ?? 0) + (int) $line['tax_total'];
        $back['total'] += $net + (int) $line['tax_total'] - (int) $line['withholding_amount'];
    }

    $fits = (erp_return_within($back['total'], $left['total']) === $back['total']);

    foreach ($back['rates'] as $rate => $figures) {
        foreach ($figures as $name => $amount) {
            $fits = $fits && (erp_return_within($amount, $left['rates'][$rate][$name] ?? 0) === $amount);
        }
    }

    return $fits ? '' : lang(array(
        'string' => 'Line "{var:1}" is below zero, so it has to come back together with the goods it was sold with: a return can give back neither more than is left of the sale nor less than nothing, in all or at any VAT rate.',
        'vars' => array((string) $credit['description']),
    ));
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
            'tax2_total' => (int) ($parent['tax2_total'] ?? 0),
            'withholding_total' => (int) $parent['withholding_total'],
            'grand_total' => (int) $parent['grand_total'],
        );
    }

    $account_id = (int) $parent['account_id'];
    $issue_date = (string) ($data['issue_date'] ?? date('Y-m-d'));
    $issue_year = (int) substr($issue_date, 0, 4);

    if (($refusal = erp_lock_refusal($issue_date)) !== '') {
        return $fail($refusal);
    }
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

    // The return is drawn in the parent's currency at the parent's rate: it
    // gives back what the invoice asked for, and a full return has to cancel
    // the invoice's base value to the kurus as well.
    $currency = strtoupper(trim((string) $parent['currency']));
    $exchange_rate = ($currency === erp_base_currency()) ? 1.0 : (float) $parent['exchange_rate'];
    $grand_total_base = ($currency === erp_base_currency())
        ? (int) $totals['grand_total']
        : ($built['is_full'] ? (int) $parent['grand_total_base'] : erp_to_base((int) $totals['grand_total'], $exchange_rate));

    $ok = erp_query("INSERT INTO erp_invoices SET
            direction = '" . escape($parent['direction']) . "',
            doc_type = 'return',
            invoice_type = 'IADE',
            series = '" . escape($series) . "',
            number = '" . (int) $numbered['number'] . "',
            issue_year = '" . (int) $numbered['year'] . "',
            full_number = '" . escape($numbered['full']) . "',
            account_id = '" . $account_id . "',
            order_id = '" . (int) $parent['order_id'] . "',
            parent_invoice_id = '" . $parent_id . "',
            issue_date = '" . escape($issue_date) . "',
            due_date = '" . escape($issue_date) . "',
            currency = '" . escape($currency) . "',
            exchange_rate = '" . escape(number_format($exchange_rate, 6, '.', '')) . "',
            exchange_rate_date = '" . escape((string) ($parent['exchange_rate_date'] ?? $issue_date)) . "',
            exchange_rate_source = '" . escape((string) ($parent['exchange_rate_source'] ?? '')) . "',
            subtotal = '" . (int) $totals['subtotal'] . "',
            discount_total = '" . (int) $totals['discount_total'] . "',
            tax_total = '" . (int) $totals['tax_total'] . "'," . erp_tax2_total_sql($totals) . "
            withholding_total = '" . (int) $totals['withholding_total'] . "',
            grand_total = '" . (int) $totals['grand_total'] . "',
            grand_total_base = '" . $grand_total_base . "',
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

        // parent_line_id is what lets the quantity go back to the right line
        // when this return is cancelled: two lines of the same product on one
        // invoice cannot be told apart by product alone.
        $ok = erp_query("INSERT INTO erp_invoice_items SET
                invoice_id = '" . $return_id . "',
                line_no = '" . $line_no . "',
                parent_line_id = '" . (int) $line['parent_line_id'] . "',
                product_id = '" . (int) $line['product_id'] . "',
                description = '" . escape($line['description']) . "',
                quantity = '" . number_format($line['quantity'], 4, '.', '') . "',
                unit_code = '" . escape($line['unit_code']) . "',
                unit_price = '" . (int) $line['unit_price'] . "',
                discount_amount = '" . (int) $line['discount_amount'] . "',
                tax_rate = '" . escape((string) $line['tax_rate']) . "',
                tax_total = '" . (int) $line['tax_total'] . "'," . erp_tax2_line_sql($line) . "
                vat_exemption_code = '" . escape(erp_vat_line_exemption_code($line)) . "',
                withholding_code = '" . escape($line['withholding_code']) . "',
                withholding_rate = '" . escape(number_format($line['withholding_rate'], 3, '.', '')) . "',"
                . (erp_invoice_lines_have_withholding() ? "
                withholding_amount = '" . (int) $line['withholding_amount'] . "'," : "") . "
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
        'currency' => $currency,
        'exchange_rate' => $exchange_rate,
        'exchange_rate_date' => (string) ($parent['exchange_rate_date'] ?? $issue_date),
        'exchange_rate_source' => (string) ($parent['exchange_rate_source'] ?? ''),
        'amount_base' => $grand_total_base,
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

    // A foreign invoice that this return has paid off carries the gap between
    // what was invoiced, less the return, and what was collected in the base
    // currency, as it does when a receipt closes it (erp_post_receipt()). No
    // later receipt closes a paid invoice, so without this the account kept
    // the gap for good.
    if (($currency !== erp_base_currency()) && erp_fx_auto_diff()) {
        $status_now = (string) db_value("SELECT status FROM erp_invoices WHERE id = '" . $parent_id . "' LIMIT 1");

        if (($status_now === 'paid') && !erp_fx_post_difference($parent, $created_by)) {
            $error = erp_db_error();
            erp_tx_rollback();
            return $fail($error);
        }
    }

    // The goods go back the way the invoice line moved them, if it did.
    if (!erp_stock_post_return($return_id, $created_by)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    erp_stock_apply_pending();

    erp_event_invoice($return_id, 'erp.invoice.created');

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

    // A document inside a closed period stays as it was filed.
    if (($refusal = erp_lock_refusal((string) $invoice['issue_date'])) !== '') {
        return $fail($refusal);
    }

    // The gift card allocation the invoice itself posted does not count: it
    // was never an operator's decision, and it is reversed below along with
    // the document that made it.
    if (erp_invoice_receipt_count($invoice_id) > 0) {
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
        // The reversal is the document's own movement with the arrow turned,
        // so it carries the document's currency, rate and base value.
        'currency' => (string) $invoice['currency'],
        'exchange_rate' => (float) $invoice['exchange_rate'],
        'exchange_rate_date' => (string) $invoice['exchange_rate_date'],
        'exchange_rate_source' => (string) ($invoice['exchange_rate_source'] ?? ''),
        'amount_base' => (int) $invoice['grand_total_base'],
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

    // The gift card credit goes the same way: reversed by an opposite
    // movement, and its allocation removed, so the account reads as if the
    // invoice had never been raised.
    if (!$is_return && !erp_unsettle_gift_card($invoice_id, $created_by)) {
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
    // be returned again. Each return line names the parent line it was taken
    // from; a line written before that link existed falls back to the first
    // parent line with the same product, which is all it ever knew.
    if ($is_return) {
        foreach ((array) db_items("SELECT parent_line_id, product_id, quantity FROM erp_invoice_items
            WHERE invoice_id = '" . $invoice_id . "'") as $line) {

            $quantity = number_format((float) $line['quantity'], 4, '.', '');

            if ((int) $line['parent_line_id'] > 0) {
                $ok = erp_query("UPDATE erp_invoice_items
                    SET returned_qty = GREATEST(0, returned_qty - " . $quantity . ")
                    WHERE id = '" . (int) $line['parent_line_id'] . "'
                      AND invoice_id = '" . (int) $invoice['parent_invoice_id'] . "'");
            } else {
                $ok = erp_query("UPDATE erp_invoice_items
                    SET returned_qty = GREATEST(0, returned_qty - " . $quantity . ")
                    WHERE invoice_id = '" . (int) $invoice['parent_invoice_id'] . "'
                      AND product_id = '" . (int) $line['product_id'] . "'
                    ORDER BY line_no ASC, id ASC
                    LIMIT 1");
            }

            if ($ok === false) {
                $error = erp_db_error();
                erp_tx_rollback();
                return $fail($error);
            }
        }

        // The parent asks for its full amount again, so a status that the
        // return had allowed to reach paid has to be worked out afresh. A
        // foreign parent that leaves paid takes back the exchange difference
        // posted when it closed, as it does when a receipt is cancelled; the
        // return was drawn in the parent's currency. Left in place, the
        // difference made the next closing post nothing.
        if (!erp_invoice_refresh_paid((int) $invoice['parent_invoice_id'])
            || !erp_settlement_after_reopen((int) $invoice['parent_invoice_id'], (string) $invoice['currency'], (int) $created_by)) {
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

    // Every stock move the document made, turned round.
    if (!erp_stock_post_cancel($invoice_id, (int) $created_by)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (!erp_account_refresh_balance($account_id) || !erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    erp_stock_apply_pending();

    erp_event_invoice($invoice_id, 'erp.invoice.cancelled');

    return array('success' => true, 'error' => '');
}
