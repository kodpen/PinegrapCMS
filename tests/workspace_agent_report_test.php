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
	pg_assert_same(array('POST', '/workspace/agent-report', 'workspace:write', false, true), array($route['method'], $route['path'], $route['scope'], $route['mcp'], $route['dry_run']));

	$times = ws_api_agent_report_times(array(array('name' => 'weekly', 'percent' => 1, 'resets_at' => '2027-01-02T03:04:05Z'), array('name' => 'daily', 'percent' => 1, 'resets_at' => 'soon')));
	pg_assert_same(gmmktime(3, 4, 5, 1, 2, 2027), $times[0]['resets_at'], 'an ISO time read as unix seconds');
	pg_assert_same('soon', $times[1]['resets_at'], 'a time that does not read is left for the check to refuse');

	$present = ws_api_agent_report_present('a', array('model' => 'm', 'windows' => array(array('name' => 'weekly', 'percent' => 5, 'resets_at' => 1800000000))), 0);
	pg_assert_same(array_keys(ws_api_agent_report_schema()), array_keys($present), 'the answer matches its schema');
	pg_assert_same(null, $present['reported_at'], 'a dry run writes no time');
}
