<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the security headers in waf.php: reading which headers a
 * web.config adds itself, so that PHP does not send them a second time.
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

// The <add> rules of <customHeaders>, lower-cased; <remove> and comments are not.
function test_waf_headers_reads_iis_custom_headers()
{
	$xml = '<configuration><system.webServer>
		<!-- <add name="X-Old" value="1" /> -->
		<httpProtocol>
			<customHeaders>
				<remove name="X-Content-Type-Options" />
				<add name="X-Content-Type-Options" value="nosniff" />
				<remove name="Referrer-Policy" />
				<add value="strict-origin-when-cross-origin" name=\'Referrer-Policy\' />
				<remove name="X-Powered-By" />
			</customHeaders>
		</httpProtocol>
	</system.webServer></configuration>';

	pg_assert_same("x-content-type-options\nreferrer-policy", waf_iis_custom_headers($xml));
}

// The repository's own web.config is what IIS sites run with. It adds
// nosniff; Referrer-Policy is PHP's, so that a page's no-referrer is not
// overridden by a server copy appended after it.
function test_waf_headers_shipped_web_config_leaves_referrer_policy_to_php()
{
	$names = explode("\n", waf_iis_custom_headers((string) file_get_contents(dirname(PG_FUNCTIONS_DIR) . '/web.config')));

	pg_assert_true(in_array('x-content-type-options', $names, true), 'nosniff');
	pg_assert_false(in_array('referrer-policy', $names, true), 'referrer policy');
}

// The rules the software writes for a new site or a repair do not set
// Referrer-Policy on any server.
function test_waf_headers_server_rules_leave_referrer_policy_to_php()
{
	$iis = pg_server_config_default('iis');
	$names = explode("\n", waf_iis_custom_headers($iis));

	pg_assert_true(in_array('x-content-type-options', $names, true), 'iis nosniff');
	pg_assert_false(in_array('referrer-policy', $names, true), 'iis referrer policy');
	pg_assert_false((bool) preg_match('/Header\s+(always\s+)?set\s+Referrer-Policy/i', pg_server_config_default('apache')), 'apache');
	pg_assert_false((bool) preg_match('/add_header\s+Referrer-Policy/i', pg_server_config_default('nginx')), 'nginx');
}

function test_waf_headers_nothing_without_custom_headers()
{
	pg_assert_same('', waf_iis_custom_headers(''), 'empty file');
	pg_assert_same('', waf_iis_custom_headers('<configuration><system.webServer /></configuration>'), 'no section');
}

// Not under IIS (the test runner has no server at all), PHP sends its own.
function test_waf_headers_not_added_outside_iis()
{
	pg_assert_false(waf_server_adds_header('X-Content-Type-Options'));
}
