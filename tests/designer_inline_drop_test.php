<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the visual designer lets an operator drop where only inline
 * content goes: a link or a button goes back into a heading (<h3><a>…</a></h3>,
 * the accordion's <h2><button>) or a sentence, but never into another link or
 * a button at any depth, a label holds one control, block content stays out
 * of headings and paragraphs, and a ready-made palette block is judged by
 * what it holds. canDrop() and its helpers, and _paletteInsert(), are cut out
 * of assets/js/style_designer.js and run under Node with stubs for the lock,
 * shared-component and loop checks, for createFromPalette() and for the
 * component list; on a machine without Node the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $cases through the designer's canDrop() and returns one value per
// case; null without Node. $setup builds the page tree as `root` and the
// targets in `T`; each case is [drag expression, target name], or a plain
// JavaScript expression whose value is returned as it is.
function designer_inline_drop_test_run($setup, $cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, '// ── Semantic HTML validation helpers');
	$to = strpos($source, '// insertCtx: null (append)', $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has canDrop() and its helpers');
	$insert_from = strpos($source, 'function _paletteInsert(');
	$insert_to = strpos($source, 'function _getCpActionItems(', (int) $insert_from);
	pg_assert_true(($insert_from !== false) && ($insert_to !== false), 'the designer has _paletteInsert()');

	$script = 'var _sharedCache = {}; var tree = null;'
		. 'function hasLockedAncestor(n) { var p = _parentOf(n); while (p) { if (p.props && p.props._locked) return true; p = _parentOf(p); } return false; }'
		. 'function _sdDragSharedIds() { return []; } function _sdSharedOwnerOf() { return 0; } function _sdSharedReaches() { return false; }'
		. 'function _subtreeContainsLoopArea() { return false; } function _resolveSharedSid() { return 0; }'
		. 'function isDescendantOf() { return false; }'
		. 'function _parentOf(n) { var found = null; (function walk(p) { (p.children || []).forEach(function (k) { if (k === n) found = p; else walk(k); }); })(tree); return found; }'
		. 'function _sdAnyParent(n) { var p = _parentOf(n); return p ? { parent: p, sid: 0 } : null; }'
		. 'var _id = 0; function N(type, props, kids) { return { _id: "n" + (++_id), type: type, props: props || {}, children: kids || [] }; }'
		. 'function sem(tag, kids, extra) { var p = { tag: tag }; for (var k in (extra || {})) p[k] = extra[k]; return N("semantic", p, kids); }'
		. 'function con(ct, extra, kids) { var p = { contentType: ct }; for (var k in (extra || {})) p[k] = extra[k]; return N("content", p, kids); }'
		. 'function node(n) { return { source: "node", node: n, type: n.type }; }'
		. 'function pal(type, extra) { return { source: "palette", type: type, extra: extra }; }'
		// createFromPalette() as the drop rules see it: every build counts
		// (`builds`) and takes ids; a build outside a dry run also writes the
		// block's styles (`styleWrites`). `links-block` stands for a
		// ready-made block that holds a headline link and a button.
		. 'var nodeIdCounter = 0; var builds = 0; var styleWrites = 0;'
		. 'function createFromPalette(type, extra) { builds++; nodeIdCounter += 5; if (!_sdPaletteDry) styleWrites++;'
		. ' if (type === "semantic") return sem(extra.tag);'
		. ' if (type === "content") return con(extra.contentType, extra.contentType === "link" ? { text: "Link", href: "#" } : {});'
		. ' if (type === "cf_field" && extra.cfType === "hidden") return sem("input", [], { _attrs: [{ name: "type", value: "hidden" }] });'
		. ' if (type === "cf_field" && extra.cfType === "text") return sem("div", [sem("label", [], { text: "Name" }), sem("input", [], { _attrs: [{ name: "type", value: "text" }] })]);'
		. ' if (type === "component" && extra.componentType === "links-block") return sem("section", [sem("div", [sem("h3", [con("link", { text: "Title", href: "#" })]), N("component", { componentType: "btn", text: "More", href: "#" })])]);'
		. ' if (type === "component") return N("component", { componentType: extra.componentType });'
		. ' return null; }'
		// An atomic component that prints its own interactive markup, as the
		// language switcher prints a dropdown button and links.
		. 'var COMPONENTS = { language_switcher: { toHTML: function () { return "<div class=\\"dropdown\\"><button type=\\"button\\">EN</button><ul><li><a href=\\"#\\">English</a></li></ul></div>"; } } };'
		// What _paletteInsert() touches besides the drop rules.
		. 'var selectedNode = null; function saveState() {} function expandAncestors() {} function render() {}'
		. 'function _trackRecentComp() {} function _renderRecentCompSection() {} var document = { getElementById: function () { return null; } };'
		. substr($source, $from, $to - $from)
		. substr($source, $insert_from, $insert_to - $insert_from)
		. 'var T = {};' . $setup . 'tree = root;'
		. 'console.log(JSON.stringify([' . implode(',', array_map(function ($c) {
			return is_array($c) ? 'canDrop(' . $c[0] . ', T.' . $c[1] . ')' : '(' . $c . ')';
		}, $cases)) . ']));';

	$file = tempnam(sys_get_temp_dir(), 'pginlinedrop');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The page every case drops into: plain inline-only containers, links and
// buttons, elements that sit inside a link or a button, the accordion header
// and labels with and without a control.
function designer_inline_drop_test_page()
{
	return 'T.h3 = sem("h3"); T.h2 = sem("h2"); T.p = sem("p"); T.div = sem("div"); T.li = sem("li");'
		. 'T.a = sem("a", [], { href: "#" }); T.contentLink = con("link", { text: "Read", href: "#" });'
		. 'T.button = sem("button"); T.btn = N("component", { componentType: "btn", text: "Go" });'
		. 'T.spanInA = sem("span"); T.spanInButton = sem("span"); T.h3InCardLink = sem("h3");'
		. 'T.divInCardLink = sem("div", [T.h3InCardLink]);'
		. 'T.headline = sem("h3", [con("link", { text: "Event title", href: "#" })]);'
		. 'T.lockedH3 = sem("h3", [], { _locked: true });'
		. 'T.accButton = con("heading", { tag: "button", text: "Question", cssClass: "accordion-button" });'
		. 'T.accHeader = sem("h2", [T.accButton], { cssClass: "accordion-header" });'
		. 'T.labelEmpty = sem("label", [], { text: "Name" });'
		. 'T.labelInput = sem("input", [], { _attrs: [{ name: "type", value: "checkbox" }] });'
		. 'T.spanInLabel = sem("span"); T.labelWithInput = sem("label", [T.labelInput, T.spanInLabel]);'
		. 'T.labelWithHidden = sem("label", [sem("input", [], { _attrs: [{ name: "type", value: "hidden" }] })]);'
		. 'var root = { _id: "root", type: "root", props: {}, children: [T.h3, T.h2, T.p, T.div, sem("ul", [T.li]), T.a, T.contentLink, T.button, T.btn,'
		. ' sem("a", [T.spanInA], { href: "#" }), sem("button", [T.spanInButton]),'
		. ' sem("a", [T.divInCardLink], { href: "#", cssClass: "card" }), T.headline, T.lockedH3,'
		. ' T.accHeader, T.labelEmpty, T.labelWithInput, T.labelWithHidden] };';
}

// A link the operator took out of a heading or a sentence goes back in,
// from the canvas or the palette, with its inline children.
function test_designer_inline_drop_link_into_heading_and_paragraph()
{
	$moved = 'node(T.headline.children[0])';
	$with_span = 'node(sem("a", [sem("span", [], { text: "New" })], { href: "#" }))';
	$answer = designer_inline_drop_test_run(designer_inline_drop_test_page(), array(
		array($moved, 'h3'),
		array($moved, 'p'),
		array($moved, 'headline'),
		array('pal("content", { contentType: "link" })', 'h3'),
		array('pal("semantic", { tag: "a" })', 'p'),
		array($with_span, 'h3'),
		array($with_span, 'p'),
		array($moved, 'div'),
		array($moved, 'li'),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array_fill(0, 9, true), $answer, 'content link into h3, p and a heading that already has one; palette link and <a> into h3 / p; an <a> with a span into h3 / p; still into div and li');
}

// No link inside a link and nothing interactive inside a button, at any
// depth, also when the link is held by a dragged span or written in a text.
function test_designer_inline_drop_no_link_in_link_or_button()
{
	$moved = 'node(T.headline.children[0])';
	$span_with_a = 'node(sem("span", [sem("a", [], { href: "#" })]))';
	$answer = designer_inline_drop_test_run(designer_inline_drop_test_page(), array(
		array($moved, 'a'),
		array('pal("semantic", { tag: "a" })', 'a'),
		array('pal("content", { contentType: "link" })', 'contentLink'),
		array($moved, 'button'),
		array('pal("content", { contentType: "link" })', 'btn'),
		array('pal("semantic", { tag: "a" })', 'spanInA'),
		array('pal("content", { contentType: "link" })', 'spanInButton'),
		array($moved, 'h3InCardLink'),
		array($span_with_a, 'a'),
		array($span_with_a, 'spanInButton'),
		array('node(con("span", { text: "See <a href=\\"#\\">this</a>" }))', 'spanInA'),
		array('node(sem("a", [sem("a", [], { href: "#" })], { href: "#" }))', 'p'),
		array('node(con("link", { text: "See <a href=\\"#\\">this</a>", href: "#" }))', 'h3'),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array_fill(0, 13, false), $answer, 'link into <a>, content link, <button>, btn, a span inside a link or a button, a heading inside a card link; a span holding an <a> into a link or a button; markup with <a> into a link; a link holding a link, also in its text');
}

// Block content stays out of headings and paragraphs, a link card included;
// a button goes into a paragraph; inline content and the other rules are as
// before.
function test_designer_inline_drop_block_and_existing_rules()
{
	$card = 'node(sem("a", [sem("div")], { href: "#" }))';
	$answer = designer_inline_drop_test_run(designer_inline_drop_test_page(), array(
		array('node(sem("div"))', 'p'),
		array('node(sem("div"))', 'h3'),
		array($card, 'p'),
		array($card, 'h3'),
		array('node(sem("button"))', 'p'),
		array('node(T.headline.children[0])', 'lockedH3'),
		array($card, 'div'),
		array('node(sem("span"))', 'p'),
		array('pal("content", { contentType: "span" })', 'p'),
		array('node(sem("span"))', 'a'),
		array('pal("content", { contentType: "icon" })', 'button'),
		array('pal("content", { contentType: "span" })', 'spanInA'),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, false, false, false, true, false, true, true, true, true, true, true), $answer, 'div and a link card stay out of p / h3, a button goes into p, a locked heading takes nothing; the link card goes into a div; spans and icons go into p, a link, a button and a span inside a link');
}

// A button is phrasing content: the accordion header button goes back into
// its <h2>, a button into a heading or a sentence, from the canvas or the
// palette; never into a link or a button, and not with block content in it.
function test_designer_inline_drop_button_into_heading_and_paragraph()
{
	$acc = 'node(T.accButton)';
	$answer = designer_inline_drop_test_run(designer_inline_drop_test_page(), array(
		array($acc, 'accHeader'),
		array($acc, 'h2'),
		array($acc, 'p'),
		array('node(sem("button"))', 'h3'),
		array('node(sem("button", [con("icon", { iconName: "bi-x" })]))', 'h2'),
		array('pal("semantic", { tag: "button" })', 'p'),
		array('pal("component", { componentType: "btn" })', 'h3'),
		array('node(sem("button", [sem("div")]))', 'h3'),
		array('node(sem("button"))', 'a'),
		array('node(sem("button"))', 'button'),
		array('pal("component", { componentType: "btn" })', 'btn'),
		array('node(sem("button"))', 'spanInA'),
		array('node(sem("button"))', 'h3InCardLink'),
		array($acc, 'spanInA'),
		array($acc, 'divInCardLink'),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, true, true, true, true, true, true, false, false, false, false, false, false, false, false), $answer, 'heading-button into its h2, h2 and p; button into h3, a button with an icon into h2, palette button into p and btn into h3; a button holding a div stays out of h3; no button into a link, a button, a btn, a span inside a link, a heading or a div inside a card link');
}

// A label holds one control: no button, btn printed as <button> or input goes
// into a label, or an element inside it, that already holds one. A hidden
// input is not a control, and a control moved within its own label does not
// count against it.
function test_designer_inline_drop_one_control_per_label()
{
	$answer = designer_inline_drop_test_run(designer_inline_drop_test_page(), array(
		array('node(sem("button"))', 'labelWithInput'),
		array('node(sem("button"))', 'spanInLabel'),
		array('node(N("component", { componentType: "btn", text: "Go", btnElement: "button" }))', 'labelWithInput'),
		array('node(sem("input", [], { _attrs: [{ name: "type", value: "text" }] }))', 'labelWithInput'),
		array('pal("cf_field", { cfType: "text" })', 'labelWithInput'),
		array('node(sem("button"))', 'labelEmpty'),
		array('node(sem("button"))', 'labelWithHidden'),
		array('node(T.labelInput)', 'labelWithInput'),
		array('node(T.labelInput)', 'spanInLabel'),
		array('node(sem("span"))', 'labelWithInput'),
		array('node(N("component", { componentType: "btn", text: "Go", href: "#" }))', 'labelWithInput'),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, false, false, false, false, true, true, true, true, true, true), $answer, 'a button, a <button> btn, an input and a form field stay out of a label holding an input and out of a span in it; a button goes into an empty label and one holding a hidden input; the label\'s own input moves within it; a span and a link btn go in');
}

// A ready-made palette block is judged by the tree it would add: a block
// holding a link or a button stays out of a link and a button at any depth.
// The look at its shape builds it once per item and changes nothing: the id
// counter is put back and no block styles are written.
function test_designer_inline_drop_palette_block_by_its_content()
{
	$block = 'pal("component", { componentType: "links-block" })';
	$answer = designer_inline_drop_test_run(designer_inline_drop_test_page(), array(
		array($block, 'spanInA'),
		array($block, 'divInCardLink'),
		array($block, 'spanInButton'),
		array('pal("cf_field", { cfType: "text" })', 'spanInA'),
		array($block, 'p'),
		array($block, 'div'),
		array('pal("component", { componentType: "badge" })', 'spanInA'),
		'nodeIdCounter',
		'styleWrites',
		'builds',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, false, false, false, false, true, true, 0, 0, 3), $answer, 'a block holding links and a text field stay out of a span or a div inside a link or a button, the block out of p; it goes into a div, a badge into a span inside a link; the dry builds (one per item) leave the id counter and the styles alone');
}

// A content heading printed as <button> or <a> (the accordion and tab header
// buttons) prints its text only, so it takes no child: a dropped span or icon
// would show on the canvas and be gone from the page. A content link prints
// its children and still takes them.
function test_designer_inline_drop_heading_button_takes_no_children()
{
	$answer = designer_inline_drop_test_run(designer_inline_drop_test_page(), array(
		array('pal("content", { contentType: "span" })', 'accButton'),
		array('node(sem("span"))', 'accButton'),
		array('pal("content", { contentType: "icon" })', 'accButton'),
		array('node(con("icon", { iconName: "bi-x" }))', 'accButton'),
		array('pal("content", { contentType: "link" })', 'accButton'),
		array('node(sem("span"))', 'contentLink'),
		array('pal("content", { contentType: "icon" })', 'contentLink'),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, false, false, false, false, true, true), $answer, 'no span, icon or link into the accordion header button; a span and an icon into a content link');
}

// What a link or a button holds is looked at all the way down, and a node's
// own markup counts: an <a> holding a span that holds a <div> stays out of a
// heading and a sentence, a span written with an <input> is a second control
// for a label, and an atomic component printing a button and links (the
// language switcher) is interactive and a control. A hidden input is neither.
function test_designer_inline_drop_depth_markup_and_hidden_input()
{
	$deep = 'node(sem("a", [sem("span", [sem("div")])], { href: "#" }))';
	$markup_input = 'node(con("span", { text: "<input type=\\"checkbox\\"> Agree" }))';
	$switcher = 'pal("component", { componentType: "language_switcher" })';
	$hidden = 'node(sem("input", [], { _attrs: [{ name: "type", value: "hidden" }] }))';
	$answer = designer_inline_drop_test_run(designer_inline_drop_test_page(), array(
		array($deep, 'p'),
		array($deep, 'h3'),
		array($markup_input, 'labelWithInput'),
		array($markup_input, 'labelEmpty'),
		array($switcher, 'spanInA'),
		array($switcher, 'labelWithInput'),
		array($switcher, 'div'),
		array($hidden, 'spanInA'),
		array('pal("cf_field", { cfType: "hidden" })', 'spanInA'),
		array('node(sem("input", [], { _attrs: [{ name: "type", value: "text" }] }))', 'spanInA'),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, false, false, true, false, false, true, true, true, false), $answer, 'a link with a div two levels down stays out of p / h3; a span with an <input> stays out of a label holding one, goes into an empty one; the language switcher stays out of a span in a link and a label with a control, goes into a div; a hidden input goes into a span in a link, a text input does not');
}

// Double-click and the command palette insert ask canDrop() with the payload
// a palette drag carries, so the item is judged by what it holds: a link
// goes into a selected heading, a block holding links lands on the page and
// not in a span inside a link.
function test_designer_inline_drop_palette_insert_payload()
{
	$answer = designer_inline_drop_test_run(designer_inline_drop_test_page(), array(
		'(function () { var before = root.children.length;'
		. ' selectedNode = T.h3; _paletteInsert("content", { contentType: "link" });'
		. ' var inH3 = T.h3.children.length;'
		. ' selectedNode = T.spanInA; _paletteInsert("component", { componentType: "links-block" });'
		. ' return [inH3, T.spanInA.children.length, root.children.length - before]; })()',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(array(1, 0, 1)), $answer, 'a link into the selected h3; a block with links not into the selected span inside a link but onto the page');

	// The double-click insert sits in a click handler; no palette check may
	// go back to the type-only payload.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_same(0, preg_match('/canDrop\(\{\s*type\s*:\s*type\s*\}/', $source), 'no palette check passes { type: type } alone');
	$payloads = preg_match_all('/var _pd = \{([^}]*)\}/', $source, $found);
	pg_assert_same(2, $payloads, 'the command palette and the double-click insert build a palette payload');
	foreach ($found[1] as $payload) {
		pg_assert_true(strpos($payload, "source: 'palette'") !== false && strpos($payload, 'extra') !== false, 'the insert payload names its source and its extra: ' . $payload);
	}
}

// The look at a palette item's shape builds it without side effects: inside
// createFromPalette() every write of block styles waits for a real build.
function test_designer_inline_drop_dry_build_writes_no_styles()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, 'function createFromPalette(');
	pg_assert_true($from !== false, 'the designer has createFromPalette()');
	$to = strpos($source, "\n    function ", $from + 1);
	$body = substr($source, $from, ($to === false ? strlen($source) : $to) - $from);

	$calls = preg_match_all('/_sdEnsureBlockStyles\(/', $body);
	$guarded = preg_match_all('/if \(!_sdPaletteDry\) _sdEnsureBlockStyles\(/', $body);
	pg_assert_true($calls > 0, 'createFromPalette() writes block styles');
	pg_assert_same($calls, $guarded, 'every block-style write in createFromPalette() is skipped during a dry build');
}
