<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - record money collected from, or paid to, an account.
 *
 * Both halves land together: the till moves and the account moves in one
 * transaction, because a till that has grown without anybody's debt shrinking
 * is money the books cannot explain.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user, 'cash')) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('add_erp_receipt');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_cash.php';

// Collecting and paying are the same movement with the signs swapped, so they
// are one screen that knows which way round it is.
$direction = (($_REQUEST['direction'] ?? 'collection') === 'payment') ? 'payment' : 'collection';
$is_collection = ($direction === 'collection');

$page_title = $is_collection ? lang('Record a Receipt') : lang('Record a Payment');

// Reached from an invoice: that invoice decides the account and suggests the
// amount, so neither is left for the user to get wrong.
$preset_invoice_id = (int) ($_REQUEST['invoice_id'] ?? 0);
$preset_invoice = null;

if ($preset_invoice_id > 0) {
    $preset_invoice = db_item("SELECT i.*, a.title AS account_title
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON i.account_id = a.id
        WHERE i.id = '" . $preset_invoice_id . "' LIMIT 1");

    if (!is_array($preset_invoice)) {
        $preset_invoice_id = 0;
    }
}

if (!$_POST) {

    if ($liveform->field_in_session('doc_date') == false) {
        $liveform->assign_field_value('doc_date', prepare_form_data_for_output(date('Y-m-d'), 'date'));
        $liveform->assign_field_value('payment_method', 'cash');

        if ($preset_invoice !== null) {
            $liveform->assign_field_value('account_id', (string) (int) $preset_invoice['account_id']);
            $liveform->assign_field_value('amount', erp_money_out(erp_invoice_open_amount($preset_invoice), false));
            $liveform->assign_field_value('description', lang(array(
                'string' => 'Invoice {var:1}',
                'vars' => $preset_invoice['full_number'],
            )));
        }
    }

    $account_options = array();
    $account_options[lang('Choose an account')] = '';

    foreach (erp_accounts(array('status' => 'active')) as $account) {
        $account_options[$account['title']] = (string) (int) $account['id'];
    }

    $till_options = array();
    $till_options[lang('Choose a till or bank account')] = '';

    foreach ((array) db_items("SELECT id, name FROM erp_cash_accounts WHERE is_active = 1 ORDER BY sort_order ASC, name ASC") as $till) {
        $till_options[$till['name']] = (string) (int) $till['id'];
    }

    $method_options = array();
    $method_options[lang('Cash')] = 'cash';
    $method_options[lang('Bank transfer')] = 'transfer';
    $method_options[lang('Credit card')] = 'credit_card';
    $method_options[lang('Cheque')] = 'cheque';
    $method_options[lang('Other')] = 'other';

    // Without an invoice the money lands on the open account, which is what the
    // plan asks for: nothing is matched to the oldest open invoice behind the
    // user's back, because a customer who finds a different invoice marked paid
    // cannot reconcile with you.
    $output_invoice_picker = '';

    if (($preset_invoice === null) && $is_collection) {

        $open_invoices = (array) db_items("SELECT i.id, i.doc_type, i.full_number, i.issue_date,
                i.grand_total, i.paid_total, i.status, a.title AS account_title
            FROM erp_invoices i
            LEFT JOIN erp_accounts a ON i.account_id = a.id
            WHERE i.direction = 'sales'
              AND i.doc_type = 'invoice'
              AND i.status IN ('issued', 'partially_paid')
              AND i.grand_total > i.paid_total
            ORDER BY i.issue_date ASC, i.id ASC
            LIMIT 200");

        if (!empty($open_invoices)) {

            $invoice_options = array();
            $invoice_options[lang('Leave it on the open account')] = '';

            foreach ($open_invoices as $open_invoice) {
                // Goods that went back are not money still to come in, so an
                // invoice covered by a return does not belong on this list.
                $still_open = erp_invoice_open_amount($open_invoice);

                if ($still_open <= 0) {
                    continue;
                }

                $label = $open_invoice['account_title'] . ' - ' . $open_invoice['full_number']
                    . ' - ' . erp_money_out($still_open);
                $invoice_options[$label] = (string) (int) $open_invoice['id'];
            }

            $output_invoice_picker = '
                        <div class="row">
                            <div class="col-12 col-lg-8 my-2">
                                <label for="invoice_id" class="form-label">' . lang('Invoice to Close') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'select', 'id' => 'invoice_id', 'name' => 'invoice_id',
                                    'class' => 'form-select', 'options' => $invoice_options)) . '
                                <div class="form-text">' . lang('Optional. The invoice has to belong to the account chosen above. Anything over what it is short of stays on the open account.') . '</div>
                            </div>
                        </div>';
        }
    }

    echo
    pg_page_shell([
        'title' => $page_title,
        'extra_classes' => 'erp erp_cash',
        'icon' => 'store',
        'heading' => $page_title,
        'heading_description' => $is_collection
            ? lang('Money coming in: the till grows and what the account owes you falls.')
            : lang('Money going out: the till falls and what you owe the account falls with it.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_cash.php'),
        'breadcrumb' => array(
            array('label' => lang('Cash and Bank'), 'url' => $list_url),
            array('label' => $page_title),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_receipt.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="direction" value="' . h($direction) . '" />
                ' . (($preset_invoice !== null)
                    ? '<input type="hidden" name="invoice_id" value="' . $preset_invoice_id . '" />'
                    : '') . '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . $page_title . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-lg-5 my-2">
                                <label for="account_id" class="form-label">' . lang('Account') . '</label>
                                ' . (($preset_invoice !== null)
                                    ? '<input type="hidden" name="account_id" value="' . (int) $preset_invoice['account_id'] . '" />
                                <input class="form-control" type="text" value="' . h($preset_invoice['account_title']) . '" readonly="readonly" />
                                <div class="form-text">' . lang(array(
                                        'string' => 'Closing invoice {var:1}, {var:2} still outstanding.',
                                        'vars' => array($preset_invoice['full_number'], erp_money_out(erp_invoice_open_amount($preset_invoice))),
                                    )) . '</div>'
                                    : $liveform->output_field(array(
                                        'type' => 'select', 'id' => 'account_id', 'name' => 'account_id',
                                        'class' => 'form-select', 'options' => $account_options))) . '
                            </div>
                            <div class="col-12 col-lg-4 my-2">
                                <label for="cash_account_id" class="form-label">' . lang('Till or Bank Account') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'select', 'id' => 'cash_account_id', 'name' => 'cash_account_id',
                                    'class' => 'form-select', 'options' => $till_options)) . '
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <label for="amount" class="form-label">' . lang('Amount') . '</label>
                                <div class="input-group">
                                    ' . $liveform->output_field(array(
                                        'type' => 'text', 'id' => 'amount', 'name' => 'amount',
                                        'class' => 'form-control text-end', 'maxlength' => '15',
                                        'inputmode' => 'decimal', 'autocomplete' => 'off', 'required' => 'required')) . '
                                    <label class="input-group-text" for="amount">' . BASE_CURRENCY_SYMBOL . '</label>
                                </div>
                            </div>
                        </div>
                        ' . $output_invoice_picker . '
                        <div class="row">
                            <div class="col-12 col-sm-4 col-lg-3 my-2">
                                <label for="doc_date" class="form-label">' . lang('Date') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'text', 'id' => 'doc_date', 'name' => 'doc_date',
                                    'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                                    'autocomplete' => 'off')) . '
                                ' . get_date_picker_format() . '
                                <script>$("#doc_date").datepicker(datetimepicker_options);</script>
                            </div>
                            <div class="col-12 col-sm-4 col-lg-3 my-2">
                                <label for="payment_method" class="form-label">' . lang('Payment Method') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'select', 'id' => 'payment_method', 'name' => 'payment_method',
                                    'class' => 'form-select', 'options' => $method_options)) . '
                            </div>
                            <div class="col-12 col-lg-6 my-2">
                                <label for="description" class="form-label">' . lang('Description') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'text', 'id' => 'description', 'name' => 'description',
                                    'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                            </div>
                        </div>
                    </div>
                </div>
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Saving')) . '"><span class="bi bi-check-circle me-2"></span><span class="btn-text">' . lang(array('string' => 'Save')) . '</span></button>
                        </div>
                    </div>
                </nav>
            </form>
        </div>
    </div>
</main>' .
    output_footer();

    $liveform->remove_form();

} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    $back = PATH . SOFTWARE_DIRECTORY . '/add_erp_receipt.php?direction=' . $direction
        . (($preset_invoice_id > 0) ? '&invoice_id=' . $preset_invoice_id : '');

    $doc_date = trim((string) $liveform->get_field_value('doc_date'));

    if (($doc_date !== '') && (validate_date($doc_date) == false)) {
        $liveform->mark_error('doc_date', lang('Please enter a valid date.'));
        go($back);
    }

    $result = erp_post_receipt(array(
        'direction' => $direction,
        'account_id' => (int) $liveform->get_field_value('account_id'),
        'cash_account_id' => (int) $liveform->get_field_value('cash_account_id'),
        'amount' => erp_kurus($liveform->get_field_value('amount')),
        'doc_date' => ($doc_date !== '') ? prepare_form_data_for_input($doc_date, 'date') : date('Y-m-d'),
        'payment_method' => $liveform->get_field_value('payment_method'),
        'description' => trim((string) $liveform->get_field_value('description')),
        'invoice_id' => (int) $liveform->get_field_value('invoice_id'),
        'created_by' => (int) $user['id'],
    ));

    if (!$result['success']) {
        $liveform->mark_error('_error', $result['error']);
        go($back);
    }

    log_activity($is_collection ? lang('erp receipt was recorded') : lang('erp payment was recorded'), $_SESSION['sessionusername']);

    // Read before the form is thrown away, not after.
    $settled_invoice_id = (int) ($preset_invoice_id ?: $liveform->get_field_value('invoice_id'));

    $liveform->remove_form();

    // Back to whichever screen the question was asked on.
    if ($settled_invoice_id > 0) {
        $liveform_invoice = new liveform('edit_erp_invoice');
        $liveform_invoice->add_notice($is_collection ? lang('Receipt recorded.') : lang('Payment recorded.'));
        go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . $settled_invoice_id);
    }

    $liveform_list = new liveform('erp_cash');
    $liveform_list->add_notice($is_collection ? lang('Receipt recorded.') : lang('Payment recorded.'));

    go(PATH . SOFTWARE_DIRECTORY . '/erp_cash.php');
}
