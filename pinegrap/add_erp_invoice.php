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
 * A store order is always billed in the base currency. An invoice typed by
 * hand - a sale outside the shop, a supplier's bill, a foreign-currency
 * document - is add_erp_manual_invoice.php.
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

    // Orders that are complete, belong to somebody - a contact that still
    // exists or already has an account, or an account named on the order the
    // way a counter sale is - and carry no invoice yet. An order whose contact
    // has been deleted cannot be billed (erp_invoice_from_order() says so),
    // so it is not offered.
    // A bank transfer order may be complete without being paid; the picker
    // says so on the option rather than hiding the order, because an invoice
    // is sometimes what the customer needs before they pay.
    $open_orders = (array) db_items("SELECT orders.id, orders.order_number, orders.total, orders.order_date,
            orders.billing_first_name, orders.billing_last_name, orders.billing_company,
            orders.status, orders.payment_method, orders.paid_at, orders.type,
            TRIM(CONCAT(COALESCE(contacts.first_name, ''), ' ', COALESCE(contacts.last_name, ''))) AS contact_name,
            COALESCE(contacts.company, '') AS contact_company,
            COALESCE(erp_accounts.title, '') AS account_title
        FROM orders
        LEFT JOIN contacts ON contacts.id = orders.contact_id
        LEFT JOIN erp_accounts ON erp_accounts.id = orders.erp_account_id
        WHERE " . erp_order_billable_sql('orders.status') . "
          AND (contacts.id IS NOT NULL
               OR COALESCE(orders.erp_account_id, 0) > 0
               OR EXISTS (SELECT 1 FROM erp_accounts linked WHERE linked.contact_id = orders.contact_id AND orders.contact_id > 0))
          AND COALESCE(orders.erp_invoice_id, 0) = 0
        ORDER BY orders.order_date DESC
        LIMIT 500");

    $order_options = array();

    foreach ($open_orders as $order) {
        // The billing name the customer typed; a counter sale has none, so
        // the contact's or the account's name stands in.
        $who = trim((string) $order['billing_company']);
        if ($who === '') {
            $who = trim($order['billing_first_name'] . ' ' . $order['billing_last_name']);
        }
        if ($who === '') {
            $who = (trim((string) $order['contact_company']) !== '') ? trim((string) $order['contact_company']) : trim((string) $order['contact_name']);
        }
        if ($who === '') {
            $who = trim((string) $order['account_title']);
        }
        if (($who !== '') && ((string) $order['type'] === 'local')) {
            $who .= ' (' . lang('local sale') . ')';
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

    echo
    pg_page_shell([
        'title' => lang('Invoice an Order'),
        'extra classes' => 'erp erp_invoices',
        'icon' => 'erp',
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
                                <div class="form-text">' . lang('For a sale outside the shop or a supplier\'s bill, type the invoice in instead:') . ' <a href="add_erp_manual_invoice.php" class="link-body-emphasis">' . lang('New Invoice') . '</a></div>
                            </div>
                        </div>
                    </div>
                </div>
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Creating')) . '"><i class="bi bi-receipt me-2" aria-hidden="true"></i><span class="btn-text">' . lang(array('string' => 'Create the Invoice')) . '</span></button>
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
        // Asked from the order's own screen, the refusal is shown there.
        if ((string) ($_POST['from_order_screen'] ?? '') === '1') {
            $liveform->remove_form();
            $liveform_order = new liveform('view_order');
            $liveform_order->mark_error('_error', $result['error']);
            go(PATH . SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id);
        }

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
    $invoiced_order = ($order_id <= 0) ? null
        : db_item("SELECT status, payment_method, paid_at FROM orders WHERE id = '" . $order_id . "' LIMIT 1");

    if (is_array($invoiced_order) && pg_order_awaiting_payment($invoiced_order)) {
        $liveform_document->add_notice(lang('This order is still awaiting its bank transfer; the invoice has no payment date yet.'));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . (int) $result['invoice_id']);
}
