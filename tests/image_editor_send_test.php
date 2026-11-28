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
// for someone who may edit a folder; a send only when the caller can send.
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
	pg_assert_same(array('sendOther', 'download'), $r['noFolder'], 'a member who may edit no folder: no Save to Files');
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
		. ' photoDrawn: M.sendMenu({ replace: true, send: true, artboard: false, convert: true }),'
		. ' photoOff: M.sendMenu({ replace: true, send: false, artboard: false, convert: true }),'
		. ' fresh: M.sendMenu({ send: true, artboard: true }),'
		. ' freshOff: M.sendMenu({ send: false, artboard: true }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array('saveCopy', 'sendChannel'), $r['replace'], 'a canvas in Files with the Workspace on');
	pg_assert_same(array('saveCopy'), $r['off'], 'the Workspace off: no send');
	pg_assert_same(array('saveCopy'), $r['photo'], 'a photo the server cannot draw is not sent');
	pg_assert_same(array('saveCopy', 'sendChannel'), $r['photoDrawn'], 'a photo of Files is sent as a canvas too');
	pg_assert_same(array('saveCopy'), $r['photoOff'], 'a photo without the Workspace: no send');
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
	pg_assert_same(3, substr_count($fm, 'send: canvasSend(),'), 'File Manager: a file, New image and a presentation opened in the canvas');
}

// Every word the menu shows is a translated label.
function test_image_editor_send_labels()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');

	$labels = pg_image_editor_strings_core();

	foreach (array('sendOther', 'sendChannel', 'download', 'sendFind', 'sendLoading', 'sendNone', 'sendError', 'sendSent', 'saveCopy', 'savePicture', 'saveVideo', 'filesFolder', 'filesLoading', 'filesNone', 'filesError', 'filesNext', 'filesSaved', 'filesOpen') as $key) {
		pg_assert_true(isset($labels[$key]) && ($labels[$key] !== ''), 'label ' . $key);
	}

	pg_assert_true(strpos($labels['sendSent'], '{name}') !== false, 'the channel named in the sent line');
}

// The folder last chosen comes back from this browser's storage, a storage
// that throws or holds junk picks none; the list indents by depth.
function test_image_editor_send_files_remembered()
{
	$r = image_editor_send_node(
		'var store = {}; M.filesRemember(31, function (k, v) { store[k] = v; }); M.filesRemember(0, function (k, v) { store.zero = v; });' . "\n"
		. 'console.log(JSON.stringify({'
		. ' back: M.filesRemembered(function (k) { return store[k]; }),'
		. ' zero: store.zero === undefined,'
		. ' junk: M.filesRemembered(function () { return "x"; }),'
		. ' none: M.filesRemembered(function () { return null; }),'
		. ' indent: [M.filesIndent(0), M.filesIndent(2).length, M.filesIndent(-3), M.filesIndent(99).length] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(31, $r['back'], 'the last folder picked again');
	pg_assert_true($r['zero'], 'no folder is not remembered');
	pg_assert_same(0, $r['junk'], 'junk picks none');
	pg_assert_same(0, $r['none'], 'nothing kept picks none');
	pg_assert_same(array('', 4, '', 24), $r['indent'], 'two spaces a level, at most twelve levels');
}

// Who is offered Save to Files: staff always, a user granted edit rights to
// a folder, nobody else; the folder list refuses the others (403).
function test_image_editor_send_files_rights()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');

	$asked = 0;
	$none = function () use (&$asked) {
		$asked++;

		return 0;
	};
	$two = function () {
		return 2;
	};

	pg_assert_true(pg_image_editor_files_edit(array('id' => 5, 'role' => 1), $none), 'staff');
	pg_assert_same(0, $asked, 'staff are not looked up');
	pg_assert_false(pg_image_editor_files_edit(array('id' => 5, 'role' => 3), $none), 'a member who may edit no folder');
	pg_assert_true(pg_image_editor_files_edit(array('id' => 5, 'role' => 3), $two), 'a user granted edit rights');

	list($payload, $status) = pg_image_editor_action_folders(array('action' => 'folders'), array('id' => 5, 'role' => 3), $none);

	pg_assert_same(403, $status, 'the folder list refused without edit rights');
	pg_assert_same('error', $payload['status'] ?? '', 'an error answer');

	$editor = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor.js');

	pg_assert_true(strpos($editor, "files: !!config().filesEdit && !!config().api,") !== false, 'the menu follows the config\'s right');
}

// The folders offered: editable ones in the tree's order, their depth
// counting the listed folders above them; the bin's and an archived
// folder's branches never.
function test_image_editor_send_folder_choices()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');

	// 1 Files > 2 Pictures > 3 Shoot ; 1 > 4 Recycle Bin > 5 Old ; 1 > 6 Archive > 7 Inside ; 8 Other (top)
	$folders = array(
		1 => array('parent' => 0, 'name' => 'Files', 'order' => 0),
		2 => array('parent' => 1, 'name' => 'Pictures', 'order' => 0),
		3 => array('parent' => 2, 'name' => 'Shoot', 'order' => 0),
		4 => array('parent' => 1, 'name' => 'Recycle Bin', 'order' => 0),
		5 => array('parent' => 4, 'name' => 'Old', 'order' => 0),
		6 => array('parent' => 1, 'name' => 'Archive', 'order' => 0),
		7 => array('parent' => 6, 'name' => 'Inside', 'order' => 0),
		8 => array('parent' => 0, 'name' => 'Other', 'order' => 0),
	);
	$all = pg_image_editor_folder_choices($folders, function () { return true; }, array(4, 6));

	pg_assert_same(array(1, 2, 3, 8), array_column($all, 'id'), 'the bin and the archived branch left out');
	pg_assert_same(array(0, 1, 2, 0), array_column($all, 'depth'), 'depth by the tree');

	// A user granted Pictures only: it stands at the top.
	$granted = pg_image_editor_folder_choices($folders, function ($id) { return in_array($id, array(2, 3, 5), true); }, array(4, 6));

	pg_assert_same(array(array('id' => 2, 'name' => 'Pictures', 'depth' => 0), array('id' => 3, 'name' => 'Shoot', 'depth' => 1)), $granted, 'only editable folders, never one in the bin');
	pg_assert_same(array(), pg_image_editor_folder_choices($folders, function () { return false; }, array()), 'none editable');
}

// The saves themselves ask again of the folder chosen: edit access and the
// bin for a picture and for a video, folder 0 refused.
function test_image_editor_send_files_saves_check_the_folder()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/image_editor.php');
	$canvas = substr($php, (int) strpos($php, 'function pg_image_editor_save_canvas('), 1500);

	pg_assert_true(strpos($canvas, 'if ($folder <= 0)') !== false, 'a picture: folder 0 refused');
	pg_assert_true(strpos($canvas, "if (!check_edit_access(\$folder) || pg_folder_binned(\$folder)) {\n            return pg_image_editor_refusal(lang('Access denied'), 403);") !== false, 'a picture: edit access and the bin, 403');

	$render = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_render.js');

	pg_assert_true(strpos($render, 'folder_id: this.filesFolder ? this.filesFolder.id :') !== false, 'a video goes to the folder chosen');
}

// A part that wraps send() hands its target on (another channel, a folder
// of Files): dropping it posted a send into the canvas's own channel.
function test_image_editor_send_wrappers_keep_the_target()
{
	foreach (glob(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor*.js') as $file) {
		if (substr($file, -7) === '.min.js') {
			continue;
		}

		$js = (string) file_get_contents($file);

		if (preg_match_all('/var (\\w+) = Editor\\.prototype\\.send;/', $js, $m)) {
			foreach ($m[1] as $name) {
				pg_assert_true(strpos($js, $name . '.apply(this, arguments)') !== false, basename($file) . ' hands every argument of send() on');
				pg_assert_true(strpos($js, $name . '.call(this, mode)') === false, basename($file) . ' does not drop the target');
			}
		}
	}
}

// A photo sent as a canvas: refused with its own sentence when its picture is
// larger than a Workspace file may be, before anything is posted; nothing
// asked when no photo is named.
function test_image_editor_send_photo_refusal()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');

	$small = array('id' => 'p1', 'data' => 'data:image/jpeg;base64,' . base64_encode(str_repeat('x', 3000)), 'name' => 'cover-Background.jpg');
	$big = array('id' => 'p1', 'data' => 'data:image/jpeg;base64,' . base64_encode(str_repeat('x', 3 * 1048576)), 'name' => 'cover-Background.jpg');
	$other = array('id' => 'l7', 'data' => 'data:image/png;base64,' . base64_encode(str_repeat('y', 3 * 1048576)), 'name' => 'pasted.png');

	pg_assert_same('', ws_canvas_photo_refusal(array($small, $other), 'p1', 2 * 1048576), 'a photo that fits goes; another picture is not asked');
	pg_assert_true(strpos(ws_canvas_photo_refusal(array($big), 'p1', 2 * 1048576), '2 MB') !== false, 'too large: refused, naming the size');
	pg_assert_same('', ws_canvas_photo_refusal(array($big), '', 2 * 1048576), 'no photo named: nothing refused');
	pg_assert_true(ws_canvas_photo_refusal(array($other), 'p1', 2 * 1048576) !== '', 'a photo named but not carried is refused');

	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_file_save.php');
	$channel = substr($endpoint, (int) strpos($endpoint, "if (isset(\$_GET['channel']))"));

	pg_assert_true(strpos($channel, 'ws_canvas_photo_refusal(') !== false && (strpos($channel, 'ws_canvas_photo_refusal(') < strpos($channel, 'ws_message_send_file_bytes(')), 'checked before the post');
}

// A photo's copy: the open document is left as it is, and a copy sent
// elsewhere points none of its layers at the files written there.
function test_image_editor_send_photo_copy_leaves_the_open_file()
{
	$artboard = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_artboard.js');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor.js');

	pg_assert_true(strpos($artboard, 'doc = M.pictureLayer(before, pic.url,') !== false, 'the copy is made from a clone, as Convert to a layer makes it');
	pg_assert_true(strpos($core, "self.send('url', { url: self.opts.send.url(channel.id), name: channel.name, doc: copy.doc, photo: copy.photo });") !== false, 'the copy and its photo layer go to the channel');
	pg_assert_true(preg_match('/if \(to\) \{[^}]*sendSent[\s\S]*?return;\s*\}\s*\/\/ Pictures the save kept as files/', $core) === 1, 'a send elsewhere returns before the open document takes the files');

	$r = image_editor_send_node(
		'var photo = { v: 1, source: { fileId: 3, w: 400, h: 300 }, layers: [{ id: "t1", type: "text", x: 10, y: 10, w: 50, h: 20, text: "Hi" }], geometry: { rot: 0 } };' . "\n"
		. 'var before = JSON.stringify(photo), d;' . "\n"
		. 'try { d = M.pictureLayer(photo, "data:image/jpeg;base64,AAAA", "cover-Background.jpg"); } catch (e) { d = null; }' . "\n"
		. 'console.log(JSON.stringify({ same: JSON.stringify(photo) === before, artboard: !!(d && d.artboard), bottom: d ? d.layers[0].type + ":" + d.layers[0].src.slice(0, 15) : "", kept: d ? d.layers.length : 0 }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_true($r['same'], 'the photo\'s document is not changed');
	pg_assert_true($r['artboard'], 'the copy is a canvas');
	pg_assert_same('image:data:image/jpeg', $r['bottom'], 'the photo is its bottom layer');
	pg_assert_same(2, $r['kept'], 'the edits stay above it');
}

// Under 576 px the bar's four secondary buttons show their glyph only and
// keep their name; nothing changes from 576 px up.
function test_image_editor_send_narrow_top_bar()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_image_editor.src.css');
	$at = strpos($css, '@media (max-width: 575.98px) {' . "\n" . '  .pgie-top .pgie-importbtn,');

	pg_assert_true($at !== false, 'the rule under 576 px');

	$block = substr($css, (int) $at, 900);

	foreach (array('importbtn', 'langbtn', 'animbtn', 'presentbtn') as $b) {
		pg_assert_true(strpos($block, '.pgie-top .pgie-' . $b . ' .pgie-vlabel') !== false, $b . ': glyph only');
	}

	pg_assert_true(strpos($block, 'flex: none;' . "\n" . '    width: 36px;') !== false, 'square, not squeezed');
	pg_assert_true(strpos($css, ".pgie-btn[hidden] {\n  display: none;") !== false, 'a hidden button stays hidden without a page reset');

	foreach (array('artboard' => array('import', 'languages'), 'present' => array('presentTitle'), 'render' => array('animTitle')) as $file => $keys) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_' . $file . '.js');

		foreach ($keys as $key) {
			pg_assert_true(strpos($js, "title: t('" . $key . "'), 'aria-label': t('" . $key . "')") !== false, $key . ' keeps its name');
		}
	}
}

// Under 576 px the main button shows its caller's short words, the full
// ones kept as its title and aria-label; from 576 px up nothing changes.
function test_image_editor_send_short_main_label()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/pg_image_editor.src.css');
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor.js');
	$ws = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_true(strpos($css, ".pgie-savebtn .pgie-saveshort {\n  display: none;\n}") !== false, 'short words hidden by default');

	$narrow = substr($css, (int) strpos($css, '@media (max-width: 575.98px) {' . "\n" . '  .pgie-top .pgie-importbtn,'), 1600);

	pg_assert_true(strpos($narrow, ".pgie-savebtn .pgie-savefull {\n    display: none;") !== false && strpos($narrow, ".pgie-savebtn .pgie-saveshort {\n    display: inline;") !== false, 'under 576 px the short words show');
	pg_assert_true(strpos($core, "this.saveBtn.setAttribute('aria-label', full);") !== false && strpos($core, 'this.saveBtn.title = full;') !== false, 'the full words stay its name');
	pg_assert_true(strpos($ws, "label: t('canvas_send'), short: t('canvas_send_short')") !== false, 'Send the canvas: Send');
	pg_assert_true(strpos($ws, "short: mine ? t('file_edit_put_short') : t('file_edit_send')") !== false, 'Put in place; Edit and send stays');

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/file_edits.php');

	pg_assert_same('Send', ws_canvas_js_strings()['canvas_send_short'] ?? null, 'the short word is a label');
	pg_assert_same('Put in place', ws_file_edit_js_strings()['file_edit_put_short'] ?? null, 'its short word is a label');
}
