/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - "Page from a document" part. On an empty page the
 * command palette's "Page from a document" opens the form and the rows
 * (window.PGDocumentRows) in the designer's own window; a design opened
 * with ?start=document builds its page from the rows the New design window
 * left in this tab's sessionStorage. Either way the rows become the page's
 * sections (window.PGDocumentPage) in one step of undo; a Word document's
 * pictures are stored in the file manager first (designer_document.php).
 * The texts are fetched once from designer_document.php; with Pinegrap AI
 * on (texts.sortOn) "Sort it for me" posts the rows' sections there. Not needed to
 * open a page: the core runs this file on first use (_sdPartLoad()) and
 * hands it the editor through a host (_sdDocumentHost()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
StyleDesigner.definePart('document', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var KEY = 'pg_document_build';
    var texts = null;
    var win = null;

    function url(query) {
        return (window.OUTPUT_PATH || '/') + (typeof software_directory !== 'undefined' ? software_directory : 'pinegrap') + '/designer_document.php' + (query || '');
    }

    function loadTexts() {
        if (texts) {
            return Promise.resolve(texts);
        }
        return fetch(url('?texts=1'), {credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (res) {
            if (!res || res.status !== 'success') {
                throw new Error('texts');
            }
            texts = res.texts;
            return texts;
        });
    }

    function post(fields) {
        var fd = new FormData();
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        fd.append('token', K._pgToken());
        return fetch(url(), {method: 'POST', body: fd, credentials: 'same-origin'}).then(function (r) { return r.json(); });
    }

    // The pictures the rows show from a Word document, stored first:
    // picture:<n> => their address in the file manager.
    function store(rows, keep, name) {
        var wanted = [];
        rows.forEach(function (r) {
            r.items.forEach(function (i) {
                var m = /^picture:(\d+)$/.exec(String(i.src || ''));
                if (m) {
                    wanted.push(+m[1]);
                }
            });
        });
        if (!keep || !wanted.length) {
            return Promise.resolve({});
        }
        return post({keep: keep, pictures: JSON.stringify(wanted), name: name}).then(function (res) {
            return (res && res.pictures) || {};
        }, function () { return {}; });
    }

    function build(host, rows, keep, name) {
        return Promise.all([store(rows, keep, name), host.builder()]).then(function (both) {
            var stored = both[0];
            var nodes = window.PGDocumentPage.build(rows, {
                make: host.make,
                node: K.createNode,
                src: function (src) {
                    var m = /^picture:(\d+)$/.exec(String(src));
                    if (m) {
                        return stored[m[1]] || '';
                    }
                    return /^(https?:\/\/|\/)/.test(String(src)) ? String(src) : '';
                }
            });
            host.insert(nodes);
            K.sdToast(esc((texts && texts.built) || _sdT('The page was built from the document.')), 'success');
        });
    }

    function close() {
        if (win) {
            if (win.form) {
                win.form.destroy();
            }
            window.pgUI.dialog.hide(win.el);
        }
    }

    function open(host) {
        loadTexts().then(function (t) {
            if (!win) {
                var el = document.createElement('dialog');
                el.className = 'dialog dialog-lg pg-dialog-centered dialog-scrollable';
                el.setAttribute('aria-labelledby', 'sd-doc-title');
                el.innerHTML = '<div class="dialog-header"><h2 class="dialog-title fs-lg" id="sd-doc-title"><i class="bi bi-file-earmark-text me-3" aria-hidden="true"></i>' + esc(_sdT('Page from a document')) + '</h2>'
                    + '<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' + esc(_sdT('Close')) + '"></button></div>'
                    + '<div class="dialog-body"></div><div class="dialog-footer"></div>';
                document.body.appendChild(el);
                win = {el: el, form: null};
                window.pgUI.dialog.on(el, 'hidden', function () {
                    if (win.form) {
                        win.form.destroy();
                        win.form = null;
                    }
                });
            }
            win.form = window.PGDocumentRows.mount({
                box: win.el.querySelector('.dialog-body'),
                foot: win.el.querySelector('.dialog-footer'),
                texts: t,
                head: true,
                reader: window.PGDocumentReader,
                dropArea: win.el,
                css: url('').replace(/designer_document\.php$/, 'assets/css/pg_document_rows.css'),
                readFile: function (file) { return post(file.id ? {file_id: file.id} : {file: file.file}); },
                sort: t.sortOn ? function (sections) { return post({sort: '1', sections: JSON.stringify(sections)}); } : null,
                pickFile: window.pgPicker ? function () { return window.pgPicker.open({kind: 'document', title: t.manager}); } : null,
                cancel: close,
                build: function (rows, answer) {
                    return build(host, rows, answer.keep, answer.name).then(close);
                }
            });
            window.pgUI.dialog.show(win.el);
        }).catch(K._sdPartFailed);
    }

    // ?start=document: the rows the New design window left, built once.
    function auto(host) {
        var raw = null;
        try {
            raw = JSON.parse(window.sessionStorage.getItem(KEY) || 'null');
            window.sessionStorage.removeItem(KEY);
        } catch (e) {
            raw = null;
        }
        if (!raw || !Array.isArray(raw.rows) || !host.empty()) {
            return;
        }
        loadTexts().catch(function () { return null; }).then(function () {
            return build(host, raw.rows, String(raw.keep || ''), String(raw.name || ''));
        });
    }

    return {open: open, auto: auto};
});
