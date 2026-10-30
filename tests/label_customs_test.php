<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the customs declaration of a label (includes/fn/product_customs.php):
 * which routes need one, the shop-defaults block of a label driver's
 * settings and how each order line is declared from the product's values
 * and the shop defaults, carrier-neutral so every label driver uses it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function label_customs_test_goods($changes = array())
{
	return array_merge(array(
		'name'           => 'Mug',
		'quantity'       => 2,
		'price'          => 1250,
		'weight'         => 0.4,
		'hs_code'        => '',
		'origin_country' => '',
	), $changes);
}

// Within one country or within the EU customs union no declaration is made;
// out of it, to or from the US or Great Britain, one is.
function test_label_customs_needed()
{
	pg_assert_false(pg_label_customs_needed('DE', 'de'), 'domestic');
	pg_assert_false(pg_label_customs_needed('US', 'US'), 'domestic US');
	pg_assert_false(pg_label_customs_needed('DE', 'FR'), 'within the EU');
	pg_assert_true(pg_label_customs_needed('DE', 'GB'), 'EU to Great Britain');
	pg_assert_true(pg_label_customs_needed('US', 'CA'), 'from the US');
	pg_assert_true(pg_label_customs_needed('FR', 'US'), 'to the US');
	pg_assert_true(pg_label_customs_needed('TR', 'DE'), 'Turkey to the EU');
	pg_assert_true(pg_label_customs_needed('CH', 'DE'), 'Switzerland to the EU');
	pg_assert_false(pg_label_customs_needed('', 'DE'), 'no country: the driver refuses it itself');
}

// The product's values first, the shop defaults second; prices stay whole
// minor units, rounded rather than cut.
function test_label_customs_lines_product_values_first()
{
	$defaults = array('hs_code' => '9999.99', 'origin_country' => 'DE');
	$built = pg_label_customs_lines(array(
		label_customs_test_goods(array('hs_code' => ' 6912.00 ', 'origin_country' => 'cn', 'price' => '1249.6')),
		label_customs_test_goods(array('name' => 'Plate', 'quantity' => 1, 'price' => 899.5)),
	), $defaults);

	pg_assert_true($built['ok']);
	pg_assert_same('', $built['error']);
	pg_assert_same(array(
		array('name' => 'Mug', 'quantity' => 2, 'unit_price' => 1250, 'unit_weight' => 0.4, 'hs_code' => '6912.00', 'origin_country' => 'CN'),
		array('name' => 'Plate', 'quantity' => 1, 'unit_price' => 900, 'unit_weight' => 0.4, 'hs_code' => '9999.99', 'origin_country' => 'DE'),
	), $built['lines']);
}

// A line with no HS code anywhere refuses the declaration and names the
// product; no code is guessed. The same for the country of origin.
function test_label_customs_lines_missing_values_refuse()
{
	$built = pg_label_customs_lines(array(
		label_customs_test_goods(array('hs_code' => '6912', 'origin_country' => 'TR')),
		label_customs_test_goods(array('name' => 'Teekanne', 'origin_country' => 'TR')),
	), array('hs_code' => '', 'origin_country' => ''));

	pg_assert_false($built['ok']);
	pg_assert_same(array(), $built['lines']);
	pg_assert_contains('Teekanne', $built['error']);
	pg_assert_contains('HS code', $built['error']);

	$built = pg_label_customs_lines(array(label_customs_test_goods(array('hs_code' => '6912'))), array('hs_code' => '', 'origin_country' => ''));

	pg_assert_false($built['ok']);
	pg_assert_contains('country of origin', $built['error']);

	// A stored value the screen would refuse counts as empty: the default
	// takes its place.
	$built = pg_label_customs_lines(array(label_customs_test_goods(array('hs_code' => '69 12', 'origin_country' => 'XX'))), array('hs_code' => '6912', 'origin_country' => 'IT'));

	pg_assert_true($built['ok']);
	pg_assert_same('6912', $built['lines'][0]['hs_code']);
	pg_assert_same('IT', $built['lines'][0]['origin_country']);

	$built = pg_label_customs_lines(array(), array('hs_code' => '6912', 'origin_country' => 'IT'));

	pg_assert_false($built['ok'], 'nothing to declare');
}

// A multibyte name is kept whole and cut by characters, not bytes; spaces
// inside are folded.
function test_label_customs_lines_multibyte_names()
{
	$long = str_repeat('Çay bardağı ', 12);
	$built = pg_label_customs_lines(array(
		label_customs_test_goods(array('name' => "  Çay   bardağı\tşeffaf ", 'hs_code' => '7013.37', 'origin_country' => 'TR')),
		label_customs_test_goods(array('name' => $long, 'hs_code' => '7013.37', 'origin_country' => 'TR')),
	), array());

	pg_assert_true($built['ok']);
	pg_assert_same('Çay bardağı şeffaf', $built['lines'][0]['name']);
	pg_assert_same(100, mb_strlen($built['lines'][1]['name'], 'UTF-8'));
	pg_assert_true(mb_check_encoding($built['lines'][1]['name'], 'UTF-8'), 'no character cut in half');
}

// A line with no weight shares what the parcel weighs beyond the lines
// that have one, at least 10 g a unit.
function test_label_customs_lines_weight()
{
	$built = pg_label_customs_lines(array(
		label_customs_test_goods(array('weight' => 0, 'quantity' => 4, 'hs_code' => '6912', 'origin_country' => 'TR')),
	), array(), 1.0);

	pg_assert_same(0.25, $built['lines'][0]['unit_weight']);

	$built = pg_label_customs_lines(array(
		label_customs_test_goods(array('weight' => 0.5, 'quantity' => 2, 'hs_code' => '6912', 'origin_country' => 'TR')),
		label_customs_test_goods(array('weight' => 0, 'quantity' => 1, 'hs_code' => '6912', 'origin_country' => 'TR')),
	), array(), 1.0);

	pg_assert_same(0.5, $built['lines'][0]['unit_weight']);
	pg_assert_same(0.01, $built['lines'][1]['unit_weight']);
}

// The block counts as in use once a shop default or a product's value is
// filled; until then a running site sends what it sent before.
function test_label_customs_in_use()
{
	$goods = array(label_customs_test_goods());
	$block = array('customs_contents' => 'merchandise', 'customs_incoterm' => 'DDU', 'customs_non_delivery' => 'return', 'customs_hs_code' => '', 'customs_origin_country' => '', 'customs_signer' => '');

	pg_assert_false(pg_label_customs_in_use($goods, $block));
	pg_assert_true(pg_label_customs_in_use($goods, array_merge($block, array('customs_hs_code' => '6912'))));
	pg_assert_true(pg_label_customs_in_use($goods, array_merge($block, array('customs_signer' => 'Jane Doe'))));
	pg_assert_true(pg_label_customs_in_use(array(label_customs_test_goods(array('origin_country' => 'TR'))), $block));
}

// The shop-defaults block: its fields, defaults, and the C1 parsers'
// refusals as marks of the settings form.
function test_label_customs_settings_fields()
{
	$fields = array();

	foreach (pg_payment_fields_normalize(pg_label_customs_fields()) as $field) {
		$fields[$field['name']] = $field['type'] . ':' . $field['default'];
	}

	pg_assert_same(array(
		'customs_contents'       => 'select:merchandise',
		'customs_incoterm'       => 'select:DDU',
		'customs_non_delivery'   => 'select:return',
		'customs_hs_code'        => 'text:',
		'customs_origin_country' => 'select:',
		'customs_signer'         => 'text:',
	), $fields);

	pg_assert_same(array(), pg_label_customs_settings_problems(array('customs_hs_code' => '6109.10', 'customs_origin_country' => 'TR')));
	pg_assert_same(array(), pg_label_customs_settings_problems(array()));

	$problems = pg_label_customs_settings_problems(array('customs_hs_code' => '61O9', 'customs_origin_country' => 'TR'));
	pg_assert_same(array('customs_hs_code'), array_keys($problems));

	$decision = pg_label_settings_decide('shippo', pg_label_fields('shippo'), array(
		'label_shippo__present'         => '1',
		'label_shippo__customs_hs_code' => 'abc',
	), array('values' => array(), 'secrets' => array(), 'exists' => false, 'enabled' => false));

	pg_assert_true(isset($decision['marks']['label_shippo__customs_hs_code']), 'the form marks the HS code');
}
