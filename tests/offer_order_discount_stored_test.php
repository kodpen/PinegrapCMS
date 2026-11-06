<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order discount offer an incomplete order keeps
 * (includes/fn/ecommerce.php, apply_offers_to_cart()): a winner is written,
 * and an order no order discount applies to any more is cleared even when the
 * session does not hold an amount for it (a cart retrieved in a new session).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_offer_order_discount_stored_winner_is_written()
{
	pg_assert_same("UPDATE orders SET discount_offer_id = '7' WHERE id = '42'", pg_order_discount_offer_update_sql(42, 7));
	pg_assert_same("UPDATE orders SET discount_offer_id = '7' WHERE id = '42'", pg_order_discount_offer_update_sql('42', '7'), 'ids as the database gives them');
}

// No winner: a stored offer is cleared, and an order without one is not
// written again on every cart view.
function test_offer_order_discount_stored_none_clears_a_stale_offer()
{
	pg_assert_same("UPDATE orders SET discount_offer_id = '0' WHERE (id = '42') AND (discount_offer_id != '0')", pg_order_discount_offer_update_sql(42, 0));
	pg_assert_same("UPDATE orders SET discount_offer_id = '0' WHERE (id = '42') AND (discount_offer_id != '0')", pg_order_discount_offer_update_sql(42, ''), 'empty id');
	pg_assert_same("UPDATE orders SET discount_offer_id = '0' WHERE (id = '0') AND (discount_offer_id != '0')", pg_order_discount_offer_update_sql("1' OR '1", 0), 'not a number');
}

// The cart clears a stale offer whatever the session holds: the clearing is
// not tied to a session amount a retrieved cart never had.
function test_offer_order_discount_stored_cart_clears_without_session_amount()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/ecommerce.php');
	pg_assert_true(strpos($source, "} else if (isset(\$_SESSION['ecommerce']['order_discount']) == true) {") === false, 'no session-only clearing');
	pg_assert_true(strpos($source, 'db(pg_order_discount_offer_update_sql($_SESSION[\'ecommerce\'][\'order_id\'] ?? 0, $best_order_discount_offer_id));') !== false, 'the cart writes through the helper');
}
