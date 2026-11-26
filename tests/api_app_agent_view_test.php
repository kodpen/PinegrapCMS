<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Application Access's AI agent tab and the sheet's agent fields
 * (includes/api/app_agent_view.php): the names the save reads, the tab
 * drawn into the drawer, the sheet's fields posted only for their card, and
 * the screen's one POST branch.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/app_drawer_view.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/app_agent_view.php');

function api_app_agent_view_test_channels()
{
	return array(array('id' => 4, 'name' => 'sales', 'kind' => 'public'), array('id' => 7, 'name' => 'board <b>', 'kind' => 'private'));
}

function test_api_app_agent_view_tab_names()
{
	$pane = api_app_agent_tab_html(array('worker' => 'Worker'), api_app_agent_view_test_channels(), 'START');

	foreach (array('name="agent_handle"', 'name="agent_name"', 'name="agent_role"', 'name="agent_door_kind" value="poll"', 'name="agent_door_kind" value="push"',
		'name="agent_door_url"', 'name="agent_channels[]" value="4"', 'name="agent_channels_sent" value="1"', 'name="api_action" value="agent_setup"') as $name) {
		pg_assert_contains($name, $pane, $name);
	}

	pg_assert_contains('id="d_agent"', $pane, 'the pane the button targets');
	pg_assert_contains('data-bs-target="#d_agent"', api_app_agent_tab_button(), 'the button');
	pg_assert_contains('START', $pane, 'the start text with Copy');
	pg_assert_contains('board &lt;b&gt;', $pane, 'a channel name escaped');
	pg_assert_contains('workspace.php?channel=7', $pane, 'each row links to its channel');
	pg_assert_contains('bi-lock', $pane, 'a private channel marked');
	pg_assert_false(strpos($pane, 'btn btn-') !== false, 'look A buttons');
	pg_assert_contains('There is no channel you manage yet.', api_app_agent_channels_html('x', array()), 'no channel');
}

function test_api_app_agent_view_sheet_fields_only_for_their_card()
{
	$sheet = api_app_agent_sheet_html(array(), api_app_agent_view_test_channels());

	pg_assert_contains('id="n_agent" hidden disabled', $sheet, 'a disabled fieldset posts nothing for another card');
	pg_assert_contains('name="agent_handle"', $sheet, 'the handle');
	pg_assert_contains('name="agent_channels[]"', $sheet, 'the channels');
}

function test_api_app_agent_view_drawer_takes_the_tab()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/app_drawer_view.php');

	pg_assert_contains("\$parts['agent_tab']", $source, 'the tab row');
	pg_assert_contains("\$parts['agent_pane']", $source, 'the pane');
}

function test_api_app_agent_view_screen_branch()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/api_settings.php');

	pg_assert_contains("'agent_channels' => 1", $screen, 'the channel list is the one list besides the permissions and events');
	pg_assert_contains("\$action === 'agent_setup'", $screen, 'one POST branch');
	pg_assert_contains('api_app_agent_setup_post($user, $app_id, $_POST, $liveform)', $screen, 'handed to the view');
	pg_assert_contains("if (\$start === 'agent')", $screen, 'Agent in a channel after Create');
	pg_assert_contains('pg:api-app-open', $screen, 'the drawer tells the tab which application opened');

	// The row-rank check runs before every branch on an existing row.
	pg_assert_true(strpos($screen, 'api_app_row_locked($rank_row') < strpos($screen, "\$action === 'agent_setup'"), 'the locked row refused first');
}

// Return in a field of the AI agent tab saves the agent. The drawer's form
// starts with a hidden default button and posts api_action=update, so Return
// saved the application and said so, and the agent was not made.
function test_api_app_agent_view_return_saves_the_agent()
{
	$script = api_app_agent_script(array(), 0);

	pg_assert_contains('e.key !== "Enter"', $script, 'Return is caught');
	pg_assert_contains('e.target.closest("#d_agent")', $script, 'in the AI agent tab only');
	pg_assert_contains('document.getElementById("da_save").click()', $script, 'the agent\'s own button posts');
}
