/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the page transition part: the section "Page
 * transition" in Settings, Theme, tab "Look and colours" (None, Fade,
 * Slide; From, Duration, Easing; a small figure of two pages with "Play"),
 * and the sub-head "Between pages" in an element's Interactions card
 * ("Keep across pages" and its "Name").
 *
 * The transition is kept in the design's variables file
 * (pg_design_page_transition_css()): the core host reads the saved file
 * and puts a change into the hidden input the design save posts. A kept
 * element gets a view-transition-name on its own class in User Styles,
 * written through the core's custom style writer (one undo step, the class
 * added the first time). The canvas never plays a transition.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
StyleDesigner.definePart('pagetrans', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var P = window.PGPageTrans;

    // The saved file's transition, per address: undefined until read.
    var saved = {};

    function style() {
        if (document.getElementById('sd-pt-style')) return;
        var s = document.createElement('style');
        s.id = 'sd-pt-style';
        s.textContent =
            '.sd-pt-fig{position:relative;width:96px;height:60px;overflow:hidden;border-radius:4px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.04)}' +
            '.sd-pt-pg{position:absolute;inset:6px;border-radius:3px;display:flex;flex-direction:column;gap:4px;padding:6px}' +
            '.sd-pt-pg i{display:block;height:4px;border-radius:2px;background:currentColor;opacity:.6}' +
            '.sd-pt-a{background:rgba(121,192,255,.22);color:#79c0ff}.sd-pt-b{background:rgba(255,200,120,.22);color:#ffc878;opacity:0}' +
            '.sd-pt-row{display:flex;align-items:center;gap:10px;margin-top:8px}' +
            '.sd-pt-fields{display:grid;gap:8px;margin-top:8px}' +
            '.sd-pt-fields .sd-trans-ms{display:flex;align-items:center;gap:8px}.sd-pt-fields .sd-trans-ms .input-group{width:110px}' +
            '.sd-pt-sub{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;opacity:.7;margin:10px 0 4px}' +
            '.sd-pt-dim{opacity:.5;pointer-events:none}';
        document.head.appendChild(s);
    }

    // ── The design's transition ────────────────────────────────────────

    // The saved file's transition (null: none), read once per address.
    function load(host) {
        var url = host.url || '';
        if (!url) return Promise.resolve(null);
        if (saved[url] !== undefined) return Promise.resolve(saved[url]);
        return fetch(url, { credentials: 'same-origin', cache: 'no-cache' }).then(function (r) {
            return r.ok ? r.text() : '';
        }).then(function (text) {
            saved[url] = P.fromCss(text, host.easeClean);
            return saved[url];
        }, function () {
            return null;
        });
    }

    // The transition of the moment: a change not saved yet, else the file's.
    function current(host) {
        return load(host).then(function (stored) {
            var posted = host.posted();
            return posted ? P.clean(posted.page, host.easeClean) : stored;
        });
    }

    function fill(host) {
        style();
        return current(host).then(function (page) {
            if (host.el.isConnected) draw(host, page);
        });
    }

    function draw(host, page) {
        var kind = page ? page.kind : '';
        var h = '<div class="sd-th-head"><span class="sd-th-title">' + esc(_sdT('Page transition')) + '</span>' +
            '<span class="sd-th-sub">' + esc(_sdT('How one page of the design gives way to the next. A visitor who asks for less motion gets none.')) + '</span></div>';
        h += '<div class="btn-group btn-group-sm flex-wrap" role="radiogroup" aria-label="' + esc(_sdT('Page transition')) + '">';
        [['', _sdT('None'), 'bi-dash-circle'], ['fade', _sdT('Fade'), 'bi-circle-half'], ['slide', _sdT('Slide'), 'bi-arrow-left-right']].forEach(function (o) {
            var on = kind === o[0];
            h += '<button type="button" class="btn btn-outline theme-secondary' + (on ? ' active' : '') + '" role="radio" aria-checked="' + (on ? 'true' : 'false') + '" data-pt-kind="' + o[0] + '">' +
                '<span class="bi ' + o[2] + ' me-1" aria-hidden="true"></span>' + esc(o[1]) + '</button>';
        });
        h += '</div>';
        if (!page) {
            h += '<p class="sd-th-hint mt-3">' + esc(_sdT('None: a page opens as before.')) + '</p>';
            host.el.innerHTML = h;
            bind(host, page);
            return;
        }
        h += '<p class="sd-th-hint mt-3">' + esc(kind === 'fade'
            ? _sdT('The old page fades out as the new one fades in.')
            : _sdT('The new page comes in from one side while the old one leaves the other way.')) + '</p>';
        h += '<div class="sd-pt-fields">';
        if (kind === 'slide') {
            h += '<label class="sd-th-field"><span>' + esc(_sdT('From')) + '</span><select class="form-control form-control-sm" data-pt="from">' +
                [['right', _sdT('The right')], ['left', _sdT('The left')], ['below', _sdT('Below')]].map(function (o) {
                    return '<option value="' + o[0] + '"' + (page.from === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
                }).join('') + '</select></label>';
        }
        var look = host.lookMs();
        var set = page.duration !== undefined;
        var ms = set ? page.duration : look;
        h += '<label class="sd-th-field"><span>' + esc(_sdT('Duration')) + '</span><div class="sd-trans-ms' + (set ? '' : ' sd-trans-inh') + '">' +
            '<input type="range" class="form-range-input" min="0" max="1000" step="10" value="' + Math.min(ms, 1000) + '" data-pt="ms-range" aria-label="' + esc(_sdT('Duration')) + '">' +
            '<div class="input-group input-group-sm"><input type="number" class="form-control form-control-sm" min="0" max="1000" step="10" data-pt="ms" value="' + (set ? page.duration : '') + '" placeholder="' + look + '">' +
            '<span class="input-group-text">ms</span></div></div></label>';
        h += '<div class="sd-th-field sd-prop-row" data-pt-ease><span>' + esc(_sdT('Easing')) + '</span>' +
            host.easeHtml('data-pt="easing"', page.easing || '', [['', _sdT('Default')]]) + '</div>';
        h += '</div>';
        h += '<div class="sd-pt-row"><div class="sd-pt-fig" aria-hidden="true">' +
            '<span class="sd-pt-pg sd-pt-a"><i style="width:70%"></i><i></i><i style="width:50%"></i></span>' +
            '<span class="sd-pt-pg sd-pt-b"><i style="width:40%"></i><i style="width:80%"></i><i></i></span></div>' +
            '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-pt-play><span class="bi bi-play-fill"></span> ' + esc(_sdT('Play')) + '</button></div>';
        host.el.innerHTML = h;
        bind(host, page);
    }

    // The figure: the old page leaves and the new one comes in, as the
    // site will, with the transition's time and easing.
    function play(host, page) {
        var fig = host.el.querySelector('.sd-pt-fig');
        if (!fig || !page || !fig.animate) return;
        var a = fig.querySelector('.sd-pt-a'), b = fig.querySelector('.sd-pt-b');
        var opts = { duration: page.duration !== undefined ? page.duration : host.lookMs(), easing: page.easing || 'ease', fill: 'none' };
        var moves = { right: ['100%, 0', '-100%, 0'], left: ['-100%, 0', '100%, 0'], below: ['0, 100%', '0, -100%'] };
        var outFrames, inFrames;
        if (page.kind === 'slide') {
            var m = moves[page.from] || moves.right;
            outFrames = [{ transform: 'none', opacity: 1 }, { transform: 'translate(' + m[1] + ')', opacity: 1 }];
            inFrames = [{ transform: 'translate(' + m[0] + ')', opacity: 1 }, { transform: 'none', opacity: 1 }];
        } else {
            outFrames = [{ opacity: 1 }, { opacity: 0 }];
            inFrames = [{ opacity: 0 }, { opacity: 1 }];
        }
        // An easing the browser refuses (a stored curve's variable) plays
        // with its own numbers, which the fallback holds.
        var tryAnim = function (el, frames) {
            try { return el.animate(frames, opts); } catch (e) {
                var cb = /cubic-bezier\([^)]*\)/.exec(opts.easing);
                opts.easing = cb ? cb[0] : 'ease';
                return el.animate(frames, opts);
            }
        };
        tryAnim(a, outFrames);
        var last = tryAnim(b, inFrames);
        b.style.opacity = '1';
        a.style.opacity = '0';
        last.onfinish = function () {
            setTimeout(function () { a.style.opacity = ''; b.style.opacity = ''; }, 600);
        };
    }

    function bind(host, page) {
        var el = host.el;
        var put = function (next) {
            host.set(P.clean(next, host.easeClean));
            fill(host);
        };
        var copy = function () {
            var o = {};
            for (var k in page) if (Object.prototype.hasOwnProperty.call(page, k)) o[k] = page[k];
            return o;
        };
        el.querySelectorAll('[data-pt-kind]').forEach(function (b) {
            b.addEventListener('click', function () {
                var k = b.getAttribute('data-pt-kind');
                if (k === (page ? page.kind : '')) return;
                if (!k) { put(null); return; }
                var next = page ? copy() : {};
                next.kind = k;
                put(next);
            });
        });
        if (!page) return;
        var from = el.querySelector('[data-pt="from"]');
        if (from) from.addEventListener('change', function () { var n = copy(); n.from = from.value; put(n); });
        var range = el.querySelector('[data-pt="ms-range"]'), num = el.querySelector('[data-pt="ms"]');
        // The slider writes when let go; the number when it is left.
        range.addEventListener('input', function () { num.value = range.value; });
        range.addEventListener('change', function () { var n = copy(); n.duration = Number(range.value); put(n); });
        num.addEventListener('change', function () {
            var n = copy();
            if (num.value.trim() === '') delete n.duration; else n.duration = Number(num.value);
            put(n);
        });
        var sel = el.querySelector('[data-pt="easing"]');
        if (sel) sel.addEventListener('change', function () {
            if (sel.value === '__curve') return;
            var n = copy();
            if (sel.value) n.easing = sel.value; else delete n.easing;
            put(n);
        });
        host.easeWire(el.querySelector('[data-pt-ease]'), {
            at: 'pagetrans',
            value: page.easing || '',
            write: function (v) { var n = copy(); if (v) n.easing = v; else delete n.easing; put(n); },
            play: function () { play(host, page); }
        });
        var pb = el.querySelector('[data-pt-play]');
        if (pb) pb.addEventListener('click', function () { play(host, page); });
    }

    // ── Keep across pages ─────────────────────────────────────────────

    // Every name the design's elements hold: { name: { pages: [...], here: n } },
    // `here` counting the elements of the open page.
    function names(kh) {
        var out = {}, bodies = {};
        kh.classes().forEach(function (c) {
            if (bodies[c.cls] === undefined) bodies[c.cls] = P.nameOf(kh.bodyOf(c.cls));
            var n = bodies[c.cls];
            if (!n) return;
            var e = out[n] || (out[n] = { pages: [], here: 0 });
            if (c.page && e.pages.indexOf(c.page) === -1) e.pages.push(c.page);
            if (c.here) e.here++;
        });
        return out;
    }

    function keep(box, kh) {
        style();
        if (!box || !box.isConnected) return Promise.resolve();
        return current(kh.design()).then(function (page) {
            if (box.isConnected) keepDraw(box, kh, page);
        });
    }

    function keepDraw(box, kh, page) {
        var row = K.row;
        var body = kh.body();
        var name = P.nameOf(body);
        var h = '<div class="sd-pt-sub">' + esc(_sdT('Between pages')) + '</div>';
        if (kh.inList) {
            h += '<div class="sd-pt-dim">' + row(_sdT('Keep across pages'), K.swAttr('data-pk="on" disabled', false)) + '</div>' +
                '<div class="sd-prop-row"><div class="form-text small mb-0">' + esc(_sdT('An element repeated in a list cannot be kept across pages.')) + '</div></div>';
            box.innerHTML = h;
            return;
        }
        h += row(_sdT('Keep across pages'), K.swAttr('data-pk="on"', !!name));
        var all = names(kh);
        if (name) {
            var opts = Object.keys(all).sort();
            if (opts.indexOf(name) === -1) opts.unshift(name);
            h += row(_sdT('Name'), '<select class="form-control form-control-sm" data-pk="name" aria-label="' + esc(_sdT('Name')) + '">' +
                opts.map(function (n) {
                    var pages = all[n] ? all[n].pages.join(', ') : '';
                    return '<option value="' + esc(n) + '"' + (n === name ? ' selected' : '') + '>' + esc(pages ? n + ' (' + pages + ')' : n) + '</option>';
                }).join('') +
                '<option value="__new">' + esc(_sdT('A new name…')) + '</option></select>');
            h += '<div class="sd-prop-row" data-pk="new-row" hidden><input type="text" class="form-control form-control-sm" maxlength="40" spellcheck="false" data-pk="new" placeholder="' +
                esc(_sdT('Small letters, digits and hyphens')) + '" aria-label="' + esc(_sdT('A new name…')) + '"></div>';
            h += '<div class="sd-prop-row"><div class="form-text small mb-0">' +
                esc(_sdT('An element with the same name on the page that opens takes this one\'s place in one move.')) + '</div></div>';
            if (all[name] && all[name].here > 1) {
                h += '<div class="sd-prop-row"><div class="form-text small mb-0 fg-warning"><span class="bi bi-exclamation-triangle me-1"></span>' +
                    esc(_sdT('Two elements on this page are named “{var}”. A name is used once a page, so neither is kept.', name)) + '</div></div>';
            }
            if (!page) {
                h += '<div class="sd-prop-row"><div class="form-text small mb-0"><span class="bi bi-info-circle me-1"></span>' +
                    esc(_sdT('Nothing is kept until a “Page transition” is chosen in Settings, Theme.')) + '</div></div>';
            }
        }
        box.innerHTML = h;
        var sw = box.querySelector('[data-pk="on"]');
        sw.addEventListener('change', function () {
            if (!sw.checked) { kh.write(P.withName(body, '')); return; }
            var here = Object.keys(all).filter(function (n) { return all[n].here > 0; });
            kh.write(P.withName(body, P.suggest(kh.kind, kh.section, here)));
        });
        var sel = box.querySelector('[data-pk="name"]');
        if (!sel) return;
        var newRow = box.querySelector('[data-pk="new-row"]'), inp = box.querySelector('[data-pk="new"]');
        sel.addEventListener('change', function () {
            if (sel.value === '__new') { newRow.hidden = false; inp.focus(); return; }
            kh.write(P.withName(body, sel.value));
        });
        inp.addEventListener('change', function () {
            var n = P.nameClean(inp.value);
            if (n) kh.write(P.withName(body, n));
        });
    }

    return {
        fill: fill,
        keep: keep
    };
});
