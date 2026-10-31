<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the Update Exchange Rates button: the currencies toolbar
 * link carries the form token, and the web branch of update_exchange_rates.php
 * checks it before any exchange rate is rewritten.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _exchange_rates_token_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The web branch checks the token after the access gate and before the first write.
function test_exchange_rates_web_branch_checks_token()
{
	$source = _exchange_rates_token_source('update_exchange_rates.php');
	$else = strpos($source, '} else {');
	pg_assert_true($else !== false);

	$access = strpos($source, 'validate_ecommerce_access(', $else);
	$token = strpos($source, 'validate_token_field()', $else);
	$update = strpos($source, 'UPDATE currencies');

	pg_assert_true($access !== false);
	pg_assert_true($token !== false);
	pg_assert_true($update !== false);
	pg_assert_true($access < $token);
	pg_assert_true($token < $update);
	pg_assert_true($token < strpos($source, 'SELECT id, code FROM currencies'));
}

// A background run has no session token, so its branch never checks one.
function test_exchange_rates_background_branch_has_no_token_check()
{
	$source = _exchange_rates_token_source('update_exchange_rates.php');
	$start = strpos($source, 'if (pg_cron_is_background_run()) {');
	$else = strpos($source, '} else {', (int) $start);

	pg_assert_true($start !== false);
	pg_assert_true(strpos(substr($source, $start, $else - $start), 'validate_token_field') === false);
}

// The toolbar url carries the raw token (pg_screen_toolbar() escapes it once)
// after an encoded send_to, so the return path cannot shadow it.
function test_exchange_rates_toolbar_url_carries_token()
{
	$source = _exchange_rates_token_source('view_currencies.php');
	pg_assert_true((bool) preg_match("/'url' => 'update_exchange_rates\\.php\\?send_to=' \\. urlencode\\(get_request_uri\\(\\)\\) \\. '&token=' \\. \\(\\\$_SESSION\\['software'\\]\\['token'\\] \\?\\? ''\\)/", $source));
	pg_assert_true(strpos($source, "update_exchange_rates.php?send_to=' . get_request_uri() . get_token_query_string_field()") === false);
}
