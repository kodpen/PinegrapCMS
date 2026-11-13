<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_error_json_action() (includes/fn/errors.php): an action that
 * answers in JSON keeps its answer when nothing goes wrong; a reported
 * warning, an exception, output_error() or an answer that cannot be written
 * as JSON turns into the error answer; what the action printed never reaches
 * the output, and the flag and handlers it set are put back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _error_json_action_failed()
{
	return array('status' => 'error', 'message' => 'failed');
}

// Runs the guard and answers what it returned and what reached the output.
// The handler underneath lets PHP carry on and print the message, as
// pg_error_record() does on a site; the runner's own would throw on every
// message. PHP's log would write it to the console as well.
function _error_json_action_run($action)
{
	$log = ini_set('log_errors', '0');

	set_error_handler(function () {
		return false;
	});

	ob_start();
	$answer = pg_error_json_action($action, _error_json_action_failed());
	$printed = (string) ob_get_clean();

	restore_error_handler();
	ini_set('log_errors', (string) $log);

	return array('answer' => $answer, 'printed' => $printed);
}

function test_error_json_action_keeps_a_good_answer()
{
	$run = _error_json_action_run(function () {
		return array('status' => 'success', 'tree' => '{}');
	});

	pg_assert_same(array('status' => 'success', 'tree' => '{}'), $run['answer']);
	pg_assert_same('', $run['printed'], 'nothing printed');
}

function test_error_json_action_drops_what_was_printed()
{
	$run = _error_json_action_run(function () {
		echo "<br />\n<b>Note</b>: printed on the way";

		return array('status' => 'success');
	});

	pg_assert_same(array('status' => 'success'), $run['answer'], 'the answer stands');
	pg_assert_same('', $run['printed'], 'the printed text is not in the output');
}

function test_error_json_action_turns_a_warning_into_the_error_answer()
{
	$run = _error_json_action_run(function () {
		$list = array('a');
		$text = 'x' . $list;

		return array('status' => 'success', 'text' => $text);
	});

	pg_assert_same(_error_json_action_failed(), $run['answer']);
	pg_assert_same('', $run['printed'], 'no PHP text in the output');
}

function test_error_json_action_turns_an_exception_into_the_error_answer()
{
	$run = _error_json_action_run(function () {
		throw new RuntimeException('broken');
	});

	pg_assert_same(_error_json_action_failed(), $run['answer']);
}

function test_error_json_action_output_error_throws_inside()
{
	pg_assert_false(pg_error_throws(), 'off before');

	$run = _error_json_action_run(function () {
		output_error('A screen nobody asked for.');

		return array('status' => 'success');
	});

	pg_assert_same(_error_json_action_failed(), $run['answer']);
	pg_assert_same('', $run['printed'], 'no error screen in the output');
	pg_assert_false(pg_error_throws(), 'off again after');
}

function test_error_json_action_an_answer_json_cannot_write_fails()
{
	$run = _error_json_action_run(function () {
		return array('status' => 'success', 'text' => "\xC3\x28");
	});

	pg_assert_same(_error_json_action_failed(), $run['answer']);
}

function test_error_json_action_a_deprecation_does_not_fail_the_action()
{
	$run = _error_json_action_run(function () {
		@trigger_error('silenced', E_USER_WARNING);
		trigger_error('old way', E_USER_DEPRECATED);

		return array('status' => 'success');
	});

	pg_assert_same(array('status' => 'success'), $run['answer'], 'a silenced warning and a deprecation leave the answer');
}

function test_error_json_action_puts_the_error_handler_back()
{
	$mine = function () {
		return true;
	};

	set_error_handler($mine);

	ob_start();
	pg_error_json_action(function () {
		return array('status' => 'success');
	}, _error_json_action_failed());
	ob_end_clean();

	$current = set_error_handler(function () {
		return false;
	});
	restore_error_handler();
	restore_error_handler();

	pg_assert_true($current === $mine, 'the handler in place before is in place after');
}
