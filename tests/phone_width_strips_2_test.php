<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that inner flex clusters wrap at phone width instead of pushing the
 * page sideways: the visitor report export and pager cluster, the firewall
 * log Content Security Policy card header buttons, the system style Save
 * row and the form item view variables header. Also pins the marketplace
 * listing grid column minimum to the width of its box.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function phone_width_strips_2_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_phone_width_visitor_report_pager_cluster_wraps()
{
	$source = phone_width_strips_2_test_source('view_visitor_report.php');

	pg_assert_true(preg_match('/<div class="([^"]*)">\s*<a class="btn btn-sm btn-outline theme-secondary" href="\' \. h\(\$csv_url\)/', $source, $match) === 1, 'export and pager cluster found');
	pg_assert_true(strpos(' ' . $match[1] . ' ', ' flex-wrap ') !== false && strpos(' ' . $match[1] . ' ', ' row-gap-2 ') !== false, 'export and pager cluster wraps');
}

function test_phone_width_waf_log_csp_header_buttons_wrap()
{
	$source = phone_width_strips_2_test_source('view_waf_log.php');

	pg_assert_true(preg_match('/<span class="([^"]*)">\s*<a href="\' \. pg_settings_link\(\'firewall\', \'pgset-headers\'\)/', $source, $match) === 1, 'CSP header buttons found');
	pg_assert_true(strpos(' ' . $match[1] . ' ', ' flex-wrap ') !== false && strpos(' ' . $match[1] . ' ', ' row-gap-2 ') !== false, 'CSP header buttons wrap');
}

function test_phone_width_system_style_save_row_wraps()
{
	$source = phone_width_strips_2_test_source('view_system_style_source.php');

	pg_assert_true(preg_match('/<div class="([^"]*)">\s*<button type="submit" name="submit_save_and_return"/', $source, $match) === 1, 'save row found');
	pg_assert_true(strpos(' ' . $match[1] . ' ', ' flex-wrap ') !== false, 'save row wraps');
}

function test_phone_width_form_item_view_variables_header_wraps()
{
	$source = phone_width_strips_2_test_source('edit_form_item_view.php');

	pg_assert_true(preg_match('/id="pg_fiv_variables">\s*<div class="([^"]*)">/', $source, $match) === 1, 'variables header found');
	pg_assert_true(strpos(' ' . $match[1] . ' ', ' flex-wrap ') !== false, 'variables header wraps');
}

function test_phone_width_marketplace_listing_grid_fits_its_box()
{
	$source = phone_width_strips_2_test_source('marketplace_products.php');

	pg_assert_true(preg_match('/\.mpa-grid \{[^}]*grid-template-columns: repeat\(auto-fill, minmax\(min\(280px, 100%\), 1fr\)\)/', $source) === 1, 'listing columns never exceed the box');
}
