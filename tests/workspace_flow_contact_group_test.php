<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the flow box that adds a contact to a contact group or takes one
 * out (contact_group): its settings, its problems, the contact it reads and
 * the guard that keeps a flow started by contact_group.joined from starting
 * again on its own add. Nothing here reads the database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/watch.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_contact_group.php');

// The source of a function, comments taken out.
function ws_flow_contact_group_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// A join a flow's box caused names that flow; the flow it names is not
// started by it, every other flow is.
function test_workspace_flow_contact_group_own_add_does_not_start_its_flow()
{
	pg_assert_true(ws_events_own_group_add('contact_group.joined', 7, 7), 'the flow that added');
	pg_assert_false(ws_events_own_group_add('contact_group.joined', 7, 8), 'another flow');
	pg_assert_false(ws_events_own_group_add('contact_group.joined', 0, 7), 'a join no flow made');
	pg_assert_false(ws_events_own_group_add('contact_group.joined', 7, 0), 'a scheduled action of no flow');
	pg_assert_false(ws_events_own_group_add('customer.created', 7, 7), 'another event');

	$start = ws_flow_contact_group_test_source('ws_events_start');
	pg_assert_contains("ws_events_own_group_add(\$event, \$added_by, \$item['flow_id'])", $start);
	pg_assert_contains("unset(\$payload['_depth'], \$payload['_flow'])", $start);
}

// The joined event carries the flow that made the change, only when one did.
function test_workspace_flow_contact_group_joined_event_names_the_flow()
{
	$event = array('id' => 4, 'name' => 'VIP', 'added' => array(11), 'removed' => array());

	$joined = pg_contact_group_joined_events($event, 9);
	pg_assert_same(9, $joined[0]['_flow']);
	pg_assert_same(11, $joined[0]['contact_id']);

	$joined = pg_contact_group_joined_events($event);
	pg_assert_false(isset($joined[0]['_flow']), 'no flow, no mark');

	pg_assert_contains('pg_contact_group_joined_events($event, $flow_id)', ws_flow_contact_group_test_source('pg_announce_contact_group_members'));
}

// Its settings as they are kept: a group, add unless remove, and the
// contact as one line, '' for the contact of the run's record.
function test_workspace_flow_contact_group_settings()
{
	pg_assert_same(array('group_id' => 4, 'way' => 'remove', 'contact' => '{{trigger.contact_id}}'),
		ws_step_config_normalize('contact_group', array('group_id' => '4', 'way' => 'remove', 'contact' => ' {{trigger.contact_id}} ')));
	pg_assert_same(array('group_id' => 0, 'way' => 'add', 'contact' => ''), ws_step_config_normalize('contact_group', array('group_id' => 'x', 'way' => 'drop')));
	pg_assert_same('12', ws_step_config_normalize('contact_group', array('contact' => 12))['contact']);
	pg_assert_same(0, ws_step_config_normalize('contact_group', array('group_id' => true))['group_id']);
}

// A group is needed; a contact, when named, is a number or one value.
function test_workspace_flow_contact_group_problems()
{
	pg_assert_same(1, count(ws_step_config_problems('contact_group', array('group_id' => 0), 'Box')));
	pg_assert_same(array(), ws_step_config_problems('contact_group', array('group_id' => 3), 'Box'));
	pg_assert_same(array(), ws_step_config_problems('contact_group', array('group_id' => 3, 'contact' => '{{trigger.contact_id}}'), 'Box'));
	pg_assert_same(1, count(ws_step_config_problems('contact_group', array('group_id' => 3, 'contact' => 'Ada and {{trigger.contact_id}}'), 'Box')));
}

// Only a real change writes: in when not a member, out when a member.
function test_workspace_flow_contact_group_change()
{
	pg_assert_same(array('add' => array(5), 'remove' => array()), ws_step_contact_group_change('add', 5, false));
	pg_assert_same(array('add' => array(), 'remove' => array()), ws_step_contact_group_change('add', 5, true));
	pg_assert_same(array('add' => array(), 'remove' => array(5)), ws_step_contact_group_change('remove', 5, true));
	pg_assert_same(array('add' => array(), 'remove' => array()), ws_step_contact_group_change('remove', 5, false));
	pg_assert_same(array('add' => array(), 'remove' => array()), ws_step_contact_group_change('add', 0, false));
}

// A step kind of its own, its contact a text filled with values, its
// outputs the contact and whether the group changed.
function test_workspace_flow_contact_group_kind()
{
	$kind = ws_step_kinds()['contact_group'];

	pg_assert_same(array('contact'), $kind['texts']);
	pg_assert_same(array('contact_id', 'changed'), array_column($kind['outputs'], 'key'));
	pg_assert_true(isset(ws_flow_kinds()['contact_group']), 'a flow box');
	pg_assert_same('json', ws_step_fill_target('contact_group', array()));
	pg_assert_contains("'contact_group'", ws_flow_contact_group_test_source('ws_flow_quick_groups'));
}

// The box writes the membership row only: never the contact's consent nor
// the group's opt-in choice; a group switched to rules is refused as it
// runs; a change is announced with the flow's id.
function test_workspace_flow_contact_group_writes_membership_only()
{
	$do = ws_flow_contact_group_test_source('ws_step_contact_group_do');

	pg_assert_contains('INSERT INTO contacts_contact_groups_xref', $do);
	pg_assert_true(strpos($do, 'opt_in') === false, 'no consent written');
	pg_assert_contains('pg_audience_group_is_dynamic($group_id)', $do);
	pg_assert_contains("pg_announce_contact_group_members(\$group_id, \$change['add'], \$change['remove'], \$label, (int) (\$run['flow_id'] ?? 0))", $do);
	pg_assert_contains("ws_step_record_get_read(\$owner, 'contact', \$contact_id)", $do);
	pg_assert_contains('pg_audience_hand_sql()', ws_flow_contact_group_test_source('ws_step_contact_group_choices'));
}

// What the box says it does.
function test_workspace_flow_contact_group_text()
{
	pg_assert_contains('VIP', ws_step_contact_group_text(array('group_name' => 'VIP', 'way' => 'add', 'contact' => '')));
	pg_assert_true(ws_step_contact_group_text(array('group_name' => 'VIP', 'way' => 'remove', 'contact' => '7'))
		!== ws_step_contact_group_text(array('group_name' => 'VIP', 'way' => 'add', 'contact' => '7')), 'add and remove read apart');
}
