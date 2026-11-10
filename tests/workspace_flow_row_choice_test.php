<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the table boxes of the workspace's flows find their row
 * (row_get reads it, row_set changes it): by its number as the table lists
 * it, by its id, or the last row of the table. What the row text may hold
 * and how each fault is worded, the same at the editor's check, at publish
 * and as the box runs; the row named in the box's sentences; a template's
 * row kept or cleared with its table. Read from the source: the store
 * finds the row by its place or as the last one, and both boxes find it
 * through one function.
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
require_once(__DIR__ . '/helpers/source_reader.php');

// A function's source without its comment lines.
function workspace_flow_row_choice_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// The ways a box finds its row: by its id first, the meaning of a box saved
// before the choice, then by its number in the table and the last row.
function test_workspace_flow_row_choice_ways()
{
	pg_assert_same(array('id', 'position', 'last', 'number', 'key'), ws_step_row_bys());
	pg_assert_same(ws_step_row_get_bys(), ws_flow_setting_values()['row_get.by'], 'a read also finds the first row that meets conditions');
	pg_assert_same(ws_step_row_bys(), ws_flow_setting_values()['row_set.by']);

	$catalogue = array_column(ws_flow_kind_catalogue(), null, 'kind');

	pg_assert_same(array('table_id', 'by', 'row', 'match', 'where', 'retry', 'retry_after'), array_column($catalogue['row_get']['settings'], 'key'));
	pg_assert_same(array('table_id', 'by', 'row', 'cells', 'retry', 'retry_after'), array_column($catalogue['row_set']['settings'], 'key'));
	pg_assert_same(array('id', 'position', 'last', 'number', 'key', 'match'), array_column($catalogue['row_get']['settings'], 'values', 'key')['by']);
}

// The way is kept; none or an unknown one is by its id, as a box saved
// before the choice meant; the last row names no row.
function test_workspace_flow_row_choice_config()
{
	pg_assert_same(array('table_id' => 12, 'by' => 'position', 'row' => '1', 'match' => 'all', 'where' => ''), ws_step_config_normalize('row_get', array('table_id' => '12', 'by' => 'position', 'row' => ' 1 ')));
	pg_assert_same(array('table_id' => 0, 'by' => 'id', 'row' => '', 'match' => 'all', 'where' => ''), ws_step_config_normalize('row_get', array()));
	pg_assert_same('id', ws_step_config_normalize('row_get', array('by' => 'nearest'))['by']);
	pg_assert_same('id', ws_step_config_normalize('row_get', array('by' => array('last')))['by']);
	pg_assert_same(array('table_id' => 0, 'by' => 'last', 'row' => '', 'match' => 'all', 'where' => ''), ws_step_config_normalize('row_get', array('by' => 'last', 'row' => '5')));
	pg_assert_same(array('table_id' => 4, 'by' => 'position', 'row' => '2', 'cells' => '{"5":"x"}'), ws_step_config_normalize('row_set', array('table_id' => 4, 'by' => 'position', 'row' => '2', 'cells' => '{"5":"x"}')));
	pg_assert_same(array('table_id' => 4, 'by' => 'last', 'row' => '', 'cells' => '{"5":"x"}'), ws_step_config_normalize('row_set', array('table_id' => 4, 'by' => 'last', 'row' => '{{trigger.row_id}}', 'cells' => '{"5":"x"}')));
	pg_assert_same(array('table_id' => 0, 'cells' => ''), ws_step_config_normalize('row_add', array('by' => 'last', 'row' => '3')), 'a new row is found by no way');
}

// What a row text may hold, once for the editor, publish and the run: a
// whole number from 1, or one value alone. Each fault has its own word.
function test_workspace_flow_row_choice_text_faults()
{
	foreach (array('1', ' 14 ', '5000', '007', '{{trigger.row_id}}', ' {{node.get.row_id}} ', '{{node.loop.index|1}}') as $text) {
		pg_assert_same('', ws_step_row_text_fault($text), $text . ' names a row');
		pg_assert_true(ws_step_row_names_row($text), $text . ' names a row for the older reader too');
	}

	foreach (array('', '   ', "\n") as $text) {
		pg_assert_same('empty', ws_step_row_text_fault($text));
	}

	foreach (array('0', '00', ' 0 ', '-2') as $text) {
		pg_assert_same('below_one', ws_step_row_text_fault($text), $text . ' is below the first row');
		pg_assert_false(ws_step_row_names_row($text));
	}

	foreach (array('abc', '1.5', '1 2', '+3', '1e3', 'row {{trigger.row_id}}', '{{trigger.row_id}}{{trigger.row_id}}', '12345678901') as $text) {
		pg_assert_same('not_row', ws_step_row_text_fault($text), $text . ' names no row');
		pg_assert_false(ws_step_row_names_row($text));
	}

	// As the run reads a filled-in row: the same numbers, leading zeros
	// and all; anything else is no row.
	pg_assert_same(7, ws_step_row_id(' 007 '));
	pg_assert_same(14, ws_step_row_id('14'));
	pg_assert_same(0, ws_step_row_id('0'));
	pg_assert_same(0, ws_step_row_id('1.0'));
	pg_assert_same(0, ws_step_row_id('-3'));
}

// The editor's problems name the box and what is wrong with its row; the
// last row needs none.
function test_workspace_flow_row_choice_problems()
{
	$problems = function ($kind, $config) {
		return implode(' ', ws_step_config_problems($kind, ws_step_config_normalize($kind, $config), 'UN'));
	};
	$cells = array('5' => 'won');

	pg_assert_same('', $problems('row_get', array('table_id' => 5, 'by' => 'position', 'row' => '1')), 'the first row of the table');
	pg_assert_same('', $problems('row_get', array('table_id' => 5, 'by' => 'position', 'row' => '{{node.loop.index}}')));
	pg_assert_same('', $problems('row_get', array('table_id' => 5, 'by' => 'id', 'row' => '{{trigger.row_id}}')));
	pg_assert_same('', $problems('row_get', array('table_id' => 5, 'by' => 'last')), 'the last row needs no row text');
	pg_assert_same('', $problems('row_set', array('table_id' => 5, 'by' => 'last', 'cells' => $cells)));

	pg_assert_same('The box UN names the row 0, but rows are counted from 1: the first row of the table is 1.', $problems('row_get', array('table_id' => 5, 'by' => 'position', 'row' => '0')));
	pg_assert_same('The box UN names the row id 0, but row ids start at 1.', $problems('row_get', array('table_id' => 5, 'by' => 'id', 'row' => '0')));
	pg_assert_same('The box UN names its row as “first”: write the row\'s number in the table, such as 1, or add one value with “Add value”.', $problems('row_get', array('table_id' => 5, 'by' => 'position', 'row' => 'first')));
	pg_assert_same('The box UN names its row as “first”: write the row\'s id, or add one value with “Add value”.', $problems('row_set', array('table_id' => 5, 'by' => 'id', 'row' => 'first', 'cells' => $cells)));
	pg_assert_same('The box UN needs the row it reads.', $problems('row_get', array('table_id' => 5, 'by' => 'position')));
	pg_assert_same('The box UN needs the row it changes.', $problems('row_set', array('table_id' => 5, 'by' => 'position', 'cells' => $cells)));

	// The same rule at publish and as the box runs, without the box's name.
	pg_assert_same('', ws_step_row_refusal('position', '1'));
	pg_assert_same('', ws_step_row_refusal('last', ''));
	pg_assert_same('Rows are counted from 1: the first row of the table is 1.', ws_step_row_refusal('position', '0'));
	pg_assert_same('Row ids start at 1.', ws_step_row_refusal('id', '0'));
	pg_assert_same('The box names no row.', ws_step_row_refusal('id', ''));
	pg_assert_same('Write the row\'s number in the table, such as 1, or add one value with “Add value”.', ws_step_row_refusal('position', 'x'));
	pg_assert_same('Write the row\'s id, or add one value with “Add value”.', ws_step_row_refusal('id', 'x'));
}

// The sentences name the row as the box finds it; a row given by a value is
// known only as the box runs.
function test_workspace_flow_row_choice_sentences()
{
	pg_assert_same('Reads the row 1 of the table “Fırın”.', ws_step_row_get_text(array('table_name' => 'Fırın', 'by' => 'position', 'row' => '1')));
	pg_assert_same('Reads the row with the id 14 of the table “Fırın”.', ws_step_row_get_text(array('table_name' => 'Fırın', 'by' => 'id', 'row' => '14')));
	pg_assert_same('Reads the last row of the table “Fırın”.', ws_step_row_get_text(array('table_name' => 'Fırın', 'by' => 'last', 'row' => '')));
	pg_assert_same('Reads a row of the table “Fırın”.', ws_step_row_get_text(array('table_name' => 'Fırın', 'by' => 'position', 'row' => '{{node.loop.index}}')));
	pg_assert_same('Reads a row of the table “Fırın”.', ws_step_row_get_text(array('table_name' => 'Fırın')), 'a box tried before the choice');

	pg_assert_same('The table “Fırın” has no row 3.', ws_step_row_missing_text('position', '3', 'Fırın'));
	pg_assert_same('The table “Fırın” has no row with the id 3.', ws_step_row_missing_text('id', ' 3 ', 'Fırın'));
	pg_assert_same('The table “Fırın” has no rows.', ws_step_row_missing_text('last', '', 'Fırın'));
	pg_assert_same('The table “Fırın” has no row “abc”.', ws_step_row_missing_text('position', 'abc', 'Fırın'), 'a value that filled in to no number');
	pg_assert_same('The table “Fırın” has no row “0”.', ws_step_row_missing_text('id', '0', 'Fırın'));
}

// A row's number fits any table a template is set up with; an id is the
// template's own table's and is cleared with it.
function test_workspace_flow_row_choice_templates()
{
	pg_assert_same('1', ws_flow_template_table_cleared('row_get', array('table_id' => 4, 'by' => 'position', 'row' => '1'))['row']);
	pg_assert_same('2', ws_flow_template_table_cleared('row_set', array('table_id' => 4, 'by' => 'position', 'row' => '2', 'cells' => '{"5":1}'))['row']);
	pg_assert_same('', ws_flow_template_table_cleared('row_get', array('table_id' => 4, 'by' => 'id', 'row' => '15'))['row']);
	pg_assert_same('', ws_flow_template_table_cleared('row_get', array('table_id' => 4, 'row' => '15'))['row'], 'a box saved before the choice names an id');
	pg_assert_same('{{trigger.row_id}}', ws_flow_template_table_cleared('row_set', array('table_id' => 4, 'by' => 'id', 'row' => '{{trigger.row_id}}', 'cells' => ''))['row']);
}

// Read from the source: the store finds a row by its place as the table
// lists it, or the last one; both boxes find their row through one function
// and check its text through one rule.
function test_workspace_flow_row_choice_reads_through_the_store()
{
	$at = workspace_flow_row_choice_test_source('ws_wtable_row_at');

	pg_assert_contains("table_id = '\" . (int) \$table_id . \"' AND position = '\" . (int) \$position . \"'", $at, 'the row the table lists at that number');
	pg_assert_contains('ORDER BY id LIMIT 1', $at, 'two rows added at once share a number: the first of them');
	pg_assert_contains('ORDER BY position DESC, id DESC LIMIT 1', workspace_flow_row_choice_test_source('ws_wtable_row_last'));

	$find = workspace_flow_row_choice_test_source('ws_step_row_find');

	pg_assert_contains('ws_wtable_row_last(', $find);
	pg_assert_contains('ws_wtable_row_at(', $find);
	pg_assert_contains('ws_wtable_row(', $find);

	pg_assert_contains('ws_step_row_find(', workspace_flow_row_choice_test_source('ws_step_row_get_do'));
	pg_assert_contains('ws_step_row_find(', workspace_flow_row_choice_test_source('ws_step_row_do'));
	pg_assert_contains('ws_step_row_refusal(', workspace_flow_row_choice_test_source('ws_step_row_get_check'));
	pg_assert_contains('ws_step_row_refusal(', workspace_flow_row_choice_test_source('ws_step_row_check'));
	pg_assert_contains('ws_step_row_problem(', workspace_flow_row_choice_test_source('ws_step_config_problems'));
}

// By its # number: the stable number the table shows, found by its own
// reader and worded as a # number, never as a place or an id.
function test_workspace_flow_row_choice_by_number()
{
	pg_assert_same('number', ws_step_row_way(array('by' => 'number', 'row' => '12'))['by']);
	pg_assert_same('12', ws_step_row_way(array('by' => 'number', 'row' => '12'))['row']);

	pg_assert_same('', ws_step_row_refusal('number', '12'));
	pg_assert_same('Row numbers start at 1.', ws_step_row_refusal('number', '0'));
	pg_assert_contains('# number', ws_step_row_refusal('number', 'abc'));
	pg_assert_contains('#0', ws_step_row_problem('row_get', array('by' => 'number', 'row' => '0'), 'Read')[0]);
	pg_assert_contains('# number', ws_step_row_problem('row_set', array('by' => 'number', 'row' => 'x'), 'Change')[0]);

	pg_assert_same('The table “Fırın” has no row #7.', ws_step_row_missing_text('number', '7', 'Fırın'));
	pg_assert_same('Reads the row #7 of the table “Fırın”.', ws_step_row_get_text(array('table_name' => 'Fırın', 'by' => 'number', 'row' => '7')));

	$find = workspace_flow_row_choice_test_source('ws_step_row_find');
	pg_assert_contains('ws_wtable_row_numbered(', $find);

	$numbered = workspace_flow_row_choice_test_source('ws_wtable_row_numbered');
	pg_assert_contains("AND number = '\" . (int) \$number . \"'", $numbered);
}
