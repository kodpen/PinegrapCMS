<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a page's sub-resource fetches at the firewall and the file
 * server: waf_fetch_is_subresource() (waf.php) reading Sec-Fetch-Dest and
 * Accept, waf_sensitive_request_counts() leaving a sub-resource fetch of a
 * sign-in screen off the sign-in, forms and checkout allowance, and
 * get_file.php sending a visitor to a sign-in screen only through
 * get_file_sign_in_redirect(), which refuses such a fetch instead.
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

// Sec-Fetch-Dest names what the page asked for; only a browser writes it.
function test_waf_subresource_by_destination()
{
	foreach (array('image', 'style', 'script', 'font', 'audio', 'video', 'track', 'manifest', 'worker', 'serviceworker') as $dest) {
		pg_assert_true(waf_fetch_is_subresource('GET', $dest, ''), $dest);
	}

	pg_assert_true(waf_fetch_is_subresource('HEAD', 'image', ''), 'a HEAD of an image');
	pg_assert_true(waf_fetch_is_subresource('get', ' Image ', ''), 'letter case and spaces');

	// A document, a frame or an embed draws a page; a fetch() reads its answer.
	foreach (array('document', 'iframe', 'frame', 'embed', 'object', 'empty', 'nested-document', 'report') as $dest) {
		pg_assert_false(waf_fetch_is_subresource('GET', $dest, 'image/avif,image/webp,*/*'), $dest);
	}

	// A browser never posts an image: a POST is always the visitor's own.
	pg_assert_false(waf_fetch_is_subresource('POST', 'image', 'image/*'), 'POST');
	pg_assert_false(waf_fetch_is_subresource('', 'image', ''), 'no method');
}

// Without the header (Safari before 16.4) the first media type of Accept
// stands in; a bare */* or an empty header is not taken for a sub-resource.
function test_waf_subresource_by_accept_without_destination()
{
	pg_assert_true(waf_fetch_is_subresource('GET', '', 'image/avif,image/webp,*/*'), 'Firefox image');
	pg_assert_true(waf_fetch_is_subresource('GET', '', 'image/webp,image/png,image/svg+xml,image/*;q=0.8,*/*;q=0.5'), 'Safari image');
	pg_assert_true(waf_fetch_is_subresource('GET', '', 'text/css,*/*;q=0.1'), 'style sheet');
	pg_assert_true(waf_fetch_is_subresource('GET', '', 'video/webm,video/ogg,video/*;q=0.9,application/ogg;q=0.7,audio/*;q=0.6,*/*;q=0.5'), 'video');
	pg_assert_true(waf_fetch_is_subresource('GET', '', 'audio/webm,audio/ogg,audio/wav,audio/*;q=0.9,application/ogg;q=0.7,video/*;q=0.6,*/*;q=0.5'), 'audio');
	pg_assert_true(waf_fetch_is_subresource('GET', '', 'font/woff2;q=1.0,*/*;q=0.1'), 'font');
	pg_assert_true(waf_fetch_is_subresource('GET', '', ' IMAGE/WEBP , */* '), 'letter case and spaces');

	pg_assert_false(waf_fetch_is_subresource('GET', '', 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8'), 'a page opening');
	pg_assert_false(waf_fetch_is_subresource('GET', '', '*/*'), 'a script tag on such a browser, or curl');
	pg_assert_false(waf_fetch_is_subresource('GET', '', 'application/json'), 'an API client');
	pg_assert_false(waf_fetch_is_subresource('GET', '', ''), 'no headers at all');
	pg_assert_false(waf_fetch_is_subresource('GET', '', 'imageboard/x'), 'a type that merely starts with the letters');
}

// The screens a protected page or file sends a visitor to sign in on count
// on the sensitive allowance like the other sensitive scripts.
function test_waf_subresource_sign_in_screens_are_sensitive()
{
	$screens = waf_sign_in_screens();

	pg_assert_true(in_array('registration_entrance.php', $screens, true), 'the site sign-in');
	pg_assert_true(in_array('index.php', $screens, true), 'the panel sign-in');
	pg_assert_true(in_array('membership_entrance.php', $screens, true), 'the membership sign-in');

	foreach ($screens as $screen) {
		pg_assert_true(in_array($screen, waf_sensitive_scripts(), true), $screen . ' is sensitive');
	}
}

// A sub-resource fetch landing on a sign-in screen is not counted; a page
// opening it, a submission, and a sub-resource fetch of any other sensitive
// script are.
function test_waf_subresource_sensitive_request_counts()
{
	pg_assert_false(waf_sensitive_request_counts('GET', 'registration_entrance.php', 'image', ''), 'an image redirected to the sign-in screen');
	pg_assert_false(waf_sensitive_request_counts('GET', 'registration_entrance.php', '', 'image/avif,image/webp,*/*'), 'the same on an older browser');
	pg_assert_false(waf_sensitive_request_counts('HEAD', 'index.php', 'style', ''), 'a style sheet on the panel sign-in');
	pg_assert_false(waf_sensitive_request_counts('GET', 'membership_entrance.php', 'font', ''), 'a font on the membership sign-in');

	pg_assert_true(waf_sensitive_request_counts('GET', 'registration_entrance.php', 'document', ''), 'a visitor opening the sign-in screen');
	pg_assert_true(waf_sensitive_request_counts('GET', 'registration_entrance.php', '', 'text/html,*/*;q=0.8'), 'the same on an older browser');
	pg_assert_true(waf_sensitive_request_counts('GET', 'registration_entrance.php', '', '*/*'), 'a command-line client');
	pg_assert_true(waf_sensitive_request_counts('GET', 'registration_entrance.php', 'empty', ''), 'a fetch() of the sign-in screen');
	pg_assert_true(waf_sensitive_request_counts('POST', 'registration_entrance.php', 'image', 'image/*'), 'a submission claiming to be an image');
	pg_assert_true(waf_sensitive_request_counts('POST', 'get_page.php', '', ''), 'every POST');
	pg_assert_true(waf_sensitive_request_counts('GET', 'reset_password.php', 'image', ''), 'a reset link fetched as an image still counts');
	pg_assert_true(waf_sensitive_request_counts('GET', 'add_to_cart.php', 'image', ''), 'a cart action fetched as an image still counts');

	pg_assert_false(waf_sensitive_request_counts('GET', 'get_page.php', 'document', ''), 'a page is not sensitive');
	pg_assert_false(waf_sensitive_request_counts('GET', '', 'document', ''), 'no script');
}

// get_file.php sends a visitor who is not signed in to a sign-in screen only
// through get_file_sign_in_redirect(), which answers a sub-resource fetch
// with 403 instead; no bare redirect to a sign-in screen is left.
function test_waf_subresource_get_file_redirects_through_the_helper()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');

	pg_assert_same(0, preg_match_all('/header\(\'Location: \' \. URL_SCHEME \. HOSTNAME \. PATH \. SOFTWARE_DIRECTORY \. \'\/(index|registration_entrance|membership_entrance)\.php/', $source), 'no bare redirect to a sign-in screen');

	pg_assert_same(5, substr_count($source, "\n" . 'get_file_sign_in_redirect(') + substr_count($source, ' get_file_sign_in_redirect('), 'the private, guest, registration and membership folders and the attachment refusal');
	pg_assert_contains("get_file_sign_in_redirect('index.php');", $source);
	pg_assert_contains("get_file_sign_in_redirect('registration_entrance.php?allow_guest=true');", $source);
	pg_assert_contains("get_file_sign_in_redirect('membership_entrance.php');", $source);
	pg_assert_same(2, substr_count($source, "get_file_sign_in_redirect('registration_entrance.php');"), 'the registration folder and the attachment refusal');

	$helper = strpos($source, 'function get_file_sign_in_redirect(');
	pg_assert_true($helper !== false, 'the helper is defined');
	$body = substr($source, (int) $helper, 1800);
	pg_assert_contains('waf_fetch_is_subresource(', $body);
	pg_assert_contains("\$_SERVER['HTTP_SEC_FETCH_DEST']", $body);
	pg_assert_contains("\$_SERVER['HTTP_ACCEPT']", $body);
	pg_assert_contains('403', $body);
	pg_assert_contains("'send_to=' . urlencode(get_request_uri())", $body);
	pg_assert_true(strpos($body, 'output_error(') < strpos($body, "header('Location: '"), 'the refusal comes before the redirect');
}
