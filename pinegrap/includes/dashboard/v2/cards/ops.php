<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Operations Desk card: three lanes, To ship, To refund and Out of stock,
 * each a count and up to three rows. The counts and rows are the ones
 * dashboard widget 9 reads (includes/dashboard/widgets/widget_9.php), behind
 * the same gates.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

if (!defined('PG_DASHBOARD_V2_OPS_ROWS')) {
    // Rows a lane shows; the count and a "more" link carry the rest.
    define('PG_DASHBOARD_V2_OPS_ROWS', 3);
}

/**
 * One lane as the renderer draws it.
 *
 * @param string $id    ship, refund or stock
 * @param string $label
 * @param int    $count every item waiting, not only the rows
 * @param array  $rows  text, note, tone, url
 * @param string $sub   the line under the count
 * @param string $tone  bad, warn or '' for the count
 * @param string $more  the "more" link's text, '' when every item is a row
 * @param string $url   where the "more" link goes
 * @param string $empty what an empty lane says
 * @return array id, label, count, count_text, sub, tone, rows, more, url, empty
 */
function pg_dashboard_v2_ops_lane($id, $label, $count, $rows, $sub, $tone, $more, $url, $empty)
{
    $count = max(0, (int) $count);
    $rows = array_slice(array_values($rows), 0, PG_DASHBOARD_V2_OPS_ROWS);

    return array(
        'id'         => (string) $id,
        'label'      => (string) $label,
        'count'      => $count,
        'count_text' => pg_format_number($count, 0),
        'sub'        => (string) $sub,
        'tone'       => ($count > 0) ? (string) $tone : '',
        'rows'       => $rows,
        'more'       => ($count > count($rows)) ? (string) $more : '',
        'url'        => (string) $url,
        'empty'      => empty($rows) ? (string) $empty : '',
    );
}

/**
 * The parcels lane's rows from widget 9's shipments: "#1042 · Ayşe Y. · 2
 * packages", how long it has waited, red past two days as the widget marks it.
 *
 * @param array $shipments order_id, order_number, ship_to_name, order_date, pending_quantity
 * @param int   $now
 * @return array text, note, tone, url
 */
function pg_dashboard_v2_ops_ship_rows($shipments, $now)
{
    $rows = array();

    foreach ($shipments as $shipment) {
        $packages = (int) $shipment['pending_quantity'];
        $parts = array('#' . (string) $shipment['order_number']);

        if (trim((string) ($shipment['ship_to_name'] ?? '')) !== '') {
            $parts[] = trim((string) $shipment['ship_to_name']);
        }

        $parts[] = lang(array(
            'string' => '{var:1} package{suffix:1}',
            'vars'   => pg_format_number($packages, 0),
            'suffix' => ($packages === 1) ? '' : 's',
        ));

        $rows[] = array(
            'text' => implode(' · ', $parts),
            'note' => pg_dashboard_v2_ops_when((int) $shipment['order_date']),
            'tone' => (($now - (int) $shipment['order_date']) >= 172800) ? 'bad' : '',
            'url'  => 'view_order.php?id=' . (int) $shipment['order_id'],
        );
    }

    return $rows;
}

/**
 * The refunds lane's rows: "#1039 · Selin K.", the money owed and since when.
 *
 * @param array $refunds id, order_number, name, owed (minor units), cancelled_at
 * @param int   $now
 * @return array text, note, tone, url
 */
function pg_dashboard_v2_ops_refund_rows($refunds, $now)
{
    $rows = array();

    foreach ($refunds as $refund) {
        $number = ((string) $refund['order_number'] !== '') ? (string) $refund['order_number'] : (string) (int) $refund['id'];
        $name = trim((string) $refund['name']);

        $rows[] = array(
            'text' => '#' . $number . ' · ' . (($name !== '') ? $name : lang('Unknown')),
            'note' => pg_dashboard_v2_money($refund['owed']) . ' · ' . pg_dashboard_v2_ops_when((int) $refund['cancelled_at']),
            'tone' => (($now - (int) $refund['cancelled_at']) >= 172800) ? 'bad' : 'warn',
            'url'  => 'view_order.php?id=' . (int) $refund['id'],
        );
    }

    return $rows;
}

/**
 * The stock lane's rows: the product and since when it is out of stock.
 *
 * @param array $products id, name, short_description, timestamp
 * @return array text, note, tone, url
 */
function pg_dashboard_v2_ops_stock_rows($products)
{
    $rows = array();

    foreach ($products as $product) {
        $title = trim((string) $product['short_description']);

        $rows[] = array(
            'text' => ($title !== '') ? $title : (string) $product['name'],
            'note' => ((int) $product['timestamp'] > 0) ? pg_dashboard_v2_ops_when((int) $product['timestamp']) : '',
            'tone' => 'bad',
            'url'  => 'edit_product.php?id=' . (int) $product['id'],
        );
    }

    return $rows;
}

/**
 * A time as plain text ("2 days ago"), for a card text written with
 * textContent.
 *
 * @param int $timestamp
 * @return string
 */
function pg_dashboard_v2_ops_when($timestamp)
{
    if (!function_exists('get_relative_time')) {
        return '';
    }

    return get_relative_time(array('timestamp' => (int) $timestamp, 'format' => 'plain_text'));
}
