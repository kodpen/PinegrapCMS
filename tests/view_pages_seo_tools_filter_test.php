<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pages list keeping its view filter across the Refresh SEO
 * Scores and Analyze HTML Structure tools: both Tools menu links carry the
 * filter, and every exit of both handlers returns to the filtered view.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function view_pages_seo_tools_filter_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_pages.php');
}

function view_pages_seo_tools_filter_test_block($source, $start_marker, $end_marker)
{
	$start = strpos($source, $start_marker);
	pg_assert_true($start !== false, $start_marker . ' exists');
	$end = strpos($source, $end_marker, $start + 1);
	pg_assert_true($end !== false, $end_marker . ' follows');

	return substr($source, $start, $end - $start);
}

function test_view_pages_seo_tools_filter_return_url_is_built_before_the_handlers()
{
	$source = view_pages_seo_tools_filter_test_source();
	$return = strpos($source, '$seo_return = ');
	$handler = strpos($source, "if (isset(\$_GET['recalculate_seo'])");

	pg_assert_true($return !== false, 'a return URL is built');
	pg_assert_true($handler !== false && $return < $handler, 'the return URL is built before the handlers run');
	pg_assert_contains("preg_match('/^[a-z_]+\$/', \$_GET['filter'])", $source, 'only a plain filter name is carried');
	pg_assert_contains("'/view_pages.php?filter=' . urlencode(\$_GET['filter'])", $source, 'the filter is URL-encoded into the return URL');
}

function test_view_pages_seo_tools_filter_every_handler_exit_returns_to_the_view()
{
	$source = view_pages_seo_tools_filter_test_source();
	$handlers = view_pages_seo_tools_filter_test_block($source, "if (isset(\$_GET['recalculate_seo'])", '$output_clear_button = ');

	pg_assert_same(2, substr_count($handlers, 'go($seo_return);'), 'both exits go to the filtered view');
	pg_assert_same(0, substr_count($handlers, "go(PATH . SOFTWARE_DIRECTORY . '/view_pages.php');"), 'no exit drops the filter');
	pg_assert_same(1, substr_count($handlers, 'validate_token_field();'), 'the handler still checks the token');
}

function test_view_pages_seo_tools_filter_structure_run_returns_to_the_view()
{
	// Analyze HTML Structure runs in audit_run.php (anonymous render); it
	// carries the list's filter back the same way.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/audit_run.php');

	pg_assert_contains("preg_match('/^[a-z_]+\$/', \$_GET['filter'])", $source, 'only a plain filter name is carried');
	pg_assert_contains("'?filter=' . urlencode(\$_GET['filter'])", $source, 'the filter is URL-encoded into the return URL');
	pg_assert_same(0, substr_count($source, "go(PATH . SOFTWARE_DIRECTORY . '/view_pages.php');"), 'no exit drops the filter');
}

function test_view_pages_seo_tools_filter_menu_links_carry_the_filter()
{
	$source = view_pages_seo_tools_filter_test_source();

	pg_assert_contains("href=\"view_pages.php?recalculate_seo=1' . \$output_filter_for_links . get_token_query_string_field() . '\"", $source, 'Refresh SEO Scores keeps the filter');
	pg_assert_contains("href=\"audit_run.php?pass=structure' . \$output_filter_for_links . get_token_query_string_field() . '\"", $source, 'Analyze HTML Structure keeps the filter');
}
