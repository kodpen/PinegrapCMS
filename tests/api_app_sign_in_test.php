<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for whether an application row can sign in (includes/api/keys.php),
 * that need no database: a stored secret hash takes only an equal, non-empty
 * supplied hash (api_secret_hash_accepts(), which api_secret_matches() asks
 * for the current and the previous secret), and a 'server' key made without
 * a secret, as Pinegrap AI's key is, has no way in at all
 * (api_app_can_sign_in()) and is never given one (api_app_secret_can_rotate(),
 * asked by both places that issue a new secret).
 *
 * The runner defines no ENCRYPTION_KEY, so api_secret_hash() is not called
 * here: the hashes below are any 64 hex characters.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/keys.php');

// The row api_app_create() writes for Pinegrap AI: kind 'server', no secret,
// no previous secret.
function api_app_sign_in_test_keyless_row()
{
	return array(
		'kind'                    => 'server',
		'api_secret_hash'         => '',
		'api_secret_hint'         => '',
		'api_secret_prev_hash'    => '',
		'api_secret_prev_expires' => 0,
	);
}

// An equal, non-empty hash is taken; another one is not.
function test_api_app_sign_in_hash_accepts_equal_hash()
{
	$hash = str_repeat('ab', 32);

	pg_assert_true(api_secret_hash_accepts($hash, $hash), 'the same hash');
	pg_assert_false(api_secret_hash_accepts($hash, str_repeat('cd', 32)), 'another hash');
	pg_assert_false(api_secret_hash_accepts($hash, ''), 'an empty supplied hash');
}

// An empty stored hash takes nothing, not even an empty supplied one, which
// hash_equals() alone would call equal.
function test_api_app_sign_in_empty_stored_hash_takes_nothing()
{
	pg_assert_true(hash_equals('', ''), 'hash_equals() calls two empty strings equal');
	pg_assert_false(api_secret_hash_accepts('', ''), 'empty against empty');
	pg_assert_false(api_secret_hash_accepts('', str_repeat('ab', 32)), 'empty against a hash');
	pg_assert_false(api_secret_hash_accepts(null, null), 'a missing value against a missing value');
}

// The key made without a secret cannot sign in, now or later.
function test_api_app_sign_in_keyless_server_row_cannot()
{
	$row = api_app_sign_in_test_keyless_row();

	pg_assert_false(api_app_can_sign_in($row, 1700000000), 'kind server, no secret');
	pg_assert_false(api_app_can_sign_in($row, 0), 'at any time');

	// A cleared previous secret with a grace left in the future opens nothing.
	$row['api_secret_prev_expires'] = 1700000000 + 3600;
	pg_assert_false(api_app_can_sign_in($row, 1700000000), 'a grace without a previous secret');
}

// A server key with a secret, or with the previous one inside its grace, can.
function test_api_app_sign_in_server_row_with_secret_can()
{
	$row = api_app_sign_in_test_keyless_row();
	$row['api_secret_hash'] = str_repeat('ab', 32);

	pg_assert_true(api_app_can_sign_in($row, 1700000000), 'a stored secret');

	$row = api_app_sign_in_test_keyless_row();
	$row['api_secret_prev_hash'] = str_repeat('cd', 32);
	$row['api_secret_prev_expires'] = 1700000000 + 60;

	pg_assert_true(api_app_can_sign_in($row, 1700000000), 'the previous secret inside its grace');
	pg_assert_false(api_app_can_sign_in($row, 1700000000 + 60), 'and not once the grace is over');
}

// Team devices and OAuth applications sign people in without a secret.
function test_api_app_sign_in_device_and_oauth_rows_can()
{
	foreach (array('device', 'oauth') as $kind) {
		$row = api_app_sign_in_test_keyless_row();
		$row['kind'] = $kind;

		pg_assert_true(api_app_can_sign_in($row, 1700000000), $kind . ' signs people in');
	}

	// A row read without its kind is taken as the column's default, 'server'.
	$row = api_app_sign_in_test_keyless_row();
	unset($row['kind']);

	pg_assert_false(api_app_can_sign_in($row, 1700000000), 'no kind, no secret');
}

// A new secret is issued only for a 'server' key that already has one: a key
// made without a secret stays without one, and a team devices or OAuth
// application has none to replace.
function test_api_app_sign_in_keyless_row_is_never_given_a_secret()
{
	$row = api_app_sign_in_test_keyless_row();

	pg_assert_false(api_app_secret_can_rotate($row), 'kind server, no secret');

	$row['api_secret_hash'] = str_repeat('ab', 32);

	pg_assert_true(api_app_secret_can_rotate($row), 'a server key with a secret');

	foreach (array('device', 'oauth') as $kind) {
		$row = api_app_sign_in_test_keyless_row();
		$row['kind'] = $kind;

		pg_assert_false(api_app_secret_can_rotate($row), $kind . ' has no secret to replace');
	}

	// A row read without its kind is taken as the column's default, 'server'.
	$row = api_app_sign_in_test_keyless_row();
	unset($row['kind']);

	pg_assert_false(api_app_secret_can_rotate($row), 'no kind, no secret');

	$row['api_secret_hash'] = str_repeat('ab', 32);

	pg_assert_true(api_app_secret_can_rotate($row), 'no kind, a secret');
}

// Both places that issue a new secret, Application Access's "Issue a new
// secret" and Claude's "Reset secret", go through api_app_rotate_secret(),
// which asks the guard and keeps a row without a secret out of its UPDATE, so
// neither can turn Pinegrap AI's key into a way in carrying its owner's
// rights. Application Access asks the guard first as well, for its message.
function test_api_app_sign_in_both_rotations_refuse_a_keyless_row()
{
	$keys = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/keys.php');

	pg_assert_contains('api_app_secret_can_rotate($existing)', $keys, 'keys.php asks the guard');
	pg_assert_contains("AND api_secret_hash <> ''", $keys, 'keys.php keeps a keyless row out of the UPDATE');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/api_settings.php');

	pg_assert_contains('api_app_secret_can_rotate($existing)', $screen, 'api_settings.php asks the guard');
	pg_assert_contains('api_app_rotate_secret(', $screen, 'api_settings.php rotates through the helper');
}
