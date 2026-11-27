<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests a product's VAT rate class (products.tax_class): the EU's reduced
 * rates (pg_eu_vat_rates()), the rate a line of each class is taxed at
 * (pg_tax_class_line_rate()), the member state whose list taxes an address
 * (pg_tax_buyer_list_country()), the product form's field and the change
 * notice when the reduced rates first arrive.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each member state's lowest nationwide reduced rate on 2026-11-27, as the
// Commission's TEDB answered for that day; Denmark has none.
function tax_class_test_reduced()
{
	return array(
		'AT' => 10.0, 'BE' => 6.0, 'BG' => 9.0, 'CY' => 5.0, 'CZ' => 12.0,
		'DE' => 7.0, 'EE' => 9.0, 'ES' => 10.0, 'FI' => 10.0, 'FR' => 5.5,
		'GR' => 6.0, 'HR' => 5.0, 'HU' => 5.0, 'IE' => 9.0, 'IT' => 5.0,
		'LT' => 5.0, 'LU' => 8.0, 'LV' => 5.0, 'MT' => 5.0, 'NL' => 9.0,
		'PL' => 5.0, 'PT' => 6.0, 'RO' => 11.0, 'SE' => 6.0, 'SI' => 5.0,
		'SK' => 5.0,
	);
}

function test_tax_class_reduced_rate_every_member_state()
{
	$reduced = tax_class_test_reduced();

	pg_assert_same(26, count($reduced), 'every member state but Denmark');

	foreach ($reduced as $country => $rate) {
		pg_assert_same($rate, pg_eu_vat_rate($country, 'reduced', '2026-11-27'), $country . '\'s reduced rate');
		pg_assert_true($rate < pg_eu_vat_rate($country, 'standard', '2026-11-27'), $country . ': below its standard rate');
	}

	pg_assert_false(pg_eu_vat_rate('DK', 'reduced', '2026-11-27'), 'Denmark has no reduced rate');
	pg_assert_same(6.0, pg_eu_vat_rate('EL', 'reduced', '2026-11-27'), 'EL is Greece');
	pg_assert_same(11.0, pg_eu_vat_rate('RO', 'reduced', '2025-08-01'), 'Romania from August 2025');
	pg_assert_false(pg_eu_vat_rate('RO', 'reduced', '2025-07-31'), 'no reduced row before it');
}

// The member state whose list taxes an address: only in no zone, with the
// switch on and in the list.
function test_tax_class_list_country()
{
	$day = '2026-11-27';

	pg_assert_same('DE', pg_tax_buyer_list_country(false, 'de', true, $day), 'Germany in no zone');
	pg_assert_same('GR', pg_tax_buyer_list_country(false, 'EL', true, $day), 'EL is Greece');
	pg_assert_same('', pg_tax_buyer_list_country(false, 'DE', false, $day), 'the switch off');
	pg_assert_same('', pg_tax_buyer_list_country('19.000', 'DE', true, $day), 'a tax zone');
	pg_assert_same('', pg_tax_buyer_list_country('0.000', 'DE', true, $day), 'a zone of 0');
	pg_assert_same('', pg_tax_buyer_list_country(false, 'TR', true, $day), 'outside the EU');
	pg_assert_same('', pg_tax_buyer_list_country(false, '', true, $day), 'no country');
}

function test_tax_class_line_rate_from_the_list()
{
	$day = '2026-11-27';
	$de = pg_tax_buyer_zone_rate(false, 'DE', true, $day);
	$dk = pg_tax_buyer_zone_rate(false, 'DK', true, $day);

	pg_assert_same(19.0, pg_tax_class_line_rate(true, 1, null, $de, 'DE', 'standard', $day), 'standard');
	pg_assert_same(7.0, pg_tax_class_line_rate(true, 1, null, $de, 'DE', 'reduced', $day), 'reduced');
	pg_assert_same(0.0, pg_tax_class_line_rate(true, 1, null, $de, 'DE', 'zero', $day), 'zero');
	pg_assert_same(19.0, pg_tax_class_line_rate(true, 1, '', $de, 'DE', 'unknown', $day), 'an unknown class is standard');
	pg_assert_same(25.0, pg_tax_class_line_rate(true, 1, null, $dk, 'DK', 'reduced', $day), 'no reduced rate: the standard one');

	// The product's own rate wins, its 0 too; untaxable and Tax off stay 0.
	pg_assert_same(10.0, pg_tax_class_line_rate(true, 1, '10.000', $de, 'DE', 'reduced', $day), 'its own rate over reduced');
	pg_assert_same(10.0, pg_tax_class_line_rate(true, 1, '10.000', $de, 'DE', 'zero', $day), 'its own rate over zero');
	pg_assert_same(0.0, pg_tax_class_line_rate(true, 1, '0.000', $de, 'DE', 'standard', $day), 'its own 0');
	pg_assert_same(0.0, pg_tax_class_line_rate(true, 0, null, $de, 'DE', 'reduced', $day), 'not taxable');
	pg_assert_same(0.0, pg_tax_class_line_rate(false, 1, null, $de, 'DE', 'reduced', $day), 'Tax off');
}

// Where a tax zone sets the rate, or the switch is off, the class changes
// nothing: the line keeps today's rate.
function test_tax_class_keeps_zones_and_today()
{
	$zones = array(false, '20.000', '0.000', 0, 20.0, null);
	$products = array(array(1, null), array(1, '10.000'), array(1, '0.000'), array(0, null));

	foreach ($zones as $zone) {
		foreach (array('standard', 'reduced', 'zero') as $class) {
			foreach (array(true, false) as $tax_on) {
				foreach ($products as $product) {
					pg_assert_same(
						pg_order_line_tax_rate($tax_on, $product[0], $product[1], $zone),
						pg_tax_class_line_rate($tax_on, $product[0], $product[1], $zone, '', $class, '2026-11-27'),
						'unchanged: ' . var_export($zone, true) . ' ' . $class . ' ' . var_export($product, true)
					);
				}
			}
		}
	}
}

function test_tax_class_parse_and_options()
{
	pg_assert_same('reduced', pg_product_tax_class_parse(' Reduced '));
	pg_assert_same('zero', pg_product_tax_class_parse('zero'));
	pg_assert_same('standard', pg_product_tax_class_parse('standard'));
	pg_assert_same('standard', pg_product_tax_class_parse("zero' OR '1"), 'anything else is standard');
	pg_assert_same('standard', pg_product_tax_class_parse(null));
	pg_assert_same(array('standard', 'reduced', 'zero'), array_keys(pg_product_tax_classes()));

	$options = pg_product_tax_class_options('reduced');
	pg_assert_contains('<option value="reduced" selected>', $options, 'the stored class selected');
	pg_assert_same(1, substr_count($options, ' selected'), 'one selected');
	pg_assert_false(pg_product_tax_class_drawn(), 'not drawn without the switch');
}

// A product copy keeps its class, cleaned to what the column holds.
function test_tax_class_product_copy()
{
	pg_assert_same(array('columns' => 'tax_class,', 'values' => "'reduced',"), pg_product_copy_extra_sql(array('tax_class' => 'reduced'), false));
	pg_assert_same("'standard',", pg_product_copy_extra_sql(array('tax_class' => "x' OR '1"), false)['values']);
}

// The update that first brings the reduced rates tells nobody of 26 "new"
// rates; a later change of a reduced rate is told, marked as reduced.
function test_tax_class_notice()
{
	$seen = array(
		array('DE', 'standard', 19.0, '2016-01-01'),
		array('EE', 'standard', 24.0, '2025-07-01'),
	);
	$rows = $seen;
	$rows[] = array('DE', 'reduced', 7.0, '2023-07-01');
	$rows[] = array('EE', 'reduced', 9.0, '2025-01-01');

	pg_assert_same(array(), pg_eu_vat_rate_changes($seen, $rows), 'a kind the earlier list did not hold');

	$next = $rows;
	$next[] = array('EE', 'reduced', 13.0, '2027-01-01');
	$changes = pg_eu_vat_rate_changes($rows, $next);
	pg_assert_same(array(
		array('country' => 'EE', 'kind' => 'reduced', 'old' => 9.0, 'new' => 13.0, 'from' => '2027-01-01'),
	), $changes, 'a reduced rate changed');

	$changes[] = array('country' => 'DE', 'kind' => 'standard', 'old' => 19.0, 'new' => 20.0, 'from' => '2027-01-01');
	$title = pg_eu_vat_rate_notice_title($changes);
	pg_assert_same('EE reduced 9>13 2027-01-01, DE 19>20 2027-01-01', $title, 'the kind beside the country');

	$parsed = pg_eu_vat_rate_notice_parse($title);
	pg_assert_same('reduced', $parsed[0]['kind'], 'read back as reduced');
	pg_assert_same(13.0, $parsed[0]['new']);
	pg_assert_same('standard', $parsed[1]['kind'], 'an entry without a kind is standard');
	pg_assert_same('standard', pg_eu_vat_rate_notice_parse('EE 22>24 2025-07-01')[0]['kind'], 'a notice stored before');
}

// The checkout reads the class only where the upgrade has added it, and the
// product form draws and saves it only while the switch is on.
function test_tax_class_wiring()
{
	$root = dirname(__DIR__) . '/pinegrap';
	$ecommerce = (string) file_get_contents($root . '/includes/fn/ecommerce.php');
	$builder = (string) file_get_contents($root . '/product_builder.php');
	$migration = (string) file_get_contents($root . '/includes/migrations/2026.11.0.php');

	pg_assert_contains("\$with_class = pg_schema_has('products', 'tax_class');", $ecommerce, 'the column probed');
	pg_assert_contains("'rate' => pg_tax_class_line_rate(pg_tax_switched_on(), \$order_item['taxable'], \$order_item['tax_rate'], \$zone_rate, \$list_country, (string) (\$order_item['tax_class'] ?? 'standard'), \$today),", $ecommerce, 'each line by its class');

	pg_assert_contains('if (pg_product_tax_class_drawn()) {', $builder, 'drawn only while the switch is on');
	pg_assert_contains("if (pg_product_tax_class_drawn() && isset(\$_POST['tax_class'])) {", $builder, 'saved only when drawn');

	pg_assert_contains("upgrade_2026_11_0_product_tax_class();       // 11.0.285", $migration, 'the step is called');
	pg_assert_contains("'tax_class' => \"ENUM('standard','reduced','zero') NOT NULL DEFAULT 'standard'\",", $migration, 'standard for every stored product');
}
