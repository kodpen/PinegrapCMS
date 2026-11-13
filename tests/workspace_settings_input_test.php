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
