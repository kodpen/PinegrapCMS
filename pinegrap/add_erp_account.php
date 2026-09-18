<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - create an account.
 *
 * An opening balance can be entered here and is written as a movement rather
 * than onto the balance directly, so where the figure came from stays readable
 * a year later.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/account_form.php');
include_once('liveform.class.php');
$liveform = new liveform('add_erp_account');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_accounts.php';

// If the form has not been submitted yet, then show it.
if (!$_POST) {

    // Defaults, applied only on the first visit so a failed save comes back
    // holding what the user typed rather than the defaults.
    if ($liveform->field_in_session('kind') == false) {
        $liveform->assign_field_value('kind', 'customer');
        $liveform->assign_field_value('status', 'active');
        $liveform->assign_field_value('is_person', '1');
        $liveform->assign_field_value('opening_amount', '0');
        $liveform->assign_field_value('opening_date', prepare_form_data_for_output(date('Y-m-d'), 'date'));
        $liveform->assign_field_value('currency', erp_base_currency());
    }

    echo
    pg_page_shell([
        'title' => lang('Create Account'),
        'extra_classes' => 'erp erp_accounts',
        'icon' => 'store',
        'heading' => lang('Create Account'),
        'heading_description' => lang('Add a customer or a supplier, with an opening balance if they already owe or are owed.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_accounts.php'),
        'breadcrumb' => array(
            array('label' => lang('Accounts'), 'url' => $list_url),
            array('label' => lang('Create Account')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_account.php" method="post">
                ' . get_token_field() . '
                ' . erp_account_form_cards($liveform, true) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Creating')) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string' => 'Create')) . '</span></button>
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

    $liveform->validate_required_field('title', lang(array('string' => '{var:1} is required', 'vars' => lang('Name'))));

    $payment_days = trim((string) $liveform->get_field_value('payment_days'));
    if (($payment_days !== '') && ((preg_match('/^[0-9]{1,4}$/', $payment_days) !== 1) || ((int) $payment_days > 3650))) {
        $liveform->mark_error('payment_days', lang('Payment term must be a whole number of days, 0 to 3650.'));
    }

    $opening_amount = trim((string) $liveform->get_field_value('opening_amount'));
    $opening_date = trim((string) $liveform->get_field_value('opening_date'));

    if (($opening_amount !== '') && (erp_kurus($opening_amount) === 0) && (preg_match('/^-?[0-9.,]+$/', $opening_amount) !== 1)) {
        $liveform->mark_error('opening_amount', lang('Please enter a valid amount.'));
    }

    if (($opening_date !== '') && (validate_date($opening_date) == false)) {
        $liveform->mark_error('opening_date', lang('Please enter a valid date.'));
    }

    // The account's currency: the base unless foreign currency is on and an
    // allowed code was chosen.
    $currency = erp_base_currency();
    if (erp_fx_enabled()) {
        $chosen = strtoupper(trim((string) $liveform->get_field_value('currency')));
        if ($chosen !== '') {
            if (!erp_fx_currency_allowed($chosen)) {
                $liveform->mark_error('currency', lang('That currency is not enabled for the ERP.'));
            }
            $currency = $chosen;
        }
    }

    // A foreign-currency opening figure is booked at the recorded rate of
    // its date; without one the account cannot be opened in that currency.
    $opening = erp_kurus($opening_amount);
    $opening_date_sql = ($opening_date !== '') ? prepare_form_data_for_input($opening_date, 'date') : date('Y-m-d');
    $opening_rate = array('rate' => 1.0, 'rate_date' => $opening_date_sql, 'source' => 'base');

    if (($opening !== 0) && ($currency !== erp_base_currency()) && ($liveform->check_form_errors() == false)) {
        $opening_rate = erp_fx_rate_for($currency, $opening_date_sql);
        if (!is_array($opening_rate)) {
            $liveform->mark_error('opening_amount', lang(array(
                'string' => 'No exchange rate is recorded for {var:1} on {var:2}. Run Update Exchange Rates or enter the rate.',
                'vars' => array($currency, prepare_form_data_for_output($opening_date_sql, 'date')),
            )));
        }
    }

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/add_erp_account.php');
    }

    $result = erp_account_save(array(
        'id' => 0,
        'kind' => $liveform->get_field_value('kind'),
        'title' => $liveform->get_field_value('title'),
        'is_person' => ($liveform->get_field_value('is_person') === '1'),
        'tax_number' => $liveform->get_field_value('tax_number'),
        'tax_office' => $liveform->get_field_value('tax_office'),
        'email' => $liveform->get_field_value('email'),
        'phone' => $liveform->get_field_value('phone'),
        'address' => $liveform->get_field_value('address'),
        'district' => $liveform->get_field_value('district'),
        'city' => $liveform->get_field_value('city'),
        'postcode' => $liveform->get_field_value('postcode'),
        'currency' => $currency,
        'status' => $liveform->get_field_value('status'),
        'notes' => $liveform->get_field_value('notes'),
        'payment_days' => (int) $payment_days,
        'created_by' => (int) $user['id'],
    ));

    if (!$result['success']) {
        $liveform->mark_error('_error', $result['error']);
        go(PATH . SOFTWARE_DIRECTORY . '/add_erp_account.php');
    }

    if ($opening !== 0) {
        $opened = erp_account_open(array(
            'account_id' => $result['id'],
            'amount' => $opening,
            'doc_date' => $opening_date_sql,
            'currency' => $currency,
            'exchange_rate' => $opening_rate['rate'],
            'exchange_rate_date' => $opening_rate['rate_date'],
            'exchange_rate_source' => $opening_rate['source'],
            'created_by' => (int) $user['id'],
        ));

        // The account itself is saved by now, so a refused opening balance is
        // reported against the account rather than losing the whole entry.
        if (!$opened['success']) {
            $liveform->remove_form();
            $liveform_edit = new liveform('edit_erp_account');
            $liveform_edit->mark_error('_error', $opened['error']);
            go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_account.php?id=' . (int) $result['id']);
        }
    }

    log_activity(lang(array('string' => 'erp account ({var:1}) was created', 'vars' => $liveform->get_field_value('title'))), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_list = new liveform('erp_accounts');
    $liveform_list->add_notice(lang('The account has been created.'));

    go(PATH . SOFTWARE_DIRECTORY . '/erp_accounts.php');
}
