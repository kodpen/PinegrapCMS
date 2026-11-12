<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Visitor map card: where the period's visits came from. The home
 * country (the one most visits came from) drawn by province with a bubble
 * per place sized by its visits, when a map of it ships and its visits carry
 * cities (Cloudflare's CF-IPCity, includes/geo.php); otherwise the world map
 * with the visitors' countries shaded by their share, the countries as bars
 * under it. The headline says how many visits came from abroad.
 *
 * Read through pg_visitor_breakdown() (includes/fn/visitor_figures.php): the
 * country and city dimensions of the daily summary for whole past days, the
 * raw rows for today, as the Visitors screen reads them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

// How deep each list is read: the summary keeps a day's top 100 values.
define('PG_DASHBOARD_V2_VISITMAP_DEPTH', 100);

// The world map the card draws when no city can be placed.
define('PG_DASHBOARD_V2_VISITMAP_WORLD', 'world-countries.svg');

/**
 * The world map's marks: each country with visits in one of four steps
 * (pg_sales_map_steps(), quantiles, so one busy country does not leave the
 * rest in the palest step) and its tip, "<country>: N visits". A country
 * the map has no outline for keeps its bar and gets no mark (the renderer
 * skips an id it cannot find). Pure apart from the country names.
 *
 * @param array    $by_country code => visits, most first
 * @param callable $count      visits => "N visits"
 * @return array shades (code => 1..4), places (id, text)
 */
function pg_dashboard_v2_visitmap_world($by_country, $count)
{
    $entries = array();

    foreach ((array) $by_country as $code => $n) {
        $entries[(string) $code] = array('total' => (int) $n);
    }

    $shades = array();
    $places = array();

    foreach (pg_sales_map_steps($entries) as $code => $step) {
        $code = (string) $code;
        $shades[$code] = (int) $step;
        $places[] = array('id' => $code, 'text' => pg_geo_country_name($code) . ': ' . $count($entries[$code]['total']));
    }

    return array('shades' => $shades, 'places' => $places);
}

/**
 * Visits per country, keyed by the code the maps know it by. A value no
 * country matches ('' included) is left out. Pure apart from the sales
 * map's country tables.
 *
 * @param array $rows breakdown rows: value, visits
 * @return array code => visits, most first
 */
function pg_dashboard_v2_visitmap_countries($rows)
{
    $countries = array();

    foreach ((array) $rows as $row) {
        $code = pg_sales_map_country_code((string) $row['value']);

        if ($code === '') {
            continue;
        }

        $countries[$code] = ($countries[$code] ?? 0) + (int) $row['visits'];
    }

    arsort($countries);

    return $countries;
}

/**
 * The card's data. Pure apart from the sales map's name tables and lang().
 *
 * @param array  $countries pg_visitor_breakdown('country', …)
 * @param array  $cities    pg_visitor_breakdown('city', …)
 * @param string $period    the period's name, "Last 30 Days"
 * @param string $maps_url  the address of assets/maps/, with a trailing slash
 * @param string $url       See all: the ready report by country for the period
 * @param int    $limit     bars drawn
 * @return array
 */
function pg_dashboard_v2_visitmap_data($countries, $cities, $period, $maps_url, $url, $limit = 6)
{
    $visits = (int) ($countries['totals']['visits'] ?? 0);

    if ($visits === 0) {
        return array('empty' => lang('No visits in this period yet.'));
    }

    $count = function ($n) {
        return lang(array('string' => '{var:1} visit{suffix:1}', 'vars' => pg_format_number($n, 0), 'suffix' => ($n == 1) ? '' : 's'));
    };

    $by_country = pg_dashboard_v2_visitmap_countries($countries['rows'] ?? array());
    $known = array_sum($by_country);
    $home = $known ? (string) key($by_country) : '';
    $home_visits = $known ? (int) reset($by_country) : 0;

    $sub = array($period, $count($visits));

    if ($known > 0) {
        $sub[] = lang(array('string' => 'abroad {var:1}%', 'vars' => pg_format_number(round(100 * ($known - $home_visits) / $known), 0)));
    }

    // Places of the home country that its map can show, by province.
    $maps = pg_sales_map_region_maps();
    $bubbles = array();
    $places = array();

    if (($home !== '') && isset($maps[$home])) {
        $regions = array();

        foreach ((array) ($cities['rows'] ?? array()) as $row) {
            $value = (string) $row['value'];
            $code = pg_sales_map_match_region($home, $value);

            if ($code === '') {
                continue;
            }

            $regions[$code] = ($regions[$code] ?? 0) + (int) $row['visits'];
            $places[] = array('text' => $value, 'visits' => (int) $row['visits']);
        }

        arsort($regions);
        $most = $regions ? max($regions) : 0;

        foreach ($regions as $code => $n) {
            $name = pg_sales_map_region_name($home, $code);
            $bubbles[] = array(
                'code'  => (string) $code,
                'share' => ($most > 0) ? round($n / $most, 3) : 0,
                'tip'   => $name . ': ' . $count($n),
            );
        }
    }

    $rows = array();

    if ($bubbles) {
        foreach (array_slice($places, 0, $limit) as $place) {
            $rows[] = array('text' => $place['text'], 'value' => $place['visits'], 'note' => pg_format_number($place['visits'], 0));
        }
    } else {
        // Countries in the panel language (pg_geo_country_name(): intl, else
        // the English name): the language shards name only a few of them,
        // so lang() left "Germany" beside "Türkiye".
        foreach (array_slice($by_country, 0, $limit, true) as $code => $n) {
            $rows[] = array('text' => pg_geo_country_name($code), 'value' => (int) $n, 'note' => pg_format_number($n, 0));
        }
    }

    $home_name = ($home !== '') ? pg_geo_country_name($home) : '';

    // No city to place but known countries: the world map, shaded.
    $world = (!$bubbles && $known) ? pg_dashboard_v2_visitmap_world($by_country, $count) : array('shades' => array(), 'places' => array());

    if ($bubbles) {
        $map_url = $maps_url . $maps[$home]['file'];
        $map_label = lang(array('string' => 'Visits by city in {var:1}', 'vars' => $home_name));
    } elseif ($world['shades']) {
        $map_url = $maps_url . PG_DASHBOARD_V2_VISITMAP_WORLD;
        $map_label = lang('Visits by country');
    } else {
        $map_url = '';
        $map_label = '';
    }

    return array(
        'figure'     => pg_format_number($visits, 0),
        'sub'        => implode(' · ', $sub),
        'map'        => $bubbles ? 'region' : ($world['shades'] ? 'world' : ''),
        'map_url'    => $map_url,
        'map_label'  => $map_label,
        'bubbles'    => $bubbles,
        'shades'     => (object) $world['shades'],
        'places'     => $world['places'],
        'legend'     => $world['shades'] ? array(lang('Few'), lang('Many')) : array(),
        'rows_title' => $bubbles ? lang('Cities') : lang('Countries'),
        'rows'       => $rows,
        'none'       => $known ? '' : lang('No visit in this period has a known location.'),
        'url'        => $url,
    );
}

function pg_dashboard_v2_card_visitmap($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('visitors', $user)) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    $days = array(0 => 1, 1 => 7, 2 => 30);
    $period = isset($days[$period]) ? (int) $period : 2;
    $now = time();

    // Days are stepped with strtotime(): across a clock change a day is not
    // 86,400 seconds.
    $from = date('Y-m-d', strtotime('-' . ($days[$period] - 1) . ' days', $now));
    $to = date('Y-m-d', $now);

    $countries = pg_visitor_breakdown('country', $from, $to, PG_DASHBOARD_V2_VISITMAP_DEPTH);
    $cities = pg_visitor_breakdown('city', $from, $to, PG_DASHBOARD_V2_VISITMAP_DEPTH);

    if (!is_array($countries) || !is_array($cities)) {
        return array('status' => 'error');
    }

    return array('status' => 'success', 'data' => pg_dashboard_v2_visitmap_data(
        $countries,
        $cities,
        pg_dashboard_v2_periods()[$period],
        OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/maps/',
        pg_ready_report_url('visitors_by_country', $days[$period])));
}
