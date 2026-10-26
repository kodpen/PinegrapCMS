<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Billing Information system widget
 * (includes/fn/widgets_billing_information.php): the values the form opens
 * with, the page row the settings write, the flags, the handler's page
 * check and the places the kind is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_billing_information_widget_start_values()
{
	$order = array('billing_first_name' => 'Ada', 'billing_city' => '', 'referral_source_code' => '', 'opt_in' => '1', 'tax_exempt' => '0');
	$contact = array('first_name' => 'Grace', 'business_city' => 'Izmir', 'lead_source' => 'web', 'tax_number' => '123', 'tax_office' => 'Konak');
	$v = pg_bi_start_values($order, $contact, false);
	pg_assert_same('Ada', $v['billing_first_name'], 'the order wins');
	pg_assert_same('Izmir', $v['billing_city'], 'else the contact');
	pg_assert_same('web', $v['referral_source'], 'the referral source falls back to the lead source');
	pg_assert_same('', $v['custom_field_1'], 'custom fields come only from the order');
	pg_assert_same('123', $v['tax_number'], 'tax number from the contact');
	pg_assert_same('1', $v['opt_in'], 'opt-in from the order');
	pg_assert_same('', $v['tax_exempt'], 'not exempt');
	pg_assert_same('1', $v['update_contact'], 'update my contact starts on');
	$v = pg_bi_start_values(null, null, true);
	pg_assert_same('', $v['update_contact'], 'unless the visitor turned it off');
	pg_assert_same('', $v['billing_email_address'], 'no order, no contact');
}

function test_billing_information_widget_page_row_changes()
{
	pg_assert_same(array(), pg_bi_page_row_changes(array('next_page_id' => 4, 'po_number' => 1), array()), 'untouched settings keep the classic row');
	pg_assert_same(array('po_number' => 0, 'custom_field_1_label' => 'Gift note'),
		pg_bi_page_row_changes(array('po_number' => '1', 'custom_field_1_label' => ''), array('po_number' => false, 'custom_field_1_label' => ' Gift note ')), 'changed values');
	pg_assert_same(array(), pg_bi_page_row_changes(array('next_page_id' => '9', 'custom_field_2_required' => '1'), array('next_page_id' => 9, 'custom_field_2_required' => true)), 'same values, no write');
	pg_assert_same(array('next_page_id' => 3), pg_bi_page_row_changes(null, array('next_page_id' => 3, 'nothing' => 1)), 'a new row');
}

function test_billing_information_widget_flags()
{
	$f = pg_bi_flags(array('custom_field_1_label' => 'Note', 'custom_field_2_label' => '', 'po_number' => '1', 'form' => '0'), true, false, true, false);
	pg_assert_true($f['has_custom_field_1'] && !$f['has_custom_field_2'] && $f['has_po_number'], 'fields by the page row');
	pg_assert_false($f['has_custom_form'] || $f['can_update_contact'] || $f['has_tax_exempt'], 'parts that are off');
	pg_assert_true($f['shows_opt_in'] && $f['has_referral_sources'], 'opt-in and referral');
}

function test_billing_information_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['billing_information']), 'panel label');
	$map = pg_sw_page_type_widgets();
	pg_assert_same('billing_information', $map['billing information'], 'stands in for the classic type');
	$known = new ReflectionFunction('pg_order_page_known');
	$src = implode('', array_slice(file($known->getFileName()), $known->getStartLine() - 1, $known->getEndLine() - $known->getStartLine() + 1));
	pg_assert_contains('billing_information_pages.page_id IS NOT NULL', $src, 'the handler takes a designed page with a row');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains("{ type: 'billing_information'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'billing_information': {", $js, 'has a starter tree');
}
