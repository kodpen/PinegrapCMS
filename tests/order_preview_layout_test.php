<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a classic order preview layout drawn by its widget: the flags
 * the order preview widget gives a converted layout, and the converter's
 * rules the order preview brought: a section loop drawn once, a flag kept
 * inside a tag, rows the widget adds itself, a description list holding
 * the widget's list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layout_convert.php');

function test_opl_layout_flags()
{
	$f = pg_op_flags(array(
		'recipients' => array(1), 'shipping_info' => '5,00', 'show_surcharge' => false, 'applied_gift_cards' => array(1),
		'purchase_now_button' => true, 'recurring_items' => false, 'number_of_payment_methods' => 1,
		'installment_table' => '<table></table>', 'custom_field_1_label' => 'Tax office', 'custom_field_1' => '',
		'custom_field_2_label' => 'Tax number', 'custom_field_2' => '42', 'billing_form_title' => 'More',
		'billing_first_name' => 'Ada', 'billing_company' => '', 'billing_address_1' => 'Main St 1', 'billing_address_2' => '',
		'billing_city' => '', 'billing_state' => '', 'billing_zip_code' => '34000', 'billing_country' => 'Turkey',
		'billing_phone_number' => '1', 'billing_fax_number' => '', 'billing_email_address' => 'a@b.c',
	));
	pg_assert_true($f['has_shipping_cost'] && $f['no_surcharge'] && $f['has_applied_gift_cards'], 'shipping, no surcharge, gift cards');
	pg_assert_true($f['can_purchase_in_totals'] && $f['one_payment_method'] && $f['has_installment_table'], 'button, one method, table');
	pg_assert_false($f['shows_custom_field_1'], 'a label with no value');
	pg_assert_true($f['shows_custom_field_2'] && $f['has_billing_form_title'], 'a label with a value, the form title');
	pg_assert_true($f['billing_has_name'] && $f['billing_has_address_1'] && $f['billing_has_country'], 'filled lines');
	pg_assert_false($f['billing_has_company'] || $f['billing_has_address_2'] || $f['billing_has_city'] || $f['billing_has_fax'], 'empty lines');
	pg_assert_true($f['billing_has_city_line'], 'the zip code alone makes the line');
	pg_assert_true($f['billing_has_phone'] && $f['billing_has_email']);

	$f = pg_op_flags(array('purchase_now_button' => true, 'recurring_items' => true, 'show_surcharge' => true, 'number_of_payment_methods' => 2));
	pg_assert_false($f['can_purchase_in_totals'], 'recurring items: the button only below');
	pg_assert_false($f['no_surcharge'] || $f['one_payment_method'] || $f['has_shipping_cost'] || $f['billing_has_city_line']);
}

// A second loop on a section writes nothing: the widget draws it once.
function test_opl_section_loop_once()
{
	$code = '<div class="a"><?php foreach($recipients as $recipient): ?><p>x</p><?php endforeach ?></div>'
		. '<div class="b"><?php foreach($recipients as $recipient): ?><p>y</p><?php endforeach ?></div>';
	$r = pg_layout_convert('order preview', $code, array());
	pg_assert_same(array(), $r['dropped']);
	pg_assert_same(array('order_recipients'), _test_opl_bound($r['tree'], 'section'));
}

// A flag inside a tag's attributes: kept when the map says so, else dropped.
function test_opl_flag_in_tag()
{
	$code = '<button name="submit_purchase_now" <?php if ($paypal_express_checkout): ?>data-paypal-label="PayPal"<?php endif ?>>Go</button>';
	$r = pg_layout_convert('order preview', $code, array());
	pg_assert_same(array(), $r['dropped']);
	pg_assert_contains('data-paypal-label', json_encode($r['tree']));
	$r = pg_layout_convert('order preview', '<p <?php if ($tax_info): ?>class="x"<?php endif ?>>t</p>', array());
	pg_assert_same(array('$tax_info'), array_column($r['dropped'], 'piece'), 'a plain flag in a tag');
}

// The redirect drivers' rows: not read, not reported.
function test_opl_loop_the_widget_adds()
{
	$code = '<?php foreach ($payment_driver_methods as $payment_driver_method => $payment_driver_label): ?><p><?=h($payment_driver_label)?></p><?php endforeach ?>';
	$r = pg_layout_convert('order preview', $code, array());
	pg_assert_same(array(), $r['dropped']);
	pg_assert_same(array(), $r['tree']['children']);
}

// A description list holding only the widget's section is that section.
function test_opl_description_list_listed()
{
	$r = pg_layout_convert('order preview', '<dl class="dl-horizontal"><?php foreach ($fields as $field): ?><dt>a</dt><?php endforeach ?></dl>', array());
	pg_assert_same(1, count($r['tree']['children']));
	pg_assert_same('billing_form', $r['tree']['children'][0]['props']['_bindings']['section']);
}

function _test_opl_bound($tree, $prop)
{
	$out = array();
	$walk = function ($nodes) use (&$walk, &$out, $prop) {
		foreach ((array)$nodes as $n) {
			if (isset($n['props']['_bindings'][$prop])) $out[] = $n['props']['_bindings'][$prop];
			$walk($n['children'] ?? array());
		}
	};
	$walk($tree['children']);
	return $out;
}
