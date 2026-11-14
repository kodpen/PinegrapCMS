<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's own plans (includes/workspace/personal_plans.php):
 * the one gate, the drawer's input, how a plan is laid on the days and read
 * back, and the guard that no file reads the plan tables but the module.
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

function test_workspace_personal_plan_gate_opens_to_its_owner_alone()
{
	$plan = array('id' => 4, 'user_id' => 7);
	$team = array(7, 8, 1);

	pg_assert_true(ws_personal_plan_can_see(7, $plan, $team), 'the owner');
	pg_assert_false(ws_personal_plan_can_see(8, $plan, $team), 'a team member');
	pg_assert_false(ws_personal_plan_can_see(1, $plan, $team), 'an administrator on the team is no exception');
	pg_assert_false(ws_personal_plan_can_see(9, $plan, $team), 'somebody off the team (a guest)');
	pg_assert_false(ws_personal_plan_can_see(7, $plan, array(8, 1)), 'an owner who left: read by nobody, themselves included');
	pg_assert_false(ws_personal_plan_can_see(7, null, $team), 'no plan');
	pg_assert_false(ws_personal_plan_can_see(0, array('id' => 4, 'user_id' => 0), array(0)), 'no reader');
	pg_assert_true(ws_personal_plan_can_edit(7, $plan, $team));
	pg_assert_false(ws_personal_plan_can_edit(8, $plan, $team));
}

function test_workspace_personal_plan_gate_opens_as_its_owner_chose()
{
	$team = array(7, 8, 9, 1);
	$people = array('id' => 4, 'user_id' => 7, 'visibility' => 'people');
	$whole = array('id' => 5, 'user_id' => 7, 'visibility' => 'team');

	pg_assert_true(ws_personal_plan_can_see(8, $people, $team, array(8)), 'a chosen member');
	pg_assert_false(ws_personal_plan_can_see(9, $people, $team, array(8)), 'a member not chosen');
	pg_assert_false(ws_personal_plan_can_see(8, $people, array(7, 9), array(8)), 'a chosen member who left');
	pg_assert_true(ws_personal_plan_can_see(9, $whole, $team), 'the whole team');
	pg_assert_false(ws_personal_plan_can_see(3, $whole, $team), 'a guest is no team member');
	pg_assert_false(ws_personal_plan_can_see(9, $whole, array(9, 1)), 'nobody reads a leaver\'s plan');
	pg_assert_false(ws_personal_plan_can_edit(8, $people, $team), 'a reader changes nothing');
	pg_assert_false(ws_personal_plan_can_edit(9, $whole, $team));
	pg_assert_false(ws_personal_plan_can_see(8, array('id' => 6, 'user_id' => 7, 'visibility' => 'odd'), $team, array(8)), 'an unknown value is closed');
}

function test_workspace_personal_plan_clean_reads_who_can_see()
{
	$base = array('title' => 'x', 'start' => '2026-11-20', 'all_day' => 1);

	pg_assert_same('me', ws_personal_plan_clean($base)['visibility'], 'only me by default');
	pg_assert_same('me', ws_personal_plan_clean($base + array('visibility' => 'all'))['visibility'], 'an unknown choice is only me');
	pg_assert_same('team', ws_personal_plan_clean($base + array('visibility' => 'team', 'people' => array(3)))['visibility']);
	pg_assert_same(array(), ws_personal_plan_clean($base + array('visibility' => 'team', 'people' => array(3)))['people'], 'people only with people');

	$chosen = ws_personal_plan_clean($base + array('visibility' => 'people', 'people' => array('8', 8, '0', 'x', array(9), 12)));
	pg_assert_same(array(8, 12), $chosen['people']);
	pg_assert_true(isset(ws_personal_plan_clean($base + array('visibility' => 'people', 'people' => array()))['error']), 'nobody chosen');
}

function test_workspace_personal_plan_clean_reads_a_timed_plan()
{
	$clean = ws_personal_plan_clean(array('title' => '  Call the bank ', 'start' => '2026-11-20', 'start_time' => '10:00', 'end_time' => '10:30', 'remind' => '15', 'note' => 'IBAN'));

	pg_assert_same('Call the bank', $clean['title']);
	pg_assert_same(strtotime('2026-11-20 10:00:00'), $clean['starts_at']);
	pg_assert_same(strtotime('2026-11-20 10:30:00'), $clean['ends_at']);
	pg_assert_same(0, $clean['all_day']);
	pg_assert_same(strtotime('2026-11-20 09:45:00'), $clean['remind_at'], '15 minutes before');

	$hour = ws_personal_plan_clean(array('title' => 'x', 'start' => '2026-11-20', 'start_time' => '23:30'));
	pg_assert_same(strtotime('2026-11-21 00:30:00'), $hour['ends_at'], 'no end time: one hour');
	pg_assert_same(0, $hour['remind_at'], 'no reminder');
}

function test_workspace_personal_plan_clean_reads_days()
{
	$clean = ws_personal_plan_clean(array('title' => 'Fair', 'start' => '2026-11-20', 'end' => '2026-11-22', 'all_day' => '1', 'remind' => '1440'));

	pg_assert_same(strtotime('2026-11-20 00:00:00'), $clean['starts_at']);
	pg_assert_same(strtotime('2026-11-23 00:00:00'), $clean['ends_at'], 'the end is the midnight after the last day');
	pg_assert_same(strtotime('2026-11-19 09:00:00'), $clean['remind_at'], 'a day before 09:00 of the first day');

	$back = ws_personal_plan_present(array('id' => 3, 'title' => 'Fair', 'note' => '', 'all_day' => 1, 'starts_at' => $clean['starts_at'], 'ends_at' => $clean['ends_at'], 'remind_at' => $clean['remind_at']));
	pg_assert_same('2026-11-20', $back['start']);
	pg_assert_same('2026-11-22', $back['end'], 'read back as the drawer wrote it');
	pg_assert_same('1440', $back['remind']);
}

function test_workspace_personal_plan_clean_refuses()
{
	foreach (array(
		'title'    => array('title' => ' ', 'start' => '2026-11-20', 'all_day' => 1),
		'day'      => array('title' => 'x', 'start' => '2026-02-30', 'all_day' => 1),
		'order'    => array('title' => 'x', 'start' => '2026-11-20', 'end' => '2026-11-19', 'all_day' => 1),
		'time'     => array('title' => 'x', 'start' => '2026-11-20', 'start_time' => '25:00'),
		'end'      => array('title' => 'x', 'start' => '2026-11-20', 'start_time' => '10:00', 'end_time' => '09:00'),
		'reminder' => array('title' => 'x', 'start' => '2026-11-20', 'all_day' => 1, 'remind' => '-5'),
		'array'    => array('title' => array('x'), 'start' => '2026-11-20', 'all_day' => 1),
	) as $label => $data) {
		pg_assert_true(isset(ws_personal_plan_clean($data)['error']), $label);
	}
}

function test_workspace_personal_plans_place_on_the_days_they_touch()
{
	$days = array();

	foreach (array('2026-11-20', '2026-11-21', '2026-11-22') as $date) {
		$days[$date] = array('date' => $date, 'events' => array());
	}

	$days = ws_personal_plans_place($days, array(
		array('id' => 5, 'title' => 'Late call', 'all_day' => 0, 'starts_at' => strtotime('2026-11-20 22:00'), 'ends_at' => strtotime('2026-11-21 00:00')),
		array('id' => 6, 'title' => 'Fair', 'all_day' => 1, 'starts_at' => strtotime('2026-11-21 00:00'), 'ends_at' => strtotime('2026-11-23 00:00')),
	));

	pg_assert_same(array(5), array_column($days['2026-11-20']['events'], 'plan_id'), 'until midnight stays on its day');
	pg_assert_same(array(6), array_column($days['2026-11-21']['events'], 'plan_id'));
	pg_assert_same(array(6), array_column($days['2026-11-22']['events'], 'plan_id'));
	pg_assert_same('personal', $days['2026-11-21']['events'][0]['kind']);
	pg_assert_same('22:00', $days['2026-11-20']['events'][0]['time']);

	$shared = ws_personal_plans_place(array('2026-11-20' => array('date' => '2026-11-20', 'events' => array())), array(
		array('id' => 7, 'user_id' => 8, 'title' => 'Their plan', 'all_day' => 1, 'starts_at' => strtotime('2026-11-20 00:00'), 'ends_at' => strtotime('2026-11-21 00:00')),
		array('id' => 8, 'user_id' => 3, 'title' => 'Mine', 'all_day' => 1, 'starts_at' => strtotime('2026-11-20 00:00'), 'ends_at' => strtotime('2026-11-21 00:00')),
	), 3, array(8 => 'Ayşe'));
	pg_assert_same(array('Ayşe', ''), array_column($shared['2026-11-20']['events'], 'owner'), 'another person\'s plan names its owner');
}

// The guard: the plan tables are read by the module alone, so every read goes
// through ws_personal_plan_can_see(); the step, the installer's table list and
// the account deletion (which calls the module) name them too.
function test_workspace_personal_plan_tables_are_read_through_the_module_alone()
{
	$allowed = array(
		'includes/workspace/personal_plans.php',
		'includes/workspace/personal_plan_steps.php',
		// A plan naming a customer: the merge moves contact_id, the erasure
		// sets it to 0; neither reads the plan.
		'includes/fn/contacts.php',
		'includes/fn/privacy.php',
		'includes/migrations/2026.11.0.php',
		'install/index.php',
	);
	$found = array();
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = str_replace('\\', '/', substr((string) $file, strlen(PG_FUNCTIONS_DIR) + 1));

		if ((substr($path, -4) !== '.php') || (strpos($path, 'vendor/') === 0) || (strpos($path, 'data/') === 0)) {
			continue;
		}

		if (preg_match('/ws_personal_plan(s|_steps|_people)\b[^(]/', (string) file_get_contents((string) $file)) && !in_array($path, $allowed, true)) {
			$found[] = $path;
		}
	}

	pg_assert_same(array(), $found, 'files naming the plan tables outside the module');

	// Inside the module every row read is passed to the gate before it leaves.
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/personal_plans.php');
	pg_assert_true(substr_count($module, 'ws_personal_plan_can_see(') >= 4, 'one plan, the plans between, the reminder run and the gate itself');
}

function test_workspace_personal_plan_reminder_line_and_api()
{
	$notify = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');
	pg_assert_contains("case 'plan_reminder':", $notify, 'described');
	pg_assert_contains("(\$row['kind'] === 'plan_reminder') && (!function_exists('ws_personal_plan') || !ws_personal_plan(", $notify, 'read back through the gate');

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_inbox.php');
	pg_assert_contains("if (\$row['kind'] === 'plan_reminder') {", $api, 'no application reads the plan\'s words');
	pg_assert_contains("=== 'plan_reminder');", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/account.php'), 'nor its bell row through GET /notifications');

	pg_assert_contains('ws_personal_plans_user_deleted((int) $user_id);', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php'), 'deleted with the account');
	pg_assert_contains("'ws_personal_plans',", (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php'), 'get_tables()');
}
