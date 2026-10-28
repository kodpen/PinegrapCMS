<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for three report details: the cash flow toolbar form wraps on
 * a phone and keeps each date label with its field, the SEO detail drawer
 * prints a group weight through the translated '{var:1}%' pattern, and the
 * Search Console chart's day labels follow the site's date order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function report_formats_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_report_formats_cashflow_toolbar_wraps()
{
	$source = report_formats_source('erp_cashflow.php');

	pg_assert_true((bool) preg_match('/<form method="get" action="erp_cashflow\.php" class="[^"]*\bflex-wrap\b[^"]*\brow-gap-/', $source), 'cash flow toolbar form wraps with a row gap');
	pg_assert_true((bool) preg_match('/<span class="d-inline-flex align-items-center gap-2">\s*<label for="from"/', $source), 'start label stays with its field');
	pg_assert_true((bool) preg_match('/<span class="d-inline-flex align-items-center gap-2">\s*<label for="to"/', $source), 'end label stays with its field');
}

function test_report_formats_seo_weight_is_translated()
{
	$source = report_formats_source('seo.php');

	pg_assert_false(strpos($source, "'%' . (int) \$weight") !== false || strpos($source, '>%\' . (int) $weight') !== false, 'no hard-coded % prefix');
	pg_assert_contains("lang(array('string' => '{var:1}%', 'vars' => (int) \$weight))", $source, 'weight through {var:1}%');
}

function test_report_formats_search_console_day_labels_follow_date_order()
{
	$source = report_formats_source('search_console.php');

	pg_assert_false(strpos($source, "date('d.m', strtotime(\$day") !== false, 'no fixed d.m day label');
	pg_assert_contains("DATE_FORMAT == 'month_day'", $source, 'day label reads the date order');
}
