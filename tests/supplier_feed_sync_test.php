<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/supplier_feed_sync.php: a record's values, the
 * missing-field and mass-drop brakes, a buy price held under half or over ten
 * times its last value, and what one record does to its product (only what
 * moved is written, closing and reopening, a person's off stays off), and the
 * run's failed lines.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/supplier_feed_sync.php');

function supplier_feed_sync_test_fields()
{
	return array(
		'supplier_key' => 'code',
		'barcode'      => 'ean',
		'buy_price'    => 'cost',
		'stock'        => 'qty',
		'vat'          => 'kdv',
		'category'     => 'cat',
		'brand'        => '',
	);
}

function supplier_feed_sync_test_item($values = array())
{
	return array_merge(array(
		'buy_price'      => 1000,
		'held_price'     => 0,
		'stock'          => 5,
		'follow_price'   => 1,
		'closed_by_feed' => 0,
	), $values);
}

function supplier_feed_sync_test_product($values = array())
{
	return array_merge(array(
		'price'              => 1500,
		'inventory'          => 1,
		'inventory_quantity' => 5,
		'enabled'            => 1,
		'tax_rate'           => null,
	), $values);
}

// Margin 50 %: a buy price of 10,00 sells at 15,00.
function supplier_feed_sync_test_context($values = array())
{
	return array_merge(array(
		'rule'          => array('margin' => 50, 'fixed' => 0, 'rounding' => 'none', 'floor_margin' => 0),
		'rate'          => 1.0,
		'vat_included'  => false,
		'home_decimals' => 2,
		'feed_decimals' => 2,
	), $values);
}

function supplier_feed_sync_test_plan($item, $product, $buy, $stock, $context = array(), $vat = null)
{
	return pg_supplier_feed_plan_item(
		supplier_feed_sync_test_item($item),
		supplier_feed_sync_test_product($product),
		array('buy' => $buy, 'stock' => $stock, 'vat' => $vat),
		supplier_feed_sync_test_context($context)
	);
}

function test_supplier_feed_sync_record_values()
{
	$values = pg_supplier_feed_record_values(
		array('code' => ' A-100 ', 'ean' => '8690000000001', 'cost' => '1.234,50 TL', 'qty' => '10+', 'kdv' => '20', 'cat' => 'Mutfak'),
		supplier_feed_sync_test_fields()
	);

	pg_assert_same('A-100', $values['key'], 'the code, trimmed');
	pg_assert_same('8690000000001', $values['barcode'], 'barcode');
	pg_assert_same(123450, $values['buy'], 'a Turkish amount in minor units');
	pg_assert_same(10, $values['stock'], 'stock text');
	pg_assert_same(20.0, $values['vat'], 'VAT percent');
	pg_assert_same('Mutfak', $values['category'], 'category');
	pg_assert_same('', $values['brand'], 'an unmapped field is empty');

	$empty = pg_supplier_feed_record_values(array('code' => 'B', 'cost' => '', 'qty' => ''), supplier_feed_sync_test_fields());

	pg_assert_same(null, $empty['buy'], 'no buy price');
	pg_assert_same(0, $empty['stock'], 'no stock is 0');
	pg_assert_same(null, $empty['vat'], 'no VAT');
}

function test_supplier_feed_sync_missing_fields()
{
	$records = array(
		array('code' => 'A', 'cost' => '1', 'qty' => '2', 'cat' => 'X'),
		array('code' => 'B', 'cost' => '1', 'qty' => '2', 'ean' => '1'),
	);

	pg_assert_same(array('vat'), pg_supplier_feed_missing_fields($records, supplier_feed_sync_test_fields()), 'only a field no record has; an unmapped one is never missing');
	pg_assert_same(array('supplier_key', 'buy_price', 'stock'), pg_supplier_feed_missing_fields(array(array('Kod' => 'A')), array(
		'supplier_key' => 'code', 'buy_price' => 'cost', 'stock' => 'qty',
	)), 'a renamed feed loses its fields');
	pg_assert_same(array(), pg_supplier_feed_missing_fields(array(), supplier_feed_sync_test_fields()), 'no records: the empty-feed brake answers instead');
}

function test_supplier_feed_sync_drop_brake()
{
	pg_assert_true(pg_supplier_feed_drop_brake(10, 6), 'six of ten dropped');
	pg_assert_false(pg_supplier_feed_drop_brake(10, 5), 'half is not more than half');
	pg_assert_true(pg_supplier_feed_drop_brake(1, 1), 'the one product dropped');
	pg_assert_false(pg_supplier_feed_drop_brake(0, 0), 'nothing matched yet');
}

function test_supplier_feed_sync_price_held()
{
	pg_assert_true(pg_supplier_feed_price_held(1000, 499), 'under half');
	pg_assert_false(pg_supplier_feed_price_held(1000, 500), 'exactly half');
	pg_assert_true(pg_supplier_feed_price_held(1000, 10001), 'over ten times');
	pg_assert_false(pg_supplier_feed_price_held(1000, 10000), 'exactly ten times');
	pg_assert_false(pg_supplier_feed_price_held(0, 1), 'no last price: nothing to compare');
	pg_assert_false(pg_supplier_feed_price_held(1000, null), 'no buy price');
}

function test_supplier_feed_sync_only_what_moved()
{
	$same = supplier_feed_sync_test_plan(array(), array(), 1000, 5);

	pg_assert_same(null, $same['price'], 'same buy price: no price write');
	pg_assert_same(null, $same['stock'], 'same stock: no stock write');
	pg_assert_same(null, $same['enable'], 'nothing switched');
	pg_assert_same('', $same['outcome'], 'nothing counted');
	pg_assert_false($same['held'], 'not held');

	$stock = supplier_feed_sync_test_plan(array(), array(), 1000, 8);

	pg_assert_same(null, $stock['price'], 'stock moved: the price stays');
	pg_assert_same(8, $stock['stock'], 'stock set');
	pg_assert_same('changed', $stock['outcome'], 'counted as changed');
	pg_assert_same(8, $stock['item']['stock'], 'the item keeps the stock');

	$price = supplier_feed_sync_test_plan(array(), array(), 1100, 5);

	pg_assert_same(1650, $price['price'], 'buy price moved: the rule gives the new price');
	pg_assert_same(null, $price['stock'], 'the stock stays');
	pg_assert_same(1100, $price['item']['buy_price'], 'the item keeps the buy price');
	pg_assert_same('changed', $price['outcome'], 'changed');

	$untracked = supplier_feed_sync_test_plan(array(), array('inventory' => 0, 'inventory_quantity' => 0), 1000, 9);

	pg_assert_same(null, $untracked['stock'], 'an untracked product gets no stock');
	pg_assert_same('', $untracked['outcome'], 'nothing moved on it');
}

function test_supplier_feed_sync_price_switches()
{
	$no_rule = supplier_feed_sync_test_plan(array(), array(), 1100, 5, array('rule' => null));

	pg_assert_same(null, $no_rule['price'], 'no saved rule: no price');
	pg_assert_same(1100, $no_rule['item']['buy_price'], 'the buy price is still kept');

	$own = supplier_feed_sync_test_plan(array('follow_price' => 0), array(), 1100, 5);

	pg_assert_same(null, $own['price'], 'Price from the feed off: no price');

	$no_rate = supplier_feed_sync_test_plan(array(), array(), 1100, 5, array('rate' => 0.0));

	pg_assert_same(null, $no_rate['price'], 'no rate: no price');

	$vat = supplier_feed_sync_test_plan(array(), array('price' => 0), 1200, 5, array('vat_included' => true), 20.0);

	pg_assert_same(1000, $vat['item']['buy_price'], 'a price with VAT is kept net');
	pg_assert_same(1500, $vat['price'], 'and the rule works on the net price');

	$product_vat = supplier_feed_sync_test_plan(array(), array('price' => 0, 'tax_rate' => '10.000'), 1100, 5, array('vat_included' => true));

	pg_assert_same(1000, $product_vat['item']['buy_price'], 'no VAT in the feed: the product\'s rate');
}

function test_supplier_feed_sync_price_held_on_item()
{
	$low = supplier_feed_sync_test_plan(array(), array(), 400, 5);

	pg_assert_true($low['held'], 'under half is held');
	pg_assert_same(null, $low['price'], 'the old price stays');
	pg_assert_same(400, $low['item']['held_price'], 'the item holds it');
	pg_assert_false(isset($low['item']['buy_price']), 'the last buy price is kept');
	pg_assert_same('', $low['outcome'], 'held is not changed');

	$high = supplier_feed_sync_test_plan(array(), array(), 10001, 6);

	pg_assert_true($high['held'], 'over ten times is held');
	pg_assert_same(null, $high['price'], 'no price');
	pg_assert_same(6, $high['stock'], 'stock still follows');

	$back = supplier_feed_sync_test_plan(array('held_price' => 400), array(), 1050, 5);

	pg_assert_false($back['held'], 'back in range');
	pg_assert_same(0, $back['item']['held_price'], 'the held price is let go');
	pg_assert_same(1050, $back['item']['buy_price'], 'the new buy price is kept');
}

function test_supplier_feed_sync_closing_and_reopening()
{
	$tracked = supplier_feed_sync_test_plan(array(), array(), 1000, 0);

	pg_assert_same(0, $tracked['stock'], 'tracked: stock 0');
	pg_assert_same(null, $tracked['enable'], 'tracked: the page stays');
	pg_assert_same('closed', $tracked['outcome'], 'closed');
	pg_assert_same(1, $tracked['item']['closed_by_feed'], 'the feed closed it');

	$reopen = supplier_feed_sync_test_plan(array('closed_by_feed' => 1, 'stock' => 0), array('inventory_quantity' => 0), 1000, 4);

	pg_assert_same(4, $reopen['stock'], 'stock back');
	pg_assert_same('reopened', $reopen['outcome'], 'reopened');
	pg_assert_same(0, $reopen['item']['closed_by_feed'], 'no longer closed by the feed');

	$untracked = supplier_feed_sync_test_plan(array(), array('inventory' => 0), 1000, 0);

	pg_assert_same(0, $untracked['enable'], 'untracked: turned off');
	pg_assert_same('closed', $untracked['outcome'], 'closed');
	pg_assert_same(1, $untracked['item']['closed_by_feed'], 'the feed closed it');

	$untracked_back = supplier_feed_sync_test_plan(array('closed_by_feed' => 1), array('inventory' => 0, 'enabled' => 0), 1000, 3);

	pg_assert_same(1, $untracked_back['enable'], 'untracked: on again, the feed closed it');
	pg_assert_same('reopened', $untracked_back['outcome'], 'reopened');

	$missing = pg_supplier_feed_plan_item(supplier_feed_sync_test_item(), supplier_feed_sync_test_product(), array('buy' => null, 'stock' => 0, 'vat' => null), supplier_feed_sync_test_context());

	pg_assert_same(0, $missing['stock'], 'missing from the pull: stock 0');
	pg_assert_same('closed', $missing['outcome'], 'closed');
	pg_assert_false(isset($missing['item']['buy_price']), 'no buy price read: the last one stays');

	$closed_again = supplier_feed_sync_test_plan(array('closed_by_feed' => 1, 'stock' => 0), array('inventory_quantity' => 0), 1000, 0);

	pg_assert_same('', $closed_again['outcome'], 'already closed: nothing counted');
	pg_assert_same(null, $closed_again['stock'], 'nothing written');
}

function test_supplier_feed_sync_person_off_stays_off()
{
	$untracked = supplier_feed_sync_test_plan(array('closed_by_feed' => 0), array('inventory' => 0, 'enabled' => 0), 1000, 7);

	pg_assert_same(null, $untracked['enable'], 'a person\'s off is never turned on');
	pg_assert_same('', $untracked['outcome'], 'nothing counted');

	$tracked = supplier_feed_sync_test_plan(array('closed_by_feed' => 0, 'stock' => 0), array('enabled' => 0, 'inventory_quantity' => 0), 1000, 7);

	pg_assert_same(null, $tracked['enable'], 'tracked: only the stock follows');
	pg_assert_same(7, $tracked['stock'], 'stock set');
	pg_assert_same('changed', $tracked['outcome'], 'changed, not reopened');

	$restocked = supplier_feed_sync_test_plan(array('closed_by_feed' => 1, 'stock' => 0), array('inventory_quantity' => 3), 1000, 3);

	pg_assert_same(0, $restocked['item']['closed_by_feed'], 'a product with stock is no longer the feed\'s closed one');
	pg_assert_same('', $restocked['outcome'], 'nothing else moved');
}

function test_supplier_feed_sync_failed_lines()
{
	$detail = pg_supplier_feed_detail_add('', array(array('line' => 'A', 'reason' => 'x')), 3);
	$detail = pg_supplier_feed_detail_add($detail, array(array('line' => 'B', 'reason' => 'y'), array('line' => 'C', 'reason' => 'z'), array('line' => 'D', 'reason' => 'w')), 3);
	$decoded = json_decode($detail, true);

	pg_assert_same(3, count($decoded['failed']), 'never more than the limit');
	pg_assert_same('C', $decoded['failed'][2]['line'], 'the first ones are kept');
	pg_assert_same('', $decoded['reason'], 'no brake reason');

	$braked = json_decode(pg_supplier_feed_detail_add($detail, array(), 3, 'The feed is empty.'), true);

	pg_assert_same('The feed is empty.', $braked['reason'], 'a brake\'s reason');
	pg_assert_same(3, count($braked['failed']), 'the lines stay');
}
