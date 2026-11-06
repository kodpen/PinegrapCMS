<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - cash and bank: where the money physically is.
 *
 * This screen only lists the tills. A till is opened on add_erp_till.php and
 * read on edit_erp_till.php, which is also where its cash book is. Money is
 * moved on add_erp_receipt.php and add_erp_transfer.php.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_cash');

// The read-only accountant reads the tills and their books; nothing that
// writes is offered.
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;

$cash_accounts = (array) db_items("SELECT * FROM erp_cash_accounts
    ORDER BY is_active DESC, sort_order ASC, name ASC");

$kind_labels = array(
    'cash' => lang('Cash'),
    'bank' => lang('Bank'),
    'pos' => lang('Card terminal'),
    'credit_card' => lang('Credit card'),
);

$total = 0;

// Every till is printed: the list table driver sorts and searches them in
// the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ($cash_accounts as $till) {
    $id = (int) $till['id'];
    $balance = (int) $till['balance'];
    $till_currency = strtoupper(trim((string) $till['currency']));

    // A till's balance is in its own currency. The total across tills only
    // means something in one currency, so a till kept in another is shown but
    // not added in.
    if (((int) $till['is_active'] === 1) && (!erp_fx_enabled() || ($till_currency === erp_base_currency()))) {
        $total += $balance;
    }

    $movements = (int) db_value("SELECT COUNT(*) FROM erp_cash_transactions WHERE cash_account_id = '" . $id . "'");

    $name_class = ((int) $till['is_active'] === 1) ? '' : ' fg-secondary text-decoration-line-through';

    $list_rows[] = array(
        'id' => $id,
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_erp_till.php?id=' . $id . '" class="' . trim('pg-link-body-emphasis' . $name_class) . '" data-pg-open>' . h($till['name']) . '</a>', 'align-middle text-wrap text-break', array('data-pg-sort-value' => (string) $till['name'])),
            'kind' => pg_list_cell(h($kind_labels[$till['kind']] ?? $till['kind'])),
            'bank' => pg_list_cell(h($till['bank_name'])),
            'iban' => pg_list_cell(h($till['iban']), 'align-middle text-wrap text-break'),
            'currency' => pg_list_cell(h($till['currency'])),
            'movements' => pg_list_cell(pg_format_number($movements, 0), 'align-middle', array('data-pg-sort-value' => $movements)),
            'balance' => pg_list_cell(h(erp_fx_enabled() ? erp_money_out_currency($balance, $till_currency) : erp_money_out($balance)), 'align-middle fw-bold' . (($balance < 0) ? ' fg-danger' : ''), array('data-pg-sort-value' => $balance)),
        ),
    );
}

$columns = array(
    'name' => array('heading' => lang('Name'), 'kind' => 'link'),
    'kind' => array('heading' => lang('Type')),
    'bank' => array('heading' => lang('Bank')),
    'iban' => array('heading' => lang('IBAN')),
    'currency' => array('heading' => lang('Currency')),
    'movements' => array('heading' => lang('Movements'), 'kind' => 'number'),
    'balance' => array('heading' => lang('Balance'), 'kind' => 'money'),
);
$sort_columns = pg_list_table_printed_sort_columns($columns);
$sort_default = array('sort' => 'name', 'order' => 'asc');
$list_state = pg_list_state('erp_cash', $sort_columns, $sort_default);
$list = pg_list_table_printed(array(
    'list' => 'erp_cash',
    'columns' => $columns,
    'levels' => $list_state['sorts'],
    'rows' => $list_rows,
));

echo
pg_page_shell([
        'title' => lang('Cash and Bank'),
        'extra classes' => 'erp erp_cash',
        'icon' => 'erp',
        'heading' => lang('Cash and Bank'),
        'heading_description' => lang('Tills, bank accounts and card terminals, and what is in each of them.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                        ' . ($readonly ? '' : '<a class="btn btn-sm btn-solid theme-primary rounded-pill px-5" href="add_erp_receipt.php?direction=collection"><i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>' . lang('Record a Receipt') . '</a>
                        <a class="btn btn-sm btn-outline theme-secondary" href="add_erp_receipt.php?direction=payment"><i class="bi bi-box-arrow-up me-1" aria-hidden="true"></i>' . lang('Record a Payment') . '</a>
                        <a class="btn btn-sm btn-outline theme-secondary" href="add_erp_transfer.php"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>' . lang('Transfer') . '</a>
                        <a class="btn btn-sm btn-outline theme-secondary" href="add_erp_till.php"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>' . lang('Open a Till') . '</a>') . '
                        <a class="btn btn-sm btn-outline theme-secondary" href="erp_cashflow.php"><i class="bi bi-graph-up-arrow me-1" aria-hidden="true"></i>' . lang('Cash flow') . '</a>
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_table_search_box('erp_cash') . '
                        ' . pg_list_sort_menu('erp_cash.php', $sort_columns, $list_state['sorts'], $sort_default) . '
                    </nav>

            <div class="card my-7">
                <div class="card-body d-flex flex-wrap align-items-baseline gap-5">
                    <span class="text-uppercase fg-secondary">' . lang('Total in Active Tills') . '</span>
                    <span class="h4 mb-0 ' . (($total < 0) ? 'fg-danger' : 'fg-success') . '">' . h(erp_money_out($total)) . '</span>
                </div>
            </div>

            <div class="card my-7">
                ' . pg_list_table_html($list['source'], $list['page'], lang('Cash and Bank')) . '
            </div>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
