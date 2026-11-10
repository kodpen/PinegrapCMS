<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the stable row numbers of workflow tables (11.0.183): the
 * migration step's columns, index and backfill, the counter taken in one
 * statement, the number kept through the bin, and the number a row is
 * shown and found by.
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

// A function's source without its comment lines.
function ws_wtable_numbers_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// The step is called once under its own label, adds the decided columns
// and index with the idempotent helpers, numbers only rows still at 0 in
// the table's order, and leaves each counter at the highest number given.
function test_workspace_wtable_numbers_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	$calls = isset($entry[1]) ? $entry[1] : '';

	pg_assert_same(1, substr_count($calls, "\tupgrade_2026_11_0_ws_wtable_numbers();       // 11.0.183\n"), 'called once, labelled 11.0.183');
	preg_match_all('#// (11\.0\.\d+)\n#', $calls, $labels);
	pg_assert_same(1, count(array_keys($labels[1], '11.0.183', true)), 'no other step carries 11.0.183');

	preg_match('/^function upgrade_2026_11_0_ws_wtable_numbers\(\) \{\n(.*?)\n\}\n/ms', $source, $match);
	$step = isset($match[1]) ? $match[1] : '';

	pg_assert_contains("install_add_column('ws_wtables', 'next_number', \"INT UNSIGNED NOT NULL DEFAULT 0\")", $step);
	pg_assert_contains("install_add_column('ws_wtable_rows', 'number', \"INT UNSIGNED NOT NULL DEFAULT 0\")", $step);
	pg_assert_contains("install_add_column('ws_wtable_row_bin', 'number', \"INT UNSIGNED NOT NULL DEFAULT 0\")", $step);
	pg_assert_contains("install_add_index('ws_wtable_rows', 'idx_number', \"KEY idx_number (table_id, number)\")", $step);
	pg_assert_contains("AND number = 0 ORDER BY position, id", $step, 'rows still at 0, in the order the table lists them');
	pg_assert_contains('GREATEST(t.next_number', $step, 'a counter never goes down');
	pg_assert_false(strpos($step, 'ALTER TABLE') !== false, 'no ALTER of its own');
}

// A number is taken in one statement and never given again; a row is
// written with it, the bin keeps it, and a restore keeps it or takes the
// next one for a row binned before the numbers.
function test_workspace_wtable_numbers_store()
{
	pg_assert_contains('next_number = LAST_INSERT_ID(next_number + 1)', ws_wtable_numbers_test_source('ws_wtable_number_take'));
	pg_assert_contains('ws_wtable_number_take($table_id)', ws_wtable_numbers_test_source('ws_wtable_row_create_write'));
	pg_assert_contains("ws_wtable_number_value((int) (\$row['number'] ?? 0))", ws_wtable_numbers_test_source('ws_wtable_row_bin_put'));

	$restore = ws_wtable_numbers_test_source('ws_wtable_row_restore');
	pg_assert_contains("((int) (\$bin['number'] ?? 0) > 0) ? (int) \$bin['number'] : ws_wtable_number_take(\$table_id)", $restore);
	pg_assert_same(2, substr_count($restore, 'ws_wtable_number_value($number)'), 'the row\'s values and the way back into the bin');
}

// The number a row is shown by: its stable number, else its place.
function test_workspace_wtable_numbers_label()
{
	pg_assert_same(12, ws_wtable_row_label(array('number' => 12, 'position' => 3)));
	pg_assert_same(3, ws_wtable_row_label(array('number' => 0, 'position' => 3)));
	pg_assert_same(3, ws_wtable_row_label(array('position' => '3')));
	pg_assert_same(0, ws_wtable_row_label(array()));
}

// A read row and the API's row carry the number.
function test_workspace_wtable_numbers_in_a_read_row()
{
	$record = array('id' => 40, 'number' => '7', 'position' => 2, 'revision' => 1, 'cells' => '{}',
		'created_by' => 1, 'created_at' => 0, 'updated_by' => 1, 'updated_at' => 0);
	$row = ws_wtable_row_out($record, array());

	pg_assert_same(7, $row['number']);
	pg_assert_same(7, ws_wtable_row_present($row)['number']);

	unset($record['number']);
	pg_assert_same(0, ws_wtable_row_out($record, array())['number'], 'before the numbers are there');

	pg_assert_same('integer', ws_api_wtable_row_schema()['number']);
	pg_assert_same(array_keys(ws_api_wtable_row_schema()), array_keys(ws_api_wtable_row_present($row)));
}
