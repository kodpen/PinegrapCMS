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

// Open an account for every contact that has ordered and has none yet - the
// same reading of the card the order bridge makes, one card per contact.
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
$unlinked_contacts = (int) db_value("SELECT COUNT(DISTINCT orders.contact_id)
    FROM orders
    INNER JOIN contacts ON contacts.id = orders.contact_id
    LEFT JOIN erp_accounts ON erp_accounts.contact_id = orders.contact_id
    WHERE orders.contact_id > 0 AND erp_accounts.id IS NULL");

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

    // A foreign-currency account shows its own-currency position too; the
    // base figure stays the one the list sorts and totals on.
    $account_currency = strtoupper(trim((string) $account['currency']));
    $output_balance_fc = '';
    if (erp_fx_enabled() && ($account_currency !== erp_base_currency())) {
        $output_balance_fc = '<div class="small fw-normal">' . h(erp_money_out_currency(abs((int) $account['balance_fc']), $account_currency)) . '</div>';
    }

    $status_class = ((string) $account['status'] === 'passive') ? ' text-body-secondary text-decoration-line-through' : '';

    $output_link_url = 'edit_erp_account.php?id=' . $id;

    // The contact behind the card, when there is one: the link is where the
    // orders come from.
    $contact_id = (int) ($account['contact_id'] ?? 0);
    $output_contact = ($contact_id > 0)
        ? '<a href="edit_contact.php?id=' . $contact_id . '" class="link-body-emphasis" title="' . h(lang('Contact')) . '"><i class="bi bi-person-check"></i></a>'
        : '<span class="text-body-secondary" title="' . h(lang('No contact is linked to this account.')) . '">—</span>';

    $output_rows .=
        '<tr>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td style="max-width:220px" class="text-nowrap text-truncate align-middle chart_label' . $status_class . '">' . h($account['title']) . '</td>
            <td class="align-middle">' . h($kind_labels[$account['kind']] ?? $account['kind']) . '</td>
            <td class="align-middle text-center" data-order="' . (($contact_id > 0) ? 1 : 0) . '">' . $output_contact . '</td>
            <td class="align-middle text-nowrap">' . h($account['tax_number']) . '</td>
            <td class="align-middle">' . h($account['city']) . '</td>
            <td class="align-middle text-end ' . $balance_class . '" data-order="' . $balance . '">' . h(erp_money_out(abs($balance))) . $output_balance_fc . '</td>
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
                        <a class="btn btn-sm btn-outline-secondary m-1" href="erp_accounts_import.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-upload me-2"></i>' . lang('Import CSV') . '</a>
                        ' . (($unlinked_contacts > 0) ? '
                        <form method="post" action="erp_accounts.php" class="d-inline">
                            ' . get_token_field() . '
                            <input type="hidden" name="erp_action" value="sync_contacts" />
                            <button type="submit" class="btn btn-sm btn-outline-secondary m-1" data-confirm-content="' . h(lang(array('string' => '{var:1} contact(s) have placed orders but have no account. Open an account for each of them from their contact card?', 'vars' => $unlinked_contacts))) . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-person-plus me-2"></i>' . h(lang(array('string' => 'Open accounts for {var:1} contact(s) with orders', 'vars' => $unlinked_contacts))) . '</button>
                        </form>' : '') . '
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
                                <th class="text-center">' . lang('Contact') . '</th>
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
