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
	foreach (array('_sdPartEl', '_sdPartSentence', '_sdOrderPreviewPart', '_sdOpOffersHtml', '_sdOpGiftCardsHtml', '_sdOpPeriodsHtml',
		'_sdInstallmentBoxHtml', '_sdInstallmentPlansHtml', '_sdInstallmentSteps', '_sdInstallmentTableHtml') as $fn) {
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
	pg_assert_same(array('checkout.offers', 'checkout.gift_cards', 'checkout.periods'), array_keys($rows));
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
	$text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
		pg_assert_same(checkout_blocks_test_text($old), checkout_blocks_test_text($new), $section . ': the same words');
		preg_match_all('/class="([^"]*)"/', $old, $m);
		foreach (array_unique($m[1]) as $class) {
			pg_assert_contains('class="' . $class . '"', $new, $section . ': class ' . $class);
		}
	}
	pg_assert_contains('href="/software/remove_gift_card_from_order.php?applied_gift_card_id=7&amp;send_to=%2Fcheckout&amp;token=t"',
		checkout_blocks_test_draw('applied_gift_cards', $d), 'the Remove link escaped once');

	$empty = array('applied_offers' => array(), 'applied_gift_cards' => array(), 'payment_periods' => array());
	pg_assert_same('', checkout_blocks_test_text(checkout_blocks_test_draw('applied_offers', $empty)), 'no offer: no words');
	pg_assert_same(array(), pg_op_part('order_recipients'), 'the recipients stay a section');
	pg_assert_same(array(), pg_op_part('billing_form'), 'the custom billing form stays its form\'s');
	pg_assert_same(array(), pg_op_part('card_installments'), 'installments stay the site\'s');
}

// The editor builds the same part as the server.
function test_checkout_blocks_part_twin()
{
	$drawn = checkout_blocks_test_run('["applied_offers", "applied_gift_cards", "recurring_payments", "order_recipients", "installment_table"].map(_sdOrderPreviewPart)');
	if ($drawn === null) {
		return;
	}
	$php = array_map('pg_op_part', array('applied_offers', 'applied_gift_cards', 'recurring_payments', 'order_recipients', 'installment_table'));
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
		"opPart(_sdT('Payment Periods'), 'recurring_payments', '', '')", "opSection(_sdT('Recipients and Items'), 'order_recipients')",
		"opSection(_sdT('Installments'), 'card_installments', '', '')") as $line) {
		pg_assert_contains($line, $js, 'the starter: ' . $line);
	}
	$starters = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_starters.php');
	pg_assert_contains("pg_op_part('applied_gift_cards')", $starters, 'the server\'s layouts open the gift cards too');
	pg_assert_same(false, strpos($starters, "_pg_st_keep('section:applied_gift_cards')"), 'no layout looks for the old section');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'installments'         => (defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY === 'Iyzipay'", $screen);
}
