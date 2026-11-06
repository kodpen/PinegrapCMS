<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the help under a value field on the Flows canvas
 * (valuesHint(), assets/js/workspace_flow.js): it names {{date}} and the
 * other words of scheduled actions only in the boxes whose texts the run
 * fills with them. A derived value box said they worked, and
 * "{{date}} {{time}}" written by one into a table cell stayed as typed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// The canvas's word kinds are the step kinds the run fills
// (ws_step_scheduled_texts()) and the task box (ws_flow_task_texts()).
function test_workspace_flow_values_hint_word_kinds()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	preg_match('~\n    var WORD_KINDS = \[([^\]]*)\];~', $js, $list);
	pg_assert_true(!empty($list), 'the list');
	preg_match_all("~'([a-z_]+)'~", $list[1] ?? '', $names);
	$canvas = $names[1];
	$filled = array_merge(array_keys(ws_step_scheduled_texts()), array('task'));
	sort($canvas);
	sort($filled);

	pg_assert_same($filled, $canvas, 'the same kinds');
	pg_assert_true(!in_array('value', $canvas, true), 'not a derived value');
	pg_assert_true(!in_array('row_set', $canvas, true), 'nor a table cell');
	pg_assert_true(!in_array('checkin', $canvas, true), 'nor a check-in');

	$fill = ws_flow_task_texts(array('title' => 'Due {{date}}', 'description' => ''), array(), function ($text) {
		return str_replace('{{date}}', '05.11.2026', $text);
	});
	pg_assert_same('Due 05.11.2026', $fill['title'], 'a task fills them');
}

// Every value field asks it; both texts are translated.
function test_workspace_flow_values_hint_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$flows = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');

	pg_assert_contains("var wrap = field(label, input, help ? help + ' ' + valuesHint(node) : valuesHint(node));", $js, 'the value field');
	pg_assert_same(1, substr_count($js, "'fl_values_hint'"), 'only through it');
	pg_assert_contains("'fl_values_hint_plain' => lang('Add value puts in what started the run or what an earlier box gave.'),", $flows, 'the plain help');
}
