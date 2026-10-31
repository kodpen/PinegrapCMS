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

function test_shipping_address_widget_verified_box()
{
	$html = pg_sa_verified_box_html('pg-sa-verified-w4', array(array('', ''), array('US', 'United States'), array('XX', 'Bad <b> & "co"')), 'US');
	pg_assert_contains('id="pg-sa-verified-w4"', $html, 'the box carries its id');
	pg_assert_contains('<option value="US" selected>United States</option>', $html, 'the default country is chosen');
	pg_assert_contains('Bad &lt;b&gt; &amp; &quot;co&quot;', $html, 'a country label is escaped');
	pg_assert_false(strpos($html, '<b>') !== false, 'no raw markup from a label');
	pg_assert_false(strpos($html, ' name=') !== false, 'the pick lists post nothing');
	foreach (array('country', 'state', 'address', 'message', 'summary', 'use') as $part) {
		pg_assert_contains('data-pg-sa-v="' . $part . '"', $html, 'the script finds ' . $part);
	}
	pg_assert_contains('type="button"', $html, 'the button does not submit the form');
	pg_assert_same(1, substr_count($html, '<option value=""></option>'), 'one empty country choice');
}

function test_shipping_address_widget_verified_script()
{
	$js = pg_sa_verified_script('pg-sa-form-w4', 'pg-sa-verified-w4', array(array('7', 'California', 'US')), array(array(
		'id' => '3', 'label' => 'Acme</script>, LA', 'company' => 'Acme</script>', 'address_1' => '1 Main', 'address_2' => '',
		'city' => 'LA', 'state_id' => '7', 'state_code' => 'CA', 'zip_code' => '90001', 'country_code' => 'US', 'country_name' => 'United States')));
	pg_assert_contains('getElementById("pg-sa-form-w4")', $js, 'scoped to the widget form');
	pg_assert_contains('getElementById("pg-sa-verified-w4")', $js, 'and to its box');
	pg_assert_false(strpos($js, 'Acme</script>') !== false, 'an address cannot close the script');
	pg_assert_contains('"fields":["company","address_1","address_2","city","zip_code","country","state"]', $js, 'the controls it fills');
	pg_assert_contains('textContent', $js, 'the picked address is written as text');
	pg_assert_false(strpos($js, 'innerHTML=a') !== false || strpos($js, 'innerHTML=t') !== false, 'never as markup');
}

function test_shipping_address_widget_verified_fill_names()
{
	$fields = pg_sa_verified_fill_fields();
	pg_assert_same(array(), array_values(array_diff($fields, pg_sa_address_fields())), 'every filled control is an address control of the widget');
	pg_assert_true(array_search('country', $fields) < array_search('state', $fields), 'the country is set before the state');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($js, "case 'shipping_address_and_arrival': {");
	$to = strpos($js, "case 'wishlist': {", (int)$from);
	pg_assert_true($from !== false && $to !== false, 'the starter tree is found');
	$starter = substr($js, (int)$from, (int)$to - (int)$from);
	foreach ($fields as $f) {
		pg_assert_true(strpos($starter, "'" . $f . "'") !== false, 'the starter has a control named ' . $f);
	}
}

function test_shipping_address_widget_verified_starter()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($js, "case 'shipping_address_and_arrival': {");
	$starter = substr($js, (int)$from, (int)strpos($js, "case 'wishlist': {", (int)$from) - (int)$from);
	pg_assert_contains("eo_visible_if: 'has_verified_addresses'", $starter, 'the help box is shown by the flag');
	pg_assert_contains("section: 'verified_addresses'", $starter, 'the starter places the server-drawn section');
	pg_assert_contains("_sectionOpts.push(['verified_addresses'", $js, 'the section can be bound in the designer');
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_shipping_address.php');
	pg_assert_contains("'verified_addresses' => ''", $php, 'the renderer fills the same section');
	pg_assert_contains("'has_verified_addresses' =>", $php, 'and sets the same flag');
}
