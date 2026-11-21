<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the files waiting in a Workspace writing box until Send
 * (assets/js/workspace.js: waitFiles(), drawWaiting(), dropWaiting(),
 * sendWaiting(), send(), uploadFiles(), uploadRefusal()). The methods are cut
 * out of the script and run under Node on a stand-in screen with a small
 * stand-in document (skipped on a machine without Node): a file put in the
 * box is not posted, a refusal the browser knows is said when it is added,
 * the chip's remove button takes it out, and Send posts it with the words.
 * The rest holds the wiring of every door and the server's lists the browser
 * reads (ws_upload_browser_rules()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');

function workspace_composer_files_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// A function of the script's top level, up to the next one.
function workspace_composer_files_test_function($source, $name)
{
	$from = strpos($source, "\n    function " . $name . '(');
	pg_assert_true($from !== false, $name . '() is in workspace.js');
	$to = strpos($source, "\n    function ", $from + 10);

	return substr($source, $from, ($to === false) ? null : $to - $from) . "\n";
}

// A method of the channel screen, "name: function (…) { … }" without its comma.
function workspace_composer_files_test_method($source, $name)
{
	$from = strpos($source, "\n        " . $name . ': function (');
	pg_assert_true($from !== false, $name . '() is a method in workspace.js');

	if ($from === false) {
		return '';
	}

	$end = strpos($source, "\n        },\n", $from);

	return trim(substr($source, $from, $end + 10 - $from));
}

// Runs the scenario script over the cut functions and methods; the JSON it
// prints, or null without Node.
function workspace_composer_files_test_run($scenario)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = workspace_composer_files_test_source();
	$script = "'use strict';\n";

	foreach (array('uploadRefusal', 'waitItem', 'waitThumbFree') as $name) {
		$script .= workspace_composer_files_test_function($source, $name);
	}

	$methods = array();

	foreach (array('waitingFiles', 'hasWaiting', 'waitFiles', 'waitDrawn', 'drawWaiting', 'dropWaiting', 'sendWaiting', 'uploadFiles', 'send') as $name) {
		$methods[] = workspace_composer_files_test_method($source, $name);
	}

	$script .= <<<'JS'
var BOOT = { upload: { max: 1000, names: ['web.config', '.htaccess'], blocked: ['php', 'exe'], refused: ['svg', 'html', 'docm', 'xlsm'], macro: ['docm', 'xlsm'] } };
var toasts = [];
var posted = [];
var revoked = [];
var failing = {};
var window = { URL: { createObjectURL: function (file) { return 'blob:' + file.name; }, revokeObjectURL: function (url) { revoked.push(url); } } };
function t(key) { return key + ((arguments.length > 1) ? ':' + Array.prototype.slice.call(arguments, 1).join('|') : ''); }
function toast(message) { toasts.push(message); }
function fail() { toasts.push('fail'); }
function touchScreen() { return false; }
function playMotion() {}
function sizeLabel(bytes) { return bytes + ' B'; }
function uploadCard() { return { remove: function () {}, progress: function () {} }; }
function apiUpload(action, fields, file, name) {
  posted.push({ action: action, name: name, body: fields.body, parent: fields.parent_id });
  return { xhr: { abort: function () {} }, done: failing[name] ? Promise.reject(new Error('down')) : Promise.resolve({}) };
}
function api(action, data) {
  posted.push({ action: action, file: data.file_id || 0, body: data.body });
  return Promise.resolve({});
}
function node(tag, className) {
  return { tagName: tag, className: className || '', children: [], attrs: {}, listeners: {}, hidden: false, isConnected: true, textContent: '',
    appendChild: function (child) { this.children.push(child); return child; },
    setAttribute: function (key, value) { this.attrs[key] = value; },
    addEventListener: function (type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); },
    click: function () { (this.listeners.click || []).forEach(function (fn) { fn({}); }); },
    focus: function () {} };
}
function el(tag, className, text) { var made = node(tag, className); made.textContent = (text === undefined) ? '' : String(text); return made; }
function icon(name) { return el('i', 'bi ' + name); }
function button(className, label, iconName, title) { var made = el('button', className); made.title = title; made.appendChild(icon(iconName)); return made; }
function clear(holder) { holder.children = []; return holder; }
function find(holder, className) {
  var found = [];
  (holder.children || []).forEach(function (child) {
    if ((' ' + child.className + ' ').indexOf(' ' + className + ' ') !== -1) { found.push(child); }
    found = found.concat(find(child, className));
  });
  return found;
}
function settle() { return new Promise(function (resolve) { setTimeout(resolve, 0); }); }
JS;

	$script .= "\nvar proto = {\n" . implode(",\n", $methods) . "\n};\n";
	$script .= <<<'JS'
function screen(words) {
  var view = Object.create(proto);
  view.channel = { id: 5, can_post: true };
  view.waitBox = el('div', 'ws-composer-files');
  view.input = node('div');
  view.words = words;
  view.reply = null;
  view.replyMore = null;
  view.editing = null;
  view.editingScheduled = null;
  view.picker = null;
  view.busy = false;
  view.sendButton = null;
  view.swaps = 0;
  view.inputText = function () { return this.words; };
  view.tokens = function (text) { return text; };
  view.meterRefuses = function () { return false; };
  view.clearSent = function (sent) { if (sent === this.words) { this.words = ''; } };
  view.drawReplyBar = function () {};
  view.voiceSwap = function () { this.swaps++; };
  view.sync = function () { this.synced = true; };
  view.bulkMade = function () {};
  return view;
}
function file(name, size, type) { return { name: name, size: size, type: type || '' }; }
function chips(view) {
  return find(view.waitBox, 'ws-wait-file').map(function (chip) {
    return [find(chip, 'ws-wait-name')[0].textContent, find(chip, 'ws-wait-size')[0].textContent, chip.children[0].tagName, chip.children[0].src || ''];
  });
}
JS;
	$script .= "\n(async function () {\n" . $scenario . "\n})().catch(function (error) { console.log(JSON.stringify({ error: String(error && error.stack || error) })); });\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsfiles');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer) && !isset($answer['error']), 'Node printed the answer: ' . $printed);

	return is_array($answer) ? $answer : array();
}

// A file put in the box (the paste, the drop and the picker all call
// waitFiles()) is not posted: it waits as a chip, its picture as the face.
function test_workspace_composer_files_paste_waits()
{
	$got = workspace_composer_files_test_run(<<<'JS'
var view = screen('Look at this');
view.waitFiles([file('shot.png', 400, 'image/png'), file('notes.pdf', 200, 'application/pdf')]);
await settle();
console.log(JSON.stringify({ posted: posted, chips: chips(view), waiting: view.hasWaiting(), hidden: view.waitBox.hidden, swaps: view.swaps, words: view.words }));
JS
	);

	if ($got === null) {
		return;
	}

	pg_assert_same(array(), $got['posted'], 'nothing is posted when a file is put in the box');
	pg_assert_same(array(array('shot.png', '400 B', 'img', 'blob:shot.png'), array('notes.pdf', '200 B', 'span', '')), $got['chips'], 'a chip each: thumbnail or icon, name, size');
	pg_assert_true($got['waiting'], 'the box holds waiting files');
	pg_assert_false($got['hidden'], 'the strip shows');
	pg_assert_true($got['swaps'] > 0, 'Send takes the microphone\'s place');
	pg_assert_same('Look at this', $got['words'], 'the words stay in the box');
}

// What the browser knows the channel refuses is said when the file is added,
// and the file is not added.
function test_workspace_composer_files_refused_when_added()
{
	$got = workspace_composer_files_test_run(<<<'JS'
var view = screen('');
view.waitFiles([file('big.zip', 5000), file('shell.php', 10), file('macro.docm', 10), file('ok.txt', 10)]);
console.log(JSON.stringify({ toasts: toasts, chips: chips(view).map(function (chip) { return chip[0]; }), posted: posted }));
JS
	);

	if ($got === null) {
		return;
	}

	pg_assert_same(array('file_too_large_named:big.zip|0', 'file_refused_named:shell.php', 'file_refused_macro:macro.docm'), $got['toasts'], 'size, kind and macro said at once');
	pg_assert_same(array('ok.txt'), $got['chips'], 'only the file taken waits');
	pg_assert_same(array(), $got['posted'], 'nothing posted');
}

// The chip's remove button takes the file out: it is not sent, its picture's
// address is let go.
function test_workspace_composer_files_remove_button()
{
	$got = workspace_composer_files_test_run(<<<'JS'
var view = screen('Only the second');
view.waitFiles([file('a.png', 10, 'image/png'), file('b.pdf', 20)]);
var before = view.swaps;
var remove = find(view.waitBox, 'ws-wait-remove');
var label = remove[0].title;
remove[0].click();
var after = chips(view).map(function (chip) { return chip[0]; });
view.send();
await settle();
console.log(JSON.stringify({ label: label, after: after, revoked: revoked, swapped: view.swaps > before, posted: posted }));
JS
	);

	if ($got === null) {
		return;
	}

	pg_assert_same('wait_remove:a.png', $got['label'], 'the button names the file');
	pg_assert_same(array('b.pdf'), $got['after'], 'the chip is gone');
	pg_assert_same(array('blob:a.png'), $got['revoked'], 'its thumbnail is let go');
	pg_assert_true($got['swapped'], 'Send or the microphone is asked again');
	pg_assert_same(1, count($got['posted']), 'only the file left is sent');
	pg_assert_same('b.pdf', $got['posted'][0]['name'], 'the one left');
}

// Send posts the waiting files as "Attach a file" sends several: the words
// with the first, a reply's message answered, a file manager copy in its
// place; the box empties, a file that failed waits again.
function test_workspace_composer_files_send_with_words()
{
	$got = workspace_composer_files_test_run(<<<'JS'
var view = screen('Here are both');
view.reply = { id: 77 };
view.waitFiles([file('one.png', 10, 'image/png'), file('two.pdf', 20)]);
view.waitingFiles().push({ library: 9, name: 'price.xlsx', size: 30, url: '' });
failing['two.pdf'] = true;
view.send();
await settle(); await settle(); await settle();
var first = { posted: posted.slice(), words: view.words, chips: chips(view).map(function (chip) { return chip[0]; }), reply: view.reply, toasts: toasts.slice(), synced: !!view.synced };
posted.length = 0;
var empty = screen('');
empty.send();
var alone = screen('');
alone.waitFiles([file('alone.png', 10, 'image/png')]);
alone.send();
await settle();
var other = screen('');
other.waitFiles([file('left.png', 10, 'image/png')]);
other.channel = { id: 6, can_post: true };
console.log(JSON.stringify({ first: first, rest: posted, other: other.hasWaiting(), otherFiles: other.waitingFiles().length }));
JS
	);

	if ($got === null) {
		return;
	}

	$first = $got['first'];

	pg_assert_same(array(
		array('action' => 'ws_attach', 'name' => 'one.png', 'body' => 'Here are both', 'parent' => 77),
		array('action' => 'ws_attach', 'name' => 'two.pdf', 'body' => '', 'parent' => 77),
		array('action' => 'ws_library_attach', 'file' => 9, 'body' => ''),
	), $first['posted'], 'one message a file, the words with the first');
	pg_assert_same('', $first['words'], 'the box is emptied of the words sent');
	pg_assert_same(array('two.pdf'), $first['chips'], 'the file that failed waits again');
	pg_assert_same(null, $first['reply'], 'the reply bar is closed');
	pg_assert_same(array('fail'), $first['toasts'], 'the failure is said');
	pg_assert_true($first['synced'], 'the conversation is read again');
	pg_assert_same(array(array('action' => 'ws_attach', 'name' => 'alone.png', 'body' => '', 'parent' => 0)), $got['rest'], 'an empty box sends nothing; a file alone is sent');
	pg_assert_false($got['other'], 'another conversation does not see the files');
	pg_assert_same(0, $got['otherFiles'], 'and lets them go');
}

// The name rules in the browser give what the server's ws_upload_refused()
// gives for the same name.
function test_workspace_composer_files_refusal_matches_the_server()
{
	$names = array('photo.JPG', 'report.pdf', 'a.php', 'shell.PHP.jpg', 'q3.xlsm.zip', 'x.svg', 'page.html', '.env', 'web.config', 'Web.Config', 'notes', 'a.docm', 'shell.php.docm', 'C:\\docs\\run.exe', 'image.png');
	$rules = ws_upload_browser_rules();
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$script = workspace_composer_files_test_function(workspace_composer_files_test_source(), 'uploadRefusal')
		. 'var rules = ' . json_encode($rules) . ";\n"
		. 'console.log(JSON.stringify(' . json_encode($names) . ".map(function (name) { return uploadRefusal(name, 1, rules); }).concat([uploadRefusal('a.png', rules.max + 1, rules), uploadRefusal('a.png', rules.max, rules)])));\n";
	$file = tempnam(sys_get_temp_dir(), 'pgwsrefuse');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	$got = json_decode(implode("\n", $out), true);

	foreach ($names as $index => $name) {
		$server = ws_upload_refused($name);
		$browser = $got[$index];

		pg_assert_same($server, $browser !== '', 'refused alike: ' . $name);

		if ($server) {
			$macro = strpos(ws_upload_refused_message($name), 'macros') !== false;
			pg_assert_same($macro ? 'macro' : 'kind', $browser, 'the same sentence: ' . $name);
		}
	}

	pg_assert_same('size', $got[count($names)], 'over the form limit');
	pg_assert_same('', $got[count($names) + 1], 'at the limit');
	pg_assert_same(ws_upload_form_max_bytes(), $rules['max'], 'the form limit');
}

// Every door of the conversation's box puts files in it; none posts them at
// once but Send; a note's attach and a recording are unchanged.
function test_workspace_composer_files_wiring()
{
	$source = workspace_composer_files_test_source();
	$composer = workspace_composer_files_test_method($source, 'composer');
	$drop = workspace_composer_files_test_method($source, 'bindDrop');
	$attach = workspace_composer_files_test_method($source, 'attach');
	$library = workspace_composer_files_test_method($source, 'attachFromLibrary');
	$send = workspace_composer_files_test_method($source, 'send');
	$swap = workspace_composer_files_test_method($source, 'voiceSwap');
	$voice = workspace_composer_files_test_method($source, 'voicePost');

	pg_assert_contains('pasteFiles: function (files) { self.waitFiles(files); }', $composer, 'a paste waits');
	pg_assert_contains("event.preventDefault();\n                        self.waitFiles(files);", $composer, 'the plain box\'s paste waits too');
	pg_assert_contains("box.appendChild(self.waitBox);\n            box.appendChild(input);", $composer, 'the chips stand over the field');
	pg_assert_false(strpos($composer, 'uploadFiles(') !== false, 'the box posts no file itself');
	pg_assert_contains('self.waitFiles(files);', $drop, 'a drop waits');
	pg_assert_false(strpos($drop, 'uploadFiles(') !== false, 'a drop posts nothing');
	pg_assert_contains('self.waitFiles(files);', $attach, 'a picked file waits');
	pg_assert_contains('waitingFiles().push({ library: file.id', $library, 'a file manager copy waits');
	pg_assert_false(strpos($library, "api('ws_library_attach'") !== false, 'the picker posts nothing');
	pg_assert_true(strpos($send, 'self.sendWaiting();') < strpos($send, "api('ws_send'"), 'Send sends the waiting files');
	pg_assert_contains("raw.trim() === '' && !options && !filesGo", $send, 'Send is live with a file alone');
	pg_assert_contains('!this.hasWaiting()', $swap, 'no microphone while a file waits');
	pg_assert_contains('self.uploadFiles([file]', $voice, 'a recording is still sent at once');
	pg_assert_contains("toast(t('sm_no_files'), 'warning');", $composer, 'Send later refuses a waiting file');
	pg_assert_contains("pasteFiles: function (files) { attach(files); }", $source, 'a note still attaches a pasted file');
	pg_assert_contains("waiting: null,\n            waitBox: null", workspace_composer_files_test_function($source, 'makeThreadView'), 'a discussion keeps its own waiting files');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');

	pg_assert_contains("array_merge(array('accept' => ''), ws_upload_browser_rules())", $actions, 'the screen reads the server\'s lists');
}
