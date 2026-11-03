<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Booking system widget (kind 'booking'): the
 * visitor picks a service, a staff member or "anyone", a day of a month
 * calendar that shows only the days with a free slot, then a time, and the
 * slot is held for them while they fill in their details.
 *
 * The page draws the services (one loop row each) and carries the staff of
 * each service in the widget's data-pg-booking settings, the same for every
 * visitor, so the page can come from the page cache. The days and times come
 * from booking_slots.php, which reads the bookings and the staff members'
 * Workspace calendars on every request; assets/js/pg_booking.js draws them
 * on pg_datetime.js's calendar.
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
   Pure helpers
   --------------------------------------------------------------------------- */

/**
 * The booking policy: how far ahead and how soon a slot may be booked, how
 * long a hold lives. The defaults until Settings › Bookings stores its own;
 * the zone is the site's.
 *
 * @return array zone, lead_minutes, horizon_days, hold_minutes, step_minutes
 *               (0: the service's length)
 */
function pg_booking_policy()
{
    return array(
        'zone'         => date_default_timezone_get(),
        'lead_minutes' => 60,
        'horizon_days' => 60,
        'hold_minutes' => 10,
        'step_minutes' => 0,
    );
}

/**
 * The service ids a staff member takes, from booking_staff.service_ids (a
 * JSON list or an array).
 *
 * @param mixed $value
 * @return int[]
 */
function pg_booking_service_ids($value)
{
    if (is_string($value)) {
        $value = json_decode($value, true);
    }

    $ids = array();

    foreach (is_array($value) ? $value : array() as $id) {
        if (is_numeric($id) && ((int) $id > 0)) {
            $ids[(int) $id] = (int) $id;
        }
    }

    return array_values($ids);
}

/**
 * The staff rows that take a service, in the order given.
 *
 * @param array $staff_rows rows with id, service_ids, enabled
 * @param int   $service_id
 * @return array
 */
function pg_booking_staff_for($staff_rows, $service_id)
{
    $found = array();

    foreach ((array) $staff_rows as $row) {
        if (is_array($row) && !empty($row['enabled']) && in_array((int) $service_id, pg_booking_service_ids(isset($row['service_ids']) ? $row['service_ids'] : ''), true)) {
            $found[] = $row;
        }
    }

    return $found;
}

/**
 * Whether a month is written 'YYYY-MM'.
 *
 * @param mixed $month
 * @return bool
 */
function pg_booking_month_valid($month)
{
    return is_string($month) && (preg_match('/^([0-9]{4})-(0[1-9]|1[0-2])$/', $month) === 1);
}

/**
 * The days of a month, 'YYYY-MM-DD'.
 *
 * @param string $month 'YYYY-MM'
 * @return string[]
 */
function pg_booking_month_dates($month)
{
    if (!pg_booking_month_valid($month)) {
        return array();
    }

    list($year, $number) = array_map('intval', explode('-', $month));
    $days = (int) date('t', mktime(12, 0, 0, $number, 1, $year));
    $dates = array();

    for ($day = 1; $day <= $days; $day++) {
        $dates[] = sprintf('%04d-%02d-%02d', $year, $number, $day);
    }

    return $dates;
}

/**
 * The free times of a day for a service over several staff members: each
 * start once, with the staff member who takes it. When more than one is
 * free at a start, the one with the fewest busy minutes that day takes it
 * ("anyone" picks the freest), the lowest id on a tie.
 *
 * @param array  $service as in pg_booking_slots()
 * @param array  $staff   rows with id, hours, exceptions
 * @param string $day     'YYYY-MM-DD'
 * @param array  $busy    staff id => list of array(start, end)
 * @param int    $now
 * @param array  $policy  as in pg_booking_slots()
 * @return array list of array('start' => unix, 'staff_id' => id), ascending
 */
function pg_booking_day_times($service, $staff, $day, $busy, $now, $policy)
{
    $by_start = array();
    $load = array();

    try {
        $zone = new DateTimeZone(!empty($policy['zone']) ? (string) $policy['zone'] : date_default_timezone_get());
        $from = (new DateTime($day . ' 00:00:00', $zone))->getTimestamp();
        $until = (new DateTime($day . ' 00:00:00', $zone))->modify('+1 day')->getTimestamp();
    } catch (Exception $exception) {
        return array();
    }

    foreach ((array) $staff as $row) {
        $id = (int) $row['id'];
        $ranges = isset($busy[$id]) ? (array) $busy[$id] : array();
        $minutes = 0;

        foreach ($ranges as $range) {
            $range = array_values((array) $range);
            if (count($range) >= 2) {
                $minutes += max(0, min((int) $range[1], $until) - max((int) $range[0], $from));
            }
        }

        $load[$id] = $minutes;

        foreach (pg_booking_slots($service, $row, $day, $ranges, $now, $policy) as $start) {
            $by_start[$start][] = $id;
        }
    }

    ksort($by_start);
    $times = array();

    foreach ($by_start as $start => $ids) {
        usort($ids, function ($a, $b) use ($load) {
            return ($load[$a] - $load[$b]) ?: ($a - $b);
        });
        $times[] = array('start' => (int) $start, 'staff_id' => $ids[0]);
    }

    return $times;
}

/**
 * The days of a month with at least one free time, for a service over
 * several staff members. Days before today or past the horizon are not
 * walked.
 *
 * @param array  $service
 * @param array  $staff
 * @param string $month  'YYYY-MM'
 * @param array  $busy   staff id => ranges
 * @param int    $now
 * @param array  $policy
 * @return string[] 'YYYY-MM-DD'
 */
function pg_booking_month_days($service, $staff, $month, $busy, $now, $policy)
{
    try {
        $zone = new DateTimeZone(!empty($policy['zone']) ? (string) $policy['zone'] : date_default_timezone_get());
        $today = (new DateTime('@' . (int) $now))->setTimezone($zone)->format('Y-m-d');
        $last = !empty($policy['horizon_days'])
            ? (new DateTime('@' . ((int) $now + ((int) $policy['horizon_days'] * 86400))))->setTimezone($zone)->format('Y-m-d')
            : null;
    } catch (Exception $exception) {
        return array();
    }

    $days = array();

    foreach (pg_booking_month_dates($month) as $date) {
        if (($date < $today) || (($last !== null) && ($date > $last))) {
            continue;
        }

        foreach ((array) $staff as $row) {
            $id = (int) $row['id'];
            if (pg_booking_slots($service, $row, $date, isset($busy[$id]) ? $busy[$id] : array(), $now, $policy)) {
                $days[] = $date;
                break;
            }
        }
    }

    return $days;
}

/**
 * The busy ranges of Workspace calendar events, per staff member. An
 * all-day event, stored up to 23:59:59 of its last day, takes that whole
 * second too.
 *
 * @param array $events     rows with starts_at, ends_at, all_day, scope,
 *                          department_id, people (user ids)
 * @param array $staff      rows with id, user_id
 * @param array $departments user id => department ids
 * @return array staff id => list of array(start, end)
 */
function pg_booking_ws_busy($events, $staff, $departments)
{
    $busy = array();

    foreach ((array) $staff as $row) {
        $user_id = (int) $row['user_id'];
        $own = isset($departments[$user_id]) ? array_map('intval', (array) $departments[$user_id]) : array();

        foreach ((array) $events as $event) {
            $scope = isset($event['scope']) ? (string) $event['scope'] : 'people';
            $people = isset($event['people']) ? array_map('intval', (array) $event['people']) : array();

            if (($scope === 'company')
                || (($scope === 'department') && in_array((int) $event['department_id'], $own, true))
                || (($scope === 'people') && in_array($user_id, $people, true))) {
                $end = (int) $event['ends_at'] + (!empty($event['all_day']) ? 1 : 0);
                $busy[(int) $row['id']][] = array((int) $event['starts_at'], $end);
            }
        }
    }

    return $busy;
}

/**
 * The staff of every service as the widget's script reads them: service id
 * => list of array(id, name), and whether "anyone" is offered (more than
 * one staff member).
 *
 * @param array $services rows with id
 * @param array $staff    rows with id, name, service_ids, enabled
 * @return array
 */
function pg_booking_widget_staff_map($services, $staff)
{
    $map = array();

    foreach ((array) $services as $service) {
        $list = array();

        foreach (pg_booking_staff_for($staff, (int) $service['id']) as $row) {
            $list[] = array('id' => (int) $row['id'], 'name' => (string) $row['name']);
        }

        $map[(string) (int) $service['id']] = $list;
    }

    return $map;
}
