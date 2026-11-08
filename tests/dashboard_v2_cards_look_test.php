<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's shop and other cards as drawn that a unit
 * test can reach. They read the sources, since the markup is drawn by the
 * script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_dashboard_v2_cards_look_css()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_dash.src.css');
}

// Offers are drawn as coupons: a dashed amber frame, the code in a
// monospaced face and its note under it.
function test_dashboard_v2_offers_are_coupons()
{
	$css = pg_test_dashboard_v2_cards_look_css();
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_cards_shop.js');

	pg_assert_contains('"pg-dash-coupon"', $js, 'the offers renderer draws coupons');
	pg_assert_true((bool) preg_match('/\.pg-dash-coupon \{[^}]*border: 1\.5px dashed/', $css), 'a dashed frame');
	pg_assert_true((bool) preg_match('/\.pg-dash-coupon-code \{[^}]*font-family: var\(--pg-font-mono/', $css), 'a monospaced code');
}
