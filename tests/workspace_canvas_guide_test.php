<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the drawing guide (includes/workspace/canvas_guide.php): the
 * text and its short form carry every value the server uses, the palettes
 * read on their boards, and the route, the MCP resource, the prompt and the
 * assistants' draw tool all give it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_MCP_ENTRY')) {
	define('PG_MCP_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/oauth/server.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/dry_run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/protocol.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/tools.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/resources.php');
require_once(PG_FUNCTIONS_DIR . '/includes/mcp/prompts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_ai.php');

// Every scalar of a nested array, keyed by its path.
function workspace_canvas_guide_scalars($value, $path = '')
{
	$found = array();

	foreach ($value as $key => $item) {
		$at = ($path === '') ? (string) $key : $path . '.' . $key;

		if (is_array($item)) {
			$found += workspace_canvas_guide_scalars($item, $at);
		} else {
			$found[$at] = $item;
		}
	}

	return $found;
}

// WCAG contrast ratio of two #rrggbb colours.
function workspace_canvas_guide_contrast($a, $b)
{
	$lum = function ($hex) {
		$c = array();

		foreach (array(1, 3, 5) as $i) {
			$v = hexdec(substr($hex, $i, 2)) / 255;
			$c[] = ($v <= 0.04045) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
		}

		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	};

	$x = $lum($a);
	$y = $lum($b);

	return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

// The canvas routes beside the core ones, as tools of a caller with $scopes.
function workspace_canvas_guide_routes($scopes)
{
	return pg_mcp_routes_for(array_merge(api_schema(), ws_canvas_api_routes()), $scopes);
}

function test_workspace_canvas_guide_text_carries_every_value()
{
	$text = ws_canvas_guide_text();

	foreach (workspace_canvas_guide_scalars(ws_canvas_guide_values()) as $path => $value) {
		pg_assert_contains((string) $value, $text, $path);
	}

	foreach (array('dark', 'light') as $board) {
		$p = ws_canvas_guide_values()['palettes'][$board];
		pg_assert_contains('series in this order ' . implode(', ', $p['series']), $text, $board . ' series in order');
	}
}

function test_workspace_canvas_guide_short_form()
{
	$v = ws_canvas_guide_values();
	$short = ws_canvas_guide_short();

	pg_assert_true(mb_strlen($short) <= 600, 'short enough for a tool description');
	pg_assert_contains('/workspace/canvas/guide', $short);
	pg_assert_contains($v['font']['font'], $short);
	pg_assert_contains($v['boards'][0]['width'] . ' x ' . $v['boards'][0]['height'], $short);

	foreach (array('dark', 'light') as $board) {
		pg_assert_contains($v['palettes'][$board]['background'], $short, $board . ' board');
		pg_assert_contains(implode(' ', $v['palettes'][$board]['series']), $short, $board . ' series');
	}

	foreach (array('title', 'subtitle', 'label', 'axis', 'note') as $role) {
		pg_assert_contains((string) $v['type'][$role]['size'], $short, $role);
	}
}

function test_workspace_canvas_guide_values_hold_together()
{
	$v = ws_canvas_guide_values();

	pg_assert_same('card', $v['boards'][0]['name'], 'the card board is the default');
	pg_assert_same($v['scale_base'], min($v['boards'][0]['width'], $v['boards'][0]['height']), 'the scale is 1 on the card board');

	foreach ($v['boards'] as $board) {
		pg_assert_true($board['width'] >= 16 && $board['width'] <= 8000 && $board['height'] >= 16 && $board['height'] <= 8000, $board['name'] . ' within the artboard limits');
	}

	foreach ($v['type'] as $role => $row) {
		pg_assert_contains($row['weight'], array(300, 400, 600, 700, 900), $role . ': a weight the editor keeps');
		pg_assert_true($row['size'] >= $v['type']['note']['size'], $role . ': nothing under the note');
		pg_assert_true(isset($v['palettes']['dark'][$row['ink']]), $role . ': its ink is a palette role');
	}

	foreach ($v['spacing']['steps'] as $step) {
		pg_assert_same(0, $step % $v['grid']['unit'], $step . ' is a multiple of the unit');
	}

	pg_assert_same($v['marks']['series_max'], count($v['palettes']['dark']['series']));
	pg_assert_same($v['marks']['series_max'], count($v['palettes']['light']['series']));
	pg_assert_same(array('bar', 'stacked_bar', 'line', 'area', 'donut', 'sparkline', 'figure_tile'), $v['kinds']);
}

function test_workspace_canvas_guide_palettes_read_on_their_boards()
{
	foreach (ws_canvas_guide_values()['palettes'] as $board => $p) {
		foreach (workspace_canvas_guide_scalars($p) as $role => $colour) {
			pg_assert_true((bool) preg_match('/^#[0-9a-f]{6}$/', $colour), $board . ' ' . $role . ' is #rrggbb');
		}

		pg_assert_true(workspace_canvas_guide_contrast($p['ink'], $p['background']) >= 7, $board . ' ink');
		pg_assert_true(workspace_canvas_guide_contrast($p['ink2'], $p['background']) >= 4.5, $board . ' ink2');
		pg_assert_true(workspace_canvas_guide_contrast($p['ink3'], $p['background']) >= 3, $board . ' ink3');

		foreach (array_merge($p['series'], array($p['up'], $p['down'])) as $colour) {
			pg_assert_true(workspace_canvas_guide_contrast($colour, $p['background']) >= 3, $board . ' ' . $colour . ' reads as a mark');
		}

		pg_assert_same(count($p['series']), count(array_unique($p['series'])), $board . ' series are distinct');
	}
}

function test_workspace_canvas_guide_route_and_tool_descriptions()
{
	$routes = array_column(ws_canvas_api_routes(), null, 'id');
	$guide = $routes['workspace.canvas.guide'];

	pg_assert_same(array('GET', '/workspace/canvas/guide', 'workspace:read', array()), array($guide['method'], $guide['path'], $guide['scope'], $guide['params']));
	pg_assert_true(function_exists($guide['handler']), 'its handler exists');

	foreach (array('workspace.channels.canvases.create', 'workspace.messages.canvas.edit') as $id) {
		pg_assert_contains(ws_canvas_guide_short(), $routes[$id]['description'], $id . ' carries the short guide');
	}

	$tools = array();

	ws_canvas_ai_tools(function ($name, $description) use (&$tools) {
		$tools[$name] = $description;

		return $name;
	});

	pg_assert_contains(ws_canvas_guide_text(), $tools['draw_canvas'], 'the assistants\' draw tool carries the whole guide');
	pg_assert_false(strpos($tools['draw_canvas'], '\n') !== false, 'no escaped newline in the description');
}

function test_workspace_canvas_guide_mcp_resource_and_prompt()
{
	$reader = workspace_canvas_guide_routes(array('workspace:read', 'workspace:write'));
	$kinds = pg_mcp_resource_kinds();

	pg_assert_same('workspace.canvas.guide', $kinds['canvas_guide']['route']);
	pg_assert_same('pinegrap://workspace/canvas-guide', pg_mcp_resource_uri('canvas_guide', array()));
	pg_assert_true(pg_mcp_resource_route('canvas_guide', $reader) !== null, 'readable with workspace:read');

	pg_assert_true(pg_mcp_prompt_is_offered('canvas_chart', $reader), 'offered to a writer');
	pg_assert_false(pg_mcp_prompt_is_offered('canvas_chart', workspace_canvas_guide_routes(array('workspace:read'))), 'not without a route to draw with');

	pg_assert_same('Missing required argument: channel_id', pg_mcp_prompt_arguments('canvas_chart', array('topic' => 'x'))['error']);

	$values = pg_mcp_prompt_arguments('canvas_chart', array('channel_id' => '25', 'topic' => 'Sales this week'))['values'];
	$text = pg_mcp_prompt_result('canvas_chart', $values, 'Acme Store', $reader, null)['messages'][0]['content']['text'];

	foreach (array('channel 25', 'Acme Store', 'Sales this week', 'pinegrap://workspace/canvas-guide', 'workspace_canvas_guide', pg_mcp_tool_name('workspace.channels.canvases.create'), 'layers split', 'dry_run true') as $needle) {
		pg_assert_contains($needle, $text, $needle);
	}

	$item = array('uri' => 'pinegrap://workspace/canvas-guide', 'mimeType' => 'application/json', 'text' => '{"data":{}}');
	$embedded = pg_mcp_prompt_result('canvas_chart', $values, 'Acme Store', $reader, $item);

	pg_assert_same(2, count($embedded['messages']), 'the guide as a second message');
	pg_assert_contains('attached below', $embedded['messages'][0]['content']['text']);
}
