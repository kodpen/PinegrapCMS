<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Claude's page on Connections: "Connection details" holds the
 * routine's address and token without drawing the token back; what it posts
 * writes only those two; the card is shown under the gate of the Assistants
 * and agents row they came from; the registry row follows the move; set-up
 * step 4 leads to the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/claude_setup.php');

function connections_claude_page_test_card($over = array())
{
	return ws_claude_connection_card_html($over + array(
		'routine_url' => 'https://api.anthropic.com/v1/claude_code/routines/trig_1/fire',
		'token_set'   => true,
		'token_field' => '<input type="hidden" name="token" value="t">',
		'can_seal'    => true,
		'https'       => true,
	));
}

function test_connections_claude_page_card_fields()
{
	$html = connections_claude_page_test_card();

	pg_assert_contains('id="connections-page-details">' . h(lang('Connection details')) . '</h2>', $html, 'the card\'s title');
	pg_assert_contains('<input type="hidden" name="connections_action" value="claude_save">', $html, 'its action');
	pg_assert_contains('<input type="hidden" name="connections_return" value="claude">', $html, 'it returns to the page');
	pg_assert_contains('<input type="hidden" name="token" value="t">', $html, 'the CSRF field');
	pg_assert_contains('name="claude_routine_url" id="ws_claude_routine_url" value="https://api.anthropic.com/v1/claude_code/routines/trig_1/fire"', $html, 'the address drawn back');
	pg_assert_contains('name="claude_token" id="ws_claude_token" value=""', $html, 'the token never drawn back');
	pg_assert_contains('type="checkbox" name="claude_token_clear" value="1" id="ws_claude_token_clear"', $html, 'the bin\'s checkbox while one is stored');
	pg_assert_contains('data-ws-claude-eye="ws_claude_token"', $html, 'the eye');
	pg_assert_same(1, substr_count($html, 'pg-button-fill'), 'Save the one filled button');
	pg_assert_contains('name="submit_try" value="1" class="pg-button pg-button-sm pg-button-quiet">' . h(lang('Save and try')), $html, 'Save and try, quiet');
	pg_assert_false(strpos($html, 'alert') !== false, 'no notice on an HTTPS site that can seal');

	$none = connections_claude_page_test_card(array('token_set' => false, 'routine_url' => ''));
	pg_assert_false(strpos($none, 'claude_token_clear') !== false, 'no bin without a stored token');
	pg_assert_contains('placeholder="sk-ant-oat01-…"', $none, 'the token\'s shape');

	$plain = connections_claude_page_test_card(array('https' => false, 'can_seal' => false));
	pg_assert_contains(h(lang('This site is not opened over HTTPS, so the routine cannot reach it.')), $plain, 'the HTTPS notice');
	pg_assert_contains(h(lang('The token cannot be stored: encryption is not available on this site.')), $plain, 'the encryption notice');
	pg_assert_same(2, substr_count($plain, '" disabled>'), 'Save and Save and try off without encryption');
}

function test_connections_claude_page_input_writes_routine_only()
{
	$config = array('enabled' => true, 'app_id' => 7, 'routine_url' => 'https://x/fire', 'token_set' => true);
	$input = ws_claude_routine_input(array(
		'claude_routine_url' => ' https://api.anthropic.com/v1/claude_code/routines/trig_2/fire ',
		'claude_token' => ' tok ', 'claude_token_clear' => '1',
		'claude_enabled' => '0', 'claude_app_id' => '9', 'claude_summary_hour' => '3',
	), $config);

	pg_assert_same(array(
		'enabled' => true, 'app_id' => 7, 'app_kept' => true, 'routine_url' => 'https://api.anthropic.com/v1/claude_code/routines/trig_2/fire',
		'token' => 'tok', 'token_clear' => true, 'summary_hour' => null, 'summary_channel' => 0,
	), $input, 'the switch, the application and the summary as stored');

	$odd = ws_claude_routine_input(array('claude_routine_url' => array('x'), 'claude_token' => array('y')), array());
	pg_assert_same('', $odd['routine_url'], 'a list is no address');
	pg_assert_same('', $odd['token'], 'a list is no token');
	pg_assert_false($odd['enabled'], 'nothing stored: off');
	pg_assert_false($odd['token_clear'], 'no clear');
}

function test_connections_claude_page_cards_and_registry()
{
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('claude', array('manager' => true, 'claude' => true)), 'the details for the Workspace\'s administrator');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('claude', array('manager' => true, 'claude' => false)), 'not without the row\'s gate');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('claude', array('manager' => true, 'ai' => true)), 'an AI provider\'s right is not enough');

	$row = pg_connections_registry()['claude'];
	pg_assert_same('connections.php', $row['place'], 'set up on Connections');
	pg_assert_same('#settings/workspace_assistants/pgset-ws-claude', $row['found_at'], 'still found at Claude\'s row');
}

function test_connections_claude_page_setup_step_leads_there()
{
	$guide = ws_claude_setup_guide('example.com', 'prompt', '');

	pg_assert_contains('<div class="ws-claude-step-title">' . h(lang('Paste the routine address and the token in Connections')) . '</div>', $guide, 'step 4\'s title');
	pg_assert_contains('href="' . h(ws_claude_connections_url()) . '" target="_blank" rel="noopener">' . h(lang('Open Claude in Connections')), $guide, 'its button to the page');
}
