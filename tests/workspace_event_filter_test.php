<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Work Calendar's department and person filters on plan
 * items (ws_event_in_view()): a person sees what applies to them, a
 * department sees company items, its own items and its members' items.
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

function workspace_event_filter_test_event($scope, $department_id = 0, $people = array())
{
	return array('scope' => $scope, 'department_id' => $department_id, 'people' => $people);
}

function test_workspace_event_in_view_without_a_filter()
{
	pg_assert_true(ws_event_in_view(workspace_event_filter_test_event('department', 2), 0, array(), 0, array()), 'no filter shows everything');
	pg_assert_true(ws_event_in_view(workspace_event_filter_test_event('people', 0, array(118)), 0, array(), 0, array()));
}

function test_workspace_event_in_view_for_a_person()
{
	// Person 119 works in department 1.
	pg_assert_true(ws_event_in_view(workspace_event_filter_test_event('company'), 119, array(1), 0, array()), 'company items apply to everyone');
	pg_assert_true(ws_event_in_view(workspace_event_filter_test_event('department', 1), 119, array(1), 0, array()), 'their department');
	pg_assert_true(!ws_event_in_view(workspace_event_filter_test_event('department', 2), 119, array(1), 0, array()), 'another department');
	pg_assert_true(ws_event_in_view(workspace_event_filter_test_event('people', 0, array(118, 119)), 119, array(1), 0, array()), 'named on the item');
	pg_assert_true(!ws_event_in_view(workspace_event_filter_test_event('people', 0, array(123)), 119, array(1), 0, array()), 'someone else\'s leave');
}

function test_workspace_event_in_view_for_a_department()
{
	$members = array(119, 120);

	pg_assert_true(ws_event_in_view(workspace_event_filter_test_event('company'), 0, array(), 1, $members), 'company items');
	pg_assert_true(ws_event_in_view(workspace_event_filter_test_event('department', 1), 0, array(), 1, $members), 'the department\'s own items');
	pg_assert_true(!ws_event_in_view(workspace_event_filter_test_event('department', 2), 0, array(), 1, $members), 'another department\'s meeting');
	pg_assert_true(ws_event_in_view(workspace_event_filter_test_event('people', 0, array(120)), 0, array(), 1, $members), 'a member\'s leave');
	pg_assert_true(!ws_event_in_view(workspace_event_filter_test_event('people', 0, array(123)), 0, array(), 1, $members), 'a leave of someone outside it');
}

function test_workspace_event_in_view_for_both()
{
	pg_assert_true(!ws_event_in_view(workspace_event_filter_test_event('people', 0, array(123)), 119, array(1), 2, array(123)), 'both filters must hold');
	pg_assert_true(ws_event_in_view(workspace_event_filter_test_event('company'), 119, array(1), 2, array(123)));
}
