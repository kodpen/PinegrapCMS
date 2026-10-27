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

$output_rows = '';

foreach ($rows as $row) {
    $product_id = (int) $row['product_id'];
    $minimum = (int) $row['min_quantity'];
    $enabled = ((string) $row['enabled'] === '1');
    $cells = erp_stock_minimum_cells($row);

    $output_rows .= '
        <tr' . ($enabled ? '' : ' class="opacity-75"') . '>
            ' . ($can_order ? '<td class="align-middle" style="width:2.5rem"><input type="checkbox" class="check" form="erp_order_form" name="products[]" value="' . $product_id . '" aria-label="' . h(lang(array('string' => 'Order {var:1}', 'vars' => erp_stock_product_label($row)))) . '" data-erp-order-pick /></td>' : '') . '
            <td class="align-middle">' . h(erp_stock_product_label($row))
                . (((trim((string) $row['product_sku']) !== '') && (trim((string) $row['product_sku']) !== trim(erp_stock_product_label($row)))) ? '<div class="small fg-secondary font-monospace">' . h((string) $row['product_sku']) . '</div>' : '')
                . ($enabled ? '' : '<span class="badge theme-secondary">' . lang('Not on sale') . '</span>') . '</td>
            <td class="' . $cells['stock']['class'] . '" data-sort="' . $cells['stock']['sort'] . '" data-pg-cell="stock">' . $cells['stock']['html'] . '</td>
            <td class="' . $cells['min_quantity']['class'] . '" style="width:9rem" data-sort="' . $cells['min_quantity']['sort'] . '"' . pg_list_edit_cell($quick_edit, 'min_quantity', $product_id, ($minimum > 0) ? $minimum : '') . '>' . $cells['min_quantity']['html'] . '</td>
            <td class="align-middle text-end" style="width:7rem">
                <a class="btn btn-sm btn-outline theme-secondary" href="erp_stock.php?product_id=' . $product_id . '#erp_stock_moves" title="' . h(lang('Stock movements')) . '"><i class="bi bi-list-ul" aria-hidden="true"></i></a>
            </td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="' . ($can_order ? 5 : 4) . '" class="text-center fg-secondary py-7">'
        . (($show === 'low') ? lang('No product on sale is at or below its minimum.') : lang('No product that tracks stock matches.')) . '</td></tr>';
}

$output_show_filter = pg_filter_select('show', array(
    'all' => lang('All'),
    'set' => lang('With a minimum'),
    'low' => lang(array('string' => 'Low ({var:1})', 'vars' => $low_count)),
), $show, array('label' => lang('Show'), 'icon' => 'funnel', 'action' => 'erp_stock_minimums.php', 'keep' => array('search')));

echo
pg_page_shell([
    'title' => lang('Minimum stock'),
    'extra classes' => 'erp erp_stock',
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
        <div class="col-12 2xl:col-9">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . ($can_order ? '<form method="get" action="add_erp_purchase_order.php" id="erp_order_form" class="d-inline">
                    <button type="submit" class="btn btn-sm btn-outline theme-primary rounded-pill px-5" disabled data-erp-order-submit><i class="bi bi-cart-plus me-1" aria-hidden="true"></i>' . lang('Purchase order for the ticked') . '</button>
                </form>' : '') . '
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
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort' . pg_list_edit_table('erp_stock_minimums.php', $quick_edit) . '>
                        <thead>
                            <tr>
                                ' . ($can_order ? '<th data-pg-sort="none"><input type="checkbox" class="check" aria-label="' . h(lang('Tick all')) . '" data-erp-order-all /></th>' : '') . '
                                <th>' . lang('Product') . '</th>
                                <th class="text-end">' . lang('Stock') . '</th>
                                <th class="text-end">' . lang('Minimum') . '</th>
                                <th data-pg-sort="none"></th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
                <div class="form-text px-5 pb-5">' . h(lang(array('string' => 'Products that track stock, at most {var:1} at a time; search to narrow the list. Empty is no minimum. The stock is the store\'s own count, the one orders take from.', 'vars' => 500)))
                    . ($quick_edit ? ' ' . h(lang('Click a minimum to change it; it is saved as soon as you press Enter or leave the box.')) : '') . '</div>
            </div>
        </div>
    </div>
</main>
<script>
// The order button wakes once a product is ticked.
(function () {
    var submit = document.querySelector("[data-erp-order-submit]");
    var all = document.querySelector("[data-erp-order-all]");
    if (!submit) {
        return;
    }
    var picks = function () {
        return document.querySelectorAll("[data-erp-order-pick]");
    };
    var sync = function () {
        var ticked = 0;
        picks().forEach(function (box) {
            ticked += box.checked ? 1 : 0;
        });
        submit.disabled = (ticked === 0);
        if (all) {
            all.checked = (ticked > 0) && (ticked === picks().length);
        }
    };
    document.addEventListener("change", function (event) {
        if (event.target === all) {
            picks().forEach(function (box) {
                box.checked = all.checked;
            });
        }
        if ((event.target === all) || event.target.hasAttribute("data-erp-order-pick")) {
            sync();
        }
    });
    sync();
})();
</script>
' .
output_footer();

$liveform->remove_form();
