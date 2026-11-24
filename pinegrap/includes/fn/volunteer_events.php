<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The webhook and Workspace events of volunteer shifts: an application
 * came in (volunteer.applied), an application was approved
 * (volunteer.approved), a volunteer took a place (shift.signed_up), a
 * shift short of volunteers got its first call (shift.unfilled), a
 * volunteer checked in (volunteer.checked_in) and hours were approved
 * (volunteer.hours_approved). The payload is built in one pure function;
 * the announcing helpers read the rows after the write and outside the
 * shift's lock. A volunteer's documents are never part of an event.
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
 * A moment as ISO 8601 UTC, null for none: a unix stamp as it is, a
 * 'YYYY-MM-DD HH:MM:SS' wall-clock time of the site read in its time zone.
 * Pure.
 *
 * @param mixed $value
 * @return string|null
 */
function pg_volunteer_event_time($value)
{
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        return ((int) $value > 0) ? gmdate('Y-m-d\TH:i:s\Z', (int) $value) : null;
    }

    $time = (is_string($value) && ($value !== '')) ? strtotime($value) : false;

    return ($time !== false) ? gmdate('Y-m-d\TH:i:s\Z', $time) : null;
}

/**
 * The data of a volunteer event, from one flat row (pg_volunteer_event_row()
 * or a shift's call). Each event's keys are fixed; the fields and samples
 * are in api_webhook_event_payloads_site(). Pure.
 *
 * @param string $event
 * @param array  $row
 * @return array
 */
function pg_volunteer_event_payload($event, array $row)
{
    $int = function ($key) use ($row) {
        return (int) ($row[$key] ?? 0);
    };
    $id = function ($key) use ($row) {
        return ((int) ($row[$key] ?? 0) > 0) ? (int) $row[$key] : null;
    };
    $text = function ($key) use ($row) {
        return (string) ($row[$key] ?? '');
    };
    $roles = is_array($row['programme_roles'] ?? null) ? $row['programme_roles'] : (array) json_decode((string) ($row['programme_roles'] ?? ''), true);
    $role = $text('role');
    $role_name = (($role !== '') && isset($roles[$role]['name'])) ? (string) $roles[$role]['name'] : '';

    switch ($event) {
        case 'volunteer.applied':
            return array(
                'id'           => $int('volunteer_id'),
                'programme_id' => $int('programme_id'),
                'programme'    => $text('programme'),
                'contact_id'   => $id('contact_id'),
                'form_id'      => $id('form_id'),
                'skills'       => implode(', ', pg_volunteer_list_text((array) json_decode($text('skills'), true))),
                'applied_at'   => pg_volunteer_event_time($row['applied_at'] ?? 0),
            );

        case 'volunteer.approved':
            return array(
                'id'           => $int('volunteer_id'),
                'programme_id' => $int('programme_id'),
                'programme'    => $text('programme'),
                'contact_id'   => $id('contact_id'),
                'approved_by'  => $id('approved_by'),
                'approved_at'  => pg_volunteer_event_time($row['approved_at'] ?? 0),
            );

        case 'shift.signed_up':
            return array(
                'id'           => $int('signup_id'),
                'shift_id'     => $int('shift_id'),
                'programme_id' => $int('programme_id'),
                'programme'    => $text('programme'),
                'volunteer_id' => $int('volunteer_id'),
                'contact_id'   => $id('contact_id'),
                'role'         => $role,
                'role_name'    => $role_name,
                'place'        => $text('place'),
                'start_at'     => pg_volunteer_event_time($text('start_time')),
                'end_at'       => pg_volunteer_event_time($text('end_time')),
                'how'          => in_array($text('how'), array('sign_up', 'waitlist', 'swap'), true) ? $text('how') : 'sign_up',
            );

        case 'shift.unfilled':
            return array(
                'id'           => $int('shift_id'),
                'programme_id' => $int('programme_id'),
                'programme'    => $text('programme'),
                'place'        => $text('place'),
                'start_at'     => pg_volunteer_event_time($text('start_time')),
                'end_at'       => pg_volunteer_event_time($text('end_time')),
                'needed'       => $int('needed'),
                'asked'        => $int('asked'),
            );

        case 'volunteer.checked_in':
            return array(
                'id'           => $int('signup_id'),
                'shift_id'     => $int('shift_id'),
                'programme_id' => $int('programme_id'),
                'programme'    => $text('programme'),
                'volunteer_id' => $int('volunteer_id'),
                'contact_id'   => $id('contact_id'),
                'role'         => $role,
                'role_name'    => $role_name,
                'place'        => $text('place'),
                'start_at'     => pg_volunteer_event_time($text('start_time')),
                'checked_in_at' => pg_volunteer_event_time($text('check_in')),
                'checked_by'   => $id('checked_by'),
            );

        case 'volunteer.hours_approved':
            return array(
                'id'           => $int('signup_id'),
                'shift_id'     => $id('shift_id'),
                'programme_id' => $int('programme_id'),
                'programme'    => $text('programme'),
                'volunteer_id' => $int('volunteer_id'),
                'contact_id'   => $id('contact_id'),
                'day'          => substr(($text('start_time') !== '') ? $text('start_time') : $text('worked_on'), 0, 10),
                'minutes'      => $int('minutes'),
                'approved_by'  => $id('approved_by'),
                'approved_at'  => pg_volunteer_event_time($row['approved_at'] ?? 0),
            );
    }

    return array();
}

/**
 * The places a shift still needs across its roles: each role's count less
 * its signed places, never below 0. Pure.
 *
 * @param array $shift pg_volunteer_shift_get() or a board shift
 * @return int
 */
function pg_volunteer_shift_needed(array $shift)
{
    $needed = 0;

    foreach ((array) ($shift['roles'] ?? array()) as $key => $count) {
        $needed += max(0, (int) $count - (int) ($shift['filled'][$key] ?? 0));
    }

    return $needed;
}

/**
 * The flat row of a volunteer profile ('volunteer') or a sign-up
 * ('signup') for pg_volunteer_event_payload(); null when it is gone.
 *
 * @param string $kind
 * @param int    $id
 * @return array|null
 */
function pg_volunteer_event_row($kind, $id)
{
    if ($kind === 'volunteer') {
        $row = db_item("SELECT volunteers.id AS volunteer_id, volunteers.programme_id, volunteers.contact_id, volunteers.form_id, volunteers.skills, volunteers.applied_at, volunteers.approved_at, volunteers.approved_by,
                volunteer_programmes.name AS programme
            FROM volunteers INNER JOIN volunteer_programmes ON volunteer_programmes.id = volunteers.programme_id
            WHERE volunteers.id = '" . (int) $id . "'");
    } else {
        $row = db_item("SELECT volunteer_signups.id AS signup_id, volunteer_signups.shift_id, volunteer_signups.programme_id, volunteer_signups.volunteer_id, volunteer_signups.role,
                volunteer_signups.check_in, volunteer_signups.checked_by, volunteer_signups.minutes, volunteer_signups.worked_on, volunteer_signups.approved_at, volunteer_signups.approved_by,
                volunteers.contact_id, volunteer_programmes.name AS programme, volunteer_programmes.roles AS programme_roles,
                volunteer_shifts.place, volunteer_shifts.start_time, volunteer_shifts.end_time
            FROM volunteer_signups INNER JOIN volunteers ON volunteers.id = volunteer_signups.volunteer_id
            INNER JOIN volunteer_programmes ON volunteer_programmes.id = volunteer_signups.programme_id
            LEFT JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
            WHERE volunteer_signups.id = '" . (int) $id . "'");
    }

    return $row ? $row : null;
}

/**
 * Announces a volunteer event about one profile or sign-up, read back
 * after the write. Called outside a shift's lock.
 *
 * @param string $event volunteer.applied, volunteer.approved, shift.signed_up,
 *                      volunteer.checked_in, volunteer.hours_approved
 * @param int    $id    the profile for volunteer.applied and .approved, else the sign-up
 * @param string $how   for shift.signed_up: sign_up, waitlist, swap
 * @return void
 */
function pg_volunteer_announce($event, $id, $how = '')
{
    $row = pg_volunteer_event_row((strpos($event, 'shift.') === 0) || ($event === 'volunteer.checked_in') || ($event === 'volunteer.hours_approved') ? 'signup' : 'volunteer', $id);

    if ($row === null) {
        return;
    }

    $row['how'] = $how;

    // Each name written out, so the event catalogue's test reads it.
    switch ($event) {
        case 'volunteer.applied':
            pg_announce('volunteer.applied', pg_volunteer_event_payload('volunteer.applied', $row));
            break;

        case 'volunteer.approved':
            pg_announce('volunteer.approved', pg_volunteer_event_payload('volunteer.approved', $row));
            break;

        case 'shift.signed_up':
            pg_announce('shift.signed_up', pg_volunteer_event_payload('shift.signed_up', $row));
            break;

        case 'volunteer.checked_in':
            pg_announce('volunteer.checked_in', pg_volunteer_event_payload('volunteer.checked_in', $row));
            break;

        case 'volunteer.hours_approved':
            pg_announce('volunteer.hours_approved', pg_volunteer_event_payload('volunteer.hours_approved', $row));
            break;
    }
}

/**
 * Announces shift.unfilled for a shift's first call: how many places it
 * still needs and how many volunteers were asked.
 *
 * @param array $programme pg_volunteer_programme_get()
 * @param array $shift     pg_volunteer_shift_get()
 * @param int   $asked
 * @return void
 */
function pg_volunteer_announce_unfilled(array $programme, array $shift, $asked)
{
    pg_announce('shift.unfilled', pg_volunteer_event_payload('shift.unfilled', array(
        'shift_id'     => (int) $shift['id'],
        'programme_id' => (int) $programme['id'],
        'programme'    => (string) $programme['name'],
        'place'        => (string) $shift['place'],
        'start_time'   => (string) $shift['start'],
        'end_time'     => (string) $shift['end'],
        'needed'       => pg_volunteer_shift_needed($shift),
        'asked'        => (int) $asked,
    )));
}
