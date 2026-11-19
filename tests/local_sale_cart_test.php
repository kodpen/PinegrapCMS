<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the counter sale's cart (includes/local_sale.php): which product
 * a scanned code adds, a gift card refused, when a line's quantity change is
 * refused (stock, a gift card line's limit) and the name a line keeps once
 * its product is gone.
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
require $pg_dir . '/includes/fn/order_completion.php';
require $pg_dir . '/includes/local_sale.php';

$mug = array('id' => 7, 'name' => 'MUG-7', 'short_description' => 'Blue mug', 'enabled' => 1, 'inventory' => 0, 'inventory_quantity' => 0);
$cup = array('id' => 9, 'name' => '8690001', 'short_description' => 'Red cup', 'enabled' => 1, 'inventory' => 0, 'inventory_quantity' => 0);

$out = array();

// 8690001 is a barcode of the mug and the product code of the cup.
$overlap = array(
	array('value', "/FROM product_barcodes WHERE barcode = '8690001'/", 7),
	array('value', "/FROM products\s+WHERE \(name = '8690001'\) AND \(id != '7'\)/", 9),
	array('item', "/^SELECT id, short_description FROM products WHERE id = '7'/", array('id' => 7, 'short_description' => 'Blue mug')),
	array('item', "/SELECT id, name, short_description, enabled, inventory, inventory_quantity, gift_card FROM products WHERE id = '7'/", $mug),
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
	array('item', "/SELECT id, name, short_description, enabled, inventory, inventory_quantity, gift_card FROM products WHERE id = '7'/", $mug),
));
$found = local_sale_find_product('8690002');
$out['barcode_find'] = array('product' => $found['product'] ? (int) $found['product']['id'] : 0, 'refusal' => (string) ($found['refusal'] ?? ''));

// MUG-7 is the mug's product code and no barcode.
fake_case(array(
	array('value', "/FROM products\s+WHERE \(name = 'MUG-7'\)/", 7),
	array('item', "/SELECT id, name, short_description, enabled, inventory, inventory_quantity, gift_card FROM products WHERE id = '7'/", $mug),
	array('item', "/FROM products WHERE name = 'MUG-7'/", $mug),
));
$found = local_sale_find_product('MUG-7');
$out['code_find'] = array('product' => $found['product'] ? (int) $found['product']['id'] : 0, 'refusal' => (string) ($found['refusal'] ?? ''));

// A gift card scanned, and picked from the search by id: the counter has no
// recipient form, so neither reaches the cart.
$card = array('id' => 40, 'name' => 'GIFT-40', 'short_description' => 'Gift card', 'enabled' => 1, 'inventory' => 0, 'inventory_quantity' => 0, 'gift_card' => 1);
$gift_rules = array(
	array('value', "/FROM product_barcodes WHERE barcode = '8690040'/", 40),
	array('item', "/SELECT id, name, short_description, enabled, inventory, inventory_quantity, gift_card FROM products WHERE id = '40'/", $card),
);

foreach (array('gift_scan' => array('8690040', 0), 'gift_pick' => array('', 40)) as $key => $how) {
	fake_case($gift_rules);
	$added = local_sale_add_item($how[0], $how[1], '1');
	$out[$key] = array('ok' => $added['ok'], 'message' => $added['message'], 'field' => $added['field'], 'writes' => $GLOBALS['fake_writes']);
}

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

// A gift card line holds at most 100 units, as on the site's cart.
$gift = array(
	array('value', "/SELECT status FROM orders WHERE id = '50'/", 'incomplete'),
	array('item', "/WHERE oi.id = '13'/", array('id' => 13, 'product_id' => 40, 'quantity' => 100, 'product_name' => 'GIFT-40', 'name' => 'GIFT-40', 'short_description' => 'Gift card', 'inventory' => 0, 'inventory_quantity' => 0)),
	array('value', "/SELECT products.gift_card\s+FROM order_items/", 1),
	array('value', "/SUM\(quantity\)/", 0),
);

fake_case($gift);
$set = local_sale_set_qty(13, '101');
$out['gift_101'] = array('ok' => $set['ok'], 'message' => $set['message'], 'writes' => $GLOBALS['fake_writes']);

fake_case($gift);
$set = local_sale_set_qty(13, '99');
$out['gift_99'] = array('ok' => $set['ok'], 'message' => $set['message'], 'writes' => $GLOBALS['fake_writes']);

// A line whose product was deleted from the catalogue is removed by name.
fake_case(array(
	array('value', "/SELECT status FROM orders WHERE id = '50'/", 'incomplete'),
	array('item', "/WHERE oi.id = '12'/", array('id' => 12, 'product_id' => 30, 'quantity' => 1, 'product_name' => 'LAMP-30', 'name' => null, 'short_description' => null, 'inventory' => null, 'inventory_quantity' => null)),
));
$set = local_sale_set_qty(12, '0');
$out['remove_deleted'] = array('ok' => $set['ok'], 'message' => $set['message']);

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

// A line whose product was deleted keeps the name the order line was
// written with: the cart row, its buttons and the messages had none.
function test_local_sale_deleted_product_line_keeps_its_name()
{
	pg_assert_same('LAMP-30', local_sale_product_label(array('name' => null, 'short_description' => null, 'product_name' => 'LAMP-30')), 'the order line\'s name');
	pg_assert_same('Blue mug', local_sale_product_label(array('name' => 'MUG-7', 'short_description' => 'Blue mug', 'product_name' => 'MUG-7')), 'the short description first');
	pg_assert_same('MUG-7', local_sale_product_label(array('name' => 'MUG-7', 'short_description' => '', 'product_name' => 'OLD')), 'then the product code');

	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/local_sale.php');
	pg_assert_contains('SELECT oi.id, oi.product_id, oi.product_name, oi.quantity, oi.price, oi.added_by_offer,', $source, 'the cart lines read it');

	$removed = local_sale_cart_test_result('remove_deleted');

	if ($removed === null) {
		return;
	}

	pg_assert_same(true, $removed['ok'], 'removed');
	pg_assert_same('LAMP-30 removed from the cart.', $removed['message'], 'named');
}

// A gift card line issues one card per unit up to 100: the counter refuses
// more as every cart door does, with the same words. A typed 101 used to be
// written and an add past the limit said "Product not found or is
// disabled."
function test_local_sale_gift_card_line_limit()
{
	$over = local_sale_cart_test_result('gift_101');

	if ($over === null) {
		return;
	}

	pg_assert_same(false, $over['ok'], '101 refused');
	pg_assert_same('A gift card line can hold at most 100 units', $over['message'], 'the cart\'s words');
	pg_assert_same(array(), $over['writes'], 'nothing written');

	$under = local_sale_cart_test_result('gift_99');
	pg_assert_same(true, $under['ok'], '99 taken');
}

// A gift card is issued from the recipient form of the site's checkout,
// which the counter does not have: a scan and a pick from the search are
// refused with the reason, before anything is written. It used to be sold
// with no card issued.
function test_local_sale_gift_card_is_refused()
{
	$reason = 'Gift cards cannot be sold at the counter: each card needs the recipient form of the site\'s checkout.';

	pg_assert_same($reason, local_sale_product_refusal(array('gift_card' => '1')), 'a gift card');
	pg_assert_same('', local_sale_product_refusal(array('gift_card' => '0')), 'any other product');
	pg_assert_same('', local_sale_product_refusal(array()), 'a row without the column');

	foreach (array('gift_scan' => 'scanned', 'gift_pick' => 'picked from the search') as $key => $label) {
		$added = local_sale_cart_test_result($key);

		if ($added === null) {
			return;
		}

		pg_assert_same(false, $added['ok'], $label . ': refused');
		pg_assert_same('Gift card: ' . $reason, $added['message'], $label . ': the reason, named');
		pg_assert_same('product', $added['field'], $label . ': on the scan box');
		pg_assert_same(array(), $added['writes'], $label . ': nothing written');
	}

	// The search list shows the reason in place of the stock.
	$page = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/add_order.php');
	$script = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/add_order.js');
	pg_assert_contains("'refusal' => local_sale_product_refusal(\$row),", $page, 'the search sends the reason');
	pg_assert_contains("price, taxable, tax_rate, gift_card", $page, 'and reads the column');
	pg_assert_contains("if (p.refusal) {", $script, 'the list shows it');
}
