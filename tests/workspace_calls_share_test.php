<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for screen sharing in a Workspace call (includes/workspace/calls.php
 * and assets/js/workspace_calls.js): several shares at once, one a member,
 * who may share and stop, a share ending when its sharer leaves or stops
 * renewing it, and the browser's share button and screen connection.
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

// The body of one function of calls.php, or ''.
function workspace_calls_share_test_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/calls.php');

	if (!preg_match('/\nfunction ' . preg_quote($name, '/') . '\([^)]*\)\n\{\n(.*?)\n\}\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_calls_share_marks_are_addressed_to_nobody()
{
	$from = array('id' => 10, 'call_id' => 1, 'left_at' => 0);

	pg_assert_true(in_array('share', ws_call_signal_kinds(), true) && in_array('unshare', ws_call_signal_kinds(), true), 'share and unshare are signal kinds');
	pg_assert_same('', ws_call_signal_check($from, null, 'share', ''), 'a share needs no addressee');
	pg_assert_same('', ws_call_signal_check($from, null, 'unshare', ''), 'nor does an unshare');
	pg_assert_true(ws_call_signal_to_nobody('ping') && ws_call_signal_to_nobody('share') && ws_call_signal_to_nobody('unshare'), 'the keep-alive and the share marks go to nobody');
	pg_assert_false(ws_call_signal_to_nobody('offer'), 'an offer goes to its member');
}

function test_workspace_calls_share_who_shares_now()
{
	$now = 1700000000;
	$live = array(10, 11, 12);
	$mark = function ($member_id, $kind, $ago) use ($now) {
		return array('from_member_id' => $member_id, 'kind' => $kind, 'created_at' => $now - $ago);
	};

	pg_assert_same(array(), ws_call_sharers_from(array(), $live, $now), 'no mark: nobody shares');
	pg_assert_same(array(11), ws_call_sharers_from(array($mark(11, 'share', 20)), $live, $now), 'a member\'s newest mark is a share: it shares');
	pg_assert_same(array(), ws_call_sharers_from(array($mark(11, 'share', 20), $mark(11, 'unshare', 5)), $live, $now), 'its newest mark is an unshare: it no longer shares');
	pg_assert_same(array(11), ws_call_sharers_from(array($mark(11, 'unshare', 40), $mark(11, 'share', 5)), $live, $now), 'a share after its unshare: it shares again');
	pg_assert_same(array(), ws_call_sharers_from(array($mark(13, 'share', 5)), $live, $now), 'a share of one no longer in the call is over');
	pg_assert_same(array(), ws_call_sharers_from(array($mark(11, 'share', WS_CALL_SHARE_LIFE)), $live, $now), 'a share not renewed in time is over (a closed tab)');
	pg_assert_same(array(10, 12), ws_call_sharers_from(array($mark(12, 'share', 30), $mark(10, 'share', 20), $mark(12, 'share', 2)), $live, $now), 'several members share at once, each once, by member id');
	pg_assert_same(array(12), ws_call_sharers_from(array($mark(10, 'share', 20), $mark(12, 'share', 10), $mark(10, 'unshare', 5)), $live, $now), 'one stopping leaves the others\' shares');
	pg_assert_true(WS_CALL_SHARE_LIFE > 2 * 30, 'the browser renews its share every 30 seconds: two renewals may be late before it lapses');
}

function test_workspace_calls_share_several_at_once()
{
	$me = array('id' => 10, 'call_id' => 1, 'left_at' => 0);

	pg_assert_same('', ws_call_share_check($me, array(), 'share'), 'nobody shares: a member may');
	pg_assert_same('', ws_call_share_check($me, array(10), 'share'), 'the sharer renews its own share');
	pg_assert_same('', ws_call_share_check($me, array(11, 12), 'share'), 'others\' shares do not hold a member back');
	pg_assert_same('', ws_call_share_check($me, array(10, 11), 'unshare'), 'the sharer stops its share');
	pg_assert_same(null, ws_call_share_check($me, array(11), 'unshare'), 'an unshare of one not sharing writes nothing');
	pg_assert_same(null, ws_call_share_check($me, array(), 'unshare'), 'nor while nobody shares');
}

function test_workspace_calls_share_the_server_keeps_the_rules()
{
	$send = workspace_calls_share_test_function('ws_call_signal_send');
	$share = workspace_calls_share_test_function('ws_call_share_send');
	$leave = workspace_calls_share_test_function('ws_call_leave') . workspace_calls_share_test_function('ws_call_member_out');
	$part = workspace_calls_share_test_function('ws_call_live_part');
	$sharer = workspace_calls_share_test_function('ws_call_sharers');

	pg_assert_true(strpos($send, 'ws_call_member_of($party, $call_id)') !== false && strpos($send, "if (!ws_call_member_matches(\$from, \$member_id)) {") !== false, 'only a member in the live call sends a mark');
	pg_assert_true(strpos($send, 'return ws_call_share_send($call, $from, $kind, $now);') !== false, 'a share or unshare goes through its own writer');
	pg_assert_true(strpos($send, 'ws_call_over_limit($sent, WS_CALL_SIGNALS_A_MINUTE)') < strpos($send, 'ws_call_share_send('), 'marks count in the member\'s signals a minute');
	pg_assert_true(strpos($share, 'ws_call_share_check($from, ws_call_sharers(') !== false, 'the check reads who shares now');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/calls.php'), 'Someone else is sharing their screen.') === false, 'no share is refused for another member\'s');
	pg_assert_true(strpos($leave, 'ws_call_member_out((int) $mine[\'id\'], time());') !== false && strpos($leave, "DELETE FROM ws_call_signals WHERE to_member_id = '\" . (int) \$member_id . \"' OR from_member_id = '\" . (int) \$member_id . \"'") !== false, 'a member\'s marks go when it leaves: its share ends');
	pg_assert_true(strpos($part, 'ws_call_sharers((int) $call[\'id\'], $members)') !== false, 'the live read says who shares');
	pg_assert_true(strpos($sharer, "to_member_id = 0 AND kind IN ('share', 'unshare')") !== false && strpos($sharer, 'ORDER BY id') !== false && strpos($sharer, 'body') === false, 'the sharers are read from the marks, never from a body');
}

function test_workspace_calls_share_present_names_the_sharers()
{
	$call = array('id' => 5, 'channel_id' => 7, 'kind' => 'voice', 'started_by' => 3, 'started_at' => 1700000000, 'state' => 'live');
	$members = array(array('id' => 10, 'user_id' => 3, 'guest_id' => 0, 'joined_at' => 1700000000));

	pg_assert_same(array(10, 11), ws_call_present($call, $members, array(), array('10', 11))['sharers'], 'the sharers\' member ids');
	pg_assert_same(array(), ws_call_present($call, $members, array())['sharers'], 'nobody: an empty list');
	pg_assert_false(isset(ws_call_present($call, $members, array())['sharer']), 'no single sharer any more');
}

// Runs one script under node with the browser part loaded; '' without node.
function workspace_calls_share_test_node($script)
{
	$node = trim((string) @shell_exec('command -v node 2>/dev/null'));

	if ($node === '') {
		return '';
	}

	$file = tempnam(sys_get_temp_dir(), 'pgshare');
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js');

	file_put_contents($file, "var window = { addEventListener: function () {} };\nvar navigator = {};\n" . $source . "\nvar C = window.PGWsCalls;\n" . $script);
	$out = (string) shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($file) . ' 2>&1');
	@unlink($file);

	return trim($out);
}

function test_workspace_calls_share_the_screen_slot()
{
	$out = workspace_calls_share_test_node(implode("\n", array(
		"console.log(JSON.stringify([",
		"  C.screenSlot(['1', '2'], 'video'),",
		"  C.screenSlot(['2', '1'], 'video'),",
		"  C.screenSlot(['1'], 'voice'),",
		"  C.screenSlot(['1'], 'video'),",
		"  C.screenSlot([], 'voice'),",
		"  C.shareState([], 10, true), C.shareState([10], 10, true), C.shareState([11], 10, true), C.shareState([10, 11], 10, true), C.shareState([], 10, false)",
		"]));",
	)));

	if ($out === '') {
		pg_assert_true(true, 'node is not installed: the slot is checked in CI');
		return;
	}

	pg_assert_same(
		'[2,2,1,-1,-1,{"show":true,"on":false},{"show":true,"on":true},{"show":true,"on":false},{"show":true,"on":true},{"show":false,"on":false}]',
		$out,
		'the screen rides the call\'s screen line (a video call\'s second video line, a voice call\'s only one); the button is pressed while this member shares, free while others do, and absent without getDisplayMedia'
	);
}

function test_workspace_calls_share_the_browser_wiring()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_calls.js');

	pg_assert_true(strpos($js, "pc.addTransceiver('video', { direction: 'sendrecv' })") !== false, 'the offerer opens the screen\'s line with every connection, so a share needs no new offer');
	pg_assert_true(strpos($js, "slot.direction = 'sendrecv';") !== false, 'the answerer sends on the screen\'s line too');
	pg_assert_true(strpos($js, 'navigator.mediaDevices.getDisplayMedia(') !== false, 'the screen is asked of the browser, which lets the person choose it');
	pg_assert_true(strpos($js, "send(0, mine.sharing ? 'share' : 'ping', '');") !== false, 'the sharer renews its share with the keep-alive');
	pg_assert_true(strpos($js, 'track.onended') !== false || strpos($js, "addEventListener('ended'") !== false, 'the browser\'s own Stop sharing ends the share');
	pg_assert_true(strpos($js, "'pg-icon-button', '', 'bi-display', t('call_share')") !== false, 'the share button is an icon button beside mute and camera (Design examples: Buttons)');
}
