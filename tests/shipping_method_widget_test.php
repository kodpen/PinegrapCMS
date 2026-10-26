<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Shipping Method system widget
 * (includes/fn/widgets_shipping_method.php): the address lines, the method
 * chosen when the page opens, the flags and the places the kind is
 * registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_shipping_method_widget_address_lines()
{
	$r = array('company' => 'Kodpen', 'address_1' => 'Main St 1', 'address_2' => '', 'zip_code' => '34000',
		'city' => 'Istanbul', 'state' => 'Istanbul', 'country' => 'TR');
	pg_assert_same(array('Kodpen', 'Main St 1', '34000 Istanbul', 'Turkey'), pg_sm_address_lines($r, 'Turkey'), 'a state equal to the city is left out');
	$r['state'] = 'CA';
	$r['company'] = '';
	pg_assert_same(array('Main St 1', '34000 Istanbul CA', 'TR'), pg_sm_address_lines($r), 'no country name: the code');
	pg_assert_same(array(), pg_sm_address_lines(null), 'no recipient');
}

function test_shipping_method_widget_chosen_method()
{
	pg_assert_same('7', pg_sm_chosen_method(array(5, 7), '7', '5'), 'the posted method first');
	pg_assert_same('5', pg_sm_chosen_method(array(5, 7), '', '5'), 'else the stored one');
	pg_assert_same('', pg_sm_chosen_method(array(5, 7), '9', '0'), 'a method no longer offered is not chosen');
	pg_assert_same('5', pg_sm_chosen_method(array(5), '', ''), 'the only method');
	pg_assert_same('', pg_sm_chosen_method(array(), '', '3'), 'nothing offered');
}

function test_shipping_method_widget_flags()
{
	$f = pg_sm_flags(true, false, 0, true);
	pg_assert_true($f['no_shipping_methods'] && !$f['has_shipping_methods'] && $f['has_arrival_date'], 'a recipient without methods');
	$f = pg_sm_flags(false, true, 3, true);
	pg_assert_true($f['no_recipient'] && $f['is_multi_recipient'], 'no recipient');
	pg_assert_false($f['has_shipping_methods'] || $f['no_shipping_methods'] || $f['has_arrival_date'], 'the form blocks need a recipient');
}

function test_shipping_method_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['shipping_method']), 'panel label');
	$map = pg_sw_page_type_widgets();
	pg_assert_same('shipping_method', $map['shipping method'], 'stands in for the classic type');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_shipping_method(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains("{ type: 'shipping_method'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'shipping_method': {", $js, 'has a starter tree');
	pg_assert_contains("shipping_method:   SW_SHIPPING_METHOD_TOKEN_GROUPS", $js, 'has a token palette');
}
