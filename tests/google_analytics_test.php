<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/google_analytics.php: the periods,
 * the runReport request bodies, a batchRunReports answer parsed into the
 * card's figures (comparison, rounding, an empty property), the cache window,
 * the property list of accountSummaries and the stored state.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_analytics.php');

// A batchRunReports answer for the four requests of pg_ga_report_requests(),
// shaped as the Data API sends it: every value a string, the date range
// dimension added to the totals report by Google.
function google_analytics_test_answer()
{
	$metrics = array(
		array('name' => 'activeUsers', 'type' => 'TYPE_INTEGER'),
		array('name' => 'sessions', 'type' => 'TYPE_INTEGER'),
		array('name' => 'screenPageViews', 'type' => 'TYPE_INTEGER'),
		array('name' => 'keyEvents', 'type' => 'TYPE_FLOAT'),
		array('name' => 'engagementRate', 'type' => 'TYPE_FLOAT'),
	);

	return array('reports' => array(
		array(
			'dimensionHeaders' => array(array('name' => 'dateRange')),
			'metricHeaders'    => $metrics,
			'rows'             => array(
				array('dimensionValues' => array(array('value' => 'previous')), 'metricValues' => array(
					array('value' => '800'), array('value' => '1000'), array('value' => '2500'), array('value' => '40'), array('value' => '0.5'))),
				array('dimensionValues' => array(array('value' => 'current')), 'metricValues' => array(
					array('value' => '1000'), array('value' => '1250'), array('value' => '3000'), array('value' => '39.6'), array('value' => '0.53456'))),
			),
		),
		array(
			'dimensionHeaders' => array(array('name' => 'date')),
			'metricHeaders'    => array(array('name' => 'activeUsers', 'type' => 'TYPE_INTEGER')),
			'rows'             => array(
				array('dimensionValues' => array(array('value' => '20261027')), 'metricValues' => array(array('value' => '40'))),
				array('dimensionValues' => array(array('value' => '20261001')), 'metricValues' => array(array('value' => '12'))),
				array('dimensionValues' => array(array('value' => '20250101')), 'metricValues' => array(array('value' => '99'))),
			),
		),
		array(
			'dimensionHeaders' => array(array('name' => 'pagePath')),
			'metricHeaders'    => array(array('name' => 'screenPageViews', 'type' => 'TYPE_INTEGER')),
			'rows'             => array(
				array('dimensionValues' => array(array('value' => '/')), 'metricValues' => array(array('value' => '900'))),
				array('dimensionValues' => array(array('value' => '/shop/<b>shoes</b>')), 'metricValues' => array(array('value' => '300'))),
			),
		),
		array(
			'dimensionHeaders' => array(array('name' => 'sessionSourceMedium')),
			'metricHeaders'    => array(array('name' => 'sessions', 'type' => 'TYPE_INTEGER')),
			'rows'             => array(
				array('dimensionValues' => array(array('value' => 'google / organic')), 'metricValues' => array(array('value' => '700'))),
				array('dimensionValues' => array(array('value' => '(direct) / (none)')), 'metricValues' => array(array('value' => '350'))),
			),
		),
	));
}

function test_google_analytics_period_ends_yesterday()
{
	pg_assert_same(array(
		'from'          => '2026-10-01',
		'to'            => '2026-10-28',
		'previous_from' => '2026-09-03',
		'previous_to'   => '2026-09-30',
	), pg_ga_period('2026-10-29'), '28 days to yesterday, then the 28 before');

	// Across the end of daylight saving time the days still count whole.
	$period = pg_ga_period('2026-03-30', 7);
	pg_assert_same('2026-03-23', $period['from']);
	pg_assert_same('2026-03-29', $period['to']);
	pg_assert_same('2026-03-16', $period['previous_from']);
	pg_assert_same('2026-03-22', $period['previous_to']);
}

function test_google_analytics_report_requests()
{
	$requests = pg_ga_report_requests(pg_ga_period('2026-10-29'), 5);

	pg_assert_same(4, count($requests), 'totals, daily, pages, sources');

	pg_assert_same(array(
		'dateRanges' => array(
			array('startDate' => '2026-10-01', 'endDate' => '2026-10-28', 'name' => 'current'),
			array('startDate' => '2026-09-03', 'endDate' => '2026-09-30', 'name' => 'previous'),
		),
		'metrics' => array(
			array('name' => 'activeUsers'),
			array('name' => 'sessions'),
			array('name' => 'screenPageViews'),
			array('name' => 'keyEvents'),
			array('name' => 'engagementRate'),
		),
	), $requests[0], 'totals: both periods in one request');

	pg_assert_same(array(
		'dateRanges' => array(array('startDate' => '2026-10-01', 'endDate' => '2026-10-28')),
		'dimensions' => array(array('name' => 'date')),
		'metrics'    => array(array('name' => 'activeUsers')),
		'orderBys'   => array(array('dimension' => array('dimensionName' => 'date'))),
		'limit'      => 100,
	), $requests[1], 'daily: active users per day');

	pg_assert_same(array(
		'dateRanges' => array(array('startDate' => '2026-10-01', 'endDate' => '2026-10-28')),
		'dimensions' => array(array('name' => 'pagePath')),
		'metrics'    => array(array('name' => 'screenPageViews')),
		'orderBys'   => array(array('metric' => array('metricName' => 'screenPageViews'), 'desc' => true)),
		'limit'      => 5,
	), $requests[2], 'top pages by views');

	pg_assert_same(array('name' => 'sessionSourceMedium'), $requests[3]['dimensions'][0], 'top sources');
	pg_assert_same(array('metric' => array('metricName' => 'sessions'), 'desc' => true), $requests[3]['orderBys'][0]);

	// The batch body the Data API takes, with nothing that is not JSON.
	pg_assert_same('{"requests":[', substr(json_encode(array('requests' => $requests)), 0, 13));
}

function test_google_analytics_report_parse()
{
	$period = pg_ga_period('2026-10-29');
	$report = pg_ga_report_parse(google_analytics_test_answer(), $period);

	pg_assert_same($period['from'], $report['from']);
	pg_assert_same(array('activeUsers' => 1000, 'sessions' => 1250, 'screenPageViews' => 3000, 'keyEvents' => 40, 'engagementRate' => 0.5346), $report['now'], 'rows found by their range name, rounded');
	pg_assert_same(array('activeUsers' => 800, 'sessions' => 1000, 'screenPageViews' => 2500, 'keyEvents' => 40, 'engagementRate' => 0.5), $report['before']);

	pg_assert_same(28, count($report['daily']), 'every day of the period, missing days as 0');
	pg_assert_same(12, $report['daily']['2026-10-01']);
	pg_assert_same(0, $report['daily']['2026-10-02']);
	pg_assert_same(40, $report['daily']['2026-10-27']);
	pg_assert_false(isset($report['daily']['2025-01-01']), 'a day outside the period is dropped');

	pg_assert_same(array(array('name' => '/', 'value' => 900), array('name' => '/shop/<b>shoes</b>', 'value' => 300)), $report['pages'], 'raw text; the card escapes');
	pg_assert_same('google / organic', $report['sources'][0]['name']);
	pg_assert_same(350, $report['sources'][1]['value']);

	// The default range names Google gives when none are set.
	$answer = google_analytics_test_answer();
	$answer['reports'][0]['rows'][0]['dimensionValues'][0]['value'] = 'date_range_1';
	$answer['reports'][0]['rows'][1]['dimensionValues'][0]['value'] = 'date_range_0';
	pg_assert_same(1000, pg_ga_report_parse($answer, $period)['now']['activeUsers']);
}

function test_google_analytics_card_figures()
{
	$figures = pg_ga_card_figures(pg_ga_report_parse(google_analytics_test_answer(), pg_ga_period('2026-10-29')));

	pg_assert_same(array('activeUsers', 'sessions', 'screenPageViews', 'keyEvents', 'engagementRate'), array_keys($figures));
	pg_assert_same(array('value' => 1000, 'kind' => 'count', 'change' => 0.25), $figures['activeUsers']);
	pg_assert_same(0.2, $figures['screenPageViews']['change']);
	pg_assert_same(0.0, $figures['keyEvents']['change'], 'equal counts: no movement');
	pg_assert_same(array('value' => 53.5, 'kind' => 'rate', 'change' => 3.5), $figures['engagementRate'], 'a rate in percent, its change in points');

	// An empty property: zeros, no change against nothing, flagged empty.
	$empty = pg_ga_report_parse(array('reports' => array(array(), array(), array(), array())), pg_ga_period('2026-10-29'));
	$figures = pg_ga_card_figures($empty);

	pg_assert_true($empty['empty']);
	pg_assert_same(array('value' => 0, 'kind' => 'count', 'change' => null), $figures['sessions']);
	pg_assert_same(array('value' => 0.0, 'kind' => 'rate', 'change' => null), $figures['engagementRate']);
	pg_assert_same(array(), $empty['pages']);
	pg_assert_same(28, count($empty['daily']));
	pg_assert_false(pg_ga_report_parse(google_analytics_test_answer(), pg_ga_period('2026-10-29'))['empty']);

	// Nothing before, something now: no percentage can be drawn.
	pg_assert_same(null, pg_ga_change(5, 0));
	pg_assert_same(-0.5, pg_ga_change(5, 10));
}

function test_google_analytics_cache_window()
{
	$now = 1800000000;

	pg_assert_true(pg_ga_due(0, $now), 'never read');
	pg_assert_false(pg_ga_due($now - 3600, $now), 'an hour old');
	pg_assert_false(pg_ga_due($now - PG_GA_TTL + 1, $now));
	pg_assert_true(pg_ga_due($now - PG_GA_TTL, $now), 'as old as the window');
	pg_assert_true(pg_ga_due($now + 7200, $now), 'a clock set back reads again');
	pg_assert_false(pg_ga_due($now - 600, $now, 900));
}

function test_google_analytics_properties_from_api()
{
	$properties = pg_ga_properties_from_api(array(
		'accountSummaries' => array(
			array('name' => 'accountSummaries/11', 'account' => 'accounts/11', 'displayName' => 'Shop', 'propertySummaries' => array(
				array('property' => 'properties/222', 'displayName' => 'shop.example', 'propertyType' => 'PROPERTY_TYPE_ORDINARY'),
				array('property' => 'properties/333', 'displayName' => '', 'propertyType' => 'PROPERTY_TYPE_ORDINARY'),
				array('property' => 'properties/x9', 'displayName' => 'Broken'),
			)),
			array('name' => 'accountSummaries/12', 'account' => 'accounts/12', 'displayName' => 'Empty account'),
			'not an array',
		),
	));

	pg_assert_same(array(
		'222' => 'shop.example (Shop)',
		'333' => '333 (Shop)',
	), $properties, 'id => property and account name; bad ids dropped');

	pg_assert_same(array(), pg_ga_properties_from_api(array()));
	pg_assert_same('222', pg_ga_property_id('properties/222'));
	pg_assert_same('222', pg_ga_property_id(' 222 '));
	pg_assert_same('', pg_ga_property_id('properties/../1'));
	pg_assert_same('', pg_ga_property_id(''));
}

function test_google_analytics_state_parse()
{
	$empty = pg_ga_state_parse(null);

	pg_assert_same(0, $empty['connection_id']);
	pg_assert_same('', $empty['property']);
	pg_assert_same(array(), $empty['report']);

	$state = pg_ga_state_parse(array(
		'connection_id' => '7',
		'property'      => 'properties/222',
		'property_name' => array('nested'),
		'fetched_at'    => '1800000000',
		'fetch_error'   => 'HTTP 403',
		'report'        => array('now' => array()),
		'unknown'       => 'x',
	));

	pg_assert_same(7, $state['connection_id']);
	pg_assert_same('222', $state['property']);
	pg_assert_same('', $state['property_name'], 'a nested value is not text');
	pg_assert_same(1800000000, $state['fetched_at']);
	pg_assert_same('HTTP 403', $state['fetch_error']);
	pg_assert_same(array(), $state['report'], 'a report without its figures is dropped');
	pg_assert_false(isset($state['unknown']));

	$report = pg_ga_report_parse(google_analytics_test_answer(), pg_ga_period('2026-10-29'));
	pg_assert_same($report, pg_ga_state_parse(array('report' => $report))['report'], 'a whole report is kept as it is');
}
