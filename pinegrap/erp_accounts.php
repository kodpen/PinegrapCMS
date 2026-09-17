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
include_once('liveform.class.php');
$liveform = new liveform('erp_accounts');

$accounts = erp_accounts(array());

$kind_labels = array(
    'customer' => lang('Customer'),
    'supplier' => lang('Supplier'),
    'both' => lang('Customer and supplier'),
);

$output_rows = '';

foreach ($accounts as $account) {
    $id = (int) $account['id'];
    $balance = (int) $account['balance'];

    // Positive is money owed to the shop. Saying which way round it is beats a
    // minus sign the reader has to read the sign convention off.
    $balance_class = ($balance > 0) ? 'text-success' : (($balance < 0) ? 'text-danger' : 'text-body-secondary');
    $balance_side = ($balance > 0) ? lang('owes you') : (($balance < 0) ? lang('you owe') : '');

    $status_class = ((string) $account['status'] === 'passive') ? ' text-body-secondary text-decoration-line-through' : '';

    $output_link_url = 'edit_erp_account.php?id=' . $id;

    $output_rows .=
        '<tr>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td style="max-width:220px" class="text-nowrap text-truncate align-middle chart_label' . $status_class . '">' . h($account['title']) . '</td>
            <td class="align-middle">' . h($kind_labels[$account['kind']] ?? $account['kind']) . '</td>
            <td class="align-middle text-nowrap">' . h($account['tax_number']) . '</td>
            <td class="align-middle">' . h($account['city']) . '</td>
            <td class="align-middle text-end ' . $balance_class . '" data-order="' . $balance . '">' . h(erp_money_out(abs($balance))) . '</td>
            <td class="align-middle">' . h($balance_side) . '</td>
        </tr>';
}

echo
pg_page_shell([
        'title' => lang('Accounts'),
        'extra_classes' => 'erp erp_accounts',
        'icon' => 'store',
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

            <div class="row mb-2 flex-wrap">
                <div class="col-12 text-center text-md-start">

                    <nav id="button_bar" class="navigation" aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1" href="add_erp_account.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string' => 'Create')) . '</a>
                    </nav>
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
                                <th>' . lang('VKN / TCKN') . '</th>
                                <th>' . lang('City') . '</th>
                                <th class="text-end">' . lang('Balance') . '</th>
                                <th>' . lang('Direction') . '</th>
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
