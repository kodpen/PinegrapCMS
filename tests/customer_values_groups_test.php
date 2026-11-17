<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the Customers report hands on (includes/fn/customer_values.php,
 * view_customer_report.php, GET /reports/customer-value): the rules a cell of
 * the groups table gives a contact group, the CSV of the top customers and
 * who may have it, and the API answer with and without names.
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
// behind the API's own entry and is stubbed by other tests. Stand-ins.
if (!function_exists('api_money')) {
	function api_money($minor_units)
	{
		return (int) $minor_units;
	}
}

if (!function_exists('api_time')) {
	function api_time($unix)
	{
		return (int) $unix > 0 ? gmdate('Y-m-d', (int) $unix) . 'T' . gmdate('H:i:s', (int) $unix) . 'Z' : null;
	}
}

// The source of a function, comments left out.
function cv_groups_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

// A small summary as the rebuild writes it.
function cv_groups_test_summary()
{
	$cells = array();

	for ($a = 0; $a <= 4; $a++) {
		$cells[$a] = array(array(0, 0), array(0, 0), array(0, 0));
	}

	$cells[0][2] = array(3, 90000);
	$cells[4][0] = array(5, 12000);

	return array(
		'v' => 1,
		'at' => 1795000000,
		'unit' => 'TRY',
		'orders' => 40,
		'customers' => 21,
		'unplaced' => 2,
		'tiles' => array('1' => array(4, 1, 6, 30000, 12.5), 'all' => array(21, 9, 40, 250000, 33.0)),
		'months' => array('2026-10' => array(2, 1000, 3, 4000), '2026-11' => array(1, 500, 0, 0)),
		'cohorts' => array('2026-10' => array(2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)),
		'cells' => $cells,
		'top' => array('all' => array(array(1045, 90000, 4), array(1046, 30000, 1)), 'year' => array(array(1045, 50000, 2))),
		'apart' => array('EUR' => array(3, 4500)),
	);
}

// Each cell hands over the last order's age between its row's bounds and
// the number of orders of its column, with conditions the editor knows.
function test_customer_value_cell_rules()
{
	pg_assert_same(array('match' => 'all', 'conditions' => array(
		array('key' => 'last_order', 'op' => 'within', 'value' => 30),
		array('key' => 'order_count', 'op' => 'eq', 'value' => 1),
	)), pg_customer_value_cell_rules(0, 0), 'within 30 days, one order');

	pg_assert_same(array('match' => 'all', 'conditions' => array(
		array('key' => 'last_order', 'op' => 'within', 'value' => 90),
		array('key' => 'last_order', 'op' => 'not_within', 'value' => 30),
		array('key' => 'order_count', 'op' => 'gte', 'value' => 2),
		array('key' => 'order_count', 'op' => 'lte', 'value' => 3),
	)), pg_customer_value_cell_rules(1, 1), '31 to 90 days, two to three orders');

	pg_assert_same(array('match' => 'all', 'conditions' => array(
		array('key' => 'last_order', 'op' => 'not_within', 'value' => 365),
		array('key' => 'order_count', 'op' => 'gte', 'value' => 4),
	)), pg_customer_value_cell_rules(4, 2), 'over 365 days, four or more');

	pg_assert_same(null, pg_customer_value_cell_rules(5, 0), 'no sixth row');
	pg_assert_same(null, pg_customer_value_cell_rules(0, 3), 'no fourth column');
	pg_assert_same(null, pg_customer_value_cell_rules(-1, 0), 'no row before the first');

	for ($age = 0; $age <= 4; $age++) {
		for ($band = 0; $band <= 2; $band++) {
			$rules = pg_customer_value_cell_rules($age, $band);

			pg_assert_same($rules, pg_audience_rules_clean($rules), $age . '-' . $band . ' is a clean rule set');

			$sql = pg_audience_sql($rules, 1795000000);
			pg_assert_false(strpos($sql['sql'], '1 = 0') !== false, $age . '-' . $band . ' selects somebody');
		}
	}
}

// Only "age-band" within the table is a cell.
function test_customer_value_cell_from()
{
	pg_assert_same(array(2, 1), pg_customer_value_cell_from('2-1'));
	pg_assert_same(array(4, 2), pg_customer_value_cell_from('4-2'));
	pg_assert_same(null, pg_customer_value_cell_from('5-0'), 'row out of the table');
	pg_assert_same(null, pg_customer_value_cell_from('0-3'), 'column out of the table');
	pg_assert_same(null, pg_customer_value_cell_from('1-1 '), 'trailing space');
	pg_assert_same(null, pg_customer_value_cell_from(array('1-1')), 'array');
	pg_assert_same(null, pg_customer_value_cell_from(null), 'nothing');
}

// The name starts with the cell's group and takes a number when taken.
function test_customer_value_group_name()
{
	$names = pg_customer_value_group_names();
	$name = pg_customer_value_cell_group_name(0, 2);

	pg_assert_true(strpos($name, $names['loyal']) === 0, 'starts with the group');

	pg_assert_same('Loyal', pg_customer_value_free_name('Loyal', array()), 'free');
	pg_assert_same('Loyal (2)', pg_customer_value_free_name('Loyal', array('loyal')), 'taken, case aside');
	pg_assert_same('Loyal (3)', pg_customer_value_free_name('Loyal', array('Loyal', 'Loyal (2)')), 'next number');

	$long = str_repeat('ş', 120);
	pg_assert_same(100, mb_strlen(pg_customer_value_free_name($long, array())), 'cut to 100 characters');
	pg_assert_same(100, mb_strlen(pg_customer_value_free_name($long, array(mb_substr($long, 0, 100)))), 'cut with its number');
}

// A group is made from the manager role up, by whoever opens contacts, and
// filled by rules.
function test_customer_value_group_rights()
{
	$source = cv_groups_test_source('pg_customer_value_may_make_groups');

	pg_assert_true(strpos($source, 'pg_audience_ready()') !== false, 'after the upgrade');
	pg_assert_true(strpos($source, "< 3") !== false, 'manager role up');
	pg_assert_true(strpos($source, 'validate_contacts_access(') !== false, 'contacts right');

	$create = cv_groups_test_source('pg_customer_value_group_create');

	pg_assert_true(strpos($create, "'dynamic'") !== false, 'filled by rules');
	pg_assert_true(strpos($create, 'pg_audience_rules_clean(') !== false, 'rules stored clean');

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/view_customer_report.php');
	$post = substr($screen, strpos($screen, "REQUEST_METHOD'] === 'POST'"));

	pg_assert_true(strpos($post, 'validate_token_field()') < strpos($post, 'pg_customer_value_group_create('), 'token checked first');
	pg_assert_true(strpos($post, '$may_make_groups') < strpos($post, 'pg_customer_value_group_create('), 'right checked first');
}

// The CSV names the customers; the screen refuses it without the contacts
// right before anything is read or sent.
function test_customer_value_top_csv()
{
	$names = array(1045 => array('id' => 7, 'name' => '=Ayşe Kaya'), 1046 => array('id' => 8, 'name' => 'Ali "Can"'));
	$lines = pg_customer_value_top_csv(cv_groups_test_summary(), $names);

	pg_assert_same(array(
		'list,rank,customer,last_order_number,orders,value,currency',
		'all,1,"\'=Ayşe Kaya",1045,4,900.00,TRY',
		'all,2,"Ali ""Can""",1046,1,300.00,TRY',
		'year,1,"\'=Ayşe Kaya",1045,2,500.00,TRY',
	), $lines);

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/view_customer_report.php');
	$csv = substr($screen, strpos($screen, "=== 'top'"));

	pg_assert_true(strpos($csv, '!$may_see_names') < strpos($csv, 'pg_csv_download_start('), 'refused without the contacts right');
	pg_assert_true(strpos($csv, '!$may_see_names') < strpos($csv, 'pg_customer_value_order_names('), 'no name read before the check');
}

// The route reads orders; names need customers:read as well.
function test_customer_value_api_route()
{
	$route = null;

	foreach (api_schema() as $row) {
		if ($row['id'] === 'reports.customer_value') {
			$route = $row;
		}
	}

	pg_assert_true($route !== null, 'declared');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/reports/customer-value', $route['path']);
	pg_assert_same('orders:read', $route['scope']);
	pg_assert_same('CustomerValueReport', $route['returns']);

	$handler = cv_groups_test_source('api_reports_customer_value');

	pg_assert_true(strpos($handler, "'customers:read'") !== false, 'names asked of customers:read');
	pg_assert_true(strpos($handler, '$names_shown') < strpos($handler, 'pg_customer_value_order_names('), 'names read only when shown');
}

// The answer: names only when shown, cells with their group, a summary not
// built yet as ready false.
function test_customer_value_api_answer()
{
	$summary = cv_groups_test_summary();
	$names = array(1045 => array('id' => 7, 'name' => 'Ayşe Kaya'));
	$pass = array('lease' => 0, 'started' => 0, 'done' => 0, 'total' => 0);

	$shown = api_customer_value_present($summary, $pass, true, $names);

	pg_assert_true($shown['ready']);
	pg_assert_same(api_time(1795000000), $shown['as_of']);
	pg_assert_same('TRY', $shown['currency']);
	pg_assert_same(2, $shown['not_placed']);
	pg_assert_same(array(array('currency' => 'EUR', 'orders' => 3, 'value' => 4500)), $shown['apart']);
	pg_assert_same(array('period' => 'all', 'customers' => 21, 'repeat_customers' => 9, 'orders' => 40, 'value' => 250000, 'days_between_orders' => 33.0), $shown['periods'][1]);
	pg_assert_same(array('month' => '2026-10', 'customers' => 2, 'ordered_again' => array(1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)), $shown['cohorts'][0]);
	pg_assert_same(15, count($shown['groups']), 'every cell');
	pg_assert_same(array('last_order' => 'within_30_days', 'orders' => '4+', 'group' => 'loyal', 'customers' => 3, 'value' => 90000), $shown['groups'][2]);
	pg_assert_same(array('last_order' => 'over_365_days', 'orders' => '1', 'group' => 'lost', 'customers' => 5, 'value' => 12000), $shown['groups'][12]);
	pg_assert_same('Ayşe Kaya', $shown['top']['all'][0]['name']);
	pg_assert_same(null, $shown['top']['all'][1]['name'], 'an order not found has no name');

	$hidden = api_customer_value_present($summary, $pass, false, $names);

	pg_assert_false($hidden['names_shown']);
	pg_assert_same(null, $hidden['top']['all'][0]['name'], 'no name without customers:read');
	pg_assert_same(1045, $hidden['top']['all'][0]['last_order_number']);

	$waiting = api_customer_value_present(null, array('lease' => 1, 'started' => 1795000000, 'done' => 4000, 'total' => 9000), true, array());

	pg_assert_false($waiting['ready']);
	pg_assert_same(array('read' => 4000, 'total' => 9000), $waiting['progress']);
	pg_assert_same(array(), $waiting['groups']);

	// Every key the document declares is answered, both ways.
	$declared = array_keys(api_customer_value_report_schema());

	pg_assert_same($declared, array_keys($shown), 'ready answer');
	pg_assert_same($declared, array_keys($waiting), 'waiting answer');
}
