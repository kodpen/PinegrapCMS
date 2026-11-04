<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Store credit (2026.11.0, 11.0.159): a gift card bound to a customer.
 *
 * gift_cards.user_id names the customer the card belongs to (0 = a plain
 * gift card, as before), kind says where the credit came from (gift, points,
 * refund, manual), reason is the line staff or the code wrote when it was
 * issued, issued_by the user who issued it (0 = the shop itself) and
 * expires_at the moment it stops (unix time, 0 = never). A customer's
 * balance is the sum of the balances of their open cards; a card is spent
 * through the gift card path that exists (applied_gift_cards), so totals,
 * receipts and refunds of a cancelled order stay the gift card ones.
 *
 * expiration_date, which the checkout already checks, is written beside
 * expires_at as the last day the card is valid, so the code path that
 * reads gift cards refuses an expired credit card without knowing about
 * credit.
 *
 * Every reader asks pg_credit_ready() first: files land before the upgrade
 * adds the columns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Longest reason kept on a card (gift_cards.reason is VARCHAR(255)). */
define('PG_CREDIT_REASON_LENGTH', 255);

/**
 * Whether the upgrade has added the store credit columns.
 *
 * @return bool
 */
function pg_credit_ready()
{
    return function_exists('pg_schema_has')
        && pg_schema_has('gift_cards', 'user_id')
        && pg_schema_has('gift_cards', 'kind')
        && pg_schema_has('gift_cards', 'expires_at');
}

/**
 * The kinds a card may have, stored word => name in the panel language.
 * 'gift' is a card sold or created as a gift card; the others are credit.
 *
 * @return array
 */
function pg_credit_kinds()
{
    return array(
        'gift'   => lang('Gift card'),
        'points' => lang('Reward points'),
        'refund' => lang('Refund'),
        'manual' => lang('Issued by staff'),
    );
}

/**
 * The last day a card expiring at $expires_at may still be used, as
 * gift_cards.expiration_date holds it: a card is valid through that date.
 * A moment at midnight belongs to the day before.
 *
 * @param int $expires_at  unix time, 0 = never
 * @return string  Y-m-d, or 0000-00-00 for never
 */
function pg_credit_expiration_date($expires_at)
{
    $expires_at = (int) $expires_at;

    return ($expires_at > 0) ? date('Y-m-d', $expires_at - 1) : '0000-00-00';
}

/**
 * Whether a card row can still pay: a balance above zero and not expired.
 *
 * @param array $card  a gift_cards row (balance, expires_at)
 * @param int   $now
 * @return bool
 */
function pg_credit_card_open(array $card, $now)
{
    $balance = isset($card['balance']) ? (int) $card['balance'] : 0;
    $expires = isset($card['expires_at']) ? (int) $card['expires_at'] : 0;
    $date = isset($card['expiration_date']) ? (string) $card['expiration_date'] : '0000-00-00';

    // expiration_date is what the checkout refuses a card by, and the gift
    // card screen edits only that, so both must allow the card.
    $date_open = ($date === '') || ($date === '0000-00-00') || ($date >= date('Y-m-d', (int) $now));

    return ($balance > 0) && (($expires === 0) || ($expires > (int) $now)) && $date_open;
}

/**
 * Whether a card may be applied by a customer at checkout: a plain gift card
 * (no owner) by anyone with its code, a customer's credit only by that
 * customer signed in as themselves.
 *
 * @param int $card_user_id  gift_cards.user_id
 * @param int $customer      pg_credit_checkout_user()
 * @return bool
 */
function pg_credit_card_usable_by($card_user_id, $customer)
{
    $card_user_id = (int) $card_user_id;

    return ($card_user_id === 0) || (($card_user_id > 0) && ($card_user_id === (int) $customer));
}

/**
 * The customer a checkout spends credit for: the signed-in user, or 0 for a
 * guest and for staff placing an order on someone's behalf (ghost).
 *
 * @return int
 */
function pg_credit_checkout_user()
{
    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN || !defined('USER_ID')) {
        return 0;
    }

    if (!empty($_SESSION['software']['ghost'])) {
        return 0;
    }

    return max(0, (int) USER_ID);
}

/**
 * The owner column for a SELECT on gift_cards, with a comma in front; before
 * the upgrade every card is unowned.
 *
 * @return string
 */
function pg_credit_owner_select_sql()
{
    return pg_credit_ready() ? ', user_id' : ', 0 AS user_id';
}

/**
 * Which cards cover an amount, oldest expiry first: a card that expires
 * soonest is spent first, a card that never expires last, and two cards with
 * the same expiry by id. A card with no balance (spent or voided), past its
 * expires_at or past an expiration_date given in the row is never picked. The last card picked gives only what is still
 * needed; when the cards hold less than the amount, every open card is
 * taken in full and the sum says how much is covered.
 *
 * @param array    $cards   gift_cards rows: id, code, balance, expires_at
 * @param int      $amount  minor units
 * @param int|null $now
 * @return array  list of array(id, code, amount) in the order they are spent
 */
function pg_credit_pick(array $cards, $amount, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $left = max(0, (int) $amount);
    $open = array();

    foreach ($cards as $card) {
        if (is_array($card) && pg_credit_card_open($card, $now)) {
            $open[] = $card;
        }
    }

    usort($open, function ($a, $b) {
        $ea = (int) $a['expires_at'];
        $eb = (int) $b['expires_at'];

        if ($ea !== $eb) {
            if ($ea === 0) {
                return 1;
            }

            if ($eb === 0) {
                return -1;
            }

            return ($ea < $eb) ? -1 : 1;
        }

        $ia = isset($a['id']) ? (int) $a['id'] : 0;
        $ib = isset($b['id']) ? (int) $b['id'] : 0;

        return ($ia === $ib) ? 0 : (($ia < $ib) ? -1 : 1);
    });

    $picked = array();

    foreach ($open as $card) {
        if ($left <= 0) {
            break;
        }

        $take = min($left, (int) $card['balance']);
        $picked[] = array(
            'id'     => isset($card['id']) ? (int) $card['id'] : 0,
            'code'   => isset($card['code']) ? (string) $card['code'] : '',
            'amount' => $take,
        );
        $left -= $take;
    }

    return $picked;
}

/**
 * What a number of reward points is worth in minor units: $points_per_unit
 * points make one unit of money (100 minor units). Rounded half up with
 * round() to the smallest unit; a value under $smallest (minor units) or a
 * rate of zero is worth nothing, so a site that sets no rate converts no
 * points.
 *
 * @param int       $points
 * @param int|float $points_per_unit
 * @param int       $smallest  minor units, 0 = any value
 * @return int  minor units
 */
function pg_credit_points_value($points, $points_per_unit, $smallest = 0)
{
    $points = (int) $points;
    $rate = (float) $points_per_unit;

    if (($points <= 0) || ($rate <= 0)) {
        return 0;
    }

    $value = (int) round($points * 100 / $rate);

    return ($value < max(0, (int) $smallest)) ? 0 : $value;
}

/**
 * The SQL condition for a customer's open cards. $user_id and $now are
 * integers, so the string is safe as it is.
 *
 * @param int $user_id
 * @param int $now
 * @return string
 */
function pg_credit_open_sql($user_id, $now)
{
    return "user_id = " . (int) $user_id
        . " AND balance > 0"
        . " AND (expires_at = 0 OR expires_at > " . (int) $now . ")"
        . " AND (expiration_date = '0000-00-00' OR expiration_date >= '" . date('Y-m-d', (int) $now) . "')";
}

/**
 * A customer's open cards, oldest expiry first (the order pg_credit_pick()
 * spends them in).
 *
 * @param int      $user_id
 * @param int|null $now
 * @return array  gift_cards rows: id, code, amount, balance, kind, reason,
 *                issued_by, expires_at, expiration_date, created_timestamp
 */
function pg_credit_cards($user_id, $now = null)
{
    $user_id = (int) $user_id;

    if (($user_id <= 0) || !pg_credit_ready()) {
        return array();
    }

    $now = ($now === null) ? time() : (int) $now;

    $rows = db_items(
        "SELECT id, code, amount, balance, kind, reason, issued_by, expires_at, expiration_date, created_timestamp
        FROM gift_cards
        WHERE " . pg_credit_open_sql($user_id, $now) . "
        ORDER BY (expires_at = 0), expires_at, id");

    return is_array($rows) ? $rows : array();
}

/**
 * A customer's store credit: the sum of their open cards' balances.
 *
 * @param int      $user_id
 * @param int|null $now
 * @return int  minor units
 */
function pg_credit_balance($user_id, $now = null)
{
    $user_id = (int) $user_id;

    if (($user_id <= 0) || !pg_credit_ready()) {
        return 0;
    }

    $now = ($now === null) ? time() : (int) $now;

    return (int) db_value("SELECT COALESCE(SUM(balance), 0) FROM gift_cards WHERE " . pg_credit_open_sql($user_id, $now));
}

/**
 * The fields of a card the audit trail compares.
 *
 * @param array $card
 * @return array
 */
function pg_credit_audit_fields(array $card)
{
    $fields = array();

    foreach (array('user_id', 'kind', 'amount', 'balance', 'reason', 'issued_by', 'expires_at', 'void_reason') as $name) {
        if (array_key_exists($name, $card)) {
            $fields[$name] = $card[$name];
        }
    }

    return $fields;
}

/**
 * The name a card is recorded under: its code with all but the last four
 * digits hidden, since the code can still pay at a checkout.
 *
 * @param string $code
 * @return string
 */
function pg_credit_audit_label($code)
{
    $code = (string) $code;

    return (strlen($code) > 4) ? str_repeat('*', strlen($code) - 4) . substr($code, -4) : $code;
}

/**
 * The checks pg_credit_issue() makes before it writes, as a reason the call
 * is refused ('' when it may go ahead).
 *
 * @param int    $user_id
 * @param int    $amount   minor units
 * @param string $kind
 * @param int    $expires  unix time, 0 = never
 * @param int    $now
 * @return string  '', 'user', 'amount', 'kind' or 'expires'
 */
function pg_credit_issue_check($user_id, $amount, $kind, $expires, $now)
{
    if ((int) $user_id <= 0) {
        return 'user';
    }

    // gift_cards.amount and balance are INT UNSIGNED.
    if (!is_int($amount) || ($amount <= 0) || ($amount > 4294967295)) {
        return 'amount';
    }

    if (($kind === 'gift') || !array_key_exists((string) $kind, pg_credit_kinds())) {
        return 'kind';
    }

    if (((int) $expires < 0) || (((int) $expires > 0) && ((int) $expires <= (int) $now))) {
        return 'expires';
    }

    return '';
}

/**
 * Give a customer store credit: a gift_cards row of their own with a
 * generated code they never need to type.
 *
 * @param int    $user_id    the customer (user.user_id)
 * @param int    $amount     minor units, above zero
 * @param string $kind       points | refund | manual
 * @param string $reason     shown to staff and the customer
 * @param int    $expires    unix time the credit stops, 0 = never
 * @param int    $issued_by  the user who issued it, 0 = the shop itself
 * @return int  the card id, 0 when refused or not written
 */
function pg_credit_issue($user_id, $amount, $kind, $reason = '', $expires = 0, $issued_by = 0)
{
    $now = time();

    if (!pg_credit_ready() || (pg_credit_issue_check((int) $user_id, $amount, (string) $kind, (int) $expires, $now) !== '')) {
        return 0;
    }

    if ((int) db_value("SELECT COUNT(*) FROM user WHERE user_id = " . (int) $user_id) === 0) {
        return 0;
    }

    $reason = mb_substr(trim((string) $reason), 0, PG_CREDIT_REASON_LENGTH);
    $code = generate_gift_card_code();

    $card = array(
        'code'                    => $code,
        'amount'                  => $amount,
        'balance'                 => $amount,
        'notes'                   => '',
        'message'                 => '',
        'expiration_date'         => pg_credit_expiration_date((int) $expires),
        'user_id'                 => (int) $user_id,
        'kind'                    => (string) $kind,
        'reason'                  => $reason,
        'issued_by'               => max(0, (int) $issued_by),
        'expires_at'              => (int) $expires,
        'created_user_id'         => max(0, (int) $issued_by),
        'created_timestamp'       => $now,
        'last_modified_user_id'   => max(0, (int) $issued_by),
        'last_modified_timestamp' => $now,
    );

    $written = db_q(
        "INSERT INTO gift_cards (" . implode(', ', array_keys($card)) . ") VALUES (" . implode(', ', array_fill(0, count($card), '?')) . ")",
        array_values($card));

    if (!$written) {
        return 0;
    }

    // Read before the audit line: its INSERT changes mysqli_insert_id().
    $id = (int) mysqli_insert_id(db::$con);

    if ($id > 0) {
        pg_audit('create', 'store_credit', $id, array(), pg_credit_audit_fields($card), pg_credit_audit_label($code));

        // The credit_issued mail, when a profile asks for it.
        if (function_exists('pg_credit_mail_issued')) {
            pg_credit_mail_issued($id);
        }
    }

    return $id;
}

/**
 * Void a customer's credit card: its balance becomes 0, so it is never
 * picked or counted again, and a note line keeps the reason. The balance is
 * written over the one read, so an order that spent part of the card
 * meanwhile makes the void fail instead of being undone.
 *
 * @param int    $card_id
 * @param string $reason
 * @return bool  whether the card was voided
 */
function pg_credit_void($card_id, $reason = '')
{
    $card_id = (int) $card_id;

    if (($card_id <= 0) || !pg_credit_ready()) {
        return false;
    }

    $card = db_item("SELECT id, code, user_id, kind, amount, balance, reason, issued_by, expires_at, notes FROM gift_cards WHERE id = " . $card_id);

    if (!is_array($card) || ((int) $card['user_id'] <= 0) || ((int) $card['balance'] <= 0)) {
        return false;
    }

    $reason = mb_substr(trim((string) $reason), 0, PG_CREDIT_REASON_LENGTH);
    $line = lang(array('string' => 'Store credit voided on {var:1}: {var:2}', 'vars' => array(date('Y-m-d'), $reason)));
    $notes = trim((string) $card['notes']);
    $notes = ($notes === '') ? $line : $notes . "\n" . $line;
    $user = defined('USER_ID') ? (int) USER_ID : 0;

    $written = db_q(
        "UPDATE gift_cards SET balance = 0, notes = ?, last_modified_user_id = ?, last_modified_timestamp = ? WHERE id = ? AND balance = ?",
        array($notes, $user, time(), $card_id, (int) $card['balance']));

    if (!$written || (mysqli_affected_rows(db::$con) !== 1)) {
        return false;
    }

    $after = $card;
    $after['balance'] = 0;
    $after['void_reason'] = $reason;

    pg_audit('revoke', 'store_credit', $card_id, pg_credit_audit_fields($card), pg_credit_audit_fields($after), pg_credit_audit_label($card['code']));

    return true;
}

/**
 * Take up to $amount off a customer's credit card, with a note line giving
 * the reason; a card brought to 0 is closed (never picked again). The
 * balance is written over the one read, so an order spending the card
 * meanwhile makes the write fail rather than be lost; the caller may read
 * again and retry.
 *
 * @param int    $card_id
 * @param int    $amount  minor units
 * @param string $note    the note line
 * @return int  what was taken off (0 when nothing was written)
 */
function pg_credit_reduce($card_id, $amount, $note)
{
    $card_id = (int) $card_id;
    $amount = (int) $amount;

    if (($card_id <= 0) || ($amount <= 0) || !pg_credit_ready()) {
        return 0;
    }

    $card = db_item("SELECT id, code, user_id, kind, amount, balance, reason, issued_by, expires_at, notes FROM gift_cards WHERE id = " . $card_id);

    if (!is_array($card) || ((int) $card['user_id'] <= 0) || ((int) $card['balance'] <= 0)) {
        return 0;
    }

    $cut = min($amount, (int) $card['balance']);
    $balance = (int) $card['balance'] - $cut;
    $line = mb_substr(trim((string) $note), 0, PG_CREDIT_REASON_LENGTH);
    $notes = trim((string) $card['notes']);
    $notes = ($notes === '') ? $line : $notes . "\n" . $line;
    $user = defined('USER_ID') ? (int) USER_ID : 0;

    $written = db_q(
        "UPDATE gift_cards SET balance = ?, notes = ?, last_modified_user_id = ?, last_modified_timestamp = ? WHERE id = ? AND balance = ?",
        array($balance, $notes, $user, time(), $card_id, (int) $card['balance']));

    if (!$written || (mysqli_affected_rows(db::$con) !== 1)) {
        return 0;
    }

    $after = $card;
    $after['balance'] = $balance;

    if ($balance === 0) {
        $after['void_reason'] = $line;
    }

    pg_audit(($balance === 0) ? 'revoke' : 'update', 'store_credit', $card_id, pg_credit_audit_fields($card), pg_credit_audit_fields($after), pg_credit_audit_label($card['code']));

    return $cut;
}

/**
 * Which applied_gift_cards rows a customer's credit needs on an order. Ticked
 * ($use), every open card not applied yet is added (in the order given, which
 * is pg_credit_cards()'s spend order), an applied card whose balance moved is
 * refreshed and one no longer open (spent, voided, expired) is removed;
 * unticked, every applied credit row is removed. No amount per row:
 * submit_order() takes min(total, sum of old_balance) and
 * pg_order_complete_gift_cards() redeems the rows by id.
 *
 * @param array $open_cards  pg_credit_cards() rows: id, code, balance
 * @param array $applied     this order's applied_gift_cards rows of the
 *                           customer's cards: id, gift_card_id, old_balance
 * @param bool  $use
 * @return array  insert: list of (gift_card_id, code, old_balance);
 *                delete: applied ids; refresh: applied id => balance
 */
function pg_credit_checkout_plan(array $open_cards, array $applied, $use)
{
    $plan = array('insert' => array(), 'delete' => array(), 'refresh' => array());

    $open = array();
    foreach ($open_cards as $card) {
        $open[(int) $card['id']] = $card;
    }

    $applied_cards = array();
    foreach ($applied as $row) {
        $card_id = (int) $row['gift_card_id'];

        if (!$use || !isset($open[$card_id]) || isset($applied_cards[$card_id])) {
            $plan['delete'][] = (int) $row['id'];
            continue;
        }

        $applied_cards[$card_id] = true;
        $balance = (int) $open[$card_id]['balance'];

        if ($balance !== (int) $row['old_balance']) {
            $plan['refresh'][(int) $row['id']] = $balance;
        }
    }

    if ($use) {
        foreach ($open as $card_id => $card) {
            if (!isset($applied_cards[$card_id])) {
                $plan['insert'][] = array('gift_card_id' => $card_id, 'code' => (string) $card['code'], 'old_balance' => (int) $card['balance']);
            }
        }
    }

    return $plan;
}

/**
 * The "Use my store credit" choice a form posted: null when the form did not
 * draw the box (store_credit_shown marks it), else whether it was ticked.
 *
 * @param array $post
 * @return bool|null
 */
function pg_credit_checkout_posted(array $post)
{
    if (!isset($post['store_credit_shown']) || ($post['store_credit_shown'] !== '1')) {
        return null;
    }

    return isset($post['use_store_credit']) && ($post['use_store_credit'] === '1');
}

/**
 * Whether credit is spent on an order: yes unless the customer unticked the
 * box (or removed a credit row) on this very order.
 *
 * @param int $order_id
 * @param int $off_order_id  pg_credit_checkout_off_order()
 * @return bool
 */
function pg_credit_checkout_wanted($order_id, $off_order_id)
{
    return ((int) $off_order_id === 0) || ((int) $off_order_id !== (int) $order_id);
}

/**
 * The order the customer unticked store credit on in this session, 0 for none.
 *
 * @return int
 */
function pg_credit_checkout_off_order()
{
    return isset($_SESSION['ecommerce']['store_credit_off']) ? (int) $_SESSION['ecommerce']['store_credit_off'] : 0;
}

/**
 * Records the customer's choice for an order.
 *
 * @param int  $order_id
 * @param bool $use
 */
function pg_credit_checkout_choose($order_id, $use)
{
    if ($use) {
        unset($_SESSION['ecommerce']['store_credit_off']);
    } else {
        $_SESSION['ecommerce']['store_credit_off'] = (int) $order_id;
    }
}

/**
 * The customer whose credit an order may spend, or 0: gift cards on, the
 * columns there, a signed-in customer (not a guest, not staff on someone's
 * behalf) and a line that is not a gift card (no gift card pays for gift
 * cards, the rule submit_order() keeps).
 *
 * @param int $order_id
 * @return int  user id
 */
function pg_credit_checkout_customer($order_id)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || !defined('ECOMMERCE_GIFT_CARD') || (ECOMMERCE_GIFT_CARD != true) || !pg_credit_ready()) {
        return 0;
    }

    $user_id = pg_credit_checkout_user();

    if ($user_id <= 0) {
        return 0;
    }

    $lines = (int) db_value(
        "SELECT COUNT(*)
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = " . $order_id . pg_order_item_active_sql() . "
            AND products.gift_card = '0'");

    return ($lines > 0) ? $user_id : 0;
}

/**
 * An order's applied_gift_cards rows of a customer's cards.
 *
 * @param int $order_id
 * @param int $user_id
 * @return array  rows: id, gift_card_id, old_balance
 */
function pg_credit_checkout_applied($order_id, $user_id)
{
    $rows = db_items(
        "SELECT applied_gift_cards.id, applied_gift_cards.gift_card_id, applied_gift_cards.old_balance
        FROM applied_gift_cards
        JOIN gift_cards ON gift_cards.id = applied_gift_cards.gift_card_id
        WHERE applied_gift_cards.order_id = " . (int) $order_id . "
            AND applied_gift_cards.givex = 0
            AND gift_cards.user_id = " . (int) $user_id . "
        ORDER BY applied_gift_cards.id");

    return is_array($rows) ? $rows : array();
}

/**
 * Brings an order's applied credit in line with the customer's cards and
 * choice (pg_credit_checkout_plan()). Called where the checkout totals are
 * read, so the total shown already has the credit taken off. Does nothing
 * for an order that may not spend credit.
 *
 * @param int $order_id
 * @return int  the customer's balance, 0 when the order may not spend credit
 */
function pg_credit_checkout_sync($order_id)
{
    $order_id = (int) $order_id;
    $user_id = pg_credit_checkout_customer($order_id);

    if ($user_id <= 0) {
        return 0;
    }

    // Two page views of the same order (two tabs, two devices) would both
    // read no row and both insert the card; the lock makes the read and the
    // writes one step per order. Not taken (busy for 5 s): nothing is written,
    // the other view does it, and submit_order() counts a repeat once anyway.
    $lock = 'pg_credit_checkout_' . $order_id;
    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 5)") !== 1) {
        return pg_credit_balance($user_id);
    }

    $cards = pg_credit_cards($user_id);
    $plan = pg_credit_checkout_plan(
        $cards,
        pg_credit_checkout_applied($order_id, $user_id),
        pg_credit_checkout_wanted($order_id, pg_credit_checkout_off_order()));

    foreach ($plan['delete'] as $id) {
        db_q("DELETE FROM applied_gift_cards WHERE id = ? AND order_id = ?", array((int) $id, $order_id));
    }

    foreach ($plan['refresh'] as $id => $balance) {
        db_q("UPDATE applied_gift_cards SET old_balance = ? WHERE id = ? AND order_id = ?", array((int) $balance, (int) $id, $order_id));
    }

    foreach ($plan['insert'] as $row) {
        db_q(
            "INSERT INTO applied_gift_cards (gift_card_id, order_id, code, old_balance, givex) VALUES (?, ?, ?, ?, 0)",
            array($row['gift_card_id'], $order_id, $row['code'], $row['old_balance']));
    }

    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    $balance = 0;
    foreach ($cards as $card) {
        $balance += (int) $card['balance'];
    }

    return $balance;
}

/**
 * The keys of applied_gift_cards rows that repeat a card already on the
 * order: the same gift_card_id for a card of this system, the same code for
 * a Givex card. The first row (lowest key order as given) stays.
 *
 * @param array $rows  applied_gift_cards rows: id, gift_card_id, code, givex
 * @return array  keys of $rows to remove
 */
function pg_gift_card_applied_repeats(array $rows)
{
    $seen = array();
    $repeats = array();

    foreach ($rows as $key => $row) {
        $card = ((int) $row['givex'] === 0)
            ? 'id:' . (int) $row['gift_card_id']
            : 'givex:' . (string) $row['code'];

        if (isset($seen[$card])) {
            $repeats[] = $key;
        } else {
            $seen[$card] = true;
        }
    }

    return $repeats;
}

/**
 * The "Use my store credit" box for an order, after pg_credit_checkout_sync():
 * show (a customer with credit who may spend it here), checked, balance.
 *
 * @param int $order_id
 * @param int $balance  what pg_credit_checkout_sync() returned
 * @return array  show, checked, balance (minor units)
 */
function pg_credit_checkout_box($order_id, $balance)
{
    return array(
        'show'    => ((int) $balance > 0),
        'checked' => pg_credit_checkout_wanted($order_id, pg_credit_checkout_off_order()),
        'balance' => (int) $balance,
    );
}

/**
 * The box as the built-in checkout screens draw it: a hidden marker, the
 * checkbox and an Update button that the checkbox presses, so the page comes
 * back with the new totals before anything is charged.
 *
 * @param array  $box           pg_credit_checkout_box()
 * @param string $button_class
 * @return string  HTML
 */
function pg_credit_checkout_box_html(array $box, $button_class = 'software_input_submit_small_secondary')
{
    if (empty($box['show'])) {
        return '';
    }

    return '<div class="store_credit" style="margin-bottom: 1em">'
        . '<input type="hidden" name="store_credit_shown" value="1">'
        . '<label><input type="checkbox" name="use_store_credit" value="1" class="software_input_checkbox"'
        . (!empty($box['checked']) ? ' checked' : '')
        . ' onchange="var b = this.form.elements[\'submit_store_credit\']; if (b) { b.click(); }"> '
        . h(lang('Use my store credit')) . ' (' . prepare_price_for_output($box['balance'], false, '', 'html') . ')</label> '
        . '<input type="submit" name="submit_store_credit" value="' . h(lang('Update')) . '" class="' . h($button_class) . '" formnovalidate>'
        . '</div>';
}

/**
 * Takes a posted choice of the box: when it differs from the one in force,
 * records it and updates the order's rows. True when the choice changed,
 * so a purchase is held back and the page shown again with the new totals.
 *
 * @param int   $order_id
 * @param array $post
 * @return bool
 */
function pg_credit_checkout_take_post($order_id, array $post)
{
    $choice = pg_credit_checkout_posted($post);

    if (($choice === null) || ($choice === pg_credit_checkout_wanted($order_id, pg_credit_checkout_off_order()))) {
        return false;
    }

    pg_credit_checkout_choose($order_id, $choice);
    pg_credit_checkout_sync($order_id);

    return true;
}

/**
 * Called when a customer removes an applied card from the order: removing
 * their own credit counts as unticking the box, or the next page view would
 * apply it again.
 *
 * @param int $applied_gift_card_id
 * @param int $order_id
 */
function pg_credit_checkout_removed($applied_gift_card_id, $order_id)
{
    $user_id = pg_credit_checkout_user();

    if (($user_id <= 0) || ((int) $order_id <= 0) || !pg_credit_ready()) {
        return;
    }

    $owner = (int) db_value(
        "SELECT gift_cards.user_id
        FROM applied_gift_cards
        JOIN gift_cards ON gift_cards.id = applied_gift_cards.gift_card_id
        WHERE applied_gift_cards.id = " . (int) $applied_gift_card_id . "
            AND applied_gift_cards.order_id = " . (int) $order_id . "
            AND applied_gift_cards.givex = 0");

    if (($owner > 0) && ($owner === $user_id)) {
        pg_credit_checkout_choose($order_id, false);
    }
}

/**
 * The ids of an order's applied_gift_cards rows that are store credit, so a
 * receipt or order screen names them "Store credit" instead of a code the
 * customer never saw.
 *
 * @param int $order_id
 * @return array  applied id => true
 */
function pg_credit_applied_ids($order_id)
{
    if (((int) $order_id <= 0) || !pg_credit_ready()) {
        return array();
    }

    $ids = array();
    $rows = db_items(
        "SELECT applied_gift_cards.id
        FROM applied_gift_cards
        JOIN gift_cards ON gift_cards.id = applied_gift_cards.gift_card_id
        WHERE applied_gift_cards.order_id = " . (int) $order_id . "
            AND applied_gift_cards.givex = 0
            AND gift_cards.user_id > 0");

    foreach ((is_array($rows) ? $rows : array()) as $row) {
        $ids[(int) $row['id']] = true;
    }

    return $ids;
}

/**
 * How an applied card is named on a checkout, receipt or order screen:
 * "Store credit" for a customer's credit, the masked code otherwise.
 *
 * @param array $row         applied_gift_cards row: id, code, givex
 * @param array $credit_ids  pg_credit_applied_ids()
 * @return string  plain text
 */
function pg_credit_applied_name(array $row, array $credit_ids)
{
    if (isset($credit_ids[(int) $row['id']])) {
        return lang('Store credit');
    }

    return ((int) $row['givex'] === 0) ? protect_gift_card_code($row['code']) : protect_givex_gift_card_code($row['code']);
}
