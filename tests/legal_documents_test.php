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
