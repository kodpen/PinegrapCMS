<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sign-in and log-out addresses of a page drawn in another site
 * language (/en/...): a script the page sends its visitor to carries the
 * language as pg_lang (pg_tr_script_url(), includes/fn/translate.php), and a
 * designed page a redirect names starts at the language's directory
 * (_pg_sw_page_base(), includes/fn/widgets_account.php). Before (#9052) the
 * sign-in redirect of a private page and the account menu's Log Out left /en/
 * for the source language's pages.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _sign_in_page_language_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of a top-level PHP function, from its declaration to the next one.
function _sign_in_page_language_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, 'found: ' . $name);
	$end = strpos($source, "\nfunction ", (int) $start + 10);
	return ($end === false) ? (string) substr($source, (int) $start) : (string) substr($source, (int) $start, $end - (int) $start);
}

function test_sign_in_page_language_script_url_source_language()
{
	pg_assert_same('/pinegrap/logout.php?token=abc', pg_tr_script_url('/pinegrap/logout.php?token=abc', ''), 'the source language adds nothing');
	pg_assert_same('/pinegrap/index.php', pg_tr_script_url('/pinegrap/index.php', ''));
}

function test_sign_in_page_language_script_url_other_language()
{
	pg_assert_same('/pinegrap/index.php?pg_lang=en', pg_tr_script_url('/pinegrap/index.php', 'en'), 'the first parameter');
	pg_assert_same('/pinegrap/index.php?send_to=%2Fen%2Fhesabim&pg_lang=en', pg_tr_script_url('/pinegrap/index.php?send_to=%2Fen%2Fhesabim', 'en'), 'after the others');
	pg_assert_same('/pinegrap/logout.php?token=abc&send_to=%2F&pg_lang=pt-BR', pg_tr_script_url('/pinegrap/logout.php?token=abc&send_to=%2F', 'pt-BR'), 'a region code as the language row writes it');
	pg_assert_same('/pinegrap/registration_entrance.php?send_to=x&pg_lang=de#form', pg_tr_script_url('/pinegrap/registration_entrance.php?send_to=x#form', 'de'), 'before a fragment');
}

// Without a language directory (the source language, and this test run) a
// page address starts at PATH, as it always did.
function test_sign_in_page_language_page_base_source_language()
{
	if (defined('LANGUAGE_PATH')) {
		pg_assert_same(LANGUAGE_PATH, _pg_sw_page_base());
		return;
	}

	pg_assert_same(defined('PATH') ? PATH : '/', _pg_sw_page_base());
}

// The designed sign-in page of a redirect and of a software screen's
// fallback starts at the language's directory.
function test_sign_in_page_language_designed_pages()
{
	$source = _sign_in_page_language_source('includes/fn/widgets_account.php');

	pg_assert_contains("defined('LANGUAGE_PATH')", _sign_in_page_language_function($source, '_pg_sw_page_base'));
	pg_assert_contains('$url = _pg_sw_page_base() . encode_url_path($name);', _sign_in_page_language_function($source, 'pg_sw_sign_in_url'), 'the sign-in redirect of a private or registration page');
	pg_assert_contains('$url = _pg_sw_page_base() . encode_url_path($name);', _sign_in_page_language_function($source, 'pg_sw_screen_page_url'), 'index.php and forgot_password.php sending on to the designed page');
}

// Every redirect of get_page.php to a software script carries the language.
function test_sign_in_page_language_page_redirects()
{
	$source = _sign_in_page_language_source('get_page.php');

	$scripts = preg_match_all("~PATH \\. SOFTWARE_DIRECTORY \\. '/(logout|index|registration_entrance|membership_entrance)\\.php~", $source);
	$wrapped = preg_match_all("~pg_tr_script_url\\(PATH \\. SOFTWARE_DIRECTORY \\. '/(logout|index|registration_entrance|membership_entrance)\\.php~", $source);

	pg_assert_true($scripts >= 11, 'the redirects are found (' . $scripts . ')');
	pg_assert_same($scripts, $wrapped, 'each one through pg_tr_script_url()');
}

// The member widgets' Log Out links and the logout screen's own question.
function test_sign_in_page_language_logout_links()
{
	$account = _sign_in_page_language_source('includes/fn/widgets_account.php');

	pg_assert_contains("\$links['__logout_url'] = pg_tr_script_url(\$software . '/logout.php?token='", $account, 'the login region (account menu)');
	pg_assert_contains(": pg_tr_script_url(_pg_acct_software_path() . '/logout.php?send_to='", $account, 'the account widget without a logout page');
	pg_assert_contains("\$logout_url = pg_tr_script_url(\$software . '/logout.php?send_to='", _sign_in_page_language_source('includes/fn/widgets.php'), 'a signed-in visitor on a sign-in page');

	$logout = _sign_in_page_language_source('logout.php');
	pg_assert_contains('<input type="hidden" name="pg_lang" value="\' . h(pg_tr_language()) . \'">', $logout, 'the question posts the language on');
}
