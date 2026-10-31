<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Figures in the SEO panel over a site page and on the database upgrade
 * pre-flight card: the group weight through the translated '{var:1}%'
 * template, the speed check figures and the backup, free space and large
 * table sizes in the panel's decimal mark and thousands separator.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _seo_speed_figures_source($path)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	pg_assert_true(is_string($source) && $source !== '', $path . ' is read');
	return is_string($source) ? $source : '';
}

// The text of a function, from its declaration to the next declaration.
function _seo_speed_figures_body($source, $declaration)
{
	$start = strpos($source, $declaration);
	pg_assert_true($start !== false, $declaration . ' is found');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\nfunction ", $start + strlen($declaration));
	return substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
}

// runner.php exits without INSTALL_OR_UPDATE, so its two label functions are
// loaded under test names from their source.
function _seo_speed_figures_load_runner_labels()
{
	if (function_exists('_seo_speed_figures_size_label')) {
		return;
	}
	$source = _seo_speed_figures_source('includes/migrations/runner.php');
	foreach (array('install_size_label' => '_seo_speed_figures_size_label', 'install_count_label' => '_seo_speed_figures_count_label') as $name => $alias) {
		$body = _seo_speed_figures_body($source, 'function ' . $name . '(');
		eval(str_replace('function ' . $name . '(', 'function ' . $alias . '(', $body));
	}
}

function test_seo_speed_figures_group_weight_uses_percent_template()
{
	$body = _seo_speed_figures_body(_seo_speed_figures_source('seo.php'), 'function pg_seo_page_toolbar(');
	pg_assert_true(strpos($body, "pg_seo_tb_gweight\">%'") === false, 'the group weight does not put a fixed % before the figure');
	pg_assert_true(strpos($body, "'string' => '{var:1}%'") !== false, 'the group weight is written through the {var:1}% template');
}

function test_seo_speed_figures_check_labels_use_panel_number_format()
{
	$body = _seo_speed_figures_body(_seo_speed_figures_source('seo.php'), 'function pg_seo_check_label(');
	foreach (array('speed_response', 'speed_slow_ratio', 'speed_memory') as $case) {
		$start = strpos($body, "case '" . $case . "':");
		pg_assert_true($start !== false, $case . ' is labelled');
		$next = strpos($body, 'case ', $start + 6);
		$part = substr($body, $start, ($next === false ? strlen($body) : $next) - $start);
		pg_assert_true(strpos($part, 'pg_format_number(') !== false, $case . ' writes its figures with pg_format_number()');
		pg_assert_true(!preg_match('/(?<!pg_format_number\()\(int\) \(\$d\[\'(ms|hits|slow|threshold)\'\]|round\(|(?<!pg_format_number\()\$d\[\'percent\'\]/', $part), $case . ' passes no raw number into lang()');
	}
}

function test_seo_speed_figures_preflight_size_label_matches_panel_size_label()
{
	$body = _seo_speed_figures_body(_seo_speed_figures_source('includes/migrations/runner.php'), 'function install_size_label(');
	pg_assert_true(strpos($body, 'return pg_innodb_size_label($bytes);') !== false, 'the pre-flight size delegates to pg_innodb_size_label() (the panel decimal mark)');
	_seo_speed_figures_load_runner_labels();
	foreach (array(2684354560, 16428249907, 5242880, 51200) as $bytes) {
		pg_assert_same(pg_innodb_size_label($bytes), _seo_speed_figures_size_label($bytes), $bytes . ' bytes read as on the panel');
	}
}

function test_seo_speed_figures_preflight_count_label_writes_full_count()
{
	_seo_speed_figures_load_runner_labels();
	pg_assert_same(pg_format_number(1500000, 0), _seo_speed_figures_count_label(1500000), 'a million-row table is written in full, not 1.5M');
	pg_assert_same(pg_format_number(3456, 0), _seo_speed_figures_count_label(3456), 'a thousand-row count uses the panel thousands separator');
	pg_assert_same('12', _seo_speed_figures_count_label(12), 'a small count stays as is');
}
