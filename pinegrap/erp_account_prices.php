<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one account's prices: the discount it has on everything, and the
 * products it buys at a price or a discount of its own. The invoice and quote
 * editors apply them when a product is picked for the account. The work lives
 * in includes/erp/price_lists.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A price or a discount changed in place on the list (pg_list_edit_request()
// in includes/fn/output.php). Answered in JSON before validate_erp_access(),
// which answers with a page; these are its conditions for a change, the
// accountant's read-only right included. The account comes with the address
// the table names.
if (pg_list_edit_requested()) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

    $edit = pg_list_edit_request(erp_account_price_edit_columns(), function ($user) {
        return defined('ERP_ENABLED') && ERP_ENABLED
            && (((int) $user['role'] < 3) || !empty($user['manage_erp']))
            && !(((int) $user['role'] >= 3) && !empty($user['manage_erp_readonly']));
    });

    $saved = erp_account_price_quick_save((is_scalar($_GET['account_id'] ?? null) ? (int) $_GET['account_id'] : 0), $edit['id'], $edit['field'], $edit['value'], (int) $edit['user']['id']);

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_account_prices');

// One value only: an array (?account_id[]=x) cast to 1 and opened the
// first account.
$account_id = is_scalar($_REQUEST['account_id'] ?? null) ? (int) $_REQUEST['account_id'] : 0;
$account = erp_account($account_id);

if (!is_array($account)) {
    output_error(lang('The account could not be found.') . ' <a href="erp_accounts.php">' . lang('Accounts') . '</a>');
    exit();
}

$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$ready = erp_price_lists_ready();
$search = mb_substr(trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : ''), 0, 100);
$self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_account_prices.php?account_id=' . $account_id;

if ($_POST) {
    validate_token_field();

    // Every field of the forms is one value. One posted as an array counts as
    // not given: (int) read an array product as product 1 and put it on the
    // list, (string) read the discount as "Array" with a notice.
    pg_request_single_values(array('erp_action', 'product_id', 'account_discount'));

    $action = (string) ($_POST['erp_action'] ?? 'save');

    if ($action === 'add') {
        if (erp_account_prices_add($account_id, (int) ($_POST['product_id'] ?? 0), (int) $user['id'])) {
            log_activity(lang(array('string' => 'erp price list of account ({var:1}) was changed', 'vars' => (string) $account['title'])), $_SESSION['sessionusername']);
            $liveform->add_notice(h(lang('The product is on the list at its list price; type the account\'s price or discount below.')));
        }
        go($self . '#erp_prices');
    }

    // The form only carries the account's discount on everything else; the
    // products' own terms change in place on the list.
    $saved = erp_account_discount_save($account_id, (string) ($_POST['account_discount'] ?? ''));

    if (!$saved['success']) {
        $liveform->mark_error('_error', h($saved['error']));
        go($self);
    }

    log_activity(lang(array('string' => 'erp price list of account ({var:1}) was changed', 'vars' => (string) $account['title'])), $_SESSION['sessionusername']);
    $liveform->add_notice(h(lang('The account\'s discount was saved.')));
    go($self);
}

if (!$ready) {
    $liveform->mark_error('', lang('Account prices come with the software update; run the update to use them.'));
}

// The products' price and discount change in place, saved as soon as they
// are typed.
$quick_edit = (!$readonly && $ready) ? erp_account_price_edit_columns() : array();

$output_rows = '';
foreach (erp_account_price_rows($account_id) as $row) {
    $product_id = (int) $row['product_id'];
    $cells = erp_account_price_cells($row);

    $output_rows .= '
        <tr>
            <td data-pg-cell="product">' . $cells['product']['html'] . '</td>
            <td class="text-end text-nowrap" data-sort="' . (int) $row['list_price'] . '">' . h(erp_money_out((int) $row['list_price'])) . '</td>
            <td class="' . $cells['price']['class'] . '" style="width:10rem" data-sort="' . $cells['price']['sort'] . '"' . pg_list_edit_cell($quick_edit, 'price', $product_id, erp_account_price_input($row['price'])) . '>' . $cells['price']['html'] . '</td>
            <td class="' . $cells['discount']['class'] . '" style="width:8rem" data-sort="' . $cells['discount']['sort'] . '"' . pg_list_edit_cell($quick_edit, 'discount', $product_id, erp_account_rate_input($row['discount_rate'])) . '>' . $cells['discount']['html'] . '</td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="4" class="text-center text-body-secondary py-4">' . lang('No product has a price of its own for this account yet. Find one below and add it.') . '</td></tr>';
}

// Finding a product to add: the ERP's own product search.
$output_found = '';
if (($search !== '') && $ready && !$readonly) {
    $found = '';
    foreach (erp_product_search($search, 20, 0) as $product) {
        $found .= '
            <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                <span class="me-auto">' . h((string) $product['name']) . ' <span class="small text-body-secondary font-monospace">' . h((string) $product['sku']) . '</span></span>
                <span class="small text-body-secondary">' . h(erp_money_out((int) $product['price'])) . '</span>
                <form method="post" action="erp_account_prices.php" class="d-inline">' . get_token_field() . '
                    <input type="hidden" name="account_id" value="' . $account_id . '" /><input type="hidden" name="erp_action" value="add" /><input type="hidden" name="product_id" value="' . (int) $product['id'] . '" />
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . lang('Add') . '</button>
                </form>
            </li>';
    }
    $output_found = ($found !== '') ? '<ul class="list-group list-group-flush border-top">' . $found . '</ul>' : '<div class="card-body border-top text-body-secondary">' . lang('No products match.') . '</div>';
}

echo
pg_page_shell([
    'title' => lang('Account prices'),
    'extra classes' => 'erp erp_accounts',
    'icon' => 'erp',
    'heading' => h(lang(array('string' => 'Prices for {var:1}', 'vars' => (string) $account['title']))),
    'heading_description' => lang('A discount on everything, and products at a price or a discount of their own. Invoices and quotes for this account are filled with them when a product is picked; the store\'s campaigns do not add to them.'),
    'cancel' => array('enable' => 'true', 'url' => 'edit_erp_account.php?id=' . $account_id),
    'breadcrumb' => array(
        array('label' => lang('Accounts'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_accounts.php'),
        array('label' => (string) $account['title'], 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_account.php?id=' . $account_id),
        array('label' => lang('Account prices')),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12 col-xxl-9">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form method="post" action="erp_account_prices.php">
                ' . get_token_field() . '
                <input type="hidden" name="account_id" value="' . $account_id . '" />
                <input type="hidden" name="erp_action" value="save" />
                <div class="card my-4">
                    <div class="card-body row g-2 align-items-end">
                        <div class="col-12 col-sm-6 col-md-4">
                            <label class="form-label" for="account_discount">' . lang('Discount on everything else (%)') . '</label>
                            <input type="text" class="form-control text-end" id="account_discount" name="account_discount" value="' . h(erp_account_rate_input($account['discount_rate'] ?? 0)) . '" inputmode="decimal" placeholder="—"' . (($readonly || !$ready) ? ' disabled' : '') . ' />
                        </div>
                        ' . ((!$readonly && $ready) ? '<div class="col-12 col-sm-auto">
                            <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>' . lang(array('string' => 'Save')) . '</button>
                        </div>' : '') . '
                        <div class="col-12 col-md form-text">' . lang('Applied on the list price of every product that has no line of its own below. Empty is none.') . '</div>
                    </div>
                </div>
            </form>

            <div class="card my-4" id="erp_prices">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort' . pg_list_edit_table('erp_account_prices.php?account_id=' . $account_id, $quick_edit) . '>
                        <thead>
                            <tr>
                                <th>' . lang('Product') . '</th>
                                <th class="text-end">' . lang('List price') . '</th>
                                <th class="text-end">' . lang('Account price') . '</th>
                                <th class="text-end">' . lang('Discount %') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
                <div class="form-text px-3 pb-3">' . lang('A price replaces the list price; with no price, the discount applies to the list price. Clear both to take the product off the list. Prices are in the catalogue\'s basis, the way the list price is.')
                    . ($quick_edit ? ' ' . h(lang('Click a price or a discount to change it; it is saved as soon as you press Enter or leave the box.')) : '') . '</div>
            </div>

            ' . ((!$readonly && $ready) ? '<div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Add a product') . '</div>
                <div class="card-body">
                    <form method="get" action="erp_account_prices.php" class="d-flex gap-1" role="search">
                        <input type="hidden" name="account_id" value="' . $account_id . '" />
                        <input type="search" class="form-control" name="search" value="' . h($search) . '" placeholder="' . h(lang('Product or code')) . '" aria-label="' . h(lang('Search')) . '" />
                        <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search" aria-hidden="true"></i></button>
                    </form>
                </div>
                ' . $output_found . '
            </div>' : '') . '
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
