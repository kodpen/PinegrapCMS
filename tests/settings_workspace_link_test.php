<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Workspace Settings button and every old link to workspace_settings.php
 * open the Workspace categories of the Site Settings dialog
 * (pg_settings_workspace_link()); the old address only redirects there and
 * refuses a form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_workspace_link_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_settings_workspace_link_first_category()
{
	pg_assert_same('settings_workspace_team.php', pg_settings_workspace_link(), 'no anchor: the first Workspace category');
	pg_assert_same('settings_workspace_team.php', pg_settings_workspace_link('ws-nothing'), 'an anchor no card answers: the first too');
	pg_assert_same('settings_workspace_team.php', pg_settings_workspace_link('settings'), 'a word with no dash');
}

function test_settings_workspace_link_old_anchors()
{
	pg_assert_same('settings_workspace_team.php#pgset-ws-departments', pg_settings_workspace_link('#ws-departments'));
	pg_assert_same('settings_workspace_assistants.php#pgset-ws-claude', pg_settings_workspace_link('ws-claude'));
	pg_assert_same('settings_workspace_assistants.php#pgset-ws-mcp', pg_settings_workspace_link('ws-mcp-4'), 'a server of the MCP card names the card');
	pg_assert_same('settings_workspace_channels.php#pgset-ws-mail', pg_settings_workspace_link('ws-mail'));
	pg_assert_same('settings_workspace_channels.php#pgset-ws-templates', pg_settings_workspace_link('ws-templates'));
	pg_assert_same('settings_workspace_channels.php#pgset-ws-voice', pg_settings_workspace_link('pgset-ws-voice'), 'a card\'s own anchor');
}

function test_settings_workspace_link_every_old_anchor_lands_in_its_category()
{
	foreach (pg_settings_legacy_anchors() as $old => $target) {
		if (strpos($old, 'ws-') === 0) {
			pg_assert_same(pg_settings_link($target[0], $target[1]), pg_settings_workspace_link($old), $old);
		}
	}
}

function test_settings_workspace_link_old_screen_redirects()
{
	$source = settings_workspace_link_test_source('workspace_settings.php');

	pg_assert_contains('ws_screen_gate($user)', $source, 'the same gate as before');
	pg_assert_contains("pg_settings_workspace_link((\$connected > 0) ? 'ws-mcp' : '', \$user)", $source, 'the first category the reader may open');
	pg_assert_contains("output_error(h(lang('The Workspace settings are in the Site Settings now. Nothing was saved.'))", $source, 'a form is refused');
	pg_assert_contains(', 405);', $source, 'as a method not allowed');
	pg_assert_contains('go($target);', $source, 'everything else is sent on');
	pg_assert_false(strpos($source, 'liveform') !== false, 'nothing is saved or drawn');
}

function test_settings_workspace_link_no_link_left_to_the_old_screen()
{
	$files = array(
		'includes/workspace/nav.php', 'includes/workspace/screen.php', 'workspace_mcp.php', 'workspace_template.php',
		'includes/designer_ai.php', 'includes/fn/system_status.php', 'includes/dashboard/v2/cards/ai.php',
		'includes/settings/registry.php', 'includes/workspace/templates.php', 'includes/workspace/claude.php',
		'includes/workspace/ai.php', 'includes/workspace/mail_settings.php', 'includes/workspace/mcp.php',
	);

	foreach ($files as $file) {
		// The side menu still lights Workspace Settings for the old address
		// (ws_side_screen()): a name in its map, not a link.
		$lines = preg_grep("/=> 'settings',/", explode("\n", settings_workspace_link_test_source($file)), PREG_GREP_INVERT);
		pg_assert_same(array(), preg_grep("/['\\/]workspace_settings\\.php[#'?]/", $lines), $file . ' links the old screen');
	}
}
