/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "From a document" form and its rows (window.PGDocumentRows), drawn in
 * the New design window's pane (assets/js/designer_document.js) and in the
 * designer's own window (style_designer_document.js) alike.
 *
 * The form takes a pasted text or one file (.docx, .md, .txt, dropped,
 * chosen or from the file manager). "Read" reads a paste in the browser
 * (window.PGDocumentReader) and hands a file to the host's readFile(); the
 * sections come back as rows: kind (a list of what the words can fill),
 * heading and the beginning of its words, how much it holds, the words
 * opened as fields, a cross to leave it out, a handle to move it. Unsure
 * rows carry an amber "?" and the reason until a kind is chosen. "Build
 * the page" hands the rows kept, in order, to the host's build().
 *
 * With Pinegrap AI on, the host gives sort(): "Sort it for me" sends the
 * kept rows' words and draws each proposal as a line under its row (another
 * kind, a shorter text for a long passage), each accepted or left by itself;
 * a row changed while it works keeps the person's choice.
 *
 * Pure parts (rowsOf, choices, choose, move, count, preview, fields, set,
 * sectionText, sortSections, sig, takeProposals, shorten) are tested in Node: tests/document_start_test.php. The texts arrive
 * translated from the host (pg_document_start_texts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var ICONS = {intro: 'bi-window', text: 'bi-text-paragraph', cards: 'bi-grid', questions: 'bi-question-circle', figures: 'bi-123',
        testimonial: 'bi-chat-quote', table: 'bi-table', prices: 'bi-tag', contact: 'bi-envelope', picture: 'bi-image', gallery: 'bi-images'};
    // The fields of one item, in the kind's shape.
    var SHAPES = {cards: ['title', 'text'], questions: ['question', 'answer'], figures: ['value', 'label'], testimonial: ['quote', 'name', 'role'],
        contact: ['value'], picture: ['alt'], gallery: ['alt'], intro: ['text'], text: ['text']};
    var COUNTS = {intro: 'paragraphs', text: 'words', cards: 'cards', questions: 'questions', figures: 'figures', testimonial: 'quotes',
        table: 'rows', prices: 'rows', contact: 'lines', picture: 'pictures', gallery: 'pictures'};
    var MAX_PASTE = 600000;

    function copy(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function fill(text, values) {
        return String(text || '').replace(/\{var:(\d+)\}/g, function (all, n) {
            return values[n - 1] != null ? values[n - 1] : '';
        });
    }

    function esc(value) {
        return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ---- Pure ---------------------------------------------------------------

    // The reader's sections as rows, each with every kind its words fill.
    function rowsOf(result, paragraphs, reader) {
        return ((result && result.sections) || []).map(function (s) {
            return {id: s.id, kind: s.kind, heading: s.heading, items: copy(s.items), unsure: !!s.unsure, reason: s.reason,
                options: (s.options || []).slice(), words: s.words || 0, out: false, fits: reader.fits(paragraphs, s)};
        });
    }

    // What the kind list offers: on an unsure row the reader's readings
    // ("It could be"), then the other kinds the words fill.
    function choices(row) {
        var kinds = row.fits.map(function (f) { return f.kind; });
        var could = row.unsure ? row.options.filter(function (k) { return kinds.indexOf(k) !== -1; }) : [];
        return {could: could, other: kinds.filter(function (k) { return could.indexOf(k) === -1; })};
    }

    // A kind chosen: its items, the mark gone. The ticked kind keeps the
    // words as they were corrected.
    function choose(row, kind) {
        var fit = row.fits.filter(function (f) { return f.kind === kind; })[0];
        if (!fit) {
            return false;
        }
        if (kind !== row.kind) {
            row.items = copy(fit.items);
            row.kind = kind;
        }
        row.unsure = false;
        row.reason = null;
        return true;
    }

    function move(rows, from, to) {
        if (from < 0 || from >= rows.length || to < 0 || to >= rows.length || from === to) {
            return false;
        }
        rows.splice(to, 0, rows.splice(from, 1)[0]);
        return true;
    }

    // How much a row holds: [count key, number].
    function count(row) {
        var key = COUNTS[row.kind] || 'words';
        var n = row.items.length;
        if (key === 'words') {
            n = row.items.reduce(function (sum, item) {
                return sum + String(item.text || '').split(/\s+/).filter(Boolean).length;
            }, 0);
        } else if (key === 'rows') {
            n = row.items.reduce(function (sum, item) { return sum + (item.rows || []).length; }, 0);
        }
        return [key, n];
    }

    // The beginning of a row's words, one line.
    function preview(row) {
        var parts = row.items.map(function (item) {
            if (item.rows) {
                return (item.rows[0] || []).join(' · ');
            }
            return item.title || item.question || item.quote || (item.value != null && item.label != null ? item.value + ' ' + item.label : '') || item.value || item.text || item.alt || '';
        }).filter(Boolean);
        var text = (row.kind === 'intro' || row.kind === 'text') ? (parts[0] || '') : parts.join(' · ');
        return text.length > 160 ? text.slice(0, 159) + '…' : text;
    }

    // The opened row's fields: [path, field key, value]; a table's cells one each.
    function fields(row) {
        var out = [[['heading'], 'heading', row.heading]];
        row.items.forEach(function (item, i) {
            if (item.rows) {
                item.rows.forEach(function (cells, r) {
                    cells.forEach(function (cell, c) { out.push([['items', i, 'rows', r, c], 'cell', cell]); });
                });
                return;
            }
            (SHAPES[row.kind] || ['text']).forEach(function (key) {
                if (Object.prototype.hasOwnProperty.call(item, key)) {
                    out.push([['items', i, key], key, item[key]]);
                }
            });
        });
        return out;
    }

    function set(row, path, value) {
        var at = row;
        for (var i = 0; i < path.length - 1; i++) {
            at = at[path[i]];
            if (at == null) {
                return;
            }
        }
        at[path[path.length - 1]] = String(value);
    }

    // ---- "Sort it for me" (pure) ----------------------------------------------

    // A row's words as one plain text, an item a paragraph.
    function sectionText(row) {
        return row.items.map(function (item) {
            if (item.rows) {
                return item.rows.map(function (cells) { return cells.join(' | '); }).join('\n');
            }
            if (item.question != null) {
                return item.question + '\n' + (item.answer || '');
            }
            if (item.quote != null) {
                return item.quote + (item.name ? ' — ' + item.name + (item.role ? ', ' + item.role : '') : '');
            }
            if (item.title != null) {
                return item.title + (item.text ? ': ' + item.text : '');
            }
            if (item.label != null) {
                return item.value + ' ' + item.label;
            }
            return item.text || item.value || item.alt || '';
        }).filter(Boolean).join('\n\n');
    }

    // What one press sends: the rows kept, each with the kinds its words fill.
    function sortSections(rows) {
        return rows.filter(function (r) { return !r.out; }).map(function (r) {
            return {id: r.id, kind: r.kind, fits: r.fits.map(function (f) { return f.kind; }), heading: r.heading || '', text: sectionText(r), unsure: !!r.unsure};
        });
    }

    // A row's kind and words, to see whether it changed while the proposals were asked for.
    function sig(row) {
        return row.kind + '|' + (row.out ? 1 : 0) + '|' + JSON.stringify(row.items);
    }

    // The answer's proposals by row id, without those of a row changed
    // since the press (sent: id => sig) or of a kind its words cannot fill.
    function takeProposals(list, rows, sent) {
        var out = {};
        (list || []).forEach(function (p) {
            var row = rows.filter(function (r) { return r.id === p.id; })[0];
            if (!row || !sent || sent[row.id] !== sig(row)) {
                return;
            }
            var it = {};
            if (p.kind && p.kind !== row.kind && row.fits.some(function (f) { return f.kind === p.kind; })) {
                it.kind = p.kind;
            }
            if (typeof p.short === 'string' && p.short.trim() !== '') {
                it.short = p.short;
                it.words = +p.words || p.short.split(/\s+/).filter(Boolean).length;
                it.was = count(row)[1];
            }
            if (it.kind || it.short) {
                out[row.id] = it;
            }
        });
        return out;
    }

    // The shorter text taken: the row's words become its paragraphs.
    function shorten(row, text) {
        row.items = String(text).split(/\n\s*\n/).map(function (s) { return s.trim(); }).filter(Boolean).map(function (s) {
            return {type: 'paragraph', text: s};
        });
    }

    // How many proposal lines are waiting.
    function proposalCount(props) {
        return Object.keys(props || {}).reduce(function (n, id) {
            return n + (props[id].kind ? 1 : 0) + (props[id].short ? 1 : 0);
        }, 0);
    }

    // ---- The form and the rows -----------------------------------------------

    function mount(o) {
        var t = o.texts;
        var R = o.reader;
        var box = o.box;
        var foot = o.foot;
        var st = {text: '', file: null, answer: null, paragraphs: null, rows: null, open: '', dirty: false, busy: false, warned: false,
            props: {}, sorting: false, sortEnd: '', sortMsg: '', sortOff: !o.sort};
        var ts = t.sort || {};
        var live = document.createElement('div');

        box.classList.add('pg-doc');
        if (o.css && !document.querySelector('link[data-pg-doc]')) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = o.css;
            link.setAttribute('data-pg-doc', '');
            document.head.appendChild(link);
        }
        live.className = 'visually-hidden';
        live.setAttribute('aria-live', 'polite');

        function btn(cls, html, label) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = cls;
            b.innerHTML = html;
            if (label) {
                b.setAttribute('aria-label', label);
            }
            return b;
        }

        function alertHtml(tone, title, text) {
            return '<div class="alert theme-' + tone + ' small py-3 px-5 mb-4" role="' + (tone === 'danger' ? 'alert' : 'status') + '">'
                + (title ? '<strong class="d-block">' + esc(title) + '</strong>' : '') + esc(text || '') + '</div>';
        }

        function size(bytes) {
            return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
        }

        // ---- F2: the form
        function drawForm(alert) {
            st.mode = 'form';
            box.innerHTML = (alert || '')
                + (o.head ? '<h3 class="pg-sm-heading">' + esc(t.head) + '</h3><p class="pg-sm-desc">' + esc(t.lead) + '</p>' : '')
                + '<div class="pg-doc-source"></div>';
            var src = box.querySelector('.pg-doc-source');
            if (st.file) {
                src.innerHTML = '<div class="pg-doc-file pg-card-inner-row"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>'
                    + '<span class="pg-doc-grow"><strong class="d-block text-truncate">' + esc(st.file.name) + '</strong><span class="pg-text-meta">'
                    + esc(String(st.file.name).split('.').pop().toUpperCase()) + (st.file.size ? ' · ' + size(st.file.size) : '') + '</span></span></div>'
                    + '<button type="button" class="pg-button pg-button-ghost mt-3 pg-doc-paste">' + esc(t.pasteInstead) + '</button>';
                var x = btn('pg-button pg-button-ghost pg-button-icon', '<i class="bi bi-x-lg" aria-hidden="true"></i>', t.remove);
                src.querySelector('.pg-doc-file').appendChild(x);
                x.addEventListener('click', unpick);
                src.querySelector('.pg-doc-paste').addEventListener('click', unpick);
            } else {
                src.innerHTML = '<label class="pg-field-label" for="pg-doc-text">' + esc(t.text) + '</label>'
                    + '<textarea class="pg-input pg-doc-text" id="pg-doc-text" rows="9" spellcheck="false" aria-describedby="pg-doc-help" placeholder="' + esc(t.placeholder) + '"></textarea>'
                    + '<div class="pg-field-help" id="pg-doc-help">' + esc(t.help) + '</div>'
                    + '<div class="pg-doc-drop mt-4"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i><span class="pg-doc-grow"><strong class="d-block">' + esc(t.drop) + '</strong>'
                    + '<span class="pg-text-meta">' + esc(t.dropLimits) + '</span></span>'
                    + '<label class="pg-button pg-button-quiet mb-0">' + esc(t.choose) + '<input type="file" class="visually-hidden" accept=".docx,.md,.markdown,.txt"></label>'
                    + (o.pickFile ? '<button type="button" class="pg-button pg-button-quiet pg-doc-manager">' + esc(t.manager) + '</button>' : '') + '</div>';
                var area = src.querySelector('textarea');
                area.value = st.text;
                area.addEventListener('input', function () {
                    st.text = area.value;
                    syncRead();
                });
                src.querySelector('input[type="file"]').addEventListener('change', function (e) {
                    if (e.target.files && e.target.files[0]) {
                        pick({file: e.target.files[0], name: e.target.files[0].name, size: e.target.files[0].size});
                    }
                });
                var mgr = src.querySelector('.pg-doc-manager');
                if (mgr) {
                    mgr.addEventListener('click', function () {
                        o.pickFile().then(function (item) {
                            if (item) {
                                pick({id: item.id, name: item.name, size: item.size || 0});
                            }
                        });
                    });
                }
            }
            box.appendChild(live);
            drawFoot([['me-auto pg-text-meta pg-doc-local', t.local], ['quiet', t.cancel, o.cancel], ['fill pg-doc-read', st.warned ? t.readAgain : t.read, read]]);
            syncRead();
        }

        function pick(file) {
            st.file = file;
            st.warned = false;
            drawForm();
        }

        function unpick() {
            st.file = null;
            drawForm();
        }

        function syncRead() {
            var b = foot.querySelector('.pg-doc-read');
            if (b) {
                b.disabled = st.busy || !(st.file || st.text.trim() !== '');
            }
        }

        // The foot's buttons: [class, label, click]; a span without click.
        function drawFoot(list) {
            foot.innerHTML = '';
            list.forEach(function (it) {
                if (!it[2]) {
                    var s = document.createElement('span');
                    s.className = it[0];
                    s.textContent = it[1];
                    foot.appendChild(s);
                    return;
                }
                var b = btn('pg-button pg-button-' + it[0], esc(it[1]));
                b.addEventListener('click', it[2]);
                foot.appendChild(b);
            });
        }

        function read() {
            if (st.dirty && !st.warned) {
                st.warned = true;
                drawForm(alertHtml('warning', '', t.backAsk));
                return;
            }
            if (!st.file) {
                if (st.text.length > MAX_PASTE) {
                    drawForm(alertHtml('danger', t.tooLong, ''));
                    return;
                }
                show({name: '', text: st.text, cut: '', pictures: [], pictures_lost: 0});
                return;
            }
            st.busy = true;
            box.insertAdjacentHTML('afterbegin', '<div class="pg-doc-wait pg-text-meta mb-4" role="status"><span class="spinner-border spinner-border-sm me-3" aria-hidden="true"></span>'
                + esc(fill(t.readingFile, [st.file.name])) + '</div>');
            var b = foot.querySelector('.pg-doc-read');
            b.textContent = t.reading;
            syncRead();
            o.readFile(st.file).then(function (answer) {
                st.busy = false;
                if (!answer || answer.status !== 'success') {
                    drawForm(alertHtml('danger', (answer && answer.title) || t.network, (answer && answer.message) || ''));
                    return;
                }
                show(answer);
            }, function () {
                st.busy = false;
                drawForm(alertHtml('danger', t.network, ''));
            });
        }

        function show(answer) {
            st.answer = answer;
            st.paragraphs = (answer.text !== '' && answer.text != null) ? R.paragraphs(answer.text) : (answer.paragraphs || []);
            st.rows = rowsOf(R.read(st.paragraphs), st.paragraphs, R);
            st.open = '';
            st.dirty = false;
            st.warned = false;
            st.props = {};
            st.sortEnd = '';
            drawRows();
        }

        // ---- F5: "Sort it for me"
        function sort() {
            if (st.sorting || st.sortOff) {
                return;
            }
            var rows = st.rows;
            var sent = {};
            rows.forEach(function (r) { sent[r.id] = sig(r); });
            st.sorting = true;
            st.sortEnd = '';
            st.props = {};
            drawRows();
            var done = function (res) {
                if (st.rows !== rows) {
                    return;
                }
                st.sorting = false;
                if (res && res.status === 'success') {
                    st.props = takeProposals(res.proposals, rows, sent);
                    st.sortEnd = proposalCount(st.props) ? '' : 'none';
                } else {
                    st.sortOff = !!(res && res.off);
                    st.sortEnd = 'fail';
                    st.sortMsg = (res && res.message) || '';
                }
                if (st.mode === 'rows') {
                    drawRows();
                }
            };
            Promise.resolve(o.sort(sortSections(rows))).then(done, function () { done(null); });
        }

        function drawProposals(row, li) {
            var p = st.props[row.id];
            if (!p || row.out) {
                return;
            }
            var line = function (html, buttons) {
                var div = document.createElement('div');
                div.className = 'pg-doc-px';
                div.innerHTML = '<i class="bi bi-stars" aria-hidden="true"></i><span class="pg-doc-pt">' + html + '</span>';
                buttons.forEach(function (b) {
                    var el = btn('pg-button pg-button-' + b[0] + ' pg-button-sm', esc(b[1]));
                    if (b[3] != null) {
                        el.setAttribute('aria-expanded', b[3] ? 'true' : 'false');
                    }
                    el.addEventListener('click', function () {
                        b[2]();
                        drawRows();
                    });
                    div.appendChild(el);
                });
                li.appendChild(div);
                return div;
            };
            if (p.kind) {
                line(fill(esc(ts.kind), ['<b>' + esc(t.kinds[p.kind]) + '</b>', esc(t.kinds[row.kind])]), [
                    ['quiet', ts.accept, function () {
                        choose(row, p.kind);
                        st.dirty = true;
                        delete p.kind;
                    }],
                    ['ghost', ts.leave, function () { delete p.kind; }]
                ]);
            }
            if (p.short) {
                line(fill(esc(ts.short), ['<b>' + esc(p.words) + '</b>', esc(p.was)]), [
                    ['quiet', p.show ? ts.hide : ts.show, function () { p.show = !p.show; }, !!p.show],
                    ['quiet', ts.use, function () {
                        shorten(row, p.short);
                        st.dirty = true;
                        delete p.short;
                    }],
                    ['ghost', ts.keep, function () { delete p.short; }]
                ]);
                if (p.show) {
                    var ba = document.createElement('div');
                    ba.className = 'pg-doc-ba';
                    ba.innerHTML = '<div><em>' + esc(fill(ts.yours, [p.was])) + '</em>' + esc(sectionText(row)) + '</div>'
                        + '<div><em>' + esc(fill(ts.proposed, [p.words])) + '</em>' + esc(p.short) + '</div>';
                    li.appendChild(ba);
                }
            }
            if (!p.kind && !p.short) {
                delete st.props[row.id];
            }
        }

        function sortEnd() {
            if (st.sortEnd === 'none') {
                return alertHtml('success', ts.none, ts.noneText);
            }
            if (st.sortEnd === 'fail') {
                return '<div class="alert theme-danger small py-3 px-5 mt-4 mb-0 pg-doc-fail" role="alert"><strong class="d-block">' + esc(ts.failed) + '</strong>'
                    + esc(ts.failedText + (st.sortMsg ? ' ' + st.sortMsg : '')) + '</div>';
            }
            return '';
        }

        // ---- F3, F4: the rows
        function drawRows() {
            // A field being typed in keeps its focus over a redraw (the
            // proposals may arrive meanwhile).
            var act = document.activeElement;
            var focus = (act && act.id && box.contains(act) && act.tagName === 'INPUT') ? [act.id, act.selectionStart, act.selectionEnd] : null;
            st.mode = 'rows';
            var rows = st.rows;
            var kept = rows.filter(function (r) { return !r.out; }).length;
            var unsure = rows.filter(function (r) { return r.unsure && !r.out; }).length;
            var a = st.answer;
            var wordCount = rows.reduce(function (n, r) { return n + r.words; }, 0);
            var proposals = proposalCount(st.props);
            var notes = '';
            if (a.cut && a.read) {
                notes += alertHtml('warning', '', fill(t.cut, [a.read.paragraphs, a.read.percent]));
            }
            if (a.pictures.length || a.pictures_lost) {
                notes += alertHtml('primary', '', fill(t.pictures, [a.pictures.length]) + (a.pictures_lost ? '. ' + fill(t.picturesLost, [a.pictures_lost]) : ''));
            }
            if (rows.length <= 1 && !rows.some(function (r) { return r.heading; })) {
                notes += alertHtml('primary', t.noHeadings, t.noHeadingsText);
            }
            box.innerHTML = notes + '<div class="pg-doc-head"><strong>' + esc(kept === rows.length ? fill(t.sections, [rows.length]) : fill(t.sectionsOf, [kept, rows.length])) + '</strong>'
                + '<span class="pg-text-meta">' + esc(a.name ? fill(t.from, [a.name, wordCount]) : fill(t.fromPaste, [wordCount])) + '</span>'
                + (proposals ? '<span class="pg-pill pg-doc-pill-ai">' + esc(fill(ts.proposals, [proposals])) + '</span>'
                    : (unsure ? '<span class="pg-pill pg-tone-amber">' + esc(fill(t.toCheck, [unsure])) + '</span>' : ''))
                + (st.sortOff ? '' : '<button type="button" class="pg-button pg-button-quiet pg-button-sm pg-doc-sort"' + (st.sorting ? ' disabled' : '') + '>'
                    + (st.sorting ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + esc(ts.busy) : '<i class="bi bi-stars me-2" aria-hidden="true"></i>' + esc(ts.button)) + '</button>')
                + '</div>'
                + '<ol class="pg-doc-rows list-unstyled mb-0"></ol>';
            var list = box.querySelector('.pg-doc-rows');
            rows.forEach(function (row, at) { list.appendChild(drawRow(row, at)); });
            if (st.sortEnd === 'fail') {
                list.insertAdjacentHTML('afterend', sortEnd());
                if (!st.sortOff) {
                    var again = btn('pg-button pg-button-quiet pg-button-sm mt-3', esc(ts.again));
                    again.addEventListener('click', sort);
                    box.querySelector('.pg-doc-fail').appendChild(again);
                }
            } else if (st.sortEnd === 'none') {
                list.insertAdjacentHTML('afterend', '<div class="mt-4">' + sortEnd() + '</div>');
            }
            if (!st.sortOff) {
                box.insertAdjacentHTML('beforeend', '<p class="pg-text-meta pg-doc-ai mt-3 mb-0"><i class="bi bi-stars me-2" aria-hidden="true"></i>' + esc(ts.line) + '</p>');
                box.querySelector('.pg-doc-sort').addEventListener('click', sort);
            }
            box.appendChild(live);
            if (focus && document.getElementById(focus[0])) {
                var again2 = document.getElementById(focus[0]);
                again2.focus();
                try {
                    again2.setSelectionRange(focus[1], focus[2]);
                } catch (e) { /* not a text field */ }
            }
            drawFoot([['quiet me-auto', t.back, function () { drawForm(); }], ['quiet', t.cancel, o.cancel], ['fill pg-doc-build', t.build, build]]);
        }

        function drawRow(row, at) {
            var li = document.createElement('li');
            var c = count(row);
            li.className = 'pg-doc-row' + (row.out ? ' is-out' : '') + (st.open === row.id ? ' is-open' : '');
            li.dataset.at = at;
            li.innerHTML = '<div class="pg-doc-line"><button type="button" class="pg-doc-grip" draggable="true" aria-label="' + esc(t.move + ': ' + (row.heading || t.kinds[row.kind])) + '"><i class="bi bi-grip-vertical" aria-hidden="true"></i></button>'
                + '<button type="button" class="pg-doc-kind" aria-haspopup="menu" aria-expanded="false"><i class="bi ' + ICONS[row.kind] + '" aria-hidden="true"></i>' + esc(t.kinds[row.kind]) + '<i class="bi bi-chevron-down" aria-hidden="true"></i></button>'
                + '<span class="pg-doc-words">' + (row.heading ? '<strong>' + esc(row.heading) + '</strong>' : '')
                + (row.unsure ? '<small class="pg-doc-why"><span class="pg-doc-q" aria-hidden="true">?</span>' + esc(fill(t[row.reason] || t.mixed, row.options.map(function (k) { return t.kinds[k]; }))) + '</small>'
                    : '<small>' + esc(preview(row)) + '</small>') + '</span>'
                + '<span class="pg-doc-count pg-text-meta">' + esc(fill(t.counts[c[0]], [c[1]])) + '</span>'
                + '<span class="pg-doc-acts"></span></div>';
            var acts = li.querySelector('.pg-doc-acts');
            var openBtn = btn('pg-button pg-button-ghost pg-button-icon', '<i class="bi bi-chevron-' + (st.open === row.id ? 'up' : 'down') + '" aria-hidden="true"></i>', st.open === row.id ? t.close : t.open);
            openBtn.setAttribute('aria-expanded', st.open === row.id ? 'true' : 'false');
            openBtn.addEventListener('click', function () {
                st.open = (st.open === row.id) ? '' : row.id;
                drawRows();
            });
            var outBtn = row.out ? btn('pg-button pg-button-ghost', esc(t.putBack)) : btn('pg-button pg-button-ghost pg-button-icon', '<i class="bi bi-x-lg" aria-hidden="true"></i>', t.leave);
            outBtn.addEventListener('click', function () {
                row.out = !row.out;
                st.dirty = true;
                drawRows();
            });
            if (!row.out) {
                acts.appendChild(openBtn);
            }
            acts.appendChild(outBtn);
            li.querySelector('.pg-doc-kind').addEventListener('click', function (e) { kindMenu(row, e.currentTarget); });
            wireMove(li, at);
            drawProposals(row, li);
            if (st.open === row.id && !row.out) {
                li.appendChild(drawFields(row));
            }
            return li;
        }

        function drawFields(row) {
            var wrap = document.createElement('div');
            wrap.className = 'pg-doc-fields';
            fields(row).forEach(function (f, n) {
                var id = 'pg-doc-f-' + row.id + '-' + n;
                var div = document.createElement('div');
                div.className = 'pg-doc-field';
                div.innerHTML = '<label class="pg-field-label" for="' + id + '">' + esc(f[1] === 'heading' ? t.heading : t.fields[f[1]] || f[1]) + '</label>'
                    + '<input type="text" class="pg-input" id="' + id + '">';
                var input = div.querySelector('input');
                input.value = f[2] == null ? '' : f[2];
                input.addEventListener('input', function () {
                    set(row, f[0], input.value);
                    st.dirty = true;
                });
                wrap.appendChild(div);
            });
            return wrap;
        }

        var menu = null;

        function closeMenu() {
            if (menu) {
                menu.opener.setAttribute('aria-expanded', 'false');
                menu.el.remove();
                document.removeEventListener('mousedown', menu.away, true);
                menu = null;
            }
        }

        function kindMenu(row, opener) {
            if (menu && menu.opener === opener) {
                closeMenu();
                return;
            }
            closeMenu();
            var c = choices(row);
            var el = document.createElement('div');
            el.className = 'pg-doc-menu';
            el.setAttribute('role', 'menu');
            var item = function (k) {
                var b = btn('pg-doc-menu-item', '<i class="bi ' + ICONS[k] + '" aria-hidden="true"></i>' + esc(t.kinds[k]) + '<i class="bi bi-check2 pg-doc-tick" aria-hidden="true"></i>');
                b.setAttribute('role', 'menuitemradio');
                b.setAttribute('aria-checked', k === row.kind ? 'true' : 'false');
                b.addEventListener('click', function () {
                    choose(row, k);
                    st.dirty = true;
                    if (st.props[row.id]) {
                        delete st.props[row.id].kind;
                    }
                    closeMenu();
                    drawRows();
                });
                el.appendChild(b);
            };
            var cap = function (text) {
                var d = document.createElement('div');
                d.className = 'pg-doc-menu-cap';
                d.setAttribute('role', 'presentation');
                d.textContent = text;
                el.appendChild(d);
            };
            if (c.could.length) {
                cap(t.couldBe);
                c.could.forEach(item);
                if (c.other.length) {
                    cap(t.other);
                }
            }
            c.other.forEach(item);
            opener.parentNode.appendChild(el);
            opener.setAttribute('aria-expanded', 'true');
            menu = {el: el, opener: opener, away: function (e) {
                if (!el.contains(e.target) && e.target !== opener && !opener.contains(e.target)) {
                    closeMenu();
                }
            }};
            document.addEventListener('mousedown', menu.away, true);
            el.addEventListener('keydown', function (e) {
                var items = Array.prototype.slice.call(el.querySelectorAll('[role="menuitemradio"]'));
                var i = items.indexOf(document.activeElement);
                if (e.key === 'Escape') {
                    e.stopPropagation();
                    closeMenu();
                    opener.focus();
                } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    items[(i + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length].focus();
                }
            });
            (el.querySelector('[aria-checked="true"]') || el.querySelector('[role="menuitemradio"]')).focus();
        }

        // A row moved by its handle: dragged (a line shows where it lands)
        // or with the up and down arrows, the new place read out.
        function wireMove(li, at) {
            var grip = li.querySelector('.pg-doc-grip');
            grip.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') {
                    return;
                }
                e.preventDefault();
                var to = at + (e.key === 'ArrowUp' ? -1 : 1);
                if (move(st.rows, at, to)) {
                    st.dirty = true;
                    drawRows();
                    live.textContent = fill(t.moved, [to + 1, st.rows.length]);
                    box.querySelectorAll('.pg-doc-grip')[to].focus();
                }
            });
            grip.addEventListener('dragstart', function (e) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(at));
                li.classList.add('is-dragging');
            });
            grip.addEventListener('dragend', function () { li.classList.remove('is-dragging'); });
            li.addEventListener('dragover', function (e) {
                e.preventDefault();
                li.classList.add('is-drop');
            });
            li.addEventListener('dragleave', function () { li.classList.remove('is-drop'); });
            li.addEventListener('drop', function (e) {
                e.preventDefault();
                var from = parseInt(e.dataTransfer.getData('text/plain'), 10);
                if (move(st.rows, from, at)) {
                    st.dirty = true;
                    drawRows();
                }
            });
        }

        function build() {
            var b = foot.querySelector('.pg-doc-build');
            var kept = st.rows.filter(function (r) { return !r.out; });
            if (!kept.length || st.busy) {
                return;
            }
            st.busy = true;
            b.disabled = true;
            b.textContent = t.building;
            box.insertAdjacentHTML('afterbegin', '<div class="pg-doc-wait pg-text-meta mb-4" role="status"><span class="spinner-border spinner-border-sm me-3" aria-hidden="true"></span>' + esc(t.buildingPage) + '</div>');
            Promise.resolve(o.build(copy(kept), st.answer)).then(function () {
                st.busy = false;
                drawRows();
            }, function (message) {
                st.busy = false;
                drawRows();
                box.insertAdjacentHTML('afterbegin', alertHtml('danger', String(message || t.network), ''));
            });
        }

        // A file dropped anywhere on the window is taken.
        var area = o.dropArea || box;
        var over = function (e) {
            if (st.mode === 'form' && e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1) {
                e.preventDefault();
            }
        };
        var dropped = function (e) {
            if (st.mode !== 'form' || !e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files[0]) {
                return;
            }
            e.preventDefault();
            var f = e.dataTransfer.files[0];
            pick({file: f, name: f.name, size: f.size});
        };
        area.addEventListener('dragover', over);
        area.addEventListener('drop', dropped);

        drawForm();

        return {
            destroy: function () {
                closeMenu();
                area.removeEventListener('dragover', over);
                area.removeEventListener('drop', dropped);
                box.innerHTML = '';
                foot.innerHTML = '';
            }
        };
    }

    window.PGDocumentRows = {
        ICONS: ICONS,
        rowsOf: rowsOf,
        choices: choices,
        choose: choose,
        move: move,
        count: count,
        preview: preview,
        fields: fields,
        set: set,
        sectionText: sectionText,
        sortSections: sortSections,
        sig: sig,
        takeProposals: takeProposals,
        shorten: shorten,
        proposalCount: proposalCount,
        mount: mount
    };
})();
