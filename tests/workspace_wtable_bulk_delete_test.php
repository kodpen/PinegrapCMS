<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The bulk Delete of the Data list's selection bar
 * (includes/workspace/wtable_list.php): the ids it reads, the single
 * delete done for each chosen table under its own right, the notice that
 * counts the deleted and names the tables left, and the screen's wiring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_list.php');

function test_ws_wtable_list_bulk_ids_reads_a_clean_list()
{
	pg_assert_same(array(3, 7), ws_wtable_list_bulk_ids(array('3', '7', '3', 'x', '-2', '0', array(5), '1.5')), 'positive whole ids, each once');
	pg_assert_same(array(), ws_wtable_list_bulk_ids('7'), 'not a list');
	pg_assert_same(array(), ws_wtable_list_bulk_ids(null));
	pg_assert_same(array(1, 2), ws_wtable_list_bulk_ids(array('1', '2', '3'), 2), 'no more than the most');
}

function test_ws_wtable_list_bulk_delete_runs_the_single_delete_per_table()
{
	$tables = array(
		1 => array('id' => 1, 'name' => 'Mine'),
		2 => array('id' => 2, 'name' => 'Shared <to me>'),
		3 => array('id' => 3, 'name' => 'Hidden'),
		4 => array('id' => 4, 'name' => 'Also mine'),
	);
	$asked = array();
	$load = function ($id) use ($tables) {
		return $tables[$id] ?? null;
	};
	$delete = function ($viewer, $table) use (&$asked) {
		$asked[] = $table ? (int) $table['id'] : 0;

		if (!$table) {
			return array('ok' => false, 'error' => 'not_found');
		}

		$errors = array(2 => 'forbidden', 3 => 'not_found');

		return isset($errors[$table['id']]) ? array('ok' => false, 'error' => $errors[$table['id']]) : array('ok' => true, 'error' => '');
	};

	$result = ws_wtable_list_bulk_delete(array('id' => 9), array(1, 2, 3, 4, 8), $load, $delete);

	pg_assert_same(array(1, 2, 3, 4, 0), $asked, 'every chosen table goes through the single delete, which checks the right');
	pg_assert_same(array(1, 4), array_map('intval', array_column($result['deleted'], 'id')), 'the deleted tables');
	pg_assert_same(array('Shared <to me>'), $result['left'], 'a table the person reads but may not delete is named');
	pg_assert_same(2, $result['unreached'], 'a table out of reach is counted, never named');
}

function test_ws_wtable_list_bulk_delete_texts()
{
	$all = ws_wtable_list_bulk_delete_texts(3, array(), 0, 30);
	pg_assert_contains('3 tables were deleted', $all['notice']);
	pg_assert_contains('Deleted tables', $all['notice'], 'the bin is named');
	pg_assert_same('', $all['error']);

	$one = ws_wtable_list_bulk_delete_texts(1, array(), 0, 30);
	pg_assert_contains('1 table was deleted', $one['notice']);

	$gone = ws_wtable_list_bulk_delete_texts(2, array(), 0, 0);
	pg_assert_true(strpos($gone['notice'], 'Deleted tables') === false, 'without the bin they are deleted for good');

	$some = ws_wtable_list_bulk_delete_texts(1, array('Leads', 'Stock'), 1, 30);
	pg_assert_contains('1 table was deleted', $some['notice']);
	pg_assert_contains('Leads, Stock', $some['notice'], 'each table left is named');
	pg_assert_contains('1 chosen table', $some['notice'], 'one out of reach is counted');
	pg_assert_same('', $some['error']);

	$none = ws_wtable_list_bulk_delete_texts(0, array('Leads'), 0, 30);
	pg_assert_same('', $none['notice']);
	pg_assert_contains('Leads', $none['error'], 'nothing deleted is an error naming what was left');

	$empty = ws_wtable_list_bulk_delete_texts(0, array(), 0, 30);
	pg_assert_contains('No table', $empty['error']);
}

function test_ws_wtable_list_bulk_delete_screen_is_wired()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_wtables.php');
	pg_assert_contains("case 'table_bulk_delete':", $screen, 'a server action of its own');
	pg_assert_contains('ws_wtable_list_bulk_delete($viewer', $screen);
	pg_assert_contains('pg-button pg-button-danger pg-button-sm ws-wt-bulk-delete', $screen, 'the bar\'s Delete is the small danger button');
	pg_assert_contains('id="ws_wt_bulk_delete"', $screen, 'one confirm dialog');
	pg_assert_contains('pg-list-table:selection', $screen, 'the button follows the selection');
}
