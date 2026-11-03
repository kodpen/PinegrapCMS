<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how long one Pinegrap AI model call may take
 * (ws_ai_call_timeout_for(), includes/workspace/ai.php): 500 seconds unless
 * data/config.php sets PG_AI_CALL_TIMEOUT, which is taken from 20 to 600
 * seconds. A complex flow drafted in a channel took longer than the 55
 * seconds the limit used to be, and all three tries were cut off.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// Nothing set: 500 seconds; a setting from 20 to 600 is taken, one under 20
// or not a number is left aside, one over 600 is cut to 600.
function test_workspace_ai_call_timeout_default_and_setting()
{
	pg_assert_same(500, ws_ai_call_timeout_for(null), 'nothing set');
	pg_assert_same(180, ws_ai_call_timeout_for(180), 'a setting is taken');
	pg_assert_same(20, ws_ai_call_timeout_for('20'), 'the shortest allowed');
	pg_assert_same(500, ws_ai_call_timeout_for(10), 'too short: the default');
	pg_assert_same(500, ws_ai_call_timeout_for('soon'), 'not a number: the default');
	pg_assert_same(600, ws_ai_call_timeout_for(900), 'the longest allowed');
}

// The constant every caller reads comes from it, and on the command line the
// scheduled job never gets less than a web request.
function test_workspace_ai_call_timeout_wiring()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/ai.php');

	pg_assert_contains("define('WS_AI_CALL_TIMEOUT', ws_ai_call_timeout_for(defined('PG_AI_CALL_TIMEOUT') ? PG_AI_CALL_TIMEOUT : null));", $source, 'the constant');
	pg_assert_same(defined('PG_AI_CALL_TIMEOUT') ? ws_ai_call_timeout_for(PG_AI_CALL_TIMEOUT) : 500, WS_AI_CALL_TIMEOUT, 'as configured here');
	pg_assert_true(ws_ai_call_timeout() >= WS_AI_CALL_TIMEOUT, 'the command line has at least as long');
}
