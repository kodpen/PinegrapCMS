<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the forward confirmation of a crawler's reverse DNS name
 * (waf_forward_confirm_match() in waf.php): the visitor's address must be
 * among the A and AAAA records of the name, compared as packed addresses so
 * one IPv6 address written two ways still matches.
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

// The records as dns_get_record($host, DNS_A | DNS_AAAA) returns them.
function waf_bot_verify_test_records()
{
	return array(
		array('host' => 'crawl-66-249-66-1.googlebot.com', 'class' => 'IN', 'ttl' => 300, 'type' => 'A', 'ip' => '66.249.66.1'),
		array('host' => 'crawl-66-249-66-1.googlebot.com', 'class' => 'IN', 'ttl' => 300, 'type' => 'AAAA', 'ipv6' => '2001:4860:4801::1'),
	);
}

// An IPv6 crawler matches its AAAA record whatever the notation.
function test_waf_forward_confirm_match_ipv6()
{
	$records = waf_bot_verify_test_records();

	pg_assert_true(waf_forward_confirm_match('2001:4860:4801:0:0:0:0:1', $records), 'expanded notation');
	pg_assert_true(waf_forward_confirm_match('2001:4860:4801::1', $records), 'compressed notation');
	pg_assert_true(waf_forward_confirm_match('2001:4860:4801:0000::0001', $records), 'leading zeros');
	pg_assert_false(waf_forward_confirm_match('2001:4860:4801::2', $records), 'another address of the block');
}

// An IPv4 crawler matches its A record.
function test_waf_forward_confirm_match_ipv4()
{
	$records = waf_bot_verify_test_records();

	pg_assert_true(waf_forward_confirm_match('66.249.66.1', $records), 'the A record');
	pg_assert_false(waf_forward_confirm_match('66.249.66.2', $records), 'not among the records');
	pg_assert_false(waf_forward_confirm_match('66.249.66.1', array($records[1])), 'only an AAAA record');
}

// Nothing to compare is never a match.
function test_waf_forward_confirm_match_malformed()
{
	$records = waf_bot_verify_test_records();

	pg_assert_false(waf_forward_confirm_match('not an address', $records), 'the visitor address');
	pg_assert_false(waf_forward_confirm_match('', $records), 'no visitor address');
	pg_assert_false(waf_forward_confirm_match('66.249.66.1', array()), 'no records');
	pg_assert_false(waf_forward_confirm_match('66.249.66.1', false), 'a failed lookup');
	pg_assert_false(waf_forward_confirm_match('66.249.66.1', array('66.249.66.1', array('type' => 'A'), array('type' => 'A', 'ip' => 'bogus'))), 'records without an address');
}
