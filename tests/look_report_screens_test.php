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
