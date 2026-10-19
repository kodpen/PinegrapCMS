<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checkout legal documents in includes/fn/legal_documents.php:
 * filling a template, conditionals, escaping, the line table, the values built
 * from an order, unknown placeholders and the built-in templates.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A formatter that makes minor units easy to read in assertions.
function legal_documents_test_money($minor)
{
	return number_format($minor / 100, 2, '.', '') . ' TL';
}

// Values are escaped and their line breaks become <br>; markup values go in as they are.
function test_legal_documents_render_escapes_text_and_keeps_markup()
{
	$out = pg_legal_document_render(
		'<p>^^buyer_name^^</p><div>^^buyer_address^^</div>^^order_lines^^',
		array('buyer_name' => 'Ali <b>&</b> Veli', 'buyer_address' => "Street 1\nİzmir"),
		array('order_lines' => '<table></table>')
	);

	pg_assert_same('<p>Ali &lt;b&gt;&amp;&lt;/b&gt; Veli</p><div>Street 1<br>' . "\n" . 'İzmir</div><table></table>', $out);
}

// A bracket shows only when its field has a value; ||other replaces an empty one.
function test_legal_documents_render_resolves_conditionals()
{
	$template = '[[Tax: ^^tax^^. ]][[Order ^^order_number^^||Not placed yet]]';

	pg_assert_same('Tax: 18.00 TL. Order 1042', pg_legal_document_render($template, array('tax' => '18.00 TL', 'order_number' => '1042')), 'both filled');
	pg_assert_same('Not placed yet', pg_legal_document_render($template, array('tax' => '', 'order_number' => '  ')), 'both empty');
	pg_assert_same('Not placed yet', pg_legal_document_render($template, array()), 'both missing');
}

// An unknown placeholder prints nothing rather than its raw name.
function test_legal_documents_render_drops_unknown_fields()
{
	pg_assert_same('Dear .', pg_legal_document_render('Dear ^^buyer_nmae^^.', array('buyer_name' => 'Ali')));
}

// The decision is made on the raw value, not on the escaped one.
function test_legal_documents_render_conditional_reads_raw_value()
{
	pg_assert_same('[x] &lt;', pg_legal_document_render('[[[x] ^^buyer_company^^]]', array('buyer_company' => '<')));
}

// Misspelt names are reported once each, in order; known names are not.
function test_legal_documents_unknown_placeholders()
{
	pg_assert_same(array(), pg_legal_document_unknown_placeholders('^^seller_name^^ [[^^tax^^]] ^^order_lines^^'), 'all known');
	pg_assert_same(array('buyer_nmae', 'totl'), pg_legal_document_unknown_placeholders('^^buyer_nmae^^ ^^totl^^ ^^buyer_nmae^^ ^^total^^'), 'unknown');
	pg_assert_same(array(), pg_legal_document_unknown_placeholders(''), 'empty template');
}

// Item names are escaped; amounts come from the formatter.
function test_legal_documents_lines_table()
{
	$html = pg_legal_document_lines_html(
		array(array('name' => 'Mug <XL>', 'quantity' => 2, 'unit_price' => 12550, 'line_total' => 25100)),
		'legal_documents_test_money'
	);

	pg_assert_contains('<td>Mug &lt;XL&gt;</td>', $html, 'name');
	pg_assert_contains('>2</td>', $html, 'quantity');
	pg_assert_contains('>125.50 TL</td>', $html, 'unit price');
	pg_assert_contains('>251.00 TL</td>', $html, 'line total');
	pg_assert_contains('<thead>', $html, 'header');
}

// Zero amounts leave their field empty (so [[...]] drops the row), the total
// and subtotal always print, and both discounts add up.
function test_legal_documents_values_from_order()
{
	$values = pg_legal_document_values(
		array(
			'billing_first_name' => 'Ayşe', 'billing_last_name' => 'Yılmaz', 'billing_company' => '',
			'billing_address_1' => 'Kordon 5', 'billing_address_2' => '', 'billing_zip_code' => '35220',
			'billing_city' => 'İzmir', 'billing_state' => '', 'billing_country' => 'Türkiye',
			'billing_phone_number' => '555', 'billing_email_address' => 'a@example.test',
			'subtotal' => 0, 'discount' => 500, 'gift_card_discount' => 250, 'tax' => 0,
			'shipping' => 4990, 'surcharge' => 0, 'total' => 0, 'order_number' => '',
		),
		array(),
		array(array('name' => 'Ayşe Yılmaz', 'address' => "Kordon 5\n35220 İzmir", 'method' => 'Cargo', 'arrival_date' => '')),
		array('name' => 'Shop Ltd', 'address' => 'Alsancak', 'email' => 's@example.test', 'tax_office' => '', 'tax_number' => '123', 'website' => ''),
		array('payment_method' => 'Card', 'today' => '19.10.2026'),
		'legal_documents_test_money'
	);

	$text = $values['text'];

	pg_assert_same('Ayşe Yılmaz', $text['buyer_name'], 'buyer name');
	pg_assert_same("Kordon 5\n35220 İzmir\nTürkiye", $text['buyer_address'], 'buyer address');
	pg_assert_same("Ayşe Yılmaz\nKordon 5\n35220 İzmir", $text['delivery_address'], 'delivery');
	pg_assert_same('Cargo', $text['shipping_method'], 'method');
	pg_assert_same('0.00 TL', $text['subtotal'], 'subtotal always');
	pg_assert_same('0.00 TL', $text['total'], 'total always');
	pg_assert_same('7.50 TL', $text['discount'], 'discounts added');
	pg_assert_same('', $text['tax'], 'no tax');
	pg_assert_same('49.90 TL', $text['shipping'], 'shipping');
	pg_assert_same('', $text['order_number'], 'no number before submit');
	pg_assert_same('123', $text['seller_tax_number'], 'seller');
	pg_assert_contains('<table', $values['html']['order_lines'], 'line table');

	foreach (array_keys(pg_legal_document_placeholders()) as $name) {
		pg_assert_true(array_key_exists($name, $text) || array_key_exists($name, $values['html']), 'value for ' . $name);
	}
}

// With no recipient the goods go to the buyer's address.
function test_legal_documents_values_without_recipient()
{
	$values = pg_legal_document_values(array('billing_address_1' => 'Kordon 5', 'billing_city' => 'İzmir'), array(), array(), array(), array(), 'legal_documents_test_money');

	pg_assert_same("Kordon 5\nİzmir", $values['text']['delivery_address']);
}

// Both built-in templates exist in Turkish and English, use known fields only,
// and an unknown language falls back to English.
function test_legal_documents_default_templates()
{
	foreach (array('preinfo', 'contract') as $type) {
		foreach (array('tr', 'en') as $language) {
			$template = pg_legal_document_default_template($type, $language);

			pg_assert_true(trim($template) !== '', $type . '.' . $language . ' exists');
			pg_assert_same(array(), pg_legal_document_unknown_placeholders($template), $type . '.' . $language . ' fields');
			pg_assert_contains('^^order_lines^^', $template, $type . '.' . $language . ' lines');
			pg_assert_contains('^^total^^', $template, $type . '.' . $language . ' total');
		}

		pg_assert_same(pg_legal_document_default_template($type, 'en'), pg_legal_document_default_template($type, 'de'), $type . ' fallback');
	}

	pg_assert_same('', pg_legal_document_default_template('kvkk', 'tr'), 'not a template type');
	pg_assert_same('', pg_legal_document_default_template('../preinfo', 'tr'), 'no path');
}

// Without the setting (constant undefined here) the feature is off.
function test_legal_documents_off_without_setting()
{
	pg_assert_false(pg_legal_documents_enabled());
	pg_assert_same(0, pg_legal_documents_store(5, array('preinfo')));
}
