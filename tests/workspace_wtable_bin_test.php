<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bin of workflow tables (includes/workspace/wtable_bin.php):
 * the links a binned row keeps, when the bin lets go, the schema step, and
 * that the store moves a delete to the bin while the readers leave a binned
 * table out.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_rows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_shares.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_bin.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_import.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_starters.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function ws_wtable_bin_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// A function's body without its comments.
function ws_wtable_bin_test_code($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// The links keep the ws_refs tags, and read back only what was written.
function test_ws_wtable_bin_links_round_trip()
{
	$refs = array(array('type' => 'contact', 'id' => 7), array('type' => 'order', 'id' => '12'));
	$json = ws_wtable_bin_links_encode($refs);

	pg_assert_same('{"refs":[{"type":"contact","id":7},{"type":"order","id":12}]}', $json);
	pg_assert_same(array('refs' => array(array('type' => 'contact', 'id' => 7), array('type' => 'order', 'id' => 12))), ws_wtable_bin_links_decode($json));
	pg_assert_same('{"refs":[]}', ws_wtable_bin_links_encode(array(array('type' => '', 'id' => 3), array('type' => 'contact', 'id' => 0), 'x')), 'empty or broken tags are left out');
}

function test_ws_wtable_bin_links_decode_takes_nothing_else()
{
	foreach (array(null, '', 'not json', '[]', '{"refs":"x"}', '{"refs":[{"type":5,"id":2}]}', '{"refs":[{"type":"contact","id":-1}]}') as $json) {
		pg_assert_same(array('refs' => array()), ws_wtable_bin_links_decode($json), var_export($json, true));
	}
}

// The bin keeps for the Recycle Bin's days; a day at least.
function test_ws_wtable_bin_expiry_and_cutoff()
{
	pg_assert_same(1000 + 30 * 86400, ws_wtable_bin_expires_at(1000, 30));
	pg_assert_same(1000 + 86400, ws_wtable_bin_expires_at(1000, 0), 'a day at least');
	pg_assert_same(5000000 - 7 * 86400, ws_wtable_bin_cutoff(5000000, 7));
	pg_assert_true(ws_wtable_bin_expires_at(1000, 7) > ws_wtable_bin_cutoff(1000 + 7 * 86400 - 1, 7) - 1000, 'an entry is not due before it expires');
}

// One step, its label taken once, with the decided columns.
function test_ws_wtable_bin_step()
{
	$source = ws_wtable_bin_test_source('includes/migrations/2026.11.0.php');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	$calls = isset($entry[1]) ? $entry[1] : '';

	pg_assert_same(1, substr_count($calls, "\tupgrade_2026_11_0_ws_wtable_bin();           // 11.0.178\n"), 'called once, labelled 11.0.178');
	preg_match_all('#// (11\.0\.\d+)\n#', $calls, $labels);
	pg_assert_same(1, count(array_keys($labels[1], '11.0.178', true)), 'no other step carries 11.0.178');

	pg_assert_true(preg_match('/^function upgrade_2026_11_0_ws_wtable_bin\(\) \{\n(.*?)\n\}\n/ms', $source, $match) === 1, 'the step is found');
	$step = $match[1];

	pg_assert_contains("'deleted_at' => \"INT UNSIGNED NOT NULL DEFAULT 0\"", $step);
	pg_assert_contains("'deleted_by' => \"INT UNSIGNED NOT NULL DEFAULT 0\"", $step);
	pg_assert_contains("install_add_column('ws_wtables', \$column, \$definition);", $step);
	pg_assert_contains("install_create_table('ws_wtable_row_bin', \"CREATE TABLE ws_wtable_row_bin (", $step);

	foreach (array('row_id      INT UNSIGNED', 'cells       MEDIUMTEXT NULL,', 'links       MEDIUMTEXT NULL,', 'deleted_by  INT UNSIGNED', 'deleted_at  INT UNSIGNED',
		'KEY idx_table (table_id, deleted_at),', 'KEY idx_deleted (deleted_at)', ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4') as $line) {
		pg_assert_contains($line, $step);
	}

	pg_assert_true(strpos($step, 'db(') === false, 'no raw statement');
	pg_assert_true(stripos($step, 'ALTER TABLE') === false, 'no ALTER of its own');
	pg_assert_true(preg_match("/install_note\\('(?:[^'\\\\]|\\\\.)+'\\);\\s*\$/", $step) === 1, 'ends with a note for the operator');

	pg_assert_same(1, substr_count(ws_wtable_bin_test_source('install/index.php'), "'ws_wtable_row_bin',"), 'in get_tables()');
	pg_assert_same(1, substr_count(ws_wtable_bin_test_source('includes/workspace/bootstrap.php'), "require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_bin.php');"), 'loaded');
}

// The five readers leave a binned table out; restore and the bin's own
// readers are the only ones that see it.
function test_ws_wtable_bin_readers_leave_binned_tables_out()
{
	foreach (array('ws_wtable', 'ws_wtables_own', 'ws_wtable_create_check', 'ws_wtables_reached', 'ws_wtable_restore_check') as $function) {
		pg_assert_contains('ws_wtable_live_sql()', ws_wtable_bin_test_code($function), $function);
	}

	pg_assert_contains("deleted_at > 0", ws_wtable_bin_test_code('ws_wtable_binned'));
	pg_assert_contains('deleted_at', ws_wtable_bin_test_code('pg_privacy_export_collect'), 'the export says when a table went to the bin');
}

// A row is copied to the bin before it leaves, and the copy goes again
// when this request did not remove the row; a table is marked.
function test_ws_wtable_bin_deletes_move_to_the_bin()
{
	$row = ws_wtable_bin_test_code('ws_wtable_row_delete');

	pg_assert_true(strpos($row, 'ws_wtable_row_bin_put(') !== false && strpos($row, 'ws_wtable_row_bin_put(') < strpos($row, 'DELETE FROM ws_wtable_rows'), 'copied before the delete');
	pg_assert_contains('ws_wtable_row_bin_drop($bin_id)', $row, 'a copy of a row not removed is taken out');

	$table = ws_wtable_bin_test_code('ws_wtable_delete');

	pg_assert_contains('ws_wtable_bin_put($table', $table);
	pg_assert_true(strpos($table, 'DELETE FROM') === false, 'a table delete deletes nothing');
	pg_assert_contains('DELETE FROM ws_wtable_row_bin', ws_wtable_bin_test_code('ws_wtable_delete_final'), 'a final delete empties the table\'s bin too');
}

// The rollback of a table made halfway stays a real delete.
function test_ws_wtable_bin_rollbacks_delete_for_good()
{
	foreach (array('ws_wtable_import_create', 'ws_wtable_starter_create') as $function) {
		$code = ws_wtable_bin_test_code($function);

		pg_assert_contains('ws_wtable_delete_final(', $code, $function);
		pg_assert_true(strpos($code, 'ws_wtable_delete(') === false, $function . ' does not bin');
	}
}

// A restore claims the bin entry before it writes the row, and the purge
// runs with the recurring job.
function test_ws_wtable_bin_restore_and_purge()
{
	$restore = ws_wtable_bin_test_code('ws_wtable_row_restore');

	pg_assert_true(strpos($restore, 'ws_wtable_row_bin_drop(') < strpos($restore, 'INSERT INTO ws_wtable_rows'), 'the entry is claimed first');
	pg_assert_contains("ws_wtable_row_announce('restored'", $restore);
	pg_assert_contains("ws_ref_source_write('wtable_row'", $restore, 'its links come back');
	pg_assert_contains('ws_wtable_bin_purge();', ws_wtable_bin_test_source('workspace_recurring_job.php'));
}

// A binned row in a line: the first cells that hold something, under their
// column names, cut short when long.
function test_ws_wtable_bin_screen_summary()
{
	$columns = array(
		'1' => array('name' => 'Name', 'type' => 'text', 'options' => array()),
		'2' => array('name' => 'Note', 'type' => 'text', 'options' => array()),
		'3' => array('name' => 'City', 'type' => 'text', 'options' => array()),
		'4' => array('name' => 'Extra', 'type' => 'text', 'options' => array()),
	);

	pg_assert_same('Name: Ada · City: Paris', ws_wtable_screen_bin_summary($columns, array('1' => 'Ada', '2' => '', '3' => "Paris\n")), 'empty cells skipped, spaces folded');
	pg_assert_same('Name: A · Note: B', ws_wtable_screen_bin_summary($columns, array('1' => 'A', '2' => 'B', '3' => 'C'), array(), 2), 'at most the cells asked');
	pg_assert_same('', ws_wtable_screen_bin_summary($columns, array()), 'nothing for an empty row');
	pg_assert_same('Name: ' . str_repeat('x', 59) . '…', ws_wtable_screen_bin_summary($columns, array('1' => str_repeat('x', 80))), 'a long cell cut');
}

// A delete says how long the bin keeps it; without the bin, that it is final.
function test_ws_wtable_bin_screen_delete_confirm()
{
	pg_assert_contains('30 days', ws_wtable_screen_delete_confirm('table', 'Leads', 30));
	pg_assert_contains('Leads', ws_wtable_screen_delete_confirm('table', 'Leads', 30));
	pg_assert_contains('cannot be brought back', ws_wtable_screen_delete_confirm('table', 'Leads', 0));
	pg_assert_contains('7 days', ws_wtable_screen_delete_confirm('row', 'Leads', 7));
	pg_assert_same('Delete this row?', ws_wtable_screen_delete_confirm('row', 'Leads', 0));
}

// The screen restores through the store and draws both bins.
function test_ws_wtable_bin_screen_wiring()
{
	$screen = ws_wtable_bin_test_source('workspace_wtables.php');

	foreach (array("case 'table_restore':", "case 'row_restore':", "case 'bin_row_delete':", 'ws_wtable_restore($viewer', 'ws_wtable_row_restore($viewer', 'ws_wtable_bin_row_delete($viewer',
		'ws_wtables_binned($viewer)', 'ws_wtable_bin_rows($viewer, $table)', 'id="ws-wt-deleted"', 'id="ws-wt-deleted-rows"', '<input type="hidden" name="wt_bin" value="">', "'wt_bin'") as $needle) {
		pg_assert_contains($needle, $screen);
	}

	pg_assert_true(strpos($screen, 'It cannot be brought back.\', \'vars\'') === false, 'the table delete asks through ws_wtable_screen_delete_confirm()');
}
