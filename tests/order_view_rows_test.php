<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel order screen's card rows (includes/fn/order_view.php):
 * a row is printed only with a value, the address type and the country are
 * named in the panel language, the requested arrival date shows the arrival
 * date's name and falls back to its code, the contact link never reads as a
 * bare number.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_view_row_empty_value_prints_nothing()
{
	pg_assert_same('', pg_order_view_row('Company', ''), 'no value, no row');
	pg_assert_same('', pg_order_view_row('Company', '   '), 'blank value, no row');
	pg_assert_same('', pg_order_view_row('Company', null), 'null value, no row');
	pg_assert_same('', pg_order_view_row('Address 2', '', true), 'no wide row either');
}

function test_order_view_row_prints_label_and_value()
{
	$html = pg_order_view_row('PO Number', 'PO-7 &amp; 8');

	pg_assert_contains('<span class="translateable fg-secondary">PO Number</span>', $html, 'the label, no colon');
	pg_assert_contains('<span class="text-end text-break">PO-7 &amp; 8</span>', $html, 'the value as given');
	pg_assert_same(1, substr_count($html, '<div class="d-flex justify-content-between gap-3">'), 'one line');
}

function test_order_view_row_wide_address_line()
{
	$html = pg_order_view_row('Address 2', 'Flat 3', true);

	pg_assert_contains('<span class="translateable col-auto fg-secondary">Address 2</span>', $html, 'the label in its own column');
	pg_assert_contains('<span class="col offset-1" style="font-size:110%;font-weight:500">Flat 3</span>', $html, 'the value emphasised');
}

function test_order_view_address_type_named()
{
	pg_assert_same(h(lang('Residential')), pg_order_view_address_type('residential'), 'residential');
	pg_assert_same(h(lang('Business')), pg_order_view_address_type('business'), 'business');
	pg_assert_same(h(lang('Business')), pg_order_view_address_type(' Business '), 'case and spaces do not matter');
	pg_assert_same('', pg_order_view_address_type(''), 'none');
	pg_assert_same('', pg_order_view_address_type(null), 'null');
}

function test_order_view_address_type_other_value_escaped()
{
	pg_assert_same('Po Box', pg_order_view_address_type('po box'), 'an unknown value is shown as stored, capitalised');
	pg_assert_same('&lt;b&gt;x', pg_order_view_address_type('<b>x'), 'escaped');
}

function test_order_view_country_name()
{
	pg_assert_same('', pg_order_view_country(''), 'none');
	pg_assert_same('', pg_order_view_country(null), 'null');
	pg_assert_same('Türkiye', pg_order_view_country('Türkiye'), 'a stored name stays');

	$name = pg_order_view_country('TR');
	pg_assert_true(($name !== '') && ($name !== 'TR'), 'a code becomes a name: ' . $name);
	pg_assert_same($name, pg_order_view_country(' tr '), 'case and spaces do not matter');
}

function test_order_view_arrival_codes_distinct()
{
	$ship_tos = array(
		array('ship_to_id' => 1, 'arrival_date_code' => 'AT-ONCE'),
		array('ship_to_id' => 2, 'arrival_date_code' => ''),
		array('ship_to_id' => 3, 'arrival_date_code' => 'SDATE'),
		array('ship_to_id' => 4, 'arrival_date_code' => 'AT-ONCE '),
		array('ship_to_id' => 5),
		'not a row',
	);

	pg_assert_same(array('AT-ONCE', 'SDATE'), pg_order_view_arrival_codes($ship_tos), 'each code once, blanks out');
	pg_assert_same(array(), pg_order_view_arrival_codes(array()), 'no recipients');
}

function test_order_view_arrival_text_name_and_date()
{
	$names = array('AT-ONCE' => 'Bir defada', 'SDATE' => 'Requested date & time');

	pg_assert_same('Bir defada', pg_order_view_arrival_text('', 'AT-ONCE', $names), 'a dateless choice shows its name');
	pg_assert_same('9 Nov 2026 (Requested date &amp; time)', pg_order_view_arrival_text('9 Nov 2026', 'SDATE', $names), 'the date, then the name');
	pg_assert_same('9 Nov 2026', pg_order_view_arrival_text('9 Nov 2026', '', $names), 'no code, the date alone');
	pg_assert_same('', pg_order_view_arrival_text('', '', $names), 'nothing');
}

function test_order_view_arrival_text_falls_back_to_code()
{
	pg_assert_same('AT-ONCE', pg_order_view_arrival_text('', 'AT-ONCE', array()), 'the row is gone: the code');
	pg_assert_same('9 Nov 2026 (OLD&lt;CODE&gt;)', pg_order_view_arrival_text('9 Nov 2026', 'OLD<CODE>', array()), 'escaped');
	pg_assert_same('AT-ONCE', pg_order_view_arrival_text('', 'AT-ONCE', array('AT-ONCE' => '  ')), 'a blank name counts as none');
}

function test_order_view_contact_text_name_company_email_id()
{
	pg_assert_same('Ayşe Yılmaz', pg_order_view_contact_text(140, 'Ayşe', 'Yılmaz', 'Kodpen', 'a@example.com'), 'the name first');
	pg_assert_same('Ayşe', pg_order_view_contact_text(140, 'Ayşe', '', '', ''), 'a first name alone');
	pg_assert_same('Kodpen', pg_order_view_contact_text(140, '', '', 'Kodpen', 'a@example.com'), 'then the company');
	pg_assert_same('a@example.com', pg_order_view_contact_text(140, '', '', '', 'a@example.com'), 'then the e-mail address');
	pg_assert_same('#140', pg_order_view_contact_text('140', '', null, '', ''), 'then the id, marked as one');
	pg_assert_same('O&#039;Neil &lt;x&gt;', pg_order_view_contact_text(1, "O'Neil", '<x>', '', ''), 'escaped');
}

// The first card of a column loses its top margin, whichever card it is;
// the others keep theirs, and empty cards leave no gap.
function test_order_view_card_column_first_card_flush()
{
	$ship = '<div class="card mt-5"><div class="card-body">Ship</div></div>';
	$returns = '  <div class="card mt-5"><div class="card-body">Returns</div></div>';

	$html = pg_order_view_card_column(array($ship, '', $returns));
	pg_assert_same(0, strpos($html, '<div class="card"><div class="card-body">Ship'), 'first card flush');
	pg_assert_same(1, substr_count($html, 'card mt-5'), 'the second card keeps its margin');

	$html = pg_order_view_card_column(array('', '   ', $returns));
	pg_assert_same(0, strpos($html, '<div class="card"><div class="card-body">Returns'), 'no ship-to: the next card is flush');

	pg_assert_same('', pg_order_view_card_column(array('', '')), 'no cards, nothing');
}

function test_order_view_line_fact_caption_and_value()
{
	pg_assert_same('', pg_order_view_line_fact('Price', ''), 'no value, no fact');
	pg_assert_same('', pg_order_view_line_fact('Price', '  '), 'blank value, no fact');

	$html = pg_order_view_line_fact('Price', '$5.00');
	pg_assert_contains('<div class="small fg-secondary">Price</div>', $html, 'the caption above');
	pg_assert_contains('<div class="text-break">$5.00</div>', $html, 'the value under it');

	pg_assert_contains('<div class="text-break fw-semibold">$10.00</div>', pg_order_view_line_fact('Total', '$10.00', '', true), 'the total is set off');

	$field = pg_order_view_line_fact('Shipped Qty', '<input id="order_item_id_4_shipped_quantity">', 'order_item_id_4_shipped_quantity');
	pg_assert_contains('<label class="d-block small fg-secondary" for="order_item_id_4_shipped_quantity">Shipped Qty</label>', $field, 'the caption names the field');
}

function test_order_view_line_layout()
{
	$html = pg_order_view_line('Product ID / SKU: Mug', '<img class="order-line-image" src="mug.jpg" alt=""/>', 'A blue mug', '<div class="order-line-fact">f</div>', '<div class="card order-line-part">g</div>');

	pg_assert_contains('<div class="list-group-item order-line">', $html, 'one list item');
	pg_assert_contains('<div class="flex-shrink-0 pg-print-none"><img class="order-line-image"', $html, 'the thumbnail, left out of print');
	pg_assert_contains('<div class="fw-semibold text-break">Product ID / SKU: Mug</div>', $html, 'the name');
	pg_assert_contains('<div class="fg-secondary text-break">A blue mug</div>', $html, 'the description under it');
	pg_assert_contains('<div class="order-line-facts mt-3"><div class="order-line-fact">f</div></div>', $html, 'the facts grid');
	pg_assert_contains('<div class="card order-line-part">g</div>', $html, 'the gift cards and forms');

	$bare = pg_order_view_line('Mug', '', '', '');
	pg_assert_false(strpos($bare, 'flex-shrink-0') !== false, 'no image, no thumbnail column');
	pg_assert_false(strpos($bare, 'fg-secondary text-break') !== false, 'no description, no line for it');
}

function test_order_view_line_part_caption()
{
	$html = pg_order_view_line_part('Gift Card (1 of 2)', '<table></table>');

	pg_assert_contains('<div class="card order-line-part mt-4">', $html, 'a box under the line');
	pg_assert_contains('fg-secondary">Gift Card (1 of 2)</div>', $html, 'its caption');
	pg_assert_contains('<div class="card-body"><table></table></div>', $html, 'what it holds');
}
