<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the numbers and texts a flow compares: a condition box's typed
 * number and a constants box's number are read as the site writes them
 * (ws_flow_typed_number()), machine numbers of the run keep their value, an
 * unreadable number is a problem at publish, and "is" and "contains" match
 * Turkish capitals and dotted or dotless i (ws_scheduled_compare()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_values.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');

// Runs $check with the site writing 1.234,5 ($comma) or 1,234.5, then puts
// the language back as it was.
function ws_flow_site_numbers_test_as($comma, $check)
{
	$was = ws_calc_decimal_comma();

	ws_calc_decimal_comma($comma);

	try {
		$check();
	} finally {
		ws_calc_decimal_comma($was);
	}
}

// A value rule of {{node.a.total}} against the run's value $current.
function ws_flow_site_numbers_test_holds($current, $op, $value)
{
	return ws_flow_condition_value_holds(
		array('type' => 'value', 'ref' => '{{node.a.total}}', 'op' => $op, 'value' => $value),
		array('a' => array('total' => $current))
	);
}

// A typed number as the site writes it; a machine number keeps its value.
function test_workspace_flow_typed_number()
{
	ws_flow_site_numbers_test_as(true, function () {
		pg_assert_same(1000.0, ws_flow_typed_number('1.000'));
		pg_assert_same(1000.5, ws_flow_typed_number('1.000,50'));
		pg_assert_same(1250.0, ws_flow_typed_number('1.250'));
		pg_assert_same(1.5, ws_flow_typed_number('1,5'));
		pg_assert_same(-1000.0, ws_flow_typed_number('-1.000'));
		pg_assert_same(1500.0, ws_flow_typed_number('1500'));
		pg_assert_same(1.5, ws_flow_typed_number('1.5'), 'a dot not before three digits is a machine decimal');
		pg_assert_same(0.125, ws_flow_typed_number('0.125'), 'nobody groups the thousands of 0');
		pg_assert_same(1000.5, ws_flow_typed_number('1.000.50'), 'a flow stored before kept a typed comma as a dot');
		pg_assert_same(null, ws_flow_typed_number('abc'));
		pg_assert_same(null, ws_flow_typed_number(''));
	});

	ws_flow_site_numbers_test_as(false, function () {
		pg_assert_same(1.0, ws_flow_typed_number('1.000'));
		pg_assert_same(1000.5, ws_flow_typed_number('1,000.50'));
		pg_assert_same(1500.0, ws_flow_typed_number('1500'));
		pg_assert_same(0.125, ws_flow_typed_number('0.125'));
		pg_assert_same(null, ws_flow_typed_number('abc'));
	});
}

// What the canvas stores comes back with its value: a value rule's number
// as typed, read the site's way; a record rule's decimal as the machine
// number the canvas read it as (siteNumber() in workspace_flow.js).
function test_workspace_flow_typed_number_from_the_canvas()
{
	ws_flow_site_numbers_test_as(true, function () {
		pg_assert_same(1.5, ws_flow_typed_number('1,500'), 'typed 1,500 is one and a half');
		pg_assert_same(1.5, ws_flow_typed_number('1,5'));
		pg_assert_same(1.5, ws_flow_typed_number('1.5'));
		pg_assert_same(1250.5, ws_flow_typed_number('1.250,5'));
		pg_assert_same(1500.0, ws_flow_typed_number('1500'));
		pg_assert_true(ws_flow_site_numbers_test_holds('2', 'gt', '1,500')['holds'], '2 is more than 1,500 in Turkish');
		pg_assert_true(ws_scheduled_compare('2', 'gt', '1.5', 'decimal'), 'a record decimal kept as 1.5');
		pg_assert_true(!ws_scheduled_compare('1499', 'gt', '1500', 'decimal'), 'and as 1500');
	});

	ws_flow_site_numbers_test_as(false, function () {
		pg_assert_same(1500.0, ws_flow_typed_number('1,500'));
		pg_assert_same(1.5, ws_flow_typed_number('1.5'));
		pg_assert_same(1500.0, ws_flow_typed_number('1500'));
	});
}

// The issue's table: the typed side read the Turkish way, the run's
// machine number as it is; the sentence shows the number as read.
function test_workspace_flow_condition_reads_site_numbers()
{
	ws_flow_site_numbers_test_as(true, function () {
		pg_assert_false(ws_flow_site_numbers_test_holds('500', 'gt', '1.000')['holds'], '500 > 1.000 is not met');
		pg_assert_true(ws_flow_site_numbers_test_holds('1500', 'gt', '1.000,50')['holds'], '1500 > 1.000,50 is met');
		pg_assert_true(ws_flow_site_numbers_test_holds('1500', 'gte', '1000')['holds'], '1500 >= 1000 is met');
		pg_assert_true(ws_flow_site_numbers_test_holds('1.250,50', 'gt', '1.000')['holds'], 'a run value written the Turkish way');
		pg_assert_false(ws_flow_site_numbers_test_holds('', 'lt', '5')['holds'], 'an empty value is no number');
		pg_assert_contains('is more than 1.000,5', ws_flow_site_numbers_test_holds('1500', 'gt', '1.000,50')['text']);
	});

	ws_flow_site_numbers_test_as(false, function () {
		pg_assert_true(ws_flow_site_numbers_test_holds('500', 'gt', '1.000')['holds'], 'English: 1.000 is one');
		pg_assert_true(ws_flow_site_numbers_test_holds('1500', 'gte', '1000')['holds']);
		pg_assert_true(ws_flow_site_numbers_test_holds('1500.5', 'gt', '1500.25')['holds'], 'stored machine numbers');
		pg_assert_false(ws_flow_site_numbers_test_holds('503', 'lt', '99')['holds']);
		pg_assert_contains('is more than 1,000 (it was "1500")', ws_flow_site_numbers_test_holds('1500', 'gt', '1000')['text']);
	});
}

// A typed number that cannot be read stops the publish and fails the box
// at run time, never a silent no.
function test_workspace_flow_condition_unreadable_number()
{
	$config = function ($op, $value) {
		return ws_flow_condition_normalize(array('rule' => array('type' => 'value', 'ref' => '{{node.a.total}}', 'op' => $op, 'value' => $value)));
	};

	ws_flow_site_numbers_test_as(true, function () use ($config) {
		pg_assert_same(1, count(ws_flow_condition_problems($config('gt', 'bin'), 'Check')));
		pg_assert_contains('not a number', ws_flow_condition_problems($config('gt', 'bin'), 'Check')[0]);
		pg_assert_same(array(), ws_flow_condition_problems($config('gt', '1.000,50'), 'Check'));
		pg_assert_same(array(), ws_flow_condition_problems($config('eq', 'bin'), 'Check'), 'only a size needs a number');
		pg_assert_false(ws_flow_condition_check(array(), $config('lte', '1,2,3'))['ok']);
		pg_assert_true(ws_flow_condition_check(array(), $config('lte', '1.000'))['ok']);
	});
}

// A constants box's number is read the site's way and given as a machine
// number; one that cannot be read is a problem.
function test_workspace_flow_constants_read_site_numbers()
{
	$graph = function ($value) {
		return array('nodes' => array(array('id' => 'c', 'kind' => 'constants', 'config' => array('rows' => array(
			array('key' => 'limit', 'type' => 'number', 'value' => $value),
			array('key' => 'code', 'type' => 'text', 'value' => '1.250'),
		)))));
	};
	$problems = function ($value) {
		return ws_flow_constants_problems(ws_flow_constants_normalize(array('rows' => array(array('key' => 'limit', 'type' => 'number', 'value' => $value)))), 'Values');
	};

	ws_flow_site_numbers_test_as(true, function () use ($graph, $problems) {
		pg_assert_same('1250', ws_flow_pure_values($graph('1.250'))['c']['limit']);
		pg_assert_same('1250.5', ws_flow_pure_values($graph('1.250,50'))['c']['limit']);
		pg_assert_same('1.250', ws_flow_pure_values($graph('1.250'))['c']['code'], 'a text stays as typed');
		pg_assert_same(array(), $problems('1.250,50'));
		pg_assert_same(1, count($problems('1,2,3')));
	});

	ws_flow_site_numbers_test_as(false, function () use ($graph, $problems) {
		pg_assert_same('1.25', ws_flow_pure_values($graph('1.250'))['c']['limit']);
		pg_assert_same('1500', ws_flow_pure_values($graph('1500'))['c']['limit']);
		pg_assert_same(array(), $problems('1,250.50'));
		pg_assert_same(1, count($problems('abc')));
	});
}

// "is" and "contains" fold Turkish capitals and dotted or dotless i: the
// condition box, the derived compare and scheduled record conditions.
function test_workspace_compare_folds_turkish_letters()
{
	$pairs = array(
		array('AYDIN', 'eq', 'Aydın'),
		array('KIRKLARELİ', 'eq', 'Kırklareli'),
		array('İSTANBUL', 'eq', 'istanbul'),
		array('İzmir', 'eq', 'izmir'),
		array('Kırıkkale Şubesi', 'contains', 'KIRIKKALE'),
		array('İzmir', 'contains', 'izmir'),
		array('ISTANBUL', 'contains', 'istanbul'),
		array('ANKARA', 'eq', 'Ankara'),
	);

	foreach ($pairs as $pair) {
		pg_assert_true(ws_scheduled_compare($pair[0], $pair[1], $pair[2], 'text'), $pair[0] . ' ' . $pair[1] . ' ' . $pair[2]);
	}

	pg_assert_false(ws_scheduled_compare('İzmir', 'neq', 'IZMIR', 'text'));
	pg_assert_true(ws_scheduled_compare('İzmir', 'neq', 'Ankara', 'text'));
	pg_assert_false(ws_scheduled_compare('Ankara', 'contains', 'İzmir', 'text'));
	pg_assert_false(ws_scheduled_compare('Ankara', 'contains', '', 'text'), 'an empty needle holds nothing');
	pg_assert_true(ws_flow_site_numbers_test_holds('İzmir', 'eq', 'izmir')['holds']);
	pg_assert_same('1', ws_flow_derived_value(ws_flow_derived_normalize(array('rule' => 'compare', 'a' => 'x', 'b' => 'y', 'op' => 'contains')), 'ISTANBUL Şube', 'istanbul'));
}
