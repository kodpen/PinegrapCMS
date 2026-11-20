<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application agent's report of its model and plan usage
 * (includes/workspace/agent_report.php, POST /workspace/agent-report): the
 * check that refuses a report whole, the stored windows read back, the
 * card's words and their age, and the route's rules.
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
require_once(__DIR__ . '/helpers/language_map.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function test_workspace_agent_report_check_takes_a_report()
{
	$check = ws_agent_report_check(' model-x ', array(
		array('name' => 'weekly', 'percent' => 61, 'resets_at' => 1800000000),
		array('name' => 'five_hour', 'percent' => 0, 'resets_at' => 1790000000),
	));
	pg_assert_true($check['ok'], $check['error']);
	pg_assert_same('model-x', $check['values']['model']);
	pg_assert_same(array('five_hour', 'weekly'), array_column($check['values']['windows'], 'name'), 'kept in the list\'s order');
	pg_assert_same($check['values']['windows'], ws_agent_report_windows($check['values']['usage']), 'the stored windows read back');
	pg_assert_true(strlen($check['values']['usage']) <= 1000, 'four windows fit the column');

	$model_only = ws_agent_report_check('model-x', null);
	pg_assert_true($model_only['ok'], 'a model alone');
	pg_assert_same('', $model_only['values']['usage']);

	$windows_only = ws_agent_report_check(null, array(array('name' => 'daily', 'percent' => 100, 'resets_at' => 1800000000)));
	pg_assert_true($windows_only['ok'], 'windows alone');

	$four = array();

	foreach (array('five_hour', 'daily', 'weekly', 'monthly') as $name) {
		$four[] = array('name' => $name, 'percent' => 50, 'resets_at' => 1800000000);
	}

	pg_assert_true(ws_agent_report_check('m', $four)['ok'], 'four windows');
}

function test_workspace_agent_report_check_refuses_whole()
{
	$window = array('name' => 'weekly', 'percent' => 10, 'resets_at' => 1800000000);
	$refused = function ($model, $windows, $field, $label) {
		$check = ws_agent_report_check($model, $windows);
		pg_assert_false($check['ok'], $label);
		pg_assert_same($field, $check['field'], $label);
		pg_assert_same(null, $check['values'], $label . ': nothing to write');
	};

	$refused('', null, 'model', 'an empty report');
	$refused(str_repeat('m', 81), null, 'model', 'a model over 80 characters');
	$refused("model\nx", null, 'model', 'a model over two lines');
	$refused(array('m'), null, 'model', 'a model that is no text');
	$refused("\xC3\x28", null, 'model', 'a model that is not UTF-8');
	$refused('m', 'weekly', 'windows', 'windows that are no list');
	$refused('m', array('a' => $window), 'windows', 'windows keyed by name');

	$five = array_fill(0, 5, $window);
	$refused('m', $five, 'windows', 'five windows');
	$refused('m', array($window, $window), 'windows', 'a window named twice');
	$refused('m', array(array('name' => 'yearly') + $window), 'windows', 'a name not on the list');
	$refused('m', array(array('percent' => 101) + $window), 'windows', 'a percent over 100');
	$refused('m', array(array('percent' => -1) + $window), 'windows', 'a percent under 0');
	$refused('m', array(array('percent' => 10.5) + $window), 'windows', 'a percent that is not whole');
	$refused('m', array(array('percent' => '10') + $window), 'windows', 'a percent given as text');
	$refused('m', array(array('resets_at' => 0) + $window), 'windows', 'no reset time');
	$refused('m', array(array('resets_at' => 'soon') + $window), 'windows', 'a reset time that did not read');
	$refused('m', array(array('extra' => 1) + $window), 'windows', 'a field more');
	$refused('m', array(array('name' => 'weekly', 'percent' => 10)), 'windows', 'a field less');
	$refused('m', array($window, 'weekly'), 'windows', 'one bad window refuses all');

	pg_assert_same(array(), ws_agent_report_windows('not json'), 'a stored value that does not read gives no windows');
	pg_assert_same(array(), ws_agent_report_windows(''));
}

function test_workspace_agent_report_words_on_the_card()
{
	$now = 1800000000;
	$agent = ws_agent_shape(array('id' => 2, 'kind' => 'app', 'report_model' => 'model-<b>', 'report_at' => $now - 720,
		'report_usage' => json_encode(array(array('name' => 'weekly', 'percent' => 61, 'resets_at' => $now + 3600)))));
	$words = ws_agent_report_words($agent, $now);

	pg_assert_contains('model-<b>', $words['text'], 'the model as reported; the card escapes it');
	pg_assert_contains('61%', $words['text']);
	pg_assert_contains(lang(array('string' => 'reported {var:1}', 'vars' => ws_seen_ago_words($now - 720, $now))), $words['text'], 'with its age');
	pg_assert_false($words['stale'], 'twelve minutes old');

	$agent['report_at'] = $now - 86400;
	pg_assert_true(ws_agent_report_words($agent, $now)['stale'], 'a day old is muted');

	$agent['report_at'] = 0;
	pg_assert_same('', ws_agent_report_words($agent, $now)['text'], 'no report yet');

	$model = ws_agent_shape(array('id' => 3, 'kind' => 'model', 'model' => 'gpt-x', 'report_model' => 'other', 'report_at' => $now));
	pg_assert_same('', ws_agent_report_words($model, $now)['text'], 'a model assistant\'s model is the one on its form');

	$line = ws_agents_settings_line_html(array('report_at' => time() - 60) + $agent + array('door_kind' => 'poll', 'status' => 'active', 'handle' => 'a', 'name' => 'A'));
	pg_assert_contains('model-&lt;b&gt;', $line, 'escaped on the card');
}

function test_workspace_agent_report_labels_and_route()
{
	$turkish = pg_test_language_map('tr');

	foreach (ws_agent_report_window_list() as $key => $english) {
		pg_assert_true(isset($turkish[$english]), $key . ' has its Turkish');
	}

	pg_assert_same(array_keys(ws_agent_report_window_list()), array_keys(ws_agent_report_window_labels()));

	$route = null;

	foreach (ws_agent_request_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.agent_report.set') {
			$route = $candidate;
		}
	}

	pg_assert_true($route !== null, 'the route is there');
	pg_assert_same(array('POST', '/workspace/agent-report', 'workspace:write', 'agent', true), array($route['method'], $route['path'], $route['scope'], $route['mcp'], $route['dry_run']));

	$times = ws_api_agent_report_times(array(array('name' => 'weekly', 'percent' => 1, 'resets_at' => '2027-01-02T03:04:05Z'), array('name' => 'daily', 'percent' => 1, 'resets_at' => 'soon')));
	pg_assert_same(gmmktime(3, 4, 5, 1, 2, 2027), $times[0]['resets_at'], 'an ISO time read as unix seconds');
	pg_assert_same('soon', $times[1]['resets_at'], 'a time that does not read is left for the check to refuse');

	$present = ws_api_agent_report_present('a', array('model' => 'm', 'windows' => array(array('name' => 'weekly', 'percent' => 5, 'resets_at' => 1800000000))), 0);
	pg_assert_same(array_keys(ws_api_agent_report_schema()), array_keys($present), 'the answer matches its schema');
	pg_assert_same(null, $present['reported_at'], 'a dry run writes no time');
}

// tools/ws_profile.php shapes what a session gives into the route's body;
// the site's own check takes it as it is.
function test_workspace_agent_report_profile_tool()
{
	require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/ws_profile.php');

	$built = ws_profile_body(array('model' => ' model-x ', 'windows' => 'five_hour:42:2026-11-13T22:00:00Z,weekly:61:1795000000'));
	pg_assert_same('', $built['error']);
	pg_assert_same(array('model' => 'model-x', 'windows' => array(
		array('name' => 'five_hour', 'percent' => 42, 'resets_at' => '2026-11-13T22:00:00Z'),
		array('name' => 'weekly', 'percent' => 61, 'resets_at' => 1795000000),
	)), $built['body']);

	$check = ws_agent_report_check($built['body']['model'], ws_api_agent_report_times($built['body']['windows']));
	pg_assert_true($check['ok'], 'the site takes what the tool sends: ' . $check['error']);

	pg_assert_same(array('model' => 'm'), ws_profile_body(array('model' => 'm'))['body'], 'a model alone');
	pg_assert_same(null, ws_profile_body(array())['body'], 'nothing to report');
	pg_assert_same(null, ws_profile_body(array('model' => str_repeat('m', 81)))['body'], 'a model over 80 characters');
	pg_assert_same(null, ws_profile_body(array('windows' => 'weekly:61'))['body'], 'a window without its reset');
	pg_assert_same(null, ws_profile_body(array('windows' => 'weekly:101:1795000000'))['body'], 'a percent over 100');
	pg_assert_same(null, ws_profile_body(array('windows' => 'weekly:6.5:1795000000'))['body'], 'a percent that is not whole');
	pg_assert_same(null, ws_profile_body(array('windows' => implode(',', array_fill(0, 5, 'weekly:1:1795000000'))))['body'], 'five windows');
}

// In an agent channel the brief asks for the model when the last report is
// missing or older than an hour; never when it is fresh.
function test_workspace_agent_report_brief_line()
{
	$now = 1800000000;
	$agent = array('id' => 2, 'kind' => 'app', 'report_at' => 0);

	pg_assert_contains('"model": "<the model you run on>"', ws_agent_report_brief_line($agent, true, $now), 'no report yet');
	pg_assert_contains('POST /workspace/agent-report', ws_agent_report_brief_line($agent, true, $now));
	pg_assert_contains('"model"', ws_agent_report_brief_line(array('report_at' => $now - WS_AGENT_REPORT_ASK_AFTER) + $agent, true, $now), 'an hour old');
	pg_assert_same('', ws_agent_report_brief_line(array('report_at' => $now - 600) + $agent, true, $now), 'fresh: no line');
	pg_assert_same('', ws_agent_report_brief_line($agent, false, $now), 'outside an agent channel');
	pg_assert_same('', ws_agent_report_brief_line(array('kind' => 'model') + $agent, true, $now), 'a model assistant');

	pg_assert_same(3600, WS_AGENT_REPORT_ASK_AFTER);
	pg_assert_contains('ws_agent_report_brief_line(', pg_test_function_source('ws_agent_brief'), 'the brief carries it');
	pg_assert_contains('ws_agent_channel_is(', pg_test_function_source('ws_agent_brief'));
}

// The answer route takes an optional model: the report's rule, and it
// updates the model and its time, keeping the usage windows.
function test_workspace_agent_report_with_the_answer()
{
	$route = null;

	foreach (ws_agent_request_api_routes() as $candidate) {
		if ($candidate['id'] === 'workspace.agent_requests.answer') {
			$route = $candidate;
		}
	}

	pg_assert_true($route !== null, 'the answer route is there');
	$model = null;

	foreach ($route['params'] as $param) {
		if ($param['name'] === 'model') {
			$model = $param;
		}
	}

	pg_assert_true($model !== null, 'the answer takes a model');
	pg_assert_same(array('body', 'string', 80), array($model['in'], $model['type'], $model['max_length']));
	pg_assert_true(empty($model['required']), 'optional');

	pg_assert_same(array('ok' => true, 'error' => '', 'model' => 'model-x'), ws_agent_report_model_check(' model-x '));
	pg_assert_false(ws_agent_report_model_check(str_repeat('m', 81))['ok'], 'over 80 characters');
	pg_assert_false(ws_agent_report_model_check("a\nb")['ok'], 'two lines');
	pg_assert_false(ws_agent_report_model_check('')['ok'], 'empty');
	pg_assert_false(ws_agent_report_model_check(5)['ok'], 'not a string');

	$save = pg_test_function_source('ws_agent_report_model_save');
	pg_assert_contains('report_model', $save);
	pg_assert_contains('report_at', $save);
	pg_assert_false(strpos($save, 'report_usage') !== false, 'the usage windows are kept');

	$answer = pg_test_function_source('ws_api_agent_request_answer');
	pg_assert_contains('ws_api_agent_answer_model($params)', $answer);
	pg_assert_true(strpos($answer, 'ws_api_agent_answer_model($params)') < strpos($answer, 'api_dry_run_stop'), 'checked before the dry run stops');
	pg_assert_contains('ws_agent_report_model_save(', $answer);
	pg_assert_contains('ws_agent_report_model_save(', pg_test_function_source('ws_api_agent_task_answer'), 'a task run\'s answer too');

	pg_assert_contains('"model": "<the model you run on>"', implode("\n", ws_agent_instructions_request_lines()), 'the instructions say it');
}

// The report's context, project and branch (E10, E11): each refusal, the
// stored list of an older report still read, the object's round trip and
// its size.
function test_workspace_agent_report_fields_refused()
{
	$refused = function ($fields, $field, $label) {
		$check = ws_agent_report_check(null, null, $fields);
		pg_assert_false($check['ok'], $label);
		pg_assert_same($field, $check['field'], $label);
		pg_assert_same(null, $check['values'], $label . ': nothing to write');
	};

	$refused(array('context' => 'big'), 'context', 'a context that is no object');
	$refused(array('context' => array(10, 20)), 'context', 'a context that is a list');
	$refused(array('context' => array('used' => 10)), 'context', 'a context without its size');
	$refused(array('context' => array('used' => 10, 'size' => 20, 'left' => 10)), 'context', 'a context with a field more');
	$refused(array('context' => array('used' => '10', 'size' => 20)), 'context', 'a used that is text');
	$refused(array('context' => array('used' => 10.5, 'size' => 20)), 'context', 'a used that is not whole');
	$refused(array('context' => array('used' => -1, 'size' => 20)), 'context', 'a negative used');
	$refused(array('context' => array('used' => 21, 'size' => 20)), 'context', 'used over size');
	$refused(array('context' => array('used' => 0, 'size' => 0)), 'context', 'a size of nothing');
	$refused(array('context' => array('used' => 1, 'size' => 10000001)), 'context', 'a size over ten million');
	$refused(array('project' => str_repeat('p', 81)), 'project', 'a project over 80 characters');
	$refused(array('project' => "a\tb"), 'project', 'a project with a control character');
	$refused(array('project' => 5), 'project', 'a project that is no text');
	$refused(array('project' => "\xC3\x28"), 'project', 'a project that is not UTF-8');
	$refused(array('branch' => str_repeat('b', 121)), 'branch', 'a branch over 120 characters');
	$refused(array('branch' => 'a b'), 'branch', 'a branch with a space');
	$refused(array('branch' => 'a..b'), 'branch', 'a branch with two dots');
	$refused(array('branch' => '/a'), 'branch', 'a branch that starts with a slash');
	$refused(array('branch' => 'a/'), 'branch', 'a branch that ends with a slash');
	$refused(array('branch' => '-a'), 'branch', 'a branch that starts with a dash');
	$refused(array('branch' => 'a~1'), 'branch', 'a branch with a character git refuses');

	// One field that does not read refuses the whole report.
	$whole = ws_agent_report_check('model-x', array(array('name' => 'weekly', 'percent' => 10, 'resets_at' => 1800000000)), array('project' => 'ok', 'branch' => 'a b'));
	pg_assert_false($whole['ok'], 'a good model and windows beside a bad branch');

	pg_assert_true(ws_agent_report_check(null, null, array('context' => array('used' => 0, 'size' => 1)))['ok'], 'a context alone is a report');
	pg_assert_true(ws_agent_report_check(null, null, array('project' => 'Pinegrap'))['ok'], 'a project alone is a report');
	pg_assert_true(ws_agent_report_check(null, null, array('branch' => 'claude/ka3-report_fields.2'))['ok'], 'a branch alone is a report');
	pg_assert_false(ws_agent_report_check(null, null, array('project' => '  ', 'branch' => ''))['ok'], 'empty fields are no report');
}

function test_workspace_agent_report_fields_round_trip()
{
	$windows = array(array('name' => 'weekly', 'percent' => 61, 'resets_at' => 1800000000));
	$check = ws_agent_report_check('model-x', $windows, array(
		'context' => array('size' => 1000000, 'used' => 412345),
		'project' => ' Pinegrap CMS ',
		'branch'  => 'feature/x',
	));
	pg_assert_true($check['ok'], $check['error']);
	pg_assert_same(array('used' => 412345, 'size' => 1000000), $check['values']['context']);
	pg_assert_same('Pinegrap CMS', $check['values']['project']);
	pg_assert_same('feature/x', $check['values']['branch']);

	$stored = json_decode($check['values']['usage'], true);
	pg_assert_same(array('windows', 'context', 'project', 'branch'), array_keys($stored), 'stored as one object');

	$read = ws_agent_report_read($check['values']['usage']);
	pg_assert_same($check['values']['windows'], $read['windows']);
	pg_assert_same($check['values']['context'], $read['context']);
	pg_assert_same('Pinegrap CMS', $read['project']);
	pg_assert_same('feature/x', $read['branch']);
	pg_assert_same($check['values']['windows'], ws_agent_report_windows($check['values']['usage']), 'the windows of the object');

	$agent = array('kind' => 'app', 'report_at' => 1790000000, 'report_usage' => $check['values']['usage']);
	$context = ws_agent_report_context($agent);
	pg_assert_same(412345, $context['used']);
	pg_assert_same(1000000, $context['size']);
	pg_assert_same(41, $context['percent']);
	pg_assert_contains('412k', $context['words']);
	pg_assert_contains('1M', $context['words']);
	pg_assert_same('Pinegrap CMS', ws_agent_report_project($agent));
	pg_assert_same('feature/x', ws_agent_report_branch($agent));

	// An older report stored the windows as a list: they still read, and
	// it carries no context, project or branch.
	$list = json_encode($windows);
	$old = ws_agent_report_read($list);
	pg_assert_same($windows, $old['windows'], 'the stored list still reads as the windows');
	pg_assert_same(null, $old['context']);
	pg_assert_same($windows, ws_agent_report_windows($list));
	pg_assert_same(null, ws_agent_report_context(array('report_usage' => $list)));
	pg_assert_same(null, ws_agent_report_project(array('report_usage' => $list)));
	pg_assert_same(null, ws_agent_report_branch(array('report_usage' => $list)));
	pg_assert_same(null, ws_agent_report_context(array('report_usage' => '')), 'nothing reported');

	// A stored object that does not read gives nothing.
	pg_assert_same(array(), ws_agent_report_windows('{"windows":[],"context":{"used":5,"size":1},"project":"","branch":""}'));
	pg_assert_same(null, ws_agent_report_context(array('report_usage' => '{"context":{"used":5,"size":1}}')));
	pg_assert_same(null, ws_agent_report_project(array('report_usage' => '{"project":"x","extra":1}')));

	// The answer's context replaces the stored one and keeps the rest.
	$merged = ws_agent_report_usage_with_context($check['values']['usage'], array('used' => 5, 'size' => 10));
	$again = ws_agent_report_read($merged);
	pg_assert_same(array('used' => 5, 'size' => 10), $again['context']);
	pg_assert_same($check['values']['windows'], $again['windows']);
	pg_assert_same('feature/x', $again['branch']);
	pg_assert_same(array('used' => 5, 'size' => 10), ws_agent_report_read(ws_agent_report_usage_with_context($list, array('used' => 5, 'size' => 10)))['context'], 'over an older list');
	pg_assert_same($windows, ws_agent_report_read(ws_agent_report_usage_with_context($list, array('used' => 5, 'size' => 10)))['windows'], 'the list\'s windows kept');
}

function test_workspace_agent_report_fields_fit_the_column()
{
	$four = array();

	foreach (array('five_hour', 'daily', 'weekly_model', 'monthly') as $name) {
		$four[] = array('name' => $name, 'percent' => 100, 'resets_at' => 9999999999);
	}

	// The widest characters a project may hold, and a branch at its limit.
	$check = ws_agent_report_check(str_repeat('m', 80), $four, array(
		'context' => array('used' => 10000000, 'size' => 10000000),
		'project' => str_repeat("\xF0\x9F\x98\x80", 80),
		'branch'  => str_repeat('b', 120),
	));
	pg_assert_true($check['ok'], $check['error']);
	pg_assert_true(strlen($check['values']['usage']) < 1000, 'the largest object fits: ' . strlen($check['values']['usage']) . ' bytes');

	$quotes = ws_agent_report_check(null, $four, array('context' => array('used' => 10000000, 'size' => 10000000), 'project' => str_repeat('"', 80), 'branch' => str_repeat('b', 120)));
	pg_assert_true(strlen($quotes['values']['usage']) < 1000, 'escaped characters fit too');
}

function test_workspace_agent_report_tokens()
{
	pg_assert_same('950', ws_agent_report_tokens(950));
	pg_assert_same('412k', ws_agent_report_tokens(412345));
	pg_assert_same('1M', ws_agent_report_tokens(999999));
	pg_assert_same('1M', ws_agent_report_tokens(1000000));
	pg_assert_same('1.5M', ws_agent_report_tokens(1500000));
	pg_assert_same('10M', ws_agent_report_tokens(10000000));
}

// The routes, the tool, the instructions and the card carry the fields.
function test_workspace_agent_report_fields_reach_the_routes()
{
	$params = array();

	foreach (ws_agent_request_api_routes() as $route) {
		foreach ($route['params'] as $param) {
			$params[$route['id']][$param['name']] = $param;
		}
	}

	pg_assert_same('object', $params['workspace.agent_report.set']['context']['type']);
	pg_assert_same(80, $params['workspace.agent_report.set']['project']['max_length']);
	pg_assert_same(120, $params['workspace.agent_report.set']['branch']['max_length']);
	pg_assert_same('object', $params['workspace.agent_requests.answer']['context']['type'], 'the answer takes context beside model');

	$schema = ws_api_agent_report_schema();

	foreach (array('context', 'project', 'branch') as $field) {
		pg_assert_true(array_key_exists($field, $schema), 'the report route returns ' . $field);
	}

	$answer = pg_test_function_source('ws_api_agent_request_answer');
	pg_assert_contains('ws_api_agent_answer_context($params)', $answer);
	pg_assert_true(strpos($answer, 'ws_api_agent_answer_context($params)') < strpos($answer, 'api_dry_run_stop'), 'checked before the dry run stops');
	pg_assert_contains('ws_agent_report_context_save(', $answer);
	pg_assert_contains('ws_agent_report_context_save(', pg_test_function_source('ws_api_agent_task_answer'), 'a task run\'s answer too');
	pg_assert_contains('ws_agent_report_check(', pg_test_function_source('ws_api_agent_report_set'));
	pg_assert_contains('\'project\'', pg_test_function_source('ws_api_agent_report_set'));

	$lines = implode("\n", ws_agent_instructions_request_lines());
	pg_assert_contains('"context": {"used"', $lines, 'the instructions name the context');
	pg_assert_contains('"project"', $lines);
	pg_assert_contains('"branch"', $lines);

	pg_assert_contains('ws_agent_report_context(', pg_test_function_source('ws_agent_card'), 'the card carries the context');

	require_once(dirname(PG_FUNCTIONS_DIR) . '/tools/ws_profile.php');
	$built = ws_profile_body(array('context' => '412345:1000000', 'project' => 'Pinegrap', 'branch' => 'feature/x'));
	pg_assert_same(array('context' => array('used' => 412345, 'size' => 1000000), 'project' => 'Pinegrap', 'branch' => 'feature/x'), $built['body'], $built['error']);
	pg_assert_same(null, ws_profile_body(array('context' => '412345'))['body'], 'a context without its size');
	pg_assert_same(null, ws_profile_body(array('context' => '5:1.5'))['body'], 'a size that is not whole');
	pg_assert_same(null, ws_profile_body(array('project' => str_repeat('p', 81)))['body'], 'a project over 80 characters');
	pg_assert_same(null, ws_profile_body(array('branch' => str_repeat('b', 121)))['body'], 'a branch over 120 characters');
}
