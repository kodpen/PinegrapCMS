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

// The filtered list the account was opened from: the back arrow, the
// breadcrumb, Save and every reload of this screen keep it.
$send_to = pg_send_to_url('');
$send_to_query = ($send_to !== '') ? '&send_to=' . urlencode($send_to) : '';
$self_action = h('edit_erp_account.php' . (($send_to !== '') ? '?send_to=' . urlencode($send_to) : ''));

// If the form has not been submitted yet, then show it.
if (!$_POST) {

    // One number: an array (?id[]=x) cast to 1 and opened the first account.
    $account_id = is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0;
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
        $liveform->assign_field_value('state', (string) ($account['state'] ?? ''));
        $liveform->assign_field_value('postcode', $account['postcode']);
        $liveform->assign_field_value('country_code', strtoupper(trim((string) $account['country_code'])));
        $liveform->assign_field_value('notes', $account['notes']);
        $liveform->assign_field_value('payment_days', (string) (int) ($account['payment_days'] ?? 0));
        $liveform->assign_field_value('overdue_notify_days', (string) (int) ($account['overdue_notify_days'] ?? 0));
        // On until the column arrives, which is also what the upgrade writes.
        $liveform->assign_field_value('overdue_notify_customer', ((int) ($account['overdue_notify_customer'] ?? 1) === 1) ? '1' : '');
        $liveform->assign_field_value('invoice_email', (string) ($account['invoice_email'] ?? ''));
        $liveform->assign_field_value('invoice_mail', ((int) ($account['invoice_mail'] ?? 1) === 1) ? '1' : '');
        $liveform->assign_field_value('credit_limit', function_exists('erp_credit_input_value') ? erp_credit_input_value((int) ($account['credit_limit'] ?? 0)) : '');
        $liveform->assign_field_value('currency', strtoupper(trim((string) $account['currency'])));
        $liveform->assign_field_value('contact_id', (string) (int) $account['contact_id']);
    }

    // The contact the form is carrying: the linked one on a fresh visit, the
    // one just picked when a save came back refused.
    $linked_contact = ((int) $liveform->get_field_value('contact_id') > 0) ? erp_contact_summary((int) $liveform->get_field_value('contact_id')) : null;

    $account_currency = strtoupper(trim((string) $account['currency']));
    $has_movements = ((int) db_value("SELECT COUNT(*) FROM erp_account_transactions WHERE account_id = '" . $account_id . "'") > 0);

    // The own-currency position beside the base one, for an account kept in
    // another currency.
    $output_balance_fc = '';
    if (erp_fx_enabled() && ($account_currency !== erp_base_currency())) {
        $balance_fc = (int) $account['balance_fc'];
        $output_balance_fc = '<span class="fg-secondary">' . h(erp_money_out_currency(abs($balance_fc), $account_currency)) . ' ' . h($account_currency) . '</span>';
    }

    // The account's card at the e-document provider, when the provider hands
    // its cards over: linked and the same, linked and different, or none yet.
    $output_card_sync = '';
    $card_provider = function_exists('erp_edoc_accounts_provider') ? erp_edoc_accounts_provider() : '';

    if ($card_provider !== '') {
        $card_label = (string) erp_edoc_info($card_provider)['label'];
        $card_row = erp_edoc_account_card_of($card_provider, $account_id);

        if ($card_row !== null) {
            $card_diff = erp_edoc_account_diff(erp_edoc_account_erp_card($account), erp_edoc_account_stored_card($card_row));
            $output_card_sync = '<a class="btn btn-sm btn-outline theme-secondary m-1" href="erp_account_sync_item.php?id=' . (int) $card_row['id'] . '"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>'
                . h(empty($card_diff)
                    ? lang(array('string' => 'Card at {var:1}: the same', 'vars' => $card_label))
                    : lang(array('string' => 'Card at {var:1}: {var:2} difference(s)', 'vars' => array($card_label, count($card_diff)))))
                . '</a>';
        } else {
            $output_card_sync = '<a class="btn btn-sm btn-outline theme-secondary m-1" href="erp_account_sync_item.php?account_id=' . $account_id . '"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'No card at {var:1}', 'vars' => $card_label))) . '</a>';
        }
    }

    $balance = (int) $account['balance'];
    $balance_class = ($balance > 0) ? 'fg-success' : (($balance < 0) ? 'fg-danger' : 'fg-secondary');
    // Saying which way round it is beats a minus sign the reader has to read
    // the sign convention off.
    $balance_side = ($balance > 0) ? lang('owes you') : (($balance < 0) ? lang('you owe') : '');

    // The credit limit beside the balance: what is left of it, or by how
    // much the account is over.
    $output_credit = '';
    $credit_state = function_exists('erp_credit_state') ? erp_credit_state($account) : null;

    if ($credit_state !== null) {
        $output_credit = '<span class="lg:ms-auto small ' . ($credit_state['over'] ? 'fg-danger fw-bold' : 'fg-secondary') . '">'
            . '<i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>'
            . h($credit_state['over']
                ? lang(array('string' => 'Credit limit {var:1}, over by {var:2}', 'vars' => array(erp_money_out($credit_state['limit']), erp_money_out($credit_state['balance'] - $credit_state['limit']))))
                : lang(array('string' => 'Credit limit {var:1}, {var:2} left', 'vars' => array(erp_money_out($credit_state['limit']), erp_money_out($credit_state['available'])))))
            . '</span>';
    }

    // What the account has run up in late payment fees this year, with the
    // button that writes it on an invoice draft (includes/erp/late_fee.php).
    $output_late_fee = function_exists('erp_late_fee_panel') ? erp_late_fee_panel($account) : '';

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
            ? ' <span class="fg-secondary small">' . h(erp_money_out_currency((int) $row['amount'], $row_currency, false)) . '</span>'
            : '';

        // A receipt or payment opens on its own screen, where it can be
        // allocated or cancelled.
        $output_description = h($row['description']);
        if (in_array((string) $row['doc_type'], array('collection', 'payment'), true) && ((int) $row['doc_id'] > 0)) {
            $output_description = '<a href="erp_receipt.php?id=' . (int) $row['doc_id'] . '" class="pg-link-body-emphasis">' . (($output_description !== '') ? $output_description : ('#' . (int) $row['doc_id'])) . '</a>';
        }

        $output_statement_rows .= '
            <tr>
                <td class="align-middle text-nowrap" data-sort="' . h(str_replace('-', '', (string) $row['doc_date'])) . '">' . h(prepare_form_data_for_output($row['doc_date'], 'date')) . '</td>
                <td class="align-middle">' . $output_description . '</td>
                <td class="align-middle text-end"' . ($is_debit ? ' data-sort="' . (int) $row['amount_base'] . '"' : '') . '>' . ($is_debit ? h(erp_money_out((int) $row['amount_base'], false)) . $output_fc : '') . '</td>
                <td class="align-middle text-end"' . ($is_debit ? '' : ' data-sort="' . (int) $row['amount_base'] . '"') . '>' . ($is_debit ? '' : h(erp_money_out((int) $row['amount_base'], false)) . $output_fc) . '</td>
                <td class="align-middle text-end" data-sort="' . (int) $row['running_balance'] . '">' . h(erp_money_out((int) $row['running_balance'])) . '</td>
            </tr>';
    }

    if ($output_statement_rows === '') {
        $output_statement_rows = '
            <tr data-pg-sort-fixed><td colspan="5" class="text-center fg-secondary py-7">' . lang('There are no movements on this account yet.') . '</td></tr>';
    }

    // Where it was talked about in the workspace, and the tasks about it.
    $output_workspace_button = '';

    if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
        $output_workspace_button = ws_record_button($user, 'erp_account', (int) $account_id, (string) $account['title']);
    }

    // A sales quote is written to a customer, so a pure supplier gets no quote links.
    $show_quotes = function_exists('erp_quotes_ready') && erp_quotes_ready() && ((string) $account['kind'] !== 'supplier');

    // A purchase order goes to a supplier, so a pure customer gets no order links.
    $show_purchase_orders = function_exists('erp_purchase_orders_ready') && erp_purchase_orders_ready() && ((string) $account['kind'] !== 'customer');

    echo
    pg_page_shell([
        'title' => lang('Edit Account'),
        'extra classes' => 'erp erp_accounts',
        'icon' => 'erp',
        'heading' => h($account['title']),
        'heading_description' => lang('The account details, and every movement behind its balance.'),
        'cancel' => array('enable' => 'true', 'url' => pg_send_to_url('erp_accounts.php')),
        'breadcrumb' => array(
            array('label' => lang('Accounts'), 'url' => pg_send_to_list_url($list_url)),
            array('label' => $account['title']),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                        <a class="btn btn-sm btn-outline theme-secondary" href="erp_reconciliation.php?id=' . $account_id . '"><i class="bi bi-envelope-paper me-1"></i>' . lang('Reconciliation Letter') . '</a>
                        ' . ((function_exists('erp_price_lists_ready') && erp_price_lists_ready()) ? '<a class="btn btn-sm btn-outline theme-secondary" href="erp_account_prices.php?account_id=' . $account_id . '"><i class="bi bi-tags me-1" aria-hidden="true"></i>' . lang('Account prices') . (((($price_count = erp_account_price_count($account_id)) > 0) || ((float) ($account['discount_rate'] ?? 0) > 0)) ? ' <span class="badge theme-secondary">' . (($price_count > 0) ? (int) $price_count : h(erp_percent_text($account['discount_rate']))) . '</span>' : '') . '</a>' : '') . '
                        <a class="btn btn-sm btn-outline theme-secondary" href="erp_invoices.php?filter=open&amp;account_id=' . $account_id . '"><i class="bi bi-receipt me-1" aria-hidden="true"></i>' . lang('Open invoices') . '</a>
                        ' . (!(defined('USER_ERP_READONLY') && USER_ERP_READONLY) ? '<a class="btn btn-sm btn-outline theme-secondary" href="add_erp_manual_invoice.php?account_id=' . $account_id . (((string) $account['kind'] === 'supplier') ? '&amp;direction=purchase' : '') . '"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . lang('New Invoice') . '</a>' : '') . '
                        ' . ($show_quotes ? '<a class="btn btn-sm btn-outline theme-secondary" href="erp_quotes.php?account_id=' . $account_id . '"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>' . lang('Quotes') . '</a>' : '') . '
                        ' . (($show_quotes && !(defined('USER_ERP_READONLY') && USER_ERP_READONLY)) ? '<a class="btn btn-sm btn-outline theme-secondary" href="add_erp_quote.php?account_id=' . $account_id . '"><i class="bi bi-file-earmark-plus me-1" aria-hidden="true"></i>' . lang('New quote') . '</a>' : '') . '
                        ' . ($show_purchase_orders ? '<a class="btn btn-sm btn-outline theme-secondary" href="erp_purchase_orders.php?account_id=' . $account_id . '"><i class="bi bi-cart-check me-1" aria-hidden="true"></i>' . lang('Purchase orders') . '</a>' : '') . '
                        ' . (($show_purchase_orders && !(defined('USER_ERP_READONLY') && USER_ERP_READONLY)) ? '<a class="btn btn-sm btn-outline theme-secondary" href="add_erp_purchase_order.php?account_id=' . $account_id . '"><i class="bi bi-cart-plus me-1" aria-hidden="true"></i>' . lang('New purchase order') . '</a>' : '') . '
                        ' . $output_card_sync . '
                        ' . $output_workspace_button . '
                    </nav>

            <div class="card my-7">
                <div class="card-body d-flex flex-wrap align-items-baseline gap-5">
                    <span class="text-uppercase fg-secondary">' . lang('Balance') . '</span>
                    <span class="h4 mb-0 ' . $balance_class . '">' . h(erp_money_out(abs($balance))) . '</span>
                    <span class="fg-secondary">' . h($balance_side) . '</span>
                    ' . $output_balance_fc . '
                    ' . $output_credit . '
                </div>
                ' . (($output_late_fee !== '') ? '<div class="card-footer bg-reset">' . $output_late_fee . '</div>' : '') . '
            </div>

            <form name="form" id="erp_account_form" action="' . $self_action . '" method="post">
                ' . get_token_field() . '
                ' . $liveform->field(array('type' => 'hidden', 'name' => 'id')) . '
                ' . erp_account_form_cards($liveform, false, $has_movements, $linked_contact, $account) . '
                <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><i class="bi bi-check-circle me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang(array('string' => 'Save')) . '</span></button>
                        </div>
                    </div>
                </nav>
            </form>

            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                    ' . lang('Statement') . '
                </div>
                <div class="card-body p-0 position-relative table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
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

    // Every field of the form is one value. One posted as an array counts as
    // not given: (int) read an array id as 1 and saved over that account,
    // (string) wrote "Array" onto the card with a notice.
    pg_request_single_values(array_merge(erp_account_form_single_fields(), array('id')));

    $liveform->add_fields_to_session();

    $account_id = (int) $liveform->get_field_value('id');

    // This screen only edits. Without an existing account behind the id the
    // save would fall through to an insert and create a record nobody asked for.
    $account = ($account_id > 0) ? erp_account($account_id) : null;

    if (($account_id <= 0) || ($account === null)) {
        $liveform->remove_form();
        $liveform_list = new liveform('erp_accounts');
        $liveform_list->mark_error('_error', lang('The account could not be found.'));
        go(pg_send_to_url(PATH . SOFTWARE_DIRECTORY . '/erp_accounts.php'));
    }

    $liveform->validate_required_field('title', lang(array('string' => '{var:1} is required', 'vars' => lang('Name'))));
    $country_code = erp_account_form_country($liveform);
    erp_account_form_check_formats($liveform, $country_code);
    // Before the contact relink below, which writes.
    erp_account_form_check_tax_number_taken($liveform, $country_code, $account_id, (string) $account['tax_number']);

    list($payment_days, $overdue_notify_days) = erp_account_form_check_days($liveform);

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

    // The contact link is checked before anything is written: a contact that
    // belongs to another card refuses the whole save, with the reason on the
    // field, rather than saving the card and dropping the link quietly.
    $contact_id = max(0, (int) $liveform->get_field_value('contact_id'));

    if (($contact_id !== (int) $account['contact_id']) && ($liveform->check_form_errors() == false)) {
        $linked = erp_account_link_contact($account_id, $contact_id);

        if (!$linked['success']) {
            $liveform->mark_error('contact_id', h($linked['error']));
        }
    }

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_account.php?id=' . $account_id . $send_to_query);
    }

    // An empty country choice is saved as empty: the store's country applies.
    $result = erp_account_save(array('id' => $account_id) + erp_account_form_save_fields($liveform, $country_code, $currency, $contact_id,
        $payment_days, $overdue_notify_days, '') + array('created_by' => (int) $user['id']));

    if (!$result['success']) {
        $liveform->mark_error('_error', $result['error']);
        go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_account.php?id=' . $account_id . $send_to_query);
    }

    log_activity(lang(array('string' => 'erp account ({var:1}) was changed', 'vars' => $liveform->get_field_value('title'))), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_list = new liveform('erp_accounts');
    $liveform_list->add_notice(lang('The account has been saved.'));

    go(pg_send_to_url(PATH . SOFTWARE_DIRECTORY . '/erp_accounts.php'));
}
