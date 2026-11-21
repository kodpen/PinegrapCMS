<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The walk-in queue's store: the three tables of step 11.0.266
 * (waiting_places, waiting_desks, waiting_tickets) and the daily clear of a
 * number's personal fields once its place's keep period has passed (30 days
 * by default): name, phone, e-mail, contact, priority reason and the link's
 * hash are emptied, the figures (service, state, desk, times) stay for the
 * report. The rules are the pure ones of walk_in_queue.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Numbers one statement of the keep-period clear empties at most. */
const PG_WAITING_CLEAR_BATCH = 1000;

/** Seconds of a general job tick the keep-period clear may use. */
const PG_WAITING_CLEAR_SECONDS = 5;

/**
 * Whether the queue's tables are there (step 11.0.266): files land before
 * the upgrade runs, and until then nothing is read or written.
 *
 * @return bool
 */
function pg_waiting_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('waiting_places', 'settings') && pg_schema_has('waiting_tickets', 'cleared_at');
    }

    return $ready;
}

/**
 * The statement that empties the personal fields of one place's numbers
 * that joined before the cutoff and were not cleared yet. A place id of 0
 * with $others reaches the numbers of every place not in $others (a place
 * deleted since), at the default keep period. Pure.
 *
 * @param int   $place_id
 * @param int   $cutoff  joined before this moment
 * @param int   $now
 * @param int   $limit
 * @param array $others  the places that have their own period (with place 0)
 * @return array array(sql, params)
 */
function pg_waiting_keep_clear_query($place_id, $cutoff, $now, $limit, array $others = array())
{
    $sets = array();
    $params = array();

    foreach (pg_waiting_clear_fields() as $column => $value) {
        $sets[] = $column . ' = ?';
        $params[] = $value;
    }

    $sets[] = 'cleared_at = ?';
    $params[] = (int) $now;

    if ((int) $place_id > 0) {
        $where = 'place_id = ?';
        $params[] = (int) $place_id;
    } elseif ($others) {
        $where = 'place_id NOT IN (' . implode(', ', array_fill(0, count($others), '?')) . ')';
        $params = array_merge($params, array_map('intval', array_values($others)));
    } else {
        $where = '1';
    }

    $params[] = (int) $cutoff;

    return array(
        'UPDATE waiting_tickets SET ' . implode(', ', $sets) . ' WHERE ' . $where . ' AND (cleared_at = 0) AND (joined_at < ?) LIMIT ' . max(1, (int) $limit),
        $params,
    );
}

/**
 * Where the day of the last keep-period clear is noted.
 *
 * @return string
 */
function pg_waiting_clear_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/waiting_clear.state';
}

/**
 * The general job's keep-period clear, once a day: for each place the
 * numbers that joined more than its keep period ago lose their personal
 * fields, in batches, for at most $seconds. A run that stops before the end
 * is not noted, so the next tick goes on.
 *
 * @param int      $seconds
 * @param int|null $now
 * @return int the numbers cleared
 */
function pg_waiting_keep_clear_daily($seconds = PG_WAITING_CLEAR_SECONDS, $now = null)
{
    if (!pg_waiting_ready()) {
        return 0;
    }

    $now = ($now === null) ? time() : (int) $now;
    $path = pg_waiting_clear_state_path();
    $last = is_file($path) ? (int) @file_get_contents($path) : 0;

    if (($now - $last) < 86400) {
        return 0;
    }

    $clock = microtime(true);
    $cleared = 0;
    $places = array();

    foreach (db_items("SELECT id, settings FROM waiting_places") as $place) {
        $places[(int) $place['id']] = pg_waiting_keep_days($place['settings']);
    }

    // The numbers of a place deleted since keep the default period.
    $runs = array();

    foreach ($places as $place_id => $days) {
        $runs[] = array($place_id, $days, array());
    }

    $runs[] = array(0, pg_waiting_keep_days(null), array_keys($places));

    foreach ($runs as $run) {
        do {
            list($sql, $params) = pg_waiting_keep_clear_query($run[0], pg_waiting_keep_cutoff($run[1], $now), $now, PG_WAITING_CLEAR_BATCH, $run[2]);

            if (db_q($sql, $params) === false) {
                return $cleared;
            }

            $done = (int) mysqli_affected_rows(db::$con);
            $cleared += $done;

            if ((microtime(true) - $clock) >= (int) $seconds) {
                return $cleared;
            }
        } while ($done >= PG_WAITING_CLEAR_BATCH);
    }

    @file_put_contents($path, (string) $now, LOCK_EX);

    return $cleared;
}
