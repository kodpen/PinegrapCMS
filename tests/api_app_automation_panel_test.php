<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Application Access's parts for automation platforms: the
 * "Automation platform" starting point ticks the contract's scopes and no
 * scope that holds a route moving money; the Events tab's "New subscription"
 * form refuses what POST /webhooks refuses, through the same check; "Send a
 * sample" refuses what POST /webhooks/{id}/test refuses; a locked row shows
 * neither the form nor the sample button.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/app_access.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/automation_contract.php');

// The scopes the starting point gives: each ticked row's scope, and the read
// scope a write choice holds.
function api_app_automation_panel_test_ticked()
{
	$groups = api_scope_groups();
	$scopes = array();

	foreach (api_app_start_choices(pg_automation_contract_scopes(), $groups) as $key => $choice) {
		$scopes[] = $groups[$key][$choice];

		if ($choice === 'write' && $groups[$key]['read'] !== '') {
			$scopes[] = $groups[$key]['read'];
		}
	}

	return $scopes;
}

// An address check that passes, or one that refuses as api_http_check_url()
// does, without a lookup.
function api_app_automation_panel_test_url_ok($url)
{
	return array('ok' => true, 'ip' => '203.0.113.5', 'error' => '');
}

function api_app_automation_panel_test_url_inside($url)
{
	return array('ok' => false, 'ip' => '10.0.0.1', 'error' => 'That address is inside this network and is not called.');
}

function api_app_automation_panel_test_check($url, $events, $held, $checker = 'api_app_automation_panel_test_url_ok')
{
	return api_webhook_create_check($url, $events, $held, $checker);
}

// The starting point ticks every contract scope a row of the screen offers,
// webhooks:manage and meta:read among them, and nothing else.
function test_api_app_start_ticks_the_contract_scopes()
{
	$contract = pg_automation_contract_scopes();
	$choices = api_app_start_choices($contract, api_scope_groups());

	pg_assert_same('write', isset($choices['webhooks']) ? $choices['webhooks'] : '', 'Webhooks: Manage');
	pg_assert_same('read', isset($choices['meta']) ? $choices['meta'] : '', 'Site info: Read');
	pg_assert_same('write', isset($choices['products']) ? $choices['products'] : '', 'products:write holds products:read');
	pg_assert_same('read', isset($choices['orders']) ? $choices['orders'] : '', 'orders are only read');

	$ticked = api_app_automation_panel_test_ticked();

	foreach ($ticked as $scope) {
		pg_assert_true(in_array($scope, $contract, true), $scope . ' is a contract scope');
	}

	$offered = array();

	foreach (api_scope_groups() as $group) {
		$offered[] = $group['read'];
		$offered[] = $group['write'];
	}

	foreach ($contract as $scope) {
		if (in_array($scope, $offered, true)) {
			pg_assert_true(in_array($scope, $ticked, true), $scope . ' is ticked');
		}
	}
}

// No ticked scope, nor the read a write holds, has a route that moves money.
function test_api_app_start_ticks_no_money_scope()
{
	$ticked = api_app_automation_panel_test_ticked();
	$money = 0;
	$inside = array();

	foreach (array_merge(api_schema(), erp_api_routes(), ws_api_routes()) as $route) {
		if (empty($route['moves_money'])) {
			continue;
		}

		$money++;

		if (in_array((string) $route['scope'], $ticked, true)) {
			$inside[] = $route['id'] . ' (' . $route['scope'] . ')';
		}
	}

	pg_assert_true($money > 0, 'the money routes were read');
	pg_assert_same(array(), $inside, 'money routes under a ticked scope');
}

// The mapping itself: write over read, a scope no row offers left out.
function test_api_app_start_choices_mapping()
{
	$groups = array(
		'a' => array('read' => 'a:read', 'write' => 'a:write'),
		'b' => array('read' => 'b:read', 'write' => ''),
		'c' => array('read' => '', 'write' => 'c:manage'),
		'd' => array('read' => 'd:read', 'write' => 'd:write'),
	);

	pg_assert_same(
		array('a' => 'write', 'b' => 'read', 'c' => 'write'),
		api_app_start_choices(array('a:read', 'a:write', 'b:read', 'c:manage', 'x:read'), $groups)
	);
	pg_assert_same(array(), api_app_start_choices(array(), $groups));
}

// The form's refusals are the route's: the same status, code, field and text.
function test_api_webhook_create_check_refusals()
{
	$long = 'https://hooks.example.com/' . str_repeat('a', 500);
	$many = array_slice(array_keys(api_webhook_events()), 0, 21);

	$cases = array(
		'no address'    => array(api_app_automation_panel_test_check('  ', array('order.created'), 0), 422, 'validation_failed', 'url', 'url is required.'),
		'too long'      => array(api_app_automation_panel_test_check($long, array('order.created'), 0), 422, 'validation_failed', 'url', 'url is longer than the 500 characters accepted.'),
		'too many'      => array(api_app_automation_panel_test_check('https://hooks.example.com/a', $many, 0), 422, 'validation_failed', 'events', 'events holds more than the 20 items accepted in one request.'),
		'inside'        => array(api_app_automation_panel_test_check('https://10.0.0.1/a', array('order.created'), 0, 'api_app_automation_panel_test_url_inside'), 422, 'validation_failed', 'url', 'That address is inside this network and is not called.'),
		'unknown event' => array(api_app_automation_panel_test_check('https://hooks.example.com/a', array('order.created', 'no.such'), 0), 422, 'validation_failed', 'events', 'Unknown event: no.such'),
		'no event'      => array(api_app_automation_panel_test_check('https://hooks.example.com/a', array(), 0), 422, 'validation_failed', 'events', 'At least one event is required.'),
		'limit'         => array(api_app_automation_panel_test_check('https://hooks.example.com/a', array('order.created'), 50), 409, 'too_many_webhooks', null, 'An application may hold 50 subscriptions.'),
	);

	foreach ($cases as $name => $case) {
		list($checked, $status, $code, $field, $message) = $case;

		pg_assert_true($checked['refusal'] !== null, $name . ': refused');
		pg_assert_same($status, $checked['refusal']['status'], $name . ': status');
		pg_assert_same($code, $checked['refusal']['code'], $name . ': code');
		pg_assert_same($field, $checked['refusal']['field'], $name . ': field');
		pg_assert_same($message, $checked['refusal']['message'], $name . ': message');
	}
}

// Under the limit, with known events, the check passes and cleans the list.
function test_api_webhook_create_check_passes()
{
	$checked = api_app_automation_panel_test_check(' https://hooks.example.com/catch/1 ', array('order.created', 'order.created', 'order.shipped'), 49);

	pg_assert_same(null, $checked['refusal']);
	pg_assert_same('https://hooks.example.com/catch/1', $checked['url']);
	pg_assert_same(array('order.created', 'order.shipped'), $checked['events']);
}

// The bounds the check holds the form to are the route row's.
function test_api_webhook_create_bounds_are_the_routes()
{
	$bounds = api_webhook_create_bounds();
	$found = 0;

	foreach (api_schema() as $route) {
		if ($route['id'] !== 'webhooks.create') {
			continue;
		}

		foreach ($route['params'] as $param) {
			if ($param['name'] === 'url') {
				pg_assert_same($bounds['url'], $param['max_length'], 'url length');
				$found++;
			}

			if ($param['name'] === 'events') {
				pg_assert_same($bounds['events'], $param['max_items'], 'events count');
				$found++;
			}
		}
	}

	pg_assert_same(2, $found, 'both bounds found');
}

// Both callers go through one check and one write; neither keeps its own.
function test_api_webhook_create_one_check_for_both_callers()
{
	$route = (new ReflectionFunction('api_webhooks_create'));
	$lines = file($route->getFileName());
	$route_source = implode('', array_slice($lines, $route->getStartLine() - 1, $route->getEndLine() - $route->getStartLine() + 1));

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/api_settings.php');

	foreach (array('route' => $route_source, 'panel' => $panel) as $name => $source) {
		pg_assert_contains('api_webhook_create_check(', $source, $name . ' checks through the one function');
		pg_assert_contains('api_webhook_store(', $source, $name . ' writes through the one function');
		pg_assert_contains('api_webhook_held_count(', $source, $name . ' counts through the one function');
		pg_assert_false(strpos($source, 'INSERT INTO api_webhooks') !== false, $name . ' writes no row itself');
		pg_assert_false(strpos($source, 'api_http_check_url(') !== false, $name . ' tries no address itself');
	}

	pg_assert_contains('api_webhook_test_refusal(', $panel, 'the sample is refused as the route refuses it');
	pg_assert_contains('api_webhook_test_queue(', $panel, 'the sample is queued as the route queues it');
	pg_assert_false(strpos($panel, 'INSERT INTO api_webhook_queue') !== false, 'the panel queues nothing itself');
}

// A sample: a stopped subscription and an event it does not hear are refused.
function test_api_webhook_test_refusal()
{
	$payloads = array('order.created' => array('fields' => array(), 'sample' => array('id' => 1)));
	$row = array('status' => 'active', 'events' => json_encode(array('order.created', 'order.shipped')));

	pg_assert_same(null, api_webhook_test_refusal($row, 'order.created', $payloads));
	pg_assert_same(null, api_webhook_test_refusal($row, '', $payloads), 'no event is webhook.test');

	$stopped = api_webhook_test_refusal(array('status' => 'disabled') + $row, 'order.created', $payloads);
	pg_assert_same(409, $stopped['status']);
	pg_assert_same('webhook_disabled', $stopped['code']);

	$deaf = api_webhook_test_refusal($row, 'customer.created', $payloads);
	pg_assert_same(422, $deaf['status']);
	pg_assert_same('event', $deaf['field']);

	$no_sample = api_webhook_test_refusal($row, 'order.shipped', $payloads);
	pg_assert_same('event', $no_sample['field']);
}

// A locked row: the actions are refused before any branch, and the drawer
// hides the form, its button and the sample box.
function test_api_app_locked_row_shows_no_form_and_no_sample()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/api_settings.php');

	$lock = strpos($panel, "if (\$app_id > 0 && \$action !== 'create') {");
	pg_assert_true($lock !== false, 'the lock check is there');
	pg_assert_true($lock < strpos($panel, "\$action === 'webhook_create'"), 'before the form is handled');
	pg_assert_true($lock < strpos($panel, "\$action === 'webhook_sample'"), 'before a sample is handled');

	foreach (array('.api-hook-acts', '.api-hook-new', '.api-hook-form', '.api-hook-sample') as $part) {
		pg_assert_contains('#api_drawer.api-locked ' . $part, $panel, $part . ' hidden on a locked row');
	}

	// The sample button is drawn inside the actions the lock hides.
	$acts = strpos($panel, "\"<div class=\\'api-hook-acts\\'>\" +");
	$sample = strpos($panel, 'api-sample-open');
	pg_assert_true($acts !== false && $sample > $acts, 'Send a sample sits in the actions');
	pg_assert_contains('if (current && current.locked) { return; }', $panel, 'the form does not open on a locked row');
}

// No text of the screen names an automation platform before its app is out.
function test_api_app_automation_texts_name_no_platform()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/api_settings.php');

	foreach (array('Zapier', 'n8n', 'Pabbly', 'Make.com') as $name) {
		pg_assert_false(stripos($panel, $name) !== false, $name . ' is not named');
	}

	pg_assert_contains("lang('An automation platform connects to the site with this application. The permissions the platform uses are ticked.')", $panel);
}
