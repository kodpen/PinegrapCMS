<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the firewall's per-address counters count and whom they
 * spare: a private or reserved visitor address is not counted
 * (waf_ip_is_private_or_reserved(), waf_ip_is_counted()), the web
 * root's index.php answers as router.php (waf_script_name_from()), the
 * ShipWorks and guest room endpoints are credential endpoints, and the
 * api.php allowance exempts staff rather than any session.
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

// A private, link-local, loopback or unspecified address (the ranges PHP's
// filter rejects as private or reserved; the IPv4 documentation ranges are
// not among them) is nobody's on the internet and is not counted; a public
// one is a visitor's. Being
// infrastructure (the server, a trusted proxy) keeps its own meaning, which
// the maintenance card reads: a private address is not that.
function test_waf_subjects_private_or_reserved_addresses()
{
	waf_config(array('waf_trusted_proxies' => ''));

	foreach (array('10.1.2.3', '172.16.0.1', '172.31.255.254', '192.168.1.1', '127.0.0.1', '169.254.10.10', '0.0.0.0', 'fd00::1', 'fc00::1', 'fe80::1', '::1', '::') as $ip) {
		pg_assert_true(waf_ip_is_private_or_reserved($ip), $ip);
		pg_assert_false(waf_ip_is_counted($ip), $ip . ' is not counted');
	}

	foreach (array('203.0.113.9', '8.8.8.8', '172.32.0.1', '192.169.1.1', '2a00:1450:4001::1', '2001:4860::8888') as $ip) {
		pg_assert_false(waf_ip_is_private_or_reserved($ip), $ip);
		pg_assert_true(waf_ip_is_counted($ip), $ip . ' is counted');
	}

	pg_assert_false(waf_ip_is_infrastructure('192.168.1.20'), 'a private address is not the server or a proxy');
	pg_assert_true(waf_ip_is_infrastructure('127.0.0.1'), 'loopback is');
	pg_assert_false(waf_ip_is_counted('not an address'), 'an unresolved address is not counted, as before');
}

// Every per-address counter asks the one question.
function test_waf_subjects_counters_ask_whether_the_address_counts()
{
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');

	foreach (array('waf_handle_rate_global', 'waf_handle_rate_sensitive', 'waf_handle_rate_api', 'waf_handle_inflight', 'waf_register_offence') as $name) {
		$start = strpos($waf, 'function ' . $name . '(');
		$body = substr($waf, (int) $start, (int) strpos($waf, "\n}\n", (int) $start) - (int) $start);
		pg_assert_contains('if (!waf_ip_is_counted($ip)) {', $body, $name);
	}
}

// The web root's index.php (a require of router.php) answers as router.php;
// the software directory's index.php, the panel sign-in, keeps its name.
function test_waf_subjects_root_index_is_the_router()
{
	$software = dirname(PG_FUNCTIONS_DIR) . '/pinegrap';

	pg_assert_same('router.php', waf_script_name_from('/index.php', dirname($software) . '/index.php', $software), 'the web root');
	pg_assert_same('index.php', waf_script_name_from('/pinegrap/index.php', $software . '/index.php', $software), 'the panel sign-in');
	pg_assert_same('index.php', waf_script_name_from('/pinegrap/index.php', $software . '/./index.php', $software), 'the same directory spelt differently');
	pg_assert_same('router.php', waf_script_name_from('/pinegrap/router.php', $software . '/router.php', $software), 'the router itself');
	pg_assert_same('registration_entrance.php', waf_script_name_from('/pinegrap/registration_entrance.php', $software . '/registration_entrance.php', $software), 'another script');
	pg_assert_same('router.php', waf_script_name_from('/index.php', '/index.php', '/pinegrap'), 'a chroot whose web root is the file system root');
	pg_assert_same('index.php', waf_script_name_from('/index.php', '', $software), 'no file name known: the name as it is');
	pg_assert_same('index.php', waf_script_name_from('/index.php', '/var/www/index.php', ''), 'no software directory known');
	pg_assert_same('router.php', waf_script_name_from('/index.php', 'C:\\inetpub\\wwwroot\\index.php', 'C:\\inetpub\\wwwroot\\pinegrap'), 'Windows paths');
	pg_assert_same('index.php', waf_script_name_from('/pinegrap/index.php', 'C:\\inetpub\\wwwroot\\pinegrap\\index.php', 'c:/inetpub/wwwroot/pinegrap'), 'Windows paths, case and slashes');
	pg_assert_same('', waf_script_name_from('', '', $software), 'nothing');

	$root = (string) file_get_contents(dirname(PG_FUNCTIONS_DIR) . '/index.php');
	pg_assert_true((bool) preg_match('/^<\?php\s+require\(\'pinegrap\/router\.php\'\);\s*$/', $root), 'the web root index.php only requires the router');
}

// The ShipWorks endpoint and the guest room answer by a credential, so they
// are spared the bot policy and the sign-in allowance like the other
// credential endpoints; the site's own api.php keeps its allowance.
function test_waf_subjects_credential_endpoints()
{
	$api = array('integration.php', 'api.php', 'mcp.php', 'oauth.php', 'workspace_ical.php', 'feed.php', 'marketplace_webhook.php', 'shipworks.php', 'workspace_guest.php', 'payment_callback.php');

	pg_assert_true(waf_names_script_in('/pinegrap/shipworks.php', '/pinegrap/shipworks.php?action=getorders', $api), 'ShipWorks');
	pg_assert_true(waf_names_script_in('/pinegrap/workspace_guest.php', '/pinegrap/workspace_guest.php', $api), 'a guest room');
	pg_assert_false(waf_names_script_in('/pinegrap/index.php', '/pinegrap/index.php/shipworks.php', $api), 'the name as PATH_INFO claims nothing');

	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	pg_assert_contains("'marketplace_webhook.php', 'workspace_guest.php', 'payment_callback.php');", $waf, 'the guest room in waf_is_api_request()');
	pg_assert_contains("if (waf_schema_ready() && ((int) waf_setting('login_throttle', 1) === 1)) {\n            \$names[] = 'shipworks.php';", $waf, 'ShipWorks only while the sign-in throttle counts its password');
	pg_assert_contains("\$answer = waf_request_names_script(array('api.php'));", $waf, 'api.php alone keeps the site allowance');

	// Neither joins the country restriction's API exemption: a warehouse's
	// client and a person's guest room are held to the country rule like
	// the panel.
	$country = waf_country_api_scripts();
	pg_assert_same(array('integration.php', 'mcp.php', 'oauth.php', 'workspace_ical.php', 'feed.php', 'marketplace_webhook.php', 'payment_callback.php'), $country);
	pg_assert_false(waf_names_script_in('/pinegrap/shipworks.php', '/pinegrap/shipworks.php', $country), 'ShipWorks is not country-exempt');
	pg_assert_false(waf_names_script_in('/pinegrap/workspace_guest.php', '/pinegrap/workspace_guest.php', $country), 'a guest room is not country-exempt');
	pg_assert_contains("waf_setting('waf_country_exempt_api', 1) && waf_request_names_script(waf_country_api_scripts())", $waf, 'the country restriction reads its own list');
}

// The sign-in throttle's address lock asks the same question as the
// firewall's counters, so one shared address cannot lock everybody out.
function test_waf_subjects_sign_in_throttle_counts_the_same_addresses()
{
	$auth = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
	$start = strpos($auth, 'function pg_login_throttle_subjects(');
	$body = substr($auth, (int) $start, (int) strpos($auth, "\n}\n", (int) $start) - (int) $start);

	pg_assert_contains("\$address_counted = function_exists('waf_ip_is_counted')", $body);
	pg_assert_contains("if (\$ip !== '' && \$address_counted) {", $body);
}

// The api.php allowance asks whether the session is staff, as the sensitive
// limit and the in-flight cap do, not merely whether there is one.
function test_waf_subjects_api_allowance_exempts_staff_only()
{
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$start = strpos($waf, 'function waf_handle_rate_api(');
	$body = substr($waf, (int) $start, (int) strpos($waf, "\n}\n", (int) $start) - (int) $start);

	pg_assert_contains('if (waf_user_is_staff()) {', $body);
	pg_assert_false(strpos($body, 'waf_user_is_authenticated()') !== false, 'not any session');
}

// The Firewall settings screen warns when the operator's own request
// resolves to a private address: a proxy in front of the site is not listed.
function test_waf_subjects_settings_warn_about_an_unlisted_proxy()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/firewall.php');

	pg_assert_contains("\$waf_own_address = function_exists('waf_client_ip') ? (string) waf_client_ip() : '';", $screen);
	pg_assert_contains("waf_ip_is_private_or_reserved(\$waf_own_address) && !waf_ip_in_any(\$waf_own_address, array('127.0.0.0/8', '::1/128'))", $screen, 'loopback is the operator on the server itself');
	pg_assert_contains("lang(array('string' => 'The firewall sees this request as coming from {var:1}, a private address: a proxy in front of the site that is not listed here, or a local network.", $screen);
}
