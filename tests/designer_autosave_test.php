<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Visual Page Editor's autosave (assets/js/style_designer.src.js):
 * the entries no editor can open again are removed when the editor starts,
 * a write that does not fit makes room by removing the oldest other
 * entries, a save removes the entries of the pages it wrote only once the
 * server has them (a refused save keeps the work), and a reply that is not
 * the editor's JSON says the session may have changed. The helpers are cut
 * out of the source and run under Node against a storage with a size
 * limit; on a machine without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_autosave_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The source of one function of the editor, from its header to the next
// line that starts a function or a comment at the same indent; '' when the
// function is not there.
function designer_autosave_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    }\n", $from);

	return ($to === false) ? '' : substr($source, $from, $to + 6 - $from);
}

// Runs $steps after the autosave helpers with a storage of $limit
// characters; null without Node.
function designer_autosave_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = designer_autosave_test_source();
	$from = strpos($source, '    var AUTOSAVE_MAX_AGE = ');
	$to = ($from !== false) ? strpos($source, "    // The browser's storage, or null where the site may not use it.", $from) : false;
	pg_assert_true(($from !== false) && ($to !== false), 'the editor has the autosave helpers');
	pg_assert_contains("var AUTOSAVE_PREFIX = 'pinegrap_designer_autosave_v2:';", $source, 'the key prefix the helpers are tested with');

	if (($from === false) || ($to === false)) {
		return null;
	}

	$store = <<<'JS'
var AUTOSAVE_PREFIX = 'pinegrap_designer_autosave_v2:';
function Store(limit) {
	var keys = [], vals = {};
	var used = function () { return keys.reduce(function (n, k) { return n + k.length + vals[k].length; }, 0); };
	return {
		get length() { return keys.length; },
		key: function (i) { return i < keys.length ? keys[i] : null; },
		getItem: function (k) { return Object.prototype.hasOwnProperty.call(vals, k) ? vals[k] : null; },
		setItem: function (k, v) {
			v = String(v);
			var had = Object.prototype.hasOwnProperty.call(vals, k);
			var before = had ? k.length + vals[k].length : 0;
			if (used() - before + k.length + v.length > limit) {
				var e = new Error('full'); e.name = 'QuotaExceededError'; throw e;
			}
			if (!had) keys.push(k);
			vals[k] = v;
		},
		removeItem: function (k) {
			if (!Object.prototype.hasOwnProperty.call(vals, k)) return;
			keys.splice(keys.indexOf(k), 1);
			delete vals[k];
		},
		keys: function () { return keys.slice().sort(); }
	};
}
var DAY = 24 * 60 * 60 * 1000, NOW = Date.UTC(2026, 10, 12, 12, 0, 0);
function rec(ts, size) { return JSON.stringify({ tree: { type: 'root', children: [] }, timestamp: ts, pad: 'x'.repeat(size || 0) }); }
function key(sid, page) { return AUTOSAVE_PREFIX + sid + ':' + page; }
JS;

	$file = tempnam(sys_get_temp_dir(), 'pgautosave');
	file_put_contents($file, substr($source, $from, $to - $from) . "\n" . $store . "\n" . $steps);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// On start: unreadable entries, entries older than seven days and those of
// tabs no reload brings back (after a day) go; saved pages, a design's blank
// first tab, other keys of the site and a tab still being worked on stay.
function test_designer_autosave_sweep_on_start()
{
	$answer = designer_autosave_test_run(<<<'JS'
var s = Store(1e9);
s.setItem(key(837, 'p3584'), rec(NOW - 2 * DAY));
s.setItem(key(837, 'p3589'), rec(NOW - 8 * DAY));
s.setItem(key('new', 'new1'), rec(NOW - 3 * DAY));
s.setItem(key('new', 'new1731400000000_2'), rec(NOW - 2 * DAY));
s.setItem(key(837, 'new1731400000000_3'), rec(NOW - 60 * 60 * 1000));
s.setItem(key(901, 'tpl-home'), rec(NOW - 2 * DAY));
s.setItem(key(902, 'p12'), '{not json');
s.setItem(key(903, 'p13'), JSON.stringify({ timestamp: NOW }));
s.setItem('pinegrap_designer_snapshots_v1', '[]');
var gone = _sdAutosaveSweep(s, NOW);
console.log(JSON.stringify({ gone: gone, left: s.keys() }));
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(5, $answer['gone']);
	pg_assert_same(array(
		'pinegrap_designer_autosave_v2:837:new1731400000000_3',
		'pinegrap_designer_autosave_v2:837:p3584',
		'pinegrap_designer_autosave_v2:new:new1',
		'pinegrap_designer_snapshots_v1',
	), $answer['left']);
}

// A write that does not fit removes the oldest other entries until it fits;
// when nothing more can go it answers 'full' and the page's older entry stays.
function test_designer_autosave_write_makes_room()
{
	$answer = designer_autosave_test_run(<<<'JS'
var s = Store(3000);
s.setItem(key(1, 'p1'), rec(NOW - 3 * DAY, 800));
s.setItem(key(2, 'p2'), rec(NOW - 1 * DAY, 800));
s.setItem(key(3, 'p3'), rec(NOW - 2 * DAY, 800));
var first = _sdAutosaveWrite(s, key(4, 'p4'), rec(NOW, 1200));
var afterFirst = s.keys();
s.setItem(key(4, 'p4'), rec(NOW - 1000, 100));
var full = _sdAutosaveWrite(s, key(4, 'p4'), rec(NOW, 5000));
console.log(JSON.stringify({ first: first, afterFirst: afterFirst, full: full, left: s.keys(), kept: JSON.parse(s.getItem(key(4, 'p4'))).timestamp }));
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same('ok', $answer['first']);
	pg_assert_same(array(
		'pinegrap_designer_autosave_v2:2:p2',
		'pinegrap_designer_autosave_v2:4:p4',
	), $answer['afterFirst'], 'the two oldest entries made room, the newest other stays');
	pg_assert_same('full', $answer['full'], 'a write that never fits says so');
	pg_assert_same(array('pinegrap_designer_autosave_v2:4:p4'), $answer['left']);
	pg_assert_true(is_numeric($answer['kept']), 'the page keeps the last entry that fitted');
}

// After the server answered a save: the entries of the pages sent, under the
// design's name at the time, go when they are no newer than the save; an
// edit made while the request was on its way and the pages held back stay.
function test_designer_autosave_forget_after_save()
{
	$answer = designer_autosave_test_run(<<<'JS'
var s = Store(1e9), START = NOW;
s.setItem(key('new', 'new1'), rec(START - 500));
s.setItem(key('new', 'new1731400000000_1'), rec(START + 900));
s.setItem(key('new', 'new1731400000000_2'), rec(START - 100));
s.setItem(key(837, 'new1'), rec(START - 100));
_sdAutosaveForget(s, 'new', ['new1', 'new1731400000000_1'], START);
console.log(JSON.stringify(s.keys()));
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'pinegrap_designer_autosave_v2:837:new1',
		'pinegrap_designer_autosave_v2:new:new1731400000000_1',
		'pinegrap_designer_autosave_v2:new:new1731400000000_2',
	), $answer);
}

// Where the editor calls them: the save no longer removes the entry before
// the request, the success branch forgets, the start sweeps, a write goes
// through the helper and a refusal is said once.
function test_designer_autosave_wiring()
{
	$source = designer_autosave_test_source();

	$prepare = designer_autosave_test_function($source, 'prepareFormData');
	pg_assert_true($prepare !== '', 'prepareFormData() is found');
	pg_assert_same(false, strpos($prepare, 'removeItem(_autosaveKey())'), 'the entry is not removed before the server has the pages');

	$save = designer_autosave_test_function($source, '_saveAjaxImpl');
	pg_assert_true($save !== '', '_saveAjaxImpl() is found');
	$success = strpos($save, "if (res.data && res.data.status === 'success') {");
	$forget = strpos($save, '_sdAutosaveForget(');
	$refused = strpos($save, "_sdT('It could not be saved:')");
	pg_assert_true(($success !== false) && ($forget !== false) && ($refused !== false) && ($forget > $success) && ($forget < $refused), 'only the success branch forgets the entries');
	pg_assert_true(strpos($save, '_sdAutosaveFlush();') !== false && strpos($save, '_sdAutosaveFlush();') < strpos($save, 'if (prepareFormData() === false)'), 'a pending autosave is written before the save');

	$restore = designer_autosave_test_function($source, 'checkAutosaveRestore');
	pg_assert_contains('_sdAutosaveSweep(store, Date.now())', $restore, 'the editor sweeps when it starts');
	pg_assert_true(strpos($restore, '_sdAutosaveSweep(') < strpos($restore, 'if (hasExistingTree) return;'), 'also when it opens a saved page');

	$write = designer_autosave_test_function($source, '_doAutosave');
	pg_assert_contains('_sdAutosaveWrite(store, _autosaveKey(), value)', $write, 'the autosave writes through the helper');
	pg_assert_contains('_sdAutosaveFullShown = true;', $write, 'a refused write is said once');
	pg_assert_same(false, strpos($write, '} catch(e) {}'), 'no write error is swallowed');

	$switch = designer_autosave_test_function($source, '_pgTabsSwitch');
	pg_assert_contains('_sdAutosaveFlush();', $switch, 'a tab switch writes the leaving page\'s pending autosave under its own key');
}

// A 200 that is not the editor's JSON (the token check's page, the sign-in
// page) says the session may have changed instead of "Publishing failed."
function test_designer_save_reply_not_json()
{
	$save = designer_autosave_test_function(designer_autosave_test_source(), '_saveAjaxImpl');
	pg_assert_contains("(res.data ? _sdT('Publishing failed.')", $save, 'the generic text is kept for a JSON reply without a message');
	pg_assert_contains("_sdT('The server did not answer as the editor expects: your session may have ended", $save, 'a reply that is not JSON names the session');
}
