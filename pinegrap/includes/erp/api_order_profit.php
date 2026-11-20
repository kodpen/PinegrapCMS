<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - an order's profit and the profitability report through the external
 * API, and so as MCP tools.
 *
 * Both read only what the order's Profit card and Accounting > Profitability
 * read (includes/erp/order_profit.php, profitability.php): the cost rows and
 * net profit the general job keeps. Nothing here computes or writes a row.
 * Each product row of the report carries its break-even price on its channel
 * at the last 90 days' figures; a price is never changed from it. Money is in
 * minor units of the store currency, revenue without tax.
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

if (!defined('PG_ERP_ENTRY')) {
    define('PG_ERP_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/profitability.php');

/**
 * A row's money figures and margin. Pure.
 *
 * @param array $row  revenue, cost_*, net, margin
 * @return array
 */
function erp_api_profit_figures_out($row)
{
    $out = array();

    foreach (erp_profitability_figures() as $figure) {
        $out[$figure] = api_money($row[$figure] ?? 0);
    }

    $out['margin'] = (($row['margin'] ?? null) === null) ? null : (float) $row['margin'];

    return $out;
}

// What erp_api_profit_figures_out() returns.
function erp_api_profit_figures_out_schema()
{
    return array(
        'revenue' => 'integer',
        'cost_product' => 'integer',
        'cost_payment' => 'integer',
        'cost_marketplace' => 'integer',
        'cost_shipping' => 'integer',
        'cost_other' => 'integer',
        'net' => 'integer',
        'margin' => 'number?',
    );
}

/**
 * An order's profit, or an order the job has not worked out (computed false,
 * no figures). Pure.
 *
 * @param int        $order_id
 * @param array|null $profit  erp_order_profit row
 * @param array      $costs   erp_order_costs rows: kind, source, source_ref, amount
 * @return array
 */
function erp_api_order_profit_present($order_id, $profit, $costs)
{
    $computed = is_array($profit);
    $out = array(
        'order_id' => (int) $order_id,
        'computed' => $computed,
        'currency' => function_exists('erp_base_currency') ? erp_base_currency() : '',
        'channel' => $computed ? (string) $profit['channel'] : null,
        'state' => $computed ? (string) $profit['state'] : null,
        'stale' => $computed && ((int) $profit['stale'] === 1),
    );

    foreach (erp_api_profit_figures_out_schema() as $field => $type) {
        $out[$field] = null;
    }

    if ($computed) {
        $out = array_merge($out, erp_api_profit_figures_out($profit));
    }

    $out['costs'] = array();

    foreach ((array) $costs as $cost) {
        $out['costs'][] = array(
            'kind' => (string) $cost['kind'],
            'source' => (string) $cost['source'],
            'source_ref' => (string) $cost['source_ref'],
            'amount' => api_money($cost['amount']),
        );
    }

    return $out;
}

// What erp_api_order_profit_present() returns, declared for the OpenAPI document.
function erp_api_order_profit_schema()
{
    return array(
        'order_id' => 'integer',
        'computed' => 'boolean',
        'currency' => 'string',
        'channel' => 'string?',
        'state' => 'string?',
        'stale' => 'boolean',
        'revenue' => 'integer?',
        'cost_product' => 'integer?',
        'cost_payment' => 'integer?',
        'cost_marketplace' => 'integer?',
        'cost_shipping' => 'integer?',
        'cost_other' => 'integer?',
        'net' => 'integer?',
        'margin' => 'number?',
        'costs' => array(array(
            'kind' => 'string',
            'source' => 'string',
            'source_ref' => 'string',
            'amount' => 'integer',
        )),
    );
}

/**
 * The profitability report as the API gives it. Pure apart from the product
 * names the report carries.
 *
 * @param array      $report    erp_profitability_report(), maybe erp_profitability_for_product()
 * @param array      $evens     erp_profitability_break_evens()
 * @param int        $limit     How many product rows to list
 * @param float|null $target    The target margin
 * @param array      $accounts    erp_profitability_accounts()
 * @param int        $product_id  The product asked for, 0 for all
 * @return array
 */
function erp_api_profitability_present($report, $evens, $limit, $target, $accounts, $product_id)
{
    $channels = array();

    foreach ((array) $report['totals']['channels'] as $channel => $figures) {
        $channels[] = array_merge(array(
            'channel' => (string) $channel,
            'name' => erp_profitability_channel_label($channel, $accounts),
            'orders' => (int) $figures['orders'],
            'units' => (float) $figures['units'],
        ), erp_api_profit_figures_out($figures));
    }

    $products = array();
    $none = erp_order_profit_break_even(null);

    foreach (array_slice((array) $report['groups'], 0, max(0, (int) $limit)) as $group) {
        $name = erp_profitability_product_name($group, (array) ($report['products'] ?? array()));
        $even = $evens[erp_profitability_key($group['product_id'], $group['channel'])] ?? $none;

        $products[] = array_merge(array(
            'product_id' => (int) $group['product_id'],
            'sku' => (string) $name['sku'],
            'name' => (string) $name['title'],
            'channel' => (string) $group['channel'],
            'channel_name' => erp_profitability_channel_label($group['channel'], $accounts),
            'orders' => (int) $group['orders'],
            'units' => (float) $group['units'],
        ), erp_api_profit_figures_out($group), array(
            'estimate_orders' => (int) $group['estimate'],
            'incomplete_orders' => (int) $group['incomplete'],
            'break_even' => array(
                'price' => ($even['price'] === null) ? null : api_money($even['price']),
                'target_price' => ($even['target_price'] === null) ? null : api_money($even['target_price']),
                'average_price' => ($even['average_price'] === null) ? null : api_money($even['average_price']),
            ),
        ));
    }

    return array(
        'from' => (string) $report['from'],
        'to' => (string) $report['to'],
        'currency' => function_exists('erp_base_currency') ? erp_base_currency() : '',
        'channel' => ((string) $report['channel'] !== '') ? (string) $report['channel'] : null,
        'product_id' => ((int) $product_id > 0) ? (int) $product_id : null,
        'target_margin' => ($target === null) ? null : (float) $target,
        'orders' => (int) $report['orders'],
        'capped' => (bool) $report['capped'],
        'uncomputed' => (int) $report['uncomputed'],
        'totals' => array_merge(array(
            'units' => (float) $report['totals']['all']['units'],
        ), erp_api_profit_figures_out($report['totals']['all'])),
        'channels' => $channels,
        'products' => $products,
        'products_count' => count((array) $report['groups']),
    );
}

// What erp_api_profitability_present() returns, declared for the OpenAPI document.
function erp_api_profitability_schema()
{
    $figures = erp_api_profit_figures_out_schema();

    return array(
        'from' => 'date',
        'to' => 'date',
        'currency' => 'string',
        'channel' => 'string?',
        'product_id' => 'integer?',
        'target_margin' => 'number?',
        'orders' => 'integer',
        'capped' => 'boolean',
        'uncomputed' => 'integer',
        'totals' => array_merge(array('units' => 'number'), $figures),
        'channels' => array(array_merge(array(
            'channel' => 'string',
            'name' => 'string',
            'orders' => 'integer',
            'units' => 'number',
        ), $figures)),
        'products' => array(array_merge(array(
            'product_id' => 'integer',
            'sku' => 'string',
            'name' => 'string',
            'channel' => 'string',
            'channel_name' => 'string',
            'orders' => 'integer',
            'units' => 'number',
        ), $figures, array(
            'estimate_orders' => 'integer',
            'incomplete_orders' => 'integer',
            'break_even' => array(
                'price' => 'integer?',
                'target_price' => 'integer?',
                'average_price' => 'integer?',
            ),
        ))),
        'products_count' => 'integer',
    );
}

// Before the order profit tables (2026.11.0, 11.0.250) there is nothing to read.
function erp_api_order_profit_ready_check()
{
    if (!erp_order_profit_ready()) {
        api_fail(503, 'service_unavailable', lang('Profitability comes with the software update; run the update to use it.'));
    }
}

function erp_api_orders_profit($params)
{
    erp_api_order_profit_ready_check();

    $order_id = (int) $params['id'];

    if (db_value("SELECT id FROM orders WHERE id = '" . $order_id . "' LIMIT 1") === null) {
        api_fail_not_found(lang('Order'));
    }

    $profit = db_item("SELECT order_id, channel, state, stale, revenue, cost_product, cost_payment, cost_marketplace,
            cost_shipping, cost_other, net, margin
        FROM erp_order_profit WHERE order_id = '" . $order_id . "' LIMIT 1");

    $costs = is_array($profit)
        ? (array) db_items("SELECT kind, source, source_ref, amount FROM erp_order_costs
            WHERE order_id = '" . $order_id . "' ORDER BY FIELD(kind, 'product', 'payment', 'marketplace', 'shipping', 'other'), id ASC")
        : array();

    api_ok(erp_api_order_profit_present($order_id, is_array($profit) ? $profit : null, $costs));
}

function erp_api_profitability($params)
{
    erp_api_order_profit_ready_check();

    $period = erp_api_report_period($params);
    $asked = (string) ($params['channel'] ?? '');
    $channel = erp_profitability_channel_in($asked);

    if (($asked !== '') && ($channel === '')) {
        api_fail_validation(lang('The channel is web, or marketplace: followed by a marketplace account number.'), 'channel');
    }

    $product_id = (int) ($params['product_id'] ?? 0);
    $target = erp_profitability_target();
    $report = erp_profitability_for_product(erp_profitability_report($period['from'], $period['to'], $channel), $product_id);
    $evens = erp_profitability_break_evens(date('Y-m-d'), $target);

    api_ok(erp_api_profitability_present($report, $evens, (int) ($params['limit'] ?? 50), $target, erp_profitability_accounts(), $product_id));
}
