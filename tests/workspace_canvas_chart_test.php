<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the chart canvas operation (includes/workspace/canvas_chart.php):
 * the checks and their refusals, the channel's tone of a one-series chart,
 * numbers and axis steps, each kind drawn as layers the editor's model opens
 * (run in Node), and the route and tool rows that offer it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_split.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_ai.php');

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

// One operation of each kind, as an agent writes it.
function workspace_canvas_chart_ops()
{
	$days = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');

	return array(
		'bar'         => array('op' => 'chart', 'kind' => 'bar', 'title' => 'Sales this week', 'subtitle' => 'Daily revenue', 'labels' => $days, 'values' => array(18400, 22100, 19800, 24600, 31200, 27400, 15900), 'note' => 'Source: orders'),
		'stacked_bar' => array('op' => 'chart', 'kind' => 'stacked_bar', 'title' => 'Orders by channel', 'labels' => $days, 'series' => array(
			array('name' => 'Site', 'values' => array(42, 51, 47, 58, 66, 61, 38)), array('name' => 'Market', 'values' => array(18, 22, 25, 21, 30, 34, 26)), array('name' => 'Store', 'values' => array(9, 12, 10, 14, 17, 15, 11)))),
		'line'        => array('op' => 'chart', 'kind' => 'line', 'theme' => 'dark', 'title' => 'Visitors', 'labels' => array('W1', 'W2', 'W3', 'W4', 'W5'), 'series' => array(
			array('name' => 'This year', 'values' => array(8200, 8900, 8600, 9400, 10100)), array('name' => 'Last year', 'values' => array(7400, 7600, 7900, 7700, 8300)))),
		'area'        => array('op' => 'chart', 'kind' => 'area', 'board' => 'slide', 'title' => 'Stock value', 'labels' => array('May', 'Jun', 'Jul'), 'values' => array(412000, 398000, 431000)),
		'donut'       => array('op' => 'chart', 'kind' => 'donut', 'board' => 'square', 'title' => 'Payment methods', 'labels' => array('Card', 'Transfer', 'Cash', 'Other'), 'values' => array(612, 214, 98, 41)),
		'sparkline'   => array('op' => 'chart', 'kind' => 'sparkline', 'label' => 'Left in cart', 'value' => '214', 'values' => array(180, 192, 176, 201, 214)),
		'figure_tile' => array('op' => 'chart', 'kind' => 'figure_tile', 'theme' => 'dark', 'label' => 'Revenue this week', 'value' => '$159,400', 'delta' => '+12.4%', 'vs' => 'on last week', 'values' => array(98, 104, 101, 112, 159)),
	);
}

// Runs a script with the editor's model as M; its JSON, null without Node.
function workspace_canvas_chart_node($script)
{
	$out = array();
	$code = 1;

	if (!function_exists('exec')) {
		return null;
	}

	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcanvaschart');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$json = ($code === 0) ? json_decode(implode("\n", $out), true) : null;

	pg_assert_true(is_array($json), ($code === 0) ? 'node ran' : implode("\n", $out));

	return $json;
}

// A one-layer canvas to draw on.
function workspace_canvas_chart_doc()
{
	$made = ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200"><rect width="400" height="200" fill="#123456"/></svg>', '#ffffff', false, null);

	return json_decode(json_encode($made['doc']), true);
}

function test_workspace_canvas_chart_check_defaults_and_refusals()
{
	$ops = workspace_canvas_chart_ops();
	$bar = ws_canvas_chart_check($ops['bar']);

	pg_assert_same('', $bar['error']);
	pg_assert_same(array('light', 'en', 'card', 1200, 675), array($bar['chart']['theme'], $bar['chart']['format'], $bar['chart']['board']['name'], $bar['chart']['board']['width'], $bar['chart']['board']['height']), 'defaults');
	pg_assert_same('figure_tile', ws_canvas_chart_check($ops['figure_tile'])['chart']['board']['name'], 'a tile has its own board');

	$refused = array(
		'chart_kind'  => array('kind' => 'pie'),
		'chart_theme' => array('theme' => 'blue'),
		'chart_board' => array('board' => 'figure_tile'),
		'chart_words' => array('title' => ''),
		'chart_data'  => array('values' => array(1, 2)),
	);

	foreach ($refused as $code => $change) {
		pg_assert_same($code, ws_canvas_chart_check(array_merge($ops['bar'], $change))['error'], $code);
		pg_assert_true(ws_canvas_edit_error_text($code) !== '', $code . ' has its words');
	}

	pg_assert_same('chart_data', ws_canvas_chart_check(array_merge($ops['bar'], array('values' => array(1, 2, 3, 4, 5, 6, -1))))['error'], 'no negative value');
	pg_assert_same('chart_data', ws_canvas_chart_check(array_merge($ops['bar'], array('values' => array(1, 2, 3, 4, 5, 6, '7'))))['error'], 'numbers only');
	pg_assert_same('chart_data', ws_canvas_chart_check(array_merge($ops['bar'], array('highlight' => 7)))['error'], 'a highlight inside the labels');
	pg_assert_same('chart_board', ws_canvas_chart_check(array_merge($ops['sparkline'], array('board' => 'card')))['error'], 'a strip stays a strip');
	pg_assert_same('chart_data', ws_canvas_chart_check(array_merge($ops['donut'], array('series' => array(array('values' => array(1, 1, 1, 1)), array('values' => array(1, 1, 1, 1))))))['error'], 'a donut is one series');
	pg_assert_same('chart_words', ws_canvas_chart_check(array_merge($ops['figure_tile'], array('value' => '')))['error'], 'a tile needs its value');
}

function test_workspace_canvas_chart_tone_of_the_channel()
{
	$v = ws_canvas_guide_values();

	foreach ($v['palettes'] as $board => $p) {
		pg_assert_same($p['series'][0], ws_canvas_chart_tone('', $p), $board . ': no channel colour, the guide\'s first');
		pg_assert_same($p['series'][0], ws_canvas_chart_tone('red', $p), $board . ': no colour code, the guide\'s first');

		foreach (ws_palette_hexes() as $hex) {
			$tone = ws_canvas_chart_tone($hex, $p);

			pg_assert_true(ws_canvas_chart_contrast($tone, $p['background']) >= 3, $board . ' ' . $hex . ' reads as a mark (' . $tone . ')');

			if (ws_canvas_chart_contrast($hex, $p['background']) >= 3) {
				pg_assert_same($hex, $tone, $board . ' ' . $hex . ' kept as it is');
			}
		}
	}

	$dark = ws_canvas_chart_tone('#3f4a5a', $v['palettes']['dark']);
	$lab = ws_canvas_chart_oklab($dark);
	$from = ws_canvas_chart_oklab('#3f4a5a');

	pg_assert_true($lab[0] > $from[0], 'lightened on a dark board');
	pg_assert_true(abs(atan2($lab[2], $lab[1]) - atan2($from[2], $from[1])) < 0.2, 'its own hue');
	pg_assert_same('#5892ea', ws_canvas_chart_hex(ws_canvas_chart_oklab('#5892ea')), 'OKLab there and back');
}

function test_workspace_canvas_chart_numbers_and_steps()
{
	pg_assert_same('31.2K', ws_canvas_chart_number(31200, 'en'));
	pg_assert_same('31,2 B', ws_canvas_chart_number(31200, 'tr'));
	pg_assert_same('9,900', ws_canvas_chart_number(9900, 'en'));
	pg_assert_same('9.900', ws_canvas_chart_number(9900, 'tr'));
	pg_assert_same('40K', ws_canvas_chart_number(40000, 'en'));
	pg_assert_same(10000.0, ws_canvas_chart_step(31200, 5));
	pg_assert_same(20.0, ws_canvas_chart_step(66, 5));
	pg_assert_same(2.5, ws_canvas_chart_step(12, 5));
}

function test_workspace_canvas_chart_each_kind_is_drawn()
{
	$v = ws_canvas_guide_values();

	foreach (workspace_canvas_chart_ops() as $kind => $op) {
		$edited = ws_canvas_edit_apply(workspace_canvas_chart_doc(), array($op), null, null, '#3e8a7a');

		pg_assert_same('', $edited['error'], $kind . ' drawn');

		if ($edited['error'] !== '') {
			continue;
		}

		$doc = $edited['doc'];
		$chart = ws_canvas_chart_check($op)['chart'];
		$p = $v['palettes'][$chart['theme']];

		pg_assert_same(array($chart['board']['width'], $chart['board']['height'], $p['background']), array($doc['artboard']['width'], $doc['artboard']['height'], $doc['artboard']['background']), $kind . ': the board');

		$texts = array_values(array_filter($doc['layers'], function ($layer) {
			return $layer['type'] === 'text';
		}));

		pg_assert_true(count($texts) >= 2, $kind . ': its words are text layers');

		foreach ($texts as $text) {
			pg_assert_same('g:Inter', $text['font'], $kind . ': ' . $text['text'] . ' in the guide\'s font');
			pg_assert_contains((int) $text['weight'], array(300, 400, 600, 700, 900), $kind . ': a weight the editor keeps');
		}

		pg_assert_true(count($doc['layers']) <= 200, $kind . ': a sensible number of layers');

		$svg = ws_canvas_chart_svg($chart, '#3e8a7a')['svg'];
		$one = in_array($kind, array('bar', 'area', 'sparkline', 'figure_tile'), true);

		pg_assert_same($one, strpos($svg, ws_canvas_chart_tone('#3e8a7a', $p)) !== false, $kind . ': the channel\'s tone only for one series');
		pg_assert_false(strpos($svg, 'opacity') !== false, $kind . ': solid colours');
	}
}

function test_workspace_canvas_chart_layers_open_in_the_editor_model()
{
	$docs = array();

	foreach (workspace_canvas_chart_ops() as $kind => $op) {
		$doc = ws_canvas_edit_apply(workspace_canvas_chart_doc(), array($op))['doc'];
		$docs[$kind] = array('doc' => $doc, 'layers' => count($doc['layers']));
	}

	$node = workspace_canvas_chart_node('var docs = ' . json_encode($docs) . ', out = {};' . "\n"
		. 'Object.keys(docs).forEach(function (k) { var d = M.canvasOpenDoc(docs[k].doc); out[k] = d ? d.layers.length : -1; });' . "\n"
		. 'console.log(JSON.stringify(out));');

	if (!is_array($node)) {
		return;
	}

	foreach ($docs as $kind => $row) {
		pg_assert_same($row['layers'], $node[$kind], $kind . ': the model opens every layer');
	}
}

function test_workspace_canvas_chart_redraw_changes_the_data()
{
	$op = workspace_canvas_chart_ops()['bar'];
	$first = ws_canvas_edit_apply(workspace_canvas_chart_doc(), array($op))['doc'];
	$op['values'][6] = 40000;
	$op['title'] .= ' (edited)';
	$second = ws_canvas_edit_apply($first, array($op))['doc'];
	$words = array_column(array_filter($second['layers'], function ($layer) {
		return $layer['type'] === 'text';
	}), 'text');

	pg_assert_contains('Sales this week (edited)', $words, 'the new title');
	pg_assert_contains('40K', $words, 'the axis reaches the new top');
	pg_assert_contains('40K', $words, 'and the new highest bar is labelled');
}

function test_workspace_canvas_chart_routes_and_tools_offer_it()
{
	$routes = array_column(ws_canvas_api_routes(), null, 'id');
	$edit = array_column($routes['workspace.messages.canvas.edit']['params'], null, 'name');

	foreach (array('kind', 'theme', 'board', 'title', 'subtitle', 'note', 'labels', 'values', 'series', 'highlight', 'label', 'value', 'delta', 'vs', 'format') as $key) {
		pg_assert_true(isset($edit['operations']['of'][$key]), 'the operation list declares ' . $key);
	}

	pg_assert_contains('chart (kind', $routes['workspace.messages.canvas.edit']['description']);
	pg_assert_contains('chart', ws_canvas_edit_ops());

	$tools = array();

	ws_canvas_ai_tools(function ($name, $description) use (&$tools) {
		$tools[$name] = $description;

		return $name;
	});

	pg_assert_contains('chart (kind', $tools['edit_canvas']);
}

function test_workspace_canvas_chart_drawn_on_create_and_by_assistants()
{
	$routes = array_column(ws_canvas_api_routes(), null, 'id');
	$create = array_column($routes['workspace.channels.canvases.create']['params'], null, 'name');

	pg_assert_same('object', $create['chart']['type'], 'create takes a chart');
	pg_assert_contains('chart (kind', $create['chart']['description']);

	$op = workspace_canvas_chart_ops()['bar'];
	unset($op['op']);

	pg_assert_same(null, ws_canvas_ai_chart(null), 'no chart given');
	pg_assert_same('bar', ws_canvas_ai_chart(json_encode($op))['kind'], 'a JSON object in a string');
	pg_assert_same(ws_canvas_edit_error_text('chart_kind'), ws_canvas_ai_chart('{"kind": "pie"}')['error']);
	pg_assert_same(ws_canvas_edit_error_text('chart_kind'), ws_canvas_ai_chart('not json')['error']);
	pg_assert_contains('channel it is drawn in', ws_canvas_guide_text(), 'the guide says a one-series chart takes the channel\'s colour');
}

function test_workspace_canvas_chart_channel_tone_of_a_row()
{
	pg_assert_same('', ws_canvas_chart_channel_tone(array()), 'no colour');
	pg_assert_same('', ws_canvas_chart_channel_tone(array('color' => 0)), 'none chosen');
	pg_assert_same(ws_palette_hex(11), ws_canvas_chart_channel_tone(array('color' => '11')), 'its place in the palette');
	pg_assert_same('#6b5ca5', ws_canvas_chart_channel_tone(array('color' => '#6B5CA5')), 'a colour code');
}

function test_workspace_canvas_chart_leaves_room_above_the_top_value()
{
	$op = workspace_canvas_chart_ops()['bar'];
	$op['values'][6] = 40000;
	$svg = ws_canvas_chart_svg(ws_canvas_chart_check($op)['chart'])['svg'];

	pg_assert_contains('>50K</text>', $svg, 'a step above a top value of 40K');

	$op['values'][6] = 15900;
	$svg = ws_canvas_chart_svg(ws_canvas_chart_check($op)['chart'])['svg'];

	pg_assert_contains('>40K</text>', $svg, '31.2K keeps its axis to 40K');
	pg_assert_false(strpos($svg, '>50K</text>') !== false, 'with no step to spare');
}

function test_workspace_canvas_chart_line_widths()
{
	$v = ws_canvas_guide_values();
	$line = ws_canvas_chart_svg(ws_canvas_chart_check(workspace_canvas_chart_ops()['line'])['chart'])['svg'];
	$tile = ws_canvas_chart_svg(ws_canvas_chart_check(workspace_canvas_chart_ops()['figure_tile'])['chart'])['svg'];

	pg_assert_contains('stroke-width="' . $v['marks']['line'] . '"', $line, 'a line on a card board');
	pg_assert_contains('stroke-width="' . $v['marks']['trend_line'] . '"', $tile, 'a tile\'s trend on its own board');
	pg_assert_true($v['marks']['line'] === 2 * $v['marks']['trend_line'], 'a card shown at half size reads as wide as the tile\'s trend');
}
