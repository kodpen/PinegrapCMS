<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visitor notifications for an order (kind 3): a browser that pressed
 * "Notify me on this device" on the order's own page is told when the order
 * is shipped and when it is delivered. Transactional, as the order's text
 * message is: it goes to that one device, never to every subscriber, and
 * stays out of the panel's list of sent messages.
 *
 * The wait is kept in site_push_watch, one row per address of the order
 * (record_id ship_tos.id): "shipped" sets bit 1 of moments, so a parcel
 * number added later sends nothing again; "delivered" is the last moment and
 * deletes the row. The moments are sent from the same two places as the
 * order's text message (update_order() and pg_shipment_delivered()), so a
 * marketplace's order and a save that keeps the customer's notice back send
 * nothing here either.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

define('PG_SITE_PUSH_KIND_ORDER', 3);

/**
 * The title and text of an order's moment in the subscription's language,
 * cut to the message columns (title 60, text 200).
 *
 * @param string $moment       shipped or delivered
 * @param string $order_number
 * @param string $language     the subscription's language ('' the site's)
 * @return array|null title, body; null for a moment that sends nothing
 */
function pg_site_push_order_text($moment, $order_number, $language)
{
    $texts = array(
        'shipped'   => array('Your order has shipped', 'Order {var:1} is on its way.'),
        'delivered' => array('Your order has been delivered', 'Order {var:1} has been delivered.'),
    );

    if (!isset($texts[(string) $moment])) {
        return null;
    }

    $in = array();

    if ((string) $language !== '') {
        $in['language'] = (string) $language;
    }

    $title = lang(array('string' => $texts[$moment][0]) + $in);
    $body = lang(array('string' => $texts[$moment][1], 'vars' => array((string) $order_number)) + $in);

    return array(
        'title' => mb_substr($title, 0, 60, 'UTF-8'),
        'body'  => mb_substr($body, 0, 200, 'UTF-8'),
    );
}

/**
 * The moments of an order's wait: the bit a moment sets and whether it is
 * the last one (which deletes the row). Pure.
 *
 * @param string $moment
 * @return array|null  array(bit, last), or null for a moment that sends nothing
 */
function pg_site_push_order_moment_bit($moment)
{
    $moments = array(
        'shipped'   => array(1, false),
        'delivered' => array(0, true),
    );

    return isset($moments[(string) $moment]) ? $moments[(string) $moment] : null;
}

/**
 * Whether this visitor may have an order's moments sent to their browser:
 * a placed order (not incomplete), which is the one this session has just
 * completed (the receipt page) or one of the signed-in account's own (the
 * order view in My Account). Pure.
 *
 * @param array|null $order               id, user_id, status
 * @param int        $completed_order_id  the session's completed order
 * @param int        $user_id             the signed-in account, or 0
 * @return bool
 */
function pg_site_push_order_may_watch($order, $completed_order_id, $user_id)
{
    if (!is_array($order) || ((int) ($order['id'] ?? 0) < 1) || ((string) ($order['status'] ?? '') === 'incomplete')) {
        return false;
    }

    if ((int) $completed_order_id === (int) $order['id']) {
        return true;
    }

    return ((int) $user_id > 0) && ((int) ($order['user_id'] ?? 0) === (int) $user_id);
}

/**
 * The door's "order" action: the browser named by its secret waits for the
 * order's shipped and delivered moments, on every address of the order.
 *
 * @param string $secret
 * @param int    $order_id
 * @return int|string  rows written (0 when it already waited), or an error
 *                     code: off, subscription, order, nothing
 */
function pg_site_push_order_watch($secret, $order_id)
{
    if (!pg_site_push_enabled() || !pg_site_push_watch_ready()) {
        return 'off';
    }

    $subscription = pg_site_push_subscription_by_secret($secret);

    if (!$subscription) {
        return 'subscription';
    }

    $order = db_item("SELECT id, user_id, status FROM orders WHERE id = '" . (int) $order_id . "'");
    $completed = (int) ($_SESSION['ecommerce']['completed_order_id'] ?? 0);

    if (!pg_site_push_order_may_watch($order, $completed, pg_site_push_user_id())) {
        return 'order';
    }

    $ship_to_ids = array_map('intval', db_values("SELECT id FROM ship_tos WHERE order_id = '" . (int) $order['id'] . "'"));

    if (!$ship_to_ids) {
        return 'nothing';
    }

    return pg_site_push_watch_add(PG_SITE_PUSH_KIND_ORDER, $ship_to_ids, (int) $subscription['id']);
}

/**
 * Sends an address's moment to every browser waiting for it, once each.
 * Called beside the order's text message, only when the customer is told.
 *
 * @param int    $order_id
 * @param int    $ship_to_id
 * @param string $moment  shipped or delivered
 * @return int  messages written
 */
function pg_site_push_order_moment($order_id, $ship_to_id, $moment)
{
    $taken = pg_site_push_order_moment_bit($moment);

    if (!$taken || !pg_site_push_enabled()) {
        return 0;
    }

    $watchers = pg_site_push_watchers(PG_SITE_PUSH_KIND_ORDER, $ship_to_id);

    if (!$watchers) {
        return 0;
    }

    list($bit, $last) = $taken;
    $order_number = (string) db_value("SELECT order_number FROM orders WHERE id = '" . (int) $order_id . "'");
    $url = pg_site_push_order_url($order_id);
    $sent = 0;

    foreach ($watchers as $watcher) {
        if (!pg_site_push_watch_take(PG_SITE_PUSH_KIND_ORDER, $ship_to_id, (int) $watcher['subscription_id'], $bit, $last)) {
            continue;
        }

        $text = pg_site_push_order_text($moment, $order_number, (string) $watcher['language']);

        if (pg_site_push_message_to_one($text + array('url' => $url), PG_SITE_PUSH_KIND_ORDER, (int) $watcher['subscription_id'])) {
            $sent++;
        }
    }

    return $sent;
}

/**
 * Where a click on an order's notice opens: the order view page with the
 * order (it asks a signed-out visitor to sign in), or the home page when the
 * site has none. A path, as site_push_message.url keeps it.
 *
 * @param int $order_id
 * @return string
 */
function pg_site_push_order_url($order_id)
{
    $page = (string) get_page_type_url('view order');
    $path = ($page === '') ? '/' : pg_site_push_url_path($page . '?id=' . (int) $order_id, pg_site_push_hosts());

    return ($path === '') ? '/' : $path;
}

/**
 * Drops the waits of an order that is being deleted. Called before its
 * addresses are deleted, since the rows are found by them.
 *
 * @param int $order_id
 * @return void
 */
function pg_site_push_order_drop($order_id)
{
    if (pg_site_push_watch_ready()) {
        pg_site_push_watch_drop(PG_SITE_PUSH_KIND_ORDER, db_values("SELECT id FROM ship_tos WHERE order_id = '" . (int) $order_id . "'"));
    }
}
