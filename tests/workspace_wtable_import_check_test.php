<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what an import into a workflow table would do without writing
 * (includes/workspace/wtable_import.php): the rows it would add and refuse
 * (ws_wtable_import_rows_check()) and a new table's columns from the source
 * (ws_wtable_import_plan()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_rows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_import.php');

function test_workspace_wtable_import_rows_check_counts_and_refuses()
{
	$columns = array(
		3 => array('id' => 3, 'name' => 'Item', 'type' => 'text', 'options' => array()),
		4 => array('id' => 4, 'name' => 'Count', 'type' => 'number', 'options' => array('decimals' => 0)),
	);
	$rows = array(
		array('Pen', '2'),
		array('', ''),
		array('Ink', 'many'),
		array('Pad', '5'),
	);

	$report = ws_wtable_import_rows_check($columns, array(0 => 3, 1 => 4), $rows, 10);

	pg_assert_same(2, $report['rows'], 'two rows would be added, the empty one skipped');
	pg_assert_same(1, $report['refused_count']);
	pg_assert_same(4, $report['refused'][0]['line'], 'the line counts the header as 1');
	pg_assert_same('cells_invalid', $report['refused'][0]['error']);
	pg_assert_same(array('Count'), array_keys($report['refused'][0]['errors']), 'the refused cell by column name');
}

function test_workspace_wtable_import_rows_check_stops_at_the_room_left()
{
	$columns = array(1 => array('id' => 1, 'name' => 'Item', 'type' => 'text', 'options' => array()));
	$report = ws_wtable_import_rows_check($columns, array(0 => 1), array(array('a'), array('b'), array('c')), 1);

	pg_assert_same(1, $report['rows']);
	pg_assert_same(2, $report['refused_count'], 'the rest past the room');
	pg_assert_same(array(array('line' => 3, 'error' => 'too_many_rows', 'errors' => array())), $report['refused']);
}

function test_workspace_wtable_import_plan_guesses_or_takes_the_types()
{
	$names = array('Item', 'Price', 'Done', 'Note');
	$rows = array(array('Pen', '12,50 TL', 'yes', 'x1'), array('Ink', '3 TL', 'no', 'x2'));

	pg_assert_same(array(
		array('index' => 0, 'name' => 'Item', 'type' => 'text'),
		array('index' => 1, 'name' => 'Price', 'type' => 'money'),
		array('index' => 2, 'name' => 'Done', 'type' => 'checkbox'),
		array('index' => 3, 'name' => 'Note', 'type' => 'text'),
	), ws_wtable_import_plan($names, $rows), 'guessed from the values');

	pg_assert_same(array(
		array('index' => 0, 'name' => 'Item', 'type' => 'text'),
		array('index' => 1, 'name' => 'Price', 'type' => 'text'),
		array('index' => 2, 'name' => 'Done', 'type' => 'checkbox'),
	), ws_wtable_import_plan($names, $rows, array('Price' => 'text', 'Note' => '')), 'a type given, a column left out');
}

function test_workspace_wtable_import_check_is_shared()
{
	$panel = file_get_contents(PG_FUNCTIONS_DIR . '/workspace_wtables.php');

	pg_assert_true(substr_count($panel, 'ws_wtable_import_check(') >= 2, 'the panel asks before both imports');
}
