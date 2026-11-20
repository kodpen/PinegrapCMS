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

function test_ai_connections_voice_on_openai_by_address()
{
	pg_assert_true(pg_ai_connection_voice_on_openai(''), 'no address is OpenAI');
	pg_assert_true(pg_ai_connection_voice_on_openai(' https://api.openai.com/v1/ '), 'OpenAI\'s base');
	pg_assert_true(pg_ai_connection_voice_on_openai('https://api.openai.com/v1/audio/speech'), 'the endpoint pasted after it');
	pg_assert_true(!pg_ai_connection_voice_on_openai('https://api.groq.com/openai/v1'), 'another address');
	pg_assert_true(!pg_ai_connection_voice_on_openai('https://api.openai.com.evil.test/v1'), 'a look-alike host');
}

function test_ai_connections_voice_move_first_key_becomes_the_connection()
{
	$move = pg_ai_connection_voice_move('', array('url' => '', 'key' => 'sk-voice-1', 'speech_url' => '', 'speech_key' => ''));

	pg_assert_true($move['store'], 'no connection: the transcripts\' key becomes it');
	pg_assert_same('sk-voice-1', $move['connection'], 'the transcripts\' key');
	pg_assert_same(array('ws_voice_key'), $move['empty'], 'and is emptied, read from the connection');
}

function test_ai_connections_voice_move_equal_emptied_different_kept()
{
	$move = pg_ai_connection_voice_move('sk-conn-1', array('url' => 'https://api.openai.com/v1', 'key' => 'sk-conn-1', 'speech_url' => '', 'speech_key' => 'sk-other-2'));

	pg_assert_true(!$move['store'], 'an existing connection stays');
	pg_assert_same(array('ws_voice_key'), $move['empty'], 'the equal key is emptied; the spoken answers\' different key stays');
}

function test_ai_connections_voice_move_leaves_another_address_alone()
{
	$move = pg_ai_connection_voice_move('', array('url' => 'https://api.groq.com/openai/v1', 'key' => 'gsk-groq-1', 'speech_url' => '', 'speech_key' => 'gsk-groq-1'));

	pg_assert_true(!$move['store'], 'a key of another address never becomes OpenAI\'s connection');
	pg_assert_same(array(), $move['empty'], 'and is kept, with the spoken answers that follow it');

	$move = pg_ai_connection_voice_move('', array('url' => 'https://api.groq.com/openai/v1', 'key' => 'gsk-groq-1', 'speech_url' => 'https://api.openai.com/v1', 'speech_key' => 'sk-speech-1'));

	pg_assert_same('sk-speech-1', $move['connection'], 'spoken answers on OpenAI give theirs');
	pg_assert_same(array('ws_voice_speech_key'), $move['empty'], 'only the spoken answers\' key moves');
}

function test_ai_connections_voice_move_run_again_changes_nothing()
{
	$voice = array('url' => '', 'key' => 'sk-voice-1', 'speech_url' => '', 'speech_key' => 'sk-speech-2');
	$first = pg_ai_connection_voice_move('', $voice);

	foreach ($first['empty'] as $name) {
		$voice[($name === 'ws_voice_key') ? 'key' : 'speech_key'] = '';
	}

	$again = pg_ai_connection_voice_move($first['connection'], $voice);

	pg_assert_same(array('ws_voice_key'), $first['empty'], 'the first run empties the equal key only');
	pg_assert_true(!$again['store'], 'run again: nothing is stored');
	pg_assert_same(array(), $again['empty'], 'run again: nothing is emptied');
	pg_assert_same('sk-speech-2', $voice['speech_key'], 'the different key still works');
}

function test_ai_connections_voice_data_step_is_called_and_guarded()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_ai_connections_voice();    // 11.0.241', $source, 'the open version runs the voice step');
	pg_assert_true(strpos($source, 'upgrade_2026_11_0_ai_connections();') < strpos($source, 'upgrade_2026_11_0_ai_connections_voice();'), 'after the assistants\' step, whose first key wins');
	pg_assert_contains('pg_ai_connection_voice_move(pg_ai_connection_key(\'openai\')', $source, 'the step follows the pure rule');
	pg_assert_contains('if (!pg_connect_can_seal()) {', $source, 'a site without an encryption key is skipped');
}

function test_ai_connections_page_of_a_provider()
{
	pg_assert_same('azure_openai', pg_ai_connection_service_of('azure'), 'Azure OpenAI\'s registry key');
	pg_assert_same('connections.php?service=openai', pg_ai_connection_page_url('openai'), 'its page on Connections');
	pg_assert_same('', pg_ai_connection_page_url('custom'), 'a custom address has no page');
}

function test_ai_connections_block_says_state_and_way()
{
	$off = pg_ai_connection_block_html('openai', 'OpenAI', false);
	$on = pg_ai_connection_block_html('anthropic', 'Anthropic', true, array('name' => 'ws_agent[3][key_clear]', 'id' => 'k3', 'attrs' => ' disabled'));

	pg_assert_contains('href="connections.php?service=openai"', $off, 'it leads to the provider\'s page');
	pg_assert_contains('Connect in Connections', $off, 'not connected: Connect in Connections');
	pg_assert_contains('Not connected', $off, 'with its state');
	pg_assert_contains('Manage in Connections', $on, 'connected: Manage in Connections');
	pg_assert_true(strpos($off, 'type="password"') === false && strpos($on, 'type="password"') === false, 'no key field');
	pg_assert_contains('Uses its own key', $on, 'a feature with its own key says so');
	pg_assert_contains('name="ws_agent[3][key_clear]" value="1" id="k3" disabled', $on, 'its box gives the own key up with the Save');
}

function test_ai_connections_agent_form_takes_no_key_for_a_connection()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/agents_settings.php');

	pg_assert_contains('pg_ai_connection_block_html($provider_id,', $source, 'the chosen provider\'s connection under the select');
	pg_assert_contains("'provider=' . implode('|', \$own_key)", $source, 'the key field only for a provider without a connection');
	pg_assert_contains('if ($connected && is_string($key) && ($key !== \'\')) {', $source, 'a key typed for a connected provider is not stored');
	pg_assert_contains('<select class="pg-input pg-input-sm" name="\' . h($name($field)) . \'"', $source, 'the selects post their names');
}

function test_ai_connections_voice_move_keeps_a_speech_key_whose_fallback_is_another_key()
{
	// Spoken answers that follow the transcripts fall back to the
	// transcripts' key: emptying theirs while the transcripts keep a
	// different key of their own would switch them to that key.
	$move = pg_ai_connection_voice_move('sk-conn-1', array('url' => '', 'key' => 'sk-own-2', 'speech_url' => '', 'speech_key' => 'sk-conn-1'));

	pg_assert_same(array(), $move['empty'], 'the spoken answers keep the key they called with');

	$move = pg_ai_connection_voice_move('sk-conn-1', array('url' => '', 'key' => 'sk-conn-1', 'speech_url' => '', 'speech_key' => 'sk-conn-1'));

	pg_assert_same(array('ws_voice_key', 'ws_voice_speech_key'), $move['empty'], 'both equal to the connection: both read it');
}

function test_ai_connections_voice_speech_on_connection_by_address()
{
	pg_assert_true(pg_ai_connection_voice_speech_on_connection('https://api.openai.com/v1', 'https://api.groq.com/openai/v1'), 'speech on OpenAI, transcripts elsewhere: OpenAI\'s connection');
	pg_assert_true(!pg_ai_connection_voice_speech_on_connection('', 'https://api.groq.com/openai/v1'), 'speech following another service takes its key');
	pg_assert_true(!pg_ai_connection_voice_speech_on_connection('https://api.openai.com/v1', ''), 'both on OpenAI: the transcripts\' key, which falls back to the connection');
	pg_assert_true(!pg_ai_connection_voice_speech_on_connection('https://api.groq.com/openai/v1', ''), 'speech on another service never takes OpenAI\'s key');
}
