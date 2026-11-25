/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - "Read the picture" part: the reference picture under
 * a sketch page read by the browser's built-in model
 * (PinegrapAiWrite.readImage(), assets/js/pg_ai_write.js) into boxes. The
 * picture is read in the browser and sent nowhere. A person's first use
 * shows one line saying so; busy, the frame's head carries a spinner and
 * "Stop"; the boxes found are proposed in the accent over the picture, and
 * a foot under the frame counts them with "Discard" and "Keep". "Keep"
 * makes them the page's boxes in one undo step. Not needed to open a page:
 * the core runs this file the first time "Read the picture" is chosen in
 * "More" (_sdPartLoad(), pg_designer_parts()) and hands it the sketch
 * through a host (_sdSketchReadHost()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('sketchread', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var sdToast = K.sdToast;

    // The first-use line was seen in this browser.
    var SEEN_KEY = 'pg_sketch_read_seen';
    // At most this many boxes are asked for and proposed.
    var MAX_BOXES = 40;

    // What the model is asked: the page's parts as boxes, in hundredths of
    // the picture's width and height.
    var PROMPT = 'The image is a screenshot or a drawing of one web page. '
        + 'Divide it into the parts a designer would draw as boxes on a wireframe: '
        + 'from top to bottom the header, the intro, each band of cards, features, text, pictures, forms and calls to action, and the footer. '
        + 'Inside a band, give one box for each card, picture, field or button that you can see. '
        + 'Give each box as x and y of its top left corner, w its width and h its height, '
        + 'all in percent of the image width (x, w) and the image height (y, h), from 0 to 100. '
        + 'text is the heading written in the box, at most six words, or an empty string. '
        + 'Only describe what the image shows. Give at most ' + MAX_BOXES + ' boxes.';

    var SCHEMA = {
        type: 'object',
        properties: {
            boxes: {
                type: 'array',
                maxItems: MAX_BOXES,
                items: {
                    type: 'object',
                    properties: {
                        x: { type: 'number' }, y: { type: 'number' },
                        w: { type: 'number' }, h: { type: 'number' },
                        text: { type: 'string' }
                    },
                    required: ['x', 'y', 'w', 'h']
                }
            }
        },
        required: ['boxes']
    };

    // One reading at a time: { frame, src, step: 'ask' | 'busy' | 'done' |
    // 'fail', boxes, stop }; null when none.
    var state = null;
    var host = null;

    // The model's answer as sketch boxes in frame units: the picture is
    // `width` wide and `picH` high there. A box with a bad number, smaller
    // than `min` or lying outside the picture is dropped; the rest are kept
    // inside the frame's width, lined up on `grid`, a heading of at most
    // `text` characters. At most `max` boxes, the same rectangle once.
    function _srBoxes(answer, width, picH, lim) {
        var list = (answer && Array.isArray(answer.boxes)) ? answer.boxes : [];
        var grid = lim.grid || 1;
        var snap = function (v) { return Math.round(v / grid) * grid; };
        var out = [];
        var seen = {};
        list.forEach(function (b) {
            if (!b || out.length >= lim.max) return;
            var x = Number(b.x), y = Number(b.y), w = Number(b.w), h = Number(b.h);
            if (!isFinite(x) || !isFinite(y) || !isFinite(w) || !isFinite(h) || w <= 0 || h <= 0) return;
            if (x >= 100 || y >= 100 || x + w <= 0 || y + h <= 0) return;
            var left = Math.max(0, x), top = Math.max(0, y);
            var right = Math.min(100, x + w), bottom = Math.min(100, y + h);
            var bx = snap(left / 100 * width), by = snap(top / 100 * picH);
            var bw = Math.min(width - bx, snap((right - left) / 100 * width));
            var bh = snap((bottom - top) / 100 * picH);
            if (bw < lim.min || bh < lim.min) return;
            var key = bx + ',' + by + ',' + bw + ',' + bh;
            if (seen[key]) return;
            seen[key] = true;
            out.push({ x: bx, y: by, w: bw, h: bh, text: String(b.text == null ? '' : b.text).replace(/\s+/g, ' ').trim().slice(0, lim.text) });
        });
        return out;
    }

    // The proposal's line: how many boxes, and what "Keep" replaces.
    function _srCountLine(found, onPage) {
        if (!onPage) return found === 1 ? _sdT('1 box proposed from the picture') : _sdT('{var:1} boxes proposed from the picture', found);
        if (found === 1) return onPage === 1 ? _sdT('1 box proposed. “Keep” replaces the 1 on the page; one Undo brings it back.') : _sdT('1 box proposed. “Keep” replaces the {var:1} on the page; one Undo brings them back.', onPage);
        return onPage === 1 ? _sdT('{var:1} boxes proposed. “Keep” replaces the 1 on the page; one Undo brings it back.', found) : _sdT('{var:1} boxes proposed. “Keep” replaces the {var:2} on the page; one Undo brings them back.', [found, onPage]);
    }

    function _srSeen() {
        try { return window.localStorage.getItem(SEEN_KEY) === '1'; } catch (e) { return false; }
    }

    function _srSetSeen() {
        try { window.localStorage.setItem(SEEN_KEY, '1'); } catch (e) { /* asked again next visit */ }
    }

    function _srStop() {
        if (state && state.stop) {
            try { state.stop.abort(); } catch (e) { /* already stopped */ }
        }
    }

    function _srEnd(say) {
        _srStop();
        state = null;
        if (host) host.redraw();
        if (say && host) host.announce(say);
    }

    // The page being read, while it still has the picture being read.
    function _srPage() {
        var page = (state && host) ? host.page(state.frame) : null;
        return (page && page.pic && page.pic.src === state.src) ? page : null;
    }

    // Reads the picture: the model is made in this call, which a click
    // made, as a download needs the person's gesture.
    function _srRead() {
        var page = _srPage();
        var ai = window.PinegrapAiWrite;
        if (!page || !ai || typeof ai.readImage !== 'function') { _srEnd(); return; }
        _srSetSeen();
        var stop = (typeof window.AbortController === 'function') ? new window.AbortController() : null;
        var mine = { frame: state.frame, src: state.src, step: 'busy', boxes: [], stop: stop };
        state = mine;
        host.redraw();
        host.announce(_sdT('Reading the picture…'));
        var lim = host.limits();
        ai.readImage({ image: page.pic.src, text: PROMPT, schema: SCHEMA, signal: stop ? stop.signal : null }).then(function (answer) {
            if (state !== mine) return;
            var now = _srPage();
            if (!now) { _srEnd(); return; }
            var boxes = _srBoxes(answer, lim.w, now.pic.h, { grid: lim.grid, min: lim.min, text: lim.text, max: Math.min(MAX_BOXES, lim.boxes) });
            if (!boxes.length) {
                _srEnd();
                sdToast(esc(_sdT('No boxes could be read from this picture.')), 'warning', 5000);
                return;
            }
            mine.step = 'done';
            mine.boxes = boxes;
            mine.stop = null;
            host.redraw();
            host.announce(_srCountLine(boxes.length, now.boxes.length));
        }, function () {
            if (state !== mine) return;
            mine.step = 'fail';
            mine.stop = null;
            host.redraw();
            host.announce(_sdT('The picture could not be read. Nothing was changed.'));
        });
    }

    function _srKeep() {
        var page = _srPage();
        if (!page || state.step !== 'done') { _srEnd(); return; }
        var boxes = state.boxes;
        state = null;
        host.keep(page, boxes);
    }

    function _srButton(cls, text, run) {
        var b = host.el('button', 'btn btn-sm sd-sketch-act ' + cls, text);
        b.type = 'button';
        b.addEventListener('click', function (event) {
            event.stopPropagation();
            run();
        });
        return b;
    }

    // "Read the picture" in "More": the first use asks under the frame,
    // later uses start at once.
    function start(h, frameId) {
        host = h;
        var page = host.page(frameId);
        if (!page || !page.pic) return;
        _srStop();
        state = { frame: frameId, src: page.pic.src, step: 'ask', boxes: [], stop: null };
        if (_srSeen()) { _srRead(); return; }
        host.redraw();
        var go = host.root().querySelector('.sd-sketch-read-go');
        if (go) go.focus({ preventScroll: true });
    }

    // The reading's marks on its frame as it is drawn again: the busy head,
    // the proposal over the picture and the foot under the frame.
    function fill(frameId, node, head, body) {
        if (!state || state.frame !== frameId || !host) return;
        if (!_srPage()) { _srStop(); state = null; return; }
        if (state.step === 'busy') {
            var busy = host.el('span', 'sd-sketch-read-busy');
            busy.setAttribute('role', 'status');
            busy.appendChild(host.el('span', 'bi bi-arrow-repeat sd-sketch-spin'));
            busy.lastChild.setAttribute('aria-hidden', 'true');
            busy.appendChild(host.el('span', '', _sdT('Reading the picture…')));
            // Before "More", which stays last in the head.
            var more = head.querySelector('.sd-sketch-more');
            head.insertBefore(busy, more);
            head.insertBefore(_srButton('btn-outline theme-secondary sd-sketch-read-stop', _sdT('Stop'), function () { _srEnd(); }), more);
            return;
        }
        var foot = host.el('div', 'sd-sketch-foot');
        foot.setAttribute('role', 'group');
        if (state.step === 'ask') {
            foot.appendChild(host.el('span', 'sd-sketch-foot-text', _sdT('“Read the picture” reads this picture with your browser\'s built-in AI. It is sent nowhere.')));
            foot.appendChild(_srButton('btn-outline theme-secondary', _sdT('Cancel'), function () { _srEnd(); }));
            foot.appendChild(_srButton('btn-solid theme-primary sd-sketch-read-go', _sdT('Read the picture'), _srRead));
        } else if (state.step === 'fail') {
            foot.appendChild(host.el('span', 'sd-sketch-foot-text', _sdT('The picture could not be read. Nothing was changed.')));
            foot.appendChild(_srButton('btn-outline theme-secondary', _sdT('Try again'), _srRead));
        } else {
            state.boxes.forEach(function (b) {
                var el = host.el('div', 'sd-sketch-proposed');
                el.style.left = b.x + 'px';
                el.style.top = b.y + 'px';
                el.style.width = b.w + 'px';
                el.style.height = b.h + 'px';
                if (b.text) el.appendChild(host.el('span', 'sd-sketch-heading', b.text));
                body.appendChild(el);
            });
            foot.appendChild(host.el('span', 'sd-sketch-foot-text', _srCountLine(state.boxes.length, host.page(frameId).boxes.length)));
            foot.appendChild(_srButton('btn-outline theme-secondary', _sdT('Discard'), function () { _srEnd(_sdT('Proposal discarded')); }));
            foot.appendChild(_srButton('btn-solid theme-primary', _sdT('Keep'), _srKeep));
        }
        node.appendChild(foot);
    }

    // Escape stops, closes or discards what is under way; false when
    // nothing is.
    function escape() {
        if (!state) return false;
        _srEnd(state.step === 'done' ? _sdT('Proposal discarded') : '');
        return true;
    }

    return { start: start, fill: fill, escape: escape };
});
