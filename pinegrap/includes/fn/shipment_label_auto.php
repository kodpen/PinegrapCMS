<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Automatic shipping labels - the label of a paid order's address made by
 * the general job, with the carrier label service its shipping method or
 * carrier takes, while that service's "create the label once the order is
 * paid" switch is on (connect_apps label_<key> options auto_label and
 * auto_label_since, includes/fn/carrier_labels.php).
 *
 * Never run in the checkout request: job.php calls pg_shipment_label_auto_run()
 * each tick. An address is taken when its order is not incomplete or
 * cancelled, was paid after the switch was turned on and within the last
 * PG_SHIPMENT_LABEL_AUTO_DAYS days, and the address has no outbound label row
 * and no tracking number. At most PG_SHIPMENT_LABEL_AUTO_LIMIT labels a run.
 *
 * Each address is tried once. A failure (the service refused, or did not say)
 * writes a shipment_labels row in state failed, reference failed-<ship_to_id>,
 * without number or file, and a Site Log line; the order screen shows it and
 * keeps Create Label, so staff make the label by hand.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Labels one run creates; the rest wait for the next tick.
define('PG_SHIPMENT_LABEL_AUTO_LIMIT', 10);

// Days after payment an order still gets its label automatically.
define('PG_SHIPMENT_LABEL_AUTO_DAYS', 3);

// Addresses one read takes: those whose service has the switch off, or
// whose marketplace makes the label, are passed over, so more are read than
// are labelled.
define('PG_SHIPMENT_LABEL_AUTO_CANDIDATES', 50);

// Addresses one run looks at at most, read page after page: passed-over
// addresses stay candidates for days and must not hide the ones after them.
define('PG_SHIPMENT_LABEL_AUTO_READ_MAX', 500);

// Days before payment an order may have been placed (a bank transfer paid
// late): the query goes by the indexed order_date.
define('PG_SHIPMENT_LABEL_AUTO_ORDER_DAYS', 60);

/**
 * When a driver's automatic label was switched on, 0 while it is off. Pure.
 *
 * @param array $settings pg_label_settings()
 * @return int unix time
 */
function pg_shipment_label_auto_since($settings)
{
    if (!is_array($settings) || empty($settings['enabled']) || !isset($settings['values']) || !is_array($settings['values'])) {
        return 0;
    }

    $values = $settings['values'];

    if (!isset($values['auto_label']) || ((string) $values['auto_label'] !== '1')) {
        return 0;
    }

    $since = isset($values['auto_label_since']) ? (int) $values['auto_label_since'] : 0;

    return max(0, $since);
}

/**
 * The drivers that make labels automatically now: switched on, set up, able
 * to create, with the switch on.
 *
 * @return array key => since (unix time)
 */
function pg_shipment_label_auto_drivers()
{
    $auto = array();

    foreach (array_keys(pg_shipment_label_drivers()) as $key) {
        $since = pg_shipment_label_auto_since(pg_label_settings($key));

        if ($since > 0) {
            $auto[$key] = $since;
        }
    }

    return $auto;
}

/**
 * Whether an address paid at $paid_at gets its label automatically from the
 * driver it would be made with. Pure.
 *
 * @param string $driver  the address's driver (pg_shipment_label_create_check()), '' for none
 * @param int    $paid_at orders.paid_at
 * @param array  $auto    pg_shipment_label_auto_drivers()
 * @param int    $now
 * @return bool
 */
function pg_shipment_label_auto_due($driver, $paid_at, $auto, $now)
{
    $driver = (string) $driver;
    $paid_at = (int) $paid_at;

    if (($driver === '') || ($paid_at <= 0) || !isset($auto[$driver])) {
        return false;
    }

    return ($paid_at >= (int) $auto[$driver]) && ($paid_at >= ((int) $now - (PG_SHIPMENT_LABEL_AUTO_DAYS * 86400)));
}

/**
 * The shipment_labels row that marks an address whose automatic label could
 * not be made. Its reference is unique per driver and address, so a second
 * write of the same failure is refused by the table. Pure.
 *
 * @param int    $order_id
 * @param int    $ship_to_id
 * @param string $driver
 * @param int    $now
 * @return array column => value
 */
function pg_shipment_label_failed_row($order_id, $ship_to_id, $driver, $now)
{
    return array(
        'order_id'         => (int) $order_id,
        'ship_to_id'       => (int) $ship_to_id,
        'number'           => '',
        'driver'           => (string) $driver,
        'reference'        => 'failed-' . (int) $ship_to_id,
        'barcode'          => '',
        'label_format'     => '',
        'label_file'       => '',
        'state'            => 'failed',
        'is_return'        => 0,
        'user_id'          => 0,
        'created_at'       => (int) $now,
        'cancelled_at'     => 0,
        'label_removed_at' => 0,
    );
}

/**
 * The addresses that may get an automatic label now, oldest payment first:
 * order not incomplete or cancelled, paid since $from, an active line on the
 * address, no outbound label row (a failed one included) and no tracking
 * number.
 *
 * @param int        $from  the earliest payment time taken
 * @param int        $now
 * @param int        $limit
 * @param array|null $after paid_at and ship_to_id of the last address read,
 *                          null for the first page
 * @return array rows of ship_to_id, order_id, order_number, paid_at
 */
function pg_shipment_label_auto_candidates($from, $now, $limit, $after = null)
{
    $next = is_array($after)
        ? "
            AND ((orders.paid_at > '" . (int) $after['paid_at'] . "')
                OR ((orders.paid_at = '" . (int) $after['paid_at'] . "') AND (ship_tos.id > '" . (int) $after['ship_to_id'] . "')))"
        : '';

    return db_items(
        "SELECT
            ship_tos.id AS ship_to_id,
            orders.id AS order_id,
            orders.order_number,
            orders.paid_at
        FROM orders
        INNER JOIN ship_tos ON ship_tos.order_id = orders.id
        WHERE
            (orders.order_date >= '" . ((int) $now - (PG_SHIPMENT_LABEL_AUTO_ORDER_DAYS * 86400)) . "')
            AND (orders.paid_at >= '" . (int) $from . "')
            AND (orders.status NOT IN ('incomplete', 'cancelled'))
            AND EXISTS (
                SELECT 1 FROM order_items
                WHERE (order_items.ship_to_id = ship_tos.id)" . pg_order_item_active_sql() . ")
            AND NOT EXISTS (
                SELECT 1 FROM shipment_labels
                WHERE (shipment_labels.ship_to_id = ship_tos.id) AND (shipment_labels.is_return = '0'))
            AND NOT EXISTS (
                SELECT 1 FROM shipping_tracking_numbers
                WHERE shipping_tracking_numbers.ship_to_id = ship_tos.id)" . $next . "
        ORDER BY orders.paid_at, ship_tos.id
        LIMIT " . (int) $limit);
}

/**
 * Make the labels of the paid orders' addresses whose service has the
 * automatic label switched on, up to PG_SHIPMENT_LABEL_AUTO_LIMIT. A refusal
 * before the service is asked (another create running, a marketplace's own
 * label) writes nothing: the address is looked at again next run. A failure
 * of the service writes the failed row and a Site Log line.
 *
 * @param int $now 0 for time()
 * @return array created, failed
 */
function pg_shipment_label_auto_run($now = 0)
{
    $out = array('created' => 0, 'failed' => 0);
    $now = ((int) $now > 0) ? (int) $now : time();

    if (!pg_shipment_labels_ready() || !pg_schema_has('orders', 'paid_at')) {
        return $out;
    }

    $auto = pg_shipment_label_auto_drivers();

    if (!$auto) {
        return $out;
    }

    $from = max(min($auto), $now - (PG_SHIPMENT_LABEL_AUTO_DAYS * 86400));

    $read = function ($after, $limit) use ($from, $now) {
        return pg_shipment_label_auto_candidates($from, $now, $limit, $after);
    };

    $try = function ($candidate) use ($auto, $now) {
        return pg_shipment_label_auto_try($candidate, $auto, $now);
    };

    return pg_shipment_label_auto_walk($read, $try, PG_SHIPMENT_LABEL_AUTO_LIMIT, PG_SHIPMENT_LABEL_AUTO_CANDIDATES, PG_SHIPMENT_LABEL_AUTO_READ_MAX);
}

/**
 * The loop of a run: candidates are read page after page, each page after
 * the last address of the one before (paid_at, ship_to_id), until the
 * labels and failures reach $limit, the candidates end or $read_max
 * addresses were looked at. An address passed over (''), however many, does
 * not stop the run from reaching the ones paid after it. Pure.
 *
 * @param callable $read     function ($after, $limit): rows with paid_at and ship_to_id
 * @param callable $try      function ($row): 'created', 'failed' or ''
 * @param int      $limit    labels and failures a run
 * @param int      $page     addresses a read
 * @param int      $read_max addresses a run looks at
 * @return array created, failed
 */
function pg_shipment_label_auto_walk($read, $try, $limit, $page, $read_max)
{
    $out = array('created' => 0, 'failed' => 0);
    $after = null;
    $seen = 0;

    while ($seen < (int) $read_max) {
        $rows = $read($after, min((int) $page, (int) $read_max - $seen));

        foreach ((array) $rows as $row) {
            if (($out['created'] + $out['failed']) >= (int) $limit) {
                return $out;
            }

            $seen++;
            $after = array('paid_at' => (int) $row['paid_at'], 'ship_to_id' => (int) $row['ship_to_id']);
            $outcome = (string) $try($row);

            if (isset($out[$outcome])) {
                $out[$outcome]++;
            }
        }

        if (count((array) $rows) < (int) $page) {
            break;
        }
    }

    return $out;
}

/**
 * One candidate of a run: its label made when its driver's switch is on
 * and it was paid since; a failure of the service leaves the failed row and
 * a Site Log line.
 *
 * @param array $candidate pg_shipment_label_auto_candidates() row
 * @param array $auto      pg_shipment_label_auto_drivers()
 * @param int   $now
 * @return string 'created', 'failed', or '' when it was passed over
 */
function pg_shipment_label_auto_try($candidate, $auto, $now)
{
    $check = pg_shipment_label_create_check((int) $candidate['ship_to_id']);

    if (!$check['ok'] || !pg_shipment_label_auto_due($check['driver'], $candidate['paid_at'], $auto, $now)) {
        return '';
    }

    $result = pg_shipment_label_create((int) $candidate['ship_to_id'], 0);

    if (!empty($result['ok'])) {
        return 'created';
    }

    // Refused before the service was asked: nothing was tried.
    if ((string) $result['code'] !== '') {
        return '';
    }

    $values = array();

    foreach (pg_shipment_label_failed_row($candidate['order_id'], $candidate['ship_to_id'], $check['driver'], $now) as $column => $value) {
        $values[] = "`" . $column . "` = '" . e((string) $value) . "'";
    }

    db("INSERT IGNORE INTO shipment_labels SET " . implode(', ', $values));

    log_activity(lang(array(
        'string' => 'The automatic shipping label of order {var:1} could not be created; create it on the order: {var:2}',
        'vars'   => array($candidate['order_number'], $result['error']),
    )), 'UNKNOWN');

    return 'failed';
}
