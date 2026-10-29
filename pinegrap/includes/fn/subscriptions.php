<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Subscriptions run by the store (2026.11.0, 11.0.128). A paid order line
 * whose payment driver declares recurring mode 'store'
 * (pg_payment_recurring_mode(), includes/payments/driver.php) becomes a
 * subscriptions row: the line's amount and period, the card the first
 * payment stored as the service's token (card_ref, sealed with the site's
 * key, never kept plain) and the time of the next charge. The cron charges
 * it through the driver's charge_renewal(); a failed charge moves the row to
 * past_due and is tried again 1, 3 and 7 days later.
 *
 * Statuses and the moves between them:
 *
 *   active     charged on next_charge_at
 *   past_due   a renewal failed; retry_count says how far the ladder is
 *   paused     the customer or staff stopped the charges for a while
 *   cancelled  no further charges; ends when the paid period is over
 *   ended      over; nothing moves it again
 *
 * Every move goes through pg_subscription_set_status(), which writes only
 * when the row still has the status it was read with, and leaves an event.
 * subscription_events keeps what happened to a subscription. Store-side
 * events have a NULL remote_event_id; a service's webhook id is kept there,
 * so its unique key refuses the same delivery twice and nothing else.
 *
 * Times are unix seconds in the site's time zone, amounts minor units of the
 * store's currency.
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
 * Are the tables there?
 *
 * @return bool
 */
function pg_subscriptions_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('subscriptions', 'card_ref') && pg_schema_has('subscription_events', 'remote_event_id');
    }

    return $ready;
}

/* ---------------------------------------------------------------------------
   Pure rules
   --------------------------------------------------------------------------- */

/**
 * The statuses a subscription is stored with.
 *
 * @return string[]
 */
function pg_subscription_statuses()
{
    return array('active', 'past_due', 'paused', 'cancelled', 'ended');
}

/**
 * The periods a subscription renews by: a number of months, or of days for
 * the week-based ones. 'twice_monthly' is 15 days, as the legacy gateways
 * charged it.
 *
 * @return array period => array(unit, count)
 */
function pg_subscription_periods()
{
    return array(
        'weekly'           => array('day', 7),
        'every_two_weeks'  => array('day', 14),
        'twice_monthly'    => array('day', 15),
        'every_four_weeks' => array('day', 28),
        'monthly'          => array('month', 1),
        'quarterly'        => array('month', 3),
        'twice_yearly'     => array('month', 6),
        'yearly'           => array('month', 12),
    );
}

/**
 * A period from the name a product's recurring_payment_period keeps.
 *
 * @param string $name
 * @return string a pg_subscription_periods() key, '' when there is none
 */
function pg_subscription_period_from_legacy($name)
{
    $map = array(
        'Weekly'            => 'weekly',
        'Every Two Weeks'   => 'every_two_weeks',
        'Twice every Month' => 'twice_monthly',
        'Every Four Weeks'  => 'every_four_weeks',
        'Monthly'           => 'monthly',
        'Quarterly'         => 'quarterly',
        'Twice every Year'  => 'twice_yearly',
        'Yearly'            => 'yearly',
    );

    return isset($map[(string) $name]) ? $map[(string) $name] : '';
}

/**
 * When the next charge falls, one period after $from, at the same time of
 * day. Month-based periods keep the anchor day (the day of the first charge)
 * and take the month's last day when it is shorter: January 31 renews on
 * February 28 (29 in a leap year), then on March 31 again.
 *
 * @param int    $from   unix time of the charge the period starts at
 * @param string $period pg_subscription_periods() key
 * @param int    $anchor_day the day of month of the first charge; 0 takes $from's
 * @return int unix time, 0 for an unknown period or no start
 */
function pg_subscription_next_charge_at($from, $period, $anchor_day = 0)
{
    $from = (int) $from;
    $periods = pg_subscription_periods();

    if (($from <= 0) || !isset($periods[(string) $period])) {
        return 0;
    }

    list($unit, $count) = $periods[(string) $period];

    $hour = (int) date('G', $from);
    $minute = (int) date('i', $from);
    $second = (int) date('s', $from);
    $day = (int) date('j', $from);
    $month = (int) date('n', $from);
    $year = (int) date('Y', $from);

    // Built from the calendar date, not by adding seconds, so a daylight
    // saving change in between keeps the time of day.
    if ($unit === 'day') {
        return (int) mktime($hour, $minute, $second, $month, $day + $count, $year);
    }

    $anchor_day = (int) $anchor_day;

    if (($anchor_day < 1) || ($anchor_day > 31)) {
        $anchor_day = $day;
    }

    $month += $count;
    $year += (int) floor(($month - 1) / 12);
    $month = (($month - 1) % 12) + 1;

    $last_day = (int) date('t', mktime(0, 0, 0, $month, 1, $year));

    return (int) mktime($hour, $minute, $second, $month, min($anchor_day, $last_day), $year);
}

/**
 * The retry ladder: how many days after a failed renewal it is tried again.
 *
 * @param int $retry_count failed attempts so far, the one just failed included
 * @return int days; 0 when the ladder is over (or nothing failed)
 */
function pg_subscription_retry_delay_days($retry_count)
{
    $ladder = array(1 => 1, 2 => 3, 3 => 7);

    return isset($ladder[(int) $retry_count]) ? $ladder[(int) $retry_count] : 0;
}

/**
 * When a failed renewal is tried again.
 *
 * @param int $failed_at   unix time of the failed attempt
 * @param int $retry_count failed attempts so far
 * @return int unix time, 0 when the ladder is over
 */
function pg_subscription_retry_at($failed_at, $retry_count)
{
    $days = pg_subscription_retry_delay_days($retry_count);

    if (($days === 0) || ((int) $failed_at <= 0)) {
        return 0;
    }

    $failed_at = (int) $failed_at;

    return (int) mktime((int) date('G', $failed_at), (int) date('i', $failed_at), (int) date('s', $failed_at),
        (int) date('n', $failed_at), (int) date('j', $failed_at) + $days, (int) date('Y', $failed_at));
}

/**
 * Whether a subscription may move from one status to another.
 *
 * @param string $from
 * @param string $to
 * @return bool
 */
function pg_subscription_transition_allowed($from, $to)
{
    $moves = array(
        'active'    => array('past_due', 'paused', 'cancelled', 'ended'),
        'past_due'  => array('active', 'paused', 'cancelled', 'ended'),
        'paused'    => array('active', 'cancelled', 'ended'),
        'cancelled' => array('ended'),
        'ended'     => array(),
    );

    return isset($moves[(string) $from]) && in_array((string) $to, $moves[(string) $from], true);
}

/**
 * The columns a move writes. Back to active the retry ladder starts over;
 * cancelled and ended keep the time they happened, once.
 *
 * @param array  $row the subscriptions row as read
 * @param string $to
 * @param int    $now
 * @return array|null column => value, null when the move is refused
 */
function pg_subscription_transition_fields($row, $to, $now)
{
    $from = isset($row['status']) ? (string) $row['status'] : '';

    if (!pg_subscription_transition_allowed($from, $to)) {
        return null;
    }

    $fields = array('status' => (string) $to, 'updated_at' => (int) $now);

    if ($to === 'active') {
        $fields['retry_count'] = 0;
    } elseif (($to === 'cancelled') && empty($row['cancelled_at'])) {
        $fields['cancelled_at'] = (int) $now;
    } elseif (($to === 'ended') && empty($row['ended_at'])) {
        $fields['ended_at'] = (int) $now;
    }

    return $fields;
}

/**
 * Whether a stored card_ref has the sealed shape encrypt_string_with_iv()
 * gives ("<ciphertext>:<iv>", both base64, a 16-byte IV) and fits the
 * column. A plain service token never has it.
 *
 * @param mixed $value
 * @return bool
 */
function pg_subscription_card_ref_is_sealed($value)
{
    return is_string($value)
        && (strlen($value) <= 255)
        && (preg_match('#^[A-Za-z0-9+/]+={0,2}:[A-Za-z0-9+/]{22}==$#', $value) === 1);
}

/**
 * The subscriptions row for a paid order line, not yet written.
 *
 * @param array  $order    the orders row
 * @param array  $item     the order_items row
 * @param string $driver   the payment driver's key
 * @param string $period   pg_subscription_periods() key
 * @param string $currency the order's currency code
 * @param string $card_ref the card's token, already sealed
 *                         (pg_subscription_card_ref_seal())
 * @param string $card_last4
 * @param int    $now      the time of the paid charge
 * @return array|null null when anything is missing or the token is not sealed
 */
function pg_subscription_row_from_order_line($order, $item, $driver, $period, $currency, $card_ref, $card_last4, $now)
{
    $order_id = isset($order['id']) ? (int) $order['id'] : 0;
    $item_id = isset($item['id']) ? (int) $item['id'] : 0;
    $currency = strtoupper(trim((string) $currency));
    $now = (int) $now;

    if (($order_id <= 0) || ($item_id <= 0) || ($now <= 0)
        || !pg_payment_key_valid($driver)
        || !isset(pg_subscription_periods()[(string) $period])
        || (preg_match('/^[A-Z]{3}$/', $currency) !== 1)
        || !pg_subscription_card_ref_is_sealed($card_ref)) {
        return null;
    }

    // What the line was charged: the unit price times the quantity, plus the
    // line's own tax (order_items.tax_total is the line's, not per unit).
    $quantity = max(1, (int) ($item['quantity'] ?? 1));
    $amount = (int) round((float) ($item['price'] ?? 0)) * $quantity + (int) round((float) ($item['tax_total'] ?? 0));

    if ($amount <= 0) {
        return null;
    }

    $card_last4 = trim((string) $card_last4);

    return array(
        'user_id'        => (int) ($order['user_id'] ?? 0),
        'product_id'     => (int) ($item['product_id'] ?? 0),
        'order_id'       => $order_id,
        'order_item_id'  => $item_id,
        'driver'         => (string) $driver,
        'remote_id'      => '',
        'status'         => 'active',
        'period'         => (string) $period,
        'amount'         => $amount,
        'currency'       => $currency,
        'next_charge_at' => pg_subscription_next_charge_at($now, $period),
        'retry_count'    => 0,
        'card_last4'     => (preg_match('/^[0-9]{4}$/', $card_last4) === 1) ? $card_last4 : '',
        'card_ref'       => $card_ref,
        'created_at'     => $now,
        'updated_at'     => $now,
        'cancelled_at'   => 0,
        'ended_at'       => 0,
    );
}

/**
 * A subscription_events row, not yet written. A store-side event has no
 * remote id and keeps NULL, which the unique key never compares, so only a
 * service's webhook id is de-duplicated.
 *
 * @param int    $subscription_id
 * @param string $kind            a short lowercase name ('created', 'renewed', 'failed', ...)
 * @param int    $order_id        the renewal order, 0 for none
 * @param int    $amount          minor units
 * @param string $remote_event_id the service's event id, '' for a store-side event
 * @param string $detail          a line for the history
 * @param int    $now
 * @return array|null null for no subscription or a kind that is not a name
 */
function pg_subscription_event_row($subscription_id, $kind, $order_id, $amount, $remote_event_id, $detail, $now)
{
    $subscription_id = (int) $subscription_id;

    if (($subscription_id <= 0) || (preg_match('/^[a-z][a-z_]{0,23}$/', (string) $kind) !== 1)) {
        return null;
    }

    $remote_event_id = trim((string) $remote_event_id);

    return array(
        'subscription_id' => $subscription_id,
        'kind'            => (string) $kind,
        'order_id'        => max(0, (int) $order_id),
        'amount'          => max(0, (int) $amount),
        'remote_event_id' => ($remote_event_id === '') ? null : substr($remote_event_id, 0, 100),
        'detail'          => mb_substr(trim((string) $detail), 0, 255),
        'created_at'      => (int) $now,
    );
}

/**
 * The order lines a paid checkout makes subscriptions of: recurring lines
 * with a price that were charged today (a line that starts later was not in
 * the payment), each with its period. None unless the payment's driver runs
 * subscriptions itself (recurring mode 'store') and its payment stored a
 * card: the legacy gateways keep their own recurring profiles.
 *
 * @param array  $lines          order lines as pg_order_lines_sql() reads them
 * @param string $recurring_mode pg_payment_recurring_mode() of the payment's driver
 * @param string $card_ref       the card_ref the payment answered, plain
 * @param string $today          Y-m-d
 * @return array list of array('item' => order_items fields, 'period' => key)
 */
function pg_subscription_checkout_lines($lines, $recurring_mode, $card_ref, $today)
{
    if (($recurring_mode !== 'store') || (trim((string) $card_ref) === '') || !is_array($lines)) {
        return array();
    }

    $planned = array();

    foreach ($lines as $line) {
        $period = pg_subscription_period_from_legacy($line['recurring_payment_period'] ?? '');
        $item_id = (int) ($line['order_item_id'] ?? 0);

        if (empty($line['recurring']) || ((int) ($line['price'] ?? 0) <= 0) || ($period === '') || ($item_id <= 0)
            || ((string) ($line['recurring_start_date'] ?? '') > (string) $today)) {
            continue;
        }

        $planned[$item_id] = array(
            'item' => array(
                'id'         => $item_id,
                'product_id' => (int) ($line['product_id'] ?? 0),
                'price'      => $line['price'],
                'quantity'   => $line['quantity'] ?? 1,
                'tax_total'  => $line['tax_total'] ?? 0,
            ),
            'period' => $period,
        );
    }

    return array_values($planned);
}

/* ---------------------------------------------------------------------------
   The card's token
   --------------------------------------------------------------------------- */

/**
 * A service's card token sealed with the site's key for subscriptions.card_ref.
 *
 * @param string $token
 * @return string '' without a key or a token, or when the sealed form would
 *                not fit the column: never the plain token
 */
function pg_subscription_card_ref_seal($token)
{
    $token = trim((string) $token);

    if (($token === '') || !pg_connect_can_seal()) {
        return '';
    }

    $sealed = encrypt_string_with_iv($token);
    $sealed = $sealed[0] . ':' . $sealed[1];

    return pg_subscription_card_ref_is_sealed($sealed) ? $sealed : '';
}

/**
 * The plain token from subscriptions.card_ref.
 *
 * @param string $stored
 * @return string '' when it cannot be opened
 */
function pg_subscription_card_ref_open($stored)
{
    if (!pg_subscription_card_ref_is_sealed($stored) || !pg_connect_can_seal()) {
        return '';
    }

    $parts = explode(':', (string) $stored, 2);

    return (string) decode_ssl_keys($parts[0], $parts[1]);
}

/**
 * The row a driver's store-mode operation takes: card_ref opened. Kept in
 * memory for the one call, never written back.
 *
 * @param array $row the subscriptions row
 * @return array
 */
function pg_subscription_driver_row($row)
{
    $row = is_array($row) ? $row : array();
    $row['card_ref'] = pg_subscription_card_ref_open($row['card_ref'] ?? '');

    return $row;
}

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

/**
 * Writes an event.
 *
 * @return int the event's id; 0 when it was not written, a webhook id that
 *             is already there included
 */
function pg_subscription_event_write($subscription_id, $kind, $order_id = 0, $amount = 0, $remote_event_id = '', $detail = '')
{
    $row = pg_subscription_event_row($subscription_id, $kind, $order_id, $amount, $remote_event_id, $detail, time());

    if (($row === null) || !pg_subscriptions_ready()) {
        return 0;
    }

    // INSERT IGNORE: the unique key refuses a webhook delivered twice, and
    // that is an answer, not an error.
    $result = db_q("INSERT IGNORE INTO subscription_events
            (subscription_id, kind, order_id, amount, remote_event_id, detail, created_at)
        VALUES (?, ?, ?, ?, " . (($row['remote_event_id'] === null) ? 'NULL' : '?') . ", ?, ?)",
        array_values(array_filter($row, function ($value) {
            return $value !== null;
        })));

    if (!$result || (mysqli_affected_rows(db::$con) < 1)) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Makes a subscription from a paid order line. Only for a driver in store
 * mode: any other driver keeps its own recurring path.
 *
 * @param array  $order
 * @param array  $item
 * @param string $driver
 * @param string $period
 * @param string $currency
 * @param string $card_token the plain token the driver answered as card_ref
 * @param string $card_last4
 * @return int the subscription's id, 0 when none was made
 */
function pg_subscription_create_from_order_line($order, $item, $driver, $period, $currency, $card_token, $card_last4)
{
    if (!pg_subscriptions_ready() || (pg_payment_recurring_mode($driver) !== 'store')) {
        return 0;
    }

    // One subscription per order line: a checkout that runs again for the
    // same order (a retried return) finds the row it made.
    $existing = (int) db_value("SELECT id FROM subscriptions WHERE (order_id = '" . e((int) ($order['id'] ?? 0)) . "') AND (order_item_id = '" . e((int) ($item['id'] ?? 0)) . "') LIMIT 1");

    if ($existing > 0) {
        return $existing;
    }

    $row = pg_subscription_row_from_order_line($order, $item, $driver, $period, $currency,
        pg_subscription_card_ref_seal($card_token), $card_last4, time());

    if ($row === null) {
        return 0;
    }

    $columns = array_keys($row);

    $result = db_q("INSERT INTO subscriptions (" . implode(', ', $columns) . ")
        VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")",
        array_values($row));

    if (!$result) {
        return 0;
    }

    $id = (int) mysqli_insert_id(db::$con);

    pg_subscription_event_write($id, 'created', $row['order_id'], $row['amount']);

    return $id;
}

/**
 * Moves a subscription to another status, only from the status it still has
 * and only by an allowed move, and records the move as an event.
 *
 * @param int    $id
 * @param string $to
 * @param string $detail a line for the history
 * @return bool true when the row moved
 */
function pg_subscription_set_status($id, $to, $detail = '')
{
    if (!pg_subscriptions_ready()) {
        return false;
    }

    $row = db_item("SELECT id, status, cancelled_at, ended_at FROM subscriptions WHERE id = '" . e((int) $id) . "'");

    if (!$row) {
        return false;
    }

    $fields = pg_subscription_transition_fields($row, (string) $to, time());

    if ($fields === null) {
        return false;
    }

    $sets = array();

    foreach (array_keys($fields) as $column) {
        $sets[] = $column . ' = ?';
    }

    // The status read is part of the condition: a move made meanwhile by the
    // cron or another request wins, and this one writes nothing.
    $params = array_values($fields);
    $params[] = (int) $row['id'];
    $params[] = (string) $row['status'];

    $result = db_q("UPDATE subscriptions SET " . implode(', ', $sets) . " WHERE (id = ?) AND (status = ?)", $params);

    if (!$result || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    $event_id = pg_subscription_event_write((int) $row['id'], 'status_' . $to, 0, 0, '', $detail);

    // A cancelled or ended subscription is mailed through its campaign
    // profiles (includes/fn/subscription_renewal.php).
    pg_subscription_mail_send((int) $row['id'], $event_id, 'status_' . $to, (string) $row['status'], (string) $to);

    return true;
}

/**
 * Makes the subscriptions of a completed checkout (submit_order.php): one per
 * recurring line pg_subscription_checkout_lines() picks, with the card the
 * payment stored. Run again for the same order it makes nothing new.
 *
 * @param int    $order_id
 * @param string $driver the payment's driver key
 * @param array  $answer the payment's answer (pg_payment_answer())
 * @param array  $lines  order lines as pg_order_lines_sql() reads them
 * @return array order_item_id => subscription id, 0 for a line that got none
 */
function pg_subscription_checkout_create($order_id, $driver, $answer, $lines)
{
    if (!pg_subscriptions_ready() || ((int) $order_id <= 0) || !pg_payment_key_valid($driver)) {
        return array();
    }

    $card_ref = (string) ($answer['card_ref'] ?? '');
    $planned = pg_subscription_checkout_lines($lines, pg_payment_recurring_mode($driver), $card_ref, date('Y-m-d'));

    if (!$planned) {
        return array();
    }

    $order = db_item("SELECT id, user_id FROM orders WHERE id = '" . e((int) $order_id) . "'");

    if (!$order) {
        return array();
    }

    $made = array();

    foreach ($planned as $line) {
        $made[$line['item']['id']] = pg_subscription_create_from_order_line($order, $line['item'], $driver, $line['period'],
            defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', $card_ref, (string) ($answer['card_last4'] ?? ''));
    }

    return $made;
}
