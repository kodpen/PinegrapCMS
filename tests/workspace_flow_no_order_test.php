<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the start box "a customer has not ordered for N days": its
 * settings and hourly rule, when a customer crosses the threshold, the start
 * key that fires once per customer and last order, a new order that resets
 * it, the hourly start budget, the values it gives {{trigger.<field>}} and
 * the rights it needs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_member.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_no_order.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

// The last orders of three customers, as ws_flow_no_order_last() keeps
// them: contact 5 ordered 40 days ago, contact 6 ten days ago, contact 7
// 31 days ago.
function ws_flow_no_order_test_last($now)
{
	return ws_flow_no_order_last(array(
		array('contact_id' => 5, 'id' => 50, 'order_number' => '1050', 'order_date' => $now - 40 * 86400),
		array('contact_id' => 5, 'id' => 41, 'order_number' => '1041', 'order_date' => $now - 90 * 86400),
		array('contact_id' => 6, 'id' => 60, 'order_number' => '1060', 'order_date' => $now - 10 * 86400),
		array('contact_id' => 7, 'id' => 70, 'order_number' => '1070', 'order_date' => $now - 31 * 86400),
	));
}

function test_workspace_flow_no_order_is_a_start()
{
	pg_assert_true(isset(ws_flow_anchor_kinds()['no_order']), 'an anchor kind');
	pg_assert_same('at', ws_flow_anchor_kinds()['no_order']['rule'], 'its anchor runs on a time rule');
	pg_assert_true(ws_flow_kinds()['no_order']['trigger'], 'a start box');
	pg_assert_same(array('next'), ws_flow_kinds()['no_order']['outcomes']);
	pg_assert_same('event', ws_flow_start_names()['no_order']);
}

function test_workspace_flow_no_order_settings_and_rule()
{
	pg_assert_same(array('days' => WS_FLOW_NO_ORDER_DAYS), ws_flow_anchor_normalize('no_order', array()), 'a new box takes the default');
	pg_assert_same(array('days' => 45), ws_flow_anchor_normalize('no_order', array('days' => '45', 'extra' => 1)));
	pg_assert_same(array('days' => 1), ws_flow_anchor_normalize('no_order', array('days' => 0)), 'at least one day');
	pg_assert_same(array('days' => WS_FLOW_NO_ORDER_DAYS_MAX), ws_flow_anchor_normalize('no_order', array('days' => 99999)));

	$now = mktime(14, 37, 20, 10, 30, 2026);
	$rule = ws_flow_no_order_rule($now);

	pg_assert_same(array('type' => 'at', 'date' => '2026-10-30', 'time' => '14:37', 'repeat' => 'hourly', 'every' => 1), $rule, 'scanned every hour from the publish');
	pg_assert_true(ws_scheduled_next_run($rule, $now) > $now, 'the rule comes again');
	pg_assert_same('at', ws_flow_anchor_rule('no_order', array('days' => 30))['type']);
}

function test_workspace_flow_no_order_threshold_crossing_fires_once()
{
	$now = mktime(12, 0, 0, 10, 30, 2026);
	$last = ws_flow_no_order_test_last($now);

	pg_assert_same(50, (int) $last[5]['id'], 'the latest order of a customer');
	pg_assert_true(ws_flow_no_order_crossed($now - 40 * 86400, 30, $now));
	pg_assert_false(ws_flow_no_order_crossed($now - 10 * 86400, 30, $now), 'not yet N days');
	pg_assert_false(ws_flow_no_order_crossed(0, 30, $now), 'a customer who never ordered');
	pg_assert_false(ws_flow_no_order_crossed($now - 30 * 86400 - WS_FLOW_NO_ORDER_WINDOW - 1, 30, $now), 'crossed before the window');

	$picked = ws_flow_no_order_pick($last, array(), array(), 30, $now, 10);

	pg_assert_same(array(5, 7), array_map('intval', array_column($picked, 'contact_id')), 'the longest waiting first; 6 has not crossed');
	pg_assert_same('noorder:5:50', ws_flow_no_order_start_key(5, 50));
	pg_assert_same('noorder:5:50', $picked[0]['start_key']);
	pg_assert_same(40, $picked[0]['days_since']);
}

function test_workspace_flow_no_order_second_scan_does_not_fire_again()
{
	$now = mktime(12, 0, 0, 10, 30, 2026);
	$last = ws_flow_no_order_test_last($now);
	$started = array('noorder:5:50', 'noorder:7:70');

	pg_assert_same(array(), ws_flow_no_order_pick($last, array(), $started, 30, $now + 3600, 10), 'the same state an hour later starts nothing');
	pg_assert_same(array(6), array_map('intval', array_column(ws_flow_no_order_pick($last, array(), $started, 30, $now + 20 * 86400, 10), 'contact_id')),
		'days later only the customer who crossed meanwhile');
}

function test_workspace_flow_no_order_new_order_resets()
{
	$now = mktime(12, 0, 0, 10, 30, 2026);
	$last = ws_flow_no_order_test_last($now);

	pg_assert_same(array(7), array_map('intval', array_column(ws_flow_no_order_pick($last, array(5), array(), 30, $now, 10), 'contact_id')),
		'a customer who ordered since the threshold is not started');

	// Contact 5 orders again (order 80); once that order is N days old the
	// customer crosses again, under a key of its own.
	$later = $now + 31 * 86400;
	$again = ws_flow_no_order_last(array(array('contact_id' => 5, 'id' => 80, 'order_number' => '1080', 'order_date' => $now)));
	$picked = ws_flow_no_order_pick($again, array(), array('noorder:5:50'), 30, $later, 10);

	pg_assert_same(1, count($picked));
	pg_assert_same('noorder:5:80', $picked[0]['start_key'], 'the new last order makes a new key');
}

function test_workspace_flow_no_order_hourly_budget()
{
	$now = mktime(12, 0, 0, 10, 30, 2026);
	$last = ws_flow_no_order_test_last($now);

	pg_assert_same(1, count(ws_flow_no_order_pick($last, array(), array(), 30, $now, 1)), 'no more than the budget');
	pg_assert_same(array(), ws_flow_no_order_pick($last, array(), array(), 30, $now, 0));

	pg_assert_same(WS_FLOW_STARTS_HOURLY, ws_flow_no_order_budget(0, 0, null));
	pg_assert_same(1, ws_flow_no_order_budget(0, WS_FLOW_STARTS_HOURLY - 1, null), 'the flow\'s starts of the hour count');
	pg_assert_same(0, ws_flow_no_order_budget(0, WS_FLOW_STARTS_HOURLY, null));
	pg_assert_same(2, ws_flow_no_order_budget(WS_FLOW_MAX_OPEN_RUNS - 2, 0, null), 'and its open runs');
	pg_assert_same(WS_FLOW_MEMBER_STARTS_HOURLY - 5, ws_flow_no_order_budget(0, 0, 5), 'a role-3 owner\'s starts of all their flows');
	pg_assert_same(0, ws_flow_no_order_budget(0, 0, WS_FLOW_MEMBER_STARTS_HOURLY + 3));
}

function test_workspace_flow_no_order_outputs()
{
	$keys = array_column(ws_flow_trigger_outputs('no_order'), 'key');

	pg_assert_same(array('contact_id', 'name', 'email', 'last_order_id', 'last_order_number', 'last_order_date', 'days_since'), $keys);
	pg_assert_same($keys, ws_flow_trigger_fields(array(array('id' => 'n', 'kind' => 'no_order', 'config' => array('days' => 30))), ws_flow_kinds()));

	$context = array('trigger' => 'no_order', 'customer' => array('contact_id' => 5, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com',
		'last_order_id' => 50, 'last_order_number' => '1050', 'last_order_date' => '2026-09-20', 'days_since' => 40));
	$values = ws_step_trigger_values($context, true);

	pg_assert_same('5', $values['contact_id']);
	pg_assert_same('Ada Lovelace', $values['name']);
	pg_assert_same('ada@example.com', $values['email']);
	pg_assert_same('1050', $values['last_order_number']);
	pg_assert_same('40', $values['days_since']);

	$hidden = ws_step_trigger_values($context, false);

	pg_assert_same('', $hidden['name'], 'nothing of the customer to an owner who lost the rights');
	pg_assert_same(count($keys), count($hidden), 'every declared field is written');
}

function test_workspace_flow_no_order_rights()
{
	pg_assert_same('', ws_flow_no_order_refusal(array('role' => 2, 'ecommerce' => true, 'contacts' => true)));
	pg_assert_true(ws_flow_no_order_refusal(array('role' => 3, 'ecommerce' => true, 'contacts' => false)) !== '', 'no contacts right');
	pg_assert_true(ws_flow_no_order_refusal(array('role' => 3, 'ecommerce' => false, 'contacts' => true)) !== '', 'no orders right');

	$checked = ws_flow_anchor_check(array('id' => 9, 'role' => 3, 'ecommerce' => false, 'contacts' => true), 'no_order', array('days' => 30));

	pg_assert_false($checked['ok'], 'publish refused without the rights');
	pg_assert_same(ws_flow_no_order_refusal(array('ecommerce' => false)), $checked['error']);

	pg_assert_true(in_array('no_order', ws_flow_member_kinds(), true), 'a role-3 member with both rights may use it');
}

function test_workspace_flow_no_order_scan_keys_and_rights_in_source()
{
	$function = new ReflectionFunction('ws_flow_no_order_scan');
	$source = implode('', array_slice(file($function->getFileName()), $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	pg_assert_contains('ws_flow_no_order_refusal(', $source, 'the rights asked at each scan');
	pg_assert_contains('ws_viewer_contacts_open(', $source, 'only contacts the owner may open');
	pg_assert_contains('ws_flow_no_order_budget(', $source);

	$start = new ReflectionFunction('ws_flow_no_order_start');
	$insert = implode('', array_slice(file($start->getFileName()), $start->getStartLine() - 1, $start->getEndLine() - $start->getStartLine() + 1));

	pg_assert_contains("'contact'", $insert, 'the run is about the customer');
	pg_assert_contains("\$pick['start_key']", $insert, 'under the customer\'s and last order\'s key');

	$anchor = new ReflectionFunction('ws_flow_anchor_start');
	$body = implode('', array_slice(file($anchor->getFileName()), $anchor->getStartLine() - 1, $anchor->getEndLine() - $anchor->getStartLine() + 1));

	pg_assert_contains('ws_flow_no_order_scan(', $body, 'the hourly anchor run scans');
}
