<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The other cards of Site Settings' "Assistants and agents" in the panel's
 * look, by the recipe of the agents' rows and drawer: the Claude and
 * Pinegrap AI state pills, the licence pill's tone, the Copy field, the new
 * secret's drawer foot, the MCP servers' rows and the tools' Save in the
 * server drawer's foot, with the field and button names unchanged.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/drawer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function settings_workspace_assistant_cards_look_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function settings_workspace_assistant_cards_look_test_function($file, $name)
{
	$source = settings_workspace_assistant_cards_look_test_source($file);
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' is defined in ' . $file);
	$end = strpos($source, "\n}\n", $start);

	return substr($source, $start, $end - $start);
}

function test_settings_workspace_assistant_cards_look_state_pill()
{
	pg_assert_same('<span class="pg-pill pg-tone-teal">' . h(lang('Ready')) . '</span>', ws_assistant_state_pill(array(), false), 'nothing missing is ready, switched on or not');
	pg_assert_same('<span class="pg-pill pg-tone-amber">' . h(lang('Not ready')) . '</span>', ws_assistant_state_pill(array('x'), true));
	pg_assert_same('<span class="pg-pill pg-tone-gray">' . h(lang('Off')) . '</span>', ws_assistant_state_pill(array('x'), false));

	foreach (array('includes/workspace/claude.php' => 'ws_claude_settings_card', 'includes/workspace/ai.php' => 'ws_ai_settings_card') as $file => $card) {
		pg_assert_contains('ws_assistant_state_pill($missing, $config[\'enabled\'])', settings_workspace_assistant_cards_look_test_function($file, $card), $card . ' draws the pill');
	}
}

function test_settings_workspace_assistant_cards_look_license_tone()
{
	pg_assert_same('pg-tone-teal', ws_ai_license_tone('success'));
	pg_assert_same('pg-tone-amber', ws_ai_license_tone('warning'));
	pg_assert_same('pg-tone-rose', ws_ai_license_tone('danger'));
	pg_assert_same('pg-tone-gray', ws_ai_license_tone('secondary'));
	pg_assert_same('pg-tone-gray', ws_ai_license_tone('anything'), 'an unknown tone is gray');
}

function test_settings_workspace_assistant_cards_look_copy_field()
{
	$field = ws_claude_copy_field('k1', 'v<1>');

	pg_assert_contains('class="pg-input pg-input-sm font-monospace"', $field, 'one line is the small field');
	pg_assert_contains('value="v&lt;1&gt;"', $field, 'the value escaped');
	pg_assert_contains('class="pg-icon-button pg-icon-button-sm no-popover" data-ws-copy-field="k1"', $field, 'Copy is an icon button the script finds');
	pg_assert_contains('<textarea class="pg-input font-monospace"', ws_claude_copy_field('k2', 'v', 4), 'more lines are a text area');
}

function test_settings_workspace_assistant_cards_look_secret_foot()
{
	$foot = ws_claude_credentials_drawer_foot_html();

	pg_assert_contains('class="pg-checkbox" type="checkbox" id="pgset-ws-claude-secret-ack"', $foot, 'the tick');
	pg_assert_contains('id="pgset-ws-claude-secret-done" disabled', $foot, 'Close waits for it');
	pg_assert_false(strpos($foot, 'name=') !== false, 'nothing of it is posted with the form');
	pg_assert_false(strpos(settings_workspace_assistant_cards_look_test_function('includes/workspace/claude.php', 'ws_claude_credentials_drawer_html'), 'id="pgset-ws-claude-secret-done"') !== false, 'Close is not in the body any more');
}

function test_settings_workspace_assistant_cards_look_mcp_rows()
{
	$card = settings_workspace_assistant_cards_look_test_function('includes/workspace/mcp.php', 'ws_mcp_settings_card');

	pg_assert_contains('<div class="ws-agset-row\'', $card, 'a server is a row by the agents\' recipe');
	pg_assert_contains('<span class="pg-pill ws-agset-state \' . $badge[0] . \'">', $card, 'its state a pill');
	// Tools and settings is the row's last part.
	pg_assert_contains('. $sign_in . $admin_buttons . $tools_button . \'</div>\';', $card, 'Tools and settings at the row\'s end');

	foreach (array('mcp_connect', 'mcp_disconnect', 'mcp_refresh', 'mcp_toggle', 'mcp_delete') as $action) {
		pg_assert_contains("ws_mcp_settings_button(\$self_url, \$form, '" . $action . "', \$id,", $card, $action . ' keeps its named button');
	}

}

function test_settings_workspace_assistant_cards_look_mcp_drawer_foot()
{
	$drawer = settings_workspace_assistant_cards_look_test_function('includes/workspace/mcp.php', 'ws_settings_mcp_drawer');
	$tools = settings_workspace_assistant_cards_look_test_function('includes/workspace/mcp.php', 'ws_mcp_settings_tools');

	pg_assert_contains('name="ws_mcp_tools" value="\' . $id . \'" form="\' . h($form) . \'" class="pg-button pg-button-fill pg-button-sm"', $drawer, 'the tools\' Save in the foot, its name and value kept');
	pg_assert_contains('$in_dialog, $foot);', $drawer, 'handed to the drawer');
	pg_assert_contains('(($admin && ($form === null)) ? $save', $tools, 'the list draws its own Save only off the dialog');
}

function test_settings_workspace_assistant_cards_look_no_old_buttons()
{
	foreach (array('includes/workspace/claude.php', 'includes/workspace/claude_setup.php', 'includes/workspace/ai.php', 'includes/workspace/mcp.php', 'includes/workspace/mcp_catalogue.php') as $file) {
		pg_assert_false(strpos(settings_workspace_assistant_cards_look_test_source($file), 'btn btn-') !== false, $file . ' draws no old button');
	}
}

function test_settings_workspace_assistant_cards_look_alias_fields()
{
	// The @ before the alias is the look's field wrapper around a bare input.
	foreach (array('includes/workspace/mcp.php', 'includes/workspace/mcp_catalogue.php') as $file) {
		$source = settings_workspace_assistant_cards_look_test_source($file);

		pg_assert_contains('<label class="pg-input pg-input-sm', $source, $file . ': the wrapper');
		pg_assert_contains('<span class="pg-text-meta" aria-hidden="true">@</span>', $source, $file . ': the @');
		pg_assert_false(strpos($source, 'form-adorn') !== false, $file . ': no Bootstrap adorn left');
	}
}
