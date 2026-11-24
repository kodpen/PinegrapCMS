<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A walk-in queue desk at work (queue_desk.php): a staff member opens a
 * desk for their shift, calls the next person, calls again, marks a person
 * not here, sends a number to another service, finishes, pauses and closes
 * the desk. Roles 0-2 reach every place; a role-3 user reaches the places
 * whose staff list names them. Every move of a number is one conditional
 * UPDATE on its state, so two desks never take the same person. The rules
 * of the screen are pure; the rest needs pg_waiting_ready().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Tries a call makes when another desk took its person first. */
const PG_WAITING_CALL_TRIES = 5;

/**
 * The user ids a place's staff list names: positive, once each. Pure.
 *
 * @param array $settings the place's settings
 * @return int[]
 */
function pg_waiting_place_staff($settings)
{
    $ids = array();

    foreach ((isset($settings['staff']) && is_array($settings['staff'])) ? $settings['staff'] : array() as $id) {
        if (is_numeric($id) && ((int) $id > 0) && !in_array((int) $id, $ids, true)) {
            $ids[] = (int) $id;
        }
    }

    return $ids;
}

/**
 * Which places' desks a user reaches, shaped as pg_booking_agenda_scope():
 * null for every place (roles 0-2), the ids of the places whose staff list
 * names a role-3 user, false for none. Pure.
 *
 * @param int   $role
 * @param int   $user_id
 * @param array $places list of array(id, settings)
 * @return int[]|null|false
 */
function pg_waiting_desk_scope($role, $user_id, $places)
{
    if ((int) $role <= 2) {
        return null;
    }

    if ((int) $role !== 3) {
        return false;
    }

    $ids = array();

    foreach ($places as $place) {
        if (in_array((int) $user_id, pg_waiting_place_staff($place['settings'] ?? array()), true)) {
            $ids[] = (int) $place['id'];
        }
    }

    return $ids ?: false;
}

/**
 * Whether a scope (pg_waiting_desk_scope()) reaches a place. Pure.
 *
 * @param int[]|null|false $scope
 * @param int              $place_id
 * @return bool
 */
function pg_waiting_desk_scope_allows($scope, $place_id)
{
    if ($scope === null) {
        return true;
    }

    return is_array($scope) && in_array((int) $place_id, $scope, true);
}

/**
 * Which of the desk's buttons work now. Call next only at an open desk
 * with nobody at it and someone waiting; the number at the desk is ended
 * (Finish, Not here, sent on) before the next call or closing; Not here
 * after the place's minutes from the call. Pure.
 *
 * @param string     $state            the desk's state
 * @param array|null $current          the number called to this desk, or null
 * @param int        $now
 * @param int        $not_here_minutes
 * @param int        $waiting          numbers waiting for the desk's services
 * @return array call_next, call_again, not_here, transfer, finish, pause, resume, close => bool
 */
function pg_waiting_desk_buttons($state, $current, $now, $not_here_minutes, $waiting)
{
    $working = in_array($state, array('open', 'paused'), true);
    $busy = $working && is_array($current);

    return array(
        'call_next'  => ($state === 'open') && !$busy && ((int) $waiting > 0),
        'call_again' => $busy,
        'not_here'   => $busy && pg_waiting_not_here_allowed((int) $current['called_at'], $now, $not_here_minutes),
        'transfer'   => $busy,
        'finish'     => $busy,
        'pause'      => ($state === 'open'),
        'resume'     => ($state === 'paused'),
        'close'      => $working && !$busy,
    );
}

/**
 * A waiting number's pills: priority (with its group's name, '' when the
 * group was removed since), booked, on the way. Pure.
 *
 * @param array $ticket priority, booking_id, on_way_at
 * @param array $groups the place's priority groups
 * @return array list of array(kind, text)
 */
function pg_waiting_desk_pills($ticket, $groups)
{
    $pills = array();

    if ((string) ($ticket['priority'] ?? '') !== '') {
        $name = '';

        foreach ($groups as $group) {
            if ($group['key'] === (string) $ticket['priority']) {
                $name = (string) $group['name'];
            }
        }

        $pills[] = array('priority', $name);
    }

    if ((int) ($ticket['booking_id'] ?? 0) > 0) {
        $pills[] = array('booked', '');
    }

    if ((int) ($ticket['on_way_at'] ?? 0) > 0) {
        $pills[] = array('on_way', '');
    }

    return $pills;
}

/**
 * Whole minutes from a moment to now, 0 for none or a clock behind. Pure.
 *
 * @param int $from
 * @param int $now
 * @return int
 */
function pg_waiting_minutes_since($from, $now)
{
    return ((int) $from > 0) ? max(0, intdiv((int) $now - (int) $from, 60)) : 0;
}

/**
 * The services a number may be sent to: the place's others. Pure.
 *
 * @param array  $services the place's services
 * @param string $current  the number's service key
 * @return array
 */
function pg_waiting_transfer_services($services, $current)
{
    return array_values(array_filter($services, function ($service) use ($current) {
        return $service['key'] !== (string) $current;
    }));
}

/**
 * The places a user reaches at the desk, as pg_waiting_desk_scope() reads
 * them; every place's settings are read (a site has a few places).
 *
 * @param array $user validate_user()
 * @return int[]|null|false
 */
function pg_waiting_user_desk_scope($user)
{
    if ((int) $user['role'] <= 2) {
        return null;
    }

    $places = array();

    foreach ((array) db_items("SELECT id, settings FROM waiting_places") as $row) {
        $places[] = array('id' => (int) $row['id'], 'settings' => (array) json_decode((string) $row['settings'], true));
    }

    return pg_waiting_desk_scope($user['role'], $user['id'], $places);
}

/**
 * A place's staff list written, the rest of its settings kept.
 *
 * @param array $place pg_waiting_place_get()
 * @param int[] $ids
 * @return bool
 */
function pg_waiting_place_staff_save($place, $ids)
{
    $settings = $place['settings'];
    $settings['staff'] = pg_waiting_place_staff(array('staff' => $ids));

    return db_q("UPDATE waiting_places SET settings = ?, updated_at = ? WHERE id = ?", array(json_encode($settings), time(), (int) $place['id'])) !== false;
}

/**
 * The desk a user has open or paused at a place, or null.
 *
 * @param array $desks   pg_waiting_desks()
 * @param int   $user_id
 * @return array|null
 */
function pg_waiting_desk_of_user($desks, $user_id)
{
    foreach ($desks as $desk) {
        if (($desk['user_id'] === (int) $user_id) && in_array($desk['state'], array('open', 'paused'), true)) {
            return $desk;
        }
    }

    return null;
}

/**
 * A closed desk opened by a user, who then has it for their shift.
 *
 * @param int $desk_id
 * @param int $place_id
 * @param int $user_id
 * @return bool whether it was closed and is now theirs
 */
function pg_waiting_desk_open($desk_id, $place_id, $user_id)
{
    $now = time();

    if (db_q("UPDATE waiting_desks SET state = 'open', user_id = ?, opened_at = ?, updated_at = ? WHERE id = ? AND state = 'closed' AND place_id = ?",
        array((int) $user_id, $now, $now, (int) $desk_id, (int) $place_id)) === false) {
        return false;
    }

    return mysqli_affected_rows(db::$con) > 0;
}

/**
 * A user's desk set to another state: open, paused or closed (which frees
 * it). Closing waits for the number at the desk to be ended.
 *
 * @param array  $desk
 * @param string $state open, paused or closed
 * @return bool
 */
function pg_waiting_desk_set_state($desk, $state)
{
    if (!in_array($state, array('open', 'paused', 'closed'), true)) {
        return false;
    }

    if (($state === 'closed') && pg_waiting_desk_current($desk['id'])) {
        return false;
    }

    $set = ($state === 'closed') ? "state = 'closed', user_id = 0, opened_at = 0" : "state = '" . $state . "'";

    return db_q("UPDATE waiting_desks SET " . $set . ", updated_at = ? WHERE id = ? AND user_id = ?", array(time(), (int) $desk['id'], (int) $desk['user_id'])) !== false;
}

/**
 * The number called to a desk and not yet ended, or null.
 *
 * @param int $desk_id
 * @return array|null the row
 */
function pg_waiting_desk_current($desk_id)
{
    $row = db_item("SELECT * FROM waiting_tickets WHERE desk_id = '" . (int) $desk_id . "' AND state = 'called' ORDER BY called_at DESC LIMIT 1");

    return $row ?: null;
}

/**
 * Today's waiting numbers of a place in calling order, kept to some
 * services when given.
 *
 * @param array         $place
 * @param string[]|null $services null for all
 * @param int|null      $now
 * @return array rows
 */
function pg_waiting_desk_waiting($place, $services = null, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;
    $rows = (array) db_items("SELECT id, service, letter, number, joined_at, priority, booking_id, booking_at, on_way_at, moved_back, called_at, name, notices FROM waiting_tickets WHERE place_id = '" . (int) $place['id'] . "' AND state = 'waiting' AND day = '" . pg_waiting_today($now) . "'");

    if ($services !== null) {
        $rows = array_filter($rows, function ($row) use ($services) {
            return in_array($row['service'], $services, true);
        });
    }

    return pg_waiting_order(array_values($rows), (int) $place['settings']['grace_minutes'] * 60);
}

/**
 * "Call next": the first in calling order among the desk's services is
 * taken with one conditional UPDATE; when another desk took that person
 * first, the next one is tried. The person is told with the desk's name
 * and those behind may now be few enough ahead to be told.
 *
 * @param array    $place
 * @param array    $desk    an open desk
 * @param int      $user_id
 * @param int|null $now
 * @return array|null the number called, or null with nobody to call
 */
function pg_waiting_call_next($place, $desk, $user_id, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;

    if (($desk['state'] !== 'open') || pg_waiting_desk_current($desk['id'])) {
        return null;
    }

    $grace = (int) $place['settings']['grace_minutes'] * 60;
    $skip = array();

    for ($try = 0; $try < PG_WAITING_CALL_TRIES; $try++) {
        $waiting = array_filter(pg_waiting_desk_waiting($place, $desk['services'], $now), function ($row) use ($skip) {
            return !in_array((int) $row['id'], $skip, true);
        });
        $next = pg_waiting_next(array_values($waiting), $desk['services'], $grace);

        if ($next === null) {
            return null;
        }

        $taken = db_q("UPDATE waiting_tickets SET state = 'called', desk_id = ?, staff_user_id = ?, called_at = ? WHERE id = ? AND state = 'waiting'",
            array((int) $desk['id'], (int) $user_id, $now, (int) $next['id']));

        if (($taken !== false) && (mysqli_affected_rows(db::$con) > 0)) {
            $ticket = db_item("SELECT * FROM waiting_tickets WHERE id = '" . (int) $next['id'] . "'");
            pg_waiting_notify($ticket, $place, 'called', array('desk' => $desk['name']));
            pg_waiting_notify_ahead($place, $ticket['service'], $now);

            return $ticket;
        }

        $skip[] = (int) $next['id'];
    }

    return null;
}

/**
 * "Call again": the number at the desk is called anew; its call time moves
 * to now, so Not here counts from the last call and the lobby screen sees
 * a new call. No second message: a number gets at most three.
 *
 * @param array    $ticket the number at the desk
 * @param int|null $now
 * @return bool
 */
function pg_waiting_call_again($ticket, $now = null)
{
    return pg_waiting_ticket_move($ticket, "called_at = '" . (($now === null) ? time() : (int) $now) . "'");
}

/**
 * "Finish": the number at the desk was served.
 *
 * @param array    $ticket
 * @param int|null $now
 * @return bool
 */
function pg_waiting_finish($ticket, $now = null)
{
    return pg_waiting_ticket_move($ticket, "state = 'served', done_at = '" . (($now === null) ? time() : (int) $now) . "'");
}

/**
 * "Not here", offered the place's minutes after the call: the person
 * leaves as not here, or, once and when the place moves people back,
 * waits again, standing behind those already waiting.
 *
 * @param array    $ticket
 * @param array    $place
 * @param int|null $now
 * @return string '' when refused, else no_show or moved_back
 */
function pg_waiting_not_here($ticket, $place, $now = null)
{
    $now = ($now === null) ? time() : (int) $now;

    if (!pg_waiting_not_here_allowed((int) $ticket['called_at'], $now, (int) $place['settings']['not_here_minutes'])) {
        return '';
    }

    if (pg_waiting_not_here_outcome((int) $ticket['moved_back'], (int) $place['settings']['move_back']) === 'moved_back') {
        // called_at keeps the moment of the move: the order rule stands the
        // number there (pg_waiting_order_time()).
        return pg_waiting_ticket_move($ticket, "state = 'waiting', moved_back = 1, desk_id = 0, staff_user_id = 0, called_at = '" . $now . "'") ? 'moved_back' : '';
    }

    return pg_waiting_ticket_move($ticket, "state = 'no_show', done_at = '" . $now . "'") ? 'no_show' : '';
}

/**
 * "Send to another service": the number waits again in that service and
 * keeps its time joined, so it stands where its arrival puts it. It takes
 * the service's letter and next count of the day, as a new number would.
 *
 * @param array  $ticket the number at the desk
 * @param array  $place
 * @param string $service the other service's key
 * @return string the new number, '' when refused
 */
function pg_waiting_transfer($ticket, $place, $service)
{
    $target = null;

    foreach (pg_waiting_transfer_services($place['services'], $ticket['service']) as $item) {
        if ($item['key'] === (string) $service) {
            $target = $item;
        }
    }

    if ($target === null) {
        return '';
    }

    for ($try = 0; $try < PG_WAITING_CALL_TRIES; $try++) {
        $count = pg_waiting_next_count(db_value("SELECT MAX(number) FROM waiting_tickets WHERE place_id = '" . (int) $place['id'] . "' AND day = '" . (int) $ticket['day'] . "' AND service = '" . e($target['key']) . "'"));
        $moved = db_q("UPDATE waiting_tickets SET state = 'waiting', service = ?, letter = ?, number = ?, desk_id = 0, staff_user_id = 0, called_at = 0 WHERE id = ? AND desk_id = ? AND state = 'called'",
            array($target['key'], $target['letter'], $count, (int) $ticket['id'], (int) $ticket['desk_id']));

        // A clash on the day's count with a number taken at that moment
        // fails the statement: the next count is tried.
        if ($moved !== false) {
            return (mysqli_affected_rows(db::$con) > 0) ? pg_waiting_number($target['letter'], $count) : '';
        }
    }

    return '';
}

/**
 * One move of the number at a desk: written only while it is still
 * called to that desk, so a second click or another screen changes
 * nothing.
 *
 * @param array  $ticket
 * @param string $set    the SET list, built from trusted values
 * @return bool
 */
function pg_waiting_ticket_move($ticket, $set)
{
    if (db_q("UPDATE waiting_tickets SET " . $set . " WHERE id = ? AND desk_id = ? AND state = 'called'", array((int) $ticket['id'], (int) $ticket['desk_id'])) === false) {
        return false;
    }

    return mysqli_affected_rows(db::$con) > 0;
}
