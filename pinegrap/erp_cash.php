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
include_once('liveform.class.php');
$liveform = new liveform('erp_cash');

$cash_accounts = (array) db_items("SELECT * FROM erp_cash_accounts
    ORDER BY is_active DESC, sort_order ASC, name ASC");

$kind_labels = array(
    'cash' => lang('Cash'),
    'bank' => lang('Bank'),
    'pos' => lang('Card terminal'),
    'credit_card' => lang('Credit card'),
);

$total = 0;
$output_rows = '';

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

    $name_class = ((int) $till['is_active'] === 1) ? '' : ' text-body-secondary text-decoration-line-through';

    $output_rows .=
        '<tr>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_erp_till.php?id=' . $id . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td class="text-nowrap align-middle chart_label' . $name_class . '">' . h($till['name']) . '</td>
            <td class="align-middle">' . h($kind_labels[$till['kind']] ?? $till['kind']) . '</td>
            <td class="align-middle">' . h($till['bank_name']) . '</td>
            <td style="max-width:220px" class="text-nowrap text-truncate align-middle">' . h($till['iban']) . '</td>
            <td class="align-middle">' . h($till['currency']) . '</td>
            <td class="align-middle text-end" data-order="' . $movements . '">' . number_format($movements) . '</td>
            <td class="align-middle text-end fw-bold ' . (($balance < 0) ? 'text-danger' : '') . '" data-order="' . $balance . '">' . h(erp_fx_enabled() ? erp_money_out_currency($balance, $till_currency) : erp_money_out($balance)) . '</td>
        </tr>';
}

echo
pg_page_shell([
        'title' => lang('Cash and Bank'),
        'extra_classes' => 'erp erp_cash',
        'icon' => 'store',
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

            <div class="row mb-2 flex-wrap">
                <div class="col-12 text-center text-md-start">

                    <nav id="button_bar" class="navigation" aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1" href="add_erp_receipt.php?direction=collection" data-loading-content="' . lang(array('string' => 'Loading')) . '"><span class="bi bi-box-arrow-in-down me-2"></span>' . lang('Record a Receipt') . '</a>
                        <a class="btn btn-sm btn-outline-primary m-1" href="add_erp_receipt.php?direction=payment" data-loading-content="' . lang(array('string' => 'Loading')) . '"><span class="bi bi-box-arrow-up me-2"></span>' . lang('Record a Payment') . '</a>
                        <a class="btn btn-sm btn-outline-primary m-1" href="add_erp_transfer.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><span class="bi bi-arrow-left-right me-2"></span>' . lang('Transfer') . '</a>
                        <a class="btn btn-sm btn-outline-secondary m-1" href="add_erp_till.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><span class="bi bi-plus-circle me-2"></span>' . lang('Open a Till') . '</a>
                        <a class="btn btn-sm btn-outline-secondary m-1" href="erp_cashflow.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><span class="bi bi-graph-up-arrow me-2"></span>' . lang('Cash flow') . '</a>
                    </nav>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-body d-flex flex-wrap align-items-baseline gap-3">
                    <span class="text-uppercase text-body-secondary">' . lang('Total in Active Tills') . '</span>
                    <span class="h4 mb-0 ' . (($total < 0) ? 'text-danger' : 'text-success') . '">' . h(erp_money_out($total)) . '</span>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Name') . '</th>
                                <th>' . lang('Type') . '</th>
                                <th>' . lang('Bank') . '</th>
                                <th>' . lang('IBAN') . '</th>
                                <th>' . lang('Currency') . '</th>
                                <th class="text-end">' . lang('Movements') . '</th>
                                <th class="text-end">' . lang('Balance') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
