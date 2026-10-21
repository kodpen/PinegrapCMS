<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the record of offer gifts the shopper took out of the cart
 * (includes/fn/ecommerce.php): pg_offer_gift_refuse() writes it,
 * pg_offer_gift_refused() reads and forgets it, per order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_offer_gift_refusal_is_remembered_once()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('ecommerce' => array('order_id' => '41'));

	pg_assert_false(pg_offer_gift_refused(7, 9), 'nothing refused yet');
	pg_offer_gift_refuse('41', '7', '9');
	pg_offer_gift_refuse(41, 7, 9);
	pg_assert_true(pg_offer_gift_refused(7, 9), 'refused');
	pg_assert_same(array('41_7_9'), $_SESSION['ecommerce']['declined_offer_gifts'], 'one record for the same gift');
	pg_assert_false(pg_offer_gift_refused(7, 10), 'another action of the offer');

	$_SESSION = $saved;
}

function test_offer_gift_refusal_belongs_to_one_order()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('ecommerce' => array('order_id' => '41'));

	pg_offer_gift_refuse(41, 7, 9);
	$_SESSION['ecommerce']['order_id'] = '42';
	pg_assert_false(pg_offer_gift_refused(7, 9), 'the next order offers the gift again');

	$_SESSION = $saved;
}

function test_offer_gift_refusal_is_forgotten()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array('ecommerce' => array('order_id' => '41', 'declined_offer_gifts' => 'not a list'));

	pg_offer_gift_refuse(41, 7, 9);
	pg_assert_same(array('41_7_9'), $_SESSION['ecommerce']['declined_offer_gifts'], 'a broken record starts over');
	pg_assert_false(pg_offer_gift_refused(7, 9, true), 'forgetting answers false');
	pg_assert_false(pg_offer_gift_refused(7, 9), 'forgotten');

	$_SESSION = $saved;
}
