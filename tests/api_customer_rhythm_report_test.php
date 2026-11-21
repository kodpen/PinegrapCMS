<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for GET /reports/customer-rhythm (includes/api/resources/reports.php):
 * the route, the page query by stage and cursor, who gets names, a customer
 * row and the report around it; and the contact group rule "Rhythm stage"
 * (includes/fn/audiences.php).
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/reports.php');

// The presenter's helpers live in includes/api/response.php, which loads only
// behind the API's own entry. Stand-ins of the same shape.
if (!function_exists('api_time')) {
	function api_time($unix)
	{
		$unix = (int) $unix;

		return ($unix <= 0) ? null : gmdate('Y-m-d\TH:i:s\Z', $unix);
	}
}

// The source of a function, comments left out.
function _api_rhythm_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

// A customer_rhythm row as the page query reads it.
function _api_rhythm_test_row(array $more = array())
{
	return array_merge(array(
		'id' => 41,
		'contact_id' => 7,
		'orders' => 3,
		'first_order_at' => 1790000000,
		'last_order_at' => 1795000000,
		'last_order_id' => 900,
		'interval_days' => 30,
		'avg_order' => 45000,
		'stage' => 2,
		'stage_before' => 1,
		'stage_at' => 1797000000,
		'chance' => 82,
		'expected_value' => 410000,
		'next_due_at' => 1797600000,
		'reminded_at' => 0,
		'held_back' => 0,
		'came_back_order_id' => 0,
		'order_number' => 1045,
		'name_ok' => 1,
		'contact_first_name' => 'Ayşe',
		'contact_last_name' => 'Kaya',
		'billing_first_name' => 'A.',
		'billing_last_name' => 'Kaya',
		'billing_company' => '',
	), $more);
}

function test_api_customer_rhythm_route()
{
	$route = null;

	foreach (api_schema() as $row) {
		if ($row['id'] === 'reports.customer_rhythm') {
			$route = $row;
		}
	}

	pg_assert_true($route !== null, 'declared');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/reports/customer-rhythm', $route['path']);
	pg_assert_same('orders:read', $route['scope']);
	pg_assert_same(array('list' => 'CustomerRhythmCustomer', 'rhythm' => 'CustomerRhythmReport'), $route['returns']);

	$params = array();

	foreach ($route['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_same(array('stage', 'contact_id', 'limit', 'cursor'), array_keys($params));
	pg_assert_same(api_customer_rhythm_stage_keys(), $params['stage']['values'], 'every stage and none');
	pg_assert_same(250, $params['limit']['max']);

	$handler = _api_rhythm_test_source('api_reports_customer_rhythm');

	pg_assert_contains("'customers:read'", $handler, 'names asked of customers:read');
	pg_assert_contains('pg_contact_owner_sql(', $handler, 'the user role reads names only in its reach');
	pg_assert_contains("api_fail(400, 'invalid_cursor'", $handler);
}

function test_api_customer_rhythm_stage_keys()
{
	pg_assert_same(array('regular', 'due', 'late', 'slipping', 'lost', 'won_back', 'none'), api_customer_rhythm_stage_keys());
	pg_assert_same('none', api_customer_rhythm_stage_key(0));
	pg_assert_same('won_back', api_customer_rhythm_stage_key(6));
	pg_assert_same('none', api_customer_rhythm_stage_key(9), 'an unknown code reads as none');
	pg_assert_same(0, api_customer_rhythm_stage_filter('none'));
	pg_assert_same(3, api_customer_rhythm_stage_filter('late'));
	pg_assert_same(null, api_customer_rhythm_stage_filter(''), 'no stage, no filter');
	pg_assert_same(null, api_customer_rhythm_stage_filter('nope'));
}

function test_api_customer_rhythm_page_query()
{
	list($sql, $params) = api_customer_rhythm_page_query(array('stage' => 2, 'limit' => 50));

	pg_assert_contains('FROM customer_rhythm LEFT JOIN orders ON orders.id = customer_rhythm.last_order_id', $sql);
	pg_assert_true(strpos($sql, 'contacts') === false, 'no contact read without names');
	pg_assert_true(strpos($sql, 'billing_first_name') === false, 'no billing name without names');
	pg_assert_true(strpos($sql, 'customer_key') === false, 'the key is never read');
	pg_assert_contains('WHERE (customer_rhythm.stage = ?)', $sql);
	pg_assert_contains('ORDER BY customer_rhythm.expected_value DESC, customer_rhythm.id DESC LIMIT 51', $sql, 'one more than the page');
	pg_assert_same(array(2), $params);

	list($sql, $params) = api_customer_rhythm_page_query(array('stage' => null, 'cursor' => array('v' => 410000, 'i' => 41), 'limit' => 10));
	pg_assert_contains('WHERE ((customer_rhythm.expected_value < ?) OR ((customer_rhythm.expected_value = ?) AND (customer_rhythm.id < ?)))', $sql, 'keyset after the cursor');
	pg_assert_same(array(410000, 410000, 41), $params);
	pg_assert_contains('LIMIT 11', $sql);

	list($sql, $params) = api_customer_rhythm_page_query(array('id' => 41, 'limit' => 1, 'names' => true, 'name_rule' => ''));
	pg_assert_contains('LEFT JOIN contacts ON contacts.id = customer_rhythm.contact_id', $sql);
	pg_assert_contains('1 AS name_ok', $sql, 'every name from the manager role up');
	pg_assert_contains('WHERE (customer_rhythm.id = ?)', $sql);
	pg_assert_same(array(41), $params);

	list($sql) = api_customer_rhythm_page_query(array('limit' => 5, 'names' => true, 'name_rule' => pg_contact_owner_sql(12, 'customer_rhythm.contact_id')));
	pg_assert_contains("(customer_rhythm.contact_id IN (SELECT contacts_contact_groups_xref.contact_id", $sql, 'the user role: names in its reach only');
	pg_assert_contains(') AS name_ok', $sql);
	pg_assert_true(strpos($sql, 'WHERE') === false || strpos($sql, 'WHERE') > strpos($sql, 'AS name_ok'), 'the rule names, it does not narrow the rows');
	pg_assert_contains('ORDER BY customer_rhythm.expected_value DESC', $sql);
}

function test_api_customer_rhythm_customer_present()
{
	$now = 1797700000;
	$row = _api_rhythm_test_row();

	$shown = api_customer_rhythm_customer_present($row, true, $now);
	pg_assert_same(41, $shown['id']);
	pg_assert_same(7, $shown['contact_id']);
	pg_assert_same('Ayşe Kaya', $shown['name'], 'the contact\'s name first');
	pg_assert_same('due', $shown['stage']);
	pg_assert_same('regular', $shown['stage_before']);
	pg_assert_same(api_time(1797000000), $shown['stage_changed_at']);
	pg_assert_same(1045, $shown['last_order_number']);
	pg_assert_same(31, $shown['days_since_last_order']);
	pg_assert_same(30, $shown['interval_days']);
	pg_assert_true($shown['own_interval']);
	pg_assert_same(82, $shown['chance']);
	pg_assert_same(410000, $shown['expected_value']);
	pg_assert_same(45000, $shown['average_order']);
	pg_assert_same(api_time(1797600000), $shown['next_due_at']);
	pg_assert_same(null, $shown['reminded_at']);
	pg_assert_false($shown['held_back']);
	pg_assert_same(null, $shown['came_back_order_id']);

	pg_assert_same(null, api_customer_rhythm_customer_present($row, false, $now)['name'], 'no customers:read, no name');
	pg_assert_same(null, api_customer_rhythm_customer_present(_api_rhythm_test_row(array('name_ok' => 0)), true, $now)['name'], 'outside the user role\'s reach');
	pg_assert_same('A. Kaya', api_customer_rhythm_customer_present(_api_rhythm_test_row(array('contact_first_name' => null, 'contact_last_name' => null)), true, $now)['name'], 'else the billing name');

	$bare = api_customer_rhythm_customer_present(array(
		'id' => 5, 'contact_id' => 0, 'orders' => 1, 'first_order_at' => 1795000000, 'last_order_at' => 1795000000, 'last_order_id' => 0,
		'interval_days' => 44, 'avg_order' => 9900, 'stage' => 0, 'stage_before' => 0, 'stage_at' => 0, 'chance' => 50,
		'expected_value' => 0, 'next_due_at' => 0, 'reminded_at' => 1796000000, 'held_back' => 1, 'came_back_order_id' => 912, 'order_number' => null,
	), true, $now);
	pg_assert_same(null, $bare['contact_id']);
	pg_assert_same(null, $bare['name'], 'no name columns read');
	pg_assert_same('none', $bare['stage']);
	pg_assert_same(null, $bare['stage_changed_at']);
	pg_assert_same(null, $bare['last_order_number']);
	pg_assert_false($bare['own_interval'], 'the store\'s median until a second order');
	pg_assert_same(api_time(1796000000), $bare['reminded_at']);
	pg_assert_true($bare['held_back']);
	pg_assert_same(912, $bare['came_back_order_id']);

	$declared = array_keys(api_customer_rhythm_customer_schema());
	pg_assert_same($declared, array_keys($shown), 'answer as declared');
}

function test_api_customer_rhythm_report_present()
{
	$pass = array('lease' => 0, 'started' => 1797000000, 'done' => 4000, 'total' => 9000);
	$waiting = api_customer_rhythm_present(null, $pass, true, 'TRY', array(), array());

	pg_assert_false($waiting['ready']);
	pg_assert_same(null, $waiting['as_of']);
	pg_assert_same(array('read' => 4000, 'total' => 9000), $waiting['progress']);
	pg_assert_same(array(), $waiting['stages']);
	pg_assert_same(array_keys(api_customer_rhythm_report_schema()), array_keys($waiting), 'answer as declared');

	$summary = array('at' => 1797500000, 'median' => 41.5, 'customers' => 120);
	$totals = array(1 => array(70, 900000), 2 => array(20, 300000), 5 => array(10, 0));
	$due = array(array('product_id' => 3, 'name' => 'Coffee 1 kg', 'quantity' => 2, 'last' => 1795000000, 'due' => 1797600000));
	$out = api_customer_rhythm_present($summary, array('lease' => 0, 'started' => 0, 'done' => 0, 'total' => 0), false, 'TRY', $totals, $due);

	pg_assert_true($out['ready']);
	pg_assert_same(api_time(1797500000), $out['as_of']);
	pg_assert_same(array('read' => 0, 'total' => 0), $out['progress']);
	pg_assert_false($out['names_shown']);
	pg_assert_same('TRY', $out['currency']);
	pg_assert_same(41.5, $out['store_median_days']);
	pg_assert_same(120, $out['customers']);
	pg_assert_same(6, count($out['stages']), 'every stage; none only when some have no stage');
	pg_assert_same(array('stage' => 'regular', 'customers' => 70, 'expected_value' => 900000), $out['stages'][0]);
	pg_assert_same(array('stage' => 'slipping', 'customers' => 0, 'expected_value' => 0), $out['stages'][3]);
	pg_assert_same(array(array('product_id' => 3, 'name' => 'Coffee 1 kg', 'quantity' => 2, 'last_bought_at' => api_time(1795000000), 'due_at' => api_time(1797600000))), $out['due']);

	$totals[0] = array(4, 0);
	$out = api_customer_rhythm_present($summary, $pass, true, 'TRY', $totals, array());
	pg_assert_same(array('stage' => 'none', 'customers' => 4, 'expected_value' => 0), $out['stages'][6], 'not yet known last');
}

function test_customer_rhythm_group_rule_clean_and_sql()
{
	$catalogue = pg_audience_conditions();
	pg_assert_true(isset($catalogue['rhythm_stage']), 'in the catalogue');
	pg_assert_same(array('eq', 'neq'), $catalogue['rhythm_stage']['ops']);
	pg_assert_same('stage', $catalogue['rhythm_stage']['value']);

	$clean = pg_audience_rules_clean(array('conditions' => array(
		array('key' => 'rhythm_stage', 'op' => 'eq', 'value' => 'slipping'),
		array('key' => 'rhythm_stage', 'op' => 'eq', 'value' => 'nope'),
		array('key' => 'rhythm_stage', 'op' => 'eq', 'value' => ''),
		array('key' => 'rhythm_stage', 'op' => 'eq', 'value' => 4),
	)));
	pg_assert_same(array(array('key' => 'rhythm_stage', 'op' => 'eq', 'value' => 'slipping')), $clean['conditions'], 'only a stage name');

	$is = pg_audience_sql(array('conditions' => array(array('key' => 'rhythm_stage', 'op' => 'eq', 'value' => 'lost'))));
	pg_assert_same("SELECT contacts.id FROM contacts WHERE ((contacts.id IN (SELECT customer_rhythm.contact_id FROM customer_rhythm WHERE customer_rhythm.contact_id <> 0 AND customer_rhythm.stage = ?)))", $is['sql']);
	pg_assert_same(array(5), $is['params'], 'the stage code');

	$not = pg_audience_sql(array('conditions' => array(array('key' => 'rhythm_stage', 'op' => 'neq', 'value' => 'won_back'))));
	pg_assert_contains('contacts.id NOT IN (SELECT customer_rhythm.contact_id', $not['sql']);
	pg_assert_same(array(6), $not['params']);

	$data = pg_audience_editor_data();
	pg_assert_same(array_keys(pg_customer_rhythm_stage_labels()), array_map(function ($o) { return $o['id']; }, $data['stages']), 'the editor\'s stage list');
}
