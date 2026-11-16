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
    $ages = array(30, 90, 180, 365);

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

        $days = ($now - $last) / 86400;
        $age = count($ages);

        foreach ($ages as $i => $bound) {
            if ($days <= $bound) {
                $age = $i;
                break;
            }
        }

        $band = ($orders >= 4) ? 2 : (($orders >= 2) ? 1 : 0);
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
