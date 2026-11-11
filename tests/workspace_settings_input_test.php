<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what Workspace Settings accepts: hours as typed (refused when
 * unreadable, negative or too many), whose team row a person may change
 * (never a higher rank's), the values a refused card form gets back, and a
 * holiday whose last day is before its first.
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

function test_workspace_settings_minutes()
{
	pg_assert_same(0, ws_settings_minutes('', 30, 1440), 'empty means the default');
	pg_assert_same(0, ws_settings_minutes('0', 30, 1440), 'zero means the default');
	pg_assert_same(450, ws_settings_minutes('7,5', 30, 1440), 'a decimal comma');
	pg_assert_same(480, ws_settings_minutes(' 8 ', 30, 1440), 'whole hours');
	pg_assert_same(5, ws_settings_minutes('0.01', 5, 2400), 'raised to the floor');
	pg_assert_same(null, ws_settings_minutes('-3', 5, 2400), 'a negative number');
	pg_assert_same(null, ws_settings_minutes('abc', 30, 1440), 'text');
	pg_assert_same(null, ws_settings_minutes('25', 30, 1440), 'over the ceiling');
	pg_assert_same(1440, ws_settings_minutes('24', 30, 1440), 'the ceiling itself');
}

function test_workspace_settings_holiday_range_refused()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/workdays.php');

	pg_assert_true(strpos($source, '($to < $from) ? $from : $to') === false, 'a reversed holiday is still turned into one day');
	pg_assert_true(strpos($source, "lang('The last day cannot be before the first day.')") !== false, 'a reversed holiday is not refused');
}

function test_workspace_settings_may_edit_person()
{
	pg_assert_true(ws_settings_may_edit_person(0, 0), 'an administrator changes another administrator');
	pg_assert_true(ws_settings_may_edit_person(0, 3), 'an administrator changes a basic user');
	pg_assert_true(ws_settings_may_edit_person(2, 2), 'a manager changes another manager');
	pg_assert_true(ws_settings_may_edit_person(2, 3), 'a manager changes a basic user');
	pg_assert_true(ws_settings_may_edit_person(3, 3), 'a basic user changes another basic user');
	pg_assert_true(!ws_settings_may_edit_person(2, 0), 'a manager takes an administrator out');
	pg_assert_true(!ws_settings_may_edit_person(2, 1), 'a manager takes a designer out');
	pg_assert_true(!ws_settings_may_edit_person(3, 2), 'a basic user takes a manager out');
	pg_assert_true(!ws_settings_may_edit_person(1, 0), 'a designer takes an administrator out');
}

function test_workspace_settings_people_rank_checked()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/team_settings.php');

	pg_assert_true(strpos($source, "if (!ws_settings_may_edit_person(\$viewer['role'], \$candidates[\$person_id]['role'])) {") !== false, 'the people save does not check the rank');
	pg_assert_true(strpos($source, "\$locked = !ws_settings_may_edit_person(\$viewer['role'], \$person['role']);") !== false, 'a higher rank\'s row is not drawn read-only');
}

function test_workspace_settings_refused_fields()
{
	$fields = ws_settings_refused_fields(array(
		'token'         => 'abc',
		'ws_action'     => 'mail_box_save',
		'box_id'        => '4',
		'name'          => 'Support',
		'password'      => 'secret!',
		'route_match'   => array('+billing', ''),
		'route_channel' => array('7', ''),
		'members'       => array(),
		'people'        => array(
			5 => array('title' => 'Lead', 'day_hours' => 'abc', 'workdays' => array('1', '2')),
		),
		'mcp_tool'      => array('search' => 'on'),
		'api_key'       => 'k',
	));

	pg_assert_same('mail_box_save', $fields['ws_action'] ?? null, 'the action is kept');
	pg_assert_same('4', $fields['box_id'] ?? null, 'the record id is kept');
	pg_assert_same('Support', $fields['name'] ?? null, 'a typed value is kept');
	pg_assert_true(!isset($fields['token']), 'the CSRF token is kept');
	pg_assert_true(!isset($fields['password']), 'the password is kept');
	pg_assert_true(!isset($fields['api_key']), 'a key is kept');
	pg_assert_same(array('+billing', ''), $fields['route_match[]'] ?? null, 'a list comes back as name[]');
	pg_assert_same('abc', $fields['people[5][day_hours]'] ?? null, 'a keyed row comes back as name[key]');
	pg_assert_same(array('1', '2'), $fields['people[5][workdays][]'] ?? null, 'a list in a row');
	pg_assert_same('on', $fields['mcp_tool[search]'] ?? null, 'a keyed value');
	pg_assert_true(!isset($fields['members[]']), 'an empty list');
}

function test_workspace_settings_refused_script_wired()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_settings.php');

	pg_assert_true(strpos($source, "\$_SESSION['software']['ws_settings_refused'] = ws_settings_refused_fields(\$_POST);") !== false, 'a refused form is not kept');
	pg_assert_true(strpos($source, "ws_settings_refused_script(\$refused_fields) .") !== false, 'the refill script is not printed');
}
