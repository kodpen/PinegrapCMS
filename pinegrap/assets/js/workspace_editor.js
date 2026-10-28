/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the writing box of a channel, and the windows that design a
 * table, a checklist or a block of code for it.
 *
 * The box stays a chat box: one field, Enter sends, Shift+Enter starts a new
 * line. What it knows about formatting shows only when asked for: select a
 * few words and a small bar offers bold, italic, strikethrough, highlight,
 * code and a link; double-click a link to change or remove it. People and
 * records picked with @ and # sit in the text as chips, and a table, a
 * checklist or a block of code sits in it as a card that opens its window
 * again when clicked.
 *
 * Messages are still stored as the light markup the server draws
 * (includes/workspace/render.php): **bold**, _italic_, ~~struck~~,
 * ==highlighted==, `code`, [text](address), tables with pipes, "- [ ]"
 * items, ``` fences, and tags as <@user:12> / <#order:1045>. This file turns the box into that text and back.
 *
 * The marks are made here on the nodes themselves (format()), never with
 * document.execCommand(), and the rows of tools under a box (the channel's,
 * a note's, a task's) are built here from the names of their tools
 * (toolbar()), so every box formats, undoes and labels its buttons alike.
 *
 * assets/js/workspace.js asks for the box through window.PGWsEditor; every
 * text comes from the #ws-config block.
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

    var configNode = document.getElementById('ws-config');
    var CFG = {};

    try {
        CFG = JSON.parse((configNode && configNode.textContent) || '{}');
    } catch (error) {
        CFG = {};
    }

    var S = CFG.strings || {};
    var OBJECT = '￼';

    // Templates and function names for sums (includes/workspace/calc.php).
    var CALC = CFG.calc || {};

    // How many steps back Ctrl+Z goes in a writing box.
    var HISTORY_MAX = 100;

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

    // The browser's own editing commands, for what behaves as typing does
    // (text, a line break, a chip or a card at the caret): the caret lands
    // where the browser puts it after typing. Marks never come this way.
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
    //
    // A highlight is the server's pair rule, (?<!=)==(?=\S)([^=\n]{1,300}?)
    // (?<=\S)==(?!=): its words hold no =, so the first = after them closes
    // it. The sign before it is a group of its own rather than a lookbehind,
    // which older browsers cannot read (a syntax error stops the whole
    // bundle); the start of what is left of the line is never after a =.
    function inlineNodes(text, labels, parent) {
        var pattern = /(<[@#][a-z_]+:[0-9]{1,10}>)|`([^`\n]{1,300})`|\[([^\]\n]{1,300})\]\(((?:https?:\/\/|mailto:)[^\s()<>"]{1,1000})\)|\*\*([^*\n]{1,300})\*\*|~~([^~\n]{1,300})~~|(^|[^\p{L}\p{N}_])_([^_\n]{1,300}?)_(?![\p{L}\p{N}_])|(^|[^=])==([^=\s](?:[^=\n]{0,298}[^=\s])?)==(?!=)/u;
        var rest = String(text || '');
        var guard = 0;

        while (rest !== '' && guard++ < 2000) {
            var match = pattern.exec(rest);

            if (!match) {
                parent.appendChild(document.createTextNode(rest));
                break;
            }

            var lead = (match[7] !== undefined) ? match[7] : ((match[9] !== undefined) ? match[9] : '');
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
            } else if (match[10] !== undefined) {
                var marked = el('mark');
                inlineNodes(match[10], labels, marked);
                parent.appendChild(marked);
            } else {
                var italic = el('i');
                inlineNodes(match[8], labels, italic);
                parent.appendChild(italic);
            }

            rest = rest.slice(match.index + match[0].length);
        }

        return parent;
    }

    // The opening line of a fenced block: its language. Only a calculation
    // takes settings after its language (```hesap ondalık=2).
    var CALC_LANGUAGES = ['hesap', 'calc', 'math'];

    function fenceOpen(line) {
        var match = /^\s*```\s*([A-Za-z0-9_+#.-]{0,20})(?:[ \t]+([^`\r\n]{1,80}?))?\s*$/.exec(line || '');

        if (!match || (match[2] && CALC_LANGUAGES.indexOf(match[1].toLowerCase()) === -1)) {
            return null;
        }

        return match[1];
    }

    function fenceInfo(line) {
        var match = /^\s*```\s*[A-Za-z0-9_+#.-]{1,20}[ \t]+([^`\r\n]{1,80}?)\s*$/.exec(line || '');

        return match ? match[1] : '';
    }

    function fenceClose(line) {
        return /^\s*```\s*$/.test(line);
    }

    // A table cell is a formula when it starts with =, unless it starts with
    // a highlight (==x==, the pair rule of inlineNodes()): the twin of
    // ws_calc_is_formula() (includes/workspace/calc.php).
    function isFormula(value) {
        var text = String((value === undefined || value === null) ? '' : value).trim();

        return (text.charAt(0) === '=') && !/^==[^=\s](?:[^=\n]{0,298}[^=\s])?==(?!=)/.test(text);
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

    // A block may carry a title: a line "::: Title" right above it
    // (includes/workspace/blocks.php). The title goes with the block's card.
    var TITLE = /^\s*:::\s+(\S.*)$/;

    function startsBlock(lines, index) {
        var line = lines[index];

        if (line === undefined) {
            return false;
        }

        return (fenceOpen(line) !== null) || (tableRow(line) && (index + 1) < lines.length && !!tableAlignments(lines[index + 1])) || CHECK.test(line);
    }

    function titleSplit(markup) {
        var text = String(markup || '');
        var lines = text.split('\n');
        var match = TITLE.exec(lines[0] || '');

        return (match && lines.length > 1) ? { title: match[1].trim(), markup: lines.slice(1).join('\n') } : { title: '', markup: text };
    }

    // The title field at the head of a block's window, and the answer that
    // puts the title back above the block.
    function titled(markup, done) {
        var parts = (typeof markup === 'string') ? titleSplit(markup) : { title: '', markup: markup };
        var field = null;

        return {
            markup: parts.markup,
            attach: function (win) {
                var wrap = el('div', 'ws-block-title-field mb-3');
                var label = el('label', 'form-label small mb-1', t('block_title'));

                field = el('input', 'form-control form-control-sm');
                field.maxLength = 160;
                field.value = parts.title;
                field.placeholder = t('block_title_help');
                field.id = 'ws-block-title-' + Date.now();
                label.htmlFor = field.id;
                wrap.appendChild(label);
                wrap.appendChild(field);
                win.body.insertBefore(wrap, win.body.firstChild);
            },
            done: function (result) {
                var title = field ? field.value.replace(/\s+/g, ' ').trim() : parts.title;

                done((result === '' || !title) ? result : '::: ' + title + '\n' + result);
            }
        };
    }

    // Lists and quotes: blocks of the box (<ul>, <ol>, <blockquote> straight
    // in it), an item a line (<li>, a <div> in a quote). One level: an item
    // holds no list. An item read from a stored text keeps the mark it was
    // written with (data-ws-mark), so a line nobody touched is written back
    // as it was ("* " stays "* ", its indentation stays).
    var LISTS = {
        bullet: { tool: 'bullets', tag: 'UL', item: 'LI', className: 'ws-list', mark: '- ' },
        number: { tool: 'numbers', tag: 'OL', item: 'LI', className: 'ws-list', mark: '1. ' },
        quote: { tool: 'quote', tag: 'BLOCKQUOTE', item: 'DIV', className: 'ws-quote', mark: '> ' }
    };

    // One line as an item of a list or a line of a quote, or null: the twin
    // of ws_list_line() (includes/workspace/render.php). mark is the line
    // up to its words, words the rest as written. [^\n] and the explicit
    // white space stand for PCRE's "." and "\S", which JavaScript reads
    // wider.
    function listLine(line) {
        var text = String(line || '');
        var rtrim = function (words) { return words.replace(/[ \t\n\r\0\x0B]+$/, ''); };
        var shape = function (kind, number, indent, words) {
            return { kind: kind, number: number, indent: indent.length, text: rtrim(words), mark: text.slice(0, text.length - words.length), words: words };
        };
        var match = /^([ \t]*)>(?:[ \t]+([^\n]*))?$/.exec(text);

        if (match) {
            return shape('quote', 0, match[1], match[2] || '');
        }

        if (/^[ \t]*[-*][ \t]\[[ xX]\](?:[ \t]|$)/.test(text) || /^[ \t]*([-*_])(?:[ \t]*\1){2,}[ \t]*$/.test(text)) {
            return null;
        }

        match = /^([ \t]*)[-*][ \t]+([^ \t\n\x0B\f\r][^\n]*)$/.exec(text);

        if (match) {
            return shape('bullet', 0, match[1], match[2]);
        }

        match = /^([ \t]*)([1-9][0-9]{0,2})\.[ \t]+([^ \t\n\x0B\f\r][^\n]*)$/.exec(text);

        return match ? shape('number', parseInt(match[2], 10), match[1], match[3]) : null;
    }

    // The lines of running text grouped into text and lists: the twin of
    // ws_text_blocks(). Only a shape that cannot have meant anything else is
    // a list; every other shape stays text, as the server draws it. lines:
    // each line, or null for one that ends a paragraph (a block's).
    function textBlocks(lines) {
        var out = [];
        var count = lines.length;
        var blank = function (line) { return (line === null) || (String(line).trim() === ''); };
        var text = function (index) {
            var last = out[out.length - 1];

            if (last && (last.kind === 'text')) {
                last.lines.push(index);
            } else {
                out.push({ kind: 'text', lines: [index] });
            }
        };
        var i = 0;

        while (i < count) {
            if (blank(lines[i])) {
                text(i++);
                continue;
            }

            var end = i;

            while ((end + 1 < count) && !blank(lines[end + 1])) {
                end++;
            }

            var first = null;
            var items = [];
            var k;

            for (k = i; (k <= end) && (first === null); k++) {
                if (listLine(lines[k])) {
                    first = k;
                }
            }

            if (first !== null) {
                var lead = listLine(lines[first]);

                for (k = first; k <= end; k++) {
                    var item = listLine(lines[k]);

                    if (!item || (item.kind !== lead.kind) || ((lead.kind !== 'quote') && (item.indent !== lead.indent))
                        || ((item.kind === 'number') && (item.number !== items.length + 1))) {
                        items = [];
                        break;
                    }

                    items.push(item);
                }

                if (((lead.kind === 'number') && (items.length < 2))
                    || ((lead.kind === 'quote') && (items.map(function (one) { return one.text; }).join('') === ''))) {
                    items = [];
                }
            }

            if (!items.length) {
                first = end + 1;
            }

            for (k = i; k < first; k++) {
                text(k);
            }

            if (items.length) {
                var at = [];

                for (k = first; k <= end; k++) {
                    at.push(k);
                }

                out.push({ kind: items[0].kind, lines: at, items: items });
            }

            i = end + 1;
        }

        return out;
    }

    // A list written back, an item a line. items: { mark, words }, words as
    // markup. An item keeps its mark; one the box made takes the mark of the
    // item before it (its indent too, which the server needs to see one
    // list), numbers are counted again from 1, an empty item is left out, a
    // line break in an item makes an item of its own. In a quote an empty
    // line stays between two lines with words.
    function listMarkup(kind, items) {
        var lines = [];
        var model = '';

        (items || []).forEach(function (item) {
            if (item.mark) {
                model = model || item.mark;
            }
        });

        model = model || LISTS[kind].mark;

        items.forEach(function (item) {
            var mark = item.mark || model;

            String(item.words).replace(/\n+$/, '').split('\n').forEach(function (words) {
                if (words.trim() === '') {
                    if (kind === 'quote') {
                        lines.push({ empty: item.mark || '>' });
                    }

                    return;
                }

                if (kind === 'number') {
                    var parts = /^([ \t]*)([0-9]+)\.([ \t]+)$/.exec(mark) || ['', '', '1', ' '];
                    var number = lines.length + 1;

                    mark = (parseInt(parts[2], 10) === number) ? mark : parts[1] + number + '.' + parts[3];
                }

                // A lone ">" read from a quote takes a space before words.
                lines.push(mark + (/[ \t]$/.test(mark) ? '' : ' ') + words);
                model = mark;
            });
        });

        while (lines.length && lines[0].empty) {
            lines.shift();
        }

        while (lines.length && lines[lines.length - 1].empty) {
            lines.pop();
        }

        return lines.map(function (line) { return line.empty || line; }).join('\n');
    }

    // A text split into lines of words and the blocks among them; the lines
    // of a list or a quote one block.
    function parseBlocks(markup) {
        var lines = String(markup || '').replace(/\r\n?/g, '\n').split('\n');
        var out = [];

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            var heading = '';

            // A title right above a block goes with it.
            if (TITLE.test(line) && startsBlock(lines, i + 1)) {
                heading = line.trim() + '\n';
                i++;
                line = lines[i];
            }

            var language = fenceOpen(line);

            if (language !== null) {
                var code = [];
                var j = i + 1;

                while (j < lines.length && !fenceClose(lines[j])) {
                    code.push(lines[j]);
                    j++;
                }

                out.push({ kind: 'code', markup: heading + '```' + language + '\n' + code.join('\n') + '\n```' });
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

                out.push({ kind: 'table', markup: heading + rows.join('\n') });
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
                out.push({ kind: 'checklist', markup: heading + items.join('\n') });
                continue;
            }

            out.push({ kind: 'text', markup: line });
        }

        var lines = out.map(function (block) { return (block.kind === 'text') ? block.markup : null; });
        var grouped = [];

        textBlocks(lines).forEach(function (group) {
            if (group.kind === 'text') {
                group.lines.forEach(function (index) { grouped.push(out[index]); });
                return;
            }

            grouped.push({
                kind: 'list',
                list: group.kind,
                markup: group.lines.map(function (index) { return lines[index]; }).join('\n'),
                items: group.items
            });
        });

        return grouped;
    }

    // ── Pasted HTML → markup ───────────────────────────────────────────
    //
    // What another program puts on the clipboard as HTML (a page, Word,
    // Google Docs, a sheet) is read in a detached document and walked here:
    // only its words and the marks the box stores come over, written as the
    // stored markup, which the box then reads as it reads a saved text. No
    // element, attribute or style of the HTML reaches the box; a link keeps
    // only an http(s) or mailto address. The walk reads nodeType, nodeName,
    // nodeValue, childNodes and getAttribute() only, so a test can hand it
    // any tree.

    // Elements whose content is no words of the text: dropped whole.
    var PASTE_DROP = /^(SCRIPT|STYLE|TEMPLATE|NOSCRIPT|IFRAME|FRAME|FRAMESET|OBJECT|EMBED|HEAD|TITLE|META|LINK|BASE|SVG|MATH|CANVAS|AUDIO|VIDEO|IMG|PICTURE|SOURCE|INPUT|BUTTON|SELECT|TEXTAREA|OPTION)$/;

    // Elements that stand on lines of their own.
    var PASTE_BLOCKS = /^(P|DIV|H[1-6]|LI|BLOCKQUOTE|TR|TD|TH|CAPTION|THEAD|TBODY|TFOOT|SECTION|ARTICLE|ASIDE|HEADER|FOOTER|NAV|MAIN|ADDRESS|FIGURE|FIGCAPTION|DL|DT|DD|HR|BODY|HTML|CENTER|DETAILS|SUMMARY|FIELDSET|LEGEND|FORM)$/;

    // The addresses a link may keep: the link rule of inlineNodes().
    var PASTE_LINK = /^(?:https?:\/\/|mailto:)[^\s()<>"]{1,1000}$/;

    var PASTE_MARKS = ['bold', 'italic', 'strike', 'highlight'];

    // What an element's style attribute says of the marks: Google Docs marks
    // with font-weight and font-style on spans (its outer <b> says normal),
    // Word with background and mso-highlight, and Word's list paragraphs
    // carry mso-list (their bullet in a span of mso-list:Ignore).
    function pasteStyle(node) {
        var style = String((node.getAttribute && node.getAttribute('style')) || '').toLowerCase();
        var read = function (name) {
            var match = new RegExp('(?:^|;)\\s*' + name + '\\s*:\\s*([^;]+)').exec(style);

            return match ? match[1].trim() : '';
        };
        var weight = read('font-weight');
        var slant = read('font-style');
        var space = read('white-space');
        var list = read('mso-list');
        var colour = (read('background-color') || read('background')).replace(/\s*([(),])\s*/g, '$1').split(/\s+/)[0];
        var marker = read('mso-highlight');

        return {
            bold: /^(bold|bolder|[6-9]00)$/.test(weight) ? true : (/^(normal|lighter|[1-5]00)$/.test(weight) ? false : null),
            italic: /^(italic|oblique)/.test(slant) ? true : ((slant === 'normal') ? false : null),
            strike: /line-through/.test(read('text-decoration') + ' ' + read('text-decoration-line')),
            highlight: (!!marker && marker !== 'none') || (/^(#|rgb|hsl|[a-z]+$)/.test(colour)
                && ['transparent', 'none', 'inherit', 'initial', 'unset', 'white', 'window', '#fff', '#ffffff', 'rgb(255,255,255)', 'rgba(0,0,0,0)', 'rgba(255,255,255,1)'].indexOf(colour) === -1),
            keep: space ? /^pre/.test(space) : null,
            ignore: list === 'ignore',
            list: !!list && list !== 'ignore'
        };
    }

    // The marks an element puts on what it holds; its style wins over its
    // tag (Google Docs' <b style="font-weight:normal"> holds the whole text).
    function pasteMarks(name, style, ctx) {
        var next = { bold: ctx.bold, italic: ctx.italic, strike: ctx.strike, highlight: ctx.highlight, keep: ctx.keep, href: ctx.href };

        if (/^(B|STRONG|H[1-6])$/.test(name)) {
            next.bold = true;
        }

        if (/^(I|EM|CITE|DFN|VAR)$/.test(name)) {
            next.italic = true;
        }

        next.bold = (style.bold === null) ? next.bold : style.bold;
        next.italic = (style.italic === null) ? next.italic : style.italic;
        next.strike = next.strike || style.strike || /^(S|STRIKE|DEL)$/.test(name);
        next.highlight = next.highlight || style.highlight || (name === 'MARK');
        next.keep = (style.keep === null) ? next.keep : style.keep;

        return next;
    }

    // The words of an element as written: white space kept, a <br> or a
    // block a new line (code, a Word list's bullet).
    function pasteWords(node) {
        if (node.nodeType === 3) {
            return String(node.nodeValue || '').replace(/ /g, ' ').replace(/\r\n?/g, '\n');
        }

        var name = (node.nodeType === 1) ? String(node.nodeName).toUpperCase() : '';

        if (!name || PASTE_DROP.test(name)) {
            return '';
        }

        if (name === 'BR') {
            return '\n';
        }

        var words = '';

        Array.prototype.forEach.call(node.childNodes || [], function (child) {
            words += pasteWords(child);
        });

        return (PASTE_BLOCKS.test(name) && words !== '' && words.slice(-1) !== '\n') ? words + '\n' : words;
    }

    // Runs side by side with the same marks and link written as one, then
    // each link around the marked words it holds.
    function pasteRunsMarkup(runs) {
        var merged = [];

        runs.forEach(function (run) {
            var last = merged[merged.length - 1];
            var same = !!last && !last.code && !run.code && (last.href === run.href) && PASTE_MARKS.every(function (key) { return last[key] === run[key]; });

            if (same) {
                last.text += run.text;
            } else {
                merged.push({ text: run.text, code: run.code, href: run.href, bold: run.bold, italic: run.italic, strike: run.strike, highlight: run.highlight });
            }
        });

        var out = '';
        var i = 0;

        while (i < merged.length) {
            var href = merged[i].href;
            var inner = '';

            for (; i < merged.length && merged[i].href === href; i++) {
                inner += pasteRunMarkup(merged[i]);
            }

            out += href ? pasteLink(inner, href) : inner;
        }

        return out;
    }

    // One run's words in its marks. A mark whose sign is in the words is
    // left off, as serializeNode() does for a highlight: the stored text
    // could not be read back.
    function pasteRunMarkup(run) {
        var text = run.text;

        if (run.code) {
            var core = text.trim();
            var lead = /^\s*/.exec(text)[0];

            if (core !== '' && core.length <= 300 && !/[`\n]/.test(core)) {
                return lead + '`' + core + '`' + text.slice(lead.length + core.length);
            }

            return text;
        }

        if (/^\s*$/.test(text)) {
            return text;
        }

        var signs = { highlight: ['=', '=='], strike: ['~', '~~'], italic: ['_', '_'], bold: ['*', '**'] };

        ['highlight', 'strike', 'italic', 'bold'].forEach(function (key) {
            if (run[key] && text.indexOf(signs[key][0]) === -1) {
                text = wrapLines(text, signs[key][1]);
            }
        });

        return text;
    }

    function pasteLink(inner, href) {
        var lead = /^\s*/.exec(inner)[0];
        var core = inner.replace(/\s+/g, ' ').trim();

        if (core === '' || core.length > 300 || /[[\]]/.test(core)) {
            return inner;
        }

        return lead + '[' + core + '](' + href + ')' + (/\s$/.test(inner) ? ' ' : '');
    }

    // The words gathered so far as lines. Runs of white space alone (the
    // gaps between the source's tags) make no line; a no-break space or a
    // <br> does (Word's empty paragraph).
    function pasteFlush(out) {
        var runs = out.runs;
        var keep = out.keep;

        out.runs = [];
        out.keep = false;

        if (!runs.some(function (run) { return run.solid; })) {
            return;
        }

        pasteRunsMarkup(runs).replace(/\n$/, '').split('\n').forEach(function (line) {
            out.lines.push(keep ? line.replace(/\s+$/, '') : line.trim());
        });

        out.wordList = null;
    }

    function pasteOut() {
        return { lines: [], runs: [], keep: false, wordList: null };
    }

    // The words of a part (an item, a cell) on one line.
    function pasteLine(nodes, ctx) {
        var sub = pasteOut();

        nodes.forEach(function (node) { pasteWalk(node, sub, ctx); });
        pasteFlush(sub);

        return sub.lines.join(' ').replace(/\s+/g, ' ').trim();
    }

    function pasteWalk(node, out, ctx) {
        if (node.nodeType === 3) {
            var raw = String(node.nodeValue || '');
            var text = raw.replace(/ /g, ' ');

            text = ctx.keep ? text.replace(/\r\n?/g, '\n') : text.replace(/\s+/g, ' ');

            if (text !== '') {
                out.runs.push({ text: text, solid: /[^\s]| /.test(raw), code: false, href: ctx.href, bold: ctx.bold, italic: ctx.italic, strike: ctx.strike, highlight: ctx.highlight });
                out.keep = out.keep || ctx.keep;
            }

            return;
        }

        var name = (node.nodeType === 1) ? String(node.nodeName).toUpperCase() : '';

        if (!name || PASTE_DROP.test(name)) {
            return;
        }

        var style = pasteStyle(node);

        if (style.ignore) {
            return;
        }

        if (name === 'BR') {
            out.runs.push({ text: '\n', solid: true, code: false, href: ctx.href, bold: false, italic: false, strike: false, highlight: false });
            return;
        }

        var inner = pasteMarks(name, style, ctx);
        var children = Array.prototype.slice.call(node.childNodes || []);

        if (name === 'A') {
            var href = String(node.getAttribute('href') || '').trim();

            inner.href = PASTE_LINK.test(href) ? href : ctx.href;
        }

        if (/^(CODE|KBD|SAMP|TT)$/.test(name)) {
            var code = pasteWords(node).replace(/\s+/g, ' ');

            if (code !== '') {
                out.runs.push({ text: code, solid: /\S/.test(code), code: true, href: inner.href, bold: false, italic: false, strike: false, highlight: false });
            }

            return;
        }

        if (name === 'PRE') {
            pasteFlush(out);

            var lines = pasteWords(node).replace(/\n+$/, '').split('\n');

            if (lines.join('') !== '') {
                Array.prototype.push.apply(out.lines, lines.some(function (line) { return /^\s*```/.test(line); }) ? lines : ['```'].concat(lines, ['```']));
            }

            return;
        }

        if (name === 'UL' || name === 'OL') {
            pasteFlush(out);
            pasteItems(node, out, inner, { numbered: name === 'OL', count: 0 });
            return;
        }

        if (name === 'BLOCKQUOTE') {
            pasteFlush(out);

            var quote = pasteOut();

            children.forEach(function (child) { pasteWalk(child, quote, inner); });
            pasteFlush(quote);
            quote.lines.forEach(function (line) { out.lines.push(line === '' ? '>' : '> ' + line); });
            return;
        }

        if (name === 'TABLE') {
            pasteFlush(out);

            var table = pasteTableRows(pasteRows(node, inner));

            if (table !== '') {
                Array.prototype.push.apply(out.lines, table.split('\n'));
                return;
            }
        }

        // A Word list paragraph: its bullet tells a numbered list from a
        // bulleted one; paragraphs one after another make one list.
        if (style.list) {
            pasteFlush(out);

            var bullet = '';

            (function find(at) {
                Array.prototype.forEach.call(at.childNodes || [], function (child) {
                    if (!bullet && child.nodeType === 1) {
                        if (pasteStyle(child).ignore) {
                            bullet = pasteWords(child).trim() || ' ';
                        } else {
                            find(child);
                        }
                    }
                });
            })(node);

            var numbered = /^\(?[0-9A-Za-z]{1,3}[.)]/.test(bullet);
            var words = pasteLine(children, inner);
            var list = (out.wordList && out.wordList.numbered === numbered) ? out.wordList : { numbered: numbered, count: 0 };

            if (words !== '') {
                out.lines.push((numbered ? (++list.count) + '. ' : '- ') + words);
            }

            out.wordList = list;
            return;
        }

        if (name === 'LI') {
            pasteFlush(out);
            pasteItem(node, out, inner, { numbered: false, count: 0 });
            return;
        }

        var block = PASTE_BLOCKS.test(name);

        if (block) {
            pasteFlush(out);
        }

        children.forEach(function (child) { pasteWalk(child, out, inner); });

        if (block) {
            pasteFlush(out);
        }
    }

    // A list's items, one level: a list inside an item goes on as items of
    // the same list (the box keeps one level).
    function pasteItems(node, out, ctx, list) {
        Array.prototype.forEach.call(node.childNodes || [], function (child) {
            var name = (child.nodeType === 1) ? String(child.nodeName).toUpperCase() : '';

            if (name === 'LI') {
                pasteItem(child, out, pasteMarks(name, pasteStyle(child), ctx), list);
            } else if (name === 'UL' || name === 'OL') {
                pasteItems(child, out, ctx, list);
            }
        });
    }

    function pasteItem(item, out, ctx, list) {
        var check = pasteCheck(item);
        var part = [];
        var emit = function () {
            var words = pasteLine(part, ctx);

            part = [];

            if (words === '') {
                return;
            }

            if (check !== null) {
                out.lines.push('- [' + (check ? 'x' : ' ') + '] ' + words);
            } else {
                out.lines.push((list.numbered ? (++list.count) + '. ' : '- ') + words);
            }
        };

        Array.prototype.forEach.call(item.childNodes || [], function (child) {
            var name = (child.nodeType === 1) ? String(child.nodeName).toUpperCase() : '';

            if (name === 'UL' || name === 'OL') {
                emit();
                pasteItems(child, out, ctx, list);
            } else {
                part.push(child);
            }
        });

        emit();
    }

    // An item of a checklist: aria-checked on the item (Google Docs), or a
    // checkbox in it before any list inside it (GitHub, Notion, the web).
    // null for an ordinary item.
    function pasteCheck(item) {
        var aria = item.getAttribute('aria-checked');

        if (aria === 'true' || aria === 'false') {
            return aria === 'true';
        }

        var found = null;

        (function find(at) {
            Array.prototype.forEach.call(at.childNodes || [], function (child) {
                var name = (child.nodeType === 1) ? String(child.nodeName).toUpperCase() : '';

                if (found !== null || !name || name === 'UL' || name === 'OL') {
                    return;
                }

                if (name === 'INPUT' && String(child.getAttribute('type') || '').toLowerCase() === 'checkbox') {
                    found = child.getAttribute('checked') !== null;
                } else {
                    find(child);
                }
            });
        })(item);

        return found;
    }

    // The rows of a table, each a list of its cells' words.
    function pasteRows(table, ctx) {
        var rows = [];

        (function find(at) {
            Array.prototype.forEach.call(at.childNodes || [], function (child) {
                var name = (child.nodeType === 1) ? String(child.nodeName).toUpperCase() : '';

                if (name === 'TR') {
                    var cells = [];

                    Array.prototype.forEach.call(child.childNodes || [], function (cell) {
                        var kind = (cell.nodeType === 1) ? String(cell.nodeName).toUpperCase() : '';

                        if (kind === 'TD' || kind === 'TH') {
                            cells.push(pasteLine(Array.prototype.slice.call(cell.childNodes || []), pasteMarks(kind, pasteStyle(cell), ctx)));
                        }
                    });

                    rows.push(cells);
                } else if (/^(THEAD|TBODY|TFOOT)$/.test(name)) {
                    find(child);
                }
            });
        })(table);

        return rows;
    }

    // Rows of cells as the box's table, its first row the head; '' when it
    // is no table the table window can hold (a layout table of one column,
    // one row, or more than the window's 12 columns and 200 rows).
    function pasteTableRows(rows) {
        rows = rows.filter(function (row) { return row.some(function (cell) { return cell !== ''; }); });

        var width = rows.reduce(function (most, row) { return Math.max(most, row.length); }, 0);

        if (rows.length < 2 || rows.length > 201 || width < 2 || width > 12) {
            return '';
        }

        var line = function (cells) {
            var all = cells.slice();

            while (all.length < width) {
                all.push('');
            }

            return '| ' + all.map(function (cell) { return String(cell).replace(/\s+/g, ' ').trim().replace(/\|/g, '\\|'); }).join(' | ') + ' |';
        };
        var rule = [];

        while (rule.length < width) {
            rule.push('---');
        }

        return [line(rows[0]), '| ' + rule.join(' | ') + ' |'].concat(rows.slice(1).map(line)).join('\n');
    }

    // A whole pasted document (its body) as the stored markup: empty lines
    // run together, an empty line after a list that words follow (the
    // server draws a list only where nothing follows it in its paragraph).
    function pasteMarkup(root) {
        var out = pasteOut();

        pasteWalk(root, out, { bold: false, italic: false, strike: false, highlight: false, keep: false, href: '' });
        pasteFlush(out);

        var lines = [];
        var fence = false;

        out.lines.forEach(function (line) {
            var last = lines.length ? lines[lines.length - 1] : null;

            if (fence || (fenceOpen(line) !== null)) {
                fence = !(fence && fenceClose(line));
                lines.push(line);
                return;
            }

            if (line === '' && (last === null || last === '')) {
                return;
            }

            if (line !== '' && last && listLine(last) && !listLine(line)) {
                lines.push('');
            }

            lines.push(line);
        });

        while (lines.length && lines[lines.length - 1] === '') {
            lines.pop();
        }

        return lines.join('\n');
    }

    // A range copied from a sheet (Excel, Google Sheets, LibreOffice): rows
    // of tab-separated cells, two rows at least, every row as wide; as the
    // box's table, or '' for any other text.
    function pasteSheet(text) {
        var lines = String(text || '').replace(/\r\n?/g, '\n').replace(/\n+$/, '').split('\n');
        var width = lines[0].split('\t').length;

        if (lines.length < 2 || width < 2 || lines.some(function (line) { return line.split('\t').length !== width; })) {
            return '';
        }

        return pasteTableRows(lines.map(function (line) { return line.split('\t'); }));
    }

    // A pasted text that is one address alone, which links the words it is
    // pasted over; '' for any other text.
    function pastedUrl(text) {
        var url = String(text || '').trim();

        return PASTE_LINK.test(url) ? url : '';
    }

    // The blocks of a stored text (parseBlocks()) as the box's nodes.
    function markupNodes(blocks, labels) {
        var fragment = document.createDocumentFragment();

        blocks.forEach(function (block, index) {
            if (block.kind === 'text') {
                inlineNodes(block.markup, labels, fragment);

                if (index < blocks.length - 1) {
                    fragment.appendChild(el('br'));
                }
            } else if (block.kind === 'list') {
                // A list ends its line itself: no break after it.
                var list = el(LISTS[block.list].tag.toLowerCase(), LISTS[block.list].className);

                block.items.forEach(function (one) {
                    list.appendChild(fillEmpty(inlineNodes(one.words, labels, itemNode(block.list, one.mark))));
                });

                fragment.appendChild(list);
            } else {
                fragment.appendChild(blockNode(block.kind, block.markup));
                fragment.appendChild(el('br'));
            }
        });

        return fragment;
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

        var kind = listKind(node);

        if (kind && !singleLine) {
            var items = Array.prototype.map.call(node.childNodes, function (item) {
                return { mark: (item.nodeType === 1) ? (item.getAttribute('data-ws-mark') || '') : '', words: serializeNode(item, '', false) };
            });
            var written = listMarkup(kind, items);
            var start = (sofar === '' || /\n$/.test(sofar)) ? '' : '\n';

            // Words straight after a list would end it on the server, which
            // draws a list only where nothing follows it in its paragraph:
            // an empty line keeps it one.
            return (written === '') ? start : start + written + '\n' + (wordsAfter(node) ? '\n' : '');
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

        // Words holding a = cannot be read back as one highlight: they are
        // written without its signs.
        if (tag === 'MARK') {
            return (inner.indexOf('=') === -1) ? wrapLines(inner, '==') : inner;
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

    // Which list an element of the box is (bullet, number, quote), or ''.
    function listKind(node) {
        if (!node || (node.nodeType !== 1)) {
            return '';
        }

        for (var kind in LISTS) {
            if (Object.prototype.hasOwnProperty.call(LISTS, kind) && (LISTS[kind].tag === node.tagName)) {
                return kind;
            }
        }

        return '';
    }

    // The next line-filling node after a list: a line break, a card or
    // nothing ends its line anyway.
    function wordsAfter(list) {
        var next = list.nextSibling;

        while (next && (((next.nodeType === 3) && (next.nodeValue.replace(/\u200b/g, '') === '')) || ((next.nodeType === 1) && next.hasAttribute('data-ws-skip')))) {
            next = next.nextSibling;
        }

        return !!next && !((next.nodeType === 1) && ((next.tagName === 'BR') || next.classList.contains('ws-rich-block')));
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
        var parts = titleSplit(markup);
        var summary = blockSummaryOf(kind, parts.markup);

        if (parts.title) {
            summary.label = parts.title + ' · ' + summary.label;
        }

        return summary;
    }

    function blockSummaryOf(kind, markup) {
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
        var language = (fenceOpen(titleSplit(markup).markup.split('\n')[0]) || '').toLowerCase();

        return CALC_LANGUAGES.indexOf(language) !== -1;
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

    // ── Marks: bold, italic, strikethrough, highlight, code, links ─────
    //
    // Made on the nodes themselves rather than with document.execCommand():
    // the browsers' commands differ (<b> or <strong>, spans with styles),
    // keep undo steps of their own that the box's history cannot follow, and
    // are deprecated. Only what the markup carries is made: <b>, <i>, <s>,
    // <mark>, <code> and <a href>; <strong>, <em>, <del> from elsewhere are read as
    // the same marks.

    var MARKS = {
        bold: { tag: 'b', match: /^(B|STRONG)$/ },
        italic: { tag: 'i', match: /^(I|EM)$/ },
        strike: { tag: 's', match: /^(S|STRIKE|DEL)$/ },
        highlight: { tag: 'mark', match: /^MARK$/ },
        code: { tag: 'code', match: /^CODE$/ },
        link: { tag: 'a', match: /^A$/ }
    };

    // The marks a caret with nothing selected keeps for what is typed next.
    var TYPED_MARKS = ['bold', 'italic', 'strike', 'highlight'];

    function markName(node) {
        if (!node || node.nodeType !== 1) {
            return '';
        }

        for (var name in MARKS) {
            if (Object.prototype.hasOwnProperty.call(MARKS, name) && MARKS[name].match.test(node.tagName)) {
                return name;
            }
        }

        return '';
    }

    // The element of a mark around a node (or the node itself), within the
    // surface.
    function markOf(node, surface, name) {
        for (var at = (node && node.nodeType === 1) ? node : (node ? node.parentNode : null); at && at !== surface; at = at.parentNode) {
            if (at.nodeType === 1 && MARKS[name].match.test(at.tagName)) {
                return at;
            }
        }

        return null;
    }

    // Chips, cards and the results drawn beside a line are no text to mark.
    function sealed(node, surface) {
        var element = (node.nodeType === 1) ? node : node.parentNode;
        var found = (element && element.closest) ? element.closest('[data-token], .ws-rich-block, [data-ws-skip], [contenteditable="false"]') : null;

        return !!(found && (found !== surface) && surface.contains(found));
    }

    function shows(node) {
        return /[^\s\u200b]/.test(node.nodeValue);
    }

    // Something worth keeping in a piece cut out of a mark: words, a line
    // break, a chip or a card (an emptied copy of a mark is not).
    function filled(piece) {
        return (piece.textContent || '').replace(/\u200b/g, '') !== ''
            || !!(piece.querySelector && piece.querySelector('br, [data-token], .ws-rich-block'));
    }

    // The text nodes a range covers, without the ones it only touches at an
    // end and the sealed ones.
    function textsIn(surface, range) {
        var root = range.commonAncestorContainer;
        var found = [];

        if (root.nodeType === 3) {
            return ((range.startOffset < range.endOffset) && !sealed(root, surface)) ? [root] : [];
        }

        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);

        while (walker.nextNode()) {
            var node = walker.currentNode;

            if ((node.nodeValue === '') || !range.intersectsNode(node)) {
                continue;
            }

            if (((node === range.startContainer) && (range.startOffset >= node.nodeValue.length)) || ((node === range.endContainer) && (range.endOffset === 0))) {
                continue;
            }

            if (!sealed(node, surface)) {
                found.push(node);
            }
        }

        return found;
    }

    // The ends of a range moved to the edges of the text nodes they fall in,
    // so that whole nodes are marked or unmarked.
    function splitEdges(range) {
        var end = range.endContainer;

        if ((end.nodeType === 3) && (range.endOffset > 0) && (range.endOffset < end.nodeValue.length)) {
            end.splitText(range.endOffset);
        }

        var start = range.startContainer;

        if ((start.nodeType === 3) && (range.startOffset > 0) && (range.startOffset < start.nodeValue.length)) {
            range.setStart(start.splitText(range.startOffset), 0);
        }
    }

    // backward: the selection is put back the way it was made, its focus at
    // the start, so that Shift+Arrow goes on moving the end it moved before.
    function select(startNode, startOffset, endNode, endOffset, backward) {
        var range = document.createRange();
        var selection = window.getSelection();

        range.setStart(startNode, startOffset);
        range.setEnd(endNode, endOffset);

        if (backward && selection.setBaseAndExtent) {
            selection.setBaseAndExtent(endNode, endOffset, startNode, startOffset);
            return range;
        }

        selection.removeAllRanges();
        selection.addRange(range);

        return range;
    }

    // Whether the selection was made backwards: its focus (the end that
    // Shift+Arrow moves) stands before its anchor. A Range has no direction,
    // so a selection rebuilt from one would turn forwards.
    function selectionBackward() {
        var selection = window.getSelection();

        if (!selection || !selection.rangeCount || selection.isCollapsed || !selection.anchorNode || !selection.focusNode) {
            return false;
        }

        var range = document.createRange();

        range.setStart(selection.anchorNode, selection.anchorOffset);
        // An end set before the start collapses the range onto it.
        range.setEnd(selection.focusNode, selection.focusOffset);

        return range.collapsed;
    }

    // A text node taken out of a mark around it: the mark is cut in two where
    // the node stands, and the marks between the two stay on the node.
    function liftOut(node, mark) {
        var head = document.createRange();
        var tail = document.createRange();
        var parent = mark.parentNode;

        head.setStart(mark, 0);
        head.setEndBefore(node);
        tail.setStartAfter(node);
        tail.setEnd(mark, mark.childNodes.length);

        var before = head.extractContents();
        var after = tail.extractContents();

        if (filled(before)) {
            var first = mark.cloneNode(false);
            first.appendChild(before);
            parent.insertBefore(first, mark);
        }

        if (filled(after)) {
            var last = mark.cloneNode(false);
            last.appendChild(after);
            parent.insertBefore(last, mark.nextSibling);
        }

        while (mark.firstChild) {
            parent.insertBefore(mark.firstChild, mark);
        }

        parent.removeChild(mark);
    }

    function wrapIn(node, name, href) {
        var mark = document.createElement(MARKS[name].tag);

        if (href) {
            mark.setAttribute('href', href);
        }

        node.parentNode.insertBefore(mark, node);
        mark.appendChild(node);

        return mark;
    }

    function sameMark(a, b) {
        return !!(a && b && (a.nodeType === 1) && (b.nodeType === 1) && (a.tagName === b.tagName) && markName(a)
            && (a.attributes.length === b.attributes.length) && ((a.getAttribute('href') || '') === (b.getAttribute('href') || '')));
    }

    // A new mark joined with the same mark beside it: <b>a</b><b>b</b> is
    // one <b>ab</b>, as the markup will read it anyway. Answers the mark
    // that is left.
    function joinMark(mark) {
        if (!mark.parentNode) {
            return mark;
        }

        while (mark.previousSibling && (mark.previousSibling.nodeType === 3) && (mark.previousSibling.nodeValue === '')) {
            mark.previousSibling.remove();
        }

        var previous = mark.previousSibling;

        if (sameMark(previous, mark)) {
            while (mark.firstChild) {
                previous.appendChild(mark.firstChild);
            }

            mark.remove();
            mark = previous;
        }

        while (mark.nextSibling && (mark.nextSibling.nodeType === 3) && (mark.nextSibling.nodeValue === '')) {
            mark.nextSibling.remove();
        }

        var next = mark.nextSibling;

        if (sameMark(mark, next)) {
            while (next.firstChild) {
                mark.appendChild(next.firstChild);
            }

            next.remove();
        }

        return mark;
    }

    // Every pair of same marks side by side under a node joined, inner marks
    // too. Letters typed into a mark of their own and a mark lifted off part
    // of a run both leave <i>y</i><i>def</i>, which the markup would write as
    // _y__def_ and every reader would show as underscores.
    function joinMarks(root) {
        for (var child = root.firstChild; child; child = child.nextSibling) {
            if (child.nodeType !== 1) {
                continue;
            }

            if (markName(child)) {
                child = joinMark(child);
            }

            joinMarks(child);
        }
    }

    // ── Marks waiting for what is typed next ──
    //
    // A mark turned on or off with nothing selected changes nothing yet: it
    // waits for the next letters typed at that caret, which are put in it
    // (or out of it), or for the next word composed there. A caret moved
    // elsewhere forgets it. One surface at a time holds a caret, so one
    // surface at a time has marks waiting.

    var waitingIn = null;

    function caretCount(surface, range) {
        var head = document.createRange();

        head.setStart(surface, 0);
        head.setEnd(range.startContainer, range.startOffset);

        return plainOf(head.cloneContents()).length;
    }

    function waiting(surface, range) {
        var held = surface.wsMarks;

        if (!held || !range || !surface.contains(range.startContainer)) {
            return null;
        }

        // A word being composed (an IME, the dead keys of a Mac, a phone's
        // keyboard, which composes every word) moves the caret on with each
        // letter: the marks wait for the whole word, until composedMarked().
        if (surface.wsComposing) {
            return held.marks;
        }

        if (!range.collapsed || (caretCount(surface, range) !== held.at)) {
            return null;
        }

        return held.marks;
    }

    function forgetWaiting() {
        var surface = waitingIn;

        waitingIn = null;

        if (surface) {
            surface.wsMarks = null;

            if (surface.wsEditor) {
                surface.wsEditor.notify();
            }
        }
    }

    function toggleWaiting(surface, range, name) {
        var marks = waiting(surface, range) || {};
        var around = !!markOf(range.startContainer, surface, name);
        var now = Object.prototype.hasOwnProperty.call(marks, name) ? marks[name] : around;

        if (waitingIn && (waitingIn !== surface)) {
            forgetWaiting();
        }

        marks[name] = !now;

        // Back to what the caret has anyway: nothing waits for that.
        if (marks[name] === around) {
            delete marks[name];
        }

        surface.wsMarks = Object.keys(marks).length ? { marks: marks, at: caretCount(surface, range) } : null;
        waitingIn = surface.wsMarks ? surface : null;
    }

    // The letters typed at a caret with marks waiting: put in the marks
    // wanted, out of the ones turned off. true when they were put in.
    function typeMarked(surface, text) {
        var range = selectionRange();
        var marks = waiting(surface, range);

        forgetWaiting();

        if (!marks) {
            return false;
        }

        var want = {};

        TYPED_MARKS.forEach(function (name) {
            want[name] = Object.prototype.hasOwnProperty.call(marks, name) ? marks[name] : !!markOf(range.startContainer, surface, name);
        });

        // The outermost mark around the caret that is turned off: the letters
        // go between its two halves.
        var outer = null;

        for (var at = (range.startContainer.nodeType === 1) ? range.startContainer : range.startContainer.parentNode; at && (at !== surface); at = at.parentNode) {
            var name = markName(at);

            if ((TYPED_MARKS.indexOf(name) !== -1) && !want[name]) {
                outer = at;
            }
        }

        var spot = range.cloneRange();
        var lastItem = (range.startContainer === surface) ? boundaryItem(surface, range.startContainer, range.startOffset) : null;

        // A caret straight after a list that ends the box types into its
        // last item.
        if (lastItem) {
            spot.selectNodeContents(lastItem);
            spot.collapse(false);

            if (isBreak(lastItem.lastChild)) {
                spot.setStartBefore(lastItem.lastChild);
                spot.collapse(true);
            }
        }

        if (outer) {
            var tail = document.createRange();

            tail.setStart(range.startContainer, range.startOffset);
            tail.setEnd(outer, outer.childNodes.length);

            var rest = tail.extractContents();

            if (filled(rest)) {
                var copy = outer.cloneNode(false);
                copy.appendChild(rest);
                outer.parentNode.insertBefore(copy, outer.nextSibling);
            }

            spot = document.createRange();
            spot.setStartAfter(outer);
            spot.collapse(true);

            if (!filled(outer)) {
                outer.remove();
            }
        }

        var typed = document.createTextNode(text);
        var top = typed;

        TYPED_MARKS.forEach(function (name) {
            if (want[name] && !markOf(spot.startContainer, surface, name)) {
                var mark = document.createElement(MARKS[name].tag);

                mark.appendChild(top);
                top = mark;
            }
        });

        spot.insertNode(top);
        joinMarks(surface);
        select(typed, typed.nodeValue.length, typed, typed.nodeValue.length);

        return true;
    }

    // The place just before the character that stands a count of characters
    // from the start of a surface, counted as caretCount() counts them
    // (plainOf(): chips, cards and line breaks one each, a line before a
    // block, results beside a line none); null when none stands there.
    function placeOf(surface, count) {
        var seen = 0;
        var spot = null;

        function walk(parent) {
            var level = { length: 0, last: '' };

            for (var i = 0; (i < parent.childNodes.length) && !spot; i++) {
                var child = parent.childNodes[i];

                if (child.nodeType === 3) {
                    var size = child.nodeValue.length;

                    if (count < seen + size) {
                        spot = [child, count - seen];
                    } else if (size) {
                        seen += size;
                        level.length += size;
                        level.last = child.nodeValue.charAt(size - 1);
                    }

                    continue;
                }

                if ((child.nodeType !== 1) || child.hasAttribute('data-ws-skip')) {
                    continue;
                }

                var one = child.hasAttribute('data-token') || child.classList.contains('ws-rich-block') || (child.tagName === 'BR');
                var line = !one && BLOCKS.test(child.tagName) && (level.length > 0) && (level.last !== '\n');

                if ((one || line) && (count === seen)) {
                    spot = [parent, i];
                    continue;
                }

                if (one || line) {
                    seen += 1;
                    level.length += 1;
                    level.last = (one && (child.tagName !== 'BR')) ? OBJECT : '\n';
                }

                if (!one) {
                    var inner = walk(child);

                    level.length += inner.length;
                    level.last = inner.length ? inner.last : level.last;
                }
            }

            return level;
        }

        walk(surface);

        return spot;
    }

    // The word a composition has just left where marks waited for it: taken
    // out and typed again by typeMarked(), so that it carries the marks as
    // letters typed one by one would. It stays part of the typing around it
    // in the undo history. true when it was typed again.
    function composedMarked(surface) {
        var held = surface.wsMarks;
        var range = selectionRange();

        if (!held) {
            return false;
        }

        if (!range || !range.collapsed || !surface.contains(range.startContainer)) {
            forgetWaiting();
            return false;
        }

        var end = caretCount(surface, range);

        // Nothing came of it (the composition was cancelled): the marks go on
        // waiting at the caret.
        if (end === held.at) {
            return false;
        }

        var start = (end > held.at) ? placeOf(surface, held.at) : null;
        var run = document.createRange();

        if (start) {
            run.setStart(start[0], start[1]);
            run.setEnd(range.startContainer, range.startOffset);
        }

        // Only words: a composition makes no line break, chip or card, and
        // a result drawn beside a line is no part of it.
        var text = start ? run.toString() : '';

        if ((text === '') || (text.length !== end - held.at) || (plainOf(run.cloneContents()) !== text.replace(/\u00a0/g, ' '))) {
            forgetWaiting();
            return false;
        }

        run.deleteContents();
        select(run.startContainer, run.startOffset, run.startContainer, run.startOffset);

        return typeMarked(surface, text);
    }

    // ── Putting marks on and off ──

    // Which marks a caret or a selection has: a caret by the marks around it
    // and the ones waiting there, a selection by whether all its words carry
    // them.
    function marksAt(surface, range) {
        var state = { bold: false, italic: false, strike: false, highlight: false, code: false, link: false };

        if (!surface || !range || !surface.contains(range.startContainer) || !surface.contains(range.endContainer)) {
            return state;
        }

        if (range.collapsed) {
            var marks = waiting(surface, range) || {};

            Object.keys(state).forEach(function (name) {
                state[name] = Object.prototype.hasOwnProperty.call(marks, name) ? !!marks[name] : !!markOf(range.startContainer, surface, name);
            });

            return state;
        }

        var texts = textsIn(surface, range).filter(shows);

        Object.keys(state).forEach(function (name) {
            state[name] = (texts.length > 0) && texts.every(function (node) { return !!markOf(node, surface, name); });
        });

        return state;
    }

    // Code holds its words as they are: the selection's text in one <code>,
    // or the code the selection is in back to plain text.
    function toggleCode(surface, range, backward) {
        var code = insideCode(range);

        if (code && (code !== surface) && surface.contains(code)) {
            var plain = document.createTextNode(code.textContent);

            code.parentNode.replaceChild(plain, code);
            select(plain, 0, plain, plain.nodeValue.length, backward);

            return true;
        }

        // One <code> holds no line: none is made over two items.
        if (range.collapsed || (itemAt(surface, range.startContainer) !== itemAt(surface, range.endContainer))) {
            return false;
        }

        var text = range.toString().replace(/\n/g, ' ');
        var core = text.trim();

        if (core === '') {
            return false;
        }

        // A double-click takes the space after the word too; it stays outside.
        var working = range.cloneRange();
        var piece = document.createDocumentFragment();
        var fresh = el('code', '', core);
        var lead = /^\s*/.exec(text)[0];
        var trail = /\s*$/.exec(text)[0];

        if (lead !== '') {
            piece.appendChild(document.createTextNode(lead));
        }

        piece.appendChild(fresh);

        if (trail !== '') {
            piece.appendChild(document.createTextNode(trail));
        }

        working.deleteContents();
        working.insertNode(piece);

        // Still selected, so the bar can take it off again.
        select(fresh.firstChild, 0, fresh.firstChild, fresh.firstChild.nodeValue.length, backward);

        return true;
    }

    // A mark on the selection, or off it when all its words have it already.
    // With nothing selected the mark waits for what is typed next; code takes
    // off the code around the caret. true when the surface changed.
    function applyMark(surface, name) {
        var range = selectionRange();

        if (!range || !MARKS[name] || (name === 'link') || !surface.contains(range.startContainer) || !surface.contains(range.endContainer)) {
            return false;
        }

        var backward = selectionBackward();

        if (name === 'code') {
            return toggleCode(surface, range, backward);
        }

        if (range.collapsed) {
            toggleWaiting(surface, range, name);
            return false;
        }

        var off = marksAt(surface, range)[name];

        range = range.cloneRange();
        splitEdges(range);

        var texts = textsIn(surface, range);

        if (!texts.length) {
            return false;
        }

        if (off) {
            texts.forEach(function (node) {
                var mark;

                while ((mark = markOf(node, surface, name))) {
                    liftOut(node, mark);
                }
            });

            joinMarks(surface);
        } else {
            var made = [];

            texts.forEach(function (node) {
                if (markOf(node, surface, name)) {
                    return;
                }

                // Words in code: the mark goes around the code.
                var code = markOf(node, surface, 'code');

                if (code && (made.indexOf(code.parentNode) !== -1)) {
                    return;
                }

                made.push(wrapIn(code || node, name));
            });

            made.forEach(joinMark);
        }

        var last = texts[texts.length - 1];

        select(texts[0], 0, last, last.nodeValue.length, backward);

        return true;
    }

    // A link over a selection: the links inside it are taken off first, then
    // each piece of its words is linked; chips and cards stay as they are.
    function linkRange(surface, range, url, backward) {
        var working = range.cloneRange();

        splitEdges(working);

        var texts = textsIn(surface, working);

        if (!texts.length) {
            return false;
        }

        texts.forEach(function (node) {
            var mark;

            while ((mark = markOf(node, surface, 'link'))) {
                liftOut(node, mark);
            }
        });

        var made = [];

        texts.forEach(function (node) {
            var code = markOf(node, surface, 'code');

            if (code && (made.indexOf(code.parentNode) !== -1)) {
                return;
            }

            made.push(wrapIn(code || node, 'link', url));
        });

        made.forEach(joinMark);
        joinMarks(surface);

        var last = texts[texts.length - 1];

        select(texts[0], 0, last, last.nodeValue.length, backward);

        return true;
    }

    // The link the caret or the selection starts in.
    function anchorAt(surface) {
        var range = selectionRange();
        var element = range ? ((range.startContainer.nodeType === 1) ? range.startContainer : range.startContainer.parentNode) : null;
        var anchor = (element && element.closest) ? element.closest('a') : null;

        return (anchor && surface.contains(anchor)) ? anchor : null;
    }

    // A change the marks made: a step of its own in a box's undo, and the
    // box's owner hears of it (a note saves, the composer grows).
    function changed(surface, apply) {
        if (surface.wsEditor) {
            return surface.wsEditor.change(apply);
        }

        var done = apply();

        if (done) {
            surface.dispatchEvent(new Event('input', { bubbles: true }));
        }

        return done;
    }

    // A mark asked for by a button or a key, on any surface of this file.
    function formatSurface(surface, name) {
        if (surface.wsEditor) {
            return surface.wsEditor.format(name);
        }

        if (name === 'link') {
            openLink(surface, anchorAt(surface));
            return true;
        }

        return changed(surface, function () { return applyMark(surface, name); });
    }

    // ── Lists and quotes in the box ────────────────────────────────────
    //
    // A line of the box is an item, or the box's own children up to a <br>.
    // Lists are made and taken apart on the nodes, never with execCommand():
    // the browsers' list commands nest, restyle and keep undo steps of their
    // own.

    function itemNode(kind, mark) {
        var item = el(LISTS[kind].item.toLowerCase());

        if (mark) {
            item.setAttribute('data-ws-mark', mark);
        }

        return item;
    }

    function isBreak(node) {
        return !!node && (node.nodeType === 1) && (node.tagName === 'BR');
    }

    function indexIn(node) {
        return Array.prototype.indexOf.call(node.parentNode.childNodes, node);
    }

    // Words, a chip or a card in a piece of the box.
    function hasWords(node) {
        return ((node.textContent || '').replace(/[\s ​]/g, '') !== '')
            || !!(node.querySelector && node.querySelector('[data-token], .ws-rich-block'));
    }

    // An item with nothing in it keeps a line break for the caret to stand on.
    function fillEmpty(item) {
        if (!hasWords(item) && !item.querySelector('br')) {
            item.appendChild(el('br'));
        }

        return item;
    }

    // The item of a list a node is in, or null.
    function itemAt(surface, node) {
        for (var at = node; at && (at !== surface); at = at.parentNode) {
            if ((at.nodeType === 1) && at.parentNode && (at.parentNode.parentNode === surface) && listKind(at.parentNode)) {
                return at;
            }
        }

        return null;
    }

    // The item a boundary stands in. One right after a list that ends the box
    // is drawn, and typed into, at the end of its last item.
    function boundaryItem(surface, container, offset) {
        var item = itemAt(surface, container);

        if (item || (container !== surface)) {
            return item;
        }

        var before = surface.childNodes[offset - 1];

        return (listKind(before) && !surface.childNodes[offset]) ? before.lastElementChild : null;
    }

    function atItemStart(item, range) {
        if (!item.contains(range.startContainer)) {
            return false;
        }

        var head = document.createRange();

        head.setStart(item, 0);
        head.setEnd(range.startContainer, range.startOffset);

        return plainOf(head.cloneContents()).replace(/​/g, '') === '';
    }

    // The lines of the box in order: { item }, or { nodes, br } for a line
    // of its own children (fixed when it holds a card or a block of the
    // browser's, which no list takes); after a final break an empty line.
    function boxLines(surface) {
        var all = [];
        var line = null;

        Array.prototype.forEach.call(surface.childNodes, function (child) {
            if (listKind(child)) {
                line = null;
                Array.prototype.forEach.call(child.children, function (item) { all.push({ item: item }); });
                return;
            }

            if (!line) {
                line = { nodes: [], br: null };
                all.push(line);
            }

            if (isBreak(child)) {
                line.br = child;
                line = null;
                return;
            }

            if ((child.nodeType === 1) && (child.classList.contains('ws-rich-block') || BLOCKS.test(child.tagName))) {
                line.fixed = true;
            }

            line.nodes.push(child);
        });

        if (!line && (!all.length || !all[all.length - 1].item)) {
            all.push({ nodes: [], br: null });
        }

        return all;
    }

    // Which of boxLines() a boundary of the selection stands on.
    function lineIndex(surface, all, container, offset) {
        var item = boundaryItem(surface, container, offset);
        var node = container;

        if (!item && (container === surface)) {
            var before = surface.childNodes[offset - 1];

            node = (before && !isBreak(before) && !listKind(before)) ? before : (surface.childNodes[offset] || null);
            item = listKind(node) ? node.firstElementChild : null;
        }

        for (var i = 0; i < all.length; i++) {
            var line = all[i];

            if (item ? (line.item === item) : (line.nodes && node && line.nodes.concat(line.br ? [line.br] : []).some(function (one) { return one === node || one.contains(node); }))) {
                return i;
            }
        }

        return all.length - 1;
    }

    // The selection by its nodes, put back once they have moved: a range
    // inside a node taken out of the box would be lost.
    function holdSelection() {
        var range = selectionRange();
        var end = function (container, offset) {
            return (container.nodeType === 3) ? { node: container, offset: offset } : { node: container.childNodes[offset] || null, parent: container };
        };

        return range ? { start: end(range.startContainer, range.startOffset), end: end(range.endContainer, range.endOffset), backward: selectionBackward() } : null;
    }

    function putSelection(surface, held, fallback) {
        var spot = function (one) {
            if (one.node) {
                if (!surface.contains(one.node) || (one.node === surface)) {
                    return null;
                }

                return (one.node.nodeType === 3) ? [one.node, Math.min(one.offset, one.node.nodeValue.length)] : [one.node.parentNode, indexIn(one.node)];
            }

            return (surface.contains(one.parent) && ((one.parent === surface) || one.parent.parentNode)) ? [one.parent, one.parent.childNodes.length] : null;
        };
        var start = held ? spot(held.start) : null;
        var end = held ? spot(held.end) : null;

        if (!start || !end) {
            start = end = fallback;
        }

        if (start) {
            select(start[0], start[1], end[0], end[1], held && held.backward);
        }
    }

    // A line made an item: it joins a list of its kind just before it, and
    // one just after it joins too. Answers the item.
    function toItem(surface, line, kind) {
        var spot = document.createTextNode('');
        var item = itemNode(kind, '');

        surface.insertBefore(spot, line.nodes[0] || line.br || null);
        line.nodes.forEach(function (node) { item.appendChild(node); });

        if (line.br) {
            line.br.remove();
        }

        var list = spot.previousSibling;

        if (listKind(list) !== kind) {
            list = el(LISTS[kind].tag.toLowerCase(), LISTS[kind].className);
            surface.insertBefore(list, spot);
        }

        list.appendChild(fillEmpty(item));

        var next = spot.nextSibling;

        if (listKind(next) === kind) {
            while (next.firstChild) {
                list.appendChild(next.firstChild);
            }

            next.remove();
        }

        spot.remove();

        return item;
    }

    // An item taken out of its list as a line of the box, the items after it
    // a list of their own below it. Answers the line ({ nodes, br }).
    function liftItem(surface, item) {
        var list = item.parentNode;

        if (item.nextElementSibling) {
            var rest = list.cloneNode(false);

            while (item.nextSibling) {
                rest.appendChild(item.nextSibling);
            }

            surface.insertBefore(rest, list.nextSibling);
        }

        var next = list.nextSibling;
        var nodes = hasWords(item) ? Array.prototype.slice.call(item.childNodes) : [];

        // The break an item ends with is no line of its own.
        while (nodes.length && isBreak(nodes[nodes.length - 1])) {
            nodes.pop();
        }

        nodes.forEach(function (node) { surface.insertBefore(node, next); });
        item.remove();

        if (!list.children.length) {
            list.remove();
        }

        // A break ends the line before it and this one, unless a list ends
        // it; an empty line is a break of its own.
        var first = nodes[0] || next;
        var before = first ? first.previousSibling : surface.lastChild;
        var br = null;

        if (before && !isBreak(before) && !listKind(before)) {
            surface.insertBefore(el('br'), first || null);
        }

        if (!nodes.length || (next && !listKind(next))) {
            br = el('br');
            surface.insertBefore(br, next);
        }

        return { nodes: nodes, br: br };
    }

    function lineStart(surface, line) {
        var first = line.nodes[0] || line.br;

        return first ? [surface, indexIn(first)] : null;
    }

    // The toolbar's bullets, numbers and quote: the lines the selection
    // touches made items of that kind, or, when all of them are already,
    // lines again. Cards and blocks stay as they are.
    function toggleList(surface, kind) {
        var range = selectionRange();

        if (!range || !surface.contains(range.startContainer) || !surface.contains(range.endContainer)) {
            return false;
        }

        var all = boxLines(surface);
        var from = lineIndex(surface, all, range.startContainer, range.startOffset);
        var to = range.collapsed ? from : lineIndex(surface, all, range.endContainer, range.endOffset);
        var lines = all.slice(from, to + 1).filter(function (line) { return !line.fixed; });
        var same = function (line) { return !!line.item && (listKind(line.item.parentNode) === kind); };
        var off = lines.length && lines.every(same);
        var held = holdSelection();
        var fallback = null;

        if (!lines.length) {
            return false;
        }

        lines.forEach(function (line) {
            if (same(line) && !off) {
                return;
            }

            var lifted = line.item ? liftItem(surface, line.item) : line;

            if (off) {
                fallback = fallback || lineStart(surface, lifted);
                return;
            }

            var item = toItem(surface, lifted, kind);

            fallback = fallback || [item, 0];
        });

        putSelection(surface, held, fallback);

        return true;
    }

    // Enter in an item: the words after the caret go on in a new item of
    // the same mark; an empty item ends the list there. A selection goes
    // first: over two items it leaves both, the caret at the later one.
    function itemEnter(surface) {
        var range = selectionRange();
        var item = range ? boundaryItem(surface, range.startContainer, range.startOffset) : null;

        if (!item) {
            return false;
        }

        if (!range.collapsed) {
            var last = boundaryItem(surface, range.endContainer, range.endOffset);

            range.deleteContents();

            if (last && (last !== item) && last.parentNode && item.parentNode) {
                fillEmpty(item);
                select(fillEmpty(last), 0, last, 0);
                return true;
            }

            range = selectionRange();
        }

        if (!hasWords(item)) {
            var line = liftItem(surface, item);
            var spot = lineStart(surface, line);

            select(spot[0], spot[1], spot[0], spot[1]);
            return true;
        }

        var tail = document.createRange();
        var next = item.cloneNode(false);

        if (item.contains(range.startContainer)) {
            tail.setStart(range.startContainer, range.startOffset);
        } else {
            tail.setStart(item, item.childNodes.length);
        }

        tail.setEnd(item, item.childNodes.length);
        next.appendChild(tail.extractContents());
        item.parentNode.insertBefore(fillEmpty(next), item.nextSibling);
        fillEmpty(item);
        select(next, 0, next, 0);

        return true;
    }

    // The caret taken out of the marks around it, the mark split there, so
    // that what goes in next (a person or record picked) stands between its
    // halves: in <code> a chip was lost, in bold its mark showed as
    // characters. Answers where to insert: { parent, before }.
    function outOfMarks(surface, range) {
        var node = range.startContainer;
        var offset = range.startOffset;
        var outer = null;

        for (var at = node; at && (at !== surface) && !(at.parentNode && listKind(at.parentNode)); at = at.parentNode) {
            if (markName(at)) {
                outer = at;
            }
        }

        if (outer) {
            var tail = document.createRange();
            var copy = outer.cloneNode(false);
            var parent = outer.parentNode;

            tail.setStart(node, offset);
            tail.setEnd(outer, outer.childNodes.length);
            copy.appendChild(tail.extractContents());

            if (filled(copy)) {
                parent.insertBefore(copy, outer.nextSibling);
            }

            var after = outer.nextSibling;

            if (!filled(outer)) {
                outer.remove();
            }

            return { parent: parent, before: after };
        }

        if (node.nodeType === 3) {
            return { parent: node.parentNode, before: (offset === 0) ? node : ((offset < node.nodeValue.length) ? node.splitText(offset) : node.nextSibling) };
        }

        return { parent: node, before: node.childNodes[offset] || null };
    }

    // A selection from the box's first words to its last.
    function wholeBox(surface, range) {
        if (!range || range.collapsed || !surface.contains(range.startContainer) || !surface.contains(range.endContainer)) {
            return false;
        }

        var head = document.createRange();
        var tail = document.createRange();

        head.setStart(surface, 0);
        head.setEnd(range.startContainer, range.startOffset);
        tail.setStart(range.endContainer, range.endOffset);
        tail.setEnd(surface, surface.childNodes.length);

        return !hasWords(head.cloneContents()) && !hasWords(tail.cloneContents());
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
        var modal = surface ? surface.closest('.dialog') : null;

        return modal || document.body;
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

        var state = marksAt(surface, range);

        [
            ['bold', 'bi-type-bold', t('fmt_bold')],
            ['italic', 'bi-type-italic', t('fmt_italic')],
            ['strike', 'bi-type-strikethrough', t('fmt_strike')],
            ['highlight', 'bi-highlighter', t('fmt_highlight')],
            ['code', 'bi-code', t('fmt_code')],
            ['link', 'bi-link-45deg', t('fmt_link')]
        ].forEach(function (entry) {
            var tool = button('ws-format-tool', '', entry[1], entry[2]);
            var active = (entry[0] !== 'link') && state[entry[0]];

            tool.classList.toggle('active', active);

            if (entry[0] !== 'link') {
                tool.setAttribute('aria-pressed', active ? 'true' : 'false');
            }

            // mousedown, so the selection stays where it is.
            tool.addEventListener('mousedown', function (event) {
                event.preventDefault();

                // A new link over the selection, even where it starts in one.
                if (entry[0] === 'link') {
                    openLink(surface, null);
                    return;
                }

                formatSurface(surface, entry[0]);
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

    // The words a link shows, from the tip's Text field (typed), the words
    // it was filled with (shown: the selection's or the link's own, '' at a
    // caret) and the address. null when the shown words are kept as they
    // are, with their marks and chips; else what is typed on one line, or
    // the address when the field is left empty.
    function linkText(typed, shown, url) {
        var text = String(typed).replace(/\s+/g, ' ').trim();
        var before = String(shown).replace(/\s+/g, ' ').trim();

        if ((before !== '') && (text === before)) {
            return null;
        }

        return text || String(url).replace(/^mailto:/, '');
    }

    // The link tip: for a new link over the selection, or for the link that
    // was double-clicked. Its Text field holds the words the link shows.
    function openLink(surface, anchor) {
        var range = selectionRange();
        var saved = range ? range.cloneRange() : null;
        var backward = selectionBackward();
        var rect = anchor ? anchor.getBoundingClientRect() : (range ? range.getBoundingClientRect() : surface.getBoundingClientRect());
        var shown = anchor ? anchor.textContent : ((saved && surface.contains(saved.commonAncestorContainer)) ? saved.toString() : '');

        hideBar();
        hideTip();

        tip = el('div', 'ws-link-tip shadow');

        var form = el('form', 'd-flex flex-column gap-1');
        var words = el('input', 'form-control form-control-sm');
        var line = el('div', 'd-flex align-items-center gap-1');
        var input = el('input', 'form-control form-control-sm');

        words.type = 'text';
        words.placeholder = t('link_text');
        words.setAttribute('aria-label', t('link_text'));
        words.value = shown.replace(/\s+/g, ' ').trim();
        form.appendChild(words);

        input.type = 'text';
        input.placeholder = t('link_placeholder');
        input.setAttribute('aria-label', t('fmt_link'));
        input.value = anchor ? (anchor.getAttribute('href') || '') : '';
        line.appendChild(input);
        form.appendChild(line);

        var save = button('btn btn-sm btn-solid theme-primary', '', 'bi-check2', t('link_save'));
        save.type = 'submit';
        line.appendChild(save);

        if (anchor) {
            var open = button('btn btn-sm btn-text theme-secondary', '', 'bi-box-arrow-up-right', t('link_open'));
            open.addEventListener('click', function () {
                var url = cleanUrl(anchor.getAttribute('href'));

                if (url) {
                    window.open(url, '_blank', 'noopener');
                }
            });
            line.appendChild(open);

            var unlink = button('btn btn-sm btn-text theme-secondary', '', 'bi-link', t('link_remove'));
            unlink.addEventListener('click', function () {
                changed(surface, function () {
                    if (!anchor.parentNode) {
                        return false;
                    }

                    anchor.replaceWith.apply(anchor, Array.prototype.slice.call(anchor.childNodes));
                    return true;
                });
                hideTip();
                surface.focus();
            });
            line.appendChild(unlink);
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

            var text = linkText(words.value, shown, url);

            changed(surface, function () {
                if (anchor) {
                    if (!anchor.parentNode) {
                        return false;
                    }

                    anchor.setAttribute('href', url);

                    if (text !== null) {
                        anchor.textContent = text;
                    }

                    return true;
                }

                if (!saved) {
                    return false;
                }

                surface.focus();

                var selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(saved);

                if (!saved.collapsed && (text === null)) {
                    return linkRange(surface, saved, url, backward);
                }

                // New words replace the selection, if any, as one link.
                var link = el('a', '', text);
                link.href = url;
                saved.deleteContents();
                saved.insertNode(link);
                saved.setStartAfter(link);
                saved.collapse(true);
                selection.removeAllRanges();
                selection.addRange(saved);

                return true;
            });

            hideTip();
            surface.focus();
        });

        form.addEventListener('keydown', function (event) {
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

        // Marks waiting at a caret are forgotten once the caret moves on.
        if (waitingIn && !waiting(waitingIn, range)) {
            forgetWaiting();
        }

        if (box && box.wsEditor) {
            box.wsEditor.savedRange = range.cloneRange();
            box.wsEditor.savedBackward = selectionBackward();
            notifySoon(box.wsEditor);
        }
    });

    // The rows of tools hear of the caret once a frame, however often the
    // selection moves while a word is typed or dragged over.
    var notifyQueue = [];
    var notifyFrame = 0;

    function notifySoon(editor) {
        if (notifyQueue.indexOf(editor) === -1) {
            notifyQueue.push(editor);
        }

        if (notifyFrame) {
            return;
        }

        var flush = function () {
            var queued = notifyQueue;

            notifyQueue = [];
            notifyFrame = 0;
            queued.forEach(function (one) { one.notify(); });
        };

        notifyFrame = window.requestAnimationFrame ? window.requestAnimationFrame(flush) : window.setTimeout(flush, 16);
    }

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

    // A shortcut ('Control+Shift+X') as the toolbar titles write it:
    // Ctrl+Shift+X, or ⌘Shift+X on a Mac.
    function keyLabel(keys, mac) {
        var label = String(keys).replace(/Control\+/g, 'Ctrl+');

        return mac ? label.replace(/Ctrl\+/g, '⌘') : label;
    }

    // What the shortcut sheet lists, as [keys, label key]: the marks, undo
    // and redo, Enter as the box takes it (enterSends: the composer's Enter
    // sends) where the box is an Editor, and the sheet itself.
    function shortcutRows(enterSends, editor) {
        var rows = [
            ['Control+B', 'fmt_bold'],
            ['Control+I', 'fmt_italic'],
            ['Control+Shift+X', 'fmt_strike'],
            ['Control+Shift+H', 'fmt_highlight'],
            ['Control+E', 'fmt_code'],
            ['Control+K', 'fmt_link'],
            ['Control+Z', 'keys_undo'],
            ['Control+Y', 'keys_redo']
        ];

        if (editor) {
            rows.push(['Enter', enterSends ? 'keys_send' : 'keys_newline']);
            rows.push(['Shift+Enter', 'keys_newline']);
        }

        rows.push(['Control+/', 'keys_title']);

        return rows;
    }

    // The shortcut sheet (Ctrl+/), by the surface; Escape or its close
    // button takes the focus back to the surface, at the caret it had.
    function openKeys(surface) {
        var range = selectionRange();
        var saved = (range && surface.contains(range.startContainer)) ? range.cloneRange() : null;
        var editor = surface.wsEditor || null;
        var rows = shortcutRows(!!(editor && editor.options.onEnter && !touchScreen()), !!editor);

        hideBar();
        hideTip();

        tip = el('div', 'ws-link-tip ws-keys-sheet shadow');
        tip.setAttribute('role', 'dialog');
        tip.setAttribute('aria-label', t('keys_title'));
        tip.tabIndex = -1;

        var back = function () {
            hideTip();
            surface.focus();

            if (saved) {
                var selection = window.getSelection();

                selection.removeAllRanges();
                selection.addRange(saved);
            }
        };

        var head = el('div', 'd-flex align-items-center justify-content-between gap-3 mb-1');
        var close = button('btn btn-sm btn-text theme-secondary', '', 'bi-x-lg', t('keys_close'));

        head.appendChild(el('strong', 'small', t('keys_title')));
        close.addEventListener('click', back);
        head.appendChild(close);
        tip.appendChild(head);

        var table = el('table', 'table table-sm small mb-0');
        var body = el('tbody');

        rows.forEach(function (row) {
            var tr = el('tr');
            var keys = el('td', 'text-nowrap');

            keys.appendChild(el('kbd', '', keyLabel(row[0], MAC)));
            tr.appendChild(keys);
            tr.appendChild(el('td', '', t(row[1])));
            body.appendChild(tr);
        });

        table.appendChild(body);
        tip.appendChild(table);

        tip.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                back();
            }
        });

        floatingHost(surface).appendChild(tip);
        place(tip, surface.getBoundingClientRect(), false);
        tip.focus();
    }

    // Keyboard shortcuts in any writing surface: Ctrl+B, Ctrl+I,
    // Ctrl+Shift+X, Ctrl+Shift+H (highlight), Ctrl+E (code), Ctrl+K (link)
    // and Ctrl+/ (the shortcut sheet), Cmd on a Mac.
    function shortcut(surface, event) {
        if (!(event.ctrlKey || event.metaKey) || event.altKey) {
            return false;
        }

        var key = String(event.key || '').toLowerCase();

        // "/" is a shifted key on many layouts (Shift+7 on Turkish Q).
        if ((key === '/') || (event.code === 'NumpadDivide')) {
            event.preventDefault();
            event.stopPropagation();
            openKeys(surface);

            return true;
        }
        var keys = { b: 'bold', i: 'italic', e: 'code', k: 'link' };
        var name = Object.prototype.hasOwnProperty.call(keys, key) ? keys[key] : (((key === 'x') && event.shiftKey) ? 'strike' : (((key === 'h') && event.shiftKey) ? 'highlight' : ''));

        if (!name) {
            return false;
        }

        // Kept from the page's own shortcuts: Ctrl+K there opens the search.
        event.preventDefault();
        event.stopPropagation();
        formatSurface(surface, name);

        return true;
    }

    // A paste into a surface. A one-line field, a table cell and Ctrl+Shift+V
    // take plain text; a writing box reads the clipboard first (pasteSmart()).
    // Files are left to the conversation's own paste handler, except in a box
    // that takes pictures (a note): one pasted without words goes to it.
    function paste(surface, event, singleLine) {
        var data = event.clipboardData;
        var editor = singleLine ? null : (surface.wsEditor || null);
        var plain = surface.wsPlainPaste;

        surface.wsPlainPaste = false;

        if (!data) {
            return;
        }

        var text = String(data.getData('text/plain') || '').replace(/\r\n?/g, '\n');
        var files = Array.prototype.slice.call(data.files || []);

        if (files.length) {
            var take = editor ? editor.options.pasteFiles : null;
            var pictures = files.filter(function (file) { return /^image\//.test(file.type || ''); });

            if (!take) {
                return;
            }

            // Words beside a picture (Word puts one of the text itself) are
            // pasted as words.
            if (text.trim() === '') {
                if (pictures.length) {
                    event.preventDefault();
                    take(pictures);
                }

                return;
            }
        }

        event.preventDefault();

        if (editor && !plain && editor.pasteSmart(text, String(data.getData('text/html') || ''))) {
            return;
        }

        pastePlain(text, singleLine);
    }

    // HTML from the clipboard as the stored markup, read in a detached
    // document: DOMParser runs no script and loads nothing, and the document
    // is never put in the page.
    function pasteHtml(html) {
        try {
            var doc = new DOMParser().parseFromString(html, 'text/html');

            return (doc && doc.body) ? pasteMarkup(doc.body) : '';
        } catch (error) {
            return '';
        }
    }

    // Plain text at the caret, line by line.
    function pastePlain(text, singleLine) {
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
            // Ctrl+Shift+V (Cmd on a Mac) pastes plain text, as before.
            surface.wsPlainPaste = (event.ctrlKey || event.metaKey) && event.shiftKey && (String(event.key || '').toLowerCase() === 'v');
            shortcut(surface, event);
        });

        // Letters typed where a mark waits go into it; the browser's own
        // format commands (its menu, a key of its own) take the buttons' way.
        surface.addEventListener('beforeinput', function (event) {
            var type = event.inputType || '';
            var formats = { formatBold: 'bold', formatItalic: 'italic', formatStrikeThrough: 'strike' };

            if ((type === 'insertText') && surface.wsMarks && event.data && !event.isComposing) {
                if (typeMarked(surface, event.data)) {
                    event.preventDefault();
                    surface.dispatchEvent(new Event('input', { bubbles: true }));
                }

                return;
            }

            if (Object.prototype.hasOwnProperty.call(formats, type)) {
                event.preventDefault();
                formatSurface(surface, formats[type]);
            }
        });

        // A composed word comes as composition text, never as insertText, and
        // the browser's own composition is left to run as it is (changing the
        // nodes under it breaks the IME): marks waiting at its caret are held
        // through it and put on the word once it is done.
        surface.addEventListener('compositionstart', function () {
            if (surface.wsMarks && !waiting(surface, selectionRange())) {
                forgetWaiting();
            }

            surface.wsComposing = true;
        });

        surface.addEventListener('compositionend', function () {
            surface.wsComposing = false;

            if (surface.wsMarks && composedMarked(surface)) {
                surface.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });

        surface.addEventListener('paste', function (event) {
            paste(surface, event, singleLine);
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
     * handled, onBlockOpen(kind, markup, done), onHistory(canUndo, canRedo)
     *
     * The box keeps its own undo history: the browser's own cannot follow
     * the cards, chips and results the box draws into itself, and loses its
     * steps as soon as one of them is redrawn.
     */
    function Editor(options) {
        var self = this;

        self.options = options || {};
        self.savedRange = null;
        self.savedBackward = false;
        self.history = { states: [], at: -1, timer: 0, pending: false, since: 0, quiet: false };
        self.watchers = [];
        self.slash = null;
        self.slashSkip = -1;

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
            if (!self.history.quiet) {
                self.recordLater();
            }

            self.tidy();
            self.fenceCheck();
            self.slashCheck(event);

            if (self.options.previews) {
                clearTimeout(self.previewTimer);
                self.previewTimer = setTimeout(function () { self.drawPreviews(); }, 250);
            }

            if (self.options.onInput) {
                self.options.onInput();
            }
        });

        // Undo and redo from the menu or the keyboard of the browser.
        node.addEventListener('beforeinput', function (event) {
            // Select all and delete in a box with a list left an empty item
            // behind: the box is emptied instead.
            if (/^delete/.test(event.inputType || '') && node.querySelector('ul, ol, blockquote') && wholeBox(node, selectionRange())) {
                event.preventDefault();
                self.change(function () {
                    node.innerHTML = '';
                    select(node, 0, node, 0);
                    return true;
                });
                return;
            }

            if (event.inputType === 'historyUndo' || event.inputType === 'historyRedo') {
                event.preventDefault();

                if (event.inputType === 'historyUndo') {
                    self.undo();
                } else {
                    self.redo();
                }
            }
        });

        node.addEventListener('keydown', function (event) {
            if (self.historyKey(event) || self.slashKey(event)) {
                return;
            }

            if (self.options.onKeydown && self.options.onKeydown(event)) {
                return;
            }

            if (self.listKey(event)) {
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

            // In an item the list goes on: Shift+Enter in the composer, whose
            // Enter sends.
            event.preventDefault();

            if (self.listEnter()) {
                return;
            }

            lineBreak();
            self.tidy();
        });

        node.addEventListener('mousedown', function () { self.slashClose(); });

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

            self.slashClose();

            if (range && node.contains(range.startContainer)) {
                self.savedRange = range.cloneRange();
                self.savedBackward = selectionBackward();
            }
        });

        self.resetHistory();
    }

    Editor.prototype = {
        focus: function () {
            this.node.focus();
        },

        isEmpty: function () {
            return (this.node.textContent || '').replace(/[\s ​]/g, '') === ''
                && !this.node.querySelector('[data-token], .ws-rich-block, ul, ol, blockquote');
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
            this.resetHistory();
        },

        // ── Undo history ──

        // The box as it stands, without what is drawn beside the text (the
        // results of lines, the cards' drawings) and with the caret's place.
        snapshot: function () {
            var copy = this.node.cloneNode(true);

            Array.prototype.forEach.call(copy.querySelectorAll('[data-ws-skip], .ws-rich-block-view'), function (node) {
                node.remove();
            });

            Array.prototype.forEach.call(copy.querySelectorAll('.ws-rich-block-shown'), function (node) {
                node.classList.remove('ws-rich-block-shown');
            });

            copy.normalize();

            return { html: copy.innerHTML, caret: this.caretOffset() };
        },

        // The caret as a count of characters from the start, chips, cards
        // and line breaks one each (as plainOf counts them).
        caretOffset: function () {
            var range = selectionRange();

            if (!range || !this.node.contains(range.startContainer)) {
                return null;
            }

            var head = document.createRange();

            head.setStart(this.node, 0);
            head.setEnd(range.startContainer, range.startOffset);

            return plainOf(head.cloneContents()).length;
        },

        placeCaret: function (offset) {
            if (offset === null || offset === undefined) {
                this.caretToEnd();
                return;
            }

            var left = offset;
            var spot = null;

            function walk(parent) {
                for (var i = 0; i < parent.childNodes.length && !spot; i++) {
                    var child = parent.childNodes[i];

                    if (child.nodeType === 3) {
                        if (left <= child.nodeValue.length) {
                            spot = [child, left];
                            return;
                        }

                        left -= child.nodeValue.length;
                    } else if (child.nodeType === 1 && !child.hasAttribute('data-ws-skip')) {
                        if (child.hasAttribute('data-token') || child.classList.contains('ws-rich-block') || child.tagName === 'BR') {
                            if (left === 0) {
                                spot = [parent, i];
                                return;
                            }

                            left -= 1;
                        } else {
                            walk(child);
                        }
                    }
                }
            }

            walk(this.node);

            if (!spot) {
                this.caretToEnd();
                return;
            }

            var range = document.createRange();
            var selection = window.getSelection();

            range.setStart(spot[0], spot[1]);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
            this.savedRange = range.cloneRange();
        },

        resetHistory: function () {
            clearTimeout(this.history.timer);
            this.history.timer = 0;
            this.history.pending = false;
            this.history.states = [this.snapshot()];
            this.history.at = 0;
            this.historyChanged();
        },

        // Typing is kept as one step until it pauses, or for two seconds of
        // typing at most.
        recordLater: function () {
            var self = this;
            var history = self.history;

            if (history.pending && (Date.now() - history.since > 2000)) {
                self.record();
            }

            if (!history.pending) {
                history.pending = true;
                history.since = Date.now();
            }

            clearTimeout(history.timer);
            history.timer = setTimeout(function () { self.record(); }, 600);
            self.historyChanged();
        },

        record: function () {
            var history = this.history;
            var state = this.snapshot();
            var top = history.states[history.at];

            clearTimeout(history.timer);
            history.timer = 0;
            history.pending = false;

            if (top && top.html === state.html) {
                top.caret = state.caret;
                this.historyChanged();
                return;
            }

            history.states = history.states.slice(0, history.at + 1);
            history.states.push(state);

            if (history.states.length > HISTORY_MAX) {
                history.states.shift();
            }

            history.at = history.states.length - 1;
            this.historyChanged();
        },

        canUndo: function () {
            return this.history.pending || this.history.at > 0;
        },

        canRedo: function () {
            return !this.history.pending && this.history.at < this.history.states.length - 1;
        },

        undo: function () {
            var history = this.history;

            if (history.pending) {
                this.record();
            }

            if (history.at <= 0) {
                return false;
            }

            history.at--;
            this.applyState(history.states[history.at]);

            return true;
        },

        redo: function () {
            var history = this.history;

            if (history.pending) {
                this.record();
            }

            if (history.at >= history.states.length - 1) {
                return false;
            }

            history.at++;
            this.applyState(history.states[history.at]);

            return true;
        },

        // A step put back: the box tells its owner it changed (a note saves
        // it), without that change becoming a step of its own.
        applyState: function (state) {
            this.node.innerHTML = state.html;
            this.savedRange = null;
            this.tidy();
            this.node.focus();
            this.placeCaret(state.caret);
            this.announce();
            this.drawPreviews();
            this.historyChanged();
        },

        // The box tells its owner it changed (a note saves, the composer
        // grows) without the change becoming a step of the history: the
        // caller has kept it as one already, or it is a step put back.
        announce: function () {
            this.history.quiet = true;

            try {
                this.node.dispatchEvent(new Event('input', { bubbles: true }));
            } finally {
                this.history.quiet = false;
            }
        },

        historyChanged: function () {
            if (this.options.onHistory) {
                this.options.onHistory(this.canUndo(), this.canRedo());
            }

            this.notify();
        },

        // ── Marks and the rows of tools ──

        // Whoever shows the box's state (a row of tools) is called when it may
        // have changed: a step of the history, the caret, a mark.
        watch: function (callback) {
            this.watchers.push(callback);
        },

        notify: function () {
            var self = this;

            self.watchers.forEach(function (callback) { callback(self); });
        },

        // A change the box makes to itself (a mark, a link): what was typed
        // before it is a step, the change is another. apply() answers true
        // when it changed the box.
        change: function (apply) {
            if (this.history.pending) {
                this.record();
            }

            if (!apply()) {
                this.notify();
                return false;
            }

            this.tidy();
            this.record();
            this.announce();

            return true;
        },

        /**
         * A mark on the selection, or off it: bold, italic, strike,
         * highlight, code or link (the link tip). With nothing selected bold,
         * italic, strike and highlight wait for what is typed next at the
         * caret. Made on the nodes, never
         * with document.execCommand(), and kept as a step of the box's undo.
         */
        format: function (name) {
            var self = this;

            if (!Object.prototype.hasOwnProperty.call(MARKS, name)) {
                return false;
            }

            self.restore();

            if (name === 'link') {
                openLink(self.node, anchorAt(self.node));
                return true;
            }

            return self.change(function () { return applyMark(self.node, name); });
        },

        // Which marks the caret or the selection has ({ bold, italic, strike,
        // highlight, code, link }); where the selection is elsewhere, the caret the box
        // kept when it was left, which a button acts on.
        formatState: function () {
            var range = selectionRange();

            if (!range || !this.node.contains(range.startContainer)) {
                range = (this.savedRange && this.node.contains(this.savedRange.startContainer)) ? this.savedRange : null;
            }

            var state = marksAt(this.node, range);
            var item = range ? boundaryItem(this.node, range.startContainer, range.startOffset) : null;

            Object.keys(LISTS).forEach(function (kind) {
                var name = LISTS[kind].tool;

                state[name] = !!item && (listKind(item.parentNode) === kind);
            });

            return state;
        },

        // A list or a quote (bullets, numbers, quote) on the lines of the
        // selection, or off them; a step of the box's undo.
        list: function (name) {
            var self = this;
            var kind = Object.keys(LISTS).filter(function (one) { return LISTS[one].tool === name; })[0];

            if (!kind) {
                return false;
            }

            self.restore();

            return self.change(function () { return toggleList(self.node, kind); });
        },

        // Shift+Tab, and Backspace at an item's start, take the item out of
        // its list; Tab puts a line straight after a list back into it.
        // Elsewhere Tab moves the focus: there is no level below an item.
        // true when handled.
        listKey: function (event) {
            var self = this;
            var range = selectionRange();

            if (!range || event.ctrlKey || event.metaKey || event.altKey || event.isComposing || !self.node.contains(range.startContainer)
                || ((event.key !== 'Tab') && (event.key !== 'Backspace'))) {
                return false;
            }

            var item = boundaryItem(self.node, range.startContainer, range.startOffset);

            if ((event.key === 'Tab') && !event.shiftKey) {
                var all = boxLines(self.node);
                var line = item ? null : all[lineIndex(self.node, all, range.startContainer, range.startOffset)];
                var first = line ? (line.nodes[0] || line.br) : null;
                var list = first ? first.previousSibling : null;

                if (!line || line.fixed || !listKind(list)) {
                    return false;
                }

                event.preventDefault();
                self.change(function () {
                    var held = holdSelection();

                    putSelection(self.node, held, [toItem(self.node, line, listKind(list)), 0]);
                    return true;
                });

                return true;
            }

            if (!item || !((event.key === 'Tab') || (!event.shiftKey && range.collapsed && atItemStart(item, range)))) {
                return false;
            }

            event.preventDefault();
            self.change(function () {
                var held = holdSelection();

                putSelection(self.node, held, lineStart(self.node, liftItem(self.node, item)));
                return true;
            });

            return true;
        },

        // Whether the caret stands in an item of a list or a quote.
        inListItem: function () {
            var range = selectionRange();

            return !!range && this.node.contains(range.startContainer) && !!boundaryItem(this.node, range.startContainer, range.startOffset);
        },

        // Enter in an item (itemEnter()); true when the caret was in one.
        listEnter: function () {
            var self = this;

            if (!self.inListItem()) {
                return false;
            }

            self.change(function () { return itemEnter(self.node); });

            return true;
        },

        // Ctrl+Z, Ctrl+Y and Ctrl+Shift+Z (Cmd on a Mac), whatever the
        // keyboard's letters.
        historyKey: function (event) {
            if (!(event.ctrlKey || event.metaKey) || event.altKey || event.isComposing) {
                return false;
            }

            var key = String(event.key || '').toLowerCase();

            if (!/^[a-z]$/.test(key)) {
                key = String(event.code || '').replace(/^Key/, '').toLowerCase();
            }

            if (key === 'z' && !event.shiftKey) {
                event.preventDefault();
                this.undo();
                return true;
            }

            if (key === 'y' || (key === 'z' && event.shiftKey)) {
                event.preventDefault();
                this.redo();
                return true;
            }

            return false;
        },

        markup: function () {
            return serialize(this.node, false);
        },

        // A stored text back into the box, for an edit. labels: token →
        // the name the tag had. A text that replaces what the person was
        // writing (an earlier version of a note) is a step they can undo;
        // any other starts the history afresh.
        setMarkup: function (markup, labels, asStep) {
            var node = this.node;
            var blocks = parseBlocks(markup);

            if (asStep && this.history.pending) {
                this.record();
            }

            node.innerHTML = '';
            node.appendChild(markupNodes(blocks, labels));

            this.tidy();
            this.caretToEnd();
            this.drawPreviews();

            if (!asStep) {
                this.resetHistory();
                return;
            }

            this.record();
            this.announce();
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

            var all = Array.prototype.slice.call(self.node.querySelectorAll('.ws-rich-block'));
            var cards = all.filter(function (card) {
                return card.wsShown !== (card.getAttribute('data-markup') || '');
            });

            if (!cards.length) {
                return;
            }

            // A calculation's names reach the tables and calculations around
            // it: with one in the note, every card is drawn again together.
            if (all.some(function (card) { return isCalcBlock(card.getAttribute('data-markup') || ''); })) {
                cards = all;
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
        // The selection is put back by its anchor and its focus, not as a
        // range, so that one made backwards stays backwards.
        restore: function () {
            var selection = window.getSelection();
            var range = selection.rangeCount ? selection.getRangeAt(0) : null;

            if (range && this.node.contains(range.startContainer)) {
                var ends = [selection.anchorNode, selection.anchorOffset, selection.focusNode, selection.focusOffset];

                this.node.focus();
                selection.setBaseAndExtent(ends[0], ends[1], ends[2], ends[3]);
                this.savedRange = range.cloneRange();
                return;
            }

            this.node.focus();

            if (this.savedRange && this.node.contains(this.savedRange.startContainer)) {
                var saved = this.savedRange;

                select(saved.startContainer, saved.startOffset, saved.endContainer, saved.endOffset, this.savedBackward);
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
                var range = selectionRange();
                var chip = chipNode(item.token, item.shown, item.icon || '');
                var space = document.createTextNode('\u00a0');

                range.deleteContents();

                var spot = outOfMarks(this.node, range);

                spot.parent.insertBefore(chip, spot.before);
                spot.parent.insertBefore(space, chip.nextSibling);
                select(space, 1, space, 1);
                this.node.dispatchEvent(new Event('input', { bubbles: true }));
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

            // A card stands on a line of its own, below a list it was put in.
            var caret = selectionRange();
            var item = caret ? boundaryItem(this.node, caret.startContainer, caret.startOffset) : null;

            if (item) {
                // As nodes: insertHTML at the box level next to a list is
                // drawn into the list.
                var list = item.parentNode;
                var card = blockNode(kind, markup);
                var end = el('br');

                this.node.insertBefore(card, list.nextSibling);
                this.node.insertBefore(end, card.nextSibling);
                select(this.node, indexIn(end) + 1, this.node, indexIn(end) + 1);
            } else {
                var before = this.beforeCaret();
                var holder = el('span');

                holder.appendChild(blockNode(kind, markup));

                command('insertHTML', ((before !== '' && !/\n$/.test(before)) ? '<br>' : '') + holder.innerHTML + '<br>');
            }

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

        /**
         * What the clipboard holds, pasted as the box would write it: one
         * address over selected words links them, a range from a sheet or a
         * table alone opens the table window filled with it, HTML comes in
         * as the box's marks, lists, quotes, code and tables. false leaves
         * the paste to plain text.
         */
        pasteSmart: function (text, html) {
            var url = pastedUrl(text);

            if (url && this.linkSelection(url)) {
                return true;
            }

            var markup = pasteSheet(text) || (html ? pasteHtml(html) : '');

            if (markup === '') {
                return false;
            }

            var blocks = parseBlocks(markup);

            if (blocks.length === 1 && blocks[0].kind === 'table') {
                this.pasteTable(blocks[0].markup);
                return true;
            }

            return this.insertMarkup(blocks);
        },

        linkSelection: function (url) {
            var node = this.node;
            var range = selectionRange();

            if (!range || range.collapsed || !node.contains(range.startContainer) || !node.contains(range.endContainer) || range.toString().trim() === '') {
                return false;
            }

            var backward = selectionBackward();

            return this.change(function () { return linkRange(node, range, url, backward); });
        },

        // A pasted table, in the table window before it is put in the box.
        pasteTable: function (markup) {
            var self = this;
            var range = selectionRange();

            if (range && self.node.contains(range.startContainer)) {
                self.savedRange = range.cloneRange();
                self.savedBackward = false;
            }

            openTable(markup, function (result) {
                if (result) {
                    self.insertBlock('table', result);
                }
            }, {
                fresh: true,
                insertLabel: (self.options.slashInserts && self.options.slashInserts.insertLabel) || '',
                scope: function () { return self.markup(); }
            });
        },

        // Pasted blocks in place of the selection, as the box reads a stored
        // text. Lists and cards stand on lines of their own: below the list
        // the caret is in, or after a line break when words stand before the
        // caret.
        insertMarkup: function (blocks) {
            var self = this;
            var node = self.node;

            return self.change(function () {
                var range = selectionRange();

                if (!range || !node.contains(range.startContainer)) {
                    self.caretToEnd();
                    range = selectionRange();
                }

                range.deleteContents();

                var words = blocks.every(function (block) { return block.kind === 'text'; });
                var fragment = markupNodes(blocks, {});
                var last = fragment.lastChild;
                var item = words ? null : boundaryItem(node, range.startContainer, range.startOffset);

                if (!last) {
                    return false;
                }

                if (words) {
                    range.insertNode(fragment);
                } else if (item) {
                    node.insertBefore(fragment, item.parentNode.nextSibling);
                } else {
                    var head = self.beforeCaret();
                    var spot = outOfMarks(node, range);

                    if (head !== '' && !/\n$/.test(head)) {
                        fragment.insertBefore(el('br'), fragment.firstChild);
                    }

                    if (spot.parent === node) {
                        node.insertBefore(fragment, spot.before);
                    } else {
                        node.appendChild(fragment);
                    }
                }

                select(last.parentNode, indexIn(last) + 1, last.parentNode, indexIn(last) + 1);

                return true;
            });
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
            }, { scope: function () { return self.markup(); } });
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
        },

        // Whether the caret stands where a typed suggestion may not go: a
        // selection, outside the box, in code, a link, a table cell or a
        // card.
        caretBlocked: function () {
            var range = selectionRange();

            if (!range || !range.collapsed || !this.node.contains(range.startContainer)) {
                return true;
            }

            var element = (range.startContainer.nodeType === 1) ? range.startContainer : range.startContainer.parentNode;

            return !!(element && element.closest && element.closest('code, a, td, th, .ws-rich-block, [data-ws-skip]'));
        },

        // "/" in a note's box (options.slashInserts) lists the inserts that
        // match the word typed after it (slashInserts()). Not while a word is
        // composed, nor in code, a link or a card; "//" leaves one "/" and no
        // list; Escape leaves the "/" as typed, a space closes it.
        slashCheck: function (event) {
            var range = selectionRange();

            if (!this.options.slashInserts || (event && event.isComposing) || this.node.wsComposing || !range) {
                this.slashClose();
                return;
            }

            var blocked = this.caretBlocked();
            var labels = {};

            SLASH.forEach(function (name) { labels[name] = t(TOOLS[name].label); });

            var found = slashInserts(this.beforeCaret(), blocked, labels);

            if (found && found.plain) {
                var open = this.slash && (this.slash.start === found.start);

                this.slashClose();

                if (open) {
                    this.slashSkip = found.start;
                    this.replaceTyped(2, { text: '/' });
                }

                return;
            }

            if (!found || (found.start === this.slashSkip)) {
                this.slashSkip = found ? this.slashSkip : -1;
                this.slashClose();
                return;
            }

            this.slashDraw(found, labels, range);
        },

        // The list under the caret: no row chosen while nothing is typed
        // after the "/", so Enter still breaks the line; the first row once
        // a word is typed.
        slashDraw: function (found, labels, range) {
            var self = this;
            var box = (self.slash && self.slash.box) || el('div', 'ws-picker ws-slash');
            var list = el('div', 'ws-picker-list');

            self.slash = { box: box, start: found.start, word: found.word, names: found.names, index: (found.word === '') ? -1 : 0, rows: [] };
            box.textContent = '';
            box.id = box.id || ('ws-slash-' + Math.random().toString(36).slice(2));
            box.setAttribute('role', 'listbox');
            box.setAttribute('aria-label', t('slash_inserts'));

            found.names.forEach(function (name, index) {
                var row = el('button', 'ws-picker-item');

                row.type = 'button';
                row.id = box.id + '-' + name;
                row.setAttribute('role', 'option');
                row.appendChild(icon(TOOLS[name].icon));
                row.appendChild(el('span', 'ws-picker-label', labels[name].replace(/\s*\(.*\)\s*$/, '')));
                row.addEventListener('mousedown', function (event) { event.preventDefault(); });
                row.addEventListener('click', function () {
                    self.slash.index = index;
                    self.slashPick();
                });
                list.appendChild(row);
                self.slash.rows.push(row);
            });

            box.appendChild(list);

            if (!box.parentNode) {
                floatingHost(self.node).appendChild(box);
            }

            self.node.setAttribute('aria-expanded', 'true');
            self.node.setAttribute('aria-controls', box.id);
            self.slashMark();

            var rect = range.getBoundingClientRect();

            if (!rect.height) {
                rect = self.node.getBoundingClientRect();
            }

            var top = rect.bottom + 4;

            if ((top + box.offsetHeight > window.innerHeight - 8) && (rect.top - box.offsetHeight - 4 >= 8)) {
                top = rect.top - box.offsetHeight - 4;
            }

            box.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - box.offsetWidth - 8)) + 'px';
            box.style.top = Math.max(8, top) + 'px';
        },

        // The chosen insert: the "/word" typed goes, then the tool acts as
        // its button does. A list or quote is one step of the box's undo
        // with the word's removal, so one Ctrl+Z brings the "/word" back;
        // the highlight waits for the next letters; a card's window opens
        // and puts the card in where the word was.
        slashPick: function () {
            var self = this;
            var slash = self.slash;
            var name = slash ? slash.names[slash.index] : null;
            var spec = name ? TOOLS[name] : null;

            self.slashClose();

            if (!spec) {
                return;
            }

            var remove = function () {
                var selection = window.getSelection();

                self.node.focus();

                for (var i = 0; i < slash.word.length + 1; i++) {
                    selection.modify('extend', 'backward', 'character');
                }

                var range = selectionRange();

                range.deleteContents();
                select(range.startContainer, range.startOffset, range.startContainer, range.startOffset);

                return true;
            };

            if (spec.list) {
                self.change(function () {
                    remove();
                    toggleList(self.node, spec.list);
                    return true;
                });
            } else if (spec.mark) {
                self.change(remove);
                applyMark(self.node, spec.mark);
                self.notify();
            } else {
                self.change(remove);
                self.savedRange = selectionRange() ? selectionRange().cloneRange() : null;
                insertFrom(self, spec.block, (typeof self.options.slashInserts === 'object') ? self.options.slashInserts : {});
            }
        },

        slashMark: function () {
            var slash = this.slash;

            slash.rows.forEach(function (row, index) {
                row.classList.toggle('active', index === slash.index);
                row.setAttribute('aria-selected', (index === slash.index) ? 'true' : 'false');
            });

            if (slash.index >= 0) {
                this.node.setAttribute('aria-activedescendant', slash.rows[slash.index].id);
                slash.rows[slash.index].scrollIntoView({ block: 'nearest' });
            } else {
                this.node.removeAttribute('aria-activedescendant');
            }
        },

        slashClose: function () {
            if (this.slash) {
                this.slash.box.remove();
                this.slash = null;
                this.node.removeAttribute('aria-expanded');
                this.node.removeAttribute('aria-controls');
                this.node.removeAttribute('aria-activedescendant');
            }
        },

        // Arrows choose, Enter or Tab inserts the chosen row, Escape closes
        // and leaves the "/" as typed; a caret moved sideways closes. true
        // when handled.
        slashKey: function (event) {
            var slash = this.slash;

            if (!slash || event.isComposing || event.ctrlKey || event.metaKey || event.altKey) {
                return false;
            }

            if ((event.key === 'ArrowDown') || (event.key === 'ArrowUp')) {
                event.preventDefault();
                slash.index = (slash.index + ((event.key === 'ArrowDown') ? 1 : slash.rows.length - 1 + ((slash.index < 0) ? 1 : 0))) % slash.rows.length;
                this.slashMark();
                return true;
            }

            if (((event.key === 'Enter') || (event.key === 'Tab')) && !event.shiftKey && (slash.index >= 0)) {
                event.preventDefault();
                this.slashPick();
                return true;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                this.slashSkip = slash.start;
                this.slashClose();
                return true;
            }

            if (['ArrowLeft', 'ArrowRight', 'Home', 'End', 'Enter', 'Tab'].indexOf(event.key) !== -1) {
                this.slashClose();
            }

            return false;
        }
    };

    // ── The rows of tools under a box ──────────────────────────────────

    // The box's own tools: icon, label key, shortcut, and what they do.
    var TOOLS = {
        undo: { icon: 'bi-arrow-counterclockwise', label: 'tool_undo', keys: 'Control+Z' },
        redo: { icon: 'bi-arrow-clockwise', label: 'tool_redo', keys: 'Control+Y' },
        bold: { icon: 'bi-type-bold', label: 'tool_bold', keys: 'Control+B', mark: 'bold' },
        italic: { icon: 'bi-type-italic', label: 'tool_italic', keys: 'Control+I', mark: 'italic' },
        strike: { icon: 'bi-type-strikethrough', label: 'tool_strike', keys: 'Control+Shift+X', mark: 'strike' },
        highlight: { icon: 'bi-highlighter', label: 'tool_highlight', keys: 'Control+Shift+H', mark: 'highlight' },
        code: { icon: 'bi-code', label: 'tool_code', keys: 'Control+E', mark: 'code' },
        link: { icon: 'bi-link-45deg', label: 'tool_link', keys: 'Control+K', mark: 'link' },
        bullets: { icon: 'bi-list-ul', label: 'tool_bullets', list: 'bullet' },
        numbers: { icon: 'bi-list-ol', label: 'tool_numbers', list: 'number' },
        quote: { icon: 'bi-quote', label: 'tool_quote', list: 'quote' },
        checklist: { icon: 'bi-ui-checks', label: 'tool_checklist', block: 'checklist' },
        table: { icon: 'bi-table', label: 'tool_table', block: 'table' },
        calc: { icon: 'bi-calculator', label: 'tool_calc', block: 'calc' },
        codeblock: { icon: 'bi-code-slash', label: 'tool_codeblock', block: 'code' }
    };

    // What "/" offers in a note's box (options.slashInserts), in order: the
    // box's own tools, which do the insert. No heading or rule: the server
    // draws neither.
    var SLASH = ['bullets', 'numbers', 'quote', 'checklist', 'table', 'calc', 'codeblock', 'highlight'];

    // A word in plain and in Turkish lower case: "İşaretle" and "işa" meet
    // only in Turkish, "Italic" and "ita" only in plain.
    function slashFold(text) {
        return [String(text).toLowerCase(), String(text).toLocaleLowerCase('tr')];
    }

    // The "/" list at the caret: what is written before it (chips as
    // U+FFFC), whether the caret stands where nothing may be inserted (code,
    // a link, a card), and the tools' labels. A "/" at a line's start or
    // after a space opens it; a word of a label or a tool's name beginning
    // with the word typed after it keeps an insert. Answers null (no list),
    // { start, plain: true } for "//", which types a plain "/", or
    // { start, word, names }.
    function slashInserts(before, blocked, labels) {
        if (blocked) {
            return null;
        }

        var plain = /(^|[\s\uFFFC])\/\/$/.exec(before);

        if (plain) {
            return { start: before.length - 2, plain: true };
        }

        var match = /(^|[\s\uFFFC])\/([^\s\/\uFFFC]{0,20})$/.exec(before);

        if (!match) {
            return null;
        }

        var typed = slashFold(match[2]);
        var names = SLASH.filter(function (name) {
            var words = (name + ' ' + String(labels[name] || '').replace(/\s*\(.*\)\s*$/, '')).split(/\s+/);

            return words.some(function (word) {
                var folded = slashFold(word);

                return (folded[0].indexOf(typed[0]) === 0) || (folded[1].indexOf(typed[1]) === 0);
            });
        });

        return names.length ? { start: before.length - match[2].length - 1, word: match[2], names: names } : null;
    }

    var MAC = /Mac|iPhone|iPad|iPod/.test((navigator && navigator.platform) || '');

    // A table, a checklist, a calculation or code from a row's button, as a
    // card at the caret.
    function insertFrom(editor, kind, options) {
        var scope = options.scope || function () { return editor.markup(); };
        var done = function (markup) {
            if (markup) {
                editor.insertBlock((kind === 'calc') ? 'code' : kind, markup);
            }
        };

        if (kind === 'checklist') {
            openChecklist('', done);
        } else if (kind === 'table') {
            openTable({ columns: 3, rows: 3 }, done, { insertLabel: options.insertLabel, scope: scope });
        } else if (kind === 'calc') {
            openCalc('', done, { insertLabel: options.insertLabel, scope: scope });
        } else {
            openCode('', done);
        }
    }

    /**
     * A row of tools for a writing box (an Editor), in the markup the rows
     * had when each screen drew its own: btn btn-sm btn-text theme-secondary icon buttons in
     * a row of the caller's class.
     *
     * items, in order, each one of:
     *   - the name of one of the box's own tools: undo, redo, bold, italic,
     *     strike, highlight, code, link, bullets, numbers, quote, checklist, table,
     *     calc, codeblock;
     *   - a node the caller made (attach, emoji, voice, send), put in as it is;
     *   - an array of those, drawn as a group of options.groupClass.
     * options: className (the row's class), groupClass, label (the row's name
     * for a screen reader), insertLabel and scope (the table and calculation
     * windows; scope defaults to the box's own text).
     *
     * The row is a toolbar: the arrow keys, Home and End move between its
     * buttons, a click leaves the focus and the selection in the text, the
     * marks show as pressed for the caret or the selection, and undo and redo
     * are off while there is nothing to take back. Buttons the caller adds to
     * the row later take part as well.
     */
    function toolbar(editor, items, options) {
        var opts = options || {};
        var row = el('div', (opts.className || 'ws-note-actions') + ' ws-rich-tools');
        var pressed = {};
        var steps = {};

        row.setAttribute('role', 'toolbar');
        row.setAttribute('aria-label', opts.label || t('tool_bar'));

        function make(name) {
            var spec = Object.prototype.hasOwnProperty.call(TOOLS, name) ? TOOLS[name] : null;

            if (!spec) {
                return null;
            }

            var label = t(spec.label);
            var node = button('btn btn-sm btn-text theme-secondary', '', spec.icon, MAC ? label.replace(/Ctrl\+/g, '⌘') : label);

            node.setAttribute('data-ws-tool', name);

            if (spec.keys) {
                node.setAttribute('aria-keyshortcuts', MAC ? spec.keys.replace('Control', 'Meta') : spec.keys);
            }

            // The link opens its tip rather than switching on and off.
            if (spec.mark && (spec.mark !== 'link')) {
                node.setAttribute('aria-pressed', 'false');
                pressed[spec.mark] = node;
            }

            if (spec.list) {
                node.setAttribute('aria-pressed', 'false');
                pressed[name] = node;
            }

            if ((name === 'undo') || (name === 'redo')) {
                steps[name] = node;
            }

            node.addEventListener('click', function () {
                if (name === 'undo') {
                    editor.undo();
                } else if (name === 'redo') {
                    editor.redo();
                } else if (spec.mark) {
                    editor.format(spec.mark);
                } else if (spec.list) {
                    editor.list(name);
                } else {
                    insertFrom(editor, spec.block, opts);
                }
            });

            return node;
        }

        function put(parent, item) {
            if (Array.isArray(item)) {
                var group = el('div', opts.groupClass || 'ws-rich-tool-group');

                group.setAttribute('role', 'group');
                item.forEach(function (one) { put(group, one); });

                if (group.childNodes.length) {
                    parent.appendChild(group);
                }

                return;
            }

            var node = (typeof item === 'string') ? make(item) : item;

            if (node) {
                parent.appendChild(node);
            }
        }

        (items || []).forEach(function (item) { put(row, item); });

        // mousedown, as on the bar over a selection: the text keeps the focus
        // and its selection while a button (or the row between them) is hit.
        row.addEventListener('mousedown', function (event) {
            var target = event.target;

            if (!(target.closest && target.closest('input, textarea, select, [contenteditable="true"]'))) {
                event.preventDefault();
            }
        });

        row.addEventListener('keydown', function (event) {
            var moves = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
            var current = (event.target.closest && event.target.closest('button')) || null;

            if ((moves.indexOf(event.key) === -1) || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || !current || !row.contains(current)) {
                return;
            }

            var list = Array.prototype.filter.call(row.querySelectorAll('button'), function (one) {
                return !one.disabled && (one.getClientRects().length > 0);
            });
            var index = list.indexOf(current);

            if (index === -1) {
                return;
            }

            var next = (event.key === 'Home') ? 0 : ((event.key === 'End') ? list.length - 1
                : (index + ((event.key === 'ArrowLeft') ? list.length - 1 : 1)) % list.length);

            event.preventDefault();
            list[next].focus();
        });

        function refresh() {
            var state = editor.formatState();

            Object.keys(pressed).forEach(function (name) {
                pressed[name].setAttribute('aria-pressed', state[name] ? 'true' : 'false');
            });

            if (steps.undo) {
                steps.undo.disabled = !editor.canUndo();
            }

            if (steps.redo) {
                steps.redo.disabled = !editor.canRedo();
            }
        }

        editor.watch(refresh);
        refresh();

        return row;
    }

    // ── Design windows ─────────────────────────────────────────────────

    function modal(title, size) {
        var node = el('dialog', 'dialog ws-designer dialog-scrollable sm-down:dialog-fullscreen ' + (size || 'dialog-lg'));
        node.innerHTML = '<div class="dialog-header"><h5 class="dialog-title"></h5><button type="button" class="btn-close" data-bs-dismiss="dialog"></button></div>'
            + '<div class="dialog-body"></div><div class="dialog-footer"></div>';
        node.querySelector('.dialog-title').textContent = title;
        node.querySelector('.btn-close').setAttribute('aria-label', t('close'));
        document.body.appendChild(node);

        var instance = window.pgUI ? pgUI.dialog.get(node) : null;

        pgUI.dialog.on(node, 'hidden', function () {
            hideBar();
            hideTip();
            node.remove();
        });

        return {
            node: node,
            body: node.querySelector('.dialog-body'),
            footer: node.querySelector('.dialog-footer'),
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

            var entry = button('btn btn-link pg-link-body-emphasis text-start text-decoration-none ws-ctx-item' + (item.danger ? ' ws-ctx-danger' : ''), item.label, item.icon);
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
        var cancel = button('btn btn-sm btn-text theme-secondary', t('cancel'));
        cancel.setAttribute('data-bs-dismiss', 'dialog');
        win.footer.appendChild(cancel);

        var save = button('btn btn-sm btn-solid theme-primary rounded-pill px-5', isNew ? (insertLabel || t('insert_to_message')) : t('update_block'), 'bi-check2');
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

        var titling = titled(markup, done);

        markup = titling.markup;
        done = titling.done;

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

        var win = modal(t('designer_table'), 'dialog-xl');

        titling.attach(win);
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
        win.body.appendChild(el('div', 'form-text mt-3', t('table_hint')));
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

            if (!isFormula(raw) || !found) {
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

            resultBox.textContent = (isFormula(raw) && found) ? '= ' + found.display : '';
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

        // Formulas (cells starting with =, not a highlight) worked out as
        // they are typed, by the code that works them out in the message
        // itself.
        var calcTimer = 0;
        var calcSerial = 0;

        function recalc() {
            read();

            var grid = [model.head].concat(model.rows);
            var formulas = grid.some(function (row) {
                return row.some(isFormula);
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
                body: JSON.stringify({ action: 'ws_calc', token: CFG.token, grid: grid, scope: options.scope ? options.scope() : '' })
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

        // ── Formula patterns ──

        // A formula into a cell, which then shows it for a last change.
        function setFormula(row, column, formula) {
            read();

            if (row < 0) {
                model.head[column] = formula;
            } else if (model.rows[row]) {
                model.rows[row][column] = formula;
            }

            draw();
            focusCell(row, column);
        }

        function patternItems() {
            var row = current.row;
            var column = current.column;
            var at = row + 2;
            var body = row >= 0;
            var above = row >= 1;
            var last = model.rows.length + 1;
            var cell = function (columnIndex, rowNumber) { return sheetLetter(columnIndex) + rowNumber; };
            var left = (column >= 1) ? cell(column - 1, at) : '';
            var left2 = (column >= 2) ? cell(column - 2, at) : '';
            var columnAbove = above ? cell(column, 2) + ':' + cell(column, at - 1) : '';
            var put = function (formula) {
                return function () { setFormula(row, column, formula); };
            };

            return [
                { icon: 'bi-arrow-up', label: t('table_fx_col_sum'), disabled: !above, action: put('=' + calcName('SUM') + '(' + columnAbove + ')') },
                { icon: 'bi-arrow-up', label: t('table_fx_col_avg'), disabled: !above, action: put('=' + calcName('AVERAGE') + '(' + columnAbove + ')') },
                { icon: 'bi-arrow-up', label: t('table_fx_col_max'), disabled: !above, action: put('=' + calcName('MAX') + '(' + columnAbove + ')') },
                { icon: 'bi-arrow-up', label: t('table_fx_col_min'), disabled: !above, action: put('=' + calcName('MIN') + '(' + columnAbove + ')') },
                '-',
                { icon: 'bi-arrow-left', label: t('table_fx_row_sum'), disabled: !body || column < 1, action: put('=' + calcName('SUM') + '(' + cell(0, at) + ':' + left + ')') },
                { icon: 'bi-x-lg', label: t('table_fx_mul'), disabled: !body || column < 2, action: put('=' + left2 + '*' + left) },
                { icon: 'bi-dash-lg', label: t('table_fx_diff'), disabled: !body || column < 2, action: put('=' + left2 + '-' + left) },
                { icon: 'bi-graph-up-arrow', label: t('table_fx_change'), disabled: !body || column < 2, action: put('=' + calcName('ROUND') + '((' + left + '-' + left2 + ')/' + left2 + '*100; 1)') },
                '-',
                { icon: 'bi-percent', label: t('table_fx_add_pct'), disabled: !body || column < 1, action: put('=' + left + '*(1+20%)') },
                { icon: 'bi-tag', label: t('table_fx_discount'), disabled: !body || column < 1, action: put('=' + left + '*(1-10%)') },
                { icon: 'bi-pie-chart', label: t('table_fx_share'), disabled: !body || column < 1, action: put('=' + calcName('ROUND') + '(' + left + '/' + calcName('SUM') + '(' + sheetLetter(column - 1) + '$2:' + sheetLetter(column - 1) + '$' + last + ')*100; 1)') },
                { icon: 'bi-signpost-split', label: t('table_fx_if'), disabled: !body || column < 1, action: put('=' + calcName('IF') + '(' + left + '>1000; ' + left + '*5%; 0)') }
            ];
        }

        // A last row that adds up every column holding figures; the first
        // column names it when it holds words.
        function addTotalRow() {
            read();

            if (model.rows.length >= MAX_ROWS) {
                return;
            }

            var last = model.rows.length + 1;
            var figure = /^[-+]?[\d.,\s]*\d[\d.,\s]*%?$/;
            var row = model.head.map(function (head, column) {
                var numbers = model.rows.some(function (cells) {
                    var value = String(cells[column] || '').replace(/[₺$€£]|\bTL\b/g, '').trim();

                    return isFormula(value) || figure.test(value);
                });

                return numbers ? '=' + calcName('SUM') + '(' + sheetLetter(column) + '2:' + sheetLetter(column) + last + ')' : '';
            });

            if (row[0] === '') {
                row[0] = '**' + t('table_total') + '**';
            }

            model.rows.push(row);
            draw();
            focusCell(model.rows.length - 1, Math.max(0, row.findIndex(isFormula)));
        }

        // The formula of the cell copied to the cells under it, its row
        // numbers moving with it ($2 stays where it is).
        function shiftRows(formula, delta) {
            return formula.replace(/(^|[^A-Za-z0-9_@$])(\$?)([A-Za-z]{1,2})(\$?)([0-9]{1,4})(?![A-Za-z0-9_(])/g, function (all, lead, columnMark, letters, rowMark, digits) {
                return lead + columnMark + letters + rowMark + (rowMark ? digits : String(Math.max(1, +digits + delta)));
            });
        }

        function fillDown() {
            read();

            var row = current.row;
            var column = current.column;
            var source = (row >= 0 && model.rows[row]) ? String(model.rows[row][column] || '') : '';

            if (!isFormula(source)) {
                formulaInput.classList.add('is-invalid');
                window.setTimeout(function () { formulaInput.classList.remove('is-invalid'); }, 1200);
                return;
            }

            for (var below = row + 1; below < model.rows.length; below++) {
                model.rows[below][column] = shiftRows(source, below - row);
            }

            draw();
            focusCell(row, column);
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
                menu(event.clientX, event.clientY, cellMenuItems(row, column), win.node);
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

            var addRowButton = button('btn btn-sm btn-text theme-secondary', t('add_row'), 'bi-plus-lg');
            addRowButton.addEventListener('mousedown', function (event) { event.preventDefault(); });
            addRowButton.addEventListener('click', function () { addRow(current.row + 1 > 0 ? current.row + 1 : model.rows.length); });
            addRowButton.disabled = model.rows.length >= MAX_ROWS;
            tools.appendChild(addRowButton);

            var addColumnButton = button('btn btn-sm btn-text theme-secondary', t('add_column'), 'bi-plus-lg');
            addColumnButton.addEventListener('mousedown', function (event) { event.preventDefault(); });
            addColumnButton.addEventListener('click', function () { addColumn(current.column + 1); });
            addColumnButton.disabled = model.head.length >= MAX_COLUMNS;
            tools.appendChild(addColumnButton);

            tools.appendChild(el('span', 'ws-table-tools-gap'));

            [['left', 'bi-text-left', t('align_left')], ['center', 'bi-text-center', t('align_center')], ['right', 'bi-text-right', t('align_right')]].forEach(function (entry) {
                var align = button('btn btn-sm btn-text theme-secondary' + (model.align[current.column] === entry[0] ? ' active' : ''), '', entry[1], entry[2]);
                align.addEventListener('mousedown', function (event) { event.preventDefault(); });
                align.addEventListener('click', function () { setAlign(current.column, (model.align[current.column] === entry[0]) ? '' : entry[0]); });
                tools.appendChild(align);
            });

            tools.appendChild(el('span', 'ws-table-tools-gap'));

            tools.appendChild(el('span', 'ws-table-tools-gap'));

            // Formulas ready to use for the cell the caret is in.
            var formulas = button('btn btn-sm btn-text theme-secondary', t('table_fx'), 'bi-plus-slash-minus');
            formulas.addEventListener('mousedown', function (event) { event.preventDefault(); });
            formulas.addEventListener('click', function () {
                var box = formulas.getBoundingClientRect();

                menu(box.left, box.bottom + 4, patternItems(), win.node);
            });
            tools.appendChild(formulas);

            var totalRow = button('btn btn-sm btn-text theme-secondary', '', 'bi-calculator', t('table_total_row'));
            totalRow.addEventListener('mousedown', function (event) { event.preventDefault(); });
            totalRow.addEventListener('click', addTotalRow);
            totalRow.disabled = model.rows.length >= MAX_ROWS;
            tools.appendChild(totalRow);

            var fill = button('btn btn-sm btn-text theme-secondary', '', 'bi-arrow-down-square', t('table_fill_down'));
            fill.addEventListener('mousedown', function (event) { event.preventDefault(); });
            fill.addEventListener('click', fillDown);
            fill.disabled = current.row < 0 || current.row >= model.rows.length - 1;
            tools.appendChild(fill);

            tools.appendChild(el('span', 'ws-table-tools-gap'));

            var dropRow = button('btn btn-sm btn-text theme-secondary ws-tool-danger', '', 'bi-dash-square', t('delete_row'));
            dropRow.addEventListener('mousedown', function (event) { event.preventDefault(); });
            dropRow.addEventListener('click', function () { removeRow(current.row); });
            dropRow.disabled = current.row < 0 || model.rows.length <= 1;
            tools.appendChild(dropRow);

            var dropColumn = button('btn btn-sm btn-text theme-secondary ws-tool-danger', '', 'bi-layout-sidebar-inset-reverse', t('delete_column'));
            dropColumn.addEventListener('mousedown', function (event) { event.preventDefault(); });
            dropColumn.addEventListener('click', function () { removeColumn(current.column); });
            dropColumn.disabled = model.head.length <= 1;
            tools.appendChild(dropColumn);
        }

        draw();

        footerButtons(win, !!options.fresh || !(typeof markup === 'string' && markup), function () {
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

        pgUI.dialog.on(win.node, 'shown', function () { focusCell(-1, 0); });
        win.show();
    }

    // ── The checklist window ──

    function openChecklist(markup, done) {
        var items = [];
        var titling = titled(markup, done);

        markup = titling.markup;
        done = titling.done;

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

        var win = modal(t('designer_checklist'), 'dialog-lg');

        titling.attach(win);
        var list = el('div', 'ws-check-edit');

        win.body.appendChild(list);

        var add = button('btn btn-sm btn-text theme-secondary mt-1', t('add_item'), 'bi-plus-lg');
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
            var box = el('input', 'check');

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

            var up = button('btn btn-sm btn-text theme-secondary', '', 'bi-arrow-up', t('move_up'));
            up.addEventListener('click', function () {
                if (row.previousElementSibling) {
                    list.insertBefore(row, row.previousElementSibling);
                }
            });
            row.appendChild(up);

            var down = button('btn btn-sm btn-text theme-secondary', '', 'bi-arrow-down', t('move_down'));
            down.addEventListener('click', function () {
                if (row.nextElementSibling) {
                    list.insertBefore(row.nextElementSibling, row);
                }
            });
            row.appendChild(down);

            var remove = button('btn btn-sm btn-text theme-secondary ws-tool-danger', '', 'bi-x-lg', t('remove'));
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

        pgUI.dialog.on(win.node, 'shown', function () { focusRow(rows()[0]); });
        win.show();
    }

    // ── The code window ──

    var LANGUAGES = ['php', 'js', 'ts', 'html', 'css', 'sql', 'json', 'bash', 'python', 'xml', 'yaml', 'text'];

    function openCode(markup, done) {
        var language = '';
        var code = '';
        var titling = titled(markup, done);

        markup = titling.markup;
        done = titling.done;

        if (markup) {
            var lines = markup.split('\n');
            language = fenceOpen(lines[0]) || '';
            code = lines.slice(1, fenceClose(lines[lines.length - 1]) ? -1 : undefined).join('\n');
        }

        var win = modal(t('designer_code'), 'dialog-lg');

        titling.attach(win);
        var row = el('div', 'd-flex align-items-center gap-3 mb-3');
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

        pgUI.dialog.on(win.node, 'shown', function () { area.focus(); });
        win.show();
    }

    // ── The calculation window: a ```hesap block ──

    // A function's name as the site writes it (TOPLA or SUM).
    function calcName(canonical) {
        var found = (CALC.functions || []).filter(function (entry) { return entry.canonical === canonical; })[0];

        return found ? found.name : canonical;
    }

    // Where "Name = expression" splits: the first = that is not part of a
    // comparison (as ws_calc_assignment_at() reads it).
    function calcAssignmentAt(line) {
        for (var i = 0; i < line.length; i++) {
            if (line.charAt(i) !== '=') {
                continue;
            }

            if (i > 0 && '<>!='.indexOf(line.charAt(i - 1)) !== -1) {
                continue;
            }

            if (line.charAt(i + 1) === '=') {
                i++;
                continue;
            }

            return i;
        }

        return -1;
    }

    // The names a block gives, for the window to offer.
    function calcNames(text) {
        var names = [];

        String(text || '').split('\n').forEach(function (line) {
            line = line.trim();

            if (line === '' || line.charAt(0) === '#' || line.indexOf('//') === 0) {
                return;
            }

            if (line.charAt(0) === '_') {
                line = line.slice(1).trim();
            }

            var at = calcAssignmentAt(line);

            if (at === -1) {
                var heading = /^([^:=()<>]*[^\s:=()<>][^:=()<>]*?)\s*:\s*[^\s:]*\s*$/.exec(line);

                if (heading) {
                    names.push(heading[1].trim());
                }

                return;
            }

            var label = line.slice(0, at).trim();

            if (label !== '') {
                names.push(label);
            }
        });

        return names;
    }

    // The settings after the language: decimals and unit, in either language.
    function calcOptions(info) {
        var options = { decimals: '', unit: '' };

        String(info || '').replace(/\s*([=:])\s*/g, '$1').split(/[\s;,]+/).forEach(function (pair) {
            var match = /^([^=:]+)[=:](.+)$/.exec(pair);

            if (!match) {
                return;
            }

            var key = match[1].toLowerCase().replace(/ı/g, 'i');

            if (['ondalik', 'decimals', 'decimal', 'places'].indexOf(key) !== -1 && /^[0-9]$/.test(match[2])) {
                options.decimals = match[2];
            } else if (['birim', 'unit'].indexOf(key) !== -1) {
                options.unit = match[2].slice(0, 8);
            }
        });

        return options;
    }

    function calcInfo(options) {
        var keys = CALC.keys || { decimals: 'decimals', unit: 'unit' };
        var parts = [];

        if (options.decimals !== '') {
            parts.push(keys.decimals + '=' + options.decimals);
        }

        var unit = String(options.unit || '').replace(/[\s=;,`<>"'&]/g, '').slice(0, 8);

        if (unit !== '') {
            parts.push(keys.unit + '=' + unit);
        }

        return parts.join(' ');
    }

    function openCalc(markup, done, options) {
        options = options || {};

        var code = '';
        var info = '';
        var titling = titled(markup, done);

        markup = titling.markup;
        done = titling.done;

        if (markup) {
            var lines = markup.split('\n');
            info = fenceInfo(lines[0]);
            code = lines.slice(1, fenceClose(lines[lines.length - 1]) ? -1 : undefined).join('\n');
        }

        var settings = calcOptions(info);
        var win = modal(t('designer_calc'), 'dialog-lg');

        titling.attach(win);

        var tools = el('div', 'ws-calc-tools');
        var area = el('textarea', 'form-control ws-code-edit ws-calc-edit');
        var namesRow = el('div', 'ws-calc-names');
        var shown = el('div', 'ws-calc-preview');
        var outside = [];
        var serial = 0;

        area.rows = 10;
        area.spellcheck = false;
        area.value = code || (options.sample || '');
        area.placeholder = t('calc_placeholder');
        area.setAttribute('aria-label', t('designer_calc'));

        // Text put in where the caret is, through the browser's own editing
        // so that Ctrl+Z in the box takes it back.
        function put(text, selectFrom, selectTo) {
            area.focus();

            var start = area.selectionStart;

            if (!command('insertText', text)) {
                area.setRangeText(text, area.selectionStart, area.selectionEnd, 'end');
                area.dispatchEvent(new Event('input'));
            }

            if (selectFrom !== undefined) {
                area.selectionStart = start + selectFrom;
                area.selectionEnd = start + ((selectTo === undefined) ? selectFrom : selectTo);
            }
        }

        // The whole lines the caret or the selection is on.
        function selectLines() {
            var value = area.value;
            var from = value.lastIndexOf('\n', area.selectionStart - 1) + 1;
            var to = value.indexOf('\n', Math.max(area.selectionStart, area.selectionEnd - ((area.selectionEnd > area.selectionStart) ? 1 : 0)));

            if (to === -1) {
                to = value.length;
            }

            area.focus();
            area.selectionStart = from;
            area.selectionEnd = to;

            return value.slice(from, to);
        }

        function tool(iconName, label, title, action, extra) {
            var node = button('btn btn-sm btn-text theme-secondary' + (extra ? ' ' + extra : ''), label, iconName, title);

            node.addEventListener('mousedown', function (event) { event.preventDefault(); });
            node.addEventListener('click', function (event) { action(node, event); });
            tools.appendChild(node);

            return node;
        }

        function gap() {
            tools.appendChild(el('span', 'ws-table-tools-gap'));
        }

        function below(node) {
            var box = node.getBoundingClientRect();

            return { x: box.left, y: box.bottom + 4 };
        }

        // Templates: an empty box takes one whole, a written one gets it
        // after what is there.
        tool('bi-journal-text', t('calc_template'), '', function (node) {
            var at = below(node);

            menu(at.x, at.y, (CALC.templates || []).map(function (template) {
                return {
                    label: template.label,
                    action: function () {
                        if (area.value.trim() === '') {
                            area.focus();
                            area.select();
                            put(template.body);
                        } else {
                            area.selectionStart = area.selectionEnd = area.value.length;
                            put((/\n$/.test(area.value) ? '\n' : '\n\n') + template.body);
                        }
                    }
                };
            }), win.node);
        });

        tool('bi-plus-slash-minus', t('calc_functions'), '', function (node) {
            var at = below(node);

            menu(at.x, at.y, (CALC.functions || []).map(function (entry) {
                return {
                    label: entry.name + ' — ' + entry.help,
                    action: function () {
                        var selected = area.value.slice(area.selectionStart, area.selectionEnd);

                        put(entry.name + '(' + selected + ')', entry.name.length + 1 + selected.length);
                    }
                };
            }), win.node);
        });

        gap();

        [['+', ' + ', t('calc_add')], ['−', ' - ', t('calc_subtract')], ['×', ' * ', t('calc_multiply')], ['÷', ' / ', t('calc_divide')], ['%', '%', t('calc_percent')]].forEach(function (entry) {
            tool('', entry[0], entry[2], function () { put(entry[1]); }, 'ws-calc-op');
        });

        tool('', '( )', t('calc_brackets'), function () {
            var selected = area.value.slice(area.selectionStart, area.selectionEnd);

            put('(' + selected + ')', 1 + selected.length);
        }, 'ws-calc-op');

        gap();

        // A group around the selected lines, or a heading above the caret's
        // line; its name is selected to be typed over.
        tool('bi-collection', t('calc_group'), t('calc_group_help'), function (node) {
            var at = below(node);

            menu(at.x, at.y, (CALC.groups || [{ name: '', label: t('calc_group') }]).map(function (entry, index) {
                return {
                    label: entry.label,
                    action: function () {
                        var name = t('calc_group_name');
                        var heading = name + ':' + ((index > 0 && entry.name) ? ' ' + entry.name : '');
                        var text = selectLines();
                        var after = area.value.slice(area.selectionEnd);
                        var close = (text.trim() !== '' && after !== '' && !/^\n\s*(\n|$)/.test(after)) ? '\n' : '';

                        put(heading + '\n' + text + close, 0, name.length);
                    }
                };
            }), win.node);
        });

        tool('bi-eye-slash', t('calc_hide'), t('calc_hide_help'), function () {
            var text = selectLines();
            var all = text.split('\n').filter(function (line) { return line.trim() !== ''; });
            var hidden = all.length && all.every(function (line) { return /^\s*_/.test(line); });

            put(text.split('\n').map(function (line) {
                if (line.trim() === '' || /^\s*(#|\/\/)/.test(line)) {
                    return line;
                }

                return hidden ? line.replace(/^(\s*)_\s*/, '$1') : line.replace(/^(\s*)/, '$1_');
            }).join('\n'));
        });

        tool('bi-arrows-vertical', t('calc_gap'), t('calc_gap_help'), function () {
            selectLines();
            area.selectionStart = area.selectionEnd;
            put('\n');
        });

        tool('bi-chat-left-text', t('calc_remark'), t('calc_remark_help'), function () {
            var text = selectLines();

            if (text.trim() === '') {
                put('# ');
            } else {
                area.selectionStart = area.selectionEnd;
                put('\n# ');
            }
        });

        // How the values are shown.
        var settingsRow = el('div', 'ws-calc-settings');
        var decimals = el('select', 'form-control form-control-sm');
        var unit = el('input', 'form-control form-control-sm');
        var decimalsId = 'ws-calc-decimals-' + Date.now();

        [['', t('calc_decimals_auto')], ['0', '0'], ['1', '1'], ['2', '2'], ['3', '3'], ['4', '4']].forEach(function (entry) {
            var option = el('option', '', entry[1]);
            option.value = entry[0];
            decimals.appendChild(option);
        });

        decimals.id = decimalsId;
        decimals.value = settings.decimals;
        unit.id = decimalsId + '-unit';
        unit.maxLength = 8;
        unit.value = settings.unit;
        unit.placeholder = t('calc_unit_placeholder');

        var decimalsLabel = el('label', 'form-label small mb-0', t('calc_decimals'));
        var unitLabel = el('label', 'form-label small mb-0', t('calc_unit'));

        decimalsLabel.htmlFor = decimals.id;
        unitLabel.htmlFor = unit.id;
        settingsRow.appendChild(decimalsLabel);
        settingsRow.appendChild(decimals);
        settingsRow.appendChild(unitLabel);
        settingsRow.appendChild(unit);

        win.body.appendChild(tools);
        win.body.appendChild(settingsRow);
        win.body.appendChild(el('div', 'form-text mt-0 mb-3', t('calc_hint')));
        win.body.appendChild(area);
        win.body.appendChild(namesRow);
        win.body.appendChild(shown);

        // The names of this block and of the rest of the text, a click
        // away.
        function drawNames() {
            var seen = {};
            var names = calcNames(area.value).concat(outside).filter(function (name) {
                var key = name.toLowerCase();

                if (seen[key]) {
                    return false;
                }

                seen[key] = true;

                return true;
            });

            namesRow.innerHTML = '';
            namesRow.classList.toggle('d-none', !names.length);

            if (!names.length) {
                return;
            }

            namesRow.appendChild(el('span', 'ws-calc-names-label', t('calc_names')));

            names.slice(0, 40).forEach(function (name) {
                var chip = button('btn btn-sm ws-calc-name', name);

                chip.addEventListener('mousedown', function (event) { event.preventDefault(); });
                chip.addEventListener('click', function () { put(name); });
                namesRow.appendChild(chip);
            });
        }

        function fence() {
            var written = calcInfo({ decimals: decimals.value, unit: unit.value });

            return '```hesap' + (written ? ' ' + written : '');
        }

        // Worked out by the code that works it out in the message itself.
        function preview() {
            var mine = ++serial;
            var body = area.value.replace(/\r\n?/g, '\n');

            drawNames();

            if (!CFG.api_url || body.trim() === '') {
                shown.innerHTML = '';
                return;
            }

            fetch(CFG.api_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'ws_calc', token: CFG.token, body: fence() + '\n' + body + '\n```', scope: options.scope ? options.scope() : '' })
            }).then(function (response) {
                return response.json();
            }).then(function (json) {
                if ((mine === serial) && json && (json.status === 'success')) {
                    shown.innerHTML = json.data.html || '';
                    outside = json.data.names || [];
                    drawNames();
                }
            }).catch(function () {});
        }

        var timer = 0;
        var later = function () {
            clearTimeout(timer);
            timer = setTimeout(preview, 300);
        };

        area.addEventListener('input', later);
        decimals.addEventListener('change', later);
        unit.addEventListener('input', later);

        footerButtons(win, !markup, function () {
            var body = area.value.replace(/\r\n?/g, '\n').replace(/\s+$/, '');

            win.hide();
            done((body.trim() === '') ? '' : fence() + '\n' + body.replace(/^(\s*)```/gm, '$1​```') + '\n```');
        }, options.insertLabel);

        pgUI.dialog.on(win.node, 'shown', function () { area.focus(); });
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

    // ── A reader's own figures in a calculation ────────────────────────
    //
    // A plain figure of a calculation in a message or a note can be clicked
    // and changed; the blocks and tables of the same text are worked out
    // again with it by the server (ws_calc_live()). Nothing is stored, and
    // the text goes back as written with the block's Reset button.

    function liveUnits(root) {
        return Array.prototype.filter.call(root.querySelectorAll('[data-ws-calc], [data-ws-calc-grid]'), function (node) {
            return !node.closest('.ws-rich');
        });
    }

    function liveRoot(node) {
        return node.closest('.ws-msg-body') || node.parentNode || document.body;
    }

    function liveData(node) {
        try {
            return JSON.parse(node.getAttribute('data-ws-calc') || '{}') || {};
        } catch (error) {
            return {};
        }
    }

    function liveRun(root) {
        var units = liveUnits(root);

        if (!units.length || !CFG.api_url) {
            return;
        }

        var payload = units.map(function (node) {
            if (node.hasAttribute('data-ws-calc')) {
                var data = liveData(node);

                return { lines: data.lines || [], info: data.info || '' };
            }

            try {
                return { grid: JSON.parse(node.getAttribute('data-ws-calc-grid') || '[]') };
            } catch (error) {
                return {};
            }
        });

        fetch(CFG.api_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'ws_calc', token: CFG.token, live: payload })
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (!json || (json.status !== 'success')) {
                return;
            }

            (json.data.units || []).forEach(function (unit, index) {
                var node = units[index];

                if (!node || !node.parentNode) {
                    return;
                }

                if ((unit.html !== undefined) && node.hasAttribute('data-ws-calc')) {
                    var holder = el('div');
                    holder.innerHTML = unit.html;

                    var fresh = holder.firstElementChild;

                    if (!fresh) {
                        return;
                    }

                    var original = node.getAttribute('data-ws-calc-original') || node.getAttribute('data-ws-calc');
                    var tried = fresh.getAttribute('data-ws-calc') !== original;

                    fresh.setAttribute('data-ws-calc-original', original);
                    fresh.classList.toggle('ws-calc-tried', tried);

                    if (tried) {
                        var reset = button('btn btn-sm btn-link ws-calc-reset', t('calc_live_reset'), 'bi-arrow-counterclockwise', t('calc_live_note'));
                        reset.setAttribute('data-ws-calc-reset', '1');
                        fresh.querySelector('.ws-calc-block-head').appendChild(reset);
                    }

                    node.replaceWith(fresh);
                } else if (unit.cells && node.hasAttribute('data-ws-calc-grid')) {
                    unit.cells.forEach(function (cell) {
                        var span = node.querySelector('[data-ws-cell="' + cell.r + ':' + cell.c + '"]');

                        if (!span) {
                            return;
                        }

                        if (!span.hasAttribute('data-ws-shown')) {
                            span.setAttribute('data-ws-shown', span.textContent);
                        }

                        span.textContent = cell.display;
                        span.classList.toggle('ws-calc-error', !!cell.error);
                        span.classList.toggle('ws-calc-tried', cell.display !== span.getAttribute('data-ws-shown'));
                    });
                }
            });
        }).catch(function () {});
    }

    function liveEdit(block, figure) {
        var data = liveData(block);
        var index = +figure.getAttribute('data-ws-calc-input');
        var line = String((data.lines || [])[index] || '');
        var at = line.indexOf('=');
        var head = (at >= 0) ? line.slice(0, at).trim() : '';
        var value = ((at >= 0) ? line.slice(at + 1) : line).trim();
        var shown = figure.textContent;
        var input = el('input', 'form-control form-control-sm ws-calc-live-input');
        var finished = false;

        input.type = 'text';
        input.inputMode = 'decimal';
        input.value = value;
        input.setAttribute('aria-label', head || t('calc_live_try'));
        input.title = t('calc_live_note');

        figure.textContent = '';
        figure.appendChild(input);
        input.focus();
        input.select();

        function finish(keep) {
            if (finished) {
                return;
            }

            finished = true;

            var typed = input.value.trim();

            input.remove();
            figure.textContent = shown;

            if (!keep || typed === '' || typed === value || !data.lines) {
                return;
            }

            data.lines[index] = ((head !== '') ? head + ' = ' : '') + typed;

            if (!block.hasAttribute('data-ws-calc-original')) {
                block.setAttribute('data-ws-calc-original', block.getAttribute('data-ws-calc'));
            }

            block.setAttribute('data-ws-calc', JSON.stringify(data));
            liveRun(liveRoot(block));
        }

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                finish(true);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                finish(false);
            }
        });

        input.addEventListener('blur', function () { finish(true); });
        input.addEventListener('click', function (event) { event.stopPropagation(); });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!target.closest || !CFG.api_url) {
            return;
        }

        var reset = target.closest('[data-ws-calc-reset]');

        if (reset) {
            var root = liveRoot(reset);

            event.preventDefault();

            liveUnits(root).forEach(function (node) {
                if (node.hasAttribute('data-ws-calc-original')) {
                    node.setAttribute('data-ws-calc', node.getAttribute('data-ws-calc-original'));
                }
            });

            liveRun(root);
            return;
        }

        var figure = target.closest('[data-ws-calc-input]');

        if (!figure || figure.closest('.ws-rich') || figure.querySelector('input')) {
            return;
        }

        var block = figure.closest('[data-ws-calc]');

        if (block) {
            event.preventDefault();
            liveEdit(block, figure);
        }
    });

    window.PGWsEditor = {
        create: function (options) { return new Editor(options); },
        toolbar: toolbar,
        open: open,
        openTable: openTable,
        openChecklist: openChecklist,
        openCode: openCode,
        openCalc: openCalc,
        serialize: serialize,
        hideFloating: function () { hideBar(); hideTip(); }
    };
})();
