<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Calls card of the Workspace settings
 * (includes/workspace/calls_settings.php): who may change it, off until
 * turned on, a start or join refused while off, the STUN and TURN address
 * checks, the relay secret never drawn or answered, and the time-limited
 * credentials handed only with a granted start or join.
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

function workspace_calls_settings_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The body of one function of a file, or ''.
function workspace_calls_settings_test_function($file, $name)
{
	if (!preg_match('/\nfunction ' . preg_quote($name, '/') . '\([^)]*\)\n\{\n(.*?)\n\}\n/s', workspace_calls_settings_test_source($file), $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_calls_settings_who_may_change_the_card()
{
	pg_assert_true(ws_calls_settings_allowed(array('member' => true, 'settings' => true, 'role' => 2)), 'a team member with the settings right changes the card');
	pg_assert_true(!ws_calls_settings_allowed(array('member' => true, 'settings' => false, 'role' => 0)), 'without the settings right, no card');
	pg_assert_true(!ws_calls_settings_allowed(array('member' => false, 'settings' => true, 'role' => 0)), 'outside the team, no card');
	pg_assert_true(ws_calls_turn_allowed(array('member' => true, 'settings' => true, 'role' => 0)), 'an administrator changes the relay');
	pg_assert_true(!ws_calls_turn_allowed(array('member' => true, 'settings' => true, 'role' => 1)), 'a designer does not');
	pg_assert_true(!ws_calls_turn_allowed(array('member' => true, 'settings' => true, 'role' => 3)), 'a basic user with the right does not');

	$save = workspace_calls_settings_test_function('includes/workspace/calls_settings.php', 'ws_calls_settings_save');
	pg_assert_true(strpos($save, 'ws_calls_settings_allowed($viewer)') !== false, 'every save asks again');
	pg_assert_true(strpos($save, 'ws_calls_turn_allowed($viewer)') !== false, 'the relay part asks for an administrator');

	$test = workspace_calls_settings_test_function('includes/workspace/calls_settings.php', 'ws_calls_test_ice');
	pg_assert_same(array('ok' => false), array('ok' => ws_calls_test_ice(array('member' => true, 'settings' => false, 'role' => 0), array())['ok']), 'the test is refused without the right');
	pg_assert_true(strpos($test, 'ws_calls_turn_allowed($viewer)') !== false, 'the test makes relay credentials for an administrator only');
}

function test_workspace_calls_settings_off_until_turned_on()
{
	pg_assert_same(false, ws_calls_settings_parse('')['on'], 'nothing stored (an upgraded site) reads as off');
	pg_assert_same(false, ws_calls_settings_parse('{broken')['on'], 'broken JSON reads as off');
	pg_assert_same(true, ws_calls_settings_parse('{"on":true}')['on'], 'turned on');

	$stored = ws_calls_settings_parse('') + array('secret_set' => false);
	$result = ws_calls_settings_from_post($stored, array('ws_calls_card' => '1'), true);
	pg_assert_same(false, $result['settings']['on'], 'an unticked switch posts nothing and saves off');
}

function test_workspace_calls_settings_a_start_is_refused_while_off()
{
	foreach (array('ws_call_start', 'ws_call_join') as $name) {
		$body = workspace_calls_settings_test_function('includes/workspace/calls.php', $name);
		$off = strpos($body, 'if (!ws_calls_on())');
		pg_assert_true($off !== false, $name . ' refuses while calls are off');
		pg_assert_true($off < strpos($body, 'ws_call_party_check('), $name . ' refuses before anything else is looked at');
	}

	$screen = workspace_calls_settings_test_source('includes/workspace/screen.php');
	pg_assert_true(strpos($screen, "'calls'        => ws_calls_on() ? array(") !== false, 'off, the screen gets no calls config and draws no call button');
}

function test_workspace_calls_settings_address_checks()
{
	$stun = ws_call_ice_lines("stun:stun.example.com:3478\nstuns:a.example.org\n\nstun:[2001:db8::1]:3478", array('stun', 'stuns'));
	pg_assert_same(array('stun:stun.example.com:3478', 'stuns:a.example.org', 'stun:[2001:db8::1]:3478'), $stun['list'], 'STUN addresses one a line');
	pg_assert_same(array(), $stun['bad'], 'none refused');

	$bad = ws_call_ice_lines("turn:relay.example.com:3478 http://x.example.com stun:a.example.com:99999 stun:<b>", array('stun', 'stuns'));
	pg_assert_same(array('turn:relay.example.com:3478', 'http://x.example.com', 'stun:a.example.com:99999', 'stun:<b>'), $bad['bad'], 'another scheme, a web address, a port too high and markup are refused');

	$turn = ws_call_ice_lines('turn:relay.example.com:3478?transport=tcp, turns:relay.example.com:5349', array('turn', 'turns'));
	pg_assert_same(array('turn:relay.example.com:3478?transport=tcp', 'turns:relay.example.com:5349'), $turn['list'], 'TURN addresses, a transport kept');

	$many = ws_call_ice_lines('stun:a1.x stun:a2.x stun:a3.x stun:a4.x stun:a5.x stun:a6.x', array('stun'));
	pg_assert_same(WS_CALL_ICE_MAX, count($many['list']), 'at most five kept');
	pg_assert_same(array('stun:a6.x'), $many['bad'], 'the sixth refused');

	$stored = ws_calls_settings_parse('') + array('secret_set' => false);
	$refused = ws_calls_settings_from_post($stored, array('ws_calls_stun' => 'nope'), true);
	pg_assert_same(array(false, 'ws_calls_stun'), array($refused['ok'], $refused['field']), 'a bad STUN line refuses the card and names its box');

	$no_secret = ws_calls_settings_from_post($stored, array('ws_calls_turn_url' => 'turn:relay.example.com'), true);
	pg_assert_same(array(false, 'ws_calls_turn_secret'), array($no_secret['ok'], $no_secret['field']), 'a relay without a secret is refused');
}

function test_workspace_calls_settings_relay_kept_from_someone_else()
{
	$stored = array('on' => false, 'stun' => array(), 'turn_url' => array('turn:relay.example.com'), 'turn_user' => 'site', 'secret_set' => true);
	$result = ws_calls_settings_from_post($stored, array('ws_calls_on' => '1', 'ws_calls_turn_url' => '', 'ws_calls_turn_secret' => 'x'), false);

	pg_assert_same(array('turn:relay.example.com'), $result['settings']['turn_url'], 'a non-administrator\'s post leaves the relay as it is');
	pg_assert_same('keep', $result['secret'], 'and its secret');
	pg_assert_same(true, $result['settings']['on'], 'the switch is theirs');
}

function test_workspace_calls_settings_the_secret_is_never_answered()
{
	$parsed = ws_calls_settings_parse('{"on":true,"turn_secret":"s3cr3t","secret":"s3cr3t"}');
	pg_assert_true(strpos(json_encode($parsed), 's3cr3t') === false, 'the settings read never carry a secret');

	$stored = ws_calls_settings_parse('') + array('secret_set' => true);
	$html = ws_calls_settings_card_html($stored, true, '/software/api.php');
	pg_assert_true((bool) preg_match('/name="ws_calls_turn_secret"[^>]*value=""/', $html), 'the secret box is drawn empty');
	pg_assert_true(strpos($html, 'A secret is stored.') !== false, 'it says one is stored');

	$result = ws_calls_settings_from_post($stored, array('ws_calls_turn_url' => 'turn:relay.example.com', 'ws_calls_turn_secret' => 'n3w'), true);
	pg_assert_true(strpos(json_encode($result['settings']), 'n3w') === false, 'the stored JSON never holds the secret');

	$save = workspace_calls_settings_test_function('includes/workspace/calls_settings.php', 'ws_calls_settings_save');
	pg_assert_true(strpos($save, 'encrypt_string_with_iv($result[\'secret\'])') !== false, 'the secret is sealed');
	pg_assert_true(strpos($save, "\$after['turn_secret'] = (") !== false, 'the audit row says kept, removed or replaced, never the value');

	$reseal = workspace_calls_settings_test_source('includes/fn/reseal.php');
	pg_assert_true(strpos($reseal, "\$config_text('workspace', 'ws_calls_turn_secret', 'iv')") !== false, 'a key reset seals it again');

	foreach (array('includes/workspace/api_calls.php', 'includes/workspace/api.php') as $file) {
		$source = workspace_calls_settings_test_source($file);
		pg_assert_true(strpos($source, 'ws_call_member_ice') === false && strpos($source, 'ws_calls_turn_secret') === false, $file . ' hands no relay credential or secret');
	}
}

function test_workspace_calls_settings_credentials_for_members_only()
{
	$creds = ws_call_turn_credentials('secret', 'site', 1000, 3600);
	pg_assert_same('4600:site', $creds['username'], 'the expiry, then the user');
	pg_assert_same(base64_encode(hash_hmac('sha1', '4600:site', 'secret', true)), $creds['credential'], 'HMAC-SHA1 of the username with the secret');
	pg_assert_same('4600:pinegrap', ws_call_turn_credentials('secret', '', 1000, 3600)['username'], 'no user gives pinegrap');

	$settings = array('on' => true, 'stun' => array(), 'turn_url' => array('turn:relay.example.com'), 'turn_user' => '');
	pg_assert_same(array(array('urls' => array(WS_CALL_DEFAULT_STUN))), ws_call_ice_servers_from($settings, '', 1000), 'without the secret: the default STUN alone');

	$member = ws_call_ice_servers_from($settings, 'secret', 1000);
	pg_assert_same(2, count($member), 'with it: STUN and the relay');
	pg_assert_same(array('turn:relay.example.com'), $member[1]['urls'], 'the relay\'s addresses');
	pg_assert_same((1000 + WS_CALL_TURN_LIFE) . ':pinegrap', $member[1]['username'], 'credentials for a few hours');

	$screen_list = workspace_calls_settings_test_function('includes/workspace/calls.php', 'ws_call_ice_servers');
	pg_assert_true(strpos($screen_list, "ws_call_ice_servers_from(ws_calls_settings(), '', time())") !== false, 'the screen config (every Workspace reader) gets STUN only');

	$actions = workspace_calls_settings_test_source('includes/workspace/actions.php');
	pg_assert_same(1, substr_count($actions, 'ws_call_member_ice()'), 'one place in the panel actions hands the credentials');
	pg_assert_true((bool) preg_match("/if \\(!\\\$result\\['ok'\\]\\) \\{\n\s+return ws_action_error\\(\\\$result\\['error'\\]\\);\n\s+\\}\n\n(\s+\\/\\/[^\n]*\n)*\s+return ws_action_ok\\(array\\('call_id' => \\\$result\\['call_id'\\], 'member_id' => \\\$result\\['member_id'\\], 'ice' => ws_call_member_ice\\(\\)\\)\\);/", $actions), 'only after a granted start or join');

	$guests = workspace_calls_settings_test_source('includes/workspace/guests.php');
	pg_assert_true(strpos($guests, "\$joined['ok'] ? array('status' => 'ok', 'call_id' => \$joined['call_id'], 'member_id' => \$joined['member_id'], 'ice' => ws_call_member_ice())") !== false, 'a guest gets them only with a granted join');
}
