<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how the visual designer finds an unsaved change inside a shared
 * component or system widget: every cached tree gets a snapshot when it is
 * first drawn, the save compares against it (read past the keys a selection
 * writes), an in-place text edit marks the component it lives in, and a
 * prefetch keeps a changed tree. The helpers are cut out of
 * assets/js/style_designer.js and run under Node; on a machine without Node
 * those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_shared_edit_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
}

// One function of the designer, from its declaration to the closing brace
// at its own indentation.
function designer_shared_edit_test_fn($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    }\n", $from);
	pg_assert_true($to !== false, $name . '() ends');

	return substr($source, $from, $to + 7 - $from);
}

// Runs $body (statements ending in `return <value>;`) against the helpers
// with a fresh cache; null without Node.
function designer_shared_edit_test_run($body)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = designer_shared_edit_test_source();
	$script = 'var _sharedCache = {}, _sharedDirty = {}, _sharedSnapshots = {};'
		. 'var contentLevel = false, openToContent = {};'
		. 'function _sdIsContentLevel() { return contentLevel; }'
		. 'function _sdSharedOpenToContent(sid) { return !!openToContent[sid]; }'
		. 'function _nodeInTree(n, t) { if (t === n) return true; return (t.children || []).some(function (c) { return _nodeInTree(n, c); }); }'
		. 'function _resolveSharedSid(node) { for (var k in _sharedCache) { var c = _sharedCache[k]; if (c && c.tree && _nodeInTree(node, c.tree)) return parseInt(k, 10); } return 0; }'
		. 'function widget() { var btn = { _id: "b", type: "semantic", props: { tag: "button", text: "Ara" }, children: [] };'
		. ' return { btn: btn, tree: { _id: "r", type: "root", props: {}, children: [{ _id: "f", type: "semantic", props: { tag: "form" }, children: [btn] }] } }; }'
		. designer_shared_edit_test_fn($source, '_pgBaselineTreeKey')
		. designer_shared_edit_test_fn($source, '_sdSharedTreeJSON')
		. designer_shared_edit_test_fn($source, '_sdSharedBaseline')
		. designer_shared_edit_test_fn($source, '_sdSharedChanged')
		. designer_shared_edit_test_fn($source, '_sdSharedMarkEdited')
		. designer_shared_edit_test_fn($source, '_sdSharedCatchEdits')
		. 'console.log(JSON.stringify((function () {' . $body . '})()));';

	$file = tempnam(sys_get_temp_dir(), 'pgsharededit');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A widget added in the session is drawn once, then its button label is
// written in place without any selection or mark: the save finds it.
function test_designer_shared_edit_unmarked_edit_is_found()
{
	$answer = designer_shared_edit_test_run(
		'var w = widget(); _sharedCache[7] = { tree: w.tree };'
		. '_sdSharedBaseline();'
		. 'var had = !!_sharedSnapshots[7];'
		. 'w.btn.props.text = "Simdi Bul";'
		. '_sdSharedCatchEdits();'
		. 'return [had, !!_sharedDirty[7], _sdSharedChanged(7)];'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, true, true), $answer);
}

// Selecting inside a widget writes `_expanded` and may leave an empty
// `_attrs`: neither is an edit, and a later baseline pass keeps the first
// snapshot.
function test_designer_shared_edit_selection_is_not_an_edit()
{
	$answer = designer_shared_edit_test_run(
		'var w = widget(); _sharedCache[7] = { tree: w.tree };'
		. '_sdSharedBaseline();'
		. 'var first = _sharedSnapshots[7];'
		. 'w.tree.children[0]._expanded = true; w.btn.props._attrs = [];'
		. '_sdSharedBaseline();'
		. '_sdSharedCatchEdits();'
		. 'return [_sharedSnapshots[7] === first, !!_sharedDirty[7], _sdSharedChanged(7)];'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, false, false), $answer);
}

// An entry already marked dirty gets no snapshot (its row may hold
// something else, and the flush sends it as it is); one without a tree
// gets none either.
function test_designer_shared_edit_baseline_skips_dirty()
{
	$answer = designer_shared_edit_test_run(
		'var w = widget(); _sharedCache[3] = { tree: w.tree }; _sharedDirty[3] = true;'
		. '_sharedCache[4] = { tree: null };'
		. '_sdSharedBaseline();'
		. 'return [3 in _sharedSnapshots, 4 in _sharedSnapshots, _sdSharedChanged(3), _sdSharedChanged(4)];'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, false, true, false), $answer);
}

// An in-place edit marks the component holding the node: none for a page
// node, none at content level for a component not opened to the operator.
function test_designer_shared_edit_mark_edited()
{
	$answer = designer_shared_edit_test_run(
		'var w = widget(); _sharedCache[9] = { tree: w.tree };'
		. 'var pageNode = { type: "content", props: { text: "x" }, children: [] };'
		. 'var r = [_sdSharedMarkEdited(pageNode), _sdSharedMarkEdited(w.btn), !!_sharedDirty[9]];'
		. 'delete _sharedDirty[9]; contentLevel = true;'
		. 'r.push(_sdSharedMarkEdited(w.btn), !!_sharedDirty[9]);'
		. 'openToContent[9] = true;'
		. 'r.push(_sdSharedMarkEdited(w.btn), !!_sharedDirty[9]);'
		. 'return r;'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(0, 9, true, 0, false, 9, true), $answer);
}

// Where the helpers are wired in.
function test_designer_shared_edit_wiring()
{
	$source = designer_shared_edit_test_source();

	$render = designer_shared_edit_test_fn($source, 'render');
	pg_assert_contains('_sdSharedBaseline();', $render, 'render() snapshots the components it drew');
	pg_assert_contains('_sdSharedBaseline();', designer_shared_edit_test_fn($source, 'renderSelectionOnly'), 'a selection redraw snapshots them too');

	$virtual = designer_shared_edit_test_fn($source, '_createVirtualText');
	pg_assert_contains('_sdInlineSelect(node);', $virtual, 'a double-click on a button label selects the button');
	pg_assert_contains("if (!_sdMayEdit(node, 'text')) return;", $virtual, 'the label asks for the text edit right');
	pg_assert_contains("node.props.text = t;\n                _sdSharedMarkEdited(node);", $virtual, 'a written label marks its component');

	$inline = designer_shared_edit_test_fn($source, 'setupInlineEditing');
	pg_assert_contains('_sdInlineSelect(node);', $inline, 'an in-place edit selects through the tracking helper');
	pg_assert_contains("node.props[item.prop] = newText;\n                        _sdSharedMarkEdited(node);", $inline, 'a committed in-place edit marks its component');

	pg_assert_contains('_sdTrackSharedEditing();', designer_shared_edit_test_fn($source, '_sdInlineSelect'), 'the in-place selection runs the shared tracking');
	$track = designer_shared_edit_test_fn($source, '_sdTrackSharedEditing');
	pg_assert_contains('!_sharedWarned[_editingSharedSid]', $track, 'the warning toast no longer depends on the snapshot');
	pg_assert_contains('if (!_sharedSnapshots[_editingSharedSid] && !_sharedDirty[_editingSharedSid]) {', $track, 'a selection never snapshots a changed tree');

	$flush = designer_shared_edit_test_fn($source, '_flushDirtyShared');
	pg_assert_false(strpos($flush, 'delete _sharedSnapshots[sid];') !== false, 'the flush keeps the snapshot until the answer');
	pg_assert_contains('currentCmp === _sharedSnapshots[sid]', $flush, 'the flush compares the way the snapshot is written');

	pg_assert_contains('if (_sdSharedChanged(sid)) { _sharedDirty[sid] = true; return; }', designer_shared_edit_test_fn($source, 'prefetchShared'), 'a prefetch keeps a changed tree');
}
