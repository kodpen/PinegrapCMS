<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Ready reports: reports defined in code that a report screen opens by an
 * address parameter (view_order_report.php?ready=<key>) as a new, unsaved
 * report. Saving one makes it the person's own saved report. Every function
 * here is pure; the screen applies the entry to its form.
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
 * Every ready report, by key. Pure.
 *
 * Each entry: 'key', 'screen' (the report screen that opens it), 'title'
 * (English, printed through lang()), 'levels' (summarize-by levels in order,
 * each 'by' and 'order'), 'filters' (field, operator, value) and 'period'
 * (the number of calendar days counted, today included; 0 for no bound).
 *
 * sales_by_region counts what the dashboard's Sales map card counts
 * (includes/dashboard/v2/cards/salesmap.php, pg_sales_map_collect()): placed
 * orders by their billing country, then billing state, over the last 30
 * days from today's midnight in the site's time. sales_over_time counts what
 * the Ecommerce summary card sums (includes/dashboard/v2/cards/shop.php: the
 * same complete and exported orders), day by day; the card opens it with its
 * own period (pg_ready_report_url($key, $days)). sales_by_payment_method and
 * sales_by_source (the UTM source) are the strip's other entries. The order
 * report has no product level, so there is no sales by product.
 *
 * @return array
 */
function pg_ready_reports()
{
    return array(
        'sales_by_region' => array(
            'key' => 'sales_by_region',
            'screen' => 'order_report',
            'title' => 'Sales by region',
            'levels' => array(
                array('by' => 'billing_country', 'order' => 'total'),
                array('by' => 'billing_state', 'order' => 'total'),
            ),
            // The new report's own filters: carts and cancelled orders left
            // out, which leaves the complete and exported orders the card sums.
            'filters' => pg_order_report_default_filters(),
            'period' => 30,
        ),
        'sales_over_time' => array(
            'key' => 'sales_over_time',
            'screen' => 'order_report',
            'title' => 'Sales over time',
            'levels' => array(
                array('by' => 'year', 'order' => 'alphabet'),
                array('by' => 'month', 'order' => 'alphabet'),
                array('by' => 'day', 'order' => 'alphabet'),
            ),
            'filters' => pg_order_report_default_filters(),
            'period' => 30,
        ),
        'sales_by_payment_method' => array(
            'key' => 'sales_by_payment_method',
            'screen' => 'order_report',
            'title' => 'Sales by payment method',
            'levels' => array(
                array('by' => 'payment_method', 'order' => 'total'),
            ),
            'filters' => pg_order_report_default_filters(),
            'period' => 30,
        ),
        'sales_by_source' => array(
            'key' => 'sales_by_source',
            'screen' => 'order_report',
            'title' => 'Sales by source',
            'levels' => array(
                array('by' => 'utm_source', 'order' => 'total'),
            ),
            'filters' => pg_order_report_default_filters(),
            'period' => 30,
        ),
    );
}

/**
 * The number of days an address asks a ready report to count instead of
 * its own period, or 0 for its own: a whole number from 1 to 366 written
 * in digits only; anything else is ignored. Pure.
 *
 * @param mixed $value the parameter as sent ($_GET['days'] ?? null)
 * @return int
 */
function pg_ready_report_days($value)
{
    if (!is_string($value) && !is_int($value)) {
        return 0;
    }

    $value = (string) $value;

    if (!ctype_digit($value) || (strlen($value) > 3)) {
        return 0;
    }

    $days = (int) $value;

    return (($days >= 1) && ($days <= 366)) ? $days : 0;
}

/**
 * The strip of a screen's ready reports: each one whose levels the screen
 * offers, with its address and whether it is the one open. Pure.
 *
 * @param string $screen  the screen ('order_report')
 * @param array  $levels  the summarize-by values the screen offers
 * @param string $current the key of the ready report open, '' for none
 * @return array of key, title (English), url, current
 */
function pg_ready_report_strip($screen, $levels, $current = '')
{
    $strip = array();

    foreach (pg_ready_reports() as $key => $report) {
        if ($report['screen'] !== $screen) {
            continue;
        }

        foreach ($report['levels'] as $level) {
            if (!in_array($level['by'], $levels, true)) {
                continue 2;
            }
        }

        $strip[] = array(
            'key' => $key,
            'title' => $report['title'],
            'url' => pg_ready_report_url($key),
            'current' => ($key === (string) $current),
        );
    }

    return $strip;
}

/**
 * The ready report an address parameter names for a screen, or null: a
 * value that is not a string, an unknown key or another screen's key opens
 * the plain new report. Pure.
 *
 * @param mixed  $value  the parameter as sent ($_GET['ready'] ?? null)
 * @param string $screen the screen asking ('order_report')
 * @return array|null
 */
function pg_ready_report_requested($value, $screen)
{
    if (!is_string($value) || ($value === '')) {
        return null;
    }

    $reports = pg_ready_reports();

    if (!isset($reports[$value]) || ($reports[$value]['screen'] !== $screen)) {
        return null;
    }

    return $reports[$value];
}

/**
 * The filters a ready report opens with: its own, then its period as an
 * order date filter "is greater than or equal to N day(s) ago", N being one
 * less than the days counted, so today is the last of them. Pure.
 *
 * @param array $report an entry of pg_ready_reports()
 * @param int   $days   days to count instead of its period (0: its own;
 *                      pg_ready_report_days())
 * @return array field, operator, value, dynamic_value and
 *               dynamic_value_attribute of each filter
 */
function pg_ready_report_filters($report, $days = 0)
{
    $filters = array();

    foreach ((array) ($report['filters'] ?? array()) as $filter) {
        $filters[] = array(
            'field' => (string) $filter['field'],
            'operator' => (string) $filter['operator'],
            'value' => (string) $filter['value'],
            'dynamic_value' => '',
            'dynamic_value_attribute' => '',
        );
    }

    $days = ((int) $days > 0) ? (int) $days : (int) ($report['period'] ?? 0);

    if ($days > 0) {
        $filters[] = array(
            'field' => 'order_date',
            'operator' => 'is greater than or equal to',
            'value' => '',
            'dynamic_value' => 'days ago',
            'dynamic_value_attribute' => (string) ($days - 1),
        );
    }

    return $filters;
}

/**
 * The address that opens a ready report on its screen. Pure.
 *
 * @param string $key  a key of pg_ready_reports()
 * @param int    $days days to count instead of its period (0: its own)
 * @return string relative to the software directory
 */
function pg_ready_report_url($key, $days = 0)
{
    $url = 'view_order_report.php?ready=' . rawurlencode((string) $key);

    if ((int) $days > 0) {
        $url .= '&days=' . (int) $days;
    }

    return $url;
}
