<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a classic view order layout drawn by the order view widget:
 * the flags the widget gives a converted layout.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_vol_layout_flags()
{
	$f = pg_ov_layout_flags(array(
		'billing_company' => 'Acme', 'billing_address_2' => ' ', 'billing_fax_number' => '', 'po_number' => 'PO-1',
		'tax_exempt' => '1', 'gift_card_discount' => '500', 'custom_field_1_label' => 'Tax office', 'custom_field_1' => '',
		'custom_field_2_label' => 'Tax number', 'custom_field_2' => '42',
	), true);
	pg_assert_true($f['billing_has_company'] && $f['has_po_number'] && $f['is_tax_exempt'] && $f['has_gift_card_discount'], 'filled');
	pg_assert_false($f['billing_has_address_2'] || $f['billing_has_fax'], 'blank lines');
	pg_assert_false($f['shows_custom_field_1'], 'a label with no value');
	pg_assert_true($f['shows_custom_field_2'] && $f['shows_shipped_quantity']);

	$f = pg_ov_layout_flags(array('tax_exempt' => '0', 'gift_card_discount' => '0'), false);
	pg_assert_same(array(), array_keys(array_filter($f)), 'an order with none');
}

// The extra reads are made only for a tree that names one of the flags.
function test_vol_layout_flags_named()
{
	pg_assert_true(pg_ov_layout_flags_named(json_encode(array('props' => array('_bindings' => array('eo_visible_if' => 'has_po_number'))))));
	pg_assert_false(pg_ov_layout_flags_named(json_encode(array('props' => array('_bindings' => array('eo_visible_if' => 'has_tax'))))));
	pg_assert_false(pg_ov_layout_flags_named(json_encode(array('props' => array('text' => 'has_po_number is a word')))));
}
