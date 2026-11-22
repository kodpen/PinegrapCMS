<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Unread filter of the Workspace list and its keys
 * (assets/js/workspace_side.js). Run under Node: sideUnread() says which rows
 * the filter keeps, sideStep() which row Alt+Up and Alt+Down go to. Then
 * what the list draws with them: the kept choice, the words, the reload.
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

function workspace_side_unread_test_source($file = 'workspace_side.js')
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);
}

// One function of the script's top level, from its line to the closing
// brace at its own indent; '' when it is not found.
function workspace_side_unread_test_function($name)
{
	$source = workspace_side_unread_test_source();
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// Runs the pure helpers under Node on the steps given and answers what each
// gave; null when Node is not installed.
function workspace_side_unread_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\n";

	foreach (array('sideUnread', 'sideStep') as $name) {
		$part = workspace_side_unread_test_function($name);
		pg_assert_true($part !== '', $name . ' is found in workspace_side.js');
		$script .= $part;
	}

	$script .= "\nvar steps = " . json_encode($steps) . ";\n"
		. "console.log(JSON.stringify(steps.map(function (step) {\n"
		. "  if (step[0] === 'unread') { return sideUnread(step[1], step[2]); }\n"
		. "  if (step[0] === 'step') { return sideStep(step[1], step[2], step[3], step[4]); }\n"
		. "  return null;\n"
		. "})));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsside');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return is_array($answer) ? $answer : array();
}

// A joined channel or a discussion with an unread count; a muted channel
// only when it mentions the reader; a channel not joined never.
function test_workspace_side_unread_rows_the_filter_keeps()
{
	$answer = workspace_side_unread_test_run(array(
		array('unread', array('joined' => true, 'unread' => 3, 'notify' => 'all'), false),
		array('unread', array('joined' => true, 'unread' => 0, 'notify' => 'all'), false),
		array('unread', array('joined' => true, 'unread' => 2, 'notify' => 'none', 'mentions' => 0), false),
		array('unread', array('joined' => true, 'unread' => 2, 'notify' => 'none', 'mentions' => 1), false),
		array('unread', array('joined' => true, 'unread' => 2, 'notify' => 'mentions'), false),
		array('unread', array('joined' => false, 'unread' => 5, 'notify' => 'all'), false),
		array('unread', array('unread' => 1), true),
		array('unread', array('unread' => 0), true),
		array('unread', null, false),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, false, false, true, true, false, true, false, false), $answer);
}

function workspace_side_unread_test_rows()
{
	return array(
		array('key' => 'c:1', 'unread' => false),
		array('key' => 'c:2', 'unread' => true),
		array('key' => 'c:3', 'unread' => false),
		array('key' => 'c:4', 'unread' => false),
		array('key' => 't:9', 'unread' => true),
	);
}

// The row before and after the open one; nothing past the edges; with no
// row open, from the top.
function test_workspace_side_unread_step_walks_the_rows()
{
	$rows = workspace_side_unread_test_rows();
	$answer = workspace_side_unread_test_run(array(
		array('step', $rows, 'c:2', 1, false),
		array('step', $rows, 'c:2', -1, false),
		array('step', $rows, 'c:1', -1, false),
		array('step', $rows, 't:9', 1, false),
		array('step', $rows, '', 1, false),
		array('step', $rows, '', -1, false),
		array('step', $rows, 'c:77', -1, false),
		array('step', array(), 'c:1', 1, false),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('c:3', 'c:1', '', '', 'c:1', 'c:1', 'c:1', ''), $answer);
}

// With Shift: the next row holding something new, either way.
function test_workspace_side_unread_step_with_shift_finds_the_next_unread()
{
	$rows = workspace_side_unread_test_rows();
	$answer = workspace_side_unread_test_run(array(
		array('step', $rows, 'c:1', 1, true),
		array('step', $rows, 'c:2', 1, true),
		array('step', $rows, 't:9', -1, true),
		array('step', $rows, 'c:2', -1, true),
		array('step', $rows, 't:9', 1, true),
		array('step', $rows, '', 1, true),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('c:2', 't:9', 'c:2', '', '', 'c:2'), $answer);
}

// The choice is kept in ws-side-filter; Unread drops the join row, sets the
// groups' folds aside and offers Mark all as read, which asks first.
function test_workspace_side_unread_draws_the_filter()
{
	$script = workspace_side_unread_test_source();

	pg_assert_contains("var FILTER_KEY = 'ws-side-filter';", $script);
	pg_assert_contains("var filter = el('div', 'pg-seg ws-side-filter');", $script, 'the look\'s segmented control');
	pg_assert_contains("item.setAttribute('aria-pressed', chosen ? 'true' : 'false');", $script);
	pg_assert_contains("var shut = !unreadOnly && !!closedGroups[item.id];", $script, 'a closed group opens while Unread is chosen');
	pg_assert_contains("if (unreadOnly && !groupListed(item.id)) {", $script, 'an empty group goes');
	pg_assert_contains("t('side_all_read')", $script);
	pg_assert_contains("window.pgConfirm({ title: t('side_read_all'), message: t('side_read_ask', count)", $script, 'asked once');
	pg_assert_contains("request('ws_read_all')", $script);
}

// The keys stay off dialogs, drawers, the Flows editor, selects and date
// fields, and a writing box's selection.
function test_workspace_side_unread_keys_leave_busy_places_alone()
{
	$busy = workspace_side_unread_test_function('keysBusy');

	pg_assert_contains("window.pgUI.anyOpen('dialog') || window.pgUI.anyOpen('drawer')", $busy);
	pg_assert_contains(".wsf-app:not(.d-none)", $busy, 'the Flows editor');
	pg_assert_contains("(tag === 'SELECT')", $busy);
	pg_assert_contains('target.selectionStart !== target.selectionEnd', $busy);
	pg_assert_contains("event.ctrlKey || event.metaKey", workspace_side_unread_test_source(), 'Ctrl and Cmd are the page\'s');
}

// The channel screen asks for its list again (threads too) after Mark all
// as read.
function test_workspace_side_unread_channel_screen_reloads()
{
	$workspace = workspace_side_unread_test_source('workspace.js');

	pg_assert_contains("reload: function () { self.reloadChannels(0); },", $workspace);
	pg_assert_contains("BOOT.threads = data.threads || BOOT.threads || [];", $workspace);
}

function test_workspace_side_unread_words()
{
	$strings = ws_side_js_strings();

	pg_assert_same(lang('Everything is read'), $strings['side_all_read'] ?? '');
	pg_assert_same(lang('Mark all as read'), $strings['side_read_all'] ?? '');
	pg_assert_same(lang(array('string' => 'Mark {var:1} channels as read?', 'vars' => '{1}')), $strings['side_read_ask'] ?? '');
	pg_assert_true(strpos($strings['side_keys'] ?? '', 'Alt+') !== false, 'the keys are named');
}
