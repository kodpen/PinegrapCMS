<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Delivery tower: what the hourly problem pass tells. Staff hear of new
 * problems through the operations alerts (codes parcel_stuck,
 * parcel_not_handed_over, parcel_problem; group 'shipping'): one bell row per
 * code at the end of a walk, with the count and the first orders, once a day
 * per code. Webhooks and flows hear of each problem opened and each one a
 * later status settled, as shipment.problem and shipment.problem_resolved.
 *
 * The payload names the order, the parcel, its carrier and province; never a
 * street address. order_type lets a flow leave a marketplace's buyer out,
 * whom the shop never writes to.
 *
 * The first walk on a site opens the rows for every parcel already in
 * trouble; it announces none of them, so a flow does not write to every
 * customer of the last 90 days at once. Its alerts are sent.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Orders an alert names before "and n more".
define('PG_DELIVERY_TOWER_ALERT_NAMED', 5);

/**
 * The alert code each problem kind is told under. A returned parcel is told
 * with the delivery problems. Pure.
 *
 * @return array kind => code of pg_ops_alert_codes()
 */
function pg_delivery_tower_alert_codes()
{
    return array(
        'stuck' => 'parcel_stuck',
        'not_handed_over' => 'parcel_not_handed_over',
        'problem' => 'parcel_problem',
        'returned' => 'parcel_problem',
    );
}

/**
 * Counts one opened problem into a walk's tally, kept in the pass record
 * between ticks: per code the count and the first orders, each once. Pure.
 *
 * @param array  $told code => array(count, orders)
 * @param string $kind
 * @param string $order_number
 */
function pg_delivery_tower_told_add(&$told, $kind, $order_number)
{
    $codes = pg_delivery_tower_alert_codes();
    if (!isset($codes[$kind])) {
        return;
    }
    $code = $codes[$kind];
    if (!isset($told[$code])) {
        $told[$code] = array('count' => 0, 'orders' => array());
    }
    $told[$code]['count'] = (int) $told[$code]['count'] + 1;
    $order_number = (string) $order_number;
    if (($order_number !== '') && !in_array($order_number, $told[$code]['orders'], true)
        && (count($told[$code]['orders']) < PG_DELIVERY_TOWER_ALERT_NAMED)) {
        $told[$code]['orders'][] = $order_number;
    }
}

/**
 * The alert's sentence: how many parcels, the first orders, how many more.
 *
 * @param string $code
 * @param array  $tally count, orders
 * @return string
 */
function pg_delivery_tower_alert_detail($code, $tally)
{
    $count = (int) $tally['count'];
    $orders = (array) $tally['orders'];

    switch ($code) {
        case 'parcel_stuck':
            $sentence = lang(array('string' => '{var:1} parcel(s) are in transit longer than usual', 'vars' => array($count)));
            break;
        case 'parcel_not_handed_over':
            $sentence = lang(array('string' => '{var:1} parcel(s) were not handed over to the carrier', 'vars' => array($count)));
            break;
        default:
            $sentence = lang(array('string' => '{var:1} parcel(s) have a delivery problem or came back', 'vars' => array($count)));
    }

    if ($orders) {
        $sentence .= '. ' . lang(array('string' => 'Orders: {var:1}', 'vars' => array(implode(', ', $orders))));
    }
    $sentence .= '.';

    if ($count > count($orders) && $orders) {
        $sentence .= ' ' . lang(array('string' => 'And {var:1} more.', 'vars' => array($count - count($orders))));
    }
    return $sentence;
}

/**
 * Sends a walk's alerts, one per code with something in it.
 *
 * @param array $told code => array(count, orders)
 */
function pg_delivery_tower_alerts_send($told)
{
    if (!function_exists('pg_ops_alert')) {
        return;
    }
    foreach ((array) $told as $code => $tally) {
        if (is_array($tally) && ((int) ($tally['count'] ?? 0) > 0)) {
            pg_ops_alert((string) $code, pg_delivery_tower_alert_detail((string) $code, $tally + array('orders' => array())));
        }
    }
}

/**
 * The data of shipment.problem and shipment.problem_resolved. Pure.
 *
 * @param array  $parcel     a row of pg_delivery_tower_parcel_query()
 * @param string $kind       stuck, not_handed_over, problem or returned
 * @param int    $problem_id the shipment_problems row
 * @param array  $figures    the summary's figures
 * @param int    $now
 * @param string $event      which of the two
 * @param string $reason     how it closed, for shipment.problem_resolved
 * @return array
 */
function pg_delivery_tower_event_payload($parcel, $kind, $problem_id, $figures, $now, $event = 'shipment.problem', $reason = '')
{
    $state = trim((string) ($parcel['state'] ?? ''));
    $city = trim((string) ($parcel['city'] ?? ''));
    $province = ($state !== '') ? $state : $city;
    $carrier = (string) ($parcel['carrier'] ?? '');
    $figure = pg_delivery_tower_figure_for($figures, $carrier, pg_delivery_tower_province_key($state, $city));
    $shipped = (int) ($parcel['shipped_at'] ?? 0);

    $payload = array(
        'id'              => (int) ($parcel['order_id'] ?? 0),
        'order_number'    => (string) ($parcel['order_number'] ?? ''),
        'order_type'      => (string) ($parcel['order_type'] ?? ''),
        'problem_id'      => (int) $problem_id,
        'shipment_id'     => (int) ($parcel['id'] ?? 0),
        'number'          => (string) ($parcel['number'] ?? ''),
        'carrier'         => $carrier,
        'kind'            => (string) $kind,
        'province'        => ($province !== '') ? $province : null,
        'days_in_transit' => ($shipped > 0) ? pg_delivery_tower_calendar_days($shipped, (int) $now) : null,
        'usual_days'      => ($figure !== null) ? round((float) $figure['median'], 1) : null,
    );

    if ($event === 'shipment.problem_resolved') {
        $payload['reason'] = (string) $reason;
    }
    return $payload;
}

/**
 * Announces a problem opened, or one settled by a later status.
 *
 * @param bool   $resolved
 * @param array  $parcel
 * @param string $kind
 * @param int    $problem_id
 * @param array  $figures
 * @param int    $now
 * @param string $reason delivered, scanned or status_changed
 */
function pg_delivery_tower_announce($resolved, $parcel, $kind, $problem_id, $figures, $now, $reason = '')
{
    if (!function_exists('pg_announce')) {
        return;
    }
    if ($resolved) {
        pg_announce('shipment.problem_resolved', pg_delivery_tower_event_payload($parcel, $kind, $problem_id, $figures, $now, 'shipment.problem_resolved', $reason));
    } else {
        pg_announce('shipment.problem', pg_delivery_tower_event_payload($parcel, $kind, $problem_id, $figures, $now, 'shipment.problem'));
    }
}
