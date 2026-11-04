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

	// The channel is the rule the daily summary keeps (pg_visitor_channel_sql()):
	// paid, email, ai, organic, social, referral or direct.
	$channel = pg_visitor_channel_sql();

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
		'device'            => pg_schema_has('visitors', 'user_agent') ? pg_visitor_device_sql(pg_visitor_text_sql('user_agent')) : "''"
	);

}

// The dimension of the daily summary (includes/fn/visitor_retention.php) each
// one is kept under once the rows of its day are gone.
function api_visitors_summary_dimensions() {

	return array(
		'referrer_host'     => 'referring_host_name',
		'search_engine'     => 'referring_search_engine',
		'search_terms'      => 'referring_search_terms',
		'channel'           => 'channel',
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
// report screen summarises them (includes/fn/visitor_figures.php): the daily
// summary for the days it holds, the rows for the rest. summary_days and
// raw_days say how many days each answered.
function api_reports_visitors($params) {

	$window = api_visitors_window($params, 400);

	$by = (string)$params['by'];

	$summary_dimensions = api_visitors_summary_dimensions();

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	// by is one of the route's values, each kept in the summary.
	$figures = pg_visitor_breakdown($summary_dimensions[$by], $window['first'], $window['last'], $limit);

	$out = array();

	foreach ($figures['rows'] as $row) {

		$value = (string)$row['value'];

		$out[] = array_merge(
			// An empty value is a visit that had none: no referrer is a direct
			// visit, no campaign is traffic that did not come from one.
			array('value' => ($value !== '') ? $value : null),
			api_visitors_figures($row)
		);

	}

	api_ok(array(
		'from'         => $window['first'],
		'to'           => $window['last'],
		'by'           => $by,
		'totals'       => api_visitors_figures($figures['totals']),
		'rows'         => $out,
		'summary_to'   => $figures['summary_to'],
		'summary_days' => $figures['summary_days']
	));

}

function api_visitor_breakdown_schema() {

	return array(
		'from'   => 'string',
		'to'     => 'string',
		'by'     => 'string',
		'totals'     => api_visitors_figures_schema(),
		'rows'       => array(array_merge(array('value' => 'string?'), api_visitors_figures_schema())),
		'summary_to'   => 'string?',
		'summary_days' => 'integer'
	);

}

// What AI assistants and AI search indexers fetched, what the site tells
// them, and the visits their answers sent (includes/fn/ai_visibility.php).
function api_reports_ai_visibility($params) {

	if (!function_exists('pg_ai_visibility_ready') || !pg_ai_visibility_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

	$window = api_visitors_window($params, 400);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 10;

	$settings = pg_ai_crawler_settings();

	$days = pg_ai_fetches_by_day($window['first'], $window['last']);

	$bots = array();

	foreach (pg_ai_fetch_bot_totals($days, pg_ai_bot_policy($settings)) as $bot) {

		$bots[] = array(
			'bot'              => $bot['bot'],
			'kind'             => $bot['kind'],
			'fetches'          => (int)$bot['fetches'],
			'firewall_allowed' => (bool)$bot['firewall_allowed']
		);

	}

	$top_pages = array();

	foreach (pg_ai_top_pages($window['first'], $window['last'], $limit) as $row) {

		$top_pages[] = array(
			'bot'       => $row['bot'],
			'page_id'   => (int)$row['page_id'],
			'page_name' => (string)$row['page_name'],
			'label'     => (string)$row['label'],
			'url'       => (string)$row['url'],
			'fetches'   => (int)$row['fetches']
		);

	}

	$unfetched = pg_ai_unfetched_pages($window['first'], $window['last'], 50);

	$referrals = pg_ai_referrals($window['first'], $window['last']);

	$assistants = array();

	foreach ($referrals['assistants'] as $row) {

		$assistants[] = array_merge(array('assistant' => $row['assistant']), api_ai_referral_figures($row));

	}

	api_ok(array(
		'from'            => $window['first'],
		'to'              => $window['last'],
		'bots'            => $bots,
		'days'            => $days,
		'top_pages'       => $top_pages,
		'unfetched_pages' => array('total' => (int)$unfetched['total'], 'pages' => $unfetched['pages']),
		'robots_ai_rules' => !empty($settings['rules']),
		'llms_txt'        => !empty($settings['llms']),
		'referrals'       => array(
			'totals'     => api_ai_referral_figures($referrals['totals']),
			'assistants' => $assistants,
			'summary_to' => $referrals['summary_to']
		)
	));

}

function api_ai_referral_figures($row) {

	return array(
		'visits'           => (int)$row['visits'],
		'page_views'       => (int)$row['page_views'],
		'forms_submitted'  => (int)$row['forms_submitted'],
		'orders_completed' => (int)$row['orders_completed'],
		'order_total'      => api_money($row['order_total'])
	);

}

function api_ai_referral_figures_schema() {

	return array(
		'visits'           => 'integer',
		'page_views'       => 'integer',
		'forms_submitted'  => 'integer',
		'orders_completed' => 'integer',
		'order_total'      => 'integer'
	);

}

function api_ai_visibility_report_schema() {

	return array(
		'from'            => 'string',
		'to'              => 'string',
		'bots'            => array(array('bot' => 'string', 'kind' => 'string', 'fetches' => 'integer', 'firewall_allowed' => 'boolean')),
		'days'            => array(array('day' => 'string', 'bot' => 'string', 'fetches' => 'integer')),
		'top_pages'       => array(array('bot' => 'string', 'page_id' => 'integer', 'page_name' => 'string', 'label' => 'string', 'url' => 'string', 'fetches' => 'integer')),
		'unfetched_pages' => array('total' => 'integer', 'pages' => array(array('page_id' => 'integer', 'page_name' => 'string'))),
		'robots_ai_rules' => 'boolean',
		'llms_txt'        => 'boolean',
		'referrals'       => array(
			'totals'     => api_ai_referral_figures_schema(),
			'assistants' => array(array_merge(array('assistant' => 'string'), api_ai_referral_figures_schema())),
			'summary_to' => 'string?'
		)
	);

}
