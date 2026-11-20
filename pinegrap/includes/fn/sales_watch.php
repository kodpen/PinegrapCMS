<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Sales watch: the store learns how many orders each channel usually brings
 * in each hour of the week and says when that rhythm breaks - orders stopped
 * on a channel or everywhere, carts that reach payment and do not complete.
 * Told through the operations alerts (includes/fn/ops_alerts.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the sales watch.
//
// Everything is read after the fact from orders and marketplace_order_map;
// checkout and the order importer are not entered. The norm is computed from
// orders and kept in data/temp/sales_watch_norm.json only as a cache: a lost
// file is rebuilt on the next pass.
//
// A channel is the site (type 'online'), or one marketplace account
// (marketplace_order_map.account_id). Counter sales (type 'local') stay out.
//
// The norm of an hour is the median, over the eight same hours of the week
// before it, of that hour blended with its two neighbours, times the scale of
// the last 7 days against the 8 weeks (0.5 to 2). Hours are counted back by
// whole weeks of 168 hours, so no time zone is involved.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The windows every pass looks back over, in hours.
 *
 * @return int[]
 */
function pg_sales_watch_window_hours()
{
    return array(1, 2, 3, 6, 12);
}

/**
 * The settings of the watch. The defaults until the Sales watch part of
 * Settings › Alerts stores its own.
 *
 * - orders_stopped, payment_broken  each alert on or off
 * - sensitivity                     the chance below which a window is told
 *
 * @return array
 */
function pg_sales_watch_settings()
{
    return array(
        'orders_stopped' => true,
        'payment_broken' => true,
        'sensitivity'    => 0.01,
    );
}

/**
 * The chance one window must fall below: the sensitivity split over the
 * five windows, so the pass as a whole keeps to it. 1% when unset.
 *
 * @param float $sensitivity
 * @return float
 */
function pg_sales_watch_alpha($sensitivity)
{
    $sensitivity = (float) $sensitivity;

    if (($sensitivity <= 0) || ($sensitivity >= 1)) {
        $sensitivity = 0.01;
    }

    return $sensitivity / count(pg_sales_watch_window_hours());
}

/**
 * The windows of a channel: a window shorter than the channel's sync
 * interval is not counted, so polling delay never reads as silence.
 *
 * @param int $interval seconds between two syncs; 0 for the site
 * @return int[] hours
 */
function pg_sales_watch_windows($interval)
{
    $windows = array();

    foreach (pg_sales_watch_window_hours() as $hours) {
        if (($hours * 3600) >= (int) $interval) {
            $windows[] = $hours;
        }
    }

    return $windows;
}

/**
 * log(exp(a) + exp(b)) without leaving the log scale.
 *
 * @param float $a
 * @param float $b
 * @return float
 */
function pg_sales_watch_log_add($a, $b)
{
    if ($a === -INF) {
        return $b;
    }

    if ($b === -INF) {
        return $a;
    }

    $high = max($a, $b);

    return $high + log(1 + exp(min($a, $b) - $high));
}

/**
 * log(n!), summed: the counts here are small enough, and PHP has no
 * lgamma() everywhere.
 *
 * @param int $n
 * @return float
 */
function pg_sales_watch_log_factorial($n)
{
    static $cache = array(0.0);

    $n = max(0, (int) $n);

    for ($i = count($cache); $i <= $n; $i++) {
        $cache[$i] = $cache[$i - 1] + log($i);
    }

    return $cache[$n];
}

/**
 * Poisson lower tail: the chance of $k orders or fewer when $lambda are
 * expected. Summed on the log scale, so a large expectation does not
 * underflow.
 *
 * @param int   $k
 * @param float $lambda
 * @return float
 */
function pg_sales_watch_poisson_lower($k, $lambda)
{
    $k = (int) $k;
    $lambda = (float) $lambda;

    if (($lambda <= 0) || ($k < 0)) {
        return ($k < 0) ? 0.0 : 1.0;
    }

    $log_lambda = log($lambda);
    $sum = -INF;

    for ($i = 0; $i <= $k; $i++) {
        $sum = pg_sales_watch_log_add($sum, ($i * $log_lambda) - $lambda - pg_sales_watch_log_factorial($i));
    }

    return min(1.0, exp($sum));
}

/**
 * Binomial lower tail: the chance of $k successes or fewer in $n tries of
 * chance $p.
 *
 * @param int   $k
 * @param int   $n
 * @param float $p
 * @return float
 */
function pg_sales_watch_binomial_lower($k, $n, $p)
{
    $k = (int) $k;
    $n = (int) $n;
    $p = (float) $p;

    if ($k >= $n) {
        return 1.0;
    }

    if (($k < 0) || ($p >= 1)) {
        return 0.0;
    }

    if ($p <= 0) {
        return 1.0;
    }

    $log_p = log($p);
    $log_q = log(1 - $p);
    $log_n = pg_sales_watch_log_factorial($n);
    $sum = -INF;

    for ($i = 0; $i <= $k; $i++) {
        $sum = pg_sales_watch_log_add($sum, $log_n - pg_sales_watch_log_factorial($i) - pg_sales_watch_log_factorial($n - $i) + ($i * $log_p) + (($n - $i) * $log_q));
    }

    return min(1.0, exp($sum));
}

/**
 * Hourly counts from order times.
 *
 * @param int[] $times unix times
 * @return array hour index (unix time / 3600) => count
 */
function pg_sales_watch_hourly_counts(array $times)
{
    $counts = array();

    foreach ($times as $time) {
        $hour = intdiv((int) $time, 3600);
        $counts[$hour] = (isset($counts[$hour]) ? $counts[$hour] : 0) + 1;
    }

    return $counts;
}

/**
 * The norm of a channel from its hourly counts of the 8 weeks before $cut.
 *
 * @param array $counts hour index => orders, covering at least the 8 weeks
 *                      and an hour before $cut
 * @param int   $cut    the hour index the counts end before
 * @return array|null array('counts', 'cut', 'total', 'scale'); null under 20
 *                    orders in the 8 weeks: too little to learn from
 */
function pg_sales_watch_norm(array $counts, $cut)
{
    $cut = (int) $cut;
    $total = 0;
    $week = 0;

    foreach ($counts as $hour => $count) {

        $hour = (int) $hour;

        if (($hour >= $cut) || ($hour < ($cut - (8 * 168)))) {
            continue;
        }

        $total += (int) $count;

        if ($hour >= ($cut - 168)) {
            $week += (int) $count;
        }
    }

    if ($total < 20) {
        return null;
    }

    $scale = $week / ($total / 8);

    return array(
        'counts' => $counts,
        'cut'    => $cut,
        'total'  => $total,
        'scale'  => min(2.0, max(0.5, $scale)),
    );
}

/**
 * Orders expected in one hour.
 *
 * @param array $norm pg_sales_watch_norm()
 * @param int   $hour hour index; up to a week after the norm's cut
 * @return float
 */
function pg_sales_watch_expected_hour(array $norm, $hour)
{
    $counts = $norm['counts'];
    $at = function ($index) use ($counts) {
        return isset($counts[$index]) ? (float) $counts[$index] : 0.0;
    };

    $values = array();

    for ($k = 1; $k <= 8; $k++) {
        $index = (int) $hour - (168 * $k);
        $values[] = (0.25 * $at($index - 1)) + (0.5 * $at($index)) + (0.25 * $at($index + 1));
    }

    sort($values);

    return (($values[3] + $values[4]) / 2) * $norm['scale'];
}

/**
 * Orders expected between two times, part hours counted by their share.
 *
 * @param array $norm
 * @param int   $start unix time
 * @param int   $end   unix time
 * @return float
 */
function pg_sales_watch_expected(array $norm, $start, $end)
{
    $start = (int) $start;
    $end = (int) $end;
    $sum = 0.0;

    for ($hour = intdiv($start, 3600); ($hour * 3600) < $end; $hour++) {

        $overlap = min($end, ($hour + 1) * 3600) - max($start, $hour * 3600);

        if ($overlap > 0) {
            $sum += ($overlap / 3600) * pg_sales_watch_expected_hour($norm, $hour);
        }
    }

    return $sum;
}

/**
 * Whether a channel's orders stopped: every window ending at $end, orders
 * against the norm's sum, Poisson lower tail.
 *
 * @param array|null $norm     pg_sales_watch_norm(); null judges nothing
 * @param int[]      $times    the channel's order times, at least the last
 *                             12 hours before $end
 * @param int        $end      where the windows end: now, or a marketplace's
 *                             last successful sync
 * @param int        $interval the channel's sync interval in seconds
 * @param float      $alpha    pg_sales_watch_alpha()
 * @return array|null the most unusual window, array('hours', 'orders',
 *                    'expected', 'p'); null when nothing is unusual
 */
function pg_sales_watch_judge_orders($norm, array $times, $end, $interval, $alpha)
{
    if (!is_array($norm)) {
        return null;
    }

    $found = null;

    foreach (pg_sales_watch_windows($interval) as $hours) {

        $start = (int) $end - ($hours * 3600);
        $expected = pg_sales_watch_expected($norm, $start, $end);

        if ($expected <= 0) {
            continue;
        }

        $orders = 0;

        foreach ($times as $time) {
            if (($time > $start) && ($time <= $end)) {
                $orders++;
            }
        }

        $p = pg_sales_watch_poisson_lower($orders, $expected);

        if (($p <= $alpha) && (($found === null) || ($p < $found['p']))) {
            $found = array('hours' => $hours, 'orders' => $orders, 'expected' => $expected, 'p' => $p);
        }
    }

    return $found;
}

/**
 * Whether payment broke on the site: carts that reached payment against
 * the orders completed in the same window, the 8 weeks' share as the norm,
 * binomial lower tail, at least 5 carts.
 *
 * @param int   $completed_norm site orders completed in the 8 weeks
 * @param int   $carts_norm     carts left at payment in the 8 weeks
 * @param int[] $completed      times of the site orders completed lately
 * @param int[] $carts          change times of the carts left at payment lately
 * @param int   $end
 * @param float $alpha
 * @return array|null array('hours', 'completed', 'carts', 'expected', 'p')
 */
function pg_sales_watch_judge_payment($completed_norm, $carts_norm, array $completed, array $carts, $end, $alpha)
{
    if ((int) $completed_norm < 20) {
        return null;
    }

    // One of each added, so a history without a single abandoned cart does
    // not make the first one impossible.
    $share = ((int) $completed_norm + 1) / ((int) $completed_norm + (int) $carts_norm + 2);

    $count = function (array $times, $start) use ($end) {
        $n = 0;
        foreach ($times as $time) {
            if (($time > $start) && ($time <= $end)) {
                $n++;
            }
        }
        return $n;
    };

    $found = null;

    foreach (pg_sales_watch_window_hours() as $hours) {

        $start = (int) $end - ($hours * 3600);
        $done = $count($completed, $start);
        $n = $done + $count($carts, $start);

        if ($n < 5) {
            continue;
        }

        $p = pg_sales_watch_binomial_lower($done, $n, $share);

        if (($p <= $alpha) && (($found === null) || ($p < $found['p']))) {
            $found = array('hours' => $hours, 'completed' => $done, 'carts' => $n, 'expected' => $n * $share, 'p' => $p);
        }
    }

    return $found;
}

/**
 * The payment methods the carts left at payment chose, most chosen first.
 *
 * @param string[] $methods orders.payment_method of each cart
 * @return string "Credit/Debit Card (3), not chosen (1)"
 */
function pg_sales_watch_payment_methods(array $methods)
{
    $counts = array();

    foreach ($methods as $method) {
        $method = trim((string) $method);
        $counts[$method] = (isset($counts[$method]) ? $counts[$method] : 0) + 1;
    }

    arsort($counts);

    $parts = array();

    foreach ($counts as $method => $count) {
        $parts[] = ((string) $method === '' ? lang('not chosen') : $method) . ' (' . $count . ')';
    }

    return implode(', ', $parts);
}

/**
 * One channel's finding as a sentence.
 *
 * @param string $label
 * @param array  $result pg_sales_watch_judge_orders()
 * @return string "Trendyol: 0 orders in 5 hours, about 9 expected"
 */
function pg_sales_watch_orders_sentence($label, array $result)
{
    return lang(array(
        'string' => '{var:1}: {var:2} orders in {var:3} hours, about {var:4} expected',
        'vars'   => array($label, (int) $result['orders'], (int) $result['hours'], (int) round($result['expected'])),
    ));
}

/**
 * The orders-stopped alerts of a pass: all channels stopped together is
 * one alert for the store and no channel alert; otherwise one per channel.
 * A single channel is named by its own name.
 *
 * @param array $judged key => array('label' => string, 'result' => array|null),
 *                      every channel that has a norm
 * @return array subject => detail
 */
function pg_sales_watch_flow_alerts(array $judged)
{
    $stopped = array();

    foreach ($judged as $key => $channel) {
        if (is_array($channel['result'])) {
            $stopped[$key] = pg_sales_watch_orders_sentence($channel['label'], $channel['result']);
        }
    }

    if ((count($judged) >= 2) && (count($stopped) === count($judged))) {
        return array('store' => lang('No channel of the store has its usual orders') . '. ' . implode('; ', $stopped) . '.');
    }

    return $stopped;
}

// ----------------------------------------------------------------------------
// Reading the orders
// ----------------------------------------------------------------------------

/**
 * Where the norm cache is.
 *
 * @return string
 */
function pg_sales_watch_cache_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/sales_watch_norm.json';
}

/**
 * The channels judged by a pass.
 *
 * @param int $now
 * @return array key => array('label', 'end', 'interval', 'account_id')
 */
function pg_sales_watch_channels($now)
{
    $channels = array(
        'site' => array('label' => lang('Site'), 'end' => (int) $now, 'interval' => 0, 'account_id' => 0),
    );

    if (!pg_schema_has('marketplace_accounts', 'last_order_pull')) {
        return $channels;
    }

    $interval = 300;

    if (function_exists('pg_cron_jobs')) {
        $jobs = pg_cron_jobs();
        if (isset($jobs['api_sync_job']['interval'])) {
            $interval = (int) $jobs['api_sync_job']['interval'];
        }
    }

    $accounts = db_items(
        "SELECT id, provider, name, last_order_pull
        FROM marketplace_accounts
        WHERE
            (status = 'active')
            AND (pull_orders = '1')
            AND (last_order_pull > 0)
        ORDER BY id");

    foreach ((array) $accounts as $account) {

        // A cursor more than a week behind is a sync problem the account
        // screen reports; its old windows say nothing about today.
        $end = min((int) $now, (int) $account['last_order_pull']);

        if ($end < ((int) $now - (7 * 86400))) {
            continue;
        }

        $label = trim((string) $account['name']);

        $channels['mp_' . (int) $account['id']] = array(
            'label'      => ($label !== '') ? $label : ucfirst((string) $account['provider']),
            'end'        => $end,
            'interval'   => $interval,
            'account_id' => (int) $account['id'],
        );
    }

    return $channels;
}

/**
 * Read the norm's counts from orders: the 8 weeks and a day before $cut,
 * per channel, and the payment share's two totals.
 *
 * @param int $cut hour index
 * @return array array('cut', 'built', 'channels' => key => counts,
 *               'completed' => int, 'carts' => int)
 */
function pg_sales_watch_norm_read($cut)
{
    $from = ((int) $cut - (8 * 168) - 26) * 3600;
    $to = (int) $cut * 3600;

    $data = array('cut' => (int) $cut, 'built' => time(), 'channels' => array('site' => array()), 'completed' => 0, 'carts' => 0);

    $rows = db_items(
        "SELECT FLOOR(order_date / 3600) AS hour, COUNT(*) AS orders
        FROM orders
        WHERE
            (order_date >= '" . $from . "')
            AND (order_date < '" . $to . "')
            AND (type = 'online')
            AND (status <> 'incomplete')
        GROUP BY hour");

    foreach ((array) $rows as $row) {
        $data['channels']['site'][(int) $row['hour']] = (int) $row['orders'];
    }

    if (pg_schema_has('marketplace_accounts', 'last_order_pull')) {

        $rows = db_items(
            "SELECT marketplace_order_map.account_id, FLOOR(orders.order_date / 3600) AS hour, COUNT(DISTINCT orders.id) AS orders
            FROM orders
            INNER JOIN marketplace_order_map ON marketplace_order_map.order_id = orders.id
            WHERE
                (orders.order_date >= '" . $from . "')
                AND (orders.order_date < '" . $to . "')
            GROUP BY marketplace_order_map.account_id, hour");

        foreach ((array) $rows as $row) {
            $data['channels']['mp_' . (int) $row['account_id']][(int) $row['hour']] = (int) $row['orders'];
        }
    }

    $norm_from = ((int) $cut - (8 * 168)) * 3600;

    $data['completed'] = (int) db_value(
        "SELECT COUNT(*)
        FROM orders
        WHERE
            (order_date >= '" . $norm_from . "')
            AND (order_date < '" . $to . "')
            AND (type = 'online')
            AND (status <> 'incomplete')");

    $data['carts'] = (int) db_value(
        "SELECT COUNT(*)
        FROM orders
        WHERE
            (last_modified_timestamp >= '" . $norm_from . "')
            AND (last_modified_timestamp < '" . $to . "')
            AND (status = 'incomplete')
            AND (billing_complete = '1')
            AND (type = 'online')");

    return $data;
}

/**
 * The norm's counts, from the cache while it is under 6 hours old.
 *
 * @param int $now
 * @return array pg_sales_watch_norm_read()
 */
function pg_sales_watch_norm_data($now)
{
    $path = pg_sales_watch_cache_path();
    $cached = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;

    if (is_array($cached) && isset($cached['cut'], $cached['built'], $cached['channels'], $cached['completed'], $cached['carts'])
        && is_array($cached['channels']) && ((int) $cached['built'] <= (int) $now) && (((int) $now - (int) $cached['built']) < 21600)) {

        // JSON keys come back as strings.
        foreach ($cached['channels'] as $key => $counts) {
            $cached['channels'][$key] = is_array($counts) ? array_map('intval', $counts) : array();
        }

        return $cached;
    }

    $data = pg_sales_watch_norm_read(intdiv((int) $now, 3600));

    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0755, true);
    }

    // Written whole and renamed into place, so a reader never sees half.
    $temp = $path . '.' . getmypid() . '.tmp';

    if (@file_put_contents($temp, (string) json_encode($data)) !== false) {
        if (!@rename($temp, $path)) {
            @unlink($temp);
        }
    }

    return $data;
}

/**
 * The orders-stopped and payment-broken check of the operations alert pass.
 *
 * @param int $now
 * @return string[] the codes raised, told or not
 */
function pg_sales_watch_check($now)
{
    $found = array();

    if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
        return $found;
    }

    $settings = pg_sales_watch_settings();

    if (empty($settings['orders_stopped']) && empty($settings['payment_broken'])) {
        return $found;
    }

    $now = (int) $now;
    $alpha = pg_sales_watch_alpha($settings['sensitivity']);
    $data = pg_sales_watch_norm_data($now);
    $channels = pg_sales_watch_channels($now);

    $earliest = $now;
    foreach ($channels as $channel) {
        $earliest = min($earliest, $channel['end']);
    }
    $since = $earliest - (12 * 3600);

    if (!empty($settings['orders_stopped'])) {

        $times = array();

        foreach ((array) db_items(
            "SELECT order_date
            FROM orders
            WHERE
                (order_date > '" . $since . "')
                AND (type = 'online')
                AND (status <> 'incomplete')") as $row) {
            $times['site'][] = (int) $row['order_date'];
        }

        if (count($channels) > 1) {
            foreach ((array) db_items(
                "SELECT DISTINCT marketplace_order_map.account_id, orders.id, orders.order_date
                FROM orders
                INNER JOIN marketplace_order_map ON marketplace_order_map.order_id = orders.id
                WHERE (orders.order_date > '" . $since . "')") as $row) {
                $times['mp_' . (int) $row['account_id']][] = (int) $row['order_date'];
            }
        }

        $judged = array();

        foreach ($channels as $key => $channel) {

            $norm = isset($data['channels'][$key]) ? pg_sales_watch_norm($data['channels'][$key], $data['cut']) : null;

            if ($norm === null) {
                continue;
            }

            $judged[$key] = array(
                'label'  => $channel['label'],
                'result' => pg_sales_watch_judge_orders($norm, isset($times[$key]) ? $times[$key] : array(), $channel['end'], $channel['interval'], $alpha),
            );
        }

        foreach (pg_sales_watch_flow_alerts($judged) as $subject => $detail) {
            pg_ops_alert('orders_stopped', $detail, $subject);
            $found['orders_stopped'] = 'orders_stopped';
        }
    }

    if (!empty($settings['payment_broken']) && ((int) $data['completed'] >= 20)) {

        $completed = array();

        foreach ((array) db_items(
            "SELECT order_date
            FROM orders
            WHERE
                (order_date > '" . ($now - (12 * 3600)) . "')
                AND (type = 'online')
                AND (status <> 'incomplete')") as $row) {
            $completed[] = (int) $row['order_date'];
        }

        $carts = array();
        $methods = array();

        foreach ((array) db_items(
            "SELECT last_modified_timestamp, payment_method
            FROM orders
            WHERE
                (last_modified_timestamp > '" . ($now - (12 * 3600)) . "')
                AND (status = 'incomplete')
                AND (billing_complete = '1')
                AND (type = 'online')") as $row) {
            $carts[] = (int) $row['last_modified_timestamp'];
            $methods[] = array((int) $row['last_modified_timestamp'], (string) $row['payment_method']);
        }

        $result = pg_sales_watch_judge_payment($data['completed'], $data['carts'], $completed, $carts, $now, $alpha);

        if ($result !== null) {

            $chosen = array();
            foreach ($methods as $method) {
                if ($method[0] > ($now - ($result['hours'] * 3600))) {
                    $chosen[] = $method[1];
                }
            }

            pg_ops_alert('payment_broken', lang(array(
                'string' => '{var:1}: {var:2} of {var:3} carts that reached payment were completed in {var:4} hours, about {var:5} expected. Payment methods chosen: {var:6}.',
                'vars'   => array(lang('Site'), (int) $result['completed'], (int) $result['carts'], (int) $result['hours'], (int) round($result['expected']), pg_sales_watch_payment_methods($chosen)),
            )), 'site');

            $found['payment_broken'] = 'payment_broken';
        }
    }

    return array_values($found);
}

// ----------------------------------------------------------------------------
// The price error shield
// ----------------------------------------------------------------------------
//
// A product sold on the site or a marketplace in the last 6 hours at a price
// under half its usual one (or under the goods' cost with Accounting on), in
// units well above its norm: a price typed 100 for 1,000 that buyers found.
// Told as one bell row per pass; with the setting on, up to 5 products a pass
// and 20 a day are taken off sale (enabled = 0) through the API's product
// writer. More than 5 at once looks like a campaign: none is closed and the
// alert asks. The products closed are kept in the config_text row
// sales_watch_closed; one enabled again, by anyone, leaves it and is left
// alone for 24 hours.

/**
 * The hours a pass looks back over for sales at a wrong price.
 */
define('PG_SALES_WATCH_PRICE_HOURS', 6);

/**
 * The days the usual price and the units' norm are learnt over.
 */
define('PG_SALES_WATCH_PRICE_DAYS', 30);

/**
 * The settings of the shield, with their defaults until the Sales watch part
 * of Settings › Alerts stores them in pg_sales_watch_settings().
 *
 * - price_error     the alert on or off
 * - close_products  take a product off sale (off by default)
 * - price_share     the share of the usual price under which a price is wrong
 *                   (the setting's price_drop)
 * - paused_until    the store's pause ends then; 0 for none
 *
 * @return array
 */
function pg_sales_watch_price_settings()
{
    $settings = pg_sales_watch_settings();
    $share = isset($settings['price_drop']) ? (float) $settings['price_drop'] : 0.5;

    return array(
        'price_error'    => isset($settings['price_error']) ? (bool) $settings['price_error'] : true,
        'close_products' => isset($settings['close_products']) ? (bool) $settings['close_products'] : false,
        'price_share'    => (($share > 0) && ($share < 1)) ? $share : 0.5,
        'paused_until'   => isset($settings['paused']['store']) ? (int) $settings['paused']['store'] : 0,
    );
}

/**
 * The usual price between two times: the median of the prices that applied,
 * each weighted by how long it applied, so a few hours of a typo do not move
 * it. A product younger than the window counts the time it has.
 *
 * @param array $rows  array('changed_at' => int, 'price' => int), oldest
 *                     first; the newest row before $start says the price then
 * @param int   $start
 * @param int   $end
 * @return int|null minor units; null with no row
 */
function pg_sales_watch_price_usual(array $rows, $start, $end)
{
    $start = (int) $start;
    $end = (int) $end;
    $spans = array();
    $count = count($rows);

    for ($i = 0; $i < $count; $i++) {

        $from = max($start, (int) $rows[$i]['changed_at']);
        $to = ($i + 1 < $count) ? min($end, (int) $rows[$i + 1]['changed_at']) : $end;

        if ($to > $from) {
            $price = (int) $rows[$i]['price'];
            $spans[$price] = (isset($spans[$price]) ? $spans[$price] : 0) + ($to - $from);
        }
    }

    if (!$spans) {
        return $count ? (int) $rows[$count - 1]['price'] : null;
    }

    ksort($spans);

    $half = array_sum($spans) / 2;
    $seen = 0;

    foreach ($spans as $price => $seconds) {
        $seen += $seconds;
        if ($seen >= $half) {
            return (int) $price;
        }
    }

    return (int) $price;
}

/**
 * Why a product's sales look like a price error, or '' when they do not.
 *
 * @param int      $price the price it sells at now, minor units
 * @param int|null $usual pg_sales_watch_price_usual()
 * @param int|null $cost  erp_product_costs.avg_cost with Accounting on, else null
 * @param float    $units units sold in the hours looked back over
 * @param float    $norm  units usually sold in as many hours
 * @param float    $share the share of the usual price under which a price is wrong
 * @return string 'half' (under the share of the usual price), 'cost' or ''
 */
function pg_sales_watch_price_reason($price, $usual, $cost, $units, $norm, $share)
{
    if (((float) $units < 5) || ((float) $units < (3 * (float) $norm))) {
        return '';
    }

    $price = (int) $price;

    if (($usual !== null) && ((int) $usual > 0) && ($price < ((int) $usual * (float) $share))) {
        return 'half';
    }

    if (($cost !== null) && ((int) $cost > 0) && ($price < (int) $cost)) {
        return 'cost';
    }

    return '';
}

/**
 * The sales_watch_closed record as read from its config_text row.
 *
 * - closed  product id => array('at', 'price', 'reason'), at most 200
 * - back    product id => when it was found on sale again
 * - times   when each of the last 24 hours' closings was made
 *
 * @param mixed $raw the stored JSON, or an array
 * @return array
 */
function pg_sales_watch_closed_normalize($raw)
{
    $data = is_array($raw) ? $raw : json_decode((string) $raw, true);
    $data = is_array($data) ? $data : array();
    $record = array('closed' => array(), 'back' => array(), 'times' => array());

    foreach ((isset($data['closed']) && is_array($data['closed'])) ? $data['closed'] : array() as $id => $entry) {
        if (((int) $id > 0) && is_array($entry)) {
            $record['closed'][(int) $id] = array(
                'at'     => isset($entry['at']) ? (int) $entry['at'] : 0,
                'price'  => isset($entry['price']) ? (int) $entry['price'] : 0,
                'reason' => isset($entry['reason']) ? (string) $entry['reason'] : '',
            );
        }
    }

    foreach ((isset($data['back']) && is_array($data['back'])) ? $data['back'] : array() as $id => $at) {
        if ((int) $id > 0) {
            $record['back'][(int) $id] = (int) $at;
        }
    }

    foreach ((isset($data['times']) && is_array($data['times'])) ? $data['times'] : array() as $at) {
        $record['times'][] = (int) $at;
    }

    return $record;
}

/**
 * Bring the record up to date: a closed product on sale again (enabled by
 * anyone, any way) leaves it and is left alone for 24 hours; one that is gone
 * leaves it too. Times older than a day are dropped.
 *
 * @param array $record  pg_sales_watch_closed_normalize()
 * @param array $enabled product id => whether it is on sale now, for the
 *                       closed products that still exist
 * @param int   $now
 * @return array
 */
function pg_sales_watch_closed_reconcile(array $record, array $enabled, $now)
{
    $now = (int) $now;

    foreach ($record['closed'] as $id => $entry) {

        if (!array_key_exists($id, $enabled)) {
            unset($record['closed'][$id]);
        } elseif ($enabled[$id]) {
            unset($record['closed'][$id]);
            $record['back'][$id] = $now;
        }
    }

    foreach ($record['back'] as $id => $at) {
        if ($at <= ($now - 86400)) {
            unset($record['back'][$id]);
        }
    }

    $times = array();

    foreach ($record['times'] as $at) {
        if ($at > ($now - 86400)) {
            $times[] = $at;
        }
    }

    $record['times'] = $times;

    return $record;
}

/**
 * Record the products closed by a pass. The oldest entries go beyond 200.
 *
 * @param array $record
 * @param array $entries product id => array('price', 'reason')
 * @param int   $now
 * @return array
 */
function pg_sales_watch_closed_add(array $record, array $entries, $now)
{
    foreach ($entries as $id => $entry) {
        unset($record['back'][(int) $id]);
        $record['closed'][(int) $id] = array(
            'at'     => (int) $now,
            'price'  => (int) $entry['price'],
            'reason' => (string) $entry['reason'],
        );
        $record['times'][] = (int) $now;
    }

    if (count($record['closed']) > 200) {
        uasort($record['closed'], function ($a, $b) {
            return $a['at'] - $b['at'];
        });
        $record['closed'] = array_slice($record['closed'], -200, null, true);
    }

    return $record;
}

/**
 * What a pass does with the products it found: which it tells of and which
 * it takes off sale.
 *
 * A product a person put back in the last 24 hours, or one already closed,
 * is left alone. More than 5 found at once closes none (a campaign looks the
 * same). At most 5 close a pass and 20 a day.
 *
 * @param array $flagged product id => details, most units first
 * @param array $record  pg_sales_watch_closed_reconcile()
 * @param int   $now
 * @param bool  $close   the setting that allows taking products off sale
 * @return array array('told' => ids, 'close' => ids, 'campaign' => bool)
 */
function pg_sales_watch_price_plan(array $flagged, array $record, $now, $close)
{
    $told = array();

    foreach (array_keys($flagged) as $id) {

        if (isset($record['closed'][$id])) {
            continue;
        }

        if (isset($record['back'][$id]) && ($record['back'][$id] > ((int) $now - 86400))) {
            continue;
        }

        $told[] = (int) $id;
    }

    $campaign = (count($told) > 5);
    $closing = array();

    if ($close && !$campaign) {

        $today = 0;

        foreach ($record['times'] as $at) {
            if ($at > ((int) $now - 86400)) {
                $today++;
            }
        }

        $closing = array_slice($told, 0, max(0, min(5, 20 - $today)));
    }

    return array('told' => $told, 'close' => $closing, 'campaign' => $campaign);
}

/**
 * The alert's text: the products counted, the first three named with their
 * price, usual price and units. No cost figure: the text also goes by e-mail
 * and chat, to people who may not read the books.
 *
 * @param array $flagged product id => array('name', 'price', 'usual', 'units', 'reason')
 * @param array $plan    pg_sales_watch_price_plan()
 * @return string
 */
function pg_sales_watch_price_detail(array $flagged, array $plan)
{
    $symbol = defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '';
    $money = function ($minor) use ($symbol) {
        return function_exists('pg_money_text')
            ? pg_money_text((int) $minor / 100, $symbol)
            : number_format((int) $minor / 100, 2);
    };

    $parts = array();

    foreach (array_slice($plan['told'], 0, 3) as $id) {

        $item = $flagged[$id];

        $parts[] = lang(array(
            'string' => '{var:1}: {var:2} sold at {var:3}, usually {var:4}',
            'vars'   => array($item['name'], (int) $item['units'], $money($item['price']), $money($item['usual'])),
        )) . (($item['reason'] === 'cost') ? ' (' . lang('under its cost') . ')' : '');
    }

    $count = count($plan['told']);
    $text = $plan['campaign']
        ? lang(array('string' => 'Price drop on {var:1} products; a campaign?', 'vars' => array($count)))
        : lang(array('string' => '{var:1} products sell well below their usual price in the last {var:2} hours', 'vars' => array($count, PG_SALES_WATCH_PRICE_HOURS)));

    $text .= '. ' . implode('; ', $parts) . (($count > 3) ? '; …' : '') . '.';

    if ($plan['close']) {
        $text .= ' ' . lang(array('string' => 'Taken off sale: {var:1}. Enabling a product again puts it back.', 'vars' => array(count($plan['close']))));
    } elseif ($plan['campaign']) {
        $text .= ' ' . lang('None was taken off sale.');
    }

    return $text;
}

/**
 * Load the API's product writer, api_products_set_enabled(), in a process
 * that is not an API request.
 *
 * @return bool
 */
function pg_sales_watch_product_writer()
{
    if (!function_exists('api_products_set_enabled')) {

        if (!defined('PG_API_PANEL')) {
            define('PG_API_PANEL', true);
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');
    }

    return function_exists('api_products_set_enabled');
}

/**
 * The products sold on the site or a marketplace in the last hours at a
 * price that looks wrong.
 *
 * @param int   $now
 * @param float $share
 * @return array product id => array('name', 'price', 'usual', 'units', 'reason'),
 *               most units first
 */
function pg_sales_watch_price_flagged($now, $share)
{
    $now = (int) $now;
    $since = $now - (PG_SALES_WATCH_PRICE_HOURS * 3600);
    $start = $since - (PG_SALES_WATCH_PRICE_DAYS * 86400);
    $active = function_exists('pg_order_item_active_sql') ? pg_order_item_active_sql() : '';

    // Units in the hours looked back over: at least 5, or nothing to judge.
    $units = array();

    foreach ((array) db_items(
        "SELECT order_items.product_id, SUM(order_items.quantity) AS units
        FROM order_items
        INNER JOIN orders ON orders.id = order_items.order_id
        WHERE
            (orders.order_date > '" . $since . "')
            AND (orders.type IN ('online', 'marketplace'))
            AND (orders.status <> 'incomplete')
            AND (order_items.product_id > 0)" . $active . "
        GROUP BY order_items.product_id
        HAVING units >= 5
        ORDER BY units DESC
        LIMIT 200") as $row) {
        $units[(int) $row['product_id']] = (float) $row['units'];
    }

    if (!$units) {
        return array();
    }

    $ids = "'" . implode("', '", array_keys($units)) . "'";

    // Only products on sale: one already off sale has nothing to stop.
    $products = array();

    foreach ((array) db_items("SELECT id, name FROM products WHERE (id IN (" . $ids . ")) AND (enabled = '1')") as $row) {
        $products[(int) $row['id']] = (string) $row['name'];
    }

    if (!$products) {
        return array();
    }

    $ids = "'" . implode("', '", array_keys($products)) . "'";

    // The norm: units of the 30 days before, per as many hours.
    $norm = array();

    foreach ((array) db_items(
        "SELECT order_items.product_id, SUM(order_items.quantity) AS units
        FROM order_items
        INNER JOIN orders ON orders.id = order_items.order_id
        WHERE
            (orders.order_date > '" . $start . "')
            AND (orders.order_date <= '" . $since . "')
            AND (orders.type IN ('online', 'marketplace'))
            AND (orders.status <> 'incomplete')
            AND (order_items.product_id IN (" . $ids . "))" . $active . "
        GROUP BY order_items.product_id") as $row) {
        $norm[(int) $row['product_id']] = (float) $row['units'] * PG_SALES_WATCH_PRICE_HOURS / (PG_SALES_WATCH_PRICE_DAYS * 24);
    }

    // The prices of the 30 days, with the row before them that says the
    // price at their start. Only the web channel has rows today; a
    // marketplace sells at the shop's price.
    $rows = array();

    if (function_exists('pg_price_history_ready') && pg_price_history_ready()) {

        foreach ((array) db_items(
            "SELECT product_id, price, changed_at
            FROM product_price_history
            WHERE
                (id IN (
                    SELECT MAX(id) FROM product_price_history
                    WHERE (product_id IN (" . $ids . ")) AND (channel = 'web') AND (changed_at < '" . $start . "')
                    GROUP BY product_id))
                OR ((product_id IN (" . $ids . ")) AND (channel = 'web') AND (changed_at >= '" . $start . "'))
            ORDER BY product_id, changed_at, id") as $row) {
            $rows[(int) $row['product_id']][] = array('changed_at' => (int) $row['changed_at'], 'price' => (int) $row['price']);
        }
    }

    $costs = array();

    if (defined('ERP_ENABLED') && ERP_ENABLED && pg_schema_has('erp_product_costs', 'avg_cost')) {
        foreach ((array) db_items("SELECT product_id, avg_cost FROM erp_product_costs WHERE product_id IN (" . $ids . ")") as $row) {
            $costs[(int) $row['product_id']] = (int) $row['avg_cost'];
        }
    }

    $flagged = array();

    foreach ($units as $id => $sold) {

        if (!isset($products[$id]) || empty($rows[$id])) {
            continue;
        }

        $newest = end($rows[$id]);
        $usual = pg_sales_watch_price_usual($rows[$id], $start, $now);
        $cost = isset($costs[$id]) ? $costs[$id] : null;
        $reason = pg_sales_watch_price_reason($newest['price'], $usual, $cost, $sold, isset($norm[$id]) ? $norm[$id] : 0.0, $share);

        if ($reason !== '') {
            $flagged[$id] = array(
                'name'   => $products[$id],
                'price'  => (int) $newest['price'],
                'usual'  => (int) $usual,
                'units'  => $sold,
                'reason' => $reason,
            );
        }
    }

    return $flagged;
}

/**
 * The price error check of the operations alert pass.
 *
 * @param int $now
 * @return string[] the codes raised, told or not
 */
function pg_sales_watch_price_check($now)
{
    if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
        return array();
    }

    $settings = pg_sales_watch_price_settings();

    $now = (int) $now;

    if (!$settings['price_error'] || ($settings['paused_until'] > $now)) {
        return array();
    }

    // The record first: a product a person put back since the last pass is
    // out of it before anything is judged.
    $raw = (string) pg_config_text('sales_watch_closed');
    $record = pg_sales_watch_closed_normalize($raw);

    if ($record['closed']) {

        $enabled = array();

        foreach ((array) db_items(
            "SELECT id, enabled FROM products
            WHERE id IN ('" . implode("', '", array_keys($record['closed'])) . "')" . pg_catalog_bin_live_sql('products')) as $row) {
            $enabled[(int) $row['id']] = ((int) $row['enabled'] === 1);
        }

        $record = pg_sales_watch_closed_reconcile($record, $enabled, $now);

    } else {
        $record = pg_sales_watch_closed_reconcile($record, array(), $now);
    }

    $flagged = pg_sales_watch_price_flagged($now, $settings['price_share']);

    // Told once a day per product.
    foreach (array_keys($flagged) as $id) {
        if (!pg_ops_alert_due(pg_ops_alert_last_sent('price_error', 'p' . $id), $now)) {
            unset($flagged[$id]);
        }
    }

    $plan = pg_sales_watch_price_plan($flagged, $record, $now, $settings['close_products'] && pg_sales_watch_product_writer());

    $closed = array();

    foreach ($plan['close'] as $id) {
        if (api_products_set_enabled($id, false, lang('Sales watch'))) {
            $closed[$id] = array('price' => $flagged[$id]['price'], 'reason' => $flagged[$id]['reason']);
        }
    }

    $plan['close'] = array_keys($closed);
    $record = pg_sales_watch_closed_add($record, $closed, $now);

    $value = (string) json_encode($record);

    if (($value !== $raw) && ($record['closed'] || $record['back'] || $record['times'] || ($raw !== ''))) {
        pg_config_text_claim('sales_watch_closed', $raw, $value);
    }

    if (!$plan['told']) {
        return array();
    }

    // One row for the pass; it links the first product closed, else the
    // first named.
    $first = $plan['close'] ? $plan['close'][0] : $plan['told'][0];
    $sent = pg_ops_alert('price_error', pg_sales_watch_price_detail($flagged, $plan), 'p' . $first, $first);

    if ($sent['sent']) {
        pg_ops_alert_state(function ($state) use ($plan, $now) {
            foreach ($plan['told'] as $id) {
                $state['sent'][pg_ops_alert_key('price_error', 'p' . $id)] = $now;
            }
            return $state;
        });
    }

    return array('price_error');
}
