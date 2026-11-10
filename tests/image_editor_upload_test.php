<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for large files in the image editor (pg_image_editor_upload.js, the
 * UPLOAD section of the model run under Node, and pg_image_editor_upload_*
 * on the server): the parts' order and offsets, going on after a part that
 * did not arrive, the bounds from the server's limits, a sound told by its
 * bytes, a refused size, and an upload that is stale or another user's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/image_editor_clip_test.php';

// The parts in order, each starting where the last ended; the next part
// after any count the server answers; whether a file goes in parts.
function test_image_editor_upload_parts()
{
	$r = image_editor_clip_node(
		'console.log(JSON.stringify({ parts: M.uploadParts(10, 4), exact: M.uploadParts(8, 4), none: M.uploadParts(0, 4),' . "\n"
		. ' next: [M.uploadNext(10, 4, 0), M.uploadNext(10, 4, 4), M.uploadNext(10, 4, 6), M.uploadNext(10, 4, 10), M.uploadNext(10, 4, 12), M.uploadNext(10, 0, 0), M.uploadNext(10, 4, -3)],' . "\n"
		. ' chunked: [M.uploadChunked(5, 4), M.uploadChunked(4, 4), M.uploadChunked(5, 0)],' . "\n"
		. ' fits: [M.uploadFits(5, 4), M.uploadFits(4, 4), M.uploadFits(5, 0)],' . "\n"
		. ' delay: [1, 2, 3, 4, 5, 9].map(M.uploadDelay), tries: M.UPLOAD_TRIES,' . "\n"
		. ' key: M.uploadKey("video", "7:0", { name: "a.mp4", size: 10, lastModified: 5 }), blob: M.uploadKey("animation", "7:0", { size: 10 }), empty: M.uploadKey("video", "", { name: "a.mp4", size: 0 }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('offset' => 0, 'length' => 4), array('offset' => 4, 'length' => 4), array('offset' => 8, 'length' => 2)), $r['parts'], 'parts in order, the last one short');
	pg_assert_same(2, count($r['exact']), 'a file of two whole parts');
	pg_assert_same(array(), $r['none'], 'nothing to send');
	pg_assert_same(array('offset' => 0, 'length' => 4), $r['next'][0], 'the first part');
	pg_assert_same(array('offset' => 4, 'length' => 4), $r['next'][1], 'the next part after the server\'s count');
	pg_assert_same(array('offset' => 6, 'length' => 4), $r['next'][2], 'a part that did not land in whole: on from the count');
	pg_assert_same(null, $r['next'][3], 'all there');
	pg_assert_same(null, $r['next'][4], 'past the end');
	pg_assert_same(null, $r['next'][5], 'no part size');
	pg_assert_same(array('offset' => 0, 'length' => 4), $r['next'][6], 'a count below 0 starts at 0');
	pg_assert_same(array(true, false, false), $r['chunked'], 'only a file larger than one part');
	pg_assert_same(array(false, true, true), $r['fits'], 'the largest file, 0 none known');
	pg_assert_same(array(1000, 2000, 4000, 8000, 15000, 15000), $r['delay'], 'the pauses grow to 15 s');
	pg_assert_same(6, $r['tries'], 'tries of a part with no answer');
	pg_assert_same('pgie-upload|video|7:0|a.mp4|10|5', $r['key'], 'a picked file is known again by name, size and time');
	pg_assert_same('', $r['blob'], 'bytes made here are not kept to go on');
	pg_assert_same('', $r['empty'], 'an empty file is not kept');
}

// One request's bound less the request line, at most 8 MB; the largest
// file upload_max_filesize, never below one request.
function test_image_editor_upload_limits()
{
	$mb = 1024 * 1024;
	$small = pg_image_editor_upload_limits_of(array('file_max' => 2 * $mb, 'request_max' => 3 * $mb, 'json_max' => 0), 2 * $mb);
	pg_assert_same(2 * $mb, $small['request'], 'one request: the upload limit');
	pg_assert_same(2 * $mb - 65536, $small['part'], 'a part leaves room for its line');
	pg_assert_same(2 * $mb, $small['max'], 'upload_max_filesize');

	$big = pg_image_editor_upload_limits_of(array('file_max' => 8 * $mb, 'request_max' => 64 * $mb, 'json_max' => 0), 512 * $mb);
	pg_assert_same(8 * $mb - 65536, $big['part'], 'the part follows the request');
	pg_assert_same(512 * $mb, $big['max'], 'a file larger than a request');

	$huge = pg_image_editor_upload_limits_of(array('file_max' => 100 * $mb, 'request_max' => 100 * $mb, 'json_max' => 0), 10 * $mb);
	pg_assert_same(8 * $mb, $huge['part'], 'at most 8 MB a part');
	pg_assert_same(100 * $mb, $huge['max'], 'never below one request');

	$none = pg_image_editor_upload_limits_of(array(), 0);
	pg_assert_same(array('part' => 8 * $mb, 'request' => 0, 'max' => 0), $none, 'no bound known');

	$tiny = pg_image_editor_upload_limits_of(array('file_max' => 20000), 0);
	pg_assert_same(16384, $tiny['part'], 'a part of at least 16 KB');
	pg_assert_same(20000, $tiny['max'], 'one request when upload_max_filesize is not known');
}

// The id, the state of a kept upload, the check of each part.
function test_image_editor_upload_checks()
{
	pg_assert_true(pg_image_editor_upload_id_ok(str_repeat('a1', 16)), 'hex id');
	pg_assert_false(pg_image_editor_upload_id_ok('../' . str_repeat('a', 29)), 'a path is no id');
	pg_assert_false(pg_image_editor_upload_id_ok(str_repeat('A', 32)), 'upper case');
	pg_assert_false(pg_image_editor_upload_id_ok(7), 'a number');

	$meta = array('user' => 5, 'kind' => 'video', 'size' => 100);
	pg_assert_same('', pg_image_editor_upload_state($meta, 5, 1000, 1500, 86400), 'own and recent');
	pg_assert_same('foreign', pg_image_editor_upload_state($meta, 6, 1000, 1500, 86400), 'another user\'s upload is refused');
	pg_assert_same('stale', pg_image_editor_upload_state($meta, 5, 1000, 1000 + 86401, 86400), 'nothing added for a day');
	pg_assert_same('gone', pg_image_editor_upload_state(null, 5, 1000, 1500, 86400), 'no upload');
	pg_assert_same('gone', pg_image_editor_upload_state(array('user' => 5, 'kind' => 'exe', 'size' => 100), 5, 1000, 1500, 86400), 'an unknown kind');
	pg_assert_same('gone', pg_image_editor_upload_state(array('user' => '5', 'kind' => 'video', 'size' => 100), 5, 1000, 1500, 86400), 'a user id as text');

	pg_assert_same('', pg_image_editor_upload_part_check(10, 4, 4, 4, 4), 'the next part');
	pg_assert_same('', pg_image_editor_upload_part_check(10, 8, 8, 2, 4), 'the short last part');
	pg_assert_same('offset', pg_image_editor_upload_part_check(10, 4, 0, 4, 4), 'a part sent twice');
	pg_assert_same('offset', pg_image_editor_upload_part_check(10, 4, 8, 2, 4), 'a part after a missing one');
	pg_assert_same('size', pg_image_editor_upload_part_check(10, 8, 8, 4, 4), 'past the file\'s size');
	pg_assert_same('size', pg_image_editor_upload_part_check(10, 0, 0, 5, 4), 'longer than a part');
	pg_assert_same('size', pg_image_editor_upload_part_check(10, 0, 0, 0, 4), 'an empty part');

	pg_assert_same(array('video' => 'video_upload', 'sound' => 'sound_upload', 'animation' => 'animation'), pg_image_editor_upload_kinds(), 'the kinds and their actions');
}

// A sound by its bytes, never its name; each kind's format.
function test_image_editor_upload_types()
{
	$pad = str_repeat("\x00", 16);
	pg_assert_same('mp3', pg_image_editor_sound_format('ID3' . "\x04\x00" . $pad), 'an ID3 tag');
	pg_assert_same('mp3', pg_image_editor_sound_format("\xff\xfb\x90\x64" . $pad), 'an MPEG audio frame');
	pg_assert_same('aac', pg_image_editor_sound_format("\xff\xf1\x50\x80" . $pad), 'an ADTS frame');
	pg_assert_same('wav', pg_image_editor_sound_format('RIFF' . "\x24\x00\x00\x00" . 'WAVEfmt ' . $pad), 'RIFF WAVE');
	pg_assert_same('opus', pg_image_editor_sound_format('OggS' . "\x00\x02" . $pad . 'OpusHead' . $pad), 'Ogg with Opus');
	pg_assert_same('ogg', pg_image_editor_sound_format('OggS' . "\x00\x02" . $pad . "\x01vorbis" . $pad), 'Ogg Vorbis');
	pg_assert_same('m4a', pg_image_editor_sound_format("\x00\x00\x00\x20ftypM4A " . $pad), 'an ISO file');
	pg_assert_same('', pg_image_editor_sound_format('RIFF' . "\x24\x00\x00\x00" . 'WEBPVP8 ' . $pad), 'a WebP picture is no sound');
	pg_assert_same('', pg_image_editor_sound_format("\xff\xe0\x00\x00" . $pad), 'a frame sync of layer 00 that is no ADTS');
	pg_assert_same('', pg_image_editor_sound_format('<?php echo 1; ?>' . $pad), 'a script');
	pg_assert_same('', pg_image_editor_sound_format('ID3'), 'too short');

	pg_assert_same('mp4', pg_image_editor_upload_format('video', "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2"), 'a video');
	pg_assert_same('', pg_image_editor_upload_format('video', 'GIF89a' . $pad), 'an animation is not a video');
	pg_assert_same('gif', pg_image_editor_upload_format('animation', 'GIF89a' . $pad), 'an animation');
	pg_assert_same('wav', pg_image_editor_upload_format('sound', 'RIFF' . "\x24\x00\x00\x00" . 'WAVEfmt ' . $pad), 'a sound');
	pg_assert_same('', pg_image_editor_upload_format('exe', 'MZ' . $pad), 'no other kind');
}

// Start, a part, a part after a missing one, the status, another user, the
// first part told by its bytes, a size over the largest file.
function test_image_editor_upload_actions()
{
	$user = array('id' => 990001, 'role' => 0);
	$other = array('id' => 990002, 'role' => 0);
	$limits = pg_image_editor_upload_limits();
	$head = "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2";
	$size = strlen($head) + 40;

	list($start, $code) = pg_image_editor_action_upload_start(array('kind' => 'video', 'size' => $size), $user);

	if (($code !== 200) || ($start['status'] !== 'success')) {
		// A checkout whose data/temp is not writable cannot keep parts.
		pg_assert_same('Sorry, the file could not be saved.', $start['message'], 'only a folder that takes no file stops the start');
		return;
	}

	$id = $start['upload'];
	pg_assert_true(pg_image_editor_upload_id_ok($id), 'an id');
	pg_assert_same(0, $start['received'], 'nothing yet');
	pg_assert_same($limits['part'], $start['part'], 'the part size');

	list($r, $code) = pg_image_editor_action_upload_part(array('upload' => $id, 'offset' => 0, 'binary' => $head), $user);
	pg_assert_same(200, $code, 'the first part');
	pg_assert_same(strlen($head), $r['received'], 'its bytes counted');

	list($r, $code) = pg_image_editor_action_upload_part(array('upload' => $id, 'offset' => 0, 'binary' => $head), $user);
	pg_assert_same(409, $code, 'the same part again');
	pg_assert_same(strlen($head), $r['received'], 'answered with the count to go on from');

	list($r, $code) = pg_image_editor_action_upload_part(array('upload' => $id, 'offset' => strlen($head) + 20, 'binary' => str_repeat('x', 20)), $user);
	pg_assert_same(409, $code, 'a part after a missing one');
	pg_assert_same('upload_offset', $r['code'], 'told where to go on');

	list($r, $code) = pg_image_editor_action_upload_status(array('upload' => $id), $user);
	pg_assert_same(array(200, strlen($head), $size, 'video'), array($code, $r['received'], $r['size'], $r['kind']), 'the status after a reload');

	list($r, $code) = pg_image_editor_action_upload_part(array('upload' => $id, 'offset' => strlen($head), 'binary' => str_repeat('x', 40)), $user);
	pg_assert_same(array(200, $size), array($code, $r['received']), 'the missing part, then all there');

	list($r, $code) = pg_image_editor_action_upload_part(array('upload' => $id, 'offset' => $size, 'binary' => 'x'), $user);
	pg_assert_same(400, $code, 'nothing past the size');

	list($r, $code) = pg_image_editor_action_upload_status(array('upload' => $id), $other);
	pg_assert_same(403, $code, 'another user\'s upload');
	list($r, $code) = pg_image_editor_action_upload_part(array('upload' => $id, 'offset' => $size, 'binary' => 'x'), $other);
	pg_assert_same(403, $code, 'another user adds nothing');

	list($refusal, $first, $taken, $taken_id) = pg_image_editor_upload_take(array('upload' => $id), $user, 'video');
	pg_assert_same(array(null, $size, $id), array($refusal, $taken, $taken_id), 'a finished upload is taken');
	pg_assert_same('mp4', pg_image_editor_clip_format($first), 'its first bytes tell its type');
	list($refusal) = pg_image_editor_upload_take(array('upload' => $id), $user, 'sound');
	pg_assert_same(409, $refusal[1], 'not as another kind');

	// Stale: its bytes last written two days ago.
	list($part) = pg_image_editor_upload_paths($id);
	@touch($part, time() - 2 * 86400);
	list($r, $code) = pg_image_editor_action_upload_status(array('upload' => $id), $user);
	pg_assert_same(array(410, 'upload_gone'), array($code, $r['code']), 'a stale upload is refused');
	pg_assert_false(is_file($part), 'and removed');

	list($start) = pg_image_editor_action_upload_start(array('kind' => 'sound', 'size' => 100), $user);
	list($r, $code) = pg_image_editor_action_upload_part(array('upload' => $start['upload'], 'offset' => 0, 'binary' => '<?php echo 1; ?>' . str_repeat(' ', 84)), $user);
	pg_assert_same('Only MP3, AAC, M4A, WAV and Ogg sounds can be added.', $r['message'], 'the first part tells the type');
	list($r, $code) = pg_image_editor_action_upload_status(array('upload' => $start['upload']), $user);
	pg_assert_same(410, $code, 'a refused type leaves nothing');

	list($r, $code) = pg_image_editor_action_upload_start(array('kind' => 'video', 'size' => 0), $user);
	pg_assert_same('error', $r['status'], 'an empty file');
	list($r, $code) = pg_image_editor_action_upload_start(array('kind' => 'script', 'size' => 10), $user);
	pg_assert_same('error', $r['status'], 'an unknown kind');

	if ($limits['max'] > 0) {
		list($r, $code) = pg_image_editor_action_upload_start(array('kind' => 'video', 'size' => $limits['max'] + 1), $user);
		pg_assert_same(413, $code, 'over the largest file');
	}

	list($r, $code) = pg_image_editor_action_upload_status(array('upload' => '../../config'), $user);
	pg_assert_same('error', $r['status'], 'a path is no upload');
}
