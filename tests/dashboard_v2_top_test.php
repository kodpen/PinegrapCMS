<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's top section (includes/dashboard/v2/top.php,
 * figures.php, page_greeting.php): presets, shortcut gates, the tile
 * fallback and the stored layout read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/top.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_greeting.php');

function pg_test_dash_user($role, $extra = array())
{
	return array_merge(array('id' => 7, 'role' => $role, 'manage_visitors' => 0, 'manage_forms' => 0, 'create_pages' => 0, 'manage_emails' => 0), $extra);
}

// Every preset names known shortcuts, tiles and at most six shortcuts.
function test_dashboard_v2_top_presets_name_known_keys()
{
	$shortcuts = pg_dashboard_v2_shortcuts();
	$figures = pg_dashboard_v2_figures();

	foreach (pg_dashboard_v2_top_presets() as $name => $preset) {
		pg_assert_true(count($preset['actions']) <= PG_DASHBOARD_V2_MAX_ACTIONS, $name . ' actions');
		pg_assert_same(4, count($preset['tiles']), $name . ' four tiles');

		foreach ($preset['actions'] as $key) {
			pg_assert_true(isset($shortcuts[$key]), $name . ' shortcut ' . $key);
		}

		foreach ($preset['tiles'] as $key) {
			pg_assert_true(isset($figures[$key]), $name . ' tile ' . $key);
		}
	}

	foreach (pg_dashboard_v2_shortcut_pool() as $key) {
		pg_assert_true(isset($shortcuts[$key]), 'pool ' . $key);
	}

	pg_assert_same(11, count($figures), 'eleven figures');
}

// Simple and an unknown preset take the first default's top section.
function test_dashboard_v2_top_for_falls_back_to_first_default()
{
	$first = pg_dashboard_v2_top_for(pg_dashboard_v2_first_preset());

	pg_assert_same($first, pg_dashboard_v2_top_for('simple'));
	pg_assert_same($first, pg_dashboard_v2_top_for('nope'));
}

// A shortcut to a screen the person cannot open is dropped, unknown and
// repeated keys too, and six is the most.
function test_dashboard_v2_actions_for_gates_and_caps()
{
	$member = pg_test_dash_user(3);
	$manager = pg_test_dash_user(2);
	$designer = pg_test_dash_user(1);

	pg_assert_same(array(), pg_dashboard_v2_actions_for(array('add_user', 'back_up', 'visual_editor'), $member), 'member');
	pg_assert_same(array('add_user', 'back_up'), pg_dashboard_v2_actions_for(array('add_user', 'back_up', 'visual_editor', 'nope', 'add_user'), $manager), 'manager');
	pg_assert_same(array('add_user', 'visual_editor'), pg_dashboard_v2_actions_for(array('add_user', 'visual_editor'), $designer), 'designer');
	pg_assert_same(array('new_page'), pg_dashboard_v2_actions_for(array('new_page'), pg_test_dash_user(3, array('create_pages' => 1))), 'page right');

	$many = array('new_page', 'upload_file', 'add_user', 'back_up', 'edit_page', 'visual_editor', 'designs');
	pg_assert_same(6, count(pg_dashboard_v2_actions_for($many, pg_test_dash_user(0))), 'six at most');
}

// "+ Add shortcut" takes the next pool entry not drawn and allowed.
function test_dashboard_v2_next_action()
{
	$manager = pg_test_dash_user(2);

	pg_assert_same('new_page', pg_dashboard_v2_next_action(array(), $manager));
	pg_assert_same('upload_file', pg_dashboard_v2_next_action(array('new_page'), $manager));
	pg_assert_same('', pg_dashboard_v2_next_action(array('a', 'b', 'c', 'd', 'e', 'f'), $manager), 'six drawn');
}

// A tile the person may not see turns into the next allowed figure not
// already in the row; a row with fewer allowed figures is shorter.
function test_dashboard_v2_tiles_for_replaces_hidden_figures()
{
	pg_assert_same(array('visits', 'forms', 'seo', 'tasks'),
		pg_dashboard_v2_tiles_for(array('visits', 'forms', 'seo', 'tasks'), array('visits', 'online', 'forms', 'seo', 'tasks')));

	pg_assert_same(array('visits', 'online', 'seo', 'inbox'),
		pg_dashboard_v2_tiles_for(array('visits', 'forms', 'seo', 'tasks'), array('visits', 'online', 'seo', 'inbox')));

	pg_assert_same(array('seo'),
		pg_dashboard_v2_tiles_for(array('visits', 'forms', 'seo', 'tasks'), array('seo')));

	pg_assert_same(array('visits', 'online'),
		pg_dashboard_v2_tiles_for(array('visits', 'visits'), array('visits', 'online')), 'repeat');
}

// The figures follow the role order, and the accessibility count is not
// offered while no stored count exists.
function test_dashboard_v2_figure_gates()
{
	pg_assert_true(pg_dashboard_v2_figure_allowed('seo', pg_test_dash_user(2)), 'seo manager');
	pg_assert_false(pg_dashboard_v2_figure_allowed('seo', pg_test_dash_user(3)), 'seo member');
	pg_assert_true(pg_dashboard_v2_figure_allowed('visits', pg_test_dash_user(3, array('manage_visitors' => 1))), 'visits right');
	pg_assert_false(pg_dashboard_v2_figure_allowed('a11y', pg_test_dash_user(0)), 'a11y');
	pg_assert_false(pg_dashboard_v2_figure_allowed('nope', pg_test_dash_user(0)), 'unknown');
	pg_assert_same(null, pg_dashboard_v2_figure('nope', pg_test_dash_user(0)));
}

function test_dashboard_v2_figure_answer_shape()
{
	$answer = pg_dashboard_v2_figure_answer(1284, 'note', 'loud', 'x.php');

	pg_assert_same(1284, $answer['value']);
	pg_assert_same('', $answer['tone'], 'unknown tone is plain');
	pg_assert_same('good', pg_dashboard_v2_figure_trend(3, 2));
	pg_assert_same('', pg_dashboard_v2_figure_trend(2, 2));
}

// The stored layout's top section is read; anything missing or wrong falls
// back to the preset.
function test_dashboard_v2_top_state_reads_layout()
{
	$state = pg_dashboard_v2_top_state(null);
	pg_assert_same(pg_dashboard_v2_first_preset(), $state['preset']);
	pg_assert_same(array('since' => true, 'actions' => true, 'tiles' => true), $state['show']);

	$state = pg_dashboard_v2_top_state(array('layout' => json_encode(array(
		'preset' => 'blog', 'actions' => array('forms'), 'top' => array('since' => 0)))));
	pg_assert_same('blog', $state['preset']);
	pg_assert_same(array('forms'), $state['actions']);
	pg_assert_same(array('visits', 'subs', 'seo', 'inbox'), $state['tiles']);
	pg_assert_false($state['show']['since']);

	$state = pg_dashboard_v2_top_state(array('layout' => '{broken'));
	pg_assert_same(pg_dashboard_v2_first_preset(), $state['preset'], 'broken JSON');
}

// No chip on the first sign-in.
function test_dashboard_v2_since_chip_needs_an_earlier_sign_in()
{
	pg_assert_same(null, pg_dashboard_v2_since_chip('visits', pg_test_dash_user(0), 0));
	pg_assert_same(null, pg_dashboard_v2_since_chip('drafts', pg_test_dash_user(0), 1700000000));
}
