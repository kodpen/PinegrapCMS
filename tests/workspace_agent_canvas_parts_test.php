<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the texts and pictures of an agent's SVG as layers of their
 * own when it is taken apart (includes/workspace/canvas_split_parts.php):
 * fonts, colours, the text layer and where the editor's own layout (run
 * under Node) puts its letters, what stays an svg layer, pictures marked
 * before the sanitizer and the addresses that name a site file.
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

function workspace_canvas_parts_fonts()
{
	return array('inter' => 'Inter', 'lobster' => 'Lobster');
}

// The layers of an SVG split with a small catalogue and no pictures.
function workspace_canvas_parts_split($svg, $parts = array())
{
	return ws_canvas_split_layers(pg_svg_sanitize($svg), array(), $parts + array('fonts' => workspace_canvas_parts_fonts()));
}

function workspace_canvas_parts_svg($body, $root = '')
{
	return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="400" height="200"' . $root . '>' . $body . '</svg>';
}

function test_workspace_canvas_parts_fonts_and_colours()
{
	$fonts = workspace_canvas_parts_fonts();

	pg_assert_same('g:Inter', ws_canvas_split_font('"Inter", sans-serif', $fonts), 'a catalogue family');
	pg_assert_same('sans', ws_canvas_split_font('Nowhere, Arial', $fonts), 'the first offered family');
	pg_assert_same('serif', ws_canvas_split_font('serif', $fonts), 'a generic family');
	pg_assert_same('mono', ws_canvas_split_font("'Courier New'", $fonts), 'a system face');
	pg_assert_same('sans', ws_canvas_split_font('', $fonts), 'none given');
	pg_assert_same(null, ws_canvas_split_font('Nowhere', $fonts), 'nothing offered');
	pg_assert_true(isset(ws_canvas_split_google_fonts()['playfair display']), 'the editor\'s Google catalogue is read');

	pg_assert_same('#ffcc00', ws_canvas_split_color('#FC0'), '#rgb');
	pg_assert_same('#0a0b0c', ws_canvas_split_color('rgb(10, 11, 12)'), 'rgb()');
	pg_assert_same('#ffffff', ws_canvas_split_color('White'), 'a name');
	pg_assert_same(array(null, null, null), array(ws_canvas_split_color('url(#g)'), ws_canvas_split_color('none'), ws_canvas_split_color('rgba(0,0,0,.5)')), 'no colour');

	pg_assert_same(2.0, ws_canvas_split_uniform(array(2, 0, 0, 2, 5, 5)), 'uniform');
	pg_assert_same(null, ws_canvas_split_uniform(array(2, 0, 0, 1, 0, 0)), 'stretched');
	pg_assert_same(null, ws_canvas_split_uniform(array(-1, 0, 0, 1, 0, 0)), 'mirrored');
}

// A text element becomes the editor's text layer.
function test_workspace_canvas_parts_text_layer()
{
	$split = workspace_canvas_parts_split(workspace_canvas_parts_svg(
		'<text id="title" x="200" y="50" font-family="Inter" font-size="24" font-weight="bold" text-anchor="middle" fill="#123456">Quarterly sales</text>'
		. '<text x="10" y="150" font-size="12" font-style="italic" opacity="0.5"><tspan x="10">One</tspan><tspan x="10" dy="18">Two</tspan></text>'
		. '<text x="20" y="20" transform="rotate(90 20 20) scale(2)" font-family="Lobster" fill="red">Turned</text>',
		' fill="#000000"'));
	list($title, $lines, $turned) = $split['layers'];

	pg_assert_same(array('text', 'title', 'Quarterly sales', 'g:Inter', 24, 700, false, 'center', '#123456'), array($title['type'], $title['name'], $title['text'], $title['font'], $title['size'], $title['weight'], $title['italic'], $title['align'], $title['color']), 'a title');
	pg_assert_same(array(1.25, 0, '', 0, 1), array($title['lineHeight'], $title['spacing'], $title['bg'], $title['rot'], $title['opacity']), 'its other fields');
	pg_assert_same(200.0, (float) ($title['x'] + $title['w'] / 2), 'centred on its anchor');
	pg_assert_same(array("One\nTwo", 1.5, true, 0.5, 'left', 10), array($lines['text'], $lines['lineHeight'], $lines['italic'], $lines['opacity'], $lines['align'], $lines['x']), 'tspans one under another are lines');
	pg_assert_same(array(90, 'g:Lobster', 32, '#ff0000'), array($turned['rot'], $turned['font'], $turned['size'], $turned['color']), 'turned and scaled');

	// The editor's own layout puts the first baseline on the text's y.
	$node = workspace_canvas_parts_node('var l = ' . json_encode($title) . ', lines = ' . json_encode($lines) . ';' . "\n"
		. 'var measure = function (font, s) { var px = parseFloat(/([0-9.]+)px/.exec(font)[1]); return Array.from(s).length * px / 2; };' . "\n"
		. 'var a = M.textLayout(l, measure), b = M.textLayout(lines, measure);' . "\n"
		. 'console.log(JSON.stringify({ base: l.y + a.lines[0].base, w: a.w, h: a.h, base2: lines.y + b.lines[1].base }));');

	if (is_array($node)) {
		pg_assert_same(array(50.0, 168.0), array((float) round($node['base'], 2), (float) round($node['base2'], 2)), 'the baselines where the SVG put them');
		pg_assert_same(array(round($title['w'], 2), round($title['h'], 2)), array(round($node['w'], 2), round($node['h'], 2)), 'the box the editor would give');
	}
}

// Texts a text layer cannot hold stay svg layers.
function test_workspace_canvas_parts_text_stays_svg()
{
	$kept = array(
		'<text x="1" y="9" class="a">Styled by a rule</text><style>.a{fill:red}</style>',
		'<text x="1" y="9" stroke="#000">Outlined</text>',
		'<text x="1" y="9" fill="url(#g)">Gradient</text>',
		'<text x="1" y="9" font-family="Nowhere">Unknown font</text>',
		'<text x="1" y="9" rotate="10">Letters turned</text>',
		'<text x="1" y="9">Mixed <tspan font-weight="bold">bold</tspan></text>',
		'<text x="1" y="9" transform="scale(2 1)">Stretched</text>',
		'<text x="1" y="9" font-size="2em">Relative size</text>',
		'<text x="1 5 9" y="9">Placed letters</text>',
	);

	foreach ($kept as $body) {
		$split = workspace_canvas_parts_split(workspace_canvas_parts_svg($body));
		pg_assert_same(array('svg', 'text'), array($split['layers'][0]['type'], $split['layers'][0]['kind'] ?? ''), $body);
	}
}

// A picture naming a file the agent may use becomes a picture layer.
function test_workspace_canvas_parts_pictures()
{
	$files = array('/logo.png' => array('id' => 7, 'url' => '/logo.png', 'width' => 200, 'height' => 100));
	$resolve = function ($href) use ($files) {
		return $files[$href] ?? null;
	};
	$svg = workspace_canvas_parts_svg('<image id="logo" href="/logo.png" x="0" y="0" width="100" height="100" opacity="0.8"/>'
		. '<image xlink:href="https://elsewhere.example/a.png" width="10" height="10"/>'
		. '<image href="#pgfile-00000000-0" width="10" height="10"/>'
		. '<g><image href="/logo.png" width="10" height="10"/></g><style>.x{fill:red}</style>');
	$marked = ws_canvas_split_mark_pictures($svg, $resolve);

	pg_assert_same(array(0 => $files['/logo.png']), $marked['files'], 'only a top-level picture of a reachable file is marked');

	$split = ws_canvas_split_layers(pg_svg_sanitize($marked['svg']), array(), array('files' => $marked['files'], 'token' => $marked['token'], 'fonts' => array()));
	$picture = $split['layers'][0];

	pg_assert_same(array('image', '/logo.png', 7, 'logo', 0.8), array($picture['type'], $picture['src'], $picture['fileId'], $picture['name'], $picture['opacity']), 'a picture layer');
	pg_assert_same(array(0, 25, 100, 50), array($picture['x'], $picture['y'], $picture['w'], $picture['h']), 'the file fitted in its box, centred');
	pg_assert_same(array('svg', 'svg', 'svg'), array($split['layers'][1]['type'], $split['layers'][2]['type'], $split['layers'][3]['type']), 'an outside address, a forged mark and a nested picture stay as before');
	pg_assert_same(false, strpos($split['layers'][1]['markup'], 'elsewhere'), 'the outside address dropped by the sanitizer');

	$doc = json_decode(json_encode(ws_canvas_from_svg($svg, '', true, $resolve)['doc']), true);
	pg_assert_same(array('l0', 'image', 7), array($doc['layers'][0]['id'], $doc['layers'][0]['type'], $doc['layers'][0]['fileId']), 'through a create');

	$board = json_decode(json_encode(ws_canvas_from_svg('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="400"><rect width="800" height="400"/></svg>')['doc']), true);
	$added = ws_canvas_edit_apply($board, array(array('op' => 'add', 'layers' => 'split', 'svg' => workspace_canvas_parts_svg('<text x="0" y="20" font-size="10">Hi</text>'), 'x' => 0, 'y' => 0, 'w' => 800, 'h' => 400)), $resolve)['doc'];
	pg_assert_same(array('text', 20), array($added['layers'][1]['type'], $added['layers'][1]['size']), 'an added text scales with its frame');
}

function test_workspace_canvas_parts_file_names()
{
	pg_assert_same('logo.png', ws_canvas_split_file_name('https://Example.com/site/logo.png?v=3', 'example.com', '/site/'), 'this site\'s address');
	pg_assert_same('a b.png', ws_canvas_split_file_name('/site/a%20b.png#x', 'example.com', '/site/'), 'from the path');
	pg_assert_same('', ws_canvas_split_file_name('https://other.com/site/logo.png', 'example.com', '/site/'), 'another site');
	pg_assert_same('', ws_canvas_split_file_name('/elsewhere/logo.png', 'example.com', '/site/'), 'outside the path');
	pg_assert_same('', ws_canvas_split_file_name('/site/dir/logo.png', 'example.com', '/site/'), 'a folder of the disk');
	pg_assert_same('', ws_canvas_split_file_name('logo.png', 'example.com', '/site/'), 'no path');
	pg_assert_same('', ws_canvas_split_file_name('data:image/png;base64,AAAA', 'example.com', '/'), 'data');
}

// Runs a script with the model as M; its JSON, null without Node.
function workspace_canvas_parts_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgcanvasparts');

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
