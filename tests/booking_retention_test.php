<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the retention of appointment bookings
 * (includes/fn/booking_retention.php): the 400-day cutoff, which rows the
 * daily pass removes, and which bookings an erasure cancels first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// 400 days of seconds before now; never below zero.
function test_booking_retention_cutoff()
{
	$now = 1800000000;

	pg_assert_same(400, pg_booking_retention_limits()['days'], '400 days');
	pg_assert_same($now - (400 * 86400), pg_booking_retention_cutoff($now), 'cutoff');
	pg_assert_same(0, pg_booking_retention_cutoff(1000), 'not below zero');
}

// Only cancelled rows, by their last change (creation when never stamped).
function test_booking_retention_where()
{
	$where = pg_booking_retention_where('1765440000');

	pg_assert_same("(status = 'cancelled') AND (GREATEST(updated_at, created_at) < '1765440000')", $where, 'condition');
	pg_assert_same("(status = 'cancelled') AND (GREATEST(updated_at, created_at) < '1')", pg_booking_retention_where("1' OR '1"), 'cast to int');

	foreach (array('held', 'requested', 'confirmed', 'no_show', 'done') as $status) {
		pg_assert_true(strpos($where, "'" . $status . "'") === false, $status . ' kept');
	}
}

// An erasure cancels what may still be cancelled and has not begun.
function test_booking_erase_cancel_where()
{
	pg_assert_same(array('held', 'requested', 'confirmed'), pg_booking_erase_live_statuses(), 'live statuses');
	pg_assert_same(
		"(status IN ('held', 'requested', 'confirmed')) AND (start_at > '1800000000')",
		pg_booking_erase_cancel_where(1800000000),
		'condition'
	);
}
