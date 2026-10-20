<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Customer returns: a request to send back goods of a completed order, and
 * the steps it goes through until the money is back (2026.11.0, tables
 * order_returns and order_return_items).
 *
 * A return covers the lines of one delivery (one ship_tos row) and lists how
 * many of each line go back. Its status is one of pg_return_statuses(), a
 * string checked here rather than an ENUM, so a status added later needs no
 * schema step:
 *
 *   requested  the customer (or staff on their behalf) asked
 *   approved   staff accepted it; the customer may send the goods
 *   rejected   staff refused it; its goods are free to be asked for again
 *   shipped    the customer handed the parcel to the carrier
 *   received   the goods are back
 *   refunded   the money went back
 *   closed     the case is finished (a request the customer withdrew, or
 *              one settled outside these steps)
 *
 * Each status reached is stamped in its own column (pg_return_stamp_column()),
 * which is what the deadlines are read from: the withdrawal period runs 14
 * days from delivery, and the refund is due 14 days after the goods reached
 * the carrier the seller named (Mesafeli Sözleşmeler Yönetmeliği as amended
 * on 24 May 2025).
 *
 * Nothing here checks who is asking: a screen that lets a customer request a
 * return checks first that the order is theirs, and a panel screen checks the
 * person's commerce rights. What is checked here is what the request says:
 * the order is complete, the delivery is the order's, the lines are the
 * delivery's and no more is asked for than is left on each line. A request
 * made by the customer must also come within the withdrawal window, after the
 * goods have left; staff may record one at any time.
 *
 * Order totals, process_order_cancellation() and the ERP's own return
 * invoices are not touched here. A delivery that has not been sent is a
 * cancellation, not a return.
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
 * Days of the withdrawal period, counted from the day after delivery.
 */
define('PG_RETURN_WINDOW_DAYS', 14);

/**
 * Days the seller has to refund, counted from the day after the goods
 * reached the carrier (or the seller, when nobody said when they were sent).
 */
define('PG_RETURN_REFUND_DAYS', 14);

/**
 * Days a parcel is taken to be on its way when no delivery date is known.
 */
define('PG_RETURN_DELIVERY_DAYS', 3);

/**
 * The longest note a customer or staff member may write, in characters.
 */
define('PG_RETURN_NOTE_MAX', 2000);

/**
 * The most lines one return may list.
 */
define('PG_RETURN_MAX_LINES', 200);

/**
 * True when both tables are in the database (2026.11.0).
 *
 * @return bool
 */
function pg_returns_ready()
{
    return pg_schema_has('order_returns', 'closed_at') && pg_schema_has('order_return_items', 'quantity');
}

/* ---------------------------------------------------------------------------
   Statuses
   --------------------------------------------------------------------------- */

/**
 * Every status a return can be in, in the order it goes through them.
 *
 * @return string[]
 */
function pg_return_statuses()
{
    return array('requested', 'approved', 'rejected', 'shipped', 'received', 'refunded', 'closed');
}

/**
 * A status as stored, or '' when the value is not one.
 *
 * @param mixed $value
 * @return string
 */
function pg_return_status($value)
{
    if (!is_string($value)) {
        return '';
    }

    $value = strtolower(trim($value));

    return in_array($value, pg_return_statuses(), true) ? $value : '';
}

/**
 * The name of a status as people read it.
 *
 * @param string $status
 * @return string
 */
function pg_return_status_label($status)
{
    switch ($status) {
        case 'requested': return lang('Return requested');
        case 'approved':  return lang('Return approved');
        case 'rejected':  return lang('Return rejected');
        case 'shipped':   return lang('Sent back');
        case 'received':  return lang('Goods received back');
        case 'refunded':  return lang('Money refunded');
        case 'closed':    return lang('Return closed');
    }

    return '';
}

/**
 * Where a return may go from each status, for staff or for the customer.
 *
 * Staff may skip "shipped" (a parcel that arrived without the customer
 * saying so) and close a case at any open step. A rejected or closed return
 * goes nowhere: the customer asks again instead, while the goods are free to
 * be asked for (pg_return_holds_goods()). The customer may only withdraw a
 * request nobody has answered yet and say the parcel is on its way.
 *
 * @param string $by 'customer', or anything else for staff
 * @return array status => statuses
 */
function pg_return_moves($by = 'staff')
{
    if ($by === 'customer') {
        return array(
            'requested' => array('closed'),
            'approved'  => array('shipped'),
        );
    }

    return array(
        'requested' => array('approved', 'rejected', 'closed'),
        'approved'  => array('shipped', 'received', 'closed'),
        'shipped'   => array('received', 'closed'),
        'received'  => array('refunded', 'closed'),
        'refunded'  => array('closed'),
    );
}

/**
 * The statuses a return in this one may move to.
 *
 * @param string $from
 * @param string $by
 * @return string[]
 */
function pg_return_next_statuses($from, $by = 'staff')
{
    $moves = pg_return_moves($by);

    return (is_string($from) && isset($moves[$from])) ? $moves[$from] : array();
}

/**
 * May a return move from one status to another?
 *
 * @param string $from
 * @param string $to
 * @param string $by
 * @return bool
 */
function pg_return_can_move($from, $to, $by = 'staff')
{
    return in_array($to, pg_return_next_statuses($from, $by), true);
}

/**
 * The column that keeps when a return reached a status.
 *
 * @param string $status
 * @return string '' for a value that is not a status
 */
function pg_return_stamp_column($status)
{
    $columns = array(
        'requested' => 'requested_at',
        'approved'  => 'decided_at',
        'rejected'  => 'decided_at',
        'shipped'   => 'shipped_at',
        'received'  => 'received_at',
        'refunded'  => 'refunded_at',
        'closed'    => 'closed_at',
    );

    return (is_string($status) && isset($columns[$status])) ? $columns[$status] : '';
}

/**
 * Do the goods of this return still count against their order lines?
 *
 * They do from the request on, so the same goods cannot be asked for twice.
 * A rejected return frees them; so does a closed one, unless the parcel had
 * left by then (or arrived, or was paid back), because those goods are no
 * longer with the customer.
 *
 * @param array $return a row of order_returns (status and the stamps)
 * @return bool
 */
function pg_return_holds_goods($return)
{
    $status = isset($return['status']) ? (string) $return['status'] : '';

    if (in_array($status, array('requested', 'approved', 'shipped', 'received', 'refunded'), true)) {
        return true;
    }

    if ($status !== 'closed') {
        return false;
    }

    foreach (array('shipped_at', 'received_at', 'refunded_at') as $column) {
        if (isset($return[$column]) && ((int) $return[$column] > 0)) {
            return true;
        }
    }

    return false;
}

/* ---------------------------------------------------------------------------
   Reasons and text
   --------------------------------------------------------------------------- */

/**
 * The reasons a customer may give, code => label. The code is what is stored.
 *
 * @return array
 */
function pg_return_reasons()
{
    return array(
        'changed_mind'     => lang('I changed my mind'),
        'damaged'          => lang('It arrived damaged'),
        'faulty'           => lang('It is faulty'),
        'wrong_item'       => lang('I was sent the wrong item'),
        'not_as_described' => lang('It is not as described'),
        'size'             => lang('The size or fit is wrong'),
        'other'            => lang('Another reason'),
    );
}

/**
 * A reason code as stored, or '' when the value is not one.
 *
 * @param mixed $value
 * @return string
 */
function pg_return_reason($value)
{
    if (!is_string($value)) {
        return '';
    }

    $value = strtolower(trim($value));

    return array_key_exists($value, pg_return_reasons()) ? $value : '';
}

/**
 * Free text as stored: valid UTF-8, line breaks as \n, no other control
 * characters, trimmed and cut to a number of characters.
 *
 * @param mixed $value
 * @param int   $max
 * @return string
 */
function pg_return_text($value, $max)
{
    if (!is_string($value)) {
        return '';
    }

    if (!mb_check_encoding($value, 'UTF-8')) {
        $value = (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    $value = str_replace(array("\r\n", "\r"), "\n", $value);
    $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

    return trim(mb_substr(trim($value), 0, max(0, (int) $max)));
}

/**
 * The carrier the goods go back with, as stored: the key of a carrier the
 * site knows (pg_shipping_carriers()) when the value names one by its key,
 * otherwise the text itself, cut to the column.
 *
 * @param mixed $value
 * @return string
 */
function pg_return_carrier($value)
{
    $value = pg_return_text($value, 32);
    $key = strtolower($value);

    if (($value !== '') && function_exists('pg_shipping_carriers') && array_key_exists($key, pg_shipping_carriers())) {
        return $key;
    }

    return $value;
}

/* ---------------------------------------------------------------------------
   Dates and deadlines
   --------------------------------------------------------------------------- */

/**
 * A calendar date as Y-m-d, or '' when the value is not one (MySQL's
 * 0000-00-00 included).
 *
 * @param mixed $value
 * @return string
 */
function pg_return_date($value)
{
    if (!is_string($value) || (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1)) {
        return '';
    }

    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : '';
}

/**
 * A date some days later, worked out on the calendar (no clock, so no
 * daylight saving step can move it).
 *
 * @param string $date Y-m-d
 * @param int    $days
 * @return string Y-m-d, or '' when the date is not one
 */
function pg_return_add_days($date, $days)
{
    $date = pg_return_date($date);

    if ($date === '') {
        return '';
    }

    list($year, $month, $day) = array_map('intval', explode('-', $date));

    return gmdate('Y-m-d', gmmktime(0, 0, 0, $month, $day + (int) $days, $year));
}

/**
 * The withdrawal window of a delivery.
 *
 * The 14 days run from the day after the goods were delivered. A known
 * delivery date opens them by itself. Without one (the shipment rows do not
 * keep it yet) the delivery is estimated: three days after the ship date, or
 * the recipient's own expected delivery date when that is later. Both of
 * those may be dates the checkout planned rather than ones that happened, so
 * the later one is taken: an estimate that is too late gives the customer a
 * few days more, one that is too early would cut their legal period short.
 *
 * The window is open on every day up to and including the deadline, before
 * the delivery too (a customer may withdraw before the goods arrive).
 *
 * @param array  $dates delivered (known, Y-m-d), ship_date and delivery_date
 *                      (from ship_tos); a missing or empty date is unknown
 * @param string $today Y-m-d in the site's time zone
 * @return array|null delivered, estimated, deadline, open; null when no date
 *                    is known or today is not a date
 */
function pg_return_window($dates, $today)
{
    $today = pg_return_date($today);

    if (($today === '') || !is_array($dates)) {
        return null;
    }

    $delivered = pg_return_date(isset($dates['delivered']) ? $dates['delivered'] : '');
    $estimated = false;

    if ($delivered === '') {
        $candidates = array();
        $ship_date = pg_return_date(isset($dates['ship_date']) ? $dates['ship_date'] : '');
        $expected = pg_return_date(isset($dates['delivery_date']) ? $dates['delivery_date'] : '');

        if ($ship_date !== '') {
            $candidates[] = pg_return_add_days($ship_date, PG_RETURN_DELIVERY_DAYS);
        }

        if ($expected !== '') {
            $candidates[] = $expected;
        }

        if (empty($candidates)) {
            return null;
        }

        // Y-m-d strings sort as the dates they are.
        sort($candidates, SORT_STRING);
        $delivered = end($candidates);
        $estimated = true;
    }

    $deadline = pg_return_add_days($delivered, PG_RETURN_WINDOW_DAYS);

    return array(
        'delivered' => $delivered,
        'estimated' => $estimated,
        'deadline'  => $deadline,
        'open'      => (strcmp($today, $deadline) <= 0),
    );
}

/**
 * The last day the refund of a return may be paid on: 14 days after the
 * goods reached the carrier, or after they arrived when nobody said when
 * they were sent.
 *
 * @param string $shipped_date  Y-m-d
 * @param string $received_date Y-m-d
 * @return string Y-m-d, or '' when neither is known
 */
function pg_return_refund_deadline($shipped_date, $received_date)
{
    $from = pg_return_date($shipped_date);

    if ($from === '') {
        $from = pg_return_date($received_date);
    }

    return ($from === '') ? '' : pg_return_add_days($from, PG_RETURN_REFUND_DAYS);
}

/**
 * Have the goods of a delivery left the shop? Any of the signs the order
 * screens read says so: a tracking number, a quantity marked shipped, or a
 * ship date that has come.
 *
 * @param bool   $has_tracking     a non-empty tracking number on the delivery
 * @param int    $shipped_quantity the delivery's lines' shipped quantities
 * @param string $ship_date        Y-m-d
 * @param string $today            Y-m-d
 * @return bool
 */
function pg_return_goods_left($has_tracking, $shipped_quantity, $ship_date, $today)
{
    if ($has_tracking || ((int) $shipped_quantity > 0)) {
        return true;
    }

    $ship_date = pg_return_date($ship_date);
    $today = pg_return_date($today);

    return ($ship_date !== '') && ($today !== '') && (strcmp($ship_date, $today) <= 0);
}

/* ---------------------------------------------------------------------------
   Lines and quantities
   --------------------------------------------------------------------------- */

/**
 * How many of a line may still be asked for.
 *
 * @param int $quantity the line's quantity
 * @param int $held     the quantity open returns already hold
 * @return int
 */
function pg_return_returnable($quantity, $held)
{
    return max(0, (int) $quantity - (int) $held);
}

/**
 * The lines a request names, checked against the lines of the delivery.
 *
 * A line is named by its order_items id with a whole, non-negative quantity;
 * zero leaves it out. At least one line must remain, each one of the
 * delivery's, none above what is left on it.
 *
 * @param mixed $requested order item id => quantity
 * @param array $lines     order item id => name, returnable
 * @return array ok, error, lines (order item id => quantity)
 */
function pg_return_check_lines($requested, $lines)
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error, 'lines' => array());
    };

    if (!is_array($requested)) {
        return $refuse(lang('Choose at least one item to return.'));
    }

    $out = array();

    foreach ($requested as $id => $quantity) {

        if (is_int($quantity)) {
            $count = $quantity;
        } elseif (is_string($quantity) && (preg_match('/^\s*\d{1,9}\s*$/', $quantity) === 1)) {
            $count = (int) $quantity;
        } else {
            return $refuse(lang('Enter the number of items to return as a whole number.'));
        }

        if ($count < 0) {
            return $refuse(lang('Enter the number of items to return as a whole number.'));
        }

        if ($count === 0) {
            continue;
        }

        $id = (int) $id;

        if (!isset($lines[$id]) || !is_array($lines[$id])) {
            return $refuse(lang('An item is not part of this delivery.'));
        }

        $returnable = isset($lines[$id]['returnable']) ? (int) $lines[$id]['returnable'] : 0;

        if ($count > $returnable) {
            return $refuse(lang(array(
                'string' => '{var:1}: at most {var:2} can be returned.',
                'vars'   => array(isset($lines[$id]['name']) ? (string) $lines[$id]['name'] : '#' . $id, $returnable))));
        }

        $out[$id] = $count;
    }

    if (empty($out)) {
        return $refuse(lang('Choose at least one item to return.'));
    }

    if (count($out) > PG_RETURN_MAX_LINES) {
        return $refuse(lang('Too many items for one return.'));
    }

    return array('ok' => true, 'error' => '', 'lines' => $out);
}

/* ---------------------------------------------------------------------------
   Reading
   --------------------------------------------------------------------------- */

/**
 * The quantities the returns of an order hold, per order line.
 *
 * @param int $order_id
 * @return array order item id => quantity
 */
function pg_return_held($order_id)
{
    $held = array();

    $rows = db_items(
        "SELECT
            order_returns.status,
            order_returns.shipped_at,
            order_returns.received_at,
            order_returns.refunded_at,
            order_return_items.order_item_id,
            order_return_items.quantity
        FROM order_returns
        JOIN order_return_items ON order_return_items.return_id = order_returns.id
        WHERE order_returns.order_id = '" . (int) $order_id . "'");

    foreach ($rows as $row) {
        if (pg_return_holds_goods($row)) {
            $item_id = (int) $row['order_item_id'];
            $held[$item_id] = (isset($held[$item_id]) ? $held[$item_id] : 0) + (int) $row['quantity'];
        }
    }

    return $held;
}

/**
 * The lines of an order goods can be returned from: those sent to a
 * recipient (a line without one is not physical goods) and not kept aside in
 * the cart. Each with what is left to ask for.
 *
 * The name is the product's own (products.short_description); the order
 * line's product_name is its code.
 *
 * @param int $order_id
 * @param int $ship_to_id one delivery only, or 0 for all
 * @return array order item id => id, ship_to_id, product_id, name, code,
 *               quantity, price, shipped_quantity, held, returnable
 */
function pg_return_order_lines($order_id, $ship_to_id = 0)
{
    $order_id = (int) $order_id;
    $ship_to_id = (int) $ship_to_id;

    $rows = db_items(
        "SELECT
            order_items.id,
            order_items.ship_to_id,
            order_items.product_id,
            order_items.product_name,
            order_items.quantity,
            order_items.price,
            order_items.shipped_quantity,
            products.short_description
        FROM order_items
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE
            (order_items.order_id = '" . $order_id . "')
            AND (order_items.ship_to_id > 0)
            AND (order_items.saved_for_later = 0)
            " . (($ship_to_id > 0) ? "AND (order_items.ship_to_id = '" . $ship_to_id . "')" : "") . "
        ORDER BY order_items.id");

    $held = pg_return_held($order_id);
    $lines = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $name = trim((string) $row['short_description']);
        $line_held = isset($held[$id]) ? $held[$id] : 0;

        $lines[$id] = array(
            'id'               => $id,
            'ship_to_id'       => (int) $row['ship_to_id'],
            'product_id'       => (int) $row['product_id'],
            'name'             => ($name !== '') ? $name : (string) $row['product_name'],
            'code'             => (string) $row['product_name'],
            'quantity'         => (int) $row['quantity'],
            'price'            => (int) $row['price'],
            'shipped_quantity' => (int) $row['shipped_quantity'],
            'held'             => $line_held,
            'returnable'       => pg_return_returnable($row['quantity'], $line_held),
        );
    }

    return $lines;
}

/**
 * Where a delivery stands for a return: whether its goods have left and its
 * withdrawal window.
 *
 * @param int    $order_id
 * @param int    $ship_to_id
 * @param string $today Y-m-d, or '' for today in the site's time zone
 * @return array|null ship_to (id, ship_date, delivery_date), goods_left,
 *                    window (pg_return_window()); null when the delivery is
 *                    not the order's
 */
function pg_return_delivery_state($order_id, $ship_to_id, $today = '')
{
    $order_id = (int) $order_id;
    $ship_to_id = (int) $ship_to_id;

    if (($order_id <= 0) || ($ship_to_id <= 0)) {
        return null;
    }

    $ship_to = db_item(
        "SELECT id, ship_date, delivery_date
        FROM ship_tos
        WHERE (id = '" . $ship_to_id . "') AND (order_id = '" . $order_id . "')");

    if (!$ship_to) {
        return null;
    }

    if (pg_return_date($today) === '') {
        $today = date('Y-m-d');
    }

    $has_tracking = ((int) db_value(
        "SELECT COUNT(*)
        FROM shipping_tracking_numbers
        WHERE (ship_to_id = '" . $ship_to_id . "') AND (TRIM(number) != '')") > 0);

    $shipped_quantity = (int) db_value(
        "SELECT COALESCE(SUM(shipped_quantity), 0)
        FROM order_items
        WHERE (order_id = '" . $order_id . "') AND (ship_to_id = '" . $ship_to_id . "')");

    // The shipment rows keep no delivery date yet; once they do, that date
    // goes in as 'delivered' and the estimate steps aside.
    $window = pg_return_window(array(
        'delivered'     => '',
        'ship_date'     => (string) $ship_to['ship_date'],
        'delivery_date' => (string) $ship_to['delivery_date'],
    ), $today);

    return array(
        'ship_to'    => array(
            'id'            => (int) $ship_to['id'],
            'ship_date'     => pg_return_date((string) $ship_to['ship_date']),
            'delivery_date' => pg_return_date((string) $ship_to['delivery_date']),
        ),
        'goods_left' => pg_return_goods_left($has_tracking, $shipped_quantity, (string) $ship_to['ship_date'], $today),
        'window'     => $window,
    );
}

/**
 * The lines of some returns, each with the order line's name.
 *
 * @param int[] $return_ids
 * @return array return id => list of order_item_id, quantity, product_id, name
 */
function pg_return_items_of($return_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $return_ids))));
    $out = array();

    if (empty($ids)) {
        return $out;
    }

    $rows = db_items(
        "SELECT
            order_return_items.return_id,
            order_return_items.order_item_id,
            order_return_items.quantity,
            order_items.product_id,
            order_items.product_name,
            products.short_description
        FROM order_return_items
        LEFT JOIN order_items ON order_items.id = order_return_items.order_item_id
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE order_return_items.return_id IN (" . implode(',', $ids) . ")
        ORDER BY order_return_items.id");

    foreach ($rows as $row) {
        $name = trim((string) $row['short_description']);

        $out[(int) $row['return_id']][] = array(
            'order_item_id' => (int) $row['order_item_id'],
            'quantity'      => (int) $row['quantity'],
            'product_id'    => (int) $row['product_id'],
            'name'          => ($name !== '') ? $name : (string) $row['product_name'],
        );
    }

    return $out;
}

/**
 * One return with its lines, or null.
 *
 * @param int $return_id
 * @return array|null the order_returns row, plus items
 */
function pg_return_load($return_id)
{
    if (((int) $return_id <= 0) || !pg_returns_ready()) {
        return null;
    }

    $return = db_item("SELECT * FROM order_returns WHERE id = '" . (int) $return_id . "'");

    if (!$return) {
        return null;
    }

    $items = pg_return_items_of(array($return['id']));
    $return['items'] = isset($items[(int) $return['id']]) ? $items[(int) $return['id']] : array();

    return $return;
}

/**
 * The returns of an order, newest first, each with its lines.
 *
 * @param int $order_id
 * @return array[]
 */
function pg_returns_of_order($order_id)
{
    if (((int) $order_id <= 0) || !pg_returns_ready()) {
        return array();
    }

    $returns = db_items("SELECT * FROM order_returns WHERE order_id = '" . (int) $order_id . "' ORDER BY id DESC");

    if (empty($returns)) {
        return array();
    }

    $items = pg_return_items_of(array_map(function ($row) { return (int) $row['id']; }, $returns));

    foreach ($returns as $index => $return) {
        $returns[$index]['items'] = isset($items[(int) $return['id']]) ? $items[(int) $return['id']] : array();
    }

    return $returns;
}

/* ---------------------------------------------------------------------------
   Writing
   --------------------------------------------------------------------------- */

/**
 * The name of the lock that keeps two requests for one order from both
 * passing the quantity check. GET_LOCK names belong to the whole database
 * server, so the name carries the database.
 *
 * @param int $order_id
 * @return string
 */
function pg_return_lock_name($order_id)
{
    $database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : (string) db_value("SELECT DATABASE()");

    return 'pg_return_' . md5($database) . '_' . (int) $order_id;
}

/**
 * Records a return request.
 *
 * The caller has already checked that the person may ask for this order: a
 * customer that it is theirs, staff that they have commerce rights.
 *
 * @param int    $order_id
 * @param int    $ship_to_id the delivery the goods came in
 * @param array  $quantities order item id => quantity
 * @param string $reason     a code of pg_return_reasons()
 * @param string $note       the customer's words; needed for "other"
 * @param array  $options    source ('customer', 'panel' or 'api'; a customer
 *                           request must be in time), user_id (who asked),
 *                           time (unix time, defaults to now), today (Y-m-d,
 *                           defaults to today)
 * @return array ok, error, id
 */
function pg_return_create($order_id, $ship_to_id, $quantities, $reason, $note, $options = array())
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error, 'id' => 0);
    };

    if (!pg_returns_ready()) {
        return $refuse(lang('Returns are not installed yet: the database has to be updated first.'));
    }

    $order_id = (int) $order_id;
    $ship_to_id = (int) $ship_to_id;
    $source = (isset($options['source']) && in_array($options['source'], array('customer', 'panel', 'api'), true)) ? $options['source'] : 'panel';
    $user_id = isset($options['user_id']) ? max(0, (int) $options['user_id']) : 0;
    $now = isset($options['time']) ? (int) $options['time'] : time();
    $today = (isset($options['today']) && (pg_return_date($options['today']) !== '')) ? $options['today'] : date('Y-m-d', $now);

    $reason = pg_return_reason($reason);
    $note = pg_return_text($note, PG_RETURN_NOTE_MAX);

    if ($reason === '') {
        return $refuse(lang('Choose a reason for the return.'));
    }

    if (($reason === 'other') && ($note === '')) {
        return $refuse(lang('Tell us why you are returning the goods.'));
    }

    $order = db_item("SELECT id, status FROM orders WHERE id = '" . $order_id . "'");

    if (!$order) {
        return $refuse(lang('The order could not be found.'));
    }

    if (!in_array((string) $order['status'], array('complete', 'exported'), true)) {
        return $refuse(lang('Only a completed order can be returned.'));
    }

    $state = pg_return_delivery_state($order_id, $ship_to_id, $today);

    if ($state === null) {
        return $refuse(lang('That delivery is not part of this order.'));
    }

    if ($source === 'customer') {

        if (!$state['goods_left']) {
            return $refuse(lang('This delivery has not been sent yet. The order can be cancelled instead.'));
        }

        // No date at all is known for the delivery: the deadline cannot be
        // worked out, so staff decide rather than the code guessing one.
        if ($state['window'] === null) {
            return $refuse(lang('We cannot tell when this delivery arrived. Please contact us to return it.'));
        }

        if (!$state['window']['open']) {
            return $refuse(lang('The return period for this delivery has ended.'));
        }
    }

    // A second request for the same order (a double click, the customer and
    // staff at once) waits here, then sees what the first one took.
    $lock = pg_return_lock_name($order_id);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 5)") !== 1) {
        return $refuse(lang('Another return is being saved for this order. Try again in a moment.'));
    }

    $checked = pg_return_check_lines($quantities, pg_return_order_lines($order_id, $ship_to_id));

    if (!$checked['ok']) {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

        return $refuse($checked['error']);
    }

    // Both tables are InnoDB: a failed statement ends the request before the
    // commit, and the half-written return goes with the connection.
    db("START TRANSACTION");

    db("INSERT INTO order_returns (order_id, ship_to_id, status, reason, customer_note, decision_note, source, requested_by, requested_at)
        VALUES (
            '" . $order_id . "',
            '" . $ship_to_id . "',
            'requested',
            '" . e($reason) . "',
            '" . e($note) . "',
            '',
            '" . e($source) . "',
            '" . $user_id . "',
            '" . $now . "')");

    $return_id = (int) mysqli_insert_id(db::$con);
    $values = array();

    foreach ($checked['lines'] as $item_id => $quantity) {
        $values[] = "('" . $return_id . "', '" . (int) $item_id . "', '" . (int) $quantity . "')";
    }

    db("INSERT INTO order_return_items (return_id, order_item_id, quantity) VALUES " . implode(', ', $values));

    db("COMMIT");

    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    return array('ok' => true, 'error' => '', 'id' => $return_id);
}

/**
 * Moves a return to another status and stamps when.
 *
 * Approving or rejecting records who decided and the note given with it
 * (shown to the customer); approving may also say which carrier the goods go
 * back with and the code the customer gives it. Sending back may carry the
 * tracking number and the carrier. The move is made only from the status the
 * return was read in, so two people answering at once cannot both win.
 *
 * @param int    $return_id
 * @param string $to
 * @param array  $options by ('customer' or staff), user_id, time, note,
 *                        carrier, carrier_code, tracking_number
 * @return array ok, error, return (the row as it is now)
 */
function pg_return_move($return_id, $to, $options = array())
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error, 'return' => null);
    };

    if (!pg_returns_ready()) {
        return $refuse(lang('Returns are not installed yet: the database has to be updated first.'));
    }

    $return = db_item("SELECT * FROM order_returns WHERE id = '" . (int) $return_id . "'");

    if (!$return) {
        return $refuse(lang('The return could not be found.'));
    }

    $by = (isset($options['by']) && ($options['by'] === 'customer')) ? 'customer' : 'staff';
    $from = (string) $return['status'];
    $to = pg_return_status($to);

    if (($to === '') || !pg_return_can_move($from, $to, $by)) {
        return $refuse(lang(array(
            'string' => 'A return that is "{var:1}" cannot become "{var:2}".',
            'vars'   => array(pg_return_status_label($from), ($to !== '') ? pg_return_status_label($to) : '?'))));
    }

    $now = isset($options['time']) ? (int) $options['time'] : time();
    $set = array(
        "status = '" . e($to) . "'",
        pg_return_stamp_column($to) . " = '" . $now . "'",
    );

    if (in_array($to, array('approved', 'rejected'), true)) {
        $set[] = "decided_by = '" . (isset($options['user_id']) ? max(0, (int) $options['user_id']) : 0) . "'";
        $set[] = "decision_note = '" . e(pg_return_text(isset($options['note']) ? $options['note'] : '', PG_RETURN_NOTE_MAX)) . "'";
    }

    if (in_array($to, array('approved', 'shipped'), true) && isset($options['carrier'])) {
        $set[] = "carrier = '" . e(pg_return_carrier($options['carrier'])) . "'";
    }

    if (($to === 'approved') && isset($options['carrier_code'])) {
        $set[] = "carrier_code = '" . e(pg_return_text($options['carrier_code'], 64)) . "'";
    }

    if (($to === 'shipped') && isset($options['tracking_number'])) {
        $set[] = "tracking_number = '" . e(pg_return_text($options['tracking_number'], 100)) . "'";
    }

    db("UPDATE order_returns SET " . implode(', ', $set) . "
        WHERE (id = '" . (int) $return['id'] . "') AND (status = '" . e($from) . "')");

    if ((int) mysqli_affected_rows(db::$con) !== 1) {
        return $refuse(lang('The return was changed in the meantime. Open it again.'));
    }

    return array('ok' => true, 'error' => '', 'return' => pg_return_load($return['id']));
}

/**
 * Removes the returns of an order that is being deleted.
 *
 * @param int $order_id
 */
function pg_returns_delete_for_order($order_id)
{
    if (((int) $order_id <= 0) || !pg_returns_ready()) {
        return;
    }

    db("DELETE order_return_items
        FROM order_return_items
        JOIN order_returns ON order_returns.id = order_return_items.return_id
        WHERE order_returns.order_id = '" . (int) $order_id . "'");

    db("DELETE FROM order_returns WHERE order_id = '" . (int) $order_id . "'");
}
