<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pins, by source, that the bell panel and the Accounting card's Test the
 * connection / Connect buttons show a refusal. The bell's load, edit and
 * Delete All only logged a refused answer, so an open bell spun forever and
 * a refused row vanished while it stayed on the server. The card's
 * endpoints ran validate_user() and validate_token_field() ahead of their
 * format=json answer, and the card printed
 * "HTTP Unexpected token '<'..." under the button.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function bell_edoc_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of a file before its first validate_user() call.
function bell_edoc_before_validate_user($file)
{
	$source = bell_edoc_source($file);
	$at = strpos($source, '$user = validate_user();');
	return ($at === false) ? '' : substr($source, 0, $at);
}

// One JavaScript function of backend.src.js, up to the next top-level one.
function bell_edoc_js_function($name)
{
	$source = bell_edoc_source('assets/js/backend.src.js');
	$start = strpos($source, "\nfunction " . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\nfunction ", $start + 10);
	return substr($source, $start, ($end === false) ? null : ($end - $start));
}

function bell_edoc_card_handler()
{
	$source = bell_edoc_source('assets/js/backend.src.js');
	$start = strpos($source, '"click", "button.pg-edoc-test"');
	return ($start === false) ? '' : substr($source, $start, 6000);
}

function test_bell_edoc_endpoints_refuse_in_json_before_validate_user()
{
	foreach (array('get_erp_edoc_test.php', 'parasut_connect.php') as $file) {
		$head = bell_edoc_before_validate_user($file);
		pg_assert_contains("\$_POST['format'] === 'json'", $head, $file);
		pg_assert_contains('USER_LOGGED_IN', $head, $file);
		pg_assert_contains("lang('Your session has expired. Reload the page and sign in again.')", $head, $file);
		pg_assert_contains('hash_equals(', $head, $file);
		pg_assert_contains("lang('Your session expired. Reload the page and try again.')", $head, $file);
		pg_assert_contains("'tone' => 'danger'", $head, $file);
		pg_assert_contains("'icon' => 'bi-x-circle'", $head, $file);
		pg_assert_contains("'lines' => array(\$message)", $head, $file);
		pg_assert_contains('401', $head, $file);
		pg_assert_contains('403', $head, $file);
	}
}

function test_bell_edoc_card_handler_reads_refusals_and_prints_no_raw_error()
{
	$handler = bell_edoc_card_handler();
	pg_assert_true($handler !== '', 'the card handler exists');
	pg_assert_contains('response.redirected', $handler);
	pg_assert_contains('return response.json().then(', $handler);
	pg_assert_contains('lang("An error occurred.")', $handler);
	pg_assert_false(strpos($handler, '"HTTP " + error.message') !== false, 'no raw HTTP/parse error under the button');
	pg_assert_false(strpos($handler, 'response.ok ? response.json()') !== false, 'a refusal body is read on a non-OK status');
}

function test_bell_edoc_get_notifications_shows_failure_and_drops_spinner()
{
	$fn = bell_edoc_js_function('get_notifications');
	pg_assert_contains('var load_failed = function (response, xhr)', $fn);
	pg_assert_contains('spinner.remove();', $fn);
	pg_assert_contains('pg-bell-failed', $fn);
	pg_assert_contains('pg_bell_refusal(response, xhr)', $fn);
	pg_assert_contains('kept_badge', $fn);
	pg_assert_contains('load_failed(response);', $fn);
	pg_assert_contains('load_failed(null, xhr);', $fn);
	pg_assert_false(strpos($fn, 'console.log(') !== false, 'a refused load is not only logged');
}

function test_bell_edoc_edit_and_remove_all_tell_the_reader()
{
	$edit = bell_edoc_js_function('edit_notification');
	pg_assert_contains('put_back();', $edit);
	pg_assert_contains('pg_bell_refused(response);', $edit);
	pg_assert_contains('pg_bell_refused(null, xhr);', $edit);
	pg_assert_false(strpos($edit, "\$target.hide('slow', function") !== false, 'the row is not removed before the answer');
	pg_assert_false(strpos($edit, 'console.log(') !== false, 'a refused edit is not only logged');

	$all = bell_edoc_js_function('remove_all_notifications');
	pg_assert_contains('pg_bell_refused(response);', $all);
	pg_assert_contains('pg_bell_refused(null, xhr);', $all);
	pg_assert_false(strpos($all, 'console.log(') !== false, 'a refused Delete All is not only logged');

	$refusal = bell_edoc_js_function('pg_bell_refusal');
	pg_assert_contains('response.message', $refusal);
	pg_assert_contains('xhr.responseJSON.message', $refusal);
	pg_assert_contains("lang('An error occurred.')", $refusal);
	pg_assert_contains("variant: 'danger'", bell_edoc_js_function('pg_bell_refused'));
}
