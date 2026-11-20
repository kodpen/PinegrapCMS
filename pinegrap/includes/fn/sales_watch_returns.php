<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sales watch, the return spike: once a night, the products whose units
 * asked back in the last 30 days stand far above their usual return rate.
 * Told through the operations alerts (includes/fn/ops_alerts.php) as one
 * bell row per pass.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the return spike of the sales watch.
//
// Read after the fact from order_returns, order_return_items, order_items and
// orders. A product's units asked back in the last 30 days are set against
// the units sold in the 30 days before them, at the product's own rate of
// the 26 weeks before that (returns of those weeks against the sales 30 days
// earlier, the same lag), or the shop's rate when the product sold too few
// units in those weeks. The upper tail of the binomial says how unlikely so
// many returns are; a rejected request does not count, a counter sale
// (type 'local') is not a sale here.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Days of returns judged, and the lag of the sales they are set against.
 */
const PG_SALES_WATCH_RETURN_DAYS = 30;

/**
 * Weeks of history a product's own rate is learnt from.
 */
const PG_SALES_WATCH_RETURN_WEEKS = 26;

/**
 * Units back below which a product is never told.
 */
const PG_SALES_WATCH_RETURN_MIN = 5;

/**
 * Units sold in the history below which the shop's rate is used.
 */
const PG_SALES_WATCH_RETURN_HISTORY = 20;

/**
 * Products named in the bell row; the rest are counted.
 */
const PG_SALES_WATCH_RETURN_NAMED = 3;

/**
 * Binomial upper tail: the chance of $k successes or more in $n tries of
 * chance $p, summed on the log scale so a tiny tail is not lost.
 *
 * @param int   $k
 * @param int   $n
 * @param float $p
 * @return float
 */
function pg_sales_watch_binomial_upper($k, $n, $p)
{
    $k = (int) $k;
    $n = (int) $n;
    $p = (float) $p;

    if ($k <= 0) {
        return 1.0;
    }

    if (($k > $n) || ($p <= 0)) {
        return 0.0;
    }

    if ($p >= 1) {
        return 1.0;
    }

    $log_p = log($p);
    $log_q = log(1 - $p);
    $log_n = pg_sales_watch_log_factorial($n);
    $sum = -INF;

    for ($i = $k; $i <= $n; $i++) {
        $sum = pg_sales_watch_log_add($sum, $log_n - pg_sales_watch_log_factorial($i) - pg_sales_watch_log_factorial($n - $i) + ($i * $log_p) + (($n - $i) * $log_q));
    }

    return min(1.0, exp($sum));
}

/**
 * The return rate a product is held to: its own with enough history, else
 * the shop's. One return and two sales are added to each, so a product or a
 * shop that never had a return still has a rate above 0.
 *
 * @param int   $returned units the product had back in the history
 * @param int   $sold     units of it sold in the history
 * @param array $shop     array('returned' => int, 'sold' => int), the shop's
 * @return float
 */
function pg_sales_watch_return_rate($returned, $sold, array $shop)
{
    if ((int) $sold < PG_SALES_WATCH_RETURN_HISTORY) {
        $returned = isset($shop['returned']) ? (int) $shop['returned'] : 0;
        $sold = isset($shop['sold']) ? (int) $shop['sold'] : 0;
    }

    $returned = max(0, (int) $returned);
    $sold = max($returned, (int) $sold);

    return ($returned + 1) / ($sold + 2);
}

/**
 * The products whose returns stand above their rate.
 *
 * The chance is split over the products judged (those with at least
 * PG_SALES_WATCH_RETURN_MIN units back), so a shop of many products is not
 * told of one by luck.
 *
 * @param array $products id => array('name', 'returned', 'sold',
 *                        'base_returned', 'base_sold', 'reasons' => code => units)
 * @param array $shop     array('returned' => int, 'sold' => int) over the history
 * @param float $alpha    the sensitivity
 * @return array id => array('name', 'returned', 'sold', 'expected', 'p',
 *               'reason'), the strongest first
 */
function pg_sales_watch_return_judge(array $products, array $shop, $alpha)
{
    $judged = array();

    foreach ($products as $id => $product) {
        if ((int) $product['returned'] >= PG_SALES_WATCH_RETURN_MIN) {
            $judged[$id] = $product;
        }
    }

    $told = array();

    if (!$judged) {
        return $told;
    }

    $alpha = (float) $alpha;

    if (($alpha <= 0) || ($alpha >= 1)) {
        $alpha = 0.01;
    }

    $alpha = $alpha / count($judged);

    foreach ($judged as $id => $product) {

        $returned = (int) $product['returned'];
        // Units bought before the month still come back: the tries are at
        // least the units back.
        $sold = max($returned, (int) $product['sold']);
        $rate = pg_sales_watch_return_rate($product['base_returned'], $product['base_sold'], $shop);
        $p = pg_sales_watch_binomial_upper($returned, $sold, $rate);

        if ($p >= $alpha) {
            continue;
        }

        $reason = '';
        $most = 0;

        foreach ((array) $product['reasons'] as $code => $units) {
            if (((string) $code !== '') && ((int) $units > $most)) {
                $reason = (string) $code;
                $most = (int) $units;
            }
        }

        $told[$id] = array(
            'name'     => (string) $product['name'],
            'returned' => $returned,
            'sold'     => $sold,
            'expected' => $sold * $rate,
            'p'        => $p,
            'reason'   => $reason,
        );
    }

    uasort($told, function ($a, $b) {
        if ($a['p'] == $b['p']) {
            return $b['returned'] - $a['returned'];
        }
        return ($a['p'] < $b['p']) ? -1 : 1;
    });

    return $told;
}

/**
 * The bell row of a pass: the products counted, the first few named with
 * their figures and the reason given most.
 *
 * @param array $told pg_sales_watch_return_judge()
 * @return string
 */
function pg_sales_watch_returns_sentence(array $told)
{
    $reasons = function_exists('pg_return_reasons') ? pg_return_reasons() : array();
    $parts = array();

    foreach (array_slice($told, 0, PG_SALES_WATCH_RETURN_NAMED, true) as $product) {

        $part = lang(array(
            'string' => '{var:1}: {var:2} units asked back, {var:3} sold the month before, about {var:4} expected',
            'vars'   => array($product['name'], (int) $product['returned'], (int) $product['sold'], (int) round($product['expected'])),
        ));

        if (($product['reason'] !== '') && isset($reasons[$product['reason']])) {
            $part .= ' (' . lang(array(
                'string' => 'mostly: {var:1}',
                'vars'   => array($reasons[$product['reason']]),
            )) . ')';
        }

        $parts[] = $part;
    }

    $sentence = lang(array(
        'string' => 'Returns rose on {var:1} products in the last 30 days',
        'vars'   => array(count($told)),
    )) . '. ' . implode('; ', $parts) . '.';

    if (count($told) > PG_SALES_WATCH_RETURN_NAMED) {
        $sentence .= ' ' . lang(array(
            'string' => 'And {var:1} more.',
            'vars'   => array(count($told) - PG_SALES_WATCH_RETURN_NAMED),
        ));
    }

    return $sentence;
}

/**
 * Whether the nightly pass is due: from 03:00 on, once a calendar day. A
 * night whose pass was missed runs at the first pass of the day after 03:00.
 *
 * @param int $now
 * @param int $last unix time of the last run, 0 for never
 * @return bool
 */
function pg_sales_watch_returns_due($now, $last)
{
    $now = (int) $now;

    if ((int) date('G', $now) < 3) {
        return false;
    }

    return ((int) $last <= 0) || (date('Ymd', (int) $last) !== date('Ymd', $now));
}

/**
 * The subject the night's alert is kept under: 'returns_0' or 'returns_1' by
 * the parity of the day, so two nights in a row are never within the alert's
 * 24 hours of each other.
 *
 * @param int $now
 * @return string
 */
function pg_sales_watch_returns_subject($now)
{
    return 'returns_' . (intdiv((int) mktime(12, 0, 0, (int) date('n', (int) $now), (int) date('j', (int) $now), (int) date('Y', (int) $now)), 86400) % 2);
}

/**
 * Whether the return spike is on: the Sales watch settings' own value, on
 * until they store one.
 *
 * @return bool
 */
function pg_sales_watch_returns_enabled()
{
    $settings = function_exists('pg_sales_watch_settings') ? pg_sales_watch_settings() : array();

    return !isset($settings['return_spike']) || !empty($settings['return_spike']);
}

/**
 * The figures of every product with a return in the window or the history.
 *
 * @param int $now
 * @return array array('products' => pg_sales_watch_return_judge()'s,
 *               'shop' => array('returned' => int, 'sold' => int))
 */
function pg_sales_watch_returns_read($now)
{
    $day = 86400;
    $recent = (int) $now - (PG_SALES_WATCH_RETURN_DAYS * $day);
    $history = $recent - (PG_SALES_WATCH_RETURN_WEEKS * 7 * $day);
    $sold_recent = $recent - (PG_SALES_WATCH_RETURN_DAYS * $day);
    $sold_history = $history - (PG_SALES_WATCH_RETURN_DAYS * $day);

    $products = array();
    $shop = array('returned' => 0, 'sold' => 0);

    $blank = array('name' => '', 'returned' => 0, 'sold' => 0, 'base_returned' => 0, 'base_sold' => 0, 'reasons' => array());

    foreach ((array) db_items(
        "SELECT
            order_items.product_id,
            order_returns.reason,
            (order_returns.requested_at > '" . $recent . "') AS recent,
            SUM(order_return_items.quantity) AS units
        FROM order_returns
        INNER JOIN order_return_items ON order_return_items.return_id = order_returns.id
        INNER JOIN order_items ON order_items.id = order_return_items.order_item_id
        WHERE
            (order_returns.requested_at > '" . $history . "')
            AND (order_returns.requested_at <= '" . (int) $now . "')
            AND (order_returns.status <> 'rejected')
            AND (order_items.product_id > 0)
        GROUP BY order_items.product_id, order_returns.reason, recent") as $row) {

        $id = (int) $row['product_id'];
        $units = (int) $row['units'];

        if (!isset($products[$id])) {
            $products[$id] = $blank;
        }

        if ((int) $row['recent'] === 1) {
            $products[$id]['returned'] += $units;
            $reason = (string) $row['reason'];
            $products[$id]['reasons'][$reason] = (isset($products[$id]['reasons'][$reason]) ? $products[$id]['reasons'][$reason] : 0) + $units;
        } else {
            $products[$id]['base_returned'] += $units;
            $shop['returned'] += $units;
        }
    }

    foreach ((array) db_items(
        "SELECT
            order_items.product_id,
            (orders.order_date > '" . $sold_recent . "') AS recent,
            SUM(order_items.quantity) AS units
        FROM orders
        INNER JOIN order_items ON order_items.order_id = orders.id
        WHERE
            (orders.order_date > '" . $sold_history . "')
            AND (orders.order_date <= '" . $recent . "')
            AND (orders.status IN ('complete', 'exported'))
            AND (orders.type <> 'local')
            AND (order_items.product_id > 0)
        GROUP BY order_items.product_id, recent") as $row) {

        $id = (int) $row['product_id'];
        $units = (int) $row['units'];

        if ((int) $row['recent'] === 1) {
            if (isset($products[$id])) {
                $products[$id]['sold'] += $units;
            }
        } else {
            $shop['sold'] += $units;
            if (isset($products[$id])) {
                $products[$id]['base_sold'] += $units;
            }
        }
    }

    return array('products' => $products, 'shop' => $shop);
}

/**
 * The names of the products told: the short description, else the name.
 *
 * @param int[] $ids
 * @return array id => name
 */
function pg_sales_watch_returns_names(array $ids)
{
    $ids = array_map('intval', $ids);
    $names = array();

    if (!$ids) {
        return $names;
    }

    foreach ((array) db_items(
        "SELECT id, name, short_description
        FROM products
        WHERE id IN (" . implode(',', $ids) . ")") as $row) {
        $name = trim((string) $row['short_description']);
        $names[(int) $row['id']] = ($name !== '') ? $name : trim((string) $row['name']);
    }

    return $names;
}

/**
 * The return spike check of the operations alert pass: runs once a night
 * (pg_sales_watch_returns_due()), the day claimed in the state file before
 * the read so two processes do not both run it.
 *
 * @param int $now
 * @return string[] the codes raised, told or not
 */
function pg_sales_watch_check_returns($now)
{
    $found = array();

    if (!defined('ECOMMERCE') || (ECOMMERCE !== true) || !pg_sales_watch_returns_enabled() || !pg_returns_ready()) {
        return $found;
    }

    $now = (int) $now;
    $state = pg_ops_alert_state();
    $last = (is_array($state) && isset($state['returns'])) ? (int) $state['returns'] : 0;

    if (!pg_sales_watch_returns_due($now, $last)) {
        return $found;
    }

    $claimed = true;

    $state = pg_ops_alert_state(function ($state) use ($now, &$claimed) {

        if (!pg_sales_watch_returns_due($now, isset($state['returns']) ? (int) $state['returns'] : 0)) {
            $claimed = false;
            return $state;
        }

        $state['returns'] = $now;

        return $state;
    });

    if (($state !== null) && !$claimed) {
        return $found;
    }

    $settings = function_exists('pg_sales_watch_settings') ? pg_sales_watch_settings() : array();
    $data = pg_sales_watch_returns_read($now);
    $told = pg_sales_watch_return_judge($data['products'], $data['shop'], isset($settings['sensitivity']) ? $settings['sensitivity'] : 0.01);

    if (!$told) {
        return $found;
    }

    $names = pg_sales_watch_returns_names(array_keys(array_slice($told, 0, PG_SALES_WATCH_RETURN_NAMED, true)));

    foreach ($told as $id => $product) {
        $told[$id]['name'] = isset($names[$id]) ? $names[$id] : '#' . $id;
    }

    // The night's claim above is the once-a-day rule. The alert's own rule
    // is 24 hours, and tonight's pass may start a few minutes earlier than
    // last night's, so odd and even days keep their send under two subjects.
    pg_ops_alert('return_spike', pg_sales_watch_returns_sentence($told), pg_sales_watch_returns_subject($now));
    $found[] = 'return_spike';

    return $found;
}
