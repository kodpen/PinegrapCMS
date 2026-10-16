<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The IP address of a visit taken out of its record some days after the
 * visit, long before the record itself goes.
 *
 * Every `visitors` row keeps the address the visit came from (INET_ATON(),
 * so IPv4 only; 0 for IPv6 and for an address that could not be read). An
 * address is personal data, and nothing needs it for long: the duplicate
 * visit check of init_tracking() looks at the last 30 minutes, and neither
 * the reports, the daily summary nor the hourly rollups count it.
 * config.visitor_ip_days (2026.4.10, 10.8; default 30, 0 keeps it as long as
 * the record) is the number of days after which the cleanup of the general
 * job sets it to 0. The visit, its figures and its place in the reports stay.
 *
 * The rows are changed the way the retention period removes them: windows of
 * the primary key, each its own short UPDATE, inside the cleanup's lock and
 * with the time its run has left (pg_visitor_cleanup_run()). A marker keeps
 * how far a pass got, so each visit is read about twice in all and not on
 * every run.
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
 * Days the address may be kept for, other than 0 (as long as the record).
 *
 * The boundary is a midnight at least a day back (pg_visitor_retention_cutoff()),
 * so even the shortest period stays clear of the 30 minutes the duplicate
 * visit check looks at.
 */
define('PG_VISITOR_IP_MIN_DAYS', 1);
define('PG_VISITOR_IP_MAX_DAYS', 3650);

/**
 * A visit whose boundary falls while it is still going on keeps its address
 * for this long more: the pass leaves a visit whose last page view is after
 * the boundary, and the next pass reads back this far to pick it up.
 */
define('PG_VISITOR_IP_GRACE_SECONDS', 86400);

/**
 * True when the setting is in the database (2026.4.10, 10.8).
 *
 * @return bool
 */
function pg_visitor_ip_ready()
{
    return pg_schema_has('config', 'visitor_ip_days');
}

/**
 * A period for the address as typed into the settings, or null when it is
 * not one. Empty and 0 both mean "as long as the record".
 *
 * @param mixed $value
 * @return int|null
 */
function pg_visitor_ip_days_from_input($value)
{
    return pg_visitor_days_from_input($value, PG_VISITOR_IP_MIN_DAYS, PG_VISITOR_IP_MAX_DAYS);
}

/**
 * The file that keeps how far the passes got. Losing it costs one pass from
 * the start of the table, nothing more.
 *
 * @return string
 */
function pg_visitor_ip_marker()
{
    return PG_FUNCTIONS_DIR . '/data/temp/visitor_ip.json';
}

/**
 * The marker: the boundary the pass under way (or the last one) goes to, the
 * id it got to, the id the next pass starts from, whether the pass is
 * through, the addresses it took out and when it ended.
 *
 * @return array('cutoff' => int, 'cursor' => int, 'from' => int,
 *               'done' => bool, 'cleared' => int, 'finished_at' => int)
 */
function pg_visitor_ip_last()
{
    $last = @json_decode((string) @file_get_contents(pg_visitor_ip_marker()), true);

    if (!is_array($last)) {
        $last = array();
    }

    return array(
        'cutoff' => isset($last['cutoff']) ? (int) $last['cutoff'] : 0,
        'cursor' => isset($last['cursor']) ? max(0, (int) $last['cursor']) : 0,
        'from' => isset($last['from']) ? max(0, (int) $last['from']) : 0,
        'done' => !empty($last['done']),
        'cleared' => isset($last['cleared']) ? (int) $last['cleared'] : 0,
        'finished_at' => isset($last['finished_at']) ? (int) $last['finished_at'] : 0,
    );
}

/**
 * Where a pass to $cutoff starts, or null when the pass to it is through.
 *
 * A pass that ended starts the next one from where it said; one that ran out
 * of time goes on from where it got, also when the boundary moved meanwhile.
 * A table emptied and numbered from 1 again (TRUNCATE, a table built anew)
 * has no visit as far as the last pass got, and a start past its last id
 * would pass over all of its visits: then a pass starts from the beginning,
 * also for a boundary that was through.
 *
 * @param array $last   pg_visitor_ip_last()
 * @param int   $cutoff
 * @param int   $max_id The highest id of the table now
 * @return array('cursor' => int, 'cleared' => int)|null
 */
function pg_visitor_ip_pass_plan($last, $cutoff, $max_id)
{
    if (max($last['cursor'], $last['from']) > (int) $max_id) {
        return array('cursor' => 0, 'cleared' => 0);
    }

    if ($last['done'] && ($last['cutoff'] === (int) $cutoff)) {
        return null;
    }

    return array(
        'cursor' => $last['done'] ? $last['from'] : $last['cursor'],
        'cleared' => $last['done'] ? 0 : $last['cleared'],
    );
}

/**
 * The statement that takes the address out of one window of ids.
 *
 * A visit that started before the boundary loses it once its last page view
 * is before the boundary too, or once it started more than the grace period
 * before it: a visit that is still going on keeps its address, so it can
 * never come back as a match (ip_address = 0) for another visitor's
 * duplicate check, which reads IPv6 visitors as 0. Rows already at 0 are not
 * written again.
 *
 * @param int $from_id Exclusive
 * @param int $to_id   Inclusive
 * @param int $cutoff
 * @return string
 */
function pg_visitor_ip_clear_sql($from_id, $to_id, $cutoff)
{
    $cutoff = (int) $cutoff;

    return "UPDATE visitors
        SET ip_address = 0
        WHERE (id > " . (int) $from_id . ") AND (id <= " . (int) $to_id . ")
            AND (start_timestamp < " . $cutoff . ")
            AND ((stop_timestamp < " . $cutoff . ") OR (start_timestamp < " . ($cutoff - PG_VISITOR_IP_GRACE_SECONDS) . "))
            AND (ip_address <> 0)";
}

/**
 * Take the address out of the visits older than the period, until $deadline.
 *
 * Runs inside the cleanup's lock (pg_visitor_cleanup_run()), after its
 * removal, so rows about to go are not written first. The chunks are those of
 * the removal: InnoDB locks only the rows a chunk changes; on MyISAM the
 * chunks are smaller and the table is left free for as long as the chunk
 * held it.
 *
 * @param int   $days      config.visitor_ip_days, 0 for no early removal
 * @param float $deadline  microtime(true) to stop at
 * @param int   $chunk     Rows per UPDATE
 * @param bool  $row_locks Whether the table is on InnoDB
 * @return array('state' => 'off'|'held'|'done'|'more', 'cleared' => int,
 *               'cutoff' => int)
 */
function pg_visitor_ip_clear_run($days, $deadline, $chunk, $row_locks)
{
    $result = array('state' => 'off', 'cleared' => 0, 'cutoff' => 0);

    $days = (int) $days;

    if ($days <= 0) {
        return $result;
    }

    $cutoff = pg_visitor_retention_cutoff($days, time());
    $result['cutoff'] = $cutoff;

    // While the table is being copied (pg_visitor_move_run()), a visit changed
    // here after it was copied would keep its address in the copy: the move
    // copies again only the visits whose stop_timestamp moved. The pass waits
    // for the copy to take the table's place.
    $move = pg_visitor_move_last();

    if ($move['state'] === 'copying') {
        $result['state'] = 'held';
        return $result;
    }

    $last = pg_visitor_ip_last();

    $plan = pg_visitor_ip_pass_plan($last, $cutoff, (int) db_value("SELECT MAX(id) FROM visitors"));

    if ($plan === null) {
        $result['state'] = 'done';
        return $result;
    }

    $cursor = $plan['cursor'];
    $cleared = $plan['cleared'];
    $last_id = pg_visitor_last_id_before($cutoff);
    $done = ($cursor >= $last_id);

    while (!$done && (microtime(true) < $deadline)) {

        $upper = pg_visitor_id_window_end($cursor, $last_id, $chunk);

        if ($upper <= $cursor) {
            $done = true;
            break;
        }

        $chunk_started = microtime(true);

        if (!@mysqli_query(db::$con, pg_visitor_ip_clear_sql($cursor, $upper, $cutoff))) {
            // A lock wait timeout or a deadlock with a live write: the next
            // run goes on from here.
            break;
        }

        $changed = (int) mysqli_affected_rows(db::$con);
        $cleared += $changed;
        $result['cleared'] += $changed;

        $cursor = $upper;

        if ($cursor >= $last_id) {
            $done = true;
            break;
        }

        usleep($row_locks ? 20000 : max(20000, (int) ((microtime(true) - $chunk_started) * 1000000)));
    }

    // The next pass starts the grace period before this boundary, where the
    // visits this one left because they were still going on are. A pass
    // under way keeps where it started: never past the table's last id.
    $from = $done ? min($cursor, pg_visitor_last_id_before($cutoff - PG_VISITOR_IP_GRACE_SECONDS)) : $plan['cursor'];

    @file_put_contents(pg_visitor_ip_marker(), json_encode(array(
        'cutoff' => $cutoff,
        'cursor' => $cursor,
        'from' => $from,
        'done' => $done,
        'cleared' => $cleared,
        'finished_at' => $done ? time() : $last['finished_at'],
    )));

    $result['state'] = $done ? 'done' : 'more';

    return $result;
}
