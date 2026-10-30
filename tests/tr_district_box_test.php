<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the optional district box of a shipping address
 * (ship_tos.district): when it is drawn (pg_ship_to_district_shown()), what
 * is stored (pg_ship_to_district_clean(), pg_ship_to_district_set_sql()) and
 * which screens draw and save it, read from their source since no test here
 * has a database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function tr_district_box_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// escape() reads db::$con; no test here has a connection.
function tr_district_box_test_db_stub()
{
	if (!class_exists('db')) {
		class db
		{
			public static $con;
		}
	}
}

// A Turkish address gets the box, by code or by name, once the column exists.
function test_tr_district_box_shown_for_turkey()
{
	pg_assert_true(pg_ship_to_district_shown('TR', false, true), 'code');
	pg_assert_true(pg_ship_to_district_shown('tr', false, true), 'lower-case code');
	pg_assert_true(pg_ship_to_district_shown('Turkey', false, true), 'English name');
	pg_assert_true(pg_ship_to_district_shown('Türkiye', false, true), 'Turkish name');
	pg_assert_true(pg_ship_to_district_country_is_tr(' TR '), 'spaces');
}

// Another country gets it only when the carrier's driver asks for it.
function test_tr_district_box_hidden_elsewhere()
{
	pg_assert_false(pg_ship_to_district_shown('DE', false, true), 'Germany');
	pg_assert_false(pg_ship_to_district_shown('US', false, true), 'United States');
	pg_assert_false(pg_ship_to_district_shown('', false, true), 'no country');
	pg_assert_true(pg_ship_to_district_shown('DE', true, true), 'driver asks');
	pg_assert_false(pg_ship_to_district_carrier_asks(''), 'no carrier');
}

// Before step 11.0.134 nothing draws the box, even for Turkey.
function test_tr_district_box_hidden_without_column()
{
	pg_assert_false(pg_ship_to_district_shown('TR', false, false), 'Turkey');
	pg_assert_false(pg_ship_to_district_shown('DE', true, false), 'driver asks');
}

// Stored as one trimmed line of at most 50 characters; empty is allowed.
function test_tr_district_box_clean()
{
	pg_assert_same('Kadıköy', pg_ship_to_district_clean('  Kadıköy  '));
	pg_assert_same('', pg_ship_to_district_clean(''));
	pg_assert_same('', pg_ship_to_district_clean('   '));
	pg_assert_same('', pg_ship_to_district_clean(null));
	pg_assert_same('', pg_ship_to_district_clean(array('x')));
	pg_assert_same('Çankaya Merkez', pg_ship_to_district_clean("Çankaya\r\n Merkez"));
	pg_assert_same(str_repeat('ş', 50), pg_ship_to_district_clean(str_repeat('ş', 60)));
	pg_assert_same(50, mb_strlen(pg_ship_to_district_clean(str_repeat('a', 51)), 'UTF-8'));
	pg_assert_same('', pg_ship_to_district_clean("\xC3\x28"));
}

// The SET part carries the cleaned value, and nothing while the column is missing.
function test_tr_district_box_set_sql()
{
	tr_district_box_test_db_stub();

	pg_assert_same('', pg_ship_to_district_set_sql('Kadıköy', false));
	pg_assert_same('', pg_ship_to_district_set_sql('', false));
	pg_assert_same(", district = 'Kadıköy'", pg_ship_to_district_set_sql(' Kadıköy ', true));
	pg_assert_same(", district = ''", pg_ship_to_district_set_sql('', true));
	pg_assert_contains("O\\'Hara", pg_ship_to_district_set_sql("O'Hara", true));
}
