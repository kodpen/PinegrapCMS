<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's own plan made for a customer
 * (includes/workspace/personal_plan_contact.php): the card's roadmaps, a copy
 * moved to a new day, the history entry, and the guards that keep a plan
 * behind its gate and away from an application.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/customer_history.php');

function test_workspace_personal_plan_card_roadmaps()
{
	$rows = ws_customer_card_roadmaps(array(
		array('id' => 7, 'title' => 'Onboarding', 'starts_at' => strtotime('2026-11-20 09:00'), 'owner' => '', 'done' => 2, 'total' => 5),
		array('id' => 8, 'title' => 'Renewal', 'starts_at' => strtotime('2026-12-01 00:00'), 'owner' => 'Ayşe', 'done' => 0, 'total' => 0),
	), 'https://example.com/pinegrap/');

	pg_assert_same('2/5', $rows[0]['progress_text']);
	pg_assert_same('', $rows[0]['owner_text'], 'the reader\'s own plan names no owner');
	pg_assert_same('https://example.com/pinegrap/workspace_my_calendar.php?view=list&date=2026-11-20&plan=7', $rows[0]['url']);
	pg_assert_same('', $rows[1]['progress_text'], 'no steps: no progress');
	pg_assert_contains('Ayşe', $rows[1]['owner_text'], 'somebody else\'s plan says whose it is');
}

function test_workspace_personal_plan_copy_moves_to_the_new_day()
{
	$plan = array('title' => 'Onboarding', 'note' => 'n', 'all_day' => 0,
		'starts_at' => strtotime('2026-10-05 10:00:00'), 'ends_at' => strtotime('2026-10-07 11:30:00'));
	$steps = array(
		array('title' => 'Kick-off', 'day' => '2026-10-05'),
		array('title' => 'Training', 'day' => '2026-10-09'),
		array('title' => 'Open', 'day' => '0000-00-00'),
	);
	$copy = ws_personal_plan_copy_shift($plan, $steps, '2026-11-20');

	pg_assert_same(strtotime('2026-11-20 10:00:00'), $copy['plan']['starts_at'], 'the time of day stays');
	pg_assert_same(strtotime('2026-11-22 11:30:00'), $copy['plan']['ends_at'], 'the length stays');
	pg_assert_same('2026-11-20', $copy['steps'][0]['day']);
	pg_assert_same('2026-11-24', $copy['steps'][1]['day'], 'a step keeps its distance to the start');
	pg_assert_same('', $copy['steps'][2]['day'], 'a step with no day keeps none');
	pg_assert_false(isset($copy['steps'][0]['done']), 'nothing is done in a copy');

	$back = ws_personal_plan_copy_shift($plan, array(), '2026-09-30');
	pg_assert_same(strtotime('2026-09-30 10:00:00'), $back['plan']['starts_at'], 'an earlier day too');
}

function test_workspace_personal_plan_history_entry()
{
	$history = ws_customer_history_build(array('workspace' => true), array(
		'plans' => array(array('id' => 9, 'title' => 'Onboarding', 'starts_at' => strtotime('2026-11-20 00:00'), 'all_day' => true, 'owner' => '', 'done' => 1, 'total' => 4)),
	), function () {
		return '';
	}, 'https://example.com/pinegrap/', 10);

	pg_assert_same(1, count($history['entries']));
	pg_assert_same('plan', $history['entries'][0]['kind']);
	pg_assert_same('1/4', $history['entries'][0]['status_label']);
	pg_assert_true($history['entries'][0]['day_only'], 'an all-day roadmap says its day alone');
	pg_assert_contains('workspace_my_calendar.php?view=list&date=2026-11-20&plan=9', $history['entries'][0]['url']);

	$none = ws_customer_history_build(array('workspace' => false), array('plans' => array(array('id' => 9, 'title' => 'x', 'starts_at' => 1, 'owner' => '', 'done' => 0, 'total' => 0))), function () {
		return '';
	}, '', 10);
	pg_assert_same(array(), $none['entries'], 'only with the Workspace right');
}

// A plan reaches the card and the history only through the plans' gate, a
// customer is named only by the plan's owner and only when they may open the
// contact, and an application never reads a plan in the customer's history.
function test_workspace_personal_plan_contact_guards()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/personal_plan_contact.php');

	pg_assert_contains('if (ws_personal_plan_can_see($user_id, $plan, $team)) {', $module, 'the plans of a contact pass the gate');
	pg_assert_same(2, substr_count($module, '!ws_personal_plan_can_edit($user_id, $plan)'), 'naming a customer and copying are the owner\'s');
	pg_assert_same(2, substr_count($module, 'ws_viewer_contacts_open($viewer, array('), 'a customer the person may open: naming and offering');
	pg_assert_false(strpos($module, 'INSERT INTO ws_personal_plans') !== false, 'a copy writes nothing: the drawer saves it');

	$history = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/customer_history.php');
	pg_assert_contains("&& (\$viewer['history_plans'] ?? true)) {", $history, 'the history reads plans unless told not to');

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_customer.php');
	pg_assert_contains("\$viewer['history_plans'] = false;", $api, 'the API\'s history never reads a plan');

	$holders = pg_contact_holders();
	pg_assert_same(array('column' => 'contact_id', 'merge' => 'move', 'erase' => 'find'), $holders['ws_personal_plans'], 'merge moves it, erasure finds it');
}

function test_workspace_personal_plan_export_row()
{
	$row = ws_personal_plan_export_row(
		array('id' => 4, 'title' => 'Onboarding', 'all_day' => 1, 'starts_at' => strtotime('2026-11-20 00:00'), 'ends_at' => strtotime('2026-11-23 00:00'), 'note' => 'private words', 'remind_at' => 5),
		'Ayşe Yılmaz',
		array(array('title' => 'Kick-off', 'day' => '2026-11-20'), array('title' => 'Open', 'day' => '0000-00-00'))
	);

	pg_assert_same(array(
		'record' => 'plan',
		'id'     => 4,
		'title'  => 'Onboarding',
		'starts' => '2026-11-20',
		'ends'   => '2026-11-22',
		'owner'  => 'Ayşe Yılmaz',
		'steps'  => 'Kick-off (2026-11-20); Open',
	), $row, 'title, days, owner and steps; no note, no reminder');

	$timed = ws_personal_plan_export_row(array('id' => 5, 'title' => 'Call', 'all_day' => 0, 'starts_at' => strtotime('2026-11-20 10:00'), 'ends_at' => strtotime('2026-11-20 11:00')), '', array());
	pg_assert_same('2026-11-20 10:00', $timed['starts']);
	pg_assert_same('2026-11-20 11:00', $timed['ends']);
}

// A contact's data export lists a plan only when it is shared with the whole
// team (owner, channel 3361): asked in the query and again row by row, and
// the export reads plans through this function alone.
function test_workspace_personal_plan_export_lists_whole_team_plans_alone()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/personal_plan_contact.php');
	$start = strpos($module, 'function ws_personal_plans_export(');
	$body = substr($module, $start, (int) strpos($module, "\n}\n", $start) - $start);

	pg_assert_contains("AND visibility = 'team'", $body, 'the query asks for whole-team plans');
	pg_assert_contains("if ((string) \$plan['visibility'] !== 'team') {", $body, 'and every row is held to it again');
	pg_assert_false(strpos($body, "'me'") !== false || strpos($body, "'people'") !== false, 'no other visibility is named');

	$export = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/privacy_export.php');
	pg_assert_contains('ws_personal_plans_export($contact_ids, $limit)', $export, 'the export lists plans by the contact');
	pg_assert_false((bool) preg_match('/ws_personal_plan(s|_steps|_people)\b[^(_]/', $export), 'and never reads the plan tables itself');
}
