<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - Stock Planning: per stock-tracked product the plan worked out every
 * night (includes/erp/stock_plan.php): units a day, days left, the run-out
 * and order-by days, the quantity to order, its state and dead stock. Per
 * supplier, New purchase order opens the purchase order form filled with the
 * plan's quantities; nothing is saved until the form is.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/stock_plan.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_stock_plan');

$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$filters = erp_stock_plan_filters($_GET);
$states = erp_stock_plan_state_labels();
$settings = erp_stock_plan_settings();
$today = erp_stock_plan_day_of(time());
$ready = erp_stock_plan_cache_ready();

if (!$ready) {
    $liveform->mark_error('', lang('Stock Planning comes with the software update; run the update to use it.'));
}

$marks = $ready ? erp_stock_plan_marks() : false;
$marks = is_array($marks) ? $marks : array('began' => '', 'fill' => '', 'filled' => false, 'day' => '', 'planned' => '');
$history_from = erp_stock_plan_history_from($marks, $today);
$all_rows = $ready ? erp_stock_plan_screen_rows($today, $history_from, $settings['dead']) : array();

$can_order = !$readonly && function_exists('erp_purchase_orders_ready') && erp_purchase_orders_ready();

// The settings card: in Site Settings below role 3, on ERP Settings for a
// role 3 user with the settings right; the read-only accountant has none.
$settings_url = '';
if (USER_MANAGE_ERP_SETTINGS && !$readonly) {
    $settings_url = ((int) $user['role'] < 3) ? pg_settings_link('accounting', 'pgset-stock-plan') : 'erp_settings.php#erp_stock_plan';
}

$date_out = function ($day) {
    return (($day === null) || ((string) $day === '')) ? '<span class="fg-secondary">—</span>' : h(prepare_form_data_for_output((string) $day, 'date'));
};

$state_tones = array('runs_out' => 'danger', 'order_now' => 'warning', 'soon' => 'info', 'fine' => 'success', 'no_history' => 'secondary');

// Counts and the dead stock's value over every planned product; the list
// below shows the ones the filters pass. Suppliers for the filter are the
// ones the plan names.
$counts = array_fill_keys(array_keys($states), 0);
$dead_value = 0;
$dead_unknown = 0;
$suppliers = array();
$list_rows = array();
$shown = array();

foreach ($all_rows as $row) {
    $counts[$row['state']] = ($counts[$row['state']] ?? 0) + 1;

    if ($row['dead']) {
        $counts['dead']++;
        if ($row['dead_value'] === null) {
            $dead_unknown++;
        } else {
            $dead_value += (int) $row['dead_value'];
        }
    }

    if ((int) $row['supplier_id'] > 0) {
        $suppliers[(int) $row['supplier_id']] = (string) $row['supplier_name'];
    }

    if (!erp_stock_plan_row_matches($row, $filters)) {
        continue;
    }

    $shown[] = $row;
    $product_id = (int) $row['product_id'];
    $label = erp_stock_product_label($row);
    $sku = trim((string) $row['product_sku']);
    $planned = ($row['state'] !== 'no_history');
    $units_day = (float) $row['units_day'];
    $season = (float) $row['season'];
    $lead = ($row['lead_days'] === null) ? null : (int) $row['lead_days'];

    $state_html = pg_pill($states[$row['state']] ?? $row['state'], $state_tones[$row['state']] ?? 'secondary');
    if ($row['dead']) {
        $state_html .= ' ' . pg_pill(lang('Dead stock'), 'secondary')
            . '<div class="pg-text-meta">' . (($row['dead_value'] === null) ? h(lang('Cost not known')) : h(erp_money_out((int) $row['dead_value']))) . '</div>';
    }

    $lead_html = ($lead === null) ? '<span class="fg-secondary">—</span>'
        : h(lang(array('string' => '{var:1} days', 'vars' => $lead)))
            . '<div class="pg-text-meta">' . h(($row['lead_source'] === 'measured') ? lang('Measured') : lang('From the account card')) . '</div>';

    $list_rows[] = array(
        'id' => $product_id,
        'cells' => array(
            'product' => pg_list_cell(pg_commerce_list_open_link('erp_stock.php?product_id=' . $product_id . '#erp_stock_moves', h($label))
                . ((($sku !== '') && ($sku !== trim($label))) ? '<div class="pg-text-meta font-monospace">' . h($sku) . '</div>' : ''), 'align-middle text-wrap text-break', array('data-pg-sort-value' => $label)),
            'stock' => pg_list_cell(pg_format_number((int) $row['inventory_quantity'], 0), 'align-middle text-end', array('data-pg-sort-value' => (string) (int) $row['inventory_quantity'])),
            'units_day' => pg_list_cell($planned ? h(pg_format_number($units_day, 2))
                . ((abs($season - 1) > 0.001) ? '<div class="pg-text-meta">' . h(lang(array('string' => 'Season ×{var:1}', 'vars' => pg_format_number($season, 2)))) . '</div>' : '')
                : '<span class="fg-secondary">—</span>', 'align-middle text-end', array('data-pg-sort-value' => (string) $units_day)),
            'days_left' => pg_list_cell(($row['days_left'] === null) ? '<span class="fg-secondary">—</span>' : pg_format_number((int) $row['days_left'], 0), 'align-middle text-end', array('data-pg-sort-value' => ($row['days_left'] === null) ? '999999' : (string) (int) $row['days_left'])),
            'runout_day' => pg_list_cell($date_out($row['runout_day']), 'align-middle text-nowrap', array('data-pg-sort-value' => (string) $row['runout_day'])),
            'order_by_day' => pg_list_cell($date_out($row['order_by_day']), 'align-middle text-nowrap', array('data-pg-sort-value' => (string) $row['order_by_day'])),
            'quantity' => pg_list_cell(((int) $row['quantity'] > 0) ? '<span class="fw-semibold">' . pg_format_number((int) $row['quantity'], 0) . '</span>' : '<span class="fg-secondary">0</span>', 'align-middle text-end', array('data-pg-sort-value' => (string) (int) $row['quantity'])),
            'state' => pg_list_cell($state_html, 'align-middle', array('data-pg-sort-value' => (string) array_search($row['state'], array_keys($states), true))),
            'supplier' => pg_list_cell(((int) $row['supplier_id'] > 0) ? h((string) $row['supplier_name']) : '<span class="fg-secondary">' . h(lang('No supplier yet')) . '</span>', 'align-middle text-wrap text-break', array('data-pg-sort-value' => (string) $row['supplier_name'])),
            'lead' => pg_list_cell($lead_html, 'align-middle', array('data-pg-sort-value' => ($lead === null) ? '999999' : (string) $lead)),
        ),
    );
}

asort($suppliers, SORT_NATURAL | SORT_FLAG_CASE);

$list = pg_list_table_printed(array(
    'list' => 'erp_stock_plan',
    'columns' => pg_commerce_list_specs(array(
        'product' => array('heading' => lang('Product'), 'kind' => 'link'),
        'stock' => array('heading' => lang('Stock'), 'kind' => 'number'),
        'units_day' => array('heading' => lang('Units a day'), 'kind' => 'number'),
        'days_left' => array('heading' => lang('Days left'), 'kind' => 'number'),
        'runout_day' => array('heading' => lang('Runs out'), 'kind' => 'text'),
        'order_by_day' => array('heading' => lang('Order by'), 'kind' => 'text'),
        'quantity' => array('heading' => lang('To order'), 'kind' => 'number'),
        'state' => array('heading' => lang('State'), 'kind' => 'text'),
        'supplier' => array('heading' => lang('Supplier'), 'kind' => 'text'),
        'lead' => array('heading' => lang('Lead time'), 'kind' => 'text'),
    ), array_fill_keys(array('product', 'stock', 'units_day', 'days_left', 'runout_day', 'order_by_day', 'quantity', 'state', 'supplier', 'lead'), 1), array()),
    'rows' => $list_rows,
));

$state_options = array('all' => lang(array('string' => 'All ({var:1})', 'vars' => count($all_rows))));
foreach ($states as $key => $state_label) {
    $state_options[$key] = lang(array('string' => '{var:1} ({var:2})', 'vars' => array($state_label, $counts[$key])));
}

$supplier_options = array('0' => lang('All suppliers'));
foreach ($suppliers as $supplier_id => $supplier_name) {
    $supplier_options[(string) $supplier_id] = $supplier_name;
}

$output_toolbar = pg_screen_toolbar(array(
    'secondary' => array('label' => lang('Minimum stock'), 'url' => 'erp_stock_minimums.php', 'icon' => 'bi-sliders'),
    'grow' => ($marks['began'] !== '') ? '<span class="pg-text-meta">' . h(lang(array('string' => 'Daily record since {var:1}', 'vars' => prepare_form_data_for_output($marks['began'], 'date')))) . '</span>' : '',
    'search' => array(
        pg_filter_select('state', $state_options, $filters['state'], array('label' => lang('State'), 'icon' => 'funnel', 'action' => 'erp_stock_plan.php', 'keep' => array('supplier'))),
        (count($supplier_options) > 1) ? pg_filter_select('supplier', $supplier_options, (string) $filters['supplier'], array('label' => lang('Supplier'), 'icon' => 'truck', 'action' => 'erp_stock_plan.php', 'keep' => array('state'))) : '',
    ),
));

// One New purchase order per supplier, from the products the list shows:
// a post that only fills the form (add_erp_purchase_order.php).
$output_orders = '';
if ($can_order) {
    foreach (erp_stock_plan_supplier_orders($shown) as $supplier_id => $order) {
        $inputs = '';
        foreach ($order['quantities'] as $product_id => $quantity) {
            $inputs .= '<input type="hidden" name="quantities[' . (int) $product_id . ']" value="' . (int) $quantity . '" />';
        }

        $output_orders .= '
                        <form method="post" action="add_erp_purchase_order.php" class="d-flex align-items-center gap-3 py-3 border-bottom">
                            ' . get_token_field() . '
                            <input type="hidden" name="plan_fill" value="1" />
                            <input type="hidden" name="account_id" value="' . (int) $supplier_id . '" />
                            ' . $inputs . '
                            <div class="flex-grow-1 text-break">' . h($order['name']) . '<div class="pg-text-meta">' . h(lang(array('string' => '{var:1} products', 'vars' => count($order['quantities'])))) . '</div></div>
                            <button type="submit" class="pg-button pg-button-ghost"><i class="bi bi-cart-plus" aria-hidden="true"></i>' . h(lang('New purchase order')) . '</button>
                        </form>';
    }
}

$empty_text = empty($all_rows)
    ? (($marks['planned'] === '') ? lang('The plan is worked out every night once the daily record has begun; come back tomorrow.') : lang('No product that tracks stock is planned yet.'))
    : lang('No product matches the filters.');

echo
pg_page_shell([
    'title' => lang('Stock Planning'),
    'extra classes' => 'erp erp_stock pg-measure-list',
    'icon' => 'erp',
    'heading' => lang('Stock Planning'),
    'heading_description' => lang('How long each product lasts at the speed it sells, when to order it and how many, worked out every night from sales on every channel.'),
    'cancel' => array('enable' => 'true', 'url' => 'erp_stock.php'),
    'breadcrumb' => array(
        array('label' => lang('Stock and cost'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_stock.php'),
        array('label' => lang('Stock Planning')),
    ),
]) . '
<main id="content" class="container-fluid">
    ' . $output_toolbar . '
    ' . $liveform->output_errors() . '
    ' . $liveform->get_warnings() . '
    ' . $liveform->output_notices() . '
    <div class="row">
        <div class="col-12 lg:col-8 2xl:col-9 order-1">
            <div class="card my-7">
                ' . ($list_rows ? pg_list_table_html($list['source'], $list['page'], lang('Stock Planning'), '')
                    : '<p class="text-center fg-secondary py-7 mb-0">' . h($empty_text) . '</p>') . '
                <div class="form-text px-5 pb-5">' . h(lang('To order covers the lead time, the safety days and the cover days, less the stock and what open purchase orders still bring. A product without a lead time has no order-by day.')) . '</div>
            </div>
        </div>
        <div class="col-12 lg:col-4 2xl:col-3 order-2">
            <div class="position-sticky my-7" style="top:3.3rem;">
                <div class="card pg-card">
                    <h2 class="card-header bg-reset border-0 pg-title-status">' . h(lang('Summary')) . '</h2>
                    <div class="card-body">
                        <dl class="row pg-text-2 mb-0">
                            <dt class="col-8 fw-normal fg-secondary">' . h($states['runs_out']) . '</dt>
                            <dd class="col-4 text-end mb-2' . ($counts['runs_out'] ? ' fg-danger fw-semibold' : '') . '">' . pg_format_number($counts['runs_out'], 0) . '</dd>
                            <dt class="col-8 fw-normal fg-secondary">' . h($states['order_now']) . '</dt>
                            <dd class="col-4 text-end mb-2">' . pg_format_number($counts['order_now'], 0) . '</dd>
                            <dt class="col-8 fw-normal fg-secondary">' . h($states['soon']) . '</dt>
                            <dd class="col-4 text-end mb-2">' . pg_format_number($counts['soon'], 0) . '</dd>
                            <dt class="col-8 fw-normal fg-secondary">' . h(lang('Dead stock value')) . '</dt>
                            <dd class="col-4 text-end mb-2">' . h(erp_money_out($dead_value)) . '</dd>
                        </dl>
                        ' . ($dead_unknown ? '<div class="form-text">' . h(lang(array('string' => '{var:1} dead products have no cost yet and are not in the value.', 'vars' => $dead_unknown))) . '</div>' : '') . '
                        <div class="form-text mt-5">' . h(lang(array('string' => 'Safety {var:1} days, cover {var:2} days; dead stock after {var:3} days without a sale.', 'vars' => array($settings['safety'], $settings['cover'], $settings['dead'])))) . '</div>
                        ' . (($settings_url !== '') ? '<a class="pg-button pg-button-ghost mt-3" href="' . h($settings_url) . '"><i class="bi bi-gear" aria-hidden="true"></i>' . h(lang('Stock Planning settings')) . '</a>' : '') . '
                    </div>
                </div>
                ' . (($output_orders !== '') ? '
                <div class="card pg-card mt-7">
                    <h2 class="card-header bg-reset border-0 pg-title-status">' . h(lang('Order per supplier')) . '</h2>
                    <div class="card-body pt-0">
                        ' . $output_orders . '
                        <div class="form-text mt-3">' . h(lang('Opens the purchase order form with the listed products and quantities; nothing is saved or sent until you save it.')) . '</div>
                    </div>
                </div>' : '') . '
            </div>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
