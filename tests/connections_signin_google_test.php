<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sign in with Google application on Google's page of
 * Connections: the client is typed there, under the gate of the Accounts
 * and sign-in settings it came from; the sign-in card keeps the switch and
 * a row that leads to the page; the secret is written as before; the
 * connected accounts still fall back to it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/signin.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function connections_signin_google_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_signin_google_secret_is_written_as_before()
{
	pg_assert_same('keep', pg_signin_google_secret_write('abc', 'abc', false), 'an unchanged secret is not sealed again');
	pg_assert_same('keep', pg_signin_google_secret_write('', '', true), 'nothing typed, nothing stored');
	pg_assert_same('seal', pg_signin_google_secret_write('new', 'abc', false), 'another value is sealed');
	pg_assert_same('seal', pg_signin_google_secret_write('new', '', true), 'a first value is sealed');
	pg_assert_same('clear', pg_signin_google_secret_write('', 'abc', false), 'an emptied field clears it');
	pg_assert_same('clear', pg_signin_google_secret_write('', '', false), 'a stored value that no longer decrypts is cleared');
}

function test_connections_signin_google_card_keeps_the_sign_in_gate()
{
	pg_assert_same(array('status', 'signin', 'details', 'uses'), pg_connections_screen_page_cards('google', array('manager' => true, 'signin' => true)), 'the sign-in application above the connected accounts\' one');
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('google', array('manager' => true, 'signin' => false)), 'not without the sign-in settings\' right');
	pg_assert_same(array('status', 'signin', 'uses'), pg_connections_screen_page_cards('google', array('manager' => false, 'signin' => true)), 'each part keeps its own gate');
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('microsoft', array('manager' => true, 'signin' => true)), 'only Google\'s page has it');

	// Accounts and sign-in is a manager's category, the gate of the card
	// the application came from; nobody gains or loses a right.
	$security = array('group' => 'security');
	pg_assert_true(pg_settings_category_open($security, array('manager' => true, 'workspace' => false, 'mail' => false)), 'a manager');
	pg_assert_false(pg_settings_category_open($security, array('manager' => false, 'workspace' => true, 'mail' => true)), 'not the Workspace or mailbox right alone');

	$screen = connections_signin_google_test_source('connections.php');
	pg_assert_contains("return pg_settings_may_open('security', \$user);", $screen, 'the gate is the category\'s');
	$save = strpos($screen, "if (\$action === 'signin_google_save') {");
	pg_assert_true($save !== false, 'the save action is there');
	pg_assert_contains('if (connections_signin_may_manage($user)) {', substr($screen, $save, 200), 'the save asks the gate first');
	pg_assert_contains("'signin'  => (\$provider_page === 'google') && connections_signin_may_manage(\$user),", $screen, 'and so does the drawing');
}

function test_connections_signin_google_fields_moved_to_the_page()
{
	$screen = connections_signin_google_test_source('connections.php');
	pg_assert_contains('name="oauth_google_client_id"', $screen, 'the client ID is typed on Google\'s page');
	pg_assert_contains('name="oauth_google_client_secret"', $screen, 'and the secret');
	pg_assert_contains('pg_signin_google_app_save($signin_client_id, $signin_client_secret)', $screen, 'saved into the same config columns');

	$card = connections_signin_google_test_source('includes/settings/security.php');
	pg_assert_false(strpos($card, 'name="oauth_google_client_id"') !== false, 'the sign-in card no longer types the client');
	pg_assert_false(strpos($card, 'name="oauth_google_client_secret"') !== false, 'nor the secret');
	pg_assert_contains('name="oauth_google_enabled"', $card, 'the switch stays');
	pg_assert_contains('href="connections.php?service=google#connections-signin"', $card, 'a row leads to Google\'s page');

	$save = connections_signin_google_test_source('includes/settings/security.save.php');
	pg_assert_false(strpos($save, 'oauth_google_client_id') !== false, 'saving the sign-in settings leaves the client ID alone');
	pg_assert_false(strpos($save, 'oauth_google_client_secret') !== false, 'and the secret');
	pg_assert_contains("oauth_google_enabled = '", $save, 'the switch is still saved there');

	pg_assert_same(array('security', 'pgset-signin'), pg_settings_legacy_anchors()['oauth_google_row'] ?? null, 'an old link to the fields lands on the card\'s row');
	pg_assert_same('#settings/security/pgset-signin', pg_connections_registry()['signin_google']['place'] ?? null, 'the switch keeps the service\'s place');
	pg_assert_same(78, pg_connections_count(), 'the figure stays');
}

function test_connections_signin_google_fallback_reads_in_one_place()
{
	$connect = connections_signin_google_test_source('includes/fn/connect.php');
	pg_assert_contains("return (\$provider_key === 'google') ? pg_signin_google_app() : null;", $connect, 'a missing connect_apps row still falls back to the sign-in application');

	$screen = connections_signin_google_test_source('connections.php');
	pg_assert_contains("lang('Use the Sign in with Google application')", $screen, 'the action that goes back to it');
	pg_assert_contains("lang('Google features use the Sign in with Google application now, as no application of their own is saved.", $screen, 'the fallback is said where both are shown');
	pg_assert_true(strpos($screen, '$signin_part . \'') < strpos($screen, "lang('Connected accounts application')"), 'the sign-in application comes first');
	pg_assert_contains('pg_signin_redirect_uri($provider), pg_connect_redirect_uri()', connections_signin_google_test_source('includes/fn/google_cloud.php'), 'Google\'s page still lists sign-in\'s own return address');
}
