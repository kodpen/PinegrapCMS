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

function pg_dashboard_v2_card_ops($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('shop', $user)) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_9.php');

    $now = time();
    $limit = PG_DASHBOARD_V2_OPS_ROWS;
    $lanes = array();

    if (defined('ECOMMERCE_SHIPPING') && (ECOMMERCE_SHIPPING === true)) {
        $shipping = pg_dashboard_ops_shipping_summary();
        $count = $shipping['orders'];

        $lanes[] = pg_dashboard_v2_ops_lane('ship', lang('To ship'), $count,
            pg_dashboard_v2_ops_ship_rows(pg_dashboard_ops_shipments($limit), $now),
            ($count > 0)
                ? lang(array('string' => '{var:1} package{suffix:1}', 'vars' => pg_format_number($shipping['packages'], 0), 'suffix' => ($shipping['packages'] === 1) ? '' : 's'))
                    . ' · ' . pg_dashboard_v2_money($shipping['value'])
                : lang('All sent'),
            $shipping['late'] ? 'bad' : 'warn',
            lang('All Orders'),
            // The list's unshipped filter over the lane's window.
            pg_dashboard_list_url('view_orders.php', array(
                'reset'            => 'true',
                'advanced_filters' => 'true',
                'shipping_status'  => 'unshipped'), pg_dashboard_days_back(strtotime(date('Y-m-d', $now)), $shipping['window_days']), $now),
            lang(array('string' => 'Nothing waiting from the last {var:1} days.', 'vars' => $shipping['window_days'])));
    }

    // The refund columns arrive with the 2026.1.27 upgrade; without them
    // there is no lane, as widget 9 draws none.
    if (pg_schema_has('orders', 'refund_status')) {
        $refunds = pg_dashboard_ops_refund_summary();
        $count = $refunds['count'];
        $list = array();

        foreach (pg_dashboard_ops_refund_list($limit) as $order) {
            $list[] = array(
                'id'           => $order['id'],
                'order_number' => $order['order_number'],
                'name'         => trim((string) $order['billing_first_name'] . ' ' . (string) $order['billing_last_name']),
                'owed'         => pg_order_cancel_refund_owed($order)['total'],
                'cancelled_at' => $order['cancelled_at'],
            );
        }

        $lanes[] = pg_dashboard_v2_ops_lane('refund', lang('To refund'), $count,
            pg_dashboard_v2_ops_refund_rows($list, $now),
            ($count > 0) ? pg_dashboard_v2_money($refunds['owed']) : lang('Nothing owed'),
            (($refunds['oldest'] > 0) && ($refunds['oldest'] < ($now - 172800))) ? 'bad' : 'warn',
            lang(array('string' => '{var:1} more waiting', 'vars' => pg_format_number(max(0, $count - $limit), 0))),
            // Every type of order and from the oldest one waiting, as the
            // widget's lane opens the list.
            pg_dashboard_list_url('view_orders.php', array(
                'reset'  => 'true',
                'status' => 'refund_pending',
                'type'   => 'any'), ($refunds['oldest'] > 0) ? $refunds['oldest'] : $now, $now),
            lang('No refunds are waiting.'));
    }

    $stock = pg_dashboard_ops_stock_counts();
    $lanes[] = pg_dashboard_v2_ops_lane('stock', lang('Out of stock'), $stock['out'],
        pg_dashboard_v2_ops_stock_rows(pg_dashboard_ops_out_of_stock_list($limit)),
        ($stock['low'] > 0)
            ? lang(array('string' => '{var:1} running low', 'vars' => pg_format_number($stock['low'], 0)))
            : (($stock['out'] > 0) ? lang('Not selling') : lang('All in stock')),
        'bad',
        lang(array('string' => '{var:1} more out of stock', 'vars' => pg_format_number(max(0, $stock['out'] - $limit), 0))),
        'view_products.php?filter=out_of_stock_products',
        lang('Every product is in stock.'));

    return array('status' => 'success', 'data' => array('lanes' => $lanes));
}
