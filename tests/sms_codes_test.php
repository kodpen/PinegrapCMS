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
