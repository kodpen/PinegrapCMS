<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an agent's SVG taken apart into layers
 * (includes/workspace/canvas_split.php): the layers of the fixture against
 * the boxes the image editor's svgLayers() measured in a browser, the
 * ported matrices against the model run under Node, the measures of each
 * element, the document a split makes on create and in an edit's add and
 * svg, and the routes' rows.
 *
 * The fixture tests/fixtures/canvas_split/chart.json holds, for chart.svg
 * after pg_svg_sanitize(), each layer's kind, the element's transform, its
 * own box (vb) and its box on the artboard as the editor gave them.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_ai.php');

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

function workspace_canvas_split_fixture()
{
	$dir = dirname(__FILE__) . '/fixtures/canvas_split/';

	return array(file_get_contents($dir . 'chart.svg'), json_decode(file_get_contents($dir . 'chart.json'), true));
}

function workspace_canvas_split_near($a, $b, $digits = 3)
{
	$round = function ($v) use ($digits) {
		return is_array($v) ? array_map(function ($n) use ($digits) { return is_bool($n) ? $n : round($n, $digits); }, $v) : round($v, $digits);
	};

	return $round($a) == $round($b);
}

// Numbers as floats without a negative zero, so JSON's 1 equals PHP's 1.0.
function workspace_canvas_split_floats($value)
{
	if (is_array($value)) {
		return array_map('workspace_canvas_split_floats', $value);
	}

	return (is_int($value) || is_float($value)) ? (float) $value + 0.0 : $value;
}

// Runs a script with the model as M; its JSON, null without Node.
function workspace_canvas_split_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcanvassplit');

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

// The fixture's layers as the editor's svgLayers() measured them.
function test_workspace_canvas_split_matches_editor_fixture()
{
	list($svg, $fixture) = workspace_canvas_split_fixture();
	$split = ws_canvas_split_layers(pg_svg_sanitize($svg));

	pg_assert_true(is_array($split), 'the fixture splits');
	pg_assert_same(array($fixture['w'], $fixture['h']), array($split['w'], $split['h']), 'the artboard');
	pg_assert_same(count($fixture['layers']), count($split['layers']), 'a layer per element');

	foreach ($fixture['layers'] as $i => $want) {
		$got = $split['layers'][$i];
		$box = array('x' => $got['x'], 'y' => $got['y'], 'w' => $got['w'], 'h' => $got['h'], 'rot' => $got['rot']);

		pg_assert_same($want['kind'], $got['kind'], 'layer ' . $i . ' kind');
		pg_assert_true(workspace_canvas_split_near($want['vb'], $got['vb']), 'layer ' . $i . ' own box ' . json_encode($got['vb']));
		pg_assert_true(workspace_canvas_split_near($want['box'], $box), 'layer ' . $i . ' box ' . json_encode($box));
		pg_assert_same(!empty($want['locked']), !empty($got['locked']), 'layer ' . $i . ' locked');
	}
}

// The model places the fixture's own boxes where the fixture says, and the
// ported matrices give what the model gives.
function test_workspace_canvas_split_port_matches_model()
{
	list($svg, $fixture) = workspace_canvas_split_fixture();
	$transforms = array('', 'translate(5 -5) scale(1.5)', 'rotate(10 130 115)', 'rotate(-30) scale(-1 2)', 'skewX(20)', 'matrix(1 2 3 4 5 6) translate(1,2)');
	$layers = array(
		array('x' => 10, 'y' => 20, 'w' => 100, 'h' => 50, 'rot' => 30, 'vb' => array('x' => 1, 'y' => 2, 'w' => 10, 'h' => 5)),
		array('x' => 0, 'y' => 0, 'w' => 8, 'h' => 4, 'rot' => -90, 'mirror' => true, 'vb' => array('x' => 0, 'y' => 0, 'w' => 2, 'h' => 2)),
	);
	$vb = array('x' => 3, 'y' => 4, 'w' => 20, 'h' => 0.2);
	$php = array('transforms' => array(), 'places' => array(), 'matrices' => array(), 'own' => ws_canvas_split_own_box($vb));

	foreach ($transforms as $t) {
		$m = ws_canvas_split_transform($t);
		$php['transforms'][] = array_map(function ($n) { return round($n, 6); }, $m);
		$place = ws_canvas_split_place($vb, $m);
		$php['places'][] = $place ? array_map(function ($n) { return is_bool($n) ? $n : round($n, 6); }, $place) : null;
	}

	foreach ($layers as $l) {
		$php['matrices'][] = array_map(function ($n) { return round($n, 6); }, ws_canvas_split_layer_matrix($l));
	}

	$fixture_places = array();

	foreach ($fixture['layers'] as $l) {
		$fixture_places[] = array($l['vb'], $l['transform']);
	}

	$node = workspace_canvas_split_node('var r = function (n) { return (typeof n === "number") ? Math.round(n * 1e6) / 1e6 : n; };' . "\n"
		. 'var ts = ' . json_encode($transforms) . ', ls = ' . json_encode($layers) . ', vb = ' . json_encode($vb) . ', fx = ' . json_encode($fixture_places) . ';' . "\n"
		. 'var root = M.svgRoot("600", "400", "0 0 300 200"), out = { transforms: [], places: [], matrices: [], own: M.svgOwnBox(vb), fixture: [] };' . "\n"
		. 'ts.forEach(function (t) { var m = M.svgTransform(t), p = M.svgPlace(vb, m); out.transforms.push(m.map(r));'
		. ' if (p) { Object.keys(p).forEach(function (k) { p[k] = r(p[k]); }); } out.places.push(p); });' . "\n"
		. 'ls.forEach(function (l) { out.matrices.push(M.svgMatrix(l).map(r)); });' . "\n"
		. 'fx.forEach(function (f) { out.fixture.push(M.svgPlace(f[0], M.mul(root.m, M.svgTransform(f[1])))); });' . "\n"
		. 'console.log(JSON.stringify(out));');

	if (!is_array($node)) {
		return;
	}

	pg_assert_same(workspace_canvas_split_floats($node['transforms']), workspace_canvas_split_floats($php['transforms']), 'transforms');
	pg_assert_same(workspace_canvas_split_floats($node['places']), workspace_canvas_split_floats($php['places']), 'places');
	pg_assert_same(workspace_canvas_split_floats($node['matrices']), workspace_canvas_split_floats($php['matrices']), 'layer matrices');
	pg_assert_true(workspace_canvas_split_near($node['own'], $php['own']), 'a thin own box');

	foreach ($fixture['layers'] as $i => $l) {
		$place = $node['fixture'][$i];

		// A skewed element is kept turned inside a group: placed by the
		// root alone.
		if ($place === null) {
			pg_assert_same('skewX(20)', $l['transform'], 'only the skewed element has no place');
			continue;
		}

		unset($place['mirror']);
		pg_assert_true(workspace_canvas_split_near($l['box'], $place), 'the model places fixture layer ' . $i);
	}
}

// Each element's own box, as getBBox() measures its geometry.
function test_workspace_canvas_split_measures()
{
	$near = function ($want, $got, $label) {
		pg_assert_true(is_array($got) && workspace_canvas_split_near($want, $got), $label . ': ' . json_encode($got));
	};

	$near(array(0, 0, 10, 10), ws_canvas_split_path_box('M0 0 H10 V10 Z'), 'lines');
	$near(array(0, 0, 10, 10), ws_canvas_split_path_box('m0 0 l10 0 0 10 -10 0z'), 'relative lines after a move');
	$near(array(0, 0, 20, 5), ws_canvas_split_path_box('M0 0 Q10 10 20 0'), 'a quadratic bulges half way');
	$near(array(0, -5, 40, 5), ws_canvas_split_path_box('M0 0 Q10 10 20 0 T40 0'), 'a smooth quadratic mirrors its control');
	$near(array(0, 0, 30, 7.5), ws_canvas_split_path_box('M0 0 C0 10 30 10 30 0'), 'a cubic to its turning point');
	$near(array(0, -10, 20, 0), ws_canvas_split_path_box('M0 0 A10 10 0 0 1 20 0'), 'a half circle swept up');
	$near(array(0, 0, 20, 10), ws_canvas_split_path_box('M0 0 A10 10 0 0 0 20 0'), 'and down');
	$near(array(0, -10, 20, 0), ws_canvas_split_path_box('M0 0a10 10 0 0120 0'), 'flags written together');
	$near(array(0, 0, 20, 10), ws_canvas_split_path_box('M0 0 A1 1 0 0 0 20 0'), 'radii too small grow');
	pg_assert_same(null, ws_canvas_split_path_box('10 10'), 'no command, no drawing');

	$doc = new DOMDocument();
	$doc->loadXML('<svg xmlns="http://www.w3.org/2000/svg" font-size="20"><text x="100" y="50" text-anchor="middle">abcd</text>'
		. '<text x="0" y="10" font-size="10">ab<tspan x="50" y="30">cd</tspan></text><g style="display:none"><rect width="5" height="5"/></g>'
		. '<g><rect x="1" y="1" width="2" height="2" transform="scale(2)"/><circle cx="20" cy="20" r="5"/></g></svg>');
	$nodes = array();

	foreach ($doc->documentElement->childNodes as $n) {
		$nodes[] = $n;
	}

	$near(array(80, 50 - 18.1, 120, 50 + 4.24), ws_canvas_split_bbox($nodes[0], array()), 'a centred text inherits its size');
	$near(array(0, 10 - 9.05, 60, 30 + 2.12), ws_canvas_split_bbox($nodes[1], array()), 'a tspan placed on its own');
	pg_assert_same(null, ws_canvas_split_bbox($nodes[2], array()), 'a hidden group draws nothing');
	$near(array(2, 2, 25, 25), ws_canvas_split_bbox($nodes[3], array()), 'a group joins its children through their transforms');
}

// One SVG as a layer per element on a new canvas.
function test_workspace_canvas_split_create()
{
	list($svg) = workspace_canvas_split_fixture();
	$made = ws_canvas_from_svg($svg, '', true);
	$doc = json_decode(json_encode($made['doc']), true);

	pg_assert_same('', $made['error'], 'made');
	pg_assert_same(12, count($doc['layers']), 'a layer per element');
	pg_assert_same(array('l0', 'l1', 'l2'), array_slice(array_column($doc['layers'], 'id'), 0, 3), 'ids in order');
	pg_assert_same('background', $doc['layers'][0]['name'], 'named by its id');
	pg_assert_true(!isset($doc['layers'][1]['name']), 'no id, no name');
	pg_assert_same('bar-1', $doc['layers'][2]['name'], 'the sanitizer\'s prefix is not in the name');
	pg_assert_same(array(600, 400), array($doc['artboard']['width'], $doc['artboard']['height']), 'the artboard');
	pg_assert_same('pending', $doc['picture'], 'pending');
	pg_assert_true(strpos($doc['svgDefs'], 'linearGradient') !== false && strpos($doc['svgDefs'], '<style') !== false, 'gradient and style rules kept for the layers');
	pg_assert_same(1, preg_match('/^i1-pgsvg-/', $doc['svgRootId']), 'the scope of the style rules');

	preg_match('/fill="url\(#([^)]+)\)"/', $doc['layers'][2]['markup'], $ref);
	pg_assert_true(isset($ref[1]) && (strpos($doc['svgDefs'], 'id="' . $ref[1] . '"') !== false), 'a layer\'s gradient is in the definitions');
	pg_assert_same('#334455', preg_match('/fill="(#334455)"/', $doc['layers'][1]['markup'], $m) ? $m[1] : '', 'the root\'s paint on each element');
	pg_assert_same(array('kept', true), array($doc['layers'][10]['kind'], $doc['layers'][10]['locked']), 'a use is kept, locked');
	pg_assert_same('g', $doc['layers'][9]['kind'], 'a group stays one layer');
	pg_assert_same(0, strpos($doc['layers'][11]['markup'], '<g'), 'a skewed element kept turned in a group');

	$one = json_decode(json_encode(ws_canvas_from_svg($svg)['doc']), true);
	pg_assert_same(1, count($one['layers']), 'without the word, one layer');

	$read = ws_canvas_read($doc, array(array('version_no' => 1, 'source' => 'agent', 'user_id' => 1, 'created_at' => 1, 'doc' => $doc)));
	pg_assert_same(array('l0', 'rect', 'background', 0, 0, 600, 400), array($read['layers'][0]['id'], $read['layers'][0]['kind'], $read['layers'][0]['name'], $read['layers'][0]['x'], $read['layers'][0]['y'], $read['layers'][0]['w'], $read['layers'][0]['h']), 'the read lists id, kind, name and box');

	pg_assert_same('empty', ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="a"/></defs></svg>', '', true)['error'], 'definitions alone draw nothing');

	$motion = ws_canvas_split_layers(pg_svg_sanitize('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><g class="pgm-1" transform="translate(10 0)"><text id="t" x="0" y="20">Hi</text></g></svg>'));
	pg_assert_same(array('text', 't', 'Hi'), array($motion['layers'][0]['type'], $motion['layers'][0]['name'], $motion['layers'][0]['text']), 'a saved motion group opens as its element, a text layer');
}

// An edit's add and svg with layers split.
function test_workspace_canvas_split_edit()
{
	$base = json_decode(json_encode(ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200"><style>.a{fill:red}</style><rect class="a" id="r" width="10" height="10"/></svg>', '', true)['doc']), true);
	$small = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="20"><style>.b{fill:blue}</style><rect class="b" id="r" width="40" height="20"/><circle cx="10" cy="10" r="5"/></svg>';

	$edited = ws_canvas_edit_apply($base, array(array('op' => 'add', 'svg' => $small, 'layers' => 'split')));
	$doc = $edited['doc'];

	pg_assert_same('', $edited['error'], 'added');
	pg_assert_same(array('l0', 'l1', 'l2'), array_column($doc['layers'], 'id'), 'new layers on top');
	pg_assert_same(array(180, 90, 40, 20), array($doc['layers'][1]['x'], $doc['layers'][1]['y'], $doc['layers'][1]['w'], $doc['layers'][1]['h']), 'centred where the one layer would go');
	$plain = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="20"><rect id="r" width="40" height="20"/></svg>';
	$twice = ws_canvas_edit_apply(json_decode(json_encode(ws_canvas_from_svg($plain, '', true)['doc']), true), array(array('op' => 'add', 'svg' => $plain, 'layers' => 'split')))['doc'];
	pg_assert_same(array(1, 1), array(preg_match('/id="i1-r"/', $twice['layers'][0]['markup']), preg_match('/id="i2-r"/', $twice['layers'][1]['markup'])), 'ids kept unique');
	pg_assert_same('r', $twice['layers'][1]['name'], 'the name is the id as written');
	pg_assert_same(1, substr_count($doc['svgDefs'], '<defs'), 'one definitions block');
	pg_assert_true(strpos($doc['svgDefs'], 'blue') !== false && strpos($doc['svgDefs'], 'red') !== false, 'both style sheets');
	pg_assert_same(false, strpos($doc['svgDefs'], 'id="i2-pgsvg'), 'the new scope renamed to the document\'s');

	$placed = ws_canvas_edit_apply($base, array(array('op' => 'add', 'svg' => $small, 'layers' => 'split', 'x' => 0, 'y' => 0, 'w' => 80, 'h' => 40)))['doc'];
	pg_assert_same(array(0, 0, 80, 40), array($placed['layers'][1]['x'], $placed['layers'][1]['y'], $placed['layers'][1]['w'], $placed['layers'][1]['h']), 'a box given places the whole');
	pg_assert_same(array(10, 10, 20, 20), array($placed['layers'][2]['x'], $placed['layers'][2]['y'], $placed['layers'][2]['w'], $placed['layers'][2]['h']), 'its parts scaled with it');

	$anew = ws_canvas_edit_apply($base, array(array('op' => 'svg', 'svg' => $small, 'layers' => 'split')))['doc'];
	pg_assert_same(array(2, 40, 20), array(count($anew['layers']), $anew['artboard']['width'], $anew['artboard']['height']), 'the whole canvas anew, split');
	pg_assert_true(strpos($anew['svgDefs'], 'red') === false, 'the old definitions gone');

	$refused = ws_canvas_edit_apply($base, array(array('op' => 'add', 'svg' => $small, 'layers' => 'pieces')));
	pg_assert_same(array('layers', 0), array($refused['error'], $refused['index']), 'an unknown word is refused');
	pg_assert_same('box', ws_canvas_edit_apply($base, array(array('op' => 'add', 'svg' => $small, 'layers' => 'split', 'w' => 0)))['error'], 'a bad box is refused');
	pg_assert_same(2, count(ws_canvas_edit_apply($base, array(array('op' => 'add', 'svg' => $small)))['doc']['layers']), 'add without the word is one layer');
}

// The word is in the routes, the operations and the assistants' tool.
function test_workspace_canvas_split_routes()
{
	$routes = array();

	foreach (ws_canvas_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$create = array_column($routes['workspace.channels.canvases.create']['params'], null, 'name');
	pg_assert_same(array('enum', array('split', 'one')), array($create['layers']['type'], $create['layers']['values']), 'create takes layers');

	$edit = array_column($routes['workspace.messages.canvas.edit']['params'], null, 'name');
	pg_assert_same('string', $edit['operations']['of']['layers'], 'an operation takes layers');
	pg_assert_same('string?', ws_api_canvas_returns()['layers'][0]['name'], 'the read names a layer');

	$tools = ws_canvas_ai_tools(function ($name, $description, $properties) {
		return array('name' => $name, 'properties' => $properties);
	});
	pg_assert_same(array('split', 'one'), $tools[0]['properties']['layers']['enum'], 'draw_canvas takes layers');
	pg_assert_same(array('split', 'one', null), array(ws_canvas_split_mode('split'), ws_canvas_split_mode(null), ws_canvas_split_mode('x')), 'the words');
}
