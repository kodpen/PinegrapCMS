<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for saving an animation into Files (action animation in
 * includes/fn/image_editor.php): the type told by the bytes, the request
 * split from the file in one binary body, the document a version keeps and
 * what reopening reads back from it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A PNG chunk: length, type, data and its CRC.
function image_editor_animation_chunk($type, $data)
{
	return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
}

// Each format by its bytes, never a still picture of the same family.
function test_image_editor_animation_type_by_bytes()
{
	$png = "\x89PNG\r\n\x1a\n" . image_editor_animation_chunk('IHDR', pack('NN', 4, 4) . "\x08\x06\x00\x00\x00");
	$idat = image_editor_animation_chunk('IDAT', 'x') . image_editor_animation_chunk('IEND', '');

	pg_assert_same('gif', pg_image_editor_animation_type("GIF89a\x04\x00\x04\x00rest"), 'GIF');
	pg_assert_same('png', pg_image_editor_animation_type($png . image_editor_animation_chunk('acTL', pack('NN', 2, 0)) . $idat), 'APNG');
	pg_assert_same('', pg_image_editor_animation_type($png . $idat), 'a still PNG');
	pg_assert_same('', pg_image_editor_animation_type($png . $idat . image_editor_animation_chunk('acTL', pack('NN', 2, 0))), 'acTL after IDAT is no APNG');
	pg_assert_same('webp', pg_image_editor_animation_type('RIFF' . pack('V', 22) . 'WEBPVP8X' . pack('V', 10) . "\x02\x00\x00\x00" . str_repeat("\x00", 6) . 'ANIM'), 'animated WebP');
	pg_assert_same('', pg_image_editor_animation_type('RIFF' . pack('V', 22) . 'WEBPVP8X' . pack('V', 10) . "\x00\x00\x00\x00" . str_repeat("\x00", 6)), 'a still WebP');
	pg_assert_same('mp4', pg_image_editor_animation_type(pack('N', 24) . 'ftypisom' . str_repeat("\x00", 12)), 'MP4');
	pg_assert_same('webm', pg_image_editor_animation_type("\x1a\x45\xdf\xa3\x9f\x42\x86\x81\x01\x42\x82\x84webm"), 'WebM');
	pg_assert_same('', pg_image_editor_animation_type("\x1a\x45\xdf\xa3\x9f\x42\x86\x81\x01\x42\x82\x88matroska"), 'Matroska is not WebM');
	pg_assert_same('pdf', pg_image_editor_animation_type("%PDF-1.4\n"), 'PDF');
	pg_assert_same('', pg_image_editor_animation_type('<?php echo 1;'), 'anything else');
	pg_assert_same('', pg_image_editor_animation_type(''), 'nothing');
}

// One line of JSON, then the file's bytes as they are.
function test_image_editor_animation_binary_body()
{
	pg_assert_same(array('{"action":"animation"}', "GIF89a\n\x00\xff"), pg_image_editor_binary_body("{\"action\":\"animation\"}\nGIF89a\n\x00\xff"), 'split at the first line end');
	pg_assert_same(array('{"action":"open"}', ''), pg_image_editor_binary_body('{"action":"open"}'), 'a JSON body alone');
}

// The version keeps the document with the source file, its base and the
// format; reopening reads them back and refuses anything else.
function test_image_editor_animation_doc()
{
	$doc = json_decode('{"v":1,"source":{"fileId":7,"width":40,"height":30},"geometry":{},"adjust":{},"layers":[],"motion":{"duration":3}}');
	$json = pg_image_editor_animation_doc_json($doc, 7, 2, 'gif');
	$kept = json_decode($json, true);

	pg_assert_same(array('source' => 7, 'base' => 2, 'format' => 'gif'), $kept['animation'], 'animation member');
	pg_assert_false(isset($kept['source']['base']), 'source.base names no version of this file');
	pg_assert_same(0, pg_file_versions_base_no(array('doc_json' => $json, 'version_no' => 3)), 'so the retention keeps no row of this file for it');
	pg_assert_true(is_object(json_decode($json)->adjust), 'an empty {} stays an object');
	pg_assert_same(array('source' => 7, 'base' => 2, 'format' => 'gif'), pg_image_editor_animation_of($json), 'read back');
	pg_assert_same(null, pg_image_editor_animation_of('{"v":1,"source":{}}'), 'a still document');
	pg_assert_same(null, pg_image_editor_animation_of('{"v":1,"animation":{"source":7,"base":2,"format":"exe"}}'), 'an unknown format');
	pg_assert_same(null, pg_image_editor_animation_of(''), 'a base row');
	pg_assert_same('', pg_image_editor_animation_doc_json($doc, 7, 2, 'bmp'), 'no animation format');
	pg_assert_same('', pg_image_editor_animation_doc_json(array('v' => 1), 7, 2, 'gif'), 'not decoded as objects');

	$board = json_decode('{"v":1,"artboard":{"width":100,"height":80,"background":"#fff"},"layers":[]}');
	$kept = json_decode(pg_image_editor_animation_doc_json($board, 0, 0, 'gif'), true);
	pg_assert_same(0, $kept['animation']['source'], 'a canvas names no source');
	pg_assert_false(isset($kept['source']), 'and gets no source member');
	pg_assert_true(pg_image_editor_canvas_is_doc(json_decode(json_encode($kept))), 'still a canvas document');
}

// The largest file a save takes: the smallest of the upload, request and
// memory bounds.
function test_image_editor_animation_limit()
{
	pg_assert_same(8000000, pg_image_editor_animation_limit(array('file_max' => 8000000, 'request_max' => 9000000, 'json_max' => 20000000)), 'the upload limit');
	pg_assert_same(14500000, pg_image_editor_animation_limit(array('file_max' => 0, 'request_max' => 0, 'json_max' => 10000000)), 'the memory, held twice');
	pg_assert_same(0, pg_image_editor_animation_limit(array('file_max' => 0, 'request_max' => 0, 'json_max' => 0)), 'nothing known');
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_animation_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgieanim');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Over the limit a smaller edge or rate is offered, the largest that fits;
// a same-format animation the editor opened is replaced.
function test_image_editor_animation_model()
{
	$r = image_editor_animation_node(
		'var out = { w: 2400, h: 1600 }, set = { format: "gif", edge: 480, fps: 15 };' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. ' fits: [M.animFits(10, 0), M.animFits(10, 10), M.animFits(11, 10)],' . "\n"
		. ' smaller: M.animSmaller(out, set, 3000000, 2000000), none: M.animSmaller(out, set, 3000000, 100000), under: M.animSmaller(out, set, 100, 2000000),' . "\n"
		. ' modes: [M.animSaveMode(null, "gif"), M.animSaveMode({ id: 4, format: "gif", replace: true }, "gif"), M.animSaveMode({ id: 4, format: "png", replace: true }, "gif"), M.animSaveMode({ id: 4, format: "gif", replace: false }, "gif")]' . "\n"
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(true, true, false), $r['fits'], 'fits');
	pg_assert_same(array('edge' => 320, 'fps' => 15, 'bytes' => 1331250), $r['smaller'], 'the largest smaller file that fits');
	pg_assert_same(null, $r['none'], 'nothing offered fits');
	pg_assert_same(null, $r['under'], 'nothing to offer under the limit');
	pg_assert_same(array('new', 'replace', 'new', 'new'), $r['modes'], 'replace only the same format');
}
