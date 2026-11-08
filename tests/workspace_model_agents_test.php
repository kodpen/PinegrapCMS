<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the model assistants (includes/workspace/model_agents.php) that
 * need no database: the providers, the address and headers of a call, the
 * body made fit for each API, the request's agent, the hold, the address
 * check, the config a running assistant sees, and that ai.php reaches the
 * gateway's licence and hold only for Pinegrap AI's own requests.
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
