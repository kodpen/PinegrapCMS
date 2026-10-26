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
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains("{ type: 'order_preview'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'order_preview': {", $js, 'has a starter tree');
}
