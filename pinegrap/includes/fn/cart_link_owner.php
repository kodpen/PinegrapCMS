<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Whose account a cart becomes when it is opened through its saved-cart link
 * (?r=): the order belongs to whoever completes it. A signed-in opener takes
 * it over, a guest opener leaves it with no account (a later sign-in binds
 * it as connect_user_to_order() does). The account that built the cart then
 * no longer lists the order or sees the buyer's billing details.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The owner to write on an order opened through its link: an array with
// user_id and contact_id, or null when the row already holds them. $order
// holds the row's user_id and contact_id. A ghosting staff member acts as the
// customer, so the order is not moved, as in initialize_order().
function pg_cart_link_owner($order, $logged_in, $ghosting, $user_id, $contact_id)
{
    if ($ghosting) {
        return null;
    }

    $owner = $logged_in
        ? array('user_id' => (int) $user_id, 'contact_id' => (int) $contact_id)
        : array('user_id' => 0, 'contact_id' => 0);

    if ((int) ($order['user_id'] ?? 0) === $owner['user_id']
        && (int) ($order['contact_id'] ?? 0) === $owner['contact_id']) {
        return null;
    }

    return $owner;
}

// Moves an incomplete order opened through its link to the current visitor.
function pg_cart_link_take_over($order_id)
{
    $order_id = (int) $order_id;

    if ($order_id <= 0) {
        return;
    }

    $order = db_item("SELECT user_id, contact_id FROM orders WHERE id = '" . $order_id . "' AND status = 'incomplete'");

    if (!$order) {
        return;
    }

    $logged_in = defined('USER_LOGGED_IN') && USER_LOGGED_IN;

    $owner = pg_cart_link_owner(
        $order,
        $logged_in,
        !empty($_SESSION['software']['ghost']),
        $logged_in && defined('USER_ID') ? USER_ID : 0,
        $logged_in && defined('USER_CONTACT_ID') ? USER_CONTACT_ID : 0
    );

    if ($owner === null) {
        return;
    }

    db_q("UPDATE orders SET user_id = ?, contact_id = ? WHERE id = ?", array($owner['user_id'], $owner['contact_id'], $order_id));
}
