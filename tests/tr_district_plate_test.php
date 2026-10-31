<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Turkish address columns of 2026.11.0 (11.0.134):
 * ship_tos.district, the recipient's district as typed, and
 * states.plate_code, the licence plate number of a Turkish province. The
 * plate fill is a pure function (pg_state_plate_codes() in
 * includes/fn/shipping.php); the step and the readiness probe are read from
 * their source, since no test here has a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function tr_district_plate_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function tr_district_plate_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// A province name gives its plate number however its Turkish letters and
// case are typed; the names the states table ships (Afyon, Hakkari) count.
function test_tr_district_plate_code_from_province_name()
{
	pg_assert_same('34', pg_state_plate_code('İstanbul'));
	pg_assert_same('34', pg_state_plate_code('ISTANBUL'));
	pg_assert_same('35', pg_state_plate_code('izmir'));
	pg_assert_same('76', pg_state_plate_code('IĞDIR'));
	pg_assert_same('76', pg_state_plate_code('Iğdır'));
	pg_assert_same('18', pg_state_plate_code('Çankırı'));
	pg_assert_same('63', pg_state_plate_code('Şanlıurfa'));
	pg_assert_same('03', pg_state_plate_code('Afyon'));
	pg_assert_same('30', pg_state_plate_code('Hakkari'));
	pg_assert_same('01', pg_state_plate_code(' Adana '));
}

// A name no province carries gives nothing.
function test_tr_district_plate_code_unmatched_name()
{
	pg_assert_same('', pg_state_plate_code('Alabama'));
	pg_assert_same('', pg_state_plate_code('Kadıköy'));
	pg_assert_same('', pg_state_plate_code(''));
	pg_assert_same('', pg_state_plate_code(null));
}

// Only rows with an empty plate code and a known name are filled; a row the
// operator (or an earlier run) already filled keeps its value.
function test_tr_district_plate_codes_fill_only_empty_rows()
{
	$rows = array(
		array('id' => '70', 'name' => 'İstanbul', 'plate_code' => ''),
		array('id' => '73', 'name' => 'Afyon', 'plate_code' => ''),
		array('id' => '76', 'name' => 'Ankara', 'plate_code' => '06'),
		array('id' => '77', 'name' => 'Antalya', 'plate_code' => '99'),
		array('id' => '80', 'name' => 'Nowhere', 'plate_code' => ''),
		array('id' => '81', 'name' => 'Kırşehir'));

	pg_assert_same(array(70 => '34', 73 => '03', 81 => '40'), pg_state_plate_codes($rows));
	pg_assert_same(array(), pg_state_plate_codes(array()));
}

// The countries filled are the rows the sales map reads as Turkey: the
// shipped code, a renamed code and a row whose code is not a code.
function test_tr_district_plate_country_ids()
{
	$countries = array(
		array('id' => '233', 'name' => 'Turkey', 'code' => 'TR'),
		array('id' => '241', 'name' => 'United States', 'code' => 'US'),
		array('id' => '300', 'name' => 'Türkiye', 'code' => 'TÜRKİYE'),
		array('id' => '301', 'name' => 'Cyprus', 'code' => 'CY'));

	pg_assert_same(array(233, 300), pg_state_plate_country_ids($countries));
}

// The step is called with its label, adds both columns with the idempotent
// helper and fills only empty plate codes, so a second run changes nothing.
function test_tr_district_plate_step()
{
	$source = tr_district_plate_test_source('includes/migrations/2026.11.0.php');
	$entry = tr_district_plate_test_body($source, 'upgrade_to_2026_11_0');
	$step = tr_district_plate_test_body($source, 'upgrade_2026_11_0_tr_district_plate');

	pg_assert_true(preg_match('/upgrade_2026_11_0_tr_district_plate\(\);\s+\/\/ 11\.0\.134\b/', $entry) === 1, 'called with its label');
	pg_assert_same(1, preg_match_all('/\/\/ 11\.0\.134\b/', $entry), 'the label is used once');

	pg_assert_contains("install_add_column('ship_tos', 'district', \"VARCHAR(50) NOT NULL DEFAULT ''\")", $step);
	pg_assert_contains("install_add_column('states', 'plate_code', \"VARCHAR(4) NOT NULL DEFAULT ''\")", $step);
	pg_assert_contains('pg_state_plate_codes(', $step);
	pg_assert_contains("AND (plate_code = '')", $step);
	pg_assert_contains('install_note(', $step);
	pg_assert_false(strpos($step, 'ALTER') !== false, 'no raw schema statement');
}

// Code that reads the district asks first, by the exact name, and says no
// when the probe itself is missing.
function test_tr_district_ready_probe()
{
	$body = tr_district_plate_test_body(tr_district_plate_test_source('includes/fn/shipping.php'), 'pg_ship_to_district_ready');

	pg_assert_contains("function_exists('pg_schema_has') && pg_schema_has('ship_tos', 'district')", $body);
	pg_assert_true(is_bool(pg_ship_to_district_ready()), 'answers yes or no');
}

// A renewal copies the district with the rest of the address once the column
// is there.
function test_tr_district_renewal_copies_district()
{
	$body = tr_district_plate_test_body(tr_district_plate_test_source('includes/fn/subscription_renewal.php'), 'pg_subscription_renewal_order_write');

	pg_assert_contains("pg_ship_to_district_ready()", $body);
	pg_assert_contains("', district'", $body);
}
