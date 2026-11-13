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
