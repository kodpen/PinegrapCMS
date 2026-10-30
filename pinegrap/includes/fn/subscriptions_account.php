<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The customer's own subscriptions in My Account: the list the my_account
 * widget draws in its "subscriptions" section, and what the customer may do
 * with a row there (subscription_action.php). The store runs every
 * subscription (includes/fn/subscriptions.php); the customer can
 *
 *   pause         an active row: no charge until it is resumed
 *   resume        a paused row: charged again from its next date
 *   cancel        an active, past_due or paused row, at the end of the
 *                 period already paid: the row is cancelled at once (no
 *                 charge is due for a cancelled row) and keeps
 *                 next_charge_at; the renewal job ends it on that date
 *                 (pg_subscription_cancelled_ends_run()). No refund or
 *                 proration.
 *   update_card   a running row: the payment service's own card page,
 *                 through the driver's update_card()
 *
 * A past_due row was not paid for its period, so it cannot be paused (that
 * would stop the retries) and its cancel ends it at the next job run.
 *
 * Every action reads the row with the customer's user id: another
 * customer's row is answered as not found. Every move goes through
 * pg_subscription_set_status() and pg_subscription_transition_allowed(), and
 * leaves an event.
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
 * The status an action moves a row to; '' for an action that moves none.
 *
 * @param string $action
 * @return string|null null for an unknown action
 */
function pg_subscription_customer_action_status($action)
{
    $statuses = array('pause' => 'paused', 'resume' => 'active', 'cancel' => 'cancelled', 'update_card' => '');

    return isset($statuses[(string) $action]) ? $statuses[(string) $action] : null;
}

/**
 * The actions the customer may take on a row, in the order they are shown.
 *
 * @param array $row the subscriptions row
 * @return string[]
 */
function pg_subscription_customer_actions($row)
{
    $status = (string) ($row['status'] ?? '');
    $by_status = array(
        'active'   => array('pause', 'cancel', 'update_card'),
        'past_due' => array('cancel', 'update_card'),
        'paused'   => array('resume', 'cancel', 'update_card'),
    );

    if (!isset($by_status[$status])) {
        return array();
    }

    $actions = array();

    foreach ($by_status[$status] as $action) {
        $to = pg_subscription_customer_action_status($action);

        // A move the status rules refuse is never offered.
        if (($to === '') || pg_subscription_transition_allowed($status, $to)) {
            $actions[] = $action;
        }
    }

    return $actions;
}

/**
 * Whether a customer may take an action on a row they asked for.
 *
 * @param array|null $row     the row read by its id, null when none
 * @param int        $user_id the signed-in customer
 * @param string     $action
 * @return string '' when allowed; not_found (no row, or another customer's),
 *                refused (the action does not fit the row's status)
 */
function pg_subscription_customer_action_check($row, $user_id, $action)
{
    if (!is_array($row) || ((int) $user_id <= 0) || ((int) ($row['user_id'] ?? 0) !== (int) $user_id)) {
        return 'not_found';
    }

    if (pg_subscription_customer_action_status($action) === null) {
        return 'refused';
    }

    return in_array((string) $action, pg_subscription_customer_actions($row), true) ? '' : 'refused';
}

/**
 * Whether a cancelled row's paid period is over, so the renewal job ends it.
 * A row cancelled while past_due (retry_count above 0) or held
 * (next_charge_at 0) has no paid period left.
 *
 * @param array $row
 * @param int   $now
 * @return bool
 */
function pg_subscription_cancelled_is_over($row, $now)
{
    if ((string) ($row['status'] ?? '') !== 'cancelled') {
        return false;
    }

    $next = (int) ($row['next_charge_at'] ?? 0);

    return ($next <= (int) $now) || ((int) ($row['retry_count'] ?? 0) > 0);
}

/**
 * The confirmation link of a past_due row whose renewal waits for the
 * customer (Stripe authentication_required): the link of its latest
 * confirm_needed event, while that is the latest renewal event.
 *
 * @param array      $row
 * @param array|null $event the row's latest event of kind renewed,
 *                          renewal_failed or confirm_needed
 * @return string an https address, '' for none
 */
function pg_subscription_customer_confirm_url($row, $event)
{
    if (((string) ($row['status'] ?? '') !== 'past_due') || !is_array($event) || ((string) ($event['kind'] ?? '') !== 'confirm_needed')) {
        return '';
    }

    $url = trim((string) ($event['detail'] ?? ''));

    return (preg_match('~^https://[^\s<>"]+$~i', $url) === 1) ? $url : '';
}

/* ---------------------------------------------------------------------------
   Actions
   --------------------------------------------------------------------------- */

/**
 * The database and payment service behind pg_subscription_customer_action().
 *
 * @return array
 */
function pg_subscription_customer_io()
{
    return array(
        'read' => function ($id) {
            return db_item("SELECT * FROM subscriptions WHERE id = '" . e((int) $id) . "'");
        },
        'set_status' => 'pg_subscription_set_status',
        'event' => function ($id, $kind, $detail) {
            return pg_subscription_event_write($id, $kind, 0, 0, '', $detail);
        },
        'call' => function ($driver, $operation, $driver_row) {
            return pg_payment_call((string) $driver, $operation, array($driver_row));
        },
        // Lines of one order share the stored card: it is forgotten only
        // when no other running subscription of the order uses it.
        'card_shared' => function ($row) {
            return (int) db_value("SELECT COUNT(*) FROM subscriptions
                WHERE (order_id = '" . e((int) $row['order_id']) . "') AND (driver = '" . e((string) $row['driver']) . "')
                    AND (id <> '" . e((int) $row['id']) . "') AND status IN ('active', 'past_due', 'paused')") > 0;
        },
        'log' => function ($message) {
            log_activity($message, 'UNKNOWN');
        },
    );
}

/**
 * Takes a customer's action on one of their subscriptions.
 *
 * @param int    $user_id    the signed-in customer
 * @param int    $id         the subscription
 * @param string $action     pause, resume, cancel or update_card
 * @param string $return_url where the payment service's card page sends the
 *                           customer back (absolute)
 * @param array|null $io     stand-ins for tests; null for the database
 * @return array result: done, redirect, not_found, refused or failed;
 *               url: the card page for redirect
 */
function pg_subscription_customer_action($user_id, $id, $action, $return_url, $io = null)
{
    if ($io === null) {
        if (!pg_subscriptions_ready()) {
            return array('result' => 'not_found', 'url' => '');
        }

        $io = pg_subscription_customer_io();
    }

    $row = $io['read']((int) $id);
    $check = pg_subscription_customer_action_check($row ?: null, $user_id, $action);

    if ($check !== '') {
        return array('result' => $check, 'url' => '');
    }

    $action = (string) $action;

    if ($action === 'update_card') {
        $driver_row = pg_subscription_driver_row($row);
        $driver_row['return_url'] = (string) $return_url;
        $answer = $io['call']((string) $row['driver'], 'update_card', $driver_row);
        $url = (string) ($answer['redirect_url'] ?? '');

        if (empty($answer['success']) || (preg_match('~^https://~i', $url) !== 1)) {
            return array('result' => 'failed', 'url' => '');
        }

        $io['event']((int) $row['id'], 'card_update_opened', 'customer');

        return array('result' => 'redirect', 'url' => $url);
    }

    // The status the row was read with is the condition of the write: a
    // renewal or a staff move made meanwhile wins and this one is refused.
    if (!$io['set_status']((int) $row['id'], pg_subscription_customer_action_status($action), 'customer')) {
        return array('result' => 'refused', 'url' => '');
    }

    // Nothing is charged again: the stored card is forgotten at the service
    // unless another subscription of the same order still runs on it. A
    // failure there leaves the cancel standing.
    if (($action === 'cancel') && !$io['card_shared']($row)) {
        $answer = $io['call']((string) $row['driver'], 'subscription_cancel', pg_subscription_driver_row($row));

        if (empty($answer['success'])) {
            $io['log'](lang(array('string' => 'The payment service did not forget the card of cancelled subscription {var:1}.', 'vars' => array((int) $row['id']))));
        }
    }

    return array('result' => 'done', 'url' => '');
}

/**
 * Ends the cancelled subscriptions whose paid period is over
 * (pg_subscription_cancelled_is_over()), from the renewal job.
 *
 * @param int $now
 * @param int $limit
 * @return int how many were ended
 */
function pg_subscription_cancelled_ends_run($now, $limit = 200)
{
    if (!pg_subscriptions_ready()) {
        return 0;
    }

    $rows = (array) db_items("SELECT id, status, next_charge_at, retry_count FROM subscriptions
        WHERE (status = 'cancelled') AND ((next_charge_at <= '" . e((int) $now) . "') OR (retry_count > 0))
        ORDER BY id ASC
        LIMIT " . (int) $limit);
    $ended = 0;

    foreach ($rows as $row) {
        if (pg_subscription_cancelled_is_over($row, $now) && pg_subscription_set_status((int) $row['id'], 'ended', 'period over')) {
            $ended++;
        }
    }

    return $ended;
}

/* ---------------------------------------------------------------------------
   The list
   --------------------------------------------------------------------------- */

/**
 * A customer's subscriptions, newest first, with the product's name and the
 * latest event: kind, detail, created_at as last_kind, last_detail,
 * last_at; and the latest renewal event as renewal_kind, renewal_detail.
 *
 * @param int $user_id
 * @return array
 */
function pg_subscription_customer_rows($user_id)
{
    if (((int) $user_id <= 0) || !pg_subscriptions_ready()) {
        return array();
    }

    $rows = (array) db_items("SELECT s.id, s.user_id, s.product_id, s.driver, s.status, s.period, s.amount, s.currency,
            s.next_charge_at, s.retry_count, s.card_last4, s.created_at, s.cancelled_at, s.ended_at,
            COALESCE(oi.product_name, p.name, '') AS product_name
        FROM subscriptions AS s
        LEFT JOIN order_items AS oi ON oi.id = s.order_item_id
        LEFT JOIN products AS p ON p.id = s.product_id
        WHERE s.user_id = '" . e((int) $user_id) . "'
        ORDER BY s.id DESC
        LIMIT 50");

    foreach ($rows as $index => $row) {
        $last = db_item("SELECT kind, detail, created_at FROM subscription_events
            WHERE subscription_id = '" . e((int) $row['id']) . "' ORDER BY id DESC LIMIT 1");
        $renewal = db_item("SELECT kind, detail FROM subscription_events
            WHERE (subscription_id = '" . e((int) $row['id']) . "') AND kind IN ('renewed', 'renewal_failed', 'confirm_needed')
            ORDER BY id DESC LIMIT 1");
        $rows[$index]['last_kind'] = $last ? (string) $last['kind'] : '';
        $rows[$index]['last_at'] = $last ? (int) $last['created_at'] : 0;
        $rows[$index]['renewal_kind'] = $renewal ? (string) $renewal['kind'] : '';
        $rows[$index]['renewal_detail'] = $renewal ? (string) $renewal['detail'] : '';
    }

    return $rows;
}

/**
 * The words a customer reads for a status.
 *
 * @param string $status
 * @return string
 */
function pg_subscription_customer_status_label($status)
{
    switch ((string) $status) {
        case 'active':
            return lang('Active');
        case 'past_due':
            return lang('Payment due');
        case 'paused':
            return lang('Paused');
        case 'cancelled':
            return lang('Cancelled');
        case 'ended':
            return lang('Ended');
    }

    return '';
}

/**
 * The words a customer reads for an event.
 *
 * @param string $kind subscription_events.kind
 * @return string
 */
function pg_subscription_customer_event_label($kind)
{
    switch ((string) $kind) {
        case 'created':
            return lang('Started');
        case 'renewed':
            return lang('Renewed');
        case 'renewal_failed':
            return lang('Payment failed');
        case 'confirm_needed':
            return lang('Payment needs your confirmation');
        case 'renewal_pending':
        case 'renewal_unclear':
            return lang('Payment in progress');
        case 'card_update_opened':
            return lang('Card update opened');
        case 'status_active':
            return lang('Resumed');
        case 'status_paused':
            return lang('Paused');
        case 'status_past_due':
            return lang('Payment due');
        case 'status_cancelled':
            return lang('Cancelled');
        case 'status_ended':
            return lang('Ended');
    }

    return '';
}

/**
 * The subscriptions section of the my_account widget: one item per row with
 * its figures and a form per action (subscription_action.php, the form
 * token). Site output, Bootstrap 5.3.8. '' when the customer has none.
 *
 * @param array  $rows       pg_subscription_customer_rows()
 * @param string $action_url subscription_action.php
 * @param string $send_to    the page to come back to (a path)
 * @param string $token      the session's form token
 * @return string
 */
function pg_subscription_customer_section_html($rows, $action_url, $send_to, $token)
{
    if (!$rows) {
        return '';
    }

    $date_format = pg_sw_default_date_format('date');
    $button_labels = array(
        'pause'       => lang('Pause'),
        'resume'      => lang('Resume'),
        'update_card' => lang('Update card'),
    );
    $html = '<div class="list-group pg-sw-subscriptions">';

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $amount = (int) $row['amount'];
        $next = (int) $row['next_charge_at'];
        $status = (string) $row['status'];
        $event = pg_subscription_customer_event_label((string) $row['last_kind']);
        $confirm = pg_subscription_customer_confirm_url($row, array('kind' => $row['renewal_kind'], 'detail' => $row['renewal_detail']));

        $html .= '<div class="list-group-item">'
            . '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">'
            . '<span class="fw-semibold">' . h((string) $row['product_name']) . '</span>'
            . '<span>' . h(trim(pg_format_number($amount / 100, 2) . ' ' . (string) $row['currency'])) . '</span>'
            . '</div>'
            . '<div class="small text-body-secondary">'
            . '<span class="badge ' . (($status === 'past_due') ? 'text-bg-warning' : (($status === 'active') ? 'text-bg-success' : 'text-bg-secondary')) . ' me-2">'
            . h(pg_subscription_customer_status_label($status)) . '</span>';

        if (($next > 0) && in_array($status, array('active', 'past_due', 'paused'), true)) {
            $html .= '<span class="me-2">' . h(lang('Next charge:')) . ' ' . h(_pg_sw_short_date($next, $date_format)) . '</span>';
        } elseif (($status === 'cancelled') && ($next > 0) && ((int) $row['retry_count'] === 0)) {
            $html .= '<span class="me-2">' . h(lang('Runs until:')) . ' ' . h(_pg_sw_short_date($next, $date_format)) . '</span>';
        }

        if ((string) $row['card_last4'] !== '') {
            $html .= '<span class="me-2">' . h(lang(array('string' => 'Card ending {var:1}', 'vars' => array((string) $row['card_last4'])))) . '</span>';
        }

        if ($event !== '') {
            $html .= '<span>' . h(lang('Last event:')) . ' ' . h($event)
                . (((int) $row['last_at'] > 0) ? ' (' . h(_pg_sw_short_date((int) $row['last_at'], $date_format)) . ')' : '') . '</span>';
        }

        $html .= '</div>';

        if ($confirm !== '') {
            $html .= '<div class="mt-2"><a href="' . h($confirm) . '" class="btn btn-sm btn-warning" rel="noopener noreferrer">'
                . h(lang('Confirm the payment')) . '</a></div>';
        }

        $actions = pg_subscription_customer_actions($row);

        if ($actions) {
            $html .= '<div class="d-flex flex-wrap gap-2 mt-2">';

            foreach ($actions as $action) {
                $fields = '<input type="hidden" name="token" value="' . h($token) . '">'
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . '<input type="hidden" name="action" value="' . h($action) . '">'
                    . '<input type="hidden" name="send_to" value="' . h($send_to) . '">';

                if ($action === 'cancel') {
                    // Asked twice without a script: the button that cancels
                    // shows only once the customer opened this.
                    $html .= '<details class="pg-sw-subscription-cancel"><summary class="btn btn-sm btn-outline-danger">' . h(lang('Cancel subscription')) . '</summary>'
                        . '<form method="post" action="' . h($action_url) . '" class="mt-2">' . $fields
                        . '<p class="small mb-2">' . h(($status === 'active')
                            ? lang('No further charges will be made. The subscription runs until the end of the period already paid.')
                            : lang('No further charges will be made and the subscription ends.')) . '</p>'
                        . '<button type="submit" class="btn btn-sm btn-danger">' . h(lang('Yes, cancel')) . '</button>'
                        . '</form></details>';
                } else {
                    $html .= '<form method="post" action="' . h($action_url) . '">' . $fields
                        . '<button type="submit" class="btn btn-sm btn-outline-secondary">' . h($button_labels[$action]) . '</button>'
                        . '</form>';
                }
            }

            $html .= '</div>';
        }

        $html .= '</div>';
    }

    return $html . '</div>';
}
