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
