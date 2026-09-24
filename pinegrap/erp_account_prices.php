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
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_account_prices');

$account_id = (int) ($_REQUEST['account_id'] ?? 0);
$account = erp_account($account_id);

if (!is_array($account)) {
    output_error(lang('The account could not be found.') . ' <a href="erp_accounts.php">' . lang('Accounts') . '</a>');
    exit();
}

$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$ready = erp_price_lists_ready();
$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
$self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_account_prices.php?account_id=' . $account_id;

if ($_POST) {
    validate_token_field();

    $action = (string) ($_POST['erp_action'] ?? 'save');

    if ($action === 'add') {
        if (erp_account_prices_add($account_id, (int) ($_POST['product_id'] ?? 0), (int) $user['id'])) {
            log_activity(lang(array('string' => 'erp price list of account ({var:1}) was changed', 'vars' => (string) $account['title'])), $_SESSION['sessionusername']);
            $liveform->add_notice(h(lang('The product is on the list at its list price; type the account\'s price or discount below.')));
        }
        go($self . '#erp_prices');
    }

    $saved_discount = erp_account_discount_save($account_id, (string) ($_POST['account_discount'] ?? ''));
    $saved = $saved_discount['success'] ? erp_account_prices_save($account_id, (array) ($_POST['terms'] ?? array()), (int) $user['id']) : $saved_discount;

    if (!$saved['success']) {
        $liveform->mark_error('_error', h($saved['error']));
        go($self);
    }

    log_activity(lang(array('string' => 'erp price list of account ({var:1}) was changed', 'vars' => (string) $account['title'])), $_SESSION['sessionusername']);
    $liveform->add_notice(h(lang('The account\'s prices were saved.')));
    go($self);
}

if (!$ready) {
    $liveform->mark_error('', lang('Account prices come with the software update; run the update to use them.'));
}

$price_out = function ($kurus) {
    return number_format(((int) $kurus) / 100, 2, erp_decimal_comma() ? ',' : '.', '');
};
$rate_out = function ($rate) {
    $mark = erp_decimal_comma() ? ',' : '.';

    return rtrim(rtrim(number_format((float) $rate, 3, $mark, ''), '0'), $mark);
};

$output_rows = '';
foreach (erp_account_price_rows($account_id) as $row) {
    $product_id = (int) $row['product_id'];
    $name = (trim((string) $row['product_name']) !== '') ? (string) $row['product_name'] : (string) $row['product_sku'];

    $output_rows .= '
        <tr>
            <td>' . h($name) . ((trim((string) $row['product_sku']) !== '' && trim((string) $row['product_sku']) !== trim($name)) ? '<div class="small text-body-secondary font-monospace">' . h((string) $row['product_sku']) . '</div>' : '')
                . (((string) $row['enabled'] !== '1') ? ' <span class="badge text-bg-secondary">' . lang('Not on sale') . '</span>' : '') . '</td>
            <td class="text-end text-nowrap" data-sort="' . (int) $row['list_price'] . '">' . h(erp_money_out((int) $row['list_price'])) . '</td>
            <td style="width:10rem"><input type="text" class="form-control form-control-sm text-end" name="terms[' . $product_id . '][price]" value="' . (((int) $row['price'] > 0) ? h($price_out($row['price'])) : '') . '" inputmode="decimal" placeholder="—" aria-label="' . h(lang('Account price')) . '"' . ($readonly ? ' disabled' : '') . ' /></td>
            <td style="width:8rem"><input type="text" class="form-control form-control-sm text-end" name="terms[' . $product_id . '][discount]" value="' . (((float) $row['discount_rate'] > 0) ? h($rate_out($row['discount_rate'])) : '') . '" inputmode="decimal" placeholder="—" aria-label="' . h(lang('Discount %')) . '"' . ($readonly ? ' disabled' : '') . ' /></td>
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
                            <input type="text" class="form-control text-end" id="account_discount" name="account_discount" value="' . (((float) ($account['discount_rate'] ?? 0) > 0) ? h($rate_out($account['discount_rate'])) : '') . '" inputmode="decimal" placeholder="—"' . (($readonly || !$ready) ? ' disabled' : '') . ' />
                        </div>
                        <div class="col-12 col-md-8 form-text">' . lang('Applied on the list price of every product that has no line of its own below. Empty is none.') . '</div>
                    </div>
                </div>

                <div class="card my-4" id="erp_prices">
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover align-middle mb-0" data-pg-sort>
                            <thead>
                                <tr>
                                    <th>' . lang('Product') . '</th>
                                    <th class="text-end">' . lang('List price') . '</th>
                                    <th>' . lang('Account price') . '</th>
                                    <th>' . lang('Discount %') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                    </div>
                    <div class="form-text px-3 pb-3">' . lang('A price replaces the list price; with no price, the discount applies to the list price. Clear both to take the product off the list. Prices are in the catalogue\'s basis, the way the list price is.') . '</div>
                </div>
                ' . ((!$readonly && $ready) ? '<nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <button type="submit" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>' . lang(array('string' => 'Save')) . '</button>
                    </div>
                </nav>' : '') . '
            </form>

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
