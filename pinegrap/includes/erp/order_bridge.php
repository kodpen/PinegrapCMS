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
 * The rate printed on a line is the legal rate the line was charged at, not
 * the ratio of two rounded kurus figures: 361 / 2008 reads as 17.978%, and an
 * e-document with that on it is wrong even though the amounts are right.
 * order_items does not keep the rate, so it is recovered (erp_line_tax_rate).
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

        // The rate the line was charged at. The amount itself is carried over,
        // never recomputed; the rate is what a discounted line's tax is taken
        // again at, and what the document prints.
        $rate = erp_line_tax_rate($line_total, $tax_total, $item['product_tax_rate'] ?? null);

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

    // The shape of the header figures, which erp_invoices stores as they are:
    //
    //   subtotal        the sum of every line's line_total, shipping and
    //                   surcharge lines included - they are sold, so they are
    //                   lines like any other
    //   discount_total  the sum of the lines' discount_amount
    //   tax_total       the sum of the lines' tax_total
    //   grand_total     subtotal - discount_total + tax_total
    //
    // shipping_total and surcharge_total say how much of the subtotal those
    // two lines account for; they are informational and are NOT added again.
    // A reader that sums subtotal + shipping_total + tax_total counts the
    // shipping twice. gift_card_total is not part of the sale at all - it is
    // how part of the grand total was paid.
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
 * The rate a line was taxed at, from the figures the order kept.
 *
 * order_items stores the line total and the tax on it, both whole kurus, and
 * not the rate. Dividing one by the other gives back the rate plus the
 * rounding that went into the tax: 361 / 2008 is 17.978%, not the 18% the
 * customer was charged. That figure is wrong on a document, and taking a
 * returned share of the line at it lands a kurus short.
 *
 * The rate is therefore the simplest one that reproduces the stored tax
 * exactly, through the same rounding the order used (erp_apply_rate). The
 * product's own rate is tried first, because when it still explains the tax it
 * is the rate that was charged. Otherwise the ratio is tried at zero, one, two
 * and then three decimals, so 18 wins over 18.001 and 8.25 over 8.253. A tax
 * that no rate with three decimals explains is left as the plain ratio, which
 * is as much as the two figures can say.
 *
 * @param int               $line_total    Kurus, undiscounted
 * @param int               $tax_total     Kurus of tax on that total
 * @param string|float|null $product_rate  products.tax_rate, NULL when the zone decided
 * @return float  Percentage
 */
function erp_line_tax_rate($line_total, $tax_total, $product_rate = null)
{
    $line_total = (int) $line_total;
    $tax_total = (int) $tax_total;

    if (($line_total <= 0) || ($tax_total <= 0)) {
        return 0;
    }

    if (($product_rate !== null) && ($product_rate !== '')) {
        $candidate = (float) $product_rate;
        if (erp_apply_rate($line_total, $candidate) === $tax_total) {
            return $candidate;
        }
    }

    $ratio = ($tax_total * 100) / $line_total;

    for ($decimals = 0; $decimals <= 3; $decimals++) {
        $candidate = round($ratio, $decimals);
        if (erp_apply_rate($line_total, $candidate) === $tax_total) {
            return $candidate;
        }
    }

    return round($ratio, 3);
}

/**
 * Map an order's payment method to the e-archive payment method vocabulary.
 *
 * The codes are the OdemeSekli values of the GIB e-Arsiv technical guide's
 * internet sales section. PayPal and Iyzico are payment intermediaries: they
 * collect the money and settle it later, so they are not reported as a card.
 *
 * @param string $order_payment_method  orders.payment_method
 * @return string  Code, or '' when the method is unknown
 */
function erp_payment_method_code($order_payment_method)
{
    switch ((string) $order_payment_method) {
        case 'Credit/Debit Card':
            return 'KREDIKARTI/BANKAKARTI';
        case 'PayPal Express Checkout':
        case 'Pay With Iyzico':
            return 'ODEMEARACISI';
        case 'Offline Payment':
            return 'EFT/HAVALE';
    }

    return '';
}

/**
 * Display labels for the e-archive payment method codes.
 *
 * @return array  code => label
 */
function erp_payment_method_labels()
{
    return array(
        'KREDIKARTI/BANKAKARTI' => lang('Credit / debit card'),
        'EFT/HAVALE' => lang('Bank transfer'),
        'KAPIDAODEME' => lang('Cash on delivery'),
        'ODEMEARACISI' => lang('Payment intermediary'),
        'DIGER' => lang('Other'),
    );
}

/**
 * When an order left and who carried it.
 *
 * An order can have several recipients, each with its own ship date and
 * shipping method. The earliest ship date is the date the sale was shipped,
 * and the carrier is the one of that recipient. When nothing has shipped yet
 * the date stays zero, but the carrier is still reported from the first
 * recipient with a shipping method: it is chosen at checkout, well before
 * the goods leave. The carrier's registered title falls back to the name of
 * the shipping method when it was not filled in.
 *
 * Checkout stores an estimated ship date for each recipient when the order is
 * placed, and the order screen is where it is corrected once the goods have
 * actually left. A date that has not arrived yet cannot be the day the goods
 * left, so it is not reported and the invoice carries no shipment date until
 * then.
 *
 * @param int $order_id
 * @return array ['shipment_date' => 'Y-m-d' or '0000-00-00', 'carrier_title' => string, 'carrier_vkn' => string]
 */
function erp_order_shipment($order_id)
{
    $shipment = array('shipment_date' => '0000-00-00', 'carrier_title' => '', 'carrier_vkn' => '');

    $recipients = (array) db_items("SELECT ship_tos.ship_date, ship_tos.shipping_method_id,
            shipping_methods.name, shipping_methods.carrier_title, shipping_methods.carrier_vkn
        FROM ship_tos
        LEFT JOIN shipping_methods ON ship_tos.shipping_method_id = shipping_methods.id
        WHERE ship_tos.order_id = '" . (int) $order_id . "' AND ship_tos.complete = 1
        ORDER BY ship_tos.id ASC");

    $shipped = null;
    $with_method = null;
    $today = date('Y-m-d');

    foreach ($recipients as $recipient) {
        $ship_date = (string) ($recipient['ship_date'] ?? '');
        if ($ship_date !== '' && $ship_date !== '0000-00-00' && $ship_date <= $today
            && ($shipped === null || $ship_date < $shipped['ship_date'])) {
            $shipped = $recipient;
        }
        if ($with_method === null && (int) ($recipient['shipping_method_id'] ?? 0) > 0) {
            $with_method = $recipient;
        }
    }

    $carrier = ($shipped !== null && (int) ($shipped['shipping_method_id'] ?? 0) > 0) ? $shipped : $with_method;

    if ($shipped !== null) {
        $shipment['shipment_date'] = (string) $shipped['ship_date'];
    }
    if ($carrier !== null) {
        $title = trim((string) ($carrier['carrier_title'] ?? ''));
        $shipment['carrier_title'] = ($title !== '') ? $title : trim((string) ($carrier['name'] ?? ''));
        $shipment['carrier_vkn'] = trim((string) ($carrier['carrier_vkn'] ?? ''));
    }

    return $shipment;
}

/**
 * The date the customer paid for an order.
 *
 * orders.paid_at is the record of it. Orders placed before that column was
 * written have only the transaction reference: a gateway that returned one
 * confirmed the payment at the moment the order was placed, so the order date
 * is the payment date. Without either there is no confirmed payment to date.
 *
 * @param array $order  The orders row
 * @return string  'Y-m-d', or '0000-00-00'
 */
function erp_order_payment_date($order)
{
    if ((int) ($order['paid_at'] ?? 0) > 0) {
        return date('Y-m-d', (int) $order['paid_at']);
    }
    if (trim((string) ($order['transaction_id'] ?? '')) !== '' && (int) ($order['order_date'] ?? 0) > 0) {
        return date('Y-m-d', (int) $order['order_date']);
    }

    return '0000-00-00';
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

    $items = (array) db_items("SELECT order_items.*, products.short_description,
            products.tax_rate AS product_tax_rate
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

    // A sale over the counter is not an internet sale, whatever else the order
    // carries. Everything that came in over the wire (web checkout,
    // marketplace, or the default type of an older row) is one, and only an
    // internet sale states the address it was made at: the ERP setting, or
    // failing that the site's own address.
    $is_internet_sale = (($order['type'] ?? '') === 'local') ? 0 : 1;
    $web_address = '';
    if ($is_internet_sale === 1) {
        $web_address = trim((string) (defined('ERP_WEB_ADDRESS') ? ERP_WEB_ADDRESS : ''));
        if ($web_address === '' && defined('URL_SCHEME') && defined('HOSTNAME')) {
            $web_address = URL_SCHEME . HOSTNAME;
        }
    }
    $shipment = erp_order_shipment($order_id);

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
            is_internet_sale = '" . $is_internet_sale . "',
            payment_method = '" . escape(erp_payment_method_code($order['payment_method'] ?? '')) . "',
            payment_date = '" . escape(erp_order_payment_date($order)) . "',
            shipment_date = '" . escape($shipment['shipment_date']) . "',
            carrier_title = '" . escape($shipment['carrier_title']) . "',
            carrier_vkn = '" . escape($shipment['carrier_vkn']) . "',
            web_address = '" . escape($web_address) . "',
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
