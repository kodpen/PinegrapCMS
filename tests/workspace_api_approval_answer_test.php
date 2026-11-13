<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what an approval request asks with Approve as the external API
 * shows it (includes/workspace/api_resources.php): answer and answer_label
 * of WorkspaceApproval, which follow the panel's card (ws_approvals_map()),
 * and that a list reads them once for its page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');

// An open request a flow's approval box waits on asks what the box set.
function test_workspace_api_approval_answer_of_an_open_request()
{
	$asked = array(
		4 => array('answer' => 'number', 'label' => 'How many kg arrived?'),
		5 => array('answer' => 'text', 'label' => ''),
	);

	pg_assert_same(array('answer' => 'number', 'label' => 'How many kg arrived?'),
		ws_api_approval_answer(array('id' => '4', 'closed_at' => '0'), $asked));
	pg_assert_same(array('answer' => 'text', 'label' => ''),
		ws_api_approval_answer(array('id' => 5, 'closed_at' => 0), $asked), 'a box with no question has no label');
}

// A request nobody's box waits on, and a closed one, ask for nothing.
function test_workspace_api_approval_answer_is_none_otherwise()
{
	$asked = array(4 => array('answer' => 'number', 'label' => 'How many kg arrived?'));
	$none = array('answer' => 'none', 'label' => '');

	pg_assert_same($none, ws_api_approval_answer(array('id' => 9, 'closed_at' => 0), $asked), 'asked by a person');
	pg_assert_same($none, ws_api_approval_answer(array('id' => 9, 'closed_at' => 0), array()));
	pg_assert_same($none, ws_api_approval_answer(array('id' => 4, 'closed_at' => 1762900000), $asked), 'closed, as on the card');
	pg_assert_same($none, ws_api_approval_answer(array('id' => 4, 'closed_at' => 0), array(4 => array('answer' => 'html', 'label' => 'x'))), 'an unknown kind');
	pg_assert_same($none, ws_api_approval_answer(array('id' => 4, 'closed_at' => 0), array(4 => array('answer' => 'none', 'label' => 'x'))), 'no label without an answer');
}

// Only the open requests of a page are looked up.
function test_workspace_api_approval_open_ids()
{
	pg_assert_same(array(7, 3), ws_api_approval_open_ids(array(
		array('id' => '7', 'closed_at' => '0'),
		array('id' => '5', 'closed_at' => '1762900000'),
		array('id' => '3', 'closed_at' => 0),
	)));
	pg_assert_same(array(), ws_api_approval_open_ids(array()));
	pg_assert_same(array(), ws_api_approval_open_ids(array(false)), 'a failed read gives no rows');
}

// The object carries both fields, and the label is null when there is none.
function test_workspace_api_approval_answer_is_on_the_object()
{
	$schema = ws_api_approval_schema();

	pg_assert_same('string', $schema['answer'] ?? null);
	pg_assert_same('string?', $schema['answer_label'] ?? null);

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');

	pg_assert_contains("'answer'            => \$asks['answer'],", $source);
	pg_assert_contains("'answer_label'      => (\$asks['label'] !== '') ? \$asks['label'] : null,", $source);
}

// A list and My day read what is asked once, not once per request.
function test_workspace_api_approval_answer_is_read_once_for_a_page()
{
	$list = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');
	$day = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_day.php');

	pg_assert_contains('$asked = ws_approval_answers_asked(ws_api_approval_open_ids($rows));', $list);
	pg_assert_contains('ws_api_approval_present($viewer, $row, $asked)', $list);
	pg_assert_contains('$asked = ws_approval_answers_asked(ws_api_approval_open_ids($approvals));', $day);
	pg_assert_contains('ws_api_approval_present($viewer, $row, $asked)', $day);
}

// The routes say what the fields mean and where the answer goes.
function test_workspace_api_approval_answer_is_documented()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_contains('answer_label', $routes['workspace.approvals.list']['description']);
	pg_assert_contains('none, or text or number', $routes['workspace.approvals.list']['description']);
	pg_assert_contains('carry that answer in note', $routes['workspace.approvals.decide']['description']);
}
