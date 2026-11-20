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
 * The alert codes of the watch that Settings › Alerts switches on or off.
 *
 * @return string[]
 */
function pg_sales_watch_alert_names()
{
    return array('orders_stopped', 'payment_broken', 'price_error', 'return_spike');
}

/**
 * The sensitivity levels: the chance below which a window is told.
 *
 * @return array<string,float>
 */
function pg_sales_watch_levels()
{
    return array(
        'low'    => 0.001,
        'normal' => 0.01,
        'high'   => 0.05,
    );
}

/**
 * The settings of the watch from their config_text row (sales_watch), every
 * value held to what it may be. No row reads as the defaults: every alert on
 * (while operations alerts are on), normal sensitivity, no product taken off
 * sale, a price under half the usual one, nothing paused. Pure.
 *
 * The row is JSON: off (the alert codes switched off, so a code added later
 * starts on), level, close_products, price_drop (percent, 10 to 90) and
 * paused (channel key or 'store' => the time the pause ends).
 *
 * @param string|array|null $stored the row's value, or it decoded
 * @return array orders_stopped, payment_broken, price_error, return_spike
 *               (bool), level, sensitivity (float), close_products (bool),
 *               price_drop (float share), paused (key => int)
 */
function pg_sales_watch_settings_normalize($stored)
{
    if (is_string($stored)) {
        $stored = json_decode($stored, true);
    }

    if (!is_array($stored)) {
        $stored = array();
    }

    $off = isset($stored['off']) ? array_map('strval', (array) $stored['off']) : array();
    $settings = array();

    foreach (pg_sales_watch_alert_names() as $name) {
        $settings[$name] = !in_array($name, $off, true);
    }

    $levels = pg_sales_watch_levels();
    $level = (isset($stored['level']) && is_string($stored['level']) && isset($levels[$stored['level']])) ? $stored['level'] : 'normal';

    $settings['level'] = $level;
    $settings['sensitivity'] = $levels[$level];
    $settings['close_products'] = !empty($stored['close_products']);

    $drop = (isset($stored['price_drop']) && is_numeric($stored['price_drop'])) ? (int) $stored['price_drop'] : 50;
    $settings['price_drop'] = min(90, max(10, $drop)) / 100;

    $paused = array();

    foreach ((isset($stored['paused']) && is_array($stored['paused'])) ? $stored['paused'] : array() as $key => $until) {
        if (preg_match('/^(store|site|mp_[0-9]+)$/', (string) $key) && is_numeric($until) && ((int) $until > 0)) {
            $paused[(string) $key] = (int) $until;
        }
    }

    $settings['paused'] = $paused;

    return $settings;
}

/**
 * The config_text row of settings from pg_sales_watch_settings_normalize().
 * Pure.
 *
 * @param array $settings
 * @return string JSON
 */
function pg_sales_watch_settings_encode(array $settings)
{
    $off = array();

    foreach (pg_sales_watch_alert_names() as $name) {
        if (isset($settings[$name]) && !$settings[$name]) {
            $off[] = $name;
        }
    }

    return json_encode(array(
        'off'            => $off,
        'level'          => (string) $settings['level'],
        'close_products' => $settings['close_products'] ? 1 : 0,
        'price_drop'     => (int) round($settings['price_drop'] * 100),
        'paused'         => (object) $settings['paused'],
    ));
}

/**
 * The settings of the watch, read once per request.
 *
 * @return array pg_sales_watch_settings_normalize()
 */
function pg_sales_watch_settings()
{
    static $settings = null;

    if ($settings === null) {
        $settings = pg_sales_watch_settings_normalize(function_exists('pg_config_text') ? pg_config_text('sales_watch') : '');
    }

    return $settings;
}

/**
 * Whether a channel is paused at $now: its own pause, or the store's. Pure.
 *
 * @param array  $settings pg_sales_watch_settings_normalize()
 * @param string $key      'site', 'mp_<id>' or 'store'
 * @param int    $now
 * @return bool
 */
function pg_sales_watch_paused(array $settings, $key, $now)
{
    foreach (array('store', (string) $key) as $name) {
        if (isset($settings['paused'][$name]) && ((int) $now < $settings['paused'][$name])) {
            return true;
        }
    }

    return false;
}

/**
 * The end of a pause typed as a date: the watch is paused through that whole
 * day, so it ends at the next midnight in the site's time zone.
 *
 * @param string $date Y-m-d, or '' for no pause
 * @return int|false 0 for no pause, false for a date that is not one
 */
function pg_sales_watch_pause_end($date)
{
    $date = trim((string) $date);

    if ($date === '') {
        return 0;
    }

    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $date, $match)
        || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
        return false;
    }

    return mktime(0, 0, 0, (int) $match[2], (int) $match[3] + 1, (int) $match[1]);
}

/**
 * The settings from the posted Sales watch part. An alert not posted is off;
 * a pause already over, or for a channel not listed, is dropped. Pure but
 * for the site's time zone.
 *
 * @param array    $post     alerts (codes on), level, close_products,
 *                           price_drop, pause (key => Y-m-d)
 * @param string[] $channels the channel keys the form listed ('store' is always taken)
 * @param int      $now
 * @return array array('settings' => pg_sales_watch_settings_normalize(),
 *               'invalid' => the pause keys whose date is not one)
 */
function pg_sales_watch_settings_from_post(array $post, array $channels, $now)
{
    $on = isset($post['alerts']) ? array_map('strval', (array) $post['alerts']) : array();
    $paused = array();
    $invalid = array();
    $keys = array_merge(array('store'), array_map('strval', $channels));

    foreach ((isset($post['pause']) && is_array($post['pause'])) ? $post['pause'] : array() as $key => $date) {

        $key = (string) $key;

        if (!in_array($key, $keys, true)) {
            continue;
        }

        $end = pg_sales_watch_pause_end(is_string($date) ? $date : '');

        if ($end === false) {
            $invalid[] = $key;
        } elseif ($end > (int) $now) {
            $paused[$key] = $end;
        }
    }

    return array(
        'settings' => pg_sales_watch_settings_normalize(array(
            'off'            => array_values(array_diff(pg_sales_watch_alert_names(), $on)),
            'level'          => isset($post['level']) ? (string) $post['level'] : 'normal',
            'close_products' => !empty($post['close_products']),
            'price_drop'     => (isset($post['price_drop']) && is_numeric($post['price_drop'])) ? (int) $post['price_drop'] : 50,
            'paused'         => $paused,
        )),
        'invalid'  => $invalid,
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
 * The channels Settings › Alerts can pause: the site and every active
 * marketplace account that pulls orders, whether or not a pass judges it
 * today.
 *
 * @return array key => label
 */
function pg_sales_watch_channel_list()
{
    $list = array('site' => lang('Site'));

    if (!pg_schema_has('marketplace_accounts', 'pull_orders')) {
        return $list;
    }

    foreach ((array) db_items(
        "SELECT id, provider, name
        FROM marketplace_accounts
        WHERE
            (status = 'active')
            AND (pull_orders = '1')
        ORDER BY id") as $account) {
        $label = trim((string) $account['name']);
        $list['mp_' . (int) $account['id']] = ($label !== '') ? $label : ucfirst((string) $account['provider']);
    }

    return $list;
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

    // The store paused: nothing is read at all.
    if (pg_sales_watch_paused($settings, 'store', $now)) {
        return $found;
    }

    $alpha = pg_sales_watch_alpha($settings['sensitivity']);
    $data = pg_sales_watch_norm_data($now);
    $channels = pg_sales_watch_channels($now);

    // A paused channel is not judged; with one of two paused, the other is
    // told by its own name rather than as the store.
    foreach (array_keys($channels) as $key) {
        if (pg_sales_watch_paused($settings, $key, $now)) {
            unset($channels[$key]);
        }
    }

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

        if (count($channels) > (isset($channels['site']) ? 1 : 0)) {
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

    if (!empty($settings['payment_broken']) && isset($channels['site']) && ((int) $data['completed'] >= 20)) {

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
