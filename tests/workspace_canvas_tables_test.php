<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the tables bound to a canvas (includes/workspace/canvas_tables.php)
 * and a live page's read of them through its bridge
 * (includes/workspace/page_bridge.php): who may bind, the bounds of a read,
 * the schema step and the card's texts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/page_bridge.php');

function ws_test_canvas_bind_facts($change = array())
{
	return $change + array('access' => 'read', 'table' => true, 'may_post' => true, 'table_write' => true, 'bound' => false, 'count' => 0);
}

function test_workspace_canvas_tables_bind_refusal()
{
	pg_assert_same('', ws_canvas_table_bind_refusal(ws_test_canvas_bind_facts()), 'a writer of channel and table binds for reading');
	pg_assert_same('', ws_canvas_table_bind_refusal(ws_test_canvas_bind_facts(array('access' => 'write'))), 'and for writing');

	foreach (array('', 'edit', 'owner', 'READ') as $access) {
		pg_assert_same('access_invalid', ws_canvas_table_bind_refusal(ws_test_canvas_bind_facts(array('access' => $access))), var_export($access, true));
	}

	pg_assert_same('not_found', ws_canvas_table_bind_refusal(ws_test_canvas_bind_facts(array('table' => false))), 'a table not reached is not named');
	pg_assert_same('forbidden', ws_canvas_table_bind_refusal(ws_test_canvas_bind_facts(array('may_post' => false))), 'a reader who may not write in the channel');
	pg_assert_same('forbidden', ws_canvas_table_bind_refusal(ws_test_canvas_bind_facts(array('table_write' => false))),
		'a table one may only look at is not bound, even for reading');
	pg_assert_same('too_many', ws_canvas_table_bind_refusal(ws_test_canvas_bind_facts(array('count' => WS_CANVAS_MAX_TABLES))), 'twenty a canvas');
	pg_assert_same('', ws_canvas_table_bind_refusal(ws_test_canvas_bind_facts(array('count' => WS_CANVAS_MAX_TABLES, 'bound' => true))),
		'changing a binding\'s access at the limit passes');
	pg_assert_same(20, WS_CANVAS_MAX_TABLES);

	foreach (array('access_invalid', 'not_found', 'forbidden', 'too_many', 'not_page') as $code) {
		pg_assert_true(ws_canvas_table_refusal_text($code) !== $code, $code . ' has its words');
	}
}

function test_workspace_canvas_tables_rows_args()
{
	$read = ws_page_bridge_rows_args(array('table' => 7));
	pg_assert_true($read['ok']);
	pg_assert_same(7, $read['table']);
	pg_assert_same(WS_PAGE_BRIDGE_ROWS, $read['limit'], 'a hundred rows when no limit is named');
	pg_assert_same(0, $read['from']);
	pg_assert_false($read['totals']);
	pg_assert_same(array(), $read['query']['where']);

	pg_assert_same(7, ws_page_bridge_rows_args(array('table' => '7'))['table'], 'digits as text');
	pg_assert_same(WS_PAGE_BRIDGE_MAX_ROWS, ws_page_bridge_rows_args(array('table' => 7, 'limit' => 100000))['limit'], 'five hundred at most');
	pg_assert_same(1, ws_page_bridge_rows_args(array('table' => 7, 'limit' => -4))['limit']);
	pg_assert_same(0, ws_page_bridge_rows_args(array('table' => 7, 'from' => -9))['from']);
	pg_assert_true(ws_page_bridge_rows_args(array('table' => 7, 'totals' => true))['totals']);

	$query = ws_page_bridge_rows_args(array('table' => 7, 'where' => array(array('column' => 3, 'op' => 'eq', 'value' => 'a')), 'join' => 'any', 'sort' => 3, 'dir' => 'desc'))['query'];
	pg_assert_same('any', $query['join']);
	pg_assert_same(3, $query['sort']);
	pg_assert_same('desc', $query['dir']);
	pg_assert_same(1, count($query['where']));

	foreach (array(array(), array('table' => 'orders'), array('table' => 1.5), array('table' => array(7))) as $args) {
		pg_assert_same('invalid', ws_page_bridge_rows_args($args)['error']['code'], json_encode($args) . ': the table by its id');
	}

	pg_assert_same('invalid', ws_page_bridge_rows_args(array('table' => 7, 'where' => array('column' => 3)))['error']['code'], 'conditions not a list');
}

function test_workspace_canvas_tables_schema_step()
{
	$step = file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_true(strpos($step, "upgrade_2026_11_0_ws_canvas_tables();        // 11.0.190") !== false, 'the step is called with its label');
	pg_assert_true(strpos($step, "UNIQUE KEY uniq_canvas_table (message_id, table_id)") !== false, 'one binding a canvas and table');
	pg_assert_true(strpos($step, "install_create_table('ws_canvas_tables'") !== false, 'made by the idempotent helper');

	$install = file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_true(strpos($install, "'ws_canvas_tables',") !== false, 'listed among the software\'s tables');

	$tables = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
	pg_assert_true(strpos($tables, 'ws_canvas_tables_forget_table($id)') !== false, 'a table\'s final delete takes its bindings');
	$threads = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/threads.php');
	pg_assert_true(strpos($threads, 'DELETE FROM ws_canvas_tables WHERE message_id IN') !== false, 'a thread\'s purge takes its canvases\' bindings');
}

function test_workspace_canvas_tables_card()
{
	$strings = ws_canvas_tables_js_strings();

	foreach (array('page_tables', 'page_table_read', 'page_table_write', 'page_table_hidden', 'page_bind', 'page_bind_none', 'page_bind_read', 'page_bind_write', 'page_unbind') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key);
	}

	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_page_card.js');
	pg_assert_true(strpos($js, "h.api('ws_page_bind'") === false && strpos($js, "send('ws_page_bind'") !== false, 'binding goes through one sender');
	pg_assert_true(strpos($js, 'table.reached ? table.name') !== false, 'a table not reached shows nameless');
}
