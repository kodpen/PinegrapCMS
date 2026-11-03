<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Online engagement card: people on the site now, the visits of the
 * last 30 minutes as one bar a minute, and the pages those visits came in
 * on. The window and the role rule are widget 4's, so both dashboards
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

    // Bar 0 is the oldest minute.
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/tips.php');
    $ago = array();
    for ($i = 0; $i < $minutes; $i++) {
        $ago[] = lang(array('string' => '{var:1} minutes ago', 'vars' => $minutes - $i));
    }
    $tips = pg_dashboard_v2_tips($ago, $bars, function ($n) {
        return lang(array('string' => '{var:1} visit{suffix:1}', 'vars' => pg_format_number($n, 0)));
    });

    return array('status' => 'success', 'data' => array(
        'figure'     => $active,
        'headline'   => ($active === 1)
            ? lang('One person on the site now')
            : lang(array('string' => '{var:1} people on the site now', 'vars' => pg_format_number($active, 0))),
        'note'       => lang(array('string' => '{var:1} visit{suffix:1} in the last 30 minutes', 'vars' => pg_format_number($recent, 0))),
        'bars'       => $bars,
        'bars_label' => lang('Visits per minute, last 30 minutes'),
        'tips'       => $tips,
        'rows_title' => lang('Pages they came in on'),
        'rows'       => $rows,
        'empty'      => (($active === 0) && ($recent === 0)) ? lang('Nobody is on the site right now.') : '',
        'url'        => 'view_visitor_reports.php',
    ));
}
