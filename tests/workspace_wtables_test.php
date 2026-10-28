<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure part of workflow tables (includes/workspace/wtables.php):
 * the column types, the options each takes, one cell checked against its
 * column, a row's cells checked and merged, the limits, and the cells as
 * JSON.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');

// A column of a type, its options checked as a write would check them.
function ws_wtables_test_column($type, $options = array())
{
	$check = ws_wtable_column_options_check($type, $options);

	return array('type' => $type, 'options' => $check['value']);
}

function ws_wtables_test_cell($type, $value, $options = array())
{
	return ws_wtable_cell_check(ws_wtables_test_column($type, $options), $value);
}

// The nine types, and which of them keep cells.
function test_workspace_wtables_types()
{
	pg_assert_same(array('text', 'number', 'money', 'date', 'person', 'status', 'checkbox', 'link', 'formula'), array_keys(ws_wtable_column_types()));
	pg_assert_true(ws_wtable_type_stores('money'));
	pg_assert_false(ws_wtable_type_stores('formula'), 'a formula is worked out');
	pg_assert_false(ws_wtable_type_stores('rating'), 'an unknown type');
}

// One more column, row or table fits under its limit and not at it.
function test_workspace_wtables_limits()
{
	pg_assert_true(ws_wtable_limit_allows('columns', 49));
	pg_assert_false(ws_wtable_limit_allows('columns', 50));
	pg_assert_true(ws_wtable_limit_allows('rows', 4999));
	pg_assert_false(ws_wtable_limit_allows('rows', 5000));
	pg_assert_false(ws_wtable_limit_allows('tables', 500));
	pg_assert_false(ws_wtable_limit_allows('cells', 0), 'an unknown limit');
}

function test_workspace_wtables_names()
{
	pg_assert_same('Leads', ws_wtable_name_check("  Leads\n")['value']);
	pg_assert_same('a b', ws_wtable_name_check("a\tb")['value'], 'a control character becomes a space');
	pg_assert_same('name_empty', ws_wtable_name_check('   ')['error']);
	pg_assert_same('name_empty', ws_wtable_name_check(array('x'))['error']);
	pg_assert_true(ws_wtable_name_check(str_repeat('ş', 120))['ok'], '120 characters, not bytes');
	pg_assert_same('name_too_long', ws_wtable_name_check(str_repeat('a', 121))['error']);
}

// Each type's options and their defaults; refused ones name the reason.
function test_workspace_wtables_column_options()
{
	pg_assert_same(array(), ws_wtable_column_options_check('text', array('x' => 1))['value'], 'text takes none');
	pg_assert_same(array('decimals' => 0), ws_wtable_column_options_check('number', null)['value']);
	pg_assert_same('decimals_invalid', ws_wtable_column_options_check('number', array('decimals' => 7))['error']);
	pg_assert_same('decimals_invalid', ws_wtable_column_options_check('number', array('decimals' => 1.5))['error']);
	pg_assert_same(array('decimals' => 2, 'currency' => 'TRY'), ws_wtable_column_options_check('money', array('currency' => 'try'))['value']);
	pg_assert_same('currency_invalid', ws_wtable_column_options_check('money', array('currency' => 'TL'))['error']);
	pg_assert_same('decimals_invalid', ws_wtable_column_options_check('money', array('decimals' => 4))['error']);
	pg_assert_same(array('time' => true), ws_wtable_column_options_check('date', array('time' => 1))['value']);
	pg_assert_same('type_unknown', ws_wtable_column_options_check('rating', array())['error']);

	$status = ws_wtable_column_options_check('status', array('choices' => array(array('key' => 'new', 'label' => ' New ', 'color' => '#AABBCC'))));
	pg_assert_same(array('choices' => array(array('key' => 'new', 'label' => 'New', 'color' => '#aabbcc'))), $status['value']);
	pg_assert_same('choices_empty', ws_wtable_column_options_check('status', array())['error']);
	pg_assert_same('choice_key_invalid', ws_wtable_column_options_check('status', array('choices' => array(array('key' => 'a', 'label' => 'A'), array('key' => 'a', 'label' => 'B'))))['error'], 'a key twice');
	pg_assert_same('choice_label_invalid', ws_wtable_column_options_check('status', array('choices' => array(array('key' => 'a', 'label' => ''))))['error']);
	pg_assert_same('choice_color_invalid', ws_wtable_column_options_check('status', array('choices' => array(array('key' => 'a', 'label' => 'A', 'color' => 'red'))))['error']);

	$many = array();

	for ($i = 0; $i < 51; $i++) {
		$many[] = array('key' => 's' . $i, 'label' => 'S' . $i);
	}

	pg_assert_same('choices_too_many', ws_wtable_column_options_check('status', array('choices' => $many))['error']);

	pg_assert_same(array('kinds' => array('order', 'product')), ws_wtable_column_options_check('link', array('kinds' => array('order', 'product', 'order')))['value']);
	pg_assert_same('kinds_invalid', ws_wtable_column_options_check('link', array('kinds' => array('Order')))['error']);
	pg_assert_same(array('expression' => 'A1*2'), ws_wtable_column_options_check('formula', array('expression' => ' =A1*2'))['value']);
	pg_assert_same('formula_empty', ws_wtable_column_options_check('formula', array('expression' => '='))['error']);
	pg_assert_same('formula_too_long', ws_wtable_column_options_check('formula', array('expression' => str_repeat('1', 1001)))['error']);
}

// An empty value empties any cell; a checkbox keeps false; a formula is
// never written.
function test_workspace_wtables_empty_and_formula_cells()
{
	foreach (array('text', 'number', 'money', 'date', 'person', 'link') as $type) {
		pg_assert_same(array('ok' => true, 'value' => null, 'error' => ''), ws_wtables_test_cell($type, ''), $type);
	}

	pg_assert_same(false, ws_wtables_test_cell('checkbox', null)['value']);
	pg_assert_same('computed', ws_wtables_test_cell('formula', 3, array('expression' => '1+2'))['error']);
	pg_assert_same('type_unknown', ws_wtable_cell_check(array('type' => 'rating'), 3)['error']);
}

function test_workspace_wtables_text_and_number_cells()
{
	pg_assert_same('12', ws_wtables_test_cell('text', 12)['value']);
	pg_assert_same('not_text', ws_wtables_test_cell('text', true)['error']);
	pg_assert_same('not_text', ws_wtables_test_cell('text', array('a'))['error']);
	pg_assert_same('too_long', ws_wtables_test_cell('text', str_repeat('a', 10001))['error']);

	pg_assert_same(3, ws_wtables_test_cell('number', '2.6')['value'], 'rounded, not cut');
	pg_assert_same(-2.35, ws_wtables_test_cell('number', -2.345, array('decimals' => 2))['value']);
	pg_assert_same(1500.0, ws_wtables_test_cell('number', '1.5e3', array('decimals' => 1))['value']);
	pg_assert_same('not_number', ws_wtables_test_cell('number', '1,5')['error']);
	pg_assert_same('not_number', ws_wtables_test_cell('number', 'abc')['error']);
	pg_assert_same('not_number', ws_wtables_test_cell('number', true)['error']);
	pg_assert_same('out_of_range', ws_wtables_test_cell('number', 1e15)['error']);
	pg_assert_same('out_of_range', ws_wtables_test_cell('number', NAN)['error']);
}

// Money is a whole number of minor units: a fraction is refused, not cut.
function test_workspace_wtables_money_cells()
{
	pg_assert_same(1999, ws_wtables_test_cell('money', 1999)['value']);
	pg_assert_same(-500, ws_wtables_test_cell('money', ' -500 ')['value']);
	pg_assert_same(2000, ws_wtables_test_cell('money', 2000.0)['value']);
	pg_assert_same('not_minor_units', ws_wtables_test_cell('money', 19.99)['error']);
	pg_assert_same('not_minor_units', ws_wtables_test_cell('money', '19.99')['error']);
	pg_assert_same('not_minor_units', ws_wtables_test_cell('money', true)['error']);
	pg_assert_same(999999999999999, ws_wtables_test_cell('money', '999999999999999')['value']);
	pg_assert_same('out_of_range', ws_wtables_test_cell('money', '1000000000000000')['error']);
	pg_assert_same('out_of_range', ws_wtables_test_cell('money', 1e16)['error']);
}

function test_workspace_wtables_date_person_status_cells()
{
	pg_assert_same('2026-02-28', ws_wtables_test_cell('date', '2026-02-28')['value']);
	pg_assert_same('not_date', ws_wtables_test_cell('date', '2026-02-30')['error']);
	pg_assert_same('not_date', ws_wtables_test_cell('date', '2026-02-28 10:00')['error'], 'a time where none is kept');
	pg_assert_same('2026-02-28 10:00', ws_wtables_test_cell('date', '2026-02-28 10:00', array('time' => true))['value']);
	pg_assert_same('2026-02-28', ws_wtables_test_cell('date', '2026-02-28', array('time' => true))['value'], 'the time may be left out');
	pg_assert_same('not_date', ws_wtables_test_cell('date', '2026-02-28 24:00', array('time' => true))['error']);
	pg_assert_same('not_date', ws_wtables_test_cell('date', 20260228)['error']);

	pg_assert_same(7, ws_wtables_test_cell('person', '7')['value']);
	pg_assert_same('not_person', ws_wtables_test_cell('person', 0)['error']);
	pg_assert_same('not_person', ws_wtables_test_cell('person', 'ali')['error']);

	$choices = array('choices' => array(array('key' => 'open', 'label' => 'Open'), array('key' => 'won', 'label' => 'Won')));
	pg_assert_same('won', ws_wtables_test_cell('status', 'won', $choices)['value']);
	pg_assert_same('not_status', ws_wtables_test_cell('status', 'lost', $choices)['error']);
	pg_assert_same('not_status', ws_wtables_test_cell('status', true, $choices)['error']);
}

function test_workspace_wtables_checkbox_and_link_cells()
{
	pg_assert_same(true, ws_wtables_test_cell('checkbox', 'TRUE')['value']);
	pg_assert_same(false, ws_wtables_test_cell('checkbox', 0)['value']);
	pg_assert_same('not_checkbox', ws_wtables_test_cell('checkbox', 'yes')['error']);

	pg_assert_same(array('kind' => 'order', 'id' => 12), ws_wtables_test_cell('link', array('kind' => 'order', 'id' => '12'))['value']);
	pg_assert_same('not_link', ws_wtables_test_cell('link', 'order:12')['error']);
	pg_assert_same('not_link', ws_wtables_test_cell('link', array('kind' => 'order', 'id' => 0))['error']);
	pg_assert_same('link_kind_refused', ws_wtables_test_cell('link', array('kind' => 'order', 'id' => 3), array('kinds' => array('product')))['error']);
}

// A row's cells: all checked, a refused one names its column, none kept.
function test_workspace_wtables_row_cells()
{
	$columns = array(
		'1' => ws_wtables_test_column('text'),
		'2' => ws_wtables_test_column('money'),
		'3' => ws_wtables_test_column('formula', array('expression' => '1')),
	);

	$check = ws_wtable_row_cells_check($columns, array('1' => 'Acme', 2 => 500));
	pg_assert_same(array('ok' => true, 'cells' => array('1' => 'Acme', '2' => 500), 'errors' => array()), $check);

	$check = ws_wtable_row_cells_check($columns, array('1' => 'Acme', '2' => 5.5, '3' => 1, '9' => 'x'));
	pg_assert_false($check['ok']);
	pg_assert_same(array(), $check['cells']);
	pg_assert_same(array('2' => 'not_minor_units', '3' => 'computed', '9' => 'unknown_column'), $check['errors']);

	pg_assert_same(array('' => 'not_cells'), ws_wtable_row_cells_check($columns, 'x')['errors']);
}

// New cells over the old; emptied cells, gone columns and formulas dropped.
function test_workspace_wtables_cells_merge()
{
	$columns = array(
		'1' => ws_wtables_test_column('text'),
		'2' => ws_wtables_test_column('checkbox'),
		'4' => ws_wtables_test_column('number'),
	);

	$merged = ws_wtable_cells_merge($columns, array('1' => 'a', '2' => true, '3' => 'gone', '4' => 1), array('2' => false, '4' => null, '1' => 'b'));
	pg_assert_same(array('1' => 'b'), $merged);
	pg_assert_same(array('1' => 'a', '4' => 2), ws_wtable_cells_merge($columns, array('4' => 2), array('1' => 'a')), 'sorted by column id');
}

function test_workspace_wtables_cells_json()
{
	pg_assert_same('{}', ws_wtable_cells_encode(array()));
	pg_assert_same('{"1":"Şirket/A","2":1999,"3":{"kind":"order","id":4},"4":2.0}', ws_wtable_cells_encode(array('1' => 'Şirket/A', '2' => 1999, '3' => array('kind' => 'order', 'id' => 4), '4' => 2.0)));
	pg_assert_same(array('1' => 'a', '2' => 5), ws_wtable_cells_decode('{"1":"a","2":5}'));
	pg_assert_same(array(), ws_wtable_cells_decode('[broken'));
	pg_assert_same(array(), ws_wtable_cells_decode(null));
	pg_assert_same(array(), ws_wtable_cells_decode('"text"'));
}
