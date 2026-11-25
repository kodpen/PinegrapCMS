/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Style guide part: the sheet the Preview menu's
 * "Style guide" row opens. What the design is made of on one sheet, in six
 * blocks: Colours (each colour's light and dark value and the contrast of
 * text on the surface), Type, Spacing, Shape, Controls (each in its states)
 * and the shared Components, each drawn once.
 *
 * The server works the blocks out (designer/style_guide,
 * includes/fn/design_style_guide.php) from the variables the editor holds,
 * the look and palette of the moment and the canvas's own stylesheet text;
 * this part draws them in a frame that carries the canvas's stylesheets, so
 * every sample is the real thing, unsaved changes included. It reads and
 * never writes: opening the sheet makes no page, file or setting.
 *
 * Not needed to open a page: the core runs this file the first time the row
 * is pressed (_sdStyleGuideOpen()) and hands it a host. While the sheet is
 * closed it holds no listener, timer or observer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('styleguide', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var sdToast = K.sdToast;

    var ORDER = ['colours', 'type', 'spacing', 'shape', 'controls', 'components'];
    // A component card is cut at this height until "Show all of it".
    var CARD_CUT = 360;
    // The most stylesheet text sent to the server (PG_STYLE_GUIDE_CSS_MAX).
    var CSS_MAX = 262144;

    // The open sheet: { el, host, guide, frame, doc, dark, hasDark, io,
    // off: [] (the listeners to take away on close) }; null while closed.
    var W = null;

    function attr(v) {
        return esc(String(v == null ? '' : v)).replace(/"/g, '&quot;');
    }

    // A CSS value put in a style attribute: no character that ends the
    // declaration or the attribute.
    function cssVal(v) {
        return String(v == null ? '' : v).replace(/[;{}<>"\\]/g, '');
    }

    function listen(target, name, fn, opts) {
        target.addEventListener(name, fn, opts);
        W.off.push(function () { target.removeEventListener(name, fn, opts); });
    }

    // ── The window ───────────────────────────────────────────────────────

    // The window's own look, put in the editor's document once.
    function chromeStyle() {
        if (document.getElementById('sd-sg-style')) return;
        var st = document.createElement('style');
        st.id = 'sd-sg-style';
        st.textContent =
            'dialog.sd-sg-dialog{width:min(1040px,calc(100vw - 48px));max-width:none;height:calc(100vh - 48px);max-height:none;margin:24px auto;padding:0;display:flex;flex-direction:column;overflow:hidden}' +
            'dialog.sd-sg-dialog:not([open]){display:none}' +
            '.sd-sg-head{display:flex;align-items:center;gap:10px;padding:10px 12px 10px 16px;border-bottom:1px solid var(--sd-panel-border)}' +
            '.sd-sg-head h5{margin:0;font-size:15px;font-weight:600;white-space:nowrap}' +
            '.sd-sg-name{color:var(--sd-text-muted);font-size:13px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
            '.sd-sg-gap{flex:1}' +
            '.sd-sg-seg{display:inline-flex;border:1px solid var(--sd-panel-border);border-radius:6px;overflow:hidden}' +
            '.sd-sg-seg button{border:0;background:none;color:var(--sd-text-muted);font-size:12px;padding:4px 10px;display:inline-flex;gap:5px;align-items:center}' +
            '.sd-sg-seg button[aria-pressed="true"]{background:var(--sd-accent-soft);color:var(--sd-text)}' +
            '.sd-sg-strip{display:flex;gap:2px;padding:0 12px;border-bottom:1px solid var(--sd-panel-border);overflow-x:auto;scrollbar-width:none;flex-shrink:0}' +
            '.sd-sg-strip button{border:0;background:none;color:var(--sd-text-muted);font-size:13px;padding:9px 10px;border-bottom:2px solid transparent;white-space:nowrap}' +
            '.sd-sg-strip button[aria-current="true"]{color:var(--sd-text);border-bottom-color:var(--sd-accent)}' +
            '.sd-sg-strip button:focus-visible{outline:2px solid var(--sd-focus-ring,var(--sd-accent));outline-offset:-2px}' +
            '.sd-sg-line{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:8px 16px;border-bottom:1px solid var(--sd-panel-border);font-size:13px;color:var(--sd-text)}' +
            '.sd-sg-line[hidden]{display:none}' +
            '.sd-sg-line .bi{color:var(--sd-accent)}' +
            '.sd-sg-line>span:not(.bi){flex:1;min-width:220px}' +
            '.sd-sg-body{position:relative;flex:1;min-height:0;background:#fff}' +
            '.sd-sg-body iframe{position:absolute;inset:0;width:100%;height:100%;border:0;background:#fff}' +
            '.sd-sg-wait{position:absolute;inset:0;padding:32px 40px;overflow:hidden;background:var(--sd-panel-bg)}' +
            '.sd-sg-wait i{display:block;height:12px;border-radius:4px;background:var(--sd-panel-border);margin:0 0 14px}' +
            '.sd-sg-wait b{display:block;height:18px;width:22%;border-radius:4px;background:var(--sd-panel-border);margin:28px 0 16px}' +
            '@media (max-width:767.98px){' +
                'dialog.sd-sg-dialog{width:100vw;height:100vh;height:100dvh;margin:0;border-radius:0}' +
                '.sd-sg-name{display:none}' +
                '.sd-sg-wait{padding:20px 16px}' +
            '}';
        document.head.appendChild(st);
    }

    function open(host) {
        if (W) { W.el.focus(); return; }
        chromeStyle();
        var canvasDark = !!(K.canvasDoc && K.canvasDoc.documentElement && K.canvasDoc.documentElement.getAttribute('data-bs-theme') === 'dark');
        // A design without a dark mode shows one value per colour and no
        // switch; the sheet opens on the mode the canvas shows.
        var hasDark = host.colorMode === 'dark' || host.colorMode === 'auto' || canvasDark;
        var d = document.createElement('dialog');
        d.className = 'dialog sd-settings-modal-dark sd-modal-content sd-sg-dialog';
        d.tabIndex = -1;
        d.setAttribute('aria-labelledby', 'sd-sg-title');
        d.innerHTML =
            '<div class="sd-sg-head">' +
                '<h5 id="sd-sg-title">' + esc(_sdT('Style guide')) + '</h5>' +
                (host.name ? '<span class="sd-sg-name">' + esc(host.name) + '</span>' : '') +
                '<span class="sd-sg-gap"></span>' +
                (hasDark ? '<span class="sd-sg-seg" role="group" aria-label="' + attr(_sdT('Colour mode')) + '">' +
                    '<button type="button" data-sg-mode="light" aria-pressed="' + !canvasDark + '"><span class="bi bi-sun" aria-hidden="true"></span>' + esc(_sdT('Light')) + '</button>' +
                    '<button type="button" data-sg-mode="dark" aria-pressed="' + canvasDark + '"><span class="bi bi-moon" aria-hidden="true"></span>' + esc(_sdT('Dark')) + '</button>' +
                '</span>' : '') +
                '<button type="button" class="btn-close fg-white" data-bs-dismiss="dialog" aria-label="' + attr(_sdT('Close')) + '"></button>' +
            '</div>' +
            '<div class="sd-sg-strip" role="toolbar" aria-label="' + attr(_sdT('Style guide')) + '"></div>' +
            '<div class="sd-sg-line" hidden></div>' +
            '<div class="sd-sg-body">' + waitHtml() + '</div>';
        document.body.appendChild(d);
        W = { el: d, host: host, guide: null, frame: null, doc: null, dark: canvasDark, hasDark: hasDark, io: null, off: [], seq: 0 };
        var mine = W;

        d.addEventListener('click', function (e) {
            var m = e.target.closest('[data-sg-mode]');
            if (m) { setMode(m.getAttribute('data-sg-mode') === 'dark'); return; }
            var b = e.target.closest('[data-sg-block]');
            if (b) { goTo(b.getAttribute('data-sg-block')); return; }
            if (e.target.closest('[data-sg-ask]')) {
                var ask = host.askQuestions;
                close();
                if (typeof ask === 'function') ask();
            }
        });
        d.querySelector('.sd-sg-strip').addEventListener('keydown', stripKeys);
        pgUI.dialog.on(d, 'hidden', function () { if (W === mine) cleanup(); });
        pgUI.dialog.show(d);

        K._pgApiPost('style_guide', request(host)).then(function (res) {
            if (W !== mine) return;
            if (!res || res.status !== 'success' || !res.guide) {
                sdToast(esc((res && res.message) ? res.message : _sdT('Network error.')), 'error', 5000);
                close();
                return;
            }
            W.guide = res.guide;
            drawChrome();
            drawFrame();
        }).catch(function () {
            if (W !== mine) return;
            sdToast(esc(_sdT('Network error.')), 'error', 5000);
            close();
        });
    }

    // Grey lines in the blocks' places until the frame is drawn.
    function waitHtml() {
        var h = '<div class="sd-sg-wait" aria-busy="true" aria-label="' + attr(_sdT('Loading…')) + '">';
        for (var b = 0; b < 3; b++) {
            h += '<b></b>';
            for (var i = 0; i < 4; i++) h += '<i style="width:' + (92 - i * 17) + '%"></i>';
        }
        return h + '</div>';
    }

    // What the server needs: the look and palette of the moment, the
    // variables the Variables tab holds (else the stored file) and the
    // canvas's own stylesheet text.
    function request(host) {
        var body = { look: host.look, palette: host.palette, css: designCss() };
        var model = host.model();
        if (model) body.model = model;
        else if (host.stored && host.styleId) body.style_id = host.styleId;
        return body;
    }

    // The design's own stylesheet text as the canvas has it: its inline
    // styles and the readable rules of its linked files, without Bootstrap,
    // the theme sheets (the model has the variables) and the editor's own.
    function designCss() {
        var doc = K.canvasDoc;
        var out = '';
        if (doc && doc.head) {
            Array.prototype.forEach.call(doc.head.querySelectorAll('style, link[rel="stylesheet"]'), function (n) {
                if (out.length >= CSS_MAX || skipSheet(n)) return;
                if (n.tagName === 'STYLE') { out += n.textContent + '\n'; return; }
                var rules = null;
                try { rules = n.sheet ? n.sheet.cssRules : null; } catch (e) { rules = null; }
                if (!rules) return;
                for (var i = 0; i < rules.length && out.length < CSS_MAX; i++) out += rules[i].cssText + '\n';
            });
        }
        if (!out && typeof K._userStylesGet === 'function') out = String(K._userStylesGet() || '');
        return out.slice(0, CSS_MAX);
    }

    function skipSheet(n) {
        var id = n.id || '';
        if (n.hasAttribute('data-pg-theme') || id.indexOf('sd-') === 0 || id.indexOf('pg-theme-') === 0) return true;
        var href = n.getAttribute('href') || '';
        return /\/bootstrap(\.rtl)?(\.min)?\.css(\?|#|$)|bootstrap-icons|fonts\.googleapis/.test(href);
    }

    function close() {
        if (!W) return;
        var d = W.el;
        if (d.open) pgUI.dialog.hide(d);
        else cleanup();
    }

    function cleanup() {
        if (!W) return;
        var w = W;
        W = null;
        w.off.forEach(function (f) { try { f(); } catch (e) {} });
        if (w.io) w.io.disconnect();
        if (w.el.parentNode) w.el.parentNode.removeChild(w.el);
        var b = w.host.button;
        if (b && b.isConnected) b.focus();
    }

    // The strip of block names and, for a design without variables, the
    // line offering the start questions.
    function drawChrome() {
        var strip = W.el.querySelector('.sd-sg-strip');
        strip.innerHTML = ORDER.map(function (key, i) {
            var b = W.guide.blocks[key];
            return '<button type="button" data-sg-block="' + key + '" tabindex="' + (i === 0 ? '0' : '-1') + '"' +
                (i === 0 ? ' aria-current="true"' : '') + '>' + esc(b ? b.title : key) + '</button>';
        }).join('');
        var line = W.el.querySelector('.sd-sg-line');
        if (W.guide.offer_start) {
            line.innerHTML = '<span class="bi bi-lightbulb" aria-hidden="true"></span><span>' +
                esc(_sdT('This design has no variables yet: these are its Theme\'s values. Four questions turn them into variables you can name and reuse.')) +
                '</span><button type="button" class="btn btn-sm btn-outline theme-primary" data-sg-ask>' + esc(_sdT('Answer the questions')) + '</button>';
            line.hidden = false;
        }
    }

    // The strip is one Tab stop; the arrows, Home and End move over it.
    function stripKeys(e) {
        var keys = { ArrowRight: 1, ArrowLeft: -1, Home: 'first', End: 'last' };
        if (!(e.key in keys)) return;
        var btns = Array.prototype.slice.call(e.currentTarget.querySelectorAll('button'));
        var at = btns.indexOf(document.activeElement);
        if (at === -1) return;
        e.preventDefault();
        var k = keys[e.key];
        var to = k === 'first' ? 0 : (k === 'last' ? btns.length - 1 : (at + k + btns.length) % btns.length);
        btns.forEach(function (b, i) { b.tabIndex = i === to ? 0 : -1; });
        btns[to].focus();
    }

    function goTo(key) {
        if (!W || !W.doc) return;
        var el = W.doc.getElementById('pgsg-' + key);
        // The block asked for stays marked while the sheet scrolls to it,
        // also when the sheet ends before it reaches the top.
        W.hold = Date.now() + 400;
        if (el) el.scrollIntoView({ block: 'start' });
        mark(key);
    }

    // Marks the block on screen in the strip and keeps it in view there.
    function mark(key) {
        var strip = W.el.querySelector('.sd-sg-strip');
        Array.prototype.forEach.call(strip.querySelectorAll('button'), function (b) {
            var on = b.getAttribute('data-sg-block') === key;
            if (on) {
                b.setAttribute('aria-current', 'true');
                var l = b.offsetLeft, r = l + b.offsetWidth;
                if (l < strip.scrollLeft) strip.scrollLeft = l;
                else if (r > strip.scrollLeft + strip.clientWidth) strip.scrollLeft = r - strip.clientWidth;
            } else {
                b.removeAttribute('aria-current');
            }
        });
    }

    function onScroll() {
        if (W.raf || (W.hold && Date.now() < W.hold)) return;
        var win = W.doc.defaultView;
        W.raf = win.requestAnimationFrame(function () {
            W.raf = 0;
            var on = ORDER[0];
            var doc = W.doc;
            var atEnd = win.innerHeight + win.scrollY >= doc.documentElement.scrollHeight - 2;
            ORDER.forEach(function (key) {
                var el = doc.getElementById('pgsg-' + key);
                if (el && el.getBoundingClientRect().top <= 48) on = key;
            });
            if (atEnd) on = ORDER[ORDER.length - 1];
            mark(on);
        });
    }

    function setMode(dark) {
        if (!W || W.dark === dark) return;
        W.dark = dark;
        Array.prototype.forEach.call(W.el.querySelectorAll('[data-sg-mode]'), function (b) {
            b.setAttribute('aria-pressed', String((b.getAttribute('data-sg-mode') === 'dark') === dark));
        });
        if (!W.doc) return;
        W.doc.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        var pairs = W.doc.getElementById('pgsg-pairs');
        if (pairs) pairs.innerHTML = pairsHtml(W.guide.blocks.colours);
        var spacing = W.doc.getElementById('pgsg-spacing');
        if (spacing) spacing.outerHTML = spacingHtml(W.guide.blocks.spacing);
    }

    // ── The frame ────────────────────────────────────────────────────────

    // The canvas's head as the frame's: its base, its stylesheets (theme,
    // fonts and icons included) and none of the editor's own.
    function headHtml() {
        var doc = K.canvasDoc;
        var h = '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        if (!doc || !doc.head) return h;
        var base = doc.querySelector('base[href]');
        if (base) h += '<base href="' + attr(base.getAttribute('href')) + '">';
        Array.prototype.forEach.call(doc.head.querySelectorAll('link[rel="stylesheet"], style'), function (n) {
            var id = n.id || '';
            if (id.indexOf('sd-') === 0 && id !== 'sd-icons-css') return;
            h += n.outerHTML;
        });
        return h;
    }

    function drawFrame() {
        var body = W.el.querySelector('.sd-sg-body');
        var frame = document.createElement('iframe');
        frame.title = _sdT('Style guide');
        frame.style.visibility = 'hidden';
        body.appendChild(frame);
        var doc = frame.contentDocument;
        var lang = (K.canvasDoc && K.canvasDoc.documentElement && K.canvasDoc.documentElement.lang) || document.documentElement.lang || 'en';
        doc.open();
        doc.write('<!DOCTYPE html><html lang="' + attr(lang) + '" data-bs-theme="' + (W.dark ? 'dark' : 'light') + '"><head>' + headHtml() +
            '<style id="pgsg-sheet">' + sheetCss() + '</style></head><body class="pgsg-body">' + sheetHtml() + '</body></html>');
        doc.close();
        W.frame = frame;
        W.doc = doc;
        var mine = W;
        var shown = false;
        var show = function () {
            if (shown || W !== mine) return;
            shown = true;
            forceStates(doc);
            frame.style.visibility = '';
            var wait = body.querySelector('.sd-sg-wait');
            if (wait) wait.parentNode.removeChild(wait);
            watchComponents();
        };
        // Shown once every stylesheet has loaded (the forced states read
        // their rules); one that never answers does not hold the sheet back.
        var links = Array.prototype.slice.call(doc.querySelectorAll('link[rel="stylesheet"]'));
        var left = links.length;
        var one = function () { if (--left <= 0) show(); };
        links.forEach(function (l) {
            var ready = false;
            try { ready = !!(l.sheet && l.sheet.cssRules); } catch (e) { ready = !!l.sheet; }
            if (ready) { left--; return; }
            l.addEventListener('load', one);
            l.addEventListener('error', one);
        });
        var t = setTimeout(show, 4000);
        W.off.push(function () { clearTimeout(t); });
        listen(frame.contentWindow, 'scroll', onScroll, { passive: true });
        listen(doc, 'keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); close(); }
        });
        if (left <= 0) show();
    }

    // The sheet's own layout. Its classes start with pgsg- so the design's
    // rules leave them alone; the samples inside take the design's rules.
    function sheetCss() {
        return 'html{scroll-behavior:auto}' +
            'body.pgsg-body{margin:0}' +
            '.pgsg{padding:32px 40px 80px;font-size:14px;line-height:1.45}' +
            '.pgsg-top{display:flex;flex-wrap:wrap;gap:6px 14px;align-items:baseline;padding-bottom:16px;margin-bottom:24px;border-bottom:1px solid var(--bs-border-color,#dee2e6)}' +
            '.pgsg-top strong{font-size:20px}' +
            '.pgsg-top .pgsg-day{margin-left:auto}' +
            '.pgsg-mute{color:var(--bs-secondary-color,#6c757d)}' +
            '.pgsg-block{padding:8px 0 40px;scroll-margin-top:8px}' +
            '.pgsg-h{font-size:18px;font-weight:600;margin:0 0 14px;line-height:1.3}' +
            '.pgsg-h3{font-size:14px;font-weight:600;margin:22px 0 10px}' +
            '.pgsg-code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px}' +
            '.pgsg-card{border:1px solid var(--bs-border-color,#dee2e6);border-radius:8px;overflow:hidden;background:var(--bs-body-bg,#fff)}' +
            '.pgsg-tag{display:inline-block;font-size:11px;padding:0 6px;border-radius:9px;border:1px solid var(--bs-border-color,#dee2e6);margin-left:6px;vertical-align:1px}' +
            '.pgsg-sw{display:grid;grid-template-columns:repeat(auto-fill,minmax(168px,1fr));gap:12px}' +
            '.pgsg-swt{display:flex;height:72px}.pgsg-swt i{flex:1}' +
            '.pgsg-tx{padding:8px 10px;font-size:13px}' +
            '.pgsg-name{font-weight:600}' +
            '.pgsg-pairs{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(400px,100%),1fr));gap:8px}' +
            '.pgsg-pair{display:flex;align-items:center;gap:12px;padding:8px 12px;font-size:13px}' +
            '.pgsg-aa{flex:0 0 56px;height:36px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:16px;border:1px solid var(--bs-border-color-translucent,rgba(0,0,0,.1))}' +
            '.pgsg-pair .pgsg-grow{flex:1;min-width:0}' +
            '.pgsg-ratio{font-variant-numeric:tabular-nums;white-space:nowrap}' +
            '.pgsg-verdict{display:inline-flex;gap:5px;align-items:center;white-space:nowrap;font-weight:600}' +
            '.pgsg-fonts{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(280px,100%),1fr));gap:12px;margin-bottom:8px}' +
            '.pgsg-font{padding:12px 14px}.pgsg-font .pgsg-aa2{font-size:32px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
            '.pgsg-trow{display:flex;align-items:center;gap:16px;padding:10px 0;border-bottom:1px solid var(--bs-border-color-translucent,rgba(0,0,0,.1))}' +
            '.pgsg-ts{flex:1;min-width:0;overflow:hidden}' +
            '.pgsg-ts>*{margin:0!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;display:block}' +
            '.pgsg-tm{flex:0 0 220px;text-align:right;font-size:12px}' +
            '.pgsg-scale{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px 22px}' +
            '.pgsg-step{display:inline-flex;flex-direction:column}.pgsg-step>span{line-height:1.1}.pgsg-step small{font-size:11px}' +
            '.pgsg-srow{display:flex;align-items:center;gap:12px;padding:5px 0;font-size:13px}' +
            '.pgsg-sn{flex:0 0 150px}.pgsg-sb{flex:1;min-width:0}' +
            '.pgsg-sb i{display:block;height:12px;border-radius:2px;max-width:100%;background:currentColor;opacity:.45}' +
            '.pgsg-srow.is-own .pgsg-sb i{opacity:1}' +
            '.pgsg-sp{flex:0 0 64px;text-align:right;font-variant-numeric:tabular-nums}' +
            '.pgsg-shape{display:grid;grid-template-columns:repeat(auto-fill,minmax(168px,1fr));gap:12px}' +
            '.pgsg-tile{padding:14px}.pgsg-tile u{display:block;height:64px;margin-bottom:10px;text-decoration:none;background:var(--bs-tertiary-bg,#f8f9fa);border:1px solid var(--bs-border-color,#dee2e6)}' +
            '.pgsg-crow{display:grid;grid-template-columns:150px repeat(4,minmax(0,1fr));gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--bs-border-color-translucent,rgba(0,0,0,.1))}' +
            '.pgsg-cell{display:flex;flex-direction:column;align-items:flex-start;gap:6px;min-width:0}' +
            '.pgsg-cell small{font-size:11px}' +
            '.pgsg-live{pointer-events:none;max-width:100%}' +
            '.pgsg-comps{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start}' +
            '.pgsg-chead{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid var(--bs-border-color,#dee2e6);font-size:13px}' +
            '.pgsg-chead .pgsg-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
            '.pgsg-cbody{position:relative;overflow:hidden}' +
            '.pgsg-cbody.is-cut{max-height:' + CARD_CUT + 'px}' +
            '.pgsg-cbody.is-cut:after{content:"";position:absolute;left:0;right:0;bottom:0;height:64px;background:linear-gradient(transparent,var(--bs-body-bg,#fff));pointer-events:none}' +
            '.pgsg-more{display:block;width:100%;border:0;border-top:1px solid var(--bs-border-color,#dee2e6);background:none;padding:8px;font-size:13px;color:inherit}' +
            '.pgsg-ghost{padding:14px}.pgsg-ghost i{display:block;height:10px;border-radius:4px;background:var(--bs-secondary-bg,#e9ecef);margin:0 0 10px}' +
            '.pgsg-note{padding:14px;font-size:13px}' +
            '@media (max-width:719.98px){.pgsg-crow{grid-template-columns:repeat(2,minmax(0,1fr))}.pgsg-crow>.pgsg-cn{grid-column:1/-1}}' +
            '@media (max-width:767.98px){' +
                '.pgsg{padding:20px 16px 60px}' +
                '.pgsg-sw{grid-template-columns:repeat(2,minmax(0,1fr))}' +
                '.pgsg-comps{grid-template-columns:minmax(0,1fr)}' +
                '.pgsg-trow{flex-direction:column;align-items:stretch;gap:4px}.pgsg-tm{flex:none;text-align:left}' +
                '.pgsg-sn{flex-basis:96px}' +
            '}';
    }

    // ── The sheet ────────────────────────────────────────────────────────

    function sheetHtml() {
        var g = W.guide;
        var day = '';
        try { day = new Date().toLocaleDateString(document.documentElement.lang || undefined, { day: 'numeric', month: 'long', year: 'numeric' }); }
        catch (e) { day = new Date().toDateString(); }
        var h = '<div class="pgsg"><div class="pgsg-top"><strong>' + esc(W.host.name || _sdT('Style guide')) + '</strong>' +
            '<span class="pgsg-mute">' + esc(_sdT('Style guide')) + '</span><span class="pgsg-day pgsg-mute">' + esc(day) + '</span></div>';
        h += block('colours', coloursHtml(g.blocks.colours));
        h += block('type', typeHtml(g.blocks.type));
        h += block('spacing', spacingHtml(g.blocks.spacing));
        h += block('shape', shapeHtml(g.blocks.shape));
        h += block('controls', controlsHtml(g.blocks.controls));
        h += block('components', componentsHtml(g.blocks.components));
        return h + '</div>';
    }

    function block(key, inner) {
        var b = W.guide.blocks[key] || {};
        return '<section class="pgsg-block" id="pgsg-' + key + '" aria-labelledby="pgsg-h-' + key + '">' +
            '<div class="pgsg-h" role="heading" aria-level="2" id="pgsg-h-' + key + '">' + esc(b.title || '') + '</div>' + inner + '</section>';
    }

    // A colour's value in the mode the sheet shows.
    function colourOf(rows, key) {
        for (var i = 0; i < rows.length; i++) if (rows[i].key === key) return rows[i];
        return null;
    }

    function coloursHtml(b) {
        var h = '<div class="pgsg-sw">';
        (b.rows || []).forEach(function (r) {
            h += '<div class="pgsg-card"><div class="pgsg-swt" aria-hidden="true"><i style="background:' + attr(cssVal(r.light)) + '"></i>' +
                (W.hasDark ? '<i style="background:' + attr(cssVal(r.dark)) + '"></i>' : '') + '</div>' +
                '<div class="pgsg-tx"><span class="pgsg-name">' + esc(r.name) + '</span>' +
                (r.custom ? '<span class="pgsg-tag">' + esc(_sdT('Your own')) + '</span>' : '') +
                '<br><span class="pgsg-code pgsg-mute">' + esc(r.light) + (W.hasDark ? ' · ' + esc(r.dark) : '') + '</span></div></div>';
        });
        h += '</div><div class="pgsg-h3" role="heading" aria-level="3">' + esc(_sdT('Text on the surface')) + '</div>';
        return h + '<div class="pgsg-pairs" id="pgsg-pairs">' + pairsHtml(b) + '</div>';
    }

    // The contrast pairs of the mode shown: the sample, the names, the
    // ratio and the verdict in words with an icon, never colour alone.
    function pairsHtml(b) {
        var mode = W.dark ? 'dark' : 'light';
        var rows = b.rows || [];
        return (b.pairs || []).filter(function (p) { return p.mode === mode; }).map(function (p) {
            var fg = colourOf(rows, p.text), bg = colourOf(rows, p.surface);
            if (!fg || !bg) return '';
            var v = p.ok ? ['bi-check-circle-fill', _sdT('Passes')]
                : (p.large_ok ? ['bi-exclamation-triangle-fill', _sdT('Large text only')] : ['bi-x-circle-fill', _sdT('Too weak for text')]);
            return '<div class="pgsg-card pgsg-pair"><span class="pgsg-aa" style="color:' + attr(cssVal(fg[mode])) + ';background:' + attr(cssVal(bg[mode])) + '" aria-hidden="true">Aa</span>' +
                '<span class="pgsg-grow">' + esc(_sdT('{var:1} on {var:2}', [fg.name, bg.name])) + '</span>' +
                '<span class="pgsg-ratio pgsg-mute">' + esc(Number(p.ratio).toFixed(2)) + ':1</span>' +
                '<span class="pgsg-verdict"><span class="bi ' + v[0] + '" aria-hidden="true"></span>' + esc(v[1]) + '</span></div>';
        }).join('');
    }

    // A text style's sample: the element its selector names, so the
    // design's own rules size it.
    function styleSample(s, text) {
        var t = esc(text);
        var sel = String(s.selector || '');
        if (/^h[1-6]$/.test(sel)) return '<' + sel + '>' + t + '</' + sel + '>';
        if (sel === 'body') return '<p>' + t + '</p>';
        if (sel === 'small') return '<small>' + t + '</small>';
        var m = /^\.([a-zA-Z][a-zA-Z0-9_-]*)$/.exec(sel);
        return m ? '<div class="' + attr(m[1]) + '">' + t + '</div>' : '<p>' + t + '</p>';
    }

    function typeHtml(b) {
        var sample = _sdT('A clear sentence shows how this style reads on the page');
        var h = '<div class="pgsg-fonts">';
        (b.fonts || []).forEach(function (f) {
            h += '<div class="pgsg-card pgsg-font"><div class="pgsg-aa2" style="font-family:' + attr(cssVal(f.value)) + '" aria-hidden="true">Aa Bb Cc 123</div>' +
                '<span class="pgsg-name">' + esc(f.name) + '</span><br><span class="pgsg-code pgsg-mute">' + esc(f.value) + '</span></div>';
        });
        h += '</div>';
        (b.styles || []).forEach(function (s) {
            var meta = [s.size, s.px != null ? s.px + ' px' : '', s.weight].filter(function (x) { return x !== '' && x != null; }).join(' · ');
            var note = s.source === 'stylesheet' ? _sdT('changed in the stylesheet') : (s.source === 'class' ? _sdT('your own class') : '');
            h += '<div class="pgsg-trow"><div class="pgsg-ts pgsg-live" inert>' + styleSample(s, sample) + '</div>' +
                '<div class="pgsg-tm"><span class="pgsg-name">' + esc(s.name) + '</span><br><span class="pgsg-code pgsg-mute">' + esc(meta) + '</span>' +
                (note ? '<br><span class="pgsg-mute">' + esc(note) + '</span>' : '') + '</div></div>';
        });
        var heading = (b.fonts && b.fonts[0]) ? b.fonts[0].value : '';
        h += '<div class="pgsg-h3" role="heading" aria-level="3">' + esc(_sdT('Scale')) + '</div><div class="pgsg-scale">';
        (b.scale || []).forEach(function (st) {
            h += '<span class="pgsg-step"><span style="font-size:' + (parseInt(st.px, 10) || 16) + 'px;font-family:' + attr(cssVal(heading)) + '" aria-hidden="true">Aa</span>' +
                '<small class="pgsg-mute">' + esc(st.name) + ' · ' + esc(st.px) + ' px</small></span>';
        });
        return h + '</div>';
    }

    // Spacing: a bar per size at three times its length; the designer's own
    // sizes last, in the accent colour of the mode shown.
    function spacingHtml(b) {
        var accent = colourOf(W.guide.blocks.colours.rows || [], 'accent');
        var own = accent ? cssVal(accent[W.dark ? 'dark' : 'light']) : '';
        var h = '<div id="pgsg-spacing">';
        (b.rows || []).forEach(function (r) {
            var px = (r.px == null) ? 0 : Number(r.px);
            h += '<div class="pgsg-srow' + (r.custom ? ' is-own' : '') + '"><span class="pgsg-sn">' + esc(r.name) + '</span>' +
                '<span class="pgsg-sb"><i style="width:' + Math.max(1, Math.round(px * 3)) + 'px' + (r.custom && own ? ';color:' + attr(own) : '') + '"></i></span>' +
                '<span class="pgsg-sp pgsg-mute">' + esc(r.px != null ? r.px + ' px' : r.size) + '</span></div>';
        });
        return h + '</div>';
    }

    // Shape: a tile per corner size, line width and shadow, each drawn with
    // the value it names; the shadow by its option's label.
    function shapeHtml(b) {
        var h = '<div class="pgsg-shape">';
        (b.rows || []).forEach(function (r) {
            var v = cssVal(r.value);
            var tile = '';
            if (r.key === 'radius') tile = '<u style="border-radius:' + attr(v) + '"></u>';
            else if (r.key === 'border_width') tile = '<u style="border:' + attr(v || '1px') + ' solid currentColor;opacity:.8"></u>';
            else tile = '<u class="shadow" style="border-color:transparent"></u>';
            h += '<div class="pgsg-card pgsg-tile">' + tile + '<span class="pgsg-name">' + esc(r.name) + '</span><br>' +
                '<span class="pgsg-code pgsg-mute">' + esc(r.label != null && r.label !== '' ? r.label : '—') + '</span></div>';
        });
        return h + '</div>';
    }

    // One control in one state. The state is forced through a class the
    // frame's copy of the design's :hover and :focus rules answers
    // (forceStates()), so a missing focus ring shows as missing.
    function controlSample(key, state) {
        var cls = state === 'hover' ? ' pgsg-s-hover' : (state === 'focus' ? ' pgsg-s-focus' : '');
        var off = state === 'disabled';
        switch (key) {
            case 'button-primary':
            case 'button-secondary':
            case 'button-outline':
                var kind = key === 'button-primary' ? 'btn-primary' : (key === 'button-secondary' ? 'btn-secondary' : 'btn-outline-primary');
                return '<button type="button" class="btn ' + kind + cls + '" tabindex="-1"' + (off ? ' disabled' : '') + '>' + esc(_sdT('Button')) + '</button>';
            case 'link':
                return '<a href="#" class="' + cls.trim() + (off ? ' disabled' : '') + '" tabindex="-1"' + (off ? ' aria-disabled="true"' : '') + '>' + esc(_sdT('A link')) + '</a>';
            case 'input':
                return '<input type="text" class="form-control' + cls + '" tabindex="-1" value="' + attr(_sdT('Your text')) + '"' + (off ? ' disabled' : '') + '>';
            case 'select':
                return '<select class="form-select' + cls + '" tabindex="-1"' + (off ? ' disabled' : '') + '><option>' + esc(_sdT('A choice')) + '</option></select>';
            case 'check':
                return '<div class="form-check"><input type="checkbox" class="form-check-input' + cls + '" tabindex="-1"' +
                    (state === 'checked' ? ' checked' : '') + (off ? ' disabled' : '') + '><label class="form-check-label">' + esc(_sdT('Tick box')) + '</label></div>';
        }
        return '';
    }

    function controlsHtml(b) {
        var labels = b.states || {};
        var h = '';
        (b.rows || []).forEach(function (c) {
            h += '<div class="pgsg-crow"><span class="pgsg-cn pgsg-name">' + esc(c.name) + '</span>';
            (c.states || []).forEach(function (st) {
                h += '<div class="pgsg-cell"><div class="pgsg-live" inert>' + controlSample(c.key, st) + '</div>' +
                    '<small class="pgsg-mute">' + esc(labels[st] || st) + '</small></div>';
            });
            h += '</div>';
        });
        return h;
    }

    function pagesText(n) {
        n = parseInt(n, 10) || 0;
        if (n === 0) return _sdT('not placed');
        return n === 1 ? _sdT('on 1 page') : _sdT('on {var} pages', n);
    }

    function componentsHtml(b) {
        var rows = b.rows || [];
        if (!rows.length) return '<p class="pgsg-mute">' + esc(_sdT('This design has no shared component yet.')) + '</p>';
        var h = '<div class="pgsg-comps">';
        rows.forEach(function (c) {
            h += '<div class="pgsg-card" data-sg-comp="' + (parseInt(c.id, 10) || 0) + '"><div class="pgsg-chead">' +
                '<span class="bi bi-puzzle" aria-hidden="true"></span><span class="pgsg-name">' + esc(c.name) + '</span>' +
                '<span class="pgsg-mute">' + esc(pagesText(c.pages)) + '</span></div>' +
                '<div class="pgsg-ghost" aria-hidden="true"><i style="width:90%"></i><i style="width:72%"></i><i style="width:54%"></i></div></div>';
        });
        return h + '</div>';
    }

    // ── Components, drawn as they come within a screen of the view ──────

    function watchComponents() {
        var doc = W.doc;
        var cards = doc.querySelectorAll('[data-sg-comp]');
        if (!cards.length) return;
        var win = doc.defaultView;
        var mine = W;
        var draw = function (list) {
            var ids = [];
            list.forEach(function (card) {
                var id = parseInt(card.getAttribute('data-sg-comp'), 10);
                var c = K.sharedCache[id];
                if (id > 0 && !(c && c.tree)) ids.push(id);
            });
            var done = function () {
                if (W !== mine) return;
                list.forEach(drawCard);
            };
            if (ids.length) K.prefetchShared(ids, done);
            else done();
        };
        if (!win.IntersectionObserver) { draw(Array.prototype.slice.call(cards)); return; }
        W.io = new win.IntersectionObserver(function (entries) {
            var list = [];
            entries.forEach(function (en) {
                if (!en.isIntersecting) return;
                W.io.unobserve(en.target);
                list.push(en.target);
            });
            if (list.length) draw(list);
        }, { rootMargin: '100% 0px' });
        Array.prototype.forEach.call(cards, function (c) { W.io.observe(c); });
        listen(doc, 'click', function (e) {
            var more = e.target.closest('.pgsg-more');
            if (!more) return;
            var body = more.previousElementSibling;
            if (body) body.classList.remove('is-cut');
            more.parentNode.removeChild(more);
        });
    }

    function drawCard(card) {
        var id = parseInt(card.getAttribute('data-sg-comp'), 10);
        var ghost = card.querySelector('.pgsg-ghost');
        if (!ghost) return;
        var html = '';
        var c = K.sharedCache[id];
        if (c && c.tree) {
            try { html = K._sdEmitNode({ type: 'shared_ref', props: { sharedId: id }, children: [] }, 0, 0); } catch (e) { html = ''; }
        }
        var doc = card.ownerDocument;
        var body = doc.createElement('div');
        if (!String(html).trim()) {
            body.className = 'pgsg-note pgsg-mute';
            body.textContent = _sdT('This component could not be drawn here. Its name opens it.');
            card.replaceChild(body, ghost);
            return;
        }
        body.className = 'pgsg-cbody';
        body.innerHTML = '<div class="pgsg-live" inert>' + html + '</div>';
        Array.prototype.forEach.call(body.querySelectorAll('a, button, input, select, textarea, [tabindex]'), function (n) { n.setAttribute('tabindex', '-1'); });
        card.replaceChild(body, ghost);
        if (body.scrollHeight > CARD_CUT) {
            body.classList.add('is-cut');
            var more = doc.createElement('button');
            more.type = 'button';
            more.className = 'pgsg-more';
            more.textContent = _sdT('Show all of it');
            card.appendChild(more);
        }
    }

    // ── Forced states ────────────────────────────────────────────────────

    // A copy of every :hover, :focus and :focus-visible rule of the frame's
    // stylesheets with the state as a class, so a sample shows the state
    // without a pointer or a focus. A sheet that cannot be read (another
    // origin) is passed over.
    function forceStates(doc) {
        var out = [];
        var swap = function (sel) {
            var parts = sel.split(',');
            var kept = [];
            parts.forEach(function (p) {
                if (!/:(hover|focus-visible|focus)\b/.test(p)) return;
                kept.push(p.replace(/:hover\b/g, '.pgsg-s-hover').replace(/:focus-visible\b|:focus\b/g, '.pgsg-s-focus'));
            });
            return kept.join(',');
        };
        var walk = function (rules, depth) {
            var text = '';
            for (var i = 0; rules && i < rules.length; i++) {
                var r = rules[i];
                if (r.selectorText) {
                    if (r.selectorText.indexOf(':hover') === -1 && r.selectorText.indexOf(':focus') === -1) continue;
                    var sel = swap(r.selectorText);
                    if (sel) text += sel + '{' + r.style.cssText + '}';
                } else if (r.cssRules && depth < 3) {
                    var inner = walk(r.cssRules, depth + 1);
                    if (!inner) continue;
                    var head = r.media ? '@media ' + r.media.mediaText : (r.conditionText ? '@supports ' + r.conditionText : (r.name !== undefined && r.constructor && /Layer/.test(r.constructor.name) ? '@layer ' + r.name : ''));
                    text += head ? head + '{' + inner + '}' : inner;
                }
            }
            return text;
        };
        Array.prototype.forEach.call(doc.styleSheets, function (sheet) {
            if (sheet.ownerNode && sheet.ownerNode.id === 'pgsg-sheet') return;
            var rules = null;
            try { rules = sheet.cssRules; } catch (e) { rules = null; }
            var t = walk(rules, 0);
            if (t) out.push(t);
        });
        if (!out.length) return;
        var st = doc.createElement('style');
        st.id = 'pgsg-states';
        st.textContent = out.join('\n');
        doc.head.appendChild(st);
    }

    return { open: open, close: close };
});
