/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the writing box of a channel, and the windows that design a
 * table, a checklist or a block of code for it.
 *
 * The box stays a chat box: one field, Enter sends, Shift+Enter starts a new
 * line. What it knows about formatting shows only when asked for: select a
 * few words and a small bar offers bold, italic, strikethrough, code and a
 * link; double-click a link to change or remove it. People and records picked
 * with @ and # sit in the text as chips, and a table, a checklist or a block
 * of code sits in it as a card that opens its window again when clicked.
 *
 * Messages are still stored as the light markup the server draws
 * (includes/workspace/render.php): **bold**, _italic_, ~~struck~~, `code`,
 * [text](address), tables with pipes, "- [ ]" items, ``` fences, and tags as
 * <@user:12> / <#order:1045>. This file turns the box into that text and back.
 *
 * assets/js/workspace.js asks for the box through window.PGWsEditor; every
 * text comes from the #ws-config block.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var configNode = document.getElementById('ws-config');
    var CFG = {};

    try {
        CFG = JSON.parse((configNode && configNode.textContent) || '{}');
    } catch (error) {
        CFG = {};
    }

    var S = CFG.strings || {};
    var OBJECT = '￼';

    // ── Small helpers ──────────────────────────────────────────────────

    function t(key) {
        var text = Object.prototype.hasOwnProperty.call(S, key) ? String(S[key]) : key;
        var values = Array.prototype.slice.call(arguments, 1);

        values.forEach(function (value, index) {
            text = text.split('{' + (index + 1) + '}').join(String(value));
        });

        return text;
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if ((text !== undefined) && (text !== null) && (text !== '')) {
            node.textContent = String(text);
        }

        return node;
    }

    function icon(name, extra) {
        var node = el('i', 'bi ' + name + (extra ? ' ' + extra : ''));
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function button(className, label, iconName, title) {
        var node = el('button', className);
        node.type = 'button';

        if (iconName) {
            node.appendChild(icon(iconName, label ? 'me-1' : ''));
        }

        if (label) {
            node.appendChild(document.createTextNode(label));
        }

        if (title) {
            node.title = title;
            node.setAttribute('aria-label', title);
        }

        return node;
    }

    function touchScreen() {
        return !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
    }

    function command(name, value) {
        try {
            return document.execCommand(name, false, (value === undefined) ? null : value);
        } catch (error) {
            return false;
        }
    }

    // Web and mail addresses only; a bare domain gets https:// in front.
    function cleanUrl(value) {
        var url = String(value || '').trim();

        if (url === '') {
            return '';
        }

        if (/^mailto:[^\s]+@[^\s]+$/i.test(url)) {
            return url;
        }

        if (/^[^\s@\/]+@[^\s@\/]+\.[a-z]{2,}$/i.test(url)) {
            return 'mailto:' + url;
        }

        if (!/^https?:\/\//i.test(url)) {
            if (!/^[a-z0-9.-]+\.[a-z]{2,}([\/?#].*)?$/i.test(url)) {
                return '';
            }

            url = 'https://' + url;
        }

        return /^https?:\/\/[^\s<>"]+$/i.test(url) ? url : '';
    }

    // ── Markup → nodes ─────────────────────────────────────────────────

    var TOKEN = /<([@#])([a-z_]+):([0-9]{1,10})>/g;

    function chipNode(token, shown, iconName) {
        var sigil = token.charAt(1);
        var type = token.slice(2, token.indexOf(':'));
        var chip = el('span', 'ws-chip ws-rich-chip ws-chip-' + type);

        chip.contentEditable = 'false';
        chip.setAttribute('data-token', token);

        if (iconName) {
            chip.appendChild(icon(iconName));
        }

        chip.appendChild(document.createTextNode((String(shown || '').charAt(0) === sigil) ? shown : sigil + (shown || token)));

        return chip;
    }

    // One line of text as nodes: tags, code, links, emphasis.
    function inlineNodes(text, labels, parent) {
        var pattern = /(<[@#][a-z_]+:[0-9]{1,10}>)|`([^`\n]{1,300})`|\[([^\]\n]{1,300})\]\(((?:https?:\/\/|mailto:)[^\s()<>"]{1,1000})\)|\*\*([^*\n]{1,300})\*\*|~~([^~\n]{1,300})~~|(^|[^\p{L}\p{N}_])_([^_\n]{1,300}?)_(?![\p{L}\p{N}_])/u;
        var rest = String(text || '');
        var guard = 0;

        while (rest !== '' && guard++ < 2000) {
            var match = pattern.exec(rest);

            if (!match) {
                parent.appendChild(document.createTextNode(rest));
                break;
            }

            var lead = (match[7] !== undefined) ? match[7] : '';
            var before = rest.slice(0, match.index) + lead;

            if (before !== '') {
                parent.appendChild(document.createTextNode(before));
            }

            if (match[1]) {
                parent.appendChild(chipNode(match[1], (labels || {})[match[1]] || match[1], ''));
            } else if (match[2] !== undefined) {
                parent.appendChild(el('code', '', match[2]));
            } else if (match[3] !== undefined) {
                var link = el('a');
                link.href = match[4];
                inlineNodes(match[3], labels, link);
                parent.appendChild(link);
            } else if (match[5] !== undefined) {
                var strong = el('b');
                inlineNodes(match[5], labels, strong);
                parent.appendChild(strong);
            } else if (match[6] !== undefined) {
                var struck = el('s');
                inlineNodes(match[6], labels, struck);
                parent.appendChild(struck);
            } else {
                var italic = el('i');
                inlineNodes(match[8], labels, italic);
                parent.appendChild(italic);
            }

            rest = rest.slice(match.index + match[0].length);
        }

        return parent;
    }

    function fenceOpen(line) {
        var match = /^\s*```\s*([A-Za-z0-9_+#.-]{0,20})\s*$/.exec(line);
        return match ? match[1] : null;
    }

    function fenceClose(line) {
        return /^\s*```\s*$/.test(line);
    }

    function tableRow(line) {
        return /^\s*\|.*\|\s*$/.test(line);
    }

    function tableAlignments(line) {
        if (!/^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?\s*$/.test(line) || line.indexOf('-') === -1) {
            return null;
        }

        return tableCells(line).map(function (cell) {
            var left = cell.charAt(0) === ':';
            var right = cell.charAt(cell.length - 1) === ':';

            return (left && right) ? 'center' : (right ? 'right' : (left ? 'left' : ''));
        });
    }

    function tableCells(line) {
        var inner = String(line).trim().replace(/^\|/, '').replace(/(^|[^\\])\|$/, '$1');
        var cells = [];
        var current = '';

        for (var i = 0; i < inner.length; i++) {
            if (inner.charAt(i) === '\\' && inner.charAt(i + 1) === '|') {
                current += '|';
                i++;
            } else if (inner.charAt(i) === '|') {
                cells.push(current.trim());
                current = '';
            } else {
                current += inner.charAt(i);
            }
        }

        cells.push(current.trim());

        return cells;
    }

    var CHECK = /^\s*[-*]\s\[( |x|X)\]\s+(.+)$/;

    // A text split into lines of words and the blocks among them.
    function parseBlocks(markup) {
        var lines = String(markup || '').replace(/\r\n?/g, '\n').split('\n');
        var out = [];

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            var language = fenceOpen(line);

            if (language !== null) {
                var code = [];
                var j = i + 1;

                while (j < lines.length && !fenceClose(lines[j])) {
                    code.push(lines[j]);
                    j++;
                }

                out.push({ kind: 'code', markup: '```' + language + '\n' + code.join('\n') + '\n```' });
                i = j;
                continue;
            }

            if (tableRow(line) && (i + 1) < lines.length && tableAlignments(lines[i + 1])) {
                var rows = [line, lines[i + 1]];
                var k = i + 2;

                while (k < lines.length && tableRow(lines[k])) {
                    rows.push(lines[k]);
                    k++;
                }

                out.push({ kind: 'table', markup: rows.join('\n') });
                i = k - 1;
                continue;
            }

            if (CHECK.test(line)) {
                var items = [];

                while (i < lines.length && CHECK.test(lines[i])) {
                    items.push(lines[i]);
                    i++;
                }

                i--;
                out.push({ kind: 'checklist', markup: items.join('\n') });
                continue;
            }

            out.push({ kind: 'text', markup: line });
        }

        return out;
    }

    // ── Nodes → markup ─────────────────────────────────────────────────

    var BLOCKS = /^(DIV|P|LI|UL|OL|H[1-6]|BLOCKQUOTE|PRE|TABLE|TR)$/;

    // The words of an element as markup. Emphasis wraps each line on its
    // own, since the markup does not carry it across a line break.
    function wrapLines(text, mark) {
        return text.split('\n').map(function (line) {
            var lead = /^\s*/.exec(line)[0];
            var trail = /\s*$/.exec(line.slice(lead.length))[0];
            var core = line.slice(lead.length, line.length - trail.length);

            return (core === '') ? line : lead + mark + core + mark + trail;
        }).join('\n');
    }

    function serializeChildren(node, singleLine) {
        var out = '';

        Array.prototype.forEach.call(node.childNodes, function (child) {
            out += serializeNode(child, out, singleLine);
        });

        return out;
    }

    function serializeNode(node, sofar, singleLine) {
        if (node.nodeType === 3) {
            return node.nodeValue.replace(/ /g, ' ').replace(/​/g, '');
        }

        if (node.nodeType !== 1) {
            return '';
        }

        // Shown beside the text, not part of it: the result of a line.
        if (node.hasAttribute('data-ws-skip')) {
            return '';
        }

        if (node.hasAttribute('data-token')) {
            return node.getAttribute('data-token');
        }

        if (node.classList.contains('ws-rich-block')) {
            var block = node.getAttribute('data-markup') || '';
            var lead = (sofar === '' || /\n$/.test(sofar)) ? '' : '\n';

            return singleLine ? '' : lead + block + '\n';
        }

        var tag = node.tagName;

        if (tag === 'BR') {
            // The line break after a card ends the card's own line, which the
            // card already did.
            var previous = node.previousSibling;

            while (previous && previous.nodeType === 3 && previous.nodeValue.replace(/[\u200b]/g, '') === '') {
                previous = previous.previousSibling;
            }

            if (!singleLine && previous && previous.nodeType === 1 && previous.classList.contains('ws-rich-block')) {
                return '';
            }

            return singleLine ? ' ' : '\n';
        }

        var inner = serializeChildren(node, singleLine);

        if (tag === 'B' || tag === 'STRONG') {
            return wrapLines(inner, '**');
        }

        if (tag === 'I' || tag === 'EM') {
            return wrapLines(inner, '_');
        }

        if (tag === 'S' || tag === 'STRIKE' || tag === 'DEL') {
            return wrapLines(inner, '~~');
        }

        // Spaces at either end stay outside the marks, as with emphasis.
        if (tag === 'CODE') {
            var code = (node.textContent || '').replace(/[\n\u00a0]/g, ' ').replace(/\u200b/g, '').replace(/`/g, '\'');
            var codeCore = code.trim();

            return (codeCore === '') ? code : /^\s*/.exec(code)[0] + '`' + codeCore + '`' + /\s*$/.exec(code)[0];
        }

        if (tag === 'A') {
            var url = cleanUrl(node.getAttribute('href'));
            var words = inner.replace(/\n/g, ' ').replace(/[\[\]]/g, '');
            var wordsCore = words.trim();

            if (!url || wordsCore === '') {
                return inner;
            }

            return /^\s*/.exec(words)[0] + '[' + wordsCore + '](' + url + ')' + /\s*$/.exec(words)[0];
        }

        if (!singleLine && BLOCKS.test(tag)) {
            var before = (sofar === '' || /\n$/.test(sofar)) ? '' : '\n';
            return before + inner + (/\n$/.test(inner) ? '' : '\n');
        }

        return inner;
    }

    function serialize(root, singleLine) {
        var text = serializeChildren(root, singleLine);

        return singleLine ? text.replace(/\s+/g, ' ').trim() : text.replace(/\n{3,}/g, '\n\n').replace(/^\n+|\s+$/g, '');
    }

    // The plain text of a piece of the box, a placeholder for each chip or
    // card, for the pickers to read what was just typed.
    function plainOf(node) {
        var out = '';

        Array.prototype.forEach.call(node.childNodes, function (child) {
            if (child.nodeType === 3) {
                out += child.nodeValue.replace(/ /g, ' ');
            } else if (child.nodeType === 1) {
                if (child.hasAttribute('data-ws-skip')) {
                    return;
                }

                if (child.hasAttribute('data-token') || child.classList.contains('ws-rich-block')) {
                    out += OBJECT;
                } else if (child.tagName === 'BR') {
                    out += '\n';
                } else {
                    if (BLOCKS.test(child.tagName) && out !== '' && !/\n$/.test(out)) {
                        out += '\n';
                    }

                    out += plainOf(child);
                }
            }
        });

        return out;
    }

    // ── Cards for tables, checklists and code ─────────────────────────

    function blockSummary(kind, markup) {
        if (kind === 'table') {
            var lines = markup.split('\n');
            var columns = tableCells(lines[0] || '').length;

            return { icon: 'bi-table', label: t('block_table', columns, Math.max(0, lines.length - 2)), preview: tableCells(lines[0] || '').join(' · ') };
        }

        if (kind === 'checklist') {
            var items = markup.split('\n').filter(function (line) { return CHECK.test(line); });
            var first = CHECK.exec(items[0] || '');

            return { icon: 'bi-ui-checks', label: t('block_checklist', items.length), preview: first ? first[2] : '' };
        }

        var body = markup.split('\n').slice(1, -1);
        var language = fenceOpen(markup.split('\n')[0]) || '';

        if (isCalcBlock(markup)) {
            var filled = body.filter(function (line) { return line.trim() !== ''; });

            return { icon: 'bi-calculator', label: t('block_calc', filled.length), preview: (filled[filled.length - 1] || '').trim() };
        }

        return { icon: 'bi-code-slash', label: t('block_code', body.length) + (language ? ' · ' + language : ''), preview: (body[0] || '').trim() };
    }

    // A ```hesap block: lines of "Name = expression", worked out.
    function isCalcBlock(markup) {
        var language = (fenceOpen(String(markup || '').split('\n')[0]) || '').toLowerCase();

        return ['hesap', 'calc', 'math'].indexOf(language) !== -1;
    }

    function blockNode(kind, markup) {
        var summary = blockSummary(kind, markup);
        var card = el('span', 'ws-rich-block ws-rich-block-' + kind);

        card.contentEditable = 'false';
        card.setAttribute('data-kind', kind);
        card.setAttribute('data-markup', markup);
        card.title = t('block_edit');
        card.appendChild(icon(summary.icon));

        var words = el('span', 'ws-rich-block-text');
        words.appendChild(el('b', '', summary.label));

        if (summary.preview) {
            words.appendChild(el('span', '', summary.preview));
        }

        card.appendChild(words);

        var remove = button('ws-rich-block-remove', '', 'bi-x-lg', t('block_remove'));
        remove.setAttribute('data-block-remove', '1');
        card.appendChild(remove);

        return card;
    }

    // A card drawn as what it holds, with the HTML the server made of it (a
    // note's writing box). Nothing in it can be used or focused: a click
    // anywhere on it opens its window, as on a card.
    function previewBlock(card, html) {
        if (String(html || '').trim() === '') {
            return;
        }

        var view = card.querySelector('.ws-rich-block-view');

        if (!view) {
            view = el('span', 'ws-rich-block-view ws-msg-body');
            card.insertBefore(view, card.querySelector('.ws-rich-block-remove'));
        }

        view.innerHTML = html;

        Array.prototype.forEach.call(view.querySelectorAll('a, input, button, select, textarea, [tabindex]'), function (node) {
            node.tabIndex = -1;

            if (/^(INPUT|BUTTON|SELECT|TEXTAREA)$/.test(node.tagName)) {
                node.disabled = true;
            }
        });

        card.classList.add('ws-rich-block-shown');
    }

    // ── The format bar and the link tip ────────────────────────────────

    var bar = null;
    var tip = null;
    var barTimer = null;

    function surfaceOf(node) {
        var element = node && (node.nodeType === 1 ? node : node.parentNode);

        return element && element.closest ? element.closest('.ws-rich-surface') : null;
    }

    function selectionRange() {
        var selection = window.getSelection();

        return (selection && selection.rangeCount) ? selection.getRangeAt(0) : null;
    }

    // Kept in the modal when a surface is in one: its focus trap would take
    // the focus back from a box outside it.
    function floatingHost(surface) {
        var modal = surface ? surface.closest('.modal') : null;

        return modal ? (modal.querySelector('.modal-content') || modal) : document.body;
    }

    function place(box, rect, below) {
        var width = box.offsetWidth;
        var height = box.offsetHeight;
        var left = rect.left + (rect.width / 2) - (width / 2);
        var top = below ? rect.bottom + 8 : rect.top - height - 8;

        // The other side when there is no room: the writing box sits at the
        // foot of the window, the cells of a table near its head.
        if (below && (top + height > window.innerHeight - 8) && (rect.top - height - 8 >= 8)) {
            top = rect.top - height - 8;
        } else if (!below && (top < 8)) {
            top = rect.bottom + 8;
        }

        box.style.left = Math.max(8, Math.min(left, window.innerWidth - width - 8)) + 'px';
        box.style.top = Math.max(8, Math.min(top, window.innerHeight - height - 8)) + 'px';
    }

    function hideBar() {
        if (bar) {
            bar.remove();
            bar = null;
        }
    }

    function hideTip() {
        if (tip) {
            tip.remove();
            tip = null;
        }
    }

    function insideCode(range) {
        var node = range.commonAncestorContainer;
        var element = node.nodeType === 1 ? node : node.parentNode;

        return element && element.closest ? element.closest('code') : null;
    }

    function toggleCode(surface) {
        var range = selectionRange();

        if (!range) {
            return;
        }

        var code = insideCode(range);

        if (code && surface.contains(code)) {
            code.replaceWith(document.createTextNode(code.textContent));
            return;
        }

        var text = range.toString().replace(/\n/g, ' ');
        var core = text.trim();

        if (core === '') {
            return;
        }

        // A double-click takes the space after the word too; it stays outside.
        var holder = el('span');
        var fresh = el('code', '', core);

        fresh.setAttribute('data-ws-fresh', '1');
        holder.appendChild(document.createTextNode(/^\s*/.exec(text)[0]));
        holder.appendChild(fresh);
        holder.appendChild(document.createTextNode(/\s*$/.exec(text)[0]));
        command('insertHTML', holder.innerHTML);

        // Still selected, so the bar can take it off again.
        var added = surface.querySelector('code[data-ws-fresh]');

        if (added) {
            added.removeAttribute('data-ws-fresh');

            var again = document.createRange();
            again.selectNodeContents(added);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(again);
        }
    }

    function showBar() {
        var range = selectionRange();
        var surface = range ? surfaceOf(range.commonAncestorContainer) : null;

        if (!range || range.collapsed || !surface || tip) {
            hideBar();
            return;
        }

        var rect = range.getBoundingClientRect();

        if (!rect || (rect.width === 0 && rect.height === 0)) {
            hideBar();
            return;
        }

        hideBar();
        bar = el('div', 'ws-format-bar shadow');
        bar.setAttribute('role', 'toolbar');

        [
            ['bold', 'bi-type-bold', t('fmt_bold')],
            ['italic', 'bi-type-italic', t('fmt_italic')],
            ['strikeThrough', 'bi-type-strikethrough', t('fmt_strike')],
            ['code', 'bi-code', t('fmt_code')],
            ['link', 'bi-link-45deg', t('fmt_link')]
        ].forEach(function (entry) {
            var tool = button('ws-format-tool', '', entry[1], entry[2]);
            var active = false;

            try {
                active = (entry[0] === 'code') ? !!insideCode(range) : ((entry[0] === 'link') ? false : document.queryCommandState(entry[0]));
            } catch (error) {
                active = false;
            }

            tool.classList.toggle('active', active);

            // mousedown, so the selection stays where it is.
            tool.addEventListener('mousedown', function (event) {
                event.preventDefault();

                if (entry[0] === 'code') {
                    toggleCode(surface);
                } else if (entry[0] === 'link') {
                    openLink(surface, null);
                    return;
                } else {
                    command('styleWithCSS', false);
                    command(entry[0]);
                }

                surface.dispatchEvent(new Event('input', { bubbles: true }));
                window.setTimeout(showBar, 0);
            });

            bar.appendChild(tool);
        });

        floatingHost(surface).appendChild(bar);
        place(bar, rect, touchScreen());
    }

    function scheduleBar() {
        window.clearTimeout(barTimer);
        barTimer = window.setTimeout(showBar, 140);
    }

    // The link tip: for a new link over the selection, or for the link that
    // was double-clicked.
    function openLink(surface, anchor) {
        var range = selectionRange();
        var saved = range ? range.cloneRange() : null;
        var rect = anchor ? anchor.getBoundingClientRect() : (range ? range.getBoundingClientRect() : surface.getBoundingClientRect());

        hideBar();
        hideTip();

        tip = el('div', 'ws-link-tip shadow');

        var form = el('form', 'd-flex align-items-center gap-1');
        var input = el('input', 'form-control form-control-sm');

        input.type = 'text';
        input.placeholder = t('link_placeholder');
        input.setAttribute('aria-label', t('fmt_link'));
        input.value = anchor ? (anchor.getAttribute('href') || '') : '';
        form.appendChild(input);

        var save = button('btn btn-sm btn-primary', '', 'bi-check2', t('link_save'));
        save.type = 'submit';
        form.appendChild(save);

        if (anchor) {
            var open = button('btn btn-sm btn-ghost', '', 'bi-box-arrow-up-right', t('link_open'));
            open.addEventListener('click', function () {
                var url = cleanUrl(anchor.getAttribute('href'));

                if (url) {
                    window.open(url, '_blank', 'noopener');
                }
            });
            form.appendChild(open);

            var unlink = button('btn btn-sm btn-ghost', '', 'bi-link', t('link_remove'));
            unlink.addEventListener('click', function () {
                anchor.replaceWith.apply(anchor, Array.prototype.slice.call(anchor.childNodes));
                hideTip();
                surface.dispatchEvent(new Event('input', { bubbles: true }));
                surface.focus();
            });
            form.appendChild(unlink);
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var url = cleanUrl(input.value);

            if (!url) {
                input.classList.add('is-invalid');
                input.title = t('link_invalid');
                input.focus();
                return;
            }

            if (anchor) {
                anchor.setAttribute('href', url);
            } else if (saved) {
                surface.focus();

                var selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(saved);

                if (saved.collapsed) {
                    var link = el('a', '', url.replace(/^mailto:/, ''));
                    link.href = url;
                    saved.insertNode(link);
                    saved.setStartAfter(link);
                    saved.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(saved);
                } else {
                    command('createLink', url);
                }
            }

            hideTip();
            surface.dispatchEvent(new Event('input', { bubbles: true }));
            surface.focus();
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                hideTip();
                surface.focus();
            }
        });

        tip.appendChild(form);
        floatingHost(surface).appendChild(tip);
        place(tip, rect, true);
        window.setTimeout(function () { input.focus(); input.select(); }, 0);
    }

    document.addEventListener('selectionchange', function () {
        if (!tip) {
            scheduleBar();
        }

        // Each writing box remembers its caret as it moves, for what a button
        // or a window inserts later: by the time the box hears its blur, a
        // window that took the focus has already moved the selection into it.
        var range = selectionRange();
        var element = range ? (range.startContainer.nodeType === 1 ? range.startContainer : range.startContainer.parentNode) : null;
        var box = (element && element.closest) ? element.closest('.ws-rich') : null;

        if (box && box.wsEditor) {
            box.wsEditor.savedRange = range.cloneRange();
        }
    });

    document.addEventListener('mousedown', function (event) {
        if (tip && !tip.contains(event.target)) {
            hideTip();
        }
    }, true);

    window.addEventListener('resize', function () {
        hideBar();
        hideTip();
    });

    document.addEventListener('scroll', function (event) {
        if (bar && !(event.target && event.target.closest && event.target.closest('.ws-format-bar'))) {
            hideBar();
        }
    }, true);

    // Keyboard shortcuts in any writing surface.
    function shortcut(surface, event) {
        if (!(event.ctrlKey || event.metaKey) || event.altKey) {
            return false;
        }

        var key = event.key.toLowerCase();

        if (key === 'b' || key === 'i') {
            command('styleWithCSS', false);
            command(key === 'b' ? 'bold' : 'italic');
        } else if (key === 'x' && event.shiftKey) {
            command('strikeThrough');
        } else if (key === 'e') {
            toggleCode(surface);
        } else if (key === 'k') {
            var range = selectionRange();
            var element = range ? (range.startContainer.nodeType === 1 ? range.startContainer : range.startContainer.parentNode) : null;
            var anchor = element && element.closest ? element.closest('a') : null;

            openLink(surface, (anchor && surface.contains(anchor)) ? anchor : null);
        } else {
            return false;
        }

        // Kept from the page's own shortcuts: Ctrl+K there opens the search.
        event.preventDefault();
        event.stopPropagation();
        surface.dispatchEvent(new Event('input', { bubbles: true }));

        return true;
    }

    // Plain text only: formatting pasted from elsewhere would not survive as
    // markup anyway. Files are left to the conversation's own paste handler.
    function pastePlain(event, singleLine) {
        var data = event.clipboardData;

        if (!data || (data.files && data.files.length)) {
            return;
        }

        event.preventDefault();

        var text = String(data.getData('text/plain') || '').replace(/\r\n?/g, '\n');

        if (singleLine) {
            command('insertText', text.replace(/\s*\n\s*/g, ' '));
            return;
        }

        text.split('\n').forEach(function (line, index) {
            if (index > 0) {
                lineBreak();
            }

            if (line !== '') {
                command('insertText', line);
            }
        });
    }

    function lineBreak() {
        if (command('insertLineBreak')) {
            return;
        }

        command('insertHTML', '<br>');
    }

    // Any contenteditable surface of this file: shortcuts, plain paste,
    // double-click on a link.
    function bindSurface(surface, singleLine) {
        surface.classList.add('ws-rich-surface');

        surface.addEventListener('keydown', function (event) {
            shortcut(surface, event);
        });

        surface.addEventListener('paste', function (event) {
            pastePlain(event, singleLine);
        });

        surface.addEventListener('drop', function (event) {
            var data = event.dataTransfer;

            if (data && !(data.files && data.files.length) && data.getData('text/plain')) {
                event.preventDefault();
                command('insertText', String(data.getData('text/plain')).replace(/\s*\n\s*/g, singleLine ? ' ' : '\n'));
            }
        });

        surface.addEventListener('dblclick', function (event) {
            var anchor = event.target.closest ? event.target.closest('a') : null;

            if (anchor && surface.contains(anchor)) {
                event.preventDefault();
                openLink(surface, anchor);
            }
        });

        // A link in the box is not followed on click.
        surface.addEventListener('click', function (event) {
            if (event.target.closest && event.target.closest('a')) {
                event.preventDefault();
            }
        });
    }

    // ── The channel's writing box ──────────────────────────────────────

    /**
     * options: placeholder, previews (cards drawn as what they hold, see
     * drawPreviews), onInput(), onEnter(event), onKeydown(event) → true when
     * handled, onBlockOpen(kind, markup, done)
     */
    function Editor(options) {
        var self = this;

        self.options = options || {};
        self.savedRange = null;

        var node = el('div', 'ws-rich form-control');
        node.contentEditable = 'true';
        node.setAttribute('role', 'textbox');
        node.setAttribute('aria-multiline', 'true');
        node.setAttribute('spellcheck', 'true');
        node.setAttribute('data-placeholder', self.options.placeholder || '');
        node.setAttribute('aria-label', self.options.placeholder || '');
        node.classList.add('is-empty');
        self.node = node;
        node.wsEditor = self;

        bindSurface(node, false);

        node.addEventListener('input', function () {
            self.tidy();
            self.fenceCheck();

            if (self.options.previews) {
                clearTimeout(self.previewTimer);
                self.previewTimer = setTimeout(function () { self.drawPreviews(); }, 250);
            }

            if (self.options.onInput) {
                self.options.onInput();
            }
        });

        node.addEventListener('keydown', function (event) {
            if (self.options.onKeydown && self.options.onKeydown(event)) {
                return;
            }

            if (event.key !== 'Enter' || event.isComposing || event.defaultPrevented) {
                return;
            }

            if (!event.shiftKey && !touchScreen() && self.options.onEnter) {
                event.preventDefault();
                self.options.onEnter(event);
                return;
            }

            event.preventDefault();
            lineBreak();
            self.tidy();
        });

        node.addEventListener('click', function (event) {
            var card = event.target.closest ? event.target.closest('.ws-rich-block') : null;

            if (!card || !node.contains(card)) {
                return;
            }

            event.preventDefault();

            if (event.target.closest('[data-block-remove]')) {
                var after = card.nextSibling;

                if (after && after.nodeType === 1 && after.tagName === 'BR') {
                    after.remove();
                }

                card.remove();
                self.tidy();
                node.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            self.editBlock(card);
        });

        // The caret is kept when the box loses the focus, for what a button
        // outside it inserts (an emoji, a table).
        node.addEventListener('blur', function () {
            var range = selectionRange();

            if (range && node.contains(range.startContainer)) {
                self.savedRange = range.cloneRange();
            }
        });
    }

    Editor.prototype = {
        focus: function () {
            this.node.focus();
        },

        isEmpty: function () {
            return (this.node.textContent || '').replace(/[\s ​]/g, '') === ''
                && !this.node.querySelector('[data-token], .ws-rich-block');
        },

        // An emptied box keeps no stray <br>, so the placeholder shows again.
        tidy: function () {
            var empty = this.isEmpty();

            if (empty && this.node.innerHTML !== '') {
                this.node.innerHTML = '';
            }

            this.node.classList.toggle('is-empty', empty);
        },

        clear: function () {
            this.node.innerHTML = '';
            this.savedRange = null;
            this.tidy();
        },

        markup: function () {
            return serialize(this.node, false);
        },

        // A stored text back into the box, for an edit. labels: token →
        // the name the tag had.
        setMarkup: function (markup, labels) {
            var node = this.node;
            var blocks = parseBlocks(markup);

            node.innerHTML = '';

            blocks.forEach(function (block, index) {
                if (block.kind === 'text') {
                    inlineNodes(block.markup, labels, node);

                    if (index < blocks.length - 1) {
                        node.appendChild(el('br'));
                    }
                } else {
                    node.appendChild(blockNode(block.kind, block.markup));
                    node.appendChild(el('br'));
                }
            });

            this.tidy();
            this.caretToEnd();
            this.drawPreviews();
        },

        // With options.previews (a note) the cards show what they hold, drawn
        // by the server as in a message: a table with its values worked out, a
        // calculation, a list, code. A card is drawn again only when what it
        // holds has changed; one that could not be drawn stays a card and is
        // tried again on the next change.
        drawPreviews: function () {
            var self = this;

            if (!self.options.previews || !CFG.api_url) {
                return;
            }

            var cards = Array.prototype.filter.call(self.node.querySelectorAll('.ws-rich-block'), function (card) {
                return card.wsShown !== (card.getAttribute('data-markup') || '');
            });

            if (!cards.length) {
                return;
            }

            var wanted = cards.map(function (card) {
                card.wsShown = card.getAttribute('data-markup') || '';
                return card.wsShown;
            });

            function again() {
                cards.forEach(function (card) { card.wsShown = null; });
            }

            fetch(CFG.api_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'ws_calc', token: CFG.token, blocks: wanted })
            }).then(function (response) {
                return response.json();
            }).then(function (json) {
                var drawn = (json && (json.status === 'success') && json.data && json.data.blocks) || null;

                if (!drawn) {
                    again();
                    return;
                }

                cards.forEach(function (card, index) {
                    if (card.parentNode && ((card.getAttribute('data-markup') || '') === wanted[index])) {
                        previewBlock(card, drawn[index]);
                    }
                });
            }).catch(again);
        },

        caretToEnd: function () {
            var range = document.createRange();
            var selection = window.getSelection();

            range.selectNodeContents(this.node);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
            this.savedRange = range.cloneRange();
        },

        // Puts the caret back where it was before a button took the focus.
        // A caret still in the box stays where it is: a button that does not
        // take the focus (a note's toolbar) leaves no blur to remember it by.
        restore: function () {
            var selection = window.getSelection();
            var range = selection.rangeCount ? selection.getRangeAt(0) : null;

            if (range && this.node.contains(range.startContainer)) {
                this.node.focus();
                selection.removeAllRanges();
                selection.addRange(range);
                this.savedRange = range.cloneRange();
                return;
            }

            this.node.focus();

            if (this.savedRange && this.node.contains(this.savedRange.startContainer)) {
                selection.removeAllRanges();
                selection.addRange(this.savedRange);
            } else if (!selection.rangeCount || !this.node.contains(selection.getRangeAt(0).startContainer)) {
                this.caretToEnd();
            }
        },

        // What is written before the caret, chips and cards as U+FFFC.
        beforeCaret: function () {
            var range = selectionRange();

            if (!range || !this.node.contains(range.startContainer)) {
                return '';
            }

            var head = document.createRange();

            head.setStart(this.node, 0);
            head.setEnd(range.startContainer, range.startOffset);

            return plainOf(head.cloneContents());
        },

        // Replaces the characters just typed (a "@ay" being picked) with a
        // chip, or with text.
        replaceTyped: function (count, item) {
            var selection = window.getSelection();

            this.node.focus();

            for (var i = 0; i < count; i++) {
                selection.modify('extend', 'backward', 'character');
            }

            if (item.token) {
                var holder = el('span');
                holder.appendChild(chipNode(item.token, item.shown, item.icon || ''));
                command('insertHTML', holder.innerHTML + ' ');
            } else if (item.text) {
                command('insertText', item.text);
            } else if (!selection.isCollapsed) {
                // Nothing in its place: what was typed goes away.
                command('delete');
            }

            this.tidy();
        },

        insertText: function (text) {
            this.restore();
            command('insertText', text);
            this.tidy();
            this.node.dispatchEvent(new Event('input', { bubbles: true }));
        },

        // A link at the caret (a file added to a note), apart from the words
        // around it.
        insertLink: function (text, url) {
            this.restore();

            var before = this.beforeCaret();
            var holder = el('span');
            var link = el('a', '', text);

            link.href = url;
            holder.appendChild(link);
            command('insertHTML', ((before !== '' && !/\s$/.test(before)) ? ' ' : '') + holder.innerHTML + ' ');
            this.tidy();
            this.node.dispatchEvent(new Event('input', { bubbles: true }));
        },

        // A table, a checklist or code, as a card on a line of its own.
        insertBlock: function (kind, markup) {
            this.restore();

            var before = this.beforeCaret();
            var holder = el('span');

            holder.appendChild(blockNode(kind, markup));

            command('insertHTML', ((before !== '' && !/\n$/.test(before)) ? '<br>' : '') + holder.innerHTML + '<br>');

            // A line after the card to write on.
            if (!this.node.lastChild || this.node.lastChild.nodeType !== 1 || this.node.lastChild.tagName !== 'BR') {
                this.node.appendChild(el('br'));
            }

            // The next card or emoji goes after this one.
            var range = selectionRange();

            if (range && this.node.contains(range.startContainer)) {
                this.savedRange = range.cloneRange();
            }

            this.tidy();
            this.node.dispatchEvent(new Event('input', { bubbles: true }));
        },

        editBlock: function (card) {
            var self = this;
            var kind = card.getAttribute('data-kind');

            open(kind, card.getAttribute('data-markup'), function (markup) {
                if (markup === '') {
                    card.remove();
                } else {
                    card.replaceWith(blockNode(kind, markup));
                }

                self.tidy();
                self.node.dispatchEvent(new Event('input', { bubbles: true }));
            });
        },

        // The lines of the box as text, each with the place its result goes:
        // what a line that ends with "=" comes to is shown there. A line
        // ends at a <br>, a card, or a "\n" - the browser types Shift+Enter
        // into this box as "\n" in the text itself.
        lines: function () {
            var lines = [{ text: '', anchor: null }];

            function end() {
                if (lines[lines.length - 1].text !== '' || lines[lines.length - 1].anchor) {
                    lines.push({ text: '', anchor: null });
                }
            }

            function walk(parent) {
                Array.prototype.forEach.call(parent.childNodes, function (child) {
                    var line = lines[lines.length - 1];

                    if (child.nodeType === 3) {
                        var at = 0;

                        child.nodeValue.split('\n').forEach(function (part, index) {
                            if (index > 0) {
                                lines.push({ text: '', anchor: null });
                            }

                            var current = lines[lines.length - 1];

                            at += part.length;
                            current.text += part.replace(/[\u200b]/g, '').replace(/\u00a0/g, ' ');
                            current.anchor = child;
                            current.offset = at;
                            at += 1;
                        });

                        return;
                    }

                    if (child.nodeType !== 1 || child.hasAttribute('data-ws-skip')) {
                        return;
                    }

                    if (child.tagName === 'BR') {
                        end();
                        return;
                    }

                    if (child.classList.contains('ws-rich-block')) {
                        end();
                        lines[lines.length - 1].anchor = child;
                        lines[lines.length - 1].block = true;
                        lines.push({ text: '', anchor: null });
                        return;
                    }

                    if (child.hasAttribute('data-token')) {
                        line.text += ' ';
                        line.anchor = child;
                        line.offset = null;
                        return;
                    }

                    if (BLOCKS.test(child.tagName)) {
                        end();
                        walk(child);
                        end();
                        return;
                    }

                    walk(child);
                });
            }

            walk(this.node);

            return lines.filter(function (line) { return !line.block; });
        },

        // Shows the results of the lines that end with "=": results is a list
        // in the order of lines(), null where there is nothing to show.
        // A result inside a text goes where its line ends, the text split
        // there; the lines are taken from the last up so that a split does not
        // move the place of a line above it. The split pieces are joined again
        // on the next pass (the caret stays where it is either way).
        showResults: function (lines, results) {
            var found = this.node.querySelectorAll('[data-ws-skip]');

            Array.prototype.forEach.call(found, function (old) {
                old.remove();
            });

            if (found.length) {
                this.node.normalize();
                lines = this.lines();
            }

            for (var index = lines.length - 1; index >= 0; index--) {
                var line = lines[index];
                var result = results[index];

                if (!result || !line.anchor || !line.anchor.parentNode) {
                    continue;
                }

                var shown = el('span', 'ws-inline-result' + (result.error ? ' ws-inline-error' : ''), result.display);
                shown.contentEditable = 'false';
                shown.setAttribute('data-ws-skip', '1');
                shown.title = result.error ? result.error : result.source;

                if ((line.anchor.nodeType === 3) && (line.offset !== null) && (line.offset < line.anchor.nodeValue.length)) {
                    line.anchor.parentNode.insertBefore(shown, line.anchor.splitText(line.offset));
                } else {
                    line.anchor.parentNode.insertBefore(shown, line.anchor.nextSibling);
                }
            }
        },

        // ``` typed at the start of a line opens the code window.
        fenceCheck: function () {
            var self = this;
            var before = self.beforeCaret();

            if (!/(^|\n)```$/.test(before)) {
                return;
            }

            self.replaceTyped(3, { text: '' });
            self.savedRange = selectionRange() ? selectionRange().cloneRange() : null;

            open('code', '', function (markup) {
                if (markup !== '') {
                    self.insertBlock('code', markup);
                }
            });
        }
    };

    // ── Design windows ─────────────────────────────────────────────────

    function modal(title, size) {
        var node = el('div', 'modal fade ws-designer');
        node.tabIndex = -1;
        node.setAttribute('aria-hidden', 'true');
        node.innerHTML = '<div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down ' + (size || 'modal-lg') + '"><div class="modal-content">'
            + '<div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
            + '<div class="modal-body"></div><div class="modal-footer"></div></div></div>';
        node.querySelector('.modal-title').textContent = title;
        node.querySelector('.btn-close').setAttribute('aria-label', t('close'));
        document.body.appendChild(node);

        var instance = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(node) : null;

        node.addEventListener('hidden.bs.modal', function () {
            hideBar();
            hideTip();
            node.remove();
        });

        return {
            node: node,
            body: node.querySelector('.modal-body'),
            footer: node.querySelector('.modal-footer'),
            show: function () { if (instance) { instance.show(); } },
            hide: function () { if (instance) { instance.hide(); } }
        };
    }

    // A small menu at the pointer (the table's cell menu).
    var menuNode = null;

    function closeMenu() {
        if (menuNode) {
            menuNode.remove();
            menuNode = null;
        }
    }

    function menu(x, y, items, host) {
        closeMenu();
        menuNode = el('div', 'ws-ctx contextmenu popover shadow-lg ws-designer-menu');

        var body = el('div', 'popover-body');

        items.forEach(function (item) {
            if (item === '-') {
                body.appendChild(el('hr', 'divider my-1'));
                return;
            }

            var entry = button('btn btn-link link-body-emphasis text-start text-decoration-none ws-ctx-item' + (item.danger ? ' ws-ctx-danger' : ''), item.label, item.icon);
            entry.disabled = !!item.disabled;
            entry.addEventListener('mousedown', function (event) { event.preventDefault(); });
            entry.addEventListener('click', function () {
                closeMenu();
                item.action();
            });
            body.appendChild(entry);
        });

        menuNode.appendChild(body);
        (host || document.body).appendChild(menuNode);
        menuNode.style.left = Math.max(8, Math.min(x, window.innerWidth - menuNode.offsetWidth - 8)) + 'px';
        menuNode.style.top = Math.max(8, Math.min(y, window.innerHeight - menuNode.offsetHeight - 8)) + 'px';
    }

    document.addEventListener('mousedown', function (event) {
        if (menuNode && !menuNode.contains(event.target)) {
            closeMenu();
        }
    }, true);

    // Escape closes the menu or the format bar only, not the window they
    // were opened in.
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || !(menuNode || bar)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (menuNode) {
            closeMenu();
        } else {
            window.clearTimeout(barTimer);
            hideBar();
        }
    }, true);

    function footerButtons(win, isNew, onSave, insertLabel) {
        var cancel = button('btn btn-sm btn-ghost', t('cancel'));
        cancel.setAttribute('data-bs-dismiss', 'modal');
        win.footer.appendChild(cancel);

        var save = button('btn btn-sm btn-primary rounded-pill px-3', isNew ? (insertLabel || t('insert_to_message')) : t('update_block'), 'bi-check2');
        save.addEventListener('click', onSave);
        win.footer.appendChild(save);
    }

    function cellSurface(markup, tag) {
        var cell = el(tag || 'div', 'ws-cell-edit');

        cell.contentEditable = 'true';
        bindSurface(cell, true);
        inlineNodes(markup || '', {}, cell);

        return cell;
    }

    // ── The table window ──

    function sheetLetter(index) {
        return String.fromCharCode(65 + index);
    }

    function openTable(markup, done, options) {
        options = options || {};

        var MAX_COLUMNS = 12;
        var MAX_ROWS = 200;
        var model = { head: [], rows: [], align: [] };

        if (markup && typeof markup === 'string') {
            var lines = markup.split('\n');

            model.head = tableCells(lines[0] || '');
            model.align = tableAlignments(lines[1] || '') || [];
            model.rows = lines.slice(2).filter(tableRow).map(tableCells);
        } else {
            var columns = Math.max(1, Math.min(MAX_COLUMNS, (markup && markup.columns) || 3));
            var rows = Math.max(1, Math.min(MAX_ROWS, (markup && markup.rows) || 2));

            for (var c = 0; c < columns; c++) {
                model.head.push(t('table_column', c + 1));
            }

            for (var r = 0; r < rows; r++) {
                model.rows.push(model.head.map(function () { return ''; }));
            }
        }

        var width = Math.max(1, model.head.length);

        model.rows = model.rows.map(function (row) {
            while (row.length < width) {
                row.push('');
            }

            return row.slice(0, width);
        });

        while (model.align.length < width) {
            model.align.push('');
        }

        var win = modal(t('designer_table'), 'modal-xl');
        var tools = el('div', 'ws-table-tools');
        var sheetBar = el('div', 'ws-sheet-bar');
        var refBox = el('span', 'ws-sheet-ref', 'A1');
        var formulaInput = el('input', 'form-control form-control-sm ws-sheet-formula');
        var resultBox = el('span', 'ws-sheet-result');
        var wrap = el('div', 'ws-table-edit-wrap');
        var table = el('table', 'table table-sm ws-table ws-table-edit ws-table-sheet');
        var current = { row: -1, column: 0 };
        var results = {};

        formulaInput.type = 'text';
        formulaInput.spellcheck = false;
        formulaInput.setAttribute('aria-label', t('table_formula_bar'));
        formulaInput.placeholder = t('table_formula_bar');
        sheetBar.appendChild(refBox);
        sheetBar.appendChild(formulaInput);
        sheetBar.appendChild(resultBox);

        win.body.appendChild(tools);
        win.body.appendChild(sheetBar);
        win.body.appendChild(wrap);
        wrap.appendChild(table);
        win.body.appendChild(el('div', 'form-text mt-2', t('table_hint')));
        win.body.appendChild(el('div', 'form-text', t('table_formula_hint')));

        // What a cell holds: its formula while it shows the value, its
        // words otherwise.
        function cellValue(cell) {
            return cell.hasAttribute('data-raw') ? cell.getAttribute('data-raw') : serialize(cell, true);
        }

        function gridRow(row) {
            return row + 1;
        }

        function cellAt(row, column) {
            return (row < 0)
                ? table.querySelectorAll('thead .ws-cell-edit')[column]
                : ((table.querySelectorAll('tbody tr')[row] || { querySelectorAll: function () { return []; } }).querySelectorAll('.ws-cell-edit')[column]);
        }

        // A formula cell that is not being edited shows what it comes to.
        function showValue(cell, row, column) {
            if (!cell || document.activeElement === cell) {
                return;
            }

            var raw = cellValue(cell);
            var found = results[gridRow(row) + ':' + column];

            if (!/^\s*=/.test(raw) || !found) {
                if (cell.hasAttribute('data-raw')) {
                    cell.textContent = raw;
                    cell.removeAttribute('data-raw');
                }

                cell.classList.remove('ws-cell-computed', 'ws-cell-error');
                cell.removeAttribute('title');
                return;
            }

            cell.setAttribute('data-raw', raw);
            cell.textContent = found.display;
            cell.classList.add('ws-cell-computed');
            cell.classList.toggle('ws-cell-error', !!found.error);
            cell.title = raw + (found.error ? ' · ' + found.error : '');
        }

        function showValues() {
            Array.prototype.forEach.call(table.querySelectorAll('.ws-cell-edit'), function (cell) {
                showValue(cell, +cell.getAttribute('data-row'), +cell.getAttribute('data-column'));
            });

            showCurrent();
        }

        function showCurrent() {
            var cell = cellAt(current.row, current.column);
            var found = results[gridRow(current.row) + ':' + current.column];
            var raw = cell ? cellValue(cell) : '';

            refBox.textContent = sheetLetter(current.column) + (gridRow(current.row) + 1);

            if (document.activeElement !== formulaInput) {
                formulaInput.value = raw;
            }

            resultBox.textContent = (/^\s*=/.test(raw) && found) ? '= ' + found.display : '';
            resultBox.classList.toggle('ws-sheet-error-text', !!(found && found.error));
            resultBox.title = (found && found.error) ? found.error : '';

            Array.prototype.forEach.call(table.querySelectorAll('.ws-sheet-col, .ws-sheet-num'), function (mark) {
                mark.classList.toggle('active', mark.classList.contains('ws-sheet-col')
                    ? (+mark.getAttribute('data-column') === current.column)
                    : (+mark.getAttribute('data-row') === current.row));
            });
        }

        // The formula bar writes into the cell it names.
        formulaInput.addEventListener('input', function () {
            var cell = cellAt(current.row, current.column);

            if (!cell) {
                return;
            }

            cell.removeAttribute('data-raw');
            cell.classList.remove('ws-cell-computed', 'ws-cell-error');
            cell.textContent = formulaInput.value;
            clearTimeout(calcTimer);
            calcTimer = setTimeout(recalc, 350);
        });

        formulaInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();

                if (current.row === model.rows.length - 1) {
                    addRow(model.rows.length);
                } else {
                    focusCell(current.row + 1, current.column);
                }
            }
        });

        // Formulas (cells starting with =) worked out as they are typed, by the
        // code that works them out in the message itself.
        var calcTimer = 0;
        var calcSerial = 0;

        function recalc() {
            read();

            var grid = [model.head].concat(model.rows);
            var formulas = grid.some(function (row) {
                return row.some(function (value) { return /^\s*=/.test(String(value)); });
            });

            if (!formulas || !CFG.api_url) {
                results = {};
                showValues();
                return;
            }

            var mine = ++calcSerial;

            fetch(CFG.api_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'ws_calc', token: CFG.token, grid: grid })
            }).then(function (response) {
                return response.json();
            }).then(function (json) {
                if ((mine !== calcSerial) || !json || (json.status !== 'success')) {
                    return;
                }

                results = {};

                json.data.cells.forEach(function (cell) {
                    if (cell.formula) {
                        results[cell.r + ':' + cell.c] = cell;
                    }
                });

                showValues();
            }).catch(function () {});
        }

        table.addEventListener('input', function (event) {
            var cell = event.target.closest ? event.target.closest('.ws-cell-edit') : null;

            if (cell && (+cell.getAttribute('data-row') === current.row) && (+cell.getAttribute('data-column') === current.column)) {
                formulaInput.value = serialize(cell, true);
            }

            clearTimeout(calcTimer);
            calcTimer = setTimeout(recalc, 350);
        });

        // The grid as it is on the screen, back into the model.
        function read() {
            var head = [];
            var rowsOut = [];

            Array.prototype.forEach.call(table.querySelectorAll('thead .ws-cell-edit'), function (cell) {
                head.push(cellValue(cell));
            });

            Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (tr) {
                rowsOut.push(Array.prototype.map.call(tr.querySelectorAll('.ws-cell-edit'), function (cell) {
                    return cellValue(cell);
                }));
            });

            model.head = head;
            model.rows = rowsOut;
        }

        function focusCell(row, column) {
            var cell = cellAt(row, column);

            if (cell) {
                cell.focus();

                var range = document.createRange();
                range.selectNodeContents(cell);
                range.collapse(false);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
            }
        }

        function addRow(at) {
            read();

            if (model.rows.length >= MAX_ROWS) {
                return;
            }

            model.rows.splice(at, 0, model.head.map(function () { return ''; }));
            draw();
            focusCell(at, Math.max(0, current.column));
        }

        function addColumn(at) {
            read();

            if (model.head.length >= MAX_COLUMNS) {
                return;
            }

            model.head.splice(at, 0, t('table_column', model.head.length + 1));
            model.align.splice(at, 0, '');
            model.rows.forEach(function (row) { row.splice(at, 0, ''); });
            draw();
            focusCell(-1, at);
        }

        function removeRow(at) {
            read();

            if (model.rows.length <= 1 || at < 0) {
                return;
            }

            model.rows.splice(at, 1);
            draw();
            focusCell(Math.min(at, model.rows.length - 1), current.column);
        }

        function removeColumn(at) {
            read();

            if (model.head.length <= 1) {
                return;
            }

            model.head.splice(at, 1);
            model.align.splice(at, 1);
            model.rows.forEach(function (row) { row.splice(at, 1); });
            draw();
            focusCell(current.row, Math.min(at, model.head.length - 1));
        }

        function setAlign(column, side) {
            read();
            model.align[column] = side;
            draw();
            focusCell(current.row, column);
        }

        function cellMenuItems(row, column) {
            return [
                { icon: 'bi-arrow-bar-up', label: t('row_above'), action: function () { addRow(Math.max(0, row)); }, disabled: row < 0 },
                { icon: 'bi-arrow-bar-down', label: t('row_below'), action: function () { addRow(row + 1); } },
                { icon: 'bi-arrow-bar-left', label: t('col_left'), action: function () { addColumn(column); } },
                { icon: 'bi-arrow-bar-right', label: t('col_right'), action: function () { addColumn(column + 1); } },
                '-',
                { icon: 'bi-text-left', label: t('align_left'), action: function () { setAlign(column, 'left'); } },
                { icon: 'bi-text-center', label: t('align_center'), action: function () { setAlign(column, 'center'); } },
                { icon: 'bi-text-right', label: t('align_right'), action: function () { setAlign(column, 'right'); } },
                '-',
                { icon: 'bi-trash', label: t('delete_row'), danger: true, disabled: row < 0 || model.rows.length <= 1, action: function () { removeRow(row); } },
                { icon: 'bi-trash', label: t('delete_column'), danger: true, disabled: model.head.length <= 1, action: function () { removeColumn(column); } }
            ];
        }

        function makeCell(tag, markupValue, row, column) {
            var holder = el(tag);
            var side = model.align[column];
            var cell = cellSurface(markupValue);

            if (side) {
                holder.className = 'text-' + (side === 'right' ? 'end' : (side === 'center' ? 'center' : 'start'));
            }

            cell.setAttribute('data-row', row);
            cell.setAttribute('data-column', column);

            // Edited, a formula cell shows its formula again.
            cell.addEventListener('focus', function () {
                current.row = row;
                current.column = column;

                if (cell.hasAttribute('data-raw')) {
                    cell.textContent = cell.getAttribute('data-raw');
                    cell.removeAttribute('data-raw');
                    cell.classList.remove('ws-cell-computed', 'ws-cell-error');

                    var range = document.createRange();
                    range.selectNodeContents(cell);
                    range.collapse(false);
                    window.getSelection().removeAllRanges();
                    window.getSelection().addRange(range);
                }

                drawTools();
                showCurrent();
            });

            cell.addEventListener('blur', function () {
                window.setTimeout(function () { showValue(cell, row, column); }, 0);
            });

            cell.addEventListener('keydown', function (event) {
                if (event.defaultPrevented) {
                    return;
                }

                var last = (row === model.rows.length - 1) && (column === model.head.length - 1);

                if (event.key === 'Tab') {
                    event.preventDefault();

                    if (event.shiftKey) {
                        if (column > 0) {
                            focusCell(row, column - 1);
                        } else if (row >= 0) {
                            focusCell(row - 1, model.head.length - 1);
                        }
                    } else if (last) {
                        addRow(model.rows.length);
                        focusCell(model.rows.length - 1, 0);
                    } else if (column < model.head.length - 1) {
                        focusCell(row, column + 1);
                    } else {
                        focusCell(row + 1, 0);
                    }
                } else if (event.key === 'Enter') {
                    event.preventDefault();

                    if (row === model.rows.length - 1) {
                        addRow(model.rows.length);
                    } else {
                        focusCell(row + 1, column);
                    }
                }
            });

            cell.addEventListener('contextmenu', function (event) {
                event.preventDefault();
                current.row = row;
                current.column = column;
                menu(event.clientX, event.clientY, cellMenuItems(row, column), win.node.querySelector('.modal-content'));
            });

            holder.appendChild(cell);

            return holder;
        }

        function draw() {
            table.innerHTML = '';

            var thead = el('thead');
            var letters = el('tr', 'ws-sheet-letters');
            var headRow = el('tr');
            var number = function (row) {
                var mark = el('th', 'ws-sheet-num', String(gridRow(row) + 1));
                mark.setAttribute('data-row', row);
                return mark;
            };

            letters.appendChild(el('th', 'ws-sheet-corner'));

            model.head.forEach(function (value, column) {
                var letter = el('th', 'ws-sheet-col', sheetLetter(column));
                letter.setAttribute('data-column', column);
                letters.appendChild(letter);
            });

            headRow.appendChild(number(-1));

            model.head.forEach(function (value, column) {
                headRow.appendChild(makeCell('th', value, -1, column));
            });

            thead.appendChild(letters);
            thead.appendChild(headRow);
            table.appendChild(thead);

            var tbody = el('tbody');

            model.rows.forEach(function (row, rowIndex) {
                var tr = el('tr');

                tr.appendChild(number(rowIndex));

                row.forEach(function (value, column) {
                    tr.appendChild(makeCell('td', value, rowIndex, column));
                });

                tbody.appendChild(tr);
            });

            table.appendChild(tbody);
            drawTools();
            recalc();
        }

        function drawTools() {
            tools.innerHTML = '';

            var addRowButton = button('btn btn-sm btn-ghost', t('add_row'), 'bi-plus-lg');
            addRowButton.addEventListener('mousedown', function (event) { event.preventDefault(); });
            addRowButton.addEventListener('click', function () { addRow(current.row + 1 > 0 ? current.row + 1 : model.rows.length); });
            addRowButton.disabled = model.rows.length >= MAX_ROWS;
            tools.appendChild(addRowButton);

            var addColumnButton = button('btn btn-sm btn-ghost', t('add_column'), 'bi-plus-lg');
            addColumnButton.addEventListener('mousedown', function (event) { event.preventDefault(); });
            addColumnButton.addEventListener('click', function () { addColumn(current.column + 1); });
            addColumnButton.disabled = model.head.length >= MAX_COLUMNS;
            tools.appendChild(addColumnButton);

            tools.appendChild(el('span', 'ws-table-tools-gap'));

            [['left', 'bi-text-left', t('align_left')], ['center', 'bi-text-center', t('align_center')], ['right', 'bi-text-right', t('align_right')]].forEach(function (entry) {
                var align = button('btn btn-sm btn-ghost' + (model.align[current.column] === entry[0] ? ' active' : ''), '', entry[1], entry[2]);
                align.addEventListener('mousedown', function (event) { event.preventDefault(); });
                align.addEventListener('click', function () { setAlign(current.column, (model.align[current.column] === entry[0]) ? '' : entry[0]); });
                tools.appendChild(align);
            });

            tools.appendChild(el('span', 'ws-table-tools-gap'));

            var dropRow = button('btn btn-sm btn-ghost ws-tool-danger', '', 'bi-dash-square', t('delete_row'));
            dropRow.addEventListener('mousedown', function (event) { event.preventDefault(); });
            dropRow.addEventListener('click', function () { removeRow(current.row); });
            dropRow.disabled = current.row < 0 || model.rows.length <= 1;
            tools.appendChild(dropRow);

            var dropColumn = button('btn btn-sm btn-ghost ws-tool-danger', '', 'bi-layout-sidebar-inset-reverse', t('delete_column'));
            dropColumn.addEventListener('mousedown', function (event) { event.preventDefault(); });
            dropColumn.addEventListener('click', function () { removeColumn(current.column); });
            dropColumn.disabled = model.head.length <= 1;
            tools.appendChild(dropColumn);
        }

        draw();

        footerButtons(win, !(typeof markup === 'string' && markup), function () {
            read();

            var escape = function (value) { return String(value).replace(/\|/g, '\\|'); };
            var rule = model.align.map(function (side) {
                return (side === 'center') ? ':---:' : ((side === 'right') ? '---:' : ((side === 'left') ? ':---' : '---'));
            });
            var linesOut = ['| ' + model.head.map(escape).join(' | ') + ' |', '| ' + rule.join(' | ') + ' |'];

            model.rows.forEach(function (row) {
                linesOut.push('| ' + row.map(function (value) { return escape(value) || ' '; }).join(' | ') + ' |');
            });

            win.hide();
            done(linesOut.join('\n'));
        }, options.insertLabel);

        win.node.addEventListener('shown.bs.modal', function () { focusCell(-1, 0); });
        win.show();
    }

    // ── The checklist window ──

    function openChecklist(markup, done) {
        var items = [];

        if (markup) {
            markup.split('\n').forEach(function (line) {
                var match = CHECK.exec(line);

                if (match) {
                    items.push({ checked: match[1] !== ' ', text: match[2] });
                }
            });
        }

        if (!items.length) {
            items = [{ checked: false, text: '' }, { checked: false, text: '' }];
        }

        var win = modal(t('designer_checklist'), 'modal-lg');
        var list = el('div', 'ws-check-edit');

        win.body.appendChild(list);

        var add = button('btn btn-sm btn-ghost mt-1', t('add_item'), 'bi-plus-lg');
        win.body.appendChild(add);
        win.body.appendChild(el('div', 'form-text', t('checklist_hint')));

        function rows() {
            return Array.prototype.slice.call(list.querySelectorAll('.ws-check-edit-row'));
        }

        function focusRow(row) {
            var text = row && row.querySelector('.ws-cell-edit');

            if (text) {
                text.focus();

                var range = document.createRange();
                range.selectNodeContents(text);
                range.collapse(false);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
            }
        }

        function makeRow(item) {
            var row = el('div', 'ws-check-edit-row');
            var box = el('input', 'form-check-input');

            box.type = 'checkbox';
            box.checked = !!item.checked;
            box.setAttribute('aria-label', t('item_done'));
            row.appendChild(box);

            var text = cellSurface(item.text);
            text.setAttribute('data-placeholder', t('item_placeholder'));
            text.classList.toggle('is-empty', !item.text);
            text.addEventListener('input', function () {
                text.classList.toggle('is-empty', (text.textContent || '').trim() === '');
            });
            row.appendChild(text);

            var up = button('btn btn-sm btn-ghost', '', 'bi-arrow-up', t('move_up'));
            up.addEventListener('click', function () {
                if (row.previousElementSibling) {
                    list.insertBefore(row, row.previousElementSibling);
                }
            });
            row.appendChild(up);

            var down = button('btn btn-sm btn-ghost', '', 'bi-arrow-down', t('move_down'));
            down.addEventListener('click', function () {
                if (row.nextElementSibling) {
                    list.insertBefore(row.nextElementSibling, row);
                }
            });
            row.appendChild(down);

            var remove = button('btn btn-sm btn-ghost ws-tool-danger', '', 'bi-x-lg', t('remove'));
            remove.addEventListener('click', function () {
                if (rows().length > 1) {
                    var previous = row.previousElementSibling || row.nextElementSibling;
                    row.remove();
                    focusRow(previous);
                } else {
                    text.innerHTML = '';
                    text.classList.add('is-empty');
                }
            });
            row.appendChild(remove);

            text.addEventListener('keydown', function (event) {
                if (event.defaultPrevented) {
                    return;
                }

                if (event.key === 'Enter') {
                    event.preventDefault();

                    // An empty item waiting below is used before a new one.
                    var waiting = row.nextElementSibling;

                    if (waiting && ((waiting.querySelector('.ws-cell-edit').textContent || '').trim() === '')) {
                        focusRow(waiting);
                        return;
                    }

                    var next = makeRow({ checked: false, text: '' });
                    list.insertBefore(next, row.nextSibling);
                    focusRow(next);
                } else if (event.key === 'Backspace' && (text.textContent || '') === '' && rows().length > 1) {
                    event.preventDefault();

                    var previous = row.previousElementSibling || row.nextElementSibling;
                    row.remove();
                    focusRow(previous);
                } else if (event.key === 'ArrowUp' && row.previousElementSibling) {
                    event.preventDefault();
                    focusRow(row.previousElementSibling);
                } else if (event.key === 'ArrowDown' && row.nextElementSibling) {
                    event.preventDefault();
                    focusRow(row.nextElementSibling);
                }
            });

            return row;
        }

        items.forEach(function (item) { list.appendChild(makeRow(item)); });

        add.addEventListener('click', function () {
            var row = makeRow({ checked: false, text: '' });
            list.appendChild(row);
            focusRow(row);
        });

        footerButtons(win, !markup, function () {
            var lines = [];

            rows().forEach(function (row) {
                var text = serialize(row.querySelector('.ws-cell-edit'), true);

                if (text !== '') {
                    lines.push('- [' + (row.querySelector('input').checked ? 'x' : ' ') + '] ' + text);
                }
            });

            win.hide();
            done(lines.join('\n'));
        });

        win.node.addEventListener('shown.bs.modal', function () { focusRow(rows()[0]); });
        win.show();
    }

    // ── The code window ──

    var LANGUAGES = ['php', 'js', 'ts', 'html', 'css', 'sql', 'json', 'bash', 'python', 'xml', 'yaml', 'text'];

    function openCode(markup, done) {
        var language = '';
        var code = '';

        if (markup) {
            var lines = markup.split('\n');
            language = fenceOpen(lines[0]) || '';
            code = lines.slice(1, fenceClose(lines[lines.length - 1]) ? -1 : undefined).join('\n');
        }

        var win = modal(t('designer_code'), 'modal-lg');
        var row = el('div', 'd-flex align-items-center gap-2 mb-2');
        var label = el('label', 'form-label mb-0 small', t('code_language'));
        var input = el('input', 'form-control form-control-sm ws-code-language');
        var listId = 'ws-code-languages-' + Date.now();
        var list = el('datalist');

        input.id = listId + '-input';
        label.htmlFor = input.id;
        input.maxLength = 20;
        input.value = language;
        input.placeholder = t('code_language_placeholder');
        input.setAttribute('list', listId);
        list.id = listId;

        LANGUAGES.forEach(function (name) {
            var option = el('option');
            option.value = name;
            list.appendChild(option);
        });

        row.appendChild(label);
        row.appendChild(input);
        row.appendChild(list);
        win.body.appendChild(row);

        var area = el('textarea', 'form-control ws-code-edit');
        area.rows = 14;
        area.spellcheck = false;
        area.value = code;
        area.placeholder = t('code_placeholder');
        area.setAttribute('aria-label', t('designer_code'));
        win.body.appendChild(area);

        // Tab indents rather than leaving the box.
        area.addEventListener('keydown', function (event) {
            if (event.key === 'Tab' && !event.shiftKey) {
                event.preventDefault();

                var start = area.selectionStart;
                area.value = area.value.slice(0, start) + '    ' + area.value.slice(area.selectionEnd);
                area.selectionStart = area.selectionEnd = start + 4;
            }
        });

        footerButtons(win, !markup, function () {
            var body = area.value.replace(/\r\n?/g, '\n').replace(/\s+$/, '');
            var name = input.value.trim().replace(/[^A-Za-z0-9_+#.-]/g, '').slice(0, 20);

            win.hide();

            if (body.trim() === '') {
                done('');
                return;
            }

            // A line of three backticks inside would end the block early.
            done('```' + name + '\n' + body.replace(/^(\s*)```/gm, '$1​```') + '\n```');
        });

        win.node.addEventListener('shown.bs.modal', function () { area.focus(); });
        win.show();
    }

    // ── The calculation window: a ```hesap block ──

    function openCalc(markup, done, options) {
        options = options || {};

        var code = '';

        if (markup) {
            var lines = markup.split('\n');
            code = lines.slice(1, fenceClose(lines[lines.length - 1]) ? -1 : undefined).join('\n');
        }

        var win = modal(t('designer_calc'), 'modal-lg');
        var area = el('textarea', 'form-control ws-code-edit ws-calc-edit');
        var shown = el('div', 'ws-calc-preview');
        var serial = 0;

        area.rows = 8;
        area.spellcheck = false;
        area.value = code || (options.sample || '');
        area.placeholder = t('calc_placeholder');
        area.setAttribute('aria-label', t('designer_calc'));
        win.body.appendChild(el('div', 'form-text mt-0 mb-2', t('calc_hint')));
        win.body.appendChild(area);
        win.body.appendChild(shown);

        // Worked out by the code that works it out in the message itself.
        function preview() {
            var mine = ++serial;
            var body = area.value.replace(/\r\n?/g, '\n');

            if (!CFG.api_url || body.trim() === '') {
                shown.innerHTML = '';
                return;
            }

            fetch(CFG.api_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'ws_calc', token: CFG.token, body: '```hesap\n' + body + '\n```' })
            }).then(function (response) {
                return response.json();
            }).then(function (json) {
                if ((mine === serial) && json && (json.status === 'success')) {
                    shown.innerHTML = json.data.html || '';
                }
            }).catch(function () {});
        }

        var timer = 0;

        area.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(preview, 300);
        });

        footerButtons(win, !markup, function () {
            var body = area.value.replace(/\r\n?/g, '\n').replace(/\s+$/, '');

            win.hide();
            done((body.trim() === '') ? '' : '```hesap\n' + body.replace(/^(\s*)```/gm, '$1\u200b```') + '\n```');
        }, options.insertLabel);

        win.node.addEventListener('shown.bs.modal', function () { area.focus(); });
        win.show();
        preview();
    }

    function open(kind, markup, done, options) {
        if (kind === 'table') {
            openTable(markup, done, options);
        } else if (kind === 'checklist') {
            openChecklist(markup, done);
        } else if ((kind === 'calc') || isCalcBlock(markup)) {
            openCalc((kind === 'calc') ? '' : markup, done, options);
        } else {
            openCode(markup, done);
        }
    }

    // ── Blocks of code in the conversation: the copy button ────────────

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest ? event.target.closest('[data-ws-copy-code]') : null;

        if (!trigger) {
            return;
        }

        var box = trigger.closest('.ws-code');
        var pre = box ? box.querySelector('pre') : null;
        var text = pre ? pre.textContent.replace(/​/g, '') : '';

        function mark() {
            var sign = trigger.querySelector('.bi');

            trigger.classList.add('copied');

            if (sign) {
                sign.className = 'bi bi-check2';
            }

            window.setTimeout(function () {
                trigger.classList.remove('copied');

                if (sign) {
                    sign.className = 'bi bi-clipboard';
                }
            }, 1400);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(mark).catch(function () {});
            return;
        }

        var area = el('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.select();

        try {
            document.execCommand('copy');
            mark();
        } catch (error) {
            // Nothing to copy with; the text can still be selected by hand.
        }

        area.remove();
    });

    window.PGWsEditor = {
        create: function (options) { return new Editor(options); },
        open: open,
        openTable: openTable,
        openChecklist: openChecklist,
        openCode: openCode,
        openCalc: openCalc,
        serialize: serialize,
        hideFloating: function () { hideBar(); hideTip(); }
    };
})();
