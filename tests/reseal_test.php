<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the encryption key re-seal (includes/fn/reseal.php) and the key
 * parameters of the sealing helpers: each kind opens with the old key and is
 * sealed with the new one; a value that does not open with the old key is
 * skipped and keeps its stored text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// encrypt_string_with_iv() and decode_ssl_keys() take an explicit key; a
// wrong key or a missing one opens to '' (pg_ssl_keys_open() to null).
function test_reseal_ssl_keys_explicit_key_round_trip()
{
	list($cipher, $iv) = encrypt_string_with_iv('s3cret', 'key-a');

	pg_assert_same('s3cret', decode_ssl_keys($cipher, $iv, 'key-a'));
	pg_assert_same('s3cret', pg_ssl_keys_open($cipher, $iv, 'key-a'));
	pg_assert_same(null, pg_ssl_keys_open($cipher, $iv, ''));
	pg_assert_same(null, pg_ssl_keys_open($cipher, 'short', 'key-a'));
	pg_assert_same('', decode_ssl_keys($cipher, $iv, ''));

	list($empty_cipher, $empty_iv) = encrypt_string_with_iv('', 'key-a');

	pg_assert_same('', pg_ssl_keys_open($empty_cipher, $empty_iv, 'key-a'));
}

// pg_connect_seal() and pg_connect_unseal() take an explicit key.
function test_reseal_connect_explicit_key_round_trip()
{
	$sealed = pg_connect_seal(array('token' => 'abc', 'n' => 1), 'key-a');

	pg_assert_true($sealed !== '');
	pg_assert_same(array('token' => 'abc', 'n' => 1), pg_connect_unseal($sealed, 'key-a'));
	pg_assert_same(array(), pg_connect_unseal($sealed, 'key-b'));
	pg_assert_same('', pg_connect_seal(array('token' => 'abc'), ''));
	pg_assert_false(pg_connect_can_seal(''));
	pg_assert_true(pg_connect_can_seal('key-a'));
}

// An "<ciphertext>:<iv>" value is opened with the old key and sealed with the
// new one in the same shape.
function test_reseal_iv_round_trip()
{
	$entry = array('area' => 't', 'kind' => 'iv');
	list($cipher, $iv) = encrypt_string_with_iv('token-1', 'old-key');

	$result = pg_reseal_value($entry, $cipher . ':' . $iv, '', 'old-key', 'new-key');
	$parts = explode(':', $result['value'], 2);

	pg_assert_same('done', $result['status']);
	pg_assert_same('token-1', decode_ssl_keys($parts[0], $parts[1], 'new-key'));
	pg_assert_false(pg_ssl_keys_open($parts[0], $parts[1], 'old-key') === 'token-1');
}

// An IV kept in its own column comes back in its own field.
function test_reseal_iv_own_column_round_trip()
{
	$entry = array('area' => 't', 'kind' => 'iv', 'iv_column' => 'secret_iv');
	list($cipher, $iv) = encrypt_string_with_iv('client-secret', 'old-key');

	$result = pg_reseal_value($entry, $cipher, $iv, 'old-key', 'new-key');

	pg_assert_same('done', $result['status']);
	pg_assert_true(strpos($result['value'], ':') === false);
	pg_assert_same('client-secret', decode_ssl_keys($result['value'], $result['iv'], 'new-key'));
}

// A JSON entry treats a value that does not decode as not sealed with the old
// key: the garbage a wrong CBC key can give is never taken for a value.
function test_reseal_iv_json_checked()
{
	$entry = array('area' => 't', 'kind' => 'iv', 'json' => true);
	list($cipher, $iv) = encrypt_string_with_iv('not json', 'old-key');

	pg_assert_same('skipped', pg_reseal_value($entry, $cipher . ':' . $iv, '', 'old-key', 'new-key')['status']);

	list($cipher, $iv) = encrypt_string_with_iv('{"user":"u"}', 'old-key');
	$result = pg_reseal_value($entry, $cipher . ':' . $iv, '', 'old-key', 'new-key');
	$parts = explode(':', $result['value'], 2);

	pg_assert_same('done', $result['status']);
	pg_assert_same('{"user":"u"}', decode_ssl_keys($parts[0], $parts[1], 'new-key'));
}

function test_reseal_connect_round_trip()
{
	$entry = array('area' => 't', 'kind' => 'connect');
	$result = pg_reseal_value($entry, pg_connect_seal(array('access_token' => 'x'), 'old-key'), '', 'old-key', 'new-key');

	pg_assert_same('done', $result['status']);
	pg_assert_same(array('access_token' => 'x'), pg_connect_unseal($result['value'], 'new-key'));
}

function test_reseal_gateway_round_trip()
{
	$entry = array('area' => 'gateway', 'kind' => 'gateway', 'width' => 100);
	$result = pg_reseal_value($entry, pg_gateway_secret_seal('Tk-1', 'old-key'), '', 'old-key', 'new-key');

	pg_assert_same('done', $result['status']);
	pg_assert_same('Tk-1', pg_gateway_secret_open($result['value'], 'new-key'));

	// A plain value from before sealing is sealed for the first time.
	$plain = pg_reseal_value($entry, 'Tk-2', '', 'old-key', 'new-key');

	pg_assert_same('done', $plain['status']);
	pg_assert_same('Tk-2', pg_gateway_secret_open($plain['value'], 'new-key'));
}

function test_reseal_card_round_trip()
{
	$entry = array('area' => 'cards', 'kind' => 'card');
	$result = pg_reseal_value($entry, encrypt_credit_card_number('4111111111111111', 'old-key'), '', 'old-key', 'new-key');

	pg_assert_same('done', $result['status']);
	pg_assert_same('4111111111111111', decrypt_credit_card_number($result['value'], 'new-key'));

	// A plain number from before encryption is sealed for the first time.
	$plain = pg_reseal_value($entry, '4111111111111111', '', 'old-key', 'new-key');

	pg_assert_same('done', $plain['status']);
	pg_assert_same('4111111111111111', decrypt_credit_card_number($plain['value'], 'new-key'));
}

// A value sealed with another key is skipped and keeps its stored text,
// whatever its kind; an empty value is skipped too.
function test_reseal_value_that_does_not_open_is_skipped()
{
	list($cipher, $iv) = encrypt_string_with_iv('{"a":1}', 'other-key');

	$cases = array(
		array(array('kind' => 'iv', 'json' => true), $cipher . ':' . $iv, ''),
		array(array('kind' => 'iv', 'iv_column' => 'iv', 'json' => true), $cipher, $iv),
		array(array('kind' => 'iv'), 'no-colon-here', ''),
		array(array('kind' => 'connect'), pg_connect_seal(array('a' => 1), 'other-key'), ''),
		array(array('kind' => 'gateway', 'width' => 100), pg_gateway_secret_seal('Tk-1', 'other-key'), ''),
		array(array('kind' => 'card'), encrypt_credit_card_number('4111111111111111', 'other-key'), ''),
		array(array('kind' => 'iv'), '', ''),
	);

	foreach ($cases as $case) {
		$result = pg_reseal_value($case[0] + array('area' => 't'), $case[1], $case[2], 'old-key', 'new-key');

		pg_assert_same('skipped', $result['status']);
		pg_assert_same($case[1], $result['value']);
		pg_assert_same($case[2], $result['iv']);
	}
}

// The registry starts with the card numbers and the gateway secrets; every
// entry names what pg_reseal_run() needs.
function test_reseal_registry_shape()
{
	$registry = pg_reseal_registry();

	pg_assert_same('orders', $registry[0]['table']);
	pg_assert_same('card', $registry[0]['kind']);

	$gateway = array();

	foreach ($registry as $entry) {
		foreach (array('area', 'table', 'column', 'key', 'kind', 'where') as $field) {
			pg_assert_true(array_key_exists($field, $entry), $field);
		}

		pg_assert_true(in_array($entry['kind'], array('card', 'gateway', 'iv', 'connect'), true));

		if ($entry['kind'] === 'gateway') {
			$gateway[$entry['column']] = $entry['width'];
		}
	}

	pg_assert_same(pg_gateway_secret_fields(), $gateway);
	pg_assert_same(array('cards', 'gateway'), array_keys(pg_reseal_empty_counts($registry)));
}
