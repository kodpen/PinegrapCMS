<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for charts whose figures were drawn only on a canvas: the
 * orders list's Top 5 products bar chart names every product with its
 * quantity, the Search Console day chart carries a table of its days, the
 * dashboard Daily Traffic chart keeps a table of the series on show, and
 * the Sales Map's Filter button sits outside the element named as a picture.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function chart_text_equivalents_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_chart_text_equivalents_top_products()
{
	$source = chart_text_equivalents_source('view_orders.php');

	pg_assert_contains("\$top_summary = lang('Top 5 products');", $source, 'summary starts with the chart title');
	pg_assert_contains("\$top_row['label'] . ': ' . pg_format_number(\$top_row['quantity'], 0)", $source, 'each product with its quantity');
	pg_assert_contains('<canvas id="pg_orders_top" role="img" aria-label="\' . h($top_summary) . \'">', $source, 'canvas named with the figures');
}

function test_chart_text_equivalents_search_console_days()
{
	$source = chart_text_equivalents_source('search_console.php');

	pg_assert_contains('<table class="visually-hidden" id="search_console_days">', $source, 'a table of the days beside the chart');
	pg_assert_contains("<caption>' . h(lang('Clicks and impressions by day')) . '</caption>", $source, 'the table is captioned');
	pg_assert_contains("<th scope=\"row\">' . h(date(get_date_format_code() . '/Y', strtotime(\$day . ' 12:00:00'))) . '</th>", $source, 'each day as a full date');
	pg_assert_contains('<canvas id="search_console_chart" aria-hidden="true"></canvas>', $source, 'the canvas is not read twice');
}

function test_chart_text_equivalents_daily_traffic()
{
	$source = chart_text_equivalents_source('includes/dashboard/widgets/widget_5.php');

	pg_assert_contains('<canvas id="vs5_canvas" aria-hidden="true"></canvas><table class="visually-hidden" id="vs5_table"></table>', $source, 'canvas and its table');
	pg_assert_contains('function table() {', $source, 'the table is rebuilt from the series on show');
	pg_assert_contains('build(); head(); table();', $source, 'rebuilt on every period switch');
	pg_assert_contains("axis:L.hour", $source, 'day view rows are hours');
	pg_assert_contains("axis:L.month", $source, 'year view rows are months');
	pg_assert_contains('cell(tr, "td", (v === null || v === undefined) ? "–" : fmtN(v));', $source, 'figures in the panel number format');
}

function test_chart_text_equivalents_sales_map_menu()
{
	$source = chart_text_equivalents_source('includes/dashboard/widgets/widget_1.php');

	pg_assert_true(strpos($source, 'id="pg_smap_canvas" role="img"') === false, 'the box holding the menu is no picture');
	pg_assert_contains('layer.setAttribute("role", "img");', $source, 'each map layer is the picture');
	pg_assert_contains('layer.setAttribute("aria-label", data.label);', $source, 'and carries its name');
	pg_assert_contains('<div class="pg-smap-tip" id="pg_smap_tip" aria-hidden="true"></div>', $source, 'the mouse tooltip repeats the list');
	pg_assert_contains('<i class="bi bi-sliders" aria-hidden="true"></i>', $source, 'the menu icon is decoration');
}
