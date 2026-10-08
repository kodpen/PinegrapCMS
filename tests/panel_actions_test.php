<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel action table in includes/panel/actions.php: every row
 * points at a handler that exists, its flags agree with the handler's source
 * and with api.php's general gate, and api.php no longer carries a case for
 * an action the table answers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

function pg_test_panel_api_source()
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/api.php');
}

// The actions api.php's general gate lets through to their own checks: the
// `($action != '...')` terms of the condition in front of switch ($action).
// The strpos() prefix rules (chat_, site_chat_, ws_) are not table rows.
function pg_test_panel_gate_exemptions()
{
	$source = pg_test_panel_api_source();
	$start = strpos($source, "if (\n    (\$action != ");
	$end = strpos($source, "\n) {", (int) $start);

	if (($start === false) || ($end === false)) {
		return null;
	}

	preg_match_all("/\\(\\\$action != '([a-z_]+)'\\)/", substr($source, $start, $end - $start), $matches);

	return $matches[1];
}

// Each row names a file under includes/panel/ that defines its handler.
function test_panel_actions_every_row_has_file_and_handler()
{
	foreach (pg_panel_actions() as $action => $entry) {
		$file = PG_FUNCTIONS_DIR . '/includes/panel/' . $entry['file'];

		if (!pg_assert_true(is_file($file), 'file ' . $action)) {
			continue;
		}

		require_once($file);

		pg_assert_true(function_exists($entry['handler']), 'handler ' . $action);
	}
}

// exempt, token and write are booleans, never truthy strings.
function test_panel_actions_flags_are_booleans()
{
	foreach (pg_panel_actions() as $action => $entry) {
		foreach (array('exempt', 'token', 'write') as $flag) {
			pg_assert_true(is_bool($entry[$flag]), $action . ' ' . $flag);
		}
	}
}

// A row that says token => true has a handler that calls validate_token().
function test_panel_actions_token_flag_matches_handler_source()
{
	foreach (pg_panel_actions() as $action => $entry) {
		require_once(PG_FUNCTIONS_DIR . '/includes/panel/' . $entry['file']);

		if (!function_exists($entry['handler'])) {
			continue;
		}

		$function = new ReflectionFunction($entry['handler']);
		$lines = file($function->getFileName());
		$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

		pg_assert_same($entry['token'], strpos($source, 'validate_token(') !== false, $action);
	}
}

// Nothing the general gate waves through may write without the form token.
function test_panel_actions_exempt_writers_check_the_token()
{
	foreach (pg_panel_actions() as $action => $entry) {
		if ($entry['write'] && $entry['exempt']) {
			pg_assert_true($entry['token'], $action);
		}
	}
}

// An action the table answers has no case left in api.php's switch.
function test_panel_actions_have_no_case_left_in_api()
{
	$source = pg_test_panel_api_source();

	foreach (array_keys(pg_panel_actions()) as $action) {
		pg_assert_false(strpos($source, "case '" . $action . "':") !== false, $action);
	}
}

// exempt agrees with the general gate's exemption list in api.php.
function test_panel_actions_exempt_matches_api_gate()
{
	$exemptions = pg_test_panel_gate_exemptions();

	if (!pg_assert_true(is_array($exemptions) && (count($exemptions) > 0), 'gate condition found')) {
		return;
	}

	foreach (pg_panel_actions() as $action => $entry) {
		pg_assert_same(in_array($action, $exemptions, true), $entry['exempt'], $action);
	}
}

// Anything that is not a listed action name has no row.
function test_panel_action_unknown_or_not_a_string_is_null()
{
	pg_assert_same(null, pg_panel_action('nope'), 'unknown');
	pg_assert_same(null, pg_panel_action(array()), 'array');
	pg_assert_same(null, pg_panel_action(null), 'null');
	pg_assert_same('pg_panel_backend_search', pg_panel_action('backend_search')['handler'], 'known');
}

// An unlisted action is handed back to api.php's switch untouched.
function test_panel_dispatch_unknown_action_returns_false()
{
	pg_assert_same(false, pg_panel_dispatch('nope', array()));
}
