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

	pg_assert_true(isset($seen['route_match'], $seen['route_channel']), 'list fields not found in the card sources');

	// The department and people fields are named by the caller
	// (includes/workspace/team_settings.php): the old screen's names.
	foreach (array('members', 'leads', 'primary', 'people') as $name) {
		pg_assert_true(in_array($name, $allowed, true), $name . '[] is not in the list allow-list');
	}
}
