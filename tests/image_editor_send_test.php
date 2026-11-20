<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Artifact Canvas's main button as a split button: which
 * choices its menu offers (M.sendMenu(), the SEND section of
 * assets/js/pg_image_editor_model.js, run under Node), the channels a send
 * offers, the downloaded file's name, and the callers that hand the editor
 * their channels: a channel's canvas in the Workspace and the File Manager
 * only with the Workspace on.
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
function image_editor_send_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiesend');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// From a channel: another channel, Save to Files as a picture and as a
// video, Download. The video only while something moves; Save to Files only
// with a folder the caller gives; a send only when the caller can send.
function test_image_editor_send_menu_from_a_channel()
{
	$r = image_editor_send_node(
		'console.log(JSON.stringify({'
		. ' all: M.sendMenu({ channel: true, send: true, files: true, motion: true, artboard: true }),'
		. ' still: M.sendMenu({ channel: true, send: true, files: true, motion: false, artboard: true }),'
		. ' noFolder: M.sendMenu({ channel: true, send: true, motion: true, artboard: true }),'
		. ' noSend: M.sendMenu({ channel: true, send: false, artboard: true }),'
		. ' photo: M.sendMenu({ channel: true, send: true, artboard: false }),'
		. ' none: M.sendMenu() }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('sendOther', 'savePicture', 'saveVideo', 'download'), $r['all'], 'every choice, in order');
	pg_assert_same(array('sendOther', 'savePicture', 'download'), $r['still'], 'the video choice only with motion');
	pg_assert_same(array('sendOther', 'download'), $r['noFolder'], 'Save to Files only with a folder given');
	pg_assert_same(array('download'), $r['noSend'], 'no channel without the caller\'s channels');
	pg_assert_same(array(), $r['photo'], 'a photo is no canvas: nothing to send or draw');
	pg_assert_same(array(), $r['none'], 'no context, no choice');
}

// From the File Manager: Save as a copy when Save writes over the file, and
// "Send to a channel as a canvas..." only with the Workspace on (the caller
// gives its channels) and only for a canvas.
function test_image_editor_send_menu_from_files()
{
	$r = image_editor_send_node(
		'console.log(JSON.stringify({'
		. ' replace: M.sendMenu({ replace: true, send: true, artboard: true }),'
		. ' off: M.sendMenu({ replace: true, send: false, artboard: true }),'
		. ' photo: M.sendMenu({ replace: true, send: true, artboard: false }),'
		. ' fresh: M.sendMenu({ send: true, artboard: true }),'
		. ' freshOff: M.sendMenu({ send: false, artboard: true }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('saveCopy', 'sendChannel'), $r['replace'], 'a canvas in Files with the Workspace on');
	pg_assert_same(array('saveCopy'), $r['off'], 'the Workspace off: no send');
	pg_assert_same(array('saveCopy'), $r['photo'], 'a photo is not sent as a canvas');
	pg_assert_same(array('sendChannel'), $r['fresh'], 'a new canvas has the send alone');
	pg_assert_same(array(), $r['freshOff'], 'a new canvas without the Workspace has no menu');
}

// The channels offered: what the server gave, without the canvas's own
// channel, a row marked post false, one without an id or one twice; the
// find field folds case and accents.
function test_image_editor_send_channels()
{
	$r = image_editor_send_node(
		'var list = [{ id: 4, name: "General", icon: "x" }, { id: 7, name: "Read only", post: false }, { id: "9", name: "Işık" }, { name: "No id" }, { id: 4, name: "General" }, { id: 12, name: "Design" }];' . "\n"
		. 'console.log(JSON.stringify({'
		. ' here: M.sendChannels(list, 12),'
		. ' files: M.sendChannels(list).map(function (c) { return c.id; }),'
		. ' junk: M.sendChannels(null, 1),'
		. ' found: M.sendFilter(M.sendChannels(list), "ISI").map(function (c) { return c.id; }),'
		. ' all: M.sendFilter(M.sendChannels(list), " ").length,'
		. ' names: [M.sendFileName("Poster.png"), M.sendFileName("a/b: c"), M.sendFileName("")] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('id' => 4, 'name' => 'General'), array('id' => 9, 'name' => 'Işık')), $r['here'], 'not the channel it is posted in, nor one the person may not write in');
	pg_assert_same(array(4, 9, 12), $r['files'], 'from Files every channel given, once');
	pg_assert_same(array(), $r['junk'], 'no list');
	pg_assert_same(array(9), $r['found'], 'found by folded words');
	pg_assert_same(3, $r['all'], 'no words: all of them');
	pg_assert_same(array('Poster.png', 'a b c.png', 'canvas.png'), $r['names'], 'the downloaded file\'s name');
}

// The callers: a channel's canvas hands its channels and leaves its own out;
// the File Manager hands them only with the Workspace on.
function test_image_editor_send_callers()
{
	$ws = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$fm = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	pg_assert_true(strpos($ws, 'send: self.canvasSend(channel)') !== false, 'New canvas in a channel hands its channels');
	pg_assert_true(strpos($ws, "api('ws_library_channels'") !== false, 'the channels the person may write in');
	pg_assert_true(strpos($ws, 'current: channel ? channel.id : 0') !== false, 'its own channel left out');
	pg_assert_true(preg_match('/function canvasSend\(\) \{\s*if \(!wsSend\) \{ return null; \}/', $fm) === 1, 'File Manager: only with the Workspace on');
	pg_assert_same(2, substr_count($fm, 'send: canvasSend(),'), 'File Manager: a file and New image');
}

// Every word the menu shows is a translated label.
function test_image_editor_send_labels()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');

	$labels = pg_image_editor_strings_core();

	foreach (array('sendOther', 'sendChannel', 'download', 'sendFind', 'sendLoading', 'sendNone', 'sendError', 'sendSent', 'saveCopy') as $key) {
		pg_assert_true(isset($labels[$key]) && ($labels[$key] !== ''), 'label ' . $key);
	}

	pg_assert_true(strpos($labels['sendSent'], '{name}') !== false, 'the channel named in the sent line');
}
