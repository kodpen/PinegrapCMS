<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The period and the section list of the Visitors overview (visitors.php):
 * which days a preset or a custom range covers by the site's clock, the
 * period it is compared with, the grain its charts are drawn in (weeks start
 * on Monday), the links that keep it, and the sections the screen and the
 * Visitors menu list. Nothing here reads the database or the session; the
 * period travels in the query string only.
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
 * The presets in the order the period bar shows them, key => label. The keys
 * are the `p` values of the query string; '7d', '30d' and '90d' are also
 * pg_visitor_insight_window()'s.
 *
 * @return array
 */
function pg_visitor_period_presets()
{
    return array(
        'today'      => lang('Today'),
        'yesterday'  => lang('Yesterday'),
        '7d'         => lang(array('string' => 'Last {var:1} days', 'vars' => array(7))),
        '30d'        => lang(array('string' => 'Last {var:1} days', 'vars' => array(30))),
        '90d'        => lang(array('string' => 'Last {var:1} days', 'vars' => array(90))),
        'month'      => lang('This month'),
        'last_month' => lang('Last month'),
        'custom'     => lang('Custom range'),
    );
}

/**
 * A Y-m-d text as that date, or '' when it is no real date. Pure.
 *
 * @param mixed $text
 * @return string
 */
function pg_visitor_period_date($text)
{
    if (!is_string($text) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $parts)) {
        return '';
    }

    if (((int) $parts[1] < 1970) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
        return '';
    }

    return $text;
}

/**
 * A day moved by whole days. Steps from noon, so a daylight saving change
 * never skips or repeats a date. Pure.
 *
 * @param string $date Y-m-d
 * @param int    $days negative for earlier
 * @return string Y-m-d
 */
function pg_visitor_period_shift($date, $days)
{
    $days = (int) $days;

    return date('Y-m-d', strtotime($date . ' 12:00:00 ' . (($days < 0) ? '-' : '+') . abs($days) . ' days'));
}

/**
 * The days from one date to another, both counted. Pure.
 *
 * @param string $from Y-m-d
 * @param string $to   Y-m-d
 * @return int
 */
function pg_visitor_period_day_count($from, $to)
{
    return (int) round((strtotime($to . ' 12:00:00') - strtotime($from . ' 12:00:00')) / 86400) + 1;
}

/**
 * The grain a period of so many days is charted in: hour for one day, day up
 * to 31, week up to 120, month beyond. Pure.
 *
 * @param int $days
 * @return string 'hour'|'day'|'week'|'month'
 */
function pg_visitor_period_grain($days)
{
    $days = (int) $days;

    if ($days <= 1) {
        return 'hour';
    }

    if ($days <= 31) {
        return 'day';
    }

    return ($days <= 120) ? 'week' : 'month';
}

/**
 * The period the overview shows, from the query string.
 *
 *   p        today, yesterday, 7d, 30d, 90d, month, last_month or custom;
 *            anything else is 7d
 *   from, to the custom range as Y-m-d; reversed dates are swapped, a day
 *            after today becomes today, a range that is no real date is 7d
 *   compare  1 unless '0'
 *
 * The answer: key, label, from, to (Y-m-d, whole days by the site's clock),
 * from_ts and to_ts (the window as timestamps, to_ts excluded; today ends
 * now), days, grain, compare, and previous_from, previous_to,
 * previous_from_ts, previous_to_ts: the same number of days just before
 * (last month against the month before it, this month against the same days
 * of last month). When the period ends today, the earlier one is cut at the
 * same time of its last day, so a morning is not set against a whole day.
 * Pure.
 *
 * @param array $request $_GET
 * @param int   $now     timestamp; 0 for the current time
 * @return array
 */
function pg_visitor_period($request, $now = 0)
{
    $request = is_array($request) ? $request : array();
    $now = $now ? (int) $now : time();
    $today = date('Y-m-d', $now);

    $key = (isset($request['p']) && is_string($request['p'])) ? $request['p'] : '';
    $presets = pg_visitor_period_presets();

    if (!isset($presets[$key])) {
        $key = '7d';
    }

    $previous_from = '';
    $previous_to = '';

    switch ($key) {

        case 'today':
            $from = $to = $today;
            break;

        case 'yesterday':
            $from = $to = pg_visitor_period_shift($today, -1);
            break;

        case 'month':
            $from = substr($today, 0, 8) . '01';
            $to = $today;
            $previous_from = pg_visitor_period_shift($from, -1);
            $previous_from = substr($previous_from, 0, 8) . '01';
            $previous_last = date('Y-m-t', strtotime($previous_from . ' 12:00:00'));
            $previous_to = pg_visitor_period_shift($previous_from, pg_visitor_period_day_count($from, $to) - 1);

            if ($previous_to > $previous_last) {
                $previous_to = $previous_last;
            }

            break;

        case 'last_month':
            $to = pg_visitor_period_shift(substr($today, 0, 8) . '01', -1);
            $from = substr($to, 0, 8) . '01';
            $previous_to = pg_visitor_period_shift($from, -1);
            $previous_from = substr($previous_to, 0, 8) . '01';
            break;

        case 'custom':
            $from = pg_visitor_period_date(isset($request['from']) ? $request['from'] : '');
            $to = pg_visitor_period_date(isset($request['to']) ? $request['to'] : '');

            if (($from === '') && ($to === '')) {
                return pg_visitor_period(array('compare' => isset($request['compare']) ? $request['compare'] : null), $now);
            }

            // One end given: a one-day range.
            if ($from === '') {
                $from = $to;
            } elseif ($to === '') {
                $to = $from;
            }

            if ($from > $to) {
                list($from, $to) = array($to, $from);
            }

            if ($to > $today) {
                $to = $today;
            }

            if ($from > $today) {
                $from = $today;
            }

            break;

        default:
            $length = (int) $key;
            $to = $today;
            $from = pg_visitor_period_shift($today, 1 - $length);

    }

    $days = pg_visitor_period_day_count($from, $to);

    if ($previous_from === '') {
        $previous_to = pg_visitor_period_shift($from, -1);
        $previous_from = pg_visitor_period_shift($from, -$days);
    }

    $from_ts = strtotime($from . ' 00:00:00');
    $to_ts = ($to === $today) ? $now : strtotime(pg_visitor_period_shift($to, 1) . ' 00:00:00');

    $previous_from_ts = strtotime($previous_from . ' 00:00:00');
    $previous_to_ts = strtotime(pg_visitor_period_shift($previous_to, 1) . ' 00:00:00');

    // The same time of the earlier period's last day, read on the wall clock
    // so a daylight saving change on either day keeps the hour.
    if ($to === $today) {
        $previous_to_ts = strtotime($previous_to . ' ' . date('H:i:s', $now));
    }

    return array(
        'key'              => $key,
        'label'            => $presets[$key],
        'from'             => $from,
        'to'               => $to,
        'from_ts'          => $from_ts,
        'to_ts'            => $to_ts,
        'days'             => $days,
        'grain'            => pg_visitor_period_grain($days),
        'compare'          => !(isset($request['compare']) && ((string) $request['compare'] === '0')),
        'previous_from'    => $previous_from,
        'previous_to'      => $previous_to,
        'previous_from_ts' => $previous_from_ts,
        'previous_to_ts'   => $previous_to_ts,
    );
}

/**
 * The first day of the chart bucket a date falls in: the date itself for
 * day (and hour, whose buckets are the hours of that day), the Monday of its
 * week, the first of its month. Pure.
 *
 * @param string $date  Y-m-d
 * @param string $grain 'hour'|'day'|'week'|'month'
 * @return string Y-m-d
 */
function pg_visitor_period_bucket($date, $grain)
{
    if ($grain === 'week') {
        return pg_visitor_period_shift($date, 1 - (int) date('N', strtotime($date . ' 12:00:00')));
    }

    if ($grain === 'month') {
        return substr($date, 0, 8) . '01';
    }

    return $date;
}

/**
 * The chart buckets of a period in order: '00'..'23' for an hour grain, else
 * the first day of each bucket (pg_visitor_period_bucket()), the first one
 * possibly starting before the period. Pure.
 *
 * @param array $period pg_visitor_period()
 * @return array
 */
function pg_visitor_period_buckets(array $period)
{
    if ($period['grain'] === 'hour') {
        $hours = array();

        for ($hour = 0; $hour < 24; $hour++) {
            $hours[] = sprintf('%02d', $hour);
        }

        return $hours;
    }

    $buckets = array();

    for ($date = $period['from']; $date <= $period['to']; $date = pg_visitor_period_shift($date, 1)) {
        $buckets[pg_visitor_period_bucket($date, $period['grain'])] = true;
    }

    return array_keys($buckets);
}

/**
 * The query string that keeps a period: p, the range for a custom one, and
 * compare=0 when comparing is off. Pure.
 *
 * @param array $period pg_visitor_period()
 * @param array $change values to set instead (p, from, to, compare)
 * @return string 'p=…' without a leading '?'
 */
function pg_visitor_period_query(array $period, array $change = array())
{
    $values = array(
        'p'       => $period['key'],
        'from'    => $period['from'],
        'to'      => $period['to'],
        'compare' => $period['compare'] ? '1' : '0',
    );

    foreach ($change as $name => $value) {
        $values[$name] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    $query = array('p' => $values['p']);

    if ($values['p'] === 'custom') {
        $query['from'] = $values['from'];
        $query['to'] = $values['to'];
    }

    if ($values['compare'] === '0') {
        $query['compare'] = '0';
    }

    return http_build_query($query, '', '&');
}

/**
 * The overview's sections in the screen's order, name => heading. A section
 * is the file includes/visitors/<name>.php; its name is also its anchor
 * (visitors.php#sources).
 *
 * @return array
 */
function pg_visitors_sections()
{
    return array(
        'alerts'      => lang('Alerts'),
        'traffic'     => lang('Traffic'),
        'conversions' => lang('Conversions'),
        'sources'     => lang('Sources'),
        'content'     => lang('Content'),
        'site_search' => lang('Site Search'),
        'google'      => lang('Google Search'),
        'pages'       => lang('Pages and Google'),
        'speed'       => lang('Page Speed'),
        'ai'          => lang('AI Visibility'),
        'places'      => lang('Places and Devices'),
    );
}

/**
 * The anchor of a section on the screen, also the menu's and the alerts'
 * #fragment. The section name, except where the panel shell already uses
 * the name as an id: <main> is #content, the skip link's target, so the
 * content section is #content_section. Pure.
 *
 * @param string $name a key of pg_visitors_sections()
 * @return string
 */
function pg_visitors_anchor($name)
{
    $name = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $name));

    return ($name === 'content') ? 'content_section' : $name;
}

/**
 * Whether a section's file is there yet.
 *
 * @param string $name a key of pg_visitors_sections()
 * @return bool
 */
function pg_visitors_section_exists($name)
{
    $sections = pg_visitors_sections();

    return isset($sections[$name]) && is_file(PG_FUNCTIONS_DIR . '/includes/visitors/' . $name . '.php');
}
