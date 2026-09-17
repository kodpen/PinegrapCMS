<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - raise an invoice from an order.
 *
 * The invoice takes its figures from the order rather than recomputing them.
 * If the two do not come to the same money, nothing is written and no document
 * number is taken: a number spent on a refused document is a hole in the
 * series that cannot be explained afterwards.
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

    // Orders that are paid for, belong to somebody, and carry no invoice yet.
    $open_orders = (array) db_items("SELECT orders.id, orders.order_number, orders.total, orders.order_date,
            orders.billing_first_name, orders.billing_last_name, orders.billing_company
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
        $order_options[$label] = (string) (int) $order['id'];
    }

    if (empty($order_options)) {
        $order_options[lang('There are no orders waiting to be invoiced.')] = '';
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

    if ($order_id <= 0) {
        $liveform->mark_error('order_id', lang(array('string' => '{var:1} is required', 'vars' => lang('Order'))));
        go(PATH . SOFTWARE_DIRECTORY . '/add_erp_invoice.php');
    }

    $result = erp_invoice_from_order($order_id, array('created_by' => (int) $user['id']));

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

    go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . (int) $result['invoice_id']);
}
