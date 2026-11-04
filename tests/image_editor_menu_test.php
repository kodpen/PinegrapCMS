<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's context menu: which items M.menuList() (the
 * MENU section of assets/js/pg_image_editor_model.js, run under Node) gives
 * for the stage with layers, the picture, an area or nothing selected, for a
 * rail item and for a scene; the part's place in the bundle, its labels and
 * the commands it draws.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_menu_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiemenu');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n"
		// The names in order, a disabled one marked with a minus, a
		// separator as "|", a submenu as name[a,b], a mode as mode:<tool>
		// with a star when it shows.
		. 'var one = function (a) { if (a.sep) { return "|"; } var n = (a.off ? "-" : "") + (a.mode ? "mode:" + a.mode + (a.on ? "*" : "") : a.name);'
		. ' return a.sub ? n + "[" + a.sub.map(one).join(",") + "]" : n; };' . "\n"
		. 'var names = function (s) { return M.menuList(s).map(one); };' . "\n"
		. 'var all = { erase: true, keep: true, copy: true, adjust: true, redact: true };' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Layers: the clipboard, the selection bar's commands, order, align, lock,
// hide, then the type's own commands where they apply.
function test_image_editor_menu_for_layers()
{
	$r = image_editor_menu_node(
		'console.log(JSON.stringify({'
		. ' one: names({ at: "stage", layers: 1, free: 1, clip: false }),'
		. ' locked: names({ at: "stage", layers: 2, free: 0, locked: true, hidden: true, clip: true }),'
		. ' types: names({ at: "stage", layers: 3, free: 3, clip: true, areas: 2, texts: 1, warp: true, svg: true })'
		. '}));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');

	$order = 'order[front,forward,backward,back]';
	$align = 'align[alignLeft,alignCenter,alignRight,alignTop,alignMiddle,alignBottom,-distX,-distY]';

	pg_assert_same(array('cut', 'copyLayers', '-paste', 'duplicate', 'remove', '|', $order, $align, 'lock', 'hide'), $r['one'], 'one layer');
	pg_assert_same(array('-cut', 'copyLayers', 'paste', 'duplicate', '-remove', '|', $order, '-' . $align, 'unlock', 'show'), $r['locked'], 'locked and hidden layers');
	pg_assert_same(array(
		'cut', 'copyLayers', 'paste', 'duplicate', 'remove', '|', $order,
		'align[alignLeft,alignCenter,alignRight,alignTop,alignMiddle,alignBottom,distX,distY]', 'lock', 'hide', '|',
		'combine[unite,minusFront,intersect,exclude,divide]', 'outlines', 'expandWarp', 'breakApart'
	), $r['types'], 'the types\' commands after the rest');
}

// The picture and an area keep the selection bar's commands (no Options);
// the empty canvas: paste, select all, Import, canvas size, fit.
function test_image_editor_menu_for_picture_area_and_canvas()
{
	$r = image_editor_menu_node(
		'console.log(JSON.stringify({'
		. ' picture: names({ at: "stage", layers: 0, picture: true, clip: true }),'
		. ' area: names({ at: "stage", area: true, can: all }),'
		. ' empty: names({ at: "stage", count: 0, clip: false, imports: true, site: true, canvas: true }),'
		. ' bare: names({ at: "stage", count: 2, clip: true, imports: true }),'
		. ' mini: names({ at: "stage", count: 1, clip: false })'
		. '}));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(array('lockPicture', 'removePicture', '|', 'paste'), $r['picture'], 'the picture');
	pg_assert_same(array('erase', 'keep', 'copy', 'adjust', 'redact', 'invert', 'deselect'), $r['area'], 'an area');
	pg_assert_same(array('-paste', '-selectAll', '|', 'import[importComputer,importSite]', 'canvasSize', 'fit'), $r['empty'], 'the empty canvas');
	pg_assert_same(array('paste', 'selectAll', '|', 'importFile', 'fit'), $r['bare'], 'no site files, no Canvas tool');
	pg_assert_same(array('-paste', 'selectAll', '|', 'fit'), $r['mini'], 'no Import button');
}

// A group item lists its modes, the one shown marked; a tool of its own has
// no menu. A scene: duplicate, delete, earlier, later, each off at its limit.
function test_image_editor_menu_for_rail_and_scene()
{
	$r = image_editor_menu_node(
		'console.log(JSON.stringify({'
		. ' group: names({ at: "rail", modes: ["crop", "adjust", "area"], tool: "adjust" }),'
		. ' single: names({ at: "rail", modes: ["text"], tool: "text" }),'
		. ' first: names({ at: "scene", index: 0, count: 1, max: 20 }),'
		. ' middle: names({ at: "scene", index: 1, count: 3, max: 3 })'
		. '}));'
	);

	if ($r === null) {
		pg_assert_true(true, 'Node is not available');
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'runs');
	pg_assert_same(array('mode:crop', 'mode:adjust*', 'mode:area'), $r['group'], 'the group\'s modes');
	pg_assert_same(array(), $r['single'], 'one tool: no menu');
	pg_assert_same(array('sceneDuplicate', '-sceneDelete', '-sceneEarlier', '-sceneLater'), $r['first'], 'the only scene');
	pg_assert_same(array('-sceneDuplicate', 'sceneDelete', 'sceneEarlier', 'sceneLater'), $r['middle'], 'a middle scene at the limit');
}

// The part is bundled after the selection bar, has its labels, and every
// item the model can give has a command with a label.
function test_image_editor_menu_part()
{
	$bundles = pg_js_bundles();
	$files = $bundles['assets/js/pg_image_editor.min.js'];

	pg_assert_same('assets/js/pg_image_editor_menu.js', end($files), 'bundled last');
	pg_assert_true(in_array('menu', pg_image_editor_parts(), true), 'a part with its strings');

	$own = pg_image_editor_strings_menu();
	pg_assert_true(isset($own['ctxMenu'], $own['ctxCut'], $own['ctxCopy'], $own['ctxPaste']), 'the menu\'s own labels');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_menu.js');
	preg_match_all("#^\s*([a-zA-Z]+): \['([a-zA-Z]+)'#m", $js, $m, PREG_SET_ORDER);

	$commands = array();

	foreach ($m as $c) {
		$commands[$c[1]] = $c[2];
	}

	$names = array('cut', 'copyLayers', 'paste', 'duplicate', 'remove', 'order', 'front', 'forward', 'backward', 'back', 'align',
		'alignLeft', 'alignCenter', 'alignRight', 'alignTop', 'alignMiddle', 'alignBottom', 'distX', 'distY', 'lock', 'unlock',
		'hide', 'show', 'combine', 'unite', 'minusFront', 'intersect', 'exclude', 'divide', 'outlines', 'expandWarp', 'breakApart',
		'lockPicture', 'removePicture', 'erase', 'keep', 'copy', 'adjust', 'redact', 'invert', 'deselect', 'selectAll', 'import',
		'importComputer', 'importSite', 'importFile', 'canvasSize', 'fit', 'sceneDuplicate', 'sceneDelete', 'sceneEarlier', 'sceneLater');
	$strings = pg_image_editor_strings();

	foreach ($names as $name) {
		pg_assert_true(isset($commands[$name]), $name . ' has a command');

		if (isset($commands[$name])) {
			pg_assert_true(isset($strings[$commands[$name]]), $name . ' has its label ' . $commands[$name]);
		}
	}
}
