<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Order Preview system widget
 * (includes/fn/widgets_order_preview.php): the flags drawn from the classic
 * page's values, the page row the settings write, the review markup, the
 * handler's page check and the places the kind is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_preview_widget_flags()
{
	$d = array(
		'recipients' => array(array('id' => '0')), 'shippable_items' => false, 'show_subtotal' => true,
		'discount_info' => '5,00', 'tax_info' => '', 'shipping_info' => '', 'gift_card_discount_info' => '',
		'show_surcharge' => true, 'surcharge_message' => true, 'total_disclaimer' => false, 'recurring_items' => false,
		'payment' => true, 'gift_card_code' => true, 'number_of_payment_methods' => 2, 'credit_debit_card' => true,
		'paypal_express_checkout' => false, 'pay_with_iyzico' => false, 'offline_payment' => true,
		'card_verification_number_url' => '', 'installment' => false, 'threedsecure' => true, 'threedsecure_required' => '',
		'terms_url' => '/terms', 'purchase_now_button' => true, 'billing_form' => false, 'custom_field_1_label' => 'Note',
		'custom_field_2_label' => '', 'po_number' => '', 'tax_exempt' => false, 'number_of_applied_offers' => 1,
		'number_of_applied_gift_cards' => 0,
	);
	$f = pg_op_flags($d);
	pg_assert_true($f['has_items'] && !$f['cart_empty'], 'items');
	pg_assert_true($f['has_discount'] && !$f['has_tax'] && !$f['has_shipping'], 'figures by their values');
	pg_assert_true($f['has_surcharge'] && $f['surcharge_message'], 'surcharge');
	pg_assert_true($f['many_payment_methods'] && $f['pays_by_card'] && $f['pays_offline'] && !$f['pays_by_paypal'], 'payment methods');
	pg_assert_true($f['has_threedsecure'] && !$f['threedsecure_required'], '3-D Secure offered, not forced');
	pg_assert_true($f['has_terms'] && $f['can_purchase'] && $f['has_applied_offers'], 'terms, button, offers');
	pg_assert_true($f['has_custom_field_1'] && !$f['has_custom_field_2'] && !$f['has_po_number'], 'billing extras by their labels and values');
	$f = pg_op_flags(array());
	pg_assert_true($f['cart_empty'] && !$f['has_items'] && !$f['can_purchase'] && !$f['has_payment'], 'no data, an empty cart');
}

function test_order_preview_widget_page_row_changes()
{
	pg_assert_same(array(), pg_op_page_row_changes(array('next_page_id' => 4), array()), 'untouched settings keep the classic row');
	pg_assert_same(array('terms_page_id' => 7, 'offline_payment_label' => 'Bank transfer'),
		pg_op_page_row_changes(array('terms_page_id' => '0', 'offline_payment_label' => ''), array('terms_page_id' => '7', 'offline_payment_label' => ' Bank transfer ')), 'changed values');
	pg_assert_same(array(), pg_op_page_row_changes(array('next_page_id' => '9', 'offline_payment_always_allowed' => '1'), array('next_page_id' => 9, 'offline_payment_always_allowed' => true)), 'same values, no write');
	pg_assert_same(array('next_page_id' => 3), pg_op_page_row_changes(null, array('next_page_id' => 3, 'nothing' => 1)), 'a new row');
	pg_assert_same(array(), pg_op_page_row_changes(array('next_page_id' => 3), array('next_page_id' => 0)), 'no next page leaves the stored one');
	pg_assert_same(array('order_receipt_email' => 1, 'order_receipt_email_page_id' => 12, 'order_receipt_email_format' => 'html'),
		pg_op_page_row_changes(array('order_receipt_email' => '0', 'order_receipt_email_page_id' => '0', 'order_receipt_email_format' => 'plain_text'),
			array('order_receipt_email_page_id' => 12)), 'a receipt page turns the e-mail on');
	pg_assert_same(array('order_receipt_email' => 0, 'order_receipt_email_page_id' => 0),
		pg_op_page_row_changes(array('order_receipt_email' => '1', 'order_receipt_email_page_id' => '12', 'order_receipt_email_format' => 'html'),
			array('order_receipt_email_page_id' => 0)), 'no receipt page turns it off');
}

function test_order_preview_widget_review_fields()
{
	$html = pg_op_review_fields_html(array(
		array('type' => 'information', 'label' => 'Intro', 'data_info' => ''),
		array('type' => 'text box', 'label' => 'Gift <note>', 'data_info' => 'Happy &amp; well'),
		array('type' => 'text box', 'label' => 'Empty', 'data_info' => ''),
	));
	pg_assert_contains('<dt class="col-sm-4">Gift &lt;note&gt;</dt>', $html, 'label escaped');
	pg_assert_contains('<dd class="col-sm-8">Happy &amp; well</dd>', $html, 'data kept as prepared');
	pg_assert_false(strpos($html, 'Intro') !== false || strpos($html, 'Empty') !== false, 'information and empty fields left out');
	pg_assert_same('', pg_op_review_fields_html(array()), 'nothing to show');
}

function test_order_preview_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['order_preview']), 'panel label');
	$map = pg_sw_page_type_widgets();
	pg_assert_same('order_preview', $map['order preview'], 'stands in for the classic type');
	$known = new ReflectionFunction('pg_order_page_known');
	$src = implode('', array_slice(file($known->getFileName()), $known->getStartLine() - 1, $known->getEndLine() - $known->getStartLine() + 1));
	pg_assert_contains('order_preview_pages.page_id IS NOT NULL', $src, 'the handler takes a designed page with a row');
	$classic = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_order_preview.php');
	pg_assert_contains("if (\$layout_data_only) {\n            return \$layout_data;", $classic, 'the classic computation hands its values to the widget');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'order_preview'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'order_preview': {", $js, 'has a starter tree');
}

function test_order_preview_widget_method_classes()
{
	$text = function ($token) {
		return array('type' => 'content', 'props' => array('contentType' => 'span', 'text' => '0', 'cssClass' => '', '_bindings' => array('text' => $token)));
	};
	$row = function ($token, $flag, $css) use ($text) {
		$p = array('tag' => 'div', 'cssClass' => $css);
		if ($flag !== '') $p['_bindings'] = array('eo_visible_if' => $flag);
		return array('type' => 'semantic', 'props' => $p, 'children' => array(
			array('type' => 'content', 'props' => array('contentType' => 'span', 'text' => 'Label', 'cssClass' => '')),
			array('type' => 'semantic', 'props' => array('tag' => 'span', 'cssClass' => 'text-nowrap'), 'children' => array($text($token))),
		));
	};
	$input = function ($name) {
		return array('type' => 'semantic', 'props' => array('tag' => 'input', 'cssClass' => 'form-control',
			'_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'name', 'value' => $name))));
	};
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => ''), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'border-top'), 'children' => array(
			$row('__cart_subtotal', 'shows_subtotal', 'd-flex'),
			$row('__cart_total', '', 'd-flex fw-bold'),
			$row('__cart_surcharge', 'has_surcharge', 'd-flex'),
			$row('__cart_total_with_surcharge', 'has_surcharge', 'd-flex fw-bold'),
		)),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'form-check', '_bindings' => array('eo_visible_if' => 'pays_by_card')),
			'children' => array($input('payment_method'))),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'border rounded', '_bindings' => array('eo_visible_if' => 'pays_by_card')),
			'children' => array(array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'row'), 'children' => array($input('card_number'), $input('expiration'))))),
	));
	$css = function ($t, $path) {
		foreach ($path as $i) $t = $t['children'][$i];
		return $t['props']['cssClass'];
	};

	$many = $tree;
	pg_op_stamp_method_classes($many, array('many_payment_methods' => true, 'has_surcharge' => true));
	pg_assert_same('d-flex', $css($many, array(0, 0)), 'the subtotal row is left alone');
	pg_assert_same('d-flex fw-bold total_row', $css($many, array(0, 1)), 'the total row');
	pg_assert_same('d-flex surcharge_row', $css($many, array(0, 2)), 'the surcharge row');
	pg_assert_same('d-flex fw-bold surcharge_total_row', $css($many, array(0, 3)), 'the total with the surcharge');
	pg_assert_same('', $css($many, array(0, 1, 1, 0)), 'the class goes on the row, not on the figure');
	pg_assert_same('form-check', $css($many, array(1)), 'the card radio row keeps showing');
	pg_assert_same('border rounded credit_debit_card', $css($many, array(2)), 'the card fields follow the method');

	$plain = $tree;
	pg_op_stamp_method_classes($plain, array('many_payment_methods' => true, 'has_surcharge' => false));
	pg_assert_same('d-flex fw-bold', $css($plain, array(0, 1)), 'no surcharge, the total row stays put');
	pg_assert_same('border rounded credit_debit_card', $css($plain, array(2)), 'card fields without a surcharge');

	$one = $tree;
	pg_op_stamp_method_classes($one, array('many_payment_methods' => false, 'has_surcharge' => true));
	pg_assert_same($tree, $one, 'one method, nothing switches');

	$again = $many;
	pg_op_stamp_method_classes($again, array('many_payment_methods' => true, 'has_surcharge' => true));
	pg_assert_same($many, $again, 'a class is stamped once');
}

// A payment section as the starter tree draws it: one radio row per method
// under its pays_by_* flag, then the card fields.
function _test_op_payment_tree()
{
	$method = function ($value, $label, $flag, $token = '') {
		$id = 'op_pm_' . trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($value)), '_');
		$lp = array('tag' => 'label', 'cssClass' => 'form-check-label', 'text' => $label, '_attrs' => array(array('name' => 'for', 'value' => $id)));
		if ($token !== '') $lp['_bindings'] = array('text' => $token);
		return array('type' => 'semantic', 'id' => 'n_' . $id, 'props' => array('tag' => 'div', 'cssClass' => 'form-check mb-2 my-look', '_bindings' => array('eo_visible_if' => $flag)),
			'children' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'input', 'cssClass' => 'form-check-input', 'id' => $id,
					'_attrs' => array(array('name' => 'type', 'value' => 'radio'), array('name' => 'name', 'value' => 'payment_method'),
						array('name' => 'value', 'value' => $value), array('name' => 'required', 'value' => '')))),
				array('type' => 'semantic', 'props' => $lp),
			));
	};
	$input = function ($name) {
		return array('type' => 'semantic', 'props' => array('tag' => 'input', 'cssClass' => 'form-control',
			'_attrs' => array(array('name' => 'type', 'value' => 'text'), array('name' => 'name', 'value' => $name))));
	};
	return array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => ''), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'mb-3', '_bindings' => array('eo_visible_if' => 'many_payment_methods')), 'children' => array(
			$method('Credit/Debit Card', 'Credit/Debit Card', 'pays_by_card'),
			$method('PayPal Express Checkout', 'PayPal', 'pays_by_paypal'),
			$method('Pay With Iyzico', 'Pay with iyzico', 'pays_by_iyzico'),
			$method('Offline Payment', 'Offline Payment', 'pays_offline', '__offline_payment_label'),
		)),
		array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'border rounded', '_bindings' => array('eo_visible_if' => 'pays_by_card')),
			'children' => array($input('card_number'), $input('expiration'))),
	));
}

// value => array(row flag, input id, label for, label text, label binding,
// row class) for each radio row of the payment section.
function _test_op_method_rows($tree)
{
	$rows = array();
	foreach ($tree['children'][0]['children'] as $row) {
		$attrs = _pg_cf_node_attrs($row['children'][0]);
		$label = $row['children'][1]['props'];
		$rows[$attrs['value']] = array(
			(string)($row['props']['_bindings']['eo_visible_if'] ?? ''), $attrs['id'] ?? '',
			_pg_cf_node_attrs($row['children'][1])['for'] ?? '', (string)($label['text'] ?? ''),
			(string)($label['_bindings']['text'] ?? ''), $row['props']['cssClass'],
		);
	}
	return $rows;
}

function test_order_preview_widget_driver_and_on_account_rows()
{
	$d = array('payment' => true, 'recurring_transaction' => false, 'number_of_payment_methods' => 4,
		'payment_driver_methods' => array('PayTR' => 'PayTR', 'PayPal Checkout' => 'PayPal'), 'system' => '');
	$option = array('value' => 'On Account', 'label' => 'Put it on my account', 'note' => 'Available credit: 1.000,00');
	pg_assert_true(pg_op_offer_on_account($d, $option), 'On Account offered');
	pg_assert_same(5, $d['number_of_payment_methods'], 'and counted');
	$tree = _test_op_payment_tree();
	pg_op_add_method_rows($tree, pg_op_method_rows($d, $option));
	$rows = _test_op_method_rows($tree);
	pg_assert_same(array('Credit/Debit Card', 'PayPal Express Checkout', 'Pay With Iyzico', 'PayTR', 'PayPal Checkout', 'Offline Payment', 'On Account'),
		array_keys($rows), 'one row per method, in the classic order');
	pg_assert_same(array('', 'op_pm_paytr', 'op_pm_paytr', 'PayTR', '', 'form-check mb-2 my-look'), $rows['PayTR'], 'a driver row in the tree\'s own look');
	pg_assert_same(array('', 'op_pm_paypal_checkout', 'op_pm_paypal_checkout', 'PayPal', '', 'form-check mb-2 my-look'), $rows['PayPal Checkout'], 'the driver\'s label');
	pg_assert_same(array('', 'op_pm_on_account', 'op_pm_on_account', 'Put it on my account (Available credit: 1.000,00)', '', 'form-check mb-2 my-look'),
		$rows['On Account'], 'On Account with its note');
	pg_assert_same('pays_offline', $rows['Offline Payment'][0], 'the saved rows keep their flags');

	// The visibility bindings then drop the methods the order is not offered;
	// the added rows stay.
	$flags = pg_op_flags($d + array('credit_debit_card' => true, 'offline_payment' => true));
	_eo_apply_visibility_bindings($tree, $flags);
	pg_assert_same(array('Credit/Debit Card', 'PayTR', 'PayPal Checkout', 'Offline Payment', 'On Account'), array_keys(_test_op_method_rows($tree)), 'after the visibility bindings');
	pg_op_stamp_method_classes($tree, $flags);
	pg_assert_same('border rounded credit_debit_card', $tree['children'][1]['props']['cssClass'], 'the card fields still follow the method');
	foreach ($tree['children'][0]['children'] as $row) {
		pg_assert_false(strpos($row['props']['cssClass'], 'credit_debit_card') !== false, 'no radio row hides with the card fields');
	}

	// Only the rows of the methods offered; none without a method row to copy.
	$tree = _test_op_payment_tree();
	pg_op_add_method_rows($tree, pg_op_method_rows(array('number_of_payment_methods' => 3, 'payment_driver_methods' => array()), null));
	pg_assert_same(_test_op_payment_tree(), $tree, 'nothing to add, the tree is unchanged');
	$bare = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array());
	pg_op_add_method_rows($bare, pg_op_method_rows($d, $option));
	pg_assert_same(array(), $bare['children'], 'no radio row to copy, nothing added');
}

function test_order_preview_widget_on_account_offer()
{
	$option = array('value' => 'On Account', 'label' => 'Put it on my account', 'note' => 'Available credit: 10');
	$hidden = '<input type="hidden" name="payment_method">';

	// One method before: the hidden field goes, the radios decide.
	$d = array('payment' => true, 'recurring_transaction' => false, 'number_of_payment_methods' => 1, 'offline_payment' => true,
		'system' => '<input type="hidden" name="x">' . $hidden . '<script>software.init_payment_method()</script>');
	pg_assert_false(pg_op_flags($d)['many_payment_methods'], 'one method alone');
	pg_assert_true(pg_op_offer_on_account($d, $option), 'offered beside the one method');
	pg_assert_true(pg_op_flags($d)['many_payment_methods'], 'two methods now');
	pg_assert_false(strpos($d['system'], $hidden) !== false, 'the single method\'s hidden field is dropped');
	pg_assert_contains('<input type="hidden" name="x">', $d['system'], 'the other system fields stay');

	// A recurring line: never on account (submit_order.php refuses it).
	$d = array('payment' => true, 'recurring_transaction' => true, 'number_of_payment_methods' => 1, 'system' => $hidden);
	pg_assert_false(pg_op_offer_on_account($d, $option), 'not for a recurring order');
	pg_assert_same(1, $d['number_of_payment_methods'], 'not counted');
	pg_assert_same($hidden, $d['system'], 'the hidden field stays');
	pg_assert_same(array(), pg_op_method_rows(array('number_of_payment_methods' => 2, 'payment_driver_methods' => array()), null), 'no On Account row');

	// No member account, no payment due, or no method at all: not offered.
	$d = array('payment' => true, 'recurring_transaction' => false, 'number_of_payment_methods' => 2, 'system' => '');
	pg_assert_false(pg_op_offer_on_account($d, null), 'no open account');
	$d = array('payment' => false, 'recurring_transaction' => false, 'number_of_payment_methods' => 0, 'system' => '');
	pg_assert_false(pg_op_offer_on_account($d, $option), 'nothing to pay');

	// Two or more methods before: the hidden field was never printed.
	$d = array('payment' => true, 'recurring_transaction' => false, 'number_of_payment_methods' => 2, 'system' => '<b></b>');
	pg_assert_true(pg_op_offer_on_account($d, $option), 'a third method');
	pg_assert_same(3, $d['number_of_payment_methods'], 'counted');
	pg_assert_same('<b></b>', $d['system'], 'system untouched');
}

function test_order_preview_widget_single_method_no_rows()
{
	// One method: the branch's hidden field posts it, no radio is drawn.
	$d = array('number_of_payment_methods' => 1, 'payment_driver_methods' => array('PayTR' => 'PayTR'));
	pg_assert_same(array(), pg_op_method_rows($d, null), 'a single driver method gets no row');
	$tree = _test_op_payment_tree();
	pg_op_add_method_rows($tree, pg_op_method_rows($d, null));
	pg_assert_same(_test_op_payment_tree(), $tree, 'the tree is unchanged');
	$d['number_of_payment_methods'] = 2;
	pg_assert_same(array(array('value' => 'PayTR', 'label' => 'PayTR', 'before_offline' => true)), pg_op_method_rows($d, null), 'beside another method it gets one');
}
