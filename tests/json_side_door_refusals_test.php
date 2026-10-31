<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pins, by source, that two JSON requests of manager screens answer an ended
 * session, a missing right or a stale token in JSON before the HTML helpers
 * run: the API documentation's Try it credential (api_docs.php,
 * test_credential) and the Database Engine visitor cleanup step
 * (database_engine.php, ajax=1). validate_user() would redirect to the sign-in
 * page, which fetch follows as a 200, and validate_token_field() prints an
 * HTML error page; either one reached the scripts as
 * "Unexpected token '<'".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function json_side_door_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The part of a file before its first validate_user() call.
function json_side_door_before_validate_user($source)
{
	$at = strpos($source, '$user = validate_user();');
	return ($at === false) ? '' : substr($source, 0, $at);
}

function test_json_side_door_api_docs_refuses_in_json_before_validate_user()
{
	$head = json_side_door_before_validate_user(json_side_door_source('api_docs.php'));
	pg_assert_contains("isset(\$_POST['test_credential'])", $head);
	pg_assert_contains('USER_LOGGED_IN', $head);
	pg_assert_contains("lang('Your session has expired. Reload the page and sign in again.')", $head);
	pg_assert_contains('USER_ROLE', $head);
	pg_assert_contains("lang('Access denied.')", $head);
	pg_assert_contains('hash_equals(', $head);
	pg_assert_contains("lang('Your session expired. Reload the page and try again.')", $head);
	pg_assert_contains('401', $head);
	pg_assert_contains('403', $head);
}

function test_json_side_door_api_docs_credential_branch_skips_html_token_check()
{
	$source = json_side_door_source('api_docs.php');
	$start = strpos($source, "// A credential for trying a call");
	pg_assert_true($start !== false, 'the credential branch exists');
	$branch = substr($source, (int) $start, 1200);
	pg_assert_false(strpos($branch, 'validate_token_field()') !== false, 'the credential branch prints no HTML token page');
}

function test_json_side_door_api_docs_get_credential_rejects_refusals()
{
	$source = json_side_door_source('api_docs.php');
	$start = strpos($source, 'function getCredential(');
	pg_assert_true($start !== false, 'getCredential() exists');
	$body = substr($source, (int) $start, 1400);
	pg_assert_contains('r.redirected', $body);
	pg_assert_contains('!r.ok', $body);
	pg_assert_contains('!c.key', $body);
	pg_assert_contains('throw new Error(', $body);
}

function test_json_side_door_database_engine_refuses_in_json_before_validate_user()
{
	$head = json_side_door_before_validate_user(json_side_door_source('database_engine.php'));
	pg_assert_contains("\$_POST['ajax'] === '1'", $head);
	pg_assert_contains('USER_LOGGED_IN', $head);
	pg_assert_contains("lang('Your session has expired. Reload the page and sign in again.')", $head);
	pg_assert_contains('USER_ROLE', $head);
	pg_assert_contains("lang('Access denied.')", $head);
	pg_assert_contains('hash_equals(', $head);
	pg_assert_contains("lang('Your session expired. Reload the page and try again.')", $head);
	pg_assert_contains("'state' => 'stopped'", $head);
}

function test_json_side_door_database_engine_step_loop_reads_refusal()
{
	$source = json_side_door_source('database_engine.php');
	$start = strpos($source, 'var step = function () {');
	pg_assert_true($start !== false, 'the step loop exists');
	$body = substr($source, (int) $start, 2200);
	pg_assert_contains('response.redirected', $body);
	pg_assert_contains('sessionEnded', $body);
	pg_assert_contains('return response.json().then(function (answer) {', $body);
	pg_assert_contains('answer.text', $body);
}
