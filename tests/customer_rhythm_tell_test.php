<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what customer rhythm tells (includes/fn/customer_rhythm_tell.php):
 * the settings, the products a reorder event names and its brake, the
 * comparison share, the two payloads, the reminder counts, and how the
 * flows' start box reads the two events.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _crt_test_now()
{
	return 1790000000;
}

function _crt_test_due($product, $due_days_from_now, $quantity = 1)
{
	return array('product_id' => $product, 'quantity' => $quantity, 'last' => _crt_test_now() - (40 * 86400), 'due' => _crt_test_now() + (int) round($due_days_from_now * 86400));
}

function _crt_test_settings($extra = array())
{
	return array_merge(pg_customer_rhythm_settings_normalize(''), $extra);
}

// A row as the pass writes it.
function _crt_test_row($extra = array())
{
	return array_merge(array(
		'customer_key' => 'eabc',
		'contact_id' => 41,
		'stage' => 2,
		'stage_before' => 1,
		'interval_days' => 30,
		'last_order_at' => _crt_test_now() - (34 * 86400) - 600,
		'last_order_id' => 1042,
		'chance' => 80,
		'expected_value' => 96000,
		'updated_at' => _crt_test_now(),
	), $extra);
}

// Defaults: on, 7 days' brake, 3 days' lead, the comparison share off.
function test_customer_rhythm_tell_settings()
{
	pg_assert_same(array('on' => true, 'brake_days' => 7, 'lead_days' => 3, 'share' => false), pg_customer_rhythm_settings_normalize(''));
	pg_assert_same(array('on' => true, 'brake_days' => 7, 'lead_days' => 3, 'share' => false), pg_customer_rhythm_settings_normalize('not json'));

	$settings = pg_customer_rhythm_settings_normalize('{"on":0,"brake_days":"14","lead_days":5,"share":1}');
	pg_assert_same(array('on' => false, 'brake_days' => 14, 'lead_days' => 5, 'share' => true), $settings);

	$settings = pg_customer_rhythm_settings_normalize(array('brake_days' => 0, 'lead_days' => 99));
	pg_assert_same(1, $settings['brake_days'], 'at least a day');
	pg_assert_same(30, $settings['lead_days'], 'at most 30 days');
}

// A due product is named from its window's opening until 7 days after its
// due day; one long past its due day never is, so a first fill does not
// remind every customer of an old purchase.
function test_customer_rhythm_tell_reorder_window()
{
	$settings = _crt_test_settings();
	$due = array(_crt_test_due(1, 2), _crt_test_due(2, -6.5), _crt_test_due(3, -8), _crt_test_due(4, -300));

	$picked = pg_customer_rhythm_reorder_pick($due, 0, _crt_test_now(), $settings);
	pg_assert_same(array(1, 2), array_column($picked, 'product_id'));

	pg_assert_same(array(), pg_customer_rhythm_reorder_pick(array(_crt_test_due(5, 4)), 0, _crt_test_now(), $settings), 'the window opens 3 days before');

	$picked = pg_customer_rhythm_reorder_pick(array(_crt_test_due(5, 4)), 0, _crt_test_now(), _crt_test_settings(array('lead_days' => 5)));
	pg_assert_same(1, count($picked), 'the lead is the setting\'s');
}

// At most one event in the brake's days; a product named before is not
// named again, one whose window opened since is named once the brake ends.
function test_customer_rhythm_tell_reorder_brake()
{
	$settings = _crt_test_settings();
	$now = _crt_test_now();
	$first = _crt_test_due(1, 1);

	$picked = pg_customer_rhythm_reorder_pick(array($first), 0, $now, $settings);
	pg_assert_same(1, count($picked));

	// The next day: the brake holds, and product 1 was named.
	$second = $first;
	$second['product_id'] = 2;
	$second['due'] = $now + (4 * 86400);
	pg_assert_same(array(), pg_customer_rhythm_reorder_pick(array($first, $second), $now, $now + 86400, $settings), 'brake');

	// A week later: product 2, whose window opened after the reminder, alone.
	$picked = pg_customer_rhythm_reorder_pick(array($first, $second), $now, $now + (7 * 86400), $settings);
	pg_assert_same(array(2), array_column($picked, 'product_id'));

	// The brake is the setting's.
	pg_assert_same(array(), pg_customer_rhythm_reorder_pick(array($first, $second), $now, $now + (7 * 86400), _crt_test_settings(array('brake_days' => 14))));
}

// A product bought again since is judged by its new purchase: its new
// window opens after the reminder, so it is named again.
function test_customer_rhythm_tell_reorder_after_a_new_purchase()
{
	$settings = _crt_test_settings();
	$reminded = _crt_test_now() - (40 * 86400);

	pg_assert_same(1, count(pg_customer_rhythm_reorder_pick(array(_crt_test_due(1, 1)), $reminded, _crt_test_now(), $settings)));
}

// The comparison share holds back about one due customer in ten, none when
// it is off; the same customer the same day gets the same answer.
function test_customer_rhythm_tell_comparison_share()
{
	$on = _crt_test_settings(array('share' => true));
	$held = 0;

	for ($i = 0; $i < 5000; $i++) {
		pg_assert_false(pg_customer_rhythm_held_back('e' . $i, _crt_test_now(), _crt_test_settings()), 'off: nobody held back');
		$held += pg_customer_rhythm_held_back('e' . $i, _crt_test_now(), $on) ? 1 : 0;
	}

	pg_assert_true(($held > 380) && ($held < 620), 'about one in ten: ' . $held);
	pg_assert_same(pg_customer_rhythm_held_back('e7', _crt_test_now(), $on), pg_customer_rhythm_held_back('e7', _crt_test_now() + 60, $on));
}

// The stage event names the contact, the stages, the interval, the days,
// the chance, the expected value and the last order; no name or address.
function test_customer_rhythm_tell_stage_payload()
{
	$payload = pg_customer_rhythm_event_payload(_crt_test_row(), '100318');

	pg_assert_same(array('id' => 41, 'stage' => 'due', 'previous_stage' => 'regular', 'interval_days' => 30, 'days_since_last_order' => 34, 'chance' => 80,
		'expected_value' => 96000, 'last_order_id' => 1042, 'last_order_number' => '100318'), $payload);

	$payload = pg_customer_rhythm_event_payload(_crt_test_row(array('stage' => 6, 'stage_before' => 0)), '');
	pg_assert_same('won_back', $payload['stage']);
	pg_assert_same(null, $payload['previous_stage'], 'no stage before');
}

// The reorder event adds each product with its last quantity and due day.
function test_customer_rhythm_tell_reorder_payload()
{
	$due = array(_crt_test_due(7, 1, 2), _crt_test_due(8, 2));
	$payload = pg_customer_rhythm_reorder_payload(_crt_test_row(), '100318', $due, array(7 => array('COFFEE-1KG', 'Coffee beans 1 kg'), 8 => array('FILTER', '')));

	pg_assert_same(41, $payload['id']);
	pg_assert_same(2, count($payload['products']));
	pg_assert_same(array('id' => 7, 'name' => 'COFFEE-1KG', 'short_description' => 'Coffee beans 1 kg', 'quantity' => 2, 'due_at' => gmdate('Y-m-d\TH:i:s\Z', _crt_test_now() + 86400)), $payload['products'][0]);
	pg_assert_same('2 × Coffee beans 1 kg, 1 × FILTER', $payload['products_text']);
}

// Reminder counts per month, the last 24 kept.
function test_customer_rhythm_tell_reminder_counts()
{
	$months = array();
	pg_customer_rhythm_reminder_count($months, gmmktime(12, 0, 0, 11, 20, 2026), false, 3);
	pg_customer_rhythm_reminder_count($months, gmmktime(12, 0, 0, 11, 21, 2026), true);
	pg_assert_same(array('2026-11' => array(3, 1)), $months);

	$merged = pg_customer_rhythm_reminders_merge(array('2026-10' => array(5, 0), '2026-11' => array(1, 1), 'bad' => array(9, 9)), $months);
	pg_assert_same(array('2026-10' => array(5, 0), '2026-11' => array(4, 2)), $merged);

	$old = array();

	for ($m = 1; $m <= 30; $m++) {
		$old[sprintf('%04d-%02d', 2024 + intdiv($m - 1, 12), (($m - 1) % 12) + 1)] = array($m, 0);
	}

	$merged = pg_customer_rhythm_reminders_merge($old, array());
	pg_assert_same(24, count($merged));
	pg_assert_same('2024-07', key($merged), 'the oldest dropped');
}

// The pass hands its due products over, with the setting's lead, and keeps
// the reminder counts in the summary.
function test_customer_rhythm_tell_pass_hand_over()
{
	$store = array('median' => 30.0, 'curve' => array('fixed' => true, 'bands' => array()), 'products' => array(11 => array('days_per_unit' => 10.0, 'pairs' => 12, 'customers' => 6, 'from' => 'product')));
	$time = _crt_test_now() - (86400 * 5);
	$lines = pg_customer_rhythm_pass_lines('eabc', pack('VVVC', 11, 1, $time, 0));
	$orders = pg_customer_rhythm_pass_orders(pack('VVVVC', $time, 5000, 1, 0, 0));

	$made = pg_customer_rhythm_pass_row('eabc', $orders, $lines, null, $store, array(), _crt_test_now());
	pg_assert_same(array(), $made['due'], 'due in 5 days: the window opens in 2');

	$store['lead'] = 5;
	$made = pg_customer_rhythm_pass_row('eabc', $orders, $lines, null, $store, array(), _crt_test_now());
	pg_assert_same(11, $made['due'][0]['product_id']);

	$summary = json_decode(pg_customer_rhythm_summary_encode($store, 123, array('customers' => 1, 'stages' => array(), 'reminders' => array('2026-11' => array(2, 1)))), true);
	pg_assert_same(array('2026-11' => array(2, 1)), $summary['reminders']);
}

// A reminder is written on the row before it is announced, and only with
// the settings on.
function test_customer_rhythm_tell_writes_before_announcing()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/customer_rhythm_tell.php');
	$write = strpos($source, 'UPDATE customer_rhythm SET reminded_at');
	$announce = strpos($source, "pg_announce('customer.reorder_due'");

	pg_assert_true(($write !== false) && ($announce !== false) && ($write < $announce));
	pg_assert_same(array('moves' => 0, 'told' => 0, 'held' => 0, 'months' => array()), pg_customer_rhythm_tell(array('eabc' => array('row' => _crt_test_row(), 'moved' => true, 'due' => array())), array(), _crt_test_settings(array('on' => false)), _crt_test_now()), 'off: nothing told');
}

// The flows' start box: about the contact, narrowed by the stage.
function test_customer_rhythm_tell_flow_start()
{
	foreach (array('customer.rhythm_stage_changed', 'customer.reorder_due') as $event) {
		pg_assert_same(array('contact', 41), ws_events_record_of($event, array('id' => 41, 'stage' => 'late')));
		pg_assert_same('late', ws_events_filter_value($event, array('id' => 41, 'stage' => 'late')));
		pg_assert_true(in_array($event, pg_event_workspace_events(), true));
		pg_assert_true(in_array($event, pg_event_workspace_on_demand(), true), 'kept only while a flow waits for it');
		pg_assert_true(isset(api_webhook_core_events()[$event]));
		pg_assert_true(isset(ws_flow_event_outputs()[$event]));
	}

	$codes = pg_customer_rhythm_stage_codes();
	unset($codes['']);
	pg_assert_same(array_keys($codes), array_keys(array_intersect_key(ws_events_rhythm_stages(), $codes)));
	pg_assert_same(count($codes), count(ws_events_rhythm_stages()));
}
