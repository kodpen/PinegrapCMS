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
	pg_assert_same(2, $kept['source']['base'], 'drawn on base 2');
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
