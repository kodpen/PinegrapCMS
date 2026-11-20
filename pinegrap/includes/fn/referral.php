<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The referral programme (2026.11.0): a customer brings a friend through
 * their own link, on the affiliate link's rails (?a=, the visit, the
 * marketing cookie, orders.affiliate_code). The code carries the
 * referrer's contact and a signature made with the site's key, so it is
 * checked without a table. When the friend's order is completed and passes
 * every check (pg_referral_check()), a commissions row is written under the
 * code, status paid, and the reward is issued as store credit of kind
 * 'referral'. A cancel takes the reward back, a restore gives it again, and
 * a refund takes it back when what is left of the order falls under the
 * smallest order that counts.
 *
 * Settings, each a config_text row (the settings card writes them):
 * referral_on ('1' = on), referral_offer_id (the friend's offer),
 * referral_reward (minor units), referral_reward_days (0 = never expires),
 * referral_smallest (minor units), referral_month_cap (0 = no limit).
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
 * A referral code: R, the contact id in base 36, a dash, ten base-36
 * characters of the signature. An affiliate code from
 * generate_affiliate_code() is five characters without a dash, so never
 * this shape. MySQL's REGEXP and preg_match() read it alike.
 */
define('PG_REFERRAL_CODE_PATTERN', '^R[0-9A-Z]{1,7}-[0-9A-Z]{10}$');

/** How long a reward waits for its referrer's month (seconds). */
define('PG_REFERRAL_LOCK_WAIT', 10);

/**
 * The signature part of a contact's code: the first 48 bits of an HMAC
 * with the site's key, in base 36, ten characters. Pure.
 *
 * @param int    $contact_id
 * @param string $key
 * @return string
 */
function pg_referral_code_sign($contact_id, $key)
{
    $hash = hash_hmac('sha256', 'referral|' . (int) $contact_id, (string) $key);
    $value = (string) base_convert(substr($hash, 0, 12), 16, 36);

    return strtoupper(str_pad(substr($value, -10), 10, '0', STR_PAD_LEFT));
}

/**
 * A contact's referral code; '' without a contact or a site key. Pure.
 *
 * @param int    $contact_id
 * @param string $key
 * @return string
 */
function pg_referral_code_make($contact_id, $key)
{
    $contact_id = (int) $contact_id;

    if (($contact_id <= 0) || ((string) $key === '')) {
        return '';
    }

    return 'R' . strtoupper(base_convert((string) $contact_id, 10, 36)) . '-' . pg_referral_code_sign($contact_id, $key);
}

/**
 * Whether a code has the referral shape. This is the one test that tells a
 * referral's commission row from an affiliate's; pg_referral_row_sql() is
 * its SQL twin. Pure.
 *
 * @param mixed $code
 * @return bool
 */
function pg_referral_code_shaped($code)
{
    return is_string($code) && (preg_match('/' . PG_REFERRAL_CODE_PATTERN . '/i', trim($code)) === 1);
}

/**
 * The contact a referral code names when its signature holds, else 0. Pure.
 *
 * @param mixed  $code
 * @param string $key
 * @return int
 */
function pg_referral_code_contact($code, $key)
{
    if (!pg_referral_code_shaped($code) || ((string) $key === '')) {
        return 0;
    }

    $code = strtoupper(trim((string) $code));
    list($number, $signature) = explode('-', substr($code, 1), 2);
    $contact_id = (int) base_convert($number, 36, 10);

    // Only the spelling pg_referral_code_make() writes: a zero before the
    // number reads as the same contact, but the month's room counts rows by
    // the exact code, so another spelling would have a month of its own.
    if (($contact_id <= 0) || ($number !== strtoupper(base_convert((string) $contact_id, 10, 36))) || !hash_equals(pg_referral_code_sign($contact_id, $key), $signature)) {
        return 0;
    }

    return $contact_id;
}

/**
 * What a commission row is: 'referral' for a referral code, 'affiliate'
 * otherwise. Read by the Commissions screen, Edit Commission, the hand
 * save and GET /commissions. Pure.
 *
 * @param mixed $code commissions.affiliate_code
 * @return string
 */
function pg_referral_commission_kind($code)
{
    return pg_referral_code_shaped($code) ? 'referral' : 'affiliate';
}

/**
 * The SQL condition that a code column holds a referral code (or, with
 * $is false, does not). Pure.
 *
 * @param string $column
 * @param bool   $is
 * @return string
 */
function pg_referral_row_sql($column, $is = true)
{
    return '(' . $column . ($is ? '' : ' NOT') . " REGEXP '" . PG_REFERRAL_CODE_PATTERN . "')";
}

/**
 * An e-mail address folded for the "not the referrer" check: trimmed, lower
 * case, without a +tag, and for Gmail without dots. '' for no address. Pure.
 *
 * @param mixed $email
 * @return string
 */
function pg_referral_fold_email($email)
{
    $email = function_exists('mb_strtolower') ? mb_strtolower(trim((string) $email), 'UTF-8') : strtolower(trim((string) $email));
    $at = strrpos($email, '@');

    if (($at === false) || ($at === 0) || (strpos($email, ' ') !== false)) {
        return '';
    }

    $local = substr($email, 0, $at);
    $domain = substr($email, $at + 1);

    if (($plus = strpos($local, '+')) !== false) {
        $local = substr($local, 0, $plus);
    }

    if (($domain === 'gmail.com') || ($domain === 'googlemail.com')) {
        $local = str_replace('.', '', $local);
        $domain = 'gmail.com';
    }

    return ($local === '') ? '' : $local . '@' . $domain;
}

/**
 * The first moment of the month $now is in, in the site's time zone. Pure.
 *
 * @param int $now
 * @return int
 */
function pg_referral_month_start($now)
{
    return (int) mktime(0, 0, 0, (int) date('n', (int) $now), 1, (int) date('Y', (int) $now));
}

/**
 * What an order is worth for the smallest order that counts: the goods,
 * tax and shipping less the discount, however they were paid (a gift card
 * or store credit included), without the card surcharge, less everything
 * refunded so far. Never below zero. Pure.
 *
 * @param array $order    total, gift_card_discount, surcharge (minor units)
 * @param int   $refunded minor units
 * @return int
 */
function pg_referral_order_value(array $order, $refunded)
{
    $value = (int) ($order['total'] ?? 0) + (int) ($order['gift_card_discount'] ?? 0) - (int) ($order['surcharge'] ?? 0) - (int) $refunded;

    return max(0, $value);
}

/**
 * Whether what is left of an order after a refund takes its reward back:
 * under the smallest order that counts, or nothing left at all. Pure.
 *
 * @param int $value    pg_referral_order_value() after the refund
 * @param int $smallest minor units
 * @return bool
 */
function pg_referral_refund_takes_back($value, $smallest)
{
    return ((int) $value <= 0) || ((int) $value < (int) $smallest);
}

/**
 * Every check a friend's completed order passes before its reward, in
 * order; the first that fails is returned, '' when all hold. Pure.
 *
 * @param array $facts on (bool), contact_id (from the signed code, 0 for
 *                     none), affiliate (an approved affiliate has the code),
 *                     referrer (user_id, contact_id, email,
 *                     affiliate_approved, orders; null without an account),
 *                     buyer (user_id, contact_id, email), friend_orders
 *                     (earlier orders that count), value
 *                     (pg_referral_order_value()), smallest, month_count
 *                     (rewards of the referrer this month), month_cap
 *                     (0 = no limit)
 * @return string '', off, code, affiliate, referrer, self, friend,
 *                smallest or month
 */
function pg_referral_check(array $facts)
{
    if (empty($facts['on'])) {
        return 'off';
    }

    if ((int) ($facts['contact_id'] ?? 0) <= 0) {
        return 'code';
    }

    if (!empty($facts['affiliate'])) {
        return 'affiliate';
    }

    $referrer = $facts['referrer'] ?? null;

    if (!is_array($referrer) || ((int) $referrer['user_id'] <= 0) || ((int) $referrer['affiliate_approved'] === 1) || ((int) $referrer['orders'] <= 0)) {
        return 'referrer';
    }

    $buyer = (array) ($facts['buyer'] ?? array());
    $buyer_email = pg_referral_fold_email($buyer['email'] ?? '');

    if (
        (((int) ($buyer['user_id'] ?? 0) > 0) && ((int) $buyer['user_id'] === (int) $referrer['user_id']))
        || (((int) ($buyer['contact_id'] ?? 0) > 0) && ((int) $buyer['contact_id'] === (int) $referrer['contact_id']))
        || (($buyer_email !== '') && ($buyer_email === pg_referral_fold_email($referrer['email'] ?? '')))
    ) {
        return 'self';
    }

    if ((int) ($facts['friend_orders'] ?? 0) > 0) {
        return 'friend';
    }

    if (pg_referral_refund_takes_back((int) ($facts['value'] ?? 0), (int) ($facts['smallest'] ?? 0))) {
        return 'smallest';
    }

    if (((int) ($facts['month_cap'] ?? 0) > 0) && ((int) ($facts['month_count'] ?? 0) >= (int) $facts['month_cap'])) {
        return 'month';
    }

    return '';
}

/**
 * The first line of the notes of a reward's card: it ties the card to the
 * friend's order, which is how the take-back finds it. Not translated: it
 * is read back. Pure.
 *
 * @param int $order_id
 * @return string
 */
function pg_referral_card_marker($order_id)
{
    return 'Referral reward of order ' . (int) $order_id;
}

/**
 * Whether a card's notes start with the order's marker line. Pure.
 *
 * @param mixed $notes
 * @param int   $order_id
 * @return bool
 */
function pg_referral_card_marked($notes, $order_id)
{
    $lines = explode("\n", str_replace("\r", '', (string) $notes));

    return trim($lines[0]) === pg_referral_card_marker($order_id);
}

/**
 * How a reward of $amount comes off its cards: newest card first, as much
 * as each still holds; what none holds any more was spent. Pure.
 *
 * @param array $cards  id, balance
 * @param int   $amount minor units
 * @return array cuts (card id => minor units), spent (minor units)
 */
function pg_referral_take_back_plan(array $cards, $amount)
{
    $cuts = pg_credit_points_card_cuts($cards, (int) $amount);

    return array('cuts' => $cuts, 'spent' => max(0, (int) $amount - (int) array_sum($cuts)));
}

/**
 * The programme's settings.
 *
 * @return array on (bool), offer_id, reward, days, smallest, month_cap
 */
function pg_referral_settings()
{
    $number = function ($name) {
        $value = trim(pg_config_text($name));

        return ctype_digit($value) ? (int) $value : 0;
    };

    $reward = $number('referral_reward');

    return array(
        'on'        => (pg_config_text('referral_on') === '1') && ($reward > 0) && defined('ECOMMERCE') && ECOMMERCE,
        'offer_id'  => $number('referral_offer_id'),
        'reward'    => $reward,
        'days'      => $number('referral_reward_days'),
        'smallest'  => $number('referral_smallest'),
        'month_cap' => $number('referral_month_cap'),
    );
}

/**
 * Whether the data can hold a reward: store credit is there, its kind
 * column has 'referral' (11.0.245) and a commission keeps the status a
 * cancel took. Files land before the upgrade runs.
 *
 * @return bool
 */
function pg_referral_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = false;

        if (pg_credit_ready() && pg_schema_has('commissions', 'status_before_cancel')) {
            $kind = db_item("SHOW COLUMNS FROM gift_cards WHERE Field = 'kind'");
            $ready = is_array($kind) && (stripos((string) ($kind['Type'] ?? ''), "'referral'") !== false);
        }
    }

    return $ready;
}

/**
 * The site's key for referral codes, '' when the site has none.
 *
 * @return string
 */
function pg_referral_key()
{
    return defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
}

/**
 * A contact's referral code with the site's key.
 *
 * @param int $contact_id
 * @return string
 */
function pg_referral_code_for_contact($contact_id)
{
    return pg_referral_code_make($contact_id, pg_referral_key());
}

/**
 * Whether an approved affiliate holds the code: then it is that affiliate's
 * code, whatever its shape.
 *
 * @param string $code
 * @return bool
 */
function pg_referral_code_is_affiliate($code)
{
    return (string) $code !== ''
        && (int) db_value("SELECT COUNT(*) FROM contacts WHERE (affiliate_code = '" . e((string) $code) . "') AND (affiliate_approved = 1)") > 0;
}

/**
 * The referrer behind a contact: its account, e-mail, whether it is an
 * approved affiliate and how many of its orders count (completed, not
 * cancelled). Null when the contact has no account.
 *
 * @param int $contact_id
 * @param int $order_id   the friend's order, never counted
 * @return array|null
 */
function pg_referral_referrer($contact_id, $order_id = 0)
{
    $row = db_item(
        "SELECT contacts.id AS contact_id, contacts.email_address, contacts.affiliate_approved, user.user_id, user.user_email
        FROM contacts
        JOIN user ON user.user_contact = contacts.id
        WHERE contacts.id = '" . (int) $contact_id . "'
        ORDER BY user.user_id ASC
        LIMIT 1");

    if (!is_array($row) || ((int) $row['user_id'] <= 0)) {
        return null;
    }

    return array(
        'user_id'            => (int) $row['user_id'],
        'contact_id'         => (int) $row['contact_id'],
        'email'              => ((string) $row['user_email'] !== '') ? (string) $row['user_email'] : (string) $row['email_address'],
        'affiliate_approved' => (int) $row['affiliate_approved'],
        'orders'             => (int) db_value(
            "SELECT COUNT(*) FROM orders
            WHERE ((user_id = '" . (int) $row['user_id'] . "') OR (contact_id = '" . (int) $row['contact_id'] . "'))
                AND (status IN ('complete', 'exported'))
                AND (id <> '" . (int) $order_id . "')"),
    );
}

/**
 * The condition that finds the friend's other orders that count: by
 * account, else by the billing e-mail; '' for a guest without an e-mail.
 * Every other completed order counts, not only those of a lower id: of two
 * orders by the same link, the one placed first may be completed last.
 *
 * @param array $order id, user_id, billing_email_address
 * @return string
 */
function pg_referral_friend_orders_sql(array $order)
{
    if ((int) $order['user_id'] > 0) {
        $who = "user_id = '" . (int) $order['user_id'] . "'";
    } elseif (trim((string) $order['billing_email_address']) !== '') {
        $who = "LOWER(TRIM(billing_email_address)) = '" . e(strtolower(trim((string) $order['billing_email_address']))) . "'";
    } else {
        return '';
    }

    return "(" . $who . ") AND (status IN ('complete', 'exported')) AND (id <> '" . (int) $order['id'] . "')";
}

/**
 * How many other orders that count the friend has
 * (pg_referral_friend_orders_sql()).
 *
 * @param array $order id, user_id, billing_email_address
 * @return int
 */
function pg_referral_friend_orders(array $order)
{
    $where = pg_referral_friend_orders_sql($order);

    return ($where === '') ? 0 : (int) db_value("SELECT COUNT(*) FROM orders WHERE " . $where);
}

/**
 * Everything refunded on an order so far: the refund rows (payment, gift
 * card and store credit parts), at least the payment total the order keeps.
 *
 * @param int $order_id
 * @return int minor units
 */
function pg_referral_refunded_total($order_id)
{
    $order_id = (int) $order_id;
    $rows = pg_schema_has('order_refunds') ? (int) db_value("SELECT COALESCE(SUM(amount_cents), 0) FROM order_refunds WHERE order_id = '" . $order_id . "'") : 0;
    $amount = pg_schema_has('orders', 'refunded_amount') ? (int) db_value("SELECT COALESCE(refunded_amount, 0) FROM orders WHERE id = '" . $order_id . "'") : 0;

    return max($rows, $amount);
}

/**
 * The order's referral row, if any.
 *
 * @param int $order_id
 * @return array|null id, affiliate_code, amount, status, status_before_cancel
 */
function pg_referral_order_row($order_id)
{
    $row = db_item(
        "SELECT id, affiliate_code, amount, status, status_before_cancel FROM commissions
        WHERE (order_id = '" . (int) $order_id . "') AND " . pg_referral_row_sql('affiliate_code') . "
        ORDER BY id ASC
        LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Issue a reward's store credit, its card marked with the friend's order.
 *
 * @param int   $user_id  the referrer's account
 * @param int   $amount   minor units
 * @param array $order    id, order_number
 * @param int   $days     0 = never expires
 * @return int the card id, 0 when not written
 */
function pg_referral_issue_credit($user_id, $amount, array $order, $days)
{
    $expires = ((int) $days > 0) ? time() + ((int) $days * 86400) : 0;
    $reason = lang(array('string' => 'Referral: order #{var:1}', 'vars' => array(pg_order_log_number($order))));
    $card_id = pg_credit_issue((int) $user_id, (int) $amount, 'referral', $reason, $expires, 0);

    if ($card_id > 0) {
        db_q("UPDATE gift_cards SET notes = ? WHERE id = ?", array(pg_referral_card_marker($order['id']), $card_id));
    }

    return $card_id;
}

/**
 * The reward of a friend's order, called at completion beside the
 * affiliate's commission when the order earned none. Every check of
 * pg_referral_check() is made under a named lock per referrer, so two
 * orders completing at once cannot both take the month's last room, nor
 * one order be rewarded twice. On success: a commissions row under the
 * code with the reward as its amount, status paid, and the credit.
 *
 * @param int    $order_id
 * @param string $code     the order's affiliate_code
 * @return string pg_referral_check()'s answer, or 'credit' / 'busy' / 'done'
 */
function pg_referral_order_completed($order_id, $code)
{
    $order_id = (int) $order_id;
    $code = strtoupper(trim((string) $code));

    // The card off, or a code that is not a referral's: nothing, as before.
    if (!pg_referral_code_shaped($code)) {
        return 'code';
    }

    $settings = pg_referral_settings();

    if (!$settings['on'] || !pg_referral_ready()) {
        return 'off';
    }

    $contact_id = pg_referral_code_contact($code, pg_referral_key());

    if ($contact_id <= 0) {
        return 'code';
    }

    $order = db_item("SELECT id, order_number, user_id, contact_id, billing_email_address, total, gift_card_discount, surcharge, status FROM orders WHERE id = '" . $order_id . "'");

    if (!is_array($order) || !in_array((string) $order['status'], array('complete', 'exported'), true)) {
        return 'code';
    }

    $lock = 'pg_referral_' . $contact_id;

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', " . PG_REFERRAL_LOCK_WAIT . ")") !== 1) {
        log_activity(pg_log_lang(array('string' => 'The referral reward of order #{var:1} was not given: its referrer\'s rewards were busy.', 'vars' => array(pg_order_log_number($order)))));

        return 'busy';
    }

    $reward = null;
    $answer = pg_referral_order_reward($order, $code, $contact_id, $settings, $reward);

    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    // The referrer's mail goes after the lock: a mail sent inline when the
    // queue is down must not hold the referrer's other orders.
    if ($answer === '') {
        pg_referral_mail_reward($contact_id, $settings['reward'], $settings['days']);
    }
    // Announced once the lock is free, once per reward: a restore's new
    // card is announced only as credit.issued.
    if (($answer === '') && is_array($reward)) {
        pg_announce('referral.rewarded', pg_referral_rewarded_event($reward));
    }

    return $answer;
}

/**
 * What referral.rewarded carries: the referral's commission row, the
 * friend's order, the reward, the referrer's contact and account and the
 * store credit card it was given on. Pure.
 *
 * @param array $reward id, reference_code, affiliate_code, order_id, amount, contact_id, user_id, card_id
 * @return array
 */
function pg_referral_rewarded_event(array $reward)
{
    return array(
        'id'             => (int) ($reward['id'] ?? 0),
        'reference_code' => (string) ($reward['reference_code'] ?? ''),
        'affiliate_code' => (string) ($reward['affiliate_code'] ?? ''),
        'order_id'       => (int) ($reward['order_id'] ?? 0),
        'amount'         => (int) ($reward['amount'] ?? 0),
        'customer_id'    => (int) ($reward['contact_id'] ?? 0),
        'account_id'     => (int) ($reward['user_id'] ?? 0),
        'card_id'        => (int) ($reward['card_id'] ?? 0),
    );
}

/**
 * The checks and the writes of pg_referral_order_completed(), under its
 * lock.
 *
 * @param array  $order
 * @param string $code
 * @param int    $contact_id
 * @param array  $settings pg_referral_settings()
 * @param array|null $reward   set to what referral.rewarded carries once given
 * @return string
 */
function pg_referral_order_reward(array $order, $code, $contact_id, array $settings, &$reward = null)
{
    if (pg_referral_order_row($order['id']) !== null) {
        return 'done';
    }

    $referrer = pg_referral_referrer($contact_id, (int) $order['id']);

    $answer = pg_referral_check(array(
        'on'            => true,
        'contact_id'    => $contact_id,
        'affiliate'     => pg_referral_code_is_affiliate($code),
        'referrer'      => $referrer,
        'buyer'         => array(
            'user_id'    => pg_order_rights_user_id($order),
            'contact_id' => (int) $order['contact_id'],
            'email'      => (string) $order['billing_email_address'],
        ),
        'friend_orders' => pg_referral_friend_orders($order),
        'value'         => pg_referral_order_value($order, pg_referral_refunded_total($order['id'])),
        'smallest'      => $settings['smallest'],
        'month_count'   => (int) db_value(
            "SELECT COUNT(*) FROM commissions
            WHERE (affiliate_code = '" . e($code) . "') AND (created_timestamp >= '" . pg_referral_month_start(time()) . "')"),
        'month_cap'     => $settings['month_cap'],
    ));

    if ($answer !== '') {
        return $answer;
    }

    $card_id = pg_referral_issue_credit($referrer['user_id'], $settings['reward'], $order, $settings['days']);

    if ($card_id <= 0) {
        return 'credit';
    }

    $reference_code = (string) generate_commission_reference_code();

    db_q(
        "INSERT INTO commissions (reference_code, affiliate_code, order_id, amount, status, created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
        VALUES (?, ?, ?, ?, 'paid', 0, UNIX_TIMESTAMP(), 0, UNIX_TIMESTAMP())",
        array($reference_code, $code, (string) (int) $order['id'], (string) (int) $settings['reward']));

    $reward = array(
        'id'             => (int) mysqli_insert_id(db::$con),
        'reference_code' => $reference_code,
        'affiliate_code' => $code,
        'order_id'       => (int) $order['id'],
        'amount'         => (int) $settings['reward'],
        'contact_id'     => (int) $contact_id,
        'user_id'        => (int) $referrer['user_id'],
        'card_id'        => (int) $card_id,
    );

    log_activity(pg_log_lang(array(
        'string' => 'Referral reward of {var:1} given as store credit for order #{var:2}.',
        'vars'   => array(pg_order_log_money($settings['reward']), pg_order_log_number($order)))));

    return '';
}

/**
 * Take an order's reward back: its row goes from paid to ineligible (one
 * guarded write, so a second cancel or refund takes nothing), then the
 * reward comes off the order's credit cards, newest first; what was spent
 * already cannot be taken and is noted on the card and in the log.
 *
 * @param int    $order_id
 * @param string $why      the note's reason
 * @return array taken, spent (minor units); both 0 when nothing was taken
 */
function pg_referral_take_back($order_id, $why)
{
    $none = array('taken' => 0, 'spent' => 0);
    $row = pg_referral_order_row($order_id);

    if (($row === null) || ((string) $row['status'] !== 'paid') || !pg_referral_ready()) {
        return $none;
    }

    db("UPDATE commissions SET
            status_before_cancel    = status,
            status                  = 'ineligible',
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $row['id'] . "' AND status = 'paid'");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return $none;
    }

    $amount = (int) $row['amount'];
    $referrer = pg_referral_referrer(pg_referral_code_contact($row['affiliate_code'], pg_referral_key()), (int) $order_id);
    $cards = array();

    if ($referrer !== null) {
        foreach ((array) db_items("SELECT id, balance, notes FROM gift_cards WHERE user_id = '" . (int) $referrer['user_id'] . "' AND kind = 'referral' ORDER BY id DESC") as $card) {
            if (pg_referral_card_marked($card['notes'], $order_id)) {
                $cards[] = $card;
            }
        }
    }

    $order = db_item("SELECT id, order_number FROM orders WHERE id = '" . (int) $order_id . "'");
    $order_number = pg_order_log_number(is_array($order) ? $order : array('id' => $order_id));
    $plan = pg_referral_take_back_plan($cards, $amount);
    $taken = 0;
    $note = lang(array(
        'string' => 'Referral reward of order #{var:1} taken back on {var:2}: {var:3}.',
        'vars'   => array($order_number, date('Y-m-d'), $why)));

    if ($plan['spent'] > 0) {
        $note .= ' ' . lang(array('string' => '{var:1} had been spent and could not be taken back.', 'vars' => array(pg_order_log_money($plan['spent']))));
    }

    foreach ($plan['cuts'] as $card_id => $cut) {
        $taken += pg_credit_reduce($card_id, $cut, $note);
    }

    // A card already spent to 0 still gets the note.
    if (($taken === 0) && !empty($cards)) {
        db_q("UPDATE gift_cards SET notes = CONCAT(notes, ?) WHERE id = ?", array("\n" . $note, (int) $cards[0]['id']));
    }

    log_activity(pg_log_lang(array(
        'string' => 'Referral reward of order #{var:1} taken back: {var:2} off the store credit, {var:3} already spent.',
        'vars'   => array($order_number, pg_order_log_money($taken), pg_order_log_money($amount - $taken)))));

    return array('taken' => $taken, 'spent' => $amount - $taken);
}

/**
 * A cancelled order (a full refund included) takes its whole reward back.
 *
 * @param int $order_id
 * @return array pg_referral_take_back()'s
 */
function pg_referral_order_cancelled($order_id)
{
    return pg_referral_take_back($order_id, lang('the order was cancelled'));
}

/**
 * After a refund the order's value is worked out again, less everything
 * refunded so far; under the smallest order that counts (as the setting
 * reads now), or with nothing left, the whole reward is taken back. A
 * refund never gives a reward back.
 *
 * @param int $order_id
 * @return array pg_referral_take_back()'s
 */
function pg_referral_order_refunded($order_id)
{
    $none = array('taken' => 0, 'spent' => 0);
    $row = pg_referral_order_row($order_id);

    if (($row === null) || ((string) $row['status'] !== 'paid')) {
        return $none;
    }

    $order = db_item("SELECT total, gift_card_discount, surcharge FROM orders WHERE id = '" . (int) $order_id . "'");

    if (!is_array($order)) {
        return $none;
    }

    $value = pg_referral_order_value($order, pg_referral_refunded_total($order_id));

    if (!pg_referral_refund_takes_back($value, pg_referral_settings()['smallest'])) {
        return $none;
    }

    return pg_referral_take_back($order_id, lang('the order was refunded'));
}

/**
 * A restored order gives its reward again, as new credit, when the
 * cancel took it and what is left of the order still counts. A reward a
 * refund took stays taken while the order is under the smallest order.
 *
 * @param int $order_id
 * @return int the new card id, 0 when nothing was given
 */
function pg_referral_order_restored($order_id)
{
    $row = pg_referral_order_row($order_id);

    if (($row === null) || ((string) $row['status'] !== 'ineligible') || ((string) $row['status_before_cancel'] !== 'paid') || !pg_referral_ready()) {
        return 0;
    }

    $order = db_item("SELECT id, order_number, total, gift_card_discount, surcharge FROM orders WHERE id = '" . (int) $order_id . "'");
    $referrer = pg_referral_referrer(pg_referral_code_contact($row['affiliate_code'], pg_referral_key()), (int) $order_id);

    if (!is_array($order) || ($referrer === null)
        || pg_referral_refund_takes_back(pg_referral_order_value($order, pg_referral_refunded_total($order_id)), pg_referral_settings()['smallest'])) {
        return 0;
    }

    db("UPDATE commissions SET
            status                  = 'paid',
            status_before_cancel    = '',
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $row['id'] . "' AND status = 'ineligible' AND status_before_cancel = 'paid'");

    if (mysqli_affected_rows(db::$con) !== 1) {
        return 0;
    }

    $card_id = pg_referral_issue_credit($referrer['user_id'], (int) $row['amount'], $order, pg_referral_settings()['days']);

    log_activity(pg_log_lang(array(
        'string' => 'Referral reward of {var:1} given again for restored order #{var:2}.',
        'vars'   => array(pg_order_log_money($row['amount']), pg_order_log_number($order)))));

    return $card_id;
}

/**
 * What a referral link does on arrival, beside what an affiliate link
 * does (init_tracking() keeps the code for the visit and the cookie): with
 * visitor tracking off the code is kept for the visit only, and the
 * friend's offer is put in the cart as an affiliate's group offer is,
 * when it runs today and no offer code is there yet. Nothing names the
 * referrer to the friend.
 *
 * @param mixed $code ?a=
 * @return void
 */
function pg_referral_arrival($code)
{
    if (!pg_referral_code_shaped($code)) {
        return;
    }

    $settings = pg_referral_settings();
    $code = strtoupper(trim((string) $code));

    if (!$settings['on'] || (pg_referral_code_contact($code, pg_referral_key()) <= 0) || pg_referral_code_is_affiliate($code)) {
        return;
    }

    if (!(defined('VISITOR_TRACKING') && VISITOR_TRACKING)) {
        $_SESSION['software']['affiliate_code'] = $code;
    }

    if (($settings['offer_id'] > 0) && ((string) ($_SESSION['ecommerce']['special_offer_code'] ?? '') === '')) {
        $offer_code = (string) db_value("SELECT code FROM offers WHERE id = '" . (int) $settings['offer_id'] . "'");

        if (($offer_code !== '') && pg_special_offer_code_running($offer_code)) {
            $_SESSION['ecommerce']['special_offer_code'] = $offer_code;
        }
    }
}

/** The longest a reward may last, in days (ten years). */
define('PG_REFERRAL_DAYS_MAX', 3650);

/** The most rewards one customer may earn in a month. */
define('PG_REFERRAL_MONTH_CAP_MAX', 1000);

/** An amount's upper bound in minor units: gift_cards.amount is INT UNSIGNED. */
define('PG_REFERRAL_AMOUNT_MAX', 4294967295);

/**
 * An amount box of the settings card as minor units. Pure but for the
 * panel's number separators pg_parse_amount() reads.
 *
 * @param mixed $raw
 * @return int|null minor units, 0 for an empty box, null when not an
 *                  amount (letters, a minus sign, above the column)
 */
function pg_referral_settings_amount($raw)
{
    if (!is_scalar($raw)) {
        return null;
    }

    $raw = trim(str_replace("\u{00A0}", ' ', (string) $raw));

    if ($raw === '') {
        return 0;
    }

    if (!preg_match('/^[0-9][0-9 .,]*$/', $raw)) {
        return null;
    }

    $minor = round(pg_parse_amount($raw) * 100);

    return (($minor < 0) || ($minor > PG_REFERRAL_AMOUNT_MAX)) ? null : (int) $minor;
}

/**
 * A whole-number box of the settings card: empty is 0.
 *
 * @param mixed $raw
 * @param int   $max
 * @return int|null null when not a whole number from 0 to $max
 */
function pg_referral_settings_whole($raw, $max)
{
    if (!is_scalar($raw)) {
        return null;
    }

    $raw = trim((string) $raw);

    if ($raw === '') {
        return 0;
    }

    if (!ctype_digit($raw) || (strlen($raw) > 10) || ((int) $raw > (int) $max)) {
        return null;
    }

    return (int) $raw;
}

/**
 * The settings card's post checked, before anything is written. Pure: the
 * offer's existence is asked of $offer_exists.
 *
 * The stored values are the config_text rows pg_referral_settings() reads,
 * as strings ('' for off or none). With the switch off the other values
 * are still checked and kept, so turning it on again finds them.
 *
 * @param array    $post         referral_on, referral_offer_id,
 *                               referral_reward, referral_reward_days,
 *                               referral_smallest, referral_month_cap
 * @param callable $offer_exists function (int $offer_id): bool
 * @return array values (config_text name => string), errors (field =>
 *               'reward', 'reward_needed', 'days', 'smallest',
 *               'month_cap' or 'offer'); values is empty when errors is not
 */
function pg_referral_settings_check(array $post, $offer_exists)
{
    $errors = array();
    $on = isset($post['referral_on']) && ((string) $post['referral_on'] === '1');

    $reward = pg_referral_settings_amount($post['referral_reward'] ?? '');

    if ($reward === null) {
        $errors['referral_reward'] = 'reward';
    } elseif ($on && ($reward === 0)) {
        $errors['referral_reward'] = 'reward_needed';
    }

    $days = pg_referral_settings_whole($post['referral_reward_days'] ?? '', PG_REFERRAL_DAYS_MAX);

    if ($days === null) {
        $errors['referral_reward_days'] = 'days';
    }

    $smallest = pg_referral_settings_amount($post['referral_smallest'] ?? '');

    if ($smallest === null) {
        $errors['referral_smallest'] = 'smallest';
    }

    $month_cap = pg_referral_settings_whole($post['referral_month_cap'] ?? '', PG_REFERRAL_MONTH_CAP_MAX);

    if ($month_cap === null) {
        $errors['referral_month_cap'] = 'month_cap';
    }

    $offer_raw = trim((string) (is_scalar($post['referral_offer_id'] ?? '') ? ($post['referral_offer_id'] ?? '') : 'x'));
    $offer_id = 0;

    if ($offer_raw !== '') {
        if (!ctype_digit($offer_raw) || (strlen($offer_raw) > 10) || ((int) $offer_raw <= 0) || !call_user_func($offer_exists, (int) $offer_raw)) {
            $errors['referral_offer_id'] = 'offer';
        } else {
            $offer_id = (int) $offer_raw;
        }
    }

    if ($errors) {
        return array('values' => array(), 'errors' => $errors);
    }

    $text = function ($number) {
        return ($number > 0) ? (string) $number : '';
    };

    return array(
        'values' => array(
            'referral_on'          => $on ? '1' : '',
            'referral_offer_id'    => $text($offer_id),
            'referral_reward'      => $text($reward),
            'referral_reward_days' => $text($days),
            'referral_smallest'    => $text($smallest),
            'referral_month_cap'   => $text($month_cap),
        ),
        'errors' => array(),
    );
}

/**
 * What the settings card says for each refusal of
 * pg_referral_settings_check().
 *
 * @return array code => sentence
 */
function pg_referral_settings_errors()
{
    return array(
        'reward'        => lang('Type the reward as an amount, such as 50 or 12.50.'),
        'reward_needed' => lang('Type a reward above zero to turn the referral programme on.'),
        'days'          => lang(array('string' => 'Type a whole number of days from 0 to {var:1}; 0 means the credit never expires.', 'vars' => array(PG_REFERRAL_DAYS_MAX))),
        'smallest'      => lang('Type the smallest order as an amount, or leave it empty.'),
        'month_cap'     => lang(array('string' => 'Type a whole number from 0 to {var:1}; 0 means no limit.', 'vars' => array(PG_REFERRAL_MONTH_CAP_MAX))),
        'offer'         => lang('Choose an offer that exists, or none.'),
    );
}

/**
 * The stored settings as the card's boxes show them: amounts with the
 * panel's decimal mark, an empty box for 0.
 *
 * @return array on (bool), offer_id (int), reward, days, smallest,
 *               month_cap (strings)
 */
function pg_referral_settings_for_input()
{
    $settings = pg_referral_settings();
    $amount = function ($minor) {
        return ($minor > 0) ? pg_format_number($minor / 100, 2) : '';
    };

    return array(
        'on'        => pg_config_text('referral_on') === '1',
        'offer_id'  => $settings['offer_id'],
        'reward'    => $amount($settings['reward']),
        'days'      => ($settings['days'] > 0) ? (string) $settings['days'] : '',
        'smallest'  => $amount($settings['smallest']),
        'month_cap' => ($settings['month_cap'] > 0) ? (string) $settings['month_cap'] : '',
    );
}

/**
 * The referrer's mail after a reward (E10): what they earned and their
 * whole store credit balance, the last day when the credit expires, and
 * a link to My Account. The friend is not named. Pure.
 *
 * @param array $data customer, amount, balance, last_day ('' = never),
 *                    account_url ('' = no link), from_name (all text)
 * @return string HTML
 */
function pg_referral_mail_body(array $data)
{
    $value = function ($key) use ($data) {
        return isset($data[$key]) ? trim((string) $data[$key]) : '';
    };
    $paragraph = function ($html) {
        return '<p style="margin:0 0 12px;">' . $html . '</p>';
    };

    $customer = $value('customer');
    $last_day = $value('last_day');
    $account_url = $value('account_url');

    return '<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;background:#f7f7f7;padding:20px;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;padding:24px;border-radius:6px;border:1px solid #e5e5e5;">'
        . '<h2 style="margin:0 0 16px;font-size:18px;">' . h(lang('You earned a referral reward')) . '</h2>'
        . $paragraph(h(lang('Hello')) . (($customer !== '') ? ' ' . h($customer) : '') . ',')
        . $paragraph(h(lang(array(
            'string' => 'A friend you referred completed their first order, so {var:1} was added to your account as store credit. It is used at checkout when you are signed in.',
            'vars'   => array($value('amount'))))))
        . $paragraph(h(lang(array('string' => 'Your store credit balance is now {var:1}.', 'vars' => array($value('balance'))))))
        . (($last_day !== '')
            ? $paragraph(h(lang(array('string' => 'This credit can be used until {var:1}.', 'vars' => array($last_day)))))
            : '')
        . (($account_url !== '') && preg_match('#^https?://#i', $account_url)
            ? $paragraph('<a href="' . h($account_url) . '">' . h(lang('See your store credit in My Account')) . '</a>')
            : '')
        . '<p style="margin:16px 0 0;color:#666;font-size:12px;">' . h($value('from_name')) . '</p>'
        . '</div></body></html>';
}

/**
 * Sends the referrer's mail through the mail queue. A failure is logged
 * and stops nothing: the reward stands.
 *
 * @param int $contact_id the referrer's contact
 * @param int $amount     the reward in minor units
 * @param int $days       how long it lasts, 0 = never expires
 * @return bool sent or queued
 */
function pg_referral_mail_reward($contact_id, $amount, $days)
{
    try {
        $row = db_item(
            "SELECT user.user_id, user.user_email, contacts.email_address, contacts.first_name, contacts.last_name
            FROM contacts
            JOIN user ON user.user_contact = contacts.id
            WHERE contacts.id = '" . (int) $contact_id . "'
            ORDER BY user.user_id ASC
            LIMIT 1");

        if (!is_array($row) || !defined('EMAIL_ADDRESS') || (EMAIL_ADDRESS === '')) {
            return false;
        }

        $to = trim(((string) $row['user_email'] !== '') ? (string) $row['user_email'] : (string) $row['email_address']);

        if (($to === '') || (strpos($to, '@') === false)) {
            return false;
        }

        $customer = trim(trim((string) $row['first_name']) . ' ' . trim((string) $row['last_name']));
        $from_name = (defined('ORGANIZATION_NAME') && (ORGANIZATION_NAME !== ''))
            ? ORGANIZATION_NAME
            : (defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : 'Pinegrap');
        $money = function ($minor) {
            return html_entity_decode(pg_order_log_money((int) $minor), ENT_QUOTES, 'UTF-8');
        };
        $account = get_page_type_url('my account');

        return (bool) email(array(
            'to'                 => $to,
            'to_name'            => $customer,
            'from_name'          => $from_name,
            'from_email_address' => EMAIL_ADDRESS,
            'subject'            => lang(array('string' => 'You earned {var:1} in store credit', 'vars' => array($money($amount)))),
            'format'             => 'html',
            'body'               => pg_referral_mail_body(array(
                'customer'    => $customer,
                'amount'      => $money($amount),
                'balance'     => $money(pg_credit_balance((int) $row['user_id'])),
                'last_day'    => ((int) $days > 0)
                    ? get_absolute_time(array('timestamp' => time() + ((int) $days * 86400), 'type' => 'date', 'format' => 'plain_text'))
                    : '',
                'account_url' => ($account && defined('URL_SCHEME') && defined('HOSTNAME_SETTING')) ? URL_SCHEME . HOSTNAME_SETTING . $account : '',
                'from_name'   => $from_name,
            )),
            'type'               => 'system',
            'source'             => 'account',
            'queue'              => true,
        ));
    } catch (\Throwable $exception) {
        log_activity(pg_log_lang(array('string' => 'The referral reward mail could not be sent: {var:1}', 'vars' => array($exception->getMessage()))));

        return false;
    }
}
