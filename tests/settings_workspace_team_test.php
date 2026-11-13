<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Team and working hours in the Site Settings dialog: the Departments and
 * Team members cards came over from Workspace Settings. Pins the category's
 * sections and lists, the old anchors' new places, a department created or
 * changed in a drawer whose fields join the settings form, the save that
 * checks everything before it writes, and the team member rows' rules (a
 * higher rank's row skipped, unreadable hours refused, nobody taking
 * themselves out of the team).
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

if (!defined('WS_DAY_MINUTES')) {
	define('WS_DAY_MINUTES', 480);
}

if (!defined('WS_WORKDAYS')) {
	define('WS_WORKDAYS', 31);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/team_settings.php');

function settings_workspace_team_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The registry's workspace_team entry, as source text.
function settings_workspace_team_test_entry()
{
	$source = settings_workspace_team_test_source('includes/settings/registry.php');
	$from = strpos($source, "        'workspace_team' => array(");
	$to = strpos($source, "        'workspace_assistants' => array(", (int) $from);

	return ($from === false || $to === false) ? '' : substr($source, $from, $to - $from);
}

function settings_workspace_team_test_candidates()
{
	return array(
		1 => array('id' => 1, 'role' => 0, 'name' => 'Ada'),
		2 => array('id' => 2, 'role' => 2, 'name' => 'Ben'),
		3 => array('id' => 3, 'role' => 3, 'name' => 'Cem'),
	);
}

function test_settings_workspace_team_sections_and_gate()
{
	$entry = settings_workspace_team_test_entry();

	pg_assert_contains("'gate'  => 'workspace',", $entry, 'opened by the Workspace settings right');

	foreach (array('pgset-ws-departments', 'pgset-ws-people', 'pgset-ws-defaults', 'pgset-ws-calendar') as $section) {
		pg_assert_contains("'" . $section . "'", $entry, $section . ' is a section');
	}

	pg_assert_contains("'ws_people'   => 3,", $entry, 'team member rows posted as lists');
	pg_assert_contains("'ws_dept'     => 3,", $entry, 'department fields posted as lists');

	// Not opened without the right, whatever the role.
	$category = array('group' => 'workspace', 'gate' => 'workspace');
	pg_assert_false(pg_settings_category_open($category, array('manager' => true, 'workspace' => false)), 'a manager without the right');
	pg_assert_true(pg_settings_category_open($category, array('manager' => false, 'workspace' => true)), 'a holder of the right');
}

function test_settings_workspace_team_old_anchors_go_to_the_new_cards()
{
	$anchors = pg_settings_legacy_anchors();

	pg_assert_same(array('workspace_team', 'pgset-ws-departments'), $anchors['ws-departments'] ?? null, 'Departments');
	pg_assert_same(array('workspace_team', 'pgset-ws-people'), $anchors['ws-people'] ?? null, 'Team members');
	pg_assert_same(array('workspace_team', 'pgset-ws-defaults'), $anchors['ws-defaults'] ?? null, 'Defaults');
}

function test_settings_workspace_team_cards_and_drawers()
{
	$cards = settings_workspace_team_test_source('includes/settings/workspace_team.php');

	pg_assert_contains('<div id="pgset-ws-departments" class="pg-set-card">', $cards, 'the Departments card');
	pg_assert_contains('<div id="pgset-ws-people" class="pg-set-card">', $cards, 'the Team members card');
	pg_assert_contains('data-bs-toggle="drawer" data-bs-target="#pgset-ws-dept-0"', $cards, 'New department opens a drawer');
	pg_assert_contains('data-bs-toggle="drawer" data-bs-target="#pgset-ws-dept-\' . $ws_id . \'"', $cards, 'Edit opens the department\'s drawer');
	pg_assert_contains('<button type="submit" name="ws_department_archive" value="\' . $ws_id . \'"', $cards, 'archive is a named submit button');
	pg_assert_contains("\$pg_settings_modals[] = ws_settings_department_drawer(", $cards, 'the drawer is handed over beside the pane');
	pg_assert_contains("return 'ws_people[' . (int) \$person_id . '][' . \$field . ']';", $cards, 'team member fields named apart from other categories');
	pg_assert_same(0, preg_match('/<form[\s>]/', $cards), 'no form of its own in the pane');

	// Every card of the old screen's team part has moved: no link card is left.
	pg_assert_false(strpos($cards, 'ws_settings_screen_links_card('), 'no card linking the old screen');
	pg_assert_false(strpos(settings_workspace_team_test_entry(), 'pgset-ws-team-screen'), 'no section for it');

	// Both paths' forms have the id the drawer's fields join.
	pg_assert_contains("\$ws_form = !empty(\$pg_settings_in_dialog) ? 'pg_settings_modal_form' : 'pg_settings_page_form';", $cards, 'the form by path');
	pg_assert_contains('id="pg_settings_modal_form"', settings_workspace_team_test_source('includes/settings/modal.php'), 'the dialog\'s form');
	pg_assert_contains('id="pg_settings_page_form"', settings_workspace_team_test_source('includes/settings/screen.php'), 'the page\'s form');
}

function test_settings_workspace_team_drawer_button_counts_in_the_dialog()
{
	$js = settings_workspace_team_test_source('assets/js/backend.src.js');

	pg_assert_contains('#pg_settings_modal_drawers [type="submit"][form="pg_settings_modal_form"]', $js, 'a drawer button is remembered as the card button');
	pg_assert_contains("['#pg_settings_modal_pane', '#pg_settings_modal_drawers']", $js, 'a refused drawer field is marked');
}

function test_settings_workspace_team_save_checks_before_it_writes()
{
	$save = settings_workspace_team_test_source('includes/settings/workspace_team.save.php');

	$check = strpos($save, 'ws_settings_people_check(');
	$department_check = strpos($save, 'ws_settings_department_check(');
	$stop = strpos($save, "if (\$liveform->check_form_errors()) {\n    return;\n}");
	$defaults = strpos($save, '$ws_refused = ws_settings_defaults_save(');
	$people = strpos($save, '    ws_settings_people_write(');
	$department = strpos($save, '$ws_saved = ws_settings_department_save(');

	pg_assert_true($check !== false && $department_check !== false && $stop !== false, 'the checks and the stop are there');
	pg_assert_true($check < $stop && $department_check < $stop, 'checked before the stop');
	pg_assert_true(($defaults !== false) && ($people !== false) && ($department !== false), 'the writes are there');
	pg_assert_true($stop < $defaults && $stop < $people && $stop < $department, 'nothing written before the stop');
	pg_assert_contains("\$liveform->mark_error('ws_people[' . (int) \$ws_person_id . '][day_hours]', \$ws_sentence);", $save, 'refused hours marked on the field');
	pg_assert_contains("\$liveform->mark_error('ws_dept[' . \$ws_department_id . '][name]', \$ws_department['error']);", $save, 'a refused department marked on its name');
	pg_assert_contains("post_value('ws_department_archive')", $save, 'the archive button');
}

function test_settings_workspace_team_people_check_rules()
{
	$candidates = settings_workspace_team_test_candidates();
	$viewer = array('id' => 2, 'role' => 2);

	$checked = ws_settings_people_check($viewer, $candidates, array(
		1 => array('title' => 'Owner', 'day_hours' => '6', 'excluded' => '1'),
		2 => array('title' => ' Lead ', 'day_hours' => '8', 'workdays' => array('1', '2', '3', '4', '5'), 'excluded' => '1'),
		3 => array('title' => 'Help', 'day_hours' => '4', 'workdays' => array('1'), 'excluded' => '1'),
		9 => array('title' => 'Nobody', 'day_hours' => '4'),
	));

	pg_assert_false(isset($checked['writes'][1]), 'a higher rank\'s row is skipped');
	pg_assert_false(isset($checked['writes'][9]), 'an unknown person is skipped');
	pg_assert_same(array('title' => 'Lead', 'day' => 0, 'mask' => 0, 'excluded' => false), $checked['writes'][2], 'the defaults stored as the default; nobody takes themselves out');
	pg_assert_same(array('title' => 'Help', 'day' => 240, 'mask' => 1, 'excluded' => true), $checked['writes'][3], 'a lower rank\'s row written');
	pg_assert_same(array(), $checked['refused'], 'nothing refused');

	$refused = ws_settings_people_check($viewer, $candidates, array(3 => array('day_hours' => 'abc')));

	pg_assert_same(array(), $refused['writes'], 'an unreadable day is not written');
	pg_assert_true(isset($refused['refused'][3]), 'an unreadable day is refused');
	pg_assert_same(array('writes' => array(), 'refused' => array()), ws_settings_people_check($viewer, $candidates, 'x'), 'no rows posted');
}
