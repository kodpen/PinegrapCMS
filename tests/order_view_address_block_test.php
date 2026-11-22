<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order screen's address block (includes/fn/order_view.php):
 * an address as the lines of a postal address with its empty parts left
 * out, the country named, the markup escaped; the billing address read off
 * the order row; "Same as the shipping address"; the count of the
 * contact's orders.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * A full Turkish address as a ship_tos row keeps it.
 */
function order_view_address_test_address(array $over = array())
{
	return array_merge(array(
		'ship_to_id' => 7,
		'first_name' => 'Ada',
		'last_name' => 'Yılmaz',
		'company' => 'Test Ltd',
		'address_1' => 'Hürriyet Mah. Pamuk Sokak No:22',
		'address_2' => 'Daire 4',
		'city' => 'Tire',
		'state' => 'İzmir',
		'zip_code' => '35960',
		'country' => 'TR',
	), $over);
}

function test_order_view_address_lines_full()
{
	pg_assert_same(array(
		'Ada Yılmaz · Test Ltd',
		'Hürriyet Mah. Pamuk Sokak No:22',
		'Daire 4',
		'Tire, İzmir 35960, ' . pg_order_view_country('TR'),
	), pg_order_view_address_lines(order_view_address_test_address()), 'every part on its line, the country named');
}

function test_order_view_address_lines_leave_empty_parts_out()
{
	$address = order_view_address_test_address(array('company' => '', 'address_2' => '  ', 'state' => '', 'zip_code' => ''));
	pg_assert_same(array('Ada Yılmaz', 'Hürriyet Mah. Pamuk Sokak No:22', 'Tire, ' . pg_order_view_country('TR')), pg_order_view_address_lines($address), 'no company, second line, state or postcode');

	$address = order_view_address_test_address(array('first_name' => '', 'last_name' => '', 'city' => '', 'country' => ''));
	pg_assert_same(array('Test Ltd', 'Hürriyet Mah. Pamuk Sokak No:22', 'Daire 4', 'İzmir 35960'), pg_order_view_address_lines($address), 'the company alone, no city or country');

	pg_assert_same(array(), pg_order_view_address_lines(array()), 'nothing stored: no line');
	pg_assert_same(array(), pg_order_view_address_lines(array('first_name' => null, 'city' => ' ')), 'only blanks: no line');
}

function test_order_view_address_lines_keep_an_older_country_name()
{
	$lines = pg_order_view_address_lines(order_view_address_test_address(array('country' => 'Türkiye')));
	pg_assert_same('Tire, İzmir 35960, Türkiye', $lines[3], 'a stored name is kept as it is');
}

function test_order_view_address_block_escapes()
{
	$html = pg_order_view_address_block(order_view_address_test_address(array('first_name' => '<b>Ada</b>', 'address_1' => 'A & B "Street"')), 'pg_billing_address');

	pg_assert_contains('<address class="pg-address" id="pg_billing_address">', $html, 'the block and its id');
	pg_assert_contains('<span class="pg-address-name">&lt;b&gt;Ada&lt;/b&gt; Yılmaz</span>', $html, 'the name escaped and set apart');
	pg_assert_contains('A &amp; B &quot;Street&quot;', $html, 'an address line escaped');
	pg_assert_false(strpos($html, '<b>Ada') !== false, 'no raw markup from the address');
	pg_assert_same('', pg_order_view_address_block(array()), 'an empty address prints nothing');
}

function test_order_view_address_block_without_name()
{
	$html = pg_order_view_address_block(order_view_address_test_address(array('first_name' => '', 'last_name' => '', 'company' => '')));

	pg_assert_false(strpos($html, 'pg-address-name') !== false, 'no name line to set apart');
	pg_assert_contains('<address class="pg-address">Hürriyet Mah. Pamuk Sokak No:22<br>', $html, 'the street is the first line');
}

function test_order_view_billing_address_reads_the_order_row()
{
	$order = array(
		'billing_first_name' => 'Ada', 'billing_last_name' => 'Yılmaz', 'billing_company' => 'Test Ltd',
		'billing_address_1' => 'Street 1', 'billing_address_2' => '', 'billing_city' => 'Tire',
		'billing_state' => 'İzmir', 'billing_zip_code' => '35960', 'billing_country' => 'TR', 'billing_email_address' => 'a@example.com',
	);

	pg_assert_same(array(
		'first_name' => 'Ada', 'last_name' => 'Yılmaz', 'company' => 'Test Ltd', 'address_1' => 'Street 1', 'address_2' => '',
		'city' => 'Tire', 'state' => 'İzmir', 'zip_code' => '35960', 'country' => 'TR',
	), pg_order_view_billing_address($order), 'the address parts, nothing else');
	pg_assert_same('', pg_order_view_billing_address(array())['city'], 'a missing column reads empty');
}

function test_order_view_same_address()
{
	$ship = order_view_address_test_address();
	$billing = order_view_address_test_address(array('first_name' => ' ada ', 'state' => 'İZMİR', 'country' => 'tr'));
	unset($billing['ship_to_id']);

	pg_assert_true(pg_order_view_same_address($billing, array($ship)), 'equal after trimming, whatever the case');
	pg_assert_true(pg_order_view_same_address($billing, array($ship, $ship)), 'the lines of one address count once');
	pg_assert_true(pg_order_view_same_address($billing, array(array('ship_to_id' => 0), $ship)), 'lines with no address do not count');

	pg_assert_false(pg_order_view_same_address($billing, array($ship, order_view_address_test_address(array('ship_to_id' => 8)))), 'two addresses');
	pg_assert_false(pg_order_view_same_address($billing, array()), 'no address');
	pg_assert_false(pg_order_view_same_address($billing, array(array('ship_to_id' => 0))), 'only lines with no address');
	pg_assert_false(pg_order_view_same_address(array_merge($billing, array('address_2' => 'Daire 5')), array($ship)), 'one part differs');
	pg_assert_false(pg_order_view_same_address(array_merge($billing, array('company' => '')), array($ship)), 'an empty part against a filled one');
	pg_assert_false(pg_order_view_same_address(array(), array(order_view_address_test_address(array_fill_keys(array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'zip_code', 'country'), '')))), 'two empty addresses are no address');
}

function test_order_view_orders_count_text()
{
	pg_assert_same('', pg_order_view_orders_count_text(0), 'none counted: no text');
	pg_assert_same('First order', pg_order_view_orders_count_text(1), 'one order');
	pg_assert_same('4 orders', pg_order_view_orders_count_text(4), 'more');
	pg_assert_same('4 orders', pg_order_view_orders_count_text('4'), 'a count as the database returns it');
}

function test_order_view_contact_orders_count_is_one_query()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/view_order.php');

	pg_assert_same(1, substr_count($source, 'pg_order_view_contact_orders_count('), 'the screen asks once');
	pg_assert_contains("status <> 'incomplete'", file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/order_view.php'), 'an incomplete order is not counted');
}

function test_order_view_kv_row()
{
	pg_assert_same('<div><dt>PO &amp; Ref</dt><dd><b>7</b></dd></div>', pg_order_view_kv('PO & Ref', '<b>7</b>'), 'the label escaped, the value as built');
	pg_assert_same('', pg_order_view_kv('PO Number', ''), 'no value: no row');
	pg_assert_same('', pg_order_view_kv('PO Number', '  '), 'blanks: no row');
	pg_assert_same('<div><dt>Tax</dt><dd>0</dd></div>', pg_order_view_kv('Tax', '0'), 'a zero is a value');
}
