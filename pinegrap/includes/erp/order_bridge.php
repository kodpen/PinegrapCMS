<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - turning an order into an invoice.
 *
 * The hard part is not copying the lines. It is that an order keeps four
 * figures on its header that are not lines at all:
 *
 *   shipping           a line on the invoice, for the amount charged; taxed
 *                      at the rate of the goods, the tax inside that amount,
 *                      unless the store leaves it untaxed (erp_order_charge_lines)
 *   surcharge          the same
 *   installment_charges the card's installment charge (taksit farkı), already
 *                      in orders.total: the same, a line of its own
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
 * discounted order, where the base itself moved and the tax is taken again per
 * rate on what is left (erp_rate_vat(), as the checkout takes it). The sum is
 * then tied back to the tax on the order's own header, because that header is
 * the document the customer actually paid against. A charge's tax is taken
 * with the goods of its rate, on their sum (erp_order_charge_lines()).
 *
 * The rate printed on a line is the legal rate the line was charged at, not
 * the ratio of two rounded kurus figures: 361 / 2008 reads as 17.978%, and an
 * e-document with that on it is wrong even though the amounts are right. The
 * checkout keeps it on the line (order_items.tax_rate); for a line placed
 * before that it is recovered (erp_line_tax_rate).
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
 * The order statuses that are a finished sale and can be invoiced.
 *
 * 'exported' is a complete order that has since been exported (to an
 * accounting file, to Paraşüt); it is still the same sale, and the rest of
 * the software counts both as one (reports, offers, the customer's account).
 *
 * @return string[]
 */
function erp_order_billable_statuses()
{
    return array('complete', 'exported');
}

/**
 * The same list as a SQL condition.
 *
 * @param string $column
 * @return string
 */
function erp_order_billable_sql($column = 'orders.status')
{
    return $column . " IN ('" . implode("', '", erp_order_billable_statuses()) . "')";
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
 * The order's lines the checkout charged when the order was placed.
 *
 * A recurring line that starts after the order day is not charged with the
 * order - its first payment is taken on its start date - and on the
 * ClearCommerce gateway no recurring line is, since the gateway bills them
 * itself: the rule submit_order.php totals the order by
 * (pg_order_line_charged_today()). orders.total, tax and discount cover the
 * charged lines alone, so the invoice, and a card refund before there is one,
 * carry those lines alone. With the others in, the invoice came to more than
 * the order and was refused, and the discount and the shipping tax were shared
 * with lines nobody had paid for.
 *
 * The day is the order's, not today's: a line that started between the order
 * and the invoice was still no part of what the order charged. The line's own
 * recurring_payment_period says whether it was recurring when ordered (the
 * product may have changed since); its start date was written at checkout,
 * and a line without one starts the product's start days after the order day,
 * as it would have at checkout.
 *
 * @param array     $order          orders row (order_date)
 * @param array     $items          order_items rows joined with the product, product_start
 *                                  being products.start
 * @param bool|null $clearcommerce  The gateway charges recurring lines itself; null reads
 *                                  the store's gateway
 * @return array  The charged rows, in their order, indexed from 0 again
 */
function erp_order_charged_items($order, $items, $clearcommerce = null)
{
    if ($clearcommerce === null) {
        $clearcommerce = defined('ECOMMERCE_CREDIT_DEBIT_CARD') && ECOMMERCE_CREDIT_DEBIT_CARD
            && defined('ECOMMERCE_PAYMENT_GATEWAY') && (ECOMMERCE_PAYMENT_GATEWAY == 'ClearCommerce');
    }

    $order_day = date('Y-m-d', (int) ($order['order_date'] ?? time()));
    $charged = array();

    foreach ((array) $items as $item) {
        $recurring = (trim((string) ($item['recurring_payment_period'] ?? '')) !== '');

        if (pg_order_line_charged_today($recurring, (string) ($item['recurring_start_date'] ?? ''),
            (int) ($item['product_start'] ?? 0), $order_day, (bool) $clearcommerce)) {
            $charged[] = $item;
        }
    }

    return $charged;
}

/**
 * Build the invoice lines for an order, header figures included.
 *
 * Returned as plain arrays rather than written, so the caller can show them
 * before anything is saved and so this can be tested without a transaction.
 *
 * The order's invoice asks for $settle_charges: a charge - the shipping, the
 * surcharge, the instalment charge - that leaves the total of a rate one the
 * VAT, taken on the whole, never reaches goes on the invoice the fewest kurus
 * lower that reach it (erp_order_charge_lines()). The checkout keeps what it
 * charges reachable (pg_order_charges_reachable(), the iyzico card's
 * instalment charge); this settles an order placed before that rule and an
 * instalment charge a payment service set itself (Pay with iyzico). The
 * invoice then comes to that much less than the customer paid
 * (totals['charges_left']); the kurus stays with the payment, on the
 * customer's account. A refund of the order's lines leaves it off and gives
 * back what was paid.
 *
 * @param array     $order           Order row
 * @param array     $items           order_items rows (saved_for_later already excluded)
 * @param bool|null $charges_taxed   The shipping and the surcharges hold tax; null asks the
 *                                   store (erp_shipping_taxed())
 * @param bool      $settle_charges  Lower a charge that leaves a rate out of reach
 * @return array  ['lines' => array, 'totals' => array]
 */
function erp_order_lines($order, $items, $charges_taxed = null, $settle_charges = false)
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
        // again at, and what the document prints. The checkout keeps it on the
        // line (order_items.tax_rate, 11.0.59); a line written before that, or
        // by a path that does not set it, has it worked back out.
        $stored_rate = $item['tax_rate'] ?? null;
        $rate = (($stored_rate !== null) && ($stored_rate !== ''))
            ? (float) $stored_rate
            : erp_line_tax_rate($line_total, $tax_total, $item['product_tax_rate'] ?? null);

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
        }

        // A discount lowers the base the tax is charged on, so the tax is
        // taken again on what is left of the lines rather than reduced by a
        // share of itself: once per rate on the sum of the discounted lines,
        // spread back over them (erp_rate_vat()), the way the checkout takes
        // an order's tax and an e-document provider works it out.
        $taxed = array();

        foreach ($lines as $index => $line) {
            $taxed[$index] = array(
                'net' => $line['line_total'] - $line['discount_amount'],
                'tax_rate' => $line['tax_rate'],
            );
        }

        foreach (erp_rate_vat($taxed)['lines'] as $index => $figures) {
            $lines[$index]['tax_total'] = $figures['vat'];
        }
    }

    // Tie the tax back to the order.
    //
    // The checkout takes an order's tax the way it is taken above: per rate
    // on the lines, and after a discount per rate again on what the discount
    // leaves of them, shared alike (update_order_item_taxes(),
    // pg_order_charged_tax()); the lines then meet the header. An order
    // placed before that had its tax taken line by line and its discount taken
    // off the header's tax (round(tax * discount / subtotal)), which can end a
    // kurus or two away from the figure here. The customer paid the header
    // figure, so that is the one the invoice carries: the difference goes back
    // to the lines holding tax, largest first.
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

    // Shipping and the surcharge are sold, so they are lines, after the goods
    // and after the tie-back above: the order's tax is the goods' tax alone,
    // and the tax these lines carry lies inside the amount charged for them.
    // The installment charge the card payment added is in orders.total as
    // well; without its line the invoice fell short of every installment
    // order and was refused. The document has no column of its own for it,
    // so surcharge_total counts it with the surcharge.
    $charged = array(
        'shipping' => array(lang('Shipping'), (int) ($order['shipping'] ?? 0)),
        'surcharge' => array(lang('Surcharge'), (int) ($order['surcharge'] ?? 0)),
        'installment' => array(lang('Installment Charge'), (int) ($order['installment_charges'] ?? 0)),
    );
    $charges = erp_order_charge_lines($lines, $charged, $charges_taxed, $settle_charges ? array_keys($charged) : array());

    // What of the charges the invoice leaves to the payment.
    $charges_left = 0;
    foreach ($charged as $name => $charge) {
        $charges_left += max(0, (int) $charge[1]);

        foreach ($charges[$name] as $line) {
            $charges_left -= (int) $line['line_total'] + (int) $line['tax_total'];
        }
    }

    $shipping_lines = $charges['shipping'];
    $surcharge_lines = array_merge($charges['surcharge'], $charges['installment']);

    $shipping = 0;
    foreach ($shipping_lines as $line) {
        $shipping += $line['line_total'];
    }

    $surcharge = 0;
    foreach ($surcharge_lines as $line) {
        $surcharge += $line['line_total'];
    }

    $lines = array_merge($lines, $shipping_lines, $surcharge_lines);

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
    // how part of the grand total was paid. charges_left is the part of the
    // charges the invoice leaves to the payment (above): the order's total
    // and gift card come to the grand total plus it.
    $totals = array(
        'subtotal' => $subtotal,
        'discount_total' => $discount_total,
        'shipping_total' => $shipping,
        'surcharge_total' => $surcharge,
        'gift_card_total' => (int) ($order['gift_card_discount'] ?? 0),
        'tax_total' => $tax_total,
        'grand_total' => $subtotal - $discount_total + $tax_total,
        'charges_left' => max(0, $charges_left),
    );

    return array('lines' => $lines, 'totals' => $totals);
}

/**
 * The invoice lines for the charges the order keeps on its header: the
 * shipping, the surcharge, the installment charge.
 *
 * The checkout adds no tax to them, so the amount the customer paid is the
 * whole charge. When the store taxes these charges (erp_shipping_taxed()) the
 * tax is inside that amount and follows the goods: at their rate, and on an
 * order of goods at several rates each charge is shared between the rates in
 * proportion to what the goods of each rate come to after the discount
 * (pg_order_charge_shares(), the rule the checkout settles its charges by),
 * one line per rate. Untaxed, or with no goods left after the discount, a
 * charge is one line at 0. Either way a charge's lines add up to the amount
 * charged, so the invoice total still meets the order's.
 *
 * The tax is taken with the goods of the rate, on the sum of every line at
 * the rate, the way an e-document provider that works the tax out from the
 * rates takes it. Split out of a charge on its own (erp_split_gross()) it can
 * come a kurus apart from that: 1.03 at 20% with 0.21 of tax and shipping
 * 1.00 split alone as 0.83 + 0.17 put 0.38 of tax on 1.86, where the tax on
 * 1.86 is 0.37. So for each rate the charges' net is the amount that, with
 * the goods' net, comes to the rate's total once the tax is taken on the
 * whole (erp_rate_amount()), less the goods' net: the shipping above is
 * 0.84 + 0.16, 0.37 on 1.87, the total 2.24 as before. The difference to the
 * split goes onto the rate's largest charge line.
 *
 * A rate whose total no amount reaches keeps the split: 57.93 at 20%, from an
 * order placed before the checkout settled its charges
 * (pg_order_charges_reachable()) or an installment charge the payment added.
 * So does one that would move a charge further than its lines' roundings
 * explain (a kurus a line, and one). A charge named in $lower is taken
 * instead the fewest kurus lower (at most ten) that leave every rate it is
 * shared by reachable, as the checkout lowers the shipping: its lines then
 * come to that much less than was charged.
 *
 * @param array     $item_lines  The order's goods lines, discount applied, their tax final
 * @param array     $charges     name => [label, int kurus charged], in the order they are placed
 * @param bool|null $taxed       The charges hold tax; null asks the store (erp_shipping_taxed())
 * @param string[]  $lower       The charges that may go lower to keep the rates reachable
 * @return array  name => lines in erp_order_lines() shape
 */
function erp_order_charge_lines($item_lines, $charges, $taxed = null, $lower = array())
{
    $out = array();
    $any = false;

    foreach ($charges as $name => $charge) {
        $out[$name] = array();
        $any = $any || ((int) $charge[1] > 0);
    }

    if (!$any) {
        return $out;
    }

    if ($taxed === null) {
        $taxed = erp_shipping_taxed();
    }

    // Each rate's goods: what they come to after the discount and their
    // total with the tax. The rates a charge is shared by are those whose
    // goods come to something, in the order they first do so on the lines.
    $goods_net = array();
    $goods_total = array();
    $shared = array();

    foreach ((array) $item_lines as $line) {
        $key = number_format(max(0, (float) $line['tax_rate']), 3, '.', '');
        $base = (int) $line['line_total'] - (int) $line['discount_amount'];
        $goods_net[$key] = ($goods_net[$key] ?? 0) + $base;
        $goods_total[$key] = ($goods_total[$key] ?? 0) + $base + (int) $line['tax_total'];

        if ($base > 0) {
            if (!isset($shared[$key])) {
                $shared[$key] = array('rate' => (float) $key, 'net' => 0, 'total' => 0);
            }

            $shared[$key]['net'] += $base;
        }
    }

    foreach ($shared as $key => $group) {
        $shared[$key]['total'] = $goods_total[$key];
    }

    // The lines of each charge, and which of them stand at each rate.
    $members = array();

    foreach ($charges as $name => $charge) {
        $label = (string) $charge[0];
        $gross = (int) $charge[1];

        if ($gross <= 0) {
            continue;
        }

        $shares = ($taxed && !empty($shared)) ? pg_order_charge_shares($gross, $shared) : array('0.000' => $gross);

        if ($taxed && !empty($shared) && in_array($name, (array) $lower, true)) {
            for ($less = 0; ($less <= 10) && ($less < $gross); $less++) {
                $tried = pg_order_charge_shares($gross - $less, $shared);
                $reached = true;

                foreach ($tried as $key => $share) {
                    if (((int) $share > 0) && ($shared[$key]['rate'] > 0)
                        && (erp_rate_amount($shared[$key]['total'] + (int) $share, $shared[$key]['rate']) === null)) {
                        $reached = false;
                        break;
                    }
                }

                if ($reached) {
                    $shares = $tried;
                    break;
                }
            }
        }

        $several = (count(array_filter($shares)) > 1);

        foreach ($shares as $key => $share) {
            $share = (int) $share;

            if ($share <= 0) {
                continue;
            }

            if (isset($shared[$key])) {
                $shared[$key]['total'] += $share;
            }

            $rate = (float) $key;
            $split = erp_split_gross($share, $rate);
            $members[$key][] = array($name, count($out[$name]));

            $out[$name][] = array(
                'product_id' => 0,
                'description' => $several ? ($label . ' (' . erp_percent_text($rate) . ')') : $label,
                'quantity' => 1,
                'unit_code' => 'C62',
                'unit_price' => $split['net'],
                'discount_amount' => 0,
                'tax_rate' => $rate,
                'tax_total' => $split['tax'],
                'line_total' => $split['net'],
            );
        }
    }

    // The tax taken on the whole of each rate.
    foreach ($members as $key => $list) {
        $rate = (float) $key;

        if (($rate <= 0) || !isset($shared[$key])) {
            continue;
        }

        $amount = erp_rate_amount($shared[$key]['total'], $rate);

        if ($amount === null) {
            continue;
        }

        $charge_net = 0;
        $largest = null;
        $largest_gross = -1;

        foreach ($list as $member) {
            $line = $out[$member[0]][$member[1]];
            $gross = (int) $line['line_total'] + (int) $line['tax_total'];
            $charge_net += (int) $line['line_total'];

            if ($gross > $largest_gross) {
                $largest = $member;
                $largest_gross = $gross;
            }
        }

        $move = $amount - $goods_net[$key] - $charge_net;

        if (($move === 0) || (abs($move) > count($list) + 1)) {
            continue;
        }

        $net = (int) $out[$largest[0]][$largest[1]]['line_total'] + $move;
        $tax = (int) $out[$largest[0]][$largest[1]]['tax_total'] - $move;

        if (($net < 0) || ($tax < 0)) {
            continue;
        }

        $out[$largest[0]][$largest[1]]['line_total'] = $net;
        $out[$largest[0]][$largest[1]]['unit_price'] = $net;
        $out[$largest[0]][$largest[1]]['tax_total'] = $tax;
    }

    return $out;
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
 * The e-archive payment method of an order. An order the customer put on
 * their account (orders.erp_on_account, 10.60) is stored as an offline
 * payment, but no transfer is made for it: it is a sale on account, which
 * the vocabulary knows only as DIGER.
 *
 * @param array $order  The orders row
 * @return string  Code, or '' when the method is unknown
 */
function erp_order_payment_method_code($order)
{
    if (!empty($order['erp_on_account'])) {
        return 'DIGER';
    }

    return erp_payment_method_code($order['payment_method'] ?? '');
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
 * Whether an order is an internet sale, and the address it was made at.
 *
 * A sale over the counter is not an internet sale, whatever else the order
 * carries. Everything that came in over the wire (web checkout,
 * marketplace, or the default type of an older row) is one, and only an
 * internet sale states the address it was made at: the ERP setting, or
 * failing that the site's own address.
 *
 * @param array $order  The orders row (type)
 * @return array ['is_internet_sale' => 0|1, 'web_address' => string]
 */
function erp_order_internet_sale($order)
{
    return _erp_order_internet_sale(
        (string) ($order['type'] ?? ''),
        defined('ERP_WEB_ADDRESS') ? (string) ERP_WEB_ADDRESS : '',
        (defined('URL_SCHEME') && defined('HOSTNAME')) ? URL_SCHEME . HOSTNAME : ''
    );
}

/**
 * erp_order_internet_sale() with the setting and the site's address passed
 * in.
 *
 * @param string $type     orders.type
 * @param string $setting  ERP_WEB_ADDRESS
 * @param string $site     The site's own address (URL_SCHEME . HOSTNAME)
 * @return array ['is_internet_sale' => 0|1, 'web_address' => string]
 */
function _erp_order_internet_sale($type, $setting, $site)
{
    if ((string) $type === 'local') {
        return array('is_internet_sale' => 0, 'web_address' => '');
    }

    $web_address = trim((string) $setting);

    return array('is_internet_sale' => 1, 'web_address' => ($web_address !== '') ? $web_address : trim((string) $site));
}

/**
 * The VUK 509 fields of an order's sale, as an e-Arşiv invoice states them:
 * whether it is an internet sale and its address, how and when it was paid,
 * when the goods left and who carried them. The order's ERP invoice stores
 * them (erp_invoice_from_order_locked()); the order screen's own Paraşüt
 * button sends them with its e-Arşiv (parasut_create_invoice()).
 *
 * @param array $order  The orders row
 * @return array is_internet_sale, web_address, payment_method, payment_date,
 *               shipment_date, carrier_title, carrier_vkn
 */
function erp_order_sale_fields($order)
{
    return erp_order_internet_sale($order) + array(
        'payment_method' => erp_order_payment_method_code($order),
        'payment_date' => erp_order_payment_date($order),
    ) + erp_order_shipment((int) ($order['id'] ?? 0));
}

/**
 * The name of an order's invoicing lock, as SQL.
 *
 * GET_LOCK names belong to the whole MySQL server, so the database is part of
 * the name and two sites on one server never wait for each other's orders.
 * Hashed, because a long database name would otherwise push the order id past
 * the 64 characters a name may have.
 *
 * @param int $order_id
 * @return string  An SQL expression
 */
function erp_order_invoice_lock_sql($order_id)
{
    return "CONCAT('pg_erp_invoice.', MD5(CONCAT(DATABASE(), '.', '" . (int) $order_id . "')))";
}

/**
 * Take or give back an order's invoicing lock.
 *
 * Through erp_query(), which reports a failure instead of ending the request:
 * a server that refuses named locks must not stop an invoice from being
 * written, it only loses the protection the lock adds.
 *
 * @param int  $order_id
 * @param bool $take  true takes it (waiting up to 10 seconds), false releases it
 * @return int  1 held, 0 another request holds it, -1 no lock on this server
 */
function erp_order_invoice_lock($order_id, $take = true)
{
    $result = erp_query($take
        ? "SELECT GET_LOCK(" . erp_order_invoice_lock_sql($order_id) . ", 10)"
        : "SELECT RELEASE_LOCK(" . erp_order_invoice_lock_sql($order_id) . ")");

    if (!($result instanceof mysqli_result)) {
        return -1;
    }

    $row = mysqli_fetch_row($result);

    if (!is_array($row) || ($row[0] === null)) {
        return -1;
    }

    return ((int) $row[0] === 1) ? 1 : 0;
}

/**
 * Raise the invoice for an order.
 *
 * Two requests invoicing the same order at once - a second click, the order
 * screen's button beside the invoice the order gets on its own
 * (erp_auto_invoice_for_order()) - would each find no invoice and each write
 * one. The look and the write run under the order's lock, so the second one
 * waits and then finds the first one's invoice.
 *
 * @param int   $order_id
 * @param array $options  created_by, series, issue_date
 * @return array ['success' => bool, 'invoice_id' => int, 'full_number' => string, 'error' => string]
 */
function erp_invoice_from_order($order_id, $options = array())
{
    $order_id = (int) $order_id;

    $locked = erp_order_invoice_lock($order_id);

    if ($locked === 0) {
        return array('success' => false, 'invoice_id' => 0, 'full_number' => '',
            'error' => lang('This order is being invoiced right now. Look at it again in a moment.'));
    }

    // Given back whatever happens inside: a lock left behind would hold every
    // later attempt on this order for as long as the connection lives.
    try {
        $result = erp_invoice_from_order_locked($order_id, $options);
    } finally {
        if ($locked === 1) {
            erp_order_invoice_lock($order_id, false);
        }
    }

    return $result;
}

/**
 * erp_invoice_from_order(), once the order's lock is held.
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
function erp_invoice_from_order_locked($order_id, $options = array())
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

    // Only a finished sale is invoiced. Checked here, not only by the screens
    // that offer the step: a delivery note written before the order was
    // cancelled, or a form opened before it, still reaches this writer.
    if (!in_array((string) $order['status'], erp_order_billable_statuses(), true)) {
        return $fail(((string) $order['status'] === 'cancelled')
            ? lang('A cancelled order is not invoiced.')
            : lang('An order is invoiced once it is complete.'));
    }

    $items = (array) db_items("SELECT order_items.*, products.short_description,
            products.tax_rate AS product_tax_rate, products.start AS product_start
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = '" . $order_id . "' AND order_items.saved_for_later = 0
        ORDER BY order_items.id ASC");

    if (empty($items)) {
        return $fail(lang('This order has no items.'));
    }

    // The invoice states what the order charged: a recurring line that had
    // not started yet is billed when its first payment is taken.
    $items = erp_order_charged_items($order, $items);

    if (empty($items)) {
        return $fail(lang('None of the lines of this order was charged when it was placed, so there is nothing to invoice yet.'));
    }

    $account_id = (int) ($order['erp_account_id'] ?? 0);
    if ($account_id <= 0) {
        $account_id = erp_account_for_contact((int) $order['contact_id'], (int) ($options['created_by'] ?? 0), $order);
    }
    if ($account_id <= 0) {
        // Two different situations, and telling them apart is the difference
        // between "link this order to somebody" and "that person's record is
        // gone" - the second cannot be put right from the order screen.
        return $fail(((int) $order['contact_id'] > 0)
            ? lang('The contact this order belongs to no longer exists, so there is no account to bill.')
            : lang('This order is not linked to a contact, so there is no account to bill.'));
    }

    $built = erp_order_lines($order, $items, null, true);

    // What the customer was actually charged. A gift card is a means of
    // payment, so it is added back: the invoice states the whole sale, and the
    // gift card settles its share of it once the document exists. A charge
    // that left a rate's total out of reach is invoiced the kurus lower that
    // reaches it (erp_order_lines()); that kurus stays with the payment.
    $expected = (int) $order['total'] + (int) ($order['gift_card_discount'] ?? 0) - (int) $built['totals']['charges_left'];

    if ($built['totals']['grand_total'] !== $expected) {
        return $fail(lang(array(
            'string' => 'The invoice total ({var:1}) does not match the order total ({var:2}). The invoice was not created.',
            'vars' => array(erp_money_out($built['totals']['grand_total']), erp_money_out($expected)),
        )));
    }

    $series = trim((string) ($options['series'] ?? (defined('ERP_DEFAULT_SERIES') ? ERP_DEFAULT_SERIES : 'PGF')));
    // The invoice is dated the day it is issued, not the day of the order. An
    // order invoiced weeks later would otherwise carry a date older than
    // documents already issued, and GİB takes e-documents of one type only in
    // date order ("Girilen tarihten sonra aynı tipte fatura kesilmiş"); a
    // back-dated invoice also falls outside the seven days VUK allows after
    // delivery. A caller that needs another date still passes issue_date.
    $issue_date = (string) ($options['issue_date'] ?? date('Y-m-d'));
    if (($issue_date === '1970-01-01') || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $issue_date)) {
        $issue_date = date('Y-m-d');
    }
    $issue_year = (int) substr($issue_date, 0, 4);

    if (($refusal = erp_lock_refusal($issue_date)) !== '') {
        return $fail($refusal);
    }

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $numbered = erp_next_number($series, 'sales_invoice', $issue_year);

    if (!$numbered['success']) {
        erp_tx_rollback();
        return $fail($numbered['error']);
    }

    $totals = $built['totals'];
    $sale = erp_order_sale_fields($order);

    $ok = erp_query("INSERT INTO erp_invoices SET
            direction = 'sales',
            doc_type = 'invoice',
            invoice_type = 'SATIS',
            series = '" . escape($series) . "',
            number = '" . (int) $numbered['number'] . "',
            issue_year = '" . (int) $numbered['year'] . "',
            full_number = '" . escape($numbered['full']) . "',
            account_id = '" . $account_id . "',
            order_id = '" . $order_id . "',
            issue_date = '" . escape($issue_date) . "',
            due_date = '" . escape(erp_account_due_date($account_id, $issue_date)) . "',
            currency = '" . escape(erp_base_currency()) . "',
            exchange_rate = '1.000000',
            exchange_rate_date = '" . escape($issue_date) . "',
            exchange_rate_source = 'base',
            subtotal = '" . (int) $totals['subtotal'] . "',
            discount_total = '" . (int) $totals['discount_total'] . "',
            shipping_total = '" . (int) $totals['shipping_total'] . "',
            surcharge_total = '" . (int) $totals['surcharge_total'] . "',
            gift_card_total = '" . (int) $totals['gift_card_total'] . "',
            tax_total = '" . (int) $totals['tax_total'] . "',
            grand_total = '" . (int) $totals['grand_total'] . "',
            grand_total_base = '" . (int) $totals['grand_total'] . "',
            status = 'issued',
            is_internet_sale = '" . (int) $sale['is_internet_sale'] . "',
            payment_method = '" . escape($sale['payment_method']) . "',
            payment_date = '" . escape($sale['payment_date']) . "',
            shipment_date = '" . escape($sale['shipment_date']) . "',
            carrier_title = '" . escape($sale['carrier_title']) . "',
            carrier_vkn = '" . escape($sale['carrier_vkn']) . "',
            web_address = '" . escape($sale['web_address']) . "',
            created_by = '" . (int) ($options['created_by'] ?? 0) . "',
            created_at = '" . time() . "',
            updated_at = '" . time() . "'");

    if ($ok === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    $invoice_id = (int) mysqli_insert_id(db::$con);

    // The customer as the card reads at the moment of issue; the document
    // prints this copy, however the card is edited afterwards.
    if (!erp_invoice_snapshot_account($invoice_id, $account_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

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
                vat_exemption_code = '" . escape(erp_vat_line_exemption_code($line)) . "',
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
        'currency' => erp_base_currency(),
        'exchange_rate_source' => 'base',
        'doc_type' => 'invoice',
        'doc_id' => $invoice_id,
        'description' => $numbered['full'],
        'created_by' => (int) ($options['created_by'] ?? 0),
    ));

    if ($posted === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    // The gift card part of the total was paid when the order was placed, so
    // the invoice does not wait for it: the account is credited and the credit
    // is allocated to the invoice, both inside this transaction. A sale paid
    // entirely by gift card is therefore issued and paid in the same breath.
    $gift_card = min((int) $totals['gift_card_total'], (int) $totals['grand_total']);

    if (($gift_card > 0) && !erp_settle_gift_card(array(
        'invoice_id' => $invoice_id,
        'account_id' => $account_id,
        'amount' => $gift_card,
        'doc_date' => $issue_date,
        'description' => $numbered['full'] . ' - ' . lang('Gift Card'),
        'created_by' => (int) ($options['created_by'] ?? 0),
    ))) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    if (!erp_account_refresh_balance($account_id)) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    // What the goods cost, for the margins; the order already moved the stock.
    if (!erp_stock_post_invoice($invoice_id, (int) ($options['created_by'] ?? 0))) {
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

    erp_event_invoice($invoice_id, 'erp.invoice.created');

    return array('success' => true, 'invoice_id' => $invoice_id, 'full_number' => $numbered['full'], 'error' => '');
}

/* ------------------------------------------------- invoicing on its own */

/**
 * Whether an order is invoiced on its own at this moment, and if not, why.
 *
 * The store chooses the moment on the ERP settings card
 * (config.erp_auto_invoice_on): never, once the order is paid, or once it is
 * shipped. Pure, so the rules can be tested without a database; the caller
 * reads the order and the state of its last sales invoice.
 *
 * Paid means nothing more is owed at checkout: a gateway confirmed the
 * payment or a bank transfer was marked as received (both write paid_at),
 * the customer put the order on their account (a sale on credit is invoiced
 * like a paid one: the invoice is what the account then owes), or a gift
 * card or a discount left nothing to pay. Shipped asks for no payment: the
 * invoice is due once the goods leave, whether or not the money is in.
 *
 * A counter sale is invoiced at the till as it is made, so it never waits
 * for this; a marketplace order stays a click on its screen until the
 * marketplace connectors hand the invoice back to the marketplace. An order
 * whose invoice was cancelled is left to the operator: whatever made them
 * cancel it, a second invoice is theirs to issue.
 *
 * The store can narrow it further (erp_auto_invoice_filters()): to orders
 * from an amount up - the whole sale the invoice states, a gift card
 * included - and to buyers with a tax number. Any number counts (a VKN, a
 * TCKN, a VAT id abroad) except the final consumer's 11111111111, which an
 * e-Archive invoice carries for a buyer who gave none
 * (erp_edoc_final_consumer_tckn()).
 *
 * A contact card whose tax number is not the one on the account the invoice
 * would be written to holds the invoice (erp_order_tax_number_difference()):
 * the checkout writes a number typed later to the card, while the account,
 * and so the invoice and its e-document, keeps the one it was opened with.
 * Which of the two is right is the operator's to say, on the order screen.
 * It is the last word, so an order the store would not invoice anyway, or
 * not yet, says that first.
 *
 * @param string $setting           ERP_AUTO_INVOICE_ON
 * @param string $trigger           What just happened: 'order_paid' | 'order_shipped'
 * @param array  $order             orders row: status, type, paid_at, erp_on_account, total,
 *                                  gift_card_discount, erp_invoice_id
 * @param string $invoice_status    Status of the order's last sales invoice, '' when it has none
 * @param array  $filters           tax_number_only (bool), min_total (minor units, 0 for none)
 * @param string $buyer_tax_number  The number the invoice would carry
 *                                  (erp_auto_invoice_buyer_tax_number()); read only when
 *                                  tax_number_only is on
 * @param bool   $tax_number_differs  The contact card names a tax number the account does not
 *                                    (erp_order_tax_number_difference())
 * @return string  '' to invoice it now; otherwise the reason it is left alone:
 *                 off | trigger | status | counter_sale | marketplace |
 *                 invoiced | cancelled_before | below_minimum | no_tax_number | unpaid |
 *                 tax_number_differs
 */
function erp_auto_invoice_decision($setting, $trigger, $order, $invoice_status, $filters = array(), $buyer_tax_number = '', $tax_number_differs = false)
{
    $setting = (string) $setting;

    if (!in_array($setting, array('order_paid', 'order_shipped'), true)) {
        return 'off';
    }

    if ((string) $trigger !== $setting) {
        return 'trigger';
    }

    if (!in_array((string) ($order['status'] ?? ''), erp_order_billable_statuses(), true)) {
        return 'status';
    }

    $type = (string) ($order['type'] ?? '');

    if ($type === 'local') {
        return 'counter_sale';
    }

    if ($type === 'marketplace') {
        return 'marketplace';
    }

    $invoice_status = (string) $invoice_status;

    if ((($invoice_status !== '') && ($invoice_status !== 'cancelled')) || ((int) ($order['erp_invoice_id'] ?? 0) > 0)) {
        return 'invoiced';
    }

    if ($invoice_status === 'cancelled') {
        return 'cancelled_before';
    }

    // What the store never invoices on its own comes before the payment,
    // which only says "not yet".
    $min_total = (int) ($filters['min_total'] ?? 0);
    $sale = (int) ($order['total'] ?? 0) + (int) ($order['gift_card_discount'] ?? 0);

    if (($min_total > 0) && ($sale < $min_total)) {
        return 'below_minimum';
    }

    // An account without a number under a card that names one is the
    // difference below, not a buyer without a number.
    if (!empty($filters['tax_number_only']) && !$tax_number_differs) {
        $number = preg_replace('/\s+/u', '', (string) $buyer_tax_number);

        if (($number === '') || ($number === '11111111111')) {
            return 'no_tax_number';
        }
    }

    if (($setting === 'order_paid')
        && ((int) ($order['paid_at'] ?? 0) <= 0)
        && ((int) ($order['erp_on_account'] ?? 0) !== 1)
        && ((int) ($order['total'] ?? 0) > 0)) {
        return 'unpaid';
    }

    if ($tax_number_differs) {
        return 'tax_number_differs';
    }

    return '';
}

/**
 * The store's two filters on the invoice it issues on its own (ERP settings
 * card, 2026.11.0, 11.0.58). Off and 0 before the step has run.
 *
 * @return array  tax_number_only (bool), min_total (minor units of the base currency)
 */
function erp_auto_invoice_filters()
{
    return array(
        'tax_number_only' => defined('ERP_AUTO_INVOICE_TAX_NUMBER_ONLY') && ((int) ERP_AUTO_INVOICE_TAX_NUMBER_ONLY === 1),
        'min_total' => defined('ERP_AUTO_INVOICE_MIN_TOTAL') ? max(0, (int) ERP_AUTO_INVOICE_MIN_TOTAL) : 0,
    );
}

/**
 * The tax number an order's invoice would carry.
 *
 * The order keeps none of its own: the checkout writes the number to the
 * contact, and the invoice goes to the account the order names, or to the
 * contact's account (erp_invoice_from_order()), which a first invoice opens
 * from the contact with its number. So the account answers when there is
 * one, the contact when there is not yet.
 *
 * @param array $order  orders row: erp_account_id, contact_id
 * @return string  '' when neither has one
 */
function erp_auto_invoice_buyer_tax_number($order)
{
    $account_id = erp_order_invoice_account_id($order);
    $contact_id = (int) ($order['contact_id'] ?? 0);

    if ($account_id > 0) {
        return trim((string) db_value("SELECT tax_number FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1"));
    }

    if ($contact_id > 0) {
        return trim((string) db_value("SELECT tax_number FROM contacts WHERE id = '" . $contact_id . "' LIMIT 1"));
    }

    return '';
}

/**
 * The account an order's invoice would be written to, as
 * erp_invoice_from_order_locked() picks it, without opening one: the account
 * the order names, else the contact's account. 0 when there is none yet, and
 * a first invoice then opens it from the contact card.
 *
 * @param array $order  orders row: erp_account_id, contact_id
 * @return int
 */
function erp_order_invoice_account_id($order)
{
    $account_id = (int) ($order['erp_account_id'] ?? 0);
    $contact_id = (int) ($order['contact_id'] ?? 0);

    if (($account_id <= 0) && ($contact_id > 0)) {
        $account_id = (int) db_value("SELECT id FROM erp_accounts WHERE contact_id = '" . $contact_id . "' LIMIT 1");
    }

    return max(0, $account_id);
}

/**
 * Whether a contact card's tax number is not the one on an account, as tax
 * numbers are matched (erp_edoc_account_norm(): letters and digits only,
 * upper case, the final consumer's 11111111111 as none). Only a card that
 * names a number can differ: an empty card says nothing, while an account
 * without a number under a card with one differs.
 *
 * @param string $contact_number  contacts.tax_number
 * @param string $account_number  erp_accounts.tax_number
 * @return bool
 */
function erp_tax_numbers_differ($contact_number, $account_number)
{
    $contact = erp_edoc_account_norm('tax_number', $contact_number);

    return ($contact !== '') && ($contact !== erp_edoc_account_norm('tax_number', $account_number));
}

/**
 * An order whose contact card names a tax number other than the account its
 * invoice would be written to (erp_order_invoice_account_id()). The checkout
 * writes a number typed later to the card ("Update my contact info"), and an
 * existing account is used as it is (erp_account_for_contact()), so the
 * invoice and its e-document would carry the account's. Nothing differs
 * before the account exists: the first invoice opens it from the card.
 *
 * @param array $order  orders row: erp_account_id, contact_id
 * @return array  empty when they agree; otherwise contact_id, contact_tax_number,
 *                account_id, account_tax_number, account_title, and own (the
 *                account is the contact's own, not one the order was billed to)
 */
function erp_order_tax_number_difference($order)
{
    $contact_id = (int) ($order['contact_id'] ?? 0);
    $account_id = erp_order_invoice_account_id($order);

    if (($contact_id <= 0) || ($account_id <= 0)) {
        return array();
    }

    $contact_number = trim((string) db_value("SELECT tax_number FROM contacts WHERE id = '" . $contact_id . "' LIMIT 1"));
    $account = db_item("SELECT id, title, tax_number, contact_id FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1");

    if (!is_array($account) || !erp_tax_numbers_differ($contact_number, (string) $account['tax_number'])) {
        return array();
    }

    return array(
        'contact_id' => $contact_id,
        'contact_tax_number' => $contact_number,
        'account_id' => $account_id,
        'account_tax_number' => trim((string) $account['tax_number']),
        'account_title' => trim((string) $account['title']),
        'own' => ((int) $account['contact_id'] === $contact_id),
    );
}

/**
 * What the order screen says of a tax number difference
 * (erp_order_tax_number_difference()): both numbers as stored and the
 * account's name. For the order screen only: the log and the bell, which are
 * kept and pushed to phones, take erp_auto_invoice_tax_number_reason().
 * Values go in raw; the screen escapes the text.
 *
 * An account the order is billed to, not the contact's own, may rightly
 * carry another number, and the text says so. Where the store invoices its
 * orders on its own, the text adds that it does not issue it on its own while
 * they differ; a later trigger runs the decision again.
 *
 * @param array $difference  contact_tax_number, account_tax_number, account_title, own
 * @param bool  $automatic   The store issues invoices on its own (ERP_AUTO_INVOICE_ON)
 * @return string
 */
function erp_order_tax_number_difference_text($difference, $automatic = false)
{
    $contact_number = trim((string) ($difference['contact_tax_number'] ?? ''));
    $account_number = trim((string) ($difference['account_tax_number'] ?? ''));
    $title = trim((string) ($difference['account_title'] ?? ''));

    if (erp_edoc_account_norm('tax_number', $account_number) === '') {
        $text = lang(array(
            'string' => 'The contact card gives the tax number {var:1}, but the account the invoice is written to, {var:2}, holds none. Issued now, the invoice goes out without one.',
            'vars' => array($contact_number, $title),
        ));
    } else {
        $text = lang(array(
            'string' => 'The contact card gives the tax number {var:1}, but the account the invoice is written to, {var:2}, holds {var:3}. Issued now, the invoice carries {var:3}.',
            'vars' => array($contact_number, $title, $account_number),
        ));
    }

    if (array_key_exists('own', (array) $difference) && empty($difference['own'])) {
        $text .= ' ' . lang('The order is billed to this account, not to the contact\'s own, so a different number may be right.');
    }

    if ($automatic) {
        $text .= ' ' . lang('The store does not issue it on its own while the two differ: check them, then issue it here.');
    }

    return $text;
}

/**
 * Why an order with a tax number difference was not invoiced on its own, for
 * the site log and the panel bell. It names neither number: the log is never
 * deleted and the bell is pushed to phones, and a TCKN is a person's identity
 * number. The order screen shows both (erp_order_tax_number_difference_text()).
 *
 * @return string
 */
function erp_auto_invoice_tax_number_reason()
{
    return lang('The tax number on the contact card is not the one on the account the invoice would be written to. Check them, then issue the invoice from the order\'s Invoice and delivery documents card.');
}

/**
 * Issue an order's invoice on its own, when this is the moment the store
 * chose (erp_auto_invoice_decision()).
 *
 * The invoice is the order screen's own step (erp_order_flow_run()), with
 * its gates: an order the screen would not offer it for - a buyer whose
 * account card lacks what the e-document provider needs, nobody to bill -
 * waits there with its reason, and the panel bell says which order and why
 * (erp_alert_auto_invoice_refused()). An order whose contact card names a
 * tax number the account does not is held the same way, its answer skipped
 * 'tax_number_differs' with the reason set (erp_order_tax_number_difference()).
 * The e-document is not sent from here: making it official stays the order
 * screen's next step.
 *
 * Run where nothing else is half written: pg_erp_auto_invoice() keeps it out
 * of an open ERP transaction, because a refused invoice rolls back the whole
 * transaction and would take the caller's own work with it.
 *
 * @param int    $order_id
 * @param string $trigger  'order_paid' | 'order_shipped'
 * @return array ['done' => bool, 'skipped' => reason code or '',
 *                'invoice_id' => int, 'number' => string, 'error' => string]
 */
function erp_auto_invoice_for_order($order_id, $trigger)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_flow.php');

    $order_id = (int) $order_id;
    $trigger = (string) $trigger;
    $answer = array('done' => false, 'skipped' => '', 'invoice_id' => 0, 'number' => '', 'error' => '');

    $order = db_item("SELECT * FROM orders WHERE id = '" . $order_id . "' LIMIT 1");

    if (!is_array($order)) {
        $answer['skipped'] = 'missing';
        return $answer;
    }

    $last = erp_order_flow_invoice_row($order);
    $filters = erp_auto_invoice_filters();

    $answer['skipped'] = erp_auto_invoice_decision(
        defined('ERP_AUTO_INVOICE_ON') ? ERP_AUTO_INVOICE_ON : 'off',
        $trigger,
        $order,
        is_array($last) ? (string) $last['status'] : '',
        $filters,
        $filters['tax_number_only'] ? erp_auto_invoice_buyer_tax_number($order) : '',
        !empty(erp_order_tax_number_difference($order))
    );

    // Held for the operator: said in the log and on the bell, which name
    // neither number; the order screen shows both.
    if ($answer['skipped'] === 'tax_number_differs') {
        $answer['error'] = erp_auto_invoice_tax_number_reason();

        return erp_auto_invoice_refuse($answer, $order);
    }

    if ($answer['skipped'] !== '') {
        return $answer;
    }

    // What the screen would offer an operator, and nothing else. A step it
    // holds back carries the sentence the screen shows beside it.
    $state = erp_order_flow_state($order_id);
    $step = (is_array($state) && isset($state['steps']['invoice'])) ? $state['steps']['invoice'] : array();

    if (($step['action'] ?? '') !== 'invoice') {
        $answer['error'] = trim((string) ($step['reason'] ?? ''));

        if ($answer['error'] === '') {
            $answer['error'] = lang('The order screen does not offer its invoice.');
        }

        return erp_auto_invoice_refuse($answer, $order);
    }

    $result = erp_order_flow_run($order_id, 'invoice', 0);

    if (empty($result['success'])) {
        $answer['error'] = (string) ($result['error'] ?? '');

        return erp_auto_invoice_refuse($answer, $order);
    }

    $invoice = db_item("SELECT id, full_number FROM erp_invoices WHERE id = '" . erp_invoice_for_order($order_id) . "' LIMIT 1");

    $answer['done'] = true;
    $answer['invoice_id'] = is_array($invoice) ? (int) $invoice['id'] : 0;
    $answer['number'] = is_array($invoice) ? (string) $invoice['full_number'] : '';

    $vars = array((string) $order['order_number'], $answer['number']);

    log_activity(($trigger === 'order_shipped')
        ? lang(array('string' => 'Order (#{var:1}) was invoiced automatically once it was shipped: {var:2}.', 'vars' => $vars))
        : lang(array('string' => 'Order (#{var:1}) was invoiced automatically once it was paid: {var:2}.', 'vars' => $vars)));

    return $answer;
}

/**
 * The answer of an automatic invoice that did not go through, said as a
 * refusal (erp_auto_invoice_refused()) unless the order has its invoice by
 * now. Two requests that invoice one order at once (two shipments recorded
 * together, the order screen's button beside the payment) both pass the
 * decision; the one that waited on the order's lock then finds the step
 * gone or the invoice already written. That is the order invoiced, not
 * refused, and it is answered as skipped 'invoiced' without a word.
 *
 * @param array $answer erp_auto_invoice_for_order()'s answer, error set
 * @param array $order  the orders row
 * @return array the answer
 */
function erp_auto_invoice_refuse($answer, $order)
{
    if (erp_invoice_for_order((int) ($order['id'] ?? 0)) > 0) {
        $answer['skipped'] = 'invoiced';
        $answer['error'] = '';

        return $answer;
    }

    erp_auto_invoice_refused($order, $answer['error']);

    return $answer;
}

/**
 * Say that an order that was due its invoice did not get one: in the site
 * log, and once per order and reason on the panel bell of whoever may use
 * the ERP (erp_alert_auto_invoice_refused()).
 *
 * @param array  $order   orders row
 * @param string $reason  What the order screen says, or why issuing failed
 * @return void
 */
function erp_auto_invoice_refused($order, $reason)
{
    $reason = trim((string) $reason);

    log_activity(lang(array(
        'string' => 'Order (#{var:1}) could not be invoiced automatically: {var:2}',
        'vars' => array((string) ($order['order_number'] ?? ''), $reason),
    )));

    if (function_exists('erp_alert_auto_invoice_refused')) {
        erp_alert_auto_invoice_refused((int) ($order['id'] ?? 0), (string) ($order['order_number'] ?? ''), $reason);
    }
}
