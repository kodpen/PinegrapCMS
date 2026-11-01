<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the public api.php storefront actions called without the fields
 * they read. get_cross_sell_items stopped on a TypeError (HTTP 500) when no
 * product group was sent, so its recipient and whole-order modes never ran;
 * get_product and get_installment_options printed PHP warnings (with the
 * server path) to signed-out visitors before their JSON.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs get_cross_sell_items() on $request in a fresh PHP with every warning
// turned into a failure and the database answering no rows. Returns the
// response, array('error' => output) on a warning or crash, or null when PHP
// cannot be started again.
function api_public_missing_fields_cross_sell_run($request, $order_id = '')
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgxsell');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

$GLOBALS['queries'] = array();

function db_items($sql) { $GLOBALS['queries'][] = $sql; return array(); }
function db_item($sql) { $GLOBALS['queries'][] = $sql; return array(); }
function db($sql) { $GLOBALS['queries'][] = $sql; return ''; }
function e($value) { return addslashes((string) $value); }
function lang($text) { return $text; }
function error_response($message = '') { return array('status' => 'error', 'message' => $message); }

require $pg_dir . '/get_cross_sell_items.php';

try {
	$response = get_cross_sell_items($request);
} catch (Throwable $error) {
	echo get_class($error) . ': ' . $error->getMessage() . ' at line ' . $error->getLine();
	exit(1);
}

echo json_encode(array('response' => $response, 'queries' => $GLOBALS['queries']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$request = ' . var_export($request, true) . ";\n"
		. ($order_id !== '' ? '$_SESSION = array(\'ecommerce\' => array(\'order_id\' => ' . var_export($order_id, true) . "));\n" : '')
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['response'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// The cart layout's call sends no source at all: no warning, no crash, the
// "no source products" answer.
function test_api_public_cross_sell_without_any_source()
{
	$run = api_public_missing_fields_cross_sell_run(array('action' => 'get_cross_sell_items'));

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_same('error', $run['response']['status']);
	pg_assert_contains('no source products', $run['response']['message']);
}

// With an order in the session and no source sent, the order's products are
// the source.
function test_api_public_cross_sell_whole_order_mode_runs()
{
	$run = api_public_missing_fields_cross_sell_run(array('action' => 'get_cross_sell_items'), '42');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_contains("order_id = '42'", implode("\n", $run['queries']), 'the order\'s items were read');
}

// The recipient mode sends only the recipient: it reaches its own branch.
function test_api_public_cross_sell_recipient_only_reaches_its_branch()
{
	$run = api_public_missing_fields_cross_sell_run(array('action' => 'get_cross_sell_items', 'recipient' => array('id' => 1)));

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_contains('does not have an active order', $run['response']['message']);
}

// A product group without the products key reads the group, no warning.
function test_api_public_cross_sell_product_group_without_products_key()
{
	$run = api_public_missing_fields_cross_sell_run(array('action' => 'get_cross_sell_items', 'product_group' => array('id' => 74)));

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_contains("product_group = '74'", implode("\n", $run['queries']), 'the group was read');
}

// A source sent as text instead of an object is read as missing.
function test_api_public_cross_sell_scalar_sources_are_ignored()
{
	$run = api_public_missing_fields_cross_sell_run(array('product_group' => 'x', 'recipient' => '7', 'products' => 'y'));

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_contains('no source products', $run['response']['message']);
}

// Returns the source of a handler in includes/panel/commerce.php.
function api_public_missing_fields_handler($name)
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/commerce.php');
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' not found');
	$next = strpos($source, "\nfunction ", $start + 10);

	return substr($source, $start, ($next === false ? strlen($source) : $next) - $start);
}

// get_product reads the id with a default and stops with a translated JSON
// error before it reads a product that was not found.
function test_api_public_get_product_missing_id()
{
	$body = api_public_missing_fields_handler('pg_panel_get_product');

	pg_assert_false(strpos($body, "e(\$request['product']['id'])") !== false, 'the id is read without a check');

	$stop = strpos($body, "lang('Product not found.')");
	$read = strpos($body, "\$product_raw['code']");
	pg_assert_true($stop !== false, 'no translated "not found" answer');
	pg_assert_true(($stop !== false) && ($read !== false) && ($stop < $read), 'the product is read before the stop');
}

// get_installment_options reads card and price with a default.
function test_api_public_installment_options_missing_fields()
{
	$body = api_public_missing_fields_handler('pg_panel_get_installment_options');

	pg_assert_false(strpos($body, "= \$request['card'];") !== false, 'card read without a default');
	pg_assert_false(strpos($body, "= \$request['price'];") !== false, 'price read without a default');
	pg_assert_contains("lang('Card number and price are required.')", $body);
}

// The JSON error texts of the public storefront actions go through lang().
function test_api_public_error_texts_are_translated()
{
	pg_assert_contains("lang('Card number and price are required.')", api_public_missing_fields_handler('pg_panel_eo_get_installments'));
	pg_assert_contains("lang('Product ID is required.')", api_public_missing_fields_handler('pg_panel_get_cross_sell_for_product'));
}
