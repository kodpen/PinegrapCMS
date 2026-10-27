<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The amount column of the orders list (view_orders.php).
 *
 * orders.total is written when the order is placed (submit_order()), so a
 * cart that never reached the payment step reads 0 there. The list shows
 * such an incomplete order with the sum of its cart lines instead, drawn
 * apart from a charged total: it is what the cart holds, not money taken.
 * The filter totals and the chart keep adding orders.total only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The query that sums the cart lines of the given orders, one row per order
 * (order_id, cart_total in minor units). Lines saved for later are left out,
 * as the cart's own subtotal leaves them out. The price may be negative and
 * the quantity is unsigned, so the quantity is cast before the product.
 *
 * @param array  $order_ids order ids; anything but a positive integer is dropped
 * @param string $active_sql pg_order_item_active_sql() of the caller
 * @return string '' when no id is left
 */
function pg_order_list_cart_totals_sql($order_ids, $active_sql = '')
{
    $ids = array();

    foreach ((array) $order_ids as $order_id) {
        $order_id = (int) $order_id;

        if ($order_id > 0) {
            $ids[$order_id] = $order_id;
        }
    }

    if (!$ids) {
        return '';
    }

    return "SELECT
            order_items.order_id,
            SUM(order_items.price * CAST(order_items.quantity AS signed)) AS cart_total
        FROM order_items
        WHERE
            (order_items.order_id IN (" . implode(', ', $ids) . "))" . $active_sql . "
        GROUP BY order_items.order_id";
}

/**
 * The cart totals of the incomplete orders among a page of the orders list.
 *
 * @param array $rows the list's rows (id, status)
 * @return array order id => cart total in minor units
 */
function pg_order_list_cart_totals($rows)
{
    $ids = array();

    foreach ((array) $rows as $row) {
        if (($row['status'] ?? '') === 'incomplete') {
            $ids[] = $row['id'];
        }
    }

    $query = pg_order_list_cart_totals_sql($ids, pg_order_item_active_sql());

    if ($query === '') {
        return array();
    }

    $totals = array();
    $result = db_q($query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $totals[(int) $row['order_id']] = (int) $row['cart_total'];
        }
    }

    return $totals;
}

/**
 * The amount cell's content: the charged total, or for an incomplete order
 * with lines in its cart the cart's total, muted and titled so it does not
 * read as money taken.
 *
 * @param string   $status     orders.status
 * @param int      $total      orders.total in minor units
 * @param int|null $cart_total the cart's total in minor units, null when unknown
 * @return string HTML
 */
function pg_order_list_amount_html($status, $total, $cart_total = null)
{
    if (($status === 'incomplete') && ((int) $total === 0) && ($cart_total !== null)) {
        return '<span class="fg-secondary fst-italic" title="' . h(lang('Cart total')) . '">' . prepare_amount((int) $cart_total / 100) . '</span>';
    }

    return prepare_amount((int) $total / 100);
}
