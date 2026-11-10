<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the public get_product action (pg_panel_get_product()): the
 * variant chooser asks it for one product by id, signed out. A product that
 * is switched off, or in the Recycle Bin (which switches it off), is not on
 * the site: every chooser lists enabled products only, so the action answers
 * "Product not found." for it instead of its texts, price, stock and photos.
 * With an offer the answer carries the strike-through price block beside the
 * amount, which stays as it was.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs pg_panel_get_product() for product 7 in a fresh PHP, the database
// answering $row for the product read and $discounts (product id => price
// in cents) for the offers. Returns the decoded answer plus the queries,
// array('error' => output) on a crash, or null when PHP cannot be started
// again.
function storefront_get_product_run($row, $discounts = array())
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/commerce.php');
	$start = strpos($source, 'function pg_panel_get_product(');
	pg_assert_true($start !== false, 'pg_panel_get_product not found');
	$next = strpos($source, "\nfunction ", $start + 10);
	$handler = substr($source, $start, ($next === false ? strlen($source) : $next) - $start);

	$file = @tempnam(sys_get_temp_dir(), 'pggetprod');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

define('PATH', '/');
$GLOBALS['queries'] = array();

function db_item($sql) { $GLOBALS['queries'][] = $sql; return $GLOBALS['row']; }
function db_q($sql, $params = array()) { $GLOBALS['queries'][] = $sql; return false; }
function lang($text) { return $text; }
function encode_url_path($path) { return $path; }
function pg_visitor_money($amount, $symbol) { return $symbol . $amount; }
function _pg_format_price_with_discount($original, $effective, $symbol) { return 'block:' . $original . ':' . $effective . ':' . $symbol; }
function get_discounted_product_prices() { return $GLOBALS['discounts']; }
function pg_product_oos_notice($product) { return ''; }
function encode_json($value) { return json_encode($value); }

register_shutdown_function(function () {
	echo "\n" . json_encode($GLOBALS['queries']);
});

eval($handler);

pg_panel_get_product(array('product' => array('id' => '7')), 'get_product');
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$GLOBALS[\'row\'] = ' . var_export($row, true) . ";\n"
		. '$GLOBALS[\'discounts\'] = ' . var_export($discounts, true) . ";\n"
		. '$handler = ' . var_export($handler, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$answer = (count($lines) >= 2) ? json_decode($lines[count($lines) - 2], true) : null;
	$queries = (count($lines) >= 1) ? json_decode($lines[count($lines) - 1], true) : null;

	if (($code !== 0) || !is_array($answer) || !is_array($queries)) {
		return array('error' => implode("\n", $lines));
	}

	return array('answer' => $answer, 'queries' => $queries);
}

function storefront_get_product_row($enabled)
{
	return array(
		'id' => '7', 'name' => 'TSHIRT-RED-XL', 'address_name' => 'tshirt-red-xl',
		'short_description' => 'Red XL', 'full_description' => 'Unreleased colour',
		'details' => '', 'code' => '', 'image_name' => 'red.jpg', 'inventory' => '1',
		'inventory_quantity' => '5', 'backorder' => '0', 'out_of_stock_message' => '',
		'price' => '12990', 'mpn' => '', 'gtin' => '', 'brand' => '', 'weight' => '0',
		'shippable' => '1', 'taxable' => '1', 'reward_points' => '0', 'seo_score' => '0',
		'custom_field_1' => '', 'custom_field_2' => '', 'custom_field_3' => '',
		'custom_field_4' => '', 'timestamp' => '0', 'enabled' => $enabled);
}

// A switched-off variant (or one in the Recycle Bin) answers "not found"
// without its fields.
function test_storefront_get_product_refuses_disabled_product()
{
	$run = storefront_get_product_run(storefront_get_product_row('0'));

	if ($run === null) {
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_same('error', $run['answer']['status']);
	pg_assert_same('Product not found.', $run['answer']['message']);
	pg_assert_false(isset($run['answer']['product']), 'a disabled product was answered');
}

// An enabled product is answered, and the switch itself is not handed out.
function test_storefront_get_product_answers_enabled_product()
{
	$run = storefront_get_product_run(storefront_get_product_row('1'));

	if ($run === null) {
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_same('success', $run['answer']['status']);
	pg_assert_same('TSHIRT-RED-XL', $run['answer']['product']['name']);
	pg_assert_same(12990, $run['answer']['product']['price_cents']);
	pg_assert_false(array_key_exists('enabled', $run['answer']['product']), 'the enabled column is in the answer');
	pg_assert_contains('enabled', $run['queries'][0]);
}

// With an offer the answer carries the strike-through block the product's
// page draws (price_block_html), for the variant chooser; price_formatted
// stays the amount for the layouts that read it.
function test_storefront_get_product_sends_the_price_block()
{
	$run = storefront_get_product_run(storefront_get_product_row('1'), array(7 => 9990));

	if ($run === null) {
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_same('block:12990:9990:$', $run['answer']['product']['price_block_html'], 'the block of the offer');
	pg_assert_same('$99.9', $run['answer']['product']['price_formatted'], 'the amount, as before');
	pg_assert_same('1', $run['answer']['product']['has_discount']);
}
