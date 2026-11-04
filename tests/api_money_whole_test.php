<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's money fields taking only a whole number of
 * minor units: "1999e-2" passed is_numeric() and (int) read it as 19, so a
 * price of 19.99 sent in exponent form was stored as 0.19 with 200.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/router.php');

function test_api_money_whole_takes_whole_minor_units()
{
	foreach (array(0, 1999, -250, '1999', ' 1999 ', '-250', '0', '007') as $raw) {
		pg_assert_true(api_money_is_whole($raw), var_export($raw, true));
	}
}

function test_api_money_whole_refuses_exponent_and_decimal_forms()
{
	foreach (array('1999e-2', '15e-1', '1E3', '1e0', 1e3, 19.99, '19.99', '19,99', '+5', '0x1A', '', ' ', 'abc', true, null, '1 999') as $raw) {
		pg_assert_false(api_money_is_whole($raw), var_export($raw, true));
	}
}
