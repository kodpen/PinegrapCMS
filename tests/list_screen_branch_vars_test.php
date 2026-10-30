<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for three list screens that printed a variable only one branch set.
 * Menu Items printed its subheading for every role, but only a Designer or
 * Administrator got one. A saved visitor report with a Date filter skipped
 * the date changer yet still printed it. The Pages list set its no mobile
 * style warning class only when no preview style was found, so during a
 * theme preview the first such row read an unset variable and later rows
 * reused the previous row's class.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Menu Items: the subheading is empty before the role check fills it.
function test_menu_items_subheading_default()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_menu_items.php');
	$default = strpos($source, "\$output_subheading = '';");

	pg_assert_true($default !== false, 'subheading default');
	pg_assert_true($default < strpos($source, "if (\$user['role'] < 2) {\n    \$output_subheading ="), 'default before the role check');
}

// Saved visitor report: the date changer is empty before the date filter check.
function test_visitor_report_date_changer_default()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_visitor_report.php');
	$default = strpos($source, "\$output_date_changer = '';");

	pg_assert_true($default !== false, 'date changer default');
	pg_assert_true($default < strpos($source, "if (\$date_filter_exists == false) {"), 'default before the date filter check');
}

// Pages list: every row starts with no mobile style warning class.
function test_pages_list_mobile_warning_class_per_row()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_pages.php');
	$row = strpos($source, "\$output_style_name = '';\n        \$output_no_mobile_style_warning_class = '';");

	pg_assert_true($row !== false, 'class reset next to the style name');
	pg_assert_same(1, substr_count($source, "\$output_no_mobile_style_warning_class = '';"), 'one reset per row');
}
