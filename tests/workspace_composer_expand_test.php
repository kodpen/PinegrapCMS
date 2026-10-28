<?php

/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the composer's "Aa" expanded box (assets/js/workspace_editor.js).
 * Run under Node: enterSends() answers whether Enter sends from the keys held
 * with it, in the one-line box and the expanded one; shortcutRows() lists
 * Enter and Ctrl+Enter as the expanded box takes them. The rest holds the
 * wiring: the Editor's keydown asks enterSends(), toolbar() puts the switch
 * in for a box that sends only, its labels sent from ws_editor_js_strings().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/workspace_editor_link_keys_test.php');

// Enter in the one-line box sends (not with Shift, not on a touch screen);
// in the expanded box only Ctrl+Enter or Cmd+Enter sends.
function test_workspace_composer_expand_enter_sends()
{
	$cases = array(
		array('keys' => array(), 'expanded' => false, 'touch' => false, 'sends' => true, 'name' => 'Enter sends in the one-line box'),
		array('keys' => array('shiftKey' => true), 'expanded' => false, 'touch' => false, 'sends' => false, 'name' => 'Shift+Enter is a new line there'),
		array('keys' => array('ctrlKey' => true), 'expanded' => false, 'touch' => false, 'sends' => true, 'name' => 'Ctrl+Enter sends there as before'),
		array('keys' => array(), 'expanded' => false, 'touch' => true, 'sends' => false, 'name' => 'Enter is a new line on a touch screen'),
		array('keys' => array(), 'expanded' => true, 'touch' => false, 'sends' => false, 'name' => 'Enter is a new line in the expanded box'),
		array('keys' => array('ctrlKey' => true), 'expanded' => true, 'touch' => false, 'sends' => true, 'name' => 'Ctrl+Enter sends there'),
		array('keys' => array('metaKey' => true), 'expanded' => true, 'touch' => false, 'sends' => true, 'name' => 'Cmd+Enter sends there'),
		array('keys' => array('ctrlKey' => true), 'expanded' => true, 'touch' => true, 'sends' => true, 'name' => 'Ctrl+Enter sends from a touch screen\'s keyboard too'),
		array('keys' => array('ctrlKey' => true, 'shiftKey' => true), 'expanded' => true, 'touch' => false, 'sends' => false, 'name' => 'Ctrl+Shift+Enter does not send'),
	);
	$answer = workspace_editor_link_keys_test_run(array('enterSends'), 'cases.map(function (one) { return enterSends(one.keys, one.expanded, one.touch); })', $cases);

	if ($answer === null) {
		return;
	}

	foreach ($cases as $i => $case) {
		pg_assert_same($case['sends'], $answer[$i], $case['name']);
	}
}

// The shortcut sheet of the expanded box: Enter a new line, Ctrl+Enter sends.
function test_workspace_composer_expand_shortcut_rows()
{
	$answer = workspace_editor_link_keys_test_run(array('shortcutRows'), "shortcutRows('ctrl', true)", array());

	if ($answer === null) {
		return;
	}

	$keys = array_map(function ($row) { return $row[0]; }, $answer);

	pg_assert_same(array('Control+B', 'Control+I', 'Control+Shift+X', 'Control+Shift+H', 'Control+E', 'Control+K', 'Control+Z', 'Control+Y', 'Enter', 'Control+Enter', 'Control+/'), $keys, 'the expanded box');
	pg_assert_same(array('Enter', 'keys_newline'), $answer[8], 'Enter is a new line');
	pg_assert_same(array('Control+Enter', 'keys_send'), $answer[9], 'Ctrl+Enter sends');
}

// The wiring in the editor and the labels on the screen.
function test_workspace_composer_expand_wiring()
{
	$editor = workspace_editor_lists_test_function('Editor');
	pg_assert_contains('enterSends(event, self.expanded', $editor, 'the box asks enterSends()');
	pg_assert_contains('expandedOn()', $editor, 'a box that sends opens as the viewer left it');

	$toolbar = workspace_editor_lists_test_function('toolbar');
	pg_assert_contains("editor.options.onEnter && !named(list, 'expand')", $toolbar, 'the switch only for a box that sends');
	pg_assert_contains('editor.expand(!editor.expanded)', $toolbar, 'it switches the box');
	pg_assert_contains("'tool_expand_hint'", $toolbar, 'the hint names Ctrl+Enter');

	$sheet = workspace_editor_lists_test_function('openKeys');
	pg_assert_contains("editor.expanded ? 'ctrl'", $sheet, 'the sheet lists the expanded box\'s keys');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	foreach (array('tool_expand', 'tool_expand_hint') as $key) {
		pg_assert_contains("'" . $key . "'", $screen, $key . ' is sent to the editor');
	}

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains('.ws-composer-tools.is-expanded .ws-composer-format', $css, 'the expanded row shows every tool');
}
