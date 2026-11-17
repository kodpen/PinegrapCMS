<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the quantity prices a product page lists
 * (includes/fn/ecommerce.php: pg_offer_quantity_break_rows(),
 * pg_offer_quantity_breaks(); includes/fn/widgets_lists_product.php:
 * pg_sw_list_product_quantity_price_rows()). A break is a running offer
 * that asks for this many of the product and discounts it; the contact
 * group condition keeps it to the group's customers. The reader runs in a
 * PHP process of its own on a fake database layer, one signed-in user each.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The price of each break: an amount off or a percentage off the list
// price, rounded half away from zero, never below zero; the lowest price
// for one quantity; ordered by quantity.
function test_offer_quantity_break_rows_price()
{
	$rows = pg_offer_quantity_break_rows(array(
		array('quantity' => 10, 'amount' => 0, 'percentage' => '12.5'),
		array('quantity' => 5, 'amount' => 150, 'percentage' => 0),
		array('quantity' => 10, 'amount' => 0, 'percentage' => '10'),
		array('quantity' => 50, 'amount' => 5000, 'percentage' => 0),
	), 1999, 1999);
	pg_assert_same(array(
		array('quantity' => 5, 'price' => 1849),
		array('quantity' => 10, 'price' => 1749),
		array('quantity' => 50, 'price' => 0),
	), $rows, '12.5% of 19.99 is 2.49875: 17.49 each, the lower of two offers for 10');
}

// A break that is not cheaper than the visitor's own price (a member's
// agreed price), or than the break for fewer pieces, is never shown: the
// cart would not charge it. No break, no quantity, no discount: nothing.
function test_offer_quantity_break_rows_kept()
{
	$rows = pg_offer_quantity_break_rows(array(
		array('quantity' => 5, 'amount' => 0, 'percentage' => 10),
		array('quantity' => 10, 'amount' => 0, 'percentage' => 5),
		array('quantity' => 20, 'amount' => 0, 'percentage' => 30),
	), 10000, 8500);
	pg_assert_same(array(array('quantity' => 20, 'price' => 7000)), $rows, 'only what beats 85.00 and the smaller breaks');

	$rows = pg_offer_quantity_break_rows(array(
		array('quantity' => 5, 'amount' => 0, 'percentage' => 20),
		array('quantity' => 10, 'amount' => 0, 'percentage' => 10),
	), 10000, 10000);
	pg_assert_same(array(array('quantity' => 5, 'price' => 8000)), $rows, '10 or more at a higher price than 5 or more is dropped');

	pg_assert_same(array(), pg_offer_quantity_break_rows(array(), 10000, 10000), 'no break');
	pg_assert_same(array(), pg_offer_quantity_break_rows(null, 10000, 10000), 'nothing read');
	pg_assert_same(array(), pg_offer_quantity_break_rows(array(
		array('quantity' => 0, 'amount' => 100, 'percentage' => 0),
		array('quantity' => 5, 'amount' => 0, 'percentage' => 0),
		'junk',
	), 10000, 10000), 'no quantity, no discount');
	pg_assert_same(array(), pg_offer_quantity_break_rows(array(array('quantity' => 5, 'amount' => 100, 'percentage' => 0)), 0, 0), 'a free product has no break');
}

// Runs $body in a PHP process of its own with the offers code on a fake
// database: product 11 at 100.00 in product group 2; user 5's contact is
// in contact group 3, user 6's in group 4; $offers the rows the break
// query answers; $conditions the offer_conditions rows by offer id.
// Answers the JSON $body prints, null when PHP cannot be started.
function offer_quantity_breaks_run($user_id, $offers, $conditions, $body)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgoffqb');

	if ($file === false) {
		return null;
	}

	$script = '<?php' . "\n"
		. 'define("PG_FUNCTIONS_DIR", ' . var_export(PG_FUNCTIONS_DIR, true) . ');' . "\n"
		. ($user_id ? 'define("USER_ID", ' . (int) $user_id . ');' . "\n" : '')
		. '$GLOBALS["offers"] = ' . var_export($offers, true) . ';' . "\n"
		. '$GLOBALS["conds"] = ' . var_export($conditions, true) . ';' . "\n"
		. <<<'PHP'
$GLOBALS['contacts'] = array(5 => 50, 6 => 60);
$GLOBALS['xref'] = array(50 => array(3), 60 => array(4));
$GLOBALS['sql'] = array();

function e($value) { return addslashes((string) $value); }
function lang($text) { return is_array($text) ? $text['string'] : $text; }
function pg_schema_has($table, $column = null) { return true; }

function db_value($sql)
{
	if (preg_match("/SELECT user_contact FROM user WHERE user_id = '(\d+)'/", $sql, $m)) {
		return isset($GLOBALS['contacts'][(int) $m[1]]) ? $GLOBALS['contacts'][(int) $m[1]] : 0;
	}
	return 0;
}

function db_values($sql)
{
	if (preg_match("/FROM contacts_contact_groups_xref\s+WHERE contact_id = '(\d+)'/", $sql, $m)) {
		return isset($GLOBALS['xref'][(int) $m[1]]) ? $GLOBALS['xref'][(int) $m[1]] : array();
	}
	if (preg_match("/FROM products_groups_xref\s+WHERE product_group = '(\d+)'/", $sql, $m)) {
		return ((int) $m[1] === 2) ? array(11, 12) : array();
	}
	return array();
}

function db_items($sql)
{
	if (preg_match("/FROM offer_conditions\s+WHERE offer_id = '(\d+)'/", $sql, $m)) {
		return isset($GLOBALS['conds'][(int) $m[1]]) ? $GLOBALS['conds'][(int) $m[1]] : array();
	}
	if (strpos($sql, 'offer_rules_products_xref') !== false) {
		$GLOBALS['sql'][] = preg_replace('/\s+/', ' ', trim($sql));
		return $GLOBALS['offers'];
	}
	return array();
}

function db_item($sql) { return null; }
function db($sql) { return true; }

require PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php';

PHP
		. $body;

	file_put_contents($file, $script);

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// One offer row as the break query reads it: running from yesterday to
// tomorrow, "contains $quantity of these products" and $percentage off
// product 11 (or, with $group, every product of product group 2).
function offer_quantity_breaks_offer($id, $quantity, $percentage, $extra = array())
{
	return $extra + array(
		'id'                          => $id,
		'status'                      => 'enabled',
		'start_date'                  => date('Y-m-d', time() - 86400),
		'end_date'                    => date('Y-m-d', time() + 86400),
		'required_quantity'           => $quantity,
		'discount_product_product_id' => 11,
		'discount_product_group_id'   => 0,
		'discount_product_target'     => 'product',
		'discount_product_amount'     => 0,
		'discount_product_percentage' => $percentage,
	);
}

// The breaks read for product 11 and the visitor in front of the page: an
// offer for contact group 3 reaches its member only; an offer not running,
// one aimed at another product or at the cheapest item, one that also asks
// about the rest of the cart, gives no break; a product group target does.
function test_offer_quantity_breaks_read()
{
	$offers = array(
		offer_quantity_breaks_offer(1, 10, 10),
		offer_quantity_breaks_offer(2, 20, 25),
		offer_quantity_breaks_offer(3, 5, 50, array('status' => 'disabled')),
		offer_quantity_breaks_offer(4, 6, 50, array('end_date' => date('Y-m-d', time() - 86400))),
		offer_quantity_breaks_offer(5, 7, 50, array('discount_product_product_id' => 99)),
		offer_quantity_breaks_offer(6, 8, 50, array('discount_product_target' => 'cheapest')),
		offer_quantity_breaks_offer(7, 9, 50),
		offer_quantity_breaks_offer(8, 30, 40, array('discount_product_product_id' => 0, 'discount_product_group_id' => 2, 'discount_product_target' => 'group')),
	);
	$conditions = array(
		2 => array(array('type' => 'contact group', 'int_value' => 3, 'text_value' => '')),
		7 => array(array('type' => 'cart quantity', 'int_value' => 3, 'text_value' => '')),
	);
	$body = 'echo json_encode(array("breaks" => pg_offer_quantity_breaks(11, 10000, 10000), "sql" => $GLOBALS["sql"]));';

	$member = offer_quantity_breaks_run(5, $offers, $conditions, $body);

	if ($member === null) {
		return;
	}

	pg_assert_false(isset($member['error']), isset($member['error']) ? $member['error'] : 'ran');
	pg_assert_same(array(
		array('quantity' => 10, 'price' => 9000),
		array('quantity' => 20, 'price' => 7500),
		array('quantity' => 30, 'price' => 6000),
	), $member['breaks'], 'the group member gets the group break');
	pg_assert_same(1, count($member['sql']), 'one query');
	pg_assert_contains("x.product_id = '11'", $member['sql'][0]);
	pg_assert_contains('offers.require_code = 0', $member['sql'][0], 'an offer that waits for a code is not shown');

	foreach (array(6 => 'a customer outside the group', 0 => 'a guest') as $user_id => $label) {
		$r = offer_quantity_breaks_run($user_id, $offers, $conditions, $body);
		pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
		pg_assert_same(array(
			array('quantity' => 10, 'price' => 9000),
			array('quantity' => 30, 'price' => 6000),
		), $r['breaks'], $label . ' gets the breaks for everybody');
	}

	$none = offer_quantity_breaks_run(5, array(), array(), $body);
	pg_assert_same(array(), $none['breaks'], 'no running offer, no break');
	$bad = offer_quantity_breaks_run(5, $offers, $conditions, 'echo json_encode(array("breaks" => pg_offer_quantity_breaks(0, 10000, 10000), "sql" => $GLOBALS["sql"]));');
	pg_assert_same(array(), $bad['breaks'], 'no product');
	pg_assert_same(array(), $bad['sql'], 'nothing read without a product');
}

// One row per break: the quantity, the price as text with the currency
// sign (an entity stored for it decoded, the list pass escapes once) and
// the sentence "10 or more: each for …".
function test_offer_quantity_price_rows()
{
	$rows = pg_sw_list_product_quantity_price_rows(array(
		array('quantity' => 10, 'price' => 9000),
		array('quantity' => 20, 'price' => 7550),
		'junk',
	), '&#8378;');
	pg_assert_same(2, count($rows));
	pg_assert_same(array('__qty_break_quantity', '__qty_break_price', '__qty_break_label'), array_keys($rows[0]));
	pg_assert_same('10', $rows[0]['__qty_break_quantity']);
	pg_assert_same(pg_money_text(90, '&#8378;', defined('VISITOR_CURRENCY_CODE_FOR_OUTPUT') ? (string) VISITOR_CURRENCY_CODE_FOR_OUTPUT : ''), $rows[0]['__qty_break_price']);
	pg_assert_contains('₺', $rows[1]['__qty_break_price'], 'the sign as a character');
	pg_assert_same(false, strpos($rows[1]['__qty_break_price'], '&#'), 'no entity left');
	pg_assert_same('20 or more: each for ' . $rows[1]['__qty_break_price'], $rows[1]['__qty_break_label']);
	pg_assert_same(array(), pg_sw_list_product_quantity_price_rows(array()), 'no break, no row');
	pg_assert_same(array(), pg_sw_list_product_quantity_price_rows(null), 'nothing read');
}
