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

	pg_assert_contains('<span class="translateable col fg-secondary">PO Number:</span>', $html, 'the label with its colon');
	pg_assert_contains('<span class="col text-end">PO-7 &amp; 8</span>', $html, 'the value as given');
	pg_assert_same(1, substr_count($html, '<div class="row">'), 'one row');
}

function test_order_view_row_wide_address_line()
{
	$html = pg_order_view_row('Address 2', 'Flat 3', true);

	pg_assert_contains('<span class="translateable col-auto fg-secondary">Address 2:</span>', $html, 'the label in its own column');
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
