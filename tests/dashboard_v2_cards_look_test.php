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

// The shop cards as drawn: the figure in the name gradient with its line at
// the far end, the week's amber bars over their day names, best sellers and
// orders as ruled rows with the state in its tone, the sales map in fuchsia.
function test_dashboard_v2_shop_cards_drawn()
{
	$css = pg_test_dashboard_v2_cards_look_css();
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_cards_shop.js');

	pg_assert_true((bool) preg_match('/\.pg-dash-shop-figure \{[^}]*background: var\(--pgd-name\);/', $css), 'figure in the name gradient');
	pg_assert_true((bool) preg_match('/\.pg-dash-shop-head \{[^}]*justify-content: space-between;/', $css), 'its line at the far end');
	pg_assert_contains('"pg-dash-shop-daynames"', $js, 'day names under the bars');
	pg_assert_true((bool) preg_match('/\.pg-dash-shop-days \.pg-dash-bars \{[^}]*var\(--pgd-amber\)/', $css), 'amber bars');
	pg_assert_contains('.pg-dash-shop-meta[data-tone="warn"]', $css, 'an order state in its tone');
	pg_assert_contains('--pg-dash-map-hue: var(--pgd-chart-3);', $css, 'the map in fuchsia');
	pg_assert_contains('"pg-dash-fx-row"', $js, 'a currency on one line');
	pg_assert_contains('.pg-dash-spark[data-tone="up"] path', $css, 'the week line in the change\'s tone');

	$shop = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/shop.php');
	pg_assert_contains("'days'       => \$day_names,", $shop, 'the card sends the day names');
}

// The other cards as drawn: the visitors on the site in a green glow, the
// note as a pale amber page, Performance as a half gauge.
function test_dashboard_v2_other_cards_drawn()
{
	$css = pg_test_dashboard_v2_cards_look_css();
	$visitors = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_cards_visitors.js');

	pg_assert_contains('el("p", "visually-hidden", data.headline)', $visitors, 'the full sentence stays for screen readers');
	pg_assert_true((bool) preg_match('/\.pg-dash-live-figure \{[^}]*radial-gradient/', $css), 'the figure glows');
	pg_assert_true((bool) preg_match('/\.pg-dash-notes \{[^}]*color: var\(--pgd-note\);/', $css), 'the note page');
	pg_assert_true((bool) preg_match('/\.pg-dash-perf \.pg-dash-goal-ring-dial \{[^}]*conic-gradient\(from 270deg/', $css), 'a half gauge');
}
