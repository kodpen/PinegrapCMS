<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - raise a return against an invoice.
 *
 * Every line of the original is listed with what is still returnable on it, so
 * a partial return is the same screen as a full one. Nothing is edited on the
 * original document: the return is its own paper, with its own number.
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
$liveform = new liveform('add_erp_return');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_invoices.php';

$invoice_id = (int) ($_REQUEST['invoice_id'] ?? 0);

$invoice = ($invoice_id > 0)
    ? db_item("SELECT i.*, a.title AS account_title
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON i.account_id = a.id
        WHERE i.id = '" . $invoice_id . "' LIMIT 1")
    : null;

if (!is_array($invoice)) {
    output_error(lang('The invoice could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Invoices') . '</a>');
    exit();
}

$invoice_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $invoice_id;

// If the form has not been submitted yet, then show it.
if (!$_POST) {

    $lines = erp_returnable_lines($invoice_id);

    $anything_left = false;

    foreach ($lines as $line) {
        if ($line['remaining_qty'] > 0.00001) {
            $anything_left = true;
        }
    }

    if (!$anything_left) {
        output_error(lang('Everything on this invoice has already been returned.')
            . ' <a href="' . h($invoice_url) . '">' . lang('Invoice') . '</a>');
        exit();
    }

    if ($liveform->field_in_session('issue_date') == false) {
        $liveform->assign_field_value('issue_date', prepare_form_data_for_output(date('Y-m-d'), 'date'));

        // Everything back, because that is the common case; anything less is a
        // number the user lowers rather than a set of boxes they have to tick.
        foreach ($lines as $line) {
            $liveform->assign_field_value('qty_' . (int) $line['id'],
                rtrim(rtrim(number_format($line['remaining_qty'], 4, '.', ''), '0'), '.'));
        }
    }

    $output_lines = '';

    foreach ($lines as $line) {
        $tidy = function ($value) {
            return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        };

        $returned = (float) $line['returned_qty'];

        $output_lines .= '
            <tr>
                <td class="align-middle text-body-secondary">' . (int) $line['line_no'] . '</td>
                <td class="align-middle">' . h($line['description']) . '</td>
                <td class="align-middle text-end">' . h($tidy($line['quantity'])) . '</td>
                <td class="align-middle text-end ' . (($returned > 0) ? 'text-warning' : 'text-body-secondary') . '">' . h($tidy($returned)) . '</td>
                <td class="align-middle text-end">' . h(erp_money_out_currency((int) $line['unit_price'], (string) $invoice['currency'])) . '</td>
                <td class="align-middle" style="width:9rem">'
                    . (($line['remaining_qty'] > 0.00001)
                        ? $liveform->output_field(array(
                            'type' => 'text', 'name' => 'qty_' . (int) $line['id'],
                            'id' => 'qty_' . (int) $line['id'],
                            'class' => 'form-control form-control-sm text-end',
                            'inputmode' => 'decimal', 'maxlength' => '10', 'autocomplete' => 'off'))
                        : '<span class="text-body-secondary">&mdash;</span>') . '</td>
            </tr>';
    }

    echo
    pg_page_shell([
        'title' => lang('Return an Invoice'),
        'extra_classes' => 'erp erp_invoices',
        'icon' => 'store',
        'heading' => lang('Return an Invoice'),
        'heading_description' => lang('Give back what came back. The original is left as it was issued; this is a document of its own.'),
        'cancel' => array('enable' => 'true', 'url' => 'edit_erp_invoice.php?id=' . $invoice_id),
        'breadcrumb' => array(
            array('label' => lang('Invoices'), 'url' => $list_url),
            array('label' => $invoice['full_number'], 'url' => $invoice_url),
            array('label' => lang('Return an Invoice')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_return.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="invoice_id" value="' . $invoice_id . '" />

                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Return an Invoice') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-lg-4 my-2">
                                <div class="form-label text-body-secondary">' . lang('Invoice') . '</div>
                                <div><a href="' . h($invoice_url) . '">' . h($invoice['full_number']) . '</a></div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4 my-2">
                                <div class="form-label text-body-secondary">' . lang('Account') . '</div>
                                <div>' . h($invoice['account_title']) . '</div>
                            </div>
                            <div class="col-12 col-sm-4 col-lg-3 my-2">
                                <label for="issue_date" class="form-label">' . lang('Date') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'text', 'id' => 'issue_date', 'name' => 'issue_date',
                                    'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                                    'autocomplete' => 'off')) . '
                                ' . get_date_picker_format() . '
                                <script>$("#issue_date").datepicker(datetimepicker_options);</script>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Lines') . '
                    </div>
                    <div class="card-body p-0 position-relative">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width:3rem">#</th>
                                    <th>' . lang('Description') . '</th>
                                    <th class="text-end">' . lang('Sold') . '</th>
                                    <th class="text-end">' . lang('Already Returned') . '</th>
                                    <th class="text-end">' . lang('Unit price') . '</th>
                                    <th class="text-end">' . lang('Returning') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_lines . '</tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-reset border-0">
                        <div class="form-text">' . lang('Set a quantity to zero to leave that line out. Returning everything gives back exactly what the invoice charged, to the kurus.') . '</div>
                    </div>
                </div>

                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Creating')) . '"><span class="bi bi-arrow-return-left me-2"></span><span class="btn-text">' . lang(array('string' => 'Create the Return')) . '</span></button>
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

    $back = PATH . SOFTWARE_DIRECTORY . '/add_erp_return.php?invoice_id=' . $invoice_id;

    $issue_date = trim((string) $liveform->get_field_value('issue_date'));

    if (($issue_date !== '') && (validate_date($issue_date) == false)) {
        $liveform->mark_error('issue_date', lang('Please enter a valid date.'));
        go($back);
    }

    $quantities = array();

    foreach (erp_returnable_lines($invoice_id) as $line) {
        $raw = str_replace(',', '.', trim((string) $liveform->get_field_value('qty_' . (int) $line['id'])));
        $quantities[(int) $line['id']] = ($raw === '') ? 0 : (float) $raw;
    }

    $result = erp_invoice_return(array(
        'invoice_id' => $invoice_id,
        'quantities' => $quantities,
        'issue_date' => ($issue_date !== '') ? prepare_form_data_for_input($issue_date, 'date') : date('Y-m-d'),
        'created_by' => (int) $user['id'],
    ));

    if (!$result['success']) {
        $liveform->mark_error('_error', $result['error']);
        go($back);
    }

    log_activity(lang(array('string' => 'erp return ({var:1}) was created', 'vars' => $result['full_number'])), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_document = new liveform('edit_erp_invoice');
    $liveform_document->add_notice(lang(array('string' => 'Return {var:1} created.', 'vars' => $result['full_number'])));

    go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . (int) $result['invoice_id']);
}
