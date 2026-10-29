<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Renewals of store-run subscriptions (includes/fn/subscriptions.php), run
 * by recurring_payment_job.php. A row that is active or past_due and whose
 * next_charge_at has come is charged through its driver's charge_renewal().
 *
 *   paid      a normal online order is written for the stored line and
 *             completed through pg_order_complete(), so stock, memberships,
 *             private folder access, the receipt, the campaigns and the ERP
 *             invoice behave as for any order; the order is tied to the
 *             subscription only through subscription_events.order_id. The
 *             next charge is one period on.
 *   failed    retry_count goes up and the row is past_due, tried again 1, 3
 *             and 7 days later; after the last step it is ended.
 *   redirect  the customer must confirm the charge (Stripe's
 *             authentication_required): past_due, the link kept on the event,
 *             no charge until the customer has confirmed it.
 *   pending   the service decides later; unclear: nobody knows whether the
 *             money was taken. Either way no further charge: next_charge_at
 *             is set to 0 until the outcome is settled.
 *
 * Each row is charged under a named database lock and read again inside it,
 * and its update is conditional on the values read, so two runs at once
 * never charge one renewal twice. A run cut short before the update keeps
 * next_charge_at, and the driver's idempotency key repeats the same attempt.
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
 * The query of the subscriptions due for a charge, oldest due first. Two
 * placeholders: now, and how many rows. next_charge_at 0 is a row held
 * until an outcome is settled, never due.
 *
 * @return string
 */
function pg_subscription_renewal_due_sql()
{
    return "SELECT id FROM subscriptions
        WHERE status IN ('active', 'past_due') AND next_charge_at > 0 AND next_charge_at <= ?
        ORDER BY next_charge_at ASC, id ASC
        LIMIT ?";
}

/**
 * Whether a row read inside the lock is still due.
 *
 * @param array|null $row
 * @param int        $now
 * @return bool
 */
function pg_subscription_renewal_is_due($row, $now)
{
    return is_array($row)
        && in_array((string) ($row['status'] ?? ''), array('active', 'past_due'), true)
        && ((int) ($row['next_charge_at'] ?? 0) > 0)
        && ((int) $row['next_charge_at'] <= (int) $now);
}

/**
 * What a charge_renewal() answer (pg_payment_answer()) means for the row.
 *
 * @param array $answer
 * @return string paid | pending | confirm | unclear | failed
 */
function pg_subscription_renewal_outcome($answer)
{
    if (!is_array($answer)) {
        return 'unclear';
    }

    if (!empty($answer['success'])) {
        $status = (string) ($answer['status'] ?? '');

        if ($status === 'paid') {
            return 'paid';
        }

        if ($status === 'pending') {
            return 'pending';
        }

        if (($status === 'redirect') && ((string) ($answer['redirect_url'] ?? '') !== '')) {
            return 'confirm';
        }

        return 'unclear';
    }

    return !empty($answer['unclear']) ? 'unclear' : 'failed';
}

/**
 * The next charge after a paid renewal: the first date of the subscription's
 * own series (the first charge, then one period after another) that is
 * still to come. A run a day late or a retry that paid days later does not
 * move the series; a row held for longer skips the periods it missed rather
 * than charging them. Pure.
 *
 * @param int    $created_at the first charge
 * @param string $period
 * @param int    $now
 * @return int unix time, 0 for an unknown period or no first charge
 */
function pg_subscription_renewal_next_at($created_at, $period, $now)
{
    $created_at = (int) $created_at;

    if ($created_at <= 0) {
        return pg_subscription_next_charge_at((int) $now, $period);
    }

    $anchor = (int) date('j', $created_at);
    $next = pg_subscription_next_charge_at($created_at, $period, $anchor);

    // Ten years of weekly charges at most; one step further than that is
    // counted from now.
    for ($step = 0; ($next > 0) && ($next <= (int) $now) && ($step < 600); $step++) {
        $next = pg_subscription_next_charge_at($next, $period, $anchor);
    }

    return ($next > (int) $now) ? $next : pg_subscription_next_charge_at((int) $now, $period, $anchor);
}

/**
 * The renewal order's figures from the stored line: its unit price and
 * quantity, and the tax that makes up the subscription's amount. Pure.
 *
 * @param array $subscription the subscriptions row (amount)
 * @param array $item         the first order's line (price, quantity)
 * @return array|null price, quantity, subtotal, tax, total; null when the
 *                    line cannot carry the amount
 */
function pg_subscription_renewal_figures($subscription, $item)
{
    $amount = (int) ($subscription['amount'] ?? 0);
    $quantity = max(1, (int) ($item['quantity'] ?? 1));
    $price = (int) round((float) ($item['price'] ?? 0));
    $subtotal = $price * $quantity;

    if (($amount <= 0) || ($price <= 0) || ($subtotal > $amount)) {
        return null;
    }

    return array(
        'price'    => $price,
        'quantity' => $quantity,
        'subtotal' => $subtotal,
        'tax'      => $amount - $subtotal,
        'total'    => $amount,
    );
}

/**
 * What a renewal outcome writes to the row: the columns, the event and its
 * detail. Pure.
 *
 * @param array  $row     the subscriptions row as read inside the lock
 * @param string $outcome pg_subscription_renewal_outcome()
 * @param array  $answer  the driver's answer
 * @param int    $now
 * @return array fields (column => value), status (the status after, or ''
 *               when it stays), event, detail
 */
function pg_subscription_renewal_plan($row, $outcome, $answer, $now)
{
    $now = (int) $now;
    $status = (string) ($row['status'] ?? '');
    $retry_count = (int) ($row['retry_count'] ?? 0);
    $fields = array('updated_at' => $now);
    $to = '';
    $detail = '';

    switch ($outcome) {
        case 'paid':
            $fields['next_charge_at'] = pg_subscription_renewal_next_at((int) ($row['created_at'] ?? 0), (string) ($row['period'] ?? ''), $now);
            $fields['retry_count'] = 0;
            $to = ($status === 'past_due') ? 'active' : '';
            $event = 'renewed';
            break;

        case 'failed':
            $retry_count = min(255, $retry_count + 1);
            $retry_at = pg_subscription_retry_at($now, $retry_count);
            $fields['retry_count'] = $retry_count;
            $fields['next_charge_at'] = $retry_at;
            $to = ($retry_at > 0) ? (($status === 'past_due') ? '' : 'past_due') : 'ended';
            $event = 'renewal_failed';
            $detail = (string) ($answer['error'] ?? '');
            break;

        case 'confirm':
            // Held until the customer confirms the charge on the service's
            // page: a charge of the ladder meanwhile could take it twice.
            $fields['next_charge_at'] = 0;
            $to = ($status === 'past_due') ? '' : 'past_due';
            $event = 'confirm_needed';
            // A link cut to the column's length would lead nowhere.
            $detail = (string) ($answer['redirect_url'] ?? '');
            $detail = (strlen($detail) <= 255) ? $detail : '';
            break;

        case 'pending':
            $fields['next_charge_at'] = 0;
            $event = 'renewal_pending';
            $detail = (string) ($answer['transaction_id'] ?? '');
            break;

        default:
            $fields['next_charge_at'] = 0;
            $event = 'renewal_unclear';
            $detail = (string) ($answer['error'] ?? '');
            break;
    }

    if ($to !== '') {
        $moved = pg_subscription_transition_fields($row, $to, $now);

        if ($moved === null) {
            $to = '';
        } else {
            unset($moved['status']);
            $fields = array_merge($fields, $moved);
        }
    }

    if ($to === 'ended') {
        $fields['next_charge_at'] = 0;
    }

    return array('fields' => $fields, 'status' => $to, 'event' => $event, 'detail' => $detail);
}

/**
 * The card a renewal answered, when it is another than the stored one (the
 * customer replaced the card on the service's page): sealed again for the
 * row. Pure but for the seal it is given.
 *
 * @param array    $answer   the driver's answer
 * @param string   $current  the stored card_ref, opened
 * @param callable $seal     pg_subscription_card_ref_seal()
 * @return array card_ref and card_last4 to write; empty when nothing changes
 */
function pg_subscription_renewal_card_fields($answer, $current, $seal)
{
    $card_ref = trim((string) ($answer['card_ref'] ?? ''));

    if (($card_ref === '') || ($card_ref === (string) $current)) {
        return array();
    }

    $sealed = (string) call_user_func($seal, $card_ref);

    if ($sealed === '') {
        return array();
    }

    $fields = array('card_ref' => $sealed);
    $last4 = (string) ($answer['card_last4'] ?? '');

    if (preg_match('/^[0-9]{4}$/', $last4) === 1) {
        $fields['card_last4'] = $last4;
    }

    return $fields;
}

/**
 * The status a row has after its plan was saved. Pure.
 *
 * @param array $row  the row as read
 * @param array $plan pg_subscription_renewal_plan()
 * @return string
 */
function pg_subscription_renewal_status_after($row, $plan)
{
    return ((string) ($plan['status'] ?? '') !== '') ? (string) $plan['status'] : (string) ($row['status'] ?? '');
}

/* ---------------------------------------------------------------------------
   One renewal
   --------------------------------------------------------------------------- */

/**
 * Charges one due subscription. Everything that touches the database or
 * the payment service goes through $io (pg_subscription_renewal_io()), so
 * the order of the steps can be tested.
 *
 * @param int   $id
 * @param int   $now
 * @param array $io lock, unlock, read, first_order, charge, seal, save,
 *                  write_order, log, event, mail
 * @return string the outcome, 'skipped' when another run holds the row or
 *                it is no longer due, 'refused' when it cannot be charged
 */
function pg_subscription_renewal_run_row($id, $now, array $io)
{
    $id = (int) $id;

    if (!call_user_func($io['lock'], $id)) {
        return 'skipped';
    }

    // Read again inside the lock: a run that finished this row meanwhile
    // has moved next_charge_at, and nothing is charged twice.
    $row = call_user_func($io['read'], $id);

    if (!pg_subscription_renewal_is_due($row, $now)) {
        call_user_func($io['unlock'], $id);

        return 'skipped';
    }

    $first = call_user_func($io['first_order'], $row);

    if (!is_array($first) || !isset($first['order'], $first['item'])
        || (pg_subscription_renewal_figures($row, $first['item']) === null)) {
        // The first order or its line is gone: no order could be written for
        // a payment, so nothing is charged and the ladder runs as for a refusal.
        $plan = pg_subscription_renewal_plan($row, 'failed', array('error' => lang('The subscription\'s first order or its line is missing.')), $now);
        call_user_func($io['save'], $row, $plan);
        $event_id = call_user_func($io['event'], $id, $plan['event'], 0, 0, $plan['detail']);
        call_user_func($io['unlock'], $id);
        call_user_func($io['mail'], $id, (int) $event_id, $plan['event'], (string) $row['status'], pg_subscription_renewal_status_after($row, $plan), 0, $plan['detail']);

        return 'refused';
    }

    $driver_row = pg_subscription_driver_row($row);
    $driver_row['order'] = $first['order'];

    $answer = call_user_func($io['charge'], $row['driver'], $driver_row);
    $outcome = pg_subscription_renewal_outcome($answer);
    $plan = pg_subscription_renewal_plan($row, $outcome, $answer, $now);

    if ($outcome === 'paid') {
        $plan['fields'] += pg_subscription_renewal_card_fields($answer, $driver_row['card_ref'], $io['seal']);
    }

    // The row moves before the order is written: a run that ends inside the
    // order's completion leaves a row that is no longer due, never one that
    // is charged again.
    call_user_func($io['save'], $row, $plan);

    $order_id = ($outcome === 'paid') ? (int) call_user_func($io['write_order'], $row, $first, $answer, $now) : 0;

    if (($outcome === 'paid') && ($order_id <= 0)) {
        // The money was taken: staff must see that no order holds it.
        $plan['detail'] = lang('Paid, but the renewal order could not be written.') . ' ' . (string) ($answer['transaction_id'] ?? '');
        call_user_func($io['log'], lang(array(
            'string' => 'Subscription {var:1} was renewed and paid, but its order could not be written. Payment: {var:2}',
            'vars'   => array($id, (string) ($answer['transaction_id'] ?? '')),
        )));
    }

    $event_id = call_user_func($io['event'], $id, $plan['event'], $order_id, ($outcome === 'paid') ? (int) $row['amount'] : 0, $plan['detail']);
    call_user_func($io['unlock'], $id);

    // After the lock is let go: the mail renders a page, and the row has
    // already moved.
    call_user_func($io['mail'], $id, (int) $event_id, $plan['event'], (string) $row['status'], pg_subscription_renewal_status_after($row, $plan), $order_id, $plan['detail']);

    return $outcome;
}

/**
 * The database and payment service behind pg_subscription_renewal_run_row().
 *
 * @return array
 */
function pg_subscription_renewal_io()
{
    return array(
        'lock' => function ($id, $wait = 0) {
            return (int) db_value("SELECT GET_LOCK('" . e('pg_subscription_renewal_' . (int) $id) . "', " . (int) $wait . ")") === 1;
        },
        'unlock' => function ($id) {
            db_value("SELECT RELEASE_LOCK('" . e('pg_subscription_renewal_' . (int) $id) . "')");
        },
        'read' => function ($id) {
            return db_item("SELECT * FROM subscriptions WHERE id = '" . e((int) $id) . "'");
        },
        'first_order' => 'pg_subscription_renewal_first_order',
        'charge' => function ($driver, $driver_row) {
            return pg_payment_call((string) $driver, 'charge_renewal', array($driver_row));
        },
        'seal' => 'pg_subscription_card_ref_seal',
        'write_order' => 'pg_subscription_renewal_order_write',
        'save' => 'pg_subscription_renewal_save',
        'log' => function ($message) {
            log_activity($message, 'UNKNOWN');
        },
        'event' => function ($id, $kind, $order_id, $amount, $detail, $remote_event_id = '') {
            return pg_subscription_event_write($id, $kind, $order_id, $amount, $remote_event_id, $detail);
        },
        'mail' => 'pg_subscription_mail_send',
    );
}

/**
 * The first order and its line, what a renewal copies and the driver reads
 * (iyzico wants the buyer and the billing address).
 *
 * @param array $row the subscriptions row
 * @return array|null order, item
 */
function pg_subscription_renewal_first_order($row)
{
    $page_column = pg_schema_has('orders', 'checkout_page_id');

    $order = db_item("SELECT
            id, user_id, contact_id, payment_method, currency_code,
            billing_salutation, billing_first_name, billing_last_name, billing_company,
            billing_address_1, billing_address_2, billing_city, billing_state,
            billing_zip_code, billing_country, billing_phone_number, billing_fax_number,
            billing_email_address, custom_field_1, custom_field_2, po_number"
            . ($page_column ? ', checkout_page_id' : '') . "
        FROM orders WHERE id = '" . e((int) $row['order_id']) . "'");

    $item = db_item("SELECT id, ship_to_id, product_id, product_name, quantity, price
        FROM order_items
        WHERE (id = '" . e((int) $row['order_item_id']) . "') AND (order_id = '" . e((int) $row['order_id']) . "')");

    return ($order && $item) ? array('order' => $order, 'item' => $item) : null;
}

/**
 * Writes the row's move, only when the row still holds the status and the
 * next_charge_at the run read, and the event of a status move.
 *
 * @param array $row  the subscriptions row as read
 * @param array $plan pg_subscription_renewal_plan()
 * @return bool true when the row moved
 */
function pg_subscription_renewal_save($row, $plan)
{
    $fields = $plan['fields'];

    if ($plan['status'] !== '') {
        $fields['status'] = $plan['status'];
    }

    $sets = array();

    foreach (array_keys($fields) as $column) {
        $sets[] = $column . ' = ?';
    }

    $params = array_values($fields);
    $params[] = (int) $row['id'];
    $params[] = (string) $row['status'];
    $params[] = (int) $row['next_charge_at'];

    $result = db_q("UPDATE subscriptions SET " . implode(', ', $sets) . "
        WHERE (id = ?) AND (status = ?) AND (next_charge_at = ?)", $params);

    if (!$result || (mysqli_affected_rows(db::$con) < 1)) {
        return false;
    }

    if ($plan['status'] !== '') {
        pg_subscription_event_write((int) $row['id'], 'status_' . $plan['status']);
    }

    return true;
}

/**
 * Writes and completes the renewal order: the first order's customer and
 * billing columns, its line's product at the stored price, and the
 * recipient's address when the line had one. Completed through
 * pg_order_complete() as a payment service's message completes an order,
 * then invoiced when the store invoices paid orders.
 *
 * @param array $row    the subscriptions row
 * @param array $first  pg_subscription_renewal_first_order()
 * @param array $answer the paid answer
 * @param int   $now
 * @return int the order's id, 0 when it could not be written
 */
function pg_subscription_renewal_order_write($row, $first, $answer, $now)
{
    $order = $first['order'];
    $item = $first['item'];
    $figures = pg_subscription_renewal_figures($row, $item);

    if ($figures === null) {
        return 0;
    }

    $columns = array(
        'status'         => 'incomplete',
        'reference_code' => generate_order_reference_code(),
        'user_id'        => (int) $order['user_id'],
        'contact_id'     => (int) $order['contact_id'],
        'currency_code'  => (string) $order['currency_code'],
    );

    foreach (array('billing_salutation', 'billing_first_name', 'billing_last_name', 'billing_company',
        'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state',
        'billing_zip_code', 'billing_country', 'billing_phone_number', 'billing_fax_number',
        'billing_email_address', 'custom_field_1', 'custom_field_2', 'po_number') as $column) {
        $columns[$column] = (string) $order[$column];
    }

    if (pg_schema_has('orders', 'type')) {
        $columns['type'] = 'online';
    }

    if (!db_q("INSERT INTO orders (" . implode(', ', array_keys($columns)) . ")
        VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")", array_values($columns))) {
        return 0;
    }

    $order_id = (int) mysqli_insert_id(db::$con);
    $ship_to_id = 0;

    if ((int) $item['ship_to_id'] > 0) {
        $copied = 'ship_to_name, salutation, first_name, last_name, company, address_1, address_2, city, state, zip_code, country, phone_number, address_verified, address_type';

        if (db_q("INSERT INTO ship_tos (order_id, " . $copied . ")
            SELECT ?, " . $copied . " FROM ship_tos WHERE id = ?", array($order_id, (int) $item['ship_to_id']))) {
            $ship_to_id = (int) mysqli_insert_id(db::$con);
        }
    }

    db_q("INSERT INTO order_items (order_id, ship_to_id, product_id, product_name, quantity, price, tax, tax_total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)", array(
        $order_id, $ship_to_id, (int) $item['product_id'], (string) $item['product_name'],
        $figures['quantity'], $figures['price'], (int) round($figures['tax'] / $figures['quantity']), $figures['tax'])) or output_error('Query failed.');

    $result = db_q(pg_order_lines_sql(), array((string) $order_id)) or output_error('Query failed.');
    $lines = array();

    while ($line = mysqli_fetch_assoc($result)) {
        $lines[] = $line;
    }

    $page_column = array_key_exists('checkout_page_id', $order);
    $settings = pg_order_payment_page_settings(pg_order_checkout_page_row($page_column ? $order['checkout_page_id'] : 0));

    $values = array(
        'payment_method'          => (string) $order['payment_method'],
        'subtotal'                => $figures['subtotal'],
        'discount'                => 0,
        'tax'                     => $figures['tax'],
        'shipping'                => 0,
        'gift_card_discount'      => 0,
        'surcharge'               => 0,
        'total'                   => $figures['total'],
        'order_date'              => (int) $now,
        'user_id'                 => ((int) $order['user_id'] > 0) ? (int) $order['user_id'] : '',
        'last_modified_timestamp' => (int) $now,
        'transaction_id'          => mb_substr((string) ($answer['transaction_id'] ?? ''), 0, 50),
        'paid_at'                 => (int) $now,
    );

    if ($page_column) {
        $values['checkout_page_id'] = pg_order_checkout_page_id($order['checkout_page_id']);
    }

    $order_number = pg_order_complete($order_id, $values, array(), array(
        'lines'          => $lines,
        'order'          => array_intersect_key($order, array_flip(array(
            'billing_salutation', 'billing_first_name', 'billing_last_name', 'billing_company',
            'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state',
            'billing_zip_code', 'billing_country', 'billing_phone_number', 'billing_fax_number',
            'billing_email_address', 'custom_field_1', 'custom_field_2', 'po_number'))),
        'contact_id'     => (int) $order['contact_id'],
        'reference_code' => $columns['reference_code'],
        'receipt'        => $settings['receipt'],
    ));

    if ($order_number === null) {
        return 0;
    }

    pg_erp_auto_invoice($order_id, 'order_paid');

    return $order_id;
}

/**
 * Whether a row waits for an outcome the service reports later: held
 * (next_charge_at 0) by a confirmation link, a pending or an unclear answer,
 * and still running, through this driver.
 *
 * @param array|null $row
 * @param string     $driver
 * @return bool
 */
function pg_subscription_renewal_is_held($row, $driver)
{
    return is_array($row)
        && in_array((string) ($row['status'] ?? ''), array('active', 'past_due'), true)
        && ((int) ($row['next_charge_at'] ?? -1) === 0)
        && ((string) ($row['driver'] ?? '') === (string) $driver);
}

/**
 * A renewal the payment service reports as paid after the cron left the row
 * held: the customer confirmed the charge on the service's page (Stripe's
 * authentication_required), or a pending or unclear charge went through.
 * Written as the cron writes a paid renewal: the row moves on, the order,
 * the event, with the service's payment id as the event's remote id so a
 * second delivery is refused by the unique key. A row that is not held (the
 * cron already took the payment off-session) is left alone.
 *
 * @param string $driver
 * @param int    $id     the subscription
 * @param array  $answer notify()'s paid answer (transaction_id, amount)
 * @param int    $now
 * @param array  $io     pg_subscription_renewal_io(); null for the real one
 * @return string paid, nothing, refused (the amount or the first order
 *                does not match), busy (a run holds the row: try again)
 */
function pg_subscription_renewal_confirmed($driver, $id, $answer, $now, $io = null)
{
    $io = is_array($io) ? $io : pg_subscription_renewal_io();
    $id = (int) $id;

    if (($id <= 0) || !call_user_func($io['lock'], $id, 10)) {
        return ($id <= 0) ? 'nothing' : 'busy';
    }

    $row = call_user_func($io['read'], $id);

    if (!pg_subscription_renewal_is_held($row, $driver)) {
        call_user_func($io['unlock'], $id);

        return 'nothing';
    }

    $first = call_user_func($io['first_order'], $row);

    if (((int) ($answer['amount'] ?? -1) !== (int) $row['amount']) || !is_array($first) || !isset($first['order'], $first['item'])
        || (pg_subscription_renewal_figures($row, $first['item']) === null)) {
        call_user_func($io['unlock'], $id);

        return 'refused';
    }

    $plan = pg_subscription_renewal_plan($row, 'paid', $answer, $now);

    call_user_func($io['save'], $row, $plan);

    $order_id = (int) call_user_func($io['write_order'], $row, $first, $answer, $now);

    $event_id = call_user_func($io['event'], $id, 'renewed', $order_id, (int) $row['amount'], '', (string) ($answer['transaction_id'] ?? ''));
    call_user_func($io['unlock'], $id);
    call_user_func($io['mail'], $id, (int) $event_id, 'renewed', (string) $row['status'], pg_subscription_renewal_status_after($row, $plan), $order_id, '');

    return 'paid';
}

/* ---------------------------------------------------------------------------
   The run
   --------------------------------------------------------------------------- */

/**
 * Charges the subscriptions that are due, in batches, for at most $limit
 * rows a run (the job comes back the next day for the rest).
 *
 * @param int $now
 * @param int $limit
 * @return array outcome => count
 */
function pg_subscription_renewals_run($now, $limit = 200)
{
    $counts = array('paid' => 0, 'failed' => 0, 'confirm' => 0, 'pending' => 0, 'unclear' => 0, 'refused' => 0, 'skipped' => 0);

    if (!pg_subscriptions_ready()) {
        return $counts;
    }

    $io = pg_subscription_renewal_io();
    $done = 0;
    $seen = array();

    while ($done < $limit) {
        $result = db_q(pg_subscription_renewal_due_sql(), array((int) $now, (int) min(25, $limit - $done)));
        $ids = array();

        while ($result && ($found = mysqli_fetch_assoc($result))) {
            $ids[] = (int) $found['id'];
        }

        // A row a run could not move (held by another run, or its update
        // lost) comes back in the next batch: it is not tried twice.
        $ids = array_values(array_diff($ids, $seen));

        if (!$ids) {
            break;
        }

        foreach ($ids as $id) {
            $seen[] = $id;
            $outcome = pg_subscription_renewal_run_row($id, $now, $io);
            $counts[$outcome] = (isset($counts[$outcome]) ? $counts[$outcome] : 0) + 1;
            $done++;
        }
    }

    if ($done > 0) {
        log_activity(lang(array(
            'string' => 'Subscription renewals: {var:1} paid, {var:2} failed, {var:3} waiting for the customer, {var:4} waiting for the payment service.',
            'vars'   => array($counts['paid'], $counts['failed'] + $counts['refused'], $counts['confirm'], $counts['pending'] + $counts['unclear']),
        )), 'UNKNOWN');
    }

    return $counts;
}

/* ---------------------------------------------------------------------------
   Mails
   --------------------------------------------------------------------------- */

/**
 * The campaign profile action an event of a subscription mails through.
 * A failure that ends the ladder says the subscription stopped; a row that
 * is ended after it was cancelled has been told already. Pure.
 *
 * @param string $event         subscription_events.kind
 * @param string $status_before the status the row had
 * @param string $status_after  the status the event left it in
 * @return string subscription_renewed, subscription_failed,
 *                subscription_cancelled, or '' for no mail
 */
function pg_subscription_mail_action($event, $status_before, $status_after)
{
    switch ((string) $event) {
        case 'renewed':
            return 'subscription_renewed';

        case 'renewal_failed':
            return ((string) $status_after === 'ended') ? 'subscription_cancelled' : 'subscription_failed';

        case 'confirm_needed':
            return 'subscription_failed';

        case 'status_cancelled':
            return 'subscription_cancelled';

        case 'status_ended':
            return ((string) $status_before === 'cancelled') ? '' : 'subscription_cancelled';
    }

    return '';
}

/**
 * The mail-merge fields of a subscription mail, every one always passed (empty
 * when unknown) so a [[...||...]] block takes its else part. Pure but for the
 * site's number separators.
 *
 * @param array $row     the subscriptions row
 * @param array $context product_name, order_number (the renewal order),
 *                       confirm_url, card_url
 * @return array name, data, type
 */
function pg_subscription_mail_fields($row, $context)
{
    $next = (int) ($row['next_charge_at'] ?? 0);
    $amount = (int) ($row['amount'] ?? 0);
    $currency = (string) ($row['currency'] ?? '');

    return array(
        array('name' => 'subscription_product', 'data' => (string) ($context['product_name'] ?? ''), 'type' => ''),
        array('name' => 'subscription_amount', 'data' => ($amount > 0) ? trim(pg_format_number($amount / 100, 2) . ' ' . $currency) : '', 'type' => ''),
        array('name' => 'subscription_next_date', 'data' => ($next > 0) ? date('Y-m-d', $next) : '', 'type' => 'date'),
        array('name' => 'subscription_card_url', 'data' => (string) ($context['card_url'] ?? ''), 'type' => ''),
        array('name' => 'subscription_confirm_url', 'data' => (string) ($context['confirm_url'] ?? ''), 'type' => ''),
        array('name' => 'order_number', 'data' => (string) ($context['order_number'] ?? ''), 'type' => ''),
    );
}

/**
 * How long a card update link from a mail stays open: past the last step of
 * the 1/3/7 ladder.
 *
 * @return int seconds
 */
function pg_subscription_card_link_ttl()
{
    return 2592000;
}

/**
 * The signature of a card update link. Its own prefix keeps it apart from any
 * other HMAC made with the same key.
 *
 * @param int    $id        the subscription
 * @param int    $issued_at
 * @param string $key
 * @return string 64 hex characters, '' when any part is missing
 */
function pg_subscription_card_token($id, $issued_at, $key)
{
    if (((int) $id <= 0) || ((int) $issued_at <= 0) || ((string) $key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'subscription-card:' . (int) $id . ':' . (int) $issued_at, (string) $key);
}

/**
 * The card update link a mail carries (subscription_card.php).
 *
 * @param int $id
 * @param int $now
 * @return string '' without the site's key
 */
function pg_subscription_card_url($id, $now)
{
    $token = pg_subscription_card_token($id, $now, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '');

    if ($token === '') {
        return '';
    }

    $base = defined('URL_SCHEME') ? URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY : '';

    return $base . '/subscription_card.php?s=' . (int) $id . '&t=' . (int) $now . '&k=' . $token;
}

/**
 * What a card update link can do now. Pure.
 *
 * invalid: not a link of this form, a signature that does not match, or
 *          issued more than five minutes ahead of this clock;
 * expired: older than pg_subscription_card_link_ttl();
 * closed:  the subscription no longer runs;
 * open:    the button may send the customer to the payment service.
 *
 * @param array  $source   $_GET or $_POST
 * @param string $key
 * @param array|null $row  the subscriptions row the link names
 * @param int    $now
 * @return string
 */
function pg_subscription_card_link_state($source, $key, $row, $now)
{
    $id = is_array($source) ? ($source['s'] ?? null) : null;
    $issued_at = is_array($source) ? ($source['t'] ?? null) : null;
    $token = is_array($source) ? ($source['k'] ?? null) : null;

    if (!is_string($id) || !is_string($issued_at) || !is_string($token)
        || (preg_match('/^[1-9][0-9]{0,9}$/', $id) !== 1)
        || (preg_match('/^[1-9][0-9]{0,10}$/', $issued_at) !== 1)
        || (preg_match('/^[0-9a-f]{64}$/', $token) !== 1)) {
        return 'invalid';
    }

    $expected = pg_subscription_card_token((int) $id, (int) $issued_at, $key);

    if (($expected === '') || !hash_equals($expected, $token) || ((int) $issued_at > (int) $now + 300)) {
        return 'invalid';
    }

    if ((int) $now > (int) $issued_at + pg_subscription_card_link_ttl()) {
        return 'expired';
    }

    if (!is_array($row) || ((int) ($row['id'] ?? 0) !== (int) $id)
        || !in_array((string) ($row['status'] ?? ''), array('active', 'past_due', 'paused'), true)) {
        return 'closed';
    }

    return 'open';
}

/**
 * Starts the mail of a subscription event through the campaign profiles of
 * its action (create_auto_email_campaigns()). Only an event that was written
 * mails: a webhook delivered twice writes no second event, so the customer
 * gets one mail per event. With no enabled profile for the action nothing is
 * sent. A failure here never stops the renewal that called it.
 *
 * @param int    $id            the subscription
 * @param int    $event_id      the event's id, 0 when it was not written
 * @param string $event         its kind
 * @param string $status_before
 * @param string $status_after
 * @param int    $order_id      the renewal order, 0 for none
 * @param string $detail        the event's detail (the confirmation link)
 * @return bool true when the profiles were asked
 */
function pg_subscription_mail_send($id, $event_id, $event, $status_before, $status_after, $order_id = 0, $detail = '')
{
    $action = pg_subscription_mail_action($event, $status_before, $status_after);

    if (((int) $event_id <= 0) || ($action === '')) {
        return false;
    }

    try {
        $row = db_item("SELECT * FROM subscriptions WHERE id = '" . e((int) $id) . "'");
        $first = $row ? pg_subscription_renewal_first_order($row) : null;

        if (!$first) {
            return false;
        }

        $context = array(
            'product_name' => (string) $first['item']['product_name'],
            'order_number' => ((int) $order_id > 0) ? (string) db_value("SELECT order_number FROM orders WHERE id = '" . e((int) $order_id) . "'") : '',
            'confirm_url'  => ((string) $event === 'confirm_needed') ? (string) $detail : '',
            'card_url'     => ($action === 'subscription_failed') ? pg_subscription_card_url((int) $id, time()) : '',
        );

        unset($row['card_ref']);

        create_auto_email_campaigns(array(
            'action'        => $action,
            'order_id'      => ((int) $order_id > 0) ? (int) $order_id : (int) $row['order_id'],
            'contact_id'    => $first['order']['contact_id'],
            'email_address' => $first['order']['billing_email_address'],
            'fields'        => pg_subscription_mail_fields($row, $context),
            'data'          => array('subscription' => $row),
        ));
    } catch (\Throwable $exception) {
        log_activity(lang(array(
            'string' => 'The e-mail of subscription {var:1} could not be prepared: {var:2}',
            'vars'   => array((int) $id, $exception->getMessage()),
        )), 'UNKNOWN');

        return false;
    }

    return true;
}
