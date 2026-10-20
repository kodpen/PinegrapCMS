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
 * The customer's side is the order view widget of My Account (the tokens
 * __return_form and __returns, includes/fn/widgets.php) and the endpoint it
 * posts to (order_return.php), behind ECOMMERCE_ORDER_RETURNS_ALLOWED. A
 * request mails the customer and rings the staff's bell.
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
 * The most returns the panel list reads at once, newest first.
 */
define('PG_RETURN_PANEL_LIMIT', 1000);

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

/**
 * The carrier as people read it: the name of a carrier the site knows, or the
 * stored text itself.
 *
 * @param string $carrier as pg_return_carrier() stores it
 * @return string
 */
function pg_return_carrier_label($carrier)
{
    $carrier = (string) $carrier;
    $carriers = function_exists('pg_shipping_carriers') ? pg_shipping_carriers() : array();

    return isset($carriers[$carrier]['name']) ? (string) $carriers[$carrier]['name'] : $carrier;
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
 * delivery date (every parcel of the delivery arrived, pg_return_parcel_delivery())
 * opens them by itself. Without one the delivery is estimated: three days
 * after the ship date, the recipient's own expected delivery date, or the day
 * the latest parcel known to have arrived did, whichever is latest. The first
 * two may be dates the checkout planned rather than ones that happened, so
 * the latest is taken: an estimate that is too late gives the customer a few
 * days more, one that is too early would cut their legal period short.
 *
 * The window is open on every day up to and including the deadline, before
 * the delivery too (a customer may withdraw before the goods arrive).
 *
 * @param array  $dates delivered (known, Y-m-d), parcel_delivered (the latest
 *                      parcel that arrived while others have not), ship_date
 *                      and delivery_date (from ship_tos); a missing or empty
 *                      date is unknown
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
        $parcel = pg_return_date(isset($dates['parcel_delivered']) ? $dates['parcel_delivered'] : '');

        if ($ship_date !== '') {
            $candidates[] = pg_return_add_days($ship_date, PG_RETURN_DELIVERY_DAYS);
        }

        if ($expected !== '') {
            $candidates[] = $expected;
        }

        if ($parcel !== '') {
            $candidates[] = $parcel;
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
 * When the parcels of a delivery reached the recipient, from their shipment
 * rows (shipping_tracking_numbers.delivered_at, 2026.11.0).
 *
 * The goods are delivered when the last parcel is, so a delivery date is
 * known only when every parcel with a number has one. While some have
 * arrived and others not, the latest known arrival is still a day the goods
 * were not all delivered before, which the estimate takes into account.
 *
 * @param string[] $dates one per parcel with a number: Y-m-d, or '' while unknown
 * @return array delivered (Y-m-d, or '' unless every parcel arrived), latest
 *               (the latest known arrival, or '')
 */
function pg_return_parcel_delivery($dates)
{
    $known = array();
    $all = true;

    foreach ((array) $dates as $date) {
        $date = pg_return_date(is_string($date) ? $date : '');

        if ($date === '') {
            $all = false;
        } else {
            $known[] = $date;
        }
    }

    if (empty($known)) {
        return array('delivered' => '', 'latest' => '');
    }

    sort($known, SORT_STRING);
    $latest = end($known);

    return array('delivered' => $all ? $latest : '', 'latest' => $latest);
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
 * Where a delivery stands for a return: whether its goods have left, its
 * parcels and its withdrawal window.
 *
 * @param int    $order_id
 * @param int    $ship_to_id
 * @param string $today Y-m-d, or '' for today in the site's time zone
 * @return array|null ship_to (id, name, ship_date, delivery_date,
 *                    method_code), parcels (number, carrier, delivered),
 *                    goods_left, window (pg_return_window()); null when the
 *                    delivery is not the order's
 */
function pg_return_delivery_state($order_id, $ship_to_id, $today = '')
{
    $order_id = (int) $order_id;
    $ship_to_id = (int) $ship_to_id;

    if (($order_id <= 0) || ($ship_to_id <= 0)) {
        return null;
    }

    $ship_to = db_item(
        "SELECT id, first_name, last_name, ship_date, delivery_date, shipping_method_code
        FROM ship_tos
        WHERE (id = '" . $ship_to_id . "') AND (order_id = '" . $order_id . "')");

    if (!$ship_to) {
        return null;
    }

    if (pg_return_date($today) === '') {
        $today = date('Y-m-d');
    }

    // The carrier and delivery columns of a parcel came with 2026.11.0
    // (11.0.20); before that step only the number is there.
    $rich = pg_schema_has('shipping_tracking_numbers', 'delivered_at');

    $rows = db_items(
        "SELECT number" . ($rich ? ", carrier, delivered_at" : "") . "
        FROM shipping_tracking_numbers
        WHERE (ship_to_id = '" . $ship_to_id . "') AND (TRIM(number) != '')
        ORDER BY id");

    $method_code = (string) $ship_to['shipping_method_code'];
    $default_carrier = function_exists('pg_shipping_default_carrier') ? pg_shipping_default_carrier() : '';
    $parcels = array();
    $arrivals = array();

    foreach ($rows as $row) {
        $carrier = $rich ? strtolower(trim((string) $row['carrier'])) : '';

        if (($carrier === '') || !function_exists('pg_shipping_carrier_exists') || !pg_shipping_carrier_exists($carrier)) {
            $carrier = function_exists('pg_shipping_carrier') ? pg_shipping_carrier((string) $row['number'], $method_code, $default_carrier) : '';
        }

        $delivered = ($rich && ((int) $row['delivered_at'] > 0)) ? date('Y-m-d', (int) $row['delivered_at']) : '';
        $arrivals[] = $delivered;

        $parcels[] = array(
            'number'    => trim((string) $row['number']),
            'carrier'   => $carrier,
            'delivered' => $delivered,
        );
    }

    $shipped_quantity = (int) db_value(
        "SELECT COALESCE(SUM(shipped_quantity), 0)
        FROM order_items
        WHERE (order_id = '" . $order_id . "') AND (ship_to_id = '" . $ship_to_id . "')");

    $arrived = pg_return_parcel_delivery($arrivals);

    $window = pg_return_window(array(
        'delivered'        => $arrived['delivered'],
        'parcel_delivered' => $arrived['latest'],
        'ship_date'        => (string) $ship_to['ship_date'],
        'delivery_date'    => (string) $ship_to['delivery_date'],
    ), $today);

    return array(
        'ship_to'    => array(
            'id'            => (int) $ship_to['id'],
            'name'          => trim(trim((string) $ship_to['first_name']) . ' ' . trim((string) $ship_to['last_name'])),
            'ship_date'     => pg_return_date((string) $ship_to['ship_date']),
            'delivery_date' => pg_return_date((string) $ship_to['delivery_date']),
            'method_code'   => $method_code,
        ),
        'parcels'    => $parcels,
        'goods_left' => pg_return_goods_left(!empty($parcels), $shipped_quantity, (string) $ship_to['ship_date'], $today),
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

/* ---------------------------------------------------------------------------
   The customer's side: My Account's order view
   --------------------------------------------------------------------------- */

/**
 * May customers ask for returns from their account? A site setting, off
 * unless data/config.php switches it on (ECOMMERCE_ORDER_RETURNS_ALLOWED), as
 * cancellation is (ECOMMERCE_ORDER_CANCEL_ALLOWED); staff record returns
 * either way.
 *
 * @return bool
 */
function pg_returns_customer_enabled()
{
    return defined('ECOMMERCE_ORDER_RETURNS_ALLOWED') && (ECOMMERCE_ORDER_RETURNS_ALLOWED === true) && pg_returns_ready();
}

/**
 * The Bootstrap badge classes of a status.
 *
 * @param string $status
 * @return string
 */
function pg_return_badge_class($status)
{
    $classes = array(
        'requested' => 'badge text-bg-warning',
        'approved'  => 'badge text-bg-info',
        'rejected'  => 'badge text-bg-danger',
        'shipped'   => 'badge text-bg-primary',
        'received'  => 'badge text-bg-primary',
        'refunded'  => 'badge text-bg-success',
        'closed'    => 'badge text-bg-secondary',
    );

    return (is_string($status) && isset($classes[$status])) ? $classes[$status] : 'badge text-bg-secondary';
}

/**
 * One return as the customer reads it: labels instead of codes, dates as the
 * page shows them, the carrier by its name, and what the customer may still
 * do about it.
 *
 * @param array    $return      a row of pg_returns_of_order()
 * @param bool     $enabled     pg_returns_customer_enabled()
 * @param callable $format_date Y-m-d => the text the page shows
 * @return array
 */
function pg_return_present($return, $enabled, $format_date)
{
    $format = is_callable($format_date) ? $format_date : function ($date) { return $date; };
    $day = function ($stamp) { return ((int) $stamp > 0) ? date('Y-m-d', (int) $stamp) : ''; };
    $status = (string) $return['status'];
    $reasons = pg_return_reasons();
    $carrier = pg_return_carrier_label((string) $return['carrier']);

    // The refund is due once the goods are on their way back, until it is paid.
    $refund = in_array($status, array('shipped', 'received'), true)
        ? pg_return_refund_deadline($day($return['shipped_at']), $day($return['received_at']))
        : '';

    $lines = array();

    foreach ((array) (isset($return['items']) ? $return['items'] : array()) as $item) {
        $lines[] = array('name' => (string) $item['name'], 'quantity' => (int) $item['quantity']);
    }

    $requested = $day($return['requested_at']);

    return array(
        'id'              => (int) $return['id'],
        'status'          => $status,
        'status_label'    => pg_return_status_label($status),
        'requested'       => ($requested !== '') ? $format($requested) : '',
        'lines'           => $lines,
        'reason_label'    => isset($reasons[(string) $return['reason']]) ? $reasons[(string) $return['reason']] : '',
        'customer_note'   => (string) $return['customer_note'],
        'decision_note'   => (string) $return['decision_note'],
        'carrier'         => $carrier,
        'carrier_code'    => (string) $return['carrier_code'],
        'tracking_number' => (string) $return['tracking_number'],
        'refund_deadline' => ($refund !== '') ? $format($refund) : '',
        'can_withdraw'    => $enabled && pg_return_can_move($status, 'closed', 'customer'),
        'can_mark_sent'   => $enabled && pg_return_can_move($status, 'shipped', 'customer'),
    );
}

/**
 * What an order's owner sees about returns: the returns of the order, and
 * the deliveries goods can still be asked back from with their lines. The
 * caller has checked that the viewer owns the order.
 *
 * A delivery is offered while the customer may ask (pg_return_create() with
 * source 'customer' says the same): the setting is on, the order is complete,
 * the goods have left, the window is open and something is left to ask for.
 * The returns are listed whatever the setting, so a customer can still read
 * what became of a request; the moves on them need the setting.
 *
 * @param int      $order_id
 * @param callable $format_date Y-m-d => the text the page shows
 * @param string   $today       Y-m-d, or '' for today
 * @return array enabled, deliveries (ship_to_id, label, deadline, lines of
 *               id, name, quantity, returnable), returns
 *               (pg_return_present()), can_request, has_returns
 */
function pg_return_customer_view($order_id, $format_date = null, $today = '')
{
    $view = array('enabled' => false, 'deliveries' => array(), 'returns' => array(), 'can_request' => false, 'has_returns' => false);
    $order_id = (int) $order_id;

    if (($order_id <= 0) || !pg_returns_ready()) {
        return $view;
    }

    $format = is_callable($format_date) ? $format_date : function ($date) { return $date; };
    $enabled = pg_returns_customer_enabled();
    $view['enabled'] = $enabled;

    foreach (pg_returns_of_order($order_id) as $return) {
        $view['returns'][] = pg_return_present($return, $enabled, $format);
    }

    $view['has_returns'] = !empty($view['returns']);

    if (!$enabled || !in_array((string) db_value("SELECT status FROM orders WHERE id = '" . $order_id . "'"), array('complete', 'exported'), true)) {
        return $view;
    }

    if (pg_return_date($today) === '') {
        $today = date('Y-m-d');
    }

    $by_delivery = array();

    foreach (pg_return_order_lines($order_id) as $line) {
        $by_delivery[$line['ship_to_id']][] = $line;
    }

    foreach ($by_delivery as $ship_to_id => $lines) {
        $state = pg_return_delivery_state($order_id, $ship_to_id, $today);

        if (!$state || !$state['goods_left'] || ($state['window'] === null) || !$state['window']['open']) {
            continue;
        }

        $left = 0;
        $shown = array();

        foreach ($lines as $line) {
            $left += $line['returnable'];
            $shown[] = array('id' => $line['id'], 'name' => $line['name'], 'quantity' => $line['quantity'], 'returnable' => $line['returnable']);
        }

        if ($left > 0) {
            $view['deliveries'][] = array(
                'ship_to_id' => (int) $ship_to_id,
                'label'      => $state['ship_to']['name'],
                'deadline'   => $format($state['window']['deadline']),
                'lines'      => $shown,
            );
        }
    }

    $view['can_request'] = !empty($view['deliveries']);

    return $view;
}

/**
 * The form a customer asks for a return with: one per delivery that still
 * has something to give back, its lines with a box for the quantity, the
 * reason and a note. Posts to order_return.php.
 *
 * @param array $deliveries pg_return_customer_view()'s deliveries
 * @param array $form       action (the endpoint), send_to (back to this
 *                          page), token (the CSRF field), order_id
 * @return string HTML, or '' when there is nothing to ask for
 */
function pg_return_form_html($deliveries, $form)
{
    if (!is_array($deliveries) || empty($deliveries)) {
        return '';
    }

    $several = (count($deliveries) > 1);
    $options = '<option value="">' . h(lang('Choose a reason')) . '</option>';

    foreach (pg_return_reasons() as $code => $label) {
        $options .= '<option value="' . h($code) . '">' . h($label) . '</option>';
    }

    $html = '';

    foreach ($deliveries as $delivery) {
        $ship_to_id = (int) $delivery['ship_to_id'];
        $rows = '';
        $any = false;

        foreach ((array) $delivery['lines'] as $line) {
            $id = (int) $line['id'];
            $left = max(0, (int) $line['returnable']);

            if ($left > 0) {
                $any = true;
                $cell = '<input type="number" class="form-control form-control-sm text-end" id="pg-ov-return-qty-' . $id . '"'
                    . ' name="qty[' . $id . ']" min="0" max="' . $left . '" step="1" value="0" inputmode="numeric"'
                    . ' aria-label="' . h(lang(array('string' => 'How many to return: {var:1}', 'vars' => array((string) $line['name'])))) . '">';
            } else {
                $cell = '<span class="small text-muted">' . h(lang('Already in a return')) . '</span>';
            }

            $rows .= '<tr><td>' . h((string) $line['name']) . '</td>'
                . '<td class="text-end">' . (int) $line['quantity'] . '</td>'
                . '<td class="text-end">' . $cell . '</td></tr>';
        }

        if (!$any) {
            continue;
        }

        $label = isset($delivery['label']) ? (string) $delivery['label'] : '';
        $deadline = isset($delivery['deadline']) ? (string) $delivery['deadline'] : '';

        $html .= '<form method="post" action="' . h((string) $form['action']) . '" class="pg-ov-return-form mb-4">'
            . (string) $form['token']
            . '<input type="hidden" name="action" value="request">'
            . '<input type="hidden" name="order_id" value="' . (int) $form['order_id'] . '">'
            . '<input type="hidden" name="ship_to_id" value="' . $ship_to_id . '">'
            . '<input type="hidden" name="send_to" value="' . h((string) $form['send_to']) . '">'
            . (($several && ($label !== '')) ? '<h6 class="fw-semibold mb-2">' . h(lang(array('string' => 'Sent to {var:1}', 'vars' => array($label)))) . '</h6>' : '')
            . (($deadline !== '') ? '<p class="small text-muted mb-2">' . h(lang(array('string' => 'You can ask to return these items until {var:1}.', 'vars' => array($deadline)))) . '</p>' : '')
            . '<div class="table-responsive"><table class="table table-sm align-middle mb-3">'
            . '<thead><tr><th scope="col">' . h(lang('Product')) . '</th>'
            . '<th scope="col" class="text-end">' . h(lang('Ordered')) . '</th>'
            . '<th scope="col" class="text-end">' . h(lang('To return')) . '</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table></div>'
            . '<div class="mb-3"><label for="pg-ov-return-reason-' . $ship_to_id . '" class="form-label small fw-semibold">' . h(lang('Reason for the return')) . '</label>'
            . '<select class="form-select" id="pg-ov-return-reason-' . $ship_to_id . '" name="reason" required>' . $options . '</select></div>'
            . '<div class="mb-3"><label for="pg-ov-return-note-' . $ship_to_id . '" class="form-label small fw-semibold">' . h(lang('Note (optional)')) . '</label>'
            . '<textarea class="form-control" id="pg-ov-return-note-' . $ship_to_id . '" name="note" rows="2" maxlength="' . PG_RETURN_NOTE_MAX . '"'
            . ' aria-describedby="pg-ov-return-note-help-' . $ship_to_id . '"></textarea>'
            . '<div id="pg-ov-return-note-help-' . $ship_to_id . '" class="form-text">' . h(lang('Needed when the reason is "Another reason".')) . '</div></div>'
            . '<button type="submit" class="btn btn-outline-primary"><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>' . h(lang('Request a return')) . '</button>'
            . '</form>';
    }

    return $html;
}

/**
 * The hidden fields every customer action on a return carries.
 *
 * @param array  $form   as pg_return_form_html() takes it
 * @param string $action withdraw or shipped
 * @param int    $return_id
 * @return string
 */
function pg_return_action_fields($form, $action, $return_id)
{
    return (string) $form['token']
        . '<input type="hidden" name="action" value="' . h($action) . '">'
        . '<input type="hidden" name="order_id" value="' . (int) $form['order_id'] . '">'
        . '<input type="hidden" name="return_id" value="' . (int) $return_id . '">'
        . '<input type="hidden" name="send_to" value="' . h((string) $form['send_to']) . '">';
}

/**
 * The returns of an order as the customer follows them: where each stands,
 * its lines, what the shop wrote, how to send the goods once it is approved,
 * when the refund is due, and the customer's own moves (withdraw a request,
 * say the parcel is on its way).
 *
 * @param array $returns pg_return_present() rows
 * @param array $form    as pg_return_form_html() takes it
 * @return string HTML, or '' when the order has no returns
 */
function pg_returns_list_html($returns, $form)
{
    if (!is_array($returns) || empty($returns)) {
        return '';
    }

    $detail = function ($label, $value_html) {
        return '<div class="small mb-1"><span class="text-muted">' . h($label) . ':</span> ' . $value_html . '</div>';
    };

    $html = '<div class="pg-ov-returns">';

    foreach ($returns as $return) {
        $id = (int) $return['id'];
        $lines = '';

        foreach ((array) $return['lines'] as $line) {
            $lines .= '<li>' . h((string) $line['name']) . ' × ' . (int) $line['quantity'] . '</li>';
        }

        $html .= '<div class="pg-ov-return border rounded p-3 mb-3">'
            . '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">'
            . '<div><div class="fw-semibold">' . h(lang(array('string' => 'Return request #{var:1}', 'vars' => array($id)))) . '</div>'
            . (((string) $return['requested'] !== '') ? '<div class="small text-muted">' . h(lang(array('string' => 'Asked on {var:1}', 'vars' => array((string) $return['requested'])))) . '</div>' : '')
            . '</div>'
            . '<span class="' . h(pg_return_badge_class((string) $return['status'])) . '">' . h((string) $return['status_label']) . '</span>'
            . '</div>'
            . (($lines !== '') ? '<ul class="small mb-2 ps-3">' . $lines . '</ul>' : '');

        if ((string) $return['reason_label'] !== '') {
            $html .= $detail(lang('Reason'), h((string) $return['reason_label']));
        }

        if ((string) $return['customer_note'] !== '') {
            $html .= $detail(lang('Your note'), nl2br(h((string) $return['customer_note'])));
        }

        if ((string) $return['decision_note'] !== '') {
            $html .= $detail(lang('Note from the shop'), nl2br(h((string) $return['decision_note'])));
        }

        // How the goods go back, once the shop has said yes.
        if ((string) $return['status'] === 'approved') {
            $carrier = (string) $return['carrier'];
            $code = (string) $return['carrier_code'];

            if (($carrier !== '') && ($code !== '')) {
                $how = h(lang(array('string' => 'Send the parcel with {var:1} and give them this return code:', 'vars' => array($carrier)))) . ' <strong>' . h($code) . '</strong>';
            } elseif ($carrier !== '') {
                $how = h(lang(array('string' => 'Send the parcel with {var:1}.', 'vars' => array($carrier))));
            } elseif ($code !== '') {
                $how = h(lang('Give the carrier this return code:')) . ' <strong>' . h($code) . '</strong>';
            } else {
                $how = h(lang('Your return was approved. You can send the goods back now.'));
            }

            $html .= '<div class="alert alert-info small py-2 my-2" role="status">' . $how . '</div>';
        }

        if ((string) $return['tracking_number'] !== '') {
            $html .= $detail(lang('Tracking number'), h((string) $return['tracking_number']));
        }

        if ((string) $return['refund_deadline'] !== '') {
            $html .= $detail(lang('Refund due by'), h((string) $return['refund_deadline']));
        }

        if (!empty($return['can_mark_sent'])) {
            $html .= '<form method="post" action="' . h((string) $form['action']) . '" class="mt-2">'
                . pg_return_action_fields($form, 'shipped', $id)
                . '<label for="pg-ov-return-tracking-' . $id . '" class="form-label small fw-semibold">' . h(lang('Tracking number of the parcel (optional)')) . '</label>'
                . '<div class="input-group input-group-sm">'
                . '<input type="text" class="form-control" id="pg-ov-return-tracking-' . $id . '" name="tracking_number" maxlength="100" autocomplete="off">'
                . '<button type="submit" class="btn btn-primary">' . h(lang('I have sent it')) . '</button>'
                . '</div></form>';
        }

        if (!empty($return['can_withdraw'])) {
            $html .= '<form method="post" action="' . h((string) $form['action']) . '" class="mt-2">'
                . pg_return_action_fields($form, 'withdraw', $id)
                . '<button type="submit" class="btn btn-sm btn-outline-secondary">' . h(lang('Withdraw the request')) . '</button>'
                . '</form>';
        }

        $html .= '</div>';
    }

    return $html . '</div>';
}

/**
 * The returns block of an order view whose layout places neither the list
 * nor the form: a design made before returns existed still shows them once
 * the setting is on.
 *
 * @param string $list_html pg_returns_list_html()
 * @param string $form_html pg_return_form_html()
 * @return string
 */
function pg_return_block_html($list_html, $form_html)
{
    if (((string) $list_html === '') && ((string) $form_html === '')) {
        return '';
    }

    return '<div class="container pb-4 pg-ov-returns-block"><div class="card">'
        . '<div class="card-header"><h6 class="mb-0 fw-semibold">' . h(lang('Returns')) . '</h6></div>'
        . '<div class="card-body">' . $list_html . $form_html . '</div>'
        . '</div></div>';
}

/**
 * The body of the mail that tells a customer their request arrived.
 *
 * @param array $data customer, order_number, lines (name, quantity),
 *                    reason_label, customer_note, from_name
 * @return string HTML
 */
function pg_return_request_mail_body($data)
{
    $customer = isset($data['customer']) ? (string) $data['customer'] : '';
    $reason = isset($data['reason_label']) ? (string) $data['reason_label'] : '';
    $note = isset($data['customer_note']) ? (string) $data['customer_note'] : '';
    $items = '';

    foreach ((array) (isset($data['lines']) ? $data['lines'] : array()) as $line) {
        $items .= '<li>' . h((string) $line['name']) . ' × ' . (int) $line['quantity'] . '</li>';
    }

    return '<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;background:#f7f7f7;padding:20px;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;padding:24px;border-radius:6px;border:1px solid #e5e5e5;">'
        . '<h2 style="margin:0 0 16px;font-size:18px;">' . h(lang('Return request received')) . '</h2>'
        . '<p style="margin:0 0 12px;">' . h(lang('Hello')) . (($customer !== '') ? ' ' . h($customer) : '') . ',</p>'
        . '<p style="margin:0 0 12px;">' . h(lang(array(
            'string' => 'We received your request to return items from order #{var:1}. We will check it and let you know what happens next.',
            'vars'   => array(isset($data['order_number']) ? (string) $data['order_number'] : '')))) . '</p>'
        . (($items !== '') ? '<ul style="margin:0 0 12px;padding-left:20px;">' . $items . '</ul>' : '')
        . (($reason !== '') ? '<p style="margin:0 0 12px;"><strong>' . h(lang('Reason')) . ':</strong> ' . h($reason) . '</p>' : '')
        . (($note !== '') ? '<p style="margin:0 0 12px;"><strong>' . h(lang('Your note')) . ':</strong> ' . nl2br(h($note)) . '</p>' : '')
        . '<p style="margin:16px 0 0;color:#666;font-size:12px;">' . h(isset($data['from_name']) ? (string) $data['from_name'] : '') . '</p>'
        . '</div></body></html>';
}

/**
 * Tells the customer their request arrived, at the order's billing address.
 * Handed to the mail queue when the general job runs, so the customer does
 * not wait on the mail server; skipped when the order has no address or the
 * site has no sender.
 *
 * @param int $return_id
 * @return bool sent or queued
 */
function pg_return_send_request_mail($return_id)
{
    $context = pg_return_mail_context($return_id);

    if ($context === null) {
        return false;
    }

    $reasons = pg_return_reasons();
    $return = $context['return'];

    return (bool) email(array(
        'to'                 => $context['to'],
        'to_name'            => $context['customer'],
        'from_name'          => $context['from_name'],
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang(array('string' => 'We received your return request for order #{var:1}', 'vars' => array($context['order_number']))),
        'format'             => 'html',
        'body'               => pg_return_request_mail_body(array(
            'customer'      => $context['customer'],
            'order_number'  => $context['order_number'],
            'lines'         => $context['lines'],
            'reason_label'  => isset($reasons[(string) $return['reason']]) ? $reasons[(string) $return['reason']] : '',
            'customer_note' => (string) $return['customer_note'],
            'from_name'     => $context['from_name'],
        )),
        'type'               => 'system',
        'queue'              => true,
    ));
}

/**
 * What every mail about a return needs: the return, the order's billing
 * address and name, the order number, the lines, and the sender's name.
 *
 * @param int $return_id
 * @return array|null return, to, customer, order_number, lines (name,
 *                    quantity), from_name; null when the return is gone, the
 *                    order has no address or the site has no sender
 */
function pg_return_mail_context($return_id)
{
    $return = pg_return_load($return_id);

    if (!$return) {
        return null;
    }

    $order = db_item(
        "SELECT id, order_number, billing_first_name, billing_last_name, billing_email_address
        FROM orders
        WHERE id = '" . (int) $return['order_id'] . "'");

    $to = is_array($order) ? trim((string) $order['billing_email_address']) : '';

    if (($to === '') || (strpos($to, '@') === false) || !defined('EMAIL_ADDRESS') || (EMAIL_ADDRESS === '')) {
        return null;
    }

    $lines = array();

    foreach ($return['items'] as $item) {
        $lines[] = array('name' => $item['name'], 'quantity' => $item['quantity']);
    }

    return array(
        'return'       => $return,
        'to'           => $to,
        'customer'     => trim(trim((string) $order['billing_first_name']) . ' ' . trim((string) $order['billing_last_name'])),
        'order_number' => ((string) $order['order_number'] !== '') ? (string) $order['order_number'] : (string) (int) $order['id'],
        'lines'        => $lines,
        'from_name'    => (defined('ORGANIZATION_NAME') && (ORGANIZATION_NAME !== ''))
            ? ORGANIZATION_NAME
            : (defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : 'Pinegrap'),
    );
}

/**
 * Tells the shop's staff on the panel bell (and on their subscribed devices)
 * that a customer asked for a return or sent the goods back. The row carries
 * the order number as its title, what happened as order_total ('requested'
 * or 'shipped') and the return as form_id; the sentence around them is built
 * by pg_notification_display().
 *
 * @param int    $return_id
 * @param string $event requested or shipped
 * @return int|false the notification id
 */
function pg_return_notify_staff($return_id, $event)
{
    $row = db_item(
        "SELECT order_returns.id, order_returns.order_id, orders.order_number
        FROM order_returns
        JOIN orders ON orders.id = order_returns.order_id
        WHERE order_returns.id = '" . (int) $return_id . "'");

    if (!$row || !function_exists('create_notification')) {
        return false;
    }

    return create_notification(array(
        'action'      => 'order_return',
        'type'        => 'info',
        'title'       => ((string) $row['order_number'] !== '') ? (string) $row['order_number'] : (string) (int) $row['order_id'],
        'order_id'    => (int) $row['order_id'],
        'form_id'     => (int) $row['id'],
        'order_total' => ($event === 'shipped') ? 'shipped' : 'requested',
    ));
}

/* ---------------------------------------------------------------------------
   The panel: the Returns list (view_returns.php) and one return
   (view_return.php), for whoever manages the orders
   --------------------------------------------------------------------------- */

/**
 * The statuses in which a return still waits on somebody: staff to decide,
 * the customer to send the goods, the parcel to arrive, the money to go back.
 *
 * @return string[]
 */
function pg_return_open_statuses()
{
    return array('requested', 'approved', 'shipped', 'received');
}

/**
 * The statuses a list filter stands for: "open" (the default, also for any
 * value that is not a filter), one status, or "all".
 *
 * @param mixed $filter
 * @return string[]
 */
function pg_return_filter_statuses($filter)
{
    if ($filter === 'all') {
        return pg_return_statuses();
    }

    $status = pg_return_status($filter);

    return ($status !== '') ? array($status) : pg_return_open_statuses();
}

/**
 * The choices of the list's status filter, each with how many returns it
 * holds.
 *
 * @param array $counts status => number of returns
 * @return array value => label
 */
function pg_return_filter_options($counts)
{
    $count = function ($status) use ($counts) {
        return (is_array($counts) && isset($counts[$status])) ? (int) $counts[$status] : 0;
    };

    $open = 0;

    foreach (pg_return_open_statuses() as $status) {
        $open += $count($status);
    }

    $options = array('open' => lang(array('string' => 'Open ({var:1})', 'vars' => array($open))));
    $all = 0;

    foreach (pg_return_statuses() as $status) {
        $all += $count($status);
        $options[$status] = lang(array('string' => '{var:1} ({var:2})', 'vars' => array(pg_return_status_label($status), $count($status))));
    }

    $options['all'] = lang(array('string' => 'All ({var:1})', 'vars' => array($all)));

    return $options;
}

/**
 * How many returns are in each status.
 *
 * @return array status => number
 */
function pg_return_status_counts()
{
    $counts = array();

    if (!pg_returns_ready()) {
        return $counts;
    }

    foreach (db_items("SELECT status, COUNT(*) AS number FROM order_returns GROUP BY status") as $row) {
        $counts[(string) $row['status']] = (int) $row['number'];
    }

    return $counts;
}

/**
 * The returns the list shows, newest request first, each with its order's
 * number and billing name and its lines.
 *
 * @param string[] $statuses
 * @param int      $limit
 * @return array[] order_returns rows plus order_number, customer, email, items
 */
function pg_returns_panel_rows($statuses, $limit = PG_RETURN_PANEL_LIMIT)
{
    $statuses = array_values(array_filter(array_map('pg_return_status', (array) $statuses)));

    if (empty($statuses) || !pg_returns_ready()) {
        return array();
    }

    $in = array();

    foreach ($statuses as $status) {
        $in[] = "'" . e($status) . "'";
    }

    $rows = db_items(
        "SELECT
            order_returns.*,
            orders.order_number,
            orders.billing_first_name,
            orders.billing_last_name,
            orders.billing_email_address
        FROM order_returns
        LEFT JOIN orders ON orders.id = order_returns.order_id
        WHERE order_returns.status IN (" . implode(', ', $in) . ")
        ORDER BY order_returns.requested_at DESC, order_returns.id DESC
        LIMIT " . max(1, (int) $limit));

    if (empty($rows)) {
        return array();
    }

    $items = pg_return_items_of(array_map(function ($row) { return (int) $row['id']; }, $rows));

    foreach ($rows as $index => $row) {
        $rows[$index]['customer'] = trim(trim((string) $row['billing_first_name']) . ' ' . trim((string) $row['billing_last_name']));
        $rows[$index]['email'] = (string) $row['billing_email_address'];
        $rows[$index]['items'] = isset($items[(int) $row['id']]) ? $items[(int) $row['id']] : array();
    }

    return $rows;
}

/**
 * A calendar date as the panel prints it, in the site's day and month order.
 *
 * @param string $date Y-m-d
 * @return string '' when the value is not a date
 */
function pg_return_day_text($date)
{
    $date = pg_return_date($date);

    if ($date === '') {
        return '';
    }

    list($year, $month, $day) = array_map('intval', explode('-', $date));

    return (defined('DATE_FORMAT') && (DATE_FORMAT == 'month_day'))
        ? $month . '/' . $day . '/' . $year
        : $day . '/' . $month . '/' . $year;
}

/**
 * A moment as the panel prints it: the date in the site's order and the time
 * on the site's clock.
 *
 * @param int $stamp unix time
 * @return string '' for 0
 */
function pg_return_time_text($stamp)
{
    $stamp = (int) $stamp;

    if ($stamp <= 0) {
        return '';
    }

    $clock = (defined('TIME_FORMAT') && (TIME_FORMAT == 'twelve_hours')) ? 'g:i A' : 'H:i';

    return pg_return_day_text(date('Y-m-d', $stamp)) . ' ' . date($clock, $stamp);
}

/**
 * The refund due date of a return, while it is due: from the moment the
 * goods are on their way back until the money is.
 *
 * @param array $return a row of order_returns
 * @return string Y-m-d, or ''
 */
function pg_return_refund_due($return)
{
    if (!in_array((string) $return['status'], array('shipped', 'received'), true)) {
        return '';
    }

    $day = function ($stamp) { return ((int) $stamp > 0) ? date('Y-m-d', (int) $stamp) : ''; };

    return pg_return_refund_deadline($day($return['shipped_at']), $day($return['received_at']));
}

/**
 * What the approval form offers: the carrier the goods go back with and the
 * code the customer gives it.
 *
 * The carrier is the one the delivery came with (its parcel row, or the one
 * recognised from the shipping method), else the site's default carrier,
 * else the one of the latest earlier approval. The code is the one given with
 * the latest earlier approval for that carrier: a shop's contracted return
 * code stays the same from one return to the next.
 *
 * @param string $delivery_carrier the delivery's carrier key, or ''
 * @param string $site_default     pg_shipping_default_carrier()
 * @param array  $previous         earlier approvals, newest first: carrier, carrier_code
 * @return array carrier, carrier_code
 */
function pg_return_approval_defaults($delivery_carrier, $site_default, $previous)
{
    $previous = is_array($previous) ? array_values($previous) : array();
    $carrier = pg_return_carrier($delivery_carrier);

    if ($carrier === '') {
        $carrier = pg_return_carrier($site_default);
    }

    if (($carrier === '') && isset($previous[0]['carrier'])) {
        $carrier = pg_return_carrier($previous[0]['carrier']);
    }

    $code = '';

    if ($carrier !== '') {
        foreach ($previous as $row) {
            $given = pg_return_text(isset($row['carrier_code']) ? $row['carrier_code'] : '', 64);

            if (($given !== '') && (strcasecmp(pg_return_carrier(isset($row['carrier']) ? $row['carrier'] : ''), $carrier) === 0)) {
                $code = $given;
                break;
            }
        }
    }

    return array('carrier' => $carrier, 'carrier_code' => $code);
}

/**
 * The carrier and code the approval form of a return starts with
 * (pg_return_approval_defaults()), read from its delivery and the shop's
 * earlier approvals.
 *
 * @param array $return a row of order_returns
 * @param array|null $state pg_return_delivery_state() of its delivery, when already read
 * @return array carrier, carrier_code
 */
function pg_return_suggested_approval($return, $state = null)
{
    if ($state === null) {
        $state = pg_return_delivery_state($return['order_id'], $return['ship_to_id']);
    }

    $delivery_carrier = '';

    foreach ((is_array($state) && isset($state['parcels'])) ? $state['parcels'] : array() as $parcel) {
        if ((string) $parcel['carrier'] !== '') {
            $delivery_carrier = (string) $parcel['carrier'];
            break;
        }
    }

    $previous = db_items(
        "SELECT carrier, carrier_code
        FROM order_returns
        WHERE
            (id != '" . (int) $return['id'] . "')
            AND (decided_at > 0)
            AND (status != 'rejected')
            AND (carrier != '')
        ORDER BY decided_at DESC, id DESC
        LIMIT 20");

    return pg_return_approval_defaults(
        $delivery_carrier,
        function_exists('pg_shipping_default_carrier') ? pg_shipping_default_carrier() : '',
        $previous);
}

/**
 * What staff may do to a return from its screen, in the order the buttons
 * come: the moves of pg_return_moves() except the refund, which belongs with
 * the payment that pays it.
 *
 * @param string $status
 * @return string[] target statuses
 */
function pg_return_staff_actions($status)
{
    return array_values(array_diff(pg_return_next_statuses($status, 'staff'), array('refunded')));
}

/**
 * The fields of a return the audit trail compares: what a move changes,
 * without the time stamps (the trail keeps its own time).
 *
 * @param array|null $row a row of order_returns
 * @return array
 */
function pg_return_audit_fields($row)
{
    $fields = array();

    foreach (array('status', 'decision_note', 'carrier', 'carrier_code', 'tracking_number') as $field) {
        $fields[$field] = is_array($row) && isset($row[$field]) ? (string) $row[$field] : '';
    }

    return $fields;
}

/**
 * A move made by staff, with what follows it: the audit trail line, the
 * activity log line and, when the request was approved or rejected, the
 * customer's mail. A rejection needs a note: the customer is told why.
 *
 * The caller has checked the person's commerce rights.
 *
 * @param int    $return_id
 * @param string $to
 * @param array  $options note, carrier, carrier_code, tracking_number (as
 *                        pg_return_move() takes them)
 * @param int    $user_id the person making the move
 * @return array ok, error, return (the row as it is now), mailed
 */
function pg_return_staff_move($return_id, $to, $options = array(), $user_id = 0)
{
    $options = is_array($options) ? $options : array();
    $to = pg_return_status($to);

    if (($to === 'rejected') && (pg_return_text(isset($options['note']) ? $options['note'] : '', PG_RETURN_NOTE_MAX) === '')) {
        return array('ok' => false, 'error' => lang('Tell the customer why the return is rejected.'), 'return' => null, 'mailed' => false);
    }

    $before = pg_returns_ready() ? db_item("SELECT * FROM order_returns WHERE id = '" . (int) $return_id . "'") : null;

    $moved = pg_return_move($return_id, $to, array_merge($options, array('by' => 'staff', 'user_id' => (int) $user_id)));

    if (!$moved['ok']) {
        return array('ok' => false, 'error' => $moved['error'], 'return' => null, 'mailed' => false);
    }

    $after = $moved['return'];
    $order_number = (string) db_value("SELECT order_number FROM orders WHERE id = '" . (int) $after['order_id'] . "'");

    if ($order_number === '') {
        $order_number = (string) (int) $after['order_id'];
    }

    pg_audit('update', 'order_return', (int) $after['id'], pg_return_audit_fields($before), pg_return_audit_fields($after),
        lang(array('string' => 'Return {var:1} of order {var:2}', 'vars' => array((int) $after['id'], $order_number))));

    $vars = array((int) $after['id'], $order_number);

    switch ($to) {
        case 'approved':
            log_activity(lang(array('string' => 'Return request {var:1} of order {var:2} was approved.', 'vars' => $vars)));
            break;
        case 'rejected':
            log_activity(lang(array('string' => 'Return request {var:1} of order {var:2} was rejected.', 'vars' => $vars)));
            break;
        case 'shipped':
            log_activity(lang(array('string' => 'Return request {var:1} of order {var:2} was recorded as sent back.', 'vars' => $vars)));
            break;
        case 'received':
            log_activity(lang(array('string' => 'The goods of return request {var:1} of order {var:2} were received.', 'vars' => $vars)));
            break;
        case 'closed':
            log_activity(lang(array('string' => 'Return request {var:1} of order {var:2} was closed.', 'vars' => $vars)));
            break;
    }

    // The move stands whatever happens to the mail.
    $mailed = in_array($to, array('approved', 'rejected'), true) ? pg_return_send_decision_mail($after['id']) : false;

    return array('ok' => true, 'error' => '', 'return' => $after, 'mailed' => $mailed);
}

/**
 * Approves several requests at once, each with the carrier and code its own
 * screen would offer (pg_return_suggested_approval()) and no note. A return
 * that is no longer a request is left as it is.
 *
 * @param int[] $return_ids
 * @param int   $user_id
 * @return array approved, mailed, skipped (counts), errors (list of text)
 */
function pg_returns_bulk_approve($return_ids, $user_id = 0)
{
    $result = array('approved' => 0, 'mailed' => 0, 'skipped' => 0, 'errors' => array());
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $return_ids))));

    foreach ($ids as $id) {
        $return = pg_returns_ready() ? db_item("SELECT * FROM order_returns WHERE id = '" . $id . "'") : null;

        if (!$return || ((string) $return['status'] !== 'requested')) {
            $result['skipped']++;
            continue;
        }

        $moved = pg_return_staff_move($id, 'approved', pg_return_suggested_approval($return), $user_id);

        if ($moved['ok']) {
            $result['approved']++;
            $result['mailed'] += $moved['mailed'] ? 1 : 0;
        } else {
            $result['errors'][] = lang(array('string' => 'Return request #{var:1}: {var:2}', 'vars' => array($id, $moved['error'])));
        }
    }

    return $result;
}

/**
 * The body of the mail that tells a customer what the shop decided.
 *
 * @param array $data status (approved or rejected), customer, order_number,
 *                    lines (name, quantity), carrier (its name), carrier_code,
 *                    decision_note, can_mark_sent (the customer may say on
 *                    the order page that the parcel left), from_name
 * @return string HTML
 */
function pg_return_decision_mail_body($data)
{
    $approved = (isset($data['status']) && ($data['status'] === 'approved'));
    $customer = isset($data['customer']) ? (string) $data['customer'] : '';
    $order_number = isset($data['order_number']) ? (string) $data['order_number'] : '';
    $carrier = isset($data['carrier']) ? (string) $data['carrier'] : '';
    $code = isset($data['carrier_code']) ? (string) $data['carrier_code'] : '';
    $note = isset($data['decision_note']) ? (string) $data['decision_note'] : '';
    $items = '';

    foreach ((array) (isset($data['lines']) ? $data['lines'] : array()) as $line) {
        $items .= '<li>' . h((string) $line['name']) . ' × ' . (int) $line['quantity'] . '</li>';
    }

    $paragraph = function ($html) {
        return '<p style="margin:0 0 12px;">' . $html . '</p>';
    };

    if ($approved) {
        $title = lang('Your return was approved');
        $intro = lang(array('string' => 'Your request to return items from order #{var:1} was approved.', 'vars' => array($order_number)));

        if (($carrier !== '') && ($code !== '')) {
            $how = h(lang(array('string' => 'Send the parcel with {var:1} and give them this return code:', 'vars' => array($carrier)))) . ' <strong>' . h($code) . '</strong>';
        } elseif ($carrier !== '') {
            $how = h(lang(array('string' => 'Send the parcel with {var:1}.', 'vars' => array($carrier))));
        } elseif ($code !== '') {
            $how = h(lang('Give the carrier this return code:')) . ' <strong>' . h($code) . '</strong>';
        } else {
            $how = h(lang('You can send the goods back now.'));
        }
    } else {
        $title = lang('About your return request');
        $intro = lang(array('string' => 'We could not accept your request to return items from order #{var:1}.', 'vars' => array($order_number)));
        $how = '';
    }

    return '<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;background:#f7f7f7;padding:20px;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;padding:24px;border-radius:6px;border:1px solid #e5e5e5;">'
        . '<h2 style="margin:0 0 16px;font-size:18px;">' . h($title) . '</h2>'
        . $paragraph(h(lang('Hello')) . (($customer !== '') ? ' ' . h($customer) : '') . ',')
        . $paragraph(h($intro))
        . (($items !== '') ? '<ul style="margin:0 0 12px;padding-left:20px;">' . $items . '</ul>' : '')
        . (($note !== '') ? $paragraph('<strong>' . h(lang('Note from the shop')) . ':</strong> ' . nl2br(h($note))) : '')
        . (($how !== '') ? '<p style="margin:0 0 12px;padding:12px;background:#eef6fb;border-radius:4px;">' . $how . '</p>' : '')
        . (($approved && !empty($data['can_mark_sent'])) ? $paragraph(h(lang('Once the parcel is on its way, you can tell us so on your order page.'))) : '')
        . (!$approved ? $paragraph(h(lang('Please contact us if you have any questions.'))) : '')
        . '<p style="margin:16px 0 0;color:#666;font-size:12px;">' . h(isset($data['from_name']) ? (string) $data['from_name'] : '') . '</p>'
        . '</div></body></html>';
}

/**
 * Tells the customer what the shop decided about a request, at the order's
 * billing address, through the mail queue when the general job runs.
 *
 * @param int $return_id
 * @return bool sent or queued; false when the return is neither approved nor
 *              rejected, the order has no address or the site has no sender
 */
function pg_return_send_decision_mail($return_id)
{
    $context = pg_return_mail_context($return_id);

    if ($context === null) {
        return false;
    }

    $return = $context['return'];
    $status = (string) $return['status'];

    if (!in_array($status, array('approved', 'rejected'), true)) {
        return false;
    }

    $subject = ($status === 'approved')
        ? lang(array('string' => 'Your return request for order #{var:1} was approved', 'vars' => array($context['order_number'])))
        : lang(array('string' => 'About your return request for order #{var:1}', 'vars' => array($context['order_number'])));

    return (bool) email(array(
        'to'                 => $context['to'],
        'to_name'            => $context['customer'],
        'from_name'          => $context['from_name'],
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => $subject,
        'format'             => 'html',
        'body'               => pg_return_decision_mail_body(array(
            'status'        => $status,
            'customer'      => $context['customer'],
            'order_number'  => $context['order_number'],
            'lines'         => $context['lines'],
            'carrier'       => pg_return_carrier_label((string) $return['carrier']),
            'carrier_code'  => (string) $return['carrier_code'],
            'decision_note' => (string) $return['decision_note'],
            'can_mark_sent' => pg_returns_customer_enabled(),
            'from_name'     => $context['from_name'],
        )),
        'type'               => 'system',
        'queue'              => true,
    ));
}

/**
 * The user names of some people, by id.
 *
 * @param int[] $user_ids
 * @return array user id => name
 */
function pg_return_user_names($user_ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $user_ids))));
    $names = array();

    if (empty($ids)) {
        return $names;
    }

    foreach (db_items("SELECT user_id, user_username FROM user WHERE user_id IN (" . implode(',', $ids) . ")") as $row) {
        $names[(int) $row['user_id']] = (string) $row['user_username'];
    }

    return $names;
}

/**
 * The returns of an order as the panel's order screen lists them: each with
 * its status, when it was asked and how many items, linked to its screen.
 *
 * @param array $rows each: id, status, requested_at, items (pg_returns_of_order())
 * @return string HTML, or '' when the order has none
 */
function pg_returns_panel_card_html($rows)
{
    if (!is_array($rows) || empty($rows)) {
        return '';
    }

    $lines = '';

    foreach ($rows as $row) {
        $quantity = 0;

        foreach ((array) (isset($row['items']) ? $row['items'] : array()) as $item) {
            $quantity += (int) $item['quantity'];
        }

        $status = (string) $row['status'];

        $lines .= '<div class="row align-items-center mb-1">
                        <span class="col"><a class="link-secondary" href="view_return.php?id=' . (int) $row['id'] . '">' . h(lang(array('string' => 'Return request #{var:1}', 'vars' => array((int) $row['id'])))) . '</a>'
                        . '<span class="d-block small text-muted">' . h(pg_return_time_text($row['requested_at'])) . ' · ' . h(lang(array('string' => '{var:1} item(s)', 'vars' => array($quantity)))) . '</span></span>
                        <span class="col-auto text-end"><span class="' . h(pg_return_badge_class($status)) . '">' . h(pg_return_status_label($status)) . '</span></span>
                    </div>';
    }

    return '<div class="card mt-3">
                <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Returns')) . '</span>
                    <a class="btn btn-sm btn-outline-secondary" href="view_returns.php?status=all">' . h(lang('All Returns')) . '</a>
                </div>
                <div class="card-body">
                    ' . $lines . '
                </div>
            </div>';
}

/**
 * The Returns card of the panel's order screen (view_order.php).
 *
 * @param int $order_id
 * @return string HTML, or '' when the order has no returns
 */
function pg_returns_panel_card($order_id)
{
    return pg_returns_panel_card_html(pg_returns_of_order($order_id));
}
