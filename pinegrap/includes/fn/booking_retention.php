<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * How long appointment bookings are kept, and what a personal data erasure
 * does to a person's bookings.
 *
 * A cancelled booking is kept for 400 days after its last change, then the
 * general job (job.php) removes it once a day. Every other status stays: a
 * booking that took place or was missed is part of the shop's record. An
 * erasure (privacy.php) first cancels the person's live bookings still to
 * come, so their slots open again, then replaces the personal columns of all
 * of them (pg_privacy_erase_tables()).
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
 * The days a cancelled booking is kept after its last change, and how many
 * rows one statement removes.
 *
 * @return array  days, batch
 */
function pg_booking_retention_limits()
{
    return array('days' => 400, 'batch' => 1000);
}

/**
 * The moment before which a cancelled booking's last change makes it old
 * enough to go. Pure.
 *
 * @param int $now
 * @return int
 */
function pg_booking_retention_cutoff($now)
{
    $limits = pg_booking_retention_limits();

    return max(0, (int) $now - ($limits['days'] * 86400));
}

/**
 * The condition of the bookings the daily pass removes: cancelled, last
 * changed before the cutoff. A row never stamped (updated_at 0) counts by
 * its creation. Pure.
 *
 * @param int $cutoff
 * @return string
 */
function pg_booking_retention_where($cutoff)
{
    $cutoff = (int) $cutoff;

    return "(status = 'cancelled') AND (GREATEST(updated_at, created_at) < '" . $cutoff . "')";
}

/**
 * The statuses an erasure cancels a booking from: those that may still move
 * to cancelled (pg_booking_can_move()). Pure.
 *
 * @return string[]
 */
function pg_booking_erase_live_statuses()
{
    $live = array();

    foreach (pg_booking_statuses() as $status) {
        if (pg_booking_can_move($status, 'cancelled')) {
            $live[] = $status;
        }
    }

    return $live;
}

/**
 * The condition, added to the person's rows, of the bookings an erasure
 * cancels first: live and not yet begun. A booking under way or past stays
 * in its status. Pure.
 *
 * @param int $now
 * @return string
 */
function pg_booking_erase_cancel_where($now)
{
    return "(status IN ('" . implode("', '", pg_booking_erase_live_statuses()) . "')) AND (start_at > '" . (int) $now . "')";
}

/**
 * Where the daily pass keeps when it last went through.
 *
 * @return string
 */
function pg_booking_retention_state_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/booking_retention.json';
}

/**
 * Whether the daily pass is owed: the booking tables are there and no pass
 * went through in the last day.
 *
 * @return bool
 */
function pg_booking_retention_due()
{
    if (!pg_booking_ready()) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_booking_retention_state_file()), true);
    $done = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The daily pass: cancelled bookings past their days, a batch at a time
 * within the time budget. The state file is written only once the pass is
 * through, so a backlog goes on the next ticks. A failed statement ends the
 * pass and it is asked again.
 *
 * @param int $seconds
 * @return array  removed, through (bool)
 */
function pg_booking_retention_run($seconds = 5)
{
    $limits = pg_booking_retention_limits();
    $deadline = microtime(true) + max(1, (int) $seconds);
    $now = time();
    $where = pg_booking_retention_where(pg_booking_retention_cutoff($now));
    $result = array('removed' => 0, 'through' => false);

    while (!$result['through'] && (microtime(true) < $deadline)) {
        if (db_q("DELETE FROM bookings WHERE " . $where . " LIMIT " . (int) $limits['batch']) === false) {
            return $result;
        }

        $affected = max(0, (int) mysqli_affected_rows(db::$con));
        $result['removed'] += $affected;
        $result['through'] = ($affected < $limits['batch']);
    }

    if ($result['through']) {
        @file_put_contents(pg_booking_retention_state_file(), json_encode(array('done_at' => $now)), LOCK_EX);
    }

    return $result;
}
