<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Workspace heading of the Site Settings dialog and its gate: a holder of
 * the Workspace settings right opens the three Workspace categories and
 * nothing else, a manager without the right is refused them, every other
 * category stays the manager's. The pane's endpoint, the page path, both
 * save paths, the dialog's list and its search ask the same question.
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

function settings_workspace_gate_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

// The registry's categories as key => array(group, gate), read from the
// source: pg_settings_categories() asks the database.
function settings_workspace_gate_test_categories()
{
	$source = settings_workspace_gate_test_source('includes/settings/registry.php');

	preg_match_all("/\n        '([a-z_]+)' => array\(\n            'label' => .*?\n            'group' => '([a-z_]+)',\n(?:            'gate'  => '([a-z_]+)',\n)?/", $source, $match, PREG_SET_ORDER);

	$categories = array();

	foreach ($match as $hit) {
		$categories[$hit[1]] = array('group' => $hit[2]);

		if (isset($hit[3]) && ($hit[3] !== '')) {
			$categories[$hit[1]]['gate'] = $hit[3];
		}
	}

	return $categories;
}

function settings_workspace_gate_test_workspace_keys()
{
	return array('workspace_team', 'workspace_assistants', 'workspace_channels');
}

function test_settings_workspace_gate_heading_and_keys()
{
	$categories = settings_workspace_gate_test_categories();

	pg_assert_same(18, count($categories), 'the registry\'s categories are read');
	pg_assert_same(settings_workspace_gate_test_workspace_keys(), array_slice(array_keys($categories), -3), 'the three Workspace categories close the list');

	foreach ($categories as $key => $category) {
		$workspace = in_array($key, settings_workspace_gate_test_workspace_keys(), true);

		pg_assert_same($workspace ? 'workspace' : null, $category['gate'] ?? null, $key . ': gated by the Workspace settings right only when it is a Workspace category');
		pg_assert_same($workspace, $category['group'] === 'workspace', $key . ': under the Workspace heading only when it is a Workspace category');
		pg_assert_same($workspace, pg_settings_category_is_workspace($category), $key . ': known as a Workspace category');
	}

	$groups = settings_workspace_gate_test_source('includes/settings/registry.php');
	pg_assert_contains("'workspace' => lang('Workspace'),", $groups, 'the fifth heading');

	foreach (settings_workspace_gate_test_workspace_keys() as $key) {
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/includes/settings/' . $key . '.php'), $key . ': its cards');
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/includes/settings/' . $key . '.save.php'), $key . ': its save');
		pg_assert_contains("define('PG_SETTINGS_KEY', '" . $key . "');", settings_workspace_gate_test_source('settings_' . $key . '.php'), $key . ': its page');
		pg_assert_contains("'settings_" . $key . "'", settings_workspace_gate_test_source('get_help_url.php'), $key . ': its help row');
		pg_assert_contains("case 'settings_" . $key . ".php':", settings_workspace_gate_test_source('includes/fn/output.php'), $key . ': its menu row');
	}
}

// Case 1: a basic user holding the Workspace settings right opens the three
// Workspace categories and nothing else.
function test_settings_workspace_gate_right_holder_opens_workspace_only()
{
	$categories = settings_workspace_gate_test_categories();
	$reader = array('manager' => false, 'workspace' => true);

	pg_assert_same(settings_workspace_gate_test_workspace_keys(), array_keys(pg_settings_categories_for(array('role' => 3), $reader, $categories)));

	foreach ($categories as $key => $category) {
		pg_assert_same(pg_settings_category_is_workspace($category), pg_settings_category_open($category, $reader), $key);
	}
}

// Case 2: a manager without the right (kept out of the team) is refused the
// Workspace categories and keeps every other one.
function test_settings_workspace_gate_manager_without_right_is_refused()
{
	$categories = settings_workspace_gate_test_categories();
	$reader = array('manager' => true, 'workspace' => false);
	$open = array_keys(pg_settings_categories_for(array('role' => 2), $reader, $categories));

	pg_assert_same(15, count($open), 'every other category');

	foreach (settings_workspace_gate_test_workspace_keys() as $key) {
		pg_assert_false(in_array($key, $open, true), $key . ' is refused');
		pg_assert_false(pg_settings_category_open($categories[$key], $reader), $key . ' does not open');
	}
}

// Case 3: every other category stays the manager's; a manager in the team
// opens all of them, a reader with neither opens none.
function test_settings_workspace_gate_other_categories_stay_the_managers()
{
	$categories = settings_workspace_gate_test_categories();

	pg_assert_same(array_keys($categories), array_keys(pg_settings_categories_for(array('role' => 0), array('manager' => true, 'workspace' => true), $categories)), 'a manager with the right opens every category');
	pg_assert_same(array(), pg_settings_categories_for(array('role' => 3), array('manager' => false, 'workspace' => false), $categories), 'a reader with neither opens none');

	foreach ($categories as $key => $category) {
		if (!pg_settings_category_is_workspace($category)) {
			pg_assert_true(pg_settings_category_open($category, array('manager' => true, 'workspace' => false)), $key . ': a manager opens it');
			pg_assert_false(pg_settings_category_open($category, array('manager' => false, 'workspace' => true)), $key . ': the right alone does not open it');
		}
	}
}

// The doors ask the gate: the pane's endpoint before it draws or saves, the
// page path before either, the save path itself, the dialog's list and its
// search.
function test_settings_workspace_gate_doors_ask_it()
{
	$pane = settings_workspace_gate_test_source('settings_pane.php');
	$gate = strpos($pane, 'pg_settings_may_open($pane, $user, $reader)');

	pg_assert_true($gate !== false, 'the endpoint asks for the pane');
	pg_assert_true($gate < strpos($pane, 'pg_settings_fragment($pane'), 'before the cards are drawn');
	pg_assert_true($gate < strpos($pane, 'pg_settings_apply($pane'), 'before the save');
	pg_assert_false(strpos($pane, "\nvalidate_area_access(\$user, 'manager');") !== false, 'the manager role alone no longer opens the endpoint');

	$screen = settings_workspace_gate_test_source('includes/settings/screen.php');
	$gate = strpos($screen, 'pg_settings_may_open($pg_settings_key, $user)');

	pg_assert_true($gate !== false, 'the page path asks for its category');
	pg_assert_true($gate < strpos($screen, 'if (!$_POST) {'), 'before it draws or saves');
	pg_assert_contains('pg_settings_categories_for($user)', $screen, 'the page\'s switcher lists the reader\'s categories');

	$fragment = settings_workspace_gate_test_source('includes/settings/fragment.php');
	pg_assert_same(2, substr_count($fragment, 'pg_settings_may_open($key, $user)'), 'the drawing and the save ask again');

	pg_assert_contains('pg_settings_categories_for($user', settings_workspace_gate_test_source('includes/settings/modal.php'), 'the dialog lists the reader\'s categories');
	pg_assert_contains('pg_settings_categories_for($user', settings_workspace_gate_test_source('includes/settings/search.php'), 'the search indexes the reader\'s categories');
	pg_assert_contains('pg_settings_categories_for($user', settings_workspace_gate_test_source('includes/panel/search.php'), 'the command palette lists the reader\'s categories');
}
