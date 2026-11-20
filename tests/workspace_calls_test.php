<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Workspace calls on the server (includes/workspace/calls.php):
 * who may take part, joining a full or ended call, a signal addressed
 * within its call and read by its member only, the limits a minute, the
 * silence that ends a call, the clean-up, the stamp that keeps an idle
 * channel's reads free, and the read-only API.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function workspace_calls_test_channel($kind = 'private', $archived = 0)
{
	return array('id' => 7, 'kind' => $kind, 'archived_at' => $archived);
}

// The body of one function of calls.php, or ''.
function workspace_calls_test_function($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/calls.php');

	if (!preg_match('/\nfunction ' . preg_quote($name, '/') . '\([^)]*\)\n\{\n(.*?)\n\}\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_calls_members_of_the_channel_take_part()
{
	$member = array('kind' => 'user', 'id' => 3, 'member' => true);
	$outsider = array('kind' => 'user', 'id' => 4, 'member' => false);

	pg_assert_same('', ws_call_party_check($member, workspace_calls_test_channel('public'), true), 'a member starts a call in a public channel');
	pg_assert_same('', ws_call_party_check($member, workspace_calls_test_channel('private'), true), 'either person of a direct conversation (a private channel of two) starts one');
	pg_assert_same('', ws_call_party_check($member, workspace_calls_test_channel('guest'), false), 'staff in a guest room join its call');
	pg_assert_true(ws_call_party_check($outsider, workspace_calls_test_channel('public'), false) !== '', 'reading a public channel without being in it is not enough');
	pg_assert_true(ws_call_party_check($member, workspace_calls_test_channel('private', 1700000000), false) !== '', 'an archived channel holds no call');
	pg_assert_true(ws_call_party_check($member, workspace_calls_test_channel('mailbox'), true) !== '', 'a mailbox room holds no call');
	pg_assert_true(ws_call_party_check($member, workspace_calls_test_channel('thread'), true) !== '', 'a discussion holds no call of its own');
	pg_assert_true(ws_call_party_check($member, null, false) !== '', 'no channel, no call');
}

function test_workspace_calls_a_guest_joins_in_its_own_room_only()
{
	$guest = array('kind' => 'guest', 'id' => 9, 'channel_id' => 7, 'read_only' => false);

	pg_assert_same('', ws_call_party_check($guest, workspace_calls_test_channel('guest'), false), 'a guest joins its own room\'s call');
	pg_assert_true(ws_call_party_check($guest, workspace_calls_test_channel('guest'), true) !== '', 'a guest never starts a call');
	pg_assert_true(ws_call_party_check(array('channel_id' => 8) + $guest, workspace_calls_test_channel('guest'), false) !== '', 'another room\'s call is closed to the guest');
	pg_assert_true(ws_call_party_check(array('read_only' => true) + $guest, workspace_calls_test_channel('public'), false) !== '', 'a guest a channel was shared with to read only does not join');
}

function test_workspace_calls_a_call_holds_four_and_ends_for_good()
{
	$live = array('id' => 1, 'state' => 'live');

	pg_assert_same(4, WS_CALL_MAX_MEMBERS, 'four people at most: every pair is connected');
	pg_assert_same('', ws_call_join_check($live, 3), 'the fourth joins');
	pg_assert_same(lang('This call is full.'), ws_call_join_check($live, 4), 'the fifth sees a full call');
	pg_assert_same(lang('This call has ended.'), ws_call_join_check(array('state' => 'ended') + $live, 0), 'an ended call is not joined again');
	pg_assert_same(lang('This call has ended.'), ws_call_join_check(null, 0), 'a call that is not there is not joined');
}

function test_workspace_calls_a_signal_stays_inside_its_call()
{
	$from = array('id' => 10, 'call_id' => 1, 'left_at' => 0);
	$to = array('id' => 11, 'call_id' => 1, 'left_at' => 0);

	pg_assert_same('', ws_call_signal_check($from, $to, 'offer', 'v=0'), 'an offer to another member of the call');
	pg_assert_same('', ws_call_signal_check($from, $to, 'candidate', '{}'), 'a candidate');
	pg_assert_same('', ws_call_signal_check($from, null, 'ping', ''), 'the keep-alive is addressed to nobody');
	pg_assert_true(ws_call_signal_check($from, null, 'answer', 'v=0') !== '', 'an answer needs its member');
	pg_assert_true(ws_call_signal_check($from, $from, 'offer', 'v=0') !== '', 'nobody signals themselves');
	pg_assert_true(ws_call_signal_check($from, array('call_id' => 2) + $to, 'offer', 'v=0') !== '', 'a member of another call is out of reach');
	pg_assert_true(ws_call_signal_check($from, array('left_at' => 1700000000) + $to, 'offer', 'v=0') !== '', 'a member who left is out of reach');
	pg_assert_true(ws_call_signal_check($from, $to, 'chat', 'hi') !== '', 'only connection messages are carried');
	pg_assert_true(ws_call_signal_check($from, $to, 'offer', str_repeat('a', WS_CALL_SIGNAL_MAX + 1)) !== '', 'a body over the limit is refused');
}

function test_workspace_calls_a_signal_is_read_by_its_member_only()
{
	$read = workspace_calls_test_function('ws_call_signals_for');
	$part = workspace_calls_test_function('ws_call_live_part');

	pg_assert_true($read !== '' && $part !== '', 'both functions are found');
	pg_assert_true(strpos($read, "WHERE to_member_id = '\" . \$member_id . \"'") !== false, 'the read asks only for the signals addressed to the member');
	pg_assert_true(strpos($part, "'signals'   => \$mine ? ws_call_signals_for((int) \$mine['id']") !== false, 'the live read hands signals only to a party in the call, its own');
	pg_assert_true(preg_match_all('/SELECT[^"]*\bbody\b[^"]*FROM ws_call_signals/', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/calls.php')) === 1, 'no other query reads a signal\'s body');
}

function test_workspace_calls_limits_a_minute()
{
	pg_assert_same(3, WS_CALL_STARTS_A_MINUTE, 'three calls started a minute per person');
	pg_assert_same(300, WS_CALL_SIGNALS_A_MINUTE, 'three hundred signals a minute per member');
	pg_assert_false(ws_call_over_limit(2, WS_CALL_STARTS_A_MINUTE), 'the third start goes through');
	pg_assert_true(ws_call_over_limit(3, WS_CALL_STARTS_A_MINUTE), 'the fourth start in a minute is refused');
	pg_assert_true(ws_call_over_limit(300, WS_CALL_SIGNALS_A_MINUTE), 'the 301st signal in a minute is refused');

	$start = workspace_calls_test_function('ws_call_start');
	$send = workspace_calls_test_function('ws_call_signal_send');

	pg_assert_true(strpos($start, 'ws_call_over_limit($started, WS_CALL_STARTS_A_MINUTE)') !== false, 'a start counts the person\'s starts');
	pg_assert_true(strpos($send, 'ws_call_over_limit($sent, WS_CALL_SIGNALS_A_MINUTE)') !== false, 'a signal counts the member\'s signals');
	pg_assert_true(strpos(workspace_calls_test_function('ws_call_signals_for'), 'DELETE') === false, 'reading deletes nothing, so the count holds every signal of the minute');
}

function test_workspace_calls_a_silent_call_ends()
{
	$now = 1700000000;
	$call = array('started_at' => $now - 600);

	pg_assert_same(120, WS_CALL_SILENCE, 'two minutes of silence');
	pg_assert_true(ws_call_is_silent($call, 0, $now - 600, $now), 'nothing heard for ten minutes');
	pg_assert_false(ws_call_is_silent($call, $now - 30, $now - 600, $now), 'a keep-alive half a minute ago');
	pg_assert_false(ws_call_is_silent($call, 0, $now - 60, $now), 'somebody joined a minute ago');
	pg_assert_false(ws_call_is_silent(array('started_at' => $now - 10), 0, 0, $now), 'a call just started');
}

function test_workspace_calls_the_full_call_and_its_clean_up()
{
	$leave = workspace_calls_test_function('ws_call_leave');
	$end = workspace_calls_test_function('ws_call_end_row');
	$sweep = workspace_calls_test_function('ws_calls_sweep');
	$start = workspace_calls_test_function('ws_call_start');
	$join = workspace_calls_test_function('ws_call_join');

	pg_assert_true(strpos($start, 'return ws_call_join($party, $channel, (int) $live[\'id\']);') !== false, 'a start where a call is on joins that call: one live call a channel');
	pg_assert_true(strpos($join, "'member_id' => (int) \$mine['id']") !== false, 'a party already in the call keeps its one row');
	pg_assert_true(strpos($leave, 'if (!ws_call_members_live($call_id)) {') !== false && strpos($leave, 'ws_call_end_row($call);') !== false, 'the last one out ends the call');
	pg_assert_true(strpos($end, "DELETE FROM ws_call_signals WHERE call_id = '\" . \$id . \"'") !== false, 'an ended call\'s signals are deleted');
	pg_assert_true(strpos($end, "SET left_at = '\" . \$now . \"' WHERE call_id") !== false, 'everyone is out of an ended call');
	pg_assert_true(strpos($sweep, "DELETE FROM ws_call_signals WHERE created_at < '\" . (\$now - WS_CALL_SIGNAL_LIFE) . \"'") !== false, 'a signal older than two minutes is deleted');
	pg_assert_true(strpos($sweep, 'ws_call_is_silent(') !== false && strpos($sweep, 'ws_call_end_row($call);') !== false, 'the clean-up ends a silent call');
	pg_assert_same(120, WS_CALL_SIGNAL_LIFE, 'signals live two minutes');
}

function test_workspace_calls_who_ends_a_call_for_everyone()
{
	$call = array('started_by' => 3);

	pg_assert_true(ws_call_may_end(array('kind' => 'user', 'id' => 3), $call, false), 'its starter');
	pg_assert_true(ws_call_may_end(array('kind' => 'user', 'id' => 4), $call, true), 'whoever manages the channel');
	pg_assert_false(ws_call_may_end(array('kind' => 'user', 'id' => 4), $call, false), 'not another member: they leave');
	pg_assert_false(ws_call_may_end(array('kind' => 'guest', 'id' => 3), $call, true), 'never a guest');
}

function test_workspace_calls_an_idle_channel_asks_nothing()
{
	pg_assert_false(ws_calls_may_be_live(true, false), 'no stamp: no call query');
	pg_assert_true(ws_calls_may_be_live(true, true), 'a stamp: the channel\'s call is read');
	pg_assert_true(ws_calls_may_be_live(false, false), 'no folder for the stamp: the database is asked, so no call goes unseen');

	$part = workspace_calls_test_function('ws_call_live_part');

	pg_assert_same(0, strpos(trim($part), 'if (!ws_calls_live_somewhere() || !ws_calls_ready()) {'), 'the file check comes before any query');
	pg_assert_true(strpos(workspace_calls_test_function('ws_call_end_row'), 'ws_calls_stamp_clear_if_idle();') !== false, 'the last call\'s end takes the stamp away');
	pg_assert_true(strpos(workspace_calls_test_function('ws_call_start'), 'ws_calls_stamp_set();') !== false, 'a start lays it down');
}

function test_workspace_calls_present_names_people_and_guests()
{
	$call = array('id' => 5, 'channel_id' => 7, 'kind' => 'video', 'started_by' => 3, 'started_at' => 1700000000, 'state' => 'live');
	$members = array(
		array('id' => 10, 'user_id' => 3, 'guest_id' => 0, 'joined_at' => 1700000000),
		array('id' => 11, 'user_id' => 0, 'guest_id' => 9, 'joined_at' => 1700000060),
	);
	$present = ws_call_present($call, $members, array('user:3' => 'Ada', 'guest:9' => 'Bora'));

	pg_assert_same('video', $present['kind'], 'the kind');
	pg_assert_same(array('member_id' => 10, 'kind' => 'person', 'user_id' => 3, 'name' => 'Ada', 'joined_at' => 1700000000), $present['members'][0], 'a person');
	pg_assert_same(array('member_id' => 11, 'kind' => 'guest', 'user_id' => 0, 'name' => 'Bora', 'joined_at' => 1700000060), $present['members'][1], 'a guest, by the name the room knows');

	$api = ws_api_call_present($present);

	pg_assert_same(array('kind' => 'guest', 'user_id' => null, 'name' => 'Bora', 'joined_at' => '2023-11-14T22:14:20Z'), $api['call']['members'][1], 'the API gives a guest no user id');
	pg_assert_same('2023-11-14T22:13:20Z', $api['call']['started_at'], 'times as ISO 8601');
	pg_assert_same(array('call' => null), ws_api_call_present(null), 'no live call');
}

function test_workspace_calls_the_api_only_reads()
{
	$routes = ws_call_api_routes();

	pg_assert_true(count($routes) > 0, 'a route');

	foreach ($routes as $route) {
		pg_assert_same('GET', $route['method'], $route['id'] . ': nothing starts, joins or ends a call for a person');
		pg_assert_same('workspace:read', $route['scope'], $route['id'] . ': the read scope');
	}

	$ids = array();

	foreach (ws_api_routes() as $route) {
		$ids[] = $route['id'];
	}

	pg_assert_true(in_array('workspace.channels.call.get', $ids, true), 'the route is merged into the Workspace API');
}

function test_workspace_calls_tables_by_their_step()
{
	$step = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');

	pg_assert_true(strpos($step, "upgrade_2026_11_0_ws_calls();                // 11.0.243") !== false, 'the step is called with its label');

	foreach (array('ws_calls', 'ws_call_members', 'ws_call_signals') as $table) {
		pg_assert_true(strpos($step, "install_create_table('" . $table . "'") !== false, $table . ' is created by the step');
		pg_assert_true(strpos($install, "'" . $table . "',") !== false, $table . ' is in get_tables()');
	}
}
