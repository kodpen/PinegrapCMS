<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The barcode label editor's buildPrintHTML() (assets/js/backend.src.js)
 * writes the page a label is printed from into a window that shares the
 * panel's origin. The barcode is free text and the label template stored
 * JSON, both saved by anyone with the product screens: neither may end the
 * inline script, the style element or an attribute and add markup of its
 * own. Runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The printed page for the template and data; null when Node cannot run.
function barcode_label_print_escape_run($template, $data)
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

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$found = preg_match('/\n        buildPrintHTML\(data\) \{.*?\n    \};\n/s', $source, $match);

	pg_assert_same(1, $found, 'backend.src.js defines LabelEditor buildPrintHTML(data)');

	$script = 'var P = {' . substr($match[0], 0, -strlen("\n    };\n")) . "\n};\n"
		. 'P.template = ' . json_encode($template) . ";\n"
		. 'process.stdout.write(JSON.stringify(P.buildPrintHTML(' . json_encode($data) . ')));';

	$file = @tempnam(sys_get_temp_dir(), 'pgbclabel');

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

function test_barcode_label_print_keeps_values_out_of_the_markup()
{
	$attack = '"></div><img src=x onerror=alert(1)></style></script><script>alert(2)</script>';
	$template = array(
		'label' => array('width' => 50, 'height' => 30, 'background' => $attack),
		'elements' => array(
			array('id' => $attack, 'type' => 'barcode', 'barcodeType' => $attack, 'x' => 1, 'y' => 1, 'width' => 40, 'height' => 15, 'rotation' => $attack),
			array('id' => 'b', 'type' => 'text', 'field' => 'sku', 'x' => '2', 'y' => 18, 'width' => 30, 'height' => 6,
				'fontSize' => $attack, 'fontWeight' => $attack, 'color' => $attack, 'scrollX' => $attack),
			array('id' => 'c', 'type' => 'text', 'field' => 'custom', 'text' => $attack, 'x' => 2, 'y' => 24, 'width' => 30, 'height' => 6),
			array('id' => 'd', 'type' => 'rect', 'x' => $attack, 'y' => 0, 'width' => 50, 'height' => 30,
				'borderWidth' => $attack, 'borderColor' => $attack, 'fill' => $attack, 'borderRadius' => $attack),
			array('id' => 'e', 'type' => 'image', 'src' => $attack, 'x' => 0, 'y' => 0, 'width' => 5, 'height' => 5),
		),
	);
	$html = barcode_label_print_escape_run($template, array('barcode' => $attack, 'sku' => $attack));

	if ($html === null) {
		return;
	}

	pg_assert_same(2, substr_count(strtolower($html), '<script'), 'only the library and the drawing script');
	pg_assert_same(2, substr_count(strtolower($html), '</script'), 'no value closes a script');
	pg_assert_same(1, substr_count(strtolower($html), '</style'), 'no value closes the style element');
	pg_assert_same(1, substr_count(strtolower($html), '<img'), 'only the image element');
	$dom = new DOMDocument();
	@$dom->loadHTML($html);
	$xpath = new DOMXPath($dom);
	pg_assert_same(0, $xpath->query('//@onerror')->length, 'no value adds an attribute');
	pg_assert_same(1, $xpath->query('//svg[@data-bc-type]')->length, 'the barcode type stays in its attribute');
	pg_assert_same(false, strpos($html, 'NaN'), 'a value that is no number falls back to a number');
}

function test_barcode_label_print_keeps_ordinary_values()
{
	$template = array(
		'label' => array('width' => 50, 'height' => 30, 'background' => '#fafafa'),
		'elements' => array(
			array('id' => 'a', 'type' => 'barcode', 'barcodeType' => 'EAN13', 'x' => 1, 'y' => 1, 'width' => 40, 'height' => 15),
			array('id' => 'b', 'type' => 'text', 'field' => 'sku', 'x' => 2, 'y' => 18, 'width' => 30, 'height' => 6,
				'fontSize' => 9, 'fontWeight' => 'bold', 'color' => 'rgba(0, 0, 0, .8)', 'rotation' => 90),
			array('id' => 'c', 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 50, 'height' => 30,
				'borderWidth' => 2, 'borderColor' => '#333', 'fill' => 'none', 'borderRadius' => 3),
		),
	);
	$html = barcode_label_print_escape_run($template, array('barcode' => '8690000000001', 'sku' => 'A&B <1>'));

	if ($html === null) {
		return;
	}

	pg_assert_true(strpos($html, 'background:#fafafa;') !== false, 'the label background is kept');
	pg_assert_true(strpos($html, 'data-bc-type="EAN13"') !== false, 'the barcode type is kept');
	pg_assert_true(strpos($html, 'font-size:9px;font-weight:bold;color:rgba(0, 0, 0, .8);') !== false, 'the text style is kept');
	pg_assert_true(strpos($html, 'transform:rotate(90deg)') !== false, 'the rotation is kept');
	pg_assert_true(strpos($html, 'border:2px solid #333;background:transparent;border-radius:3px;') !== false, 'the frame style is kept');
	pg_assert_true(strpos($html, 'A&amp;B &lt;1&gt;') !== false, 'the text is escaped');
	pg_assert_true(strpos($html, '"8690000000001"') !== false, 'the barcode reaches the drawing script');
}
