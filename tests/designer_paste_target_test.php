<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where the visual designer pastes a block, and for the rule that
 * keeps two of its functions from sharing a name. All of
 * assets/js/style_designer.js is one function scope, so a second function
 * declaration of a name silently replaces the first for every caller: with
 * the in-memory paste's spot (beside, no index) answering for the paste
 * event's (into or beside, with an index), a pasted block went first in the
 * parent. The scan below reads the file under Node and lists the names
 * declared twice in one scope; _sdPasteSpot(), _sdPasteRepeats(),
 * _sdPasteFits(), _sdPasteNodes() and the paste event's handler are cut out
 * of the file with the drop rules (canDrop() and the lock and shared-tree
 * helpers it asks) and run with a page tree and a widget's tree, some with
 * the designer's own history (saveState(), undo(), redo()) and Ctrl+D
 * (onDuplicate()). Every paste path (the paste event, the context-menu
 * Paste, Ctrl+V without the system clipboard) places its blocks through
 * _sdPasteNodes(). On a machine without Node the tests are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script under Node and returns its JSON answer; null without Node.
function designer_paste_target_test_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgpaste');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A tokenizer that knows comments, strings, template literals and regular
// expressions, follows brackets, and opens a function scope at the body of
// `function …(…) {`, of a method `name(…) {` and of an arrow `=> {`. It
// prints { ok, error, declarations, duplicates: [{ name, lines }] }: the
// function declarations that share a name in one function scope (blocks
// inside a function count as that function's scope).
function designer_paste_target_test_scanner()
{
	return <<<'JS'
var src = require('fs').readFileSync(process.argv[2], 'utf8');
var n = src.length, i = 0;
// The last token: a keyword or identifier, a punctuator, '#v' (a value) or
// '#p' (a property name). `nl`: a line break since then.
var prev = '', nl = false;
var stack = [], scopes = [{}], fnKw = null, bodyNext = false;
var count = 0, dups = [], error = '';
var REGEX_AFTER = ' return typeof instanceof in of new delete void throw case do else yield await ';
var NOT_METHOD = ' if for while switch catch with return typeof in of new delete void throw case await yield ';
var VALUE_WORDS = ' this null true false undefined super ';
function isWord(t) { return /^[A-Za-z_$\u0080-￿#]/.test(t); }
function regexAllowed() {
    if (prev === '' || prev === '#v' || prev === '#p') return prev === '';
    if (isWord(prev)) return REGEX_AFTER.indexOf(' ' + prev + ' ') !== -1;
    return prev !== ')' && prev !== ']' && prev !== '}';
}
// `function` where a statement starts is a declaration; anywhere else it
// is an expression, whose name stays inside it.
function declPosition() {
    if (prev === '' || prev === '{' || prev === '}' || prev === ';' || prev === ')' || prev === 'else') return true;
    return nl && (prev === '#v' || prev === '#p' || prev === ']' || (isWord(prev) && REGEX_AFTER.indexOf(' ' + prev + ' ') === -1));
}
function closeScope() {
    var s = scopes.pop();
    Object.keys(s).forEach(function (k) { if (s[k].length > 1) dups.push({ name: k, at: s[k] }); });
}
function lineOf(off) { return src.slice(0, off).split('\n').length; }
// Inside `…`: up to the closing backtick or to the next `${`.
function template() {
    while (i < n) {
        var c = src[i];
        if (c === '\\') { i += 2; continue; }
        if (c === '`') { i++; prev = '#v'; nl = false; return; }
        if (c === '$' && src[i + 1] === '{') { i += 2; stack.push({ ch: '${' }); prev = '{'; nl = false; return; }
        i++;
    }
    error = 'unterminated template';
}
while (i < n && !error) {
    var c = src[i], d = src[i + 1];
    if (c === '\n') { nl = true; i++; continue; }
    if (/[ \t\r\f\v ﻿]/.test(c)) { i++; continue; }
    if (c === '/' && d === '/') { var e1 = src.indexOf('\n', i); i = e1 === -1 ? n : e1; continue; }
    if (c === '/' && d === '*') {
        var e2 = src.indexOf('*/', i + 2);
        if (e2 === -1) { error = 'unterminated comment at line ' + lineOf(i); break; }
        if (src.slice(i, e2).indexOf('\n') !== -1) nl = true;
        i = e2 + 2; continue;
    }
    var bodyWas = bodyNext;
    bodyNext = false;
    if (c === '"' || c === "'") {
        var j = i + 1;
        while (j < n && src[j] !== c && src[j] !== '\n') { if (src[j] === '\\') j++; j++; }
        if (src[j] !== c) { error = 'unterminated string at line ' + lineOf(i); break; }
        i = j + 1; prev = '#v'; nl = false; continue;
    }
    if (c === '`') { i++; template(); continue; }
    if (c === '/' && regexAllowed()) {
        var k = i + 1, cls = false;
        while (k < n && src[k] !== '\n') {
            if (src[k] === '\\') { k += 2; continue; }
            if (cls) { if (src[k] === ']') cls = false; }
            else if (src[k] === '[') cls = true;
            else if (src[k] === '/') break;
            k++;
        }
        if (src[k] !== '/') { error = 'unterminated regular expression at line ' + lineOf(i); break; }
        k++;
        while (k < n && /[a-z]/.test(src[k])) k++;
        i = k; prev = '#v'; nl = false; continue;
    }
    if (/[A-Za-z_$\u0080-￿#]/.test(c)) {
        var w = /^[#A-Za-z_$\u0080-￿][\w$\u0080-￿]*/.exec(src.slice(i, i + 200))[0];
        var at = i;
        i += w.length;
        if (prev === '.') { prev = '#p'; nl = false; continue; }
        if (w === 'function') { fnKw = { decl: declPosition() }; prev = w; nl = false; continue; }
        if (fnKw && prev === 'function') {
            if (fnKw.decl) {
                count++;
                var s = scopes[scopes.length - 1];
                (s[w] = s[w] || []).push(at);
            }
            prev = w; nl = false; continue;
        }
        prev = VALUE_WORDS.indexOf(' ' + w + ' ') !== -1 ? '#v' : w;
        nl = false; continue;
    }
    if (/[0-9]/.test(c) || (c === '.' && /[0-9]/.test(d))) {
        i += /^[0-9.]*[0-9a-zA-Z_.]*/.exec(src.slice(i, i + 100))[0].length;
        prev = '#v'; nl = false; continue;
    }
    if (c === '=' && d === '>') { i += 2; prev = '=>'; bodyNext = true; nl = false; continue; }
    if ((c === '+' && d === '+') || (c === '-' && d === '-')) { i += 2; prev = '#v'; nl = false; continue; }
    if (c === '(') {
        var params = !!fnKw;
        var call = !params && isWord(prev) && NOT_METHOD.indexOf(' ' + prev + ' ') === -1;
        fnKw = null;
        stack.push({ ch: '(', params: params, call: call });
        i++; prev = c; nl = false; continue;
    }
    if (c === '[') { stack.push({ ch: '[' }); i++; prev = c; nl = false; continue; }
    if (c === '{') {
        stack.push({ ch: '{', fn: bodyWas });
        if (bodyWas) scopes.push({});
        i++; prev = c; nl = false; continue;
    }
    if (c === ')' || c === ']' || c === '}') {
        var top = stack.pop();
        var want = c === ')' ? '(' : (c === ']' ? '[' : '{');
        if (!top || (top.ch !== want && !(c === '}' && top.ch === '${'))) { error = 'unbalanced ' + c + ' at line ' + lineOf(i); break; }
        i++;
        if (top.ch === '${') { template(); continue; }
        if (top.fn) closeScope();
        // The parameters of a function or a method: a `{` next is its body.
        if (c === ')' && (top.params || top.call)) bodyNext = true;
        prev = c; nl = false; continue;
    }
    i++; prev = c; nl = false;
}
if (!error && stack.length) error = stack.length + ' bracket(s) left open';
if (!error) closeScope();
console.log(JSON.stringify({
    ok: !error,
    error: error,
    declarations: count,
    duplicates: dups.map(function (x) { return { name: x.name, lines: x.at.map(lineOf) }; })
}));
JS;
}

// Scans $path; null without Node.
function designer_paste_target_test_scan($path)
{
	return designer_paste_target_test_node(
		'process.argv[2] = ' . json_encode($path) . ';' . designer_paste_target_test_scanner()
	);
}

// The scanner reads what it is meant to: nested scopes, function
// expressions, methods, arrows, and braces and slashes inside strings,
// comments, templates and regular expressions.
function test_designer_paste_target_scanner_reads_scopes()
{
	$sample = <<<'JS'
var S = (function () {
    function a() { function inner() {} return /}{/.test('}') ? `${ { x: '{' }.x }` : 1 / 2; }
    function b() { function inner() {} }
    var e = function a() {};
    var o = { m(x) { function a() {} }, n: () => { function a() {} } };
    // function a() {}
    /* function a() { */
    if (o) { function c() {} } else { function c() {} }
    function a() {}
    return { a: a };
})();
JS;
	$file = tempnam(sys_get_temp_dir(), 'pgscan');
	file_put_contents($file, $sample);
	$answer = designer_paste_target_test_scan($file);
	unlink($file);

	if ($answer === null) {
		return;
	}

	pg_assert_true($answer['ok'], 'the sample is read to its end: ' . $answer['error']);
	pg_assert_same(9, $answer['declarations'], 'nine declarations: a, inner, b, inner, a (method), a (arrow), c, c, a');
	pg_assert_same(array(
		array('name' => 'a', 'lines' => array(2, 9)),
		array('name' => 'c', 'lines' => array(8, 8)),
	), $answer['duplicates'], 'a and c are declared twice in the outer scope; the inner functions, the method\'s, the arrow\'s, the expression\'s and the commented a are not');
}

// Every function the designer declares has one declaration, so no caller
// gets another function than the one it was written against.
// findNodeById() is declared twice with the same behaviour (the id walk);
// it is listed here so a new duplicate still fails.
function test_designer_paste_target_function_names_are_declared_once()
{
	$answer = designer_paste_target_test_scan(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');

	if ($answer === null) {
		return;
	}

	pg_assert_true($answer['ok'], 'style_designer.js is read to its end: ' . $answer['error']);
	pg_assert_true($answer['declarations'] > 1000, 'the scan sees the designer\'s function declarations (' . $answer['declarations'] . ')');

	$known = array('findNodeById');
	$names = array();

	foreach ($answer['duplicates'] as $duplicate) {
		if (!in_array($duplicate['name'], $known, true)) {
			$names[] = $duplicate['name'] . ' (lines ' . implode(', ', $duplicate['lines']) . ')';
		}
	}

	pg_assert_same(array(), $names, 'no other function name is declared twice in one scope of style_designer.js');
}

// Runs $cases (JavaScript expressions) against the designer's paste
// functions and returns one value per case; null without Node. The page
// tree is `tree`: a section S holding heading A, paragraph B, div C (with
// paragraph C1), paragraph Q (a <p> element), an image IMG and paragraph D;
// a container K holding row R with columns K1 (with paragraph KP) and K2; a
// locked div L (with paragraph L1); a list U (with item LI). Shared
// component 7 (a widget's own tree) is `W`: heading WH and div WD (with
// WD1). Pasted nodes: paragraphs X and Y, a column COL, a list item LIX and
// LX, a paragraph copied from a locked one. `ids(list)` names the nodes of
// a list, a pasted copy by its text or tag. `paste(text, aim)` sends a paste
// event with `text` on the clipboard that lands on the canvas element
// (`{ sd: id }`) or the Overview row (`{ nid: id }`) of a node, the way the
// caret left in it does; `own(nodes)` is the clipboard after a copy here.
// Markup goes to the server stub, which answers with `apiKids()` when it is
// set. `deep(list)` names the nodes with their children in brackets,
// `byId(id)` finds a node as a canvas click does, `mark` keeps ids between
// steps. With $history the designer's own saveState(), undo(), redo() and
// onDuplicate() run instead of a saveState() that only counts.
function designer_paste_target_test_run($cases, $history = false)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$code = '';

	// Every declaration of each name, in file order, so a second
	// declaration replaces the first here exactly as it does in the editor.
	$names = array('findParent', '_findParentInShared', '_sdAnyParent', '_sdPasteSpot', '_sdPasteRepeats', '_sdPasteFits', '_sdPasteNodes',
		'isLockedEffective', 'hasLockedAncestor', 'lockedAncestorOf', 'isDescendantOf', '_nodeInTree', '_resolveSharedSid',
		'_sdSharedOwnerOf', 'collectSharedIds', '_sdDragSharedIds', '_sdSharedChildIds', '_sdSharedReaches', '_subtreeContainsLoopArea',
		'_sdHandlePaste', '_sdNodeFromEventTarget', '_sdFindNodeForEl', 'findNodeById', '_sdIsTextSink', '_sdPasteAsText', '_sdLooksLikeMarkup');

	if ($history) {
		$names = array_merge($names, array('saveState', 'undo', 'redo', 'reindex', '_snapshotShared', '_restoreShared',
			'_sdPasteMemo', '_sdPasteRelink', 'onDuplicate'));
	}

	foreach ($names as $name) {
		$offset = 0;
		$found = 0;

		while (($at = strpos($source, "\n    function " . $name . '(', $offset)) !== false) {
			$end = strpos($source, "\n    }\n", $at);
			pg_assert_true($end !== false, 'the end of ' . $name . '()');
			$code .= substr($source, $at, $end + 7 - $at);
			$offset = $end;
			$found++;
		}

		pg_assert_true($found > 0, 'style_designer.js declares ' . $name . '()');
	}

	pg_assert_true((bool) preg_match('/\n    var _SD_TEXT_SINK_TAGS = [^\n]+\n/', $source, $match), 'the designer lists its text elements');
	pg_assert_true((bool) preg_match('/\n    var _SD_CLIP_TAG = [^\n]+\n/', $source, $tag), 'the designer marks its own nodes on the clipboard');
	pg_assert_true((bool) preg_match('/\n    var _sdLastPaste = [^\n]+\n/', $source, $last), 'the designer remembers the last paste');

	// canDrop() with the helpers it is written next to.
	$from = strpos($source, '// ── Semantic HTML validation helpers');
	$to = strpos($source, '// insertCtx: null (append)', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has canDrop() and its helpers');
	$code .= substr($source, $from, $to - $from);

	$script = 'var tree, selectedNode = null, selectedNodes = [], _sharedCache = {}, _sharedDirty = {}, mark = {};'
		. ($history
			? 'var saves = 0, undoStack = [], redoStack = [], _saveStateRun = { key: null, at: 0 }, SAVE_STATE_RUN_MS = 700;'
				. 'function _dragIndexDrop() {} function _sdEditableInvalidate() {} function scheduleAutosave() {} function updateUndoRedoState() {}'
				. 'function _snapshotAssetFields() { return null; } function _restoreAssetFields() {}'
				. 'function _sdMayEdit() { return true; } function _blockDuplicateWithToast() { return false; }'
			: 'var saves = 0; function saveState() { saves++; }')
		. 'var nodeIdCounter = 100; function gid() { return "sd_" + (++nodeIdCounter); }'
		. 'function cloneTree(n) { return JSON.parse(JSON.stringify(n)); }'
		. 'function expandAncestors() {} function render() {} function _parentIndexFor() { return null; }'
		. 'var dragData = null, _lockedIndex = null; function createFromPalette() { return null; }'
		. 'var _collab = { mayEdit: true }, clipboardNodes = [], posts = 0; function _sdReadOnlyNudge() {}'
		. 'function _sdT(s) { return s; } function sdToast() {} function _backfillIds() {}'
		. 'function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); }'
		. 'var apiKids = null; function _pgApiPost() { posts++; var kids = apiKids ? apiKids() : null;'
		. ' return { then: function (f) { if (kids) f({ status: "success", children: kids }); return { catch: function () {} }; } }; }'
		. $match[0] . $tag[0] . $last[0] . $code
		. 'function N(id, type, props, kids) { return { _id: id, type: type, props: props || {}, children: kids || [] }; }'
		. 'function txt(id, text) { return N(id, "content", { contentType: "paragraph", text: text }); }'
		. 'var A = N("A", "content", { contentType: "heading", tag: "h2", text: "A" }), B = txt("B", "B"), C1 = txt("C1", "C1"),'
		. ' C = N("C", "semantic", { tag: "div" }, [C1]), Q = N("Q", "semantic", { tag: "p", text: "Q" }),'
		. ' IMG = N("IMG", "semantic", { tag: "img" }), D = txt("D", "D"), S = N("S", "semantic", { tag: "section" }, [A, B, C, Q, IMG, D]);'
		. 'var KP = txt("KP", "KP"), K1 = N("K1", "col", { cssClass: "col" }, [KP]), K2 = N("K2", "col", { cssClass: "col" }),'
		. ' R = N("R", "row", {}, [K1, K2]), K = N("K", "container", {}, [R]),'
		. ' L1 = txt("L1", "L1"), L = N("L", "semantic", { tag: "div", _locked: true }, [L1]),'
		. ' LI = N("LI", "semantic", { tag: "li", text: "LI" }), U = N("U", "semantic", { tag: "ul" }, [LI]);'
		. 'tree = N("root", "root", {}, [S, K, L, U]);'
		. 'var WH = N("WH", "content", { contentType: "heading", tag: "h3", text: "WH" }), WD1 = txt("WD1", "WD1"),'
		. ' WD = N("WD", "semantic", { tag: "div" }, [WD1]), W = N("wroot", "root", {}, [WH, WD]);'
		. '_sharedCache[7] = { tree: W };'
		. 'var X = txt("X", "X"), Y = txt("Y", "Y"), COL = N("COL", "col", { cssClass: "col", text: "COL" }),'
		. ' LIX = N("LIX", "semantic", { tag: "li", text: "LIX" }), LX = N("LX", "content", { contentType: "paragraph", text: "LX", _locked: true });'
		. 'function ids(list) { return list.map(function (k) { return k._id.indexOf("sd_") === 0 ? "+" + (k.props.text || k.props.tag) : k._id; }).join(","); }'
		. 'function deep(list) { return list.map(function (k) { return ids([k]) + (k.children && k.children.length ? "[" + deep(k.children) + "]" : ""); }).join(","); }'
		. 'function spot(s) { return s.parent._id + (s.index !== undefined ? "@" + s.index : "") + (s.sid !== undefined ? "#" + s.sid : ""); }'
		. 'function sel(node) { selectedNode = node; return true; }'
		. 'function byId(id) { return _sdFindNodeForEl(id, null); }'
		. 'function own(nodes) { return _SD_CLIP_TAG + JSON.stringify(nodes); }'
		. 'function paste(text, aim) {'
		. ' var el = function (id) { return id ? { getAttribute: function () { return id; } } : null; };'
		. ' var target = { tagName: "SPAN", isContentEditable: false, closest: function (q) {'
		. '  return q === "[data-sd-id]" ? el(aim && aim.sd) : (q === "[data-nid]" ? el(aim && aim.nid) : null); } };'
		. ' var stopped = false;'
		. ' _sdHandlePaste({ target: target, preventDefault: function () { stopped = true; },'
		. '  clipboardData: { getData: function (type) { return type === "text/plain" ? text : ""; } } });'
		. ' return stopped; }'
		. 'console.log(JSON.stringify([' . implode(',', array_map(function ($c) {
			return '(' . $c . ')';
		}, $cases)) . ']));';

	return designer_paste_target_test_node($script);
}

// The paste event's spot: into the selection when it holds children, beside
// it when it does not — in the page tree or in the widget's own tree — and
// at the end of the page when nothing is selected. A copy of the selected
// node itself goes right after it (Ctrl+C, Ctrl+V duplicates), also when it
// is one of several copied nodes; a copy of an ancestor still goes into the
// selected area.
function test_designer_paste_target_paste_event_spot()
{
	$answer = designer_paste_target_test_run(array(
		'sel(B), spot(_sdPasteSpot([X]))',
		'sel(C), spot(_sdPasteSpot([X]))',
		'sel(Q), spot(_sdPasteSpot([X]))',
		'sel(IMG), spot(_sdPasteSpot([X]))',
		'sel(S), spot(_sdPasteSpot([X]))',
		'sel(null), spot(_sdPasteSpot([X]))',
		'sel(tree), spot(_sdPasteSpot([X]))',
		'sel(WH), spot(_sdPasteSpot([X]))',
		'sel(WD), spot(_sdPasteSpot([X]))',
		'sel(txt("gone", "gone")), spot(_sdPasteSpot([X]))',
		'sel(C), spot(_sdPasteSpot([cloneTree(C)]))',
		'sel(S), spot(_sdPasteSpot([cloneTree(S)]))',
		'sel(WD), spot(_sdPasteSpot([cloneTree(WD)]))',
		'sel(C), spot(_sdPasteSpot([cloneTree(B), cloneTree(C)]))',
		'sel(C), spot(_sdPasteSpot([cloneTree(S)]))',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'S@2', 'C@1', 'S@4', 'S@5', 'S@6',
		'root@4', 'root@4',
		'wroot@1', 'WD@1',
		'root@4',
		'S@3', 'root@1', 'wroot@2', 'S@3',
		'C@1',
	), $answer, 'beside paragraph B, a <p> and an image; into div C and the section; at the end of the page with nothing or the page selected; beside the widget heading and into the widget div; a node in no tree goes at the end of the page; a copy of div C, of the section and of the widget div right after the original; B and C copied together beside C; a copy of the section into div C');
}

// The drop rules keep a paste out of a selection that may not hold it: it
// goes beside a locked element, beside a row for anything but a column,
// beside a list for anything but an item, beside a selected column for a
// column. One node that does not fit puts the whole paste beside. A copy of
// a locked element still goes in.
function test_designer_paste_target_paste_event_spot_follows_drop_rules()
{
	$answer = designer_paste_target_test_run(array(
		'sel(L), spot(_sdPasteSpot([X]))',
		'sel(R), spot(_sdPasteSpot([X]))',
		'sel(R), spot(_sdPasteSpot([COL]))',
		'sel(K1), spot(_sdPasteSpot([X]))',
		'sel(K1), spot(_sdPasteSpot([COL]))',
		'sel(U), spot(_sdPasteSpot([X]))',
		'sel(U), spot(_sdPasteSpot([LIX]))',
		'sel(C), spot(_sdPasteSpot([X, COL]))',
		'sel(C), spot(_sdPasteSpot([LX]))',
		'ids(L.children) + " " + ids(R.children) + " " + ids(U.children)',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'root@3',
		'K@1', 'R@2',
		'K1@1', 'R@1',
		'root@4', 'U@1',
		'S@3', 'C@1',
		'L1 K1,K2 LI',
	), $answer, 'beside the locked div; a paragraph beside the row, a column into it; a paragraph into a column, a column beside it; a paragraph beside the list, an item into it; a paragraph with a column beside div C; a copy of a locked paragraph into div C; the selections are left as they were');
}

// Pasting through the paste event: the blocks go in order where the spot
// says, one history step, the last one selected, and a widget's tree that
// got them is saved with the next save.
function test_designer_paste_target_paste_event_places_blocks()
{
	$answer = designer_paste_target_test_run(array(
		'sel(B), (_sdPasteNodes([X, Y]), ids(S.children) + " saves=" + saves + " sel=" + selectedNode.props.text)',
		'sel(C), (_sdPasteNodes([X]), ids(C.children))',
		'sel(null), (_sdPasteNodes([X]), ids(tree.children))',
		'sel(WH), (_sdPasteNodes([X]), ids(W.children) + " dirty=" + JSON.stringify(_sharedDirty))',
		'sel(WD), (_sdPasteNodes([Y]), ids(WD.children))',
		'sel(L), (_sdPasteNodes([Y]), ids(tree.children) + " L=" + ids(L.children))',
		'sel(A), (_sdPasteNodes([Y]), ids(S.children))',
		'sel(C), (_sdPasteNodes([cloneTree(C)]), ids(S.children) + " C=" + ids(C.children))',
		'saves',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'A,B,+X,+Y,C,Q,IMG,D saves=1 sel=Y',
		'C1,+X',
		'S,K,L,U,+X',
		'WH,+X,WD dirty={"7":true}',
		'WD1,+Y',
		'S,K,L,+Y,U,+X L=L1',
		'A,+Y,B,+X,+Y,C,Q,IMG,D',
		'A,+Y,B,+X,+Y,C,+div,Q,IMG,D C=C1,+X',
		8,
	), $answer, 'X and Y right after B in order, one save, Y selected; X last in div C; X at the end of the page; X right after the widget heading, the widget marked for saving; Y last in the widget div; Y beside the locked div, not into it; Y beside the selected heading A; a copy of div C right after it, not into it; one history step per paste');
}

// The paste event itself: our nodes off the system clipboard go by the
// selection, wherever the caret was left — on a locked Overview row, on the
// first element of a re-rendered canvas — and to the end of the page when
// nothing is selected (after Ctrl+Z, after Escape). Markup aimed at a
// paragraph still becomes that paragraph's text: the aimed node decides
// what markup is, not where a block goes.
function test_designer_paste_target_paste_event_goes_by_selection()
{
	$answer = designer_paste_target_test_run(array(
		'sel(null), paste(own([X]), { nid: "L1" }) + " " + ids(tree.children) + " L=" + ids(L.children)',
		'sel(null), paste(own([Y]), { sd: "A" }) + " " + ids(tree.children) + " S=" + ids(S.children)',
		'sel(B), paste(own([X]), { nid: "L1" }) + " " + ids(S.children) + " L=" + ids(L.children)',
		'sel(WH), paste(own([Y]), { sd: "A" }) + " " + ids(W.children) + " S=" + ids(S.children)',
		'sel(C), paste(own([C]), { nid: "C" }) + " " + ids(S.children) + " C=" + ids(C.children)',
		'sel(C), paste("<b>D</b>", { sd: "D" }) + " " + D.props.text + " C=" + ids(C.children) + " posts=" + posts',
		'sel(null), paste("<div>new</div>", { nid: "L" }) + " posts=" + posts + " L=" + ids(L.children)',
		'saves',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'true S,K,L,U,+X L=L1',
		'true S,K,L,U,+X,+Y S=A,B,C,Q,IMG,D',
		'true A,B,+X,C,Q,IMG,D L=L1',
		'true WH,+Y,WD S=A,B,+X,C,Q,IMG,D',
		'true A,B,+X,C,+div,Q,IMG,D C=C1',
		'true &lt;b&gt;D&lt;/b&gt; C=C1 posts=0',
		'true posts=1 L=L1',
		6,
	), $answer, 'nothing selected, the caret on the locked div\'s row or on heading A: the end of the page; B or the widget heading selected: right after it, not into the locked div or beside A; a copy of the selected div C right after C; markup aimed at paragraph D becomes its text; markup goes to the server to become nodes; one history step per paste');
}

// Repeated Ctrl+V through the paste event duplicates side by side: with the
// copied container selected, each paste goes right after the copy the one
// before selected, not into it. Pasting into a container takes selecting
// another one, and then the next paste goes beside that copy, inside it. A
// selected leaf takes the paste beside it; nothing selected, the end of the
// page.
function test_designer_paste_target_repeated_paste_duplicates()
{
	$answer = designer_paste_target_test_run(array(
		'sel(C), paste(own([C]), { sd: "C" }) && paste(own([C]), { sd: "C" }), deep(S.children)',
		'paste(own([C]), { sd: "C" }), ids(S.children) + " sel=" + (selectedNode === S.children[5])',
		'sel(K1), paste(own([C]), { sd: "K1" }) && paste(own([C]), { sd: "K1" }), deep(K1.children)',
		'sel(B), paste(own([C]), { sd: "B" }) && paste(own([C]), { sd: "B" }), ids(S.children)',
		'sel(null), paste(own([C]), { nid: "L1" }) && paste(own([C]), { sd: "A" }), ids(tree.children)',
		'sel(S), paste(own([S]), { sd: "S" }) && paste(own([S]), { sd: "S" }), ids(tree.children)',
		'saves',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'A,B,C[C1],+div[+C1],+div[+C1],Q,IMG,D',
		'A,B,C,+div,+div,+div,Q,IMG,D sel=true',
		'KP,+div[+C1],+div[+C1]',
		'A,B,+div,+div,C,+div,+div,+div,Q,IMG,D',
		'S,K,L,U,+div,+div',
		'S,+section,+section,K,L,U,+div,+div',
		11,
	), $answer, 'div C copied and selected: two pastes give two copies right after it, a third one a third; column K1 selected: the copy goes into it and the next one beside it there; paragraph B selected: beside it and beside that copy; nothing selected: the end of the page twice; the section copied and selected: two sections beside it; one history step per paste');
}

// The in-memory paste (the context-menu Paste and Ctrl+V while the copy
// did not reach the system clipboard) pastes clipboardNodes through
// _sdPasteNodes() and lands as the paste event does: two pastes of the
// selected copied container are two siblings, another container selected
// takes it inside, a leaf beside, nothing selected the end of the page, in
// a widget's tree too, which is then saved with the next save. No paste
// path places clipboard nodes on its own any more.
function test_designer_paste_target_in_memory_paste_lands_like_the_paste_event()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_same(0, substr_count($source, 'clipboardNodes.forEach('), 'no paste path copies clipboardNodes into the tree itself');
	pg_assert_same(4, substr_count($source, '_sdPasteNodes(clipboardNodes)'), 'the context-menu Paste, Ctrl+V in the designer and in the canvas, and the paste event\'s fallback paste through _sdPasteNodes()');

	$answer = designer_paste_target_test_run(array(
		'clipboardNodes = [cloneTree(C)], sel(C), _sdPasteNodes(clipboardNodes), _sdPasteNodes(clipboardNodes), deep(S.children)',
		'sel(K1), _sdPasteNodes(clipboardNodes), _sdPasteNodes(clipboardNodes), deep(K1.children)',
		'sel(B), _sdPasteNodes(clipboardNodes), ids(S.children)',
		'sel(null), _sdPasteNodes(clipboardNodes), _sdPasteNodes(clipboardNodes), ids(tree.children)',
		'clipboardNodes = [cloneTree(WD)], sel(WD), _sdPasteNodes(clipboardNodes), _sdPasteNodes(clipboardNodes), deep(W.children) + " dirty=" + JSON.stringify(_sharedDirty)',
		'clipboardNodes = [X, Y], sel(WH), _sdPasteNodes(clipboardNodes), ids(W.children)',
		'saves',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'A,B,C[C1],+div[+C1],+div[+C1],Q,IMG,D',
		'KP,+div[+C1],+div[+C1]',
		'A,B,+div,C,+div,+div,Q,IMG,D',
		'S,K,L,U,+div,+div',
		'WH,WD[WD1],+div[+WD1],+div[+WD1] dirty={"7":true}',
		'WH,+X,+Y,WD,+div,+div',
		10,
	), $answer, 'div C copied and selected: two copies right after it; column K1 selected: into it, then beside that copy; paragraph B selected: beside it; nothing selected: the end of the page twice; the widget div copied and selected: two copies beside it in the widget\'s tree, the widget marked for saving; two copied nodes keep their order; one history step per paste');
}

// What counts as a repeat. A paste of the same copy by the other path (the
// paste event, then the context-menu Paste) still duplicates. Once the
// clipboard holds something else, the copy the last paste selected is a
// container like any other and takes the paste inside. The last copy is
// known by reference, not by id: a node that only carries its id (as one
// made after an undo can) takes the paste inside; undo and redo find the
// copy again themselves (see the history test below). Markup pasted twice
// is two siblings too; other markup goes inside.
function test_designer_paste_target_repeat_means_same_clipboard_same_copy()
{
	$answer = designer_paste_target_test_run(array(
		'sel(C), paste(own([C]), { sd: "C" }), clipboardNodes = [cloneTree(C)], _sdPasteNodes(clipboardNodes), deep(S.children)',
		'clipboardNodes = [X], _sdPasteNodes(clipboardNodes), deep(S.children)',
		'sel(D), clipboardNodes = [cloneTree(C)], _sdPasteNodes(clipboardNodes), (function () { var c = selectedNode, twin = N(c._id, "semantic", { tag: "div" }); S.children.splice(S.children.indexOf(c), 1, twin); sel(twin); })(), _sdPasteNodes(clipboardNodes), deep(S.children.slice(-2))',
		'sel(C), apiKids = function () { return [N(gid(), "semantic", { tag: "div", text: "M" })]; }, paste("<div>M</div>", { sd: "C" }) && paste("<div>M</div>", { sd: "C" }), deep(C.children)',
		'apiKids = function () { return [N(gid(), "semantic", { tag: "div", text: "N" })]; }, paste("<div>N</div>", { sd: "C" }), deep(C.children) + " posts=" + posts',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'A,B,C[C1],+div[+C1],+div[+C1],Q,IMG,D',
		'A,B,C[C1],+div[+C1],+div[+C1,+X],Q,IMG,D',
		'D,+div[+div[+C1]]',
		'C1,+M,+M',
		'C1,+M,+M[+N] posts=3',
	), $answer, 'a copy of C through the paste event and then the in-memory paste: two siblings; after copying X, X goes into the selected copy; a node with the last copy\'s id but not that copy takes the paste inside; the same markup twice with div C selected: into C, then beside that copy; other markup into the selected copy');
}

// Runs each scenario in a fresh copy of the trees, with the designer's own
// history and Ctrl+D; one value per scenario, null without Node.
function designer_paste_target_test_history($scenarios)
{
	$answers = array();

	foreach ($scenarios as $scenario) {
		$answer = designer_paste_target_test_run(array($scenario), true);

		if ($answer === null) {
			return null;
		}

		$answers[] = $answer[0];
	}

	return $answers;
}

// Undo and redo rebuild every node from a snapshot, so the last copy is no
// longer the object the paste selected. The snapshot keeps it by id, in its
// own tree, and undo() and redo() point the last paste at the rebuilt copy:
// after Ctrl+Z (of the paste after it or of an unrelated edit) or Ctrl+Y,
// the copy selected again takes the next paste of the same clipboard beside
// it, on both paths. A new node that gets the undone copy's id is another
// container and takes the paste inside, and so does a page node that has
// the id of a copy made in the widget's tree.
function test_designer_paste_target_undo_and_redo_keep_the_last_copy()
{
	$answer = designer_paste_target_test_history(array(
		'sel(C), paste(own([C]), { sd: "C" }), mark.c1 = selectedNode._id, paste(own([C]), { sd: "C" }), undo(),'
			. ' sel(byId(mark.c1)), paste(own([C]), { sd: mark.c1 }), deep(byId("S").children) + " undo=" + undoStack.length',
		'clipboardNodes = [cloneTree(C)], sel(C), _sdPasteNodes(clipboardNodes), mark.c1 = selectedNode._id, undo(), redo(),'
			. ' sel(byId(mark.c1)), _sdPasteNodes(clipboardNodes), deep(byId("S").children)',
		'sel(C), paste(own([C]), { sd: "C" }), mark.c1 = selectedNode._id, saveState(), byId("S").children.splice(5, 1), undo(),'
			. ' sel(byId(mark.c1)), paste(own([C]), { sd: mark.c1 }), deep(byId("S").children)',
		'sel(C), paste(own([C]), { sd: "C" }), paste(own([C]), { sd: "C" }), mark.c2 = selectedNode._id, undo(),'
			. ' saveState(), mark.n = N(gid(), "semantic", { tag: "div", text: "new" }), byId("S").children.push(mark.n),'
			. ' sel(mark.n), paste(own([C]), { sd: mark.n._id }), (mark.n._id === mark.c2) + " " + deep(byId("S").children)',
		'sel(C), paste(own([C]), { sd: "C" }), mark.c1 = selectedNode._id, undo(),'
			. ' saveState(), mark.n = N(mark.c1, "semantic", { tag: "div", text: "new" }), byId("S").children.push(mark.n),'
			. ' sel(mark.n), paste(own([C]), { sd: mark.n._id }), deep(byId("S").children)',
		'tree.children.push(N("sd_101", "semantic", { tag: "div", text: "twin" })), clipboardNodes = [cloneTree(WD)], sel(WD),'
			. ' _sdPasteNodes(clipboardNodes), mark.w1 = selectedNode._id, _sdPasteNodes(clipboardNodes), undo(),'
			. ' sel(findNodeById(mark.w1, _sharedCache[7].tree)), _sdPasteNodes(clipboardNodes), sel(byId("sd_101")), _sdPasteNodes(clipboardNodes),'
			. ' (mark.w1 === "sd_101") + " " + deep(_sharedCache[7].tree.children) + " page " + deep(tree.children.slice(-1))',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'A,B,C[C1],+div[+C1],+div[+C1],Q,IMG,D undo=2',
		'A,B,C[C1],+div[+C1],+div[+C1],Q,IMG,D',
		'A,B,C[C1],+div[+C1],+div[+C1],Q,IMG,D',
		'true A,B,C[C1],+div[+C1],Q,IMG,D,+new[+div[+C1]]',
		'A,B,C[C1],Q,IMG,D,+new[+div[+C1]]',
		'true WH,WD[WD1],+div[+WD1],+div[+WD1] page +twin[+div[+WD1]]',
	), $answer, 'paste twice and undo once: the copy left, selected again, takes the paste beside it; paste, undo, redo (in-memory path): the same; an undone unrelated edit in between: the same; a new div given the undone copy\'s id takes the paste inside, after two pastes and after one; in the widget the copy selected after an undo takes it beside, and the page node with that copy\'s id takes it inside');
}

// Ctrl+D on a copied node, or on the last copy, makes one more copy of the
// clipboard: Copy, Ctrl+D, Ctrl+V and Copy, Ctrl+V, Ctrl+D, Ctrl+V give
// copies side by side on both paths. A duplicate of a node that is not on
// the clipboard is a container like any other and takes the paste inside.
function test_designer_paste_target_duplicate_counts_as_a_copy()
{
	$answer = designer_paste_target_test_history(array(
		'clipboardNodes = [cloneTree(C)], sel(C), onDuplicate(C), _sdPasteNodes(clipboardNodes), deep(byId("S").children)',
		'clipboardNodes = [cloneTree(C)], sel(C), onDuplicate(C), paste(own([C]), { sd: selectedNode._id }), deep(byId("S").children)',
		'clipboardNodes = [cloneTree(C)], sel(C), _sdPasteNodes(clipboardNodes), onDuplicate(selectedNode), _sdPasteNodes(clipboardNodes), deep(byId("S").children)',
		'clipboardNodes = [X], sel(C), onDuplicate(C), _sdPasteNodes(clipboardNodes), deep(byId("S").children)',
		'clipboardNodes = [cloneTree(C)], sel(C), onDuplicate(C), clipboardNodes = [X], _sdPasteNodes(clipboardNodes), deep(byId("S").children)',
		'clipboardNodes = [cloneTree(C)], sel(C), onDuplicate(C), undo(), sel(byId("C")), _sdPasteNodes(clipboardNodes), deep(byId("S").children)',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'A,B,C[C1],+div[+C1],+div[+C1],Q,IMG,D',
		'A,B,C[C1],+div[+C1],+div[+C1],Q,IMG,D',
		'A,B,C[C1],+div[+C1],+div[+C1],+div[+C1],Q,IMG,D',
		'A,B,C[C1],+div[+C1,+X],Q,IMG,D',
		'A,B,C[C1],+div[+C1,+X],Q,IMG,D',
		'A,B,C[C1],+div[+C1],Q,IMG,D',
	), $answer, 'C copied, Ctrl+D, Ctrl+V: the paste beside the duplicate, in memory and through the paste event; C copied, Ctrl+V, Ctrl+D, Ctrl+V: three copies side by side; X copied, Ctrl+D on C, Ctrl+V: X into the duplicate; C copied, Ctrl+D, then X copied: X into the duplicate; Ctrl+D undone: C selected takes its copy beside it');
}
