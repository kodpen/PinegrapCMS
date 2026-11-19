<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for sharing a record in a channel from its Workspace drawer: the
 * line is always posted as a message (ws_send with as_text), never run as a
 * / command, and the drawer says it was shared only when a message came back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// ws_send with as_text skips the /claude, /ai rewrites, the command step and
// the "//word" escape; without it the composer's path is unchanged.
function test_workspace_record_share_send_as_text_skips_commands()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$send = substr($actions, strpos($actions, "case 'ws_send':"));
	$send = substr($send, 0, strpos($send, "case 'ws_edit':"));

	$flag = strpos($send, "\$as_text = !empty(\$request['as_text']);");
	pg_assert_true($flag !== false, 'the action reads the flag');

	$guard = strpos($send, 'if (!$as_text) {');
	pg_assert_true($guard !== false && $guard > $flag, 'a guard follows the flag');

	foreach (array('ws_claude_command_body($body)', 'ws_ai_command_body($body)', 'ws_command_run($viewer', 'ws_command_unescape($body)') as $call) {
		$at = strpos($send, $call);
		pg_assert_true($at !== false && $at > $guard && $at < strpos($send, 'ws_message_send($viewer'), $call . ' runs only without the flag');
	}
}

// The drawer's share sends the flag and toasts only on a message.
function test_workspace_record_share_drawer_sends_a_message()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$share = substr($js, strpos($js, '// Share the record in a channel, with a line of text.'), 2000);

	pg_assert_contains('as_text: 1', $share, 'the share is sent as text');
	pg_assert_contains('result.message_id', $share, 'the toast waits for a message');
}
