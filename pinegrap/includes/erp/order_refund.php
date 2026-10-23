<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - a card refund chosen by line and quantity.
 *
 * A refund typed as an amount has no lines: the return invoice that has to
 * follow it is then written by hand, and the two never quite agree - a
 * kurus of rounding here, a forgotten shipping line there. Chosen by line
 * and quantity, the amount is worked out by the same function that writes
 * the return invoice, so the card refund and the return are one figure.
 *
 * The lines are the invoice's when the order has one (less what has been
 * returned already), otherwise the order's own, built the way the invoice
 * would be.
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
 * The lines a card refund can be chosen from.
 *
 * Shipping and the surcharge have no order_items row, so on the order's own
 * lines they carry the ids -1 and -2.
 *
 * left_out names the order_items rows the order did not charge
 * (erp_order_charged_items()), which no refund line stands for, so a caller
 * matching order lines to refund lines (pg_return_erp_line_map()) can pass
 * over them. On an invoice it is worked out from today's rows and gateway
 * setting: an invoice issued while such lines were still put on it may carry
 * them.
 *
 * @param int $order_id
 * @return array|null  ['source' => 'invoice'|'order', 'invoice_id' => int,
 *                      'invoice_number' => string, 'lines' => array,
 *                      'left_out' => int[] order_items ids]
 *                     null when the order cannot be read
 */
function erp_order_refund_lines($order_id)
{
    $order_id = (int) $order_id;
    $order = db_item("SELECT * FROM orders WHERE id = '" . $order_id . "' LIMIT 1");

    if (!is_array($order)) {
        return null;
    }

    $items = (array) db_items("SELECT order_items.*, products.short_description,
            products.tax_rate AS product_tax_rate, products.start AS product_start
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = '" . $order_id . "' AND order_items.saved_for_later = 0
        ORDER BY order_items.id ASC");

    // Only what the order charged can be given back: a recurring line that
    // had not started yet took no money (erp_order_charged_items()).
    $charged = erp_order_charged_items($order, $items);
    $left_out = array_values(array_diff(array_map('intval', array_column($items, 'id')),
        array_map('intval', array_column($charged, 'id'))));

    $invoice = ((int) ($order['erp_invoice_id'] ?? 0) > 0)
        ? db_item("SELECT id, full_number, status, doc_type FROM erp_invoices WHERE id = '" . (int) $order['erp_invoice_id'] . "' LIMIT 1")
        : null;

    if (is_array($invoice) && ((string) $invoice['doc_type'] === 'invoice') && !in_array((string) $invoice['status'], array('draft', 'cancelled'), true)) {
        return array(
            'source' => 'invoice',
            'invoice_id' => (int) $invoice['id'],
            'invoice_number' => (string) $invoice['full_number'],
            'lines' => erp_returnable_lines((int) $invoice['id']),
            'left_out' => $left_out,
        );
    }

    $built = erp_order_lines($order, $charged);

    return array('source' => 'order', 'invoice_id' => 0, 'invoice_number' => '',
        'lines' => erp_order_refund_order_lines($built['lines'], $charged),
        'left_out' => $left_out);
}

/**
 * The order's built lines as erp_return_build() reads a parent's lines.
 *
 * Each line gets the id its quantity is posted under: the item lines keep the
 * id of their order_items row, in the order the lines were built from the
 * rows; the lines that have none (shipping, surcharge) come after them with
 * -1, -2, ... Nothing has been given back yet, so returned_qty is 0 and so is
 * each tax given back so far.
 *
 * Those three zeros are what make the refund meet the order. A line's tax is
 * the order's figure: tied to the order's header by erp_order_lines(), or
 * split out of a gross charge (shipping), so it can sit a kurus from its rate
 * on its amount. With the taxes given back so far known, erp_return_build()
 * lets a line given back whole take exactly the line's tax and caps a piece
 * of it by that tax; without them it took every line's tax again, per rate
 * group of the return, and "Everything" came a kurus short or over the
 * amount collected - the card got one less, and the order, refunded a kurus
 * below its total, was never marked cancelled.
 *
 * @param array $built_lines  erp_order_lines()['lines']
 * @param array $items        The order_items rows the lines were built from, indexed from 0
 * @return array
 */
function erp_order_refund_order_lines($built_lines, $items)
{
    $lines = array();
    $extra = -1;

    foreach ((array) $built_lines as $index => $line) {
        $line['id'] = isset($items[$index]) ? (int) $items[$index]['id'] : $extra--;
        $line['returned_qty'] = 0;
        $line['remaining_qty'] = (float) $line['quantity'];
        $line['returned_tax'] = 0;
        $line['returned_withholding'] = 0;
        $line['returned_tax2'] = 0;
        $lines[] = $line;
    }

    return $lines;
}

/**
 * What giving back a number of one line comes to, for every count that can
 * be chosen, so the screen can answer at once as the boxes change.
 *
 * Each line is worked out on its own by erp_return_build(): exact while it is
 * the only line chosen and the sale has no line below zero. With several
 * lines the taxes of the pieces given back in part are taken per group of the
 * return, and goods bring a credit line's share back with them
 * (erp_return_credit_quantities()), so the dialog takes the figures for the
 * whole choice from the server (erp_refund_preview).
 *
 * @param array $line  One line from erp_order_refund_lines()
 * @param int   $cap   Most counts to list; beyond it the screen scales
 * @return array  count => kurus, from 1
 */
function erp_order_refund_line_prices($line, $cap = 200)
{
    $prices = array();
    $remaining = (int) floor((float) $line['remaining_qty'] + 0.00001);

    for ($count = 1; ($count <= $remaining) && ($count <= $cap); $count++) {
        $built = erp_return_build(array($line), array((int) $line['id'] => $count));
        $prices[$count] = ($built['error'] === '') ? (int) $built['totals']['grand_total'] : 0;
    }

    return $prices;
}

/**
 * A quantity the way a person writes it: 2, not 2.0000.
 *
 * @param float|string $quantity
 * @return string
 */
function erp_quantity_out($quantity)
{
    $text = rtrim(rtrim(number_format((float) $quantity, 4, '.', ''), '0'), '.');
    return ($text === '' || $text === '-0') ? '0' : $text;
}

/**
 * The refund for the chosen quantities, and what each chosen line comes to
 * within it (its taxes are its share of the return's groups).
 *
 * @param array $source      from erp_order_refund_lines()
 * @param array $quantities  line id => count, as posted
 * @return array ['amount' => int kurus, 'quantities' => array, 'lines' => [line id => kurus],
 *                'is_full' => bool, 'error' => string]
 */
function erp_order_refund_amount($source, $quantities)
{
    $chosen = array();

    foreach ((array) $quantities as $line_id => $count) {
        $count = erp_quantity_in($count);
        if ($count > 0) {
            $chosen[(int) $line_id] = $count;
        }
    }

    $built = erp_return_build($source['lines'], $chosen);

    if ($built['error'] !== '') {
        return array('amount' => 0, 'quantities' => array(), 'lines' => array(), 'is_full' => false, 'error' => $built['error']);
    }

    $lines = array();

    foreach ($built['lines'] as $line) {
        $lines[(int) $line['parent_line_id']] = (int) $line['line_total'] - (int) $line['discount_amount']
            + (int) $line['tax_total'] - (int) $line['withholding_amount'];
    }

    return array(
        'amount' => (int) $built['totals']['grand_total'],
        'quantities' => $chosen,
        'lines' => $lines,
        'is_full' => (bool) $built['is_full'],
        'error' => '',
    );
}

/**
 * The body of the card refund dialog: the lines, a quantity box on each, and
 * the total as the boxes change - each line priced alone at once
 * (erp_order_refund_line_prices()), then the server's figures for the whole
 * choice (api.php, erp_refund_preview: erp_order_refund_amount(), what the
 * refund itself is worked out with), so what is shown is what is refunded.
 *
 * @param array $source         from erp_order_refund_lines()
 * @param int   $order_id
 * @param int   $refunded_cents  Refunded to the card so far
 * @param int   $remaining_cents Still on the card
 * @return string  HTML; empty when no line is left to refund
 */
function erp_order_refund_dialog($source, $order_id, $refunded_cents, $remaining_cents)
{
    $refund_lines = array_filter($source['lines'], function ($line) {
        return (float) $line['remaining_qty'] > 0.00001;
    });

    if (empty($refund_lines)) {
        return '';
    }

    $refund_rows = '';
    $refund_prices = array();
    foreach ($refund_lines as $refund_line) {
        $line_id = (int) $refund_line['id'];
        $remaining = (int) floor((float) $refund_line['remaining_qty'] + 0.00001);
        $refund_prices[$line_id] = erp_order_refund_line_prices($refund_line);
        $refund_rows .= '
                                    <tr>
                                        <td class="text-break">' . h((string) $refund_line['description']) . '
                                            ' . (((float) $refund_line['returned_qty'] > 0) ? '<div class="small text-body-secondary">' . lang(array('string' => '{var:1} of {var:2} already returned', 'vars' => array(erp_quantity_out($refund_line['returned_qty']), erp_quantity_out($refund_line['quantity'])))) . '</div>' : '') . '
                                            ' . (((int) $refund_line['line_total'] < 0) ? '<div class="small text-body-secondary">' . lang('Left empty or at 0, it comes back with the goods in proportion.') . '</div>' : '') . '
                                        </td>
                                        <td class="text-end text-nowrap">' . erp_quantity_out($refund_line['remaining_qty']) . '</td>
                                        <td style="width:6.5rem"><input type="number" class="form-control form-control-sm text-end" name="refund_qty[' . $line_id . ']" data-refund-line="' . $line_id . '" min="0" max="' . $remaining . '" step="1" value="0" inputmode="numeric" aria-label="' . h(lang('Quantity to refund')) . '"></td>
                                        <td class="text-end text-nowrap" data-refund-line-total="' . $line_id . '">-</td>
                                    </tr>';
    }

    $refund_uncovered = 0;
    if ($source['source'] === 'invoice') {
        $refund_uncovered = $refunded_cents - erp_invoice_returned_total((int) $source['invoice_id']);
    }

    return '
                            <input type="hidden" name="refund_mode" value="lines">
                            <p class="small text-body-secondary">' . lang('Choose what came back. The amount is worked out from the lines, the same way the return invoice will be.') . '</p>
                            ' . (($refund_uncovered > 0) ? '<div class="alert alert-warning small py-2">' . lang(array('string' => '{var:1} refunded earlier has no return invoice yet. Issue it before refunding more, so the same goods are not refunded twice.', 'vars' => BASE_CURRENCY_SYMBOL . number_format($refund_uncovered / 100, 2, '.', ','))) . ' <a href="add_erp_return.php?invoice_id=' . (int) $source['invoice_id'] . '&back_order=' . (int) $order_id . '">' . lang('Issue the return invoice') . '</a></div>' : '') . '
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-2">
                                    <thead><tr>
                                        <th>' . lang('Description') . '</th>
                                        <th class="text-end">' . lang('Refundable') . '</th>
                                        <th>' . lang('Quantity') . '</th>
                                        <th class="text-end">' . lang('Amount') . '</th>
                                    </tr></thead>
                                    <tbody>' . $refund_rows . '
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="payment_refund_all">' . lang('Everything') . '</button>
                                <div class="fw-semibold">' . lang('Refund Amount') . ': <span id="payment_refund_total">' . BASE_CURRENCY_SYMBOL . '0.00</span></div>
                            </div>
                            <div class="small text-body-secondary mt-1">' . lang('max') . ': ' . BASE_CURRENCY_SYMBOL . number_format($remaining_cents / 100, 2, '.', ',') . '</div>
                            <div class="small text-warning-emphasis d-none" id="payment_refund_capped">' . lang('The lines come to more than is left on the card (part was paid another way); the card refund stops at what is left.') . '</div>
                            <p class="form-text text-muted mt-2">' . (($source['source'] === 'invoice')
                                ? lang(array('string' => 'After the refund, the return invoice against {var:1} opens with the same lines filled in; check it and save.', 'vars' => h($source['invoice_number'])))
                                : lang('The order has no invoice yet. Once it is invoiced, issue a return invoice for these lines from the order\'s document card.')) . '</p>
                            <script>
                            (function () {
                                var prices = ' . json_encode($refund_prices, JSON_HEX_TAG | JSON_HEX_AMP) . ';
                                var max = ' . (int) $remaining_cents . ';
                                var symbol = ' . json_encode(html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES, 'UTF-8'), JSON_HEX_TAG | JSON_HEX_AMP) . ';
                                var money = function (kurus) {
                                    return symbol + (kurus / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                                };
                                var line = function (id, count) {
                                    var list = prices[id] || {};
                                    if (count <= 0) { return 0; }
                                    if (list[count] !== undefined) { return list[count]; }
                                    var keys = Object.keys(list).map(Number);
                                    var top = keys.length ? Math.max.apply(null, keys) : 0;
                                    return top ? Math.round(list[top] / top * count) : 0;
                                };
                                var inputs = document.querySelectorAll("[data-refund-line]");
                                var submit = document.querySelector("#payment-refund-modal [name=submit_payment_refund]");
                                var orderId = ' . (int) $order_id . ';
                                var asked = 0;
                                var timer = null;
                                // A line below zero left at 0 still has an amount in
                                // the server\'s answer: the share that comes back with
                                // the goods.
                                var show = function (total, amounts, counts) {
                                    Object.keys(counts).forEach(function (id) {
                                        document.querySelector("[data-refund-line-total=\"" + id + "\"]").textContent = (counts[id] || amounts[id]) ? money(amounts[id] || 0) : "-";
                                    });
                                    document.getElementById("payment_refund_total").textContent = money(Math.min(total, max));
                                    document.getElementById("payment_refund_capped").classList.toggle("d-none", total <= max);
                                    if (submit) { submit.disabled = (total <= 0); }
                                };
                                // Several lines given back in part take their taxes per
                                // group, so the whole is the server\'s figure, not the sum
                                // of the lines priced alone.
                                var ask = function (counts) {
                                    var number = ++asked;
                                    var quantities = {};
                                    Object.keys(counts).forEach(function (id) {
                                        if (counts[id] > 0) { quantities[id] = counts[id]; }
                                    });
                                    if (!Object.keys(quantities).length || !window.fetch) { return; }
                                    fetch("api.php", {
                                        method: "POST",
                                        credentials: "same-origin",
                                        headers: {"Content-Type": "application/json"},
                                        body: JSON.stringify({action: "erp_refund_preview", token: (typeof software_token !== "undefined") ? software_token : "", order_id: orderId, quantities: quantities})
                                    }).then(function (response) {
                                        return response.json();
                                    }).then(function (answer) {
                                        if ((number !== asked) || !answer || (answer.status !== "success") || !answer.data) { return; }
                                        show(parseInt(answer.data.amount, 10) || 0, answer.data.lines || {}, counts);
                                    }).catch(function () {});
                                };
                                var refresh = function () {
                                    var total = 0;
                                    var amounts = {};
                                    var counts = {};
                                    inputs.forEach(function (input) {
                                        var id = input.getAttribute("data-refund-line");
                                        var count = Math.max(0, Math.min(parseInt(input.value || "0", 10) || 0, parseInt(input.max, 10)));
                                        counts[id] = count;
                                        amounts[id] = line(id, count);
                                        total += amounts[id];
                                    });
                                    show(total, amounts, counts);
                                    window.clearTimeout(timer);
                                    timer = window.setTimeout(function () { ask(counts); }, 200);
                                };
                                inputs.forEach(function (input) { input.addEventListener("input", refresh); });
                                document.getElementById("payment_refund_all").addEventListener("click", function () {
                                    inputs.forEach(function (input) { input.value = input.max; });
                                    refresh();
                                });
                                refresh();
                            })();
                            </script>';
}
