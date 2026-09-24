<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - move money between your own tills.
 *
 * Nobody's debt changes here, so this touches no account: it is two cash
 * movements that have to happen together or not at all.
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
$liveform = new liveform('add_erp_transfer');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_cash.php';

if (!$_POST) {

    if ($liveform->field_in_session('doc_date') == false) {
        $liveform->assign_field_value('doc_date', prepare_form_data_for_output(date('Y-m-d'), 'date'));
    }

    $till_options = array();
    $till_options[lang('Choose a till or bank account')] = '';

    foreach ((array) db_items("SELECT id, name FROM erp_cash_accounts WHERE is_active = 1 ORDER BY sort_order ASC, name ASC") as $till) {
        $till_options[$till['name']] = (string) (int) $till['id'];
    }

    echo
    pg_page_shell([
        'title' => lang('Transfer'),
        'extra classes' => 'erp erp_cash',
        'icon' => 'erp',
        'heading' => lang('Transfer'),
        'heading_description' => lang('Move money from one of your tills to another. Nobody owes anybody anything differently afterwards.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_cash.php'),
        'breadcrumb' => array(
            array('label' => lang('Cash and Bank'), 'url' => $list_url),
            array('label' => lang('Transfer')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_transfer.php" method="post">
                ' . get_token_field() . '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Transfer') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-lg-4 my-2">
                                <label for="from_id" class="form-label">' . lang('From') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'select', 'id' => 'from_id', 'name' => 'from_id',
                                    'class' => 'form-select', 'options' => $till_options)) . '
                            </div>
                            <div class="col-12 col-lg-4 my-2">
                                <label for="to_id" class="form-label">' . lang('To') . '</label>
                                ' . $liveform->output_field(array(
                                    'type' => 'select', 'id' => 'to_id', 'name' => 'to_id',
                                    'class' => 'form-select', 'options' => $till_options)) . '
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4 my-2">
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
                            <div class="col-12 col-lg-9 my-2">
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
                            <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-check-circle me-2" aria-hidden="true"></i><span class="btn-text">' . lang(array('string' => 'Save')) . '</span></button>
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

    $back = PATH . SOFTWARE_DIRECTORY . '/add_erp_transfer.php';

    $doc_date = trim((string) $liveform->get_field_value('doc_date'));

    if (($doc_date !== '') && (validate_date($doc_date) == false)) {
        $liveform->mark_error('doc_date', lang('Please enter a valid date.'));
        go($back);
    }

    $result = erp_post_transfer(array(
        'from_id' => (int) $liveform->get_field_value('from_id'),
        'to_id' => (int) $liveform->get_field_value('to_id'),
        'amount' => erp_kurus($liveform->get_field_value('amount')),
        'doc_date' => ($doc_date !== '') ? prepare_form_data_for_input($doc_date, 'date') : date('Y-m-d'),
        'description' => trim((string) $liveform->get_field_value('description')),
        'created_by' => (int) $user['id'],
    ));

    if (!$result['success']) {
        $liveform->mark_error('_error', $result['error']);
        go($back);
    }

    log_activity(lang('erp transfer was recorded'), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_list = new liveform('erp_cash');
    $liveform_list->add_notice(lang('Transfer recorded.'));

    go(PATH . SOFTWARE_DIRECTORY . '/erp_cash.php');
}
