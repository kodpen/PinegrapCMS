<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - raise an invoice from an order, or type one in.
 *
 * The invoice takes its figures from the order rather than recomputing them.
 * If the two do not come to the same money, nothing is written and no document
 * number is taken: a number spent on a refused document is a hole in the
 * series that cannot be explained afterwards.
 *
 * With foreign currency switched on the screen also takes an invoice typed by
 * hand, in the base currency or in one the ERP allows, since a store order is
 * always billed in the base currency.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('add_erp_invoice');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_invoices.php';

// If the form has not been submitted yet, then show it.
if (!$_POST) {

    // Orders that are complete, belong to somebody, and carry no invoice yet.
    // A bank transfer order may be complete without being paid; the picker
    // says so on the option rather than hiding the order, because an invoice
    // is sometimes what the customer needs before they pay.
    $open_orders = (array) db_items("SELECT orders.id, orders.order_number, orders.total, orders.order_date,
            orders.billing_first_name, orders.billing_last_name, orders.billing_company,
            orders.status, orders.payment_method, orders.paid_at
        FROM orders
        WHERE orders.status = 'complete'
          AND orders.contact_id > 0
          AND COALESCE(orders.erp_invoice_id, 0) = 0
        ORDER BY orders.order_date DESC
        LIMIT 500");

    $order_options = array();

    foreach ($open_orders as $order) {
        $who = trim((string) $order['billing_company']);
        if ($who === '') {
            $who = trim($order['billing_first_name'] . ' ' . $order['billing_last_name']);
        }

        // liveform prints option labels as-is and the billing name was typed by the customer.
        $label = h('#' . $order['order_number'] . ' - ' . $who . ' - ' . erp_money_out((int) $order['total']));

        if (pg_order_awaiting_payment($order)) {
            $label .= h(' — ' . lang('awaiting payment'));
        }

        $order_options[$label] = (string) (int) $order['id'];
    }

    if (empty($order_options)) {
        $order_options[lang('There are no orders waiting to be invoiced.')] = '';
    }

    // The typed-in invoice. Only offered with foreign currency on; a
    // base-currency shop raises its invoices from orders as before.
    $output_manual = '';

    if (erp_fx_enabled()) {

        if ($liveform->field_in_session('manual_issue_date') == false) {
            $liveform->assign_field_value('source', 'order');
            $liveform->assign_field_value('manual_direction', 'sales');
            $liveform->assign_field_value('manual_currency', erp_base_currency());
            $liveform->assign_field_value('manual_issue_date', prepare_form_data_for_output(date('Y-m-d'), 'date'));
            $liveform->assign_field_value('manual_exchange_rate', '');
        }

        $account_options = array();
        $account_options[lang('Choose an account')] = '';
        // liveform prints option labels as-is; account titles come from contact names typed at checkout.
        foreach (erp_accounts(array('status' => 'active')) as $account) {
            $account_options[h($account['title'])] = (string) (int) $account['id'];
        }

        $direction_options = array();
        $direction_options[lang('Sales invoice')] = 'sales';
        $direction_options[lang('Purchase invoice')] = 'purchase';

        $source_options = array();
        $source_options[lang('From an order')] = 'order';
        $source_options[lang('Typed in')] = 'manual';

        // Today's recorded rate for each allowed currency, so the operator can
        // see what the empty rate box will be filled with.
        $rate_hints = array();
        foreach (erp_fx_currencies() as $code) {
            $known = erp_fx_rate_for($code, date('Y-m-d'));
            $rate_hints[] = $code . ': ' . (is_array($known)
                ? (erp_fx_rate_out($known['rate']) . ' (' . prepare_form_data_for_output($known['rate_date'], 'date') . ')')
                : lang('no rate recorded'));
        }

        $output_manual_lines = '';
        for ($index = 0; $index < 6; $index++) {
            $output_manual_lines .= '
                            <tr>
                                <td class="text-body-secondary align-middle">' . ($index + 1) . '</td>
                                <td>' . $liveform->output_field(array(
                                    'type' => 'text', 'id' => 'line_description_' . $index, 'name' => 'line_description_' . $index,
                                    'class' => 'form-control form-control-sm', 'maxlength' => '255', 'autocomplete' => 'off')) . '</td>
                                <td>' . $liveform->output_field(array(
                                    'type' => 'text', 'id' => 'line_quantity_' . $index, 'name' => 'line_quantity_' . $index,
                                    'class' => 'form-control form-control-sm text-end', 'maxlength' => '12', 'inputmode' => 'decimal', 'autocomplete' => 'off')) . '</td>
                                <td>' . $liveform->output_field(array(
                                    'type' => 'text', 'id' => 'line_unit_price_' . $index, 'name' => 'line_unit_price_' . $index,
                                    'class' => 'form-control form-control-sm text-end', 'maxlength' => '15', 'inputmode' => 'decimal', 'autocomplete' => 'off')) . '</td>
                                <td>' . $liveform->output_field(array(
                                    'type' => 'text', 'id' => 'line_tax_rate_' . $index, 'name' => 'line_tax_rate_' . $index,
                                    'class' => 'form-control form-control-sm text-end', 'maxlength' => '7', 'inputmode' => 'decimal', 'autocomplete' => 'off')) . '</td>
                            </tr>';
        }

        $output_manual = '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Typed-in Invoice') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-lg-3 my-2">
                                <label for="source" class="form-label">' . lang('Source') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'select', 'id' => 'source', 'name' => 'source',
                                    'class' => 'form-select', 'options' => $source_options)) . '
                                <div class="form-text">' . lang('Choose Typed in to raise the invoice from the lines below instead of from an order.') . '</div>
                            </div>
                            <div class="col-12 col-lg-3 my-2">
                                <label for="manual_direction" class="form-label">' . lang('Direction') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'select', 'id' => 'manual_direction', 'name' => 'manual_direction',
                                    'class' => 'form-select', 'options' => $direction_options)) . '
                            </div>
                            <div class="col-12 col-lg-6 my-2">
                                <label for="manual_account_id" class="form-label">' . lang('Account') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'select', 'id' => 'manual_account_id', 'name' => 'manual_account_id',
                                    'class' => 'form-select', 'options' => $account_options)) . '
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-4 col-lg-3 my-2">
                                <label for="manual_currency" class="form-label">' . lang('Currency') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'select', 'id' => 'manual_currency', 'name' => 'manual_currency',
                                    'class' => 'form-select', 'options' => erp_fx_currency_options())) . '
                            </div>
                            <div class="col-12 col-sm-4 col-lg-3 my-2">
                                <label for="manual_exchange_rate" class="form-label">' . lang('Exchange Rate') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'text', 'id' => 'manual_exchange_rate', 'name' => 'manual_exchange_rate',
                                    'class' => 'form-control text-end', 'maxlength' => '20', 'inputmode' => 'decimal',
                                    'autocomplete' => 'off', 'placeholder' => lang('rate of the issue date'))) . '
                                <div class="form-text">' . h(lang(array('string' => '{var:1} per unit of the document currency. Leave empty to use the recorded rate of the issue date.', 'vars' => erp_base_currency()))) . (!empty($rate_hints) ? (' ' . h(implode(', ', $rate_hints))) : '') . '</div>
                            </div>
                            <div class="col-12 col-sm-4 col-lg-3 my-2">
                                <label for="manual_issue_date" class="form-label">' . lang('Date') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'text', 'id' => 'manual_issue_date', 'name' => 'manual_issue_date',
                                    'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                                    'autocomplete' => 'off')) . '
                                ' . get_date_picker_format() . '
                                <script>$("#manual_issue_date").datepicker(datetimepicker_options);</script>
                            </div>
                        </div>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:3rem">#</th>
                                        <th>' . lang('Description') . '</th>
                                        <th class="text-end" style="width:12%">' . lang('Quantity') . '</th>
                                        <th class="text-end" style="width:18%">' . lang('Unit price') . '</th>
                                        <th class="text-end" style="width:12%">' . lang('VAT %') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_manual_lines . '</tbody>
                            </table>
                        </div>
                        <div class="form-text">' . lang('Amounts are in the document currency. Empty rows are ignored.') . '</div>
                    </div>
                </div>';
    }

    echo
    pg_page_shell([
        'title' => lang('Invoice an Order'),
        'extra_classes' => 'erp erp_invoices',
        'icon' => 'store',
        'heading' => lang('Invoice an Order'),
        'heading_description' => lang('Raise a sales invoice from an order that has been paid for but not yet invoiced.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_invoices.php'),
        'breadcrumb' => array(
            array('label' => lang('Invoices'), 'url' => $list_url),
            array('label' => lang('Invoice an Order')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_invoice.php" method="post">
                ' . get_token_field() . '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Order') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-lg-8 my-2">
                                <label for="order_id" class="form-label">' . lang('Order') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'select', 'id' => 'order_id', 'name' => 'order_id',
                                    'class' => 'form-select', 'options' => $order_options)) . '
                                <div class="form-text">' . lang('Completed orders that are linked to a contact and have not been invoiced yet.') . '</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 my-2">
                                <div class="form-text">' . lang('The invoice takes its figures from the order. Shipping and any surcharge become lines; an order discount is spread over the lines it applied to.') . '</div>
                            </div>
                        </div>
                    </div>
                </div>
                ' . $output_manual . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Creating')) . '"><span class="bi bi-receipt me-2"></span><span class="btn-text">' . lang(array('string' => 'Create the Invoice')) . '</span></button>
                        </div>
                    </div>
                </nav>
            </form>
        </div>
    </div>
</main>' .
    output_footer();

    $liveform->remove_form();

// Otherwise the form has been submitted so process it.
} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    $order_id = (int) $liveform->get_field_value('order_id');
    $is_manual = erp_fx_enabled() && ($liveform->get_field_value('source') === 'manual');

    if ($is_manual) {

        $issue_date = trim((string) $liveform->get_field_value('manual_issue_date'));

        if (($issue_date !== '') && (validate_date($issue_date) == false)) {
            $liveform->mark_error('manual_issue_date', lang('Please enter a valid date.'));
            go(PATH . SOFTWARE_DIRECTORY . '/add_erp_invoice.php');
        }

        $issue_date = ($issue_date !== '') ? prepare_form_data_for_input($issue_date, 'date') : date('Y-m-d');
        $currency = strtoupper(trim((string) $liveform->get_field_value('manual_currency')));

        // The rate of the issue date unless the operator typed another one; a
        // typed rate that matches the recorded one keeps the feed as source.
        $exchange_rate = 1.0;
        $rate_date = $issue_date;
        $rate_source = 'base';

        if ($currency !== erp_base_currency()) {
            $recorded = erp_fx_rate_for($currency, $issue_date);
            $typed = erp_fx_rate_in($liveform->get_field_value('manual_exchange_rate'));

            if ($typed > 0) {
                $exchange_rate = $typed;
                $rate_source = (is_array($recorded) && (abs($recorded['rate'] - $typed) < 0.0000005)) ? $recorded['source'] : 'manual';
                $rate_date = ($rate_source === 'manual') ? $issue_date : $recorded['rate_date'];
            } elseif (is_array($recorded)) {
                $exchange_rate = $recorded['rate'];
                $rate_date = $recorded['rate_date'];
                $rate_source = $recorded['source'];
            } else {
                $liveform->mark_error('manual_exchange_rate', lang(array(
                    'string' => 'No exchange rate is recorded for {var:1} on {var:2}. Run Update Exchange Rates or enter the rate.',
                    'vars' => array($currency, prepare_form_data_for_output($issue_date, 'date')),
                )));
                go(PATH . SOFTWARE_DIRECTORY . '/add_erp_invoice.php');
            }
        }

        $lines = array();
        for ($index = 0; $index < 6; $index++) {
            $lines[] = array(
                'description' => $liveform->get_field_value('line_description_' . $index),
                'quantity' => erp_fx_rate_in($liveform->get_field_value('line_quantity_' . $index)),
                'unit_price' => erp_kurus($liveform->get_field_value('line_unit_price_' . $index)),
                'tax_rate' => erp_fx_rate_in($liveform->get_field_value('line_tax_rate_' . $index)),
            );
        }

        $result = erp_invoice_create_manual(array(
            'direction' => $liveform->get_field_value('manual_direction'),
            'account_id' => (int) $liveform->get_field_value('manual_account_id'),
            'currency' => $currency,
            'exchange_rate' => $exchange_rate,
            'exchange_rate_date' => $rate_date,
            'exchange_rate_source' => $rate_source,
            'issue_date' => $issue_date,
            'lines' => $lines,
            'created_by' => (int) $user['id'],
        ));

    } else {

        if ($order_id <= 0) {
            $liveform->mark_error('order_id', lang(array('string' => '{var:1} is required', 'vars' => lang('Order'))));
            go(PATH . SOFTWARE_DIRECTORY . '/add_erp_invoice.php');
        }

        $result = erp_invoice_from_order($order_id, array('created_by' => (int) $user['id']));
    }

    if (!$result['success']) {
        $liveform->mark_error('_error', $result['error']);
        go(PATH . SOFTWARE_DIRECTORY . '/add_erp_invoice.php');
    }

    log_activity(lang(array('string' => 'erp invoice ({var:1}) was created', 'vars' => $result['full_number'])), $_SESSION['sessionusername']);

    // The notice belongs to the screen the user lands on, which is the document
    // itself rather than the register.
    $liveform->remove_form();
    $liveform_document = new liveform('edit_erp_invoice');
    $liveform_document->add_notice(lang(array('string' => 'Invoice {var:1} created.', 'vars' => $result['full_number'])));

    // The invoice was raised from an order whose bank transfer has not
    // arrived; its payment date stays empty until the receipt is posted, and
    // the operator should hear that now rather than find it on the document.
    $invoiced_order = ($is_manual || ($order_id <= 0)) ? null
        : db_item("SELECT status, payment_method, paid_at FROM orders WHERE id = '" . $order_id . "' LIMIT 1");

    if (is_array($invoiced_order) && pg_order_awaiting_payment($invoiced_order)) {
        $liveform_document->add_notice(lang('This order is still awaiting its bank transfer; the invoice has no payment date yet.'));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . (int) $result['invoice_id']);
}
