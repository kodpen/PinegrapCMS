<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the workflow table starters (includes/workspace/wtable_starters.php):
 * every starter's columns pass the store's option check, its sample rows
 * fit its columns and become cells the store takes.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_import.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_starters.php');

function test_workspace_wtable_starters_columns()
{
	$starters = ws_wtable_starters();

	pg_assert_same(array('contacts', 'checklist'), array_keys($starters));

	foreach ($starters as $key => $starter) {
		pg_assert_true(($starter['name'] !== '') && (mb_strlen($starter['name']) <= ws_wtable_limits()['name']), $key . ' name');
		pg_assert_true(count($starter['columns']) <= ws_wtable_limits()['columns'], $key . ' columns');

		$names = array();

		foreach ($starter['columns'] as $column) {
			$check = ws_wtable_column_options_check($column['type'], $column['options']);
			pg_assert_true($check['ok'], $key . ' ' . $column['name'] . ' options');
			$names[] = mb_strtolower($column['name']);
		}

		pg_assert_same(count($names), count(array_unique($names)), $key . ' column names unique');
	}
}

function test_workspace_wtable_starters_rows()
{
	$today = mktime(12, 0, 0, 3, 10, 2026);

	pg_assert_same('2026-03-12', ws_wtable_starter_value(array('days' => 2), 7, $today));
	pg_assert_same('7', ws_wtable_starter_value('me', 7, $today));
	pg_assert_same('Ada', ws_wtable_starter_value('Ada', 7, $today));

	foreach (ws_wtable_starters() as $key => $starter) {
		$columns = array();

		foreach ($starter['columns'] as $i => $column) {
			$columns[$i + 1] = array('type' => $column['type'], 'options' => ws_wtable_column_options_check($column['type'], $column['options'])['value']);
		}

		foreach ($starter['rows'] as $n => $row) {
			pg_assert_same(count($starter['columns']), count($row), $key . ' row ' . $n . ' fits');

			foreach ($row as $i => $value) {
				$cell = ws_wtable_import_cell($columns[$i + 1], ws_wtable_starter_value($value, 7, $today), array());

				if (($cell !== null) && ($columns[$i + 1]['type'] !== 'person')) {
					pg_assert_true(ws_wtable_cell_check($columns[$i + 1], $cell)['ok'], $key . ' row ' . $n . ' cell ' . $i);
				}
			}
		}
	}
}
