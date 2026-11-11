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
		. '{"id":"c1","type":"video","fileId":11,"name":"beach.mp4","src":"/beach.mp4","length":9000,"from":0,"to":4000,"start":0,"speed":1,"volume":1,"muted":true,"effects":{"brightness":20},"x":0,"y":0,"w":1920,"h":1080,"rot":0},'
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
	$changed = pg_image_editor_doc_media_named($doc, array(11 => '/beach-2026.mp4', 99 => '/other.mp4'));

	pg_assert_same(2, $changed, 'the clip and its detached sound');
	pg_assert_same('/beach-2026.mp4', $doc->layers[0]->src, 'the clip');
	pg_assert_same('/beach-2026.mp4', $doc->sounds[0]->src, 'the sound on the same file');
	pg_assert_same('/city.webm', $doc->scenes[1]->layers[0]->src, 'a file not in the list keeps its address');
	pg_assert_same('/logo.png', $doc->layers[1]->src, 'a picture is left alone');
	pg_assert_same(0, pg_image_editor_doc_media_named($doc, array(11 => '/beach-2026.mp4')), 'nothing changes twice');
	pg_assert_same(0, pg_image_editor_doc_media_named($doc, array(13 => '')), 'an empty address is no address');
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
