<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the language the external API's texts come in
 * (api_text_language_pick(), includes/api/resources/meta.php): GET /meta and
 * GET /auth/me name the language whose file lang() loads, 'en' when the
 * language lang() picks has no file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/meta.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/account.php');

// A file check that knows only en and tr, as the repository ships.
function api_text_language_test_files()
{
	return function ($code) {
		return in_array($code, array('en', 'tr'), true);
	};
}

// Each source wins over the next one.
function test_api_text_language_pick_order()
{
	$has = api_text_language_test_files();

	pg_assert_same('tr', api_text_language_pick('tr', false, 'en', 'en', $has), 'front-end first');
	pg_assert_same('tr', api_text_language_pick('', false, 'tr', 'en', $has), 'enforced next');
	pg_assert_same('tr', api_text_language_pick('', false, '', 'tr', $has), 'software next');
	pg_assert_same('en', api_text_language_pick('', false, '', '', $has), 'en last');
	pg_assert_same('en', api_text_language_pick(null, false, null, null, $has), 'nothing defined');
}

// A front-end language held back by pg_tr_suspend() is skipped.
function test_api_text_language_pick_skips_a_suspended_frontend()
{
	$has = api_text_language_test_files();

	pg_assert_same('en', api_text_language_pick('tr', true, '', 'en', $has), 'suspended');
	pg_assert_same('tr', api_text_language_pick('en', true, 'tr', 'en', $has), 'suspended, enforced wins');
}

// A language with no file of its own is answered as English, since lang()
// keeps the English source text for it.
function test_api_text_language_pick_without_a_file_is_en()
{
	$has = api_text_language_test_files();

	pg_assert_same('en', api_text_language_pick('de', false, 'tr', 'tr', $has), 'front-end de');
	pg_assert_same('en', api_text_language_pick('', false, '', 'fr', $has), 'software fr');
}

// The wrapper answers a language that has a file.
function test_api_text_language_has_a_file()
{
	$code = api_text_language();

	pg_assert_true(is_string($code) && ($code !== ''), 'a code');
	pg_assert_true(($code === 'en') || pg_tr_ui_has_file($code), 'with a file');
}

// Both answers declare the field.
function test_api_text_language_declared()
{
	$meta = api_meta_schema();
	$me = api_auth_me_schema();

	pg_assert_same('string', isset($meta['language']) ? $meta['language'] : null, 'meta');
	pg_assert_same('string', isset($me['language']) ? $me['language'] : null, 'auth/me');

	$account = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/account.php');
	$meta_source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/meta.php');

	pg_assert_true(strpos($account, "'language' => api_text_language()") !== false, 'auth/me answers it');
	pg_assert_true(strpos($meta_source, "'language'       => api_text_language()") !== false, 'meta answers it');
}
