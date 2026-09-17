<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - turning an order into an invoice.
 *
 * The hard part is not copying the lines. It is that an order keeps four
 * figures on its header that are not lines at all:
 *
 *   shipping           a line on the invoice, priced as sold
 *   surcharge          a line on the invoice
 *   discount           NOT a line - spread across the lines it discounted
 *   gift_card_discount not a reduction of the sale at all: it is a payment,
 *                      so it leaves the invoice alone and settles it instead
 *
 * The discount is the awkward one. submit_order.php takes it off the tax at
 * header level while order_items keeps its undiscounted tax, so copying lines
 * across as they stand bills the customer for more than they paid. It is spread
 * over the lines in proportion to their totals, and the parts add up to the
 * figure on the order exactly (erp_allocate).
 *
 * Tax is carried over from the order rather than recomputed, except on a
 * discounted line, where the base itself moved and the tax has to follow it.
 * The sum is then tied back to the tax on the order's own header, because that
 * header is the document the customer actually paid against.
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
 * Has this order already been invoiced?
 *
 * One sales invoice per order, checked here rather than with a unique key: the
 * same order can carry several credit notes, so the constraint cannot sit on
 * (order_id) and MySQL has no partial index to put it on (order_id) where the
 * type is 'invoice'.
 *
 * @param int $order_id
 * @return int  Invoice id, or 0
 */
function erp_invoice_for_order($order_id)
{
    return (int) db_value("SELECT id FROM erp_invoices
        WHERE order_id = '" . (int) $order_id . "' AND direction = 'sales' AND doc_type = 'invoice'
          AND status <> 'cancelled'
        LIMIT 1");
}

/**
 * Build the invoice lines for an order, header figures included.
 *
 * Returned as plain arrays rather than written, so the caller can show them
 * before anything is saved and so this can be tested without a transaction.
 *
 * @param array $order  Order row
 * @param array $items  order_items rows (saved_for_later already excluded)
 * @return array  ['lines' => array, 'totals' => array]
 */
function erp_order_lines($order, $items)
{
    $lines = array();
    $weights = array();

    foreach ($items as $index => $item) {
        $quantity = max(1, (int) $item['quantity']);
        $unit_price = (int) $item['price'];
        $line_total = $unit_price * $quantity;
        $tax_total = (int) $item['tax_total'];

        // The rate the line was charged at, recovered from the two figures the
        // order stores. Only used to print a rate on the document; the amount
        // itself is carried over, never recomputed.
        $rate = ($line_total > 0) ? round(($tax_total * 100) / $line_total, 3) : 0;

        $lines[] = array(
            'product_id' => (int) $item['product_id'],
            'description' => (string) ($item['short_description'] ?: $item['product_name']),
            'quantity' => $quantity,
            'unit_code' => 'C62',
            'unit_price' => $unit_price,
            'discount_amount' => 0,
            'tax_rate' => $rate,
            'tax_total' => $tax_total,
            'line_total' => $line_total,
        );

        $weights[$index] = $line_total;
    }

    // The order discount, spread over the lines that earned it.
    $discount = (int) ($order['discount'] ?? 0);

    if (($discount > 0) && !empty($weights)) {
        $shares = erp_allocate($discount, $weights);

        foreach ($shares as $index => $share) {
            $lines[$index]['discount_amount'] = (int) $share;

            // A discount lowers the base the tax is charged on, so the tax is
            // taken again on what is left of the line rather than reduced by a
            // share of itself. That keeps rate x base = tax true on the line,
            // which is what an e-invoice is checked against.
            $base = $lines[$index]['line_total'] - (int) $share;
            $lines[$index]['tax_total'] = erp_apply_rate($base, $lines[$index]['tax_rate']);
        }
    }

    // Shipping and the surcharge are sold, so they are lines. Neither carries
    // tax in Pinegrap - the order's tax comes from the item lines alone.
    $shipping = (int) ($order['shipping'] ?? 0);
    if ($shipping > 0) {
        $lines[] = array(
            'product_id' => 0,
            'description' => lang('Shipping'),
            'quantity' => 1,
            'unit_code' => 'C62',
            'unit_price' => $shipping,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_total' => 0,
            'line_total' => $shipping,
        );
    }

    $surcharge = (int) ($order['surcharge'] ?? 0);
    if ($surcharge > 0) {
        $lines[] = array(
            'product_id' => 0,
            'description' => lang('Surcharge'),
            'quantity' => 1,
            'unit_code' => 'C62',
            'unit_price' => $surcharge,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_total' => 0,
            'line_total' => $surcharge,
        );
    }

    // Tie the tax back to the order.
    //
    // submit_order.php takes the discount off the tax once, on the header
    // (round(tax * discount / subtotal)). Taken line by line above, the same
    // reduction lands on the same figure in exact arithmetic, but can end a
    // kurus or two away once every line is rounded on its own. The customer
    // paid the header figure, so that is the one the invoice carries: the
    // difference goes back to the lines holding tax, largest first.
    //
    // Only rounding is absorbed here - at most a kurus per taxed line. A wider
    // gap means the order's own figures disagree with each other, which is for
    // the caller to refuse rather than for this to paper over.
    $taxed = array();
    $line_tax = 0;

    foreach ($lines as $index => $line) {
        $line_tax += $line['tax_total'];
        if ($line['tax_total'] > 0) {
            $taxed[$index] = $line['tax_total'];
        }
    }

    $residual = (int) ($order['tax'] ?? 0) - $line_tax;

    if (($residual !== 0) && !empty($taxed) && (abs($residual) <= (count($taxed) + 1))) {
        arsort($taxed);
        $indexes = array_keys($taxed);
        $step = ($residual > 0) ? 1 : -1;
        $limit = count($indexes) * 2;

        for ($i = 0; ($residual !== 0) && ($i < $limit); $i++) {
            $lines[$indexes[$i % count($indexes)]]['tax_total'] += $step;
            $residual -= $step;
        }
    }

    $subtotal = 0;
    $discount_total = 0;
    $tax_total = 0;

    foreach ($lines as $line) {
        $subtotal += $line['line_total'];
        $discount_total += $line['discount_amount'];
        $tax_total += $line['tax_total'];
    }

    $totals = array(
        'subtotal' => $subtotal,
        'discount_total' => $discount_total,
        'shipping_total' => $shipping,
        'surcharge_total' => $surcharge,
        'gift_card_total' => (int) ($order['gift_card_discount'] ?? 0),
        'tax_total' => $tax_total,
        'grand_total' => $subtotal - $discount_total + $tax_total,
    );

    return array('lines' => $lines, 'totals' => $totals);
}

/**
 * Raise the invoice for an order.
 *
 * Everything lands in one transaction: the number, the header, the lines and
 * the movement on the customer's account. A number taken and then abandoned is
 * a hole in the series, and a header without its ledger entry is a document
 * nobody owes anything against.
 *
 * @param int   $order_id
 * @param array $options  created_by, series, issue_date
 * @return array ['success' => bool, 'invoice_id' => int, 'full_number' => string, 'error' => string]
 */
function erp_invoice_from_order($order_id, $options = array())
{
    $order_id = (int) $order_id;

    $fail = function ($message) {
        return array('success' => false, 'invoice_id' => 0, 'full_number' => '', 'error' => $message);
    };

    $existing = erp_invoice_for_order($order_id);
    if ($existing > 0) {
        return $fail(lang('This order has already been invoiced.'));
    }

    $order = db_item("SELECT * FROM orders WHERE id = '" . $order_id . "' LIMIT 1");
    if (!is_array($order)) {
        return $fail(lang('Order not found.'));
    }

    $items = (array) db_items("SELECT order_items.*, products.short_description
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = '" . $order_id . "' AND order_items.saved_for_later = 0
        ORDER BY order_items.id ASC");

    if (empty($items)) {
        return $fail(lang('This order has no items.'));
    }

    $account_id = (int) ($order['erp_account_id'] ?? 0);
    if ($account_id <= 0) {
        $account_id = erp_account_for_contact((int) $order['contact_id'], (int) ($options['created_by'] ?? 0));
    }
    if ($account_id <= 0) {
        // Two different situations, and telling them apart is the difference
        // between "link this order to somebody" and "that person's record is
        // gone" - the second cannot be put right from the order screen.
        return $fail(((int) $order['contact_id'] > 0)
            ? lang('The contact this order belongs to no longer exists, so there is no account to bill.')
            : lang('This order is not linked to a contact, so there is no account to bill.'));
    }

    $built = erp_order_lines($order, $items);

    // What the customer was actually charged. A gift card is a means of
    // payment, so it is added back: the invoice states the whole sale.
    $expected = (int) $order['total'] + (int) ($order['gift_card_discount'] ?? 0);

    if ($built['totals']['grand_total'] !== $expected) {
        return $fail(lang(array(
            'string' => 'The invoice total ({var:1}) does not match the order total ({var:2}). The invoice was not created.',
            'vars' => array(erp_money_out($built['totals']['grand_total']), erp_money_out($expected)),
        )));
    }

    $series = trim((string) ($options['series'] ?? (defined('ERP_DEFAULT_SERIES') ? ERP_DEFAULT_SERIES : 'PGF')));
    $issue_date = (string) ($options['issue_date'] ?? date('Y-m-d', (int) $order['order_date']));
    if ($issue_date === '1970-01-01') {
        $issue_date = date('Y-m-d');
    }
    $issue_year = (int) substr($issue_date, 0, 4);

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $numbered = erp_next_number($series, 'sales_invoice', $issue_year);

    if (!$numbered['success']) {
        erp_tx_rollback();
        return $fail($numbered['error']);
    }

    $totals = $built['totals'];

    $ok = erp_query("INSERT INTO erp_invoices SET
            direction = 'sales',
            doc_type = 'invoice',
            invoice_type = 'SATIS',
            series = '" . escape($series) . "',
            number = '" . (int) $numbered['number'] . "',
            issue_year = '" . $issue_year . "',
            full_number = '" . escape($numbered['full']) . "',
            account_id = '" . $account_id . "',
            order_id = '" . $order_id . "',
            issue_date = '" . escape($issue_date) . "',
            due_date = '" . escape($issue_date) . "',
            currency = 'TRY',
            subtotal = '" . (int) $totals['subtotal'] . "',
            discount_total = '" . (int) $totals['discount_total'] . "',
            shipping_total = '" . (int) $totals['shipping_total'] . "',
            surcharge_total = '" . (int) $totals['surcharge_total'] . "',
            gift_card_total = '" . (int) $totals['gift_card_total'] . "',
            tax_total = '" . (int) $totals['tax_total'] . "',
            grand_total = '" . (int) $totals['grand_total'] . "',
            grand_total_try = '" . (int) $totals['grand_total'] . "',
            status = 'issued',
            is_internet_sale = 1,
            web_address = '" . escape(defined('ERP_WEB_ADDRESS') ? ERP_WEB_ADDRESS : '') . "',
            created_by = '" . (int) ($options['created_by'] ?? 0) . "',
            created_at = '" . time() . "',
            updated_at = '" . time() . "'");

    if ($ok === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    $invoice_id = (int) mysqli_insert_id(db::$con);
    $line_no = 0;

    foreach ($built['lines'] as $line) {
        $line_no++;
        $ok = erp_query("INSERT INTO erp_invoice_items SET
                invoice_id = '" . $invoice_id . "',
                line_no = '" . $line_no . "',
                product_id = '" . (int) $line['product_id'] . "',
                description = '" . escape($line['description']) . "',
                quantity = '" . (int) $line['quantity'] . "',
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
    }

    $posted = erp_account_post(array(
        'account_id' => $account_id,
        'doc_date' => $issue_date,
        'kind' => 'invoice',
        'direction' => 'debit',
        'amount' => (int) $totals['grand_total'],
        'currency' => 'TRY',
        'doc_type' => 'invoice',
        'doc_id' => $invoice_id,
        'description' => $numbered['full'],
        'created_by' => (int) ($options['created_by'] ?? 0),
    ));

    if (($posted === false) || !erp_account_refresh_balance($account_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (erp_query("UPDATE orders SET erp_invoice_id = '" . $invoice_id . "', erp_account_id = '" . $account_id . "'
        WHERE id = '" . $order_id . "'") === false) {
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
