<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the refusal answers of Upload Design Files
 * (add_design_file.php) and Upload File (add_file.php): Dropzone counts any
 * 2xx answer as success, so every refusal on its XHR path is JSON with a 4xx
 * status, a body PHP discarded for being over post_max_size is refused before
 * the form branch, and the screen shows the reason in a visible alert line.
 * Upload Design Files checks each file's upload error before copying it and
 * counts only the files written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function upload_refusal_answers_source($file = 'add_design_file.php')
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $file);
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
	foreach (array('add_design_file.php', 'add_file.php') as $file) {
		upload_refusal_answers_assert_screen($file);
	}
}

function upload_refusal_answers_assert_screen($file)
{
	$src = upload_refusal_answers_source($file);

	pg_assert_contains('dictResponseError : ', $src, $file . ': no translated dictResponseError');
	pg_assert_true((bool) preg_match('/<div[^>]*id="pg_upload_error"[^>]*role="alert"/', $src), $file . ': no role=alert line');

	$start = strpos($src, 'this.on("errormultiple"');
	pg_assert_true($start !== false, $file . ': no errormultiple handler');
	$handler = substr($src, (int) $start, 1600);
	pg_assert_false(strpos($handler, 'console.log(') !== false, $file . ': failure only logged to the console');
	pg_assert_contains('pg_upload_error', $handler, $file . ': failure not shown in the alert line');
	pg_assert_contains('[data-dz-errormessage]', $handler, $file . ': file bubbles not given the readable message');

	$start = strpos($src, 'this.on("sendingmultiple"');
	pg_assert_true($start !== false, $file . ': no sendingmultiple handler');
	pg_assert_contains('$("#pg_upload_error").prop("hidden", true)', substr($src, (int) $start, 300), $file . ': old failure line not hidden on a new send');

	// The real ceilings, as on Upload File: per file, the queue as a whole,
	// and a line saying both.
	pg_assert_false(strpos($src, 'maxFilesize: 9999') !== false, $file . ': maxFilesize is no limit');
	pg_assert_contains("maxFilesize: ' . \$dropzone_file_max_mb . ',", $src, $file . ': maxFilesize not the server limit');
	pg_assert_contains('dictFileTooBig: ', $src, $file . ': no dictFileTooBig');
	pg_assert_contains('accept: function (file, done)', $src, $file . ': queue total not checked');
	pg_assert_contains("'string' => 'At most {var:1} per file, and {var:2} in one upload.'", $src, $file . ': no limits line');
}

function test_upload_refusal_add_file_xhr_refusals_are_json_4xx()
{
	$src = upload_refusal_answers_source('add_file.php');

	// The discarded-body answer carries the key Dropzone writes into each bubble.
	pg_assert_contains("'error'   => \$message", $src, 'refusal answer has no error key');
	pg_assert_contains('upload_refusal_answer(413, $upload_message)', $src, 'discarded body not answered through the refusal helper');

	pg_assert_contains("upload_refusal_answer(403, lang('Your session expired. Reload the page and try again.'))", $src, 'token refusal not JSON 403');
	pg_assert_contains("upload_refusal_answer(400, lang('Please select a file.'))", $src, 'empty batch not JSON 400');
	pg_assert_contains("upload_refusal_answer(403, lang('Access denied.'))", $src, 'folder refusal not JSON 403');

	// The token check runs before validate_token_field(), whose HTML page
	// answers 200.
	$json_token = strpos($src, "upload_refusal_answer(403, lang('Your session expired.");
	$html_token = strpos($src, 'validate_token_field();');
	pg_assert_true($json_token !== false && $html_token !== false && $json_token < $html_token, 'JSON token check after validate_token_field()');

	// A Dropzone batch where every file failed is refused, not redirected.
	pg_assert_contains('upload_refusal_answer(400, $upload_failed_message)', $src, 'all-failed batch not refused');
}

function test_upload_refusal_design_files_checked_before_copy()
{
	$src = upload_refusal_answers_source();

	$error = strpos($src, "\$_FILES['file']['error'][\$index]");
	$copy  = strpos($src, 'copy($_FILES');
	pg_assert_true($error !== false, 'upload error code never read');
	pg_assert_true($copy !== false && $error < $copy, 'error code read after the copy');

	foreach (array(
		'{var:1} is larger than {var:2}, the biggest single file this server accepts.',
		'{var:1} only arrived in part, so it was not saved. Please try again.',
		'The server could not accept {var:1}. Please try again.') as $sentence) {
		pg_assert_contains("'string' => '" . $sentence . "'", $src, 'missing sentence: ' . $sentence);
	}

	// A copy that fails writes no row.
	pg_assert_contains('if (!@copy($_FILES', $src, 'copy() result not checked');

	// Only written files are counted and named.
	pg_assert_false(strpos($src, "count(\$_FILES['file']['name'])") !== false, 'notice counts the files sent, not the files written');
	pg_assert_contains('count($uploaded_files)', $src, 'notice does not count the files written');
	pg_assert_contains('upload_refusal_answer(400, $upload_failed_message)', $src, 'all-failed batch not refused');
	pg_assert_contains('add_warning(h($failed_file[\'message\']))', $src, 'failed files not reported on the list');
}
