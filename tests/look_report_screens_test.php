<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the report screens in look A: the order, visitor and
 * shipping reports and the ERP tax report take the report page width, and
 * the look's block draws their parts from the reference sheet's numbers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_report_screens_test_source($path)
{
	$file = dirname(__DIR__) . '/pinegrap/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

function look_report_screens_test_block()
{
	$css = look_report_screens_test_source('assets/css/backend.src.css');
	$start = strpos($css, 'Look A: report screens.');

	return ($start === false) ? '' : substr($css, $start);
}

function look_report_screens_test_screens()
{
	return array('view_order_report.php', 'view_visitor_report.php', 'view_shipping_report.php', 'erp_vat_report.php');
}

function test_report_screens_take_the_report_width()
{
	foreach (look_report_screens_test_screens() as $screen) {
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-report\\b/", look_report_screens_test_source($screen)), $screen . ' takes the report width');
	}

	$block = look_report_screens_test_block();

	pg_assert_true($block !== '', 'the block is found');
	pg_assert_contains("body.pg-measure-report #content {\n  width: fit-content;\n  min-width: min(100%, var(--pg-measure-list-min) + 2rem);\n  max-width: 100%;\n  margin-inline: auto;\n}", $block, 'as wide as a list, centred');
}

// Every screen draws its table in a look card with the report table, and
// none keeps the old inline view styles or the dashboard's card class on
// the view.
function test_report_screens_draw_the_table_in_a_frame()
{
	foreach (look_report_screens_test_screens() as $screen) {
		$source = look_report_screens_test_source($screen);

		pg_assert_contains('<section class="pg-card pg-report-frame"', $source, $screen . ': the table sits in a frame');
		pg_assert_contains('pg-report-table', $source, $screen . ': a report table');
		pg_assert_false(strpos($source, 'class="pg-dash pg-report-view"') !== false, $screen . ': the view is not a dashboard card');
		pg_assert_false(strpos($source, '.pg-report-view {') !== false, $screen . ': no inline view styles');
		pg_assert_false(strpos($source, 'table-striped') !== false, $screen . ': no striped table');
	}
}

// The order and visitor reports: the report's name as the screen title in
// the bar, an Unsaved pill and Save only for a report not yet saved, the
// ready reports and date presets as segmented rows, the settings drawer in
// sections.
function test_report_screens_head_and_drawer()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php') as $screen) {
		$source = look_report_screens_test_source($screen);

		pg_assert_contains("'grow' => '<h2 class=\"pg-title-screen\">' . h(\$output_", $source, $screen . ': the name heads the bar');
		pg_assert_contains("'<span class=\"pg-pill pg-tone-amber\">' . h(lang('Unsaved')) . '</span>'", $source, $screen . ': an unsaved report says so');
		pg_assert_contains("isset(\$_GET['id']) ? '' : '<button type=\"submit\" form=\"edit_form\" name=\"submit_save\"", $source, $screen . ': Save in the bar only before the first save');
		pg_assert_contains("array('class' => 'pg-report-head')", $source, $screen . ': the head bar');
		pg_assert_contains('<nav class="pg-report-strip"', $source, $screen . ': the ready reports');
		pg_assert_contains('<div class="pg-report-tools">', $source, $screen . ': the date row');
		pg_assert_contains('<span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>', $source, $screen . ': the period as text');
		pg_assert_same(4, substr_count($source, '<section class="pg-report-section"'), $screen . ': report, levels, filters, how it works');
		pg_assert_contains('<h3 class="pg-report-section-title">\' . h(lang(\'Summarize by\')) . \' <em>· \' . h(lang(\'up to three levels\')) . \'</em></h3>', $source, $screen . ': the level limit beside its title');
	}
}

// The look's block carries the reference sheet's numbers.
function test_report_screens_block_numbers()
{
	$block = look_report_screens_test_block();

	pg_assert_contains(".pg-report-fig-value {\n  margin: 7px 0 5px;\n  overflow: hidden;\n  font-size: 26px;", $block, 'a 26 px figure');
	pg_assert_contains(".pg-report-table th,\n.pg-report-table td {\n  height: 40px;\n  padding: 0 16px;", $block, '40 px rows');
	pg_assert_contains(".pg-report-table tr.pg-report-total > * {\n  height: 46px;", $block, 'a 46 px total row');
	pg_assert_contains("#report_settings {\n  --pg-drawer-width: 430px;", $block, 'a 430 px drawer');
	pg_assert_contains('.pg-report-plot {', $block, 'the line chart');
	pg_assert_contains('.pg-report-bars {', $block, 'the bars');
	pg_assert_contains('.pg-report-view .pg-report-map {', $block, 'the map in the accent');
}

// A settings card's switch: Bootstrap subtracts the border width from a
// length in calc(), so a bare 0 drops the knob; the width carries a unit.
function test_settings_card_switch_keeps_its_knob()
{
	$css = look_report_screens_test_source('assets/css/backend.src.css');

	pg_assert_contains("  --bs-switch-border-width: 0px;\n", $css, 'the width with a unit');
	pg_assert_false(strpos($css, '--bs-switch-border-width: 0;') !== false, 'never a bare 0');
}
