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
 * The Customer, Payment, Shipping and Items columns: the page's rows are
 * read once per column (the first line of each order, the lines with their
 * address, the parcels) and the payment and shipping pills come from View
 * Order's own function (pg_order_view_states()).
 *
 * Also the tax of one order line for the orders CSV exports, the
 * discount of each line of the Paraşüt export, and the list's Summary (the
 * best sellers and the payment methods opened under the figure strip).
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
 * The positive order ids among those given, each once, as integers.
 *
 * @param mixed $order_ids
 * @return array id => id
 */
function pg_order_list_ids($order_ids)
{
    $ids = array();

    foreach ((array) $order_ids as $order_id) {
        $order_id = (int) $order_id;

        if ($order_id > 0) {
            $ids[$order_id] = $order_id;
        }
    }

    return $ids;
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
    $ids = pg_order_list_ids($order_ids);

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

/**
 * Whether an order may go into the Paraşüt export of the orders list: only a
 * completed or exported order is a sale. A cancelled order or an open cart
 * would reach the accountant as a sales invoice with no customer.
 *
 * @param string $status orders.status
 * @return bool
 */
function pg_order_parasut_exportable($status)
{
    return in_array((string) $status, array('complete', 'exported'), true);
}

/**
 * The product VAT rate typed in the Paraşüt export dialog, as a percentage.
 * Empty means 0; anything that is not a number from 0 to 100 is refused.
 *
 * @param mixed $raw the posted value
 * @return float|null null when refused
 */
function pg_order_parasut_tax_rate($raw)
{
    $raw = str_replace(',', '.', trim((string) $raw));

    if ($raw === '') {
        return 0.0;
    }

    if (!is_numeric($raw)) {
        return null;
    }

    $rate = (float) $raw;

    if (($rate < 0) || ($rate > 100)) {
        return null;
    }

    return $rate;
}

/**
 * The İNDİRİM TUTARI (discount, column Q) of each product line of an order in
 * the Paraşüt export: the order discount (orders.discount) spread over the
 * lines by their value (unit price x quantity) with pg_tax_spread(), so the
 * shares add up to the discount and the remainder goes by largest remainder.
 * A line of no value or below zero takes none, and no line takes more than
 * its value: Paraşüt refuses a line below zero. The gift card is a means of
 * payment, not a discount, and is not passed in.
 *
 * @param int   $discount    orders.discount, minor units
 * @param array $line_totals key => the line's value in minor units
 * @return array key => the line's discount in minor units
 */
function pg_order_parasut_line_discounts($discount, $line_totals)
{
    $shares = array();
    $weights = array();

    foreach ((array) $line_totals as $key => $total) {
        $shares[$key] = 0;

        if ((int) $total > 0) {
            $weights[$key] = (int) $total;
        }
    }

    $discount = min((int) $discount, array_sum($weights));

    if (($discount <= 0) || empty($weights)) {
        return $shares;
    }

    foreach (pg_tax_spread($discount, $weights) as $key => $share) {
        $shares[$key] = $share;
    }

    return $shares;
}

/**
 * The card number cell of the orders CSV exports. The '#' keeps a number from
 * being read as a number by a spreadsheet; an order without one gets an
 * empty cell.
 *
 * @param string $card_number the masked or decrypted number
 * @return string
 */
function pg_order_export_card_cell($card_number)
{
    $card_number = (string) $card_number;

    return ($card_number === '') ? '' : '#' . $card_number;
}

/**
 * A date cell of the orders CSV exports in the site's date format; an empty
 * date ('0000-00-00') gets an empty cell instead of '0/0/0000'.
 *
 * @param string $date a DATE column value
 * @return string
 */
function pg_order_export_date_cell($date)
{
    $date = trim((string) $date);

    if (($date === '') || ($date === '0000-00-00')) {
        return '';
    }

    return prepare_form_data_for_output($date, 'date');
}

/**
 * The country codes whose name, as the panel shows it, contains the text of
 * the orders list's Country filter ("Türkiye" finds TR on a Turkish panel,
 * while countries.name holds "Turkey"). Case is ignored.
 *
 * @param string $term   the filter text
 * @param array  $labels countries.code => the name in the panel's language
 * @return array codes
 */
function pg_order_country_codes_by_label($term, array $labels)
{
    $term = pg_order_country_fold($term);

    if ($term === '') {
        return array();
    }

    $codes = array();

    foreach ($labels as $code => $label) {
        if (mb_strpos(pg_order_country_fold($label), $term) !== false) {
            $codes[] = (string) $code;
        }
    }

    return $codes;
}

/**
 * Text folded for the country filter's match: trimmed and lower case, the
 * Turkish dotted and dotless I folded alike ("TÜRKİYE", "türkiye").
 *
 * @param string $text
 * @return string
 */
function pg_order_country_fold($text)
{
    $text = str_replace(array('İ', 'I', 'ı'), 'i', trim((string) $text));

    return mb_strtolower($text, 'UTF-8');
}

/**
 * The SQL condition that adds the codes of the countries whose shown name
 * matches the filter text (pg_order_country_codes_by_label()) to a Country
 * filter of the orders list, read from the site's countries table once per
 * request; '' when no name matches.
 *
 * @param string $column the column holding the stored code, e.g. orders.billing_country
 * @param string $term   the filter text
 * @return string ' OR (column IN (...))' or ''
 */
function pg_order_country_filter_sql($column, $term)
{
    static $labels = null;

    if ($labels === null) {
        $labels = array();

        foreach ((array) db_items("SELECT code, name FROM countries") as $row) {
            $code = strtoupper(trim((string) $row['code']));

            if ($code !== '') {
                $labels[$code] = pg_geo_country_label($row['name'], $code);
            }
        }
    }

    $codes = pg_order_country_codes_by_label($term, $labels);

    if (empty($codes)) {
        return '';
    }

    return " OR (" . $column . " IN ('" . implode("', '", array_map('escape', $codes)) . "'))";
}

/**
 * The join that names an address's state for the orders list's State
 * filters. A state code is unique only inside its country (state_form.php),
 * so the states row is matched by code within the address's own country:
 * matched by code alone, a code two countries share (WA in the United States
 * and Australia) repeated the order in the CSV exports and let one country's
 * state name find another country's orders. The country is read by a
 * subquery, never joined, so no row is repeated.
 *
 * @param string $alias          the states alias, e.g. billing_states
 * @param string $state_column   the column holding the stored state code
 * @param string $country_column the column holding the stored country code
 * @return string ' LEFT JOIN states AS ...'
 */
function pg_order_list_state_join($alias, $state_column, $country_column)
{
    return " LEFT JOIN states AS " . $alias
        . " ON " . $alias . ".code = " . $state_column
        . " AND " . $alias . ".country_id IN (SELECT " . $alias . "_country.id FROM countries AS " . $alias . "_country WHERE " . $alias . "_country.code = " . $country_column . ")";
}

/**
 * The rows of one Summary part of the orders list, each with the length of
 * its bar: the largest value draws the whole track, the others in proportion,
 * and a value above zero keeps at least a sliver so it is never drawn empty.
 *
 * @param array $rows each array('label' => string, 'value' => number)
 * @return array the same rows with 'share' (0..100, integer) added
 */
function pg_order_list_summary_bars(array $rows)
{
    $max = 0.0;

    foreach ($rows as $row) {
        $max = max($max, (float) ($row['value'] ?? 0));
    }

    $bars = array();

    foreach ($rows as $row) {
        $value = (float) ($row['value'] ?? 0);
        $share = ($max > 0 && $value > 0) ? max(2, (int) round($value / $max * 100)) : 0;
        $bars[] = array('label' => (string) ($row['label'] ?? ''), 'value' => $value, 'share' => $share);
    }

    return $bars;
}

/**
 * One part of the orders list's Summary: its title and a row per entry with
 * the name, the bar and the figure. The figure is printed as text, so the
 * bar adds nothing a screen reader misses and is hidden from it.
 *
 * @param string $title the part's title, plain text
 * @param array  $rows  pg_order_list_summary_bars() rows
 * @param string $empty the sentence shown when there is no row, plain text
 * @return string HTML
 */
function pg_order_list_summary_part_html($title, array $rows, $empty)
{
    $id = 'pg_orders_summary_' . substr(md5($title), 0, 8);
    $html = '<div class="pg-orders-summary-part"><h3 class="pg-group-label" id="' . $id . '">' . h($title) . '</h3>';

    if (!$rows) {
        return $html . '<p class="pg-figure-note">' . h($empty) . '</p></div>';
    }

    $html .= '<ul class="pg-orders-summary-bars" aria-labelledby="' . $id . '">';

    foreach ($rows as $row) {
        $html .= '<li>'
            . '<span class="pg-orders-summary-name" title="' . h($row['label']) . '">' . h($row['label']) . '</span>'
            . '<span class="pg-orders-summary-track" aria-hidden="true"><i style="width: ' . (int) $row['share'] . '%"></i></span>'
            . '<b class="pg-orders-summary-value">' . h(pg_format_number($row['value'], 0)) . '</b>'
            . '</li>';
    }

    return $html . '</ul></div>';
}

/**
 * The orders list's Summary, opened under the figure strip: the five best
 * sellers by quantity and the orders by payment method, over the orders the
 * list shows. view_orders.php?summary=1 answers with it when Summary opens.
 *
 * @param array $top      array('label', 'value') rows, best first
 * @param array $payments array('label', 'value') rows, most first
 * @return string HTML
 */
function pg_order_list_summary_html(array $top, array $payments)
{
    $empty = lang('No orders in this range.');

    return '<div class="pg-orders-summary-grid">'
        . pg_order_list_summary_part_html(lang('Top 5 products'), pg_order_list_summary_bars($top), $empty)
        . pg_order_list_summary_part_html(lang('Payment methods'), pg_order_list_summary_bars($payments), $empty)
        . '</div>';
}

/**
 * The Customer cell's text: the billing first and last name, else the
 * billing company. Plain text, not escaped.
 *
 * @param mixed $first_name   orders.billing_first_name
 * @param mixed $last_name    orders.billing_last_name
 * @param mixed $company      orders.billing_company
 * @return string
 */
function pg_order_list_customer_text($first_name, $last_name, $company)
{
    $name = trim(trim((string) $first_name) . ' ' . trim((string) $last_name));

    return ($name !== '') ? $name : trim((string) $company);
}

/**
 * The query that reads the first line of each order and how many lines it
 * has (order_id, product_name, line_count). Lines saved for later are left
 * out, as the cart and the order leave them out.
 *
 * @param array  $order_ids  order ids; anything but a positive integer is dropped
 * @param string $active_sql pg_order_item_active_sql() of the caller
 * @return string '' when no id is left
 */
function pg_order_list_items_sql($order_ids, $active_sql = '')
{
    $ids = pg_order_list_ids($order_ids);

    if (!$ids) {
        return '';
    }

    return "SELECT
            first_line.order_id,
            order_items.product_name,
            first_line.line_count
        FROM (
            SELECT
                order_items.order_id,
                MIN(order_items.id) AS first_id,
                COUNT(*) AS line_count
            FROM order_items
            WHERE
                (order_items.order_id IN (" . implode(', ', $ids) . "))" . $active_sql . "
            GROUP BY order_items.order_id
        ) AS first_line
        INNER JOIN order_items ON order_items.id = first_line.first_id";
}

/**
 * The query that reads every line with an address of the given orders,
 * with its address's dates, as View Order reads its addresses: from the
 * lines, so an address no line points to is not one of the order's.
 *
 * @param array $order_ids
 * @return string '' when no id is left
 */
function pg_order_list_ship_lines_sql($order_ids)
{
    $ids = pg_order_list_ids($order_ids);

    if (!$ids) {
        return '';
    }

    return "SELECT
            order_items.order_id,
            order_items.ship_to_id,
            order_items.quantity,
            order_items.show_shipped_quantity,
            order_items.shipped_quantity,
            ship_tos.ship_date,
            ship_tos.delivery_date
        FROM order_items
        LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id
        WHERE
            (order_items.order_id IN (" . implode(', ', $ids) . "))
            AND (order_items.ship_to_id > 0)
        ORDER BY order_items.ship_to_id ASC, order_items.id ASC";
}

/**
 * The query that reads the parcels of the given orders (ship_to_id, number,
 * status).
 *
 * @param array $order_ids
 * @param bool  $status_ready pg_shipment_rows_ready(): the status column exists
 * @return string '' when no id is left
 */
function pg_order_list_parcels_sql($order_ids, $status_ready)
{
    $ids = pg_order_list_ids($order_ids);

    if (!$ids) {
        return '';
    }

    return "SELECT
            ship_to_id,
            number,
            " . ($status_ready ? 'status' : "'' AS status") . "
        FROM shipping_tracking_numbers
        WHERE order_id IN (" . implode(', ', $ids) . ")
        ORDER BY ship_to_id ASC, id ASC";
}

/**
 * The addresses of each order as pg_order_view_states() takes them, built
 * from the rows of pg_order_list_ship_lines_sql() and
 * pg_order_list_parcels_sql(). A parcel without a number is left out and a
 * status that is not known reads as created, as View Order reads them
 * (pg_shipment_view()).
 *
 * @param array $lines   rows of the lines query
 * @param array $parcels rows of the parcels query
 * @return array order id => ship_to_id => ship_date, delivery_date, lines, parcels
 */
function pg_order_list_addresses(array $lines, array $parcels)
{
    $orders = array();
    $owner = array();

    foreach ($lines as $line) {
        $order_id = (int) ($line['order_id'] ?? 0);
        $ship_to_id = (int) ($line['ship_to_id'] ?? 0);

        if (($order_id <= 0) || ($ship_to_id <= 0)) {
            continue;
        }

        if (!isset($orders[$order_id][$ship_to_id])) {
            $orders[$order_id][$ship_to_id] = array(
                'ship_date' => (string) ($line['ship_date'] ?? ''),
                'delivery_date' => (string) ($line['delivery_date'] ?? ''),
                'lines' => array(),
                'parcels' => array(),
            );
            $owner[$ship_to_id] = $order_id;
        }

        $orders[$order_id][$ship_to_id]['lines'][] = array(
            'quantity' => (int) ($line['quantity'] ?? 0),
            'show_shipped_quantity' => (int) ($line['show_shipped_quantity'] ?? 0),
            'shipped_quantity' => (int) ($line['shipped_quantity'] ?? 0),
        );
    }

    foreach ($parcels as $parcel) {
        $ship_to_id = (int) ($parcel['ship_to_id'] ?? 0);

        if (!isset($owner[$ship_to_id]) || (trim((string) ($parcel['number'] ?? '')) === '')) {
            continue;
        }

        $status = (string) ($parcel['status'] ?? '');
        $orders[$owner[$ship_to_id]][$ship_to_id]['parcels'][] = in_array($status, pg_shipment_statuses(), true) ? $status : 'created';
    }

    return $orders;
}

/**
 * What the Payment, Shipping and Items cells of a page of the orders list
 * show, read in one query each.
 *
 * @param array $rows       the list's rows (id, status, total, payment_method,
 *                          paid_at, refunded_amount, erp_on_account)
 * @param bool  $paid_known whether orders.paid_at exists
 * @param string $today     Y-m-d
 * @return array order id => array('payment' => pill|null, 'shipping' => pill|null, 'items' => first line row|null)
 */
function pg_order_list_row_states(array $rows, $paid_known, $today)
{
    $ids = pg_order_list_ids(array_column($rows, 'id'));

    if (!$ids) {
        return array();
    }

    $first_lines = array();
    foreach (db_items(pg_order_list_items_sql($ids, pg_order_item_active_sql())) as $line) {
        $first_lines[(int) $line['order_id']] = $line;
    }

    $addresses = pg_order_list_addresses(
        db_items(pg_order_list_ship_lines_sql($ids)),
        db_items(pg_order_list_parcels_sql($ids, pg_shipment_rows_ready()))
    );

    $states = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];

        // Without the paid_at column nothing tells an unpaid transfer from a
        // paid one, so the awaiting state is off, as on View Order.
        if (!$paid_known) {
            unset($row['paid_at']);
        }

        $pills = pg_order_view_states($row, $addresses[$id] ?? array(), $today);
        $states[$id] = array(
            'payment' => $pills['payment'],
            'shipping' => $pills['shipping'],
            'items' => $first_lines[$id] ?? null,
        );
    }

    return $states;
}

/**
 * A Payment or Shipping cell: the pill, or nothing.
 *
 * @param array|null $pill array(translated text, tone) from pg_order_view_states()
 * @return string HTML
 */
function pg_order_list_pill_html($pill)
{
    if (!is_array($pill) || !isset($pill[0], $pill[1])) {
        return '';
    }

    return pg_pill((string) $pill[0], (string) $pill[1]);
}

/**
 * The Items cell: the first line's product and "+N more" for the others.
 *
 * @param array|null $line pg_order_list_items_sql() row
 * @return string HTML
 */
function pg_order_list_items_html($line)
{
    if (!is_array($line)) {
        return '';
    }

    $html = h((string) ($line['product_name'] ?? ''));
    $others = (int) ($line['line_count'] ?? 0) - 1;

    if ($others > 0) {
        $html .= ' <small class="fg-secondary">' . h(lang(array('string' => '+{var:1} more', 'vars' => array($others)))) . '</small>';
    }

    return $html;
}
