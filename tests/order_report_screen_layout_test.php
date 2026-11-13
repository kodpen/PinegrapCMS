<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order report edit form layout (view_order_report.php): the
 * report, layout and filter cards in a form column, how a report works in a
 * sticky status column written after it, Save & Run in the save bar column.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_report_screen_layout_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order_report.php');
}

// The settings (name, levels, filters, Save & Run) sit in one drawer from
// the right inside the form, so its fields still submit; the field names
// and the filter builder are the ones the screen always posted.
function test_order_report_settings_sit_in_a_drawer()
{
	$source = order_report_screen_layout_source();

	$form = strpos($source, '<form name="form" id="edit_form" action="view_order_report.php" method="post">');
	$drawer = strpos($source, '<dialog class="drawer drawer-end" id="report_settings" aria-labelledby="report_settings_title"');
	$body = strpos($source, '<div class="drawer-body">');
	$footer = strpos($source, '<div class="drawer-footer">');
	$end = strpos($source, '</dialog>');
	$form_end = strpos($source, '</form>', (int) $end);

	pg_assert_true($form !== false && $drawer !== false && $body !== false && $footer !== false && $end !== false, 'form, drawer, body and footer are there');
	pg_assert_true($form < $drawer && $drawer < $body && $body < $footer && $footer < $end && $end < $form_end, 'the drawer is inside the form');
	pg_assert_contains('id="report_settings_title">\' . h(lang(\'Report settings\')) . \'</h2>', $source, 'the drawer is named');

	foreach (array("'name'=>'name'", "'name'=>'detail'", "'name'=>'summarize_by_1'", "'name'=>'order_by_1'", "'name'=>'summarize_by_3'", "'name'=>'order_by_3'", 'id="filter_table"', 'onclick="create_filter()"', "lang('How It Works')") as $field) {
		$at = strpos($source, $field);
		pg_assert_true($at !== false && $at > $body && $at < $footer, $field . ' sits in the drawer body');
	}

	$save = strpos($source, 'id="submit_save" name="submit_save"');
	pg_assert_true($save !== false && $save > $footer && $save < $end, 'Save & Run sits in the drawer footer');
	pg_assert_contains('<button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">\' . h(lang(\'Close\')) . \'</button>', $source, 'Close keeps what was typed');
	pg_assert_contains('<button type="button" class="pg-icon-button ms-auto" data-bs-dismiss="drawer" aria-label="\' . h(lang(\'Close\')) . \'"', $source, 'and so does the cross in the head');
	pg_assert_false(strpos($source, 'Cancel Edit') !== false, 'no Edit / Cancel Edit pair');
	pg_assert_false(strpos($source, 'id="edit_button"') !== false, 'no Edit button');
	pg_assert_false(strpos($source, 'edit_form.style.display') !== false, 'the form is never hidden by style');
}

// "Report settings" opens the drawer; it opens by itself on a plain new
// report and after a save that came back with an error.
function test_order_report_settings_button_and_open_state()
{
	$source = order_report_screen_layout_source();

	pg_assert_contains('id="report_settings_button" data-bs-toggle="drawer" data-bs-target="#report_settings"', $source);
	pg_assert_contains("\$open_settings_at_load = ((isset(\$_GET['id']) == false) && (\$ready_report === null)) || \$returned_with_error;", $source);
	pg_assert_contains("(\$open_settings_at_load ? ' data-pg-open-at-load=\"1\"' : '')", $source);

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_report_view.js');

	pg_assert_contains('document.querySelector("dialog[data-pg-open-at-load]")', $script);
	pg_assert_contains('window.pgUI.drawer.show(drawer);', $script, 'opened through pgUI');
}

// Figures and chart sit between the date changer and the table, drawn from
// the server's data; the dashboard's bundle and stylesheet draw the map.
function test_order_report_view_over_the_table()
{
	$source = order_report_screen_layout_source();

	$changer = strpos($source, "' . \$output_date_changer . '\n");
	$view = strpos($source, '<section class="pg-report-view" id="pg_report_view"');
	$table = strpos($source, '<table class="pg-report-table pg-report-share">');

	pg_assert_true($changer !== false && $view !== false && $table !== false && $changer < $view && $view < $table, 'date changer, view, table');
	pg_assert_contains('<script type="application/json" id="pg_report_view_data">\' . (string) json_encode($report_view, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . \'</script>', $source, 'data that cannot close its script');
	pg_assert_contains("pg_js_bundle_tags('assets/js/pg_dash.min.js') . pg_js_bundle_tags('assets/js/pg_report_view.min.js')", $source, 'the drawing, then the view');
	pg_assert_contains("'head' => '<link rel=\"stylesheet\" href=\"' . h(pg_dashboard_v2_style_url()) . '\">',", $source, 'the dashboard stylesheet');
	pg_assert_contains("'figures' => pg_report_view_figures(\$grand, \$charge_columns, BASE_CURRENCY_SYMBOL),", $source, 'figures from the grand total');
	pg_assert_contains('pg_report_view_chart($report_view_kind, $report_view_groups, $report_view_names, $report_view_label, BASE_CURRENCY_SYMBOL, $report_view_codes)', $source, 'chart from the first level, or the finest date level');
	pg_assert_contains('pg_report_view_time_groups($results, $report_view_levels, \'update_summarize_by_name\')', $source, 'a line over the finest date level');

	$bundles = pg_js_bundles();

	pg_assert_same(array('assets/js/pg_report_view.js'), $bundles['assets/js/pg_report_view.min.js'] ?? null);
}
