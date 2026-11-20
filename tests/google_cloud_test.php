<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what setting up the Google application needs
 * (includes/fn/google_cloud.php) and for Google's and Microsoft's own pages
 * on Connections (connections.php?service=google, ...=microsoft): the APIs
 * read from the registry, Google's "API has not been used in project"
 * answer read as a plain sentence, the verification notes shared with a
 * Gmail mailbox's form, and the provider pages' keys, cards and uses.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_google_cloud_apis_come_from_the_registry()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_cloud.php');

	$apis = pg_google_cloud_apis(pg_connections_registry());

	pg_assert_same(array('gmail'), $apis['gmail.googleapis.com']['features'] ?? null, 'Gmail names the Gmail API');
	pg_assert_same('Google Calendar API', $apis['calendar-json.googleapis.com']['name'] ?? null, 'Google Calendar names its API');
	pg_assert_same(array('google_drive'), $apis['drive.googleapis.com']['features'] ?? null, 'Drive');
	pg_assert_true(isset($apis['searchconsole.googleapis.com'], $apis['analyticsdata.googleapis.com'], $apis['merchantapi.googleapis.com']), 'Search Console, Analytics and Merchant Center');

	foreach (pg_connections_registry() as $key => $service) {
		if (!empty($service['apis'])) {
			pg_assert_same('google', $service['state']['provider'] ?? null, $key . ' calls Google through a connected account');
		}
	}

	pg_assert_same(78, pg_connections_count(), 'the figure stays');
	pg_assert_same(array('a' => array('name' => 'A API', 'features' => array('x', 'y'))),
		pg_google_cloud_apis(array('x' => array('apis' => array('a' => 'A API')), 'y' => array('apis' => array('a' => 'A API')), 'z' => array())), 'an API once, with every feature');
}

function test_google_cloud_api_disabled_answer()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_cloud.php');

	$text = 'HTTP 403: Google Calendar API has not been used in project 123456789 before or it is disabled. Enable it by visiting '
		. 'https://console.developers.google.com/apis/api/calendar-json.googleapis.com/overview?project=123456789 then retry.';

	pg_assert_same(array(
		'name' => 'Google Calendar API',
		'api'  => 'calendar-json.googleapis.com',
		'url'  => 'https://console.cloud.google.com/apis/library/calendar-json.googleapis.com?project=123456789',
	), pg_google_cloud_api_disabled($text), 'the API, and its enable page in the project');

	$bare = pg_google_cloud_api_disabled('Gmail API has not been used in project my-project before or it is disabled.');
	pg_assert_same('Gmail API', $bare['name'] ?? null, 'without the address');
	pg_assert_same('https://console.cloud.google.com/apis/library?project=my-project', $bare['url'] ?? null, 'the project\'s library');

	pg_assert_same(null, pg_google_cloud_api_disabled('HTTP 401: invalid_grant'), 'any other error stays as it is');
	pg_assert_same(null, pg_google_cloud_api_disabled(''), 'no error');
	pg_assert_same('https://console.cloud.google.com/apis/library/gmail.googleapis.com', pg_google_cloud_enable_url('gmail.googleapis.com'), 'the enable page');
}

function test_google_cloud_verification_notes()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/google_cloud.php');

	$general = pg_google_cloud_verification_notes();
	$gmail = pg_google_cloud_verification_notes('gmail');

	pg_assert_same(5, count($general), 'when, Testing, seven days, Internal, verification');
	pg_assert_same(array_slice($general, 1, 3), array_slice($gmail, 1, 3), 'the three ways and the seven days are the same on both');
	pg_assert_contains('Test users', $general[1], 'Testing names the test users');
	pg_assert_contains('seven days', $general[2], 'a test user\'s access ends after seven days');
	pg_assert_contains('Internal', $general[3], 'an Internal application');
	pg_assert_contains('security assessment', $gmail[4], 'full Gmail access also needs the assessment');
	pg_assert_true(strpos($general[4], 'security assessment') === false, 'the general note does not claim it');
}

function test_google_cloud_provider_pages()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

	$rows = array(
		'provider_google'    => array('kind' => 'provider'),
		'provider_microsoft' => array('kind' => 'provider'),
		'provider_github'    => array('kind' => 'provider'),
		'github'             => array('kind' => 'service'),
		'google_calendar'    => array('kind' => 'service'),
		'signin_google'      => array('kind' => 'service'),
	);

	pg_assert_same('google', pg_connections_screen_page_id('google', $rows), 'Google has a page of its own');
	pg_assert_same('microsoft', pg_connections_screen_page_id('microsoft', $rows), 'Microsoft too');
	pg_assert_same('', pg_connections_screen_page_id('dropbox', $rows), 'Dropbox keeps its drawer');
	pg_assert_same('', pg_connections_screen_page_id('google', array('google_calendar' => array('kind' => 'service'))), 'not without the provider\'s row');
	pg_assert_same('google', pg_connections_screen_provider_page('google', $rows), 'the provider of the page');
	pg_assert_same('', pg_connections_screen_provider_page('github', $rows), 'GitHub\'s page is its service\'s');

	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('google', array('manager' => true)), 'the application for a manager');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('microsoft', array('manager' => false)), 'the screen\'s manager gate stays');

	$uses = pg_connections_screen_provider_uses('google', pg_connections_registry(), $rows);
	pg_assert_same(array('signin_google', 'google_calendar'), $uses, 'the visible services Google serves: by its accounts or as sign-in');
}

function test_google_cloud_pages_on_the_screen()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/connections.php');

	pg_assert_contains('pg_google_cloud_redirect_addresses($provider_key)', $source, 'both redirect addresses on the provider\'s page');
	pg_assert_contains("lang('Both must be added: signing in and connecting an account come back to different addresses.')", $source, 'the note');
	pg_assert_contains('connections_google_cloud_parts($registry, $rows)', $source, 'Google\'s APIs and warning');
	pg_assert_contains('pg_google_cloud_verification_notes()', $source, 'the warning shared with the mailbox form');
	pg_assert_contains("connections_error_html(\$account['last_error'])", $source, 'an account\'s error is read');
	pg_assert_contains("window.location.replace(pages[named[1]])", $source, 'the old drawer address leads to the page');

	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');
	pg_assert_contains("pg_google_cloud_verification_notes('gmail')", $mail, 'the mailbox form reads the same notes');
	pg_assert_contains("pg_google_cloud_redirect_addresses('google')", $mail, 'and the same addresses');

	$security = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/security.php');
	pg_assert_contains('href="connections.php?service=microsoft"', $security, 'Microsoft sign-in leads to Microsoft\'s page');
}
