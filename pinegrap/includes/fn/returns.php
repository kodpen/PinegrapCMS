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
 * Days before a refund falls due that staff are reminded of it.
 */
define('PG_RETURN_REMIND_DAYS', 3);

/**
 * Seconds within which the same request sent again is taken as the first one
 * (pg_return_repeat_of()): long enough for a mail sent inside the request to
 * keep the first answer waiting.
 */
define('PG_RETURN_REPEAT_SECONDS', 60);

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
 * zero or an empty box leaves it out. At least one line must remain, each one
 * of the delivery's, none above what is left on it.
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
        } elseif (is_string($quantity) && (trim($quantity) === '')) {
            // A number box the customer cleared arrives empty: nothing asked
            // for on that line, like 0.
            $count = 0;
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

/**
 * The request a new one repeats, if any: one still waiting for an answer,
 * made at most PG_RETURN_REPEAT_SECONDS before, with the same reason, note
 * and lines. That is the same form sent twice (a double click, a reload of
 * the answer page), not a second return.
 *
 * Quantities are read the way pg_return_check_lines() reads them; a value it
 * would not take is left out of the comparison, as are zeros.
 *
 * @param array  $recent    requests of the same order, delivery and person:
 *                          id, reason, customer_note, requested_at, items
 *                          (list of order_item_id, quantity)
 * @param string $reason    the new request's reason, as stored
 * @param string $note      its note, as stored
 * @param mixed  $requested order item id => quantity, as sent
 * @param int    $now       unix time of the new request
 * @return int the id of the request it repeats, or 0
 */
function pg_return_repeat_of($recent, $reason, $note, $requested, $now)
{
    if (!is_array($requested) || !is_array($recent)) {
        return 0;
    }

    $wanted = array();

    foreach ($requested as $id => $quantity) {
        if (is_int($quantity)) {
            $count = $quantity;
        } elseif (is_string($quantity) && (preg_match('/^\s*\d{1,9}\s*$/', $quantity) === 1)) {
            $count = (int) $quantity;
        } else {
            continue;
        }

        if ($count > 0) {
            $wanted[(int) $id] = $count;
        }
    }

    if (empty($wanted)) {
        return 0;
    }

    ksort($wanted);

    foreach ($recent as $row) {
        $age = (int) $now - (int) $row['requested_at'];

        if (($age < 0) || ($age > PG_RETURN_REPEAT_SECONDS)) {
            continue;
        }

        if (((string) $row['reason'] !== (string) $reason) || ((string) $row['customer_note'] !== (string) $note)) {
            continue;
        }

        $held = array();

        foreach ((isset($row['items']) ? (array) $row['items'] : array()) as $item) {
            $item_id = (int) $item['order_item_id'];
            $held[$item_id] = (isset($held[$item_id]) ? $held[$item_id] : 0) + (int) $item['quantity'];
        }

        ksort($held);

        if ($held === $wanted) {
            return (int) $row['id'];
        }
    }

    return 0;
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
 * @return array ok, error, id, repeat (true when the request was the same
 *               form sent again and id is the request it repeats)
 */
function pg_return_create($order_id, $ship_to_id, $quantities, $reason, $note, $options = array())
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error, 'id' => 0, 'repeat' => false);
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

    // The same form sent again while the first one was being saved answers
    // with that request, before the quantity check: the first one may have
    // taken every unit, and the page the customer ends up seeing is the
    // answer to the second.
    $recent = db_items(
        "SELECT id, reason, customer_note, requested_at
        FROM order_returns
        WHERE
            order_id = '" . $order_id . "'
            AND ship_to_id = '" . $ship_to_id . "'
            AND status = 'requested'
            AND source = '" . e($source) . "'
            AND requested_by = '" . $user_id . "'
            AND requested_at >= '" . ($now - PG_RETURN_REPEAT_SECONDS) . "'
        ORDER BY id DESC
        LIMIT 10");

    if (!empty($recent)) {
        $items = pg_return_items_of(array_column($recent, 'id'));

        foreach ($recent as $index => $row) {
            $recent[$index]['items'] = isset($items[(int) $row['id']]) ? $items[(int) $row['id']] : array();
        }

        $repeat = pg_return_repeat_of($recent, $reason, $note, $quantities, $now);

        if ($repeat > 0) {
            db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

            return array('ok' => true, 'error' => '', 'id' => $repeat, 'repeat' => true);
        }
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

    return array('ok' => true, 'error' => '', 'id' => $return_id, 'repeat' => false);
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

    // From here on a refund falls due, and the daily reminder has to run.
    if (in_array($to, array('shipped', 'received'), true)) {
        pg_return_reminder_job_switch_on();
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
 * that a customer asked for a return or sent the goods back, or that a
 * refund falls due. The row carries the order number as its title, what
 * happened as order_total and the return as form_id; the sentence around them
 * is built by pg_notification_display().
 *
 * @param int    $return_id
 * @param string $event requested, shipped, refund_due or refund_overdue
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
        'order_total' => in_array($event, array('shipped', 'refund_due', 'refund_overdue'), true) ? $event : 'requested',
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
 * come: the moves of pg_return_moves() except the refund, which has its own
 * form because it moves money first (pg_return_refund()).
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

/* ---------------------------------------------------------------------------
   The refund of a return whose goods are back, and the reminders of refunds
   falling due
   --------------------------------------------------------------------------- */

/**
 * An amount of money as typed ("120", "120.5", "120,50") in minor units.
 *
 * @param mixed $value
 * @return int -1 when the value is not an amount
 */
function pg_return_amount_cents($value)
{
    if (is_int($value)) {
        return ($value >= 0) ? $value * 100 : -1;
    }

    if (!is_string($value) || (preg_match('/^\s*(\d{1,9})(?:[.,](\d{1,2}))?\s*$/', $value, $parts) !== 1)) {
        return -1;
    }

    return ((int) $parts[1] * 100) + (isset($parts[2]) ? (int) str_pad($parts[2], 2, '0') : 0);
}

/**
 * What the goods of a return cost the customer, from the order's own lines:
 * each unit's price and its share of the line's tax, less the line's share of
 * the order discount with the tax taken again on what is left, the way the
 * ERP spreads the discount (erp_order_lines()). Shipping is not included.
 *
 * @param array $lines      order item id => price (a unit, minor units),
 *                          quantity, tax_total (the line's)
 * @param array $quantities order item id => how many go back
 * @param int   $discount   orders.discount
 * @return int minor units
 */
function pg_return_refund_estimate($lines, $quantities, $discount)
{
    $lines = is_array($lines) ? $lines : array();
    $discount = max(0, (int) $discount);
    $subtotal = 0;

    foreach ($lines as $line) {
        $subtotal += (int) $line['price'] * max(0, (int) $line['quantity']);
    }

    $amount = 0;

    foreach ((array) $quantities as $id => $count) {
        if (!isset($lines[$id])) {
            continue;
        }

        $line = $lines[$id];
        $sold = max(1, (int) $line['quantity']);
        $count = min(max(0, (int) $count), $sold);

        if ($count === 0) {
            continue;
        }

        $base = (int) $line['price'] * $count;
        $tax = (int) round((int) $line['tax_total'] * $count / $sold);
        $share = (($discount > 0) && ($subtotal > 0)) ? min($base, (int) round($discount * $base / $subtotal)) : 0;

        if (($share > 0) && ($base > 0)) {
            $tax = (int) round($tax * ($base - $share) / $base);
        }

        $amount += $base - $share + $tax;
    }

    return $amount;
}

/**
 * Which line of the ERP's refund lines (erp_order_refund_lines()) each order
 * line is.
 *
 * On the order's own lines a goods line carries its order line's id. An
 * invoice made from the order lists the goods first and in the order's line
 * order (erp_order_lines()), so its n-th line is the n-th order line; each
 * pair is checked by product, and an invoice that does not line up (made or
 * changed by hand) gives no map.
 *
 * A line the order did not charge (a recurring line starting after the order
 * day, erp_order_charged_items()) is not among the refund lines; the source
 * names it in left_out and it gets no entry, so the lines that were charged
 * still map. On the order's own lines it is always skipped. On an invoice it
 * is skipped only when the invoice line at its position is not its product:
 * an invoice issued while such lines were still put on it carries the line
 * and keeps mapping it. The invoice source works left_out out from today's
 * order_items rows and gateway setting, not from what the invoice was built
 * from.
 *
 * @param array $items  order item id => product id, in the order's line order
 *                      (lines kept for later left out)
 * @param array $source erp_order_refund_lines(): source, lines (id, product_id),
 *                      left_out (order item ids)
 * @return array|null order item id => refund line id
 */
function pg_return_erp_line_map($items, $source)
{
    if (!is_array($items) || !is_array($source) || !isset($source['lines']) || !is_array($source['lines'])) {
        return null;
    }

    $lines = array_values($source['lines']);
    $map = array();
    $left_out = array();
    $left_out_ids = (isset($source['left_out']) && is_array($source['left_out'])) ? $source['left_out'] : array();

    foreach ($left_out_ids as $left_out_id) {
        $left_out[(int) $left_out_id] = true;
    }

    if ((isset($source['source']) ? $source['source'] : '') === 'order') {
        $ids = array();

        foreach ($lines as $line) {
            $ids[(int) $line['id']] = true;
        }

        foreach ($items as $item_id => $product_id) {
            if (isset($left_out[(int) $item_id])) {
                continue;
            }

            if (!isset($ids[(int) $item_id])) {
                return null;
            }

            $map[(int) $item_id] = (int) $item_id;
        }

        return $map;
    }

    $position = 0;

    foreach ($items as $item_id => $product_id) {
        $matches = isset($lines[$position]) && ((int) $lines[$position]['product_id'] === (int) $product_id);

        // Not on this invoice: the next line is the next charged item's.
        if (!$matches && isset($left_out[(int) $item_id])) {
            continue;
        }

        if (!$matches) {
            return null;
        }

        $map[(int) $item_id] = (int) $lines[$position]['id'];
        $position++;
    }

    return $map;
}

/**
 * Money as plain text in the site's currency: for logs and mails.
 *
 * @param int $cents
 * @return string
 */
function pg_return_money_text($cents)
{
    $symbol = defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '';

    return html_entity_decode(strip_tags(pg_format_money((int) $cents / 100, $symbol)), ENT_QUOTES, 'UTF-8');
}

/**
 * Whether a person goes on to the ERP return invoice after a refund: the ERP
 * must be on and they must be allowed to use it (role below 3 or manage_erp).
 * The redirect after the refund and the note under the refund card both ask
 * this, so the screen promises the invoice only to whoever will see it.
 *
 * @param array     $user   validate_user() row (role, manage_erp)
 * @param bool|null $erp_on null reads ERP_ENABLED
 * @return bool
 */
function pg_return_erp_hand_off($user, $erp_on = null)
{
    if ($erp_on === null) {
        $erp_on = defined('ERP_ENABLED') && ERP_ENABLED;
    }

    if (!$erp_on || !is_array($user)) {
        return false;
    }

    return ((int) (isset($user['role']) ? $user['role'] : 99) < 3) || !empty($user['manage_erp']);
}

/**
 * What a refund of a return would be: what is left to give back on the order,
 * whether the payment's driver can give it back, and the amount the goods
 * come to. With the ERP on, the amount is the ERP's own figure for the same
 * lines (erp_order_refund_amount()), and an issued invoice names the lines a
 * return invoice will carry.
 *
 * @param array $return pg_return_load()
 * @return array|null order (id, order_number, status, payment_method),
 *                    refunded, remaining, driver (key, '' when the money
 *                    cannot be sent back from here), driver_label, estimate,
 *                    suggested, erp (invoice_id, invoice_number, quantities);
 *                    null when the order is gone
 */
function pg_return_refund_plan($return)
{
    $order = db_item("SELECT * FROM orders WHERE id = '" . (int) $return['order_id'] . "'");

    if (!$order) {
        return null;
    }

    $status = (string) $order['status'];
    $refunded = max(0, (int) (isset($order['refunded_amount']) ? $order['refunded_amount'] : 0));
    $remaining = max(0, (int) $order['total'] - $refunded);
    $driver = function_exists('pg_payment_refund_driver') ? pg_payment_refund_driver((string) $order['payment_method']) : '';

    // The same conditions the order screen offers its refund on.
    if (($driver !== '') && ((trim((string) $order['transaction_id']) === '') || in_array($status, array('cancelled', 'incomplete'), true) || ($remaining <= 0))) {
        $driver = '';
    }

    $definition = ($driver !== '') ? pg_payment_driver($driver) : null;

    $quantities = array();

    foreach ($return['items'] as $item) {
        $quantities[(int) $item['order_item_id']] = (isset($quantities[(int) $item['order_item_id']]) ? $quantities[(int) $item['order_item_id']] : 0) + (int) $item['quantity'];
    }

    $rows = db_items(
        "SELECT id, product_id, price, quantity, tax_total
        FROM order_items
        WHERE (order_id = '" . (int) $order['id'] . "') AND (saved_for_later = 0)
        ORDER BY id");

    $lines = array();
    $products = array();

    foreach ($rows as $row) {
        $lines[(int) $row['id']] = $row;
        $products[(int) $row['id']] = (int) $row['product_id'];
    }

    $estimate = pg_return_refund_estimate($lines, $quantities, isset($order['discount']) ? (int) $order['discount'] : 0);
    $erp = array('invoice_id' => 0, 'invoice_number' => '', 'quantities' => array());

    if (defined('ERP_ENABLED') && ERP_ENABLED) {
        require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
        require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_refund.php');

        $source = erp_order_refund_lines((int) $order['id']);
        $map = ($source !== null) ? pg_return_erp_line_map($products, $source) : null;
        $chosen = array();

        foreach ($quantities as $item_id => $count) {
            if (($map === null) || !isset($map[$item_id])) {
                $chosen = array();
                break;
            }

            $chosen[$map[$item_id]] = $count;
        }

        if (!empty($chosen)) {
            $built = erp_order_refund_amount($source, $chosen);

            if ($built['error'] === '') {
                $estimate = (int) $built['amount'];

                if ($source['source'] === 'invoice') {
                    $erp = array(
                        'invoice_id'     => (int) $source['invoice_id'],
                        'invoice_number' => (string) $source['invoice_number'],
                        'quantities'     => $built['quantities'],
                    );
                }
            }
        }
    }

    return array(
        'order'        => array(
            'id'             => (int) $order['id'],
            'order_number'   => ((string) $order['order_number'] !== '') ? (string) $order['order_number'] : (string) (int) $order['id'],
            'status'         => $status,
            'payment_method' => (string) $order['payment_method'],
        ),
        'refunded'     => $refunded,
        'remaining'    => $remaining,
        'driver'       => $driver,
        'driver_label' => is_array($definition) ? (string) $definition['label'] : '',
        'estimate'     => $estimate,
        'suggested'    => min($estimate, $remaining),
        'erp'          => $erp,
    );
}

/**
 * Gives the money of a received return back and marks it refunded.
 *
 * Through the payment's driver ('driver': the one that took the order's
 * payment, pg_payment_refund_driver()), or as a record of a refund made
 * another way ('manual': a bank transfer, cash; 0 when the money already went
 * back from the order screen). The return is marked refunded before the
 * driver is asked, under the order's lock, so a second click or a second
 * person cannot pay it twice; a refusal puts it back. An unclear answer (no
 * answer in time, one that cannot be read: the money may have gone back)
 * does not: the return stays refunded and the amount is recorded as refunded
 * but unconfirmed, the attempt is logged and the operator is told to check
 * the service's own screen - reopening it sent a second refund on the next
 * click. What was paid back is added to the order's refunded amount with an
 * order_refunds row; the order's status and totals stay as they are (a
 * return is not a cancellation).
 *
 * The caller has checked the person's commerce rights.
 *
 * @param int   $return_id
 * @param array $options amount (minor units), method (driver or manual),
 *                       reference (of a manual refund)
 * @param int   $user_id
 * @return array ok, error, amount, method, mailed, erp (pg_return_refund_plan()'s),
 *               unclear (the driver's answer did not say; ok is false)
 */
function pg_return_refund($return_id, $options, $user_id = 0)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'amount' => 0, 'method' => '', 'mailed' => false, 'erp' => null, 'unclear' => false);
    };

    if (!pg_returns_ready() || !pg_schema_has('order_refunds', 'notes') || !pg_schema_has('orders', 'refunded_amount')) {
        return $fail(lang('Returns are not installed yet: the database has to be updated first.'));
    }

    $return = pg_return_load($return_id);

    if (!$return) {
        return $fail(lang('The return could not be found.'));
    }

    $options = is_array($options) ? $options : array();
    $method = (isset($options['method']) && ($options['method'] === 'driver')) ? 'driver' : 'manual';
    $amount = isset($options['amount']) ? (int) $options['amount'] : -1;
    $lock = pg_return_lock_name($return['order_id']);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 5)") !== 1) {
        return $fail(lang('Another return is being saved for this order. Try again in a moment.'));
    }

    $release = function () use ($lock) {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");
    };

    // Read again under the lock: what the page showed may be gone.
    $return = pg_return_load($return['id']);

    if (!$return || ((string) $return['status'] !== 'received')) {
        $release();

        return $fail(lang('Only a return whose goods are back can be refunded.'));
    }

    $plan = pg_return_refund_plan($return);

    if ($plan === null) {
        $release();

        return $fail(lang('The order could not be found.'));
    }

    if (($method === 'driver') && ($plan['driver'] === '')) {
        $release();

        return $fail(lang('The payment of this order cannot be refunded from here. Refund it another way and record it.'));
    }

    if (($amount < 0) || (($method === 'driver') && ($amount === 0))) {
        $release();

        return $fail(lang('The refund amount must be greater than 0.'));
    }

    if ($amount > $plan['remaining']) {
        $release();

        return $fail(lang('The refund amount exceeds the remaining refundable amount.'));
    }

    $before = db_item("SELECT * FROM order_returns WHERE id = '" . (int) $return['id'] . "'");
    $moved = pg_return_move($return['id'], 'refunded', array('by' => 'staff', 'user_id' => (int) $user_id));

    if (!$moved['ok']) {
        $release();

        return $fail($moved['error']);
    }

    $reference = pg_return_text(isset($options['reference']) ? $options['reference'] : '', 255);
    $unclear = null;

    if ($method === 'driver') {
        // A driver refund keeps only the service's own id of the refund: the
        // form's reference belongs to a refund made another way.
        $reference = '';

        $order_row = db_item("SELECT * FROM orders WHERE id = '" . (int) $return['order_id'] . "'");
        $order_lines = db_items(
            "SELECT * FROM order_items
            WHERE (order_id = '" . (int) $return['order_id'] . "') AND (saved_for_later = 0)
            ORDER BY id ASC");

        $answer = pg_payment_call($plan['driver'], 'refund', array($order_row, $amount, $order_lines));

        foreach ($answer['log'] as $line) {
            log_activity($line);
        }

        list($service_words, $service_detail) = pg_return_refund_service_words($answer);

        if (!$answer['success'] && $answer['unclear']) {
            // The return stays refunded and the money is booked: a second
            // click must not send a second refund, and whatever the service
            // did, the operator finishes it on its own screen.
            $unclear = pg_return_refund_unclear_lines($return['id'], $plan['order']['order_number'], $amount, $plan['driver_label'], $service_words, $service_detail);

            log_activity($unclear['log']);
        } elseif (!$answer['success']) {
            db("UPDATE order_returns SET status = 'received', refunded_at = 0
                WHERE (id = '" . (int) $return['id'] . "') AND (status = 'refunded')");

            $release();

            log_activity(pg_return_refund_refused_line($return['id'], $plan['order']['order_number'], $amount, $plan['driver_label'], ($service_detail !== '') ? $service_detail : (string) $answer['error']));

            return $fail(((string) $answer['error'] !== '') ? (string) $answer['error'] : lang('The refund could not be made.'));
        } else {
            $reference = pg_return_text((string) $answer['refund_reference'], 255);
        }
    }

    if ($amount > 0) {
        db("UPDATE orders SET refunded_amount = COALESCE(refunded_amount, 0) + " . $amount . " WHERE id = '" . (int) $return['order_id'] . "'");

        db("INSERT INTO order_refunds (order_id, amount_cents, refund_type, transaction_id, notes, created_at)
            VALUES (
                '" . (int) $return['order_id'] . "',
                '" . $amount . "',
                '" . (($method === 'driver') ? 'return' : 'return_manual') . "',
                '" . e($reference) . "',
                '" . e('Return request #' . (int) $return['id'] . (($unclear !== null) ? ' (unconfirmed: no clear answer from ' . $plan['driver_label'] . ')' : '')) . "',
                NOW())");
    }

    $release();

    $after = pg_return_audit_fields($moved['return']);
    $after['refund_amount'] = (string) $amount;
    $after['refund_method'] = $method;
    $after['refund_reference'] = $reference;

    $old = pg_return_audit_fields($before);
    $old['refund_amount'] = '';
    $old['refund_method'] = '';
    $old['refund_reference'] = '';

    pg_audit('update', 'order_return', (int) $return['id'], $old, $after,
        lang(array('string' => 'Return {var:1} of order {var:2}', 'vars' => array((int) $return['id'], $plan['order']['order_number']))));

    if ($unclear !== null) {
        // Nothing is mailed and the ERP hand-off is not offered: whether the
        // money went back is not known; staff confirm it on the service's
        // screen, then tell the customer and issue the return invoice by hand.
        return array(
            'ok'      => false,
            'error'   => $unclear['error'],
            'amount'  => $amount,
            'method'  => $method,
            'mailed'  => false,
            'erp'     => null,
            'unclear' => true,
        );
    }

    $vars = array((int) $return['id'], $plan['order']['order_number'], pg_return_money_text($amount));

    log_activity(($method === 'driver')
        ? lang(array('string' => 'Return request {var:1} of order {var:2} was refunded: {var:3}.', 'vars' => $vars))
        : lang(array('string' => 'Return request {var:1} of order {var:2} was recorded as refunded another way: {var:3}.', 'vars' => $vars)));

    return array(
        'ok'     => true,
        'error'  => '',
        'amount' => $amount,
        'method' => $method,
        'mailed' => ($amount > 0) ? pg_return_send_refund_mail($return['id'], $amount, $method) : false,
        'erp'    => $plan['erp'],
        'unclear' => false,
    );
}

/**
 * What a failed refund answer says, split by who may read it. The screen
 * words are the service's own (raw.message) and are '' when the driver threw:
 * raw.exception set means raw.message is PHP's exception text, which can name
 * classes and server paths, so it goes to the activity log only. The log
 * detail is raw.message whatever it holds. Neither falls back to the answer's
 * error sentence, which the caller has already.
 *
 * @param array $answer a pg_payment_answer() shape
 * @return array screen words, log detail
 */
function pg_return_refund_service_words($answer)
{
    $raw = (is_array($answer) && isset($answer['raw']) && is_array($answer['raw'])) ? $answer['raw'] : array();
    $detail = (isset($raw['message']) && is_scalar($raw['message'])) ? (string) $raw['message'] : '';
    $words = (isset($raw['exception']) && ((string) $raw['exception'] !== '')) ? '' : $detail;

    return array($words, $detail);
}

/**
 * What an unclear refund answer of a return leaves: the activity log line
 * (the return, the order, the amount, the service and what came back) and the
 * sentence the operator reads.
 *
 * @param int         $return_id
 * @param string      $order_number
 * @param int         $amount     minor units asked back
 * @param string      $service    the driver's label
 * @param string      $message    the service's own words, for the screen
 * @param string|null $log_detail what came back, for the log (exception text
 *                                included); null: the same as $message
 * @return array log, error
 */
function pg_return_refund_unclear_lines($return_id, $order_number, $amount, $service, $message, $log_detail = null)
{
    $message = (string) $message;
    $log_detail = ($log_detail === null) ? $message : (string) $log_detail;

    $vars = array((int) $return_id, (string) $order_number, pg_return_money_text($amount), (string) $service);

    return array(
        'log'   => lang(array(
            'string' => 'Return request {var:1} of order {var:2}: {var:4} gave no clear answer to the refund of {var:3} ({var:5}). The return stays refunded and the amount was recorded as refunded. Check the refund on the {var:4} screen; if it is not there, make it there.',
            'vars'   => array_merge($vars, array(($log_detail !== '') ? $log_detail : lang('no answer'))))),
        'error' => lang(array(
            'string' => 'No clear answer came from {var:4} ({var:5}): the refund of {var:3} may have been made. The return stays refunded and the amount was recorded. Check the payment on the {var:4} screen before doing anything else; if the refund is not there, make it there. Do not send it again from here.',
            'vars'   => array_merge($vars, array(($message !== '') ? $message : lang('no answer'))))),
    );
}

/**
 * The activity log line of a refund the service refused for a return.
 *
 * @param int    $return_id
 * @param string $order_number
 * @param int    $amount  minor units asked back
 * @param string $service the driver's label
 * @param string $message the service's words
 * @return string
 */
function pg_return_refund_refused_line($return_id, $order_number, $amount, $service, $message)
{
    return lang(array(
        'string' => 'Return request {var:1} of order {var:2}: {var:4} refused the refund of {var:3} ({var:5}). The return is back to received.',
        'vars'   => array((int) $return_id, (string) $order_number, pg_return_money_text($amount), (string) $service, ((string) $message !== '') ? (string) $message : lang('no message'))));
}

/**
 * The body of the mail that tells a customer their money was given back.
 *
 * @param array $data customer, order_number, lines (name, quantity), amount
 *                    (plain text), by_payment (the money went back the way it
 *                    was paid), from_name
 * @return string HTML
 */
function pg_return_refund_mail_body($data)
{
    $customer = isset($data['customer']) ? (string) $data['customer'] : '';
    $items = '';

    foreach ((array) (isset($data['lines']) ? $data['lines'] : array()) as $line) {
        $items .= '<li>' . h((string) $line['name']) . ' × ' . (int) $line['quantity'] . '</li>';
    }

    $paragraph = function ($html) {
        return '<p style="margin:0 0 12px;">' . $html . '</p>';
    };

    return '<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;background:#f7f7f7;padding:20px;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;padding:24px;border-radius:6px;border:1px solid #e5e5e5;">'
        . '<h2 style="margin:0 0 16px;font-size:18px;">' . h(lang('Your refund was made')) . '</h2>'
        . $paragraph(h(lang('Hello')) . (($customer !== '') ? ' ' . h($customer) : '') . ',')
        . $paragraph(h(lang(array(
            'string' => 'We received the items you returned from order #{var:1} and refunded {var:2}.',
            'vars'   => array(isset($data['order_number']) ? (string) $data['order_number'] : '', isset($data['amount']) ? (string) $data['amount'] : '')))))
        . (($items !== '') ? '<ul style="margin:0 0 12px;padding-left:20px;">' . $items . '</ul>' : '')
        . (!empty($data['by_payment']) ? $paragraph(h(lang('The money goes back the way you paid. Your bank may take a few days to show it.'))) : '')
        . '<p style="margin:16px 0 0;color:#666;font-size:12px;">' . h(isset($data['from_name']) ? (string) $data['from_name'] : '') . '</p>'
        . '</div></body></html>';
}

/**
 * Tells the customer their money was given back, at the order's billing
 * address, through the mail queue when the general job runs.
 *
 * @param int    $return_id
 * @param int    $amount minor units
 * @param string $method driver or manual
 * @return bool sent or queued
 */
function pg_return_send_refund_mail($return_id, $amount, $method)
{
    $context = pg_return_mail_context($return_id);

    if ($context === null) {
        return false;
    }

    return (bool) email(array(
        'to'                 => $context['to'],
        'to_name'            => $context['customer'],
        'from_name'          => $context['from_name'],
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang(array('string' => 'Your refund for order #{var:1}', 'vars' => array($context['order_number']))),
        'format'             => 'html',
        'body'               => pg_return_refund_mail_body(array(
            'customer'     => $context['customer'],
            'order_number' => $context['order_number'],
            'lines'        => $context['lines'],
            'amount'       => pg_return_money_text($amount),
            'by_payment'   => ($method === 'driver'),
            'from_name'    => $context['from_name'],
        )),
        'type'               => 'system',
        'queue'              => true,
    ));
}

/**
 * Where a refund stands against its deadline: 'due' from PG_RETURN_REMIND_DAYS
 * days before it up to the day itself, 'overdue' after it, '' otherwise.
 *
 * @param string $deadline Y-m-d (pg_return_refund_due())
 * @param string $today    Y-m-d
 * @return string
 */
function pg_return_reminder_stage($deadline, $today)
{
    $deadline = pg_return_date($deadline);
    $today = pg_return_date($today);

    if (($deadline === '') || ($today === '')) {
        return '';
    }

    if (strcmp($today, $deadline) > 0) {
        return 'overdue';
    }

    return (strcmp($deadline, pg_return_add_days($today, PG_RETURN_REMIND_DAYS)) <= 0) ? 'due' : '';
}

/**
 * The order_returns column that records when a reminder stage was given.
 *
 * @param string $stage pg_return_reminder_stage(): 'due' or 'overdue'
 * @return string column name, '' for anything else
 */
function pg_return_reminder_stamp_column($stage)
{
    if ($stage === 'due') {
        return 'refund_due_notified_at';
    }

    if ($stage === 'overdue') {
        return 'refund_overdue_notified_at';
    }

    return '';
}

/**
 * Rings the staff's bell for each return whose refund is due within
 * PG_RETURN_REMIND_DAYS days, and again once it is overdue: once per return
 * and stage. The stage is stamped on the return itself (step 11.0.25) before
 * the bell rings, and only the run whose UPDATE claimed the row rings it: a
 * notification row that staff delete from the bell ("Delete all") is gone
 * for everyone and cannot be the memory of what was already said. A bell
 * that could not be written gives the stamp back, so the next run tries
 * again.
 *
 * @param string $today Y-m-d, or '' for today
 * @return array due, overdue (how many were rung)
 */
function pg_return_remind_refunds($today = '')
{
    $result = array('due' => 0, 'overdue' => 0);

    if (!pg_returns_ready() || !pg_schema_has('order_returns', 'refund_overdue_notified_at')) {
        return $result;
    }

    if (pg_return_date($today) === '') {
        $today = date('Y-m-d');
    }

    foreach (db_items("SELECT * FROM order_returns WHERE status IN ('shipped', 'received')") as $row) {
        $stage = pg_return_reminder_stage(pg_return_refund_due($row), $today);
        $column = pg_return_reminder_stamp_column($stage);

        if (($column === '') || ((int) $row[$column] > 0)) {
            continue;
        }

        db("UPDATE order_returns SET " . $column . " = UNIX_TIMESTAMP()
            WHERE (id = '" . (int) $row['id'] . "') AND (" . $column . " = 0)");

        if (mysqli_affected_rows(db::$con) !== 1) {
            continue;
        }

        if (pg_return_notify_staff($row['id'], 'refund_' . $stage)) {
            $result[$stage]++;
        } else {
            db("UPDATE order_returns SET " . $column . " = 0 WHERE id = '" . (int) $row['id'] . "'");
        }
    }

    return $result;
}

/**
 * Adds the daily refund reminder to the jobs the general job runs, when the
 * general job is the one running them; a site that schedules each job on its
 * own keeps its own schedule. Called when a refund starts to fall due.
 *
 * @return bool the job is (now) in the list
 */
function pg_return_reminder_job_switch_on()
{
    if (!function_exists('pg_cron_dispatch_list') || !defined('JOB_DISPATCH_ENABLED') || !JOB_DISPATCH_ENABLED) {
        return false;
    }

    $list = pg_cron_dispatch_list();

    if (in_array('return_reminder_job', $list, true)) {
        return true;
    }

    $list[] = 'return_reminder_job';

    db("UPDATE config SET job_dispatch = '" . e(implode(',', $list)) . "'");

    return true;
}
