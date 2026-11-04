<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The alerts section of the Visitors overview, drawn first: one line for
 * each figure that fell sharply against the period before (visits, a
 * landing page's visits, the conversion rate, Google clicks, a page's
 * Google clicks), and one when no visit has been recorded today although
 * yesterday had visits, which points at broken tracking. Each line carries
 * its figures and a link to the section that shows them. Nothing is stored:
 * the alerts are worked out on every render from the same figures the other
 * sections read (pg_visitor_totals(), pg_visitor_breakdown(), pg_gsc_*()).
 * With nothing to report the section draws nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');

// Visits, or Google clicks, down by more than this share of the period before.
const PG_VISITORS_ALERTS_DROP = 0.3;

// A landing page, or a page in Google, that lost more than this share.
const PG_VISITORS_ALERTS_PAGE_LOSS = 0.5;

// The conversion rate fallen to under this share of the period before.
const PG_VISITORS_ALERTS_RATE_LOSS = 0.5;

// The least the period before must hold for a fall to mean something, so a
// quiet site is not warned about three visits against five.
const PG_VISITORS_ALERTS_MIN_VISITS = 50;
const PG_VISITORS_ALERTS_MIN_PAGE_VISITS = 50;
const PG_VISITORS_ALERTS_MIN_ORDERS = 5;
const PG_VISITORS_ALERTS_MIN_CLICKS = 50;
const PG_VISITORS_ALERTS_MIN_PAGE_CLICKS = 20;

// From this hour of the site's clock a day without a visit is an alert.
const PG_VISITORS_ALERTS_QUIET_HOUR = 10;

// How many pages each page rule names at most, the largest losses first.
const PG_VISITORS_ALERTS_PAGES = 3;

// How deep the page lists are read, so a page that fell out of the top is
// still found in the period before.
const PG_VISITORS_ALERTS_DEPTH = 200;

/**
 * The fall of a figure, in whole percent, when it fell by more than a share
 * and the earlier figure is at least a minimum; null otherwise. Pure.
 *
 * @param int|float $now
 * @param int|float $before
 * @param float     $share   0.3 for "down by more than 30 %"
 * @param int|float $minimum
 * @return int|null
 */
function pg_visitors_alerts_fall($now, $before, $share, $minimum = 1)
{
    $now = (float) $now;
    $before = (float) $before;

    if (($before <= 0) || ($before < (float) $minimum) || ($now >= $before * (1 - (float) $share))) {
        return null;
    }

    return (int) round(($before - $now) / $before * 100);
}

/**
 * Visits down by more than PG_VISITORS_ALERTS_DROP. Pure.
 *
 * @param array      $now    pg_visitor_totals() of the period
 * @param array|null $before of the period before
 * @return array|null array('rule' => 'visits', 'section' => 'traffic',
 *                    'now', 'before', 'percent')
 */
function pg_visitors_alerts_visits(array $now, $before)
{
    if (!is_array($before)) {
        return null;
    }

    $visits = isset($now['visits']) ? (int) $now['visits'] : 0;
    $earlier = isset($before['visits']) ? (int) $before['visits'] : 0;
    $percent = pg_visitors_alerts_fall($visits, $earlier, PG_VISITORS_ALERTS_DROP, PG_VISITORS_ALERTS_MIN_VISITS);

    if ($percent === null) {
        return null;
    }

    return array('rule' => 'visits', 'section' => 'traffic', 'now' => $visits, 'before' => $earlier, 'percent' => $percent);
}

/**
 * Breakdown rows added up by value without regard to case, '' and '/'
 * meeting as the home page. Pure.
 *
 * @param array  $rows   pg_visitor_breakdown() rows, or pg_gsc_top() rows
 * @param string $figure the figure to add up (visits, clicks)
 * @param string $name   the key that holds the value (value, name)
 * @return array lower-cased value => array('value', 'figure')
 */
function pg_visitors_alerts_fold(array $rows, $figure, $name = 'value')
{
    $out = array();

    foreach ($rows as $row) {
        $value = trim(isset($row[$name]) ? (string) $row[$name] : '');

        if ($value === '/') {
            $value = '';
        }

        $key = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

        if (!isset($out[$key])) {
            $out[$key] = array('value' => $value, 'figure' => 0);
        }

        $out[$key]['figure'] += isset($row[$figure]) ? (int) $row[$figure] : 0;
    }

    return $out;
}

/**
 * The pages that lost more than PG_VISITORS_ALERTS_PAGE_LOSS of a figure
 * they had at least a minimum of, the largest losses first, at most
 * PG_VISITORS_ALERTS_PAGES. Pure.
 *
 * @param array  $now     rows of the period
 * @param array  $before  rows of the period before
 * @param string $figure  visits or clicks
 * @param int    $minimum the least the page had before
 * @param string $name    the key that holds the page (value, name)
 * @return array list of array('page', 'now', 'before', 'percent')
 */
function pg_visitors_alerts_page_losses(array $now, array $before, $figure, $minimum, $name = 'value')
{
    $now = pg_visitors_alerts_fold($now, $figure, $name);
    $out = array();

    foreach (pg_visitors_alerts_fold($before, $figure, $name) as $key => $row) {
        $current = isset($now[$key]) ? $now[$key]['figure'] : 0;
        $percent = pg_visitors_alerts_fall($current, $row['figure'], PG_VISITORS_ALERTS_PAGE_LOSS, $minimum);

        if ($percent !== null) {
            $out[] = array('page' => $row['value'], 'now' => $current, 'before' => $row['figure'], 'percent' => $percent);
        }
    }

    usort($out, function ($a, $b) {
        $lost_a = $a['before'] - $a['now'];
        $lost_b = $b['before'] - $b['now'];

        if ($lost_a !== $lost_b) {
            return ($lost_a > $lost_b) ? -1 : 1;
        }

        return strcmp($a['page'], $b['page']);
    });

    return array_slice($out, 0, PG_VISITORS_ALERTS_PAGES);
}

/**
 * Landing pages that lost more than half their visits, at least
 * PG_VISITORS_ALERTS_MIN_PAGE_VISITS before. Pure.
 *
 * @param array      $now    pg_visitor_breakdown('landing_page_name') rows
 * @param array|null $before of the period before
 * @return array list of array('rule' => 'landing', 'section' => 'content',
 *               'page', 'now', 'before', 'percent')
 */
function pg_visitors_alerts_landing(array $now, $before)
{
    if (!is_array($before)) {
        return array();
    }

    $out = array();

    foreach (pg_visitors_alerts_page_losses($now, $before, 'visits', PG_VISITORS_ALERTS_MIN_PAGE_VISITS) as $loss) {
        $out[] = array('rule' => 'landing', 'section' => 'content') + $loss;
    }

    return $out;
}

/**
 * Completed orders over visits in percent; null without a visit. Pure.
 *
 * @param array|null $totals pg_visitor_totals()
 * @return float|null
 */
function pg_visitors_alerts_rate_of($totals)
{
    if (!is_array($totals) || empty($totals['visits'])) {
        return null;
    }

    return (isset($totals['orders_completed']) ? (int) $totals['orders_completed'] : 0) / (int) $totals['visits'] * 100;
}

/**
 * The conversion rate (completed orders over visits) fallen to under half,
 * with at least PG_VISITORS_ALERTS_MIN_ORDERS completed orders and
 * PG_VISITORS_ALERTS_MIN_VISITS visits before. Pure.
 *
 * @param array      $now    pg_visitor_totals() of the period
 * @param array|null $before of the period before
 * @return array|null array('rule' => 'rate', 'section' => 'conversions',
 *                    'now', 'before' (percent, two decimals), 'percent')
 */
function pg_visitors_alerts_rate(array $now, $before)
{
    if (!is_array($before)
        || (isset($before['orders_completed']) ? (int) $before['orders_completed'] : 0) < PG_VISITORS_ALERTS_MIN_ORDERS
        || (isset($before['visits']) ? (int) $before['visits'] : 0) < PG_VISITORS_ALERTS_MIN_VISITS
        || empty($now['visits'])) {
        return null;
    }

    $rate = pg_visitors_alerts_rate_of($now);
    $earlier = pg_visitors_alerts_rate_of($before);
    $percent = pg_visitors_alerts_fall($rate, $earlier, PG_VISITORS_ALERTS_RATE_LOSS);

    if ($percent === null) {
        return null;
    }

    return array('rule' => 'rate', 'section' => 'conversions', 'now' => round($rate, 2), 'before' => round($earlier, 2), 'percent' => $percent);
}

/**
 * Google clicks down by more than PG_VISITORS_ALERTS_DROP. Pure.
 *
 * @param array      $now    pg_gsc_totals() of the window
 * @param array|null $before of the window before
 * @return array|null array('rule' => 'clicks', 'section' => 'google',
 *                    'now', 'before', 'percent')
 */
function pg_visitors_alerts_clicks(array $now, $before)
{
    if (!is_array($before)) {
        return null;
    }

    $clicks = isset($now['clicks']) ? (int) $now['clicks'] : 0;
    $earlier = isset($before['clicks']) ? (int) $before['clicks'] : 0;
    $percent = pg_visitors_alerts_fall($clicks, $earlier, PG_VISITORS_ALERTS_DROP, PG_VISITORS_ALERTS_MIN_CLICKS);

    if ($percent === null) {
        return null;
    }

    return array('rule' => 'clicks', 'section' => 'google', 'now' => $clicks, 'before' => $earlier, 'percent' => $percent);
}

/**
 * Pages that lost more than half their Google clicks, at least
 * PG_VISITORS_ALERTS_MIN_PAGE_CLICKS before. Pure.
 *
 * @param array      $now     pg_gsc_top('page', ...) rows of the window
 * @param array|null $before  of the window before
 * @param string     $section the section the lines link to
 * @return array list of array('rule' => 'google_page', 'section', 'page',
 *               'now', 'before', 'percent')
 */
function pg_visitors_alerts_google_pages(array $now, $before, $section = 'google')
{
    if (!is_array($before)) {
        return array();
    }

    $out = array();

    foreach (pg_visitors_alerts_page_losses($now, $before, 'clicks', PG_VISITORS_ALERTS_MIN_PAGE_CLICKS, 'name') as $loss) {
        $out[] = array('rule' => 'google_page', 'section' => (string) $section) + $loss;
    }

    return $out;
}

/**
 * No visit recorded today after PG_VISITORS_ALERTS_QUIET_HOUR although
 * yesterday had visits: the tracking may be broken. Pure.
 *
 * @param int $today     visits recorded today so far
 * @param int $yesterday visits yesterday
 * @param int $hour      the hour now by the site's clock, 0-23
 * @return array|null array('rule' => 'quiet', 'section' => 'traffic',
 *                    'now' => 0, 'before' => yesterday's visits)
 */
function pg_visitors_alerts_quiet($today, $yesterday, $hour)
{
    if (((int) $today > 0) || ((int) $yesterday <= 0) || ((int) $hour < PG_VISITORS_ALERTS_QUIET_HOUR)) {
        return null;
    }

    return array('rule' => 'quiet', 'section' => 'traffic', 'now' => 0, 'before' => (int) $yesterday, 'percent' => 100);
}

/**
 * The text of an alert line.
 *
 * @param array $alert one of the rules' answers
 * @return string plain text
 */
function pg_visitors_alerts_text(array $alert)
{
    $page = isset($alert['page']) ? (string) $alert['page'] : '';
    $count = function ($value) {
        return pg_format_number((int) $value, 0);
    };

    switch ($alert['rule']) {

        case 'visits':
            return lang(array('string' => 'Visits fell by {var:1} %: {var:2} against {var:3} in the period before.', 'vars' => array($alert['percent'], $count($alert['now']), $count($alert['before']))));

        case 'landing':
            return lang(array('string' => 'The landing page {var:1} lost {var:2} % of its visits: {var:3} against {var:4} in the period before.', 'vars' => array(($page === '') ? lang('Home page') : $page, $alert['percent'], $count($alert['now']), $count($alert['before']))));

        case 'rate':
            return lang(array('string' => 'The conversion rate fell by more than half: {var:1} % against {var:2} % in the period before.', 'vars' => array(pg_format_number($alert['now'], 2), pg_format_number($alert['before'], 2))));

        case 'clicks':
            return lang(array('string' => 'Google clicks fell by {var:1} %: {var:2} against {var:3} in the period before.', 'vars' => array($alert['percent'], $count($alert['now']), $count($alert['before']))));

        case 'google_page':
            return lang(array('string' => 'The page {var:1} lost {var:2} % of its Google clicks: {var:3} against {var:4} in the period before.', 'vars' => array(pg_gsc_page_label($page, pg_gsc_site_url()), $alert['percent'], $count($alert['now']), $count($alert['before']))));

        case 'quiet':
            return lang(array('string' => 'No visit has been recorded today, although yesterday had {var:1}: the visit tracking may be broken.', 'vars' => array($count($alert['before']))));
    }

    return '';
}

/**
 * The alerts as list items: an icon, the text and a link to the section.
 *
 * @param array $alerts the rules' answers
 * @param array $period pg_visitor_period()
 * @return string HTML
 */
function pg_visitors_alerts_list(array $alerts, array $period)
{
    $sections = pg_visitors_sections();
    $query = pg_visitor_period_query($period);
    $items = '';

    foreach ($alerts as $alert) {
        $section = isset($sections[$alert['section']]) ? $alert['section'] : 'traffic';
        $icon = ($alert['rule'] === 'quiet') ? 'bi-exclamation-octagon' : 'bi-graph-down-arrow';

        $items .= '
            <li class="pg-visitors-alert is-' . h($alert['rule']) . '">
                <i class="bi ' . $icon . '" aria-hidden="true"></i>
                <span class="pg-visitors-alert-text">' . h(pg_visitors_alerts_text($alert)) . '</span>
                <a class="pg-visitors-alert-link" href="visitors.php?' . h($query) . '#' . h(pg_visitors_anchor($section)) . '">' . h($sections[$section]) . '</a>
            </li>';
    }

    return '<ul class="pg-visitors-alert-list">' . $items . '</ul>';
}

/**
 * The Google alerts over the window the Google section reads: the overview
 * period's length, ending on the latest day with figures when the period
 * runs past it; none when the download does not reach the window before.
 *
 * @param array $period pg_visitor_period()
 * @return array the rules' answers
 */
function pg_visitors_alerts_google(array $period)
{
    if (!pg_gsc_linked()) {
        return array();
    }

    $latest = pg_gsc_latest_day();

    if ($latest === '') {
        return array();
    }

    $end = ($latest < (string) $period['to']) ? $latest : (string) $period['to'];
    $window = pg_gsc_period($end, max(1, (int) $period['days']));

    if (!pg_gsc_comparable(pg_gsc_oldest_day(), $window['previous_from'])) {
        return array();
    }

    $alerts = array();
    $clicks = pg_visitors_alerts_clicks(pg_gsc_totals($window['from'], $window['to']), pg_gsc_totals($window['previous_from'], $window['previous_to']));

    if ($clicks !== null) {
        $alerts[] = $clicks;
    }

    $pages = pg_visitors_alerts_google_pages(
        pg_gsc_top('page', $window['from'], $window['to'], PG_VISITORS_ALERTS_DEPTH),
        pg_gsc_top('page', $window['previous_from'], $window['previous_to'], PG_VISITORS_ALERTS_DEPTH),
        pg_visitors_section_exists('pages') ? 'pages' : 'google'
    );

    return array_merge($alerts, $pages);
}

/**
 * The section: '' when there is nothing to report.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML
 */
function pg_visitors_section_alerts(array $period, array $user)
{
    if (!function_exists('pg_visitor_totals') || !function_exists('pg_visitor_breakdown')) {
        return '';
    }

    $from = (int) $period['from_ts'];
    $last = (int) $period['to_ts'] - 1;
    $previous_from = (int) $period['previous_from_ts'];
    $previous_last = (int) $period['previous_to_ts'] - 1;

    $now = pg_visitor_totals($from, $last);
    $before = pg_visitor_totals($previous_from, $previous_last);

    $alerts = array();

    $visits = pg_visitors_alerts_visits($now, $before);

    if ($visits !== null) {
        $alerts[] = $visits;
    }

    $landing_now = pg_visitor_breakdown('landing_page_name', $from, $last, PG_VISITORS_ALERTS_DEPTH);
    $landing_before = pg_visitor_breakdown('landing_page_name', $previous_from, $previous_last, PG_VISITORS_ALERTS_DEPTH);

    if (is_array($landing_now) && is_array($landing_before)) {
        $alerts = array_merge($alerts, pg_visitors_alerts_landing($landing_now['rows'], $landing_before['rows']));
    }

    if (defined('ECOMMERCE') && (ECOMMERCE == true)) {
        $rate = pg_visitors_alerts_rate($now, $before);

        if ($rate !== null) {
            $alerts[] = $rate;
        }
    }

    $alerts = array_merge($alerts, pg_visitors_alerts_google($period));

    // Tracking is checked on today whatever the period shows.
    $time = time();
    $today = date('Y-m-d', $time);
    $midnight = strtotime($today . ' 00:00:00');

    if ((int) date('G', $time) >= PG_VISITORS_ALERTS_QUIET_HOUR) {
        $today_totals = pg_visitor_totals($midnight, $time);

        if (empty($today_totals['visits'])) {
            $yesterday = pg_visitor_period_shift($today, -1);
            $yesterday_totals = pg_visitor_totals(strtotime($yesterday . ' 00:00:00'), $midnight - 1);
            $quiet = pg_visitors_alerts_quiet(0, (int) $yesterday_totals['visits'], (int) date('G', $time));

            if ($quiet !== null) {
                array_unshift($alerts, $quiet);
            }
        }
    }

    if (!$alerts) {
        return '';
    }

    return pg_visitors_section('alerts', lang('Alerts'), pg_visitors_alerts_list($alerts, $period));
}
