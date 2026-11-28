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
 * read as money taken. An order kept in another currency than the base
 * prints in it (pg_order_money()).
 *
 * @param string   $status     orders.status
 * @param int      $total      orders.total in minor units
 * @param int|null $cart_total the cart's total in minor units, null when unknown
 * @param array    $order      charged_currency; array() for an order in base
 * @return string HTML
 */
function pg_order_list_amount_html($status, $total, $cart_total = null, $order = array())
{
    $own = (pg_order_display_for((array) $order, defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', array()) !== null);
    $money = function ($minor) use ($own, $order) {
        return $own ? h(pg_order_money((array) $order, (int) $minor)) : prepare_amount((int) $minor / 100);
    };

    if (($status === 'incomplete') && ((int) $total === 0) && ($cart_total !== null)) {
        return '<span class="fg-secondary fst-italic" title="' . h(lang('Cart total')) . '">' . $money($cart_total) . '</span>';
    }

    return $money($total);
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
 * has (order_id, product_name, line_count). product_name is the name people
 * know (products.short_description), as Summary reads it: order_items keeps
 * the SKU there, which a deleted product falls back to. Lines saved for
 * later are left out, as the cart and the order leave them out.
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
            COALESCE(NULLIF(products.short_description, ''), order_items.product_name) AS product_name,
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
        INNER JOIN order_items ON order_items.id = first_line.first_id
        LEFT JOIN products ON products.id = order_items.product_id";
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
 * The text of a cell that gives way when the list is narrow: one line cut
 * with an ellipsis, the whole text as its title.
 *
 * @param string $html  the cell's markup
 * @param string $title the whole text, plain
 * @return string HTML
 */
function pg_order_list_clip_html($html, $title)
{
    if ((string) $html === '') {
        return '';
    }

    return '<span class="pg-orders-clip" title="' . h((string) $title) . '">' . $html . '</span>';
}

/**
 * The Customer cell: the name, cut with an ellipsis when it is long.
 *
 * @param string $text pg_order_list_customer_text()
 * @return string HTML
 */
function pg_order_list_customer_html($text)
{
    return pg_order_list_clip_html(h((string) $text), (string) $text);
}

/**
 * The Items cell: the first line's product and "+N more" for the others,
 * cut with an ellipsis when it is long.
 *
 * @param array|null $line pg_order_list_items_sql() row
 * @return string HTML
 */
function pg_order_list_items_html($line)
{
    if (!is_array($line)) {
        return '';
    }

    // short_description may carry markup from the product editor.
    $name = trim(strip_tags((string) ($line['product_name'] ?? '')));
    $html = h($name);
    $title = $name;
    $others = (int) ($line['line_count'] ?? 0) - 1;

    if ($others > 0) {
        $more = lang(array('string' => '+{var:1} more', 'vars' => array($others)));
        $html .= ' <small class="fg-secondary">' . h($more) . '</small>';
        $title .= ' ' . $more;
    }

    return pg_order_list_clip_html($html, $title);
}

/**
 * The quick filters over the orders list, in their order: code => label.
 * '' is All.
 *
 * @return array
 */
function pg_order_list_quick_filters()
{
    return array(
        '' => lang('All'),
        'awaiting_payment' => lang('Awaiting payment'),
        'to_ship' => lang('To ship'),
        'shipped' => lang('Shipped'),
        'delivered' => lang('Delivered'),
    );
}

/**
 * A quick filter's code as kept in the session, '' (All) for anything that
 * is not one.
 *
 * @param mixed $value
 * @return string
 */
function pg_order_list_quick_value($value)
{
    $value = is_string($value) ? trim($value) : '';

    return (($value !== '') && array_key_exists($value, pg_order_list_quick_filters())) ? $value : '';
}

/**
 * The condition, over the current orders row, that a quick filter keeps:
 * the rule pg_order_awaiting_payment() and the shipping pill of
 * pg_order_view_states() apply to one order, written in SQL that MySQL and
 * SQLite both read (tests/order_list_quick_test.php runs it over the same
 * sample orders as the pills).
 *
 * The addresses of an order are those its lines point to, as View Order
 * reads them; a parcel without a number is not one, and a parcel status
 * that is not known reads as created.
 *
 * @param string $quick        a code of pg_order_list_quick_filters()
 * @param string $today        Y-m-d
 * @param bool   $paid_known   orders.paid_at exists
 * @param bool   $status_ready shipping_tracking_numbers.status exists
 * @return string '' for All or an unknown code
 */
function pg_order_list_quick_sql($quick, $today, $paid_known, $status_ready)
{
    $today = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $today) ? (string) $today : date('Y-m-d');

    // Without paid_at nothing is known to be unpaid, as on View Order.
    $awaiting = $paid_known
        ? "(((orders.payment_method = 'Offline Payment') OR " . pg_order_payment_may_pend_sql('orders') . ") AND (orders.paid_at = 0) AND (orders.status IN ('complete', 'exported')) AND (orders.total > 0))"
        : '(1 = 0)';

    // One address (a.ship_to_id) of the order, and its state.
    $has_address = "EXISTS (SELECT 1 FROM order_items AS a WHERE a.order_id = orders.id AND a.ship_to_id > 0)";
    $parcel = "FROM shipping_tracking_numbers AS p WHERE p.order_id = orders.id AND p.ship_to_id = a.ship_to_id AND TRIM(p.number) <> ''";
    $date_due = function ($column) use ($today) {
        return "EXISTS (SELECT 1 FROM ship_tos AS s WHERE s.id = a.ship_to_id AND s." . $column . " > '0000-00-00' AND s." . $column . " <= '" . $today . "')";
    };
    $delivered = "(" . $date_due('delivery_date')
        . ($status_ready ? " OR (EXISTS (SELECT 1 " . $parcel . ") AND NOT EXISTS (SELECT 1 " . $parcel . " AND p.status <> 'delivered'))" : '')
        . ")";
    $counted = "FROM order_items AS l WHERE l.order_id = orders.id AND l.ship_to_id = a.ship_to_id AND l.show_shipped_quantity <> 0";
    $shipped = "(" . $delivered . " OR " . $date_due('ship_date')
        . " OR EXISTS (SELECT 1 " . $parcel . ")"
        . " OR (EXISTS (SELECT 1 " . $counted . ") AND NOT EXISTS (SELECT 1 " . $counted . " AND l.shipped_quantity < l.quantity)))";
    $address = "FROM order_items AS a WHERE a.order_id = orders.id AND a.ship_to_id > 0";

    switch ((string) $quick) {
        case 'awaiting_payment':
            return $awaiting;

        case 'to_ship':
            return "((orders.status IN ('complete', 'exported')) AND NOT " . $awaiting . " AND " . $has_address
                . " AND NOT EXISTS (SELECT 1 " . $address . " AND " . $shipped . "))";

        case 'shipped':
            return "(" . $has_address
                . " AND NOT EXISTS (SELECT 1 " . $address . " AND NOT " . $shipped . ")"
                . " AND EXISTS (SELECT 1 " . $address . " AND NOT " . $delivered . "))";

        case 'delivered':
            return "(" . $has_address . " AND NOT EXISTS (SELECT 1 " . $address . " AND NOT " . $delivered . "))";
    }

    return '';
}

/**
 * The query that counts the orders of each quick filter among the orders
 * of $base_sql (the list's other filters and period), in one row: n_all
 * and n_<code>.
 *
 * @param string $base_sql     a query whose id column names the orders
 * @param string $today        Y-m-d
 * @param bool   $paid_known
 * @param bool   $status_ready
 * @return string
 */
function pg_order_list_quick_counts_sql($base_sql, $today, $paid_known, $status_ready)
{
    $parts = array('COUNT(*) AS n_all');

    foreach (array_keys(pg_order_list_quick_filters()) as $code) {
        if ($code === '') {
            continue;
        }

        $parts[] = "SUM(CASE WHEN " . pg_order_list_quick_sql($code, $today, $paid_known, $status_ready) . " THEN 1 ELSE 0 END) AS n_" . $code;
    }

    return "SELECT " . implode(",\n        ", $parts) . "
        FROM orders
        WHERE orders.id IN (SELECT quick_base.id FROM (" . $base_sql . ") AS quick_base)";
}

/**
 * The query behind the main menu's Orders count: the orders the quick filter
 * "To ship" keeps over every period and every other filter, with the order
 * date of the oldest of them (n, oldest).
 *
 * @param string $today        Y-m-d
 * @param bool   $paid_known   whether orders.paid_at exists
 * @param bool   $status_ready whether the parcel status column exists
 * @return string
 */
function pg_order_to_ship_menu_sql($today, $paid_known, $status_ready)
{
    return "SELECT COUNT(*) AS n, MIN(orders.order_date) AS oldest
        FROM orders
        WHERE " . pg_order_list_quick_sql('to_ship', $today, $paid_known, $status_ready);
}

/**
 * Where the Orders panel's "To ship" entry leads: All Orders afresh
 * (reset=true drops a status, type or search left in the screen's session)
 * on the quick filter, its period started on the oldest order to ship when
 * that is older than the list's default month, so the list shows every
 * order the menu counted.
 *
 * @param int $oldest the order date (Unix time) of the oldest order to ship;
 *                    0 when none
 * @param int $now    Unix time
 * @return string a URL relative to the panel's directory
 */
function pg_order_to_ship_menu_url($oldest, $now)
{
    $url = 'view_orders.php?reset=true&quick=to_ship';
    $oldest = (int) $oldest;
    $default = pg_list_period_default((int) $now);

    if (($oldest > 0) && (date('Y-m-d', $oldest) < $default['start_year'] . '-' . $default['start_month'] . '-' . $default['start_day'])) {
        $url .= '&start_month=' . date('m', $oldest) . '&start_day=' . date('d', $oldest) . '&start_year=' . date('Y', $oldest);
    }

    return $url;
}

/**
 * The main menu's Orders count: the orders to ship and the date of the
 * oldest, read once a page and kept in the session for a minute, since the
 * count reads every complete order and the menu is drawn on every panel page.
 *
 * @return array array('count' => int, 'oldest' => int Unix time or 0)
 */
function pg_order_to_ship_menu_count()
{
    static $result = null;

    if ($result !== null) {
        return $result;
    }

    $kept = $_SESSION['software']['menu_counts']['to_ship'] ?? null;

    if (is_array($kept) && (($kept['at'] ?? 0) > (time() - 60)) && (($kept['at'] ?? 0) <= time())) {
        $result = array('count' => (int) ($kept['count'] ?? 0), 'oldest' => (int) ($kept['oldest'] ?? 0));
        return $result;
    }

    $row = db_item(pg_order_to_ship_menu_sql(date('Y-m-d'), pg_schema_has('orders', 'paid_at'), pg_shipment_rows_ready()));
    $result = array('count' => (int) ($row['n'] ?? 0), 'oldest' => (int) ($row['oldest'] ?? 0));

    if (isset($_SESSION)) {
        $_SESSION['software']['menu_counts']['to_ship'] = $result + array('at' => time());
    }

    return $result;
}

/**
 * The quick filters as a segmented control of links, each with its count.
 *
 * @param string $current a code of pg_order_list_quick_filters()
 * @param array  $counts  the row of pg_order_list_quick_counts_sql(); a
 *                        count that is missing is not shown
 * @return string HTML
 */
function pg_order_list_quick_html($current, array $counts)
{
    $current = pg_order_list_quick_value($current);
    $items = '';

    foreach (pg_order_list_quick_filters() as $code => $label) {
        $key = 'n_' . (($code === '') ? 'all' : $code);
        $count = isset($counts[$key]) ? ' <span class="pg-seg-count pg-tabular">' . pg_format_number((int) $counts[$key], 0) . '</span>' : '';
        $items .= '<a class="pg-seg-item' . (($code === $current) ? ' is-active" aria-current="true"' : '"')
            . ' href="view_orders.php?quick=' . h($code) . '">' . h($label) . $count . '</a>';
    }

    return '<div class="pg-seg pg-seg-wrap pg-orders-quick" role="group" aria-label="' . h(lang('Quick filters')) . '">' . $items . '</div>';
}

/**
 * The period before a period, of the same number of days: it ends the
 * second before the period starts.
 *
 * @param int $start the period's first second (00:00:00 of its first day)
 * @param int $stop  the period's last second (23:59:59 of its last day)
 * @return array array(first second, last second)
 */
function pg_order_list_previous_period($start, $stop)
{
    $start = (int) $start;
    $days = max(1, (int) round(((int) $stop + 1 - $start) / 86400));

    // mktime() counts in days, so a change of clocks inside the period
    // moves neither end off midnight.
    $previous_start = mktime(0, 0, 0, (int) date('n', $start), (int) date('j', $start) - $days, (int) date('Y', $start));

    return array($previous_start, $start - 1);
}

/**
 * The change of a figure against the period before, in whole per cent, or
 * null when the period before had nothing to compare with.
 *
 * @param int|float $now
 * @param int|float $before
 * @return int|null
 */
function pg_order_list_change($now, $before)
{
    $before = (float) $before;

    if ($before <= 0) {
        return null;
    }

    return (int) round((((float) $now - $before) / $before) * 100);
}

/**
 * The change note beside a figure of the strip: "+12%", "−8%", "0%", in the
 * strip's note ink, a rise good and a fall bad; nothing when there is no
 * period before to compare with.
 *
 * @param int|float $now
 * @param int|float $before
 * @return string HTML
 */
function pg_order_list_change_html($now, $before)
{
    $change = pg_order_list_change($now, $before);

    if ($change === null) {
        return '';
    }

    $text = ($change > 0) ? '+' . $change . '%' : (($change < 0) ? "\u{2212}" . abs($change) . '%' : '0%');
    $class = ($change > 0) ? ' is-good' : (($change < 0) ? ' is-bad' : '');
    $title = lang('Against the period before, of the same length');

    return '<span class="pg-figure-note pg-tabular' . $class . '" title="' . h($title) . '">' . h($text) . '<span class="visually-hidden"> ' . h($title) . '</span></span>';
}
