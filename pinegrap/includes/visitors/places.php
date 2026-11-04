<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The places and devices section of the Visitors overview: the countries and
 * cities of the period's visits as top lists with their share of all visits,
 * the device split (desktop, mobile, tablet, unknown) as one bar, and first
 * against return visits as another. Every figure comes from
 * pg_visitor_breakdown(), so the daily summary answers the days it holds.
 *
 * A visit carries a country and a city only once it gave a billing address,
 * so the lists say how many visits they know a place for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

/**
 * The places and devices section.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML
 */
function pg_visitors_section_places(array $period, array $user)
{
    $title = lang('Places and Devices');

    $devices = pg_visitor_breakdown('device', $period['from'], $period['to'], 10);

    if ($devices === false) {
        return '';
    }

    $total = (int) $devices['totals']['visits'];

    if ($total <= 0) {
        return pg_visitors_section('places', $title, pg_visitors_empty());
    }

    $countries = pg_visitor_breakdown('country', $period['from'], $period['to'], 11);
    $cities = pg_visitor_breakdown('city', $period['from'], $period['to'], 11);
    $first = pg_visitor_breakdown('first_visit', $period['from'], $period['to'], 5);

    $country_rows = pg_visitors_places_top(($countries !== false) ? $countries['rows'] : array(), $total, 10, 'pg_visitors_places_country_label');
    $city_rows = pg_visitors_places_top(($cities !== false) ? $cities['rows'] : array(), $total, 10);

    $device_split = pg_visitors_places_split($devices['rows'], $total, array(
        'desktop' => lang('Desktop'),
        'mobile'  => lang('Mobile'),
        'tablet'  => lang('Tablet'),
    ));

    $visit_split = pg_visitors_places_split(($first !== false) ? $first['rows'] : array(), $total, array(
        '1' => lang('First Visit'),
        '0' => lang('Return Visit'),
    ));

    $known = pg_visitors_places_known(($countries !== false) ? $countries['rows'] : array());

    $body = '
        <div class="pg-visitors-places-lists">
            <div>
                <h3 class="pg-visitors-places-heading">' . h(lang('Countries')) . '</h3>
                ' . pg_visitors_bars($country_rows, array('label' => lang('Countries'))) . '
            </div>
            <div>
                <h3 class="pg-visitors-places-heading">' . h(lang('Cities')) . '</h3>
                ' . pg_visitors_bars($city_rows, array('label' => lang('Cities'))) . '
            </div>
        </div>
        <p class="pg-visitors-places-note">' . h(lang(array(
            'string' => 'A place is known for {var:1} of {var:2} visits: it is saved from the billing address a visit gives.',
            'vars'   => array(pg_format_number($known, 0), pg_format_number($total, 0)),
        ))) . '</p>
        <div class="pg-visitors-places-splits">
            <div>
                <h3 class="pg-visitors-places-heading">' . h(lang('Devices')) . '</h3>
                ' . pg_visitors_places_split_bar($device_split, lang('Devices')) . '
            </div>
            <div>
                <h3 class="pg-visitors-places-heading">' . h(lang('First and return visits')) . '</h3>
                ' . pg_visitors_places_split_bar($visit_split, lang('First and return visits')) . '
            </div>
        </div>';

    return pg_visitors_section('places', $title, $body);
}

/**
 * A breakdown's rows as a bar list: visits with a value only, the largest
 * $limit, each with its share of all the period's visits. Pure apart from
 * the label callback.
 *
 * @param array         $rows  pg_visitor_breakdown() rows
 * @param int           $total the period's visits
 * @param int           $limit
 * @param callable|null $label value => text shown; null shows the value
 * @return array rows for pg_visitors_bars()
 */
function pg_visitors_places_top(array $rows, $total, $limit, $label = null)
{
    $out = array();

    foreach ($rows as $row) {
        $value = trim((string) $row['value']);
        $visits = (int) $row['visits'];

        if (($value === '') || ($visits <= 0)) {
            continue;
        }

        $out[] = array(
            'label' => ($label !== null) ? (string) call_user_func($label, $value) : $value,
            'value' => $visits,
            'text'  => pg_format_number($visits, 0) . ' · ' . pg_visitors_places_share_text($visits, $total),
        );

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * Visits of a breakdown that carry a value. Pure.
 *
 * @param array $rows pg_visitor_breakdown() rows
 * @return int
 */
function pg_visitors_places_known(array $rows)
{
    $known = 0;

    foreach ($rows as $row) {
        if (trim((string) $row['value']) !== '') {
            $known += (int) $row['visits'];
        }
    }

    return $known;
}

/**
 * A breakdown cut into named parts and an unknown rest, each with its share
 * of all the period's visits. Values are matched without regard to case; a
 * value not named, an empty value and visits no row holds (the summary's
 * rest row) are the unknown part, listed only when it has visits. Pure.
 *
 * @param array $rows   pg_visitor_breakdown() rows
 * @param int   $total  the period's visits
 * @param array $labels value => text, in the order shown
 * @return array list of array('key', 'label', 'visits', 'share' => percent)
 */
function pg_visitors_places_split(array $rows, $total, array $labels)
{
    $counts = array();

    foreach (array_keys($labels) as $key) {
        $counts[(string) $key] = 0;
    }

    $named = 0;

    foreach ($rows as $row) {
        $value = strtolower(trim((string) $row['value']));

        if (isset($counts[$value])) {
            $counts[$value] += (int) $row['visits'];
            $named += (int) $row['visits'];
        }
    }

    $total = max((int) $total, $named);
    $parts = array();

    foreach ($labels as $key => $label) {
        $parts[] = array(
            'key'    => (string) $key,
            'label'  => (string) $label,
            'visits' => $counts[(string) $key],
            'share'  => pg_visitors_places_share($counts[(string) $key], $total),
        );
    }

    if ($total > $named) {
        $parts[] = array(
            'key'    => 'unknown',
            'label'  => lang('Unknown'),
            'visits' => $total - $named,
            'share'  => pg_visitors_places_share($total - $named, $total),
        );
    }

    return $parts;
}

/**
 * A part's share of a whole in percent, one decimal. Pure.
 *
 * @param int $part
 * @param int $total
 * @return float
 */
function pg_visitors_places_share($part, $total)
{
    return ((int) $total > 0) ? round((int) $part / (int) $total * 100, 1) : 0.0;
}

/**
 * A share as text: a whole number from 10 % up, one decimal under it, and
 * "under 0.1 %" for a part too small to show.
 *
 * @param int $part
 * @param int $total
 * @return string
 */
function pg_visitors_places_share_text($part, $total)
{
    $share = pg_visitors_places_share($part, $total);

    if (((int) $part > 0) && ($share < 0.1)) {
        return '< ' . pg_visitors_percent(0.1, 1);
    }

    return pg_visitors_percent($share, ($share >= 10) ? 0 : 1);
}

/**
 * Parts drawn as one bar of coloured segments and a legend with the visits
 * and share of each.
 *
 * @param array  $parts pg_visitors_places_split()
 * @param string $label the bar's accessible name
 * @return string HTML
 */
function pg_visitors_places_split_bar(array $parts, $label)
{
    $total = 0;

    foreach ($parts as $part) {
        $total += (int) $part['visits'];
    }

    if ($total <= 0) {
        return pg_visitors_empty();
    }

    $segments = '';
    $legend = '';

    foreach (array_values($parts) as $index => $part) {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $part['key']));
        $class = 'pg-visitors-split-' . ($index % 6) . (($key === 'unknown') ? ' is-unknown' : '');

        if ((int) $part['visits'] > 0) {
            $segments .= '<span class="' . $class . '" style="width:' . pg_visitors_places_share($part['visits'], $total) . '%"></span>';
        }

        $legend .= '
            <li>
                <span class="pg-visitors-split-swatch ' . $class . '" aria-hidden="true"></span>
                <span class="pg-visitors-split-label">' . h($part['label']) . '</span>
                <span class="pg-visitors-split-value">' . h(pg_format_number($part['visits'], 0) . ' · ' . pg_visitors_places_share_text($part['visits'], $total)) . '</span>
            </li>';
    }

    return '
        <div class="pg-visitors-split" aria-hidden="true">' . $segments . '</div>
        <ul class="pg-visitors-split-legend list-unstyled mb-0" aria-label="' . h($label) . '">' . $legend . '</ul>';
}

/**
 * A stored country as shown: a two-letter code by its name in the panel
 * language, anything else (an older name) as stored.
 *
 * @param string $value
 * @return string
 */
function pg_visitors_places_country_label($value)
{
    $value = trim((string) $value);

    if (preg_match('/^[A-Za-z][A-Za-z0-9]$/', $value)) {
        return pg_geo_country_name($value);
    }

    return $value;
}
