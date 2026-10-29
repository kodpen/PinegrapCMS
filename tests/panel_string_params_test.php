<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel screens that read a one-value request parameter with a
 * bare (string) cast: an array (?q[]=x) turned into "Array", wrote an
 * "Array to string conversion" warning to the error log and was searched,
 * printed or looked up as the word "Array". (int) on an array gives 1, so an
 * array id opened record 1. These screens read the parameter only when it is
 * one value, so an array counts as not given.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Screen => request array and the parameters it reads as one value.
function panel_string_params_screens()
{
	return array(
		'translations'           => array('_GET', array('language', 'page_id', 'group', 'filter', 'q')),
		'settings_pane'          => array('_REQUEST', array('pane')),
		'workspace_template'     => array('_GET', array('id', 'copy', 'channel')),
		'view_regions'           => array('_GET', array('filter')),
		'google_taxonomy_action' => array('_REQUEST', array('q')),
		'add_order'              => array('_GET', array('request', 'q')),
		'add_system_style'       => array('_GET', array('start')),
	);
}

// The listed parameters are checked with is_string() and no longer read with
// the old unchecked forms: (string) $_GET['x'], (int) ($_GET['x'] ?? 0) and
// isset($_GET['x']) ? ... without the check.
function test_panel_string_params_checked_before_cast()
{
	foreach (panel_string_params_screens() as $screen => $request) {
		list($array, $parameters) = $request;
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen . '.php');

		foreach ($parameters as $parameter) {
			$read = preg_quote('$' . $array . "['" . $parameter . "']", '/');

			pg_assert_true((bool) preg_match('/is_string\(\s*' . $read . '/', $source), $screen . ': ' . $parameter . ' not checked with is_string()');
			pg_assert_false((bool) preg_match('/\(string\)\s*\(?\s*' . $read . '/', $source), $screen . ': (string) cast of ' . $parameter);
			pg_assert_false((bool) preg_match('/\(int\)\s*\(\s*' . $read . '\s*\?\?/', $source), $screen . ': (int) cast of ' . $parameter . ' ?? 0');
			pg_assert_false((bool) preg_match('/isset\(\s*' . $read . '\s*\)\s*\?/', $source), $screen . ': isset(' . $parameter . ') ? without is_string()');
		}
	}
}

// The order report sets the date changer and the summary columns before the
// branches that fill them, so a report filtered on the order date or with
// fewer than three summary levels writes no "Undefined variable" warning.
function test_panel_string_params_order_report_defaults()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_order_report.php');

	foreach (array('$output_date_changer', '$sql_summarize_by_1', '$sql_summarize_by_2', '$sql_summarize_by_3') as $variable) {
		$default = strpos($source, $variable . " = '';");

		pg_assert_true($default !== false, 'view_order_report: no default for ' . $variable);
		pg_assert_same(strpos($source, $variable), $default, 'view_order_report: ' . $variable . ' used before its default');
	}
}
