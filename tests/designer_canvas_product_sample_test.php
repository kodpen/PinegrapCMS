<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer canvas's sample product (_sdProductSample() in
 * style_designer.js, run under Node): its prices agree with each other and
 * its flags are written as the page writes them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The discount is the original price less the price, its percent rounds
// from them, the range runs from the price, and every yes/no is '1' or '0'.
function test_canvas_product_sample_agrees()
{
	if (!function_exists('exec')) return;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return;
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$a = strpos($js, "\n    var _SD_PRODUCT_SAMPLE_REGIONS = {");
	pg_assert_true($a !== false, '_SD_PRODUCT_SAMPLE_REGIONS is found');
	$b = strpos($js, "\n    function _sdProductSample(", $a);
	$b = strpos($js, "\n    }\n", $b) + 6;
	$stubs = "function _sdT(s) { return s; }\nfunction _sdMoney(n) { return String(n); }\nfunction _sdDateSample() { return 'DAY'; }\n";
	$script = "var p = _sdProductSample(), n = function (k) { return parseFloat(p[k]); };\n"
		. "var flags = ['__backorder', '__out_of_stock', '__preorder', '__shippable', '__taxable', '__featured', '__is_new', '__has_variants', '__has_children', '__is_bundle', '__is_free_shipping', '__is_gift_card', '__is_donation'];\n"
		. "console.log(JSON.stringify([n('__original_price') - n('__price') === n('__discount_amount'),\n"
		. "  Math.round(100 * n('__discount_amount') / n('__original_price')) === n('__discount_percent'),\n"
		. "  n('__price_min') === n('__price') && n('__price_max') > n('__price'),\n"
		. "  p.__sku === p.__name && p.__short_description === p.__image_alt,\n"
		. "  flags.every(function (k) { return p[k] === '1' || p[k] === '0'; })]));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgprod');
	if ($file === false) return;
	file_put_contents($file, $stubs . substr($js, $a, $b - $a) . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	pg_assert_same(array(true, true, true, true, true), json_decode($printed, true), $printed);
}
