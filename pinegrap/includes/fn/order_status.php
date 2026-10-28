<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Order status moves: what taking an order from the status it has to another
 * one asks of the store.
 *
 * The external API's order update (api_orders_status_move()) and the
 * Workspace's order writer (ws_change_write_order(), refused first by
 * ws_change_check()) decide by the one rule below, so neither writes the
 * status into or out of cancelled by hand: a cancellation goes through
 * process_order_cancellation() and a restore through
 * pg_order_restore_cancelled() (includes/fn/ecommerce.php). Those two do the
 * bookkeeping a plain status write leaves behind: the canceller, the date and
 * the reason, the gift cards the order issued, the review request, the
 * payment, the customer's e-mail and the order.cancelled announcement.
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
 * What moving an order from the status it has ($from) to another one ($to)
 * asks of the store. Pure: the caller reads the order and carries the answer
 * out.
 *
 *   none     nothing: the status stays as it is.
 *   set      a plain status write: between complete and exported, either way.
 *   cancel   the store's cancellation, process_order_cancellation(): it
 *            records who cancelled and why, expires the gift cards the order
 *            issued, cancels a waiting review request, voids or flags the
 *            payment, mails the customer and announces order.cancelled.
 *   restore  out of cancelled to complete or exported, the order screen's
 *            Restore, pg_order_restore_cancelled(): the cancellation's
 *            bookkeeping and an unfinished refund flag are cleared and the
 *            gift cards it expired get their date back. It takes no other
 *            target.
 *   refuse   a move the store has no path for:
 *            - to or from incomplete. incomplete is the cart's status: a
 *              placed order is never turned back into a cart (which the
 *              abandoned cart mail would then pick up), and a cart becomes an
 *              order only through the checkout, which numbers it, takes the
 *              payment and moves the stock. The order screen offers neither,
 *              nor a cancel for a cart.
 *            - out of cancelled to anything but complete or exported.
 *            - to a value the status column does not hold. 'canceled' with
 *              one L is refused either way: the column has not held it since
 *              2026.1.26 (it is stored as ''), only the status badge still
 *              reads it, and neither helper takes such a row.
 *
 * An order whose status was lost ('') may still be set to complete or
 * exported, or cancelled.
 *
 * @param string $from the status the order has
 * @param string $to   the status asked for
 * @return string none, set, cancel, restore or refuse
 */
function pg_order_status_move($from, $to)
{
    $from = (string) $from;
    $to = (string) $to;

    if ($from === $to) {
        return 'none';
    }

    if (($from === 'incomplete') || ($to === 'incomplete') || ($from === 'canceled')) {
        return 'refuse';
    }

    if ($to === 'cancelled') {
        return 'cancel';
    }

    if (!in_array($to, array('complete', 'exported'), true)) {
        return 'refuse';
    }

    return ($from === 'cancelled') ? 'restore' : 'set';
}

/**
 * Why pg_order_status_move() refuses a move, in the words the external API
 * and the Workspace give it.
 *
 * @param string $from the status the order has
 * @param string $to   the status asked for
 * @return string
 */
function pg_order_status_move_refusal($from, $to)
{
    $vars = array((string) $from, (string) $to);

    if (in_array('incomplete', $vars, true)) {
        return lang(array(
            'string' => 'An order cannot be moved from {var:1} to {var:2}: a basket becomes an order only through the checkout, and an order does not go back to being a basket.',
            'vars'   => $vars,
        ));
    }

    return lang(array(
        'string' => 'An order cannot be moved from {var:1} to {var:2}.',
        'vars'   => $vars,
    ));
}

/**
 * The SET clause that moves an order's last-change stamp
 * (orders.last_modified_timestamp) to now, for the UPDATE that changes it:
 * the external API's GET /orders?modified_since= and the ShipWorks feed find
 * an order again by this stamp, so every writer that changes what
 * GET /orders/{id} shows carries it (the cancellation, its refund outcome,
 * the restore, a status or notes write, the refund mark, an export). Set in
 * the same statement, never as a second UPDATE, so a change is never seen
 * without its stamp.
 *
 * @return string
 */
function pg_order_touch_sql()
{
    return 'last_modified_timestamp = UNIX_TIMESTAMP()';
}
