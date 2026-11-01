<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address labels that follow the country: a Turkish address is
 * asked as İl (the state box) and İlçe (the city box), any other as City and
 * State / Province (pg_address_labels() and its helpers in
 * includes/fn/shipping.php); only default words swap, a designer's own words
 * stay. Also the delivery note's district, taken from the recipient first
 * (erp_address_from_ship_to()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/accounts.php');

function address_labels_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The words in Turkish, as lang() gives them on a Turkish site.
function address_labels_test_words()
{
	return array(
		'city' => array('tr' => 'İlçe', 'other' => 'Şehir', 'defaults' => array('City', 'District', 'Şehir', 'İlçe')),
		'state' => array('tr' => 'İl', 'other' => 'Eyalet / İl', 'defaults' => array('State / Province', 'Province', 'State', 'Eyalet / İl', 'İl')),
	);
}

// Turkey by code or name: District / Province; elsewhere City / State / Province.
function test_address_labels_by_country()
{
	$tr = array('city' => 'District', 'state' => 'Province');
	$other = array('city' => 'City', 'state' => 'State / Province');
	pg_assert_same($tr, pg_address_labels('TR'));
	pg_assert_same($tr, pg_address_labels('Türkiye'));
	pg_assert_same($tr, pg_address_labels('turkey'));
	pg_assert_same($other, pg_address_labels('DE'));
	pg_assert_same($other, pg_address_labels(''));
	pg_assert_same($other, pg_address_labels(null));
}

// A default word matches with its asterisk, colon, case and spacing ignored.
function test_address_labels_key()
{
	pg_assert_same('state/province', pg_address_label_key(' State / Province* '));
	pg_assert_same('city', pg_address_label_key("\n City*:\n"));
	pg_assert_same(pg_address_label_key('İlçe'), pg_address_label_key('İLÇE *'));
	pg_assert_same('', pg_address_label_key('*'));
}

// Only a default word swaps; the asterisk and spacing around it stay.
function test_address_labels_swap()
{
	$words = address_labels_test_words();
	pg_assert_same('İlçe*', pg_address_label_swap('City*', $words['city'], true));
	pg_assert_same('Şehir*', pg_address_label_swap('City*', $words['city'], false));
	pg_assert_same('Şehir', pg_address_label_swap('İlçe', $words['city'], false), 'a Turkish layout abroad');
	pg_assert_same(" İl\n", pg_address_label_swap(" State / Province\n", $words['state'], true));
	pg_assert_same('İl *', pg_address_label_swap('Eyalet / İl *', $words['state'], true));
	pg_assert_same(null, pg_address_label_swap('İlçe/Semt', $words['city'], true), 'a layout\'s own words');
	pg_assert_same(null, pg_address_label_swap('Town', $words['city'], false), 'a designer\'s own words');
	pg_assert_same(null, pg_address_label_swap('', $words['city'], true), 'no text');
}

// A field column of a designer tree: a label and one named control.
function address_labels_test_column($name, $text)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'label', 'text' => $text), 'children' => array()),
		array('type' => 'semantic', 'props' => array('tag' => 'input', '_attrs' => array(array('name' => 'name', 'value' => $name))), 'children' => array()),
	));
}

function address_labels_test_named($name)
{
	return function ($node) use ($name) {
		foreach ((array) ($node['props']['_attrs'] ?? array()) as $a) {
			if (($a['name'] ?? '') === 'name' && ($a['value'] ?? '') === $name) {
				return true;
			}
		}
		return false;
	};
}

function address_labels_test_attrs($node)
{
	$attrs = array();
	foreach ((array) ($node['props']['_attrs'] ?? array()) as $a) {
		$attrs[$a['name']] = $a['value'];
	}
	return $attrs;
}

// The render writes the country's words on default labels and stamps both
// texts for the script; a label a designer reworded is left alone.
function test_address_labels_tree_stamp()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		address_labels_test_column('city', 'City*'),
		address_labels_test_column('country', 'Country'),
		address_labels_test_column('state', 'State / Province'),
	));
	$stamped = $tree;
	pg_address_labels_tree_stamp($stamped, address_labels_test_named('city'), address_labels_test_named('state'), 'country', 'TR', array('TR', 'TUR'), address_labels_test_words());

	$city = $stamped['children'][0]['children'][0];
	$attrs = address_labels_test_attrs($city);
	pg_assert_same('İlçe*', $city['props']['text'], 'Turkish recipient: İlçe');
	pg_assert_same('country', $attrs['data-pg-addr-label']);
	pg_assert_same('TR,TUR', $attrs['data-pg-addr-codes']);
	pg_assert_same('İlçe*', $attrs['data-pg-addr-tr']);
	pg_assert_same('Şehir*', $attrs['data-pg-addr-other']);
	pg_assert_same('İl', $stamped['children'][2]['children'][0]['props']['text'], 'Turkish recipient: İl');
	pg_assert_same('Country', $stamped['children'][1]['children'][0]['props']['text'], 'other labels untouched');

	$abroad = $tree;
	pg_address_labels_tree_stamp($abroad, address_labels_test_named('city'), address_labels_test_named('state'), 'country', 'DE', array('TR'), address_labels_test_words());
	pg_assert_same('Şehir*', $abroad['children'][0]['children'][0]['props']['text'], 'abroad: City');
	pg_assert_same('Eyalet / İl', $abroad['children'][2]['children'][0]['props']['text'], 'abroad: State / Province');

	$own = $tree;
	$own['children'][0]['children'][0]['props']['text'] = 'Town / village';
	pg_address_labels_tree_stamp($own, address_labels_test_named('city'), address_labels_test_named('state'), 'country', 'TR', array('TR'), address_labels_test_words());
	pg_assert_same('Town / village', $own['children'][0]['children'][0]['props']['text'], 'own words kept');
	pg_assert_false(isset(address_labels_test_attrs($own['children'][0]['children'][0])['data-pg-addr-label']), 'own words not stamped');
}

// The script swaps a stamped label with its country control.
function test_address_labels_script()
{
	$source = address_labels_test_source('includes/fn/shipping.php');
	$start = strpos($source, "\nfunction pg_ship_to_district_script(");
	$js = (string) substr($source, (int) $start, 2500);
	pg_assert_contains('data-pg-addr-label', $js);
	pg_assert_contains('data-pg-addr-tr', $js);
	pg_assert_contains('data-pg-addr-other', $js);
}

// Every designer widget that asks for an address stamps its labels and
// prints the script; every classic page hands init_country() the words.
function test_address_labels_places()
{
	$eo = address_labels_test_source('includes/fn/widgets_express_order.php');
	pg_assert_same(2, substr_count($eo, 'pg_address_labels_tree_stamp($tree, $bound('), 'express order: shipping and billing in the tree');
	pg_assert_same(2, substr_count($eo, 'pg_address_labels_html($label, '), 'express order: the server-drawn city rows and billing state');
	pg_assert_contains("pg_address_labels_html(lang('State / Province'), 'state', 'shipping_' . \$rid . '_country'", $eo, 'express order: the recipient state');
	pg_assert_contains('pg_ship_to_district_script()', $eo);

	$widgets = array(
		'includes/fn/widgets_shipping_address.php' => "pg_sa_address_labels(\$tree, '', \$values['country']);",
		'includes/fn/widgets_billing_information.php' => "pg_sa_address_labels(\$tree, 'billing_', \$values['billing_country']);",
		'includes/fn/widgets_account.php' => "pg_sa_address_labels(\$tree, 'business_', \$values['business_country']);",
		'includes/fn/widgets_affiliate_sign_up.php' => "pg_sa_address_labels(\$tree, '', \$values['country']);",
	);
	foreach ($widgets as $file => $call) {
		$source = address_labels_test_source($file);
		pg_assert_contains($call, $source, $file . ' stamps its labels');
		pg_assert_contains('pg_ship_to_district_script()', $source, $file . ' prints the script');
	}
	pg_assert_same(2, substr_count(address_labels_test_source('includes/fn/widgets_account.php'), 'pg_sa_address_labels('), 'profile and address book');
	pg_assert_same(2, substr_count(address_labels_test_source('includes/fn/widgets_account.php'), '. pg_ship_to_district_script()'), 'profile and address book print the script');

	$pages = array(
		'get_express_order.php' => 2,
		'get_shipping_address_and_arrival.php' => 1,
		'get_billing_information.php' => 1,
		'get_update_address_book.php' => 1,
		'get_my_account_profile.php' => 1,
		'get_affiliate_sign_up_form_screen_content.php' => 1,
	);
	foreach ($pages as $file => $count) {
		pg_assert_same($count, substr_count(address_labels_test_source($file), 'pg_address_labels_init_props('), $file);
	}

	$js = address_labels_test_source('frontend.src.js');
	pg_assert_contains('address_labels: function(properties)', $js);
	$min = address_labels_test_source('frontend.min.js');
	pg_assert_true(strpos($min, 'address_labels:function(e)') !== false, 'the served script has it');
	pg_assert_true(strpos($min, 'p=e.address_labels?software.address_labels(e):null') !== false, 'the served init_country() calls it');
}

// init_country() gets the city label's id, the codes that stand for Turkey
// and the words, in the page's language.
function test_address_labels_init_props()
{
	$props = pg_address_labels_init_props('billing_city', array('TR'), address_labels_test_words());
	pg_assert_contains('city_id: "billing_city"', $props);
	pg_assert_contains('"codes":["TR"]', $props);
	pg_assert_contains('"tr":"İlçe"', $props);
	pg_assert_same(0, strpos($props, ', '), 'appended after the last property');
}

// The delivery note takes the recipient's district first, else the city box
// of a Turkish address.
function test_address_labels_waybill_district()
{
	$place = erp_address_from_ship_to('Kadıköy', 'İstanbul', 'TR', 'Moda');
	pg_assert_same(array('city' => 'İstanbul', 'district' => 'Moda', 'state' => ''), $place);
	$place = erp_address_from_ship_to('Kadıköy', 'İstanbul', 'TR', '');
	pg_assert_same('Kadıköy', $place['district'], 'older order: the city box');
	$place = erp_address_from_ship_to('Berlin', 'BE', 'DE', ' Mitte ');
	pg_assert_same(array('city' => 'Berlin', 'district' => 'Mitte', 'state' => 'BE'), $place);
	pg_assert_contains('erp_address_from_ship_to(', address_labels_test_source('includes/erp/waybills.php'));
}
