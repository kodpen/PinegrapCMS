<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the 2026.11.0 data step (11.0.138) that turns the Turkish ship_tos
 * rows of n11 orders imported before the import followed the checkout (province
 * in city, town in state) around. The row matcher is a pure function
 * (pg_ship_to_reversed_tr_swap() in includes/fn/shipping.php); the step is read
 * from its source, since no test here has a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one top-level function, up to the next one.
function n11_old_address_order_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// A reversed row is swapped and its empty district takes the town.
function test_n11_old_address_order_swaps_reversed_row()
{
	pg_assert_same(
		array('city' => 'Kadıköy', 'state' => 'İstanbul', 'district' => 'Kadıköy'),
		pg_ship_to_reversed_tr_swap(array('city' => 'İstanbul', 'state' => 'Kadıköy', 'district' => '', 'country' => 'Türkiye')));
}

// The province is found however its letters and case are typed, and by the
// older spellings the sales map knows.
function test_n11_old_address_order_province_accent_and_case()
{
	$swap = pg_ship_to_reversed_tr_swap(array('city' => 'ISTANBUL', 'state' => 'Beşiktaş', 'district' => '', 'country' => 'TR'));
	pg_assert_same('ISTANBUL', $swap['state']);
	pg_assert_same('Beşiktaş', $swap['city']);

	$swap = pg_ship_to_reversed_tr_swap(array('city' => 'iğdır', 'state' => 'Tuzluca', 'district' => '', 'country' => ''));
	pg_assert_same('iğdır', $swap['state']);

	$swap = pg_ship_to_reversed_tr_swap(array('city' => 'Afyon', 'state' => 'Sandıklı', 'district' => '', 'country' => ''));
	pg_assert_same('Afyon', $swap['state']);
}

// Turkey as empty, its code, and its Turkish or English name in any case;
// another country is left alone.
function test_n11_old_address_order_country_forms()
{
	$row = array('city' => 'Ankara', 'state' => 'Çankaya', 'district' => '');

	foreach (array('', 'TR', 'tr', 'Türkiye', 'TÜRKİYE', 'turkiye', 'Turkey', 'TURKEY', ' Türkiye ') as $country) {
		pg_assert_true(pg_ship_to_reversed_tr_swap($row + array('country' => $country)) !== array(), 'Turkey as "' . $country . '"');
	}

	foreach (array('DE', 'Germany', 'Cyprus', 'US') as $country) {
		pg_assert_same(array(), pg_ship_to_reversed_tr_swap($row + array('country' => $country)), 'not Turkey: ' . $country);
	}

	pg_assert_true(pg_ship_to_reversed_tr_swap(array('city' => 'Ankara', 'state' => 'Çankaya')) !== array(), 'a missing country reads as empty');
}

// A row whose state already names a province is in the checkout's order (or
// was swapped by an earlier run) and is left alone, as is a city that names
// no province or an empty row.
function test_n11_old_address_order_guards()
{
	pg_assert_same(array(), pg_ship_to_reversed_tr_swap(array('city' => 'Kadıköy', 'state' => 'İstanbul', 'district' => 'Kadıköy', 'country' => 'TR')));
	pg_assert_same(array(), pg_ship_to_reversed_tr_swap(array('city' => 'Bolu', 'state' => 'Ankara', 'district' => '', 'country' => 'TR')), 'both provinces');
	pg_assert_same(array(), pg_ship_to_reversed_tr_swap(array('city' => 'Kadıköy', 'state' => 'Moda', 'district' => '', 'country' => 'TR')));
	pg_assert_same(array(), pg_ship_to_reversed_tr_swap(array('city' => '', 'state' => '', 'district' => '', 'country' => '')));
	pg_assert_same(array(), pg_ship_to_reversed_tr_swap(array()));
	pg_assert_same(array(), pg_ship_to_reversed_tr_swap(null));

	// A rerun on what the first run wrote finds nothing.
	$first = pg_ship_to_reversed_tr_swap(array('city' => 'İzmir', 'state' => 'Bornova', 'district' => '', 'country' => 'TR'));
	pg_assert_same(array(), pg_ship_to_reversed_tr_swap($first + array('country' => 'TR')));
}

// A district already written stays; only an empty one takes the town.
function test_n11_old_address_order_district_only_when_empty()
{
	$swap = pg_ship_to_reversed_tr_swap(array('city' => 'Bursa', 'state' => 'Nilüfer', 'district' => 'Osmangazi', 'country' => 'TR'));
	pg_assert_same('Osmangazi', $swap['district']);

	$swap = pg_ship_to_reversed_tr_swap(array('city' => 'Bursa', 'state' => 'Nilüfer', 'district' => '  ', 'country' => 'TR'));
	pg_assert_same('Nilüfer', $swap['district']);

	// An empty town leaves the district empty too.
	$swap = pg_ship_to_reversed_tr_swap(array('city' => 'Bursa', 'state' => '', 'district' => '', 'country' => 'TR'));
	pg_assert_same(array('city' => '', 'state' => 'Bursa', 'district' => ''), $swap);
}

// The step is called with its label after the district step, reads only n11
// orders, guards its UPDATE with the values it read and leaves one note.
function test_n11_old_address_order_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	$entry = n11_old_address_order_test_body($source, 'upgrade_to_2026_11_0');
	$step = n11_old_address_order_test_body($source, 'upgrade_2026_11_0_n11_old_address_order');

	pg_assert_true(preg_match('/upgrade_2026_11_0_n11_old_address_order\(\);\s+\/\/ 11\.0\.138\b/', $entry) === 1, 'called with its label');
	pg_assert_same(1, preg_match_all('/\/\/ 11\.0\.138\b/', $entry), 'the label is used once');
	pg_assert_true(strpos($entry, 'upgrade_2026_11_0_tr_district_plate();') < strpos($entry, 'upgrade_2026_11_0_n11_old_address_order();'), 'after the district step');

	pg_assert_contains("provider = 'n11'", $step);
	pg_assert_contains('pg_ship_to_reversed_tr_swap(', $step);
	pg_assert_contains("install_column_exists('ship_tos', 'district')", $step);
	pg_assert_contains('LIMIT', $step);
	pg_assert_same(1, substr_count($step, 'install_note('), 'one note');
	pg_assert_false(strpos($step, 'ALTER') !== false, 'no schema statement');
	pg_assert_false(strpos($step, 'install_add_column') !== false, 'no schema statement');
}
