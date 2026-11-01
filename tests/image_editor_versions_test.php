<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's versions: which saves the retention keeps
 * (the original plus the last 10), the stored file names, which documents a
 * version row may hold, which version a document was drawn on, and the
 * drawer's model section run under Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Rows of versions 1..$count of one file, oldest first.
function image_editor_versions_rows($count)
{
	$rows = array();

	for ($n = 1; $n <= $count; $n++) {
		$rows[] = array('version_id' => 100 + $n, 'version_no' => $n, 'base_no' => 1);
	}

	return $rows;
}

// The original and the last 10 saves stay; the rest go, oldest first.
function test_image_editor_versions_retention_keeps_the_original_and_ten()
{
	pg_assert_same(array(), pg_file_versions_pruned(image_editor_versions_rows(11)), 'eleven rows: nothing goes');
	pg_assert_same(array(102), pg_file_versions_pruned(image_editor_versions_rows(12)), 'twelve rows: version 2 goes');
	pg_assert_same(array(102, 103, 104), pg_file_versions_pruned(image_editor_versions_rows(14)), 'fourteen rows: versions 2-4 go');
	pg_assert_same(array(), pg_file_versions_pruned(array()), 'no rows');

	// The file changed outside the editor: version 3 is a base row, the saves
	// after it are drawn on it and keep it past the newest ten.
	$rows = image_editor_versions_rows(16);
	$rows[2]['base_no'] = 3;

	for ($i = 3; $i < 16; $i++) {
		$rows[$i]['base_no'] = 3;
	}

	pg_assert_same(array(102, 104, 105, 106), pg_file_versions_pruned($rows), 'the base the kept saves need stays');

	$rows[15]['base_no'] = 0;
	pg_assert_same(array(102, 104, 105, 106), pg_file_versions_pruned($rows), 'an unknown base keeps nothing more');
}

// A request decoded once as objects reads as json_decode($body, true) would;
// the overlay string is the same.
function test_image_editor_versions_request_arrays()
{
	$body = '{"action":"save","doc":{"v":1,"adjust":{},"layers":[{"id":"a","pts":[1,2]}]},"overlay":"data:image/png;base64,AAAA"}';

	pg_assert_same(json_decode($body, true), pg_image_editor_arrays(json_decode($body)), 'arrays as the assoc decode');
	pg_assert_same('7', pg_image_editor_arrays('7'), 'a scalar as it is');
}

// A version's file name: file, version, a digest and the format; anything
// else gives no name.
function test_image_editor_versions_stored_name()
{
	pg_assert_same('7-3-0123456789abcdef.jpg', pg_file_versions_stored_name(7, 3, 'JPG', '0123456789abcdef0123'), 'file, version, digest, format');
	pg_assert_same('', pg_file_versions_stored_name(0, 3, 'jpg', '0123456789abcdef'), 'no file');
	pg_assert_same('', pg_file_versions_stored_name(7, 0, 'jpg', '0123456789abcdef'), 'no version');
	pg_assert_same('', pg_file_versions_stored_name(7, 1, '../x', '0123456789abcdef'), 'a path is no format');
	pg_assert_same('', pg_file_versions_stored_name(7, 1, 'png', 'not hex'), 'a digest is hex');
}

// A document is kept up to 1 MB and without a picture inside it.
function test_image_editor_versions_doc_storable()
{
	pg_assert_true(pg_file_versions_doc_storable('{"v":1,"layers":[{"type":"text","text":"data: 5"}]}'), 'the word data: in a text is no picture');
	pg_assert_false(pg_file_versions_doc_storable('{"v":1,"layers":[{"type":"image","src":"data:image/png;base64,iVBOR"}]}'), 'a pasted picture is not kept');
	pg_assert_false(pg_file_versions_doc_storable('{"v":1,"t":"' . str_repeat('a', 1048576) . '"}'), 'over 1 MB is not kept');
	pg_assert_false(pg_file_versions_doc_storable(''), 'empty');
}

// The kept document carries its base and keeps {} an object.
function test_image_editor_versions_doc_json_keeps_objects_and_base()
{
	$doc = json_decode('{"v":1,"source":{"fileId":4,"width":30,"height":20},"geometry":{"rot":0},"adjust":{},"layers":[]}');
	$json = pg_file_versions_doc_json($doc, 1);

	pg_assert_contains('"adjust":{}', $json, 'an empty object stays one');
	pg_assert_contains('"base":1', $json, 'the base is kept');
	pg_assert_false(isset($doc->source->base), 'the request\'s document is left as it was');
	pg_assert_same('', pg_file_versions_doc_json(array('v' => 1, 'source' => array()), 1), 'an array is refused: it would lose {}');
	pg_assert_same('', pg_file_versions_doc_json(json_decode('{"v":1,"source":{"fileId":4},"layers":[{"src":"data:image/png;base64,AAAA"}]}'), 1), 'a picture inside: not kept');

	pg_assert_same(3, pg_file_versions_base_no(array('version_no' => 3, 'doc_json' => '')), 'a base row is its own base');
	pg_assert_same(1, pg_file_versions_base_no(array('version_no' => 5, 'doc_json' => $json)), 'a save row names its base');
	pg_assert_same(0, pg_file_versions_base_no(array('version_no' => 5, 'doc_json' => '{"v":1}')), 'unknown base');
}

// Every label of the drawer is translated.
function test_image_editor_versions_labels()
{
	$strings = pg_image_editor_strings_versions();

	foreach (array('versions', 'versionN', 'versionRestore', 'versionPreview', 'versionDelete', 'versionYou', 'versionLatest', 'versionOriginal', 'versionSaved', 'versionRestored', 'versionRedacted') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), 'label ' . $key);
	}
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_versions_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgievers');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The model's versions section: labels, kinds and the document an open
// brings back.
function test_image_editor_versions_model()
{
	$node = image_editor_versions_node('console.log(JSON.stringify({'
		. ' text: M.versionText("Version {n}", 4),'
		. ' kinds: ["original", "restored", "redacted", "saved", "other"].map(M.versionKindKey),'
		. ' line: [M.versionLine({ when: "09:41", user: "" }, "You"), M.versionLine({ when: "09:41", user: "ayse" }, "You")],'
		. ' doc: M.versionOpenDoc({ v: 1, source: { fileId: 9, width: 30, height: 20, base: 1 }, geometry: { rot: 90 }, adjust: [], layers: [{ id: "a" }] }, 4),'
		. ' bad: [M.versionOpenDoc(null, 4), M.versionOpenDoc({ v: 2, source: {}, geometry: {} }, 4), M.versionOpenDoc({ v: 1, source: {}, geometry: {}, artboard: { width: 9 } }, 4)]'
		. '}));');

	if ($node === null) {
		return;
	}

	pg_assert_false(isset($node['error']), isset($node['error']) ? $node['error'] : 'node ran');
	pg_assert_same('Version 4', $node['text'], 'the number in the label');
	pg_assert_same(array('versionOriginal', 'versionRestored', 'versionRedacted', 'versionSaved', 'versionSaved'), $node['kinds'], 'kinds to labels');
	pg_assert_same(array('09:41 · You', '09:41 · ayse'), $node['line'], 'when and who');
	pg_assert_same(4, $node['doc']['source']['fileId'], 'the file opened');
	pg_assert_false(isset($node['doc']['source']['base']), 'the base is the request\'s');
	pg_assert_same(array(), $node['doc']['adjust'], 'adjust is an object');
	pg_assert_same(90, $node['doc']['geometry']['rot'], 'the geometry stays');
	pg_assert_same(array(null, null, null), $node['bad'], 'no picture document, no document');
}
