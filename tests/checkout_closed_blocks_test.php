<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order preview's closed blocks: the applied offers, applied
 * gift cards and recurring payments open into lists of the designer's own
 * elements (pg_op_part(), pg_op_list_rows()), drawn with the same words as
 * the sections they replace; the installment choice and table stay the
 * site's, built by pure builders the designer canvas draws twins of. The
 * twins in assets/js/style_designer.src.js are cut out and run under Node
 * (skipped on a machine without Node) for a sample order with an offer, a
 * gift card and a recurring product.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/payments/iyzico.php');

function checkout_blocks_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The body of the named function, up to the next function at the same indentation.
function checkout_blocks_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    function ", $from + 10);
	pg_assert_true($to !== false, $name . '() has an end');

	return substr($source, $from, $to - $from);
}

// Runs a JavaScript expression over the cut twins; null without Node.
function checkout_blocks_test_run($expr)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = checkout_blocks_test_source();
	$script = "function esc(s) { if (!s && s !== 0) return ''; return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n"
		. "function _sdT(s) { return s; }\n"
		. "function _sdMoney(n) { return '$' + Number(n).toFixed(2); }\n"
		. "function createNode(type, props, children) { return { type: type, props: props || {}, children: children || [] }; }\n";
	foreach (array('_sdPartEl', '_sdPartWhen', '_sdPartSentence', '_sdOrderPreviewPart', '_sdOrderPreviewRecipientsPart', '_sdOpOffersHtml', '_sdOpGiftCardsHtml', '_sdOpPeriodsHtml',
		'_sdInstallmentBoxHtml', '_sdInstallmentPlansHtml', '_sdInstallmentSteps', '_sdInstallmentTableHtml',
		'_sdOpRecipientsHtml', '_sdOpReviewFieldsHtml') as $fn) {
		$script .= checkout_blocks_test_function($source, $fn) . "\n";
	}
	$script .= 'console.log(JSON.stringify(' . $expr . '));';
	$file = tempnam(sys_get_temp_dir(), 'pgop');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A price as get_order_preview() writes it.
function checkout_blocks_test_price($text)
{
	return '<span style="white-space: nowrap">' . $text . '</span>';
}

// get_order_preview()'s data for a sample order: an offer, a gift card
// the order takes an amount from and one it does not (the total was
// already covered), a monthly recurring product, a yearly period unused.
function checkout_blocks_test_order()
{
	return array(
		'applied_offers' => array(
			array('id' => 3, 'code' => 'WELCOME10', 'description' => '10% off your first order'),
			array('id' => 4, 'code' => 'SHIP', 'description' => 'Free shipping & returns'),
		),
		'applied_gift_cards' => array(
			array('id' => 7, 'protected_code' => '************1234', 'amount_info' => checkout_blocks_test_price('&#8378;25,00'),
				'remaining_balance_info' => checkout_blocks_test_price('&#8378;50,00'),
				'remove_url' => '/software/remove_gift_card_from_order.php?applied_gift_card_id=7&send_to=%2Fcheckout&token=t'),
			array('id' => 8, 'protected_code' => '************9876'),
		),
		'payment_periods' => array(
			'Monthly' => array('name' => 'Monthly', 'exists' => true, 'subtotal_info' => checkout_blocks_test_price('&#8378;49,90'),
				'tax_info' => checkout_blocks_test_price('&#8378;9,98'), 'total_info' => checkout_blocks_test_price('&#8378;59,88')),
			'Yearly' => array('name' => 'Yearly', 'exists' => false),
		),
	);
}

// The rows carry the section's words, amounts as text, only the cards the
// order takes from and only the periods it has.
function test_checkout_blocks_list_rows()
{
	$rows = pg_op_list_rows(checkout_blocks_test_order());
	pg_assert_same(array('checkout.offers', 'checkout.gift_cards', 'checkout.periods', 'checkout.ship_tos', 'checkout.lines', 'checkout.line_gift_cards'), array_keys($rows));
	pg_assert_same(array(
		array('__offer_code' => 'WELCOME10', '__offer_text' => '10% off your first order'),
		array('__offer_code' => 'SHIP', '__offer_text' => 'Free shipping & returns'),
	), $rows['checkout.offers'][0], 'the offers, raw');
	pg_assert_same(array(array(
		'__gift_card_code' => '************1234', '__gift_card_amount' => '₺25,00', '__gift_card_balance' => '₺50,00',
		'__gift_card_remove_url' => '/software/remove_gift_card_from_order.php?applied_gift_card_id=7&send_to=%2Fcheckout&token=t',
	)), $rows['checkout.gift_cards'][0], 'the card the order takes from, amounts as text');
	pg_assert_same(array(array('__period_name' => 'Monthly', '__period_subtotal' => '₺49,90', '__period_tax' => '₺9,98', '__period_total' => '₺59,88')),
		$rows['checkout.periods'][0], 'the period the order has');

	$none = pg_op_list_rows(array('applied_offers' => array(), 'applied_gift_cards' => array(), 'payment_periods' => array()));
	pg_assert_same(array(array(), array(), array()), array($none['checkout.offers'][0], $none['checkout.gift_cards'][0], $none['checkout.periods'][0]), 'nothing applied');
	$no_tax = pg_op_list_rows(array('payment_periods' => array(array('name' => 'Weekly', 'exists' => true,
		'subtotal_info' => checkout_blocks_test_price('1,00'), 'total_info' => checkout_blocks_test_price('1,00')))));
	pg_assert_same('', $no_tax['checkout.periods'][0][0]['__period_tax'], 'tax off: an empty tax cell, as the section prints');
}

// The opened part of $section drawn as the order preview draws it: its
// lists filled from $d's rows by the finish pass.
function checkout_blocks_test_draw($section, $d)
{
	static $sid = 60;
	$tree = array('type' => 'root', 'props' => array(), 'children' => pg_op_part($section));
	$scope = pg_sw_finish_open(array('sid' => ++$sid, 'kind' => 'order_preview', 'email' => false));
	try {
		$html = trim(_render_tree_node($tree, 0, 0));
		pg_sw_list_publish('checkout', pg_op_list_rows($d));
		$html = _pg_member_apply_tokens($html, array('__ship_to_key' => '0'));
	} finally {
		pg_sw_finish_close();
	}
	return pg_sw_finish_widget_html($html, $scope);
}

// The visible words of $html on one line.
function checkout_blocks_test_text($html)
{
	// A line break or a block's edge reads as a space; inline tags join
	// their words, as a browser shows them.
	$html = preg_replace('#<(?:br|/?(?:div|li|ul|ol|p|dl|dt|dd|table|thead|tbody|tr|th|td))\b[^>]*>#i', ' ', $html);
	$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	return str_replace(array('( ', ' )'), array('(', ')'), trim(preg_replace('/\s+/u', ' ', $text)));
}

// The opened part, drawn with its lists, says what the section said: the
// same words in the same order, its classes and the Remove link kept.
function test_checkout_blocks_part_prints_the_sections_words()
{
	$d = checkout_blocks_test_order();
	$sections = array(
		'applied_offers'     => _pg_op_offers_html($d['applied_offers']),
		'applied_gift_cards' => _pg_op_gift_cards_html($d['applied_gift_cards']),
		'recurring_payments' => _pg_op_payment_periods_html($d['payment_periods']),
	);
	foreach ($sections as $section => $old) {
		$new = checkout_blocks_test_draw($section, $d);
		pg_assert_same(false, strpos($new, '^^'), $section . ': no token left');
		// Without white space: two inline boxes side by side in a flex row
		// read apart on screen whether or not a space separates them.
		pg_assert_same(preg_replace('/\s+/u', '', checkout_blocks_test_text($old)), preg_replace('/\s+/u', '', checkout_blocks_test_text($new)), $section . ': the same words');
		preg_match_all('/class="([^"]*)"/', $old, $m);
		foreach (array_unique($m[1]) as $class) {
			pg_assert_contains('class="' . $class . '"', $new, $section . ': class ' . $class);
		}
	}
	pg_assert_contains('href="/software/remove_gift_card_from_order.php?applied_gift_card_id=7&amp;send_to=%2Fcheckout&amp;token=t"',
		checkout_blocks_test_draw('applied_gift_cards', $d), 'the Remove link escaped once');

	$empty = array('applied_offers' => array(), 'applied_gift_cards' => array(), 'payment_periods' => array());
	pg_assert_same('', checkout_blocks_test_text(checkout_blocks_test_draw('applied_offers', $empty)), 'no offer: no words');
	pg_assert_same(array(), pg_op_part('billing_form'), 'the custom billing form stays its form\'s');
	pg_assert_same(array(), pg_op_part('card_installments'), 'installments stay the site\'s');
}

// The editor builds the same part as the server.
function test_checkout_blocks_part_twin()
{
	$drawn = checkout_blocks_test_run('["applied_offers", "applied_gift_cards", "recurring_payments", "order_recipients", "installment_table", "billing_form"].map(_sdOrderPreviewPart)');
	if ($drawn === null) {
		return;
	}
	$php = array_map('pg_op_part', array('applied_offers', 'applied_gift_cards', 'recurring_payments', 'order_recipients', 'installment_table', 'billing_form'));
	pg_assert_same(json_decode(json_encode($php), true), $drawn, 'the parts are the server\'s');
}

// Each canvas sample of an unopened section is its builder's output.
function test_checkout_blocks_section_twins()
{
	$d = checkout_blocks_test_order();
	$drawn = checkout_blocks_test_run('['
		. '_sdOpOffersHtml(["10% off your first order", "Free shipping & returns"]), _sdOpOffersHtml([]),'
		. '_sdOpGiftCardsHtml([{ code: "************1234", amount: ' . json_encode(checkout_blocks_test_price('&#8378;25,00')) . ', balance: ' . json_encode(checkout_blocks_test_price('&#8378;50,00')) . ', url: ' . json_encode($d['applied_gift_cards'][0]['remove_url']) . ' }]), _sdOpGiftCardsHtml([]),'
		. '_sdOpPeriodsHtml([{ name: "Monthly", subtotal: ' . json_encode(checkout_blocks_test_price('&#8378;49,90')) . ', tax: ' . json_encode(checkout_blocks_test_price('&#8378;9,98')) . ', total: ' . json_encode(checkout_blocks_test_price('&#8378;59,88')) . ' }]), _sdOpPeriodsHtml([]),'
		. '_sdInstallmentBoxHtml(6, 0), _sdInstallmentBoxHtml(9, 3),'
		. '_sdInstallmentSteps(9), _sdInstallmentSteps(1),'
		. '_sdInstallmentTableHtml(_sdInstallmentSteps(6), [{ family: "Bonus & Co", cells: { 2: ["$510.00", "$1020.00"], 3: ["$350.00", "$1050.00"], 6: ["$180.00", "$1080.00"] } }, { family: "Axess", cells: { 2: ["$505.00", "$1010.00"] } }])'
		. ']');
	if ($drawn === null) {
		return;
	}
	pg_assert_same(_pg_op_offers_html($d['applied_offers']), $drawn[0], 'offers');
	pg_assert_same('', $drawn[1], 'no offer');
	pg_assert_same(_pg_op_gift_cards_html($d['applied_gift_cards']), $drawn[2], 'gift cards');
	pg_assert_same('', $drawn[3], 'no gift card');
	pg_assert_same(_pg_op_payment_periods_html($d['payment_periods']), $drawn[4], 'payment periods');
	pg_assert_same('', $drawn[5], 'no recurring product');
	pg_assert_same(pg_eo_installment_box_html(6, 0), $drawn[6], 'the installment box');
	pg_assert_same(pg_eo_installment_box_html(9, 3), $drawn[7], 'the installment box, plan 3 kept');
	pg_assert_same(pg_payment_iyzico_installment_steps(9), $drawn[8], 'the plan counts');
	pg_assert_same(array(), $drawn[9], 'no plan under two');
	pg_assert_same(pg_payment_iyzico_installment_table_html(pg_payment_iyzico_installment_steps(6), array(
		array('family' => 'Bonus & Co', 'cells' => array(2 => array('$510.00', '$1020.00'), 3 => array('$350.00', '$1050.00'), 6 => array('$180.00', '$1080.00'))),
		array('family' => 'Axess', 'cells' => array(2 => array('$505.00', '$1010.00'))),
	)), $drawn[10], 'the installment table');
}

// The plans the canvas lists are the ones the box's script writes.
function test_checkout_blocks_installment_plans_markup()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_express_order.php');
	$plans = checkout_blocks_test_run('_sdInstallmentPlansHtml([{ n: 1, label: "Single payment", total: "$10.00", increase: "" }, { n: 3, label: "3x $3.40", total: "$10.20", increase: "$0.20" }], 1)');
	if ($plans === null) {
		return;
	}
	foreach (array('<div class="row g-2">', '<div class="col-12 col-md-6"><label class="d-flex justify-content-between align-items-center border rounded p-2 small mb-0" style="cursor:pointer">',
		'<span><input type="radio" class="form-check-input me-2 pg-eo-installment-radio" name="_pg_eo_installment_radio" value="', '<span class="text-muted">', ' <span class="text-warning">+') as $piece) {
		pg_assert_contains($piece, $plans, 'the canvas writes ' . $piece);
		pg_assert_contains(str_replace('"', '\\\\"', $piece), $script, 'the script writes ' . $piece);
	}
	pg_assert_contains('value="1" checked>Single payment', $plans, 'the chosen plan ticked');
}

// The renderer publishes the lists, fills their key and keeps the sections
// for a page not yet opened; the editor offers the button and the starter
// opens the three parts.
function test_checkout_blocks_wiring()
{
	$op = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_order_preview.php');
	$publish = strpos($op, "if (pg_sw_list_wanted('checkout')) pg_sw_list_publish('checkout', pg_op_list_rows(\$d));");
	pg_assert_true($publish !== false, 'the renderer publishes the lists');
	// The lists a tree holds are known once it is drawn: published before,
	// they would list nothing.
	pg_assert_true($publish > strpos($op, "trim(_render_tree_node(\$split['static_tree'], 0, 0))"), 'the rows are published after the tree is drawn');
	pg_assert_contains("'__ship_to_key'             => '0',", $op);
	pg_assert_contains("'applied_gift_cards' => _pg_op_gift_cards_html(\$d['applied_gift_cards']),", $op);
	pg_assert_contains("'applied_offers'     => _pg_op_offers_html(\$d['applied_offers']),", $op);
	$js = checkout_blocks_test_source();
	pg_assert_contains("if (_sdOrderPreviewOpens(kind, section)) return _sdOrderPreviewOpenRow();", $js);
	pg_assert_contains("document.querySelectorAll('#sd-properties [data-sd-op-open]')", $js);
	pg_assert_contains("var _opSample = _sdOrderPreviewSectionSample(_sec, doc);", $js);
	foreach (array("opPart(_sdT('Offers'), 'applied_offers', '', '')", "opPart(_sdT('Applied Gift Cards'), 'applied_gift_cards', 'has_gift_card_discount')",
		"opPart(_sdT('Payment Periods'), 'recurring_payments', '', '')", "opPart(_sdT('Recipients and Items'), 'order_recipients')",
		"opSection(_sdT('Installments'), 'card_installments', '', '')") as $line) {
		pg_assert_contains($line, $js, 'the starter: ' . $line);
	}
	// A list inside a table or a <ul> draws as the site does: a cell's own
	// words, a band that does not widen the table, bullets inside the frame.
	pg_assert_contains("if (tag === 'td' || tag === 'th') _sdTableCellText(doc, node, el);", $js);
	// The order preview's markup values draw as markup on the canvas, as on the site.
	pg_assert_contains("_semIsHtml = _sdIsHtmlPreviewToken(node.props._bindings.text) || !!_SD_OP_MARKUP_ROWS[node.props._bindings.text];", $js);
	foreach (array('__ship_to_cost_html', '__ship_to_form_html', '__line_title_html', '__line_price_html', '__line_amount_html', '__line_stock_html', '__line_forms_html') as $key) {
		pg_assert_contains($key . ': 1', $js, $key . ' drawn as markup');
	}
	pg_assert_contains('white-space:nowrap; overflow:hidden; width:0; min-width:100%; }', $js);
	pg_assert_contains("ol > .sd-wrap > .sd-loop-area > .sd-wrap > li, ol > .sd-wrap > .sd-loop-area > .sd-ghost > li { list-style-position:inside; }", $js);
	// The architect's note (channel 6539): the bullets clear the frame's
	// line, the words stay where the site puts them.
	pg_assert_contains("'ul > .sd-wrap > .sd-loop-area, ol > .sd-wrap > .sd-loop-area { margin-left:-.5rem; padding-left:.5rem; width:calc(100% + .5rem); }'", $js);
	$starters = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_starters.php');
	pg_assert_contains("pg_op_part('applied_gift_cards')", $starters, 'the server\'s layouts open the gift cards too');
	pg_assert_same(false, strpos($starters, "_pg_st_keep('section:applied_gift_cards')"), 'no layout looks for the old section');
	pg_assert_contains("pg_op_part('order_recipients')", $starters, 'nor for the recipients\' section');
	pg_assert_same(false, strpos($starters, "_pg_st_keep('section:order_recipients')"), 'the recipients come open');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'installments'         => (defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY === 'Iyzipay'", $screen);
}

// The value of `var NAME = {…};` in the designer's source, as JavaScript.
function checkout_blocks_test_var($source, $name)
{
	$from = strpos($source, '    var ' . $name . ' = {');
	pg_assert_true($from !== false, 'the designer has ' . $name);
	$to = strpos($source, "\n    };", $from);

	return substr($source, $from, $to + 7 - $from) . "\n";
}

// Inside a checkout list's row the row's own sample wins over the sample
// order's value of the same name; in an order view list the sample order's
// stays. The gift card rows add up to the sample order's gift card discount.
function test_checkout_blocks_row_sample_wins_in_checkout_lists()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}
	$source = checkout_blocks_test_source();
	// SW_LIST_SOURCES reads a few token lists of other widgets: empty here.
	$script = "function _sdT(s) { return s; }\nvar SW_SHIP_TO_ENTITY_TOKENS = [], SW_ORDER_LINE_ENTITY_TOKENS = [], SW_PRODUCT_ENTITY_TOKENS = [];\n"
		. "function _sdListsAbove(n) { return n.lists.map(function (s) { return { props: { source: s } }; }); }\n"
		. checkout_blocks_test_var($source, 'SW_LIST_SOURCES') . checkout_blocks_test_var($source, '_SD_LIST_ROW_PREVIEWS')
		. checkout_blocks_test_var($source, 'SD_LIST_SAMPLE_ROWS') . checkout_blocks_test_var($source, '_SD_LIST_CARD_SAMPLES')
		. checkout_blocks_test_function($source, '_sdCheckoutRowSample') . "\n"
		. checkout_blocks_test_function($source, '_sdOrderAmounts') . "\n"
		. "var num = function (t) { return parseFloat(String(t).replace(/[^0-9,]/g, '').replace(',', '.')); };\n"
		. "var rows = [_SD_LIST_ROW_PREVIEWS].concat(SD_LIST_SAMPLE_ROWS['checkout.gift_cards']);\n"
		. "console.log(JSON.stringify({\n"
		. "  op: _sdCheckoutRowSample('__gift_card_amount', { lists: ['checkout.gift_cards'] }),\n"
		. "  nested: _sdCheckoutRowSample('__gift_card_amount', { lists: ['checkout.gift_cards', 'checkout.recipients'] }),\n"
		. "  item: _sdCheckoutRowSample('__gift_card_amount', { lists: ['order.item_gift_cards'] }),\n"
		. "  none: _sdCheckoutRowSample('__gift_card_amount', { lists: [] }),\n"
		. "  period: _sdCheckoutRowSample('__period_total', { lists: ['checkout.periods'] }),\n"
		. "  past: _sdCheckoutRowSample('__booking_manage_url', { lists: ['account.past_bookings'] }),\n"
		. "  upcoming: _sdCheckoutRowSample('__booking_manage_url', { lists: ['account.upcoming_bookings'] }),\n"
		. "  codes: rows.map(function (r) { return r.__gift_card_code; }),\n"
		. "  sum: Math.round(rows.reduce(function (a, r) { return a + num(r.__gift_card_amount); }, 0) * 100), gift: Math.round(_sdOrderAmounts().gift * 100)\n"
		. "}));";
	$file = tempnam(sys_get_temp_dir(), 'pgop');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	$r = json_decode(implode("\n", $out), true);
	pg_assert_same('₺25,00', $r['op'], 'an order preview gift card row draws its own amount');
	pg_assert_same('₺25,00', $r['nested'], 'the nearest list giving the value decides');
	pg_assert_same(null, $r['item'], 'an order view item gift card keeps the sample order\'s amount');
	pg_assert_same(null, $r['none'], 'outside a list the sample order\'s value stays');
	pg_assert_same('₺59,88', $r['period'], 'a period row draws its own total');
	pg_assert_same('', $r['past'], 'a past booking\'s card has no manage page');
	pg_assert_same(null, $r['upcoming'], 'an upcoming one reads the shared sample');
	pg_assert_same(3, count(array_unique($r['codes'])), 'the three sample cards are told apart');
	pg_assert_same($r['gift'], $r['sum'], 'the sample cards add up to the gift card discount of the totals');
}

// The recipients section as development printed it before its builder was
// split in two (pg_op_recipient_rows(), pg_op_recipients_html()): the
// yardstick the new builder is held to byte for byte.
function checkout_blocks_test_recipients_as_development_printed($d)
{
	$html = '';
	$describe = (($d['product_description_type'] ?? '') === 'short_description') ? 'short_description' : 'full_description';
	foreach ((array)($d['recipients'] ?? array()) as $r) {
		if (empty($r['items'])) continue;
		$html .= '<div class="pg-op-recipient mb-4">';
		if (!empty($r['ship_to_heading'])) {
			$html .= '<div class="d-flex flex-wrap justify-content-between gap-2 mb-2">'
				. '<div><div class="fw-semibold">' . h(lang('Ship to')) . ' ' . h((string)$r['ship_to_name']) . '</div>'
				. '<div class="small text-muted">' . h(trim((string)$r['name'])) . ($r['name'] !== '' ? '<br>' : '') . h((string)$r['address']) . '</div>';
			if (!empty($d['arrival_dates']) && !empty($r['arrival_date_name'])) {
				$arrival = !empty($r['arrival_date_custom'])
					? get_absolute_time(array('timestamp' => strtotime((string)$r['arrival_date']), 'type' => 'date', 'size' => 'long', 'format' => 'plain_text'))
					: (string)$r['arrival_date_name'];
				$html .= '<div class="small">' . h(lang('Requested Arrival Date')) . ': ' . h($arrival) . '</div>';
			}
			if ((string)$r['shipping_method_name'] !== '') {
				$html .= '<div class="small">' . h(lang('Shipping Method')) . ': ' . h((string)$r['shipping_method_name'])
					. (isset($r['shipping_cost_info']) ? ' (' . $r['shipping_cost_info'] . ')' : '') . '</div>';
			}
			$html .= '</div>' . ((string)($r['update_url'] ?? '') !== ''
				? '<a class="small" href="' . h((string)$r['update_url']) . '">' . h(lang('Change')) . '</a>' : '') . '</div>';
			if (!empty($r['form']) && !empty($r['form_data'])) {
				$html .= '<div class="small mb-2">' . pg_op_review_fields_html($r['fields']) . '</div>';
			}
		}
		$html .= '<ul class="list-group">';
		foreach ($r['items'] as $item) {
			// The full description is the product's own HTML, the short one text.
			$name = (string)$item[$describe] === '' ? h((string)$item['name'])
				: ($describe === 'short_description' ? h((string)$item[$describe]) : (string)$item[$describe]);
			$html .= '<li class="list-group-item"><div class="d-flex flex-wrap justify-content-between gap-2">'
				. '<div><div>' . $name . '</div><div class="small text-muted">' . h((string)$item['name'])
				. ' &times; ' . (int)$item['quantity'] . (isset($item['price_info']) ? ' @ ' . $item['price_info'] : '') . '</div></div>'
				. '<div class="fw-semibold text-nowrap">' . $item['amount_info'] . '</div></div>';
			if (!empty($item['show_out_of_stock_message'])) {
				$html .= '<div class="small text-danger">' . $item['out_of_stock_message'] . '</div>';
			}
			if (!empty($item['calendar_event']['name'])) {
				$html .= '<div class="small">' . h((string)$item['calendar_event']['name'])
					. (isset($item['calendar_event']['date_and_time_range']) ? ' — ' . $item['calendar_event']['date_and_time_range'] : '') . '</div>';
			}
			if (!empty($item['recurring'])) {
				$html .= '<div class="small text-muted">' . h(lang('Payment Period')) . ': ' . h(lang((string)$item['recurring_payment_period']))
					. ((int)$item['recurring_number_of_payments'] > 0 ? ', ' . h(lang('Number of Payments')) . ': ' . (int)$item['recurring_number_of_payments'] : '')
					. (!empty($d['start_date']) && (string)$item['recurring_start_date'] !== '' ? ', ' . h(lang('Start Date')) . ': ' . h(get_absolute_time(array('timestamp' => strtotime((string)$item['recurring_start_date']), 'type' => 'date', 'size' => 'long', 'format' => 'plain_text'))) : '') . '</div>';
			}
			foreach ((array)($item['gift_cards'] ?? array()) as $n => $card) {
				if (!is_array($card) || (string)$card['recipient_email_address'] === '') continue;
				$html .= '<div class="small">' . h(lang('Gift Card')) . ' ' . ($n + 1) . ': ' . h((string)$card['recipient_email_address'])
					. ((string)$card['from_name'] !== '' ? ', ' . h(lang('From')) . ' ' . h((string)$card['from_name']) : '') . '</div>';
			}
			foreach ((array)($item['forms'] ?? array()) as $form) {
				if (!empty($form['data'])) $html .= '<div class="small mt-1">' . pg_op_review_fields_html($form['fields']) . '</div>';
			}
			$html .= '</li>';
		}
		$html .= '</ul></div>';
	}
	return $html;
}

// get_order_preview()'s recipients for a sample order of two recipients:
// the first with an arrival date, a shipping method at a discount and a
// custom shipping form; its items a product with a product form and a price
// lowered by an offer, an event out of stock, a monthly recurring service,
// and two gift cards (the second sent by nobody named). The second
// recipient has no heading (nothing ships to it) and one item.
function checkout_blocks_test_recipients()
{
	$field = function ($label, $data) { return array('label' => $label, 'type' => 'text box', 'data_info' => $data); };
	return array(
		'product_description_type' => 'full_description',
		'arrival_dates' => true,
		'start_date' => true,
		'recipients' => array(
			array(
				'id' => 11, 'ship_to_heading' => true, 'ship_to_name' => 'Myself', 'name' => 'Ms. Sam Carter ',
				'address' => 'Cooper Studio, Harbour Road 12, Portland, Oregon, 97201, USA',
				'arrival_date_name' => 'Standard', 'arrival_date_custom' => false, 'arrival_date' => '',
				'shipping_method_name' => 'Express <24h>',
				'shipping_cost_info' => '<span style="text-decoration: line-through; white-space: nowrap">₺60,00</span> <span style="white-space: nowrap" class="software_discounted_price">₺49,90</span>',
				'update_url' => '/shipping?ship_to_id=11&a=1',
				'form' => true, 'form_data' => true, 'fields' => array($field('Door code', '4 &amp; 2'), array('label' => 'Note', 'type' => 'information', 'data_info' => 'x')),
				'items' => array(
					array('id' => 101, 'name' => 'PRD-1001', 'full_description' => '<p>Linen <b>Shirt</b></p>', 'quantity' => 2,
						'price_info' => checkout_blocks_test_price('₺500,00') . ' ' . checkout_blocks_test_price('₺450,00'), 'amount_info' => checkout_blocks_test_price('₺900,00'),
						'forms' => array(array('data' => true, 'fields' => array($field('Monogram', 'A.M.'))), array('data' => false, 'fields' => array()))),
					array('id' => 102, 'name' => 'YOGA', 'full_description' => '', 'quantity' => 1, 'amount_info' => checkout_blocks_test_price('₺0,00'),
						'show_out_of_stock_message' => true, 'out_of_stock_message' => '<em>Back in May</em>',
						'calendar_event' => array('name' => 'Yoga & Tea', 'date_and_time_range' => 'Monday, May 4, 2026')),
					array('id' => 103, 'name' => 'SP1', 'full_description' => 'Monthly plan', 'quantity' => 1, 'price_info' => checkout_blocks_test_price('₺79,95'),
						'amount_info' => checkout_blocks_test_price('₺79,95'), 'recurring' => true, 'recurring_payment_period' => 'Monthly',
						'recurring_number_of_payments' => 12, 'recurring_start_date' => '2026-12-01'),
					array('id' => 104, 'name' => 'GC50', 'full_description' => 'Gift card', 'quantity' => 3, 'price_info' => checkout_blocks_test_price('₺50,00'),
						'amount_info' => checkout_blocks_test_price('₺150,00'), 'calendar_event' => array('name' => 'Gala'),
						'gift_cards' => array(array('recipient_email_address' => 'ann@example.com', 'from_name' => 'Bo'), false,
							array('recipient_email_address' => 'cy@example.com', 'from_name' => ''), array('recipient_email_address' => '', 'from_name' => 'X'))),
				),
			),
			array('id' => 12, 'ship_to_heading' => false, 'items' => array()),
			array('id' => 0, 'ship_to_heading' => false, 'items' => array(
				array('id' => 105, 'name' => 'E-BOOK', 'full_description' => '', 'quantity' => 1, 'price_info' => checkout_blocks_test_price('₺14,95'),
					'amount_info' => checkout_blocks_test_price('₺14,95')),
			)),
		),
	);
}

// The section's new builder prints what development printed.
function test_checkout_blocks_recipients_section_unchanged()
{
	$d = checkout_blocks_test_recipients();
	$old = checkout_blocks_test_recipients_as_development_printed($d);
	pg_assert_contains('Linen <b>Shirt</b>', $old, 'the fixture reaches the description');
	pg_assert_same($old, pg_op_recipients_html(pg_op_recipient_rows($d)), 'byte for byte');
	pg_assert_same('', pg_op_recipients_html(pg_op_recipient_rows(array('recipients' => array()))), 'no recipient, nothing');
}

// The opened recipients part, drawn with its three lists, says what the
// section says, its classes and links kept.
function test_checkout_blocks_recipients_part_prints_the_sections_words()
{
	$d = checkout_blocks_test_recipients();
	$old = pg_op_recipients_html(pg_op_recipient_rows($d));
	$new = checkout_blocks_test_draw('order_recipients', $d);
	pg_assert_same(false, strpos($new, '^^'), 'no token left');
	pg_assert_same(checkout_blocks_test_text($old), checkout_blocks_test_text($new), 'the same words');
	preg_match_all('/class="([^"]*)"/', $old, $m);
	foreach (array_unique($m[1]) as $class) {
		pg_assert_contains('class="' . $class . '"', $new, 'class ' . $class);
	}
	foreach (array('href="/shipping?ship_to_id=11&amp;a=1"', '<p>Linen <b>Shirt</b></p>', '<em>Back in May</em>',
		'<span style="text-decoration: line-through; white-space: nowrap">₺60,00</span>', '<dt class="col-sm-4">Door code</dt>', 'Express &lt;24h&gt;', 'Yoga &amp; Tea') as $piece) {
		pg_assert_contains($piece, $new, 'kept: ' . $piece);
	}
	pg_assert_same(2, substr_count($new, 'class="pg-op-recipient mb-4"'), 'two recipients with items');
}

// The rows the recipient lists read: the recipients under the whole order,
// each one's items under its id, each item's gift cards under the item's id;
// flags for the values a condition may not read (markup).
function test_checkout_blocks_recipient_list_rows()
{
	$rows = pg_op_list_rows(checkout_blocks_test_recipients());
	pg_assert_same(array('11', '0'), array_column($rows['checkout.ship_tos'][0], '__ship_to_key'), 'two recipients with items, in order');
	$first = $rows['checkout.ship_tos'][0][0];
	pg_assert_same(array('1', 'Myself', 'Ms. Sam Carter', '1', '1'),
		array($first['__ship_to_heading'], $first['__ship_to_name'], $first['__ship_to_person'], $first['__ship_to_has_cost'], $first['__ship_to_has_form']), 'the heading');
	pg_assert_same(array('', '', ''), array($rows['checkout.ship_tos'][0][1]['__ship_to_heading'], $rows['checkout.ship_tos'][0][1]['__ship_to_has_cost'],
		$rows['checkout.ship_tos'][0][1]['__ship_to_cost_html']), 'nothing ships to the second');
	pg_assert_same(array('11', '0'), array_map('strval', array_keys($rows['checkout.lines'])), 'items under their recipient');
	pg_assert_same(array('101', '102', '103', '104'), array_column($rows['checkout.lines']['11'], '__line_key'));
	$yoga = $rows['checkout.lines']['11'][1];
	pg_assert_same(array('', '1', 'Yoga & Tea', 'Monday, May 4, 2026'), array($yoga['__line_has_price'], $yoga['__line_has_stock_note'], $yoga['__line_event'], $yoga['__line_event_dates']), 'a donation-like line without a price, out of stock, an event');
	pg_assert_same('', $rows['checkout.lines']['11'][3]['__line_event_dates'], 'an event without dates');
	pg_assert_same(array('104'), array_map('strval', array_keys($rows['checkout.line_gift_cards'])), 'gift cards under their item');
	pg_assert_same(array(array('__line_card_number' => '1', '__line_card_email' => 'ann@example.com', '__line_card_from' => 'Bo'),
		array('__line_card_number' => '3', '__line_card_email' => 'cy@example.com', '__line_card_from' => '')), $rows['checkout.line_gift_cards']['104'],
		'a card without a recipient gives no row; the number counts every card');
	foreach (array('__ship_to_cost_html', '__ship_to_form_html', '__line_title_html', '__line_price_html', '__line_amount_html', '__line_stock_html', '__line_forms_html') as $key) {
		pg_assert_true(isset(pg_sw_raw_token_keys()[$key]), $key . ' is printed as markup');
		pg_assert_false(pg_sw_cond_token_allowed($key), $key . ' is no condition: its flag is');
	}
}

// The unopened recipients section on the canvas is its builder's output.
function test_checkout_blocks_recipients_section_twin()
{
	$rows = pg_op_recipient_rows(checkout_blocks_test_recipients());
	$drawn = checkout_blocks_test_run('[_sdOpRecipientsHtml(' . json_encode($rows) . '), _sdOpRecipientsHtml([]), '
		. '_sdOpReviewFieldsHtml([{ label: "Door & code", type: "text box", data_info: "4 &amp; 2" }, { label: "Note", type: "information", data_info: "x" }, { label: "Empty", type: "text box", data_info: "" }]), _sdOpReviewFieldsHtml([])]');
	if ($drawn === null) {
		return;
	}
	pg_assert_same(pg_op_recipients_html($rows), $drawn[0], 'two recipients with a form, an event, a gift card and a recurring item');
	pg_assert_same('', $drawn[1], 'none');
	pg_assert_same(pg_op_review_fields_html(array(array('label' => 'Door & code', 'type' => 'text box', 'data_info' => '4 &amp; 2'),
		array('label' => 'Note', 'type' => 'information', 'data_info' => 'x'), array('label' => 'Empty', 'type' => 'text box', 'data_info' => ''))), $drawn[2], 'the review fields');
	pg_assert_same('', $drawn[3], 'no field');
}

