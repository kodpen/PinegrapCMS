<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a canvas made from an agent's SVG (includes/workspace/canvas_svg.php
 * and api_canvas.php): the port of the image editor's Import against the
 * model run under Node, the document it builds, its refusals, the blank
 * picture, the canvas as an agent reads it and the routes' rows.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_svg.php');

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

function workspace_agent_canvas_svg()
{
	return '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200" viewBox="0 0 200 100" fill="#123456">'
		. '<title>Plan</title>'
		. '<defs><linearGradient id="g"><stop offset="0" stop-color="#ff0000"/></linearGradient></defs>'
		. '<rect id="box" x="10" y="10" width="50" height="20" fill="url(#g)"/>'
		. '<use href="#box" x="60"/>'
		. '</svg>';
}

// The sizes, view box and ids as the editor's model gives them.
function test_workspace_agent_canvas_port_matches_model()
{
	$cases = array(
		array('400', '200', '0 0 200 100'),
		array('', '', '0 0 50 20'),
		array('10cm', '', '-5 -5 100 50'),
		array('', '120', ''),
		array('', '', ''),
		array('99999', '3', ''),
		array('50%', '2in', '0 0 30 90'),
		array('1e2', '33.3', '0,0,7,3'),
	);
	$ids = array(array('svg', 'a', 'b', 'a'), array('x'));
	$taken = array(array('i1-a'), array('i1-x', 'i2-x', 'i4-x'));

	$php = array('roots' => array(), 'texts' => array(), 'maps' => array());

	foreach ($cases as $case) {
		$root = ws_canvas_svg_root($case[0], $case[1], $case[2]);
		$php['roots'][] = array($root['w'], $root['h']);
		$php['texts'][] = ws_canvas_svg_matrix_text($root['m']);
	}

	foreach ($ids as $i => $list) {
		$php['maps'][] = ws_canvas_svg_id_map($list, $taken[$i]);
	}

	$php['value'] = ws_canvas_svg_rename_value(' #a ', array('a' => 'i1-a'));
	$php['url'] = ws_canvas_svg_rename_value('url(#a) url("#b") url(#c)', array('a' => 'i1-a', 'b' => 'i1-b'));
	$php['css'] = ws_canvas_svg_rename_css('#svg .x, #a{fill:#fff;mask:url(#a)} #b > i{stroke:url(\'#b\')}', array('svg' => 'i1-svg', 'a' => 'i1-a', 'b' => 'i1-b'));

	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgagentcanvas');

	if ($file === false) {
		return;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		. 'var cases = ' . json_encode($cases) . ', ids = ' . json_encode($ids) . ', taken = ' . json_encode($taken) . ';' . "\n"
		. 'var out = { roots: [], texts: [], maps: [] };' . "\n"
		. 'cases.forEach(function (c) { var r = M.svgRoot(c[0], c[1], c[2]); out.roots.push([r.w, r.h]); out.texts.push(M.svgMatrixText(r.m)); });' . "\n"
		. 'ids.forEach(function (l, i) { out.maps.push(M.svgIdMap(l, taken[i])); });' . "\n"
		. 'out.value = M.svgRenameValue(" #a ", { a: "i1-a" });' . "\n"
		. 'out.url = M.svgRenameValue(\'url(#a) url("#b") url(#c)\', { a: "i1-a", b: "i1-b" });' . "\n"
		. 'out.css = M.svgRenameCss(' . json_encode('#svg .x, #a{fill:#fff;mask:url(#a)} #b > i{stroke:url(\'#b\')}') . ', { svg: "i1-svg", a: "i1-a", b: "i1-b" });' . "\n"
		. 'console.log(JSON.stringify(out));');

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$node = ($code === 0) ? json_decode(implode("\n", $out), true) : null;

	pg_assert_true(is_array($node), ($code === 0) ? 'node ran' : implode("\n", $out));

	if (!is_array($node)) {
		return;
	}

	pg_assert_same($node['roots'], $php['roots'], 'artboard sizes');
	pg_assert_same($node['texts'], $php['texts'], 'view box matrices');
	pg_assert_same($node['maps'], $php['maps'], 'id maps');
	pg_assert_same($node['value'], $php['value'], 'a whole reference');
	pg_assert_same($node['url'], $php['url'], 'url references');
	pg_assert_same($node['css'], $php['css'], 'a style sheet');
}

// An agent's SVG as one imported layer covering an artboard of its size.
function test_workspace_agent_canvas_from_svg()
{
	$made = ws_canvas_from_svg(workspace_agent_canvas_svg());

	pg_assert_same('', $made['error'], 'made');

	$doc = json_decode(json_encode($made['doc']), true);

	pg_assert_true(ws_canvas_is_doc($made['doc']), 'a canvas document');
	pg_assert_same(array('width' => 400, 'height' => 200, 'background' => '#ffffff'), $doc['artboard'], 'the artboard of the root\'s size');
	pg_assert_same('pending', $doc['picture'], 'its picture waits for a browser');
	pg_assert_true(is_object($made['doc']->adjust), 'adjust stays an object');
	pg_assert_same(1, count($doc['layers']), 'one layer');

	$layer = $doc['layers'][0];

	pg_assert_same(array('l0', 'svg', 'g', true), array($layer['id'], $layer['type'], $layer['kind'], $layer['imported']), 'an imported svg layer');
	pg_assert_same(array(0, 0, 400, 200, 0), array($layer['x'], $layer['y'], $layer['w'], $layer['h'], $layer['rot']), 'covering the artboard');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 400, 'h' => 200), $layer['vb'], 'its view box');
	pg_assert_true(strpos($layer['markup'], '<g xmlns="http://www.w3.org/2000/svg" id="i1-svg"><g transform="matrix(2 0 0 2 0 0)" fill="#123456">') === 0, 'the groups: ' . substr($layer['markup'], 0, 120));
	pg_assert_true(strpos($layer['markup'], 'id="i1-g"') !== false, 'ids renamed');
	pg_assert_true(strpos($layer['markup'], 'url(#i1-g)') !== false, 'url references follow');
	pg_assert_true(strpos($layer['markup'], 'href="#i1-box"') !== false, 'href references follow');
	pg_assert_true(strpos($layer['markup'], '<title') === false, 'no title');
	pg_assert_true(strpos($layer['markup'], '<linearGradient') !== false, 'definitions kept in the layer');

	$dark = ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg" width="20000" height="5"><circle r="3"/></svg>', '#ABCDEF');
	pg_assert_same(array('width' => 8000, 'height' => 16, 'background' => '#abcdef'), json_decode(json_encode($dark['doc']->artboard), true), 'sides kept to the editor\'s limits, the fill lower case');
	pg_assert_same('transparent', ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg"><circle r="3"/></svg>', 'transparent')['doc']->artboard->background, 'transparent');
}

function test_workspace_agent_canvas_refusals()
{
	pg_assert_same('empty', ws_canvas_from_svg('')['error'], 'nothing');
	pg_assert_same('empty', ws_canvas_from_svg(array('x'))['error'], 'no text');
	pg_assert_same('empty', ws_canvas_from_svg('<html/>')['error'], 'no SVG');
	pg_assert_same('empty', ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><title>x</title></svg>')['error'], 'nothing left to draw');
	pg_assert_same('empty', ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="a"/></defs></svg>')['error'], 'definitions only');
	pg_assert_same('too_large', ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg"><desc>' . str_repeat('x', 1048576) . '</desc><circle r="3"/></svg>')['error'], 'over 1 MB');
	pg_assert_same('background', ws_canvas_from_svg(workspace_agent_canvas_svg(), 'red')['error'], 'a fill by name');
	pg_assert_same('not_kept', ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/png;base64,iVBORw0KGgo=" width="5" height="5"/></svg>')['error'] ?: 'kept', 'a picture as data');

	foreach (array('too_large', 'background', 'not_kept', 'empty') as $error) {
		pg_assert_true(ws_canvas_from_svg_error_text($error) !== '', $error . ' has words');
	}
}

// The blank picture: a PNG of the artboard's size in its fill.
function test_workspace_agent_canvas_blank_png()
{
	$png = ws_canvas_blank_png(400, 200, '#336699');
	$size = getimagesizefromstring($png);

	pg_assert_same(array(400, 200, IMAGETYPE_PNG), array($size[0], $size[1], $size[2]), 'its size');

	$big = ws_canvas_blank_png(8000, 8000, 'transparent');
	pg_assert_true(strlen($big) < 200000, 'the largest artboard stays small: ' . strlen($big));
	pg_assert_same(array(8000, 8000), array_slice(getimagesizefromstring($big), 0, 2), 'the largest artboard');

	if (function_exists('imagecreatefromstring')) {
		$image = imagecreatefromstring($png);
		$rgb = imagecolorsforindex($image, imagecolorat($image, 399, 199));
		pg_assert_same(array(0x33, 0x66, 0x99), array($rgb['red'], $rgb['green'], $rgb['blue']), 'its fill');

		$clear = imagecreatefromstring(ws_canvas_blank_png(16, 16, 'transparent'));
		pg_assert_same(127, imagecolorsforindex($clear, imagecolorat($clear, 3, 3))['alpha'], 'transparent');
	}
}

// The canvas as an agent reads it: the layers with their boxes, a text
// layer's words, an svg layer's markup, who made each version.
function test_workspace_agent_canvas_read()
{
	$doc = json_decode(json_encode(ws_canvas_from_svg(workspace_agent_canvas_svg())['doc']), true);
	$doc['layers'][] = array('id' => 'l1', 'type' => 'text', 'text' => 'Hello', 'color' => '#ff0000', 'x' => 5.5, 'y' => '7', 'w' => 100, 'h' => 20, 'rot' => 15, 'locked' => true);
	$doc['layers'][] = array('id' => 'l2', 'type' => 'rect', 'x' => 1, 'y' => 2, 'w' => 3, 'h' => 4, 'text' => 'not a text layer');
	$doc['layers'][] = 'broken';

	$versions = array(
		array('version_no' => 1, 'source' => 'agent', 'user_id' => 4, 'created_at' => 100, 'doc' => array('by' => array('id' => 7, 'kind' => 'app', 'handle' => 'drawer'))),
		array('version_no' => 2, 'source' => 'saved', 'user_id' => 9, 'created_at' => 200, 'doc' => array('by' => array('id' => 7, 'kind' => 'app', 'handle' => 'drawer'))),
		array('version_no' => 3, 'source' => 'agent', 'user_id' => 0, 'created_at' => 300, 'doc' => null),
	);

	$read = ws_canvas_read($doc, $versions);

	pg_assert_same(array('width' => 400, 'height' => 200, 'background' => '#ffffff'), $read['artboard'], 'the artboard');
	pg_assert_same('pending', $read['picture'], 'not drawn yet');
	pg_assert_same(3, $read['version'], 'the newest version');
	pg_assert_same(3, count($read['layers']), 'a broken layer left out');
	pg_assert_same('svg', $read['layers'][0]['type'], 'the drawing first');
	pg_assert_same($doc['layers'][0]['markup'], $read['layers'][0]['markup'], 'its markup');
	pg_assert_same(null, $read['layers'][0]['text'], 'no text on an svg layer');
	pg_assert_same(array('l1', 'text', null, null, 5.5, 7, 100, 20, 15, true, 'Hello', '#ff0000', null), array_values($read['layers'][1]), 'a text layer');
	pg_assert_same(array(null, null, null), array($read['layers'][2]['text'], $read['layers'][2]['color'], $read['layers'][2]['markup']), 'a shape has no text or markup');
	pg_assert_same(array('version' => 1, 'source' => 'agent', 'user_id' => 4, 'agent_id' => 7, 'agent' => 'drawer', 'created_at' => 100), $read['versions'][0], 'an agent\'s version');
	pg_assert_same(array(9, null, null), array($read['versions'][1]['user_id'], $read['versions'][1]['agent_id'], $read['versions'][1]['agent']), 'a person\'s save carries the agent\'s by along, and is still theirs');
	pg_assert_same(array(null, null), array($read['versions'][2]['user_id'], $read['versions'][2]['agent']), 'nobody named');

	unset($doc['picture']);
	pg_assert_same('drawn', ws_canvas_read($doc, array())['picture'], 'drawn');
	pg_assert_same(0, ws_canvas_read($doc, array())['version'], 'no versions');
	pg_assert_same(null, ws_canvas_doc_agent(array('by' => array('id' => 3))), 'no handle, no agent');
}

// The routes: an agent's write that rehearses and answers 201, a read, both
// with every field the answer carries declared.
function test_workspace_agent_canvas_routes()
{
	$routes = array();

	foreach (ws_canvas_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$create = $routes['workspace.channels.canvases.create'];
	pg_assert_same(array('POST', '/workspace/channels/{id}/canvases', 'workspace:write', true, 201), array($create['method'], $create['path'], $create['scope'], $create['dry_run'], $create['status']), 'create');

	$get = $routes['workspace.messages.canvas.get'];
	pg_assert_same(array('GET', '/workspace/messages/{id}/canvas', 'workspace:read'), array($get['method'], $get['path'], $get['scope']), 'read');

	$read = ws_canvas_read(json_decode(json_encode(ws_canvas_from_svg(workspace_agent_canvas_svg())['doc']), true),
		array(array('version_no' => 1, 'source' => 'agent', 'user_id' => 4, 'created_at' => 100, 'doc' => null)));
	$declared = ws_api_canvas_returns();
	$present = array('message_id' => 1, 'file_id' => 2, 'name' => '', 'url' => '', 'api_url' => '') + $read;

	pg_assert_same(array_keys($declared), array_keys($present), 'the canvas\'s fields declared');
	pg_assert_same(array_keys($declared['layers'][0]), array_keys($read['layers'][0]), 'a layer\'s fields declared');
	pg_assert_same(array_keys($declared['versions'][0]), array_keys($read['versions'][0]), 'a version\'s fields declared');
	pg_assert_same(array_keys($declared['artboard']), array_keys($read['artboard']), 'the artboard\'s fields declared');
	pg_assert_true(in_array('workspace.channels.canvases.create', array_column(ws_api_routes(), 'id'), true), 'merged into the workspace routes');
}

function workspace_agent_canvas_edit_doc()
{
	$doc = json_decode(json_encode(ws_canvas_from_svg(workspace_agent_canvas_svg())['doc']), true);
	$doc['layers'][] = array('id' => 'l1', 'type' => 'text', 'text' => 'Hello', 'color' => '#000000', 'x' => 10, 'y' => 10, 'w' => 100, 'h' => 20, 'rot' => 0);
	$doc['layers'][] = array('id' => 'l2', 'type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 5, 'h' => 5, 'locked' => true);
	unset($doc['picture']);

	return $doc;
}

// Each operation on its own, in order, all or none.
function test_workspace_agent_canvas_edit_operations()
{
	$doc = workspace_agent_canvas_edit_doc();
	$small = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50" id="svg"><circle id="g" r="5" fill="url(#g)"/></svg>';

	$added = ws_canvas_edit_apply($doc, array(array('op' => 'add', 'svg' => $small)));
	pg_assert_same('', $added['error'], 'added');
	$layer = end($added['doc']['layers']);
	pg_assert_same(array('l3', 'svg', 'g', true), array($layer['id'], $layer['type'], $layer['kind'], $layer['imported']), 'a new imported layer on top');
	pg_assert_same(array(150, 75, 100, 50), array($layer['x'] + 0, $layer['y'] + 0, $layer['w'] + 0, $layer['h'] + 0), 'its own size, centred');
	pg_assert_true(strpos($layer['markup'], 'id="i2-svg"') !== false && strpos($layer['markup'], 'url(#i2-g)') !== false, 'ids clear of the canvas\'s own i1- ids');
	pg_assert_same('pending', $added['doc']['picture'], 'the picture pending again');

	$big = ws_canvas_edit_apply($doc, array(array('op' => 'add', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="100"><rect width="9" height="9"/></svg>', 'x' => 1)));
	$layer = end($big['doc']['layers']);
	pg_assert_same(array(1, 75, 400, 50), array($layer['x'] + 0, $layer['y'] + 0, $layer['w'] + 0, $layer['h'] + 0), 'made smaller to fit, a given x kept');

	$replaced = ws_canvas_edit_apply($doc, array(array('op' => 'replace', 'layer' => 'l0', 'svg' => $small)));
	pg_assert_same(array(0, 0, 400, 200), array($replaced['doc']['layers'][0]['x'], $replaced['doc']['layers'][0]['y'], $replaced['doc']['layers'][0]['w'], $replaced['doc']['layers'][0]['h']), 'replaced in its box');
	pg_assert_same(array('x' => 0, 'y' => 0, 'w' => 100, 'h' => 50), $replaced['doc']['layers'][0]['vb'], 'the new drawing\'s view box');
	pg_assert_true(strpos($replaced['doc']['layers'][0]['markup'], 'id="i1-svg"') !== false, 'its own old ids free to take again');

	$texted = ws_canvas_edit_apply($doc, array(array('op' => 'text', 'layer' => 'l1', 'text' => 'Bye', 'color' => '#FF0000'), array('op' => 'box', 'layer' => 'l1', 'x' => 40, 'rot' => 12.5)));
	pg_assert_same(array('Bye', '#ff0000', 40, 10, 12.5), array($texted['doc']['layers'][1]['text'], $texted['doc']['layers'][1]['color'], $texted['doc']['layers'][1]['x'], $texted['doc']['layers'][1]['y'], $texted['doc']['layers'][1]['rot']), 'text, then its box');

	$removed = ws_canvas_edit_apply($doc, array(array('op' => 'remove', 'layer' => 'l1')));
	pg_assert_same(array('l0', 'l2'), array_column($removed['doc']['layers'], 'id'), 'removed');

	$anew = ws_canvas_edit_apply($doc + array('x' => 1), array(array('op' => 'svg', 'svg' => $small)));
	pg_assert_same(array('width' => 100, 'height' => 50, 'background' => '#ffffff'), $anew['doc']['artboard'], 'drawn anew, the background kept');
	pg_assert_same(1, count($anew['doc']['layers']), 'one layer');
	pg_assert_same('#000000', ws_canvas_edit_apply($doc, array(array('op' => 'svg', 'svg' => $small, 'background' => '#000000')))['doc']['artboard']['background'], 'a new background');
}

function test_workspace_agent_canvas_edit_refusals()
{
	$doc = workspace_agent_canvas_edit_doc();
	$cases = array(
		array(null, 'operations', -1),
		array(array(), 'operations', -1),
		array(array('op' => 'remove', 'layer' => 'l1'), 'operations', -1),
		array(array_fill(0, 21, array('op' => 'remove', 'layer' => 'l1')), 'too_many', -1),
		array(array(array('op' => 'paint')), 'op', 0),
		array(array(array('op' => 'remove', 'layer' => 'l1'), array('op' => 'remove', 'layer' => 'l1')), 'layer', 1),
		array(array(array('op' => 'box', 'layer' => 'l2', 'x' => 1)), 'locked', 0),
		array(array(array('op' => 'box', 'layer' => 'l1', 'w' => 0)), 'box', 0),
		array(array(array('op' => 'box', 'layer' => 'l1', 'x' => '5')), 'box', 0),
		array(array(array('op' => 'box', 'layer' => 'l1', 'y' => 200000)), 'box', 0),
		array(array(array('op' => 'text', 'layer' => 'l0', 'text' => 'x')), 'text', 0),
		array(array(array('op' => 'text', 'layer' => 'l1', 'text' => 'x', 'color' => 'red')), 'color', 0),
		array(array(array('op' => 'replace', 'layer' => 'l1', 'svg' => workspace_agent_canvas_svg())), 'not_svg', 0),
		array(array(array('op' => 'replace', 'layer' => 'l0', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg"/>')), 'empty', 0),
		array(array(array('op' => 'add', 'svg' => str_repeat('x', 1048577))), 'too_large', 0),
		array(array(array('op' => 'svg', 'svg' => workspace_agent_canvas_svg(), 'background' => 'blue')), 'background', 0),
	);

	foreach ($cases as $i => $case) {
		$got = ws_canvas_edit_apply($doc, $case[0]);
		pg_assert_same(array($case[1], $case[2], null), array($got['error'], $got['index'], $got['doc']), 'case ' . $i);
		pg_assert_true(ws_canvas_edit_error_text($case[1]) !== '', 'case ' . $i . ' has words');
	}
}

function test_workspace_agent_canvas_edit_route()
{
	$routes = array();

	foreach (ws_canvas_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	$edit = $routes['workspace.messages.canvas.edit'];
	pg_assert_same(array('POST', array('PATCH'), '/workspace/messages/{id}/canvas', 'workspace:write', true), array($edit['method'], $edit['also_accepts'], $edit['path'], $edit['scope'], $edit['dry_run']), 'edit');
	pg_assert_same(20, ws_canvas_edit_max_ops(), 'the list\'s limit');
	pg_assert_same(ws_canvas_edit_max_ops(), $edit['params'][2]['max_items'], 'the route says the same limit');
	pg_assert_same(array('i1-a', 'svg'), ws_canvas_taken_ids(array('svgRootId' => 'svg', 'layers' => array(array('id' => 'l0', 'type' => 'svg', 'markup' => '<g id="i1-a"/>'), array('id' => 'l1', 'type' => 'svg', 'markup' => '<g id="i1-b"/>'))), 'l1'), 'taken ids, one layer left out');
}
