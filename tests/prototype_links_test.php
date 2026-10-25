<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the share links of a design's prototype
 * (includes/fn/prototype_links.php, prototype.php?link=...): what a new link
 * may be made with, when a link still opens, which page it starts on, the
 * password's lock and the cookie a right password leaves, the frame address
 * signed for a link, the token kept out of every address a log writes down,
 * when a visit counts as a use, the designer's endpoints and the API routes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');

// A token as pg_short_link_new_token() writes one: 43 URL-safe characters.
function prototype_links_test_token()
{
	return 'AbCdEf0123456789_-AbCdEf0123456789_-AbCdEfG';
}

// The durations a link can be made for, in seconds.
function prototype_links_test_durations()
{
	return array(3600, 86400, 259200, 604800, 2592000, 7776000);
}

// A create request with every field, laid over the given ones.
function prototype_links_test_input($over = array())
{
	return array_merge(array('audience' => 'anyone', 'start_page_id' => 0, 'duration' => 604800, 'password' => ''), $over);
}

function test_prototype_links_token_shape_and_hint()
{
	pg_assert_same(43, strlen(prototype_links_test_token()));
	pg_assert_true(pg_proto_link_token_shape(prototype_links_test_token()));
	pg_assert_true(pg_proto_link_token_shape(pg_short_link_new_token()), 'a new token');
	pg_assert_false(pg_proto_link_token_shape(substr(prototype_links_test_token(), 1)), 'one short');
	pg_assert_false(pg_proto_link_token_shape(prototype_links_test_token() . 'x'), 'one long');
	pg_assert_false(pg_proto_link_token_shape(str_replace('_', '+', prototype_links_test_token())), 'not URL-safe');
	pg_assert_false(pg_proto_link_token_shape(array(prototype_links_test_token())), 'an array');
	pg_assert_false(pg_proto_link_token_shape(''));
	pg_assert_same('AbCdEf', pg_proto_link_hint(prototype_links_test_token()));
}

function test_prototype_links_plan_for_anyone_needs_an_expiry_of_at_most_ninety_days()
{
	$now = 1900000000;

	$plan = pg_proto_link_plan(prototype_links_test_input(array('duration' => 0)), array(11, 12), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok'], 'no expiry');
	pg_assert_same('duration', $plan['error']);
	pg_assert_same('duration', $plan['field']);

	$plan = pg_proto_link_plan(prototype_links_test_input(array('duration' => 7776000 + 86400)), array(11), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok'], 'past ninety days');
	pg_assert_same('duration', $plan['error']);

	// Only one of the offered choices, even when it is shorter.
	$plan = pg_proto_link_plan(prototype_links_test_input(array('duration' => 1234)), array(11), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok'], 'not a choice');

	// A choice longer than ninety days handed in is still refused.
	$plan = pg_proto_link_plan(prototype_links_test_input(array('duration' => 31536000)), array(11), array(31536000), $now);
	pg_assert_false($plan['ok'], 'a year, even when offered');

	$plan = pg_proto_link_plan(prototype_links_test_input(), array(11), prototype_links_test_durations(), $now);
	pg_assert_true($plan['ok']);
	pg_assert_same(array('audience' => 'anyone', 'start_page_id' => 0, 'expires_at' => $now + 604800, 'password' => ''), $plan['link']);
}

function test_prototype_links_plan_for_the_team_may_have_no_expiry()
{
	$now = 1900000000;

	$plan = pg_proto_link_plan(prototype_links_test_input(array('audience' => 'team', 'duration' => 0)), array(11), prototype_links_test_durations(), $now);
	pg_assert_true($plan['ok']);
	pg_assert_same(0, $plan['link']['expires_at']);

	$plan = pg_proto_link_plan(prototype_links_test_input(array('audience' => 'team', 'duration' => 86400)), array(11), prototype_links_test_durations(), $now);
	pg_assert_same($now + 86400, $plan['link']['expires_at']);

	$plan = pg_proto_link_plan(prototype_links_test_input(array('audience' => 'team', 'duration' => 999)), array(11), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok'], 'an expiry that is not a choice');

	$plan = pg_proto_link_plan(prototype_links_test_input(array('audience' => 'everyone')), array(11), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok']);
	pg_assert_same('audience', $plan['error']);

	$plan = pg_proto_link_plan(prototype_links_test_input(array('audience' => array('team'))), array(11), prototype_links_test_durations(), $now);
	pg_assert_same('audience', $plan['error']);
}

function test_prototype_links_plan_start_page_is_a_draft_of_the_design()
{
	$now = 1900000000;

	$plan = pg_proto_link_plan(prototype_links_test_input(array('start_page_id' => 12)), array(11, 12), prototype_links_test_durations(), $now);
	pg_assert_true($plan['ok']);
	pg_assert_same(12, $plan['link']['start_page_id']);

	$plan = pg_proto_link_plan(prototype_links_test_input(array('start_page_id' => '12')), array(11, 12), prototype_links_test_durations(), $now);
	pg_assert_same(12, $plan['link']['start_page_id'], 'digits as a string');

	$plan = pg_proto_link_plan(prototype_links_test_input(array('start_page_id' => 13)), array(11, 12), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok'], 'a page that is not a draft of the design');
	pg_assert_same('start_page', $plan['error']);
	pg_assert_same('start_page_id', $plan['field']);

	$plan = pg_proto_link_plan(prototype_links_test_input(), array(), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok'], 'a design without drafts');
	pg_assert_same('no_drafts', $plan['error']);
}

function test_prototype_links_plan_password_is_optional_and_bounded()
{
	$now = 1900000000;

	$plan = pg_proto_link_plan(prototype_links_test_input(array('password' => 'secret-1')), array(11), prototype_links_test_durations(), $now);
	pg_assert_true($plan['ok']);
	pg_assert_same('secret-1', $plan['link']['password']);

	$plan = pg_proto_link_plan(prototype_links_test_input(array('password' => 'abc')), array(11), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok'], 'too short');
	pg_assert_same('password', $plan['error']);

	$plan = pg_proto_link_plan(prototype_links_test_input(array('password' => str_repeat('a', 129))), array(11), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok'], 'too long');

	$plan = pg_proto_link_plan(prototype_links_test_input(array('password' => array('x'))), array(11), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok'], 'not text');

	// Spaces alone are no password; spaces around one are kept as given, as
	// the password form reads them.
	$plan = pg_proto_link_plan(prototype_links_test_input(array('password' => '      ')), array(11), prototype_links_test_durations(), $now);
	pg_assert_false($plan['ok'], 'spaces only');
	pg_assert_same('password_blank', $plan['error']);
	pg_assert_same('password', $plan['field']);

	$plan = pg_proto_link_plan(prototype_links_test_input(array('password' => ' k3Fq9z')), array(11), prototype_links_test_durations(), $now);
	pg_assert_true($plan['ok']);
	pg_assert_same(' k3Fq9z', $plan['link']['password']);

	// The API reads the password as sent, not as its router trims a string.
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/design.php');
	preg_match('~function api_design_prototype_links_create\(.*?\n}\n~s', $api, $create);
	pg_assert_contains('api_request_input()', isset($create[0]) ? $create[0] : '');
	pg_assert_false(strpos(isset($create[0]) ? $create[0] : '', "(string)\$params['password']") !== false, 'not the trimmed value');

	// Every refusal has a sentence for the person.
	foreach (array('audience', 'duration', 'start_page', 'no_drafts', 'password', 'password_blank') as $code) {
		pg_assert_true(pg_proto_link_error_message($code) !== '', $code);
	}
}

function test_prototype_links_state_revoked_expired_unknown_open()
{
	$now = 1900000000;

	pg_assert_same('unknown', pg_proto_link_state(null, $now));
	pg_assert_same('unknown', pg_proto_link_state(array(), $now));
	pg_assert_same('ok', pg_proto_link_state(array('id' => 4, 'revoked_at' => 0, 'expires_at' => 0), $now), 'a team link with no expiry');
	pg_assert_same('ok', pg_proto_link_state(array('id' => 4, 'revoked_at' => 0, 'expires_at' => $now + 1), $now));
	pg_assert_same('expired', pg_proto_link_state(array('id' => 4, 'revoked_at' => 0, 'expires_at' => $now), $now));
	pg_assert_same('revoked', pg_proto_link_state(array('id' => 4, 'revoked_at' => $now - 5, 'expires_at' => $now + 100), $now));
	pg_assert_same('revoked', pg_proto_link_state(array('id' => 4, 'revoked_at' => $now - 5, 'expires_at' => $now - 100), $now), 'revoked wins');

	// Only a guess is counted against the source: a token of the right
	// shape that nobody made. A revoked token was given to whoever holds it
	// (every click and reload of an open viewer brings it back), a token cut
	// short or written out as *** is no guess, and a link that ran out or
	// lost its drafts is somebody arriving late.
	$token = prototype_links_test_token();
	pg_assert_true(pg_proto_link_offence_due('unknown', $token));
	pg_assert_false(pg_proto_link_offence_due('revoked', $token), 'revoked: the people it was given to');
	pg_assert_false(pg_proto_link_offence_due('unknown', '***'), 'the written-out placeholder');
	pg_assert_false(pg_proto_link_offence_due('unknown', substr($token, 0, 20)), 'cut short');
	pg_assert_false(pg_proto_link_offence_due('unknown', ''), 'none');
	pg_assert_false(pg_proto_link_offence_due('unknown', array($token)), 'an array');
	pg_assert_false(pg_proto_link_offence_due('expired', $token));
	pg_assert_false(pg_proto_link_offence_due('gone', $token));
	pg_assert_false(pg_proto_link_offence_due('ok', $token));
}

function test_prototype_links_start_page_falls_back_to_the_first_draft()
{
	$pages = array(array('id' => 11), array('id' => 12), array('id' => 15));

	pg_assert_same(array('id' => 12, 'fallback' => false), pg_proto_link_start($pages, 12, 0), 'the link\'s own start page');
	pg_assert_same(array('id' => 11, 'fallback' => false), pg_proto_link_start($pages, 0, 0), 'no start page: the first draft');
	pg_assert_same(array('id' => 11, 'fallback' => false), pg_proto_link_start($pages, 13, 0), 'a start page no longer a draft');
	pg_assert_same(array('id' => 15, 'fallback' => false), pg_proto_link_start($pages, 12, 15), 'a page moved to in the prototype');
	pg_assert_same(array('id' => 12, 'fallback' => true), pg_proto_link_start($pages, 12, 99), 'a page asked for that is not a draft');
	pg_assert_same(array('id' => 0, 'fallback' => false), pg_proto_link_start(array(), 12, 12));
}

function test_prototype_links_password_locks_at_ten_failures_for_fifteen_minutes()
{
	$now = 1900000000;

	pg_assert_same(10, pg_proto_link_password_limit());
	pg_assert_same(900, pg_proto_link_lock_seconds());
	pg_assert_same(0, pg_proto_link_lock_after(9, $now));
	pg_assert_same($now + 900, pg_proto_link_lock_after(10, $now));
	// Past the limit every further failure locks again.
	pg_assert_same($now + 900, pg_proto_link_lock_after(11, $now));

	pg_assert_true(pg_proto_link_password_locked(array('password_locked_until' => $now + 1), $now));
	pg_assert_false(pg_proto_link_password_locked(array('password_locked_until' => $now), $now));
	pg_assert_false(pg_proto_link_password_locked(array('password_locked_until' => 0), $now));
	pg_assert_false(pg_proto_link_password_locked(array(), $now));
}

function test_prototype_links_password_cookie_is_bound_to_link_password_and_time()
{
	$now = 1900000000;
	$key = 'site-key';
	$hash = '$2y$10$abcdefghijklmnopqrstuuJ1yQ0bW2eJmQe1o2uS8b8y0Qe7Wq9a6';
	$value = pg_proto_link_pass_value(7, $hash, $now + pg_proto_link_pass_ttl(), $key);

	pg_assert_same(43200, pg_proto_link_pass_ttl(), 'twelve hours');
	pg_assert_same('pg_proto_pass_7', pg_proto_link_pass_cookie_name(7));
	pg_assert_true(pg_proto_link_pass_valid($value, 7, $hash, $key, $now));
	pg_assert_false(pg_proto_link_pass_valid($value, 8, $hash, $key, $now), 'another link');
	pg_assert_false(pg_proto_link_pass_valid($value, 7, $hash . 'x', $key, $now), 'the password changed');
	pg_assert_false(pg_proto_link_pass_valid($value, 7, $hash, 'other-key', $now), 'another key');
	pg_assert_false(pg_proto_link_pass_valid($value, 7, $hash, $key, $now + 43200), 'expired');
	pg_assert_false(pg_proto_link_pass_valid($value, 7, '', $key, $now), 'no password any more');
	pg_assert_false(pg_proto_link_pass_valid('', 7, $hash, $key, $now));
	pg_assert_false(pg_proto_link_pass_valid(array($value), 7, $hash, $key, $now));
	pg_assert_false(pg_proto_link_pass_valid('garbage', 7, $hash, $key, $now));

	// An expiry further ahead than a right password ever sets is no cookie
	// this site wrote.
	$far = pg_proto_link_pass_value(7, $hash, $now + 86400 * 30, $key);
	pg_assert_false(pg_proto_link_pass_valid($far, 7, $hash, $key, $now));

	pg_assert_same('', pg_proto_link_pass_value(7, $hash, $now + 60, ''), 'no key');
	pg_assert_same('', pg_proto_link_pass_value(0, $hash, $now + 60, $key), 'no link');
	pg_assert_same('', pg_proto_link_pass_value(7, '', $now + 60, $key), 'no password');
}

function test_prototype_links_frame_signature_round_trip()
{
	$now = 1900000000;
	$key = 'site-key';
	$token = prototype_links_test_token();
	$expires = $now + pg_proto_frame_ttl();
	$signature = pg_proto_link_frame_sign(7, 12, 4, $expires, $key, 'red-shirt', 'abc');

	pg_assert_same(1, preg_match('/^[0-9a-f]{64}$/', $signature));

	$query = pg_proto_link_frame_query(7, 12, 4, $expires, $signature, 'red-shirt', 'abc');
	pg_assert_contains('frame=1&s=7&p=12&l=4&e=' . $expires . '&sig=' . $signature, $query);
	// The frame runs the design's own scripts, which may report its address
	// to an analytics vendor: the token is never in it.
	pg_assert_false(strpos($query, 'link=') !== false, 'no token parameter');
	pg_assert_false(strpos($query, $token) !== false, 'no token');
	parse_str($query, $source);

	$parsed = pg_proto_link_frame_parse($source);
	pg_assert_true(is_array($parsed));
	pg_assert_same(4, $parsed['l']);
	pg_assert_false(isset($parsed['link']), 'the frame reads no token');
	pg_assert_same('red-shirt', $parsed['sub']);
	pg_assert_same('ok', pg_proto_link_frame_state($parsed, $key, $now));
	pg_assert_same('expired', pg_proto_link_frame_state($parsed, $key, $expires + 1));
}

function test_prototype_links_frame_signature_refuses_changes_and_the_person_mode()
{
	$now = 1900000000;
	$key = 'site-key';
	$expires = $now + 600;
	$signature = pg_proto_link_frame_sign(7, 12, 4, $expires, $key);
	parse_str(pg_proto_link_frame_query(7, 12, 4, $expires, $signature), $good);

	foreach (array('s' => '8', 'p' => '13', 'l' => '5', 'e' => (string) ($expires + 1), 'sub' => 'x', 'r' => 'y') as $name => $value) {
		$source = $good;
		$source[$name] = $value;
		pg_assert_same('invalid', pg_proto_link_frame_state(pg_proto_link_frame_parse($source), $key, $now), 'changed ' . $name);
	}

	pg_assert_same('invalid', pg_proto_link_frame_state(pg_proto_link_frame_parse($good), '', $now), 'no key');
	pg_assert_same('', pg_proto_link_frame_sign(7, 12, 4, $expires, ''), 'no key, no signature');
	pg_assert_same('', pg_proto_link_frame_sign(7, 12, 0, $expires, $key), 'no link');

	// An expiry further ahead than the viewer ever sets.
	$far = $now + 7200;
	parse_str(pg_proto_link_frame_query(7, 12, 4, $far, pg_proto_link_frame_sign(7, 12, 4, $far, $key)), $source);
	pg_assert_same('invalid', pg_proto_link_frame_state(pg_proto_link_frame_parse($source), $key, $now));

	// A signature the person mode made for user 4 opens no link 4, and the
	// other way round: each mode has a prefix of its own.
	$person = pg_proto_frame_sign(7, 12, 4, $expires, $key);
	pg_assert_true($person !== $signature);
	$source = $good;
	$source['sig'] = $person;
	pg_assert_same('invalid', pg_proto_link_frame_state(pg_proto_link_frame_parse($source), $key, $now));
	pg_assert_same('invalid', pg_proto_frame_state(array('s' => 7, 'p' => 12, 'u' => 4, 'e' => $expires, 'sig' => $signature, 'sub' => '', 'r' => ''), $key, $now));

	// The address of a link needs its link id, as a plain whole number.
	$source = $good;
	unset($source['l']);
	pg_assert_same(null, pg_proto_link_frame_parse($source));
	$source = $good;
	$source['l'] = '04';
	pg_assert_same(null, pg_proto_link_frame_parse($source));
}

function test_prototype_links_token_is_redacted_from_every_logged_address()
{
	$token = prototype_links_test_token();

	pg_assert_same('/pinegrap/prototype.php?link=***', pg_proto_link_redact_uri('/pinegrap/prototype.php?link=' . $token));
	pg_assert_same('/pinegrap/prototype.php?link=***&page=12', pg_proto_link_redact_uri('/pinegrap/prototype.php?link=' . $token . '&page=12'));
	pg_assert_same('/pinegrap/prototype.php?frame=1&s=7&link=***&r=a', pg_proto_link_redact_uri('/pinegrap/prototype.php?frame=1&s=7&link=' . $token . '&r=a'));
	pg_assert_same('/pinegrap/prototype.php?li%6Ek=***', pg_proto_link_redact_uri('/pinegrap/prototype.php?li%6Ek=' . $token), 'an escaped name');
	pg_assert_same('/pinegrap/prototype.php?link[]=***', pg_proto_link_redact_uri('/pinegrap/prototype.php?link[]=' . $token), 'as an array');
	pg_assert_same('/pinegrap/prototype.php?page=3&xlink=abc', pg_proto_link_redact_uri('/pinegrap/prototype.php?page=3&xlink=abc'), 'another name');
	pg_assert_same('/pinegrap/prototype.php?page=3', pg_proto_link_redact_uri('/pinegrap/prototype.php?page=3'));
	pg_assert_same('link=***&page=3', pg_proto_link_redact_uri('link=' . $token . '&page=3'), 'a query string alone');

	$server = pg_proto_link_redact_server(array(
		'REQUEST_URI' => '/pinegrap/prototype.php?link=' . $token,
		'HTTP_X_REWRITE_URL' => '/pinegrap/prototype.php?link=' . $token,
		'UNENCODED_URL' => '/pinegrap/prototype.php?link=' . $token,
		'QUERY_STRING' => 'link=' . $token,
		'REDIRECT_QUERY_STRING' => 'link=' . $token,
		'HTTP_USER_AGENT' => 'link=keep',
	));

	foreach (array('REQUEST_URI', 'HTTP_X_REWRITE_URL', 'UNENCODED_URL', 'QUERY_STRING', 'REDIRECT_QUERY_STRING') as $name) {
		pg_assert_false(strpos($server[$name], $token) !== false, $name);
	}

	pg_assert_same('link=keep', $server['HTTP_USER_AGENT'], 'not an address');
}

function test_prototype_links_viewer_bar_carries_the_token_the_frame_links_never_do()
{
	$token = prototype_links_test_token();
	$base = pg_proto_link_viewer_base('/pinegrap/prototype.php', $token);

	// The viewer's own bar (out of the frame's reach) keeps the link's
	// address, so it can be reloaded and bookmarked.
	pg_assert_same('/pinegrap/prototype.php?link=' . $token, $base);
	pg_assert_same('/pinegrap/prototype.php?link=' . $token . '&page=12&r=a', pg_proto_viewer_url($base, 12, '', 'a'));
	// The person's viewer keeps its addresses.
	pg_assert_same('/pinegrap/prototype.php?page=12', pg_proto_viewer_url('/pinegrap/prototype.php', 12));

	// The frame's links name the link by its id: the design's scripts read
	// every link on the page.
	$frame_base = pg_proto_link_frame_base('/pinegrap/prototype.php', 4);
	pg_assert_same('/pinegrap/prototype.php?l=4', $frame_base);

	$ctx = prototype_links_test_ctx($frame_base);
	$target = pg_proto_link_target('/pricing', $ctx);
	pg_assert_same('prototype', $target['kind']);
	pg_assert_same('/pinegrap/prototype.php?l=4&page=12', $target['href']);

	$html = pg_proto_rewrite_links('<a href="/pricing">P</a> <a href="?r=x#top">R</a> <a href="/start#a">S</a>', function ($href) use ($ctx) {
		return pg_proto_link_target($href, $ctx);
	}, array());
	pg_assert_false(strpos($html, $token) !== false, 'no token in the drawn page');
	pg_assert_false(strpos($html, 'link=') !== false, 'no link parameter in the drawn page');
	pg_assert_contains('href="/pinegrap/prototype.php?l=4&amp;page=12"', $html);

	// prototype.php builds the frame's links on the link's id, and the frame
	// reads the link by its id alone.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');
	pg_assert_contains('$pg_proto_base = pg_proto_link_frame_base($pg_proto_viewer, $parsed[\'l\']);', $source);
	pg_assert_false(strpos($source, "\$parsed['link']") !== false, 'the frame reads no token');
	pg_assert_false(strpos($source, '$pg_proto_signature, $pg_proto_token') !== false, 'the frame address is made without the token');
}

function test_prototype_links_frame_links_open_only_in_the_session_that_opened_the_link()
{
	$token = prototype_links_test_token();
	$row = array('id' => 4, 'token_hash' => hash('sha256', $token));

	pg_assert_true(pg_proto_link_session_opens(hash('sha256', $token), $row));
	pg_assert_false(pg_proto_link_session_opens('', $row), 'no mark');
	pg_assert_false(pg_proto_link_session_opens(null, $row), 'no mark');
	pg_assert_false(pg_proto_link_session_opens(array(hash('sha256', $token)), $row), 'not text');
	pg_assert_false(pg_proto_link_session_opens(hash('sha256', 'another'), $row), 'another link\'s mark');
	pg_assert_false(pg_proto_link_session_opens(hash('sha256', $token), null), 'no link');

	$kept = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();
	pg_proto_link_session_mark($row);
	pg_assert_same(hash('sha256', $token), $_SESSION['software']['prototype_link_open'][4]);
	pg_assert_false(strpos((string) json_encode($_SESSION), $token) !== false, 'the session keeps the hash, not the token');
	$_SESSION = $kept;

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');

	// Marked past every gate (team sign-in, password), checked on a frame
	// link before anything else, and sent on to the link's own address.
	$password_gate = strpos($source, 'pg_proto_link_pass_valid(');
	$mark = strpos($source, 'pg_proto_link_session_mark($pg_proto_link);');
	pg_assert_true(($mark !== false) && ($mark > $password_gate), 'marked past the gates');
	pg_assert_contains('pg_proto_link_session_opens(', $source);
	pg_assert_contains("isset(\$_GET['link']) || isset(\$_GET['l'])", $source);
}

function test_prototype_links_password_attempt_is_counted_in_the_database_before_the_check()
{
	$now = 1900000000;
	$sql = pg_proto_link_attempt_sql(7, $now);

	// Only while not locked, read from the row in the database.
	pg_assert_contains("WHERE id = '7' AND password_locked_until <= '1900000000'", $sql);
	// The attempt that reaches the limit locks at once, before its password
	// is checked; a right one clears it again.
	pg_assert_contains("password_locked_until = IF(password_failures + 1 >= 10, '1900000900', password_locked_until)", $sql);
	pg_assert_contains('password_failures = password_failures + 1', $sql);
	// The lock is worked out from the count before this attempt whether the
	// server assigns left to right (MySQL) or all at once (MariaDB's
	// SIMULTANEOUS_ASSIGNMENT).
	pg_assert_true(strpos($sql, 'password_locked_until = IF(') < strpos($sql, 'password_failures = password_failures + 1'), 'lock before count');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');
	preg_match('~function pg_proto_link_password_try\(.*?\n}\n~s', $source, $match);
	pg_assert_true(isset($match[0]), 'pg_proto_link_password_try found');
	$body = isset($match[0]) ? $match[0] : '';

	// Requests sent at once do not get past a lock written meanwhile: the
	// lock is not read from the row the request started with.
	pg_assert_false(strpos($body, 'pg_proto_link_password_locked(') !== false, 'not from the stale row');
	pg_assert_true(strpos($body, 'pg_proto_link_attempt_sql(') < strpos($body, 'password_verify('), 'counted before the check');
	pg_assert_contains('mysqli_affected_rows(db::$con)', $body);
}

function test_prototype_links_secure_mode_redirect_keeps_the_token()
{
	$token = prototype_links_test_token();

	// The address as get_request_uri() reads it, before the token is written
	// out of it.
	pg_assert_same('/pinegrap/prototype.php?link=' . $token . '&page=3', pg_proto_link_request_uri(array('REQUEST_URI' => '/pinegrap/prototype.php?link=' . $token . '&page=3')));
	pg_assert_same('/x.php?link=a', pg_proto_link_request_uri(array('HTTP_X_REWRITE_URL' => '/x.php?link=a', 'REQUEST_URI' => '/y.php')), 'IIS rewrite first');
	pg_assert_same('/p.php?link=a', pg_proto_link_request_uri(array('PHP_SELF' => '/p.php', 'QUERY_STRING' => 'link=a')));
	pg_assert_same('/p.php', pg_proto_link_request_uri(array('PHP_SELF' => '/p.php', 'QUERY_STRING' => '')));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');
	$define = strpos($source, "define('PG_SECURE_MODE_REDIRECT_URL', pg_proto_link_request_uri(\$_SERVER));");
	$redact = strpos($source, '$_SERVER = pg_proto_link_redact_server($_SERVER);');
	pg_assert_true(($define !== false) && ($define < $redact), 'kept before the address is written out');

	// Both of Secure Mode's redirects send the visitor on to it.
	$init = (string) file_get_contents(PG_FUNCTIONS_DIR . '/init.php');
	pg_assert_same(2, substr_count($init, "HOSTNAME_SETTING . (defined('PG_SECURE_MODE_REDIRECT_URL') ? PG_SECURE_MODE_REDIRECT_URL : REQUEST_URL)"));
	pg_assert_false(strpos($init, 'HOSTNAME_SETTING . REQUEST_URL') !== false, 'no redirect from the written-out address');
}

function test_prototype_links_end_with_their_design()
{
	$now = 1900000000;

	pg_assert_same("UPDATE prototype_links SET revoked_at = '1900000000', revoked_by = '3' WHERE style_id IN (7,9) AND revoked_at = 0", pg_proto_links_revoke_design_sql(array(7, '9', 0, -1), 3, $now));
	pg_assert_same("UPDATE prototype_links SET revoked_at = '1900000000', revoked_by = '0' WHERE style_id IN (7) AND revoked_at = 0", pg_proto_links_revoke_design_sql(7, 0, $now));
	pg_assert_same('', pg_proto_links_revoke_design_sql(array(), 3, $now), 'no design');
	pg_assert_same('', pg_proto_links_revoke_design_sql(array(0), 3, $now), 'no design');

	// Every path that deletes a design's style row ends its links: a later
	// design given the same id (InnoDB on MySQL 5.7 after a restart) never
	// opens for them.
	foreach (array('includes/fn/designer.php', 'view_folder_and_files_f.php', 'delete_styles.php', 'includes/install_design_template.php') as $file) {
		pg_assert_contains('pg_proto_links_revoke_design(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file);
	}
}

function test_prototype_links_anyone_sees_only_drafts_a_visitor_could_open()
{
	// A link for anyone draws a draft as a signed-out visitor would get it
	// once published: not one going live behind a sign-in.
	pg_assert_true(pg_proto_link_page_shown('anyone', 'public'));
	pg_assert_true(pg_proto_link_page_shown('anyone', 'guest'), 'a guest needs no account');

	foreach (array('private', 'registration', 'membership', '') as $type) {
		pg_assert_false(pg_proto_link_page_shown('anyone', $type), $type);
		// The team sees every draft, as it does on the site.
		pg_assert_true(pg_proto_link_page_shown('team', $type), 'team: ' . $type);
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');
	pg_assert_contains("pg_proto_link_page_shown((string) \$pg_proto_link['audience'], pg_proto_link_page_access(\$pg_proto_page['id']))", $source);
}

function test_prototype_links_fallback_notice_names_the_start_page()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');

	// A link falls back to its own start page, which need not be the first.
	pg_assert_contains("lang('The page you opened is no longer a draft of this design, so the prototype opens at its start page.')", $source);
	pg_assert_false(strpos($source, 'so the prototype starts at its first page.') !== false);
}

function test_prototype_links_share_dialog_is_named_and_copies_with_a_fallback()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$start = strpos($source, 'function _pgPrototypeShareDialog(');
	$end = strpos($source, 'function _pgTabsSyncViewButton(');
	$dialog = (($start !== false) && ($end !== false)) ? substr($source, $start, $end - $start) : '';

	pg_assert_true($dialog !== '', 'the dialog found');

	// The dialog and the audience radios have names a screen reader reads.
	pg_assert_contains("el.setAttribute('aria-labelledby', 'ps-title');", $dialog);
	pg_assert_contains('id="ps-title"', $dialog);
	pg_assert_contains('role="radiogroup" aria-labelledby="ps-audience-label"', $dialog);
	pg_assert_contains('id="ps-audience-label"', $dialog);

	// A refused write never ends in silence: the address is shown selected
	// with a note. Safari keeps the click's permission while the address is
	// still on its way when the write starts inside the click.
	pg_assert_contains('navigator.clipboard.writeText(text).then(copied, function () { copyFailed(text, field); });', $dialog);
	pg_assert_contains('new ClipboardItem(', $dialog);
}

function prototype_links_test_ctx($viewer)
{
	return array(
		'env' => array('host' => 'example.com', 'path' => '/', 'software_directory' => 'pinegrap'),
		'maps' => array(
			'pages' => array(
				'start' => array('id' => 11, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 50),
				'pricing' => array('id' => 12, 'type' => 'standard', 'layout_type' => 'system', 'folder' => 50),
			),
			'files' => array(),
			'short_links' => array(),
			'products' => array(),
			'product_groups' => array(),
			'home_page_id' => 0,
		),
		'form_names' => array(),
		'proto' => array(11 => true, 12 => true),
		'draft_folder' => 50,
		'bin_folders' => array(),
		'current_page' => 11,
		'current_sub' => '',
		'current_record' => '',
		'languages' => array(),
		'viewer' => $viewer,
	);
}

function test_prototype_links_use_counts_on_a_human_get_only()
{
	pg_assert_true(pg_proto_link_counts_use('GET', 'human'));
	pg_assert_true(pg_proto_link_counts_use('get', 'human'));
	pg_assert_false(pg_proto_link_counts_use('HEAD', 'human'), 'HEAD');
	pg_assert_false(pg_proto_link_counts_use('POST', 'human'), 'the password form');
	pg_assert_false(pg_proto_link_counts_use('GET', 'good'), 'a known bot, a link preview');
	pg_assert_false(pg_proto_link_counts_use('GET', 'empty'), 'no user agent');
}

function test_prototype_links_summary_says_where_a_link_stands()
{
	$now = 1900000000;
	$row = array(
		'id' => '4', 'style_id' => '7', 'start_page_id' => '12', 'token_hash' => str_repeat('a', 64), 'token_secret' => 'x:y',
		'token_hint' => 'AbCdEf', 'audience' => 'anyone', 'password_hash' => '$2y$10$hash', 'password_failures' => '3',
		'password_locked_until' => (string) ($now + 60), 'expires_at' => (string) ($now + 3600), 'created_by' => '2',
		'created_at' => (string) ($now - 60), 'revoked_at' => '0', 'revoked_by' => '0', 'uses' => '5', 'last_used_at' => (string) ($now - 10),
	);

	$summary = pg_proto_link_summary($row, $now);
	pg_assert_same(4, $summary['id']);
	pg_assert_same(7, $summary['style_id']);
	pg_assert_same(12, $summary['start_page_id']);
	pg_assert_same('anyone', $summary['audience']);
	pg_assert_same('AbCdEf', $summary['hint']);
	pg_assert_same(true, $summary['password']);
	pg_assert_same(true, $summary['locked']);
	pg_assert_same('open', $summary['state']);
	pg_assert_same(5, $summary['uses']);
	pg_assert_same($now + 3600, $summary['expires_at']);
	// Nothing that opens the link is in it.
	pg_assert_false(isset($summary['token_hash']) || isset($summary['token_secret']) || isset($summary['password_hash']));

	$row['password_hash'] = null;
	$row['expires_at'] = (string) ($now - 1);
	$summary = pg_proto_link_summary($row, $now);
	pg_assert_same(false, $summary['password']);
	pg_assert_same(false, $summary['locked'], 'no password, no lock');
	pg_assert_same('expired', $summary['state']);

	$row['revoked_at'] = (string) ($now - 5);
	pg_assert_same('revoked', pg_proto_link_summary($row, $now)['state']);
}

function test_prototype_links_password_page_has_a_token_and_no_design_name()
{
	$html = pg_proto_link_password_html(array(
		'action' => '/pinegrap/prototype.php?link=' . prototype_links_test_token(),
		'token_field' => '<input type="hidden" name="token" value="csrf">',
		'error' => 'That password is not right.',
		'locked' => false,
		'software' => '/pinegrap',
		'lang' => 'en',
	));

	pg_assert_contains('<form method="post" action="/pinegrap/prototype.php?link=' . prototype_links_test_token() . '"', $html);
	pg_assert_contains('type="password"', $html);
	pg_assert_contains('autocomplete="current-password"', $html);
	pg_assert_contains('name="token" value="csrf"', $html);
	pg_assert_contains('That password is not right.', $html);
	pg_assert_contains('<meta name="robots" content="noindex, nofollow">', $html);

	$locked = pg_proto_link_password_html(array('action' => '/x', 'token_field' => '', 'error' => 'Locked', 'locked' => true, 'software' => '/pinegrap', 'lang' => 'en'));
	pg_assert_contains(' disabled', $locked);
}

function test_prototype_links_shell_has_no_close_button_for_a_link()
{
	$html = pg_proto_shell_html(array(
		'design' => 'Spring',
		'pages' => array(array('id' => 12, 'name' => 'pricing', 'title' => 'Pricing', 'scheduled' => false)),
		'current' => 12,
		'fallback' => false,
		'frame_url' => '/pinegrap/prototype.php?frame=1',
		'viewer' => '/pinegrap/prototype.php?link=' . prototype_links_test_token(),
		'close_url' => '',
		'software' => '/pinegrap',
		'lang' => 'en',
		'error' => '',
	));

	pg_assert_false(strpos($html, 'id="pg-proto-close"') !== false);
	pg_assert_contains('<option value="/pinegrap/prototype.php?link=' . prototype_links_test_token() . '&amp;page=12" selected>', $html);
}

function test_prototype_links_php_redacts_before_init_and_gates_each_audience()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/prototype.php');
	$init = strpos($source, "include('init.php');");

	// The token leaves every address a log writes down before init.php runs
	// the firewall.
	$redact = strpos($source, '$_SERVER = pg_proto_link_redact_server($_SERVER);');
	pg_assert_true(($redact !== false) && ($redact < $init), 'redacted before init.php');

	// One answer for a revoked, expired or unknown link, counted for a
	// guessed token only.
	pg_assert_contains("pg_proto_frame_refuse(lang('This link no longer works.'), 404);", $source);
	pg_assert_contains('pg_proto_link_offence_due($pg_proto_state, $pg_proto_token)', $source);
	pg_assert_contains('pg_proto_link_offence();', $source);

	// A team link asks for a signed-in manager or above; a password link
	// for its cookie; the form is sent with the session's form token.
	pg_assert_contains("'team'", $source);
	pg_assert_contains('pg_proto_viewer_role_allowed($pg_proto_link_user[\'role\'])', $source);
	pg_assert_contains('pg_proto_link_pass_valid(', $source);
	pg_assert_contains('validate_token_field();', $source);
	pg_assert_contains('pg_post_body_discarded()', $source);

	// The frame of a link checks the link again, not a person.
	pg_assert_contains('pg_proto_link_frame_state(', $source);
	pg_assert_contains('pg_proto_link_state(', $source);

	// Uses are counted on a human GET only.
	pg_assert_contains('pg_proto_link_counts_use(', $source);
}

function test_prototype_links_log_lines_name_id_and_hint_never_the_token()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/prototype_links.php');

	preg_match_all('~log_activity\((.*?)\);~s', $source, $calls);
	pg_assert_true(count($calls[1]) >= 3, 'issue, revoke and lock are logged');

	foreach ($calls[1] as $call) {
		pg_assert_false(strpos($call, '$token') !== false, 'a log line with the token: ' . $call);
	}

	// Each line names the link as "#id (hint…)", from the row's hint.
	pg_assert_same(4, substr_count($source, "#{var:1} ({var:2}…)"), 'shared with anyone, with the team, revoked, locked');
	pg_assert_true(substr_count($source, "(string) \$row['token_hint']") >= 3);
}

function test_prototype_links_designer_endpoints_are_for_designers_and_sorted()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	foreach (array('prototype_links', 'prototype_link_create', 'prototype_link_address', 'prototype_link_revoke') as $sub) {
		pg_assert_contains("        case '" . $sub . "':", $source);
		// Not opened to a content-level operator (roles 2 and 3).
		$allow = array();
		preg_match('~\$content_ok = array\((.*?)\);~s', $source, $allow);
		pg_assert_false(isset($allow[1]) && (strpos($allow[1], "'" . $sub . "'") !== false), $sub . ' is for designers');
	}
}

function test_prototype_links_schema_step_and_table_list()
{
	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_prototype_links();        // 11.0.35', $migration);
	pg_assert_contains("install_create_table('prototype_links'", $migration);
	pg_assert_false(strpos($migration, 'ENUM(\'anyone\'') !== false, 'the audience is text, not an enum');

	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_contains("'prototype_links',", $install);
}

// The API's declarations, loaded as the panel's document loads them.
function prototype_links_test_api()
{
	if (!defined('PG_API_PANEL')) {
		define('PG_API_PANEL', true);
	}

	if (!defined('PG_MCP_ENTRY')) {
		define('PG_MCP_ENTRY', true);
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/design.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');

	$routes = array();

	foreach (api_schema() as $route) {
		if (isset($route['id']) && (strpos($route['id'], 'design.prototype_links.') === 0)) {
			$routes[$route['id']] = $route;
		}
	}

	return $routes;
}

function test_prototype_links_api_routes_are_never_mcp_tools()
{
	$routes = prototype_links_test_api();

	pg_assert_same(array('design.prototype_links.list', 'design.prototype_links.create', 'design.prototype_links.revoke'), array_keys($routes));

	foreach ($routes as $id => $route) {
		pg_assert_same(false, isset($route['mcp']) ? $route['mcp'] : null, $id . ' says mcp false');
		pg_assert_false(pg_mcp_route_is_offered($route), $id . ' is no tool');
	}

	pg_assert_same('design:read', $routes['design.prototype_links.list']['scope']);

	foreach (array('design.prototype_links.create', 'design.prototype_links.revoke') as $id) {
		pg_assert_same('POST', $routes[$id]['method'], $id);
		pg_assert_same('design:write', $routes[$id]['scope'], $id);
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' can be rehearsed');
	}

	$params = array();

	foreach ($routes['design.prototype_links.create']['params'] as $param) {
		$params[$param['name']] = $param;
	}

	pg_assert_same(array('anyone', 'team'), $params['audience']['values']);
	pg_assert_true(!empty($params['audience']['required']));
	pg_assert_same(128, $params['password']['max_length']);
	pg_assert_same(201, $routes['design.prototype_links.create']['status']);
}

function test_prototype_links_api_presenter_matches_its_schema_and_hides_the_token()
{
	prototype_links_test_api();

	if (!function_exists('api_time')) {
		function api_time($unix)
		{
			return ((int) $unix > 0) ? gmdate('Y-m-d\\TH:i:s\\Z', (int) $unix) : null;
		}
	}

	$now = 1900000000;
	$row = array(
		'id' => '4', 'style_id' => '7', 'start_page_id' => '0', 'token_hash' => str_repeat('a', 64), 'token_secret' => 'x:y',
		'token_hint' => 'AbCdEf', 'audience' => 'team', 'password_hash' => null, 'password_failures' => '0',
		'password_locked_until' => '0', 'expires_at' => '0', 'created_by' => '2',
		'created_at' => (string) ($now - 60), 'revoked_at' => '0', 'revoked_by' => '0', 'uses' => '0', 'last_used_at' => '0',
	);

	$out = api_design_prototype_link_present($row, $now);

	pg_assert_same(array_keys(api_design_prototype_link_schema()), array_keys($out));
	pg_assert_same(null, $out['start_page_id'], 'the first draft');
	pg_assert_same(null, $out['expires_at'], 'a team link that runs for good');
	pg_assert_same('open', $out['state']);
	pg_assert_false(strpos((string) json_encode($out), str_repeat('a', 64)) !== false, 'no token hash');
	pg_assert_false(strpos((string) json_encode($out), 'x:y') !== false, 'no sealed token');
}
