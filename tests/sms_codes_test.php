<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the codes sent by text message (includes/fn/sms_codes.php):
 * keeping one as a hash, checking it once, its binding to a purpose, a
 * subject and a number, the guess limit and the queue options. No database
 * and no provider.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A code is six digits, kept only as a hash, used once.
function test_sms_codes_keep_and_check()
{
	for ($i = 0; $i < 20; $i++) {
		pg_assert_true((bool) preg_match('/^[0-9]{6}$/D', pg_sms_code_new()), 'six digits');
	}

	$store = null;
	pg_assert_true(pg_sms_code_keep($store, 'cod', 'order:12', '905321234567', '864209', 1000));
	pg_assert_false(strpos(serialize($store), '864209') !== false, 'only a hash is kept');
	pg_assert_same('code_wrong', pg_sms_code_check($store, 'cod', 'order:12', '905321234567', '654321', 1001), 'wrong code');
	pg_assert_same('code_wrong', pg_sms_code_check($store, 'cod', 'order:12', '905321234567', '', 1001), 'empty code');
	pg_assert_same('', pg_sms_code_check($store, 'cod', 'order:12', '905321234567', ' 864 209 ', 1002), 'spaces are ignored');
	pg_assert_same('code_expired', pg_sms_code_check($store, 'cod', 'order:12', '905321234567', '864209', 1003), 'used once');
}

// A code confirms only what it was sent for, and only the number it went to;
// a check for something else leaves it as it is.
function test_sms_codes_binding()
{
	$store = null;
	pg_sms_code_keep($store, 'cod', 'order:12', '905321234567', '123456', 1000);
	pg_assert_same('code_expired', pg_sms_code_check($store, 'signin', 'order:12', '905321234567', '123456', 1001), 'another purpose');
	pg_assert_same('code_expired', pg_sms_code_check($store, 'cod', 'order:13', '905321234567', '123456', 1001), 'another order');
	pg_assert_same('code_expired', pg_sms_code_check($store, 'cod', 'order:12', '905329999999', '123456', 1001), 'another number');
	pg_assert_same('', pg_sms_code_check($store, 'cod', 'order:12', '905321234567', '123456', 1001), 'the others did not use it up');

	pg_assert_false(pg_sms_code_keep($store, 'other', 'order:12', '905321234567', '123456', 1000), 'unknown purpose');
	pg_assert_false(pg_sms_code_keep($store, 'cod', '', '905321234567', '123456', 1000), 'no subject');
	pg_assert_false(pg_sms_code_keep($store, 'cod', 'order:12', '', '123456', 1000), 'no number');
}

// Ten minutes, a newer code replaces the older, the fifth wrong guess ends it.
function test_sms_codes_lifetime_and_guesses()
{
	$store = null;
	pg_sms_code_keep($store, 'signin', 'user:5', '905321234567', '123456', 1000);
	pg_assert_same('code_expired', pg_sms_code_check($store, 'signin', 'user:5', '905321234567', '123456', 1000 + pg_sms_code_lifetime() + 1), 'too old');
	pg_assert_same(null, $store, 'an old code is dropped');

	pg_sms_code_keep($store, 'signin', 'user:5', '905321234567', '123456', 1000);
	pg_assert_same('code_expired', pg_sms_code_check($store, 'signin', 'user:5', '905321234567', '123456', 999), 'kept in the future');

	pg_sms_code_keep($store, 'signin', 'user:5', '905321234567', '123456', 1000);
	pg_sms_code_keep($store, 'signin', 'user:5', '905321234567', '222222', 1010);
	pg_assert_same('code_wrong', pg_sms_code_check($store, 'signin', 'user:5', '905321234567', '123456', 1011), 'a newer code replaces the older');

	pg_sms_code_keep($store, 'signin', 'user:5', '905321234567', '123456', 1000);

	for ($i = 0; $i < 4; $i++) {
		pg_assert_same('code_wrong', pg_sms_code_check($store, 'signin', 'user:5', '905321234567', '000000', 1001), 'guess ' . ($i + 1));
	}

	pg_assert_same('code_expired', pg_sms_code_check($store, 'signin', 'user:5', '905321234567', '000000', 1001), 'fifth wrong guess ends it');
	pg_assert_same('code_expired', pg_sms_code_check($store, 'signin', 'user:5', '905321234567', '123456', 1001), 'right code after that');

	$store = 'broken';
	pg_assert_same('code_expired', pg_sms_code_check($store, 'signin', 'user:5', '905321234567', '123456', 1001), 'nothing kept');
}

// The queue drops a code it could not send before the code ends, and every
// send has a reference of its own the queue accepts.
function test_sms_codes_queue_options()
{
	$options = pg_sms_code_queue_options('cod', 'order:12', 1000, 'a1b2');
	pg_assert_same(1000 + pg_sms_code_lifetime(), $options['expires_at']);
	pg_assert_same('code:cod:order:12:1000:a1b2', $options['reference']);
	pg_assert_true((bool) preg_match('/^[A-Za-z0-9:._\/-]{1,64}$/D', $options['reference']), 'a reference the queue accepts');

	$other = pg_sms_code_queue_options('cod', 'order:12', 1000, 'c3d4');
	pg_assert_false($options['reference'] === $other['reference'], 'two sends in one second');

	$odd = pg_sms_code_queue_options('signin', 'user:5 x', 1000, 'é1');
	pg_assert_same('code:signin:user:5x:1000:1', $odd['reference'], 'characters the queue refuses are dropped');
}

// The cash-on-delivery code is asked for an offline payment only, never for
// an order put on the member's account, and only while the event is on.
function test_sms_codes_cod_applies()
{
	pg_assert_true(pg_sms_cod_applies('Offline Payment', false, true));
	pg_assert_false(pg_sms_cod_applies('Offline Payment', true, true), 'on account');
	pg_assert_false(pg_sms_cod_applies('Offline Payment', false, false), 'event off');
	pg_assert_false(pg_sms_cod_applies('Credit/Debit Card', false, true), 'card');
	pg_assert_false(pg_sms_cod_applies('Pay With Iyzico', false, true), 'redirect');
	pg_assert_same('cod_code', pg_sms_code_event('cod'));
	pg_assert_same('', pg_sms_code_event('other'));
}

// A request without the session's token sends nothing.
function test_sms_codes_token()
{
	pg_assert_true(pg_sms_code_token_ok('abc123', 'abc123'));
	pg_assert_false(pg_sms_code_token_ok('abc123', 'abc124'), 'another token');
	pg_assert_false(pg_sms_code_token_ok('abc123', ''), 'no token');
	pg_assert_false(pg_sms_code_token_ok('', ''), 'no session token');
	pg_assert_false(pg_sms_code_token_ok('abc123', array('abc123')), 'an array');
	pg_assert_false(pg_sms_code_token_ok(null, null), 'no session');
}

// The session wrapper: the order's stored number decides, a missing or
// wrong code is refused, and the right one is used once.
function test_sms_codes_verify_in_session()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('software' => array());

	pg_assert_same('number', pg_sms_code_verify('cod', 'order:7', '0212 555 12 12', '123456'), 'landline');
	pg_assert_same('code_missing', pg_sms_code_verify('cod', 'order:7', '0532 123 45 67', ' '));
	pg_assert_same('code_expired', pg_sms_code_verify('cod', 'order:7', '0532 123 45 67', '123456'), 'none sent');

	pg_sms_code_keep($_SESSION['software']['sms_code']['cod'], 'cod', 'order:7', '905321234567', '123456', time());
	pg_assert_same('code_expired', pg_sms_code_verify('cod', 'order:7', '0533 123 45 67', '123456'), 'another number');
	pg_assert_same('code_wrong', pg_sms_code_verify('cod', 'order:7', '0532 123 45 67', '654321'));
	pg_assert_same('', pg_sms_code_verify('cod', 'order:7', '0532 123 45 67', '123456'));
	pg_assert_same('code_expired', pg_sms_code_verify('cod', 'order:7', '0532 123 45 67', '123456'), 'used');

	$_SESSION = $saved;
}

// Sends are limited per subject, number and address; the number shown keeps
// only its last two digits.
function test_sms_codes_limits_and_mask()
{
	$limits = pg_sms_code_limits();
	pg_assert_same(array(3, 600), $limits['subject']);
	pg_assert_true($limits['number'][0] > 0 && $limits['address'][0] >= $limits['number'][0]);
	pg_assert_same('•••• 67', pg_sms_code_masked('905321234567'));
	pg_assert_same('', pg_sms_code_masked('12'));
}

// The sign-in code is offered to a member with a second factor and a mobile
// number, while the event is on; never to staff, never as a factor of its own.
function test_sms_codes_signin_offered()
{
	pg_assert_true(pg_sms_signin_offered(3, true, true, '905321234567'));
	pg_assert_true(pg_sms_signin_offered('3', true, true, '905321234567'), 'role as read from the database');
	foreach (array(0, 1, 2) as $role) {
		pg_assert_false(pg_sms_signin_offered($role, true, true, '905321234567'), 'staff role ' . $role);
	}
	pg_assert_false(pg_sms_signin_offered(3, false, true, '905321234567'), 'no factor');
	pg_assert_false(pg_sms_signin_offered(3, true, false, '905321234567'), 'event off');
	pg_assert_false(pg_sms_signin_offered(3, true, true, ''), 'no mobile number');
	pg_assert_same('signin_code', pg_sms_code_event('signin'));
}

// The screen shows the code field only while a code for this account was
// sent in the session and still works; a sign-in code never checks an order.
function test_sms_codes_signin_sent()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('software' => array());

	pg_assert_false(pg_sms_signin_sent(5), 'none');
	pg_sms_code_keep($_SESSION['software']['sms_code']['signin'], 'signin', 'user:5', '905321234567', '123456', time());
	pg_assert_true(pg_sms_signin_sent(5));
	pg_assert_false(pg_sms_signin_sent(6), 'another account');
	pg_assert_same('code_expired', pg_sms_code_verify('cod', 'user:5', '0532 123 45 67', '123456'), 'another purpose');
	pg_assert_same('', pg_sms_code_verify('signin', 'user:5', '0532 123 45 67', '123456'));
	pg_assert_false(pg_sms_signin_sent(5), 'used');

	pg_sms_code_keep($_SESSION['software']['sms_code']['signin'], 'signin', 'user:5', '905321234567', '123456', time() - pg_sms_code_lifetime() - 1);
	pg_assert_false(pg_sms_signin_sent(5), 'too old');

	$_SESSION = $saved;
}
