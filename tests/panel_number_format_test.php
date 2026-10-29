<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for panel figures written with the panel language's separators
 * (pg_number_separators()): database sizes (pg_innodb_size_label()), the
 * location database's row count, and the script-drawn catalog prices, upload
 * sizes, image editor size and visitor counts, which used the browser's
 * locale or a fixed English shape beside server figures in the panel's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _panel_number_format_source($path)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	pg_assert_true(is_string($source) && $source !== '', $path . ' is read');
	return is_string($source) ? $source : '';
}

// The text of a JS or PHP function, from its declaration to the next one.
function _panel_number_format_body($source, $declaration)
{
	$start = strpos($source, $declaration);
	pg_assert_true($start !== false, $declaration . ' is found');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, 'function ', $start + strlen($declaration));
	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

function test_panel_number_format_innodb_size_label_separators()
{
	$turkish = array('decimal' => ',', 'thousands' => '.');

	pg_assert_same('2,5 GB', pg_innodb_size_label(2684354560, $turkish), 'a Turkish decimal comma');
	pg_assert_same('1.024 MB', pg_innodb_size_label(1073741823, $turkish), 'a Turkish thousands dot');
	pg_assert_same('16 KB', pg_innodb_size_label(16384, $turkish), 'kilobytes');

	$french = array('decimal' => ',', 'thousands' => "\u{00A0}");
	pg_assert_same("1\u{00A0}000 KB", pg_innodb_size_label(1024000, $french), 'a no-break space groups');
}

function test_panel_number_format_geo_row_count()
{
	$body = _panel_number_format_body(_panel_number_format_source('includes/fn/geo.php'), 'function pg_geo_settings_note(');

	pg_assert_true(strpos($body, 'number_format(') === false, 'no number_format() with its English separators');
	pg_assert_contains('pg_format_number(', $body, 'the row count with the panel separators');
}

function test_panel_number_format_catalog_prices_and_upload_sizes()
{
	$source = _panel_number_format_source('view_folders.php');

	pg_assert_contains('encode_json(pg_number_separators())', $source, 'the panel separators reach the script');
	pg_assert_true(strpos($source, 'toLocaleString(undefined') === false, 'no browser-locale figures');

	$upload = _panel_number_format_body($source, 'function uploadSizeLabel(');
	pg_assert_true(strpos($upload, 'toFixed(1) +') === false, 'upload sizes not in a fixed English shape');
}

function test_panel_number_format_visitor_widget()
{
	$source = _panel_number_format_source('includes/dashboard/widgets/widget_5.php');

	pg_assert_true(strpos($source, 'toLocaleString()') === false, 'no browser-locale visitor counts');
	pg_assert_contains('pg_number_separators()', $source, 'the panel separators reach the script');
}

function test_panel_number_format_image_editor_and_product_uploads()
{
	pg_assert_contains('window.pgImageEditorNumber', _panel_number_format_source('includes/fn/editor.php'), 'the editor gets the panel separators');

	$editor = _panel_number_format_body(_panel_number_format_source('assets/js/image_editor_modal.js'), 'function _humanSize(');
	pg_assert_contains('pgImageEditorNumber', $editor, 'the editor size uses them');

	$product = _panel_number_format_body(_panel_number_format_source('assets/js/product_builder.js'), 'function readableBytes(');
	pg_assert_contains('cfg.moneySeparators', $product, 'the product screen size uses the panel separators');
}
