<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the recovered carts rule (pg_cart_recovery_outcome() in
 * includes/fn/cart_recovery.php): a cart is left when it was started at
 * least 6 hours before the completion; a completed order is recovered when
 * its last touch was sent at most 7 days before the completion, and that
 * touch takes it; a left cart with no touch is counted apart.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/cart_recovery.php');

function test_cart_recovery_left_window()
{
	$done = 1800000000;

	$out = pg_cart_recovery_outcome($done - 21600, $done, array());
	pg_assert_same(true, $out['left'], 'started exactly 6 hours before: left');
	pg_assert_same(true, $out['untouched'], 'left with no touch: counted apart');
	pg_assert_same(0, $out['touch_id'], 'no touch takes it');

	$out = pg_cart_recovery_outcome($done - 21599, $done, array());
	pg_assert_same(false, $out['left'], 'one second short of 6 hours: not left');
	pg_assert_same(false, $out['untouched'], 'a cart not left is not counted apart');

	$out = pg_cart_recovery_outcome(0, $done, array());
	pg_assert_same(false, $out['left'], 'no start time: not left');
}

function test_cart_recovery_last_touch_takes_it()
{
	$done = 1800000000;

	$out = pg_cart_recovery_outcome($done - 864000, $done, array(4 => $done - 500000, 9 => $done - 3600));
	pg_assert_same(9, $out['touch_id'], 'the last touch takes the completion');
	pg_assert_same(false, $out['untouched'], 'a touched cart is not counted apart');

	$out = pg_cart_recovery_outcome($done - 864000, $done, array(4 => $done - 3600, 9 => $done - 500000));
	pg_assert_same(4, $out['touch_id'], 'last by sent time, not by id');

	$out = pg_cart_recovery_outcome($done - 864000, $done, array(3 => $done - 100, 5 => $done - 100));
	pg_assert_same(5, $out['touch_id'], 'same second: the later row');
}

function test_cart_recovery_seven_days()
{
	$done = 1800000000;

	$out = pg_cart_recovery_outcome($done - 2000000, $done, array(7 => $done - 604800));
	pg_assert_same(7, $out['touch_id'], 'exactly 7 days before: recovered');

	$out = pg_cart_recovery_outcome($done - 2000000, $done, array(7 => $done - 604801));
	pg_assert_same(0, $out['touch_id'], 'one second over 7 days: not recovered');
	pg_assert_same(false, $out['untouched'], 'touched too long ago is not "no touch"');

	$out = pg_cart_recovery_outcome($done - 2000000, $done, array(2 => $done - 100, 7 => $done - 604801));
	pg_assert_same(2, $out['touch_id'], 'an older touch outside the window does not matter');

	$out = pg_cart_recovery_outcome($done - 2000000, $done, array(8 => $done + 60));
	pg_assert_same(0, $out['touch_id'], 'a touch after the completion takes nothing');
	pg_assert_same(true, $out['untouched'], 'only a later touch: no touch before the completion');

	$out = pg_cart_recovery_outcome($done - 2000000, $done, array(8 => 0));
	pg_assert_same(0, $out['touch_id'], 'a touch never sent takes nothing');
}

function test_cart_recovery_channel_and_source_names()
{
	pg_assert_same('email', pg_cart_recovery_channel('EMAIL'), 'channel lower-cased');
	pg_assert_same('', pg_cart_recovery_channel('fax'), 'unknown channel refused');
	pg_assert_same('popup', pg_cart_recovery_source_type('popup'), 'pop-up source');
	pg_assert_same('', pg_cart_recovery_source_type('banner'), 'unknown source refused');
}
