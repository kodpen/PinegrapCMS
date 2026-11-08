<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's parts on demand: a part not needed to open
 * a page is a bundle of its own (pg_designer_parts(), includes/fn/output.php),
 * kept out of style_designer.min.js and handed to the editor as
 * sdDesign.parts; the editor's loader (_sdPartLoad(), cut out of
 * assets/js/style_designer.src.js and run under Node) runs a part's files in
 * order, once. On a machine without Node the loader tests are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/designer_blocks_test.php';

// Every part is a bundle of pg_js_bundles() named style_designer_<part>.min.js
// whose sources are not in the editor's own bundle: a part also bundled with
// the core would run twice.
function test_designer_parts_are_bundles_apart_from_the_core()
{
	$parts = pg_designer_parts();
	$bundles = pg_js_bundles();
	pg_assert_true(isset($parts['ai']), 'the assistant is a part');
	$core = array_flip($bundles['assets/js/style_designer.min.js']);

	foreach ($parts as $name => $bundle) {
		pg_assert_same('assets/js/style_designer_' . $name . '.min.js', $bundle, $name . ' is named after the part');
		pg_assert_true(isset($bundles[$bundle]), $bundle . ' is a bundle');

		foreach ($bundles[$bundle] as $source) {
			pg_assert_false(isset($core[$source]), $source . ' is not in style_designer.min.js');
		}
	}

	pg_assert_same(array('assets/js/designer_ai.js'), $bundles['assets/js/style_designer_ai.min.js'], 'the assistant part is its panel');
}

// The addresses handed to the editor: only the parts asked for, each the
// files pg_js_bundle_urls() gives for its bundle.
function test_designer_part_urls_list_the_parts_asked_for()
{
	pg_assert_same(array(), pg_designer_part_urls(array()), 'no part asked for');
	pg_assert_same(array(), pg_designer_part_urls(array('nope')), 'an unknown part is left out');
	$urls = pg_designer_part_urls(array('ai'));
	pg_assert_same(array('ai'), array_keys($urls), 'the assistant part');
	pg_assert_same(pg_js_bundle_urls('assets/js/style_designer_ai.min.js'), $urls['ai'], 'its bundle\'s addresses');
}

// The designer screen no longer prints the assistant's script: the editor
// loads it as a part.
function test_designer_screen_hands_parts_to_the_editor()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_false(strpos($screen, 'src="assets/js/designer_ai.js') !== false, 'no <script> tag for designer_ai.js');
	pg_assert_true(strpos($screen, "'parts'") !== false && strpos($screen, 'pg_designer_part_urls(') !== false, 'sdDesign.parts is filled');
}

// Runs $script after the loader and returns the JSON lines it prints; null
// without Node. `head` collects the script elements the loader appends;
// `finish(i, ok)` fires the load or error event of the i-th one.
function designer_parts_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = _test_blocks_js();
	$from = strpos($source, '    // ── Parts on demand');
	$to = strpos($source, '    // ── end of parts on demand', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the parts loader');

	$js = 'var appended = [];'
		. 'var document = { head: { appendChild: function (s) { s.parentNode = this; appended.push(s); }, removeChild: function (s) { s.parentNode = null; } },'
		. ' createElement: function (tag) { return { tag: tag, src: "", async: true, parentNode: null }; } };'
		. 'var _design = { parts: { ai: ["a.js", "b.js"], empty: [] } };'
		. 'function finish(i, ok) { var s = appended[i]; if (ok) s.onload(); else s.onerror(); }'
		. 'function flush() { return new Promise(function (r) { setTimeout(r, 0); }); }'
		. 'function out(v) { console.log(JSON.stringify(v)); }'
		. "\n" . substr($source, $from, $to - $from) . "\n" . $script;

	$file = tempnam(sys_get_temp_dir(), 'sdparts') . '.js';
	file_put_contents($file, $js);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the loader script runs: ' . implode("\n", $lines));

	return array_map(function ($line) { return json_decode($line, true); }, $lines);
}

// The files run one after the other, in order and not async; the promise
// settles when the last has run; asked again, the same promise and no new
// script.
function test_designer_part_loader_runs_files_in_order_once()
{
	$r = designer_parts_run(
		'var settled = "";'
		. 'var p = _sdPartLoad("ai"); p.then(function () { settled = "ok"; });'
		. 'var again = _sdPartLoad("ai") === p;'
		. 'flush().then(function () { out([appended.length, again, _sdPartLoaded("ai")]); finish(0, true); return flush(); })'
		. '.then(function () { out([appended.map(function (s) { return s.src; }), appended[1].async, settled]); finish(1, true); return flush(); })'
		. '.then(function () { out([settled, _sdPartLoaded("ai"), appended.length]); _sdPartLoad("ai"); return flush(); })'
		. '.then(function () { out(appended.length); });'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(1, true, false), $r[0], 'one script at a time, the same promise, not loaded yet');
	pg_assert_same(array(array('a.js', 'b.js'), false, ''), $r[1], 'the second file after the first, in order');
	pg_assert_same(array('ok', true, 2), $r[2], 'settled once both ran');
	pg_assert_same(2, $r[3], 'a loaded part is not fetched again');
}

// A file that fails rejects the promise and takes its element away; the next
// ask tries again. A part the screen did not hand over rejects at once.
function test_designer_part_loader_retries_after_a_failure()
{
	$r = designer_parts_run(
		'_sdPartLoad("ai").then(function () { out("ok"); }, function () { out(["failed", appended[0].parentNode === null, _sdPartLoaded("ai")]); });'
		. 'flush().then(function () { finish(0, false); return flush(); })'
		. '.then(function () { _sdPartLoad("ai"); return flush(); })'
		. '.then(function () { out(appended.length); return Promise.all([_sdPartLoad("nope").catch(function () { return "unknown"; }), _sdPartLoad("empty").catch(function () { return "empty"; })]); })'
		. '.then(function (v) { out(v); });'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('failed', true, false), $r[0], 'rejected, the element taken away');
	pg_assert_same(2, $r[1], 'the next ask appends the file again');
	pg_assert_same(array('unknown', 'empty'), $r[2], 'a part not handed over is refused');
}
