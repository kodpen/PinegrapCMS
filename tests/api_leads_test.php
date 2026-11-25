<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor journey's API (includes/api/resources/leads.php):
 * GET /customers/hot (the MCP read tool, followed_by), GET
 * /customers/{id}/journey, GET /customers/{id}/score, GET
 * /reports/lead-sources, and the interest score and interests on the
 * Customer answer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/customers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/leads.php');

// The presenters' helper lives in includes/api/response.php, which loads only
// behind the API's own entry. A stand-in of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		$unix = (int) $unix;

		return ($unix <= 0) ? null : gmdate('Y-m-d\TH:i:s\Z', $unix);
	}
}

function _api_leads_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

function test_api_leads_routes()
{
	$expect = array(
		'customers.hot'        => array('/customers/hot', 'customers:read', 'api_customers_hot'),
		'customers.journey'    => array('/customers/{id}/journey', 'customers:read', 'api_customers_journey'),
		'customers.score'      => array('/customers/{id}/score', 'customers:read', 'api_customers_score'),
		'reports.lead_sources' => array('/reports/lead-sources', 'visitors:read', 'api_reports_lead_sources'),
	);

	foreach ($expect as $id => $spec) {
		$route = _api_leads_route($id);

		pg_assert_true(is_array($route), $id . ' is declared');
		pg_assert_same('GET', $route['method'], $id . ' reads');
		pg_assert_same($spec[0], $route['path'], $id . ' path');
		pg_assert_same($spec[1], $route['scope'], $id . ' scope');
		pg_assert_true(function_exists($spec[2]), $id . ' handler exists');
		// A route is an MCP tool unless it says otherwise.
		pg_assert_false(isset($route['mcp']), $id . ' is an MCP tool');
	}

	pg_assert_same('HotContact', _api_leads_route('customers.hot')['returns']['list'], 'hot contacts list');
}

function test_api_leads_score_unscored()
{
	$score = api_lead_score_present(7, null, 60);

	pg_assert_same(7, $score['id']);
	pg_assert_false($score['scored'], 'never scored');
	pg_assert_same(null, $score['interest']);
	pg_assert_same(null, $score['fit']);
	pg_assert_same(60, $score['threshold']);
	pg_assert_false($score['hot']);
	pg_assert_same(array(), $score['reasons']);
	pg_assert_same(array(), $score['topics']);
	pg_assert_same(null, $score['scored_at']);
}

function test_api_leads_score_scored()
{
	$row = array(
		'interest' => '72', 'fit' => '45', 'hot_at' => '1800000000', 'last_visit_at' => '1800000600', 'scored_at' => '1800001200',
		'reasons' => json_encode(array('interest' => array(array('kind' => 'pricing_page', 'points' => 30.04), array('kind' => '')), 'fit' => array(array('kind' => 'company', 'points' => 25)))),
		'topics' => json_encode(array(array('id' => 4, 'kind' => 'folder', 'name' => 'Services', 'weight' => 120.26), array('name' => ''))),
	);

	$score = api_lead_score_present(7, $row, 60);

	pg_assert_true($score['scored']);
	pg_assert_same(72, $score['interest']);
	pg_assert_same(45, $score['fit']);
	pg_assert_true($score['hot'], 'at or above the threshold');
	pg_assert_false(api_lead_score_present(7, array('interest' => '59') + $row, 60)['hot'], 'below the threshold');
	pg_assert_same(array(
		array('score' => 'interest', 'kind' => 'pricing_page', 'points' => 30.0),
		array('score' => 'fit', 'kind' => 'company', 'points' => 25.0),
	), $score['reasons'], 'both lists, empty kinds left out');
	pg_assert_same(array(array('id' => 4, 'kind' => 'folder', 'name' => 'Services', 'weight' => 120.3)), $score['topics'], 'nameless topics left out');
	pg_assert_same('2027-01-15T08:00:00Z', $score['hot_at']);
	pg_assert_same('2027-01-15T08:20:00Z', $score['scored_at']);
	pg_assert_same(array_keys(api_lead_score_schema()), array_keys($score), 'schema and presenter match');
}

function test_api_leads_journey_entries()
{
	$visit = api_lead_journey_entry_present(array('kind' => 'visit', 'id' => 3, 'at' => 1800000000, 'pages' => array(array('pricing', 40), array('chair', 90, 'product', 12)), 'count' => 2, 'minutes' => 2));

	pg_assert_same('visit', $visit['kind']);
	pg_assert_same(array(
		array('page' => 'pricing', 'seconds' => 40, 'item_type' => null, 'item_id' => null),
		array('page' => 'chair', 'seconds' => 90, 'item_type' => 'product', 'item_id' => 12),
	), $visit['pages'], 'pages with the catalogue item');
	pg_assert_same(2, $visit['minutes']);
	pg_assert_same(null, $visit['amount']);
	pg_assert_same(null, $visit['name'], 'a visit has no name');

	$order = api_lead_journey_entry_present(array('kind' => 'order', 'id' => 9, 'at' => 1800000000, 'name' => '1001', 'amount' => 129900, 'url' => 'view_order.php?id=9'));

	pg_assert_same(129900, $order['amount']);
	pg_assert_same(null, $order['pages']);
	pg_assert_false(isset($order['url']), 'no panel address');

	$course = api_lead_journey_entry_present(array('kind' => 'course', 'id' => 5, 'at' => 1800000000, 'name' => 'Basics', 'complete' => true));

	pg_assert_true($course['complete']);
	pg_assert_same(array_keys(api_lead_journey_entry_schema()), array_keys($order), 'schema and presenter match');

	pg_assert_same(null, api_lead_first_touch_present(null), 'no visit tied');
	pg_assert_same(
		array('channel' => 'search', 'campaign' => '', 'landing_page' => 'home', 'at' => '2027-01-15T08:00:00Z'),
		api_lead_first_touch_present(array('channel' => 'search', 'campaign' => '', 'landing_page' => 'home', 'at' => 1800000000))
	);
}

function test_api_leads_hot_followed_by()
{
	$row = array(
		'id' => '11', 'first_name' => 'Ada', 'last_name' => 'Lane', 'company' => 'Acme', 'email_address' => 'ada@acme.test',
		'interest' => '80', 'fit' => '60', 'hot_at' => '1800000000', 'last_visit_at' => '1800000600',
		'reasons' => json_encode(array('interest' => array(array('kind' => 'form', 'points' => 20)), 'fit' => array(array('kind' => 'company', 'points' => 25)))),
		'topics' => json_encode(array(array('name' => 'Services'), array('name' => 'Chairs'))),
	);

	$off = api_lead_hot_present($row, 60, null);

	pg_assert_same(11, $off['id']);
	pg_assert_same(80, $off['score']);
	pg_assert_same(array('form'), $off['reasons'], 'only the interest reasons, as customer.hot');
	pg_assert_same('Services', $off['topic']);
	pg_assert_same(array('Services', 'Chairs'), $off['topics']);
	pg_assert_same(null, $off['followed_by'], 'null with the Workspace off');

	pg_assert_same(array(), api_lead_hot_present($row, 60, array())['followed_by'], 'nobody follows: an empty list');
	pg_assert_same(array(4, 2), api_lead_hot_present($row, 60, array('4', 2))['followed_by'], 'user ids in the column\'s order');
	pg_assert_same(array_keys(api_lead_hot_schema()), array_keys($off), 'schema and presenter match');
}

function test_api_leads_hot_page_sql()
{
	$sql = api_lead_hot_page_sql('', null, 50);

	pg_assert_contains('contact_scores.hot = 1', $sql);
	pg_assert_contains('ORDER BY contact_scores.interest DESC, contacts.id DESC', $sql);
	pg_assert_contains('LIMIT 51', $sql, 'one more tells there is a next page');

	$sql = api_lead_hot_page_sql('(contacts.id IN (1))', array('v' => 70, 'i' => 12), 10);

	pg_assert_contains('(contacts.id IN (1))', $sql, 'the user role\'s reach');
	pg_assert_contains("contact_scores.interest < '70' OR (contact_scores.interest = '70' AND contacts.id < '12')", $sql, 'keyset after the cursor');
}

function test_api_leads_sources()
{
	$group = array('key' => 'search', 'leads' => 4, 'converted' => 1, 'customers' => 2, 'revenue' => 5000, 'share' => 25);
	$present = api_lead_source_present('campaign', $group, false);

	pg_assert_same(null, $present['revenue'], 'revenue hidden without orders:read');
	pg_assert_same(5000, api_lead_source_present('campaign', $group, true)['revenue']);
	pg_assert_same(25, $present['share']);
	pg_assert_same(null, api_lead_source_present('campaign', array('share' => null) + $group, true)['share'], 'no lead, no share');
	pg_assert_same(array_keys(api_lead_source_schema()), array_keys($present), 'schema and presenter match');

	$report = api_lead_sources_report_present(array('first' => '2026-11-01', 'last' => '2026-11-24'), 'last', 'campaign',
		array('leads' => 4, 'converted' => 1, 'customers' => 2, 'revenue' => 5000, 'share' => 25), true, false, 'EUR');

	pg_assert_same('2026-11-01', $report['from']);
	pg_assert_same('last', $report['touch']);
	pg_assert_same(null, $report['currency'], 'no currency without revenue');
	pg_assert_same(null, $report['totals']['revenue']);
	pg_assert_same(array_keys(api_lead_sources_report_schema()), array_keys($report), 'schema and presenter match');
}

function test_api_leads_customer_score_fields()
{
	pg_assert_contains('LEFT JOIN contact_scores ON contact_scores.contact_id = contacts.id', api_customer_score_sql(true));
	pg_assert_contains('NULL AS lead_interest', api_customer_score_sql(false), 'an old site reads no table');
	pg_assert_same(array('Services', 'Chairs'), api_customer_topics(json_encode(array(array('name' => 'Services'), array('name' => ''), array('name' => 'Chairs')))));
	pg_assert_same(array(), api_customer_topics(null));

	$schema = api_customer_schema();

	pg_assert_same('integer?', $schema['interest_score']);
	pg_assert_same('string[]', $schema['topics']);
}
