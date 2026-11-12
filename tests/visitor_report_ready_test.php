<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor report's ready reports (includes/fn/ready_reports.php):
 * its entries, their addresses with the date changer's preset, the strip on
 * view_visitor_report.php and the visitor cards' See all links.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/visitors.php');

// Over time, by source, by landing page and by country; no date filter, the
// period is a preset of the date changer.
function test_visitor_ready_report_entries()
{
	$visitor = array_filter(pg_ready_reports(), function ($report) {
		return $report['screen'] === 'visitor_report';
	});

	pg_assert_same(array('visitors_over_time', 'visitors_by_source', 'visitors_by_page', 'visitors_by_country'), array_keys($visitor));

	foreach ($visitor as $key => $report) {
		pg_assert_same(array(), $report['filters'], $key . ' carries no filter');
		pg_assert_same(30, $report['period'], $key . ' 30 days');
		pg_assert_true(pg_ready_report_visitor_preset($report['period']) !== '', $key . ' period has a preset');
	}

	pg_assert_same(array('year', 'month', 'day'), array_column($visitor['visitors_over_time']['levels'], 'by'));
	pg_assert_same(array('alphabet', 'alphabet', 'alphabet'), array_column($visitor['visitors_over_time']['levels'], 'order'), 'time order');
	pg_assert_same(array(array('by' => 'referring_host_name', 'order' => 'number of visitors')), $visitor['visitors_by_source']['levels']);
	pg_assert_same(array(array('by' => 'landing_page_name', 'order' => 'number of visitors')), $visitor['visitors_by_page']['levels']);
	pg_assert_same(array(array('by' => 'country', 'order' => 'number of visitors')), $visitor['visitors_by_country']['levels']);

	// The first levels draw a line, bars, bars and the map.
	pg_assert_same('line', pg_report_view_chart_kind('year'));
	pg_assert_same('map', pg_report_view_chart_kind('country'));
	pg_assert_same('bars', pg_report_view_chart_kind('referring_host_name'));
	pg_assert_same('bars', pg_report_view_chart_kind('landing_page_name'));
}

// A visitor key opens on the visitor screen only, an order key only there.
function test_visitor_ready_report_requested()
{
	pg_assert_same('visitors_by_country', pg_ready_report_requested('visitors_by_country', 'visitor_report')['key']);
	pg_assert_same(null, pg_ready_report_requested('visitors_by_country', 'order_report'), 'not on the order report');
	pg_assert_same(null, pg_ready_report_requested('sales_by_region', 'visitor_report'), 'no order entry here');
	pg_assert_same(null, pg_ready_report_requested(array('visitors_by_page'), 'visitor_report'), 'an array');
}

// 1, 7, 30 and 90 days are the date changer's presets; other counts none.
function test_visitor_ready_report_preset()
{
	pg_assert_same('today', pg_ready_report_visitor_preset(1));
	pg_assert_same('7d', pg_ready_report_visitor_preset(7));
	pg_assert_same('30d', pg_ready_report_visitor_preset(30));
	pg_assert_same('90d', pg_ready_report_visitor_preset(90));
	pg_assert_same('', pg_ready_report_visitor_preset(14));
	pg_assert_same('', pg_ready_report_visitor_preset(0));

	foreach (array('today', '7d', '30d', '90d') as $preset) {
		pg_assert_true(in_array($preset, pg_visitor_report_preset_keys(), true), $preset . ' is a screen preset');
	}
}

function test_visitor_ready_report_url()
{
	pg_assert_same('view_visitor_report.php?ready=visitors_by_source&p=30d', pg_ready_report_url('visitors_by_source'), 'its own period');
	pg_assert_same('view_visitor_report.php?ready=visitors_over_time&p=today', pg_ready_report_url('visitors_over_time', 1));
	pg_assert_same('view_visitor_report.php?ready=visitors_by_country&p=7d', pg_ready_report_url('visitors_by_country', 7));
	pg_assert_same('view_visitor_report.php?ready=visitors_by_page', pg_ready_report_url('visitors_by_page', 14), 'no preset: the last range stays');
	pg_assert_same('view_order_report.php?ready=sales_by_region&days=7', pg_ready_report_url('sales_by_region', 7), 'the order report keeps days');
}

// Only the entries whose levels the screen offers; the open one marked.
function test_visitor_ready_report_strip()
{
	$strip = pg_ready_report_strip('visitor_report', array('year', 'month', 'day', 'referring_host_name', 'landing_page_name', 'country'), 'visitors_by_page');

	pg_assert_same(array('visitors_over_time', 'visitors_by_source', 'visitors_by_page', 'visitors_by_country'), array_column($strip, 'key'));
	pg_assert_same(array(false, false, true, false), array_column($strip, 'current'));
	pg_assert_same('view_visitor_report.php?ready=visitors_by_country&p=30d', $strip[3]['url']);

	pg_assert_same(array('visitors_by_country'), array_column(pg_ready_report_strip('visitor_report', array('country', 'billing_country')), 'key'));
}

// The screen reads ?ready= only for a new report, fills levels and name,
// keeps it open across the presets and prints the strip under the toolbar.
function test_visitor_report_screen_opens_a_ready_report()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor_report.php');

	pg_assert_contains("\$ready_report = isset(\$_GET['id']) ? null : pg_ready_report_requested(\$_GET['ready'] ?? null, 'visitor_report');", $source, 'read once, an id wins');
	pg_assert_contains("\$open_settings_at_load = ((isset(\$_GET['id']) == false) && (\$ready_report === null)) || \$returned_with_error;", $source, 'a ready report shows its results');
	pg_assert_contains("\$liveform->assign_field_value('summarize_by_' . \$level, \$ready_report['levels'][\$level - 1]['by'] ?? '');", $source, 'levels');
	pg_assert_contains("\$liveform->assign_field_value('order_by_' . \$level, \$ready_report['levels'][\$level - 1]['order'] ?? '');", $source, 'order by');
	pg_assert_contains("\$liveform->assign_field_value('name', lang(\$ready_report['title']));", $source, 'name for a save');
	pg_assert_contains("\$liveform->assign_field_value('summarize_by_1', 'year');", $source, 'the plain new report keeps year, month, day');
	pg_assert_contains("\$preset_query['ready'] = \$ready_report['key'];", $source, 'a preset keeps the ready report');
	pg_assert_contains("\$ready_strip = pg_ready_report_strip('visitor_report', ", $source);
	pg_assert_true(strpos($source, "    ' . \$output_edit_button . '\n    ' . \$output_ready_strip . '\n") !== false, 'the strip sits under the toolbar');
	pg_assert_contains('<form name="form" id="edit_form" action="view_visitor_report.php" method="post">', $source, 'a Save posts no ready key: a new saved report');
}

// Visitor Summaries opens visitors over time, the Visitor map visitors by
// country, On the site now visitors by landing page today.
function test_visitor_cards_see_all_open_ready_reports()
{
	$cards = PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/';

	pg_assert_contains("'url'           => pg_ready_report_url('visitors_over_time', count(\$range['dates'])),", (string) file_get_contents($cards . 'visitors.php'));
	pg_assert_contains("pg_ready_report_url('visitors_by_country', \$days[\$period])", (string) file_get_contents($cards . 'visitmap.php'));
	pg_assert_contains("'url'        => pg_ready_report_url('visitors_by_page', 1),", (string) file_get_contents($cards . 'engagement.php'));

	// The Visitor Summaries card counts 1, 7 or 30 days: each a preset.
	foreach (array(0, 1, 2) as $period) {
		$range = pg_dashboard_v2_visitor_dates($period, 2, mktime(12, 0, 0, 3, 15, 2026));
		pg_assert_true(pg_ready_report_visitor_preset(count($range['dates'])) !== '', 'period ' . $period);
	}
}
