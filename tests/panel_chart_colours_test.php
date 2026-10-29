<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for panel charts drawn with Chart.js: without colour options
 * Chart.js draws #666 labels and a black 10% grid, unreadable on the dark
 * card. Each chart resolves its ink from --pg-fg-2 through a probe element
 * (canvas cannot parse the light-dark() text a custom property returns) and
 * passes it to the legend and every scale, on a grey grid that reads in both
 * themes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_chart_colours_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function panel_chart_colours_assert_inked($path, $scales)
{
	$source = panel_chart_colours_source($path);

	pg_assert_contains('probe.style.color = "var(--pg-fg-2)";', $source, $path . ' probes the panel ink');
	pg_assert_contains('var ink = getComputedStyle(probe).color;', $source, $path . ' reads the computed ink');
	pg_assert_contains('var grid = "rgba(128,128,128,.2)";', $source, $path . ' uses a theme-neutral grid');
	pg_assert_contains('legend: { position: "bottom", labels: { color: ink', $source, $path . ' legend ink');
	pg_assert_same($scales, substr_count($source, 'ticks: { color: ink'), $path . ' every scale has ink');
	pg_assert_same($scales, substr_count($source, 'grid: { color: grid'), $path . ' every scale has a grid colour');
	pg_assert_false(
		(bool) preg_match('/getPropertyValue\(\s*["\']--pg-/', $source),
		$path . ' hands no --pg-* custom property text to Chart.js');
}

function test_panel_chart_colours_erp_profit()
{
	panel_chart_colours_assert_inked('erp_profit.php', 2);
}

function test_panel_chart_colours_search_console()
{
	panel_chart_colours_assert_inked('search_console.php', 3);
}
