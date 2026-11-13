<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a live page's writes with consent: the reader's answer for a
 * page version (includes/workspace/canvas_consents.php), the shape of the
 * bridge's add and change and the page's refusals
 * (includes/workspace/page_bridge.php), the canvas a row write names
 * (includes/workspace/wtable_rows.php) and what the page card keeps to.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_consents.php');

if (!function_exists('ws_wtable_screen_error_text')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_screen.php');
}

function test_workspace_canvas_consent_state()
{
	pg_assert_same('ask', ws_canvas_consent_state(null, array(3)), 'no answer yet');
	pg_assert_same('ask', ws_canvas_consent_state(null, array()), 'no answer, even with no write table');
	pg_assert_same('no', ws_canvas_consent_state(array('answer' => 'no', 'tables' => '[3]'), array(3)), 'the reader said no');
	pg_assert_same('no', ws_canvas_consent_state(array('answer' => 'maybe', 'tables' => '[3]'), array(3)), 'anything but yes is no');
	pg_assert_same('yes', ws_canvas_consent_state(array('answer' => 'yes', 'tables' => '[3,4]'), array(3)), 'a table taken off since');
	pg_assert_same('yes', ws_canvas_consent_state(array('answer' => 'yes', 'tables' => '["3"]'), array('3')), 'ids as text');
	pg_assert_same('ask', ws_canvas_consent_state(array('answer' => 'yes', 'tables' => '[3]'), array(3, 4)), 'a write table bound after the answer asks again');
	pg_assert_same('ask', ws_canvas_consent_state(array('answer' => 'yes', 'tables' => 'oops'), array(3)), 'unreadable tables ask again');
}

function test_workspace_canvas_consent_write_ids()
{
	$tables = array(
		array('id' => 3, 'name' => 'Orders', 'access' => 'write'),
		array('id' => 4, 'name' => 'Products', 'access' => 'read'),
		array('id' => '7', 'name' => 'Notes', 'access' => 'write'),
	);
	pg_assert_same(array(3, 7), ws_canvas_consent_write_ids($tables));
	pg_assert_same(array(), ws_canvas_consent_write_ids(array()));
}

function test_workspace_page_bridge_write_args()
{
	$add = ws_page_bridge_write_args('add', array('table' => 3, 'cells' => array('12' => 'Ada')));
	pg_assert_true($add['ok'], 'an add');
	pg_assert_same(3, $add['table']);
	pg_assert_same(array('12' => 'Ada'), $add['cells']);
	pg_assert_true(ws_page_bridge_write_args('add', array('table' => '3', 'cells' => array()))['ok'], 'an empty row, the table by text');

	foreach (array(null, 0, -1, '3a', 1.5, array(3)) as $table) {
		pg_assert_same('invalid', ws_page_bridge_write_args('add', array('table' => $table, 'cells' => array()))['error']['code'], var_export($table, true) . ' is no table id');
	}

	foreach (array(null, 'x', 5, array('a', 'b')) as $cells) {
		pg_assert_same('invalid', ws_page_bridge_write_args('add', array('table' => 3, 'cells' => $cells))['error']['code'], var_export($cells, true) . ' are no cells');
	}

	$change = ws_page_bridge_write_args('change', array('table' => 3, 'row' => 9, 'revision' => '2', 'cells' => array('12' => 'Bo')));
	pg_assert_true($change['ok'], 'a change');
	pg_assert_same(9, $change['row']);
	pg_assert_same(2, $change['revision']);
	pg_assert_same('invalid', ws_page_bridge_write_args('change', array('table' => 3, 'revision' => 2, 'cells' => array()))['error']['code'], 'no row');
	pg_assert_same('invalid', ws_page_bridge_write_args('change', array('table' => 3, 'row' => 9, 'cells' => array()))['error']['code'], 'no revision');
}

function test_workspace_page_bridge_write_refusal()
{
	pg_assert_same('forbidden', ws_page_bridge_write_refusal(array('error' => 'not_found'))['error']['code'], 'the reader may not write it');

	$changed = ws_page_bridge_write_refusal(array('error' => 'changed_meanwhile', 'revision' => 6));
	pg_assert_same('changed_meanwhile', $changed['error']['code']);
	pg_assert_same(6, $changed['error']['revision'], 'the revision now, to read again');

	$cells = ws_page_bridge_write_refusal(array('error' => 'cells_invalid', 'field' => 'cells', 'errors' => array('12' => 'value')));
	pg_assert_same('invalid', $cells['error']['code']);
	pg_assert_same('cells', $cells['error']['field']);
	pg_assert_same(array('12' => 'value'), (array) $cells['error']['cells'], 'each refused cell');
	pg_assert_false(isset(ws_page_bridge_write_refusal(array('error' => 'too_many_rows'))['error']['cells']));
}

function test_workspace_wtable_row_via_given()
{
	pg_assert_same(0, ws_wtable_row_via_given(array()), 'any other write');
	pg_assert_same(0, ws_wtable_row_via_given(null));
	pg_assert_same(0, ws_wtable_row_via_given(array('via_message_id' => 0)));
	pg_assert_same(0, ws_wtable_row_via_given(array('via_message_id' => -4)));
	pg_assert_same(0, ws_wtable_row_via_given(array('via_message_id' => 'x')));
	pg_assert_same(42, ws_wtable_row_via_given(array('via_message_id' => 42)));

	$out = ws_wtable_row_out(array('id' => 1, 'position' => 1, 'revision' => 1, 'cells' => '{}', 'created_by' => 2, 'created_at' => 0,
		'updated_by' => 2, 'updated_at' => 0, 'via_message_id' => '42'), array());
	pg_assert_same(42, $out['via_message_id'], 'a read row names its canvas');
	pg_assert_same(42, ws_wtable_row_present($out)['via_message_id']);
	pg_assert_true(array_key_exists('via_message_id', ws_api_wtable_row_schema()), 'the API describes it');
}

function test_workspace_page_card_asks_before_a_write()
{
	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_page_card.js');

	pg_assert_true(strpos($js, "'ws_page_consent'") !== false, 'the answer is kept by the site');
	pg_assert_true(strpos($js, "question.state === 'ask'") !== false, 'the card asks only when the site says so');
	pg_assert_true(strpos($js, 'showModal') !== false, 'the question is the panel\'s dialog, over the page');

	$actions = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	pg_assert_true(strpos($actions, "unset(\$answer['ask'])") !== false, 'the page never sees the question');
}
