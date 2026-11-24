<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the service pages of Connections that took over a card: what a
 * save on Claude's page checks and writes, and how a chat target's last
 * failure is dated.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/claude.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/claude_setup.php');

// Claude's page sends the application as stored: a linked application
// deleted in Application Access must not refuse the routine's address.
function test_connections_merges_claude_page_keeps_application_unchecked()
{
	$config = array('enabled' => true, 'app_id' => 41, 'routine_url' => '', 'token_set' => false);
	$page = ws_claude_routine_input(array('claude_routine_url' => 'https://api.anthropic.com/v1/claude_code/routines/trig_1/fire'), $config);

	pg_assert_false(ws_claude_settings_app_checked($page), 'the stored application is not checked again from Claude\'s page');

	$row = ws_claude_settings_input(array('ws_claude_app_id' => '41'), 'ws_');
	pg_assert_true(ws_claude_settings_app_checked($row), 'an application chosen on the row is checked');

	$none = ws_claude_settings_input(array('ws_claude_app_id' => '0'), 'ws_');
	pg_assert_false(ws_claude_settings_app_checked($none), 'no application: nothing to check');
}

// A Save on Claude's page that changes nothing writes nothing: a write
// clears the routine's last error and the wait it was given.
function test_connections_merges_claude_page_unchanged_save_writes_nothing()
{
	$url = 'https://api.anthropic.com/v1/claude_code/routines/trig_1/fire';
	$config = array('enabled' => true, 'app_id' => 41, 'routine_url' => $url, 'token_set' => true);

	$same = ws_claude_routine_decision(array('claude_routine_url' => ' ' . $url . ' ', 'claude_token' => ''), $config);
	pg_assert_false($same['write'], 'the same address and no token: nothing written');
	pg_assert_same($url, $same['input']['routine_url'], 'the input still carries the address');

	$other = ws_claude_routine_decision(array('claude_routine_url' => 'https://api.anthropic.com/v1/claude_code/routines/trig_2/fire'), $config);
	pg_assert_true($other['write'], 'a new address is written');

	$token = ws_claude_routine_decision(array('claude_routine_url' => $url, 'claude_token' => 'tok'), $config);
	pg_assert_true($token['write'], 'a new token is written');

	$clear = ws_claude_routine_decision(array('claude_routine_url' => $url, 'claude_token_clear' => '1'), $config);
	pg_assert_true($clear['write'], 'removing the token is written');
}
