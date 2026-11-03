<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for Workspace Settings: every field a settings card posts as a
 * list (name="x[]" or name="x[key]") is in the list allow-list the screen
 * passes to pg_request_unexpected_lists(). A list field missing there makes
 * every save of its card fail with "the value you entered is not valid".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_settings_lists_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

/**
 * The names the screen accepts as lists.
 *
 * @return string[]
 */
function workspace_settings_lists_allowed()
{
	$source = workspace_settings_lists_source('workspace_settings.php');

	if (!preg_match('/\$ws_settings_lists = pg_request_unexpected_lists\(\$_POST, array\((.*?)\)\);/s', $source, $match)) {
		return array();
	}

	preg_match_all("/'([a-z_]+)'\s*=>\s*\d/", $match[1], $names);

	return $names[1];
}

function test_workspace_settings_lists_allow_list_found()
{
	$allowed = workspace_settings_lists_allowed();

	pg_assert_true(in_array('people', $allowed, true), 'allow-list not found in workspace_settings.php');
}

function test_workspace_settings_lists_every_card_list_allowed()
{
	$allowed = workspace_settings_lists_allowed();
	$files = array(
		'workspace_settings.php',
		'includes/workspace/ai.php',
		'includes/workspace/bulk.php',
		'includes/workspace/claude.php',
		'includes/workspace/claude_setup.php',
		'includes/workspace/mail_route.php',
		'includes/workspace/mail_settings.php',
		'includes/workspace/mcp.php',
		'includes/workspace/templates.php',
		'includes/workspace/voice.php',
		'includes/workspace/workdays.php',
	);
	$seen = array();

	foreach ($files as $file) {
		preg_match_all('/name="([a-z_]+)\[/', workspace_settings_lists_source($file), $names);

		foreach ($names[1] as $name) {
			$seen[$name] = true;
			pg_assert_true(in_array($name, $allowed, true), $file . ': ' . $name . '[] is not in the list allow-list');
		}
	}

	pg_assert_true(isset($seen['route_match'], $seen['route_channel'], $seen['members']), 'list fields not found in the card sources');
}

function test_workspace_settings_may_edit_person()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

	pg_assert_true(ws_settings_may_edit_person(0, 0), 'an administrator changes another administrator');
	pg_assert_true(ws_settings_may_edit_person(0, 3), 'an administrator changes a basic user');
	pg_assert_true(ws_settings_may_edit_person(2, 2), 'a manager changes another manager');
	pg_assert_true(ws_settings_may_edit_person(2, 3), 'a manager changes a basic user');
	pg_assert_true(ws_settings_may_edit_person(3, 3), 'a basic user changes another basic user');
	pg_assert_true(!ws_settings_may_edit_person(2, 0), 'a manager takes an administrator out');
	pg_assert_true(!ws_settings_may_edit_person(2, 1), 'a manager takes a designer out');
	pg_assert_true(!ws_settings_may_edit_person(3, 2), 'a basic user takes a manager out');
	pg_assert_true(!ws_settings_may_edit_person(1, 0), 'a designer takes an administrator out');
}

function test_workspace_settings_people_rank_checked()
{
	$source = workspace_settings_lists_source('workspace_settings.php');

	pg_assert_true(strpos($source, "if (!ws_settings_may_edit_person(\$viewer['role'], \$candidates[\$person_id]['role'])) {") !== false, 'the people save does not check the rank');
	pg_assert_true(strpos($source, "\$locked = !ws_settings_may_edit_person(\$viewer['role'], \$person['role']);") !== false, 'a higher rank\'s row is not drawn read-only');
}
