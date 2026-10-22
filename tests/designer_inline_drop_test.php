<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the visual designer lets an operator drop where only inline
 * content goes: a link goes back into a heading (<h3><a>…</a></h3>) or a
 * sentence, but never into another link or a button at any depth, and block
 * content stays out of headings and paragraphs. canDrop() and its helpers
 * are cut out of assets/js/style_designer.js and run under Node with stubs
 * for the lock, shared-component and loop checks; on a machine without Node
 * the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $cases through the designer's canDrop() and returns one boolean per
// case; null without Node. $setup builds the page tree as `root` and the
// targets in `T`; each case is [drag expression, target name].
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
		. substr($source, $from, $to - $from)
		. 'var T = {};' . $setup . 'tree = root;'
		. 'console.log(JSON.stringify([' . implode(',', array_map(function ($c) {
			return 'canDrop(' . $c[0] . ', T.' . $c[1] . ')';
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
// buttons, and elements that sit inside a link or a button.
function designer_inline_drop_test_page()
{
	return 'T.h3 = sem("h3"); T.p = sem("p"); T.div = sem("div"); T.li = sem("li");'
		. 'T.a = sem("a", [], { href: "#" }); T.contentLink = con("link", { text: "Read", href: "#" });'
		. 'T.button = sem("button"); T.btn = N("component", { componentType: "btn", text: "Go" });'
		. 'T.spanInA = sem("span"); T.spanInButton = sem("span"); T.h3InCardLink = sem("h3");'
		. 'T.headline = sem("h3", [con("link", { text: "Event title", href: "#" })]);'
		. 'T.lockedH3 = sem("h3", [], { _locked: true });'
		. 'var root = { _id: "root", type: "root", props: {}, children: [T.h3, T.p, T.div, sem("ul", [T.li]), T.a, T.contentLink, T.button, T.btn,'
		. ' sem("a", [T.spanInA], { href: "#" }), sem("button", [T.spanInButton]),'
		. ' sem("a", [sem("div", [T.h3InCardLink])], { href: "#", cssClass: "card" }), T.headline, T.lockedH3] };';
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
// depth, also when the link is held by a dragged span or written in its text.
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
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array_fill(0, 12, false), $answer, 'link into <a>, content link, <button>, btn, a span inside a link or a button, a heading inside a card link; a span holding an <a> into a link or a button; markup with <a> into a link; a link holding a link');
}

// Block content stays out of headings and paragraphs, a link card included;
// inline content and the other rules are as before.
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

	pg_assert_same(array(false, false, false, false, false, false, true, true, true, true, true, true), $answer, 'div, a link card and a button stay out of p / h3, a locked heading takes nothing; the link card goes into a div; spans and icons go into p, a link, a button and a span inside a link');
}
