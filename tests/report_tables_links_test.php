<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the report tables: the order and visitor detail rows and
 * the shipping report's product names are real links rather than clickable
 * rows, every percent figure goes through the translated '{var:1}%'
 * template (Turkish puts the sign first), and the visitor detail marks are
 * named icons instead of a bare '*'. Group names, product names and
 * Grand Total are row headers, and the corner names the summarize-by levels.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function report_tables_links_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A click handler on a row or a cell is out of the keyboard's reach.
function test_report_tables_have_no_click_only_rows()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php', 'view_shipping_report.php') as $screen) {
		$source = report_tables_links_source($screen);

		pg_assert_false((bool) preg_match('/<(tr|td)\b[^>]*\bonclick=/', $source), $screen . ': onclick on a row or cell');
		pg_assert_false(strpos($source, 'window.location.href') !== false, $screen . ': navigation in a script');
	}

	$source = report_tables_links_source('view_order_report.php');
	pg_assert_contains("<a href=\"' . h(\$output_link_url) . '\">' . h(\$order['order_number']) . '</a>", $source, 'order number is a link');
	pg_assert_contains("'view_order.php?id=' . (int) \$order['id'] . '&send_to=' . urlencode(", $source, 'order link url');

	$source = report_tables_links_source('view_visitor_report.php');
	pg_assert_contains("<a href=\"' . h(\$output_link_url) . '\">' . h(\$visitor['id']) . '</a>", $source, 'visitor id is a link');
	pg_assert_contains("'view_visitor.php?id=' . (int) \$visitor['id'] . \$query_string_visitor_report_id;", $source, 'visitor link url');

	$source = report_tables_links_source('view_shipping_report.php');
	pg_assert_contains("<a href=\"' . h('edit_product.php?id=' . (int) \$product['id']) . '\">", $source, 'product name is a link');
}

// The '{var:1}%' template puts the sign where the panel language wants it.
function test_report_tables_percent_follows_panel_language()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php') as $screen) {
		$source = report_tables_links_source($screen);

		pg_assert_false((bool) preg_match("/\\) \\. '%</", $source), $screen . ': a percent sign glued after a number');
		pg_assert_contains("h(lang(array('string' => '{var:1}%', 'vars' => pg_format_number(100, 2))))", $source, $screen . ': Grand Total percent');
	}

	$source = report_tables_links_source('search_console.php');
	pg_assert_false(strpos($source, ", \$decimals)) . '%'") !== false, 'search_console_percent glues the sign');
	pg_assert_false(strpos($source, "pg_format_number(\$change * 100, 0) . '%'") !== false, 'change badge glues the sign');
	pg_assert_contains("lang(array('string' => '{var:1}%', 'vars' => pg_format_number(abs(\$change) * 100, 0)))", $source, 'change badge sign stays outside the template');

	$tr = pg_test_language_map('tr');
	pg_assert_same('%{var:1}', $tr['{var:1}%']);
}

// A bare '*' is read as "star"; the panel marks a yes with a named icon.
function test_report_tables_visitor_marks_are_named()
{
	$source = report_tables_links_source('view_visitor_report.php');

	pg_assert_false(strpos($source, "? '*' : ''") !== false, 'bare * mark');
	pg_assert_contains("<span class=\"material-icons\" role=\"img\" aria-label=\"' . h(lang('Yes')) . '\">task_alt</span>", $source, 'named yes mark');
}

// A figure is read with its row's heading: the group names and Grand Total
// are row headers, and the corner names the summarize-by levels.
function test_report_tables_name_their_rows()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php') as $screen) {
		$source = report_tables_links_source($screen);

		pg_assert_false(strpos($source, "<th colspan=\"' . \$number_of_summarize_bys . '\">&nbsp;</th>") !== false, $screen . ': empty corner heading');
		pg_assert_contains("array_search(\$liveform->get_field_value('summarize_by_' . \$level), \$summarize_by_options, true)", $source, $screen . ': corner reads the chosen levels');
		pg_assert_contains("'<th colspan=\"' . \$number_of_summarize_bys . '\">' . h(implode(' / ', \$summarize_by_labels)) . '</th>'", $source, $screen . ': named corner');
		pg_assert_contains("<th scope=\"row\" class=\"align-middle text-nowrap\" colspan=\"' . \$number_of_summarize_bys . '\">' . lang('Grand Total') . '</th>", $source, $screen . ': Grand Total row header');
		pg_assert_contains("<th scope=\"row\" class=\"align-middle text-nowrap\" colspan=\"' . \$colspan . '\">", $source, $screen . ': level 2 row header');
		pg_assert_false((bool) preg_match('/<td[^>]*>\' \. (nl2br\()?h\(\$summarize_by_[123]_name/', $source), $screen . ': a group name in a td');
		pg_assert_same(3, preg_match_all('/<th scope="row"[^>]*>\' \. (nl2br\()?h\(\$summarize_by_[123]_name/', $source), $screen . ': three group-name row headers');
	}

	$source = report_tables_links_source('view_shipping_report.php');
	pg_assert_contains("<th scope=\"row\" class=\"chart_label fw-normal\"><a href=\"' . h('edit_product.php?id=", $source, 'shipping product row header');
	pg_assert_contains("<th scope=\"row\">' . lang('Grand Total') . '</th>", $source, 'shipping Grand Total row header');
}
