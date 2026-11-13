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
	// The forms the old Workspace Settings screen drew are still kept in the
	// card functions (their $form === null branch) and draw these names;
	// nothing posts them now, as workspace_settings.php refuses a POST.
	return array('workdays', 'members', 'leads', 'primary', 'people', 'mcp_tool', 'route_match', 'route_channel', 'github_events');
}

function test_workspace_settings_lists_old_screen_posts_nothing()
{
	$source = workspace_settings_lists_source('workspace_settings.php');

	pg_assert_false(strpos($source, '$_POST') !== false, 'the old address reads no form');
	pg_assert_contains("output_error(h(lang('The Workspace settings are in the Site Settings now. Nothing was saved.'))", $source, 'a POST is refused');
}

function test_workspace_settings_lists_every_card_list_allowed()
{
	// A card that moved to Site Settings draws its dialog-only lists
	// (ws_mail[...]) there; that category's 'lists' entry names them.
	preg_match_all("/'lists'\s*=>\s*array\((.*?)\)/s", workspace_settings_lists_source('includes/settings/registry.php'), $lists);
	preg_match_all("/'(ws_[a-z0-9_]*)'\s*=>\s*[1-9]/", implode("\n", $lists[1]), $dialog);
	$allowed = array_merge(workspace_settings_lists_allowed(), $dialog[1]);
	$files = array(
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
