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
 * days from today's midnight in the site's time.
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
    );
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
 * @return array field, operator, value, dynamic_value and
 *               dynamic_value_attribute of each filter
 */
function pg_ready_report_filters($report)
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

    $days = (int) ($report['period'] ?? 0);

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
 * @param string $key a key of pg_ready_reports()
 * @return string relative to the software directory
 */
function pg_ready_report_url($key)
{
    return 'view_order_report.php?ready=' . rawurlencode((string) $key);
}
