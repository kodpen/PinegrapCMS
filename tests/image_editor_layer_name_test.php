<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the layers list's names (Editor.layerName() in
 * pg_image_editor_layers.js): a numbered name stays with its layer when the
 * stacking order changes. They run the editor parts under Node and are
 * skipped where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and the editor's prototype as Editor;
// answers its JSON, null without Node.
function image_editor_layer_name_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgielname');

	if ($file === false) {
		return null;
	}

	$js = PG_FUNCTIONS_DIR . '/assets/js/';
	$head = 'global.window = global; global.document = { activeElement: null, addEventListener: function () {} };' . "\n"
		. 'var M = require(' . json_encode($js . 'pg_image_editor_model.js') . '); window.PgImageEditorModel = window.PgImageEditorModel || M;' . "\n"
		. 'require(' . json_encode($js . 'pg_image_editor.js') . '); require(' . json_encode($js . 'pg_image_editor_layers.js') . ');' . "\n"
		. 'var Editor = window.PgImageEditor.parts.Editor;' . "\n"
		. 'function img(id) { return { id: id, type: "image", x: 0, y: 0, w: 10, h: 10, rot: 0, opacity: 1 }; }' . "\n"
		. 'function named(layers) { var e = Object.create(Editor.prototype); e.doc = { layers: layers }; return layers.map(function (l) { return [l.id, e.layerName(l)]; }); }' . "\n";

	file_put_contents($file, $head . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The number comes from the layer's id (the order it was added in), so
// bringing a layer to the front keeps both names with their layers; an id
// that carries no number falls back to the stacking order after them.
function test_image_editor_layer_name_follows_the_layer()
{
	$r = image_editor_layer_name_node(
		'var a = img("l0"), b = img("l1"), c = img("l12");' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. ' added: named([a, b]), front: named([b, a]), many: named([c, a, b]),' . "\n"
		. ' odd: named([img("x"), img("l3")]), one: named([img("l5")])' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');

	$name1 = $r['added'][0][1];
	$name2 = $r['added'][1][1];

	pg_assert_true($name1 !== $name2, 'two images get two names');
	pg_assert_same(array(array('l1', $name2), array('l0', $name1)), $r['front'], 'brought to the front, each layer keeps its name');
	pg_assert_same(array(array('l12', str_replace('1', '3', $name1)), array('l0', $name1), array('l1', $name2)), $r['many'], 'numbered by id, not by place');
	pg_assert_same(array(array('x', str_replace('1', '2', $name1)), array('l3', $name1)), $r['odd'], 'an id without a number comes after the numbered ones');
	pg_assert_same(false, strpos($r['one'][0][1], '1'), 'a lone layer of its type has no number');
}
