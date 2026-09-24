<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - edit a till, and read its cash book.
 *
 * A till that has movements behind it cannot be deleted, because its balance is
 * made of them and other documents point at them. One that is no longer used is
 * set to passive, which keeps it out of the way without rewriting history.
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
$liveform = new liveform('edit_erp_till');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_cash.php';

if (!$_POST) {

    $till_id = (int) ($_GET['id'] ?? 0);
    $till = ($till_id > 0) ? db_item("SELECT * FROM erp_cash_accounts WHERE id = '" . $till_id . "' LIMIT 1") : null;

    if (!is_array($till)) {
        output_error(lang('The till could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Cash and Bank') . '</a>');
        exit();
    }

    if ($liveform->field_in_session('id') == false) {
        $liveform->assign_field_value('id', (string) $till_id);
        $liveform->assign_field_value('name', $till['name']);
        $liveform->assign_field_value('kind', $till['kind']);
        $liveform->assign_field_value('is_active', ((int) $till['is_active'] === 1) ? '1' : '0');
        $liveform->assign_field_value('bank_name', $till['bank_name']);
        $liveform->assign_field_value('iban', $till['iban']);
        // The sign stays on: erp_kurus() reads a leading minus back, and an
        // opening balance shown without it would be saved as a positive figure.
        $liveform->assign_field_value('opening_balance', erp_money_out_currency((int) $till['opening_balance'], (string) $till['currency']));
        $liveform->assign_field_value('currency', strtoupper(trim((string) $till['currency'])));
    }

    // Every figure on this screen is in the till's own currency.
    $till_currency = strtoupper(trim((string) $till['currency']));
    $money = function ($kurus, $show_sign = true) use ($till_currency) {
        return h(erp_fx_enabled() ? erp_money_out_currency((int) $kurus, $till_currency, $show_sign) : erp_money_out((int) $kurus, $show_sign));
    };

    // A receipt that was cancelled is still in the book, struck through, next
    // to the reversal that cancelled it; the reversal row is what says so.
    $movements = (array) db_items("SELECT c.*, a.title AS account_title,
            (SELECT r.id FROM erp_cash_transactions r WHERE r.doc_type = 'cancel' AND r.doc_id = c.id LIMIT 1) AS reversal_id
        FROM erp_cash_transactions c
        LEFT JOIN erp_accounts a ON c.account_id = a.id
        WHERE c.cash_account_id = '" . $till_id . "'
        ORDER BY c.doc_date ASC, c.id ASC");

    // Deliberately not a DataTable: a cash book is read down the page in date
    // order, and the running balance on each line only means anything in that
    // order.
    $running = (int) $till['opening_balance'];
    $output_movements = '
        <tr class="text-body-secondary" data-pg-sort-fixed="top">
            <td colspan="4">' . lang('Opening Balance') . '</td>
            <td class="text-end">' . $money($running) . '</td>
        </tr>';

    foreach ($movements as $movement) {
        $in = ((string) $movement['direction'] === 'in');
        $amount = (int) $movement['amount'];
        $running += ($in ? $amount : -$amount);

        $is_receipt = in_array((string) $movement['doc_type'], array('collection', 'payment'), true);
        $is_cancelled = ((int) $movement['reversal_id'] > 0);

        $output_description = h($movement['description']);
        if ($is_receipt) {
            $output_description = '<a href="erp_receipt.php?id=' . (int) $movement['id'] . '" class="link-body-emphasis">' . (($output_description !== '') ? $output_description : ('#' . (int) $movement['id'])) . '</a>';
        } elseif (((string) $movement['doc_type'] === 'expense') && ((int) $movement['doc_id'] > 0)) {
            $output_description = '<a href="edit_erp_expense.php?id=' . (int) $movement['doc_id'] . '" class="link-body-emphasis">' . (($output_description !== '') ? $output_description : ('#' . (int) $movement['doc_id'])) . '</a>';
        }
        if ($is_cancelled) {
            $output_description = '<span class="text-decoration-line-through text-body-secondary">' . $output_description . '</span> <span class="badge text-bg-secondary">' . lang('Cancelled') . '</span>';
        }

        $output_movements .= '
        <tr>
            <td class="align-middle text-nowrap" data-sort="' . h(str_replace('-', '', (string) $movement['doc_date'])) . '">' . h(prepare_form_data_for_output($movement['doc_date'], 'date')) . '</td>
            <td class="align-middle">' . $output_description . '</td>
            <td class="align-middle">' . h($movement['account_title']) . '</td>
            <td class="align-middle text-end ' . ($in ? 'text-success' : 'text-danger') . '" data-sort="' . ($in ? $amount : -$amount) . '">'
                . ($in ? '+' : '&minus;') . $money($amount, false) . '</td>
            <td class="align-middle text-end" data-sort="' . (int) $running . '">' . $money($running) . '</td>
        </tr>';
    }

    if (empty($movements)) {
        $output_movements .= '
        <tr data-pg-sort-fixed><td colspan="5" class="text-center text-body-secondary py-4">' . lang('There are no movements in this till yet.') . '</td></tr>';
    }

    $balance = (int) $till['balance'];

    echo
    pg_page_shell([
        'title' => lang('Edit Till'),
        'extra classes' => 'erp erp_cash',
        'icon' => 'erp',
        'heading' => h($till['name']),
        'heading_description' => lang('The till, and every movement through it.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_cash.php'),
        'breadcrumb' => array(
            array('label' => lang('Cash and Bank'), 'url' => $list_url),
            array('label' => $till['name']),
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
                    <span class="h4 mb-0 ' . (($balance < 0) ? 'text-danger' : 'text-success') . '">' . $money($balance) . '</span>
                </div>
            </div>

            <form name="form" action="edit_erp_till.php" method="post">
                ' . get_token_field() . '
                ' . $liveform->field(array('type' => 'hidden', 'name' => 'id')) . '
                ' . erp_till_form_cards($liveform, !empty($movements)) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-check-circle me-2" aria-hidden="true"></i><span class="btn-text">' . lang(array('string' => 'Save')) . '</span></button>
                            ' . (empty($movements)
                                ? '<button type="submit" id="delete_button" name="submit_delete" value="Delete" class="btn my-1 btn-outline-danger" data-loading-content="' . lang(array('string' => 'Deleting')) . '"><i class="bi bi-trash me-2" aria-hidden="true"></i><span class="btn-text">' . lang(array('string' => 'Delete')) . '</span></button>'
                                : '') . '
                        </div>
                    </div>
                </nav>
            </form>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Cash Book') . '
                </div>
                <div class="card-body p-0 position-relative table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Description') . '</th>
                                <th>' . lang('Account') . '</th>
                                <th class="text-end">' . lang('Amount') . '</th>
                                <th class="text-end">' . lang('Balance') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_movements . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>' .
    output_footer();

    $liveform->remove_form();

} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    $till_id = (int) $liveform->get_field_value('id');

    if ($liveform->get_field_value('submit_delete') == 'Delete') {

        $movement_count = (int) db_value("SELECT COUNT(*) FROM erp_cash_transactions WHERE cash_account_id = '" . $till_id . "'");

        if ($movement_count > 0) {
            $liveform->mark_error('_error', lang('This till has movements in it, so it cannot be deleted. Set it to passive instead.'));
            go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_till.php?id=' . $till_id);
        }

        db("DELETE FROM erp_cash_accounts WHERE id = '" . $till_id . "'");

        log_activity(lang(array('string' => 'erp till ({var:1}) was deleted', 'vars' => $liveform->get_field_value('name'))), $_SESSION['sessionusername']);

        $liveform->remove_form();
        $liveform_list = new liveform('erp_cash');
        $liveform_list->add_notice(lang('The till has been deleted.'));

        go(PATH . SOFTWARE_DIRECTORY . '/erp_cash.php');
    }

    $liveform->validate_required_field('name', lang(array('string' => '{var:1} is required', 'vars' => lang('Name'))));

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_till.php?id=' . $till_id);
    }

    $kinds = array('cash', 'bank', 'pos', 'credit_card');
    $kind = in_array($liveform->get_field_value('kind'), $kinds, true) ? $liveform->get_field_value('kind') : 'cash';

    // The currency may only change while nothing has moved through the till:
    // its balance is a plain sum of movements in one currency.
    $sql_currency = '';
    if (erp_fx_enabled()) {
        $stored_currency = strtoupper(trim((string) db_value("SELECT currency FROM erp_cash_accounts WHERE id = '" . $till_id . "' LIMIT 1")));
        $chosen = strtoupper(trim((string) $liveform->get_field_value('currency')));
        $has_movements = ((int) db_value("SELECT COUNT(*) FROM erp_cash_transactions WHERE cash_account_id = '" . $till_id . "'") > 0);

        if (($chosen !== '') && ($chosen !== $stored_currency)) {
            if ($has_movements) {
                $liveform->mark_error('currency', lang('Cannot be changed once there are movements.'));
            } elseif (!erp_fx_currency_allowed($chosen)) {
                $liveform->mark_error('currency', lang('That currency is not enabled for the ERP.'));
            } else {
                $sql_currency = "currency = '" . escape($chosen) . "',";
            }

            if ($liveform->check_form_errors() == true) {
                go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_till.php?id=' . $till_id);
            }
        }
    }

    erp_query("UPDATE erp_cash_accounts SET
        name = '" . escape(trim((string) $liveform->get_field_value('name'))) . "',
        kind = '" . escape($kind) . "',
        " . $sql_currency . "
        iban = '" . escape(trim((string) $liveform->get_field_value('iban'))) . "',
        bank_name = '" . escape(trim((string) $liveform->get_field_value('bank_name'))) . "',
        opening_balance = '" . erp_kurus($liveform->get_field_value('opening_balance')) . "',
        is_active = '" . (($liveform->get_field_value('is_active') === '0') ? 0 : 1) . "',
        updated_at = '" . time() . "'
        WHERE id = '" . $till_id . "'");

    erp_cash_refresh_balance($till_id);

    log_activity(lang(array('string' => 'erp till ({var:1}) was changed', 'vars' => $liveform->get_field_value('name'))), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_list = new liveform('erp_cash');
    $liveform_list->add_notice(lang('The till has been saved.'));

    go(PATH . SOFTWARE_DIRECTORY . '/erp_cash.php');
}
