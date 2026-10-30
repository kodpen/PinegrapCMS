<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard figures that only a mouse tooltip or a row of bars showed carry
 * a text copy: the Sales Map writes a table of every place it colours, the
 * Daily Traffic table carries the day view's page views and busiest page,
 * and the seven-day bars of the Activity tiles and the Search Console card
 * say their week's total.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function dashboard_chart_text_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The bars stay a picture; the opt-in summary says the week's total.
function test_dashboard_chart_text_bars_summary()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

	$plain = pg_widget_bars(array(1, 2, 3, 4, 5, 6, 7));
	pg_assert_true(strpos($plain, 'visually-hidden') === false, 'no summary unless asked for');
	pg_assert_contains('<span class="pg-bars" aria-hidden="true">', $plain, 'the bars are decoration');

	// Eight days come in; only the last seven are drawn and counted.
	$summary = pg_widget_bars(array(100, 1, 2, 3, 4, 5, 6, 1000), '');
	pg_assert_contains('<span class="visually-hidden">' . lang(array('string' => 'in 7 days {var:1}', 'vars' => pg_format_number(1021, 0))) . '</span>', $summary, 'the week total as text');

	$labelled = pg_widget_bars(array(0, 0, 0, 0, 0, 0, 2), lang('Clicks'));
	pg_assert_contains('<span class="visually-hidden">' . h(lang('Clicks')) . ': ' . lang(array('string' => 'in 7 days {var:1}', 'vars' => pg_format_number(2, 0))) . '</span>', $labelled, 'a label names what was counted');
}

// A filter tile's count says it is today's, and its bars say the week.
function test_dashboard_chart_text_filter_tile()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

	$tile = pg_widget_filter(array(
		'key'        => 'orders',
		'label'      => 'Orders',
		'count'      => '3',
		'count_text' => lang(array('string' => '{var:1} today', 'vars' => '3')),
		'bars'       => array(1, 1, 1, 1, 1, 1, 3),
	));

	pg_assert_contains('<span class="pg-filter-count " aria-hidden="true">3</span><span class="visually-hidden">' . h(lang(array('string' => '{var:1} today', 'vars' => '3'))) . '</span>', $tile, 'the count is read with its unit');
	pg_assert_contains('<span class="visually-hidden">' . lang(array('string' => 'in 7 days {var:1}', 'vars' => pg_format_number(9, 0))) . '</span>', $tile, 'the week is read as a total');

	$lane = pg_widget_filter(array('key' => 'open', 'label' => 'Open', 'count' => '4'));
	pg_assert_contains('<span class="pg-filter-count ">4</span>', $lane, 'a tile without count_text keeps its plain count');
}

function test_dashboard_chart_text_activity_and_search_console()
{
	$activity = dashboard_chart_text_source('includes/dashboard/widgets/widget_7.php');
	pg_assert_contains("'count_text' => lang(array('string' => '{var:1} today', 'vars' => pg_format_number(\$today, 0))),", $activity, 'each kind says its count is today');
	pg_assert_contains("'count_text' => lang(array('string' => '{var:1} today', 'vars' => pg_format_number(\$today_total, 0))),", $activity, 'all activity says its count is today');

	$console = dashboard_chart_text_source('includes/dashboard/widgets/widget_31.php');
	pg_assert_contains("pg_widget_bars(\$week, lang('Clicks'))", $console, 'the week of clicks as text');

	$analytics = dashboard_chart_text_source('includes/dashboard/widgets/widget_32.php');
	pg_assert_contains('pg_widget_bars(array_values($week))', $analytics, 'Google Analytics keeps its own table and no summary');
}

function test_dashboard_chart_text_sales_map_table()
{
	$source = dashboard_chart_text_source('includes/dashboard/widgets/widget_1.php');

	pg_assert_contains('$sm_table = \'<table class="visually-hidden">\'', $source, 'a table beside each list');
	pg_assert_contains("'<caption>' . h(\$sm_view_label . ' · ' . \$sm_period['label']) . '</caption>'", $source, 'captioned with view and period');
	pg_assert_contains("h(lang(\$sm_is_world ? 'Country' : 'Region'))", $source, 'the place column');
	pg_assert_contains("h(lang(\$sm_is_world ? 'Regions' : 'Cities'))", $source, 'one level further down');
	pg_assert_contains("h(lang('Share of sales'))", $source, 'the share column');
	pg_assert_contains("data-smap-list=\"' . \$sm_slot . '\">'\n                    . \$sm_rows . \$sm_table . '</div>'", $source, 'inside the slot the period switch toggles');
	pg_assert_true(strpos($source, 'already written in the list beside') === false, 'no comment claims the list carries every figure');
}

function test_dashboard_chart_text_daily_traffic_table()
{
	$source = dashboard_chart_text_source('includes/dashboard/widgets/widget_5.php');

	pg_assert_contains("return array('name' => (string) \$row['name'], 'cnt' => (int) \$row['cnt']);", $source, 'hour page names unescaped');
	pg_assert_contains("json_encode(array_values(\$h_today_pages), JSON_HEX_TAG | JSON_HEX_AMP)", $source, 'today pages safe inside the script');
	pg_assert_contains("json_encode(array_values(\$h_yest_pages), JSON_HEX_TAG | JSON_HEX_AMP)", $source, 'yesterday pages safe inside the script');
	pg_assert_contains('if (s.views) { cell(hr, "th", s.label + " · " + L.views, "col"); }', $source, 'a page views column');
	pg_assert_contains('if (s.pages) { cell(hr, "th", s.label + " · " + L.top, "col"); }', $source, 'a busiest page column');
	pg_assert_contains('cell(tr, "td", pg ? pg.name + " · " + fmtN(pg.cnt) : "–");', $source, 'the busiest page with its count');
}
