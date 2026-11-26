<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Delivery tower: the passes the general job runs. Each reads the parcel
 * rows (shipping_tracking_numbers) and hands them to the pure rules of
 * includes/fn/delivery_tower.php.
 *
 * - The nightly summary (config_text 'delivery_tower_summary'): the parcels
 *   delivered in the last PG_DELIVERY_TOWER_DAYS days, measured per carrier
 *   and province, with their on-time counts against checkout's estimate.
 *   Rebuilt once a night after PG_DELIVERY_TOWER_NIGHT_HOUR, at most the
 *   tick's budget at a time; the walk keeps its place in data/temp between
 *   ticks and writes the summary once when it is through.
 * - The hourly problem pass: the open parcels (and every parcel with an
 *   open problem), judged by the summary's figures; a new problem is a
 *   shipment_problems row (INSERT IGNORE on the unique parcel, kind and
 *   closed_at key, so a pass can run again), a settled one is closed with
 *   close_reason 1. The place is kept in config_text 'delivery_tower_pass'.
 *
 * Both run under one MySQL lock per database, so two overlapping ticks
 * never walk together. No parcel row is written: the status writer stays
 * pg_shipment_record().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Days of delivered parcels the summary measures.
define('PG_DELIVERY_TOWER_DAYS', 90);

// Local hour from which the night's summary is due.
define('PG_DELIVERY_TOWER_NIGHT_HOUR', 2);

// Seconds between two problem passes.
define('PG_DELIVERY_TOWER_PASS_INTERVAL', 3600);

// Seconds of a general job tick the passes take at most together.
define('PG_DELIVERY_TOWER_TICK_SECONDS', 20);

// Parcel rows read per query.
define('PG_DELIVERY_TOWER_PAGE', 1000);

/**
 * The problem kinds as shipment_problems.kind stores them.
 *
 * @return array kind => number
 */
function pg_delivery_tower_kind_codes()
{
    return array('stuck' => 1, 'not_handed_over' => 2, 'problem' => 3, 'returned' => 4);
}

/**
 * The close reasons as shipment_problems.close_reason stores them.
 *
 * @return array reason => number
 */
function pg_delivery_tower_close_reasons()
{
    return array('open' => 0, 'status' => 1, 'sent_again' => 2, 'refunded' => 3, 'found' => 4, 'other' => 5);
}

/**
 * Whether the problem table and the parcel columns are there.
 *
 * @return bool
 */
function pg_delivery_tower_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('shipment_problems') && pg_shipment_rows_ready();
}

/**
 * The settings (config_text 'delivery_tower_settings'), cleaned.
 *
 * @return array
 */
function pg_delivery_tower_settings()
{
    return pg_delivery_tower_settings_clean(json_decode(pg_config_text('delivery_tower_settings'), true));
}

/**
 * The local night hour of the day $now falls in, or of the day before when
 * $now is earlier than that hour. Pure.
 *
 * @param int    $now
 * @param string $timezone '' = PHP's default
 * @return int unix time
 */
function _pg_delivery_tower_night_start($now, $timezone)
{
    $date = new DateTime('@' . (int) $now);
    $date->setTimezone(new DateTimeZone($timezone ? $timezone : date_default_timezone_get()));
    $date->setTime(PG_DELIVERY_TOWER_NIGHT_HOUR, 0, 0);
    if ($date->getTimestamp() > (int) $now) {
        $date->modify('-1 day');
        $date->setTime(PG_DELIVERY_TOWER_NIGHT_HOUR, 0, 0);
    }
    return $date->getTimestamp();
}

/**
 * Whether the summary is due: none yet, or built before the last night
 * hour. Pure.
 *
 * @param int    $summary_at when the kept summary was built, 0 = none
 * @param int    $now
 * @param string $timezone
 * @return bool
 */
function pg_delivery_tower_summary_due($summary_at, $now, $timezone = '')
{
    return (int) $summary_at <= 0 || (int) $summary_at < _pg_delivery_tower_night_start($now, $timezone);
}

/**
 * Whether the problem pass is due: a walk under way, or the last one ended
 * an interval ago. Pure.
 *
 * @param array $record after (the place, 0 = no walk under way), at (when
 *                      the last walk started)
 * @param int   $now
 * @return bool
 */
function pg_delivery_tower_pass_due($record, $now)
{
    if (!empty($record['after'])) {
        return true;
    }
    return ((int) $now - (int) (isset($record['at']) ? $record['at'] : 0)) >= PG_DELIVERY_TOWER_PASS_INTERVAL;
}

/**
 * A new summary walk, started at $started. Pure.
 *
 * @param int $started
 * @return array started, after (the last parcel id read), days (carrier =>
 *               province key => list of packed parcels, see
 *               _pg_delivery_tower_pack())
 */
function pg_delivery_tower_summary_state_new($started)
{
    return array('started' => (int) $started, 'after' => 0, 'days' => array());
}

/**
 * One measured parcel as one integer, so a walk over many parcels stays
 * small: calendar days, working days and the on-time mark (0 not judged,
 * 1 late, 2 on time). Pure.
 */
function _pg_delivery_tower_pack($calendar, $working, $on_time)
{
    return min(99999, (int) $calendar) * 1000000 + min(99999, (int) $working) * 10 + ($on_time === null ? 0 : ($on_time ? 2 : 1));
}

/**
 * Adds one page of delivered parcels to the walk. A row with no carrier
 * or no ship date is left out, as the figures leave it. Pure.
 *
 * @param array  $state
 * @param array  $rows  id, carrier, state, city, shipped_at, delivered_at,
 *                      delivery_date (checkout's estimate)
 * @param string $timezone
 */
function pg_delivery_tower_summary_add(&$state, $rows, $timezone = '')
{
    foreach ((array) $rows as $row) {
        $state['after'] = max((int) $state['after'], (int) $row['id']);
        $shipped = (int) $row['shipped_at'];
        $delivered = (int) $row['delivered_at'];
        if ((string) $row['carrier'] === '' || $shipped <= 0 || $delivered < $shipped) {
            continue;
        }
        $province = pg_delivery_tower_province_key($row['state'], $row['city']);
        $state['days'][(string) $row['carrier']][$province][] = _pg_delivery_tower_pack(
            pg_delivery_tower_calendar_days($shipped, $delivered, $timezone),
            pg_delivery_tower_working_days($shipped, $delivered, $timezone),
            pg_delivery_tower_on_time($delivered, isset($row['delivery_date']) ? $row['delivery_date'] : '', $timezone)
        );
    }
}

/**
 * The walk's state as text, kept between ticks. Pure.
 *
 * @param array $state
 * @return string
 */
function pg_delivery_tower_summary_state_encode($state)
{
    return json_encode($state);
}

/**
 * The kept state read back, only when it belongs to the walk started at
 * $started. Pure.
 *
 * @param string $text
 * @param int    $started
 * @return array|null
 */
function pg_delivery_tower_summary_state_decode($text, $started)
{
    $state = json_decode((string) $text, true);
    if (!is_array($state) || !isset($state['started'], $state['after'], $state['days']) || (int) $state['started'] !== (int) $started || !is_array($state['days'])) {
        return null;
    }
    return $state;
}

/**
 * The summary from a finished walk: per carrier, the figure across the
 * country and per province with enough parcels (the rules' minimum and
 * figure), and the on-time counts of the parcels that had an estimate for
 * the same places. A parcel with no province counts for the country only.
 * Pure.
 *
 * @param array $state
 * @param int   $min parcels a figure needs
 * @return array at, days, parcels, figures (as pg_delivery_tower_figures()
 *               gives them), on_time
 */
function pg_delivery_tower_summary_finish($state, $min = PG_DELIVERY_TOWER_MIN_PARCELS)
{
    $figures = array();
    $on_time = array();
    $parcels = 0;
    foreach ($state['days'] as $carrier => $provinces) {
        $country = array();
        $country_marks = array();
        $figures[$carrier] = array('country' => null, 'provinces' => array());
        foreach ($provinces as $province => $packed) {
            $pairs = array();
            $marks = array();
            foreach ($packed as $value) {
                $pairs[] = array(intdiv($value, 1000000), intdiv($value % 1000000, 10));
                if ($value % 10 > 0) {
                    $marks[] = $value % 10 === 2 ? 1 : 0;
                }
            }
            $parcels += count($pairs);
            foreach ($pairs as $pair) {
                $country[] = $pair;
            }
            foreach ($marks as $mark) {
                $country_marks[] = $mark;
            }
            $figure = $province === '' ? null : _pg_delivery_tower_figure($pairs, $min);
            if ($figure !== null) {
                $figures[$carrier]['provinces'][(string) $province] = $figure;
                if ($marks) {
                    $on_time[$carrier]['provinces'][(string) $province] = _pg_delivery_tower_on_time_count($marks);
                }
            }
        }
        $figures[$carrier]['country'] = _pg_delivery_tower_figure($country, $min);
        // The country's share needs the country's figure, as a province's
        // needs its own: one parcel on time is no 100%.
        if ($country_marks && ($figures[$carrier]['country'] !== null)) {
            $on_time[$carrier]['country'] = _pg_delivery_tower_on_time_count($country_marks);
            if (!isset($on_time[$carrier]['provinces'])) {
                $on_time[$carrier]['provinces'] = array();
            }
        }
    }

    return array(
        'at' => (int) $state['started'],
        'days' => PG_DELIVERY_TOWER_DAYS,
        'parcels' => $parcels,
        'figures' => $figures,
        'on_time' => $on_time,
    );
}

/**
 * Judged and on-time counts of a list of 1/0 marks.
 */
function _pg_delivery_tower_on_time_count($marks)
{
    return array('judged' => count($marks), 'on_time' => (int) array_sum($marks));
}

/**
 * The summary as config_text holds it, at most 65535 bytes: while it is
 * longer, the province with the fewest parcels across all carriers is left
 * out (the pass then judges its parcels by the carrier's country figure).
 * Pure.
 *
 * @param array $summary
 * @return string
 */
function pg_delivery_tower_summary_encode($summary)
{
    $text = json_encode($summary);
    while (strlen($text) > 65535) {
        $smallest = null;
        foreach ($summary['figures'] as $carrier => $parts) {
            foreach ($parts['provinces'] as $province => $figure) {
                if ($smallest === null || $figure['count'] < $smallest[2]) {
                    $smallest = array($carrier, $province, $figure['count']);
                }
            }
        }
        if ($smallest === null) {
            break;
        }
        // Drop every province of that size at once, so a long tail goes in
        // a few rounds.
        foreach ($summary['figures'] as $carrier => $parts) {
            foreach ($parts['provinces'] as $province => $figure) {
                if ($figure['count'] <= $smallest[2]) {
                    unset($summary['figures'][$carrier]['provinces'][$province], $summary['on_time'][$carrier]['provinces'][$province]);
                }
            }
        }
        $text = json_encode($summary);
    }
    return $text;
}

/**
 * The kept summary, or null before the first.
 *
 * @return array|null
 */
function pg_delivery_tower_summary()
{
    $summary = json_decode(pg_config_text('delivery_tower_summary'), true);
    return is_array($summary) && isset($summary['at'], $summary['figures']) ? $summary : null;
}

/**
 * The read of one page of the problem pass: parcels after the place, in id
 * order, that are still on their way and were made, shipped or moved since
 * $since, or that have an open problem whatever their age; on an order that
 * is neither incomplete nor cancelled. Pure.
 *
 * @param int $after
 * @param int $since
 * @param int $limit
 * @return array array(sql, params)
 */
function pg_delivery_tower_parcel_query($after, $since, $limit)
{
    $sql = "SELECT
            shipping_tracking_numbers.id,
            shipping_tracking_numbers.order_id,
            shipping_tracking_numbers.ship_to_id,
            shipping_tracking_numbers.number,
            shipping_tracking_numbers.carrier,
            shipping_tracking_numbers.status,
            shipping_tracking_numbers.source,
            shipping_tracking_numbers.shipped_at,
            shipping_tracking_numbers.delivered_at,
            ship_tos.state,
            ship_tos.city,
            orders.order_number,
            orders.type AS order_type
        FROM shipping_tracking_numbers
        INNER JOIN orders ON orders.id = shipping_tracking_numbers.order_id
        LEFT JOIN ship_tos ON ship_tos.id = shipping_tracking_numbers.ship_to_id
        WHERE
            (shipping_tracking_numbers.id > ?)
            AND (orders.status NOT IN ('incomplete', 'cancelled'))
            AND (
                (
                    (shipping_tracking_numbers.delivered_at = 0)
                    AND (shipping_tracking_numbers.status != 'delivered')
                    AND (TRIM(shipping_tracking_numbers.number) != '')
                    AND (
                        (shipping_tracking_numbers.created_at >= ?)
                        OR (shipping_tracking_numbers.shipped_at >= ?)
                        OR (shipping_tracking_numbers.status_at >= ?)
                    )
                )
                OR shipping_tracking_numbers.id IN (SELECT shipment_id FROM shipment_problems WHERE closed_at = 0)
            )
        ORDER BY shipping_tracking_numbers.id
        LIMIT " . max(1, (int) $limit);

    return array($sql, array((int) $after, (int) $since, (int) $since, (int) $since));
}

/**
 * What one page of the problem pass writes. Pure.
 *
 * @param array    $parcels  id => parcel row (pg_delivery_tower_parcel_query())
 * @param array    $labels   "ship_to_id|number" => array(driver, created_at),
 *                           the parcel's newest active label
 * @param array    $open     shipment id => kinds open on it
 * @param array    $figures  the summary's figures
 * @param int      $now
 * @param array    $settings pg_delivery_tower_settings_clean()
 * @param string[] $tracking the label drivers that follow their parcels
 * @param string   $timezone
 * @return array open: list of (shipment_id, order_id, ship_to_id, kind),
 *               close: list of (shipment_id, kind, reason); kinds as
 *               numbers, reason as pg_delivery_tower_pass() gives it
 */
function pg_delivery_tower_parcel_changes($parcels, $labels, $open, $figures, $now, $settings, $tracking, $timezone = '')
{
    $codes = pg_delivery_tower_kind_codes();
    $changes = array('open' => array(), 'close' => array());

    foreach ($parcels as $id => $parcel) {
        $key = (int) $parcel['ship_to_id'] . '|' . (string) $parcel['number'];
        $label = isset($labels[$key]) ? $labels[$key] : null;
        $status = (string) $parcel['status'];
        if ((int) $parcel['delivered_at'] > 0) {
            // A delivered parcel ends every open problem, whatever a later
            // status write left in the status column.
            $status = 'delivered';
        }
        $parcel['label_at'] = $label ? (int) $label['created_at'] : 0;
        // The label path writes its parcel with source carrier itself, so on
        // a labelled parcel that source says nothing: only a driver that
        // tracks (or a marketplace) reads its status.
        $source = ($label && ((string) $parcel['source'] === 'carrier')) ? '' : $parcel['source'];
        $parcel['status_read'] = pg_delivery_tower_status_is_read($source, $label && in_array((string) $label['driver'], (array) $tracking, true));
        $parcel['status'] = $status;

        $found = $status === 'delivered' ? array() : pg_delivery_tower_problems_found($parcel, $figures, $now, $settings, $timezone);
        $pass = pg_delivery_tower_pass($status, isset($open[$id]) ? $open[$id] : array(), $found);

        foreach ($pass['open'] as $kind) {
            $changes['open'][] = array('shipment_id' => (int) $id, 'order_id' => (int) $parcel['order_id'], 'ship_to_id' => (int) $parcel['ship_to_id'], 'kind' => $codes[$kind]);
        }
        foreach ($pass['close'] as $kind => $why) {
            if (isset($codes[$kind])) {
                $changes['close'][] = array('shipment_id' => (int) $id, 'kind' => $codes[$kind], 'reason' => $why);
            }
        }
    }
    return $changes;
}

/**
 * The general job's entry: under the per-database lock, the summary when
 * it is due, then the problem pass when it is due, both inside $budget
 * seconds. A tick with nothing due reads two small config_text rows.
 *
 * @param int      $budget seconds
 * @param int|null $now
 * @return array|null null when nothing ran; else summary (null, or done
 *                    and read) and pass (null, or done, read, opened,
 *                    closed)
 */
function pg_delivery_tower_job_run($budget = PG_DELIVERY_TOWER_TICK_SECONDS, $now = null)
{
    if (!defined('ECOMMERCE') || !ECOMMERCE || !pg_delivery_tower_ready()) {
        return null;
    }

    $now = $now === null ? time() : (int) $now;
    $deadline = microtime(true) + max(1, (int) $budget);
    $summary = pg_delivery_tower_summary();
    $summary_due = pg_delivery_tower_summary_due($summary ? (int) $summary['at'] : 0, $now);
    $record = pg_delivery_tower_pass_record();

    if (!$summary_due && !pg_delivery_tower_pass_due($record, $now)) {
        return null;
    }

    $lock = "LEFT(CONCAT('pg_delivery_tower.', DATABASE()), 64)";
    if ((int) db_value("SELECT GET_LOCK(" . $lock . ", 0)") !== 1) {
        return null;
    }

    $result = array('summary' => null, 'pass' => null);
    if ($summary_due) {
        $result['summary'] = pg_delivery_tower_summary_run($deadline, $now);
        $summary = pg_delivery_tower_summary_reread();
    }
    // The pass waits for a first summary only while it is being built: a
    // site with no delivery yet still gets its problems found.
    if (($result['summary'] === null || $result['summary']['done']) && microtime(true) < $deadline
        && pg_delivery_tower_pass_due(pg_delivery_tower_pass_record(), $now)) {
        $result['pass'] = pg_delivery_tower_pass_run($deadline, $now, $summary ? $summary['figures'] : array());
    }

    db_value("SELECT RELEASE_LOCK(" . $lock . ")");
    return $result;
}

/**
 * The summary read past the request cache, after this run wrote it.
 *
 * @return array|null
 */
function pg_delivery_tower_summary_reread()
{
    $summary = json_decode((string) db_value("SELECT value FROM config_text WHERE name = 'delivery_tower_summary'"), true);
    return is_array($summary) && isset($summary['at'], $summary['figures']) ? $summary : null;
}

/**
 * Where the summary walk keeps its place between ticks, named after the
 * database (sites may share one software directory).
 *
 * @return string
 */
function pg_delivery_tower_summary_state_path()
{
    $database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : (string) db_value("SELECT DATABASE()");
    return PG_FUNCTIONS_DIR . '/data/temp/delivery_tower_summary_' . substr(sha1((string) $database), 0, 12) . '.json';
}

/**
 * The summary walk, until $deadline: from its kept place, pages of the
 * parcels delivered in the window; at the end the summary is written in
 * one write.
 *
 * @param float $deadline microtime
 * @param int   $now
 * @return array done, read
 */
function pg_delivery_tower_summary_run($deadline, $now)
{
    $path = pg_delivery_tower_summary_state_path();
    $state = null;
    if (is_file($path) && (time() - (int) @filemtime($path)) < 86400) {
        $text = (string) @file_get_contents($path);
        $kept = json_decode($text, true);
        if (is_array($kept) && isset($kept['started'])) {
            $state = pg_delivery_tower_summary_state_decode($text, (int) $kept['started']);
        }
    }
    if ($state === null) {
        $state = pg_delivery_tower_summary_state_new($now);
    }

    $from = (int) $state['started'] - PG_DELIVERY_TOWER_DAYS * 86400;
    $read = 0;
    $done = false;

    while (true) {
        $rows = db_items(
            "SELECT
                shipping_tracking_numbers.id,
                shipping_tracking_numbers.carrier,
                shipping_tracking_numbers.shipped_at,
                shipping_tracking_numbers.delivered_at,
                ship_tos.state,
                ship_tos.city,
                ship_tos.delivery_date
            FROM shipping_tracking_numbers
            LEFT JOIN ship_tos ON ship_tos.id = shipping_tracking_numbers.ship_to_id
            WHERE
                (shipping_tracking_numbers.id > '" . (int) $state['after'] . "')
                AND (shipping_tracking_numbers.delivered_at >= '" . $from . "')
                AND (shipping_tracking_numbers.delivered_at <= '" . (int) $state['started'] . "')
            ORDER BY shipping_tracking_numbers.id
            LIMIT " . PG_DELIVERY_TOWER_PAGE);
        if (!is_array($rows)) {
            break;
        }
        pg_delivery_tower_summary_add($state, $rows);
        $read += count($rows);
        if (count($rows) < PG_DELIVERY_TOWER_PAGE) {
            $done = true;
            break;
        }
        if (microtime(true) >= $deadline) {
            break;
        }
    }

    if ($done) {
        pg_config_text_save('delivery_tower_summary', pg_delivery_tower_summary_encode(pg_delivery_tower_summary_finish($state)));
        @unlink($path);
    } else {
        // Written whole, then moved, so a run killed while writing leaves
        // the last good state behind.
        $temp = $path . '.' . (function_exists('getmypid') ? getmypid() : mt_rand());
        if (@file_put_contents($temp, pg_delivery_tower_summary_state_encode($state)) !== false) {
            @rename($temp, $path);
        }
    }

    return array('done' => $done, 'read' => $read);
}

/**
 * The problem pass record, read past the request cache.
 *
 * @return array after, at
 */
function pg_delivery_tower_pass_record()
{
    $record = json_decode((string) db_value("SELECT value FROM config_text WHERE name = 'delivery_tower_pass'"), true);
    return array_merge(array('after' => 0, 'at' => 0, 'quiet' => 0, 'told' => array()), is_array($record) ? $record : array());
}

/**
 * The problem pass, until $deadline: pages of parcels from the kept place,
 * their labels and open problems read alongside, the rows opened and
 * closed; then the open rows whose parcel or order is gone are closed.
 * Each row opened, and each one a later status closed, is announced
 * (includes/fn/delivery_tower_tell.php), except on a site's first walk; the
 * walk's new rows are counted into its alerts, sent when it ends.
 *
 * @param float $deadline microtime
 * @param int   $now
 * @param array $figures the summary's
 * @return array done, read, opened, closed
 */
function pg_delivery_tower_pass_run($deadline, $now, $figures)
{
    $record = pg_delivery_tower_pass_record();
    if (empty($record['after'])) {
        // No walk ever started: this one finds what was already wrong and
        // stays quiet (delivery_tower_tell.php).
        $record = array('after' => 0, 'at' => $now, 'quiet' => empty($record['at']) ? 1 : 0, 'told' => array());
    }
    $told = is_array($record['told']) ? $record['told'] : array();
    $names = array_flip(pg_delivery_tower_kind_codes());

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_tracking.php');
    $tracking = pg_shipment_labels_ready() ? pg_shipment_tracking_drivers() : array();
    $settings = pg_delivery_tower_settings();
    $reason = pg_delivery_tower_close_reasons();
    $result = array('done' => false, 'read' => 0, 'opened' => 0, 'closed' => 0);

    while (true) {
        list($sql, $params) = pg_delivery_tower_parcel_query($record['after'], $now - PG_DELIVERY_TOWER_DAYS * 86400, PG_DELIVERY_TOWER_PAGE);
        $page = db_q($sql, $params);
        if ($page === false) {
            break;
        }
        $parcels = array();
        while ($row = mysqli_fetch_assoc($page)) {
            $parcels[(int) $row['id']] = $row;
        }
        mysqli_free_result($page);
        if ($parcels) {
            $open_ids = array();
            $changes = pg_delivery_tower_parcel_changes($parcels, pg_delivery_tower_page_labels($parcels), pg_delivery_tower_page_open(array_keys($parcels), $open_ids), $figures, $now, $settings, $tracking);
            if ($changes['open']) {
                // One statement a page; the unique key skips a kind already
                // open on a parcel (a run that overlapped this one).
                $values = array();
                foreach ($changes['open'] as $row) {
                    $values[] = '(' . (int) $row['shipment_id'] . ', ' . (int) $row['order_id'] . ', ' . (int) $row['ship_to_id'] . ', ' . (int) $row['kind'] . ', ' . (int) $now . ', ' . (int) $now . ')';
                }
                $written = db_q("INSERT IGNORE INTO shipment_problems (shipment_id, order_id, ship_to_id, kind, found_at, updated_at) VALUES " . implode(', ', $values));
                $result['opened'] += $written !== false ? (int) mysqli_affected_rows(db::$con) : 0;
                // The rows this pass wrote, read back for their ids.
                $shipments = array();
                foreach ($changes['open'] as $row) {
                    $shipments[(int) $row['shipment_id']] = true;
                }
                $opened = db_items(
                    "SELECT id, shipment_id, kind FROM shipment_problems
                    WHERE (closed_at = 0) AND (found_at = '" . (int) $now . "') AND (shipment_id IN (" . implode(', ', array_keys($shipments)) . "))");
                foreach ((array) $opened as $row) {
                    $parcel = $parcels[(int) $row['shipment_id']] ?? null;
                    $kind = $names[(int) $row['kind']] ?? '';
                    if (!$parcel || ($kind === '')) {
                        continue;
                    }
                    pg_delivery_tower_told_add($told, $kind, (string) $parcel['order_number']);
                    if (empty($record['quiet'])) {
                        pg_delivery_tower_announce(false, $parcel, $kind, (int) $row['id'], $figures, $now);
                    }
                }
            }
            foreach ($changes['close'] as $row) {
                $written = db_q(
                    "UPDATE shipment_problems SET closed_at = ?, closed_by = 0, close_reason = ?, updated_at = ? WHERE shipment_id = ? AND kind = ? AND closed_at = 0",
                    array($now, $reason['status'], $now, $row['shipment_id'], $row['kind']));
                $closed = $written !== false ? (int) mysqli_affected_rows(db::$con) : 0;
                $result['closed'] += $closed;
                $problem_id = (int) ($open_ids[$row['shipment_id']][$row['kind']] ?? 0);
                if ($closed && $problem_id && empty($record['quiet']) && isset($names[$row['kind']])) {
                    pg_delivery_tower_announce(true, $parcels[$row['shipment_id']], $names[$row['kind']], $problem_id, $figures, $now, $row['reason']);
                }
            }
            $record['after'] = max(array_keys($parcels));
        }
        $result['read'] += count($parcels);
        if (count($parcels) < PG_DELIVERY_TOWER_PAGE) {
            $result['done'] = true;
            break;
        }
        if (microtime(true) >= $deadline) {
            break;
        }
    }

    if ($result['done']) {
        // An open row whose parcel was removed or whose order was cancelled
        // or deleted: nothing will ever settle it.
        $written = db_q(
            "UPDATE shipment_problems
            LEFT JOIN shipping_tracking_numbers ON shipping_tracking_numbers.id = shipment_problems.shipment_id
            LEFT JOIN orders ON orders.id = shipment_problems.order_id
            SET shipment_problems.closed_at = ?, shipment_problems.closed_by = 0, shipment_problems.close_reason = ?, shipment_problems.updated_at = ?
            WHERE shipment_problems.closed_at = 0
                AND ((shipping_tracking_numbers.id IS NULL) OR (orders.id IS NULL) OR (orders.status IN ('incomplete', 'cancelled')))",
            array($now, $reason['status'], $now));
        $result['closed'] += $written !== false ? (int) mysqli_affected_rows(db::$con) : 0;
        $record['after'] = 0;
        pg_delivery_tower_alerts_send($told);
        $told = array();
    }

    pg_config_text_save('delivery_tower_pass', json_encode(array(
        'after' => (int) $record['after'],
        'at' => (int) $record['at'],
        'quiet' => $result['done'] ? 0 : (int) $record['quiet'],
        'told' => $told,
    )));
    return $result;
}

/**
 * The newest active, non-return label of each parcel of a page.
 *
 * @param array $parcels id => row (ship_to_id, number)
 * @return array "ship_to_id|number" => array(driver, created_at)
 */
function pg_delivery_tower_page_labels($parcels)
{
    if (!pg_shipment_labels_ready()) {
        return array();
    }
    $ship_tos = array();
    foreach ($parcels as $parcel) {
        $ship_tos[(int) $parcel['ship_to_id']] = true;
    }
    $labels = array();
    $rows = db_items(
        "SELECT ship_to_id, number, driver, created_at
        FROM shipment_labels
        WHERE (ship_to_id IN (" . implode(', ', array_keys($ship_tos)) . "))
            AND (state = 'active') AND (is_return = '0')
        ORDER BY created_at");
    foreach ((array) $rows as $row) {
        $labels[(int) $row['ship_to_id'] . '|' . (string) $row['number']] = array('driver' => (string) $row['driver'], 'created_at' => (int) $row['created_at']);
    }
    return $labels;
}

/**
 * The kinds open on each parcel of a page, as kind names.
 *
 * @param int[] $ids
 * @param array $rows filled with shipment id => kind number => problem id
 * @return array shipment id => list of kinds
 */
function pg_delivery_tower_page_open($ids, &$rows = null)
{
    $rows = array();
    $names = array_flip(pg_delivery_tower_kind_codes());
    $open = array();
    $found = db_items(
        "SELECT id, shipment_id, kind FROM shipment_problems
        WHERE (closed_at = 0) AND (shipment_id IN (" . implode(', ', array_map('intval', $ids)) . "))");
    foreach ((array) $found as $row) {
        $rows[(int) $row['shipment_id']][(int) $row['kind']] = (int) $row['id'];
        if (isset($names[(int) $row['kind']])) {
            $open[(int) $row['shipment_id']][] = $names[(int) $row['kind']];
        }
    }
    return $open;
}

/**
 * Removes an order's problem rows, when the order is deleted.
 *
 * @param int $order_id
 */
function pg_delivery_tower_delete_for_order($order_id)
{
    if (function_exists('pg_schema_has') && pg_schema_has('shipment_problems')) {
        db_q("DELETE FROM shipment_problems WHERE order_id = ?", array((int) $order_id));
    }
}
