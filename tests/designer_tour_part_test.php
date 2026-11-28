<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the designer's first tour part (assets/js/style_designer_tour.js)
 * run in Node on a small stand-in for the editor's page: the card when the
 * browser will not keep what was seen, Escape meant for an open menu, the
 * count when a stop's control goes away, a "How do I…" pointer whose
 * pressed control goes away before the next one comes, and a control below
 * the fold of its panel. Skipped where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs `$code` in Node with the model and the part loaded on a stand-in
// page; `out` comes back decoded. Null without Node.
//
// The page: `ctl(sel, box)` puts a control on the screen (box [l, t, r, b]),
// `ctl(sel, null)` takes it away; `clip(sel, box, scroll)` sets the box of
// an ancestor that cuts it; `menuOpen` and `dialogOpen` say what is open;
// `tick(ms)` runs the timers; `key(name)` presses a key on the page,
// `next()` the right arrow in the bubble;
// `press(sel)` clicks a control; `bubble()` is the bubble's markup or ''.
function designer_tour_part_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$page = <<<'JS'
var now = 0, timers = [], seq = 0;
function setTimeout(f, ms) { var id = ++seq; timers.push({ id: id, at: now + (ms || 0), f: f }); return id; }
function setInterval(f, ms) { var id = ++seq; timers.push({ id: id, at: now + ms, f: f, every: ms }); return id; }
function clearTimeout(id) { timers = timers.filter(function (t) { return t.id !== id; }); }
var clearInterval = clearTimeout;
function tick(ms) {
    var end = now + ms;
    for (;;) {
        timers.sort(function (a, b) { return a.at - b.at || a.id - b.id; });
        var t = timers[0];
        if (!t || t.at > end) break;
        now = t.at;
        if (t.every) t.at += t.every; else timers.shift();
        t.f();
    }
    now = end;
}
var listeners = [];
function on(target, type, f) { listeners.push({ target: target, type: type, f: f }); }
function off(target, type, f) { listeners = listeners.filter(function (l) { return !(l.target === target && l.type === type && l.f === f); }); }
function fire(target, type, e) { listeners.filter(function (l) { return l.target === target && l.type === type; }).forEach(function (l) { l.f(e); }); }
var controls = {}, clips = {}, menuOpen = false, dialogOpen = false, scrolled = [];
function El(tag) {
    this.tagName = tag; this.className = ''; this.style = {}; this.attrs = {}; this.parentNode = null;
    this.hidden = false; this.html = ''; this.offsetHeight = 140; var self = this;
    this.classList = {
        add: function () { for (var i = 0; i < arguments.length; i++) if (!this.contains(arguments[i])) self.className += ' ' + arguments[i]; },
        remove: function () { for (var i = 0; i < arguments.length; i++) self.className = (' ' + self.className + ' ').replace(' ' + arguments[i] + ' ', ' ').trim(); },
        contains: function (c) { return (' ' + self.className + ' ').indexOf(' ' + c + ' ') !== -1; },
        toggle: function (c, f) { if (f === undefined ? !this.contains(c) : f) this.add(c); else this.remove(c); }
    };
}
El.prototype = {
    setAttribute: function (k, v) { this.attrs[k] = String(v); },
    getAttribute: function (k) { return this.attrs[k] === undefined ? null : this.attrs[k]; },
    addEventListener: function (t, f) { on(this, t, f); },
    removeEventListener: function (t, f) { off(this, t, f); },
    appendChild: function (c) { c.parentNode = this; return c; },
    insertBefore: function (c) { c.parentNode = this; return c; },
    removeChild: function (c) { c.parentNode = null; return c; },
    get isConnected() { return !!this.parentNode || this === document.body; },
    set innerHTML(v) { this.html = v; this.kids = {}; },
    get innerHTML() { return this.html; },
    querySelector: function (sel) {
        var m = /data-sd-tour="(\w+)"/.exec(sel), self = this;
        if (m && !/next|done/.test(m[1])) m = null;
        if (sel.indexOf('next') !== -1 && sel.indexOf('done') !== -1) {
            m = this.html.indexOf('data-sd-tour="next"') !== -1 ? ['', 'next'] : (this.html.indexOf('data-sd-tour="done"') !== -1 ? ['', 'done'] : null);
            if (!m) return null;
        }
        this.kids = this.kids || {};
        if (!this.kids[sel]) { this.kids[sel] = new El('span'); this.kids[sel].parentNode = self; }
        return this.kids[sel];
    },
    closest: function (sel) {
        if (/sd-tour-card|sd-tour-bubble/.test(sel) && /sd-tour-(card|bubble)/.test(this.className)) return this;
        return null;
    },
    contains: function (el) { for (; el; el = el.parentNode) if (el === this) return true; return false; },
    // A hidden element, or one inside a hidden element, takes no focus.
    focus: function () { for (var p = this; p; p = p.parentNode) if (p.hidden) return; document.activeElement = this; },
    getClientRects: function () { return this.box ? [1] : []; },
    getBoundingClientRect: function () {
        var b = this.box || [0, 0, 0, 0];
        return { left: b[0], top: b[1], right: b[2], bottom: b[3], width: b[2] - b[0], height: b[3] - b[1] };
    },
    scrollIntoView: function () { scrolled.push(this.sel); if (this.onScroll) this.onScroll(); }
};
function ctl(sel, box) {
    if (!box) { delete controls[sel]; return; }
    var el = controls[sel] || new El('div');
    el.sel = sel; el.box = box; controls[sel] = el;
    return el;
}
function clip(sel, box, scroll) {
    var p = new El('div');
    p.box = box; p.overflow = scroll ? 'auto' : 'hidden';
    controls[sel].parentElement = p;
}
var document = {
    body: new El('body'), activeElement: null,
    createElement: function (t) { return new El(t); },
    querySelector: function (sel) { return controls[sel] || null; },
    getElementById: function () { return null; },
    querySelectorAll: function (sel) {
        var out = [];
        if (/dialog/.test(sel) && dialogOpen) out.push(ctl('#dlg', [100, 100, 400, 400]));
        if (/aria-expanded|menu/.test(sel) && !/dialog/.test(sel) && menuOpen) out.push(ctl('#menu', [100, 40, 300, 200]));
        return out;
    },
    addEventListener: function (t, f) { on(document, t, f); },
    removeEventListener: function (t, f) { off(document, t, f); }
};
var store = {}, storeRefuses = false;
var window = {
    innerWidth: 1280, innerHeight: 800,
    addEventListener: function (t, f) { on(window, t, f); },
    removeEventListener: function (t, f) { off(window, t, f); },
    matchMedia: function () { return { matches: true }; },
    getComputedStyle: function (el) { return { overflowX: el.overflow || 'visible', overflowY: el.overflow || 'visible' }; },
    localStorage: {
        getItem: function (k) { return store.hasOwnProperty(k) ? store[k] : null; },
        setItem: function (k, v) { if (storeRefuses) throw new Error('QuotaExceededError'); store[k] = String(v); }
    }
};
var getComputedStyle = window.getComputedStyle;
function requestAnimationFrame(f) { return setTimeout(f, 16); }
function key(name) {
    var e = { key: name, target: document.activeElement || document.body, defaultPrevented: false, stopped: false,
        preventDefault: function () { this.defaultPrevented = true; }, stopPropagation: function () { this.stopped = true; } };
    fire(document, 'keydown', e);
    return e;
}
function press(sel) {
    var e = { target: controls[sel], type: 'click' };
    e.target.ownerDocument = document;
    fire(document, 'click', e);
}
var part = null, access = 'full';
var StyleDesigner = { definePart: function (n, f) { part = f(kit); } };
var kit = {
    _sdT: function (k) { return k; },
    esc: function (s) { return String(s); },
    _sdAccess: function () { return access; },
    design: { userId: 7 },
    canvasDoc: null
};
function bubbleEl() {
    var b = listeners.filter(function (l) { return l.type === 'keydown' && l.target && l.target.classList && l.target.classList.contains('sd-tour-bubble'); })[0];
    return b && b.target.parentNode ? b.target : null;
}
function bubble() { var b = bubbleEl(); return b ? b.html : ''; }
// The right arrow inside the bubble: Next.
function next() { fire(bubbleEl(), 'keydown', { key: 'ArrowRight', preventDefault: function () {} }); }
function card() { return listeners.some(function (l) { return l.type === 'resize' && l.f.name === 'onCardResize'; }); }
function state() { return JSON.parse(store['pg.designer.7.tour'] || 'null'); }
// The full tour's controls, all on the screen.
ctl('#sd-components', [0, 40, 260, 760]);
ctl('#sd-properties', [1000, 40, 1280, 760]);
ctl('#sd-canvas', [260, 40, 1000, 760]);
ctl('#sd-preview-menu-btn', [800, 4, 880, 34]);
ctl('#sd-ajax-save', [900, 4, 990, 34]);
ctl('#sd-status-shortcuts', [1240, 778, 1270, 798]);
JS;

	$script = $page . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_designer_tour.js') . "\n"
		. file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_tour.js') . "\n"
		. "var out = null;\n"
		. $code . "\n"
		. 'console.log(JSON.stringify(out));';

	$file = @tempnam(sys_get_temp_dir(), 'pgtourpart');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// A browser that will not keep what was seen (storage full, writes
// refused) would show the card again on every visit: it is not shown.
function test_designer_tour_part_no_card_when_the_state_cannot_be_kept()
{
	$res = designer_tour_part_node(<<<'JS'
storeRefuses = true;
var offered = part.offer();
tick(5000);
out = { offered: offered, card: card() };
storeRefuses = false;
var again = part.offer();
out.kept = again && card() && state().offered === true;
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_same(false, $res['card'], 'no card when the browser refuses to keep that it was offered');
	pg_assert_true($res['kept'], 'with storage the card is shown and kept as offered');
}

// Escape that closes the open Preview menu at the "see" stop leaves the
// tour running; Escape with nothing open ends it as seen.
function test_designer_tour_part_escape_for_an_open_menu_keeps_the_tour()
{
	$res = designer_tour_part_node(<<<'JS'
part.start();
tick(10);
next(); next();
menuOpen = true;
key('Escape');
var during = bubble() !== '' && state() === null;
menuOpen = false;
key('Escape');
out = { during: during, after: bubble() === '', seen: !!(state() && state().seen.full) };
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['during'], 'Escape for an open menu does not end the tour');
	pg_assert_true($res['after'] && $res['seen'], 'Escape with nothing open ends the tour as seen');
}

// A stop's control that goes away while the tour runs (the window made
// narrow, the palette closed) changes the count and the buttons with it.
function test_designer_tour_part_count_follows_a_control_gone()
{
	$res = designer_tour_part_node(<<<'JS'
part.start();
tick(10);
next();
ctl('#sd-components', null);
tick(500);
out = { html: bubble() };
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true(strpos($res['html'], 'data-sd-tour="back"') === false, 'no Back once the stop before is gone');
	pg_assert_true(strpos($res['html'], 'Tour · 1 of 4') !== false, 'the count is one of four once the palette is gone');
	pg_assert_true(strpos($res['html'], 'aria-hidden="true"><i class="is-on"></i><i></i><i></i><i></i></span>') !== false, 'four dots, the first on');
}

// A pointer whose pressed control goes away before the next pointer's
// control comes (rows read from the server) waits for it: the task is
// neither closed nor walked past the next pointer.
function test_designer_tour_part_pointer_waits_for_the_next_control()
{
	$res = designer_tour_part_node(<<<'JS'
ctl('#sd-main-undo', [10, 4, 40, 34]);
ctl('#sd-vb-snapshots', [50, 4, 80, 34]);
part.start('history');
tick(10);
next();
var at2 = bubble().indexOf('Open “History”') !== -1;
press('#sd-vb-snapshots');
// The press opens the History panel over the bar button.
ctl('#sd-vb-snapshots', null);
tick(450);
ctl('#sd-snapshots-panel .sd-snap-item', [300, 100, 600, 140]);
tick(2000);
out = { at2: at2, open: bubble() !== '', last: bubble().indexOf('Open one to look at it') !== -1 };
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['at2'], 'the task stands at its second pointer');
	pg_assert_true($res['open'], 'the task is not closed while the next control comes');
	pg_assert_true($res['last'], 'the task moves on to the pointer whose control came');
}

// A control below the fold of its panel's list counts as on the screen and
// is scrolled into view when its stop comes, not skipped.
function test_designer_tour_part_scrolls_a_control_into_view()
{
	$res = designer_tour_part_node(<<<'JS'
ctl('#sd-main-undo', [10, 4, 40, 34]);
ctl('#sd-vb-snapshots', [50, 4, 80, 34]);
var row = ctl('#sd-snapshots-panel .sd-snap-item', [300, 900, 600, 940]);
clip('#sd-snapshots-panel .sd-snap-item', [280, 100, 620, 700], true);
row.onScroll = function () { row.box = [300, 600, 600, 640]; };
part.start('history');
tick(10);
next(); next();
tick(450);
out = { last: bubble().indexOf('Open one to look at it') !== -1, scrolled: scrolled };
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['last'], 'a row below the fold of its list is a stop, not skipped');
	pg_assert_same(array('#sd-snapshots-panel .sd-snap-item'), $res['scrolled'], 'it is scrolled into view once');
}

// A task started from the Help window starts while the window is still
// closing: the bubble waits hidden, then takes the focus and is read out
// once the window has gone, and the focus goes back to where the window
// gave it at the end.
function test_designer_tour_part_task_from_the_help_window_gets_the_focus()
{
	$res = designer_tour_part_node(<<<'JS'
ctl('#sd-main-undo', [10, 4, 40, 34]);
ctl('#sd-vb-snapshots', [50, 4, 80, 34]);
var row = new El('button');
row.parentNode = new El('dialog');
row.parentNode.isDialog = true;
row.closest = function (sel) { return /dialog/.test(sel) ? this.parentNode : null; };
document.activeElement = row;
dialogOpen = true;
part.start('history');
tick(100);
var b = bubbleEl();
var hiddenFocus = document.activeElement === row;
dialogOpen = false;
// The window gives the focus back to the "?" button that opened it.
var opener = ctl('#sd-status-shortcuts', [1240, 778, 1270, 798]);
opener.parentNode = document.body;
document.activeElement = opener;
tick(500);
var main = b.querySelector('[data-sd-tour="next"], [data-sd-tour="done"]');
var said = b.querySelector('.sd-tour-said').textContent || '';
var mainFocused = document.activeElement === main;
key('Escape');
out = { hiddenFocus: hiddenFocus, mainFocused: mainFocused, focused: document.activeElement === opener ? 'opener' : 'other', said: said };
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['hiddenFocus'], 'no focus moves while the window is still closing');
	pg_assert_true($res['mainFocused'], 'Next takes the focus once the window has gone');
	pg_assert_true(strpos($res['said'], 'How do I, 1 of 3.') === 0, 'the pointer is read out once the window has gone');
	pg_assert_same('opener', $res['focused'], 'the end gives the focus back to where the window gave it');
}

// A stop of another control that goes away while the focus is on the
// bubble's Next redraws the count: the focus stays on the bubble's Next
// instead of falling to the page with the old button.
function test_designer_tour_part_redraw_keeps_the_focus()
{
	$res = designer_tour_part_node(<<<'JS'
part.start();
tick(10);
next();
var b = bubbleEl();
var before = document.activeElement === b.querySelector('[data-sd-tour="next"], [data-sd-tour="done"]');
// The window made narrow: the "find" stop's button goes away.
ctl('#sd-status-shortcuts', null);
tick(500);
var main = b.querySelector('[data-sd-tour="next"], [data-sd-tour="done"]');
out = { before: before, redrawn: bubble().indexOf('Tour · 2 of 4') !== -1, kept: document.activeElement === main };
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['before'], 'Next has the focus at the second stop');
	pg_assert_true($res['redrawn'], 'the count is drawn again for four stops');
	pg_assert_true($res['kept'], 'the focus stays on the bubble after the redraw');
}

// Escape that ends the tour or puts the card away is the tour's alone: it
// does not go on to the editor, which would also clear the selection or
// close the Prototype view with the same press. Escape meant for an open
// menu is left to it.
function test_designer_tour_part_escape_does_not_reach_the_editor()
{
	$res = designer_tour_part_node(<<<'JS'
part.offer();
tick(10);
var cardKey = key('Escape');
var cardGone = !card();
part.start();
tick(10);
menuOpen = true;
var menuKey = key('Escape');
menuOpen = false;
var tourKey = key('Escape');
out = {
    cardGone: cardGone, card: cardKey.stopped && cardKey.defaultPrevented,
    menu: !menuKey.stopped && !menuKey.defaultPrevented,
    tour: tourKey.stopped && tourKey.defaultPrevented && bubble() === ''
};
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true($res['cardGone'] && $res['card'], 'Escape that puts the card away stops there');
	pg_assert_true($res['menu'], 'Escape for an open menu goes on to the menu');
	pg_assert_true($res['tour'], 'Escape that ends the tour stops there');
}

// The card says how many stops the tour will show: four when the palette
// is not on the screen (a narrow window), not the five of a wide one.
function test_designer_tour_part_card_counts_the_stops_shown()
{
	$res = designer_tour_part_node(<<<'JS'
ctl('#sd-components', null);
part.offer();
tick(10);
var c = listeners.filter(function (l) { return l.type === 'click' && l.target.classList && l.target.classList.contains('sd-tour-card'); })[0];
out = { html: c ? c.target.html : '' };
JS
	);

	if ($res === null) {
		return;
	}

	pg_assert_true(strpos($res['html'], 'Four stops on the real buttons.') !== false, 'the card promises the four stops shown: ' . $res['html']);
}
