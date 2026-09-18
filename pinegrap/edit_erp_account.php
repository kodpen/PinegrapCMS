<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - edit an account, and read its statement.
 *
 * There is no delete button. An account with movements behind it is what a
 * balance is made of, and removing it would silently rewrite figures that have
 * already been reported. An account that should no longer be used is set to
 * passive instead, which keeps its history readable.
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
$liveform = new liveform('edit_erp_account');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_accounts.php';

// If the form has not been submitted yet, then show it.
if (!$_POST) {

    $account_id = (int) ($_GET['id'] ?? 0);
    $account = ($account_id > 0) ? erp_account($account_id) : null;

    if (!is_array($account)) {
        output_error(lang('The account could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Accounts') . '</a>');
        exit();
    }

    // Pre-populate the fields, but only when the form is not already carrying
    // what the user typed into a save that came back with an error.
    if ($liveform->field_in_session('id') == false) {
        $liveform->assign_field_value('id', (string) $account_id);
        $liveform->assign_field_value('title', $account['title']);
        $liveform->assign_field_value('kind', $account['kind']);
        $liveform->assign_field_value('status', $account['status']);
        $liveform->assign_field_value('is_person', ((int) $account['is_person'] === 1) ? '1' : '0');
        $liveform->assign_field_value('tax_number', $account['tax_number']);
        $liveform->assign_field_value('tax_office', $account['tax_office']);
        $liveform->assign_field_value('email', $account['email']);
        $liveform->assign_field_value('phone', $account['phone']);
        $liveform->assign_field_value('address', $account['address']);
        $liveform->assign_field_value('district', $account['district']);
        $liveform->assign_field_value('city', $account['city']);
        $liveform->assign_field_value('postcode', $account['postcode']);
        $liveform->assign_field_value('notes', $account['notes']);
        $liveform->assign_field_value('currency', strtoupper(trim((string) $account['currency'])));
    }

    $account_currency = strtoupper(trim((string) $account['currency']));
    $has_movements = ((int) db_value("SELECT COUNT(*) FROM erp_account_transactions WHERE account_id = '" . $account_id . "'") > 0);

    // The own-currency position beside the base one, for an account kept in
    // another currency.
    $output_balance_fc = '';
    if (erp_fx_enabled() && ($account_currency !== erp_base_currency())) {
        $balance_fc = (int) $account['balance_fc'];
        $output_balance_fc = '<span class="text-body-secondary">' . h(erp_money_out_currency(abs($balance_fc), $account_currency)) . ' ' . h($account_currency) . '</span>';
    }

    $balance = (int) $account['balance'];
    $balance_class = ($balance > 0) ? 'text-success' : (($balance < 0) ? 'text-danger' : 'text-muted');
    // Saying which way round it is beats a minus sign the reader has to read
    // the sign convention off.
    $balance_side = ($balance > 0) ? lang('owes you') : (($balance < 0) ? lang('you owe') : '');

    // ------------------------------------------------------------ statement
    //
    // Deliberately not a DataTable: a statement is read down the page in date
    // order because each line's balance is the one before it plus this line.
    // Sorting it by any other column turns that column of figures into
    // nonsense, so the ordering is not the reader's to change.
    $statement = erp_account_statement($account_id);
    $output_statement_rows = '';

    foreach ($statement['rows'] as $row) {
        $is_debit = ($row['direction'] === 'debit');

        // The statement runs in the base currency; a movement made in another
        // currency also says what it was in that currency.
        $row_currency = strtoupper(trim((string) $row['currency']));
        $output_fc = (erp_fx_enabled() && ($row_currency !== erp_base_currency()))
            ? ' <span class="text-body-secondary small">' . h(erp_money_out_currency((int) $row['amount'], $row_currency, false)) . '</span>'
            : '';

        // A receipt or payment opens on its own screen, where it can be
        // allocated or cancelled.
        $output_description = h($row['description']);
        if (in_array((string) $row['doc_type'], array('collection', 'payment'), true) && ((int) $row['doc_id'] > 0)) {
            $output_description = '<a href="erp_receipt.php?id=' . (int) $row['doc_id'] . '" class="link-body-emphasis">' . (($output_description !== '') ? $output_description : ('#' . (int) $row['doc_id'])) . '</a>';
        }

        $output_statement_rows .= '
            <tr>
                <td class="align-middle text-nowrap">' . h(prepare_form_data_for_output($row['doc_date'], 'date')) . '</td>
                <td class="align-middle">' . $output_description . '</td>
                <td class="align-middle text-end">' . ($is_debit ? h(erp_money_out((int) $row['amount_base'], false)) . $output_fc : '') . '</td>
                <td class="align-middle text-end">' . ($is_debit ? '' : h(erp_money_out((int) $row['amount_base'], false)) . $output_fc) . '</td>
                <td class="align-middle text-end">' . h(erp_money_out((int) $row['running_balance'])) . '</td>
            </tr>';
    }

    if ($output_statement_rows === '') {
        $output_statement_rows = '
            <tr><td colspan="5" class="text-center text-body-secondary py-4">' . lang('There are no movements on this account yet.') . '</td></tr>';
    }

    echo
    pg_page_shell([
        'title' => lang('Edit Account'),
        'extra_classes' => 'erp erp_accounts',
        'icon' => 'store',
        'heading' => h($account['title']),
        'heading_description' => lang('The account details, and every movement behind its balance.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_accounts.php'),
        'breadcrumb' => array(
            array('label' => lang('Accounts'), 'url' => $list_url),
            array('label' => $account['title']),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <div class="card my-4">
                <div class="card-body d-flex flex-wrap align-items-baseline gap-3">
                    <span class="text-uppercase text-body-secondary">' . lang('Balance') . '</span>
                    <span class="h4 mb-0 ' . $balance_class . '">' . h(erp_money_out(abs($balance))) . '</span>
                    <span class="text-body-secondary">' . h($balance_side) . '</span>
                    ' . $output_balance_fc . '
                </div>
            </div>

            <form name="form" action="edit_erp_account.php" method="post">
                ' . get_token_field() . '
                ' . $liveform->field(array('type' => 'hidden', 'name' => 'id')) . '
                ' . erp_account_form_cards($liveform, false, $has_movements) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Saving')) . '"><span class="bi bi-check-circle me-2"></span><span class="btn-text">' . lang(array('string' => 'Save')) . '</span></button>
                        </div>
                    </div>
                </nav>
            </form>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Statement') . '
                </div>
                <div class="card-body p-0 position-relative">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Description') . '</th>
                                <th class="text-end">' . lang('Debit') . '</th>
                                <th class="text-end">' . lang('Credit') . '</th>
                                <th class="text-end">' . lang('Balance') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_statement_rows . '</tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4">' . lang('Closing Balance') . '</td>
                                <td class="text-end">' . h(erp_money_out((int) $statement['closing'])) . '</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>' .
    output_footer();

    $liveform->remove_form();

// Otherwise the form has been submitted so process it.
} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    $account_id = (int) $liveform->get_field_value('id');

    // This screen only edits. Without an existing account behind the id the
    // save would fall through to an insert and create a record nobody asked for.
    if (($account_id <= 0) || (erp_account($account_id) === null)) {
        $liveform->remove_form();
        $liveform_list = new liveform('erp_accounts');
        $liveform_list->mark_error('_error', lang('The account could not be found.'));
        go(PATH . SOFTWARE_DIRECTORY . '/erp_accounts.php');
    }

    $liveform->validate_required_field('title', lang(array('string' => '{var:1} is required', 'vars' => lang('Name'))));

    // The stored currency unless foreign currency is on and an allowed code
    // was posted; an account with movements posts its own code back.
    $stored = erp_account($account_id);
    $currency = is_array($stored) ? strtoupper(trim((string) $stored['currency'])) : erp_base_currency();
    if (erp_fx_enabled()) {
        $chosen = strtoupper(trim((string) $liveform->get_field_value('currency')));
        $locked = ((int) db_value("SELECT COUNT(*) FROM erp_account_transactions WHERE account_id = '" . $account_id . "'") > 0);
        if (($chosen !== '') && ($chosen !== $currency)) {
            if ($locked) {
                $liveform->mark_error('currency', lang('Cannot be changed once there are movements.'));
            } elseif (!erp_fx_currency_allowed($chosen)) {
                $liveform->mark_error('currency', lang('That currency is not enabled for the ERP.'));
            } else {
                $currency = $chosen;
            }
        }
    }

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_account.php?id=' . $account_id);
    }

    $result = erp_account_save(array(
        'id' => $account_id,
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
        'created_by' => (int) $user['id'],
    ));

    if (!$result['success']) {
        $liveform->mark_error('_error', $result['error']);
        go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_account.php?id=' . $account_id);
    }

    log_activity(lang(array('string' => 'erp account ({var:1}) was changed', 'vars' => $liveform->get_field_value('title'))), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_list = new liveform('erp_accounts');
    $liveform_list->add_notice(lang('The account has been saved.'));

    go(PATH . SOFTWARE_DIRECTORY . '/erp_accounts.php');
}
