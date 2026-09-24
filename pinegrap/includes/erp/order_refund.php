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
 * @param int $order_id
 * @return array|null  ['source' => 'invoice'|'order', 'invoice_id' => int,
 *                      'invoice_number' => string, 'lines' => array]
 *                     null when the order cannot be read
 */
function erp_order_refund_lines($order_id)
{
    $order_id = (int) $order_id;
    $order = db_item("SELECT * FROM orders WHERE id = '" . $order_id . "' LIMIT 1");

    if (!is_array($order)) {
        return null;
    }

    $invoice = ((int) ($order['erp_invoice_id'] ?? 0) > 0)
        ? db_item("SELECT id, full_number, status, doc_type FROM erp_invoices WHERE id = '" . (int) $order['erp_invoice_id'] . "' LIMIT 1")
        : null;

    if (is_array($invoice) && ((string) $invoice['doc_type'] === 'invoice') && !in_array((string) $invoice['status'], array('draft', 'cancelled'), true)) {
        return array(
            'source' => 'invoice',
            'invoice_id' => (int) $invoice['id'],
            'invoice_number' => (string) $invoice['full_number'],
            'lines' => erp_returnable_lines((int) $invoice['id']),
        );
    }

    $items = (array) db_items("SELECT order_items.*, products.short_description,
            products.tax_rate AS product_tax_rate
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = '" . $order_id . "' AND order_items.saved_for_later = 0
        ORDER BY order_items.id ASC");

    $built = erp_order_lines($order, $items);
    $lines = array();
    $extra = -1;

    foreach ($built['lines'] as $index => $line) {
        // Item lines keep the index of their order_items row; the two lines
        // that have none come after them.
        $line['id'] = isset($items[$index]) ? (int) $items[$index]['id'] : $extra--;
        $line['returned_qty'] = 0;
        $line['remaining_qty'] = (float) $line['quantity'];
        $lines[] = $line;
    }

    return array('source' => 'order', 'invoice_id' => 0, 'invoice_number' => '', 'lines' => $lines);
}

/**
 * What giving back a number of one line comes to, for every count that can
 * be chosen, so the screen can show the exact total as the boxes change.
 *
 * Each line is worked out on its own by erp_return_build(), which is what
 * the total is on the server too: a return's figures are the sums of its
 * lines, so the screen's sum and the server's agree to the kurus.
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
 * The refund for the chosen quantities.
 *
 * @param array $source      from erp_order_refund_lines()
 * @param array $quantities  line id => count, as posted
 * @return array ['amount' => int kurus, 'quantities' => array, 'is_full' => bool, 'error' => string]
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
        return array('amount' => 0, 'quantities' => array(), 'is_full' => false, 'error' => $built['error']);
    }

    return array(
        'amount' => (int) $built['totals']['grand_total'],
        'quantities' => $chosen,
        'is_full' => (bool) $built['is_full'],
        'error' => '',
    );
}

/**
 * The body of the card refund dialog: the lines, a quantity box on each, and
 * the total as the boxes change - worked out in the page from the same
 * per-line figures the server uses, so what is shown is what is refunded.
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
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="iyzico_refund_all">' . lang('Everything') . '</button>
                                <div class="fw-semibold">' . lang('Refund Amount') . ': <span id="iyzico_refund_total">' . BASE_CURRENCY_SYMBOL . '0.00</span></div>
                            </div>
                            <div class="small text-body-secondary mt-1">' . lang('max') . ': ' . BASE_CURRENCY_SYMBOL . number_format($remaining_cents / 100, 2, '.', ',') . '</div>
                            <div class="small text-warning-emphasis d-none" id="iyzico_refund_capped">' . lang('The lines come to more than is left on the card (part was paid another way); the card refund stops at what is left.') . '</div>
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
                                var submit = document.querySelector("#iyzico-refund-modal [name=submit_iyzico_refund]");
                                var refresh = function () {
                                    var total = 0;
                                    inputs.forEach(function (input) {
                                        var id = input.getAttribute("data-refund-line");
                                        var count = Math.max(0, Math.min(parseInt(input.value || "0", 10) || 0, parseInt(input.max, 10)));
                                        var amount = line(id, count);
                                        total += amount;
                                        document.querySelector("[data-refund-line-total=\"" + id + "\"]").textContent = count ? money(amount) : "-";
                                    });
                                    document.getElementById("iyzico_refund_total").textContent = money(Math.min(total, max));
                                    document.getElementById("iyzico_refund_capped").classList.toggle("d-none", total <= max);
                                    if (submit) { submit.disabled = (total <= 0); }
                                };
                                inputs.forEach(function (input) { input.addEventListener("input", refresh); });
                                document.getElementById("iyzico_refund_all").addEventListener("click", function () {
                                    inputs.forEach(function (input) { input.value = input.max; });
                                    refresh();
                                });
                                refresh();
                            })();
                            </script>';
}
