<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The conversions section of the Visitors overview: forms submitted and,
 * with commerce on, orders created, checked out and completed, the revenue
 * and the conversion rate (completed orders over visits), each against the
 * earlier period; the order funnel as bars; the figures by the period's
 * grain as a chart. Every figure comes from pg_visitor_totals(), so a day
 * the daily summary holds is read from it and the rest from the raw rows.
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
 * The conversions section.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML
 */
function pg_visitors_section_conversions(array $period, array $user)
{
    $commerce = defined('ECOMMERCE') && (ECOMMERCE == true);

    $now = pg_visitor_totals((int) $period['from_ts'], (int) $period['to_ts'] - 1);
    $before = $period['compare']
        ? pg_visitor_totals((int) $period['previous_from_ts'], (int) $period['previous_to_ts'] - 1)
        : null;

    $title = lang('Conversions');

    if (!pg_visitors_conversions_has_figures($now, $commerce)) {
        return pg_visitors_section('conversions', $title, pg_visitors_empty());
    }

    $tiles = '';

    foreach (pg_visitors_conversions_tiles($now, $before, $commerce) as $tile) {
        $tiles .= pg_visitors_tile(
            $tile['label'],
            pg_visitors_conversions_format($tile['value'], $tile['format']),
            ($before !== null) ? pg_visitors_change($tile['value'], $tile['before']) : '',
            $tile['note']
        );
    }

    $body = '<div class="pg-visitors-tiles">' . $tiles . '</div>';

    $chart = pg_visitors_conversions_chart($period, $commerce);
    $funnel = $commerce ? pg_visitors_conversions_funnel($now) : array();

    if (($chart !== '') || $funnel) {
        $body .= '<div class="pg-visitors-conversions-detail' . ($funnel ? '' : ' is-single') . '">';

        if ($chart !== '') {
            $body .= '<div class="pg-visitors-conversions-chart">' . $chart . '</div>';
        }

        if ($funnel) {
            $body .= '
                <div class="pg-visitors-conversions-funnel">
                    <h3 class="pg-visitors-conversions-subtitle">' . h(lang('Order funnel')) . '</h3>
                    ' . pg_visitors_bars($funnel, array('label' => lang('Order funnel'))) . '
                </div>';
        }

        $body .= '</div>';
    }

    return pg_visitors_section('conversions', $title, $body);
}

/**
 * Whether a window has anything the section shows: a form, or with commerce
 * on an order. Visits alone are the traffic section's. Pure.
 *
 * @param array $totals   pg_visitor_totals()
 * @param bool  $commerce
 * @return bool
 */
function pg_visitors_conversions_has_figures(array $totals, $commerce)
{
    $figures = array('forms_submitted');

    if ($commerce) {
        $figures = array_merge($figures, array('orders_created', 'orders_checked_out', 'orders_completed', 'order_total'));
    }

    foreach ($figures as $figure) {
        if (!empty($totals[$figure])) {
            return true;
        }
    }

    return false;
}

/**
 * Completed orders over visits, in percent; null without a visit. Pure.
 *
 * @param array|null $totals pg_visitor_totals()
 * @return float|null
 */
function pg_visitors_conversions_rate($totals)
{
    if (!is_array($totals) || empty($totals['visits'])) {
        return null;
    }

    $completed = isset($totals['orders_completed']) ? (int) $totals['orders_completed'] : 0;

    return round($completed / (int) $totals['visits'] * 100, 2);
}

/**
 * The tiles of the section as data, in order: forms, then with commerce the
 * three order steps, the revenue (minor units) and the conversion rate.
 * Pure.
 *
 * @param array      $now      pg_visitor_totals() of the period
 * @param array|null $before   of the earlier period, null when not compared
 * @param bool       $commerce
 * @return array list of array('key', 'label', 'value', 'before', 'format'
 *               => count|money|percent, 'note')
 */
function pg_visitors_conversions_tiles(array $now, $before, $commerce)
{
    $figures = array('forms_submitted' => lang('Forms submitted'));

    if ($commerce) {
        $figures += array(
            'orders_created'     => lang('Orders created'),
            'orders_checked_out' => lang('Orders checked out'),
            'orders_completed'   => lang('Orders completed'),
            'order_total'        => lang('Revenue'),
        );
    }

    $tiles = array();

    foreach ($figures as $key => $label) {
        $tiles[] = array(
            'key'    => $key,
            'label'  => $label,
            'value'  => isset($now[$key]) ? (int) $now[$key] : 0,
            'before' => is_array($before) ? (isset($before[$key]) ? (int) $before[$key] : 0) : null,
            'format' => ($key === 'order_total') ? 'money' : 'count',
            'note'   => '',
        );
    }

    if ($commerce) {
        $tiles[] = array(
            'key'    => 'rate',
            'label'  => lang('Conversion rate'),
            'value'  => pg_visitors_conversions_rate($now),
            'before' => pg_visitors_conversions_rate($before),
            'format' => 'percent',
            'note'   => lang('Completed orders over visits'),
        );
    }

    return $tiles;
}

/**
 * A tile's value as text: a count with the site's separators, an amount in
 * minor units with the base currency, a percent with two decimals; a dash
 * for a percent without visits.
 *
 * @param int|float|null $value
 * @param string         $format count|money|percent
 * @return string plain text
 */
function pg_visitors_conversions_format($value, $format)
{
    if ($format === 'money') {
        return pg_money_text((int) $value / 100, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '');
    }

    if ($format === 'percent') {
        return ($value === null) ? '–' : pg_visitors_percent($value, 2);
    }

    return pg_format_number((int) $value, 0);
}

/**
 * The order funnel as bar rows: visits, orders created, checked out and
 * completed, each with its share of the visits. Pure.
 *
 * @param array $totals pg_visitor_totals()
 * @return array rows for pg_visitors_bars(); empty without a visit
 */
function pg_visitors_conversions_funnel(array $totals)
{
    $visits = isset($totals['visits']) ? (int) $totals['visits'] : 0;

    if ($visits <= 0) {
        return array();
    }

    $steps = array(
        'visits'             => lang('Visits'),
        'orders_created'     => lang('Orders created'),
        'orders_checked_out' => lang('Orders checked out'),
        'orders_completed'   => lang('Orders completed'),
    );

    $rows = array();

    foreach ($steps as $key => $label) {
        $value = isset($totals[$key]) ? (int) $totals[$key] : 0;
        $text = pg_format_number($value, 0);

        if ($key !== 'visits') {
            $text .= ' · ' . pg_visitors_percent(round($value / $visits * 100, 2), 2);
        }

        $rows[] = array('label' => $label, 'value' => $value, 'text' => $text);
    }

    return $rows;
}

/**
 * The chart buckets of a period as day windows: the first and last day of
 * each bucket of the period's grain, cut to the period. A one-day period
 * has none: its figures are the tiles. Pure.
 *
 * @param array $period pg_visitor_period()
 * @return array list of array('bucket' => Y-m-d, 'from' => Y-m-d, 'to' => Y-m-d)
 */
function pg_visitors_conversions_buckets(array $period)
{
    if (($period['grain'] === 'hour') || ($period['from'] === $period['to'])) {
        return array();
    }

    $buckets = array();

    for ($date = $period['from']; $date <= $period['to']; $date = pg_visitor_period_shift($date, 1)) {
        $bucket = pg_visitor_period_bucket($date, $period['grain']);

        if (!isset($buckets[$bucket])) {
            $buckets[$bucket] = array('bucket' => $bucket, 'from' => $date, 'to' => $date);
        }

        $buckets[$bucket]['to'] = $date;
    }

    return array_values($buckets);
}

/**
 * The figures by the period's grain: forms and, with commerce, completed
 * orders, on one axis; the revenue of each point is in its tooltip (a
 * second axis with a third line made the chart hard to read). The summary
 * run is read once and every bucket split against it, so a bucket costs at
 * most one summary and one raw query.
 *
 * @param array $period   pg_visitor_period()
 * @param bool  $commerce
 * @return string HTML; '' for a one-day period
 */
function pg_visitors_conversions_chart(array $period, $commerce)
{
    $buckets = pg_visitors_conversions_buckets($period);

    if (!$buckets) {
        return '';
    }

    $run = pg_visitor_summary_range();
    $today = date('Y-m-d');
    $last = (int) $period['to_ts'] - 1;

    $labels = array();
    $forms = array();
    $completed = array();
    $notes = array();
    $symbol = defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '';

    foreach ($buckets as $bucket) {
        $bounds = pg_visitor_window_bounds($bucket['from'], $bucket['to']);

        if ($bounds === null) {
            continue;
        }

        $totals = pg_visitor_totals_of_parts(pg_visitor_day_split($bounds[0], min($bounds[1], $last), $run, $today));

        $labels[] = prepare_form_data_for_output($bucket['bucket'], 'date', false);
        $forms[] = $totals['forms_submitted'];
        $completed[] = $totals['orders_completed'];
        $notes[] = $commerce ? lang('Revenue') . ': ' . pg_money_text((int) $totals['order_total'] / 100, $symbol) : '';
    }

    $series = array(array('label' => lang('Forms submitted'), 'data' => $forms));

    if ($commerce) {
        $series[] = array('label' => lang('Orders completed'), 'data' => $completed);
    }

    return pg_visitors_chart('conversions_chart', array(
        'type'    => 'line',
        'labels'  => $labels,
        'series'  => $series,
        'notes'   => $notes,
        'caption' => lang('Forms and orders over the period'),
    ));
}
