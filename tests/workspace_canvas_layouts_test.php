<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ready layouts (includes/workspace/canvas_layouts.php): each
 * layout in both board palettes is drawn by the guide's values, its layers
 * are ones the image editor's model lays out the same way, its fill and
 * chart values redraw it, and the routes and the MCP prompt offer it.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_layouts.php');

// A layer by its name.
function workspace_canvas_layouts_layer($doc, $name)
{
	foreach ($doc['layers'] as $layer) {
		if (($layer['name'] ?? '') === $name) {
			return $layer;
		}
	}

	return null;
}

function test_workspace_canvas_layouts_follow_the_guide()
{
	$v = ws_canvas_guide_values();
	$boards = array_column($v['boards'], null, 'name');

	pg_assert_same(array('figures_chart', 'report_page', 'comparison', 'slide'), array_keys(ws_canvas_layouts()), 'the four layouts');

	foreach (ws_canvas_layouts() as $name => $layout) {
		foreach (ws_canvas_layout_themes() as $theme) {
			$doc = ws_canvas_layout_doc($name, $theme);
			$p = $v['palettes'][$theme];
			$at = $name . ' ' . $theme;

			pg_assert_true(is_array($doc), $at . ': drawn');
			pg_assert_same(array($boards[$layout['board']]['width'], $boards[$layout['board']]['height'], $p['background']), array($doc['artboard']['width'], $doc['artboard']['height'], $doc['artboard']['background']), $at . ': the board and its background');

			$scale = min($doc['artboard']['width'], $doc['artboard']['height']) / $v['scale_base'];
			$sizes = array();

			foreach ($v['type'] as $row) {
				$sizes[] = (int) round($row['size'] * $scale);
			}

			$colours = array_merge(array($p['ink'], $p['ink2'], $p['ink3'], $p['grid'], $p['baseline'], $p['up'], $p['down'], $p['other']), $p['series']);
			$names = array();

			foreach ($doc['layers'] as $layer) {
				$names[] = $layer['name'] ?? '';
				pg_assert_contains($layer['color'], $colours, $at . ' ' . ($layer['name'] ?? '') . ': a colour of the palette');
				pg_assert_true(($layer['x'] >= 0) && ($layer['y'] >= 0) && ($layer['x'] + $layer['w'] <= $doc['artboard']['width']) && ($layer['y'] + $layer['h'] <= $doc['artboard']['height']), $at . ' ' . $layer['name'] . ': on the board');

				if ($layer['type'] === 'text') {
					pg_assert_same($v['font']['font'], $layer['font'], $at . ' ' . $layer['name'] . ': the guide\'s font');
					pg_assert_contains($layer['size'], $sizes, $at . ' ' . $layer['name'] . ': a size of the type scale');
					pg_assert_contains($layer['weight'], array(300, 400, 600, 700, 900), $at . ' ' . $layer['name'] . ': a weight the editor keeps');
					$change = (substr($layer['name'], -7) === '_change');
					pg_assert_contains($layer['color'], $change ? array($p['up'], $p['down']) : array($p['ink'], $p['ink2'], $p['ink3']), $at . ' ' . $layer['name'] . ': text in ink, a change in up or down');
				}
			}

			pg_assert_same(count($names), count(array_unique(array_filter($names))), $at . ': every layer named once');
			pg_assert_true(workspace_canvas_layouts_layer($doc, 'title') !== null && workspace_canvas_layouts_layer($doc, 'note') !== null, $at . ': a title and a note');
		}
	}

	pg_assert_same(null, ws_canvas_layout_doc('poster', 'light'), 'an unknown layout');
	pg_assert_same(null, ws_canvas_layout_doc('slide', 'sepia'), 'an unknown theme');
}

function test_workspace_canvas_layouts_list()
{
	$list = array_column(ws_canvas_layouts_list(), null, 'name');

	pg_assert_same(array(6, 6, 0, 5), array_column(array_values($list), 'chart_values'), 'the bars of each chart');
	pg_assert_same(array('light', 'dark'), $list['slide']['themes'], 'both themes');
	pg_assert_same(array(1920, 1080), array($list['slide']['width'], $list['slide']['height']), 'the slide board');

	$layers = array_column($list['figures_chart']['layers'], null, 'name');

	pg_assert_same(array('text', 'Title'), array($layers['title']['kind'], $layers['title']['text']), 'a text layer with its words');
	pg_assert_same('shape', $layers['chart_bar_1']['kind'], 'a bar is a shape');
	pg_assert_true(isset($layers['figure_3_change'], $layers['chart_label_6'], $layers['chart_value']), 'figures, labels and the value');
}

function test_workspace_canvas_layouts_fill_and_chart_values()
{
	$filled = ws_canvas_layout_fill('figures_chart', 'dark', array('title' => 'Sales', 'chart_label_1' => 'May', 'chart_values' => array(10, 20, 30, 40, 50, 100)));

	pg_assert_same('', $filled['error'], 'filled');

	$doc = $filled['doc'];
	pg_assert_same(array('Sales', 'May'), array(workspace_canvas_layouts_layer($doc, 'title')['text'], workspace_canvas_layouts_layer($doc, 'chart_label_1')['text']), 'the words');
	pg_assert_same(array('0', '25', '50', '75', '100'), array_map(function ($i) use ($doc) {
		return workspace_canvas_layouts_layer($doc, 'chart_axis_' . $i)['text'];
	}, range(0, 4)), 'the axis on round steps');
	pg_assert_same('100', workspace_canvas_layouts_layer($doc, 'chart_value')['text'], 'the last value');

	$first = workspace_canvas_layouts_layer($doc, 'chart_bar_1');
	$last = workspace_canvas_layouts_layer($doc, 'chart_bar_6');
	pg_assert_true(abs($last['h'] / 10 - $first['h']) <= 1, 'bars in proportion');
	pg_assert_same($first['y'] + $first['h'], $last['y'] + $last['h'], 'one baseline');
	pg_assert_same(ws_canvas_guide_values()['palettes']['dark']['series'][0], $last['color'], 'the first series colour');

	pg_assert_same(array(40000, 10000), array_values(ws_canvas_layout_axis(array(31200))), 'a round top');
	pg_assert_same('31.200', ws_canvas_layout_number(31200), 'thousands grouped');

	foreach (array(array(1, 2), array(1, 2, 3, 4, 5, -6), array(1, 2, 3, 4, 5, 'x'), 'many', array('a' => 1)) as $bad) {
		$refused = ws_canvas_layout_fill('figures_chart', 'light', array('chart_values' => $bad));
		pg_assert_same('chart', $refused['error'], 'values refused: ' . json_encode($bad));
		pg_assert_contains('list of 6 numbers', ws_canvas_layout_fill_error_text($refused));
	}

	$none = ws_canvas_layout_fill('comparison', 'light', array('chart_values' => array(1)));
	pg_assert_contains('no chart', ws_canvas_layout_fill_error_text($none), 'a layout without a chart');

	$name = ws_canvas_layout_fill('slide', 'light', array('headline' => 'x'));
	pg_assert_same('name', $name['error'], 'a name the layout has not');
	pg_assert_contains('point_1 (text)', ws_canvas_layout_fill_error_text($name), 'the refusal names its layers');

	pg_assert_same('kind', ws_canvas_layout_fill('slide', 'light', array('chart_bar_1' => 'x'))['error'], 'a shape is not filled');
}

// The editor's model lays out every text layer in the box the server gave
// it, with the same half-size letter estimate, and finds every layer on the
// board.
function test_workspace_canvas_layouts_editor_model()
{
	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$docs = array();

	foreach (array_keys(ws_canvas_layouts()) as $name) {
		$docs[$name] = ws_canvas_layout_doc($name, 'dark');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcanvaslayouts');
	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var docs = ' . json_encode($docs) . ', out = {};' . "\n"
		. 'var measure = function (font, s) { var px = parseFloat(/([0-9.]+)px/.exec(font)[1]); return Array.from(s).length * px / 2; };' . "\n"
		. 'Object.keys(docs).forEach(function (n) { var d = docs[n], bad = [];' . "\n"
		. '  d.layers.forEach(function (l) { if (l.type === "text") { var a = M.textLayout(l, measure); if (Math.abs(a.w - l.w) > 0.02 || Math.abs(a.h - l.h) > 0.02) { bad.push(l.name); } } });' . "\n"
		. '  var b = M.bounds(d.layers); out[n] = { bad: bad, inside: b.x >= -0.5 && b.y >= -0.5 && b.x + b.w <= d.artboard.width + 0.5 && b.y + b.h <= d.artboard.height + 0.5, keys: Object.keys(M.newDocument({ blank: d.artboard })).sort().join() === Object.keys(d).sort().join() }; });' . "\n"
		. 'console.log(JSON.stringify(out));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the model ran: ' . implode("\n", array_slice($out, 0, 8)));

	foreach ((array) json_decode(implode("\n", $out), true) as $name => $row) {
		pg_assert_same(array(), $row['bad'], $name . ': text boxes as the editor lays them out');
		pg_assert_true($row['inside'], $name . ': every layer on the board');
		pg_assert_true($row['keys'], $name . ': the fields of the editor\'s document');
	}
}

function test_workspace_canvas_layouts_routes_and_prompt()
{
	$routes = array_column(ws_canvas_api_routes(), null, 'id');
	$params = array_column($routes['workspace.channels.canvases.create']['params'], null, 'name');

	pg_assert_same(array_keys(ws_canvas_layouts()), $params['layout']['values'], 'layout names the four');
	pg_assert_same(array('light', 'dark'), $params['theme']['values'], 'theme');
	pg_assert_contains('chart_values', $params['fill']['description']);
	pg_assert_contains('ready layout', $routes['workspace.channels.canvases.create']['description']);
	pg_assert_true(isset($routes['workspace.canvas.templates']['returns']['layouts']), 'the list returns layouts');
	pg_assert_contains('layouts', $routes['workspace.canvas.templates']['description']);

	$prompt = pg_mcp_prompt_canvas_chart_text(array('channel_id' => 25), 'Site', true);
	pg_assert_contains('ready layout', $prompt, 'the prompt offers the layouts');
}
