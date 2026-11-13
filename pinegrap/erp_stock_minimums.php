<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - minimum stock: the lowest count each product should have before it
 * is bought again, and the products at or below it now.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A minimum changed in place on the list (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON before validate_erp_access(),
// which answers with a page; these are its conditions for a change, the
// accountant's read-only right included.
if (pg_list_edit_requested()) {
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

    $edit = pg_list_edit_request(erp_stock_minimum_edit_columns(), function ($user) {
        return defined('ERP_ENABLED') && ERP_ENABLED
            && (((int) $user['role'] < 3) || !empty($user['manage_erp']))
            && !(((int) $user['role'] >= 3) && !empty($user['manage_erp_readonly']));
    });

    $saved = erp_stock_minimum_quick_save($edit['id'], $edit['value'], (int) $edit['user']['id']);

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
$liveform = new liveform('erp_stock_minimums');

$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$search = mb_substr(trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : ''), 0, 100);
$show = in_array($_GET['show'] ?? '', array('set', 'low'), true) ? (string) $_GET['show'] : 'all';

$ready = erp_stock_minimums_ready();

if (!$ready) {
    $liveform->mark_error('', lang('Minimum stock comes with the software update; run the update to use it.'));
}

$rows = $ready ? erp_stock_minimum_rows(array('search' => $search, 'show' => $show, 'limit' => 500)) : array();
$low_count = erp_stock_low_count();

// Products ticked here open a new purchase order (includes/erp/
// purchase_orders.php) with what each needs to get back to its minimum.
$can_order = !$readonly && function_exists('erp_purchase_orders_ready') && erp_purchase_orders_ready();

// The minimum changes in place, saved as soon as it is typed.
$quick_edit = (!$readonly && $ready) ? erp_stock_minimum_edit_columns() : array();

// The rows are printed: the list table driver sorts and searches them in the
// browser (browser mode). The stock and the minimum are drawn by the
// function that draws them again in the answer to an edit; the cells are
// keyed the way the answer names them.
$list_rows = array();

// Read in the status column: how many products the list holds and how many
// of them have a minimum; the low count is the site-wide one the filter shows.
$summary_products = 0;
$summary_set = 0;

foreach ($rows as $row) {
    $product_id = (int) $row['product_id'];
    $minimum = (int) $row['min_quantity'];
    $summary_products++;
    $summary_set += ($minimum > 0) ? 1 : 0;
    $enabled = ((string) $row['enabled'] === '1');
    $label = erp_stock_product_label($row);
    $sku = trim((string) $row['product_sku']);
    $cells = erp_stock_minimum_cells($row);

    $list_rows[] = array(
        'id' => $product_id,
        'class' => $enabled ? '' : 'opacity-75',
        'cells' => array(
            'product' => pg_list_cell(pg_commerce_list_open_link('erp_stock.php?product_id=' . $product_id . '#erp_stock_moves', h($label))
                . ((($sku !== '') && ($sku !== trim($label))) ? '<div class="small fg-secondary font-monospace">' . h($sku) . '</div>' : '')
                . ($enabled ? '' : '<span class="badge theme-secondary">' . lang('Not on sale') . '</span>'), 'align-middle text-wrap text-break', array('data-pg-sort-value' => $label)),
            'stock' => pg_list_cell($cells['stock']['html'], $cells['stock']['class'], array('data-pg-sort-value' => (string) $cells['stock']['sort'])),
            'min_quantity' => pg_list_cell($cells['min_quantity']['html'], $cells['min_quantity']['class'], pg_list_edit_cell_attributes($quick_edit, 'min_quantity', $product_id, ($minimum > 0) ? $minimum : '') + array('data-pg-sort-value' => (string) $cells['min_quantity']['sort'])),
        ),
    );
}

$list = pg_list_table_printed(array(
    'list' => 'erp_stock_minimums',
    'columns' => pg_commerce_list_specs(array(
        'product' => array('heading' => lang('Product'), 'kind' => 'link'),
        'stock' => array('heading' => lang('Stock'), 'kind' => 'number'),
        'min_quantity' => array('heading' => lang('Minimum'), 'kind' => 'number', 'width' => '9rem'),
    ), array('product' => 1, 'stock' => 1, 'min_quantity' => 1), $quick_edit),
    'rows' => $list_rows,
    'edit' => array('url' => 'erp_stock_minimums.php', 'columns' => $quick_edit),
));

$output_show_filter = pg_filter_select('show', array(
    'all' => lang('All'),
    'set' => lang('With a minimum'),
    'low' => lang(array('string' => 'Low ({var:1})', 'vars' => $low_count)),
), $show, array('label' => lang('Show'), 'icon' => 'funnel', 'action' => 'erp_stock_minimums.php', 'keep' => array('search')));

echo
pg_page_shell([
    'title' => lang('Minimum stock'),
    'extra classes' => 'erp erp_stock pg-measure-list',
    'icon' => 'erp',
    'heading' => lang('Minimum stock'),
    'heading_description' => lang('The lowest count each product should have before it is bought again; the ones at or below it are listed on the stock screen and the ERP dashboard.'),
    'cancel' => array('enable' => 'true', 'url' => 'erp_stock.php'),
    'breadcrumb' => array(
        array('label' => lang('Stock and cost'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_stock.php'),
        array('label' => lang('Minimum stock')),
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

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . ($can_order ? '<form method="get" action="add_erp_purchase_order.php" id="erp_order_form" class="d-none"></form>' : '') . '
                <div class="pg-toolbar-grow"></div>
                <form method="get" action="erp_stock_minimums.php" class="pg-toolbar-search disable_shortcut" role="search">
                    ' . (($show !== 'all') ? '<input type="hidden" name="show" value="' . h($show) . '" />' : '') . '
                    <label class="form-control form-control-sm form-adorn rounded-pill">
                        <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-ghost" name="search" value="' . h($search) . '" placeholder="' . h(lang('Product or code')) . '" aria-label="' . h(lang('Search')) . '" autocomplete="off" />
                    </label>
                </form>
                ' . $output_show_filter . '
            </nav>

            <div class="card my-7">
                ' . ($list_rows ? pg_list_table_html($list['source'], $list['page'], lang('Minimum stock'), $can_order ? '<button type="submit" form="erp_order_form" class="btn btn-sm btn-solid theme-primary"><i class="bi bi-cart-plus me-1" aria-hidden="true"></i>' . lang('Purchase order for the selected') . '</button>' : '')
                    : '<p class="text-center fg-secondary py-7 mb-0">' . (($show === 'low') ? lang('No product on sale is at or below its minimum.') : lang('No product that tracks stock matches.')) . '</p>') . '
                <div class="form-text px-5 pb-5">' . h(lang(array('string' => 'Products that track stock, at most {var:1} at a time; search to narrow the list. Empty is no minimum. The stock is the store\'s own count, the one orders take from.', 'vars' => 500))). '</div>
            </div>
        </div>
        <div class="col-12 lg:col-4 2xl:col-3 order-2">
            <div class="position-sticky my-7" style="top:3.3rem;">
                <div class="card">
                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Summary')) . '</div>
                    <div class="card-body">
                        <dl class="row small mb-0">
                            <dt class="col-8 fw-normal fg-secondary">' . h(lang('Products')) . '</dt>
                            <dd class="col-4 text-end mb-2">' . pg_format_number($summary_products, 0) . '</dd>
                            <dt class="col-8 fw-normal fg-secondary">' . h(lang('With a minimum')) . '</dt>
                            <dd class="col-4 text-end mb-2">' . pg_format_number($summary_set, 0) . '</dd>
                            <dt class="col-8 fw-normal fg-secondary">' . h(lang('At or below the minimum')) . '</dt>
                            <dd class="col-4 text-end mb-0' . ($low_count ? ' fg-danger fw-semibold' : '') . '">' . pg_format_number($low_count, 0) . '</dd>
                        </dl>
                        ' . ($can_order ? '<div class="form-text mt-5">' . h(lang('Tick products in the list to open a purchase order with what each needs to get back to its minimum.')) . '</div>' : '') . '
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
// The purchase order button sits in the selection bar of the list; the selected
// products go to the new order as products[] (the list is printed whole, so
// its selection is always a list of ids).
(function () {
    var root = document.querySelector(".pg-list-table[data-pg-list-table=\"erp_stock_minimums\"]");
    var form = document.getElementById("erp_order_form");
    if (!root || !form) {
        return;
    }
    root.addEventListener("pg-list-table:selection", function (event) {
        var ids = (event.detail.payload && event.detail.payload.ids) || [];
        form.textContent = "";
        ids.forEach(function (id) {
            var input = document.createElement("input");
            input.type = "hidden";
            input.name = "products[]";
            input.value = id;
            form.appendChild(input);
        });
    });
})();
</script>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
