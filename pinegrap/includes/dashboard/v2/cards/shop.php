<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Ecommerce summary card: revenue and orders for the period with the
 * change against the period it is compared with, the last seven days as
 * bars (today last), and the best sellers. Orders count as dashboard widget
 * 3 counts them (complete or exported), and the best sellers are its query
 * (pg_dashboard_best_sellers()).
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
 * The time windows one query sums: the period ("now"), the window its
 * change is measured against ("before": the period before, or the same
 * days a year ago when that is the compare setting), and the last seven
 * days one by one ("d0" six days ago to "d6" today). Each is [from, to),
 * midnight to midnight; days are stepped with strtotime() so a clock change
 * does not shift them.
 *
 * @param int $period   0 today, 1 last 7 days, 2 last 30 days
 * @param int $compare  0 none, 1 the period before, 2 last year
 * @param int $midnight today's midnight
 * @return array name => array(from, to)
 */
function pg_dashboard_v2_shop_windows($period, $compare, $midnight)
{
    $lengths = array(0 => 1, 1 => 7, 2 => 30);
    $days = $lengths[$period] ?? 30;
    $tomorrow = (int) strtotime('+1 day', $midnight);
    $from = (int) strtotime('-' . ($days - 1) . ' days', $midnight);

    $windows = array('now' => array($from, $tomorrow));

    if ($compare === 2) {
        $windows['before'] = array((int) strtotime('-1 year', $from), (int) strtotime('-1 year', $tomorrow));
    } else {
        $windows['before'] = array((int) strtotime('-' . $days . ' days', $from), $from);
    }

    for ($i = 0; $i < 7; $i++) {
        $start = (int) strtotime('-' . (6 - $i) . ' days', $midnight);
        $windows['d' . $i] = array($start, (int) strtotime('+1 day', $start));
    }

    return $windows;
}

/**
 * The SUM() columns of the windows: name_count and name_total for each.
 *
 * @param array $windows pg_dashboard_v2_shop_windows()
 * @return string SQL, after SELECT
 */
function pg_dashboard_v2_shop_columns($windows)
{
    $columns = array();

    foreach ($windows as $name => $window) {
        $in = "orders.order_date >= " . (int) $window[0] . " AND orders.order_date < " . (int) $window[1];
        $columns[] = "COALESCE(SUM(CASE WHEN " . $in . " THEN 1 ELSE 0 END), 0) AS " . $name . "_count";
        $columns[] = "COALESCE(SUM(CASE WHEN " . $in . " THEN orders.total ELSE 0 END), 0) AS " . $name . "_total";
    }

    return implode(",\n            ", $columns);
}

function pg_dashboard_v2_card_shop($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('shop_summary', $user)) {
        return array('status' => 'denied');
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_3.php');

    $midnight = (int) strtotime(date('Y-m-d'));
    $windows = pg_dashboard_v2_shop_windows($period, $compare, $midnight);
    $earliest = min(array_map(function ($window) {
        return $window[0];
    }, $windows));

    // One pass over the orders the windows cover; the WHERE compares the raw
    // column, so its index applies.
    $sums = (array) db_item(
        "SELECT
            " . pg_dashboard_v2_shop_columns($windows) . "
        FROM orders
        WHERE orders.status IN ('complete', 'exported')
            AND orders.order_date >= " . (int) $earliest
    );

    $money = function ($minor) {
        return pg_format_money(round((float) $minor) / 100, BASE_CURRENCY_SYMBOL);
    };

    $total = (float) ($sums['now_total'] ?? 0);
    $count = (int) ($sums['now_count'] ?? 0);
    $change = ($compare > 0) ? pg_dashboard_v2_change($total, (float) ($sums['before_total'] ?? 0)) : null;

    $period_names = array(0 => lang('Today'), 1 => lang('Last 7 days'), 2 => lang('Last 30 days'));

    $bars = array();
    $bar_labels = array();

    for ($i = 0; $i < 7; $i++) {
        $bars[] = round((float) ($sums['d' . $i . '_total'] ?? 0) / 100, 2);
        $bar_labels[] = get_absolute_time(array('timestamp' => $windows['d' . $i][0], 'type' => 'date', 'year' => false, 'format' => 'plain_text', 'timezone_type' => 'site'))
            . ': ' . $money($sums['d' . $i . '_total'] ?? 0);
    }

    $best = pg_dashboard_best_sellers($windows['now'][0], 5);
    $rows = array();

    foreach ($best as $row) {
        $rows[] = array(
            'text'  => (string) $row['name'],
            'value' => (float) $row['revenue'],
            'note'  => pg_format_number($row['qty'], 0) . ' · ' . $money($row['revenue']),
        );
    }

    return array('status' => 'success', 'data' => array(
        'figure'     => $money($total),
        'sub'        => $period_names[$period] . ' · ' . lang(array('string' => '{var:1} order{suffix:1}', 'vars' => pg_format_number($count, 0), 'suffix' => ($count == 1) ? '' : 's')),
        'change'     => ($change === null) ? '' : $change['text'],
        'tone'       => ($change === null) ? '' : $change['tone'],
        'bars'       => $bars,
        'bars_label' => lang('Revenue per day, last 7 days, today last') . ' — ' . implode(', ', $bar_labels),
        'rows_title' => lang('Best Sellers'),
        'rows'       => $rows,
        'url'        => 'view_orders.php',
    ));
}
