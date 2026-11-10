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

// Addresses one run looks at: those whose service has the switch off are
// passed over, so more are read than are labelled.
define('PG_SHIPMENT_LABEL_AUTO_CANDIDATES', 50);

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
 * @param int $from  the earliest payment time taken
 * @param int $now
 * @param int $limit
 * @return array rows of ship_to_id, order_id, order_number, paid_at
 */
function pg_shipment_label_auto_candidates($from, $now, $limit)
{
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
                WHERE shipping_tracking_numbers.ship_to_id = ship_tos.id)
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

    foreach (pg_shipment_label_auto_candidates($from, $now, PG_SHIPMENT_LABEL_AUTO_CANDIDATES) as $candidate) {
        if (($out['created'] + $out['failed']) >= PG_SHIPMENT_LABEL_AUTO_LIMIT) {
            break;
        }

        $check = pg_shipment_label_create_check((int) $candidate['ship_to_id']);

        if (!$check['ok'] || !pg_shipment_label_auto_due($check['driver'], $candidate['paid_at'], $auto, $now)) {
            continue;
        }

        $result = pg_shipment_label_create((int) $candidate['ship_to_id'], 0);

        if (!empty($result['ok'])) {
            $out['created']++;
            continue;
        }

        // Refused before the service was asked: nothing was tried.
        if ((string) $result['code'] !== '') {
            continue;
        }

        $values = array();

        foreach (pg_shipment_label_failed_row($candidate['order_id'], $candidate['ship_to_id'], $check['driver'], $now) as $column => $value) {
            $values[] = "`" . $column . "` = '" . e((string) $value) . "'";
        }

        db("INSERT IGNORE INTO shipment_labels SET " . implode(', ', $values));
        $out['failed']++;

        log_activity(lang(array(
            'string' => 'The automatic shipping label of order {var:1} could not be created; create it on the order: {var:2}',
            'vars'   => array($candidate['order_number'], $result['error']),
        )), 'UNKNOWN');
    }

    return $out;
}
