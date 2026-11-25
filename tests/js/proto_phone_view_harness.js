/**
 * Pinegrap - Enterprise Website Platform
 *
 * A stand-in browser for the phone page's script
 * (pg_proto_phone_shell_script(), includes/fn/prototype_phone_view.php):
 * the few elements it reads, a clock and timers moved by hand, fetch
 * answered from a list, the tab's visibility, sessionStorage and the
 * address. tests/prototype_phone_view_test.php writes the script and a
 * scenario after this file and reads what the scenario prints.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

'use strict';

var clock = 1900000000000;
var timers = [];
var timerId = 0;
var asked = [];
var answers = [];
var replaced = [];
var posted = [];
var storage = {};
var listeners = { window: {}, document: {} };

function element(id) {
    var classes = {};
    var events = {};
    return {
        id: id,
        textContent: '',
        hidden: id === 'pp-update' || id === 'pp-sheet' || id === 'pp-dim',
        attrs: {},
        classList: {
            add: function (c) { classes[c] = true; },
            remove: function (c) { delete classes[c]; },
            contains: function (c) { return !!classes[c]; },
            toggle: function (c, on) { if (on === undefined) on = !classes[c]; if (on) classes[c] = true; else delete classes[c]; return on; }
        },
        setAttribute: function (k, v) { this.attrs[k] = String(v); },
        addEventListener: function (type, fn) { (events[type] = events[type] || []).push(fn); },
        fire: function (type, e) { (events[type] || []).forEach(function (fn) { fn(e || {}); }); },
        contentWindow: (id === 'pg-proto-frame') ? { postMessage: function (m) { posted.push(m); } } : null
    };
}

var elements = {};

['pp-line', 'pp-words', 'pp-update', 'pp-list', 'pp-sheet', 'pp-dim', 'pp-name', 'pg-proto-frame', 'pp-config'].forEach(function (id) {
    elements[id] = element(id);
});

function on(target, type, fn) { (listeners[target][type] = listeners[target][type] || []).push(fn); }
function off(target, type, fn) { listeners[target][type] = (listeners[target][type] || []).filter(function (f) { return f !== fn; }); }
function fire(target, type, e) { (listeners[target][type] || []).slice().forEach(function (fn) { fn(e || {}); }); }

global.document = {
    visibilityState: 'visible',
    getElementById: function (id) { return elements[id] || null; },
    addEventListener: function (type, fn) { on('document', type, fn); }
};

global.window = {
    sessionStorage: {
        getItem: function (k) { return Object.prototype.hasOwnProperty.call(storage, k) ? storage[k] : null; },
        setItem: function (k, v) { storage[k] = String(v); },
        removeItem: function (k) { delete storage[k]; }
    },
    history: { replaceState: function (s, t, url) { window.lastAddress = url; } },
    location: { replace: function (url) { replaced.push(url); }, href: '' },
    addEventListener: function (type, fn) { on('window', type, fn); },
    removeEventListener: function (type, fn) { off('window', type, fn); }
};

Date.now = function () { return clock; };

global.setTimeout = function (fn, ms) {
    timerId++;
    timers.push({ id: timerId, fn: fn, at: clock + (ms || 0), ms: ms || 0 });
    return timerId;
};

global.clearTimeout = function (id) {
    timers = timers.filter(function (t) { return t.id !== id; });
};

global.fetch = function (url) {
    asked.push(url);
    var a = answers.length ? answers.shift() : { status: 200, body: { ok: true, changed: false, next: 10, stop: false } };

    if (a === 'offline') {
        return Promise.reject(new TypeError('Failed to fetch'));
    }

    return Promise.resolve({
        status: a.status,
        ok: a.status >= 200 && a.status < 300,
        json: function () { return Promise.resolve(a.body); }
    });
};

// The asking timer (waits of ten seconds or more) the script holds now, in
// seconds; 0 when none.
function waiting() {
    var long = timers.filter(function (t) { return t.ms >= 10000; });
    return long.length ? long[0].ms / 1000 : 0;
}

// Moves the clock to the next timer and runs it, then lets the answer's
// promises settle.
async function next() {
    timers.sort(function (a, b) { return a.at - b.at; });
    var t = timers.shift();

    if (t) {
        clock = Math.max(clock, t.at);
        t.fn();
    }

    await settle();
}

async function settle() {
    for (var i = 0; i < 10; i++) {
        await new Promise(function (r) { setImmediate(r); });
    }
}

function setConfig(cfg) {
    elements['pp-config'].textContent = JSON.stringify(cfg);
}

function state() {
    return {
        words: elements['pp-words'].textContent,
        warn: elements['pp-line'].classList.contains('pp-warn'),
        fresh: elements['pp-line'].classList.contains('pp-new'),
        update: !elements['pp-update'].hidden,
        wait: waiting(),
        asked: asked.length,
        replaced: replaced.slice()
    };
}
