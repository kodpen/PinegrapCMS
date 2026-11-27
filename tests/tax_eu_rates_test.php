<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the EU's VAT rate list (includes/fn/tax_eu_rates.php), the rate a
 * buyer's address stands for at the checkout (pg_tax_buyer_zone_rate()) and
 * the switch "Rates by the buyer's country" in Site Settings › Commerce.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each member state's standard rate on 2026-11-27, as the Commission's TEDB
// answered for that day.
function tax_eu_rates_test_current()
{
	return array(
		'AT' => 20.0, 'BE' => 21.0, 'BG' => 20.0, 'CY' => 19.0, 'CZ' => 21.0,
		'DE' => 19.0, 'DK' => 25.0, 'EE' => 24.0, 'ES' => 21.0, 'FI' => 25.5,
		'FR' => 20.0, 'GR' => 24.0, 'HR' => 25.0, 'HU' => 27.0, 'IE' => 23.0,
		'IT' => 22.0, 'LT' => 21.0, 'LU' => 17.0, 'LV' => 21.0, 'MT' => 18.0,
		'NL' => 21.0, 'PL' => 23.0, 'PT' => 23.0, 'RO' => 21.0, 'SE' => 25.0,
		'SI' => 22.0, 'SK' => 23.0,
	);
}

function test_tax_eu_rates_every_member_state()
{
	$current = tax_eu_rates_test_current();

	pg_assert_same(27, count($current), 'twenty-seven member states');

	foreach ($current as $country => $rate) {
		pg_assert_same($rate, pg_eu_vat_rate($country, 'standard', '2026-11-27'), $country . '\'s standard rate');
		pg_assert_true(pg_vat_eu_member($country), $country . ' is a member state for the VAT check too');
	}

	$listed = array_values(array_unique(array_column(pg_eu_vat_rates(), 0)));
	sort($listed);
	$expected = array_keys($current);
	sort($expected);

	pg_assert_same($expected, $listed, 'the list holds the member states and no other country');
}

function test_tax_eu_rates_rows_well_formed()
{
	$seen = array();
	$last = array();

	foreach (pg_eu_vat_rates() as $row) {
		pg_assert_same(4, count($row), 'four fields');
		list($country, $kind, $rate, $from) = $row;

		pg_assert_true(preg_match('/^[A-Z]{2}$/', $country) === 1, $country . ': an ISO code');
		pg_assert_true(in_array($kind, array('standard', 'reduced'), true), $country . ': the standard and the reduced rate are listed');

		if ($kind === 'standard') {
			pg_assert_true(is_float($rate) && ($rate >= 15.0) && ($rate < 30.0), $country . ' ' . $from . ': a standard rate of at least 15%');
		} else {
			pg_assert_true(is_float($rate) && ($rate >= 5.0) && ($rate < 15.0), $country . ' ' . $from . ': a reduced rate of at least 5%');
		}

		$day = DateTime::createFromFormat('!Y-m-d', $from);
		pg_assert_true(($day !== false) && ($day->format('Y-m-d') === $from), $country . ' ' . $from . ': a real day');

		$key = $country . ' ' . $kind;
		pg_assert_false(isset($seen[$key . ' ' . $from]), $key . ' ' . $from . ': one row a day');
		pg_assert_true(!isset($last[$key]) || ($last[$key] < $from), $key . ' ' . $from . ': oldest first');

		$seen[$key . ' ' . $from] = true;
		$last[$key] = $from;
	}
}

// A changed rate applies on its date: the day before keeps the old one.
function test_tax_eu_rates_dated_change()
{
	pg_assert_same(20.0, pg_eu_vat_rate('EE', 'standard', '2023-12-31'), 'Estonia before 2024');
	pg_assert_same(22.0, pg_eu_vat_rate('EE', 'standard', '2024-01-01'), 'Estonia from 1 January 2024');
	pg_assert_same(22.0, pg_eu_vat_rate('EE', 'standard', '2025-06-30'), 'Estonia on 30 June 2025');
	pg_assert_same(24.0, pg_eu_vat_rate('EE', 'standard', '2025-07-01'), 'Estonia from 1 July 2025');
	pg_assert_same(19.0, pg_eu_vat_rate('RO', 'standard', '2025-07-31'), 'Romania before August 2025');
	pg_assert_same(21.0, pg_eu_vat_rate('RO', 'standard', '2025-08-01'), 'Romania from 1 August 2025');
	pg_assert_same(24.0, pg_eu_vat_rate('FI', 'standard', '2024-08-31'), 'Finland before September 2024');
	pg_assert_same(25.5, pg_eu_vat_rate('FI', 'standard', '2024-09-01'), 'Finland from 1 September 2024');
	pg_assert_same(20.0, pg_eu_vat_rate('SK', 'standard', '2024-12-31'), 'Slovakia before 2025');
	pg_assert_same(23.0, pg_eu_vat_rate('SK', 'standard', '2025-01-01'), 'Slovakia from 2025');

	// A rate cut for a while and restored.
	pg_assert_same(16.0, pg_eu_vat_rate('DE', 'standard', '2020-12-31'), 'Germany\'s cut in 2020');
	pg_assert_same(19.0, pg_eu_vat_rate('DE', 'standard', '2021-01-01'), 'Germany restored in 2021');
	pg_assert_same(21.0, pg_eu_vat_rate('IE', 'standard', '2021-02-28'), 'Ireland\'s cut');
	pg_assert_same(23.0, pg_eu_vat_rate('IE', 'standard', '2021-03-01'), 'Ireland restored');
	pg_assert_same(16.0, pg_eu_vat_rate('LU', 'standard', '2023-12-31'), 'Luxembourg\'s cut in 2023');
	pg_assert_same(17.0, pg_eu_vat_rate('LU', 'standard', '2024-01-01'), 'Luxembourg restored in 2024');

	// A date with a time is read as its day.
	pg_assert_same(24.0, pg_eu_vat_rate('EE', 'standard', '2025-07-01 00:00:00'), 'a date-time');
}

function test_tax_eu_rates_codes_and_refusals()
{
	pg_assert_same(24.0, pg_eu_vat_rate('EL', 'standard', '2026-11-27'), 'EL is Greece');
	pg_assert_same(19.0, pg_eu_vat_rate(' de ', 'Standard', '2026-11-27'), 'case and spaces');

	foreach (array('TR', 'GB', 'XI', 'CH', 'NO', 'US', 'AX', 'MC', '') as $country) {
		pg_assert_false(pg_eu_vat_rate($country, 'standard', '2026-11-27'), '"' . $country . '" is not in the list');
	}

	pg_assert_false(pg_eu_vat_rate('DE', 'super-reduced', '2026-11-27'), 'a kind the list does not hold');
	pg_assert_false(pg_eu_vat_rate('DE', 'standard', '2015-12-31'), 'a day before the first row');
	pg_assert_false(pg_eu_vat_rate('DE', 'standard', ''), 'no day');
	pg_assert_false(pg_eu_vat_rate('DE', 'standard', '27.11.2026'), 'a day in another shape');
}

// With the switch off every address keeps the zone rate it has today, so
// every line keeps today's rate.
function test_tax_eu_rates_switch_off_keeps_today()
{
	$zones = array(false, '20.000', '0.000', 0, 20.0, null);
	$countries = array('DE', 'FR', 'TR', 'GB', '');
	$products = array(array(1, null), array(1, '10.000'), array(1, '0.000'), array(0, null), array(0, '10.000'));

	foreach ($zones as $zone) {
		foreach ($countries as $country) {
			pg_assert_same($zone, pg_tax_buyer_zone_rate($zone, $country, false, '2026-11-27'), 'the zone rate as it is: ' . var_export($zone, true) . ' ' . $country);

			foreach (array(true, false) as $tax_on) {
				foreach ($products as $product) {
					pg_assert_same(
						pg_order_line_tax_rate($tax_on, $product[0], $product[1], $zone),
						pg_order_line_tax_rate($tax_on, $product[0], $product[1], pg_tax_buyer_zone_rate($zone, $country, false, '2026-11-27')),
						'today\'s line rate: ' . var_export($zone, true) . ' ' . $country . ' ' . var_export($product, true)
					);
				}
			}
		}
	}

	// An EU buyer in no zone pays no tax while it is off, as today.
	pg_assert_same(0.0, pg_order_line_tax_rate(true, 1, null, pg_tax_buyer_zone_rate(false, 'DE', false, '2026-11-27')), 'no zone, no tax');
	pg_assert_false(pg_tax_by_country_on(), 'off on a site without the row');
}

function test_tax_eu_rates_switch_on()
{
	$day = '2026-11-27';

	// An EU address in no zone takes its country's standard rate.
	pg_assert_same(19.0, pg_tax_buyer_zone_rate(false, 'DE', true, $day), 'Germany in no zone');
	pg_assert_same(24.0, pg_tax_buyer_zone_rate(false, 'GR', true, $day), 'Greece in no zone');
	pg_assert_same(22.0, pg_tax_buyer_zone_rate(false, 'EE', true, '2025-06-30'), 'the rate of the order\'s day');
	pg_assert_same(19.0, pg_order_line_tax_rate(true, 1, null, pg_tax_buyer_zone_rate(false, 'DE', true, $day)), 'the line takes it');

	// A tax zone wins, a zone of 0 too.
	pg_assert_same('20.000', pg_tax_buyer_zone_rate('20.000', 'DE', true, $day), 'a zone\'s rate');
	pg_assert_same('0.000', pg_tax_buyer_zone_rate('0.000', 'DE', true, $day), 'a zone of 0');
	pg_assert_same(0.0, pg_order_line_tax_rate(true, 1, null, pg_tax_buyer_zone_rate('0.000', 'DE', true, $day)), 'a zone of 0 charges 0');

	// A product's own rate wins, its 0 too; an untaxable product and the Tax
	// switch off stay at 0.
	pg_assert_same(10.0, pg_order_line_tax_rate(true, 1, '10.000', pg_tax_buyer_zone_rate(false, 'DE', true, $day)), 'the product\'s own rate');
	pg_assert_same(0.0, pg_order_line_tax_rate(true, 1, '0.000', pg_tax_buyer_zone_rate(false, 'DE', true, $day)), 'a product rate of 0');
	pg_assert_same(0.0, pg_order_line_tax_rate(true, 0, null, pg_tax_buyer_zone_rate(false, 'DE', true, $day)), 'not taxable');
	pg_assert_same(0.0, pg_order_line_tax_rate(false, 1, null, pg_tax_buyer_zone_rate(false, 'DE', true, $day)), 'Tax off');

	// Outside the EU nothing changes.
	pg_assert_false(pg_tax_buyer_zone_rate(false, 'TR', true, $day), 'Turkey in no zone');
	pg_assert_false(pg_tax_buyer_zone_rate(false, '', true, $day), 'no country');
	pg_assert_same(0.0, pg_order_line_tax_rate(true, 1, '10.000', pg_tax_buyer_zone_rate(false, 'US', true, $day)), 'no tax in no zone outside the EU');
}

// One place decides: the checkout's and the express order's taxes read each
// address through it after the exempt order is cleared; the counter sale keeps
// its store's zone.
function test_tax_eu_rates_one_place()
{
	$root = dirname(__DIR__) . '/pinegrap';
	$ecommerce = (string) file_get_contents($root . '/includes/fn/ecommerce.php');
	$sale = (string) file_get_contents($root . '/includes/local_sale.php');
	$order = (string) file_get_contents($root . '/includes/fn/local_order.php');

	$start = strpos($ecommerce, 'function update_order_item_taxes(');
	pg_assert_true($start !== false, 'update_order_item_taxes() is there');
	$end = strpos($ecommerce, "\nfunction ", $start + 10);
	$body = substr($ecommerce, (int) $start, ($end === false) ? null : ($end - $start));

	$exempt = strpos($body, 'SET tax_total = 0, tax = 0');
	$billing = strpos($body, '$billing_address_tax_rate = pg_tax_rate_for_buyer_address($billing_country, $billing_state, $today, $billing_list_country);');
	$shipping = strpos($body, "\$shipping_address_tax_rate = pg_tax_rate_for_buyer_address((string) (\$ship_to_address['country'] ?? ''), (string) (\$ship_to_address['state'] ?? ''), \$today, \$shipping_list_country);");

	pg_assert_true($billing !== false, 'the billing address');
	pg_assert_true($shipping !== false, 'a recipient\'s address');
	pg_assert_true(($exempt !== false) && ($exempt < $billing), 'an exempt order is cleared first');
	pg_assert_false(strpos($body, 'get_tax_rate_for_address(') !== false, 'no address read past it');

	pg_assert_contains('$zone_rate = get_tax_rate_for_address($country, $state);', $ecommerce, 'the zone');
	pg_assert_contains('return pg_tax_buyer_zone_rate($zone_rate, $country, $by_country, $date);', $ecommerce, 'then the switch');
	pg_assert_false(strpos($sale, 'pg_tax_rate_for_buyer_address') !== false, 'the counter sale keeps its store\'s zone');
	pg_assert_false(strpos($order, 'pg_tax_rate_for_buyer_address') !== false, 'the counter sale\'s completion too');
}

// The switch sits beside Tax, its note says what the shop takes on and shows
// while it is on, and it is saved as a config_text row only when drawn.
function test_tax_eu_rates_settings_switch()
{
	$root = dirname(__DIR__) . '/pinegrap';
	$card = (string) file_get_contents($root . '/includes/settings/commerce.php');
	$save = (string) file_get_contents($root . '/includes/settings/commerce.save.php');
	$script = pg_commerce_settings_reveal_script();

	$tax = strpos($card, "pg_commerce_settings_switch('ecommerce_tax', ");
	$row = strpos($card, '. $tax_by_country_row');
	$exempt = strpos($card, "pg_commerce_settings_switch('ecommerce_tax_exempt', ");

	pg_assert_true(($tax !== false) && ($row !== false) && ($exempt !== false) && ($tax < $row) && ($row < $exempt), 'between Tax and Allow tax-exempt');
	pg_assert_contains('lang("Rates by the buyer\'s country")', $card, 'its label');
	pg_assert_contains('liability', $card, 'the note names the liability');
	pg_assert_contains('one-stop shop', $card, 'and the one-stop shop');
	pg_assert_contains('data-pg-commerce-shows="ecommerce_tax_by_country_note"', $card, 'the switch shows its note');
	pg_assert_contains('data-pg-commerce-shows', $script, 'the script follows the switch');

	pg_assert_contains("if (post_value('ecommerce_tax_by_country_card') && pg_schema_has('config_text')) {", $save, 'saved only when drawn');
	pg_assert_contains("pg_config_text_save('ecommerce_tax_by_country', post_value('ecommerce_tax_by_country') ? '1' : '');", $save, 'a config_text row, none while off');

	$html = pg_commerce_settings_switch('ecommerce_tax_by_country', false, 'Rates', 'Help', '', '<div id="note" hidden>Note</div>');
	pg_assert_contains('<div id="note" hidden>Note</div>', $html, 'what follows the help line stays in the switch\'s cell');
	pg_assert_true(strpos($html, 'Note') < strrpos($html, '</div>'), 'inside the cell');
}
