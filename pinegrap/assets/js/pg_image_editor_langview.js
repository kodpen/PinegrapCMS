/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the language view, where one language's picture texts are
 * moved and sized by hand. "Correct by hand" on a row of the Languages list
 * (pg_image_editor_artboard.js) shows the saved document as that language's
 * picture is drawn (M.languageView()): only its texts can be picked, with
 * Select; a drag moves one, a corner sizes its letters, a side sets the
 * width its lines wrap to. Each change becomes the text's correction for the
 * language (M.languageViewSettle()); "Done" writes them into the canvas
 * document (M.languageViewCorrections()) as one undo step, and the canvas's
 * Save keeps them. The source picture's texts, places and the other
 * languages never change here.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_artboard.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el;

    Editor.inits.push(function () {
        this.lv = null;
        // A language asked for at the opening (the queue's Look by hand row).
        this.langAsk = (this.opts && (typeof this.opts.language === 'string')) ? this.opts.language : '';
    });

    // The layout every fit and put of the view measures with.
    Editor.prototype.lvLayout = function () {
        var self = this;

        return function (l) { return self.textLayout(l); };
    };

    // "Correct by hand" on a row: a language is corrected on the saved
    // document, so unsaved changes are saved first (Save closes the canvas).
    Editor.prototype.correctLanguage = function (lang) {
        var self = this;

        if (!this.dirty) {
            this.closeConfirm();
            this.enterLanguage(lang);
            return;
        }

        var keep = el('button', { type: 'button', class: 'pgie-btn', text: t('cancel'), onclick: function () { self.closeConfirm(); } });
        var save = el('button', { type: 'button', class: 'pgie-btn pgie-primary', text: t('save'), onclick: function () {
            self.closeConfirm();
            self.save();
        } });

        this.closeConfirm();
        this.confirmBox = el('div', { class: 'pgie-confirm', role: 'alertdialog', 'aria-modal': 'true', 'aria-describedby': 'pgie-lv-save' }, [
            el('div', { class: 'pgie-confirm-box' }, [
                el('p', { id: 'pgie-lv-save', text: t('langSaveFirst') }),
                el('div', { class: 'pgie-confirm-actions' }, [keep, save])
            ])
        ]);
        this.confirmReturn = this.langBtn;
        this.root.appendChild(this.confirmBox);
        save.focus();
    };

    // Shows the language's picture in place of the document: the editor's
    // own document, history and tools are kept aside until the view ends.
    Editor.prototype.enterLanguage = function (lang) {
        var self = this, board = M.docSize(this.doc);
        var view = M.languageView(this.doc, lang.texts, lang.code, this.lvLayout(), board);

        if (!view || this.lv || !this.ready) {
            this.showMessage(t('langViewError'), true);
            return;
        }

        this.lv = {
            code: lang.code, name: lang.name, board: board, view: view, texts: lang.texts,
            saved: { doc: this.doc, history: this.history, opening: this.opening, tools: this.tools, tool: this.tool, sheetOpen: this.sheetOpen }
        };
        this.doc = view.doc;
        this.history = new M.History();
        this.opening = M.clone(view.doc);
        this.tools = Object.assign({}, this.tools, { list: ['select'] });
        this.tool = 'select';
        this.sel = [];
        this.layersOpen = false;
        this.sheetOpen = false;
        this.layersDirty = true;
        this.root.classList.add('pgie-langview');
        this.stage.appendChild(this.lvBar());
        this.renderTools();
        this.render();

        // The translated words may need faces not loaded yet: once they are,
        // the view is laid out again while nothing has changed in it.
        this.layersReady().then(function () {
            var again = (self.lv && (self.lv.view === view) && !self.history.canUndo()) ? M.languageView(self.lv.saved.doc, lang.texts, lang.code, self.lvLayout(), board) : null;

            if (again) {
                self.lv.view = again;
                self.doc = again.doc;
                self.opening = M.clone(again.doc);
                self.layersDirty = true;
                self.render();
            }
        }, function () { /* drawn with the faces there are */ });
    };

    // The bar over the stage (at its foot on a phone): the language's name,
    // what the view does, its menu, Back to automatic and Done.
    Editor.prototype.lvBar = function () {
        var self = this, lv = this.lv, url = (this.langData && this.langData.words_url) || '';
        var menu = null, more = null;

        if (url) {
            menu = el('div', { class: 'pgie-lvmenu', role: 'menu', hidden: true }, [
                el('a', { role: 'menuitem', href: url, target: '_blank', rel: 'noopener', text: t('langEditWords') })
            ]);
            more = el('button', { type: 'button', class: 'pgie-btn', text: t('langViewMenu'), 'aria-haspopup': 'menu', 'aria-expanded': 'false', onclick: function () {
                menu.hidden = !menu.hidden;
                more.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
            } });
        }

        lv.auto = el('button', { type: 'button', class: 'pgie-btn', text: t('langAuto'), onclick: function () { self.lvAutomatic(); } });
        lv.bar = el('div', { class: 'pgie-lvbar', role: 'toolbar', 'aria-label': lv.name }, [
            el('span', { class: 'pgie-lvname' }, [el('strong', { text: lv.name }), el('span', { class: 'pgie-note', text: t('langViewLine') })]),
            el('span', { class: 'pgie-lvmore' }, [more, menu]),
            lv.auto,
            el('button', { type: 'button', class: 'pgie-btn pgie-primary', text: t('langDone'), onclick: function () { self.leaveLanguage(); } })
        ]);

        // The bar's presses are its own, not the stage's Select.
        ['pointerdown', 'pointerup', 'dblclick', 'wheel'].forEach(function (type) {
            lv.bar.addEventListener(type, function (e) { e.stopPropagation(); });
        });

        return lv.bar;
    };

    // Back to automatic: the picked texts' corrections off, or with none
    // picked every text's of the language, after a question.
    Editor.prototype.lvAutomatic = function () {
        var self = this, code = this.lv.code, ids = this.sel.slice();
        var off = function () {
            self.change(function (doc) {
                doc.layers.forEach(function (l) {
                    if (!ids.length || (ids.indexOf(l.id) >= 0)) {
                        M.languageCorrect(l, code, null);
                    }
                });
            });
        };

        if (ids.length) {
            off();
            return;
        }

        var keep = el('button', { type: 'button', class: 'pgie-btn', text: t('cancel'), onclick: function () { self.closeConfirm(); } });
        var go = el('button', { type: 'button', class: 'pgie-btn pgie-primary', text: t('langAuto'), onclick: function () {
            self.closeConfirm();
            off();
        } });

        this.confirmBox = el('div', { class: 'pgie-confirm', role: 'alertdialog', 'aria-modal': 'true', 'aria-describedby': 'pgie-lv-auto' }, [
            el('div', { class: 'pgie-confirm-box' }, [
                el('p', { id: 'pgie-lv-auto', text: t('langAutoAll').replace('{name}', this.lv.name) }),
                el('div', { class: 'pgie-confirm-actions' }, [keep, go])
            ])
        ]);
        this.confirmReturn = this.lv.auto;
        this.root.appendChild(this.confirmBox);
        keep.focus();
    };

    // Done: the view's corrections into the canvas document, one undo step
    // there; the editor's document, history and tools come back.
    Editor.prototype.leaveLanguage = function () {
        var lv = this.lv;

        if (!lv) {
            return;
        }

        var next = M.clone(lv.saved.doc), changed = M.languageViewCorrections(next, this.doc, lv.code);

        this.closeConfirm();
        this.lv = null;
        this.doc = lv.saved.doc;
        this.history = lv.saved.history;
        this.opening = lv.saved.opening;
        this.tools = lv.saved.tools;
        this.tool = lv.saved.tool;
        this.sheetOpen = lv.saved.sheetOpen;
        this.sel = [];

        if (changed) {
            this.history.push(M.clone(this.doc));
            this.doc = next;
        }

        if (lv.bar && lv.bar.parentNode) {
            lv.bar.parentNode.removeChild(lv.bar);
        }

        this.root.classList.remove('pgie-langview');
        this.settleDirty();
        this.layersDirty = true;
        this.renderMeta();
        this.renderTools();
        this.render();
    };

    // A change in the view is kept to what it may change and turned into
    // the texts' corrections (M.languageViewSettle()).
    var changeLv = Editor.prototype.change;

    Editor.prototype.change = function (mutate, key, viewOnly) {
        var self = this, before;

        if (!this.lv) {
            return changeLv.apply(this, arguments);
        }

        before = M.clone(this.doc);

        return changeLv.call(this, function (doc) {
            mutate(doc);
            M.languageViewSettle(before, doc, self.lv.view, self.lvLayout(), self.lv.board);
        }, key, viewOnly);
    };

    // A side handle sets the width the lines wrap to, the letters' size
    // kept; a corner sizes the letters as everywhere.
    var textType = Editor.layerTypes.text, textResize = textType && textType.resize;

    if (textResize) {
        textType.resize = function (start, box, h) {
            if (!this.lv || ((h !== 'e') && (h !== 'w'))) {
                return textResize.call(this, start, box, h);
            }

            var fix = M.languageCorrection(start, this.lv.code), fw = Math.max(1, (fix ? fix.w : start.w) + box.w - start.w);

            return { lvFix: M.languageCorrectionAt(start, fw, box.x, box.y, box.w) };
        };
    }

    // The panel: the layers, a text to pick, every other layer locked, a
    // dot on a corrected text.
    var renderPanelLv = Editor.prototype.renderPanel;

    Editor.prototype.renderPanel = function () {
        var self = this, lv = this.lv;

        if (!lv || !this.panel) {
            return renderPanelLv.apply(this, arguments);
        }

        var list = el('ul', { class: 'pgie-layerlist', 'aria-label': t('layers') });

        this.doc.layers.slice().reverse().forEach(function (l) {
            var text = (l.type === 'text'), on = (self.sel.indexOf(l.id) >= 0), fixed = text && !!M.languageCorrection(l, lv.code);

            list.appendChild(el('li', { class: 'pgie-lrow' + (on ? ' on' : '') + (text ? '' : ' pgie-lvlocked') }, [
                el('button', { type: 'button', class: 'pgie-lname', disabled: !text, 'aria-pressed': on ? 'true' : 'false', onclick: function () {
                    self.select([l.id]);
                } }, [X.icon(text ? 'text' : 'lock'), el('span', { text: self.layerName(l) }), fixed ? el('span', { class: 'pgie-lvdot', title: t('langCorrect') }) : null])
            ]));
        });

        this.panel.textContent = '';
        this.panel.setAttribute('aria-label', lv.name);
        this.panel.appendChild(el('div', { class: 'pgie-ph' }, [el('h2', { text: lv.name })]));
        this.panel.appendChild(el('div', { class: 'pgie-pb' }, [list]));
    };

    // On the stage: a dot on each corrected text's box, the amber outline
    // of a text past the picture's edge; Back to automatic only where a
    // correction is to take off.
    Editor.views.push(function () {
        var self = this, lv = this.lv, v = this.view, vr = this.viewRect(), k = v.k, any = false;

        if (!lv || !this.selEl) {
            return;
        }

        this.doc.layers.forEach(function (l) {
            var fixed = (l.type === 'text') && !!M.languageCorrection(l, lv.code), past = (l.type === 'text') && !l.hidden && M.languagePast(l, lv.board);

            any = any || (fixed && (!self.sel.length || (self.sel.indexOf(l.id) >= 0)));

            if (!fixed && !past) {
                return;
            }

            var d = el('div', { class: 'pgie-lvbox' + (past ? ' pgie-lvpast' : '') }, [fixed ? el('span', { class: 'pgie-lvdot' }) : null]);

            d.style.left = (v.x + (l.x - vr.x) * k) + 'px';
            d.style.top = (v.y + (l.y - vr.y) * k) + 'px';
            d.style.width = (l.w * k) + 'px';
            d.style.height = (l.h * k) + 'px';
            d.style.transform = l.rot ? 'rotate(' + l.rot + 'deg)' : '';
            self.selEl.appendChild(d);
        });

        if (lv.auto) {
            lv.auto.disabled = !any;
        }
    });

    // No typing, deleting, copying or pasting in the view; arrows, undo and
    // Select all stay.
    Editor.keys.unshift(function (e, mod, k) {
        if (!this.lv) {
            return false;
        }

        if (mod) {
            return !/^[zZyYaA]$/.test(k);
        }

        return (k === 'Enter') || (k === 'Delete') || (k === 'Backspace');
    });

    // Escape with nothing picked ends the view as Done does.
    Editor.escapes.unshift(function () {
        if (!this.lv || this.sel.length) {
            return false;
        }

        this.leaveLanguage();

        return true;
    });

    ['editLayer', 'openMenu', 'onPaste'].forEach(function (name) {
        var own = Editor.prototype[name];

        if (own) {
            Editor.prototype[name] = function () {
                return this.lv ? false : own.apply(this, arguments);
            };
        }
    });

    // Save, Close and the Languages list first end the view: the canvas
    // document, never the view's, is saved or kept.
    ['save', 'requestClose', 'openLanguages'].forEach(function (name) {
        var own = Editor.prototype[name];

        Editor.prototype[name] = function () {
            if (this.lv) {
                this.leaveLanguage();
            }

            return own.apply(this, arguments);
        };
    });

    // Opened on a language (the queue's Look by hand row): its view once
    // the picture and its languages are both there.
    Editor.prototype.lvAsked = function () {
        var code = this.langAsk, data = this.langData;
        var lang = (code && this.ready && data && data.languages) ? data.languages.filter(function (l) { return l.code === code; })[0] : null;

        if (lang) {
            this.langAsk = '';

            if (M.languageDrawable(lang)) {
                this.enterLanguage(lang);
            }
        }
    };

    var setReadyLv = Editor.prototype.setReady, loadLanguagesLv = Editor.prototype.loadLanguages;

    Editor.prototype.setReady = function () {
        setReadyLv.apply(this, arguments);
        this.lvAsked();
    };

    Editor.prototype.loadLanguages = function () {
        var self = this;

        return loadLanguagesLv.apply(this, arguments).then(function (data) {
            self.lvAsked();

            return data;
        });
    };

}(window.PgImageEditor.parts));
