<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application agent's start text on the Agents card
 * (includes/workspace/agent_start_text.php): the text names the Site API,
 * the instructions route and HTTP Basic from the environment and holds no
 * credential; only an application agent's drawer gets the box with Copy.
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

function workspace_agent_start_text_test_agent($kind)
{
	return array('id' => 7, 'kind' => $kind, 'handle' => 'helper', 'name' => 'Helper', 'app_id' => 3,
		'api_key' => 'pgk_live_key_value', 'api_secret' => 'pgs_live_secret_value', 'door_secret' => 'door-token-value');
}

function test_workspace_agent_start_text_names_the_route_and_basic()
{
	$text = ws_agent_start_text('https://example.test/pinegrap/integration.php/');

	pg_assert_contains('Site API: https://example.test/pinegrap/integration.php', $text, 'the Site API without its trailing slash');
	pg_assert_contains('GET https://example.test/pinegrap/integration.php/workspace/agent-instructions', $text, 'the instructions route');
	pg_assert_contains('HTTP Basic', $text);
	pg_assert_contains('PINEGRAP_KEY', $text, 'the key from the environment');
	pg_assert_contains('PINEGRAP_SECRET', $text, 'the secret from the environment');
	pg_assert_contains('"text"', $text, 'follows the answer\'s text');
	pg_assert_contains('instructions_version', $text, 'reads again on another version');
	pg_assert_contains('cannot be read, stop', $text);
	pg_assert_contains('If they are not set, ask the person who set you up', $text, 'unset variables: ask, never guess');
}

function test_workspace_agent_start_text_holds_no_credential()
{
	$agent = workspace_agent_start_text_test_agent('app');
	$box = ws_agent_start_box($agent, 'https://example.test/pinegrap/integration.php');

	foreach (array('pgk_live_key_value', 'pgs_live_secret_value', 'door-token-value') as $secret) {
		pg_assert_false(strpos($box, $secret) !== false, 'no credential in the box: ' . $secret);
	}

	pg_assert_false((bool) preg_match('/-u\s+"?[A-Za-z0-9_]+:[A-Za-z0-9_]+/', ws_agent_start_text('https://example.test')), 'only variable names after -u');
}

function test_workspace_agent_start_box_only_for_an_application()
{
	$box = ws_agent_start_box(workspace_agent_start_text_test_agent('app'), 'https://example.test/pinegrap/integration.php');

	pg_assert_contains('id="pgset-ws-agent-start-7"', $box, 'a field of its own per agent');
	pg_assert_contains('data-ws-copy-field="pgset-ws-agent-start-7"', $box, 'the Claude guide\'s Copy button');
	pg_assert_contains('readonly', $box);
	pg_assert_contains(h(lang('Start text for the agent')), $box);
	pg_assert_contains('workspace/agent-instructions', $box);

	pg_assert_same('', ws_agent_start_box(workspace_agent_start_text_test_agent('model')), 'a model agent gets no box');
	pg_assert_same('', ws_agent_start_box(workspace_agent_start_text_test_agent('claude')), 'Claude gets no box');
}

function test_workspace_agent_start_box_in_the_drawer_with_the_copy_script()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/agents_settings.php');
	$from = strpos($source, 'function ws_settings_agent_drawer(');
	$drawer = substr($source, (int) $from);

	pg_assert_contains('ws_agent_start_box($agent)', $drawer, 'the agent\'s drawer draws the box');
	pg_assert_contains("</script>' . ws_claude_copy_script();", $source, 'the agents card binds Copy without the Claude card');
	pg_assert_contains('window.pgWsCopyBound', ws_claude_copy_script(), 'bound once per page');
}
