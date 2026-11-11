<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The report screens' shared view: from a report's result (its first
 * summarize-by level, the groups of that level, the grand total) the
 * summary figures and the chart the screen draws with the dashboard's
 * drawing (assets/js/pg_dash.js, assets/js/pg_report_view.js). The table
 * under them stays the screen's own. Every function here is pure: the
 * figures are the ones the table prints, read from the same buckets.
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
 * The rows a bar chart or a map lists at most.
 */
if (!defined('PG_REPORT_VIEW_ROWS')) {
    define('PG_REPORT_VIEW_ROWS', 10);
}

/**
 * The chart a first summarize-by level gets. Pure.
 *
 * A line over time for year, month and day; the world map for the billing
 * or shipping country; no chart without a level or for a level whose
 * groups are one person's details (a name, an address, a card number), as
 * a chart of those says nothing; bars for every other named level.
 *
 * @param string $level the first summarize-by value ('' for none)
 * @return string 'line', 'map', 'bars' or 'none'
 */
function pg_report_view_chart_kind($level)
{
    $level = (string) $level;

    if ($level === '') {
        return 'none';
    }

    if (in_array($level, array('year', 'month', 'day'), true)) {
        return 'line';
    }

    if (in_array($level, array('billing_country', 'shipping_country', 'country'), true)) {
        return 'map';
    }

    $none = array(
        'ip_address', 'cardholder', 'card_number', 'po_number', 'reference_code', 'tracking_code',
        'billing_salutation', 'billing_first_name', 'billing_last_name', 'billing_company',
        'billing_address_1', 'billing_address_2', 'billing_zip_code', 'billing_phone_number',
        'billing_fax_number', 'billing_email_address',
    );

    return in_array($level, $none, true) ? 'none' : 'bars';
}

/**
 * The groups a line is drawn over: the finest of the date levels the report
 * starts with (year, then month, then day), in the query's order, each
 * named by its levels joined ("2026 October 11"). A report by year alone
 * draws its years; one by year, month and day draws its days, so a single
 * year is not a line of one point. Pure.
 *
 * @param array    $results the report's first level, each group holding
 *                          its next level in 'results'
 * @param array    $levels  the summarize-by values in order
 * @param callable $name    (raw name, level) => the name the table prints
 * @return array array(groups, names, the date level drawn), keys 0..n-1;
 *               the level is '' when the report starts with no date level
 */
function pg_report_view_time_groups($results, $levels, $name)
{
    $depth = 0;

    $levels = array_values((array) $levels);

    foreach ($levels as $level) {
        if (!in_array((string) $level, array('year', 'month', 'day'), true)) {
            break;
        }

        $depth++;
    }

    $groups = array();
    $names = array();
    $walk = function ($level_groups, $index, $prefix) use (&$walk, &$groups, &$names, $levels, $depth, $name) {
        foreach ((array) $level_groups as $group) {
            $text = trim($prefix . ' ' . call_user_func($name, $group['name'] ?? '', $levels[$index]));

            if (($index + 1 < $depth) && !empty($group['results'])) {
                $walk($group['results'], $index + 1, $text);
            } else {
                $groups[] = $group;
                $names[] = $text;
            }
        }
    };

    if ($depth > 0) {
        $walk($results, 0, '');
    }

    return array($groups, $names, ($depth > 0) ? (string) $levels[$depth - 1] : '');
}

/**
 * Money in minor units as plain text, the base currency's symbol decoded
 * for textContent. Pure.
 *
 * @param int|float $minor
 * @param string    $symbol the stored symbol (may be an HTML entity)
 * @return string
 */
function pg_report_view_money($minor, $symbol)
{
    return pg_money_text(round((float) $minor) / 100, (string) $symbol);
}

/**
 * The summary figures over the chart: the order count and every money
 * column the table's Grand Total row prints, in its order (the charge
 * columns only when the table shows them). Pure.
 *
 * @param array  $grand          the grand total bucket (pg_order_report_bucket())
 * @param array  $charge_columns pg_order_report_charge_columns($grand)
 * @param string $symbol         the base currency's stored symbol
 * @return array of label, text
 */
function pg_report_view_figures($grand, $charge_columns, $symbol)
{
    $labels = array(
        'subtotal' => lang('Subtotal'),
        'discount' => lang('Discount'),
        'tax' => lang('Tax'),
        'shipping' => lang('Shipping'),
        'gift_card_discount' => lang('Gift card'),
        'surcharge' => lang('Card surcharge'),
        'installment_charges' => lang('Installment Charge'),
        'total' => lang('Total'),
    );

    $figures = array(
        array('label' => lang('Orders'), 'text' => pg_format_number((int) ($grand['count'] ?? 0), 0)),
    );

    foreach (array_merge(array('subtotal', 'discount', 'tax', 'shipping'), (array) $charge_columns, array('total')) as $name) {
        $figures[] = array('label' => $labels[$name], 'text' => pg_report_view_money($grand[$name] ?? 0, $symbol));
    }

    return $figures;
}

/**
 * What the figure box says over one group: its name, its total and its
 * order count. Pure.
 *
 * @param string $name
 * @param array  $group a bucket
 * @param string $symbol
 * @return string
 */
function pg_report_view_tip($name, $group, $symbol)
{
    $count = (int) ($group['count'] ?? 0);

    return $name . ': ' . pg_report_view_money($group['total'] ?? 0, $symbol) . ' · '
        . lang(array('string' => '{var:1} order{suffix:1}', 'vars' => pg_format_number($count, 0), 'suffix' => ($count == 1) ? '' : 's'));
}

/**
 * Four shading steps by rank among the groups that sold: the top quarter
 * darkest. Pure.
 *
 * @param array $totals key => total
 * @return array key => 1-4 (none for a total of zero or less)
 */
function pg_report_view_steps($totals)
{
    $sold = array_filter($totals, function ($total) {
        return $total > 0;
    });

    arsort($sold);
    $count = count($sold);
    $steps = array();
    $rank = 0;

    foreach ($sold as $key => $total) {
        $steps[$key] = 4 - (int) floor($rank * 4 / $count);
        $rank++;
    }

    return $steps;
}

/**
 * The chart's data. Pure.
 *
 * line: 'values' (each group's total, in the query's order, which is the
 * time order), 'tips', 'first' and 'last' (the end labels).
 * bars: 'rows' (the largest totals, at most PG_REPORT_VIEW_ROWS).
 * map:  'map' (the file under assets/maps/), 'shades' (code => step),
 * 'places' (code, text) and 'rows'; a group no code is found for is in the
 * rows only.
 *
 * @param string $kind   pg_report_view_chart_kind()
 * @param array  $groups the first level's buckets, in the query's order
 * @param array  $names  their names as the table prints them, same keys
 * @param string $label  the level's label ("Billing Country")
 * @param string $symbol the base currency's stored symbol
 * @param array  $codes  map: each group's country code, same keys
 * @return array|null null when there is nothing to draw
 */
function pg_report_view_chart($kind, $groups, $names, $label, $symbol, $codes = array())
{
    if (($kind === 'none') || empty($groups)) {
        return null;
    }

    $chart = array('kind' => $kind, 'label' => lang(array('string' => 'Total by {var:1}', 'vars' => $label)));

    if ($kind === 'line') {
        $chart['values'] = array();
        $chart['tips'] = array();

        foreach ($groups as $key => $group) {
            $chart['values'][] = round((float) ($group['total'] ?? 0) / 100, 2);
            $chart['tips'][] = pg_report_view_tip((string) $names[$key], $group, $symbol);
        }

        $chart['first'] = (string) reset($names);
        $chart['last'] = (string) end($names);

        return $chart;
    }

    $totals = array();

    foreach ($groups as $key => $group) {
        $totals[$key] = (float) ($group['total'] ?? 0);
    }

    arsort($totals);
    $chart['rows'] = array();

    foreach (array_slice(array_keys($totals), 0, PG_REPORT_VIEW_ROWS) as $key) {
        $chart['rows'][] = array(
            'text' => (string) $names[$key],
            'value' => round($totals[$key] / 100, 2),
            'note' => pg_report_view_money($totals[$key], $symbol),
        );
    }

    if ($kind === 'map') {
        // Two spellings of one country (TR, Turkey) are one place on the map.
        $places = array();

        foreach ($groups as $key => $group) {
            $code = strtoupper((string) ($codes[$key] ?? ''));

            if ($code === '') {
                continue;
            }

            if (!isset($places[$code])) {
                $places[$code] = array('name' => (string) $names[$key], 'total' => 0, 'count' => 0);
            }

            $places[$code]['total'] += (float) ($group['total'] ?? 0);
            $places[$code]['count'] += (int) ($group['count'] ?? 0);
        }

        $chart['map'] = 'world-countries.svg';
        $chart['shades'] = (object) pg_report_view_steps(array_map(function ($place) {
            return $place['total'];
        }, $places));
        $chart['places'] = array();

        foreach ($places as $code => $place) {
            $chart['places'][] = array('id' => (string) $code, 'text' => pg_report_view_tip($place['name'], $place, $symbol));
        }
    }

    return $chart;
}
