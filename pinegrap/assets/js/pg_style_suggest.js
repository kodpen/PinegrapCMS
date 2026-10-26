/**
 * Pinegrap - Enterprise Website Platform
 *
 * The browser side of the designer's smart completion: it loads the
 * predictor (assets/js/pg_style_predict.js) and the Bootstrap vocabulary
 * (assets/js/designer_vocab/bootstrap5.json) the first time an input asks
 * for them, reads an element's context for the predictor, and draws the
 * light suggestion dropdown under a panel input. The designer page itself
 * carries only this file.
 *
 *   PgStyleSuggest.configure({ predictor: url, vocab: url });
 *   PgStyleSuggest.load(function (p) { ... });   // p is null on failure
 *   PgStyleSuggest.get();                         // the predictor or null
 *   PgStyleSuggest.elementContext(el, opts);      // { tag, classes, attrs, parents }
 *   PgStyleSuggest.designClasses(lists);          // { name: count }
 *   PgStyleSuggest.dropdown(input, opts);         // the light dropdown
 *
 * window.PgStyleSuggest in a browser; module.exports under Node (the pure
 * helpers only need plain objects there). Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var urls = { predictor: '', vocab: '' };
    var state = 'idle';     // idle | loading | ready | failed
    var predictor = null;
    var waiting = [];

    function configure(opts) {
        opts = opts || {};
        if (opts.predictor) urls.predictor = String(opts.predictor);
        if (opts.vocab) urls.vocab = String(opts.vocab);
    }

    function settle(p) {
        predictor = p;
        state = p ? 'ready' : 'failed';
        var list = waiting;
        waiting = [];
        list.forEach(function (cb) {
            try { cb(predictor); } catch (e) { /* one caller's error must not stop the rest */ }
        });
    }

    // Loads the predictor script and the vocabulary once; every caller gets
    // the same predictor. A failure is final for the page: the inputs keep
    // working with what they know themselves.
    function load(cb) {
        if (typeof cb === 'function') {
            if (state === 'ready' || state === 'failed') { cb(predictor); return; }
            waiting.push(cb);
        }
        if (state !== 'idle') return;
        state = 'loading';
        var doc = root.document;
        if (!doc || !urls.vocab || typeof root.fetch !== 'function') { settle(null); return; }

        var vocab = null, script = !!root.PgStylePredict, failed = false;
        var done = function () {
            if (failed || !vocab || !script) return;
            try { settle(root.PgStylePredict.create(vocab)); } catch (e) { settle(null); }
        };
        var fail = function () { if (!failed) { failed = true; settle(null); } };

        if (!script) {
            if (!urls.predictor) { fail(); return; }
            var s = doc.createElement('script');
            s.src = urls.predictor;
            s.async = true;
            s.onload = function () { script = !!root.PgStylePredict; if (script) done(); else fail(); };
            s.onerror = fail;
            doc.head.appendChild(s);
        }
        root.fetch(urls.vocab, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('vocabulary ' + r.status); return r.json(); })
            .then(function (v) { vocab = v; done(); })
            .catch(fail);
    }

    function get() { return predictor; }

    function classList(el) {
        var cls = el && el.getAttribute ? el.getAttribute('class') : '';
        return String(cls || '').split(/\s+/).filter(Boolean);
    }

    // The predictor's context of a document element: its tag, classes,
    // attributes and up to six ancestors nearest first. opts.skipClass
    // (a RegExp) drops the editor's own classes; opts.skipEl(el) passes over
    // an editor wrapper on the way up; the walk stops at <body>.
    function elementContext(el, opts) {
        opts = opts || {};
        var ctx = { tag: '', classes: [], attrs: {}, parents: [] };
        if (!el || !el.tagName) return ctx;
        var keep = function (c) { return !(opts.skipClass && opts.skipClass.test(c)); };
        ctx.tag = String(el.tagName).toLowerCase();
        ctx.classes = classList(el).filter(keep);
        var attrs = el.attributes || [];
        for (var i = 0; i < attrs.length; i++) {
            var name = String(attrs[i].name || '').toLowerCase();
            if (!name || name === 'class' || name === 'style' || /^data-sd-|^data-sw-/.test(name)) continue;
            ctx.attrs[name] = String(attrs[i].value || '');
        }
        var p = el.parentElement || el.parentNode;
        while (p && p.tagName && ctx.parents.length < 6) {
            var tag = String(p.tagName).toLowerCase();
            if (tag === 'body' || tag === 'html') break;
            if (!(opts.skipEl && opts.skipEl(p))) {
                ctx.parents.push({ tag: tag, classes: classList(p).filter(keep) });
            }
            p = p.parentElement || p.parentNode;
        }
        return ctx;
    }

    // How often each class is used, from class strings or lists of names.
    function designClasses(lists) {
        var counts = {};
        (lists || []).forEach(function (l) {
            (typeof l === 'string' ? l.split(/\s+/) : (l || [])).forEach(function (c) {
                if (c) counts[c] = (counts[c] || 0) + 1;
            });
        });
        return counts;
    }

    // The light dropdown under a panel input. opts:
    //   items(query)  the suggestions to show, [{ label, hint, muted }]
    //   pick(item)    called when one is chosen (click, Enter on a
    //                 highlighted row, Tab)
    //   onFocus()     called on focus, before the first list (to start load())
    //   openEmpty     show the list on focus with nothing typed
    // Returns { refresh, close }. Enter with no highlighted row and the
    // arrow keys left alone stay with the input's own handlers.
    function dropdown(input, opts) {
        if (!input || input._pgSuggestBound) return input && input._pgSuggestBound;
        opts = opts || {};
        var doc = input.ownerDocument;
        var box = doc.createElement('div');
        box.className = 'sd-class-ac-dropdown';
        box.style.display = 'none';
        doc.body.appendChild(box);
        var active = -1, shown = [];

        var esc = function (s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        };
        var close = function () { box.style.display = 'none'; active = -1; shown = []; };
        var draw = function () {
            if (!shown.length) { close(); return; }
            var html = '';
            shown.forEach(function (it, i) {
                html += '<div class="sd-class-ac-item' + (i === active ? ' active' : '') + (it.muted ? ' sd-class-ac-muted' : '') + '" data-idx="' + i + '">'
                    + '<span class="sd-class-ac-label">' + esc(it.label) + '</span>'
                    + (it.hint ? '<span class="sd-class-ac-hint">' + esc(it.hint) + '</span>' : '')
                    + '</div>';
            });
            box.innerHTML = html;
            var r = input.getBoundingClientRect();
            box.style.left = r.left + 'px';
            box.style.top = (r.bottom + 2) + 'px';
            box.style.minWidth = Math.max(200, r.width) + 'px';
            box.style.display = 'block';
        };
        var refresh = function () {
            if (doc.activeElement !== input) return;
            var q = String(input.value || '').trim();
            if (!q && !opts.openEmpty) { close(); return; }
            shown = (opts.items ? opts.items(q) : []) || [];
            // Never preselect: Enter on a typed value adds what was typed.
            active = -1;
            draw();
        };
        var choose = function (i) {
            var it = shown[i];
            close();
            if (it && opts.pick) opts.pick(it);
        };

        input.addEventListener('input', refresh);
        input.addEventListener('focus', function () {
            if (opts.onFocus) opts.onFocus();
            refresh();
        });
        input.addEventListener('blur', function () { setTimeout(close, 120); });
        input.addEventListener('keydown', function (e) {
            if (box.style.display !== 'block' || !shown.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                active = (active + 1) % shown.length;
                draw();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active - 1 + shown.length) % shown.length;
                draw();
            } else if (e.key === 'Tab') {
                e.preventDefault();
                choose(active >= 0 ? active : 0);
            } else if (e.key === 'Enter') {
                if (active >= 0) { e.preventDefault(); choose(active); }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                close();
            }
        });
        box.addEventListener('mousedown', function (e) {
            var row = e.target.closest ? e.target.closest('.sd-class-ac-item') : null;
            if (!row) return;
            e.preventDefault();  // keep the focus in the input
            var i = parseInt(row.getAttribute('data-idx'), 10);
            if (!isNaN(i)) choose(i);
        });

        // The panel redraws its inputs; the dropdown goes with its input.
        var obs = new MutationObserver(function () {
            if (!doc.body.contains(input)) { box.remove(); obs.disconnect(); }
        });
        obs.observe(doc.body, { childList: true, subtree: true });

        input._pgSuggestBound = { refresh: refresh, close: close };
        return input._pgSuggestBound;
    }

    var api = {
        configure: configure,
        load: load,
        get: get,
        elementContext: elementContext,
        designClasses: designClasses,
        dropdown: dropdown
    };

    root.PgStyleSuggest = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
