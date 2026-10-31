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

function test_panel_chart_colours_view_orders()
{
	// The Orders statistics strip read --bs-secondary-color, which nothing
	// in the panel defines, so its text was always the #6c757d fallback.
	$source = panel_chart_colours_source('view_orders.php');

	pg_assert_false(strpos($source, '--bs-secondary-color') !== false, 'view_orders.php reads an undefined variable for the chart ink');
	pg_assert_contains('probe.style.color = "var(--pg-fg-2)";', $source, 'view_orders.php probes the panel ink');
	pg_assert_contains('var ink = getComputedStyle(probe).color;', $source, 'view_orders.php reads the computed ink');
	pg_assert_same(2, substr_count($source, 'ticks: { color: ink'), 'view_orders.php: both bar scales have ink');
	pg_assert_contains('legend: { position: "right", labels: { color: ink', $source, 'view_orders.php legend ink');
	pg_assert_false(
		(bool) preg_match('/getPropertyValue\(\s*["\']--pg-/', $source),
		'view_orders.php hands no --pg-* custom property text to Chart.js');
}

function panel_chart_colours_assert_tone($source, $path, $token, $name)
{
	// The pastel --bs-primary/--bs-success fills read about 2:1 on the light
	// card; a line or bar edge takes the theme's -ink tone, resolved through
	// the same probe because the token holds light and dark values.
	pg_assert_contains('probe.style.color = "var(' . $token . ')";' . "\n", $source, $path . ' probes ' . $token);
	pg_assert_contains('var ' . $name . ' = getComputedStyle(probe).color;', $source, $path . ' reads ' . $token);
}

function test_panel_chart_colours_search_console_clicks_ink()
{
	$path = 'search_console.php';
	$source = panel_chart_colours_source($path);

	panel_chart_colours_assert_tone($source, $path, '--pg-primary-ink', 'primaryInk');
	pg_assert_true((bool) preg_match('/label: [^\n]*Clicks[^\n]*borderColor: primaryInk, backgroundColor: primaryInk/', $source), 'the Clicks line is drawn in the primary ink');
	pg_assert_false(strpos($source, 'color("--bs-primary"') !== false, 'the Clicks line no longer reads the pastel fill');
}

function test_panel_chart_colours_erp_profit_series()
{
	$path = 'erp_profit.php';
	$source = panel_chart_colours_source($path);

	panel_chart_colours_assert_tone($source, $path, '--pg-primary-ink', 'primaryInk');
	panel_chart_colours_assert_tone($source, $path, '--pg-secondary-ink', 'secondaryInk');
	panel_chart_colours_assert_tone($source, $path, '--pg-success-ink', 'successInk');
	pg_assert_true((bool) preg_match('/Net sales[^\n]*borderColor: primaryInk, borderWidth: 2/', $source), 'Net sales bars have an ink edge');
	// Costs are outlined, so the two bar series differ by fill, not by tint.
	pg_assert_true((bool) preg_match('/Costs and expenses[^\n]*backgroundColor: "transparent", borderColor: secondaryInk, borderWidth: 2/', $source), 'Costs and expenses bars are outlined in ink');
	pg_assert_true((bool) preg_match('/Net profit[^\n]*borderColor: successInk, backgroundColor: successInk/', $source), 'the Net profit line is drawn in the success ink');
	pg_assert_false(strpos($source, 'color("--bs-success"') !== false, 'the Net profit line no longer reads the pastel fill');
	pg_assert_false(strpos($source, 'color("--bs-secondary-bg"') !== false, 'Costs and expenses no longer take a grey tint of the Net sales fill');
	// The month table under the chart carries the figures; the canvas only
	// repeated the card heading as its name.
	pg_assert_contains('<canvas id="erp_profit_chart" aria-hidden="true"></canvas>', $source, 'the ERP canvas is hidden from readers');
}
