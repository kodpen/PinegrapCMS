<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Sales map card: the last 30 days' revenue, the store's home country
 * shaded by region in four steps (when a map of it ships), and its best
 * regions as bars; on a store without a home map, its countries. Every region
 * of the map names itself under the pointer, and one that sold opens its
 * orders, as on dashboard widget 1. Everything comes from
 * pg_sales_map_collect(), the one query that widget draws from, with its
 * definition of a sale (complete or exported, billing address) and its
 * quantile steps.
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
 * The card's data from the collected sales.
 *
 * 'places' holds every region of the map in the order the keyboard steps
 * through them: those that sold, by sales, then the rest in the map's own
 * order. Each has the text the figure box shows and, for a region that sold,
 * the orders it counted when $can_open (the rows link the same).
 *
 * @param array  $collect  pg_sales_map_collect()
 * @param string $maps_url the address of assets/maps/, with a trailing slash
 * @param int    $limit    rows drawn
 * @param bool   $can_open whether the person may open the orders screen
 * @return array
 */
function pg_dashboard_v2_salesmap_data($collect, $maps_url, $limit = 6, $can_open = false)
{
    $period = $collect['periods']['d30'] ?? null;

    if (($collect['status'] ?? '') !== 'ok' || !is_array($period) || ((int) $period['count'] === 0)) {
        return array('empty' => lang('There are no sales in this period.'));
    }

    $home = (string) $collect['home'];
    $on_map = ($home !== '') && ((string) $collect['home_map'] !== '');

    if ($on_map) {
        $entries = array();

        foreach ($period['regions'] as $key => $region) {
            if ($region['country'] === $home) {
                $entries[$key] = $region;
            }
        }
    } else {
        $entries = $period['countries'];
    }

    $steps = pg_sales_map_steps($entries);
    $shades = array();
    $rows = array();
    $places = array();

    foreach ($entries as $key => $entry) {
        $code = (string) $entry['code'];
        $url = '';

        // A region by its country and the spelling that sold most, a
        // country by its name: the orders screen filters on the text.
        if ($can_open && ($on_map || ($code !== ''))) {
            $url = pg_sales_map_orders_url(
                $on_map ? (string) ($entry['country_name'] ?? '') : (string) $entry['name'],
                $on_map ? (string) ($entry['state'] ?? '') : '',
                (int) ($period['from'] ?? 0),
                (int) ($collect['first'] ?? 0));
        }

        if ($on_map && ($code !== '') && isset($steps[$key])) {
            $shades[$code] = (int) $steps[$key];
            $places[$code] = array('id' => $code, 'text' => pg_dashboard_v2_salesmap_tip($entry), 'url' => $url);
        }

        if (count($rows) < $limit) {
            // A country row in the panel language unless the store renamed
            // it (pg_geo_country_label()); the link keeps the stored name.
            $row = array(
                'text'  => $on_map ? (string) $entry['name'] : pg_geo_country_label((string) $entry['name'], $code),
                'value' => (float) $entry['total'],
                'note'  => pg_dashboard_v2_money($entry['total']),
            );

            if ($url !== '') {
                $row['url'] = $url;
            }

            $rows[] = $row;
        }
    }

    // The regions that sold nothing still say which place they are.
    if ($on_map) {
        $map = pg_sales_map_region_maps()[$home] ?? array();
        $names = isset($map['names']) ? (array) call_user_func($map['names']) : array();

        foreach ($names as $code => $name) {
            if (!isset($places[(string) $code])) {
                $places[(string) $code] = array('id' => (string) $code, 'text' => $name . "\n" . lang('No sales'), 'url' => '');
            }
        }
    }

    $count = (int) $period['count'];

    return array(
        'figure'     => pg_dashboard_v2_money($period['total']),
        'sub'        => lang('Last 30 days') . ' · ' . lang(array('string' => '{var:1} order{suffix:1}', 'vars' => pg_format_number($count, 0), 'suffix' => ($count == 1) ? '' : 's')),
        'map_url'    => $on_map ? $maps_url . (string) $collect['home_map'] : '',
        'map_label'  => $on_map ? lang(array('string' => 'Sales by region in {var:1}, last 30 days', 'vars' => pg_geo_country_name($home))) : '',
        'shades'     => (object) $shades,
        'places'     => array_values($places),
        'legend'     => array(lang('Few'), lang('Many')),
        'rows_title' => $on_map ? lang('Regions') : lang('Countries'),
        'rows'       => $rows,
        'url'        => 'view_order_report.php',
    );
}

/**
 * What the figure box says over a region that sold: its name, revenue and
 * orders, and its best cities on a line of their own.
 *
 * @param array $entry a region of pg_sales_map_collect()
 * @return string
 */
function pg_dashboard_v2_salesmap_tip($entry)
{
    $count = (int) $entry['count'];
    $lines = array(
        (string) $entry['name'],
        pg_dashboard_v2_money($entry['total']) . ' · ' . lang(array('string' => '{var:1} order{suffix:1}', 'vars' => pg_format_number($count, 0), 'suffix' => ($count == 1) ? '' : 's')),
    );
    $cities = array();

    foreach ((array) ($entry['cities'] ?? array()) as $city) {
        if ((string) $city['name'] !== '') {
            $cities[] = (string) $city['name'];
        }
    }

    if ($cities) {
        $lines[] = implode(', ', $cities);
    }

    return implode("\n", $lines);
}

function pg_dashboard_v2_card_salesmap($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('shop_reports', $user)) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    return array('status' => 'success', 'data' => pg_dashboard_v2_salesmap_data(pg_sales_map_collect(), OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/maps/', 6, pg_dashboard_v2_gate('shop', $user)));
}
