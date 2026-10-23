<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/form_subscription.php: the signed confirmation link of
 * a double opt-in form (token, query, parsing, the state a link is in) and the
 * decisions taken on submission (hold the group back, mail again).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A keyed hash over contact, group, time and the normalised address, with its own prefix.
function test_form_subscription_token_is_keyed_and_bound()
{
	$token = pg_fs_token(5, 7, 'ali@example.com', 1760000000, 'k1');

	pg_assert_same(hash_hmac('sha256', 'subscribe-confirm:5:7:1760000000:ali@example.com', 'k1'), $token, 'HMAC of the prefixed values');
	pg_assert_same($token, pg_fs_token('5', '7', '  ALI@Example.com ', '1760000000', 'k1'), 'same address, other spelling');

	pg_assert_false($token === pg_fs_token(6, 7, 'ali@example.com', 1760000000, 'k1'), 'another contact');
	pg_assert_false($token === pg_fs_token(5, 8, 'ali@example.com', 1760000000, 'k1'), 'another group');
	pg_assert_false($token === pg_fs_token(5, 7, 'ali@example.com', 1760000001, 'k1'), 'another time');
	pg_assert_false($token === pg_fs_token(5, 7, 'veli@example.com', 1760000000, 'k1'), 'another address');
	pg_assert_false($token === pg_fs_token(5, 7, 'ali@example.com', 1760000000, 'k2'), 'another key');
	pg_assert_false($token === pg_privacy_person_token('ali@example.com', 'k1'), 'not the person token');
}

// No token without every part: a link that cannot be checked is never made.
function test_form_subscription_token_needs_every_part()
{
	pg_assert_same('', pg_fs_token(5, 7, 'ali@example.com', 1760000000, ''), 'no key');
	pg_assert_same('', pg_fs_token(0, 7, 'ali@example.com', 1760000000, 'k1'), 'no contact');
	pg_assert_same('', pg_fs_token(5, 0, 'ali@example.com', 1760000000, 'k1'), 'no group');
	pg_assert_same('', pg_fs_token(5, 7, 'ali@example.com', 0, 'k1'), 'no time');
	pg_assert_same('', pg_fs_token(5, 7, 'not an address', 1760000000, 'k1'), 'no address');
	pg_assert_same('', pg_fs_token(-5, 7, 'ali@example.com', 1760000000, 'k1'), 'negative contact');
}

// Seven days.
function test_form_subscription_ttl()
{
	pg_assert_same(604800, pg_fs_ttl());
}

// The query a link carries, read back by the parser.
function test_form_subscription_query_round_trip()
{
	$sig = str_repeat('a1', 32);
	$query = pg_fs_query(5, 7, 1760000000, $sig);

	pg_assert_same('c=5&g=7&t=1760000000&s=' . $sig, $query, 'query');
	pg_assert_same('c=5&amp;g=7&amp;t=1760000000&amp;s=' . $sig, pg_fs_query(5, 7, 1760000000, $sig, '&amp;'), 'for HTML');

	parse_str($query, $source);

	pg_assert_same(array('c' => 5, 'g' => 7, 't' => 1760000000, 's' => $sig), pg_fs_parse($source), 'parsed back');
}

// Only plain positive numbers and a 64-character lower-case hex signature are read.
function test_form_subscription_parse_refuses_anything_else()
{
	$valid = array('c' => '5', 'g' => '7', 't' => '1760000000', 's' => str_repeat('0f', 32));

	pg_assert_true(is_array(pg_fs_parse($valid)), 'valid');

	foreach (array('c', 'g', 't') as $key) {
		foreach (array('', '0', '-5', '+5', '05', '5.0', '5 ', ' 5', '1e3', '0x10', '99999999999', array('5'), null, 5) as $bad) {
			pg_assert_same(null, pg_fs_parse(array($key => $bad) + $valid), $key . ' ' . var_export($bad, true));
		}
	}

	foreach (array('', str_repeat('A', 64), str_repeat('a', 63), str_repeat('a', 65), str_repeat('g', 64), array(str_repeat('a', 64))) as $bad) {
		pg_assert_same(null, pg_fs_parse(array('s' => $bad) + $valid), 's ' . var_export($bad, true));
	}

	$missing = $valid;
	unset($missing['s']);

	pg_assert_same(null, pg_fs_parse($missing), 'no signature');
	pg_assert_same(null, pg_fs_parse(array()), 'nothing');
	pg_assert_same(null, pg_fs_parse('c=5'), 'not an array');
}

// Invalid before anything else; then already a member, expired, or ready to confirm.
function test_form_subscription_link_state()
{
	$issued = 1760000000;
	$expected = pg_fs_token(5, 7, 'ali@example.com', $issued, 'k1');
	$parsed = array('c' => 5, 'g' => 7, 't' => $issued, 's' => $expected);

	pg_assert_same('confirm', pg_fs_link_state($parsed, $expected, $issued + 60, false), 'fresh');
	pg_assert_same('confirm', pg_fs_link_state($parsed, $expected, $issued + pg_fs_ttl(), false), 'last second');
	pg_assert_same('expired', pg_fs_link_state($parsed, $expected, $issued + pg_fs_ttl() + 1, false), 'one second late');
	pg_assert_same('member', pg_fs_link_state($parsed, $expected, $issued + 60, true), 'already in the group');
	pg_assert_same('member', pg_fs_link_state($parsed, $expected, $issued + pg_fs_ttl() + 1, true), 'already in the group, link old');
	pg_assert_same('confirm', pg_fs_link_state($parsed, $expected, $issued - 300, false), 'clock skew allowed');
	pg_assert_same('invalid', pg_fs_link_state($parsed, $expected, $issued - 301, false), 'issued in the future');

	pg_assert_same('invalid', pg_fs_link_state(array('s' => str_repeat('0', 64)) + $parsed, $expected, $issued + 60, false), 'wrong signature');
	pg_assert_same('invalid', pg_fs_link_state($parsed, '', $issued + 60, true), 'nothing to compare with');
	pg_assert_same('invalid', pg_fs_link_state(null, $expected, $issued + 60, false), 'not parsed');
	pg_assert_same('invalid', pg_fs_link_state($parsed, null, $issued + 60, false), 'expected is not a string');
}

// The form's group waits for the confirmation only with the switch on, a group set, and no membership yet.
function test_form_subscription_hold_group()
{
	pg_assert_true(pg_fs_hold_group(1, 7, false), 'held');
	pg_assert_true(pg_fs_hold_group('1', '7', false), 'from a row');
	pg_assert_false(pg_fs_hold_group(0, 7, false), 'switch off');
	pg_assert_false(pg_fs_hold_group(1, 0, false), 'no group');
	pg_assert_false(pg_fs_hold_group(1, 7, true), 'already a member');
	pg_assert_false(pg_fs_hold_group(null, 7, false), 'column missing');
}

// One confirmation mail per address and group a day.
function test_form_subscription_resend_allowed()
{
	$now = 1760000000;

	pg_assert_true(pg_fs_resend_allowed(0, $now), 'never sent');
	pg_assert_true(pg_fs_resend_allowed(null, $now), 'no row');
	pg_assert_false(pg_fs_resend_allowed($now - 60, $now), 'a minute ago');
	pg_assert_false(pg_fs_resend_allowed($now - 86399, $now), 'a second short of a day');
	pg_assert_true(pg_fs_resend_allowed($now - 86400, $now), 'a day ago');
	pg_assert_false(pg_fs_resend_allowed($now + 600, $now), 'a row stamped ahead');
}

// The link handed to the submitter's mail is kept per submitted form, empty otherwise.
function test_form_subscription_pending_url_holder()
{
	pg_assert_same('', pg_fs_pending_url(901), 'nothing yet');
	pg_assert_same('https://example.com/c', pg_fs_pending_url(901, 'https://example.com/c'), 'set');
	pg_assert_same('https://example.com/c', pg_fs_pending_url(901), 'read back');
	pg_assert_same('', pg_fs_pending_url(902), 'another form');
}

// Subscribed means in the group and not opted out of it on the e-mail
// preferences screen; an opted-out member may confirm again.
function test_form_subscription_subscribed()
{
	pg_assert_true(pg_fs_subscribed(true, null), 'in the group, no opt_in row');
	pg_assert_true(pg_fs_subscribed(true, '1'), 'in the group, opted in');
	pg_assert_true(pg_fs_subscribed(true, 1), 'opted in as an integer');
	pg_assert_false(pg_fs_subscribed(true, '0'), 'in the group, opted out');
	pg_assert_false(pg_fs_subscribed(true, 0), 'opted out as an integer');
	pg_assert_false(pg_fs_subscribed(false, null), 'not in the group');
	pg_assert_false(pg_fs_subscribed(false, '1'), 'an opt_in row without the group');
}
