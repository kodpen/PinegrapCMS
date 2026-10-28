<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the page bar's CSS (pg_page_action_bar() in
 * includes/fn/output.php): it is printed in the editor toolbar under the
 * panel's stylesheet, so its colours come from the --pg-* tokens. Bootstrap 6
 * no longer defines the Bootstrap 5 variables it used to read, and a rule
 * reading one falls back to the browser default in every theme.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of pg_page_action_bar(), up to the next function.
function page_action_bar_theme_test_source()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/output.php');
	$start = strpos($source, 'function pg_page_action_bar(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

function test_page_action_bar_css_reads_panel_tokens()
{
	$source = page_action_bar_theme_test_source();

	pg_assert_true($source !== '', 'pg_page_action_bar() is found');

	foreach (array('--bs-border-color', '--bs-body-color', '--bs-tertiary-bg', '--bs-secondary-color', '--bs-border-color-translucent') as $name) {
		pg_assert_same(0, preg_match('/var\(\s*' . preg_quote($name, '/') . '\s*[,)]/', $source), 'the page bar reads no ' . $name);
	}

	pg_assert_same(0, preg_match('/var\(\s*--bs-/', $source), 'the page bar reads no Bootstrap variable');

	foreach (array('--pg-border-color', '--pg-fg-body', '--pg-bg-1', '--pg-fg-2', '--pg-border-color-translucent') as $name) {
		pg_assert_true(strpos($source, 'var(' . $name . ')') !== false, 'the page bar reads ' . $name);
	}
}
