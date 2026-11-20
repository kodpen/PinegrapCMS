<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Event ticket check-in at the door (event_check_in.php): staff pick an
 * event's date or a ticket product, then scan a ticket's QR code with the
 * phone's camera or type its code. A valid ticket is marked used by one
 * conditional UPDATE on its row, so two phones scanning the same ticket at
 * once mark it once and the second gets "used before". Another event or
 * date, a void ticket and an unknown code each get their own answer. The
 * screen counts checked in against sold for the pick, finds a guest by
 * name when the code is missing, and lets a manager undo a check-in.
 *
 * No new right: an event's tickets are checked in by whoever may manage
 * that calendar (validate_calendar_event_access()), a product's by whoever
 * manages commerce. Loaded by the screen only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The guest's name follows the tickets page's rule
// (pg_event_ticket_guest_name()), so the door names whom the ticket names.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/event_tickets_page.php');

/** The most rows a name search answers. */
define('PG_CHECK_IN_SEARCH_LIMIT', 30);

/**
 * Reads a pick as the screen posts it: "e-<event>-<date number>" for an
 * event's date, "p-<product>" for a ticket product. Pure.
 *
 * @param mixed $value
 * @return array|null kind (event or product), event_id, recurrence_number,
 *                    product_id; null when it is not a pick
 */
function pg_check_in_pick_parse($value)
{
    if (!is_string($value)) {
        return null;
    }

    if (preg_match('/^e-([1-9][0-9]{0,9})-([0-9]{1,9})$/', $value, $m) === 1) {
        return array('kind' => 'event', 'event_id' => (int) $m[1], 'recurrence_number' => (int) $m[2], 'product_id' => 0);
    }

    if (preg_match('/^p-([1-9][0-9]{0,9})$/', $value, $m) === 1) {
        return array('kind' => 'product', 'event_id' => 0, 'recurrence_number' => 0, 'product_id' => (int) $m[1]);
    }

    return null;
}

/**
 * A pick written back as the screen posts it. Pure.
 *
 * @param array $pick pg_check_in_pick_parse()
 * @return string
 */
function pg_check_in_pick_value(array $pick)
{
    return ($pick['kind'] === 'event')
        ? 'e-' . (int) $pick['event_id'] . '-' . (int) $pick['recurrence_number']
        : 'p-' . (int) $pick['product_id'];
}

/**
 * Whether a ticket belongs to the pick: an event's ticket to its event and
 * date, a product's ticket (no event) to its product. Pure.
 *
 * @param array $ticket the event_tickets row
 * @param array $pick   pg_check_in_pick_parse()
 * @return bool
 */
function pg_check_in_ticket_matches(array $ticket, array $pick)
{
    $event_id = (int) ($ticket['calendar_event_id'] ?? 0);

    if ($pick['kind'] === 'event') {
        return ($event_id === (int) $pick['event_id'])
            && ((int) ($ticket['recurrence_number'] ?? 0) === (int) $pick['recurrence_number']);
    }

    return ($event_id === 0) && ((int) ($ticket['product_id'] ?? 0) === (int) $pick['product_id']);
}

/**
 * Whether a user may check in a ticket or work on a pick: an event's for
 * whoever may manage that calendar, a product's for whoever manages
 * commerce. Pure; the calendar check is handed in.
 *
 * @param int      $event_id       0 for a product's ticket
 * @param array    $user           role, manage_ecommerce, manage_calendars
 * @param callable $event_access   function (int $event_id): bool, the
 *                                 calendar right on that event
 * @return bool
 */
function pg_check_in_allowed($event_id, array $user, $event_access)
{
    $role = (int) ($user['role'] ?? 3);

    if ((int) $event_id > 0) {
        return ($role < 3) || (!empty($user['manage_calendars']) && (bool) call_user_func($event_access, (int) $event_id));
    }

    return ($role < 3) || !empty($user['manage_ecommerce']);
}

/**
 * Whether a user may undo a check-in: a manager (role 2) or above, who
 * also may check that ticket in. Pure.
 *
 * @param array $user role
 * @return bool
 */
function pg_check_in_may_undo(array $user)
{
    return (int) ($user['role'] ?? 3) <= 2;
}

/**
 * The answer a ticket gets at the door, before anything is written: unknown
 * (no ticket has the code), denied (the user may not check it in), void,
 * other (another event, date or product), used (checked in before) or
 * valid. Pure.
 *
 * @param array|null $ticket  the row, null for an unknown code
 * @param array      $pick    pg_check_in_pick_parse()
 * @param bool       $allowed pg_check_in_allowed() for the ticket
 * @return string
 */
function pg_check_in_answer($ticket, array $pick, $allowed)
{
    if (!is_array($ticket)) {
        return 'unknown';
    }

    if (!$allowed) {
        return 'denied';
    }

    if ((string) ($ticket['state'] ?? '') === 'void') {
        return 'void';
    }

    if (!pg_check_in_ticket_matches($ticket, $pick)) {
        return 'other';
    }

    return ((string) ($ticket['state'] ?? '') === 'used') ? 'used' : 'valid';
}

/**
 * Marks a valid ticket used: one conditional UPDATE, so of two scans at
 * once only one changes the row; the other changes none and is told the
 * ticket was used before.
 *
 * @param int           $ticket_id
 * @param int           $user_id
 * @param callable|null $query pg_event_tickets_query() by default
 * @return bool whether this call marked it
 */
function pg_check_in_mark_used($ticket_id, $user_id, $query = null)
{
    if ((int) $ticket_id <= 0) {
        return false;
    }

    $changed = call_user_func(($query === null) ? 'pg_event_tickets_query' : $query,
        "UPDATE event_tickets
        SET state = 'used', used_at = UNIX_TIMESTAMP(), used_by = ?
        WHERE (id = ?) AND (state = 'valid')",
        array((int) $user_id, (int) $ticket_id));

    return ($changed !== false) && ((int) $changed === 1);
}

/**
 * Undoes a check-in: a used ticket becomes valid again, by one conditional
 * UPDATE, so a void ticket stays void.
 *
 * @param int           $ticket_id
 * @param callable|null $query pg_event_tickets_query() by default
 * @return bool whether this call changed it
 */
function pg_check_in_undo($ticket_id, $query = null)
{
    if ((int) $ticket_id <= 0) {
        return false;
    }

    $changed = call_user_func(($query === null) ? 'pg_event_tickets_query' : $query,
        "UPDATE event_tickets
        SET state = 'valid', used_at = 0, used_by = 0
        WHERE (id = ?) AND (state = 'used')",
        array((int) $ticket_id));

    return ($changed !== false) && ((int) $changed === 1);
}

/**
 * Checks a ticket in: finds it, answers it, and marks a valid one used.
 * When the UPDATE changes no row, the row is read again and answered as it
 * is then: "used" after a scan on another phone, "void" after a cancel,
 * "unknown" after a deletion, "failed" when the write itself failed.
 *
 * @param callable $find   function (): array|null, the ticket row
 * @param array    $pick   pg_check_in_pick_parse()
 * @param array    $user   id, role, manage_ecommerce, manage_calendars
 * @param callable $event_access function (int $event_id): bool
 * @param callable|null $query pg_event_tickets_query() by default
 * @return array answer (pg_check_in_answer() or failed), ticket (the row as
 *               read after the write, or null)
 */
function pg_check_in_run($find, array $pick, array $user, $event_access, $query = null)
{
    $ticket = call_user_func($find);
    $allowed = is_array($ticket) && pg_check_in_allowed((int) ($ticket['calendar_event_id'] ?? 0), $user, $event_access);
    $answer = pg_check_in_answer($ticket, $pick, $allowed);

    if ($answer === 'valid') {
        $marked = pg_check_in_mark_used((int) $ticket['id'], (int) ($user['id'] ?? 0), $query);
        $ticket = call_user_func($find);

        // The write lost: the row changed after it was read (another phone
        // checked it in, its order was cancelled or deleted), so the answer
        // is the row's as read again. A row that still reads valid was not
        // marked because the write failed: "failed", never "used".
        if (!$marked) {
            $answer = pg_check_in_answer($ticket, $pick, $allowed);
            $answer = ($answer === 'valid') ? 'failed' : $answer;
        }
    }

    if (($answer === 'unknown') || ($answer === 'denied')) {
        $ticket = null;
    }

    return array('answer' => $answer, 'ticket' => $ticket);
}

/**
 * What ticket.checked_in carries for a ticket just marked used: the ticket,
 * its order and line, what it is for, and when and by whom it was checked
 * in. Never its code nor the code's hash. Pure.
 *
 * @param array $ticket the row as read after the check-in
 * @return array
 */
function pg_check_in_ticket_event(array $ticket)
{
    $used_at = (int) ($ticket['used_at'] ?? 0);
    $product_id = (int) ($ticket['product_id'] ?? 0);

    return array(
        'id'                => (int) ($ticket['id'] ?? 0),
        'order_id'          => (int) ($ticket['order_id'] ?? 0),
        'order_number'      => (int) ($ticket['order_number'] ?? 0),
        'order_item_id'     => (int) ($ticket['order_item_id'] ?? 0),
        'unit'              => (int) ($ticket['unit'] ?? 0),
        'product_id'        => ($product_id > 0) ? $product_id : null,
        'calendar_event_id' => (int) ($ticket['calendar_event_id'] ?? 0),
        'recurrence_number' => (int) ($ticket['recurrence_number'] ?? 0),
        'checked_in_at'     => ($used_at > 0) ? gmdate('Y-m-d\TH:i:s\Z', $used_at) : null,
        'checked_in_by'     => (int) ($ticket['used_by'] ?? 0),
    );
}

/**
 * The guest's name on a ticket: the first and last name the product form
 * asked for that unit, else the buyer's. Pure.
 *
 * @param array $form  contact_field => value of the unit's form
 * @param array $buyer billing_first_name, billing_last_name
 * @return string
 */
function pg_check_in_guest_name(array $form, array $buyer)
{
    $name = trim(trim((string) ($form['first_name'] ?? '')) . ' ' . trim((string) ($form['last_name'] ?? '')));

    if ($name !== '') {
        return $name;
    }

    return trim(trim((string) ($buyer['billing_first_name'] ?? '')) . ' ' . trim((string) ($buyer['billing_last_name'] ?? '')));
}

/**
 * Checked in against sold from the state counts of a pick: void tickets
 * are not sold. Pure.
 *
 * @param array $rows state, n
 * @return array checked_in, sold
 */
function pg_check_in_counts_from_rows(array $rows)
{
    $by_state = array('valid' => 0, 'used' => 0);

    foreach ($rows as $row) {
        $state = (string) ($row['state'] ?? '');

        if (isset($by_state[$state])) {
            $by_state[$state] += (int) ($row['n'] ?? 0);
        }
    }

    return array('checked_in' => $by_state['used'], 'sold' => $by_state['used'] + $by_state['valid']);
}

/**
 * The SQL condition and values of a pick on event_tickets as t. Pure.
 *
 * @param array $pick
 * @return array sql, params
 */
function pg_check_in_pick_where(array $pick)
{
    if ($pick['kind'] === 'event') {
        return array('sql' => '(t.calendar_event_id = ?) AND (t.recurrence_number = ?)',
            'params' => array((int) $pick['event_id'], (int) $pick['recurrence_number']));
    }

    return array('sql' => '(t.calendar_event_id = 0) AND (t.product_id = ?)', 'params' => array((int) $pick['product_id']));
}

/**
 * Reads the rows of a statement with values.
 *
 * @param string $sql
 * @param array  $params
 * @return array
 */
function pg_check_in_rows($sql, array $params)
{
    $result = db_q($sql, $params);
    $rows = array();

    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/** The columns a ticket is read with, its order's and its checker's. */
define('PG_CHECK_IN_TICKET_COLUMNS', "t.id, t.order_id, t.order_item_id, t.unit, t.product_id, t.calendar_event_id,
    t.recurrence_number, t.state, t.used_at, t.used_by, orders.order_number, orders.billing_first_name,
    orders.billing_last_name, user.user_username AS used_by_name,
    (SELECT COUNT(*) FROM event_tickets AS s WHERE s.order_item_id = t.order_item_id) AS line_tickets");

/**
 * A ticket by the hash of its code, or by its id.
 *
 * @param string $column code_hash or id
 * @param mixed  $value
 * @return array|null
 */
function pg_check_in_ticket_find($column, $value)
{
    $column = ($column === 'id') ? 't.id' : 't.code_hash';

    if (($column === 't.code_hash') && ((string) $value === '')) {
        return null;
    }

    $rows = pg_check_in_rows(
        "SELECT " . PG_CHECK_IN_TICKET_COLUMNS . "
        FROM event_tickets AS t
        LEFT JOIN orders ON orders.id = t.order_id
        LEFT JOIN user ON user.user_id = t.used_by
        WHERE " . $column . " = ?
        LIMIT 1",
        array(($column === 't.id') ? (int) $value : (string) $value));

    return $rows ? pg_check_in_with_names($rows)[0] : null;
}

/**
 * Adds each ticket's guest name: one query for the units' product forms.
 *
 * @param array $tickets
 * @return array
 */
function pg_check_in_with_names(array $tickets)
{
    $line_ids = array();

    foreach ($tickets as $ticket) {
        $line_ids[(int) $ticket['order_item_id']] = true;
    }

    $rows = array();

    if ($line_ids) {
        $ids = array_keys($line_ids);
        $rows = pg_check_in_rows(
            "SELECT form_data.order_item_id, form_data.quantity_number, form_data.name, form_data.data, form_fields.contact_field
            FROM form_data
            LEFT JOIN form_fields ON form_fields.id = form_data.form_field_id
            WHERE form_data.order_item_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
            ORDER BY form_data.id",
            $ids);
    }

    return pg_check_in_names_from_forms($tickets, $rows);
}

/**
 * Each ticket with its guest name: the one the tickets page shows
 * (pg_event_ticket_guest_name() on the unit's product form), else the
 * buyer's. Pure.
 *
 * @param array $tickets   rows with order_item_id, unit, billing_first_name,
 *                         billing_last_name
 * @param array $form_rows the lines' form_data rows with contact_field
 * @return array the tickets with guest_name
 */
function pg_check_in_names_from_forms(array $tickets, array $form_rows)
{
    $guests = pg_event_tickets_guest_names_from_rows($form_rows);

    foreach ($tickets as $i => $ticket) {
        $guest = (string) ($guests[(int) $ticket['order_item_id']][(int) $ticket['unit']] ?? '');
        $tickets[$i]['guest_name'] = ($guest !== '') ? $guest : pg_check_in_guest_name(array(), $ticket);
    }

    return $tickets;
}

/**
 * Checked in against sold for a pick.
 *
 * @param array $pick
 * @return array checked_in, sold
 */
function pg_check_in_counts(array $pick)
{
    $where = pg_check_in_pick_where($pick);

    return pg_check_in_counts_from_rows(pg_check_in_rows(
        "SELECT t.state, COUNT(*) AS n FROM event_tickets AS t WHERE " . $where['sql'] . " GROUP BY t.state",
        $where['params']));
}

/**
 * The pick's tickets whose guest or buyer name holds the text, or whose
 * order number is it; void tickets left out.
 *
 * @param array  $pick
 * @param string $text
 * @return array
 */
function pg_check_in_search(array $pick, $text)
{
    $text = trim((string) $text);

    if (mb_strlen($text) < 2) {
        return array();
    }

    $search = pg_check_in_search_sql($pick, $text);

    return pg_check_in_with_names(pg_check_in_rows($search['sql'], $search['params']));
}

/**
 * The name search's statement and values: the buyer's name, the order
 * number, or a name in the unit's product form, read by the tickets page's
 * rule (the contact's name fields, or a field named for the guest,
 * PG_EVENT_TICKET_GUEST_FIELD_PATTERN). Pure.
 *
 * @param array  $pick
 * @param string $text the trimmed search text
 * @return array sql, params
 */
function pg_check_in_search_sql(array $pick, $text = '')
{
    $like = '%' . escape_like(mb_substr((string) $text, 0, 100)) . '%';
    $where = pg_check_in_pick_where($pick);

    return array(
        'sql'    => "SELECT " . PG_CHECK_IN_TICKET_COLUMNS . "
        FROM event_tickets AS t
        LEFT JOIN orders ON orders.id = t.order_id
        LEFT JOIN user ON user.user_id = t.used_by
        WHERE
            " . $where['sql'] . "
            AND (t.state <> 'void')
            AND (
                (CONCAT(orders.billing_first_name, ' ', orders.billing_last_name) LIKE ?)
                OR (orders.order_number = ?)
                OR EXISTS (
                    SELECT 1 FROM form_data
                    LEFT JOIN form_fields ON form_fields.id = form_data.form_field_id
                    WHERE
                        (form_data.order_item_id = t.order_item_id)
                        AND (form_data.quantity_number = t.unit)
                        AND (
                            (form_fields.contact_field IN ('first_name', 'last_name'))
                            OR (LOWER(form_data.name) REGEXP ?))
                        AND (form_data.data LIKE ?)))
        ORDER BY orders.billing_last_name, orders.billing_first_name, t.order_item_id, t.unit
        LIMIT " . PG_CHECK_IN_SEARCH_LIMIT,
        'params' => array_merge($where['params'], array($like, (string) $text, PG_EVENT_TICKET_GUEST_FIELD_PATTERN, $like)),
    );
}

/**
 * The query of the event dates that hold tickets, 500 at most. The dates
 * whose tickets were sold last come first: the ended ones are dropped only
 * after the cap, so without an order the oldest dates filled it and a new
 * event never reached the pick list. Pure.
 *
 * @return string
 */
function pg_check_in_picks_groups_sql()
{
    return "SELECT calendar_event_id, recurrence_number
        FROM event_tickets
        WHERE (calendar_event_id > 0) AND (state <> 'void')
        GROUP BY calendar_event_id, recurrence_number
        ORDER BY MAX(id) DESC
        LIMIT 500";
}

/**
 * The event dates and products a user may check tickets in for, from the
 * tickets sold: an event's date with its name, date and place, a product
 * with its name. An event that ended more than a day ago is left out.
 *
 * @param array    $user
 * @param callable $event_access function (int $event_id): bool
 * @return array rows of value, label, detail
 */
function pg_check_in_picks(array $user, $event_access)
{
    $picks = array();
    $dated = array();

    $groups = pg_check_in_rows(pg_check_in_picks_groups_sql(), array());

    foreach ($groups as $group) {
        $event_id = (int) $group['calendar_event_id'];

        if (!pg_check_in_allowed($event_id, $user, $event_access) || !function_exists('get_calendar_event')) {
            continue;
        }

        $event = get_calendar_event($event_id, (int) $group['recurrence_number']);

        if (!is_array($event) || (strtotime((string) $event['end_date_and_time']) < time() - 86400)) {
            continue;
        }

        $dated[] = array(
            'value'  => pg_check_in_pick_value(array('kind' => 'event', 'event_id' => $event_id, 'recurrence_number' => (int) $group['recurrence_number'])),
            'label'  => (string) $event['name'],
            'detail' => trim(pg_check_in_event_date($event) . (((string) $event['location'] !== '') ? ' · ' . strip_tags((string) $event['location']) : '')),
            'start'  => (string) $event['start_date_and_time'],
        );
    }

    usort($dated, function ($a, $b) {
        return strcmp($a['start'], $b['start']);
    });

    foreach ($dated as $row) {
        unset($row['start']);
        $picks[] = $row;
    }

    if (pg_check_in_allowed(0, $user, $event_access)) {
        $products = pg_check_in_rows(
            "SELECT t.product_id, products.short_description, products.name
            FROM event_tickets AS t
            LEFT JOIN products ON products.id = t.product_id
            WHERE (t.calendar_event_id = 0) AND (t.product_id > 0) AND (t.state <> 'void')
            GROUP BY t.product_id, products.short_description, products.name
            ORDER BY products.short_description
            LIMIT 200", array());

        foreach ($products as $row) {
            $picks[] = array(
                'value'  => pg_check_in_pick_value(array('kind' => 'product', 'event_id' => 0, 'recurrence_number' => 0, 'product_id' => (int) $row['product_id'])),
                'label'  => ((string) $row['short_description'] !== '') ? (string) $row['short_description'] : (string) $row['name'],
                'detail' => lang('Ticket product'),
            );
        }
    }

    return $picks;
}

/**
 * The words an answer is shown with: a title, a line under it and a tone.
 *
 * @param string     $answer pg_check_in_answer()
 * @param array|null $ticket the row with guest_name
 * @param string     $for    for another event: the event or product the
 *                           ticket is for
 * @return array pill, title, detail, tone
 */
function pg_check_in_answer_text($answer, $ticket, $for = '')
{
    $name = is_array($ticket) ? (string) ($ticket['guest_name'] ?? '') : '';
    $of = is_array($ticket) ? lang(array('string' => 'ticket {var:1} of {var:2}', 'vars' => array((int) $ticket['unit'], max((int) $ticket['unit'], (int) ($ticket['line_tickets'] ?? 0))))) : '';
    $order = is_array($ticket) ? lang(array('string' => 'Order #{var:1}', 'vars' => array((string) ($ticket['order_number'] ?? '')))) : '';

    switch ($answer) {
        case 'valid':
            return array('pill' => lang('Checked in'), 'title' => lang('Valid ticket'), 'detail' => trim($name . ' · ' . $of . ' · ' . $order, ' ·'), 'tone' => 'teal');

        case 'used':
            $when = ((int) ($ticket['used_at'] ?? 0) > 0) ? strip_tags((string) get_absolute_time(array('timestamp' => (int) $ticket['used_at']))) : '';
            $by = (string) ($ticket['used_by_name'] ?? '');

            return array(
                'pill'   => lang('Used'),
                'title'  => lang('Already checked in'),
                'detail' => trim($name . ' · ' . $of . ' · ' . (($by !== '')
                    ? lang(array('string' => 'checked in {var:1} by {var:2}', 'vars' => array($when, $by)))
                    : lang(array('string' => 'checked in {var:1}', 'vars' => array($when)))), ' ·'),
                'tone'   => 'amber',
            );

        case 'other':
            return array('pill' => lang('Other event'), 'title' => lang('Ticket for another event or date'), 'detail' => trim($for . ' · ' . $name . ' · ' . $order, ' ·'), 'tone' => 'rose');

        case 'void':
            return array('pill' => lang('Voided'), 'title' => lang('Void ticket'), 'detail' => trim(lang('Its order was cancelled.') . ' ' . $order), 'tone' => 'rose');

        case 'failed':
            return array('pill' => lang('Not checked in'), 'title' => lang('The ticket could not be checked in'), 'detail' => lang('Scan it again.'), 'tone' => 'rose');

        case 'denied':
            return array('pill' => lang('Not allowed'), 'title' => lang('You may not check in this ticket'), 'detail' => lang('Its event or product is not one you manage.'), 'tone' => 'rose');
    }

    return array('pill' => lang('Unknown'), 'title' => lang('Unknown code'), 'detail' => lang('No ticket has this code. Check it and type it again.'), 'tone' => 'rose');
}

/**
 * An event date's start in the panel's language, day and month names
 * translated (get_absolute_time()).
 *
 * @param array $event get_calendar_event()
 * @return string
 */
function pg_check_in_event_date(array $event)
{
    $start = strtotime((string) ($event['start_date_and_time'] ?? ''));

    if (!$start) {
        return '';
    }

    return trim(strip_tags((string) get_absolute_time(array('timestamp' => $start, 'size' => 'long'))));
}

/**
 * What a ticket is for, as a wrong-event answer names it: the event's name
 * and date, or the product's name.
 *
 * @param array $ticket
 * @return string
 */
function pg_check_in_ticket_for(array $ticket)
{
    $event_id = (int) ($ticket['calendar_event_id'] ?? 0);

    if ($event_id > 0) {
        $event = function_exists('get_calendar_event') ? get_calendar_event($event_id, (int) ($ticket['recurrence_number'] ?? 0)) : false;

        return is_array($event) ? trim((string) $event['name'] . ', ' . pg_check_in_event_date($event), ', ') : '';
    }

    return (string) db_value("SELECT short_description FROM products WHERE id = '" . (int) ($ticket['product_id'] ?? 0) . "'");
}

/**
 * A ticket as the screen lists it after a name search.
 *
 * @param array $ticket
 * @return array
 */
function pg_check_in_ticket_out(array $ticket)
{
    return array(
        'id'     => (int) $ticket['id'],
        'name'   => (string) ($ticket['guest_name'] ?? ''),
        'of'     => lang(array('string' => 'ticket {var:1} of {var:2}', 'vars' => array((int) $ticket['unit'], max((int) $ticket['unit'], (int) ($ticket['line_tickets'] ?? 0))))),
        'order'  => lang(array('string' => 'Order #{var:1}', 'vars' => array((string) ($ticket['order_number'] ?? '')))),
        'state'  => (string) $ticket['state'],
    );
}
