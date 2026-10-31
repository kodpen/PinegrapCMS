<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_login_captcha_check() in includes/fn/auth.php: a right answer,
 * a wrong or missing question and a question that ran out are told apart,
 * the question is consumed in every case, and the gate records no failed
 * sign-in for a question that only ran out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs pg_login_captcha_check() against a stubbed session question and
// returns the outcome and whether the question is still in the session.
function login_captcha_expiry_test_check($pending, $answer)
{
	$saved = isset($_SESSION) ? $_SESSION : null;

	$_SESSION = array('software' => array());

	if ($pending !== null) {
		$_SESSION['software']['login_captcha'] = $pending;
	}

	$outcome = pg_login_captcha_check($answer);
	$left = isset($_SESSION['software']['login_captcha']);

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}

	return array($outcome, $left);
}

function test_login_captcha_check_right_answer_is_ok()
{
	pg_assert_same(array('ok', false), login_captcha_expiry_test_check(array('answer' => 7, 'issued' => time() - 30), ' 7 '));
}

function test_login_captcha_check_wrong_answer_is_wrong()
{
	pg_assert_same(array('wrong', false), login_captcha_expiry_test_check(array('answer' => 7, 'issued' => time() - 30), '8'));
	pg_assert_same(array('wrong', false), login_captcha_expiry_test_check(array('answer' => 7, 'issued' => time() - 30), '7a'));
}

function test_login_captcha_check_missing_or_malformed_question_is_wrong()
{
	pg_assert_same(array('wrong', false), login_captcha_expiry_test_check(null, '7'));
	pg_assert_same(array('wrong', false), login_captcha_expiry_test_check('7', '7'));
	pg_assert_same(array('wrong', false), login_captcha_expiry_test_check(array('answer' => 7), '7'));
}

function test_login_captcha_check_old_question_is_expired_even_when_right()
{
	pg_assert_same(array('expired', false), login_captcha_expiry_test_check(array('answer' => 7, 'issued' => time() - 601), '7'));
	pg_assert_same(array('expired', false), login_captcha_expiry_test_check(array('answer' => 7, 'issued' => time() - 900), '3'));
	pg_assert_same(array('ok', false), login_captcha_expiry_test_check(array('answer' => 7, 'issued' => time() - 590), '7'));
}

// The body of pg_login_captcha_gate(), up to the next function.
function login_captcha_expiry_test_gate_body()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
	$start = strpos($source, 'function pg_login_captcha_gate(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_login_captcha_gate_expired_branch_records_no_failure()
{
	$body = login_captcha_expiry_test_gate_body();
	$expired = strpos($body, "if (\$outcome === 'expired') {");
	$failure = strpos($body, 'pg_login_record_failure($identifier);');

	pg_assert_true($expired !== false, 'expired branch present');
	pg_assert_true($failure !== false, 'wrong answer still recorded');

	$branch = substr($body, $expired, $failure - $expired);

	pg_assert_contains("'question expired'", $branch);
	pg_assert_contains("lang('The question was open for more than 10 minutes. Please answer the new one.')", $branch);
	pg_assert_false(strpos($branch, 'pg_login_record_failure') !== false, 'no failure recorded for an expired question');
	pg_assert_contains("lang('That answer is not right. Please try the new question.')", substr($body, $failure));
}
