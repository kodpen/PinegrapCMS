/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Pinegrap AI actions of the Prototype view's
 * Sketch tool, in a sketch page's "More" while Pinegrap AI is on.
 * "Describe the page" opens a small window; the words are sent to Pinegrap
 * AI (designer/sketch_ai, includes/fn/sketch_ai.php) and the boxes it draws
 * replace the page's in one undo step of the sketch: the reader gives them
 * their kinds as for boxes drawn by hand. "Write the texts" writes, one
 * section a request, the texts of the built page's sections that still hold
 * their sample content; a section edited in Design, the shared header and
 * footer and the widgets are left alone. The texts are one undo step of
 * the page's tab. Not needed to open a page: the core runs this file the
 * first time a row is chosen (_sdPartLoad(), pg_designer_parts()) and hands
 * it the editor through a host (_sdSketchAiHost()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('sketchai', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var sdToast = K.sdToast;

    var host = null;
    // The window while open; the drawing or the writing under way; the
    // line under each frame; the words last sent for each frame.
    var win = null;
    var draw = null;
    var write = null;
    var lines = {};
    var words = {};
    var tok = 0;

    // ── Pure ──

    // The server's boxes (a page 100 units wide) in frame units, on the
    // grid, inside the frame's width, at least `min`; at most `boxes`.
    function _saBoxes(list, lim) {
        var grid = lim.grid || 1;
        var k = lim.w / 100;
        var snap = function (v) { return Math.round(v * k / grid) * grid; };
        var out = [];
        (Array.isArray(list) ? list : []).forEach(function (b) {
            if (!b || out.length >= lim.boxes) return;
            var x = Math.min(lim.w, Math.max(0, snap(Number(b.x) || 0)));
            var y = Math.max(0, snap(Number(b.y) || 0));
            var w = Math.min(lim.w - x, snap(Number(b.w) || 0));
            var h = snap(Number(b.h) || 0);
            if (w < lim.min || h < lim.min) return;
            out.push({ x: x, y: y, w: w, h: h, text: String(b.text || '').slice(0, lim.text) });
        });
        return out;
    }

    // The sample texts of a section's tree in their order: headings,
    // paragraphs and links with words, outside shared components.
    function _saItems(node) {
        var out = [];
        var TYPES = { heading: true, paragraph: true, link: true };
        (function walk(n) {
            if (!n || n.type === 'shared_ref') return;
            var p = n.props || {};
            if (n.type === 'content' && TYPES[p.contentType]) {
                var text = String(p.text || '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, '\'').replace(/&amp;/g, '&').replace(/\s+/g, ' ').trim();
                if (text) out.push({ node: n, type: p.contentType, text: text });
            }
            (n.children || []).forEach(walk);
        })(node);
        return out;
    }

    // The built page's sections: sample (as built) or edited since.
    function _saSections(root, fp) {
        var secs = (root && root.props && root.props._sketch && root.props._sketch.secs) || {};
        var out = { sample: [], edited: 0 };
        (root.children || []).forEach(function (c) {
            var id = c.props && c.props._sketchId;
            if (id === undefined || id === null || id === '' || c.type === 'shared_ref' || !secs[id]) return;
            if (fp(c) === secs[id].fp) out.sample.push(String(id));
            else out.edited++;
        });
        return out;
    }

    function _saBold(text, name) {
        return esc(text).replace('\u0001', '<strong>' + esc(name) + '</strong>');
    }

    // ── Shared ──

    function _saButton(cls, text, run) {
        var b = host.el('button', 'btn btn-sm sd-sketch-act ' + cls, text);
        b.type = 'button';
        b.addEventListener('click', function (event) { event.stopPropagation(); run(); });
        return b;
    }

    function _saLine(frameId, tone, html, act, run) {
        lines[frameId] = { tone: tone, html: html, act: act, run: run };
        host.redraw();
    }

    // Pinegrap AI was switched off: the actions go, nothing more is sent.
    function _saOff() {
        host.off();
        _saClose();
        draw = null;
        write = null;
        sdToast(esc(_sdT('Pinegrap AI is off.')), 'warning', 5000);
        host.redraw();
    }

    // ── Describe the page ──

    function _saClose() {
        if (!win) return;
        var was = win;
        win = null;
        if (was.back.parentNode) was.back.parentNode.removeChild(was.back);
    }

    function describe(h, frameId) {
        host = h;
        var page = host.page(frameId);
        if (!page || draw) return;
        _saClose();
        var back = host.el('div', 'sd-skd-back');
        var box = host.el('div', 'sd-skd sd-sai');
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-labelledby', 'sd-sai-title');
        var head = host.el('div', 'sd-skd-head');
        var title = host.el('div', 'sd-skd-title', _sdT('Describe the page'));
        title.id = 'sd-sai-title';
        head.appendChild(title);
        box.appendChild(head);
        var body = host.el('div', 'sd-skd-body');
        var field = host.el('textarea', 'form-control sd-sai-field');
        field.rows = 5;
        field.maxLength = 2000;
        field.placeholder = _sdT('What is the page for, and what should be on it?');
        field.setAttribute('aria-label', _sdT('Describe the page'));
        field.value = words[frameId] || '';
        body.appendChild(field);
        body.appendChild(host.el('p', 'sd-sai-note', _sdT('Your words are sent to Pinegrap AI.')));
        if (page.boxes.length) {
            body.appendChild(host.el('p', 'sd-sai-warn', page.boxes.length === 1 ? _sdT('The 1 box on this page is replaced. Ctrl+Z brings it back.') : _sdT('The {var:1} boxes on this page are replaced. Ctrl+Z brings them back.', page.boxes.length)));
        }
        box.appendChild(body);
        var foot = host.el('div', 'sd-skd-foot');
        var go = _saButton('btn-solid theme-primary', _sdT('Draw the sketch'), function () {
            var text = field.value.trim();
            if (!text) return;
            _saClose();
            _saDraw(frameId, text);
        });
        var sync = function () { go.disabled = !field.value.trim(); };
        field.addEventListener('input', sync);
        sync();
        foot.appendChild(_saButton('btn-outline theme-secondary', _sdT('Cancel'), _saClose));
        foot.appendChild(go);
        box.appendChild(foot);
        back.appendChild(box);
        back.addEventListener('keydown', function (event) {
            event.stopPropagation();
            if (event.key === 'Escape') { event.preventDefault(); _saClose(); return; }
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) { event.preventDefault(); go.click(); return; }
            if (event.key === 'Tab') {
                var all = Array.prototype.filter.call(box.querySelectorAll('textarea, button'), function (el) { return !el.disabled; });
                var i = all.indexOf(document.activeElement);
                if (event.shiftKey && i <= 0) { event.preventDefault(); all[all.length - 1].focus(); }
                else if (!event.shiftKey && i === all.length - 1) { event.preventDefault(); all[0].focus(); }
            }
        });
        win = { frame: frameId, back: back };
        document.body.appendChild(back);
        field.focus();
    }

    function _saDraw(frameId, text) {
        words[frameId] = text;
        delete lines[frameId];
        var mine = { frame: frameId, tok: ++tok };
        draw = mine;
        host.redraw();
        host.announce(_sdT('Drawing the sketch…'));
        var again = function () { _saDraw(frameId, text); };
        var fail = function () {
            if (draw !== mine) return;
            draw = null;
            _saLine(frameId, 'rose', esc(_sdT('The sketch could not be drawn. Nothing was changed.')), _sdT('Try again'), again);
            host.announce(_sdT('The sketch could not be drawn. Nothing was changed.'));
        };
        host.post({ mode: 'describe', words: text }).then(function (res) {
            if (draw !== mine) return;
            if (res && res.off) { _saOff(); return; }
            var page = host.page(frameId);
            var boxes = (res && res.status === 'success' && page) ? _saBoxes(res.boxes, host.limits()) : [];
            if (!boxes.length) { fail(); return; }
            draw = null;
            host.keep(page, boxes);
            lines[frameId] = { tone: 'teal', html: esc(boxes.length === 1 ? _sdT('Sketch drawn from your words: 1 box.') : _sdT('Sketch drawn from your words: {var:1} boxes.', boxes.length)), act: _sdT('Undo'), run: function () { delete lines[frameId]; host.undoSketch(); }, sig: JSON.stringify(page.boxes) };
            host.redraw();
        }, fail);
    }

    // ── Write the texts ──

    function texts(h, frameId) {
        host = h;
        var page = host.page(frameId);
        var p = (page && page.built) ? host.pageOf(page.built.uid) : null;
        if (!p || write) return;
        var name = host.name(page);
        var root = host.treeOf(p);
        var found = _saSections(root, host.fp);
        if (!found.sample.length) {
            _saLine(frameId, 'blue', _saBold(_sdT('Every section of {var:1} was edited. No text was changed.', '\u0001'), name));
            return;
        }
        var d = page.details || {};
        var mine = { frame: frameId, p: p, done: 0, total: found.sample.length, written: 0, left: found.edited, stepped: false };
        write = mine;
        delete lines[frameId];
        host.redraw();
        host.announce(_sdT('Writing the texts…'));
        var finish = function (failed) {
            if (write !== mine) return;
            write = null;
            var undo = mine.stepped ? function () { delete lines[frameId]; host.undoPage(p); host.redraw(); } : null;
            if (failed) {
                _saLine(frameId, 'rose', esc(_sdT('The texts could not be written. {var:1} sections were written, the rest keep their sample content.', mine.written)), _sdT('Try again'), function () { texts(host, frameId); });
                return;
            }
            var html = _saBold(mine.written === 1 ? _sdT('Texts written in 1 section of {var:1}.', '\u0001') : _sdT('Texts written in {var:1} sections of {var:2}.', [mine.written, '\u0001']), name);
            if (mine.left) html += ' ' + esc(mine.left === 1 ? _sdT('1 edited section was left as it is.') : _sdT('{var:1} edited sections were left as they are.', mine.left));
            _saLine(frameId, 'teal', html, undo ? _sdT('Undo') : '', undo);
            host.announce(_sdT('The texts are written.'));
        };
        // The section's node as it is now, while it still holds its sample.
        var current = function (id) {
            var t = host.treeOf(p);
            var secs = (t && t.props && t.props._sketch && t.props._sketch.secs) || {};
            var c = (t.children || []).filter(function (n) { return n.props && String(n.props._sketchId) === id; })[0];
            return (c && secs[id] && host.fp(c) === secs[id].fp) ? c : null;
        };
        var next = function (i) {
            if (write !== mine) return;
            if (i >= found.sample.length) { finish(false); return; }
            var id = found.sample[i];
            var c = current(id);
            var items = c ? _saItems(c) : [];
            if (!items.length) { if (!c) mine.left++; mine.done++; host.redraw(); next(i + 1); return; }
            host.post({
                mode: 'texts', heading: (c.props && c.props.customName) || '',
                items: items.map(function (it) { return { type: it.type, text: it.text }; }),
                page: { name: name, title: d.title || '', description: d.description || '' }
            }).then(function (res) {
                if (write !== mine) return;
                if (res && res.off) { _saOff(); return; }
                if (!res || res.status !== 'success' || !Array.isArray(res.texts) || res.texts.length !== items.length) { finish(true); return; }
                // Edited while the model wrote: left as it is.
                var now = current(id);
                if (now !== c) { mine.left++; }
                else {
                    if (!mine.stepped) { host.step(p); mine.stepped = true; }
                    items.forEach(function (it, k) { it.node.props.text = esc(String(res.texts[k])); });
                    mine.written++;
                    host.drawPage(p);
                }
                mine.done++;
                host.redraw();
                next(i + 1);
            }, function () { finish(true); });
        };
        next(0);
    }

    // ── On the frame ──

    function fill(frameId, node, head, body) {
        if (!host) return;
        var more = head.querySelector('.sd-sketch-more');
        var busyText = (draw && draw.frame === frameId) ? _sdT('Drawing the sketch…')
            : (write && write.frame === frameId) ? _sdT('Writing the texts… {var:1} of {var:2}', [Math.min(write.done + 1, write.total), write.total]) : '';
        if (busyText) {
            var busy = host.el('span', 'sd-sketch-read-busy');
            busy.setAttribute('role', 'status');
            busy.appendChild(host.el('span', 'bi bi-arrow-repeat sd-sketch-spin'));
            busy.lastChild.setAttribute('aria-hidden', 'true');
            busy.appendChild(host.el('span', '', busyText));
            var draft = head.querySelector('.sd-skb-draft');
            if (draft) draft.parentNode.removeChild(draft);
            head.insertBefore(busy, more);
            if (draw && draw.frame === frameId) {
                head.insertBefore(_saButton('btn-outline theme-secondary', _sdT('Stop'), stop), more);
                body.classList.add('sd-sai-dim');
            }
        }
        var line = lines[frameId];
        var page = host.page(frameId);
        if (line && line.sig && page && JSON.stringify(page.boxes) !== line.sig) { delete lines[frameId]; line = null; }
        if (!line || busyText) return;
        Array.prototype.forEach.call(node.querySelectorAll('.sd-sketch-foot'), function (el) { el.parentNode.removeChild(el); });
        var foot = host.el('div', 'sd-sketch-foot sd-skb-result');
        foot.setAttribute('role', 'status');
        var tile = host.el('span', 'sd-skb-tile sd-skb-' + line.tone);
        tile.innerHTML = '<span class="bi ' + ({ teal: 'bi-check-lg', blue: 'bi-info-lg', rose: 'bi-x-lg' }[line.tone]) + '" aria-hidden="true"></span>';
        foot.appendChild(tile);
        var text = host.el('span', 'sd-sketch-foot-text');
        text.innerHTML = line.html;
        foot.appendChild(text);
        if (line.act && line.run) foot.appendChild(_saButton('btn-outline theme-secondary', line.act, line.run));
        node.appendChild(foot);
    }

    // "Stop": the drawing ends with the page as it was; the writing keeps
    // what is written so far.
    function stop() {
        if (draw) {
            var frameId = draw.frame;
            draw = null;
            delete lines[frameId];
            host.redraw();
            host.announce(_sdT('Stopped. The page is as it was.'));
            return;
        }
        if (write) {
            // An answer still on its way is dropped (write !== mine).
            var mine = write;
            write = null;
            var undo = mine.stepped ? function () { delete lines[mine.frame]; host.undoPage(mine.p); host.redraw(); } : null;
            _saLine(mine.frame, 'teal', esc(_sdT('Stopped. {var:1} sections were written.', mine.written)), undo ? _sdT('Undo') : '', undo);
        }
    }

    function escape() {
        if (win) { _saClose(); return true; }
        if (draw || write) { stop(); return true; }
        return false;
    }

    return {
        describe: describe,
        texts: texts,
        fill: fill,
        escape: escape,
        stop: stop,
        writing: function (frameId) { return !!(write && write.frame === frameId); },
        pure: { boxes: _saBoxes, items: _saItems, sections: _saSections }
    };
});
