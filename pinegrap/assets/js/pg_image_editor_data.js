/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the data layer, a Workspace table drawn as a chart or a
 * plain table from its description and the rows read for the reader.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-data-layer.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, t = X.t, el = X.el, section = X.section, find = X.find;

    // A chevron to the left: the picker's Back.
    X.icons.chevl = X.icons.chevl || 'M14.5 6.5L9 12l5.5 5.5';

    // The kinds of the Data menu and the panel, in their order, by label.
    var KIND_LABELS = { table: 'data_kind_table', bar: 'data_kind_bar', stacked_bar: 'data_kind_stacked_bar', line: 'data_kind_line', area: 'data_kind_area', donut: 'data_kind_donut' };

    // The rows a layer's table gave this reader, as the editor holds them:
    // { columns, rows }, or { state: 'refused' }; nothing before the read.
    Editor.prototype.dataRead = function (table, read) {
        this.dataReads[String(table)] = read || null;
        this.layersDirty = true;
        this.renderView();
    };

    // A layer's marks (M.dataMarks()) in its box; a description that does
    // not pass its check draws as an empty layer.
    // A painter made without the editor's inits (a language's picture) holds
    // no reads: its layers draw with no rows, as the Workspace's file does.
    Editor.prototype.dataMarksOf = function (l) {
        var c = M.dataCheck(l.data), read, bare = this.dataBareDraw || !this.dataReads;
        var words = { empty: t(bare ? 'dataBare' : 'dataEmpty'), refused: t('dataRefused'), more: t('dataMore'), count: t('dataCount'), other: t('dataOther') };

        if (!c.ok) {
            return M.dataMarks(M.dataCheck({ table: 1, label: 'x', values: ['x'] }).data, l.w, l.h, { labels: [], series: [] }, words);
        }

        read = bare ? null : this.dataReads[String(c.data.table)];

        if (read === undefined) {
            this.dataLoad(c.data.table);
        }

        if (read && (read.state !== 'refused')) {
            read = M.dataSeries(c.data, read.columns, read.rows, words);
        }

        return M.dataMarks(c.data, l.w, l.h, read || null, words);
    };

    function family(l) {
        var c = M.dataCheck(l.data);

        return M.textFamily(c.ok ? c.data.font : 'g:Inter');
    }

    function pathD(pts, close) {
        return pts.map(function (q, i) { return (i ? 'L' : 'M') + q[0] + ' ' + q[1]; }).join('') + (close ? 'Z' : '');
    }

    function dataNode(l) {
        var g = document.createElementNS(SVGNS, 'g'), cx = l.x + l.w / 2, cy = l.y + l.h / 2, font = family(l);

        g.setAttribute('transform', 'rotate(' + (l.rot || 0) + ' ' + cx + ' ' + cy + ') translate(' + l.x + ' ' + l.y + ')');

        if ((l.opacity !== undefined) && (l.opacity < 1)) {
            g.setAttribute('opacity', String(l.opacity));
        }

        this.dataMarksOf(l).forEach(function (m) {
            var n;

            if (m.t === 'text') {
                n = document.createElementNS(SVGNS, 'text');
                n.setAttribute('x', String(m.x));
                n.setAttribute('y', String(m.y));
                n.setAttribute('font-family', font);
                n.setAttribute('font-size', String(m.size));
                n.setAttribute('font-weight', String(m.weight));
                n.setAttribute('fill', m.fill);
                n.setAttribute('text-anchor', m.anchor);
                n.textContent = m.v;
            } else {
                n = document.createElementNS(SVGNS, 'path');
                n.setAttribute('d', pathD(m.pts, m.t === 'poly'));

                if (m.t === 'poly') {
                    n.setAttribute('fill', m.fill);
                } else {
                    n.setAttribute('fill', 'none');
                    n.setAttribute('stroke', m.stroke);
                    n.setAttribute('stroke-width', String(m.width));
                    n.setAttribute('stroke-linejoin', 'round');
                    n.setAttribute('stroke-linecap', 'round');
                }
            }

            g.appendChild(n);
        });

        return g;
    }

    // The same marks onto the save's canvas, in rotated-space units.
    function dataPaint(g, l) {
        var font = family(l);

        g.translate(l.x + l.w / 2, l.y + l.h / 2);
        g.rotate((l.rot || 0) * Math.PI / 180);
        g.translate(-l.w / 2, -l.h / 2);

        this.dataMarksOf(l).forEach(function (m) {
            if (m.t === 'text') {
                g.font = m.weight + ' ' + m.size + 'px ' + font;
                g.textAlign = (m.anchor === 'middle') ? 'center' : ((m.anchor === 'end') ? 'right' : 'left');
                g.textBaseline = 'alphabetic';
                g.fillStyle = m.fill;
                g.fillText(m.v, m.x, m.y);

                return;
            }

            g.beginPath();
            m.pts.forEach(function (q, i) {
                g[i ? 'lineTo' : 'moveTo'](q[0], q[1]);
            });

            if (m.t === 'poly') {
                g.closePath();
                g.fillStyle = m.fill;
                g.fill();
            } else {
                g.strokeStyle = m.stroke;
                g.lineWidth = m.width;
                g.lineJoin = 'round';
                g.lineCap = 'round';
                g.stroke();
            }
        });
    }

    Editor.layerTypes.data = {
        node: function (l) { return dataNode.call(this, l); },
        paint: function (g, l) { dataPaint.call(this, g, l); },
        // The text's face, at the weights the guide draws with.
        ready: function (l) {
            var self = this, c = M.dataCheck(l.data), font = c.ok ? c.data.font : 'g:Inter';

            return Promise.all([400, 600, 700].map(function (weight) {
                return self.loadTextFont ? self.loadTextFont({ font: font, weight: weight, size: 20, text: 'Aa0' }) : null;
            }));
        },
        name: function (l) {
            var c = M.dataCheck(l.data), s = c.ok ? c.data.title : '';

            return (s.length > 32) ? s.slice(0, 31) + '…' : s;
        },
        // One data layer's own controls, first in Select's panel.
        panel: function (body, layers) {
            if (layers.length === 1) {
                this.dataPanel(body, layers[0]);
            }
        }
    };

    Editor.inits.push(function () {
        this.dataReads = {};
        // Tables asked for, the picker's list, the panel's open group.
        this.dataAsked = {};
        this.dataTables = null;
        this.dataPick = null;
        this.dataFold = 'data';
        // "Show on a canvas" on the Data screen: { table, show } opens step
        // two of the picker on that table at once.
        this.dataShow = (this.opts.dataShow && this.opts.dataShow.table) ? this.opts.dataShow : null;
    });

    Editor.views.push(function () {
        var start = this.dataShow;

        if (start && this.ready) {
            this.dataShow = null;
            this.openDataPicker(null, start);
        }
    });

    // ---- stills ----

    // The artboard as the Workspace keeps it (M.dataSendBare()): its data
    // layers drawn with no rows and a line saying they show where the canvas
    // is opened. The file is seen by every reader of the channel and a
    // guest; the rows follow each reader's rights only in the card's drawing
    // and the editor.
    Editor.prototype.drawArtboardBare = function () {
        this.dataBareDraw = true;

        try {
            return this.drawArtboard();
        } finally {
            this.dataBareDraw = false;
        }
    };

    // The line an export of a document with data layers shows: the picture
    // keeps the rows of now. null without a data layer.
    Editor.prototype.dataStillLine = function () {
        return M.dataTablesOf(this.doc).length ? el('p', { class: 'pgie-note pgie-datastill', text: t('dataStill') }) : null;
    };

    // The same once over the stage, after a download: once an editor.
    Editor.prototype.dataStillSay = function () {
        if (!this.dataStillSaid && M.dataTablesOf(this.doc).length) {
            this.dataStillSaid = true;
            this.showMessage(t('dataStill'));
        }
    };

    // ---- reading ----

    // The rows of a table read once for this reader (action data_rows),
    // handed to dataRead(); then(read) when given.
    Editor.prototype.dataLoad = function (table, then) {
        var self = this, key = String(table);

        if (!X.config().api || this.dataAsked[key]) {
            return;
        }

        this.dataAsked[key] = true;
        this.request({ action: 'data_rows', table: Number(table) }).then(function (r) {
            var read = (r && (r.status === 'success') && r.read) ? r.read : { state: 'refused' };

            if (!self.finished) {
                self.dataRead(table, read);
                self.renderPanel();

                if (then) {
                    then(read);
                }
            }
        }, function () {
            delete self.dataAsked[key];
        });
    };

    // The tables this reader may read (action data_tables), once:
    // [{ id, name, channel, rows, tone: { light, dark } }]; then(list),
    // null when they could not be read.
    Editor.prototype.dataTablesLoad = function (then) {
        var self = this;

        if (this.dataTables) {
            then(this.dataTables);
            return;
        }

        this.request({ action: 'data_tables' }).then(function (r) {
            if ((r && (r.status === 'success')) && Array.isArray(r.tables)) {
                self.dataTables = r.tables;
            }

            then(self.dataTables);
        }, function () {
            then(null);
        });
    };

    Editor.prototype.dataTableOf = function (id) {
        return (this.dataTables || []).filter(function (x) { return Number(x.id) === Number(id); })[0] || null;
    };

    // ---- the Data menu ----

    // A bar chart: the top bar's Data.
    X.icons.data = X.icons.data || 'M4.5 19.5h15M7.5 16v-4.5M12 16V6.5M16.5 16V9.5';

    // "Data" on the top bar right after Import (where layers are added),
    // with the editor's API: it opens the picker. Its word gives way to the
    // icon under 576 px, as Import's does.
    Editor.topData = function () {
        var self = this;

        if (!X.config().api || (this.tools.list.indexOf('select') < 0)) {
            return null;
        }

        this.dataBtn = el('button', { type: 'button', class: 'pgie-btn pgie-databtn', title: t('layer_data'), 'aria-label': t('layer_data'), 'aria-haspopup': 'dialog', onclick: function () {
            self.openDataPicker(null);
        } }, [X.icon('data'), el('span', { class: 'pgie-vlabel', text: t('layer_data') })]);

        return this.dataBtn;
    };

    (function () {
        var at = Editor.topItems.indexOf(Editor.topImport);

        Editor.topItems.splice((at < 0) ? Editor.topItems.length : at + 1, 0, Editor.topData);
    }());

    // The picker over the editor in two steps: "Pick a table" (a search
    // and the tables as rows), then "Pick a view" (a sample card for each
    // kind the table's columns fit). A card puts the layer on the stage, or
    // given a layer id (the panel's Change) gives that layer the table.
    // Given start ({ table, show: 'chart' | 'table' }) it opens on step two
    // with that table, no way back, the cards of the kinds show takes.
    Editor.prototype.openDataPicker = function (layerId, start) {
        var self = this, back = document.activeElement;

        this.closeConfirm();
        this.dataPick = { layer: layerId || null, search: '', table: null, show: start ? start.show : '' };
        this.confirmBox = el('div', { class: 'pgie-confirm', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-data-title' }, [
            el('div', { class: 'pgie-confirm-box pgie-importbox pgie-databox' })
        ]);
        this.confirmReturn = (back && this.root.contains(back)) ? back : (this.dataBtn || this.importBtn);
        this.root.appendChild(this.confirmBox);

        if (start) {
            this.dataPickViews(start.table);
        } else {
            this.dataPickTables();
        }
    };

    // The head of a step: its title, Back on the second step, Close.
    function pickHead(self, title, onBack) {
        var close = X.button('pgie-ibtn', t('close'), 'close', function () { self.closeConfirm(); });

        return el('div', { class: 'pgie-importhead' }, [
            onBack ? X.button('pgie-ibtn', t('dataBack'), 'chevl', onBack) : null,
            el('h2', { id: 'pgie-data-title', text: title }),
            close
        ]);
    }

    Editor.prototype.dataPickTables = function () {
        var self = this, box = this.confirmBox && this.confirmBox.firstChild, st = this.dataPick, list = el('div', { class: 'pgie-datatables', role: 'list' });
        var find = el('input', { type: 'search', class: 'pgie-input pgie-stkfind', value: st.search, placeholder: t('dataFind'), 'aria-label': t('dataFind') });

        if (!box) {
            return;
        }

        box.textContent = '';
        box.appendChild(pickHead(this, t('dataPickTable'), null));
        box.appendChild(find);
        box.appendChild(list);
        find.addEventListener('input', function () {
            st.search = find.value;
            self.dataTableRows(list);
        });
        list.appendChild(el('p', { class: 'pgie-note', text: t('dataReading') }));
        this.dataTablesLoad(function () {
            self.dataTableRows(list);
        });
        find.focus();
    };

    // The tables as rows (name, channel, row count), filtered by the search.
    Editor.prototype.dataTableRows = function (list) {
        var self = this, words = String(this.dataPick ? this.dataPick.search : '').toLowerCase().trim();
        var shown = (this.dataTables || []).filter(function (x) {
            return !words || (String(x.name) + ' ' + String(x.channel || '')).toLowerCase().indexOf(words) >= 0;
        });

        list.textContent = '';

        if (!this.dataTables || !shown.length) {
            list.appendChild(el('p', { class: 'pgie-note', text: t(!this.dataTables ? 'dataTablesError' : 'dataNoTables') }));
            return;
        }

        shown.forEach(function (x) {
            list.appendChild(el('button', { type: 'button', class: 'pgie-datatable', role: 'listitem', onclick: function () { self.dataPickViews(x); } }, [
                el('span', { class: 'pgie-ptext' }, [
                    el('b', { text: String(x.name) }),
                    el('span', { text: (x.channel ? '#' + x.channel : t('dataNoChannel')) + ' · ' + String(t('dataRowsCount')).replace('%d', String(x.rows || 0)) })
                ])
            ]));
        });
    };

    // Step two: the table's rows read, a sample card for each kind it fits.
    Editor.prototype.dataPickViews = function (x) {
        var self = this, box = this.confirmBox && this.confirmBox.firstChild, cards = el('div', { class: 'pgie-presets pgie-datacards' });

        if (!box) {
            return;
        }

        this.dataPick.table = x;
        box.textContent = '';
        box.appendChild(pickHead(this, t('dataPickView'), this.dataPick.show ? null : function () { self.dataPickTables(); }));
        box.appendChild(cards);
        cards.appendChild(el('p', { class: 'pgie-note', text: t('dataReading') }));

        var draw = function (read) {
            var kinds;

            if (!self.confirmBox || !self.confirmBox.contains(cards)) {
                return;
            }

            cards.textContent = '';

            if (!read || (read.state === 'refused')) {
                cards.appendChild(el('p', { class: 'pgie-note', text: t('dataRefused') }));
                return;
            }

            kinds = self.dataPick.show ? M.dataShowKinds(self.dataPick.show, read.columns, read.rows) : M.dataFits(read.columns, read.rows);

            // A table whose every column was deleted fits no kind, not even
            // a plain table.
            if (!M.dataColumns(read.columns, read.rows).length) {
                cards.appendChild(el('p', { class: 'pgie-note', text: t('dataNoColumns') }));
                return;
            }

            if ((self.dataPick.show !== 'table') && (M.dataFits(read.columns, read.rows).length === 1)) {
                box.insertBefore(el('p', { class: 'pgie-note', text: t('dataOnlyTable') }), cards);
            }

            kinds.forEach(function (kind) {
                var d = M.dataStart(Number(x.id), kind, read.columns, read.rows, x.name);

                cards.appendChild(self.dataCard(d, read, kind, false, function () { self.dataPlace(d); }));
            });

            if (cards.firstChild) {
                cards.firstChild.focus();
            }
        };

        if (this.dataReads[String(x.id)]) {
            draw(this.dataReads[String(x.id)]);
        } else {
            delete this.dataAsked[String(x.id)];
            this.dataLoad(x.id, draw);
        }
    };

    // A sample card: the description drawn small from the table's first
    // rows, its kind's name under it; on marks it pressed.
    Editor.prototype.dataCard = function (d, read, kind, on, pick) {
        var svg = document.createElementNS(SVGNS, 'svg'), rows = (read.rows || []).slice(0, M.DATA_SAMPLE_ROWS), w = 240, h = 150;
        var l = { x: 0, y: 0, w: w, h: h, rot: 0, data: Object.assign({}, d, { title: '' }) };

        svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.setAttribute('class', 'pgie-datasample');
        svg.appendChild(dataNode.call({ dataMarksOf: function () {
            return M.dataMarks(l.data, w, h, M.dataSeries(l.data, read.columns, rows, marksWords()), marksWords());
        } }, l));

        return el('button', { type: 'button', class: 'pgie-preset pgie-datacard' + (on ? ' on' : ''), 'aria-pressed': on ? 'true' : null, onclick: pick }, [
            svg, el('span', { class: 'pgie-ptext' }, [el('b', { text: t(KIND_LABELS[kind]) })])
        ]);
    };

    function marksWords() {
        return { empty: t('dataEmpty'), refused: t('dataRefused'), more: t('dataMore'), count: t('dataCount'), other: t('dataOther') };
    }

    // The picked view as a new layer in the middle of the canvas, 60 % of
    // its width at the card's shape, selected; or, from Change, as the
    // picked layer's new description in its box.
    Editor.prototype.dataPlace = function (d) {
        var self = this, at = this.dataPick ? this.dataPick.layer : null, size = M.docSize(this.doc), id;
        var w = Math.round(Math.min(size.w * 0.6, size.h * 0.6 * 16 / 9)), h = Math.round(w * 9 / 16);

        this.closeConfirm();
        this.dataPick = null;

        if (!d) {
            return;
        }

        if (at && find(this.doc, at)) {
            this.change(function (doc) { find(doc, at).data = d; });
            return;
        }

        this.toSelect();
        this.change(function (doc) {
            id = M.newId(doc);
            doc.layers.push({ id: id, type: 'data', x: Math.round((size.w - w) / 2), y: Math.round((size.h - h) / 2), w: w, h: h, rot: 0, opacity: 1, data: d });
        });
        self.select([id]);
    };

    // ---- the panel ----

    // The layer's panel in three groups folded one at a time: Data (the
    // table, columns, grouping, sort, row limit), Look (the kind as sample
    // cards, colours, legend) and Text (title, number format, type size). A
    // group shows only what the kind uses.
    Editor.prototype.dataPanel = function (body, l) {
        var self = this, c = M.dataCheck(l.data), d = c.ok ? c.data : null, read = d ? this.dataReads[String(d.table)] : null;
        // A change of the description; quiet keeps the panel as it is (a
        // title being typed, a slider or colour moving).
        var set = function (patch, key, quiet) {
            var next = M.dataCheck(Object.assign({}, d, patch));

            if (next.ok) {
                self.change(function (doc) {
                    var o = find(doc, l.id);

                    if (o) {
                        o.data = next.data;
                    }
                }, key || null, !!quiet);
            }
        };

        if (!d) {
            return;
        }

        if (!this.dataTables) {
            this.dataTablesLoad(function () { self.renderPanel(); });
        }

        [['data', t('layer_data')], ['look', t('dataGroupLook')], ['text', t('dataGroupText')]].forEach(function (g) {
            var open = (self.dataFold === g[0]);

            body.appendChild(el('button', { type: 'button', class: 'pgie-more pgie-datafold' + (open ? ' on' : ''), 'aria-expanded': open ? 'true' : 'false', onclick: function () {
                self.dataFold = open ? '' : g[0];
                self.renderPanel();
            } }, [el('span', { text: g[1] }), X.icon('chev')]));

            if (open) {
                self['dataGroup_' + g[0]](body, l, d, read, set);
            }
        });
    };

    // A select of choices [[value, label]].
    function choose(label, choices, value, pick) {
        var s = el('select', { class: 'pgie-input pgie-datafield', 'aria-label': label });

        choices.forEach(function (o) {
            s.appendChild(el('option', { value: o[0], text: o[1], selected: o[0] === value }));
        });
        s.addEventListener('change', function () { pick(s.value); });

        return section(label, s);
    }

    Editor.prototype.dataGroup_data = function (body, l, d, read, set) {
        var self = this, x = this.dataTableOf(d.table), cols, nums, cap, kinds, has, live;

        body.appendChild(section(t('dataTable'), el('div', { class: 'pgie-datatablerow' }, [
            el('span', { text: x ? String(x.name) : '…' }),
            el('button', { type: 'button', class: 'pgie-btn', text: t('dataChange'), onclick: function () { self.openDataPicker(l.id); } })
        ])));

        if (!read || (read.state === 'refused')) {
            body.appendChild(el('p', { class: 'pgie-note', text: t(read ? 'dataRefused' : 'dataReading') }));
            return;
        }

        cols = M.dataColumns(read.columns, read.rows);
        nums = cols.filter(function (o) { return (d.kind === 'table') || o.number; });
        // A column deleted from the table since the layer was made: the
        // panel says so, a blank stands for a gone label (so picking any
        // column is a change), and a gone value column is dropped by the
        // next change instead of hiding behind the checkboxes.
        has = function (id) { return cols.some(function (o) { return o.id === id; }); };
        live = d.values.filter(has);

        if (!has(d.label) || (live.length < d.values.length)) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('dataColumnGone') }));
        }

        body.appendChild(choose(t('dataLabel'), (has(d.label) ? [] : [['', '']]).concat(cols.map(function (o) { return [o.id, o.name]; })), has(d.label) ? d.label : '', function (v) {
            if (v !== '') {
                set({ label: v, values: live.filter(function (id) { return id !== v; }) });
            }
        }));

        if (d.group !== 'count') {
            if (d.kind === 'donut') {
                body.appendChild(choose(t('dataValues'), (live.length ? [] : [['', '']]).concat(nums.filter(function (o) { return o.id !== d.label; }).map(function (o) { return [o.id, o.name]; })), live.length ? live[0] : '', function (v) {
                    if (v !== '') {
                        set({ values: [v] });
                    }
                }));
            } else {
                body.appendChild(section(t('dataValues'), el('div', { class: 'pgie-datavalues' }, nums.filter(function (o) { return o.id !== d.label; }).map(function (o) {
                    var on = live.indexOf(o.id) >= 0, box = el('input', { type: 'checkbox' });

                    box.checked = on;
                    // Five at most; a chart keeps one.
                    box.disabled = (!on && (live.length >= M.DATA_MAX.values)) || (on && (d.kind !== 'table') && (live.length === 1));
                    box.addEventListener('change', function () {
                        set({ values: box.checked ? live.concat([o.id]) : live.filter(function (id) { return id !== o.id; }) });
                    });

                    return el('label', { class: 'pgie-check' }, [box, el('span', { text: o.name })]);
                }))));
            }
        }

        body.appendChild(section(t('dataGrouping'), this.drawSegment([['none', t('dataGroupNone')], ['sum', t('dataSum')], ['count', t('dataCount')], ['average', t('dataAverage')]], d.group, function (v) {
            var back = (v !== 'count') && !d.values.length ? (M.dataStart(d.table, (d.kind === 'table') ? 'table' : d.kind, read.columns, read.rows, '') || { values: [] }).values : d.values;

            set({ group: v, values: back });
        })));

        if (d.kind !== 'donut') {
            body.appendChild(section(t('dataSort'), this.drawSegment([['none', t('dataGroupNone')], ['label', t('dataSortLabel')], ['value', t('dataSortValue')]], d.sort, function (v) {
                set({ sort: v });
            })));

            if (d.sort !== 'none') {
                body.appendChild(this.drawSegment([['asc', t('dataAsc')], ['desc', t('dataDesc')]], d.dir, function (v) { set({ dir: v }); }));
            }

            cap = (d.kind === 'table') ? M.DATA_MAX.tableRows : M.DATA_MAX.labels;
            body.appendChild(this.drawSlider(t('dataLimit'), 1, cap, d.limit || cap, String, function (n, live) {
                set({ limit: (n >= cap) ? 0 : n }, 'data-limit-' + l.id, live);
            }));
        }

        kinds = M.dataFits(read.columns, read.rows);

        if (kinds.length === 1) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('dataOnlyTable') }));
        }
    };

    Editor.prototype.dataGroup_look = function (body, l, d, read, set) {
        var self = this, x = this.dataTableOf(d.table), tone = (x && x.tone) ? x.tone[d.theme] : '', cards, series, n, mode, now;

        if (read && (read.state !== 'refused')) {
            cards = el('div', { class: 'pgie-presets pgie-datacards pgie-datakinds' });
            M.dataFits(read.columns, read.rows).forEach(function (kind) {
                var next = (kind === d.kind) ? d : M.dataWithKind(d, kind, read.columns, read.rows);

                if (next) {
                    cards.appendChild(self.dataCard(next, read, kind, kind === d.kind, function () { set(next); }));
                }
            });
            body.appendChild(section(t('dataKind'), cards));
            series = M.dataSeries(d, read.columns, read.rows, marksWords());
        }

        n = (d.kind === 'donut') ? Math.min(M.DATA_MAX.colours, series ? series.labels.length : 1) : Math.min(M.DATA_MAX.colours, series ? series.series.length : 1);
        mode = !d.colours.length ? 'palette' : (((d.colours.length === 1) && (d.colours[0] === tone)) ? 'channel' : 'chosen');
        body.appendChild(section(t('dataColours'), this.drawSegment([['palette', t('dataPalette')]].concat(tone ? [['channel', t('dataChannel')]] : []).concat([['chosen', t('dataChosen')]]), mode, function (v) {
            var p = M.DATA_GUIDE.palettes[d.theme].series;

            set({ colours: (v === 'palette') ? [] : ((v === 'channel') ? [tone] : p.slice(0, Math.max(1, n))) });
        })));

        if (mode === 'chosen') {
            now = d.colours.slice();

            for (var j = 0; j < Math.max(1, n); j++) {
                (function (j) {
                    var name = series ? ((d.kind === 'donut') ? series.labels[j] : (series.series[j] || {}).name) : '';

                    body.appendChild(X.swatches(name || t('dataColours'), now[j % now.length] || '', function (colour, live) {
                        var next = d.colours.slice();

                        while (next.length <= j) {
                            next.push(next[next.length - 1] || colour);
                        }

                        next[j] = colour;
                        set({ colours: next }, live ? 'data-colour-' + j : null, live);
                    }));
                }(j));
            }
        }

        if ((d.kind !== 'donut') && (d.kind !== 'table') && series && (series.series.length > 1)) {
            body.appendChild(section(t('dataLegend'), this.drawSegment([['off', t('dataOff')], ['top', t('dataTop')], ['bottom', t('dataBottom')]], d.legend ? d.legendAt : 'off', function (v) {
                set((v === 'off') ? { legend: false } : { legend: true, legendAt: v });
            })));
        }
    };

    Editor.prototype.dataGroup_text = function (body, l, d, read, set) {
        var title = el('input', { type: 'text', class: 'pgie-input pgie-datafield', maxlength: String(M.DATA_MAX.title), value: d.title, 'aria-label': t('dataTitle') });

        title.addEventListener('input', function () {
            set({ title: title.value.replace(/[\n\r]+/g, ' ') }, 'data-title-' + l.id, true);
        });
        body.appendChild(section(t('dataTitle'), title));
        body.appendChild(section(t('dataFormatNumbers'), this.drawSegment([['en', M.dataFormat(12345.6, 'en')], ['tr', M.dataFormat(12345.6, 'tr')]], d.format, function (v) {
            set({ format: v });
        })));
        body.appendChild(section(t('dataSize'), this.drawSegment([['s', t('dataSmall')], ['m', t('dataMedium')], ['l', t('dataLarge')]], d.size, function (v) {
            set({ size: v });
        })));
    };
}(window.PgImageEditor.parts));
