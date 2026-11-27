<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what is typed into the visual designer's code view before it
 * reaches the tree: a redraw while typed text waits (a selection change, a
 * panel) keeps it, a half-typed attribute is not applied, words typed
 * straight into a container, row or column become text, a free button's
 * label is read back, and the "switch off again to leave it out" window
 * starts anew after more typing. The functions are cut out of
 * assets/js/style_designer_code.js and run under Node, the HTML parser from
 * the code editor bundle; on a machine without Node the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The text of `function $name(` up to its closing brace, counted by braces
// (the cut functions hold no brace inside a string or a regexp).
function designer_code_view_typing_test_cut($source, $name)
{
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return null;
	}

	$open = strpos($source, '{', $from);
	$depth = 0;
	$length = strlen($source);

	for ($i = $open; $i < $length; $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $from, $i + 1 - $from);
			}
		}
	}

	return null;
}

// Runs $steps after $prelude and the named functions of the code part (a
// `_SD_…` name: the line of that constant);
// prints what $steps logs as JSON. null without Node.
function designer_code_view_typing_test_run($names, $prelude, $steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_code.js');
	$script = 'global.window = global; global.self = global;' . "\n" . $prelude . "\n";

	foreach ($names as $name) {
		$match = array();
		$cut = (strpos($name, '_SD_') === 0)
			? (preg_match('/^\s*var ' . $name . ' = [^;]+;/m', $source, $match) ? $match[0] : null)
			: designer_code_view_typing_test_cut($source, $name);
		pg_assert_true($cut !== null, 'the code part has ' . $name . '()');

		if ($cut === null) {
			return null;
		}

		$script .= $cut . "\n";
	}

	$script .= $steps;

	$file = tempnam(sys_get_temp_dir(), 'pgcodetype');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A selection change or a panel redraw while typed text waits for the
// pause (or for its HTML to be finished) leaves the text in the editor;
// a change of the tree itself still writes the tree's text.
function test_designer_code_view_redraw_keeps_waiting_typing()
{
	$prelude = 'var K = {}; var tree = "<body>\n    <p>x</p>\n</body>";'
		. 'var _sdCode = { text: tree, dirty: null, applyTimer: 0, busy: false, entries: [] };'
		. 'var value = tree, tracked = 0, revealed = 0;'
		. '_sdCode.ed = { getValue: function () { return value; },'
		. '  applyExternal: function (f, t, s) { value = value.slice(0, f) + s + value.slice(t); },'
		. '  track: function () { tracked++; }, lock: function () {} };'
		. 'function _sdCodeEnabled() { return true; }'
		. 'function _sdCodeSyncMode() {}'
		. 'function _sdCodeDocument() { return { text: tree, entries: [], locks: [] }; }'
		. 'function _sdCodeRevealSelection() { revealed++; }'
		. 'function clearTimeout() {}';

	$answer = designer_code_view_typing_test_run(array('_sdCodeRefresh', '_sdCodeTextDiff'), $prelude, '
		var out = {};
		value = tree.replace("<p>x</p>", "<p>x</p>\n    <p>Hel");
		_sdCode.dirty = { from: 30, to: 40 };
		_sdCodeRefresh();
		out.kept = value.indexOf("<p>Hel") !== -1;
		out.dirty = !!_sdCode.dirty;
		out.revealed = revealed;
		out.tracked = tracked;

		tree = "<body>\n    <p>y</p>\n</body>";
		_sdCodeRefresh();
		out.treeWins = value === tree;
		out.dirtyAfter = !!_sdCode.dirty;
		console.log(JSON.stringify(out));
	');

	if ($answer === null) {
		return;
	}

	pg_assert_true($answer['kept'], 'the typed text stays in the editor');
	pg_assert_true($answer['dirty'], 'and still waits to be applied');
	pg_assert_same(1, $answer['revealed'], 'the selection is still shown');
	pg_assert_same(0, $answer['tracked'], 'the ranges that followed the typing are not replaced');
	pg_assert_true($answer['treeWins'], 'a changed tree writes its text');
	pg_assert_false($answer['dirtyAfter'], 'and nothing waits any more');
}

// An attribute still being typed (`name=` with no value, a quote typed
// inside a value) leaves the element unread, on a node and on the body.
function test_designer_code_view_half_typed_attribute_is_not_applied()
{
	$prelude = '(0, eval)(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/lib/code_editor/cm6.bundle.js') . ', "utf8"));'
		. 'var parser = PgCM.langHtml.htmlLanguage.parser;'
		. 'function _sdCodeDecode(s) { return s.replace(/&quot;/g, "\"").replace(/&amp;/g, "&"); }'
		. 'function el(src) { return parser.parse(src).topNode.firstChild; }';

	$answer = designer_code_view_typing_test_run(array('_sdCodeParse', '_sdCodeParseElement', '_sdCodeParseElementShallow', '_sdCodeHasError'), $prelude, '
		var out = {};
		var read = function (src) { var it = _sdCodeParseElement(el(src), src); return it ? it.attrs.map(function (a) { return a.name + "=" + a.value; }).join(" ") : null; };
		out.noValue = read("<div class=\"a\" data-y=>x</div>");
		out.quoteInside = read("<div title=\"say \"hi\"\">x</div>");
		out.openQuote = read("<div class=\"a>x</div>");
		out.fine = read("<div title=\'a\' class=\"b &amp; c\" hidden>x</div>");
		var body = "<body class=\"a\" x=>\n</body>";
		out.bodyNoValue = _sdCodeParseElementShallow(el(body), body);
		body = "<body class=\"a\" hidden>\n</body>";
		var b = _sdCodeParseElementShallow(el(body), body);
		out.bodyFine = b ? b.attrs.length : -1;
		console.log(JSON.stringify(out));
	');

	if ($answer === null) {
		return;
	}

	pg_assert_same(null, $answer['noValue'], '`data-y=` with no value yet');
	pg_assert_same(null, $answer['quoteInside'], 'a double quote typed inside a value');
	pg_assert_same(null, $answer['openQuote'], 'a value whose quote is not closed');
	pg_assert_same('title=a class=b & c hidden=null', $answer['fine'], 'finished attributes are read');
	pg_assert_same(null, $answer['bodyNoValue'], 'the body tag with a half-typed attribute');
	pg_assert_same(2, $answer['bodyFine'], 'the body tag with finished attributes');
}

// Words typed straight into a container, row or column become a text
// element there, as at body level; a plain element keeps its own text.
function test_designer_code_view_text_in_a_grid_element_is_kept()
{
	$prelude = 'var n = 0; function gid() { return "n" + (++n); }'
		. 'var owned = { o: { node: { type: "content", _id: "o" }, from: 20, sid: 0 } };'
		. 'function _sdCodeOwnerOf(it) { return it.from === 20 ? owned.o : null; }'
		. 'function _sdCodeOwnerInside() { return null; }'
		. 'function _sdCodeHasOwnedInside() { return false; }'
		. 'function _sdCodeReconcileNode() {}'
		. 'function _sdCodeSharedRefFor() { return null; }';

	$answer = designer_code_view_typing_test_run(array('_SD_CODE_SPAN_PARENTS', '_sdCodeReconcileChildren', '_sdCodeTextSlot', '_sdCodeNewSpan'), $prelude, '
		var out = {};
		var items = [{ kind: "text", from: 5, to: 15, raw: "\n  Hello  \n" }, { kind: "element", from: 20, to: 30, children: [] }];
		var report = { imports: [] };
		["container", "row", "col"].forEach(function (t) {
			var parent = { type: t, props: {}, children: [] };
			_sdCodeReconcileChildren(parent, items, 0, report);
			out[t] = parent.children.map(function (c) { return c._id === "o" ? "owned" : c.props.contentType + ":" + c.props.text; });
		});
		var plain = { type: "semantic", props: { tag: "div", text: "Hello" }, children: [] };
		_sdCodeReconcileChildren(plain, items, 0, report);
		out.plain = plain.children.length;
		console.log(JSON.stringify(out));
	');

	if ($answer === null) {
		return;
	}

	foreach (array('container', 'row', 'col') as $type) {
		pg_assert_same(array('span:Hello', 'owned'), $answer[$type], 'the words typed in a ' . $type);
	}

	pg_assert_same(1, $answer['plain'], 'a plain element keeps its text as its own');
}

// A button that is a link or a <button> is read back with its label as
// the text between its tags; an <input> button's label is its value, and
// a badge (which escapes its label) keeps none.
function test_designer_code_view_button_label_is_read_back()
{
	$prelude = 'function _sdIsSentence() { return false; }';

	$answer = designer_code_view_typing_test_run(array('_sdCodeTextKind'), $prelude, '
		var btn = function (p) { return { type: "component", props: Object.assign({ componentType: "btn" }, p) }; };
		console.log(JSON.stringify({
			link: _sdCodeTextKind(btn({})),
			button: _sdCodeTextKind(btn({ btnElement: "button" })),
			input: _sdCodeTextKind(btn({ btnElement: "input" })),
			badge: _sdCodeTextKind({ type: "component", props: { componentType: "badge" } })
		}));
	');

	if ($answer === null) {
		return;
	}

	pg_assert_same('inner', $answer['link'], 'a link button');
	pg_assert_same('inner', $answer['button'], 'a <button>');
	pg_assert_same('none', $answer['input'], 'an <input> button');
	pg_assert_same('none', $answer['badge'], 'a badge');
}

// After a switch held the view on, more typing starts the window anew: a
// later switch warns again instead of leaving the new text out silently.
function test_designer_code_view_typing_restarts_the_leave_window()
{
	$prelude = 'var value = "a", toasts = 0;'
		. 'var _sdCode = { on: true, busy: false, text: "a", dirty: null, applyTimer: 0, leaveAt: 0 };'
		. '_sdCode.ed = { getValue: function () { return value; } };'
		. 'var _SD_CODE_LEAVE_MS = 6000, _SD_CODE_APPLY_MS = 400;'
		. 'function _sdCodeMayEdit() { return true; }'
		. 'function _sdCodeFlush() {}'
		. 'function _sdCodeApply() {}'
		. 'function _sdT(s) { return s; } function esc(s) { return s; }'
		. 'function sdToast() { toasts++; }'
		. 'function setTimeout() { return 1; } function clearTimeout() {}'
		. 'var update = { changes: { mapPos: function (p) { return p; }, iterChangedRanges: function (f) { f(1, 1, 1, 2); } } };';

	$answer = designer_code_view_typing_test_run(array('_sdCodeHoldsUnapplied', '_sdCodeOnChange'), $prelude, '
		var out = {};
		value = "a<p";
		_sdCode.dirty = { from: 1, to: 3 };
		out.first = _sdCodeHoldsUnapplied();
		value = "a<p>Hi</";
		_sdCodeOnChange(_sdCode.ed, update);
		out.second = _sdCodeHoldsUnapplied();
		out.toasts = toasts;
		out.third = _sdCodeHoldsUnapplied();
		console.log(JSON.stringify(out));
	');

	if ($answer === null) {
		return;
	}

	pg_assert_true($answer['first'], 'the first switch holds the view on');
	pg_assert_true($answer['second'], 'after more typing the next switch holds it again');
	pg_assert_same(2, $answer['toasts'], 'and says why again');
	pg_assert_false($answer['third'], 'a switch right after that leaves the text out');
}
