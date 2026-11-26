<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Product insights: the figures of Edit Product's Performance card (units
 * sold, revenue, orders, views, conversion, returns, margin, last sale, the
 * chart of units sold) and its "Carts and interest" part (left and open
 * carts, saved lines, favourites, waiting for stock, rating), read for one
 * product and one period, and kept ten minutes in the person's session.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module loaded where it is needed: the panel action product_insights
// (includes/panel/commerce.php). Read only: nothing here writes a table.
//
// Sales are the product's own lines (order_items.product_id) on orders whose
// status is complete or exported, by orders.order_date; a product sold inside
// a bundle counts by its own line only. Every read starts from the
// order_items.product_id index and reaches orders by its primary key, so it
// reads the product's lines and no other.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How long a read is kept, in seconds. */
const PG_PRODUCT_INSIGHTS_KEEP = 600;

/** The most reads one session keeps; the oldest goes first. */
const PG_PRODUCT_INSIGHTS_KEPT_MAX = 20;

/** The fewest views a conversion is shown for. */
const PG_PRODUCT_INSIGHTS_MIN_VIEWS = 20;

/** The years the All chart shows. */
const PG_PRODUCT_INSIGHTS_YEARS = 10;

/** The most orders the margin is worked out over; past it the margin is left out. */
const PG_PRODUCT_INSIGHTS_MARGIN_MAX_ORDERS = 20000;

/**
 * The periods the card offers.
 *
 * @return string[]
 */
function pg_product_insights_periods()
{
    return array('30d', '12m', 'all');
}

/**
 * A period name as asked, or 30d. Pure.
 *
 * @param mixed $value
 * @return string
 */
function pg_product_insights_period_in($value)
{
    return (is_string($value) && in_array($value, pg_product_insights_periods(), true)) ? $value : '30d';
}

/**
 * The bounds of a period ending now, in PHP's time zone (the site's). Pure.
 *
 * 30d: the 30 days ending today, by day; 12m: the 12 months ending with this
 * one, by month; all: no start, by year over the last ten. The earlier period
 * is the one of the same length right before (none for all).
 *
 * The chart's buckets are the unix starts of each day, month or year; a sale
 * at or after a bucket's start and before the next one's is in it.
 *
 * @param string $period  a pg_product_insights_periods() name
 * @param int    $now
 * @return array ['period', 'start' (0 for all), 'prev_start' (0 for none),
 *                'end', 'unit' (day|month|year), 'buckets' => list of unix starts]
 */
function pg_product_insights_window($period, $now)
{
    $now = (int) $now;
    $period = pg_product_insights_period_in($period);
    $today = (string) date('Y-m-d', $now);
    $buckets = array();

    if ($period === '30d') {
        for ($i = 29; $i >= 0; $i--) {
            $buckets[] = (int) strtotime($today . ' 00:00:00 -' . $i . ' days');
        }

        return array('period' => $period, 'start' => $buckets[0],
            'prev_start' => (int) strtotime($today . ' 00:00:00 -59 days'), 'end' => $now,
            'unit' => 'day', 'buckets' => $buckets);
    }

    if ($period === '12m') {
        $month = (string) date('Y-m-01', $now);

        for ($i = 11; $i >= 0; $i--) {
            $buckets[] = (int) strtotime($month . ' 00:00:00 -' . $i . ' months');
        }

        return array('period' => $period, 'start' => $buckets[0],
            'prev_start' => (int) strtotime($month . ' 00:00:00 -23 months'), 'end' => $now,
            'unit' => 'month', 'buckets' => $buckets);
    }

    $year = (int) date('Y', $now);

    for ($i = PG_PRODUCT_INSIGHTS_YEARS - 1; $i >= 0; $i--) {
        $buckets[] = (int) mktime(0, 0, 0, 1, 1, $year - $i);
    }

    return array('period' => 'all', 'start' => 0, 'prev_start' => 0, 'end' => $now,
        'unit' => 'year', 'buckets' => $buckets);
}

/**
 * The bounds the sales read groups by with MySQL's INTERVAL(): the earlier
 * period's start (when there is one), then the chart's buckets. INTERVAL()
 * answers 0 for a date before the first bound and i for one at or after
 * bound i but before bound i + 1. Pure.
 *
 * @param array $window  pg_product_insights_window()
 * @return int[]
 */
function pg_product_insights_bounds($window)
{
    $bounds = array();

    if ((int) $window['prev_start'] > 0) {
        $bounds[] = (int) $window['prev_start'];
    }

    foreach ((array) $window['buckets'] as $start) {
        $bounds[] = (int) $start;
    }

    return $bounds;
}

/**
 * The sales figures folded from the grouped read. Pure.
 *
 * @param array $window  pg_product_insights_window()
 * @param array $rows    list of ['slot' => INTERVAL() index, 'units', 'revenue', 'orders']
 * @return array ['units', 'revenue', 'orders', 'before' => [units, revenue, orders]|null,
 *                'chart' => list of ['start', 'units']]
 */
function pg_product_insights_sales_fold($window, $rows)
{
    $has_before = ((int) $window['prev_start'] > 0);
    $first_bucket = $has_before ? 2 : 1;
    $figures = array('units' => 0, 'revenue' => 0, 'orders' => 0);
    $before = $has_before ? $figures : null;
    $chart = array();

    foreach (array_values((array) $window['buckets']) as $index => $start) {
        $chart[$index] = array('start' => (int) $start, 'units' => 0);
    }

    foreach ((array) $rows as $row) {
        $slot = (int) $row['slot'];
        $units = (int) $row['units'];
        $revenue = (int) $row['revenue'];
        $orders = (int) $row['orders'];

        if ($has_before && ($slot === 1)) {
            $before['units'] += $units;
            $before['revenue'] += $revenue;
            $before['orders'] += $orders;
            continue;
        }

        // Before every bound: only All reads there (sales older than its
        // chart), and they count in its figures.
        if (($slot < $first_bucket) && $has_before) {
            continue;
        }

        $figures['units'] += $units;
        $figures['revenue'] += $revenue;
        $figures['orders'] += $orders;

        if ($slot >= $first_bucket) {
            $chart[$slot - $first_bucket]['units'] += $units;
        }
    }

    return $figures + array('before' => $before, 'chart' => array_values($chart));
}

/**
 * The change of a figure against the earlier period, a percent with one
 * decimal, or null when there is no earlier period or its value is zero.
 * Pure.
 *
 * @param int|float      $now
 * @param int|float|null $before
 * @return float|null
 */
function pg_product_insights_change($now, $before)
{
    if (($before === null) || ((float) $before == 0.0)) {
        return null;
    }

    return round((((float) $now - (float) $before) * 100) / (float) $before, 1);
}

/**
 * A share as a percent with one decimal, or null without a whole. Pure.
 *
 * @param int|float $part
 * @param int|float $whole
 * @return float|null
 */
function pg_product_insights_share($part, $whole)
{
    return ((float) $whole > 0) ? round(((float) $part * 100) / (float) $whole, 1) : null;
}

/**
 * Conversion: orders over views, a percent with one decimal, shown only from
 * 20 views. Pure.
 *
 * @param int      $orders
 * @param int|null $views  null when views are not known
 * @return float|null
 */
function pg_product_insights_conversion($orders, $views)
{
    if (($views === null) || ((int) $views < PG_PRODUCT_INSIGHTS_MIN_VIEWS)) {
        return null;
    }

    return pg_product_insights_share((int) $orders, (int) $views);
}

/**
 * Whether the content rollup holds a period: its first day is on or before
 * the period's first day. All is held from whatever day the rollup starts.
 * Pure.
 *
 * @param string|null $first_day  MIN(stat_date), null for an empty rollup
 * @param int         $start      the period's start, 0 for all
 * @return bool
 */
function pg_product_insights_views_held($first_day, $start)
{
    if (($first_day === null) || ((string) $first_day === '')) {
        return false;
    }

    return ((int) $start <= 0) || ((string) $first_day <= (string) date('Y-m-d', (int) $start));
}

/**
 * The key a read is kept under. Pure.
 *
 * @param int    $product_id
 * @param string $period
 * @param bool   $margin  whether the read holds the margin (the person may see it)
 * @return string
 */
function pg_product_insights_keep_key($product_id, $period, $margin)
{
    return (int) $product_id . '|' . pg_product_insights_period_in($period) . '|' . ($margin ? 'm' : '-');
}

/**
 * A kept read, or null when there is none under ten minutes old. Pure.
 *
 * @param array  $kept  key => ['at' => unix time, 'data' => array]
 * @param string $key
 * @param int    $now
 * @return array|null
 */
function pg_product_insights_kept($kept, $key, $now)
{
    $row = is_array($kept) ? ($kept[$key] ?? null) : null;

    if (!is_array($row) || !isset($row['at'], $row['data']) || !is_array($row['data'])) {
        return null;
    }

    $age = (int) $now - (int) $row['at'];

    return (($age >= 0) && ($age < PG_PRODUCT_INSIGHTS_KEEP)) ? $row['data'] : null;
}

/**
 * The kept reads with one added: stale ones dropped, the oldest first when
 * past the limit. Pure.
 *
 * @param array  $kept
 * @param string $key
 * @param array  $data
 * @param int    $now
 * @return array
 */
function pg_product_insights_keep($kept, $key, $data, $now)
{
    $out = array();

    foreach ((is_array($kept) ? $kept : array()) as $name => $row) {
        if (($name !== $key) && (pg_product_insights_kept($kept, (string) $name, $now) !== null)) {
            $out[(string) $name] = $row;
        }
    }

    $out[$key] = array('at' => (int) $now, 'data' => $data);

    uasort($out, function ($a, $b) {
        return (int) $a['at'] - (int) $b['at'];
    });

    while (count($out) > PG_PRODUCT_INSIGHTS_KEPT_MAX) {
        array_shift($out);
    }

    return $out;
}

/**
 * The orders that sell: pg_customer_value_order_counts() in SQL, so the card
 * counts what the Customers report counts. Complete or exported, not
 * cancelled, not refunded in full; a column not yet migrated is left out of
 * the test. Pure.
 *
 * @param bool $has_cancelled_at     orders.cancelled_at exists
 * @param bool $has_refunded_amount  orders.refunded_amount exists
 * @return string  SQL for a WHERE
 */
function pg_product_insights_counted_sql($has_cancelled_at, $has_refunded_amount)
{
    return "orders.status IN ('complete', 'exported')"
        . ($has_cancelled_at ? " AND COALESCE(orders.cancelled_at, 0) = 0" : '')
        . ($has_refunded_amount ? " AND (orders.refunded_amount <= 0 OR orders.refunded_amount < orders.total)" : '');
}

/**
 * The conditions of the product's sales lines: product, a counted order
 * (pg_product_insights_counted_sql()), an active line and an optional date
 * floor, over order_items joined to orders.
 *
 * @param int $product_id
 * @param int $from  unix time, 0 for none
 * @return string  SQL for a WHERE
 */
function pg_product_insights_sales_where($product_id, $from)
{
    return "order_items.product_id = '" . (int) $product_id . "'
            AND " . pg_product_insights_counted_sql(pg_schema_has('orders', 'cancelled_at'), pg_schema_has('orders', 'refunded_amount'))
        . pg_order_item_active_sql('order_items')
        . (((int) $from > 0) ? " AND orders.order_date >= '" . (int) $from . "'" : '');
}

/**
 * The FROM and WHERE of the product's sales lines. Pure.
 *
 * @param int $product_id
 * @param int $from  unix time, 0 for none
 * @return string
 */
function pg_product_insights_sales_from($product_id, $from)
{
    return "FROM order_items
        INNER JOIN orders ON orders.id = order_items.order_id
        WHERE " . pg_product_insights_sales_where($product_id, $from);
}

/**
 * The orders whose amounts are in the store's base currency:
 * pg_customer_value_order_unit() in SQL. A web order always is (its
 * currency_code is only what the visitor was shown); a marketplace order is
 * when its code is empty or the base one. Pure.
 *
 * @param string $base      BASE_CURRENCY_CODE
 * @param bool   $has_type  orders.type exists
 * @return string  SQL for a condition
 */
function pg_product_insights_base_unit_sql($base, $has_type)
{
    if (!$has_type) {
        return '1 = 1';
    }

    $base = (string) preg_replace('/[^A-Z]/', '', strtoupper((string) $base));

    return "(orders.type <> 'marketplace' OR TRIM(COALESCE(orders.currency_code, '')) = ''"
        . " OR UPPER(TRIM(orders.currency_code)) = '" . $base . "')";
}

/**
 * Units, revenue and orders of the period, the earlier period and the chart.
 * Revenue sums only lines of orders in the base currency
 * (pg_product_insights_base_unit_sql()); units and orders count every
 * order.
 *
 * @param int   $product_id
 * @param array $window  pg_product_insights_window()
 * @return array|null  pg_product_insights_sales_fold(), null when the read failed
 */
function pg_product_insights_sales($product_id, $window)
{
    $bounds = pg_product_insights_bounds($window);
    $slot = "INTERVAL(orders.order_date, " . implode(', ', array_map('intval', $bounds)) . ")";
    $from = ((int) $window['prev_start'] > 0) ? (int) $window['prev_start'] : 0;

    $result = db_q("SELECT " . $slot . " AS slot, SUM(order_items.quantity) AS units,
            SUM(CASE WHEN " . pg_product_insights_base_unit_sql(defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', pg_schema_has('orders', 'type')) . "
                THEN CAST(order_items.price AS SIGNED) * CAST(order_items.quantity AS SIGNED) ELSE 0 END) AS revenue,
            COUNT(DISTINCT order_items.order_id) AS orders
        " . pg_product_insights_sales_from($product_id, $from) . "
        GROUP BY slot");

    if (!$result) {
        return null;
    }

    $rows = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return pg_product_insights_sales_fold($window, $rows);
}

/**
 * The newest order date of the product's sales, whatever the period.
 *
 * @param int $product_id
 * @return int  unix time, 0 for never
 */
function pg_product_insights_last_sale($product_id)
{
    return (int) db_value("SELECT MAX(orders.order_date) " . pg_product_insights_sales_from($product_id, 0));
}

/**
 * The product's views in the period and the earlier one, from the content
 * rollup; null for each the rollup does not hold, both null with visitor
 * statistics off.
 *
 * @param int   $product_id
 * @param array $window
 * @return array ['views' => int|null, 'before' => int|null, 'since' => Y-m-d of the rollup's first day]
 */
function pg_product_insights_views($product_id, $window)
{
    $none = array('views' => null, 'before' => null, 'since' => '');

    if (!(defined('VISITOR_TRACKING') && VISITOR_TRACKING) || !function_exists('pg_visitor_rollup_ready')
        || !pg_visitor_rollup_ready()) {
        return $none;
    }

    $first_day = db_value("SELECT MIN(stat_date) FROM visitor_content_hourly");
    $first_day = ($first_day === null || $first_day === false) ? null : (string) $first_day;

    if (!pg_product_insights_views_held($first_day, $window['start'])) {
        return $none;
    }

    $now_sql = ((int) $window['start'] > 0) ? "stat_date >= '" . e((string) date('Y-m-d', (int) $window['start'])) . "'" : "1 = 1";
    $before_held = ((int) $window['prev_start'] > 0) && pg_product_insights_views_held($first_day, $window['prev_start']);
    $before_sql = $before_held
        ? "stat_date >= '" . e((string) date('Y-m-d', (int) $window['prev_start'])) . "' AND NOT (" . $now_sql . ")"
        : "1 = 0";

    $row = db_item("SELECT SUM(CASE WHEN " . $now_sql . " THEN views ELSE 0 END) AS now_views,
            SUM(CASE WHEN " . $before_sql . " THEN views ELSE 0 END) AS before_views
        FROM visitor_content_hourly
        WHERE item_type = 'product' AND item_id = '" . (int) $product_id . "'");

    if (!is_array($row)) {
        return $none;
    }

    return array(
        'views' => (int) $row['now_views'],
        'before' => $before_held ? (int) $row['before_views'] : null,
        'since' => (string) $first_day,
    );
}

/**
 * The returns whose goods count as back: pg_return_holds_goods() in SQL.
 * Every open status and refunded; a closed one only when something was sent,
 * received or paid back (a request withdrawn before that is no return);
 * rejected never. Pure.
 *
 * @return string  SQL for a WHERE
 */
function pg_product_insights_returns_held_sql()
{
    return "(order_returns.status IN ('requested', 'approved', 'shipped', 'received', 'refunded')
            OR (order_returns.status = 'closed' AND (order_returns.shipped_at > 0 OR order_returns.received_at > 0 OR order_returns.refunded_at > 0)))";
}

/**
 * Units of the product's lines asked back, for orders of the period: the
 * returns that hold goods (pg_product_insights_returns_held_sql()).
 *
 * @param int   $product_id
 * @param array $window
 * @return int|null  null when returns are not installed
 */
function pg_product_insights_returns($product_id, $window)
{
    if (!function_exists('pg_returns_ready') || !pg_returns_ready()) {
        return null;
    }

    return (int) db_value("SELECT SUM(order_return_items.quantity)
        FROM order_items
        INNER JOIN orders ON orders.id = order_items.order_id
        INNER JOIN order_return_items ON order_return_items.order_item_id = order_items.id
        INNER JOIN order_returns ON order_returns.id = order_return_items.return_id
        WHERE " . pg_product_insights_sales_where($product_id, (int) $window['start']) . "
            AND " . pg_product_insights_returns_held_sql());
}

/**
 * The cart figures: left and open carts holding an active line of the
 * product and their units, and carts holding it as a saved line.
 *
 * Left: an incomplete order last changed at least 6 hours ago and, unless
 * the period is All, in the period (pg_cart_recovery_is_left()'s idle rule).
 * Open: last changed under 6 hours ago.
 *
 * @param int   $product_id
 * @param array $window
 * @param int   $now
 * @return array ['left_carts', 'left_units', 'open_carts', 'open_units', 'saved_carts' (null when off)]
 */
function pg_product_insights_carts($product_id, $window, $now)
{
    $idle = (int) $now - PG_CART_RECOVERY_LEFT_AFTER;
    $from = (int) $window['start'];
    $saved_column = pg_schema_has('order_items', 'saved_for_later');
    $saved_on = $saved_column && defined('ECOMMERCE_SAVE_FOR_LATER') && ECOMMERCE_SAVE_FOR_LATER;
    $active = $saved_column ? "order_items.saved_for_later = 0" : "1 = 1";
    $left = $active . " AND orders.last_modified_timestamp > 0 AND orders.last_modified_timestamp <= '" . $idle . "'"
        . (($from > 0) ? " AND orders.last_modified_timestamp >= '" . $from . "'" : '');
    $open = $active . " AND orders.last_modified_timestamp > '" . $idle . "'";

    $row = db_item("SELECT
            COUNT(DISTINCT CASE WHEN " . $left . " THEN orders.id END) AS left_carts,
            SUM(CASE WHEN " . $left . " THEN order_items.quantity ELSE 0 END) AS left_units,
            COUNT(DISTINCT CASE WHEN " . $open . " THEN orders.id END) AS open_carts,
            SUM(CASE WHEN " . $open . " THEN order_items.quantity ELSE 0 END) AS open_units"
            . ($saved_on ? ", COUNT(DISTINCT CASE WHEN order_items.saved_for_later = 1 THEN orders.id END) AS saved_carts" : "") . "
        FROM order_items
        INNER JOIN orders ON orders.id = order_items.order_id
        WHERE order_items.product_id = '" . (int) $product_id . "' AND orders.status = 'incomplete'");

    $row = is_array($row) ? $row : array();

    return array(
        'left_carts' => (int) ($row['left_carts'] ?? 0),
        'left_units' => (int) ($row['left_units'] ?? 0),
        'open_carts' => (int) ($row['open_carts'] ?? 0),
        'open_units' => (int) ($row['open_units'] ?? 0),
        'saved_carts' => $saved_on ? (int) ($row['saved_carts'] ?? 0) : null,
    );
}

/**
 * Today's interest: favourites (different owners), back-in-stock requests
 * not yet sent, and the rating; null for each whose feature is not installed.
 *
 * @param int $product_id
 * @return array ['favourites', 'waiting', 'rating_avg', 'rating_count']
 */
function pg_product_insights_interest($product_id)
{
    $product_id = (int) $product_id;
    $out = array('favourites' => null, 'waiting' => null, 'rating_avg' => null, 'rating_count' => null);

    if (function_exists('pg_wishlist_ready') && pg_wishlist_ready()) {
        $out['favourites'] = (int) db_value("SELECT COUNT(DISTINCT user_id, cookie_token) FROM wishlist_items
            WHERE product_id = '" . $product_id . "'");
    }

    if (function_exists('pg_stock_notify_ready') && pg_stock_notify_ready()) {
        $out['waiting'] = (int) db_value("SELECT COUNT(*) FROM stock_notify
            WHERE product_id = '" . $product_id . "' AND sent_at = 0");
    }

    if (function_exists('pg_reviews_ready') && pg_reviews_ready()) {
        $rating = db_item("SELECT rating_avg, rating_count FROM products WHERE id = '" . $product_id . "'");

        if (is_array($rating)) {
            $out['rating_count'] = (int) $rating['rating_count'];
            $out['rating_avg'] = ($out['rating_count'] > 0) ? round((float) $rating['rating_avg'], 1) : null;
        }
    }

    return $out;
}

/**
 * The product's margin in the period from the order profit rows: each order
 * split over its lines as Accounting › Profitability splits it
 * (erp_profitability_split()), the product's lines summed, the margin
 * worked out on the sums. Called only for a person who may see the Profit
 * card (erp_order_profit_shown()).
 *
 * @param int   $product_id
 * @param array $window
 * @return array|null ['margin' => float, 'state' => final|estimate|incomplete], null when no cost is known
 */
function pg_product_insights_margin($product_id, $window)
{
    $order_ids = db_values("SELECT DISTINCT order_items.order_id
        " . pg_product_insights_sales_from($product_id, (int) $window['start']) . "
        LIMIT " . (PG_PRODUCT_INSIGHTS_MARGIN_MAX_ORDERS + 1));

    if (!is_array($order_ids) || empty($order_ids) || (count($order_ids) > PG_PRODUCT_INSIGHTS_MARGIN_MAX_ORDERS)) {
        return null;
    }

    $rows = array();

    foreach (array_chunk(array_map('intval', $order_ids), 500) as $chunk) {
        $in = implode(',', $chunk);
        $profits = (array) db_items("SELECT erp_order_profit.order_id, erp_order_profit.order_date, erp_order_profit.channel,
                erp_order_profit.state, erp_order_profit.revenue, erp_order_profit.cost_product,
                erp_order_profit.cost_payment, erp_order_profit.cost_marketplace, erp_order_profit.cost_shipping,
                erp_order_profit.cost_other, erp_order_profit.net
            FROM erp_order_profit " . erp_order_profit_counted_join() . "
            WHERE erp_order_profit.order_id IN (" . $in . ")");

        if (empty($profits)) {
            continue;
        }

        $items = array();

        foreach ((array) db_items("SELECT order_items.id, order_items.order_id, order_items.product_id, order_items.quantity,
                order_items.price, order_items.tax_total, order_items.recurring_payment_period,
                order_items.recurring_start_date, products.start AS product_start
            FROM order_items
            LEFT JOIN products ON products.id = order_items.product_id
            WHERE order_items.order_id IN (" . $in . ")" . pg_order_item_active_sql('order_items') . "
            ORDER BY order_items.id ASC") as $item) {
            $items[(int) $item['order_id']][] = $item;
        }

        $item_costs = array();

        foreach ((array) db_items("SELECT order_id, source_ref, amount FROM erp_order_costs
            WHERE order_id IN (" . $in . ") AND kind = 'product' AND source_ref LIKE 'item:%'") as $cost) {
            $item_costs[(int) $cost['order_id']][(int) substr((string) $cost['source_ref'], 5)] = (int) $cost['amount'];
        }

        foreach ($profits as $profit) {
            $order_id = (int) $profit['order_id'];
            $lines = erp_profitability_lines((int) $profit['order_date'], $items[$order_id] ?? array());

            foreach (erp_profitability_split($profit, $lines, $item_costs[$order_id] ?? array()) as $row) {
                if ((int) $row['product_id'] === (int) $product_id) {
                    $rows[] = $row;
                }
            }
        }
    }

    return pg_product_insights_margin_fold($rows);
}

/**
 * The margin of the product's split rows, worked out on the sums. Pure.
 *
 * @param array $rows  erp_profitability_split() rows of the product
 * @return array|null ['margin', 'state'], null without revenue or with no product cost known
 */
function pg_product_insights_margin_fold($rows)
{
    $revenue = 0;
    $net = 0;
    $cost = 0;
    $state = 'final';

    foreach ((array) $rows as $row) {
        $revenue += (int) $row['revenue'];
        $net += (int) $row['net'];
        $cost += (int) $row['cost_product'];

        if ((string) $row['state'] === 'incomplete') {
            $state = 'incomplete';
        } elseif (((string) $row['state'] === 'estimate') && ($state === 'final')) {
            $state = 'estimate';
        }
    }

    if (($revenue === 0) || ($cost === 0)) {
        return null;
    }

    return array('margin' => erp_order_profit_margin($net, $revenue), 'state' => $state);
}

/**
 * Whether this person sees the margin: where the order's Profit card shows.
 *
 * @param array $user
 * @return bool
 */
function pg_product_insights_margin_shown($user)
{
    if (!(defined('ERP_ENABLED') && ERP_ENABLED) || !is_array($user)
        || !(((int) ($user['role'] ?? 3) < 3) || !empty($user['manage_erp']))) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_profit_view.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/profitability.php');

    return erp_order_profit_shown($user);
}

/**
 * The card's figures, assembled. Pure.
 *
 * @param array      $window    pg_product_insights_window()
 * @param array      $sales     pg_product_insights_sales_fold()
 * @param int        $last_sale
 * @param array      $views     pg_product_insights_views()
 * @param int|null   $returns
 * @param array|null $margin    pg_product_insights_margin_fold(); false when the person may not see it
 * @param array      $carts     pg_product_insights_carts()
 * @param array      $interest  pg_product_insights_interest()
 * @param int        $now
 * @return array
 */
function pg_product_insights_figures($window, $sales, $last_sale, $views, $returns, $margin, $carts, $interest, $now)
{
    $before = $sales['before'];
    $view_count = $views['views'];

    $figures = array(
        'units' => (int) $sales['units'],
        'revenue' => (int) $sales['revenue'],
        'orders' => (int) $sales['orders'],
        'views' => $view_count,
        'views_since' => ($view_count !== null) ? (string) $views['since'] : '',
        'conversion' => pg_product_insights_conversion((int) $sales['orders'], $view_count),
        'returns' => $returns,
        'returns_share' => ($returns === null) ? null : pg_product_insights_share((int) $returns, (int) $sales['units']),
        'last_sale' => (int) $last_sale,
        'change' => array(
            'units' => pg_product_insights_change($sales['units'], ($before === null) ? null : $before['units']),
            'revenue' => pg_product_insights_change($sales['revenue'], ($before === null) ? null : $before['revenue']),
            'orders' => pg_product_insights_change($sales['orders'], ($before === null) ? null : $before['orders']),
            'views' => ($view_count === null) ? null : pg_product_insights_change($view_count, $views['before']),
        ),
    );

    if ($margin !== false) {
        $figures['margin'] = is_array($margin) ? $margin['margin'] : null;
        $figures['margin_state'] = is_array($margin) ? $margin['state'] : '';
    }

    return array(
        'period' => (string) $window['period'],
        'unit' => (string) $window['unit'],
        'read_at' => (int) $now,
        'keep' => PG_PRODUCT_INSIGHTS_KEEP,
        'no_sales' => ((int) $sales['units'] === 0) && ((int) $sales['orders'] === 0),
        'figures' => $figures,
        'chart' => $sales['chart'],
        'carts' => $carts,
        'interest' => $interest,
    );
}

/**
 * Reads the card's figures for a product and a period.
 *
 * @param int    $product_id
 * @param string $period
 * @param bool   $margin  whether the person may see the margin
 * @param int    $now
 * @return array|null  null when a read failed
 */
function pg_product_insights_read($product_id, $period, $margin, $now)
{
    $window = pg_product_insights_window($period, $now);
    $sales = pg_product_insights_sales($product_id, $window);

    if ($sales === null) {
        return null;
    }

    return pg_product_insights_figures(
        $window,
        $sales,
        pg_product_insights_last_sale($product_id),
        pg_product_insights_views($product_id, $window),
        pg_product_insights_returns($product_id, $window),
        $margin ? pg_product_insights_margin($product_id, $window) : false,
        pg_product_insights_carts($product_id, $window, $now),
        pg_product_insights_interest($product_id),
        $now
    );
}

/**
 * The stock pill of Edit Product's head, or null when the product does not
 * track its inventory. Pure.
 *
 * @param array $product products row: inventory, inventory_quantity, backorder
 * @return array|null array(text, tone)
 */
function pg_product_insights_stock_pill($product)
{
    if (empty($product['inventory'])) {
        return null;
    }

    $quantity = (int) ($product['inventory_quantity'] ?? 0);

    if ($quantity > 0) {
        return array(lang(array('string' => 'In stock · {var:1}', 'vars' => array(pg_format_number($quantity, 0)))), 'teal');
    }

    return !empty($product['backorder'])
        ? array(lang('Backorder'), 'amber')
        : array(lang('Out of stock'), 'rose');
}

/**
 * The pills of Edit Product's head: Enabled or Disabled, then the stock
 * pill. Pure.
 *
 * @param array $product products row
 * @return array list of array(text, tone)
 */
function pg_product_insights_head_pills($product)
{
    $pills = array(!empty($product['enabled']) ? array(lang('Enabled'), 'teal') : array(lang('Disabled'), 'gray'));
    $stock = pg_product_insights_stock_pill($product);

    if ($stock !== null) {
        $pills[] = $stock;
    }

    return $pills;
}

/**
 * A change against the earlier period as the card's note: the arrow, the
 * percent and its tone class. Pure.
 *
 * @param float|null $change pg_product_insights_change()
 * @return array array(text, class); text '' when there is no change line
 */
function pg_product_insights_change_note($change)
{
    if ($change === null) {
        return array('', '');
    }

    $change = (float) $change;
    $percent = lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number(abs($change), (floor(abs($change)) == abs($change)) ? 0 : 1))));

    if ($change > 0) {
        return array('↑ ' . $percent, 'is-good');
    }

    return ($change < 0) ? array('↓ ' . $percent, 'is-bad') : array($percent, '');
}

/**
 * A chart bucket's name: a day, a month or a year. Pure.
 *
 * @param int    $start the bucket's first second
 * @param string $unit  day, month or year
 * @return string
 */
function pg_product_insights_bucket_name($start, $unit)
{
    $start = (int) $start;

    if ($unit === 'year') {
        return date('Y', $start);
    }

    if ($unit === 'month') {
        return lang(date('M', $start)) . ' ' . date('Y', $start);
    }

    return date('j', $start) . ' ' . lang(date('M', $start));
}

/**
 * The figure cells of the Performance card, in the board's order; a figure
 * the read left out has no cell. Pure.
 *
 * @param array  $data   pg_product_insights_figures()
 * @param string $symbol the store's currency symbol as stored (HTML)
 * @param int    $now
 * @return string
 */
function pg_product_insights_figure_cells($data, $symbol, $now)
{
    $figures = (array) $data['figures'];
    $change = (array) ($figures['change'] ?? array());
    $number = function ($value) {
        return h(pg_format_number((int) $value, 0));
    };
    $cell = function ($label, $value, $key) use ($change) {
        $note = pg_product_insights_change_note($change[$key] ?? null);

        return pg_figure_grid_cell($label, $value, $note[0], $note[1]);
    };

    $cells = $cell(lang('Units sold'), $number($figures['units']), 'units')
        . $cell(lang('Revenue'), pg_format_money(((int) $figures['revenue']) / 100, $symbol), 'revenue')
        . $cell(lang('Orders'), $number($figures['orders']), 'orders');

    if ($figures['views'] !== null) {
        $cells .= $cell(lang('Views'), $number($figures['views']), 'views');

        $cells .= ($figures['conversion'] !== null)
            ? pg_figure_grid_cell(lang('Conversion'), h(lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number($figures['conversion'], 1))))), lang(array('string' => '{var:1} orders per 100 views', 'vars' => array(pg_format_number($figures['conversion'], 1)))))
            : pg_figure_grid_cell(lang('Conversion'), '—', lang(array('string' => 'Shown from {var:1} views', 'vars' => array(PG_PRODUCT_INSIGHTS_MIN_VIEWS))));
    }

    if ($figures['returns'] !== null) {
        $cells .= pg_figure_grid_cell(lang('Returns'), h(lang(array('string' => '{var:1} units', 'vars' => array(pg_format_number((int) $figures['returns'], 0))))),
            ($figures['returns_share'] !== null) ? lang(array('string' => '{var:1}% of units sold', 'vars' => array(pg_format_number($figures['returns_share'], 1)))) : '');
    }

    if (array_key_exists('margin', $figures) && ($figures['margin'] !== null)) {
        $states = array('estimate' => lang('Estimate: some costs are estimated'), 'incomplete' => lang('Some costs are missing'));
        $cells .= pg_figure_grid_cell(lang('Margin'), h(lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number($figures['margin'], 1))))),
            $states[(string) ($figures['margin_state'] ?? '')] ?? '');
    }

    $last = (int) $figures['last_sale'];
    $cells .= ($last > 0)
        ? pg_figure_grid_cell(lang('Last sale'), h(get_relative_time(array('timestamp' => $last, 'format' => 'plain_text'))), date('j', $last) . ' ' . lang(date('M', $last)) . ' ' . date('Y', $last))
        : pg_figure_grid_cell(lang('Last sale'), '—', lang('Never sold'));

    return $cells;
}

/**
 * The bar chart of units sold, one bar a bucket, the first and last
 * bucket's names under it. Pure.
 *
 * @param array $data pg_product_insights_figures()
 * @return string
 */
function pg_product_insights_chart_html($data)
{
    $chart = array_values((array) $data['chart']);

    if ($chart === array()) {
        return '';
    }

    $unit = (string) $data['unit'];
    $titles = array('day' => lang('Units sold by day'), 'month' => lang('Units sold by month'), 'year' => lang('Units sold by year'));
    $top = max(1, max(array_map(function ($bucket) {
        return (int) $bucket['units'];
    }, $chart)));
    $bars = '';

    foreach ($chart as $bucket) {
        $units = (int) $bucket['units'];
        $name = pg_product_insights_bucket_name($bucket['start'], $unit) . ': ' . lang(array('string' => '{var:1} units', 'vars' => array(pg_format_number($units, 0))));
        $bars .= '<i' . (($units === 0) ? ' class="is-zero"' : '') . ' style="height:' . max(4, (int) round($units * 100 / $top)) . '%" title="' . h($name) . '"></i>';
    }

    return '<div class="pg-figure-grid-chart" role="img" aria-label="' . h($titles[$unit] ?? $titles['day']) . '">'
        . '<span class="pg-figure-label">' . h($titles[$unit] ?? $titles['day']) . '</span>'
        . '<div class="pg-figure-grid-bars">' . $bars . '</div>'
        . '<div class="pg-figure-grid-axis"><span>' . h(pg_product_insights_bucket_name($chart[0]['start'], $unit)) . '</span><span>' . h(pg_product_insights_bucket_name($chart[count($chart) - 1]['start'], $unit)) . '</span></div>'
        . '</div>';
}

/**
 * The "Carts and interest" rows: today's state and the period's left carts;
 * a row whose feature is off is left out. Pure.
 *
 * @param array  $data      pg_product_insights_figures()
 * @param string $carts_url All Orders on incomplete orders of the product
 * @return string
 */
function pg_product_insights_interest_html($data, $carts_url)
{
    $carts = (array) $data['carts'];
    $interest = (array) $data['interest'];
    $row = function ($icon, $text, $value, $extra = '', $wide = false) {
        return '<div class="pg-figure-grid-row' . ($wide ? ' is-wide' : '') . '"><i class="bi ' . $icon . '" aria-hidden="true"></i>'
            . '<span>' . h($text) . '</span> <b class="pg-tabular">' . h($value) . '</b>' . $extra . '</div>';
    };
    $count = function ($string, $value) {
        return lang(array('string' => $string, 'vars' => array(pg_format_number((int) $value, 0))));
    };

    $rows = $row('bi-cart-x', lang('Left in carts'), $count('{var:1} carts', $carts['left_carts']),
            ' <span class="pg-text-meta">· ' . h($count('{var:1} units', $carts['left_units'])) . '</span>'
            . (((int) $carts['left_carts'] > 0) ? '<a class="pg-figure-grid-row-link" href="' . h($carts_url) . '">' . h(lang('View carts')) . '</a>' : ''), true)
        . $row('bi-cart', lang('In open carts'), $count('{var:1} carts', $carts['open_carts']), ' <span class="pg-text-meta">· ' . h(lang('last 6 hours')) . '</span>');

    if ($interest['favourites'] !== null) {
        $rows .= $row('bi-heart', lang('Favourites'), $count('{var:1} people', $interest['favourites']));
    }

    if ($interest['waiting'] !== null) {
        $rows .= $row('bi-bell', lang('Waiting for stock'), $count('{var:1} people', $interest['waiting']));
    }

    if ($carts['saved_carts'] !== null) {
        $rows .= $row('bi-bookmark', lang('Saved for later'), $count('{var:1} carts', $carts['saved_carts']));
    }

    if ($interest['rating_count'] !== null) {
        $rows .= ($interest['rating_avg'] !== null)
            ? $row('bi-star', lang('Rating'), pg_format_number($interest['rating_avg'], 1), ' <span class="pg-text-meta">· ' . h($count('{var:1} reviews', $interest['rating_count'])) . '</span>')
            : $row('bi-star', lang('Rating'), '—', ' <span class="pg-text-meta">· ' . h(lang('No reviews yet')) . '</span>');
    }

    return '<p class="pg-figure-grid-sub">' . h(lang('Carts and interest')) . '</p><div class="pg-figure-grid-rows">' . $rows . '</div>';
}

/**
 * The Performance card's body under its title: the figure grid and the
 * chart (or "No sales in this period" with the views), "Carts and
 * interest", and the foot. Pure.
 *
 * @param array  $data   pg_product_insights_figures()
 * @param string $sku    products.name, for the links to All Orders
 * @param string $symbol the store's currency symbol as stored (HTML)
 * @param int    $now
 * @return string
 */
function pg_product_insights_card_html($data, $sku, $symbol, $now)
{
    $orders = 'view_orders.php?reset=true&product_name=' . rawurlencode((string) $sku);

    if (!empty($data['no_sales'])) {
        $views = $data['figures']['views'];
        $top = '<div class="pg-figure-grid-empty"><span class="pg-icon-tile" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>'
            . '<b>' . h(lang('No sales in this period')) . '</b>'
            . (($views !== null) ? '<span class="pg-text-meta">' . h(lang(array('string' => 'Views: {var:1}', 'vars' => array(pg_format_number((int) $views, 0))))) . '</span>' : '')
            . '</div>';
    } else {
        $top = '<div class="pg-figure-grid">' . pg_product_insights_figure_cells($data, $symbol, $now) . '</div>'
            . pg_product_insights_chart_html($data);
    }

    return $top
        . pg_product_insights_interest_html($data, $orders . '&status=incomplete')
        . '<div class="pg-figure-grid-foot"><a href="' . h($orders) . '">' . h(lang('Orders of this product')) . '</a>'
        . '<span class="pg-text-meta">' . h(lang(array('string' => 'Refreshed every {var:1} minutes', 'vars' => array((int) round(((int) ($data['keep'] ?? PG_PRODUCT_INSIGHTS_KEEP)) / 60))))) . '</span></div>';
}
