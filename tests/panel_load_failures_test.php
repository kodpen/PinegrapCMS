<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pins, by source, that the dashboard cards and the SEO detail panel say when
 * a load fails: a dashboard card whose request fails or is refused gets a
 * retry line instead of a skeleton that pulses for good, and the SEO fragment
 * answers an ended session or a stale token with a status the callers check,
 * instead of the sign-in page or the site's designed error page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_load_failures_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The body of a JavaScript function, from its name to the next top-level
// "function " declaration at the same indent.
function panel_load_failures_js_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) {
		return '';
	}
	$next = strpos($source, "\n    function ", $start + 10);
	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_panel_load_failures_dashboard_one_by_one_has_error_callback()
{
	$body = panel_load_failures_js_function(panel_load_failures_source('welcome.php'), 'pg_widgets_one_by_one');
	pg_assert_contains('error: function', $body);
	pg_assert_contains('pg_widget_failed(widget_id)', $body);
}

function test_panel_load_failures_dashboard_refused_answer_is_shown()
{
	$body = panel_load_failures_js_function(panel_load_failures_source('welcome.php'), 'pg_widget_apply');
	pg_assert_contains('pg_widget_failed(widget_id)', $body);
}

function test_panel_load_failures_dashboard_failure_only_replaces_skeleton()
{
	$source = panel_load_failures_source('welcome.php');
	$body = panel_load_failures_js_function($source, 'pg_widget_failed');
	pg_assert_true($body !== '', 'pg_widget_failed() is defined');
	pg_assert_contains('.card-body-placeholder', $body);
	pg_assert_contains('pg_widgets_request([widget_id])', $body);
	pg_assert_contains("json_encode(lang('Something went wrong. Please try again.'))", $source);
	pg_assert_contains("json_encode(lang('Try again'))", $source);
}

function test_panel_load_failures_seo_endpoint_refuses_before_redirect()
{
	$source = panel_load_failures_source('get_seo_analysis.php');
	$refusal = strpos($source, 'http_response_code(401)');
	$validate = strpos($source, '$user = validate_user();');
	pg_assert_true($refusal !== false, 'a missing session answers 401');
	pg_assert_true($validate !== false && $refusal < $validate, 'the 401 comes before validate_user()');
	pg_assert_contains('http_response_code(403)', $source);
	pg_assert_contains("hash_equals(", $source);
	pg_assert_false(strpos($source, 'validate_token_field()') !== false, 'validate_token_field() draws the site error page');
	pg_assert_contains("lang('Your session has expired. Please sign in again.')", $source);
}

function test_panel_load_failures_seo_fetches_check_response()
{
	$source = panel_load_failures_source('seo.php');
	pg_assert_same(2, substr_count($source, 'if (!response.ok || response.redirected)'));
	pg_assert_false(strpos($source, 'return response.text(); })') !== false, 'no answer is read without its status');

	$folders = panel_load_failures_source('view_folders.php');
	pg_assert_same(1, substr_count($folders, 'if (!response.ok || response.redirected)'));
	pg_assert_false(strpos($folders, 'return response.text(); })') !== false, 'no answer is read without its status');
}
