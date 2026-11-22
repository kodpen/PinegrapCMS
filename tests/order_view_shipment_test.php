<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for View Order's shipment card (includes/fn/order_view.php): the
 * line row with its heading row, the shipped quantity "/ ordered", the
 * card titles ("Shipment 1 / 2", "Items not shipped", "Items") and the
 * district in a shipping address's place line.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_view_line_row_parts()
{
	$html = pg_order_view_line(array(
		'name_html' => '<a href="edit_product.php?id=3">Blue mug</a>',
		'image_html' => '<img class="pg-line-image" src="mug.jpg" alt="">',
		'meta' => array('MUG-1', '', 'Colour: Blue'),
		'amount_html' => '2 × $5.00',
		'shipped_html' => pg_order_view_line_shipped('<input id="order_item_id_4_shipped_quantity">', 2),
		'total_html' => '$10.00',
		'parts_html' => '<div class="card order-line-part">g</div>',
	));

	pg_assert_contains('<div class="pg-line-row">', $html, 'one row');
	pg_assert_contains('<div class="pg-line-media pg-print-none"><img class="pg-line-image"', $html, 'the picture, left out of print');
	pg_assert_contains('<span class="pg-line-title"><a href="edit_product.php?id=3">Blue mug</a></span>', $html, 'the name, a link');
	pg_assert_contains('<span class="pg-line-meta">MUG-1 · Colour: Blue</span>', $html, 'the grey line, an empty part left out');
	pg_assert_contains('2 × $5.00</div>', $html, 'quantity × price');
	pg_assert_contains('<input id="order_item_id_4_shipped_quantity"><span class="pg-line-of">/ 2</span>', $html, 'the field over the ordered quantity');
	pg_assert_contains('<div class="pg-line-total pg-tabular"><span class="visually-hidden">' . h(lang('Total')) . ': </span>$10.00</div>', $html, 'the total, named for a screen reader');
	pg_assert_contains('<div class="pg-line-parts"><div class="card order-line-part">g</div></div>', $html, 'forms and gift cards under the row');
}

function test_order_view_line_row_bare()
{
	$html = pg_order_view_line(array('name_html' => 'Mug', 'amount_html' => '1 × $5.00', 'total_html' => '$5.00'));

	pg_assert_contains('<div class="pg-line-media pg-print-none"><span class="pg-line-image pg-line-image-none"><i class="bi bi-box-seam" aria-hidden="true"></i></span></div>', $html, 'no picture: the box icon keeps the column');
	pg_assert_false(strpos($html, 'pg-line-meta') !== false, 'no grey line without its parts');
	pg_assert_false(strpos($html, 'pg-line-shipped') !== false, 'a line that does not ship has no shipped cell');
	pg_assert_false(strpos($html, 'pg-line-parts') !== false, 'nothing under the row');
}

function test_order_view_line_shipped_cell()
{
	pg_assert_same('', pg_order_view_line_shipped('', 3), 'an incomplete order shows no shipped quantity');
	pg_assert_same('<div class="pg-line-shipped">1<span class="pg-line-of">/ 3</span></div>', pg_order_view_line_shipped('1', '3'), 'a cancelled order: the number over the ordered one');
}

function test_order_view_line_list_and_head()
{
	pg_assert_same('', pg_order_view_line_list('', true), 'no line, no list');

	$ships = pg_order_view_line_list('<div class="pg-line-row">r</div>', true);
	pg_assert_contains('<div class="pg-line-list pg-line-list-ship">', $ships, 'a list with the shipped column');
	pg_assert_contains('<div class="pg-line-head" aria-hidden="true">', $ships, 'the heading row, once, hidden from a screen reader');
	pg_assert_contains('<span>' . h(lang('Shipped')) . '</span>', $ships, 'the shipped column named');
	pg_assert_contains('<div class="pg-line-row">r</div></div>', $ships, 'the rows after the heading');

	$plain = pg_order_view_line_list('<div class="pg-line-row">r</div>', false);
	pg_assert_contains('<div class="pg-line-list">', $plain, 'no shipped column');
	pg_assert_false(strpos($plain, '<span>' . h(lang('Shipped')) . '</span>') !== false, 'and no heading for it');
	pg_assert_contains('<span>' . h(lang('Qty × Price')) . '</span>', $plain, 'quantity and price named');
}

function test_order_view_shipment_titles()
{
	pg_assert_same(lang('Shipment'), pg_order_view_shipment_title(1, 1), 'one address: Shipment');
	pg_assert_same(lang(array('string' => 'Shipment {var:1} / {var:2}', 'vars' => array(2, 3))), pg_order_view_shipment_title(2, 3), 'two or more: numbered');
	pg_assert_same(lang('Items not shipped'), pg_order_view_unshipped_title(true), 'lines with no address beside shipments');
	pg_assert_same(lang('Items'), pg_order_view_unshipped_title(false), 'an order with no address');
}

function test_order_view_address_lines_district()
{
	$address = array('first_name' => 'Ada', 'last_name' => 'Yılmaz', 'address_1' => 'Pamuk Sokak 22', 'district' => 'Bornova', 'city' => 'Merkez', 'state' => 'İzmir', 'zip_code' => '35040', 'country' => '');
	pg_assert_same(array('Ada Yılmaz', 'Pamuk Sokak 22', 'Bornova, Merkez, İzmir 35040'), pg_order_view_address_lines($address), 'the district before the city');

	$address['district'] = 'MERKEZ';
	pg_assert_same('Merkez, İzmir 35040', pg_order_view_address_lines($address)[2], 'a district that repeats the city is left out');
}
