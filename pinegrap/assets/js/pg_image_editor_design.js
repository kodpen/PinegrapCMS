/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Artifact Canvas's design file (a .pptx made in Canva or PowerPoint),
 * loaded the first time Import's "A design file (.pptx)" is used, never with
 * the canvas (designScript(), pg_image_editor_artboard.js). The file goes to
 * the action design_file (includes/fn/image_editor_design.php), which reads
 * its pages; the page picked comes back with its pictures kept in Files and
 * joins the open canvas as one group fitted to the board, in one undo step.
 *
 * Its pure part (designFit(), designPlace()) is exported for the tests under
 * Node (tests/image_editor_design_test.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    // A page of w x h placed in box: as large as fits, proportions kept,
    // centred. { x, y, w, h }.
    function designFit(size, box) {
        var w = Math.max(1, Number(size.w) || 1), h = Math.max(1, Number(size.h) || 1);
        var k = Math.min(box.w / w, box.h / h);

        return { x: box.x + (box.w - w * k) / 2, y: box.y + (box.h - h * k) / 2, w: w * k, h: h * k };
    }

    // Whether a page's board colour is drawn under its layers: any colour
    // but white or none, which the canvas's own board shows.
    function designColour(c) {
        return !!c && !/^(#fff|#ffffff|transparent)$/i.test(String(c));
    }

    // A page's document (the action's) as layers joining doc: its board's
    // colour a rectangle under them, a locked background unlocked so the
    // group moves whole, new ids, one new group, scaled as one into box
    // (M.groupScale()) with the letters, outlines and corners scaled too.
    // M is the model. { group, layers }.
    function designPlace(M, doc, page, box) {
        var a = page.artboard || {}, size = { w: Number(a.width) || 1, h: Number(a.height) || 1 };
        var from = { x: 0, y: 0, w: size.w, h: size.h }, fit = designFit(size, box), k = fit.w / size.w;
        var gid = M.groupNewId(doc), ids = { layers: (doc.layers || []).slice() }, list = [];

        if (designColour(a.background)) {
            list.push({ type: 'rect', x: 0, y: 0, w: size.w, h: size.h, rot: 0, opacity: 1, color: a.background, fill: 'solid', stroke: 0, radius: 0 });
        }

        (page.layers || []).forEach(function (l) {
            list.push(M.clone(l));
        });

        list.forEach(function (l) {
            l.id = M.newId(ids);
            ids.layers.push({ id: l.id });
            l.grp = [gid].concat(Array.isArray(l.grp) ? l.grp : []);
            delete l.locked;
        });

        list = M.groupScale(list, from, fit).map(function (l, i) {
            var start = list[i];

            if (l.type === 'text') {
                l.size = M.textResize(start, { x: l.x, y: l.y, w: l.w, h: l.h }, 'se').size;
            }

            if (Number(l.stroke) > 0) {
                l.stroke = Number(l.stroke) * k;
            }

            if ((l.type === 'rect') && (Number(l.radius) > 0)) {
                l.radius = Number(l.radius) * k;
            }

            return l;
        });

        return { group: gid, layers: list };
    }

    var api = { designFit: designFit, designPlace: designPlace };

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
    }

    if (!root || !root.PgImageEditor || !root.PgImageEditor.parts) {
        return;
    }

    var X = root.PgImageEditor.parts, Editor = X.Editor, M = X.M, t = X.t, el = X.el;

    // The file picker of Import's design item.
    Editor.prototype.designPick = function () {
        var self = this, input = el('input', { type: 'file', accept: '.pptx,application/vnd.openxmlformats-officedocument.presentationml.presentation', hidden: true });

        input.addEventListener('change', function () {
            var f = input.files && input.files[0];

            input.parentNode.removeChild(input);

            if (f) {
                self.designImport(f);
            }
        });
        this.root.appendChild(input);
        input.click();
    };

    // Where the pictures land: the open canvas's folder, or the one a canvas
    // not saved yet is made in.
    Editor.prototype.designHead = function (f) {
        return {
            action: 'design_file', file_id: this.opts.fileId || 0,
            folder_id: (this.opts.save && this.opts.save.folderId) || 0, name: (f && f.name) || ''
        };
    };

    // The page of a read file to bring in: the only one, else the first
    // (the page chooser takes this place).
    Editor.prototype.designChoosePage = function (read) {
        return Promise.resolve((read.pages && read.pages.length) ? read.pages[0].n : 0);
    };

    // A design file read (sent in parts when large, uploadSend()), a page
    // picked, then that page asked for with its pictures and added.
    Editor.prototype.designImport = function (f) {
        var self = this, head = this.designHead(f);

        this.showMessage(t('designReading'));

        return this.uploadSend(f, 'design', head, { error: t('designError') }).then(function (read) {
            return self.designChoosePage(read).then(function (n) {
                if (!n || self.finished) {
                    return null;
                }

                self.showMessage(t('designOpening'));

                return self.request(Object.assign({}, head, { design: read.design, pages: [n] })).then(function (r) {
                    if (!r || (r.status !== 'success') || !r.pages || !r.pages.length) {
                        throw new Error((r && r.message) || t('designError'));
                    }

                    if (!self.finished) {
                        self.designAdd(r.pages[0]);
                    }
                });
            });
        }).catch(function (e) {
            if (!self.finished) {
                self.showMessage((e && e.message) || t('designError'), true);
            }
        });
    };

    // A page's layers into the open canvas as one group fitted to the
    // board, one undo step; the group is picked.
    Editor.prototype.designAdd = function (page) {
        var box = this.viewRect(), placed = null, rep = page.report || {};

        this.change(function (doc) {
            placed = designPlace(M, doc, page.doc || {}, box);
            placed.layers.forEach(function (l) { doc.layers.push(l); });
            doc.layers = M.groupTidy(doc.layers);
        });
        this.select(placed.layers.map(function (l) { return l.id; }));
        this.showMessage(t('designAdded').replace('{n}', page.n).replace('{texts}', rep.texts || 0).replace('{pictures}', rep.pictures || 0));
    };
}((typeof window !== 'undefined') ? window : null));
