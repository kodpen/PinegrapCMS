<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for screen sharing in a Workspace call (includes/workspace/calls.php
 * and assets/js/workspace_calls.js): one share at a time, who may share and
 * stop, the share ending when its sharer leaves or stops renewing it, and
 * the browser's share button and screen connection.
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

	pg_assert_same(0, ws_call_sharer_from(null, $live, $now), 'no mark: nobody shares');
	pg_assert_same(11, ws_call_sharer_from(array('from_member_id' => 11, 'kind' => 'share', 'created_at' => $now - 20), $live, $now), 'the newest mark is a share: its sender shares');
	pg_assert_same(0, ws_call_sharer_from(array('from_member_id' => 11, 'kind' => 'unshare', 'created_at' => $now - 5), $live, $now), 'the newest mark is an unshare: nobody shares');
	pg_assert_same(0, ws_call_sharer_from(array('from_member_id' => 13, 'kind' => 'share', 'created_at' => $now - 5), $live, $now), 'a share of one no longer in the call is over');
	pg_assert_same(0, ws_call_sharer_from(array('from_member_id' => 11, 'kind' => 'share', 'created_at' => $now - WS_CALL_SHARE_LIFE), $live, $now), 'a share not renewed in time is over (a closed tab)');
	pg_assert_true(WS_CALL_SHARE_LIFE > 2 * 30, 'the browser renews its share every 30 seconds: two renewals may be late before it lapses');
}

function test_workspace_calls_share_one_at_a_time()
{
	$me = array('id' => 10, 'call_id' => 1, 'left_at' => 0);

	pg_assert_same('', ws_call_share_check($me, 0, 'share'), 'nobody shares: a member may');
	pg_assert_same('', ws_call_share_check($me, 10, 'share'), 'the sharer renews its own share');
	pg_assert_same(lang('Someone else is sharing their screen.'), ws_call_share_check($me, 11, 'share'), 'another member\'s share holds the screen');
	pg_assert_same('', ws_call_share_check($me, 10, 'unshare'), 'the sharer stops its share');
	pg_assert_same(null, ws_call_share_check($me, 11, 'unshare'), 'an unshare from anyone else writes nothing, so it cannot end another\'s share');
	pg_assert_same(null, ws_call_share_check($me, 0, 'unshare'), 'nor while nobody shares');
}

function test_workspace_calls_share_the_server_keeps_the_rules()
{
	$send = workspace_calls_share_test_function('ws_call_signal_send');
	$share = workspace_calls_share_test_function('ws_call_share_send');
	$leave = workspace_calls_share_test_function('ws_call_leave') . workspace_calls_share_test_function('ws_call_member_out');
	$part = workspace_calls_share_test_function('ws_call_live_part');
	$sharer = workspace_calls_share_test_function('ws_call_sharer');

	pg_assert_true(strpos($send, 'ws_call_member_of($party, $call_id)') !== false && strpos($send, "if (!ws_call_member_matches(\$from, \$member_id)) {") !== false, 'only a member in the live call sends a mark');
	pg_assert_true(strpos($send, 'return ws_call_share_send($call, $from, $kind, $now);') !== false, 'a share or unshare goes through its own writer');
	pg_assert_true(strpos($send, 'ws_call_over_limit($sent, WS_CALL_SIGNALS_A_MINUTE)') < strpos($send, 'ws_call_share_send('), 'marks count in the member\'s signals a minute');
	pg_assert_true(strpos($share, 'ws_call_lock($call[\'channel_id\'])') !== false && strpos($share, 'ws_call_unlock($call[\'channel_id\']);') !== false, 'the check and the write are held together: two shares at once cannot both win');
	pg_assert_true(strpos($share, 'ws_call_share_check($from, ws_call_sharer(') !== false, 'the check reads who shares now');
	pg_assert_true(strpos($leave, 'ws_call_member_out((int) $mine[\'id\'], time());') !== false && strpos($leave, "DELETE FROM ws_call_signals WHERE to_member_id = '\" . (int) \$member_id . \"' OR from_member_id = '\" . (int) \$member_id . \"'") !== false, 'a member\'s marks go when it leaves: its share ends');
	pg_assert_true(strpos($part, 'ws_call_sharer((int) $call[\'id\'], $members)') !== false, 'the live read says who shares');
	pg_assert_true(strpos($sharer, "to_member_id = 0 AND kind IN ('share', 'unshare')") !== false && strpos($sharer, 'body') === false, 'the sharer is read from the marks, never from a body');
}

function test_workspace_calls_share_present_names_the_sharer()
{
	$call = array('id' => 5, 'channel_id' => 7, 'kind' => 'voice', 'started_by' => 3, 'started_at' => 1700000000, 'state' => 'live');
	$members = array(array('id' => 10, 'user_id' => 3, 'guest_id' => 0, 'joined_at' => 1700000000));

	pg_assert_same(10, ws_call_present($call, $members, array(), 10)['sharer'], 'the sharer\'s member id');
	pg_assert_same(0, ws_call_present($call, $members, array())['sharer'], 'nobody: 0');
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
		"  C.shareState(0, 10, true), C.shareState(10, 10, true), C.shareState(11, 10, true), C.shareState(0, 10, false)",
		"]));",
	)));

	if ($out === '') {
		pg_assert_true(true, 'node is not installed: the slot is checked in CI');
		return;
	}

	pg_assert_same(
		'[2,2,1,-1,-1,{"show":true,"on":false,"busy":false},{"show":true,"on":true,"busy":false},{"show":true,"on":false,"busy":true},{"show":false,"on":false,"busy":false}]',
		$out,
		'the screen rides the call\'s screen line (a video call\'s second video line, a voice call\'s only one); the button is pressed while one shares, held while another does, and absent without getDisplayMedia'
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
