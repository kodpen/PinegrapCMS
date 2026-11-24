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
