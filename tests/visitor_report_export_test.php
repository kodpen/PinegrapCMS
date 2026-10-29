<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source checks for the visitor report's CSV export: the grouping columns
 * are named with the same labels the report form offers, every grouping
 * cell reads as it does on the screen, the file name keeps the letters of a
 * Turkish report name, and the Pay Per Click / Organic grouping is
 * translated.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function visitor_report_export_source()
{
	return (string) file_get_contents(__DIR__ . '/../pinegrap/view_visitor_report.php');
}

// The export block alone, from its request check to its exit.
function visitor_report_export_block()
{
	$source = visitor_report_export_source();
	$start = strpos($source, "if (isset(\$_GET['export']) && \$_GET['export'] == 'csv')");
	pg_assert_true($start !== false, 'the export block exists');
	$end = strpos($source, 'exit();', $start);
	pg_assert_true($end !== false, 'the export block ends with exit()');

	return substr($source, $start, $end - $start);
}

function visitor_report_summarize_name_function()
{
	$source = visitor_report_export_source();
	$start = strpos($source, 'function update_summarize_by_name(');
	pg_assert_true($start !== false, 'update_summarize_by_name() exists');
	$end = strpos($source, "\nfunction ", $start + 10);

	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

// A grouping header is the option's label, not its stored value.
function test_visitor_report_export_headers_are_option_labels()
{
	$block = visitor_report_export_block();
	pg_assert_contains('array_flip($summarize_by_options)', $block);
	pg_assert_false(
		(bool) preg_match('/\$export_headers\[\]\s*=\s*\$liveform->get_field_value\(/', $block),
		'no raw option value as a header'
	);
}

// Each grouping cell goes through the screen's own labelling.
function test_visitor_report_export_cells_use_screen_names()
{
	$block = visitor_report_export_block();
	pg_assert_true(
		(bool) preg_match('/update_summarize_by_name\(\s*\$row\[\'sb\'\s*\.\s*\$i\]/', $block),
		'grouping cells labelled by update_summarize_by_name()'
	);
}

// The report name keeps its letters in the file name.
function test_visitor_report_export_file_name_is_transliterated()
{
	$block = visitor_report_export_block();
	$transliterate = strpos($block, 'pg_transliterate_to_ascii($export_name)');
	$filter = strpos($block, "preg_replace('/[^A-Za-z0-9_-]+/'");
	pg_assert_true($transliterate !== false, 'the name is transliterated');
	pg_assert_true($filter !== false && $transliterate < $filter, 'before the safe-set filter');
}

// The export still streams: unbuffered query and a flush every 1000 rows.
function test_visitor_report_export_still_streams()
{
	$block = visitor_report_export_block();
	pg_assert_contains('MYSQLI_USE_RESULT', $block);
	pg_assert_contains('% 1000', $block);
	pg_assert_contains("number_format(\$row['order_total'] / 100, 2, '.', '')", $block);
}

// The two SQL literals of the Pay Per Click / Organic grouping are translated.
function test_visitor_report_pay_per_click_organic_translated()
{
	$function = visitor_report_summarize_name_function();
	pg_assert_contains("case 'pay_per_click_organic':", $function);
	pg_assert_contains("lang('Pay Per Click')", $function);
	pg_assert_contains("lang('Organic')", $function);

	// The SQL still returns the literals the case maps.
	$source = visitor_report_export_source();
	pg_assert_contains('THEN "Pay Per Click"', $source);
	pg_assert_contains('THEN "Organic"', $source);
}
