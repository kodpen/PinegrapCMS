<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pre-order of a product (2026.11.0, 11.0.151): products.preorder (0/1) and
 * products.preorder_date, the day the stock is expected and the order ships
 * from. A pre-order product is sold before its stock arrives on the back
 * order path: the product screen turns products.backorder on with it, so
 * every stock gate of the cart and checkout lets it through unchanged and no
 * totals code knows about pre-orders.
 *
 * A pre-order is running while the flag is set, the date is after today and
 * the product has no stock in hand (a tracked product with a quantity above
 * zero ships now). While it runs the catalog widgets, the cart line and the
 * order mails say "Pre-order, ships from <date>", and a requested arrival
 * date of the checkout may not fall before the date. The general job clears
 * the flag once the date has come or the stock is in; the back order switch
 * stays as it is.
 *
 * Every reader asks pg_product_preorder_ready() first: files land before
 * the upgrade adds the columns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Rows one pass of the general job clears at most. */
define('PG_PREORDER_END_ROWS', 500);

/**
 * Whether the upgrade has added both columns.
 *
 * @return bool
 */
function pg_product_preorder_ready()
{
    return function_exists('pg_schema_has')
        && pg_schema_has('products', 'preorder')
        && pg_schema_has('products', 'preorder_date');
}

/**
 * The two columns for a SELECT on the products table (or its alias), with a
 * comma in front; before the upgrade the same names with "no pre-order", so
 * a reader needs no second branch.
 *
 * @param  string $table The products table's name or alias in the query.
 * @param  bool   $ready pg_product_preorder_ready()
 * @return string
 */
function pg_preorder_select_sql($table, $ready)
{
    $table = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $table));

    if (!$ready || ($table === '')) {
        return ', 0 AS preorder, NULL AS preorder_date';
    }

    return ', ' . $table . '.preorder, ' . $table . '.preorder_date';
}

/**
 * A pre-order date as typed or stored, made ready to store: '' for none, the
 * date as YYYY-MM-DD for a real day, false for anything else. The product
 * screen's date box and the API send ISO; MySQL's zero date is none.
 *
 * @param  mixed $value
 * @return string|false
 */
function pg_preorder_date_parse($value)
{
    if ($value === null) {
        return '';
    }

    if (!is_string($value)) {
        return false;
    }

    $value = trim($value);

    if (($value === '') || ($value === '0000-00-00')) {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $part) !== 1) {
        return false;
    }

    if (!checkdate((int) $part[2], (int) $part[3], (int) $part[1])) {
        return false;
    }

    return $value;
}

/**
 * What is wrong with a pre-order as posted, as an English sentence for
 * lang(), or '' when it can be stored. Turned on, it needs a date after
 * today; turned off, the date is not read.
 *
 * @param  bool   $on
 * @param  mixed  $date
 * @param  string $today YYYY-MM-DD in the site's time zone.
 * @return string
 */
function pg_preorder_problem($on, $date, $today)
{
    if (!$on) {
        return '';
    }

    $date = pg_preorder_date_parse($date);

    if ($date === false) {
        return 'The pre-order date is not a valid date.';
    }

    if ($date === '') {
        return 'A pre-order needs the date the stock is expected.';
    }

    if ($date <= (string) $today) {
        return 'The pre-order date must be after today.';
    }

    return '';
}

/**
 * The columns the product screen stores from its form. Turning pre-order on
 * turns the back order on with it: that is the path that sells the product
 * before its stock arrives. A date that cannot be read is never stored;
 * pg_preorder_problem() refused the form before this runs.
 *
 * @param  array $post
 * @return array
 */
function pg_preorder_from_post($post)
{
    $on = !empty($post['preorder']);
    $date = pg_preorder_date_parse(isset($post['preorder_date']) ? $post['preorder_date'] : '');

    if (!$on || ($date === false) || ($date === '')) {
        return array('preorder' => 0, 'preorder_date' => null);
    }

    return array('preorder' => 1, 'preorder_date' => $date, 'backorder' => 1);
}

/**
 * Whether a product row is on pre-order now: the flag is set, its date is
 * after today and no stock is in hand. The row needs preorder and
 * preorder_date; inventory and inventory_quantity are read when present.
 *
 * @param  array  $product
 * @param  string $today YYYY-MM-DD in the site's time zone.
 * @return bool
 */
function pg_preorder_active($product, $today)
{
    if (!is_array($product) || empty($product['preorder'])) {
        return false;
    }

    $date = pg_preorder_date_parse(isset($product['preorder_date']) ? (string) $product['preorder_date'] : '');

    if (($date === false) || ($date === '') || ($date <= (string) $today)) {
        return false;
    }

    if (!empty($product['inventory']) && isset($product['inventory_quantity']) && ((int) $product['inventory_quantity'] > 0)) {
        return false;
    }

    return true;
}

/**
 * The running pre-order's date of a product row, or ''.
 *
 * @param  array  $product
 * @param  string $today
 * @return string YYYY-MM-DD or ''
 */
function pg_preorder_active_date($product, $today)
{
    return pg_preorder_active($product, $today) ? (string) $product['preorder_date'] : '';
}

/**
 * The site's today as YYYY-MM-DD (PHP runs in the site's time zone).
 *
 * @return string
 */
function pg_preorder_today()
{
    return date('Y-m-d');
}

/**
 * A pre-order date as the site prints dates.
 *
 * @param  string $date YYYY-MM-DD
 * @return string
 */
function pg_preorder_date_text($date)
{
    if (function_exists('prepare_form_data_for_output')) {
        return (string) prepare_form_data_for_output($date, 'date', false);
    }

    return (string) $date;
}

/**
 * "Pre-order, ships from <date>" for a product row whose pre-order runs,
 * else ''. Plain text: escape it where it goes into HTML.
 *
 * @param  array  $product
 * @param  string $today
 * @return string
 */
function pg_preorder_label($product, $today)
{
    $date = pg_preorder_active_date($product, $today);

    if ($date === '') {
        return '';
    }

    return (string) lang(array('string' => 'Pre-order, ships from {var:1}', 'vars' => array(pg_preorder_date_text($date))));
}

/**
 * The catalog widgets' pre-order tokens of a product row, next to the
 * __out_of_stock family: __preorder ('1' or '0'), __preorder_date (the date
 * as the site prints it) and __preorder_label; both texts '' when none runs.
 *
 * @param  array  $product
 * @param  string $today
 * @return array
 */
function pg_preorder_tokens($product, $today)
{
    $date = pg_preorder_active_date($product, $today);

    return array(
        '__preorder'       => ($date !== '') ? '1' : '0',
        '__preorder_date'  => ($date !== '') ? pg_preorder_date_text($date) : '',
        '__preorder_label' => pg_preorder_label($product, $today),
    );
}

/**
 * The earliest day a requested arrival date may take for an order: the
 * latest running pre-order date among its products, or '' when none of them
 * is on pre-order.
 *
 * @param  array  $products Product rows (preorder, preorder_date, inventory, inventory_quantity).
 * @param  string $today
 * @return string YYYY-MM-DD or ''
 */
function pg_preorder_earliest_arrival($products, $today)
{
    $latest = '';

    foreach ((array) $products as $product) {
        $date = pg_preorder_active_date($product, $today);

        if ($date > $latest) {
            $latest = $date;
        }
    }

    return $latest;
}

/**
 * pg_preorder_earliest_arrival() for the products of an order's active
 * lines, read in one query.
 *
 * @param  int $order_id
 * @return string YYYY-MM-DD or ''
 */
function pg_preorder_order_earliest_arrival($order_id)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || !pg_product_preorder_ready()) {
        return '';
    }

    $active = function_exists('pg_order_item_active_sql') ? pg_order_item_active_sql('order_items') : '';

    $products = db_items(
        "SELECT products.preorder, products.preorder_date, products.inventory, products.inventory_quantity
        FROM order_items
        JOIN products ON products.id = order_items.product_id
        WHERE order_items.order_id = '" . $order_id . "' AND products.preorder = 1" . $active);

    return pg_preorder_earliest_arrival(is_array($products) ? $products : array(), pg_preorder_today());
}

/**
 * The note the arrival date check answers when a requested date falls
 * before the order's pre-order date, or '' when the date can be used.
 *
 * @param  string $date     The requested arrival date, YYYY-MM-DD.
 * @param  string $earliest pg_preorder_earliest_arrival()
 * @return string English sentence for lang(), {var:1} the date.
 */
function pg_preorder_arrival_problem($date, $earliest)
{
    $date = pg_preorder_date_parse($date);

    if (($date === false) || ($date === '') || ((string) $earliest === '')) {
        return '';
    }

    return ($date < (string) $earliest) ? 'This order holds a pre-order that ships from {var:1}; choose that day or a later one.' : '';
}

/**
 * The statement the general job runs to end pre-orders: the date has come
 * or the stock is in. The flag is cleared, the date and the back order stay.
 *
 * @param  string $today
 * @param  int    $limit
 * @return string
 */
function pg_preorder_end_sql($today, $limit)
{
    $today = pg_preorder_date_parse((string) $today);

    if (($today === false) || ($today === '')) {
        $today = '1970-01-01';
    }

    return "UPDATE products SET preorder = 0
        WHERE preorder = 1
        AND (preorder_date IS NULL OR preorder_date <= '" . $today . "'
            OR (inventory = 1 AND inventory_quantity > 0))
        LIMIT " . max(1, (int) $limit);
}

/**
 * Once an hour from job.php: clear the flag of every pre-order whose date
 * has come or whose stock is in. Readers already stop showing an ended
 * pre-order on their own; this keeps the flag true for the API, exports and
 * the product screen. A tick between passes reads one small file.
 *
 * @return int Products whose pre-order ended.
 */
function pg_preorder_end_hourly()
{
    if (!pg_product_preorder_ready()) {
        return 0;
    }

    $path = PG_FUNCTIONS_DIR . '/data/temp/preorder_ended.txt';
    $last = is_file($path) ? (int) @file_get_contents($path) : 0;
    $now = time();

    if (($now - $last) < 3600) {
        return 0;
    }

    @file_put_contents($path, (string) $now, LOCK_EX);

    db(pg_preorder_end_sql(pg_preorder_today(), PG_PREORDER_END_ROWS));

    return max(0, (int) mysqli_affected_rows(db::$con));
}
