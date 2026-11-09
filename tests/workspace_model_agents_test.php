<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the model assistants (includes/workspace/model_agents.php) that
 * need no database: the providers, the address and headers of a call, the
 * body made fit for each API, the request's agent, the hold, the address
 * check, the config a running assistant sees, that ai.php reaches the
 * gateway's licence and hold only for Pinegrap AI's own requests, that the
 * loop's failure sentences name the assistant, and that its own row in a
 * channel decides whether it is asked there.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

function test_workspace_model_providers_complete()
{
	$providers = ws_model_providers();
	pg_assert_same(array('openai', 'gemini', 'mistral', 'deepseek', 'openrouter', 'azure', 'custom'), array_keys($providers));

	foreach ($providers as $id => $provider) {
		pg_assert_true(strlen($id) <= 32, 'fits ws_agents.provider');
		pg_assert_true($provider['terms'] !== '', $id . ' says what it does with what is sent');
		pg_assert_true(($provider['base'] === '') || (strpos($provider['base'], 'https://') === 0), $id . ' fixed base is https');
		pg_assert_true(lang($provider['terms']) !== '', $id . ' terms translate');
	}

	pg_assert_true(!$providers['custom']['key'], 'a local server may have no key');
	pg_assert_true($providers['custom']['http'] && !$providers['azure']['http'], 'only a custom address may be plain http');
	pg_assert_same('', ws_model_provider_terms('nope'));
}

function test_workspace_model_endpoint()
{
	pg_assert_same('https://api.openai.com/v1/chat/completions', ws_model_endpoint('openai', 'https://evil.example/v1'), 'a fixed base ignores what is given');
	pg_assert_same('https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', ws_model_endpoint('gemini', ''));
	pg_assert_same('https://r.openai.azure.com/openai/v1/chat/completions', ws_model_endpoint('azure', 'https://r.openai.azure.com/openai/v1/'));
	pg_assert_same('http://localhost:11434/v1/chat/completions', ws_model_endpoint('custom', 'http://localhost:11434/v1/chat/completions'), 'a full address is kept');
	pg_assert_same('http://localhost:11434/v1/models', ws_model_endpoint('custom', 'http://localhost:11434/v1', '/models'));
}

function test_workspace_model_headers()
{
	$openai = ws_model_headers('openai', ' sk-1 ');
	pg_assert_true(in_array('Authorization: Bearer sk-1', $openai, true));
	pg_assert_true(!in_array('api-key: sk-1', $openai, true));
	pg_assert_true(in_array('api-key: az', ws_model_headers('azure', 'az'), true), 'Azure also reads api-key');

	foreach (ws_model_headers('custom', '') as $header) {
		pg_assert_true(stripos($header, 'Authorization') === false, 'no empty bearer');
	}
}

function test_workspace_model_payload()
{
	$body = array('model' => 'gpt-oss', 'messages' => array(), 'reasoning_effort' => 'low', 'max_tokens' => 3000, 'tool_choice' => 'required');

	$openai = ws_model_payload('openai', 'gpt-4.1-mini', $body);
	pg_assert_same('gpt-4.1-mini', $openai['model']);
	pg_assert_true(!isset($openai['reasoning_effort']) && !isset($openai['max_tokens']));
	pg_assert_same(3000, $openai['max_completion_tokens']);
	pg_assert_same('required', $openai['tool_choice']);

	$mistral = ws_model_payload('mistral', 'mistral-small', $body);
	pg_assert_same(3000, $mistral['max_tokens']);
	pg_assert_true(!isset($mistral['max_completion_tokens']) && !isset($mistral['reasoning_effort']));
}

function test_workspace_model_agent_id_and_hold()
{
	pg_assert_same(12, ws_model_agent_id_of('model:12'));
	pg_assert_same(0, ws_model_agent_id_of('ai'));
	pg_assert_same(0, ws_model_agent_id_of('model:0'));
	pg_assert_same(0, ws_model_agent_id_of('model:12 '));
	pg_assert_true(strlen('model:' . str_repeat('9', 10)) <= 16, 'fits ws_ai_requests.agent');

	pg_assert_same(0, ws_model_hold_seconds(0));
	pg_assert_same(60, ws_model_hold_seconds(1));
	pg_assert_same(120, ws_model_hold_seconds(2));
	pg_assert_same(WS_MODEL_HOLD_MAX, ws_model_hold_seconds(40));
}

function test_workspace_model_base_problem()
{
	pg_assert_same('', ws_model_base_problem('openai', ''), 'a fixed address needs none');
	pg_assert_true(ws_model_base_problem('nope', '') !== '', 'an unknown provider');
	pg_assert_same('', ws_model_base_problem('azure', 'https://r.openai.azure.com/openai/v1'));
	pg_assert_true(ws_model_base_problem('azure', 'http://r.openai.azure.com/openai/v1') !== '', 'Azure only over https');
	pg_assert_true(ws_model_base_problem('azure', '') !== '', 'Azure needs its address');
	pg_assert_same('', ws_model_base_problem('custom', 'http://localhost:11434/v1'), 'a local Ollama server');
	pg_assert_true(ws_model_base_problem('custom', 'ftp://x.example') !== '', 'http or https only');
	pg_assert_true(ws_model_base_problem('custom', 'https://' . str_repeat('a', 500) . '.example') !== '', 'fits door_url');
	pg_assert_true(ws_model_provider_name('custom') !== 'custom', 'a custom address is named in words');
	pg_assert_same('OpenAI', ws_model_provider_name('openai'));
}

function test_workspace_model_config_over()
{
	$config = array('enabled' => false, 'app_id' => 3, 'model' => 'gpt-oss', 'error' => 'down', 'hold_until' => 99, 'license_set' => true);
	$over = ws_model_config_over($config, array('app_id' => 8, 'model' => 'gemini-2.5-flash'));
	pg_assert_same(array('enabled' => true, 'app_id' => 8, 'model' => 'gemini-2.5-flash', 'error' => '', 'hold_until' => 0, 'license_set' => true), $over);

	pg_assert_same(null, ws_model_running());
	ws_model_running(array('id' => 1));
	pg_assert_same(array('id' => 1), ws_model_running());
	ws_model_running(null);
	pg_assert_same(null, ws_model_running(), 'let go after the run');
}

// A function's source without its comment lines.
function workspace_model_agents_test_source($function)
{
	$reflection = new ReflectionFunction($function);
	$lines = file($reflection->getFileName());

	return preg_replace('~^\s*//.*$~m', '', implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)));
}

// A model assistant's call never reaches the licence, the gateway's hold or
// Pinegrap AI's own config: each of these turns to the running assistant
// before it does.
function test_workspace_model_ai_hooks()
{
	$checks = array(
		'ws_ai_http'             => array('ws_model_http(', 'ws_ai_license_key()'),
		'ws_ai_response_problem' => array('ws_model_response_problem(', 'ws_ai_license_verdict('),
		'ws_ai_hold'             => array('ws_model_hold(', 'UPDATE config'),
		'ws_ai_config'           => array('ws_model_config_over(', 'return $running'),
	);

	foreach ($checks as $function => $order) {
		$source = workspace_model_agents_test_source($function);
		$first = strpos($source, $order[0]);
		$second = strpos($source, $order[1]);
		pg_assert_true(($first !== false) && ($second !== false), $function . ' has both');

		if ($function !== 'ws_ai_config') {
			pg_assert_true($first < $second, $function . ' turns to the assistant first');
		}
	}

	pg_assert_true(strpos(workspace_model_agents_test_source('ws_ai_kick'), 'ws_model_kick(') < strpos(workspace_model_agents_test_source('ws_ai_kick'), "ws_ai_config()['enabled']"), 'the assistants run whether Pinegrap AI is on or not');
}

// A model assistant as ws_model_running() holds it while its request is
// answered.
function workspace_model_agents_test_running()
{
	return array('id' => 7, 'kind' => 'model', 'handle' => 'gemini', 'name' => 'Gemini Helper', 'provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'app_id' => 8);
}

// The loop's failure sentences name whoever answers: Pinegrap AI for its own
// requests, the model assistant by its name while one runs.
function test_workspace_model_loop_sentences_name_the_assistant()
{
	$which = array('unreachable', 'no_model', 'undone', 'unfinished', 'channel');

	pg_assert_same(null, ws_model_running());

	foreach ($which as $name) {
		pg_assert_contains('Pinegrap AI', ws_ai_loop_sentence($name), $name . ': Pinegrap AI\'s own request keeps its wording');
	}

	pg_assert_same(lang('Pinegrap AI could not be reached or took too long to answer.'), ws_ai_loop_sentence('unreachable'));
	pg_assert_same(lang('Pinegrap AI lists no model to answer with.'), ws_ai_loop_sentence('no_model'));
	pg_assert_same(lang('Pinegrap AI could not do it'), ws_ai_loop_sentence('undone'));
	pg_assert_same(lang('Pinegrap AI could not finish the answer.'), ws_ai_loop_sentence('unfinished'));
	pg_assert_same(lang('Pinegrap AI may not be asked in this channel.'), ws_ai_loop_sentence('channel'));
	pg_assert_same('', ws_ai_loop_sentence('nothing'), 'an unknown sentence is none');

	ws_model_running(workspace_model_agents_test_running());

	try {
		foreach ($which as $name) {
			$said = ws_ai_loop_sentence($name);
			pg_assert_contains('Gemini Helper', $said, $name . ': the assistant by its name');
			pg_assert_false(strpos($said, 'Pinegrap AI') !== false, $name . ': never as Pinegrap AI');
			pg_assert_same(ws_model_loop_sentence(ws_model_running(), $name), $said, $name . ': the assistant\'s own sentence');
		}

		pg_assert_same(lang(array('string' => '{var:1} could not be reached or took too long to answer.', 'vars' => 'Gemini Helper')), ws_ai_loop_sentence('unreachable'));
		pg_assert_same(lang(array('string' => '{var:1} could not do it', 'vars' => 'Gemini Helper')), ws_ai_loop_sentence('undone'));
		pg_assert_same('', ws_ai_loop_sentence('nothing'));
	} finally {
		ws_model_running(null);
	}
}

// A call that failed at the assistant's own API is told with the assistant's
// name wherever the loop asks for the sentence (a draft made inside its
// request through pg_ai_chat(), say), never as Pinegrap AI's.
function test_workspace_model_http_error_names_the_assistant()
{
	$timeout = array('http' => 0, 'json' => null, 'body' => '', 'headers' => '', 'errno' => 28, 'error' => 'Operation timed out');
	$refused = array('http' => 500, 'json' => array('error' => array('message' => 'overloaded')), 'body' => '', 'headers' => '', 'errno' => 0, 'error' => '');

	pg_assert_same(lang('Pinegrap AI did not answer in time.'), ws_ai_http_error($timeout), 'Pinegrap AI\'s own call');

	ws_model_running(workspace_model_agents_test_running());

	try {
		pg_assert_same(lang(array('string' => '{var:1} did not answer in time.', 'vars' => 'Gemini Helper')), ws_ai_http_error($timeout), 'a time-out of the assistant\'s API');
		pg_assert_same(ws_model_http_error(ws_model_running(), $refused), ws_ai_http_error($refused), 'an HTTP error of the assistant\'s API');
		pg_assert_false(strpos(ws_ai_http_error($refused), 'Pinegrap AI') !== false, 'never as Pinegrap AI');
	} finally {
		ws_model_running(null);
	}
}

// Where a model assistant may be asked: its own row in the channel decides,
// as it decides an application agent's answer. Pinegrap AI's switch
// (ai_access) is Pinegrap AI's alone, as Claude's is Claude's: neither a
// private channel nobody opened to Pinegrap AI nor a channel closed to it
// keeps out an assistant the channel's people added.
function test_workspace_model_channel_verdict()
{
	$row = array('channel_id' => 30, 'agent_id' => 7, 'allowed' => 1);
	$private = array('id' => 30, 'kind' => 'private', 'ai_access' => 0);

	pg_assert_true(ws_model_channel_verdict($private, false, $row), 'a private channel nobody opened to Pinegrap AI');
	pg_assert_true(ws_model_channel_verdict(array('ai_access' => 1) + $private, false, $row), 'a private channel opened to Pinegrap AI');
	pg_assert_true(ws_model_channel_verdict(array('kind' => 'public') + $private, false, $row), 'a public channel');
	pg_assert_true(ws_model_channel_verdict(array('ai_access' => 2) + $private, false, $row), 'a private channel closed to Pinegrap AI');
	pg_assert_true(ws_model_channel_verdict(array('kind' => 'public', 'ai_access' => 2) + $private, false, $row), 'a public channel closed to Pinegrap AI');

	pg_assert_false(ws_model_channel_verdict($private, false, null), 'the assistant is not in the channel');
	pg_assert_false(ws_model_channel_verdict(array('kind' => 'public') + $private, false, null), 'nor in a public one it was not added to');
	pg_assert_false(ws_model_channel_verdict($private, false, array('allowed' => 0) + $row), 'its row no longer allows it');
	pg_assert_false(ws_model_channel_verdict(array('ai_access' => 1) + $private, false, null), 'a channel opened to Pinegrap AI is not opened to an assistant nobody added');

	pg_assert_false(ws_model_channel_verdict(array('kind' => 'guest') + $private, false, $row), 'never in a guest\'s room');
	pg_assert_false(ws_model_channel_verdict($private, true, $row), 'never where a guest reads along');
	pg_assert_false(ws_model_channel_verdict(null, false, $row), 'no channel');
}

// Inside the loop the channel is asked about whoever answers, at every place
// that decides whether a request may be answered in it; Pinegrap AI's own
// switch stays the rule for Pinegrap AI.
function test_workspace_model_loop_asks_about_the_one_answering()
{
	foreach (array('ws_ai_request_channel', 'ws_ai_finish_fail', 'ws_ai_deliver') as $function) {
		$source = workspace_model_agents_test_source($function);
		pg_assert_contains('ws_ai_loop_channel_allowed($channel)', $source, $function . ' asks about the one answering');
		pg_assert_false(strpos($source, 'ws_ai_channel_allowed(') !== false, $function . ': not by Pinegrap AI\'s switch alone');
	}

	pg_assert_contains("\$claude ? 'ws_claude_channel_allowed' : 'ws_ai_loop_channel_allowed'", workspace_model_agents_test_source('ws_ai_briefing'), 'the briefing: Claude by its own check, otherwise the one answering');

	$loop = workspace_model_agents_test_source('ws_ai_loop_channel_allowed');
	$model = strpos($loop, 'ws_model_channel_allowed($running, $channel)');
	$own = strpos($loop, 'ws_ai_channel_allowed($channel)');
	pg_assert_true(($model !== false) && ($own !== false), 'the assistant\'s rule and Pinegrap AI\'s');
	pg_assert_true($model < $own, 'the running assistant first');

	$allowed = workspace_model_agents_test_source('ws_model_channel_allowed');
	pg_assert_contains("ws_channel_agent_row((int) \$channel['id'], (int) \$agent['id'])", $allowed, 'its row in the channel the request was made in');
	pg_assert_contains('ws_thread_parent_channel($channel)', $allowed, 'a discussion follows its channel for what closes it');
	pg_assert_contains('ws_channel_share_open(', $allowed, 'a channel shared with a guest');
	pg_assert_contains('ws_model_channel_verdict(', $allowed);

	pg_assert_false(ws_ai_loop_channel_allowed(array('id' => 31, 'kind' => 'guest')), 'no assistant running: Pinegrap AI\'s rule, never in a guest\'s room');
	pg_assert_same(null, ws_model_running());
}

// The loop's own places say the sentences through ws_ai_loop_sentence(), so
// none of them names Pinegrap AI for a model assistant's request.
function test_workspace_model_loop_sentences_are_used()
{
	$places = array(
		'ws_ai_step'     => array('unreachable', 'no_model', 'undone'),
		'ws_ai_final'    => array('unfinished'),
		'ws_ai_deliver'  => array('channel'),
		'ws_ai_briefing' => array('channel'),
	);

	foreach ($places as $function => $sentences) {
		$source = workspace_model_agents_test_source($function);

		foreach ($sentences as $name) {
			pg_assert_contains("ws_ai_loop_sentence('" . $name . "')", $source, $function . ' says ' . $name . ' for the one answering');
		}

		pg_assert_same(0, preg_match("/lang\('Pinegrap AI (could not be reached or took|lists no model|could not do it|could not finish|may not be asked in this channel)/", $source), $function . ' holds none of them for Pinegrap AI alone');
	}

	$error = workspace_model_agents_test_source('ws_ai_http_error');
	pg_assert_true(strpos($error, 'ws_model_http_error($running, $response)') < strpos($error, "lang(array('string' => 'Pinegrap AI answered HTTP"), 'a failed call turns to the assistant first');
}
