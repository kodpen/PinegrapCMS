<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What weakens cart recovery: pure rules over a period's recovered carts
 * figures (includes/fn/cart_recovery_figures.php) and over what each left
 * cart's order holds, each with a minimum so a quiet shop is not warned about
 * two carts. The rules: left carts no message can reach (no address, or no
 * consent where the abandoned-order mail needs it), a message sent often and
 * clicked little, carts that came back and stopped (still open or completed
 * too late, and the furthest checkout step they reached), a first message
 * sent long after the cart was left, and left carts holding a line out of
 * stock. pg_cart_recovery_weak() runs them; pg_cart_recovery_weak_period()
 * reads the facts of a period. Nothing is stored.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The least left carts a period must hold for the cart rules to speak.
const PG_CART_RECOVERY_WEAK_MIN_LEFT = 10;

// Unreachable carts above this share of the left carts.
const PG_CART_RECOVERY_WEAK_UNREACHABLE = 0.3;

// A message sent at least this often whose carts came back under this share.
const PG_CART_RECOVERY_WEAK_MIN_SENT = 20;
const PG_CART_RECOVERY_WEAK_CLICKS = 0.05;

// How many messages the message rule names at most, the most sent first.
const PG_CART_RECOVERY_WEAK_MESSAGES = 3;

// At least this many carts came back and over this share of them stopped.
const PG_CART_RECOVERY_WEAK_MIN_RETURNED = 5;
const PG_CART_RECOVERY_WEAK_STALLED = 0.5;

// A first message this many seconds after the cart was started is late; the
// rule speaks when over this share of at least the minimum carts were.
const PG_CART_RECOVERY_WEAK_LATE_AFTER = 86400;
const PG_CART_RECOVERY_WEAK_LATE = 0.5;
const PG_CART_RECOVERY_WEAK_MIN_TIMED = 10;

// Left carts not recovered holding a line out of stock above this share.
const PG_CART_RECOVERY_WEAK_STOCK = 0.2;

// How many order ids one facts query reads.
const PG_CART_RECOVERY_WEAK_CHUNK = 500;

/**
 * A share in whole percent. Pure.
 *
 * @param int $part
 * @param int $base
 * @return int
 */
function pg_cart_recovery_weak_percent($part, $base)
{
    return ((int) $base > 0) ? (int) round((int) $part / (int) $base * 100) : 0;
}

/**
 * The checkout steps in the order a shopper goes through them.
 *
 * @return string[]
 */
function pg_cart_recovery_weak_steps()
{
    return array('cart', 'shipping', 'billing', 'payment');
}

/**
 * The furthest checkout step an order reached, from what it holds: sent to
 * the payment service, billing details, a shipping address, else the cart.
 * Pure.
 *
 * @param array $order checkout_page_id, billing (bool), shipping (bool)
 * @return string one of pg_cart_recovery_weak_steps()
 */
function pg_cart_recovery_weak_step(array $order)
{
    if (!empty($order['checkout_page_id'])) {
        return 'payment';
    }

    if (!empty($order['billing'])) {
        return 'billing';
    }

    if (!empty($order['shipping'])) {
        return 'shipping';
    }

    return 'cart';
}

/**
 * Left carts no message could reach: among the untouched ones, those with
 * no e-mail address and those whose contact has not opted in when the mail
 * needs it. Pure.
 *
 * @param int   $left      the period's left carts
 * @param array $untouched facts of the untouched carts: address (bool, null
 *                         for an order deleted since), consent (bool)
 * @return array|null array('rule' => 'unreachable', 'carts', 'left',
 *                    'no_address', 'no_consent', 'percent')
 */
function pg_cart_recovery_weak_unreachable($left, array $untouched)
{
    $left = (int) $left;

    if ($left < PG_CART_RECOVERY_WEAK_MIN_LEFT) {
        return null;
    }

    $no_address = 0;
    $no_consent = 0;

    foreach ($untouched as $cart) {
        // An order deleted since: nothing is known of its address.
        if (array_key_exists('address', $cart) && ($cart['address'] === null)) {
            continue;
        }

        if (empty($cart['address'])) {
            $no_address++;
        } elseif (empty($cart['consent'])) {
            $no_consent++;
        }
    }

    $carts = $no_address + $no_consent;

    if ($carts <= $left * PG_CART_RECOVERY_WEAK_UNREACHABLE) {
        return null;
    }

    return array(
        'rule'       => 'unreachable',
        'carts'      => $carts,
        'left'       => $left,
        'no_address' => $no_address,
        'no_consent' => $no_consent,
        'percent'    => pg_cart_recovery_weak_percent($carts, $left),
    );
}

/**
 * Messages sent often whose carts rarely came back, the most sent first.
 * Pure.
 *
 * @param array $messages pg_cart_recovery_figures()['messages'], with label
 * @return array list of array('rule' => 'message', 'label', 'channel',
 *               'sent', 'reached', 'returned', 'percent')
 */
function pg_cart_recovery_weak_messages(array $messages)
{
    $out = array();

    foreach ($messages as $message) {
        $sent = (int) $message['sent'];
        $reached = (int) $message['reached'];
        $returned = (int) $message['returned'];

        if (($sent < PG_CART_RECOVERY_WEAK_MIN_SENT) || ($reached < 1) || ($returned >= $reached * PG_CART_RECOVERY_WEAK_CLICKS)) {
            continue;
        }

        $out[] = array(
            'rule'     => 'message',
            'label'    => isset($message['label']) ? (string) $message['label'] : '',
            'channel'  => (string) $message['channel'],
            'sent'     => $sent,
            'reached'  => $reached,
            'returned' => $returned,
            'percent'  => pg_cart_recovery_weak_percent($returned, $reached),
        );
    }

    usort($out, function ($a, $b) {
        if ($a['sent'] !== $b['sent']) {
            return ($a['sent'] > $b['sent']) ? -1 : 1;
        }

        return strcmp($a['label'], $b['label']);
    });

    return array_slice($out, 0, PG_CART_RECOVERY_WEAK_MESSAGES);
}

/**
 * Carts that came back and were not recovered: still open (the order's own
 * status) or completed after the 7 days, with the furthest checkout step the
 * open ones reached. Pure.
 *
 * @param array $touched facts of the touched carts: returned, recovered,
 *                       open (bool), completed (bool: after the 7 days), step
 * @return array|null array('rule' => 'stalled', 'carts', 'returned', 'open',
 *                    'late', 'steps' => step => count, 'step', 'percent')
 */
function pg_cart_recovery_weak_stalled(array $touched)
{
    $returned = 0;
    $open = 0;
    $late = 0;
    $steps = array_fill_keys(pg_cart_recovery_weak_steps(), 0);

    foreach ($touched as $cart) {
        if (empty($cart['returned'])) {
            continue;
        }

        $returned++;

        if (!empty($cart['recovered'])) {
            continue;
        }

        if (!empty($cart['open'])) {
            $open++;
            $step = isset($steps[$cart['step']]) ? $cart['step'] : 'cart';
            $steps[$step]++;
        } elseif (!empty($cart['completed'])) {
            $late++;
        }
    }

    $carts = $open + $late;

    if (($returned < PG_CART_RECOVERY_WEAK_MIN_RETURNED) || ($carts <= $returned * PG_CART_RECOVERY_WEAK_STALLED)) {
        return null;
    }

    // The step most open carts stopped at; on a tie, the earlier step.
    $step = '';
    $most = 0;

    foreach ($steps as $name => $count) {
        if ($count > $most) {
            $step = $name;
            $most = $count;
        }
    }

    return array(
        'rule'     => 'stalled',
        'carts'    => $carts,
        'returned' => $returned,
        'open'     => $open,
        'late'     => $late,
        'steps'    => $steps,
        'step'     => $step,
        'percent'  => pg_cart_recovery_weak_percent($carts, $returned),
    );
}

/**
 * First messages sent long after the cart was left. Only carts whose start
 * time is still known count (the completion rewrites it): delay is null for
 * the others. Pure.
 *
 * @param array $touched facts of the touched carts: delay (seconds or null)
 * @return array|null array('rule' => 'late', 'carts', 'timed', 'percent')
 */
function pg_cart_recovery_weak_late(array $touched)
{
    $timed = 0;
    $late = 0;

    foreach ($touched as $cart) {
        if (!isset($cart['delay']) || ($cart['delay'] === null)) {
            continue;
        }

        $timed++;

        if ((int) $cart['delay'] > PG_CART_RECOVERY_WEAK_LATE_AFTER) {
            $late++;
        }
    }

    if (($timed < PG_CART_RECOVERY_WEAK_MIN_TIMED) || ($late <= $timed * PG_CART_RECOVERY_WEAK_LATE)) {
        return null;
    }

    return array('rule' => 'late', 'carts' => $late, 'timed' => $timed, 'percent' => pg_cart_recovery_weak_percent($late, $timed));
}

/**
 * Left carts not recovered that hold a line out of stock. Pure.
 *
 * @param int   $left  the period's left carts
 * @param array $carts facts of every left cart: recovered, stock_out (bool)
 * @return array|null array('rule' => 'stock', 'carts', 'left', 'percent')
 */
function pg_cart_recovery_weak_stock($left, array $carts)
{
    $left = (int) $left;

    if ($left < PG_CART_RECOVERY_WEAK_MIN_LEFT) {
        return null;
    }

    $out = 0;

    foreach ($carts as $cart) {
        if (empty($cart['recovered']) && !empty($cart['stock_out'])) {
            $out++;
        }
    }

    if ($out <= $left * PG_CART_RECOVERY_WEAK_STOCK) {
        return null;
    }

    return array('rule' => 'stock', 'carts' => $out, 'left' => $left, 'percent' => pg_cart_recovery_weak_percent($out, $left));
}

/**
 * Every rule over a period, the carts no message reached first. Pure.
 *
 * @param array $figures   pg_cart_recovery_figures(), messages named
 * @param array $touched   facts of the period's touched carts
 * @param array $untouched facts of the period's untouched carts
 * @return array the rules that fired
 */
function pg_cart_recovery_weak(array $figures, array $touched, array $untouched)
{
    $out = array();
    $left = (int) $figures['left'];

    foreach (array(
        pg_cart_recovery_weak_unreachable($left, $untouched),
        pg_cart_recovery_weak_stalled($touched),
        pg_cart_recovery_weak_late($touched),
        pg_cart_recovery_weak_stock($left, array_merge($touched, $untouched)),
    ) as $finding) {
        if ($finding !== null) {
            $out[] = $finding;
        }
    }

    return array_merge($out, pg_cart_recovery_weak_messages($figures['messages']));
}

/**
 * The touched carts of [from, to) from their touch rows: whether each came
 * back or was recovered, when it was completed, its first send and whether
 * it was completed more than 7 days after its last touch (late). Pure.
 *
 * @param array $touches pg_cart_recovery_touch_rows() with completed_at
 * @param int   $from
 * @param int   $to
 * @return array order id => array('first_sent', 'returned', 'recovered', 'completed_at', 'late')
 */
function pg_cart_recovery_weak_carts(array $touches, $from, $to)
{
    $carts = array();

    foreach ($touches as $touch) {
        if (((string) $touch['channel'] === '') || ((int) $touch['sent_at'] < 1)) {
            continue;
        }

        $id = (int) $touch['order_id'];

        if (!isset($carts[$id])) {
            $carts[$id] = array('first_sent' => (int) $touch['sent_at'], 'returned' => false, 'recovered' => false, 'completed_at' => 0, 'sent' => array());
        }

        $carts[$id]['sent'][] = (int) $touch['sent_at'];

        $carts[$id]['first_sent'] = min($carts[$id]['first_sent'], (int) $touch['sent_at']);
        $carts[$id]['returned'] = $carts[$id]['returned'] || ((int) $touch['clicked_at'] > 0) || ((int) $touch['restored_at'] > 0);
        $carts[$id]['recovered'] = $carts[$id]['recovered'] || ((int) $touch['recovered'] === 1);
        $carts[$id]['completed_at'] = max($carts[$id]['completed_at'], isset($touch['completed_at']) ? (int) $touch['completed_at'] : 0);
    }

    foreach ($carts as $id => $cart) {
        if (($cart['first_sent'] < (int) $from) || ($cart['first_sent'] >= (int) $to)) {
            unset($carts[$id]);

            continue;
        }

        // Late: completed, not recovered, and its last touch before the
        // completion more than 7 days earlier. A cart completed within the 7
        // days without a recovery was not left (the completion rule).
        $last_sent = 0;

        foreach ($cart['sent'] as $sent_at) {
            if ($sent_at <= $cart['completed_at']) {
                $last_sent = max($last_sent, $sent_at);
            }
        }

        $carts[$id]['late'] = ($cart['completed_at'] > 0) && !$cart['recovered']
            && (($last_sent === 0) || (($cart['completed_at'] - $last_sent) > PG_CART_RECOVERY_WINDOW));

        unset($carts[$id]['sent']);
    }

    return $carts;
}

/**
 * Whether the abandoned-order mail needs the contact's opt-in: an enabled
 * profile of that kind whose purpose is not transactional.
 *
 * @return bool
 */
function pg_cart_recovery_weak_consent_needed()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_recipients.php');

    $result = db_q(
        "SELECT purpose FROM email_campaign_profiles
        WHERE (enabled = '1') AND (action = 'order_abandoned')", array());

    if (!$result) {
        return true;
    }

    $needed = false;

    while ($row = mysqli_fetch_assoc($result)) {
        if (pg_email_campaign_opt_in_required((string) $row['purpose'])) {
            $needed = true;
        }
    }

    return $needed;
}

/**
 * What each order holds for the rules: open, its start time, its contact's
 * address and opt-in, the furthest checkout step and a line out of stock.
 *
 * @param int[] $order_ids
 * @return array order id => array('open', 'order_date', 'address', 'opt_in',
 *               'step', 'stock_out')
 */
function pg_cart_recovery_weak_orders(array $order_ids)
{
    $out = array();

    foreach (array_chunk(array_values(array_unique(array_map('intval', $order_ids))), PG_CART_RECOVERY_WEAK_CHUNK) as $ids) {
        $result = db_q(
            "SELECT
                orders.id,
                orders.status,
                orders.order_date,
                orders.checkout_page_id,
                orders.billing_email_address,
                contacts.email_address AS contact_email_address,
                contacts.opt_in,
                EXISTS (
                    SELECT 1 FROM ship_tos
                    WHERE (ship_tos.order_id = orders.id) AND (ship_tos.address_1 <> '')) AS shipping,
                EXISTS (
                    SELECT 1 FROM order_items
                    INNER JOIN products ON products.id = order_items.product_id
                    WHERE
                        (order_items.order_id = orders.id)" . pg_order_item_active_sql('order_items') . "
                        AND (products.inventory = 1)
                        AND (products.inventory_quantity <= 0)
                        AND (products.backorder = 0)) AS stock_out
            FROM orders
            LEFT JOIN contacts ON (contacts.id = orders.contact_id) AND (orders.contact_id <> 0)
            WHERE orders.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")",
            array_map('strval', $ids));

        if (!$result) {
            continue;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $out[(int) $row['id']] = array(
                'open'       => ((string) $row['status'] === 'incomplete'),
                'order_date' => (int) $row['order_date'],
                'address'    => (trim((string) $row['contact_email_address']) !== ''),
                'opt_in'     => ((int) $row['opt_in'] === 1),
                'step'       => pg_cart_recovery_weak_step(array(
                    'checkout_page_id' => (int) $row['checkout_page_id'],
                    'billing'          => (trim((string) $row['billing_email_address']) !== ''),
                    'shipping'         => ((int) $row['shipping'] === 1),
                )),
                'stock_out'  => ((int) $row['stock_out'] === 1),
            );
        }
    }

    return $out;
}

/**
 * The ids of the untouched left carts of [from, to), as
 * pg_cart_recovery_untouched() counts them.
 *
 * @param int $from
 * @param int $to
 * @param int $now
 * @return int[]
 */
function pg_cart_recovery_weak_untouched_ids($from, $to, $now)
{
    $ids = array();
    $queries = array(
        array(
            "SELECT order_id FROM cart_recovery_touch
            WHERE (channel = '') AND (completed_at >= ?) AND (completed_at < ?)",
            array((string) (int) $from, (string) (int) $to),
        ),
    );

    $until = min((int) $to, (int) $now - PG_CART_RECOVERY_LEFT_AFTER + 1);

    if ($until > (int) $from) {
        $queries[] = array(
            "SELECT orders.id FROM orders
            WHERE
                (orders.status = 'incomplete')
                AND (orders.order_date >= ?)
                AND (orders.order_date < ?)
                AND NOT EXISTS (
                    SELECT 1 FROM cart_recovery_touch
                    WHERE cart_recovery_touch.order_id = orders.id)
                AND EXISTS (
                    SELECT 1 FROM order_items
                    WHERE (order_items.order_id = orders.id)" . pg_order_item_active_sql('order_items') . ")",
            array((string) (int) $from, (string) $until),
        );
    }

    foreach ($queries as $query) {
        $result = db_q($query[0], $query[1]);

        if ($result) {
            while ($row = mysqli_fetch_row($result)) {
                $ids[] = (int) $row[0];
            }
        }
    }

    return $ids;
}

/**
 * The facts of a period's left carts for pg_cart_recovery_weak(): the
 * touched ones and the untouched ones.
 *
 * @param int $from
 * @param int $to
 * @param int $now
 * @return array array('touched' => list, 'untouched' => list)
 */
function pg_cart_recovery_weak_facts($from, $to, $now)
{
    // The figures' own rows and reading, so both count a cart alike.
    $touches = pg_cart_recovery_touch_rows($from, $to, $now);

    $carts = pg_cart_recovery_weak_carts($touches, $from, $to);
    $untouched_ids = pg_cart_recovery_weak_untouched_ids($from, $to, $now);
    $orders = pg_cart_recovery_weak_orders(array_merge(array_keys($carts), $untouched_ids));
    $consent_needed = pg_cart_recovery_weak_consent_needed();

    $touched = array();

    foreach ($carts as $id => $cart) {
        $order = isset($orders[$id]) ? $orders[$id] : array('open' => false, 'order_date' => 0, 'step' => 'cart', 'stock_out' => false);

        $touched[] = array(
            'returned'  => $cart['returned'],
            'recovered' => $cart['recovered'],
            'open'      => $order['open'],
            'completed' => $cart['late'],
            'step'      => $order['step'],
            // A completed order's start time was rewritten at completion.
            'delay'     => ($order['open'] && ($order['order_date'] > 0)) ? max(0, $cart['first_sent'] - $order['order_date']) : null,
            'stock_out' => $order['open'] && $order['stock_out'],
        );
    }

    $untouched = array();

    foreach ($untouched_ids as $id) {
        $untouched[] = pg_cart_recovery_weak_untouched_fact(isset($orders[$id]) ? $orders[$id] : null, $consent_needed);
    }

    return array('touched' => $touched, 'untouched' => $untouched);
}

/**
 * The facts of one untouched left cart for the rules, pure. A no-touch row
 * outlives its order (a deleted order keeps its touches): for it the
 * address is null, unknown, so the unreachable rule does not count it as a
 * cart with no address.
 *
 * @param array|null $order          pg_cart_recovery_weak_orders() row, null when the order is gone
 * @param bool       $consent_needed pg_cart_recovery_weak_consent_needed()
 * @return array address (bool|null), consent, recovered, stock_out
 */
function pg_cart_recovery_weak_untouched_fact($order, $consent_needed)
{
    if (!is_array($order)) {
        return array('address' => null, 'consent' => false, 'recovered' => false, 'stock_out' => false);
    }

    return array(
        'address'   => !empty($order['address']),
        'consent'   => !$consent_needed || !empty($order['opt_in']),
        'recovered' => false,
        'stock_out' => !empty($order['open']) && !empty($order['stock_out']),
    );
}

/**
 * What weakens recovery in [from, to); array() before the table exists.
 *
 * @param array    $figures pg_cart_recovery_period() of the same period
 * @param int      $from
 * @param int      $to
 * @param int|null $now unix time; now when null
 * @return array pg_cart_recovery_weak()
 */
function pg_cart_recovery_weak_period(array $figures, $from, $to, $now = null)
{
    if (!pg_cart_recovery_ready()) {
        return array();
    }

    $facts = pg_cart_recovery_weak_facts($from, $to, ($now === null) ? time() : (int) $now);

    return pg_cart_recovery_weak($figures, $facts['touched'], $facts['untouched']);
}
