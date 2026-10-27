<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a flow is built from (includes/workspace/flow_catalogue.php):
 * every kind of box with its outcomes, outputs and settings, the settings read
 * from the normalizers a save keeps the draft through, each listed word kept
 * by its normalizer and any other one replaced by the first; and, read from
 * the source, the validation of a draft that runs the publish check for the
 * owner alone, and the two routes offered as MCP tools.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_catalogue.php');

// The source of a function, comments taken out.
function ws_flow_catalogue_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	return (string) preg_replace('~^\s*(//|\*|/\*).*$~m', '', $source);
}

// The catalogue by kind.
function ws_flow_catalogue_test_kinds()
{
	$out = array();

	foreach (ws_flow_kind_catalogue() as $kind) {
		$out[$kind['kind']] = $kind;
	}

	return $out;
}

// Every kind ws_flow_kinds() knows is in the catalogue once, with its
// outcomes and outputs, and its settings are the keys its normalizer keeps.
function test_workspace_flow_catalogue_covers_every_kind()
{
	$kinds = ws_flow_catalogue_test_kinds();

	pg_assert_same(array_keys(ws_flow_kinds()), array_keys($kinds));
	pg_assert_same(count(ws_flow_kinds()), count(ws_flow_kind_catalogue()), 'each kind once');

	foreach (ws_flow_kinds() as $kind => $info) {
		$entry = $kinds[$kind];

		pg_assert_same(!empty($info['trigger']), $entry['starts'], $kind . ' starts');
		pg_assert_same(array_values($info['outcomes']), $entry['outcomes'], $kind . ' outcomes');
		pg_assert_same(array_column($info['outputs'], 'key'), array_column($entry['outputs'], 'key'), $kind . ' outputs');

		$keys = array_column($entry['settings'], 'key');

		pg_assert_same(array_map('strval', array_keys(ws_flow_config_normalize($kind, array()))), $keys, $kind . ' settings');

		// A setting sent as the catalogue says is kept by the normalizer.
		pg_assert_same($keys, array_map('strval', array_keys(ws_flow_config_normalize($kind, array_fill_keys($keys, '')))), $kind . ' keeps its keys');
	}

	pg_assert_true($kinds['manual']['starts'] && !$kinds['manual']['step']);
	pg_assert_true($kinds['post']['step']);
	pg_assert_same(array('body'), $kinds['post']['texts']);
	pg_assert_same('ssl', array_column($kinds['web_check']['outputs'], 'mode', 'key')['days_left']);
}

// The types read from the empty settings, and the ones set by hand.
function test_workspace_flow_catalogue_setting_types()
{
	$kinds = ws_flow_catalogue_test_kinds();
	$setting = function ($kind, $key) use ($kinds) {
		foreach ($kinds[$kind]['settings'] as $one) {
			if ($one['key'] === $key) {
				return $one;
			}
		}

		return null;
	};

	pg_assert_same('text', $setting('post', 'body')['type']);
	pg_assert_same('integer', $setting('post', 'channel_id')['type']);
	pg_assert_same('boolean', $setting('web_check', 'redirect')['type']);
	pg_assert_same(array('list', 'integer'), array($setting('notify', 'user_ids')['type'], $setting('notify', 'user_ids')['of']));
	pg_assert_same('object', $setting('report', 'metrics')['of']);
	pg_assert_same('object', $setting('change', 'fields')['type']);
	pg_assert_same('object', $setting('condition', 'rule')['type']);
	pg_assert_same(array('integer', true), array($setting('task', 'due_in')['type'], $setting('task', 'due_in')['blank']));
	pg_assert_same(null, $setting('post', 'body')['of']);
	pg_assert_same(null, $setting('post', 'body')['values']);

	// Every hand-set type names a setting that is there.
	foreach (array_keys(ws_flow_setting_overrides()) as $name) {
		list($kind, $key) = explode('.', $name, 2);

		pg_assert_true($setting($kind, $key) !== null, $name . ' is a setting');
	}
}

// Each listed word is kept as sent; any other one becomes the first.
function test_workspace_flow_catalogue_values_match_the_normalizers()
{
	foreach (ws_flow_setting_values() as $name => $words) {
		list($kind, $key) = explode('.', $name, 2);

		foreach ($words as $word) {
			pg_assert_same($word, ws_flow_config_normalize($kind, array($key => $word))[$key], $name . ' keeps ' . $word);
		}

		pg_assert_same($words[0], ws_flow_config_normalize($kind, array($key => 'no-such-word'))[$key], $name . ' replaces an unknown word');
		pg_assert_same($words[0], ws_flow_config_normalize($kind, array())[$key], $name . ' starts at its first word');
	}
}

// The publish check, with the owner's rights, runs for the owner alone and
// only when the draft has no problem; nothing is written.
function test_workspace_flow_validate_checks_rights_for_the_owner()
{
	$source = ws_flow_catalogue_test_source('ws_flow_validate');

	pg_assert_contains("ws_flow_access(\$viewer, \$flow) !== 'edit'", $source);
	pg_assert_contains('if ($owner && empty($problems))', $source);
	pg_assert_contains('ws_flow_publish_check($viewer, $flow)', $source);
	pg_assert_false((bool) preg_match('~\bdb\(|UPDATE|INSERT~', $source), 'nothing is written');
}

// Both routes write nothing and are tools; validation needs edit.
function test_workspace_flow_catalogue_routes()
{
	$routes = array();

	foreach (ws_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	foreach (array('workspace.flow_kinds.list', 'workspace.flows.validate') as $id) {
		pg_assert_true(isset($routes[$id]), $id . ' is a route');
		pg_assert_same('workspace:read', $routes[$id]['scope']);
		pg_assert_true(pg_mcp_route_is_offered($routes[$id]), $id . ' is a tool');
	}

	pg_assert_contains('ws_api_flow_editable(', ws_flow_catalogue_test_source('ws_api_flows_validate'));
	pg_assert_same(array_keys(ws_api_flow_kind_schema()), array_keys(ws_api_flow_kind_present(ws_flow_kind_catalogue()[0])));
}
