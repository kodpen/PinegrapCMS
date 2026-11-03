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
