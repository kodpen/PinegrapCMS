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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_account_prices');

// One value only: an array (?account_id[]=x) cast to 1 and opened the
// first account.
$account_id = is_scalar($_REQUEST['account_id'] ?? null) ? (int) $_REQUEST['account_id'] : 0;
$account = erp_account($account_id);

if (!is_array($account)) {
    output_error(lang('The account could not be found.') . ' <a href="erp_accounts.php">' . lang('Accounts') . '</a>', 404);
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
        // The box comes back with what was typed, marked.
        $liveform->assign_field_value('account_discount', (string) ($_POST['account_discount'] ?? ''));
        $liveform->mark_error('account_discount', h($saved['error']));
        go($self);
    }

    log_activity(lang(array('string' => 'erp price list of account ({var:1}) was changed', 'vars' => (string) $account['title'])), $_SESSION['sessionusername']);
    $liveform->add_notice(h(lang('The account\'s discount was saved.')));
    go($self);
}

if (!$ready) {
    $liveform->mark_error('', lang('Account prices come with the software update; run the update to use them.'));
}

// Purchase documents never read these terms (the invoice editor applies them
// on sales only), so a supplier's page says so instead of looking in force.
if ($ready && ((string) $account['kind'] === 'supplier')) {
    $liveform->add_warning(h(lang('This account is a supplier only. Account prices apply to sales only (invoices, quotes and the store), never to purchases.')));
}

// The products' price and discount change in place, saved as soon as they
// are typed.
$quick_edit = (!$readonly && $ready) ? erp_account_price_edit_columns() : array();

// Every product of the account's list is printed: the list table driver
// sorts and searches them in the browser (browser mode). The product and
// the terms are drawn by the function that draws them again in the answer
// to an edit; the cells are keyed the way the answer names them.
$list_rows = array();

// Read in the status column: how many products the list holds, and how many
// of them have a price or a discount of their own.
$summary_products = 0;
$summary_priced = 0;
$summary_discounted = 0;

foreach (erp_account_price_rows($account_id) as $row) {
    $product_id = (int) $row['product_id'];
    $summary_products++;
    $summary_priced += ((int) $row['price'] > 0) ? 1 : 0;
    $summary_discounted += ((float) $row['discount_rate'] > 0) ? 1 : 0;
    $cells = erp_account_price_cells($row);

    $list_rows[] = array(
        'id' => $product_id,
        'cells' => array(
            'product' => pg_list_cell($cells['product']['html'], 'align-middle text-wrap text-break'),
            'list_price' => pg_list_cell(h(erp_money_out((int) $row['list_price'])), 'align-middle text-nowrap', array('data-pg-sort-value' => (string) (int) $row['list_price'])),
            'price' => pg_list_cell($cells['price']['html'], 'align-middle ' . $cells['price']['class'], pg_list_edit_cell_attributes($quick_edit, 'price', $product_id, erp_account_price_input($row['price'])) + array('data-pg-sort-value' => (string) $cells['price']['sort'])),
            'discount' => pg_list_cell($cells['discount']['html'], 'align-middle ' . $cells['discount']['class'], pg_list_edit_cell_attributes($quick_edit, 'discount', $product_id, erp_account_rate_input($row['discount_rate'])) + array('data-pg-sort-value' => (string) $cells['discount']['sort'])),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'erp_account_prices',
    'columns' => pg_commerce_list_specs(array(
        'product' => array('heading' => lang('Product')),
        'list_price' => array('heading' => lang('List price'), 'kind' => 'money'),
        'price' => array('heading' => lang('Account price'), 'kind' => 'money', 'width' => '10rem'),
        'discount' => array('heading' => lang('Discount %'), 'kind' => 'number', 'width' => '8rem'),
    ), array('product' => 1, 'list_price' => 1, 'price' => 1, 'discount' => 1), $quick_edit),
    'rows' => $list_rows,
    'edit' => array('url' => 'erp_account_prices.php?account_id=' . $account_id, 'columns' => $quick_edit),
));

// Finding a product to add: the ERP's own product search.
$output_found = '';
if (($search !== '') && $ready && !$readonly) {
    $found = '';
    foreach (erp_product_search($search, 20, 0) as $product) {
        $found .= '
            <li class="list-group-item d-flex flex-wrap align-items-center gap-3">
                <span class="me-auto">' . h((string) $product['name']) . ' <span class="small fg-secondary font-monospace">' . h((string) $product['sku']) . '</span></span>
                <span class="small fg-secondary">' . h(erp_money_out((int) $product['price'])) . '</span>
                <form method="post" action="erp_account_prices.php" class="d-inline">' . get_token_field() . '
                    <input type="hidden" name="account_id" value="' . $account_id . '" /><input type="hidden" name="erp_action" value="add" /><input type="hidden" name="product_id" value="' . (int) $product['id'] . '" />
                    <button type="submit" class="btn btn-sm btn-outline theme-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . lang('Add') . '</button>
                </form>
            </li>';
    }
    $output_found = ($found !== '') ? '<ul class="list-group list-group-flush border-top">' . $found . '</ul>' : '<div class="card-body border-top fg-secondary">' . lang('No products match.') . '</div>';
}

// A refused discount: the box shows what was typed and is marked.
$discount_refused = $liveform->check_field_error('account_discount');

echo
pg_page_shell([
    'title' => lang('Account prices'),
    'extra classes' => 'erp erp_accounts pg-measure-list',
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
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
        </div>
        <div class="col-12 lg:col-8 2xl:col-9 order-1">
            <div class="card my-7" id="erp_prices">
                ' . ($list_rows ? pg_list_table_html($list['source'], $list['page'], lang('Account prices')) : '<p class="text-center fg-secondary py-7 mb-0">' . lang('No product has a price of its own for this account yet. Find one below and add it.') . '</p>') . '
                <div class="form-text px-5 pb-5">' . lang('A price replaces the list price; with no price, the discount applies to the list price. Clear both to take the product off the list. Prices are in the catalogue\'s basis, the way the list price is.'). '</div>
            </div>

            ' . ((!$readonly && $ready) ? '<div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Add a product') . '</div>
                <div class="card-body">
                    <form method="get" action="erp_account_prices.php" class="d-flex gap-1" role="search">
                        <input type="hidden" name="account_id" value="' . $account_id . '" />
                        <input type="search" class="form-control" name="search" value="' . h($search) . '" placeholder="' . h(lang('Product or code')) . '" aria-label="' . h(lang('Search')) . '" />
                        <button type="submit" class="btn btn-outline theme-secondary" aria-label="' . h(lang('Search')) . '"><i class="bi bi-search" aria-hidden="true"></i></button>
                    </form>
                </div>
                ' . $output_found . '
            </div>' : '') . '
        </div>
        <div class="col-12 lg:col-4 2xl:col-3 order-2">
            <div class="position-sticky my-7" style="top:3.3rem;">
                <form method="post" action="erp_account_prices.php" class="card mb-7">
                    ' . get_token_field() . '
                    <input type="hidden" name="account_id" value="' . $account_id . '" />
                    <input type="hidden" name="erp_action" value="save" />
                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Discount')) . '</div>
                    <div class="card-body">
                        <div class="form-field">
                            <label class="form-label" for="account_discount">' . lang('Discount on everything else (%)') . '</label>
                            <input type="text" class="form-control text-end' . ($discount_refused ? ' is-invalid' : '') . '" id="account_discount" name="account_discount" value="' . h($liveform->field_in_session('account_discount') ? (string) $liveform->get_field_value('account_discount') : erp_account_rate_input($account['discount_rate'] ?? 0)) . '" inputmode="decimal" placeholder="—"' . ($discount_refused ? ' aria-invalid="true"' : '') . (($readonly || !$ready) ? ' disabled' : '') . ' />
                            <div class="form-text">' . lang('Applied on the list price of every product that has no line of its own below. Empty is none.') . '</div>
                        </div>
                        ' . ((!$readonly && $ready) ? '<button type="submit" class="btn btn-solid theme-success w-100 mt-5"><i class="bi bi-check-circle me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Save')) . '</span></button>' : '') . '
                    </div>
                </form>
                <div class="card">
                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Summary')) . '</div>
                    <div class="card-body">
                        <dl class="row small mb-0">
                            <dt class="col-8 fw-normal fg-secondary">' . h(lang('Products')) . '</dt>
                            <dd class="col-4 text-end mb-2">' . pg_format_number($summary_products, 0) . '</dd>
                            <dt class="col-8 fw-normal fg-secondary">' . h(lang('Account price')) . '</dt>
                            <dd class="col-4 text-end mb-2">' . pg_format_number($summary_priced, 0) . '</dd>
                            <dt class="col-8 fw-normal fg-secondary">' . h(lang('Discount %')) . '</dt>
                            <dd class="col-4 text-end mb-0">' . pg_format_number($summary_discounted, 0) . '</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
