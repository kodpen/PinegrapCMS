<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Customer value summary: repeat purchase, value per customer, cohorts and
 * the groups' cells, worked out from the orders by the general job and kept
 * as one settings text (config_text 'customer_values'). No table: the job
 * walks the orders by date in runs bounded in time, keeps its place and the
 * per-customer state in a temp file between runs, and replaces the summary
 * in one write when the whole walk is done. Nothing is added to the order
 * path; a figure is never repaired in place, only rebuilt.
 *
 * One rule says which orders count (pg_customer_value_order_counts()), one
 * says for how much (pg_customer_value_order_amount()), one in which unit
 * (pg_customer_value_order_unit()) and one who the customer is
 * (pg_customer_value_key()). Every figure goes through them.
 *
 * The summary holds no name, address or e-mail: a customer is a hash while
 * the walk runs, and a top customer is kept as their last order's number.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// A summary older than this is rebuilt.
define('PG_CUSTOMER_VALUE_INTERVAL', 86400);

// Seconds of a general job tick the rebuild may use.
define('PG_CUSTOMER_VALUE_TICK_SECONDS', 20);

// Orders read per query.
define('PG_CUSTOMER_VALUE_PAGE', 2000);

// Months kept in the summary's month table, cohorts kept, top customers kept.
define('PG_CUSTOMER_VALUE_MONTHS', 36);
define('PG_CUSTOMER_VALUE_COHORTS', 24);
define('PG_CUSTOMER_VALUE_TOP', 20);

/**
 * Whether an order counts: complete or exported, never cancelled, and not
 * refunded in full. A free order counts. Pure.
 *
 * @param array $order  status, total, refunded_amount, cancelled_at
 * @return bool
 */
function pg_customer_value_order_counts($order)
{
    $status = isset($order['status']) ? (string) $order['status'] : '';

    if (($status !== 'complete') && ($status !== 'exported')) {
        return false;
    }

    if (!empty($order['cancelled_at'])) {
        return false;
    }

    $refunded = isset($order['refunded_amount']) ? (int) $order['refunded_amount'] : 0;

    return !(($refunded > 0) && ($refunded >= (int) $order['total']));
}

/**
 * What an order is worth: its total less what was refunded, in minor units,
 * never below zero. Pure.
 *
 * @param array $order
 * @return int
 */
function pg_customer_value_order_amount($order)
{
    $refunded = isset($order['refunded_amount']) ? (int) $order['refunded_amount'] : 0;

    return max(0, (int) $order['total'] - $refunded);
}

/**
 * The unit an order's amounts are in: the base currency, except for a
 * marketplace order whose currency code names another unit. A web order's
 * currency_code is only what the visitor was shown. Pure.
 *
 * @param array  $order  type, currency_code
 * @param string $base   BASE_CURRENCY_CODE
 * @return string
 */
function pg_customer_value_order_unit($order, $base)
{
    $base = strtoupper((string) $base);

    if (!isset($order['type']) || ((string) $order['type'] !== 'marketplace')) {
        return $base;
    }

    $code = strtoupper(trim(isset($order['currency_code']) ? (string) $order['currency_code'] : ''));

    return ($code === '') ? $base : $code;
}

/**
 * Who the customer is: the billing e-mail folded to lower case, hashed so
 * no address is kept; an order with no e-mail goes by its contact. '' when
 * the order has neither. Worked out again at every rebuild. Pure.
 *
 * @param array $order  billing_email_address, contact_id
 * @return string
 */
function pg_customer_value_key($order)
{
    $email = isset($order['billing_email_address']) ? trim((string) $order['billing_email_address']) : '';

    if ($email !== '') {
        $email = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);

        return 'e' . substr(md5($email), 0, 20);
    }

    $contact = isset($order['contact_id']) ? (int) $order['contact_id'] : 0;

    return ($contact > 0) ? 'c' . $contact : '';
}

/**
 * A month as a running number (year * 12 + month - 1) in the site's time.
 * Pure.
 *
 * @param int $time
 * @return int
 */
function pg_customer_value_month($time)
{
    return ((int) date('Y', (int) $time) * 12) + (int) date('n', (int) $time) - 1;
}

/**
 * A running month number as 'YYYY-MM'. Pure.
 *
 * @param int $month
 * @return string
 */
function pg_customer_value_month_label($month)
{
    return sprintf('%04d-%02d', intdiv((int) $month, 12), ((int) $month % 12) + 1);
}

/**
 * The state of a new rebuild that counts the orders placed up to $started.
 *
 * @param int $started
 * @return array
 */
function pg_customer_value_state_new($started)
{
    return array(
        'v' => 1,
        'started' => (int) $started,
        'date' => 0,
        'id' => 0,
        'done' => 0,
        'total' => 0,
        'unplaced' => 0,
        // key => "first,last,orders,value,recent,number,m.m.m"
        'customers' => array(),
        // month => array(first orders, first value, returning orders, returning value, gap seconds, gaps)
        'months' => array(),
        // unit => array(orders, value)
        'apart' => array(),
    );
}

/**
 * Add a page of order rows, read in date order, to the state and move its
 * place to the last row. Pure.
 *
 * @param array  $state
 * @param array  $rows
 * @param string $base
 * @return void
 */
function pg_customer_value_add_rows(&$state, $rows, $base)
{
    $base = strtoupper((string) $base);
    $recent_from = (int) $state['started'] - 365 * 86400;

    foreach ($rows as $order) {
        $state['date'] = (int) $order['order_date'];
        $state['id'] = (int) $order['id'];
        $state['done']++;

        if (!pg_customer_value_order_counts($order)) {
            continue;
        }

        $amount = pg_customer_value_order_amount($order);
        $unit = pg_customer_value_order_unit($order, $base);

        if ($unit !== $base) {
            if (!isset($state['apart'][$unit])) {
                $state['apart'][$unit] = array(0, 0);
            }

            $state['apart'][$unit][0]++;
            $state['apart'][$unit][1] += $amount;
            continue;
        }

        $key = pg_customer_value_key($order);

        if ($key === '') {
            $state['unplaced']++;
            continue;
        }

        $time = (int) $order['order_date'];
        $month = pg_customer_value_month($time);
        $recent = ($time >= $recent_from) ? $amount : 0;
        $number = (int) $order['order_number'];

        if (!isset($state['months'][$month])) {
            $state['months'][$month] = array(0, 0, 0, 0, 0, 0);
        }

        if (!isset($state['customers'][$key])) {
            $state['customers'][$key] = $time . ',' . $time . ',1,' . $amount . ',' . $recent . ',' . $number . ',' . $month;
            $state['months'][$month][0]++;
            $state['months'][$month][1] += $amount;
            continue;
        }

        $c = explode(',', $state['customers'][$key]);
        $gap = max(0, $time - (int) $c[1]);
        $state['customers'][$key] = $c[0] . ',' . $time . ',' . ((int) $c[2] + 1) . ',' . ((int) $c[3] + $amount) . ',' . ((int) $c[4] + $recent) . ',' . $number . ',' . $c[6] . '.' . $month;
        $state['months'][$month][2]++;
        $state['months'][$month][3] += $amount;
        $state['months'][$month][4] += $gap;
        $state['months'][$month][5]++;
    }
}

/**
 * The state as the text kept between runs. Pure.
 *
 * @param array $state
 * @return string
 */
function pg_customer_value_state_encode($state)
{
    return serialize($state);
}

/**
 * The state read back, or null when the text is not one.
 *
 * @param string $text
 * @return array|null
 */
function pg_customer_value_state_decode($text)
{
    $state = @unserialize((string) $text, array('allowed_classes' => false));

    if (!is_array($state) || !isset($state['v'], $state['started'], $state['customers']) || ((int) $state['v'] !== 1)) {
        return null;
    }

    return $state;
}

/**
 * Keep the best PG_CUSTOMER_VALUE_TOP rows by value, order number breaking
 * a tie. Pure.
 *
 * @param array $top    rows array(number, value, orders)
 * @param array $row
 * @return array
 */
function _pg_customer_value_top_add($top, $row)
{
    if ($row[1] <= 0) {
        return $top;
    }

    $top[] = $row;

    if (count($top) > PG_CUSTOMER_VALUE_TOP * 2) {
        $top = _pg_customer_value_top_sort($top);
    }

    return $top;
}

/**
 * @param array $top
 * @return array
 */
function _pg_customer_value_top_sort($top)
{
    usort($top, function ($a, $b) {
        if ($a[1] !== $b[1]) {
            return ($a[1] < $b[1]) ? 1 : -1;
        }

        return ($a[0] < $b[0]) ? -1 : (($a[0] > $b[0]) ? 1 : 0);
    });

    return array_slice($top, 0, PG_CUSTOMER_VALUE_TOP);
}

/**
 * The groups' age bounds in days: a last order within each, then longer.
 * Pure.
 *
 * @return int[]
 */
function pg_customer_value_ages()
{
    return array(30, 90, 180, 365);
}

/**
 * The age row of a customer whose last order was $days ago: 0 for within
 * 30 days (30 itself included), up to 4 for longer than 365. Pure.
 *
 * @param float $days
 * @return int
 */
function pg_customer_value_cell_age($days)
{
    foreach (pg_customer_value_ages() as $i => $bound) {
        if ($days <= $bound) {
            return $i;
        }
    }

    return count(pg_customer_value_ages());
}

/**
 * The orders column of a customer: 0 for one order, 1 for two or three, 2
 * for four or more. Pure.
 *
 * @param int $orders
 * @return int
 */
function pg_customer_value_cell_band($orders)
{
    return ($orders >= 4) ? 2 : (($orders >= 2) ? 1 : 0);
}

/**
 * The summary of a finished walk. Tiles are kept for the last 1, 3, 6, 12
 * and 24 whole months and for all time, each array(customers, customers with
 * two orders or more, orders, value, average days between orders); months
 * array(first-time orders, first-time value, returning orders, returning
 * value); cohorts array(size, then of the twelve months after the first how
 * many ordered in each); cells [last order age][orders] array(customers,
 * value), ages up to 30, 90, 180, 365 days and longer, orders 1, 2-3, 4+.
 * Pure.
 *
 * @param array  $state
 * @param string $base
 * @return array
 */
function pg_customer_value_finish($state, $base)
{
    $now = (int) $state['started'];
    $this_month = pg_customer_value_month($now);
    $periods = array(1, 3, 6, 12, 24);
    $ages = pg_customer_value_ages();

    $tiles = array();
    $repeaters = array();

    foreach ($periods as $p) {
        $tiles[(string) $p] = array(0, 0, 0, 0, 0, 0);
        $repeaters[$p] = array($this_month - $p, $this_month - 1);
    }

    $tiles['all'] = array(0, 0, 0, 0, 0, 0);

    $cohorts = array();
    $cohort_from = $this_month - PG_CUSTOMER_VALUE_COHORTS + 1;

    for ($m = $cohort_from; $m <= $this_month; $m++) {
        $cohorts[$m] = array_fill(0, 13, 0);
    }

    $cells = array();

    for ($a = 0; $a <= count($ages); $a++) {
        $cells[$a] = array(array(0, 0), array(0, 0), array(0, 0));
    }

    $top_all = array();
    $top_year = array();
    $customers = 0;

    foreach ($state['customers'] as $text) {
        $c = explode(',', $text);
        $last = (int) $c[1];
        $orders = (int) $c[2];
        $value = (int) $c[3];
        $months = array_map('intval', explode('.', $c[6]));
        $customers++;

        $tiles['all'][0]++;

        if ($orders >= 2) {
            $tiles['all'][1]++;
        }

        foreach ($repeaters as $p => $range) {
            $in = 0;

            foreach ($months as $m) {
                if (($m >= $range[0]) && ($m <= $range[1])) {
                    $in++;
                }
            }

            if ($in > 0) {
                $tiles[(string) $p][0]++;

                if ($in >= 2) {
                    $tiles[(string) $p][1]++;
                }
            }
        }

        $first_month = $months[0];

        if (isset($cohorts[$first_month])) {
            $cohorts[$first_month][0]++;
            $seen = array();

            foreach ($months as $m) {
                $k = $m - $first_month;

                if (($k >= 1) && ($k <= 12) && !isset($seen[$k])) {
                    $seen[$k] = true;
                    $cohorts[$first_month][$k]++;
                }
            }
        }

        $age = pg_customer_value_cell_age(($now - $last) / 86400);
        $band = pg_customer_value_cell_band($orders);
        $cells[$age][$band][0]++;
        $cells[$age][$band][1] += $value;

        $top_all = _pg_customer_value_top_add($top_all, array((int) $c[5], $value, $orders));
        $top_year = _pg_customer_value_top_add($top_year, array((int) $c[5], (int) $c[4], $orders));
    }

    $month_table = array();
    $orders_counted = 0;

    foreach ($state['months'] as $m => $row) {
        $orders_counted += $row[0] + $row[2];
        $targets = array('all');

        foreach ($repeaters as $p => $range) {
            if (($m >= $range[0]) && ($m <= $range[1])) {
                $targets[] = (string) $p;
            }
        }

        foreach ($targets as $t) {
            $tiles[$t][2] += $row[0] + $row[2];
            $tiles[$t][3] += $row[1] + $row[3];
            $tiles[$t][4] += $row[4];
            $tiles[$t][5] += $row[5];
        }
    }

    for ($m = $this_month - PG_CUSTOMER_VALUE_MONTHS + 1; $m <= $this_month; $m++) {
        $row = isset($state['months'][$m]) ? $state['months'][$m] : array(0, 0, 0, 0, 0, 0);
        $month_table[pg_customer_value_month_label($m)] = array($row[0], $row[1], $row[2], $row[3]);
    }

    foreach ($tiles as $t => $row) {
        $gap = ($row[5] > 0) ? round($row[4] / $row[5] / 86400, 1) : 0.0;
        $tiles[$t] = array($row[0], $row[1], $row[2], $row[3], (float) $gap);
    }

    $cohort_table = array();

    foreach ($cohorts as $m => $row) {
        $cohort_table[pg_customer_value_month_label($m)] = $row;
    }

    $apart = $state['apart'];
    ksort($apart);

    return array(
        'v' => 1,
        'at' => $now,
        'unit' => strtoupper((string) $base),
        'orders' => $orders_counted,
        'customers' => $customers,
        'unplaced' => (int) $state['unplaced'],
        'tiles' => $tiles,
        'months' => $month_table,
        'cohorts' => $cohort_table,
        'cells' => $cells,
        'top' => array('all' => _pg_customer_value_top_sort($top_all), 'year' => _pg_customer_value_top_sort($top_year)),
        'apart' => $apart,
    );
}

/**
 * The summary as the settings text holds it. Pure.
 *
 * @param array $summary
 * @return string
 */
function pg_customer_value_summary_encode($summary)
{
    return (string) json_encode($summary, JSON_PRESERVE_ZERO_FRACTION);
}

/**
 * @param string $json
 * @return array|null
 */
function pg_customer_value_summary_decode($json)
{
    $summary = json_decode((string) $json, true);

    return (is_array($summary) && isset($summary['v'], $summary['at'])) ? $summary : null;
}

/**
 * The summary the last finished rebuild wrote, or null before the first.
 *
 * @return array|null
 */
function pg_customer_value_summary()
{
    return pg_customer_value_summary_decode(pg_config_text('customer_values'));
}

/**
 * The read of one page of orders after the place ($date, $id), in date
 * order on the order_date index, up to the pass's start. A column the site
 * does not have yet reads as its default. Pure.
 *
 * @param int   $until
 * @param int   $date
 * @param int   $id
 * @param int   $limit
 * @param array $present  the optional columns the orders table has
 * @return array  array(sql, params)
 */
function pg_customer_value_page_query($until, $date, $id, $limit, $present)
{
    $optional = array('refunded_amount' => '0', 'type' => "'online'", 'cancelled_at' => 'NULL');
    $columns = array('id', 'order_date', 'order_number', 'billing_email_address', 'contact_id', 'total', 'currency_code', 'status');

    foreach ($optional as $column => $default) {
        $columns[] = in_array($column, $present, true) ? $column : $default . ' AS ' . $column;
    }

    $sql = 'SELECT ' . implode(', ', $columns) . ' FROM orders'
        . " WHERE (status IN ('complete', 'exported')) AND (order_date <= ?)"
        . ' AND (order_date >= ?) AND ((order_date > ?) OR (id > ?))'
        . ' ORDER BY order_date, id LIMIT ' . max(1, (int) $limit);

    return array($sql, array((int) $until, (int) $date, (int) $date, (int) $id));
}

/**
 * Where the walk's state lives between runs.
 *
 * @return string
 */
function pg_customer_value_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/customer_values.state';
}

/**
 * The pass record (config_text 'customer_values_pass'), read past the
 * request cache since it is compared and swapped: lease (until when a run
 * holds it), started, done, total.
 *
 * @return array  array(raw text, record)
 */
function pg_customer_value_pass_read()
{
    $raw = (string) db_value("SELECT value FROM config_text WHERE name = 'customer_values_pass'");
    $record = json_decode($raw, true);

    if (!is_array($record)) {
        $record = array();
    }

    return array($raw, array_merge(array('lease' => 0, 'started' => 0, 'done' => 0, 'total' => 0), $record));
}

/**
 * Whether a rebuild is due: one under way, or a summary missing or older
 * than PG_CUSTOMER_VALUE_INTERVAL. Pure.
 *
 * @param array    $record
 * @param int|null $summary_at
 * @param int      $now
 * @return bool
 */
function pg_customer_value_due($record, $summary_at, $now)
{
    if ((int) $record['started'] > 0) {
        return true;
    }

    return ($summary_at === null) || (((int) $now - (int) $summary_at) >= PG_CUSTOMER_VALUE_INTERVAL);
}

/**
 * The general job's rebuild: under one claim of the pass record, walk the
 * orders for at most $budget seconds from where the last run stopped; at
 * the end of the walk write the summary in one write. A run that loses the
 * claim, or finds another run's lease alive, does nothing.
 *
 * @param int      $budget  seconds
 * @param int|null $now
 * @return array|null  array('done' => bool, 'read' => orders read) or null when nothing ran
 */
function pg_customer_value_job_run($budget = PG_CUSTOMER_VALUE_TICK_SECONDS, $now = null)
{
    if (!defined('ECOMMERCE') || !ECOMMERCE || !pg_schema_has('config_text') || !pg_schema_has('orders')) {
        return null;
    }

    $now = ($now === null) ? time() : (int) $now;
    $budget = max(1, (int) $budget);
    $clock = microtime(true);

    list($raw, $record) = pg_customer_value_pass_read();

    if ((int) $record['lease'] > $now) {
        return null;
    }

    $summary = pg_customer_value_summary();

    if (!pg_customer_value_due($record, ($summary === null) ? null : (int) $summary['at'], $now)) {
        return null;
    }

    $state = null;
    $path = pg_customer_value_state_path();

    if (((int) $record['started'] > 0) && is_file($path)) {
        $state = pg_customer_value_state_decode((string) @file_get_contents($path));

        if (($state !== null) && ((int) $state['started'] !== (int) $record['started'])) {
            $state = null;
        }
    }

    if ($state === null) {
        $state = pg_customer_value_state_new($now);
        $state['total'] = (int) db_value("SELECT COUNT(*) FROM orders WHERE (status IN ('complete', 'exported')) AND (order_date > 0) AND (order_date <= " . (int) $now . ")");
    }

    $lease = array('lease' => $now + $budget + 60, 'started' => (int) $state['started'], 'done' => (int) $state['done'], 'total' => (int) $state['total']);

    if (!pg_config_text_claim('customer_values_pass', $raw, json_encode($lease))) {
        return null;
    }

    $present = array();

    foreach (array('refunded_amount', 'type', 'cancelled_at') as $column) {
        if (pg_schema_has('orders', $column)) {
            $present[] = $column;
        }
    }

    $base = defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : 'USD';
    $read = 0;
    $finished = false;

    while (true) {
        list($sql, $params) = pg_customer_value_page_query($state['started'], $state['date'], $state['id'], PG_CUSTOMER_VALUE_PAGE, $present);
        $result = db_q($sql, $params);

        if ($result === false) {
            break;
        }

        $rows = array();

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        mysqli_free_result($result);
        pg_customer_value_add_rows($state, $rows, $base);
        $read += count($rows);

        if (count($rows) < PG_CUSTOMER_VALUE_PAGE) {
            $finished = true;
            break;
        }

        if ((microtime(true) - $clock) >= $budget) {
            break;
        }
    }

    $claimed = json_encode($lease);

    if ($finished) {
        $saved = pg_config_text_save('customer_values', pg_customer_value_summary_encode(pg_customer_value_finish($state, $base)));
        @unlink($path);
        // A summary too large for its text keeps the last one; the pass
        // ends all the same and is tried again after the interval.
        pg_config_text_claim('customer_values_pass', $claimed, json_encode(array('lease' => 0, 'started' => 0, 'done' => (int) $state['done'], 'total' => (int) $state['total'], 'saved' => $saved ? 1 : 0)));

        return array('done' => true, 'read' => $read);
    }

    // Written whole, then moved, so a run killed while writing leaves the
    // last good state and its place behind.
    $temp = $path . '.' . getmypid();

    if (@file_put_contents($temp, pg_customer_value_state_encode($state)) !== false) {
        @rename($temp, $path);
    }

    pg_config_text_claim('customer_values_pass', $claimed, json_encode(array('lease' => 0, 'started' => (int) $state['started'], 'done' => (int) $state['done'], 'total' => (int) $state['total'])));

    return array('done' => false, 'read' => $read);
}

/**
 * The periods the Customers report offers, as the summary keys its tiles:
 * the last whole months, or all time. Pure.
 *
 * @return array key => label
 */
function pg_customer_value_periods()
{
    return array(
        '1' => lang('Last month'),
        '3' => lang('3 months'),
        '6' => lang('6 months'),
        '12' => lang('12 months'),
        '24' => lang('24 months'),
        'all' => lang('All time'),
    );
}

/**
 * The period the screen shows: the one asked for when it is offered, else
 * the one kept, else twelve months. Pure.
 *
 * @param mixed $asked
 * @param mixed $kept
 * @return string
 */
function pg_customer_value_period_pick($asked, $kept)
{
    $periods = pg_customer_value_periods();

    foreach (array($asked, $kept) as $value) {
        if (is_string($value) && isset($periods[$value])) {
            return $value;
        }
    }

    return '12';
}

/**
 * Which group a cell of the groups table is: new, returning, loyal, at_risk
 * or lost, by its age row (pg_customer_value_cell_age()) and orders column
 * (pg_customer_value_cell_band()). Every cell has exactly one group. Pure.
 *
 * @param int $age
 * @param int $band
 * @return string
 */
function pg_customer_value_cell_group($age, $band)
{
    if ($age >= 4) {
        return 'lost';
    }

    if ($age === 3) {
        return 'at_risk';
    }

    if ($age === 2) {
        return array('at_risk', 'returning', 'loyal')[$band];
    }

    return array('new', 'returning', 'loyal')[$band];
}

/**
 * Each group's bounds as the screen states them beside its name. Pure.
 *
 * @return array key => text
 */
function pg_customer_value_group_bounds()
{
    return array(
        'new' => lang('one order, the last within 90 days'),
        'returning' => lang('two to three orders, the last within 180 days'),
        'loyal' => lang('four orders or more, the last within 180 days'),
        'at_risk' => lang('the last order 181 to 365 days ago, or one order 91 to 180 days ago'),
        'lost' => lang('the last order over 365 days ago'),
    );
}

/**
 * The groups' names as the screen writes them.
 *
 * @return array key => label
 */
function pg_customer_value_group_names()
{
    return array(
        'new' => lang('New customers'),
        'returning' => lang('Returning customers'),
        'loyal' => lang('Loyal customers'),
        'at_risk' => lang('At-risk customers'),
        'lost' => lang('Lost customers'),
    );
}

/**
 * A share as the screen writes it, one decimal; '' with nothing to share
 * out. Pure.
 *
 * @param int|float $part
 * @param int|float $whole
 * @return string
 */
function pg_customer_value_share($part, $whole)
{
    if ($whole <= 0) {
        return '';
    }

    return lang(array('string' => '{var:1}%', 'vars' => pg_format_number($part / $whole * 100, 1)));
}

/**
 * The report's tiles for one period: customers, repeat purchase rate,
 * orders per customer, value per customer, days between orders. Pure.
 *
 * @param array  $tile   a summary tile: customers, customers with two
 *                       orders or more, orders, value, days between orders
 * @param string $symbol the base currency's symbol
 * @return array pg_report_view_figure() arrays
 */
function pg_customer_value_figures($tile, $symbol)
{
    $customers = (int) $tile[0];
    $repeat = (int) $tile[1];
    $orders = (int) $tile[2];
    $value = (int) $tile[3];
    $days = (float) $tile[4];

    return array(
        pg_report_view_figure(lang('Customers'), pg_format_number($customers, 0), 'bi-people', 'blue'),
        pg_report_view_figure(lang('Repeat purchase rate'), ($customers > 0) ? pg_customer_value_share($repeat, $customers) : '–', 'bi-arrow-repeat', 'violet', lang(array('string' => '{var:1} with two orders or more', 'vars' => pg_format_number($repeat, 0)))),
        pg_report_view_figure(lang('Orders per customer'), ($customers > 0) ? pg_format_number($orders / $customers, 2) : '–', 'bi-bag', 'teal', lang(array('string' => '{var:1} order{suffix:1}', 'vars' => pg_format_number($orders, 0), 'suffix' => ($orders == 1) ? '' : 's'))),
        pg_report_view_figure(lang('Value per customer'), ($customers > 0) ? pg_report_view_money($value / $customers, $symbol) : '–', 'bi-cash-stack', 'amber', pg_report_view_money($value, $symbol)),
        pg_report_view_figure(lang('Days between orders'), ($days > 0) ? pg_format_number($days, 1) : '–', 'bi-calendar3', 'gray'),
    );
}

/**
 * The months of a period that the summary holds, oldest first: for N whole
 * months the N before the summary's month, for all time every month kept.
 * Pure.
 *
 * @param array  $summary
 * @param string $period
 * @return array 'YYYY-MM' => array(first orders, first value, returning orders, returning value)
 */
function pg_customer_value_period_months($summary, $period)
{
    $months = isset($summary['months']) ? (array) $summary['months'] : array();

    if ($period === 'all') {
        return $months;
    }

    // The month the summary was worked out in is the last key and is not a
    // whole month yet.
    $keys = array_keys($months);
    array_pop($keys);
    $keys = array_slice($keys, -max(1, (int) $period));

    return array_intersect_key($months, array_flip($keys));
}

/**
 * A 'YYYY-MM' month as the screen names it ('Kas 2026'). Pure.
 *
 * @param string $key
 * @return string
 */
function pg_customer_value_month_name($key)
{
    $time = mktime(12, 0, 0, (int) substr((string) $key, 5, 2), 1, (int) substr((string) $key, 0, 4));

    return lang(date('M', $time)) . ' ' . date('Y', $time);
}

/**
 * The chart of the months: value from first-time customers, with the value
 * from returning customers dashed beside it. Null for fewer than two months
 * or none with a sale. Pure.
 *
 * @param array  $months pg_customer_value_period_months()
 * @param string $symbol
 * @return array|null
 */
function pg_customer_value_month_chart($months, $symbol)
{
    if (count($months) < 2) {
        return null;
    }

    $first = lang('First-time customers');
    $returning = lang('Returning customers');
    $chart = array('kind' => 'line', 'label' => lang('Value by month'), 'series' => $first, 'compare_label' => $returning, 'values' => array(), 'compare' => array(), 'names' => array(), 'notes' => array(), 'compare_notes' => array(), 'tips' => array());
    $any = false;

    foreach ($months as $key => $row) {
        $name = pg_customer_value_month_name($key);
        $any = $any || ($row[1] > 0) || ($row[3] > 0);
        $chart['values'][] = round($row[1] / 100, 2);
        $chart['compare'][] = round($row[3] / 100, 2);
        $chart['names'][] = $name;
        $chart['notes'][] = pg_report_view_money($row[1], $symbol);
        $chart['compare_notes'][] = pg_report_view_money($row[3], $symbol);
        $chart['tips'][] = $name . ': ' . $first . ' ' . pg_report_view_money($row[1], $symbol) . ' · ' . $returning . ' ' . pg_report_view_money($row[3], $symbol);
    }

    return $any ? $chart : null;
}

/**
 * The cohort table's rows, newest cohort first: the month of the first
 * order, its customers and, for each of the twelve months after it, the
 * share that ordered again; null for a month that has not begun. Pure.
 *
 * @param array $summary
 * @param int   $count cohorts shown
 * @return array rows array('month' => 'YYYY-MM', 'size' => int, 'shares' => (string|null)[12])
 */
function pg_customer_value_cohort_rows($summary, $count = 12)
{
    $cohorts = isset($summary['cohorts']) ? (array) $summary['cohorts'] : array();
    $rows = array();
    $last = pg_customer_value_month((int) $summary['at']);

    foreach (array_slice($cohorts, -max(1, (int) $count), null, true) as $key => $row) {
        $month = ((int) substr($key, 0, 4) * 12) + (int) substr($key, 5, 2) - 1;
        $shares = array();

        for ($k = 1; $k <= 12; $k++) {
            $shares[] = (($month + $k) > $last) ? null : pg_customer_value_share((int) $row[$k], (int) $row[0]);
        }

        $rows[] = array('month' => (string) $key, 'size' => (int) $row[0], 'shares' => $shares);
    }

    return array_reverse($rows);
}

/**
 * The top customers' rows. A name and the order to open are given only to
 * a reader who may read contacts; others get the figures. Pure.
 *
 * @param array $top    summary rows array(last order number, value, orders)
 * @param array $orders order number => array('id' => int, 'name' => string),
 *                      read only for a reader who may see names
 * @param bool  $names  whether the reader may see names
 * @return array rows: rank, name, orders, value, order_id (0 when hidden)
 */
function pg_customer_value_top_rows($top, $orders, $names)
{
    $rows = array();

    foreach (array_values((array) $top) as $i => $row) {
        $number = (int) $row[0];
        $known = ($names && isset($orders[$number])) ? $orders[$number] : array('id' => 0, 'name' => '');

        $rows[] = array(
            'rank' => $i + 1,
            'name' => (string) $known['name'],
            'orders' => (int) $row[2],
            'value' => (int) $row[1],
            'order_id' => (int) $known['id'],
        );
    }

    return $rows;
}

/**
 * The orders the top customers are kept by, with the billing name each one
 * carries now: read when the screen is shown, so an erased person's name is
 * never kept. Call only for a reader who may read contacts.
 *
 * @param array $numbers order numbers
 * @return array order number => array('id' => int, 'name' => string)
 */
function pg_customer_value_order_names($numbers)
{
    $numbers = array_values(array_unique(array_filter(array_map('intval', (array) $numbers))));

    if (empty($numbers)) {
        return array();
    }

    $result = db_q(
        'SELECT id, order_number, billing_first_name, billing_last_name, billing_company FROM orders WHERE order_number IN (' . implode(', ', array_fill(0, count($numbers), '?')) . ')',
        $numbers);
    $names = array();

    if ($result === false) {
        return $names;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $name = trim($row['billing_first_name'] . ' ' . $row['billing_last_name']);
        $names[(int) $row['order_number']] = array('id' => (int) $row['id'], 'name' => ($name !== '') ? $name : trim((string) $row['billing_company']));
    }

    mysqli_free_result($result);

    return $names;
}

/**
 * Whether the orders behind a summary are too few to say anything: fewer
 * than twenty customers. Pure.
 *
 * @param array $summary
 * @return bool
 */
function pg_customer_value_too_little($summary)
{
    return (int) ($summary['customers'] ?? 0) < 20;
}
