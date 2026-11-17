<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the menus of the Workspace writing box on a phone: a change of
 * the window's height only (the keyboard going down) moves a menu standing
 * by a button of the box (ctxAfterChange(), cut out of
 * assets/js/workspace.js and run under Node, skipped on a machine without
 * Node), a new width or any other menu closes it, and every tool of the box
 * marks its menu as one that follows its button.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_composer_menu_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// The body of the named function, up to the next function at the same indentation.
function workspace_composer_menu_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, $name . '() is in workspace.js');
	$to = strpos($source, "\n    function ", $from + 10);

	return substr($source, $from, $to === false ? null : $to - $from);
}

// Runs a JavaScript expression over the cut function; null without Node.
function workspace_composer_menu_test_run($expr)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = workspace_composer_menu_test_function(workspace_composer_menu_test_source(), 'ctxAfterChange') . "\n"
		. 'console.log(JSON.stringify(' . $expr . '));';
	$file = tempnam(sys_get_temp_dir(), 'pgwsmenu');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function test_workspace_composer_menu_follows_a_height_change()
{
	$got = workspace_composer_menu_test_run('[ctxAfterChange(390, 390, true), ctxAfterChange(390, 390, false), ctxAfterChange(390, 844, true), ctxAfterChange(844, 390, false)]');

	if ($got === null) {
		return;
	}

	pg_assert_same('place', $got[0], 'the keyboard going down moves a menu of the box');
	pg_assert_same('close', $got[1], 'a menu of a message row still closes');
	pg_assert_same('close', $got[2], 'a turn of the phone closes a menu of the box');
	pg_assert_same('close', $got[3], 'a new width closes any menu');
}

function test_workspace_composer_menu_tools_follow_their_button()
{
	$source = workspace_composer_menu_test_source();

	foreach (array('attach', 'emoji', 'insert', 'later') as $button) {
		pg_assert_contains('ctxFollow(' . $button . ');', $source, 'the ' . $button . ' menu of the box stands by its button');
	}

	pg_assert_contains('ctxSettle(x, y);', workspace_composer_menu_test_function($source, 'ctxMenu'), 'a menu takes its anchor');
	pg_assert_contains('ctxSettle(x, y);', workspace_composer_menu_test_function($source, 'floatBox'), 'a floating box takes its anchor');
	pg_assert_true(strpos($source, "window.addEventListener('resize', closeCtx);") === false, 'a resize no longer closes every menu');
}
