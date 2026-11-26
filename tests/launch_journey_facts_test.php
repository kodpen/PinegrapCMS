<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the launch journey's facts and "Go live": the mail records a
 * sending service signs, each payment way's own test mode (PayPal Express
 * Checkout, the classic Stripe gateway, a driver whose mode was never
 * saved), "Go live" as one statement that a second press cannot repeat, its
 * Site Log lines, and the journey's row written by two requests at once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/launch_journey.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/launch_journey_site.php');

function test_launch_journey_facts_mail_service_dkim_left_out()
{
	$health = array(
		'spf'   => array('state' => 'ok', 'reason' => 'covered'),
		'dkim'  => array('state' => 'unknown', 'reason' => 'service'),
		'dmarc' => array('state' => 'ok', 'reason' => 'enforced'),
	);
	$facts = pg_launch_journey_mail_facts($health);

	pg_assert_same('ok', $facts['mail_spf']);
	pg_assert_false(array_key_exists('mail_dkim', $facts), 'the sending service signs the mail: not the site\'s record to judge');
	pg_assert_same('done', pg_launch_journey_rule('mail', array('mail_ready' => true) + $facts, 'company')['state'], 'the mail step can be done through a sending service');

	$failed = pg_launch_journey_mail_facts(array('spf' => array('state' => 'unknown', 'reason' => 'lookup')));
	pg_assert_same('unknown', $failed['mail_spf'], 'a failed lookup stays worth a look');
	pg_assert_same('unknown', $failed['mail_dkim'], 'a record not answered is not checked');
}

function test_launch_journey_facts_paypal_express_checkout_own_mode()
{
	$way = pg_launch_journey_payment_classic('PayPal Express Checkout', 'Stripe PaymentIntents', array('paypal_mode' => 'sandbox', 'stripe_key' => ''));

	pg_assert_same('test', pg_launch_journey_payment_live($way['driver'], $way['settings'], 'live'), 'PayPal Express Checkout in sandbox is test whatever the card gateway mode');

	$way = pg_launch_journey_payment_classic('PayPal Express Checkout', '', array('paypal_mode' => 'live', 'stripe_key' => ''));
	pg_assert_same('live', pg_launch_journey_payment_live($way['driver'], $way['settings'], 'test'), 'its live mode is live with the card gateway in test');

	$facts = array('payments' => array(
		pg_launch_journey_payment_classic('PayPal Express Checkout', '', array('paypal_mode' => 'sandbox', 'stripe_key' => '')),
		array('driver' => 'paytr', 'settings' => array('mode' => 'live')),
	), 'gateway_mode' => 'live');
	pg_assert_same('blocker', pg_launch_journey_rule('payment_live', $facts, 'shop')['level'], 'two ways, one in test: a blocker');
}

function test_launch_journey_facts_classic_stripe_by_its_key()
{
	$way = pg_launch_journey_payment_classic('Credit/Debit Card', 'Stripe', array('paypal_mode' => '', 'stripe_key' => 'sk_test_abc'));
	pg_assert_same('test', pg_launch_journey_payment_live($way['driver'], $way['settings'], 'live'), 'the classic Stripe gateway with a test key is test');

	$way = pg_launch_journey_payment_classic('Credit/Debit Card', 'Stripe', array('paypal_mode' => '', 'stripe_key' => 'sk_live_abc'));
	pg_assert_same('live', pg_launch_journey_payment_live($way['driver'], $way['settings'], 'test'));

	$way = pg_launch_journey_payment_classic('Credit/Debit Card', 'Authorize.Net', array('paypal_mode' => '', 'stripe_key' => ''));
	pg_assert_same('test', pg_launch_journey_payment_live($way['driver'], $way['settings'], 'test'), 'another classic gateway goes by the gateway mode');
}

function test_launch_journey_facts_driver_mode_default()
{
	$fields = array(
		array('name' => 'client_id', 'default' => ''),
		array('name' => 'mode', 'default' => 'live'),
	);
	$settings = pg_launch_journey_payment_driver_settings(array('values' => array('client_id' => 'x'), 'secrets' => array('client_secret' => 's')), $fields);

	pg_assert_same('live', $settings['mode'], 'a mode never saved is the field default the driver runs on');
	pg_assert_same('s', $settings['client_secret']);
	pg_assert_same('live', pg_launch_journey_payment_live('paypal', $settings, ''));

	$saved = pg_launch_journey_payment_driver_settings(array('values' => array('mode' => 'sandbox'), 'secrets' => array()), $fields);
	pg_assert_same('sandbox', $saved['mode'], 'a saved mode wins');
}

function test_launch_journey_facts_go_live_one_statement()
{
	$closed = "User-agent: *\nDisallow: /";
	$query = pg_launch_journey_golive_query(array('maintenance', 'robots'), $closed, '', 7);

	pg_assert_true(is_array($query), 'both changes are one statement');
	pg_assert_same(1, substr_count($query[0], 'UPDATE config'), 'one UPDATE: both land or neither');
	pg_assert_contains('maintenance_mode', $query[0]);
	pg_assert_contains('additional_robots_content', $query[0]);
	pg_assert_contains('WHERE', $query[0], 'guarded by what was read, so a second press changes nothing');
	pg_assert_true(in_array($closed, $query[1], true), 'the robots text read is the guard');

	pg_assert_same(null, pg_launch_journey_golive_query(array(), '', '', 7), 'nothing to change, no statement');

	$only = pg_launch_journey_golive_query(array('maintenance'), $closed, '', 7);
	pg_assert_false(strpos($only[0], 'additional_robots_content') !== false, 'the robots text is left alone when it is open');
}

function test_launch_journey_facts_go_live_log_once()
{
	$first = pg_launch_journey_golive_log(array('maintenance', 'robots'), true, array());
	pg_assert_same(3, count($first), 'two changes and the went-live line');

	pg_assert_same(array(), pg_launch_journey_golive_log(array(), false, array()), 'a second press changes nothing and logs nothing');

	$left = pg_launch_journey_golive_log(array(), true, array('Legal texts (No personal-data text is set)'));
	pg_assert_same(1, count($left));
	pg_assert_contains('Legal texts', $left[0]);
}

function test_launch_journey_facts_row_written_twice()
{
	// Another request writes the summary link between this request's read
	// and its write: the write is tried again on the fresh row.
	$row = '{"purpose":"shop"}';
	$reads = 0;
	$read = function () use (&$row, &$reads) {
		$reads++;
		if ($reads === 1) {
			$seen = $row;
			$row = '{"purpose":"shop","summary":{"hash":"x"}}';
			return $seen;
		}
		return $row;
	};
	$claim = function ($expected, $value) use (&$row) {
		if ($row !== $expected) {
			return false;
		}
		$row = $value;
		return true;
	};
	$change = function ($json) {
		$data = json_decode($json, true);
		$data['closed'] = true;
		return json_encode($data);
	};

	pg_assert_true(pg_launch_journey_row_write($change, $read, $claim));
	$data = json_decode($row, true);
	pg_assert_true(!empty($data['closed']));
	pg_assert_true(isset($data['summary']), 'the other request\'s link is kept');

	pg_assert_true(pg_launch_journey_row_write(function ($json) { return $json; }, function () { return '{"a":1}'; }, function () { return false; }), 'nothing to change is written');
}

function test_launch_journey_facts_card_stays_while_maintenance_blocks()
{
	$facts = array(
		'https' => true, 'mail_ready' => true, 'kvkk_text' => true, 'consent_enabled' => true,
		'robots_text' => '', 'search_console' => 'connected', 'google_analytics' => 'connected',
		'backup' => 'pass', 'cron' => 'ok', 'team_signed_in' => true, 'broken_links' => 0,
	);

	pg_assert_true(pg_launch_journey_state('company', $facts + array('maintenance' => true), array())['finished'], 'every card step done');
	pg_assert_false(pg_launch_journey_card_closes('company', $facts + array('maintenance' => true), array()), 'maintenance on: the card stays for "Go live"');
	pg_assert_true(pg_launch_journey_card_closes('company', $facts + array('maintenance' => false), array()), 'nothing blocking: it closes');
	pg_assert_false(pg_launch_journey_card_closes('company', array('maintenance' => false) + array_diff_key($facts, array('team_signed_in' => 1)), array()), 'a step not done keeps it');
}

function test_launch_journey_facts_shop_switched_off()
{
	$rule = pg_launch_journey_rule('payment_live', array('shop' => false, 'payments' => array()), 'shop');
	pg_assert_same('todo', $rule['state'], 'a shop switched off is a known answer');
	pg_assert_same('warning', $rule['level']);
	pg_assert_same('shop_off', $rule['reason']);

	$row = array('key' => 'payment_live') + $rule;
	pg_assert_same('The shop is switched off', pg_launch_journey_step_line($row, array()));
	pg_assert_same('#settings/commerce/pgset-store', pg_launch_journey_step_url($row), '"Do it" opens where the shop is switched on');

	$card = pg_launch_journey_card('shop', array('shop' => false, 'payments' => array()), array());
	$keys = array_column($card['rows'], 'url', 'key');
	pg_assert_same('#settings/commerce/pgset-store', $keys['payment_live']);

	pg_assert_same('no_online_payment', pg_launch_journey_rule('payment_live', array('shop' => true, 'payments' => array()), 'shop')['reason'], 'the shop on with no online way stays as it was');
}

function test_launch_journey_facts_bookings_not_set_up()
{
	$rule = pg_launch_journey_rule('booking_hours', array('booking_setup' => false, 'booking_hours' => false), 'clinic');
	pg_assert_same('warning', $rule['level']);
	pg_assert_same('not_set_up', $rule['reason']);

	$row = array('key' => 'booking_hours') + $rule;
	pg_assert_same('Bookings are not set up', pg_launch_journey_step_line($row, array()));
	pg_assert_same('#settings/bookings/pgset-booking-services', pg_launch_journey_step_url($row));

	pg_assert_same('unknown', pg_launch_journey_rule('booking_hours', array(), 'clinic')['state'], 'tables not there yet: unreadable, unknown');
	pg_assert_same('no_hours', pg_launch_journey_rule('booking_hours', array('booking_setup' => true, 'booking_hours' => false), 'clinic')['reason']);
}
