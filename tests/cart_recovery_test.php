<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the recovered carts rule (pg_cart_recovery_outcome() in
 * includes/fn/cart_recovery.php): a cart is left when it was started at
 * least 6 hours before the completion; a completed order is recovered when
 * its last touch was sent at most 7 days before the completion, and that
 * touch takes it; a left cart with no touch is counted apart. Also the
 * touch arguments (a pop-up on a page, a WhatsApp key), the left-cart test
 * and when a pop-up event is a touch.
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

function test_cart_recovery_not_left_is_not_recovered()
{
	$done = 1800000000;

	// A pop-up taken on a cart started an hour before it was completed: the
	// cart was never left, so the touch brought nothing back.
	$out = pg_cart_recovery_outcome($done - 3600, $done, array(5 => $done - 1800));
	pg_assert_same(false, $out['left'], 'started an hour before: not left');
	pg_assert_same(0, $out['touch_id'], 'a cart that was not left is not recovered');
	pg_assert_same(false, $out['untouched'], 'nor counted apart');

	$out = pg_cart_recovery_outcome($done - 21600, $done, array(5 => $done - 1800));
	pg_assert_same(5, $out['touch_id'], 'left exactly 6 hours before: the touch takes it');
}

function test_cart_recovery_channel_and_source_names()
{
	pg_assert_same('email', pg_cart_recovery_channel('EMAIL'), 'channel lower-cased');
	pg_assert_same('', pg_cart_recovery_channel('fax'), 'unknown channel refused');
	pg_assert_same('popup', pg_cart_recovery_source_type('popup'), 'pop-up source');
	pg_assert_same('', pg_cart_recovery_source_type('banner'), 'unknown source refused');
}

function test_cart_recovery_touch_args()
{
	$args = pg_cart_recovery_touch_args('42', 'page', 'popup', '7');
	pg_assert_same(array('order_id' => 42, 'channel' => 'page', 'source_type' => 'popup', 'source_id' => 7), $args, 'a pop-up taken up on a page is a touch');

	$args = pg_cart_recovery_touch_args(42, 'WhatsApp', 'Template', 3);
	pg_assert_same('whatsapp', $args['channel'], 'a WhatsApp key message: channel whatsapp');
	pg_assert_same('template', $args['source_type'], 'a WhatsApp key message: source template');

	pg_assert_same(null, pg_cart_recovery_touch_args(0, 'page', 'popup', 7), 'no order: no touch');
	pg_assert_same(null, pg_cart_recovery_touch_args(42, 'banner', 'popup', 7), 'unknown channel: no touch');
	pg_assert_same(null, pg_cart_recovery_touch_args(42, 'page', 'banner', 7), 'unknown source: no touch');
	pg_assert_same(0, pg_cart_recovery_touch_args(42, 'page', 'popup', -5)['source_id'], 'a negative source id is 0');
}

function test_cart_recovery_is_left()
{
	$now = 1800000000;

	pg_assert_same(true, pg_cart_recovery_is_left('incomplete', $now - 21600, $now), 'idle exactly 6 hours: left');
	pg_assert_same(false, pg_cart_recovery_is_left('incomplete', $now - 21599, $now), 'one second short of 6 hours: not left');
	pg_assert_same(true, pg_cart_recovery_is_left('incomplete', $now - 2592000, $now), 'idle exactly 30 days: still left');
	pg_assert_same(false, pg_cart_recovery_is_left('incomplete', $now - 2592001, $now), 'idle over 30 days: no longer touched');
	pg_assert_same(false, pg_cart_recovery_is_left('complete', $now - 86400, $now), 'a completed order is no left cart');
	pg_assert_same(false, pg_cart_recovery_is_left('incomplete', 0, $now), 'no change time: not left');
}

function test_cart_recovery_popup_touches()
{
	pg_assert_same(true, pg_cart_recovery_popup_touches('taken', 12, 1), 'taken with a cart line: a touch');
	pg_assert_same(true, pg_cart_recovery_popup_touches('taken', '12', 3), 'an order id as the session holds it');
	pg_assert_same(false, pg_cart_recovery_popup_touches('shown', 12, 1), 'shown is no touch');
	pg_assert_same(false, pg_cart_recovery_popup_touches('closed', 12, 1), 'closed is no touch');
	pg_assert_same(false, pg_cart_recovery_popup_touches('Taken', 12, 1), 'the event name is exact');
	pg_assert_same(false, pg_cart_recovery_popup_touches('taken', 0, 1), 'no order: no touch');
	pg_assert_same(false, pg_cart_recovery_popup_touches('taken', -4, 1), 'a negative order id: no touch');
	pg_assert_same(false, pg_cart_recovery_popup_touches('taken', 12, 0), 'an order with no line: no touch');
	pg_assert_same(false, pg_cart_recovery_popup_touches(null, 12, 1), 'no event: no touch');
}

function test_cart_recovery_never_left_drops_its_touches()
{
	$done = 1800000000;

	// Cart 12:00, pop-up 12:05, order 12:20: never left, its touch goes.
	$out = pg_cart_recovery_outcome($done - 1200, $done, array(5 => $done - 900));
	pg_assert_same(true, pg_cart_recovery_drop_touches($out, $done - 1200), 'a cart never left loses its touches');

	// The same cart completed the next day was left: the touch stays.
	$out = pg_cart_recovery_outcome($done - 86400, $done, array(5 => $done - 86100));
	pg_assert_same(false, pg_cart_recovery_drop_touches($out, $done - 86400), 'a left cart keeps its touches');
	pg_assert_same(5, $out['touch_id'], 'and the page touch recovers it');

	// An unknown start time decides nothing: the touches stay.
	$out = pg_cart_recovery_outcome(0, $done, array(5 => $done - 900));
	pg_assert_same(false, pg_cart_recovery_drop_touches($out, 0), 'no start time: nothing deleted');
}
