<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the counter sale's cart (includes/local_sale.php): which product
 * a scanned code adds and when a line's quantity change is refused for
 * stock.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/pinegrap/includes/local_sale.php');

// Runs the cart actions in a PHP process of their own, against a fake db
// layer that answers by matching the SQL (the real local_sale.php and
// ecommerce.php, nothing else of the site). Every case starts with no rows
// and no writes. Null when PHP cannot be started again.
function local_sale_cart_test_run()
{
	static $result = false;

	if ($result !== false) {
		return $result;
	}

	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return $result = null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgsale');

	if ($file === false) {
		return $result = null;
	}

	$script = <<<'PHP'
define('PG_FUNCTIONS_DIR', $pg_dir);
define('BARCODE_ENABLED', true);

$GLOBALS['fake_rules'] = array();
$GLOBALS['fake_writes'] = array();

function fake_answer($kind, $sql)
{
	foreach ($GLOBALS['fake_rules'] as $rule) {
		if (($rule[0] === $kind) && preg_match($rule[1], $sql)) {
			return $rule[2];
		}
	}
	return ($kind === 'items') ? array() : null;
}

function fake_case($rules)
{
	$GLOBALS['fake_rules'] = $rules;
	$GLOBALS['fake_writes'] = array();
}

function db_value($sql) { return fake_answer('value', $sql); }
function db_item($sql) { return fake_answer('item', $sql); }
function db_items($sql) { return fake_answer('items', $sql); }
function db($sql) { $GLOBALS['fake_writes'][] = preg_replace('/\s+/', ' ', $sql); return true; }
function e($value) { return addslashes((string) $value); }
function escape($value) { return addslashes((string) $value); }
function escape_like($value) { return addcslashes((string) $value, '%_'); }

function lang($text)
{
	if (is_array($text)) {
		$string = $text['string'];
		foreach (array_values((array) $text['vars']) as $index => $var) {
			$string = str_replace('{var:' . ($index + 1) . '}', (string) $var, $string);
		}
		return $string;
	}
	return $text;
}

require $pg_dir . '/includes/fn/ecommerce.php';
require $pg_dir . '/includes/local_sale.php';

$mug = array('id' => 7, 'name' => 'MUG-7', 'short_description' => 'Blue mug', 'enabled' => 1, 'inventory' => 0, 'inventory_quantity' => 0);
$cup = array('id' => 9, 'name' => '8690001', 'short_description' => 'Red cup', 'enabled' => 1, 'inventory' => 0, 'inventory_quantity' => 0);

$out = array();

// 8690001 is a barcode of the mug and the product code of the cup.
$overlap = array(
	array('value', "/FROM product_barcodes WHERE barcode = '8690001'/", 7),
	array('value', "/FROM products\s+WHERE \(name = '8690001'\) AND \(id != '7'\)/", 9),
	array('item', "/^SELECT id, short_description FROM products WHERE id = '7'/", array('id' => 7, 'short_description' => 'Blue mug')),
	array('item', "/SELECT id, name, short_description, enabled, inventory, inventory_quantity FROM products WHERE id = '7'/", $mug),
	array('item', "/FROM products WHERE name = '8690001'/", $cup),
);

fake_case($overlap);
$found = local_sale_find_product('8690001');
$out['overlap_find'] = array('product' => $found['product'] ? (int) $found['product']['id'] : 0, 'refusal' => (string) ($found['refusal'] ?? ''));

// A product that does reach the cart needs the whole site; here it means
// the scan was not refused.
fake_case($overlap);
try {
	$added = local_sale_add_item('8690001', 0, '1');
} catch (Throwable $e) {
	$added = array('ok' => true, 'message' => 'added to the cart', 'field' => '');
}
$out['overlap_add'] = array('ok' => $added['ok'], 'message' => $added['message'], 'field' => $added['field'], 'writes' => $GLOBALS['fake_writes']);

// 8690002 is the mug's barcode and nobody's product code.
fake_case(array(
	array('value', "/FROM product_barcodes WHERE barcode = '8690002'/", 7),
	array('item', "/SELECT id, name, short_description, enabled, inventory, inventory_quantity FROM products WHERE id = '7'/", $mug),
));
$found = local_sale_find_product('8690002');
$out['barcode_find'] = array('product' => $found['product'] ? (int) $found['product']['id'] : 0, 'refusal' => (string) ($found['refusal'] ?? ''));

// MUG-7 is the mug's product code and no barcode.
fake_case(array(
	array('value', "/FROM products\s+WHERE \(name = 'MUG-7'\)/", 7),
	array('item', "/SELECT id, name, short_description, enabled, inventory, inventory_quantity FROM products WHERE id = '7'/", $mug),
	array('item', "/FROM products WHERE name = 'MUG-7'/", $mug),
));
$found = local_sale_find_product('MUG-7');
$out['code_find'] = array('product' => $found['product'] ? (int) $found['product']['id'] : 0, 'refusal' => (string) ($found['refusal'] ?? ''));

// A cart line of 5 mugs while the shelf holds 3 (sold elsewhere since).
$_SESSION['ecommerce']['local_sale_order_id'] = 50;
$line = array(
	array('value', "/SELECT status FROM orders WHERE id = '50'/", 'incomplete'),
	array('item', "/FROM order_items oi\s+LEFT JOIN products p ON p.id = oi.product_id\s+WHERE oi.id = '11'/", array('id' => 11, 'product_id' => 7, 'quantity' => 5, 'product_name' => 'MUG-7', 'name' => 'MUG-7', 'short_description' => 'Blue mug', 'inventory' => 1, 'inventory_quantity' => 3)),
	array('value', "/SUM\(quantity\)/", 0),
);

foreach (array('4', '5', '6') as $quantity) {
	fake_case($line);
	$set = local_sale_set_qty(11, $quantity);
	$out['set_' . $quantity] = array('ok' => $set['ok'], 'message' => $set['message'], 'writes' => $GLOBALS['fake_writes']);
}

// The same line once the shelf is empty.
$line[1][2]['inventory_quantity'] = 0;
fake_case($line);
$set = local_sale_set_qty(11, '4');
$out['set_empty_shelf'] = array('ok' => $set['ok'], 'message' => $set['message'], 'writes' => $GLOBALS['fake_writes']);

echo json_encode($out);
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$decoded = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($decoded) || !isset($decoded['overlap_find'])) {
		return $result = array('error' => implode("\n", $lines));
	}

	return $result = $decoded;
}

function local_sale_cart_test_result($key)
{
	$result = local_sale_cart_test_run();

	if ($result === null) {
		return null;
	}

	pg_assert_true(!isset($result['error']), 'the cart script ran: ' . ($result['error'] ?? ''));

	return $result[$key] ?? null;
}

// A code that is a barcode of one product and the product code of another
// is refused, as the barcode stock screens refuse it: the barcode's product
// used to go into the cart while the operator scanned the other one's label.
function test_local_sale_scan_refuses_a_code_of_two_products()
{
	$found = local_sale_cart_test_result('overlap_find');

	if ($found === null) {
		return;
	}

	pg_assert_same(0, $found['product'], 'no product picked');
	pg_assert_same('8690001 is a barcode of Blue mug and the product code of another product. Change one of them, then scan again.', $found['refusal'], 'the stock screens\' words');

	$added = local_sale_cart_test_result('overlap_add');
	pg_assert_same(false, $added['ok'], 'not added');
	pg_assert_same($found['refusal'], $added['message'], 'the refusal is shown');
	pg_assert_same('product', $added['field'], 'on the scan box');
	pg_assert_same(array(), $added['writes'], 'nothing written');
}

function test_local_sale_scan_finds_a_barcode_or_a_code()
{
	$barcode = local_sale_cart_test_result('barcode_find');

	if ($barcode === null) {
		return;
	}

	pg_assert_same(array('product' => 7, 'refusal' => ''), $barcode, 'a barcode alone');
	pg_assert_same(array('product' => 7, 'refusal' => ''), local_sale_cart_test_result('code_find'), 'a product code alone');
}

// Lowering a line above the stock is never refused: the minus button and a
// smaller typed figure said "no more can be added" and kept the line where
// completing the sale refuses it.
function test_local_sale_set_qty_lowers_a_line_above_the_stock()
{
	$lower = local_sale_cart_test_result('set_4');

	if ($lower === null) {
		return;
	}

	pg_assert_same(true, $lower['ok'], 'five to four with three on the shelf');
	pg_assert_contains("UPDATE order_items SET quantity = '4' WHERE id = '11'", implode("\n", $lower['writes']), 'written');

	$same = local_sale_cart_test_result('set_5');
	pg_assert_same(true, $same['ok'], 'the same figure again');

	$raise = local_sale_cart_test_result('set_6');
	pg_assert_same(false, $raise['ok'], 'raising past the stock is refused');
	pg_assert_same('Only 3 in stock; no more can be added.', $raise['message'], 'refusal');
	pg_assert_same(array(), $raise['writes'], 'nothing written');

	$empty = local_sale_cart_test_result('set_empty_shelf');
	pg_assert_same(true, $empty['ok'], 'lowered with nothing on the shelf');
}
