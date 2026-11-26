<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for waf_mask_request_url() and waf_credential_params() (waf.php): the
 * request address the firewall log stores and shows keeps its path and its
 * parameter names, while the value of a credential parameter becomes '*'.
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

// A flow's web address keeps the flow id and loses the key.
function test_waf_mask_flow_key()
{
	pg_assert_same(
		'/pinegrap/flow_start.php?flow=12&key=*',
		waf_mask_request_url('/pinegrap/flow_start.php?flow=12&key=' . str_repeat('a1', 32), 'flow_start.php'));
}

// The calendar feed token is masked; an address without a query is unchanged.
function test_waf_mask_calendar_token_and_plain_path()
{
	pg_assert_same('/pinegrap/workspace_ical.php?token=*', waf_mask_request_url('/pinegrap/workspace_ical.php?token=abc123', 'workspace_ical.php'));
	pg_assert_same('/about-us', waf_mask_request_url('/about-us', 'router.php'));
	pg_assert_same('', waf_mask_request_url('', ''));
}

// The common names are masked for every script, as whole names, whatever
// their case or encoding; other values are the evidence and stay as sent.
function test_waf_mask_common_names_everywhere()
{
	pg_assert_same(
		"/x.php?q=' OR 1=1&ToKeN=*&access_token=*&monkey=1&key_id=2",
		waf_mask_request_url("/x.php?q=' OR 1=1&ToKeN=s3cr3t&access_token=zz&monkey=1&key_id=2", 'x.php'));
	pg_assert_same('/x.php?%6Bey=*&a=1', waf_mask_request_url('/x.php?%6Bey=v&a=1', 'x.php'));
	pg_assert_same('/x.php?access.token=*', waf_mask_request_url('/x.php?access.token=v', 'x.php'), 'PHP reads a dot as _');
	pg_assert_same('/x.php?key[]=*&key[a]=*', waf_mask_request_url('/x.php?key[]=v&key[a]=w', 'x.php'));
	pg_assert_same('/x.php?password=*&sig=*&secret=*&signature=*', waf_mask_request_url('/x.php?password=p&sig=s&secret=t&signature=u', 'x.php'));
}

// A name with no value, an empty value and repeated separators stay as sent.
function test_waf_mask_keeps_shape()
{
	pg_assert_same('/x.php?key&token=&&a=1', waf_mask_request_url('/x.php?key&token=&&a=1', 'x.php'));
	pg_assert_same('/x.php?key=*', waf_mask_request_url('/x.php?key=a=b', 'x.php'), 'a value with = is one value');
}

// A short name is masked only for the scripts that read it as a credential.
function test_waf_mask_short_names_per_script()
{
	pg_assert_same('/pinegrap/tickets.php?o=5&t=*', waf_mask_request_url('/pinegrap/tickets.php?o=5&t=abcdef', 'tickets.php'));
	pg_assert_same('/shop?t=newsletter', waf_mask_request_url('/shop?t=newsletter', 'router.php'), 'a tracking code is not a credential');
	pg_assert_same('/set-password?k=*', waf_mask_request_url('/set-password?k=abc', 'router.php'));
	pg_assert_same('/pinegrap/connect.php?code=*&state=*', waf_mask_request_url('/pinegrap/connect.php?code=a&state=b', 'connect.php'));
	pg_assert_same('/pinegrap/connections.php?state=attention', waf_mask_request_url('/pinegrap/connections.php?state=attention', 'connections.php'));
	pg_assert_same('/pinegrap/confirm_subscription.php?c=1&t=17000&s=*', waf_mask_request_url('/pinegrap/confirm_subscription.php?c=1&t=17000&s=sig', 'confirm_subscription.php'));
	pg_assert_same('/pinegrap/feedback.php?t=*&s=4', waf_mask_request_url('/pinegrap/feedback.php?t=tok&s=4', 'feedback.php'), 'feedback reads s as the score');
}

// With no script given, the script is read from the address itself, so the
// screens and the alert mask a row the same way the request was masked.
function test_waf_mask_script_from_address()
{
	pg_assert_same('/pinegrap/tickets.php?o=5&t=*', waf_mask_request_url('/pinegrap/tickets.php?o=5&t=abc'));
	pg_assert_same('/set-password?k=*', waf_mask_request_url('/set-password?k=abc'));
	pg_assert_same('/shop?t=summer', waf_mask_request_url('/shop?t=summer'));
	pg_assert_same('/pinegrap/includes/phpmailer/get_oauth_token.php?code=*', waf_mask_request_url('/pinegrap/includes/phpmailer/get_oauth_token.php?code=abc'));
}

// The list itself: lower-case whole names, the common ones for any script.
function test_waf_credential_params_list()
{
	$any = waf_credential_params('');

	foreach (array('key', 'token', 'secret', 'signature', 'password', 'access_token') as $name) {
		pg_assert_true(in_array($name, $any, true), $name . ' is always masked');
	}

	pg_assert_true(!in_array('t', $any, true), 't is masked only per script');
	pg_assert_true(in_array('t', waf_credential_params('Tickets.php'), true), 'the script name is not case-sensitive');
}
