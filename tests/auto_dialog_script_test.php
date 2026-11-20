<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's pop-up script run in Node: software.show_auto_dialog() and
 * software.open_dialog() taken out of frontend.src.js and of the
 * hand-patched frontend.min.js and run against a small stand-in of the
 * page, so both scripts are held to the same behaviour. Skipped where Node
 * cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/auto_dialogs.php');

// One method of the software object, `name: function(…) {…}` in the source
// or `name:function(…){…}` in the bundle, read up to its closing brace;
// strings and comments are stepped over. '' when it is not found.
function auto_dialog_script_test_method($source, $name)
{
	if (!preg_match('/\b' . preg_quote($name, '/') . ':\s*function\s*\(/', $source, $found, PREG_OFFSET_CAPTURE)) {
		return '';
	}

	$start = $found[0][1] + strlen($name) + 1;
	$open = strpos($source, '{', $start);
	$depth = 0;
	$length = strlen($source);

	for ($i = $open; $i < $length; $i++) {
		$char = $source[$i];

		if (($char === '"') || ($char === "'") || ($char === '`')) {
			for ($i++; ($i < $length) && ($source[$i] !== $char); $i++) {
				if ($source[$i] === '\\') {
					$i++;
				}
			}
		} elseif (($char === '/') && ($source[$i + 1] === '/')) {
			$i = strpos($source, "\n", $i);
		} elseif (($char === '/') && ($source[$i + 1] === '*')) {
			$i = strpos($source, '*/', $i) + 1;
		} elseif ($char === '{') {
			$depth++;
		} elseif ($char === '}') {
			$depth--;

			if ($depth === 0) {
				return ltrim(substr($source, $start, $i + 1 - $start));
			}
		}
	}

	return '';
}

// Runs $code in Node after a stand-in page that holds the named methods of
// the script; its `out` comes back decoded. Null when Node cannot run.
function auto_dialog_script_test_run($script, $methods, $code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $script);
	$page = <<<'JS'
var timers = [];
function setTimeout(callback) { timers.push(callback); }
function run_timers() { timers.splice(0).forEach(function (callback) { callback(); }); }
function element(tag) {
    return {
        tag: tag, listeners: {}, children: [], style: {}, attributes: {}, className: '', parentNode: null,
        addEventListener: function (name, callback) { (this.listeners[name] = this.listeners[name] || []).push(callback); },
        removeEventListener: function () {},
        dispatch: function (name, event) { (this.listeners[name] || []).slice().forEach(function (callback) { callback(event || {}); }); },
        appendChild: function (child) { this.children.push(child); child.parentNode = this; return child; },
        removeChild: function (child) { this.children.splice(this.children.indexOf(child), 1); child.parentNode = null; },
        setAttribute: function (name, value) { this.attributes[name] = String(value); },
        hasAttribute: function (name) { return this.attributes.hasOwnProperty(name); },
        querySelector: function () { return null; },
        showModal: function () { this.open = 'modal'; },
        show: function () { this.open = 'plain'; },
        close: function () { this.open = false; this.dispatch('close'); }
    };
}
var card = {hidden: true};
var document_listeners = {};
var document = {
    body: element('body'),
    getElementById: function (id) { return (id === 'pg-cc-card') ? card : null; },
    createElement: element,
    addEventListener: function (name, callback) { (document_listeners[name] = document_listeners[name] || []).push(callback); },
    removeEventListener: function (name, callback) {
        var list = document_listeners[name] || [];
        if (list.indexOf(callback) !== -1) { list.splice(list.indexOf(callback), 1); }
    },
    dispatch: function (name, event) { (document_listeners[name] || []).slice().forEach(function (callback) { callback(event || {}); }); }
};
var window = {innerWidth: 1000, innerHeight: 800};
var software_device_type = 'desktop';
function lang(text) { return text; }
var marks = [];
var counted = [];
var memory = {mark: function (id, state) { marks.push(id + state); }};
var software = {
    is_web_address: function () { return true; },
    dialog_style: function () {},
    count_auto_dialog: function (row, event) { counted.push(event); }
};
JS;

	foreach ($methods as $method) {
		$body = auto_dialog_script_test_method($source, $method);
		pg_assert_true($body !== '', $method . ' is found in ' . $script);
		$page .= "\nsoftware." . $method . ' = ' . $body . ';';
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgpopup');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $page . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, $script . ': the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return ($status === 0) ? json_decode(implode("\n", $out), true) : null;
}

function test_a_popup_that_waited_for_the_cookie_notice_is_remembered_and_counted()
{
	// The visitor reopened the cookie notice's card: a centred pop-up waits
	// for the answer and opens a moment after it. It must be marked shown
	// (or it opens again on every page), counted, and marked closed.
	$code = <<<'JS'
card.hidden = false;
software.show_auto_dialog({id: 7, url: '/offer', place: 'centre', content: 'url'}, false, memory);
var waiting = {marks: marks.length, open: document.body.children.length};
card.hidden = true;
document.dispatch('pg:consent', {detail: {}});
run_timers();
var dialog = document.body.children[0];
var opened = dialog ? dialog.open : null;
if (dialog) { dialog.close(); }
out = {waiting: waiting, opened: opened, marks: marks, counted: counted};
JS;

	foreach (array('frontend.src.js', 'frontend.min.js') as $script) {
		$out = auto_dialog_script_test_run($script, array('open_dialog', 'show_auto_dialog'), $code);

		if ($out === null) {
			continue;
		}

		pg_assert_same(array('marks' => 0, 'open' => 0), $out['waiting'], $script . ': nothing opens or is marked while the card is open');
		pg_assert_same('modal', $out['opened'], $script . ': it opens centred once the card is answered');
		pg_assert_same(array('7s', '7c'), $out['marks'], $script . ': marked shown when it opens and closed when it closes');
		pg_assert_same(array('shown', 'closed'), $out['counted'], $script . ': shown and closed are counted');
	}
}

function test_a_popup_opened_at_once_is_remembered_and_a_preview_is_not()
{
	$code = <<<'JS'
software.show_auto_dialog({id: 3, url: '/news', place: 'corner', content: 'url'}, false, memory);
var plain = document.body.children[0].open;
document.body.children[0].close();
software.show_auto_dialog({id: 4, url: '/news', place: 'centre', content: 'url'}, true, memory);
out = {plain: plain, marks: marks, counted: counted, preview: document.body.children.length};
JS;

	foreach (array('frontend.src.js', 'frontend.min.js') as $script) {
		$out = auto_dialog_script_test_run($script, array('open_dialog', 'show_auto_dialog'), $code);

		if ($out === null) {
			continue;
		}

		pg_assert_same('plain', $out['plain'], $script . ': a corner pop-up opens beside the page');
		pg_assert_same(array('3s', '3c'), $out['marks'], $script . ': shown and closed are remembered');
		pg_assert_same(array('shown', 'closed'), $out['counted'], $script . ': and counted');
		pg_assert_same(1, $out['preview'], $script . ': a preview opens but is neither remembered nor counted');
	}
}

function test_the_rows_printed_into_the_inline_script_hold_no_markup()
{
	// An address may hold "<!--<script>" (no space, so the URL field takes
	// it): printed raw into the page's inline script, the HTML parser then
	// reads past the script's closing tag and the rest of every page is
	// swallowed as script.
	$rows = array(array('id' => 2, 'url' => 'https://example.com/a<!--<script>b</script>&c', 'content' => 'url'));
	$json = pg_auto_dialogs_script_json($rows);

	pg_assert_false(strpos($json, '<') !== false || strpos($json, '>') !== false, 'no angle bracket reaches the script: ' . $json);
	pg_assert_same($rows, json_decode($json, true), 'the script still reads the same rows');
	pg_assert_same('[{"id":1,"url":"\\/çiçek"}]', pg_auto_dialogs_script_json(array(array('id' => 1, 'url' => '/çiçek'))), 'letters stay as they are');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_same(2, substr_count($page, "auto_dialogs: ' . pg_auto_dialogs_script_json("), 'the page and the preview print the rows through it');
	pg_assert_same(0, substr_count($page, "auto_dialogs: ' . encode_json("), 'never through encode_json()');
}
