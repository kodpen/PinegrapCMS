<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for New application's starting points (api_app_templates()): every
 * card has its fields, the automation card ticks what the old "Automation
 * platform" choice ticked, no card ticks a row that moves money, and the
 * sign-in card with and without the site's OAuth application.
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

if (!function_exists('lang')) {
	function lang($text) { return is_array($text) ? (string) $text['string'] : (string) $text; }
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/app_access.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/app_templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/automation_contract.php');

// Every route of the core and of the modules, switched on or not.
function api_app_templates_test_routes()
{
	return array_merge(api_schema(), erp_api_routes(), ws_api_routes());
}

// Scope groups as api_scope_groups() writes them, built from the routes so
// every module's rows are in it without a database.
function api_app_templates_test_groups()
{
	$groups = array();

	foreach (api_app_templates_test_routes() as $route) {
		$scope = isset($route['scope']) ? (string) $route['scope'] : '';

		if (strpos($scope, ':') === false) {
			continue;
		}

		list($key, $half) = explode(':', $scope, 2);

		if (!in_array($half, array('read', 'write'), true)) {
			$key .= '_' . $half;
		}

		if (!isset($groups[$key])) {
			$groups[$key] = array('label' => $key, 'read' => '', 'write' => '');
		}

		$groups[$key][($half === 'read') ? 'read' : 'write'] = $scope;
	}

	return $groups;
}

function api_app_templates_test_cards($oauth_app = null)
{
	return api_app_templates(
		api_app_templates_test_groups(),
		pg_automation_contract_scopes(),
		api_app_money_scopes(api_app_templates_test_routes()),
		$oauth_app
	);
}

// Each card carries the fields the sheet draws, a known kind and a known
// guide; the ids are the keys.
function test_api_app_templates_shape()
{
	$cards = api_app_templates_test_cards();

	pg_assert_same(array('signin', 'automation', 'own', 'reporting', 'devices'), array_keys($cards), 'the cards and their order');

	foreach ($cards as $id => $card) {
		pg_assert_same($id, $card['id'], $id . ': id');

		foreach (array('icon', 'title', 'text', 'acts_as', 'guide') as $field) {
			pg_assert_true(is_string($card[$field]) && $card[$field] !== '', $id . ': ' . $field);
		}

		pg_assert_same(0, strpos($card['icon'], 'bi-'), $id . ': a Bootstrap icon');
		pg_assert_true(in_array($card['kind'], array('server', 'device', 'oauth'), true), $id . ': kind');
		pg_assert_same($card['kind'] === 'server', $card['secret'], $id . ': only an integration is given a secret');
		pg_assert_true(is_array($card['choices']), $id . ': choices');
		pg_assert_same(0, $card['existing'], $id . ': nothing set up');
	}

	pg_assert_same('oauth', $cards['signin']['kind']);
	pg_assert_same('device', $cards['devices']['kind']);
	pg_assert_same(array(), $cards['own']['choices'], 'own integration: nothing ticked');
	pg_assert_same(array(), $cards['signin']['choices'], 'sign-in: nothing ticked');
	pg_assert_same(array(), $cards['devices']['choices'], 'devices: nothing ticked');
}

// The automation card ticks exactly what the old starting point ticked.
function test_api_app_templates_automation_ticks_as_today()
{
	$groups = api_scope_groups();
	$money = api_app_money_scopes(api_app_templates_test_routes());
	$cards = api_app_templates($groups, pg_automation_contract_scopes(), $money, null);

	pg_assert_same(api_app_start_choices(pg_automation_contract_scopes(), $groups), $cards['automation']['choices']);
	pg_assert_same('write', $cards['automation']['choices']['webhooks'], 'Webhooks: Manage');
}

// Read-only reporting ticks a read on every row that has one, never a write.
function test_api_app_templates_reporting_reads_only()
{
	$groups = api_app_templates_test_groups();
	$cards = api_app_templates_test_cards();
	$choices = $cards['reporting']['choices'];

	pg_assert_true(count($choices) > 5, 'reads are ticked');
	pg_assert_same(array('read'), array_values(array_unique($choices)), 'only reads');

	foreach ($groups as $key => $group) {
		if ($group['read'] === '') {
			pg_assert_false(isset($choices[$key]), $key . ': a write-only row stays unticked');
		}
	}

	pg_assert_same('read', $choices['orders'], 'orders are read');
}

// No card ticks a scope, or the read its write holds, under which a route
// moves money.
function test_api_app_templates_tick_no_money_row()
{
	$groups = api_app_templates_test_groups();
	$money = api_app_money_scopes(api_app_templates_test_routes());

	pg_assert_true(count($money) > 0, 'the money scopes were read');

	foreach (api_app_templates_test_cards() as $id => $card) {
		foreach ($card['choices'] as $key => $choice) {
			$held = array($groups[$key][$choice]);

			if ($choice === 'write' && $groups[$key]['read'] !== '') {
				$held[] = $groups[$key]['read'];
			}

			pg_assert_same(array(), array_values(array_intersect($held, $money)), $id . ': ' . $key . ' moves money');
		}
	}

	// A read that holds a money route is left out too.
	$fake = array('cash' => array('label' => 'Cash', 'read' => 'cash:read', 'write' => 'cash:write'),
		'pages' => array('label' => 'Pages', 'read' => 'pages:read', 'write' => 'pages:write'));
	$cards = api_app_templates($fake, array('cash:write', 'pages:read'), array('cash:read'), null);

	pg_assert_same(array('pages' => 'read'), $cards['reporting']['choices'], 'reporting skips the money read');
	pg_assert_same(array('pages' => 'read'), $cards['automation']['choices'], 'automation skips a write holding it');
}

// The money scopes are the scopes of the routes marked so, once each.
function test_api_app_money_scopes_reads_the_mark()
{
	$routes = array(
		array('id' => 'a', 'scope' => 'orders:write', 'moves_money' => true),
		array('id' => 'b', 'scope' => 'orders:write', 'moves_money' => true),
		array('id' => 'c', 'scope' => 'pages:write'),
		array('id' => 'd', 'moves_money' => true)
	);

	pg_assert_same(array('orders:write'), api_app_money_scopes($routes));
}

// The sign-in card: offered when the site has no OAuth application, "Set up
// already" and pointing at it when it has one.
function test_api_app_templates_sign_in_card()
{
	$fresh = api_app_templates_test_cards();

	pg_assert_same(0, $fresh['signin']['existing'], 'no application yet');
	pg_assert_same('', $fresh['signin']['badge'], 'no badge');

	$set = api_app_templates_test_cards(array('id' => 42, 'name' => 'Assistants'));

	pg_assert_same(42, $set['signin']['existing'], 'opens the one there is');
	pg_assert_same('Set up already', $set['signin']['badge']);
	pg_assert_same(0, $set['own']['existing'], 'other cards are not touched');
}

// The posted starting point names a card, or none.
function test_api_app_template_posted()
{
	$cards = api_app_templates_test_cards();

	pg_assert_same('automation', api_app_template_posted('automation', $cards));
	pg_assert_same('', api_app_template_posted('nothing', $cards));
	pg_assert_same('', api_app_template_posted(array('own'), $cards));
	pg_assert_same('', api_app_template_posted('', $cards));
}

// The sheet draws the step line and one choice card per starting point, the
// first one picked, with look A's buttons only.
function test_api_app_new_view_draws_the_cards()
{
	if (!function_exists('h')) {
		function h($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
	}

	if (!function_exists('get_token_field')) {
		function get_token_field() { return '<input type="hidden" name="token" value="t">'; }
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/api/app_new_view.php');

	$cards = api_app_templates_test_cards(array('id' => 7, 'name' => 'Assistants'));
	$html = api_app_new_view($cards, '<div class="api-perm"></div>', 'note');

	pg_assert_same(3, substr_count($html, 'class="stepper-item'), 'three steps');
	pg_assert_contains('aria-current="step">What connects</li>', $html, 'step 1 is current');

	foreach ($cards as $id => $card) {
		pg_assert_contains('name="start" value="' . $id . '" id="n_start_' . $id . '"', $html, $id . ': its card');
	}

	pg_assert_same(1, substr_count($html, ' checked>'), 'one card picked');
	pg_assert_contains('id="n_start_signin" data-kind="oauth" data-existing="7"', $html, 'the sign-in card opens the application there is');
	pg_assert_contains('Set up already', $html);
	pg_assert_false(strpos($html, 'btn btn-') !== false, 'no old button');
	pg_assert_contains('<details class="api-new-advanced"', $html, 'Advanced starts closed');
}
