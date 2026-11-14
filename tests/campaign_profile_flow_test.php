<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the converter of e-mail campaign profiles into flows
 * (includes/fn/campaign_profile_flow.php): the start, Wait and e-mail box
 * each profile becomes, the fields no start fills, chains and their
 * followers, the owner, the limits, the graph a flow checks as valid, a
 * chain that stays whole, the report's lines, and the starting sites'
 * profiles as worked out by hand.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_mail_person.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_mail_campaign.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/campaign_profile_flow.php');

// A profile row as the table keeps it, with what the test changes.
function campaign_profile_flow_test_row($id, $values = array())
{
	return $values + array(
		'id' => $id, 'name' => 'Profile ' . $id, 'enabled' => 1, 'action' => 'order_shipped', 'action_item_id' => 0,
		'subject' => 'Your order', 'format' => 'plain_text', 'body' => 'It is on its way.', 'page_id' => 0,
		'from_name' => '', 'from_email_address' => '', 'reply_email_address' => '', 'bcc_email_address' => '',
		'schedule_time' => '00:00:00', 'schedule_length' => 0, 'schedule_unit' => 'days', 'schedule_period' => 'after',
		'schedule_base' => 'action', 'purpose' => 'transactional', 'created_user_id' => 1,
	);
}

// What the converter is told besides the rows.
function campaign_profile_flow_test_facts($profiles, $stats = array())
{
	$facts = array('texts' => array(), 'stats' => $stats, 'outputs' => array(), 'owners' => array(),
		'limits' => array('delay_max' => 129600, 'max_nodes' => 100, 'starts_hourly' => 60, 'open_runs' => 500));

	foreach ($profiles as $id => $profile) {
		$facts['texts'][$id] = array($profile['subject'], $profile['body']);
		$event = pg_profile_flow_event($profile['action']);

		if ($event !== '') {
			$facts['outputs'][$event] = array_map(function ($output) {
				return $output['key'];
			}, ws_flow_trigger_outputs('event', array('event' => $event)));
			$facts['owners'][$id] = array('id' => 1, 'name' => 'admin', 'changed' => false, 'reason' => '');
		}
	}

	return $facts;
}

function test_campaign_profile_flow_starts()
{
	pg_assert_same('order.shipped', pg_profile_flow_event('order_shipped'));
	pg_assert_same('', pg_profile_flow_event('order_abandoned'), 'no start for an abandoned order');
	pg_assert_same('', pg_profile_flow_event('email_campaign_sent'), 'a follower has none of its own');

	$form = pg_profile_flow_start(array('action' => 'custom_form_submitted', 'action_item_id' => 12));

	pg_assert_same(array('event' => 'form.submitted', 'page_id' => 12, 'status' => '', 'channel_id' => 0), $form['start']);
	pg_assert_same('7', pg_profile_flow_start(array('action' => 'contact_group_joined', 'action_item_id' => 7))['start']['status'], 'the group as the filter');

	$calendar = pg_profile_flow_start(array('action' => 'calendar_event_reserved', 'action_item_id' => 5));

	pg_assert_same(array('type' => 'value', 'ref' => '{{trigger.record_id}}', 'op' => 'eq', 'value' => '5'), $calendar['condition']['rule'], 'a Condition on the event');
	pg_assert_same(array(), ws_flow_condition_problems(ws_flow_condition_normalize($calendar['condition']), 'C'), 'one the Condition box takes');
}

function test_campaign_profile_flow_waits()
{
	$wait = function ($values) {
		return pg_profile_flow_wait($values + array('schedule_length' => 0, 'schedule_unit' => 'days', 'schedule_time' => '00:00:00', 'schedule_period' => 'after', 'schedule_base' => 'action'), 129600);
	};

	pg_assert_same(array('ok' => true, 'config' => null, 'minutes' => 0, 'reason' => ''), $wait(array()), 'at once, any time: no Wait box');

	$days = $wait(array('schedule_length' => 3, 'schedule_time' => '10:00:00'));

	pg_assert_same(array('mode' => 'for', 'at' => '', 'amount' => 3, 'unit' => 'days', 'time' => '10:00', 'passed' => 'go'), $days['config']);
	pg_assert_same($days['config'], ws_flow_delay_normalize($days['config']), 'kept as the Wait box keeps it');
	pg_assert_same(array(), ws_flow_delay_problems($days['config'], 'W'), 'and valid');
	pg_assert_same(3 * 1440 + 1440, $days['minutes'], 'a send time may add a day');
	pg_assert_same('00:01', $wait(array('schedule_time' => '00:01:00'))['config']['time'], 'a chosen midnight as stored');
	pg_assert_same(5 * 60, $wait(array('schedule_length' => 5, 'schedule_unit' => 'hours'))['minutes']);
	pg_assert_false($wait(array('schedule_length' => 91))['ok'], 'longer than the Wait box may');
	pg_assert_false($wait(array('schedule_length' => 1, 'schedule_period' => 'before'))['ok'], 'before its action');

	$calendar = array('schedule_base' => 'calendar_event_start_time', 'schedule_period' => 'before');
	$until = $wait($calendar + array('schedule_length' => 2));

	pg_assert_same(array('mode' => 'until', 'at' => '{{trigger.start_at}}', 'amount' => 2, 'unit' => 'days', 'time' => '', 'passed' => 'end'), $until['config'], 'until the start less two days; a passed moment ends the run');
	pg_assert_same(array(), ws_flow_delay_problems(ws_flow_delay_normalize($until['config']), 'W'));
	pg_assert_false($wait($calendar + array('schedule_length' => 2, 'schedule_time' => '09:00:00'))['ok'], 'a time of day before the start moves later');
	pg_assert_false($wait(array('schedule_base' => 'calendar_event_start_time', 'schedule_length' => 1))['ok'], 'after the start');
	pg_assert_true($wait(array('schedule_base' => 'calendar_event_start_time', 'schedule_time' => '18:00:00'))['ok'], 'at the start, then a time of day');
}

function test_campaign_profile_flow_email()
{
	$plain = pg_profile_flow_email(campaign_profile_flow_test_row(1, array('purpose' => 'commercial', 'from_name' => 'Shop', 'from_email_address' => 'info@example.com', 'bcc_email_address' => 'copy@example.com')));

	pg_assert_same(array('record_person', 'marketing', 'It is on its way.', 0), array($plain['recipient'], $plain['purpose'], $plain['body'], $plain['page_id']));
	pg_assert_same(array('Shop', 'info@example.com', '', 'copy@example.com'), array($plain['from_name'], $plain['from_email'], $plain['reply_to'], $plain['bcc']), 'the sender carried');
	pg_assert_same(array(), ws_step_config_problems('email', ws_step_config_normalize('email', $plain), 'Mail'), 'a box the flow takes');

	$page = pg_profile_flow_email(campaign_profile_flow_test_row(1, array('format' => 'html', 'page_id' => 40, 'body' => 'ignored')));

	pg_assert_same(array('info', 40, ''), array($page['purpose'], $page['page_id'], $page['body']));
}

function test_campaign_profile_flow_fields()
{
	pg_assert_same(array('name', 'order_number', 'x'), pg_profile_flow_fields_used(array('Hi [[^^name^^, ||]]', '^^order_number^^ ^^name^^', '<p>^^x^^%%d%%</p>')));
	pg_assert_same(array('order_reference_code'), pg_profile_flow_fields_missing(array('name', 'order_number', 'order_reference_code'), array('order_number')), 'name comes from the contact');
}

function test_campaign_profile_flow_chains()
{
	$profiles = array(
		1 => array('action' => 'order_shipped', 'action_item_id' => 0),
		2 => array('action' => 'email_campaign_sent', 'action_item_id' => 1),
		3 => array('action' => 'email_campaign_sent', 'action_item_id' => 2),
		4 => array('action' => 'email_campaign_sent', 'action_item_id' => 1),
		5 => array('action' => 'email_campaign_sent', 'action_item_id' => 99),
		6 => array('action' => 'email_campaign_sent', 'action_item_id' => 7),
		7 => array('action' => 'email_campaign_sent', 'action_item_id' => 6),
	);
	$split = pg_profile_flow_chains($profiles);

	pg_assert_same(array(1 => array(array(1, 0), array(2, 1), array(4, 1), array(3, 2))), $split['chains'], 'a tree, each after its leader');
	pg_assert_same(array(5, 6, 7), $split['orphans'], 'a gone leader and a ring send nothing');
}

function test_campaign_profile_flow_owner()
{
	$admin = array('id' => 1, 'name' => 'admin');

	pg_assert_same(array('id' => 4, 'name' => 'ayse', 'changed' => false, 'reason' => ''), pg_profile_flow_owner(array('id' => 4, 'name' => 'ayse', 'staff' => true, 'right' => true), $admin));
	pg_assert_same(1, pg_profile_flow_owner(array('id' => 4, 'name' => 'ayse', 'staff' => true, 'right' => false), $admin)['id'], 'without the start\'s right');
	pg_assert_same(1, pg_profile_flow_owner(array('id' => 4, 'name' => 'ayse', 'staff' => false, 'right' => true), $admin)['id'], 'off the staff');
	pg_assert_true(pg_profile_flow_owner(null, $admin)['changed'], 'an account gone');
}

function test_campaign_profile_flow_limits()
{
	$limits = array('starts_hourly' => 60, 'open_runs' => 500);

	pg_assert_same('', pg_profile_flow_limit_reason(array('made' => 300, 'busiest' => 48), 1440, $limits), '80% is still in');
	pg_assert_true(pg_profile_flow_limit_reason(array('made' => 300, 'busiest' => 49), 1440, $limits) !== '', 'over 80% of the hourly starts');
	pg_assert_same(70, pg_profile_flow_open_runs(300, 7 * 1440), 'a week of starts held open');
	pg_assert_true(pg_profile_flow_limit_reason(array('made' => 3000, 'busiest' => 10), 7 * 1440, $limits) !== '', 'over 80% of the open runs');
}

// A chain becomes one valid flow; one profile that stays keeps the chain.
function test_campaign_profile_flow_convert()
{
	$profiles = array(
		1 => campaign_profile_flow_test_row(1, array('name' => 'Shipped', 'enabled' => 0, 'subject' => 'Order ^^order_number^^ shipped')),
		2 => campaign_profile_flow_test_row(2, array('name' => 'How was it', 'action' => 'email_campaign_sent', 'action_item_id' => 1, 'schedule_length' => 7, 'schedule_time' => '10:00:00')),
		3 => campaign_profile_flow_test_row(3, array('name' => 'Booked', 'action' => 'calendar_event_reserved', 'action_item_id' => 5, 'schedule_base' => 'calendar_event_start_time', 'schedule_period' => 'before', 'schedule_length' => 1)),
		4 => campaign_profile_flow_test_row(4, array('name' => 'Left cart', 'action' => 'order_abandoned')),
		5 => campaign_profile_flow_test_row(5, array('name' => 'Cart again', 'action' => 'email_campaign_sent', 'action_item_id' => 4)),
		6 => campaign_profile_flow_test_row(6, array('name' => 'Ghost', 'action' => 'email_campaign_sent', 'action_item_id' => 99)),
	);
	$plan = pg_profile_flow_convert($profiles, campaign_profile_flow_test_facts($profiles));

	pg_assert_same(array(1, 3), array_keys($plan['flows']));
	pg_assert_same(array(1, 2), $plan['flows'][1]['members']);
	pg_assert_true($plan['flows'][1]['paused'], 'a disabled head becomes a paused flow');

	foreach ($plan['flows'] as $flow) {
		pg_assert_same(array(), ws_flow_graph_check($flow['graph']), 'a graph the flow takes: ' . $flow['name']);
	}

	pg_assert_same(array('start', 'mail_1', 'sent_2', 'wait_2', 'mail_2'), array_column($plan['flows'][1]['graph']['nodes'], 'id'));
	pg_assert_same('{{node.mail_1.sent}}', $plan['flows'][1]['graph']['nodes'][2]['config']['rule']['ref'], 'the follower after the e-mail sent');
	pg_assert_same(array('start', 'calendar', 'wait_3', 'mail_3'), array_column($plan['flows'][3]['graph']['nodes'], 'id'));

	pg_assert_same(array(6, 4, 5), array_keys($plan['left']));
	pg_assert_contains('No start box', $plan['left'][4]);
	pg_assert_contains('Left cart', $plan['left'][5], 'a follower stays with its chain');

	$lines = implode("\n", pg_profile_flow_report_lines($plan, $profiles, array(1 => array('made' => 4, 'busiest' => 1, 'waiting' => 2))));

	pg_assert_contains('“Shipped” (off) becomes a flow, paused', $lines);
	pg_assert_contains('Wait 7 days · then at 10:00', $lines);
	pg_assert_contains('“Left cart” (order_abandoned, on) stays a profile', $lines);
	pg_assert_contains('2 ready or paused stay with the campaign job', $lines);
}

// A field the start does not fill keeps the whole chain.
function test_campaign_profile_flow_unfilled_field()
{
	$profiles = array(1 => campaign_profile_flow_test_row(1, array('body' => 'Code ^^order_reference_code^^')),
		2 => campaign_profile_flow_test_row(2, array('action' => 'email_campaign_sent', 'action_item_id' => 1)));
	$plan = pg_profile_flow_convert($profiles, campaign_profile_flow_test_facts($profiles));

	pg_assert_same(array(), $plan['flows']);
	pg_assert_contains('^^order_reference_code^^', $plan['left'][1]);
	pg_assert_contains('Profile 1', $plan['left'][2]);
}

// The starting sites' profiles as MA9a worked them out by hand.
function test_campaign_profile_flow_starting_sites()
{
	$sender = array('from_email_address' => 'info@example.com', 'reply_email_address' => 'info@example.com', 'bcc_email_address' => 'info@example.com', 'format' => 'html', 'body' => '', 'enabled' => 0);
	$profiles = array(
		1 => campaign_profile_flow_test_row(1, $sender + array('name' => 'Sipariş Yardımı', 'action' => 'order_abandoned', 'page_id' => 10, 'schedule_time' => '10:00:00', 'purpose' => 'commercial')),
		2 => campaign_profile_flow_test_row(2, $sender + array('name' => 'Kargo Yola Çıktı', 'action' => 'order_shipped', 'page_id' => 11)),
	);
	$facts = campaign_profile_flow_test_facts($profiles);
	$facts['texts'][2][] = '<p>Merhaba [[^^name^^, ||]]</p>';
	$plan = pg_profile_flow_convert($profiles, $facts);

	pg_assert_same(array(2), array_keys($plan['flows']));
	pg_assert_true($plan['flows'][2]['paused']);

	$mail = $plan['flows'][2]['graph']['nodes'][1];

	pg_assert_same(array('email', 11, 'info', 'info@example.com'), array($mail['kind'], $mail['config']['page_id'], $mail['config']['purpose'], $mail['config']['bcc']));
	pg_assert_same(array(1), array_keys($plan['left']));
}
