<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the fees and field rules the design templates give their forms:
 * the hidden fee products of a template's catalog ('fees',
 * _pg_tpl_fee_product()) and the {{product:<key>}} placeholder that names
 * one in a form's payment setting (_pg_tpl_fill()); the Events
 * registration's optional fee (priced 0), the Cafe catering deposit and the
 * Courses enrolment fee; the Cafe delivery address and the Clinic policy
 * number shown only for the answer they belong to.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A template as the loader reads it, read once per run.
function _test_tpl_fees_load($id)
{
	static $out = array();
	if (!isset($out[$id])) {
		$out[$id] = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
	}
	return $out[$id];
}

// The form settings of a template's page, or null.
function _test_tpl_fees_form($tpl, $key)
{
	foreach ($tpl['pages'] as $p) {
		if ($p['key'] === $key) return isset($p['form']) ? $p['form'] : null;
	}
	return null;
}

// The node of a tree whose props.id is $id, or null.
function _test_tpl_fees_node($node, $id)
{
	if (!is_array($node)) return null;
	if (isset($node['props']['id']) && $node['props']['id'] === $id) return $node;
	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		$found = _test_tpl_fees_node($child, $id);
		if ($found) return $found;
	}
	return null;
}

function test_tpl_fill_product_placeholder()
{
	$vars = array('products' => array('deposit' => 42));
	pg_assert_same(42, _pg_tpl_fill('{{product:deposit}}', $vars), 'a value that is only the placeholder becomes the id as a number');
	pg_assert_same(0, _pg_tpl_fill('{{product:missing}}', $vars), 'a product the site has not got is 0');
	pg_assert_same(0, _pg_tpl_fill('{{product:deposit}}', array()), 'no products (no shop) is 0');
	pg_assert_same('id 42', _pg_tpl_fill('id {{product:deposit}}', $vars), 'inside a text the id as text');
	pg_assert_same(
		array('product_id' => 42, 'offline' => 1),
		_pg_tpl_fill(array('product_id' => '{{product:deposit}}', 'offline' => 1), $vars),
		'a payment setting is filled');
}

function test_tpl_fee_product_row()
{
	$fee = array('sku' => 'FEE-X', 'title' => 'Deposit', 'description' => 'Paid <with> a form.', 'price' => array('USD' => 50, 'TRY' => 1499.995));
	$row = _pg_tpl_fee_product($fee, 'TRY', 7);
	pg_assert_same('FEE-X', $row['name'], 'the SKU is the name');
	pg_assert_same(150000, $row['price'], 'minor units of the base currency, rounded');
	pg_assert_same('0', $row['shippable'], 'not shipped');
	pg_assert_same('0', $row['taxable'], 'not taxed: the order adds nothing on top');
	pg_assert_same('1', $row['enabled'], 'enabled, so an order can carry it');
	pg_assert_same('Deposit', $row['short_description'], 'the title');
	pg_assert_same('<p>Paid &lt;with&gt; a form.</p>', $row['full_description'], 'the description escaped');
	pg_assert_same(7, $row['user'], 'the user');
	pg_assert_same(5000, _pg_tpl_fee_product($fee, 'EUR', 1)['price'], 'a currency not listed takes the USD figure');
	pg_assert_same(0, _pg_tpl_fee_product(array('sku' => 'F', 'price' => array('USD' => -3)), 'USD', 1)['price'], 'never below 0');
	pg_assert_same('F', _pg_tpl_fee_product(array('sku' => ' F '), 'USD', 1)['short_description'], 'no title: the SKU');
}

// Each template's form names a fee of its own catalog, and the setting it
// fills is a fee once the product exists.
function test_tpl_forms_name_their_fee_products()
{
	$cases = array(
		array('pinegrap-events', 'registration', 'registration_fee'),
		array('pinegrap-cafe', 'corporate', 'catering_deposit'),
		array('pinegrap-courses', 'enrolment', 'enrolment_fee'),
	);
	foreach ($cases as $c) {
		list($id, $page, $key) = $c;
		$tpl  = _test_tpl_fees_load($id);
		$form = _test_tpl_fees_form($tpl, $page);
		pg_assert_true(is_array($form) && isset($form['payment']), "$id: the $page form has a payment setting");
		pg_assert_same('{{product:' . $key . '}}', $form['payment']['product_id'], "$id: it names its fee product");
		$fees = isset($tpl['catalog']['fees']) ? $tpl['catalog']['fees'] : array();
		pg_assert_true(isset($fees[$key]['sku']) && $fees[$key]['sku'] !== '', "$id: the catalog carries the fee $key");
		pg_assert_true(empty($fees[$key]['shippable']) && !isset($fees[$key]['group']), "$id: the fee is no listed product");
		$clean = pg_form_fee_settings_clean(_pg_tpl_fill($form['payment'], array('products' => array($key => 9))));
		pg_assert_same(9, $clean['product_id'], "$id: filled, the setting names the product");
		pg_assert_same('product', $clean['amount_source'], "$id: the amount is the product's price");
		pg_assert_same(1, $clean['offline'], "$id: bank transfer where the store allows it");
		$none = pg_form_fee_settings_clean(_pg_tpl_fill($form['payment'], array()));
		pg_assert_same('', pg_form_fee_settings_encode($none), "$id: without the shop no fee is stored");
	}
}

function test_tpl_fee_prices()
{
	$events = _test_tpl_fees_load('pinegrap-events');
	foreach ($events['catalog']['fees']['registration_fee']['price'] as $cur => $price) {
		pg_assert_same(0, $price, "events: the registration fee is free ($cur) until priced");
	}
	pg_assert_same(null, pg_form_fee(array('product_id' => 9, 'amount_source' => 'product', 'product_price' => 0), array()),
		'a product priced 0 takes no payment');
	foreach (array(array('pinegrap-cafe', 'catering_deposit'), array('pinegrap-courses', 'enrolment_fee')) as $c) {
		$tpl = _test_tpl_fees_load($c[0]);
		$prices = $tpl['catalog']['fees'][$c[1]]['price'];
		foreach (array('USD', 'EUR', 'GBP', 'TRY') as $cur) {
			pg_assert_true(isset($prices[$cur]) && $prices[$cur] > 0, "{$c[0]}: a price in $cur");
		}
	}
}

function test_tpl_cafe_address_shown_for_delivery_only()
{
	$tpl  = _test_tpl_fees_load('pinegrap-cafe');
	$node = _test_tpl_fees_node($tpl['widgets']['quote_form']['tree'], 'cf-quote-address');
	pg_assert_true(is_array($node), 'the address field is there');
	$rules = pg_cf_rules_clean($node['props']['_cf']['rules'], 'text');
	pg_assert_same(array('field' => 'delivery', 'answers' => array(lang('Delivered to us'))), $rules['shown_when'], 'shown only for delivery');
	pg_assert_same($rules['shown_when'], $rules['required_when'], 'and required then');
}

function test_tpl_clinic_policy_shown_for_private_insurance()
{
	$tpl  = _test_tpl_fees_load('pinegrap-clinic');
	$tree = $tpl['widgets']['appointment_form']['tree'];
	$node = _test_tpl_fees_node($tree, 'cl-appt-policy');
	pg_assert_true(is_array($node), 'the policy field is there');
	$rules = pg_cf_rules_clean($node['props']['_cf']['rules'], 'text');
	pg_assert_same(array('field' => 'insurance', 'answers' => array(lang('Private insurance'))), $rules['shown_when'], 'shown for private insurance');
	pg_assert_true(!isset($rules['required_when']), 'never required');
	pg_assert_true(is_array(_test_tpl_fees_node($tree, 'cl-appt-insurance')), 'the field it follows is on the form');
}

// Source reading: opening a template hands the fee products to the fill.
function test_tpl_prepare_passes_fee_products()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_true(strpos($src, "'products'  => \$catalog['products'],") !== false, 'the fill gets the products');
	pg_assert_true(strpos($src, "'products' => array());") !== false, 'the catalog answers products even when empty');
}
