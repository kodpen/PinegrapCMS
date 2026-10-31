<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Delete confirmations on Edit State, Edit Shipping Method, Edit Zone
 * and Edit Product Attribute name the records the delete removes or
 * unlinks, with their counts, and keep the plain sentence when nothing
 * goes with the record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/state_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/zone_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/product_attribute_form.php');

/**
 * The English keys of a list of lang() arguments.
 *
 * @param array $sentences
 * @return array
 */
function commerce_delete_confirm_keys($sentences)
{
	$keys = array();

	foreach ($sentences as $sentence) {
		$keys[] = $sentence['string'];
	}

	return $keys;
}

function test_commerce_delete_confirm_state()
{
	pg_assert_same(
		array('WARNING: This {var:1} will be permanently deleted.'),
		commerce_delete_confirm_keys(pg_state_form_delete_confirm(0)),
		'no address keeps the plain sentence');

	$one = pg_state_form_delete_confirm(1);
	pg_assert_same(array('WARNING: This state and its {var:1} verified shipping address{suffix:1} will be permanently deleted.'), commerce_delete_confirm_keys($one), 'addresses are named');
	pg_assert_same(array(1), $one[0]['vars'], 'the count');
	pg_assert_same(array(''), $one[0]['suffix'], 'one address reads singular');

	$many = pg_state_form_delete_confirm(12);
	pg_assert_same(array('es'), $many[0]['suffix'], 'addresses take -es');
}

function test_commerce_delete_confirm_shipping_method()
{
	pg_assert_same(
		array('WARNING: This {var:1} will be permanently deleted.'),
		commerce_delete_confirm_keys(pg_shipping_method_form_delete_confirm(0, 0)),
		'nothing linked keeps the plain sentence');

	pg_assert_same(
		array('WARNING: This shipping method and its {var:1} ship date adjustment{suffix:1} will be permanently deleted.'),
		commerce_delete_confirm_keys(pg_shipping_method_form_delete_confirm(3, 0)),
		'adjustments only');

	pg_assert_same(
		array('WARNING: This shipping method will be permanently deleted, and {var:1} offer action{suffix:1} will no longer discount shipping on it.'),
		commerce_delete_confirm_keys(pg_shipping_method_form_delete_confirm(0, 1)),
		'offer actions only');

	$both = pg_shipping_method_form_delete_confirm(1, 2);
	pg_assert_same(
		array('WARNING: This shipping method and its {var:1} ship date adjustment{suffix:1} will be permanently deleted, and {var:2} offer action{suffix:2} will no longer discount shipping on it.'),
		commerce_delete_confirm_keys($both),
		'both');
	pg_assert_same(array(1, 2), $both[0]['vars'], 'both counts in order');
	pg_assert_same(array('', 's'), $both[0]['suffix'], 'each count picks its own ending');
}

function test_commerce_delete_confirm_zone()
{
	pg_assert_same(
		array('WARNING: This {var:1} will be permanently deleted.'),
		commerce_delete_confirm_keys(pg_zone_form_delete_confirm(0, 0, 0, 0)),
		'nothing linked keeps the plain sentence');

	pg_assert_same(
		array('WARNING: This zone will be permanently deleted and removed from {var:1} product{suffix:1}.'),
		commerce_delete_confirm_keys(pg_zone_form_delete_confirm(4, 0, 0, 0)),
		'products only, none left without a zone');

	pg_assert_same(
		array('WARNING: This zone will be permanently deleted and removed from {var:1} shipping method{suffix:1}.'),
		commerce_delete_confirm_keys(pg_zone_form_delete_confirm(0, 2, 0, 0)),
		'methods only');

	$all = pg_zone_form_delete_confirm(40, 3, 40, 1);
	pg_assert_same(
		array(
			'WARNING: This zone will be permanently deleted and removed from {var:1} product{suffix:1} and {var:2} shipping method{suffix:2}.',
			'{var:1} product{suffix:1} will have no other zone and cannot be shipped, and {var:2} shipping method{suffix:2} will have no other zone and will never be offered.',
		),
		commerce_delete_confirm_keys($all),
		'both linked and both left without a zone');
	pg_assert_same(array(40, 3), $all[0]['vars'], 'linked counts');
	pg_assert_same(array(40, 1), $all[1]['vars'], 'stranded counts');
	pg_assert_same(array('s', ''), $all[1]['suffix'], 'stranded endings');

	pg_assert_same(
		'{var:1} product{suffix:1} will have no other zone and cannot be shipped.',
		pg_zone_form_delete_confirm(5, 0, 2, 0)[1]['string'],
		'only products stranded');
	pg_assert_same(
		'{var:1} shipping method{suffix:1} will have no other zone and will never be offered.',
		pg_zone_form_delete_confirm(0, 5, 0, 5)[1]['string'],
		'only methods stranded');
}

function test_commerce_delete_confirm_product_attribute()
{
	pg_assert_same(
		array('WARNING: This {var:1} will be permanently deleted.'),
		commerce_delete_confirm_keys(pg_product_attribute_form_delete_confirm(0, 0)),
		'unused keeps the plain sentence');

	pg_assert_same(
		array('WARNING: This product attribute and its options will be permanently deleted and removed from {var:1} product{suffix:1}.'),
		commerce_delete_confirm_keys(pg_product_attribute_form_delete_confirm(2, 0)),
		'products only');

	pg_assert_same(
		array('WARNING: This product attribute and its options will be permanently deleted and removed from {var:1} product group{suffix:1}.'),
		commerce_delete_confirm_keys(pg_product_attribute_form_delete_confirm(0, 1)),
		'groups only');

	$both = pg_product_attribute_form_delete_confirm(1, 6);
	pg_assert_same(
		array('WARNING: This product attribute and its options will be permanently deleted and removed from {var:1} product{suffix:1} and {var:2} product group{suffix:2}.'),
		commerce_delete_confirm_keys($both),
		'both');
	pg_assert_same(array('', 's'), $both[0]['suffix'], 'endings');
}

function test_commerce_delete_confirm_screens_use_the_helpers()
{
	$pins = array(
		'edit_state.php' => 'pg_state_form_delete_confirm(pg_state_form_delete_counts(',
		'edit_shipping_method.php' => 'pg_shipping_method_form_delete_confirm(...pg_shipping_method_form_delete_counts(',
		'edit_zone.php' => 'pg_zone_form_delete_confirm(...pg_zone_form_delete_counts(',
		'edit_product_attribute.php' => 'pg_product_attribute_form_delete_confirm(...pg_product_attribute_form_delete_counts(',
	);

	foreach ($pins as $file => $pin) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains($pin, $source, $file . ' builds its confirmation from the counts');
		pg_assert_contains("data-confirm-content=\"' . h(implode(' ', array_map('lang', ", $source, $file . ' prints the translated sentences escaped');
		pg_assert_true(strpos($source, "'This {var:1} will be permanently deleted.'") === false, $file . ' no longer prints the plain sentence itself');
	}
}

function test_commerce_delete_confirm_turkish_values()
{
	$map = pg_test_language_map('tr');
	$sentences = array_merge(
		pg_state_form_delete_confirm(2),
		pg_shipping_method_form_delete_confirm(2, 0),
		pg_shipping_method_form_delete_confirm(0, 2),
		pg_shipping_method_form_delete_confirm(2, 2),
		pg_zone_form_delete_confirm(2, 0, 0, 0),
		pg_zone_form_delete_confirm(0, 2, 0, 0),
		pg_zone_form_delete_confirm(2, 2, 2, 2),
		pg_zone_form_delete_confirm(2, 0, 2, 0),
		pg_zone_form_delete_confirm(0, 2, 0, 2),
		pg_product_attribute_form_delete_confirm(2, 0),
		pg_product_attribute_form_delete_confirm(0, 2),
		pg_product_attribute_form_delete_confirm(2, 2)
	);

	foreach ($sentences as $sentence) {
		$key = $sentence['string'];
		pg_assert_true(isset($map[$key]), 'Turkish value for: ' . $key);

		if (!isset($map[$key])) {
			continue;
		}

		foreach (array_keys($sentence['vars']) as $index) {
			pg_assert_contains('{var:' . ($index + 1) . '}', $map[$key], 'Turkish keeps {var:' . ($index + 1) . '}: ' . $key);
		}

		pg_assert_true(strpos($map[$key], '{suffix') === false, 'Turkish drops the English ending: ' . $key);
	}
}
