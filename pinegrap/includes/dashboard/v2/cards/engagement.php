<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Online engagement card: people on the site now, the visits of the
 * last 30 minutes as one bar a minute, and the pages those visits came in
 * on; with nobody on the site, when the last visitors were online. The window and the role rule are widget 4's, so both dashboards
 * count the same people.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * Visit starts per minute, oldest first.
 *
 * @param array $rows   rows of minute (0 = the oldest minute) and count
 * @param int   $minutes
 * @return int[]
 */
function pg_dashboard_v2_minute_bars($rows, $minutes)
{
    $bars = array_fill(0, (int) $minutes, 0);

    foreach ((array) $rows as $row) {
        $minute = (int) $row['minute'];

        if (($minute >= 0) && ($minute < $minutes)) {
            $bars[$minute] += (int) $row['visits'];
        }
    }

    return $bars;
}

// How many of the last visits the card lists while nobody is on the site,
// and how many of the newest visit ids it looks through to find them: the
// read walks the primary key down from MAX(id), never the whole table.
define('PG_DASHBOARD_V2_LAST_VISITS', 5);
define('PG_DASHBOARD_V2_LAST_VISITS_WINDOW', 200);

/**
 * The card's "last visits" rows, latest first: when the visitor was last
 * seen (how long ago, then the clock in the site's time) and the page the
 * visit came in on.
 *
 * A visit was last seen at its stop_timestamp, the last request it made;
 * a visit that never recorded one counts from its start.
 *
 * @param array    $visits rows of id, landing_page_name, start_timestamp, stop_timestamp
 * @param int      $limit
 * @param callable $ago    timestamp => "5 hours ago"
 * @param callable $clock  timestamp => "14:05"
 * @return array[] rows of when, ago, clock, page
 */
function pg_dashboard_v2_last_visit_rows($visits, $limit, $ago, $clock)
{
    $rows = array();

    foreach ((array) $visits as $visit) {
        $seen = (int) ($visit['stop_timestamp'] ?? 0);

        if ($seen <= 0) {
            $seen = (int) ($visit['start_timestamp'] ?? 0);
        }

        if ($seen <= 0) {
            continue;
        }

        $rows[] = array(
            'when'  => $seen,
            'id'    => (int) ($visit['id'] ?? 0),
            'page'  => trim((string) ($visit['landing_page_name'] ?? '')),
        );
    }

    usort($rows, function ($a, $b) {
        if ($a['when'] !== $b['when']) {
            return ($a['when'] < $b['when']) ? 1 : -1;
        }

        return ($a['id'] < $b['id']) ? 1 : (($a['id'] > $b['id']) ? -1 : 0);
    });

    $out = array();

    foreach (array_slice($rows, 0, max(0, (int) $limit)) as $row) {
        $out[] = array(
            'when'  => $row['when'],
            'ago'   => (string) $ago($row['when']),
            'clock' => (string) $clock($row['when']),
            'page'  => $row['page'],
        );
    }

    return $out;
}

/**
 * The last visits from the newest visit ids: MAX(id) on the primary key,
 * then a range of PG_DASHBOARD_V2_LAST_VISITS_WINDOW ids below it, sorted
 * in PHP. The raw visitors table is never scanned or counted.
 *
 * @return array[] pg_dashboard_v2_last_visit_rows()
 */
function pg_dashboard_v2_last_visits()
{
    $top = (int) db_value("SELECT MAX(id) FROM visitors");

    if ($top < 1) {
        return array();
    }

    $visits = db_items(
        "SELECT id, landing_page_name, start_timestamp, stop_timestamp FROM visitors
         WHERE id > " . max(0, $top - PG_DASHBOARD_V2_LAST_VISITS_WINDOW) . " AND id <= " . $top);

    return pg_dashboard_v2_last_visit_rows($visits, PG_DASHBOARD_V2_LAST_VISITS, function ($timestamp) {
        return function_exists('get_relative_time')
            ? get_relative_time(array('timestamp' => (int) $timestamp, 'format' => 'plain_text'))
            : '';
    }, function ($timestamp) {
        return function_exists('get_absolute_time')
            ? get_absolute_time(array('timestamp' => (int) $timestamp, 'type' => 'time', 'timezone_type' => 'site', 'format' => 'plain_text'))
            : '';
    });
}

function pg_dashboard_v2_card_engagement($user, $period, $compare)
{
    if ((int) $user['role'] >= 3) {
        return array('status' => 'denied');
    }

    $now = time();
    $minutes = 30;
    $from = $now - ($minutes * 60);

    // Twenty minutes, the span widget 4 calls "active".
    $active = (int) db_value("SELECT COUNT(*) FROM visitors WHERE stop_timestamp >= " . ($now - 1200));

    $bars = pg_dashboard_v2_minute_bars(db_items(
        "SELECT FLOOR((start_timestamp - " . $from . ") / 60) AS minute, COUNT(*) AS visits
         FROM visitors WHERE start_timestamp >= " . $from . "
         GROUP BY minute"), $minutes);

    $rows = array();

    foreach (db_items(
        "SELECT landing_page_name AS name, COUNT(*) AS visits FROM visitors
         WHERE stop_timestamp >= " . ($now - 1200) . " AND landing_page_name != ''
         GROUP BY landing_page_name ORDER BY visits DESC, landing_page_name ASC LIMIT 5") as $row) {
        $rows[] = array('text' => (string) $row['name'], 'value' => (int) $row['visits'], 'note' => pg_format_number((int) $row['visits'], 0));
    }

    $recent = array_sum($bars);

    // Nobody on the site: the card says so and lists when visitors were
    // last online, so it is not one bare line.
    $last = ($active === 0) ? pg_dashboard_v2_last_visits() : array();

    // Bar 0 is the oldest minute.
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/tips.php');
    $ago = array();
    for ($i = 0; $i < $minutes; $i++) {
        $ago[] = lang(array('string' => '{var:1} minutes ago', 'vars' => $minutes - $i));
    }
    $tips = pg_dashboard_v2_tips($ago, $bars, function ($n) {
        return lang(array('string' => '{var:1} visit{suffix:1}', 'vars' => pg_format_number($n, 0), 'suffix' => pg_dashboard_v2_plural_suffix($n)));
    });

    return array('status' => 'success', 'data' => array(
        'figure'     => $active,
        'label'      => lang('On the site now'),
        'headline'   => ($active === 1)
            ? lang('One person on the site now')
            : lang(array('string' => '{var:1} people on the site now', 'vars' => pg_format_number($active, 0))),
        'note'       => lang(array('string' => '{var:1} visit{suffix:1} in the last 30 minutes', 'vars' => pg_format_number($recent, 0), 'suffix' => pg_dashboard_v2_plural_suffix($recent))),
        'bars'       => $bars,
        'bars_label' => lang('Visits per minute, last 30 minutes'),
        'tips'       => $tips,
        'rows_title' => lang('Pages they came in on'),
        'rows'       => $rows,
        // Only when there is nothing at all to draw: with last visits the
        // line is the renderer's heading (nobody), above the list.
        'empty'      => (($active === 0) && ($recent === 0) && !$last) ? lang('Nobody is on the site right now.') : '',
        'nobody'     => ($active === 0) ? lang('Nobody is on the site right now.') : '',
        'last_title' => lang('Last visits'),
        'last'       => $last,
        'url'        => 'view_visitor_reports.php',
    ));
}
