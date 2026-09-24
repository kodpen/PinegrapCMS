<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - read a delivery note.
 *
 * A note is not edited once written: it says what left the door on a day, and
 * the vehicle has gone. It is turned into an invoice, or cancelled if it was
 * written in error before anything was billed against it. The PDF is the copy
 * that travels with the goods.
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
$liveform = new liveform('edit_erp_waybill');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_waybills.php';

$waybill_id = (int) ($_REQUEST['id'] ?? 0);
$waybill = erp_waybill($waybill_id);

if (!is_array($waybill)) {
    output_error(lang('The delivery note could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Delivery Notes') . '</a>');
    exit();
}

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_waybill.php?id=' . $waybill_id;

if ($_POST) {

    validate_token_field();

    if (($_POST['erp_action'] ?? '') === 'invoice') {

        $result = erp_waybill_invoice($waybill_id, (int) $user['id']);

        if (!$result['success']) {
            $liveform->mark_error('_error', $result['error']);
            go($self_url);
        }

        log_activity(lang(array('string' => 'erp delivery note ({var:1}) was invoiced', 'vars' => $waybill['full_number'])), $_SESSION['sessionusername']);

        $liveform->remove_form();

        if ($result['draft']) {
            // Typed by hand: the lines are on a draft, the prices are the
            // operator's to finish. The editor is where that happens.
            $liveform_draft = new liveform('edit_erp_invoice_draft');
            $liveform_draft->add_notice(lang(array('string' => 'The lines of delivery note {var:1} are on this draft. Fill in the prices and issue the invoice.', 'vars' => $waybill['full_number'])));
            go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice_draft.php?id=' . (int) $result['invoice_id']);
        }

        $liveform_document = new liveform('edit_erp_invoice');
        $liveform_document->add_notice(lang(array('string' => 'Delivery note {var:1} has been invoiced.', 'vars' => $waybill['full_number'])));
        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . (int) $result['invoice_id']);
    }

    if (($_POST['erp_action'] ?? '') === 'cancel') {

        $result = erp_waybill_cancel($waybill_id);

        if ($result['success']) {
            log_activity(lang(array('string' => 'erp delivery note ({var:1}) was cancelled', 'vars' => $waybill['full_number'])), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The delivery note has been cancelled.'));
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

        go($self_url);
    }
}

$lines = erp_waybill_lines($waybill_id);
$invoice_state = erp_waybill_invoice_state($waybill);
$is_cancelled = ((string) $waybill['status'] === 'cancelled');

$empty = '<span class="text-body-secondary">&mdash;</span>';

$ship_time = (string) $waybill['ship_time'];
$ship_time = ($ship_time !== '' && $ship_time !== '00:00:00') ? substr($ship_time, 0, 5) : '';

if ($is_cancelled) {
    $output_state = '<span class="text-danger">' . lang('Cancelled') . '</span>';
} elseif ($invoice_state === 'invoiced') {
    $output_state = '<span class="text-success">' . lang('Invoiced') . '</span>';
} elseif ($invoice_state === 'draft') {
    $output_state = '<span class="text-warning">' . lang('Invoice drafted') . '</span>';
} else {
    $output_state = '<span class="text-primary">' . lang('Not yet invoiced') . '</span>';
}

$output_invoice = $empty;
if (((int) $waybill['invoice_id'] > 0) && ($invoice_state !== 'none')) {
    $output_invoice = ($invoice_state === 'draft')
        ? '<a href="edit_erp_invoice_draft.php?id=' . (int) $waybill['invoice_id'] . '">' . lang('Draft invoice') . '</a>'
        : '<a href="edit_erp_invoice.php?id=' . (int) $waybill['invoice_id'] . '">' . h($waybill['invoice_number']) . '</a>';
}

$output_order = ((int) $waybill['order_id'] > 0)
    ? '<a href="view_order.php?id=' . (int) $waybill['order_id'] . '">' . h($waybill['order_number'] ?: ('#' . (int) $waybill['order_id'])) . '</a>'
    : $empty;

$output_lines = '';
foreach ($lines as $line) {
    $quantity = erp_quantity_text($line['quantity']);
    $output_lines .= '
                            <tr>
                                <td class="text-body-secondary">' . (int) $line['line_no'] . '</td>
                                <td>' . h($line['description']) . ((trim((string) $line['product_name']) !== '' && (string) $line['product_name'] !== (string) $line['description']) ? '<div class="text-body-secondary small">' . h($line['product_name']) . '</div>' : '') . '</td>
                                <td class="text-end" data-sort="' . (float) $line['quantity'] . '">' . h($quantity) . '</td>
                                <td>' . h(erp_waybill_unit_label($line['unit_code'])) . '</td>
                            </tr>';
}

$ship_to_line = erp_address_locality(array(
    'district' => (string) $waybill['ship_to_district'],
    'city' => (string) $waybill['ship_to_city'],
    'state' => (string) ($waybill['ship_to_state'] ?? ''),
), (string) $waybill['ship_to_country']);

// Nothing left to do once the note is cancelled or its invoice stands. A
// note whose invoice is only drafted can still be cancelled together with
// the draft, but not from here: the draft says what it is billing.
$can_invoice = (!$is_cancelled && ($invoice_state === 'none'));
$can_cancel = (!$is_cancelled && ($invoice_state === 'none'));

// Where it was talked about in the workspace, and the tasks about it.
$output_workspace_button = '';

if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    $output_workspace_button = ws_record_button($user, 'waybill', (int) $waybill_id, (string) $waybill['full_number']);
}

echo
pg_page_shell([
        'title' => lang('Delivery Note'),
        'extra classes' => 'erp erp_waybills',
        'icon' => 'erp',
        'heading' => h($waybill['full_number']),
        'heading_description' => lang('The note as it was written: what left, to whom and how.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_waybills.php'),
        'breadcrumb' => array(
            array('label' => lang('Delivery Notes'), 'url' => $list_url),
            array('label' => $waybill['full_number']),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                        <a class="btn btn-sm btn-outline-secondary" href="get_erp_waybill_pdf.php?id=' . $waybill_id . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>' . lang('PDF') . '</a>
                        <a class="btn btn-sm btn-outline-secondary" href="get_erp_waybill_pdf.php?id=' . $waybill_id . '&amp;download=1"><i class="bi bi-download me-1"></i>' . lang('Download') . '</a>
                        ' . $output_workspace_button . '
                    </nav>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Delivery Note') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-sm-6 col-lg-3 my-2">
                            <div class="form-label text-body-secondary">' . lang('Account') . '</div>
                            <div><a href="edit_erp_account.php?id=' . (int) $waybill['account_id'] . '">' . h($waybill['account_title']) . '</a></div>
                        </div>
                        <div class="col-6 col-sm-3 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Date') . '</div>
                            <div>' . h(prepare_form_data_for_output($waybill['issue_date'], 'date')) . '</div>
                        </div>
                        <div class="col-6 col-sm-3 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Shipment Date') . '</div>
                            <div>' . h(prepare_form_data_for_output($waybill['ship_date'], 'date')) . (($ship_time !== '') ? ' <span class="text-body-secondary">' . h($ship_time) . '</span>' : '') . '</div>
                        </div>
                        <div class="col-6 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Status') . '</div>
                            <div>' . $output_state . '</div>
                        </div>
                        <div class="col-6 col-sm-4 col-lg-1 my-2">
                            <div class="form-label text-body-secondary">' . lang('Order') . '</div>
                            <div>' . $output_order . '</div>
                        </div>
                        <div class="col-6 col-sm-4 col-lg-2 my-2">
                            <div class="form-label text-body-secondary">' . lang('Invoice') . '</div>
                            <div>' . $output_invoice . '</div>
                        </div>
                    </div>
                    <div class="row border-top mt-2 pt-2">
                        <div class="col-12 col-lg-6 my-2">
                            <div class="form-label text-body-secondary">' . lang('Delivered to') . '</div>
                            <div class="fw-bold">' . h($waybill['ship_to_title']) . '</div>
                            <div>' . h($waybill['ship_to_address']) . '</div>
                            <div>' . h($ship_to_line) . '</div>
                        </div>
                        <div class="col-12 col-lg-6 my-2">
                            <div class="form-label text-body-secondary">' . lang('Transport') . '</div>
                            <div>' . ((trim((string) $waybill['carrier_title']) !== '') ? h($waybill['carrier_title']) . ((trim((string) $waybill['carrier_vkn']) !== '') ? ' <span class="text-body-secondary">' . h($waybill['carrier_vkn']) . '</span>' : '') : $empty) . '</div>
                            <div>' . ((trim((string) $waybill['plate']) !== '') ? '<span class="text-body-secondary">' . lang('Plate') . ':</span> ' . h($waybill['plate']) : '') . ((trim((string) $waybill['driver_name']) !== '') ? ' <span class="text-body-secondary ms-2">' . lang('Driver') . ':</span> ' . h($waybill['driver_name']) . ((trim((string) $waybill['driver_tckn']) !== '') ? ' <span class="text-body-secondary">' . h($waybill['driver_tckn']) . '</span>' : '') : '') . '</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Lines') . '
                </div>
                <div class="card-body p-0 position-relative table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th style="width:3rem">#</th>
                                <th>' . lang('Description') . '</th>
                                <th class="text-end" style="width:10rem">' . lang('Quantity') . '</th>
                                <th style="width:8rem">' . lang('Unit') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_lines . '</tbody>
                    </table>
                </div>
            </div>

            ' . ((trim((string) $waybill['notes']) !== '')
                ? '<div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Notes') . '
                </div>
                <div class="card-body">' . nl2br(h($waybill['notes'])) . '</div>
            </div>'
                : '') . '

            ' . (($can_invoice || $can_cancel)
                ? '<form name="form" action="edit_erp_waybill.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $waybill_id . '" />
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            ' . ($can_invoice
                                ? '<button type="submit" name="erp_action" value="invoice" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-receipt me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Invoice the Delivery Note') . '</span></button>'
                                : '') . '
                            ' . ($can_cancel
                                ? '<button type="submit" name="erp_action" value="cancel" class="btn my-1 btn-outline-danger" data-confirm-content="' . lang('Cancel this delivery note? It keeps its number and stays in the register as cancelled.') . '" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-x-circle me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Cancel the Document') . '</span></button>'
                                : '') . '
                        </div>
                    </div>
                </nav>
            </form>'
                : '') . '
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
