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
	$areas = array_keys(pg_reseal_empty_counts($registry));

	pg_assert_same(array('cards', 'gateway'), array_slice($areas, 0, 2));

	// Every area the card warns about before a reset is one the re-seal covers.
	foreach (array_keys(pg_encryption_key_reset_counts(array('connect_accounts' => 1, 'marketplace_accounts' => 1, 'edoc_providers' => 1, 'mfa_keys' => 1, 'config' => array('parasut_credentials_enc' => 'x', 'backup_remote_settings' => 'x', 'oauth_google_client_secret' => 'x', 'product_feeds' => '{"formats":{"g":{"token_secret":"x"}}}')))) as $area) {
		pg_assert_true(in_array($area, $areas, true), $area);
	}
}

// The registry entry of a table and column, or null.
function reseal_test_entry($table, $column)
{
	foreach (pg_reseal_registry() as $entry) {
		if (($entry['table'] === $table) && ($entry['column'] === $column)) {
			return $entry;
		}
	}

	return null;
}

// Every module that seals with the site's key has its entry, and every table
// and column an entry names is one a migration step creates.
function test_reseal_registry_module_entries()
{
	$expected = array(
		'connect_apps.secret', 'connect_accounts.secret', 'marketplace_accounts.credentials',
		'erp_edoc_providers.credentials_enc', 'config.parasut_credentials_enc', 'config.backup_remote_settings',
		'config.product_feeds', 'config.oauth_google_client_secret', 'user_mfa.totp_secret', 'user_mfa.pending_secret',
		'subscriptions.card_ref', 'config.translation_google_key', 'ws_mcp_servers.secret', 'ws_calendar_feeds.token_secret',
		'ws_mail_boxes.settings', 'config.ws_claude_token', 'config_text.value', 'ws_agents.key_sealed',
		'ws_agents.door_secret_sealed',
	);
	$found = array();

	foreach (pg_reseal_registry() as $entry) {
		$found[] = $entry['table'] . '.' . $entry['column'];
	}

	foreach ($expected as $name) {
		pg_assert_true(in_array($name, $found, true), $name);
	}

	$migrations = '';

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/migrations/*.php') as $file) {
		$migrations .= file_get_contents($file);
	}

	foreach (pg_reseal_registry() as $entry) {
		if ($entry['table'] === 'orders') {
			continue;
		}

		$columns = array($entry['column']);

		if (!empty($entry['iv_column'])) {
			$columns[] = $entry['iv_column'];
		}

		foreach ($columns as $column) {
			$created = (bool) preg_match('/(install_create_table\(\'' . preg_quote($entry['table'], '/') . '\'[^;]*\n\s*' . preg_quote($column, '/') . '\s|install_add_column\(\'' . preg_quote($entry['table'], '/') . '\', \'' . preg_quote($column, '/') . '\')/s', $migrations);
			$config = ($entry['table'] === 'config') || ($entry['table'] === 'config_text');

			pg_assert_true($created || $config, $entry['table'] . '.' . $column);
		}
	}
}

// Product feed tokens sit inside the JSON: the one sealed with the old key is
// re-sealed, one under another key is skipped, an empty one is not counted;
// the column keeps its unescaped slashes.
function test_reseal_product_feed_tokens()
{
	$entry = reseal_test_entry('config', 'product_feeds');
	list($c1, $i1) = encrypt_string_with_iv('tok-google', 'old-key');
	// AES-CBC has no MAC: about one IV in 256 lets a value sealed with another
	// key open with the old one as garbage (a valid padding byte), and a
	// token is not checked after opening. Draw until it does not open, so the
	// skipped count does not depend on the random IV.
	do {
		list($c2, $i2) = encrypt_string_with_iv('tok-meta', 'other-key');
	} while (pg_ssl_keys_open($c2, $i2, 'old-key') !== null);
	$stored = json_encode(array('formats' => array(
		'google' => array('token_hash' => 'h1', 'token_secret' => $c1 . ':' . $i1, 'url' => 'a/b'),
		'meta'   => array('token_hash' => 'h2', 'token_secret' => $c2 . ':' . $i2),
		'csv'    => array('token_hash' => '', 'token_secret' => ''),
	)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	$result = pg_reseal_value($entry, $stored, '', 'old-key', 'new-key');
	$data = json_decode($result['value'], true);
	$google = explode(':', $data['formats']['google']['token_secret'], 2);

	pg_assert_same('done', $result['status']);
	pg_assert_same(array('done' => 1, 'skipped' => 1, 'failed' => 0), $result['counts']);
	pg_assert_same('tok-google', decode_ssl_keys($google[0], $google[1], 'new-key'));
	pg_assert_same($c2 . ':' . $i2, $data['formats']['meta']['token_secret']);
	pg_assert_same('h1', $data['formats']['google']['token_hash']);
	pg_assert_contains('"a/b"', $result['value']);
}

// A JSON column with nothing that opens is skipped and keeps its text; one
// that is not JSON too.
function test_reseal_json_fields_nothing_opens()
{
	$entry = reseal_test_entry('ws_mail_boxes', 'settings');
	$stored = json_encode(array('host' => 'imap.example.com', 'secret' => pg_connect_seal(array('password' => 'p'), 'other-key')));

	foreach (array($stored, 'not json') as $value) {
		$result = pg_reseal_value($entry, $value, '', 'old-key', 'new-key');

		pg_assert_same('skipped', $result['status']);
		pg_assert_same($value, $result['value']);
	}
}

// The mailbox password and the ActivityPub private key are pg_connect_seal()
// values inside JSON.
function test_reseal_connect_fields()
{
	$mailbox = json_encode(array('host' => 'imap.example.com', 'secret' => pg_connect_seal(array('password' => 'p'), 'old-key')));
	$result = pg_reseal_value(reseal_test_entry('ws_mail_boxes', 'settings'), $mailbox, '', 'old-key', 'new-key');
	$data = json_decode($result['value'], true);

	pg_assert_same('done', $result['status']);
	pg_assert_same('imap.example.com', $data['host']);
	pg_assert_same(array('password' => 'p'), pg_connect_unseal($data['secret'], 'new-key'));

	$ap_entry = null;

	foreach (pg_reseal_registry() as $entry) {
		if (($entry['table'] === 'config_text') && (strpos($entry['where'], "'ap_keys'") !== false)) {
			$ap_entry = $entry;
		}
	}

	pg_assert_true(is_array($ap_entry), 'ap_keys entry');

	$keys = json_encode(array('public' => 'PUBLIC', 'private' => pg_connect_seal(array('pem' => 'PRIVATE'), 'old-key')));
	$result = pg_reseal_value($ap_entry, $keys, '', 'old-key', 'new-key');
	$data = json_decode($result['value'], true);

	pg_assert_same('PUBLIC', $data['public']);
	pg_assert_same(array('pem' => 'PRIVATE'), pg_connect_unseal($data['private'], 'new-key'));
}

// A re-sealed card reference keeps the shape the subscriptions module asks
// for, and its WHERE picks only that shape.
function test_reseal_subscription_card_ref()
{
	$entry = reseal_test_entry('subscriptions', 'card_ref');
	list($cipher, $iv) = encrypt_string_with_iv('cus_123/card_456', 'old-key');

	$result = pg_reseal_value($entry, $cipher . ':' . $iv, '', 'old-key', 'new-key');

	pg_assert_same('done', $result['status']);
	pg_assert_true(pg_subscription_card_ref_is_sealed($result['value']));
	pg_assert_contains("LIKE '%:%=='", $entry['where']);
	pg_assert_same('skipped', pg_reseal_value($entry, 'cus_plain_token', '', 'old-key', 'new-key')['status']);
}

// A two-step key (base32 text) and the Google client secret, whose IV has its
// own column.
function test_reseal_mfa_and_google_entries()
{
	$mfa = reseal_test_entry('user_mfa', 'totp_secret');
	list($cipher, $iv) = encrypt_string_with_iv('JBSWY3DPEHPK3PXP', 'old-key');
	$result = pg_reseal_value($mfa, $cipher . ':' . $iv, '', 'old-key', 'new-key');
	$parts = explode(':', $result['value'], 2);

	pg_assert_same('user_id', $mfa['key']);
	pg_assert_same('JBSWY3DPEHPK3PXP', decode_ssl_keys($parts[0], $parts[1], 'new-key'));

	$google = reseal_test_entry('config', 'oauth_google_client_secret');
	list($cipher, $iv) = encrypt_string_with_iv('GOCSPX-abc', 'old-key');
	$result = pg_reseal_value($google, $cipher, $iv, 'old-key', 'new-key');

	pg_assert_same('oauth_google_secret_iv', $google['iv_column']);
	pg_assert_same('GOCSPX-abc', decode_ssl_keys($result['value'], $result['iv'], 'new-key'));
}

// The key reset runs last in the Accounts and sign-in save, after every write
// that seals a value with the constant (the old key in that request).
function test_reseal_runs_after_the_sealing_writes()
{
	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/security.save.php');
	$reset = strpos($save, 'pg_reseal_run(');

	pg_assert_true($reset !== false);

	foreach (array('pg_captcha_settings_save(', 'pg_connect_app_save(', 'encrypt_string_with_iv(', 'generate_encryption_key()') as $call) {
		pg_assert_true(strpos($save, $call) !== false, $call);
	}

	foreach (array('pg_captcha_settings_save(', 'pg_connect_app_save(', 'encrypt_string_with_iv(') as $call) {
		pg_assert_true(strpos($save, $call) < strpos($save, 'generate_encryption_key()'), $call . ' runs before the key changes');
	}
}
