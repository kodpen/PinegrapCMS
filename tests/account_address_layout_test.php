<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the my account and update address book pages' own layout files
 * turned into their widgets' design elements: the address book converts
 * with nothing dropped, its address type under the widget's flag and its
 * "What is this?" link bound to the page the type row names; the my
 * account page's values become the widget's tokens under value conditions,
 * what the widget has not is dropped and reported.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');

// Every node of a tree, depth first, the root left out.
function _test_aal_nodes($nodes)
{
	$out = array();
	foreach ((array)$nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) $out = array_merge($out, _test_aal_nodes($n['children']));
	}
	return $out;
}

// The values of one binding over the tree, in order.
function _test_aal_bound($tree, $prop)
{
	$out = array();
	foreach (_test_aal_nodes($tree['children']) as $n) {
		if (isset($n['props']['_bindings'][$prop])) $out[] = $n['props']['_bindings'][$prop];
	}
	return $out;
}

// The control names of the tree's inputs, selects and buttons, in order.
function _test_aal_names($tree)
{
	$out = array();
	foreach (_test_aal_nodes($tree['children']) as $n) {
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if (($a['name'] ?? '') === 'name') $out[] = (string)$a['value'];
		}
	}
	return $out;
}

function _test_aal_site($site, $id)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/' . $site . '/layouts/' . $id . '.php');
}

function _test_aal_template($type)
{
	return pg_layout_strip_template_header((string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/' . $type . '.php'));
}

function _test_aal_pieces($r)
{
	return array_map(function ($d) { return $d['piece']; }, $r['dropped']);
}

function test_aal_types()
{
	pg_assert_true(in_array('my account', pg_layout_convert_types(), true), 'my account');
	pg_assert_true(in_array('update address book', pg_layout_convert_types(), true), 'update address book');
}

// The starting sites' address book page (976, both sites; its type row
// asks for the address type and names no help page): nothing dropped, the
// classic control names, the type radios under the widget's flag, no
// "What is this?" link.
function test_aal_address_book_starting_sites()
{
	$names = array('ship_to_name', 'salutation', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'state',
		'zip_code', 'country', 'address_type', 'address_type', 'phone_number');
	foreach (array('english_default', 'turkish_default') as $site) {
		$state = pg_layout_convert_state('update address book', array('address_type' => '1', 'address_type_page_id' => '0'));
		$r = pg_layout_convert('update address book', _test_aal_site($site, 976), array('state' => $state));
		$json = json_encode($r['tree']);
		pg_assert_true($r['ok'], $site . ': converts');
		pg_assert_same(array(), $r['dropped'], $site . ': nothing dropped');
		pg_assert_false((bool)preg_match('~pg-(vis|loop|section|row|raw)|<form|<\?~', $json), $site . ': no marker, form tag or PHP left');
		pg_assert_same($names, _test_aal_names($r['tree']), $site . ': the classic control names');
		pg_assert_same(array('has_address_type', 'has_address_type', 'has_address_type'), _test_aal_bound($r['tree'], 'eo_visible_if'), $site . ': the flag');
		pg_assert_same(array('__my_account_url'), _test_aal_bound($r['tree'], 'href'), $site . ': the bound links');
		pg_assert_false(strpos($json, 'What is this') !== false, $site . ': no help page, no link');
		pg_assert_false(strpos($json, 'loop_area') !== false, $site . ': no list');
	}
}

// A type row naming a help page: "What is this?" is a link bound to it.
function test_aal_address_book_help_link()
{
	$r = pg_layout_convert('update address book', _test_aal_site('english_default', 976), array('state' => array('address_type_page_id' => 12)));
	pg_assert_same(array(), $r['dropped']);
	$link = null;
	foreach (_test_aal_nodes($r['tree']['children']) as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $link = $n;
	}
	pg_assert_true($link !== null, 'the link is bound to a page');
	pg_assert_same(array('page_id' => 12, 'params' => array()), $link['props']['_bindParams']['href']);
	pg_assert_contains('What is this?', json_encode($link));
}

// The type row: the switch is the widget's setting, the help page the
// converter's.
function test_aal_address_book_seed_and_state()
{
	pg_assert_same(array('address_type' => true), pg_layout_widget_seed('update address book', array('address_type' => '1', 'address_type_page_id' => '7')));
	pg_assert_same(array('address_type' => false), pg_layout_widget_seed('update address book', array()));
	pg_assert_same(array('address_type_page_id' => 7), pg_layout_convert_state('update address book', array('address_type' => '1', 'address_type_page_id' => '7')));
	pg_assert_same(array('address_type_page_id' => 0), pg_layout_convert_state('update address book', array()));
}

// The stock template converts with nothing dropped.
function test_aal_address_book_template()
{
	$r = pg_layout_convert('update address book', _test_aal_template('update_address_book'), array('state' => array('address_type_page_id' => 3)));
	pg_assert_true($r['ok']);
	pg_assert_same(array(), $r['dropped']);
	pg_assert_same(array('__page_link', '__my_account_url'), _test_aal_bound($r['tree'], 'href'));
}

// The starting sites' my account page (971, both sites): what the widget
// has not is dropped and reported, so the run leaves it a System region
// and the Region panel lists it.
function test_aal_my_account_starting_sites()
{
	$want = array('$business_address_1', '$business_address_2', '$business_state or $business_zip_code', '$business_state', '$business_zip_code',
		'$business_fax', '$start_page_url', 'check_view_access(284)', 'check_view_access(134) or check_view_access(135)', 'check_view_access(104)',
		'$affiliate', "h(\$order['reorder_url'])", '$incomplete_orders', '($address_book) and ($recipients)');
	foreach (array('english_default', 'turkish_default') as $site) {
		$r = pg_layout_convert('my account', _test_aal_site($site, 971), array());
		$json = json_encode($r['tree']);
		pg_assert_true($r['ok'], $site . ': converts');
		pg_assert_same($want, _test_aal_pieces($r), $site . ': the dropped pieces');
		pg_assert_false((bool)preg_match('~pg-(vis|loop|section|row|raw)|<form|<\?|__pg_unlinked~', $json), $site . ': no marker, form tag or PHP left');
		pg_assert_same(array('__profile_url', '__email_preferences_url', '__change_password_url', '__logout_url', '__order_url'),
			_test_aal_bound($r['tree'], 'href'), $site . ': the bound links');
		pg_assert_same(array('first_name', '__member_job_title', '__member_company', '__member_business_city', '__member_business_country',
			'__member_business_phone', '__member_mobile_phone', '__member_home_phone', 'member_id', '__member_expiry_date',
			'__member_reward_points', '__member_time_zone', '__order_url'), array_values(array_unique(_test_aal_bound($r['tree'], 'show_if'))), $site . ': the value conditions');
		pg_assert_false(strpos($json, 'when_') !== false, $site . ': no flag of the map left');
		pg_assert_false(strpos($json, 'Reorder') !== false, $site . ': the reorder button goes');
	}
}

// The order history: the widget's account.orders list under has_orders,
// its row by the list's tokens; the view button only with an address.
function test_aal_my_account_orders()
{
	$r = pg_layout_convert('my account', _test_aal_site('english_default', 971), array());
	$loops = array_values(array_filter(_test_aal_nodes($r['tree']['children']), function ($n) { return $n['type'] === 'loop_area'; }));
	pg_assert_same(1, count($loops), 'one list');
	pg_assert_same('account.orders', $loops[0]['props']['source'] ?? '');
	pg_assert_same(array('__order_no', '__order_date', '__order_total'), _test_aal_bound($loops[0], 'text'));
	$view = null;
	foreach (_test_aal_nodes($loops[0]['children']) as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__order_url') $view = $n;
	}
	pg_assert_same(array('op' => 'neq', 'value' => '#'), $view['props']['_bindParams']['show_if']);
	pg_assert_same(array('has_orders', 'has_orders', 'has_orders'), _test_aal_bound($r['tree'], 'eo_visible_if'));
}

// A line of text with an "otherwise" branch: each branch its own span
// under its test (the expiry date, or "Not Set" without one). Two tokens
// of one line are joined by 'any'.
function test_aal_my_account_value_conditions()
{
	$r = pg_layout_convert('my account', _test_aal_site('english_default', 971), array());
	$none = $date = $name = null;
	foreach (_test_aal_nodes($r['tree']['children']) as $n) {
		$text = (string)($n['props']['text'] ?? '');
		if (strpos($text, 'Not Set') !== false) $none = $n;
		if (strpos($text, '^^__member_expiry_date^^') !== false) $date = $n;
		if (strpos($text, '^^first_name^^') !== false) $name = $n;
	}
	pg_assert_same(array('op' => 'empty'), $none['props']['_bindParams']['show_if']);
	pg_assert_same(array('op' => 'not_empty'), $date['props']['_bindParams']['show_if']);
	pg_assert_false(strpos($date['props']['text'], 'pg-vis') !== false, 'no marker in the text');
	pg_assert_same(array('first_name', 'last_name'), array($name['props']['_bindings']['show_if'], $name['props']['_bindings']['show_if_2']));
	pg_assert_same('any', $name['props']['_bindParams']['show_if']['join']);
}

// The stock template: the store credit block is the widget's two
// sections, the personal data request shown with its address.
function test_aal_my_account_template()
{
	$r = pg_layout_convert('my account', _test_aal_template('my_account'), array());
	pg_assert_true($r['ok']);
	pg_assert_same(array('!empty($google_account_notice)', '$start_page_url', '$business_address_1', '$business_address_2',
		'$business_state or $business_zip_code', '$business_state', '$business_zip_code', '$business_fax', '$affiliate',
		"h(\$order['reorder_url'])", '$incomplete_orders', '$address_book'), _test_aal_pieces($r));
	pg_assert_same(array('store_credit', 'store_credit_convert'), _test_aal_bound($r['tree'], 'section'));
	pg_assert_contains('__privacy_request_url', json_encode(_test_aal_bound($r['tree'], 'show_if')));
}
