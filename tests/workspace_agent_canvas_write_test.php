<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an agent writing a canvas's layers
 * (includes/workspace/canvas_layer_write.php through ws_canvas_edit_apply()):
 * text, picture, shape and icon layers as the editor's tools make them,
 * style, order, name and lock, a layer named by its name, the fonts route
 * and the rows of the routes and tools.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_ai.php');

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

// A blank 600 x 300 canvas with one svg layer l0.
function workspace_canvas_write_doc()
{
	return json_decode(json_encode(ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="300"><rect width="600" height="300" fill="#eeeeee"/></svg>')['doc']), true);
}

function workspace_canvas_write_apply($operations, $file = null)
{
	return ws_canvas_edit_apply(workspace_canvas_write_doc(), $operations, null, $file);
}

function test_workspace_canvas_write_adds()
{
	$files = function ($op) {
		return (($op['file_id'] ?? 0) === 9) ? array('id' => 9, 'url' => '/logo.png', 'width' => 400, 'height' => 200) : null;
	};
	$done = workspace_canvas_write_apply(array(
		array('op' => 'add', 'type' => 'text', 'text' => 'Sales', 'font' => 'g:Inter', 'size' => 40, 'color' => '#FF0000', 'align' => 'center', 'name' => 'title', 'x' => 10, 'y' => 20),
		array('op' => 'add', 'type' => 'picture', 'file_id' => 9),
		array('op' => 'add', 'type' => 'shape', 'shape' => 'rectangle', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 50, 'radius' => 80, 'fill' => 'soft', 'color' => '#00aa00'),
		array('op' => 'add', 'type' => 'shape', 'shape' => 'line', 'x1' => 0, 'y1' => 0, 'x2' => 0, 'y2' => 100, 'stroke' => 4),
		array('op' => 'add', 'type' => 'icon', 'icon' => 'bi-bar-chart', 'lock' => true),
	), $files);
	$layers = $done['doc']['layers'];

	pg_assert_same('', $done['error'], 'added');
	pg_assert_same(array('l0', 'l1', 'l2', 'l3', 'l4', 'l5'), array_column($layers, 'id'), 'on top, in order');

	$text = $layers[1];
	pg_assert_same(array('text', 'Sales', 'g:Inter', 40, '#ff0000', 'center', 'title', 700, 1.25, 10, 20), array($text['type'], $text['text'], $text['font'], $text['size'], $text['color'], $text['align'], $text['name'], $text['weight'], $text['lineHeight'], $text['x'], $text['y']), 'a text layer');
	pg_assert_same(array(100, 50), array($text['w'], $text['h']), 'its box from the estimated layout');

	pg_assert_same(array('image', '/logo.png', 9, 240, 120, 180, 90), array($layers[2]['type'], $layers[2]['src'], $layers[2]['fileId'], $layers[2]['w'], $layers[2]['h'], $layers[2]['x'], $layers[2]['y']), 'a picture, 40 % of the board, centred');
	pg_assert_same(array('rect', 'soft', 25, '#00aa00', 'solid', 2.5), array($layers[3]['type'], $layers[3]['fill'], $layers[3]['radius'], $layers[3]['color'], $layers[3]['dash'], $layers[3]['stroke']), 'a rectangle, its radius cut to half the shorter side, the medium stroke');
	pg_assert_same(array('line', -50, 48, 100, 4, 90), array($layers[4]['type'], $layers[4]['x'], $layers[4]['y'], $layers[4]['w'], $layers[4]['h'], $layers[4]['rot']), 'a line along its box');
	pg_assert_true(!isset($layers[4]['fill']), 'a line has no fill');
	pg_assert_same(array('glyph', 'bi', ws_canvas_write_icon('bar-chart'), '#212529', 60, 60, true), array($layers[5]['type'], $layers[5]['set'], $layers[5]['code'], $layers[5]['color'], $layers[5]['w'], $layers[5]['h'], $layers[5]['locked']), 'an icon, locked');
	pg_assert_true($layers[5]['code'] > 0, 'the icon map is read');
}

function test_workspace_canvas_write_refusals()
{
	$cases = array(
		array(array('op' => 'add', 'type' => 'video'), 'type'),
		array(array('op' => 'add', 'type' => 'text', 'text' => ''), 'text'),
		array(array('op' => 'add', 'type' => 'text', 'text' => 'a', 'font' => 'Nowhere'), 'font'),
		array(array('op' => 'add', 'type' => 'text', 'text' => 'a', 'color' => 'red'), 'color'),
		array(array('op' => 'add', 'type' => 'text', 'text' => 'a', 'fill' => 'solid'), 'style'),
		array(array('op' => 'add', 'type' => 'picture', 'file_id' => 1), 'picture'),
		array(array('op' => 'add', 'type' => 'shape', 'shape' => 'star'), 'shape'),
		array(array('op' => 'add', 'type' => 'shape', 'shape' => 'line', 'x1' => 0), 'line'),
		array(array('op' => 'add', 'type' => 'icon', 'icon' => 'no-such-icon'), 'icon'),
		array(array('op' => 'add', 'type' => 'icon', 'icon' => 'star', 'name' => 'l0'), 'name'),
		array(array('op' => 'add', 'type' => 'icon', 'icon' => 'star', 'lock' => 'yes'), 'lock'),
		array(array('op' => 'style', 'layer' => 'l0', 'font' => 'serif'), 'style'),
		array(array('op' => 'style', 'layer' => 'l0'), 'style'),
		array(array('op' => 'order', 'layer' => 'l0', 'to' => 'up'), 'order'),
		array(array('op' => 'order', 'layer' => 'l0', 'to' => 'above', 'target' => 'nothing'), 'layer'),
		array(array('op' => 'name', 'layer' => 'l0', 'name' => ''), 'name'),
		array(array('op' => 'lock', 'layer' => 'l0'), 'lock'),
	);

	foreach ($cases as $case) {
		pg_assert_same($case[1], workspace_canvas_write_apply(array($case[0]), function () { return null; })['error'], json_encode($case[0]));
	}
}

function test_workspace_canvas_write_changes()
{
	$doc = workspace_canvas_write_apply(array(
		array('op' => 'add', 'type' => 'text', 'text' => 'Hi', 'size' => 20, 'align' => 'right', 'x' => 100, 'y' => 0, 'name' => 'greeting'),
		array('op' => 'add', 'type' => 'icon', 'icon' => 'star', 'name' => 'star'),
		array('op' => 'add', 'type' => 'shape', 'shape' => 'ellipse'),
	))['doc'];

	$styled = ws_canvas_edit_apply($doc, array(array('op' => 'style', 'layer' => 'greeting', 'size' => 40, 'weight' => 330, 'italic' => false, 'font' => 'Lobster', 'opacity' => 0.5)));
	$text = $styled['doc']['layers'][1];
	pg_assert_same('', $styled['error'], 'styled by name');
	pg_assert_same(array(40, 300, false, 'g:Lobster', 0.5), array($text['size'], $text['weight'], $text['italic'], $text['font'], $text['opacity']), 'the text\'s look');
	pg_assert_same(array(100 + 20 - 40, 40, 50), array($text['x'], $text['w'], $text['h']), 'refitted, its right side kept');

	pg_assert_same('#123456', ws_canvas_edit_apply($doc, array(array('op' => 'style', 'layer' => 'star', 'color' => '#123456')))['doc']['layers'][2]['color'], 'an icon\'s colour');
	pg_assert_same('style', ws_canvas_edit_apply($doc, array(array('op' => 'style', 'layer' => 'l3', 'radius' => 4)))['error'], 'an ellipse has no corners');

	$order = function ($ops) use ($doc) {
		return array_column(ws_canvas_edit_apply($doc, $ops)['doc']['layers'], 'id');
	};
	pg_assert_same(array('l1', 'l0', 'l2', 'l3'), $order(array(array('op' => 'order', 'layer' => 'greeting', 'to' => 'back'))), 'to the back');
	pg_assert_same(array('l0', 'l2', 'l3', 'l1'), $order(array(array('op' => 'order', 'layer' => 'l1', 'to' => 'front'))), 'to the front');
	pg_assert_same(array('l0', 'l2', 'l3', 'l1'), $order(array(array('op' => 'order', 'layer' => 'l1', 'to' => 'above', 'target' => 'l3'))), 'above another');
	pg_assert_same(array('l3', 'l0', 'l1', 'l2'), $order(array(array('op' => 'order', 'layer' => 'l3', 'to' => 'below', 'target' => 'l0'))), 'below another');

	$locked = ws_canvas_edit_apply($doc, array(array('op' => 'lock', 'layer' => 'star', 'locked' => true), array('op' => 'name', 'layer' => 'star', 'name' => 'gold star')));
	pg_assert_same(array('', true, 'gold star'), array($locked['error'], $locked['doc']['layers'][2]['locked'], $locked['doc']['layers'][2]['name']), 'a locked layer still takes a name');
	pg_assert_same('locked', ws_canvas_edit_apply($locked['doc'], array(array('op' => 'style', 'layer' => 'gold star', 'color' => '#000000')))['error'], 'and nothing else');

	$open = ws_canvas_edit_apply($locked['doc'], array(array('op' => 'lock', 'layer' => 'l2', 'locked' => false), array('op' => 'remove', 'layer' => 'gold star')));
	pg_assert_same(array('', array('l0', 'l1', 'l3')), array($open['error'], array_column($open['doc']['layers'], 'id')), 'unlocked, then removed by name');

	$twins = $doc;
	$twins['layers'][3]['name'] = 'greeting';
	pg_assert_same('layer', ws_canvas_edit_apply($twins, array(array('op' => 'remove', 'layer' => 'greeting')))['error'], 'a name two layers share names neither');
	pg_assert_same('name', ws_canvas_edit_apply($doc, array(array('op' => 'name', 'layer' => 'l3', 'name' => 'star')))['error'], 'a name taken');
}

function test_workspace_canvas_write_fonts_and_rows()
{
	$fonts = ws_canvas_write_fonts();
	pg_assert_same(array('sans', 'system'), array($fonts[0]['font'], $fonts[0]['source']), 'the system stacks first');
	pg_assert_true(in_array('g:Inter', array_column($fonts, 'font'), true), 'the Google catalogue');
	pg_assert_same(array('sans', 'g:Inter', 'g:Inter', null), array(ws_canvas_write_font('sans', array('inter' => 'Inter')), ws_canvas_write_font('Inter', array('inter' => 'Inter')), ws_canvas_write_font('g:inter', array('inter' => 'Inter')), ws_canvas_write_font('Nowhere', array())), 'font values');

	$routes = array_column(ws_canvas_api_routes(), null, 'id');
	pg_assert_same(array('GET', '/workspace/canvas/fonts', 'workspace:read'), array($routes['workspace.canvas.fonts']['method'], $routes['workspace.canvas.fonts']['path'], $routes['workspace.canvas.fonts']['scope']), 'the fonts route');

	$of = array_column($routes['workspace.messages.canvas.edit']['params'], null, 'name')['operations']['of'];
	foreach (array('type', 'font', 'shape', 'icon', 'file_id', 'name', 'lock', 'locked', 'to', 'target', 'x1') as $field) {
		pg_assert_true(isset($of[$field]), 'an operation takes ' . $field);
	}

	pg_assert_same(array('add', 'replace', 'remove', 'text', 'box', 'svg', 'style', 'order', 'name', 'lock'), ws_canvas_edit_ops(), 'the operations');

	$tools = ws_canvas_ai_tools(function ($name, $description) {
		return array('name' => $name, 'description' => $description);
	});
	foreach (array('style (', 'order (', 'name (', 'lock (', 'type text', 'icon (') as $word) {
		pg_assert_true(strpos($tools[2]['description'], $word) !== false, 'edit_canvas names ' . $word);
	}
}
