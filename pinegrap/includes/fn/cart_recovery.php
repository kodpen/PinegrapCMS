<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Recovered carts: the touches a left cart gets (a message sent or an offer
 * shown) in cart_recovery_touch, and which completed order a touch brought
 * back. Every write of that table goes through this file:
 * pg_cart_recovery_touch() when a touch goes out, pg_cart_recovery_restored()
 * when ?r=<reference code> restores the cart, pg_cart_recovery_completed()
 * from pg_order_complete(). pg_cart_recovery_outcome() is the rule, pure.
 *
 * A WhatsApp message that carries a single-use key is a touch of the
 * recipient's left cart (pg_cart_recovery_whatsapp_key_sent()); the key's
 * use on that cart marks it clicked and restored
 * (pg_cart_recovery_key_used()).
 *
 * A pop-up taken up on a page by a visitor whose session holds a cart is a
 * touch of that cart (pg_cart_recovery_popup_taken(), from page_view.php's
 * pop-up event branch).
 *
 * A site whose update has not added the table writes nothing here and
 * completes orders as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Seconds a cart waits before it counts as left (the abandoned-order mail's 6 hours). */
const PG_CART_RECOVERY_LEFT_AFTER = 21600;

/** Seconds a touch may come before the completion and still take it (7 days). */
const PG_CART_RECOVERY_WINDOW = 604800;

/** Seconds after its last change a cart is still a left cart a message may touch (30 days). */
const PG_CART_RECOVERY_LEFT_HORIZON = 2592000;

/**
 * Whether the table exists. Asked once a request.
 *
 * @return bool
 */
function pg_cart_recovery_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('cart_recovery_touch');
    }

    return $ready;
}

/**
 * A channel name the table accepts, '' for any other.
 *
 * @param string $channel
 * @return string email, whatsapp, sms or page
 */
function pg_cart_recovery_channel($channel)
{
    $channel = strtolower(trim((string) $channel));

    return in_array($channel, array('email', 'whatsapp', 'sms', 'page'), true) ? $channel : '';
}

/**
 * A source type the table accepts, '' for any other.
 *
 * @param string $source_type
 * @return string campaign, template or popup
 */
function pg_cart_recovery_source_type($source_type)
{
    $source_type = strtolower(trim((string) $source_type));

    return in_array($source_type, array('campaign', 'template', 'popup'), true) ? $source_type : '';
}

/**
 * The arguments pg_cart_recovery_touch() writes, pure: the names checked,
 * the ids made integers. null when a touch cannot be written (no order, an
 * unknown channel or source).
 *
 * @param int    $order_id
 * @param string $channel
 * @param string $source_type
 * @param int    $source_id
 * @return array|null order_id, channel, source_type, source_id
 */
function pg_cart_recovery_touch_args($order_id, $channel, $source_type, $source_id)
{
    $args = array(
        'order_id'    => (int) $order_id,
        'channel'     => pg_cart_recovery_channel($channel),
        'source_type' => pg_cart_recovery_source_type($source_type),
        'source_id'   => max(0, (int) $source_id),
    );

    if (($args['order_id'] < 1) || ($args['channel'] === '') || ($args['source_type'] === '')) {
        return null;
    }

    return $args;
}

/**
 * Whether an order is a left cart a message may touch now, pure: still
 * incomplete, last changed at least 6 hours ago (the abandoned-order mail's
 * rule) and at most 30 days ago.
 *
 * @param string $status        orders.status
 * @param int    $last_modified orders.last_modified_timestamp
 * @param int    $now
 * @return bool
 */
function pg_cart_recovery_is_left($status, $last_modified, $now)
{
    $last_modified = (int) $last_modified;
    $idle = (int) $now - $last_modified;

    return ((string) $status === 'incomplete') && ($last_modified > 0)
        && ($idle >= PG_CART_RECOVERY_LEFT_AFTER) && ($idle <= PG_CART_RECOVERY_LEFT_HORIZON);
}

/**
 * The rule, pure. A cart is left when it was started at least 6 hours before
 * the completion. The order's last touch sent before the completion takes it
 * when it was sent at most 7 days before; an older last touch takes nothing.
 * A left cart with no touch sent before the completion is counted apart
 * (untouched), never as recovered.
 *
 * @param int   $order_date   unix time the cart was started, read before the completion rewrites it
 * @param int   $completed_at unix time of the completion
 * @param array $touches      touch id => sent_at (unix time; 0 = not sent)
 * @return array{left: bool, touch_id: int, untouched: bool}
 */
function pg_cart_recovery_outcome($order_date, $completed_at, array $touches)
{
    $order_date = (int) $order_date;
    $completed_at = (int) $completed_at;

    $left = ($order_date > 0) && (($completed_at - $order_date) >= PG_CART_RECOVERY_LEFT_AFTER);

    $last_id = 0;
    $last_sent = 0;

    foreach ($touches as $id => $sent_at) {
        $id = (int) $id;
        $sent_at = (int) $sent_at;

        if (($sent_at < 1) || ($sent_at > $completed_at)) {
            continue;
        }

        // The later send wins; on the same second, the later row.
        if (($sent_at > $last_sent) || (($sent_at === $last_sent) && ($id > $last_id))) {
            $last_id = $id;
            $last_sent = $sent_at;
        }
    }

    $touch_id = (($last_id > 0) && (($completed_at - $last_sent) <= PG_CART_RECOVERY_WINDOW)) ? $last_id : 0;

    return array(
        'left'      => $left,
        'touch_id'  => $touch_id,
        'untouched' => $left && ($last_id === 0),
    );
}

/**
 * The first column of the first row a placeholder query returns; '' when it
 * failed or found nothing.
 *
 * @param string $sql
 * @param array  $params
 * @return string
 */
function pg_cart_recovery_first($sql, array $params)
{
    $result = db_q($sql, $params);

    if (!$result) {
        return '';
    }

    $row = mysqli_fetch_row($result);

    return is_array($row) ? (string) $row[0] : '';
}

/**
 * Records one touch of a left cart. The one writer of a touch: a WhatsApp
 * message carrying a single-use key (channel whatsapp, source template,
 * pg_cart_recovery_whatsapp_key_sent()) and an offer a visitor took up on a
 * page (channel page, source popup, the pop-up's id) call it too. A second call for the same order, channel and source writes
 * nothing, so a job that runs twice counts one touch.
 *
 * @param int      $order_id    the incomplete order
 * @param string   $channel     email, whatsapp, sms or page
 * @param string   $source_type campaign, template or popup
 * @param int      $source_id   that row's id
 * @param int|null $sent_at     unix time; now when null
 * @return int the touch's id, 0 when nothing was written
 */
function pg_cart_recovery_touch($order_id, $channel, $source_type, $source_id, $sent_at = null)
{
    $args = pg_cart_recovery_touch_args($order_id, $channel, $source_type, $source_id);
    $sent_at = ($sent_at === null) ? time() : (int) $sent_at;

    if (($args === null) || !pg_cart_recovery_ready()) {
        return 0;
    }

    $params = array((string) $args['order_id'], $args['channel'], $args['source_type'], (string) $args['source_id']);

    $existing = (int) pg_cart_recovery_first(
        "SELECT id FROM cart_recovery_touch
        WHERE (order_id = ?) AND (channel = ?) AND (source_type = ?) AND (source_id = ?)
        LIMIT 1", $params);

    if ($existing > 0) {
        return 0;
    }

    if (!db_q(
        "INSERT INTO cart_recovery_touch (order_id, channel, source_type, source_id, sent_at)
        VALUES (?, ?, ?, ?, ?)", array_merge($params, array((string) $sent_at)))
    ) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Whether a pop-up event is a touch of the visitor's cart, pure: the event
 * is "taken" and the session's unfinished order has at least one line. Any
 * pop-up counts, not only one shown to visitors with a cart. The 6-hour
 * left-cart test does not apply here: it is the rule of a sent message; the
 * completion rule decides whether the cart came back.
 *
 * @param string $event      shown, closed or taken
 * @param int    $order_id   the session's order, 0 for none
 * @param int    $line_count its lines not saved for later
 * @return bool
 */
function pg_cart_recovery_popup_touches($event, $order_id, $line_count)
{
    return ($event === 'taken') && ((int) $order_id > 0) && ((int) $line_count > 0);
}

/**
 * A pop-up's event from page_view.php, after the count's gates (rate limit,
 * staff, crawler, consent, signature): when it is a take-up and the session
 * holds a cart, the cart gets a touch of channel page, source popup. The
 * writer writes the same order and pop-up once.
 *
 * @param int    $auto_dialog_id
 * @param string $event
 * @return int the touch's id, 0 when nothing was written
 */
function pg_cart_recovery_popup_taken($auto_dialog_id, $event)
{
    if ($event !== 'taken') {
        return 0;
    }

    $order_id = (isset($_SESSION['ecommerce']['order_id']) && is_scalar($_SESSION['ecommerce']['order_id']))
        ? (int) $_SESSION['ecommerce']['order_id'] : 0;

    // The cart audience's test (one active line), so both read a cart alike.
    $line_count = (($order_id > 0) && function_exists('pg_auto_dialog_session_has_cart')
        && pg_auto_dialog_session_has_cart()) ? 1 : 0;

    if (!pg_cart_recovery_popup_touches($event, $order_id, $line_count)) {
        return 0;
    }

    return pg_cart_recovery_touch($order_id, 'page', 'popup', (int) $auto_dialog_id);
}

/**
 * ?r=<reference code> restored the cart: the order's last touch sent before
 * now is marked clicked and restored (each time kept from its first visit).
 *
 * @param int      $order_id
 * @param int|null $now unix time; now when null
 * @return void
 */
function pg_cart_recovery_restored($order_id, $now = null)
{
    $order_id = (int) $order_id;
    $now = ($now === null) ? time() : (int) $now;

    if (($order_id < 1) || !pg_cart_recovery_ready()) {
        return;
    }

    $touch_id = (int) pg_cart_recovery_first(
        "SELECT id FROM cart_recovery_touch
        WHERE (order_id = ?) AND (channel <> '') AND (sent_at > 0) AND (sent_at <= ?)
        ORDER BY sent_at DESC, id DESC
        LIMIT 1", array((string) $order_id, (string) $now));

    if ($touch_id < 1) {
        return;
    }

    db_q(
        "UPDATE cart_recovery_touch
        SET
            clicked_at = IF(clicked_at = 0, ?, clicked_at),
            restored_at = IF(restored_at = 0, ?, restored_at)
        WHERE id = ?", array((string) $now, (string) $now, (string) $touch_id));
}

/**
 * The left cart a message is about: the order given, when it is one, else
 * the contact's last changed left cart. 0 for none.
 *
 * @param int $order_id   the order the sender named, 0 for none
 * @param int $contact_id the recipient's contact, 0 for none
 * @param int $now
 * @return int
 */
function pg_cart_recovery_left_cart($order_id, $contact_id, $now)
{
    $order_id = (int) $order_id;
    $contact_id = (int) $contact_id;

    if ($order_id > 0) {
        $result = db_q("SELECT id, status, last_modified_timestamp FROM orders WHERE id = ?", array((string) $order_id));
    } elseif ($contact_id > 0) {
        $result = db_q(
            "SELECT orders.id, orders.status, orders.last_modified_timestamp
            FROM orders
            WHERE
                (orders.contact_id = ?)
                AND (orders.status = 'incomplete')
                AND EXISTS (
                    SELECT 1 FROM order_items
                    WHERE (order_items.order_id = orders.id)" . pg_order_item_active_sql() . "
                )
            ORDER BY orders.last_modified_timestamp DESC, orders.id DESC
            LIMIT 1", array((string) $contact_id));
    } else {
        return 0;
    }

    $row = $result ? mysqli_fetch_assoc($result) : null;

    if (!is_array($row) || !pg_cart_recovery_is_left($row['status'], $row['last_modified_timestamp'], $now)) {
        return 0;
    }

    return (int) $row['id'];
}

/**
 * A WhatsApp message carrying a single-use key was queued: a touch of the
 * left cart it is about (channel whatsapp, source the template). Called by
 * pg_whatsapp_enqueue() once the message is in the queue.
 *
 * @param int      $order_id    the order the sender named, 0 for none
 * @param int      $contact_id  the recipient's contact, 0 for none
 * @param int      $template_id whatsapp_templates.id
 * @param int|null $now         unix time; now when null
 * @return int the touch's id, 0 when nothing was written
 */
function pg_cart_recovery_whatsapp_key_sent($order_id, $contact_id, $template_id, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;

    if ((((int) $order_id < 1) && ((int) $contact_id < 1)) || !pg_cart_recovery_ready()) {
        return 0;
    }

    $cart = pg_cart_recovery_left_cart($order_id, $contact_id, $now);

    return ($cart > 0) ? pg_cart_recovery_touch($cart, 'whatsapp', 'template', $template_id, $now) : 0;
}

/**
 * A single-use key was used on an order: when the key came in a WhatsApp
 * message (whatsapp_outbox.key_code_id) and that message's template touched
 * this order, the touch is marked clicked and restored as ?r= marks one
 * (each time kept from its first visit).
 *
 * @param int        $order_id
 * @param array|null $key_code pg_order_key_code()
 * @param int|null   $now      unix time; now when null
 * @return void
 */
function pg_cart_recovery_key_used($order_id, $key_code, $now = null)
{
    $order_id = (int) $order_id;
    $key_id = is_array($key_code) ? (int) ($key_code['id'] ?? 0) : 0;
    $now = ($now === null) ? time() : (int) $now;

    if (($order_id < 1) || ($key_id < 1) || !pg_cart_recovery_ready() || !pg_schema_has('whatsapp_outbox')) {
        return;
    }

    $template_id = (int) pg_cart_recovery_first(
        "SELECT template_id FROM whatsapp_outbox WHERE key_code_id = ? ORDER BY id DESC LIMIT 1", array((string) $key_id));

    if ($template_id < 1) {
        return;
    }

    db_q(
        "UPDATE cart_recovery_touch
        SET
            clicked_at = IF(clicked_at = 0, ?, clicked_at),
            restored_at = IF(restored_at = 0, ?, restored_at)
        WHERE
            (order_id = ?) AND (channel = 'whatsapp') AND (source_type = 'template') AND (source_id = ?)
            AND (sent_at > 0) AND (sent_at <= ?)",
        array((string) $now, (string) $now, (string) $order_id, (string) $template_id, (string) $now));
}

/**
 * The cart's start time, read by pg_order_complete() before its first write
 * rewrites order_date. 0 when the table is not there (nothing to record).
 *
 * @param int $order_id
 * @return int
 */
function pg_cart_recovery_order_date($order_id)
{
    if (((int) $order_id < 1) || !pg_cart_recovery_ready()) {
        return 0;
    }

    return (int) pg_cart_recovery_first("SELECT order_date FROM orders WHERE id = ?", array((string) (int) $order_id));
}

/**
 * The order was completed: every touch of it gets completed_at; the touch
 * the rule picks gets recovered = 1 and the total. A left cart with no touch
 * gets one row with channel '' and the total, counted apart. The total is
 * written on one row of an order at most, so a sum over a period counts an
 * order once.
 *
 * @param int      $order_id
 * @param int      $order_date   the cart's start time, from pg_cart_recovery_order_date()
 * @param int      $total        the order's total, integer minor units
 * @param int|null $completed_at unix time; now when null
 * @return void
 */
function pg_cart_recovery_completed($order_id, $order_date, $total, $completed_at = null)
{
    $order_id = (int) $order_id;
    $completed_at = ($completed_at === null) ? time() : (int) $completed_at;
    $total = (int) round((float) $total);

    if (($order_id < 1) || !pg_cart_recovery_ready()) {
        return;
    }

    $result = db_q(
        "SELECT id, sent_at FROM cart_recovery_touch
        WHERE (order_id = ?) AND (channel <> '')", array((string) $order_id));

    if (!$result) {
        return;
    }

    $touches = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $touches[(int) $row['id']] = (int) $row['sent_at'];
    }

    $outcome = pg_cart_recovery_outcome($order_date, $completed_at, $touches);

    if ($touches) {
        db_q(
            "UPDATE cart_recovery_touch
            SET completed_at = ?
            WHERE (order_id = ?) AND (channel <> '')", array((string) $completed_at, (string) $order_id));
    }

    if ($outcome['touch_id'] > 0) {
        db_q(
            "UPDATE cart_recovery_touch
            SET recovered = 1, completed_total = ?
            WHERE id = ?", array((string) $total, (string) $outcome['touch_id']));

        return;
    }

    if ($outcome['untouched']) {
        db_q(
            "INSERT INTO cart_recovery_touch (order_id, channel, completed_at, completed_total)
            VALUES (?, '', ?, ?)", array((string) $order_id, (string) $completed_at, (string) $total));
    }
}
