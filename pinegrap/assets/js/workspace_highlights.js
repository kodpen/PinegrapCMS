/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a reader's highlights on a message: the mini toolbar that
 * shows on a selection in a message's text ("For everyone", "For me"), the
 * highlights drawn into the message's body in their maker's colour, and the
 * card that opens on one (who made it, for whom, the note; the note and
 * Remove for who may).
 *
 * A highlight is kept by its text and which occurrence of it in the
 * message's plain text it is (includes/workspace/highlights.php). This file
 * is the browser's twin of that plain text and of finding a quote in it:
 * fold(), plainFromHtml(), find(), occurrenceAt() and textMap() are pure and
 * read the server's fixtures under Node (tests/workspace_highlights_test.php).
 * The body's text is never changed: a highlight is a <mark> around pieces of
 * text already drawn, and one whose quote is not in the drawn text is not
 * drawn.
 *
 * assets/js/workspace.js calls attach() after messageNode() drew a body.
 * Every text comes from the #ws-config block.
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The longest quote, in characters (WS_HIGHLIGHT_QUOTE_MAX).
    var QUOTE_MAX = 1000;
    var NOTE_MAX = 255;

    // The elements whose edges separate words, as the server's twin reads
    // them (ws_highlight_plain()).
    var BLOCK = /^(BR|HR|DIV|P|LI|UL|OL|BLOCKQUOTE|PRE|TABLE|THEAD|TBODY|TR|TD|TH|H[1-6])$/;

    // What the screen adds to a drawn body after the server's text: never
    // part of the plain text.
    var SKIP = '.ws-msg-edited, .ws-hl-bar, .ws-hl-card';

    // The same set as the server's [\s\p{Z}\x{FEFF}].
    var SPACE = /[\s﻿]/;

    /**
     * Every run of white space folded to one space, the ends trimmed.
     */
    function fold(text) {
        return String(text == null ? '' : text).replace(/[\s﻿]+/g, ' ').replace(/^ | $/g, '');
    }

    function decode(text) {
        var named = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };

        return text.replace(/&(#[0-9]+|#x[0-9a-f]+|[a-z]+);/gi, function (whole, name) {
            if (name.charAt(0) === '#') {
                var code = (name.charAt(1).toLowerCase() === 'x') ? parseInt(name.slice(2), 16) : parseInt(name.slice(1), 10);

                return (code > 0 && code < 0x110000) ? String.fromCodePoint(code) : whole;
            }

            return Object.prototype.hasOwnProperty.call(named, name.toLowerCase()) ? named[name.toLowerCase()] : whole;
        });
    }

    /**
     * The plain text of a drawn body's HTML: ws_highlight_plain()'s twin.
     */
    function plainFromHtml(html) {
        var text = String(html == null ? '' : html)
            .replace(/<(?:br|hr)\b[^>]*>|<\/?(?:div|p|li|ul|ol|blockquote|pre|table|thead|tbody|tr|td|th|h[1-6])\b[^>]*>/gi, ' ')
            .replace(/<[^>]*>/g, '');

        return fold(decode(text));
    }

    /**
     * Where the given occurrence of a quote begins in a plain text, or
     * null; every place a quote begins is an occurrence, overlapping ones
     * too (ws_highlight_find()'s twin, in UTF-16 units).
     */
    function find(plain, quote, occurrence) {
        plain = String(plain == null ? '' : plain);
        quote = fold(quote);
        occurrence = parseInt(occurrence, 10);

        if (quote === '' || !(occurrence >= 0)) {
            return null;
        }

        var at = -1;

        for (var i = 0; i <= occurrence; i++) {
            at = plain.indexOf(quote, at + 1);

            if (at < 0) {
                return null;
            }
        }

        return at;
    }

    /**
     * The occurrence a quote beginning at this place is: how many times it
     * begins earlier.
     */
    function occurrenceAt(plain, quote, start) {
        var count = 0;
        var at = -1;

        while ((at = plain.indexOf(quote, at + 1)) >= 0 && at < start) {
            count++;
        }

        return count;
    }

    /**
     * The length of a text in characters, as the server counts them.
     */
    function chars(text) {
        return Array.from(String(text)).length;
    }

    function skipped(node) {
        return !!(node.matches && node.matches(SKIP));
    }

    /**
     * The plain text of a body as drawn, with, for every text node, the
     * place in the plain text of each of its units (-1 for white space
     * folded away). The plain text equals plainFromHtml() of the HTML the
     * body was drawn from.
     *
     * @return {{plain: string, segs: Array<{node: Node, idx: number[]}>}}
     */
    function textMap(root) {
        var plain = '';
        var pending = false;
        var segs = [];

        var space = function () {
            if (plain !== '') {
                pending = true;
            }
        };

        var walk = function (node) {
            if (node.nodeType === 3) {
                var text = String(node.nodeValue || '');
                var idx = [];

                for (var i = 0; i < text.length; i++) {
                    var ch = text.charAt(i);

                    if (SPACE.test(ch)) {
                        idx.push(-1);
                        space();
                        continue;
                    }

                    if (pending) {
                        plain += ' ';
                        pending = false;
                    }

                    idx.push(plain.length);
                    plain += ch;
                }

                segs.push({ node: node, idx: idx });

                return;
            }

            if (node.nodeType !== 1 || skipped(node)) {
                return;
            }

            var block = BLOCK.test(String(node.nodeName).toUpperCase());

            if (block) {
                space();
            }

            for (var c = 0; c < node.childNodes.length; c++) {
                walk(node.childNodes[c]);
            }

            if (block) {
                space();
            }
        };

        for (var k = 0; k < root.childNodes.length; k++) {
            walk(root.childNodes[k]);
        }

        return { plain: plain, segs: segs };
    }

    /**
     * The pieces of text nodes a range of the plain text covers: for each,
     * the node and the units [from, to) inside it.
     */
    function piecesOf(map, start, end) {
        var out = [];

        map.segs.forEach(function (seg) {
            var from = -1;
            var to = -1;

            for (var i = 0; i < seg.idx.length; i++) {
                var at = seg.idx[i];

                if (at >= start && at < end) {
                    if (from < 0) {
                        from = i;
                    }

                    to = i + 1;
                }
            }

            if (from >= 0) {
                out.push({ node: seg.node, from: from, to: to });
            }
        });

        return out;
    }

    // ── Drawing ────────────────────────────────────────────────────────

    var state = { h: null, bar: null, card: null, timer: 0, pointer: false, bound: false, tone: null };

    function tone(highlight) {
        return state.tone ? state.tone({ tone: highlight.tone }) : 'primary';
    }

    /**
     * The highlights of a message drawn into its body, oldest first, so
     * that overlaps nest the newer inside. One whose quote is not in the
     * drawn text is left out.
     */
    function draw(message, body) {
        var list = (message.highlights || []).slice().sort(function (a, b) { return a.id - b.id; });

        list.forEach(function (highlight) {
            var map = textMap(body);
            var start = find(map.plain, highlight.quote, highlight.occurrence);

            if (start === null) {
                return;
            }

            var first = true;

            piecesOf(map, start, start + fold(highlight.quote).length).forEach(function (piece) {
                var node = piece.node;

                if (piece.to < node.nodeValue.length) {
                    node.splitText(piece.to);
                }

                if (piece.from > 0) {
                    node = node.splitText(piece.from);
                }

                var mark = document.createElement('mark');
                mark.className = 'ws-hl' + (highlight.scope === 'me' ? ' ws-hl-me' : '');
                mark.setAttribute('data-ws-hl', String(highlight.id));
                mark.setAttribute('data-ws-tone', tone(highlight));

                // One stop per highlight for the keyboard, on its first piece.
                if (first) {
                    mark.tabIndex = 0;
                    first = false;
                }

                node.parentNode.insertBefore(mark, node);
                mark.appendChild(node);
            });
        });
    }

    // ── The card ───────────────────────────────────────────────────────

    function closeCard() {
        if (state.card && state.card.parentNode) {
            state.card.parentNode.removeChild(state.card);
        }

        state.card = null;
    }

    function place(box, rect, below) {
        var top = below ? rect.bottom + window.scrollY + 8 : rect.top + window.scrollY - box.offsetHeight - 8;
        var left = Math.max(8, Math.min(rect.left + window.scrollX, window.scrollX + document.documentElement.clientWidth - box.offsetWidth - 8));

        box.style.top = Math.max(window.scrollY + 8, top) + 'px';
        box.style.left = left + 'px';
    }

    // A button of the panel's look that runs a function on a press.
    function press(className, label, iconName, run) {
        var node = state.h.button(className, label, iconName);
        node.addEventListener('click', run);

        return node;
    }

    function send(action, data, body) {
        var h = state.h;

        return h.api(action, data).then(function (result) {
            if (result && result.message && body.wsHlRedraw) {
                body.wsHlRedraw(result.message);
            }

            return result;
        }).catch(function (error) {
            h.toast((error && error.message) || String(error), 'danger');
            throw error;
        });
    }

    /**
     * The card of the highlights under a mark: the mark's own and the ones
     * it sits in (overlaps are listed together).
     */
    function openCard(mark, body) {
        var h = state.h;
        var message = body.wsHlMessage;
        var ids = [];

        for (var node = mark; node && node !== body; node = node.parentNode) {
            if (node.nodeType === 1 && node.hasAttribute('data-ws-hl')) {
                ids.push(parseInt(node.getAttribute('data-ws-hl'), 10));
            }
        }

        var list = (message.highlights || []).filter(function (one) { return ids.indexOf(one.id) >= 0; });

        if (!list.length) {
            return;
        }

        closeCard();

        var card = h.el('div', 'ws-hl-card card shadow-sm');
        card.setAttribute('role', 'dialog');
        card.setAttribute('aria-label', h.t('hl_card'));

        list.forEach(function (one) {
            var row = h.el('div', 'ws-hl-card-row');
            var head = h.el('div', 'ws-hl-card-head');
            var swatch = h.el('span', 'ws-hl-swatch');
            swatch.setAttribute('data-ws-tone', tone(one));
            head.appendChild(swatch);
            head.appendChild(h.el('strong', '', one.name));
            head.appendChild(h.el('span', 'text-body-secondary small', one.scope === 'me' ? h.t('hl_only_you') : h.t('hl_for_everyone')));
            row.appendChild(head);

            if (one.note) {
                row.appendChild(h.el('div', 'ws-hl-note', one.note));
            }

            var tools = h.el('div', 'ws-hl-card-tools');

            if (one.mine) {
                tools.appendChild(press('btn btn-sm btn-link px-0', one.note ? h.t('hl_edit_note') : h.t('hl_add_note'), '', function () {
                    noteForm(row, tools, one, body);
                }));
            }

            if (one.can_remove) {
                tools.appendChild(press('btn btn-sm btn-link px-0 text-danger', h.t('hl_remove'), '', function () {
                    send('ws_highlight_remove', { id: one.id }, body).then(closeCard, function () {});
                }));
            }

            if (tools.childNodes.length) {
                row.appendChild(tools);
            }

            card.appendChild(row);
        });

        card.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeCard();
                mark.focus();
            }
        });

        document.body.appendChild(card);
        state.card = card;
        place(card, mark.getBoundingClientRect(), true);
    }

    function noteForm(row, tools, one, body) {
        var h = state.h;
        var form = h.el('form', 'ws-hl-note-form');
        var field = h.el('textarea', 'form-control form-control-sm');
        field.maxLength = NOTE_MAX;
        field.rows = 2;
        field.value = one.note || '';
        field.setAttribute('aria-label', h.t('hl_note'));
        form.appendChild(field);

        var buttons = h.el('div', 'ws-hl-card-tools');
        var save = h.el('button', 'btn btn-sm btn-primary', h.t('hl_save'));
        save.type = 'submit';
        buttons.appendChild(save);
        buttons.appendChild(press('btn btn-sm btn-link', h.t('hl_cancel'), '', function () {
            row.replaceChild(tools, form);
        }));
        form.appendChild(buttons);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            send('ws_highlight_note', { id: one.id, note: field.value }, body).then(closeCard, function () {});
        });

        row.replaceChild(form, tools);
        field.focus();
    }

    // ── The toolbar on a selection ─────────────────────────────────────

    function closeBar() {
        if (state.bar && state.bar.parentNode) {
            state.bar.parentNode.removeChild(state.bar);
        }

        state.bar = null;
    }

    function closest(node, selector) {
        var element = (node && node.nodeType === 3) ? node.parentNode : node;

        return (element && element.closest) ? element.closest(selector) : null;
    }

    /**
     * The selection as a highlight's place: the body it lies in, its quote
     * and occurrence; null when it is not inside one message's text.
     */
    function selected() {
        var selection = window.getSelection ? window.getSelection() : null;

        if (!selection || selection.isCollapsed || !selection.rangeCount) {
            return null;
        }

        var range = selection.getRangeAt(0);
        var body = closest(range.startContainer, '.ws-msg-body');

        if (!body || body !== closest(range.endContainer, '.ws-msg-body') || !body.wsHlMessage
            || closest(range.startContainer, SKIP) || closest(range.endContainer, SKIP)) {
            return null;
        }

        var map = textMap(body);
        var start = -1;
        var end = -1;

        map.segs.forEach(function (seg) {
            if (!range.intersectsNode(seg.node)) {
                return;
            }

            var lo = (seg.node === range.startContainer) ? range.startOffset : 0;
            var hi = (seg.node === range.endContainer) ? range.endOffset : seg.idx.length;

            for (var i = lo; i < hi; i++) {
                if (seg.idx[i] >= 0) {
                    start = (start < 0) ? seg.idx[i] : Math.min(start, seg.idx[i]);
                    end = Math.max(end, seg.idx[i] + 1);
                }
            }
        });

        if (start < 0) {
            return null;
        }

        var quote = map.plain.slice(start, end);

        return { body: body, range: range, quote: quote, occurrence: occurrenceAt(map.plain, quote, start) };
    }

    function showBar() {
        var h = state.h;
        var spot = selected();

        closeBar();

        if (!spot) {
            return;
        }

        var message = spot.body.wsHlMessage;
        var tooLong = chars(spot.quote) > QUOTE_MAX;
        var bar = h.el('div', 'ws-hl-bar btn-group shadow-sm');
        bar.setAttribute('role', 'toolbar');
        bar.setAttribute('aria-label', h.t('hl_toolbar'));

        var add = function (scope, label) {
            var button = press('btn btn-sm btn-light', label, 'bi-highlighter', function () {
                var selection = window.getSelection();

                closeBar();

                if (selection) {
                    selection.removeAllRanges();
                }

                send('ws_highlight_set', { message_id: message.id, quote: spot.quote, occurrence: spot.occurrence, scope: scope }, spot.body).catch(function () {});
            });

            button.disabled = tooLong;
            bar.appendChild(button);
        };

        // "For everyone" only where the reader may react (H4).
        if (message.can_react) {
            add('all', h.t('hl_for_everyone'));
        }

        add('me', h.t('hl_for_me'));

        if (tooLong) {
            bar.appendChild(h.el('span', 'ws-hl-bar-note small', h.t('hl_shorter')));
        }

        // A press keeps the selection: the bar never takes the focus.
        bar.addEventListener('mousedown', function (event) {
            event.preventDefault();
        });

        document.body.appendChild(bar);
        state.bar = bar;

        var rect = spot.range.getBoundingClientRect();
        var touch = !!(h.touchScreen && h.touchScreen());

        place(bar, rect, touch || rect.top < bar.offsetHeight + 16);
    }

    function later() {
        clearTimeout(state.timer);
        state.timer = setTimeout(function () {
            if (!state.pointer) {
                showBar();
            }
        }, 200);
    }

    function bind() {
        if (state.bound) {
            return;
        }

        state.bound = true;

        document.addEventListener('pointerdown', function (event) {
            state.pointer = true;

            if (state.card && !state.card.contains(event.target) && !closest(event.target, 'mark.ws-hl')) {
                closeCard();
            }
        }, true);

        document.addEventListener('pointerup', function (event) {
            state.pointer = false;

            if (state.bar && state.bar.contains(event.target)) {
                return;
            }

            clearTimeout(state.timer);
            setTimeout(showBar, 0);
        }, true);

        document.addEventListener('selectionchange', function () {
            var selection = window.getSelection();

            if (!selection || selection.isCollapsed) {
                closeBar();
                return;
            }

            later();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeBar();
                closeCard();
            }
        });

        // The list moving away from the selection closes the bar.
        document.addEventListener('scroll', function (event) {
            if (state.bar && event.target && event.target.nodeType === 1 && event.target.closest && event.target.closest('.ws-pane-messages, .ws-messages')) {
                closeBar();
            }
        }, true);
    }

    /**
     * A message's body after messageNode() drew it: its highlights drawn,
     * the card and the toolbar wired.
     *
     * @param {Object}   message the message's payload
     * @param {Element}  body    its .ws-msg-body
     * @param {Object}   h       featureHelpers()
     * @param {Function} redraw  draws a message again from a fresh payload
     */
    function attach(message, body, h, redraw, senderTone) {
        if (!message || message.deleted || !body) {
            return;
        }

        state.h = h;
        state.tone = senderTone || state.tone;
        body.wsHlMessage = message;
        body.wsHlRedraw = redraw;
        bind();

        if (message.highlights && message.highlights.length) {
            draw(message, body);
        }

        var open = function (event) {
            var mark = closest(event.target, 'mark.ws-hl');

            if (mark && body.contains(mark)) {
                openCard(mark, body);
            }
        };

        body.addEventListener('click', open);
        body.addEventListener('focusin', open);
        body.addEventListener('mouseover', function (event) {
            if (!state.card) {
                open(event);
            }
        });
    }

    var api = { fold: fold, plainFromHtml: plainFromHtml, find: find, occurrenceAt: occurrenceAt, textMap: textMap, piecesOf: piecesOf, attach: attach };

    if (typeof window !== 'undefined') {
        window.PGWsHighlights = api;
    }
})();
