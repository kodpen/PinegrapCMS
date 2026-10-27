<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the firewall's country restriction in waf.php: reading a list of
 * country codes, deciding whether a country is refused, the paths the
 * restriction always leaves alone (health.php among them), and when the
 * panel's sign-in screens keep
 * a visitor to their built-in forms.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// Upper-cased, in the order written, each once; T1 kept, anything else dropped.
function test_waf_country_codes_reads_a_list()
{
	pg_assert_same(array('TR', 'DE', 'US'), waf_country_codes('tr, de;US'), 'separators and case');
	pg_assert_same(array('TR', 'DE'), waf_country_codes("TR\nDE\nTR"), 'duplicates');
	pg_assert_same(array('T1', 'NL'), waf_country_codes('t1 nl'), 'Tor');
	pg_assert_same(array('FR'), waf_country_codes('TUR, 1, X, FR, ;'), 'junk');
	pg_assert_same(array(), waf_country_codes(''), 'empty');
}

// Block refuses the listed countries, allow refuses every other one.
function test_waf_country_refused_by_mode()
{
	$list = array('TR', 'DE');

	pg_assert_true(waf_country_refused('DE', 'block', $list, false), 'block: listed');
	pg_assert_false(waf_country_refused('US', 'block', $list, false), 'block: not listed');
	pg_assert_false(waf_country_refused('TR', 'allow', $list, false), 'allow: listed');
	pg_assert_true(waf_country_refused('US', 'allow', $list, false), 'allow: not listed');
	pg_assert_true(waf_country_refused('T1', 'allow', $list, false), 'allow: Tor is not a listed country');
	pg_assert_false(waf_country_refused('US', 'off', $list, true), 'off');
	pg_assert_false(waf_country_refused('US', '', $list, true), 'no mode');
}

// An unknown country is let in unless the operator asked otherwise, in either mode.
function test_waf_country_refused_unknown_country()
{
	pg_assert_false(waf_country_refused('', 'allow', array('TR'), false), 'allow, let in');
	pg_assert_true(waf_country_refused('', 'allow', array('TR'), true), 'allow, refused');
	pg_assert_false(waf_country_refused('', 'block', array('TR'), false), 'block, let in');
	pg_assert_true(waf_country_refused('', 'block', array('TR'), true), 'block, refused');
}

// robots.txt and .well-known are never restricted; a page is.
function test_waf_country_exempts_always_served_paths()
{
	$saved = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;

	$_SERVER['REQUEST_URI'] = '/.well-known/acme-challenge/abc';
	pg_assert_true(waf_country_request_exempt(), 'ACME challenge');

	$_SERVER['REQUEST_URI'] = '/robots.txt?x=1';
	pg_assert_true(waf_country_request_exempt(), 'robots.txt');

	if ($saved === null) {
		unset($_SERVER['REQUEST_URI']);
	} else {
		$_SERVER['REQUEST_URI'] = $saved;
	}
}

// health.php is known by the script that runs, never by a path that names it:
// router.php serving /x/health.php/y must not carry the uptime check's
// exemption from the bot policy and the country restriction.
function test_waf_health_request_by_running_script()
{
	pg_assert_true(waf_is_health_request('health.php'), 'health.php');
	pg_assert_false(waf_is_health_request('router.php'), 'router.php');
	pg_assert_false(waf_is_health_request('get_page.php'), 'a page');
	pg_assert_false(waf_is_health_request('health.php.bak'), 'another file');
	pg_assert_false(waf_is_health_request(''), 'no script');
	pg_assert_false(waf_is_health_request(), 'the test runner');
}

// The site's OAuth client metadata (connect_client.php) is fetched by the
// service's sign-in server, from wherever it runs and under whatever name its
// fetcher has: known by the running script, like health.php, it skips the bot
// policy and the country restriction.
function test_waf_client_metadata_request_by_running_script()
{
	pg_assert_true(waf_is_client_metadata_request('connect_client.php'), 'connect_client.php');
	pg_assert_false(waf_is_client_metadata_request('router.php'), 'router.php');
	pg_assert_false(waf_is_client_metadata_request('connect_callback.php'), 'the sign-in return, a person in a browser');
	pg_assert_false(waf_is_client_metadata_request(''), 'no script');
	pg_assert_false(waf_is_client_metadata_request(), 'the test runner');

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	pg_assert_same(2, substr_count($waf, 'waf_is_health_request() || waf_is_client_metadata_request()'), 'the bot policy and the country restriction both let it through');
}

// No proof was recorded in this process, so no crawler is let past.
function test_waf_country_bot_proof_starts_absent()
{
	pg_assert_false(waf_bot_verified());
}

// Staff from a refused country stay on the panel's own sign-in forms while
// the panel is exempt; anyone the site's pages would let in is not kept.
function test_waf_country_keeps_refused_staff_to_panel()
{
	$config = array(
		'waf_country_mode' => 'block',
		'waf_country_list' => 'AQ, KP',
		'waf_country_exempt_panel' => 1,
		'waf_country_unknown' => 0,
	);

	pg_assert_true(waf_country_keeps_to_panel_decision('block', $config, 'AQ', false), 'refused country');
	pg_assert_false(waf_country_keeps_to_panel_decision('block', $config, 'US', false), 'country let in');
	pg_assert_false(waf_country_keeps_to_panel_decision('monitor', $config, 'AQ', false), 'monitor mode refuses nobody');
	pg_assert_false(waf_country_keeps_to_panel_decision('off', $config, 'AQ', false), 'firewall off');
	pg_assert_false(waf_country_keeps_to_panel_decision('block', $config, 'AQ', true), 'allowed address');
	pg_assert_false(waf_country_keeps_to_panel_decision('block', $config, '', false), 'unknown country let in');

	$config['waf_country_unknown'] = 1;
	pg_assert_true(waf_country_keeps_to_panel_decision('block', $config, '', false), 'unknown country refused');

	$config['waf_country_exempt_panel'] = 0;
	pg_assert_false(waf_country_keeps_to_panel_decision('block', $config, 'AQ', false), 'panel not exempt');

	$config['waf_country_exempt_panel'] = 1;
	$config['waf_country_mode'] = 'allow';
	$config['waf_country_list'] = 'TR';
	pg_assert_true(waf_country_keeps_to_panel_decision('block', $config, 'DE', false), 'allow mode, other country');
	pg_assert_false(waf_country_keeps_to_panel_decision('block', $config, 'TR', false), 'allow mode, listed country');

	$config['waf_country_mode'] = 'off';
	pg_assert_false(waf_country_keeps_to_panel_decision('block', $config, 'DE', false), 'restriction off');
}

// Without a database the answer is no, and the panel behaves as it always has.
function test_waf_country_keeps_to_panel_fails_open()
{
	pg_assert_false(waf_country_keeps_to_panel());
}
