<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Visitor traffic, added up: visits and page views by day or hour, the most
// viewed content, and visits broken down by where they came from.
//
// Only totals leave the site. A visit's own row carries an IP address and a
// city, and nothing here hands out a single visit: the visitor screens open
// that to the people who may see it, and a machine counting traffic does not
// need it.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// The window a report covers, from the from and to parameters: the last thirty
// days when neither is sent, and never longer than $max_days.
function api_visitors_window($params, $max_days) {

	$to = isset($params['to']) ? (int)$params['to'] : time();

	$from = isset($params['from']) ? (int)$params['from'] : ($to - (29 * 86400));

	if ($from > $to) {

		api_fail_validation(lang('from is after to.'), 'from');

	}

	$first = date('Y-m-d', $from);

	$last = date('Y-m-d', $to);

	$days = (int)round((strtotime($last . ' 12:00:00') - strtotime($first . ' 12:00:00')) / 86400) + 1;

	if ($days > $max_days) {

		api_fail_validation(lang(array(
			'string' => 'The window is longer than {var:1} days. Ask for a shorter one.',
			'vars'   => $max_days
		)), 'from');

	}

	return array('from' => $from, 'to' => $to, 'first' => $first, 'last' => $last, 'days' => $days);

}

// Visits and page views from the hourly rollup, one entry per day or hour of
// the window, empty ones included so a chart needs no gap filling.
function api_reports_traffic($params) {

	$interval = (isset($params['interval']) && ($params['interval'] === 'hour')) ? 'hour' : 'day';

	$window = api_visitors_window($params, ($interval === 'hour') ? 31 : 400);

	$stats = pg_visitor_stats_range($window['first'], $window['last']);

	$periods = array();

	$totals = array('visits' => 0, 'page_views' => 0);

	for ($day = 0; $day < $window['days']; $day++) {

		$date = date('Y-m-d', strtotime($window['first'] . ' 12:00:00') + ($day * 86400));

		$hours = isset($stats[$date]) ? $stats[$date] : array();

		$day_visits = 0;

		$day_views = 0;

		for ($hour = 0; $hour < 24; $hour++) {

			$visits = isset($hours[$hour]) ? (int)$hours[$hour]['visitors'] : 0;

			$views = isset($hours[$hour]) ? (int)$hours[$hour]['page_views'] : 0;

			$day_visits += $visits;

			$day_views += $views;

			if ($interval === 'hour') {

				$periods[] = array('period' => $date . ' ' . sprintf('%02d', $hour) . ':00', 'visits' => $visits, 'page_views' => $views);

			}

		}

		if ($interval === 'day') {

			$periods[] = array('period' => $date, 'visits' => $day_visits, 'page_views' => $day_views);

		}

		$totals['visits'] += $day_visits;

		$totals['page_views'] += $day_views;

	}

	api_ok(array(
		'from'             => $window['first'],
		'to'               => $window['last'],
		'interval'         => $interval,
		'visitor_tracking' => (defined('VISITOR_TRACKING') && VISITOR_TRACKING == true),
		'totals'           => $totals,
		'periods'          => $periods
	));

}

function api_traffic_report_schema() {

	return array(
		'from'             => 'string',
		'to'               => 'string',
		'interval'         => 'string',
		'visitor_tracking' => 'boolean',
		'totals'           => array('visits' => 'integer', 'page_views' => 'integer'),
		'periods'          => array(array('period' => 'string', 'visits' => 'integer', 'page_views' => 'integer'))
	);

}

// One page, product, product group or form record, with its views.
function api_top_content_present($row) {

	return array(
		'label'     => (string)$row['label'],
		'url'       => (string)$row['url'],
		'views'     => (int)$row['views'],
		'page_id'   => ((int)$row['page_id'] > 0) ? (int)$row['page_id'] : null,
		'page_name' => (string)$row['page_name'],
		// product, product_group or form when one item on the page was viewed;
		// null for the page itself.
		'item_type' => ((string)$row['item_type'] !== '') ? (string)$row['item_type'] : null,
		'item_id'   => ((int)$row['item_id'] > 0) ? (int)$row['item_id'] : null
	);

}

function api_top_content_schema() {

	return array(
		'label'     => 'string',
		'url'       => 'string',
		'views'     => 'integer',
		'page_id'   => 'integer?',
		'page_name' => 'string',
		'item_type' => 'string?',
		'item_id'   => 'integer?'
	);

}

function api_reports_top_content($params) {

	$window = api_visitors_window($params, 400);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 10;

	$out = array();

	foreach (pg_visitor_top_content($window['first'], $window['last'], $limit) as $row) {

		$out[] = api_top_content_present($row);

	}

	api_ok_list($out, $limit, null);

}

// What a visit can be counted by, and the visitors column (or expression) it is.
function api_visitors_dimensions() {

	// Paid is a tracking code carrying the pay-per-click flag set in the SEO
	// settings. With no flag set nothing is counted as paid: an empty pattern
	// would match every visit.
	$flag = defined('PAY_PER_CLICK_FLAG') ? trim((string)PAY_PER_CLICK_FLAG) : '';

	$channel = "CASE"
		. (($flag !== '') ? " WHEN (tracking_code LIKE '%" . escape(escape_like($flag)) . "%') THEN 'pay_per_click'" : '')
		. " WHEN (referring_search_engine <> '') THEN 'organic' ELSE 'neither' END";

	return array(
		'referrer_host'     => 'referring_host_name',
		'search_engine'     => 'referring_search_engine',
		'search_terms'      => 'referring_search_terms',
		'channel'           => $channel,
		'landing_page'      => 'landing_page_name',
		'tracking_code'     => 'tracking_code',
		'utm_source'        => 'utm_source',
		'utm_medium'        => 'utm_medium',
		'utm_campaign'      => 'utm_campaign',
		'utm_term'          => 'utm_term',
		'utm_content'       => 'utm_content',
		'country'           => 'country',
		'state'             => 'state',
		'city'              => 'city',
		'form'              => 'custom_form_name',
		'site_search_terms' => 'site_search_terms',
		'device'            => pg_schema_has('visitors', 'user_agent') ? pg_visitor_device_sql('user_agent') : "''"
	);

}

// The dimension of the daily summary (includes/fn/visitor_retention.php) each
// one is kept under once the rows of its day are gone.
function api_visitors_summary_dimensions() {

	return array(
		'referrer_host'     => 'referring_host_name',
		'search_engine'     => 'referring_search_engine',
		'search_terms'      => 'referring_search_terms',
		'channel'           => 'pay_per_click_organic',
		'landing_page'      => 'landing_page_name',
		'tracking_code'     => 'tracking_code',
		'utm_source'        => 'utm_source',
		'utm_medium'        => 'utm_medium',
		'utm_campaign'      => 'utm_campaign',
		'utm_term'          => 'utm_term',
		'utm_content'       => 'utm_content',
		'country'           => 'country',
		'state'             => 'state',
		'city'              => 'city',
		'form'              => 'custom_form_name',
		'site_search_terms' => 'site_search_terms',
		'device'            => 'device'
	);

}

function api_visitors_figures($row) {

	return array(
		'visits'             => (int)$row['visits'],
		'page_views'         => (int)$row['page_views'],
		'forms_submitted'    => (int)$row['forms_submitted'],
		'orders_created'     => (int)$row['orders_created'],
		'orders_checked_out' => (int)$row['orders_checked_out'],
		'orders_completed'   => (int)$row['orders_completed'],
		'order_total'        => api_money($row['order_total'])
	);

}

function api_visitors_figures_schema() {

	return array(
		'visits'             => 'integer',
		'page_views'         => 'integer',
		'forms_submitted'    => 'integer',
		'orders_created'     => 'integer',
		'orders_checked_out' => 'integer',
		'orders_completed'   => 'integer',
		'order_total'        => 'integer'
	);

}

// Visits in the window counted by one dimension, largest first, as the visitor
// report screen summarises them. The figures are the same sums the screen
// shows: the order flags are 0 or 1 on a visit, so their sum is a count.
function api_reports_visitors($params) {

	$window = api_visitors_window($params, 400);

	$dimensions = api_visitors_dimensions();

	$by = (string)$params['by'];

	$expression = $dimensions[$by];

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$sums = "COUNT(*) AS visits,
		COALESCE(SUM(page_views), 0) AS page_views,
		COALESCE(SUM(custom_form_submitted), 0) AS forms_submitted,
		COALESCE(SUM(order_created), 0) AS orders_created,
		COALESCE(SUM(order_checked_out), 0) AS orders_checked_out,
		COALESCE(SUM(order_completed), 0) AS orders_completed,
		COALESCE(SUM(order_total), 0) AS order_total";

	// Whole days by the site's clock, as the other two reports count them.
	$start = strtotime($window['first'] . ' 00:00:00');
	$stop = strtotime($window['last'] . ' 00:00:00 +1 day');

	// Days whose rows the retention period has removed come from the daily
	// summary: every day before the first raw one, the rows from it on. A
	// summarized day's row the cleanup has not reached yet is not counted
	// twice.
	$summary_to = null;
	$summary_rows = array();
	$summary_total = null;
	$raw_from = function_exists('pg_visitor_summary_raw_from') ? pg_visitor_summary_raw_from() : 0;

	if ($raw_from > $start) {

		$summary_last = min($window['last'], date('Y-m-d', $raw_from - 1));
		$summary_dimensions = api_visitors_summary_dimensions();

		$summary_range = "(stat_date >= '" . escape($window['first']) . "') AND (stat_date <= '" . escape($summary_last) . "')";

		$summary_sums = "COALESCE(SUM(visits), 0) AS visits,
			COALESCE(SUM(page_views), 0) AS page_views,
			COALESCE(SUM(forms_submitted), 0) AS forms_submitted,
			COALESCE(SUM(orders_created), 0) AS orders_created,
			COALESCE(SUM(orders_checked_out), 0) AS orders_checked_out,
			COALESCE(SUM(orders_completed), 0) AS orders_completed,
			COALESCE(SUM(order_total), 0) AS order_total";

		$summary_total = api_row("SELECT " . $summary_sums . " FROM visitor_stats_daily WHERE (dimension = '') AND " . $summary_range);

		// A day's values outside its top ones are one remainder row there,
		// counted in the totals and never listed as a value.
		$summary_rows = api_rows("SELECT
				" . (($by === 'channel') ? "IF(dim_value = '', 'neither', dim_value)" : "dim_value") . " AS value, " . $summary_sums . "
			FROM visitor_stats_daily
			WHERE (dimension = '" . escape($summary_dimensions[$by]) . "') AND (is_rest = 0) AND " . $summary_range . "
			GROUP BY value
			ORDER BY visits DESC, value ASC
			LIMIT " . max(500, $limit * 4));

		if ((int) $summary_total['visits'] > 0) {
			$summary_to = $summary_last;
		}

		$start = max($start, $raw_from);

	}

	$where = "WHERE start_timestamp >= '" . (int) $start . "'
		AND start_timestamp < '" . (int) $stop . "'";

	$total = api_row("SELECT " . $sums . " FROM visitors " . $where);

	$rows = api_rows("SELECT " . $expression . " AS value, " . $sums . "
		FROM visitors
		" . $where . "
		GROUP BY value
		ORDER BY visits DESC, value ASC
		LIMIT " . (($summary_to !== null) ? max(500, $limit * 4) : $limit));

	if ($summary_to !== null) {

		$figures = array('visits', 'page_views', 'forms_submitted', 'orders_created', 'orders_checked_out', 'orders_completed', 'order_total');

		foreach ($figures as $figure) {
			$total[$figure] = (int) $total[$figure] + (int) $summary_total[$figure];
		}

		// The two lists added up by value, as the database groups them:
		// without regard to case.
		$merged = array();

		foreach (array_merge($summary_rows, $rows) as $row) {

			$key = mb_strtolower((string) $row['value']);

			if (!isset($merged[$key])) {
				$merged[$key] = array('value' => (string) $row['value']) + array_fill_keys($figures, 0);
			}

			foreach ($figures as $figure) {
				$merged[$key][$figure] += (int) $row[$figure];
			}

		}

		usort($merged, function ($a, $b) {
			return ($a['visits'] != $b['visits']) ? (($a['visits'] > $b['visits']) ? -1 : 1) : strcmp($a['value'], $b['value']);
		});

		$rows = array_slice($merged, 0, $limit);

	}

	$out = array();

	foreach ($rows as $row) {

		$out[] = array_merge(
			// An empty value is a visit that had none: no referrer is a direct
			// visit, no campaign is traffic that did not come from one.
			array('value' => ((string)$row['value'] !== '') ? (string)$row['value'] : null),
			api_visitors_figures($row)
		);

	}

	api_ok(array(
		'from'       => $window['first'],
		'to'         => $window['last'],
		'by'         => $by,
		'totals'     => api_visitors_figures($total),
		'rows'       => $out,
		'summary_to' => $summary_to
	));

}

function api_visitor_breakdown_schema() {

	return array(
		'from'   => 'string',
		'to'     => 'string',
		'by'     => 'string',
		'totals'     => api_visitors_figures_schema(),
		'rows'       => array(array_merge(array('value' => 'string?'), api_visitors_figures_schema())),
		'summary_to' => 'string?'
	);

}
