<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pop-up (auto dialog) counts: one auto_dialog_daily row per pop-up and site
 * day with how often it was shown, closed and taken up. The dialog script
 * posts each event once per pop-up and page load to page_view.php with the
 * id and the day signature the page printed; this module checks the post,
 * writes the upsert, sweeps the old days and reads the figures the list and
 * edit screens show. The table holds no visitor data.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Days of counts kept; the daily sweep removes older ones.
define('PG_AUTO_DIALOG_COUNT_DAYS', 400);

// Days the list and edit screens sum.
define('PG_AUTO_DIALOG_COUNT_PERIOD', 30);

// Pop-up beacons one address may send a minute; a visitor sends a few a page.
define('PG_AUTO_DIALOG_COUNT_RATE_LIMIT', 30);

// Where the daily sweep keeps when it last finished (data/temp/).
define('PG_AUTO_DIALOG_COUNT_SWEEP_STATE', 'auto_dialog_count_sweep.json');

/**
 * The events a pop-up counts, each the name of its column.
 *
 * @return array
 */
function pg_auto_dialog_count_events()
{
    return array('shown', 'closed', 'taken');
}

/**
 * Whether the site has run the step that adds auto_dialog_daily.
 *
 * @return bool
 */
function pg_auto_dialog_counts_ready()
{
    return pg_schema_has('auto_dialog_daily');
}

/**
 * The signature a page prints with a pop-up for one day, with the site's key;
 * '' without a key or an id, and then the pop-up sends no beacon. The day
 * bounds how long a signature copied out of a page can be replayed. Pure.
 *
 * @param int    $auto_dialog_id
 * @param string $key ENCRYPTION_KEY
 * @param string $day Y-m-d, the day the page was drawn
 * @return string
 */
function pg_auto_dialog_count_sign($auto_dialog_id, $key, $day)
{
    $auto_dialog_id = (int) $auto_dialog_id;
    $key = (string) $key;

    if (($auto_dialog_id <= 0) || ($key === '') || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day)) {
        return '';
    }

    return hash_hmac('sha256', 'auto-dialog:' . $auto_dialog_id . '|' . $day, $key);
}

/**
 * Reads a pop-up beacon: the id, the event and the signature this site gave
 * the id today or yesterday (a page drawn before midnight, or kept by the
 * page cache, still counts the next day). Anything else in the wrong shape
 * drops the whole post. Pure.
 *
 * @param array  $post $_POST
 * @param string $key  ENCRYPTION_KEY
 * @param int    $now  timestamp, 0 for now
 * @return array|null array(id, event), null when the post is not counted
 */
function pg_auto_dialog_count_parse($post, $key, $now = 0)
{
    $id = $post['ad'] ?? null;
    $event = $post['ae'] ?? null;
    $signature = $post['as'] ?? null;

    if (!is_string($id) || !ctype_digit($id) || (strlen($id) > 10) || ((int) $id <= 0)
        || !is_string($event) || !in_array($event, pg_auto_dialog_count_events(), true)
        || !is_string($signature) || ($signature === '')) {
        return null;
    }

    $now = ((int) $now > 0) ? (int) $now : time();

    foreach (array(date('Y-m-d', $now), date('Y-m-d', strtotime('-1 day', $now))) as $day) {
        $expected = pg_auto_dialog_count_sign((int) $id, $key, $day);

        if (($expected !== '') && hash_equals($expected, $signature)) {
            return array((int) $id, $event);
        }
    }

    return null;
}

/**
 * The page's pop-up rows, each with the signature of its counts beacon for
 * the day (sign); a row is left as it is without a key. Pure.
 *
 * @param array  $rows pg_auto_dialogs_on_page()
 * @param string $key  ENCRYPTION_KEY, '' when the counts are off
 * @param string $day  Y-m-d
 * @return array
 */
function pg_auto_dialog_count_signed($rows, $key, $day)
{
    $signed = array();

    foreach ((array) $rows as $row) {
        $signature = pg_auto_dialog_count_sign($row['id'] ?? 0, $key, $day);

        if ($signature !== '') {
            $row['sign'] = $signature;
        }

        $signed[] = $row;
    }

    return $signed;
}

/**
 * Whether one more pop-up beacon from this address would pass the per-minute
 * limit, counted in the firewall's rate buckets, as the page speed beacon is.
 *
 * @return bool
 */
function pg_auto_dialog_count_rate_exceeded()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip')) {
        return false;
    }

    $ip = waf_client_ip();

    if (function_exists('waf_ip_subject')) {
        $ip = waf_ip_subject($ip);
    }

    return waf_rate_exceeded($ip, 'popup', PG_AUTO_DIALOG_COUNT_RATE_LIMIT, 60);
}

/**
 * Whether this request may count a pop-up event: not a crawler or a script,
 * not staff (the site search rule, pg_site_search_user_countable()), and
 * not a visitor who refused statistics in the cookie notice (the one check
 * of the anonymous counts, pg_consent_statistics_not_refused()). The dialog
 * script asks the browser (software.statistics_allowed()) before it sends;
 * this is the server's own check of the same answer.
 *
 * @return bool
 */
function pg_auto_dialog_count_allowed()
{
    if (function_exists('pg_request_is_automated') && pg_request_is_automated()) {
        return false;
    }

    if (!pg_site_search_user_countable(defined('USER_LOGGED_IN') && USER_LOGGED_IN, defined('USER_ROLE') ? USER_ROLE : null)) {
        return false;
    }

    return !function_exists('pg_consent_statistics_not_refused') || pg_consent_statistics_not_refused();
}

/**
 * The upsert of one event: the id and the day are bound in that order; the
 * column is one of pg_auto_dialog_count_events(). Pure.
 *
 * @param string $event
 * @return string '' for an unknown event
 */
function pg_auto_dialog_count_upsert_sql($event)
{
    if (!in_array($event, pg_auto_dialog_count_events(), true)) {
        return '';
    }

    return "INSERT INTO auto_dialog_daily (auto_dialog_id, day, " . $event . ") VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE " . $event . " = " . $event . " + 1";
}

/**
 * Counts one event of a pop-up that still exists, on the site's day. Nothing
 * on a site without the table.
 *
 * @param int    $auto_dialog_id
 * @param string $event
 * @return bool
 */
function pg_auto_dialog_count($auto_dialog_id, $event)
{
    $sql = pg_auto_dialog_count_upsert_sql($event);

    if (($sql === '') || !pg_auto_dialog_counts_ready()) {
        return false;
    }

    if (!db_value("SELECT id FROM auto_dialogs WHERE id = '" . (int) $auto_dialog_id . "' LIMIT 1")) {
        return false;
    }

    return (bool) db_q($sql, array((int) $auto_dialog_id, date('Y-m-d')));
}

/**
 * The first day the screens sum: the period ends today. Pure.
 *
 * @param int $days
 * @param int $now timestamp, 0 for now
 * @return string Y-m-d
 */
function pg_auto_dialog_count_from($days = PG_AUTO_DIALOG_COUNT_PERIOD, $now = 0)
{
    $now = ((int) $now > 0) ? (int) $now : time();

    return date('Y-m-d', strtotime('-' . max(0, (int) $days - 1) . ' days', $now));
}

/**
 * The last period's counts of every pop-up, or of one, in one grouped query:
 * id => array(shown, closed, taken). A pop-up with no row is missing; an
 * empty list on a site without the table or when the query failed.
 *
 * @param int|null $auto_dialog_id null for all
 * @param int      $days
 * @return array
 */
function pg_auto_dialog_count_figures($auto_dialog_id = null, $days = PG_AUTO_DIALOG_COUNT_PERIOD)
{
    if (!pg_auto_dialog_counts_ready()) {
        return array();
    }

    $sql = "SELECT auto_dialog_id, SUM(shown) AS shown, SUM(closed) AS closed, SUM(taken) AS taken
        FROM auto_dialog_daily
        WHERE day >= ?";
    $values = array(pg_auto_dialog_count_from($days));

    if ($auto_dialog_id !== null) {
        $sql .= " AND auto_dialog_id = ?";
        $values[] = (int) $auto_dialog_id;
    }

    $result = db_q($sql . " GROUP BY auto_dialog_id", $values);

    return $result ? pg_auto_dialog_count_rows(mysqli_fetch_all($result, MYSQLI_ASSOC)) : array();
}

/**
 * The grouped rows as id => array(shown, closed, taken) of whole numbers.
 * Pure.
 *
 * @param array $rows
 * @return array
 */
function pg_auto_dialog_count_rows($rows)
{
    $figures = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row) || ((int) ($row['auto_dialog_id'] ?? 0) <= 0)) {
            continue;
        }

        $figures[(int) $row['auto_dialog_id']] = array(
            'shown' => (int) ($row['shown'] ?? 0),
            'closed' => (int) ($row['closed'] ?? 0),
            'taken' => (int) ($row['taken'] ?? 0),
        );
    }

    return $figures;
}

/**
 * A pop-up's counts as the screens show them, zeros when it has none.
 *
 * @param array $figures pg_auto_dialog_count_figures()
 * @param int   $auto_dialog_id
 * @return array shown, closed, taken
 */
function pg_auto_dialog_count_of($figures, $auto_dialog_id)
{
    return $figures[(int) $auto_dialog_id] ?? array('shown' => 0, 'closed' => 0, 'taken' => 0);
}

/**
 * Removes a deleted pop-up's counts.
 *
 * @param int $auto_dialog_id
 * @return void
 */
function pg_auto_dialog_count_delete($auto_dialog_id)
{
    if (pg_auto_dialog_counts_ready()) {
        db_q("DELETE FROM auto_dialog_daily WHERE auto_dialog_id = ?", array((int) $auto_dialog_id));
    }
}

/**
 * The first day the table keeps. Pure.
 *
 * @param int $now timestamp, 0 for now
 * @return string Y-m-d
 */
function pg_auto_dialog_count_keep_from($now = 0)
{
    $now = ((int) $now > 0) ? (int) $now : time();

    return date('Y-m-d', strtotime('-' . PG_AUTO_DIALOG_COUNT_DAYS . ' days', $now));
}

/**
 * Where the daily sweep keeps when it last finished.
 *
 * @return string
 */
function pg_auto_dialog_count_sweep_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/' . PG_AUTO_DIALOG_COUNT_SWEEP_STATE;
}

/**
 * Whether the daily sweep is owed: it has not run in the last day.
 *
 * @return bool
 */
function pg_auto_dialog_count_sweep_due()
{
    if (!pg_auto_dialog_counts_ready()) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_auto_dialog_count_sweep_file()), true);
    $done = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The daily pass (job.php): the days older than PG_AUTO_DIALOG_COUNT_DAYS go,
 * five thousand rows at a time within the time budget. Writes the state file
 * when through.
 *
 * @param int $seconds
 * @return int rows removed
 */
function pg_auto_dialog_count_sweep($seconds = 5)
{
    if (!pg_auto_dialog_counts_ready()) {
        return 0;
    }

    $removed = 0;
    $deadline = microtime(true) + max(1, (int) $seconds);
    $keep_from = pg_auto_dialog_count_keep_from();

    while (microtime(true) < $deadline) {
        if (db_q("DELETE FROM auto_dialog_daily WHERE day < ? LIMIT 5000", array($keep_from)) === false) {
            return $removed;
        }

        $affected = max(0, (int) mysqli_affected_rows(db::$con));
        $removed += $affected;

        if ($affected < 5000) {
            $file = pg_auto_dialog_count_sweep_file();

            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0755, true);
            }

            @file_put_contents($file, json_encode(array('done_at' => time())), LOCK_EX);
            break;
        }
    }

    return $removed;
}
