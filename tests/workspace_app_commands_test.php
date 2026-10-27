<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the / commands an application registers
 * (includes/workspace/app_commands.php): which names it may take, and how
 * its description and usage line are checked and kept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/app_commands.php');

// A name is kept in Turkish lower case without its slash, the texts on one
// line, so the picker and the parser find it as typed.
function test_workspace_app_command_check_keeps_the_clean_shape()
{
	$ok = ws_app_command_check(array('name' => '/Özetle', 'description' => "  Sums up\nthe channel ", 'usage' => '[days]'));
	pg_assert_same(array('name' => 'özetle', 'description' => 'Sums up the channel', 'usage' => '[days]'), $ok, 'clean shape');

	$ok = ws_app_command_check(array('name' => 'Işık', 'description' => 'Light'));
	pg_assert_same('ışık', $ok['name'], 'Turkish dotless I');
	pg_assert_same('', $ok['usage'], 'usage may be left out');
}

// Only one word of letters reads as a command in the composer
// (ws_command_attempt()), so other shapes are refused.
function test_workspace_app_command_check_refuses_names_that_are_no_command()
{
	foreach (array('', 'a', 'sum-up', 'sum up', 'report2', 'abcdefghijklmnopqrstu', '//x') as $name) {
		pg_assert_same('name_invalid', ws_app_command_check(array('name' => $name, 'description' => 'x'))['error'], 'name ' . $name);
	}

	pg_assert_same('name_invalid', ws_app_command_check(array('name' => 5, 'description' => 'x'))['error'], 'not a string');
	pg_assert_same('name_invalid', ws_app_command_check(null)['error'], 'no input');
}

// A built-in name or an assistant's word means one thing, whichever case or
// slash it comes with.
function test_workspace_app_command_check_refuses_built_in_names()
{
	foreach (array('task', 'GÖREV', '/help', 'ürün', 'claude', 'AI') as $name) {
		pg_assert_same('name_taken', ws_app_command_check(array('name' => $name, 'description' => 'x'))['error'], 'name ' . $name);
	}
}

function test_workspace_app_command_check_bounds_the_texts()
{
	pg_assert_same('description_required', ws_app_command_check(array('name' => 'report', 'description' => " \n "))['error'], 'blank description');
	pg_assert_same('description_required', ws_app_command_check(array('name' => 'report'))['error'], 'no description');
	pg_assert_same('description_too_long', ws_app_command_check(array('name' => 'report', 'description' => str_repeat('ş', 201)))['error'], 'long description');
	pg_assert_same('Report', ws_app_command_check(array('name' => 'report', 'description' => 'Report', 'usage' => array('x')))['description'], 'usage not a string is empty');
	pg_assert_same('usage_too_long', ws_app_command_check(array('name' => 'report', 'description' => 'x', 'usage' => str_repeat('a', 201)))['error'], 'long usage');
	pg_assert_same(str_repeat('ş', 200), ws_app_command_check(array('name' => 'report', 'description' => str_repeat('ş', 200)))['description'], 'two hundred letters fit');
}
