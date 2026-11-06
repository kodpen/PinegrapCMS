<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the per-address limits on the ERP document link, OAuth client
 * registration and OAuth client metadata reads count the firewall's subject
 * (waf_ip_subject(): the /64 for IPv6), not the raw address: keyed on the
 * address, every IPv6 address of one sender had its own allowance.
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

function rate_subject_call_sites_test_source($relative)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);

	pg_assert_true(is_string($source) && ($source !== ''));

	return (string) $source;
}

// No limit in these files takes the raw address as its subject.
function test_rate_subject_call_sites_no_raw_address()
{
	foreach (array('get_account_document.php', 'includes/oauth/endpoints.php', 'includes/oauth/server.php') as $relative) {
		$source = rate_subject_call_sites_test_source($relative);

		pg_assert_same(0, preg_match('/waf_rate_(exceeded|record|hits)\(\s*(waf_client_ip|api_client_ip)\(\)/', $source), $relative);
	}
}

// Each of the three limits is keyed on waf_ip_subject().
function test_rate_subject_call_sites_keyed_on_subject()
{
	$expected = array(
		'get_account_document.php'     => "'erp_doc'",
		'includes/oauth/endpoints.php' => "'oauth_reg'",
		'includes/oauth/server.php'    => "'oauth_cimd'",
	);

	foreach ($expected as $relative => $scope) {
		$source = rate_subject_call_sites_test_source($relative);

		pg_assert_same(1, preg_match('/waf_rate_exceeded\(\s*waf_ip_subject\([^;]*' . preg_quote($scope, '/') . '/', $source), $relative);
	}
}

// Two addresses of one IPv6 /64 are one subject; an IPv4 address is itself.
function test_rate_subject_call_sites_ipv6_share_one_subject()
{
	pg_assert_same(waf_ip_subject('2001:db8:1:2::1'), waf_ip_subject('2001:db8:1:2:ffff::9'));
	pg_assert_same('2001:db8:1:2::/64', waf_ip_subject('2001:db8:1:2::1'));
	pg_assert_same('203.0.113.7', waf_ip_subject('203.0.113.7'));
}
