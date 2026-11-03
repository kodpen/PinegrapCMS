<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * esc() in assets/js/product_builder.js writes option labels, SKUs and
 * short descriptions into the variant matrix, inside attribute values as
 * well as text. It must turn both quotes into entities, so a label such as
 * `Z" onfocus="x` stays one value and never becomes an attribute. Runs in
 * Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs esc() from the script in Node over the values; null when Node cannot run.
function product_builder_js_escape_run($values)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/product_builder.js');
	$found = preg_match('/function esc\(value\) \{.*?\n    \}\n/s', $source, $match);

	pg_assert_same(1, $found, 'product_builder.js defines esc(value)');

	$script = $match[0] . "\n"
		. 'var values = ' . json_encode($values, JSON_UNESCAPED_UNICODE) . ";\n"
		. 'console.log(JSON.stringify(values.map(esc)));';

	$file = @tempnam(sys_get_temp_dir(), 'pgesc');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// The same entities h() gives for text and for attribute values.
function test_product_builder_js_esc_escapes_quotes_and_markup()
{
	$values = array('Mavi "q"', 'Z" data-pgx="1', "O'Brien", '<b>&amp;</b>', 'Kırmızı', '', 0, 12.5);
	$result = product_builder_js_escape_run($values);

	if ($result === null) {
		return;
	}

	$expected = array();

	foreach ($values as $value) {
		$expected[] = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}

	pg_assert_same($expected, $result, 'esc() escapes & < > " and \'');
}

// undefined and null are written as nothing.
function test_product_builder_js_esc_empty_values()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/product_builder.js');

	pg_assert_true(strpos($source, "value === undefined || value === null ? ''") !== false, 'esc() writes undefined and null as an empty string');
}
