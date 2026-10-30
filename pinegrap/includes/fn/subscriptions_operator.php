<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The operator's Subscriptions screen (view_subscriptions.php): the list of
 * store-run subscriptions with its filters, one row's facts, history and
 * renewal orders, and what staff may do with a row:
 *
 *   pause    an active row
 *   resume   a paused row
 *   cancel   an active, past_due or paused row, at the end of the period
 *            already paid
 *
 * the same moves, from the same statuses, as the customer's own in My
 * Account (includes/fn/subscriptions_account.php): both go through
 * pg_subscription_store_move(), so the status rules, the event, the mail and
 * the stored card are handled once. The event's detail names the operator.
 * Nothing here cancels at once, ends a row, refunds or changes a price or a
 * period: refunds stay on the renewal order's own screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Pure rules
   --------------------------------------------------------------------------- */

/**
 * The moves staff may make on a row, in the order they are shown: the
 * customer's moves without the card update, which only the customer can
 * make on the payment service's page.
 *
 * @param array $row the subscriptions row
 * @return string[]
 */
function pg_subscription_operator_actions($row)
{
    return array_values(array_diff(pg_subscription_customer_actions($row), array('update_card')));
}

/**
 * The history line that names the operator who made a move.
 *
 * @param array $user validate_user()'s answer
 * @return string at most 255 characters (subscription_events.detail)
 */
function pg_subscription_operator_detail($user)
{
    $name = trim((string) ($user['username'] ?? ''));

    if ($name === '') {
        $name = trim((string) ($user['email_address'] ?? ''));
    }

    $suffix = ' (#' . (int) ($user['id'] ?? 0) . ')';

    return 'operator: ' . mb_substr($name, 0, 255 - 10 - mb_strlen($suffix)) . $suffix;
}

/**
 * Why a move is refused on a row, for the screen's notice.
 *
 * @param array|null $row
 * @param string     $action
 * @return string '' when the move is allowed
 */
function pg_subscription_operator_refusal($row, $action)
{
    if (!is_array($row)) {
        return lang('The subscription was not found.');
    }

    $action = (string) $action;

    if (in_array($action, pg_subscription_operator_actions($row), true)) {
        return '';
    }

    $status = (string) ($row['status'] ?? '');

    if (!in_array($action, array('pause', 'resume', 'cancel'), true)) {
        return lang('Only pause, resume and cancel at the end of the period are possible here.');
    }

    if (in_array($status, array('cancelled', 'ended'), true)) {
        return lang('A cancelled or ended subscription cannot be changed.');
    }

    if (($action === 'pause') && ($status === 'past_due')) {
        return lang('A subscription with a payment due cannot be paused: it would stop the payment retries.');
    }

    if ($action === 'pause') {
        return lang('The subscription is already paused.');
    }

    return lang('Only a paused subscription can be resumed.');
}

/**
 * The list's filters from the request, every unknown value dropped.
 *
 * @param array $input $_GET
 * @return array status, next_from, next_to (Y-m-d or ''), q
 */
function pg_subscription_operator_filter($input)
{
    $filter = array('status' => '', 'next_from' => '', 'next_to' => '', 'q' => '');
    $status = is_scalar($input['status'] ?? null) ? (string) $input['status'] : '';

    if (in_array($status, pg_subscription_statuses(), true)) {
        $filter['status'] = $status;
    }

    foreach (array('next_from', 'next_to') as $key) {
        $value = is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : '';

        if ((preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match) === 1) && checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            $filter[$key] = $value;
        }
    }

    if (is_scalar($input['q'] ?? null)) {
        $filter['q'] = mb_substr(trim((string) $input['q']), 0, 100);
    }

    return $filter;
}

/**
 * The WHERE clause of the list for a filter (pg_subscription_operator_filter()).
 * The next-charge range is whole days in the site's time zone; the search
 * reads the customer's name and e-mail on the first order and their account.
 *
 * @param array $filter
 * @return string
 */
function pg_subscription_operator_where($filter)
{
    $where = array();

    if ((string) ($filter['status'] ?? '') !== '') {
        $where[] = "(s.status = '" . e((string) $filter['status']) . "')";
    }

    if ((string) ($filter['next_from'] ?? '') !== '') {
        list($year, $month, $day) = array_map('intval', explode('-', (string) $filter['next_from']));
        $where[] = "(s.next_charge_at >= '" . mktime(0, 0, 0, $month, $day, $year) . "')";
    }

    if ((string) ($filter['next_to'] ?? '') !== '') {
        list($year, $month, $day) = array_map('intval', explode('-', (string) $filter['next_to']));
        $where[] = "(s.next_charge_at < '" . mktime(0, 0, 0, $month, $day + 1, $year) . "')";
    }

    if ((string) ($filter['q'] ?? '') !== '') {
        $like = "'%" . e(escape_like((string) $filter['q'])) . "%'";
        $columns = array('o.billing_first_name', 'o.billing_last_name', "CONCAT(o.billing_first_name, ' ', o.billing_last_name)",
            'o.billing_email_address', 'u.user_username', 'u.user_email');
        $alternatives = array();

        foreach ($columns as $column) {
            $alternatives[] = $column . ' LIKE ' . $like;
        }

        $where[] = '(' . implode(' OR ', $alternatives) . ')';
    }

    return $where ? implode(' AND ', $where) : '1 = 1';
}

/**
 * The words staff read for a period name (pg_subscription_periods()).
 *
 * @param string $period
 * @return string
 */
function pg_subscription_operator_period_label($period)
{
    switch ((string) $period) {
        case 'weekly':
            return lang('Weekly');
        case 'every_two_weeks':
            return lang('Every Two Weeks');
        case 'twice_monthly':
            return lang('Twice every Month');
        case 'every_four_weeks':
            return lang('Every Four Weeks');
        case 'monthly':
            return lang('Monthly');
        case 'quarterly':
            return lang('Quarterly');
        case 'twice_yearly':
            return lang('Twice every Year');
        case 'yearly':
            return lang('Yearly');
    }

    return (string) $period;
}

/* ---------------------------------------------------------------------------
   Actions
   --------------------------------------------------------------------------- */

/**
 * Makes an operator's move on a subscription.
 *
 * @param array      $user   validate_user()'s answer: named in the event
 * @param int        $id     the subscription
 * @param string     $action pause, resume or cancel
 * @param array|null $io     stand-ins for tests; null for the database
 * @return array result: done, not_found or refused; reason: the words for a
 *               refusal
 */
function pg_subscription_operator_action($user, $id, $action, $io = null)
{
    if ($io === null) {
        if (!pg_subscriptions_ready()) {
            return array('result' => 'not_found', 'reason' => lang('The subscription was not found.'));
        }

        $io = pg_subscription_customer_io();
    }

    $row = $io['read']((int) $id);
    $row = $row ?: null;
    $reason = pg_subscription_operator_refusal($row, $action);

    if ($reason !== '') {
        return array('result' => ($row === null) ? 'not_found' : 'refused', 'reason' => $reason);
    }

    if (!pg_subscription_store_move($row, (string) $action, pg_subscription_operator_detail($user), $io)) {
        return array('result' => 'refused', 'reason' => lang('The subscription changed meanwhile. Look at it again and retry.'));
    }

    return array('result' => 'done', 'reason' => '');
}

/* ---------------------------------------------------------------------------
   Reading
   --------------------------------------------------------------------------- */

/**
 * The columns the list and the row page read: the subscription, its product
 * name, the customer on the first order and their account.
 *
 * @return string
 */
function pg_subscription_operator_select_sql()
{
    return "SELECT s.id, s.user_id, s.product_id, s.order_id, s.driver, s.status, s.period, s.amount, s.currency,
            s.next_charge_at, s.retry_count, s.card_last4, s.created_at, s.updated_at, s.cancelled_at, s.ended_at,
            COALESCE(oi.product_name, p.name, '') AS product_name,
            COALESCE(o.order_number, '') AS order_number,
            TRIM(CONCAT(COALESCE(o.billing_first_name, ''), ' ', COALESCE(o.billing_last_name, ''))) AS customer_name,
            COALESCE(o.billing_email_address, '') AS customer_email,
            COALESCE(u.user_username, '') AS customer_username
        FROM subscriptions AS s
        LEFT JOIN order_items AS oi ON oi.id = s.order_item_id
        LEFT JOIN products AS p ON p.id = s.product_id
        LEFT JOIN orders AS o ON o.id = s.order_id
        LEFT JOIN user AS u ON u.user_id = s.user_id";
}

/**
 * The list: the rows the filter matches, the next charge first (rows with
 * none last), then the newest.
 *
 * @param array $filter pg_subscription_operator_filter()
 * @param int   $limit
 * @return array
 */
function pg_subscription_operator_rows($filter, $limit)
{
    if (!pg_subscriptions_ready()) {
        return array();
    }

    return (array) db_items(pg_subscription_operator_select_sql() . "
        WHERE " . pg_subscription_operator_where($filter) . "
        ORDER BY (s.next_charge_at = 0) ASC, s.next_charge_at ASC, s.id DESC
        LIMIT " . max(1, (int) $limit));
}

/**
 * One row for the row page, with its history (newest first) and its renewal
 * orders (the events that name an order other than the first).
 *
 * @param int $id
 * @return array|null row, events, orders
 */
function pg_subscription_operator_record($id)
{
    if (((int) $id <= 0) || !pg_subscriptions_ready()) {
        return null;
    }

    $row = db_item(pg_subscription_operator_select_sql() . " WHERE s.id = '" . e((int) $id) . "'");

    if (!$row) {
        return null;
    }

    $events = (array) db_items("SELECT id, kind, order_id, amount, detail, created_at FROM subscription_events
        WHERE subscription_id = '" . e((int) $id) . "'
        ORDER BY id DESC
        LIMIT 200");

    $orders = (array) db_items("SELECT id, order_number, order_date, status, total FROM orders
        WHERE id IN (SELECT order_id FROM subscription_events
                WHERE (subscription_id = '" . e((int) $id) . "') AND (order_id > 0))
            AND (id <> '" . e((int) $row['order_id']) . "')
        ORDER BY id DESC
        LIMIT 200");

    return array('row' => $row, 'events' => $events, 'orders' => $orders);
}
