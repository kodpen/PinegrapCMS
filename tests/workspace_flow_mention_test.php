<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the mention box of a flow: a person writes @ and the flow's
 * name in the channel the box names, and the flow starts. Its settings and
 * rule, how a mention is found in a text and which flow it names, how the
 * rule matches and starts only the flow named, the values it gives
 * {{trigger.<field>}}, and where the mention is kept: after the composer's
 * send only, never in ws_message_send(), which flows and applications use.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_mention.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_member.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_catalogue.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function test_workspace_flow_mention_box_is_a_start()
{
	pg_assert_same('mention', ws_flow_anchor_kinds()['mention']['rule']);
	pg_assert_true(ws_flow_kinds()['mention']['trigger'], 'a mention box starts a flow');
	pg_assert_same(array('next'), ws_flow_kinds()['mention']['outcomes']);
	pg_assert_same('event', ws_flow_start_names()['mention'], 'listed among the flows started on an event');
	pg_assert_true(ws_events_is_own('workspace.flow.mentioned'), 'kept by ws_event()');
	pg_assert_false(strpos(pg_test_function_source('ws_events_catalog'), 'workspace.flow.mentioned') !== false, 'not offered by the event box: the mention box is its only way in');
	pg_assert_false(in_array('mention', ws_flow_member_kinds(), true), 'staff build it; a role-3 member\'s own flows do not');
}

function test_workspace_flow_mention_settings_and_rule()
{
	pg_assert_same(array('channel_id' => 0), ws_flow_anchor_normalize('mention', array()), 'a new box: no channel');
	pg_assert_same(array('channel_id' => 12), ws_flow_anchor_normalize('mention', array('channel_id' => '12', 'extra' => 1)), 'unknown keys dropped');
	pg_assert_same(array('channel_id' => 0), ws_flow_anchor_normalize('mention', array('channel_id' => -4)));

	pg_assert_same(array('type' => 'mention', 'event' => 'workspace.flow.mentioned', 'channel_id' => 12),
		ws_flow_anchor_rule('mention', array('channel_id' => 12)), 'the rule names the event, so ws_events_awaited() reads it');

	pg_assert_same(1, count(ws_flow_anchor_problems('mention', array('channel_id' => 0), '“Ask”')), 'no channel is a problem');
	pg_assert_same(array(), ws_flow_anchor_problems('mention', array('channel_id' => 12), '“Ask”'));

	$graph = array('nodes' => array(array('id' => 'ask', 'kind' => 'mention', 'label' => '', 'x' => 0, 'y' => 0, 'config' => array())), 'edges' => array());
	pg_assert_true(in_array('bad_config', array_column(ws_flow_graph_check($graph), 'code'), true), 'publishing a mention box without a channel is refused');
}

function test_workspace_flow_mention_refusal()
{
	$public = array('id' => 12, 'kind' => 'public', 'archived_at' => 0);

	pg_assert_same('', ws_flow_mention_refusal($public, true));
	pg_assert_same('', ws_flow_mention_refusal(array('kind' => 'private') + $public, true));
	pg_assert_same(lang('Choose a channel you may read.'), ws_flow_mention_refusal($public, false), 'a channel the owner does not read');
	pg_assert_same(lang('Choose a channel you may read.'), ws_flow_mention_refusal(null, true), 'no channel');
	pg_assert_same(lang('Choose a channel you may read.'), ws_flow_mention_refusal(array('archived_at' => 5) + $public, true), 'archived');

	foreach (array('thread', 'guest', 'direct') as $kind) {
		pg_assert_same(lang('Choose a channel you may read.'), ws_flow_mention_refusal(array('kind' => $kind) + $public, true), 'a ' . $kind . ' channel');
	}
}

function test_workspace_flow_mention_found_in_text()
{
	pg_assert_same(0, ws_flow_mention_at('@Order check please', 'Order check'));
	pg_assert_same(4, ws_flow_mention_at('Hi, @order CHECK.', 'Order check'), 'any case, punctuation after');
	pg_assert_same(-1, ws_flow_mention_at('Order check', 'Order check'), 'the name without @');
	pg_assert_same(-1, ws_flow_mention_at('@Order checks', 'Order check'), 'a longer word');
	pg_assert_same(-1, ws_flow_mention_at('mail sales@Order check', 'Order check'), 'inside an address');
	pg_assert_same(-1, ws_flow_mention_at('@Order', ''), 'an empty name');
	pg_assert_same(1, ws_flow_mention_at('(@Stok sayımı)', 'stok sayımı'), 'letters beyond ASCII');
	pg_assert_same(-1, ws_flow_mention_at('@Stok sayımıx', 'Stok sayımı'));
	pg_assert_same(0, ws_flow_mention_at('@a.b (x)', 'a.b (x)'), 'a name with signs a pattern would read');
}

function test_workspace_flow_mention_names_one_flow()
{
	$flows = array(3 => 'Order check', 5 => 'Order check report', 8 => 'Leave');

	pg_assert_same(5, ws_flow_mention_pick('@Order check report now', $flows), 'at the same place the longer name');
	pg_assert_same(3, ws_flow_mention_pick('@Order check now', $flows));
	pg_assert_same(8, ws_flow_mention_pick('@Leave then @Order check', $flows), 'the first mentioned');
	pg_assert_same(0, ws_flow_mention_pick('Order check', $flows), 'none mentioned');
	pg_assert_same(0, ws_flow_mention_pick('@Order check', array()), 'none waiting');
}

function test_workspace_flow_mention_rule_matches()
{
	$rule = ws_flow_anchor_rule('mention', array('channel_id' => 12));
	$payload = ws_flow_mention_payload(array('id' => 4, 'name' => 'Ada'), array('id' => 12, 'name' => 'ops'), 90, 7, 'hi @Count');

	pg_assert_true(ws_events_rule_matches($rule, 'workspace.flow.mentioned', $payload));
	pg_assert_false(ws_events_rule_matches($rule, 'workspace.flow.mentioned', array('channel_id' => 13) + $payload), 'another channel');
	pg_assert_false(ws_events_rule_matches($rule, 'workspace.guest.message', $payload), 'another event');
	pg_assert_false(ws_events_rule_matches(array('channel_id' => 0) + $rule, 'workspace.flow.mentioned', array('channel_id' => 0) + $payload), 'a rule of no channel');

	pg_assert_false(ws_flow_mention_other_flow($rule, $payload, 7), 'the flow named starts');
	pg_assert_true(ws_flow_mention_other_flow($rule, $payload, 6), 'another flow of the same channel does not');
	pg_assert_true(ws_flow_mention_other_flow($rule, $payload, 0), 'a plain scheduled action does not');
	pg_assert_false(ws_flow_mention_other_flow(array('type' => 'event'), $payload, 6), 'other rules are not asked');

	$start = pg_test_function_source('ws_events_start');
	pg_assert_contains("ws_flow_mention_other_flow(\$item['rule'], \$payload, \$item['flow_id'])", $start, 'ws_events_start() starts only the flow named');
	pg_assert_contains("array('event', 'row', 'mention')", $start, 'ws_events_start() reads the mention rules');
	pg_assert_contains("array('event', 'row', 'mention')", pg_test_function_source('ws_events_awaited'), 'ws_events_awaited() reads them');
}

function test_workspace_flow_mention_payload_and_record()
{
	$payload = ws_flow_mention_payload(array('id' => 4, 'name' => 'Ada'), array('id' => 12, 'name' => 'ops'), 90, 7, str_repeat('x', 2500));

	pg_assert_same('{"author_id":4,', substr(json_encode($payload), 0, 15), 'the author leads the JSON, as ws_flow_mention_recent() reads it');
	pg_assert_same(WS_FLOW_MENTION_TEXT_MAX, mb_strlen($payload['text']), 'the text is cut');
	pg_assert_same(array('channel', 12), ws_events_record_of('workspace.flow.mentioned', $payload), 'about its channel');
	pg_assert_same(60, WS_FLOW_MENTION_GAP, 'one start per person a minute');
}

function test_workspace_flow_mention_trigger_values()
{
	pg_assert_same(array('message_id', 'text', 'author_id', 'author_name', 'channel_id', 'channel_name'), array_column(ws_flow_trigger_outputs('mention'), 'key'));

	$payload = ws_flow_mention_payload(array('id' => 4, 'name' => 'Ada'), array('id' => 12, 'name' => 'ops'), 90, 7, 'hi @Count');
	$context = array('trigger' => 'mention', 'event' => 'workspace.flow.mentioned', 'event_payload' => $payload);

	pg_assert_same(array('message_id' => '90', 'text' => 'hi @Count', 'author_id' => '4', 'author_name' => 'Ada', 'channel_id' => '12', 'channel_name' => 'ops'),
		ws_step_trigger_values($context, true));
	pg_assert_same(array('message_id' => '', 'text' => '', 'author_id' => '', 'author_name' => '', 'channel_id' => '', 'channel_name' => ''),
		ws_step_trigger_values($context, false), 'nothing while the owner no longer reads the channel');

	$for = pg_test_function_source('ws_step_trigger_values_for');
	pg_assert_contains("(\$kind === 'mention')", $for, 'the owner\'s reach is asked for a mention');
}

function test_workspace_flow_mention_kept_after_the_composer_only()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$send = substr($actions, strpos($actions, "case 'ws_send':"), 6000);
	$send = substr($send, 0, strpos($send, "case 'ws_edit':"));

	pg_assert_contains('ws_flow_mention_after_send($viewer, $channel, $result[\'message_id\'], $body)', $send, 'the composer\'s send keeps a mention');
	pg_assert_false(strpos(pg_test_function_source('ws_message_send'), 'ws_flow_mention') !== false,
		'ws_message_send(), which flows, applications and assistants write through, keeps none');

	$hits = 0;

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php') as $file) {
		$hits += substr_count((string) file_get_contents($file), 'ws_flow_mention_after_send(') - ((basename($file) === 'flow_mention.php') ? 1 : 0);
	}

	pg_assert_same(1, $hits, 'called from the composer\'s send alone');

	$after = pg_test_function_source('ws_flow_mention_after_send');
	pg_assert_contains('ws_flow_mention_recent(', $after, 'once a minute a person');
	pg_assert_contains("empty(\$viewer['member'])", $after, 'a team member only');
}
