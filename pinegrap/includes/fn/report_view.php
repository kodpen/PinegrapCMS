<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The report screens' shared view (the order, visitor and shipping
 * reports): from a report's result (its first summarize-by level, the
 * groups of that level, the grand total) the summary figures and the
 * chart the screen draws with the dashboard's drawing
 * (assets/js/pg_dash.js, assets/js/pg_report_view.js). The table under
 * them stays the screen's own. Every function here is pure: the figures
 * are the ones the table prints, read from the same buckets.
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
 * The visitor report's summary figures, each the text of its cell in the
 * table's Grand Total row: visitors, page views, the share of visitors who
 * submitted a form and, with e-commerce, the share who completed an order,
 * the order total and the average order amount. Pure.
 *
 * @param array  $grand     count, page_views, custom_form_submitted,
 *                          order_completed, order_total (minor units)
 * @param bool   $ecommerce whether the table shows its order columns
 * @param string $symbol    the base currency's stored symbol
 * @return array of label, text
 */
function pg_report_view_visitor_figures($grand, $ecommerce, $symbol)
{
    $count = (int) ($grand['count'] ?? 0);
    $percent = function ($part) use ($count) {
        return lang(array('string' => '{var:1}%', 'vars' => pg_format_number(($count > 0) ? $part / $count * 100 : 0, 2)));
    };

    $figures = array(
        array('label' => lang('Visitors'), 'text' => pg_format_number($count, 0)),
        array('label' => lang('Page Views'), 'text' => pg_format_number((int) ($grand['page_views'] ?? 0), 0)),
        array('label' => lang('Custom Form Submitted') . ' %', 'text' => $percent((int) ($grand['custom_form_submitted'] ?? 0))),
    );

    if ($ecommerce) {
        $total = (float) ($grand['order_total'] ?? 0);

        $figures[] = array('label' => lang('Order Completed') . ' %', 'text' => $percent((int) ($grand['order_completed'] ?? 0)));
        $figures[] = array('label' => lang('Order Total'), 'text' => pg_report_view_money($total, $symbol));
        $figures[] = array('label' => lang('Average Order Amount'), 'text' => pg_money_text((($count > 0) ? $total / $count : 0) / 100, $symbol));
    }

    return $figures;
}

/**
 * The shipping report's summary figures, from its Grand Total row: the
 * products listed, then the units before the period, in it (the sum of its
 * days), after it and in all. Units are those of the status chosen (to
 * ship, shipped or either). Pure.
 *
 * @param array $totals   the screen's totals: 'before', each date, 'after',
 *                        'total'
 * @param array $dates    the period's dates (Y-m-d), in order
 * @param int   $products the number of product rows
 * @return array of label, text
 */
function pg_report_view_shipping_figures($totals, $dates, $products)
{
    $period = 0;

    foreach ($dates as $date) {
        $period += (int) ($totals[$date] ?? 0);
    }

    return array(
        array('label' => lang('Products'), 'text' => pg_format_number((int) $products, 0)),
        array('label' => lang('Before'), 'text' => pg_format_number((int) ($totals['before'] ?? 0), 0)),
        array('label' => lang('In this period'), 'text' => pg_format_number($period, 0)),
        array('label' => lang('After'), 'text' => pg_format_number((int) ($totals['after'] ?? 0), 0)),
        array('label' => lang('Total'), 'text' => pg_format_number((int) ($totals['total'] ?? 0), 0)),
    );
}

/**
 * The shipping report's chart: a line of units over the period's ship
 * dates, one point a day, as the table's day columns count them. None for
 * a period of one day (a line of one point) or with no unit in it. Pure.
 *
 * @param array $totals the screen's totals, keyed by date among others
 * @param array $dates  the period's dates (Y-m-d), in order
 * @param array $names  each date's name as the table heads it, same keys
 *                      as $dates
 * @return array|null
 */
function pg_report_view_shipping_chart($totals, $dates, $names)
{
    if (count($dates) < 2) {
        return null;
    }

    $groups = array();
    $sum = 0;

    foreach (array_values($dates) as $i => $date) {
        $groups[$i] = array('count' => (int) ($totals[$date] ?? 0));
        $sum += $groups[$i]['count'];
    }

    if ($sum <= 0) {
        return null;
    }

    return pg_report_view_chart_by('line', $groups, array_values($names), lang('Units by ship date'), array(
        'raw' => function ($group) {
            return (int) ($group['count'] ?? 0);
        },
        'value' => function ($raw) {
            return (int) $raw;
        },
        'note' => function ($raw) {
            return pg_format_number($raw, 0);
        },
        'tip' => function ($name, $group) {
            $count = (int) ($group['count'] ?? 0);

            return $name . ': ' . lang(array('string' => '{var:1} unit{suffix:1}', 'vars' => pg_format_number($count, 0), 'suffix' => ($count == 1) ? '' : 's'));
        },
    ));
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
    return pg_report_view_chart_by($kind, $groups, $names, lang(array('string' => 'Total by {var:1}', 'vars' => $label)), array(
        'raw' => function ($group) {
            return (float) ($group['total'] ?? 0);
        },
        'value' => function ($raw) {
            return round($raw / 100, 2);
        },
        'note' => function ($raw) use ($symbol) {
            return pg_report_view_money($raw, $symbol);
        },
        'tip' => function ($name, $group) use ($symbol) {
            return pg_report_view_tip($name, $group, $symbol);
        },
    ), $codes);
}

/**
 * The visitor report's chart: the same kinds, measured in visitors (the
 * group's count), with no money. Pure.
 *
 * @param string $kind   pg_report_view_chart_kind()
 * @param array  $groups the first level's groups, each with its 'count'
 * @param array  $names  their names as the table prints them, same keys
 * @param string $label  the level's label ("Country")
 * @param array  $codes  map: each group's country code, same keys
 * @return array|null null when there is nothing to draw
 */
function pg_report_view_visitor_chart($kind, $groups, $names, $label, $codes = array())
{
    return pg_report_view_chart_by($kind, $groups, $names, lang(array('string' => 'Visitors by {var:1}', 'vars' => $label)), array(
        'raw' => function ($group) {
            return (int) ($group['count'] ?? 0);
        },
        'value' => function ($raw) {
            return (int) $raw;
        },
        'note' => function ($raw) {
            return pg_format_number($raw, 0);
        },
        'tip' => function ($name, $group) {
            $count = (int) ($group['count'] ?? 0);

            return $name . ': ' . lang(array('string' => '{var:1} visitor{suffix:1}', 'vars' => pg_format_number($count, 0), 'suffix' => ($count == 1) ? '' : 's'));
        },
    ), $codes);
}

/**
 * The chart's data by one measure. Pure. $measure holds four callables:
 * 'raw' (group => the number ranked and shaded), 'value' (raw => the number
 * drawn), 'note' (raw => its text in a row) and 'tip' (name, group => the
 * text over a point or a place). A map place sums its groups' 'total' and
 * 'count', so both measures read it as a group.
 */
function pg_report_view_chart_by($kind, $groups, $names, $title, $measure, $codes = array())
{
    if (($kind === 'none') || empty($groups)) {
        return null;
    }

    $chart = array('kind' => $kind, 'label' => $title);

    if ($kind === 'line') {
        $chart['values'] = array();
        $chart['tips'] = array();

        foreach ($groups as $key => $group) {
            $chart['values'][] = $measure['value']($measure['raw']($group));
            $chart['tips'][] = $measure['tip']((string) $names[$key], $group);
        }

        $chart['first'] = (string) reset($names);
        $chart['last'] = (string) end($names);

        return $chart;
    }

    $totals = array();

    foreach ($groups as $key => $group) {
        $totals[$key] = $measure['raw']($group);
    }

    arsort($totals);
    $chart['rows'] = array();

    foreach (array_slice(array_keys($totals), 0, PG_REPORT_VIEW_ROWS) as $key) {
        $chart['rows'][] = array(
            'text' => (string) $names[$key],
            'value' => $measure['value']($totals[$key]),
            'note' => $measure['note']($totals[$key]),
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
        $chart['shades'] = (object) pg_report_view_steps(array_map(function ($place) use ($measure) {
            return $measure['raw']($place);
        }, $places));
        $chart['places'] = array();

        foreach ($places as $code => $place) {
            $chart['places'][] = array('id' => (string) $code, 'text' => $measure['tip']($place['name'], $place));
        }
    }

    return $chart;
}
