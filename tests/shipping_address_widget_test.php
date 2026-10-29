<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Shipping Address and Arrival system widget
 * (includes/fn/widgets_shipping_address.php): the recipient refusals, the
 * billing box rule, the page row the settings write, the date box value,
 * the flags, the per-row names of the arrival controls, the state controls
 * and the places the kind is registered in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A semantic control as the starter tree draws it.
function shipping_address_widget_test_control($tag, $name, $more = array())
{
	return array('type' => 'semantic', 'props' => array('tag' => $tag,
		'_attrs' => array_merge(array(array('name' => 'name', 'value' => $name)), $more)), 'children' => array());
}

// The value of one attribute of a node, null when it has none.
function shipping_address_widget_test_attr($node, $name)
{
	foreach ($node['props']['_attrs'] as $a) {
		if ($a['name'] === $name) return $a['value'];
	}
	return null;
}

function test_shipping_address_widget_recipient_problem()
{
	pg_assert_same('not_found', pg_sa_recipient_problem(null, '5'), 'no recipient');
	pg_assert_same('complete', pg_sa_recipient_problem(array('order_id' => '5', 'order_status' => 'complete'), '5'), 'complete order');
	pg_assert_same('complete', pg_sa_recipient_problem(array('order_id' => '5', 'order_status' => 'exported'), '5'), 'exported order');
	pg_assert_same('no_order', pg_sa_recipient_problem(array('order_id' => '5', 'order_status' => 'incomplete'), ''), 'session expired');
	pg_assert_same('other_order', pg_sa_recipient_problem(array('order_id' => '5', 'order_status' => 'incomplete'), 6), 'another order');
	pg_assert_same('', pg_sa_recipient_problem(array('order_id' => '5', 'order_status' => 'incomplete'), 5), 'own recipient');
	pg_assert_true(pg_sa_recipient_problem_message('other_order') !== '', 'each refusal has its words');
	pg_assert_same('', pg_sa_recipient_problem_message(''), 'no words without a refusal');
}

function test_shipping_address_widget_same_as_billing()
{
	pg_assert_true(pg_sa_offers_same_as_billing(false, array('myself', 'Mom'), 'Mom', false), 'single mode always offers it');
	pg_assert_false(pg_sa_offers_same_as_billing(false, array(), 'myself', true), 'not once the order has billing details');
	pg_assert_false(pg_sa_offers_same_as_billing(true, array('myself', 'Mom'), 'Mom', false), 'not for another recipient beside myself');
	pg_assert_true(pg_sa_offers_same_as_billing(true, array('myself', 'Mom'), 'myself', false), 'offered to myself');
	pg_assert_true(pg_sa_offers_same_as_billing(true, array('Dad', 'Mom'), 'Mom', false), 'offered when nobody is myself');
}

function test_shipping_address_widget_page_row_changes()
{
	pg_assert_same(array(), pg_sa_page_row_changes(array('next_page_id' => 4, 'address_type' => 1), array()), 'untouched settings keep the classic row');
	pg_assert_same(array(), pg_sa_page_row_changes(array('next_page_id' => 4), array('next_page_id' => 0)), 'no next page chosen changes nothing');
	pg_assert_same(array('next_page_id' => 9), pg_sa_page_row_changes(array('next_page_id' => '4'), array('next_page_id' => '9')), 'a chosen next page');
	pg_assert_same(array(), pg_sa_page_row_changes(array('next_page_id' => '9'), array('next_page_id' => 9)), 'same value, no write');
	pg_assert_same(array('address_type' => 0, 'address_type_page_id' => 0),
		pg_sa_page_row_changes(array('address_type' => '1', 'address_type_page_id' => '3'), array('address_type' => false, 'address_type_page_id' => -2)), 'switched off');
	pg_assert_same(array('next_page_id' => 7, 'address_type' => 1),
		pg_sa_page_row_changes(null, array('next_page_id' => 7, 'address_type' => true)), 'a new row takes every setting');
}

function test_shipping_address_widget_date_input_value()
{
	pg_assert_same('2026-11-03', pg_sa_date_input_value('2026-11-03'), 'ISO stays');
	pg_assert_same('', pg_sa_date_input_value('0000-00-00'), 'no date');
	pg_assert_same('', pg_sa_date_input_value(''), 'empty');
	pg_assert_same('', pg_sa_date_input_value('soon'), 'not a date');
}

function test_shipping_address_widget_flags()
{
	$on = pg_sa_flags(true, false, true, false);
	pg_assert_true($on['has_recipient'] && !$on['no_recipient'] && $on['has_arrival_dates'], 'a recipient with arrival dates');
	pg_assert_false($on['has_custom_form'], 'no custom form');
	$off = pg_sa_flags(false, true, true, true);
	pg_assert_true($off['no_recipient'] && $off['is_multi_recipient'], 'no recipient');
	pg_assert_false($off['has_arrival_dates'] || $off['has_custom_form'], 'the form blocks need a recipient');
	pg_assert_false($on['has_verified_addresses'], 'no verified address, no help box');
	pg_assert_true(pg_sa_flags(true, false, false, false, true)['has_verified_addresses'], 'verified addresses show the help box');
	pg_assert_false(pg_sa_flags(false, false, false, false, true)['has_verified_addresses'], 'the help box needs a recipient');
}

function test_shipping_address_widget_arrival_row_names()
{
	$row = array('type' => 'root', 'props' => array(), 'children' => array(
		shipping_address_widget_test_control('input', 'arrival_date', array(array('name' => 'type', 'value' => 'radio'), array('name' => 'value', 'value' => ''))),
		shipping_address_widget_test_control('input', 'custom_arrival_date', array(array('name' => 'type', 'value' => 'date'))),
	));
	_pg_sa_name_arrival_row($row, 12);
	pg_assert_same('12', shipping_address_widget_test_attr($row['children'][0], 'value'), 'the radio carries the date id');
	pg_assert_same('custom_arrival_date_12', shipping_address_widget_test_attr($row['children'][1], 'name'), 'the day box takes the handler name');
}

function test_shipping_address_widget_state_controls()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		shipping_address_widget_test_control('input', 'state', array(array('name' => 'type', 'value' => 'text'))),
		shipping_address_widget_test_control('select', 'state', array(array('name' => 'hidden', 'value' => ''))),
	));
	$with = $tree;
	_pg_sa_state_controls($with, 'CA', array(array('AZ', 'Arizona'), array('CA', 'California')));
	pg_assert_same('', shipping_address_widget_test_attr($with['children'][0], 'name'), 'the text box posts nothing for a country with states');
	pg_assert_same('', shipping_address_widget_test_attr($with['children'][0], 'hidden'), 'and is hidden');
	pg_assert_same('state', shipping_address_widget_test_attr($with['children'][1], 'name'), 'the pick list posts');
	pg_assert_same(null, shipping_address_widget_test_attr($with['children'][1], 'hidden'), 'and is shown');
	pg_assert_same(3, count($with['children'][1]['children']), 'an empty choice and the states');
	pg_assert_same('', shipping_address_widget_test_attr($with['children'][1]['children'][2], 'selected'), 'the stored state is chosen');
	pg_assert_same('list', shipping_address_widget_test_attr($with['children'][1], 'data-pg-sa-state'), 'marked for the script');

	$without = $tree;
	_pg_sa_state_controls($without, 'Bavaria', array());
	pg_assert_same('state', shipping_address_widget_test_attr($without['children'][0], 'name'), 'the text box posts for a country without states');
	pg_assert_same('Bavaria', shipping_address_widget_test_attr($without['children'][0], 'value'), 'with the stored state');
	pg_assert_same('', shipping_address_widget_test_attr($without['children'][1], 'name'), 'the pick list posts nothing');
}

function test_shipping_address_widget_state_script()
{
	$js = pg_sa_state_script('pg-sa-form-w3', array('US' => array(array('CA', 'Cal</script>'))), array('US'));
	pg_assert_contains('getElementById("pg-sa-form-w3")', $js, 'scoped to the widget form');
	pg_assert_false(strpos($js, 'Cal</script>') !== false, 'a state name cannot close the script');
	pg_assert_contains('custom_arrival_date_', $js, 'a picked day selects its arrival date');
}

function test_shipping_address_widget_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['shipping_address_and_arrival']), 'panel label');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("'shipping_address_and_arrival'", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'shipping_address_and_arrival'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'shipping_address_and_arrival': {", $js, 'has a starter tree');
	pg_assert_contains("shipping_address_and_arrival: SW_SHIPPING_ADDRESS_TOKEN_GROUPS", $js, 'has a token palette');
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/shipping_address_and_arrival.php');
	pg_assert_contains('shipping_address_and_arrival_pages.page_id IS NOT NULL', $handler, 'the handler takes a designed page with a row');
}
