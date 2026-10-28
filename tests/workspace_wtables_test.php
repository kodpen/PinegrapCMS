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

// A new table needs a name; a change keeps what it leaves out.
function test_workspace_wtables_table_input()
{
	$new = ws_wtable_table_input_check(array('name' => ' Leads ', 'description' => "First\r\nsecond\x01"), true);
	pg_assert_true($new['ok']);
	pg_assert_same(array('name' => 'Leads', 'description' => "First\nsecond"), $new['sets']);

	pg_assert_same('name_empty', ws_wtable_table_input_check(array(), true)['error'], 'a new table without a name');
	pg_assert_same('name', ws_wtable_table_input_check(array(), true)['field']);
	pg_assert_same(array('description' => ''), ws_wtable_table_input_check(array('description' => null), false)['sets'], 'a change of the description alone');
	pg_assert_same('nothing_to_change', ws_wtable_table_input_check(array('other' => 1), false)['error']);
	pg_assert_same('description_invalid', ws_wtable_table_input_check(array('description' => array()), false)['error']);
	pg_assert_same('description_invalid', ws_wtable_table_input_check(array('description' => "bad \xC3\x28"), false)['error'], 'not UTF-8');
	pg_assert_true(ws_wtable_table_input_check(array('description' => str_repeat('ğ', 2000)), false)['ok'], '2000 characters, not bytes');
	pg_assert_same('description_too_long', ws_wtable_table_input_check(array('description' => str_repeat('a', 2001)), false)['error']);
	pg_assert_same(422, ws_wtable_table_input_check(array(), true)['status']);
}

// A new column needs a name and a known type; a change never moves its type.
function test_workspace_wtables_column_input()
{
	$new = ws_wtable_column_input_check(array('name' => 'Amount', 'type' => 'money'));
	pg_assert_true($new['ok']);
	pg_assert_same(array('name' => 'Amount', 'type' => 'money', 'options' => array('decimals' => 2, 'currency' => '')), $new['sets'], 'the type\'s default options');

	pg_assert_same('type_unknown', ws_wtable_column_input_check(array('name' => 'A', 'type' => 'rating'))['error']);
	pg_assert_same('type_unknown', ws_wtable_column_input_check(array('name' => 'A'))['error']);
	pg_assert_same('choices_empty', ws_wtable_column_input_check(array('name' => 'A', 'type' => 'status'))['error']);
	pg_assert_same('options', ws_wtable_column_input_check(array('name' => 'A', 'type' => 'status'))['field']);

	$old = array('id' => 5, 'name' => 'Amount', 'type' => 'money', 'options' => array('decimals' => 2, 'currency' => ''));
	pg_assert_same(array('name' => 'Total'), ws_wtable_column_input_check(array('name' => 'Total'), $old)['sets'], 'a rename keeps the options');
	pg_assert_same(array('options' => array('decimals' => 0, 'currency' => 'EUR')), ws_wtable_column_input_check(array('options' => array('decimals' => 0, 'currency' => 'eur')), $old)['sets']);
	pg_assert_same('type_fixed', ws_wtable_column_input_check(array('type' => 'number'), $old)['error']);
	pg_assert_true(ws_wtable_column_input_check(array('type' => 'money', 'name' => 'X'), $old)['ok'], 'the same type again is no change of type');
	pg_assert_same('nothing_to_change', ws_wtable_column_input_check(array(), $old)['error']);
}

function test_workspace_wtables_column_rows()
{
	pg_assert_same('{}', ws_wtable_options_encode(array()));
	pg_assert_same('{"decimals":2,"currency":"TRY"}', ws_wtable_options_encode(array('decimals' => 2, 'currency' => 'TRY')));

	$column = ws_wtable_column_from_row(array('id' => '7', 'table_id' => '3', 'name' => 'Stage', 'type' => 'status', 'options' => '{"choices":[{"key":"new","label":"New","color":""}]}', 'position' => '2', 'created_at' => '10'));
	pg_assert_same(7, $column['id']);
	pg_assert_same(array('choices' => array(array('key' => 'new', 'label' => 'New', 'color' => ''))), $column['options']);
	pg_assert_same(array(), ws_wtable_column_from_row(array('id' => 1, 'table_id' => 1, 'name' => '', 'type' => 'text', 'options' => null, 'position' => 0, 'created_at' => 0))['options'], 'no options read as none');
	pg_assert_same(array(), ws_wtable_column_from_row(array('id' => 1, 'table_id' => 1, 'name' => '', 'type' => 'text', 'options' => '[broken', 'position' => 0, 'created_at' => 0))['options']);
}

function ws_wtables_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The body of the step function, from its signature to the closing brace at
// the start of a line.
function ws_wtables_test_step()
{
	if (!preg_match('/^function upgrade_2026_11_0_workspace_wtables\(\) \{\n(.*?)\n\}\n/ms', ws_wtables_test_source('includes/migrations/2026.11.0.php'), $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_wtables_step_is_called_once()
{
	$source = ws_wtables_test_source('includes/migrations/2026.11.0.php');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);

	$calls = isset($entry[1]) ? $entry[1] : '';

	pg_assert_same(1, substr_count($calls, "\tupgrade_2026_11_0_workspace_wtables();      // 11.0.110\n"), 'called once, labelled 11.0.110');

	preg_match_all('#// (11\.0\.\d+)\n#', $calls, $labels);

	pg_assert_same(1, count(array_keys($labels[1], '11.0.110', true)), 'no other step carries 11.0.110');
}

// The four tables, created through the helper with the decided columns.
function test_workspace_wtables_step_creates_the_tables()
{
	$step = ws_wtables_test_step();

	pg_assert_true($step !== '', 'the step function is found');

	$lines = array(
		'ws_wtables' => array(
			"user_id      INT UNSIGNED NOT NULL DEFAULT 0,",
			"name         VARCHAR(255) NOT NULL DEFAULT '',",
			"description  TEXT NULL,",
			"row_count    INT UNSIGNED NOT NULL DEFAULT 0,",
			"revision     INT UNSIGNED NOT NULL DEFAULT 0,",
			"KEY idx_owner (user_id, updated_at)",
		),
		'ws_wtable_columns' => array(
			"type         VARCHAR(16) NOT NULL DEFAULT 'text',",
			"options      TEXT NULL,",
			"position     INT UNSIGNED NOT NULL DEFAULT 0,",
			"KEY idx_table (table_id, position)",
		),
		'ws_wtable_rows' => array(
			"cells        MEDIUMTEXT NULL,",
			"revision     INT UNSIGNED NOT NULL DEFAULT 0,",
			"created_by   INT UNSIGNED NOT NULL DEFAULT 0,",
			"updated_by   INT UNSIGNED NOT NULL DEFAULT 0,",
			"KEY idx_table (table_id, position)",
		),
		'ws_wtable_shares' => array(
			"target_type  VARCHAR(16) NOT NULL DEFAULT '',",
			"target_id    INT UNSIGNED NOT NULL DEFAULT 0,",
			"level        VARCHAR(16) NOT NULL DEFAULT 'view',",
			"UNIQUE KEY uq_target (table_id, target_type, target_id),",
			"KEY idx_target (target_type, target_id, table_id)",
		),
	);

	foreach ($lines as $table => $columns) {
		$start = strpos($step, "install_create_table('" . $table . "', \"CREATE TABLE " . $table . " (");

		pg_assert_true($start !== false, $table . ' created through the helper, which asks first');

		$end = strpos($step, 'COLLATE=utf8mb4_unicode_ci");', (int) $start);
		$body = substr($step, (int) $start, (int) $end - (int) $start);

		pg_assert_contains(') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', $body, $table . ' InnoDB utf8mb4');

		foreach ($columns as $line) {
			pg_assert_contains($line, $body, $table);
		}
	}

	pg_assert_same(4, substr_count($step, 'install_create_table('), 'four tables');
	pg_assert_true(stripos($step, 'ENUM') === false, 'VARCHAR, not ENUM');
	pg_assert_true(stripos($step, 'FOREIGN KEY') === false, 'no foreign keys');
	pg_assert_true(stripos($step, 'ALTER TABLE') === false, 'no ALTER of its own');
	pg_assert_true(strpos($step, 'db(') === false, 'no raw statement');
	pg_assert_true(preg_match("/install_note\\('(?:[^'\\\\]|\\\\.)+'\\);\\s*\$/", $step) === 1, 'ends with a note for the operator');
}

// A reinstall drops the four tables with the software's others, and the
// workspace loads the file.
function test_workspace_wtables_tables_are_listed_and_loaded()
{
	$install = ws_wtables_test_source('install/index.php');

	foreach (array('ws_wtables', 'ws_wtable_columns', 'ws_wtable_rows', 'ws_wtable_shares') as $table) {
		pg_assert_same(1, substr_count($install, "'" . $table . "',"), $table . ' in get_tables()');
	}

	pg_assert_same(1, substr_count(ws_wtables_test_source('includes/workspace/bootstrap.php'), "require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');"));
}
