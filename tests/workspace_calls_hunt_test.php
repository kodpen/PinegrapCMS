<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for faults found in Workspace calls (includes/workspace/calls.php,
 * api_calls.php): a party who may no longer take part loses its place, a
 * decline never ends a call a guest is in, a call that went silent ends at
 * the time it was last heard, and the API never reports a dead call as on.
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

// The body of one function of a file under pinegrap/, or ''.
function workspace_calls_hunt_test_function($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

	if (!preg_match('/\nfunction ' . preg_quote($name, '/') . '\([^)]*\)\n\{\n(.*?)\n\}\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_calls_hunt_a_party_taken_out_of_the_channel_loses_its_place()
{
	$member = array('kind' => 'user', 'id' => 3, 'member' => true);
	$removed = array('kind' => 'user', 'id' => 3, 'member' => false);
	$channel = array('id' => 7, 'kind' => 'public', 'archived_at' => 0);
	$guest_now_reads = array('kind' => 'guest', 'id' => 9, 'channel_id' => 7, 'read_only' => true);

	pg_assert_false(ws_call_party_barred($member, $channel), 'a member stays in the call');
	pg_assert_true(ws_call_party_barred($removed, $channel), 'a person taken out of a public channel during the call may not stay in it');
	pg_assert_true(ws_call_party_barred($guest_now_reads, array('id' => 7, 'kind' => 'guest', 'archived_at' => 0)), 'nor a guest whose link now reads only');
	pg_assert_true(ws_call_party_barred($member, null), 'nor anyone once the channel is gone');

	$out = workspace_calls_hunt_test_function('includes/workspace/calls.php', 'ws_call_party_out_if_barred');

	pg_assert_contains('ws_call_party_barred(', $out, 'the place is taken out on the rule');
	pg_assert_contains('ws_call_leave(', $out, 'by a leave, so the last one out ends the call');

	pg_assert_contains('ws_call_party_out_if_barred(', workspace_calls_hunt_test_function('includes/workspace/calls.php', 'ws_call_live_part'), 'the one-second read takes the place out, so the person no longer hears new offers');
	pg_assert_contains('ws_call_party_out_if_barred(', workspace_calls_hunt_test_function('includes/workspace/calls.php', 'ws_call_signal_send'), 'and the person sends no more connection messages');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$route = substr($actions, (int) strpos($actions, "case 'ws_call_leave':"), 1600);

	pg_assert_contains('ws_call_leave(ws_call_party_person($viewer, null)', $route, 'a person who no longer reads a private channel leaves its call at once, not 90 seconds later');
}

function test_workspace_calls_hunt_a_decline_never_ends_a_call_a_guest_is_in()
{
	$person = array('user_id' => 3, 'guest_id' => 0);
	$guest = array('user_id' => 0, 'guest_id' => 9);

	pg_assert_false(ws_call_has_guest(array($person)), 'the caller alone: no guest');
	pg_assert_true(ws_call_has_guest(array($person, $guest)), 'a guest of a shared channel came into the call');

	$decline = workspace_calls_hunt_test_function('includes/workspace/calls.php', 'ws_call_decline');

	pg_assert_contains('ws_call_has_guest($members)', $decline, 'a conversation of two shared with a guest: the other person\'s Decline would end the call the guest is in, so it is refused');
}
