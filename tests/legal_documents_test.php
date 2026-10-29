<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the checkout legal documents in includes/fn/legal_documents.php:
 * filling a template, conditionals, escaping, the line table, the values built
 * from an order, unknown placeholders and the built-in templates, and the
 * values a checkout's document link posts (assets/js/pg_legal_documents.js).
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

// A Turkish starter site starts with the documents switched on; any other
// language and a restored backup (no starter language) start off.
function test_legal_documents_on_for_new_site()
{
	pg_assert_true(pg_legal_documents_on_for_new_site('tr'), 'Turkish starter');
	pg_assert_true(pg_legal_documents_on_for_new_site(' TR '), 'case and spaces');
	pg_assert_false(pg_legal_documents_on_for_new_site('en'), 'English starter');
	pg_assert_false(pg_legal_documents_on_for_new_site('de'), 'another language');
	pg_assert_false(pg_legal_documents_on_for_new_site(''), 'a backup');
	pg_assert_false(pg_legal_documents_on_for_new_site(null), 'nothing');
}

// The installer turns the switch on once the upgrades have added its column
// (before them the update would find no column and change nothing), and
// before the installation finishes.
function test_legal_documents_install_switches_on_after_upgrades()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');

	$upgrades = strpos($source, 'install_run_upgrades($versions, $installed_version_key');
	$seed = strpos($source, 'pg_legal_documents_on_for_new_site(get_starter_site_language($install_directory_path))');
	$done = strpos($source, "add_install_step(lang('The installation is complete')");

	pg_assert_true($upgrades !== false, 'the upgrades of a new site');
	pg_assert_true($seed !== false, 'the starter language decides');
	pg_assert_true(($seed > $upgrades) && ($seed < $done), 'after the upgrades, before the end');
	pg_assert_same(1, substr_count($source, 'UPDATE config SET ecommerce_legal_documents = 1'), 'one write');
	pg_assert_contains("install_column_exists('config', 'ecommerce_legal_documents')", substr($source, $seed, 400), 'only where the column is');
}

// Without the setting (constant undefined here) the feature is off.
function test_legal_documents_off_without_setting()
{
	pg_assert_false(pg_legal_documents_enabled());
	pg_assert_same(0, pg_legal_documents_store(5, array('preinfo')));
}

// A cart's amounts come from its rows; the checkout's own total decides,
// and what it takes off the row sums is the discount.
function test_legal_documents_cart_order_amounts()
{
	$lines = array(
		array('line_total' => 20000, 'tax' => 3600),
		array('line_total' => 5000, 'tax' => 0),
	);
	$ship_tos = array(array('cost' => 1500), array('cost' => 0));

	$order = pg_legal_document_cart_order(array('order_number' => '17', 'surcharge' => 99, 'gift_card_discount' => 10), $lines, $ship_tos, array('total' => 28100));

	pg_assert_same(25000, $order['subtotal'], 'subtotal');
	pg_assert_same(3600, $order['tax'], 'tax');
	pg_assert_same(1500, $order['shipping'], 'shipping');
	pg_assert_same(2000, $order['discount'], 'discount is what the checkout took off');
	pg_assert_same(0, $order['gift_card_discount'], 'gift card counted in the discount');
	pg_assert_same(0, $order['surcharge'], 'no surcharge before the payment method');
	pg_assert_same(28100, $order['total'], 'checkout total');
	pg_assert_same('', $order['order_number'], 'no number on a cart');

	$plain = pg_legal_document_cart_order(array(), $lines, $ship_tos, array());

	pg_assert_same(30100, $plain['total'], 'row sums without a checkout total');
	pg_assert_same(0, $plain['discount'], 'no discount without a checkout total');

	$higher = pg_legal_document_cart_order(array(), $lines, $ship_tos, array('total' => 40000));

	pg_assert_same(0, $higher['discount'], 'a higher checkout total is no negative discount');
	pg_assert_same(11400, $higher['shipping'], 'the rest is shipping the rows do not know');
	pg_assert_same(40000, $higher['total'], 'the checkout total still decides');

	$unchosen = pg_legal_document_cart_order(array(), $lines, $ship_tos, array('total' => 28600));

	pg_assert_same(0, $unchosen['shipping'], 'goods and tax only: no shipping chosen yet');
	pg_assert_same(0, $unchosen['discount'], 'a stale shipping row is no discount');
}

// An order discount lowers the tax in its own proportion, as the checkout
// and submit_order.php do, so the document's discount and tax are the
// checkout's and not the tax difference added to the discount.
function test_legal_documents_cart_order_discount_lowers_the_tax()
{
	$lines = array(array('line_total' => 10000, 'tax' => 1800));

	$order = pg_legal_document_cart_order(array(), $lines, array(), array('total' => 10620, 'order_discount' => 1000));

	pg_assert_same(10000, $order['subtotal'], 'subtotal');
	pg_assert_same(1000, $order['discount'], 'the order discount');
	pg_assert_same(1620, $order['tax'], 'tax after the discount: 1800 - round(1800 * 1000 / 10000)');
	pg_assert_same(10620, $order['total'], 'checkout total');

	$no_total = pg_legal_document_cart_order(array(), $lines, array(), array('order_discount' => 1000));

	pg_assert_same(10620, $no_total['total'], 'without a drawn total the sums agree with the checkout');
	pg_assert_same(1000, $no_total['discount'], 'and the discount is the order discount');

	$gift_card = pg_legal_document_cart_order(array(), $lines, array(array('cost' => 500)), array('total' => 10120, 'order_discount' => 1000));

	pg_assert_same(500, $gift_card['shipping'], 'shipping kept');
	pg_assert_same(2000, $gift_card['discount'], 'what else the checkout took off (a gift card) adds to it');
	pg_assert_same(1620, $gift_card['tax'], 'the tax is still the discounted one');

	$stale = pg_legal_document_cart_order(array(), $lines, array(array('cost' => 500)), array('total' => 10620, 'order_discount' => 1000));

	pg_assert_same(0, $stale['shipping'], 'discounted goods and tax only: no shipping chosen yet');
	pg_assert_same(1000, $stale['discount'], 'a stale shipping row adds no discount');

	$capped = pg_legal_document_cart_order(array(), $lines, array(), array('order_discount' => 99999));

	pg_assert_same(10000, $capped['discount'], 'never more than the subtotal');
	pg_assert_same(0, $capped['tax'], 'nothing left to tax');

	$odd = pg_legal_document_cart_order(array(), array(array('line_total' => 2990, 'tax' => 538)), array(), array('order_discount' => 299));

	pg_assert_same(484, $odd['tax'], 'rounded as submit_order.php: 538 - round(538 * 299 / 2990)');
}

// With the lines' rates kept, the tax after an order discount is taken again
// per rate on what the discount leaves of the lines, as submit_order.php
// takes it (pg_order_charged_tax()): 1.01 at 20% and 1.27 at 10% less 1.00.
function test_legal_documents_cart_order_discount_taxes_per_rate()
{
	$lines = array(
		array('line_total' => 101, 'tax' => 20, 'rate' => '20.000'),
		array('line_total' => 127, 'tax' => 13, 'rate' => '10.000'),
	);

	$order = pg_legal_document_cart_order(array(), $lines, array(), array('order_discount' => 100));

	pg_assert_same(18, $order['tax'], '0.11 on 0.57 at 20% and 0.07 on 0.71 at 10%');
	pg_assert_same(228 - 100 + 18, $order['total'], 'the checkout total');
}

// Where the shipping holds tax on the order's invoice the documents show it
// as the checkout charges it: 9.99 that would bring 39.95 with 7.99 of tax
// to 57.93, which no amount at 20% reaches, is 9.98.
function test_legal_documents_cart_order_settles_the_shipping()
{
	$lines = array(array('line_total' => 3995, 'tax' => 799, 'rate' => '20.000'));
	$ship_tos = array(array('cost' => 999));

	$order = pg_legal_document_cart_order(array(), $lines, $ship_tos, array('settle_rates' => true));
	pg_assert_same(998, $order['shipping']);
	pg_assert_same(5792, $order['total']);

	$order = pg_legal_document_cart_order(array(), $lines, $ship_tos, array('total' => 5792, 'settle_rates' => true));
	pg_assert_same(998, $order['shipping'], 'the total the checkout showed');
	pg_assert_same(0, $order['discount'], 'and no discount made up for it');

	$order = pg_legal_document_cart_order(array(), $lines, $ship_tos, array());
	pg_assert_same(999, $order['shipping'], 'without the rule');
}

// A recurring line that starts later is charged on its own once it starts:
// the checkout's subtotal and tax leave it out, so the documents count it in
// no total and make up no discount for it.
function test_legal_documents_cart_order_leaves_out_lines_charged_later()
{
	$lines = array(
		array('line_total' => 10000, 'tax' => 2000, 'rate' => '20.000'),
		array('line_total' => 5000, 'tax' => 1000, 'rate' => '20.000', 'charged' => false),
	);

	$order = pg_legal_document_cart_order(array(), $lines, array(), array('total' => 12000));

	pg_assert_same(10000, $order['subtotal'], 'the line charged today');
	pg_assert_same(2000, $order['tax'], 'its tax');
	pg_assert_same(0, $order['discount'], 'no discount made up for the later line');
	pg_assert_same(12000, $order['total'], 'checkout total');

	$discounted = pg_legal_document_cart_order(array(), $lines, array(), array('order_discount' => 1000));

	pg_assert_same(1800, $discounted['tax'], 'the discount retaxes the line charged today only');
	pg_assert_same(10800, $discounted['total'], 'as the checkout totals it');
}

// The documents read the lines the checkout reads: a line saved for later is
// no part of the order, and each line says whether the order charges it.
function test_legal_documents_for_order_reads_the_checkout_lines()
{
	$section = legal_documents_test_function_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legal_documents.php'), 'pg_legal_documents_for_order');

	pg_assert_contains('pg_order_item_active_sql()', $section, 'saved-for-later lines are left out');
	pg_assert_contains('pg_order_line_charged_today(', $section, 'lines charged later are marked');
}

// Typed billing values replace the row's; empty ones and the country code do not.
function test_legal_documents_cart_order_billing()
{
	$order = pg_legal_document_cart_order(
		array('billing_first_name' => 'Old', 'billing_city' => 'Ankara', 'billing_country' => 'Türkiye', 'payment_method' => ''),
		array(),
		array(),
		array('billing' => array('billing_first_name' => 'Ayşe', 'billing_city' => '  ', 'billing_country' => 'DE', 'notes' => 'x'), 'payment_method' => 'Offline Payment')
	);

	pg_assert_same('Ayşe', $order['billing_first_name'], 'typed name');
	pg_assert_same('Ankara', $order['billing_city'], 'empty value keeps the row');
	pg_assert_same('Türkiye', $order['billing_country'], 'country is named by the caller');
	pg_assert_false(isset($order['notes']), 'only billing fields');
	pg_assert_same('Offline Payment', $order['payment_method'], 'payment method');
}

// The snapshot reads the hidden total, billing inputs, the selected country
// and the ticked payment method, whatever the attribute order.
function test_legal_documents_form_snapshot()
{
	$html = '<form><input type="hidden" name="total" value="1,281.00">'
		. '<input value="Ay&amp;şe" name="billing_first_name" class="form-control">'
		. '<input type="text" name="billing_last_name" value="">'
		. '<input type="hidden" name="billing_city" value="hidden city">'
		. "<input type='email' name='billing_email_address' value='a@example.test'>"
		. '<select name="billing_country" class="form-select"><option value="">-</option><option value="TR" selected>Türkiye</option></select>'
		. '<input type="radio" name="payment_method" value="Credit/Debit Card">'
		. '<input type="radio" name="payment_method" value="Offline Payment" checked>'
		. '<input type="hidden" name="total" value="5.00">'
		. '</form>';

	$snapshot = pg_legal_document_form_snapshot($html);

	pg_assert_same(128100, $snapshot['total'], 'first total, commas dropped');
	pg_assert_same(array('billing_first_name' => 'Ay&şe', 'billing_email_address' => 'a@example.test', 'billing_country' => 'TR'), $snapshot['billing'], 'billing values');
	pg_assert_same('Offline Payment', $snapshot['payment_method'], 'ticked payment method');

	pg_assert_same(array('total' => null, 'billing' => array(), 'payment_method' => ''), pg_legal_document_form_snapshot('<p>no form</p>'), 'nothing to read');
	pg_assert_same(null, pg_legal_document_form_snapshot('<input type="hidden" name="total" value="abc">')['total'], 'not a number');
}

// Two required checkboxes, each linking to its document; a ticked answer stays ticked.
function test_legal_documents_checkboxes_html()
{
	$html = pg_legal_documents_checkboxes_html(array('legal_contract' => '1', 'legal_preinfo' => '0'), '/pinegrap/legal_document.php');

	pg_assert_contains('name="legal_preinfo" id="legal_preinfo" value="1" required>', $html, 'preinfo unticked');
	pg_assert_contains('name="legal_contract" id="legal_contract" value="1" required checked>', $html, 'contract ticked');
	pg_assert_contains('href="/pinegrap/legal_document.php?type=preinfo" target="_blank" rel="noopener"', $html, 'preinfo link');
	pg_assert_contains('href="/pinegrap/legal_document.php?type=contract"', $html, 'contract link');
	pg_assert_contains('<label class="form-check-label" for="legal_contract">', $html, 'label');

	pg_assert_contains('legal_document.php?x=1&amp;type=preinfo', pg_legal_documents_checkboxes_html(array(), 'legal_document.php?x=1'), 'query kept, escaped');
}

// The block goes in front of the purchase button's tag; no button, no change.
function test_legal_documents_insert_before_purchase()
{
	$html = '<form><input name="a"><div><button type="submit" name="submit_purchase_now" value="1">Buy</button></div></form>';

	pg_assert_same(
		'<form><input name="a"><div>[X]<button type="submit" name="submit_purchase_now" value="1">Buy</button></div></form>',
		pg_legal_documents_insert_before_purchase($html, '[X]')
	);
	pg_assert_same('<form><input name="a"></form>', pg_legal_documents_insert_before_purchase('<form><input name="a"></form>', '[X]'), 'no purchase button');
}

// Without the setting the checkout output passes through untouched.
function test_legal_documents_inject_off_without_setting()
{
	$html = '<button name="submit_purchase_now">Buy</button>';

	pg_assert_same($html, pg_legal_documents_inject($html, array()));
}

// Attachment names are ASCII, lower case, with the order number.
function test_legal_documents_file_name()
{
	pg_assert_same('on-bilgilendirme-formu-1172.html', pg_legal_document_file_name('Ön Bilgilendirme Formu', '1172'));
	pg_assert_same('mesafeli-satis-sozlesmesi-1172.html', pg_legal_document_file_name('Mesafeli Satış Sözleşmesi', '1172'));
	pg_assert_same('distance-sales-contract.html', pg_legal_document_file_name('Distance Sales Contract', ''));
	pg_assert_same('document-12.html', pg_legal_document_file_name('???', '1/2'));
}

// The standalone page escapes its title and note and keeps the document's markup.
function test_legal_documents_page_html()
{
	$page = pg_legal_document_page_html('A <b>', '<h2>Body</h2>', 'tr', 'Accepted & kept');

	pg_assert_contains('<html lang="tr">', $page, 'language');
	pg_assert_contains('<title>A &lt;b&gt;</title>', $page, 'title escaped');
	pg_assert_contains('<main><h2>Body</h2><p class="pg-legal-note">Accepted &amp; kept</p></main>', $page, 'body and note');
	pg_assert_contains('noindex', $page, 'not indexed');
	pg_assert_false(strpos(pg_legal_document_page_html('T', 'B', 'en'), 'pg-legal-note">') !== false, 'no note');
}

// Without a signed-in user or staff rights (constants undefined here) only
// the guest who has just placed the order may read its copies.
function test_legal_documents_may_view_guest_receipt()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('ecommerce' => array('completed_order_id' => '42'));

	pg_assert_true(pg_legal_document_may_view(array('id' => 42, 'user_id' => 0)), 'the order just placed');
	pg_assert_false(pg_legal_document_may_view(array('id' => 43, 'user_id' => 0)), 'another order');

	$_SESSION = array();
	pg_assert_false(pg_legal_document_may_view(array('id' => 42, 'user_id' => 0)), 'no receipt in the session');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// A line goes by what the shop calls the product, as the cart and the order
// receipt show it: its short description. order_items.product_name is the
// Product ID / SKU, printed only when the product has no short description
// or no longer exists.
function test_legal_documents_item_name()
{
	pg_assert_same('Deri Kalem Kılıfı', pg_legal_document_item_name('Deri Kalem Kılıfı', '543'), 'the short description');
	pg_assert_same('Deri Kalem Kılıfı', pg_legal_document_item_name("  Deri Kalem Kılıfı\n", '543'), 'trimmed');
	pg_assert_same('543', pg_legal_document_item_name('', '543'), 'no short description');
	pg_assert_same('543', pg_legal_document_item_name('   ', '543'), 'only spaces');
	pg_assert_same('543', pg_legal_document_item_name(null, '543'), 'the product was deleted');
	pg_assert_same('', pg_legal_document_item_name(null, null), 'nothing at all');
}

// The source of one function, up to the next top-level function.
function legal_documents_test_function_source($source, $name)
{
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$next = preg_match('/\nfunction [a-z_]+\(/', $source, $match, PREG_OFFSET_CAPTURE, $from + 10);

	return $next ? substr($source, $from, $match[0][1] - $from) : substr($source, $from);
}

// The documents' lines read the product's short description and name each
// line with pg_legal_document_item_name().
function test_legal_documents_for_order_names_lines_by_short_description()
{
	$section = legal_documents_test_function_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legal_documents.php'), 'pg_legal_documents_for_order');

	pg_assert_contains('products.short_description', $section, 'the short description is read');
	pg_assert_contains('LEFT JOIN products ON products.id = order_items.product_id', $section, 'a deleted product keeps the line');
	pg_assert_contains("pg_legal_document_item_name(\$item['short_description'], \$item['product_name'])", $section, 'each line is named by it');
}

// The address kept with an acceptance is the visitor's as the firewall
// resolves it: behind a CDN or a trusted proxy REMOTE_ADDR is the proxy.
function test_legal_documents_store_keeps_the_visitor_address()
{
	$section = legal_documents_test_function_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legal_documents.php'), 'pg_legal_documents_store');
	$asked = strpos($section, 'waf_client_ip()');
	$stored = strpos($section, 'INSERT INTO order_documents');

	pg_assert_true(($asked !== false) && ($stored !== false) && ($asked < $stored), 'waf_client_ip() is asked before the row is written');
}

// Acceptance times are printed on the site's clock: 24 hours unless Site
// Settings ask for 12 (the runner has no settings, so 24 here). The legacy
// form formatter printed AM/PM whatever the setting.
function test_legal_documents_time_text()
{
	$accepted = mktime(13, 21, 0, 10, 19, 2026);

	pg_assert_same('19/10/2026 13:21', pg_legal_document_time_text($accepted), 'day first, 24 hours');
	pg_assert_same('5/1/2026 09:05', pg_legal_document_time_text(mktime(9, 5, 0, 1, 5, 2026)), 'no leading zeros in the date, two digits for the hour');
	pg_assert_contains('19/10/2026 13:21', pg_legal_document_acceptance_note(array('accepted_at' => $accepted)), 'the note on the kept copy');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legal_documents.php');

	pg_assert_false(strpos($source, "'date and time'"), 'no time is printed with the legacy formatter');
	pg_assert_contains('pg_legal_document_time_text($accepted)', legal_documents_test_function_source($source, 'pg_legal_documents_panel_card'), 'the panel card prints it the same way');
}

// What the checkout link posts lies over the page's snapshot: billing fields
// as one bounded line each (a cleared one too), a known payment method only,
// and never the total.
function test_legal_documents_typed_snapshot()
{
	$seen = array(
		'order_id' => 7,
		'total' => 28100,
		'billing' => array('billing_first_name' => 'Drawn', 'billing_company' => 'Old Ltd', 'billing_country' => 'TR'),
		'payment_method' => 'Credit/Debit Card',
	);

	$typed = pg_legal_document_typed_snapshot($seen, array(
		'token'                 => 'abc',
		'billing_first_name'    => "  Ay\x00şe \r\nNur ",
		'billing_company'       => '',
		'billing_city'          => str_repeat('ğ', 300),
		'billing_country'       => ' de ',
		'billing_address_1'     => array('not', 'a', 'value'),
		'billing_email_address' => "\xC3\x28",
		'payment_method'        => 'Offline Payment',
		'total'                 => '1.00',
		'notes'                 => 'not a billing field',
	));

	pg_assert_same(7, $typed['order_id'], 'the snapshot keeps its cart');
	pg_assert_same(28100, $typed['total'], 'the total stays the page\'s');
	pg_assert_true($typed['typed'], 'marked as typed');
	pg_assert_same('Ay şe  Nur', $typed['billing']['billing_first_name'], 'control characters become spaces, the ends are trimmed');
	pg_assert_same('', $typed['billing']['billing_company'], 'a cleared field is kept as cleared');
	pg_assert_same(255, mb_strlen($typed['billing']['billing_city'], 'UTF-8'), 'cut at 255 characters, not bytes');
	pg_assert_same('de', $typed['billing']['billing_country'], 'country code trimmed');
	pg_assert_false(isset($typed['billing']['billing_address_1']), 'a list is no value');
	pg_assert_false(isset($typed['billing']['billing_email_address']), 'a value that is not UTF-8 is left out');
	pg_assert_false(isset($typed['billing']['notes']) || isset($typed['notes']), 'only billing fields are taken');
	pg_assert_same('Offline Payment', $typed['payment_method'], 'a known payment method');

	pg_assert_same('Credit/Debit Card', pg_legal_document_typed_snapshot($seen, array('payment_method' => '<b>Free</b>'))['payment_method'], 'an unknown method is ignored');
	pg_assert_same('XYZ1234567', pg_legal_document_typed_snapshot(array(), array('billing_country' => 'XYZ12345678901'))['billing']['billing_country'], 'a country code is short');

	$untouched = pg_legal_document_typed_snapshot($seen, array('token' => 'abc', 'payment_method' => 'Offline Payment'));

	pg_assert_false(isset($untouched['typed']), 'no billing field posted: not typed');
	pg_assert_same($seen['billing'], $untouched['billing'], 'the drawn billing values stay');

	$empty = pg_legal_document_typed_snapshot(array(), array());

	pg_assert_same(array('total' => null, 'billing' => array(), 'payment_method' => ''), $empty, 'nothing seen, nothing posted');
}

// A typed snapshot clears the fields the visitor emptied; one drawn by the
// page never does, and fields nobody posted keep the row's value.
function test_legal_documents_cart_order_typed_billing()
{
	$row = array('billing_first_name' => 'Old', 'billing_company' => 'Old Ltd', 'billing_city' => 'Ankara', 'billing_country' => 'Türkiye');

	$typed = pg_legal_document_cart_order($row, array(), array(), array(
		'billing' => array('billing_first_name' => 'Ayşe', 'billing_company' => '', 'billing_country' => ''),
		'typed' => true,
	));

	pg_assert_same('Ayşe', $typed['billing_first_name'], 'typed name');
	pg_assert_same('', $typed['billing_company'], 'a cleared company is cleared');
	pg_assert_same('Ankara', $typed['billing_city'], 'a field nobody posted keeps the row');
	pg_assert_same('Türkiye', $typed['billing_country'], 'the country is still named by the caller');

	$drawn = pg_legal_document_cart_order($row, array(), array(), array('billing' => array('billing_company' => '')));

	pg_assert_same('Old Ltd', $drawn['billing_company'], 'an empty field the page drew keeps the row');
}

// The token check answers rather than stops the page, and only for the
// session's own non-empty token.
function test_legal_documents_token_matches()
{
	pg_assert_true(pg_legal_document_token_matches('abc123', 'abc123'), 'the session token');
	pg_assert_false(pg_legal_document_token_matches('abc123', 'abc124'), 'another token');
	pg_assert_false(pg_legal_document_token_matches('', ''), 'no session token');
	pg_assert_false(pg_legal_document_token_matches('abc123', array('abc123')), 'a list');
	pg_assert_false(pg_legal_document_token_matches(null, 'abc123'), 'nothing in the session');
}

// The payment methods a document names, and the label of each.
function test_legal_documents_payment_methods()
{
	pg_assert_same(array('Credit/Debit Card', 'PayPal Express Checkout', 'Offline Payment', 'Pay With Iyzico', 'PayPal Checkout', 'PayTR'), array_keys(pg_legal_document_payment_methods()), 'the built-in values, then the shipped redirect drivers\'');
	pg_assert_same('Pay With Iyzico', pg_legal_document_payment_label('Pay With Iyzico'), 'a known method by its label');
	pg_assert_same('Something else', pg_legal_document_payment_label('Something else'), 'an unknown one as it is');
}

// Every checkout that shows the documents loads the script that posts the
// form, whether the boxes were added here or a designer tree binds them.
function test_legal_documents_script()
{
	$tag = pg_legal_documents_script('/pinegrap');

	pg_assert_same(1, preg_match('#^<script src="/pinegrap/assets/js/pg_legal_documents\.js\?v=\d+" defer></script>$#', $tag), 'versioned and deferred: ' . $tag);
	pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/assets/js/pg_legal_documents.js'), 'the script ships');

	$section = legal_documents_test_function_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legal_documents.php'), 'pg_legal_documents_inject');

	// Past the switch there is one way out, with or without boxes added.
	pg_assert_same(2, preg_match_all('/^\s+return /m', $section), 'the off return and one more');
	pg_assert_contains('$html) . pg_legal_documents_script();', $section, 'the way out with the documents carries it');
}

// The script posts exactly the billing fields the documents read.
function test_legal_documents_script_posts_the_billing_fields()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_legal_documents.js');

	pg_assert_same(1, preg_match('/var BILLING = \[(.*?)\];/s', $script, $match), 'the list is there');
	preg_match_all("/'([a-z0-9_]+)'/", $match[1] ?? '', $names);
	pg_assert_same(pg_legal_document_billing_fields(), $names[1], 'the same fields in the same order');
	pg_assert_contains("'payment_method'", $script, 'and the payment method');
	pg_assert_contains("url.searchParams.has('order_id')", $script, 'a kept copy is left to the plain link');
	pg_assert_contains('url.origin !== window.location.origin', $script, 'the token never leaves the site');
}

// With "my billing address is the same as my shipping address" ticked the
// billing block is hidden and empty; the script posts the one recipient's
// fields as the billing ones, the same fields express_order.php copies on
// submit, and leaves out fields a hidden block holds.
function test_legal_documents_script_posts_the_recipient_when_billing_is_the_same()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_legal_documents.js');
	$submit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/express_order.php');

	pg_assert_same(1, preg_match('/var SAME_AS_SHIPPING = \[(.*?)\];/s', $script, $match), 'the list is there');
	preg_match_all("/'([a-z0-9_]+)'/", $match[1] ?? '', $names);
	pg_assert_same(1, preg_match('/foreach \(array\(([^)]*)\) as \$pg_same_field\)/', $submit, $copied), 'the submit copies a list');
	preg_match_all("/'([a-z0-9_]+)'/", $copied[1] ?? '', $copied_names);
	pg_assert_same($copied_names[1], $names[1], 'the same fields as the submit copies');
	pg_assert_contains("'billing_same_as_shipping'", $script, 'it reads the box');
	pg_assert_contains("closest('[hidden]')", $script, 'a hidden block is not posted');
}

// The document page takes posted values only with the session's token and
// hands them to the cart's documents; a kept copy never reads them.
function test_legal_documents_page_reads_posted_values_with_the_token()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/legal_document.php');
	$checked = strpos($page, "pg_legal_document_token_matches(\$_SESSION['software']['token'] ?? '', \$_POST['token'] ?? '')");
	$used = strpos($page, 'pg_legal_documents_for_cart($posted)');
	$kept = strpos($page, 'pg_legal_documents_of_order($order_id)');

	pg_assert_true(($checked !== false) && ($used !== false) && ($checked < $used), 'the token is checked before the values are used');
	pg_assert_contains('pg_post_body_discarded()', $page, 'a dropped body is no values');
	pg_assert_true(($kept !== false) && ($kept < $checked), 'the kept copy is read in the branch before');
	pg_assert_false(strpos($page, 'validate_token_field()'), 'a wrong token falls back instead of failing');
}

// The cart's document is read in the language the checkout is served in:
// its address carries pg_lang (the document page is a script of its own,
// not a routed page), as a form posted from that page does.
function test_legal_documents_cart_url()
{
	pg_assert_same('/p/legal_document.php', pg_legal_document_cart_url('', '', '/p/legal_document.php'), 'source language, no type');
	pg_assert_same('/p/legal_document.php?pg_lang=en', pg_legal_document_cart_url('', 'en', '/p/legal_document.php'), 'a target language');
	pg_assert_same('/p/legal_document.php?type=preinfo', pg_legal_document_cart_url('preinfo', '', '/p/legal_document.php'), 'a type');
	pg_assert_same('/p/legal_document.php?type=contract&pg_lang=pt-br', pg_legal_document_cart_url('contract', 'pt-br', '/p/legal_document.php'), 'both');
	pg_assert_same('/p/legal_document.php?type=a%26b&pg_lang=e%26n', pg_legal_document_cart_url('a&b', 'e&n', '/p/legal_document.php'), 'encoded');
	pg_assert_contains('legal_document.php?pg_lang=en&amp;type=preinfo', pg_legal_documents_checkboxes_html(array(), pg_legal_document_cart_url('', 'en', 'legal_document.php')), 'the injected boxes keep the language');

	$injector = legal_documents_test_function_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legal_documents.php'), 'pg_legal_documents_inject');
	$widget = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_express_order.php');

	pg_assert_contains('pg_legal_documents_checkboxes_html($values, pg_legal_document_cart_url())', $injector, 'the injected boxes link in the page language');
	pg_assert_contains("h(pg_legal_document_cart_url('preinfo'))", $widget, 'the designer token of the pre-information form');
	pg_assert_contains("h(pg_legal_document_cart_url('contract'))", $widget, 'the designer token of the contract');
}

// A kept copy's address is the document page with the order's id and the type.
function test_legal_documents_copy_url()
{
	pg_assert_same('/pinegrap/legal_document.php?order_id=42&type=preinfo', pg_legal_document_copy_url('42', 'preinfo', '/pinegrap/legal_document.php'));
	pg_assert_same('/p/legal_document.php?order_id=0&type=a%26b', pg_legal_document_copy_url('x', 'a&b', '/p/legal_document.php'), 'id cast, type encoded');
}

// The order view gets an address for each kept type the order has a copy
// of (the KVKK copy too), '' for the others, in the labels' order; a stored
// type nothing names gets none.
function test_legal_documents_copy_urls()
{
	$kept = array(
		'kvkk'     => array('type' => 'kvkk', 'html' => '<p>K</p>'),
		'contract' => array('type' => 'contract', 'html' => '<p>C</p>'),
		'other'    => array('type' => 'other', 'html' => '<p>O</p>'),
	);

	pg_assert_same(
		array('preinfo' => '', 'contract' => '/p/legal_document.php?order_id=7&type=contract', 'kvkk' => '/p/legal_document.php?order_id=7&type=kvkk'),
		pg_legal_document_copy_urls($kept, 7, '/p/legal_document.php')
	);
	pg_assert_same(array('preinfo' => '', 'contract' => '', 'kvkk' => ''), pg_legal_document_copy_urls(array(), 7, '/p/legal_document.php'), 'nothing kept');
}

// The order view links the kept copies, never in the receipt e-mail (it
// attaches them); a layout that binds no link is given the legal documents
// card (widget_default_parts_test.php).
function test_legal_documents_order_view_links()
{
	$section = legal_documents_test_function_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php'), '_render_system_widget_order_view');

	pg_assert_contains("empty(\$sw_ctx['email']) ? pg_legal_documents_of_order((int)\$order['id']) : array()", $section, 'no copies in the e-mail');
	pg_assert_contains("'__legal_preinfo_url'       => h(\$legal_urls['preinfo'])", $section, 'the pre-information form link');
	pg_assert_contains("'__legal_contract_url'      => h(\$legal_urls['contract'])", $section, 'the contract link');
	pg_assert_contains("'__legal_kvkk_url'          => h(\$legal_urls['kvkk'])", $section, 'the KVKK copy link');
	pg_assert_contains("'has_legal_kvkk'           => \$legal_urls['kvkk'] !== ''", $section, 'shown while the KVKK copy is kept');
	pg_assert_contains("array(array('__legal_preinfo_url', '__legal_contract_url', '__legal_kvkk_url'), 'pg_sw_part_order_legal_documents')", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_parts.php'), 'a bound link keeps the card away');
}

// Deleting an order deletes the copies kept with it: they hold the buyer's
// name, address, phone, e-mail, IP and browser, and a reused order id would
// otherwise find "already stored" copies of another customer.
function test_legal_documents_deleted_with_the_order()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/delete_order.php');
	$orders = strpos($source, 'db("DELETE FROM orders WHERE');
	$documents = strpos($source, 'db("DELETE FROM order_documents WHERE order_id = \'" . e($order[\'id\']) . "\'")');

	pg_assert_true(($orders !== false) && ($documents !== false) && ($orders < $documents), 'the copies go with the order');
	pg_assert_contains("pg_schema_has('order_documents')", $source, 'only where the table is there');
}

// Each stored and accepted document becomes one granted contract consent,
// tied to the order's buyer and stamped with the document's own ip, user
// agent and acceptance time. An unaccepted or unstored type gives none.
function test_legal_documents_consent_events()
{
	$order = array('billing_email_address' => ' Buyer@Example.com ', 'user_id' => '12', 'contact_id' => '34');
	$events = pg_legal_documents_consent_events($order, array('preinfo', 'contract'), array('contract', 'kvkk'), '203.0.113.9', 'Agent/1.0', 1700000000);

	pg_assert_same(array(array(
		'channel'    => 'contract',
		'purpose'    => 'contract',
		'status'     => 'granted',
		'source'     => 'checkout',
		'email'      => ' Buyer@Example.com ',
		'user_id'    => 12,
		'contact_id' => 34,
		'ip'         => '203.0.113.9',
		'user_agent' => 'Agent/1.0',
		'created_at' => 1700000000,
	)), $events, 'only the stored and accepted type; kvkk was not stored');

	pg_assert_same(array(), pg_legal_documents_consent_events($order, array('preinfo', 'contract'), array(), '', '', 1), 'nothing accepted');

	$both = pg_legal_documents_consent_events(array(), array('preinfo', 'contract'), array('preinfo', 'contract'), '', '', 1);
	pg_assert_same(array('preinfo', 'contract'), array_column($both, 'purpose'), 'in the stored order');
	pg_assert_same(array(0, 0, ''), array($both[0]['user_id'], $both[0]['contact_id'], $both[0]['email']), 'a missing order field is empty');

	// The rows pass the consent log's own checks.
	$event = $events[0];
	$row = pg_consent_log_row($event, 1);
	pg_assert_true(is_array($row), 'a valid consent row');
	pg_assert_same(pg_privacy_person_token('buyer@example.com', null), pg_privacy_person_token($event['email'], null), 'the token is made from the normalised address');
}

// The store writes the consent rows after its documents, only for what it
// wrote: the repeat call returns before them.
function test_legal_documents_store_records_contract_consents()
{
	$section = legal_documents_test_function_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legal_documents.php'), 'pg_legal_documents_store');
	$repeat = strpos($section, 'return 0;', strpos($section, 'SELECT COUNT(*) FROM order_documents'));
	$stored = strpos($section, 'INSERT INTO order_documents');
	$consent = strpos($section, 'pg_consent_log_record(');

	pg_assert_true(($repeat !== false) && ($stored !== false) && ($consent !== false) && ($repeat < $stored) && ($stored < $consent), 'consents after the documents, never on the repeat call');
	pg_assert_contains('pg_legal_documents_consent_events(', $section, 'rows built by the tested helper');
}

// The KVKK notice is kept with the order and named, but never required: the
// checkout's required boxes stay the two documents.
function test_legal_documents_kvkk_label()
{
	pg_assert_same(array('preinfo', 'contract'), array_keys(pg_legal_document_types()), 'required documents');
	pg_assert_same(array('preinfo', 'contract', 'kvkk'), array_keys(pg_legal_document_labels()), 'kept types');
}

// The notice sentence links the page; the consent box is never required and
// keeps its ticked state; the sentence is escaped; neither gives nothing.
function test_legal_documents_kvkk_html()
{
	pg_assert_same('', pg_legal_kvkk_html(array(), '', '  '), 'nothing set');

	$notice = pg_legal_kvkk_html(array(), '/kvkk?a=1&b=2', '');
	pg_assert_contains('href="/kvkk?a=1&amp;b=2"', $notice, 'link escaped');
	pg_assert_true(strpos($notice, 'legal_kvkk') === false, 'no box without a sentence');

	$box = pg_legal_kvkk_html(array('legal_kvkk' => '1'), '', "I agree <b>\nfully</b>");
	pg_assert_contains('name="legal_kvkk" id="legal_kvkk" value="1" checked>', $box, 'ticked, not required');
	pg_assert_true(strpos($box, 'required') === false, 'never required');
	pg_assert_contains('I agree &lt;b&gt;<br>' . "\n" . 'fully&lt;/b&gt;', $box, 'sentence escaped');
	pg_assert_contains('value="1">', pg_legal_kvkk_html(array('legal_kvkk' => '0'), '', 'Yes'), 'unticked');
	pg_assert_contains('class="pg-legal-kvkk', $box, 'the marker inject looks for');
}

// The kept copy names the linked page and the consent sentence.
function test_legal_documents_kvkk_copy_html()
{
	pg_assert_same('', pg_legal_kvkk_copy_html('', ''), 'nothing shown, nothing kept');

	$copy = pg_legal_kvkk_copy_html('https://example.com/kvkk', 'I consent & agree');
	pg_assert_contains('<a href="https://example.com/kvkk">https://example.com/kvkk</a>', $copy, 'page address');
	pg_assert_contains('<p>I consent &amp; agree</p>', $copy, 'consent sentence');
	pg_assert_true(strpos(pg_legal_kvkk_copy_html('https://example.com/kvkk', ''), '<h3>') === false, 'no consent section without a sentence');
}

// A KVKK copy's note speaks of consent, not acceptance.
function test_legal_documents_kvkk_acceptance_note()
{
	pg_assert_same(lang('The customer gave no explicit consent.'), pg_legal_document_acceptance_note(array('type' => 'kvkk', 'accepted_at' => null)));
	pg_assert_same(
		lang(array('string' => 'Explicit consent given by the customer on {var:1}.', 'vars' => array(pg_legal_document_time_text(1700000000)))),
		pg_legal_document_acceptance_note(array('type' => 'kvkk', 'accepted_at' => '1700000000'))
	);
	pg_assert_same(lang('This copy was kept with the order without being accepted.'), pg_legal_document_acceptance_note(array('type' => 'contract', 'accepted_at' => null)), 'documents unchanged');
}

// The checkout posts the box, the submit passes it to the store, and the
// store counts it as consent only when the checkout asked for it.
function test_legal_documents_kvkk_reaches_the_store()
{
	$submit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');
	$store = legal_documents_test_function_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legal_documents.php'), 'pg_legal_documents_store');
	$inject = legal_documents_test_function_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legal_documents.php'), 'pg_legal_documents_inject');

	pg_assert_contains("'kvkk'     => (\$liveform->get_field_value('legal_kvkk') == 1)", $submit, 'submit passes the answer');
	pg_assert_contains("'legal_contract', 'legal_kvkk'", $submit, 'a single value');
	pg_assert_contains("'legal_contract', 'legal_kvkk'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/express_order.php'), 'a single value on the express order');
	pg_assert_contains("array_diff(\$accepted_types, array('kvkk'))", $store, 'no consent without the box');
	pg_assert_contains("pg_legal_kvkk_copy_html(\$kvkk['full_url'], \$kvkk['consent'])", $store, 'the copy kept');
	pg_assert_contains("strpos(\$html, 'pg-legal-kvkk') === false", $inject, 'one set of KVKK lines');
}

// A sign-up page gets the KVKK lines in front of its register button: the
// legacy screen's submit_register (its login form's button comes first),
// else a widget's first submit control; once only, never without settings.
function test_legal_documents_kvkk_signup_html()
{
	$legacy = '<form><button type="submit" name="submit_login">Log in</button></form><form><input type="submit" name="submit_register" value="Register"></form>';
	$out = pg_legal_kvkk_signup_html($legacy, array(), '/kvkk', 'I consent');
	pg_assert_true(strpos($out, 'pg-legal-kvkk') > strpos($out, 'submit_login'), 'not on the login form');
	pg_assert_true(strpos($out, 'pg-legal-kvkk') < strpos($out, 'submit_register'), 'in front of the register button');

	$widget = '<form><input name="email"><button class="btn" type="submit">Sign up</button></form>';
	$out = pg_legal_kvkk_signup_html($widget, array('legal_kvkk' => '1'), '', 'I consent');
	pg_assert_contains('</div><button class="btn" type="submit">', $out, 'a widget: before its submit control');
	pg_assert_contains('value="1" checked>', $out, 'ticked state kept');

	pg_assert_same($out, pg_legal_kvkk_signup_html($out, array(), '/kvkk', 'I consent'), 'once only');
	pg_assert_same($widget, pg_legal_kvkk_signup_html($widget, array(), '', ''), 'nothing set');
	pg_assert_same('<p>Signed in</p>', pg_legal_kvkk_signup_html('<p>Signed in</p>', array(), '/kvkk', ''), 'no form');
}

// Consent counts only when the store asks for it and the box was ticked.
function test_legal_documents_kvkk_consent_given()
{
	pg_assert_true(pg_legal_kvkk_consent_given('1', 'I consent'));
	pg_assert_false(pg_legal_kvkk_consent_given('1', '  '), 'no sentence, no consent');
	pg_assert_false(pg_legal_kvkk_consent_given('0', 'I consent'), 'unticked');
	pg_assert_false(pg_legal_kvkk_consent_given(null, 'I consent'), 'not posted');
	pg_assert_false(pg_legal_kvkk_consent_given(array('1'), 'I consent'), 'not a value');
}

// Every sign-up screen draws the lines and both entrance scripts pass the
// answer on; the account writers record it as a granted contract:kvkk row.
function test_legal_documents_kvkk_signup_wiring()
{
	$read = function ($file) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	};

	pg_assert_same(2, substr_count($read('get_page_content.php'), 'pg_legal_kvkk_signup_inject(get_'), 'both legacy screens in a page');
	pg_assert_same(2, substr_count($read('includes/fn/forms.php'), 'pg_legal_kvkk_signup_inject(get_'), 'both fallback screens');
	pg_assert_same(2, substr_count($read('includes/fn/designer.php'), 'pg_legal_kvkk_signup_inject(_render_system_widget_'), 'both widgets');

	foreach (array('registration_entrance.php' => 'register_opts', 'membership_entrance.php' => 'activate_opts') as $file => $opts) {
		pg_assert_contains("\$" . $opts . "['kvkk_consent'] = pg_legal_kvkk_consent_given(\$_POST['legal_kvkk'] ?? null, \$kvkk['consent']);", $read($file), $file);
	}

	pg_assert_same(2, substr_count($read('includes/fn/auth.php'), "array('contract:kvkk' => true),"), 'register and activate record it');
	pg_assert_true(pg_consent_log_purpose_valid('contract', 'kvkk'), 'a purpose the log takes');

	$changes = pg_consent_log_changes(array(), array('contract:kvkk' => true));
	pg_assert_same(1, count($changes), 'a first consent is a change');
	pg_assert_true(is_array(pg_consent_log_row($changes[0] + array('source' => 'signup', 'user_id' => 5), 1)), 'a valid sign-up row');
	pg_assert_same(array(), pg_consent_log_changes(array('contract:kvkk' => array('status' => 'granted')), array('contract:kvkk' => true)), 'given before: nothing new');
}
