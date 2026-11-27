<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor's market on the site (includes/fn/markets_site.php):
 * the rounding of a converted price, the market of a country, the country a
 * visitor is priced by, and the country list's choices.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each rule rounds up in minor units; the sign is kept and 0 stays 0.
function test_markets_site_round_minor()
{
	pg_assert_same(1234, pg_market_round_minor(1234, 'none'));
	pg_assert_same(1300, pg_market_round_minor(1234, 'whole'));
	pg_assert_same(1200, pg_market_round_minor(1200, 'whole'), 'already whole');
	pg_assert_same(1299, pg_market_round_minor(1234, 'end99'));
	pg_assert_same(1299, pg_market_round_minor(1299, 'end99'), 'already .99');
	pg_assert_same(1399, pg_market_round_minor(1300, 'end99'), 'a whole amount goes to the next .99');
	pg_assert_same(99, pg_market_round_minor(5, 'end99'));
	pg_assert_same(0, pg_market_round_minor(0, 'end99'));
	pg_assert_same(0, pg_market_round_minor(0, 'whole'));
	pg_assert_same(-1300, pg_market_round_minor(-1234, 'whole'), 'a negative amount by its size');
	pg_assert_same(-1299, pg_market_round_minor(-1234, 'end99'));
	pg_assert_same(1234, pg_market_round_minor(1234, 'bogus'), 'an unknown rule is none');
}

// A base price at the rate, rounded half away from zero, then by the rule.
function test_markets_site_price_minor()
{
	// 10.00 at 0.0297 = 0.297 -> 0.30
	pg_assert_same(30, pg_market_price_minor(1000, 0.0297, 'none'));
	// 19.99 at 1.5 = 29.985 -> 29.99 (half away from zero, never cut)
	pg_assert_same(2999, pg_market_price_minor(1999, 1.5, 'none'));
	pg_assert_same(3000, pg_market_price_minor(1999, 1.5, 'whole'));
	pg_assert_same(2999, pg_market_price_minor(1999, 1.5, 'end99'));
	pg_assert_same(-2999, pg_market_price_minor(-1999, 1.5, 'none'));
	pg_assert_same(0, pg_market_price_minor(0, 2, 'end99'));
	pg_assert_same(1999, pg_market_price_minor('1999', '1', 'none'), 'figures as strings');
	pg_assert_same(0, pg_market_price_minor(1999, 0, 'none'), 'no rate, no price');
}

// The market holding a country; '' and an unknown country have none.
function test_markets_site_for_country()
{
	$markets = array(
		7 => array('id' => 7, 'currency_code' => 'EUR', 'rounding' => 'end99', 'countries' => array('DE', 'FR')),
		9 => array('id' => 9, 'currency_code' => 'USD', 'rounding' => 'none', 'countries' => array('US')),
	);

	pg_assert_same(7, pg_market_for_country($markets, 'fr')['id']);
	pg_assert_same(9, pg_market_for_country($markets, ' US ')['id']);
	pg_assert_same(null, pg_market_for_country($markets, 'TR'));
	pg_assert_same(null, pg_market_for_country($markets, ''));
	pg_assert_same(null, pg_market_for_country(array(), 'DE'));
}

// The country chosen in the list wins over the one the visitor is in; an
// empty choice is "other countries" and prices in base.
function test_markets_site_visitor_country()
{
	pg_assert_same(array('country' => 'DE', 'chosen' => false), pg_market_visitor_country(array(), 'de'));
	pg_assert_same(array('country' => 'FR', 'chosen' => true), pg_market_visitor_country(array('market_country' => 'fr'), 'DE'));
	pg_assert_same(array('country' => '', 'chosen' => true), pg_market_visitor_country(array('market_country' => ''), 'DE'));
	pg_assert_same(array('country' => 'DE', 'chosen' => false), pg_market_visitor_country(array('market_country' => array('x')), 'DE'), 'an array is no choice');
	pg_assert_same(array('country' => '', 'chosen' => true), pg_market_visitor_country(array('market_country' => 'Germany'), 'DE'), 'not two letters: other countries');
	pg_assert_same(array('country' => '', 'chosen' => false), pg_market_visitor_country(array(), 'T1'), 'Tor is no country');
}

// The list holds the enabled markets' countries by name, the visitor's
// first when it is not in any of them, "other countries" last.
function test_markets_site_country_choices()
{
	$markets = array(
		7 => array('id' => 7, 'countries' => array('DE', 'FR')),
		9 => array('id' => 9, 'countries' => array('US')),
	);
	$names = array('DE' => 'Germany', 'FR' => 'France', 'US' => 'United States', 'TR' => 'Türkiye');

	pg_assert_same(array('FR' => 'France', 'DE' => 'Germany', 'US' => 'United States'), pg_market_country_choices($markets, $names));
	pg_assert_same(array('FR' => 'France'), pg_market_country_choices(array(array('countries' => array('FR', 'XX'))), $names), 'a code with no country row is left out');
}

// The module is loaded with markets.php and the site's money goes through it.
function test_markets_site_wiring()
{
	$markets = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/markets.php');
	pg_assert_true(strpos($markets, "includes/fn/markets_site.php") !== false, 'markets.php loads the site part');

	$init = file_get_contents(PG_FUNCTIONS_DIR . '/init.php');
	pg_assert_true(strpos($init, 'pg_market_visitor()') !== false, 'init.php takes the visitor\'s market');
	pg_assert_true(strpos($init, "define('VISITOR_MARKET_ID'") !== false);

	$ecommerce = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');
	pg_assert_true(strpos($ecommerce, 'pg_visitor_price(') !== false, 'the money functions round by the market');

	pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/update_market.php'), 'the country list posts to update_market.php');
}
