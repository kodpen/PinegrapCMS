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
 * Also the tax of one order line for the orders CSV exports.
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

/**
 * The tax of one order line in minor units, as the order's tax counted it.
 * Checkout writes the line's tax to tax_total and 0 to the old per-unit tax
 * column; a marketplace import writes only the per-unit tax, and its order
 * tax is that tax times the quantity. So tax_total is taken when it is set,
 * else the unit tax for each unit.
 *
 * @param int|string|null $tax_total order_items.tax_total
 * @param int|string|null $unit_tax order_items.tax
 * @param int|string|null $quantity order_items.quantity
 * @return int
 */
function pg_order_item_line_tax($tax_total, $unit_tax, $quantity)
{
    $tax_total = (int) round((float) $tax_total);

    if ($tax_total !== 0) {
        return $tax_total;
    }

    return (int) round((float) $unit_tax * (float) $quantity);
}

/**
 * The tax of one order line for the orders CSV exports, in minor units: its
 * own tax (pg_order_item_line_tax()) less its share of the order discount,
 * so the lines of an order add up to orders.tax (pg_order_line_charged_taxes()).
 *
 * The order's lines are read whole on the first call for it, not from the
 * export's own rows: a product filter can leave some of them out of the
 * export, and the discount is shared over all of them. Only the last order
 * read is kept, as the exports go through the lines order by order. A
 * recurring line not charged with the order (pg_order_line_charged_today())
 * keeps its own tax.
 *
 * @param int|string $order_id
 * @param int|string $item_id
 * @return int
 */
function pg_order_export_line_tax($order_id, $item_id)
{
    static $cache = array('order_id' => 0, 'taxes' => array());

    $order_id = (int) $order_id;
    $item_id = (int) $item_id;

    if ($cache['order_id'] !== $order_id) {
        $cache = array('order_id' => $order_id, 'taxes' => array());

        $order = db_item("SELECT discount, tax, order_date FROM orders WHERE id = '" . $order_id . "'");
        $with_rate = pg_schema_has('order_items', 'tax_rate');
        $order_day = date('Y-m-d', (int) ($order['order_date'] ?? 0));
        $clearcommerce = defined('ECOMMERCE_CREDIT_DEBIT_CARD') && (ECOMMERCE_CREDIT_DEBIT_CARD == true)
            && defined('ECOMMERCE_PAYMENT_GATEWAY') && (ECOMMERCE_PAYMENT_GATEWAY == 'ClearCommerce');
        $lines = array();

        foreach ((array) db_items(
            "SELECT id, price, quantity, tax, tax_total, recurring_payment_period, recurring_start_date" . ($with_rate ? ", tax_rate" : "") . "
             FROM order_items
             WHERE order_id = '" . $order_id . "'" . pg_order_item_active_sql() . "
             ORDER BY id") as $item) {
            $lines[(int) $item['id']] = array(
                'amount' => (int) $item['price'] * max(1, (int) $item['quantity']),
                'rate' => $with_rate ? $item['tax_rate'] : null,
                'tax' => pg_order_item_line_tax($item['tax_total'], $item['tax'], $item['quantity']),
                'alone' => !pg_order_line_charged_today((string) $item['recurring_payment_period'] !== '', $item['recurring_start_date'], 0, $order_day, $clearcommerce),
            );
        }

        $cache['taxes'] = is_array($order)
            ? pg_order_line_charged_taxes($lines, (int) $order['discount'], (int) $order['tax'])
            : array_map(function ($line) { return $line['tax']; }, $lines);
    }

    return (int) ($cache['taxes'][$item_id] ?? 0);
}
