<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the order, visitor and shipping report screens and the
 * form list view layout on Bootstrap 6: the period changer buttons carry a
 * v6 variant (the group, join and size rules only select [class*=btn-]
 * children), the arrows and groups are named, the layout selects have a
 * label, the Grand Total percentage follows the site's number format and
 * the visitor detail pager links are named.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function report_screens_bs6_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_report_screens_no_bare_btn_in_a_group()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php', 'view_shipping_report.php') as $screen) {
		$source = report_screens_bs6_source($screen);

		pg_assert_false((bool) preg_match('/<a class="btn py-0/', $source), $screen . ': no bare .btn link without a variant');
		pg_assert_false(strpos($source, '"><</a>') !== false || strpos($source, '">></a>') !== false, $screen . ': no raw < / > arrow text');
		pg_assert_contains('bi-chevron-left', $source, $screen . ': previous arrow is a chevron');
		pg_assert_contains("aria-label=\"' . h(lang('Previous')) . '\"", $source, $screen . ': previous arrow is named');
		pg_assert_contains("aria-label=\"' . h(lang('Next')) . '\"", $source, $screen . ': next arrow is named');
		pg_assert_contains('role="group"', $source, $screen . ': each changer is a named group');
	}
}

function test_report_screens_layout_selects_are_labelled()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php') as $screen) {
		$source = report_screens_bs6_source($screen);

		pg_assert_false(strpos($source, 'input-group-text row') !== false, $screen . ': layout rows are plain rows');

		foreach (array('summarize_by_1', 'summarize_by_2', 'summarize_by_3', 'order_by_1', 'order_by_2', 'order_by_3') as $field) {
			pg_assert_contains("'name'=>'" . $field . "', 'id'=>'" . $field . "'", $source, $screen . ': ' . $field . ' has an id');
			pg_assert_contains('<label for="' . $field . '"', $source, $screen . ': ' . $field . ' has a label');
		}
	}

	$source = report_screens_bs6_source('edit_form_list_view.php');
	pg_assert_false(strpos($source, 'input-group-text row') !== false, 'edit_form_list_view.php: layout rows are plain rows');

	foreach (array('order_by_1', 'order_by_2', 'order_by_3', 'order_by_1_type', 'order_by_2_type', 'order_by_3_type') as $field) {
		pg_assert_contains('<label for="' . $field . '"', $source, 'edit_form_list_view.php: ' . $field . ' has a label');
	}
}

function test_report_screens_grand_total_percent_follows_number_format()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php') as $screen) {
		$source = report_screens_bs6_source($screen);

		pg_assert_false(strpos($source, '100.00%') !== false, $screen . ': no hard-coded 100.00%');
		pg_assert_contains("pg_format_number(100, 2) . '%", $source, $screen . ': 100 % is formatted');
	}
}

function test_report_screens_visitor_pager_is_named()
{
	$source = report_screens_bs6_source('view_visitor_report.php');

	foreach (array('First page', 'Previous page', 'Next page', 'Last page') as $name) {
		pg_assert_contains("lang('" . $name . "')", $source, 'visitor pager: ' . $name);
	}

	pg_assert_contains("<nav aria-label=\"' . h(lang('Pagination')) . '\">", $source, 'visitor pager nav is named');
}

function test_report_screens_toolbar_labels_are_translated()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php', 'view_shipping_report.php', 'edit_form_list_view.php') as $screen) {
		$source = report_screens_bs6_source($screen);

		pg_assert_false(strpos($source, 'aria-label="Button Bar"') !== false, $screen . ': Button Bar is translated');
		pg_assert_false(strpos($source, 'aria-label="data edit buttons ') !== false, $screen . ': data edit buttons is translated');
	}
}
