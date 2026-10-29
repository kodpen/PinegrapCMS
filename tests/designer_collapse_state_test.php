<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the open state of a collapse (an accordion item) in the visual
 * designer. The canvas copies a panel opened by a click into the tree, so the
 * save drops `show` from toggleable elements; a collapse keeps the state its
 * toggle is written with instead (aria-expanded="true" and no `collapsed`
 * means open), modal, offcanvas and dropdown-menu always close. The Expanded
 * and Collapsed switches write the panel and its toggles together, and the
 * accordions the palette builds (the Components tab's accordion, the FAQ
 * blocks) have working toggles whose state agrees with their panels and
 * survives a save. A modal, offcanvas or dropdown menu open on the canvas
 * stays open through a rebuild of the canvas without reaching the tree. The
 * functions are cut out of assets/js/style_designer.src.js and run under Node
 * (the canvas on a small DOM stand-in); on a machine without Node the test is
 * skipped. The builders are found with the source scanner of
 * designer_blocks_test.php.
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

// Runs $script after the designer's collapse-state functions (and its
// createNode() with `_sdT()` stubbed) and returns the JSON it prints; null
// without Node. $script builds trees with N() / sem() / con() and calls
// out(value) once.
function designer_collapse_state_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = _test_blocks_js();
	$from = strpos($source, '    // ── Toggleable open state');
	$to = strpos($source, '    // ========================= IDS', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the toggleable open-state functions');
	$node_from = strpos($source, '    function gid()');
	$node_to = strpos($source, '    function colProps(', (int) $node_from);
	pg_assert_true(($node_from !== false) && ($node_to !== false), 'the designer has createNode()');

	$js = 'var nodeIdCounter = 0;'
		. 'function _sdT(s) { var a = Array.prototype.slice.call(arguments, 1); return String(s).replace(/\\{var(?::\\d+)?\\}/g, function () { return a.length ? String(a.shift()) : ""; }); }'
		. substr($source, $from, $to - $from)
		. substr($source, $node_from, $node_to - $node_from)
		. 'var _id = 0; function N(type, props, kids) { return { _id: "n" + (++_id), type: type, props: props || {}, children: kids || [] }; }'
		. 'function sem(tag, cls, attrs, kids, extra) { var p = { tag: tag, cssClass: cls || "", _attrs: attrs || [] }; for (var k in (extra || {})) p[k] = extra[k]; return N("semantic", p, kids); }'
		. 'function con(tag, cls, attrs, extra) { var p = { contentType: "heading", tag: tag, text: "Q", cssClass: cls || "", _attrs: attrs || [] }; for (var k in (extra || {})) p[k] = extra[k]; return N("content", p); }'
		. 'function at(n, name) { var a = (n.props._attrs || []).filter(function (x) { return x.name === name; })[0]; return a ? a.value : null; }'
		. 'function cls(n) { return n.props.cssClass; }'
		. 'function out(v) { console.log(JSON.stringify(v)); }'
		. $script;

	$file = tempnam(sys_get_temp_dir(), 'pgcollapse');
	file_put_contents($file, $js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// A toggle button for #$id: open or closed, the way the builders write it.
function designer_collapse_state_button($id, $open)
{
	return 'con("button", "accordion-button' . ($open ? '' : ' collapsed') . '", ['
		. '{ name: "type", value: "button" }, { name: "data-bs-toggle", value: "collapse" },'
		. ' { name: "data-bs-target", value: "#' . $id . '" }, { name: "aria-expanded", value: "' . ($open ? 'true' : 'false') . '" }])';
}

// An item open as written keeps `show` through a save, and gets it back when
// an older save dropped it; an item written closed loses a `show` the canvas
// left on it. Running the pass twice changes nothing more.
function test_designer_collapse_state_follows_the_toggle()
{
	$answer = designer_collapse_state_run(
		'var p1 = sem("div", "accordion-collapse collapse", [], [], { id: "c1" });'
		. 'var p2 = sem("div", "accordion-collapse collapse show", [], [], { id: "c2" });'
		. 'var p3 = sem("div", "accordion-collapse collapse show", [], [], { id: "c3" });'
		. 'var root = N("root", {}, [sem("div", "accordion", [], ['
		. ' sem("div", "accordion-item", [], [sem("h2", "accordion-header", [], [' . designer_collapse_state_button('c1', true) . ']), p1]),'
		. ' sem("div", "accordion-item", [], [sem("h2", "accordion-header", [], [' . designer_collapse_state_button('c2', false) . ']), p2]),'
		. ' sem("div", "accordion-item", [], [sem("h2", "accordion-header", [], [' . designer_collapse_state_button('c3', true) . ']), p3])'
		. '])]);'
		. '_normalizeToggleableShowState(root); var once = [cls(p1), cls(p2), cls(p3)];'
		. '_normalizeToggleableShowState(root);'
		. 'out([once, [cls(p1), cls(p2), cls(p3)]]);'
	);

	if ($answer === null) {
		return;
	}

	$expected = array('accordion-collapse collapse show', 'accordion-collapse collapse', 'accordion-collapse collapse show');
	pg_assert_same(array($expected, $expected), $answer, 'open toggle: show added or kept; closed toggle: show dropped; idempotent');
}

// What counts as an open toggle: aria-expanded="true" without `collapsed`; a
// link's href names the panel as well as data-bs-target; one open toggle of
// several is enough; a legacy `_attrs` id finds the panel; a class selector
// target or no toggle at all leaves the panel closed.
function test_designer_collapse_state_toggle_shapes()
{
	$answer = designer_collapse_state_run(
		'var half = sem("div", "collapse show", [], [], { id: "half" });'
		. 'var linked = sem("div", "collapse", [{ name: "id", value: "linked" }]);'
		. 'var two = sem("div", "collapse", [], [], { id: "two" });'
		. 'var bycls = sem("div", "collapse show multi", [], [], { id: "bycls" });'
		. 'var alone = sem("div", "collapse show", [], [], { id: "alone" });'
		. 'var root = N("root", {}, ['
		. ' con("button", "btn collapsed", [{ name: "data-bs-toggle", value: "collapse" }, { name: "data-bs-target", value: "#half" }, { name: "aria-expanded", value: "true" }]), half,'
		. ' sem("a", "btn", [{ name: "href", value: "#linked" }, { name: "data-bs-toggle", value: "collapse" }, { name: "aria-expanded", value: "true" }]), linked,'
		. ' ' . designer_collapse_state_button('two', false) . ', ' . designer_collapse_state_button('two', true) . ', two,'
		. ' con("button", "btn", [{ name: "data-bs-toggle", value: "collapse" }, { name: "data-bs-target", value: ".multi" }, { name: "aria-expanded", value: "true" }]), bycls,'
		. ' alone]);'
		. '_normalizeToggleableShowState(root);'
		. 'out([cls(half), cls(linked), cls(two), cls(bycls), cls(alone)]);'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('collapse', 'collapse show', 'collapse show', 'collapse multi', 'collapse'), $answer,
		'collapsed class wins over aria-expanded; href target; any open toggle; legacy id; class target and no toggle close');
}

// Modal, offcanvas and dropdown-menu have no authored open state: they close
// even when their trigger says aria-expanded="true".
function test_designer_collapse_state_other_toggleables_close()
{
	$answer = designer_collapse_state_run(
		'var modal = sem("div", "modal fade show", [], [], { id: "m" });'
		. 'var off = sem("div", "offcanvas offcanvas-start show", [], [], { id: "o" });'
		. 'var menu = sem("ul", "dropdown-menu show");'
		. 'var root = N("root", {}, ['
		. ' con("button", "btn", [{ name: "data-bs-toggle", value: "collapse" }, { name: "data-bs-target", value: "#m" }, { name: "aria-expanded", value: "true" }]), modal,'
		. ' con("button", "btn", [{ name: "data-bs-toggle", value: "offcanvas" }, { name: "data-bs-target", value: "#o" }, { name: "aria-expanded", value: "true" }]), off,'
		. ' sem("div", "dropdown", [], [con("button", "btn dropdown-toggle", [{ name: "data-bs-toggle", value: "dropdown" }, { name: "aria-expanded", value: "true" }]), menu])]);'
		. '_normalizeToggleableShowState(root);'
		. 'out([cls(modal), cls(off), cls(menu)]);'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('modal fade', 'offcanvas offcanvas-start', 'dropdown-menu'), $answer, 'show is dropped');
}

// A small stand-in for the canvas document: elements with classes,
// attributes and a parent, and querySelectorAll() for a list of classes.
function designer_collapse_state_dom()
{
	return 'function El(cls, attrs, parent) { var self = this; this.cls = String(cls).split(/\\s+/).filter(Boolean); this.attrs = attrs || {}; this.parentElement = parent || null;'
		. ' this.classList = { contains: function (c) { return self.cls.indexOf(c) !== -1; }, add: function (c) { if (self.cls.indexOf(c) === -1) self.cls.push(c); } }; }'
		. 'El.prototype.hasAttribute = function (n) { return Object.prototype.hasOwnProperty.call(this.attrs, n); };'
		. 'El.prototype.getAttribute = function (n) { return this.hasAttribute(n) ? this.attrs[n] : null; };'
		. 'El.prototype.removeAttribute = function (n) { delete this.attrs[n]; };'
		. 'function Doc(els) { this.els = els; }'
		. 'Doc.prototype.querySelectorAll = function (sel) { var want = sel.split(",").map(function (s) { return s.trim().replace(/^\\./, ""); });'
		. ' return this.els.filter(function (e) { return want.some(function (c) { return e.classList.contains(c); }); }); };'
		. 'function wrap(id, sid) { var a = { "data-sd-id": id }; if (sid) a["data-sw-sid"] = sid; return new El("sd-wrap", a); }'
		// One canvas: a page menu and a widget modal under the same node id, an
		// offcanvas, a menu of server HTML (no wrapper), a self-wrapped menu
		// and a collapse. `open` names the ones drawn open.
		. 'function canvas(open) { open = open || {}; var c = {};'
		. ' c.menu = new El("dropdown-menu" + (open.menu ? " show" : ""), {}, wrap("sd_1"));'
		. ' c.modal = new El("modal fade" + (open.modal ? " show" : ""), {}, wrap("sd_1", "7"));'
		. ' c.oc = new El("offcanvas offcanvas-start" + (open.oc ? " show" : ""), {}, wrap("sd_3"));'
		. ' c.server = new El("dropdown-menu" + (open.server ? " show" : ""), {}, new El("nav"));'
		. ' c.self = new El("dropdown-menu" + (open.self ? " show" : ""), open.self ? { "data-sd-id": "sd_4", "data-sd-self": "", "data-bs-popper": "static" } : { "data-sd-id": "sd_4", "data-sd-self": "" });'
		. ' c.col = new El("collapse" + (open.col ? " show" : ""), {}, wrap("sd_5"));'
		. ' c.doc = new Doc([c.menu, c.modal, c.oc, c.server, c.self, c.col]); return c; }'
		. 'function shown(c) { return ["menu", "modal", "oc", "server", "self", "col"].filter(function (k) { return c[k].classList.contains("show"); }); }';
}

// Modal, offcanvas and dropdown-menu are the toggleables whose open state is
// canvas state only; a collapse, a modal's dialog or a dropdown's own box are
// not.
function test_designer_collapse_state_canvas_only_kinds()
{
	$answer = designer_collapse_state_run(
		'out(["modal fade", "offcanvas offcanvas-start show", "dropdown-menu dropdown-menu-end", "collapse navbar-collapse",'
		. ' "accordion-collapse collapse show", "modal-dialog", "dropdown", "offcanvas-body", "", null].map(function (c) { return _sdShowIsCanvasOnly(c); }));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, true, true, false, false, false, false, false, false, false), $answer, 'modal, offcanvas, dropdown-menu only');
}

// A rebuild of the canvas keeps what was open on it: the capture names each
// open modal, offcanvas or menu a tree node draws (a widget's node apart from
// the page's node of the same id; server HTML and a collapse left out), the
// restore opens them again in the new drawing. A node that still carries
// `show` (an offcanvas as the palette adds it) stays open on the canvas and
// loses it in the tree; a collapse node is left alone; an open menu loses
// Popper's mark.
function test_designer_collapse_state_canvas_open_survives_rebuild()
{
	$answer = designer_collapse_state_run(
		designer_collapse_state_dom()
		. 'var before = canvas({ menu: true, modal: true, server: true, col: true });'
		. 'var open = _sdCanvasOpenCapture(before.doc);'
		. 'var nodes = { ":sd_1": N("semantic", { tag: "ul", cssClass: "dropdown-menu" }), "7:sd_1": N("semantic", { tag: "div", cssClass: "modal fade" }),'
		. ' ":sd_3": N("semantic", { tag: "div", cssClass: "offcanvas offcanvas-start show" }), ":sd_4": N("semantic", { tag: "ul", cssClass: "dropdown-menu show" }),'
		. ' ":sd_5": N("semantic", { tag: "div", cssClass: "collapse show" }) };'
		. 'function nodeOf(id, w) { return nodes[(w.getAttribute("data-sw-sid") || "") + ":" + id] || null; }'
		. 'var after = canvas({ oc: true, self: true, col: true });'
		. '_sdCanvasOpenRestore(after.doc, open, nodeOf);'
		. 'var again = canvas({ col: true }); _sdCanvasOpenRestore(again.doc, {}, nodeOf);'
		. '_sdCanvasOpenRestore(null, open, nodeOf);'
		. 'out({ open: open, shown: shown(after), popper: after.self.getAttribute("data-bs-popper"),'
		. ' tree: Object.keys(nodes).map(function (k) { return k + " " + cls(nodes[k]); }), closed: shown(again), none: _sdCanvasOpenCapture(null) });'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(':sd_1' => true, '7:sd_1' => true), $answer['open'], 'the open page menu and widget modal are named, server HTML and the collapse are not');
	pg_assert_same(array('menu', 'modal', 'oc', 'self', 'col'), $answer['shown'], 'open again after the rebuild; the drawn-open offcanvas and self-wrapped menu stay open');
	pg_assert_same(null, $answer['popper'], 'an open menu loses data-bs-popper');
	pg_assert_same(array(
		':sd_1 dropdown-menu', '7:sd_1 modal fade', ':sd_3 offcanvas offcanvas-start', ':sd_4 dropdown-menu', ':sd_5 collapse show',
	), $answer['tree'], 'the tree keeps no show of a modal, offcanvas or menu; a collapse keeps its own');
	pg_assert_same(array('col'), $answer['closed'], 'nothing captured: the new drawing stays as the tree has it');
	pg_assert_same(array(), $answer['none'], 'no canvas, nothing open');
}

// Where the editor uses it: renderCanvas() captures before the body is
// emptied (only when the canvas shows the active page, whose ids the keys
// name) and restores after the build; the mirror from the canvas into the
// tree is for a collapse only and skips the canvas-only kinds; the context
// bar's Show / Hide writes the tree for a collapse only. The drawn page is
// the page object, not its key: the first save of a new page re-keys it, and
// the next redraw must still find the canvas showing it. A fresh canvas
// document gets what the old one had open.
function test_designer_collapse_state_canvas_open_wiring()
{
	$render = _test_blocks_slice('    function renderCanvas() {', "\n    }\n");
	$page = strpos($render, 'var _page = _pgActivePage();');
	$capture = strpos($render, 'canvasDoc.body._sdDrawnPage === _page ? _sdCanvasOpenCapture(canvasDoc)');
	$pending = strpos($render, ': ((_sdPendingOpen && _sdPendingOpen.page === _page) ? _sdPendingOpen.open : {});');
	$used = strpos($render, '_sdPendingOpen = null;');
	$wipe = strpos($render, "canvasDoc.body.innerHTML = '';");
	$build = strpos($render, 'buildNodeEl(tree)');
	$stamp = strpos($render, 'canvasDoc.body._sdDrawnPage = _page;');
	$restore = strpos($render, '_sdCanvasOpenRestore(canvasDoc, _openToggleables, _sdFindNodeForEl);');
	pg_assert_true($page !== false && $capture !== false && $pending !== false && $used !== false && $wipe !== false && $build !== false && $stamp !== false && $restore !== false, 'renderCanvas() captures, stamps and restores');
	pg_assert_true($page < $capture && $capture < $pending && $pending < $used && $used < $wipe && $wipe < $build && $build < $stamp && $stamp < $restore, 'capture before the wipe, restore after the build');
	pg_assert_same(0, preg_match('/_sdDrawnPage\s*(?:===?|!==?)\s*_activeKey|_sdDrawnPage\s*=\s*_activeKey/', _test_blocks_js()), 'the drawn page is never the key, which a save changes');
	$after_save = _test_blocks_slice('    function _pgTabsAfterSave(data) {', "\n    }\n");
	pg_assert_true(strpos($after_save, 'if (_activeKey === p.key) _activeKey = newKey;') !== false, 'the first save of a new page re-keys the active page (the reason the object is compared)');

	$reset = _test_blocks_slice('    function _sdCanvasRealmReset() {', "\n    }\n");
	$keep = strpos($reset, '_sdPendingOpen = { page: canvasDoc.body._sdDrawnPage, open: _sdCanvasOpenCapture(canvasDoc) };');
	$drop = strpos($reset, 'canvasDoc = null;');
	pg_assert_true($keep !== false && $drop !== false && $keep < $drop, 'a realm reset notes what the old document had open before it lets the document go');

	$sync = _test_blocks_slice('    function _syncToggleableShowStateFromDom() {', "\n    }\n");
	pg_assert_true(strpos($sync, "canvasDoc.body._sdDrawnPage !== _pgActivePage()) return;") !== false, 'the mirror waits for the active page on the canvas');
	pg_assert_true(strpos($sync, "canvasDoc.querySelectorAll('.collapse')") !== false, 'the mirror reads collapse elements only');
	pg_assert_true(strpos($sync, '_sdShowIsCanvasOnly(node.props.cssClass)) continue;') !== false, 'the mirror skips a canvas-only node');

	$ctx = _test_blocks_slice('    function ctxBarToggle(toShow) {', "\n    }\n");
	pg_assert_true(strpos($ctx, 'if (!_sdShowIsCanvasOnly(targetNode.props.cssClass)) {') !== false, 'Show / Hide writes the tree for a collapse only');
}

// A dropdown menu's open state is held by its canvas element alone, so the
// element is found from the node, within the node's widget: a widget's menu
// whose id a page element also uses is the widget's element, not the page's
// (and a page node is never matched inside a widget). The context bar's
// Open / Close and the Options panel's Expanded switch read and write it so.
function test_designer_collapse_state_canvas_inner_of_node()
{
	$fns = _test_blocks_slice('    function _sdCanvasInnerOf(node) {', '    /**')
		. _test_blocks_slice('    function _findCanvasEl(node) {', "\n    }\n") . "\n    }\n"
		. _test_blocks_slice('    function _resolveSharedSid(node) {', "\n    }\n") . "\n    }\n"
		. _test_blocks_slice('    function _nodeInTree(target, root) {', "\n    }\n") . "\n    }\n";
	pg_assert_true(strpos($fns, 'function _sdWrapInner(wrap)') !== false, 'the designer has _sdCanvasInnerOf() and _sdWrapInner()');

	$answer = designer_collapse_state_run(
		$fns
		// Elements with classes, attributes, children and querySelector() for
		// [a="v"] lists with an optional :not([a]).
		. 'function E(cls, attrs, kids) { var self = this; this.cls = String(cls).split(/\\s+/).filter(Boolean); this.attrs = attrs || {}; this.children = kids || [];'
		. ' this.classList = { contains: function (c) { return self.cls.indexOf(c) !== -1; } }; }'
		. 'E.prototype.hasAttribute = function (n) { return Object.prototype.hasOwnProperty.call(this.attrs, n); };'
		. 'E.prototype.getAttribute = function (n) { return this.hasAttribute(n) ? this.attrs[n] : null; };'
		. 'function all(root) { var out = []; (function walk(e) { out.push(e); e.children.forEach(walk); })(root); return out; }'
		. 'function match(e, sel) { var not = /:not\\(\\[([\\w-]+)\\]\\)$/.exec(sel); if (not && e.hasAttribute(not[1])) return false;'
		. ' var re = /\\[([\\w-]+)="([^"]*)"\\]/g, m, any = false; while ((m = re.exec(sel))) { any = true; if (e.getAttribute(m[1]) !== m[2]) return false; } return any; }'
		. 'var pageForm = new E("", {}), pageWrap = new E("sd-wrap", { "data-sd-id": "sd_8" }, [new E("sd-tb"), pageForm]);'
		. 'var menu = new E("dropdown-menu", {}), menuWrap = new E("sd-wrap", { "data-sd-id": "sd_8", "data-sw-sid": "7" }, [new E("sd-tb"), new E("sd-dz"), menu]);'
		. 'var self = new E("dropdown-menu", { "data-sd-id": "sd_9", "data-sd-self": "" });'
		. 'var body = new E("", {}, [pageWrap, new E("widget", {}, [menuWrap]), self]);'
		. 'var canvasDoc = { querySelector: function (sel) { return all(body).filter(function (e) { return match(e, sel); })[0] || null; } };'
		. 'var menuNode = N("semantic", { tag: "ul", cssClass: "dropdown-menu" }); menuNode._id = "sd_8";'
		. 'var formNode = N("semantic", { tag: "form" }); formNode._id = "sd_8";'
		. 'var selfNode = N("semantic", { tag: "ul", cssClass: "dropdown-menu" }); selfNode._id = "sd_9";'
		. 'var tree = N("root", {}, [formNode, selfNode]);'
		. 'var _sharedCache = { 7: { tree: N("root", {}, [N("semantic", { tag: "div", cssClass: "dropdown" }, [menuNode])]) } };'
		. 'function name(el) { return el === menu ? "widget menu" : el === pageForm ? "page form" : el === self ? "self" : String(el); }'
		. 'var lone = N("semantic", {}); lone._id = "sd_404";'
		. 'out([name(_sdCanvasInnerOf(menuNode)), name(_sdCanvasInnerOf(formNode)), name(_sdCanvasInnerOf(selfNode)), _sdCanvasInnerOf(lone), _sdCanvasInnerOf(null), _sdWrapInner(new E("sd-wrap", {}, [new E("sd-tb")]))]);'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('widget menu', 'page form', 'self', null, null, null), $answer, 'each node finds its own element; none, nothing');

	$ctx = _test_blocks_slice('    function updateCtxBar() {', "\n    }\n");
	pg_assert_true(substr_count($ctx, '_sdCanvasInnerOf(menuNode)') >= 2, 'the context bar reads and toggles the menu of the node it was drawn for');
	$props = _test_blocks_slice('    function propsDropdownOptions(n) {', "\n    }\n");
	pg_assert_true(strpos($props, 'var menuEl      = menuNode ? _sdCanvasInnerOf(menuNode) : null;') !== false, 'Expanded reads the menu of the node');
	$change = _test_blocks_slice('    function handleDropdownPropChange(ddProp, val) {', "\n    }\n\n    function btnGroup(");
	$open = strpos($change, 'var mel = _sdCanvasInnerOf(menuNode);');
	$bar = $open === false ? false : strpos($change, 'updateCtxBar();', $open);
	$next = strpos($change, "case 'alignment':");
	pg_assert_true($open !== false, 'Expanded opens the menu of the node');
	pg_assert_true($bar !== false && $next !== false && $bar < $next, 'Expanded redraws the context bar, whose Open / Close label reads the menu');
	foreach (array($ctx, $props, $change) as $body) {
		pg_assert_same(0, preg_match('/getCanvasInnerEl\((?:menuNode|mNode|triggerNode|node)\._id\)/', $body), 'no id lookup for the dropdown nodes');
	}
}

// The Expanded / Collapsed switches open or close a panel and flip every
// toggle with it; a toggle without aria-expanded gets one.
function test_designer_collapse_state_switch_writes_panel_and_toggles()
{
	$answer = designer_collapse_state_run(
		'var panel = sem("div", "accordion-collapse collapse", [], [], { id: "c1" });'
		. 'var b1 = ' . designer_collapse_state_button('c1', false) . ';'
		. 'var b2 = con("button", "btn", [{ name: "data-bs-toggle", value: "collapse" }, { name: "data-bs-target", value: "#c1" }]);'
		. 'var other = sem("div", "accordion-collapse collapse", [], [], { id: "c2" });'
		. 'var root = N("root", {}, [b1, b2, panel, other]);'
		. '_sdSetCollapseOpen(root, "c1", true);'
		. 'var opened = [cls(panel), cls(b1), at(b1, "aria-expanded"), cls(b2), at(b2, "aria-expanded"), cls(other)];'
		. '_normalizeToggleableShowState(root); var saved = cls(panel);'
		. '_sdSetCollapseOpen(root, "c1", false);'
		. 'out([opened, saved, [cls(panel), cls(b1), at(b1, "aria-expanded"), cls(b2), at(b2, "aria-expanded")]]);'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		array('accordion-collapse collapse show', 'accordion-button', 'true', 'btn', 'true', 'accordion-collapse collapse'),
		'accordion-collapse collapse show',
		array('accordion-collapse collapse', 'accordion-button collapsed', 'false', 'btn collapsed', 'false'),
	), $answer, 'open, kept by the save, then closed');
}

// Each accordion a palette builder writes: every button toggles a panel of
// the same tree (data-bs-toggle, data-bs-target, aria-controls), the panel
// names its accordion in data-bs-parent, button and panel agree on the open
// state, the first item is open, and a save keeps that state.
function test_designer_collapse_state_palette_accordions()
{
	$builders = array('accordion', 'faq-accordion', 'faq-categorized');
	$code = '';
	foreach ($builders as $ctype) {
		$at = _test_blocks_builder_at($ctype);
		$block = $at === false ? null : _test_blocks_block($at);
		pg_assert_true($block !== null, $ctype . ' has a builder');
		if ($block === null) {
			return;
		}
		$code .= 'builds.push(function (ctype) {' . substr(_test_blocks_js(), $at, $block[1] - $at + 1) . '}("' . $ctype . '"));';
	}

	$answer = designer_collapse_state_run(
		'var builds = [];' . $code
		. 'out(builds.map(function (root) {'
		. ' var nodes = []; (function w(n) { nodes.push(n); (n.children || []).forEach(w); })(root);'
		. ' var byId = {}; nodes.forEach(function (n) { var id = _sdNodeElementId(n); if (id) byId[id] = n; });'
		. ' var report = { items: 0, open: [], problems: [] };'
		. ' nodes.forEach(function (b) {'
		. '  if ((" " + cls(b) + " ").indexOf(" accordion-button ") === -1) return;'
		. '  report.items++;'
		. '  var id = _sdCollapseToggleTarget(b), panel = id ? byId[id] : null;'
		. '  if (!panel || (" " + cls(panel) + " ").indexOf(" accordion-collapse ") === -1) { report.problems.push("no panel: " + b.props.text); return; }'
		. '  if (at(b, "type") !== "button") report.problems.push("type: " + b.props.text);'
		. '  if (at(b, "aria-controls") !== id) report.problems.push("aria-controls: " + b.props.text);'
		. '  var parent = String(at(panel, "data-bs-parent") || "").replace(/^#/, "");'
		. '  if (!parent || !byId[parent] || (" " + cls(byId[parent]) + " ").indexOf(" accordion ") === -1) report.problems.push("data-bs-parent: " + b.props.text);'
		. '  var show = (" " + cls(panel) + " ").indexOf(" show ") !== -1;'
		. '  var collapsed = (" " + cls(b) + " ").indexOf(" collapsed ") !== -1;'
		. '  if (show !== (at(b, "aria-expanded") === "true") || show === collapsed) report.problems.push("state: " + b.props.text);'
		. '  report.open.push(show);'
		. ' });'
		. ' var before = JSON.stringify(root); _normalizeToggleableShowState(root);'
		. ' report.saveKeeps = JSON.stringify(root) === before;'
		. ' return report;'
		. '}));'
	);

	if ($answer === null) {
		return;
	}

	foreach ($builders as $i => $ctype) {
		$report = $answer[$i];
		pg_assert_true($report['items'] > 0, $ctype . ' writes accordion buttons');
		pg_assert_same(array(), $report['problems'], $ctype . ' buttons toggle their panels in step');
		pg_assert_true($report['open'][0], $ctype . ' first item is open');
		pg_assert_true($report['saveKeeps'], $ctype . ' a save keeps the built state');
	}
	pg_assert_same(array(true, false, false, false, false, false), $answer[1]['open'], 'faq-accordion: only the first item is open');
}
