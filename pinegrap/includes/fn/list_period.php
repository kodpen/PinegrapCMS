<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The date range of the panel's list screens (orders, contacts, submitted
 * forms, the reports): the month and the week the period navigator's arrows
 * move to, the day a week starts on and the range written as a heading.
 *
 * A range is the six values the screens keep in their session and read from
 * the address: start_month, start_day, start_year, stop_month, stop_day and
 * stop_year, each a zero-padded string ('09', '05', '2026').
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
 * The whole calendar month a timestamp falls in, as a range.
 *
 * @param int $timestamp any moment of the month
 * @return array start_month, start_day, start_year, stop_month, stop_day, stop_year
 */
function pg_list_period_month($timestamp)
{
    return array(
        'start_month' => date('m', $timestamp),
        'start_day'   => '01',
        'start_year'  => date('Y', $timestamp),
        'stop_month'  => date('m', $timestamp),
        'stop_day'    => date('t', $timestamp),
        'stop_year'   => date('Y', $timestamp),
    );
}

/**
 * The months the ‹ Month › arrows move to: the whole month before and the
 * whole month after the LAST month on screen. Stepping from the first month
 * skipped a month on any range that crosses one (the default "26 September -
 * 27 October" went back to August) and went to the year 2020 on the contacts'
 * all-time range, so a multi-month range steps from its last month too.
 *
 * @param array $range the range on screen; a missing or impossible stop
 *                     month counts as this month
 * @return array array('previous' => range, 'next' => range)
 */
function pg_list_period_month_steps($range)
{
    $month = (int) ($range['stop_month'] ?? 0);
    $year = (int) ($range['stop_year'] ?? 0);

    if (($month < 1) || ($month > 12)) {
        $month = (int) date('n');
    }

    if ($year < 1) {
        $year = (int) date('Y');
    }

    // mktime() rolls month 0 and month 13 into the neighbouring years.
    return array(
        'previous' => pg_list_period_month(mktime(0, 0, 0, $month - 1, 1, $year)),
        'next'     => pg_list_period_month(mktime(0, 0, 0, $month + 1, 1, $year)),
    );
}

/**
 * The day a week starts on in the panel language, as date('w') counts it:
 * 1 (Monday) in Turkish, 0 (Sunday) otherwise - the same choice the date
 * pickers make (get_date_picker_format()).
 *
 * @return int 0 or 1
 */
function pg_list_period_week_first_day()
{
    return (lang(array('info' => '')) === 'tr') ? 1 : 0;
}

/**
 * The seven-day week a timestamp falls in, moved by a number of weeks, as a
 * range. Day arithmetic through mktime() keeps a week across a daylight
 * saving change whole, where adding 86400-second days lands an hour off.
 *
 * @param int $timestamp any moment of the week
 * @param int $offset    weeks to move: -1 the week before, 1 the week after
 * @param int $first_day the day the week starts on, 0 (Sunday) to 6
 * @return array start_month, start_day, start_year, stop_month, stop_day, stop_year
 */
function pg_list_period_week($timestamp, $offset, $first_day)
{
    $month = (int) date('n', $timestamp);
    $year = (int) date('Y', $timestamp);
    $day = (int) date('j', $timestamp) - (((int) date('w', $timestamp) - $first_day + 7) % 7) + (7 * $offset);

    $start = mktime(0, 0, 0, $month, $day, $year);
    $stop = mktime(0, 0, 0, $month, $day + 6, $year);

    return array(
        'start_month' => date('m', $start),
        'start_day'   => date('d', $start),
        'start_year'  => date('Y', $start),
        'stop_month'  => date('m', $stop),
        'stop_day'    => date('d', $stop),
        'stop_year'   => date('Y', $stop),
    );
}

/**
 * The weeks the ‹ Week › arrows and the "This week" item move to: the week
 * before and the week after the one the range starts in, and this week.
 *
 * @param array    $range     the range on screen; a missing start counts as today
 * @param int|null $first_day the day the week starts on; the panel
 *                            language's (pg_list_period_week_first_day()) when null
 * @param int|null $now       the current time; time() when null
 * @return array array('previous' => range, 'current' => range, 'next' => range)
 */
function pg_list_period_week_steps($range, $first_day = null, $now = null)
{
    if ($first_day === null) {
        $first_day = pg_list_period_week_first_day();
    }

    if ($now === null) {
        $now = time();
    }

    $month = (int) ($range['start_month'] ?? 0);
    $day = (int) ($range['start_day'] ?? 0);
    $year = (int) ($range['start_year'] ?? 0);

    $start = (checkdate($month, $day, $year)) ? mktime(0, 0, 0, $month, $day, $year) : $now;

    return array(
        'previous' => pg_list_period_week($start, -1, $first_day),
        'current'  => pg_list_period_week($now, 0, $first_day),
        'next'     => pg_list_period_week($start, 1, $first_day),
    );
}

/**
 * One date of a range in the site's date order with the month's name in
 * the panel language: '26 September 2026' or 'September 26, 2026'.
 *
 * @param array  $range       the range
 * @param string $which       'start' or 'stop'
 * @param string $date_format 'day_month' or 'month_day'
 * @return string
 */
function pg_list_period_date($range, $which, $date_format)
{
    $month = get_month_name_from_number(sprintf('%02d', (int) ($range[$which . '_month'] ?? 0)));
    $day = (int) ($range[$which . '_day'] ?? 0);
    $year = (int) ($range[$which . '_year'] ?? 0);

    if ($date_format === 'month_day') {
        return $month . ' ' . $day . ', ' . $year;
    }

    return $day . ' ' . $month . ' ' . $year;
}

/**
 * The range as the heading beside the period navigator, both dates in the
 * site's date order ('26 September 2026 – 27 October 2026'); a one-day
 * range is written once. Not escaped.
 *
 * @param array       $range       the range on screen
 * @param string|null $date_format 'day_month' or 'month_day'; the site's
 *                                 DATE_FORMAT when null
 * @return string
 */
function pg_list_period_heading($range, $date_format = null)
{
    if ($date_format === null) {
        $date_format = defined('DATE_FORMAT') ? DATE_FORMAT : 'month_day';
    }

    $start = pg_list_period_date($range, 'start', $date_format);
    $stop = pg_list_period_date($range, 'stop', $date_format);

    if ($start === $stop) {
        return $start;
    }

    return $start . ' – ' . $stop;
}
