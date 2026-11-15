<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the recovered carts figures (pg_cart_recovery_figures() in
 * includes/fn/cart_recovery_figures.php): a cart belongs to the period of
 * its first touch; reached, returned, recovered and revenue per cart, per
 * channel and per message; a late cart counts among the left, reached and
 * returned carts and never among the recovered ones.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/cart_recovery_figures.php');

// One touch row as the table holds it.
function cart_recovery_figures_test_touch($order_id, $channel, $source_id, $sent_at, array $more = array())
{
	return $more + array(
		'order_id'        => $order_id,
		'channel'         => $channel,
		'source_type'     => 'campaign',
		'source_id'       => $source_id,
		'sent_at'         => $sent_at,
		'clicked_at'      => 0,
		'restored_at'     => 0,
		'recovered'       => 0,
		'completed_total' => 0,
	);
}

function test_cart_recovery_figures_cart_counts()
{
	$from = 1800000000;
	$to = $from + 86400 * 7;

	$touches = array(
		// Reached only.
		cart_recovery_figures_test_touch(1, 'email', 10, $from + 100),
		// Returned by a click, then recovered by its second mail.
		cart_recovery_figures_test_touch(2, 'email', 10, $from + 200, array('clicked_at' => $from + 300)),
		cart_recovery_figures_test_touch(2, 'email', 11, $from + 400, array('restored_at' => $from + 500, 'recovered' => 1, 'completed_total' => 12550)),
		// Late: clicked, completed more than 7 days after its touch.
		cart_recovery_figures_test_touch(3, 'whatsapp', 20, $from + 600, array('clicked_at' => $from + 700)),
		// First touch before the period: not this period's cart.
		cart_recovery_figures_test_touch(4, 'email', 10, $from - 50),
		cart_recovery_figures_test_touch(4, 'email', 11, $from + 50, array('recovered' => 1, 'completed_total' => 999)),
		// First touch after the period.
		cart_recovery_figures_test_touch(5, 'email', 10, $to),
		// A no-touch row never reaches the figures as a touch.
		cart_recovery_figures_test_touch(6, '', 0, 0, array('completed_total' => 500)),
	);

	$f = pg_cart_recovery_figures($touches, $from, $to, 4);

	pg_assert_same(3, $f['reached'], 'carts whose first touch is in the period');
	pg_assert_same(2, $f['returned'], 'a click or a restore returns a cart; the late one too');
	pg_assert_same(1, $f['recovered'], 'the late cart is not recovered');
	pg_assert_same(12550, $f['revenue'], 'only the recovered total, minor units');
	pg_assert_same(4, $f['untouched'], 'left carts with no touch, as given');
	pg_assert_same(7, $f['left'], 'left = reached + untouched');
}

function test_cart_recovery_figures_by_channel_and_message()
{
	$from = 1800000000;
	$to = $from + 86400;

	$touches = array(
		cart_recovery_figures_test_touch(1, 'email', 10, $from + 10),
		cart_recovery_figures_test_touch(1, 'whatsapp', 20, $from + 20, array('clicked_at' => $from + 30, 'recovered' => 1, 'completed_total' => 4000)),
		cart_recovery_figures_test_touch(2, 'email', 10, $from + 40, array('restored_at' => $from + 50)),
		cart_recovery_figures_test_touch(2, 'email', 10, $from + 45),
	);

	$f = pg_cart_recovery_figures($touches, $from, $to, 0);

	pg_assert_same(array('email', 'whatsapp'), array_keys($f['channels']), 'channels in a fixed order, only those used');
	pg_assert_same(2, $f['channels']['email']['reached'], 'a cart counts once per channel');
	pg_assert_same(1, $f['channels']['email']['returned'], 'returned by a touch of that channel');
	pg_assert_same(0, $f['channels']['email']['recovered'], 'recovery goes to the touch that took it');
	pg_assert_same(1, $f['channels']['whatsapp']['recovered'], 'the taking touch\'s channel');
	pg_assert_same(4000, $f['channels']['whatsapp']['revenue'], 'its revenue');

	pg_assert_same(array('campaign:10', 'campaign:20'), array_keys($f['messages']), 'messages by source');
	pg_assert_same(2, $f['messages']['campaign:10']['reached'], 'a message reaches a cart once');
	pg_assert_same(3, $f['messages']['campaign:10']['sent'], 'but counts every send');
	pg_assert_same('email', $f['messages']['campaign:10']['channel'], 'the message\'s channel');
	pg_assert_same(1, $f['messages']['campaign:20']['recovered'], 'recovered by its message');
	pg_assert_same(2, $f['left'], 'no untouched cart given');
}

function test_cart_recovery_figures_empty()
{
	$f = pg_cart_recovery_figures(array(), 1800000000, 1800086400, 0);

	pg_assert_same(0, $f['left'], 'nothing left');
	pg_assert_same(array(), $f['channels'], 'no channel');
	pg_assert_same(array(), $f['messages'], 'no message');
}

function test_cart_recovery_rate()
{
	pg_assert_same(null, pg_cart_recovery_rate(0, 0), 'no base: no rate');
	pg_assert_same(33.3, pg_cart_recovery_rate(1, 3), 'one decimal');
	pg_assert_same(100.0, pg_cart_recovery_rate(2, 2), 'whole');
}
