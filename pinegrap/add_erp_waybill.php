<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - write a delivery note.
 *
 * By hand, or for an order (?order_id=): then the form opens filled with the
 * order's goods, its recipient's address and the carrier and ship date the
 * order screen recorded, so what is saved is what left the door rather than
 * what was planned. The note is numbered when it is saved; a form opened and
 * abandoned costs the series nothing.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/waybill_form.php');
include_once('liveform.class.php');
$liveform = new liveform('add_erp_waybill');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_waybills.php';
$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_erp_waybill.php';

// If the form has not been submitted yet, then show it.
if (!$_POST) {

    $order_id = (int) ($_GET['order_id'] ?? 0);
    $invoice_id = (int) ($_GET['invoice_id'] ?? 0);
    $order = null;
    $invoice = null;
    $given_lines = array();

    // Opened from an invoice: the note is for what that invoice sold. An
    // invoice written from an order is the order's note with the invoice
    // attached, so the order path below takes over.
    if ($invoice_id > 0) {
        $invoice = db_item("SELECT id, full_number, order_id, status FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

        if (!is_array($invoice)) {
            $liveform_list = new liveform('erp_waybills');
            $liveform_list->mark_error('_error', lang('The invoice could not be found.'));
            go($list_url);
        }

        $existing_for_invoice = erp_waybills_for_invoice($invoice_id);

        if ($existing_for_invoice) {
            $liveform_document = new liveform('edit_erp_waybill');
            $liveform_document->add_notice(lang(array('string' => 'Invoice {var:1} already has delivery note {var:2}.', 'vars' => array($invoice['full_number'], $existing_for_invoice[0]['full_number']))));
            go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_waybill.php?id=' . (int) $existing_for_invoice[0]['id']);
        }

        if ((int) $invoice['order_id'] > 0) {
            $order_id = (int) $invoice['order_id'];
        } else {
            $from_invoice = erp_waybill_data_from_invoice($invoice_id, (int) $user['id']);

            if (!$from_invoice['success']) {
                $liveform_document = new liveform('edit_erp_invoice');
                $liveform_document->mark_error('_error', $from_invoice['error']);
                go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id);
            }

            $given_lines = $from_invoice['data']['lines'];
            erp_waybill_form_prefill($liveform, $from_invoice['data']);
        }
    }

    if ($order_id > 0) {
        $existing = erp_waybills_for_order($order_id);

        if ($existing) {
            // One shipment, one note. The one that exists is where to go.
            $order_number = (string) db_value("SELECT order_number FROM orders WHERE id = '" . $order_id . "' LIMIT 1");
            $liveform_document = new liveform('edit_erp_waybill');
            $liveform_document->add_notice(lang(array('string' => 'Order #{var:1} already has delivery note {var:2}.', 'vars' => array(($order_number !== '') ? $order_number : (string) $order_id, $existing[0]['full_number']))));
            go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_waybill.php?id=' . (int) $existing[0]['id']);
        }

        // With an invoice in hand its carrier fills what the order's shipping
        // method leaves blank; the invoice is attached either way. Without one
        // the order's own invoice, if any, is attached when the note is saved.
        $from_order = ($invoice !== null)
            ? erp_waybill_data_from_invoice($invoice_id, (int) $user['id'])
            : erp_waybill_data_from_order($order_id, (int) $user['id']);

        if (!$from_order['success']) {
            $liveform_list = new liveform('erp_waybills');
            $liveform_list->mark_error('_error', $from_order['error']);
            go($list_url);
        }

        $order = db_item("SELECT id, order_number FROM orders WHERE id = '" . $order_id . "' LIMIT 1");
        $given_lines = $from_order['data']['lines'];
        erp_waybill_form_prefill($liveform, $from_order['data']);

        if (($invoice === null) && function_exists('erp_invoice_for_order')) {
            $order_invoice_id = (int) erp_invoice_for_order($order_id);
            if ($order_invoice_id > 0) {
                $invoice = db_item("SELECT id, full_number, order_id, status FROM erp_invoices WHERE id = '" . $order_invoice_id . "' LIMIT 1");
            }
        }
    } elseif ($invoice === null) {
        erp_waybill_form_prefill($liveform, array(
            'issue_date' => date('Y-m-d'),
            'ship_to_country' => erp_default_country_code(),
        ));
    }

    echo
    pg_page_shell([
        'title' => lang('New Delivery Note'),
        'extra_classes' => 'erp erp_waybills',
        'icon' => 'store',
        'heading' => lang('New Delivery Note'),
        'heading_description' => lang('What left, to whom, where, when and with which vehicle. Prices come later, on the invoice.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_waybills.php'),
        'breadcrumb' => array(
            array('label' => lang('Delivery Notes'), 'url' => $list_url),
            array('label' => lang('New Delivery Note')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_waybill.php" method="post" autocomplete="off">
                ' . get_token_field() . '
                ' . $liveform->output_field(array('type' => 'hidden', 'id' => 'order_id', 'name' => 'order_id')) . '
                ' . $liveform->output_field(array('type' => 'hidden', 'id' => 'invoice_id', 'name' => 'invoice_id')) . '
                ' . erp_waybill_form_cards($liveform, array(
                    'lines' => erp_waybill_form_lines($liveform, $given_lines),
                    'order' => $order,
                    'invoice' => $invoice,
                )) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Creating')) . '"><span class="bi bi-truck me-2"></span><span class="btn-text">' . lang('Issue the Delivery Note') . '</span></button>
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

    $read = erp_waybill_form_read($liveform, $user);

    // Where a refused form goes back to: the address it was opened from.
    $back_url = $self_url;
    if ((int) $liveform->get_field_value('invoice_id') > 0) {
        $back_url .= '?invoice_id=' . (int) $liveform->get_field_value('invoice_id');
    } elseif ((int) $liveform->get_field_value('order_id') > 0) {
        $back_url .= '?order_id=' . (int) $liveform->get_field_value('order_id');
    }

    if (!$read['success']) {
        $liveform->mark_error($read['field'], $read['error']);
        go($back_url);
    }

    $result = erp_waybill_create($read['data']);

    if (!$result['success']) {
        $liveform->mark_error('_error', $result['error']);
        go($back_url);
    }

    log_activity(lang(array('string' => 'erp delivery note ({var:1}) was created', 'vars' => $result['full_number'])), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_document = new liveform('edit_erp_waybill');
    $liveform_document->add_notice(lang(array('string' => 'Delivery note {var:1} created.', 'vars' => $result['full_number'])));

    // What the note filled in elsewhere, said once.
    $written_back = (array) ($result['written_back'] ?? array());
    if (isset($written_back['order_ship_date'])) {
        $liveform_document->add_notice(lang('The ship date was written onto the order\'s recipients, which had none.'));
    }
    if (isset($written_back['invoice_shipment_date']) || isset($written_back['invoice_carrier'])) {
        $liveform_document->add_notice(lang('The shipment date and the carrier were written onto the invoice, which had none.'));
    }

    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_waybill.php?id=' . (int) $result['waybill_id']);
}
