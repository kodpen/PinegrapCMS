<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for assets/js/pg_ui.js, the adapter between panel code and
 * Bootstrap's JavaScript, run under Node against stand-ins for Bootstrap 5
 * and 6 and a small fake document: the component names, the option mapping,
 * the promises of show and hide, listening to both event names, the dialog
 * markup of each version, the overlay host and the readiness promise.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs the scenarios below with Node and returns their JSON; null when there
// is no Node, array('error' => output) when the script fails or prints
// something that is not JSON.
function pg_ui_test_run()
{
	static $result = false;

	if ($result !== false) {
		return $result;
	}

	if (!function_exists('exec')) {
		return $result = null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return $result = null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgui');

	if ($file === false) {
		return $result = null;
	}

	$script = 'var PG_UI_SOURCE = require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_ui.js') . ', "utf8");' . "\n"
		. pg_ui_test_harness();
	file_put_contents($file, $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$text = implode("\n", $out);
	$json = json_decode($text, true);

	if ($code !== 0 || !is_array($json)) {
		return $result = array('error' => $text);
	}

	return $result = $json;
}

// The Node side: a fake document, Bootstrap 5 and 6 stand-ins, the
// scenarios. Each scenario loads pg_ui.js into a window of its own.
function pg_ui_test_harness()
{
	return <<<'JS'
function FakeElement(doc, tag) {
    var self = this;
    this.ownerDocument = doc;
    this.tagName = String(tag).toUpperCase();
    this.attributes = {};
    this.children = [];
    this.parentNode = null;
    this.listeners = {};
    this.className = '';
    this.open = false;
    this.textContent = '';
    this.id = '';
    this.classList = {
        add: function (c) { if (!self.classList.contains(c)) { self.className = (self.className + ' ' + c).trim(); } },
        remove: function (c) { self.className = self.className.split(/\s+/).filter(function (x) { return x !== c; }).join(' '); },
        contains: function (c) { return self.className.split(/\s+/).indexOf(c) !== -1; }
    };
    doc.all.push(this);
}
FakeElement.prototype.setAttribute = function (k, v) { this.attributes[k] = String(v); };
FakeElement.prototype.getAttribute = function (k) { return Object.prototype.hasOwnProperty.call(this.attributes, k) ? this.attributes[k] : null; };
FakeElement.prototype.appendChild = function (c) { c.parentNode = this; this.children.push(c); return c; };
FakeElement.prototype.addEventListener = function (t, f) { (this.listeners[t] = this.listeners[t] || []).push(f); };
FakeElement.prototype.removeEventListener = function (t, f) { this.listeners[t] = (this.listeners[t] || []).filter(function (x) { return x !== f; }); };
FakeElement.prototype.fire = function (t) { var self = this; (this.listeners[t] || []).slice().forEach(function (f) { f.call(self, { type: t }); }); };
FakeElement.prototype.matches = function (sel) {
    var self = this;
    return sel.split(',').some(function (one) {
        one = one.trim();
        var m = one.match(/^([a-z]+)?((?:\.[\w-]+)*)(\[open\])?$/);
        if (!m) { return false; }
        if (m[1] && self.tagName !== m[1].toUpperCase()) { return false; }
        if (m[3] && !self.open) { return false; }
        return (m[2] || '').split('.').filter(Boolean).every(function (c) { return self.classList.contains(c); });
    });
};
FakeElement.prototype.closest = function (sel) {
    for (var el = this; el; el = el.parentNode) { if (el.matches && el.matches(sel)) { return el; } }
    return null;
};

function makeDocument() {
    var doc = { all: [], listeners: {} };
    doc.createElement = function (tag) { return new FakeElement(doc, tag); };
    doc.body = doc.createElement('body');
    doc.querySelectorAll = function (sel) { return doc.all.filter(function (el) { return el.matches(sel); }); };
    doc.querySelector = function (sel) { return doc.querySelectorAll(sel)[0] || null; };
    doc.addEventListener = function (t, f) { (doc.listeners[t] = doc.listeners[t] || []).push(f); };
    doc.fire = function (t, detail) { (doc.listeners[t] || []).forEach(function (f) { f({ type: t, detail: detail }); }); };
    return doc;
}

// A component stand-in: one instance per element and name, show/hide flip
// the open state and fire the version's events.
function makeComponent(name, ev, six) {
    function C(el, opts) { this.el = el; this.opts = opts; this.name = name; }
    C.getOrCreateInstance = function (el, opts) {
        el.instances = el.instances || {};
        if (!el.instances[name]) { el.instances[name] = new C(el, opts); }
        return el.instances[name];
    };
    C.getInstance = function (el) { return (el.instances && el.instances[name]) || null; };
    C.prototype.show = function () {
        if (this.el.tagName === 'DIALOG') { this.el.open = true; } else { this.el.classList.add('show'); }
        this.el.fire('shown.bs.' + ev);
        return six ? Promise.resolve('v6-promise') : undefined;
    };
    C.prototype.hide = function () {
        if (this.el.tagName === 'DIALOG') { this.el.open = false; } else { this.el.classList.remove('show'); }
        this.el.fire('hidden.bs.' + ev);
        return six ? Promise.resolve('v6-promise') : undefined;
    };
    C.prototype.toggle = function () { return (this.el.open || this.el.classList.contains('show')) ? this.hide() : this.show(); };
    C.prototype.dispose = function () { delete this.el.instances[name]; };
    return C;
}

function makeBootstrap(v) {
    var b = {};
    if (v === 5) {
        b.Modal = makeComponent('Modal', 'modal', false);
        b.Offcanvas = makeComponent('Offcanvas', 'offcanvas', false);
        b.Dropdown = makeComponent('Dropdown', 'dropdown', false);
    } else {
        b.Dialog = makeComponent('Dialog', 'dialog', true);
        b.Drawer = makeComponent('Drawer', 'drawer', true);
        b.Menu = makeComponent('Menu', 'menu', true);
        b.Chips = makeComponent('Chips', 'chips', true);
    }
    ['Tooltip', 'Popover', 'Collapse', 'Tab', 'Toast'].forEach(function (n) { b[n] = makeComponent(n, n.toLowerCase(), v === 6); });
    return b;
}

function load(v) {
    var doc = makeDocument();
    var win = { document: doc, setTimeout: setTimeout, clearTimeout: clearTimeout };
    if (v) { win.bootstrap = makeBootstrap(v); }
    new Function('window', 'document', PG_UI_SOURCE)(win, doc);
    return win;
}

async function main() {
    var out = {};

    // Bootstrap 5.
    var w5 = load(5), ui = w5.pgUI, d5 = w5.document;
    out.v5_version = ui.version();
    var modal = d5.createElement('div');
    modal.className = 'modal fade';
    out.v5_ctor = ui.get('dialog', modal).name;
    out.v5_options = ui.options('menu', { floatingConfig: { strategy: 'fixed' } });
    var hiddenTypes = [];
    var off = ui.on(modal, 'dialog', 'hidden', function (e) { hiddenTypes.push(e.type); });
    var shown = await ui.show('dialog', modal);
    out.v5_show = { resolved: shown && shown.name, open: ui.isOpen('dialog', modal), any: ui.anyOpen('dialog') };
    await ui.dialog.hide(modal);
    modal.fire('hidden.bs.dialog');
    off();
    modal.fire('hidden.bs.modal');
    out.v5_events = hiddenTypes;
    var onceCount = 0;
    ui.on(modal, 'dialog', 'shown', function () { onceCount++; }, { once: true });
    await ui.show('dialog', modal);
    await ui.hide('dialog', modal);
    await ui.show('dialog', modal);
    out.v5_once = onceCount;
    out.v5_toggle_closes = (await ui.toggle('dialog', modal), ui.isOpen('dialog', modal));
    var parts = ui.dialog.build({ id: 'x', title: 'T', size: 'lg', centered: true, backdrop: 'static', keyboard: false, closeLabel: 'Close' });
    out.v5_build = {
        root: parts.root.tagName + '.' + parts.root.className,
        shell: parts.root.children[0].className,
        content: parts.root.children[0].children[0].className,
        header: parts.header.className, title: parts.title.textContent, body: parts.body.className,
        footer: parts.footer.className, dismiss: parts.close.getAttribute('data-bs-dismiss'),
        label: parts.close.getAttribute('aria-label'), backdrop: parts.root.getAttribute('data-bs-backdrop'),
        keyboard: parts.root.getAttribute('data-bs-keyboard'), id: parts.root.id
    };
    out.v5_host_is_body = ui.overlayHost(modal) === d5.body;
    out.v5_ready = (await w5.pgBootstrapReady) === w5.bootstrap;
    try { ui.names('carousel'); out.unknown_kind = 'no error'; } catch (e) { out.unknown_kind = e.message; }

    // Bootstrap 6.
    var w6 = load(6), ui6 = w6.pgUI, d6 = w6.document;
    out.v6_version = ui6.version();
    var dlg = d6.createElement('dialog');
    dlg.className = 'dialog';
    out.v6_ctor = ui6.get('dialog', dlg).name;
    out.v6_menu_ctor = ui6.get('menu', d6.createElement('button')).name;
    out.chips_ctor = [ui6.get('chips', d6.createElement('div')).name, ui.get('chips', d5.createElement('div'))];
    out.v6_options = ui6.options('tooltip', { popperConfig: { strategy: 'fixed' }, placement: 'auto' });
    out.v6_collapse_options = ui6.options('collapse', { toggle: false, parent: '#a' });
    var v6Types = [];
    ui6.on(dlg, 'dialog', 'shown', function (e) { v6Types.push(e.type); });
    await ui6.show('dialog', dlg);
    out.v6_open = { open: dlg.open, isOpen: ui6.isOpen('dialog', dlg), any: ui6.anyOpen('dialog'), events: v6Types };
    var inside = d6.createElement('span');
    dlg.appendChild(inside);
    out.v6_host = { inside: ui6.overlayHost(inside) === dlg, outside_goes_to_top: ui6.overlayHost(d6.createElement('i')) === dlg };
    await ui6.hide('dialog', dlg);
    out.v6_host_after_close_is_body = ui6.overlayHost(inside) === d6.body;
    var parts6 = ui6.dialog.build({ title: 'T', size: 'xl', scrollable: true, centered: true, footer: false });
    out.v6_build = {
        root: parts6.root.tagName + '.' + parts6.root.className,
        header: parts6.header.className, body: parts6.body.className, footer: parts6.footer,
        dismiss: parts6.close.getAttribute('data-bs-dismiss'), children: parts6.root.children.length
    };

    // No Bootstrap yet: the module bridge announces it later.
    var w0 = load(0);
    out.none_version = w0.pgUI.version();
    out.none_get = w0.pgUI.get('dialog', w0.document.createElement('div'));
    var late = makeBootstrap(6);
    setTimeout(function () { w0.bootstrap = late; w0.document.fire('pg:bootstrap-ready', late); }, 5);
    out.none_ready = (await w0.pgBootstrapReady) === late;

    console.log(JSON.stringify(out));
}

main().catch(function (e) { console.log('FAILED ' + (e && e.stack || e)); process.exit(1); });
JS;
}

// Skip only without Node; anything else that is not JSON is a failure.
function pg_ui_test_result()
{
	$r = pg_ui_test_run();

	if (is_array($r) && isset($r['error'])) {
		pg_assert_same('', $r['error'], 'pg_ui.js under Node');
	}

	return $r;
}

function test_pg_ui_names_and_options()
{
	$r = pg_ui_test_result();
	if (!is_array($r) || isset($r['error'])) {
		return;
	}

	pg_assert_same(5, $r['v5_version']);
	pg_assert_same('Modal', $r['v5_ctor'], 'dialog is Modal under 5');
	pg_assert_same(6, $r['v6_version']);
	pg_assert_same('Dialog', $r['v6_ctor'], 'dialog is Dialog under 6');
	pg_assert_same('Menu', $r['v6_menu_ctor'], 'menu is Menu under 6');
	pg_assert_same(array('Chips', null), $r['chips_ctor'], 'chips only under 6');
	pg_assert_same(array('popperConfig' => array('strategy' => 'fixed')), $r['v5_options'], 'floatingConfig read as popperConfig by 5');
	pg_assert_same(array('placement' => 'auto', 'floatingConfig' => array('strategy' => 'fixed')), $r['v6_options'], 'popperConfig read as floatingConfig by 6');
	pg_assert_same(array('parent' => '#a'), $r['v6_collapse_options'], 'v6 Collapse has no toggle option');
	pg_assert_same('pgUI: unknown component "carousel"', $r['unknown_kind']);
}

function test_pg_ui_show_hide_and_events()
{
	$r = pg_ui_test_result();
	if (!is_array($r) || isset($r['error'])) {
		return;
	}

	pg_assert_same(array('resolved' => 'Modal', 'open' => true, 'any' => true), $r['v5_show'], 'v5 show settles on shown.bs.modal');
	pg_assert_same(array('hidden.bs.modal', 'hidden.bs.dialog'), $r['v5_events'], 'both event names, none after off()');
	pg_assert_same(1, $r['v5_once'], 'once fires once');
	pg_assert_same(false, $r['v5_toggle_closes']);
	pg_assert_same(array('open' => true, 'isOpen' => true, 'any' => true, 'events' => array('shown.bs.dialog')), $r['v6_open']);
}

function test_pg_ui_build_dialog()
{
	$r = pg_ui_test_result();
	if (!is_array($r) || isset($r['error'])) {
		return;
	}

	pg_assert_same(array(
		'root' => 'DIV.modal fade',
		'shell' => 'modal-dialog modal-lg modal-dialog-centered',
		'content' => 'modal-content',
		'header' => 'modal-header',
		'title' => 'T',
		'body' => 'modal-body',
		'footer' => 'modal-footer',
		'dismiss' => 'modal',
		'label' => 'Close',
		'backdrop' => 'static',
		'keyboard' => 'false',
		'id' => 'x',
	), $r['v5_build']);

	pg_assert_same(array(
		'root' => 'DIALOG.dialog dialog-xl pg-dialog-centered dialog-scrollable',
		'header' => 'dialog-header',
		'body' => 'dialog-body',
		'footer' => null,
		'dismiss' => 'dialog',
		'children' => 2,
	), $r['v6_build'], 'one <dialog>, no wrappers, no footer when asked');
}

function test_pg_ui_overlay_host_and_ready()
{
	$r = pg_ui_test_result();
	if (!is_array($r) || isset($r['error'])) {
		return;
	}

	pg_assert_true($r['v5_host_is_body'], 'Bootstrap 5: the body, as today');
	pg_assert_same(array('inside' => true, 'outside_goes_to_top' => true), $r['v6_host'], 'Bootstrap 6: the open dialog');
	pg_assert_true($r['v6_host_after_close_is_body']);
	pg_assert_true($r['v5_ready'], 'ready at once under 5');
	pg_assert_same(0, $r['none_version']);
	pg_assert_same(null, $r['none_get'], 'no instance before Bootstrap is there');
	pg_assert_true($r['none_ready'], 'ready when the module bridge announces Bootstrap 6');
}
