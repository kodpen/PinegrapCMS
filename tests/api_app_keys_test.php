<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how an application row is made (includes/api/keys.php), that
 * need no database: which kind a posted value becomes (api_app_kind()), which
 * rows are given a secret (api_app_issues_secret()), and that Application
 * Access makes and rotates keys through the same helpers as the Workspace
 * cards, rotating with a day of grace.
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

// Only 'device' and 'oauth' are taken as posted; anything else is 'server'.
function test_api_app_keys_kind()
{
	pg_assert_same('device', api_app_kind('device'), 'device');
	pg_assert_same('oauth', api_app_kind('oauth'), 'oauth');
	pg_assert_same('server', api_app_kind('server'), 'server');
	pg_assert_same('server', api_app_kind(''), 'nothing posted');
	pg_assert_same('server', api_app_kind('DEVICE'), 'another case');
	pg_assert_same('server', api_app_kind(array('device')), 'a list');
	pg_assert_same('server', api_app_kind(null), 'null');
}

// A secret is made only for a 'server' row asked to have one: a team devices
// or OAuth application never gets one, whatever the caller asked.
function test_api_app_keys_issues_secret()
{
	pg_assert_true(api_app_issues_secret('server', true), 'server with a secret');
	pg_assert_false(api_app_issues_secret('server', false), 'server without (Pinegrap AI)');
	pg_assert_false(api_app_issues_secret('device', true), 'device');
	pg_assert_false(api_app_issues_secret('oauth', true), 'oauth');
}

// Application Access writes no api_apps row of its own: create and rotate go
// through the helpers, rotation keeps the old secret for 24 hours, and the
// posted kind decides whether a secret is made.
function test_api_app_keys_application_access_uses_the_helpers()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/api_settings.php');

	pg_assert_false(strpos($screen, 'INSERT INTO api_apps') !== false, 'no INSERT of its own');
	pg_assert_false(strpos($screen, 'api_secret_hash = ') !== false, 'no secret UPDATE of its own');
	pg_assert_contains('api_app_create(', $screen, 'creates through the helper');
	pg_assert_contains("api_app_kind(isset(\$_POST['kind'])", $screen, 'reads the kind through api_app_kind()');
	pg_assert_contains("api_app_rotate_secret(\$app_id, 86400,", $screen, 'rotates with a day of grace');
}
