<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the words "Add value" offers under "Always" on the Flows canvas
 * (alwaysWords(), assets/js/workspace_flow.js): {{date}} and {{time}} only
 * in the boxes whose texts the run fills with them. A derived value box said
 * they worked, and "{{date}} {{time}}" written by one into a table cell
 * stayed as typed. The e-mail's recipient words (ws_flow_mail_words()) are
 * the ones its send path fills.
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

// No hint stands under a value field any more: the list's first line says
// what a value is and "Always" names the words.
function test_workspace_flow_values_hint_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$flows = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');

	pg_assert_contains("var wrap = field(label, input, help);", $js, 'the value field passes only its own help');
	pg_assert_same(0, substr_count($js, "'fl_values_hint"), 'no hint key');
	pg_assert_same(0, substr_count($js, 'valuesHint('), 'no hint function');
	pg_assert_contains("var always = alwaysWords(node.kind);", $js, 'Always in the list');
	pg_assert_contains("'fl_values_what'      => lang(", $flows, 'the list\'s first line');
}

// The recipient words an e-mail box offers are the fields its mail fills for
// the person it goes to, each labelled.
function test_workspace_flow_values_mail_words()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_mail_campaign.php');

	$words = ws_flow_mail_words();
	$filled = array_map(function ($field) {
		return $field['name'];
	}, pg_mail_merge_contact_fields(null));

	pg_assert_same($filled, array_column($words, 'key'), 'the same words, in order');
	pg_assert_same(array('name', 'first_name', 'last_name'), array_column($words, 'key'), 'name, first and last name');

	foreach ($words as $word) {
		pg_assert_true($word['label'] !== '' && $word['label'] !== $word['key'], 'a label for ' . $word['key']);
	}

	$flows = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
	pg_assert_contains("\$steps['mail_words'] = function_exists('ws_flow_mail_words') ? ws_flow_mail_words() : array();", $flows, 'in the editor\'s settings');
}
