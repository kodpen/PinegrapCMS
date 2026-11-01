/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: versions: the saves of a file, opened, restored or deleted.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * Opening a file whose newest save kept its document loads the version that
 * document was drawn on (action picture) with the document, so layers and
 * texts edit again; a save names that version (base). The drawer in the
 * panel lists the versions (action versions): preview, restore, delete.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, button = X.button, config = X.config;

    X.icons.history = 'M4 12a8 8 0 1 0 2.4-5.7M4 4.5v3.8h3.8M12 8v4.5l3 1.8';

    // Bytes of a version's picture as an address the stage can draw (a blob
    // URL is the page's own origin, so a save may read it back from a canvas).
    function fetchPicture(fileId, versionId) {
        var cfg = config();

        return fetch(cfg.api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: cfg.token || '', action: 'picture', file_id: fileId, version_id: versionId })
        }).then(function (res) {
            var type = res.headers.get('Content-Type') || '';

            if (!res.ok || (type.indexOf('image/') !== 0)) {
                throw new Error('picture');
            }

            return res.blob();
        }).then(function (blob) {
            return URL.createObjectURL(blob);
        });
    }

    Editor.inits.push(function () {
        // The version the document is drawn on (0: the file as it is), how
        // many the file has, the drawer, a preview over the stage and the
        // file a restore wrote.
        this.versionBase = 0;
        this.versionCount = 0;
        this.versionsOpen = false;
        this.versionList = null;
        this.versionAsk = 0;
        this.versionPreview = null;
        this.versionUrls = [];
        this.restoredFile = null;
    });

    // A file in the File Manager, saved through the API: the only kind with
    // versions.
    Editor.prototype.hasVersions = function () {
        return !!this.opts.fileId && (this.saveMode !== 'callback') && !this.tools.mini && !!config().api;
    };

    Editor.topItems.push(function () {
        var self = this;

        if (!this.hasVersions()) {
            return null;
        }

        this.versionCountEl = el('span', { class: 'pgie-vcount' });
        this.versionsBtn = el('button', { type: 'button', class: 'pgie-btn pgie-vbtn', 'aria-pressed': 'false', title: t('versions'), onclick: function () { self.toggleVersions(); } }, [
            X.icon('history'), el('span', { class: 'pgie-vlabel', text: t('versions') }), this.versionCountEl
        ]);
        this.renderVersionCount();

        return this.versionsBtn;
    });

    Editor.prototype.renderVersionCount = function () {
        if (this.versionCountEl) {
            this.versionCountEl.textContent = this.versionCount ? String(this.versionCount) : '';
            this.versionCountEl.hidden = !this.versionCount;
        }
    };

    // The open answer: the newest save's document on its version, else the
    // file as it is.
    var openFile = Editor.prototype.openFile;

    Editor.prototype.openFile = function (r) {
        var self = this, doc = (r.base && !this.opts.src) ? M.versionOpenDoc(r.doc, this.opts.fileId) : null;

        this.versionCount = Number(r.versions) || 0;
        this.renderVersionCount();

        if (!doc) {
            this.versionBase = 0;
            openFile.call(this, r);
            return;
        }

        fetchPicture(this.opts.fileId, r.base).then(function (url) {
            self.versionUrls.push(url);
            self.versionBase = Number(r.base);
            self.doc = doc;
            self.layersDirty = true;
            self.loadImage(url);
        }, function () {
            // The version could not be read: the file opens as it is, its
            // layers drawn in.
            self.versionBase = 0;
            openFile.call(self, r);
        });
    };

    Editor.saveHooks.push(function (body, mode) {
        if ((mode === 'replace') || (mode === 'copy')) {
            body.base = this.versionBase;
        }
    });

    Editor.prototype.toggleVersions = function (open) {
        open = (open === undefined) ? !this.versionsOpen : open;
        this.versionsOpen = open;
        this.versionAsk = 0;
        this.endVersionPreview();

        if (this.versionsBtn) {
            this.versionsBtn.classList.toggle('on', open);
            this.versionsBtn.setAttribute('aria-pressed', open ? 'true' : 'false');
        }

        if (open) {
            this.versionsTool = this.tool;
            this.sheetOpen = true;
            this.loadVersions();
        }

        this.render();
    };

    Editor.prototype.loadVersions = function () {
        var self = this;

        this.versionList = null;
        this.request({ action: 'versions', file_id: this.opts.fileId }).then(function (r) {
            self.versionList = (r && (r.status === 'success')) ? r : { error: (r && r.message) || t('versionsError') };

            if (r && r.versions) {
                self.versionCount = r.versions.length;
                self.renderVersionCount();
            }

            if (self.versionsOpen) {
                self.renderPanel();
            }
        }, function () {
            self.versionList = { error: t('versionsError') };

            if (self.versionsOpen) {
                self.renderPanel();
            }
        });
    };

    // The drawer takes the panel while it is open; picking a tool closes it.
    var renderPanel = Editor.prototype.renderPanel;

    Editor.prototype.renderPanel = function () {
        if (this.versionsOpen && (this.tool !== this.versionsTool)) {
            this.versionsOpen = false;
            this.endVersionPreview();

            if (this.versionsBtn) {
                this.versionsBtn.classList.remove('on');
                this.versionsBtn.setAttribute('aria-pressed', 'false');
            }
        }

        if (!this.versionsOpen || !this.panel) {
            renderPanel.call(this);
            return;
        }

        var self = this, body = el('div', { class: 'pgie-pb pgie-versions' });

        this.panel.textContent = '';
        this.panel.setAttribute('aria-label', t('versions'));
        this.panel.appendChild(el('div', { class: 'pgie-ph' }, [
            el('h2', { text: t('versions') }),
            el('div', { class: 'pgie-grow' }),
            button('pgie-ibtn sm', t('closePanel'), 'close', function () { self.toggleVersions(false); })
        ]));
        body.appendChild(el('p', { class: 'pgie-vintro', text: t('versionsIntro') }));

        if (!this.versionList) {
            body.appendChild(el('p', { class: 'pgie-vnote', role: 'status', text: t('loading') }));
        } else if (this.versionList.error) {
            body.appendChild(el('p', { class: 'pgie-vnote pgie-error', role: 'alert', text: this.versionList.error }));
        } else if (!this.versionList.versions.length) {
            body.appendChild(el('p', { class: 'pgie-vnote', text: t('versionsNone') }));
        } else {
            var list = el('ul', { class: 'pgie-vlist' });

            this.versionList.versions.forEach(function (v) {
                list.appendChild(self.versionRow(v, self.versionList.restore));
            });
            body.appendChild(list);
            body.appendChild(el('p', { class: 'pgie-vnote', text: t('versionsKeep') }));
        }

        this.panel.appendChild(body);
    };

    Editor.prototype.versionRow = function (v, mayRestore) {
        var self = this, name = M.versionText(t('versionN'), v.no);
        var previewing = this.versionPreview && (this.versionPreview.id === v.id);
        var actions = el('div', { class: 'pgie-vact' });

        actions.appendChild(el('button', { type: 'button', class: 'pgie-btn sm' + (previewing ? ' on' : ''), 'aria-pressed': previewing ? 'true' : 'false', text: t('versionPreview'), onclick: function () {
            if (previewing) {
                self.endVersionPreview();
                self.renderPanel();
            } else {
                self.previewVersion(v);
            }
        } }));

        if (mayRestore && !v.current) {
            var ask = (this.versionAsk === v.id);

            actions.appendChild(el('button', { type: 'button', class: 'pgie-btn sm' + (ask ? ' pgie-danger' : ''), text: ask ? t('versionRestoreAsk') : t('versionRestore'), onclick: function () {
                if (self.dirty && !ask) {
                    self.versionAsk = v.id;
                    self.renderPanel();
                    return;
                }

                self.restoreVersion(v);
            } }));
        }

        if (v['delete']) {
            actions.appendChild(button('pgie-ibtn sm', t('versionDelete') + ': ' + name, 'trash', function () { self.deleteVersion(v); }));
        }

        return el('li', { class: 'pgie-vrow' + (v.current ? ' pgie-vcur' : '') }, [
            el('div', { class: 'pgie-vhead' }, [
                el('strong', { text: name }),
                v.current ? el('span', { class: 'pgie-vbadge', text: t('versionLatest') }) : null
            ]),
            el('div', { class: 'pgie-vmeta', text: M.versionLine(v, t('versionYou')) }),
            el('div', { class: 'pgie-vkind', text: t(M.versionKindKey(v.kind)) + ' · ' + v.width + ' × ' + v.height + ' px · ' + v.size }),
            actions
        ]);
    };

    // The version's picture over the stage until another one, Back or
    // closing the drawer; the document underneath is left as it is.
    Editor.prototype.previewVersion = function (v) {
        var self = this;

        this.endVersionPreview();
        this.versionPreview = { id: v.id };
        this.renderPanel();

        fetchPicture(this.opts.fileId, v.id).then(function (url) {
            self.versionUrls.push(url);

            if (!self.versionPreview || (self.versionPreview.id !== v.id)) {
                return;
            }

            var back = el('button', { type: 'button', class: 'pgie-btn sm', text: t('versionEndPreview'), onclick: function () {
                self.endVersionPreview();
                self.renderPanel();
            } });

            self.versionPreview.node = el('div', { class: 'pgie-vpreview' }, [
                el('img', { src: url, alt: M.versionText(t('versionPreviewing'), v.no) }),
                el('div', { class: 'pgie-vpbar', role: 'status' }, [el('span', { text: M.versionText(t('versionPreviewing'), v.no) }), back])
            ]);
            self.stage.appendChild(self.versionPreview.node);
        }, function () {
            self.versionPreview = null;
            self.renderPanel();
            self.showMessage(t('versionsError'), true);
        });
    };

    Editor.prototype.endVersionPreview = function () {
        if (this.versionPreview && this.versionPreview.node && this.versionPreview.node.parentNode) {
            this.versionPreview.node.parentNode.removeChild(this.versionPreview.node);
        }

        this.versionPreview = null;
    };

    // The server writes the version back to the file; the editor then opens
    // it afresh: its document on its version, a new history.
    Editor.prototype.restoreVersion = function (v) {
        var self = this;

        if (this.saving) {
            return;
        }

        this.versionAsk = 0;
        this.endVersionPreview();
        this.setSaving(true);
        this.request({ action: 'restore', file_id: this.opts.fileId, version_id: v.id }).then(function (r) {
            self.setSaving(false);

            if (!r || (r.status !== 'success')) {
                self.showMessage((r && r.message) || t('saveError'), true);
                return;
            }

            self.restoredFile = r.file;
            self.reopen(r);
            self.showMessage(M.versionText(t('versionRestoredMsg'), r.no));
            self.loadVersions();
        }, function () {
            self.setSaving(false);
            self.showMessage(t('saveError'), true);
        });
    };

    // A fresh start on what the server answered (restore): the picture, the
    // document, the history and the selection.
    Editor.prototype.reopen = function (r) {
        if (this.img && this.img.parentNode) {
            this.img.parentNode.removeChild(this.img);
        }

        this.img = null;
        this.ready = false;
        this.sel = [];
        this.doc = M.newDocument(this.opts);
        this.history = new M.History(100);
        this.opening = null;
        this.dirty = false;
        this.layersDirty = true;
        this.render();
        this.openFile({ file: { url: r.url }, versions: r.versions, doc: r.doc, base: r.base });
    };

    Editor.prototype.deleteVersion = function (v) {
        var self = this;

        if (this.versionPreview && (this.versionPreview.id === v.id)) {
            this.endVersionPreview();
        }

        this.request({ action: 'delete_version', file_id: this.opts.fileId, version_id: v.id }).then(function (r) {
            if (!r || (r.status !== 'success')) {
                self.showMessage((r && r.message) || t('versionsError'), true);
                return;
            }

            self.loadVersions();
        }, function () {
            self.showMessage(t('versionsError'), true);
        });
    };

    // A restore wrote the file: closing without a save still tells the
    // caller, so its list shows the restored picture.
    var finish = Editor.prototype.finish;

    Editor.prototype.finish = function (result) {
        this.versionUrls.forEach(function (url) { URL.revokeObjectURL(url); });
        this.versionUrls = [];

        if (!result && this.restoredFile && !this.finished) {
            result = { file: this.restoredFile, doc: M.clone(this.doc), fileId: this.restoredFile.id };

            if (typeof this.opts.onSave === 'function') {
                this.opts.onSave(result);
            }
        }

        finish.call(this, result);
    };
}(window.PgImageEditor.parts));
