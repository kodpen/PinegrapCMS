<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site-side scripts that call the software with fetch() or XMLHttpRequest
 * pass by the jQuery prefilter that adds pg_lang (frontend.src.js), so each
 * one sends the page's language (<html data-pg-lang>) itself; without it the
 * answer (a refusal, a sent-code sentence, the SMS text) comes back in the
 * source language on a translated page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function site_fetch_language_test_read($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The body of the named JavaScript function, up to the next top-level
// function of the same indent.
function site_fetch_language_test_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\n    function ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_site_fetch_language_chat_site_api_sends_page_language()
{
	$api = site_fetch_language_test_function(site_fetch_language_test_read('chat_site.src.js'), 'api');

	pg_assert_contains("getAttribute('data-pg-lang')", $api);
	pg_assert_contains('payload.pg_lang', $api);
}

function test_site_fetch_language_chat_site_min_twin_sends_page_language()
{
	$min = site_fetch_language_test_read('chat_site.min.js');

	pg_assert_true(strpos($min, 'getAttribute("data-pg-lang")') !== false);
	pg_assert_true(strpos($min, '.pg_lang=') !== false);
}

function test_site_fetch_language_passkey_post_sends_page_language()
{
	$post = site_fetch_language_test_function(site_fetch_language_test_read('assets/js/pg_passkey.js'), 'post');

	pg_assert_contains("getAttribute('data-pg-lang')", $post);
	pg_assert_contains("body.append('pg_lang'", $post);
}

function test_site_fetch_language_inline_scripts_send_page_language()
{
	$sms = site_fetch_language_test_read('includes/fn/sms_codes.php');
	$at = strpos($sms, 'data.append("purpose","cod")');

	pg_assert_true($at !== false);
	pg_assert_contains('data.append("pg_lang"', substr($sms, $at, 600));

	$passkeys = site_fetch_language_test_read('includes/fn/passkeys.php');
	$parts = explode('body.append("purpose", "recovery");', $passkeys);

	pg_assert_same(3, count($parts));

	for ($i = 1; $i < count($parts); $i++) {
		pg_assert_contains('body.append("pg_lang", lang);', substr($parts[$i], 0, 400));
	}
}
