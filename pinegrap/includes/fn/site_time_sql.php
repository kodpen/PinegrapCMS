<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A timestamp column read in the site's own time by MySQL, daylight saving
 * time included.
 *
 * init.php gives the MySQL session the site's offset at the moment of the
 * request (SET time_zone = date('P')), not a named zone: many hosts have no
 * MySQL time zone tables. FROM_UNIXTIME() therefore reads every timestamp
 * with today's offset, and in a zone with daylight saving time a timestamp
 * from the other season comes out an hour off: a visit at 00:20 summer time
 * on 1 October falls on 30 September under a winter session. PHP's own
 * dates (mktime(), date(), get_absolute_time()) follow the zone's rules, so
 * a report that picks its window in PHP and groups its days in SQL would
 * disagree with itself.
 *
 * pg_site_time_sql() adds to the column, for the window a report reads, the
 * difference between the zone's offset at that moment and the session's, so
 * FROM_UNIXTIME() gives the site's wall-clock time. A zone without a change
 * in the window, in the session's own season, keeps the plain
 * FROM_UNIXTIME(column).
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
 * The shifts a timestamp in [$from, $to] needs so that FROM_UNIXTIME() under
 * the session offset of $now reads it in the zone's own time.
 *
 * Each segment holds the shift in seconds and the first timestamp it no
 * longer covers (null for the last one). Neighbouring segments never share
 * a shift. An unknown zone, an empty window or no window at all (null)
 * gives one segment without a shift.
 *
 * @param int|null    $from First timestamp of the window
 * @param int|null    $to   Last timestamp of the window
 * @param string|null $zone Time zone name; the site's (PHP's default) when null
 * @param int|null    $now  The moment whose offset the session has; time() when null
 * @return array List of array('until' => int|null, 'shift' => int)
 */
function pg_site_time_shifts($from, $to, $zone = null, $now = null)
{
    $plain = array(array('until' => null, 'shift' => 0));

    if (($from === null) || ($to === null) || ((int) $from > (int) $to)) {
        return $plain;
    }

    $from = (int) $from;
    $to = (int) $to;

    try {
        $timezone = new DateTimeZone(($zone === null) ? date_default_timezone_get() : (string) $zone);
    } catch (Exception $exception) {
        return $plain;
    }

    $offset_at = function ($timestamp) use ($timezone) {
        $moment = new DateTime('@' . (int) $timestamp);
        $moment->setTimezone($timezone);

        return $moment->getOffset();
    };

    $session_offset = $offset_at(($now === null) ? time() : $now);

    $segments = array();
    $shift = $offset_at($from) - $session_offset;

    $transitions = $timezone->getTransitions($from, $to);

    foreach (is_array($transitions) ? $transitions : array() as $transition) {
        // The first entry describes $from itself, not a change.
        if (($transition['ts'] <= $from) || ($transition['ts'] > $to)) {
            continue;
        }

        $next_shift = (int) $transition['offset'] - $session_offset;

        if ($next_shift == $shift) {
            continue;
        }

        $segments[] = array('until' => (int) $transition['ts'], 'shift' => $shift);
        $shift = $next_shift;
    }

    $segments[] = array('until' => null, 'shift' => $shift);

    return $segments;
}

/**
 * FROM_UNIXTIME($column, $format) in the site's own time for the rows of
 * [$from, $to] (pg_site_time_shifts()). A row outside the window takes the
 * shift of the nearest end, so the window must hold every row the query
 * reads.
 *
 * @param string      $column SQL expression of a Unix timestamp
 * @param string      $format MySQL date format, e.g. '%Y-%m-%d'
 * @param int|null    $from   First timestamp of the window
 * @param int|null    $to     Last timestamp of the window
 * @param string|null $zone   Time zone name; the site's when null
 * @param int|null    $now    The moment whose offset the session has; time() when null
 * @return string
 */
function pg_site_time_sql($column, $format, $from, $to, $zone = null, $now = null)
{
    $segments = pg_site_time_shifts($from, $to, $zone, $now);

    $shifted = function ($shift) use ($column) {
        if ($shift == 0) {
            return $column;
        }

        return $column . (($shift > 0) ? ' + ' : ' - ') . abs($shift);
    };

    if (count($segments) == 1) {
        $expression = $shifted($segments[0]['shift']);
    } else {
        $expression = 'CASE';

        foreach ($segments as $segment) {
            if ($segment['until'] === null) {
                $expression .= ' ELSE ' . $shifted($segment['shift']);
            } else {
                $expression .= ' WHEN ' . $column . ' < ' . $segment['until'] . ' THEN ' . $shifted($segment['shift']);
            }
        }

        $expression .= ' END';
    }

    return 'FROM_UNIXTIME(' . $expression . ", '" . str_replace(array('\\', "'"), array('\\\\', "''"), (string) $format) . "')";
}
