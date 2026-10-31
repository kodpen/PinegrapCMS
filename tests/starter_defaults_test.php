<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Starter site defaults: neither dump ships the AddThis script (the service
 * closed in 2023, so every page made a request that only failed), the
 * Turkish dump's site title and first arrival name are Turkish, and the
 * 2026.11.0 data step empties the sharing code only where it is still one
 * of the two stock scripts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function starter_defaults_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The dump line that starts with $prefix, or ''.
function starter_defaults_dump_line($dump, $prefix)
{
	foreach (explode("\n", starter_defaults_source('data/backups/' . $dump . '/sql.sql')) as $line) {
		if (strpos($line, $prefix) === 0) {
			return $line;
		}
	}

	return '';
}

function test_starter_defaults_no_addthis()
{
	foreach (array('english_default', 'turkish_default') as $dump) {
		$config = starter_defaults_dump_line($dump, 'INSERT INTO `config` VALUES');

		pg_assert_true($config !== '', $dump . ': the config INSERT is found');
		pg_assert_true(strpos($config, 'addthis.com') === false, $dump . ': no addthis.com in config');
		pg_assert_true(strpos($config, 'addthis_widget') === false, $dump . ': no addthis_widget in config');
	}
}

function test_starter_defaults_turkish_title_and_arrival()
{
	$config = starter_defaults_dump_line('turkish_default', 'INSERT INTO `config` VALUES');

	pg_assert_true(strpos($config, "'https://analytics.google.com','Web Sitem'") !== false, 'the Turkish site title is Web Sitem');
	pg_assert_true(strpos($config, 'My Website') === false, 'no English site title in the Turkish dump');

	$arrival = starter_defaults_dump_line('turkish_default', 'INSERT INTO `arrival_dates` VALUES');

	pg_assert_true(strpos($arrival, "(1,'En kısa sürede','") !== false, 'the first arrival name is En kısa sürede');
	pg_assert_true(strpos($arrival, 'Bir defada') === false, 'no Bir defada arrival name');
}

function test_starter_defaults_data_step_empties_only_stock_script()
{
	$source = starter_defaults_source('includes/migrations/2026.11.0.php');

	pg_assert_true(preg_match('/^\tupgrade_2026_11_0_starter_addthis\(\); +\/\/ 11\.0\.\d+$/m', $source) === 1, 'the step is called from upgrade_to_2026_11_0()');

	$start = strpos($source, 'function upgrade_2026_11_0_starter_addthis()');
	pg_assert_true($start !== false, 'the step is defined');

	$step = substr($source, (int) $start, 2000);

	foreach (array('ra-577d4f644c7cc90c\"></script>\r\n\r\n"', 'ra-5be52cac894a13b9\"></script>\r\n"') as $tail) {
		pg_assert_true(strpos($step, $tail) !== false, 'the step names the stock script ending ' . $tail);
	}

	pg_assert_true(strpos($step, "SET social_networking_code = ''") !== false, 'the step empties the code');
	pg_assert_true(strpos($step, 'WHERE BINARY social_networking_code IN') !== false, 'only a byte-identical stock script is emptied');
	pg_assert_true(strpos($step, 'title') === false && strpos($step, 'arrival_dates') === false, 'the title and arrival names are not migrated');
}
