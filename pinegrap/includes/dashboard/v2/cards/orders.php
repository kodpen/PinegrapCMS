<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Orders card: the last three orders with their number, customer, state
 * and amount, from the query dashboard widget 8 lists
 * (pg_dashboard_recent_orders()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * One order as a card row. The state is written out beside its tone, so the
 * colour never carries it alone.
 *
 * @param array $order    a pg_dashboard_recent_orders() row
 * @param bool  $shipping shipping is on
 * @return array text, number, note, tone, amount, url, when
 */
function pg_dashboard_v2_order_row($order, $shipping)
{
    if ((string) $order['status'] === 'cancelled') {
        $note = lang('Canceled');
        $tone = 'bad';
    } elseif (!$shipping) {
        $note = '';
        $tone = '';
    } elseif (!empty($order['shipped'])) {
        $note = lang('Shipped');
        $tone = 'good';
    } else {
        $note = lang('Not shipped yet');
        $tone = 'warn';
    }

    return array(
        'text'   => (string) $order['name'],
        'number' => (string) $order['order_number'],
        'note'   => $note,
        'tone'   => $tone,
        'amount' => pg_dashboard_v2_money($order['total']),
        'url'    => 'view_order.php?id=' . (int) $order['id'],
        'when'   => get_relative_time(array('timestamp' => (int) $order['timestamp'], 'format' => 'plain_text')),
    );
}

function pg_dashboard_v2_card_orders($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('shop', $user)) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_8.php');

    $shipping = defined('ECOMMERCE_SHIPPING') && (ECOMMERCE_SHIPPING == true);
    $rows = array();

    foreach (pg_dashboard_recent_orders(3) as $order) {
        $rows[] = pg_dashboard_v2_order_row($order, $shipping);
    }

    return array('status' => 'success', 'data' => array(
        'rows'  => $rows,
        'empty' => empty($rows) ? lang('No orders yet.') : '',
        'url'   => 'view_orders.php',
    ));
}
