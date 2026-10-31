<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Screens that had a lone control or fact line under their heading (a bar
 * whose one control sat alone at the right, a report's lone Edit button, a
 * lone action row, a "Disk Usage" line) draw one toolbar through
 * pg_screen_toolbar() instead. Source pins: the screens need a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function screen_lone_controls_source($file)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	pg_assert_true(is_string($source), $file . ' is readable');

	return (string) $source;
}

function screen_lone_controls_screens()
{
	return array(
		'erp_invoice_recurrences.php',
		'view_audit_log.php',
		'view_waf_log.php',
		'view_not_found_log.php',
		'view_order_report.php',
		'view_visitor_report.php',
		'view_performance_log.php',
		'erp_bank_statement.php',
		'erp_stock_count.php',
		'calendars.php',
		'view_files.php',
		'view_design_files.php',
		'backups.php',
	);
}

function test_screen_lone_controls_bars_come_from_the_helper()
{
	foreach (screen_lone_controls_screens() as $file) {
		$source = screen_lone_controls_source($file);
		pg_assert_contains('pg_screen_toolbar(', $source, $file . ' draws its bar through pg_screen_toolbar()');
		pg_assert_false(strpos($source, '<nav id="button_bar"') !== false, $file . ' writes no bar by hand');
		// An empty grow written by hand is the spacer that pushed a single
		// control to the right; the helper adds one only beside other slots.
		pg_assert_false(strpos($source, 'pg-toolbar-grow"></div>') !== false, $file . ' starts no bar with an empty grow');
	}
}

function test_screen_lone_controls_disk_usage_is_in_the_toolbar()
{
	foreach (array('view_files.php', 'view_design_files.php', 'backups.php') as $file) {
		$source = screen_lone_controls_source($file);
		pg_assert_true((bool) preg_match("/'grow'\\s*=>[^\\n]*lang\\('Disk Usage'\\)/", $source), $file . ' puts Disk Usage in the grow slot');
		pg_assert_false((bool) preg_match("/<p>' \\. lang\\('Disk Usage'\\)/", $source), $file . ' has no Disk Usage paragraph');
	}
}

function test_screen_lone_controls_reports_keep_the_bar_id()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php') as $file) {
		$source = screen_lone_controls_source($file);
		// Edit hides the bar and Cancel Edit shows it again, both by its id;
		// the helper draws nav#button_bar.
		pg_assert_contains("getElementById(\\'button_bar\\').style.display = \\'none\\'", $source, $file . ' Edit hides the bar by id');
		pg_assert_contains("getElementById(\\'button_bar\\').style.display = \\'\\'", $source, $file . ' Cancel Edit gives the bar back its flex display');
		pg_assert_false(strpos($source, "getElementById(\\'button_bar\\').style.display = \\'block\\'") !== false, $file . ' Cancel Edit does not force display block');
		pg_assert_contains('id="edit_button"', $source, $file . ' keeps the Edit button id');
	}
}

function test_screen_lone_controls_one_bar_on_the_log_screens()
{
	$audit = screen_lone_controls_source('view_audit_log.php');
	pg_assert_same(1, substr_count($audit, 'pg_screen_toolbar('), 'audit trail: one toolbar');
	pg_assert_false((bool) preg_match('/class="pg-toolbar[ "]/', $audit), 'audit trail: no second hand-written bar');
	pg_assert_contains('form="audit_view_filter"', $audit, 'audit trail: the toolbar search and Filter submit the filter form');
	pg_assert_contains('id="audit_view_filter"', $audit, 'audit trail: the filter form');

	$waf = screen_lone_controls_source('view_waf_log.php');
	$sort = strpos($waf, 'pg_list_sort_menu(');
	$events = strpos($waf, "lang('Recent Events')");
	pg_assert_true($sort !== false && $events !== false && $sort < $events, 'firewall log: the sort menu is in the screen toolbar, not the events card');
	pg_assert_false((bool) preg_match('/class="pg-toolbar[ "]/', $waf), 'firewall log: no hand-written bar');

	$performance = screen_lone_controls_source('view_performance_log.php');
	pg_assert_false(strpos($performance, "<p class=\"m-0 small fg-secondary\">' . lang('Per-request") !== false, 'performance log: no second description line');
	pg_assert_same(3, substr_count($performance, "'heading_description' => h(lang('Per-request runtime metrics"), 'performance log: every branch carries the description as heading_description');
}

function test_screen_lone_controls_lone_rows_are_gone()
{
	$bank = screen_lone_controls_source('erp_bank_statement.php');
	pg_assert_false(strpos($bank, '<div class="d-flex justify-content-end my-5">') !== false, 'bank statement: no lone row for Record the suggested lines');

	$count = screen_lone_controls_source('erp_stock_count.php');
	pg_assert_false(strpos($count, '<div class="d-flex flex-wrap gap-3 justify-content-end mb-7">') !== false, 'stock count: no lone row for Cancel the count');
	pg_assert_contains("name=\"erp_action\" value=\"apply\"", $count, 'stock count: Apply stays with its form');

	$calendars = screen_lone_controls_source('calendars.php');
	pg_assert_false(strpos($calendars, 'md:col-6 xl:col-9 text-center md:text-start') !== false, 'calendars: the bar is not inside a column');
}

function test_screen_lone_controls_edit_screen_rows_join_the_toolbar()
{
	$screens = array(
		'edit_common_region.php', 'edit_designer_region.php', 'edit_menu.php', 'edit_calendar_event.php',
		'edit_email_campaign_profile.php', 'edit_folder.php', 'edit_product_group.php', 'edit_custom_style.php',
		'edit_ad_region.php', 'edit_dynamic_region.php', 'edit_login_region.php', 'view_menu_items.php',
		'edit_javascript.php', 'edit_theme_css.php', 'view_fields.php', 'add_submitted_form.php',
	);

	foreach ($screens as $file) {
		$source = screen_lone_controls_source($file);
		pg_assert_contains('pg_screen_toolbar(', $source, $file . ' draws its bar through pg_screen_toolbar()');
		pg_assert_false(strpos($source, '<nav id="button_bar"') !== false, $file . ' writes no bar by hand');
		// The record's name was a second heading in square brackets.
		pg_assert_false((bool) preg_match("/<h2[^>]*>\\[' \\./", $source), $file . ' has no [name] second heading');
		// The Duplicate link was a lone btn-link row.
		// (A Duplicate submit in the form's save bar stays with its form.)
		pg_assert_false(strpos($source, 'btn btn-link pg-link-secondary py-0 mb-3') !== false, $file . ' has no lone Duplicate row');
		// The Page Style Body Tag line was a paragraph above the bar.
		pg_assert_false(strpos($source, "<p>' . lang('Page Style Body Tag')") !== false, $file . ' has no Body Tag paragraph');
	}
}
