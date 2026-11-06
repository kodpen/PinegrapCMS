<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - accounts: who owes the shop and who the shop owes.
 *
 * One list for customers and suppliers. A party is often both, and a shop that
 * keeps two lists reconciles them by hand forever.
 *
 * This screen only lists. Creating is add_erp_account.php and editing is
 * edit_erp_account.php, which is also where an account's statement is read.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/drawer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_accounts');
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;

// Open an account for every contact that has ordered and has none yet - the
// same reading of the card the order bridge makes, one card per contact.
// The action is one value; one posted as an array is no action.
pg_request_single_values(array('erp_action'));

if ($_POST && (($_POST['erp_action'] ?? '') === 'sync_contacts')) {
    validate_token_field();

    $synced = erp_accounts_sync_contacts((int) $user['id']);

    log_activity(lang(array('string' => 'erp accounts were opened for {var:1} contact(s) with orders', 'vars' => (int) $synced['created'])), $_SESSION['sessionusername']);

    $liveform->add_notice(((int) $synced['created'] > 0)
        ? lang(array('string' => '{var:1} account(s) opened from contacts with orders.', 'vars' => (int) $synced['created']))
        : lang('Every contact with an order already has an account.'));

    if ((int) $synced['skipped'] > 0) {
        $liveform->add_notice(lang(array('string' => '{var:1} contact(s) could not be given an account: the card carries no name or company.', 'vars' => (int) $synced['skipped'])));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/erp_accounts.php');
}

$accounts = erp_accounts(array());

// Contacts that have ordered but have no account yet; the button says how many.
$unlinked_contacts = (int) db_value(erp_accounts_unlinked_contacts_sql('COUNT(DISTINCT orders.contact_id)'));

// Cards an e-document would be refused for. Counted on every load so the
// toolbar can say how many there are, and the list can be narrowed to
// them - cleaning 380 cards one failed invoice at a time is no way to
// spend an afternoon. Nothing is drawn when no provider is chosen.
$edoc_only = (($_GET['filter'] ?? '') === 'edoc');
$edoc_gaps = array();

// Accounts past their credit limit (4.98): flagged in the list, and the list
// can be narrowed to them.
$over_limit_only = (($_GET['filter'] ?? '') === 'over_limit');
$over_limit = array();

if (function_exists('erp_credit_state')) {
    foreach ($accounts as $credit_account) {
        $credit_state = erp_credit_state($credit_account);

        if (($credit_state !== null) && $credit_state['over'] && ((string) $credit_account['status'] === 'active')) {
            $over_limit[(int) $credit_account['id']] = $credit_state;
        }
    }
}

foreach ($accounts as $edoc_account) {
    if ((string) $edoc_account['status'] !== 'active') {
        continue;
    }

    $edoc_gap = erp_edoc_account_missing($edoc_account);

    if (!empty($edoc_gap['fields'])) {
        $edoc_gaps[(int) $edoc_account['id']] = $edoc_gap['fields'];
    }
}

$kind_labels = array(
    'customer' => lang('Customer'),
    'supplier' => lang('Supplier'),
    'both' => lang('Customer and supplier'),
);

// Every account is printed: the list table driver sorts and searches them
// in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

// Asked once for the whole list; each row then only checks the contact's groups.
$contacts_access = validate_contacts_access($user, true);

// Deleting a contact leaves erp_accounts.contact_id behind, so the linked ids
// are checked once for the whole list: a contact that is gone reads as none
// rather than a link to "Record not found."
$linked_contact_ids = array();
foreach ($accounts as $linked_account) {
    if ((int) ($linked_account['contact_id'] ?? 0) > 0) {
        $linked_contact_ids[(int) $linked_account['contact_id']] = true;
    }
}
$live_contacts = array();
if (!empty($linked_contact_ids)) {
    foreach ((array) db_items("SELECT id FROM contacts WHERE id IN (" . implode(',', array_keys($linked_contact_ids)) . ")") as $live_contact) {
        $live_contacts[(int) $live_contact['id']] = true;
    }
}

foreach ($accounts as $account) {
    $id = (int) $account['id'];

    if ($edoc_only && !isset($edoc_gaps[$id])) {
        continue;
    }

    if ($over_limit_only && !isset($over_limit[$id])) {
        continue;
    }

    // The details sit in visually-hidden text too: the title is a hover
    // popover only.
    $credit_text = isset($over_limit[$id])
        ? lang(array('string' => 'Credit limit {var:1}, over by {var:2}', 'vars' => array(erp_money_out($over_limit[$id]['limit']), erp_money_out($over_limit[$id]['balance'] - $over_limit[$id]['limit']))))
        : '';
    $output_credit_flag = isset($over_limit[$id])
        ? ' <span class="badge theme-danger" title="' . h($credit_text) . '">' . lang('Over limit') . '</span><span class="visually-hidden"> ' . h($credit_text) . '</span>'
        : '';

    // The gap is named in the tooltip and in visually-hidden text: "not
    // ready" without saying what is missing only moves the question one
    // click further away.
    $edoc_gap_text = isset($edoc_gaps[$id])
        ? lang(array('string' => 'Missing for the e-document: {var:1}', 'vars' => implode(', ', $edoc_gaps[$id])))
        : '';
    $output_edoc_flag = isset($edoc_gaps[$id])
        ? ' <i class="bi bi-exclamation-triangle-fill fg-warning" aria-hidden="true" title="' . h($edoc_gap_text) . '"></i><span class="visually-hidden">' . h($edoc_gap_text) . '</span>'
        : '';
    $balance = (int) $account['balance'];

    // Positive is money owed to the shop. Saying which way round it is beats a
    // minus sign the reader has to read the sign convention off.
    $balance_class = ($balance > 0) ? 'fg-success' : (($balance < 0) ? 'fg-danger' : 'fg-secondary');
    $balance_side = ($balance > 0) ? lang('owes you') : (($balance < 0) ? lang('you owe') : '');

    // A foreign-currency account shows its own-currency position too; the
    // base figure stays the one the list sorts and totals on.
    $account_currency = strtoupper(trim((string) $account['currency']));
    $output_balance_fc = '';
    if (erp_fx_enabled() && ($account_currency !== erp_base_currency())) {
        $output_balance_fc = '<div class="small fw-normal">' . h(erp_money_out_currency(abs((int) $account['balance_fc']), $account_currency)) . '</div>';
    }

    $status_class = ((string) $account['status'] === 'passive') ? ' fg-secondary text-decoration-line-through' : '';

    // The contact behind the card, when there is one: the link is where the
    // orders come from. A viewer edit_contact.php would refuse sees the mark
    // without the link.
    $contact_id = (int) ($account['contact_id'] ?? 0);
    if (!isset($live_contacts[$contact_id])) {
        $contact_id = 0;
    }
    if ($contact_id < 1) {
        $output_contact = '<span class="fg-secondary" title="' . h(lang('No contact is linked to this account.')) . '">—</span>';
    } elseif (pg_can_open_contact($user, $contact_id, $contacts_access)) {
        $output_contact = '<a href="edit_contact.php?id=' . $contact_id . '" class="pg-link-body-emphasis d-inline-flex align-items-center justify-content-center p-1" style="min-width:1.5rem;min-height:1.5rem" title="' . h(lang('Contact')) . '" aria-label="' . h(lang('Contact')) . '"><i class="bi bi-person-check" aria-hidden="true"></i></a>';
    } else {
        $output_contact = '<span class="fg-secondary d-inline-flex align-items-center justify-content-center p-1" title="' . h(lang('Contact')) . '"><i class="bi bi-person-check" aria-hidden="true"></i><span class="visually-hidden">' . h(lang('Contact')) . '</span></span>';
    }

    $list_rows[] = array(
        'id' => $id,
        'cells' => array(
            'name' => pg_list_cell('<a href="' . h('edit_erp_account.php?id=' . $id . '&send_to=' . urlencode(REQUEST_URL)) . '" class="' . trim('pg-link-body-emphasis' . $status_class) . '" data-pg-open>' . h($account['title']) . '</a>' . $output_edoc_flag, 'align-middle text-wrap text-break', array('data-pg-sort-value' => (string) $account['title'])),
            'kind' => pg_list_cell(h($kind_labels[$account['kind']] ?? $account['kind'])),
            'contact' => pg_list_cell($output_contact, 'align-middle', array('data-pg-sort-value' => ($contact_id > 0) ? 1 : 0)),
            'tax_number' => pg_list_cell(h($account['tax_number']), 'align-middle text-nowrap'),
            'city' => pg_list_cell(h($account['city'])),
            'balance' => pg_list_cell(h(erp_money_out(abs($balance))) . $output_balance_fc, 'align-middle ' . $balance_class, array('data-pg-sort-value' => $balance)),
            'direction' => pg_list_cell(h($balance_side) . $output_credit_flag),
            'actions' => pg_list_cell(erp_drawer_button($id)),
        ),
    );
}

$columns = array(
    'name' => array('heading' => lang('Name'), 'kind' => 'link'),
    'kind' => array('heading' => lang('Type')),
    'contact' => array('heading' => lang('Contact'), 'kind' => 'check'),
    'tax_number' => array('heading' => erp_tax_id_label()),
    'city' => array('heading' => lang('City')),
    'balance' => array('heading' => lang('Balance'), 'kind' => 'money'),
    'direction' => array('heading' => lang('Direction')),
    'actions' => array('heading' => lang('Action'), 'kind' => 'actions'),
);
$sort_columns = pg_list_table_printed_sort_columns($columns);
$sort_default = array('sort' => 'name', 'order' => 'asc');
$list_state = pg_list_state('erp_accounts', $sort_columns, $sort_default);
$list = pg_list_table_printed(array(
    'list' => 'erp_accounts',
    'columns' => $columns,
    'levels' => $list_state['sorts'],
    'rows' => $list_rows,
));

// The narrowing switches only show while there is something to narrow to,
// or while one of them is on; one filter, each choice with how many cards
// it holds.
$show_edoc = (!empty($edoc_gaps) || $edoc_only);
$show_over_limit = (!empty($over_limit) || $over_limit_only);
$output_filter = '';

if ($show_edoc || $show_over_limit) {
    $account_filter_options = array('' => lang('All'));
    if ($show_edoc) {
        $account_filter_options['edoc'] = lang('Not ready for e-documents') . ' (' . count($edoc_gaps) . ')';
    }
    if ($show_over_limit) {
        $account_filter_options['over_limit'] = lang('Over their credit limit') . ' (' . count($over_limit) . ')';
    }
    $output_filter = pg_filter_select('filter', $account_filter_options, $edoc_only ? 'edoc' : ($over_limit_only ? 'over_limit' : ''),
        array('label' => lang('Show'), 'icon' => 'funnel', 'action' => 'erp_accounts.php'));
}

echo
pg_page_shell([
        'title' => lang('Accounts'),
        'extra classes' => 'erp erp_accounts',
        'icon' => 'erp',
        'heading' => lang('Accounts'),
        'heading_description' => lang('Customers and suppliers, their balances and their statements.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . (!$readonly ? '<a class="btn btn-sm btn-solid theme-primary rounded-pill px-5" href="add_erp_account.php"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>' . lang(array('string' => 'Create')) . '</a>' : '') . '
                ' . (!$readonly ? '<a class="btn btn-sm btn-outline theme-secondary" href="erp_accounts_import.php"><i class="bi bi-upload me-1"></i>' . lang('Import CSV') . '</a>' : '') . '
                ' . ((!$readonly && ($unlinked_contacts > 0)) ? '
                <form method="post" action="erp_accounts.php" class="d-inline">
                    ' . get_token_field() . '
                    <input type="hidden" name="erp_action" value="sync_contacts" />
                    <button type="submit" class="btn btn-sm btn-outline theme-secondary" data-confirm-content="' . h(lang(array('string' => '{var:1} contact(s) have placed orders but have no account. Open an account for each of them from their contact card?', 'vars' => $unlinked_contacts))) . '"><i class="bi bi-person-plus me-1"></i>' . h(lang(array('string' => 'Open accounts for {var:1} contact(s) with orders', 'vars' => $unlinked_contacts))) . '</button>
                </form>' : '') . '
                <div class="pg-toolbar-grow"></div>
                ' . pg_list_table_search_box('erp_accounts') . '
                ' . pg_list_sort_menu('erp_accounts.php', $sort_columns, $list_state['sorts'], $sort_default, ($edoc_only || $over_limit_only) ? array('filter' => $edoc_only ? 'edoc' : 'over_limit') : array()) . '
                ' . $output_filter . '
            </nav>
            <div class="card my-7">
                ' . pg_list_table_html($list['source'], $list['page'], lang('Accounts')) . '
            </div>
        </div>
    </div>
</main>
' . erp_drawer_markup('account') . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
