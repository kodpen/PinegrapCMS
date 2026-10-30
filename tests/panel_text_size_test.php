<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the text of Marketplace product matching, the Marketplaces
 * account screen, API Documentation and Update Search Index is sized in rem,
 * so it grows with the browser's text size like the rest of the panel, and
 * that the columns holding that text grow with it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_text_size_test_source($file)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	pg_assert_true($source !== false, $file . ' is readable');

	return (string) $source;
}

// The screen's own <style>@layer custom { ... }</style> block.
function panel_text_size_test_style($file)
{
	$source = panel_text_size_test_source($file);
	$start = strpos($source, '<style>@layer custom {');
	pg_assert_true($start !== false, $file . ' has its style block');

	return substr($source, (int) $start, (int) strpos($source, '</style>', (int) $start) - (int) $start);
}

// The stylesheet returned by api_console_css().
function panel_text_size_test_console_css()
{
	$source = panel_text_size_test_source('includes/api/console_view.php');
	$start = strpos($source, 'function api_console_css()');
	pg_assert_true($start !== false, 'api_console_css() is there');
	$end = strpos($source, "\n';\n", (int) $start);

	return substr($source, (int) $start, (int) $end - (int) $start);
}

function panel_text_size_test_no_px_font_size($css, $label)
{
	pg_assert_same(0, preg_match_all('/font-size:\s*[0-9.]+px/', $css), $label . ' has no px font-size');
	pg_assert_true(preg_match_all('/font-size:\s*[0-9.]+rem/', $css) > 0, $label . ' sizes its text in rem');
}

function test_panel_text_size_marketplace_products_in_rem()
{
	$style = panel_text_size_test_style('marketplace_products.php');

	panel_text_size_test_no_px_font_size($style, 'marketplace_products.php');
	pg_assert_contains('.mpp-stat b { display: block; font-size: 1.625rem;', $style);
	pg_assert_contains('grid-template-columns: 22px minmax(0, 1fr) 7rem minmax(0, 1fr) 16.25rem;', $style);
	pg_assert_contains('grid-template-columns: 22px minmax(0, 1fr) 6rem minmax(0, 1fr); }', $style);
}

function test_panel_text_size_marketplace_settings_in_rem()
{
	$source = panel_text_size_test_source('marketplace_settings.php');

	panel_text_size_test_no_px_font_size(panel_text_size_test_style('marketplace_settings.php'), 'marketplace_settings.php');
	pg_assert_true(strpos($source, 'font-size:34px') === false, 'the empty state icon is not in px');
	pg_assert_contains('font-size:2.125rem', $source);
}

function test_panel_text_size_api_console_in_rem()
{
	$css = panel_text_size_test_console_css();

	panel_text_size_test_no_px_font_size($css, 'api_console_css()');
	pg_assert_contains('.doc-params table td:first-child { width: 10.625rem; }', $css);
	pg_assert_contains('.doc-params table td:nth-child(2) { width: 5.625rem; }', $css);
	pg_assert_contains('max-height: 380px', $css);
}

function test_panel_text_size_search_index_rows_in_rem()
{
	$source = panel_text_size_test_source('update_search_index.php');

	pg_assert_same(0, preg_match_all('/font-size:\s*[0-9.]+px/', $source), 'no px font-size in the index rows');
	pg_assert_true(strpos($source, 'min-width:22px') === false, 'the counter width is not in px');
	pg_assert_same(5, substr_count($source, 'style="min-width:1.375rem;font-size:0.625rem"'), 'five counters in rem');
	pg_assert_same(5, substr_count($source, 'style="font-size:0.5625rem"'), 'five type badges in rem');
}
