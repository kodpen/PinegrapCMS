<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the text of Marketplace product matching, the Marketplaces
 * account screen, API Documentation, Update Search Index, the dashboard cards
 * (with their loading skeleton), the calendar card, Sales Map and the bell
 * panel is sized in rem, so it grows with the browser's text size like the
 * rest of the panel, and that the columns and boxes holding that text grow
 * with it.
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

// The CSS rule that starts with $selector followed by " {", up to its "}".
function panel_text_size_test_rule($css, $selector)
{
	$start = strpos($css, "\n" . $selector . " {");
	pg_assert_true($start !== false, $selector . ' rule is there');

	return substr($css, (int) $start, (int) strpos($css, '}', (int) $start) - (int) $start);
}

function test_panel_text_size_dashboard_cards_in_rem()
{
	$css = panel_text_size_test_source('assets/css/backend.src.css');
	$start = strpos($css, '/* Dashboard list row');
	$end = strpos($css, '/* ══ Dashboard welcome card');
	pg_assert_true($start !== false && $end !== false && $end > $start, 'the dashboard block is there');
	$block = substr($css, (int) $start, (int) $end - (int) $start);

	// Icon glyphs keep their px size; every text size in the block is rem.
	$icons = array(
		'.pg-row-go', '.pg-row-sm .pg-row-go', '.pg-eg-stat .bi', '.pg-head-dir',
		'.pg-check > i', '.pg-check-caret', '.pg-health-dot', '.pg-job-icon',
		'.pg-job-caret', '.pg-job-btn i', '.pg-widget-empty > .bi', '.pg-filter-ico',
		'.pg-act > .pg-filters .pg-filter-ico', '.pg-lanes .pg-filter-ico',
	);
	preg_match_all('/([^{}]+)\{[^{}]*font-size:\s*[0-9.]+px/', $block, $matches);
	foreach ($matches[1] as $selector) {
		$selector = trim(preg_replace('/\/\*.*?\*\//s', '', $selector));
		pg_assert_true(in_array($selector, $icons, true), $selector . ' is an icon rule, the only kind kept in px');
	}
	pg_assert_true(preg_match_all('/font-size:\s*[0-9.]+rem/', $block) > 40, 'the dashboard block sizes its text in rem');

	// The badge tile holds initials, a currency sign or the rank under overflow:hidden.
	$badge = panel_text_size_test_rule($block, '.pg-row-badge');
	pg_assert_contains('width: 2.125rem;', $badge);
	pg_assert_contains('height: 2.125rem;', $badge);
	pg_assert_contains('font-size: 0.875rem;', $badge);
	pg_assert_contains('font-size: 0.75rem;', panel_text_size_test_rule($block, '.pg-rank'));
	pg_assert_contains('max-width: 12.5rem;', panel_text_size_test_rule($block, '.pg-health-dial'));
}

function test_panel_text_size_calendar_card_bell_and_sales_map_in_rem()
{
	$css = panel_text_size_test_source('assets/css/backend.src.css');

	preg_match_all('/\n\.widget table\.monthly_calendar[^{]*\{[^}]*\}/', $css, $calendar);
	pg_assert_true(count($calendar[0]) >= 5, 'the calendar card rules are there');
	foreach ($calendar[0] as $rule) {
		pg_assert_same(0, preg_match('/(?:font-size|width|height|line-height):\s*[0-9.]+px/', $rule), 'no px text box in ' . trim(strtok($rule, '{')));
	}
	pg_assert_contains('font-size: 1.25rem;', panel_text_size_test_rule($css, '.widget table.monthly_calendar .calendar-day-wrapper'));

	$tip = panel_text_size_test_rule($css, '.pg-smap-tip');
	pg_assert_contains('font-size: 0.6875rem;', $tip);
	preg_match_all('/\n\.pg-smap-(?:tip|menu)[^{]*\{[^}]*\}/', $css, $map);
	pg_assert_true(count($map[0]) >= 3, 'the Sales Map tooltip and menu rules are there');
	foreach ($map[0] as $rule) {
		pg_assert_same(0, preg_match('/font-size:\s*[0-9.]+px/', $rule), 'no px font-size in ' . trim(strtok($rule, '{')));
	}

	$description = panel_text_size_test_rule($css, '#notifications .list-group-item .notification-description');
	pg_assert_contains('line-height: 0.9375rem;', $description);
	pg_assert_contains('font-size: 0.8125rem;', $description);
	pg_assert_contains('width: 21.875rem;', panel_text_size_test_rule($css, '#notifications'));
}

function test_panel_text_size_dashboard_skeleton_in_rem()
{
	$source = panel_text_size_test_source('welcome.php');

	pg_assert_true(strpos($source, "\$height . 'px") === false, 'skeleton strips are not sized in px');
	pg_assert_true(strpos($source, "'px;height:'") === false, 'skeleton tile icons are not sized in px');
	pg_assert_contains('<span class="placeholder rounded-4 flex-shrink-0" style="width:2.125rem;height:2.125rem"></span>', $source);
	pg_assert_contains('<span class="placeholder rounded-4 flex-shrink-0" style="width:1.375rem;height:1.375rem"></span>', $source);
	pg_assert_contains('<div class="pg-ec-fact" style="height:2.26875rem">', $source);
	pg_assert_contains('style="min-height:8.75rem"', $source);
}
