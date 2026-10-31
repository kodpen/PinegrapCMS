<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_order_view_district() (includes/fn/order_view.php): an order
 * address prints its district only when it differs from the city, compared
 * the Turkish way (I, İ, ı and i are one letter) and ignoring case and
 * spaces; and pins that the order screen, the printed order and the packing
 * slip go through it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_address_district_same_as_city_is_left_out()
{
	pg_assert_same('', pg_order_view_district('Kadıköy', 'Kadıköy'), 'same text');
	pg_assert_same('', pg_order_view_district('KADIKÖY', 'Kadıköy'), 'case');
	pg_assert_same('', pg_order_view_district('kadiköy', 'KADIKÖY'), 'dotless and dotted i');
	pg_assert_same('', pg_order_view_district('İSTANBUL', 'istanbul'), 'capital dotted İ');
	pg_assert_same('', pg_order_view_district('ISTANBUL', 'İstanbul'), 'capital I without a dot');
	pg_assert_same('', pg_order_view_district(' Kadıköy ', 'Kadıköy'), 'outer spaces');
	pg_assert_same('', pg_order_view_district('Kadı  köy', 'Kadıköy'), 'inner spaces');
}

function test_order_address_district_different_from_city_is_printed()
{
	pg_assert_same('Moda', pg_order_view_district('Moda', 'Kadıköy'), 'different');
	pg_assert_same('Kadıköy', pg_order_view_district(' Kadıköy ', 'İstanbul'), 'trimmed');
	pg_assert_same('Kadıköy', pg_order_view_district('Kadıköy', ''), 'no city');
	pg_assert_same('Kadıköy', pg_order_view_district('Kadıköy', null), 'city missing');
}

function test_order_address_district_empty_prints_nothing()
{
	pg_assert_same('', pg_order_view_district('', 'Kadıköy'), 'empty');
	pg_assert_same('', pg_order_view_district('   ', 'Kadıköy'), 'blank');
	pg_assert_same('', pg_order_view_district(null, 'Kadıköy'), 'column missing');
}

function test_order_address_district_screens_use_the_helper()
{
	$root = dirname(__DIR__) . '/pinegrap/';

	foreach (array('view_order.php' => 1, 'print_order.php' => 2, 'print_packing_slip.php' => 1) as $file => $count) {
		$source = (string) file_get_contents($root . $file);
		pg_assert_same($count, substr_count($source, 'pg_order_view_district('), $file);
		pg_assert_true(strpos($source, "!empty(\$ship_tos[\$key]['district'])") === false, $file . ': district printed without the city check');
		pg_assert_true(strpos($source, "!empty(\$st['district'])") === false, $file . ': district printed without the city check');
		pg_assert_true(strpos($source, "!empty(\$ship_to['district'])") === false, $file . ': district printed without the city check');
	}
}
