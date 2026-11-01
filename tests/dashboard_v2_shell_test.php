<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's shell (includes/dashboard/v2/): the card
 * list, the sign-in and switch upserts, the greeting by the hour, the switch
 * row, the JSON printed into the page, and that welcome.php and the sign-in
 * reach it the way the plan fixes.
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
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page.php');
require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

function test_dashboard_v2_card_list_has_thirty_valid_entries()
{
	$cards = pg_dashboard_v2_cards();
	$categories = pg_dashboard_v2_card_categories();

	pg_assert_same(30, count($cards), 'thirty cards');

	foreach ($cards as $id => $card) {
		pg_assert_true(is_string($id) && preg_match('/^[a-z]+$/', $id) === 1, $id . ': a string id, never a widget number');
		pg_assert_true(isset($categories[$card['category']]), $id . ': known category');
		pg_assert_true(in_array($card['width'], array('S', 'M', 'L'), true), $id . ': width S, M or L');
		pg_assert_true(($card['period'] === null) || in_array($card['period'], array(0, 1, 2), true), $id . ': period null or 0-2');
		pg_assert_true(in_array($card['gate'], array('all', 'staff', 'admin', 'workspace', 'ecommerce', 'calendars', 'widget'), true), $id . ': known gate');
		pg_assert_true(($card['gate'] !== 'widget') || ($card['widget'] > 0), $id . ': a widget gate names its widget');
		pg_assert_true(is_bool($card['built']), $id . ': built is a flag');
		pg_assert_true(strpos($card['icon'], 'bi-') === 0, $id . ': Bootstrap icon');
	}

	pg_assert_true(!isset($cards['14']) && !in_array(14, array_column($cards, 'widget'), true), 'retired widget 14 is not reused');
}

function test_dashboard_v2_sign_in_query_moves_the_last_sign_in()
{
	$query = pg_dashboard_sign_in_query('7', 1700000000);

	pg_assert_contains('VALUES (7, 0, 0, \'\', 1700000000, 0)', $query, 'a first sign-in creates the row with the switch off');
	pg_assert_true(strpos($query, 'signed_in_before = signed_in_at') < strpos($query, 'signed_in_at = VALUES(signed_in_at)'), 'the old time is copied before it is overwritten');
	pg_assert_false(strpos($query, 'enabled =') !== false, 'a sign-in never touches the switch');
}

function test_dashboard_v2_switch_query_sets_only_the_switch()
{
	$on = pg_dashboard_v2_switch_query(5, true, 100);
	$off = pg_dashboard_v2_switch_query(5, false, 100);

	pg_assert_contains('VALUES (5, 0, 1, \'\', 100, 5)', $on, 'on');
	pg_assert_contains('VALUES (5, 0, 0, \'\', 100, 5)', $off, 'off');
	pg_assert_false(strpos($on, 'layout =') !== false, 'a flip keeps the saved layout');
	pg_assert_false(strpos($on, 'signed_in') !== false, 'a flip keeps the sign-in times');
}

function test_dashboard_v2_switch_state()
{
	pg_assert_false(pg_dashboard_v2_is_enabled(null), 'no row: the current dashboard');
	pg_assert_false(pg_dashboard_v2_is_enabled(array('enabled' => '0')), 'off');
	pg_assert_true(pg_dashboard_v2_is_enabled(array('enabled' => '1')), 'on');
}

function test_dashboard_v2_greeting_follows_the_workspace_rule()
{
	$expected = array(0 => 'Good night', 4 => 'Good night', 5 => 'Good morning', 11 => 'Good morning',
		12 => 'Good afternoon', 17 => 'Good afternoon', 18 => 'Good evening', 22 => 'Good evening', 23 => 'Good night');

	foreach ($expected as $hour => $text) {
		pg_assert_same(lang($text), pg_dashboard_v2_greeting($hour), 'hour ' . $hour);
	}
}

function test_dashboard_v2_switch_row_markup()
{
	$off = pg_dashboard_v2_switch_row(false);
	$on = pg_dashboard_v2_switch_row(true, '<button type="button">x</button>');

	pg_assert_contains('id="pg_dash_switch"', $off, 'the switch');
	pg_assert_contains('role="switch"', $off, 'announced as a switch');
	pg_assert_false(strpos($off, ' checked') !== false, 'off is unchecked');
	pg_assert_contains(' checked', $on, 'on is checked');
	pg_assert_contains('<label for="pg_dash_switch"', $off, 'labelled');
	pg_assert_false(strpos($off, 'pg-dash-switch-aside') !== false, 'no aside on the current dashboard');
	pg_assert_contains('pg-dash-switch-aside', $on, 'aside on the new page');
}

function test_dashboard_v2_json_cannot_close_its_script()
{
	$json = pg_dashboard_v2_json(array('title' => '</script><b>&\'"'));

	pg_assert_false(strpos($json, '</script>') !== false, 'no closing tag');
	pg_assert_false(strpos($json, '<') !== false, 'no raw <');
	pg_assert_same(array('title' => '</script><b>&\'"'), json_decode($json, true), 'round trip');
}

function test_dashboard_v2_config_lists_every_card()
{
	$config = pg_dashboard_v2_config();

	pg_assert_same(30, count($config['cards']), 'every card');
	pg_assert_same(array('id', 'title', 'icon', 'category', 'width', 'period', 'built'), array_keys($config['cards'][0]), 'entry keys');
}

function test_dashboard_v2_is_reached_from_welcome_and_every_sign_in()
{
	$welcome = (string) file_get_contents(PG_FUNCTIONS_DIR . '/welcome.php');
	$auth = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/authentication.php');
	$login_as = (string) file_get_contents(PG_FUNCTIONS_DIR . '/login_as_user.php');

	pg_assert_true(strpos($welcome, 'pg_dashboard_v2_page(') < strpos($welcome, 'pg_page_shell('), 'the branch runs before the current page prints');
	pg_assert_contains('pg_dashboard_v2_switch_row(false)', $welcome, 'the switch row on the current dashboard');
	pg_assert_contains('pg_dashboard_sign_in_mark($user_id);', $auth, 'pg_session_sign_in() marks the sign-in');
	pg_assert_contains('pg_session_sign_in($login_as_user_id, $user[\'username\'], false);', $login_as, 'signing in as someone else does not');

	$actions = pg_panel_actions();
	pg_assert_true(isset($actions['dashboard_switch']) && $actions['dashboard_switch']['exempt'] && $actions['dashboard_switch']['token'], 'dashboard_switch: every role, token checked');
}
