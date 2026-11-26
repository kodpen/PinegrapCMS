<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Google or Microsoft account service's page finishing its own
 * set-up: the provider's application asked on the service's page while it
 * is missing, the drives' one answer for connecting an account, and Sign in
 * with Microsoft's part and anchor on Microsoft's page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/backup_cloud.php');

function connections_page_finishes_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_connections_page_finishes_app_here_for_account_services()
{
	$registry = pg_connections_registry();
	$missing = array('google' => false, 'microsoft' => false, 'dropbox' => false, 'github' => false);

	foreach (array('google_drive' => 'google', 'onedrive' => 'microsoft', 'google_analytics' => 'google', 'merchant_center' => 'google',
		'gmail' => 'google', 'microsoft_mail' => 'microsoft', 'google_calendar' => 'google', 'outlook_calendar' => 'microsoft', 'onenote' => 'microsoft') as $id => $provider) {
		pg_assert_same($provider, pg_connections_screen_app_here($id, $registry[$id], $missing, true), $id . ': its application asked on its page');
	}

	pg_assert_same('', pg_connections_screen_app_here('google_drive', $registry['google_drive'], array('google' => true), true), 'a ready application is not asked again');
	pg_assert_same('', pg_connections_screen_app_here('google_drive', $registry['google_drive'], $missing, false), 'only for a manager');
	pg_assert_same('google', pg_connections_screen_app_here('google_drive', $registry['google_drive'], array(), true), 'an application never saved is missing');
	pg_assert_same('', pg_connections_screen_app_here('dropbox', $registry['dropbox'], $missing, true), 'Dropbox has no page of its own to share');
	pg_assert_same('', pg_connections_screen_app_here('signin_microsoft', $registry['signin_microsoft'], $missing, true), 'a sign-in row is no account service');
	pg_assert_same('', pg_connections_screen_app_here('stripe', $registry['stripe'], $missing, true), 'a key service');
	pg_assert_same('', pg_connections_screen_app_here('google', array('state' => array('kind' => 'account', 'provider' => 'google')), $missing, true), 'the provider\'s own page holds it already');
	pg_assert_same('', pg_connections_screen_app_here('github', array('state' => array('kind' => 'account', 'provider' => 'github')), $missing, true), 'GitHub\'s page holds its own');
	pg_assert_same('', pg_connections_screen_app_here('google_drive', 'nothing', $missing, true), 'no service');
}

function test_connections_page_finishes_app_card_order()
{
	pg_assert_same(array('status', 'app', 'details', 'uses'), pg_connections_screen_page_cards('google_analytics', array('manager' => true, 'privacy' => true, 'app' => true)), 'the application before the tag and the figures');
	pg_assert_same(array('status', 'backups', 'app', 'uses'), pg_connections_screen_page_cards('google_drive', array('manager' => true, 'maintenance' => true, 'app' => true)), 'the drive: Connect inside Status, the application under it');
	pg_assert_same(array('status', 'app', 'uses'), pg_connections_screen_page_cards('google_calendar', array('manager' => true, 'app' => true)));
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('google_calendar', array('manager' => false, 'app' => true)), 'the manager gate stays');
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('github', array('manager' => true, 'app' => true)), 'a page holding the application has no second one');
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('google', array('manager' => true, 'app' => true)));
}

function test_connections_page_finishes_microsoft_signin_part()
{
	pg_assert_same(array('status', 'signin', 'details', 'uses'), pg_connections_screen_page_cards('microsoft', array('manager' => true, 'signin' => true)), 'Sign in with Microsoft\'s part on Microsoft\'s page');
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('microsoft', array('manager' => true, 'signin' => false)), 'not without the sign-in settings\' right');

	$security = connections_page_finishes_test_source('includes/settings/security.php');
	pg_assert_contains("pg_connections_lead_url('microsoft', 'security', 'pgset-signin', 'connections-signin')", $security, 'the card opens Microsoft\'s page at its sign-in part');

	$screen = connections_page_finishes_test_source('connections.php');
	pg_assert_contains('function connections_signin_microsoft_part(', $screen);
	pg_assert_true(strpos($screen, "'signin'    => (\$provider_page === 'google') &&") === false, 'the sign-in part is not Google\'s alone');
}

function test_connections_page_finishes_drives_one_answer()
{
	$screen = connections_page_finishes_test_source('connections.php');

	pg_assert_contains('function connections_app_card(', $screen, 'the application card of a service\'s page');
	pg_assert_contains('connections_app_form($provider_key, $provider, $app', $screen, 'the same form and storage as the provider\'s page');
	pg_assert_contains("!in_array('app', \$page['cards'], true) && !in_array('backups', \$page['cards'], true)", $screen, 'the pointer to the provider\'s page only where nothing here connects');
	pg_assert_contains("href=\"#connections-app\"", $screen, 'Google Analytics and Merchant Center point to the application on their page');

	pg_assert_same('connections.php?service=google_drive&from=maintenance%2Fpgset-backup#connections-app', pg_backup_cloud_app_url('gdrive'), 'the Backups card: the drive\'s own page');
	pg_assert_same('connections.php?service=onedrive&from=maintenance%2Fpgset-backup#connections-app', pg_backup_cloud_app_url('onedrive'));
	pg_assert_same('connections.php#connections-dropbox', pg_backup_cloud_app_url('dropbox'), 'Dropbox keeps its drawer');
}
