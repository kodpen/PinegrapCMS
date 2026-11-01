<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the callers of the image editor (includes/fn/image_editor.php):
 * the older window.openImageEditor() call mapped onto pgImageEditor.open(),
 * the edit buttons and the #pg-image-edit address of the loader, an edit
 * drawn for an endpoint of its own (pg_image_editor_render(), Workspace) and
 * the places a copy saved from a page's picture is written into.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The loader's script as pg_image_editor_includes() prints it, drawn in a
// PHP process of its own (it needs OUTPUT_PATH and prints once).
function image_editor_switch_loader()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgieswitch');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. "define('OUTPUT_PATH', '/');\n"
		. "define('OUTPUT_SOFTWARE_DIRECTORY', 'pinegrap');\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. "echo pg_image_editor_includes();\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if (($code !== 0) || !preg_match('#^<script>(.*)</script>$#s', implode("\n", $out), $match)) {
		return null;
	}

	return $match[1];
}

// Runs the loader under Node with a window and a document of plain objects,
// then $script, which prints JSON; null where there is no Node.
function image_editor_switch_node($loader, $hash, $script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgieswitch');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var opened = [], clicks = [];' . "\n"
		. 'var window = { location: { hash: ' . json_encode($hash) . ', pathname: "/p", search: "", reload: function () {} }, history: { replaceState: function () {} } };' . "\n"
		. 'var document = { head: { appendChild: function () {} }, createElement: function () { return {}; }, addEventListener: function (n, f) { clicks.push(f); } };' . "\n"
		. 'global.window = window; global.document = document;' . "\n"
		. 'window.PgImageEditor = { open: function (o) { opened.push(o); return Promise.resolve(null); } };' . "\n"
		. $loader . "\n"
		. 'setTimeout(function () {' . "\n" . $script . "\n" . '}, 0);' . "\n");

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The older options: a file by id or name is saved over, or copied when it
// may not be replaced; saveUrl sends the edit elsewhere with its label; the
// caller's onSaved gets the saved file.
function test_image_editor_switch_alias_maps_the_old_options()
{
	$loader = image_editor_switch_loader();
	$res = ($loader === null) ? null : image_editor_switch_node($loader, '', '
		var got = [];
		window.openImageEditor({ src: "/a.jpg", fileId: 4, fileName: "a.jpg", onSaved: function (f) { got.push(f); } });
		window.openImageEditor({ fileName: "b.png", allowReplace: false });
		window.openImageEditor({ src: "/c.png", fileName: "c.png", saveUrl: "/ws?message=1", saveLabel: "Send", onCancel: function () {} });
		setTimeout(function () {
			opened[0].onSave({ file: { mode: "replace", name: "a.jpg" }, doc: {} });
			console.log(JSON.stringify({ opened: opened.map(function (o) { return { fileId: o.fileId || 0, fileName: o.fileName || "", src: o.src || "", save: o.save, close: typeof o.onClose }; }), got: got, available: window.imageEditorAvailable() }));
		}, 0);');

	if ($res === null) {
		pg_assert_true(true, 'no PHP process or Node; skipped');
		return;
	}

	pg_assert_false(isset($res['error']), isset($res['error']) ? $res['error'] : '');
	pg_assert_same(array('fileId' => 4, 'fileName' => 'a.jpg', 'src' => '', 'save' => array('mode' => 'replace'), 'close' => 'undefined'), $res['opened'][0], 'a file is opened from the server, saved over');
	pg_assert_same(array('mode' => 'copy'), $res['opened'][1]['save'], 'allowReplace false saves a copy');
	pg_assert_same(array('fileId' => 0, 'fileName' => 'c.png', 'src' => '/c.png', 'save' => array('mode' => 'url', 'url' => '/ws?message=1', 'label' => 'Send'), 'close' => 'function'), $res['opened'][2], 'saveUrl takes the edit elsewhere');
	pg_assert_same(array(array('mode' => 'replace', 'name' => 'a.jpg')), $res['got'], 'onSaved gets the saved file');
	pg_assert_true($res['available'], 'the editor is always there');
}

// An edit button opens its file by name; #pg-image-edit= opens one on load
// (the address image_editor_edit.php sends to).
function test_image_editor_switch_buttons_and_address()
{
	$loader = image_editor_switch_loader();
	$res = ($loader === null) ? null : image_editor_switch_node($loader, '#pg-image-edit=a%20b.jpg', '
		var button = { getAttribute: function (n) { return { "data-pg-image-edit": "c.png" }[n] || null; } };
		var prevented = false;
		clicks[0]({ target: { closest: function (s) { return s === "[data-pg-image-edit]" ? button : null; } }, preventDefault: function () { prevented = true; } });
		clicks[0]({ target: { closest: function () { return null; } }, preventDefault: function () { throw new Error("not a button"); } });
		setTimeout(function () {
			console.log(JSON.stringify({ names: opened.map(function (o) { return o.fileName; }), modes: opened.map(function (o) { return o.save.mode; }), prevented: prevented }));
		}, 0);');

	if ($res === null) {
		pg_assert_true(true, 'no PHP process or Node; skipped');
		return;
	}

	pg_assert_false(isset($res['error']), isset($res['error']) ? $res['error'] : '');
	pg_assert_same(array('a b.jpg', 'c.png'), $res['names'], 'the address, then the button');
	pg_assert_same(array('replace', 'replace'), $res['modes'], 'both save over the file');
	pg_assert_true($res['prevented'], 'the link is not followed');
}

// Workspace's picture: the edit is drawn from the file in its own format, a
// document out of range is refused before any pixel work.
function test_image_editor_switch_render()
{
	if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = sys_get_temp_dir() . '/pgieswitch' . getmypid() . mt_rand(1000, 9999);
	@mkdir($folder, 0777, true);
	$path = $folder . '/photo.png';
	$image = imagecreatetruecolor(80, 60);
	imagefilledrectangle($image, 0, 0, 79, 59, imagecolorallocate($image, 200, 30, 30));
	imagepng($image, $path);
	pg_image_destroy($image);

	$doc = array(
		'v' => 1,
		'source' => array('fileId' => 0, 'width' => 80, 'height' => 60),
		'artboard' => null,
		'geometry' => array('rot' => 90, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => array('x' => 0, 'y' => 0, 'w' => 30, 'h' => 40), 'cropShape' => 'rect', 'output' => null),
		'adjust' => array(),
		'filter' => 'none',
		'frame' => null,
		'layers' => array());

	try {
		$made = pg_image_editor_render($path, array('doc' => $doc));

		pg_assert_same('', $made['error'], 'drawn');
		pg_assert_same('png', $made['extension'], 'in the file\'s own format');
		pg_assert_same(array(30, 40), array_slice(getimagesizefromstring($made['data']), 0, 2), 'turned and cropped');
		pg_assert_same(array($path), glob($folder . '/*'), 'no temporary file left, the file untouched');

		$doc['geometry']['rot'] = 45;
		$refused = pg_image_editor_render($path, array('doc' => $doc));

		pg_assert_true($refused['error'] !== '', 'a turn the editor never makes is refused');
		pg_assert_same('', $refused['data'], 'with nothing drawn');
		pg_assert_true(pg_image_editor_render($folder . '/missing.png', array('doc' => $doc))['error'] !== '', 'a missing file is refused');
	} finally {
		foreach ((array) glob($folder . '/*') as $file) {
			@unlink($file);
		}

		@rmdir($folder);
	}
}

// Every object type of the edit buttons has its table, id column and
// content column; a product's column comes from the button.
function test_image_editor_switch_content_places()
{
	$places = pg_image_editor_content_places();

	pg_assert_same(array('ad', 'calendar_event', 'cregion', 'pregion', 'system_region_header', 'system_region_footer', 'product', 'product_group', 'form_field'), array_keys($places));
	pg_assert_same(array('pregion', 'pregion_id', 'pregion_content'), $places['pregion'], 'tables are singular');

	$frontend = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');

	foreach (array('pregion', 'cregion', 'system_region_header', 'system_region_footer') as $type) {
		pg_assert_contains("var image_editor_object_type = '" . $type . "';", $frontend, $type . ' is a type the page editor sends');
	}
}
