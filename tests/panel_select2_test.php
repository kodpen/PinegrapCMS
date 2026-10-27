<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel draws its searchable selects as Bootstrap 6 Combobox fields
 * (pg_fields.js), so its header loads no select2 asset and the panel
 * sheet carries no select2 rule. Site pages keep their own select2.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_select2_test_header_source()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/output.php');
	$start = strpos($source, 'function output_control_panel_header_includes(');
	pg_assert_true($start !== false, 'header function found');
	$end = strpos($source, "\nfunction ", $start + 1);
	return substr($source, $start, $end === false ? null : $end - $start);
}

function test_panel_header_loads_no_select2()
{
	$header = panel_select2_test_header_source();
	pg_assert_contains('bootstrap-6/css/bootstrap.min.css', $header);
	pg_assert_false(stripos($header, 'select2') !== false, 'no select2 asset in the panel header');
}

function test_panel_sheet_has_no_select2_rule()
{
	$css = file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	pg_assert_true($css !== false && $css !== '', 'backend.src.css read');
	pg_assert_false(stripos($css, 'select2') !== false, 'no select2 rule in backend.src.css');
}
