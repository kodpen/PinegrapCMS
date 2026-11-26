/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - My library's file. "Import to my library" opens the
 * browser's file chooser, sends the file's text for the import's checks
 * (designer/library_import: no script, no event attribute, no address
 * outside the allowed kinds) and shows, in one window, the check running,
 * then what was added item by item, or why the file was refused whole.
 * Export (designer/library_export) writes the chosen items, every item
 * when none is chosen, into one file named after the site and lets the
 * browser save it; "Manage library" calls it with its choice. Not needed
 * to open a page: the core runs this file the first time one of them is
 * used (_sdPartLoad(), pg_designer_parts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
StyleDesigner.definePart('libraryfile', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    // The largest file the server reads (PG_LIBRARY_EXPORT_MAX); a larger
    // one is refused here before it is sent, in the server's words.
    var MAX_BYTES = 1048576;
    var busy = false;

    function _lfUnreachable() {
        return _sdT('The library could not be reached. Try again.');
    }

    // Tells the editor's library group to read the library again.
    function _lfChanged() {
        try { document.dispatchEvent(new CustomEvent('sd-library-changed')); } catch (e) { /* old browser: the next open reads it */ }
    }

    function _lfSave(name, text) {
        var url = URL.createObjectURL(new Blob([text], { type: 'application/json' }));
        var a = document.createElement('a');
        a.href = url;
        a.download = name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    /**
     * Writes the chosen items (row ids; none for every item) into one file
     * and lets the browser save it; one line says how many and where.
     */
    function exportItems(ids) {
        if (busy) return Promise.resolve(false);
        busy = true;
        return K._pgApiPost('library_export', { ids: Array.isArray(ids) ? ids : [] }).then(function (res) {
            busy = false;
            if (!res || res.status !== 'success') {
                K.sdToast(esc((res && res.message) || _lfUnreachable()), 'danger');
                return false;
            }
            if (!res.count) {
                K.sdToast(esc(_sdT('My library is empty: there is nothing to export.')), 'info');
                return false;
            }
            _lfSave(res.name || 'library.json', res.text);
            K.sdToast(esc(_sdT('{var:1} items exported to {var:2}.', [res.count, res.name || 'library.json'])), 'success');
            return true;
        }, function () {
            busy = false;
            K.sdToast(esc(_lfUnreachable()), 'danger');
            return false;
        });
    }

    // ── The import window ─────────────────────────────────────────────

    // One window for the three states: checking, the report, refused.
    function _lfWindow(fileName) {
        var el = document.createElement('dialog');
        el.className = 'dialog pg-dialog-centered dialog-scrollable';
        el.tabIndex = -1;
        el.innerHTML =
            '<div class="dialog-header py-3"><div class="min-w-0">' +
                    '<h6 class="dialog-title mb-0 text-truncate" data-lf-title>' + esc(_sdT('Import to my library')) + '</h6>' +
                    '<div class="small fg-secondary text-truncate">' + esc(fileName) + '</div></div>' +
                '<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' + esc(_sdT('Close')) + '"></button></div>' +
            '<div class="dialog-body small" data-lf-body aria-live="polite"></div>' +
            '<div class="dialog-footer py-3" data-lf-foot></div>';
        document.body.appendChild(el);
        if (window.pgUI) {
            pgUI.dialog.on(el, 'hidden', function () { el.remove(); });
            pgUI.dialog.show(el);
        }
        return el;
    }

    function _lfClose(el) {
        if (window.pgUI) pgUI.dialog.hide(el); else el.remove();
    }

    function _lfFoot(el, label, solid) {
        el.querySelector('[data-lf-foot]').innerHTML =
            '<button type="button" class="pg-button ' + (solid ? 'pg-button-fill' : 'pg-button-quiet') + '" data-bs-dismiss="dialog">' + esc(label) + '</button>';
    }

    function _lfChecking(el) {
        el.querySelector('[data-lf-body]').innerHTML =
            '<div class="d-flex align-items-center gap-3 py-2"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>' +
            '<span>' + esc(_sdT('Checking the file…')) + '</span></div>';
        _lfFoot(el, _sdT('Cancel'), false);
    }

    function _lfRefused(el, message) {
        el.querySelector('[data-lf-title]').textContent = _sdT('This file was not imported');
        el.querySelector('[data-lf-body]').innerHTML =
            '<p class="mb-2">' + esc(message) + '</p>' +
            '<p class="fg-secondary mb-0">' + esc(_sdT('Nothing was added to the library.')) + '</p>';
        _lfFoot(el, _sdT('Close'), false);
    }

    // What the report says of one item kept (pg_library_import_plan()).
    function _lfNote(entry) {
        if (entry.note === 'name_kept') return _sdT('The library has an item of this name: both are kept.');
        if (entry.note === 'new_group') return _sdT('Added to the new group “{var:1}”.', entry.group);
        if (entry.note === 'group') return _sdT('Added to “{var:1}”.', entry.group);
        return _sdT('Added with no group.');
    }

    function _lfReport(el, rows) {
        var list = rows.map(function (r) {
            return '<li class="d-flex flex-column py-2 border-bottom">' +
                       '<span class="fw-semibold text-truncate">' + esc(r.name) + '</span>' +
                       '<span class="fg-secondary">' + esc(_lfNote(r)) + '</span>' +
                   '</li>';
        }).join('');
        el.querySelector('[data-lf-body]').innerHTML =
            '<ul class="list-unstyled mb-3">' + list + '</ul>' +
            '<p class="mb-0">' + esc(rows.length === 1
                ? _sdT('1 item added. It is in the Blocks list now.')
                : _sdT('{var:1} items added. They are in the Blocks list now.', rows.length)) + '</p>';
        _lfFoot(el, _sdT('Done'), true);
    }

    function _lfSend(file) {
        var el = _lfWindow(file.name);
        var open = true;
        if (window.pgUI) pgUI.dialog.on(el, 'hidden', function () { open = false; });
        if (file.size > MAX_BYTES) {
            _lfRefused(el, _sdT('“{var:1}” is larger than 1 MB.', file.name));
            return;
        }
        _lfChecking(el);
        busy = true;
        var reader = new FileReader();
        reader.onload = function () {
            // Cancelled while the file was read: nothing is sent.
            if (!open) { busy = false; return; }
            K._pgApiPost('library_import', { text: String(reader.result || ''), file: file.name }).then(function (res) {
                busy = false;
                var rows = (res && res.status === 'success' && Array.isArray(res.rows)) ? res.rows : null;
                if (rows) _lfChanged();
                if (!open) {
                    // Cancelled after the file was sent: the server kept it.
                    if (rows) K.sdToast(esc(_sdT('{var:1} items added. They are in the Blocks list now.', rows.length)), 'success');
                    return;
                }
                if (rows) _lfReport(el, rows);
                else _lfRefused(el, (res && res.message) || _lfUnreachable());
            }, function () {
                busy = false;
                if (open) _lfRefused(el, _lfUnreachable());
            });
        };
        reader.onerror = function () {
            busy = false;
            if (open) _lfRefused(el, _sdT('“{var:1}” is not a library file.', file.name));
        };
        reader.readAsText(file);
    }

    // Opens the browser's file chooser; the chosen file is checked before
    // anything is kept.
    function importFile() {
        if (busy) return;
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json,application/json';
        input.hidden = true;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            input.remove();
            if (file) _lfSend(file);
        });
        document.body.appendChild(input);
        input.click();
    }

    return { exportItems: exportItems, importFile: importFile };
});
