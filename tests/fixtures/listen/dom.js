/**
 * Pinegrap - Enterprise Website Platform
 *
 * A small DOM-shaped fake for tests/listen_script_test.php: elements and
 * text nodes with the members assets/js/pg_listen.js reads (attributes,
 * hidden, text, attribute and tag selectors, closest, events, focus), a
 * document, a window and a speechSynthesis that records what it is given.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

var __doc = null;

function __events() {
    var on = {};
    return {
        addEventListener: function (type, fn) { (on[type] = on[type] || []).push(fn); },
        fire: function (type, e) { (on[type] || []).slice().forEach(function (fn) { fn(e || { type: type }); }); }
    };
}

// A selector: "tag", "[attr]" or "tag[attr]".
function __matches(node, sel) {
    if (node.nodeType !== 1) return false;
    var m = /^([a-z0-9]*)(?:\[([a-z0-9-]+)\])?$/i.exec(sel);
    if (!m) throw new Error('selector not supported: ' + sel);
    if (m[1] && node.tagName !== m[1].toUpperCase()) return false;
    if (m[2] && !node.hasAttribute(m[2])) return false;
    return true;
}

function __walk(node, fn) {
    node.childNodes.forEach(function (k) {
        if (k.nodeType !== 1) return;
        fn(k);
        __walk(k, fn);
    });
}

function el(tag, attrs, children) {
    var ev = __events();
    var node = {
        nodeType: 1,
        tagName: tag.toUpperCase(),
        attributes: {},
        childNodes: [],
        parentNode: null,
        getAttribute: function (n) { return Object.prototype.hasOwnProperty.call(this.attributes, n) ? this.attributes[n] : null; },
        setAttribute: function (n, v) { this.attributes[n] = String(v); },
        removeAttribute: function (n) { delete this.attributes[n]; },
        hasAttribute: function (n) { return Object.prototype.hasOwnProperty.call(this.attributes, n); },
        get hidden() { return this.hasAttribute('hidden'); },
        set hidden(v) { if (v) this.setAttribute('hidden', ''); else this.removeAttribute('hidden'); },
        get textContent() {
            return this.childNodes.map(function (k) { return k.nodeType === 3 ? k.nodeValue : k.textContent; }).join('');
        },
        set textContent(v) {
            var t = { nodeType: 3, nodeValue: String(v), parentNode: this };
            this.childNodes = [t];
        },
        querySelector: function (sel) { return this.querySelectorAll(sel)[0] || null; },
        querySelectorAll: function (sel) {
            var out = [];
            __walk(this, function (k) { if (__matches(k, sel)) out.push(k); });
            return out;
        },
        closest: function (sel) {
            for (var n = this; n && n.nodeType === 1; n = n.parentNode) if (__matches(n, sel)) return n;
            return null;
        },
        contains: function (other) {
            for (var n = other; n; n = n.parentNode) if (n === this) return true;
            return false;
        },
        addEventListener: ev.addEventListener,
        fire: ev.fire,
        click: function () { ev.fire('click', { type: 'click', preventDefault: function () {} }); },
        focus: function () { if (__doc) __doc.activeElement = this; }
    };
    Object.keys(attrs || {}).forEach(function (n) { node.attributes[n] = String(attrs[n]); });
    (children || []).forEach(function (c) {
        var k = (typeof c === 'string') ? { nodeType: 3, nodeValue: c } : c;
        k.parentNode = node;
        node.childNodes.push(k);
    });
    return node;
}

// The block as LS2 draws it: the root hidden, three buttons each with the
// text the script fills.
function listenBlock() {
    var btn = function (name, hidden) {
        var a = { type: 'button' };
        a['data-pg-listen-' + name] = '';
        if (hidden) a.hidden = '';
        return el('button', a, [el('i', { 'aria-hidden': 'true' }, []), el('span', { 'data-pg-listen-text': '' }, [name])]);
    };
    return el('div', { 'data-pg-listen': '', hidden: '' }, [btn('play', false), btn('pause', true), btn('stop', true)]);
}

function fakeDoc(html) {
    var ev = __events();
    var doc = {
        documentElement: html,
        visibilityState: 'visible',
        activeElement: null,
        querySelector: function (sel) { return __matches(html, sel) ? html : html.querySelector(sel); },
        querySelectorAll: function (sel) { return html.querySelectorAll(sel); },
        addEventListener: ev.addEventListener,
        fire: ev.fire
    };
    __doc = doc;
    return doc;
}

function fakeSynth(voices) {
    var ev = __events();
    var s = {
        voices: voices,
        spoken: [],
        current: null,
        paused: false,
        cancels: 0,
        getVoices: function () { return s.voices; },
        speak: function (u) { s.spoken.push(u); s.current = u; },
        pause: function () { s.paused = true; },
        resume: function () { s.paused = false; },
        cancel: function () {
            s.cancels++;
            var u = s.current;
            s.current = null;
            s.paused = false;
            if (u && u.onerror) u.onerror({ error: 'interrupted' });
        },
        // The utterance being spoken ends.
        finish: function () {
            var u = s.current;
            s.current = null;
            if (u && u.onend) u.onend({});
        },
        addEventListener: ev.addEventListener,
        fire: ev.fire
    };
    return s;
}

function fakeWin(synth) {
    var ev = __events();
    return {
        speechSynthesis: synth,
        SpeechSynthesisUtterance: function (text) { this.text = text; this.lang = ''; this.voice = null; },
        addEventListener: ev.addEventListener,
        fire: ev.fire
    };
}
