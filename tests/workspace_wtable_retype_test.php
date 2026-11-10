<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for changing the type of a workflow table column
 * (includes/workspace/wtable_retype.php): the types that change, one cell
 * converted between each pair of them, the cells of a table's rows
 * converted or listed when they do not convert, and the type asked for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// A column of a type, its options checked as a write checks them.
function ws_wtable_retype_test_column($type, $options = array(), $id = 4)
{
	return array('id' => $id, 'type' => $type, 'options' => ws_wtable_column_options_check($type, $options)['value']);
}

// One cell converted: the value kept, or the error.
function ws_wtable_retype_test($from, $to, $value, $from_options = array(), $to_options = array())
{
	$cell = ws_wtable_retype_cell(ws_wtable_retype_test_column($from, $from_options), ws_wtable_retype_test_column($to, $to_options), $value);

	return $cell['ok'] ? $cell['value'] : 'error:' . $cell['error'];
}

// Runs a test with the site's decimal mark a comma or a point.
function ws_wtable_retype_test_in($comma, $test)
{
	$own = ws_calc_decimal_comma();
	ws_calc_decimal_comma($comma);

	try {
		$test();
	} finally {
		ws_calc_decimal_comma($own);
	}
}

function test_workspace_wtable_retype_types()
{
	pg_assert_same(array('text', 'number', 'money', 'date', 'list', 'checkbox'), ws_wtable_retype_types());
	pg_assert_true(ws_wtable_retype_is_change(array('type' => 'text'), array('type' => 'number')));
	pg_assert_false(ws_wtable_retype_is_change(array('type' => 'text'), array('type' => 'text')), 'the same type');
	pg_assert_true(ws_wtable_retype_is_change(array('type' => 'list', 'options' => array('of' => 'text')), array('type' => 'list', 'options' => array('of' => 'number'))), 'texts into numbers');
	pg_assert_false(ws_wtable_retype_is_change(array('type' => 'status'), array('type' => 'text')), 'a status does not change');
	pg_assert_false(ws_wtable_retype_is_change(array('type' => 'text'), array('type' => 'formula')));
}

// What a type change asks for: a type that changes, options it takes.
function test_workspace_wtable_retype_input()
{
	$text = ws_wtable_retype_test_column('text');

	$check = ws_wtable_retype_input_check($text, array('type' => 'money', 'options' => array('currency' => 'eur')));
	pg_assert_true($check['ok']);
	pg_assert_same(array('id' => 4, 'type' => 'money', 'options' => array('decimals' => 2, 'currency' => 'EUR')), $check['to']);
	pg_assert_same('type_same', ws_wtable_retype_input_check($text, array('type' => 'text'))['error']);
	pg_assert_same('type_unknown', ws_wtable_retype_input_check($text, array('type' => 'person'))['error']);
	pg_assert_same('type_not_changeable', ws_wtable_retype_input_check(ws_wtable_retype_test_column('status', array('choices' => array(array('key' => 'a', 'label' => 'A')))), array('type' => 'text'))['error']);
	pg_assert_same('decimals_invalid', ws_wtable_retype_input_check($text, array('type' => 'number', 'options' => array('decimals' => 9)))['error']);
	pg_assert_same('options', ws_wtable_retype_input_check($text, array('type' => 'number', 'options' => array('decimals' => 9)))['field']);
}

// An empty cell stays empty into every type; an unticked box is empty.
function test_workspace_wtable_retype_empty_cells()
{
	foreach (ws_wtable_retype_types() as $from) {
		foreach (ws_wtable_retype_types() as $to) {
			if ($from !== $to) {
				$kept = ws_wtable_retype_test($from, $to, null);
				pg_assert_true(($kept === null) || ($kept === false), $from . ' into ' . $to);
			}
		}
	}

	pg_assert_same(null, ws_wtable_retype_test('checkbox', 'text', false));
}

// Text read the site's way, as the file import reads it.
function test_workspace_wtable_retype_from_text()
{
	ws_wtable_retype_test_in(true, function () {
		pg_assert_same(1250, ws_wtable_retype_test('text', 'number', ' 1.250 '), 'a Turkish thousands point');
		pg_assert_same(1.5, ws_wtable_retype_test('text', 'number', '1,5', array(), array('decimals' => 2)));
		pg_assert_same(2, ws_wtable_retype_test('text', 'number', '1,5'), 'rounded to the column\'s decimals');
		pg_assert_same('error:not_number', ws_wtable_retype_test('text', 'number', '12 kg'));
		pg_assert_same(125075, ws_wtable_retype_test('text', 'money', '₺1.250,75'), 'minor units');
		pg_assert_same(29, ws_wtable_retype_test('text', 'money', '0,29'), 'rounded, not cut');
		pg_assert_same('error:not_minor_units', ws_wtable_retype_test('text', 'money', 'free'));
		pg_assert_same(array(1.5, 2), ws_wtable_retype_test('text', 'list', "1,5\n\n2", array(), array('of' => 'number')));
		pg_assert_same('error:not_number', ws_wtable_retype_test('text', 'list', "1\nmany", array(), array('of' => 'number')));
	});

	pg_assert_same('2026-11-10', ws_wtable_retype_test('text', 'date', '10.11.2026'));
	pg_assert_same('2026-11-10 14:30', ws_wtable_retype_test('text', 'date', '2026-11-10 14:30', array(), array('time' => true)));
	pg_assert_same('2026-11-10 00:00', ws_wtable_retype_test('text', 'date', '2026-11-10', array(), array('time' => true)));
	pg_assert_same('error:time_lost', ws_wtable_retype_test('text', 'date', '2026-11-10 14:30'), 'a time would be lost');
	pg_assert_same('error:not_date', ws_wtable_retype_test('text', 'date', 'tomorrow'));
	pg_assert_same(true, ws_wtable_retype_test('text', 'checkbox', 'Evet'));
	pg_assert_same(false, ws_wtable_retype_test('text', 'checkbox', '0'));
	pg_assert_same('error:not_checkbox', ws_wtable_retype_test('text', 'checkbox', 'maybe'));
	pg_assert_same(array('Red', 'Blue'), ws_wtable_retype_test('text', 'list', "Red\nBlue"), 'one item a line');
	pg_assert_same('error:too_long', ws_wtable_retype_test('list', 'text', array(str_repeat('a', 1000), str_repeat('b', 1000), str_repeat('c', 1000), str_repeat('d', 1000), str_repeat('e', 1000), str_repeat('f', 1000), str_repeat('g', 1000), str_repeat('h', 1000), str_repeat('i', 1000), str_repeat('j', 1000), 'k')), 'the store\'s own check');
}

// Numbers and amounts into text the site's way, so they read back the same.
function test_workspace_wtable_retype_from_numbers()
{
	ws_wtable_retype_test_in(true, function () {
		pg_assert_same('1234,5', ws_wtable_retype_test('number', 'text', 1234.5, array('decimals' => 2)), 'no grouping');
		pg_assert_same('12,50', ws_wtable_retype_test('money', 'text', 1250), 'money keeps its decimals');
		pg_assert_same(1234.5, ws_wtable_retype_test('text', 'number', ws_wtable_retype_test('number', 'text', 1234.5, array('decimals' => 2)), array(), array('decimals' => 2)), 'and back');
		pg_assert_same(array('12,50'), ws_wtable_retype_test('money', 'list', 1250));
	});

	pg_assert_same(12.5, ws_wtable_retype_test('money', 'number', 1250, array(), array('decimals' => 2)));
	pg_assert_same(13, ws_wtable_retype_test('money', 'number', 1250), 'to the number\'s decimals');
	pg_assert_same(1250, ws_wtable_retype_test('number', 'money', 12.5, array('decimals' => 2)));
	pg_assert_same(12, ws_wtable_retype_test('number', 'money', 12, array(), array('decimals' => 0)));
	pg_assert_same(true, ws_wtable_retype_test('number', 'checkbox', 1));
	pg_assert_same(false, ws_wtable_retype_test('number', 'checkbox', 0));
	pg_assert_same('error:not_checkbox', ws_wtable_retype_test('number', 'checkbox', 2));
	pg_assert_same(true, ws_wtable_retype_test('money', 'checkbox', 100), 'one unit');
	pg_assert_same('error:not_checkbox', ws_wtable_retype_test('money', 'checkbox', 1));
	pg_assert_same('error:no_conversion', ws_wtable_retype_test('number', 'date', 20261110));
	pg_assert_same(array(12.5), ws_wtable_retype_test('money', 'list', 1250, array(), array('of' => 'number')));
	pg_assert_same(array(3), ws_wtable_retype_test('number', 'list', 3, array(), array('of' => 'number')));
}

// A date, a ticked box and a list into the other types.
function test_workspace_wtable_retype_from_dates_boxes_and_lists()
{
	pg_assert_same('2026-11-10 14:30', ws_wtable_retype_test('date', 'text', '2026-11-10 14:30', array('time' => true)));
	pg_assert_same('error:no_conversion', ws_wtable_retype_test('date', 'number', '2026-11-10'));
	pg_assert_same('error:no_conversion', ws_wtable_retype_test('date', 'checkbox', '2026-11-10'));
	pg_assert_same(array('2026-11-10'), ws_wtable_retype_test('date', 'list', '2026-11-10'));
	pg_assert_same('error:not_number', ws_wtable_retype_test('date', 'list', '2026-11-10', array(), array('of' => 'number')));

	pg_assert_same('1', ws_wtable_retype_test('checkbox', 'text', true));
	pg_assert_same(1, ws_wtable_retype_test('checkbox', 'number', true));
	pg_assert_same(100, ws_wtable_retype_test('checkbox', 'money', true));
	pg_assert_same(array(1), ws_wtable_retype_test('checkbox', 'list', true, array(), array('of' => 'number')));
	pg_assert_same('error:no_conversion', ws_wtable_retype_test('checkbox', 'date', true));

	pg_assert_same("Red\nBlue", ws_wtable_retype_test('list', 'text', array('Red', 'Blue')), 'one item a line');
	pg_assert_same(7, ws_wtable_retype_test('list', 'number', array(7), array('of' => 'number')), 'a list of one item');
	pg_assert_same('error:list_many', ws_wtable_retype_test('list', 'number', array(7, 8), array('of' => 'number')));
	pg_assert_same('2026-11-10', ws_wtable_retype_test('list', 'date', array('10.11.2026')));
	pg_assert_same(true, ws_wtable_retype_test('list', 'checkbox', array('yes')));
	pg_assert_same(array(1, 2.5), ws_wtable_retype_test('list', 'list', array('1', '2.5'), array('of' => 'text'), array('of' => 'number')), 'texts into numbers');
	pg_assert_same('error:not_number', ws_wtable_retype_test('list', 'list', array('1', 'two'), array('of' => 'text'), array('of' => 'number')));

	ws_wtable_retype_test_in(false, function () {
		pg_assert_same(array('1', '2.5'), ws_wtable_retype_test('list', 'list', array(1, 2.5), array('of' => 'number'), array('of' => 'text')), 'numbers into texts');
		pg_assert_same("1\n2.5", ws_wtable_retype_test('list', 'text', array(1, 2.5), array('of' => 'number')));
	});
}

// Over a table's rows: the cells that change, and each that does not
// convert with its row, binned rows too.
function test_workspace_wtable_retype_rows()
{
	$from = ws_wtable_retype_test_column('text', array(), 3);
	$to = ws_wtable_retype_test_column('number', array(), 3);
	$rows = array(
		array('id' => 11, 'label' => 1, 'cells' => array('3' => '12', '5' => 'x')),
		array('id' => 12, 'label' => 2, 'cells' => array('5' => 'no cell of the column')),
		array('id' => 13, 'label' => 4, 'cells' => array('3' => 'twelve kilos and some more words that make this value longer than the list shows it, cut')),
		array('id' => 2, 'label' => 3, 'bin' => true, 'cells' => array('3' => 'n/a')),
	);

	$plan = ws_wtable_retype_rows($from, $to, $rows);
	pg_assert_same(array('row:11' => 12), $plan['changed']);
	pg_assert_same(array('row:13', 'bin:2'), array_column($plan['faults'], 'key'));
	pg_assert_same(array(4, 3), array_column($plan['faults'], 'label'));
	pg_assert_same(array(false, true), array_column($plan['faults'], 'bin'));
	pg_assert_same('not_number', $plan['faults'][1]['error']);
	pg_assert_same(80, mb_strlen($plan['faults'][0]['value']), 'a long value is cut');

	$plan = ws_wtable_retype_rows(ws_wtable_retype_test_column('checkbox', array(), 3), ws_wtable_retype_test_column('text', array(), 3), array(array('id' => 1, 'cells' => array('3' => true))));
	pg_assert_same(array('row:1' => '1'), $plan['changed']);

	$plan = ws_wtable_retype_rows(ws_wtable_retype_test_column('number', array(), 3), ws_wtable_retype_test_column('checkbox', array(), 3), array(array('id' => 1, 'cells' => array('3' => 0))));
	pg_assert_same(array('row:1' => null), $plan['changed'], 'an unticked box is an empty cell');

	pg_assert_same('a, 2', ws_wtable_retype_shown(array('a', 2)));
	pg_assert_same('order:5', ws_wtable_retype_shown(array('kind' => 'order', 'id' => 5)));
}

// The module is loaded with the workspace.
function test_workspace_wtable_retype_is_loaded()
{
	pg_assert_same(1, substr_count((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php'), "require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_retype.php');"));
}
