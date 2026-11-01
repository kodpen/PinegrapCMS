<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Sales map card: the last 30 days' revenue, the store's home country
 * shaded by region in four steps (when a map of it ships), and its best
 * regions as bars; on a store without a home map, its countries. Everything
 * comes from pg_sales_map_collect(), the one query dashboard widget 1 draws
 * from, with its definition of a sale (complete or exported, billing
 * address) and its quantile steps.
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
 * @param array  $collect  pg_sales_map_collect()
 * @param string $maps_url the address of assets/maps/, with a trailing slash
 * @param int    $limit    rows drawn
 * @return array
 */
function pg_dashboard_v2_salesmap_data($collect, $maps_url, $limit = 6)
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

    foreach ($entries as $key => $entry) {
        if ($on_map && ((string) $entry['code'] !== '') && isset($steps[$key])) {
            $shades[(string) $entry['code']] = (int) $steps[$key];
        }

        if (count($rows) < $limit) {
            $rows[] = array(
                'text'  => (string) $entry['name'],
                'value' => (float) $entry['total'],
                'note'  => pg_dashboard_v2_money($entry['total']),
            );
        }
    }

    $count = (int) $period['count'];

    return array(
        'figure'     => pg_dashboard_v2_money($period['total']),
        'sub'        => lang('Last 30 days') . ' · ' . lang(array('string' => '{var:1} order{suffix:1}', 'vars' => pg_format_number($count, 0), 'suffix' => ($count == 1) ? '' : 's')),
        'map_url'    => $on_map ? $maps_url . (string) $collect['home_map'] : '',
        'map_label'  => $on_map ? lang(array('string' => 'Sales by region in {var:1}, last 30 days', 'vars' => (string) $collect['home_name'])) : '',
        'shades'     => (object) $shades,
        'legend'     => array(lang('Few'), lang('Many')),
        'rows_title' => $on_map ? lang('Regions') : lang('Countries'),
        'rows'       => $rows,
        'url'        => 'view_order_report.php',
    );
}

function pg_dashboard_v2_card_salesmap($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('shop_reports', $user)) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    return array('status' => 'success', 'data' => pg_dashboard_v2_salesmap_data(pg_sales_map_collect(), OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/maps/'));
}
