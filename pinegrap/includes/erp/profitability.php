<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Accounting > Profitability: each product's profit by channel (the web
 * store, each marketplace account) over a period, from the order profit rows
 * the general job keeps (includes/erp/order_profit.php), and the settings it
 * is built with: the typed cost rates and the target margin.
 *
 * An order's figures are split over its lines: revenue and every cost by
 * each line's share of the order's gross, except the product cost of a line
 * that has its own cost row (item:<order_item_id>), which it keeps. Every
 * split adds up to the order's figure exactly (erp_allocate()), so the
 * products of a channel add up to the channel and the channels to the
 * period.
 *
 * Only reads the order profit tables; the settings card writes erp_cost_rates
 * and the target margin (config_text), and marks the orders a changed rate
 * applies to so the general job works them out again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/money.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit.php');

/** The config_text row that keeps the target margin, a percent of revenue without tax. */
const ERP_PROFITABILITY_TARGET = 'erp_profit_target_margin';

/** The most orders one period reads; past it the screen says the figures are cut. */
const ERP_PROFITABILITY_MAX_ORDERS = 20000;

/** Orders read per query for their lines and cost rows. */
const ERP_PROFITABILITY_CHUNK = 500;

/**
 * The figures every row, group and total carries.
 *
 * @return string[]
 */
function erp_profitability_figures()
{
    return array('revenue', 'cost_product', 'cost_payment', 'cost_marketplace', 'cost_shipping', 'cost_other', 'net');
}

/**
 * An order's profit split over its lines. Pure.
 *
 * @param array $profit      erp_order_profit row: order_id, channel, state, revenue, cost_*, net
 * @param array $lines       the order's lines: id, product_id, quantity, weight (the line's gross)
 * @param array $item_costs  order_item_id => the product cost of the line's own row (item:<id>)
 * @return array  one row per line: order_id, product_id, channel, state, quantity, the figures
 */
function erp_profitability_split($profit, $lines, $item_costs)
{
    $lines = array_values((array) $lines);

    // An order without lines carries everything on one row of no product.
    if (empty($lines)) {
        $lines = array(array('id' => 0, 'product_id' => 0, 'quantity' => 0, 'weight' => 1));
    }

    $weights = array();
    $all_zero = true;

    foreach ($lines as $index => $line) {
        $weights[$index] = max(0, (int) ($line['weight'] ?? 0));
        $all_zero = $all_zero && ($weights[$index] === 0);
    }

    // Lines of no weight (all free) share evenly.
    if ($all_zero) {
        $weights = array_fill_keys(array_keys($weights), 1);
    }

    $shares = array();

    foreach (array('revenue', 'cost_payment', 'cost_marketplace', 'cost_shipping', 'cost_other') as $figure) {
        $shares[$figure] = erp_allocate((int) ($profit[$figure] ?? 0), $weights);
    }

    // Product cost: a line's own row first, the rest by weight over the lines
    // without one; when every line has its row, a difference to the order's
    // figure (a row written after the profit row) is spread over them all.
    $product_total = (int) ($profit['cost_product'] ?? 0);
    $own = array();
    $rest_weights = array();

    foreach ($lines as $index => $line) {
        $id = (int) ($line['id'] ?? 0);

        if (($id > 0) && array_key_exists($id, (array) $item_costs)) {
            $own[$index] = (int) $item_costs[$id];
        } else {
            $rest_weights[$index] = $weights[$index];
        }
    }

    $product = array_fill_keys(array_keys($weights), 0);
    $left = $product_total - array_sum($own);

    foreach ($own as $index => $amount) {
        $product[$index] = $amount;
    }

    if (!empty($rest_weights)) {
        if (array_sum($rest_weights) <= 0) {
            $rest_weights = array_fill_keys(array_keys($rest_weights), 1);
        }

        foreach (erp_allocate($left, $rest_weights) as $index => $amount) {
            $product[$index] += $amount;
        }
    } elseif ($left !== 0) {
        foreach (erp_allocate($left, $weights) as $index => $amount) {
            $product[$index] += $amount;
        }
    }

    $shares['cost_product'] = $product;
    $out = array();

    foreach ($lines as $index => $line) {
        $row = array(
            'order_id' => (int) ($profit['order_id'] ?? 0),
            'product_id' => (int) ($line['product_id'] ?? 0),
            'channel' => (string) ($profit['channel'] ?? 'web'),
            'state' => (string) ($profit['state'] ?? 'final'),
            'quantity' => $line['quantity'] ?? 0,
        );

        foreach (array('revenue', 'cost_product', 'cost_payment', 'cost_marketplace', 'cost_shipping', 'cost_other') as $figure) {
            $row[$figure] = (int) $shares[$figure][$index];
        }

        $row['net'] = $row['revenue'] - $row['cost_product'] - $row['cost_payment'] - $row['cost_marketplace']
            - $row['cost_shipping'] - $row['cost_other'];
        $out[] = $row;
    }

    return $out;
}

/**
 * A blank group of figures.
 *
 * @return array
 */
function erp_profitability_blank()
{
    return array_fill_keys(erp_profitability_figures(), 0) + array('orders' => 0, 'units' => 0.0, 'estimate' => 0, 'incomplete' => 0, 'margin' => null);
}

/**
 * The split rows grouped by product and channel, the most profitable first.
 * Pure.
 *
 * @param array $rows  erp_profitability_split() rows
 * @return array  list of groups: product_id, channel, orders, units, the figures,
 *                margin, estimate and incomplete (orders in that state)
 */
function erp_profitability_group($rows)
{
    $groups = array();
    $seen = array();

    foreach ((array) $rows as $row) {
        $key = (int) $row['product_id'] . '|' . (string) $row['channel'];

        if (!isset($groups[$key])) {
            $groups[$key] = array('product_id' => (int) $row['product_id'], 'channel' => (string) $row['channel']) + erp_profitability_blank();
        }

        foreach (erp_profitability_figures() as $figure) {
            $groups[$key][$figure] += (int) $row[$figure];
        }

        $groups[$key]['units'] += (float) $row['quantity'];
        $order_key = $key . '|' . (int) $row['order_id'];

        // An order with two lines of one product counts once.
        if (!isset($seen[$order_key])) {
            $seen[$order_key] = true;
            $groups[$key]['orders']++;

            if ((string) $row['state'] === 'estimate') {
                $groups[$key]['estimate']++;
            } elseif ((string) $row['state'] === 'incomplete') {
                $groups[$key]['incomplete']++;
            }
        }
    }

    foreach ($groups as $key => $group) {
        $groups[$key]['margin'] = erp_order_profit_margin($group['net'], $group['revenue']);
    }

    $groups = array_values($groups);

    usort($groups, function ($a, $b) {
        if ($a['net'] !== $b['net']) {
            return ($a['net'] < $b['net']) ? 1 : -1;
        }

        return strcmp($a['product_id'] . '|' . $a['channel'], $b['product_id'] . '|' . $b['channel']);
    });

    return $groups;
}

/**
 * The groups added up per channel and in all. Pure.
 *
 * The orders, estimate and incomplete counts are the groups' sums: an order
 * of two products counts in each. The screen prints the period's orders from
 * erp_profitability_report()'s own count.
 *
 * @param array $groups  erp_profitability_group()
 * @return array ['channels' => channel => figures (by net, highest first), 'all' => figures]
 */
function erp_profitability_totals($groups)
{
    $channels = array();
    $all = erp_profitability_blank();

    foreach ((array) $groups as $group) {
        $channel = (string) $group['channel'];

        if (!isset($channels[$channel])) {
            $channels[$channel] = erp_profitability_blank();
        }

        foreach (array_merge(erp_profitability_figures(), array('orders', 'estimate', 'incomplete')) as $figure) {
            $channels[$channel][$figure] += (int) $group[$figure];
            $all[$figure] += (int) $group[$figure];
        }

        $channels[$channel]['units'] += (float) $group['units'];
        $all['units'] += (float) $group['units'];
    }

    foreach ($channels as $channel => $figures) {
        $channels[$channel]['margin'] = erp_order_profit_margin($figures['net'], $figures['revenue']);
    }

    $all['margin'] = erp_order_profit_margin($all['net'], $all['revenue']);

    uksort($channels, function ($a, $b) use ($channels) {
        if ($channels[$a]['net'] !== $channels[$b]['net']) {
            return ($channels[$a]['net'] < $channels[$b]['net']) ? 1 : -1;
        }

        return strcmp((string) $a, (string) $b);
    });

    return array('channels' => $channels, 'all' => $all);
}

/**
 * A channel's name: the web store, or a marketplace account by its name.
 *
 * @param string $channel   web, or marketplace:<account id>
 * @param array  $accounts  account id => name
 * @return string  plain text
 */
function erp_profitability_channel_label($channel, $accounts)
{
    $channel = (string) $channel;

    if (preg_match('/^marketplace:(\d+)$/', $channel, $match)) {
        $id = (int) $match[1];
        $name = trim((string) (((array) $accounts)[$id] ?? ''));

        return ($name !== '') ? $name : lang(array('string' => 'Marketplace account #{var:1}', 'vars' => $id));
    }

    return lang('Web store');
}

/**
 * The channel filter as asked: '' (all), web or marketplace:<id>. Pure.
 *
 * @param mixed $asked
 * @return string
 */
function erp_profitability_channel_in($asked)
{
    if (!is_string($asked)) {
        return '';
    }

    return (($asked === 'web') || (preg_match('/^marketplace:[1-9]\d{0,9}$/', $asked) === 1)) ? $asked : '';
}

/**
 * A typed number with a point or a comma as its decimal sign and at most
 * $places decimals, as a string with a point; null when it is not one. Pure.
 *
 * @param string $text
 * @param int    $places
 * @return string|null
 */
function erp_profitability_number_in($text, $places)
{
    $text = str_replace(',', '.', trim((string) $text));

    return (preg_match('/^\d{1,9}(\.\d{1,' . (int) $places . '})?$/', $text) === 1) ? $text : null;
}

/**
 * A typed rate as the settings card sends it: a percent (0 to 100, three
 * decimals) and a fixed amount in the base currency. Both empty removes the
 * rate. Pure.
 *
 * @param string $percent
 * @param string $fixed
 * @return array ['error' => '' or the reason, 'empty' => bool, 'percent' => float, 'fixed' => int minor units]
 */
function erp_profitability_rate_in($percent, $fixed)
{
    $percent = trim((string) $percent);
    $fixed = trim((string) $fixed);
    $out = array('error' => '', 'empty' => false, 'percent' => 0.0, 'fixed' => 0);

    if (($percent === '') && ($fixed === '')) {
        $out['empty'] = true;

        return $out;
    }

    if ($percent !== '') {
        $number = erp_profitability_number_in($percent, 3);

        if (($number === null) || ((float) $number > 100)) {
            $out['error'] = lang('Enter a percent from 0 to 100, with at most three decimals.');

            return $out;
        }

        $out['percent'] = (float) $number;
    }

    if ($fixed !== '') {
        if ((strpos($fixed, '-') !== false) || !erp_amount_text_valid($fixed)) {
            $out['error'] = lang('Enter the fixed part as an amount of 0 or more.');

            return $out;
        }

        $kurus = erp_kurus($fixed);

        if (!is_int($kurus) || ($kurus > 100000000000)) {
            $out['error'] = lang('Enter the fixed part as an amount of 0 or more.');

            return $out;
        }

        $out['fixed'] = $kurus;
    }

    return $out;
}

/**
 * The target margin as typed: '' for none, else a percent from 0 to below
 * 100 with at most one decimal, written with a point. Pure.
 *
 * @param string $text
 * @return array ['error' => '' or the reason, 'value' => string]
 */
function erp_profitability_target_in($text)
{
    $text = trim((string) $text);

    if ($text === '') {
        return array('error' => '', 'value' => '');
    }

    $number = erp_profitability_number_in($text, 1);

    if (($number === null) || ((float) $number >= 100)) {
        return array('error' => lang('Enter the target margin as a percent from 0 to 99.9, with at most one decimal.'), 'value' => '');
    }

    // 30.0 is written 30, 07 is written 7.
    return array('error' => '', 'value' => (string) (0 + (float) $number));
}

/**
 * Whether a margin is under the target. Pure.
 *
 * @param float|string|null $margin
 * @param float|null        $target
 * @return bool
 */
function erp_profitability_below_target($margin, $target)
{
    return ($margin !== null) && ($target !== null) && ((float) $margin < (float) $target);
}

/**
 * The target margin set on the settings card, or null when none is.
 *
 * @return float|null
 */
function erp_profitability_target()
{
    $value = pg_config_text(ERP_PROFITABILITY_TARGET);

    return ($value === '') ? null : (float) $value;
}

/**
 * The marketplace accounts by id, for the channel names.
 *
 * @return array  id => name
 */
function erp_profitability_accounts()
{
    $accounts = array();

    if (pg_schema_has('marketplace_accounts')) {
        foreach ((array) db_items("SELECT id, provider, name FROM marketplace_accounts ORDER BY id ASC") as $row) {
            $name = trim((string) $row['name']);
            $accounts[(int) $row['id']] = ($name !== '') ? $name : ucfirst((string) $row['provider']);
        }
    }

    return $accounts;
}

/**
 * The rows of the settings card: each payment method orders were paid with,
 * each shipping method and each marketplace account, with the rate typed for
 * it. A rate kept for a method no longer listed is shown too, so it can be
 * removed.
 *
 * @return array  kind => list of ['key', 'label', 'percent', 'fixed', 'set']
 */
function erp_profitability_rate_rows()
{
    $rates = erp_order_profit_rates();
    $rows = array('payment' => array(), 'shipping' => array(), 'marketplace' => array());

    $payment = array();

    foreach ((array) db_values("SELECT DISTINCT payment_method FROM orders WHERE payment_method <> '' ORDER BY payment_method ASC LIMIT 50") as $method) {
        $payment[(string) $method] = (string) $method;
    }

    $shipping = array();

    if (pg_schema_has('shipping_methods')) {
        foreach ((array) db_items("SELECT id, name FROM shipping_methods ORDER BY name ASC, id ASC") as $method) {
            $shipping[(string) (int) $method['id']] = (string) $method['name'];
        }
    }

    $marketplace = array();

    foreach (erp_profitability_accounts() as $id => $name) {
        $marketplace[(string) $id] = $name;
    }

    foreach (array('payment' => $payment, 'shipping' => $shipping, 'marketplace' => $marketplace) as $kind => $labels) {
        foreach ((array) $rates[$kind] as $key => $rate) {
            if (!isset($labels[(string) $key])) {
                $labels[(string) $key] = ($kind === 'payment') ? (string) $key : '#' . $key;
            }
        }

        foreach ($labels as $key => $label) {
            $rate = $rates[$kind][(string) $key] ?? null;
            $rows[$kind][] = array(
                'key' => (string) $key,
                'label' => $label,
                'percent' => ($rate === null) ? '' : erp_decimal_text((float) $rate['percent']),
                'fixed' => (($rate === null) || ((int) $rate['fixed'] === 0)) ? '' : erp_money_input((int) $rate['fixed']),
                'set' => ($rate !== null),
            );
        }
    }

    return $rows;
}

/**
 * Whether a rate as sent differs from the one kept. Pure.
 *
 * @param array|null $before  erp_order_profit_rates() entry: percent, fixed; null for none
 * @param array      $sent    erp_profitability_rate_in()
 * @return bool
 */
function erp_profitability_rate_changed($before, $sent)
{
    if ($before === null) {
        return empty($sent['empty']);
    }

    return !empty($sent['empty'])
        || (round((float) $before['percent'], 3) !== round((float) $sent['percent'], 3))
        || ((int) $before['fixed'] !== (int) $sent['fixed']);
}

/**
 * Mark the computed orders a rate applies to, so the general job works them
 * out again: its recheck compares only the order's own figures, never the
 * rates. A shop order paid with the method, an order with a recipient on the
 * shipping method, an order of the marketplace account.
 *
 * @param string $kind  payment, shipping, marketplace
 * @param string $key   the rate's key
 * @return bool
 */
function erp_profitability_mark_rate($kind, $key)
{
    if ($kind === 'payment') {
        $result = db_q("UPDATE erp_order_profit INNER JOIN orders ON orders.id = erp_order_profit.order_id
            SET erp_order_profit.stale = 1
            WHERE orders.payment_method = ? AND erp_order_profit.channel = 'web'", array((string) $key));
    } elseif ($kind === 'shipping') {
        $result = db_q("UPDATE erp_order_profit SET stale = 1
            WHERE order_id IN (SELECT order_id FROM ship_tos WHERE shipping_method_id = ?)", array((int) $key));
    } else {
        $result = db_q("UPDATE erp_order_profit SET stale = 1 WHERE channel = ?", array('marketplace:' . (int) $key));
    }

    return $result !== false;
}

/**
 * Save the settings card: every rate it sent and the target margin. Nothing
 * is written when one field is refused.
 *
 * @param array $post     rate[<kind>][<n>][key|percent|fixed], target_margin
 * @param int   $user_id
 * @return array ['success' => bool, 'error' => string, 'field' => string]
 */
function erp_profitability_settings_save($post, $user_id)
{
    $writes = array();

    foreach (array('payment', 'shipping', 'marketplace') as $kind) {
        $sent = $post['rate'][$kind] ?? array();

        if (!is_array($sent)) {
            continue;
        }

        foreach ($sent as $index => $row) {
            if (!is_array($row) || !is_string($row['key'] ?? null)) {
                continue;
            }

            $key = (string) $row['key'];

            if (($key === '') || (mb_strlen($key) > 100) || (($kind !== 'payment') && (preg_match('/^[1-9]\d{0,9}$/', $key) !== 1))) {
                continue;
            }

            $rate = erp_profitability_rate_in(is_string($row['percent'] ?? null) ? $row['percent'] : '', is_string($row['fixed'] ?? null) ? $row['fixed'] : '');

            if ($rate['error'] !== '') {
                return array('success' => false, 'error' => $rate['error'], 'field' => 'rate_' . $kind . '_' . (int) $index);
            }

            $writes[] = array('kind' => $kind, 'key' => $key) + $rate;
        }
    }

    $target = erp_profitability_target_in(is_string($post['target_margin'] ?? null) ? $post['target_margin'] : '');

    if ($target['error'] !== '') {
        return array('success' => false, 'error' => $target['error'], 'field' => 'target_margin');
    }

    $now = time();
    $old = erp_order_profit_rates();

    foreach ($writes as $write) {
        $before = $old[$write['kind']][$write['key']] ?? null;

        if (!erp_profitability_rate_changed($before, $write)) {
            continue;
        }

        $ok = $write['empty']
            ? db_q("DELETE FROM erp_cost_rates WHERE kind = ? AND rate_key = ?", array($write['kind'], $write['key']))
            : db_q("INSERT INTO erp_cost_rates (kind, rate_key, percent, fixed, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE percent = VALUES(percent), fixed = VALUES(fixed), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
                array($write['kind'], $write['key'], number_format($write['percent'], 3, '.', ''), (int) $write['fixed'], (int) $user_id, $now));

        if (($ok === false) || !erp_profitability_mark_rate($write['kind'], $write['key'])) {
            return array('success' => false, 'error' => lang('The settings could not be saved.'), 'field' => '');
        }
    }

    if (!pg_config_text_save(ERP_PROFITABILITY_TARGET, $target['value']) && ($target['value'] !== '')) {
        return array('success' => false, 'error' => lang('The settings could not be saved.'), 'field' => 'target_margin');
    }

    return array('success' => true, 'error' => '', 'field' => '');
}

/**
 * The period's profit by product and channel, read from the order profit
 * rows of the orders placed in it.
 *
 * @param string $from     Y-m-d
 * @param string $to       Y-m-d
 * @param string $channel  '' for all, web or marketplace:<id>
 * @return array ['groups', 'totals', 'orders', 'capped', 'products' => id => [title, sku],
 *                'uncomputed' => finished orders of the period with no profit row yet]
 */
function erp_profitability_report($from, $to, $channel = '')
{
    $start = (int) strtotime($from . ' 00:00:00');
    $end = (int) strtotime($to . ' 00:00:00 +1 day');
    $where = "order_date >= '" . $start . "' AND order_date < '" . $end . "'"
        . (($channel !== '') ? " AND channel = '" . e($channel) . "'" : '');

    $profits = (array) db_items("SELECT order_id, channel, state, revenue, cost_product, cost_payment, cost_marketplace,
            cost_shipping, cost_other, net
        FROM erp_order_profit WHERE " . $where . "
        ORDER BY order_date ASC, order_id ASC
        LIMIT " . (ERP_PROFITABILITY_MAX_ORDERS + 1));

    $capped = count($profits) > ERP_PROFITABILITY_MAX_ORDERS;

    if ($capped) {
        array_pop($profits);
    }

    $rows = array();
    $product_ids = array();

    foreach (array_chunk($profits, ERP_PROFITABILITY_CHUNK) as $chunk) {
        $ids = array();

        foreach ($chunk as $profit) {
            $ids[] = (int) $profit['order_id'];
        }

        $lines = array();

        foreach ((array) db_items("SELECT id, order_id, product_id, quantity, price, tax_total FROM order_items
            WHERE order_id IN (" . implode(',', $ids) . ") AND saved_for_later = 0
            ORDER BY id ASC") as $item) {
            $lines[(int) $item['order_id']][] = array(
                'id' => (int) $item['id'],
                'product_id' => (int) $item['product_id'],
                'quantity' => (float) $item['quantity'],
                'weight' => erp_order_profit_line_gross($item),
            );
            $product_ids[(int) $item['product_id']] = true;
        }

        $item_costs = array();

        foreach ((array) db_items("SELECT order_id, source_ref, amount FROM erp_order_costs
            WHERE order_id IN (" . implode(',', $ids) . ") AND kind = 'product' AND source_ref LIKE 'item:%'") as $cost) {
            $item_costs[(int) $cost['order_id']][(int) substr((string) $cost['source_ref'], 5)] = (int) $cost['amount'];
        }

        foreach ($chunk as $profit) {
            $order_id = (int) $profit['order_id'];

            foreach (erp_profitability_split($profit, $lines[$order_id] ?? array(), $item_costs[$order_id] ?? array()) as $row) {
                $rows[] = $row;
            }
        }
    }

    $groups = erp_profitability_group($rows);
    unset($rows);

    $products = array();
    $ids = array_filter(array_map('intval', array_keys($product_ids)));

    foreach (array_chunk($ids, ERP_PROFITABILITY_CHUNK) as $chunk) {
        foreach ((array) db_items("SELECT id, name, short_description FROM products WHERE id IN (" . implode(',', $chunk) . ")") as $product) {
            $title = trim((string) $product['short_description']);
            $sku = trim((string) $product['name']);
            $products[(int) $product['id']] = array('title' => ($title !== '') ? $title : $sku, 'sku' => $sku);
        }
    }

    $uncomputed = (int) db_value("SELECT COUNT(*) FROM orders
        LEFT JOIN erp_order_profit ON erp_order_profit.order_id = orders.id
        WHERE erp_order_profit.order_id IS NULL
            AND orders.status IN ('" . implode("','", array_map('e', erp_order_billable_statuses())) . "')
            AND orders.order_date >= '" . $start . "' AND orders.order_date < '" . $end . "'");

    return array(
        'from' => $from,
        'to' => $to,
        'channel' => $channel,
        'groups' => $groups,
        'totals' => erp_profitability_totals($groups),
        'orders' => count($profits),
        'capped' => $capped,
        'products' => $products,
        'uncomputed' => ($channel === '') ? $uncomputed : 0,
    );
}

/**
 * The key of a product on a channel, as the break-even list and the loss
 * notice keep it. Pure.
 *
 * @param int    $product_id
 * @param string $channel
 * @return string
 */
function erp_profitability_key($product_id, $channel)
{
    return (int) $product_id . '|' . (string) $channel;
}

/**
 * The report narrowed to one product: its groups and their totals. Pure.
 *
 * @param array $report      erp_profitability_report()
 * @param int   $product_id  0 for every product
 * @return array
 */
function erp_profitability_for_product($report, $product_id)
{
    if ((int) $product_id <= 0) {
        return $report;
    }

    $groups = array();

    foreach ((array) $report['groups'] as $group) {
        if ((int) $group['product_id'] === (int) $product_id) {
            $groups[] = $group;
        }
    }

    $report['groups'] = $groups;
    $report['totals'] = erp_profitability_totals($groups);

    return $report;
}

/**
 * Each product's break-even price on each channel it sold on over the last 90
 * days (erp_order_profit_break_even()), at the target margin when one is set.
 *
 * @param string     $today   Y-m-d, the period's last day
 * @param float|null $target  erp_profitability_target()
 * @return array  erp_profitability_key() => erp_order_profit_break_even()
 */
function erp_profitability_break_evens($today, $target)
{
    $report = erp_profitability_report(date('Y-m-d', strtotime($today . ' -89 days')), $today);
    $evens = array();

    foreach ($report['groups'] as $group) {
        $evens[erp_profitability_key($group['product_id'], $group['channel'])] = erp_order_profit_break_even($group, $target);
    }

    return $evens;
}

/**
 * A group's product as the screen and the workbook name it.
 *
 * @param array $group     erp_profitability_group() row
 * @param array $products  id => [title, sku]
 * @return array ['title', 'sku']
 */
function erp_profitability_product_name($group, $products)
{
    $id = (int) $group['product_id'];

    if ($id <= 0) {
        return array('title' => lang('Lines without a product'), 'sku' => '');
    }

    return $products[$id] ?? array('title' => lang(array('string' => 'Product #{var:1}', 'vars' => $id)), 'sku' => '');
}

/**
 * The report as a workbook: the channels and the products by channel.
 *
 * @param array  $report    erp_profitability_report()
 * @param array  $accounts  erp_profitability_accounts()
 * @param string $path      Where to write the .xlsx
 * @return bool
 */
function erp_profitability_workbook($report, $accounts, $path)
{
    $base = erp_base_currency();
    $money = function ($label) use ($base) {
        return array('label' => $label . ' (' . $base . ')', 'type' => 'money', 'width' => 16);
    };
    $cost_columns = array(
        $money(lang('Net sales')),
        $money(lang('Product cost')),
        $money(lang('Payment fees')),
        $money(lang('Marketplace deductions')),
        $money(lang('Shipping cost')),
        $money(lang('Other costs')),
        $money(lang('Net profit')),
        array('label' => lang('Margin (%)'), 'type' => 'number', 'width' => 10),
    );
    $figures = function ($row) {
        return array($row['revenue'], $row['cost_product'], $row['cost_payment'], $row['cost_marketplace'],
            $row['cost_shipping'], $row['cost_other'], $row['net'], ($row['margin'] !== null) ? (float) $row['margin'] : '');
    };

    $channel_rows = array();

    foreach ($report['totals']['channels'] as $channel => $row) {
        $channel_rows[] = array_merge(array(erp_profitability_channel_label($channel, $accounts), (float) $row['units']), $figures($row));
    }

    $channel_rows[] = array_merge(array(lang('Total'), (float) $report['totals']['all']['units']), $figures($report['totals']['all']));

    $sheets = array();
    $sheets['channels'] = array('title' => lang('By channel'), 'columns' => array_merge(array(
        array('label' => lang('Channel'), 'type' => 'text', 'width' => 28),
        array('label' => lang('Units'), 'type' => 'number', 'width' => 10),
    ), $cost_columns), 'rows' => $channel_rows);

    $product_rows = array();

    foreach ($report['groups'] as $group) {
        $name = erp_profitability_product_name($group, $report['products']);
        $state = ((int) $group['incomplete'] > 0) ? lang('Incomplete') : (((int) $group['estimate'] > 0) ? lang('Estimate') : '');
        $product_rows[] = array_merge(array(
            (string) $name['sku'],
            (string) $name['title'],
            erp_profitability_channel_label($group['channel'], $accounts),
            (int) $group['orders'],
            (float) $group['units'],
        ), $figures($group), array($state));
    }

    $sheets['products'] = array('title' => lang('By product and channel'), 'columns' => array_merge(array(
        array('label' => lang('Code'), 'type' => 'text', 'width' => 16),
        array('label' => lang('Product'), 'type' => 'text', 'width' => 40),
        array('label' => lang('Channel'), 'type' => 'text', 'width' => 24),
        array('label' => lang('Orders'), 'type' => 'int', 'width' => 8),
        array('label' => lang('Units'), 'type' => 'number', 'width' => 10),
    ), $cost_columns, array(
        array('label' => lang('State'), 'type' => 'text', 'width' => 12),
    )), 'rows' => $product_rows);

    return erp_accountant_write_workbook($sheets, $path);
}
