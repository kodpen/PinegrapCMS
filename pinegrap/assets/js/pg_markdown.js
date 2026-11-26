/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Markdown reader of the designer's Markdown component
 * (window.PGMarkdown): the browser twin of pg_markdown_html()
 * (includes/fn/markdown.php), drawing the same markup for the canvas, the
 * preview and the code view. Loaded as the designer part 'markdown'.
 *
 * Every piece of the author's text is escaped before it is marked up, so no
 * HTML the author wrote reaches the page. A link keeps an http, https,
 * mailto or tel address, or one on this site; a picture an http or https
 * one, or one on this site; anything else is drawn as its words. The rules
 * are listed in the PHP file; tests/markdown_test.php runs this file in
 * Node and holds the two equal, so a change goes into both.
 *
 * Nothing runs at load but the definition of window.PGMarkdown.
 *
 * Memory: docs/memory/designer/markdown-component.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    function esc(text) {
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function trimTabs(s) { return s.replace(/^[ \t]+|[ \t]+$/g, ''); }

    // Twin of pg_markdown_safe_url().
    function safeUrl(url, picture) {
        url = String(url).replace(/[\x00-\x20\x7F]+/g, '');
        if (url === '') return false;
        var allowed = picture ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'];
        var m = /^([A-Za-z][A-Za-z0-9+.\-]*):/.exec(url);
        if (m) return allowed.indexOf(m[1].toLowerCase()) !== -1 ? url : false;
        return url.split(/[\/?#]/)[0].indexOf(':') === -1 ? url : false;
    }

    function indentOf(line) {
        var width = 0;
        for (var i = 0; i < line.length; i++) {
            if (line.charAt(i) === ' ') width++;
            else if (line.charAt(i) === '\t') width += 4;
            else break;
        }
        return width;
    }

    function dedent(line, width) {
        var taken = 0, i = 0;
        while (i < line.length && taken < width && (line.charAt(i) === ' ' || line.charAt(i) === '\t')) {
            taken += line.charAt(i) === '\t' ? 4 : 1;
            i++;
        }
        return line.slice(i);
    }

    function itemOf(line) {
        var m = /^([ \t]*)([-*+]|[0-9]{1,9}[.)])[ \t]+([^\n]*)$/.exec(line);
        if (!m) return null;
        var ordered = /^[0-9]/.test(m[2]), indent = indentOf(m[1]);
        return { indent: indent, ordered: ordered, start: ordered ? parseInt(m[2], 10) : 1, column: indent + m[2].length + 1, text: m[3] };
    }

    function blank(line) { return trimTabs(line) === ''; }

    function fenceOf(line) {
        var m = /^ {0,3}(`{3,}|~{3,})[ \t]*([^`\n]*)$/.exec(line);
        return m ? [m[1], trimTabs(m[2])] : null;
    }

    function rule(line) { return /^ {0,3}([-*_])(?:[ \t]*\1){2,}[ \t]*$/.test(line); }

    function headingOf(line) {
        var m = /^ {0,3}(#{1,6})[ \t]+([^\n]*)$/.exec(line);
        return m ? [m[1].length, trimTabs(m[2].replace(/[ \t]+#+[ \t]*$/, ''))] : null;
    }

    function cellsOf(line) {
        line = trimTabs(line.split('\\|').join('\x04'));
        if (line.charAt(0) === '|') line = line.slice(1);
        if (line.slice(-1) === '|') line = line.slice(0, -1);
        return line.split('|').map(function (cell) { return trimTabs(cell.split('\x04').join('\\|')); });
    }

    function tableAt(lines, i) {
        if (i + 1 >= lines.length || lines[i].indexOf('|') === -1) return false;
        var delimiter = lines[i + 1];
        if (delimiter.indexOf('|') === -1 && delimiter.indexOf('-') === -1) return false;
        if (!/^[ \t]*\|?[ \t]*:?-+:?[ \t]*(\|[ \t]*:?-+:?[ \t]*)*\|?[ \t]*$/.test(delimiter)) return false;
        return cellsOf(lines[i]).length === cellsOf(delimiter).length;
    }

    function blockStart(lines, i) {
        var line = lines[i];
        return fenceOf(line) !== null || headingOf(line) !== null || rule(line) ||
            /^ {0,3}>/.test(line) || itemOf(line) !== null || tableAt(lines, i);
    }

    var URL = '((?:[^()\\x00-\\x20]|\\([^()\\x00-\\x20]*\\))*)(?:[ \\t]+"([^"\\n]*)")?';
    var RX_IMAGE = new RegExp('!\\[([^\\]\\n]*)\\]\\(' + URL + '\\)', 'g');
    var RX_LINK = new RegExp('\\[([^\\]\\n]+)\\]\\(' + URL + '\\)', 'g');

    function marks(s) {
        s = s.replace(/\*\*([^ \t\n*](?:[^\n]*?[^ \t\n*])?)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/(^|[^A-Za-z0-9_])__([^ \t\n_](?:[^\n]*?[^ \t\n_])?)__(?![A-Za-z0-9_])/g, '$1<strong>$2</strong>');
        s = s.replace(/~~([^ \t\n~](?:[^\n]*?[^ \t\n~])?)~~/g, '<del>$1</del>');
        s = s.replace(/\*([^ \t\n*](?:[^\n]*?[^ \t\n*])?)\*/g, '<em>$1</em>');
        return s.replace(/(^|[^A-Za-z0-9_])_([^ \t\n_](?:[^\n]*?[^ \t\n_])?)_(?![A-Za-z0-9_])/g, '$1<em>$2</em>');
    }

    // Twin of pg_markdown_inline().
    function inline(text) {
        var html = [], plain = [];
        function put(markup, words) { html.push(markup); plain.push(words); return '\x01' + (html.length - 1) + '\x02'; }
        function backPlain(s) { return s.replace(/\x01([0-9]+)\x02/g, function (m, n) { return plain[+n]; }); }
        function backHtml(s) { return s.replace(/\x01([0-9]+)\x02/g, function (m, n) { return html[+n]; }); }

        text = text.replace(/`([^`\n]+)`/g, function (m, code) { return put('<code>' + esc(code) + '</code>', code); });
        text = text.replace(/\\([!-\/:-@\[-`{-~])/g, function (m, c) { return put(esc(c), c); });
        text = text.replace(RX_IMAGE, function (m, alt, src, title) {
            alt = backPlain(alt);
            src = safeUrl(backPlain(src), true);
            if (src === false) return put(esc(alt), alt);
            title = title !== undefined ? backPlain(title) : '';
            return put('<img src="' + esc(src) + '" alt="' + esc(alt) + '"' + (title !== '' ? ' title="' + esc(title) + '"' : '') +
                ' class="img-fluid" loading="lazy">', alt);
        });
        text = text.replace(RX_LINK, function (m, label, href, title) {
            var words = backPlain(label);
            label = backHtml(marks(esc(label)));
            href = safeUrl(backPlain(href), false);
            if (href === false) return put(label, words);
            title = title !== undefined ? backPlain(title) : '';
            return put('<a href="' + esc(href) + '"' + (title !== '' ? ' title="' + esc(title) + '"' : '') + '>' + label + '</a>', words);
        });
        text = text.replace(/<((?:https?:\/\/|mailto:)[^\x00-\x20<>]+)>/g, function (m, address) {
            address = backPlain(address);
            return put('<a href="' + esc(address) + '">' + esc(address) + '</a>', address);
        });
        return backHtml(marks(esc(text))).split('\x03').join('<br>');
    }

    function paragraph(lines) {
        return inline(lines.map(function (line, k) {
            line = line.replace(/^[ \t]+/, '');
            if (k === lines.length - 1) return line.replace(/[ \t]+$/, '');
            if (/(?: {2,}|\\)$/.test(line)) return line.replace(/(?: {2,}|\\)$/, '').replace(/[ \t]+$/, '') + '\x03';
            return line.replace(/[ \t]+$/, '');
        }).join('\n'));
    }

    // Twin of pg_markdown_blocks().
    function blocks(lines, depth) {
        var out = [], n = lines.length, i = 0;
        if (depth > 8) {
            var all = lines.join('\n').replace(/^[ \t\n]+|[ \t\n]+$/g, '');
            return all === '' ? [] : ['<p>' + inline(all) + '</p>'];
        }
        while (i < n) {
            var line = lines[i];
            if (blank(line)) { i++; continue; }

            var fence = fenceOf(line);
            if (fence !== null) {
                var code = [];
                var close = new RegExp('^ {0,3}' + (fence[0].charAt(0) === '`' ? '`' : '~') + '{' + fence[0].length + ',}[ \\t]*$');
                i++;
                while (i < n && !close.test(lines[i])) { code.push(lines[i]); i++; }
                i++;
                var word = fence[1].split(/[ \t]+/)[0];
                var language = /^[A-Za-z0-9_+#-]{1,30}$/.test(word) ? word : '';
                out.push('<pre><code' + (language !== '' ? ' class="language-' + esc(language) + '"' : '') + '>' + esc(code.join('\n')) + '</code></pre>');
                continue;
            }

            var heading = headingOf(line);
            if (heading !== null) {
                out.push('<h' + heading[0] + '>' + inline(heading[1]) + '</h' + heading[0] + '>');
                i++;
                continue;
            }

            if (rule(line)) { out.push('<hr>'); i++; continue; }

            if (tableAt(lines, i)) {
                var head = cellsOf(line);
                var aligns = cellsOf(lines[i + 1]).map(function (cell) {
                    var left = cell.charAt(0) === ':', right = cell.slice(-1) === ':';
                    return (left && right) ? ' class="text-center"' : (right ? ' class="text-end"' : (left ? ' class="text-start"' : ''));
                });
                var table = '<div class="table-responsive"><table class="table"><thead><tr>';
                head.forEach(function (cell, k) { table += '<th scope="col"' + aligns[k] + '>' + inline(cell) + '</th>'; });
                table += '</tr></thead>';
                i += 2;
                var rows = '';
                while (i < n && !blank(lines[i]) && lines[i].indexOf('|') !== -1) {
                    var cells = cellsOf(lines[i]);
                    rows += '<tr>';
                    head.forEach(function (unused, k) { rows += '<td' + aligns[k] + '>' + (k < cells.length ? inline(cells[k]) : '') + '</td>'; });
                    rows += '</tr>';
                    i++;
                }
                out.push(table + (rows !== '' ? '<tbody>' + rows + '</tbody>' : '') + '</table></div>');
                continue;
            }

            if (/^ {0,3}>/.test(line)) {
                var quoted = [];
                while (i < n && /^ {0,3}>/.test(lines[i])) { quoted.push(lines[i].replace(/^ {0,3}> ?/, '')); i++; }
                out.push('<blockquote class="blockquote">\n' + blocks(quoted, depth + 1).join('\n') + '\n</blockquote>');
                continue;
            }

            var first = itemOf(line);
            if (first !== null) {
                var base = first.indent, items = [];
                while (i < n) {
                    var current = lines[i];
                    if (blank(current)) {
                        var j = i + 1;
                        while (j < n && blank(lines[j])) j++;
                        var next = j < n ? itemOf(lines[j]) : null;
                        if (j < n && (indentOf(lines[j]) > base + 1 || (next !== null && next.ordered === first.ordered))) { i = j; continue; }
                        break;
                    }
                    var item = itemOf(current);
                    if (item !== null && item.indent <= base + 1) {
                        if (item.ordered !== first.ordered) break;
                        items.push({ text: item.text, column: item.column, rest: [] });
                        i++;
                        continue;
                    }
                    var last = items[items.length - 1];
                    if (indentOf(current) > base + 1) {
                        last.rest.push(dedent(current, Math.min(indentOf(current), last.column)));
                        i++;
                        continue;
                    }
                    if (blockStart(lines, i)) break;
                    last.rest.push(current);
                    i++;
                }
                var li = items.map(function (it) {
                    var para = [it.text], rest = it.rest.slice();
                    while (rest.length && !blank(rest[0]) && !blockStart(rest, 0)) para.push(rest.shift());
                    var inner = blocks(rest, depth + 1);
                    return '<li>' + paragraph(para) + (inner.length ? '\n' + inner.join('\n') : '') + '</li>';
                });
                var tag = first.ordered ? 'ol' : 'ul';
                out.push('<' + tag + ((first.ordered && first.start !== 1) ? ' start="' + first.start + '"' : '') + '>\n' + li.join('\n') + '\n</' + tag + '>');
                continue;
            }

            var para = [line];
            i++;
            while (i < n && !blank(lines[i]) && !blockStart(lines, i)) { para.push(lines[i]); i++; }
            out.push('<p>' + paragraph(para) + '</p>');
        }
        return out;
    }

    // Twin of pg_markdown_html().
    function html(text) {
        text = String(text == null ? '' : text).replace(/\r\n|\r/g, '\n').replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
        return blocks(text.split('\n'), 0).join('\n');
    }

    window.PGMarkdown = { html: html, safeUrl: safeUrl };
})();
