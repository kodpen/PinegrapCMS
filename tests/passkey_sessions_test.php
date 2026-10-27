<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sessions a passkey opens: the mark a passkey sign-in leaves
 * in the session until its remember-me token is minted
 * (pg_passkey_signin_mark(), pg_passkey_signin_marked()), and the 2026.11.0
 * step that gives auth_tokens its passkey_id column
 * (upgrade_2026_11_0_session_passkey(), 11.0.46), checked in its source: the
 * step itself needs a database and is run twice in a sandbox instead.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The mark is for one account, taken once, and only shortly after the
// passkey was checked: a sign-in left at the device limit screen and given
// up must not name the passkey on a later password sign-in.
function test_passkey_sessions_mark()
{
	$store = null;
	pg_assert_same(0, pg_passkey_signin_marked($store, 7, 1000), 'nothing marked');

	pg_passkey_signin_mark($store, 7, 42, 1000);
	pg_assert_same(0, pg_passkey_signin_marked($store, 8, 1001), 'another account');
	pg_assert_same(42, pg_passkey_signin_marked($store, 7, 1001), 'its account');
	pg_assert_same(0, pg_passkey_signin_marked($store, 7, 1002), 'taken once');

	pg_passkey_signin_mark($store, 7, 42, 1000);
	pg_assert_same(0, pg_passkey_signin_marked($store, 7, 1000 + 601), 'too old');
	pg_assert_same(null, $store, 'an old mark is dropped');

	pg_passkey_signin_mark($store, 7, 0, 1000);
	pg_assert_same(0, pg_passkey_signin_marked($store, 7, 1001), 'no passkey');

	$store = 'broken';
	pg_assert_same(0, pg_passkey_signin_marked($store, 7, 1001), 'a broken store');
}

// The step adds the column and its index through the idempotent helpers,
// under its own label, and writes no ALTER of its own.
function test_passkey_sessions_schema_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains("upgrade_2026_11_0_session_passkey();", $source, 'called');
	pg_assert_true((bool) preg_match('/upgrade_2026_11_0_session_passkey\(\);\s*\/\/ 11\.0\.46/', $source), 'labelled 11.0.46');

	$step = preg_match('/^function upgrade_2026_11_0_session_passkey\(\) \{\n(.*?)\n\}\n/ms', $source, $match) ? $match[1] : '';

	pg_assert_contains("install_add_column('auth_tokens', 'passkey_id', \"INT UNSIGNED NOT NULL DEFAULT 0\")", $step, 'column');
	pg_assert_contains("install_add_index('auth_tokens', 'idx_passkey', \"INDEX idx_passkey (passkey_id)\")", $step, 'index');
	pg_assert_false(stripos($step, 'ALTER TABLE') !== false, 'no ALTER of its own');
	pg_assert_contains('install_note(', $step, 'a note for the operator');
}
