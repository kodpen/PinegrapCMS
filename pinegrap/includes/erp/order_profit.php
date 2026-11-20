<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An order's costs and net profit, in the Accounting module.
 *
 * erp_order_costs keeps an order's cost rows: product, payment, marketplace,
 * shipping and other, each with its source and a reference, so a rerun adds
 * no row. erp_order_profit keeps one row per order: revenue, the costs by
 * kind, net profit, margin and a state (final, estimate, incomplete).
 * erp_cost_rates keeps the typed rates (percent plus fixed) per payment
 * method, shipping method and marketplace account, used when no real figure
 * was recorded.
 *
 * erp_order_profit_compute() is the whole calculation and reads nothing; the
 * pass (erp_order_profit_run(), called by erp_order_profit_job.php) reads an
 * order's figures, computes, and writes the two order tables. It never writes
 * a receipt, a till or bank move, an account move, an invoice settlement or a
 * payout record: a figure becomes an order's cost row and nothing else.
 *
 * Money is integer minor units in the order's own currency (the unit of
 * orders.total); a figure read in another currency is converted before it is
 * written, and a rate is applied only with erp_apply_rate() (money.php).
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/fx.php');

/** Orders computed per database read in the pass. */
const ERP_ORDER_PROFIT_BATCH = 100;

/** The config_text row that keeps where the recheck of computed orders stands. */
const ERP_ORDER_PROFIT_CURSOR = 'erp_order_profit_cursor';

/**
 * The source_ref of the fee a payment service kept (source gateway): one row
 * per order, as an order is paid once; a second write for it (a repeated
 * checkout) replaces the amount.
 */
const ERP_ORDER_PROFIT_GATEWAY_REF = 'gateway';

/**
 * The kinds of cost an order has.
 *
 * @return string[]
 */
function erp_order_profit_kinds()
{
    return array('product', 'payment', 'marketplace', 'shipping', 'other');
}

/**
 * The sources the pass itself writes; every other source (gateway, label,
 * marketplace) is recorded by the code that read the real figure and is
 * never removed by the pass.
 *
 * @return string[]
 */
function erp_order_profit_pass_sources()
{
    return array('invoice', 'average', 'bundle', 'rate', 'estimate');
}

/**
 * An order's revenue: erp_profit_report()'s rule for an order (total plus the
 * gift card part, less tax; erp_profit_uninvoiced_orders()), less what was
 * refunded.
 *
 * @param array $order  orders row
 * @return int
 */
function erp_order_profit_revenue($order)
{
    return (int) ($order['total'] ?? 0) + (int) ($order['gift_card_discount'] ?? 0)
        - (int) ($order['tax'] ?? 0) - (int) ($order['refunded_amount'] ?? 0);
}

/**
 * The figures of an order the profit is built from, as one hash: the pass
 * computes an order again when it changes.
 *
 * @param array $order  orders row
 * @return string  32 hex characters
 */
function erp_order_profit_basis($order)
{
    $parts = array();

    foreach (array('status', 'total', 'gift_card_discount', 'tax', 'refunded_amount', 'shipping', 'payment_method', 'erp_invoice_id') as $key) {
        $parts[] = (string) ($order[$key] ?? '');
    }

    return md5(implode('|', $parts));
}

/**
 * A typed rate on an amount: the percent of it plus the fixed part.
 *
 * @param int   $base
 * @param array $rate  ['percent' => float, 'fixed' => int]
 * @return int
 */
function erp_order_profit_rate_amount($base, $rate)
{
    return (int) erp_apply_rate((int) $base, (float) ($rate['percent'] ?? 0)) + (int) ($rate['fixed'] ?? 0);
}

/**
 * A line's amount as charged, tax included: what a marketplace takes its
 * commission on.
 *
 * @param array $item  order_items row: price, quantity, tax_total
 * @return int
 */
function erp_order_profit_line_gross($item)
{
    return (int) erp_line_total((int) ($item['price'] ?? 0), (float) ($item['quantity'] ?? 0)) + (int) ($item['tax_total'] ?? 0);
}

/**
 * The product cost rows of an order.
 *
 * An invoiced order takes the cost of its invoice's sale moves, one row for
 * the invoice, when every move carries a cost (a move written before the
 * product had a cost row holds 0). Otherwise each line: a bundle by the unit
 * cost its components were sold with (order_item_components), a plain line by
 * the average cost the pass first met, kept so a later average never
 * rewrites it. A line with no cost known adds no row and the order is
 * incomplete.
 *
 * @param array $input  see erp_order_profit_compute()
 * @return array ['rows' => array, 'incomplete' => bool]
 */
function erp_order_profit_product_rows($input)
{
    $invoice_id = (int) ($input['order']['erp_invoice_id'] ?? 0);
    $moves = (array) ($input['invoice_moves'] ?? array());

    if (($invoice_id > 0) && !empty($moves)) {
        $total = 0;
        $costed = true;

        foreach ($moves as $move) {
            if ((int) $move['cost_total'] <= 0) {
                $costed = false;
                break;
            }

            $total += (int) $move['cost_total'];
        }

        if ($costed) {
            return array(
                'rows' => array(array('kind' => 'product', 'source' => 'invoice', 'ref' => 'invoice:' . $invoice_id, 'amount' => $total)),
                'incomplete' => false,
            );
        }
    }

    $kept = (array) ($input['kept'] ?? array());
    $averages = (array) ($input['averages'] ?? array());
    $components = (array) ($input['components'] ?? array());
    $rows = array();
    $incomplete = false;

    foreach ((array) $input['items'] as $item) {
        $product_id = (int) ($item['product_id'] ?? 0);
        $quantity = (float) ($item['quantity'] ?? 0);
        $ref = 'item:' . (int) $item['id'];

        if (($product_id <= 0) || ($quantity <= 0)) {
            continue;
        }

        if (array_key_exists($ref, $kept)) {
            $rows[] = array('kind' => 'product', 'source' => 'average', 'ref' => $ref, 'amount' => (int) $kept[$ref]);
            continue;
        }

        $parts = (array) ($components[(int) $item['id']] ?? array());

        if (!empty($parts)) {
            $unit = 0;
            $from_snapshot = true;
            $known = true;

            foreach ($parts as $part) {
                $cost = (int) $part['unit_cost'];

                // A component sold before it had a cost: its average now.
                if ($cost <= 0) {
                    $from_snapshot = false;
                    $cost = (int) ($averages[(int) $part['product_id']] ?? 0);
                }

                if ($cost <= 0) {
                    $known = false;
                    break;
                }

                $unit += (int) erp_line_total($cost, (float) $part['quantity']);
            }

            if (!$known) {
                $incomplete = true;
                continue;
            }

            $rows[] = array('kind' => 'product', 'source' => $from_snapshot ? 'bundle' : 'average', 'ref' => $ref,
                'amount' => (int) erp_line_total($unit, $quantity));
            continue;
        }

        $average = (int) ($averages[$product_id] ?? 0);

        if ($average <= 0) {
            $incomplete = true;
            continue;
        }

        $rows[] = array('kind' => 'product', 'source' => 'average', 'ref' => $ref, 'amount' => (int) erp_line_total($average, $quantity));
    }

    return array('rows' => $rows, 'incomplete' => $incomplete);
}

/**
 * An order's margin: net × 100 / revenue, rounded to one decimal with
 * round(), or null without revenue. The only place erp_order_profit.margin
 * is worked out; nothing writes it by hand.
 *
 * @param int $net
 * @param int $revenue
 * @return float|null
 */
function erp_order_profit_margin($net, $revenue)
{
    return ((int) $revenue !== 0) ? round(((int) $net) * 100 / ((int) $revenue), 1) : null;
}

/**
 * A product's break-even price on a channel: the lowest price, without tax,
 * at which a unit made no loss on the figures of the period read (the last 90
 * days), and the price that reaches the target margin when one is set. Shown
 * only: no price is ever changed from it. Pure.
 *
 * Product cost, shipping and other costs are taken as a cost per unit; the
 * payment fees and marketplace deductions as a share of the price, as a rate
 * on the sale is. So price - share x price - unit cost = margin x price, and
 * price = unit cost / (1 - share - margin). A share and margin that take the
 * whole price leave no such price (null); no cost makes every price cover it
 * (0).
 *
 * @param array|null $group   erp_profitability_group() row: units, revenue and the costs
 * @param float|null $target  Target margin, a percent of revenue; null for none
 * @return array ['price' => int|null, 'target_price' => int|null, 'average_price' => int|null]
 */
function erp_order_profit_break_even($group, $target = null)
{
    $none = array('price' => null, 'target_price' => null, 'average_price' => null);
    $units = is_array($group) ? (float) ($group['units'] ?? 0) : 0.0;

    if ($units <= 0) {
        return $none;
    }

    $revenue = (int) ($group['revenue'] ?? 0);
    $share = ($revenue > 0) ? ((int) ($group['cost_payment'] ?? 0) + (int) ($group['cost_marketplace'] ?? 0)) / $revenue : 0.0;
    $unit_cost = ((int) ($group['cost_product'] ?? 0) + (int) ($group['cost_shipping'] ?? 0) + (int) ($group['cost_other'] ?? 0)) / $units;

    $price = function ($margin) use ($share, $unit_cost) {
        $left = 1 - $share - $margin;

        if ($left <= 0) {
            return null;
        }

        return ($unit_cost <= 0) ? 0 : erp_kurus_up($unit_cost / $left);
    };

    return array(
        'price' => $price(0),
        'target_price' => ($target === null) ? null : $price(((float) $target) / 100),
        'average_price' => ($revenue > 0) ? (int) round($revenue / $units) : null,
    );
}

/**
 * Compute an order's cost rows and its profit. Reads nothing.
 *
 * @param array $input
 *   order            orders row: id, status, total, gift_card_discount, tax, refunded_amount,
 *                    shipping, payment_method, erp_invoice_id
 *   items            the charged order_items rows: id, product_id, quantity, price, tax_total
 *   invoice_moves    the sale moves of the order's invoice: product_id, quantity, cost_total
 *   kept             product rows the pass wrote before from an average, ref => amount
 *   averages         product_id => avg_cost today
 *   components       order_item_id => list of product_id, quantity (per bundle), unit_cost
 *   rates            kind (payment, shipping, marketplace) => key => ['percent', 'fixed']
 *   shipping_methods the shipping method ids of the order's recipients
 *   marketplace      null, or account_id and commission (product_id => percent or null)
 *   recorded         cost rows written by others: kind, source, ref, amount
 * @return array ['costs' => rows the pass writes, 'profit' => the erp_order_profit figures]
 */
function erp_order_profit_compute($input)
{
    $order = (array) $input['order'];
    $rates = (array) ($input['rates'] ?? array());
    $recorded = (array) ($input['recorded'] ?? array());
    $marketplace = $input['marketplace'] ?? null;

    $real = array();

    foreach ($recorded as $row) {
        $real[(string) $row['kind']] = true;
    }

    $product = erp_order_profit_product_rows($input);
    $costs = $product['rows'];

    // A rate or estimate is one row per kind with an empty reference, so two
    // never pile up for an order (UNIQUE order_id, kind, source_ref).

    // Payment: a marketplace collects the money itself and its deductions
    // stand for the fee, so a rate per payment method is for the shop's own
    // orders.
    if (empty($real['payment']) && !is_array($marketplace)) {
        $method = (string) ($order['payment_method'] ?? '');

        if (($method !== '') && isset($rates['payment'][$method])) {
            $costs[] = array('kind' => 'payment', 'source' => 'rate', 'ref' => '',
                'amount' => erp_order_profit_rate_amount((int) ($order['total'] ?? 0), $rates['payment'][$method]));
        }
    }

    // Shipping: each recipient's method that has a rate, on the order's
    // shipping charge, added up.
    if (empty($real['shipping'])) {
        $amount = 0;
        $found = false;

        foreach (array_unique(array_map('intval', (array) ($input['shipping_methods'] ?? array()))) as $method_id) {
            $key = (string) $method_id;

            if (isset($rates['shipping'][$key])) {
                $amount += erp_order_profit_rate_amount((int) ($order['shipping'] ?? 0), $rates['shipping'][$key]);
                $found = true;
            }
        }

        if ($found) {
            $costs[] = array('kind' => 'shipping', 'source' => 'rate', 'ref' => '', 'amount' => $amount);
        }
    }

    // Marketplace, until its own deductions are read: the listing's
    // commission rate on each line, else the rate typed for the account,
    // added up; an estimate when any line took the listing's rate.
    if (is_array($marketplace) && empty($real['marketplace'])) {
        $account = (string) (int) ($marketplace['account_id'] ?? 0);
        $commission = (array) ($marketplace['commission'] ?? array());
        $amount = 0;
        $found = false;
        $source = 'rate';

        foreach ((array) $input['items'] as $item) {
            $gross = erp_order_profit_line_gross($item);
            $percent = $commission[(int) ($item['product_id'] ?? 0)] ?? null;

            if (($percent !== null) && ((float) $percent > 0)) {
                $amount += erp_order_profit_rate_amount($gross, array('percent' => (float) $percent, 'fixed' => 0));
                $found = true;
                $source = 'estimate';
            } elseif (isset($rates['marketplace'][$account])) {
                $amount += erp_order_profit_rate_amount($gross, $rates['marketplace'][$account]);
                $found = true;
            }
        }

        if ($found) {
            $costs[] = array('kind' => 'marketplace', 'source' => $source, 'ref' => '', 'amount' => $amount);
        }
    }

    $by_kind = array_fill_keys(erp_order_profit_kinds(), 0);
    $estimate = false;

    foreach (array_merge($costs, $recorded) as $row) {
        $kind = in_array($row['kind'], erp_order_profit_kinds(), true) ? $row['kind'] : 'other';
        $by_kind[$kind] += (int) $row['amount'];

        if (in_array($row['source'], array('rate', 'estimate'), true)) {
            $estimate = true;
        }
    }

    $revenue = erp_order_profit_revenue($order);
    $net = $revenue - array_sum($by_kind);

    if ($product['incomplete']) {
        $state = 'incomplete';
    } elseif ($estimate) {
        $state = 'estimate';
    } else {
        $state = 'final';
    }

    return array(
        'costs' => $costs,
        'profit' => array(
            'revenue' => $revenue,
            'cost_product' => $by_kind['product'],
            'cost_payment' => $by_kind['payment'],
            'cost_marketplace' => $by_kind['marketplace'],
            'cost_shipping' => $by_kind['shipping'],
            'cost_other' => $by_kind['other'],
            'net' => $net,
            'margin' => erp_order_profit_margin($net, $revenue),
            'state' => $state,
            'channel' => is_array($marketplace) ? 'marketplace:' . (int) ($marketplace['account_id'] ?? 0) : 'web',
            'basis' => erp_order_profit_basis($order),
        ),
    );
}

/**
 * Whether the three tables are there (2026.11.0, 11.0.250).
 *
 * @return bool
 */
function erp_order_profit_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('erp_order_costs')
            && pg_schema_has('erp_order_profit') && pg_schema_has('erp_cost_rates');
    }

    return $ready;
}

/**
 * The typed rates, kind => key => ['percent', 'fixed'].
 *
 * @return array
 */
function erp_order_profit_rates()
{
    $rates = array('payment' => array(), 'shipping' => array(), 'marketplace' => array());

    foreach ((array) db_items("SELECT kind, rate_key, percent, fixed FROM erp_cost_rates") as $row) {
        $rates[(string) $row['kind']][(string) $row['rate_key']] = array('percent' => (float) $row['percent'], 'fixed' => (int) $row['fixed']);
    }

    return $rates;
}

/**
 * Read everything erp_order_profit_compute() needs for one order.
 *
 * @param array $order  orders row
 * @param array $rates  erp_order_profit_rates()
 * @return array
 */
function erp_order_profit_input($order, $rates)
{
    $order_id = (int) $order['id'];

    $items = (array) db_items("SELECT order_items.id, order_items.product_id, order_items.quantity, order_items.price,
            order_items.tax_total, order_items.recurring_payment_period, order_items.recurring_start_date,
            products.start AS product_start
        FROM order_items
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE order_items.order_id = '" . $order_id . "' AND order_items.saved_for_later = 0
        ORDER BY order_items.id ASC");

    if (function_exists('erp_order_charged_items')) {
        $items = erp_order_charged_items($order, $items);
    }

    $moves = array();

    if (((int) $order['erp_invoice_id'] > 0) && function_exists('erp_stock_ready') && erp_stock_ready()) {
        $moves = (array) db_items("SELECT product_id, quantity, cost_total FROM erp_stock_moves
            WHERE invoice_id = '" . (int) $order['erp_invoice_id'] . "' AND kind = 'sale'");
    }

    $kept = array();
    $recorded = array();

    foreach ((array) db_items("SELECT kind, source, source_ref, amount FROM erp_order_costs WHERE order_id = '" . $order_id . "'") as $row) {
        if (in_array((string) $row['source'], erp_order_profit_pass_sources(), true)) {
            if (((string) $row['kind'] === 'product') && ((string) $row['source'] === 'average')) {
                $kept[(string) $row['source_ref']] = (int) $row['amount'];
            }
        } else {
            $recorded[] = array('kind' => (string) $row['kind'], 'source' => (string) $row['source'],
                'ref' => (string) $row['source_ref'], 'amount' => (int) $row['amount']);
        }
    }

    $product_ids = array();
    $components = array();
    $item_ids = array();

    foreach ($items as $item) {
        $product_ids[(int) $item['product_id']] = true;
        $item_ids[] = (int) $item['id'];
    }

    if (!empty($item_ids) && pg_schema_has('order_item_components')) {
        foreach ((array) db_items("SELECT order_item_id, product_id, quantity, unit_cost FROM order_item_components
            WHERE order_id = '" . $order_id . "'") as $part) {
            $components[(int) $part['order_item_id']][] = $part;
            $product_ids[(int) $part['product_id']] = true;
        }
    }

    $averages = array();
    $ids = array_filter(array_map('intval', array_keys($product_ids)));

    if (!empty($ids) && pg_schema_has('erp_product_costs')) {
        foreach ((array) db_items("SELECT product_id, avg_cost FROM erp_product_costs
            WHERE product_id IN (" . implode(',', $ids) . ")") as $row) {
            $averages[(int) $row['product_id']] = (int) $row['avg_cost'];
        }
    }

    $shipping_methods = array_map('intval', (array) db_values("SELECT DISTINCT shipping_method_id FROM ship_tos
        WHERE order_id = '" . $order_id . "' AND shipping_method_id > 0"));

    $marketplace = null;
    $account_id = pg_schema_has('marketplace_order_map')
        ? (int) db_value("SELECT account_id FROM marketplace_order_map WHERE order_id = '" . $order_id . "' LIMIT 1")
        : 0;

    if ($account_id > 0) {
        $commission = array();

        if (!empty($ids) && function_exists('waf_table_has_column') && waf_table_has_column('marketplace_product_map', 'commission')) {
            foreach ((array) db_items("SELECT product_id, commission FROM marketplace_product_map
                WHERE account_id = '" . $account_id . "' AND product_id IN (" . implode(',', $ids) . ")") as $row) {
                $commission[(int) $row['product_id']] = ($row['commission'] === null) ? null : (float) $row['commission'];
            }
        }

        $marketplace = array('account_id' => $account_id, 'commission' => $commission);
    }

    return array(
        'order' => $order,
        'items' => $items,
        'invoice_moves' => $moves,
        'kept' => $kept,
        'averages' => $averages,
        'components' => $components,
        'rates' => $rates,
        'shipping_methods' => $shipping_methods,
        'marketplace' => $marketplace,
        'recorded' => $recorded,
    );
}

/**
 * Write an order's computed rows: the pass's own cost rows replace the ones
 * it wrote before (rows other code recorded stay), then the profit row.
 *
 * @param int   $order_id
 * @param array $order    orders row
 * @param array $result   erp_order_profit_compute()
 * @return bool
 */
function erp_order_profit_save($order_id, $order, $result)
{
    $order_id = (int) $order_id;
    $now = time();
    $keep = array();

    foreach ($result['costs'] as $row) {
        $keep[] = $row['kind'] . '|' . $row['ref'];

        $ok = db_q("INSERT INTO erp_order_costs (order_id, kind, source, source_ref, amount, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE source = VALUES(source), amount = VALUES(amount), updated_at = VALUES(updated_at)",
            array($order_id, $row['kind'], $row['source'], $row['ref'], (int) $row['amount'], $now, $now));

        if ($ok === false) {
            return false;
        }
    }

    foreach ((array) db_items("SELECT id, kind, source_ref FROM erp_order_costs
        WHERE order_id = '" . $order_id . "' AND source IN ('" . implode("','", erp_order_profit_pass_sources()) . "')") as $row) {
        if (!in_array($row['kind'] . '|' . $row['source_ref'], $keep, true)) {
            db_q("DELETE FROM erp_order_costs WHERE id = ?", array((int) $row['id']));
        }
    }

    $profit = $result['profit'];

    // A margin past the column's range (a sale of a few kurus against a large
    // cost) is kept at its edge.
    if ($profit['margin'] !== null) {
        $profit['margin'] = max(-999999999.9, min(999999999.9, (float) $profit['margin']));
    }

    return db_q("INSERT INTO erp_order_profit (order_id, order_date, channel, revenue, cost_product, cost_payment,
            cost_marketplace, cost_shipping, cost_other, net, margin, state, basis, stale, computed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
        ON DUPLICATE KEY UPDATE order_date = VALUES(order_date), channel = VALUES(channel), revenue = VALUES(revenue),
            cost_product = VALUES(cost_product), cost_payment = VALUES(cost_payment),
            cost_marketplace = VALUES(cost_marketplace), cost_shipping = VALUES(cost_shipping),
            cost_other = VALUES(cost_other), net = VALUES(net), margin = VALUES(margin), state = VALUES(state),
            basis = VALUES(basis), stale = 0, computed_at = VALUES(computed_at)",
        array($order_id, (int) ($order['order_date'] ?? 0), $profit['channel'], $profit['revenue'], $profit['cost_product'],
            $profit['cost_payment'], $profit['cost_marketplace'], $profit['cost_shipping'], $profit['cost_other'],
            $profit['net'], $profit['margin'], $profit['state'], $profit['basis'], $now)) !== false;
}

/**
 * Compute one order now and write it, or remove its rows when it is no
 * longer a finished sale.
 *
 * @param array $order  orders row
 * @param array $rates  erp_order_profit_rates()
 * @return bool
 */
function erp_order_profit_order($order, $rates)
{
    if (!in_array((string) $order['status'], erp_order_billable_statuses(), true)) {
        return erp_order_profit_forget((int) $order['id']);
    }

    return erp_order_profit_save((int) $order['id'], $order, erp_order_profit_compute(erp_order_profit_input($order, $rates)));
}

/**
 * Ask the pass to compute an order again: for the code that records a real
 * cost (a gateway's fee, a label's price, a marketplace's deduction).
 *
 * @param int $order_id
 * @return bool
 */
function erp_order_profit_mark($order_id)
{
    if (!erp_order_profit_ready()) {
        return false;
    }

    return db_q("UPDATE erp_order_profit SET stale = 1 WHERE order_id = ?", array((int) $order_id)) !== false;
}

/**
 * Remove an order's cost and profit rows: the order was deleted, or it is no
 * longer a finished sale (cancelled). Real figures recorded for it go too:
 * they are kept for the order alone.
 *
 * @param int $order_id
 * @return bool
 */
function erp_order_profit_forget($order_id)
{
    if (!erp_order_profit_ready()) {
        return true;
    }

    $order_id = (int) $order_id;

    return (db_q("DELETE FROM erp_order_costs WHERE order_id = ?", array($order_id)) !== false)
        && (db_q("DELETE FROM erp_order_profit WHERE order_id = ?", array($order_id)) !== false);
}

/**
 * The orders columns the pass reads.
 *
 * @return string
 */
function erp_order_profit_order_columns()
{
    return "orders.id, orders.status, orders.order_date, orders.total, orders.gift_card_discount, orders.tax,
        orders.refunded_amount, orders.shipping, orders.payment_method, orders.erp_invoice_id";
}

/**
 * The profit pass: orders marked to compute again, then finished orders with
 * no profit row yet, then a rolling recheck of the computed ones (a refund,
 * an invoice, a cancellation changes the basis). Resumable: each part stops
 * when the budget runs out, and the recheck's place is kept in config_text.
 *
 * With the Accounting module off it reads and writes nothing.
 *
 * @param callable  $time_left  Seconds left in this run
 * @param bool|null $enabled    Whether the Accounting module is on; null reads ERP_ENABLED
 * @return array ['reason' => off|done|more|error, 'done' => int]
 */
function erp_order_profit_run($time_left, $enabled = null)
{
    if ($enabled === null) {
        $enabled = defined('ERP_ENABLED') && ERP_ENABLED;
    }

    if (!$enabled) {
        return array('reason' => 'off', 'done' => 0);
    }

    if (!erp_order_profit_ready() || !waf_table_has_column('orders', 'erp_invoice_id')) {
        return array('reason' => 'off', 'done' => 0);
    }

    $rates = erp_order_profit_rates();
    $columns = erp_order_profit_order_columns();
    $done = 0;

    $parts = array(
        // Marked by a writer of a real cost.
        "SELECT " . $columns . " FROM erp_order_profit
            INNER JOIN orders ON orders.id = erp_order_profit.order_id
            WHERE erp_order_profit.stale = 1 ORDER BY erp_order_profit.order_id LIMIT " . ERP_ORDER_PROFIT_BATCH,
        // Finished and never computed.
        "SELECT " . $columns . " FROM orders
            LEFT JOIN erp_order_profit ON erp_order_profit.order_id = orders.id
            WHERE " . erp_order_billable_sql('orders.status') . " AND erp_order_profit.order_id IS NULL
            ORDER BY orders.id LIMIT " . ERP_ORDER_PROFIT_BATCH,
    );

    foreach ($parts as $sql) {
        while (true) {
            $orders = db_items($sql);

            if (empty($orders)) {
                break;
            }

            foreach ($orders as $order) {
                if (!erp_order_profit_order($order, $rates)) {
                    return array('reason' => 'error', 'done' => $done);
                }

                $done++;

                if (call_user_func($time_left) <= 0) {
                    return array('reason' => 'more', 'done' => $done);
                }
            }
        }
    }

    // The recheck: computed orders whose figures changed since.
    $cursor = (int) pg_config_text(ERP_ORDER_PROFIT_CURSOR);

    while (true) {
        $rows = db_items("SELECT " . $columns . ", erp_order_profit.order_id AS profit_order_id,
                erp_order_profit.basis AS profit_basis
            FROM erp_order_profit
            LEFT JOIN orders ON orders.id = erp_order_profit.order_id
            WHERE erp_order_profit.order_id > '" . $cursor . "'
            ORDER BY erp_order_profit.order_id LIMIT " . ERP_ORDER_PROFIT_BATCH);

        if (empty($rows)) {
            pg_config_text_save(ERP_ORDER_PROFIT_CURSOR, '0');

            return array('reason' => 'done', 'done' => $done);
        }

        foreach ($rows as $row) {
            $cursor = (int) $row['profit_order_id'];

            if ((int) $row['id'] <= 0) {
                // The order is gone (deleted outside delete_order.php).
                erp_order_profit_forget($cursor);
                continue;
            }

            if (erp_order_profit_basis($row) !== (string) $row['profit_basis']) {
                if (!erp_order_profit_order($row, $rates)) {
                    return array('reason' => 'error', 'done' => $done);
                }

                $done++;
            }
        }

        pg_config_text_save(ERP_ORDER_PROFIT_CURSOR, (string) $cursor);

        if (call_user_func($time_left) <= 0) {
            return array('reason' => 'more', 'done' => $done);
        }
    }
}

/**
 * A payment service's fee in the order's own currency (the store's base), in
 * minor units: as answered when it is in that currency; otherwise converted
 * with erp_to_base() at the rate the service converted the payment at, else
 * at the day's rate. Null when it cannot be converted or is not a fee.
 *
 * @param array  $fee            pg_payment_answer(): fee, fee_currency, fee_rate
 * @param string $base_currency  the order's currency
 * @param float  $day_rate       base units per unit of fee_currency on the day; 0 when none
 * @return int|null
 */
function erp_order_profit_fee_amount($fee, $base_currency, $day_rate)
{
    $amount = (is_array($fee) && isset($fee['fee'])) ? $fee['fee'] : null;

    if (!is_int($amount) || ($amount < 0)) {
        return null;
    }

    $currency = strtoupper(trim((string) ($fee['fee_currency'] ?? '')));

    if (($currency === '') || ($currency === strtoupper(trim((string) $base_currency)))) {
        return $amount;
    }

    $rate = (float) ($fee['fee_rate'] ?? 0);

    if (!($rate > 0)) {
        $rate = (float) $day_rate;
    }

    if (!($rate > 0) || ($rate > ERP_MAX_EXCHANGE_RATE) || !erp_base_amount_fits($amount, $rate)) {
        return null;
    }

    return erp_to_base($amount, $rate);
}

/**
 * Record the fee a payment service kept for an order as its payment cost
 * (source gateway, ERP_ORDER_PROFIT_GATEWAY_REF), and ask the pass to compute
 * the order again: the real fee replaces the payment method's typed rate.
 * Nothing else is written: no receipt, till, bank or account move.
 *
 * With the Accounting module off it reads and writes nothing.
 *
 * @param int       $order_id
 * @param array     $fee      pg_payment_answer(): fee, fee_currency, fee_rate
 * @param bool|null $enabled  Whether the Accounting module is on; null reads ERP_ENABLED
 * @return bool  whether a row was written
 */
function erp_order_profit_record_fee($order_id, $fee, $enabled = null)
{
    if ($enabled === null) {
        $enabled = defined('ERP_ENABLED') && ERP_ENABLED;
    }

    $order_id = (int) $order_id;

    if (!$enabled || ($order_id <= 0) || !is_array($fee) || !isset($fee['fee']) || !is_int($fee['fee']) || !erp_order_profit_ready()) {
        return false;
    }

    $base = erp_base_currency();
    $currency = strtoupper(trim((string) ($fee['fee_currency'] ?? '')));
    $day_rate = 0.0;

    if (($currency !== '') && ($currency !== $base) && !((float) ($fee['fee_rate'] ?? 0) > 0)) {
        $rate = erp_fx_rate_for($currency, date('Y-m-d'));
        $day_rate = is_array($rate) ? (float) $rate['rate'] : 0.0;
    }

    $amount = erp_order_profit_fee_amount($fee, $base, $day_rate);

    if ($amount === null) {
        log_activity(pg_log_lang(array('string' => 'The payment fee of order #{var:1} ({var:2} minor units of {var:3}) could not be converted into {var:4}: no exchange rate. The typed rate stays.',
            'vars' => array($order_id, $fee['fee'], $currency, $base))));

        return false;
    }

    $now = time();

    $ok = db_q("INSERT INTO erp_order_costs (order_id, kind, source, source_ref, amount, created_at, updated_at)
        VALUES (?, 'payment', 'gateway', ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE source = VALUES(source), amount = VALUES(amount), updated_at = VALUES(updated_at)",
        array($order_id, ERP_ORDER_PROFIT_GATEWAY_REF, $amount, $now, $now)) !== false;

    if ($ok) {
        erp_order_profit_mark($order_id);
    }

    return $ok;
}

/* ---------------------------------------------------------------------------
   Label prices: the shipping cost a carrier label service charged
   --------------------------------------------------------------------------- */

/**
 * The source_ref of a label's cost row: 'label:' and the shipment_labels id,
 * so each label of an order (several addresses, a return label) is its own
 * row and making the same label's row again changes nothing. Pure.
 *
 * @param int $label_id shipment_labels.id
 * @return string
 */
function erp_order_profit_label_ref($label_id)
{
    return 'label:' . (int) $label_id;
}

/**
 * A label's price in minor units of the order's currency, or null when it
 * cannot be had. The service's decimal is read at two places, half away from
 * zero (erp_decimal_units()); a price in another currency is converted at
 * the rate of the label's day (erp_to_base()). Pure.
 *
 * @param array      $price    pg_label_price_normalize(): value, currency
 * @param string     $currency the order's currency (the unit of orders.total)
 * @param float|null $rate     units of the order's currency per one unit of
 *                             the price's; null or 0 when none is recorded
 * @return int|null
 */
function erp_order_profit_label_amount($price, $currency, $rate)
{
    if (!is_array($price) || !isset($price['value'], $price['currency'])
        || (preg_match('/^\d{1,12}(\.\d{1,6})?$/', (string) $price['value']) !== 1)) {
        return null;
    }

    $amount = (int) round(erp_decimal_units((string) $price['value'], 2));
    $currency = strtoupper(trim((string) $currency));

    if (($currency === '') || (strtoupper((string) $price['currency']) === $currency)) {
        return $amount;
    }

    if (($rate === null) || ((float) $rate <= 0)) {
        return null;
    }

    return erp_to_base($amount, (float) $rate);
}

/**
 * Record what a label cost as the order's shipping cost (kind shipping,
 * source label), replacing the rate typed for the order's shipping method,
 * and ask the pass to compute the order again. Nothing is read or written
 * while Accounting is off, and nothing beside the cost row: no receipt, till,
 * bank or account move.
 *
 * @param int   $order_id
 * @param int   $label_id shipment_labels.id
 * @param array $price    pg_label_answer() price
 * @param int   $now      unix time the label was made: the day of the rate
 * @return bool whether a row was written
 */
function erp_order_profit_label_record($order_id, $label_id, $price, $now)
{
    if (!defined('ERP_ENABLED') || !ERP_ENABLED || !erp_order_profit_ready() || empty($price) || ((int) $label_id <= 0)) {
        return false;
    }

    $currency = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';
    $rate = null;

    if (($currency !== '') && (strtoupper((string) $price['currency']) !== strtoupper($currency)) && function_exists('pg_currency_rate')) {
        $found = pg_currency_rate((string) $price['currency'], date('Y-m-d', (int) $now), $currency);
        $rate = is_array($found) ? (float) $found['rate'] : null;
    }

    $amount = erp_order_profit_label_amount($price, $currency, $rate);

    if ($amount === null) {
        return false;
    }

    $ok = db_q("INSERT INTO erp_order_costs (order_id, kind, source, source_ref, amount, created_at, updated_at)
        VALUES (?, 'shipping', 'label', ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE source = VALUES(source), amount = VALUES(amount), updated_at = VALUES(updated_at)",
        array((int) $order_id, erp_order_profit_label_ref($label_id), $amount, (int) $now, (int) $now)) !== false;

    if ($ok) {
        erp_order_profit_mark($order_id);
    }

    return $ok;
}

/**
 * Remove a cancelled label's cost row and ask the pass to compute the order
 * again: with no label row left, the rate typed for its method comes back.
 * Nothing is read or written while Accounting is off.
 *
 * @param int $order_id
 * @param int $label_id shipment_labels.id
 * @return bool
 */
function erp_order_profit_label_forget($order_id, $label_id)
{
    if (!defined('ERP_ENABLED') || !ERP_ENABLED || !erp_order_profit_ready()) {
        return false;
    }

    $ok = db_q("DELETE FROM erp_order_costs WHERE order_id = ? AND kind = 'shipping' AND source = 'label' AND source_ref = ?",
        array((int) $order_id, erp_order_profit_label_ref($label_id))) !== false;

    if ($ok) {
        erp_order_profit_mark($order_id);
    }

    return $ok;
}
