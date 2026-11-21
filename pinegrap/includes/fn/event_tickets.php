<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Event tickets (2026.11.0): a completed order's line that gives tickets
 * (a calendar event's reservation, or a product marked "Gives tickets")
 * gets one event_tickets row per unit, 100 a line at most, as gift cards
 * are issued. A refunded return voids the tickets of the units it took and
 * gives their event places back. A ticket keeps its order line and unit, the product, the
 * event and its date when the line has one, and its state: valid, used or
 * void. Its code is drawn again from the row's salt under the tickets' own
 * key (pg_event_ticket_code(), pg_event_tickets_key(): 32 random bytes in
 * config_text, sealed with the site's key) and only its hash is stored,
 * so the door finds a ticket by the code it is shown and the table alone
 * gives no code. A
 * cancelled order voids its tickets and a restored one gives them back,
 * each by one conditional UPDATE. The tickets reach the buyer in a mail of
 * their own through the mail queue: one signed link to the tickets page.
 *
 * The switch "Tickets" (Site Settings, Features) is a config_text row,
 * '1' on, missing off: with it off no line gives tickets. An order that is
 * deleted takes its tickets with it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The settings text of the "Tickets" switch: '1' on, missing off. */
define('PG_EVENT_TICKETS_SWITCH', 'event_tickets');

/** The most tickets one order line gives, as for gift cards. */
define('PG_EVENT_TICKETS_LINE_CAP', 100);

/** The letters a code is written in: Crockford's base 32, no I, L, O or U. */
define('PG_EVENT_TICKET_ALPHABET', '0123456789ABCDEFGHJKMNPQRSTVWXYZ');

/**
 * Whether the tickets table is there.
 *
 * @return bool
 */
function pg_event_tickets_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('event_tickets');
}

/**
 * Whether the products have their "Gives tickets" mark
 * (products.gives_tickets).
 *
 * @return bool
 */
function pg_event_tickets_product_mark_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('products', 'gives_tickets');
}

/**
 * A ticket as the personal data export lists it: its order, what it is for
 * and when, its state and check-in time. Never its code, salt or hash. Pure.
 *
 * @param array $row   event_tickets id, order_id, unit, calendar_event_id,
 *                     recurrence_number, state, used_at, with the order's
 *                     order_number and the product's product_name
 * @param array $event get_calendar_event() of its event and repeat, or
 *                     array() for a product's ticket
 * @param array $check_ins a series ticket's entries: event_date (the date's
 *                         start), checked_at
 * @return array
 */
function pg_event_tickets_export_row(array $row, array $event = array(), array $check_ins = array())
{
    $entries = array();

    foreach ($check_ins as $entry) {
        $entries[] = array('event_date' => (string) ($entry['event_date'] ?? ''), 'checked_in_at' => (int) ($entry['checked_at'] ?? 0));
    }

    return array(
        'id'                => (int) ($row['id'] ?? 0),
        'order_id'          => (int) ($row['order_id'] ?? 0),
        'order_number'      => (string) ($row['order_number'] ?? ''),
        'unit'              => (int) ($row['unit'] ?? 0),
        'event'             => (string) ($event['name'] ?? ''),
        'event_date'        => (string) ($event['start_date_and_time'] ?? ''),
        'product'           => (string) ($row['product_name'] ?? ''),
        'state'             => (string) ($row['state'] ?? ''),
        'checked_in_at'     => (int) ($row['used_at'] ?? 0),
        'check_ins'         => $entries,
    );
}

/**
 * Whether the entries of series tickets have their table
 * (event_ticket_checkins).
 *
 * @return bool
 */
function pg_event_ticket_checkins_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('event_ticket_checkins');
}

/**
 * Whether an event gives series tickets: it repeats and its dates share
 * their places (separate reservations off), so a reservation is for the
 * whole series and its ticket is kept on date 0; the door enters it once
 * on each date. Pure.
 *
 * @param array|false|null $event get_calendar_event(): recurrence,
 *                                recurrence_type, separate_reservations,
 *                                total_recurrence_number
 * @return bool
 */
function pg_event_ticket_is_series($event)
{
    if (!is_array($event)) {
        return false;
    }

    return ((int) ($event['recurrence'] ?? 0) === 1)
        && in_array((string) ($event['recurrence_type'] ?? ''), array('day', 'week', 'month', 'year'), true)
        && ((int) ($event['separate_reservations'] ?? 0) !== 1)
        && ((int) ($event['total_recurrence_number'] ?? 0) > 0);
}

/**
 * The dates of a series by their numbers: 0 to its stored repeat count,
 * those removed from the calendar left out. Pure.
 *
 * @param int   $total      the event's stored repeat count
 * @param array $exceptions the removed date numbers
 * @return int[]
 */
function pg_event_ticket_series_numbers($total, array $exceptions)
{
    $removed = array();

    foreach ($exceptions as $number) {
        $removed[(int) $number] = true;
    }

    $numbers = array();

    for ($number = 0; $number <= max(0, (int) $total); $number++) {
        if (!isset($removed[$number])) {
            $numbers[] = $number;
        }
    }

    return $numbers;
}

/**
 * An event that gives series tickets, with its dates; read once a request.
 *
 * @param int $event_id
 * @return array|null event (get_calendar_event() of its first date), dates
 *                    (pg_event_ticket_series_numbers()); null when the
 *                    event gives no series tickets
 */
function pg_event_ticket_series($event_id)
{
    static $series = array();

    $event_id = (int) $event_id;

    // Without the entries' table a series ticket is read as before: on date 0.
    if (($event_id <= 0) || !function_exists('get_calendar_event') || !pg_event_ticket_checkins_ready()) {
        return null;
    }

    if (!array_key_exists($event_id, $series)) {
        $event = get_calendar_event($event_id, 0);
        $series[$event_id] = null;

        if (pg_event_ticket_is_series($event)) {
            $exceptions = array();

            foreach ((array) db_items("SELECT recurrence_number FROM calendar_event_exceptions WHERE calendar_event_id = '" . $event_id . "'") as $row) {
                $exceptions[] = (int) $row['recurrence_number'];
            }

            $series[$event_id] = array('event' => $event, 'dates' => pg_event_ticket_series_numbers($event['total_recurrence_number'], $exceptions));
        }
    }

    return $series[$event_id];
}

/**
 * Whether a ticket row is a series ticket: an event's, on date 0, whose
 * event gives series tickets.
 *
 * @param array $ticket calendar_event_id, recurrence_number
 * @return bool
 */
function pg_event_ticket_row_is_series(array $ticket)
{
    return ((int) ($ticket['calendar_event_id'] ?? 0) > 0)
        && ((int) ($ticket['recurrence_number'] ?? 0) === 0)
        && (pg_event_ticket_series((int) $ticket['calendar_event_id']) !== null);
}

/**
 * The entries of tickets, oldest first, with the name of who checked each
 * in; on one date only when a date is given.
 *
 * @param int[]    $ticket_ids
 * @param int|null $date
 * @return array rows of ticket_id, calendar_event_id, recurrence_number,
 *               checked_at, checked_by, checked_by_name
 */
function pg_event_ticket_checkins(array $ticket_ids, $date = null)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ticket_ids))));

    if (!$ids || !pg_event_ticket_checkins_ready()) {
        return array();
    }

    return (array) db_items(
        "SELECT c.ticket_id, c.calendar_event_id, c.recurrence_number, c.checked_at, c.checked_by, user.user_username AS checked_by_name
        FROM event_ticket_checkins AS c
        LEFT JOIN user ON user.user_id = c.checked_by
        WHERE (c.ticket_id IN (" . implode(', ', $ids) . "))" . (($date === null) ? '' : " AND (c.recurrence_number = '" . (int) $date . "')") . "
        ORDER BY c.recurrence_number, c.id");
}

/**
 * Whether the "Tickets" switch is on.
 *
 * @return bool
 */
function pg_event_tickets_switch_on()
{
    if (!function_exists('pg_config_text')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    return pg_config_text(PG_EVENT_TICKETS_SWITCH) === '1';
}

/**
 * Saves the "Tickets" switch: '1' on, the row removed when off. Switched
 * on, the tickets key is made first when there is none.
 *
 * @param bool $on
 * @return bool
 */
function pg_event_tickets_switch_save($on)
{
    if (!function_exists('pg_config_text_save')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    if ($on) {
        pg_event_tickets_key_make();
    }

    return (bool) pg_config_text_save(PG_EVENT_TICKETS_SWITCH, $on ? '1' : '');
}

/** The settings text that holds the tickets' own key, sealed with the site's key. */
define('PG_EVENT_TICKETS_KEY_SETTING', 'event_tickets_key');

/**
 * Seals a tickets key with the site's key: "cipher:iv", as the reseal
 * registry's 'iv' kind reads it.
 *
 * @param string      $key      64 hex characters
 * @param string|null $site_key null is ENCRYPTION_KEY
 * @return string
 */
function pg_event_tickets_key_seal($key, $site_key = null)
{
    list($cipher, $iv) = encrypt_string_with_iv((string) $key, $site_key);

    return $cipher . ':' . $iv;
}

/**
 * Opens a sealed tickets key. AES-CBC carries no MAC, so a value that opens
 * under a wrong key is told apart by its shape: 64 hex characters.
 *
 * @param string      $sealed   "cipher:iv"
 * @param string|null $site_key null is ENCRYPTION_KEY
 * @return string the key, '' when there is none or it does not open
 */
function pg_event_tickets_key_open($sealed, $site_key = null)
{
    $sealed = (string) $sealed;

    if (strpos($sealed, ':') === false) {
        return '';
    }

    list($cipher, $iv) = explode(':', $sealed, 2);
    $key = pg_ssl_keys_open($cipher, $iv, $site_key);

    return (is_string($key) && (preg_match('/^[0-9a-f]{64}$/', $key) === 1)) ? $key : '';
}

/**
 * The tickets' own key the codes and the tickets link are drawn under,
 * '' when none was made yet or it does not open with the site's key. The
 * door needs no key: it looks a code up by its hash.
 *
 * @return string
 */
function pg_event_tickets_key()
{
    if (!function_exists('pg_config_text')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    return pg_event_tickets_key_open(pg_config_text(PG_EVENT_TICKETS_KEY_SETTING));
}

/**
 * Makes the tickets key once: 32 random bytes, sealed with the site's key.
 * Only a missing row takes it (pg_config_text_claim()), so a key that exists
 * never changes; it is made when the switch is saved on, never during an
 * order's completion.
 *
 * @return bool whether a key is there afterwards
 */
function pg_event_tickets_key_make()
{
    if (!function_exists('pg_config_text_claim')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    if (pg_config_text(PG_EVENT_TICKETS_KEY_SETTING) !== '') {
        return true;
    }

    if (!defined('ENCRYPTION_KEY') || ((string) ENCRYPTION_KEY === '')) {
        return false;
    }

    return pg_config_text_claim(PG_EVENT_TICKETS_KEY_SETTING, '', pg_event_tickets_key_seal(bin2hex(random_bytes(32))))
        || ((string) db_value("SELECT value FROM config_text WHERE name = '" . e(PG_EVENT_TICKETS_KEY_SETTING) . "'") !== '');
}

/**
 * Whether completed orders make tickets now: the switch on, the table
 * there and the tickets key opening with the site's key.
 *
 * @return bool
 */
function pg_event_tickets_on()
{
    return pg_event_tickets_ready() && pg_event_tickets_switch_on() && (pg_event_tickets_key() !== '');
}

/**
 * Whether an order line gives tickets: a calendar event's reservation line
 * (the event still takes reservations) or a product marked "Gives tickets".
 * Pure.
 *
 * @param array $line calendar_event_id, reservations, gives_tickets (the
 *                    pg_order_lines_sql() row)
 * @param bool  $calendars whether the calendars are on
 * @return bool
 */
function pg_event_tickets_line_gives(array $line, $calendars = true)
{
    $reservation = $calendars
        && ((int) ($line['calendar_event_id'] ?? 0) > 0)
        && ((int) ($line['reservations'] ?? 0) === 1);

    return $reservation || ((int) ($line['gives_tickets'] ?? 0) === 1);
}

/**
 * How many tickets a line gives: one per unit, PG_EVENT_TICKETS_LINE_CAP at
 * most, none for a line that gives no tickets or with the switch off. Pure.
 *
 * @param array $line      the pg_order_lines_sql() row
 * @param bool  $on        pg_event_tickets_on()
 * @param bool  $calendars whether the calendars are on
 * @return int
 */
function pg_event_tickets_line_count(array $line, $on, $calendars = true)
{
    if (!$on || !pg_event_tickets_line_gives($line, $calendars)) {
        return 0;
    }

    return (int) min(PG_EVENT_TICKETS_LINE_CAP, max(0, (int) ($line['quantity'] ?? 0)));
}

/**
 * A new ticket's salt: 32 random hex characters.
 *
 * @return string
 */
function pg_event_ticket_salt()
{
    return bin2hex(random_bytes(16));
}

/**
 * A ticket's code, drawn from its line, unit and salt under the tickets key:
 * twelve characters of Crockford's base 32 (60 bits) in three groups of
 * four. It carries no order number and is drawn again for the holder's
 * page from the row; only its hash is stored. Pure.
 *
 * @param int    $order_item_id
 * @param int    $unit 1 to the line's ticket count
 * @param string $salt event_tickets.code_salt
 * @param string $key  the tickets key
 * @return string '' without a key, a line, a unit or a salt
 */
function pg_event_ticket_code($order_item_id, $unit, $salt, $key)
{
    $order_item_id = (int) $order_item_id;
    $unit = (int) $unit;
    $salt = (string) $salt;
    $key = (string) $key;

    if (($order_item_id <= 0) || ($unit <= 0) || ($salt === '') || ($key === '')) {
        return '';
    }

    $raw = hash_hmac('sha256', 'event-ticket:' . $order_item_id . ':' . $unit . ':' . $salt, $key, true);

    // 60 bits: the first 8 bytes, 5 bits at a time.
    $bits = '';

    for ($i = 0; $i < 8; $i++) {
        $bits .= str_pad(decbin(ord($raw[$i])), 8, '0', STR_PAD_LEFT);
    }

    $code = '';

    for ($i = 0; $i < 12; $i++) {
        $code .= PG_EVENT_TICKET_ALPHABET[bindec(substr($bits, $i * 5, 5))];
    }

    return substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
}

/**
 * A code as typed or scanned, written the way it is hashed: upper case,
 * without spaces or dashes, O read as 0 and I or L as 1. Pure.
 *
 * @param string $code
 * @return string '' when it is not twelve characters of the alphabet
 */
function pg_event_ticket_code_normalize($code)
{
    $code = strtoupper(preg_replace('/[\s\-]+/', '', (string) $code));
    $code = strtr($code, array('O' => '0', 'I' => '1', 'L' => '1'));

    return (preg_match('/^[0-9A-HJKMNP-TV-Z]{12}$/', $code) === 1) ? $code : '';
}

/**
 * The stored hash of a code: sha256 of its normalized form. Pure.
 *
 * @param string $code
 * @return string 64 hex characters, '' for a code that is not one
 */
function pg_event_ticket_code_hash($code)
{
    $code = pg_event_ticket_code_normalize($code);

    return ($code === '') ? '' : hash('sha256', $code);
}

/**
 * Runs a statement for the ticket writes: the number of rows it changed, or
 * false when it failed.
 *
 * @param string $sql
 * @param array  $params
 * @return int|false
 */
function pg_event_tickets_query($sql, array $params)
{
    $result = db_q($sql, $params);

    if ($result === false) {
        return false;
    }

    return (int) mysqli_affected_rows(db::$con);
}

/**
 * The rows a line's tickets are written as: one per unit, each with its
 * salt and its code's hash. Pure but for the salts it is given.
 *
 * @param array    $line     order_item_id, product_id, calendar_event_id,
 *                           recurrence_number, reservations, quantity
 * @param int      $order_id
 * @param int      $count    pg_event_tickets_line_count()
 * @param string   $key      the tickets key
 * @param callable $salt     returns a new salt (pg_event_ticket_salt())
 * @return array rows of the event_tickets columns
 */
function pg_event_tickets_line_rows(array $line, $order_id, $count, $key, $salt = 'pg_event_ticket_salt')
{
    $rows = array();
    $order_item_id = (int) ($line['order_item_id'] ?? 0);
    $reservation = ((int) ($line['calendar_event_id'] ?? 0) > 0) && ((int) ($line['reservations'] ?? 0) === 1);

    for ($unit = 1; $unit <= (int) $count; $unit++) {
        $code_salt = (string) call_user_func($salt);

        $rows[] = array(
            'order_id'          => (int) $order_id,
            'order_item_id'     => $order_item_id,
            'unit'              => $unit,
            'product_id'        => (int) ($line['product_id'] ?? 0),
            'calendar_event_id' => $reservation ? (int) $line['calendar_event_id'] : 0,
            'recurrence_number' => $reservation ? (int) ($line['recurrence_number'] ?? 0) : 0,
            'code_salt'         => $code_salt,
            'code_hash'         => pg_event_ticket_code_hash(pg_event_ticket_code($order_item_id, $unit, $code_salt, $key)),
        );
    }

    return $rows;
}

/**
 * Makes a completed line's tickets: one row per unit, written once (a unit
 * that has its row keeps it, by the line and unit key). Nothing with the
 * switch off or for a line that gives none.
 *
 * @param array         $line     the pg_order_lines_sql() row
 * @param int           $order_id
 * @param bool|null     $on       null asks pg_event_tickets_on()
 * @param callable|null $query    pg_event_tickets_query() by default
 * @param string|null   $key      null is pg_event_tickets_key()
 * @return int the tickets made
 */
function pg_event_tickets_complete_line(array $line, $order_id, $on = null, $query = null, $key = null)
{
    $on = ($on === null) ? pg_event_tickets_on() : (bool) $on;
    $count = pg_event_tickets_line_count($line, $on, defined('CALENDARS') ? (CALENDARS == true) : true);

    if ($count <= 0) {
        return 0;
    }

    $key = ($key === null) ? pg_event_tickets_key() : (string) $key;
    $query = ($query === null) ? 'pg_event_tickets_query' : $query;
    $made = 0;

    foreach (pg_event_tickets_line_rows($line, $order_id, $count, $key) as $row) {
        if ($row['code_hash'] === '') {
            continue;
        }

        $changed = call_user_func($query,
            "INSERT IGNORE INTO event_tickets (
                order_id, order_item_id, unit, product_id, calendar_event_id, recurrence_number,
                code_salt, code_hash, state, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'valid', UNIX_TIMESTAMP())",
            array($row['order_id'], $row['order_item_id'], $row['unit'], $row['product_id'],
                $row['calendar_event_id'], $row['recurrence_number'], $row['code_salt'], $row['code_hash']));

        $made += ($changed === false) ? 0 : (int) $changed;
    }

    return $made;
}

/**
 * A cancelled order's tickets become void: one conditional UPDATE, so a
 * second cancel changes nothing. When it was used stays on the row.
 *
 * @param int           $order_id
 * @param callable|null $query pg_event_tickets_query() by default
 * @return int the tickets voided
 */
function pg_event_tickets_order_cancelled($order_id, $query = null)
{
    if (((int) $order_id <= 0) || (($query === null) && !pg_event_tickets_ready())) {
        return 0;
    }

    $changed = call_user_func(($query === null) ? 'pg_event_tickets_query' : $query,
        "UPDATE event_tickets
        SET state = 'void', voided_at = UNIX_TIMESTAMP()
        WHERE (order_id = ?) AND (state <> 'void')",
        array((int) $order_id));

    return ($changed === false) ? 0 : (int) $changed;
}

/**
 * A restored order's void tickets come back: used when they had been
 * checked in before the cancel, valid otherwise, by one conditional UPDATE;
 * then the units refunded returns took are voided again.
 *
 * @param int           $order_id
 * @param callable|null $query pg_event_tickets_query() by default
 * @return int the tickets given back
 */
function pg_event_tickets_order_restored($order_id, $query = null)
{
    if (((int) $order_id <= 0) || (($query === null) && !pg_event_tickets_ready())) {
        return 0;
    }

    $changed = call_user_func(($query === null) ? 'pg_event_tickets_query' : $query,
        "UPDATE event_tickets
        SET state = IF(used_at > 0, 'used', 'valid'), voided_at = 0
        WHERE (order_id = ?) AND (state = 'void')",
        array((int) $order_id));

    // A unit a refunded return took back stays void: the cancel voided it
    // along with the rest, and the restore must not give it back.
    if ($query === null) {
        $changed = ($changed === false) ? false : ((int) $changed - pg_event_tickets_returns_reapply($order_id));
    }

    return ($changed === false) ? 0 : max(0, (int) $changed);
}

/**
 * Removes the tickets of an order that is being deleted: a row left behind
 * would still pass at the door, and an order line that later gets the same
 * id would find its units taken and stay without tickets.
 *
 * @param int           $order_id
 * @param callable|null $query pg_event_tickets_query() by default
 * @return int the tickets removed
 */
function pg_event_tickets_delete_for_order($order_id, $query = null)
{
    if (((int) $order_id <= 0) || (($query === null) && !pg_event_tickets_ready())) {
        return 0;
    }

    $run = ($query === null) ? 'pg_event_tickets_query' : $query;

    // A series ticket's entries go first, while its row still names the order.
    if (($query !== null) || pg_event_ticket_checkins_ready()) {
        call_user_func($run,
            "DELETE event_ticket_checkins FROM event_ticket_checkins
            INNER JOIN event_tickets ON event_tickets.id = event_ticket_checkins.ticket_id
            WHERE event_tickets.order_id = ?",
            array((int) $order_id));
    }

    $changed = call_user_func($run,
        "DELETE FROM event_tickets WHERE order_id = ?",
        array((int) $order_id));

    return ($changed === false) ? 0 : (int) $changed;
}

/**
 * The state a ticket is left in by a cancel, a restore or a refunded
 * return, as the UPDATEs here write it. Any other move of a return (a
 * reopened one among them) leaves it as it is. Pure.
 *
 * @param string $action  cancel, restore or return
 * @param string $state   valid, used or void
 * @param int    $used_at
 * @return string
 */
function pg_event_ticket_state_after($action, $state, $used_at)
{
    if (($action === 'cancel') || ($action === 'return')) {
        return 'void';
    }

    if (($action === 'restore') && ($state === 'void')) {
        return ((int) $used_at > 0) ? 'used' : 'valid';
    }

    return (string) $state;
}

/**
 * Whether a return's move to this status voids the tickets of its units:
 * only the refund does. Approval, the parcel and the goods received back
 * leave them, and no move of a return gives a voided ticket back. Pure.
 *
 * @param string $status the status the return moves to
 * @return bool
 */
function pg_event_tickets_return_voids($status)
{
    return ((string) $status === 'refunded');
}

/**
 * Which tickets of a returned line become void: as many as the return took,
 * from those not void yet; first the ones not checked in, highest unit
 * first, and a checked-in one only when no other is left. Pure.
 *
 * @param array $tickets  the line's rows: id, unit, state
 * @param int   $quantity the units the return took of the line
 * @return array ticket ids, in the order picked
 */
function pg_event_tickets_return_pick(array $tickets, $quantity)
{
    $quantity = (int) $quantity;

    if ($quantity <= 0) {
        return array();
    }

    $valid = array();
    $used = array();

    foreach ($tickets as $ticket) {
        $state = (string) ($ticket['state'] ?? '');

        if ($state === 'valid') {
            $valid[] = $ticket;
        } elseif ($state === 'used') {
            $used[] = $ticket;
        }
    }

    $by_unit = function ($a, $b) {
        return (int) $b['unit'] - (int) $a['unit'];
    };

    usort($valid, $by_unit);
    usort($used, $by_unit);

    $ids = array();

    foreach (array_merge($valid, $used) as $ticket) {
        if (count($ids) >= $quantity) {
            break;
        }

        $ids[] = (int) $ticket['id'];
    }

    return $ids;
}

/**
 * The tickets of one order line, for the pick of a refunded return.
 *
 * @param int $order_id
 * @param int $order_item_id
 * @return array rows: id, unit, state
 */
function pg_event_tickets_line_read($order_id, $order_item_id)
{
    return (array) db_items(
        "SELECT id, unit, state, used_at FROM event_tickets
        WHERE (order_id = '" . (int) $order_id . "') AND (order_item_id = '" . (int) $order_item_id . "')
        ORDER BY unit ASC");
}

/**
 * Voids the tickets of the units a refunded return took back, line by line
 * (pg_event_tickets_return_pick()). Runs whatever the switch says, as the
 * cancel does, so a refunded place never stays valid at the door.
 *
 * @param int           $order_id
 * @param array         $quantities order item id => units returned
 * @param callable|null $query      pg_event_tickets_query() by default
 * @param callable|null $read       pg_event_tickets_line_read() by default
 * @return int the tickets voided
 */
function pg_event_tickets_lines_refunded($order_id, array $quantities, $query = null, $read = null)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || (($query === null) && !pg_event_tickets_ready())) {
        return 0;
    }

    $query = ($query === null) ? 'pg_event_tickets_query' : $query;
    $read = ($read === null) ? 'pg_event_tickets_line_read' : $read;
    $voided = 0;

    foreach ($quantities as $order_item_id => $quantity) {
        if (((int) $order_item_id <= 0) || ((int) $quantity <= 0)) {
            continue;
        }

        $ids = pg_event_tickets_return_pick((array) call_user_func($read, $order_id, (int) $order_item_id), (int) $quantity);

        if (!$ids) {
            continue;
        }

        $changed = call_user_func($query,
            "UPDATE event_tickets
            SET state = 'void', voided_at = UNIX_TIMESTAMP()
            WHERE (order_id = ?) AND (id IN (" . implode(', ', array_fill(0, count($ids), '?')) . ")) AND (state <> 'void')",
            array_merge(array($order_id), $ids));

        $voided += ($changed === false) ? 0 : (int) $changed;
    }

    return $voided;
}

/**
 * The reservation lines a refunded return gives places back for: each
 * returned line with its quantity set to the units returned, never more
 * than the line holds. Pure.
 *
 * @param array $lines      rows as pg_order_event_places() reads them, with
 *                          order_item_id
 * @param array $quantities order item id => units returned
 * @return array the returned lines
 */
function pg_event_tickets_return_place_lines(array $lines, array $quantities)
{
    $returned = array();

    foreach ($lines as $line) {
        $units = (int) ($quantities[(int) ($line['order_item_id'] ?? 0)] ?? 0);

        if ($units <= 0) {
            continue;
        }

        $line['quantity'] = min($units, (int) ($line['quantity'] ?? 0));
        $returned[] = $line;
    }

    return $returned;
}

/**
 * Gives back the event places of a refunded return's units, as a cancel
 * does (pg_order_event_places_cancelled()): only with tickets on and the
 * calendars on, for an event that takes reservations and limits its places;
 * those waiting for a date that got places are mailed.
 *
 * @param int   $order_id
 * @param array $quantities order item id => units returned
 * @return int the dates given places
 */
function pg_event_tickets_return_places($order_id, array $quantities)
{
    $order_id = (int) $order_id;
    $ids = array();

    foreach ($quantities as $order_item_id => $quantity) {
        if (((int) $order_item_id > 0) && ((int) $quantity > 0)) {
            $ids[] = (int) $order_item_id;
        }
    }

    if (($order_id <= 0) || !$ids || !pg_event_tickets_on() || !defined('CALENDARS') || !CALENDARS
        || !function_exists('pg_order_event_places')) {
        return 0;
    }

    $lines = db_items(
        "SELECT
            order_items.id AS order_item_id,
            order_items.calendar_event_id,
            order_items.recurrence_number,
            order_items.quantity,
            calendar_events.reservations,
            calendar_events.limit_reservations,
            calendar_events.recurrence,
            calendar_events.separate_reservations
        FROM order_items
        INNER JOIN calendar_events ON calendar_events.id = order_items.calendar_event_id
        WHERE (order_items.order_id = '" . $order_id . "') AND (order_items.calendar_event_id > 0)
            AND (order_items.id IN (" . implode(', ', $ids) . "))");

    $freed = array();

    foreach (pg_order_event_places(pg_event_tickets_return_place_lines((array) $lines, $quantities), true) as $place) {
        db(pg_order_event_places_sql('give', $place[0], $place[1], $place[2]));

        if (mysqli_affected_rows(db::$con) > 0) {
            $freed[] = array($place[0], $place[1]);
        }
    }

    if ($freed && function_exists('pg_stock_notify_event_freed')) {
        pg_stock_notify_event_freed($freed);
    }

    return count($freed);
}

/**
 * A refunded return's tickets and places: the returned units' tickets
 * become void and their places come back. Called once per refund, by the
 * request that moved the return to refunded (pg_return_refund()).
 *
 * @param int   $order_id
 * @param array $quantities order item id => units returned
 * @return int the tickets voided
 */
function pg_event_tickets_return_refunded($order_id, array $quantities)
{
    if (!pg_event_tickets_return_voids('refunded')) {
        return 0;
    }

    $voided = pg_event_tickets_lines_refunded($order_id, $quantities);

    pg_event_tickets_return_places($order_id, $quantities);

    return $voided;
}

/**
 * The units each line of an order gave back through refunded returns: a
 * return counts once its money went back (refunded_at > 0, closed after it
 * too).
 *
 * @param int $order_id
 * @return array order item id => units
 */
function pg_event_tickets_refunded_units($order_id)
{
    if (!function_exists('pg_returns_ready') || !pg_returns_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT order_return_items.order_item_id, SUM(order_return_items.quantity) AS quantity
        FROM order_return_items
        JOIN order_returns ON order_returns.id = order_return_items.return_id
        WHERE (order_returns.order_id = '" . (int) $order_id . "') AND (order_returns.refunded_at > 0)
        GROUP BY order_return_items.order_item_id");

    $units = array();

    foreach ((array) $rows as $row) {
        $units[(int) $row['order_item_id']] = (int) $row['quantity'];
    }

    return $units;
}

/**
 * An order's reservation lines less the units its refunded returns took:
 * those places came back with the refund, so a later cancel gives back, and
 * a restore takes again, only the rest. Never below 0. Pure.
 *
 * @param array $lines    rows with order_item_id and quantity
 * @param array $refunded order item id => units (pg_event_tickets_refunded_units())
 * @return array the lines with what is left of each quantity
 */
function pg_event_tickets_places_left(array $lines, array $refunded)
{
    foreach ($lines as $index => $line) {
        $units = (int) ($refunded[(int) ($line['order_item_id'] ?? 0)] ?? 0);

        if ($units > 0) {
            $lines[$index]['quantity'] = max(0, (int) ($line['quantity'] ?? 0) - $units);
        }
    }

    return $lines;
}

/**
 * After a restore gave every void ticket back, the units refunded returns
 * took stay void: as many per line as those returns took, by the same pick.
 *
 * @param int $order_id
 * @return int the tickets voided again
 */
function pg_event_tickets_returns_reapply($order_id)
{
    $units = pg_event_tickets_refunded_units($order_id);

    return $units ? pg_event_tickets_lines_refunded($order_id, $units) : 0;
}

/**
 * The raw token of an order's tickets link: an HMAC of the order under the
 * tickets key, drawn again whenever the link is needed. Pure.
 *
 * @param int    $order_id
 * @param string $key
 * @return string 64 hex characters, '' without an order or a key
 */
function pg_event_tickets_link_token($order_id, $key)
{
    $order_id = (int) $order_id;
    $key = (string) $key;

    if (($order_id <= 0) || ($key === '')) {
        return '';
    }

    return hash_hmac('sha256', 'event-tickets:' . $order_id, $key);
}

/**
 * Whether a tickets link's token is the order's. Pure.
 *
 * @param int    $order_id
 * @param string $token
 * @param string $key
 * @return bool
 */
function pg_event_tickets_link_token_valid($order_id, $token, $key)
{
    $expected = pg_event_tickets_link_token($order_id, $key);

    return ($expected !== '') && is_string($token) && hash_equals($expected, $token);
}

/**
 * The signed link to an order's tickets page, '' without the tickets key.
 *
 * @param int $order_id
 * @return string
 */
function pg_event_tickets_url($order_id)
{
    $token = pg_event_tickets_link_token($order_id, pg_event_tickets_key());

    if ($token === '') {
        return '';
    }

    $base = defined('URL_SCHEME') ? URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY : '';

    return $base . '/tickets.php?o=' . (int) $order_id . '&t=' . $token;
}

/**
 * The tickets mail's body: how many tickets the order holds and one link
 * to them. No QR picture: a ticket's QR is drawn only on its page. Pure.
 *
 * @param array $values name, order_number, count, url
 * @return string HTML
 */
function pg_event_tickets_mail_body(array $values)
{
    $name = trim((string) ($values['name'] ?? ''));

    $html = '<p>' . h(($name !== '')
        ? lang(array('string' => 'Hello {var:1},', 'vars' => array($name)))
        : lang('Hello,')) . '</p>';

    $html .= '<p>' . h(lang(array(
        'string' => 'Your order #{var:1} holds {var:2} tickets.',
        'vars'   => array((string) ($values['order_number'] ?? ''), (int) ($values['count'] ?? 0))))) . '</p>';

    $html .= '<p><a href="' . h((string) ($values['url'] ?? '')) . '">' . h(lang('Open your tickets')) . '</a></p>';

    $html .= '<p>' . h(lang('Show the QR code of each ticket at the entrance. Keep this link to yourself: whoever holds it can open your tickets.')) . '</p>';

    return $html;
}

/**
 * Sends a completed order's tickets mail through the mail queue, when the
 * order made tickets: one signed link to the tickets page.
 *
 * @param int    $order_id
 * @param string $order_number
 * @param string $email_address the order's billing address
 * @param string $name          the buyer's first name
 * @param int    $count         the tickets the completion made
 * @return bool queued or sent
 */
function pg_event_tickets_order_mail($order_id, $order_number, $email_address, $name, $count)
{
    $email_address = trim((string) $email_address);

    if (((int) $count <= 0) || ($email_address === '')) {
        return false;
    }

    $url = pg_event_tickets_url($order_id);

    if ($url === '') {
        return false;
    }

    return (bool) email(array(
        'to'                 => $email_address,
        'from_name'          => ORGANIZATION_NAME,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang(array('string' => 'Your tickets for order #{var:1}', 'vars' => array((string) $order_number))),
        'format'             => 'html',
        'body'               => pg_event_tickets_mail_body(array(
            'name'         => $name,
            'order_number' => $order_number,
            'count'        => (int) $count,
            'url'          => $url,
        )),
        'type'               => 'system',
        'queue'              => true,
    ));
}
