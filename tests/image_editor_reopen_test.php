<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a video project saved into Files and opened again (V9 of the
 * image editor's video plan): the clips and sounds pointed at their files'
 * addresses by fileId, the pictures from this computer kept as files by an
 * animation save, the File Manager's test for a video the editor made, and
 * the kept document coming back whole.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A canvas with two scenes: a clip in the open one, a clip in the closed
// one, a picture from Files and a detached sound plus music.
function image_editor_reopen_doc()
{
	return json_decode('{"v":1,"artboard":{"width":1920,"height":1080,"background":"#000000"},'
		. '"layers":['
		. '{"id":"c1","type":"video","fileId":11,"name":"beach.mp4","src":"/beach.mp4","length":9000,"from":0,"to":4000,"start":0,"speed":1,"volume":1,"muted":true,"fx":{"brightness":20,"blur":10},"transition":{"kind":"fade","ms":500},"x":0,"y":0,"w":1920,"h":1080,"rot":0},'
		. '{"id":"p1","type":"image","fileId":12,"src":"/logo.png","x":10,"y":10,"w":100,"h":100,"rot":0}],'
		. '"scenes":[{"id":"s1","name":"","transition":{"kind":"fade","ms":600}},'
		. '{"id":"s2","name":"","transition":{"kind":"none","ms":600},"layers":[{"id":"c2","type":"video","fileId":13,"name":"city.webm","src":"/city.webm","length":5000,"from":500,"to":3500,"start":0,"speed":2,"volume":0.5,"muted":false,"x":0,"y":0,"w":1920,"h":1080,"rot":0}]}],'
		. '"scene":"s1",'
		. '"sounds":[{"id":"snd1","fileId":11,"name":"beach.mp4","src":"/beach.mp4","length":9000,"start":0,"from":0,"to":4000,"volume":1},'
		. '{"id":"snd2","fileId":0,"name":"","src":"/music.mp3","length":30000,"start":0,"from":0,"to":9000,"volume":0.4}]}');
}

// The clips of every scene and the sounds with a file id; nothing else.
function test_image_editor_reopen_media_of_a_document()
{
	$ids = array_map(function ($item) { return $item->id; }, pg_image_editor_doc_media(image_editor_reopen_doc()));

	pg_assert_same(array('c1', 'c2', 'snd1'), $ids, 'two clips and one sound name a file; the picture and a sound without an id do not');
	pg_assert_same(array(), pg_image_editor_doc_media(null), 'no document');
	pg_assert_same(array(), pg_image_editor_doc_media(json_decode('{"layers":[{"id":"x","type":"video","fileId":"a","src":"/a.mp4"}]}')), 'a fileId that is no number');
}

// A source renamed after the save: its clip and its sound take the file's
// address now; a file not found keeps the saved one.
function test_image_editor_reopen_renamed_source()
{
	$doc = image_editor_reopen_doc();
	$changed = pg_image_editor_doc_media_named($doc, array(11 => array('url' => '/beach-2026.mp4', 'name' => 'beach-2026.mp4'), 99 => array('url' => '/other.mp4', 'name' => 'other.mp4')));

	pg_assert_same(2, $changed, 'the clip and its detached sound');
	pg_assert_same('/beach-2026.mp4', $doc->layers[0]->src, 'the clip');
	pg_assert_same('beach-2026.mp4', $doc->layers[0]->name, 'under its new name');
	pg_assert_same('/beach-2026.mp4', $doc->sounds[0]->src, 'the sound on the same file');
	pg_assert_same('beach-2026.mp4', $doc->sounds[0]->name, 'the sound too');
	pg_assert_same('/city.webm', $doc->scenes[1]->layers[0]->src, 'a file not in the list keeps its address');
	pg_assert_same('/logo.png', $doc->layers[1]->src, 'a picture is left alone');
	pg_assert_same(0, pg_image_editor_doc_media_named($doc, array(11 => array('url' => '/beach-2026.mp4', 'name' => 'beach-2026.mp4'))), 'nothing changes twice');
	pg_assert_same(0, pg_image_editor_doc_media_named($doc, array(13 => array('url' => '', 'name' => 'x.webm'))), 'an empty address is no address');
	pg_assert_same('city.webm', $doc->scenes[1]->layers[0]->name, 'nor a new name');
}

// The document an animation's version keeps comes back whole: clips with
// their trims, speed, volume and effects, scenes with their transitions,
// sounds with their fades.
function test_image_editor_reopen_document_kept_whole()
{
	$doc = image_editor_reopen_doc();
	$json = pg_image_editor_animation_doc_json($doc, 0, 0, 'mp4');
	$kept = json_decode($json, true);

	pg_assert_true($json !== '', 'kept');
	unset($kept['animation']);
	pg_assert_same(json_decode(json_encode(image_editor_reopen_doc()), true), $kept, 'every member as saved');

	$doc->layers[1]->src = 'data:image/png;base64,iVBORw0KGgo=';
	pg_assert_same('', pg_image_editor_animation_doc_json($doc, 0, 0, 'mp4'), 'a picture still in the document is not kept: it goes to Files first');
}

// The File Manager offers the editor on an MP4 or WebM whose newest version
// action animation wrote; a GIF opens as a picture anyway, a still save's
// document names nothing.
function test_image_editor_reopen_video_project()
{
	$json = function ($format) {
		return pg_image_editor_animation_doc_json(image_editor_reopen_doc(), 0, 0, $format);
	};

	pg_assert_true(pg_image_editor_video_project(array('source' => 'animation', 'doc_json' => $json('mp4'))), 'MP4');
	pg_assert_true(pg_image_editor_video_project(array('source' => 'animation', 'doc_json' => $json('webm'))), 'WebM');
	pg_assert_false(pg_image_editor_video_project(array('source' => 'animation', 'doc_json' => $json('gif'))), 'a GIF is a picture');
	pg_assert_false(pg_image_editor_video_project(array('source' => 'save', 'doc_json' => $json('mp4'))), 'a row the browser wrote');
	pg_assert_false(pg_image_editor_video_project(array('source' => 'animation', 'doc_json' => '')), 'no document');
	pg_assert_false(pg_image_editor_video_project(null), 'no version');
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_reopen_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiereopen');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// An animation's save sends the pictures from this computer of every scene
// and points their layers at the files it kept.
function test_image_editor_reopen_pictures_go_along()
{
	$r = image_editor_reopen_node(
		'var png = "data:image/png;base64,iVBORw0KGgo=", doc = { layers: [{ id: "a", type: "image", src: png, fileName: "me.png" }, { id: "b", type: "image", src: "/x.png", fileId: 4 }, { id: "c", type: "video", src: "/v.mp4", fileId: 5 }],' . "\n"
		. ' scenes: [{ id: "s1" }, { id: "s2", layers: [{ id: "d", type: "image", src: "data:image/jpeg;base64,/9j/" }, { id: "e", type: "image", src: "data:image/gif;base64,R0lG" }] }] };' . "\n"
		. 'var list = M.animPictures(doc).map(function (p) { return [p.id, p.name]; });' . "\n"
		. 'var n = M.picturesTaken(doc, { a: { id: 21, url: "/me.png" }, d: { id: 22, url: "/d.jpg" } });' . "\n"
		. 'console.log(JSON.stringify({ list: list, n: n, a: doc.layers[0], d: doc.scenes[1].layers[0], none: M.animPictures(null), nothing: M.picturesTaken(doc, null) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('a', 'me.png'), array('d', '')), $r['list'], 'PNG and JPEG pictures without a file, in a closed scene too');
	pg_assert_same(2, $r['n'], 'two layers point at their files');
	pg_assert_same(array('id' => 'a', 'type' => 'image', 'src' => '/me.png', 'fileId' => 21), $r['a'], 'the open scene\'s picture');
	pg_assert_same(array('id' => 'd', 'type' => 'image', 'src' => '/d.jpg', 'fileId' => 22), $r['d'], 'a closed scene\'s picture');
	pg_assert_same(array(), $r['none'], 'no document');
	pg_assert_same(0, $r['nothing'], 'nothing kept');
}

// The source of the paths no unit reaches: the save keeps the pictures
// before the document, the open reads the addresses again, the editor sends
// and takes the pictures, the File Manager offers the editor on a project.
function test_image_editor_reopen_wired()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');
	$save = substr($php, strpos($php, 'function pg_image_editor_action_animation('));
	$open = substr($php, strpos($php, 'function pg_image_editor_animation_open('));
	$open = substr($open, 0, strpos($open, "\n}\n"));
	$kept = strpos($save, 'pg_image_editor_pictures_saved($record, $request, $folder, $user)');
	$json = strpos($save, 'pg_image_editor_animation_doc_json($doc,');

	pg_assert_true(($kept !== false) && ($json !== false) && ($kept < $json), 'an animation save keeps the pictures before the document');
	pg_assert_true(strpos($open, 'pg_image_editor_doc_media_fresh($made[\'doc\'], $user)') !== false, 'the open reads the addresses again');

	// A video opened as its own canvas (no saved canvas) is no picture:
	// its replace takes the animation's own row before the picture check.
	$canvas = substr($save, strpos($save, '} elseif ($file_id > 0) {'), 1400);
	$self = strpos($canvas, 'if ($self && pg_image_editor_canvas_is_doc($self[\'doc\'])) {');
	$picture = strpos($canvas, 'pg_image_editor_file($file_id, $user)');

	pg_assert_true(($self !== false) && ($picture !== false) && ($self < $picture), 'a video made on an unsaved canvas is replaced without the picture check');
	pg_assert_true(strpos($canvas, 'pg_files_include_erp_document(array($file_id))') !== false, 'an ERP document stays read-only');
	pg_assert_true(strpos($open, 'pg_image_editor_doc_media_fresh') < strpos($open, "'doc'       => \$made['doc']"), 'before the document is answered');

	$render = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_render.js');
	$send = substr($render, strpos($render, 'Editor.prototype.saveAnimationFile'), 1600);

	pg_assert_true(strpos($send, 'M.animPictures(this.doc)') !== false && strpos($send, 'head.pictures = pictures') !== false, 'Save to Files sends the pictures');
	pg_assert_true(strpos($send, 'M.picturesTaken(self.doc, r.pictures)') !== false, 'and takes the files back');

	$window = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_window.php');
	$folders = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	pg_assert_true(strpos($window, "pg_image_editor_video_project(pg_file_versions_newest((int) \$file['id']))") !== false, 'the file window tells a video project');
	pg_assert_true(strpos($folders, "((kind === 'video') && file.editor_project)") !== false, 'and offers Image Editor on it');
}
