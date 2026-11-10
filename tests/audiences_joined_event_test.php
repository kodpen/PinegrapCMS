<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the flow event of a contact joining a contact group
 * (contact_group.joined): one payload per contact the members_changed event
 * added (includes/fn/events.php), the record it is about, the group filter of
 * its rule, the groups' labels and what the flow's box gives
 * (includes/workspace/watch.php, flow_anchors.php), and the custom form's
 * group loop announcing the contacts its insert added. Nothing here reads the
 * database.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');

// The custom form's group loop counts the row its insert wrote: one row is
// the contact joining, none (a failed write) announces nobody.
function test_audiences_joined_event_form_door_counts_the_written_row()
{
	pg_assert_same(array(12), pg_fs_confirm_joined('12', 1), 'one row written');
	pg_assert_same(array(), pg_fs_confirm_joined(12, 0), 'no row written');
	pg_assert_same(array(), pg_fs_confirm_joined(12, -1), 'the write failed');
	pg_assert_same(array(), pg_fs_confirm_joined(0, 1), 'no contact');
}

// The form door announces through the same seam as every other door, after
// its insert and only from the branch that inserts.
function test_audiences_joined_event_form_door_announces_its_insert()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/custom_form.php');
	$loop = strpos($source, 'foreach ($contact_groups as $contact_group_id)');
	pg_assert_true($loop !== false, 'the group loop is there');

	$insert = strpos($source, 'INSERT INTO contacts_contact_groups_xref', (int) $loop);
	$announce = strpos($source, 'pg_announce_contact_group_members(', (int) $loop);
	pg_assert_true($insert !== false, 'the loop inserts');
	pg_assert_true(($announce !== false) && ($announce > $insert), 'the loop announces after its insert');

	$between = substr($source, (int) $insert, (int) $announce - (int) $insert);
	pg_assert_true(strpos($between, 'pg_fs_confirm_joined($contact_id, mysqli_affected_rows(') !== false, 'only the written row is announced');
}

// One payload for each contact added, none for those taken out.
function test_audiences_joined_event_one_payload_per_added_contact()
{
	$event = pg_contact_group_members_event(7, 'VIP', array(3, '5', 3, 0), array(9));

	pg_assert_same(array(
		array('id' => 3, 'contact_id' => 3, 'group_id' => 7, 'group_name' => 'VIP'),
		array('id' => 5, 'contact_id' => 5, 'group_id' => 7, 'group_name' => 'VIP'),
	), pg_contact_group_joined_events($event), 'the added contacts, once each');
	pg_assert_same(array(), pg_contact_group_joined_events(pg_contact_group_members_event(7, 'VIP', array(), array(9))), 'nobody joined');
	pg_assert_same(array(), pg_contact_group_joined_events(null), 'no event');
}

// Kept for the workspace beside the other site events; never the webhooks'
// members_changed.
function test_audiences_joined_event_is_kept_for_the_workspace()
{
	$events = pg_event_workspace_events();

	pg_assert_true(in_array('contact_group.joined', $events, true), 'kept');
	pg_assert_false(in_array('contact_group.members_changed', $events, true), 'the webhook event stays the webhooks\'');
}

// The event is about the contact, and a channel watching it hears nothing.
function test_audiences_joined_event_is_about_the_contact()
{
	pg_assert_same(array('contact', 3), ws_events_record_of('contact_group.joined', array('id' => 3, 'contact_id' => 3, 'group_id' => 7)), 'the contact');
	pg_assert_false(ws_events_watched('contact_group.joined'), 'not written into a watching channel');
}

// A rule narrowed to a group keeps its id as the status and matches only
// that group; an empty one matches any.
function test_audiences_joined_event_rule_matches_its_group()
{
	$vip = array('type' => 'event', 'event' => 'contact_group.joined', 'status' => '7');
	$any = array('type' => 'event', 'event' => 'contact_group.joined', 'status' => '');
	$joined = array('id' => 3, 'contact_id' => 3, 'group_id' => 7, 'group_name' => 'VIP');

	pg_assert_true(ws_events_rule_matches($vip, 'contact_group.joined', $joined), 'its group');
	pg_assert_false(ws_events_rule_matches($vip, 'contact_group.joined', array('group_id' => 8) + $joined), 'another group');
	pg_assert_true(ws_events_rule_matches($any, 'contact_group.joined', $joined), 'any group');
	pg_assert_false(ws_events_rule_matches($vip, 'customer.created', $joined), 'another event');
	pg_assert_same('exported', ws_events_filter_value('order.status_changed', array('status' => 'exported', 'group_id' => 7)), 'other events keep the status');
}

// A group filled by rules is named with its kind; a bad row is left out.
function test_audiences_joined_event_group_labels()
{
	pg_assert_same(array(
		'4' => 'Newsletter',
		'7' => lang(array('string' => '{var:1} (filled by rules)', 'vars' => 'VIP')),
	), ws_events_contact_group_labels(array(
		array('id' => 4, 'name' => 'Newsletter', 'kind' => 'static'),
		array('id' => '7', 'name' => 'VIP', 'kind' => 'dynamic'),
		array('id' => 0, 'name' => 'none'),
		'not a row',
	)), 'labels by id');
	pg_assert_same(array('5' => 'Old'), ws_events_contact_group_labels(array(array('id' => 5, 'name' => 'Old'))), 'before the kind column');
}

// The flow's event box gives the contact, the group and its name.
function test_audiences_joined_event_flow_outputs()
{
	$keys = array();

	foreach (ws_flow_event_outputs()['contact_group.joined'] ?? array() as $output) {
		$keys[] = $output['key'];
	}

	pg_assert_same(array('contact_id', 'group_id', 'group_name'), $keys, 'outputs');
}
