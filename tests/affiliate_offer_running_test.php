<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the one rule that says whether an offer runs on a given day
 * (enabled and inside its dates), which the cart, express order and the
 * affiliate link (?a=) all ask: an affiliate code whose group offer is
 * disabled or out of its dates is not put into the session as the special
 * offer code.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function affiliate_offer_running_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_affiliate_offer_running_enabled_inside_dates()
{
	$offer = array('status' => 'enabled', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31');
	pg_assert_true(pg_offer_is_running($offer, '2026-10-31'), 'the last day runs');
	pg_assert_true(pg_offer_is_running($offer, '2026-10-01'), 'the first day runs');
	pg_assert_true(pg_offer_is_running($offer, '2026-10-15'), 'a day inside runs');
}

function test_affiliate_offer_running_disabled_or_outside_dates()
{
	$offer = array('status' => 'disabled', 'start_date' => '2026-01-01', 'end_date' => '2099-12-31');
	pg_assert_false(pg_offer_is_running($offer, '2026-10-31'), 'a disabled offer does not run');

	$offer = array('status' => 'enabled', 'start_date' => '2026-11-01', 'end_date' => '2099-12-31');
	pg_assert_false(pg_offer_is_running($offer, '2026-10-31'), 'not started yet');

	$offer = array('status' => 'enabled', 'start_date' => '2026-01-01', 'end_date' => '2026-10-30');
	pg_assert_false(pg_offer_is_running($offer, '2026-10-31'), 'already ended');

	// A key code whose offer is gone reads null offer columns through its LEFT JOIN.
	$offer = array('status' => null, 'start_date' => null, 'end_date' => null);
	pg_assert_false(pg_offer_is_running($offer, '2026-10-31'), 'no offer does not run');
}

function test_affiliate_offer_running_any_of_shared_code()
{
	$off = array('status' => 'disabled', 'start_date' => '2026-01-01', 'end_date' => '2099-12-31');
	$on = array('status' => 'enabled', 'start_date' => '2026-01-01', 'end_date' => '2099-12-31');
	pg_assert_true(pg_offers_any_running(array($off, $on), '2026-10-31'), 'one running offer of a shared code is enough');
	pg_assert_false(pg_offers_any_running(array($off), '2026-10-31'), 'none running');
	pg_assert_false(pg_offers_any_running(array(), '2026-10-31'), 'no offer at all');
}

function test_affiliate_offer_running_affiliate_link_asks_the_rule()
{
	$page = affiliate_offer_running_source('get_page.php');
	pg_assert_contains("pg_special_offer_code_running(\$_GET['a'])", $page, 'the affiliate link fills the code only when its offer runs');
	pg_assert_true(strpos($page, "get_offer_code_for_special_offer_code(\$_GET['a'])") === false, 'not on the key code alone');

	foreach (array('shopping_cart.php', 'express_order.php') as $file) {
		$source = affiliate_offer_running_source($file);
		pg_assert_contains('pg_offers_any_running($offers, $current_date)', $source, $file . ' asks the same rule');
		pg_assert_true(strpos($source, "(\$offer['start_date'] <= \$current_date)") === false, $file . ' keeps no copy of the rule');
	}
}
