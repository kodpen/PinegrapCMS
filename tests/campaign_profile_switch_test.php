<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for e-mail campaign profiles switched to their flows
 * (includes/fn/campaign_profile_switch.php): the window around the switch
 * second, the start box keeping the profile and the second as far as the
 * start rule, a chain already switched left out of the next run, and the
 * followers turned off once nothing above them waits.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/campaign_profile_switch.php');

// A profile row with what the test changes.
function campaign_profile_switch_test_row($id, $values = array())
{
	return $values + array(
		'id' => $id, 'name' => 'Profile ' . $id, 'enabled' => 1, 'action' => 'order_shipped', 'action_item_id' => 0,
		'subject' => 'Your order', 'format' => 'plain_text', 'body' => 'It is on its way.', 'page_id' => 0,
		'from_name' => '', 'from_email_address' => '', 'reply_email_address' => '', 'bcc_email_address' => '',
		'schedule_time' => '00:00:00', 'schedule_length' => 0, 'schedule_unit' => 'days', 'schedule_period' => 'after',
		'schedule_base' => 'action', 'purpose' => 'transactional', 'created_user_id' => 1,
	);
}

// The decided window: before S - 60 never, after S + 60 always, in between
// only when the profile did not mail the person.
function test_campaign_profile_switch_window()
{
	$s = 1000000;

	pg_assert_true(!pg_profile_flow_switch_starts($s - 61, $s, false), 'before the window the profile served it');
	pg_assert_true(pg_profile_flow_switch_starts($s + 61, $s, true), 'after the window the flow always starts');
	pg_assert_true(pg_profile_flow_switch_starts($s - 60, $s, false), 'at the window\'s edge, not mailed: the flow starts');
	pg_assert_true(!pg_profile_flow_switch_starts($s + 60, $s, true), 'at the far edge, mailed: no second mail');
	pg_assert_true(!pg_profile_flow_switch_starts($s, $s, true), 'at the second, mailed by the profile');
	pg_assert_true(pg_profile_flow_switch_starts($s, $s, false), 'at the second, not mailed');
}

// The profile and the second reach the start rule; other event boxes carry
// neither.
function test_campaign_profile_switch_start_rule()
{
	$plain = ws_flow_anchor_normalize('event', array('event' => 'order.shipped'));

	pg_assert_true(!array_key_exists('profile_id', $plain) && !array_key_exists('switched_at', $plain), 'a drawn flow keeps its rule');

	$graph = array('nodes' => array(array('id' => 'start', 'kind' => 'event', 'config' => array('event' => 'order.shipped', 'page_id' => 0, 'status' => '', 'channel_id' => 0))));
	$graph = pg_profile_flow_graph_switched($graph, 7, 1234);
	$config = ws_flow_anchor_normalize('event', $graph['nodes'][0]['config']);
	$rule = ws_flow_anchor_rule('event', $config);

	pg_assert_same(7, $rule['profile_id']);
	pg_assert_same(1234, $rule['switched_at']);
	pg_assert_same(7, pg_profile_flow_graph_profile($graph));
	pg_assert_same(0, pg_profile_flow_graph_profile(array('nodes' => array(array('kind' => 'event', 'config' => $plain)))));
	pg_assert_true(!array_key_exists('profile_id', ws_flow_anchor_normalize('event', array('event' => 'order.shipped', 'profile_id' => 7))), 'a profile without its second carries nothing');
}

// A second run leaves a switched chain out, followers included.
function test_campaign_profile_switch_twice()
{
	$profiles = array(
		1 => campaign_profile_switch_test_row(1, array('enabled' => 0)),
		2 => campaign_profile_switch_test_row(2, array('action' => 'email_campaign_sent', 'action_item_id' => 1)),
	);
	$facts = array('texts' => array(1 => array('a'), 2 => array('b')), 'stats' => array(), 'owners' => array(),
		'outputs' => array('order.shipped' => array()), 'switched' => array(1 => 40),
		'limits' => array('delay_max' => 129600, 'max_nodes' => 100, 'starts_hourly' => 60, 'open_runs' => 500));
	$plan = pg_profile_flow_convert($profiles, $facts);

	pg_assert_same(array(), $plan['flows']);
	pg_assert_same(array(), $plan['left'], 'its follower is not left behind');
	pg_assert_same(array(1 => 40), $plan['done']);
	pg_assert_contains('became flow #40', implode("\n", pg_profile_flow_report_lines($plan, $profiles, array())));
}

// A follower goes off once no campaign above it waits; outside a switched
// chain nothing is touched.
function test_campaign_profile_switch_followers_done()
{
	$profiles = array(
		1 => array('action' => 'order_shipped', 'action_item_id' => 0, 'enabled' => 0),
		2 => array('action' => 'email_campaign_sent', 'action_item_id' => 1, 'enabled' => 1),
		3 => array('action' => 'email_campaign_sent', 'action_item_id' => 2, 'enabled' => 1),
		4 => array('action' => 'order_shipped', 'action_item_id' => 0, 'enabled' => 1),
		5 => array('action' => 'email_campaign_sent', 'action_item_id' => 4, 'enabled' => 1),
		6 => array('action' => 'email_campaign_sent', 'action_item_id' => 7, 'enabled' => 1),
		7 => array('action' => 'email_campaign_sent', 'action_item_id' => 6, 'enabled' => 1),
	);

	pg_assert_same(array(2, 3), pg_profile_flow_followers_done($profiles, array(1), array()), 'nothing waits: both off');
	pg_assert_same(array(), pg_profile_flow_followers_done($profiles, array(1), array(1 => 2)), 'the head\'s campaigns still feed 2, and through it 3');
	pg_assert_same(array(2), pg_profile_flow_followers_done($profiles, array(1), array(2 => 1)), 'campaigns of 2 still feed 3; 2 has nothing above it left');
	pg_assert_same(array(), pg_profile_flow_followers_done($profiles, array(), array()), 'no chain switched');
}

// The file opens with an exit, holds the state as JSON, and anything else is
// no state.
function test_campaign_profile_switch_file_text()
{
	$due = pg_profile_flow_switch_due(42, 1000);
	$text = pg_profile_flow_switch_guard() . json_encode($due);

	pg_assert_same(0, strpos($text, '<?php exit; ?>'));
	pg_assert_same($due, pg_profile_flow_switch_parse($text));
	pg_assert_same(null, pg_profile_flow_switch_parse(json_encode($due)), 'without the guard');
	pg_assert_same(null, pg_profile_flow_switch_parse(pg_profile_flow_switch_guard() . '{"status":"lost"}'), 'an unknown status');
	pg_assert_same(42, $due['ceiling']);
	pg_assert_same('due', $due['status']);
}

// A profile made after the update stays a profile, and keeps its chain.
function test_campaign_profile_switch_ceiling()
{
	$profiles = array(
		1 => campaign_profile_switch_test_row(1),
		2 => campaign_profile_switch_test_row(2, array('action' => 'email_campaign_sent', 'action_item_id' => 1)),
		3 => campaign_profile_switch_test_row(3, array('name' => 'Older')),
	);
	$facts = array('texts' => array(1 => array('a'), 2 => array('b'), 3 => array('c')), 'stats' => array(),
		'owners' => array(1 => array('id' => 1, 'name' => 'admin', 'changed' => false, 'reason' => ''), 3 => array('id' => 1, 'name' => 'admin', 'changed' => false, 'reason' => '')),
		'outputs' => array('order.shipped' => array()), 'ceiling' => 1,
		'limits' => array('delay_max' => 129600, 'max_nodes' => 100, 'starts_hourly' => 60, 'open_runs' => 500));
	$plan = pg_profile_flow_convert($profiles, $facts);

	pg_assert_same(array(), $plan['flows'], 'the chain holds a newer profile, and 3 is newer itself');
	pg_assert_contains('made after the update', pg_profile_flow_reason_text($plan['left'][2]));
	pg_assert_contains('Profile 2', pg_profile_flow_reason_text($plan['left'][1]), 'its head stays with it');
	pg_assert_contains('made after the update', pg_profile_flow_reason_text($plan['left'][3]));
}

// A chain whose flow could not be published is left with the reason.
function test_campaign_profile_switch_final()
{
	$plan = array('flows' => array(1 => array('members' => array(1, 2)), 5 => array('members' => array(5))), 'left' => array(), 'done' => array(7 => 70));
	$final = pg_profile_flow_switch_final($plan, array(1 => 'No owner.'), array(1 => array('name' => 'Shipped')));

	pg_assert_same(array(5), array_keys($final['flows']));
	pg_assert_same('No owner.', $final['left'][1]);
	pg_assert_contains('Shipped', pg_profile_flow_reason_text($final['left'][2]));
	pg_assert_same(array(7 => 70), $final['done']);
}

// The screen's notice: under way, then the report with the flows linked.
function test_campaign_profile_switch_notice()
{
	pg_assert_same('', pg_profile_flow_switch_notice(null, ''));

	$due = pg_profile_flow_switch_due(9, 1000);
	pg_assert_contains('Profiles are being turned into flows', pg_profile_flow_switch_notice($due, ''));

	$done = array('status' => 'done', 'done_at' => 2000, 'switched' => array(1 => 70), 'failed' => array(), 'report' => array(
		'plan' => array('flows' => array(), 'left' => array(4 => 'No start box covers its action yet.'), 'orphans' => array(), 'done' => array(1 => 70)),
		'profiles' => array(1 => array('name' => 'Shipped <b>', 'action' => 'order_shipped', 'enabled' => 0, 'purpose' => 'transactional'),
			4 => array('name' => 'Left cart', 'action' => 'order_abandoned', 'enabled' => 1, 'purpose' => 'commercial')),
		'stats' => array()));
	$html = pg_profile_flow_switch_notice($done, '14.11.2026', array('order_abandoned' => 'Order Abandoned'));

	pg_assert_contains('Profiles turned into flows on 14.11.2026', $html);
	pg_assert_contains('id="profile-flows" class="alert theme-success', $html, 'the panel\'s notice');
	pg_assert_contains('<a href="workspace_flows.php?flow=70">Shipped &lt;b&gt;</a>', $html, 'linked and escaped');
	pg_assert_contains('No start box covers its action yet.', $html);
	pg_assert_contains('Flows made: 1; profiles left: 1', $html);
	pg_assert_contains('<a href="edit_email_campaign_profile.php?id=4">“Left cart”</a> (Order Abandoned, on)', $html, 'a profile left leads to the profile, its action by the screen\'s name');
	pg_assert_true(strpos($html, 'Last 30 days') === false, 'no line of zeros');
}

// The bell line is the administrators' alone.
function test_campaign_profile_switch_bell()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

	$row = array('action' => 'profile_flows', 'title' => '3');

	pg_assert_true(pg_notification_visible($row, array('id' => 1, 'role' => '0')), 'an administrator');
	pg_assert_true(!pg_notification_visible($row, array('id' => 2, 'role' => 1)), 'a designer');
	pg_assert_contains('view_email_campaign_profiles.php', pg_notification_display($row)['url']);
}

// A Send an e-mail box to the record's person is checked at publish with a
// stand-in address; the address a run puts in is kept. Before, every such
// flow was refused "Write the address to send it to."
function test_campaign_profile_switch_record_person_check()
{
	pg_assert_same('person@example.com', ws_step_check_stand_in('email', array('recipient' => 'record_person', 'to' => ''))['to']);
	pg_assert_same('ayse@example.org', ws_step_check_stand_in('email', array('recipient' => 'record_person', 'to' => 'ayse@example.org'))['to'], 'the run\'s address is kept');
	pg_assert_same('', ws_step_check_stand_in('email', array('recipient' => 'typed', 'to' => ''))['to'], 'a typed box still needs its address');
	pg_assert_same(array('recipient' => 'record_person'), ws_step_check_stand_in('sms', array('recipient' => 'record_person')), 'other boxes untouched');
	pg_assert_contains('ws_step_check_stand_in($kind, $config)', file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php'), 'ws_step_check() asks it');
}

// The checked event rule the anchor keeps carries the profile and the
// second; before, ws_scheduled_rule_input() dropped them and the window
// never applied.
function test_campaign_profile_switch_checked_rule()
{
	pg_assert_same(array('profile_id' => 7, 'switched_at' => 1234), ws_scheduled_event_switch(array('type' => 'event', 'event' => 'order.shipped', 'profile_id' => 7, 'switched_at' => 1234)));
	pg_assert_same(array(), ws_scheduled_event_switch(array('type' => 'event', 'event' => 'order.shipped')), 'a drawn flow');
	pg_assert_same(array(), ws_scheduled_event_switch(array('profile_id' => 7)), 'without its second');
	pg_assert_contains("'rule' => \$out + ws_scheduled_event_switch(\$rule)", file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php'), 'the event branch of ws_scheduled_rule_input() keeps them');
}

// A reason is kept as the lang() spec it is drawn from, so a report the job
// stored is read in the reader's language; a plain sentence is shown as is.
function test_campaign_profile_switch_reason_codes()
{
	$profiles = array(1 => campaign_profile_switch_test_row(1, array('action' => 'order_abandoned')));
	$facts = array('texts' => array(1 => array('a')), 'stats' => array(), 'owners' => array(), 'outputs' => array(),
		'limits' => array('delay_max' => 129600, 'max_nodes' => 100, 'starts_hourly' => 60, 'open_runs' => 500));
	$plan = pg_profile_flow_convert($profiles, $facts);

	pg_assert_same(array('string' => 'No start box covers its action yet.'), $plan['left'][1], 'stored untranslated');
	pg_assert_same('No start box covers its action yet.', pg_profile_flow_reason_text($plan['left'][1]));
	pg_assert_same('Its page is gone.', pg_profile_flow_reason_text(array('string' => 'Its page is gone.')));
	pg_assert_same('A refusal.', pg_profile_flow_reason_text('A refusal.'), 'a sentence stored as it was');
	pg_assert_same(array('string' => 'It waits longer than a flow\'s Wait box may.'), pg_profile_flow_wait(array('schedule_length' => 400, 'schedule_unit' => 'days'), 129600)['reason']);

	$json = json_decode(json_encode($plan), true);
	pg_assert_contains('No start box covers its action yet.', implode("\n", pg_profile_flow_report_lines($json, $profiles, array())), 'drawn from the stored plan');
}

// Every profile of a chain a flow was made of belongs to that flow.
function test_campaign_profile_switch_members()
{
	$profiles = array(
		1 => array('action' => 'order_shipped', 'action_item_id' => 0),
		2 => array('action' => 'email_campaign_sent', 'action_item_id' => 1),
		3 => array('action' => 'email_campaign_sent', 'action_item_id' => 2),
		4 => array('action' => 'order_abandoned', 'action_item_id' => 0),
	);

	pg_assert_same(array(1 => 70, 2 => 70, 3 => 70), pg_profile_flow_members($profiles, array(1 => 70)));
	pg_assert_same(array(), pg_profile_flow_members($profiles, array()));
}

// Where a profile screen sends its visitor: a converted profile to its flow,
// any screen on a site with none left to the Automatic sends tab.
function test_campaign_profile_switch_screen_door()
{
	$converted = array(1 => 70, 2 => 70);

	pg_assert_same('workspace_flows.php?flow=70', pg_profile_flow_screen_door(2, $converted, 3));
	pg_assert_same('', pg_profile_flow_screen_door(4, $converted, 3), 'a profile left draws');
	pg_assert_same('', pg_profile_flow_screen_door(0, $converted, 1), 'the list with one left draws');
	pg_assert_same('messages_automatic.php', pg_profile_flow_screen_door(0, $converted, 0), 'none left');
	pg_assert_same('messages_automatic.php', pg_profile_flow_screen_door(0, array(), 0), 'a site with no profile at all');
	pg_assert_same(" AND email_campaign_profiles.id NOT IN ('1', '2')", pg_profile_flow_left_sql($converted));
	pg_assert_same('', pg_profile_flow_left_sql(array()));
	pg_assert_same('Order Abandoned', pg_profile_flow_action_names()['order_abandoned']);

	foreach (array('view_email_campaign_profiles.php' => 'pg_profile_flow_screen_go();', 'add_email_campaign_profile.php' => 'pg_profile_flow_screen_go();',
		'import_email_campaign_profiles.php' => 'pg_profile_flow_screen_go();', 'edit_email_campaign_profile.php' => 'pg_profile_flow_screen_go($id);',
		'duplicate_email_campaign_profile.php' => 'pg_profile_flow_screen_go($id);') as $file => $call) {
		pg_assert_contains($call, file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ' sends its visitor on');
	}

	pg_assert_contains('pg_profile_flow_switch_notice(', file_get_contents(PG_FUNCTIONS_DIR . '/messages_automatic.php'), 'the report on the flows side');
}
