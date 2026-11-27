<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Paraşüt's test environment (staging) with a client from its
 * Developer Portal: the sign-in addresses of each environment, the
 * Environment select on Paraşüt's page of Connections and its read-only state
 * while the site is connected, the amber pill only while connected in
 * staging, and the older way left as it was.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of a function of a source file, read from the source.
function parasut_staging_test_body($file, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// Only the word staging names the test environment; anything else is live.
function test_parasut_staging_environment_value()
{
	pg_assert_same('staging', parasut_environment_value('staging'));
	pg_assert_same('staging', parasut_environment_value(" staging\n"));
	pg_assert_same('live', parasut_environment_value(''), 'no row');
	pg_assert_same('live', parasut_environment_value('Staging'));
	pg_assert_same('live', parasut_environment_value('test'));
	pg_assert_same('live', parasut_environment_value(array('staging')));
}

// Live signs in at id.parasut.com, staging at id-staging.parasut.com, on the
// same paths; the API answers at api.parasut.com and api-staging.parasut.com.
function test_parasut_staging_sign_in_addresses()
{
	$live = parasut_sign_in('live');
	pg_assert_same('https://id.parasut.com/realms/parasut/protocol/openid-connect/auth', $live['authorize']);
	pg_assert_same('https://id.parasut.com/realms/parasut/protocol/openid-connect/token', $live['token']);
	pg_assert_same('https://id.parasut.com/realms/parasut', $live['issuer']);

	$staging = parasut_sign_in('staging');
	pg_assert_same('https://id-staging.parasut.com/realms/parasut/protocol/openid-connect/auth', $staging['authorize']);
	pg_assert_same('https://id-staging.parasut.com/realms/parasut/protocol/openid-connect/token', $staging['token']);
	pg_assert_same('https://id-staging.parasut.com/realms/parasut', $staging['issuer']);

	pg_assert_same($live, parasut_sign_in('anything'), 'an unknown value is live');

	pg_assert_same('https://api.parasut.com', parasut_api_base('live'));
	pg_assert_same('https://api-staging.parasut.com', parasut_api_base('staging'));
	pg_assert_same('https://api.parasut.com', parasut_api_base('anything'), 'an unknown value is live');
}

// The token in use decides the API host: staging only for the portal
// connection's token in the staging environment. The older way's token stays
// on the live host whatever the row says.
function test_parasut_staging_api_host_follows_the_token()
{
	pg_assert_same('staging', parasut_api_environment('connection', 'staging'));
	pg_assert_same('live', parasut_api_environment('connection', 'live'));
	pg_assert_same('live', parasut_api_environment('legacy', 'staging'), 'the older token with the staging row');
	pg_assert_same('live', parasut_api_environment('legacy', 'live'));

	pg_assert_same('https://api-staging.parasut.com', parasut_api_base(parasut_api_environment('connection', 'staging')));
	pg_assert_same('https://api.parasut.com', parasut_api_base(parasut_api_environment('connection', 'live')));
	pg_assert_same('https://api.parasut.com', parasut_api_base(parasut_api_environment('legacy', 'staging')));

	// The v4 requests and the /me read go through the environment-aware base;
	// the older way's OAuth addresses keep the old one.
	pg_assert_contains('_parasut_v4_base()', parasut_staging_test_body('includes/fn/parasut.php', '_parasut_prefix'));
	pg_assert_contains('_parasut_v4_base()', parasut_staging_test_body('includes/fn/parasut.php', 'parasut_me'));
	pg_assert_contains('_parasut_base()', parasut_staging_test_body('includes/fn/parasut.php', 'parasut_authorize_url'));
	pg_assert_contains('_parasut_base()', parasut_staging_test_body('includes/fn/parasut.php', '_parasut_token_exchange'));
}

// The invoice on Paraşüt's own screen: uygulama.parasut.com for live,
// uygulama-staging.parasut.com for staging.
function test_parasut_staging_screen_link()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');

	pg_assert_same('https://uygulama.parasut.com/123/satislar/456', erp_edoc_parasut_panel_address('123', '456', 'live'));
	pg_assert_same('https://uygulama-staging.parasut.com/35477/satislar/9', erp_edoc_parasut_panel_address('35477', '9', 'staging'));
	pg_assert_same('', erp_edoc_parasut_panel_address('', '9', 'staging'), 'no company');
	pg_assert_same('', erp_edoc_parasut_panel_address('35477', ' ', 'live'), 'no record');
}

// The provider takes its addresses and the issuer it checks from the site's
// environment; with no row (as here) it is live.
function test_parasut_staging_provider_reads_the_environment()
{
	$provider = pg_connect_provider('parasut');

	pg_assert_same('live', parasut_environment(), 'no row');
	pg_assert_same('https://id.parasut.com/realms/parasut/protocol/openid-connect/auth', $provider['authorize_url']);
	pg_assert_same('https://id.parasut.com/realms/parasut/protocol/openid-connect/token', $provider['token_url']);
	pg_assert_same(array('https://id.parasut.com/realms/parasut'), $provider['issuers']);

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/connect/providers/parasut.php');
	pg_assert_contains('parasut_sign_in(', $source);
	pg_assert_contains('parasut_environment()', $source);
}

// The select: Live and Test (staging) with the help; while connected it is
// disabled and says why, so a token of one environment is never used
// against the other.
function test_parasut_staging_select_states()
{
	$open = parasut_environment_field_html('staging', false);
	pg_assert_contains('name="parasut_environment"', $open);
	pg_assert_contains('value="live"', $open);
	pg_assert_contains('value="staging" selected', $open);
	pg_assert_contains('Test (staging)', $open);
	pg_assert_contains('Documents made there are not real.', $open);
	pg_assert_false(strpos($open, 'disabled') !== false, 'free while not connected');
	pg_assert_false(strpos($open, 'Disconnect to change the environment.') !== false);

	$locked = parasut_environment_field_html('live', true);
	pg_assert_contains('value="live" selected', $locked);
	pg_assert_contains(' disabled', $locked);
	pg_assert_contains('Disconnect to change the environment.', $locked);

	// The save follows the same rule: nothing changes while connected.
	pg_assert_same('staging', parasut_environment_save_value('staging', null));
	pg_assert_same('live', parasut_environment_save_value('live', null));
	pg_assert_same('live', parasut_environment_save_value('bogus', null));
	pg_assert_same(null, parasut_environment_save_value('staging', array('id' => 7, 'status' => 'active')), 'connected');
	pg_assert_same(null, parasut_environment_save_value('staging', array('id' => 7, 'status' => 'reauth')), 'one to be made again');
	pg_assert_same(null, parasut_environment_save_value(null, null), 'not posted');

	$form = parasut_staging_test_body('connections.php', 'connections_app_form');
	$select = strpos($form, 'parasut_environment_field_html(');
	pg_assert_true($select !== false, 'drawn in the application boxes');
	pg_assert_true($select > strpos($form, '$redirect_html'), 'after the redirect address');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/connections.php');
	pg_assert_contains('parasut_environment_save(', $screen, 'saved with Save');
}

// The pill only while connected in staging: not live, not without a
// connection, not with one to be made again.
function test_parasut_staging_pill_only_when_connected_in_staging()
{
	$active = array('id' => 7, 'status' => 'active');

	$mark = parasut_environment_mark('staging', $active);
	pg_assert_true($mark['is_test']);
	pg_assert_same('Test environment', $mark['label']);

	pg_assert_false(parasut_environment_mark('live', $active)['is_test'], 'live');
	pg_assert_false(parasut_environment_mark('staging', null)['is_test'], 'not connected');
	pg_assert_false(parasut_environment_mark('staging', array('id' => 7, 'status' => 'reauth'))['is_test'], 'to be made again');

	// The e-document screens read it through the driver's environment.
	require_once(PG_FUNCTIONS_DIR . '/includes/erp/edoc/parasut.php');
	pg_assert_true(function_exists('erp_edoc_parasut_environment'), 'the driver answers the environment');

	$page = parasut_staging_test_body('connections.php', 'connections_edoc_page');
	pg_assert_contains('pg-tone-amber', $page);
}

// The older way (a client from before the portal) has no staging: its
// authorize address and its API stay on api.parasut.com.
function test_parasut_staging_older_way_untouched()
{
	$authorize = parasut_staging_test_body('includes/fn/parasut.php', 'parasut_authorize_url');
	pg_assert_false(strpos($authorize, 'parasut_environment') !== false, 'no environment in the older Connect');
	pg_assert_false(strpos($authorize, 'id-staging') !== false);

	$exchange = parasut_staging_test_body('includes/fn/parasut.php', '_parasut_token_exchange');
	pg_assert_false(strpos($exchange, 'parasut_environment') !== false, 'no environment in the older exchange');

	pg_assert_contains('https://api.parasut.com', parasut_authorize_url('legacy-client'));
}
