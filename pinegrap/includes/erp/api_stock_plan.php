<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the stock plan through the external API: GET /erp/stock-plan.
 *
 * Reads the plan the night pass builds (erp_stock_plan,
 * includes/erp/stock_plan.php): per tracked product the units sold a day,
 * the season, the supplier and its lead time, the days left, the run-out
 * and order-by days, the quantity to order and the state. Dead stock is
 * worked out when read, the way the plan's screen does. Read only: the plan
 * never changes a count and nothing is ordered from here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/stock_plan.php');

/**
 * The plan's states, in the order of urgency. Pure.
 *
 * @return array
 */
function erp_api_stock_plan_states()
{
    return array('runs_out', 'order_now', 'soon', 'fine', 'no_history');
}

/**
 * The filters of GET /erp/stock-plan as conditions on erp_stock_plan sp.
 * Pure.
 *
 * @param array $params state, supplier_id, product_id
 * @return array
 */
function erp_api_stock_plan_where(array $params)
{
    $where = array("p.inventory = '1'");

    if (isset($params['state']) && in_array((string) $params['state'], erp_api_stock_plan_states(), true)) {
        $where[] = "sp.state = '" . (string) $params['state'] . "'";
    }

    if (!empty($params['supplier_id'])) {
        $where[] = "sp.supplier_id = '" . (int) $params['supplier_id'] . "'";
    }

    if (!empty($params['product_id'])) {
        $where[] = "sp.product_id = '" . (int) $params['product_id'] . "'";
    }

    return $where;
}

/**
 * One plan row on the wire. $ctx carries what dead stock is judged by:
 * today, history_from and dead_days.
 *
 * @param array $row erp_stock_plan with product_sku, product_name,
 *                   inventory_quantity, supplier_title, avg_cost
 * @param array $ctx
 * @return array
 */
function erp_api_stock_plan_present($row, array $ctx)
{
    $stock = (int) $row['inventory_quantity'];
    $last_sale = erp_api_date((string) ($row['last_sale'] ?? ''));
    $cost = (($row['avg_cost'] ?? null) === null) ? null : (int) $row['avg_cost'];
    $dead = erp_stock_plan_dead($stock, $last_sale, (string) $ctx['history_from'], (string) $ctx['today'], (int) $ctx['dead_days'], $cost);
    $supplier = (int) $row['supplier_id'];

    return array(
        'product_id' => (int) $row['product_id'],
        'sku' => (string) $row['product_sku'],
        'name' => erp_stock_product_label($row),
        'stock' => $stock,
        'units_per_day' => round((float) $row['units_day'], 3),
        'season' => round((float) $row['season'], 2),
        'supplier_id' => ($supplier > 0) ? $supplier : null,
        'supplier_title' => ($supplier > 0) ? (string) ($row['supplier_title'] ?? '') : null,
        'lead_days' => ($row['lead_days'] === null) ? null : (int) $row['lead_days'],
        'lead_source' => (string) $row['lead_source'],
        'days_left' => ($row['days_left'] === null) ? null : (int) $row['days_left'],
        'runout_date' => erp_api_date((string) ($row['runout_day'] ?? '')),
        'order_by_date' => erp_api_date((string) ($row['order_by_day'] ?? '')),
        'quantity' => (int) $row['quantity'],
        'state' => (string) $row['state'],
        'last_sale_date' => $last_sale,
        'dead' => $dead['dead'],
        'dead_value' => $dead['value'],
    );
}

// What erp_api_stock_plan_present() returns.
function erp_api_stock_plan_schema()
{
    return array(
        'product_id' => 'integer',
        'sku' => 'string',
        'name' => 'string',
        'stock' => 'integer',
        'units_per_day' => 'number',
        'season' => 'number',
        'supplier_id' => 'integer?',
        'supplier_title' => 'string?',
        'lead_days' => 'integer?',
        'lead_source' => 'string',
        'days_left' => 'integer?',
        'runout_date' => 'date?',
        'order_by_date' => 'date?',
        'quantity' => 'integer',
        'state' => 'string',
        'last_sale_date' => 'date?',
        'dead' => 'boolean',
        'dead_value' => 'integer?',
    );
}

function erp_api_stock_plan_list($params)
{
    if (!erp_stock_plan_cache_ready()) {
        api_fail(503, 'service_unavailable', lang('Stock planning comes with the software update; run the update to use it.'));
    }

    $today = erp_stock_plan_day_of(time());
    $marks = erp_stock_plan_marks();
    $ctx = array(
        'today' => $today,
        'history_from' => is_array($marks) ? erp_stock_plan_history_from($marks, $today) : '',
        'dead_days' => erp_stock_plan_settings()['dead'],
    );

    $costs = erp_stock_ready();
    $from = "FROM erp_stock_plan sp
        INNER JOIN products p ON p.id = sp.product_id
        LEFT JOIN erp_accounts a ON a.id = sp.supplier_id"
        . ($costs ? " LEFT JOIN erp_product_costs c ON c.product_id = sp.product_id" : '');

    $page = erp_api_page("SELECT sp.*, sp.product_id AS id, p.name AS product_sku, p.short_description AS product_name,
            p.inventory_quantity, a.title AS supplier_title, " . ($costs ? 'c.avg_cost' : 'NULL AS avg_cost') . " " . $from,
        erp_api_stock_plan_where($params), '', 'sp.product_id', $params,
        "FROM erp_stock_plan sp INNER JOIN products p ON p.id = sp.product_id");

    $out = array();

    foreach ($page['rows'] as $row) {
        $out[] = erp_api_stock_plan_present($row, $ctx);
    }

    api_ok_list($out, $page['limit'], $page['next_cursor'], $page['total']);
}
