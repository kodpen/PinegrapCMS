/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "From a document" start of the New design window
 * (pg_designer_start_pane()), loaded by assets/js/designer_home.js the
 * first time the start is chosen. It loads the reader and the rows
 * (pg_document_reader.js, pg_document_rows.js) and draws the form in the
 * pane, its buttons in the foot. A file is read by designer_document.php;
 * "Build the page" keeps the rows in this tab's sessionStorage, asks the
 * start questions and opens the new design with ?start=document, where the
 * designer builds the page (style_designer_document.js).
 *
 * The texts arrive translated in the pane's data-texts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var parts = window.sdStartParts = window.sdStartParts || {};

    if (parts.document) {
        return;
    }

    var C = {};

    try {
        C = JSON.parse((document.getElementById('sdh-config') || {}).textContent || '{}') || {};
    } catch (e) {
        C = {};
    }

    var base = C.partBase || 'assets/js/';
    var KEY = 'pg_document_build';
    var mounted = null;
    var loading = null;

    function script(name) {
        return new Promise(function (resolve, reject) {
            var el = document.createElement('script');
            el.src = base + name + (C.partStamp ? '?v=' + C.partStamp : '');
            el.onload = resolve;
            el.onerror = reject;
            document.head.appendChild(el);
        });
    }

    function token() {
        return (typeof software_token !== 'undefined') ? software_token : '';
    }

    function readFile(file) {
        var fd = new FormData();
        if (file.id) {
            fd.append('file_id', file.id);
        } else {
            fd.append('file', file.file, file.name);
        }
        fd.append('token', token());
        return fetch('designer_document.php', {method: 'POST', body: fd, credentials: 'same-origin'}).then(function (r) { return r.json(); });
    }

    parts.document = {
        show: function (pane, win) {
            var root = pane.querySelector('.sd-doc');
            var foot = win.querySelector('.sd-ns-part-foot');

            if (!root || !foot) {
                return;
            }
            foot.innerHTML = '';
            if (mounted) {
                mounted.foot = foot;
                return;
            }
            loading = loading || (window.PGDocumentRows ? Promise.resolve() : script('pg_document_reader.js').then(function () { return script('pg_document_rows.js'); }));
            loading.then(function () {
                if (mounted || pane.hidden) {
                    return;
                }
                var texts = JSON.parse(root.getAttribute('data-texts') || '{}');
                mounted = window.PGDocumentRows.mount({
                    box: root,
                    foot: foot,
                    texts: texts,
                    reader: window.PGDocumentReader,
                    dropArea: win,
                    css: base.replace(/js\/$/, 'css/') + 'pg_document_rows.css' + (C.partStamp ? '?v=' + C.partStamp : ''),
                    readFile: readFile,
                    pickFile: window.pgPicker ? function () { return window.pgPicker.open({kind: 'document', title: texts.manager}); } : null,
                    cancel: function () { window.pgUI.dialog.hide(win); },
                    build: function (rows, answer) {
                        try {
                            window.sessionStorage.setItem(KEY, JSON.stringify({rows: rows, keep: answer.keep || '', name: answer.name || ''}));
                        } catch (e) {
                            return Promise.reject(texts.network);
                        }
                        var href = root.getAttribute('data-href');
                        if (typeof window.sdStartAsk === 'function') {
                            foot.hidden = true;
                            window.sdStartAsk(pane, href);
                        } else {
                            window.location.href = href;
                        }
                        return Promise.resolve();
                    }
                });
            }, function () {
                loading = null;
            });
        }
    };
})();
