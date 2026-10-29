<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the refusal answers of Upload Design Files
 * (add_design_file.php): Dropzone counts any 2xx answer as success, so every
 * refusal on its XHR path is JSON with a 4xx status, a body PHP discarded
 * for being over post_max_size is refused before the form branch, and the
 * screen shows the reason in a visible alert line.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function upload_refusal_answers_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/add_design_file.php');
}

function test_upload_refusal_discarded_body_before_form_branch()
{
	$src = upload_refusal_answers_source();

	$discarded = strpos($src, 'if (pg_post_body_discarded())');
	$form      = strpos($src, 'if (!$_POST)');
	pg_assert_true($discarded !== false, 'no discarded-body check');
	pg_assert_true($form !== false && $discarded < $form, 'discarded-body check runs after the form branch');
	pg_assert_contains('upload_refusal_answer(413,', $src, 'discarded body not answered with 413');
}

function test_upload_refusal_xhr_refusals_are_json_4xx()
{
	$src = upload_refusal_answers_source();

	pg_assert_contains("strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'", $src, 'XHR caller not detected');
	pg_assert_contains("header('Content-Type: application/json; charset=utf-8');", $src);
	pg_assert_contains('http_response_code($status);', $src);

	// Token, empty batch and refused name each answer the XHR caller with a 4xx.
	pg_assert_contains("upload_refusal_answer(403, lang('Your session expired. Reload the page and try again.'))", $src, 'token refusal not JSON 403');
	pg_assert_contains("upload_refusal_answer(400, lang('Please select a file.'))", $src, 'empty batch not JSON 400');
	pg_assert_contains('upload_refusal_answer(415, pg_upload_blocked_message($file_name))', $src, 'refused name not JSON 415');

	// The non-XHR fallback form keeps its HTML error pages.
	pg_assert_contains('validate_token_field();', $src);
	pg_assert_contains('output_error(h(pg_upload_blocked_message($file_name))', $src);
}

function test_upload_refusal_screen_shows_the_reason()
{
	$src = upload_refusal_answers_source();

	pg_assert_contains('dictResponseError : ', $src, 'no translated dictResponseError');
	pg_assert_true((bool) preg_match('/<div[^>]*id="pg_upload_error"[^>]*role="alert"/', $src), 'no role=alert line');

	$start = strpos($src, 'this.on("errormultiple"');
	pg_assert_true($start !== false, 'no errormultiple handler');
	$handler = substr($src, (int) $start, 1600);
	pg_assert_false(strpos($handler, 'console.log(') !== false, 'failure only logged to the console');
	pg_assert_contains('pg_upload_error', $handler, 'failure not shown in the alert line');
	pg_assert_contains('[data-dz-errormessage]', $handler, 'file bubbles not given the readable message');
}
