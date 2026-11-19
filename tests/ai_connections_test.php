<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the AI providers' connections (includes/fn/ai_connections.php):
 * one connection per provider of the registry's AI group, the data step's
 * rule (the first key becomes the connection, equal keys are emptied, a
 * different key is kept) and that it changes nothing run again, the key an
 * assistant calls with, and the page's gate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/ai_connections.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

function test_ai_connections_one_per_model_provider_of_the_registry()
{
	$registry = pg_connections_registry();

	foreach (pg_ai_connection_services() as $id => $provider) {
		pg_assert_true(isset($registry[$id]), $id . ' is a row of the registry');
		pg_assert_same('ai', $registry[$id]['group'], $id . ' is in the AI group');
		pg_assert_same('connections.php', $registry[$id]['place'], $id . ': its key is typed on its page on Connections');
		pg_assert_same(array('kind' => 'reader', 'function' => 'pg_ai_connection_ready', 'args' => array($provider)), $registry[$id]['state'], $id . ': its state is its connection');
		pg_assert_same(array($provider), $registry[$id]['covers']['model'], $id . ' covers its model provider');
	}

	pg_assert_same(7, count(pg_ai_connection_services()), 'seven providers take a key; a custom address is no service');
	pg_assert_same('ai_openai', pg_ai_connection_store_key('openai'), 'kept among the service entries under ai_<provider>');
	pg_assert_true(!pg_ai_connection_known('custom'), 'a custom address has no connection');
	pg_assert_same(78, pg_connections_count(), 'the registry still counts 78 services');
}

function test_ai_connections_move_first_key_becomes_the_connection()
{
	$agents = array(
		array('id' => 3, 'key' => 'sk-first-key'),
		array('id' => 5, 'key' => 'sk-other-key'),
		array('id' => 8, 'key' => 'sk-first-key'),
		array('id' => 9, 'key' => ''),
	);

	$move = pg_ai_connection_move('', $agents);

	pg_assert_same('sk-first-key', $move['connection'], 'the first stored key becomes the connection');
	pg_assert_true($move['store'], 'it is stored');
	pg_assert_same(array(3, 8), $move['empty'], 'the keys equal to it are emptied; a different one stays');
}

function test_ai_connections_move_keeps_an_existing_connection()
{
	$move = pg_ai_connection_move('sk-connection', array(
		array('id' => 1, 'key' => 'sk-own-key'),
		array('id' => 2, 'key' => 'sk-connection'),
	));

	pg_assert_same('sk-connection', $move['connection'], 'an existing connection is never replaced');
	pg_assert_true(!$move['store'], 'nothing is stored');
	pg_assert_same(array(2), $move['empty'], 'only the equal key is emptied');
}

function test_ai_connections_move_run_again_changes_nothing()
{
	$agents = array(array('id' => 4, 'key' => 'sk-a-key-here'), array('id' => 6, 'key' => 'sk-b-key-here'));
	$first = pg_ai_connection_move('', $agents);

	// What the database holds after the first run: the emptied keys are gone.
	$after = array();

	foreach ($agents as $agent) {
		$after[] = in_array($agent['id'], $first['empty'], true) ? array('id' => $agent['id'], 'key' => '') : $agent;
	}

	$second = pg_ai_connection_move($first['connection'], $after);

	pg_assert_true(!$second['store'], 'the second run stores nothing');
	pg_assert_same(array(), $second['empty'], 'and empties nothing');
	pg_assert_same(array('connection' => '', 'store' => false, 'empty' => array()), pg_ai_connection_move('', array()), 'a provider without keys gets no connection');
}

function test_ai_connections_own_key_first()
{
	pg_assert_same('sk-own-key', pg_ai_connection_key_for('unknown', 'sk-own-key'), 'an assistant\'s own key is called with');
	pg_assert_same('', pg_ai_connection_key_for('custom', ''), 'no key and no connection: nothing');
}

function test_ai_connections_users_say_whose_key()
{
	$users = pg_ai_connection_users('openai', array(
		array('id' => 1, 'name' => 'Ada', 'provider' => 'openai', 'key_sealed' => 'abc:def'),
		array('id' => 2, 'name' => 'Bo', 'provider' => 'openai', 'key_sealed' => null),
		array('id' => 3, 'name' => 'Cy', 'provider' => 'mistral', 'key_sealed' => 'abc:def'),
	));

	pg_assert_same(array(
		array('id' => 1, 'name' => 'Ada', 'own' => true),
		array('id' => 2, 'name' => 'Bo', 'own' => false),
	), $users, 'the provider\'s assistants, those with a key of their own marked');
}

function test_ai_connections_key_shape()
{
	pg_assert_true(pg_ai_connection_key_shape_ok('sk-proj-abcdef123'), 'a key');
	pg_assert_true(!pg_ai_connection_key_shape_ok('short'), 'too short');
	pg_assert_true(!pg_ai_connection_key_shape_ok('sk with spaces'), 'no spaces');
}

function test_ai_connections_page_cards_keep_the_assistants_gate()
{
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('openai', array('manager' => true, 'ai' => true)), 'who may change the assistants types the key');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('openai', array('manager' => true, 'ai' => false)), 'a manager without that right sees the state only');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('stripe', array('manager' => true, 'ai' => true)), 'the right opens no other service\'s details');
	pg_assert_same('workspace_assistants', pg_ai_connection_category(), 'the gate of the assistants\' card');
}

function test_ai_connections_data_step_is_called_and_guarded()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_ai_connections();', $source, 'the open version runs the step');
	pg_assert_contains('pg_ai_connection_move(pg_ai_connection_key($provider), $agents)', $source, 'the step follows the pure rule, read against the stored connection');
}
