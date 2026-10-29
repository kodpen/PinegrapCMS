<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for assets/js/pg_ui.js, the adapter between panel code and
 * Bootstrap's JavaScript, run under Node against stand-ins for Bootstrap 5
 * and 6 and a small fake document: the component names, the option mapping,
 * the promises of show and hide, listening to both event names, the dialog
 * markup of each version, the overlay host, the readiness promise and the
 * Range with its value bubble.
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
        var m = one.match(/^([a-z]+)?((?:\.[\w-]+)*)(\[open\])?(?::not\(\.([\w-]+)\))?$/);
        if (!m) { return false; }
        if (m[1] && self.tagName !== m[1].toUpperCase()) { return false; }
        if (m[3] && !self.open) { return false; }
        if (m[4] && self.classList.contains(m[4])) { return false; }
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
// the open state and fire the version's events. A v6 <dialog> opens at once
// (showModal()) and settles later; its hide() adds .hiding and closes on a
// later tick, as Bootstrap 6 defers close() to the end of the animation.
function makeComponent(name, ev, six) {
    function C(el, opts) { this.el = el; this.opts = opts; this.name = name; }
    C.getOrCreateInstance = function (el, opts) {
        el.instances = el.instances || {};
        if (!el.instances[name]) { el.instances[name] = new C(el, opts); }
        return el.instances[name];
    };
    C.getInstance = function (el) { return (el.instances && el.instances[name]) || null; };
    C.prototype.show = function () {
        var el = this.el;
        if (six && el.tagName === 'DIALOG') {
            el.open = true;
            return new Promise(function (resolve) { setTimeout(function () { el.fire('shown.bs.' + ev); resolve('v6-promise'); }, 2); });
        }
        if (el.tagName === 'DIALOG') { el.open = true; } else { el.classList.add('show'); }
        el.fire('shown.bs.' + ev);
        return six ? Promise.resolve('v6-promise') : undefined;
    };
    C.prototype.hide = function () {
        var el = this.el;
        if (six && el.tagName === 'DIALOG') {
            el.fire('hide.bs.' + ev);
            el.classList.add('hiding');
            return new Promise(function (resolve) {
                setTimeout(function () { el.open = false; el.classList.remove('hiding'); el.fire('hidden.bs.' + ev); resolve('v6-promise'); }, 2);
            });
        }
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
        b.Combobox = makeComponent('Combobox', 'combobox', true);
    }
    ['Tooltip', 'Popover', 'Collapse', 'Tab', 'Toast'].forEach(function (n) { b[n] = makeComponent(n, n.toLowerCase(), v === 6); });
    if (v === 6) { b.Range = makeComponent('Range', 'range', true); }
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
        keyboard: parts.root.getAttribute('data-bs-keyboard'), id: parts.root.id,
        title_id: parts.title.id, labelledby: parts.root.getAttribute('aria-labelledby')
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
    out.chips_ctor = [ui6.get('chips', d6.createElement('div')).name, ui.get('chips', d5.createElement('div')), ui6.get('combobox', d6.createElement('button')).name, ui.get('combobox', d5.createElement('button'))];
    out.v6_options = ui6.options('tooltip', { popperConfig: { strategy: 'fixed' }, placement: 'auto' });
    out.v6_collapse_options = ui6.options('collapse', { toggle: false, parent: '#a' });
    var v6Types = [];
    ui6.on(dlg, 'dialog', 'shown', function (e) { v6Types.push(e.type); });
    await ui6.show('dialog', dlg);
    out.v6_open = { open: dlg.open, isOpen: ui6.isOpen('dialog', dlg), any: ui6.anyOpen('dialog'), events: v6Types.slice() };
    var inside = d6.createElement('span');
    dlg.appendChild(inside);
    out.v6_host = { inside: ui6.overlayHost(inside) === dlg, outside_goes_to_top: ui6.overlayHost(d6.createElement('i')) === dlg };
    var closing = ui6.hide('dialog', dlg);
    out.v6_closing = { open: dlg.open, isOpen: ui6.isOpen('dialog', dlg), any: ui6.anyOpen('dialog'), host_outside_is_body: ui6.overlayHost(d6.createElement('i')) === d6.body };
    await closing;
    out.v6_closed = { open: dlg.open, isOpen: ui6.isOpen('dialog', dlg), hiding: dlg.classList.contains('hiding') };
    var opening = ui6.show('dialog', dlg);
    out.v6_opening = { isOpen: ui6.isOpen('dialog', dlg), any: ui6.anyOpen('dialog'), host_outside: ui6.overlayHost(d6.createElement('i')) === dlg };
    await opening;
    await ui6.hide('dialog', dlg);
    var drw = d6.createElement('dialog');
    drw.className = 'drawer';
    await ui6.show('drawer', drw);
    var drawerClosing = ui6.hide('drawer', drw);
    out.v6_drawer_closing = { isOpen: ui6.isOpen('drawer', drw), any: ui6.anyOpen('drawer') };
    await drawerClosing;
    // A toast container follows the top open dialog and goes back to the body
    // on that dialog's hide event (or a native close).
    var toasts = d6.createElement('div');
    d6.body.appendChild(toasts);
    out.v6_layer_no_dialog = ui6.hostLayer(toasts) === d6.body && toasts.parentNode === d6.body;
    await ui6.show('dialog', dlg);
    var lifted = ui6.hostLayer(toasts) === dlg && toasts.parentNode === dlg;
    var again = ui6.hostLayer(toasts) === dlg && (dlg.listeners['hide.bs.dialog'] || []).length === 1;
    var hiding = ui6.hide('dialog', dlg);
    var backOnHide = toasts.parentNode === d6.body;
    out.v6_layer_closing_skipped = ui6.hostLayer(toasts) === d6.body;
    await hiding;
    await ui6.show('dialog', dlg);
    ui6.hostLayer(toasts);
    dlg.fire('close');
    out.v6_layer = { lifted: lifted, once: again, back_on_hide: backOnHide, back_on_close: toasts.parentNode === d6.body, listeners_left: (dlg.listeners['hide.bs.dialog'] || []).length + (dlg.listeners.close || []).length };
    dlg.open = false;
    out.v5_layer_body = ui.hostLayer(d5.createElement('div')) === d5.body;
    out.v6_host_after_close_is_body = ui6.overlayHost(inside) === d6.body;
    var parts6 = ui6.dialog.build({ title: 'T', size: 'xl', scrollable: true, centered: true, footer: false });
    out.v6_build = {
        root: parts6.root.tagName + '.' + parts6.root.className,
        header: parts6.header.className, body: parts6.body.className, footer: parts6.footer,
        dismiss: parts6.close.getAttribute('data-bs-dismiss'), children: parts6.root.children.length,
        labelledby: parts6.root.getAttribute('aria-labelledby') === parts6.title.id && parts6.title.id !== ''
    };

    // Range with the value bubble: v6 only, old wrappers disposed.
    var r1 = d6.createElement('div'), r2 = d6.createElement('div');
    var inst1 = await ui6.range.bubble(r1, function (v) { return v + '%'; });
    r1.isConnected = false;
    var inst2 = await ui6.range.bubble(r2);
    out.v6_range = {
        name: inst1.name, bubble: inst1.opts.bubble, text: inst1.opts.formatter(40),
        plain_formatter: inst2.opts.formatter, old_disposed: !ui6.range.find(r1), new_kept: ui6.range.find(r2) === inst2
    };
    out.v5_range = await ui.range.bubble(d5.createElement('div'));

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
	pg_assert_same(array('Chips', null, 'Combobox', null), $r['chips_ctor'], 'chips and combobox only under 6');
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

// Bootstrap 6 adds .hiding and closes the <dialog> only after its animation:
// a dialog on its way out counts as closed, one on its way in as open.
function test_pg_ui_v6_closing_dialog_is_closed()
{
	$r = pg_ui_test_result();
	if (!is_array($r) || isset($r['error'])) {
		return;
	}

	pg_assert_same(array('open' => true, 'isOpen' => false, 'any' => false, 'host_outside_is_body' => true), $r['v6_closing'], 'closing dialog: still [open], not counted');
	pg_assert_same(array('open' => false, 'isOpen' => false, 'hiding' => false), $r['v6_closed']);
	pg_assert_same(array('isOpen' => true, 'any' => true, 'host_outside' => true), $r['v6_opening'], 'opening dialog counts as open');
	pg_assert_same(array('isOpen' => false, 'any' => false), $r['v6_drawer_closing'], 'closing drawer: not counted');
}

// A toast container shown while a modal <dialog> is open sits in that
// dialog, not under its ::backdrop, and leaves it when the dialog hides.
function test_pg_ui_host_layer()
{
	$r = pg_ui_test_result();
	if (!is_array($r) || isset($r['error'])) {
		return;
	}

	pg_assert_true($r['v6_layer_no_dialog'], 'no dialog open: the body');
	pg_assert_same(array('lifted' => true, 'once' => true, 'back_on_hide' => true, 'back_on_close' => true, 'listeners_left' => 0), $r['v6_layer']);
	pg_assert_true($r['v6_layer_closing_skipped'], 'a closing dialog is not a host');
	pg_assert_true($r['v5_layer_body'], 'Bootstrap 5: the body');
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
		'title_id' => 'x-title',
		'labelledby' => 'x-title',
	), $r['v5_build'], 'the dialog is named by its title');

	pg_assert_same(array(
		'root' => 'DIALOG.dialog dialog-xl pg-dialog-centered dialog-scrollable',
		'header' => 'dialog-header',
		'body' => 'dialog-body',
		'footer' => null,
		'dismiss' => 'dialog',
		'children' => 2,
		'labelledby' => true,
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

function test_pg_ui_range_bubble()
{
	$r = pg_ui_test_result();
	if (!is_array($r) || isset($r['error'])) {
		return;
	}

	pg_assert_same(array(
		'name' => 'Range',
		'bubble' => true,
		'text' => '40%',
		'plain_formatter' => null,
		'old_disposed' => true,
		'new_kept' => true,
	), $r['v6_range'], 'Range with the bubble, a detached wrapper disposed');
	pg_assert_same(null, $r['v5_range'], 'no Range under Bootstrap 5');
}
