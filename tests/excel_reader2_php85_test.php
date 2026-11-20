<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the bundled excel_reader2.php (php-excel-reader 2.22) reads an
 * uploaded .xls file for the site search index on PHP 8 and stays quiet
 * there. Its two classes had PHP 4 constructors, which PHP 8 never runs, so
 * convert_xls_to_text() in update_search_index.php returned '' for every
 * file. The library is patched locally: __construct(), declared properties
 * (PHP 8.2) and an integer column-name step (PHP 8.1). The test runner lets
 * a deprecation raised inside a bundled library through, so the function is
 * run in a PHP process of its own that reports every error.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// convert_xls_to_text() run on each file in a PHP process that reports every
// error; update_search_index.php is a job script, so only the function is
// taken from its source.
function pg_test_excel_reader2_convert($files)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php');
	$start = strpos($source, 'function convert_xls_to_text(');
	$end = ($start === false) ? false : strpos($source, "\nfunction ", $start + 10);
	pg_assert_true(($start !== false) && ($end !== false), 'convert_xls_to_text() is found in update_search_index.php');

	$script = tempnam(sys_get_temp_dir(), 'pg_xls');
	$code = <<<'PHP'
<?php
error_reporting(E_ALL);
ini_set("display_errors", "1");
date_default_timezone_set("UTC");
set_error_handler(function ($severity, $message, $file, $line) {
	echo "ERROR " . $message . " (" . basename($file) . ":" . $line . ")\n";
	return true;
});
// The function loads the library by a path relative to the software folder,
// as the job does.
chdir($argv[1]);

PHP;
	$code .= substr($source, $start, $end - $start) . "\n";
	$code .= <<<'PHP'
foreach (array_slice($argv, 2) as $file) {
	echo "OUT " . json_encode(convert_xls_to_text($file), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
PHP;
	file_put_contents($script, $code);

	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg(PG_FUNCTIONS_DIR);

	foreach ($files as $file) {
		$command .= ' ' . escapeshellarg($file);
	}

	$output = (string) shell_exec($command . ' 2>&1');
	unlink($script);

	return trim(str_replace("\r\n", "\n", $output));
}

function test_excel_reader2_reads_xls_text_without_notices()
{
	// tests/fixtures/excel_reader2/products.xls is a BIFF8 workbook written by
	// PHPExcel's Excel5 writer: two sheets with text (Turkish letters included),
	// decimals, a negative number, a formula (C2+C3) and a date in Excel's
	// built-in format 14.
	$fixture = dirname(__FILE__) . '/fixtures/excel_reader2/products.xls';
	pg_assert_true(is_file($fixture), 'the .xls fixture is there');

	pg_assert_same(
		'OUT "Product Price Stock Çay bardağı 12.5 40 Copper kettle 249.99 -3 Total 37 Restocked 03/15/2023 Handle with care 1234567"',
		pg_test_excel_reader2_convert(array($fixture)));
}

function test_excel_reader2_returns_empty_text_for_other_files_quietly()
{
	// A file that is no .xls (this test) and a missing one leave no text and
	// no notice, so the job goes on to the next file.
	pg_assert_same(
		"OUT \"\"\nOUT \"\"",
		pg_test_excel_reader2_convert(array(__FILE__, dirname(__FILE__) . '/fixtures/excel_reader2/missing.xls')));
}
