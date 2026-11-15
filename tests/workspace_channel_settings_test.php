<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Channel settings drawer (channelForm() in
 * assets/js/workspace.js). Run under Node: channelSettingsTabs() gives the
 * tabs a channel shows, General first and the others only when they hold
 * something; eraRows() lays "Conversation versions" out as one list, the
 * current conversation first, then the earlier versions newest first. The
 * rest holds the wiring: the segmented control at the top, the icon and
 * colour chosen from buttons beside the name, the switches at the end of
 * their rows, Add people and Manage moved out of Channel info for a manager.
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

function workspace_channel_settings_test_source($file = 'workspace.js')
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);
}

// One "function name(" of workspace.js up to the closing brace at its own
// indent, or ''.
function workspace_channel_settings_test_function($name)
{
	$source = workspace_channel_settings_test_source();
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// Runs the expression on each case under Node and answers what it gave;
// null when Node is not installed.
function workspace_channel_settings_test_run($expression, $cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\n";

	foreach (array('channelSettingsTabs', 'eraRows') as $name) {
		$text = workspace_channel_settings_test_function($name);
		pg_assert_true($text !== '', $name . '() is found');
		$script .= $text;
	}

	$script .= "\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (one) { return " . $expression . "; })));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwschset');
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

// General is always first; People and agents, Conversation and Events only
// when the channel has something to show there, always in that order.
function test_workspace_channel_settings_tabs()
{
	$answer = workspace_channel_settings_test_run('channelSettingsTabs(one)', array(
		array('people' => true, 'conversation' => true, 'events' => true),
		array(),
		array('events' => true, 'people' => true),
		array('conversation' => true, 'people' => false),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('general', 'people', 'conversation', 'events'), $answer[0], 'every tab, in its order');
	pg_assert_same(array('general'), $answer[1], 'General alone');
	pg_assert_same(array('general', 'people', 'events'), $answer[2], 'the order is the drawer\'s, not the caller\'s');
	pg_assert_same(array('general', 'conversation'), $answer[3], 'a part with nothing is left out');
}

// One list: the current conversation first, with Clear only for whoever may
// clear it; then every earlier version, newest first, each with Read and,
// for whoever may clear, Rename.
function test_workspace_channel_settings_version_rows()
{
	$data = array(
		'since_text' => '12 Nov',
		'count'      => 7,
		'can_clear'  => true,
		'eras'       => array(
			array('id' => 4, 'number' => 1, 'label' => 'Version 1'),
			array('id' => 9, 'number' => 3, 'label' => 'Before the launch'),
			array('id' => 6, 'number' => 2, 'label' => 'Version 2'),
		),
	);
	$reader = $data;
	$reader['can_clear'] = false;
	$reader['eras'] = array();
	$reader['count'] = 0;
	$reader['since_text'] = '';

	$answer = workspace_channel_settings_test_run('eraRows(one)', array($data, $reader));

	if ($answer === null) {
		return;
	}

	$rows = $answer[0];
	pg_assert_same(4, count($rows), 'the current conversation and three versions');
	pg_assert_same('current', $rows[0]['kind'], 'the current conversation first');
	pg_assert_same('12 Nov', $rows[0]['since_text']);
	pg_assert_same(7, $rows[0]['count']);
	pg_assert_same(array('clear'), $rows[0]['actions'], 'Clear belongs to the current row');
	pg_assert_same(array(9, 6, 4), array($rows[1]['id'], $rows[2]['id'], $rows[3]['id']), 'newest first');
	pg_assert_same(array('rename', 'read'), $rows[1]['actions'], 'Rename in the row, Read at its end');

	$alone = $answer[1];
	pg_assert_same(1, count($alone), 'never cleared: the current conversation alone');
	pg_assert_same(array(), $alone[0]['actions'], 'no Clear for a reader who may not clear');
}

// The drawer's frame: the sheet's segmented control at the top, the panes
// kept in the page while hidden, so Save reads every tab's fields.
function test_workspace_channel_settings_frame()
{
	$body = workspace_channel_settings_test_function('channelForm');

	pg_assert_true($body !== '', 'channelForm() is found');
	pg_assert_contains("'pg-seg ws-settings-tabs'", $body, 'the sheet\'s segmented control');
	pg_assert_contains("'pg-seg-item'", $body);
	pg_assert_contains('aria-pressed', $body);
	pg_assert_contains('channelSettingsTabs(', $body);
	pg_assert_contains('.hidden = ', $body, 'a pane is hidden, never removed');
	pg_assert_true(strpos($body, 'formRow(t(\'color\')') === false, 'no colour list stands open');
	pg_assert_true(strpos($body, 'formRow(t(\'icon\')') === false, 'no icon list stands open');
	pg_assert_contains('channelLook(', $body, 'icon and colour beside the name');
}

// Icon and colour: each a button that opens its list in a floating box.
function test_workspace_channel_settings_icon_and_colour_buttons()
{
	$body = workspace_channel_settings_test_function('channelLook');

	pg_assert_true($body !== '', 'channelLook() is found');
	pg_assert_contains('floatBox(', $body, 'the lists float, like a popover');
	pg_assert_contains("setAttribute('aria-haspopup', 'dialog')", $body);
	pg_assert_contains('iconPicker(', $body);
	pg_assert_contains('colorPicker(', $body);
}

// A switch sits at the end of its row, its title and help line at the left,
// as a row of Site Settings.
function test_workspace_channel_settings_switch_rows()
{
	$events = workspace_channel_settings_test_source('workspace_events.js');

	pg_assert_contains("'pg-card-inner-row ws-switch-row'", $events, 'the events switch is a settings row');
	pg_assert_contains("'pg-switch'", $events);
	pg_assert_true(strpos($events, "el('div', 'switch')") === false, 'no switch before its label any more');
}

// The versions' texts: one help line under the title, the current row's
// count.
function test_workspace_channel_settings_version_texts()
{
	$texts = ws_eras_js_strings();

	foreach (array('era_help', 'era_current_count') as $key) {
		pg_assert_true(isset($texts[$key]), $key . ' is given');
	}

	$body = workspace_channel_settings_test_function('eraSection');
	pg_assert_contains('eraRows(', $body, 'the list is drawn from eraRows()');
	pg_assert_contains("t('era_help')", $body);
}

// Channel info only tells: a manager adds people and manages agents in the
// People and agents tab; whoever else may add people keeps the button there.
function test_workspace_channel_settings_info_moves()
{
	$source = workspace_channel_settings_test_source();

	pg_assert_true(strpos($source, "'ws-info-manage'") === false && strpos($source, ' ws-info-manage') === false, 'no Manage in Channel info');
	pg_assert_true(strpos($source, 'channelForm(channel, { agents: true }') === false, 'no drawer opened scrolled to the agents');
	pg_assert_contains('!channel.can_manage && channel.can_post', $source, 'Add people stays in Channel info only for a member who is not a manager');
}
