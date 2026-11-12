<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Invoice an Order screen (add_erp_invoice.php) in the wide layout: the
 * form column with the Order card (the order picker), the sticky status
 * column with the Summary (how many orders wait for an invoice, how many of
 * them still await payment, what the invoice takes from the order, the way
 * to a typed invoice) and the sticky save bar with Create the Invoice. The
 * screen keeps its gates, the order query, the form tag and the POST.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * How many of the listed orders wait for an invoice, and how many of those
 * are still awaiting payment (pg_order_awaiting_payment()).
 *
 * @param array $open_orders The orders rows the picker offers
 * @return array  ['orders' => int, 'awaiting' => int]
 */
function pg_invoice_order_form_counts($open_orders)
{
    $counts = array('orders' => 0, 'awaiting' => 0);

    foreach ((array) $open_orders as $order) {
        if (!is_array($order)) {
            continue;
        }

        $counts['orders']++;

        if (pg_order_awaiting_payment($order)) {
            $counts['awaiting']++;
        }
    }

    return $counts;
}

/**
 * The form column: the Order card with the picker and its help line.
 *
 * @param string $order_field The picker, already HTML (liveform output_field())
 * @return string  HTML
 */
function pg_invoice_order_form_card($order_field)
{
    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Order') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <label for="order_id" class="form-label">' . lang('Order') . '</label>
                                    ' . $order_field . '
                                    <div class="form-text">' . lang('Completed orders that are linked to a contact or an account and have not been invoiced yet. Orders still awaiting payment are marked.') . '</div>
                                </div>
                            </div>
                        </div>';
}

/**
 * The sticky status column: the Summary with the order counts, what the
 * invoice takes from the order, and a link to type an invoice in instead.
 *
 * @param array  $counts     pg_invoice_order_form_counts()
 * @param string $manual_url The typed invoice screen (add_erp_manual_invoice.php)
 * @return string  HTML
 */
function pg_invoice_order_form_status($counts, $manual_url)
{
    $orders = (int) ($counts['orders'] ?? 0);
    $awaiting = (int) ($counts['awaiting'] ?? 0);

    return '
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-3">'
        . pg_invoice_order_form_status_row(lang('Orders to invoice'), (string) $orders, false)
        . pg_invoice_order_form_status_row(lang('Awaiting payment'), ($awaiting > 0) ? '<span class="badge theme-warning">' . $awaiting . '</span>' : '0', true) . '
                                        </dl>
                                        <p class="form-text mt-0">' . lang('The invoice takes its figures from the order. Shipping and any surcharge become lines; an order discount is spread over the lines it applied to.') . '</p>
                                        <p class="form-text">' . lang('For a sale outside the shop or a supplier\'s bill, type the invoice in instead:') . '</p>
                                        <a href="' . h($manual_url) . '" class="btn btn-sm btn-outline theme-secondary w-100"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>' . lang('New Invoice') . '</a>
                                    </div>
                                </div>
                            </div>
                        </div>';
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label The label, plain text
 * @param string $value The value, already HTML
 * @param bool   $last  False draws the line under the row
 * @return string  HTML
 */
function pg_invoice_order_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the form column, the status column and the sticky save
 * bar with Create the Invoice, disabled while no order waits.
 *
 * @param string $columns     pg_invoice_order_form_card() . pg_invoice_order_form_status()
 * @param bool   $can_create  At least one order can be picked
 * @return string  HTML
 */
function pg_invoice_order_form_layout($columns, $can_create)
{
    return '
                    <div class="row">' . $columns . '
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">
                                        <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"' . ($can_create ? '' : ' disabled') . '><i class="bi bi-receipt me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Create the Invoice') . '</span></button>
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>';
}
