<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - open a till or a bank account.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/account_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/till_form.php');
include_once('liveform.class.php');
$liveform = new liveform('add_erp_till');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_cash.php';

if (!$_POST) {

    if ($liveform->field_in_session('kind') == false) {
        $liveform->assign_field_value('kind', 'cash');
        $liveform->assign_field_value('is_active', '1');
        $liveform->assign_field_value('opening_balance', '0');
        $liveform->assign_field_value('currency', erp_base_currency());
    }

    echo
    pg_page_shell([
        'title' => lang('Open a Till'),
        'extra classes' => 'erp erp_cash',
        'icon' => 'erp',
        'heading' => lang('Open a Till'),
        'heading_description' => lang('Add somewhere money is kept: a cash drawer, a bank account or a card terminal.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_cash.php'),
        'breadcrumb' => array(
            array('label' => lang('Cash and Bank'), 'url' => $list_url),
            array('label' => lang('Open a Till')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_till.php" method="post">
                ' . get_token_field() . '
                ' . erp_till_form_cards($liveform) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Creating')) . '"><i class="bi bi-plus-circle me-2" aria-hidden="true"></i><span class="btn-text">' . lang(array('string' => 'Create')) . '</span></button>
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

    $liveform->validate_required_field('name', lang(array('string' => '{var:1} is required', 'vars' => lang('Name'))));

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/add_erp_till.php');
    }

    $kinds = array('cash', 'bank', 'pos', 'credit_card');
    $kind = in_array($liveform->get_field_value('kind'), $kinds, true) ? $liveform->get_field_value('kind') : 'cash';

    // The base currency unless foreign currency is on and an allowed code was chosen.
    $currency = erp_base_currency();
    if (erp_fx_enabled()) {
        $chosen = strtoupper(trim((string) $liveform->get_field_value('currency')));
        if ($chosen !== '') {
            if (!erp_fx_currency_allowed($chosen)) {
                $liveform->mark_error('currency', lang('That currency is not enabled for the ERP.'));
                go(PATH . SOFTWARE_DIRECTORY . '/add_erp_till.php');
            }
            $currency = $chosen;
        }
    }

    erp_query("INSERT INTO erp_cash_accounts SET
        name = '" . escape(trim((string) $liveform->get_field_value('name'))) . "',
        kind = '" . escape($kind) . "',
        currency = '" . escape($currency) . "',
        iban = '" . escape(trim((string) $liveform->get_field_value('iban'))) . "',
        bank_name = '" . escape(trim((string) $liveform->get_field_value('bank_name'))) . "',
        opening_balance = '" . erp_kurus($liveform->get_field_value('opening_balance')) . "',
        is_active = '" . (($liveform->get_field_value('is_active') === '0') ? 0 : 1) . "',
        created_at = '" . time() . "',
        updated_at = '" . time() . "'");

    $till_id = (int) mysqli_insert_id(db::$con);

    // The opening figure is a column rather than a movement, so the balance is
    // derived again rather than nudged.
    erp_cash_refresh_balance($till_id);

    log_activity(lang(array('string' => 'erp till ({var:1}) was opened', 'vars' => $liveform->get_field_value('name'))), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_list = new liveform('erp_cash');
    $liveform_list->add_notice(lang('The till has been opened.'));

    go(PATH . SOFTWARE_DIRECTORY . '/erp_cash.php');
}
