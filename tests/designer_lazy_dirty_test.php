<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's lazy dirty checks: the dirty answer of a page
 * that is not open is kept until the edit epoch, its tree, its baseline or
 * its settings change, so the poll's sweep and the tab strip stop
 * serialising pages nobody touched; the open page is compared every time.
 * _pgPageDirty() and its helpers are cut out of
 * assets/js/style_designer.src.js and run under Node; on a machine without
 * Node the test is skipped.
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

// Runs $script after _pgPageDirty() and the lazy-dirty helpers and returns
// the JSON it prints; null without Node. `serialised` counts the calls of
// the baseline serialiser; `read` counts the reads of the open page's fields.
function designer_lazy_dirty_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = _test_blocks_js();
	$from = strpos($source, '    function _pgPageDirty(p) {');
	$to = strpos($source, '    // ── end of lazy dirty checks', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has _pgPageDirty() and the lazy dirty checks');

	$js = 'var serialised = 0, read = 0, componentOnly = false;'
		. 'var _sdEditEpoch = 1, _activeKey = "open", tree = { t: "open" };'
		. 'function _sdComponentOnly() { return componentOnly; }'
		. 'function _pgReadFieldsIntoPage() { read++; }'
		. 'function _pgTreeOf(p) { return p.key === _activeKey ? tree : p.tree; }'
		. 'function _pgSettingsJSON(p) { return JSON.stringify({ name: p.page_name || "" }); }'
		. 'function _pgBaselineOf(p) { serialised++; return JSON.stringify(_pgTreeOf(p)) + "|" + _pgSettingsJSON(p); }'
		. 'function page(key, t, name) { var p = { key: key, page_id: 3, tree: t, page_name: name }; p.baseline = _pgBaselineOf(p); return p; }'
		. 'function out(v) { console.log(JSON.stringify(v)); }'
		. substr($source, $from, $to - $from)
		. $script;

	$file = tempnam(sys_get_temp_dir(), 'pglazydirty');
	file_put_contents($file, $js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A page not open is serialised once per epoch; asked again it answers from
// the kept result. An edit (the epoch moves), a new tree object, a new
// baseline or a renamed page compares again.
function test_designer_lazy_dirty_kept_for_pages_not_open()
{
	$answer = designer_lazy_dirty_run(
		'var p = page("other", { t: "a" }, "About");'
		. 'serialised = 0;'
		. 'var r = [_pgPageDirty(p), _pgPageDirty(p), _pgPageDirty(p)];'
		. 'var once = serialised;'
		. '_sdEditEpoch++; _pgPageDirty(p); var afterEpoch = serialised;'
		. 'p.tree = { t: "b" }; var newTree = _pgPageDirty(p); var afterTree = serialised;'
		. 'p.baseline = _pgBaselineOf(p); serialised = 0; var rebased = _pgPageDirty(p); var afterBaseline = serialised;'
		. 'p.page_name = "Team"; var renamed = _pgPageDirty(p); var afterRename = serialised;'
		. 'out({ r: r, once: once, afterEpoch: afterEpoch, newTree: newTree, afterTree: afterTree, rebased: rebased,'
		. ' afterBaseline: afterBaseline, renamed: renamed, afterRename: afterRename });'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, false, false), $answer['r'], 'a clean page stays clean');
	pg_assert_same(1, $answer['once'], 'serialised once for three asks in one epoch');
	pg_assert_same(2, $answer['afterEpoch'], 'an edit epoch compares again');
	pg_assert_same(true, $answer['newTree'], 'another tree object is compared: dirty');
	pg_assert_same(3, $answer['afterTree'], 'and it was serialised');
	pg_assert_same(false, $answer['rebased'], 'a new baseline is compared: clean');
	pg_assert_same(1, $answer['afterBaseline'], 'and it was serialised');
	pg_assert_same(true, $answer['renamed'], 'a renamed page is compared: dirty');
	pg_assert_same(2, $answer['afterRename'], 'and it was serialised');
}

// The open page is compared every time, its fields read first: typing in
// place changes its tree without moving the epoch. A page never saved is
// dirty, a component opened on its own has no dirty page.
function test_designer_lazy_dirty_open_page_always_compared()
{
	$answer = designer_lazy_dirty_run(
		'var o = page("open", null, "Home");'
		. 'serialised = 0; read = 0;'
		. 'var a = _pgPageDirty(o);'
		. 'tree.t = "typed";'
		. 'var b = _pgPageDirty(o);'
		. 'var n = { key: "new", page_id: 0, tree: {} };'
		. 'var fresh = _pgPageDirty(n);'
		. 'componentOnly = true; var comp = _pgPageDirty(o);'
		. 'out({ a: a, b: b, serialised: serialised, read: read, fresh: fresh, comp: comp, kept: Object.keys(_pgDirtyMemo) });'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(false, $answer['a'], 'the open page starts clean');
	pg_assert_same(true, $answer['b'], 'a change in place shows without an epoch');
	pg_assert_same(2, $answer['serialised'], 'the open page is serialised on every ask');
	pg_assert_same(2, $answer['read'], 'its fields are read first');
	pg_assert_same(true, $answer['fresh'], 'a page never saved is dirty');
	pg_assert_same(false, $answer['comp'], 'a component opened alone has no dirty page');
	pg_assert_same(array(), $answer['kept'], 'nothing is kept for the open page');
}

// The kept entries live beside the pages, never on them: a page object is
// copied and sent with its fields.
function test_designer_lazy_dirty_not_on_the_page()
{
	$answer = designer_lazy_dirty_run(
		'var p = page("other", { t: "a" }, "About");'
		. '_pgPageDirty(p);'
		. 'out({ keys: Object.keys(p).sort(), kept: Object.keys(_pgDirtyMemo),'
		. ' same: _pgDirtyMemoSame(_pgDirtyMemoKey(p), _pgDirtyMemoKey(p)), none: _pgDirtyMemoSame(null, _pgDirtyMemoKey(p)) });'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('baseline', 'key', 'page_id', 'page_name', 'tree'), $answer['keys'], 'the page object gains no field');
	pg_assert_same(array('other'), $answer['kept'], 'the answer is kept by page key');
	pg_assert_same(true, $answer['same'], 'equal keys match');
	pg_assert_same(false, $answer['none'], 'no kept answer never matches');
}
