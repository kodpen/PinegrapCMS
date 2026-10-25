<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where the visual designer pastes a block, and for the rule that
 * keeps two of its functions from sharing a name. All of
 * assets/js/style_designer.js is one function scope, so a second function
 * declaration of a name silently replaces the first for every caller: with
 * the in-memory paste's spot (beside, no index) answering for the paste
 * event's (into or beside, with an index), a pasted block goes first in the
 * parent. The scan below reads the file under Node and lists the names
 * declared twice in one scope; _sdPasteSpot(), _sdPasteFits(),
 * _sdPasteNodes() and _sdPasteTarget() are cut out of the file with the drop
 * rules (canDrop() and the lock and shared-tree helpers it asks) and run with
 * a page tree and a widget's tree. On a machine without Node the tests are
 * skipped.
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
// a list, a pasted copy by its text.
function designer_paste_target_test_run($cases)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$code = '';

	// Every declaration of each name, in file order, so a second
	// declaration replaces the first here exactly as it does in the editor.
	$names = array('findParent', '_findParentInShared', '_sdAnyParent', '_sdPasteTarget', '_sdPasteSpot', '_sdPasteFits', '_sdPasteNodes',
		'isLockedEffective', 'hasLockedAncestor', 'lockedAncestorOf', 'isDescendantOf', '_nodeInTree', '_resolveSharedSid',
		'_sdSharedOwnerOf', 'collectSharedIds', '_sdDragSharedIds', '_sdSharedChildIds', '_sdSharedReaches', '_subtreeContainsLoopArea');

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

	// canDrop() with the helpers it is written next to.
	$from = strpos($source, '// ── Semantic HTML validation helpers');
	$to = strpos($source, '// insertCtx: null (append)', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has canDrop() and its helpers');
	$code .= substr($source, $from, $to - $from);

	$script = 'var tree, selectedNode = null, selectedNodes = [], _sharedCache = {}, _sharedDirty = {};'
		. 'var saves = 0; function saveState() { saves++; }'
		. 'var nodeIdCounter = 100; function gid() { return "sd_" + (++nodeIdCounter); }'
		. 'function cloneTree(n) { return JSON.parse(JSON.stringify(n)); }'
		. 'function expandAncestors() {} function render() {} function _parentIndexFor() { return null; }'
		. 'var dragData = null, _lockedIndex = null; function createFromPalette() { return null; }'
		. $match[0] . $code
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
		. 'function ids(list) { return list.map(function (k) { return k._id.indexOf("sd_") === 0 ? "+" + k.props.text : k._id; }).join(","); }'
		. 'function spot(s) { return s.parent._id + (s.index !== undefined ? "@" + s.index : "") + (s.sid !== undefined ? "#" + s.sid : ""); }'
		. 'function sel(node) { selectedNode = node; return true; }'
		. 'console.log(JSON.stringify([' . implode(',', array_map(function ($c) {
			return '(' . $c . ')';
		}, $cases)) . ']));';

	return designer_paste_target_test_node($script);
}

// The paste event's spot: into the selection when it holds children, beside
// it when it does not — in the page tree or in the widget's own tree — and
// at the end of the page when nothing is selected. The selection decides;
// the node the paste landed on only when nothing is selected, so a caret
// left in another element (a locked Overview row's label) does not move the
// paste there.
function test_designer_paste_target_paste_event_spot()
{
	$answer = designer_paste_target_test_run(array(
		'sel(B), spot(_sdPasteSpot(null, [X]))',
		'sel(C), spot(_sdPasteSpot(null, [X]))',
		'sel(Q), spot(_sdPasteSpot(null, [X]))',
		'sel(IMG), spot(_sdPasteSpot(null, [X]))',
		'sel(S), spot(_sdPasteSpot(null, [X]))',
		'sel(null), spot(_sdPasteSpot(null, [X]))',
		'sel(tree), spot(_sdPasteSpot(null, [X]))',
		'sel(WH), spot(_sdPasteSpot(null, [X]))',
		'sel(WD), spot(_sdPasteSpot(null, [X]))',
		'sel(B), spot(_sdPasteSpot(C, [X]))',
		'sel(B), spot(_sdPasteSpot(L, [X]))',
		'sel(C), spot(_sdPasteSpot(WH, [X]))',
		'sel(tree), spot(_sdPasteSpot(L1, [X]))',
		'sel(null), spot(_sdPasteSpot(C, [X]))',
		'sel(null), spot(_sdPasteSpot(WH, [X]))',
		'sel(txt("gone", "gone")), spot(_sdPasteSpot(null, [X]))',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		'S@2', 'C@1', 'S@4', 'S@5', 'S@6',
		'root@4', 'root@4',
		'wroot@1', 'WD@1',
		'S@2', 'S@2', 'C@1', 'root@4',
		'C@1', 'wroot@1',
		'root@4',
	), $answer, 'beside paragraph B, a <p> and an image; into div C and the section; at the end of the page with nothing or the page selected; beside the widget heading and into the widget div; the selection before an aimed node elsewhere (div C, the locked div, the widget heading, a node in the locked div); the aimed node with nothing selected; a node in no tree goes at the end of the page');
}

// The drop rules keep a paste out of a selection that may not hold it: it
// goes beside a locked element, beside a row for anything but a column,
// beside a list for anything but an item, beside a selected column for a
// column. One node that does not fit puts the whole paste beside. A copy of
// a locked element still goes in.
function test_designer_paste_target_paste_event_spot_follows_drop_rules()
{
	$answer = designer_paste_target_test_run(array(
		'sel(L), spot(_sdPasteSpot(null, [X]))',
		'sel(R), spot(_sdPasteSpot(null, [X]))',
		'sel(R), spot(_sdPasteSpot(null, [COL]))',
		'sel(K1), spot(_sdPasteSpot(null, [X]))',
		'sel(K1), spot(_sdPasteSpot(null, [COL]))',
		'sel(U), spot(_sdPasteSpot(null, [X]))',
		'sel(U), spot(_sdPasteSpot(null, [LIX]))',
		'sel(C), spot(_sdPasteSpot(null, [X, COL]))',
		'sel(C), spot(_sdPasteSpot(null, [LX]))',
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
		'sel(L), (_sdPasteNodes([Y], L1), ids(tree.children) + " L=" + ids(L.children))',
		'sel(A), (_sdPasteNodes([Y], L), ids(S.children))',
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
		7,
	), $answer, 'X and Y right after B in order, one save, Y selected; X last in div C; X at the end of the page; X right after the widget heading, the widget marked for saving; Y last in the widget div; Y beside the locked div, not into it, whatever the caret is in; Y beside the selected heading A, not where a stale caret was; one history step per paste');
}

// The in-memory paste (context menu, Ctrl+V without the system clipboard)
// keeps its own spot: the parent of the selection, in the page tree or the
// widget's tree, with the widget's id; the page with nothing selected.
function test_designer_paste_target_in_memory_paste_spot()
{
	$answer = designer_paste_target_test_run(array(
		'sel(B), spot(_sdPasteTarget())',
		'sel(C), spot(_sdPasteTarget())',
		'sel(WH), spot(_sdPasteTarget())',
		'sel(null), spot(_sdPasteTarget())',
		'sel(tree), spot(_sdPasteTarget())',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('S#0', 'S#0', 'wroot#7', 'root#0', 'root#0'), $answer, 'the parent of B and of div C on the page, the widget\'s root for its heading, the page with nothing or the page selected');
}
