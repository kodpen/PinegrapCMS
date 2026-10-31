<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's save (includes/fn/image_editor.php and the
 * edit steps of includes/image_engine.php): the document check and the
 * recipe it gives, the recipe made concrete for a file of another size, the
 * pixels GD (and Imagick where it is loaded) draw for turns, flips, a
 * straightened crop and an output size, held against the preview's own
 * matrices under Node, the crop shapes, the overlay, the first frame of an
 * animated picture, and the PNG a browser sends.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A document as the editor saves it: a 240 x 160 picture with $geometry
// over the defaults.
function image_editor_save_doc($geometry = array())
{
	return array(
		'v' => 1,
		'source' => array('fileId' => 7, 'width' => 240, 'height' => 160),
		'artboard' => null,
		'geometry' => array_merge(array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => null, 'cropShape' => 'rect', 'output' => null), $geometry),
		'adjust' => array(),
		'filter' => 'none',
		'frame' => null,
		'layers' => array());
}

function image_editor_save_gd()
{
	return function_exists('imagecreatetruecolor') && function_exists('imagerotate') && function_exists('imageflip') && function_exists('imagepng');
}

// A folder of its own under the system's temporary one.
function image_editor_save_folder()
{
	$folder = sys_get_temp_dir() . '/pgiesave' . getmypid() . mt_rand(1000, 9999);
	@mkdir($folder, 0777, true);

	return $folder;
}

function image_editor_save_remove($folder)
{
	foreach ((array) glob($folder . '/*') as $file) {
		@unlink($file);
	}

	@rmdir($folder);
}

// A 240 x 160 PNG whose red is the pixel's x and green its y, so a pixel of
// the result tells where in the original it was taken from.
function image_editor_save_gradient($path)
{
	$image = imagecreatetruecolor(240, 160);

	for ($y = 0; $y < 160; $y++) {
		for ($x = 0; $x < 240; $x++) {
			imagesetpixel($image, $x, $y, ($x << 16) | ($y << 8) | 128);
		}
	}

	imagepng($image, $path);
	pg_image_destroy($image);
}

// A w x h truecolor picture of one colour, transparent where alpha is 127.
function image_editor_save_solid($width, $height, $red, $green, $blue, $alpha = 0)
{
	$image = imagecreatetruecolor($width, $height);
	imagealphablending($image, false);
	imagesavealpha($image, true);
	imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocatealpha($image, $red, $green, $blue, $alpha));

	return $image;
}

function image_editor_save_pixel($image, $x, $y)
{
	$rgba = imagecolorat($image, $x, $y);

	return array(($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF, ($rgba >> 24) & 0x7F);
}

// Runs $script with the editor's model as M and answers its JSON; null where
// there is no Node.
function image_editor_save_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiesave');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// A document the editor makes is taken and turned into the engine's recipe:
// the turned space, the crop, the output size (the crop's by default).
function test_image_editor_save_validate_builds_the_recipe()
{
	$check = pg_image_edit_validate(image_editor_save_doc(array('rot' => 90, 'flipX' => true, 'straighten' => 4.5, 'crop' => array('x' => 10, 'y' => 20.5, 'w' => 100, 'h' => 80.4), 'cropShape' => 'circle')));
	$edit = $check['edit'];

	pg_assert_same('', $check['error'], 'taken');
	pg_assert_same(90, $edit['rot'], 'the quarter turn');
	pg_assert_true(($edit['flip_x'] === true) && ($edit['flip_y'] === false), 'the flips');
	pg_assert_same(4.5, $edit['straighten'], 'the straightening');
	pg_assert_same(array(160.0, 240.0), array((float) $edit['space_w'], (float) $edit['space_h']), 'a quarter turn swaps the space');
	pg_assert_same(array('x' => 10.0, 'y' => 20.5, 'w' => 100.0, 'h' => 80.4), $edit['crop'], 'the crop');
	pg_assert_same(array(100, 80), array($edit['out_w'], $edit['out_h']), 'the crop size without an output size');
	pg_assert_same('circle', $edit['shape'], 'the shape');

	$plain = pg_image_edit_validate(image_editor_save_doc());

	pg_assert_same(null, $plain['edit']['crop'], 'no crop');
	pg_assert_same(array(240, 160), array($plain['edit']['out_w'], $plain['edit']['out_h']), 'the whole picture');

	$sized = pg_image_edit_validate(image_editor_save_doc(array('output' => array('w' => 1200, 'h' => 800))));

	pg_assert_same(array(1200, 800), array($sized['edit']['out_w'], $sized['edit']['out_h']), 'the output size');

	$board = pg_image_edit_validate(array('v' => 1, 'source' => null, 'artboard' => array('width' => 1080, 'height' => 1350, 'background' => '#ffffff'), 'layers' => array()));

	pg_assert_same('', $board['error'], 'an artboard is taken');
	pg_assert_same(array('width' => 1080, 'height' => 1350), $board['artboard'], 'its size');
	pg_assert_same(null, $board['edit'], 'with no recipe');
}

// Anything the editor does not make is refused whole, by the part that is
// wrong.
function test_image_editor_save_validate_refuses()
{
	$cases = array(
		array('v', array_merge(image_editor_save_doc(), array('v' => 2))),
		array('v', '{"v":1}'),
		array('layers', array_merge(image_editor_save_doc(), array('layers' => array_fill(0, 501, array())))),
		array('source', array_merge(image_editor_save_doc(), array('source' => array('width' => 0, 'height' => 160)))),
		array('source', array_merge(image_editor_save_doc(), array('geometry' => null))),
		array('rot', image_editor_save_doc(array('rot' => 45))),
		array('rot', image_editor_save_doc(array('rot' => '90'))),
		array('flip', image_editor_save_doc(array('flipX' => 1))),
		array('straighten', image_editor_save_doc(array('straighten' => 45.5))),
		array('crop', image_editor_save_doc(array('crop' => array('x' => 200, 'y' => 0, 'w' => 60, 'h' => 50)))),
		array('crop', image_editor_save_doc(array('rot' => 90, 'crop' => array('x' => 0, 'y' => 0, 'w' => 200, 'h' => 50)))),
		array('crop', image_editor_save_doc(array('crop' => array('x' => 0, 'y' => 0, 'w' => 0.5, 'h' => 50)))),
		array('crop', image_editor_save_doc(array('crop' => array('x' => '0', 'y' => 0, 'w' => 10, 'h' => 10)))),
		array('crop', image_editor_save_doc(array('crop' => 'all'))),
		array('shape', image_editor_save_doc(array('cropShape' => 'star'))),
		array('output', image_editor_save_doc(array('output' => array('w' => 8001, 'h' => 10)))),
		array('output', image_editor_save_doc(array('output' => array('w' => 100)))),
		array('artboard', array('v' => 1, 'artboard' => array('width' => 9000, 'height' => 100))),
		array('artboard', array('v' => 1, 'artboard' => array('width' => 8))));

	foreach ($cases as $case) {
		$check = pg_image_edit_validate($case[1]);

		pg_assert_same($case[0], $check['error'], json_encode($case[1]) . ' is refused');
		pg_assert_true(($check['edit'] === null) && ($check['artboard'] === null), 'with nothing to draw');
	}
}

// The crop is placed by the browser's picture; a file of another size is
// cropped at the same place, and the cover scale is the preview's.
function test_image_editor_save_plan_follows_the_file()
{
	$check = pg_image_edit_validate(image_editor_save_doc(array('crop' => array('x' => 24, 'y' => 16, 'w' => 120, 'h' => 80))));
	$plan = pg_image_edit_plan(480, 320, $check['edit']);

	pg_assert_same(array('x' => 48.0, 'y' => 32.0, 'w' => 240.0, 'h' => 160.0), $plan['crop'], 'twice the size, twice the box');
	pg_assert_same(array(120, 80), array($plan['out_w'], $plan['out_h']), 'the output stays what the editor showed');

	$work = pg_image_edit_work_size(240, 160, pg_image_edit_validate(image_editor_save_doc(array('straighten' => 30)))['edit']);

	pg_assert_true(($work[0] > 240) && ($work[1] > 160), 'room for the straightened copy: ' . json_encode($work));

	$crops = array(
		array(array('x' => 0, 'y' => 0, 'w' => 240, 'h' => 160), 10),
		array(array('x' => 40, 'y' => 20, 'w' => 160, 'h' => 120), -12),
		array(array('x' => 10, 'y' => 50, 'w' => 60, 'h' => 100), 33.5));
	$node = image_editor_save_node('console.log(JSON.stringify(' . json_encode($crops) . '.map(function (c) { return M.coverScale(c[0], 240, 160, c[1]); })));');

	if ($node === null) {
		return;
	}

	pg_assert_false(isset($node['error']), isset($node['error']) ? $node['error'] : 'node ran');

	foreach ($crops as $i => $crop) {
		pg_assert_true(abs(pg_image_edit_cover_scale($crop[0], 240, 160, $crop[1]) - $node[$i]) < 1e-9, 'cover scale ' . $i . ' as the preview\'s');
	}
}

// Where each sample of the result comes from in the original, by the
// preview's matrices; checked against the pixels a library drew.
function image_editor_save_parity($library)
{
	$docs = array(
		image_editor_save_doc(array('rot' => 90, 'flipX' => true, 'crop' => array('x' => 20, 'y' => 30, 'w' => 100, 'h' => 150))),
		image_editor_save_doc(array('straighten' => 12, 'crop' => array('x' => 40, 'y' => 20, 'w' => 160, 'h' => 120), 'output' => array('w' => 80, 'h' => 60))),
		image_editor_save_doc(array('rot' => 270, 'flipY' => true, 'straighten' => -7.5, 'crop' => array('x' => 30, 'y' => 40, 'w' => 90, 'h' => 140), 'output' => array('w' => 180, 'h' => 280))),
		image_editor_save_doc(array('rot' => 180, 'flipX' => true, 'flipY' => true)));

	$expected = image_editor_save_node('var docs = ' . json_encode($docs) . ";\n"
		. 'console.log(JSON.stringify(docs.map(function (doc) {'
		. '  var crop = M.cropBox(doc), size = M.outputSize(doc), inv = M.inv(M.pictureMatrix(doc)), points = [];'
		. '  [0.2, 0.5, 0.8].forEach(function (fy) { [0.2, 0.5, 0.8].forEach(function (fx) {'
		. '    var i = Math.floor(fx * size.w), j = Math.floor(fy * size.h);'
		. '    var q = M.apply(inv, crop.x + (i + 0.5) * crop.w / size.w, crop.y + (j + 0.5) * crop.h / size.h);'
		. '    points.push([i, j, q[0], q[1]]);'
		. '  }); });'
		. '  return points;'
		. '})));');

	if ($expected === null) {
		return;
	}

	pg_assert_false(isset($expected['error']), isset($expected['error']) ? $expected['error'] : 'node ran');

	$folder = image_editor_save_folder();
	$source = $folder . '/gradient.png';

	try {
		image_editor_save_gradient($source);

		foreach ($docs as $n => $doc) {
			$edit = pg_image_edit_validate($doc)['edit'];
			$data = ($library === 'gd')
				? pg_process_image_with_gd($source, IMAGETYPE_PNG, false, 240, 160, 92, '', 0, $edit)
				: pg_process_image_with_imagick($source, IMAGETYPE_PNG, false, 240, 160, 92, '', 0, $edit);

			pg_assert_true(is_string($data) && ($data !== ''), $library . ' drew case ' . $n);

			$image = imagecreatefromstring((string) $data);

			pg_assert_same(array($edit['out_w'], $edit['out_h']), array(imagesx($image), imagesy($image)), $library . ' case ' . $n . ' at the output size');

			foreach ($expected[$n] as $point) {
				$pixel = image_editor_save_pixel($image, $point[0], $point[1]);
				$x = $point[2] - 0.5;
				$y = $point[3] - 0.5;

				pg_assert_true((abs($pixel[0] - $x) <= 3) && (abs($pixel[1] - $y) <= 3), $library . ' case ' . $n . ' pixel ' . $point[0] . ',' . $point[1] . ' is from ' . round($x, 1) . ',' . round($y, 1) . ' of the original, not ' . $pixel[0] . ',' . $pixel[1]);
			}

			pg_image_destroy($image);
		}
	} finally {
		image_editor_save_remove($folder);
	}
}

// The server draws what the preview shows: a turn with a flip, a
// straightened crop at another output size, both together, a half turn with
// both flips.
function test_image_editor_save_gd_draws_what_the_preview_shows()
{
	if (!image_editor_save_gd()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	image_editor_save_parity('gd');
}

function test_image_editor_save_imagick_draws_what_the_preview_shows()
{
	if (!class_exists('Imagick')) {
		pg_assert_true(true, 'Imagick is not loaded; skipped');
		return;
	}

	image_editor_save_parity('imagick');
}

// A circle and rounded corners clear what lies outside, with soft edges;
// without alpha the corners are white. The overlay goes over the picture
// and is cut by the shape too, as the preview clips the layers.
function test_image_editor_save_gd_shape_and_overlay()
{
	if (!image_editor_save_gd()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$circle = pg_image_edit_gd(image_editor_save_solid(100, 100, 10, 120, 200), array('shape' => 'circle', 'out_w' => 100, 'out_h' => 100), true);

	pg_assert_same(127, image_editor_save_pixel($circle, 0, 0)[3], 'a corner is cleared');
	pg_assert_same(array(10, 120, 200, 0), image_editor_save_pixel($circle, 50, 50), 'the middle is kept');
	pg_assert_same(0, image_editor_save_pixel($circle, 0, 50)[3], 'the widest row reaches the edge');
	$edge = image_editor_save_pixel($circle, 14, 14)[3];
	pg_assert_true(($edge > 0) && ($edge < 127), 'the pixel the curve crosses is partly clear: ' . $edge);
	pg_image_destroy($circle);

	$rounded = pg_image_edit_gd(image_editor_save_solid(100, 100, 10, 120, 200), array('shape' => 'rounded', 'out_w' => 100, 'out_h' => 100), false);

	pg_assert_same(array(255, 255, 255, 0), image_editor_save_pixel($rounded, 0, 0), 'without alpha the corner is white');
	pg_assert_same(array(10, 120, 200, 0), image_editor_save_pixel($rounded, 50, 0), 'the top edge between the corners is kept');
	pg_image_destroy($rounded);

	$folder = image_editor_save_folder();
	$overlay = $folder . '/overlay.png';

	try {
		$layer = image_editor_save_solid(100, 100, 0, 0, 0, 127);
		imagefilledrectangle($layer, 40, 40, 59, 59, imagecolorallocatealpha($layer, 255, 0, 0, 0));
		imagefilledrectangle($layer, 0, 0, 9, 9, imagecolorallocatealpha($layer, 255, 0, 0, 0));
		imagepng($layer, $overlay);
		pg_image_destroy($layer);

		$drawn = pg_image_edit_gd(image_editor_save_solid(100, 100, 10, 120, 200), array('shape' => 'circle', 'out_w' => 100, 'out_h' => 100, 'overlay' => $overlay), true);

		pg_assert_same(array(255, 0, 0, 0), image_editor_save_pixel($drawn, 50, 50), 'the overlay is over the picture');
		pg_assert_same(array(10, 120, 200, 0), image_editor_save_pixel($drawn, 30, 50), 'and nowhere else');
		pg_assert_same(127, image_editor_save_pixel($drawn, 1, 1)[3], 'the shape cuts the overlay too');
		pg_image_destroy($drawn);
	} finally {
		image_editor_save_remove($folder);
	}
}

// An edit is refused for an animated picture unless it asks for the first
// frame (a copy); it is written even when the file comes out heavier.
function test_image_editor_save_engine_edit()
{
	if (!image_editor_save_gd()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_editor_save_folder();
	$source = $folder . '/moving.png';

	try {
		$image = image_editor_save_solid(80, 60, 200, 30, 30);
		ob_start();
		imagepng($image);
		$png = ob_get_clean();
		pg_image_destroy($image);

		// An acTL chunk before the picture's data makes it an APNG.
		$actl = pack('N', 8) . 'acTL' . pack('N', 2) . pack('N', 0) . pack('N', crc32('acTL' . pack('N', 2) . pack('N', 0)));
		file_put_contents($source, substr($png, 0, 33) . $actl . substr($png, 33));

		$edit = pg_image_edit_validate(array_merge(image_editor_save_doc(array('crop' => array('x' => 10, 'y' => 10, 'w' => 40, 'h' => 30))), array('source' => array('width' => 80, 'height' => 60))))['edit'];
		$refused = pg_image_edit_apply($source, $edit);

		pg_assert_same('animated', $refused['reason'], 'an animated picture is left alone');

		$edit['first_frame'] = true;
		$still = pg_image_edit_apply($source, $edit);

		pg_assert_same('success', $still['status'], 'its first frame is edited for a copy');
		pg_assert_same(array(40, 30), array($still['width'], $still['height']), 'at the crop size');
		pg_assert_false($still['changed'], 'and only handed back');

		file_put_contents($source, $png);
		unset($edit['first_frame']);
		$written = pg_process_image_file($source, array('edit' => $edit));

		pg_assert_true($written['changed'], 'an edit is written');
		pg_assert_same(array(40, 30), array_slice(getimagesize($source), 0, 2), 'the file is the crop');
	} finally {
		image_editor_save_remove($folder);
	}
}

// A browser's PNG is kept only as a PNG data: URL of the size asked for.
function test_image_editor_save_take_png()
{
	if (!image_editor_save_gd()) {
		pg_assert_true(true, 'GD is not available; skipped');
		return;
	}

	$folder = image_editor_save_folder();
	$path = $folder . '/taken.png';

	try {
		$image = image_editor_save_solid(30, 20, 0, 0, 0, 127);
		ob_start();
		imagepng($image);
		$png = ob_get_clean();
		pg_image_destroy($image);

		pg_assert_true(pg_image_editor_take_png('data:image/png;base64,' . base64_encode($png), $path, 30, 20), 'the right PNG is taken');
		pg_assert_same($png, file_get_contents($path), 'byte for byte');

		pg_assert_false(pg_image_editor_take_png('data:image/png;base64,' . base64_encode($png), $path . '2', 31, 20), 'another size is refused');
		pg_assert_false(file_exists($path . '2'), 'and not left on the disk');
		pg_assert_false(pg_image_editor_take_png('data:image/svg+xml;base64,' . base64_encode('<svg/>'), $path . '3', 30, 20), 'another type is refused');
		pg_assert_false(pg_image_editor_take_png('data:image/png;base64,' . base64_encode('<?php echo 1;'), $path . '4', 30, 20), 'bytes that are no PNG are refused');
		pg_assert_false(file_exists($path . '4'), 'and not left on the disk');
	} finally {
		image_editor_save_remove($folder);
	}
}
