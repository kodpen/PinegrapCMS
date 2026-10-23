<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the controls of a page that keep working on the visual editor's
 * canvas: a selected element inside a see-through container is lifted for
 * its outline without moving an element its own classes place (an alert's
 * close button stays in its corner and dismisses the alert); dropdowns are
 * opened by the editor from a guard on the canvas window, before
 * Bootstrap's document listeners.
 *
 * The canvas stylesheet (IFRAME_CSS) is cut out of
 * assets/js/style_designer.js and evaluated under Node; on a machine without
 * Node those tests are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_canvas_controls_js()
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	}
	return $js;
}

// Runs $script under Node and returns its JSON output decoded; null without
// Node.
function _test_canvas_controls_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$file = tempnam(sys_get_temp_dir(), 'pgcc');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 5)));
	return json_decode(implode("\n", $out), true);
}

// The canvas stylesheet rules, as the editor builds them (with the marker of
// the boxed-wrapper rules still in front).
function _test_canvas_controls_iframe_css()
{
	static $css = false;
	if ($css !== false) {
		return $css;
	}
	$js = _test_canvas_controls_js();
	$from = strpos($js, '    var _SD_BX = ');
	$to = strpos($js, '    // The canvas stylesheet for this design', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has its canvas stylesheet');
	$css = _test_canvas_controls_node('function _sdT(s) { return s; } function esc(s) { return s; }'
		. substr($js, $from, $to - $from)
		. 'process.stdout.write(JSON.stringify(IFRAME_CSS));');
	return $css;
}

// The selectors of a rule's selector list, split at the commas outside
// parentheses (a :not() list keeps its own).
function _test_canvas_controls_selectors($rule)
{
	$list = ltrim(substr($rule, 0, (int) strpos($rule, '{')), "\x01");
	$out = array();
	$depth = 0;
	$current = '';
	for ($i = 0, $n = strlen($list); $i < $n; $i++) {
		$c = $list[$i];
		if ($c === '(') {
			$depth++;
		} elseif ($c === ')') {
			$depth--;
		} elseif ($c === ',' && $depth === 0) {
			$out[] = trim($current);
			$current = '';
			continue;
		}
		$current .= $c;
	}
	$out[] = trim($current);
	return $out;
}

// A selected child of an alert, card body, navbar … is lifted
// (position:relative, z-index) above its neighbours, but an element placed
// by its own classes keeps its place: the close button of a dismissible
// alert jumped out of its corner on mousedown and the click hit the alert.
function test_designer_canvas_controls_selected_lift_skips_placed_elements()
{
	$css = _test_canvas_controls_iframe_css();
	if ($css === null) {
		return;
	}
	$lifts = array();
	foreach ($css as $rule) {
		if (strpos($rule, '.sd-selected >') !== false && preg_match('/\{[^}]*position:\s*relative/', $rule)) {
			$lifts[] = $rule;
		}
	}
	pg_assert_true(count($lifts) >= 2, 'the canvas lifts a selected child in the see-through containers');
	$containers = array();
	foreach ($lifts as $rule) {
		foreach (_test_canvas_controls_selectors($rule) as $selector) {
			if (!preg_match('/^\.([a-z-]+) > \.sd-wrap\.sd-selected > (.*)$/', $selector, $m)) {
				continue;
			}
			$containers[] = $m[1];
			foreach (array('.btn-close', '.position-absolute', '.position-fixed', '.position-sticky', '.sticky-top', '.fixed-top', '.stretched-link', '.sd-tb', '.sd-issue-badge') as $placed) {
				pg_assert_true(preg_match('/:not\([^)]*' . preg_quote($placed, '/') . '\b/', $m[2]) === 1, $m[1] . ': a selected ' . $placed . ' is not lifted');
			}
		}
	}
	foreach (array('alert', 'card-body', 'navbar', 'toast-header', 'btn-group', 'dropdown-menu') as $container) {
		pg_assert_true(in_array($container, $containers, true), 'a selected child of .' . $container . ' is still lifted');
	}
	// The outline itself is drawn on every selected child, a close button
	// included.
	$outlined = false;
	foreach ($css as $rule) {
		if (in_array('.alert > .sd-wrap.sd-selected > *', _test_canvas_controls_selectors($rule), true) && strpos($rule, 'outline:1px solid') !== false && strpos($rule, 'position:') === false) {
			$outlined = true;
		}
	}
	pg_assert_true($outlined, 'a selected child of an alert keeps its outline, without being moved');
}

// The body of the listener `$target.addEventListener('$type', function …`
// registered in the canvas set-up, up to its closing `}, true);`; '' when
// there is none.
function _test_canvas_controls_listener($target, $type)
{
	$js = _test_canvas_controls_js();
	$from = strpos($js, 'function _sdInitCanvasDoc(');
	$to = strpos($js, 'function _sdCanvasRealmReset(', (int) $from);
	if ($from === false || $to === false) {
		return '';
	}
	$setup = substr($js, $from, $to - $from);
	$at = strpos($setup, $target . ".addEventListener('" . $type . "', function");
	if ($at === false) {
		return '';
	}
	$end = strpos($setup, "}, true);", $at);
	return ($end === false) ? '' : substr($setup, $at, $end - $at);
}

// Bootstrap 5.3 puts its delegated data-api listeners on the document in the
// capture phase, so the editor's dropdown guard has to stop the click before
// the document: on the canvas window. A guard on the document ran beside
// Bootstrap, which threw on every toggle click and toggled the language
// switcher's menu back closed.
function test_designer_canvas_controls_dropdown_guard_on_window()
{
	$js = _test_canvas_controls_js();
	pg_assert_contains('var _cvWin = canvasDoc.defaultView;', $js, 'the guard sits on the canvas window');

	$click = _test_canvas_controls_listener('_cvWin', 'click');
	pg_assert_true($click !== '', 'the canvas window has a capture click listener');
	pg_assert_contains("closest('[data-bs-toggle=\"dropdown\"]')", $click, 'it takes dropdown toggles');
	pg_assert_contains('e.stopPropagation()', $click, 'it keeps the click from Bootstrap');
	pg_assert_contains("menu.classList.add('show')", $click, 'the editor opens the menu itself');

	$doc_click = _test_canvas_controls_listener('canvasDoc', 'click');
	pg_assert_true($doc_click !== '', 'the canvas document keeps its click guard for links and submits');
	pg_assert_false(strpos($doc_click, 'data-bs-toggle="dropdown"') !== false, 'no dropdown handling is left beside Bootstrap on the document');

	$keys = _test_canvas_controls_listener('_cvWin', 'keydown');
	pg_assert_true($keys !== '', 'the canvas window guards the dropdown keys');
	foreach (array("'Escape'", "'ArrowUp'", "'ArrowDown'") as $key) {
		pg_assert_contains($key, $keys, $key . ' is kept from Bootstrap');
	}
	pg_assert_contains('isContentEditable', $keys, 'a text written in place keeps its own key handling');
	pg_assert_contains('_sdShortcutKeydown(e)', $keys, 'the editor shortcuts still see the other keys');
}

// The dropdown key guard of the canvas window run under Node: ArrowDown and
// Escape typed into a dropdown item being written in place go to the edit's
// own key handler and never on to the document, where Bootstrap's handler
// cancelled the caret move and threw "Cannot read properties of undefined
// (reading 'parentNode')"; outside an edit the guard still closes the menus
// on Escape and hands the arrows to the editor's shortcuts.
function test_designer_canvas_controls_dropdown_keys_while_editing_in_place()
{
	$js = _test_canvas_controls_js();
	$keys = _test_canvas_controls_listener('_cvWin', 'keydown');
	pg_assert_true($keys !== '', 'the canvas window guards the dropdown keys');
	$helper = '';
	$from = strpos($js, 'function _sdDdEditKey(');
	$to = strpos($js, 'var _cvWin = canvasDoc.defaultView;');
	if ($from !== false && $to !== false && $to > $from) {
		$helper = substr($js, $from, $to - $from);
	}

	$script = <<<'JS'
var canvasDoc = { querySelector: function (s) { return s === '.dropdown-menu.show' ? {} : null; } };
function El(tag, cls, parent, editable) {
	this.tagName = tag; this.cls = cls || ''; this.parentNode = parent || canvasDoc;
	this.ownerDocument = canvasDoc; this.isContentEditable = !!editable;
}
El.prototype.matchesOne = function (s) {
	if (s === '[data-bs-toggle="dropdown"]') return / toggle /.test(' ' + this.cls + ' ');
	return s.charAt(0) === '.' && (' ' + this.cls + ' ').indexOf(' ' + s.slice(1) + ' ') !== -1;
};
El.prototype.closest = function (sel) {
	var list = sel.split(',').map(function (s) { return s.trim(); });
	for (var e = this; e && e !== canvasDoc; e = e.parentNode) {
		if (list.some(function (s) { return e.matchesOne(s); })) return e;
	}
	return null;
};
var log = [];
function _sdShortcutKeydown(e) { log.push('shortcut:' + e.key); }
function _sdDdCloseAll() { log.push('closeAll'); }
var listener = null;
var _cvWin = { addEventListener: function (t, f, cap) { if (t === 'keydown' && cap) listener = f; } };
HELPER
KEYS}, true);

var menu = new El('UL', 'dropdown-menu show');
var wrap = new El('DIV', 'sd-wrap', menu);
var item = new El('A', 'dropdown-item', wrap, false);
var toggle = new El('A', 'nav-link toggle');
var text = new El('P', '', null, false);
// The edit's handler as the editor writes it: Escape ends the edit and
// bubbles, every other key stays with the text.
function editHandler(e) {
	log.push('edit:' + e.key);
	if (e.key === 'Escape') { this.isContentEditable = false; return; }
	e.stopPropagation();
}
function press(target, key) {
	var ev = { key: key, target: target, cancelBubble: false, defaultPrevented: false,
		stopPropagation: function () { this.cancelBubble = true; },
		preventDefault: function () { this.defaultPrevented = true; } };
	log = [];
	listener(ev);
	return { reachesDocument: !ev.cancelBubble, prevented: ev.defaultPrevented, log: log.slice() };
}
var out = {};
item.isContentEditable = true; item._sdEditKeydown = editHandler;
out.editDown = press(item, 'ArrowDown');
out.editUp = press(item, 'ArrowUp');
out.editEsc = press(item, 'Escape');
item.isContentEditable = false;
out.itemEsc = press(item, 'Escape');
out.toggleDown = press(toggle, 'ArrowDown');
toggle.isContentEditable = true; toggle._sdEditKeydown = editHandler;
out.toggleEditDown = press(toggle, 'ArrowDown');
text.isContentEditable = true; text._sdEditKeydown = editHandler;
out.textEditDown = press(text, 'ArrowDown');
console.log(JSON.stringify(out));
JS;
	$script = str_replace(array('HELPER', 'KEYS'), array($helper, $keys), $script);
	$r = _test_canvas_controls_node($script);
	if ($r === null) {
		return;
	}

	foreach (array('editDown' => 'ArrowDown', 'editUp' => 'ArrowUp', 'toggleEditDown' => 'ArrowDown') as $case => $key) {
		pg_assert_false($r[$case]['reachesDocument'], $case . ': Bootstrap never sees the key typed into the edit');
		pg_assert_false($r[$case]['prevented'], $case . ': the caret still moves');
		pg_assert_same(array('edit:' . $key), $r[$case]['log'], $case . ': the edit handles the key, the menu stays as it is');
	}
	pg_assert_false($r['editEsc']['reachesDocument'], 'Escape in an edit never reaches Bootstrap');
	pg_assert_false($r['editEsc']['prevented'], 'the guard does not cancel Escape in an edit');
	pg_assert_same(array('edit:Escape', 'shortcut:Escape'), $r['editEsc']['log'], 'Escape ends the edit and goes on to the shortcuts, the menu stays open');

	pg_assert_false($r['itemEsc']['reachesDocument'], 'outside an edit Escape is still kept from Bootstrap');
	pg_assert_true($r['itemEsc']['prevented'], 'outside an edit Escape is taken by the guard');
	pg_assert_same(array('closeAll'), $r['itemEsc']['log'], 'outside an edit Escape closes the open menus');
	pg_assert_false($r['toggleDown']['reachesDocument'], 'an arrow on a toggle is kept from Bootstrap');
	pg_assert_same(array('shortcut:ArrowDown'), $r['toggleDown']['log'], 'an arrow on a toggle goes on to the shortcuts');

	pg_assert_true($r['textEditDown']['reachesDocument'], 'a text edited outside a dropdown is left to its own listeners');
	pg_assert_same(array(), $r['textEditDown']['log'], 'the guard does not touch a text outside a dropdown');
}

// Both in-place editors hand their key handler to the guard, and the
// handler of an ended session lets the keys go: a dropdown item (a link)
// keeps the focus after its edit, and the old listener stopped Ctrl+Z.
function test_designer_canvas_controls_edit_key_handlers_wired()
{
	$js = _test_canvas_controls_js();
	pg_assert_contains('item.el._sdEditKeydown = _ieKeydown;', $js, 'the element edit gives the guard its key handler');
	pg_assert_contains('s._sdEditKeydown = _vtKeydown;', $js, 'the button label edit gives the guard its key handler');
	$at = strpos($js, 'var _ieKeydown = function (ke) {');
	pg_assert_true($at !== false, 'the element edit names its key handler');
	$head = substr($js, (int) $at, 600);
	$ended = strpos($head, 'if (isCommitted) return;');
	pg_assert_true($ended !== false && $ended < strpos($head, "if (ke.key === 'Escape')"), 'an ended edit leaves the keys alone');
}

// A click inside a control the canvas runs is let through to it, on its icon
// or its label too: the theme toggle's moon icon did nothing (the icon's
// wrapper stopped the click before the button's own listener), and the
// Popover button never opened (its label span stopped the click Bootstrap's
// Popover listens for on the element itself).
function test_designer_canvas_controls_inner_clicks_reach_the_control()
{
	$js = _test_canvas_controls_js();
	pg_assert_true(preg_match("/var _SD_CANVAS_CONTROLS = ((?:'[^']*'\\s*\\+?\\s*)+);/", $js, $m) === 1, 'the designer names the controls the canvas runs');
	preg_match_all("/'([^']*)'/", $m[1], $parts);
	$controls = array_map('trim', explode(',', implode('', $parts[1])));
	foreach (array('[data-bs-toggle]', '[data-bs-target]', '[data-bs-dismiss]', '[data-bs-slide]', '[data-bs-slide-to]', '[data-bs-theme-value]', '[data-pg-theme-switcher] button', '[data-pg-scroll]') as $control) {
		pg_assert_true(in_array($control, $controls, true), $control . ' is a control the canvas runs');
	}

	// The element wrapper and the shared component wrapper treat a click in
	// one as interactive: they select without stopping it.
	pg_assert_true(preg_match('/var isInteractive = !!e\.target\.closest\([^;]*_SD_CANVAS_CONTROLS/s', $js) === 1, 'a click inside a control is interactive for the element wrapper');
	pg_assert_true(preg_match('/_tgt\.closest\(_SD_CANVAS_CONTROLS\)\)\s*\{\s*return;\s*\}\s*e\.stopPropagation\(\);/', $js) === 1, 'the shared component wrapper leaves a click inside a control alone');

	// The label of a button: a control's label lets the click go on, a plain
	// button's label still keeps it.
	$from = strpos($js, 'function _createVirtualText(');
	pg_assert_true($from !== false, 'the designer draws button labels as virtual text');
	$body = substr($js, (int) $from, 3000);
	$at = strpos($body, "s.addEventListener('click', function(e) {");
	pg_assert_true($at !== false, 'the label has a click listener');
	$click = substr($body, (int) $at, (int) strpos($body, '});', (int) $at) - (int) $at);
	pg_assert_contains('closest(_SD_CANVAS_CONTROLS)', $click, 'the label knows the controls');
	pg_assert_true(strpos($click, 'return;') < strpos($click, 'e.stopPropagation()'), 'a control gets the click before the label would stop it');
	pg_assert_contains('e._sdHandled = true', $click, 'the wrappers around a control do not select its parent instead');
}

// assets/js/pg_theme_switcher.js run under Node on a small stand-in for the
// DOM: $markup is a list of switchers ({kind, editor}: editor puts the
// editor's toolbar, with its .bi drag handle, in front of the toggle's
// icon), $setup runs before the file (window, document, root and storage in
// scope) and $steps after it; the script's JSON output is returned. null
// without Node.
function _test_canvas_controls_engine($markup, $setup, $steps)
{
	$engine = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_theme_switcher.js');
	$dom = <<<'JS'
function El(tag, attrs, kids) {
	this.tagName = tag.toUpperCase(); this.attrs = Object.assign({}, attrs || {});
	this.children = []; this.parentElement = null; this.listeners = {}; this.checked = false;
	var self = this; (kids || []).forEach(function (k) { k.parentElement = self; self.children.push(k); });
}
Object.defineProperty(El.prototype, 'className', {
	get: function () { return this.attrs['class'] || ''; },
	set: function (v) { this.attrs['class'] = String(v); }
});
Object.defineProperty(El.prototype, 'classList', { get: function () {
	var el = this, list = function () { return el.className.split(/\s+/).filter(Boolean); };
	return {
		contains: function (c) { return list().indexOf(c) !== -1; },
		toggle: function (c, on) { var l = list().filter(function (x) { return x !== c; }); if (on) l.push(c); el.className = l.join(' '); },
		remove: function (c) { el.className = list().filter(function (x) { return x !== c; }).join(' '); }
	};
} });
El.prototype.getAttribute = function (n) { return (n in this.attrs) ? this.attrs[n] : null; };
El.prototype.setAttribute = function (n, v) { this.attrs[n] = String(v); };
El.prototype.removeAttribute = function (n) { delete this.attrs[n]; };
El.prototype.addEventListener = function (t, f) { (this.listeners[t] = this.listeners[t] || []).push(f); };
El.prototype.fire = function (t) { var self = this; (this.listeners[t] || []).forEach(function (f) { f.call(self, { type: t, preventDefault: function () {} }); }); };
El.prototype.is = function (simple) {
	var el = this, m = simple.match(/^([a-z]*)((?:\.[\w-]+|\[[^\]]+\])*)$/);
	if (!m) throw new Error('selector ' + simple);
	if (m[1] && el.tagName !== m[1].toUpperCase()) return false;
	return (m[2].match(/\.[\w-]+|\[[^\]]+\]/g) || []).every(function (a) {
		if (a.charAt(0) === '.') return el.classList.contains(a.slice(1));
		var p = a.slice(1, -1).match(/^([\w-]+)(?:="([^"]*)")?$/);
		return p[2] === undefined ? el.getAttribute(p[1]) !== null : el.getAttribute(p[1]) === p[2];
	});
};
// Simple selectors, lists and the descendant combinator.
El.prototype.matches = function (sel) {
	var el = this;
	return sel.split(',').some(function (one) {
		var parts = one.trim().split(/\s+/);
		if (!el.is(parts.pop())) return false;
		for (var e = el.parentElement; parts.length && e; e = e.parentElement) {
			if (e.is(parts[parts.length - 1])) parts.pop();
		}
		return !parts.length;
	});
};
El.prototype.querySelectorAll = function (sel) {
	var out = [];
	(function walk(e) { e.children.forEach(function (k) { if (k.matches(sel)) out.push(k); walk(k); }); })(this);
	return out;
};
El.prototype.querySelector = function (sel) { return this.querySelectorAll(sel)[0] || null; };
El.prototype.closest = function (sel) { for (var e = this; e; e = e.parentElement) { if (e.matches(sel)) return e; } return null; };
El.prototype.contains = function (x) { for (var e = x; e; e = e.parentElement) { if (e === this) return true; } return false; };
function h(tag, attrs, kids) { return new El(tag, attrs, kids); }

var store = {};
var storage = {
	getItem: function (k) { return (k in store) ? store[k] : null; },
	setItem: function (k, v) { store[k] = String(v); }
};
var docListeners = {};
var body = h('body', {}, MARKUP.map(function (sw) {
	if (sw.kind === 'toggle') {
		var icon = h('i', { 'class': 'bi bi-moon-stars-fill' });
		var inner = sw.editor ? h('div', { 'class': 'sd-wrap' }, [h('div', { 'class': 'sd-tb' }, [h('span', { 'class': 'bi bi-arrows-move' })]), icon]) : icon;
		return h('div', { 'data-pg-theme-switcher': 'toggle' }, [h('button', { type: 'button' }, [inner])]);
	}
	if (sw.kind === 'switch') {
		return h('div', { 'data-pg-theme-switcher': 'switch' }, [h('input', { type: 'checkbox' })]);
	}
	return h('div', { 'data-pg-theme-switcher': 'dropdown' }, [h('i', { 'class': 'bi pg-active-icon' })].concat(['light', 'dark', 'auto'].map(function (v) {
		return h('button', { type: 'button', 'data-bs-theme-value': v }, [h('i', { 'class': 'pg-check d-none' })]);
	})));
}));
var root = h('html', {}, [body]);
var changes = 0;
var document = {
	documentElement: root, body: body, readyState: 'complete',
	addEventListener: function (t, f) { (docListeners[t] = docListeners[t] || []).push(f); },
	dispatchEvent: function (ev) { (docListeners[ev.type] || []).forEach(function (f) { f(ev); }); },
	querySelectorAll: function (sel) { return root.querySelectorAll(sel); }
};
document.addEventListener('pg-theme-change', function () { changes++; });
var window = {
	localStorage: storage,
	matchMedia: function () { return { matches: false, addEventListener: function () {} }; }
};
window.window = window;
window.document = document;
document.defaultView = window;
function view() {
	var t = root.querySelector('[data-pg-theme-switcher="toggle"] button');
	var s = root.querySelector('[data-pg-theme-switcher="switch"] input');
	var d = root.querySelector('[data-pg-theme-switcher="dropdown"] [data-bs-theme-value].active');
	return {
		html: root.getAttribute('data-bs-theme'), stored: storage.getItem('pg-theme'), changes: changes,
		toggleIcon: t ? t.querySelectorAll('i').pop().className : null,
		handle: t && t.querySelector('.sd-tb .bi') ? t.querySelector('.sd-tb .bi').className : null,
		pressed: t ? t.getAttribute('aria-pressed') : null,
		switchOn: s ? s.checked : null,
		menu: d ? d.getAttribute('data-bs-theme-value') : null
	};
}
var out = [];
JS;
	$script = str_replace('MARKUP', json_encode($markup), $dom)
		. $setup . "\n"
		. "(function (window, document) {\n" . $engine . "\n}).call(window, window, document);\n"
		. "var pgSetTheme = window.pgSetTheme;\n"
		. $steps . "\n"
		. 'process.stdout.write(JSON.stringify(out));';
	return _test_canvas_controls_node($script);
}

// The theme toggle on the canvas: the editor's toolbar sits in the button in
// front of the icon, and its drag handle is a .bi too. The toggle drew the
// sun on that handle and kept the moon on its own icon.
function test_designer_canvas_controls_theme_toggle_icon_beside_editor_chrome()
{
	$out = _test_canvas_controls_engine(
		array(array('kind' => 'toggle', 'editor' => true)),
		'',
		"var b = root.querySelector('[data-pg-theme-switcher=\"toggle\"] button');\n"
		. "b.fire('click'); out.push(view());\n"
		. "b.fire('click'); out.push(view());"
	);
	if ($out === null) {
		return;
	}
	pg_assert_same('dark', $out[0]['html'], 'a click on the toggle turns the page dark');
	pg_assert_same('bi bi-sun-fill', $out[0]['toggleIcon'], 'the toggle\'s own icon shows the sun');
	pg_assert_same('bi bi-arrows-move', $out[0]['handle'], 'the editor\'s drag handle keeps its icon');
	pg_assert_same('light', $out[1]['html'], 'a second click turns it light again');
	pg_assert_same('bi bi-moon-stars-fill', $out[1]['toggleIcon'], 'and the moon comes back');

	// On the page (no editor toolbar) the toggle is unchanged.
	$out = _test_canvas_controls_engine(
		array(array('kind' => 'toggle', 'editor' => false)),
		'',
		"root.querySelector('[data-pg-theme-switcher=\"toggle\"] button').fire('click'); out.push(view());"
	);
	pg_assert_same('bi bi-sun-fill', $out[0]['toggleIcon'], 'the page\'s toggle shows the sun when dark');
	pg_assert_same('true', $out[0]['pressed'], 'and is pressed');
}

// The editor's canvas theme code (applyCanvasTheme() and its two helpers),
// cut out of assets/js/style_designer.js, set up as JavaScript on the stand-in
// canvas document of _test_canvas_controls_engine(): `ed.apply(mode, opts)`,
// the toolbar button `tb`, the Themes panel's mode in `panelMode`; the canvas
// document's pg-theme-change reaches the editor as in _sdInitCanvasDoc().
function _test_canvas_controls_editor_theme_js()
{
	$js = _test_canvas_controls_js();
	$from = strpos($js, '    function applyCanvasTheme(');
	$to = strpos($js, '    // ========================= IFRAME SETUP', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has its canvas theme code');
	return "var tbIcon = { className: 'bi bi-moon' }, tbTxt = { textContent: 'Dark' };\n"
		. "var tb = { active: false, classList: { toggle: function (c, on) { tb.active = !!on; } }, querySelector: function (s) { return s === '.bi' ? tbIcon : tbTxt; } };\n"
		. "var tabs = ['light', 'dark'].map(function (m) { var t = { on: m === 'light', dataset: { themeMode: m } }; t.classList = { toggle: function (c, on) { t.on = !!on; } }; return t; });\n"
		. "var editorDoc = { getElementById: function (id) { return id === 'sd-vb-theme' ? tb : null; }, querySelectorAll: function () { return tabs; } };\n"
		. "var panelMode = null, _assetsApi = { setThemeMode: function (m) { panelMode = m; } };\n"
		. "var canvasDoc = document, canvasDarkTheme = (root.getAttribute('data-bs-theme') === 'dark');\n"
		. "function _sdT(s) { return s; }\n"
		. "var ed = {};\n"
		. "(function (document) {\n" . substr($js, (int) $from, (int) $to - (int) $from) . "\n"
		. "ed.apply = applyCanvasTheme; ed.changed = _sdCanvasThemeChanged;\n"
		. "})(editorDoc);\n"
		. "document.addEventListener('pg-theme-change', ed.changed);\n"
		. "function editor() { var v = view(); v.dark = canvasDarkTheme; v.button = tb.active; v.label = tbTxt.textContent; v.panel = panelMode; v.tab = tabs[1].on ? 'dark' : 'light'; v.body = body.getAttribute('data-bs-theme'); return v; }\n";
}

// The editor's Dark/Light view and the theme switchers on the canvas are one
// state, on the canvas <html>, and it never reaches the site's localStorage
// (the canvas shares the site's origin). The view sat on <body>, so a
// switcher choosing light left the canvas dark, and the toolbar clicked the
// canvas switchers, which stored the editor's view as the visitor's theme.
function test_designer_canvas_controls_theme_view_is_one_state_never_stored()
{
	$markup = array(array('kind' => 'toggle', 'editor' => true), array('kind' => 'switch'), array('kind' => 'dropdown'));
	$out = _test_canvas_controls_engine(
		$markup,
		// The editor's view is dark when the engine arrives, and the canvas
		// loader asks for the no-store mode.
		"root.setAttribute('data-bs-theme', 'dark'); window.pgThemeNoStore = true;",
		_test_canvas_controls_editor_theme_js()
		. "out.push(editor());\n"
		. "ed.apply('light', { source: 'toolbar' }); out.push(editor());\n"
		. "ed.apply('dark', { source: 'toolbar' }); out.push(editor());\n"
		. "var input = root.querySelector('[data-pg-theme-switcher=\"switch\"] input'); input.checked = false; input.fire('change'); out.push(editor());\n"
		. "root.querySelector('[data-pg-theme-switcher=\"dropdown\"] [data-bs-theme-value=\"dark\"]').fire('click'); out.push(editor());\n"
		. "ed.apply('light', { source: 'themes' }); out.push(editor());"
	);
	if ($out === null) {
		return;
	}
	list($start, $light, $dark, $switched, $menu, $panel) = $out;

	pg_assert_same('dark', $start['html'], 'the engine starts from the editor\'s view on <html>');
	pg_assert_same(true, $start['switchOn'], 'the switch shows the view');
	pg_assert_same('dark', $start['menu'], 'the menu shows the view');

	pg_assert_same('light', $light['html'], 'the toolbar\'s Light puts the canvas light');
	pg_assert_same(false, $light['switchOn'], 'the switch follows the toolbar');
	pg_assert_same('bi bi-moon-stars-fill', $light['toggleIcon'], 'the toggle follows the toolbar');
	pg_assert_same('light', $light['panel'], 'the Themes panel follows the toolbar');
	pg_assert_same('dark', $dark['html'], 'the toolbar\'s Dark puts the canvas dark');
	pg_assert_same(null, $dark['body'], 'the view is never written on <body>, where it would override <html>');

	pg_assert_same('light', $switched['html'], 'the canvas switch puts the canvas light');
	pg_assert_same(false, $switched['dark'], 'the editor\'s view follows the switch');
	pg_assert_same(false, $switched['button'], 'the toolbar button is no longer pressed');
	pg_assert_same('Dark', $switched['label'], 'the toolbar button offers Dark again');
	pg_assert_same('light', $switched['tab'], 'the Themes panel mode tab follows the switch');
	pg_assert_same('light', $switched['panel'], 'the Themes panel shows the light values');

	pg_assert_same('dark', $menu['html'], 'the menu\'s Dark puts the canvas dark');
	pg_assert_same(true, $menu['dark'], 'and the editor\'s view follows it');
	pg_assert_same('light', $panel['html'], 'the Themes panel\'s mode tab puts the canvas light');

	foreach ($out as $i => $step) {
		pg_assert_same(null, $step['stored'], 'step ' . $i . ': nothing is written to the site\'s localStorage');
	}

	// On the published page the engine keeps the visitor's choice as before.
	$out = _test_canvas_controls_engine(
		array(array('kind' => 'toggle', 'editor' => false)),
		"storage.setItem('pg-theme', 'dark');",
		"out.push(view()); root.querySelector('[data-pg-theme-switcher=\"toggle\"] button').fire('click'); out.push(view());"
	);
	pg_assert_same('dark', $out[0]['html'], 'the page opens in the stored theme');
	pg_assert_same('light', $out[1]['html'], 'the toggle switches it');
	pg_assert_same('light', $out[1]['stored'], 'and stores the visitor\'s choice');
}

// Where the editor keeps the view and how the canvas engine is loaded.
function test_designer_canvas_controls_theme_view_wiring()
{
	$js = _test_canvas_controls_js();
	$from = strpos($js, '    function applyCanvasTheme(');
	$to = strpos($js, '    // ========================= IFRAME SETUP', (int) $from);
	$theme = substr($js, (int) $from, (int) $to - (int) $from);
	pg_assert_false(strpos($theme, '.click()') !== false, 'the editor never clicks the canvas switchers');
	pg_assert_false(strpos($theme, 'dispatchEvent') !== false, 'nor fires their change events');
	pg_assert_false(strpos($theme, "body.setAttribute('data-bs-theme'") !== false, 'the view is not written on <body>');

	$from = strpos($js, 'function _sdThemeSwitchers()');
	$loader = substr($js, (int) $from, 1200);
	$flag = strpos($loader, 'win.pgThemeNoStore = true');
	pg_assert_true($flag !== false, 'the canvas loads the engine in its no-store mode');
	pg_assert_true($flag < strpos($loader, 'canvasDoc.head.appendChild(scr)'), 'the flag is set before the file runs');

	pg_assert_contains("canvasDoc.addEventListener('pg-theme-change', _sdCanvasThemeChanged);", $js, 'the editor follows a switcher clicked on the canvas');
	pg_assert_contains("<html lang=\"' + esc(_sdDocLang()) + '\"' + (canvasDarkTheme ? ' data-bs-theme=\"dark\"' : '') + '>", $js, 'a fresh canvas document opens in the view, on <html>');
	pg_assert_contains("'html[data-bs-theme=\"dark\"] .sd-dz {", $js, 'the dark drop zones follow the view on <html>');

	$engine = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_theme_switcher.js');
	pg_assert_contains('window.pgThemeNoStore', $engine, 'the engine knows the no-store mode');
}
