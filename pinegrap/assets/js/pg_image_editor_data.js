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

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, t = X.t;

    // The rows a layer's table gave this reader, as the editor holds them:
    // { columns, rows }, or { state: 'refused' }; nothing before the read.
    Editor.prototype.dataRead = function (table, read) {
        this.dataReads[String(table)] = read || null;
        this.layersDirty = true;
        this.renderView();
    };

    // A layer's marks (M.dataMarks()) in its box; a description that does
    // not pass its check draws as an empty layer.
    Editor.prototype.dataMarksOf = function (l) {
        var c = M.dataCheck(l.data), read, words = { empty: t('dataEmpty'), refused: t('dataRefused'), more: t('dataMore'), count: t('dataCount'), other: t('dataOther') };

        if (!c.ok) {
            return M.dataMarks(M.dataCheck({ table: 1, label: 'x', values: ['x'] }).data, l.w, l.h, { labels: [], series: [] }, words);
        }

        read = this.dataReads[String(c.data.table)];

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
        }
    };

    Editor.inits.push(function () {
        this.dataReads = {};
    });
}(window.PgImageEditor.parts));
