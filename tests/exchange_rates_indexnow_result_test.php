<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Update Exchange Rates announces only the rates it wrote and says when no
 * rate service answered; the IndexNow ping logs success only for an answer
 * IndexNow counts as accepted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _exchange_rates_result_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// IndexNow answers 200 or 202 for a ping it took; 400, 403, 422 and 429 refuse it.
function test_indexnow_ping_accepted_only_for_200_and_202()
{
	pg_assert_true(pg_indexnow_ping_accepted(200));
	pg_assert_true(pg_indexnow_ping_accepted(202));

	foreach (array(0, 204, 301, 400, 403, 422, 429, 500) as $status) {
		pg_assert_true(!pg_indexnow_ping_accepted($status));
	}
}

// A refused ping goes through the failure line with its HTTP code.
function test_indexnow_refusal_logged_with_http_code()
{
	pg_assert_same('HTTP 403', pg_indexnow_ping_failure(array('status' => 403, 'error' => '')));
	pg_assert_same('HTTP 422', pg_indexnow_ping_failure(array('status' => 422, 'error' => '')));
	pg_assert_same('Could not resolve host', pg_indexnow_ping_failure(array('status' => 0, 'error' => 'Could not resolve host')));

	$source = _exchange_rates_result_source('includes/fn/seo.php');
	pg_assert_true(strpos($source, 'Any HTTP answer counts as sent') === false);
	pg_assert_true(strpos($source, 'if (pg_indexnow_ping_accepted($answer[\'status\'])) {') !== false);
	pg_assert_true(strpos($source, "lang('IndexNow ping failed: ') . pg_indexnow_ping_failure(\$answer)") !== false);
}

// The success notice counts the written rates and is left out when none was written.
function test_exchange_rates_notice_counts_written_rates()
{
	$source = _exchange_rates_result_source('update_exchange_rates.php');

	pg_assert_true(strpos($source, "lang('The exchange rates have been updated.')") === false);
	pg_assert_true(strpos($source, "'string' => '{var:1} exchange rates were updated'") !== false);
	pg_assert_true(strpos($source, 'if ($updated > 0) {') !== false);
	pg_assert_true(strpos($source, '$updated++;') !== false);

	// The per-currency mark stays.
	pg_assert_true(strpos($source, "'string' => 'Failed to update exchange rate for currency {var:1}'") !== false);
}

// No answer for any wanted currency: one error that the rates are unchanged.
function test_exchange_rates_no_answer_says_rates_unchanged()
{
	$source = _exchange_rates_result_source('update_exchange_rates.php');

	pg_assert_true(strpos($source, 'if ($wanted && !$fetched) {') !== false);
	pg_assert_true(strpos($source, "lang('The exchange rate services could not be reached, so the exchange rates were not changed.')") !== false);
}
