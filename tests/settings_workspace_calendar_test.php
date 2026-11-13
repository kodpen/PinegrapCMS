<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Working calendar in Team and working hours of the Site Settings
 * dialog: days off kept by hand and public holiday calendar addresses came
 * over from Workspace Settings. Pins the section, its lists and the old
 * anchor's new place, the buttons that name each row action, the two
 * screens' field names reading as one request, the add forms' checks made
 * before anything is written, the drawers, and the gate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

if (!defined('PG_SETTINGS_ENTRY')) {
	define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function settings_workspace_calendar_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_workspace_calendar_section_lists_and_anchor()
{
	$source = settings_workspace_calendar_test_source('includes/settings/registry.php');
	$from = strpos($source, "        'workspace_team' => array(");
	$to = strpos($source, "        'workspace_assistants' => array(", (int) $from);
	$entry = substr($source, (int) $from, (int) $to - (int) $from);

	pg_assert_contains("'pgset-ws-calendar'    => lang('Working calendar'),", $entry, 'the card is a section');
	pg_assert_contains("'pgset-ws-calendar'    => pg_settings_keywords(lang('workspace, holiday, holidays, days off, working calendar, calendar feed')),", $entry, 'its keywords');
	pg_assert_contains("'ws_hol'      => 1,", $entry, 'a holiday\'s fields posted as a list');
	pg_assert_contains("'ws_feed'     => 1,", $entry, 'a calendar address\'s fields posted as a list');
	pg_assert_same(array('workspace_team', 'pgset-ws-calendar'), pg_settings_legacy_anchors()['ws-calendar'] ?? null, 'the old anchor lands on the card');
}

function test_settings_workspace_calendar_gate()
{
	// The category opens for the Workspace settings right, not the role.
	$source = settings_workspace_calendar_test_source('includes/settings/registry.php');
	$from = strpos($source, "        'workspace_team' => array(");

	pg_assert_contains("'gate'  => 'workspace',", substr($source, (int) $from, 400), 'gated by the Workspace settings right');

	$category = array('group' => 'workspace', 'gate' => 'workspace');
	pg_assert_false(pg_settings_category_open($category, array('manager' => true, 'workspace' => false)), 'a manager without the right');
	pg_assert_true(pg_settings_category_open($category, array('manager' => false, 'workspace' => true)), 'a holder of the right alone');
}

function test_settings_workspace_calendar_old_and_new_names_read_alike()
{
	$old = array(
		'ws_action' => 'holiday_add', 'name' => 'Founders day', 'from' => '2026-12-01', 'to' => '2026-12-02', 'department_id' => '3', 'yearly' => '1',
		'url' => 'webcal://example.com/h.ics', 'public_only' => '1', 'feed_id' => '4', 'event_id' => '9', 'skip' => '1',
	);

	pg_assert_same(
		array('holiday_add', ws_holidays_settings_input('holiday_add', $old)),
		ws_holidays_settings_request(array('ws_holiday_add' => '1', 'ws_hol' => array('name' => 'Founders day', 'from' => '2026-12-01', 'to' => '2026-12-02', 'department_id' => '3', 'yearly' => '1'))),
		'a holiday added'
	);
	pg_assert_same(
		array('feed_add', ws_holidays_settings_input('feed_add', array_merge($old, array('name' => 'Holidays')))),
		ws_holidays_settings_request(array('ws_feed_add' => '1', 'ws_feed' => array('url' => 'webcal://example.com/h.ics', 'name' => 'Holidays', 'department_id' => '3', 'public_only' => '1'))),
		'a calendar address added'
	);
	pg_assert_same(array('holiday_remove', array('event_id' => '9')), ws_holidays_settings_request(array('ws_holiday_remove' => '9')), 'a holiday removed');
	pg_assert_same(array('feed_sync', array('feed_id' => '4')), ws_holidays_settings_request(array('ws_feed_sync' => '4')), 'read now');
	pg_assert_same(array('feed_remove', array('feed_id' => '4')), ws_holidays_settings_request(array('ws_feed_remove' => '4')), 'a calendar address removed');
	pg_assert_same(array('feed_day', array('feed_id' => '', 'event_id' => '9', 'skip' => '1')), ws_holidays_settings_request(array('ws_feed_skip' => '9')), 'a day left out');
	pg_assert_same(array('feed_day', array('feed_id' => '', 'event_id' => '9', 'skip' => '0')), ws_holidays_settings_request(array('ws_feed_count' => '9')), 'a day counted again');
	pg_assert_same(null, ws_holidays_settings_request(array('ws_hol' => array('name' => 'x'), 'ws_day_hours' => '8')), 'a plain save asks for nothing');
	pg_assert_same(null, ws_holidays_settings_request(array('ws_feed_sync' => array('4'))), 'a button posted as a list is no button');
	pg_assert_same(array('name' => '', 'from' => '', 'to' => '', 'department_id' => '', 'yearly' => ''), ws_holidays_settings_input('holiday_add', array('name' => array('x'))), 'a list reads as empty');
	pg_assert_same(array(), ws_holidays_settings_input('nothing', $old), 'an unknown action reads nothing');
}

function test_settings_workspace_calendar_buttons_round_trip()
{
	$cases = array(
		array('holiday_remove', array('event_id' => 9), array('ws_holiday_remove', 9)),
		array('feed_sync', array('feed_id' => 4), array('ws_feed_sync', 4)),
		array('feed_remove', array('feed_id' => 4), array('ws_feed_remove', 4)),
		array('feed_day', array('feed_id' => 4, 'event_id' => 9, 'skip' => 1), array('ws_feed_skip', 9)),
		array('feed_day', array('feed_id' => 4, 'event_id' => 9, 'skip' => 0), array('ws_feed_count', 9)),
	);

	foreach ($cases as $case) {
		$button = ws_holidays_settings_button($case[0], $case[1]);

		pg_assert_same($case[2], $button, $case[0] . ' button');

		$read = ws_holidays_settings_request(array($button[0] => (string) $button[1]));

		pg_assert_same($case[0], $read[0] ?? null, $case[0] . ' read back');
	}

	pg_assert_same('ws_hol[from]', ws_holidays_settings_field('holiday_add', 'from'), 'a holiday\'s field');
	pg_assert_same('ws_feed[url]', ws_holidays_settings_field('feed_add', 'url'), 'a calendar address\'s field');
}

function test_settings_workspace_calendar_add_checks()
{
	$refused = function ($action, $fields) {
		return ws_holidays_settings_refused($action, ws_holidays_settings_input($action, $fields));
	};

	pg_assert_same(array(), $refused('holiday_add', array('name' => 'A', 'from' => '2026-12-01')), 'one day');
	pg_assert_same(array(), $refused('holiday_add', array('name' => 'A', 'from' => '01.12.2026', 'to' => '2026-12-03')), 'a run of days');
	pg_assert_same(array('name'), array_keys($refused('holiday_add', array('name' => ' ', 'from' => '2026-12-01'))), 'no name');
	pg_assert_same(array('from'), array_keys($refused('holiday_add', array('name' => 'A', 'from' => 'soon'))), 'no first day');
	pg_assert_same(array('to'), array_keys($refused('holiday_add', array('name' => 'A', 'from' => '2026-12-01', 'to' => '2026-13-01'))), 'an unreadable last day');
	pg_assert_same(array('to' => lang('The last day cannot be before the first day.')), $refused('holiday_add', array('name' => 'A', 'from' => '2026-12-02', 'to' => '2026-12-01')), 'a last day before the first');

	$checked = ws_holiday_input(array('name' => "  Two\n words ", 'from' => '2026-12-01', 'to' => ''));

	pg_assert_same(array('Two words', '2026-12-01', '2026-12-01'), array($checked['name'], $checked['from'], $checked['to']), 'the name tidied, one day');

	pg_assert_same(array(), $refused('feed_add', array('url' => 'webcal://example.com/h.ics')), 'a webcal address');
	pg_assert_same(array('url'), array_keys($refused('feed_add', array('url' => 'http://example.com/h.ics'))), 'not https');
	pg_assert_same(array('url'), array_keys($refused('feed_add', array('url' => 'https://' . str_repeat('a', 2000)))), 'too long');
	pg_assert_same('https://example.com/h.ics', ws_holiday_feed_address('webcal://example.com/h.ics'), 'webcal read as https');
	pg_assert_same(array(), $refused('feed_sync', array('feed_id' => 4)), 'a row action has no field to check');
}

function test_settings_workspace_calendar_card_and_save()
{
	$cards = settings_workspace_calendar_test_source('includes/settings/workspace_team.php');

	pg_assert_contains('<div id="pgset-ws-calendar" class="pg-set-card">', $cards, 'the card');
	pg_assert_contains("pg_settings_drawer('pgset-ws-holiday-new'", $cards, 'a holiday is added in a drawer');
	pg_assert_contains("pg_settings_drawer('pgset-ws-feed-new'", $cards, 'a calendar address is added in a drawer');
	pg_assert_contains("pg_settings_drawer('pgset-ws-feed-' . \$ws_id", $cards, 'a calendar\'s days in a drawer');
	pg_assert_contains('<button type="submit" name="ws_holiday_add" value="1" form="\' . h($ws_form) . \'"', $cards, 'add a holiday is a named button of the settings form');
	pg_assert_contains('<button type="submit" name="ws_feed_add" value="1" form="\' . h($ws_form) . \'"', $cards, 'add a calendar is a named button of the settings form');
	pg_assert_contains("list(\$name, \$value) = ws_holidays_settings_button(\$action, \$values);", $cards, 'row actions named by the shared function');
	pg_assert_same(0, preg_match('/<form[\s>]/', $cards), 'no form of its own in the pane');
	pg_assert_contains('<a href="#pgset-ws-calendar">', $cards, 'Defaults points at the card in the same pane');

	// A drawer's fields carry no required: it would block the pane's Save.
	$fields = settings_workspace_calendar_test_source('includes/workspace/workdays.php');

	pg_assert_contains("\$required = (\$form === null) ? ' required' : '';", $fields, 'required only on the old screen');

	$save = settings_workspace_calendar_test_source('includes/settings/workspace_team.save.php');
	$request = strpos($save, '$ws_calendar = ws_holidays_settings_request($_POST);');
	$mark = strpos($save, '$liveform->mark_error(ws_holidays_settings_field($ws_calendar[0], $ws_field), $ws_sentence);');
	$stop = strpos($save, "if (\$liveform->check_form_errors()) {\n    return;\n}");
	$post = strpos($save, 'ws_holidays_settings_post($ws_viewer, $ws_calendar[0], $liveform, $ws_calendar[1], true);');

	pg_assert_true($request !== false && $mark !== false && $stop !== false && $post !== false, 'the request, the marks, the stop and the job are there');
	pg_assert_true($request < $stop && $mark < $stop && $stop < $post, 'checked before the stop, run after the save');

	pg_assert_same(0, preg_match('/\$_POST/', substr($fields, (int) strpos($fields, 'function ws_holidays_settings_post('), 4000)), 'the shared save reads no request');
}
