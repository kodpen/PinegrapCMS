<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for taking a poll back and asking it again (poll_again.php): who
 * the asker is, a taken-back poll refusing votes and writing no result, the
 * new poll's data, and the API's routes and answer.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/poll_parts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/poll_again.php');

function ws_poll_again_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
}

// A person asks with their own message; an application with its own.
function test_ws_poll_is_asker()
{
	$poll = array('created_by' => 4);
	$person = array('sender_kind' => 'user', 'sender_id' => 4);
	$agent = array('sender_kind' => 'app', 'sender_id' => 21);

	pg_assert_true(ws_poll_is_asker($poll, $person, 4), 'the person who asked');
	pg_assert_false(ws_poll_is_asker($poll, $person, 5), 'somebody else');
	pg_assert_false(ws_poll_is_asker($poll, $agent, 4), 'the application\'s owner is not the asker of its poll');
	pg_assert_true(ws_poll_is_asker($poll, $agent, 4, 21), 'the application that asked');
	pg_assert_false(ws_poll_is_asker($poll, $agent, 4, 22), 'another application');
	pg_assert_false(ws_poll_is_asker($poll, $person, 4, 21), 'an application never changes a person\'s poll');
}

// A poll taken back takes no vote, before its closing time is looked at.
function test_ws_poll_vote_state_withdrawn()
{
	$poll = array('id' => 3, 'closed_at' => 0, 'closes_at' => time() - 60, 'withdrawn_at' => time() - 10);
	$state = ws_poll_vote_state($poll, false);

	pg_assert_false($state['ok']);
	pg_assert_same('withdrawn_at', $state['field']);
	pg_assert_false($state['expired'], 'a taken-back poll is not closed by a refused vote');
	pg_assert_true(ws_poll_withdrawn($poll));
	pg_assert_false(ws_poll_withdrawn(array('closed_at' => 0)), 'a poll made before the column');
}

// What is sent replaces the old poll's; options keep their parts, not ids.
function test_ws_poll_again_data()
{
	$old = array('question' => 'Hangisi?', 'multiple' => true, 'anonymous' => false,
		'options' => array(array('id' => 7, 'label' => 'Elma', 'description' => 'Kırmızı'), array('id' => 8, 'label' => 'Armut')));

	$same = ws_poll_again_data($old, array());
	pg_assert_same('Hangisi?', $same['question']);
	pg_assert_true($same['multiple']);
	pg_assert_same(array(array('label' => 'Elma', 'description' => 'Kırmızı'), array('label' => 'Armut')), $same['options'], 'parts carried, ids dropped');
	pg_assert_same(0, $same['closes_at'], 'the closing moment is not carried');

	$changed = ws_poll_again_data($old, array('question' => 'Yeni?', 'options' => array('A', 'B'), 'multiple' => false));
	pg_assert_same('Yeni?', $changed['question']);
	pg_assert_same(array('A', 'B'), $changed['options']);
	pg_assert_false($changed['multiple']);
	pg_assert_true(ws_poll_input($changed)['ok'], 'held to the rules of a new poll');
}

// A taken-back poll writes no result, is never closed by its time, and is
// neither changed nor closed.
function test_ws_poll_withdrawn_writes_no_result()
{
	$close = ws_poll_again_test_source('ws_poll_close');
	pg_assert_true(strpos($close, "withdrawn_at") < strpos($close, 'UPDATE ws_polls SET closed_at'), 'returns before closing');
	pg_assert_contains('withdrawn_at = 0', ws_poll_again_test_source('ws_polls_autoclose'));
	pg_assert_contains("lang('The poll was taken back.')", ws_poll_again_test_source('ws_poll_edit_check'));
	pg_assert_contains("lang('The poll was taken back.')", ws_poll_again_test_source('ws_guest_vote'));
	pg_assert_contains('ws_poll_is_asker(', ws_poll_again_test_source('ws_poll_edit_check'), 'the change knows the asking application');
}

// Asking again creates first and takes the old one back only while open.
function test_ws_poll_ask_again_order()
{
	$source = ws_poll_again_test_source('ws_poll_ask_again');
	pg_assert_true(strpos($source, 'ws_poll_create(') < strpos($source, 'ws_poll_withdraw_write('), 'nothing changes when the new poll is refused');
	pg_assert_contains("again_of = '", $source);
	pg_assert_contains("(int) \$poll['closed_at'] === 0", $source, 'an answered or closed poll stays');
}

// The API: the routes, the asker's application, the dry runs, the answer.
function test_ws_api_polls_withdraw_and_again()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('workspace.polls.withdraw' => '/workspace/polls/{id}/withdraw', 'workspace.polls.again' => '/workspace/polls/{id}/again') as $id => $path) {
		pg_assert_true(isset($routes[$id]), $id);
		pg_assert_same($path, $routes[$id]['path']);
		pg_assert_true(!empty($routes[$id]['dry_run']), $id . ' rehearses');
	}

	pg_assert_same(201, $routes['workspace.polls.again']['status']);

	foreach (array('ws_api_polls_withdraw', 'ws_api_polls_again', 'ws_api_polls_update') as $handler) {
		$source = ws_poll_again_test_source($handler);
		pg_assert_contains('ws_api_poll_asker_app(', $source, $handler . ' lets the asking application in');
		pg_assert_true(strpos($source, '_check(') < strpos($source, 'api_dry_run_stop('), $handler . ': a dry run meets the checks');
	}

	pg_assert_contains('ws_api_own_words_only(', ws_poll_again_test_source('ws_api_poll_asker_app'), 'a person\'s poll stays their own words');
}

// The poll's answer carries the take-back and ask-again fields, null for none.
function test_ws_api_poll_present_again_fields()
{
	$present = ws_api_poll_present(array('id' => 9, 'closes_at' => 0, 'voters' => 0, 'options' => array(), 'result_id' => 0,
		'withdrawn' => true, 'withdrawn_at' => 1793091600, 'again_of' => 0, 'asked_again' => 12, 'asked_again_message' => 340));

	pg_assert_same(array_keys(ws_api_poll_schema()), array_keys($present), 'declared fields in order');
	pg_assert_true($present['withdrawn']);
	pg_assert_same(api_time(1793091600), $present['withdrawn_at']);
	pg_assert_same(null, $present['again_of']);
	pg_assert_same(12, $present['asked_again']);
	pg_assert_same(340, $present['asked_again_message_id']);
	pg_assert_false($present['can_withdraw'], 'false unless the screen says so');
}
