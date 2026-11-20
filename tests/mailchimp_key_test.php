<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Mailchimp API key on the Communication settings card
 * (includes/fn/mailchimp_key.php): the data center read from the key is a
 * plain host label, so the key is never sent to another host, and an empty
 * box keeps the stored key. No database and no Mailchimp.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!function_exists('lang')) {
	function lang($text) { return is_array($text) ? $text['string'] : $text; }
}

// A key-shaped test value, built at run time so no literal looks like a key.
function mailchimp_key_test_value($dc, $fill = 'a')
{
	return str_repeat($fill, 32) . '-' . $dc;
}

// The data center is the label after the last dash of a real key.
function test_mailchimp_dc_from_key()
{
	pg_assert_same('us6', pg_mailchimp_dc(mailchimp_key_test_value('us6')));
	pg_assert_same('us21', pg_mailchimp_dc(' ' . mailchimp_key_test_value('us21') . ' '));
}

// Anything that would put another host into the request address gives ''.
function test_mailchimp_dc_refuses_other_hosts()
{
	foreach (array(
		'',
		str_repeat('a', 32),
		'abc-evil.example/x?',
		'abc-evil.example#',
		'abc-us6.evil.example',
		'abc-user@evil.example',
		'abc-us6:8080',
		'abc-US6',
		"abc-us6\nevil",
		'abc-',
	) as $key) {
		pg_assert_same('', pg_mailchimp_dc($key), 'refused: ' . json_encode($key));
	}
}

// An empty box keeps the stored key; a typed key replaces it; the remove
// tick clears it.
function test_mailchimp_key_for_save()
{
	$stored = mailchimp_key_test_value('us6');
	$new = mailchimp_key_test_value('us21', 'b');

	pg_assert_same($stored, pg_mailchimp_key_for_save('', $stored, false));
	pg_assert_same($stored, pg_mailchimp_key_for_save('   ', $stored, false));
	pg_assert_same($new, pg_mailchimp_key_for_save(' ' . $new . ' ', $stored, false));
	pg_assert_same('', pg_mailchimp_key_for_save('', $stored, true));
	pg_assert_same($new, pg_mailchimp_key_for_save($new, $stored, true), 'a typed key wins over the tick');
	pg_assert_same('', pg_mailchimp_key_for_save(array('x'), '', false), 'a list is no key');
}

// A refused request's log line and mail list the request without the key:
// the key passed by the settings save is replaced by its data center only.
function test_mailchimp_request_for_log_leaves_the_key_out()
{
	$key = mailchimp_key_test_value('us6', 'c');
	$request = array('method' => 'get', 'path' => '/lists/abc123', 'data' => array(), 'key' => $key);

	$logged = pg_mailchimp_request_for_log($request);
	$printed = print_r($logged, true);

	pg_assert_same('/lists/abc123', $logged['path'], 'the refused path is still named');
	pg_assert_same('****-us6', $logged['key'], 'only the data center is shown');
	pg_assert_true(strpos($printed, $key) === false, 'the key is not printed');
	pg_assert_true(strpos($printed, str_repeat('c', 8)) === false, 'no part of the key is printed');

	$plain = array('method' => 'get', 'path' => '/lists/abc123', 'data' => array());
	pg_assert_same($plain, pg_mailchimp_request_for_log($plain), 'a request without a key is unchanged');
}

// The key is masked wherever it could appear in the message: the response
// body echoing it, a URL carrying it encoded, the Basic auth header value.
function test_mailchimp_mask_key_everywhere()
{
	$key = mailchimp_key_test_value('us6', 'd') . '+/';
	$text = "Response: Array ( [detail] => key " . $key . " is wrong )\n"
		. 'URL: https://x.example/?apikey=' . rawurlencode($key) . "\n"
		. 'Authorization: Basic ' . base64_encode('key:' . $key) . "\n"
		. 'Token: ' . base64_encode($key);

	$masked = pg_mailchimp_mask_key($text, $key);

	pg_assert_true(strpos($masked, $key) === false, 'the plain key is masked');
	pg_assert_true(strpos($masked, rawurlencode($key)) === false, 'the encoded key is masked');
	pg_assert_true(strpos($masked, base64_encode('key:' . $key)) === false, 'the auth header value is masked');
	pg_assert_true(strpos($masked, base64_encode($key)) === false, 'the base64 key is masked');
	pg_assert_true(strpos($masked, 'is wrong') !== false, 'the rest of the message stays');

	pg_assert_same('no key here', pg_mailchimp_mask_key('no key here', ''), 'an empty key masks nothing');
}

// The message mailchimp_request() logs and mails for a refused request names
// what Mailchimp refused and never carries the key.
function test_mailchimp_error_message_has_no_key()
{
	$key = mailchimp_key_test_value('us6', 'e');
	$request = array('method' => 'get', 'path' => '/lists/nolist', 'data' => array(), 'key' => $key);
	$response = array('title' => 'Resource Not Found', 'detail' => 'The requested resource could not be found.', 'echo' => $key);

	$message = pg_mailchimp_error_message('MailChimp Error: Resource Not Found.', $response, $request, $key);

	pg_assert_true(strpos($message, 'Resource Not Found') !== false, 'names what Mailchimp refused');
	pg_assert_true(strpos($message, '/lists/nolist') !== false, 'names the refused path');
	pg_assert_true(strpos($message, $key) === false, 'the key is not in the message');
	pg_assert_true(strpos($message, str_repeat('e', 8)) === false, 'no part of the key is in the message');

	$timeout = pg_mailchimp_error_message('MailChimp Error', null, $request, $key);
	pg_assert_true(strpos($timeout, $key) === false, 'a cURL failure message has no key either');
	pg_assert_true(strpos($timeout, '/lists/nolist') !== false);
}
