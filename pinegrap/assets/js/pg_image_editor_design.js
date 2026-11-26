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
 * The New image dialog's "Open a design file" (newImageRows, the artboard
 * part) uses it too: the window "Open a design file" shows the pages as
 * tiles, and the ticked ones become canvas files in the dialog's folder, or
 * the scenes of one canvas, the first opening in the editor; a line under
 * the top bar reports what was brought in.
 *
 * Its pure part (designFit(), designPlace(), the chooser's designTicks()
 * to designRoom()) is exported for the tests under Node
 * (tests/image_editor_design_test.php).
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

    // The pages ticked when the chooser opens: every one from the New image
    // dialog, the first from Import (one page joins an open canvas).
    function designTicks(pages, from) {
        var list = (pages || []).map(function (p) { return p.n; });

        return (from === 'new') ? list : list.slice(0, 1);
    }

    // A canvas made from the file: its name without the folder and .pptx,
    // then the page's number (none for one canvas of scenes); '' unnamed.
    function designName(fileName, n) {
        var stem = String(fileName || '').split(/[\\/]/).pop().replace(/\.pptx$/i, '').trim();

        return (stem === '') ? '' : (n ? stem + ' ' + n : stem);
    }

    // PHP answers an empty list as [], a filled one as an object.
    function designPairs(map) {
        return (map && (typeof map === 'object') && !Array.isArray(map))
            ? Object.keys(map).map(function (k) { return [k, map[k]]; }) : [];
    }

    // A page not read whole (a part left out or simplified): the amber mark.
    function designPartial(page) {
        var rep = (page && page.report) || {};

        return (designPairs(rep.left).length > 0) || (Number(rep.simplified) > 0);
    }

    // A page's document as its tile draws it: a picture still in the
    // package (src pptx:) is a plain box in its place.
    function designThumbDoc(M, doc) {
        var next = M.clone(doc || {});

        next.layers = (next.layers || []).map(function (l) {
            if ((l.type !== 'image') || !/^pptx:/.test(String(l.src || ''))) {
                return l;
            }

            return { id: l.id, type: 'rect', x: l.x, y: l.y, w: l.w, h: l.h, rot: l.rot || 0, opacity: (l.opacity === undefined) ? 1 : l.opacity,
                color: '#cfd5dc', fill: 'solid', stroke: 0, radius: 0 };
        });

        return next;
    }

    // Pages ([{ n, doc }]) as one canvas with a scene a page, the first one
    // open; one page is a canvas without scenes.
    function designScenes(M, pages) {
        var doc = M.clone(pages[0].doc);

        pages.slice(1).forEach(function (p) {
            M.sceneAdd(doc, false);
            doc.layers = M.clone(p.doc.layers || []);
            doc.artboard.background = (p.doc.artboard && p.doc.artboard.background) || '#ffffff';
        });

        if (M.sceneCount(doc)) {
            M.sceneOpen(doc, doc.scenes[0].id);
        }

        return doc;
    }

    // The report line's numbers: fonts as [family, stand-in], what was left
    // out as [kind, count] and their sum.
    function designReport(rep) {
        rep = rep || {};

        var left = designPairs(rep.left);

        return {
            texts: Number(rep.texts) || 0,
            pictures: Number(rep.pictures) || 0,
            fonts: designPairs(rep.fonts),
            left: left,
            leftCount: left.reduce(function (sum, p) { return sum + (Number(p[1]) || 0); }, 0),
            simplified: Number(rep.simplified) || 0
        };
    }

    // Why Open waits: 'none' (nothing ticked), 'scenes' (more pages than one
    // canvas has scenes), else ''.
    function designRoom(count, scenes, max) {
        if (count < 1) {
            return 'none';
        }

        return (scenes && (count > max)) ? 'scenes' : '';
    }

    var api = {
        designFit: designFit, designPlace: designPlace, designTicks: designTicks, designName: designName, designPartial: designPartial,
        designThumbDoc: designThumbDoc, designScenes: designScenes, designReport: designReport, designRoom: designRoom
    };

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
    }

    if (!root || !root.PgImageEditor || !root.PgImageEditor.parts) {
        return;
    }

    var X = root.PgImageEditor.parts, Editor = X.Editor, M = X.M, t = X.t, el = X.el;

    var SVGNS = 'http://www.w3.org/2000/svg';

    // What the reader and the mapper count as left out, each with its label
    // (designLeft_<kind>); any other kind reads as other.
    var LEFT_KINDS = ['chart', 'table', 'diagram', 'object', 'media', 'ink', 'gradient', 'pattern', 'picture', 'mask', 'other'];

    // The file picker: from Import (one page joins the open canvas) or the
    // New image dialog (from 'new', with say() writing in that dialog and
    // ready() closing it once the file is read).
    Editor.prototype.designPick = function (from, say, ready) {
        var self = this, input = el('input', { type: 'file', accept: '.pptx,application/vnd.openxmlformats-officedocument.presentationml.presentation', hidden: true });

        input.addEventListener('change', function () {
            var f = input.files && input.files[0];

            input.parentNode.removeChild(input);

            if (f) {
                self.designImport(f, from, say, ready);
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

    // A design file read (sent in parts when large, uploadSend()), its pages
    // chosen in the window, then those pages asked for with their pictures
    // and opened: from Import one page added, from the New image dialog
    // canvases or scenes. A refusal is a sentence where the person is: the
    // New image dialog, the window, else a line over the stage.
    Editor.prototype.designImport = function (f, from, say, ready) {
        var self = this, head = this.designHead(f);

        say = say || function (text, error) { self.showMessage(text, error); };
        say(t('designReading'));

        return this.uploadSend(f, 'design', head, { error: t('designError') }).then(function (read) {
            if (self.finished) {
                return null;
            }

            if (!read.pages || !read.pages.length) {
                throw new Error(t('designError'));
            }

            if (ready) {
                ready();
            }

            return self.designChoose(read, from, function (choice) {
                self.showMessage(t('designOpening'));

                return self.request(Object.assign({}, head, { design: read.design, pages: choice.pages })).then(function (r) {
                    if (!r || (r.status !== 'success') || !r.pages || !r.pages.length) {
                        throw new Error((r && r.message) || t('designError'));
                    }

                    if (self.finished) {
                        return null;
                    }

                    if (from === 'new') {
                        return self.designOpenPages(r, choice.scenes, head.folder_id, f.name || '');
                    }

                    self.designAdd(r.pages[0]);
                    self.designReportLine(r.report);

                    return null;
                });
            });
        }).catch(function (e) {
            if (!self.finished) {
                (self.board.dialog ? say : function (text, error) { self.showMessage(text, error); })((e && e.message) || t('designError'), true);
            }
        });
    };

    // A page's document drawn small by the canvas's own layer drawing.
    Editor.prototype.designThumb = function (doc) {
        var a = (doc && doc.artboard) || { width: 1, height: 1 }, svg = document.createElementNS(SVGNS, 'svg'), self = this;

        svg.setAttribute('viewBox', '0 0 ' + a.width + ' ' + a.height);
        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        svg.setAttribute('class', 'pgie-dthumb');
        svg.setAttribute('aria-hidden', 'true');

        var bg = document.createElementNS(SVGNS, 'rect');

        bg.setAttribute('width', String(a.width));
        bg.setAttribute('height', String(a.height));
        bg.setAttribute('fill', (a.background && (a.background !== 'transparent')) ? a.background : '#ffffff');
        svg.appendChild(bg);

        designThumbDoc(M, doc).layers.forEach(function (l) {
            var n = l.hidden ? null : self.layerNode(M.groupLook(l));

            if (n) {
                svg.appendChild(n);
            }
        });

        return svg;
    };

    // The window "Open a design file": the pages as tiles in the file's
    // order, each with its number, a tick and the amber mark when not read
    // whole. From Import one page is picked; from the New image dialog
    // every page starts ticked and the foot says what Open makes. A file of
    // one page skips the tiles. open(choice) answers a promise; its refusal
    // stays in the window as a sentence.
    Editor.prototype.designChoose = function (read, from, open) {
        var self = this, pages = read.pages, many = (from === 'new');
        var ticked = designTicks(pages, from), max = M.SCENE_MAX || 50;

        if (pages.length === 1) {
            return open({ pages: [pages[0].n], scenes: false });
        }

        return new Promise(function (resolve) {
            var tiles = el('div', { class: 'pgie-dtiles', role: many ? 'group' : 'radiogroup', 'aria-label': t('designTitle') });
            var asScenes = el('input', { type: 'checkbox' });
            var note = el('p', { class: 'pgie-dnote', role: 'status' });
            var go = el('button', { type: 'button', class: 'pgie-btn pgie-primary', text: many ? t('designOpen') : t('designAddPage') });
            var cancel = el('button', { type: 'button', class: 'pgie-btn', text: t('cancel') });
            var label = el('span', { class: 'pgie-dcount' });
            var box, win, busy = false;

            var refresh = function () {
                Array.prototype.forEach.call(tiles.children, function (b) {
                    var on = ticked.indexOf(Number(b.getAttribute('data-n'))) >= 0;

                    b.classList.toggle('on', on);
                    b.setAttribute(many ? 'aria-pressed' : 'aria-checked', on ? 'true' : 'false');
                });

                var n = ticked.length, room = designRoom(n, many && asScenes.checked, max);

                label.textContent = !many ? '' : (asScenes.checked ? t('designAsScenesFoot') : t((n === 1) ? 'designCanvasesOne' : 'designCanvases')).replace(/\{n\}/g, n);
                note.classList.remove('pgie-derror');
                note.textContent = (room === 'scenes') ? t('designScenesMax').replace('{n}', max) : ((room === 'none') ? t('designNone') : '');
                go.disabled = busy || (room !== '');
                cancel.disabled = busy;
            };

            var close = function () {
                if (win.parentNode) {
                    win.parentNode.removeChild(win);
                }

                self.stage.focus({ preventScroll: true });
                resolve(null);
            };

            pages.forEach(function (p) {
                var partial = designPartial(p), name = t('designPage').replace('{n}', p.n);

                tiles.appendChild(el('button', { type: 'button', class: 'pgie-dtile', 'data-n': String(p.n), role: many ? null : 'radio',
                    'aria-label': partial ? name + ', ' + t('designPartial') : name, title: partial ? t('designPartial') : null, onclick: function () {
                        var at = ticked.indexOf(p.n);

                        if (!many) {
                            ticked = [p.n];
                        } else if (at >= 0) {
                            ticked.splice(at, 1);
                        } else {
                            ticked = pages.map(function (q) { return q.n; }).filter(function (n) { return (n === p.n) || (ticked.indexOf(n) >= 0); });
                        }

                        refresh();
                    } }, [
                    el('span', { class: 'pgie-dpic' }, [self.designThumb(p.doc)]),
                    el('span', { class: 'pgie-dname' }, [
                        el('span', { class: 'pgie-dtick', 'aria-hidden': 'true' }),
                        el('span', { text: String(p.n) }),
                        partial ? el('span', { class: 'pgie-damber', 'aria-hidden': 'true' }) : null
                    ])
                ]));
            });

            asScenes.addEventListener('change', refresh);
            cancel.addEventListener('click', close);
            go.addEventListener('click', function () {
                busy = true;
                refresh();
                note.textContent = t('designOpening');

                Promise.resolve(open({ pages: ticked.slice(), scenes: many && asScenes.checked })).then(function () {
                    busy = false;
                    close();
                }, function (e) {
                    busy = false;
                    refresh();
                    note.classList.add('pgie-derror');
                    note.textContent = (e && e.message) || t('designError');
                });
            });

            box = el('div', { class: 'pgie-confirm-box pgie-newbox pgie-designbox' }, [
                el('h2', { id: 'pgie-design-title', text: t('designTitle') }),
                many ? null : el('p', { text: t('designPickOne') }),
                read.truncated ? el('p', { text: t('designTruncated').replace('{n}', pages.length) }) : null,
                tiles,
                el('div', { class: 'pgie-dfoot' }, [
                    many ? label : null,
                    many ? el('label', { class: 'pgie-check' }, [asScenes, el('span', { text: t('designAsScenes') })]) : null,
                    note,
                    el('div', { class: 'pgie-confirm-actions' }, [cancel, go])
                ])
            ]);
            win = el('div', { class: 'pgie-confirm', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-design-title' }, [box]);

            // Its keys stay in it, as the New image dialog's: Tab goes round,
            // Escape cancels.
            win.addEventListener('keydown', function (e) {
                var list = Array.prototype.filter.call(box.querySelectorAll('button, input'), function (n) { return !n.disabled && n.offsetParent !== null; });
                var i = list.indexOf(document.activeElement);

                e.stopPropagation();

                if ((e.key === 'Escape') && !busy) {
                    e.preventDefault();
                    close();
                } else if ((e.key === 'Tab') && list.length && (e.shiftKey ? i <= 0 : i === list.length - 1)) {
                    e.preventDefault();
                    list[e.shiftKey ? list.length - 1 : 0].focus();
                }
            });
            win.addEventListener('paste', function (e) { e.stopPropagation(); });
            refresh();
            self.root.appendChild(win);
            (tiles.querySelector('.on') || go).focus();
        });
    };

    // The pages asked for (r, with their pictures in Files) as canvas files
    // in folder: one a page named by the file and the page's number, or one
    // canvas with a scene a page (scenes). Each is drawn and saved as a new
    // canvas is (sendBody()), one after the other; the first then opens in
    // this editor, saved over from now on. A page that cannot be saved
    // stops the rest with a sentence naming how many were.
    Editor.prototype.designOpenPages = function (r, scenes, folder, fileName) {
        var self = this, list;

        if (scenes) {
            list = [{ name: designName(fileName, 0), doc: designScenes(M, r.pages) }];
        } else {
            list = r.pages.map(function (p) { return { name: designName(fileName, p.n), doc: p.doc }; });
        }

        list.forEach(function (item) {
            item.doc = M.canvasOpenDoc(item.doc) || item.doc;
        });

        var saved = [];

        return list.reduce(function (chain, item) {
            return chain.then(function () {
                if (self.finished) {
                    return null;
                }

                self.showMessage(t('designSaving').replace('{done}', saved.length).replace('{n}', list.length));

                return self.withDoc(item.doc, function () { return self.layersReady(); }).then(function () {
                    var body = self.withDoc(item.doc, function () { return self.sendBody('new', { folder: { id: folder } }); });

                    if (!body) {
                        throw new Error(t('saveError'));
                    }

                    body.name = item.name;

                    return self.request(body);
                }).then(function (a) {
                    if (!a || (a.status !== 'success')) {
                        throw new Error((a && a.message) || t('saveError'));
                    }

                    saved.push({ id: a.id, name: a.name || item.name, url: a.url || '', doc: item.doc });
                });
            });
        }, Promise.resolve()).then(function () {
            self.hideMessage();
            self.designCreated(saved);

            if (saved.length && !self.finished) {
                self.designOpenSaved(saved[0]);
                self.designReportLine(r.report);
            }
        }, function (e) {
            self.designCreated(saved);

            throw new Error(saved.length
                ? t('designSaveStopped').replace('{done}', saved.length).replace('{n}', list.length) + ' ' + ((e && e.message) || '')
                : ((e && e.message) || t('saveError')));
        });
    };

    // The caller hears of the canvas files written (options.onCreated: the
    // File Manager shows them).
    Editor.prototype.designCreated = function (saved) {
        if (saved.length && (typeof this.opts.onCreated === 'function')) {
            this.opts.onCreated(saved.map(function (s) { return { id: s.id, name: s.name, url: s.url }; }));
        }
    };

    // A canvas file just written opened in this editor as a canvas opened
    // from Files is: its document, nothing to undo or save, and Save writes
    // over that file.
    Editor.prototype.designOpenSaved = function (file) {
        this.opts.fileId = file.id;
        this.opts.fileName = file.name;
        this.opts.save = Object.assign({}, this.opts.save || {}, { mode: 'replace', name: file.name });
        this.saveMode = 'replace';
        this.file = { id: file.id, name: file.name, url: file.url, replace: true };
        this.nameEl.textContent = file.name;
        this.dialog.setAttribute('aria-label', t('dialog') + ': ' + file.name);
        this.history = new M.History(100);
        this.opening = null;
        this.select([]);
        this.openFile({ canvas: true, doc: file.doc, versions: 1 });
        this.settleDirty();
        this.renderSave();
    };

    // The line under the top bar after Open: texts and pictures brought in,
    // the fonts replaced by name, the elements left out, and Details
    // opening the list. Its close button takes it away.
    Editor.prototype.designReportLine = function (report) {
        var self = this, rep = designReport(report), parts = [], details, line, more;

        if (this.designLine && this.designLine.parentNode) {
            this.designLine.parentNode.removeChild(this.designLine);
        }

        parts.push(t('designReportLine').replace('{texts}', rep.texts).replace('{pictures}', rep.pictures));

        if (rep.fonts.length) {
            parts.push(t('designReportFonts').replace('{list}', rep.fonts.map(function (f) { return f[0]; }).join(', ')));
        }

        if (rep.leftCount) {
            parts.push(t('designReportLeft').replace('{n}', rep.leftCount));
        }

        details = el('ul', { class: 'pgie-dlist', id: 'pgie-design-details', hidden: true },
            rep.fonts.map(function (f) {
                return el('li', { text: t('designFontSwap').replace('{from}', f[0]).replace('{to}', f[1]) });
            }).concat(rep.left.map(function (p) {
                return el('li', { text: t('designLeftKind').replace('{kind}', t('designLeft_' + ((LEFT_KINDS.indexOf(p[0]) >= 0) ? p[0] : 'other'))).replace('{n}', p[1]) });
            }), rep.simplified ? [el('li', { text: t('designSimplified').replace('{n}', rep.simplified) })] : []));

        more = (rep.fonts.length || rep.leftCount || rep.simplified) ? el('button', { type: 'button', class: 'pgie-btn pgie-dmore', 'aria-expanded': 'false',
            'aria-controls': 'pgie-design-details', text: t('designDetails'), onclick: function () {
                details.hidden = !details.hidden;
                more.setAttribute('aria-expanded', details.hidden ? 'false' : 'true');
            } }) : null;

        line = el('div', { class: 'pgie-dreport', role: 'status' }, [
            el('div', { class: 'pgie-drow' }, [
                el('span', { class: 'pgie-dtext', text: parts.join(' ') }),
                more,
                el('button', { type: 'button', class: 'pgie-ibtn sm', 'aria-label': t('close'), title: t('close'), text: '×', onclick: function () {
                    line.parentNode.removeChild(line);
                    self.designLine = null;
                } })
            ]),
            details
        ]);

        this.designLine = line;
        this.root.insertBefore(line, this.root.querySelector('.pgie-body'));
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
